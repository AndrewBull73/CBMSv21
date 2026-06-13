<?php
declare(strict_types=1);

namespace App\Models;

final class VersionTypesAdminModel
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
            $where[] = '(CAST(vt.VersionTypeID AS NVARCHAR(20)) LIKE :search OR vt.VersionTypeCode LIKE :search OR vt.VersionTypeName LIKE :search OR ISNULL(vt.Description, \'\') LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $active = trim((string) ($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 'ISNULL(vt.ActiveFlag, 1) = :active';
            $params[':active'] = (int) $active;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                vt.VersionTypeID,
                vt.VersionTypeCode,
                vt.VersionTypeName,
                vt.Description,
                vt.ActiveFlag,
                vt.CreatedDate,
                vt.UpdatedDate,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblVersions v
                    WHERE v.VersionTypeID = vt.VersionTypeID
                ) AS VersionUsageCount
            FROM dbo.tblVersionTypes vt
            {$whereSql}
            ORDER BY vt.VersionTypeID ASC, vt.VersionTypeName ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $versionTypeId): ?array
    {
        if ($versionTypeId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                VersionTypeID,
                VersionTypeCode,
                VersionTypeName,
                Description,
                ActiveFlag,
                CreatedDate,
                UpdatedDate
            FROM dbo.tblVersionTypes
            WHERE VersionTypeID = :versionTypeId
        ");
        $stmt->execute([':versionTypeId' => $versionTypeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): int
    {
        $versionTypeId = (int) ($data['VersionTypeID'] ?? 0);
        $code = strtoupper(trim((string) ($data['VersionTypeCode'] ?? '')));
        $name = trim((string) ($data['VersionTypeName'] ?? ''));
        $description = trim((string) ($data['Description'] ?? ''));
        $activeFlag = !empty($data['ActiveFlag']) ? 1 : 0;

        if ($code === '') {
            throw new \RuntimeException('Version type code is required.');
        }
        if ($name === '') {
            throw new \RuntimeException('Version type name is required.');
        }
        if (!preg_match('/^[A-Z0-9_\\-]+$/', $code)) {
            throw new \RuntimeException('Version type code can contain only letters, numbers, hyphens, and underscores.');
        }

        $existing = $versionTypeId > 0 ? $this->getById($versionTypeId) : null;
        if ($versionTypeId > 0 && $existing === null) {
            throw new \RuntimeException('Version type was not found.');
        }

        $this->assertUniqueCode($code, $versionTypeId > 0 ? $versionTypeId : null);

        if ($activeFlag === 0 && $versionTypeId > 0 && $this->versionUsageCount($versionTypeId) > 0) {
            throw new \RuntimeException('Version type cannot be deactivated while it is still used by version rows.');
        }

        if ($existing !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE dbo.tblVersionTypes
                SET VersionTypeCode = :code,
                    VersionTypeName = :name,
                    Description = :description,
                    ActiveFlag = :activeFlag,
                    UpdatedDate = SYSDATETIME()
                WHERE VersionTypeID = :versionTypeId
            ");
            $stmt->execute([
                ':versionTypeId' => $versionTypeId,
                ':code' => $code,
                ':name' => $name,
                ':description' => $description !== '' ? $description : null,
                ':activeFlag' => $activeFlag,
            ]);

            return $versionTypeId;
        }

        $versionTypeId = $this->nextVersionTypeId();

        $stmt = $this->pdo->prepare("
            INSERT INTO dbo.tblVersionTypes (
                VersionTypeID,
                VersionTypeCode,
                VersionTypeName,
                Description,
                ActiveFlag,
                CreatedDate,
                UpdatedDate
            ) VALUES (
                :versionTypeId,
                :code,
                :name,
                :description,
                :activeFlag,
                SYSDATETIME(),
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':versionTypeId' => $versionTypeId,
            ':code' => $code,
            ':name' => $name,
            ':description' => $description !== '' ? $description : null,
            ':activeFlag' => $activeFlag,
        ]);

        return $versionTypeId;
    }

    private function assertUniqueCode(string $code, ?int $excludeVersionTypeId = null): void
    {
        $sql = 'SELECT COUNT(*) FROM dbo.tblVersionTypes WHERE UPPER(VersionTypeCode) = :code';
        $params = [':code' => strtoupper($code)];
        if (($excludeVersionTypeId ?? 0) > 0) {
            $sql .= ' AND VersionTypeID <> :versionTypeId';
            $params[':versionTypeId'] = $excludeVersionTypeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Version type code must be unique.');
        }
    }

    private function versionUsageCount(int $versionTypeId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM dbo.tblVersions
            WHERE VersionTypeID = :versionTypeId
        ');
        $stmt->execute([':versionTypeId' => $versionTypeId]);
        return (int) $stmt->fetchColumn();
    }

    private function nextVersionTypeId(): int
    {
        $stmt = $this->pdo->query('SELECT ISNULL(MAX(VersionTypeID), 0) + 1 FROM dbo.tblVersionTypes');
        return (int) ($stmt->fetchColumn() ?: 1);
    }
}
