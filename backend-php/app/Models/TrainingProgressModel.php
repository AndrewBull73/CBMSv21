<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class TrainingProgressModel
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsTrainingProgress(): bool
    {
        $sql = "SELECT OBJECT_ID(N'dbo.tblTrainingProgress')";
        return (int) ($this->db->query($sql)->fetchColumn() ?: 0) > 0;
    }

    public function supportsTrainingAttempts(): bool
    {
        $sql = "SELECT OBJECT_ID(N'dbo.tblTrainingAttempts')";
        return (int) ($this->db->query($sql)->fetchColumn() ?: 0) > 0;
    }

    public function loadState(int $userId, string $scenarioCode): ?array
    {
        if ($userId <= 0 || $scenarioCode === '' || !$this->supportsTrainingProgress()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT TOP 1
                TrainingProgressID,
                UserID,
                ScenarioCode,
                ScreenFamily,
                Status,
                CurrentStep,
                TotalSteps,
                AttemptNo,
                SamplesJson,
                StartedAt,
                CompletedAt,
                StoppedAt,
                LastActivityAt
            FROM dbo.tblTrainingProgress
            WHERE UserID = :uid
              AND ScenarioCode = :scenario
            ORDER BY TrainingProgressID DESC
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':scenario' => $scenarioCode,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $samples = [];
        $rawSamples = (string) ($row['SamplesJson'] ?? '');
        if ($rawSamples !== '') {
            $decoded = json_decode($rawSamples, true);
            if (is_array($decoded)) {
                $samples = $decoded;
            }
        }

        return [
            'training_progress_id' => (int) ($row['TrainingProgressID'] ?? 0),
            'scenario_id' => (string) ($row['ScenarioCode'] ?? ''),
            'screen_family' => (string) ($row['ScreenFamily'] ?? ''),
            'status' => strtolower((string) ($row['Status'] ?? '')),
            'current_step' => (int) ($row['CurrentStep'] ?? 0),
            'total_steps' => (int) ($row['TotalSteps'] ?? 0),
            'attempt_no' => (int) ($row['AttemptNo'] ?? 1),
            'samples' => $samples,
            'started_at' => (string) ($row['StartedAt'] ?? ''),
            'completed_at' => $row['CompletedAt'] !== null ? (string) $row['CompletedAt'] : null,
            'stopped_at' => $row['StoppedAt'] !== null ? (string) $row['StoppedAt'] : null,
            'last_activity_at' => (string) ($row['LastActivityAt'] ?? ''),
        ];
    }

    public function startScenario(int $userId, array $state, ?int $updatedBy = null): array
    {
        if ($userId <= 0 || !$this->supportsTrainingProgress()) {
            return $state;
        }

        $existing = $this->loadState($userId, (string) ($state['scenario_id'] ?? ''));
        $attemptNo = (int) ($existing['attempt_no'] ?? 0) + 1;
        $state['attempt_no'] = max(1, $attemptNo);
        $state['status'] = 'active';
        $state['started_at'] = $this->dbUtcNow();
        $state['completed_at'] = null;
        $state['stopped_at'] = null;
        $state['last_activity_at'] = $state['started_at'];

        $this->upsertState($userId, $state, $updatedBy);
        $this->syncAttemptState($userId, $state, $updatedBy);

        return $this->loadState($userId, (string) ($state['scenario_id'] ?? '')) ?? $state;
    }

    public function saveState(int $userId, array $state, ?int $updatedBy = null): void
    {
        if ($userId <= 0 || !$this->supportsTrainingProgress()) {
            return;
        }

        $scenarioCode = trim((string) ($state['scenario_id'] ?? ''));
        $existing = $scenarioCode !== '' ? $this->loadState($userId, $scenarioCode) : null;
        $status = strtolower(trim((string) ($state['status'] ?? 'active'))) ?: 'active';
        $now = $this->dbUtcNow();

        if ($status === 'completed') {
            $state['completed_at'] = ($existing !== null && (string) ($existing['status'] ?? '') === 'completed')
                ? ($existing['completed_at'] ?? $state['completed_at'] ?? $now)
                : $now;
            $state['stopped_at'] = null;
        } elseif ($status === 'stopped') {
            $state['completed_at'] = null;
            $state['stopped_at'] = ($existing !== null && (string) ($existing['status'] ?? '') === 'stopped')
                ? ($existing['stopped_at'] ?? $state['stopped_at'] ?? $now)
                : $now;
        } else {
            $state['completed_at'] = null;
            $state['stopped_at'] = null;
        }

        $state['last_activity_at'] = $now;
        $this->upsertState($userId, $state, $updatedBy);
        $this->syncAttemptState($userId, $state, $updatedBy);
    }

    public function stopScenario(int $userId, string $scenarioCode, ?int $updatedBy = null): void
    {
        if ($userId <= 0 || $scenarioCode === '' || !$this->supportsTrainingProgress()) {
            return;
        }

        $state = $this->loadState($userId, $scenarioCode);
        $stoppedAt = $this->dbUtcNow();

        $stmt = $this->db->prepare("
            UPDATE dbo.tblTrainingProgress
            SET Status = N'stopped',
                StoppedAt = :stoppedAt,
                LastActivityAt = :stoppedAt,
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSUTCDATETIME()
            WHERE UserID = :uid
              AND ScenarioCode = :scenario
              AND ISNULL(Status, N'') <> N'completed'
        ");
        $stmt->execute([
            ':stoppedAt' => $stoppedAt,
            ':uid' => $userId,
            ':scenario' => $scenarioCode,
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
        ]);

        if ($stmt->rowCount() > 0 && is_array($state)) {
            $state['status'] = 'stopped';
            $state['completed_at'] = null;
            $state['stopped_at'] = $stoppedAt;
            $state['last_activity_at'] = $stoppedAt;
            $this->syncAttemptState($userId, $state, $updatedBy);
        }
    }

    public function listSummaries(array $filters = []): array
    {
        if (!$this->supportsTrainingProgress()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'tp.Status = :status';
            $params[':status'] = strtolower(trim((string) $filters['status']));
        }
        if (($filters['scenario_code'] ?? '') !== '') {
            $where[] = 'tp.ScenarioCode = :scenario';
            $params[':scenario'] = trim((string) $filters['scenario_code']);
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(
                u.Username LIKE :qUser
                OR u.DisplayName LIKE :qUser
                OR u.Email LIKE :qUser
                OR tp.ScenarioCode LIKE :qUser
            )';
            $params[':qUser'] = '%' . trim((string) $filters['q']) . '%';
        }

        $sql = "
            SELECT
                tp.TrainingProgressID,
                tp.UserID,
                tp.ScenarioCode,
                tp.ScreenFamily,
                tp.Status,
                tp.CurrentStep,
                tp.TotalSteps,
                tp.AttemptNo,
                tp.StartedAt,
                tp.CompletedAt,
                tp.StoppedAt,
                tp.LastActivityAt,
                u.Username,
                u.DisplayName,
                u.Email
            FROM dbo.tblTrainingProgress tp
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = tp.UserID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                ISNULL(tp.LastActivityAt, tp.StartedAt) DESC,
                tp.TrainingProgressID DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listUserStates(int $userId): array
    {
        if ($userId <= 0 || !$this->supportsTrainingProgress()) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                ScenarioCode,
                Status,
                CurrentStep,
                TotalSteps,
                AttemptNo,
                StartedAt,
                CompletedAt,
                StoppedAt,
                LastActivityAt
            FROM dbo.tblTrainingProgress
            WHERE UserID = :uid
            ORDER BY ScenarioCode
        ");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[(string) ($row['ScenarioCode'] ?? '')] = $row;
        }
        return $map;
    }

    public function resetScenarioForUser(int $userId, string $scenarioCode): int
    {
        if ($userId <= 0 || $scenarioCode === '' || !$this->supportsTrainingProgress()) {
            return 0;
        }

        $stmt = $this->db->prepare("
            DELETE FROM dbo.tblTrainingProgress
            WHERE UserID = :uid
              AND ScenarioCode = :scenario
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':scenario' => $scenarioCode,
        ]);

        return (int) $stmt->rowCount();
    }

    public function resetAll(): int
    {
        if (!$this->supportsTrainingProgress()) {
            return 0;
        }

        $stmt = $this->db->prepare("DELETE FROM dbo.tblTrainingProgress");
        $stmt->execute();
        return (int) $stmt->rowCount();
    }

    private function upsertState(int $userId, array $state, ?int $updatedBy = null): void
    {
        $scenarioCode = trim((string) ($state['scenario_id'] ?? ''));
        if ($scenarioCode === '') {
            return;
        }

        $screenFamily = trim((string) ($state['screen_family'] ?? ''));
        $status = strtolower(trim((string) ($state['status'] ?? 'active'))) ?: 'active';
        $currentStep = max(1, (int) ($state['current_step'] ?? 1));
        $totalSteps = max($currentStep, (int) ($state['total_steps'] ?? $currentStep));
        $attemptNo = max(1, (int) ($state['attempt_no'] ?? 1));
        $samplesJson = json_encode($state['samples'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $existing = $this->loadState($userId, $scenarioCode);
        $startedAt = trim((string) ($state['started_at'] ?? ''))
            ?: (string) ($existing['started_at'] ?? '')
            ?: $this->dbUtcNow();
        $completedAt = ($state['completed_at'] ?? null) ?: null;
        $stoppedAt = ($state['stopped_at'] ?? null) ?: null;
        $lastActivityAt = trim((string) ($state['last_activity_at'] ?? '')) ?: $this->dbUtcNow();

        if ($existing !== null) {
            $stmt = $this->db->prepare("
                UPDATE dbo.tblTrainingProgress
                SET ScreenFamily = :screenFamily,
                    Status = :status,
                    CurrentStep = :currentStep,
                    TotalSteps = :totalSteps,
                    AttemptNo = :attemptNo,
                    SamplesJson = :samplesJson,
                    StartedAt = :startedAt,
                    CompletedAt = :completedAt,
                    StoppedAt = :stoppedAt,
                    LastActivityAt = :lastActivityAt,
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE UserID = :uid
                  AND ScenarioCode = :scenarioCode
            ");
            $stmt->execute([
                ':screenFamily' => $screenFamily !== '' ? $screenFamily : null,
                ':status' => $status,
                ':currentStep' => $currentStep,
                ':totalSteps' => $totalSteps,
                ':attemptNo' => $attemptNo,
                ':samplesJson' => $samplesJson,
                ':startedAt' => $startedAt,
                ':completedAt' => $completedAt,
                ':stoppedAt' => $stoppedAt,
                ':lastActivityAt' => $lastActivityAt,
                ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
                ':uid' => $userId,
                ':scenarioCode' => $scenarioCode,
            ]);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblTrainingProgress
                (UserID, ScenarioCode, ScreenFamily, Status, CurrentStep, TotalSteps, AttemptNo, SamplesJson, StartedAt, CompletedAt, StoppedAt, LastActivityAt, CreatedBy, CreatedDate, UpdatedBy, UpdatedDate)
            VALUES
                (:uid, :scenarioCode, :screenFamily, :status, :currentStep, :totalSteps, :attemptNo, :samplesJson, :startedAt, :completedAt, :stoppedAt, :lastActivityAt, :createdBy, SYSUTCDATETIME(), :updatedBy, SYSUTCDATETIME())
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':scenarioCode' => $scenarioCode,
            ':screenFamily' => $screenFamily !== '' ? $screenFamily : null,
            ':status' => $status,
            ':currentStep' => $currentStep,
            ':totalSteps' => $totalSteps,
            ':attemptNo' => $attemptNo,
            ':samplesJson' => $samplesJson,
            ':startedAt' => $startedAt,
            ':completedAt' => $completedAt,
            ':stoppedAt' => $stoppedAt,
            ':lastActivityAt' => $lastActivityAt,
            ':createdBy' => $updatedBy > 0 ? $updatedBy : null,
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
        ]);
    }

    private function syncAttemptState(int $userId, array $state, ?int $updatedBy = null): void
    {
        if ($userId <= 0 || !$this->supportsTrainingAttempts()) {
            return;
        }

        $scenarioCode = trim((string) ($state['scenario_id'] ?? ''));
        if ($scenarioCode === '') {
            return;
        }

        $this->ensureAttemptRow($userId, $state, $updatedBy);

        $status = strtolower(trim((string) ($state['status'] ?? 'active'))) ?: 'active';
        $totalSteps = max(1, (int) ($state['total_steps'] ?? 1));
        $currentStep = max(1, (int) ($state['current_step'] ?? 1));
        $completedSteps = $status === 'completed'
            ? $totalSteps
            : min($totalSteps, max(0, $currentStep - 1));
        $samplesJson = json_encode($state['samples'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $completedAt = $status === 'completed' ? (($state['completed_at'] ?? null) ?: null) : null;
        $stoppedAt = $status === 'stopped' ? (($state['stopped_at'] ?? null) ?: null) : null;
        $lastActivityAt = trim((string) ($state['last_activity_at'] ?? '')) ?: $this->dbUtcNow();

        $stmt = $this->db->prepare("
            UPDATE dbo.tblTrainingAttempts
            SET ScreenFamily = :screenFamily,
                Status = :status,
                TotalSteps = :totalSteps,
                CompletedSteps = :completedSteps,
                SamplesJson = :samplesJson,
                CompletedAt = :completedAt,
                StoppedAt = :stoppedAt,
                LastActivityAt = :lastActivityAt,
                DurationSeconds = CASE
                    WHEN :completedAtForDuration IS NOT NULL THEN DATEDIFF(SECOND, StartedAt, :completedAtDurationValue)
                    WHEN :stoppedAtForDuration IS NOT NULL THEN DATEDIFF(SECOND, StartedAt, :stoppedAtDurationValue)
                    ELSE NULL
                END,
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSUTCDATETIME()
            WHERE UserID = :uid
              AND ScenarioCode = :scenarioCode
              AND AttemptNo = :attemptNo
        ");
        $stmt->execute([
            ':screenFamily' => $this->nullIfEmpty($state['screen_family'] ?? null),
            ':status' => $status,
            ':totalSteps' => $totalSteps,
            ':completedSteps' => $completedSteps,
            ':samplesJson' => $samplesJson,
            ':completedAt' => $completedAt,
            ':stoppedAt' => $stoppedAt,
            ':lastActivityAt' => $lastActivityAt,
            ':completedAtForDuration' => $completedAt,
            ':completedAtDurationValue' => $completedAt,
            ':stoppedAtForDuration' => $stoppedAt,
            ':stoppedAtDurationValue' => $stoppedAt,
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
            ':uid' => $userId,
            ':scenarioCode' => $scenarioCode,
            ':attemptNo' => max(1, (int) ($state['attempt_no'] ?? 1)),
        ]);
    }

    private function ensureAttemptRow(int $userId, array $state, ?int $updatedBy = null): void
    {
        $scenarioCode = trim((string) ($state['scenario_id'] ?? ''));
        if ($scenarioCode === '') {
            return;
        }

        $startedAt = trim((string) ($state['started_at'] ?? '')) ?: $this->dbUtcNow();
        $lastActivityAt = trim((string) ($state['last_activity_at'] ?? '')) ?: $startedAt;
        $samplesJson = json_encode($state['samples'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblTrainingAttempts
                (UserID, ScenarioCode, ScreenFamily, AttemptNo, Status, TotalSteps, CompletedSteps, SamplesJson, StartedAt, LastActivityAt, CreatedBy, CreatedDate, UpdatedBy, UpdatedDate)
            SELECT
                :uid,
                :scenarioCode,
                :screenFamily,
                :attemptNo,
                :status,
                :totalSteps,
                0,
                :samplesJson,
                :startedAt,
                :lastActivityAt,
                :createdBy,
                SYSUTCDATETIME(),
                :updatedBy,
                SYSUTCDATETIME()
            WHERE NOT EXISTS (
                SELECT 1
                FROM dbo.tblTrainingAttempts
                WHERE UserID = :existsUid
                  AND ScenarioCode = :existsScenarioCode
                  AND AttemptNo = :existsAttemptNo
            )
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':scenarioCode' => $scenarioCode,
            ':screenFamily' => $this->nullIfEmpty($state['screen_family'] ?? null),
            ':attemptNo' => max(1, (int) ($state['attempt_no'] ?? 1)),
            ':status' => strtolower(trim((string) ($state['status'] ?? 'active'))) ?: 'active',
            ':totalSteps' => max(1, (int) ($state['total_steps'] ?? 1)),
            ':samplesJson' => $samplesJson,
            ':startedAt' => $startedAt,
            ':lastActivityAt' => $lastActivityAt,
            ':createdBy' => $updatedBy > 0 ? $updatedBy : null,
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
            ':existsUid' => $userId,
            ':existsScenarioCode' => $scenarioCode,
            ':existsAttemptNo' => max(1, (int) ($state['attempt_no'] ?? 1)),
        ]);
    }

    private function dbUtcNow(): string
    {
        return (string) $this->db
            ->query("SELECT CONVERT(VARCHAR(19), SYSUTCDATETIME(), 120)")
            ->fetchColumn();
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
