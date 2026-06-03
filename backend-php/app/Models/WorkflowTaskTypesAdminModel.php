<?php
declare(strict_types=1);

namespace App\Models;

final class WorkflowTaskTypesAdminModel
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function listRows(array $filters): array
    {
        $where = [];
        $params = [];

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(CAST(t.TaskTypeID AS NVARCHAR(20)) LIKE :search OR t.Code LIKE :search OR t.Name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $active = trim((string) ($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 't.IsActive = :active';
            $params[':active'] = (int) $active;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                t.TaskTypeID,
                t.Code,
                t.Name,
                t.IsActive,
                t.CreatedAt,
                t.CreatedBy,
                t.UpdatedAt,
                t.UpdatedBy,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblWorkflowTasks wt
                    WHERE wt.TaskTypeID = t.TaskTypeID
                ) AS TaskUsageCount
            FROM dbo.tblWorkflowTaskTypes t
            {$whereSql}
            ORDER BY t.Name ASC, t.TaskTypeID ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $taskTypeId): ?array
    {
        if ($taskTypeId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                TaskTypeID,
                Code,
                Name,
                IsActive,
                CreatedAt,
                CreatedBy,
                UpdatedAt,
                UpdatedBy
            FROM dbo.tblWorkflowTaskTypes
            WHERE TaskTypeID = :taskTypeId
        ");
        $stmt->execute([':taskTypeId' => $taskTypeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, string $updatedBy = 'system'): int
    {
        $taskTypeId = (int) ($data['TaskTypeID'] ?? 0);
        $code = strtoupper(trim((string) ($data['Code'] ?? '')));
        $name = trim((string) ($data['Name'] ?? ''));
        $isActive = !empty($data['IsActive']) ? 1 : 0;

        if ($code === '') {
            throw new \RuntimeException('Task type code is required.');
        }
        if ($name === '') {
            throw new \RuntimeException('Task type name is required.');
        }
        if (!preg_match('/^[A-Z0-9_\\-]+$/', $code)) {
            throw new \RuntimeException('Task type code can contain only letters, numbers, hyphens, and underscores.');
        }

        $existing = $taskTypeId > 0 ? $this->getById($taskTypeId) : null;
        if ($taskTypeId > 0 && $existing === null) {
            throw new \RuntimeException('Workflow task type was not found.');
        }
        $this->assertUniqueCode($code, $taskTypeId > 0 ? $taskTypeId : null);
        if ($isActive === 0 && $taskTypeId > 0 && $this->taskUsageCount($taskTypeId) > 0) {
            throw new \RuntimeException('Workflow task type cannot be deactivated while it is still used by workflow tasks.');
        }

        if ($existing !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE dbo.tblWorkflowTaskTypes
                SET Code = :code,
                    Name = :name,
                    IsActive = :isActive,
                    UpdatedAt = SYSDATETIME(),
                    UpdatedBy = :updatedBy
                WHERE TaskTypeID = :taskTypeId
            ");
            $stmt->execute([
                ':taskTypeId' => $taskTypeId,
                ':code' => $code,
                ':name' => $name,
                ':isActive' => $isActive,
                ':updatedBy' => $updatedBy,
            ]);

            return $taskTypeId;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO dbo.tblWorkflowTaskTypes (
                Code,
                Name,
                IsActive,
                CreatedAt,
                CreatedBy,
                UpdatedAt,
                UpdatedBy
            ) VALUES (
                :code,
                :name,
                :isActive,
                SYSDATETIME(),
                :updatedBy,
                SYSDATETIME(),
                :updatedBy
            )
        ");
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':isActive' => $isActive,
            ':updatedBy' => $updatedBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assertUniqueCode(string $code, ?int $excludeTaskTypeId = null): void
    {
        $sql = 'SELECT COUNT(*) FROM dbo.tblWorkflowTaskTypes WHERE UPPER(Code) = :code';
        $params = [':code' => strtoupper($code)];
        if (($excludeTaskTypeId ?? 0) > 0) {
            $sql .= ' AND TaskTypeID <> :taskTypeId';
            $params[':taskTypeId'] = $excludeTaskTypeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Task type code must be unique.');
        }
    }

    private function taskUsageCount(int $taskTypeId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM dbo.tblWorkflowTasks
            WHERE TaskTypeID = :taskTypeId
        ');
        $stmt->execute([':taskTypeId' => $taskTypeId]);
        return (int) $stmt->fetchColumn();
    }
}
