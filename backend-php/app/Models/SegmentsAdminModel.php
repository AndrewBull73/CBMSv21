<?php
declare(strict_types=1);

namespace App\Models;

use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class SegmentsAdminModel
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function listRows(array $filters): array
    {
        $hasFinancialFlag = $this->columnExists('dbo.tblSegments', 'UsedInFinancialAccount');
        $hasStrategicFlag = $this->columnExists('dbo.tblSegments', 'UsedInStrategicPlanning');
        $hasOrgStructureFlag = $this->columnExists('dbo.tblSegments', 'UsedInOrgStructure');
        $where = [];
        $params = [];

        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(CAST(s.SegmentID AS NVARCHAR(20)) LIKE :search
                OR s.SegmentCode LIKE :search
                OR s.SegmentName LIKE :search
                OR ISNULL(s.CBMSDimension, \'\') LIKE :search
                OR ISNULL(s.SegmentGroup, \'\') LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $dimension = trim((string)($filters['dimension'] ?? ''));
        if ($dimension !== '') {
            $where[] = 'ISNULL(s.CBMSDimension, \'\') = :dimension';
            $params[':dimension'] = $dimension;
        }

        $group = trim((string)($filters['group'] ?? ''));
        if ($group !== '') {
            $where[] = 'ISNULL(s.SegmentGroup, \'\') = :segmentGroup';
            $params[':segmentGroup'] = $group;
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT
                    s.SegmentID,
                    s.SegmentCode,
                    s.SegmentName,
                    s.MaxLength,
                    s.MinLength,
                    s.StartPoint,
                    s.EndPoint,
                    s.Attribute1Name,
                    s.Attribute2Name,
                    s.Attribute3Name,
                    s.Attribute4Name,
                    s.Type,
                    s.DefaultBusinessArea,
                    s.CBMSDimension,
                    s.Editable,
                    s.Static,
                    s.SegmentGroup,
                    " . ($hasFinancialFlag ? "ISNULL(s.UsedInFinancialAccount, 0)" : "CAST(0 AS INT)") . " AS UsedInFinancialAccount,
                    " . ($hasStrategicFlag ? "ISNULL(s.UsedInStrategicPlanning, 0)" : "CAST(0 AS INT)") . " AS UsedInStrategicPlanning,
                    " . ($hasOrgStructureFlag ? "ISNULL(s.UsedInOrgStructure, 0)" : "CAST(0 AS INT)") . " AS UsedInOrgStructure,
                    s.DisplayOrder,
                    s.Delimiter,
                    s.ParentSegmentNoDefault,
                    s.ParentRequired,
                    (SELECT COUNT(*) FROM dbo.tblSegmentValues sv WHERE sv.SegmentNo = s.SegmentID) AS ValueCount
                FROM dbo.tblSegments s
                {$whereSql}
                ORDER BY s.SegmentID";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $segmentId): ?array
    {
        if ($segmentId <= 0) {
            return null;
        }

        $hasFinancialFlag = $this->columnExists('dbo.tblSegments', 'UsedInFinancialAccount');
        $hasStrategicFlag = $this->columnExists('dbo.tblSegments', 'UsedInStrategicPlanning');
        $hasOrgStructureFlag = $this->columnExists('dbo.tblSegments', 'UsedInOrgStructure');

        $stmt = $this->pdo->prepare(
            "SELECT
                SegmentID,
                SegmentCode,
                SegmentName,
                MaxLength,
                MinLength,
                StartPoint,
                EndPoint,
                Attribute1Name,
                Attribute2Name,
                Attribute3Name,
                Attribute4Name,
                Type,
                DefaultBusinessArea,
                CBMSDimension,
                Editable,
                Static,
                SegmentGroup,
                " . ($hasFinancialFlag ? "ISNULL(UsedInFinancialAccount, 0)" : "CAST(0 AS INT)") . " AS UsedInFinancialAccount,
                " . ($hasStrategicFlag ? "ISNULL(UsedInStrategicPlanning, 0)" : "CAST(0 AS INT)") . " AS UsedInStrategicPlanning,
                " . ($hasOrgStructureFlag ? "ISNULL(UsedInOrgStructure, 0)" : "CAST(0 AS INT)") . " AS UsedInOrgStructure,
                DisplayOrder,
                Delimiter,
                ParentSegmentNoDefault,
                ParentRequired
             FROM dbo.tblSegments
             WHERE SegmentID = :segmentId"
        );
        $stmt->execute([':segmentId' => $segmentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data): void
    {
        $segmentId = (int)($data['SegmentID'] ?? 0);
        if ($segmentId <= 0) {
            throw new \RuntimeException('Segment ID is required.');
        }

        $segmentCode = trim((string)($data['SegmentCode'] ?? ''));
        $segmentName = trim((string)($data['SegmentName'] ?? ''));
        if ($segmentCode === '' || $segmentName === '') {
            throw new \RuntimeException('Segment Code and Segment Name are required.');
        }

        $maxLength = $this->nullIfZero($data['MaxLength'] ?? null);
        $minLength = $this->nullIfZero($data['MinLength'] ?? null);
        $startPoint = $this->nullIfZero($data['StartPoint'] ?? null);
        $endPoint = $this->nullIfZero($data['EndPoint'] ?? null);
        $cbmsDimension = $this->nullIfEmpty($data['CBMSDimension'] ?? null);
        $segmentGroup = $this->nullIfEmpty($data['SegmentGroup'] ?? null);
        $usedInFinancialAccount = !empty($data['UsedInFinancialAccount']) ? 1 : 0;
        $usedInStrategicPlanning = !empty($data['UsedInStrategicPlanning']) ? 1 : 0;
        $usedInOrgStructure = !empty($data['UsedInOrgStructure']) ? 1 : 0;
        $parentSegmentNoDefault = $this->nullIfZero($data['ParentSegmentNoDefault'] ?? null);

        if ($startPoint !== null && $startPoint <= 0) {
            throw new \RuntimeException('Start Point must be greater than zero when entered.');
        }
        if ($endPoint !== null && $endPoint <= 0) {
            throw new \RuntimeException('End Point must be greater than zero when entered.');
        }
        if ($startPoint !== null && $endPoint !== null && $endPoint < $startPoint) {
            throw new \RuntimeException('End Point must be greater than or equal to Start Point.');
        }
        if ($minLength !== null && $maxLength !== null && $minLength > $maxLength) {
            throw new \RuntimeException('Min Length cannot be greater than Max Length.');
        }
        if ($cbmsDimension === null) {
            throw new \RuntimeException('CBMS Dimension is required.');
        }
        if ($segmentGroup === null) {
            throw new \RuntimeException('Segment Group is required.');
        }
        if ($usedInFinancialAccount !== 1 && $usedInStrategicPlanning !== 1 && $usedInOrgStructure !== 1) {
            throw new \RuntimeException('At least one usage flag must be enabled for the segment.');
        }
        if ($parentSegmentNoDefault !== null) {
            if ($parentSegmentNoDefault === $segmentId) {
                throw new \RuntimeException('Parent Segment No Default cannot reference the same segment.');
            }
            if (!$this->segmentExists($parentSegmentNoDefault)) {
                throw new \RuntimeException('Selected Parent Segment No Default was not found.');
            }
        }
        if ($this->segmentCodeExistsForOtherSegment($segmentCode, $segmentId)) {
            throw new \RuntimeException('Segment Code must be unique.');
        }

        $existing = $this->getById($segmentId);
        $hasFinancialFlag = $this->columnExists('dbo.tblSegments', 'UsedInFinancialAccount');
        $hasStrategicFlag = $this->columnExists('dbo.tblSegments', 'UsedInStrategicPlanning');
        $hasOrgStructureFlag = $this->columnExists('dbo.tblSegments', 'UsedInOrgStructure');
        $params = [
            ':segmentId' => $segmentId,
            ':segmentCode' => $segmentCode,
            ':segmentName' => $segmentName,
            ':maxLength' => $maxLength,
            ':minLength' => $minLength,
            ':startPoint' => $startPoint,
            ':endPoint' => $endPoint,
            ':attribute1Name' => $this->nullIfEmpty($data['Attribute1Name'] ?? null),
            ':attribute2Name' => $this->nullIfEmpty($data['Attribute2Name'] ?? null),
            ':attribute3Name' => $this->nullIfEmpty($data['Attribute3Name'] ?? null),
            ':attribute4Name' => $this->nullIfEmpty($data['Attribute4Name'] ?? null),
            ':type' => $this->nullIfEmpty($data['Type'] ?? null),
            ':defaultBusinessArea' => $this->nullIfEmpty($data['DefaultBusinessArea'] ?? null),
            ':cbmsDimension' => $cbmsDimension,
            ':editable' => $this->nullIfEmpty($data['Editable'] ?? null),
            ':static' => $this->nullIfEmpty($data['Static'] ?? null),
            ':segmentGroup' => $segmentGroup,
            ':displayOrder' => $this->nullIfZero($data['DisplayOrder'] ?? null),
            ':delimiter' => $this->nullIfEmpty($data['Delimiter'] ?? null),
            ':parentSegmentNoDefault' => $parentSegmentNoDefault,
            ':parentRequired' => !empty($data['ParentRequired']) ? 1 : 0,
        ];
        if ($hasFinancialFlag) {
            $params[':usedInFinancialAccount'] = $usedInFinancialAccount;
        }
        if ($hasStrategicFlag) {
            $params[':usedInStrategicPlanning'] = $usedInStrategicPlanning;
        }
        if ($hasOrgStructureFlag) {
            $params[':usedInOrgStructure'] = $usedInOrgStructure;
        }

        if ($existing !== null) {
            $sql = "UPDATE dbo.tblSegments
                    SET SegmentCode = :segmentCode,
                        SegmentName = :segmentName,
                        MaxLength = :maxLength,
                        MinLength = :minLength,
                        StartPoint = :startPoint,
                        EndPoint = :endPoint,
                        Attribute1Name = :attribute1Name,
                        Attribute2Name = :attribute2Name,
                        Attribute3Name = :attribute3Name,
                        Attribute4Name = :attribute4Name,
                        Type = :type,
                        DefaultBusinessArea = :defaultBusinessArea,
                        CBMSDimension = :cbmsDimension,
                        Editable = :editable,
                        Static = :static,
                        SegmentGroup = :segmentGroup";
            if ($hasFinancialFlag) {
                $sql .= ",
                        UsedInFinancialAccount = :usedInFinancialAccount";
            }
            if ($hasStrategicFlag) {
                $sql .= ",
                        UsedInStrategicPlanning = :usedInStrategicPlanning";
            }
            if ($hasOrgStructureFlag) {
                $sql .= ",
                        UsedInOrgStructure = :usedInOrgStructure";
            }
            $sql .= ",
                        DisplayOrder = :displayOrder,
                        Delimiter = :delimiter,
                        ParentSegmentNoDefault = :parentSegmentNoDefault,
                        ParentRequired = :parentRequired
                    WHERE SegmentID = :segmentId";
        } else {
            $sql = "INSERT INTO dbo.tblSegments (
                        SegmentID,
                        SegmentCode,
                        SegmentName,
                        MaxLength,
                        MinLength,
                        StartPoint,
                        EndPoint,
                        Attribute1Name,
                        Attribute2Name,
                        Attribute3Name,
                        Attribute4Name,
                        Type,
                        DefaultBusinessArea,
                        CBMSDimension,
                        Editable,
                        Static,
                        SegmentGroup";
            if ($hasFinancialFlag) {
                $sql .= ",
                        UsedInFinancialAccount";
            }
            if ($hasStrategicFlag) {
                $sql .= ",
                        UsedInStrategicPlanning";
            }
            if ($hasOrgStructureFlag) {
                $sql .= ",
                        UsedInOrgStructure";
            }
            $sql .= ",
                        DisplayOrder,
                        Delimiter,
                        ParentSegmentNoDefault,
                        ParentRequired
                    ) VALUES (
                        :segmentId,
                        :segmentCode,
                        :segmentName,
                        :maxLength,
                        :minLength,
                        :startPoint,
                        :endPoint,
                        :attribute1Name,
                        :attribute2Name,
                        :attribute3Name,
                        :attribute4Name,
                        :type,
                        :defaultBusinessArea,
                        :cbmsDimension,
                        :editable,
                        :static,
                        :segmentGroup";
            if ($hasFinancialFlag) {
                $sql .= ",
                        :usedInFinancialAccount";
            }
            if ($hasStrategicFlag) {
                $sql .= ",
                        :usedInStrategicPlanning";
            }
            if ($hasOrgStructureFlag) {
                $sql .= ",
                        :usedInOrgStructure";
            }
            $sql .= ",
                        :displayOrder,
                        :delimiter,
                        :parentSegmentNoDefault,
                        :parentRequired
                    )";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function saveImportRow(array $data): bool
    {
        $segmentId = (int)($data['SegmentID'] ?? 0);
        if ($segmentId <= 0) {
            throw new \RuntimeException('SegmentID is required.');
        }

        $existing = $this->getById($segmentId);
        $payload = [
            'SegmentID' => $segmentId,
            'SegmentCode' => trim((string)($data['SegmentCode'] ?? '')),
            'SegmentName' => trim((string)($data['SegmentName'] ?? '')),
            'MaxLength' => $data['MaxLength'] ?? null,
            'MinLength' => $data['MinLength'] ?? null,
            'StartPoint' => $data['StartPoint'] ?? null,
            'EndPoint' => $data['EndPoint'] ?? null,
            'Attribute1Name' => trim((string)($data['Attribute1Name'] ?? '')),
            'Attribute2Name' => trim((string)($data['Attribute2Name'] ?? '')),
            'Attribute3Name' => trim((string)($data['Attribute3Name'] ?? '')),
            'Attribute4Name' => trim((string)($data['Attribute4Name'] ?? '')),
            'Type' => trim((string)($data['Type'] ?? '')),
            'DefaultBusinessArea' => trim((string)($data['DefaultBusinessArea'] ?? '')),
            'CBMSDimension' => trim((string)($data['CBMSDimension'] ?? '')),
            'Editable' => trim((string)($data['Editable'] ?? '')),
            'Static' => trim((string)($data['Static'] ?? '')),
            'SegmentGroup' => trim((string)($data['SegmentGroup'] ?? '')),
            'UsedInFinancialAccount' => (int)($data['UsedInFinancialAccount'] ?? 0),
            'UsedInStrategicPlanning' => (int)($data['UsedInStrategicPlanning'] ?? 0),
            'UsedInOrgStructure' => (int)($data['UsedInOrgStructure'] ?? 0),
            'DisplayOrder' => $data['DisplayOrder'] ?? null,
            'Delimiter' => trim((string)($data['Delimiter'] ?? '')),
            'ParentSegmentNoDefault' => $data['ParentSegmentNoDefault'] ?? null,
            'ParentRequired' => (int)($data['ParentRequired'] ?? 0),
        ];

        $this->save($payload);
        return $existing === null;
    }

    public function buildTemplateWorkbook(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Segments');

        $headers = [
            'SegmentID',
            'SegmentCode',
            'SegmentName',
            'MaxLength',
            'MinLength',
            'StartPoint',
            'EndPoint',
            'Attribute1Name',
            'Attribute2Name',
            'Attribute3Name',
            'Attribute4Name',
            'Type',
            'DefaultBusinessArea',
            'CBMSDimension',
            'Editable',
            'Static',
            'SegmentGroup',
            'UsedInFinancialAccount',
            'UsedInStrategicPlanning',
            'UsedInOrgStructure',
            'DisplayOrder',
            'Delimiter',
            'ParentSegmentNoDefault',
            'ParentRequired',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            [1, 'FUND', 'Fund', 4, 4, 1, 4, '', '', '', '', 'Master', '', 'FUND', 'Y', 'N', 'Core', 1, 0, 0, 10, '-', '', 0],
            [2, 'PROGRAM', 'Program', 4, 4, 5, 8, '', '', '', '', 'Hierarchy', '', 'PROGRAM', 'Y', 'N', 'Core', 1, 1, 0, 20, '-', '', 0],
        ], null, 'A2');

        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Instructions');
        $notes->fromArray([
            ['Column', 'Required', 'Notes'],
            ['SegmentID', 'Yes', 'Primary key and import upsert key.'],
            ['SegmentCode', 'Yes', 'Short client-facing segment code.'],
            ['SegmentName', 'Yes', 'Display label for the segment.'],
            ['MaxLength', 'No', 'Maximum code length.'],
            ['MinLength', 'No', 'Minimum code length.'],
            ['StartPoint', 'No', 'Start position in the composite code.'],
            ['EndPoint', 'No', 'End position in the composite code.'],
            ['Attribute1Name-Attribute4Name', 'No', 'Optional attribute labels.'],
            ['Type', 'No', 'Optional segment classification.'],
            ['DefaultBusinessArea', 'No', 'Optional default business area.'],
            ['CBMSDimension', 'No', 'Dimension mapping used by readiness and downstream modules.'],
            ['Editable', 'No', 'Client convention flag such as Y/N.'],
            ['Static', 'No', 'Client convention flag such as Y/N.'],
            ['SegmentGroup', 'No', 'Optional grouping label.'],
            ['UsedInFinancialAccount', 'No', 'Use 1 or 0. Defaults to 0 when blank.'],
            ['UsedInStrategicPlanning', 'No', 'Use 1 or 0. Defaults to 0 when blank.'],
            ['UsedInOrgStructure', 'No', 'Use 1 or 0. Defaults to 0 when blank.'],
            ['DisplayOrder', 'No', 'Controls list ordering.'],
            ['Delimiter', 'No', 'Optional separator character.'],
            ['ParentSegmentNoDefault', 'No', 'Optional default parent segment number.'],
            ['ParentRequired', 'No', 'Use 1 or 0. Defaults to 0 when blank.'],
        ], null, 'A1');

        for ($col = 1; $col <= 3; $col++) {
            $notes->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function listDimensions(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT CBMSDimension
             FROM dbo.tblSegments
             WHERE NULLIF(LTRIM(RTRIM(ISNULL(CBMSDimension, ''))), '') IS NOT NULL
             ORDER BY CBMSDimension"
        );
        return array_map(
            static fn(array $row): string => (string)$row['CBMSDimension'],
            $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : []
        );
    }

    public function listGroups(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT SegmentGroup
             FROM dbo.tblSegments
             WHERE NULLIF(LTRIM(RTRIM(ISNULL(SegmentGroup, ''))), '') IS NOT NULL
             ORDER BY SegmentGroup"
        );
        return array_map(
            static fn(array $row): string => (string)$row['SegmentGroup'],
            $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : []
        );
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function segmentExists(int $segmentId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM dbo.tblSegments
             WHERE SegmentID = :segmentId"
        );
        $stmt->execute([':segmentId' => $segmentId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function segmentCodeExistsForOtherSegment(string $segmentCode, int $segmentId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM dbo.tblSegments
             WHERE SegmentCode = :segmentCode
               AND SegmentID <> :segmentId"
        );
        $stmt->execute([
            ':segmentCode' => $segmentCode,
            ':segmentId' => $segmentId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :schemaName
              AND TABLE_NAME = :tableName
              AND COLUMN_NAME = :columnName
        ";

        $schemaName = 'dbo';
        $tableOnly = $tableName;
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableOnly] = explode('.', $tableName, 2);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':schemaName' => $schemaName,
            ':tableName' => $tableOnly,
            ':columnName' => $columnName,
        ]);

        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function nullIfZero(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = (int)$value;
        return $number === 0 ? null : $number;
    }
}
