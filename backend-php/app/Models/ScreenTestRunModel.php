<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class ScreenTestRunModel
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsScreenTestRuns(): bool
    {
        $sql = "SELECT OBJECT_ID(N'dbo.tblScreenTestRuns')";
        return (int) ($this->db->query($sql)->fetchColumn() ?: 0) > 0;
    }

    public function supportsScreenTestRunAttachments(): bool
    {
        $sql = "SELECT OBJECT_ID(N'dbo.tblScreenTestRunAttachment')";
        return (int) ($this->db->query($sql)->fetchColumn() ?: 0) > 0;
    }

    public function nextAttemptNo(int $userId, string $scenarioCode): int
    {
        if ($userId <= 0 || $scenarioCode === '' || !$this->supportsScreenTestRuns()) {
            return 1;
        }

        $stmt = $this->db->prepare("
            SELECT ISNULL(MAX(AttemptNo), 0) + 1
            FROM dbo.tblScreenTestRuns
            WHERE UserID = :uid
              AND ScenarioCode = :scenario
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':scenario' => $scenarioCode,
        ]);

        return max(1, (int) ($stmt->fetchColumn() ?: 1));
    }

    public function recordRun(array $run): int
    {
        if (!$this->supportsScreenTestRuns()) {
            return 0;
        }

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblScreenTestRuns
                (
                    UserID,
                    ScenarioCode,
                    ScenarioTitle,
                    ModuleName,
                    ScreenFamily,
                    TargetRoute,
                    RunResult,
                    VerificationStatus,
                    AttemptNo,
                    FiscalYearID,
                    VersionID,
                    DataObjectCode,
                    ContextJson,
                    TestDataJson,
                    OutcomeSummary,
                    DefectReference,
                    TesterNotes,
                    StartedAt,
                    CompletedAt,
                    DurationSeconds,
                    CreatedBy,
                    CreatedDate,
                    UpdatedBy,
                    UpdatedDate
                )
            VALUES
                (
                    :userId,
                    :scenarioCode,
                    :scenarioTitle,
                    :moduleName,
                    :screenFamily,
                    :targetRoute,
                    :runResult,
                    :verificationStatus,
                    :attemptNo,
                    :fiscalYearId,
                    :versionId,
                    :dataObjectCode,
                    :contextJson,
                    :testDataJson,
                    :outcomeSummary,
                    :defectReference,
                    :testerNotes,
                    :startedAt,
                    :completedAt,
                    :durationSeconds,
                    :createdBy,
                    SYSDATETIME(),
                    :updatedBy,
                    SYSDATETIME()
                )
        ");

        $stmt->execute([
            ':userId' => (int) ($run['user_id'] ?? 0),
            ':scenarioCode' => (string) ($run['scenario_code'] ?? ''),
            ':scenarioTitle' => (string) ($run['scenario_title'] ?? ''),
            ':moduleName' => (string) ($run['module_name'] ?? ''),
            ':screenFamily' => (string) ($run['screen_family'] ?? ''),
            ':targetRoute' => (string) ($run['target_route'] ?? ''),
            ':runResult' => (string) ($run['run_result'] ?? 'blocked'),
            ':verificationStatus' => (string) ($run['verification_status'] ?? 'not_run'),
            ':attemptNo' => max(1, (int) ($run['attempt_no'] ?? 1)),
            ':fiscalYearId' => (int) ($run['fiscal_year_id'] ?? 0) ?: null,
            ':versionId' => (int) ($run['version_id'] ?? 0) ?: null,
            ':dataObjectCode' => trim((string) ($run['dataobject_code'] ?? '')) ?: null,
            ':contextJson' => json_encode($run['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':testDataJson' => json_encode($run['test_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':outcomeSummary' => trim((string) ($run['outcome_summary'] ?? '')) ?: null,
            ':defectReference' => trim((string) ($run['defect_reference'] ?? '')) ?: null,
            ':testerNotes' => trim((string) ($run['tester_notes'] ?? '')) ?: null,
            ':startedAt' => (string) ($run['started_at'] ?? gmdate('Y-m-d H:i:s')),
            ':completedAt' => (string) ($run['completed_at'] ?? gmdate('Y-m-d H:i:s')),
            ':durationSeconds' => max(0, (int) ($run['duration_seconds'] ?? 0)),
            ':createdBy' => (int) ($run['created_by'] ?? $run['user_id'] ?? 0) ?: null,
            ':updatedBy' => (int) ($run['updated_by'] ?? $run['user_id'] ?? 0) ?: null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listRuns(int $userId, array $filters = [], bool $includeAll = false): array
    {
        if (!$this->supportsScreenTestRuns()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (!$includeAll) {
            $where[] = 'r.UserID = :uid';
            $params[':uid'] = $userId;
        }

        if (($filters['scenario_code'] ?? '') !== '') {
            $where[] = 'r.ScenarioCode = :scenarioCode';
            $params[':scenarioCode'] = trim((string) $filters['scenario_code']);
        }
        if (($filters['module'] ?? '') !== '') {
            $where[] = 'r.ModuleName = :moduleName';
            $params[':moduleName'] = trim((string) $filters['module']);
        }
        if (($filters['result'] ?? '') !== '') {
            $where[] = 'r.RunResult = :runResult';
            $params[':runResult'] = strtolower(trim((string) $filters['result']));
        }
        if (($filters['verification'] ?? '') !== '') {
            $where[] = 'r.VerificationStatus = :verificationStatus';
            $params[':verificationStatus'] = strtolower(trim((string) $filters['verification']));
        }
        if (($filters['q'] ?? '') !== '') {
            $needle = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(
                r.ScenarioCode LIKE :q1
                OR r.ScenarioTitle LIKE :q2
                OR r.ModuleName LIKE :q3
                OR r.DefectReference LIKE :q4
                OR r.OutcomeSummary LIKE :q5
                OR r.TesterNotes LIKE :q6
                OR u.Username LIKE :q7
                OR u.DisplayName LIKE :q8
            )';
            $params[':q1'] = $needle;
            $params[':q2'] = $needle;
            $params[':q3'] = $needle;
            $params[':q4'] = $needle;
            $params[':q5'] = $needle;
            $params[':q6'] = $needle;
            $params[':q7'] = $needle;
            $params[':q8'] = $needle;
        }

        $sql = "
            SELECT
                r.ScreenTestRunID,
                r.UserID,
                r.ScenarioCode,
                r.ScenarioTitle,
                r.ModuleName,
                r.ScreenFamily,
                r.TargetRoute,
                r.RunResult,
                r.VerificationStatus,
                r.AttemptNo,
                r.FiscalYearID,
                r.VersionID,
                r.DataObjectCode,
                r.ContextJson,
                r.TestDataJson,
                r.OutcomeSummary,
                r.DefectReference,
                r.TesterNotes,
                r.StartedAt,
                r.CompletedAt,
                r.DurationSeconds,
                u.Username,
                u.DisplayName
            FROM dbo.tblScreenTestRuns r
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = r.UserID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                ISNULL(r.CompletedAt, r.StartedAt) DESC,
                r.ScreenTestRunID DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRunById(int $runId): ?array
    {
        if ($runId <= 0 || !$this->supportsScreenTestRuns()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                r.ScreenTestRunID,
                r.UserID,
                r.ScenarioCode,
                r.ScenarioTitle,
                r.ModuleName,
                r.ScreenFamily,
                r.TargetRoute,
                r.RunResult,
                r.VerificationStatus,
                r.AttemptNo,
                r.FiscalYearID,
                r.VersionID,
                r.DataObjectCode,
                r.ContextJson,
                r.TestDataJson,
                r.OutcomeSummary,
                r.DefectReference,
                r.TesterNotes,
                r.StartedAt,
                r.CompletedAt,
                r.DurationSeconds,
                u.Username,
                u.DisplayName
            FROM dbo.tblScreenTestRuns r
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = r.UserID
            WHERE r.ScreenTestRunID = :runId
        ");
        $stmt->execute([':runId' => $runId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveAttachment(int $runId, array $data): int
    {
        if ($runId <= 0 || !$this->supportsScreenTestRunAttachments()) {
            throw new \RuntimeException('Screen test run attachment storage is not available.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblScreenTestRunAttachment (
                ScreenTestRunID,
                OriginalFileName,
                StoredFileName,
                MimeType,
                FileSizeBytes,
                StoragePath,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ScreenTestRunID,
                :OriginalFileName,
                :StoredFileName,
                :MimeType,
                :FileSizeBytes,
                :StoragePath,
                1,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':ScreenTestRunID' => $runId,
            ':OriginalFileName' => trim((string) ($data['OriginalFileName'] ?? '')),
            ':StoredFileName' => trim((string) ($data['StoredFileName'] ?? '')),
            ':MimeType' => $this->nullableString($data['MimeType'] ?? null),
            ':FileSizeBytes' => max(0, (int) ($data['FileSizeBytes'] ?? 0)),
            ':StoragePath' => trim((string) ($data['StoragePath'] ?? '')),
            ':CreatedBy' => (int) ($data['UserID'] ?? 1),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listAttachmentsByRunIds(array $runIds): array
    {
        $runIds = array_values(array_filter(array_map('intval', $runIds), static fn (int $id): bool => $id > 0));
        if ($runIds === [] || !$this->supportsScreenTestRunAttachments()) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($runIds as $index => $runId) {
            $placeholder = ':run' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $runId;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dbo.tblScreenTestRunAttachment
            WHERE ActiveFlag = 1
              AND ScreenTestRunID IN (" . implode(', ', $placeholders) . ")
            ORDER BY ScreenTestRunID DESC, ScreenTestRunAttachmentID DESC
        ");
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) ($row['ScreenTestRunID'] ?? 0)][] = $row;
        }

        return $grouped;
    }

    public function getAttachment(int $attachmentId): ?array
    {
        if ($attachmentId <= 0 || !$this->supportsScreenTestRunAttachments()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dbo.tblScreenTestRunAttachment
            WHERE ScreenTestRunAttachmentID = :attachmentId
              AND ActiveFlag = 1
        ");
        $stmt->execute([':attachmentId' => $attachmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : null;
    }
}
