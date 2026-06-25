<?php
declare(strict_types=1);

namespace App\Models;

final class SegmentValuesAdminModel
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function listFiscalYears(): array
    {
        $stmt = $this->pdo->query(
            "SELECT FiscalYearID, YearLabel
             FROM dbo.tblFiscalYears
             WHERE IsActive = 1
             ORDER BY FiscalYearID DESC"
        );
        return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function listSegments(): array
    {
        $stmt = $this->pdo->query(
            "SELECT SegmentID AS SegmentNo, SegmentName
             FROM dbo.tblSegments
             ORDER BY SegmentID"
        );
        return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function listDataObjectCodes(int $fiscalYearId): array
    {
        if ($fiscalYearId <= 0) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            "SELECT DataObjectCode, DataObjectName
             FROM dbo.tblDataObjectCodes
             WHERE FiscalYearID = :fy
             ORDER BY DataObjectCode"
        );
        $stmt->execute([':fy' => $fiscalYearId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listDataObjectCodesAll(): array
    {
        $stmt = $this->pdo->query(
            "SELECT DISTINCT DataObjectCode
             FROM dbo.tblDataObjectCodes
             ORDER BY DataObjectCode"
        );
        return array_map(
            static fn(array $row): string => (string)$row['DataObjectCode'],
            $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : []
        );
    }

    public function listRows(array $filters): array
    {
        $where = [];
        $params = [];

        $fy = (int)($filters['fy'] ?? 0);
        if ($fy > 0) {
            $where[] = 'sv.FiscalYearID = :fy';
            $params[':fy'] = $fy;
        }

        $dataObjectCode = trim((string)($filters['data_object_code'] ?? ''));
        if ($dataObjectCode !== '') {
            $where[] = 'sv.DataObjectCode = :dataObjectCode';
            $params[':dataObjectCode'] = $dataObjectCode;
        }

        $segmentNo = (int)($filters['segment_no'] ?? 0);
        if ($segmentNo > 0) {
            $where[] = 'sv.SegmentNo = :segmentNo';
            $params[':segmentNo'] = $segmentNo;
        }

        $active = trim((string)($filters['active'] ?? '1'));
        if ($active === '1') {
            $where[] = 'sv.ActiveFlag = 1';
        } elseif ($active === '0') {
            $where[] = 'sv.ActiveFlag = 0';
        }

        $search = trim((string)($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(sv.SegmentCode LIKE :search OR ISNULL(sv.SegmentName, \'\') LIKE :search OR sv.DataObjectCode LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "SELECT sv.SegmentValueID,
                       sv.FiscalYearID,
                       fy.YearLabel,
                       sv.DataObjectCode,
                       doc.DataObjectName,
                       sv.SegmentNo,
                       seg.SegmentName AS SegmentLabel,
                       sv.SegmentCode,
                       sv.SegmentName,
                       sv.SegmentExternalID,
                       sv.ParentSegmentValueID,
                       sv.ParentSegmentNo,
                       parentSeg.SegmentName AS ParentSegmentLabel,
                       sv.ParentSegmentCode,
                       " . ($this->columnExists('dbo.tblSegmentValues', 'ParentSegmentDataObjectCode') ? 'sv.ParentSegmentDataObjectCode' : 'CONVERT(nvarchar(50), NULL) AS ParentSegmentDataObjectCode') . ",
                       sv.SortOrder,
                       sv.ActiveFlag,
                       sv.UpdatedBy,
                       sv.UpdatedDate
                FROM dbo.tblSegmentValues sv
                LEFT JOIN dbo.tblFiscalYears fy
                  ON fy.FiscalYearID = sv.FiscalYearID
                LEFT JOIN dbo.tblDataObjectCodes doc
                  ON doc.FiscalYearID = sv.FiscalYearID
                 AND doc.DataObjectCode = sv.DataObjectCode
                LEFT JOIN dbo.tblSegments seg
                  ON seg.SegmentID = sv.SegmentNo
                LEFT JOIN dbo.tblSegments parentSeg
                  ON parentSeg.SegmentID = sv.ParentSegmentNo
                {$whereSql}
                ORDER BY sv.FiscalYearID DESC,
                         sv.DataObjectCode,
                         sv.SegmentNo,
                         sv.SortOrder,
                         sv.SegmentCode,
                         sv.SegmentValueID";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT SegmentValueID,
                    FiscalYearID,
                    DataObjectCode,
                    SegmentNo,
                    SegmentCode,
                    SegmentName,
                    SegmentExternalID,
                    ParentSegmentValueID,
                    SortOrder,
                    ActiveFlag,
                    UpdatedBy,
                    UpdatedDate,
                    ParentSegmentNo,
                    ParentSegmentCode,
                    " . ($this->columnExists('dbo.tblSegmentValues', 'ParentSegmentDataObjectCode') ? 'ParentSegmentDataObjectCode' : 'CONVERT(nvarchar(50), NULL) AS ParentSegmentDataObjectCode') . "
             FROM dbo.tblSegmentValues
             WHERE SegmentValueID = :id"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, bool $resolveParentLinks = true): void
    {
        $id = (int)($data['SegmentValueID'] ?? 0);
        $fiscalYearId = (int)($data['FiscalYearID'] ?? 0);
        $dataObjectCode = trim((string)($data['DataObjectCode'] ?? ''));
        $segmentNo = (int)($data['SegmentNo'] ?? 0);
        $segmentCode = trim((string)($data['SegmentCode'] ?? ''));
        $activeFlag = (int)($data['ActiveFlag'] ?? 1);
        $updatedBy = (int)($data['UpdatedBy'] ?? 0);
        $parentSegmentValueId = (int)($data['ParentSegmentValueID'] ?? 0);
        $parentSegmentNo = (int)($data['ParentSegmentNo'] ?? 0);
        $parentSegmentCode = trim((string)($data['ParentSegmentCode'] ?? ''));
        $parentSegmentDataObjectCode = trim((string)($data['ParentSegmentDataObjectCode'] ?? ''));
        $hasParentDataObjectCode = $this->columnExists('dbo.tblSegmentValues', 'ParentSegmentDataObjectCode');

        if ($fiscalYearId <= 0) {
            throw new \RuntimeException('Fiscal year is required.');
        }
        if ($dataObjectCode === '') {
            throw new \RuntimeException('Data Object Code is required.');
        }
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Segment No is required.');
        }
        if ($segmentCode === '') {
            throw new \RuntimeException('Segment Code is required.');
        }
        if ($activeFlag !== 0 && $activeFlag !== 1) {
            throw new \RuntimeException('ActiveFlag must be 0 or 1.');
        }
        if ($updatedBy <= 0) {
            throw new \RuntimeException('A valid user context is required.');
        }
        if ($id > 0 && $parentSegmentValueId === $id) {
            throw new \RuntimeException('Parent Segment Value cannot reference the same segment value.');
        }
        if (!$this->fiscalYearExists($fiscalYearId)) {
            throw new \RuntimeException('Selected fiscal year was not found.');
        }
        if (!$this->dataObjectCodeExists($fiscalYearId, $dataObjectCode)) {
            throw new \RuntimeException('Selected Data Object Code was not found for the fiscal year.');
        }
        if (!$this->segmentExists($segmentNo)) {
            throw new \RuntimeException('Selected Segment No was not found.');
        }
        if ($parentSegmentValueId > 0 && !$this->segmentValueExists($parentSegmentValueId, $fiscalYearId)) {
            throw new \RuntimeException('Selected parent segment value was not found for the fiscal year.');
        }
        if (($parentSegmentNo > 0 && $parentSegmentCode === '') || ($parentSegmentNo <= 0 && $parentSegmentCode !== '')) {
            throw new \RuntimeException('Parent Segment No and Parent Segment Code must both be provided or both be blank.');
        }
        if ($parentSegmentNo > 0) {
            if (!$this->segmentExists($parentSegmentNo)) {
                throw new \RuntimeException('Selected Parent Segment No was not found.');
            }
            if (!$this->segmentCodeExists($fiscalYearId, $parentSegmentNo, $parentSegmentCode)) {
                throw new \RuntimeException('Selected Parent Segment Code was not found for the fiscal year and parent segment.');
            }
        }

        $params = [
            ':fy' => $fiscalYearId,
            ':dataObjectCode' => $dataObjectCode,
            ':segmentNo' => $segmentNo,
            ':segmentCode' => $segmentCode,
            ':segmentName' => $this->nullIfEmpty($data['SegmentName'] ?? null),
            ':segmentExternalId' => $this->nullIfEmpty($data['SegmentExternalID'] ?? null),
            ':parentSegmentValueId' => $this->nullIfZero($parentSegmentValueId),
            ':sortOrder' => (int)$data['SortOrder'],
            ':activeFlag' => $activeFlag,
            ':updatedBy' => $updatedBy,
            ':parentSegmentNo' => $this->nullIfZero($parentSegmentNo),
            ':parentSegmentCode' => $this->nullIfEmpty($parentSegmentCode),
        ];
        if ($hasParentDataObjectCode) {
            $params[':parentSegmentDataObjectCode'] = $this->nullIfEmpty($parentSegmentDataObjectCode);
        }

        if ($id > 0) {
            $params[':id'] = $id;
            $sql = "UPDATE dbo.tblSegmentValues
                    SET FiscalYearID = :fy,
                        DataObjectCode = :dataObjectCode,
                        SegmentNo = :segmentNo,
                        SegmentCode = :segmentCode,
                        SegmentName = :segmentName,
                        SegmentExternalID = :segmentExternalId,
                        ParentSegmentValueID = :parentSegmentValueId,
                        SortOrder = :sortOrder,
                        ActiveFlag = :activeFlag,
                        UpdatedBy = :updatedBy,
                        UpdatedDate = GETDATE(),
                        ParentSegmentNo = :parentSegmentNo,
                        ParentSegmentCode = :parentSegmentCode" . ($hasParentDataObjectCode ? ",
                        ParentSegmentDataObjectCode = :parentSegmentDataObjectCode" : "") . "
                    WHERE SegmentValueID = :id";
        } else {
            $sql = "INSERT INTO dbo.tblSegmentValues (
                        FiscalYearID,
                        DataObjectCode,
                        SegmentNo,
                        SegmentCode,
                        SegmentName,
                        SegmentExternalID,
                        ParentSegmentValueID,
                        SortOrder,
                        ActiveFlag,
                        UpdatedBy,
                        UpdatedDate,
                        ParentSegmentNo,
                        ParentSegmentCode" . ($hasParentDataObjectCode ? ",
                        ParentSegmentDataObjectCode" : "") . "
                    ) VALUES (
                        :fy,
                        :dataObjectCode,
                        :segmentNo,
                        :segmentCode,
                        :segmentName,
                        :segmentExternalId,
                        :parentSegmentValueId,
                        :sortOrder,
                        :activeFlag,
                        :updatedBy,
                        GETDATE(),
                        :parentSegmentNo,
                        :parentSegmentCode" . ($hasParentDataObjectCode ? ",
                        :parentSegmentDataObjectCode" : "") . "
                    )";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        if ($resolveParentLinks) {
            $this->resolveParentSegmentValueIds($fiscalYearId);
        }
    }

    public function archive(int $id, ?int $updatedBy = null): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE dbo.tblSegmentValues
             SET ActiveFlag = 0,
                 UpdatedBy = :updatedBy,
                 UpdatedDate = GETDATE()
             WHERE SegmentValueID = :id"
        );
        $stmt->execute([
            ':updatedBy' => $updatedBy,
            ':id' => $id,
        ]);
    }

    public function deleteById(int $id): array
    {
        if ($id <= 0) {
            throw new \RuntimeException('Segment value id is required.');
        }

        $record = $this->getById($id);
        if (!$record) {
            throw new \RuntimeException('Segment value was not found.');
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM dbo.tblSegmentValues
             WHERE SegmentValueID = :id"
        );
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() !== 1) {
            throw new \RuntimeException('Segment value delete did not remove exactly one row.');
        }

        return $record;
    }

    public function saveImportRow(array $data): bool
    {
        $existingId = $this->findExistingId(
            (int)$data['FiscalYearID'],
            trim((string)$data['DataObjectCode']),
            (int)$data['SegmentNo'],
            trim((string)$data['SegmentCode'])
        );

        $payload = $data;
        $payload['SegmentValueID'] = $existingId ?? 0;
        $payload['SortOrder'] = (int)($data['SortOrder'] ?? 0);
        $payload['ActiveFlag'] = (int)($data['ActiveFlag'] ?? 1);
        $payload['UpdatedBy'] = $data['UpdatedBy'] ?? null;

        $this->save($payload, false);
        return $existingId === null;
    }

    public function resolveParentSegmentValueIds(int $fiscalYearId, ?int $segmentNo = null): array
    {
        if ($fiscalYearId <= 0) {
            throw new \RuntimeException('Fiscal year is required before resolving parent links.');
        }

        if ($this->procedureExists('dbo.uspResolveSegmentValueParentLinks')) {
            $stmt = $this->pdo->prepare(
                "EXEC dbo.uspResolveSegmentValueParentLinks
                    @FiscalYearID = :fy,
                    @SegmentNo = :segmentNo"
            );
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo !== null && $segmentNo > 0 ? $segmentNo : null,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: [
                'ParentDataObjectRowsUpdated' => 0,
                'ParentValueRowsUpdated' => 0,
                'MissingParentValueIDRows' => 0,
            ];
        }

        return $this->resolveParentSegmentValueIdsInline($fiscalYearId, $segmentNo);
    }

    public function buildTemplateWorkbook(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SegmentValues');
        $headers = [
            'FiscalYearID',
            'DataObjectCode',
            'SegmentNo',
            'SegmentCode',
            'SegmentName',
            'SegmentExternalID',
            'ParentSegmentValueID',
            'ParentSegmentNo',
            'ParentSegmentDataObjectCode',
            'ParentSegmentCode',
            'SortOrder',
            'ActiveFlag',
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            ['2026', '1001', '9', '0001', 'Example Project', 'EXT-1', '', '', '', '', '10', '1'],
        ], null, 'A2');
        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $help = $spreadsheet->createSheet();
        $help->setTitle('Instructions');
        $help->fromArray([
            ['Column', 'Required', 'Notes'],
            ['FiscalYearID', 'Yes unless override selected on upload', 'Numeric fiscal year id.'],
            ['DataObjectCode', 'Yes', 'Must exist in tblDataObjectCodes for the fiscal year.'],
            ['SegmentNo', 'Yes', 'Matches tblSegments.SegmentID.'],
            ['SegmentCode', 'Yes', 'Upsert key with fiscal year, data object, and segment number.'],
            ['SegmentName', 'No', 'Friendly label.'],
            ['SegmentExternalID', 'No', 'External reference if used.'],
            ['ParentSegmentValueID', 'No', 'Optional direct parent value id.'],
            ['ParentSegmentNo', 'No', 'Optional parent segment number.'],
            ['ParentSegmentDataObjectCode', 'No', 'Optional parent data object code. The application resolver can populate it.'],
            ['ParentSegmentCode', 'No', 'Optional parent segment code.'],
            ['SortOrder', 'No', 'Defaults to 0.'],
            ['ActiveFlag', 'No', 'Use 1 or 0. Defaults to 1.'],
        ], null, 'A1');
        for ($col = 1; $col <= 3; $col++) {
            $help->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    public function buildExportWorkbook(array $filters): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SegmentValues');

        $headers = [
            'FiscalYearID',
            'YearLabel',
            'DataObjectCode',
            'DataObjectName',
            'SegmentNo',
            'SegmentLabel',
            'SegmentCode',
            'SegmentName',
            'SegmentExternalID',
            'ParentSegmentValueID',
            'ParentSegmentNo',
            'ParentSegmentLabel',
            'ParentSegmentDataObjectCode',
            'ParentSegmentCode',
            'SortOrder',
            'ActiveFlag',
            'Status',
            'UpdatedBy',
            'UpdatedDate',
        ];

        $sheet->fromArray($headers, null, 'A1');

        $data = [];
        foreach ($this->listRows($filters) as $row) {
            $activeFlag = (int) ($row['ActiveFlag'] ?? 0);
            $data[] = [
                $row['FiscalYearID'] ?? '',
                $row['YearLabel'] ?? '',
                $row['DataObjectCode'] ?? '',
                $row['DataObjectName'] ?? '',
                $row['SegmentNo'] ?? '',
                $row['SegmentLabel'] ?? '',
                $row['SegmentCode'] ?? '',
                $row['SegmentName'] ?? '',
                $row['SegmentExternalID'] ?? '',
                $row['ParentSegmentValueID'] ?? '',
                $row['ParentSegmentNo'] ?? '',
                $row['ParentSegmentLabel'] ?? '',
                $row['ParentSegmentDataObjectCode'] ?? '',
                $row['ParentSegmentCode'] ?? '',
                $row['SortOrder'] ?? '',
                $row['ActiveFlag'] ?? '',
                $activeFlag === 1 ? 'Active' : 'Archived',
                $row['UpdatedBy'] ?? '',
                $row['UpdatedDate'] ?? '',
            ];
        }

        if ($data !== []) {
            $sheet->fromArray($data, null, 'A2');
        }

        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function findExistingId(int $fiscalYearId, string $dataObjectCode, int $segmentNo, string $segmentCode): ?int
    {
        if ($fiscalYearId <= 0 || $dataObjectCode === '' || $segmentNo <= 0 || $segmentCode === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT TOP 1 SegmentValueID
             FROM dbo.tblSegmentValues
             WHERE FiscalYearID = :fy
               AND DataObjectCode = :dataObjectCode
               AND SegmentNo = :segmentNo
               AND SegmentCode = :segmentCode
             ORDER BY SegmentValueID"
        );
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':dataObjectCode' => $dataObjectCode,
            ':segmentNo' => $segmentNo,
            ':segmentCode' => $segmentCode,
        ]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    private function fiscalYearExists(int $fiscalYearId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM dbo.tblFiscalYears
             WHERE FiscalYearID = :fy
               AND ISNULL(IsActive, 1) = 1"
        );
        $stmt->execute([':fy' => $fiscalYearId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function dataObjectCodeExists(int $fiscalYearId, string $dataObjectCode): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM dbo.tblDataObjectCodes
             WHERE FiscalYearID = :fy
               AND DataObjectCode = :dataObjectCode"
        );
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':dataObjectCode' => $dataObjectCode,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function segmentExists(int $segmentNo): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM dbo.tblSegments
             WHERE SegmentID = :segmentNo"
        );
        $stmt->execute([':segmentNo' => $segmentNo]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function segmentValueExists(int $segmentValueId, int $fiscalYearId): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM dbo.tblSegmentValues
             WHERE SegmentValueID = :segmentValueId
               AND FiscalYearID = :fy"
        );
        $stmt->execute([
            ':segmentValueId' => $segmentValueId,
            ':fy' => $fiscalYearId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function segmentCodeExists(int $fiscalYearId, int $segmentNo, string $segmentCode): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*)
             FROM dbo.tblSegmentValues
             WHERE FiscalYearID = :fy
               AND SegmentNo = :segmentNo
               AND SegmentCode = :segmentCode"
        );
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
            ':segmentCode' => $segmentCode,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function resolveParentSegmentValueIdsInline(int $fiscalYearId, ?int $segmentNo = null): array
    {
        if (!$this->columnExists('dbo.tblSegmentValues', 'ParentSegmentNo')
            || !$this->columnExists('dbo.tblSegmentValues', 'ParentSegmentCode')
            || !$this->columnExists('dbo.tblSegmentValues', 'ParentSegmentValueID')
        ) {
            return [
                'FiscalYearID' => $fiscalYearId,
                'SegmentNo' => $segmentNo,
                'ParentDataObjectRowsUpdated' => 0,
                'ParentValueRowsUpdated' => 0,
                'MissingParentValueIDRows' => 0,
            ];
        }

        $parentDataObjectRows = 0;
        $hasParentDataObjectCode = $this->columnExists('dbo.tblSegmentValues', 'ParentSegmentDataObjectCode');

        if ($hasParentDataObjectCode && $this->tableExists('dbo.tblDataObjectCodes')) {
            $stmt = $this->pdo->prepare("
                WITH ChildRows AS (
                    SELECT
                        child.SegmentValueID,
                        child.FiscalYearID,
                        child.DataObjectCode,
                        child.ParentSegmentNo,
                        child.ParentSegmentCode
                    FROM dbo.tblSegmentValues child
                    WHERE child.FiscalYearID = :fy
                      AND (:segmentNo IS NULL OR child.SegmentNo = :segmentNoFilter)
                      AND child.ParentSegmentNo IS NOT NULL
                      AND NULLIF(LTRIM(RTRIM(ISNULL(child.ParentSegmentCode, N''))), N'') IS NOT NULL
                ),
                DataObjectScope AS (
                    SELECT
                        child.SegmentValueID,
                        ScopeDataObjectCode = CONVERT(nvarchar(50), child.DataObjectCode),
                        ScopeDepth = 0
                    FROM ChildRows child

                    UNION ALL

                    SELECT
                        scope.SegmentValueID,
                        ScopeDataObjectCode = CONVERT(nvarchar(50), doc.DataObjectCodeParent),
                        ScopeDepth = scope.ScopeDepth + 1
                    FROM DataObjectScope scope
                    INNER JOIN dbo.tblDataObjectCodes doc
                        ON doc.FiscalYearID = :fyScope
                       AND doc.DataObjectCode = scope.ScopeDataObjectCode
                    WHERE NULLIF(LTRIM(RTRIM(ISNULL(doc.DataObjectCodeParent, N''))), N'') IS NOT NULL
                      AND doc.DataObjectCodeParent <> scope.ScopeDataObjectCode
                      AND scope.ScopeDepth < 20
                ),
                ParentCandidates AS (
                    SELECT
                        child.SegmentValueID,
                        ParentSegmentDataObjectCode = parent.DataObjectCode,
                        rn = ROW_NUMBER() OVER (
                            PARTITION BY child.SegmentValueID
                            ORDER BY
                                CASE WHEN scope.ScopeDataObjectCode IS NOT NULL THEN 0 ELSE 1 END,
                                ISNULL(scope.ScopeDepth, 999),
                                CASE WHEN parent.DataObjectCode = child.DataObjectCode THEN 0 ELSE 1 END,
                                parent.SegmentValueID
                        )
                    FROM ChildRows child
                    INNER JOIN dbo.tblSegmentValues parent
                        ON parent.FiscalYearID = child.FiscalYearID
                       AND parent.SegmentNo = child.ParentSegmentNo
                       AND parent.SegmentCode = child.ParentSegmentCode
                       AND parent.ActiveFlag = 1
                    LEFT JOIN DataObjectScope scope
                        ON scope.SegmentValueID = child.SegmentValueID
                       AND scope.ScopeDataObjectCode = parent.DataObjectCode
                )
                UPDATE child
                SET child.ParentSegmentDataObjectCode = candidate.ParentSegmentDataObjectCode
                FROM dbo.tblSegmentValues child
                INNER JOIN ParentCandidates candidate
                    ON candidate.SegmentValueID = child.SegmentValueID
                   AND candidate.rn = 1
                WHERE ISNULL(child.ParentSegmentDataObjectCode, N'') <> ISNULL(candidate.ParentSegmentDataObjectCode, N'')
                OPTION (MAXRECURSION 100)
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':fyScope' => $fiscalYearId,
                ':segmentNo' => $segmentNo !== null && $segmentNo > 0 ? $segmentNo : null,
                ':segmentNoFilter' => $segmentNo !== null && $segmentNo > 0 ? $segmentNo : null,
            ]);
            $parentDataObjectRows = $stmt->rowCount();
        }

        $dataObjectPredicate = $hasParentDataObjectCode
            ? "AND (
                    NULLIF(LTRIM(RTRIM(ISNULL(child.ParentSegmentDataObjectCode, N''))), N'') IS NULL
                    OR parent.DataObjectCode = child.ParentSegmentDataObjectCode
               )"
            : '';
        $dataObjectOrder = $hasParentDataObjectCode
            ? "CASE WHEN parent.DataObjectCode = child.ParentSegmentDataObjectCode THEN 0 ELSE 1 END,"
            : '';

        $stmt = $this->pdo->prepare("
            WITH ParentMatches AS (
                SELECT
                    child.SegmentValueID AS ChildSegmentValueID,
                    parent.SegmentValueID AS ParentSegmentValueID,
                    rn = ROW_NUMBER() OVER (
                        PARTITION BY child.SegmentValueID
                        ORDER BY
                            {$dataObjectOrder}
                            parent.SegmentValueID
                    ),
                    match_count = COUNT(parent.SegmentValueID) OVER (PARTITION BY child.SegmentValueID)
                FROM dbo.tblSegmentValues child
                INNER JOIN dbo.tblSegmentValues parent
                    ON parent.FiscalYearID = child.FiscalYearID
                   AND parent.SegmentNo = child.ParentSegmentNo
                   AND parent.SegmentCode = child.ParentSegmentCode
                   AND parent.ActiveFlag = 1
                   {$dataObjectPredicate}
                WHERE child.FiscalYearID = :fy
                  AND (:segmentNo IS NULL OR child.SegmentNo = :segmentNoFilter)
                  AND child.ParentSegmentNo IS NOT NULL
                  AND NULLIF(LTRIM(RTRIM(ISNULL(child.ParentSegmentCode, N''))), N'') IS NOT NULL
            )
            UPDATE child
            SET child.ParentSegmentValueID = matches.ParentSegmentValueID
            FROM dbo.tblSegmentValues child
            INNER JOIN ParentMatches matches
                ON matches.ChildSegmentValueID = child.SegmentValueID
               AND matches.rn = 1
               AND matches.match_count = 1
            WHERE ISNULL(child.ParentSegmentValueID, 0) <> matches.ParentSegmentValueID
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo !== null && $segmentNo > 0 ? $segmentNo : null,
            ':segmentNoFilter' => $segmentNo !== null && $segmentNo > 0 ? $segmentNo : null,
        ]);
        $parentValueRows = $stmt->rowCount();

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSegmentValues child
            WHERE child.FiscalYearID = :fy
              AND (:segmentNo IS NULL OR child.SegmentNo = :segmentNoFilter)
              AND child.ParentSegmentNo IS NOT NULL
              AND NULLIF(LTRIM(RTRIM(ISNULL(child.ParentSegmentCode, N''))), N'') IS NOT NULL
              AND child.ParentSegmentValueID IS NULL
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo !== null && $segmentNo > 0 ? $segmentNo : null,
            ':segmentNoFilter' => $segmentNo !== null && $segmentNo > 0 ? $segmentNo : null,
        ]);

        return [
            'FiscalYearID' => $fiscalYearId,
            'SegmentNo' => $segmentNo,
            'ParentDataObjectRowsUpdated' => $parentDataObjectRows,
            'ParentValueRowsUpdated' => $parentValueRows,
            'MissingParentValueIDRows' => (int) ($stmt->fetchColumn() ?: 0),
        ];
    }

    private function tableExists(string $qualifiedName): bool
    {
        $parts = explode('.', str_replace(['[', ']'], '', $qualifiedName));
        $schema = count($parts) > 1 ? $parts[0] : 'dbo';
        $table = count($parts) > 1 ? $parts[1] : $parts[0];

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM sys.tables t
            INNER JOIN sys.schemas s
                ON s.schema_id = t.schema_id
            WHERE s.name = :schemaName
              AND t.name = :tableName
        ");
        $stmt->execute([
            ':schemaName' => $schema,
            ':tableName' => $table,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $qualifiedName, string $columnName): bool
    {
        $parts = explode('.', str_replace(['[', ']'], '', $qualifiedName));
        $schema = count($parts) > 1 ? $parts[0] : 'dbo';
        $table = count($parts) > 1 ? $parts[1] : $parts[0];

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM sys.columns c
            INNER JOIN sys.tables t
                ON t.object_id = c.object_id
            INNER JOIN sys.schemas s
                ON s.schema_id = t.schema_id
            WHERE s.name = :schemaName
              AND t.name = :tableName
              AND c.name = :columnName
        ");
        $stmt->execute([
            ':schemaName' => $schema,
            ':tableName' => $table,
            ':columnName' => $columnName,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function procedureExists(string $qualifiedName): bool
    {
        $parts = explode('.', str_replace(['[', ']'], '', $qualifiedName));
        $schema = count($parts) > 1 ? $parts[0] : 'dbo';
        $procedure = count($parts) > 1 ? $parts[1] : $parts[0];

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM sys.procedures p
            INNER JOIN sys.schemas s
                ON s.schema_id = p.schema_id
            WHERE s.name = :schemaName
              AND p.name = :procedureName
        ");
        $stmt->execute([
            ':schemaName' => $schema,
            ':procedureName' => $procedure,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function nullIfZero(mixed $value): ?int
    {
        $number = (int)$value;
        return $number > 0 ? $number : null;
    }
}
