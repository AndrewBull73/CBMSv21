<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkflowIssueModel
{
    private string $lastError = '';

    public function __construct(private PDO $conn)
    {
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function supportsIssues(): bool
    {
        try {
            $stmt = $this->conn->query("
                SELECT CASE
                    WHEN OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
                    THEN 1 ELSE 0 END
            ");
            return (int)($stmt ? $stmt->fetchColumn() : 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function supportsIssueAttachments(): bool
    {
        return $this->tableExists('dbo.tblWorkflowIssueAttachments');
    }

    public function typeOptions(): array
    {
        return [
            'BUG' => 'workflow_issue_type_bug',
            'GAP' => 'workflow_issue_type_gap',
            'RISK' => 'workflow_issue_type_risk',
            'DECISION' => 'workflow_issue_type_decision',
            'DATA' => 'workflow_issue_type_data',
            'DEPENDENCY' => 'workflow_issue_type_dependency',
            'CHANGE_REQUEST' => 'workflow_issue_type_change_request',
            'OTHER' => 'workflow_issue_type_other',
        ];
    }

    public function severityOptions(): array
    {
        return [
            'CRITICAL' => 'workflow_issue_severity_critical',
            'HIGH' => 'workflow_issue_severity_high',
            'MEDIUM' => 'workflow_issue_severity_medium',
            'LOW' => 'workflow_issue_severity_low',
        ];
    }

    public function priorityOptions(): array
    {
        return [
            'MUST' => 'workflow_requirement_priority_must',
            'SHOULD' => 'workflow_requirement_priority_should',
            'COULD' => 'workflow_requirement_priority_could',
            'WONT' => 'workflow_requirement_priority_wont',
        ];
    }

    public function statusOptions(): array
    {
        return [
            'OPEN' => 'workflow_issue_status_open',
            'TRIAGED' => 'workflow_issue_status_triaged',
            'IN_PROGRESS' => 'workflow_issue_status_in_progress',
            'BLOCKED' => 'workflow_issue_status_blocked',
            'RESOLVED' => 'workflow_issue_status_resolved',
            'CLOSED' => 'workflow_issue_status_closed',
            'DEFERRED' => 'workflow_issue_status_deferred',
        ];
    }

    public function listIssues(array $filters = []): array
    {
        if (!$this->supportsIssues()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        $q = trim((string)($filters['q'] ?? ''));
        $status = $this->normalizeStatusCode($filters['status'] ?? '', false);
        $severity = $this->normalizeSeverityCode($filters['severity'] ?? '', false);
        $id = (int)($filters['id'] ?? 0);
        $projectID = (int)($filters['workflowProjectID'] ?? 0);
        $requirementID = (int)($filters['workflowRequirementID'] ?? 0);
        $active = (string)($filters['active'] ?? '1');

        if ($q !== '') {
            $where[] = '(i.IssueCode LIKE :q OR i.IssueTitle LIKE :q OR i.IssueDescription LIKE :q OR i.ResolutionSummary LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($status !== '') {
            $where[] = 'i.IssueStatusCode = :status';
            $params[':status'] = $status;
        }
        if ($severity !== '') {
            $where[] = 'i.SeverityCode = :severity';
            $params[':severity'] = $severity;
        }
        if ($id > 0) {
            $where[] = 'i.WorkflowIssueID = :id';
            $params[':id'] = $id;
        }
        if ($projectID > 0) {
            $where[] = 'i.WorkflowProjectID = :workflowProjectID';
            $params[':workflowProjectID'] = $projectID;
        }
        if ($requirementID > 0) {
            $where[] = 'i.WorkflowRequirementID = :workflowRequirementID';
            $params[':workflowRequirementID'] = $requirementID;
        }
        if ($active === '1' || $active === '0') {
            $where[] = 'i.Active = :active';
            $params[':active'] = (int)$active;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT
                    i.WorkflowIssueID,
                    i.WorkflowProjectID,
                    i.WorkflowRequirementID,
                    i.IssueCode,
                    i.IssueTitle,
                    i.IssueDescription,
                    i.IssueTypeCode,
                    i.SeverityCode,
                    i.PriorityCode,
                    i.IssueStatusCode,
                    i.RaisedByUserID,
                    i.OwnerUserID,
                    i.RaisedAt,
                    i.DueDate,
                    i.ResolvedAt,
                    i.ResolutionSummary,
                    i.Active,
                    i.CreatedAt,
                    i.CreatedBy,
                    i.UpdatedAt,
                    p.ProjectCode,
                    p.ProjectName,
                    r.RequirementCode,
                    r.RequirementTitle,
                    COALESCE(NULLIF(LTRIM(RTRIM(owner.DisplayName)), N''), NULLIF(LTRIM(RTRIM(owner.Username)), N''), CASE WHEN i.OwnerUserID IS NOT NULL THEN CONCAT(N'User #', i.OwnerUserID) ELSE NULL END) AS OwnerName,
                    COALESCE(NULLIF(LTRIM(RTRIM(raisedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(raisedBy.Username)), N''), CASE WHEN i.RaisedByUserID IS NOT NULL THEN CONCAT(N'User #', i.RaisedByUserID) ELSE NULL END) AS RaisedByName,
                    COALESCE(taskCounts.TaskCount, 0) AS TaskCount,
                    COALESCE(taskCounts.OpenTaskCount, 0) AS OpenTaskCount
                FROM dbo.tblWorkflowIssues i
                LEFT JOIN dbo.tblWorkflowProjects p ON p.WorkflowProjectID = i.WorkflowProjectID
                LEFT JOIN dbo.tblWorkflowRequirements r ON r.WorkflowRequirementID = i.WorkflowRequirementID
                LEFT JOIN dbo.tblUsers owner ON owner.UserID = i.OwnerUserID
                LEFT JOIN dbo.tblUsers raisedBy ON raisedBy.UserID = i.RaisedByUserID
                LEFT JOIN (
                    SELECT
                        l.LinkedEntityID AS WorkflowIssueID,
                        COUNT(DISTINCT t.WorkflowTaskID) AS TaskCount,
                        SUM(CASE WHEN t.WorkflowTaskID IS NOT NULL AND t.CompletedAt IS NULL THEN 1 ELSE 0 END) AS OpenTaskCount
                    FROM dbo.tblWorkflowEntityLinks l
                    INNER JOIN dbo.tblWorkflowTasks t ON t.WorkflowTaskID = l.WorkflowTaskID
                    WHERE l.Active = 1
                      AND l.LinkedEntity = N'WorkflowIssue'
                      AND l.LinkedEntityID IS NOT NULL
                    GROUP BY l.LinkedEntityID
                ) taskCounts ON taskCounts.WorkflowIssueID = i.WorkflowIssueID
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    CASE i.SeverityCode WHEN N'CRITICAL' THEN 0 WHEN N'HIGH' THEN 1 WHEN N'MEDIUM' THEN 2 ELSE 3 END,
                    CASE i.IssueStatusCode WHEN N'OPEN' THEN 0 WHEN N'TRIAGED' THEN 1 WHEN N'IN_PROGRESS' THEN 2 WHEN N'BLOCKED' THEN 3 ELSE 4 END,
                    i.DueDate ASC,
                    i.WorkflowIssueID DESC
            ");
            $stmt->execute($params);
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function findIssue(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsIssues()) {
            return null;
        }

        $rows = $this->listIssues(['active' => '', 'id' => $id]);
        if ($rows !== []) {
            foreach ($rows as $row) {
                if ((int)($row['WorkflowIssueID'] ?? 0) === $id) {
                    return $row;
                }
            }
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT TOP 1 *
                FROM dbo.tblWorkflowIssues
                WHERE WorkflowIssueID = :id
            ");
            $stmt->execute([':id' => $id]);
            $this->lastError = '';
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function saveIssue(array $data, int $currentUserID): int
    {
        if (!$this->supportsIssues()) {
            throw new \RuntimeException('Workflow issue storage is not installed.');
        }

        $id = (int)($data['WorkflowIssueID'] ?? 0);
        $title = trim((string)($data['IssueTitle'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Issue title is required.');
        }

        $payload = [
            ':WorkflowProjectID' => !empty($data['WorkflowProjectID']) ? (int)$data['WorkflowProjectID'] : null,
            ':WorkflowRequirementID' => !empty($data['WorkflowRequirementID']) ? (int)$data['WorkflowRequirementID'] : null,
            ':IssueCode' => $this->nullableString($data['IssueCode'] ?? null),
            ':IssueTitle' => $title,
            ':IssueDescription' => $this->nullableString($data['IssueDescription'] ?? null),
            ':IssueTypeCode' => $this->normalizeTypeCode($data['IssueTypeCode'] ?? '', true),
            ':SeverityCode' => $this->normalizeSeverityCode($data['SeverityCode'] ?? 'MEDIUM', true),
            ':PriorityCode' => $this->normalizePriorityCode($data['PriorityCode'] ?? 'SHOULD', true),
            ':IssueStatusCode' => $this->normalizeStatusCode($data['IssueStatusCode'] ?? 'OPEN', true),
            ':RaisedByUserID' => !empty($data['RaisedByUserID']) ? (int)$data['RaisedByUserID'] : null,
            ':OwnerUserID' => !empty($data['OwnerUserID']) ? (int)$data['OwnerUserID'] : null,
            ':RaisedAt' => $this->nullableDateTime($data['RaisedAt'] ?? null),
            ':DueDate' => $this->nullableDate($data['DueDate'] ?? null),
            ':ResolvedAt' => $this->nullableDateTime($data['ResolvedAt'] ?? null),
            ':ResolutionSummary' => $this->nullableString($data['ResolutionSummary'] ?? null),
            ':Active' => !empty($data['Active']) ? 1 : 0,
            ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
        ];

        if ($id > 0) {
            $payload[':id'] = $id;
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowIssues
                SET WorkflowProjectID = :WorkflowProjectID,
                    WorkflowRequirementID = :WorkflowRequirementID,
                    IssueCode = :IssueCode,
                    IssueTitle = :IssueTitle,
                    IssueDescription = :IssueDescription,
                    IssueTypeCode = :IssueTypeCode,
                    SeverityCode = :SeverityCode,
                    PriorityCode = :PriorityCode,
                    IssueStatusCode = :IssueStatusCode,
                    RaisedByUserID = :RaisedByUserID,
                    OwnerUserID = :OwnerUserID,
                    RaisedAt = :RaisedAt,
                    DueDate = :DueDate,
                    ResolvedAt = :ResolvedAt,
                    ResolutionSummary = :ResolutionSummary,
                    Active = :Active,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowIssueID = :id
            ");
            $stmt->execute($payload);
            $savedId = $id;
        } else {
            $payload[':CreatedBy'] = $currentUserID > 0 ? $currentUserID : null;
            $stmt = $this->conn->prepare("
                INSERT INTO dbo.tblWorkflowIssues
                    (WorkflowProjectID, WorkflowRequirementID, IssueCode, IssueTitle, IssueDescription,
                     IssueTypeCode, SeverityCode, PriorityCode, IssueStatusCode, RaisedByUserID, OwnerUserID,
                     RaisedAt, DueDate, ResolvedAt, ResolutionSummary, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
                OUTPUT INSERTED.WorkflowIssueID
                VALUES
                    (:WorkflowProjectID, :WorkflowRequirementID, :IssueCode, :IssueTitle, :IssueDescription,
                     :IssueTypeCode, :SeverityCode, :PriorityCode, :IssueStatusCode, :RaisedByUserID, :OwnerUserID,
                     COALESCE(:RaisedAt, SYSUTCDATETIME()), :DueDate, :ResolvedAt, :ResolutionSummary, :Active,
                     SYSUTCDATETIME(), :CreatedBy, SYSUTCDATETIME(), :UpdatedBy)
            ");
            $stmt->execute($payload);
            $savedId = (int)($stmt->fetchColumn() ?: 0);
        }

        if ($savedId <= 0) {
            throw new \RuntimeException('Workflow issue save did not return an issue id.');
        }

        if (empty($data['IssueCode'])) {
            $code = $this->defaultIssueCode($savedId);
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowIssues
                SET IssueCode = :code
                WHERE WorkflowIssueID = :id
                  AND (IssueCode IS NULL OR LTRIM(RTRIM(IssueCode)) = N'')
            ");
            $stmt->execute([':code' => $code, ':id' => $savedId]);
        }

        $this->lastError = '';
        return $savedId;
    }

    public function archiveIssue(int $id, int $currentUserID): bool
    {
        if ($id <= 0 || !$this->supportsIssues()) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowIssues
                SET Active = 0,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowIssueID = :id
            ");
            $stmt->execute([
                ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
                ':id' => $id,
            ]);
            $this->lastError = '';
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function listAttachments(int $issueID, bool $includeDeleted = false): array
    {
        if ($issueID <= 0 || !$this->supportsIssueAttachments()) {
            return [];
        }

        try {
            $whereDeleted = $includeDeleted ? '' : 'AND a.Deleted = 0';
            $stmt = $this->conn->prepare("
                SELECT
                    a.WorkflowIssueAttachmentID,
                    a.WorkflowIssueID,
                    a.OriginalFileName,
                    a.StoredFileName,
                    a.StoragePath,
                    a.MimeType,
                    a.FileSizeBytes,
                    a.UploadedByUserID,
                    a.UploadedAt,
                    a.Deleted,
                    a.DeletedByUserID,
                    a.DeletedAt,
                    COALESCE(NULLIF(LTRIM(RTRIM(u.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u.Username)), N''), CASE WHEN a.UploadedByUserID IS NOT NULL THEN CONCAT(N'User #', a.UploadedByUserID) ELSE NULL END) AS UploadedByName
                FROM dbo.tblWorkflowIssueAttachments a
                LEFT JOIN dbo.tblUsers u ON u.UserID = a.UploadedByUserID
                WHERE a.WorkflowIssueID = :WorkflowIssueID
                  {$whereDeleted}
                ORDER BY a.UploadedAt DESC, a.WorkflowIssueAttachmentID DESC
            ");
            $stmt->bindValue(':WorkflowIssueID', $issueID, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getAttachment(int $attachmentID, bool $includeDeleted = false): ?array
    {
        if ($attachmentID <= 0 || !$this->supportsIssueAttachments()) {
            return null;
        }

        try {
            $whereDeleted = $includeDeleted ? '' : 'AND a.Deleted = 0';
            $stmt = $this->conn->prepare("
                SELECT
                    a.WorkflowIssueAttachmentID,
                    a.WorkflowIssueID,
                    a.OriginalFileName,
                    a.StoredFileName,
                    a.StoragePath,
                    a.MimeType,
                    a.FileSizeBytes,
                    a.UploadedByUserID,
                    a.UploadedAt,
                    a.Deleted,
                    a.DeletedByUserID,
                    a.DeletedAt,
                    i.IssueCode,
                    i.IssueTitle,
                    i.OwnerUserID,
                    i.CreatedBy
                FROM dbo.tblWorkflowIssueAttachments a
                INNER JOIN dbo.tblWorkflowIssues i ON i.WorkflowIssueID = a.WorkflowIssueID
                WHERE a.WorkflowIssueAttachmentID = :WorkflowIssueAttachmentID
                  {$whereDeleted}
            ");
            $stmt->bindValue(':WorkflowIssueAttachmentID', $attachmentID, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function saveAttachment(int $issueID, array $data): int
    {
        if ($issueID <= 0 || !$this->supportsIssueAttachments()) {
            return 0;
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO dbo.tblWorkflowIssueAttachments
                    (WorkflowIssueID, OriginalFileName, StoredFileName, StoragePath,
                     MimeType, FileSizeBytes, UploadedByUserID, UploadedAt, Deleted)
                OUTPUT INSERTED.WorkflowIssueAttachmentID
                VALUES
                    (:WorkflowIssueID, :OriginalFileName, :StoredFileName, :StoragePath,
                     :MimeType, :FileSizeBytes, :UploadedByUserID, SYSUTCDATETIME(), 0)
            ");
            $stmt->execute([
                ':WorkflowIssueID' => $issueID,
                ':OriginalFileName' => trim((string)($data['OriginalFileName'] ?? 'attachment')),
                ':StoredFileName' => trim((string)($data['StoredFileName'] ?? '')),
                ':StoragePath' => trim((string)($data['StoragePath'] ?? '')),
                ':MimeType' => $this->nullableString($data['MimeType'] ?? null),
                ':FileSizeBytes' => max(0, (int)($data['FileSizeBytes'] ?? 0)),
                ':UploadedByUserID' => !empty($data['UploadedByUserID']) ? (int)$data['UploadedByUserID'] : null,
            ]);
            $this->lastError = '';
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return 0;
        }
    }

    public function markAttachmentDeleted(int $attachmentID, int $userID): bool
    {
        if ($attachmentID <= 0 || $userID <= 0 || !$this->supportsIssueAttachments()) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowIssueAttachments
                SET Deleted = 1,
                    DeletedByUserID = :DeletedByUserID,
                    DeletedAt = SYSUTCDATETIME()
                WHERE WorkflowIssueAttachmentID = :WorkflowIssueAttachmentID
            ");
            $stmt->execute([
                ':DeletedByUserID' => $userID,
                ':WorkflowIssueAttachmentID' => $attachmentID,
            ]);
            $this->lastError = '';
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function defaultIssueCode(int $id): string
    {
        return 'ISS-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    }

    private function normalizeTypeCode($value, bool $default): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->typeOptions()) ? $code : ($default ? 'OTHER' : '');
    }

    private function normalizeSeverityCode($value, bool $default): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->severityOptions()) ? $code : ($default ? 'MEDIUM' : '');
    }

    private function normalizePriorityCode($value, bool $default): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->priorityOptions()) ? $code : ($default ? 'SHOULD' : '');
    }

    private function normalizeStatusCode($value, bool $default): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->statusOptions()) ? $code : ($default ? 'OPEN' : '');
    }

    private function nullableString($value): ?string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private function nullableDate($value): ?string
    {
        $value = trim((string)$value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function nullableDateTime($value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        $value = str_replace('T', ' ', $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) ? $value : null;
    }

    private function tableExists(string $table): bool
    {
        try {
            $stmt = $this->conn->prepare("SELECT CASE WHEN OBJECT_ID(:tableName, N'U') IS NOT NULL THEN 1 ELSE 0 END");
            $stmt->execute([':tableName' => $table]);
            return (int)($stmt->fetchColumn() ?: 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
}
