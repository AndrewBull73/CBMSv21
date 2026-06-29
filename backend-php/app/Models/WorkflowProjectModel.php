<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkflowProjectModel
{
    private PDO $conn;
    private string $lastError = '';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function supportsWorkflowProjects(): bool
    {
        try {
            $stmt = $this->conn->query("
                SELECT CASE
                    WHEN OBJECT_ID(N'dbo.tblWorkflowProjects', N'U') IS NOT NULL
                     AND OBJECT_ID(N'dbo.tblWorkflowProjectUsers', N'U') IS NOT NULL
                     AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
                     AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowProjectID') IS NOT NULL
                    THEN 1 ELSE 0 END
            ");
            return (int)($stmt ? $stmt->fetchColumn() : 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function statusOptions(): array
    {
        return [
            'PLANNED' => 'workflow_project_status_planned',
            'ACTIVE' => 'workflow_project_status_active',
            'ON_HOLD' => 'workflow_project_status_on_hold',
            'COMPLETED' => 'workflow_project_status_completed',
            'CANCELLED' => 'workflow_project_status_cancelled',
        ];
    }

    public function listProjects(string $q = '', string $status = '', ?string $active = '1'): array
    {
        if (!$this->supportsWorkflowProjects()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        $status = $this->normalizeStatusCode($status, false);

        if ($q !== '') {
            $where[] = '(p.ProjectCode LIKE :q OR p.ProjectName LIKE :q OR p.[Description] LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($status !== '') {
            $where[] = 'p.ProjectStatusCode = :status';
            $params[':status'] = $status;
        }
        if ($active === '1' || $active === '0') {
            $where[] = 'p.Active = :active';
            $params[':active'] = (int)$active;
        }

        try {
            $sql = "
                SELECT
                    p.WorkflowProjectID,
                    p.ProjectCode,
                    p.ProjectName,
                    p.[Description],
                    p.ProjectOwnerUserID,
                    p.ProjectStatusCode,
                    p.StartDate,
                    p.TargetEndDate,
                    p.ActualEndDate,
                    p.Active,
                    p.CreatedAt,
                    p.CreatedBy,
                    p.UpdatedAt,
                    p.UpdatedBy,
                    COUNT(t.WorkflowTaskID) AS TaskCount,
                    SUM(CASE WHEN t.WorkflowTaskID IS NOT NULL AND t.CompletedAt IS NULL THEN 1 ELSE 0 END) AS OpenTaskCount,
                    COALESCE(NULLIF(LTRIM(RTRIM(owner.DisplayName)), N''), NULLIF(LTRIM(RTRIM(owner.Username)), N''), CASE WHEN p.ProjectOwnerUserID IS NOT NULL THEN CONCAT(N'User #', p.ProjectOwnerUserID) ELSE NULL END) AS ProjectOwnerName
                FROM dbo.tblWorkflowProjects p
                LEFT JOIN dbo.tblUsers owner
                    ON owner.UserID = p.ProjectOwnerUserID
                LEFT JOIN dbo.tblWorkflowTasks t
                    ON t.WorkflowProjectID = p.WorkflowProjectID
                WHERE " . implode(' AND ', $where) . "
                GROUP BY
                    p.WorkflowProjectID,
                    p.ProjectCode,
                    p.ProjectName,
                    p.[Description],
                    p.ProjectOwnerUserID,
                    p.ProjectStatusCode,
                    p.StartDate,
                    p.TargetEndDate,
                    p.ActualEndDate,
                    p.Active,
                    p.CreatedAt,
                    p.CreatedBy,
                    p.UpdatedAt,
                    p.UpdatedBy,
                    owner.DisplayName,
                    owner.Username
                ORDER BY p.Active DESC, p.ProjectStatusCode ASC, p.ProjectName ASC
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $this->lastError = '';
            return $this->attachProjectUserSummaries($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function listActiveProjects(): array
    {
        return $this->listProjects('', '', '1');
    }

    public function findProject(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsWorkflowProjects()) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT TOP 1
                    p.WorkflowProjectID,
                    p.ProjectCode,
                    p.ProjectName,
                    p.[Description],
                    p.ProjectOwnerUserID,
                    p.ProjectStatusCode,
                    p.StartDate,
                    p.TargetEndDate,
                    p.ActualEndDate,
                    p.Active,
                    p.CreatedAt,
                    p.CreatedBy,
                    p.UpdatedAt,
                    p.UpdatedBy,
                    COALESCE(NULLIF(LTRIM(RTRIM(owner.DisplayName)), N''), NULLIF(LTRIM(RTRIM(owner.Username)), N''), CASE WHEN p.ProjectOwnerUserID IS NOT NULL THEN CONCAT(N'User #', p.ProjectOwnerUserID) ELSE NULL END) AS ProjectOwnerName
                FROM dbo.tblWorkflowProjects p
                LEFT JOIN dbo.tblUsers owner
                    ON owner.UserID = p.ProjectOwnerUserID
                WHERE p.WorkflowProjectID = :id
            ");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->lastError = '';
            if (!$row) {
                return null;
            }
            $row['ProjectUserIDs'] = $this->listProjectUserIds($id);
            return $row;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function listProjectUsers(int $projectId): array
    {
        if ($projectId <= 0 || !$this->supportsWorkflowProjects()) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT
                    pu.WorkflowProjectUserID,
                    pu.WorkflowProjectID,
                    pu.UserID,
                    pu.ProjectRoleCode,
                    pu.AssignedAt,
                    pu.AssignedBy,
                    COALESCE(NULLIF(LTRIM(RTRIM(u.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u.Username)), N''), CONCAT(N'User #', pu.UserID)) AS UserName
                FROM dbo.tblWorkflowProjectUsers pu
                LEFT JOIN dbo.tblUsers u
                    ON u.UserID = pu.UserID
                WHERE pu.WorkflowProjectID = :projectId
                ORDER BY UserName ASC, pu.UserID ASC
            ");
            $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function listProjectUserIds(int $projectId): array
    {
        $ids = [];
        foreach ($this->listProjectUsers($projectId) as $row) {
            $userId = (int)($row['UserID'] ?? 0);
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }
        return $ids;
    }

    public function projectCodeExists(string $projectCode, int $excludeProjectId = 0): bool
    {
        $projectCode = trim($projectCode);
        if ($projectCode === '' || !$this->supportsWorkflowProjects()) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(1)
                FROM dbo.tblWorkflowProjects
                WHERE ProjectCode = :projectCode
                  AND (:excludeProjectIdFilter <= 0 OR WorkflowProjectID <> :excludeProjectId)
            ");
            $stmt->bindValue(':projectCode', $projectCode);
            $stmt->bindValue(':excludeProjectIdFilter', $excludeProjectId, PDO::PARAM_INT);
            $stmt->bindValue(':excludeProjectId', $excludeProjectId, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return (int)($stmt->fetchColumn() ?: 0) > 0;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function saveProject(array $data, int $currentUserId): int
    {
        if (!$this->supportsWorkflowProjects()) {
            throw new \RuntimeException('Workflow project tables are not installed.');
        }

        $id = (int)($data['WorkflowProjectID'] ?? 0);
        $projectName = trim((string)($data['ProjectName'] ?? ''));
        if ($projectName === '') {
            throw new \InvalidArgumentException('Project name is required.');
        }
        $projectCode = $this->nullableString($data['ProjectCode'] ?? null);
        if ($projectCode !== null && $this->projectCodeExists($projectCode, $id)) {
            throw new \InvalidArgumentException('Project code already exists.');
        }

        $payload = [
            ':ProjectCode' => $projectCode,
            ':ProjectName' => $projectName,
            ':Description' => $this->nullableString($data['Description'] ?? null),
            ':ProjectOwnerUserID' => !empty($data['ProjectOwnerUserID']) ? (int)$data['ProjectOwnerUserID'] : null,
            ':ProjectStatusCode' => $this->normalizeStatusCode($data['ProjectStatusCode'] ?? 'PLANNED', true),
            ':StartDate' => $this->nullableDate($data['StartDate'] ?? null),
            ':TargetEndDate' => $this->nullableDate($data['TargetEndDate'] ?? null),
            ':ActualEndDate' => $this->nullableDate($data['ActualEndDate'] ?? null),
            ':Active' => !empty($data['Active']) ? 1 : 0,
        ];
        $projectUserIds = $this->normalizeUserIds($data['ProjectUserIDs'] ?? []);

        $startedTransaction = !$this->conn->inTransaction();
        if ($startedTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            if ($id > 0) {
                $stmt = $this->conn->prepare("
                    UPDATE dbo.tblWorkflowProjects
                    SET ProjectCode = :ProjectCode,
                        ProjectName = :ProjectName,
                        [Description] = :Description,
                        ProjectOwnerUserID = :ProjectOwnerUserID,
                        ProjectStatusCode = :ProjectStatusCode,
                        StartDate = :StartDate,
                        TargetEndDate = :TargetEndDate,
                        ActualEndDate = :ActualEndDate,
                        Active = :Active,
                        UpdatedAt = SYSUTCDATETIME(),
                        UpdatedBy = :UpdatedBy
                    WHERE WorkflowProjectID = :id
                ");
                $payload[':id'] = $id;
                $payload[':UpdatedBy'] = $currentUserId > 0 ? $currentUserId : null;
                $stmt->execute($payload);
                $savedId = $id;
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO dbo.tblWorkflowProjects
                        (ProjectCode, ProjectName, [Description], ProjectOwnerUserID, ProjectStatusCode,
                         StartDate, TargetEndDate, ActualEndDate, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
                    OUTPUT INSERTED.WorkflowProjectID
                    VALUES
                        (:ProjectCode, :ProjectName, :Description, :ProjectOwnerUserID, :ProjectStatusCode,
                         :StartDate, :TargetEndDate, :ActualEndDate, :Active, SYSUTCDATETIME(), :CreatedBy, SYSUTCDATETIME(), :UpdatedBy)
                ");
                $payload[':CreatedBy'] = $currentUserId > 0 ? $currentUserId : null;
                $payload[':UpdatedBy'] = $currentUserId > 0 ? $currentUserId : null;
                $stmt->execute($payload);
                $savedId = (int)($stmt->fetchColumn() ?: 0);
            }

            if ($savedId <= 0) {
                throw new \RuntimeException('Workflow project save did not return a project id.');
            }

            $this->syncProjectUsers($savedId, $projectUserIds, $currentUserId);
            if ($startedTransaction) {
                $this->conn->commit();
            }
            $this->lastError = '';
            return $savedId;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            if ($this->isProjectCodeUniqueViolation($e)) {
                $this->lastError = 'Project code already exists.';
                throw new \InvalidArgumentException('Project code already exists.', 0, $e);
            }
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    private function normalizeStatusCode($value, bool $defaultPlanned): string
    {
        $code = strtoupper(trim((string)$value));
        $allowed = ['PLANNED', 'ACTIVE', 'ON_HOLD', 'COMPLETED', 'CANCELLED'];
        if (in_array($code, $allowed, true)) {
            return $code;
        }
        return $defaultPlanned ? 'PLANNED' : '';
    }

    private function nullableString($value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function nullableDate($value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function normalizeUserIds($value): array
    {
        $raw = is_array($value) ? $value : [$value];
        $ids = [];
        foreach ($raw as $item) {
            $userId = (int)$item;
            if ($userId > 0) {
                $ids[$userId] = $userId;
            }
        }
        return array_values($ids);
    }

    private function syncProjectUsers(int $projectId, array $userIds, int $currentUserId): void
    {
        $existingRoles = $this->listProjectUserRoleMap($projectId);
        $delete = $this->conn->prepare("
            DELETE FROM dbo.tblWorkflowProjectUsers
            WHERE WorkflowProjectID = :projectId
        ");
        $delete->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        $delete->execute();

        if ($userIds === []) {
            return;
        }

        $insert = $this->conn->prepare("
            INSERT INTO dbo.tblWorkflowProjectUsers
                (WorkflowProjectID, UserID, ProjectRoleCode, AssignedAt, AssignedBy)
            VALUES
                (:WorkflowProjectID, :UserID, :ProjectRoleCode, SYSUTCDATETIME(), :AssignedBy)
        ");
        foreach ($userIds as $userId) {
            $userId = (int)$userId;
            $insert->execute([
                ':WorkflowProjectID' => $projectId,
                ':UserID' => $userId,
                ':ProjectRoleCode' => $existingRoles[$userId] ?? 'MEMBER',
                ':AssignedBy' => $currentUserId > 0 ? $currentUserId : null,
            ]);
        }
    }

    private function listProjectUserRoleMap(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $roles = [];
        $stmt = $this->conn->prepare("
            SELECT UserID, ProjectRoleCode
            FROM dbo.tblWorkflowProjectUsers
            WHERE WorkflowProjectID = :projectId
        ");
        $stmt->bindValue(':projectId', $projectId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $userId = (int)($row['UserID'] ?? 0);
            if ($userId > 0) {
                $roles[$userId] = $this->normalizeProjectRoleCode($row['ProjectRoleCode'] ?? 'MEMBER');
            }
        }

        return $roles;
    }

    private function normalizeProjectRoleCode($value): string
    {
        $code = strtoupper(trim((string)$value));
        return in_array($code, ['MEMBER', 'LEAD', 'OBSERVER'], true) ? $code : 'MEMBER';
    }

    private function isProjectCodeUniqueViolation(\Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'UX_tblWorkflowProjects_ProjectCode')
            || str_contains($message, 'Cannot insert duplicate key')
            || str_contains($message, 'duplicate key')
            || str_contains($message, '2601')
            || str_contains($message, '2627');
    }

    private function attachProjectUserSummaries(array $rows): array
    {
        $projectIds = [];
        foreach ($rows as $row) {
            $projectId = (int)($row['WorkflowProjectID'] ?? 0);
            if ($projectId > 0) {
                $projectIds[$projectId] = $projectId;
            }
        }
        if ($projectIds === []) {
            return $rows;
        }

        $members = [];
        try {
            $placeholders = [];
            $params = [];
            foreach (array_values($projectIds) as $idx => $projectId) {
                $key = ':p' . $idx;
                $placeholders[] = $key;
                $params[$key] = $projectId;
            }
            $stmt = $this->conn->prepare("
                SELECT
                    pu.WorkflowProjectID,
                    pu.UserID,
                    COALESCE(NULLIF(LTRIM(RTRIM(u.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u.Username)), N''), CONCAT(N'User #', pu.UserID)) AS UserName
                FROM dbo.tblWorkflowProjectUsers pu
                LEFT JOIN dbo.tblUsers u
                    ON u.UserID = pu.UserID
                WHERE pu.WorkflowProjectID IN (" . implode(',', $placeholders) . ")
                ORDER BY pu.WorkflowProjectID ASC, UserName ASC, pu.UserID ASC
            ");
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $member) {
                $projectId = (int)($member['WorkflowProjectID'] ?? 0);
                if ($projectId <= 0) {
                    continue;
                }
                $members[$projectId][] = (string)($member['UserName'] ?? '');
            }
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
        }

        foreach ($rows as &$row) {
            $projectId = (int)($row['WorkflowProjectID'] ?? 0);
            $names = array_values(array_filter($members[$projectId] ?? [], static fn($name) => trim((string)$name) !== ''));
            $row['ProjectUserCount'] = count($names);
            $row['ProjectUserNames'] = implode(', ', array_slice($names, 0, 4));
            $row['ProjectUserNamesOverflow'] = max(0, count($names) - 4);
        }
        unset($row);

        return $rows;
    }
}
