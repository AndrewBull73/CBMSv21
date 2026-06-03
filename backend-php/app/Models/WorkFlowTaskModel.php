<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkFlowTaskModel
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

    public function listByUser(
        int $userID,
        int $page = 1,
        int $pageSize = 20,
        string $q = '',
        ?int $typeID = null,
        ?int $statusID = null
    ): array {
        return $this->listFiltered($userID, $page, $pageSize, $q, $typeID, $statusID, false);
    }

    public function listFilteredByUser(
        int $userID,
        string $q = '',
        ?int $typeID = null,
        ?int $statusID = null,
        int $page = 1,
        int $pageSize = 20
    ): array {
        return $this->listFiltered($userID, $page, $pageSize, $q, $typeID, $statusID, false);
    }

    public function listFiltered(
        ?int $assignedToUserID,
        int $page = 1,
        int $pageSize = 20,
        string $q = '',
        ?int $typeID = null,
        ?int $statusID = null,
        bool $onlyOpen = false
    ): array {
        $where = [];
        $params = [];
        $offset = max(0, ($page - 1) * $pageSize);

        if ($assignedToUserID !== null) {
            $where[] = 't.AssignedToUserID = :assignedToUserID';
            $params[':assignedToUserID'] = $assignedToUserID;
        }
        if ($q !== '') {
            $where[] = '(t.Title LIKE :q OR t.Description LIKE :q OR t.RelatedEntity LIKE :q OR t.RelatedKey LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($typeID !== null) {
            $where[] = 't.TaskTypeID = :typeID';
            $params[':typeID'] = $typeID;
        }
        if ($statusID !== null) {
            $where[] = 't.StatusID = :statusID';
            $params[':statusID'] = $statusID;
        }
        if ($onlyOpen) {
            $where[] = 't.CompletedAt IS NULL';
            $where[] = "UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')";
            $where[] = "UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')";
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $countSql = "
                SELECT COUNT(*)
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                {$whereSql}
            ";
            $stc = $this->conn->prepare($countSql);
            $stc->execute($params);
            $total = (int) $stc->fetchColumn();

            $sql = "
                SELECT
                    t.WorkflowTaskID,
                    t.TaskTypeID,
                    t.StatusID,
                    t.Title,
                    t.Description,
                    t.RelatedEntity,
                    t.RelatedKey,
                    t.DueDate,
                    t.CompletedAt,
                    t.CreatedAt,
                    t.UpdatedAt,
                    t.CreatedByUserID,
                    t.AssignedToUserID,
                    ty.Name AS TaskTypeName,
                    st.Code AS StatusCode,
                    st.Name AS StatusName,
                    u1.DisplayName AS CreatedByName,
                    u2.DisplayName AS AssignedToName
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblUsers u1 ON t.CreatedByUserID = u1.UserID
                LEFT JOIN dbo.tblUsers u2 ON t.AssignedToUserID = u2.UserID
                LEFT JOIN dbo.tblWorkflowTaskTypes ty ON t.TaskTypeID = ty.TaskTypeID
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                {$whereSql}
                ORDER BY
                    CASE
                        WHEN t.CompletedAt IS NULL AND CAST(t.DueDate AS date) < CAST(SYSUTCDATETIME() AS date) THEN 0
                        WHEN t.CompletedAt IS NULL THEN 1
                        ELSE 2
                    END,
                    t.DueDate ASC,
                    t.WorkflowTaskID DESC
                OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY
            ";

            $st = $this->conn->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
            $st->execute();

            return [
                'items' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return ['items' => [], 'total' => 0];
        }
    }

    public function summarizeFiltered(
        ?int $assignedToUserID,
        string $q = '',
        ?int $typeID = null,
        ?int $statusID = null
    ): array {
        $where = [];
        $params = [];

        if ($assignedToUserID !== null) {
            $where[] = 't.AssignedToUserID = :assignedToUserID';
            $params[':assignedToUserID'] = $assignedToUserID;
        }
        if ($q !== '') {
            $where[] = '(t.Title LIKE :q OR t.Description LIKE :q OR t.RelatedEntity LIKE :q OR t.RelatedKey LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($typeID !== null) {
            $where[] = 't.TaskTypeID = :typeID';
            $params[':typeID'] = $typeID;
        }
        if ($statusID !== null) {
            $where[] = 't.StatusID = :statusID';
            $params[':statusID'] = $statusID;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $sql = "
                SELECT
                    COUNT(*) AS TotalTasks,
                    SUM(CASE
                        WHEN t.CompletedAt IS NULL
                         AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                         AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                        THEN 1 ELSE 0 END) AS OpenTasks,
                    SUM(CASE
                        WHEN t.CompletedAt IS NULL
                         AND CAST(t.DueDate AS date) < CAST(SYSUTCDATETIME() AS date)
                         AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                         AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                        THEN 1 ELSE 0 END) AS OverdueTasks,
                    SUM(CASE
                        WHEN t.CompletedAt IS NOT NULL
                         OR UPPER(ISNULL(st.Code, '')) IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                         OR UPPER(ISNULL(st.Name, '')) IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                        THEN 1 ELSE 0 END) AS ClosedTasks
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                {$whereSql}
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'total' => (int) ($row['TotalTasks'] ?? 0),
                'open' => (int) ($row['OpenTasks'] ?? 0),
                'overdue' => (int) ($row['OverdueTasks'] ?? 0),
                'closed' => (int) ($row['ClosedTasks'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return ['total' => 0, 'open' => 0, 'overdue' => 0, 'closed' => 0];
        }
    }

    public function create(array $data): bool
    {
        $sql = "
            INSERT INTO dbo.tblWorkflowTasks
                (TaskTypeID, StatusID, Title, Description,
                 CreatedByUserID, AssignedToUserID,
                 RelatedEntity, RelatedKey, DueDate, CompletedAt, CreatedAt)
            VALUES
                (:TaskTypeID, :StatusID, :Title, :Description,
                 :CreatedByUserID, :AssignedToUserID,
                 :RelatedEntity, :RelatedKey, :DueDate, :CompletedAt, SYSUTCDATETIME())
        ";

        $st = $this->conn->prepare($sql);
        $ok = $st->execute([
            ':TaskTypeID' => $data['TaskTypeID'],
            ':StatusID' => $data['StatusID'],
            ':Title' => $data['Title'],
            ':Description' => $data['Description'] ?? null,
            ':CreatedByUserID' => $data['CreatedByUserID'],
            ':AssignedToUserID' => !empty($data['AssignedToUserID']) ? $data['AssignedToUserID'] : null,
            ':RelatedEntity' => $data['RelatedEntity'] ?? null,
            ':RelatedKey' => $data['RelatedKey'] ?? null,
            ':DueDate' => $data['DueDate'] ?? null,
            ':CompletedAt' => $data['CompletedAt'] ?? null,
        ]);

        if (!$ok) {
            $this->lastError = implode(' | ', $st->errorInfo());
        } else {
            $this->lastError = '';
        }

        return $ok;
    }

    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE dbo.tblWorkflowTasks
            SET TaskTypeID = :TaskTypeID,
                StatusID = :StatusID,
                Title = :Title,
                Description = :Description,
                AssignedToUserID = :AssignedToUserID,
                RelatedEntity = :RelatedEntity,
                RelatedKey = :RelatedKey,
                DueDate = :DueDate,
                CompletedAt = :CompletedAt,
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :UpdatedBy
            WHERE WorkflowTaskID = :id
        ";

        $st = $this->conn->prepare($sql);
        $ok = $st->execute([
            ':id' => $id,
            ':TaskTypeID' => $data['TaskTypeID'],
            ':StatusID' => $data['StatusID'],
            ':Title' => $data['Title'],
            ':Description' => $data['Description'] ?? null,
            ':AssignedToUserID' => !empty($data['AssignedToUserID']) ? $data['AssignedToUserID'] : null,
            ':RelatedEntity' => $data['RelatedEntity'] ?? null,
            ':RelatedKey' => $data['RelatedKey'] ?? null,
            ':DueDate' => $data['DueDate'] ?? null,
            ':CompletedAt' => $data['CompletedAt'] ?? null,
            ':UpdatedBy' => $data['UpdatedBy'] ?? null,
        ]);

        if (!$ok) {
            $this->lastError = implode(' | ', $st->errorInfo());
        } else {
            $this->lastError = '';
        }

        return $ok;
    }

    public function updateStatus(int $id, int $statusID, ?string $completedAt, ?int $updatedBy): bool
    {
        $sql = "
            UPDATE dbo.tblWorkflowTasks
            SET StatusID = :statusID,
                CompletedAt = :completedAt,
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :updatedBy
            WHERE WorkflowTaskID = :id
        ";

        try {
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':id' => $id,
                ':statusID' => $statusID,
                ':completedAt' => $completedAt,
                ':updatedBy' => $updatedBy,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $st = $this->conn->prepare("DELETE FROM dbo.tblWorkflowTasks WHERE WorkflowTaskID = :id");
        return $st->execute([':id' => $id]);
    }

    public function find(int $id): ?array
    {
        try {
            $sql = "
                SELECT
                    t.WorkflowTaskID,
                    t.Title,
                    t.Description,
                    t.DueDate,
                    t.CompletedAt,
                    t.CreatedAt,
                    t.UpdatedAt,
                    t.RelatedEntity,
                    t.RelatedKey,
                    t.TaskTypeID,
                    t.StatusID,
                    t.AssignedToUserID,
                    t.CreatedByUserID,
                    ty.Name AS TaskTypeName,
                    st.Code AS StatusCode,
                    st.Name AS StatusName,
                    u1.DisplayName AS CreatedByName,
                    u2.DisplayName AS AssignedToName
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskTypes ty ON t.TaskTypeID = ty.TaskTypeID
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                LEFT JOIN dbo.tblUsers u1 ON t.CreatedByUserID = u1.UserID
                LEFT JOIN dbo.tblUsers u2 ON t.AssignedToUserID = u2.UserID
                WHERE t.WorkflowTaskID = :id
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function listOpenByRelatedEntityKey(string $relatedEntity, string $relatedKey): array
    {
        try {
            $sql = "
                SELECT
                    t.WorkflowTaskID,
                    t.TaskTypeID,
                    t.StatusID,
                    t.Title,
                    t.Description,
                    t.CreatedByUserID,
                    t.AssignedToUserID,
                    t.RelatedEntity,
                    t.RelatedKey,
                    t.DueDate,
                    t.CompletedAt
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                WHERE UPPER(ISNULL(t.RelatedEntity, '')) = UPPER(:relatedEntity)
                  AND ISNULL(t.RelatedKey, '') = :relatedKey
                  AND t.CompletedAt IS NULL
                  AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                  AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                ORDER BY t.WorkflowTaskID DESC
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':relatedEntity' => $relatedEntity,
                ':relatedKey' => $relatedKey,
            ]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function findOpenByRelatedEntityKeyAndAssignee(string $relatedEntity, string $relatedKey, int $assignedToUserID): ?array
    {
        $rows = $this->listOpenByRelatedEntityKey($relatedEntity, $relatedKey);
        foreach ($rows as $row) {
            if ((int) ($row['AssignedToUserID'] ?? 0) === $assignedToUserID) {
                return $row;
            }
        }
        return null;
    }
}
