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
        $state['completed_at'] = null;
        $state['stopped_at'] = null;
        $state['last_activity_at'] = gmdate('Y-m-d H:i:s');

        $this->upsertState($userId, $state, $updatedBy);
        return $state;
    }

    public function saveState(int $userId, array $state, ?int $updatedBy = null): void
    {
        if ($userId <= 0 || !$this->supportsTrainingProgress()) {
            return;
        }

        $state['last_activity_at'] = gmdate('Y-m-d H:i:s');
        $this->upsertState($userId, $state, $updatedBy);
    }

    public function stopScenario(int $userId, string $scenarioCode, ?int $updatedBy = null): void
    {
        if ($userId <= 0 || $scenarioCode === '' || !$this->supportsTrainingProgress()) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblTrainingProgress
            SET Status = N'stopped',
                StoppedAt = SYSDATETIME(),
                LastActivityAt = SYSDATETIME(),
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE UserID = :uid
              AND ScenarioCode = :scenario
              AND ISNULL(Status, N'') <> N'completed'
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':scenario' => $scenarioCode,
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
        ]);
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

        $screenFamily = trim((string) ($state['screen_family'] ?? 'users'));
        $status = strtolower(trim((string) ($state['status'] ?? 'active'))) ?: 'active';
        $currentStep = max(1, (int) ($state['current_step'] ?? 1));
        $totalSteps = max($currentStep, (int) ($state['total_steps'] ?? $currentStep));
        $attemptNo = max(1, (int) ($state['attempt_no'] ?? 1));
        $samplesJson = json_encode($state['samples'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $startedAt = trim((string) ($state['started_at'] ?? '')) ?: gmdate('Y-m-d H:i:s');
        $completedAt = ($state['completed_at'] ?? null) ?: null;
        $stoppedAt = ($state['stopped_at'] ?? null) ?: null;

        $existing = $this->loadState($userId, $scenarioCode);
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
                    LastActivityAt = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
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
                (:uid, :scenarioCode, :screenFamily, :status, :currentStep, :totalSteps, :attemptNo, :samplesJson, :startedAt, :completedAt, :stoppedAt, SYSDATETIME(), :createdBy, SYSDATETIME(), :updatedBy, SYSDATETIME())
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
            ':createdBy' => $updatedBy > 0 ? $updatedBy : null,
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
        ]);
    }
}
