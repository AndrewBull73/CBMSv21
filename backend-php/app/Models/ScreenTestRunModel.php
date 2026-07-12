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

    public function supportsScreenTestAssignments(): bool
    {
        $sql = "SELECT OBJECT_ID(N'dbo.tblScreenTestAssignments')";
        return (int) ($this->db->query($sql)->fetchColumn() ?: 0) > 0;
    }

    public function searchUsers(string $query = '', int $limit = 50): array
    {
        $query = trim($query);
        $limit = max(1, min(100, $limit));
        $where = 'ISNULL(IsActive, 1) = 1';
        $params = [];
        if ($query !== '') {
            $where .= " AND (
                Username LIKE :q_username
                OR Email LIKE :q_email
                OR DisplayName LIKE :q_display
                OR FirstName LIKE :q_first
                OR LastName LIKE :q_last
                OR CONVERT(NVARCHAR(20), UserID) = :exact_id
            )";
            $likeQuery = '%' . $query . '%';
            $params[':q_username'] = $likeQuery;
            $params[':q_email'] = $likeQuery;
            $params[':q_display'] = $likeQuery;
            $params[':q_first'] = $likeQuery;
            $params[':q_last'] = $likeQuery;
            $params[':exact_id'] = $query;
        }

        $stmt = $this->db->prepare("
            SELECT TOP ({$limit})
                UserID,
                Username,
                LTRIM(RTRIM(DisplayName)) AS DisplayName,
                Email
            FROM dbo.tblUsers
            WHERE {$where}
            ORDER BY LTRIM(RTRIM(DisplayName)) ASC, Username ASC
        ");
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    public function saveAssignments(array $data, int $updatedBy): array
    {
        if (!$this->supportsScreenTestAssignments()) {
            throw new \RuntimeException('Screen test assignment storage is not available.');
        }

        $userIds = array_values(array_unique($this->parseIntList((string) ($data['UserIDs'] ?? ''))));
        $scenarioCodes = array_values(array_unique(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            is_array($data['ScenarioCodes'] ?? null) ? $data['ScenarioCodes'] : explode(',', (string) ($data['ScenarioCodes'] ?? ''))
        ), static fn(string $value): bool => $value !== '')));
        $moduleByScenario = is_array($data['ModuleByScenario'] ?? null) ? $data['ModuleByScenario'] : [];
        $dueDate = $this->nullableString($data['DueDate'] ?? null);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $auditUserId = $updatedBy > 0 ? $updatedBy : null;

        if ($userIds === [] || $scenarioCodes === []) {
            throw new \RuntimeException('At least one user and one test script are required.');
        }

        $duplicateStmt = $this->db->prepare("
            SELECT TOP 1 ScreenTestAssignmentID
            FROM dbo.tblScreenTestAssignments
            WHERE UserID = :UserID
              AND ScenarioCode = :ScenarioCode
              AND ActiveFlag = 1
              AND Status IN (N'assigned', N'in_progress')
        ");

        $insertStmt = $this->db->prepare("
            INSERT INTO dbo.tblScreenTestAssignments
                (UserID, ScenarioCode, ModuleName, DueDate, Status, AssignedBy, Notes, ActiveFlag, CreatedBy, UpdatedBy)
            VALUES
                (:UserID, :ScenarioCode, :ModuleName, :DueDate, N'assigned', :AssignedBy, :Notes, 1, :CreatedBy, :UpdatedBy)
        ");

        $created = 0;
        $skipped = 0;
        foreach ($userIds as $userId) {
            foreach ($scenarioCodes as $scenarioCode) {
                $duplicateStmt->execute([
                    ':UserID' => $userId,
                    ':ScenarioCode' => $scenarioCode,
                ]);
                if ($duplicateStmt->fetchColumn()) {
                    $skipped++;
                    continue;
                }

                $insertStmt->execute([
                    ':UserID' => $userId,
                    ':ScenarioCode' => $scenarioCode,
                    ':ModuleName' => $this->nullableString($moduleByScenario[$scenarioCode] ?? null),
                    ':DueDate' => $dueDate,
                    ':AssignedBy' => $auditUserId,
                    ':Notes' => $notes,
                    ':CreatedBy' => $auditUserId,
                    ':UpdatedBy' => $auditUserId,
                ]);
                $created++;
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function listAssignments(array $filters = []): array
    {
        if (!$this->supportsScreenTestAssignments()) {
            return [];
        }

        $where = ['a.ActiveFlag = 1'];
        $params = [];

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $where[] = 'a.UserID = :userId';
            $params[':userId'] = $userId;
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status === 'open') {
            $where[] = "a.Status IN (N'assigned', N'in_progress')";
        } elseif ($status !== '') {
            $where[] = 'a.Status = :status';
            $params[':status'] = $status;
        }
        $scenarioCode = trim((string) ($filters['scenario_code'] ?? ''));
        if ($scenarioCode !== '') {
            $where[] = 'a.ScenarioCode = :scenarioCode';
            $params[':scenarioCode'] = $scenarioCode;
        }
        $moduleName = trim((string) ($filters['module'] ?? ''));
        if ($moduleName !== '') {
            $where[] = 'a.ModuleName = :moduleName';
            $params[':moduleName'] = $moduleName;
        }

        $stmt = $this->db->prepare("
            SELECT
                a.ScreenTestAssignmentID,
                a.UserID,
                a.ScenarioCode,
                a.ModuleName,
                a.DueDate,
                a.Status,
                a.AssignedBy,
                a.AssignedAt,
                a.CompletedAt,
                a.Notes,
                u.Username,
                u.DisplayName,
                u.Email,
                assignedByName = COALESCE(NULLIF(LTRIM(RTRIM(ab.DisplayName)), N''), ab.Username)
            FROM dbo.tblScreenTestAssignments a
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = a.UserID
            LEFT JOIN dbo.tblUsers ab
                ON ab.UserID = a.AssignedBy
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                ISNULL(a.DueDate, CONVERT(date, '9999-12-31')),
                a.AssignedAt DESC,
                COALESCE(NULLIF(LTRIM(RTRIM(u.DisplayName)), N''), u.Username),
                a.ScenarioCode
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cancelAssignment(int $assignmentId, int $updatedBy): bool
    {
        if ($assignmentId <= 0 || !$this->supportsScreenTestAssignments()) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblScreenTestAssignments
            SET Status = N'cancelled',
                ActiveFlag = 0,
                UpdatedBy = :UpdatedBy,
                UpdatedDate = SYSUTCDATETIME()
            WHERE ScreenTestAssignmentID = :ScreenTestAssignmentID
              AND Status IN (N'assigned', N'in_progress')
        ");
        $stmt->execute([
            ':ScreenTestAssignmentID' => $assignmentId,
            ':UpdatedBy' => $updatedBy > 0 ? $updatedBy : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function cleanupAssignments(array $filters, int $updatedBy): int
    {
        if (!$this->supportsScreenTestAssignments()) {
            return 0;
        }

        $scope = strtolower(trim((string) ($filters['scope'] ?? 'completed')));
        $where = ['ActiveFlag = 1'];
        $params = [
            ':UpdatedBy' => $updatedBy > 0 ? $updatedBy : null,
        ];

        if ($scope === 'closed') {
            $where[] = "Status IN (N'completed', N'cancelled')";
        } elseif ($scope === 'overdue_open') {
            $where[] = "Status IN (N'assigned', N'in_progress')";
            $where[] = 'DueDate IS NOT NULL';
            $where[] = 'DueDate < CAST(GETDATE() AS date)';
        } else {
            $where[] = "Status = N'completed'";
        }

        $moduleName = trim((string) ($filters['module'] ?? ''));
        if ($moduleName !== '') {
            $where[] = 'ModuleName = :ModuleName';
            $params[':ModuleName'] = $moduleName;
        }

        $dueBefore = trim((string) ($filters['due_before'] ?? ''));
        if ($dueBefore !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueBefore)) {
            $where[] = 'DueDate IS NOT NULL';
            $where[] = 'DueDate <= :DueBefore';
            $params[':DueBefore'] = $dueBefore;
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblScreenTestAssignments
            SET ActiveFlag = 0,
                UpdatedBy = :UpdatedBy,
                UpdatedDate = SYSUTCDATETIME()
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function resetAssignment(int $assignmentId, int $updatedBy): bool
    {
        if ($assignmentId <= 0 || !$this->supportsScreenTestAssignments()) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblScreenTestAssignments
            SET Status = N'assigned',
                CompletedAt = NULL,
                UpdatedBy = :UpdatedBy,
                UpdatedDate = SYSUTCDATETIME()
            WHERE ScreenTestAssignmentID = :ScreenTestAssignmentID
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':ScreenTestAssignmentID' => $assignmentId,
            ':UpdatedBy' => $updatedBy > 0 ? $updatedBy : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markAssignmentsAfterRun(int $userId, string $scenarioCode, string $runResult, int $updatedBy): void
    {
        if ($userId <= 0 || trim($scenarioCode) === '' || !$this->supportsScreenTestAssignments()) {
            return;
        }

        $isPassed = strtolower(trim($runResult)) === 'passed';
        $stmt = $this->db->prepare("
            UPDATE dbo.tblScreenTestAssignments
            SET Status = :Status,
                CompletedAt = CASE WHEN :CompletedFlag = 1 THEN SYSUTCDATETIME() ELSE CompletedAt END,
                UpdatedBy = :UpdatedBy,
                UpdatedDate = SYSUTCDATETIME()
            WHERE UserID = :UserID
              AND ScenarioCode = :ScenarioCode
              AND ActiveFlag = 1
              AND Status IN (N'assigned', N'in_progress')
        ");
        $stmt->execute([
            ':Status' => $isPassed ? 'completed' : 'in_progress',
            ':CompletedFlag' => $isPassed ? 1 : 0,
            ':UpdatedBy' => $updatedBy > 0 ? $updatedBy : null,
            ':UserID' => $userId,
            ':ScenarioCode' => trim($scenarioCode),
        ]);
    }

    public function summarizeAssignments(int $userId, array $filters = [], bool $includeAll = false): array
    {
        $empty = [
            'total' => 0,
            'assigned' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0,
        ];
        if (!$this->supportsScreenTestAssignments()) {
            return $empty;
        }

        $where = ['a.ActiveFlag = 1'];
        $params = [];
        if (!$includeAll) {
            $where[] = 'a.UserID = :summaryUserId';
            $params[':summaryUserId'] = $userId;
        }

        $scenarioCode = trim((string) ($filters['scenario_code'] ?? ''));
        if ($scenarioCode !== '') {
            $where[] = 'a.ScenarioCode = :summaryScenarioCode';
            $params[':summaryScenarioCode'] = $scenarioCode;
        }

        $moduleName = trim((string) ($filters['module'] ?? ''));
        if ($moduleName !== '') {
            $where[] = 'a.ModuleName = :summaryModuleName';
            $params[':summaryModuleName'] = $moduleName;
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $needle = '%' . $query . '%';
            $where[] = '(
                a.ScenarioCode LIKE :summaryQScenario
                OR a.ModuleName LIKE :summaryQModule
                OR a.Notes LIKE :summaryQNotes
                OR u.Username LIKE :summaryQUsername
                OR u.DisplayName LIKE :summaryQDisplayName
                OR u.Email LIKE :summaryQEmail
            )';
            $params[':summaryQScenario'] = $needle;
            $params[':summaryQModule'] = $needle;
            $params[':summaryQNotes'] = $needle;
            $params[':summaryQUsername'] = $needle;
            $params[':summaryQDisplayName'] = $needle;
            $params[':summaryQEmail'] = $needle;
        }

        $stmt = $this->db->prepare("
            SELECT
                COUNT(1) AS TotalAssignments,
                SUM(CASE WHEN a.Status = N'assigned' THEN 1 ELSE 0 END) AS AssignedCount,
                SUM(CASE WHEN a.Status = N'in_progress' THEN 1 ELSE 0 END) AS InProgressCount,
                SUM(CASE WHEN a.Status = N'completed' THEN 1 ELSE 0 END) AS CompletedCount,
                SUM(CASE
                    WHEN a.DueDate IS NOT NULL
                     AND a.DueDate < CAST(GETDATE() AS date)
                     AND a.Status IN (N'assigned', N'in_progress')
                    THEN 1 ELSE 0 END) AS OverdueCount
            FROM dbo.tblScreenTestAssignments a
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = a.UserID
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['TotalAssignments'] ?? 0),
            'assigned' => (int) ($row['AssignedCount'] ?? 0),
            'in_progress' => (int) ($row['InProgressCount'] ?? 0),
            'completed' => (int) ($row['CompletedCount'] ?? 0),
            'overdue' => (int) ($row['OverdueCount'] ?? 0),
        ];
    }

    public function listAssignmentProgress(int $userId, array $filters = [], bool $includeAll = false): array
    {
        if (!$this->supportsScreenTestAssignments()) {
            return [];
        }

        $where = ['a.ActiveFlag = 1'];
        $params = [];
        if (!$includeAll) {
            $where[] = 'a.UserID = :progressUserId';
            $params[':progressUserId'] = $userId;
        }

        $scenarioCode = trim((string) ($filters['scenario_code'] ?? ''));
        if ($scenarioCode !== '') {
            $where[] = 'a.ScenarioCode = :progressScenarioCode';
            $params[':progressScenarioCode'] = $scenarioCode;
        }

        $moduleName = trim((string) ($filters['module'] ?? ''));
        if ($moduleName !== '') {
            $where[] = 'a.ModuleName = :progressModuleName';
            $params[':progressModuleName'] = $moduleName;
        }

        $hasRunStorage = $this->supportsScreenTestRuns();
        if ($hasRunStorage && trim((string) ($filters['result'] ?? '')) !== '') {
            $where[] = 'latestRun.RunResult = :progressRunResult';
            $params[':progressRunResult'] = strtolower(trim((string) $filters['result']));
        }
        if ($hasRunStorage && trim((string) ($filters['verification'] ?? '')) !== '') {
            $where[] = 'latestRun.VerificationStatus = :progressVerificationStatus';
            $params[':progressVerificationStatus'] = strtolower(trim((string) $filters['verification']));
        }

        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $needle = '%' . $query . '%';
            $searchTerms = [
                'a.ScenarioCode LIKE :progressQScenario',
                'a.ModuleName LIKE :progressQModule',
                'a.Notes LIKE :progressQNotes',
                'u.Username LIKE :progressQUsername',
                'u.DisplayName LIKE :progressQDisplayName',
                'u.Email LIKE :progressQEmail',
            ];
            if ($hasRunStorage) {
                $searchTerms[] = 'latestRun.ScenarioTitle LIKE :progressQTitle';
                $searchTerms[] = 'latestRun.OutcomeSummary LIKE :progressQOutcome';
                $searchTerms[] = 'latestRun.DefectReference LIKE :progressQDefect';
                $params[':progressQTitle'] = $needle;
                $params[':progressQOutcome'] = $needle;
                $params[':progressQDefect'] = $needle;
            }
            $where[] = '(' . implode(' OR ', $searchTerms) . ')';
            $params[':progressQScenario'] = $needle;
            $params[':progressQModule'] = $needle;
            $params[':progressQNotes'] = $needle;
            $params[':progressQUsername'] = $needle;
            $params[':progressQDisplayName'] = $needle;
            $params[':progressQEmail'] = $needle;
        }

        $latestRunApply = $hasRunStorage ? "
            OUTER APPLY (
                SELECT TOP 1
                    r.ScreenTestRunID,
                    r.ScenarioTitle,
                    r.RunResult,
                    r.VerificationStatus,
                    r.AttemptNo,
                    r.CompletedAt,
                    r.OutcomeSummary,
                    r.DefectReference
                FROM dbo.tblScreenTestRuns r
                WHERE r.UserID = a.UserID
                  AND r.ScenarioCode = a.ScenarioCode
                ORDER BY ISNULL(r.CompletedAt, r.StartedAt) DESC, r.ScreenTestRunID DESC
            ) latestRun
        " : "
            OUTER APPLY (
                SELECT
                    CAST(NULL AS INT) AS ScreenTestRunID,
                    CAST(NULL AS NVARCHAR(255)) AS ScenarioTitle,
                    CAST(NULL AS NVARCHAR(30)) AS RunResult,
                    CAST(NULL AS NVARCHAR(30)) AS VerificationStatus,
                    CAST(NULL AS INT) AS AttemptNo,
                    CAST(NULL AS DATETIME2(0)) AS CompletedAt,
                    CAST(NULL AS NVARCHAR(1000)) AS OutcomeSummary,
                    CAST(NULL AS NVARCHAR(255)) AS DefectReference
            ) latestRun
        ";

        $stmt = $this->db->prepare("
            SELECT
                a.ScreenTestAssignmentID,
                a.UserID,
                a.ScenarioCode,
                COALESCE(latestRun.ScenarioTitle, a.ScenarioCode) AS ScenarioTitle,
                a.ModuleName,
                a.DueDate,
                a.Status AS AssignmentStatus,
                a.AssignedAt,
                a.CompletedAt AS AssignmentCompletedAt,
                a.Notes,
                u.Username,
                u.DisplayName,
                u.Email,
                latestRun.ScreenTestRunID,
                latestRun.RunResult,
                latestRun.VerificationStatus,
                latestRun.AttemptNo,
                latestRun.CompletedAt AS LatestRunCompletedAt,
                latestRun.OutcomeSummary,
                latestRun.DefectReference
            FROM dbo.tblScreenTestAssignments a
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = a.UserID
            {$latestRunApply}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE WHEN a.Status IN (N'assigned', N'in_progress') THEN 0 ELSE 1 END,
                ISNULL(a.DueDate, CONVERT(date, '9999-12-31')),
                COALESCE(NULLIF(LTRIM(RTRIM(u.DisplayName)), N''), u.Username),
                a.ModuleName,
                a.ScenarioCode
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    private function parseIntList(string $csv): array
    {
        $ids = [];
        foreach (preg_split('/[,\s]+/', $csv) ?: [] as $part) {
            $id = (int) trim((string) $part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
