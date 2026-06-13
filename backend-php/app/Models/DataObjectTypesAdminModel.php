<?php
declare(strict_types=1);

namespace App\Models;

final class DataObjectTypesAdminModel
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
            $where[] = '(CAST(dot.DataObjectTypeID AS NVARCHAR(20)) LIKE :search OR dot.DataObjectTypeName LIKE :search OR CAST(ISNULL(dot.SegmentNo, 0) AS NVARCHAR(20)) LIKE :search OR CAST(dot.[Level] AS NVARCHAR(20)) LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $container = trim((string) ($filters['container'] ?? ''));
        if ($container === '1' || $container === '0') {
            $where[] = 'ISNULL(dot.DataContainer, 1) = :container';
            $params[':container'] = (int) $container;
        }

        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                dot.DataObjectTypeID,
                dot.DataObjectTypeName,
                dot.SegmentNo,
                dot.DataContainer,
                dot.UpdatedBy,
                dot.DateUpdated,
                dot.[Level],
                (
                    SELECT COUNT(*)
                    FROM dbo.tblDataObjectCodes doc
                    WHERE doc.DataObjectTypeID = dot.DataObjectTypeID
                ) AS DataObjectUsageCount
            FROM dbo.tblDataObjectTypes dot
            {$whereSql}
            ORDER BY dot.[Level] ASC, dot.DataObjectTypeID ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $dataObjectTypeId): ?array
    {
        if ($dataObjectTypeId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                DataObjectTypeID,
                DataObjectTypeName,
                SegmentNo,
                DataContainer,
                UpdatedBy,
                DateUpdated,
                [Level]
            FROM dbo.tblDataObjectTypes
            WHERE DataObjectTypeID = :dataObjectTypeId
        ");
        $stmt->execute([':dataObjectTypeId' => $dataObjectTypeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, int $userId): int
    {
        $dataObjectTypeId = (int) ($data['DataObjectTypeID'] ?? 0);
        $name = trim((string) ($data['DataObjectTypeName'] ?? ''));
        $segmentNo = $this->normalizeNullablePositiveInt($data['SegmentNo'] ?? null, 'Segment No');
        $level = $this->normalizeRequiredPositiveInt($data['Level'] ?? null, 'Level');
        $dataContainer = !empty($data['DataContainer']) ? 1 : 0;

        if ($name === '') {
            throw new \RuntimeException('Data object type name is required.');
        }
        if (strlen($name) > 100) {
            throw new \RuntimeException('Data object type name must be 100 characters or fewer.');
        }
        if ($level > 255) {
            throw new \RuntimeException('Level must be between 1 and 255.');
        }

        $existing = $dataObjectTypeId > 0 ? $this->getById($dataObjectTypeId) : null;
        if ($dataObjectTypeId > 0 && $existing === null) {
            throw new \RuntimeException('Data object type was not found.');
        }

        $this->assertUniqueName($name, $existing !== null ? $dataObjectTypeId : null);

        if ($existing !== null) {
            $stmt = $this->pdo->prepare("
                UPDATE dbo.tblDataObjectTypes
                SET DataObjectTypeName = :name,
                    SegmentNo = :segmentNo,
                    DataContainer = :dataContainer,
                    UpdatedBy = :updatedBy,
                    DateUpdated = SYSDATETIME(),
                    [Level] = :level
                WHERE DataObjectTypeID = :dataObjectTypeId
            ");
            $stmt->execute([
                ':dataObjectTypeId' => $dataObjectTypeId,
                ':name' => $name,
                ':segmentNo' => $segmentNo,
                ':dataContainer' => $dataContainer,
                ':updatedBy' => $userId > 0 ? $userId : null,
                ':level' => $level,
            ]);

            return $dataObjectTypeId;
        }

        $dataObjectTypeId = $this->nextDataObjectTypeId();

        $stmt = $this->pdo->prepare("
            INSERT INTO dbo.tblDataObjectTypes (
                DataObjectTypeID,
                DataObjectTypeName,
                SegmentNo,
                DataContainer,
                UpdatedBy,
                DateUpdated,
                [Level]
            ) VALUES (
                :dataObjectTypeId,
                :name,
                :segmentNo,
                :dataContainer,
                :updatedBy,
                SYSDATETIME(),
                :level
            )
        ");
        $stmt->execute([
            ':dataObjectTypeId' => $dataObjectTypeId,
            ':name' => $name,
            ':segmentNo' => $segmentNo,
            ':dataContainer' => $dataContainer,
            ':updatedBy' => $userId > 0 ? $userId : null,
            ':level' => $level,
        ]);

        return $dataObjectTypeId;
    }

    private function assertUniqueName(string $name, ?int $excludeDataObjectTypeId = null): void
    {
        $sql = 'SELECT COUNT(*) FROM dbo.tblDataObjectTypes WHERE UPPER(DataObjectTypeName) = :name';
        $params = [':name' => strtoupper($name)];

        if (($excludeDataObjectTypeId ?? 0) > 0) {
            $sql .= ' AND DataObjectTypeID <> :dataObjectTypeId';
            $params[':dataObjectTypeId'] = $excludeDataObjectTypeId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new \RuntimeException('Data object type name must be unique.');
        }
    }

    private function nextDataObjectTypeId(): int
    {
        $stmt = $this->pdo->query('SELECT ISNULL(MAX(DataObjectTypeID), 0) + 1 FROM dbo.tblDataObjectTypes');
        return (int) ($stmt->fetchColumn() ?: 1);
    }

    private function normalizeNullablePositiveInt(mixed $value, string $label): ?int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (!ctype_digit($text)) {
            throw new \RuntimeException($label . ' must be a whole number.');
        }

        $number = (int) $text;
        if ($number <= 0) {
            throw new \RuntimeException($label . ' must be greater than zero.');
        }

        return $number;
    }

    private function normalizeRequiredPositiveInt(mixed $value, string $label): int
    {
        $text = trim((string) $value);
        if ($text === '') {
            throw new \RuntimeException($label . ' is required.');
        }
        if (!ctype_digit($text)) {
            throw new \RuntimeException($label . ' must be a whole number.');
        }

        $number = (int) $text;
        if ($number <= 0) {
            throw new \RuntimeException($label . ' must be greater than zero.');
        }

        return $number;
    }
}
