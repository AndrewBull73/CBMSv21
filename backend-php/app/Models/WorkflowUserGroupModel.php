<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkflowUserGroupModel
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

    public function supportsWorkflowUserGroups(): bool
    {
        try {
            $stmt = $this->conn->query("
                SELECT CASE
                    WHEN OBJECT_ID(N'dbo.tblWorkflowUserGroups', N'U') IS NOT NULL
                     AND OBJECT_ID(N'dbo.tblWorkflowUserGroupMembers', N'U') IS NOT NULL
                    THEN 1 ELSE 0 END
            ");
            return (int)($stmt ? $stmt->fetchColumn() : 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function listGroups(string $q = '', ?string $active = '1'): array
    {
        if (!$this->supportsWorkflowUserGroups()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(g.GroupName LIKE :q OR g.[Description] LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($active === '1' || $active === '0') {
            $where[] = 'g.Active = :active';
            $params[':active'] = (int)$active;
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        try {
            $sql = "
                SELECT
                    g.WorkflowUserGroupID,
                    g.GroupName,
                    g.[Description],
                    g.Active,
                    g.CreatedAt,
                    g.CreatedBy,
                    g.UpdatedAt,
                    g.UpdatedBy,
                    COUNT(CASE WHEN gm.Active = 1 THEN 1 END) AS MemberCount,
                    COUNT(CASE WHEN gm.Active = 1 AND ISNULL(u.IsActive, 0) = 1 THEN 1 END) AS ActiveMemberCount,
                    COALESCE(NULLIF(LTRIM(RTRIM(createdBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(createdBy.Username)), N''), CASE WHEN g.CreatedBy IS NOT NULL THEN CONCAT(N'User #', g.CreatedBy) ELSE NULL END) AS CreatedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(updatedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(updatedBy.Username)), N''), CASE WHEN g.UpdatedBy IS NOT NULL THEN CONCAT(N'User #', g.UpdatedBy) ELSE NULL END) AS UpdatedByName
                FROM dbo.tblWorkflowUserGroups g
                LEFT JOIN dbo.tblWorkflowUserGroupMembers gm
                    ON gm.WorkflowUserGroupID = g.WorkflowUserGroupID
                LEFT JOIN dbo.tblUsers u
                    ON u.UserID = gm.UserID
                LEFT JOIN dbo.tblUsers createdBy
                    ON createdBy.UserID = g.CreatedBy
                LEFT JOIN dbo.tblUsers updatedBy
                    ON updatedBy.UserID = g.UpdatedBy
                {$whereSql}
                GROUP BY
                    g.WorkflowUserGroupID,
                    g.GroupName,
                    g.[Description],
                    g.Active,
                    g.CreatedAt,
                    g.CreatedBy,
                    g.UpdatedAt,
                    g.UpdatedBy,
                    createdBy.DisplayName,
                    createdBy.Username,
                    updatedBy.DisplayName,
                    updatedBy.Username
                ORDER BY g.Active DESC, g.GroupName ASC
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function findGroup(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsWorkflowUserGroups()) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT TOP 1
                    WorkflowUserGroupID,
                    GroupName,
                    [Description],
                    Active,
                    CreatedAt,
                    CreatedBy,
                    UpdatedAt,
                    UpdatedBy
                FROM dbo.tblWorkflowUserGroups
                WHERE WorkflowUserGroupID = :id
            ");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->lastError = '';
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function listMemberUserIds(int $groupId): array
    {
        if ($groupId <= 0 || !$this->supportsWorkflowUserGroups()) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT gm.UserID
                FROM dbo.tblWorkflowUserGroupMembers gm
                INNER JOIN dbo.tblUsers u
                    ON u.UserID = gm.UserID
                WHERE gm.WorkflowUserGroupID = :groupId
                  AND gm.Active = 1
                ORDER BY u.DisplayName ASC, u.Username ASC
            ");
            $stmt->bindValue(':groupId', $groupId, PDO::PARAM_INT);
            $stmt->execute();
            $ids = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $userId = (int)($row['UserID'] ?? 0);
                if ($userId > 0) {
                    $ids[] = $userId;
                }
            }
            $this->lastError = '';
            return $ids;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function listActiveMembersForGroups(array $groupIds): array
    {
        $ids = $this->normalizeIds($groupIds);
        if ($ids === [] || !$this->supportsWorkflowUserGroups()) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $idx => $id) {
            $key = ':g' . $idx;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        try {
            $sql = "
                SELECT DISTINCT
                    u.UserID,
                    u.Username,
                    LTRIM(RTRIM(u.DisplayName)) AS DisplayName,
                    u.Email
                FROM dbo.tblWorkflowUserGroupMembers gm
                INNER JOIN dbo.tblWorkflowUserGroups g
                    ON g.WorkflowUserGroupID = gm.WorkflowUserGroupID
                   AND g.Active = 1
                INNER JOIN dbo.tblUsers u
                    ON u.UserID = gm.UserID
                   AND ISNULL(u.IsActive, 0) = 1
                WHERE gm.Active = 1
                  AND gm.WorkflowUserGroupID IN (" . implode(',', $placeholders) . ")
                ORDER BY LTRIM(RTRIM(u.DisplayName)) ASC, u.Username ASC
            ";
            $stmt = $this->conn->prepare($sql);
            foreach ($params as $key => $id) {
                $stmt->bindValue($key, $id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function saveGroup(array $data, int $currentUserId): int
    {
        if (!$this->supportsWorkflowUserGroups()) {
            throw new \RuntimeException('Workflow user group tables are not installed.');
        }

        $id = (int)($data['WorkflowUserGroupID'] ?? 0);
        $groupName = trim((string)($data['GroupName'] ?? ''));
        if ($groupName === '') {
            throw new \InvalidArgumentException('Group name is required.');
        }

        if ($id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowUserGroups
                SET GroupName = :GroupName,
                    [Description] = :Description,
                    Active = :Active,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowUserGroupID = :id
            ");
            $stmt->execute([
                ':id' => $id,
                ':GroupName' => $groupName,
                ':Description' => trim((string)($data['Description'] ?? '')) ?: null,
                ':Active' => !empty($data['Active']) ? 1 : 0,
                ':UpdatedBy' => $currentUserId > 0 ? $currentUserId : null,
            ]);
            $this->lastError = '';
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblWorkflowUserGroups
                (GroupName, [Description], Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
            OUTPUT INSERTED.WorkflowUserGroupID
            VALUES
                (:GroupName, :Description, :Active, SYSUTCDATETIME(), :CreatedBy, SYSUTCDATETIME(), :UpdatedBy)
        ");
        $stmt->execute([
            ':GroupName' => $groupName,
            ':Description' => trim((string)($data['Description'] ?? '')) ?: null,
            ':Active' => !empty($data['Active']) ? 1 : 0,
            ':CreatedBy' => $currentUserId > 0 ? $currentUserId : null,
            ':UpdatedBy' => $currentUserId > 0 ? $currentUserId : null,
        ]);

        $newId = (int)($stmt->fetchColumn() ?: 0);
        if ($newId <= 0) {
            throw new \RuntimeException('Workflow user group insert failed.');
        }
        $this->lastError = '';
        return $newId;
    }

    public function saveMembers(int $groupId, array $userIds, int $currentUserId): void
    {
        if ($groupId <= 0 || !$this->supportsWorkflowUserGroups()) {
            return;
        }

        $ids = $this->normalizeIds($userIds);
        $ownsTransaction = !$this->conn->inTransaction();
        if ($ownsTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            $deactivate = $this->conn->prepare("
                UPDATE dbo.tblWorkflowUserGroupMembers
                SET Active = 0,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowUserGroupID = :WorkflowUserGroupID
            ");
            $deactivate->execute([
                ':WorkflowUserGroupID' => $groupId,
                ':UpdatedBy' => $currentUserId > 0 ? $currentUserId : null,
            ]);

            $find = $this->conn->prepare("
                SELECT TOP 1 WorkflowUserGroupMemberID
                FROM dbo.tblWorkflowUserGroupMembers
                WHERE WorkflowUserGroupID = :WorkflowUserGroupID
                  AND UserID = :UserID
            ");
            $activate = $this->conn->prepare("
                UPDATE dbo.tblWorkflowUserGroupMembers
                SET Active = 1,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowUserGroupMemberID = :WorkflowUserGroupMemberID
            ");
            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblWorkflowUserGroupMembers
                    (WorkflowUserGroupID, UserID, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
                VALUES
                    (:WorkflowUserGroupID, :UserID, 1, SYSUTCDATETIME(), :CreatedBy, SYSUTCDATETIME(), :UpdatedBy)
            ");

            foreach ($ids as $userId) {
                $find->execute([
                    ':WorkflowUserGroupID' => $groupId,
                    ':UserID' => $userId,
                ]);
                $memberId = (int)($find->fetchColumn() ?: 0);
                if ($memberId > 0) {
                    $activate->execute([
                        ':WorkflowUserGroupMemberID' => $memberId,
                        ':UpdatedBy' => $currentUserId > 0 ? $currentUserId : null,
                    ]);
                    continue;
                }

                $insert->execute([
                    ':WorkflowUserGroupID' => $groupId,
                    ':UserID' => $userId,
                    ':CreatedBy' => $currentUserId > 0 ? $currentUserId : null,
                    ':UpdatedBy' => $currentUserId > 0 ? $currentUserId : null,
                ]);
            }

            if ($ownsTransaction) {
                $this->conn->commit();
            }
            $this->lastError = '';
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }

    private function normalizeIds(array $values): array
    {
        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }
}
