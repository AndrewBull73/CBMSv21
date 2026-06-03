<?php
declare(strict_types=1);

namespace App\Models;

final class WorkflowTaskStatusesAdminModel
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
            $where[] = '(CAST(s.StatusID AS NVARCHAR(20)) LIKE :search OR s.Code LIKE :search OR s.Name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $active = trim((string) ($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 's.IsActive = :active';
            $params[':active'] = (int) $active;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                s.StatusID,
                s.Code,
                s.Name,
                s.IsActive,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblWorkflowTasks wt
                    WHERE wt.StatusID = s.StatusID
                ) AS TaskUsageCount
            FROM dbo.tblWorkflowTaskStatuses s
            {$whereSql}
            ORDER BY s.Name ASC, s.StatusID ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $statusId): ?array
    {
        if ($statusId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                StatusID,
                Code,
                Name,
                IsActive
            FROM dbo.tblWorkflowTaskStatuses
            WHERE StatusID = :statusId
        ");
        $stmt->execute([':statusId' => $statusId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $statusId = (int) ($data['StatusID'] ?? 0);
        $code = strtoupper(trim((string) ($data['Code'] ?? '')));
        $name = trim((string) ($data['Name'] ?? ''));
        $isActive = !empty($data['IsActive']) ? 1 : 0;

        if ($code === '') {
            throw new \RuntimeException('Status code is required.');
        }
        if ($name === '') {
            throw new \RuntimeException('Status name is required.');
        }
        if (!preg_match('/^[A-Z0-9_\\-]+$/', $code)) {
            throw new \RuntimeException('Status code can contain only letters, numbers, hyphens, and underscores.');
        }

        $existing = $statusId > 0 ? $this->getById($statusId) : null;
        if ($statusId > 0 && $existing === null) {
            throw new \RuntimeException('Workflow task status was not found.');
        }
        $this->assertUniqueCode($code, $statusId > 0 ? $statusId : null);
        if ($isActive === 0 && $statusId > 0 && $this->taskUsageCount($statusId) > 0) {
            throw new \RuntimeException('Workflow task status cannot be deactivated while it is still used by workflow tasks.');
        }

        if ($existing !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE dbo.tblWorkflowTaskStatuses
                SET Code = :code,
                    Name = :name,
                    IsActive = :isActive
                WHERE StatusID = :statusId
            ");
            $stmt->execute([
                ':statusId' => $statusId,
                ':code' => $code,
                ':name' => $name,
                ':isActive' => $isActive,
            ]);

            return $statusId;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO dbo.tblWorkflowTaskStatuses (
                Code,
                Name,
                IsActive
            ) VALUES (
                :code,
                :name,
                :isActive
            )
        ");
        $stmt->execute([
            ':code' => $code,
            ':name' => $name,
            ':isActive' => $isActive,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function assertUniqueCode(string $code, ?int $excludeStatusId = null): void
    {
        $sql = 'SELECT COUNT(*) FROM dbo.tblWorkflowTaskStatuses WHERE UPPER(Code) = :code';
        $params = [':code' => strtoupper($code)];
        if (($excludeStatusId ?? 0) > 0) {
            $sql .= ' AND StatusID <> :statusId';
            $params[':statusId'] = $excludeStatusId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Status code must be unique.');
        }
    }

    private function taskUsageCount(int $statusId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM dbo.tblWorkflowTasks
            WHERE StatusID = :statusId
        ');
        $stmt->execute([':statusId' => $statusId]);
        return (int) $stmt->fetchColumn();
    }
}
