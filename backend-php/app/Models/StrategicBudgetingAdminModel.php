<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class StrategicBudgetingAdminModel
{
    private PDO $conn;
    private const SEGMENT_NOT_MAPPED_NOTE = '[NOT_MAPPED]';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function listOrgUnits(string $q = ''): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(ou.OrgUnitName LIKE :q OR ou.VoteCode LIKE :q OR ou.OrgUnitTypeCode LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "
            SELECT
                ou.OrgUnitID,
                ou.ParentOrgUnitID,
                parent.OrgUnitName AS ParentOrgUnitName,
                ou.OrgUnitTypeCode,
                ou.VoteCode,
                ou.OrgUnitName,
                ou.ActiveFlag
            FROM dbo.tblSbOrgUnit ou
            LEFT JOIN dbo.tblSbOrgUnit parent
                ON parent.OrgUnitID = ou.ParentOrgUnitID
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ou.OrgUnitName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOrgUnit(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbOrgUnit WHERE OrgUnitID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveOrgUnit(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $sql = "
                UPDATE dbo.tblSbOrgUnit
                SET ParentOrgUnitID = :ParentOrgUnitID,
                    OrgUnitTypeCode = :OrgUnitTypeCode,
                    VoteCode = :VoteCode,
                    OrgUnitName = :OrgUnitName,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE OrgUnitID = :OrgUnitID
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':ParentOrgUnitID' => $data['ParentOrgUnitID'],
                ':OrgUnitTypeCode' => $data['OrgUnitTypeCode'],
                ':VoteCode' => $data['VoteCode'],
                ':OrgUnitName' => $data['OrgUnitName'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':OrgUnitID' => $id,
            ]);
            return $id;
        }

        $sql = "
            INSERT INTO dbo.tblSbOrgUnit (
                ParentOrgUnitID,
                OrgUnitTypeCode,
                VoteCode,
                OrgUnitName,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ParentOrgUnitID,
                :OrgUnitTypeCode,
                :VoteCode,
                :OrgUnitName,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ParentOrgUnitID' => $data['ParentOrgUnitID'],
            ':OrgUnitTypeCode' => $data['OrgUnitTypeCode'],
            ':VoteCode' => $data['VoteCode'],
            ':OrgUnitName' => $data['OrgUnitName'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateOrgUnit(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbOrgUnit
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE OrgUnitID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listSectors(string $q = ''): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(SectorName LIKE :q OR SectorDescription LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = '
            SELECT SectorID, SectorName, SectorDescription, ActiveFlag
            FROM dbo.tblSbSector
        ';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY SectorName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSector(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbSector WHERE SectorID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSectorByName(string $sectorName): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbSector
            WHERE SectorName = :sectorName
            ORDER BY SectorID ASC
        ");
        $stmt->execute([':sectorName' => $sectorName]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSector(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbSector
                SET SectorName = :SectorName,
                    SectorDescription = :SectorDescription,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE SectorID = :SectorID
            ");
            $stmt->execute([
                ':SectorName' => $data['SectorName'],
                ':SectorDescription' => $data['SectorDescription'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':SectorID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbSector (
                SectorName,
                SectorDescription,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :SectorName,
                :SectorDescription,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':SectorName' => $data['SectorName'],
            ':SectorDescription' => $data['SectorDescription'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateSector(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbSector
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE SectorID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listFundingTypes(string $q = ''): array
    {
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            return [];
        }

        $supportsDefaultPhasing = $this->supportsFundingTypeDefaultPhasing();
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(FundingTypeCode LIKE :q OR FundingTypeName LIKE :q OR FundingTypeDescription LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $supportsPhasingProfiles = $supportsDefaultPhasing && $this->supportsPhasingProfileConfig();
        $sql = '
            SELECT
                ft.FundingTypeID,
                ft.FundingTypeCode,
                ft.FundingTypeName,
                ft.FundingTypeDescription,
                ' . ($supportsDefaultPhasing ? 'ft.DefaultPhasingProfileID' : 'CAST(NULL AS INT) AS DefaultPhasingProfileID') . ',
                ft.ActiveFlag' . ($supportsPhasingProfiles ? ',
                pp.ProfileName AS DefaultPhasingProfileName' : ',
                CAST(NULL AS NVARCHAR(150)) AS DefaultPhasingProfileName') . '
            FROM dbo.tblSbFundingType ft
            ' . ($supportsPhasingProfiles ? '
            LEFT JOIN dbo.tblSbPhasingProfile pp
                ON pp.PhasingProfileID = ft.DefaultPhasingProfileID' : '') . '
        ';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY FundingTypeName ASC, FundingTypeCode ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFundingType(int $id): ?array
    {
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            return null;
        }

        $supportsDefaultPhasing = $this->supportsFundingTypeDefaultPhasing();
        $supportsPhasingProfiles = $supportsDefaultPhasing && $this->supportsPhasingProfileConfig();
        $stmt = $this->conn->prepare('
            SELECT
                ft.*' . (!$supportsDefaultPhasing ? ',
                CAST(NULL AS INT) AS DefaultPhasingProfileID' : '') . ($supportsPhasingProfiles ? ',
                pp.ProfileName AS DefaultPhasingProfileName' : ',
                CAST(NULL AS NVARCHAR(150)) AS DefaultPhasingProfileName') . '
            FROM dbo.tblSbFundingType ft
            ' . ($supportsPhasingProfiles ? '
            LEFT JOIN dbo.tblSbPhasingProfile pp
                ON pp.PhasingProfileID = ft.DefaultPhasingProfileID' : '') . '
            WHERE ft.FundingTypeID = :id
        ');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFundingTypeByCode(string $fundingTypeCode): ?array
    {
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbFundingType
            WHERE FundingTypeCode = :code
            ORDER BY FundingTypeID ASC
        ");
        $stmt->execute([':code' => $fundingTypeCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFundingTypeBySourceCode(int $fiscalYearId, string $sourceSegmentCode): ?array
    {
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbFundingType
            WHERE SourceFiscalYearID = :fy
              AND SourceSegmentCode = :sourceSegmentCode
            ORDER BY FundingTypeID ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':sourceSegmentCode' => $sourceSegmentCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveFundingType(array $data, ?int $id = null): int
    {
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            throw new \RuntimeException('Funding type overlays require the standalone dimension migration.');
        }

        $supportsDefaultPhasing = $this->supportsFundingTypeDefaultPhasing();
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbFundingType
                SET FundingTypeCode = :FundingTypeCode,
                    FundingTypeName = :FundingTypeName,
                    FundingTypeDescription = :FundingTypeDescription," . ($supportsDefaultPhasing ? "
                    DefaultPhasingProfileID = :DefaultPhasingProfileID," : "") . "
                    SourceFiscalYearID = :SourceFiscalYearID,
                    SourceDataObjectCode = :SourceDataObjectCode,
                    SourceSegmentNo = :SourceSegmentNo,
                    SourceSegmentCode = :SourceSegmentCode,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE FundingTypeID = :FundingTypeID
            ");
            $stmt->execute([
                ':FundingTypeCode' => $data['FundingTypeCode'],
                ':FundingTypeName' => $data['FundingTypeName'],
                ':FundingTypeDescription' => $data['FundingTypeDescription'],
                ':SourceFiscalYearID' => $data['SourceFiscalYearID'] ?? null,
                ':SourceDataObjectCode' => $data['SourceDataObjectCode'] ?? null,
                ':SourceSegmentNo' => $data['SourceSegmentNo'] ?? null,
                ':SourceSegmentCode' => $data['SourceSegmentCode'] ?? null,
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':FundingTypeID' => $id,
            ] + ($supportsDefaultPhasing ? [':DefaultPhasingProfileID' => $data['DefaultPhasingProfileID'] ?? null] : []));
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbFundingType (
                FundingTypeCode,
                FundingTypeName,
                FundingTypeDescription," . ($supportsDefaultPhasing ? "
                DefaultPhasingProfileID," : "") . "
                SourceFiscalYearID,
                SourceDataObjectCode,
                SourceSegmentNo,
                SourceSegmentCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :FundingTypeCode,
                :FundingTypeName,
                :FundingTypeDescription," . ($supportsDefaultPhasing ? "
                :DefaultPhasingProfileID," : "") . "
                :SourceFiscalYearID,
                :SourceDataObjectCode,
                :SourceSegmentNo,
                :SourceSegmentCode,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':FundingTypeCode' => $data['FundingTypeCode'],
            ':FundingTypeName' => $data['FundingTypeName'],
            ':FundingTypeDescription' => $data['FundingTypeDescription'],
            ':SourceFiscalYearID' => $data['SourceFiscalYearID'] ?? null,
            ':SourceDataObjectCode' => $data['SourceDataObjectCode'] ?? null,
            ':SourceSegmentNo' => $data['SourceSegmentNo'] ?? null,
            ':SourceSegmentCode' => $data['SourceSegmentCode'] ?? null,
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ] + ($supportsDefaultPhasing ? [':DefaultPhasingProfileID' => $data['DefaultPhasingProfileID'] ?? null] : []));

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateFundingType(int $id, int $userId): void
    {
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFundingType
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE FundingTypeID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listFundingTypeOptions(): array
    {
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            return [];
        }

        $supportsDefaultPhasing = $this->supportsFundingTypeDefaultPhasing();
        $stmt = $this->conn->query("
            SELECT FundingTypeID, FundingTypeCode, FundingTypeName, " . ($supportsDefaultPhasing ? "DefaultPhasingProfileID" : "CAST(NULL AS INT) AS DefaultPhasingProfileID") . "
            FROM dbo.tblSbFundingType
            WHERE ActiveFlag = 1
            ORDER BY CASE WHEN COALESCE(FundingTypeCode, N'') = N'' THEN 1 ELSE 0 END,
                     FundingTypeCode ASC,
                     FundingTypeName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function supportsStrategicCeilings(): bool
    {
        return $this->tableExists('dbo.tblSbCeiling');
    }

    public function listSectorCeilings(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->supportsStrategicCeilings()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            WITH PlanAgg AS (
                SELECT
                    p.SectorID,
                    COALESCE(SUM(ab.Amount), 0) AS PlannedAmount
                FROM dbo.tblSbProgram p
                LEFT JOIN dbo.tblSbOutput o
                    ON o.ProgramID = p.ProgramID
                   AND o.ActiveFlag = 1
                LEFT JOIN dbo.tblSbActivity a
                    ON a.OutputID = o.OutputID
                   AND a.ActiveFlag = 1
                LEFT JOIN dbo.tblSbActivityBudget ab
                    ON ab.ActivityID = a.ActivityID
                   AND ab.FiscalYearID = :fyPlan
                   AND ab.VersionID = :verPlan
                   AND ab.ActiveFlag = 1
                WHERE p.ActiveFlag = 1
                GROUP BY p.SectorID
            )
            SELECT
                c.CeilingID,
                c.FiscalYearID,
                c.VersionID,
                c.ScopeTypeCode,
                c.SectorID,
                c.CeilingAmount,
                c.Notes,
                c.ActiveFlag,
                s.SectorName,
                s.SourceSegmentCode,
                COALESCE(p.PlannedAmount, 0) AS PlannedAmount,
                c.CeilingAmount - COALESCE(p.PlannedAmount, 0) AS VarianceAmount,
                CASE WHEN COALESCE(p.PlannedAmount, 0) > c.CeilingAmount THEN 1 ELSE 0 END AS OverCeilingFlag
            FROM dbo.tblSbCeiling c
            INNER JOIN dbo.tblSbSector s
                ON s.SectorID = c.SectorID
            LEFT JOIN PlanAgg p
                ON p.SectorID = c.SectorID
            WHERE c.FiscalYearID = :fy
              AND c.VersionID = :ver
              AND c.ScopeTypeCode = N'SECTOR'
              AND c.ActiveFlag = 1
            ORDER BY s.SectorName ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':fyPlan' => $fiscalYearId,
            ':verPlan' => $versionId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSectorCeiling(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsStrategicCeilings()) {
            return null;
        }
        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbCeiling
            WHERE CeilingID = :id
              AND ScopeTypeCode = N'SECTOR'
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSectorCeiling(int $fiscalYearId, int $versionId, array $data, ?int $id = null): int
    {
        if ($fiscalYearId <= 0 || $versionId <= 0) {
            throw new \InvalidArgumentException('Fiscal context is required.');
        }
        if (!$this->supportsStrategicCeilings()) {
            throw new \RuntimeException('Strategic ceiling table is not installed.');
        }

        $params = [
            ':FiscalYearID' => $fiscalYearId,
            ':VersionID' => $versionId,
            ':SectorID' => (int) ($data['SectorID'] ?? 0),
            ':CeilingAmount' => (float) ($data['CeilingAmount'] ?? 0),
            ':Notes' => $data['Notes'] ?? null,
            ':ActiveFlag' => (int) ($data['ActiveFlag'] ?? 1),
            ':UserID' => (int) ($data['UserID'] ?? 1),
        ];

        if ($params[':SectorID'] <= 0) {
            throw new \InvalidArgumentException('Sector is required.');
        }
        if ($params[':CeilingAmount'] < 0) {
            throw new \InvalidArgumentException('Ceiling amount cannot be negative.');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbCeiling
                SET SectorID = :SectorID,
                    CeilingAmount = :CeilingAmount,
                    Notes = :Notes,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UserID,
                    UpdatedDate = SYSDATETIME()
                WHERE CeilingID = :CeilingID
                  AND ScopeTypeCode = N'SECTOR'
            ");
            $stmt->execute([
                ':SectorID' => $params[':SectorID'],
                ':CeilingAmount' => $params[':CeilingAmount'],
                ':Notes' => $params[':Notes'],
                ':ActiveFlag' => $params[':ActiveFlag'],
                ':UserID' => $params[':UserID'],
                ':CeilingID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbCeiling (
                VersionID,
                FiscalYearID,
                ScopeTypeCode,
                SectorID,
                CeilingAmount,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :VersionID,
                :FiscalYearID,
                N'SECTOR',
                :SectorID,
                :CeilingAmount,
                :Notes,
                :ActiveFlag,
                :UserID,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':VersionID' => $params[':VersionID'],
            ':FiscalYearID' => $params[':FiscalYearID'],
            ':SectorID' => $params[':SectorID'],
            ':CeilingAmount' => $params[':CeilingAmount'],
            ':Notes' => $params[':Notes'],
            ':ActiveFlag' => $params[':ActiveFlag'],
            ':UserID' => $params[':UserID'],
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function deactivateSectorCeiling(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->supportsStrategicCeilings()) {
            return;
        }
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbCeiling
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE CeilingID = :id
              AND ScopeTypeCode = N'SECTOR'
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function getSectorCeilingSummary(int $fiscalYearId, int $versionId): array
    {
        $rows = $this->listSectorCeilings($fiscalYearId, $versionId);
        $resourceEnvelope = $this->getResourceEnvelopeSummary($fiscalYearId, $versionId);
        $allocated = 0.0;
        $planned = 0.0;
        $overrunCount = 0;
        foreach ($rows as $row) {
            $allocated += (float) ($row['CeilingAmount'] ?? 0);
            $planned += (float) ($row['PlannedAmount'] ?? 0);
            if ((int) ($row['OverCeilingFlag'] ?? 0) === 1) {
                $overrunCount++;
            }
        }

        $envelopeAmount = (float) ($resourceEnvelope['CurrentYearAmount'] ?? 0);
        return [
            'SectorCount' => count($rows),
            'EnvelopeAmount' => $envelopeAmount,
            'AllocatedAmount' => $allocated,
            'RemainingAmount' => $envelopeAmount - $allocated,
            'PlannedAmount' => $planned,
            'OverrunCount' => $overrunCount,
        ];
    }

    public function copySectorCeilingsFromPlan(int $fiscalYearId, int $versionId, int $userId): array
    {
        $matrix = $this->getSectorCeilingAllocationMatrix($fiscalYearId, $versionId);
        $created = 0;
        $updated = 0;

        foreach ($matrix as $row) {
            $sectorId = (int) ($row['SectorID'] ?? 0);
            $plannedAmount = (float) ($row['PlannedAmount'] ?? 0);
            if ($sectorId <= 0) {
                continue;
            }

            $payload = [
                'SectorID' => $sectorId,
                'CeilingAmount' => $plannedAmount,
                'Notes' => $plannedAmount > 0 ? 'Copied from current strategic plan total.' : 'Initialized from current strategic plan total.',
                'ActiveFlag' => 1,
                'UserID' => $userId,
            ];

            $existingId = (int) ($row['CeilingID'] ?? 0);
            $this->saveSectorCeiling($fiscalYearId, $versionId, $payload, $existingId > 0 ? $existingId : null);
            if ($existingId > 0) {
                $updated++;
            } else {
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'sector_count' => count($matrix),
        ];
    }

    public function allocateRemainingSectorCeilingBalance(int $fiscalYearId, int $versionId, string $method, int $userId): array
    {
        $method = strtoupper(trim($method));
        $summary = $this->getSectorCeilingSummary($fiscalYearId, $versionId);
        $remaining = (float) ($summary['RemainingAmount'] ?? 0);
        if ($remaining <= 0) {
            throw new \RuntimeException('There is no positive remaining balance to allocate.');
        }

        $matrix = $this->getSectorCeilingAllocationMatrix($fiscalYearId, $versionId);
        if ($matrix === []) {
            throw new \RuntimeException('No sectors are available for allocation.');
        }

        $weights = [];
        foreach ($matrix as $index => $row) {
            $weights[$index] = match ($method) {
                'PLAN_SHARE' => max(0.0, (float) ($row['PlannedAmount'] ?? 0)),
                'CEILING_SHARE' => max(0.0, (float) ($row['CeilingAmount'] ?? 0)),
                default => 1.0,
            };
        }

        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            $weights = array_fill(0, count($matrix), 1.0);
            $weightTotal = (float) count($matrix);
        }

        $allocated = 0.0;
        $updated = 0;
        foreach ($matrix as $index => $row) {
            $share = $remaining * ($weights[$index] / $weightTotal);
            $newAmount = round((float) ($row['CeilingAmount'] ?? 0) + $share, 2);
            $payload = [
                'SectorID' => (int) ($row['SectorID'] ?? 0),
                'CeilingAmount' => $newAmount,
                'Notes' => $this->mergeCeilingHelperNote((string) ($row['Notes'] ?? ''), 'Adjusted by remaining balance helper (' . strtolower($method) . ').'),
                'ActiveFlag' => 1,
                'UserID' => $userId,
            ];
            $existingId = (int) ($row['CeilingID'] ?? 0);
            $this->saveSectorCeiling($fiscalYearId, $versionId, $payload, $existingId > 0 ? $existingId : null);
            $allocated += $share;
            $updated++;
        }

        return [
            'allocated' => round($allocated, 2),
            'updated' => $updated,
            'method' => $method,
        ];
    }

    private function getSectorCeilingAllocationMatrix(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->supportsStrategicCeilings()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            WITH PlanAgg AS (
                SELECT
                    p.SectorID,
                    COALESCE(SUM(ab.Amount), 0) AS PlannedAmount
                FROM dbo.tblSbProgram p
                LEFT JOIN dbo.tblSbOutput o
                    ON o.ProgramID = p.ProgramID
                   AND o.ActiveFlag = 1
                LEFT JOIN dbo.tblSbActivity a
                    ON a.OutputID = o.OutputID
                   AND a.ActiveFlag = 1
                LEFT JOIN dbo.tblSbActivityBudget ab
                    ON ab.ActivityID = a.ActivityID
                   AND ab.FiscalYearID = :fyPlan
                   AND ab.VersionID = :verPlan
                   AND ab.ActiveFlag = 1
                WHERE p.ActiveFlag = 1
                GROUP BY p.SectorID
            )
            SELECT
                s.SectorID,
                s.SectorName,
                s.SourceSegmentCode,
                c.CeilingID,
                COALESCE(c.CeilingAmount, 0) AS CeilingAmount,
                c.Notes,
                COALESCE(p.PlannedAmount, 0) AS PlannedAmount
            FROM dbo.tblSbSector s
            LEFT JOIN dbo.tblSbCeiling c
                ON c.SectorID = s.SectorID
               AND c.FiscalYearID = :fyCeiling
               AND c.VersionID = :verCeiling
               AND c.ScopeTypeCode = N'SECTOR'
               AND c.ActiveFlag = 1
            LEFT JOIN PlanAgg p
                ON p.SectorID = s.SectorID
            WHERE s.ActiveFlag = 1
            ORDER BY s.SectorName ASC
        ");
        $stmt->execute([
            ':fyPlan' => $fiscalYearId,
            ':verPlan' => $versionId,
            ':fyCeiling' => $fiscalYearId,
            ':verCeiling' => $versionId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function mergeCeilingHelperNote(string $existingNote, string $helperNote): string
    {
        $existingNote = trim($existingNote);
        if ($existingNote === '') {
            return $helperNote;
        }
        if (str_contains($existingNote, $helperNote)) {
            return $existingNote;
        }
        return $existingNote . ' ' . $helperNote;
    }

    public function supportsFundingTypeDefaultPhasing(): bool
    {
        return $this->tableColumnExists('dbo.tblSbFundingType', 'DefaultPhasingProfileID');
    }

    public function listPrograms(string $q = '', ?int $sectorId = null, ?int $orgUnitId = null): array
    {
        $supportsLinks = $this->supportsProgramOrgLinks();
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(p.ProgramName LIKE :q OR p.ProgramCode LIKE :q OR p.ProgramManagerName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($sectorId !== null && $sectorId > 0) {
            $where[] = 'p.SectorID = :sectorId';
            $params[':sectorId'] = $sectorId;
        }
        if ($orgUnitId !== null && $orgUnitId > 0) {
            $where[] = 'p.OrgUnitID = :orgUnitId';
            $params[':orgUnitId'] = $orgUnitId;
        }

        $sql = "
            SELECT
                p.ProgramID,
                p.OrgUnitID,
                p.ProgramCode,
                p.ProgramName,
                p.ProgramDescription,
                p.ProgramManagerName,
                p.ActiveFlag,
                s.SectorName,
                COALESCE(doc.DataObjectName, o.OrgUnitName) AS OrgUnitName,
                o.SourceDataObjectCode AS OrgUnitDataObjectCode," . ($supportsLinks ? "
                ISNULL(linkStats.LinkedOrgCount, 0) AS LinkedOrgCount" : "
                CAST(0 AS INT) AS LinkedOrgCount") . "
            FROM dbo.tblSbProgram p
            INNER JOIN dbo.tblSbSector s
                ON s.SectorID = p.SectorID
            INNER JOIN dbo.tblSbOrgUnit o
                ON o.OrgUnitID = p.OrgUnitID
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = o.SourceFiscalYearID
               AND doc.DataObjectCode = o.SourceDataObjectCode
            " . ($supportsLinks ? "
            LEFT JOIN (
                SELECT ProgramID, COUNT(*) AS LinkedOrgCount
                FROM dbo.tblSbProgramOrgLink
                WHERE ActiveFlag = 1
                GROUP BY ProgramID
            ) linkStats
                ON linkStats.ProgramID = p.ProgramID" : "") . "
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.ProgramName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProgram(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT
                p.*,
                o.SourceDataObjectCode AS OrgUnitDataObjectCode,
                COALESCE(doc.DataObjectName, o.OrgUnitName) AS OrgUnitName" . ($this->supportsProgramOrgLinks() ? ",
                ISNULL(linkStats.LinkedOrgCount, 0) AS LinkedOrgCount" : ",
                CAST(0 AS INT) AS LinkedOrgCount") . "
            FROM dbo.tblSbProgram p
            INNER JOIN dbo.tblSbOrgUnit o
                ON o.OrgUnitID = p.OrgUnitID
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = o.SourceFiscalYearID
               AND doc.DataObjectCode = o.SourceDataObjectCode
            " . ($this->supportsProgramOrgLinks() ? "
            LEFT JOIN (
                SELECT ProgramID, COUNT(*) AS LinkedOrgCount
                FROM dbo.tblSbProgramOrgLink
                WHERE ActiveFlag = 1
                GROUP BY ProgramID
            ) linkStats
                ON linkStats.ProgramID = p.ProgramID" : "") . "
            WHERE p.ProgramID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveProgram(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbProgram
                SET OrgUnitID = :OrgUnitID,
                    SectorID = :SectorID,
                    ProgramCode = :ProgramCode,
                    ProgramName = :ProgramName,
                    ProgramDescription = :ProgramDescription,
                    ProgramManagerName = :ProgramManagerName,
                    SourceFiscalYearID = :SourceFiscalYearID,
                    SourceDataObjectCode = :SourceDataObjectCode,
                    SourceSegmentNo = :SourceSegmentNo,
                    SourceSegmentCode = :SourceSegmentCode,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ProgramID = :ProgramID
            ");
            $stmt->execute([
                ':OrgUnitID' => $data['OrgUnitID'],
                ':SectorID' => $data['SectorID'],
                ':ProgramCode' => $data['ProgramCode'],
                ':ProgramName' => $data['ProgramName'],
                ':ProgramDescription' => $data['ProgramDescription'],
                ':ProgramManagerName' => $data['ProgramManagerName'],
                ':SourceFiscalYearID' => $data['SourceFiscalYearID'] ?? null,
                ':SourceDataObjectCode' => $data['SourceDataObjectCode'] ?? null,
                ':SourceSegmentNo' => $data['SourceSegmentNo'] ?? null,
                ':SourceSegmentCode' => $data['SourceSegmentCode'] ?? null,
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':ProgramID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbProgram (
                OrgUnitID,
                SectorID,
                ProgramCode,
                ProgramName,
                ProgramDescription,
                ProgramManagerName,
                SourceFiscalYearID,
                SourceDataObjectCode,
                SourceSegmentNo,
                SourceSegmentCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :OrgUnitID,
                :SectorID,
                :ProgramCode,
                :ProgramName,
                :ProgramDescription,
                :ProgramManagerName,
                :SourceFiscalYearID,
                :SourceDataObjectCode,
                :SourceSegmentNo,
                :SourceSegmentCode,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':OrgUnitID' => $data['OrgUnitID'],
            ':SectorID' => $data['SectorID'],
            ':ProgramCode' => $data['ProgramCode'],
            ':ProgramName' => $data['ProgramName'],
            ':ProgramDescription' => $data['ProgramDescription'],
            ':ProgramManagerName' => $data['ProgramManagerName'],
            ':SourceFiscalYearID' => $data['SourceFiscalYearID'] ?? null,
            ':SourceDataObjectCode' => $data['SourceDataObjectCode'] ?? null,
            ':SourceSegmentNo' => $data['SourceSegmentNo'] ?? null,
            ':SourceSegmentCode' => $data['SourceSegmentCode'] ?? null,
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateProgram(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbProgram
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ProgramID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listSubPrograms(string $q = '', ?int $programId = null): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(sp.SubProgramName LIKE :q OR sp.SubProgramCode LIKE :q OR p.ProgramName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($programId !== null && $programId > 0) {
            $where[] = 'sp.ProgramID = :programId';
            $params[':programId'] = $programId;
        }

        $sql = "
            SELECT
                sp.SubProgramID,
                sp.ProgramID,
                sp.SubProgramCode,
                sp.SubProgramName,
                sp.SubProgramDescription,
                sp.ActiveFlag,
                p.ProgramName
            FROM dbo.tblSbSubProgram sp
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = sp.ProgramID
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.ProgramName ASC, sp.SubProgramName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSubProgram(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT
                sp.*,
                p.ProgramName,
                o.SourceDataObjectCode AS OrgUnitDataObjectCode,
                COALESCE(doc.DataObjectName, o.OrgUnitName) AS OrgUnitName
            FROM dbo.tblSbSubProgram sp
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = sp.ProgramID
            INNER JOIN dbo.tblSbOrgUnit o
                ON o.OrgUnitID = p.OrgUnitID
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = o.SourceFiscalYearID
               AND doc.DataObjectCode = o.SourceDataObjectCode
            WHERE sp.SubProgramID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSubProgram(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbSubProgram
                SET ProgramID = :ProgramID,
                    SubProgramCode = :SubProgramCode,
                    SubProgramName = :SubProgramName,
                    SubProgramDescription = :SubProgramDescription,
                    SourceFiscalYearID = :SourceFiscalYearID,
                    SourceDataObjectCode = :SourceDataObjectCode,
                    SourceSegmentNo = :SourceSegmentNo,
                    SourceSegmentCode = :SourceSegmentCode,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE SubProgramID = :SubProgramID
            ");
            $stmt->execute([
                ':ProgramID' => $data['ProgramID'],
                ':SubProgramCode' => $data['SubProgramCode'],
                ':SubProgramName' => $data['SubProgramName'],
                ':SubProgramDescription' => $data['SubProgramDescription'],
                ':SourceFiscalYearID' => $data['SourceFiscalYearID'] ?? null,
                ':SourceDataObjectCode' => $data['SourceDataObjectCode'] ?? null,
                ':SourceSegmentNo' => $data['SourceSegmentNo'] ?? null,
                ':SourceSegmentCode' => $data['SourceSegmentCode'] ?? null,
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':SubProgramID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbSubProgram (
                ProgramID,
                SubProgramCode,
                SubProgramName,
                SubProgramDescription,
                SourceFiscalYearID,
                SourceDataObjectCode,
                SourceSegmentNo,
                SourceSegmentCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ProgramID,
                :SubProgramCode,
                :SubProgramName,
                :SubProgramDescription,
                :SourceFiscalYearID,
                :SourceDataObjectCode,
                :SourceSegmentNo,
                :SourceSegmentCode,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':ProgramID' => $data['ProgramID'],
            ':SubProgramCode' => $data['SubProgramCode'],
            ':SubProgramName' => $data['SubProgramName'],
            ':SubProgramDescription' => $data['SubProgramDescription'],
            ':SourceFiscalYearID' => $data['SourceFiscalYearID'] ?? null,
            ':SourceDataObjectCode' => $data['SourceDataObjectCode'] ?? null,
            ':SourceSegmentNo' => $data['SourceSegmentNo'] ?? null,
            ':SourceSegmentCode' => $data['SourceSegmentCode'] ?? null,
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateSubProgram(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbSubProgram
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE SubProgramID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listEconomicItems(string $q = ''): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(ei.EconomicCode LIKE :q OR ei.EconomicName LIKE :q OR parent.EconomicName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "
            SELECT
                ei.EconomicItemID,
                ei.ParentEconomicItemID,
                parent.EconomicName AS ParentEconomicName,
                ei.EconomicCode,
                ei.EconomicName,
                ei.EconomicLevel,
                ei.ActiveFlag
            FROM dbo.tblSbEconomicItem ei
            LEFT JOIN dbo.tblSbEconomicItem parent
                ON parent.EconomicItemID = ei.ParentEconomicItemID
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY ei.EconomicCode ASC, ei.EconomicName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getEconomicItem(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbEconomicItem WHERE EconomicItemID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getEconomicItemByCode(string $economicCode): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbEconomicItem WHERE EconomicCode = :economicCode');
        $stmt->execute([':economicCode' => $economicCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveEconomicItem(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbEconomicItem
                SET ParentEconomicItemID = :ParentEconomicItemID,
                    EconomicCode = :EconomicCode,
                    EconomicName = :EconomicName,
                    EconomicLevel = :EconomicLevel,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE EconomicItemID = :EconomicItemID
            ");
            $stmt->execute([
                ':ParentEconomicItemID' => $data['ParentEconomicItemID'],
                ':EconomicCode' => $data['EconomicCode'],
                ':EconomicName' => $data['EconomicName'],
                ':EconomicLevel' => $data['EconomicLevel'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':EconomicItemID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbEconomicItem (
                ParentEconomicItemID,
                EconomicCode,
                EconomicName,
                EconomicLevel,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ParentEconomicItemID,
                :EconomicCode,
                :EconomicName,
                :EconomicLevel,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':ParentEconomicItemID' => $data['ParentEconomicItemID'],
            ':EconomicCode' => $data['EconomicCode'],
            ':EconomicName' => $data['EconomicName'],
            ':EconomicLevel' => $data['EconomicLevel'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateEconomicItem(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbEconomicItem
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE EconomicItemID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listFundingSources(string $q = ''): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(FundingSourceName LIKE :q OR FundingTypeCode LIKE :q OR DonorName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "
            SELECT
                FundingSourceID,
                FundingTypeCode,
                FundingSourceName,
                DonorName,
                ConditionsText,
                ActiveFlag
            FROM dbo.tblSbFundingSource
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY FundingSourceName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFundingSource(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbFundingSource WHERE FundingSourceID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFundingSourceBySourceCode(int $fiscalYearId, string $sourceSegmentCode): ?array
    {
        if (!$this->hasFundingSourceSourceTrackingColumns()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbFundingSource
            WHERE SourceFiscalYearID = :fy
              AND SourceSegmentCode = :sourceSegmentCode
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':sourceSegmentCode' => $sourceSegmentCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveFundingSource(array $data, ?int $id = null): int
    {
        if (!$this->hasFundingSourceSourceTrackingColumns()) {
            throw new \RuntimeException('Funding source overlays require the source-tracking migration on tblSbFundingSource.');
        }

        $fundingTypeCode = strtoupper(trim((string) ($data['FundingTypeCode'] ?? '')));
        $fundingTypeId = null;
        if ($fundingTypeCode !== '') {
            $fundingType = $this->getFundingTypeByCode($fundingTypeCode);
            if ($fundingType === null) {
                throw new \RuntimeException('Selected funding type was not found.');
            }
            $fundingTypeId = (int) ($fundingType['FundingTypeID'] ?? 0);
            if ($fundingTypeId <= 0) {
                throw new \RuntimeException('Selected funding type is not configured correctly.');
            }
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbFundingSource
                SET FundingTypeID = :FundingTypeID,
                    FundingTypeCode = :FundingTypeCode,
                    FundingSourceName = :FundingSourceName,
                    DonorName = :DonorName,
                    ConditionsText = :ConditionsText,
                    SourceFiscalYearID = :SourceFiscalYearID,
                    SourceSegmentCode = :SourceSegmentCode,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE FundingSourceID = :FundingSourceID
            ");
            $stmt->execute([
                ':FundingTypeID' => $fundingTypeId,
                ':FundingTypeCode' => $fundingTypeCode,
                ':FundingSourceName' => $data['FundingSourceName'],
                ':DonorName' => $data['DonorName'],
                ':ConditionsText' => $data['ConditionsText'],
                ':SourceFiscalYearID' => $data['SourceFiscalYearID'] ?? null,
                ':SourceSegmentCode' => $data['SourceSegmentCode'] ?? null,
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':FundingSourceID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbFundingSource (
                FundingTypeID,
                FundingTypeCode,
                FundingSourceName,
                DonorName,
                ConditionsText,
                SourceFiscalYearID,
                SourceSegmentCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :FundingTypeID,
                :FundingTypeCode,
                :FundingSourceName,
                :DonorName,
                :ConditionsText,
                :SourceFiscalYearID,
                :SourceSegmentCode,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':FundingTypeID' => $fundingTypeId,
            ':FundingTypeCode' => $fundingTypeCode,
            ':FundingSourceName' => $data['FundingSourceName'],
            ':DonorName' => $data['DonorName'],
            ':ConditionsText' => $data['ConditionsText'],
            ':SourceFiscalYearID' => $data['SourceFiscalYearID'] ?? null,
            ':SourceSegmentCode' => $data['SourceSegmentCode'] ?? null,
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateFundingSource(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFundingSource
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE FundingSourceID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listObjectives(string $q = '', ?int $programId = null): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(o.ObjectiveText LIKE :q OR p.ProgramName LIKE :q OR sp.SubProgramName LIKE :q OR ISNULL(g.GoalNames, \'\') LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($programId !== null && $programId > 0) {
            $where[] = 'o.ProgramID = :programId';
            $params[':programId'] = $programId;
        }

        $sql = "
            SELECT
                o.ObjectiveID,
                o.ProgramID,
                o.SubProgramID,
                o.ObjectiveText,
                o.PolicyLink,
                o.PriorityRank,
                o.ActiveFlag,
                p.ProgramName,
                sp.SubProgramName,
                ISNULL(g.GoalNames, '') AS GoalNames
            FROM dbo.tblSbObjective o
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = o.ProgramID
            LEFT JOIN dbo.tblSbSubProgram sp
                ON sp.SubProgramID = o.SubProgramID
            LEFT JOIN (
                SELECT
                    og.ObjectiveID,
                    STRING_AGG(gl.GoalCode + N' - ' + gl.GoalName, N'; ') WITHIN GROUP (ORDER BY gl.GoalCode) AS GoalNames
                FROM dbo.tblSbObjectiveGoal og
                INNER JOIN dbo.tblSbGoal gl
                    ON gl.GoalID = og.GoalID
                GROUP BY og.ObjectiveID
            ) g
                ON g.ObjectiveID = o.ObjectiveID
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.ProgramName ASC, o.PriorityRank ASC, o.ObjectiveID ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listObjectiveOptions(): array
    {
        $stmt = $this->conn->query("
            SELECT ObjectiveID, ObjectiveText, ProgramID, SubProgramID
            FROM dbo.tblSbObjective
            WHERE ActiveFlag = 1
            ORDER BY ObjectiveText ASC, ObjectiveID ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getObjective(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbObjective WHERE ObjectiveID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listGoals(string $q = ''): array
    {
        if (!$this->tableExists('dbo.tblSbGoal')) {
            return [];
        }

        $params = [];
        $sql = "
            SELECT
                g.GoalID,
                g.GoalCode,
                g.GoalName,
                g.GoalTypeCode,
                g.StrategicPillarID,
                g.ActiveFlag,
                p.StrategicPillarName
            FROM dbo.tblSbGoal g
            LEFT JOIN dbo.tblSbStrategicPillar p
                ON p.StrategicPillarID = g.StrategicPillarID
            WHERE g.ActiveFlag = 1
        ";

        if ($q !== '') {
            $sql .= " AND (g.GoalCode LIKE :q OR g.GoalName LIKE :q OR g.GoalTypeCode LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY g.GoalTypeCode ASC, g.GoalCode ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listStrategicPillars(string $q = ''): array
    {
        if (!$this->tableExists('dbo.tblSbStrategicPillar')) {
            return [];
        }

        $params = [];
        $sql = "
            SELECT StrategicPillarID, StrategicPillarCode, StrategicPillarName, FrameworkCode, ActiveFlag
            FROM dbo.tblSbStrategicPillar
            WHERE ActiveFlag = 1
        ";

        if ($q !== '') {
            $sql .= " AND (StrategicPillarCode LIKE :q OR StrategicPillarName LIKE :q OR FrameworkCode LIKE :q)";
            $params[':q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY FrameworkCode ASC, StrategicPillarCode ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStrategicPillar(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbStrategicPillar')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbStrategicPillar
            WHERE StrategicPillarID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveStrategicPillar(array $data, ?int $id = null): int
    {
        if (!$this->tableExists('dbo.tblSbStrategicPillar')) {
            throw new \RuntimeException('Strategic Pillars are not available until the Strategic Pillar migration is run.');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbStrategicPillar
                SET StrategicPillarCode = :StrategicPillarCode,
                    StrategicPillarName = :StrategicPillarName,
                    FrameworkCode = :FrameworkCode,
                    StrategicPillarDescription = :StrategicPillarDescription,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE StrategicPillarID = :StrategicPillarID
            ");
            $stmt->execute([
                ':StrategicPillarCode' => $data['StrategicPillarCode'],
                ':StrategicPillarName' => $data['StrategicPillarName'],
                ':FrameworkCode' => $data['FrameworkCode'],
                ':StrategicPillarDescription' => $data['StrategicPillarDescription'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':StrategicPillarID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbStrategicPillar (
                StrategicPillarCode,
                StrategicPillarName,
                FrameworkCode,
                StrategicPillarDescription,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :StrategicPillarCode,
                :StrategicPillarName,
                :FrameworkCode,
                :StrategicPillarDescription,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':StrategicPillarCode' => $data['StrategicPillarCode'],
            ':StrategicPillarName' => $data['StrategicPillarName'],
            ':FrameworkCode' => $data['FrameworkCode'],
            ':StrategicPillarDescription' => $data['StrategicPillarDescription'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateStrategicPillar(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbStrategicPillar')) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbStrategicPillar
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE StrategicPillarID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function getGoal(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbGoal')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbGoal
            WHERE GoalID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveGoal(array $data, ?int $id = null): int
    {
        if (!$this->tableExists('dbo.tblSbGoal')) {
            throw new \RuntimeException('Goals are not available until the Goal migration is run.');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbGoal
                SET GoalCode = :GoalCode,
                    GoalName = :GoalName,
                    GoalTypeCode = :GoalTypeCode,
                    StrategicPillarID = :StrategicPillarID,
                    GoalDescription = :GoalDescription,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE GoalID = :GoalID
            ");
            $stmt->execute([
                ':GoalCode' => $data['GoalCode'],
                ':GoalName' => $data['GoalName'],
                ':GoalTypeCode' => $data['GoalTypeCode'],
                ':StrategicPillarID' => $data['StrategicPillarID'],
                ':GoalDescription' => $data['GoalDescription'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':GoalID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbGoal (
                GoalCode,
                GoalName,
                GoalTypeCode,
                StrategicPillarID,
                GoalDescription,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :GoalCode,
                :GoalName,
                :GoalTypeCode,
                :StrategicPillarID,
                :GoalDescription,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':GoalCode' => $data['GoalCode'],
            ':GoalName' => $data['GoalName'],
            ':GoalTypeCode' => $data['GoalTypeCode'],
            ':StrategicPillarID' => $data['StrategicPillarID'],
            ':GoalDescription' => $data['GoalDescription'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateGoal(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbGoal')) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbGoal
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE GoalID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function supportsStrategicDimensionAttributes(): bool
    {
        return $this->tableExists('dbo.tblSbDimensionAttribute')
            && $this->tableExists('dbo.tblSbDimensionAttributeOption')
            && $this->tableExists('dbo.tblSbDimensionAttributeValue');
    }

    public function getStrategicAttributeDimensionDefinitions(): array
    {
        $definitions = [];
        foreach ($this->getStrategicSegmentDefinitions() as $definition) {
            $code = strtoupper(trim((string) ($definition['Code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $definitions[] = [
                'Code' => $code,
                'Label' => (string) ($definition['Label'] ?? $code),
            ];
        }

        $definitions[] = ['Code' => 'GOAL', 'Label' => 'Goal'];
        $definitions[] = ['Code' => 'STRATEGIC_PILLAR', 'Label' => 'Strategic Pillar'];

        return $definitions;
    }

    public function listDimensionAttributes(?string $dimensionCode = null, string $q = ''): array
    {
        if (!$this->tableExists('dbo.tblSbDimensionAttribute')) {
            return [];
        }

        $params = [];
        $where = [];

        if ($dimensionCode !== null && trim($dimensionCode) !== '') {
            $where[] = 'a.StrategicDimensionCode = :dimensionCode';
            $params[':dimensionCode'] = strtoupper(trim($dimensionCode));
        }
        if ($q !== '') {
            $where[] = '(a.AttributeCode LIKE :q OR a.AttributeName LIKE :q OR a.HelpText LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "
            SELECT
                a.AttributeID,
                a.StrategicDimensionCode,
                a.AttributeCode,
                a.AttributeName,
                a.DataTypeCode,
                a.HelpText,
                a.IsRequired,
                a.DisplayOrder,
                a.ActiveFlag,
                COUNT(o.AttributeOptionID) AS OptionCount
            FROM dbo.tblSbDimensionAttribute a
            LEFT JOIN dbo.tblSbDimensionAttributeOption o
              ON o.AttributeID = a.AttributeID
             AND o.ActiveFlag = 1
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            GROUP BY
                a.AttributeID,
                a.StrategicDimensionCode,
                a.AttributeCode,
                a.AttributeName,
                a.DataTypeCode,
                a.HelpText,
                a.IsRequired,
                a.DisplayOrder,
                a.ActiveFlag
            ORDER BY
                a.StrategicDimensionCode ASC,
                a.DisplayOrder ASC,
                a.AttributeName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDimensionAttribute(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbDimensionAttribute')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbDimensionAttribute
            WHERE AttributeID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveDimensionAttribute(array $data, ?int $id = null): int
    {
        if (!$this->tableExists('dbo.tblSbDimensionAttribute')) {
            throw new \RuntimeException('Strategic dimension custom attributes are not available until the dimension attribute migration is run.');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbDimensionAttribute
                SET StrategicDimensionCode = :StrategicDimensionCode,
                    AttributeCode = :AttributeCode,
                    AttributeName = :AttributeName,
                    DataTypeCode = :DataTypeCode,
                    HelpText = :HelpText,
                    IsRequired = :IsRequired,
                    DisplayOrder = :DisplayOrder,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE AttributeID = :AttributeID
            ");
            $stmt->execute([
                ':StrategicDimensionCode' => $data['StrategicDimensionCode'],
                ':AttributeCode' => $data['AttributeCode'],
                ':AttributeName' => $data['AttributeName'],
                ':DataTypeCode' => $data['DataTypeCode'],
                ':HelpText' => $data['HelpText'],
                ':IsRequired' => $data['IsRequired'],
                ':DisplayOrder' => $data['DisplayOrder'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':AttributeID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbDimensionAttribute (
                StrategicDimensionCode,
                AttributeCode,
                AttributeName,
                DataTypeCode,
                HelpText,
                IsRequired,
                DisplayOrder,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :StrategicDimensionCode,
                :AttributeCode,
                :AttributeName,
                :DataTypeCode,
                :HelpText,
                :IsRequired,
                :DisplayOrder,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':StrategicDimensionCode' => $data['StrategicDimensionCode'],
            ':AttributeCode' => $data['AttributeCode'],
            ':AttributeName' => $data['AttributeName'],
            ':DataTypeCode' => $data['DataTypeCode'],
            ':HelpText' => $data['HelpText'],
            ':IsRequired' => $data['IsRequired'],
            ':DisplayOrder' => $data['DisplayOrder'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateDimensionAttribute(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbDimensionAttribute')) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbDimensionAttribute
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE AttributeID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listDimensionAttributeOptions(int $attributeId): array
    {
        if ($attributeId <= 0 || !$this->tableExists('dbo.tblSbDimensionAttributeOption')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                AttributeOptionID,
                AttributeID,
                OptionCode,
                OptionLabel,
                DisplayOrder,
                ActiveFlag
            FROM dbo.tblSbDimensionAttributeOption
            WHERE AttributeID = :attributeId
            ORDER BY DisplayOrder ASC, OptionLabel ASC
        ");
        $stmt->execute([':attributeId' => $attributeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDimensionAttributeOption(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbDimensionAttributeOption')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbDimensionAttributeOption
            WHERE AttributeOptionID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveDimensionAttributeOption(array $data, ?int $id = null): int
    {
        if (!$this->tableExists('dbo.tblSbDimensionAttributeOption')) {
            throw new \RuntimeException('Strategic dimension attribute options are not available until the dimension attribute migration is run.');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbDimensionAttributeOption
                SET OptionCode = :OptionCode,
                    OptionLabel = :OptionLabel,
                    DisplayOrder = :DisplayOrder,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE AttributeOptionID = :AttributeOptionID
            ");
            $stmt->execute([
                ':OptionCode' => $data['OptionCode'],
                ':OptionLabel' => $data['OptionLabel'],
                ':DisplayOrder' => $data['DisplayOrder'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':AttributeOptionID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbDimensionAttributeOption (
                AttributeID,
                OptionCode,
                OptionLabel,
                DisplayOrder,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :AttributeID,
                :OptionCode,
                :OptionLabel,
                :DisplayOrder,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':AttributeID' => $data['AttributeID'],
            ':OptionCode' => $data['OptionCode'],
            ':OptionLabel' => $data['OptionLabel'],
            ':DisplayOrder' => $data['DisplayOrder'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateDimensionAttributeOption(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbDimensionAttributeOption')) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbDimensionAttributeOption
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE AttributeOptionID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function getDimensionAttributeFields(string $dimensionCode, ?int $entityId = null): array
    {
        if (!$this->supportsStrategicDimensionAttributes()) {
            return [];
        }

        $dimensionCode = strtoupper(trim($dimensionCode));
        if ($dimensionCode === '') {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                AttributeID,
                StrategicDimensionCode,
                AttributeCode,
                AttributeName,
                DataTypeCode,
                HelpText,
                IsRequired,
                DisplayOrder
            FROM dbo.tblSbDimensionAttribute
            WHERE StrategicDimensionCode = :dimensionCode
              AND ActiveFlag = 1
            ORDER BY DisplayOrder ASC, AttributeName ASC
        ");
        $stmt->execute([':dimensionCode' => $dimensionCode]);
        $fields = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($fields === []) {
            return [];
        }

        $valuesByAttributeId = [];
        if ($entityId !== null && $entityId > 0) {
            $valueStmt = $this->conn->prepare("
                SELECT
                    AttributeID,
                    ValueText,
                    ValueNumber,
                    ValueDate,
                    ValueBit
                FROM dbo.tblSbDimensionAttributeValue
                WHERE StrategicDimensionCode = :dimensionCode
                  AND EntityID = :entityId
            ");
            $valueStmt->execute([
                ':dimensionCode' => $dimensionCode,
                ':entityId' => $entityId,
            ]);

            foreach ($valueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $valuesByAttributeId[(int) ($row['AttributeID'] ?? 0)] = $row;
            }
        }

        foreach ($fields as &$field) {
            $attributeId = (int) ($field['AttributeID'] ?? 0);
            $field['Options'] = [];
            $field['CurrentValue'] = null;

            if (strtoupper((string) ($field['DataTypeCode'] ?? '')) === 'LIST') {
                $field['Options'] = array_values(array_filter(
                    $this->listDimensionAttributeOptions($attributeId),
                    static fn(array $option): bool => (int) ($option['ActiveFlag'] ?? 0) === 1
                ));
            }

            if (isset($valuesByAttributeId[$attributeId])) {
                $field['CurrentValue'] = $this->normalizeDimensionAttributeStoredValue(
                    strtoupper((string) ($field['DataTypeCode'] ?? 'TEXT')),
                    $valuesByAttributeId[$attributeId]
                );
            }
        }
        unset($field);

        return $fields;
    }

    public function syncDimensionAttributeValues(string $dimensionCode, int $entityId, array $values, int $userId): void
    {
        if ($entityId <= 0 || !$this->supportsStrategicDimensionAttributes()) {
            return;
        }

        $fields = $this->getDimensionAttributeFields($dimensionCode, null);
        if ($fields === []) {
            return;
        }

        foreach ($fields as $field) {
            $attributeId = (int) ($field['AttributeID'] ?? 0);
            if ($attributeId <= 0) {
                continue;
            }

            $dataType = strtoupper((string) ($field['DataTypeCode'] ?? 'TEXT'));
            $rawValue = $values[$attributeId] ?? $values[(string) $attributeId] ?? null;

            if ($dataType === 'BOOLEAN') {
                $this->upsertDimensionAttributeValue(
                    strtoupper(trim($dimensionCode)),
                    $entityId,
                    $attributeId,
                    ['ValueBit' => (int) ($this->coerceBooleanValue($rawValue) ? 1 : 0)],
                    $userId
                );
                continue;
            }

            $normalized = $this->normalizeDimensionAttributeInputValue($dataType, $rawValue);
            if ($normalized === null) {
                $this->deleteDimensionAttributeValue(strtoupper(trim($dimensionCode)), $entityId, $attributeId);
                continue;
            }

            $payload = match ($dataType) {
                'NUMBER' => ['ValueNumber' => $normalized],
                'DATE' => ['ValueDate' => $normalized],
                default => ['ValueText' => $normalized],
            };

            $this->upsertDimensionAttributeValue(
                strtoupper(trim($dimensionCode)),
                $entityId,
                $attributeId,
                $payload,
                $userId
            );
        }
    }

    public function getObjectiveGoalIds(int $objectiveId): array
    {
        if ($objectiveId <= 0 || !$this->tableExists('dbo.tblSbObjectiveGoal')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT GoalID
            FROM dbo.tblSbObjectiveGoal
            WHERE ObjectiveID = :objectiveId
        ");
        $stmt->execute([':objectiveId' => $objectiveId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function saveObjective(array $data, ?int $id = null): int
    {
        $goalIds = array_values(array_unique(array_filter(array_map('intval', $data['GoalIDs'] ?? []))));

        $this->conn->beginTransaction();
        try {
            if ($id !== null && $id > 0) {
                $stmt = $this->conn->prepare("
                    UPDATE dbo.tblSbObjective
                    SET ProgramID = :ProgramID,
                        SubProgramID = :SubProgramID,
                        ObjectiveText = :ObjectiveText,
                        PolicyLink = :PolicyLink,
                        PriorityRank = :PriorityRank,
                        ActiveFlag = :ActiveFlag,
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE ObjectiveID = :ObjectiveID
                ");
                $stmt->execute([
                    ':ProgramID' => $data['ProgramID'],
                    ':SubProgramID' => $data['SubProgramID'],
                    ':ObjectiveText' => $data['ObjectiveText'],
                    ':PolicyLink' => $data['PolicyLink'],
                    ':PriorityRank' => $data['PriorityRank'],
                    ':ActiveFlag' => $data['ActiveFlag'],
                    ':UpdatedBy' => $data['UserID'],
                    ':ObjectiveID' => $id,
                ]);
                $objectiveId = $id;
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO dbo.tblSbObjective (
                        ProgramID,
                        SubProgramID,
                        ObjectiveText,
                        PolicyLink,
                        PriorityRank,
                        ActiveFlag,
                        CreatedBy,
                        CreatedDate
                    )
                    VALUES (
                        :ProgramID,
                        :SubProgramID,
                        :ObjectiveText,
                        :PolicyLink,
                        :PriorityRank,
                        :ActiveFlag,
                        :CreatedBy,
                        SYSDATETIME()
                    )
                ");
                $stmt->execute([
                    ':ProgramID' => $data['ProgramID'],
                    ':SubProgramID' => $data['SubProgramID'],
                    ':ObjectiveText' => $data['ObjectiveText'],
                    ':PolicyLink' => $data['PolicyLink'],
                    ':PriorityRank' => $data['PriorityRank'],
                    ':ActiveFlag' => $data['ActiveFlag'],
                    ':CreatedBy' => $data['UserID'],
                ]);
                $objectiveId = (int) $this->conn->lastInsertId();
            }

            $this->syncObjectiveGoals($objectiveId, $goalIds, (int) $data['UserID']);
            $this->conn->commit();
            return $objectiveId;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function deactivateObjective(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbObjective
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ObjectiveID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    private function syncObjectiveGoals(int $objectiveId, array $goalIds, int $userId): void
    {
        if ($objectiveId <= 0 || !$this->tableExists('dbo.tblSbObjectiveGoal')) {
            return;
        }

        $delete = $this->conn->prepare("
            DELETE FROM dbo.tblSbObjectiveGoal
            WHERE ObjectiveID = :objectiveId
        ");
        $delete->execute([':objectiveId' => $objectiveId]);

        if ($goalIds === []) {
            return;
        }

        $insert = $this->conn->prepare("
            INSERT INTO dbo.tblSbObjectiveGoal (
                ObjectiveID,
                GoalID,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :objectiveId,
                :goalId,
                :createdBy,
                SYSDATETIME()
            )
        ");

        foreach ($goalIds as $goalId) {
            if ($goalId <= 0) {
                continue;
            }

            $insert->execute([
                ':objectiveId' => $objectiveId,
                ':goalId' => $goalId,
                ':createdBy' => $userId,
            ]);
        }
    }

    public function listIndicators(string $q = '', ?string $typeCode = null): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(IndicatorName LIKE :q OR IndicatorDefinition LIKE :q OR UnitOfMeasure LIKE :q OR DataSource LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($typeCode !== null && $typeCode !== '') {
            $where[] = 'IndicatorTypeCode = :typeCode';
            $params[':typeCode'] = $typeCode;
        }

        $sql = "
            SELECT
                IndicatorID,
                IndicatorTypeCode,
                IndicatorName,
                IndicatorDefinition,
                UnitOfMeasure,
                DataSource,
                FrequencyCode,
                Disaggregation,
                ActiveFlag
            FROM dbo.tblSbIndicator
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY IndicatorTypeCode ASC, IndicatorName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getIndicator(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbIndicator WHERE IndicatorID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveIndicator(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbIndicator
                SET IndicatorTypeCode = :IndicatorTypeCode,
                    IndicatorName = :IndicatorName,
                    IndicatorDefinition = :IndicatorDefinition,
                    UnitOfMeasure = :UnitOfMeasure,
                    DataSource = :DataSource,
                    FrequencyCode = :FrequencyCode,
                    Disaggregation = :Disaggregation,
                    QualityNotes = :QualityNotes,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE IndicatorID = :IndicatorID
            ");
            $stmt->execute([
                ':IndicatorTypeCode' => $data['IndicatorTypeCode'],
                ':IndicatorName' => $data['IndicatorName'],
                ':IndicatorDefinition' => $data['IndicatorDefinition'],
                ':UnitOfMeasure' => $data['UnitOfMeasure'],
                ':DataSource' => $data['DataSource'],
                ':FrequencyCode' => $data['FrequencyCode'],
                ':Disaggregation' => $data['Disaggregation'],
                ':QualityNotes' => $data['QualityNotes'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':IndicatorID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbIndicator (
                IndicatorTypeCode,
                IndicatorName,
                IndicatorDefinition,
                UnitOfMeasure,
                DataSource,
                FrequencyCode,
                Disaggregation,
                QualityNotes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :IndicatorTypeCode,
                :IndicatorName,
                :IndicatorDefinition,
                :UnitOfMeasure,
                :DataSource,
                :FrequencyCode,
                :Disaggregation,
                :QualityNotes,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':IndicatorTypeCode' => $data['IndicatorTypeCode'],
            ':IndicatorName' => $data['IndicatorName'],
            ':IndicatorDefinition' => $data['IndicatorDefinition'],
            ':UnitOfMeasure' => $data['UnitOfMeasure'],
            ':DataSource' => $data['DataSource'],
            ':FrequencyCode' => $data['FrequencyCode'],
            ':Disaggregation' => $data['Disaggregation'],
            ':QualityNotes' => $data['QualityNotes'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateIndicator(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbIndicator
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE IndicatorID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listIndicatorTargets(int $fiscalYearId, int $versionId, string $q = '', ?int $indicatorId = null): array
    {
        $params = [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ];
        $where = [
            't.FiscalYearID = :fy',
            't.VersionID = :ver',
        ];

        if ($q !== '') {
            $where[] = '(i.IndicatorName LIKE :q OR i.IndicatorDefinition LIKE :q OR i.UnitOfMeasure LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($indicatorId !== null && $indicatorId > 0) {
            $where[] = 't.IndicatorID = :indicatorId';
            $params[':indicatorId'] = $indicatorId;
        }

        $sql = "
            SELECT
                t.IndicatorTargetID,
                t.IndicatorID,
                i.IndicatorTypeCode,
                i.IndicatorName,
                i.UnitOfMeasure,
                t.BaselineValue,
                t.TargetValue,
                t.Notes,
                t.ActiveFlag
            FROM dbo.tblSbIndicatorTarget t
            INNER JOIN dbo.tblSbIndicator i
                ON i.IndicatorID = t.IndicatorID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.IndicatorTypeCode ASC, i.IndicatorName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getIndicatorTarget(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbIndicatorTarget WHERE IndicatorTargetID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveIndicatorTarget(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbIndicatorTarget
                SET IndicatorID = :IndicatorID,
                    VersionID = :VersionID,
                    FiscalYearID = :FiscalYearID,
                    BaselineValue = :BaselineValue,
                    TargetValue = :TargetValue,
                    Notes = :Notes,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE IndicatorTargetID = :IndicatorTargetID
            ");
            $stmt->execute([
                ':IndicatorID' => $data['IndicatorID'],
                ':VersionID' => $data['VersionID'],
                ':FiscalYearID' => $data['FiscalYearID'],
                ':BaselineValue' => $data['BaselineValue'],
                ':TargetValue' => $data['TargetValue'],
                ':Notes' => $data['Notes'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':IndicatorTargetID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbIndicatorTarget (
                IndicatorID,
                VersionID,
                FiscalYearID,
                BaselineValue,
                TargetValue,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :IndicatorID,
                :VersionID,
                :FiscalYearID,
                :BaselineValue,
                :TargetValue,
                :Notes,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':IndicatorID' => $data['IndicatorID'],
            ':VersionID' => $data['VersionID'],
            ':FiscalYearID' => $data['FiscalYearID'],
            ':BaselineValue' => $data['BaselineValue'],
            ':TargetValue' => $data['TargetValue'],
            ':Notes' => $data['Notes'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateIndicatorTarget(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbIndicatorTarget
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE IndicatorTargetID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listOutputs(string $q = '', ?int $programId = null): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(o.OutputName LIKE :q OR o.OutputDescription LIKE :q OR p.ProgramName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($programId !== null && $programId > 0) {
            $where[] = 'o.ProgramID = :programId';
            $params[':programId'] = $programId;
        }

        $sql = "
            SELECT
                o.OutputID,
                o.ProgramID,
                o.SubProgramID,
                o.OutputName,
                o.OutputDescription,
                o.OutputOwnerOrgUnitID,
                o.ActiveFlag,
                p.ProgramName,
                sp.SubProgramName,
                COALESCE(doc.DataObjectName, ou.OrgUnitName) AS OutputOwnerOrgUnitName,
                ou.SourceDataObjectCode AS OutputOwnerDataObjectCode
            FROM dbo.tblSbOutput o
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = o.ProgramID
            LEFT JOIN dbo.tblSbSubProgram sp
                ON sp.SubProgramID = o.SubProgramID
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = o.OutputOwnerOrgUnitID
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = ou.SourceFiscalYearID
               AND doc.DataObjectCode = ou.SourceDataObjectCode
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.ProgramName ASC, o.OutputName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOutput(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT o.*, ou.SourceDataObjectCode AS OutputOwnerDataObjectCode
            FROM dbo.tblSbOutput o
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = o.OutputOwnerOrgUnitID
            WHERE o.OutputID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveOutput(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbOutput
                SET ProgramID = :ProgramID,
                    SubProgramID = :SubProgramID,
                    OutputName = :OutputName,
                    OutputDescription = :OutputDescription,
                    OutputOwnerOrgUnitID = :OutputOwnerOrgUnitID,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE OutputID = :OutputID
            ");
            $stmt->execute([
                ':ProgramID' => $data['ProgramID'],
                ':SubProgramID' => $data['SubProgramID'],
                ':OutputName' => $data['OutputName'],
                ':OutputDescription' => $data['OutputDescription'],
                ':OutputOwnerOrgUnitID' => $data['OutputOwnerOrgUnitID'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':OutputID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbOutput (
                ProgramID,
                SubProgramID,
                OutputName,
                OutputDescription,
                OutputOwnerOrgUnitID,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ProgramID,
                :SubProgramID,
                :OutputName,
                :OutputDescription,
                :OutputOwnerOrgUnitID,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':ProgramID' => $data['ProgramID'],
            ':SubProgramID' => $data['SubProgramID'],
            ':OutputName' => $data['OutputName'],
            ':OutputDescription' => $data['OutputDescription'],
            ':OutputOwnerOrgUnitID' => $data['OutputOwnerOrgUnitID'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateOutput(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbOutput
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE OutputID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listActivities(string $q = '', ?int $outputId = null): array
    {
        $params = [];
        $where = [];
        $projectJoin = '';
        $projectSelect = 'CAST(NULL AS INT) AS ProjectID, CAST(NULL AS NVARCHAR(50)) AS ProjectCode, CAST(NULL AS NVARCHAR(200)) AS ProjectName,';
        if ($this->tableColumnExists('dbo.tblSbActivity', 'ProjectID')) {
            $projectJoin = "
            LEFT JOIN dbo.tblSbProject pr
                ON pr.ProjectID = a.ProjectID";
            $projectSelect = 'a.ProjectID, pr.ProjectCode, pr.ProjectName,';
        }

        if ($q !== '') {
            $where[] = '(a.ActivityName LIKE :q OR a.LocationCode LIKE :q OR o.OutputName LIKE :q OR p.ProgramName LIKE :q OR COALESCE(pr.ProjectName, N\'\') LIKE :q OR COALESCE(pr.ProjectCode, N\'\') LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($outputId !== null && $outputId > 0) {
            $where[] = 'a.OutputID = :outputId';
            $params[':outputId'] = $outputId;
        }

        $sql = "
            SELECT
                a.ActivityID,
                a.OutputID,
                a.ActivityName,
                a.ActivityDescription,
                a.ActivityTypeCode,
                a.LocationCode,
                a.StartDate,
                a.EndDate,
                a.ImplementationStatusCode,
                a.ProcurementRequiredFlag,
                a.ActiveFlag,
                {$projectSelect}
                o.OutputName,
                p.ProgramName
            FROM dbo.tblSbActivity a
            INNER JOIN dbo.tblSbOutput o
                ON o.OutputID = a.OutputID
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = o.ProgramID
            {$projectJoin}
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.ProgramName ASC, o.OutputName ASC, a.ActivityName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getActivity(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbActivity WHERE ActivityID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveActivity(array $data, ?int $id = null): int
    {
        $hasProjectId = $this->tableColumnExists('dbo.tblSbActivity', 'ProjectID');
        if ($id !== null && $id > 0) {
            $sql = "
                UPDATE dbo.tblSbActivity
                SET OutputID = :OutputID,
                    ActivityName = :ActivityName,
                    ActivityDescription = :ActivityDescription,
                    ActivityTypeCode = :ActivityTypeCode,
                    LocationCode = :LocationCode,
                    StartDate = :StartDate,
                    EndDate = :EndDate,
                    ImplementationStatusCode = :ImplementationStatusCode,
                    ProcurementRequiredFlag = :ProcurementRequiredFlag,
                    Dependencies = :Dependencies,
                    RiskNotes = :RiskNotes," .
                    ($hasProjectId ? "
                    ProjectID = :ProjectID," : '') . "
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ActivityID = :ActivityID
            ";
            $params = [
                ':OutputID' => $data['OutputID'],
                ':ActivityName' => $data['ActivityName'],
                ':ActivityDescription' => $data['ActivityDescription'],
                ':ActivityTypeCode' => $data['ActivityTypeCode'],
                ':LocationCode' => $data['LocationCode'],
                ':StartDate' => $data['StartDate'],
                ':EndDate' => $data['EndDate'],
                ':ImplementationStatusCode' => $data['ImplementationStatusCode'],
                ':ProcurementRequiredFlag' => $data['ProcurementRequiredFlag'],
                ':Dependencies' => $data['Dependencies'],
                ':RiskNotes' => $data['RiskNotes'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':ActivityID' => $id,
            ];
            if ($hasProjectId) {
                $params[':ProjectID'] = $data['ProjectID'] ?? null;
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        $sql = "
            INSERT INTO dbo.tblSbActivity (
                OutputID,
                ActivityName,
                ActivityDescription,
                ActivityTypeCode,
                LocationCode,
                StartDate,
                EndDate,
                ImplementationStatusCode,
                ProcurementRequiredFlag,
                Dependencies,
                RiskNotes" .
                ($hasProjectId ? ",
                ProjectID" : '') . ",
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :OutputID,
                :ActivityName,
                :ActivityDescription,
                :ActivityTypeCode,
                :LocationCode,
                :StartDate,
                :EndDate,
                :ImplementationStatusCode,
                :ProcurementRequiredFlag,
                :Dependencies,
                :RiskNotes" .
                ($hasProjectId ? ",
                :ProjectID" : '') . ",
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ";
        $params = [
            ':OutputID' => $data['OutputID'],
            ':ActivityName' => $data['ActivityName'],
            ':ActivityDescription' => $data['ActivityDescription'],
            ':ActivityTypeCode' => $data['ActivityTypeCode'],
            ':LocationCode' => $data['LocationCode'],
            ':StartDate' => $data['StartDate'],
            ':EndDate' => $data['EndDate'],
            ':ImplementationStatusCode' => $data['ImplementationStatusCode'],
            ':ProcurementRequiredFlag' => $data['ProcurementRequiredFlag'],
            ':Dependencies' => $data['Dependencies'],
            ':RiskNotes' => $data['RiskNotes'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ];
        if ($hasProjectId) {
            $params[':ProjectID'] = $data['ProjectID'] ?? null;
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateActivity(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbActivity
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ActivityID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listActivityBudgets(int $fiscalYearId, int $versionId, string $q = '', ?int $activityId = null): array
    {
        $params = [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ];
        $where = [
            'FiscalYearID = :fy',
            'VersionID = :ver',
        ];

        if ($q !== '') {
            $where[] = '(ActivityName LIKE :q OR OutputName LIKE :q OR ProgramName LIKE :q OR EconomicName LIKE :q OR EconomicCode LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($activityId !== null && $activityId > 0) {
            $where[] = 'ActivityID = :activityId';
            $params[':activityId'] = $activityId;
        }

        $sql = "
            SELECT
                ActivityBudgetID,
                ActivityID,
                ActivityName,
                OutputName,
                ProgramName,
                SectorName,
                EconomicItemID,
                EconomicCode,
                EconomicName,
                FundingSourceID,
                FundingSourceName,
                Amount,
                CurrencyCode
            FROM dbo.vwSbActivityBudgetDetail
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ProgramName ASC, OutputName ASC, ActivityName ASC, EconomicCode ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getActivityBudget(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbActivityBudget WHERE ActivityBudgetID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveActivityBudget(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbActivityBudget
                SET ActivityID = :ActivityID,
                    VersionID = :VersionID,
                    FiscalYearID = :FiscalYearID,
                    EconomicItemID = :EconomicItemID,
                    FundingSourceID = :FundingSourceID,
                    Amount = :Amount,
                    CurrencyCode = :CurrencyCode,
                    Notes = :Notes,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ActivityBudgetID = :ActivityBudgetID
            ");
            $stmt->execute([
                ':ActivityID' => $data['ActivityID'],
                ':VersionID' => $data['VersionID'],
                ':FiscalYearID' => $data['FiscalYearID'],
                ':EconomicItemID' => $data['EconomicItemID'],
                ':FundingSourceID' => $data['FundingSourceID'],
                ':Amount' => $data['Amount'],
                ':CurrencyCode' => $data['CurrencyCode'],
                ':Notes' => $data['Notes'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':ActivityBudgetID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbActivityBudget (
                ActivityID,
                VersionID,
                FiscalYearID,
                EconomicItemID,
                FundingSourceID,
                Amount,
                CurrencyCode,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ActivityID,
                :VersionID,
                :FiscalYearID,
                :EconomicItemID,
                :FundingSourceID,
                :Amount,
                :CurrencyCode,
                :Notes,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':ActivityID' => $data['ActivityID'],
            ':VersionID' => $data['VersionID'],
            ':FiscalYearID' => $data['FiscalYearID'],
            ':EconomicItemID' => $data['EconomicItemID'],
            ':FundingSourceID' => $data['FundingSourceID'],
            ':Amount' => $data['Amount'],
            ':CurrencyCode' => $data['CurrencyCode'],
            ':Notes' => $data['Notes'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateActivityBudget(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbActivityBudget
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ActivityBudgetID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function getActivityBudgetSummary(int $fiscalYearId, int $versionId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                COUNT(*) AS BudgetLineCount,
                COUNT(DISTINCT ActivityID) AS ActivityCount,
                SUM(Amount) AS TotalAmount
            FROM dbo.tblSbActivityBudget
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function listNarratives(int $fiscalYearId, int $versionId, string $q = '', ?string $sectionCode = null): array
    {
        $supportsProjectLink = $this->supportsNarrativeProjectLink();
        $params = [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ];
        $where = [
            'n.FiscalYearID = :fy',
            'n.VersionID = :ver',
        ];

        if ($q !== '') {
            $where[] = $supportsProjectLink
                ? '(n.NarrativeTitle LIKE :q OR n.BodyText LIKE :q OR p.ProgramName LIKE :q OR s.SectorName LIKE :q OR ou.OrgUnitName LIKE :q OR pj.ProjectName LIKE :q OR pj.ProjectCode LIKE :q)'
                : '(n.NarrativeTitle LIKE :q OR n.BodyText LIKE :q OR p.ProgramName LIKE :q OR s.SectorName LIKE :q OR ou.OrgUnitName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($sectionCode !== null && $sectionCode !== '') {
            $where[] = 'n.SectionCode = :sectionCode';
            $params[':sectionCode'] = $sectionCode;
        }

        $sql = "
            SELECT
                n.NarrativeID,
                n.SectionCode,
                n.OrgUnitID,
                n.SectorID,
                n.ProgramID,
                " . ($supportsProjectLink ? 'n.ProjectID,' : 'CAST(NULL AS INT) AS ProjectID,') . "
                n.NarrativeTitle,
                n.SortOrder,
                n.LockedFlag,
                n.ActiveFlag,
                n.BodyText,
                p.ProgramName,
                s.SectorName,
                ou.OrgUnitName,
                " . ($supportsProjectLink
                    ? 'pj.ProjectCode, pj.ProjectName'
                    : "CAST(NULL AS NVARCHAR(50)) AS ProjectCode, CAST(NULL AS NVARCHAR(255)) AS ProjectName") . "
            FROM dbo.tblSbNarrative n
            LEFT JOIN dbo.tblSbProgram p
                ON p.ProgramID = n.ProgramID
            LEFT JOIN dbo.tblSbSector s
                ON s.SectorID = n.SectorID
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = n.OrgUnitID
            " . ($supportsProjectLink ? 'LEFT JOIN dbo.tblSbProject pj ON pj.ProjectID = n.ProjectID' : '') . "
            WHERE " . implode(' AND ', $where) . "
            ORDER BY n.SectionCode ASC, n.SortOrder ASC, n.NarrativeID ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getNarrative(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbNarrative WHERE NarrativeID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveNarrative(array $data, ?int $id = null): int
    {
        $supportsProjectLink = $this->supportsNarrativeProjectLink();

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbNarrative
                SET VersionID = :VersionID,
                    FiscalYearID = :FiscalYearID,
                    SectionCode = :SectionCode,
                    OrgUnitID = :OrgUnitID,
                    SectorID = :SectorID,
                    ProgramID = :ProgramID,
                    " . ($supportsProjectLink ? 'ProjectID = :ProjectID,' : '') . "
                    NarrativeTitle = :NarrativeTitle,
                    BodyText = :BodyText,
                    SortOrder = :SortOrder,
                    LockedFlag = :LockedFlag,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE NarrativeID = :NarrativeID
            ");
            $stmt->execute([
                ':VersionID' => $data['VersionID'],
                ':FiscalYearID' => $data['FiscalYearID'],
                ':SectionCode' => $data['SectionCode'],
                ':OrgUnitID' => $data['OrgUnitID'],
                ':SectorID' => $data['SectorID'],
                ':ProgramID' => $data['ProgramID'],
                ...($supportsProjectLink ? [':ProjectID' => $data['ProjectID'] ?? null] : []),
                ':NarrativeTitle' => $data['NarrativeTitle'],
                ':BodyText' => $data['BodyText'],
                ':SortOrder' => $data['SortOrder'],
                ':LockedFlag' => $data['LockedFlag'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':NarrativeID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbNarrative (
                VersionID,
                FiscalYearID,
                SectionCode,
                OrgUnitID,
                SectorID,
                ProgramID,
                " . ($supportsProjectLink ? 'ProjectID,' : '') . "
                NarrativeTitle,
                BodyText,
                SortOrder,
                LockedFlag,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :VersionID,
                :FiscalYearID,
                :SectionCode,
                :OrgUnitID,
                :SectorID,
                :ProgramID,
                " . ($supportsProjectLink ? ':ProjectID,' : '') . "
                :NarrativeTitle,
                :BodyText,
                :SortOrder,
                :LockedFlag,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':VersionID' => $data['VersionID'],
            ':FiscalYearID' => $data['FiscalYearID'],
            ':SectionCode' => $data['SectionCode'],
            ':OrgUnitID' => $data['OrgUnitID'],
            ':SectorID' => $data['SectorID'],
            ':ProgramID' => $data['ProgramID'],
            ...($supportsProjectLink ? [':ProjectID' => $data['ProjectID'] ?? null] : []),
            ':NarrativeTitle' => $data['NarrativeTitle'],
            ':BodyText' => $data['BodyText'],
            ':SortOrder' => $data['SortOrder'],
            ':LockedFlag' => $data['LockedFlag'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateNarrative(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbNarrative
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE NarrativeID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listFiscalRisks(string $q = '', ?string $riskTypeCode = null): array
    {
        $supportsProjectLink = $this->supportsFiscalRiskProjectLink();
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = $supportsProjectLink
                ? '(r.RiskTitle LIKE :q OR r.RiskDescription LIKE :q OR r.MitigationStrategy LIKE :q OR ou.OrgUnitName LIKE :q OR pj.ProjectName LIKE :q OR pj.ProjectCode LIKE :q)'
                : '(r.RiskTitle LIKE :q OR r.RiskDescription LIKE :q OR r.MitigationStrategy LIKE :q OR ou.OrgUnitName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($riskTypeCode !== null && $riskTypeCode !== '') {
            $where[] = 'r.RiskTypeCode = :riskTypeCode';
            $params[':riskTypeCode'] = $riskTypeCode;
        }

        $sql = "
            SELECT
                r.FiscalRiskID,
                r.RiskTypeCode,
                r.RiskTitle,
                r.RiskDescription,
                r.LikelihoodScore,
                r.ImpactScore,
                r.EstimatedFiscalExposure,
                " . ($supportsProjectLink ? 'r.ProjectID,' : 'CAST(NULL AS INT) AS ProjectID,') . "
                r.OwnerOrgUnitID,
                r.ActiveFlag,
                ou.OrgUnitName AS OwnerOrgUnitName,
                " . ($supportsProjectLink
                    ? 'pj.ProjectCode, pj.ProjectName'
                    : "CAST(NULL AS NVARCHAR(50)) AS ProjectCode, CAST(NULL AS NVARCHAR(255)) AS ProjectName") . "
            FROM dbo.tblSbFiscalRisk r
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = r.OwnerOrgUnitID
            " . ($supportsProjectLink ? 'LEFT JOIN dbo.tblSbProject pj ON pj.ProjectID = r.ProjectID' : '') . "
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY r.RiskTypeCode ASC, r.RiskTitle ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFiscalRisk(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbFiscalRisk WHERE FiscalRiskID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveFiscalRisk(array $data, ?int $id = null): int
    {
        $supportsProjectLink = $this->supportsFiscalRiskProjectLink();

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbFiscalRisk
                SET RiskTypeCode = :RiskTypeCode,
                    RiskTitle = :RiskTitle,
                    RiskDescription = :RiskDescription,
                    LikelihoodScore = :LikelihoodScore,
                    ImpactScore = :ImpactScore,
                    EstimatedFiscalExposure = :EstimatedFiscalExposure,
                    MitigationStrategy = :MitigationStrategy,
                    " . ($supportsProjectLink ? 'ProjectID = :ProjectID,' : '') . "
                    OwnerOrgUnitID = :OwnerOrgUnitID,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE FiscalRiskID = :FiscalRiskID
            ");
            $stmt->execute([
                ':RiskTypeCode' => $data['RiskTypeCode'],
                ':RiskTitle' => $data['RiskTitle'],
                ':RiskDescription' => $data['RiskDescription'],
                ':LikelihoodScore' => $data['LikelihoodScore'],
                ':ImpactScore' => $data['ImpactScore'],
                ':EstimatedFiscalExposure' => $data['EstimatedFiscalExposure'],
                ':MitigationStrategy' => $data['MitigationStrategy'],
                ...($supportsProjectLink ? [':ProjectID' => $data['ProjectID'] ?? null] : []),
                ':OwnerOrgUnitID' => $data['OwnerOrgUnitID'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':FiscalRiskID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbFiscalRisk (
                RiskTypeCode,
                RiskTitle,
                RiskDescription,
                LikelihoodScore,
                ImpactScore,
                EstimatedFiscalExposure,
                MitigationStrategy,
                " . ($supportsProjectLink ? 'ProjectID,' : '') . "
                OwnerOrgUnitID,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :RiskTypeCode,
                :RiskTitle,
                :RiskDescription,
                :LikelihoodScore,
                :ImpactScore,
                :EstimatedFiscalExposure,
                :MitigationStrategy,
                " . ($supportsProjectLink ? ':ProjectID,' : '') . "
                :OwnerOrgUnitID,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':RiskTypeCode' => $data['RiskTypeCode'],
            ':RiskTitle' => $data['RiskTitle'],
            ':RiskDescription' => $data['RiskDescription'],
            ':LikelihoodScore' => $data['LikelihoodScore'],
            ':ImpactScore' => $data['ImpactScore'],
            ':EstimatedFiscalExposure' => $data['EstimatedFiscalExposure'],
            ':MitigationStrategy' => $data['MitigationStrategy'],
            ...($supportsProjectLink ? [':ProjectID' => $data['ProjectID'] ?? null] : []),
            ':OwnerOrgUnitID' => $data['OwnerOrgUnitID'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateFiscalRisk(int $id, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFiscalRisk
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE FiscalRiskID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function listProgramRisks(string $q = '', ?int $programId = null, ?int $fiscalRiskId = null): array
    {
        $params = [];
        $where = [];

        if ($q !== '') {
            $where[] = '(p.ProgramName LIKE :q OR r.RiskTitle LIKE :q OR r.RiskTypeCode LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($programId !== null && $programId > 0) {
            $where[] = 'pr.ProgramID = :programId';
            $params[':programId'] = $programId;
        }
        if ($fiscalRiskId !== null && $fiscalRiskId > 0) {
            $where[] = 'pr.FiscalRiskID = :fiscalRiskId';
            $params[':fiscalRiskId'] = $fiscalRiskId;
        }

        $sql = "
            SELECT
                pr.ProgramRiskID,
                pr.ProgramID,
                pr.FiscalRiskID,
                p.ProgramName,
                p.ProgramCode,
                r.RiskTitle,
                r.RiskTypeCode,
                r.LikelihoodScore,
                r.ImpactScore
            FROM dbo.tblSbProgramRisk pr
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = pr.ProgramID
            INNER JOIN dbo.tblSbFiscalRisk r
                ON r.FiscalRiskID = pr.FiscalRiskID
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.ProgramName ASC, r.RiskTitle ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProgramRisk(int $id): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbProgramRisk WHERE ProgramRiskID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveProgramRisk(array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbProgramRisk
                SET ProgramID = :ProgramID,
                    FiscalRiskID = :FiscalRiskID
                WHERE ProgramRiskID = :ProgramRiskID
            ");
            $stmt->execute([
                ':ProgramID' => $data['ProgramID'],
                ':FiscalRiskID' => $data['FiscalRiskID'],
                ':ProgramRiskID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbProgramRisk (
                ProgramID,
                FiscalRiskID,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ProgramID,
                :FiscalRiskID,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':ProgramID' => $data['ProgramID'],
            ':FiscalRiskID' => $data['FiscalRiskID'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deleteProgramRisk(int $id): void
    {
        $stmt = $this->conn->prepare('DELETE FROM dbo.tblSbProgramRisk WHERE ProgramRiskID = :id');
        $stmt->execute([':id' => $id]);
    }

    public function subProgramBelongsToProgram(?int $subProgramId, int $programId): bool
    {
        if ($subProgramId === null || $subProgramId <= 0) {
            return true;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSbSubProgram
            WHERE SubProgramID = :subProgramId
              AND ProgramID = :programId
        ");
        $stmt->execute([
            ':subProgramId' => $subProgramId,
            ':programId' => $programId,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function activityBelongsToProgram(?int $activityId, int $programId, ?int $subProgramId = null): bool
    {
        if ($activityId === null || $activityId <= 0) {
            return true;
        }
        if ($programId <= 0) {
            return false;
        }

        $params = [
            ':activityId' => $activityId,
            ':programId' => $programId,
        ];
        $subProgramFilter = '';
        if ($subProgramId !== null && $subProgramId > 0) {
            $subProgramFilter = ' AND o.SubProgramID = :subProgramId';
            $params[':subProgramId'] = $subProgramId;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSbActivity a
            INNER JOIN dbo.tblSbOutput o
                ON o.OutputID = a.OutputID
            WHERE a.ActivityID = :activityId
              AND o.ProgramID = :programId{$subProgramFilter}
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function listOrgUnitOptions(): array
    {
        $stmt = $this->conn->query("
            SELECT OrgUnitID, OrgUnitName, OrgUnitTypeCode, VoteCode, SourceFiscalYearID, SourceDataObjectCode
            FROM dbo.tblSbOrgUnit
            WHERE ActiveFlag = 1
            ORDER BY OrgUnitName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listOrgUnitOptionsForDataObject(int $fiscalYearId, string $dataObjectCode, ?int $userId = null): array
    {
        $scopeCodes = $this->getDataObjectDescendantScope($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [trim($dataObjectCode)];
        }
        $scopeCodes = array_values(array_filter(array_map('trim', $scopeCodes), static fn(string $code): bool => $code !== ''));
        if ($scopeCodes === []) {
            return $this->listOrgUnitOptions();
        }

        $this->materializeOrgUnitsForDataObjectScope($fiscalYearId, $scopeCodes, $userId ?? 1);

        $params = [':fy' => $fiscalYearId];
        $scopeSql = $this->buildScopeInListParams($scopeCodes, 'scopeOrgUnit', $params);
        $stmt = $this->conn->prepare("
            SELECT OrgUnitID, OrgUnitName, OrgUnitTypeCode, VoteCode, SourceFiscalYearID, SourceDataObjectCode
            FROM dbo.tblSbOrgUnit
            WHERE ActiveFlag = 1
              AND SourceFiscalYearID = :fy
              AND COALESCE(SourceDataObjectCode, N'') IN ({$scopeSql})
            ORDER BY CASE WHEN COALESCE(SourceDataObjectCode, N'') = N'' THEN 1 ELSE 0 END,
                     SourceDataObjectCode ASC,
                     OrgUnitName ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function orgUnitBelongsToDataObject(int $orgUnitId, int $fiscalYearId, string $dataObjectCode): bool
    {
        if ($orgUnitId <= 0) {
            return true;
        }

        $scopeCodes = $this->getDataObjectDescendantScope($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [trim($dataObjectCode)];
        }
        $scopeCodes = array_values(array_filter(array_map('trim', $scopeCodes), static fn(string $code): bool => $code !== ''));
        if ($scopeCodes === []) {
            return false;
        }

        $params = [
            ':orgUnitId' => $orgUnitId,
            ':fy' => $fiscalYearId,
        ];
        $scopeSql = $this->buildScopeInListParams($scopeCodes, 'scopeOrgUnitCheck', $params);
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSbOrgUnit
            WHERE OrgUnitID = :orgUnitId
              AND ActiveFlag = 1
              AND SourceFiscalYearID = :fy
              AND COALESCE(SourceDataObjectCode, N'') IN ({$scopeSql})
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function listParentOrgUnitOptions(?int $excludeId = null): array
    {
        $sql = "
            SELECT OrgUnitID, OrgUnitName, OrgUnitTypeCode, VoteCode
            FROM dbo.tblSbOrgUnit
            WHERE ActiveFlag = 1
        ";
        $params = [];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND OrgUnitID <> :excludeId';
            $params[':excludeId'] = $excludeId;
        }
        $sql .= ' ORDER BY OrgUnitName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSectorOptions(): array
    {
        $stmt = $this->conn->query("
            SELECT SectorID, SectorName
            FROM dbo.tblSbSector
            WHERE ActiveFlag = 1
            ORDER BY SectorID ASC, SectorName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSectorOptionsForDataObject(int $fiscalYearId, string $dataObjectCode): array
    {
        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [trim($dataObjectCode)];
        }
        $scopeCodes = array_values(array_filter(array_map('trim', $scopeCodes), static fn(string $code): bool => $code !== ''));
        if ($scopeCodes === []) {
            return $this->listSectorOptions();
        }

        $params = [];
        $scopeSqlProgram = $this->buildScopeInListParams($scopeCodes, 'scopeSectorProgram', $params);
        $scopeSqlOwner = $this->buildScopeInListParams($scopeCodes, 'scopeSectorOwner', $params);

        if ($this->supportsProgramOrgLinks()) {
            $scopeSqlLinked = $this->buildScopeInListParams($scopeCodes, 'scopeSectorLinked', $params);
            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    s.SectorID,
                    s.SectorName
                FROM dbo.tblSbSector s
                INNER JOIN dbo.tblSbProgram p
                    ON p.SectorID = s.SectorID
                   AND p.ActiveFlag = 1
                INNER JOIN dbo.tblSbOrgUnit ownerOu
                    ON ownerOu.OrgUnitID = p.OrgUnitID
                LEFT JOIN dbo.tblSbProgramOrgLink pol
                    ON pol.ProgramID = p.ProgramID
                   AND pol.ActiveFlag = 1
                LEFT JOIN dbo.tblSbOrgUnit linkedOu
                    ON linkedOu.OrgUnitID = pol.OrgUnitID
                WHERE s.ActiveFlag = 1
                  AND (
                        p.SourceDataObjectCode IN ({$scopeSqlProgram})
                     OR ownerOu.SourceDataObjectCode IN ({$scopeSqlOwner})
                     OR linkedOu.SourceDataObjectCode IN ({$scopeSqlLinked})
                  )
                ORDER BY s.SectorID ASC, s.SectorName ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->conn->prepare("
            SELECT DISTINCT
                s.SectorID,
                s.SectorName
            FROM dbo.tblSbSector s
            INNER JOIN dbo.tblSbProgram p
                ON p.SectorID = s.SectorID
               AND p.ActiveFlag = 1
            INNER JOIN dbo.tblSbOrgUnit ownerOu
                ON ownerOu.OrgUnitID = p.OrgUnitID
            WHERE s.ActiveFlag = 1
              AND (
                    p.SourceDataObjectCode IN ({$scopeSqlProgram})
                 OR ownerOu.SourceDataObjectCode IN ({$scopeSqlOwner})
              )
            ORDER BY s.SectorID ASC, s.SectorName ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sectorBelongsToDataObject(int $sectorId, int $fiscalYearId, string $dataObjectCode): bool
    {
        if ($sectorId <= 0) {
            return true;
        }

        foreach ($this->listSectorOptionsForDataObject($fiscalYearId, $dataObjectCode) as $option) {
            if ((int) ($option['SectorID'] ?? 0) === $sectorId) {
                return true;
            }
        }

        return false;
    }

    public function getStrategicSegmentDefinitions(): array
    {
        return [
            [
                'Code' => 'SECTOR',
                'Label' => 'Sector',
                'HelpText' => 'Map Sector only if your client maintains policy sectors in a CBMS segment; otherwise leave it unmapped and manage sectors directly in the strategic module.',
                'Required' => false,
            ],
            [
                'Code' => 'PROGRAM',
                'Label' => 'Program',
                'HelpText' => 'Map Program if your client stores programs in a CBMS segment. Leave it unmapped only if programs will be maintained directly in the strategic module.',
                'Required' => false,
            ],
            [
                'Code' => 'SUBPROGRAM',
                'Label' => 'SubProgram',
                'HelpText' => 'Map SubProgram if your client stores subprograms in a CBMS segment. Leave it unmapped only if subprograms will be maintained directly in the strategic module.',
                'Required' => false,
            ],
            [
                'Code' => 'PROJECT',
                'Label' => 'Project',
                'HelpText' => 'Map Project if your client stores projects in a CBMS segment and wants them imported into the strategic module.',
                'Required' => false,
            ],
            [
                'Code' => 'ECONOMIC',
                'Label' => 'Economic',
                'HelpText' => 'Map Economic if your client sources economic classifications from CBMS segments; otherwise maintain them directly in the strategic module.',
                'Required' => false,
            ],
            [
                'Code' => 'FUNDING_TYPE',
                'Label' => 'Funding Type',
                'HelpText' => 'Map Funding Type if your client sources funding types from CBMS segments; otherwise maintain them directly in the strategic module.',
                'Required' => false,
            ],
            [
                'Code' => 'FUNDING_SOURCE',
                'Label' => 'Funding Source',
                'HelpText' => 'Map Funding Source if your client sources funding sources or donors from CBMS segments; otherwise maintain them directly in the strategic module.',
                'Required' => false,
            ],
            [
                'Code' => 'OBJECTIVE',
                'Label' => 'Objective',
                'HelpText' => 'Objectives are strategic records in this module and do not need a CBMS segment unless your implementation uses one.',
                'Required' => false,
            ],
            [
                'Code' => 'OUTPUT',
                'Label' => 'Output',
                'HelpText' => 'Map Output only if your client stores outputs in a CBMS segment; otherwise leave it unmapped.',
                'Required' => false,
            ],
            [
                'Code' => 'ACTIVITY',
                'Label' => 'Activity',
                'HelpText' => 'Map Activity only if your client stores activities in a CBMS segment; otherwise leave it unmapped.',
                'Required' => false,
            ],
            [
                'Code' => 'INDICATOR',
                'Label' => 'Indicator',
                'HelpText' => 'Indicators are strategic records in this module and can remain unmapped unless your client stores them in a CBMS segment.',
                'Required' => false,
            ],
            [
                'Code' => 'TARGET',
                'Label' => 'Target',
                'HelpText' => 'Targets are strategic records in this module and can remain unmapped where no source segment exists.',
                'Required' => false,
            ],
        ];
    }

    public function listAvailableSegments(): array
    {
        $stmt = $this->conn->query("
            SELECT
                TRY_CONVERT(INT, SegmentCode) AS SegmentNo,
                SegmentCode,
                SegmentName,
                MaxLength,
                MinLength,
                CBMSDimension,
                SegmentGroup,
                DisplayOrder
            FROM dbo.tblSegments
            WHERE TRY_CONVERT(INT, SegmentCode) IS NOT NULL
            ORDER BY TRY_CONVERT(INT, SegmentCode) ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $segmentNo = (int) ($row['SegmentNo'] ?? 0);
            $segmentName = trim((string) ($row['SegmentName'] ?? ''));
            $segmentGroup = trim((string) ($row['SegmentGroup'] ?? ''));
            $cbmsDimension = trim((string) ($row['CBMSDimension'] ?? ''));
            $row['SegmentLabel'] = $segmentNo . ' - ' . $segmentName
                . ($segmentGroup !== '' ? ' [' . $segmentGroup . ']' : '')
                . ($cbmsDimension !== '' ? ' / ' . $cbmsDimension : '');
        }
        unset($row);

        return $rows;
    }

    public function getAvailableSegmentByNo(int $segmentNo): ?array
    {
        if ($segmentNo <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT
                TRY_CONVERT(INT, SegmentCode) AS SegmentNo,
                SegmentCode,
                SegmentName,
                MaxLength,
                MinLength,
                CBMSDimension,
                SegmentGroup,
                DisplayOrder
            FROM dbo.tblSegments
            WHERE TRY_CONVERT(INT, SegmentCode) = :segmentNo
        ");
        $stmt->execute([':segmentNo' => $segmentNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row === null) {
            return null;
        }

        $segmentName = trim((string) ($row['SegmentName'] ?? ''));
        $segmentGroup = trim((string) ($row['SegmentGroup'] ?? ''));
        $cbmsDimension = trim((string) ($row['CBMSDimension'] ?? ''));
        $row['SegmentLabel'] = $segmentNo . ' - ' . $segmentName
            . ($segmentGroup !== '' ? ' [' . $segmentGroup . ']' : '')
            . ($cbmsDimension !== '' ? ' / ' . $cbmsDimension : '');

        return $row;
    }

    public function supportsFiscalPeriodConfig(): bool
    {
        return $this->tableExists('dbo.tblSbFiscalPeriodConfig');
    }

    public function supportsPhasingProfileConfig(): bool
    {
        return $this->tableExists('dbo.tblSbPhasingProfile');
    }

    public function supportsFiscalAssumptionConfig(): bool
    {
        return $this->tableExists('dbo.tblSbFiscalAssumption');
    }

    public function buildDefaultFiscalAssumption(int $fiscalYearId, int $versionId): array
    {
        return [
            'FiscalAssumptionID' => 0,
            'FiscalYearID' => $fiscalYearId,
            'VersionID' => $versionId > 0 ? $versionId : null,
            'AssumptionCode' => 'INFLATION_RATE',
            'AssumptionName' => 'Inflation Rate',
            'AssumptionValue' => 0.0,
            'AssumptionNotes' => null,
            'ActiveFlag' => 1,
        ];
    }

    public function getFiscalAssumptionDefinitions(): array
    {
        return [
            ['code' => 'INFLATION_RATE', 'name' => 'Inflation Rate'],
            ['code' => 'WAGE_GROWTH_RATE', 'name' => 'Wage Growth Rate'],
            ['code' => 'EXCHANGE_RATE', 'name' => 'Exchange Rate'],
            ['code' => 'GDP_GROWTH_RATE', 'name' => 'GDP Growth Rate'],
        ];
    }

    public function listFiscalAssumptions(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || !$this->supportsFiscalAssumptionConfig()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbFiscalAssumption
            WHERE FiscalYearID = :fy
              AND (
                    (:verMatch > 0 AND VersionID = :verFilter)
                 OR (:verNull <= 0 AND VersionID IS NULL)
              )
            ORDER BY AssumptionName ASC, AssumptionCode ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':verMatch' => $versionId,
            ':verFilter' => $versionId,
            ':verNull' => $versionId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFiscalAssumption(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsFiscalAssumptionConfig()) {
            return null;
        }
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbFiscalAssumption WHERE FiscalAssumptionID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveFiscalAssumption(int $fiscalYearId, int $versionId, array $data, ?int $id = null): int
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }
        if (!$this->supportsFiscalAssumptionConfig()) {
            throw new \RuntimeException('Fiscal assumption table is not installed.');
        }

        $params = [
            ':FiscalYearID' => $fiscalYearId,
            ':VersionID' => $versionId > 0 ? $versionId : null,
            ':AssumptionCode' => strtoupper(trim((string) ($data['AssumptionCode'] ?? ''))),
            ':AssumptionName' => trim((string) ($data['AssumptionName'] ?? '')),
            ':AssumptionValue' => (float) ($data['AssumptionValue'] ?? 0),
            ':AssumptionNotes' => $data['AssumptionNotes'] ?? null,
            ':ActiveFlag' => (int) ($data['ActiveFlag'] ?? 1),
            ':UserID' => (int) ($data['UserID'] ?? 1),
        ];

        if ($params[':AssumptionCode'] === '' || $params[':AssumptionName'] === '') {
            throw new \InvalidArgumentException('Assumption code and name are required.');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbFiscalAssumption
                SET AssumptionCode = :AssumptionCode,
                    AssumptionName = :AssumptionName,
                    AssumptionValue = :AssumptionValue,
                    AssumptionNotes = :AssumptionNotes,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UserID,
                    UpdatedDate = SYSDATETIME()
                WHERE FiscalAssumptionID = :FiscalAssumptionID
            ");
            $stmt->execute([
                ':AssumptionCode' => $params[':AssumptionCode'],
                ':AssumptionName' => $params[':AssumptionName'],
                ':AssumptionValue' => $params[':AssumptionValue'],
                ':AssumptionNotes' => $params[':AssumptionNotes'],
                ':ActiveFlag' => $params[':ActiveFlag'],
                ':UserID' => $params[':UserID'],
                ':FiscalAssumptionID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbFiscalAssumption (
                FiscalYearID,
                VersionID,
                AssumptionCode,
                AssumptionName,
                AssumptionValue,
                AssumptionNotes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :FiscalYearID,
                :VersionID,
                :AssumptionCode,
                :AssumptionName,
                :AssumptionValue,
                :AssumptionNotes,
                :ActiveFlag,
                :UserID,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':FiscalYearID' => $params[':FiscalYearID'],
            ':VersionID' => $params[':VersionID'],
            ':AssumptionCode' => $params[':AssumptionCode'],
            ':AssumptionName' => $params[':AssumptionName'],
            ':AssumptionValue' => $params[':AssumptionValue'],
            ':AssumptionNotes' => $params[':AssumptionNotes'],
            ':ActiveFlag' => $params[':ActiveFlag'],
            ':UserID' => $params[':UserID'],
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function deactivateFiscalAssumption(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->supportsFiscalAssumptionConfig()) {
            return;
        }
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFiscalAssumption
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE FiscalAssumptionID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function getFiscalAssumptionValue(int $fiscalYearId, int $versionId, string $assumptionCode): ?float
    {
        if ($fiscalYearId <= 0 || !$this->supportsFiscalAssumptionConfig()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 AssumptionValue
            FROM dbo.tblSbFiscalAssumption
            WHERE FiscalYearID = :fy
              AND AssumptionCode = :code
              AND ActiveFlag = 1
              AND (
                    (:verMatch > 0 AND VersionID = :verFilter)
                 OR (VersionID IS NULL)
              )
            ORDER BY CASE WHEN VersionID = :verOrder THEN 0 ELSE 1 END, FiscalAssumptionID DESC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':verMatch' => $versionId,
            ':verFilter' => $versionId,
            ':verOrder' => $versionId,
            ':code' => strtoupper(trim($assumptionCode)),
        ]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (float) $value;
    }

    public function getMonthOptions(): array
    {
        return [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];
    }

    private function inferFiscalStartMonthNo(int $fiscalYearId): int
    {
        if ($fiscalYearId > 0) {
            $stmt = $this->conn->prepare('
                SELECT TOP 1 MONTH(StartDate) AS StartMonthNo
                FROM dbo.tblFiscalYears
                WHERE FiscalYearID = :fy
            ');
            $stmt->execute([':fy' => $fiscalYearId]);
            $monthNo = (int) ($stmt->fetchColumn() ?: 0);
            if ($monthNo >= 1 && $monthNo <= 12) {
                return $monthNo;
            }
        }

        return 4;
    }

    public function buildDefaultFiscalPeriodConfig(int $fiscalYearId, int $startMonthNo = 0): array
    {
        if ($startMonthNo < 1 || $startMonthNo > 12) {
            $startMonthNo = $this->inferFiscalStartMonthNo($fiscalYearId);
        }
        $startMonthNo = max(1, min(12, $startMonthNo));
        $startYear = $fiscalYearId > 0 ? $fiscalYearId : (int) date('Y');
        $monthNames = array_values($this->getMonthOptions());

        $record = [
            'FiscalYearID' => $fiscalYearId,
            'StartMonthNo' => $startMonthNo,
            'ActiveFlag' => 1,
        ];

        for ($i = 1; $i <= 12; $i++) {
            $monthIndex = ($startMonthNo - 1 + ($i - 1)) % 12;
            $yearOffset = (int) floor(($startMonthNo - 1 + ($i - 1)) / 12);
            $record['BP' . $i . 'Label'] = $monthNames[$monthIndex] . ' ' . ($startYear + $yearOffset);
        }

        for ($i = 1; $i <= 5; $i++) {
            $oyStart = $startYear + $i;
            $oyEndShort = substr((string) ($oyStart + 1), -2);
            $record['OuterYear' . $i . 'Label'] = $oyStart . '/' . $oyEndShort;
        }

        return $record;
    }

    public function getFiscalPeriodConfig(int $fiscalYearId): array
    {
        $default = $this->buildDefaultFiscalPeriodConfig($fiscalYearId);
        if ($fiscalYearId <= 0 || !$this->supportsFiscalPeriodConfig()) {
            return $default;
        }

        $stmt = $this->conn->prepare('SELECT TOP 1 * FROM dbo.tblSbFiscalPeriodConfig WHERE FiscalYearID = :fy ORDER BY FiscalPeriodConfigID DESC');
        $stmt->execute([':fy' => $fiscalYearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($row === []) {
            return $default;
        }

        return array_merge($default, $row);
    }

    public function getFiscalPeriodLabels(int $fiscalYearId): array
    {
        $record = $this->getFiscalPeriodConfig($fiscalYearId);
        $labels = [
            'StartMonthNo' => (int) ($record['StartMonthNo'] ?? 1),
            'BP' => [],
            'OuterYear' => [],
        ];

        for ($i = 1; $i <= 12; $i++) {
            $labels['BP'][$i] = (string) ($record['BP' . $i . 'Label'] ?? ('BP' . $i));
        }
        for ($i = 1; $i <= 5; $i++) {
            $labels['OuterYear'][$i] = (string) ($record['OuterYear' . $i . 'Label'] ?? ('Outer Year ' . $i));
        }

        return $labels;
    }

    public function saveFiscalPeriodConfig(int $fiscalYearId, array $data): void
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }
        if (!$this->supportsFiscalPeriodConfig()) {
            throw new \RuntimeException('Fiscal period config table is not installed.');
        }

        $sql = "
            MERGE dbo.tblSbFiscalPeriodConfig AS target
            USING (SELECT :FiscalYearID AS FiscalYearID) AS source
            ON target.FiscalYearID = source.FiscalYearID
            WHEN MATCHED THEN
                UPDATE SET
                    StartMonthNo = :StartMonthNo,
                    BP1Label = :BP1Label,
                    BP2Label = :BP2Label,
                    BP3Label = :BP3Label,
                    BP4Label = :BP4Label,
                    BP5Label = :BP5Label,
                    BP6Label = :BP6Label,
                    BP7Label = :BP7Label,
                    BP8Label = :BP8Label,
                    BP9Label = :BP9Label,
                    BP10Label = :BP10Label,
                    BP11Label = :BP11Label,
                    BP12Label = :BP12Label,
                    OuterYear1Label = :OuterYear1Label,
                    OuterYear2Label = :OuterYear2Label,
                    OuterYear3Label = :OuterYear3Label,
                    OuterYear4Label = :OuterYear4Label,
                    OuterYear5Label = :OuterYear5Label,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
            WHEN NOT MATCHED THEN
                INSERT (
                    FiscalYearID,
                    StartMonthNo,
                    BP1Label,
                    BP2Label,
                    BP3Label,
                    BP4Label,
                    BP5Label,
                    BP6Label,
                    BP7Label,
                    BP8Label,
                    BP9Label,
                    BP10Label,
                    BP11Label,
                    BP12Label,
                    OuterYear1Label,
                    OuterYear2Label,
                    OuterYear3Label,
                    OuterYear4Label,
                    OuterYear5Label,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                ) VALUES (
                    :FiscalYearID,
                    :StartMonthNo,
                    :BP1Label,
                    :BP2Label,
                    :BP3Label,
                    :BP4Label,
                    :BP5Label,
                    :BP6Label,
                    :BP7Label,
                    :BP8Label,
                    :BP9Label,
                    :BP10Label,
                    :BP11Label,
                    :BP12Label,
                    :OuterYear1Label,
                    :OuterYear2Label,
                    :OuterYear3Label,
                    :OuterYear4Label,
                    :OuterYear5Label,
                    :ActiveFlag,
                    :CreatedBy,
                    SYSDATETIME()
                );
        ";

        $params = [
            ':FiscalYearID' => $fiscalYearId,
            ':StartMonthNo' => (int) ($data['StartMonthNo'] ?? 1),
            ':ActiveFlag' => (int) ($data['ActiveFlag'] ?? 1),
            ':CreatedBy' => (int) ($data['UserID'] ?? 1),
            ':UpdatedBy' => (int) ($data['UserID'] ?? 1),
        ];
        for ($i = 1; $i <= 12; $i++) {
            $params[':BP' . $i . 'Label'] = $data['BP' . $i . 'Label'] ?? null;
        }
        for ($i = 1; $i <= 5; $i++) {
            $params[':OuterYear' . $i . 'Label'] = $data['OuterYear' . $i . 'Label'] ?? null;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
    }

    public function buildDefaultPhasingProfile(int $fiscalYearId): array
    {
        $record = [
            'PhasingProfileID' => 0,
            'FiscalYearID' => $fiscalYearId,
            'ProfileCode' => '',
            'ProfileName' => '',
            'ProfileDescription' => null,
            'ActiveFlag' => 1,
        ];
        for ($i = 1; $i <= 12; $i++) {
            $record['BP' . $i . 'Weight'] = 1;
        }
        return $record;
    }

    public function listPhasingProfiles(int $fiscalYearId): array
    {
        if ($fiscalYearId <= 0 || !$this->supportsPhasingProfileConfig()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbPhasingProfile
            WHERE FiscalYearID = :fy
            ORDER BY ProfileName ASC, ProfileCode ASC
        ");
        $stmt->execute([':fy' => $fiscalYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listActivePhasingProfiles(int $fiscalYearId): array
    {
        $rows = [];
        foreach ($this->listPhasingProfiles($fiscalYearId) as $row) {
            if ((int) ($row['ActiveFlag'] ?? 0) !== 1) {
                continue;
            }
            $weights = [];
            for ($i = 1; $i <= 12; $i++) {
                $weights[$i] = (float) ($row['BP' . $i . 'Weight'] ?? 0);
            }
            $row['Weights'] = $weights;
            $rows[] = $row;
        }
        return $rows;
    }

    public function getPhasingProfile(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsPhasingProfileConfig()) {
            return null;
        }
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbPhasingProfile WHERE PhasingProfileID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function savePhasingProfile(int $fiscalYearId, array $data, ?int $id = null): int
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }
        if (!$this->supportsPhasingProfileConfig()) {
            throw new \RuntimeException('Phasing profile table is not installed.');
        }

        $params = [
            ':FiscalYearID' => $fiscalYearId,
            ':ProfileCode' => strtoupper(trim((string) ($data['ProfileCode'] ?? ''))),
            ':ProfileName' => trim((string) ($data['ProfileName'] ?? '')),
            ':ProfileDescription' => $data['ProfileDescription'] ?? null,
            ':ActiveFlag' => (int) ($data['ActiveFlag'] ?? 1),
            ':UserID' => (int) ($data['UserID'] ?? 1),
        ];
        for ($i = 1; $i <= 12; $i++) {
            $params[':BP' . $i . 'Weight'] = (float) ($data['BP' . $i . 'Weight'] ?? 0);
        }

        if ($params[':ProfileCode'] === '' || $params[':ProfileName'] === '') {
            throw new \InvalidArgumentException('Profile code and name are required.');
        }

        if ($id !== null && $id > 0) {
            $sql = "
                UPDATE dbo.tblSbPhasingProfile
                SET ProfileCode = :ProfileCode,
                    ProfileName = :ProfileName,
                    ProfileDescription = :ProfileDescription,
                    BP1Weight = :BP1Weight,
                    BP2Weight = :BP2Weight,
                    BP3Weight = :BP3Weight,
                    BP4Weight = :BP4Weight,
                    BP5Weight = :BP5Weight,
                    BP6Weight = :BP6Weight,
                    BP7Weight = :BP7Weight,
                    BP8Weight = :BP8Weight,
                    BP9Weight = :BP9Weight,
                    BP10Weight = :BP10Weight,
                    BP11Weight = :BP11Weight,
                    BP12Weight = :BP12Weight,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UserID,
                    UpdatedDate = SYSDATETIME()
                WHERE PhasingProfileID = :PhasingProfileID
                  AND FiscalYearID = :FiscalYearID
            ";
            $params[':PhasingProfileID'] = $id;
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        $sql = "
            INSERT INTO dbo.tblSbPhasingProfile (
                FiscalYearID, ProfileCode, ProfileName, ProfileDescription,
                BP1Weight, BP2Weight, BP3Weight, BP4Weight, BP5Weight, BP6Weight,
                BP7Weight, BP8Weight, BP9Weight, BP10Weight, BP11Weight, BP12Weight,
                ActiveFlag, CreatedBy, CreatedDate
            ) VALUES (
                :FiscalYearID, :ProfileCode, :ProfileName, :ProfileDescription,
                :BP1Weight, :BP2Weight, :BP3Weight, :BP4Weight, :BP5Weight, :BP6Weight,
                :BP7Weight, :BP8Weight, :BP9Weight, :BP10Weight, :BP11Weight, :BP12Weight,
                :ActiveFlag, :UserID, SYSDATETIME()
            )
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) $this->conn->lastInsertId();
    }

    public function deactivatePhasingProfile(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->supportsPhasingProfileConfig()) {
            return;
        }
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbPhasingProfile
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE PhasingProfileID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function getStrategicSegmentMappings(int $fiscalYearId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                c.StrategicDimensionCode,
                c.SegmentNo,
                s.SegmentName,
                s.CBMSDimension,
                c.Notes
            FROM dbo.tblSbSegmentConfig c
            LEFT JOIN dbo.tblSegments s
              ON TRY_CONVERT(INT, s.SegmentCode) = c.SegmentNo
            WHERE c.FiscalYearID = :fy
              AND c.ActiveFlag = 1
            ORDER BY c.StrategicDimensionCode ASC
        ");
        $stmt->execute([':fy' => $fiscalYearId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $mappings = [];
        foreach ($rows as $row) {
            $mappings[(string) $row['StrategicDimensionCode']] = $row;
        }

        return $mappings;
    }

    public function getStrategicSegmentDecisions(int $fiscalYearId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                c.StrategicDimensionCode,
                c.SegmentNo,
                s.SegmentName,
                s.CBMSDimension,
                c.Notes,
                c.ActiveFlag,
                c.StrategicSegmentConfigID
            FROM dbo.tblSbSegmentConfig c
            LEFT JOIN dbo.tblSegments s
              ON TRY_CONVERT(INT, s.SegmentCode) = c.SegmentNo
            WHERE c.FiscalYearID = :fy
            ORDER BY c.StrategicDimensionCode ASC, c.StrategicSegmentConfigID DESC
        ");
        $stmt->execute([':fy' => $fiscalYearId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $decisions = [];
        foreach ($rows as $row) {
            $code = (string) ($row['StrategicDimensionCode'] ?? '');
            if ($code === '' || isset($decisions[$code])) {
                continue;
            }

            $isMapped = (int) ($row['ActiveFlag'] ?? 0) === 1 && (int) ($row['SegmentNo'] ?? 0) > 0;
            $isExplicitNotMapped = !$isMapped
                && trim((string) ($row['Notes'] ?? '')) === self::SEGMENT_NOT_MAPPED_NOTE;

            $row['DecisionState'] = $isMapped
                ? 'MAPPED'
                : ($isExplicitNotMapped ? 'NOT_MAPPED' : 'UNSET');

            $decisions[$code] = $row;
        }

        return $decisions;
    }

    public function getStrategicSegmentMapping(int $fiscalYearId, string $dimensionCode): ?array
    {
        $dimensionCode = strtoupper(trim($dimensionCode));
        $all = $this->getStrategicSegmentMappings($fiscalYearId);
        return $all[$dimensionCode] ?? null;
    }

    public function saveStrategicSegmentMappings(int $fiscalYearId, array $mappings, int $userId): void
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }

        $allowed = [];
        foreach ($this->getStrategicSegmentDefinitions() as $definition) {
            $code = (string) ($definition['Code'] ?? '');
            $allowed[] = $code;
        }

        $segmentExists = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSegments
            WHERE TRY_CONVERT(INT, SegmentCode) = :segmentNo
        ");

        $upsert = $this->conn->prepare("
            MERGE dbo.tblSbSegmentConfig AS target
            USING (
                SELECT
                    :SourceFiscalYearID AS FiscalYearID,
                    :SourceStrategicDimensionCode AS StrategicDimensionCode
            ) AS source
            ON target.FiscalYearID = source.FiscalYearID
           AND target.StrategicDimensionCode = source.StrategicDimensionCode
            WHEN MATCHED THEN
                UPDATE SET
                    SegmentNo = :SegmentNo,
                    Notes = :Notes,
                    ActiveFlag = 1,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
            WHEN NOT MATCHED THEN
                INSERT (
                    FiscalYearID,
                    StrategicDimensionCode,
                    SegmentNo,
                    Notes,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :InsertFiscalYearID,
                    :InsertStrategicDimensionCode,
                    :InsertSegmentNo,
                    :InsertNotes,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                );
        ");

        $upsertInactive = $this->conn->prepare("
            MERGE dbo.tblSbSegmentConfig AS target
            USING (
                SELECT
                    :SourceFiscalYearID AS FiscalYearID,
                    :SourceStrategicDimensionCode AS StrategicDimensionCode
            ) AS source
            ON target.FiscalYearID = source.FiscalYearID
           AND target.StrategicDimensionCode = source.StrategicDimensionCode
            WHEN MATCHED THEN
                UPDATE SET
                    SegmentNo = :SegmentNo,
                    Notes = :Notes,
                    ActiveFlag = 0,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
            WHEN NOT MATCHED THEN
                INSERT (
                    FiscalYearID,
                    StrategicDimensionCode,
                    SegmentNo,
                    Notes,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :InsertFiscalYearID,
                    :InsertStrategicDimensionCode,
                    :InsertSegmentNo,
                    :InsertNotes,
                    0,
                    :CreatedBy,
                    SYSDATETIME()
                );
        ");

        $deactivate = $this->conn->prepare("
            UPDATE dbo.tblSbSegmentConfig
            SET ActiveFlag = 0,
                Notes = :Notes,
                UpdatedBy = :UpdatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE FiscalYearID = :FiscalYearID
              AND StrategicDimensionCode = :StrategicDimensionCode
        ");

        $startedTransaction = !$this->conn->inTransaction();
        if ($startedTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            foreach ($mappings as $dimensionCode => $row) {
                $dimensionCode = strtoupper(trim((string) $dimensionCode));
                if (!in_array($dimensionCode, $allowed, true)) {
                    continue;
                }

                $decision = strtoupper(trim((string) ($row['Decision'] ?? '')));
                $segmentNo = (int) ($row['SegmentNo'] ?? 0);
                if ($decision === '') {
                    $deactivate->execute([
                        ':Notes' => null,
                        ':UpdatedBy' => $userId,
                        ':FiscalYearID' => $fiscalYearId,
                        ':StrategicDimensionCode' => $dimensionCode,
                    ]);
                    continue;
                }

                if ($decision === 'NOT_MAPPED') {
                    $upsertInactive->execute([
                        ':SourceFiscalYearID' => $fiscalYearId,
                        ':SourceStrategicDimensionCode' => $dimensionCode,
                        ':SegmentNo' => null,
                        ':Notes' => self::SEGMENT_NOT_MAPPED_NOTE,
                        ':UpdatedBy' => $userId,
                        ':InsertFiscalYearID' => $fiscalYearId,
                        ':InsertStrategicDimensionCode' => $dimensionCode,
                        ':InsertSegmentNo' => null,
                        ':InsertNotes' => self::SEGMENT_NOT_MAPPED_NOTE,
                        ':CreatedBy' => $userId,
                    ]);
                    continue;
                }

                if ($decision !== 'MAPPED') {
                    throw new \RuntimeException('Unknown mapping decision for ' . $dimensionCode . '.');
                }

                if ($segmentNo <= 0) {
                    throw new \RuntimeException('Select a segment for ' . $dimensionCode . ' or choose Not mapped.');
                }

                $segmentExists->execute([':segmentNo' => $segmentNo]);
                if ((int) $segmentExists->fetchColumn() <= 0) {
                    throw new \RuntimeException('Configured segment ' . $segmentNo . ' was not found in tblSegments.');
                }

                $notes = $row['Notes'] ?? null;
                if (trim((string) $notes) === self::SEGMENT_NOT_MAPPED_NOTE) {
                    $notes = null;
                }

                $upsert->execute([
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceStrategicDimensionCode' => $dimensionCode,
                    ':SegmentNo' => $segmentNo,
                    ':Notes' => $notes,
                    ':InsertFiscalYearID' => $fiscalYearId,
                    ':InsertStrategicDimensionCode' => $dimensionCode,
                    ':InsertSegmentNo' => $segmentNo,
                    ':InsertNotes' => $notes,
                    ':UpdatedBy' => $userId,
                    ':CreatedBy' => $userId,
                ]);
            }

            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function listSegmentBackedPrograms(int $fiscalYearId, string $q = '', ?int $sectorId = null): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROGRAM');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return [];
        }

        $params = [
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ];
        $where = [
            'sv.FiscalYearID = :fy',
            'sv.SegmentNo = :segmentNo',
            'sv.ActiveFlag = 1',
        ];

        if ($q !== '') {
            $where[] = '(sv.SegmentCode LIKE :q OR sv.SegmentName LIKE :q OR sv.DataObjectCode LIKE :q OR doc.DataObjectName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($sectorId !== null && $sectorId > 0) {
            $where[] = 'p.SectorID = :sectorId';
            $params[':sectorId'] = $sectorId;
        }

        $sql = "
            SELECT
                sv.DataObjectCode AS OrgUnitDataObjectCode,
                COALESCE(doc.DataObjectName, CASE WHEN sv.DataObjectCode = '0' THEN 'Global' ELSE sv.DataObjectCode END) AS OrgUnitName,
                sv.SegmentCode AS ProgramCode,
                sv.SegmentName AS ProgramName,
                p.ProgramID,
                p.SectorID,
                sec.SectorName,
                p.ProgramDescription,
                p.ProgramManagerName,
                p.ActiveFlag,
                CASE WHEN p.ProgramID IS NULL THEN 0 ELSE 1 END AS OverlayConfiguredFlag
            FROM dbo.tblSegmentValues sv
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = sv.FiscalYearID
               AND doc.DataObjectCode = sv.DataObjectCode
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.SourceFiscalYearID = sv.FiscalYearID
               AND ou.SourceDataObjectCode = sv.DataObjectCode
            LEFT JOIN dbo.tblSbProgram p
                ON p.OrgUnitID = ou.OrgUnitID
               AND p.ProgramCode = sv.SegmentCode
            LEFT JOIN dbo.tblSbSector sec
                ON sec.SectorID = p.SectorID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY doc.DataObjectCode ASC, sv.SegmentCode ASC, sv.SegmentName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSegmentBackedProgramSource(int $fiscalYearId, string $dataObjectCode, string $programCode): ?array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROGRAM');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return null;
        }
        $resolved = $this->resolveSegmentValueSourceRow($fiscalYearId, $segmentNo, $dataObjectCode, $programCode);
        if ($resolved === null) {
            return null;
        }

        return [
            'FiscalYearID' => $fiscalYearId,
            'OrgUnitDataObjectCode' => $dataObjectCode,
            'OrgUnitName' => $this->resolveDataObjectDisplayName($fiscalYearId, $dataObjectCode),
            'SourceDataObjectCode' => (string) ($resolved['DataObjectCode'] ?? ''),
            'SourceDataObjectName' => $this->resolveDataObjectDisplayName($fiscalYearId, (string) ($resolved['DataObjectCode'] ?? '')),
            'ProgramCode' => (string) ($resolved['SegmentCode'] ?? ''),
            'ProgramName' => (string) ($resolved['SegmentName'] ?? ''),
            'SourceSegmentNo' => $segmentNo,
        ];
    }

    public function findProgramOverlayBySource(int $fiscalYearId, string $dataObjectCode, string $programCode): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT TOP 1 p.*, o.SourceDataObjectCode AS OrgUnitDataObjectCode
            FROM dbo.tblSbProgram p
            INNER JOIN dbo.tblSbOrgUnit o
                ON o.OrgUnitID = p.OrgUnitID
            WHERE (
                    (p.SourceFiscalYearID = :fySource
                     AND p.SourceDataObjectCode = :dataObjectCodeSource
                     AND p.SourceSegmentCode = :programCodeSource)
                 OR (o.SourceFiscalYearID = :fyOwner
                     AND o.SourceDataObjectCode = :dataObjectCodeOwner
                     AND p.ProgramCode = :programCodeOwner)
                  )
            ORDER BY CASE WHEN p.SourceDataObjectCode = :preferredSourceCode THEN 0 ELSE 1 END,
                     p.ProgramID ASC
        ");
        $stmt->execute([
            ':fySource' => $fiscalYearId,
            ':dataObjectCodeSource' => $dataObjectCode,
            ':programCodeSource' => $programCode,
            ':fyOwner' => $fiscalYearId,
            ':dataObjectCodeOwner' => $dataObjectCode,
            ':programCodeOwner' => $programCode,
            ':preferredSourceCode' => $dataObjectCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function programBelongsToDataObject(int $programId, int $fiscalYearId, string $dataObjectCode): bool
    {
        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [$dataObjectCode];
        }

        $params = [':programId' => $programId];
        $scopeSqlProgram = $this->buildScopeInListParams($scopeCodes, 'scopeProgram', $params);
        $scopeSqlOwner = $this->buildScopeInListParams($scopeCodes, 'scopeOwner', $params);
        $scopeSqlLinked = $this->buildScopeInListParams($scopeCodes, 'scopeLinked', $params);

        if ($this->supportsProgramOrgLinks()) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbProgram p
                INNER JOIN dbo.tblSbOrgUnit o
                    ON o.OrgUnitID = p.OrgUnitID
                LEFT JOIN dbo.tblSbProgramOrgLink pol
                    ON pol.ProgramID = p.ProgramID
                   AND pol.ActiveFlag = 1
                LEFT JOIN dbo.tblSbOrgUnit linked
                    ON linked.OrgUnitID = pol.OrgUnitID
                WHERE p.ProgramID = :programId
                  AND (
                        p.SourceDataObjectCode IN ({$scopeSqlProgram})
                     OR o.SourceDataObjectCode IN ({$scopeSqlOwner})
                     OR linked.SourceDataObjectCode IN ({$scopeSqlLinked})
                  )
            ");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn() > 0;
        }

        $params = [':programId' => $programId];
        $scopeSqlProgram = $this->buildScopeInListParams($scopeCodes, 'scopeProgramSolo', $params);
        $scopeSqlOwner = $this->buildScopeInListParams($scopeCodes, 'scopeOwnerSolo', $params);

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSbProgram p
            INNER JOIN dbo.tblSbOrgUnit o
                ON o.OrgUnitID = p.OrgUnitID
            WHERE p.ProgramID = :programId
              AND (
                    p.SourceDataObjectCode IN ({$scopeSqlProgram})
                 OR o.SourceDataObjectCode IN ({$scopeSqlOwner})
              )
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function listProgramLinkedOrgUnits(int $programId): array
    {
        if ($programId <= 0 || !$this->supportsProgramOrgLinks()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                pol.ProgramOrgLinkID,
                pol.ProgramID,
                pol.OrgUnitID,
                pol.LinkTypeCode,
                pol.ActiveFlag,
                ou.SourceDataObjectCode AS DataObjectCode,
                COALESCE(doc.DataObjectName, ou.OrgUnitName) AS DataObjectName
            FROM dbo.tblSbProgramOrgLink pol
            INNER JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = pol.OrgUnitID
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = ou.SourceFiscalYearID
               AND doc.DataObjectCode = ou.SourceDataObjectCode
            WHERE pol.ProgramID = :programId
              AND pol.ActiveFlag = 1
            ORDER BY ou.SourceDataObjectCode ASC, COALESCE(doc.DataObjectName, ou.OrgUnitName) ASC
        ");
        $stmt->execute([':programId' => $programId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function syncProgramLinkedOrgUnitsByDataObjectCodes(int $programId, int $fiscalYearId, array $dataObjectCodes, int $primaryOrgUnitId, int $userId): void
    {
        if ($programId <= 0 || $fiscalYearId <= 0 || !$this->supportsProgramOrgLinks()) {
            return;
        }

        $requestedOrgUnitIds = [];
        foreach ($dataObjectCodes as $code) {
            $dataObjectCode = trim((string)$code);
            if ($dataObjectCode === '') {
                continue;
            }

            $orgUnitId = $this->ensureOrgUnitFromDataObject($fiscalYearId, $dataObjectCode, $userId);
            if ($orgUnitId <= 0 || $orgUnitId === $primaryOrgUnitId) {
                continue;
            }

            $requestedOrgUnitIds[$orgUnitId] = true;
        }

        $existingStmt = $this->conn->prepare("
            SELECT ProgramOrgLinkID, OrgUnitID, ActiveFlag
            FROM dbo.tblSbProgramOrgLink
            WHERE ProgramID = :programId
        ");
        $existingStmt->execute([':programId' => $programId]);
        $existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $existingByOrgUnitId = [];
        foreach ($existingRows as $row) {
            $existingByOrgUnitId[(int)($row['OrgUnitID'] ?? 0)] = $row;
        }

        foreach (array_keys($requestedOrgUnitIds) as $orgUnitId) {
            $existing = $existingByOrgUnitId[$orgUnitId] ?? null;
            if ($existing !== null) {
                if ((int)($existing['ActiveFlag'] ?? 0) === 0) {
                    $reactivate = $this->conn->prepare("
                        UPDATE dbo.tblSbProgramOrgLink
                        SET ActiveFlag = 1,
                            UpdatedBy = :userId,
                            UpdatedDate = SYSDATETIME()
                        WHERE ProgramOrgLinkID = :id
                    ");
                    $reactivate->execute([
                        ':userId' => $userId,
                        ':id' => (int)$existing['ProgramOrgLinkID'],
                    ]);
                }
                continue;
            }

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbProgramOrgLink (
                    ProgramID,
                    OrgUnitID,
                    LinkTypeCode,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                ) VALUES (
                    :programId,
                    :orgUnitId,
                    N'PARTICIPATING',
                    1,
                    :userId,
                    SYSDATETIME()
                )
            ");
            $insert->execute([
                ':programId' => $programId,
                ':orgUnitId' => $orgUnitId,
                ':userId' => $userId,
            ]);
        }

        foreach ($existingRows as $row) {
            $orgUnitId = (int)($row['OrgUnitID'] ?? 0);
            if ($orgUnitId <= 0 || $orgUnitId === $primaryOrgUnitId || isset($requestedOrgUnitIds[$orgUnitId]) || (int)($row['ActiveFlag'] ?? 0) === 0) {
                continue;
            }

            $archive = $this->conn->prepare("
                UPDATE dbo.tblSbProgramOrgLink
                SET ActiveFlag = 0,
                    UpdatedBy = :userId,
                    UpdatedDate = SYSDATETIME()
                WHERE ProgramOrgLinkID = :id
            ");
            $archive->execute([
                ':userId' => $userId,
                ':id' => (int)$row['ProgramOrgLinkID'],
            ]);
        }
    }

    public function listSegmentBackedSubPrograms(int $fiscalYearId, string $q = '', ?int $programId = null): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SUBPROGRAM');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return [];
        }

        $params = [
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ];
        $where = [
            'sv.FiscalYearID = :fy',
            'sv.SegmentNo = :segmentNo',
            'sv.ActiveFlag = 1',
        ];

        if ($q !== '') {
            $where[] = '(sv.SegmentCode LIKE :q OR sv.SegmentName LIKE :q OR sv.DataObjectCode LIKE :q OR doc.DataObjectName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($programId !== null && $programId > 0) {
            $where[] = 'sp.ProgramID = :programId';
            $params[':programId'] = $programId;
        }

        $sql = "
            SELECT
                sv.DataObjectCode AS OrgUnitDataObjectCode,
                COALESCE(doc.DataObjectName, CASE WHEN sv.DataObjectCode = '0' THEN 'Global' ELSE sv.DataObjectCode END) AS OrgUnitName,
                sv.SegmentCode AS SubProgramCode,
                sv.SegmentName AS SubProgramName,
                ov.SubProgramID,
                ov.ProgramID,
                ov.ProgramName,
                ov.SubProgramDescription,
                ov.ActiveFlag,
                CASE WHEN ov.SubProgramID IS NULL THEN 0 ELSE 1 END AS OverlayConfiguredFlag
            FROM dbo.tblSegmentValues sv
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = sv.FiscalYearID
               AND doc.DataObjectCode = sv.DataObjectCode
            LEFT JOIN (
                SELECT
                    sp.SubProgramID,
                    sp.ProgramID,
                    sp.SubProgramCode,
                    sp.SubProgramDescription,
                    sp.ActiveFlag,
                    p.ProgramName,
                    o.SourceFiscalYearID,
                    o.SourceDataObjectCode
                FROM dbo.tblSbSubProgram sp
                INNER JOIN dbo.tblSbProgram p
                    ON p.ProgramID = sp.ProgramID
                INNER JOIN dbo.tblSbOrgUnit o
                    ON o.OrgUnitID = p.OrgUnitID
            ) ov
                ON ov.SourceFiscalYearID = sv.FiscalYearID
               AND ov.SourceDataObjectCode = sv.DataObjectCode
               AND ov.SubProgramCode = sv.SegmentCode
            WHERE " . implode(' AND ', $where) . "
            ORDER BY doc.DataObjectCode ASC, sv.SegmentCode ASC, sv.SegmentName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSegmentBackedSubProgramSource(int $fiscalYearId, string $dataObjectCode, string $subProgramCode): ?array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SUBPROGRAM');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return null;
        }
        $resolved = $this->resolveSegmentValueSourceRow($fiscalYearId, $segmentNo, $dataObjectCode, $subProgramCode);
        if ($resolved === null) {
            return null;
        }

        return [
            'FiscalYearID' => $fiscalYearId,
            'OrgUnitDataObjectCode' => $dataObjectCode,
            'OrgUnitName' => $this->resolveDataObjectDisplayName($fiscalYearId, $dataObjectCode),
            'SourceDataObjectCode' => (string) ($resolved['DataObjectCode'] ?? ''),
            'SourceDataObjectName' => $this->resolveDataObjectDisplayName($fiscalYearId, (string) ($resolved['DataObjectCode'] ?? '')),
            'SubProgramCode' => (string) ($resolved['SegmentCode'] ?? ''),
            'SubProgramName' => (string) ($resolved['SegmentName'] ?? ''),
            'SourceSegmentNo' => $segmentNo,
        ];
    }

    public function findSubProgramOverlayBySource(int $fiscalYearId, string $dataObjectCode, string $subProgramCode): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT TOP 1 sp.*
            FROM dbo.tblSbSubProgram sp
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = sp.ProgramID
            INNER JOIN dbo.tblSbOrgUnit o
                ON o.OrgUnitID = p.OrgUnitID
            WHERE (
                    (sp.SourceFiscalYearID = :fySource
                     AND sp.SourceDataObjectCode = :dataObjectCodeSource
                     AND sp.SourceSegmentCode = :subProgramCodeSource)
                 OR (o.SourceFiscalYearID = :fyOwner
                     AND o.SourceDataObjectCode = :dataObjectCodeOwner
                     AND sp.SubProgramCode = :subProgramCodeOwner)
                  )
            ORDER BY CASE WHEN sp.SourceDataObjectCode = :preferredSourceCode THEN 0 ELSE 1 END,
                     sp.SubProgramID ASC
        ");
        $stmt->execute([
            ':fySource' => $fiscalYearId,
            ':dataObjectCodeSource' => $dataObjectCode,
            ':subProgramCodeSource' => $subProgramCode,
            ':fyOwner' => $fiscalYearId,
            ':dataObjectCodeOwner' => $dataObjectCode,
            ':subProgramCodeOwner' => $subProgramCode,
            ':preferredSourceCode' => $dataObjectCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function importSubProgramOverlaysFromSegments(int $fiscalYearId, int $userId, string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }

        $subProgramMapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SUBPROGRAM');
        $subProgramSegmentNo = (int) ($subProgramMapping['SegmentNo'] ?? 0);
        if ($subProgramSegmentNo <= 0) {
            throw new \RuntimeException('Subprogram segment mapping is not configured for the active fiscal year.');
        }

        $programMapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROGRAM');
        $programSegmentNo = (int) ($programMapping['SegmentNo'] ?? 0);
        if ($programSegmentNo <= 0) {
            throw new \RuntimeException('Program segment mapping is not configured for the active fiscal year.');
        }

        $created = 0;
        $skipped = 0;
        $missingParentLink = 0;
        $missingParentOverlay = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $subProgramSegmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbSubProgram',
                    'key_column' => 'SubProgramID',
                    'dimension_code' => null,
                    'blockers' => [
                        ['table' => 'dbo.tblSbObjective', 'column' => 'SubProgramID', 'label' => 'objectives'],
                        ['table' => 'dbo.tblSbOutput', 'column' => 'SubProgramID', 'label' => 'outputs'],
                        ['table' => 'dbo.tblSbResourceEnvelope', 'column' => 'RestrictedSubProgramID', 'label' => 'resource envelope targets'],
                    ],
                ]
            );

            $parentNoColumn = $this->conn->query("SELECT COL_LENGTH('dbo.tblSegmentValues', 'ParentSegmentNo')")->fetchColumn();
            $parentCodeColumn = $this->conn->query("SELECT COL_LENGTH('dbo.tblSegmentValues', 'ParentSegmentCode')")->fetchColumn();
            if ($parentNoColumn === null || $parentCodeColumn === null) {
                throw new \RuntimeException('Subprogram import requires ParentSegmentNo and ParentSegmentCode on tblSegmentValues.');
            }

            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    sv.DataObjectCode,
                    sv.SegmentCode AS SubProgramCode,
                    sv.SegmentName AS SubProgramName,
                    sv.ParentSegmentNo,
                    sv.ParentSegmentCode
                FROM dbo.tblSegmentValues sv
                LEFT JOIN (
                    SELECT
                        sp.SubProgramID,
                        sp.SubProgramCode,
                        o.SourceFiscalYearID,
                        o.SourceDataObjectCode
                    FROM dbo.tblSbSubProgram sp
                    INNER JOIN dbo.tblSbProgram p
                        ON p.ProgramID = sp.ProgramID
                    INNER JOIN dbo.tblSbOrgUnit o
                        ON o.OrgUnitID = p.OrgUnitID
                ) existing
                    ON existing.SourceFiscalYearID = sv.FiscalYearID
                   AND existing.SourceDataObjectCode = sv.DataObjectCode
                   AND existing.SubProgramCode = sv.SegmentCode
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :subProgramSegmentNo
                  AND sv.ActiveFlag = 1
                  AND existing.SubProgramID IS NULL
                ORDER BY sv.DataObjectCode ASC, sv.SegmentCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':subProgramSegmentNo' => $subProgramSegmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbSubProgram (
                    ProgramID,
                    SubProgramCode,
                    SubProgramName,
                    SubProgramDescription,
                    SourceFiscalYearID,
                    SourceDataObjectCode,
                    SourceSegmentNo,
                    SourceSegmentCode,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :ProgramID,
                    :SubProgramCode,
                    :SubProgramName,
                    NULL,
                    :SourceFiscalYearID,
                    :SourceDataObjectCode,
                    :SourceSegmentNo,
                    :SourceSegmentCode,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                )
            ");

            foreach ($rows as $row) {
                $dataObjectCode = trim((string) ($row['DataObjectCode'] ?? ''));
                $subProgramCode = trim((string) ($row['SubProgramCode'] ?? ''));
                $subProgramName = trim((string) ($row['SubProgramName'] ?? ''));
                $parentSegmentCode = trim((string) ($row['ParentSegmentCode'] ?? ''));
                $parentSegmentNo = (int) ($row['ParentSegmentNo'] ?? 0);

                if ($dataObjectCode === '' || $subProgramCode === '' || $subProgramName === '') {
                    $skipped++;
                    continue;
                }

                if ($parentSegmentNo !== $programSegmentNo || $parentSegmentCode === '') {
                    $missingParentLink++;
                    continue;
                }

                $resolvedParentSource = $this->getSegmentBackedProgramSource($fiscalYearId, $dataObjectCode, $parentSegmentCode);
                $parentProgram = $resolvedParentSource !== null
                    ? $this->findProgramOverlayBySource($fiscalYearId, (string) ($resolvedParentSource['SourceDataObjectCode'] ?? $dataObjectCode), $parentSegmentCode)
                    : null;
                if ($parentProgram === null) {
                    $missingParentOverlay++;
                    continue;
                }

                if ($this->findSubProgramOverlayBySource($fiscalYearId, $dataObjectCode, $subProgramCode) !== null) {
                    $skipped++;
                    continue;
                }

                $insert->execute([
                    ':ProgramID' => (int) ($parentProgram['ProgramID'] ?? 0),
                    ':SubProgramCode' => $subProgramCode,
                    ':SubProgramName' => $subProgramName,
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceDataObjectCode' => $dataObjectCode,
                    ':SourceSegmentNo' => $subProgramSegmentNo,
                    ':SourceSegmentCode' => $subProgramCode,
                    ':CreatedBy' => $userId,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing_parent_link' => $missingParentLink,
            'missing_parent_overlay' => $missingParentOverlay,
            'reset' => $resetSummary,
        ];
    }

    public function listSegmentBackedEconomicItems(int $fiscalYearId, string $q = ''): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'ECONOMIC');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return [];
        }

        $params = [
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ];
        $where = [
            'sv.FiscalYearID = :fy',
            'sv.SegmentNo = :segmentNo',
            'sv.ActiveFlag = 1',
        ];

        if ($q !== '') {
            $where[] = '(sv.SegmentCode LIKE :q OR sv.SegmentName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "
            WITH src AS (
                SELECT DISTINCT
                    sv.SegmentCode AS EconomicCode,
                    sv.SegmentName AS EconomicName
                FROM dbo.tblSegmentValues sv
                WHERE " . implode(' AND ', $where) . "
            )
            SELECT
                src.EconomicCode,
                src.EconomicName,
                ei.EconomicItemID,
                ei.ParentEconomicItemID,
                parent.EconomicName AS ParentEconomicName,
                ei.EconomicLevel,
                ei.ActiveFlag,
                CASE WHEN ei.EconomicItemID IS NULL THEN 0 ELSE 1 END AS OverlayConfiguredFlag
            FROM src
            LEFT JOIN dbo.tblSbEconomicItem ei
                ON ei.EconomicCode = src.EconomicCode
            LEFT JOIN dbo.tblSbEconomicItem parent
                ON parent.EconomicItemID = ei.ParentEconomicItemID
            ORDER BY src.EconomicCode ASC, src.EconomicName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSegmentBackedProjects(int $fiscalYearId, string $q = '', string $scope = 'current_imported'): array
    {
        if (!$this->tableExists('dbo.tblSbProject')) {
            return [];
        }

        $scope = strtolower(trim($scope));
        if (!in_array($scope, ['current_imported', 'all'], true)) {
            $scope = 'current_imported';
        }

        $params = [];
        $where = [];
        if ($q !== '') {
            $where[] = '(
                p.ProjectCode LIKE :q
                OR p.ProjectName LIKE :q
                OR COALESCE(p.ProjectDescription, N\'\') LIKE :q
                OR COALESCE(leadOu.OrgUnitName, N\'\') LIKE :q
            )';
            $params[':q'] = '%' . $q . '%';
        }

        if ($this->supportsProjectSourceMaps()) {
            $sql = "
                SELECT
                    p.ProjectID,
                    p.ProjectCode,
                    p.ProjectName,
                    p.ProjectDescription,
                    p.ProjectTypeCode,
                    p.LifecycleStatusCode,
                    p.PriorityCode,
                    p.ActiveFlag,
                    leadOu.OrgUnitName AS LeadOrgUnitName,
                    ISNULL(mapCounts.SourceMappingCount, 0) AS SourceMappingCount,
                    ISNULL(programCounts.ProgramLinkCount, 0) AS ProgramLinkCount,
                    ISNULL(objectiveCounts.ObjectiveLinkCount, 0) AS ObjectiveLinkCount,
                    ISNULL(submissionCounts.FundingSubmissionCount, 0) AS FundingSubmissionCount
                FROM dbo.tblSbProject p
                LEFT JOIN dbo.tblSbOrgUnit leadOu
                    ON leadOu.OrgUnitID = p.LeadOrgUnitID
                LEFT JOIN (
                    SELECT ProjectID, COUNT(*) AS SourceMappingCount
                    FROM dbo.tblSbProjectSourceMap
                    WHERE ActiveFlag = 1
                    GROUP BY ProjectID
                ) mapCounts
                    ON mapCounts.ProjectID = p.ProjectID
                LEFT JOIN (
                    SELECT ProjectID, COUNT(*) AS ProgramLinkCount
                    FROM dbo.tblSbProjectProgramLink
                    WHERE ActiveFlag = 1
                    GROUP BY ProjectID
                ) programCounts
                    ON programCounts.ProjectID = p.ProjectID
                LEFT JOIN (
                    SELECT ProjectID, COUNT(*) AS ObjectiveLinkCount
                    FROM dbo.tblSbProjectObjectiveLink
                    WHERE ActiveFlag = 1
                    GROUP BY ProjectID
                ) objectiveCounts
                    ON objectiveCounts.ProjectID = p.ProjectID
                LEFT JOIN (
                    SELECT ProjectID, COUNT(*) AS FundingSubmissionCount
                    FROM dbo.tblSbFundingSubmissionLine
                    WHERE ActiveFlag = 1
                      AND ProjectID IS NOT NULL
                    GROUP BY ProjectID
                ) submissionCounts
                    ON submissionCounts.ProjectID = p.ProjectID
            ";

            if ($scope === 'current_imported' && $fiscalYearId > 0) {
                $sql .= "
                    INNER JOIN (
                        SELECT DISTINCT ProjectID
                        FROM dbo.tblSbProjectSourceMap
                        WHERE FiscalYearID = :scopeFiscalYearId
                ";
                $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROJECT');
                $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
                if ($segmentNo > 0) {
                    $sql .= "
                          AND SourceSegmentNo = :scopeSegmentNo
                    ";
                    $params[':scopeSegmentNo'] = $segmentNo;
                }
                $sql .= "
                          AND ActiveFlag = 1
                    ) currentScopeMap
                        ON currentScopeMap.ProjectID = p.ProjectID
                ";
                $params[':scopeFiscalYearId'] = $fiscalYearId;
            }
        } else {
            $sql = "
                SELECT
                    p.ProjectID,
                    p.ProjectCode,
                    p.ProjectName,
                    p.ProjectDescription,
                    CAST(NULL AS NVARCHAR(30)) AS ProjectTypeCode,
                    CAST(NULL AS NVARCHAR(30)) AS LifecycleStatusCode,
                    CAST(NULL AS NVARCHAR(20)) AS PriorityCode,
                    p.ActiveFlag,
                    CAST(NULL AS NVARCHAR(200)) AS LeadOrgUnitName,
                    CASE WHEN p.SourceSegmentCode IS NULL THEN 0 ELSE 1 END AS SourceMappingCount,
                    0 AS ProgramLinkCount,
                    0 AS ObjectiveLinkCount,
                    ISNULL(submissionCounts.FundingSubmissionCount, 0) AS FundingSubmissionCount
                FROM dbo.tblSbProject p
                LEFT JOIN (
                    SELECT ProjectID, COUNT(*) AS FundingSubmissionCount
                    FROM dbo.tblSbFundingSubmissionLine
                    WHERE ActiveFlag = 1
                      AND ProjectID IS NOT NULL
                    GROUP BY ProjectID
                ) submissionCounts
                    ON submissionCounts.ProjectID = p.ProjectID
            ";

            if ($scope === 'current_imported' && $fiscalYearId > 0) {
                $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROJECT');
                $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
                $where[] = 'p.SourceFiscalYearID = :scopeFiscalYearId';
                $params[':scopeFiscalYearId'] = $fiscalYearId;
                if ($segmentNo > 0) {
                    $where[] = 'p.SourceSegmentNo = :scopeSegmentNo';
                    $params[':scopeSegmentNo'] = $segmentNo;
                }
            }
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= "
            ORDER BY CASE WHEN COALESCE(p.ProjectCode, N'') = N'' THEN 1 ELSE 0 END,
                     p.ProjectCode ASC,
                     p.ProjectName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSegmentBackedProjectSource(int $fiscalYearId, string $dataObjectCode, string $projectCode): ?array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROJECT');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1
                sv.DataObjectCode,
                sv.SegmentCode AS ProjectCode,
                sv.SegmentName AS ProjectName
            FROM dbo.tblSegmentValues sv
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.DataObjectCode = :dataObjectCode
              AND sv.SegmentCode = :projectCode
              AND sv.ActiveFlag = 1
            ORDER BY sv.SegmentName ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
            ':dataObjectCode' => $dataObjectCode,
            ':projectCode' => $projectCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getProject(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbProject')) {
            return null;
        }

        $select = 'p.*';
        $joins = '';
        if ($this->tableColumnExists('dbo.tblSbProject', 'LeadOrgUnitID')) {
            $select .= ', leadOu.OrgUnitName AS LeadOrgUnitName, leadOu.SourceDataObjectCode AS LeadDataObjectCode';
            $joins .= ' LEFT JOIN dbo.tblSbOrgUnit leadOu ON leadOu.OrgUnitID = p.LeadOrgUnitID';
        } else {
            $select .= ', CAST(NULL AS NVARCHAR(200)) AS LeadOrgUnitName, CAST(NULL AS NVARCHAR(50)) AS LeadDataObjectCode';
        }
        if ($this->tableColumnExists('dbo.tblSbProject', 'SponsorOrgUnitID')) {
            $select .= ', sponsorOu.OrgUnitName AS SponsorOrgUnitName, sponsorOu.SourceDataObjectCode AS SponsorDataObjectCode';
            $joins .= ' LEFT JOIN dbo.tblSbOrgUnit sponsorOu ON sponsorOu.OrgUnitID = p.SponsorOrgUnitID';
        } else {
            $select .= ', CAST(NULL AS NVARCHAR(200)) AS SponsorOrgUnitName, CAST(NULL AS NVARCHAR(50)) AS SponsorDataObjectCode';
        }

        $stmt = $this->conn->prepare("SELECT {$select} FROM dbo.tblSbProject p{$joins} WHERE p.ProjectID = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findProjectOverlayBySource(int $fiscalYearId, string $dataObjectCode, string $projectCode): ?array
    {
        if ($fiscalYearId <= 0 || $projectCode === '' || !$this->tableExists('dbo.tblSbProject')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbProject
            WHERE SourceFiscalYearID = :fy
              AND COALESCE(SourceDataObjectCode, N'') = :dataObjectCode
              AND SourceSegmentCode = :sourceSegmentCode
              AND ActiveFlag = 1
            ORDER BY ProjectID ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':dataObjectCode' => $dataObjectCode,
            ':sourceSegmentCode' => $projectCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveProject(array $data, ?int $id = null): int
    {
        if (!$this->tableExists('dbo.tblSbProject')) {
            throw new \RuntimeException('Project table is not installed.');
        }

        $fields = [
            'ProjectCode' => $data['ProjectCode'],
            'ProjectName' => $data['ProjectName'],
            'ProjectDescription' => $data['ProjectDescription'],
            'ActiveFlag' => $data['ActiveFlag'],
        ];

        $optionalFieldMap = [
            'ExternalReference' => 'ExternalReference',
            'ProjectTypeCode' => 'ProjectTypeCode',
            'ProjectCategoryCode' => 'ProjectCategoryCode',
            'LifecycleStatusCode' => 'LifecycleStatusCode',
            'PriorityCode' => 'PriorityCode',
            'LeadOrgUnitID' => 'LeadOrgUnitID',
            'SponsorOrgUnitID' => 'SponsorOrgUnitID',
            'ProjectManagerName' => 'ProjectManagerName',
            'CapitalFlag' => 'CapitalFlag',
            'ProcurementRequiredFlag' => 'ProcurementRequiredFlag',
            'StartDate' => 'StartDate',
            'EndDate' => 'EndDate',
            'EstimatedTotalCost' => 'EstimatedTotalCost',
            'ApprovedTotalCost' => 'ApprovedTotalCost',
            'FundingGapAmount' => 'FundingGapAmount',
            'CurrencyCode' => 'CurrencyCode',
            'FundingStatusCode' => 'FundingStatusCode',
            'RiskRatingCode' => 'RiskRatingCode',
            'LocationCode' => 'LocationCode',
            'LocationDescription' => 'LocationDescription',
            'SourceFiscalYearID' => 'SourceFiscalYearID',
            'SourceDataObjectCode' => 'SourceDataObjectCode',
            'SourceSegmentNo' => 'SourceSegmentNo',
            'SourceSegmentCode' => 'SourceSegmentCode',
        ];

        foreach ($optionalFieldMap as $column => $key) {
            if ($this->tableColumnExists('dbo.tblSbProject', $column)) {
                $fields[$column] = $data[$key] ?? null;
            }
        }

        if ($id !== null && $id > 0) {
            $set = [];
            $params = [];
            foreach ($fields as $column => $value) {
                $set[] = $column . ' = :' . $column;
                $params[':' . $column] = $value;
            }
            $params[':UpdatedBy'] = $data['UserID'];
            $params[':ProjectID'] = $id;

            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbProject
                SET " . implode(",\n                    ", $set) . ",
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ProjectID = :ProjectID
            ");
            $stmt->execute($params);
            return $id;
        }

        $columns = array_keys($fields);
        $params = [];
        foreach ($fields as $column => $value) {
            $params[':' . $column] = $value;
        }
        $params[':CreatedBy'] = $data['UserID'];

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbProject (
                " . implode(",\n                ", $columns) . ",
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :" . implode(",\n                :", $columns) . ",
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute($params);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateProject(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblSbProject')) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbProject
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ProjectID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function importProjectOverlaysFromSegments(int $fiscalYearId, int $userId, string $resetMode = 'none'): array
    {
        if (!$this->tableExists('dbo.tblSbProject')) {
            throw new \RuntimeException('Project table is not installed. Run create_tblSbProject.sql first.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROJECT');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return ['created' => 0, 'skipped' => 0];
        }

        $resetMode = $this->normalizeImportResetMode($resetMode);

        $stmt = $this->conn->prepare("
            SELECT DISTINCT
                sv.DataObjectCode,
                sv.SegmentCode AS ProjectCode,
                sv.SegmentName AS ProjectName
            FROM dbo.tblSegmentValues sv
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.ActiveFlag = 1
            ORDER BY sv.SegmentCode ASC, sv.SegmentName ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $created = 0;
        $skipped = 0;
        $matched = 0;
        $resetSummary = [
            'mode' => $resetMode,
            'source_maps_cleared' => 0,
            'projects_archived' => 0,
            'projects_deleted' => 0,
            'projects_preserved' => 0,
            'blocked' => 0,
        ];

        $this->conn->beginTransaction();
        try {
            if ($resetMode !== 'none') {
                $resetSummary = $this->resetProjectImportsForFiscalYear($fiscalYearId, $segmentNo, $userId, $resetMode);
            }

            foreach ($rows as $row) {
                $dataObjectCode = trim((string) ($row['DataObjectCode'] ?? ''));
                $projectCode = trim((string) ($row['ProjectCode'] ?? ''));
                $projectName = trim((string) ($row['ProjectName'] ?? ''));

                if ($projectCode === '' || $projectName === '') {
                    $skipped++;
                    continue;
                }

                if ($this->findProjectOverlayBySource($fiscalYearId, $dataObjectCode, $projectCode) !== null) {
                    $skipped++;
                    continue;
                }

                $project = $this->findActiveProjectByCodeOrName($projectCode, $projectName);
                $projectId = (int) ($project['ProjectID'] ?? 0);
                if ($projectId <= 0) {
                    $projectId = $this->saveProject([
                        'ProjectCode' => $projectCode,
                        'ProjectName' => $projectName,
                        'ProjectDescription' => null,
                        'ProjectTypeCode' => $this->tableColumnExists('dbo.tblSbProject', 'ProjectTypeCode') ? 'OTHER' : null,
                        'LifecycleStatusCode' => $this->tableColumnExists('dbo.tblSbProject', 'LifecycleStatusCode') ? 'PIPELINE' : null,
                        'PriorityCode' => $this->tableColumnExists('dbo.tblSbProject', 'PriorityCode') ? 'MEDIUM' : null,
                        'CapitalFlag' => 0,
                        'ProcurementRequiredFlag' => 0,
                        'SourceFiscalYearID' => $fiscalYearId,
                        'SourceDataObjectCode' => $dataObjectCode,
                        'SourceSegmentNo' => $segmentNo,
                        'SourceSegmentCode' => $projectCode,
                        'ActiveFlag' => 1,
                        'UserID' => $userId,
                    ], null);
                    $created++;
                }

                if ($this->supportsProjectSourceMaps()) {
                    $this->upsertProjectSourceMapping([
                        'ProjectID' => $projectId,
                        'FiscalYearID' => $fiscalYearId,
                        'DataObjectCode' => $dataObjectCode !== '' ? $dataObjectCode : null,
                        'SourceSegmentNo' => $segmentNo,
                        'SourceSegmentCode' => $projectCode,
                        'SourceSegmentName' => $projectName !== '' ? $projectName : null,
                        'SourceSystemCode' => 'SEGMENT',
                        'IsPrimaryFlag' => 1,
                        'ActiveFlag' => 1,
                        'UserID' => $userId,
                    ]);
                }
                if ($project !== null) {
                    $matched++;
                }
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'matched' => $matched,
            'reset' => $resetSummary,
        ];
    }

    public function getSegmentBackedEconomicSource(int $fiscalYearId, string $economicCode): ?array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'ECONOMIC');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1
                sv.SegmentCode AS EconomicCode,
                sv.SegmentName AS EconomicName
            FROM dbo.tblSegmentValues sv
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.SegmentCode = :economicCode
              AND sv.ActiveFlag = 1
            ORDER BY sv.SegmentName ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
            ':economicCode' => $economicCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listSegmentBackedFundingTypes(int $fiscalYearId, string $q = ''): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_TYPE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            return [];
        }

        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            $rows = $this->listFundingTypes($q);
            foreach ($rows as &$row) {
                $row['SourceSegmentCode'] = $row['FundingTypeCode'] ?? null;
                $row['OverlayConfiguredFlag'] = 1;
            }
            unset($row);
            return $rows;
        }

        $params = [
            ':fy' => $fiscalYearId,
            ':fyJoin' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ];
        $where = [
            'sv.FiscalYearID = :fy',
            'sv.SegmentNo = :segmentNo',
            'sv.ActiveFlag = 1',
        ];

        if ($q !== '') {
            $where[] = '(sv.SegmentCode LIKE :q OR sv.SegmentName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "
            WITH src AS (
                SELECT DISTINCT
                    sv.SegmentCode AS SourceSegmentCode,
                    sv.SegmentName AS FundingTypeName
                FROM dbo.tblSegmentValues sv
                WHERE " . implode(' AND ', $where) . "
            )
            SELECT
                src.SourceSegmentCode,
                src.FundingTypeName,
                ft.FundingTypeID,
                ft.FundingTypeCode,
                ft.FundingTypeDescription,
                ft.ActiveFlag,
                CASE WHEN ft.FundingTypeID IS NULL THEN 0 ELSE 1 END AS OverlayConfiguredFlag
            FROM src
            LEFT JOIN dbo.tblSbFundingType ft
                ON ft.SourceFiscalYearID = :fyJoin
               AND ft.SourceSegmentCode = src.SourceSegmentCode
            ORDER BY src.SourceSegmentCode ASC, src.FundingTypeName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSegmentBackedFundingType(int $fiscalYearId, string $sourceSegmentCode): ?array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_TYPE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1
                sv.SegmentCode AS SourceSegmentCode,
                sv.SegmentName AS FundingTypeName
            FROM dbo.tblSegmentValues sv
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.SegmentCode = :sourceSegmentCode
              AND sv.ActiveFlag = 1
            ORDER BY sv.SegmentName ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
            ':sourceSegmentCode' => $sourceSegmentCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listFundingTypeOrphanOverlays(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblSbFundingType') || $fiscalYearId <= 0) {
            return [];
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_TYPE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                ft.FundingTypeID,
                ft.FundingTypeCode,
                ft.FundingTypeName,
                ft.FundingTypeDescription,
                ft.SourceFiscalYearID,
                ft.SourceSegmentNo,
                ft.SourceSegmentCode,
                ft.ActiveFlag,
                CASE
                    WHEN ft.SourceSegmentNo IS NULL OR ft.SourceSegmentCode IS NULL OR LTRIM(RTRIM(ft.SourceSegmentCode)) = '' THEN 'No source link'
                    WHEN ft.SourceSegmentNo <> :segmentNoReason THEN CONCAT('Linked to old segment ', ft.SourceSegmentNo)
                    WHEN sv.SegmentValueID IS NULL THEN 'Source code not found in current mapped segment'
                    ELSE 'Other'
                END AS OrphanReason
            FROM dbo.tblSbFundingType ft
            LEFT JOIN dbo.tblSegmentValues sv
                ON sv.FiscalYearID = :fyJoin
               AND sv.SegmentNo = :segmentNoJoin
               AND sv.SegmentCode = ft.SourceSegmentCode
               AND sv.ActiveFlag = 1
            WHERE ft.ActiveFlag = 1
              AND (
                    ft.SourceFiscalYearID = :fyFilter
                    OR (ft.SourceFiscalYearID IS NULL AND ft.SourceSegmentCode IS NOT NULL)
                  )
              AND (
                    ft.SourceSegmentNo IS NULL
                    OR ft.SourceSegmentCode IS NULL
                    OR LTRIM(RTRIM(ft.SourceSegmentCode)) = ''
                    OR ft.SourceSegmentNo <> :segmentNoFilter
                    OR sv.SegmentValueID IS NULL
                  )
            ORDER BY ft.FundingTypeName ASC, ft.FundingTypeCode ASC, ft.FundingTypeID ASC
        ");
        $stmt->execute([
            ':fyJoin' => $fiscalYearId,
            ':segmentNoJoin' => $segmentNo,
            ':fyFilter' => $fiscalYearId,
            ':segmentNoFilter' => $segmentNo,
            ':segmentNoReason' => $segmentNo,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSegmentBackedFundingSources(int $fiscalYearId, string $q = ''): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_SOURCE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return [];
        }

        if (!$this->hasFundingSourceSourceTrackingColumns()) {
            $params = [
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ];
            $where = [
                'sv.FiscalYearID = :fy',
                'sv.SegmentNo = :segmentNo',
                'sv.ActiveFlag = 1',
            ];

            if ($q !== '') {
                $where[] = '(sv.SegmentCode LIKE :q OR sv.SegmentName LIKE :q)';
                $params[':q'] = '%' . $q . '%';
            }

            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    sv.SegmentCode AS SourceSegmentCode,
                    sv.SegmentName AS FundingSourceName,
                    CAST(NULL AS INT) AS FundingSourceID,
                    CAST(NULL AS NVARCHAR(20)) AS FundingTypeCode,
                    CAST(NULL AS NVARCHAR(200)) AS DonorName,
                    CAST(NULL AS NVARCHAR(MAX)) AS ConditionsText,
                    CAST(NULL AS BIT) AS ActiveFlag,
                    CAST(0 AS INT) AS OverlayConfiguredFlag
                FROM dbo.tblSegmentValues sv
                WHERE " . implode(' AND ', $where) . "
                ORDER BY sv.SegmentCode ASC, sv.SegmentName ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $params = [
            ':fy' => $fiscalYearId,
            ':fyJoin' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ];
        $where = [
            'sv.FiscalYearID = :fy',
            'sv.SegmentNo = :segmentNo',
            'sv.ActiveFlag = 1',
        ];

        if ($q !== '') {
            $where[] = '(sv.SegmentCode LIKE :q OR sv.SegmentName LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = "
            WITH src AS (
                SELECT DISTINCT
                    sv.SegmentCode AS SourceSegmentCode,
                    sv.SegmentName AS FundingSourceName
                FROM dbo.tblSegmentValues sv
                WHERE " . implode(' AND ', $where) . "
            )
            SELECT
                src.SourceSegmentCode,
                src.FundingSourceName,
                fs.FundingSourceID,
                fs.FundingTypeCode,
                fs.DonorName,
                fs.ConditionsText,
                fs.ActiveFlag,
                CASE WHEN fs.FundingSourceID IS NULL THEN 0 ELSE 1 END AS OverlayConfiguredFlag
            FROM src
            LEFT JOIN dbo.tblSbFundingSource fs
                ON fs.SourceFiscalYearID = :fyJoin
               AND fs.SourceSegmentCode = src.SourceSegmentCode
            ORDER BY src.SourceSegmentCode ASC, src.FundingSourceName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSegmentBackedFundingSource(int $fiscalYearId, string $sourceSegmentCode): ?array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_SOURCE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1
                sv.SegmentCode AS SourceSegmentCode,
                sv.SegmentName AS FundingSourceName
            FROM dbo.tblSegmentValues sv
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.SegmentCode = :sourceSegmentCode
              AND sv.ActiveFlag = 1
            ORDER BY sv.SegmentName ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
            ':sourceSegmentCode' => $sourceSegmentCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function hasFundingSourceSourceTrackingColumns(): bool
    {
        return $this->tableColumnExists('dbo.tblSbFundingSource', 'SourceFiscalYearID')
            && $this->tableColumnExists('dbo.tblSbFundingSource', 'SourceSegmentCode');
    }

    public function importProgramOverlaysFromSegments(int $fiscalYearId, int $sectorId, int $userId, string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROGRAM');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Program segment mapping is not configured for the active fiscal year.');
        }

        $sectorMapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SECTOR');
        $sectorSegmentNo = (int) ($sectorMapping['SegmentNo'] ?? 0);
        $canAutoAssignSector = $this->canAutoAssignProgramSectors($fiscalYearId);

        if (!$canAutoAssignSector && $sectorId <= 0) {
            throw new \InvalidArgumentException('Sector is required when sector auto-assignment is not available.');
        }

        if ($sectorId > 0 && $this->getSector($sectorId) === null) {
            throw new \RuntimeException('Selected sector was not found.');
        }

        $selectColumns = [
            'sv.DataObjectCode',
            'sv.SegmentCode AS ProgramCode',
            'sv.SegmentName AS ProgramName',
        ];
        if ($this->hasParentSegmentColumns()) {
            $selectColumns[] = 'sv.ParentSegmentNo';
            $selectColumns[] = 'sv.ParentSegmentCode';
        }

        $created = 0;
        $skipped = 0;
        $autoAssigned = 0;
        $manualAssigned = 0;
        $missingSectorLink = 0;
        $missingSectorOverlay = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $segmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbProgram',
                    'key_column' => 'ProgramID',
                    'dimension_code' => 'PROGRAM',
                    'blockers' => [
                        ['table' => 'dbo.tblSbSubProgram', 'column' => 'ProgramID', 'label' => 'subprograms'],
                        ['table' => 'dbo.tblSbObjective', 'column' => 'ProgramID', 'label' => 'objectives'],
                        ['table' => 'dbo.tblSbOutput', 'column' => 'ProgramID', 'label' => 'outputs'],
                        ['table' => 'dbo.tblSbNarrative', 'column' => 'ProgramID', 'label' => 'narratives'],
                        ['table' => 'dbo.tblSbProgramRisk', 'column' => 'ProgramID', 'label' => 'program risks'],
                        ['table' => 'dbo.tblSbProjectProgramLink', 'column' => 'ProgramID', 'label' => 'project links'],
                        ['table' => 'dbo.tblSbResourceEnvelope', 'column' => 'RestrictedProgramID', 'label' => 'resource envelope targets'],
                    ],
                    'cleanup_tables' => [
                        ['table' => 'dbo.tblSbProgramOrgLink', 'column' => 'ProgramID'],
                    ],
                ]
            );

            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    " . implode(",\n                ", $selectColumns) . "
                FROM dbo.tblSegmentValues sv
                LEFT JOIN dbo.tblSbOrgUnit ou
                    ON ou.SourceFiscalYearID = sv.FiscalYearID
                   AND ou.SourceDataObjectCode = sv.DataObjectCode
                LEFT JOIN dbo.tblSbProgram p
                    ON p.OrgUnitID = ou.OrgUnitID
                   AND p.ProgramCode = sv.SegmentCode
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
                  AND p.ProgramID IS NULL
                ORDER BY sv.DataObjectCode ASC, sv.SegmentCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbProgram (
                    OrgUnitID,
                    SectorID,
                    ProgramCode,
                    ProgramName,
                    ProgramDescription,
                    ProgramManagerName,
                    SourceFiscalYearID,
                    SourceDataObjectCode,
                    SourceSegmentNo,
                    SourceSegmentCode,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :OrgUnitID,
                    :SectorID,
                    :ProgramCode,
                    :ProgramName,
                    NULL,
                    NULL,
                    :SourceFiscalYearID,
                    :SourceDataObjectCode,
                    :SourceSegmentNo,
                    :SourceSegmentCode,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                )
            ");

            foreach ($rows as $row) {
                $dataObjectCode = trim((string) ($row['DataObjectCode'] ?? ''));
                $programCode = trim((string) ($row['ProgramCode'] ?? ''));
                $programName = trim((string) ($row['ProgramName'] ?? ''));
                $resolvedSectorId = 0;

                if ($dataObjectCode === '' || $programCode === '' || $programName === '') {
                    $skipped++;
                    continue;
                }

                $orgUnitId = $this->ensureOrgUnitFromDataObject($fiscalYearId, $dataObjectCode, $userId);
                if ($this->findProgramOverlayBySource($fiscalYearId, $dataObjectCode, $programCode) !== null) {
                    $skipped++;
                    continue;
                }

                if ($canAutoAssignSector) {
                    $parentSegmentNo = (int) ($row['ParentSegmentNo'] ?? 0);
                    $parentSegmentCode = trim((string) ($row['ParentSegmentCode'] ?? ''));
                    $resolution = $this->resolveProgramImportSector(
                        $fiscalYearId,
                        $dataObjectCode,
                        $parentSegmentNo,
                        $parentSegmentCode
                    );

                    if (!empty($resolution['sector'])) {
                        $resolvedSectorId = (int) (($resolution['sector']['SectorID'] ?? 0));
                        $autoAssigned++;
                    } elseif ($sectorId > 0) {
                        $resolvedSectorId = $sectorId;
                        $manualAssigned++;
                        if (($resolution['reason'] ?? '') === 'missing_parent_overlay') {
                            $missingSectorOverlay++;
                        } else {
                            $missingSectorLink++;
                        }
                    } else {
                        if (($resolution['reason'] ?? '') === 'missing_parent_overlay') {
                            $missingSectorOverlay++;
                        } else {
                            $missingSectorLink++;
                        }
                        continue;
                    }
                } else {
                    $resolvedSectorId = $sectorId;
                    $manualAssigned++;
                }

                if ($resolvedSectorId <= 0) {
                    $skipped++;
                    continue;
                }

                $insert->execute([
                    ':OrgUnitID' => $orgUnitId,
                    ':SectorID' => $resolvedSectorId,
                    ':ProgramCode' => $programCode,
                    ':ProgramName' => $programName,
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceDataObjectCode' => $dataObjectCode,
                    ':SourceSegmentNo' => $segmentNo,
                    ':SourceSegmentCode' => $programCode,
                    ':CreatedBy' => $userId,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'auto_assigned' => $autoAssigned,
            'manual_assigned' => $manualAssigned,
            'missing_sector_link' => $missingSectorLink,
            'missing_sector_overlay' => $missingSectorOverlay,
            'reset' => $resetSummary,
        ];
    }

    public function importEconomicItemOverlaysFromSegments(int $fiscalYearId, int $userId, string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'ECONOMIC');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Economic segment mapping is not configured for the active fiscal year.');
        }

        $created = 0;
        $skipped = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $segmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbEconomicItem',
                    'key_column' => 'EconomicItemID',
                    'dimension_code' => null,
                    'blockers' => [
                        ['table' => 'dbo.tblSbActivityBudget', 'column' => 'EconomicItemID', 'label' => 'activity budgets'],
                        ['table' => 'dbo.tblSbResourceEnvelope', 'column' => 'RestrictedEconomicItemID', 'label' => 'resource envelope targets'],
                    ],
                ]
            );

            $stmt = $this->conn->prepare("
                WITH src AS (
                    SELECT DISTINCT
                        sv.SegmentCode AS EconomicCode,
                        sv.SegmentName AS EconomicName
                    FROM dbo.tblSegmentValues sv
                    WHERE sv.FiscalYearID = :fy
                      AND sv.SegmentNo = :segmentNo
                      AND sv.ActiveFlag = 1
                )
                SELECT
                    src.EconomicCode,
                    src.EconomicName
                FROM src
                LEFT JOIN dbo.tblSbEconomicItem ei
                    ON ei.EconomicCode = src.EconomicCode
                WHERE ei.EconomicItemID IS NULL
                ORDER BY src.EconomicCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbEconomicItem (
                    ParentEconomicItemID,
                    EconomicCode,
                    EconomicName,
                    EconomicLevel,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    NULL,
                    :EconomicCode,
                    :EconomicName,
                    NULL,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                )
            ");

            foreach ($rows as $row) {
                $economicCode = trim((string) ($row['EconomicCode'] ?? ''));
                $economicName = trim((string) ($row['EconomicName'] ?? ''));

                if ($economicCode === '' || $economicName === '') {
                    $skipped++;
                    continue;
                }

                if ($this->getEconomicItemByCode($economicCode) !== null) {
                    $skipped++;
                    continue;
                }

                $insert->execute([
                    ':EconomicCode' => $economicCode,
                    ':EconomicName' => $economicName,
                    ':CreatedBy' => $userId,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'reset' => $resetSummary,
        ];
    }

    public function importFundingTypeOverlaysFromSegments(int $fiscalYearId, int $userId, string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }

        if (!$this->tableExists('dbo.tblSbFundingType')) {
            throw new \RuntimeException('Funding type import requires the standalone dimension migration.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_TYPE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Funding type segment mapping is not configured for the active fiscal year.');
        }

        $created = 0;
        $skipped = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $segmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbFundingType',
                    'key_column' => 'FundingTypeID',
                    'dimension_code' => null,
                    'blockers' => [
                        ['table' => 'dbo.tblSbFundingSource', 'column' => 'FundingTypeID', 'label' => 'funding sources'],
                    ],
                ]
            );

            $stmt = $this->conn->prepare("
                WITH src AS (
                    SELECT DISTINCT
                        sv.SegmentCode AS SourceSegmentCode,
                        sv.SegmentName AS FundingTypeName
                    FROM dbo.tblSegmentValues sv
                    WHERE sv.FiscalYearID = :fy
                      AND sv.SegmentNo = :segmentNo
                      AND sv.ActiveFlag = 1
                )
                SELECT
                    src.SourceSegmentCode,
                    src.FundingTypeName
                FROM src
                LEFT JOIN dbo.tblSbFundingType ft
                    ON ft.SourceFiscalYearID = :fyJoin
                   AND ft.SourceSegmentCode = src.SourceSegmentCode
                WHERE ft.FundingTypeID IS NULL
                ORDER BY src.SourceSegmentCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':fyJoin' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbFundingType (
                    FundingTypeCode,
                    FundingTypeName,
                    FundingTypeDescription,
                    SourceFiscalYearID,
                    SourceSegmentNo,
                    SourceSegmentCode,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :FundingTypeCode,
                    :FundingTypeName,
                    NULL,
                    :SourceFiscalYearID,
                    :SourceSegmentNo,
                    :SourceSegmentCode,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                )
            ");

            foreach ($rows as $row) {
                $sourceSegmentCode = trim((string) ($row['SourceSegmentCode'] ?? ''));
                $fundingTypeName = trim((string) ($row['FundingTypeName'] ?? ''));

                if ($sourceSegmentCode === '' || $fundingTypeName === '') {
                    $skipped++;
                    continue;
                }

                if ($this->getFundingTypeBySourceCode($fiscalYearId, $sourceSegmentCode) !== null) {
                    $skipped++;
                    continue;
                }

                $insert->execute([
                    ':FundingTypeCode' => $sourceSegmentCode,
                    ':FundingTypeName' => $fundingTypeName,
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceSegmentNo' => $segmentNo,
                    ':SourceSegmentCode' => $sourceSegmentCode,
                    ':CreatedBy' => $userId,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'reset' => $resetSummary,
        ];
    }

    public function importFundingSourceOverlaysFromSegments(int $fiscalYearId, int $userId, string $defaultFundingTypeCode = 'DOMESTIC', string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }

        $sourceFiscalColumn = $this->conn->query("SELECT COL_LENGTH('dbo.tblSbFundingSource', 'SourceFiscalYearID')")->fetchColumn();
        $sourceCodeColumn = $this->conn->query("SELECT COL_LENGTH('dbo.tblSbFundingSource', 'SourceSegmentCode')")->fetchColumn();
        if ($sourceFiscalColumn === null || $sourceCodeColumn === null) {
            throw new \RuntimeException('Funding source import requires the alter_tblSbFundingSource_add_source_segment.sql migration.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_SOURCE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Funding source segment mapping is not configured for the active fiscal year.');
        }

        $defaultFundingTypeCode = strtoupper(trim($defaultFundingTypeCode));
        if ($defaultFundingTypeCode === '') {
            throw new \InvalidArgumentException('A default funding type is required.');
        }
        $defaultFundingType = $this->getFundingTypeByCode($defaultFundingTypeCode);
        if ($defaultFundingType === null) {
            throw new \RuntimeException('Default funding type ' . $defaultFundingTypeCode . ' was not found.');
        }
        $defaultFundingTypeId = (int) ($defaultFundingType['FundingTypeID'] ?? 0);
        if ($defaultFundingTypeId <= 0) {
            throw new \RuntimeException('Default funding type ' . $defaultFundingTypeCode . ' is not configured correctly.');
        }

        $created = 0;
        $skipped = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $segmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbFundingSource',
                    'key_column' => 'FundingSourceID',
                    'dimension_code' => null,
                    'blockers' => [
                        ['table' => 'dbo.tblSbActivityBudget', 'column' => 'FundingSourceID', 'label' => 'activity budgets'],
                        ['table' => 'dbo.tblSbResourceEnvelope', 'column' => 'FundingSourceID', 'label' => 'resource envelopes'],
                    ],
                ]
            );

            $stmt = $this->conn->prepare("
                WITH src AS (
                    SELECT DISTINCT
                        sv.SegmentCode AS SourceSegmentCode,
                        sv.SegmentName AS FundingSourceName
                    FROM dbo.tblSegmentValues sv
                    WHERE sv.FiscalYearID = :fy
                      AND sv.SegmentNo = :segmentNo
                      AND sv.ActiveFlag = 1
                )
                SELECT
                    src.SourceSegmentCode,
                    src.FundingSourceName
                FROM src
                LEFT JOIN dbo.tblSbFundingSource fs
                    ON fs.SourceFiscalYearID = :fyJoin
                   AND fs.SourceSegmentCode = src.SourceSegmentCode
                WHERE fs.FundingSourceID IS NULL
                ORDER BY src.SourceSegmentCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':fyJoin' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbFundingSource (
                    FundingTypeID,
                    FundingTypeCode,
                    FundingSourceName,
                    DonorName,
                    ConditionsText,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate,
                    SourceFiscalYearID,
                    SourceSegmentCode
                )
                VALUES (
                    :FundingTypeID,
                    :FundingTypeCode,
                    :FundingSourceName,
                    NULL,
                    NULL,
                    1,
                    :CreatedBy,
                    SYSDATETIME(),
                    :SourceFiscalYearID,
                    :SourceSegmentCode
                )
            ");

            foreach ($rows as $row) {
                $sourceSegmentCode = trim((string) ($row['SourceSegmentCode'] ?? ''));
                $fundingSourceName = trim((string) ($row['FundingSourceName'] ?? ''));

                if ($sourceSegmentCode === '' || $fundingSourceName === '') {
                    $skipped++;
                    continue;
                }

                if ($this->getFundingSourceBySourceCode($fiscalYearId, $sourceSegmentCode) !== null) {
                    $skipped++;
                    continue;
                }

                $insert->execute([
                    ':FundingTypeID' => $defaultFundingTypeId,
                    ':FundingTypeCode' => $defaultFundingTypeCode,
                    ':FundingSourceName' => $fundingSourceName,
                    ':CreatedBy' => $userId,
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceSegmentCode' => $sourceSegmentCode,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'reset' => $resetSummary,
        ];
    }

    public function getSectorBySourceCode(int $fiscalYearId, string $sourceSegmentCode): ?array
    {
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbSector')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbSector
            WHERE SourceFiscalYearID = :fy
              AND SourceSegmentCode = :sourceSegmentCode
            ORDER BY SectorID ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':sourceSegmentCode' => $sourceSegmentCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getSectorBySourceScope(int $fiscalYearId, string $dataObjectCode): ?array
    {
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbSector')) {
            return null;
        }

        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [trim($dataObjectCode)];
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbSector
            WHERE SourceFiscalYearID = :fy
              AND SourceDataObjectCode = :dataObjectCode
              AND ActiveFlag = 1
            ORDER BY SectorID ASC
        ");

        foreach ($scopeCodes as $scopeCode) {
            $scopeCode = trim((string) $scopeCode);
            if ($scopeCode === '') {
                continue;
            }

            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':dataObjectCode' => $scopeCode,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if ($row !== null) {
                return $row;
            }
        }

        return null;
    }

    public function findObjectiveBySource(int $fiscalYearId, string $dataObjectCode, string $sourceSegmentCode): ?array
    {
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbObjective')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbObjective
            WHERE SourceFiscalYearID = :fy
              AND SourceDataObjectCode = :dataObjectCode
              AND SourceSegmentCode = :sourceSegmentCode
            ORDER BY ObjectiveID ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':dataObjectCode' => $dataObjectCode,
            ':sourceSegmentCode' => $sourceSegmentCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findOutputBySource(int $fiscalYearId, string $dataObjectCode, string $sourceSegmentCode): ?array
    {
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbOutput')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbOutput
            WHERE SourceFiscalYearID = :fy
              AND SourceDataObjectCode = :dataObjectCode
              AND SourceSegmentCode = :sourceSegmentCode
            ORDER BY OutputID ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':dataObjectCode' => $dataObjectCode,
            ':sourceSegmentCode' => $sourceSegmentCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findActivityBySource(int $fiscalYearId, string $dataObjectCode, string $sourceSegmentCode): ?array
    {
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbActivity')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbActivity
            WHERE SourceFiscalYearID = :fy
              AND SourceDataObjectCode = :dataObjectCode
              AND SourceSegmentCode = :sourceSegmentCode
            ORDER BY ActivityID ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':dataObjectCode' => $dataObjectCode,
            ':sourceSegmentCode' => $sourceSegmentCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function importSectorOverlaysFromSegments(int $fiscalYearId, int $userId, string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbSector')) {
            throw new \RuntimeException('Sector import requires the standalone/source-tracking migration on tblSbSector.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SECTOR');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Sector segment mapping is not configured for the active fiscal year.');
        }

        $created = 0;
        $linked = 0;
        $skipped = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $segmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbSector',
                    'key_column' => 'SectorID',
                    'dimension_code' => null,
                    'blockers' => [
                        ['table' => 'dbo.tblSbProgram', 'column' => 'SectorID', 'label' => 'programs'],
                        ['table' => 'dbo.tblSbNarrative', 'column' => 'SectorID', 'label' => 'narratives'],
                        ['table' => 'dbo.tblSbResourceEnvelope', 'column' => 'RestrictedSectorID', 'label' => 'resource envelope targets'],
                    ],
                ]
            );

            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    sv.SegmentCode AS SourceSegmentCode,
                    sv.SegmentName AS SectorName
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
                ORDER BY sv.SegmentCode ASC, sv.SegmentName ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbSector (
                    SectorName,
                    SectorDescription,
                    SourceFiscalYearID,
                    SourceSegmentNo,
                    SourceSegmentCode,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :SectorName,
                    NULL,
                    :SourceFiscalYearID,
                    :SourceSegmentNo,
                    :SourceSegmentCode,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                )
            ");
            $linkExisting = $this->conn->prepare("
                UPDATE dbo.tblSbSector
                SET SourceFiscalYearID = :SourceFiscalYearID,
                    SourceSegmentNo = :SourceSegmentNo,
                    SourceSegmentCode = :SourceSegmentCode,
                    ActiveFlag = 1,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE SectorID = :SectorID
            ");

            foreach ($rows as $row) {
                $sourceSegmentCode = trim((string) ($row['SourceSegmentCode'] ?? ''));
                $sectorName = trim((string) ($row['SectorName'] ?? ''));

                if ($sourceSegmentCode === '' || $sectorName === '') {
                    $skipped++;
                    continue;
                }

                if ($this->getSectorBySourceCode($fiscalYearId, $sourceSegmentCode) !== null) {
                    $skipped++;
                    continue;
                }

                $existingByName = $this->getSectorByName($sectorName);
                if ($existingByName !== null) {
                    $linkExisting->execute([
                        ':SourceFiscalYearID' => $fiscalYearId,
                        ':SourceSegmentNo' => $segmentNo,
                        ':SourceSegmentCode' => $sourceSegmentCode,
                        ':UpdatedBy' => $userId,
                        ':SectorID' => (int) ($existingByName['SectorID'] ?? 0),
                    ]);
                    $linked++;
                    continue;
                }

                $insert->execute([
                    ':SectorName' => $sectorName,
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceSegmentNo' => $segmentNo,
                    ':SourceSegmentCode' => $sourceSegmentCode,
                    ':CreatedBy' => $userId,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'linked' => $linked,
            'skipped' => $skipped,
            'reset' => $resetSummary,
        ];
    }

    public function importObjectiveOverlaysFromSegments(int $fiscalYearId, int $userId, string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbObjective')) {
            throw new \RuntimeException('Objective import requires the standalone/source-tracking migration on tblSbObjective.');
        }
        if (!$this->hasParentSegmentColumns()) {
            throw new \RuntimeException('Objective import requires ParentSegmentNo and ParentSegmentCode on tblSegmentValues.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'OBJECTIVE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Objective segment mapping is not configured for the active fiscal year.');
        }

        $created = 0;
        $skipped = 0;
        $missingParentLink = 0;
        $missingParentOverlay = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $segmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbObjective',
                    'key_column' => 'ObjectiveID',
                    'dimension_code' => 'OBJECTIVE',
                    'blockers' => [
                        ['table' => 'dbo.tblSbProjectObjectiveLink', 'column' => 'ObjectiveID', 'label' => 'project links'],
                    ],
                    'cleanup_tables' => [
                        ['table' => 'dbo.tblSbObjectiveGoal', 'column' => 'ObjectiveID'],
                        ['table' => 'dbo.tblSbObjectiveIndicator', 'column' => 'ObjectiveID'],
                    ],
                ]
            );

            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    sv.DataObjectCode,
                    sv.SegmentCode AS SourceSegmentCode,
                    sv.SegmentName AS ObjectiveText,
                    sv.ParentSegmentNo,
                    sv.ParentSegmentCode
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
                ORDER BY sv.DataObjectCode ASC, sv.SegmentCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbObjective (
                    ProgramID,
                    SubProgramID,
                    ObjectiveText,
                    PolicyLink,
                    PriorityRank,
                    SourceFiscalYearID,
                    SourceDataObjectCode,
                    SourceSegmentNo,
                    SourceSegmentCode,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :ProgramID,
                    :SubProgramID,
                    :ObjectiveText,
                    NULL,
                    NULL,
                    :SourceFiscalYearID,
                    :SourceDataObjectCode,
                    :SourceSegmentNo,
                    :SourceSegmentCode,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                )
            ");

            foreach ($rows as $row) {
                $dataObjectCode = trim((string) ($row['DataObjectCode'] ?? ''));
                $sourceSegmentCode = trim((string) ($row['SourceSegmentCode'] ?? ''));
                $objectiveText = trim((string) ($row['ObjectiveText'] ?? ''));

                if ($dataObjectCode === '' || $sourceSegmentCode === '' || $objectiveText === '') {
                    $skipped++;
                    continue;
                }
                if ($this->findObjectiveBySource($fiscalYearId, $dataObjectCode, $sourceSegmentCode) !== null) {
                    $skipped++;
                    continue;
                }

                $parent = $this->resolveProgramSubProgramParentFromSource(
                    $fiscalYearId,
                    $dataObjectCode,
                    (int) ($row['ParentSegmentNo'] ?? 0),
                    trim((string) ($row['ParentSegmentCode'] ?? ''))
                );
                if (($parent['status'] ?? '') === 'missing_link') {
                    $missingParentLink++;
                    continue;
                }
                if (($parent['status'] ?? '') !== 'resolved') {
                    $missingParentOverlay++;
                    continue;
                }

                $insert->execute([
                    ':ProgramID' => (int) ($parent['ProgramID'] ?? 0),
                    ':SubProgramID' => $parent['SubProgramID'] ?? null,
                    ':ObjectiveText' => $objectiveText,
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceDataObjectCode' => $dataObjectCode,
                    ':SourceSegmentNo' => $segmentNo,
                    ':SourceSegmentCode' => $sourceSegmentCode,
                    ':CreatedBy' => $userId,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing_parent_link' => $missingParentLink,
            'missing_parent_overlay' => $missingParentOverlay,
            'reset' => $resetSummary,
        ];
    }

    public function importOutputOverlaysFromSegments(int $fiscalYearId, int $userId, string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbOutput')) {
            throw new \RuntimeException('Output import requires the standalone/source-tracking migration on tblSbOutput.');
        }
        if (!$this->hasParentSegmentColumns()) {
            throw new \RuntimeException('Output import requires ParentSegmentNo and ParentSegmentCode on tblSegmentValues.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'OUTPUT');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Output segment mapping is not configured for the active fiscal year.');
        }

        $created = 0;
        $skipped = 0;
        $missingParentLink = 0;
        $missingParentOverlay = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $segmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbOutput',
                    'key_column' => 'OutputID',
                    'dimension_code' => null,
                    'blockers' => [
                        ['table' => 'dbo.tblSbActivity', 'column' => 'OutputID', 'label' => 'activities'],
                    ],
                    'cleanup_tables' => [
                        ['table' => 'dbo.tblSbOutputIndicator', 'column' => 'OutputID'],
                    ],
                ]
            );

            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    sv.DataObjectCode,
                    sv.SegmentCode AS SourceSegmentCode,
                    sv.SegmentName AS OutputName,
                    sv.ParentSegmentNo,
                    sv.ParentSegmentCode
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
                ORDER BY sv.DataObjectCode ASC, sv.SegmentCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbOutput (
                    ProgramID,
                    SubProgramID,
                    OutputName,
                    OutputDescription,
                    OutputOwnerOrgUnitID,
                    SourceFiscalYearID,
                    SourceDataObjectCode,
                    SourceSegmentNo,
                    SourceSegmentCode,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :ProgramID,
                    :SubProgramID,
                    :OutputName,
                    NULL,
                    :OutputOwnerOrgUnitID,
                    :SourceFiscalYearID,
                    :SourceDataObjectCode,
                    :SourceSegmentNo,
                    :SourceSegmentCode,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                )
            ");

            foreach ($rows as $row) {
                $dataObjectCode = trim((string) ($row['DataObjectCode'] ?? ''));
                $sourceSegmentCode = trim((string) ($row['SourceSegmentCode'] ?? ''));
                $outputName = trim((string) ($row['OutputName'] ?? ''));

                if ($dataObjectCode === '' || $sourceSegmentCode === '' || $outputName === '') {
                    $skipped++;
                    continue;
                }
                if ($this->findOutputBySource($fiscalYearId, $dataObjectCode, $sourceSegmentCode) !== null) {
                    $skipped++;
                    continue;
                }

                $parent = $this->resolveProgramSubProgramParentFromSource(
                    $fiscalYearId,
                    $dataObjectCode,
                    (int) ($row['ParentSegmentNo'] ?? 0),
                    trim((string) ($row['ParentSegmentCode'] ?? ''))
                );
                if (($parent['status'] ?? '') === 'missing_link') {
                    $missingParentLink++;
                    continue;
                }
                if (($parent['status'] ?? '') !== 'resolved') {
                    $missingParentOverlay++;
                    continue;
                }

                $insert->execute([
                    ':ProgramID' => (int) ($parent['ProgramID'] ?? 0),
                    ':SubProgramID' => $parent['SubProgramID'] ?? null,
                    ':OutputName' => $outputName,
                    ':OutputOwnerOrgUnitID' => $this->ensureOrgUnitFromDataObject($fiscalYearId, $dataObjectCode, $userId),
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceDataObjectCode' => $dataObjectCode,
                    ':SourceSegmentNo' => $segmentNo,
                    ':SourceSegmentCode' => $sourceSegmentCode,
                    ':CreatedBy' => $userId,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing_parent_link' => $missingParentLink,
            'missing_parent_overlay' => $missingParentOverlay,
            'reset' => $resetSummary,
        ];
    }

    public function importActivityOverlaysFromSegments(int $fiscalYearId, int $userId, string $resetMode = 'none'): array
    {
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbActivity')) {
            throw new \RuntimeException('Activity import requires the standalone/source-tracking migration on tblSbActivity.');
        }
        if (!$this->hasParentSegmentColumns()) {
            throw new \RuntimeException('Activity import requires ParentSegmentNo and ParentSegmentCode on tblSegmentValues.');
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'ACTIVITY');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('Activity segment mapping is not configured for the active fiscal year.');
        }
        $outputMapping = $this->getStrategicSegmentMapping($fiscalYearId, 'OUTPUT');
        $outputSegmentNo = (int) ($outputMapping['SegmentNo'] ?? 0);
        if ($outputSegmentNo <= 0) {
            throw new \RuntimeException('Activity import requires OUTPUT segment mapping to be configured first.');
        }

        $created = 0;
        $skipped = 0;
        $missingParentLink = 0;
        $missingParentOverlay = 0;

        $this->conn->beginTransaction();
        try {
            $resetSummary = $this->resetStandaloneDimensionImportsForFiscalYear(
                $fiscalYearId,
                $segmentNo,
                $userId,
                $resetMode,
                [
                    'table' => 'dbo.tblSbActivity',
                    'key_column' => 'ActivityID',
                    'dimension_code' => null,
                    'blockers' => [
                        ['table' => 'dbo.tblSbActivityBudget', 'column' => 'ActivityID', 'label' => 'activity budgets'],
                        ['table' => 'dbo.tblSbResourceEnvelope', 'column' => 'RestrictedActivityID', 'label' => 'resource envelope targets'],
                    ],
                ]
            );

            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    sv.DataObjectCode,
                    sv.SegmentCode AS SourceSegmentCode,
                    sv.SegmentName AS ActivityName,
                    sv.ParentSegmentNo,
                    sv.ParentSegmentCode
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
                ORDER BY sv.DataObjectCode ASC, sv.SegmentCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSbActivity (
                    OutputID,
                    ActivityName,
                    ActivityDescription,
                    ActivityTypeCode,
                    LocationCode,
                    StartDate,
                    EndDate,
                    ImplementationStatusCode,
                    ProcurementRequiredFlag,
                    Dependencies,
                    RiskNotes,
                    SourceFiscalYearID,
                    SourceDataObjectCode,
                    SourceSegmentNo,
                    SourceSegmentCode,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                VALUES (
                    :OutputID,
                    :ActivityName,
                    NULL,
                    N'OPERATIONAL',
                    NULL,
                    NULL,
                    NULL,
                    N'PLANNED',
                    0,
                    NULL,
                    NULL,
                    :SourceFiscalYearID,
                    :SourceDataObjectCode,
                    :SourceSegmentNo,
                    :SourceSegmentCode,
                    1,
                    :CreatedBy,
                    SYSDATETIME()
                )
            ");

            foreach ($rows as $row) {
                $dataObjectCode = trim((string) ($row['DataObjectCode'] ?? ''));
                $sourceSegmentCode = trim((string) ($row['SourceSegmentCode'] ?? ''));
                $activityName = trim((string) ($row['ActivityName'] ?? ''));
                $parentSegmentNo = (int) ($row['ParentSegmentNo'] ?? 0);
                $parentSegmentCode = trim((string) ($row['ParentSegmentCode'] ?? ''));

                if ($dataObjectCode === '' || $sourceSegmentCode === '' || $activityName === '') {
                    $skipped++;
                    continue;
                }
                if ($this->findActivityBySource($fiscalYearId, $dataObjectCode, $sourceSegmentCode) !== null) {
                    $skipped++;
                    continue;
                }
                if ($parentSegmentNo !== $outputSegmentNo || $parentSegmentCode === '') {
                    $missingParentLink++;
                    continue;
                }

                $output = $this->findOutputBySource($fiscalYearId, $dataObjectCode, $parentSegmentCode);
                if ($output === null) {
                    $missingParentOverlay++;
                    continue;
                }

                $insert->execute([
                    ':OutputID' => (int) ($output['OutputID'] ?? 0),
                    ':ActivityName' => $activityName,
                    ':SourceFiscalYearID' => $fiscalYearId,
                    ':SourceDataObjectCode' => $dataObjectCode,
                    ':SourceSegmentNo' => $segmentNo,
                    ':SourceSegmentCode' => $sourceSegmentCode,
                    ':CreatedBy' => $userId,
                ]);
                $created++;
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'missing_parent_link' => $missingParentLink,
            'missing_parent_overlay' => $missingParentOverlay,
            'reset' => $resetSummary,
        ];
    }

    public function getOverlayImportDashboard(int $fiscalYearId, int $versionId = 0): array
    {
        $statuses = [
            'PROGRAM' => $this->getProgramOverlayImportStatus($fiscalYearId),
            'SUBPROGRAM' => $this->getSubProgramOverlayImportStatus($fiscalYearId),
            'PROJECT' => $this->getProjectDimensionStatus($fiscalYearId),
            'SECTOR' => $this->getSectorDimensionStatus($fiscalYearId),
            'ECONOMIC' => $this->getEconomicOverlayImportStatus($fiscalYearId),
            'FUNDING_TYPE' => $this->getFundingTypeDimensionStatus($fiscalYearId),
            'FUNDING_SOURCE' => $this->getFundingSourceOverlayImportStatus($fiscalYearId),
            'OBJECTIVE' => $this->getObjectiveDimensionStatus($fiscalYearId),
            'INDICATOR' => $this->getIndicatorDimensionStatus($fiscalYearId),
            'TARGET' => $this->getTargetDimensionStatus($fiscalYearId, $versionId),
            'OUTPUT' => $this->getOutputDimensionStatus($fiscalYearId),
            'ACTIVITY' => $this->getActivityDimensionStatus($fiscalYearId),
        ];

        $history = $this->getLatestDimensionImportHistoryMap($fiscalYearId, array_keys($statuses));
        foreach ($statuses as $code => &$status) {
            $status['last_import'] = $history[$code] ?? null;
        }
        unset($status);

        return $statuses;
    }

    public function getLatestDimensionImportHistory(int $fiscalYearId, string $dimensionCode): ?array
    {
        $map = $this->getLatestDimensionImportHistoryMap($fiscalYearId, [$dimensionCode]);
        $dimensionCode = strtoupper(trim($dimensionCode));
        return $map[$dimensionCode] ?? null;
    }

    public function getLatestDimensionImportHistoryMap(int $fiscalYearId, array $dimensionCodes): array
    {
        if ($fiscalYearId <= 0 || !$this->tableExists('dbo.tblAuditLog')) {
            return [];
        }

        $normalizedCodes = array_values(array_unique(array_filter(array_map(
            static fn(mixed $code): string => strtoupper(trim((string) $code)),
            $dimensionCodes
        ), static fn(string $code): bool => $code !== '')));
        if ($normalizedCodes === []) {
            return [];
        }

        $params = [':fy' => $fiscalYearId];
        $placeholders = [];
        foreach ($normalizedCodes as $index => $code) {
            $key = ':code' . $index;
            $params[$key] = $code;
            $placeholders[] = $key;
        }

        $stmt = $this->conn->prepare("
            SELECT AuditID, EventTime, Username, EntityKey, Details
            FROM dbo.tblAuditLog
            WHERE Action = :action
              AND Entity = :entity
              AND FiscalYearID = :fy
              AND EntityKey IN (" . implode(', ', $placeholders) . ")
            ORDER BY EventTime DESC, AuditID DESC
        ");
        $stmt->execute($params + [
            ':action' => 'IMPORT_DIMENSION',
            ':entity' => 'STRATEGY_DIMENSION',
        ]);

        $history = [];
        foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $code = strtoupper(trim((string) ($row['EntityKey'] ?? '')));
            if ($code === '' || isset($history[$code])) {
                continue;
            }

            $details = [];
            $rawDetails = $row['Details'] ?? null;
            if (is_string($rawDetails) && $rawDetails !== '') {
                $decoded = json_decode($rawDetails, true);
                if (is_array($decoded)) {
                    $details = $decoded;
                }
            }

            $history[$code] = [
                'EventTime' => (string) ($row['EventTime'] ?? ''),
                'Username' => (string) ($row['Username'] ?? ''),
                'Details' => $details,
                'ImportSummaryText' => $this->buildDimensionImportSummaryText($details),
                'ImportSummaryParts' => $this->buildDimensionImportSummaryParts($details),
            ];
        }

        return $history;
    }

    private function buildDimensionImportSummaryText(array $details): string
    {
        $parts = $this->buildDimensionImportSummaryParts($details);
        $textParts = [];
        foreach ($parts as $part) {
            $label = trim((string) ($part['label'] ?? ''));
            $value = trim((string) ($part['value'] ?? ''));
            if ($label === '') {
                continue;
            }
            $textParts[] = $value !== '' ? ($label . ' ' . $value) : $label;
        }

        return implode(', ', $textParts);
    }

    private function buildDimensionImportSummaryParts(array $details): array
    {
        $summary = is_array($details['summary'] ?? null) ? $details['summary'] : [];
        $parts = [];

        $labels = [
            'created' => 'Created',
            'updated' => 'Updated',
            'matched_existing' => 'Matched existing',
            'skipped' => 'Skipped',
        ];

        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $summary)) {
                $parts[] = [
                    'label' => $label,
                    'value' => (string) ((int) $summary[$key]),
                ];
            }
        }

        $resetMode = strtolower(trim((string) ($details['reset_mode'] ?? ($summary['reset']['mode'] ?? 'none'))));
        if ($resetMode !== '' && $resetMode !== 'none') {
            $parts[] = [
                'label' => 'Reset',
                'value' => $resetMode,
            ];
        }

        return $parts;
    }

    public function listProgramOptions(): array
    {
        $stmt = $this->conn->query("
            SELECT ProgramID, ProgramName, ProgramCode
            FROM dbo.tblSbProgram
            WHERE ActiveFlag = 1
            ORDER BY CASE WHEN COALESCE(ProgramCode, N'') = N'' THEN 1 ELSE 0 END,
                     ProgramCode ASC,
                     ProgramName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProgramOptionsForDataObject(int $fiscalYearId, string $dataObjectCode): array
    {
        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [trim($dataObjectCode)];
        }
        $scopeCodes = array_values(array_filter(array_map('trim', $scopeCodes), static fn(string $code): bool => $code !== ''));
        if ($scopeCodes === []) {
            return $this->listProgramOptions();
        }

        $params = [];
        $scopeSqlProgram = $this->buildScopeInListParams($scopeCodes, 'scopeProgramList', $params);
        $scopeSqlOwner = $this->buildScopeInListParams($scopeCodes, 'scopeOwnerList', $params);

        if ($this->supportsProgramOrgLinks()) {
            $scopeSqlLinked = $this->buildScopeInListParams($scopeCodes, 'scopeLinkedList', $params);
            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    p.ProgramID,
                    p.ProgramCode,
                    p.ProgramName,
                    p.SectorID,
                    p.OrgUnitID
                FROM dbo.tblSbProgram p
                INNER JOIN dbo.tblSbOrgUnit o
                    ON o.OrgUnitID = p.OrgUnitID
                LEFT JOIN dbo.tblSbProgramOrgLink pol
                    ON pol.ProgramID = p.ProgramID
                   AND pol.ActiveFlag = 1
                LEFT JOIN dbo.tblSbOrgUnit linked
                    ON linked.OrgUnitID = pol.OrgUnitID
                WHERE p.ActiveFlag = 1
                  AND (
                        p.SourceDataObjectCode IN ({$scopeSqlProgram})
                     OR o.SourceDataObjectCode IN ({$scopeSqlOwner})
                     OR linked.SourceDataObjectCode IN ({$scopeSqlLinked})
                  )
                ORDER BY p.ProgramCode ASC,
                         p.ProgramName ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->conn->prepare("
            SELECT DISTINCT
                p.ProgramID,
                p.ProgramCode,
                p.ProgramName,
                p.SectorID,
                p.OrgUnitID
            FROM dbo.tblSbProgram p
            INNER JOIN dbo.tblSbOrgUnit o
                ON o.OrgUnitID = p.OrgUnitID
            WHERE p.ActiveFlag = 1
              AND (
                    p.SourceDataObjectCode IN ({$scopeSqlProgram})
                 OR o.SourceDataObjectCode IN ({$scopeSqlOwner})
              )
            ORDER BY p.ProgramCode ASC,
                     p.ProgramName ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSubProgramOptions(): array
    {
        $stmt = $this->conn->query("
            SELECT sp.SubProgramID, sp.ProgramID, sp.SubProgramCode, sp.SubProgramName, p.ProgramName
            FROM dbo.tblSbSubProgram sp
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = sp.ProgramID
            WHERE sp.ActiveFlag = 1
            ORDER BY CASE WHEN COALESCE(p.ProgramCode, N'') = N'' THEN 1 ELSE 0 END,
                     p.ProgramCode ASC,
                     CASE WHEN COALESCE(sp.SubProgramCode, N'') = N'' THEN 1 ELSE 0 END,
                     sp.SubProgramCode ASC,
                     sp.SubProgramName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSubProgramOptionsForDataObject(int $fiscalYearId, string $dataObjectCode): array
    {
        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [trim($dataObjectCode)];
        }
        $scopeCodes = array_values(array_filter(array_map('trim', $scopeCodes), static fn(string $code): bool => $code !== ''));
        if ($scopeCodes === []) {
            return $this->listSubProgramOptions();
        }

        $params = [];
        $scopeSqlProgram = $this->buildScopeInListParams($scopeCodes, 'scopeSubProgram', $params);
        $scopeSqlOwner = $this->buildScopeInListParams($scopeCodes, 'scopeSubOwner', $params);

        if ($this->supportsProgramOrgLinks()) {
            $scopeSqlLinked = $this->buildScopeInListParams($scopeCodes, 'scopeSubLinked', $params);
            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    sp.SubProgramID,
                    sp.ProgramID,
                    sp.SubProgramCode,
                    sp.SubProgramName,
                    p.ProgramCode,
                    p.ProgramName,
                    p.SectorID,
                    p.OrgUnitID
                FROM dbo.tblSbSubProgram sp
                INNER JOIN dbo.tblSbProgram p
                    ON p.ProgramID = sp.ProgramID
                INNER JOIN dbo.tblSbOrgUnit o
                    ON o.OrgUnitID = p.OrgUnitID
                LEFT JOIN dbo.tblSbProgramOrgLink pol
                    ON pol.ProgramID = p.ProgramID
                   AND pol.ActiveFlag = 1
                LEFT JOIN dbo.tblSbOrgUnit linked
                    ON linked.OrgUnitID = pol.OrgUnitID
                WHERE sp.ActiveFlag = 1
                  AND p.ActiveFlag = 1
                  AND (
                        p.SourceDataObjectCode IN ({$scopeSqlProgram})
                     OR o.SourceDataObjectCode IN ({$scopeSqlOwner})
                     OR linked.SourceDataObjectCode IN ({$scopeSqlLinked})
                  )
                ORDER BY p.ProgramCode ASC,
                         sp.SubProgramCode ASC,
                         sp.SubProgramName ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->conn->prepare("
            SELECT DISTINCT
                sp.SubProgramID,
                sp.ProgramID,
                sp.SubProgramCode,
                sp.SubProgramName,
                p.ProgramCode,
                p.ProgramName,
                p.SectorID,
                p.OrgUnitID
            FROM dbo.tblSbSubProgram sp
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = sp.ProgramID
            INNER JOIN dbo.tblSbOrgUnit o
                ON o.OrgUnitID = p.OrgUnitID
            WHERE sp.ActiveFlag = 1
              AND p.ActiveFlag = 1
              AND (
                    p.SourceDataObjectCode IN ({$scopeSqlProgram})
                 OR o.SourceDataObjectCode IN ({$scopeSqlOwner})
              )
            ORDER BY p.ProgramCode ASC,
                     sp.SubProgramCode ASC,
                     sp.SubProgramName ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectOptions(): array
    {
        if (!$this->tableExists('dbo.tblSbProject')) {
            return [];
        }

        $stmt = $this->conn->query("
            SELECT ProjectID, ProjectCode, ProjectName
            FROM dbo.tblSbProject
            WHERE ActiveFlag = 1
            ORDER BY CASE WHEN COALESCE(ProjectCode, N'') = N'' THEN 1 ELSE 0 END,
                     ProjectCode ASC,
                     ProjectName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectOptionsForDataObject(int $fiscalYearId, string $dataObjectCode): array
    {
        if (!$this->tableExists('dbo.tblSbProject')) {
            return [];
        }

        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [trim($dataObjectCode)];
        }
        $scopeCodes = array_values(array_filter(array_map('trim', $scopeCodes), static fn(string $code): bool => $code !== ''));
        if ($scopeCodes === []) {
            return $this->listProjectOptions();
        }

        $params = [':fy' => $fiscalYearId];
        $scopeSql = $this->buildScopeInListParams($scopeCodes, 'scopeProject', $params);
        if ($this->supportsProjectSourceMaps()) {
            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    p.ProjectID,
                    p.ProjectCode,
                    p.ProjectName,
                    sm.DataObjectCode AS SourceDataObjectCode
                FROM dbo.tblSbProject p
                LEFT JOIN dbo.tblSbProjectSourceMap sm
                    ON sm.ProjectID = p.ProjectID
                   AND sm.ActiveFlag = 1
                WHERE p.ActiveFlag = 1
                  AND (
                        (
                            sm.FiscalYearID = :fy
                            AND COALESCE(sm.DataObjectCode, N'') IN ({$scopeSql})
                        )
                     OR NOT EXISTS (
                            SELECT 1
                            FROM dbo.tblSbProjectSourceMap smx
                            WHERE smx.ProjectID = p.ProjectID
                              AND smx.ActiveFlag = 1
                        )
                  )
                ORDER BY CASE WHEN COALESCE(p.ProjectCode, N'') = N'' THEN 1 ELSE 0 END,
                         p.ProjectCode ASC,
                         p.ProjectName ASC
            ");
        } else {
            $stmt = $this->conn->prepare("
                SELECT ProjectID, ProjectCode, ProjectName, SourceDataObjectCode
                FROM dbo.tblSbProject
                WHERE ActiveFlag = 1
                  AND SourceFiscalYearID = :fy
                  AND COALESCE(SourceDataObjectCode, N'') IN ({$scopeSql})
                ORDER BY CASE WHEN COALESCE(ProjectCode, N'') = N'' THEN 1 ELSE 0 END,
                         ProjectCode ASC,
                         ProjectName ASC
            ");
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectSourceMappings(int $projectId): array
    {
        if ($projectId <= 0 || !$this->supportsProjectSourceMaps()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                ProjectSourceMapID,
                ProjectID,
                FiscalYearID,
                DataObjectCode,
                SourceSegmentNo,
                SourceSegmentCode,
                SourceSegmentName,
                SourceSystemCode,
                IsPrimaryFlag,
                ActiveFlag
            FROM dbo.tblSbProjectSourceMap
            WHERE ProjectID = :projectId
              AND ActiveFlag = 1
            ORDER BY FiscalYearID DESC, SourceSegmentNo ASC, SourceSegmentCode ASC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectLinkedPrograms(int $projectId): array
    {
        if ($projectId <= 0 || !$this->supportsProjectProgramLinks()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                l.ProjectProgramLinkID,
                l.ProjectID,
                l.ProgramID,
                l.LinkTypeCode,
                p.ProgramCode,
                p.ProgramName
            FROM dbo.tblSbProjectProgramLink l
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = l.ProgramID
            WHERE l.ProjectID = :projectId
              AND l.ActiveFlag = 1
            ORDER BY p.ProgramCode ASC, p.ProgramName ASC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectLinkedObjectives(int $projectId): array
    {
        if ($projectId <= 0 || !$this->supportsProjectObjectiveLinks()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                l.ProjectObjectiveLinkID,
                l.ProjectID,
                l.ObjectiveID,
                l.ContributionTypeCode,
                o.ObjectiveText
            FROM dbo.tblSbProjectObjectiveLink l
            INNER JOIN dbo.tblSbObjective o
                ON o.ObjectiveID = l.ObjectiveID
            WHERE l.ProjectID = :projectId
              AND l.ActiveFlag = 1
            ORDER BY o.ObjectiveText ASC, o.ObjectiveID ASC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectLinkedOrgUnits(int $projectId): array
    {
        if ($projectId <= 0 || !$this->supportsProjectOrgUnitLinks()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                l.ProjectOrgUnitLinkID,
                l.ProjectID,
                l.OrgUnitID,
                l.RoleCode,
                ou.OrgUnitName,
                ou.SourceDataObjectCode AS DataObjectCode
            FROM dbo.tblSbProjectOrgUnitLink l
            INNER JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = l.OrgUnitID
            WHERE l.ProjectID = :projectId
              AND l.ActiveFlag = 1
            ORDER BY ou.OrgUnitName ASC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProjectUsageSummary(int $projectId): array
    {
        $summary = [
            'FundingSubmissionCount' => 0,
            'ActivityCount' => 0,
            'SourceMappingCount' => 0,
            'ResourceEnvelopeCount' => 0,
            'NarrativeCount' => 0,
            'FiscalRiskCount' => 0,
            'ProgramLinkCount' => 0,
            'ObjectiveLinkCount' => 0,
            'OrgUnitLinkCount' => 0,
        ];

        if ($projectId <= 0) {
            return $summary;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSbFundingSubmissionLine
            WHERE ProjectID = :projectId
              AND ActiveFlag = 1
        ");
        $stmt->execute([':projectId' => $projectId]);
        $summary['FundingSubmissionCount'] = (int) ($stmt->fetchColumn() ?: 0);

        if ($this->tableColumnExists('dbo.tblSbActivity', 'ProjectID')) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbActivity
                WHERE ProjectID = :projectId
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['ActivityCount'] = (int) ($stmt->fetchColumn() ?: 0);
        }

        if ($this->supportsProjectSourceMaps()) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbProjectSourceMap
                WHERE ProjectID = :projectId
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['SourceMappingCount'] = (int) ($stmt->fetchColumn() ?: 0);
        } else {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbProject
                WHERE ProjectID = :projectId
                  AND SourceSegmentCode IS NOT NULL
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['SourceMappingCount'] = (int) ($stmt->fetchColumn() ?: 0);
        }

        if ($this->supportsResourceEnvelopeProjectTargetId()) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbResourceEnvelope
                WHERE RestrictedProjectID = :projectId
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['ResourceEnvelopeCount'] = (int) ($stmt->fetchColumn() ?: 0);
        } elseif ($this->supportsResourceEnvelope()) {
            $project = $this->getProject($projectId);
            $projectRef = trim((string) ($project['ProjectCode'] ?? ''));
            if ($projectRef !== '') {
                $stmt = $this->conn->prepare("
                    SELECT COUNT(*)
                    FROM dbo.tblSbResourceEnvelope
                    WHERE RestrictedProjectReference = :projectRef
                      AND ActiveFlag = 1
                ");
                $stmt->execute([':projectRef' => $projectRef]);
                $summary['ResourceEnvelopeCount'] = (int) ($stmt->fetchColumn() ?: 0);
            }
        }

        if ($this->supportsNarrativeProjectLink()) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbNarrative
                WHERE ProjectID = :projectId
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['NarrativeCount'] = (int) ($stmt->fetchColumn() ?: 0);
        }

        if ($this->supportsFiscalRiskProjectLink()) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbFiscalRisk
                WHERE ProjectID = :projectId
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['FiscalRiskCount'] = (int) ($stmt->fetchColumn() ?: 0);
        }

        if ($this->supportsProjectProgramLinks()) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbProjectProgramLink
                WHERE ProjectID = :projectId
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['ProgramLinkCount'] = (int) ($stmt->fetchColumn() ?: 0);
        }

        if ($this->supportsProjectObjectiveLinks()) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbProjectObjectiveLink
                WHERE ProjectID = :projectId
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['ObjectiveLinkCount'] = (int) ($stmt->fetchColumn() ?: 0);
        }

        if ($this->supportsProjectOrgUnitLinks()) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbProjectOrgUnitLink
                WHERE ProjectID = :projectId
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':projectId' => $projectId]);
            $summary['OrgUnitLinkCount'] = (int) ($stmt->fetchColumn() ?: 0);
        }

        return $summary;
    }

    public function listProjectFundingSubmissionUsage(int $projectId): array
    {
        if ($projectId <= 0 || !$this->tableExists('dbo.tblSbFundingSubmissionLine')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                l.StrategicFundingSubmissionLineID,
                l.StrategicFundingSubmissionID,
                l.BidTitle,
                l.LineStatusCode,
                l.CurrentYearRequestedAmount,
                l.OuterYear1RequestedAmount,
                l.OuterYear2RequestedAmount,
                s.RequestTitle,
                s.SubmissionStatusCode,
                s.DataObjectCode
            FROM dbo.tblSbFundingSubmissionLine l
            INNER JOIN dbo.tblSbFundingSubmission s
                ON s.StrategicFundingSubmissionID = l.StrategicFundingSubmissionID
            WHERE l.ProjectID = :projectId
              AND l.ActiveFlag = 1
            ORDER BY s.StrategicFundingSubmissionID DESC, l.StrategicFundingSubmissionLineID DESC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectActivityUsage(int $projectId): array
    {
        if ($projectId <= 0 || !$this->tableColumnExists('dbo.tblSbActivity', 'ProjectID')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                a.ActivityID,
                a.ActivityName,
                a.ActivityTypeCode,
                a.ImplementationStatusCode,
                a.StartDate,
                a.EndDate,
                o.OutputName,
                p.ProgramName
            FROM dbo.tblSbActivity a
            INNER JOIN dbo.tblSbOutput o
                ON o.OutputID = a.OutputID
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = o.ProgramID
            WHERE a.ProjectID = :projectId
              AND a.ActiveFlag = 1
            ORDER BY p.ProgramName ASC, o.OutputName ASC, a.ActivityName ASC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectResourceEnvelopeUsage(int $projectId): array
    {
        if ($projectId <= 0 || !$this->supportsResourceEnvelope()) {
            return [];
        }

        $fundingSourceCodeExpr = 'CAST(NULL AS NVARCHAR(100)) AS FundingSourceCode';
        if ($this->tableColumnExists('dbo.tblSbFundingSource', 'FundingSourceCode')) {
            $fundingSourceCodeExpr = 'fs.FundingSourceCode';
        } elseif ($this->tableColumnExists('dbo.tblSbFundingSource', 'SourceSegmentCode')) {
            $fundingSourceCodeExpr = 'fs.SourceSegmentCode AS FundingSourceCode';
        }

        $outerYearSelect = $this->supportsResourceEnvelopeExtendedOutYears()
            ? 're.OuterYear3Amount, re.OuterYear4Amount, re.OuterYear5Amount'
            : 'CAST(NULL AS DECIMAL(19,6)) AS OuterYear3Amount, CAST(NULL AS DECIMAL(19,6)) AS OuterYear4Amount, CAST(NULL AS DECIMAL(19,6)) AS OuterYear5Amount';

        if ($this->supportsResourceEnvelopeProjectTargetId()) {
            $stmt = $this->conn->prepare("
                SELECT
                    re.ResourceEnvelopeID,
                    re.FiscalYearID,
                    re.VersionID,
                    re.FundingTypeID,
                    re.FundingSourceID,
                    ft.FundingTypeCode,
                    ft.FundingTypeName,
                    " . $fundingSourceCodeExpr . ",
                    fs.FundingSourceName,
                    re.CurrentYearAmount,
                    re.OuterYear1Amount,
                    re.OuterYear2Amount,
                    " . $outerYearSelect . ",
                    re.RestrictionCode,
                    re.RestrictionReference,
                    re.FinancingInstrumentCode
                FROM dbo.tblSbResourceEnvelope re
                INNER JOIN dbo.tblSbFundingType ft
                    ON ft.FundingTypeID = re.FundingTypeID
                LEFT JOIN dbo.tblSbFundingSource fs
                    ON fs.FundingSourceID = re.FundingSourceID
                WHERE re.RestrictedProjectID = :projectId
                  AND re.ActiveFlag = 1
                ORDER BY re.FiscalYearID DESC, re.VersionID DESC, ft.FundingTypeName ASC, fs.FundingSourceName ASC, re.ResourceEnvelopeID DESC
            ");
            $stmt->execute([':projectId' => $projectId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $project = $this->getProject($projectId);
        $projectRef = trim((string) ($project['ProjectCode'] ?? ''));
        if ($projectRef === '') {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                re.ResourceEnvelopeID,
                re.FiscalYearID,
                re.VersionID,
                re.FundingTypeID,
                re.FundingSourceID,
                ft.FundingTypeCode,
                ft.FundingTypeName,
                " . $fundingSourceCodeExpr . ",
                fs.FundingSourceName,
                re.CurrentYearAmount,
                re.OuterYear1Amount,
                re.OuterYear2Amount,
                " . $outerYearSelect . ",
                re.RestrictionCode,
                re.RestrictionReference,
                re.FinancingInstrumentCode
            FROM dbo.tblSbResourceEnvelope re
            INNER JOIN dbo.tblSbFundingType ft
                ON ft.FundingTypeID = re.FundingTypeID
            LEFT JOIN dbo.tblSbFundingSource fs
                ON fs.FundingSourceID = re.FundingSourceID
            WHERE re.RestrictedProjectReference = :projectRef
              AND re.ActiveFlag = 1
            ORDER BY re.FiscalYearID DESC, re.VersionID DESC, ft.FundingTypeName ASC, fs.FundingSourceName ASC, re.ResourceEnvelopeID DESC
        ");
        $stmt->execute([':projectRef' => $projectRef]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProjectFundingEnvelopeSummary(int $projectId): array
    {
        $summary = [
            'EnvelopeLineCount' => 0,
            'FundingSourceCount' => 0,
            'CurrentYearAmount' => 0.0,
            'OuterYearAmount' => 0.0,
            'TotalHorizonAmount' => 0.0,
        ];

        $lines = $this->listProjectResourceEnvelopeUsage($projectId);
        if ($lines === []) {
            return $summary;
        }

        $sources = [];
        foreach ($lines as $line) {
            $summary['EnvelopeLineCount']++;
            $summary['CurrentYearAmount'] += (float) ($line['CurrentYearAmount'] ?? 0);
            $outerAmount = (float) ($line['OuterYear1Amount'] ?? 0)
                + (float) ($line['OuterYear2Amount'] ?? 0)
                + (float) ($line['OuterYear3Amount'] ?? 0)
                + (float) ($line['OuterYear4Amount'] ?? 0)
                + (float) ($line['OuterYear5Amount'] ?? 0);
            $summary['OuterYearAmount'] += $outerAmount;

            $sourceKey = (int) ($line['FundingSourceID'] ?? 0) > 0
                ? 'SRC:' . (int) ($line['FundingSourceID'] ?? 0)
                : 'TYPE:' . (int) ($line['FundingTypeID'] ?? 0);
            $sources[$sourceKey] = true;
        }

        $summary['FundingSourceCount'] = count($sources);
        $summary['TotalHorizonAmount'] = $summary['CurrentYearAmount'] + $summary['OuterYearAmount'];

        return $summary;
    }

    public function listProjectFundingSourceBreakdown(int $projectId): array
    {
        $lines = $this->listProjectResourceEnvelopeUsage($projectId);
        if ($lines === []) {
            return [];
        }

        $groups = [];
        foreach ($lines as $line) {
            $fundingTypeId = (int) ($line['FundingTypeID'] ?? 0);
            $fundingSourceId = (int) ($line['FundingSourceID'] ?? 0);
            $key = $fundingTypeId . ':' . $fundingSourceId;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'FundingTypeID' => $fundingTypeId,
                    'FundingSourceID' => $fundingSourceId > 0 ? $fundingSourceId : null,
                    'FundingTypeCode' => (string) ($line['FundingTypeCode'] ?? ''),
                    'FundingTypeName' => (string) ($line['FundingTypeName'] ?? ''),
                    'FundingSourceCode' => (string) ($line['FundingSourceCode'] ?? ''),
                    'FundingSourceName' => (string) ($line['FundingSourceName'] ?? ''),
                    'EnvelopeLineCount' => 0,
                    'CurrentYearAmount' => 0.0,
                    'OuterYearAmount' => 0.0,
                    'TotalHorizonAmount' => 0.0,
                ];
            }
            $groups[$key]['EnvelopeLineCount']++;
            $groups[$key]['CurrentYearAmount'] += (float) ($line['CurrentYearAmount'] ?? 0);
            $outerAmount = (float) ($line['OuterYear1Amount'] ?? 0)
                + (float) ($line['OuterYear2Amount'] ?? 0)
                + (float) ($line['OuterYear3Amount'] ?? 0)
                + (float) ($line['OuterYear4Amount'] ?? 0)
                + (float) ($line['OuterYear5Amount'] ?? 0);
            $groups[$key]['OuterYearAmount'] += $outerAmount;
            $groups[$key]['TotalHorizonAmount'] += (float) ($line['CurrentYearAmount'] ?? 0) + $outerAmount;
        }

        uasort($groups, static function (array $left, array $right): int {
            $amountCompare = $right['TotalHorizonAmount'] <=> $left['TotalHorizonAmount'];
            if ($amountCompare !== 0) {
                return $amountCompare;
            }

            $leftLabel = trim((string) (($left['FundingTypeName'] ?? '') . ' ' . ($left['FundingSourceName'] ?? '')));
            $rightLabel = trim((string) (($right['FundingTypeName'] ?? '') . ' ' . ($right['FundingSourceName'] ?? '')));
            return strcmp($leftLabel, $rightLabel);
        });

        return array_values($groups);
    }

    public function listProjectNarrativeUsage(int $projectId): array
    {
        if ($projectId <= 0 || !$this->supportsNarrativeProjectLink()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                n.NarrativeID,
                n.FiscalYearID,
                n.VersionID,
                n.SectionCode,
                n.NarrativeTitle,
                n.SortOrder,
                p.ProgramName,
                s.SectorName,
                ou.OrgUnitName
            FROM dbo.tblSbNarrative n
            LEFT JOIN dbo.tblSbProgram p
                ON p.ProgramID = n.ProgramID
            LEFT JOIN dbo.tblSbSector s
                ON s.SectorID = n.SectorID
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = n.OrgUnitID
            WHERE n.ProjectID = :projectId
              AND n.ActiveFlag = 1
            ORDER BY n.FiscalYearID DESC, n.VersionID DESC, n.SectionCode ASC, n.SortOrder ASC, n.NarrativeID ASC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProjectFiscalRiskUsage(int $projectId): array
    {
        if ($projectId <= 0 || !$this->supportsFiscalRiskProjectLink()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                r.FiscalRiskID,
                r.RiskTypeCode,
                r.RiskTitle,
                r.LikelihoodScore,
                r.ImpactScore,
                r.EstimatedFiscalExposure,
                ou.OrgUnitName AS OwnerOrgUnitName
            FROM dbo.tblSbFiscalRisk r
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = r.OwnerOrgUnitID
            WHERE r.ProjectID = :projectId
              AND r.ActiveFlag = 1
            ORDER BY r.RiskTypeCode ASC, r.RiskTitle ASC
        ");
        $stmt->execute([':projectId' => $projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProjectArchiveBlockers(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $summary = $this->getProjectUsageSummary($projectId);
        $candidates = [
            'funding submissions' => (int) ($summary['FundingSubmissionCount'] ?? 0),
            'activities' => (int) ($summary['ActivityCount'] ?? 0),
            'source mappings' => (int) ($summary['SourceMappingCount'] ?? 0),
            'resource envelope targets' => (int) ($summary['ResourceEnvelopeCount'] ?? 0),
            'narratives' => (int) ($summary['NarrativeCount'] ?? 0),
            'fiscal risks' => (int) ($summary['FiscalRiskCount'] ?? 0),
            'program links' => (int) ($summary['ProgramLinkCount'] ?? 0),
            'objective links' => (int) ($summary['ObjectiveLinkCount'] ?? 0),
            'org unit links' => (int) ($summary['OrgUnitLinkCount'] ?? 0),
        ];

        $blockers = [];
        foreach ($candidates as $label => $count) {
            if ($count > 0) {
                $blockers[] = $label . ' (' . $count . ')';
            }
        }

        return $blockers;
    }

    public function projectBelongsToDataObject(?int $projectId, int $fiscalYearId, string $dataObjectCode): bool
    {
        if ($projectId === null || $projectId <= 0) {
            return true;
        }

        foreach ($this->listProjectOptionsForDataObject($fiscalYearId, $dataObjectCode) as $option) {
            if ((int) ($option['ProjectID'] ?? 0) === $projectId) {
                return true;
            }
        }

        return false;
    }

    public function dataObjectCodeFallsWithinScope(int $fiscalYearId, string $candidateCode, string $rootCode): bool
    {
        $candidateCode = trim($candidateCode);
        $rootCode = trim($rootCode);
        if ($fiscalYearId <= 0 || $candidateCode === '' || $rootCode === '') {
            return false;
        }
        if ($candidateCode === $rootCode) {
            return true;
        }

        return in_array($candidateCode, $this->getDataObjectDescendantScope($fiscalYearId, $rootCode), true);
    }

    public function listOutputOptions(): array
    {
        $stmt = $this->conn->query("
            SELECT o.OutputID, o.OutputName, p.ProgramName
            FROM dbo.tblSbOutput o
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = o.ProgramID
            WHERE o.ActiveFlag = 1
            ORDER BY p.ProgramName ASC, o.OutputName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listActivityOptions(): array
    {
        $projectJoin = '';
        $projectSelect = 'CAST(NULL AS INT) AS ProjectID, CAST(NULL AS NVARCHAR(50)) AS ProjectCode, CAST(NULL AS NVARCHAR(200)) AS ProjectName,';
        if ($this->tableColumnExists('dbo.tblSbActivity', 'ProjectID')) {
            $projectJoin = "
            LEFT JOIN dbo.tblSbProject pr
                ON pr.ProjectID = a.ProjectID";
            $projectSelect = 'a.ProjectID, pr.ProjectCode, pr.ProjectName,';
        }
        $stmt = $this->conn->query("
            SELECT a.ActivityID, a.ActivityName, a.SourceSegmentCode AS ActivityCode, {$projectSelect} o.OutputName, p.ProgramName, p.ProgramCode
            FROM dbo.tblSbActivity a
            INNER JOIN dbo.tblSbOutput o
                ON o.OutputID = a.OutputID
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = o.ProgramID
            {$projectJoin}
            WHERE a.ActiveFlag = 1
            ORDER BY CASE WHEN COALESCE(p.ProgramCode, N'') = N'' THEN 1 ELSE 0 END,
                     p.ProgramCode ASC,
                     o.OutputName ASC,
                     CASE WHEN COALESCE(a.SourceSegmentCode, N'') = N'' THEN 1 ELSE 0 END,
                     a.SourceSegmentCode ASC,
                     a.ActivityName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listActivityOptionsForDataObject(int $fiscalYearId, string $dataObjectCode): array
    {
        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $dataObjectCode);
        if ($scopeCodes === []) {
            $scopeCodes = [trim($dataObjectCode)];
        }
        $scopeCodes = array_values(array_filter(array_map('trim', $scopeCodes), static fn(string $code): bool => $code !== ''));
        if ($scopeCodes === []) {
            return $this->listActivityOptions();
        }

        $params = [];
        $scopeSqlProgram = $this->buildScopeInListParams($scopeCodes, 'scopeActivityProgram', $params);
        $scopeSqlOwner = $this->buildScopeInListParams($scopeCodes, 'scopeActivityOwner', $params);

        if ($this->supportsProgramOrgLinks()) {
            $scopeSqlLinked = $this->buildScopeInListParams($scopeCodes, 'scopeActivityLinked', $params);
            $stmt = $this->conn->prepare("
                SELECT DISTINCT
                    a.ActivityID,
                    a.ActivityName,
                    a.SourceSegmentCode AS ActivityCode,
                    a.OutputID,
                    o.OutputName,
                    o.SubProgramID,
                    p.ProgramID,
                    p.ProgramName,
                    p.ProgramCode,
                    p.SectorID,
                    p.OrgUnitID
                FROM dbo.tblSbActivity a
                INNER JOIN dbo.tblSbOutput o
                    ON o.OutputID = a.OutputID
                INNER JOIN dbo.tblSbProgram p
                    ON p.ProgramID = o.ProgramID
                INNER JOIN dbo.tblSbOrgUnit ownerOu
                    ON ownerOu.OrgUnitID = p.OrgUnitID
                LEFT JOIN dbo.tblSbProgramOrgLink pol
                    ON pol.ProgramID = p.ProgramID
                   AND pol.ActiveFlag = 1
                LEFT JOIN dbo.tblSbOrgUnit linkedOu
                    ON linkedOu.OrgUnitID = pol.OrgUnitID
                WHERE a.ActiveFlag = 1
                  AND o.ActiveFlag = 1
                  AND p.ActiveFlag = 1
                  AND (
                        p.SourceDataObjectCode IN ({$scopeSqlProgram})
                     OR ownerOu.SourceDataObjectCode IN ({$scopeSqlOwner})
                     OR linkedOu.SourceDataObjectCode IN ({$scopeSqlLinked})
                  )
                ORDER BY p.ProgramCode ASC,
                         o.OutputName ASC,
                         a.SourceSegmentCode ASC,
                         a.ActivityName ASC
            ");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->conn->prepare("
            SELECT DISTINCT
                a.ActivityID,
                a.ActivityName,
                a.SourceSegmentCode AS ActivityCode,
                a.OutputID,
                o.OutputName,
                o.SubProgramID,
                p.ProgramID,
                p.ProgramName,
                p.ProgramCode,
                p.SectorID,
                p.OrgUnitID
            FROM dbo.tblSbActivity a
            INNER JOIN dbo.tblSbOutput o
                ON o.OutputID = a.OutputID
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = o.ProgramID
            INNER JOIN dbo.tblSbOrgUnit ownerOu
                ON ownerOu.OrgUnitID = p.OrgUnitID
            WHERE a.ActiveFlag = 1
              AND o.ActiveFlag = 1
              AND p.ActiveFlag = 1
              AND (
                    p.SourceDataObjectCode IN ({$scopeSqlProgram})
                 OR ownerOu.SourceDataObjectCode IN ({$scopeSqlOwner})
              )
            ORDER BY p.ProgramCode ASC,
                     o.OutputName ASC,
                     a.SourceSegmentCode ASC,
                     a.ActivityName ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listEconomicItemOptions(): array
    {
        $stmt = $this->conn->query("
            SELECT EconomicItemID, EconomicCode, EconomicName
            FROM dbo.tblSbEconomicItem
            WHERE ActiveFlag = 1
            ORDER BY EconomicCode ASC, EconomicName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listFundingSourceOptions(): array
    {
        $fundingSourceCodeExpr = 'CAST(NULL AS NVARCHAR(100)) AS FundingSourceCode';
        $fundingSourceCodeOrderExpr = 'CAST(NULL AS NVARCHAR(100))';
        if ($this->tableColumnExists('dbo.tblSbFundingSource', 'FundingSourceCode')) {
            $fundingSourceCodeExpr = 'fs.FundingSourceCode';
            $fundingSourceCodeOrderExpr = 'fs.FundingSourceCode';
        } elseif ($this->tableColumnExists('dbo.tblSbFundingSource', 'SourceSegmentCode')) {
            $fundingSourceCodeExpr = 'fs.SourceSegmentCode AS FundingSourceCode';
            $fundingSourceCodeOrderExpr = 'fs.SourceSegmentCode';
        }

        $stmt = $this->conn->query("
            SELECT
                fs.FundingSourceID,
                fs.FundingSourceName,
                {$fundingSourceCodeExpr},
                COALESCE(ft.FundingTypeID, fs.FundingTypeID, 0) AS FundingTypeID,
                COALESCE(ft.FundingTypeCode, fs.FundingTypeCode) AS FundingTypeCode
            FROM dbo.tblSbFundingSource fs
            LEFT JOIN dbo.tblSbFundingType ft
                ON ft.FundingTypeCode = fs.FundingTypeCode
               AND ft.ActiveFlag = 1
            WHERE fs.ActiveFlag = 1
            ORDER BY CASE WHEN COALESCE({$fundingSourceCodeOrderExpr}, N'') = N'' THEN 1 ELSE 0 END,
                     {$fundingSourceCodeOrderExpr} ASC,
                     fs.FundingSourceName ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function supportsResourceEnvelope(): bool
    {
        return $this->tableExists('dbo.tblSbResourceEnvelope');
    }

    public function supportsResourceEnvelopeMtffAttributes(): bool
    {
        return $this->supportsResourceEnvelope()
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'ReliabilityCode')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictionCode')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'FinancingInstrumentCode')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'OuterYearAssumptionBasisCode');
    }

    public function supportsResourceEnvelopeRestrictionDetails(): bool
    {
        return $this->supportsResourceEnvelope()
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictionScopeTypeCode')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictionReference')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictionDescription');
    }

    public function supportsResourceEnvelopeRestrictionTargets(): bool
    {
        return $this->supportsResourceEnvelope()
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictedSectorID')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictedProgramID')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictedSubProgramID')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictedOrgUnitID')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictedActivityID')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictedEconomicItemID')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictedProjectReference');
    }

    public function supportsActivityProjectLink(): bool
    {
        return $this->tableColumnExists('dbo.tblSbActivity', 'ProjectID');
    }

    public function supportsResourceEnvelopeProjectTargetId(): bool
    {
        return $this->supportsResourceEnvelope() && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'RestrictedProjectID');
    }

    public function supportsResourceEnvelopeExtendedOutYears(): bool
    {
        return $this->supportsResourceEnvelope()
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'OuterYear3Amount')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'OuterYear4Amount')
            && $this->tableColumnExists('dbo.tblSbResourceEnvelope', 'OuterYear5Amount');
    }

    public function getResourceEnvelopeReliabilityOptions(): array
    {
        return [
            ['code' => 'CONFIRMED', 'label' => 'Confirmed'],
            ['code' => 'INDICATIVE', 'label' => 'Indicative'],
            ['code' => 'PIPELINE', 'label' => 'Pipeline'],
            ['code' => 'PROVISIONAL', 'label' => 'Provisional'],
        ];
    }

    public function getResourceEnvelopeRestrictionOptions(): array
    {
        return [
            ['code' => 'DISCRETIONARY', 'label' => 'Discretionary'],
            ['code' => 'EARMARKED', 'label' => 'Earmarked'],
            ['code' => 'RINGFENCED', 'label' => 'Ringfenced'],
        ];
    }

    public function getResourceEnvelopeRestrictionScopeOptions(): array
    {
        return [
            ['code' => 'SECTOR', 'label' => 'Sector'],
            ['code' => 'PROGRAM', 'label' => 'Program'],
            ['code' => 'SUBPROGRAM', 'label' => 'SubProgram'],
            ['code' => 'ORGUNIT', 'label' => 'Ministry / Org Unit'],
            ['code' => 'PROJECT', 'label' => 'Project'],
            ['code' => 'ECONOMIC', 'label' => 'Economic Item'],
            ['code' => 'OTHER', 'label' => 'Other'],
        ];
    }

    public function getResourceEnvelopeFinancingInstrumentOptions(): array
    {
        return [
            ['code' => 'TAX', 'label' => 'Tax Revenue'],
            ['code' => 'NON_TAX', 'label' => 'Non-Tax Revenue'],
            ['code' => 'BUDGET_SUPPORT_GRANT', 'label' => 'Budget Support Grant'],
            ['code' => 'PROJECT_GRANT', 'label' => 'Project Grant'],
            ['code' => 'CONCESSIONAL_LOAN', 'label' => 'Concessional Loan'],
            ['code' => 'COMMERCIAL_BORROWING', 'label' => 'Commercial Borrowing'],
            ['code' => 'OTHER_FINANCING', 'label' => 'Other Financing'],
        ];
    }

    public function getResourceEnvelopeAssumptionBasisOptions(): array
    {
        return [
            ['code' => 'BASELINE', 'label' => 'Baseline'],
            ['code' => 'PROJECTED', 'label' => 'Projected'],
            ['code' => 'POLICY_ADJUSTED', 'label' => 'Policy Adjusted'],
            ['code' => 'SCENARIO', 'label' => 'Scenario-Based'],
        ];
    }

    public function listResourceEnvelopeLines(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->supportsResourceEnvelope()) {
            return [];
        }

        $mtffAttributesReady = $this->supportsResourceEnvelopeMtffAttributes();
        $restrictionDetailsReady = $this->supportsResourceEnvelopeRestrictionDetails();
        $restrictionTargetsReady = $this->supportsResourceEnvelopeRestrictionTargets();
        $extendedOutYearsReady = $this->supportsResourceEnvelopeExtendedOutYears();
        $projectTargetIdReady = $this->supportsResourceEnvelopeProjectTargetId();
        $fundingSourceCodeExpr = 'CAST(NULL AS NVARCHAR(100)) AS FundingSourceCode';
        if ($this->tableColumnExists('dbo.tblSbFundingSource', 'FundingSourceCode')) {
            $fundingSourceCodeExpr = 'fs.FundingSourceCode';
        } elseif ($this->tableColumnExists('dbo.tblSbFundingSource', 'SourceSegmentCode')) {
            $fundingSourceCodeExpr = 'fs.SourceSegmentCode AS FundingSourceCode';
        }

        $stmt = $this->conn->prepare("
            SELECT
                re.ResourceEnvelopeID,
                re.FiscalYearID,
                re.VersionID,
                re.FundingTypeID,
                re.FundingSourceID,
                ft.FundingTypeCode,
                ft.FundingTypeName,
                " . $fundingSourceCodeExpr . ",
                fs.FundingSourceName,
                " . ($mtffAttributesReady ? "re.ReliabilityCode" : "CAST(NULL AS NVARCHAR(30)) AS ReliabilityCode") . ",
                " . ($mtffAttributesReady ? "re.RestrictionCode" : "CAST(NULL AS NVARCHAR(30)) AS RestrictionCode") . ",
                " . ($restrictionDetailsReady ? "re.RestrictionScopeTypeCode" : "CAST(NULL AS NVARCHAR(30)) AS RestrictionScopeTypeCode") . ",
                " . ($restrictionDetailsReady ? "re.RestrictionReference" : "CAST(NULL AS NVARCHAR(100)) AS RestrictionReference") . ",
                " . ($restrictionDetailsReady ? "re.RestrictionDescription" : "CAST(NULL AS NVARCHAR(255)) AS RestrictionDescription") . ",
                " . ($restrictionTargetsReady ? "re.RestrictedSectorID" : "CAST(NULL AS INT) AS RestrictedSectorID") . ",
                " . ($restrictionTargetsReady ? "re.RestrictedProgramID" : "CAST(NULL AS INT) AS RestrictedProgramID") . ",
                " . ($restrictionTargetsReady ? "re.RestrictedSubProgramID" : "CAST(NULL AS INT) AS RestrictedSubProgramID") . ",
                " . ($restrictionTargetsReady ? "re.RestrictedOrgUnitID" : "CAST(NULL AS INT) AS RestrictedOrgUnitID") . ",
                " . ($restrictionTargetsReady ? "re.RestrictedActivityID" : "CAST(NULL AS INT) AS RestrictedActivityID") . ",
                " . ($restrictionTargetsReady ? "re.RestrictedEconomicItemID" : "CAST(NULL AS INT) AS RestrictedEconomicItemID") . ",
                " . ($projectTargetIdReady ? "re.RestrictedProjectID" : "CAST(NULL AS INT) AS RestrictedProjectID") . ",
                " . ($restrictionTargetsReady ? "re.RestrictedProjectReference" : "CAST(NULL AS NVARCHAR(100)) AS RestrictedProjectReference") . ",
                " . ($mtffAttributesReady ? "re.FinancingInstrumentCode" : "CAST(NULL AS NVARCHAR(50)) AS FinancingInstrumentCode") . ",
                " . ($mtffAttributesReady ? "re.OuterYearAssumptionBasisCode" : "CAST(NULL AS NVARCHAR(50)) AS OuterYearAssumptionBasisCode") . ",
                re.CurrentYearAmount,
                re.BP1Amount,
                re.BP2Amount,
                re.BP3Amount,
                re.BP4Amount,
                re.BP5Amount,
                re.BP6Amount,
                re.BP7Amount,
                re.BP8Amount,
                re.BP9Amount,
                re.BP10Amount,
                re.BP11Amount,
                re.BP12Amount,
                re.OuterYear1Amount,
                re.OuterYear2Amount,
                " . ($extendedOutYearsReady ? "re.OuterYear3Amount" : "CAST(NULL AS DECIMAL(19,6)) AS OuterYear3Amount") . ",
                " . ($extendedOutYearsReady ? "re.OuterYear4Amount" : "CAST(NULL AS DECIMAL(19,6)) AS OuterYear4Amount") . ",
                " . ($extendedOutYearsReady ? "re.OuterYear5Amount" : "CAST(NULL AS DECIMAL(19,6)) AS OuterYear5Amount") . ",
                re.EnvelopeNotes,
                re.ActiveFlag,
                CASE
                    WHEN re.BP1Amount IS NOT NULL OR re.BP2Amount IS NOT NULL OR re.BP3Amount IS NOT NULL OR re.BP4Amount IS NOT NULL
                      OR re.BP5Amount IS NOT NULL OR re.BP6Amount IS NOT NULL OR re.BP7Amount IS NOT NULL OR re.BP8Amount IS NOT NULL
                      OR re.BP9Amount IS NOT NULL OR re.BP10Amount IS NOT NULL OR re.BP11Amount IS NOT NULL OR re.BP12Amount IS NOT NULL
                    THEN 1 ELSE 0
                END AS HasPhasing
            FROM dbo.tblSbResourceEnvelope re
            INNER JOIN dbo.tblSbFundingType ft
                ON ft.FundingTypeID = re.FundingTypeID
            LEFT JOIN dbo.tblSbFundingSource fs
                ON fs.FundingSourceID = re.FundingSourceID
            WHERE re.FiscalYearID = :fy
              AND re.VersionID = :ver
              AND re.ActiveFlag = 1
            ORDER BY ft.FundingTypeName ASC, fs.FundingSourceName ASC, re.ResourceEnvelopeID ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getResourceEnvelopeLine(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsResourceEnvelope()) {
            return null;
        }

        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblSbResourceEnvelope WHERE ResourceEnvelopeID = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findResourceEnvelopeLineByContext(
        int $fiscalYearId,
        int $versionId,
        int $fundingTypeId,
        ?int $fundingSourceId = null,
        ?string $reliabilityCode = null,
        ?string $restrictionCode = null,
        ?string $restrictionScopeTypeCode = null,
        ?string $restrictionReference = null,
        ?int $restrictedSectorId = null,
        ?int $restrictedProgramId = null,
        ?int $restrictedSubProgramId = null,
        ?int $restrictedOrgUnitId = null,
        ?int $restrictedActivityId = null,
        ?int $restrictedEconomicItemId = null,
        ?int $restrictedProjectId = null,
        ?string $restrictedProjectReference = null,
        ?string $financingInstrumentCode = null,
        ?string $outerYearAssumptionBasisCode = null,
        ?int $excludeId = null
    ): ?array {
        if ($fiscalYearId <= 0 || $versionId <= 0 || $fundingTypeId <= 0 || !$this->supportsResourceEnvelope()) {
            return null;
        }

        $mtffAttributesReady = $this->supportsResourceEnvelopeMtffAttributes();
        $restrictionDetailsReady = $this->supportsResourceEnvelopeRestrictionDetails();
        $restrictionTargetsReady = $this->supportsResourceEnvelopeRestrictionTargets();
        $projectTargetIdReady = $this->supportsResourceEnvelopeProjectTargetId();

        $sql = "
            SELECT TOP 1 *
            FROM dbo.tblSbResourceEnvelope
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND FundingTypeID = :fundingTypeId
              AND ActiveFlag = 1
        ";
        $params = [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':fundingTypeId' => $fundingTypeId,
        ];

        if ($fundingSourceId !== null && $fundingSourceId > 0) {
            $sql .= " AND FundingSourceID = :fundingSourceId";
            $params[':fundingSourceId'] = $fundingSourceId;
        } else {
            $sql .= " AND FundingSourceID IS NULL";
        }

        if ($mtffAttributesReady) {
            if ($reliabilityCode !== null && trim($reliabilityCode) !== '') {
                $sql .= " AND ReliabilityCode = :reliabilityCode";
                $params[':reliabilityCode'] = trim($reliabilityCode);
            } else {
                $sql .= " AND ReliabilityCode IS NULL";
            }

            if ($restrictionCode !== null && trim($restrictionCode) !== '') {
                $sql .= " AND RestrictionCode = :restrictionCode";
                $params[':restrictionCode'] = trim($restrictionCode);
            } else {
                $sql .= " AND RestrictionCode IS NULL";
            }

            if ($restrictionDetailsReady) {
                if ($restrictionScopeTypeCode !== null && trim($restrictionScopeTypeCode) !== '') {
                    $sql .= " AND RestrictionScopeTypeCode = :restrictionScopeTypeCode";
                    $params[':restrictionScopeTypeCode'] = trim($restrictionScopeTypeCode);
                } else {
                    $sql .= " AND RestrictionScopeTypeCode IS NULL";
                }

                if ($restrictionReference !== null && trim($restrictionReference) !== '') {
                    $sql .= " AND RestrictionReference = :restrictionReference";
                    $params[':restrictionReference'] = trim($restrictionReference);
                } else {
                    $sql .= " AND RestrictionReference IS NULL";
                }
            }

            if ($restrictionTargetsReady) {
                $targetMap = [
                    'RestrictedSectorID' => $restrictedSectorId,
                    'RestrictedProgramID' => $restrictedProgramId,
                    'RestrictedSubProgramID' => $restrictedSubProgramId,
                    'RestrictedOrgUnitID' => $restrictedOrgUnitId,
                    'RestrictedActivityID' => $restrictedActivityId,
                    'RestrictedEconomicItemID' => $restrictedEconomicItemId,
                ];
                foreach ($targetMap as $column => $value) {
                    $paramName = ':' . $column;
                    if ($value !== null && $value > 0) {
                        $sql .= " AND {$column} = {$paramName}";
                        $params[$paramName] = $value;
                    } else {
                        $sql .= " AND {$column} IS NULL";
                    }
                }

                if ($projectTargetIdReady) {
                    if ($restrictedProjectId !== null && $restrictedProjectId > 0) {
                        $sql .= " AND RestrictedProjectID = :restrictedProjectId";
                        $params[':restrictedProjectId'] = $restrictedProjectId;
                    } else {
                        $sql .= " AND RestrictedProjectID IS NULL";
                    }
                }

                if ($restrictedProjectReference !== null && trim($restrictedProjectReference) !== '') {
                    $sql .= " AND RestrictedProjectReference = :restrictedProjectReference";
                    $params[':restrictedProjectReference'] = trim($restrictedProjectReference);
                } else {
                    $sql .= " AND RestrictedProjectReference IS NULL";
                }
            }

            if ($financingInstrumentCode !== null && trim($financingInstrumentCode) !== '') {
                $sql .= " AND FinancingInstrumentCode = :financingInstrumentCode";
                $params[':financingInstrumentCode'] = trim($financingInstrumentCode);
            } else {
                $sql .= " AND FinancingInstrumentCode IS NULL";
            }

            if ($outerYearAssumptionBasisCode !== null && trim($outerYearAssumptionBasisCode) !== '') {
                $sql .= " AND OuterYearAssumptionBasisCode = :outerYearAssumptionBasisCode";
                $params[':outerYearAssumptionBasisCode'] = trim($outerYearAssumptionBasisCode);
            } else {
                $sql .= " AND OuterYearAssumptionBasisCode IS NULL";
            }
        }

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= " AND ResourceEnvelopeID <> :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $sql .= " ORDER BY ResourceEnvelopeID ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveResourceEnvelopeLine(array $data, ?int $id = null): int
    {
        if (!$this->supportsResourceEnvelope()) {
            throw new \RuntimeException('Resource envelope table is not installed.');
        }

        $mtffAttributesReady = $this->supportsResourceEnvelopeMtffAttributes();
        $restrictionDetailsReady = $this->supportsResourceEnvelopeRestrictionDetails();
        $restrictionTargetsReady = $this->supportsResourceEnvelopeRestrictionTargets();
        $projectTargetIdReady = $this->supportsResourceEnvelopeProjectTargetId();
        $extendedOutYearsReady = $this->supportsResourceEnvelopeExtendedOutYears();

        if ($id !== null && $id > 0) {
            $sql = "
                UPDATE dbo.tblSbResourceEnvelope
                SET FiscalYearID = :FiscalYearID,
                    VersionID = :VersionID,
                    FundingTypeID = :FundingTypeID,
                    FundingSourceID = :FundingSourceID" .
                    ($mtffAttributesReady ? ",
                    ReliabilityCode = :ReliabilityCode,
                    RestrictionCode = :RestrictionCode" : "") .
                    ($restrictionDetailsReady ? ",
                    RestrictionScopeTypeCode = :RestrictionScopeTypeCode,
                    RestrictionReference = :RestrictionReference,
                    RestrictionDescription = :RestrictionDescription" : "") .
                    ($restrictionTargetsReady ? ",
                    RestrictedSectorID = :RestrictedSectorID,
                    RestrictedProgramID = :RestrictedProgramID,
                    RestrictedSubProgramID = :RestrictedSubProgramID,
                    RestrictedOrgUnitID = :RestrictedOrgUnitID,
                    RestrictedActivityID = :RestrictedActivityID,
                    RestrictedEconomicItemID = :RestrictedEconomicItemID" .
                    ($projectTargetIdReady ? ",
                    RestrictedProjectID = :RestrictedProjectID" : '') . ",
                    RestrictedProjectReference = :RestrictedProjectReference" : "") .
                    ($mtffAttributesReady ? ",
                    FinancingInstrumentCode = :FinancingInstrumentCode,
                    OuterYearAssumptionBasisCode = :OuterYearAssumptionBasisCode" : "") . ",
                    CurrentYearAmount = :CurrentYearAmount,
                    BP1Amount = :BP1Amount,
                    BP2Amount = :BP2Amount,
                    BP3Amount = :BP3Amount,
                    BP4Amount = :BP4Amount,
                    BP5Amount = :BP5Amount,
                    BP6Amount = :BP6Amount,
                    BP7Amount = :BP7Amount,
                    BP8Amount = :BP8Amount,
                    BP9Amount = :BP9Amount,
                    BP10Amount = :BP10Amount,
                    BP11Amount = :BP11Amount,
                    BP12Amount = :BP12Amount,
                    OuterYear1Amount = :OuterYear1Amount,
                    OuterYear2Amount = :OuterYear2Amount" .
                    ($extendedOutYearsReady ? ",
                    OuterYear3Amount = :OuterYear3Amount,
                    OuterYear4Amount = :OuterYear4Amount,
                    OuterYear5Amount = :OuterYear5Amount" : "") . ",
                    EnvelopeNotes = :EnvelopeNotes,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ResourceEnvelopeID = :ResourceEnvelopeID
            ";
            $params = [
                ':FiscalYearID' => $data['FiscalYearID'],
                ':VersionID' => $data['VersionID'],
                ':FundingTypeID' => $data['FundingTypeID'],
                ':FundingSourceID' => $data['FundingSourceID'],
                ':CurrentYearAmount' => $data['CurrentYearAmount'],
                ':BP1Amount' => $data['BP1Amount'],
                ':BP2Amount' => $data['BP2Amount'],
                ':BP3Amount' => $data['BP3Amount'],
                ':BP4Amount' => $data['BP4Amount'],
                ':BP5Amount' => $data['BP5Amount'],
                ':BP6Amount' => $data['BP6Amount'],
                ':BP7Amount' => $data['BP7Amount'],
                ':BP8Amount' => $data['BP8Amount'],
                ':BP9Amount' => $data['BP9Amount'],
                ':BP10Amount' => $data['BP10Amount'],
                ':BP11Amount' => $data['BP11Amount'],
                ':BP12Amount' => $data['BP12Amount'],
                ':OuterYear1Amount' => $data['OuterYear1Amount'],
                ':OuterYear2Amount' => $data['OuterYear2Amount'],
                ':EnvelopeNotes' => $data['EnvelopeNotes'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':ResourceEnvelopeID' => $id,
            ];
            if ($mtffAttributesReady) {
                $params[':ReliabilityCode'] = $data['ReliabilityCode'];
                $params[':RestrictionCode'] = $data['RestrictionCode'];
                $params[':FinancingInstrumentCode'] = $data['FinancingInstrumentCode'];
                $params[':OuterYearAssumptionBasisCode'] = $data['OuterYearAssumptionBasisCode'];
            }
            if ($restrictionDetailsReady) {
                $params[':RestrictionScopeTypeCode'] = $data['RestrictionScopeTypeCode'];
                $params[':RestrictionReference'] = $data['RestrictionReference'];
                $params[':RestrictionDescription'] = $data['RestrictionDescription'];
            }
            if ($restrictionTargetsReady) {
                $params[':RestrictedSectorID'] = $data['RestrictedSectorID'];
                $params[':RestrictedProgramID'] = $data['RestrictedProgramID'];
                $params[':RestrictedSubProgramID'] = $data['RestrictedSubProgramID'];
                $params[':RestrictedOrgUnitID'] = $data['RestrictedOrgUnitID'];
                $params[':RestrictedActivityID'] = $data['RestrictedActivityID'];
                $params[':RestrictedEconomicItemID'] = $data['RestrictedEconomicItemID'];
                if ($projectTargetIdReady) {
                    $params[':RestrictedProjectID'] = $data['RestrictedProjectID'];
                }
                $params[':RestrictedProjectReference'] = $data['RestrictedProjectReference'];
            }
            if ($extendedOutYearsReady) {
                $params[':OuterYear3Amount'] = $data['OuterYear3Amount'];
                $params[':OuterYear4Amount'] = $data['OuterYear4Amount'];
                $params[':OuterYear5Amount'] = $data['OuterYear5Amount'];
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $id;
        }

        $sql = "
            INSERT INTO dbo.tblSbResourceEnvelope (
                FiscalYearID,
                VersionID,
                FundingTypeID,
                FundingSourceID" .
                ($mtffAttributesReady ? ",
                ReliabilityCode,
                RestrictionCode" : "") .
                ($restrictionDetailsReady ? ",
                RestrictionScopeTypeCode,
                RestrictionReference,
                RestrictionDescription" : "") .
                ($restrictionTargetsReady ? ",
                RestrictedSectorID,
                RestrictedProgramID,
                RestrictedSubProgramID,
                RestrictedOrgUnitID,
                RestrictedActivityID,
                RestrictedEconomicItemID" .
                ($projectTargetIdReady ? ",
                RestrictedProjectID" : '') . ",
                RestrictedProjectReference" : "") .
                ($mtffAttributesReady ? ",
                FinancingInstrumentCode,
                OuterYearAssumptionBasisCode" : "") . ",
                CurrentYearAmount,
                BP1Amount,
                BP2Amount,
                BP3Amount,
                BP4Amount,
                BP5Amount,
                BP6Amount,
                BP7Amount,
                BP8Amount,
                BP9Amount,
                BP10Amount,
                BP11Amount,
                BP12Amount,
                OuterYear1Amount,
                OuterYear2Amount" .
                ($extendedOutYearsReady ? ",
                OuterYear3Amount,
                OuterYear4Amount,
                OuterYear5Amount" : "") . ",
                EnvelopeNotes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :FiscalYearID,
                :VersionID,
                :FundingTypeID,
                :FundingSourceID" .
                ($mtffAttributesReady ? ",
                :ReliabilityCode,
                :RestrictionCode" : "") .
                ($restrictionDetailsReady ? ",
                :RestrictionScopeTypeCode,
                :RestrictionReference,
                :RestrictionDescription" : "") .
                ($restrictionTargetsReady ? ",
                :RestrictedSectorID,
                :RestrictedProgramID,
                :RestrictedSubProgramID,
                :RestrictedOrgUnitID,
                :RestrictedActivityID,
                :RestrictedEconomicItemID" .
                ($projectTargetIdReady ? ",
                :RestrictedProjectID" : '') . ",
                :RestrictedProjectReference" : "") .
                ($mtffAttributesReady ? ",
                :FinancingInstrumentCode,
                :OuterYearAssumptionBasisCode" : "") . ",
                :CurrentYearAmount,
                :BP1Amount,
                :BP2Amount,
                :BP3Amount,
                :BP4Amount,
                :BP5Amount,
                :BP6Amount,
                :BP7Amount,
                :BP8Amount,
                :BP9Amount,
                :BP10Amount,
                :BP11Amount,
                :BP12Amount,
                :OuterYear1Amount,
                :OuterYear2Amount" .
                ($extendedOutYearsReady ? ",
                :OuterYear3Amount,
                :OuterYear4Amount,
                :OuterYear5Amount" : "") . ",
                :EnvelopeNotes,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ";
        $params = [
            ':FiscalYearID' => $data['FiscalYearID'],
            ':VersionID' => $data['VersionID'],
            ':FundingTypeID' => $data['FundingTypeID'],
            ':FundingSourceID' => $data['FundingSourceID'],
            ':CurrentYearAmount' => $data['CurrentYearAmount'],
            ':BP1Amount' => $data['BP1Amount'],
            ':BP2Amount' => $data['BP2Amount'],
            ':BP3Amount' => $data['BP3Amount'],
            ':BP4Amount' => $data['BP4Amount'],
            ':BP5Amount' => $data['BP5Amount'],
            ':BP6Amount' => $data['BP6Amount'],
            ':BP7Amount' => $data['BP7Amount'],
            ':BP8Amount' => $data['BP8Amount'],
            ':BP9Amount' => $data['BP9Amount'],
            ':BP10Amount' => $data['BP10Amount'],
            ':BP11Amount' => $data['BP11Amount'],
            ':BP12Amount' => $data['BP12Amount'],
            ':OuterYear1Amount' => $data['OuterYear1Amount'],
            ':OuterYear2Amount' => $data['OuterYear2Amount'],
            ':EnvelopeNotes' => $data['EnvelopeNotes'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ];
        if ($mtffAttributesReady) {
            $params[':ReliabilityCode'] = $data['ReliabilityCode'];
            $params[':RestrictionCode'] = $data['RestrictionCode'];
            $params[':FinancingInstrumentCode'] = $data['FinancingInstrumentCode'];
            $params[':OuterYearAssumptionBasisCode'] = $data['OuterYearAssumptionBasisCode'];
        }
        if ($restrictionDetailsReady) {
            $params[':RestrictionScopeTypeCode'] = $data['RestrictionScopeTypeCode'];
            $params[':RestrictionReference'] = $data['RestrictionReference'];
            $params[':RestrictionDescription'] = $data['RestrictionDescription'];
        }
        if ($restrictionTargetsReady) {
            $params[':RestrictedSectorID'] = $data['RestrictedSectorID'];
            $params[':RestrictedProgramID'] = $data['RestrictedProgramID'];
            $params[':RestrictedSubProgramID'] = $data['RestrictedSubProgramID'];
            $params[':RestrictedOrgUnitID'] = $data['RestrictedOrgUnitID'];
            $params[':RestrictedActivityID'] = $data['RestrictedActivityID'];
            $params[':RestrictedEconomicItemID'] = $data['RestrictedEconomicItemID'];
            if ($projectTargetIdReady) {
                $params[':RestrictedProjectID'] = $data['RestrictedProjectID'];
            }
            $params[':RestrictedProjectReference'] = $data['RestrictedProjectReference'];
        }
        if ($extendedOutYearsReady) {
            $params[':OuterYear3Amount'] = $data['OuterYear3Amount'];
            $params[':OuterYear4Amount'] = $data['OuterYear4Amount'];
            $params[':OuterYear5Amount'] = $data['OuterYear5Amount'];
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $this->conn->lastInsertId();
    }

    public function deactivateResourceEnvelopeLine(int $id, int $userId): void
    {
        if ($id <= 0 || !$this->supportsResourceEnvelope()) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbResourceEnvelope
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ResourceEnvelopeID = :id
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':id' => $id,
        ]);
    }

    public function getResourceEnvelopeSummary(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->supportsResourceEnvelope()) {
            return [
                'LineCount' => 0,
                'CurrentYearAmount' => 0.0,
                'OuterYear1Amount' => 0.0,
                'OuterYear2Amount' => 0.0,
                'OuterYear3Amount' => 0.0,
                'OuterYear4Amount' => 0.0,
                'OuterYear5Amount' => 0.0,
                'BP1Amount' => 0.0,
                'BP2Amount' => 0.0,
                'BP3Amount' => 0.0,
                'BP4Amount' => 0.0,
                'BP5Amount' => 0.0,
                'BP6Amount' => 0.0,
                'BP7Amount' => 0.0,
                'BP8Amount' => 0.0,
                'BP9Amount' => 0.0,
                'BP10Amount' => 0.0,
                'BP11Amount' => 0.0,
                'BP12Amount' => 0.0,
            ];
        }

        $extendedOutYearsReady = $this->supportsResourceEnvelopeExtendedOutYears();

        $stmt = $this->conn->prepare("
            SELECT
                COUNT(*) AS LineCount,
                COALESCE(SUM(CurrentYearAmount), 0) AS CurrentYearAmount,
                COALESCE(SUM(OuterYear1Amount), 0) AS OuterYear1Amount,
                COALESCE(SUM(OuterYear2Amount), 0) AS OuterYear2Amount,
                " . ($extendedOutYearsReady ? "COALESCE(SUM(OuterYear3Amount), 0)" : "CAST(0 AS DECIMAL(19,6))") . " AS OuterYear3Amount,
                " . ($extendedOutYearsReady ? "COALESCE(SUM(OuterYear4Amount), 0)" : "CAST(0 AS DECIMAL(19,6))") . " AS OuterYear4Amount,
                " . ($extendedOutYearsReady ? "COALESCE(SUM(OuterYear5Amount), 0)" : "CAST(0 AS DECIMAL(19,6))") . " AS OuterYear5Amount,
                COALESCE(SUM(BP1Amount), 0) AS BP1Amount,
                COALESCE(SUM(BP2Amount), 0) AS BP2Amount,
                COALESCE(SUM(BP3Amount), 0) AS BP3Amount,
                COALESCE(SUM(BP4Amount), 0) AS BP4Amount,
                COALESCE(SUM(BP5Amount), 0) AS BP5Amount,
                COALESCE(SUM(BP6Amount), 0) AS BP6Amount,
                COALESCE(SUM(BP7Amount), 0) AS BP7Amount,
                COALESCE(SUM(BP8Amount), 0) AS BP8Amount,
                COALESCE(SUM(BP9Amount), 0) AS BP9Amount,
                COALESCE(SUM(BP10Amount), 0) AS BP10Amount,
                COALESCE(SUM(BP11Amount), 0) AS BP11Amount,
                COALESCE(SUM(BP12Amount), 0) AS BP12Amount
            FROM dbo.tblSbResourceEnvelope
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function listFiscalRiskOptions(): array
    {
        $stmt = $this->conn->query("
            SELECT FiscalRiskID, RiskTitle, RiskTypeCode
            FROM dbo.tblSbFiscalRisk
            WHERE ActiveFlag = 1
            ORDER BY RiskTitle ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listDataScopeOrgUnitOptions(int $fiscalYearId, ?string $status = 'Active'): array
    {
        $sql = "
            SELECT
                c.DataObjectCode,
                c.DataObjectName,
                c.DataObjectCodeParent,
                c.DataObjectTypeID,
                t.DataObjectTypeName,
                t.Level
            FROM dbo.tblDataObjectCodes c
            LEFT JOIN dbo.tblDataObjectTypes t
                ON t.DataObjectTypeID = c.DataObjectTypeID
            WHERE c.FiscalYearID = :fy
        ";
        $params = [':fy' => $fiscalYearId];

        if ($status !== null && $status !== '') {
            $sql .= " AND COALESCE(c.DataObjectCodeStatus, '') = :status";
            $params[':status'] = $status;
        }

        $sql .= ' ORDER BY c.DataObjectCode ASC, c.DataObjectName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function ensureOrgUnitFromDataObject(int $fiscalYearId, string $dataObjectCode, int $userId): int
    {
        $dataObjectCode = trim($dataObjectCode);
        if ($fiscalYearId <= 0 || $dataObjectCode === '') {
            throw new \InvalidArgumentException('Fiscal year and DataObjectCode are required.');
        }

        if ($dataObjectCode === '0') {
            $existingGlobal = $this->conn->prepare("
                SELECT OrgUnitID
                FROM dbo.tblSbOrgUnit
                WHERE SourceFiscalYearID = :fy
                  AND SourceDataObjectCode = '0'
            ");
            $existingGlobal->execute([':fy' => $fiscalYearId]);
            $existingGlobalId = (int) ($existingGlobal->fetchColumn() ?: 0);
            if ($existingGlobalId > 0) {
                return $existingGlobalId;
            }

            $insertGlobal = $this->conn->prepare("
                INSERT INTO dbo.tblSbOrgUnit (
                    ParentOrgUnitID,
                    OrgUnitTypeCode,
                    VoteCode,
                    OrgUnitName,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate,
                    SourceFiscalYearID,
                    SourceDataObjectCode
                )
                VALUES (
                    NULL,
                    'VOTE',
                    NULL,
                    'Global Strategic Scope',
                    1,
                    :CreatedBy,
                    SYSDATETIME(),
                    :SourceFiscalYearID,
                    '0'
                )
            ");
            $insertGlobal->execute([
                ':CreatedBy' => $userId,
                ':SourceFiscalYearID' => $fiscalYearId,
            ]);
            return (int) $this->conn->lastInsertId();
        }

        $existing = $this->conn->prepare("
            SELECT OrgUnitID
            FROM dbo.tblSbOrgUnit
            WHERE SourceFiscalYearID = :fy
              AND SourceDataObjectCode = :code
        ");
        $existing->execute([
            ':fy' => $fiscalYearId,
            ':code' => $dataObjectCode,
        ]);
        $existingId = (int) ($existing->fetchColumn() ?: 0);
        if ($existingId > 0) {
            return $existingId;
        }

        $source = $this->conn->prepare("
            SELECT TOP 1
                c.DataObjectCode,
                c.DataObjectName,
                c.DataObjectCodeParent,
                c.DataObjectTypeID,
                t.DataObjectTypeName,
                t.Level
            FROM dbo.tblDataObjectCodes c
            LEFT JOIN dbo.tblDataObjectTypes t
                ON t.DataObjectTypeID = c.DataObjectTypeID
            WHERE c.FiscalYearID = :fy
              AND c.DataObjectCode = :code
        ");
        $source->execute([
            ':fy' => $fiscalYearId,
            ':code' => $dataObjectCode,
        ]);
        $row = $source->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row === null) {
            throw new \RuntimeException('Selected DataScope org unit was not found for the active fiscal year.');
        }

        $parentOrgUnitId = null;
        $parentCode = trim((string) ($row['DataObjectCodeParent'] ?? ''));
        if ($parentCode !== '') {
            $parentOrgUnitId = $this->ensureOrgUnitFromDataObject($fiscalYearId, $parentCode, $userId);
        }

        $typeCode = $this->mapDataObjectLevelToOrgUnitType((int) ($row['Level'] ?? 0));
        $insert = $this->conn->prepare("
            INSERT INTO dbo.tblSbOrgUnit (
                ParentOrgUnitID,
                OrgUnitTypeCode,
                VoteCode,
                OrgUnitName,
                ActiveFlag,
                CreatedBy,
                CreatedDate,
                SourceFiscalYearID,
                SourceDataObjectCode
            )
            VALUES (
                :ParentOrgUnitID,
                :OrgUnitTypeCode,
                :VoteCode,
                :OrgUnitName,
                1,
                :CreatedBy,
                SYSDATETIME(),
                :SourceFiscalYearID,
                :SourceDataObjectCode
            )
        ");
        $insert->execute([
            ':ParentOrgUnitID' => $parentOrgUnitId,
            ':OrgUnitTypeCode' => $typeCode,
            ':VoteCode' => (string) ($row['DataObjectCode'] ?? ''),
            ':OrgUnitName' => (string) ($row['DataObjectName'] ?? ''),
            ':CreatedBy' => $userId,
            ':SourceFiscalYearID' => $fiscalYearId,
            ':SourceDataObjectCode' => (string) ($row['DataObjectCode'] ?? ''),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function mapDataObjectLevelToOrgUnitType(int $level): string
    {
        if ($level <= 1) {
            return 'VOTE';
        }
        if ($level === 2) {
            return 'MDA';
        }
        if ($level === 3) {
            return 'AGENCY';
        }
        return 'DEPT';
    }

    private function getProgramOverlayImportStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROGRAM');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return [
                'label' => 'Programs',
                'mapping' => $mapping,
                'source_count' => 0,
                'overlay_count' => 0,
                'missing_count' => 0,
                'status_note' => 'Program segment mapping is not configured.',
            ];
        }

        $stmt = $this->conn->prepare("
            SELECT
                COUNT(*) AS SourceCount,
                SUM(CASE WHEN p.ProgramID IS NOT NULL THEN 1 ELSE 0 END) AS OverlayCount
            FROM (
                SELECT DISTINCT
                    sv.FiscalYearID,
                    sv.DataObjectCode,
                    sv.SegmentCode,
                    sv.SegmentName
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
            ) src
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.SourceFiscalYearID = src.FiscalYearID
               AND ou.SourceDataObjectCode = src.DataObjectCode
            LEFT JOIN dbo.tblSbProgram p
                ON p.OrgUnitID = ou.OrgUnitID
               AND p.ProgramCode = src.SegmentCode
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $sourceCount = (int) ($row['SourceCount'] ?? 0);
        $overlayCount = (int) ($row['OverlayCount'] ?? 0);
        $canAutoAssignSector = $this->canAutoAssignProgramSectors($fiscalYearId);

        $status = [
            'label' => 'Programs',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => true,
            'import_reason' => null,
            'manage_route' => 'strategy-setup/programs',
            'manage_label' => 'Manage Programs',
            'auto_sector_assignment' => $canAutoAssignSector,
            'requires_sector_selection' => !$canAutoAssignSector,
        ];

        if (!$canAutoAssignSector) {
            $status['status_note'] = 'Program import needs a sector assignment for new overlays.';
            return $status;
        }

        $stmt = $this->conn->prepare("
            SELECT DISTINCT
                sv.DataObjectCode,
                sv.ParentSegmentNo,
                sv.ParentSegmentCode
            FROM dbo.tblSegmentValues sv
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.SourceFiscalYearID = sv.FiscalYearID
               AND ou.SourceDataObjectCode = sv.DataObjectCode
            LEFT JOIN dbo.tblSbProgram p
                ON p.OrgUnitID = ou.OrgUnitID
               AND p.ProgramCode = sv.SegmentCode
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.ActiveFlag = 1
              AND p.ProgramID IS NULL
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        $pendingRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $importReadyCount = 0;
        $missingSectorLinkCount = 0;
        $missingSectorOverlayCount = 0;

        foreach ($pendingRows as $pendingRow) {
            $resolution = $this->resolveProgramImportSector(
                $fiscalYearId,
                (string) ($pendingRow['DataObjectCode'] ?? ''),
                (int) ($pendingRow['ParentSegmentNo'] ?? 0),
                (string) ($pendingRow['ParentSegmentCode'] ?? '')
            );

            if (!empty($resolution['sector'])) {
                $importReadyCount++;
            } elseif (($resolution['reason'] ?? '') === 'missing_parent_overlay') {
                $missingSectorOverlayCount++;
            } else {
                $missingSectorLinkCount++;
            }
        }

        $status['import_ready_count'] = $importReadyCount;
        $status['missing_sector_link_count'] = $missingSectorLinkCount;
        $status['missing_sector_overlay_count'] = $missingSectorOverlayCount;
        $status['status_note'] = 'Program import will first try a direct sector source link, then fall back to a sector overlay tied to the same source DataObject scope.';

        return $status;
    }

    private function getSubProgramOverlayImportStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SUBPROGRAM');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        $programMapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROGRAM');
        $programSegmentNo = (int) ($programMapping['SegmentNo'] ?? 0);

        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return [
                'label' => 'SubPrograms',
                'mapping' => $mapping,
                'source_count' => 0,
                'overlay_count' => 0,
                'missing_count' => 0,
                'import_ready_count' => 0,
                'missing_parent_link_count' => 0,
                'missing_parent_overlay_count' => 0,
                'status_note' => 'Subprogram segment mapping is not configured.',
            ];
        }

        if (!$this->tableColumnExists('dbo.tblSegmentValues', 'ParentSegmentNo') || !$this->tableColumnExists('dbo.tblSegmentValues', 'ParentSegmentCode')) {
            return [
                'label' => 'SubPrograms',
                'mapping' => $mapping,
                'source_count' => 0,
                'overlay_count' => 0,
                'missing_count' => 0,
                'import_ready_count' => 0,
                'missing_parent_link_count' => 0,
                'missing_parent_overlay_count' => 0,
                'status_note' => 'Parent segment fields are not available on tblSegmentValues.',
            ];
        }

        $stmt = $this->conn->prepare("
            SELECT
                COUNT(*) AS SourceCount,
                SUM(CASE WHEN existing.SubProgramID IS NOT NULL THEN 1 ELSE 0 END) AS OverlayCount,
                SUM(CASE WHEN existing.SubProgramID IS NULL
                           AND sv.ParentSegmentNo = :programSegmentNoReady
                           AND COALESCE(sv.ParentSegmentCode, '') <> ''
                           AND p.ProgramID IS NOT NULL
                         THEN 1 ELSE 0 END) AS ImportReadyCount,
                SUM(CASE WHEN existing.SubProgramID IS NULL
                           AND (sv.ParentSegmentNo <> :programSegmentNoMissingLink
                                OR sv.ParentSegmentNo IS NULL
                                OR COALESCE(sv.ParentSegmentCode, '') = '')
                         THEN 1 ELSE 0 END) AS MissingParentLinkCount,
                SUM(CASE WHEN existing.SubProgramID IS NULL
                           AND sv.ParentSegmentNo = :programSegmentNoMissingOverlay
                           AND COALESCE(sv.ParentSegmentCode, '') <> ''
                           AND p.ProgramID IS NULL
                         THEN 1 ELSE 0 END) AS MissingParentOverlayCount
            FROM dbo.tblSegmentValues sv
            LEFT JOIN (
                SELECT
                    sp.SubProgramID,
                    sp.SubProgramCode,
                    o.SourceFiscalYearID,
                    o.SourceDataObjectCode
                FROM dbo.tblSbSubProgram sp
                INNER JOIN dbo.tblSbProgram p
                    ON p.ProgramID = sp.ProgramID
                INNER JOIN dbo.tblSbOrgUnit o
                    ON o.OrgUnitID = p.OrgUnitID
            ) existing
                ON existing.SourceFiscalYearID = sv.FiscalYearID
               AND existing.SourceDataObjectCode = sv.DataObjectCode
               AND existing.SubProgramCode = sv.SegmentCode
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.SourceFiscalYearID = sv.FiscalYearID
               AND ou.SourceDataObjectCode = sv.DataObjectCode
            LEFT JOIN dbo.tblSbProgram p
                ON p.OrgUnitID = ou.OrgUnitID
               AND p.ProgramCode = sv.ParentSegmentCode
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.ActiveFlag = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
            ':programSegmentNoReady' => $programSegmentNo,
            ':programSegmentNoMissingLink' => $programSegmentNo,
            ':programSegmentNoMissingOverlay' => $programSegmentNo,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $sourceCount = (int) ($row['SourceCount'] ?? 0);
        $overlayCount = (int) ($row['OverlayCount'] ?? 0);

        return [
            'label' => 'SubPrograms',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_ready_count' => (int) ($row['ImportReadyCount'] ?? 0),
            'missing_parent_link_count' => (int) ($row['MissingParentLinkCount'] ?? 0),
            'missing_parent_overlay_count' => (int) ($row['MissingParentOverlayCount'] ?? 0),
            'import_supported' => true,
            'import_action' => 'strategy-setup/import-sub-program-overlays',
            'import_confirm_title' => 'Import SubProgram Overlays',
            'import_confirm_message' => 'Create missing subprogram overlays that have a valid parent program link?',
            'import_reason' => null,
            'manage_route' => 'strategy-setup/sub-programs',
            'manage_label' => 'Manage SubPrograms',
            'status_note' => 'Subprogram import relies on ParentSegmentNo and ParentSegmentCode matching the configured program segment.',
        ];
    }

    private function getEconomicOverlayImportStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'ECONOMIC');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return [
                'label' => 'Economic Items',
                'mapping' => $mapping,
                'source_count' => 0,
                'overlay_count' => 0,
                'missing_count' => 0,
                'status_note' => 'Economic segment mapping is not configured.',
            ];
        }

        $stmt = $this->conn->prepare("
            WITH src AS (
                SELECT DISTINCT
                    sv.SegmentCode
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
            )
            SELECT
                COUNT(*) AS SourceCount,
                SUM(CASE WHEN ei.EconomicItemID IS NOT NULL THEN 1 ELSE 0 END) AS OverlayCount
            FROM src
            LEFT JOIN dbo.tblSbEconomicItem ei
                ON ei.EconomicCode = src.SegmentCode
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $sourceCount = (int) ($row['SourceCount'] ?? 0);
        $overlayCount = (int) ($row['OverlayCount'] ?? 0);

        return [
            'label' => 'Economic Items',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => true,
            'import_action' => 'strategy-setup/import-economic-item-overlays',
            'import_confirm_title' => 'Import Economic Item Overlays',
            'import_confirm_message' => 'Create missing economic item overlays from the mapped source values?',
            'import_reason' => null,
            'manage_route' => 'strategy-setup/economic-items',
            'manage_label' => 'Manage Economic Items',
            'status_note' => 'Economic imports create overlays with parent and level left blank for later refinement.',
        ];
    }

    private function getSectorDimensionStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SECTOR');
        if (!$this->hasSourceTrackingColumnsForTable('dbo.tblSbSector')) {
            $stmt = $this->conn->query("
                SELECT COUNT(*)
                FROM dbo.tblSbSector
                WHERE ActiveFlag = 1
            ");
            return [
                'label' => 'Sectors',
                'mapping' => $mapping,
                'source_count' => $this->countDistinctMappedSegmentValues($fiscalYearId, 'SECTOR'),
                'overlay_count' => (int) ($stmt->fetchColumn() ?: 0),
                'overlay_label' => 'Current Records',
                'missing_count' => 0,
                'import_supported' => false,
                'import_reason' => 'Sector import requires the standalone/source-tracking migration on tblSbSector.',
                'manage_route' => 'strategy-setup/sectors',
                'manage_label' => 'Manage Sectors',
                'status_note' => 'Sectors can be standalone or segment-backed, but this database still needs the source-tracking migration before sector import can run.',
            ];
        }

        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            $stmt = $this->conn->query("
                SELECT COUNT(*)
                FROM dbo.tblSbSector
                WHERE ActiveFlag = 1
            ");
            $overlayCount = (int) ($stmt->fetchColumn() ?: 0);
            return [
                'label' => 'Sectors',
                'mapping' => $mapping,
                'source_count' => 0,
                'overlay_count' => $overlayCount,
                'overlay_label' => 'Current Records',
                'missing_count' => 0,
                'import_supported' => false,
                'import_reason' => 'Import is only used when a client maps sectors from tblSegmentValues.',
                'manage_route' => 'strategy-setup/sectors',
                'manage_label' => 'Manage Sectors',
                'status_note' => 'Sectors can be maintained directly when no sector segment is mapped.',
            ];
        }

        $stmt = $this->conn->prepare("
            WITH src AS (
                SELECT DISTINCT sv.SegmentCode
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
            )
            SELECT
                COUNT(*) AS SourceCount,
                SUM(CASE WHEN s.SectorID IS NOT NULL THEN 1 ELSE 0 END) AS OverlayCount
            FROM src
            LEFT JOIN dbo.tblSbSector s
                ON s.SourceFiscalYearID = :fyJoin
               AND s.SourceSegmentCode = src.SegmentCode
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':fyJoin' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $sourceCount = (int) ($row['SourceCount'] ?? 0);
        $overlayCount = (int) ($row['OverlayCount'] ?? 0);

        return [
            'label' => 'Sectors',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'overlay_label' => 'Current Records',
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => true,
            'import_reason' => null,
            'import_action' => 'strategy-setup/import-sector-overlays',
            'import_confirm_title' => 'Import Sector Overlays',
            'import_confirm_message' => 'Create or link missing sector overlays from the mapped source values?',
            'manage_route' => 'strategy-setup/sectors',
            'manage_label' => 'Manage Sectors',
            'status_note' => 'Sector mapping is configured and sectors can now be imported from the mapped source values.',
        ];
    }

    private function getFundingTypeDimensionStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_TYPE');
        if (!$this->tableExists('dbo.tblSbFundingType')) {
            return [
                'label' => 'Funding Types',
                'mapping' => $mapping,
                'source_count' => $this->countDistinctMappedSegmentValues($fiscalYearId, 'FUNDING_TYPE'),
                'overlay_count' => 0,
                'overlay_label' => 'Current Records',
                'missing_count' => 0,
                'import_supported' => false,
                'import_reason' => 'Run the standalone strategic dimension migration to create tblSbFundingType.',
                'manage_route' => null,
                'manage_label' => 'Manage Funding Types',
                'status_note' => 'Funding type is configured as a strategic dimension, but the standalone funding type table has not been created in this database yet.',
            ];
        }

        $stmt = $this->conn->query("
            SELECT COUNT(*)
            FROM dbo.tblSbFundingType
            WHERE ActiveFlag = 1
        ");
        $overlayCount = (int) ($stmt->fetchColumn() ?: 0);
        $sourceCount = $this->countDistinctMappedSegmentValues($fiscalYearId, 'FUNDING_TYPE');

        return [
            'label' => 'Funding Types',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'overlay_label' => 'Current Records',
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => !empty($mapping['SegmentNo']),
            'import_reason' => !empty($mapping['SegmentNo']) ? null : 'Import is only used when a client maps funding types from tblSegmentValues.',
            'import_action' => !empty($mapping['SegmentNo']) ? 'strategy-setup/import-funding-type-overlays' : null,
            'import_confirm_title' => 'Import Funding Type Overlays',
            'import_confirm_message' => 'Create missing funding type overlays from the mapped source values?',
            'manage_route' => 'strategy-setup/funding-types',
            'manage_label' => 'Manage Funding Types',
            'status_note' => ($mapping['SegmentNo'] ?? null)
                ? 'Funding types have a standalone overlay table and can be imported from the mapped segment values.'
                : 'Funding types have a standalone overlay table and can also be maintained directly when no source segment is used.',
        ];
    }

    private function getFundingSourceOverlayImportStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'FUNDING_SOURCE');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return [
                'label' => 'Funding Sources',
                'mapping' => $mapping,
                'source_count' => 0,
                'overlay_count' => 0,
                'missing_count' => 0,
                'status_note' => 'Funding source segment mapping is not configured.',
            ];
        }

        if (!$this->tableColumnExists('dbo.tblSbFundingSource', 'SourceFiscalYearID') || !$this->tableColumnExists('dbo.tblSbFundingSource', 'SourceSegmentCode')) {
            return [
                'label' => 'Funding Sources',
                'mapping' => $mapping,
                'source_count' => 0,
                'overlay_count' => 0,
                'missing_count' => 0,
                'status_note' => 'Funding source import requires the source-tracking migration on tblSbFundingSource.',
            ];
        }

        $stmt = $this->conn->prepare("
            WITH src AS (
                SELECT DISTINCT
                    sv.SegmentCode
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
            )
            SELECT
                COUNT(*) AS SourceCount,
                SUM(CASE WHEN fs.FundingSourceID IS NOT NULL THEN 1 ELSE 0 END) AS OverlayCount
            FROM src
            LEFT JOIN dbo.tblSbFundingSource fs
                ON fs.SourceFiscalYearID = :fyJoin
               AND fs.SourceSegmentCode = src.SegmentCode
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':fyJoin' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $sourceCount = (int) ($row['SourceCount'] ?? 0);
        $overlayCount = (int) ($row['OverlayCount'] ?? 0);

        return [
            'label' => 'Funding Sources',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => true,
            'manage_route' => 'strategy-setup/funding-sources',
            'manage_label' => 'Manage Funding Sources',
            'status_note' => 'Funding imports default new overlays to DOMESTIC until you refine them.',
        ];
    }

    private function getObjectiveDimensionStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'OBJECTIVE');
        $overlayCount = $this->countActiveRows('dbo.tblSbObjective');
        $sourceCount = $this->countDistinctMappedSegmentValues($fiscalYearId, 'OBJECTIVE');
        $importSupported = !empty($mapping['SegmentNo'])
            && $this->hasSourceTrackingColumnsForTable('dbo.tblSbObjective')
            && $this->hasParentSegmentColumns();

        return [
            'label' => 'Objectives',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'overlay_label' => 'Current Records',
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => $importSupported,
            'import_reason' => $importSupported ? null : 'Objective import needs source-tracking columns plus ParentSegmentNo and ParentSegmentCode on tblSegmentValues.',
            'import_action' => $importSupported ? 'strategy-performance/import-objective-overlays' : null,
            'import_confirm_title' => 'Import Objective Overlays',
            'import_confirm_message' => 'Create missing objective overlays where each source row has a valid parent program or subprogram link?',
            'manage_route' => 'strategy-performance/objectives',
            'manage_label' => 'Manage Objectives',
            'status_note' => ($mapping['SegmentNo'] ?? null)
                ? 'Objective mapping is configured and objectives can now be imported when each source row links to an imported program or subprogram.'
                : 'Objectives are strategic records and are not currently sourced from tblSegmentValues.',
        ];
    }

    private function getIndicatorDimensionStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'INDICATOR');
        $overlayCount = $this->countActiveRows('dbo.tblSbIndicator');
        $sourceCount = $this->countDistinctMappedSegmentValues($fiscalYearId, 'INDICATOR');

        return [
            'label' => 'Indicators',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'overlay_label' => 'Current Records',
            'missing_count' => 0,
            'import_supported' => false,
            'import_reason' => 'Indicator import has not been implemented yet. Indicators can still be maintained directly in the strategic module.',
            'manage_route' => 'strategy-performance/indicators',
            'manage_label' => 'Manage Indicators',
            'status_note' => ($mapping['SegmentNo'] ?? null)
                ? 'Indicator mapping is configured, but indicators are still maintained directly in the strategic module today.'
                : 'Indicators are strategic records and are currently maintained directly in the strategic module.',
        ];
    }

    private function getTargetDimensionStatus(int $fiscalYearId, int $versionId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'TARGET');
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSbIndicatorTarget
            WHERE FiscalYearID = :fy
              AND (:ver = 0 OR VersionID = :verFilter)
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':verFilter' => $versionId,
        ]);
        $overlayCount = (int) ($stmt->fetchColumn() ?: 0);

        return [
            'label' => 'Targets',
            'mapping' => $mapping,
            'source_count' => $this->countDistinctMappedSegmentValues($fiscalYearId, 'TARGET'),
            'overlay_count' => $overlayCount,
            'overlay_label' => 'Current Context Records',
            'missing_count' => 0,
            'import_supported' => false,
            'import_reason' => 'tblSegmentValues does not hold numeric target values, so target import needs a different source shape than code/name segment rows.',
            'manage_route' => 'strategy-performance/targets',
            'manage_label' => 'Manage Targets',
            'status_note' => ($mapping['SegmentNo'] ?? null)
                ? 'Target mapping is configured, but target import is still blocked because tblSegmentValues does not carry the numeric values needed for indicator targets.'
                : 'Targets are strategic records tied to the active fiscal year and version, and are not currently sourced from tblSegmentValues.',
        ];
    }

    private function getOutputDimensionStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'OUTPUT');
        $overlayCount = $this->countActiveRows('dbo.tblSbOutput');
        $sourceCount = $this->countDistinctMappedSegmentValues($fiscalYearId, 'OUTPUT');
        $importSupported = !empty($mapping['SegmentNo'])
            && $this->hasSourceTrackingColumnsForTable('dbo.tblSbOutput')
            && $this->hasParentSegmentColumns();

        return [
            'label' => 'Outputs',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'overlay_label' => 'Current Records',
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => $importSupported,
            'import_reason' => $importSupported ? null : 'Output import needs source-tracking columns plus ParentSegmentNo and ParentSegmentCode on tblSegmentValues.',
            'import_action' => $importSupported ? 'strategy-delivery/import-output-overlays' : null,
            'import_confirm_title' => 'Import Output Overlays',
            'import_confirm_message' => 'Create missing output overlays where each source row has a valid parent program or subprogram link?',
            'manage_route' => 'strategy-delivery/outputs',
            'manage_label' => 'Manage Outputs',
            'status_note' => ($mapping['SegmentNo'] ?? null)
                ? 'Output mapping is configured and outputs can now be imported when each source row links to an imported program or subprogram.'
                : 'Outputs are maintained directly in the strategic module unless a client-specific segment mapping is configured.',
        ];
    }

    private function getProjectDimensionStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'PROJECT');
        $importSupported = !empty($mapping['SegmentNo']) && $this->tableExists('dbo.tblSbProject');
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        $sourceCount = 0;
        $overlayCount = 0;

        if ($fiscalYearId > 0 && $segmentNo > 0) {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM (
                    SELECT DISTINCT
                        COALESCE(sv.DataObjectCode, N'') AS DataObjectCode,
                        sv.SegmentCode,
                        sv.SegmentName
                    FROM dbo.tblSegmentValues sv
                    WHERE sv.FiscalYearID = :fy
                      AND sv.SegmentNo = :segmentNo
                      AND sv.ActiveFlag = 1
                ) src
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            $sourceCount = (int) ($stmt->fetchColumn() ?: 0);

            if ($this->supportsProjectSourceMaps()) {
                $stmt = $this->conn->prepare("
                    SELECT COUNT(*)
                    FROM dbo.tblSbProjectSourceMap
                    WHERE FiscalYearID = :fy
                      AND SourceSegmentNo = :segmentNo
                      AND ActiveFlag = 1
                ");
                $stmt->execute([
                    ':fy' => $fiscalYearId,
                    ':segmentNo' => $segmentNo,
                ]);
                $overlayCount = (int) ($stmt->fetchColumn() ?: 0);
            } else {
                $stmt = $this->conn->prepare("
                    SELECT COUNT(*)
                    FROM dbo.tblSbProject
                    WHERE SourceFiscalYearID = :fy
                      AND SourceSegmentNo = :segmentNo
                      AND ActiveFlag = 1
                ");
                $stmt->execute([
                    ':fy' => $fiscalYearId,
                    ':segmentNo' => $segmentNo,
                ]);
                $overlayCount = (int) ($stmt->fetchColumn() ?: 0);
            }
        }

        return [
            'label' => 'Projects',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'overlay_label' => 'Current Records',
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => $importSupported,
            'import_reason' => $importSupported ? null : 'Project import needs the PROJECT mapping and the create_tblSbProject.sql migration.',
            'import_action' => $importSupported ? 'strategy-setup/import-project-overlays' : null,
            'import_confirm_title' => 'Import Project Overlays',
            'import_confirm_message' => 'Create missing project records from the mapped source segment values?',
            'manage_route' => 'strategy-setup/projects',
            'manage_label' => 'Manage Projects',
            'status_note' => ($mapping['SegmentNo'] ?? null)
                ? 'Project counts on this dashboard reflect current fiscal year source rows and current fiscal year active source mappings. The Project Register still shows the full reusable master list.'
                : 'Projects are not yet sourced from tblSegmentValues until a PROJECT mapping is configured.',
        ];
    }

    private function getActivityDimensionStatus(int $fiscalYearId): array
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, 'ACTIVITY');
        $overlayCount = $this->countActiveRows('dbo.tblSbActivity');
        $sourceCount = $this->countDistinctMappedSegmentValues($fiscalYearId, 'ACTIVITY');
        $outputMapped = !empty($this->getStrategicSegmentMapping($fiscalYearId, 'OUTPUT')['SegmentNo']);
        $importSupported = !empty($mapping['SegmentNo'])
            && $outputMapped
            && $this->hasSourceTrackingColumnsForTable('dbo.tblSbActivity')
            && $this->hasParentSegmentColumns();

        return [
            'label' => 'Activities',
            'mapping' => $mapping,
            'source_count' => $sourceCount,
            'overlay_count' => $overlayCount,
            'overlay_label' => 'Current Records',
            'missing_count' => max(0, $sourceCount - $overlayCount),
            'import_supported' => $importSupported,
            'import_reason' => $importSupported ? null : 'Activity import needs ACTIVITY and OUTPUT mappings plus source-tracking columns and parent links in tblSegmentValues.',
            'import_action' => $importSupported ? 'strategy-delivery/import-activity-overlays' : null,
            'import_confirm_title' => 'Import Activity Overlays',
            'import_confirm_message' => 'Create missing activity overlays where each source row links back to an imported output?',
            'manage_route' => 'strategy-delivery/activities',
            'manage_label' => 'Manage Activities',
            'status_note' => ($mapping['SegmentNo'] ?? null)
                ? 'Activity mapping is configured and activities can now be imported when each source row links back to an imported output.'
                : 'Activities are strategic records and are not currently sourced from tblSegmentValues.',
        ];
    }

    private function resolveProgramSubProgramParentFromSource(int $fiscalYearId, string $dataObjectCode, int $parentSegmentNo, string $parentSegmentCode): array
    {
        if ($parentSegmentNo <= 0 || $parentSegmentCode === '') {
            return ['status' => 'missing_link'];
        }

        $programSegmentNo = (int) (($this->getStrategicSegmentMapping($fiscalYearId, 'PROGRAM')['SegmentNo'] ?? 0));
        if ($programSegmentNo > 0 && $parentSegmentNo === $programSegmentNo) {
            $program = $this->findProgramOverlayBySource($fiscalYearId, $dataObjectCode, $parentSegmentCode);
            if ($program === null) {
                return ['status' => 'missing_overlay'];
            }
            return [
                'status' => 'resolved',
                'ProgramID' => (int) ($program['ProgramID'] ?? 0),
                'SubProgramID' => null,
            ];
        }

        $subProgramSegmentNo = (int) (($this->getStrategicSegmentMapping($fiscalYearId, 'SUBPROGRAM')['SegmentNo'] ?? 0));
        if ($subProgramSegmentNo > 0 && $parentSegmentNo === $subProgramSegmentNo) {
            $subProgram = $this->findSubProgramOverlayBySource($fiscalYearId, $dataObjectCode, $parentSegmentCode);
            if ($subProgram === null) {
                return ['status' => 'missing_overlay'];
            }
            return [
                'status' => 'resolved',
                'ProgramID' => (int) ($subProgram['ProgramID'] ?? 0),
                'SubProgramID' => (int) ($subProgram['SubProgramID'] ?? 0),
            ];
        }

        return ['status' => 'missing_link'];
    }

    private function hasParentSegmentColumns(): bool
    {
        return $this->tableColumnExists('dbo.tblSegmentValues', 'ParentSegmentNo')
            && $this->tableColumnExists('dbo.tblSegmentValues', 'ParentSegmentCode');
    }

    private function canAutoAssignProgramSectors(int $fiscalYearId): bool
    {
        $sectorMapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SECTOR');

        return $fiscalYearId > 0
            && (
                ((int) ($sectorMapping['SegmentNo'] ?? 0) > 0 && $this->hasParentSegmentColumns())
                || $this->hasSourceTrackingColumnsForTable('dbo.tblSbSector')
            )
            && $this->hasSourceTrackingColumnsForTable('dbo.tblSbSector');
    }

    private function resolveProgramImportSector(
        int $fiscalYearId,
        string $dataObjectCode,
        int $parentSegmentNo,
        string $parentSegmentCode
    ): array {
        $sectorMapping = $this->getStrategicSegmentMapping($fiscalYearId, 'SECTOR');
        $sectorSegmentNo = (int) ($sectorMapping['SegmentNo'] ?? 0);
        $parentSegmentCode = trim($parentSegmentCode);
        $dataObjectCode = trim($dataObjectCode);

        if ($sectorSegmentNo > 0 && $parentSegmentNo === $sectorSegmentNo && $parentSegmentCode !== '') {
            $sector = $this->getSectorBySourceCode($fiscalYearId, $parentSegmentCode);
            if ($sector !== null) {
                return [
                    'sector' => $sector,
                    'reason' => 'parent_link',
                ];
            }
        }

        if ($dataObjectCode !== '') {
            $sector = $this->getSectorBySourceScope($fiscalYearId, $dataObjectCode);
            if ($sector !== null) {
                return [
                    'sector' => $sector,
                    'reason' => 'source_scope',
                ];
            }
        }

        if ($sectorSegmentNo > 0 && $parentSegmentNo === $sectorSegmentNo && $parentSegmentCode !== '') {
            return [
                'sector' => null,
                'reason' => 'missing_parent_overlay',
            ];
        }

        return [
            'sector' => null,
            'reason' => 'missing_scope',
        ];
    }

    private function hasSourceTrackingColumnsForTable(string $tableName): bool
    {
        return $this->tableColumnExists($tableName, 'SourceFiscalYearID')
            && $this->tableColumnExists($tableName, 'SourceSegmentCode');
    }

    private function normalizeDimensionAttributeStoredValue(string $dataType, array $row): mixed
    {
        return match ($dataType) {
            'NUMBER' => $row['ValueNumber'] ?? null,
            'DATE' => $row['ValueDate'] ?? null,
            'BOOLEAN' => (int) ($row['ValueBit'] ?? 0),
            default => $row['ValueText'] ?? null,
        };
    }

    private function normalizeDimensionAttributeInputValue(string $dataType, mixed $rawValue): mixed
    {
        return match ($dataType) {
            'NUMBER' => $this->normalizeNumericAttributeValue($rawValue),
            'DATE' => $this->normalizeStringAttributeValue($rawValue),
            'TEXT', 'LONG_TEXT', 'LIST' => $this->normalizeStringAttributeValue($rawValue),
            default => $this->normalizeStringAttributeValue($rawValue),
        };
    }

    private function normalizeStringAttributeValue(mixed $rawValue): ?string
    {
        $value = trim((string) $rawValue);
        return $value === '' ? null : $value;
    }

    private function normalizeNumericAttributeValue(mixed $rawValue): ?float
    {
        $value = trim((string) $rawValue);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function coerceBooleanValue(mixed $rawValue): bool
    {
        if (is_bool($rawValue)) {
            return $rawValue;
        }

        $value = strtolower(trim((string) $rawValue));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private function upsertDimensionAttributeValue(
        string $dimensionCode,
        int $entityId,
        int $attributeId,
        array $valueColumns,
        int $userId
    ): void {
        $existingStmt = $this->conn->prepare("
            SELECT AttributeValueID
            FROM dbo.tblSbDimensionAttributeValue
            WHERE StrategicDimensionCode = :dimensionCode
              AND EntityID = :entityId
              AND AttributeID = :attributeId
        ");
        $existingStmt->execute([
            ':dimensionCode' => $dimensionCode,
            ':entityId' => $entityId,
            ':attributeId' => $attributeId,
        ]);
        $existingId = (int) ($existingStmt->fetchColumn() ?: 0);

        $payload = [
            ':dimensionCode' => $dimensionCode,
            ':entityId' => $entityId,
            ':attributeId' => $attributeId,
            ':ValueText' => $valueColumns['ValueText'] ?? null,
            ':ValueNumber' => $valueColumns['ValueNumber'] ?? null,
            ':ValueDate' => $valueColumns['ValueDate'] ?? null,
            ':ValueBit' => $valueColumns['ValueBit'] ?? null,
        ];

        if ($existingId > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbDimensionAttributeValue
                SET ValueText = :ValueText,
                    ValueNumber = :ValueNumber,
                    ValueDate = :ValueDate,
                    ValueBit = :ValueBit,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE AttributeValueID = :AttributeValueID
            ");
            $stmt->execute($payload + [
                ':UpdatedBy' => $userId,
                ':AttributeValueID' => $existingId,
            ]);
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbDimensionAttributeValue (
                StrategicDimensionCode,
                EntityID,
                AttributeID,
                ValueText,
                ValueNumber,
                ValueDate,
                ValueBit,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :dimensionCode,
                :entityId,
                :attributeId,
                :ValueText,
                :ValueNumber,
                :ValueDate,
                :ValueBit,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute($payload + [
            ':CreatedBy' => $userId,
        ]);
    }

    private function deleteDimensionAttributeValue(string $dimensionCode, int $entityId, int $attributeId): void
    {
        $stmt = $this->conn->prepare("
            DELETE FROM dbo.tblSbDimensionAttributeValue
            WHERE StrategicDimensionCode = :dimensionCode
              AND EntityID = :entityId
              AND AttributeID = :attributeId
        ");
        $stmt->execute([
            ':dimensionCode' => $dimensionCode,
            ':entityId' => $entityId,
            ':attributeId' => $attributeId,
        ]);
    }

    public function supportsSegmentPublicationWorkflow(): bool
    {
        return $this->tableExists('dbo.tblSbSegmentPublishRequest')
            && $this->tableExists('dbo.tblSbSegmentPublishRequestLine');
    }

    public function listSegmentPublishDimensions(int $fiscalYearId): array
    {
        $defs = [
            'SECTOR' => 'Sector',
            'PROGRAM' => 'Program',
            'SUBPROGRAM' => 'SubProgram',
            'OBJECTIVE' => 'Objective',
            'TARGET' => 'Target',
            'OUTPUT' => 'Output',
            'ACTIVITY' => 'Activity',
            'PROJECT' => 'Project',
            'ECONOMIC' => 'Economic Item',
            'FUNDING_TYPE' => 'Funding Type',
            'FUNDING_SOURCE' => 'Funding Source',
        ];

        $rows = [];
        foreach ($defs as $code => $label) {
            $mapping = $this->getStrategicSegmentMapping($fiscalYearId, $code);
            $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
            $segmentDef = $segmentNo > 0 ? $this->getAvailableSegmentByNo($segmentNo) : null;
            $rows[] = [
                'Code' => $code,
                'Label' => $label,
                'SegmentNo' => $segmentNo,
                'Mapped' => !empty($mapping['SegmentNo']),
                'MinLength' => (int) ($segmentDef['MinLength'] ?? 0),
                'MaxLength' => (int) ($segmentDef['MaxLength'] ?? 0),
            ];
        }

        return $rows;
    }

    public function listSegmentPublishRequests(int $fiscalYearId, ?int $versionId = null): array
    {
        if (!$this->supportsSegmentPublicationWorkflow()) {
            return [];
        }

        $params = [':fy' => $fiscalYearId];
        $sql = "
            SELECT
                r.*,
                ISNULL(ls.LineCount, 0) AS LineCount,
                ISNULL(ls.PendingCount, 0) AS PendingCount,
                ISNULL(ls.ApprovedCount, 0) AS ApprovedCount,
                ISNULL(ls.PublishedCount, 0) AS PublishedCount,
                ISNULL(ls.FailedCount, 0) AS FailedCount
            FROM dbo.tblSbSegmentPublishRequest r
            LEFT JOIN (
                SELECT
                    StrategicSegmentPublishRequestID,
                    COUNT(*) AS LineCount,
                    SUM(CASE WHEN LineStatusCode = N'PENDING' THEN 1 ELSE 0 END) AS PendingCount,
                    SUM(CASE WHEN LineStatusCode = N'APPROVED' THEN 1 ELSE 0 END) AS ApprovedCount,
                    SUM(CASE WHEN LineStatusCode = N'PUBLISHED' THEN 1 ELSE 0 END) AS PublishedCount,
                    SUM(CASE WHEN LineStatusCode = N'FAILED' THEN 1 ELSE 0 END) AS FailedCount
                FROM dbo.tblSbSegmentPublishRequestLine
                GROUP BY StrategicSegmentPublishRequestID
            ) ls
                ON ls.StrategicSegmentPublishRequestID = r.StrategicSegmentPublishRequestID
            WHERE r.FiscalYearID = :fy
        ";

        if ($versionId !== null && $versionId > 0) {
            $sql .= " AND (r.VersionID = :ver OR r.VersionID IS NULL)";
            $params[':ver'] = $versionId;
        }

        $sql .= " ORDER BY r.StrategicSegmentPublishRequestID DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSegmentPublishRequest(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsSegmentPublicationWorkflow()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbSegmentPublishRequest
            WHERE StrategicSegmentPublishRequestID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSegmentPublishRequest(array $data, ?int $id = null): int
    {
        if (!$this->supportsSegmentPublicationWorkflow()) {
            throw new \RuntimeException('Segment publication workflow is not available until create_tblSbSegmentPublishRequest.sql is run.');
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbSegmentPublishRequest
                SET RequestTitle = :RequestTitle,
                    RequestNotes = :RequestNotes,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE StrategicSegmentPublishRequestID = :RequestID
                  AND RequestStatusCode IN (N'DRAFT', N'REJECTED')
            ");
            $stmt->execute([
                ':RequestTitle' => $data['RequestTitle'],
                ':RequestNotes' => $data['RequestNotes'],
                ':UpdatedBy' => $data['UserID'],
                ':RequestID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbSegmentPublishRequest (
                FiscalYearID,
                VersionID,
                RequestTitle,
                RequestNotes,
                RequestStatusCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :FiscalYearID,
                :VersionID,
                :RequestTitle,
                :RequestNotes,
                N'DRAFT',
                1,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':FiscalYearID' => $data['FiscalYearID'],
            ':VersionID' => $data['VersionID'] ?: null,
            ':RequestTitle' => $data['RequestTitle'],
            ':RequestNotes' => $data['RequestNotes'],
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function listSegmentPublishRequestLines(int $requestId): array
    {
        if ($requestId <= 0 || !$this->supportsSegmentPublicationWorkflow()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbSegmentPublishRequestLine
            WHERE StrategicSegmentPublishRequestID = :requestId
            ORDER BY StrategicDimensionCode ASC, DataObjectCode ASC, SortOrder ASC, SegmentCode ASC, StrategicSegmentPublishRequestLineID ASC
        ");
        $stmt->execute([':requestId' => $requestId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSegmentPublishRequestLine(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsSegmentPublicationWorkflow()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbSegmentPublishRequestLine
            WHERE StrategicSegmentPublishRequestLineID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSegmentPublishRequestLine(array $data, ?int $id = null): int
    {
        if (!$this->supportsSegmentPublicationWorkflow()) {
            throw new \RuntimeException('Segment publication workflow is not available until create_tblSbSegmentPublishRequest.sql is run.');
        }

        $request = $this->getSegmentPublishRequest((int) ($data['StrategicSegmentPublishRequestID'] ?? 0));
        if ($request === null) {
            throw new \RuntimeException('Publish request was not found.');
        }
        $requestStatus = strtoupper(trim((string) ($request['RequestStatusCode'] ?? 'DRAFT')));
        if (!in_array($requestStatus, ['DRAFT', 'REJECTED'], true)) {
            throw new \RuntimeException('Only draft or rejected requests can be edited.');
        }

        $dimensionCode = strtoupper(trim((string) ($data['StrategicDimensionCode'] ?? '')));
        $mapping = $this->getStrategicSegmentMapping((int) ($request['FiscalYearID'] ?? 0), $dimensionCode);
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($segmentNo <= 0) {
            throw new \RuntimeException('The selected strategic dimension is not mapped for the active fiscal year.');
        }
        $segmentDef = $this->getAvailableSegmentByNo($segmentNo);

        $segmentCode = trim((string) ($data['SegmentCode'] ?? ''));
        $segmentName = trim((string) ($data['SegmentName'] ?? ''));
        $dataObjectCode = trim((string) ($data['DataObjectCode'] ?? ''));
        if ($segmentCode === '' || $segmentName === '' || $dataObjectCode === '') {
            throw new \RuntimeException('DataObjectCode, SegmentCode, and SegmentName are required.');
        }
        $segmentCodeLength = function_exists('mb_strlen') ? mb_strlen($segmentCode) : strlen($segmentCode);
        $minLength = (int) ($segmentDef['MinLength'] ?? 0);
        $maxLength = (int) ($segmentDef['MaxLength'] ?? 0);
        if ($minLength > 0 && $segmentCodeLength < $minLength) {
            throw new \RuntimeException('Segment Code must be at least ' . $minLength . ' character(s) for the mapped segment.');
        }
        if ($maxLength > 0 && $segmentCodeLength > $maxLength) {
            throw new \RuntimeException('Segment Code must be at most ' . $maxLength . ' character(s) for the mapped segment.');
        }

        $sortOrder = trim((string) ($data['SortOrder'] ?? ''));
        $resolvedSortOrder = $sortOrder !== '' ? (int) $sortOrder : (is_numeric($segmentCode) ? (int) $segmentCode : 0);
        $parentSegmentNo = (int) ($data['ParentSegmentNo'] ?? 0);
        $parentSegmentCode = trim((string) ($data['ParentSegmentCode'] ?? ''));
        if ($parentSegmentNo > 0 && $parentSegmentCode === '') {
            throw new \RuntimeException('Parent Segment Code is required when Parent Segment No is selected.');
        }
        if ($parentSegmentCode !== '' && $parentSegmentNo <= 0) {
            throw new \RuntimeException('Parent Segment No is required when Parent Segment Code is entered.');
        }
        if ($parentSegmentNo > 0 && $parentSegmentCode !== '') {
            $parentSegmentDef = $this->getAvailableSegmentByNo($parentSegmentNo);
            $parentCodeLength = function_exists('mb_strlen') ? mb_strlen($parentSegmentCode) : strlen($parentSegmentCode);
            $parentMinLength = (int) ($parentSegmentDef['MinLength'] ?? 0);
            $parentMaxLength = (int) ($parentSegmentDef['MaxLength'] ?? 0);
            if ($parentMinLength > 0 && $parentCodeLength < $parentMinLength) {
                throw new \RuntimeException('Parent Segment Code must be at least ' . $parentMinLength . ' character(s) for the selected parent segment.');
            }
            if ($parentMaxLength > 0 && $parentCodeLength > $parentMaxLength) {
                throw new \RuntimeException('Parent Segment Code must be at most ' . $parentMaxLength . ' character(s) for the selected parent segment.');
            }
        }

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbSegmentPublishRequestLine
                SET StrategicDimensionCode = :StrategicDimensionCode,
                    SegmentNo = :SegmentNo,
                    DataObjectCode = :DataObjectCode,
                    SegmentCode = :SegmentCode,
                    SegmentName = :SegmentName,
                    SegmentExternalID = :SegmentExternalID,
                    ParentSegmentNo = :ParentSegmentNo,
                    ParentSegmentCode = :ParentSegmentCode,
                    SortOrder = :SortOrder,
                    ActiveFlag = :ActiveFlag,
                    LineStatusCode = N'DRAFT',
                    LineStatusNote = NULL,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE StrategicSegmentPublishRequestLineID = :LineID
                  AND LineStatusCode IN (N'DRAFT', N'REJECTED', N'FAILED')
            ");
            $stmt->execute([
                ':StrategicDimensionCode' => $dimensionCode,
                ':SegmentNo' => $segmentNo,
                ':DataObjectCode' => $dataObjectCode,
                ':SegmentCode' => $segmentCode,
                ':SegmentName' => $segmentName,
                ':SegmentExternalID' => trim((string) ($data['SegmentExternalID'] ?? '')) ?: null,
                ':ParentSegmentNo' => $parentSegmentNo > 0 ? $parentSegmentNo : null,
                ':ParentSegmentCode' => $parentSegmentCode !== '' ? $parentSegmentCode : null,
                ':SortOrder' => $resolvedSortOrder,
                ':ActiveFlag' => !empty($data['ActiveFlag']) ? 1 : 0,
                ':UpdatedBy' => $data['UserID'],
                ':LineID' => $id,
            ]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbSegmentPublishRequestLine (
                StrategicSegmentPublishRequestID,
                StrategicDimensionCode,
                SegmentNo,
                DataObjectCode,
                SegmentCode,
                SegmentName,
                SegmentExternalID,
                ParentSegmentNo,
                ParentSegmentCode,
                SortOrder,
                ActiveFlag,
                LineStatusCode,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :RequestID,
                :StrategicDimensionCode,
                :SegmentNo,
                :DataObjectCode,
                :SegmentCode,
                :SegmentName,
                :SegmentExternalID,
                :ParentSegmentNo,
                :ParentSegmentCode,
                :SortOrder,
                :ActiveFlag,
                N'DRAFT',
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':RequestID' => (int) $request['StrategicSegmentPublishRequestID'],
            ':StrategicDimensionCode' => $dimensionCode,
            ':SegmentNo' => $segmentNo,
            ':DataObjectCode' => $dataObjectCode,
            ':SegmentCode' => $segmentCode,
            ':SegmentName' => $segmentName,
            ':SegmentExternalID' => trim((string) ($data['SegmentExternalID'] ?? '')) ?: null,
            ':ParentSegmentNo' => $parentSegmentNo > 0 ? $parentSegmentNo : null,
            ':ParentSegmentCode' => $parentSegmentCode !== '' ? $parentSegmentCode : null,
            ':SortOrder' => $resolvedSortOrder,
            ':ActiveFlag' => !empty($data['ActiveFlag']) ? 1 : 0,
            ':CreatedBy' => $data['UserID'],
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deleteSegmentPublishRequestLine(int $id): void
    {
        if ($id <= 0 || !$this->supportsSegmentPublicationWorkflow()) {
            return;
        }
        $line = $this->getSegmentPublishRequestLine($id);
        if ($line === null) {
            return;
        }
        $request = $this->getSegmentPublishRequest((int) ($line['StrategicSegmentPublishRequestID'] ?? 0));
        if ($request === null) {
            return;
        }
        $requestStatus = strtoupper(trim((string) ($request['RequestStatusCode'] ?? 'DRAFT')));
        if (!in_array($requestStatus, ['DRAFT', 'REJECTED'], true)) {
            throw new \RuntimeException('Only draft or rejected requests can remove lines.');
        }

        $stmt = $this->conn->prepare("
            DELETE FROM dbo.tblSbSegmentPublishRequestLine
            WHERE StrategicSegmentPublishRequestLineID = :id
              AND LineStatusCode IN (N'DRAFT', N'REJECTED', N'FAILED')
        ");
        $stmt->execute([':id' => $id]);
    }

    public function transitionSegmentPublishRequest(int $requestId, string $action, int $userId): void
    {
        if ($requestId <= 0 || !$this->supportsSegmentPublicationWorkflow()) {
            throw new \RuntimeException('Segment publication workflow is not installed.');
        }

        $request = $this->getSegmentPublishRequest($requestId);
        if ($request === null) {
            throw new \RuntimeException('Publish request was not found.');
        }

        $status = strtoupper(trim((string) ($request['RequestStatusCode'] ?? 'DRAFT')));
        $action = strtolower(trim($action));

        $this->conn->beginTransaction();
        try {
            if ($action === 'submit') {
                if ($status !== 'DRAFT' && $status !== 'REJECTED') {
                    throw new \RuntimeException('Only draft or rejected requests can be submitted.');
                }
                if (count($this->listSegmentPublishRequestLines($requestId)) <= 0) {
                    throw new \RuntimeException('Add at least one line before submitting the request.');
                }
                $this->conn->prepare("
                    UPDATE dbo.tblSbSegmentPublishRequest
                    SET RequestStatusCode = N'PENDING',
                        SubmittedBy = :SubmittedBy,
                        SubmittedDate = SYSDATETIME(),
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicSegmentPublishRequestID = :RequestIDSubmit
                ")->execute([
                    ':SubmittedBy' => $userId,
                    ':UpdatedBy' => $userId,
                    ':RequestIDSubmit' => $requestId,
                ]);
                $this->conn->prepare("
                    UPDATE dbo.tblSbSegmentPublishRequestLine
                    SET LineStatusCode = N'PENDING',
                        LineStatusNote = NULL,
                        UpdatedBy = :UpdatedByLines,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicSegmentPublishRequestID = :RequestIDLines
                      AND LineStatusCode IN (N'DRAFT', N'REJECTED', N'FAILED')
                ")->execute([
                    ':UpdatedByLines' => $userId,
                    ':RequestIDLines' => $requestId,
                ]);
            } elseif ($action === 'approve') {
                if ($status !== 'PENDING') {
                    throw new \RuntimeException('Only pending requests can be approved.');
                }
                $this->conn->prepare("
                    UPDATE dbo.tblSbSegmentPublishRequest
                    SET RequestStatusCode = N'APPROVED',
                        ApprovedBy = :ApprovedBy,
                        ApprovedDate = SYSDATETIME(),
                        UpdatedBy = :UpdatedByApprove,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicSegmentPublishRequestID = :RequestIDApprove
                ")->execute([
                    ':ApprovedBy' => $userId,
                    ':UpdatedByApprove' => $userId,
                    ':RequestIDApprove' => $requestId,
                ]);
                $this->conn->prepare("
                    UPDATE dbo.tblSbSegmentPublishRequestLine
                    SET LineStatusCode = N'APPROVED',
                        LineStatusNote = NULL,
                        UpdatedBy = :UpdatedByApprovedLines,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicSegmentPublishRequestID = :RequestIDApprovedLines
                      AND LineStatusCode = N'PENDING'
                ")->execute([
                    ':UpdatedByApprovedLines' => $userId,
                    ':RequestIDApprovedLines' => $requestId,
                ]);
            } elseif ($action === 'reject') {
                if ($status !== 'PENDING') {
                    throw new \RuntimeException('Only pending requests can be rejected.');
                }
                $this->conn->prepare("
                    UPDATE dbo.tblSbSegmentPublishRequest
                    SET RequestStatusCode = N'REJECTED',
                        RejectedBy = :RejectedBy,
                        RejectedDate = SYSDATETIME(),
                        UpdatedBy = :UpdatedByReject,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicSegmentPublishRequestID = :RequestIDReject
                ")->execute([
                    ':RejectedBy' => $userId,
                    ':UpdatedByReject' => $userId,
                    ':RequestIDReject' => $requestId,
                ]);
                $this->conn->prepare("
                    UPDATE dbo.tblSbSegmentPublishRequestLine
                    SET LineStatusCode = N'REJECTED',
                        UpdatedBy = :UpdatedByRejectedLines,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicSegmentPublishRequestID = :RequestIDRejectedLines
                      AND LineStatusCode = N'PENDING'
                ")->execute([
                    ':UpdatedByRejectedLines' => $userId,
                    ':RequestIDRejectedLines' => $requestId,
                ]);
            } else {
                throw new \RuntimeException('Unsupported publish request action.');
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function publishApprovedSegmentRequest(int $requestId, int $userId): array
    {
        if ($requestId <= 0 || !$this->supportsSegmentPublicationWorkflow()) {
            throw new \RuntimeException('Segment publication workflow is not installed.');
        }

        $request = $this->getSegmentPublishRequest($requestId);
        if ($request === null) {
            throw new \RuntimeException('Publish request was not found.');
        }
        $status = strtoupper(trim((string) ($request['RequestStatusCode'] ?? 'DRAFT')));
        if (!in_array($status, ['APPROVED', 'PARTIAL'], true)) {
            throw new \RuntimeException('Only approved requests can be published.');
        }

        $lines = $this->listSegmentPublishRequestLines($requestId);
        $approvedLines = array_values(array_filter($lines, static fn(array $row): bool => strtoupper(trim((string) ($row['LineStatusCode'] ?? ''))) === 'APPROVED'));
        if ($approvedLines === []) {
            throw new \RuntimeException('There are no approved lines to publish.');
        }

        $published = 0;
        $failed = 0;

        $this->conn->beginTransaction();
        try {
            $insert = $this->conn->prepare("
                INSERT INTO dbo.tblSegmentValues (
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
                    ParentSegmentCode
                )
                VALUES (
                    :FiscalYearID,
                    :DataObjectCode,
                    :SegmentNo,
                    :SegmentCode,
                    :SegmentName,
                    :SegmentExternalID,
                    NULL,
                    :SortOrder,
                    :ActiveFlag,
                    :UpdatedBy,
                    SYSDATETIME(),
                    :ParentSegmentNo,
                    :ParentSegmentCode
                )
            ");

            foreach ($approvedLines as $line) {
                $existsStmt = $this->conn->prepare("
                    SELECT TOP 1 SegmentValueID
                    FROM dbo.tblSegmentValues
                    WHERE FiscalYearID = :FiscalYearID
                      AND DataObjectCode = :DataObjectCode
                      AND SegmentNo = :SegmentNo
                      AND SegmentCode = :SegmentCode
                      AND ActiveFlag = 1
                    ORDER BY SegmentValueID ASC
                ");
                $existsStmt->execute([
                    ':FiscalYearID' => (int) ($request['FiscalYearID'] ?? 0),
                    ':DataObjectCode' => (string) ($line['DataObjectCode'] ?? ''),
                    ':SegmentNo' => (int) ($line['SegmentNo'] ?? 0),
                    ':SegmentCode' => (string) ($line['SegmentCode'] ?? ''),
                ]);
                $existingId = (int) ($existsStmt->fetchColumn() ?: 0);

                if ($existingId > 0) {
                    $failed++;
                    $this->conn->prepare("
                        UPDATE dbo.tblSbSegmentPublishRequestLine
                        SET LineStatusCode = N'FAILED',
                            LineStatusNote = N'An active tblSegmentValues row already exists for this fiscal year, scope, segment, and code.',
                            UpdatedBy = :UpdatedByFail,
                            UpdatedDate = SYSDATETIME()
                        WHERE StrategicSegmentPublishRequestLineID = :LineIDFail
                    ")->execute([
                        ':UpdatedByFail' => $userId,
                        ':LineIDFail' => (int) $line['StrategicSegmentPublishRequestLineID'],
                    ]);
                    continue;
                }

                $insert->execute([
                    ':FiscalYearID' => (int) ($request['FiscalYearID'] ?? 0),
                    ':DataObjectCode' => (string) ($line['DataObjectCode'] ?? ''),
                    ':SegmentNo' => (int) ($line['SegmentNo'] ?? 0),
                    ':SegmentCode' => (string) ($line['SegmentCode'] ?? ''),
                    ':SegmentName' => $line['SegmentName'] ?? null,
                    ':SegmentExternalID' => $line['SegmentExternalID'] ?? null,
                    ':SortOrder' => (int) ($line['SortOrder'] ?? 0),
                    ':ActiveFlag' => !empty($line['ActiveFlag']) ? 1 : 0,
                    ':UpdatedBy' => $userId,
                    ':ParentSegmentNo' => !empty($line['ParentSegmentNo']) ? (int) $line['ParentSegmentNo'] : null,
                    ':ParentSegmentCode' => trim((string) ($line['ParentSegmentCode'] ?? '')) !== '' ? (string) $line['ParentSegmentCode'] : null,
                ]);
                $segmentValueId = (int) $this->conn->lastInsertId();

                $this->conn->prepare("
                    UPDATE dbo.tblSbSegmentPublishRequestLine
                    SET LineStatusCode = N'PUBLISHED',
                        LineStatusNote = NULL,
                        PublishedSegmentValueID = :SegmentValueID,
                        PublishedBy = :PublishedByLine,
                        PublishedDate = SYSDATETIME(),
                        UpdatedBy = :UpdatedByLine,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicSegmentPublishRequestLineID = :LineIDPublished
                ")->execute([
                    ':SegmentValueID' => $segmentValueId,
                    ':PublishedByLine' => $userId,
                    ':UpdatedByLine' => $userId,
                    ':LineIDPublished' => (int) $line['StrategicSegmentPublishRequestLineID'],
                ]);
                $published++;
            }

            $remainingApprovedStmt = $this->conn->prepare("
                SELECT COUNT(*)
                FROM dbo.tblSbSegmentPublishRequestLine
                WHERE StrategicSegmentPublishRequestID = :RequestID
                  AND LineStatusCode = N'APPROVED'
            ");
            $remainingApprovedStmt->execute([':RequestID' => $requestId]);
            $remainingApproved = (int) ($remainingApprovedStmt->fetchColumn() ?: 0);

            $newRequestStatus = ($remainingApproved === 0 && $failed === 0) ? 'PUBLISHED' : 'PARTIAL';
            $this->conn->prepare("
                UPDATE dbo.tblSbSegmentPublishRequest
                SET RequestStatusCode = :RequestStatusCode,
                    PublishedBy = :PublishedByRequest,
                    PublishedDate = SYSDATETIME(),
                    UpdatedBy = :UpdatedByRequest,
                    UpdatedDate = SYSDATETIME()
                WHERE StrategicSegmentPublishRequestID = :RequestIDPublish
            ")->execute([
                ':RequestStatusCode' => $newRequestStatus,
                ':PublishedByRequest' => $userId,
                ':UpdatedByRequest' => $userId,
                ':RequestIDPublish' => $requestId,
            ]);

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'published' => $published,
            'failed' => $failed,
        ];
    }

    public function supportsFundingSubmissionWorkflow(): bool
    {
        return $this->tableExists('dbo.tblSbFundingSubmission')
            && $this->tableExists('dbo.tblSbFundingSubmissionLine');
    }

    public function supportsFundingSubmissionAttachments(): bool
    {
        return $this->tableExists('dbo.tblSbFundingSubmissionAttachment');
    }

    public function supportsFundingSubmissionReviewResponseFields(): bool
    {
        return $this->supportsFundingSubmissionWorkflow()
            && $this->tableColumnExists('dbo.tblSbFundingSubmissionLine', 'ReviewerResponse')
            && $this->tableColumnExists('dbo.tblSbFundingSubmissionLine', 'ReviewerConditions')
            && $this->tableColumnExists('dbo.tblSbFundingSubmissionLine', 'ReviewerNextSteps');
    }

    public function supportsFundingSubmissionHeaderAssessmentFields(): bool
    {
        return $this->supportsFundingSubmissionWorkflow()
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerRanking')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'AssessmentGrade')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerRecommendation')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerSummary')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerConditions')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerNextSteps');
    }

    public function getFundingSubmissionTypeOptions(): array
    {
        return [
            ['code' => 'NEW_SPENDING', 'label' => 'New Spending'],
            ['code' => 'EXPANSION', 'label' => 'Expansion'],
            ['code' => 'REALLOCATION', 'label' => 'Reallocation'],
            ['code' => 'SAVINGS', 'label' => 'Savings'],
            ['code' => 'CAPITAL', 'label' => 'Capital'],
            ['code' => 'DONOR', 'label' => 'Donor'],
        ];
    }

    public function getFundingSubmissionPriorityOptions(): array
    {
        return [
            ['code' => 'LOW', 'label' => 'Low'],
            ['code' => 'MEDIUM', 'label' => 'Medium'],
            ['code' => 'HIGH', 'label' => 'High'],
            ['code' => 'CRITICAL', 'label' => 'Critical'],
        ];
    }

    public function getFundingSubmissionAssessmentGradeOptions(): array
    {
        return [
            ['code' => 'A', 'label' => 'A - Strong'],
            ['code' => 'B', 'label' => 'B - Good'],
            ['code' => 'C', 'label' => 'C - Moderate'],
            ['code' => 'D', 'label' => 'D - Weak'],
            ['code' => 'E', 'label' => 'E - Not Recommended'],
        ];
    }

    public function getFundingSubmissionReviewerRecommendationOptions(): array
    {
        return [
            ['code' => 'RECOMMEND_APPROVE', 'label' => 'Recommend Approve'],
            ['code' => 'RECOMMEND_PARTIAL', 'label' => 'Recommend Partial Approval'],
            ['code' => 'RECOMMEND_REJECT', 'label' => 'Recommend Reject'],
            ['code' => 'RECOMMEND_RESUBMISSION', 'label' => 'Recommend Resubmission'],
        ];
    }

    public function listFundingSubmissions(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            return [];
        }

        $groupByColumns = [
            's.StrategicFundingSubmissionID',
            's.FiscalYearID',
            's.VersionID',
            's.DataObjectCode',
            's.OrgUnitID',
            'ou.OrgUnitName',
            'doc.DataObjectName',
            'wf.Status',
            's.RequestTitle',
            's.RequestNotes',
            's.SubmissionTypeCode',
            's.PriorityCode',
            's.SubmissionStatusCode',
            's.TotalRequestedAmount',
            's.TotalApprovedAmount',
            's.SubmittedBy',
            's.SubmittedDate',
            's.ReviewedBy',
            's.ReviewedDate',
            's.ApprovedBy',
            's.ApprovedDate',
            's.DecisionNote',
            's.ActiveFlag',
            's.CreatedBy',
            's.CreatedDate',
            's.UpdatedBy',
            's.UpdatedDate',
        ];

        if ($this->supportsFundingSubmissionHeaderAssessmentFields()) {
            $groupByColumns = array_merge($groupByColumns, [
                's.ReviewerRanking',
                's.AssessmentGrade',
                's.ReviewerRecommendation',
                's.ReviewerSummary',
                's.ReviewerConditions',
                's.ReviewerNextSteps',
            ]);
        }

        $stmt = $this->conn->prepare("
            SELECT
                s.*,
                ou.OrgUnitName,
                COALESCE(doc.DataObjectName, ou.OrgUnitName, s.DataObjectCode) AS DataObjectName,
                wf.Status AS DataObjectWorkflowStatus,
                COUNT(l.StrategicFundingSubmissionLineID) AS LineCount,
                COALESCE(SUM(COALESCE(l.CurrentYearRequestedAmount, 0)), 0) AS CalculatedRequestedAmount,
                COALESCE(SUM(COALESCE(l.CurrentYearApprovedAmount, 0)), 0) AS CalculatedApprovedAmount
            FROM dbo.tblSbFundingSubmission s
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = s.OrgUnitID
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = s.FiscalYearID
               AND doc.DataObjectCode = s.DataObjectCode
            OUTER APPLY (
                SELECT TOP 1 w.Status
                FROM dbo.tblDataObjectWorkflowStatus w
                WHERE w.FiscalYearID = s.FiscalYearID
                  AND w.DataObjectCode = s.DataObjectCode
                ORDER BY CASE WHEN w.VersionID = s.VersionID THEN 0 ELSE 1 END,
                         w.DateUpdated DESC,
                         w.WorkflowStatusID DESC
            ) wf
            LEFT JOIN dbo.tblSbFundingSubmissionLine l
                ON l.StrategicFundingSubmissionID = s.StrategicFundingSubmissionID
            WHERE s.FiscalYearID = :fy
              AND s.VersionID = :ver
              AND s.ActiveFlag = 1
            GROUP BY " . implode(",\n                ", $groupByColumns) . "
            ORDER BY s.StrategicFundingSubmissionID DESC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFundingSubmissionSummaryReport(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            return [
                'summary' => [
                    'SubmissionCount' => 0,
                    'LineCount' => 0,
                    'RequestedAmount' => 0.0,
                    'ApprovedAmount' => 0.0,
                    'PublishedAmount' => 0.0,
                ],
                'bySector' => [],
                'byProgram' => [],
                'submissions' => [],
            ];
        }

        $summaryStmt = $this->conn->prepare("
            SELECT
                COUNT(DISTINCT s.StrategicFundingSubmissionID) AS SubmissionCount,
                COUNT(l.StrategicFundingSubmissionLineID) AS LineCount,
                COALESCE(SUM(COALESCE(l.CurrentYearRequestedAmount, 0)), 0) AS RequestedAmount,
                COALESCE(SUM(COALESCE(l.CurrentYearApprovedAmount, 0)), 0) AS ApprovedAmount,
                COALESCE(SUM(CASE WHEN l.PublishedCeilingID IS NOT NULL THEN COALESCE(l.CurrentYearApprovedAmount, 0) ELSE 0 END), 0) AS PublishedAmount
            FROM dbo.tblSbFundingSubmission s
            LEFT JOIN dbo.tblSbFundingSubmissionLine l
                ON l.StrategicFundingSubmissionID = s.StrategicFundingSubmissionID
            WHERE s.FiscalYearID = :fy
              AND s.VersionID = :ver
              AND s.ActiveFlag = 1
        ");
        $summaryStmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $sectorStmt = $this->conn->prepare("
            SELECT
                COALESCE(sec.SectorName, N'Unassigned') AS SectorName,
                COUNT(l.StrategicFundingSubmissionLineID) AS LineCount,
                COALESCE(SUM(COALESCE(l.CurrentYearRequestedAmount, 0)), 0) AS RequestedAmount,
                COALESCE(SUM(COALESCE(l.CurrentYearApprovedAmount, 0)), 0) AS ApprovedAmount,
                COALESCE(SUM(CASE WHEN l.PublishedCeilingID IS NOT NULL THEN COALESCE(l.CurrentYearApprovedAmount, 0) ELSE 0 END), 0) AS PublishedAmount
            FROM dbo.tblSbFundingSubmission s
            INNER JOIN dbo.tblSbFundingSubmissionLine l
                ON l.StrategicFundingSubmissionID = s.StrategicFundingSubmissionID
            LEFT JOIN dbo.tblSbSector sec
                ON sec.SectorID = l.SectorID
            WHERE s.FiscalYearID = :fy
              AND s.VersionID = :ver
              AND s.ActiveFlag = 1
            GROUP BY COALESCE(sec.SectorName, N'Unassigned')
            ORDER BY RequestedAmount DESC, SectorName ASC
        ");
        $sectorStmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);
        $bySector = $sectorStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $programStmt = $this->conn->prepare("
            SELECT
                COALESCE(p.ProgramName, N'Unassigned') AS ProgramName,
                COALESCE(sec.SectorName, N'Unassigned') AS SectorName,
                COUNT(l.StrategicFundingSubmissionLineID) AS LineCount,
                COALESCE(SUM(COALESCE(l.CurrentYearRequestedAmount, 0)), 0) AS RequestedAmount,
                COALESCE(SUM(COALESCE(l.CurrentYearApprovedAmount, 0)), 0) AS ApprovedAmount,
                COALESCE(SUM(CASE WHEN l.PublishedCeilingID IS NOT NULL THEN COALESCE(l.CurrentYearApprovedAmount, 0) ELSE 0 END), 0) AS PublishedAmount
            FROM dbo.tblSbFundingSubmission s
            INNER JOIN dbo.tblSbFundingSubmissionLine l
                ON l.StrategicFundingSubmissionID = s.StrategicFundingSubmissionID
            LEFT JOIN dbo.tblSbProgram p
                ON p.ProgramID = l.ProgramID
            LEFT JOIN dbo.tblSbSector sec
                ON sec.SectorID = l.SectorID
            WHERE s.FiscalYearID = :fy
              AND s.VersionID = :ver
              AND s.ActiveFlag = 1
            GROUP BY COALESCE(p.ProgramName, N'Unassigned'), COALESCE(sec.SectorName, N'Unassigned')
            ORDER BY RequestedAmount DESC, ProgramName ASC
        ");
        $programStmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);
        $byProgram = $programStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'summary' => $summary,
            'bySector' => $bySector,
            'byProgram' => $byProgram,
            'submissions' => $this->listFundingSubmissions($fiscalYearId, $versionId),
        ];
    }

    public function getFundingSubmission(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT
                s.*,
                ou.OrgUnitName,
                COALESCE(doc.DataObjectName, ou.OrgUnitName, s.DataObjectCode) AS DataObjectName,
                wf.Status AS DataObjectWorkflowStatus
            FROM dbo.tblSbFundingSubmission
            s
            LEFT JOIN dbo.tblSbOrgUnit ou
                ON ou.OrgUnitID = s.OrgUnitID
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = s.FiscalYearID
               AND doc.DataObjectCode = s.DataObjectCode
            OUTER APPLY (
                SELECT TOP 1 w.Status
                FROM dbo.tblDataObjectWorkflowStatus w
                WHERE w.FiscalYearID = s.FiscalYearID
                  AND w.DataObjectCode = s.DataObjectCode
                ORDER BY CASE WHEN w.VersionID = s.VersionID THEN 0 ELSE 1 END,
                         w.DateUpdated DESC,
                         w.WorkflowStatusID DESC
            ) wf
            WHERE s.StrategicFundingSubmissionID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listFundingSubmissionAttachments(int $submissionId): array
    {
        if ($submissionId <= 0 || !$this->tableExists('dbo.tblSbFundingSubmissionAttachment')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbFundingSubmissionAttachment
            WHERE StrategicFundingSubmissionID = :submissionId
              AND ActiveFlag = 1
            ORDER BY StrategicFundingSubmissionAttachmentID DESC
        ");
        $stmt->execute([':submissionId' => $submissionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFundingSubmissionAttachment(int $attachmentId): ?array
    {
        if ($attachmentId <= 0 || !$this->tableExists('dbo.tblSbFundingSubmissionAttachment')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbFundingSubmissionAttachment
            WHERE StrategicFundingSubmissionAttachmentID = :attachmentId
              AND ActiveFlag = 1
        ");
        $stmt->execute([':attachmentId' => $attachmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveFundingSubmissionAttachment(int $submissionId, array $data): int
    {
        if ($submissionId <= 0 || !$this->tableExists('dbo.tblSbFundingSubmissionAttachment')) {
            throw new \RuntimeException('Funding submission attachment storage is not available.');
        }

        $submission = $this->getFundingSubmission($submissionId);
        if ($submission === null) {
            throw new \RuntimeException('Funding submission was not found.');
        }

        $status = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        if (!in_array($status, ['DRAFT', 'REJECTED'], true)) {
            throw new \RuntimeException('Attachments can only be added while a submission is draft or rejected.');
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbFundingSubmissionAttachment (
                StrategicFundingSubmissionID,
                OriginalFileName,
                StoredFileName,
                MimeType,
                FileSizeBytes,
                StoragePath,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :StrategicFundingSubmissionID,
                :OriginalFileName,
                :StoredFileName,
                :MimeType,
                :FileSizeBytes,
                :StoragePath,
                1,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':StrategicFundingSubmissionID' => $submissionId,
            ':OriginalFileName' => trim((string) ($data['OriginalFileName'] ?? '')),
            ':StoredFileName' => trim((string) ($data['StoredFileName'] ?? '')),
            ':MimeType' => $this->nullableString($data['MimeType'] ?? null),
            ':FileSizeBytes' => (int) ($data['FileSizeBytes'] ?? 0),
            ':StoragePath' => trim((string) ($data['StoragePath'] ?? '')),
            ':CreatedBy' => (int) ($data['UserID'] ?? 1),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function deleteFundingSubmissionAttachment(int $attachmentId, int $userId): void
    {
        $attachment = $this->getFundingSubmissionAttachment($attachmentId);
        if ($attachment === null) {
            return;
        }

        $submission = $this->getFundingSubmission((int) ($attachment['StrategicFundingSubmissionID'] ?? 0));
        if ($submission === null) {
            return;
        }

        $status = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        if (!in_array($status, ['DRAFT', 'REJECTED'], true)) {
            throw new \RuntimeException('Attachments can only be removed while a submission is draft or rejected.');
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFundingSubmissionAttachment
            SET ActiveFlag = 0,
                UpdatedBy = :UpdatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE StrategicFundingSubmissionAttachmentID = :AttachmentID
        ");
        $stmt->execute([
            ':UpdatedBy' => $userId,
            ':AttachmentID' => $attachmentId,
        ]);

        $path = trim((string) ($attachment['StoragePath'] ?? ''));
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }

    public function saveFundingSubmission(array $data, ?int $id = null): int
    {
        if (!$this->supportsFundingSubmissionWorkflow()) {
            throw new \RuntimeException('Funding submissions are not available until create_tblSbFundingSubmission.sql is run.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $versionId = (int) ($data['VersionID'] ?? 0);
        $dataObjectCode = trim((string) ($data['DataObjectCode'] ?? ''));
        $requestTitle = trim((string) ($data['RequestTitle'] ?? ''));
        $requestNotes = trim((string) ($data['RequestNotes'] ?? ''));
        $submissionTypeCode = strtoupper(trim((string) ($data['SubmissionTypeCode'] ?? 'NEW_SPENDING')));
        $priorityCode = strtoupper(trim((string) ($data['PriorityCode'] ?? 'MEDIUM')));
        $decisionNoteProvided = array_key_exists('DecisionNote', $data);
        $decisionNote = trim((string) ($data['DecisionNote'] ?? ''));
        $userId = (int) ($data['UserID'] ?? 0);

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            throw new \RuntimeException('A valid fiscal context is required.');
        }
        if ($dataObjectCode === '') {
            throw new \RuntimeException('Submitting DataObjectCode is required.');
        }
        if ($requestTitle === '') {
            throw new \RuntimeException('Request title is required.');
        }

        $validTypes = array_column($this->getFundingSubmissionTypeOptions(), 'code');
        if (!in_array($submissionTypeCode, $validTypes, true)) {
            throw new \RuntimeException('Submission type is not valid.');
        }

        $validPriorities = array_column($this->getFundingSubmissionPriorityOptions(), 'code');
        if (!in_array($priorityCode, $validPriorities, true)) {
            throw new \RuntimeException('Priority is not valid.');
        }

        $orgUnitId = $this->ensureOrgUnitFromDataObject($fiscalYearId, $dataObjectCode, $userId);

        if ($id !== null && $id > 0) {
            $existing = $this->getFundingSubmission($id);
            if ($existing === null) {
                throw new \RuntimeException('Funding submission was not found.');
            }

            $status = strtoupper(trim((string) ($existing['SubmissionStatusCode'] ?? 'DRAFT')));
            if (!in_array($status, ['DRAFT', 'REJECTED'], true)) {
                throw new \RuntimeException('Only draft or rejected lodgements can be edited.');
            }
            if (!$decisionNoteProvided) {
                $decisionNote = trim((string) ($existing['DecisionNote'] ?? ''));
            }

            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbFundingSubmission
                SET DataObjectCode = :DataObjectCode,
                    OrgUnitID = :OrgUnitID,
                    RequestTitle = :RequestTitle,
                    RequestNotes = :RequestNotes,
                    SubmissionTypeCode = :SubmissionTypeCode,
                    PriorityCode = :PriorityCode,
                    DecisionNote = :DecisionNote,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE StrategicFundingSubmissionID = :SubmissionID
            ");
            $stmt->execute([
                ':DataObjectCode' => $dataObjectCode,
                ':OrgUnitID' => $orgUnitId,
                ':RequestTitle' => $requestTitle,
                ':RequestNotes' => $requestNotes !== '' ? $requestNotes : null,
                ':SubmissionTypeCode' => $submissionTypeCode,
                ':PriorityCode' => $priorityCode,
                ':DecisionNote' => $decisionNote !== '' ? $decisionNote : null,
                ':UpdatedBy' => $userId,
                ':SubmissionID' => $id,
            ]);

            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbFundingSubmission (
                FiscalYearID,
                VersionID,
                DataObjectCode,
                OrgUnitID,
                RequestTitle,
                RequestNotes,
                SubmissionTypeCode,
                PriorityCode,
                SubmissionStatusCode,
                DecisionNote,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :FiscalYearID,
                :VersionID,
                :DataObjectCode,
                :OrgUnitID,
                :RequestTitle,
                :RequestNotes,
                :SubmissionTypeCode,
                :PriorityCode,
                N'DRAFT',
                :DecisionNote,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':FiscalYearID' => $fiscalYearId,
            ':VersionID' => $versionId,
            ':DataObjectCode' => $dataObjectCode,
            ':OrgUnitID' => $orgUnitId,
            ':RequestTitle' => $requestTitle,
            ':RequestNotes' => $requestNotes !== '' ? $requestNotes : null,
            ':SubmissionTypeCode' => $submissionTypeCode,
            ':PriorityCode' => $priorityCode,
            ':DecisionNote' => $decisionNote !== '' ? $decisionNote : null,
            ':CreatedBy' => $userId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function listFundingSubmissionLines(int $submissionId): array
    {
        if ($submissionId <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                l.*,
                sec.SectorName,
                p.ProgramName,
                sp.SubProgramName,
                pr.ProjectName,
                a.ActivityName,
                ft.FundingTypeName,
                ft.FundingTypeCode,
                fs.FundingSourceName,
                ei.EconomicCode,
                ei.EconomicName
            FROM dbo.tblSbFundingSubmissionLine l
            LEFT JOIN dbo.tblSbSector sec
                ON sec.SectorID = l.SectorID
            LEFT JOIN dbo.tblSbProgram p
                ON p.ProgramID = l.ProgramID
            LEFT JOIN dbo.tblSbSubProgram sp
                ON sp.SubProgramID = l.SubProgramID
            LEFT JOIN dbo.tblSbProject pr
                ON pr.ProjectID = l.ProjectID
            LEFT JOIN dbo.tblSbActivity a
                ON a.ActivityID = l.ActivityID
            LEFT JOIN dbo.tblSbFundingType ft
                ON ft.FundingTypeID = l.FundingTypeID
            LEFT JOIN dbo.tblSbFundingSource fs
                ON fs.FundingSourceID = l.FundingSourceID
            LEFT JOIN dbo.tblSbEconomicItem ei
                ON ei.EconomicItemID = l.EconomicItemID
            WHERE l.StrategicFundingSubmissionID = :submissionId
            ORDER BY COALESCE(l.PriorityRank, 999999) ASC, l.BidTitle ASC, l.StrategicFundingSubmissionLineID ASC
        ");
        $stmt->execute([':submissionId' => $submissionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFundingSubmissionLine(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbFundingSubmissionLine
            WHERE StrategicFundingSubmissionLineID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getFundingSubmissionLineDetail(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT
                l.*,
                sec.SectorName,
                p.ProgramName,
                sp.SubProgramName,
                pr.ProjectName,
                a.ActivityName,
                ft.FundingTypeName,
                ft.FundingTypeCode,
                fs.FundingSourceName,
                ei.EconomicCode,
                ei.EconomicName
            FROM dbo.tblSbFundingSubmissionLine l
            LEFT JOIN dbo.tblSbSector sec
                ON sec.SectorID = l.SectorID
            LEFT JOIN dbo.tblSbProgram p
                ON p.ProgramID = l.ProgramID
            LEFT JOIN dbo.tblSbSubProgram sp
                ON sp.SubProgramID = l.SubProgramID
            LEFT JOIN dbo.tblSbProject pr
                ON pr.ProjectID = l.ProjectID
            LEFT JOIN dbo.tblSbActivity a
                ON a.ActivityID = l.ActivityID
            LEFT JOIN dbo.tblSbFundingType ft
                ON ft.FundingTypeID = l.FundingTypeID
            LEFT JOIN dbo.tblSbFundingSource fs
                ON fs.FundingSourceID = l.FundingSourceID
            LEFT JOIN dbo.tblSbEconomicItem ei
                ON ei.EconomicItemID = l.EconomicItemID
            WHERE l.StrategicFundingSubmissionLineID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveFundingSubmissionLine(array $data, ?int $id = null): int
    {
        if (!$this->supportsFundingSubmissionWorkflow()) {
            throw new \RuntimeException('Funding submissions are not available until create_tblSbFundingSubmission.sql is run.');
        }

        $submissionId = (int) ($data['StrategicFundingSubmissionID'] ?? 0);
        $submission = $this->getFundingSubmission($submissionId);
        if ($submission === null) {
            throw new \RuntimeException('Funding submission was not found.');
        }

        $submissionStatus = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        if (!in_array($submissionStatus, ['DRAFT', 'REJECTED'], true)) {
            throw new \RuntimeException('Only draft or rejected lodgements can be edited.');
        }

        $bidTitle = trim((string) ($data['BidTitle'] ?? ''));
        if ($bidTitle === '') {
            throw new \RuntimeException('Bid title is required.');
        }

        $requestedAmount = $this->nullableDecimal($data['CurrentYearRequestedAmount'] ?? null, false);
        if ($requestedAmount === null || (float) $requestedAmount <= 0) {
            throw new \RuntimeException('Current year requested amount is required and must be greater than zero.');
        }

        $fiscalYearId = (int) ($submission['FiscalYearID'] ?? 0);
        $dataObjectCode = trim((string) ($submission['DataObjectCode'] ?? ''));
        $sectorId = $this->nullableInt($data['SectorID'] ?? null);
        $programId = $this->nullableInt($data['ProgramID'] ?? null);
        $subProgramId = $this->nullableInt($data['SubProgramID'] ?? null);
        $projectId = $this->nullableInt($data['ProjectID'] ?? null);
        $activityId = $this->nullableInt($data['ActivityID'] ?? null);
        $orgUnitId = $this->nullableInt($data['OrgUnitID'] ?? null);

        if ($subProgramId !== null && $programId === null) {
            throw new \RuntimeException('Select a program before choosing a subprogram.');
        }
        if ($activityId !== null && $programId === null) {
            throw new \RuntimeException('Select a program before choosing an activity.');
        }
        if ($sectorId !== null && !$this->sectorBelongsToDataObject($sectorId, $fiscalYearId, $dataObjectCode)) {
            throw new \RuntimeException('Selected sector is not available for the current System DataScope.');
        }
        if ($orgUnitId !== null && !$this->orgUnitBelongsToDataObject($orgUnitId, $fiscalYearId, $dataObjectCode)) {
            throw new \RuntimeException('Selected org unit is not available for the current System DataScope.');
        }
        if ($programId !== null && !$this->programBelongsToDataObject($programId, $fiscalYearId, $dataObjectCode)) {
            throw new \RuntimeException('Selected program is not available for the current System DataScope.');
        }
        if ($programId !== null && $subProgramId !== null && !$this->subProgramBelongsToProgram($subProgramId, $programId)) {
            throw new \RuntimeException('Selected subprogram does not belong to the selected program.');
        }
        if ($programId !== null && $activityId !== null && !$this->activityBelongsToProgram($activityId, $programId, $subProgramId)) {
            throw new \RuntimeException('Selected activity does not belong to the selected program/subprogram combination.');
        }
        if ($projectId !== null && !$this->projectBelongsToDataObject($projectId, $fiscalYearId, $dataObjectCode)) {
            throw new \RuntimeException('Selected project is not available for the current System DataScope.');
        }
        if ($programId !== null) {
            $program = $this->getProgram($programId);
            if ($program === null) {
                throw new \RuntimeException('Selected program was not found.');
            }
            $programSectorId = (int) ($program['SectorID'] ?? 0);
            if ($sectorId !== null && $programSectorId > 0 && $programSectorId !== $sectorId) {
                throw new \RuntimeException('Selected program does not belong to the selected sector.');
            }
        }

        $payload = [
            ':SubmissionID' => $submissionId,
            ':SectorID' => $sectorId,
            ':ProgramID' => $programId,
            ':SubProgramID' => $subProgramId,
            ':ProjectID' => $projectId,
            ':ActivityID' => $activityId,
            ':OrgUnitID' => $orgUnitId,
            ':FundingTypeID' => $this->nullableInt($data['FundingTypeID'] ?? null),
            ':FundingSourceID' => $this->nullableInt($data['FundingSourceID'] ?? null),
            ':EconomicItemID' => $this->nullableInt($data['EconomicItemID'] ?? null),
            ':BidTitle' => $bidTitle,
            ':BusinessCaseSummary' => $this->nullableString($data['BusinessCaseSummary'] ?? null),
            ':ExpectedOutput' => $this->nullableString($data['ExpectedOutput'] ?? null),
            ':ExpectedOutcome' => $this->nullableString($data['ExpectedOutcome'] ?? null),
            ':CurrentYearRequestedAmount' => $requestedAmount,
            ':OuterYear1RequestedAmount' => $this->nullableDecimal($data['OuterYear1RequestedAmount'] ?? null),
            ':OuterYear2RequestedAmount' => $this->nullableDecimal($data['OuterYear2RequestedAmount'] ?? null),
            ':OuterYear3RequestedAmount' => $this->nullableDecimal($data['OuterYear3RequestedAmount'] ?? null),
            ':OuterYear4RequestedAmount' => $this->nullableDecimal($data['OuterYear4RequestedAmount'] ?? null),
            ':OuterYear5RequestedAmount' => $this->nullableDecimal($data['OuterYear5RequestedAmount'] ?? null),
            ':PriorityRank' => $this->nullableInt($data['PriorityRank'] ?? null),
            ':ScoreStrategicAlignment' => $this->nullableDecimal($data['ScoreStrategicAlignment'] ?? null),
            ':ScoreReadiness' => $this->nullableDecimal($data['ScoreReadiness'] ?? null),
            ':ScoreFiscalAffordability' => $this->nullableDecimal($data['ScoreFiscalAffordability'] ?? null),
            ':ScoreServiceImpact' => $this->nullableDecimal($data['ScoreServiceImpact'] ?? null),
            ':ActiveFlag' => !empty($data['ActiveFlag']) ? 1 : 0,
            ':UserID' => (int) ($data['UserID'] ?? 0),
        ];

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbFundingSubmissionLine
                SET SectorID = :SectorID,
                    ProgramID = :ProgramID,
                    SubProgramID = :SubProgramID,
                    ProjectID = :ProjectID,
                    ActivityID = :ActivityID,
                    OrgUnitID = :OrgUnitID,
                    FundingTypeID = :FundingTypeID,
                    FundingSourceID = :FundingSourceID,
                    EconomicItemID = :EconomicItemID,
                    BidTitle = :BidTitle,
                    BusinessCaseSummary = :BusinessCaseSummary,
                    ExpectedOutput = :ExpectedOutput,
                    ExpectedOutcome = :ExpectedOutcome,
                    CurrentYearRequestedAmount = :CurrentYearRequestedAmount,
                    OuterYear1RequestedAmount = :OuterYear1RequestedAmount,
                    OuterYear2RequestedAmount = :OuterYear2RequestedAmount,
                    OuterYear3RequestedAmount = :OuterYear3RequestedAmount,
                    OuterYear4RequestedAmount = :OuterYear4RequestedAmount,
                    OuterYear5RequestedAmount = :OuterYear5RequestedAmount,
                    PriorityRank = :PriorityRank,
                    ScoreStrategicAlignment = :ScoreStrategicAlignment,
                    ScoreReadiness = :ScoreReadiness,
                    ScoreFiscalAffordability = :ScoreFiscalAffordability,
                    ScoreServiceImpact = :ScoreServiceImpact,
                    ActiveFlag = :ActiveFlag,
                    LineStatusCode = N'DRAFT',
                    UpdatedBy = :UserID,
                    UpdatedDate = SYSDATETIME()
                WHERE StrategicFundingSubmissionLineID = :LineID
                  AND LineStatusCode IN (N'DRAFT', N'REJECTED')
            ");
            $updatePayload = $payload;
            unset($updatePayload[':SubmissionID']);
            $stmt->execute($updatePayload + [':LineID' => $id]);
            $this->syncFundingSubmissionTotals($submissionId);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbFundingSubmissionLine (
                StrategicFundingSubmissionID,
                SectorID,
                ProgramID,
                SubProgramID,
                ProjectID,
                ActivityID,
                OrgUnitID,
                FundingTypeID,
                FundingSourceID,
                EconomicItemID,
                BidTitle,
                BusinessCaseSummary,
                ExpectedOutput,
                ExpectedOutcome,
                CurrentYearRequestedAmount,
                OuterYear1RequestedAmount,
                OuterYear2RequestedAmount,
                OuterYear3RequestedAmount,
                OuterYear4RequestedAmount,
                OuterYear5RequestedAmount,
                PriorityRank,
                ScoreStrategicAlignment,
                ScoreReadiness,
                ScoreFiscalAffordability,
                ScoreServiceImpact,
                LineStatusCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :SubmissionID,
                :SectorID,
                :ProgramID,
                :SubProgramID,
                :ProjectID,
                :ActivityID,
                :OrgUnitID,
                :FundingTypeID,
                :FundingSourceID,
                :EconomicItemID,
                :BidTitle,
                :BusinessCaseSummary,
                :ExpectedOutput,
                :ExpectedOutcome,
                :CurrentYearRequestedAmount,
                :OuterYear1RequestedAmount,
                :OuterYear2RequestedAmount,
                :OuterYear3RequestedAmount,
                :OuterYear4RequestedAmount,
                :OuterYear5RequestedAmount,
                :PriorityRank,
                :ScoreStrategicAlignment,
                :ScoreReadiness,
                :ScoreFiscalAffordability,
                :ScoreServiceImpact,
                N'DRAFT',
                :ActiveFlag,
                :UserID,
                SYSDATETIME()
            )
        ");
        $stmt->execute($payload);
        $lineId = (int) $this->conn->lastInsertId();
        $this->syncFundingSubmissionTotals($submissionId);

        return $lineId;
    }

    public function deleteFundingSubmissionLine(int $id): void
    {
        if ($id <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            return;
        }

        $line = $this->getFundingSubmissionLine($id);
        if ($line === null) {
            return;
        }

        $submission = $this->getFundingSubmission((int) ($line['StrategicFundingSubmissionID'] ?? 0));
        if ($submission === null) {
            return;
        }

        $status = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        if (!in_array($status, ['DRAFT', 'REJECTED'], true)) {
            throw new \RuntimeException('Only draft or rejected lodgements can remove lines.');
        }

        $stmt = $this->conn->prepare("
            DELETE FROM dbo.tblSbFundingSubmissionLine
            WHERE StrategicFundingSubmissionLineID = :id
              AND LineStatusCode IN (N'DRAFT', N'REJECTED')
        ");
        $stmt->execute([':id' => $id]);
        $this->syncFundingSubmissionTotals((int) ($line['StrategicFundingSubmissionID'] ?? 0));
    }

    public function transitionFundingSubmission(int $submissionId, string $action, int $userId): void
    {
        if ($submissionId <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            throw new \RuntimeException('Funding submission workflow is not installed.');
        }

        $submission = $this->getFundingSubmission($submissionId);
        if ($submission === null) {
            throw new \RuntimeException('Funding submission was not found.');
        }

        $submittedBy = (int) ($submission['SubmittedBy'] ?? 0);
        $reviewedBy = (int) ($submission['ReviewedBy'] ?? 0);

        $status = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        $action = strtolower(trim($action));

        $this->conn->beginTransaction();
        try {
            if ($action === 'lodge') {
                if (!in_array($status, ['DRAFT', 'REJECTED'], true)) {
                    throw new \RuntimeException('Only draft or rejected lodgements can be lodged.');
                }
                if (count($this->listFundingSubmissionLines($submissionId)) <= 0) {
                    throw new \RuntimeException('Add at least one funding item before lodging.');
                }
                $workflowStatus = strtoupper(trim((string) ($submission['DataObjectWorkflowStatus'] ?? $this->getDataObjectWorkflowStatus((int) ($submission['FiscalYearID'] ?? 0), (int) ($submission['VersionID'] ?? 0), (string) ($submission['DataObjectCode'] ?? '')))));
                if ($workflowStatus !== 'OPEN') {
                    throw new \RuntimeException('This lodgement cannot be lodged because the linked DataScope is not Open.');
                }

                $this->conn->prepare("
                    UPDATE dbo.tblSbFundingSubmission
                    SET SubmissionStatusCode = N'LODGED',
                        UpdatedBy = :UserIDUpdate,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionID = :SubmissionID
                ")->execute([
                    ':UserIDUpdate' => $userId,
                    ':SubmissionID' => $submissionId,
                ]);
                $this->recordFundingSubmissionHistory($submissionId, null, 'LODGE', $status, 'LODGED', null, $userId);
            } elseif ($action === 'submit') {
                if ($status !== 'LODGED') {
                    throw new \RuntimeException('Only lodged submissions can be submitted for review.');
                }

                $this->conn->prepare("
                    UPDATE dbo.tblSbFundingSubmission
                    SET SubmissionStatusCode = N'PENDING',
                        SubmittedBy = :UserID,
                        SubmittedDate = SYSDATETIME(),
                        UpdatedBy = :UserIDUpdate,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionID = :SubmissionID
                ")->execute([
                    ':UserID' => $userId,
                    ':UserIDUpdate' => $userId,
                    ':SubmissionID' => $submissionId,
                ]);
                $this->conn->prepare("
                    UPDATE dbo.tblSbFundingSubmissionLine
                    SET LineStatusCode = N'PENDING',
                        UpdatedBy = :UserID,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionID = :SubmissionID
                      AND LineStatusCode IN (N'DRAFT', N'REJECTED')
                ")->execute([
                    ':UserID' => $userId,
                    ':SubmissionID' => $submissionId,
                ]);
                $this->recordFundingSubmissionHistory($submissionId, null, 'SUBMIT', $status, 'PENDING', null, $userId);
            } elseif ($action === 'approve') {
                $this->assertFundingSubmissionApprovalActorSeparation($submittedBy, $reviewedBy, $userId);
                if ($status !== 'REVIEWED') {
                    throw new \RuntimeException('Only reviewed submissions can be approved.');
                }

                $lines = $this->listFundingSubmissionLines($submissionId);
                $lineStatuses = array_values(array_unique(array_map(
                    static fn(array $line): string => strtoupper(trim((string) ($line['LineStatusCode'] ?? 'DRAFT'))),
                    $lines
                )));
                if ($lines === [] || count(array_intersect($lineStatuses, ['DRAFT', 'PENDING'])) > 0) {
                    throw new \RuntimeException('All funding items must be reviewed before overall approval.');
                }

                if (count(array_diff($lineStatuses, ['REJECTED'])) === 0) {
                    throw new \RuntimeException('All funding items are rejected. Use Reject for the submission instead of Approve.');
                }

                $nextSubmissionStatus = count(array_diff($lineStatuses, ['APPROVED', 'FUNDED'])) === 0 ? 'APPROVED' : 'PARTIAL';

                $this->conn->prepare("
                    UPDATE dbo.tblSbFundingSubmission
                    SET SubmissionStatusCode = :SubmissionStatusCode,
                        ApprovedBy = :ApprovedBy,
                        ApprovedDate = SYSDATETIME(),
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionID = :SubmissionID
                ")->execute([
                    ':SubmissionStatusCode' => $nextSubmissionStatus,
                    ':ApprovedBy' => $userId,
                    ':UpdatedBy' => $userId,
                    ':SubmissionID' => $submissionId,
                ]);
                $this->recordFundingSubmissionHistory($submissionId, null, 'APPROVE', $status, $nextSubmissionStatus, null, $userId);
            } elseif ($action === 'reject') {
                $this->assertFundingSubmissionApprovalActorSeparation($submittedBy, $reviewedBy, $userId);
                if ($status !== 'REVIEWED') {
                    throw new \RuntimeException('Only reviewed submissions can be rejected.');
                }
                $this->conn->prepare("
                    UPDATE dbo.tblSbFundingSubmission
                    SET SubmissionStatusCode = N'REJECTED',
                        ApprovedBy = :ApprovedBy,
                        ApprovedDate = SYSDATETIME(),
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionID = :SubmissionID
                ")->execute([
                    ':ApprovedBy' => $userId,
                    ':UpdatedBy' => $userId,
                    ':SubmissionID' => $submissionId,
                ]);
                $this->recordFundingSubmissionHistory($submissionId, null, 'REJECT', $status, 'REJECTED', null, $userId);
            } elseif ($action === 'fund') {
                if (!in_array($status, ['APPROVED', 'PARTIAL'], true)) {
                    throw new \RuntimeException('Only approved or partial submissions can be marked funded.');
                }
                $this->assertFundingSubmissionApprovalActorSeparation($submittedBy, $reviewedBy, $userId);

                $this->conn->prepare("
                    UPDATE dbo.tblSbFundingSubmission
                    SET SubmissionStatusCode = N'FUNDED',
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionID = :SubmissionID
                ")->execute([
                    ':UpdatedBy' => $userId,
                    ':SubmissionID' => $submissionId,
                ]);
                $this->conn->prepare("
                    UPDATE dbo.tblSbFundingSubmissionLine
                    SET LineStatusCode = N'FUNDED',
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionID = :SubmissionID
                      AND LineStatusCode IN (N'APPROVED', N'PARTIAL')
                ")->execute([
                    ':UpdatedBy' => $userId,
                    ':SubmissionID' => $submissionId,
                ]);
                $this->recordFundingSubmissionHistory($submissionId, null, 'FUND', $status, 'FUNDED', null, $userId);
            } else {
                throw new \RuntimeException('Unsupported funding submission action.');
            }

            $this->syncFundingSubmissionTotals($submissionId);
            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function saveFundingSubmissionLineReview(int $lineId, array $data): void
    {
        if ($lineId <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            throw new \RuntimeException('Funding submission workflow is not installed.');
        }

        $line = $this->getFundingSubmissionLine($lineId);
        if ($line === null) {
            throw new \RuntimeException('Funding submission line was not found.');
        }

        $submissionId = (int) ($line['StrategicFundingSubmissionID'] ?? 0);
        $submission = $this->getFundingSubmission($submissionId);
        if ($submission === null) {
            throw new \RuntimeException('Funding submission was not found.');
        }

        $submissionStatus = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        if (!in_array($submissionStatus, ['PENDING', 'REVIEWED'], true)) {
            throw new \RuntimeException('Only submitted-for-review or reviewed submissions can be reviewed.');
        }

        if ((int) ($submission['SubmittedBy'] ?? 0) > 0 && (int) ($submission['SubmittedBy'] ?? 0) === (int) ($data['UserID'] ?? 0)) {
            throw new \RuntimeException('The submitter cannot review their own submission.');
        }

        $currentLineStatus = strtoupper(trim((string) ($line['LineStatusCode'] ?? 'DRAFT')));
        if (!in_array($currentLineStatus, ['PENDING', 'APPROVED', 'PARTIAL', 'REJECTED'], true)) {
            throw new \RuntimeException('This line cannot be reviewed in its current state.');
        }

        $action = strtolower(trim((string) ($data['ReviewAction'] ?? '')));
        if (!in_array($action, ['approve', 'partial', 'reject'], true)) {
            throw new \RuntimeException('Review decision is not valid.');
        }

        $approvedCurrent = $this->nullableDecimal($data['CurrentYearApprovedAmount'] ?? null, true);
        $approvedOuter1 = $this->nullableDecimal($data['OuterYear1ApprovedAmount'] ?? null, true);
        $approvedOuter2 = $this->nullableDecimal($data['OuterYear2ApprovedAmount'] ?? null, true);
        $approvedOuter3 = $this->nullableDecimal($data['OuterYear3ApprovedAmount'] ?? null, true);
        $approvedOuter4 = $this->nullableDecimal($data['OuterYear4ApprovedAmount'] ?? null, true);
        $approvedOuter5 = $this->nullableDecimal($data['OuterYear5ApprovedAmount'] ?? null, true);
        $decisionNote = $this->nullableString($data['DecisionNote'] ?? null);
        $supportsReviewResponseFields = $this->supportsFundingSubmissionReviewResponseFields();
        $reviewerResponse = $supportsReviewResponseFields
            ? $this->nullableString($data['ReviewerResponse'] ?? null)
            : null;
        $reviewerConditions = $supportsReviewResponseFields
            ? $this->nullableString($data['ReviewerConditions'] ?? null)
            : null;
        $reviewerNextSteps = $supportsReviewResponseFields
            ? $this->nullableString($data['ReviewerNextSteps'] ?? null)
            : null;
        $userId = (int) ($data['UserID'] ?? 0);

        if ($action === 'approve') {
            $approvedCurrent = $this->copyRequestedAmount($line['CurrentYearRequestedAmount'] ?? null) ?? '0';
            $approvedOuter1 = $this->copyRequestedAmount($line['OuterYear1RequestedAmount'] ?? null);
            $approvedOuter2 = $this->copyRequestedAmount($line['OuterYear2RequestedAmount'] ?? null);
            $approvedOuter3 = $this->copyRequestedAmount($line['OuterYear3RequestedAmount'] ?? null);
            $approvedOuter4 = $this->copyRequestedAmount($line['OuterYear4RequestedAmount'] ?? null);
            $approvedOuter5 = $this->copyRequestedAmount($line['OuterYear5RequestedAmount'] ?? null);
        } elseif ($action === 'reject') {
            $approvedCurrent = '0';
            $approvedOuter1 = '0';
            $approvedOuter2 = '0';
            $approvedOuter3 = '0';
            $approvedOuter4 = '0';
            $approvedOuter5 = '0';
        }

        $approvedTotal = $this->decimalToFloat($approvedCurrent)
            + $this->decimalToFloat($approvedOuter1)
            + $this->decimalToFloat($approvedOuter2)
            + $this->decimalToFloat($approvedOuter3)
            + $this->decimalToFloat($approvedOuter4)
            + $this->decimalToFloat($approvedOuter5);

        $requestedTotal = $this->decimalToFloat($line['CurrentYearRequestedAmount'] ?? null)
            + $this->decimalToFloat($line['OuterYear1RequestedAmount'] ?? null)
            + $this->decimalToFloat($line['OuterYear2RequestedAmount'] ?? null)
            + $this->decimalToFloat($line['OuterYear3RequestedAmount'] ?? null)
            + $this->decimalToFloat($line['OuterYear4RequestedAmount'] ?? null)
            + $this->decimalToFloat($line['OuterYear5RequestedAmount'] ?? null);

        if ($approvedTotal < 0) {
            throw new \RuntimeException('Approved amounts cannot be negative.');
        }

        $nextStatus = match ($action) {
            'reject' => 'REJECTED',
            'approve' => 'APPROVED',
            default => ($approvedTotal <= 0.0 ? 'REJECTED' : (($requestedTotal > 0.0 && abs($approvedTotal - $requestedTotal) < 0.000001) ? 'APPROVED' : 'PARTIAL')),
        };

        $this->conn->beginTransaction();
        try {
            if ($supportsReviewResponseFields) {
                $updateSql = "
                    UPDATE dbo.tblSbFundingSubmissionLine
                    SET CurrentYearApprovedAmount = :CurrentYearApprovedAmount,
                        OuterYear1ApprovedAmount = :OuterYear1ApprovedAmount,
                        OuterYear2ApprovedAmount = :OuterYear2ApprovedAmount,
                        OuterYear3ApprovedAmount = :OuterYear3ApprovedAmount,
                        OuterYear4ApprovedAmount = :OuterYear4ApprovedAmount,
                        OuterYear5ApprovedAmount = :OuterYear5ApprovedAmount,
                        LineStatusCode = :LineStatusCode,
                        DecisionNote = :DecisionNote,
                        ReviewerResponse = :ReviewerResponse,
                        ReviewerConditions = :ReviewerConditions,
                        ReviewerNextSteps = :ReviewerNextSteps,
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionLineID = :LineID
                ";
                $params = [
                    ':CurrentYearApprovedAmount' => $approvedCurrent,
                    ':OuterYear1ApprovedAmount' => $approvedOuter1,
                    ':OuterYear2ApprovedAmount' => $approvedOuter2,
                    ':OuterYear3ApprovedAmount' => $approvedOuter3,
                    ':OuterYear4ApprovedAmount' => $approvedOuter4,
                    ':OuterYear5ApprovedAmount' => $approvedOuter5,
                    ':LineStatusCode' => $nextStatus,
                    ':DecisionNote' => $decisionNote,
                    ':ReviewerResponse' => $reviewerResponse,
                    ':ReviewerConditions' => $reviewerConditions,
                    ':ReviewerNextSteps' => $reviewerNextSteps,
                    ':UpdatedBy' => $userId,
                    ':LineID' => $lineId,
                ];
            } else {
                $updateSql = "
                    UPDATE dbo.tblSbFundingSubmissionLine
                    SET CurrentYearApprovedAmount = :CurrentYearApprovedAmount,
                        OuterYear1ApprovedAmount = :OuterYear1ApprovedAmount,
                        OuterYear2ApprovedAmount = :OuterYear2ApprovedAmount,
                        OuterYear3ApprovedAmount = :OuterYear3ApprovedAmount,
                        OuterYear4ApprovedAmount = :OuterYear4ApprovedAmount,
                        OuterYear5ApprovedAmount = :OuterYear5ApprovedAmount,
                        LineStatusCode = :LineStatusCode,
                        DecisionNote = :DecisionNote,
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicFundingSubmissionLineID = :LineID
                ";
                $params = [
                    ':CurrentYearApprovedAmount' => $approvedCurrent,
                    ':OuterYear1ApprovedAmount' => $approvedOuter1,
                    ':OuterYear2ApprovedAmount' => $approvedOuter2,
                    ':OuterYear3ApprovedAmount' => $approvedOuter3,
                    ':OuterYear4ApprovedAmount' => $approvedOuter4,
                    ':OuterYear5ApprovedAmount' => $approvedOuter5,
                    ':LineStatusCode' => $nextStatus,
                    ':DecisionNote' => $decisionNote,
                    ':UpdatedBy' => $userId,
                    ':LineID' => $lineId,
                ];
            }

            $stmt = $this->conn->prepare($updateSql);
            $stmt->execute($params);

            $this->recordFundingSubmissionHistory($submissionId, $lineId, strtoupper($action), $currentLineStatus, $nextStatus, $decisionNote, $userId);
            $this->syncFundingSubmissionTotals($submissionId);
            $this->refreshFundingSubmissionStatusFromLines($submissionId, $userId);
            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function saveFundingSubmissionAssessment(int $submissionId, array $data): void
    {
        if ($submissionId <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            throw new \RuntimeException('Funding submission workflow is not installed.');
        }
        if (!$this->supportsFundingSubmissionHeaderAssessmentFields()) {
            throw new \RuntimeException('Submission assessment fields are not installed.');
        }

        $submission = $this->getFundingSubmission($submissionId);
        if ($submission === null) {
            throw new \RuntimeException('Funding submission was not found.');
        }

        $status = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        $userId = (int) ($data['UserID'] ?? 0);
        if (!in_array($status, ['PENDING', 'REVIEWED'], true)) {
            throw new \RuntimeException('Only submitted-for-review or reviewed submissions can be assessed.');
        }
        if ((int) ($submission['SubmittedBy'] ?? 0) > 0 && (int) ($submission['SubmittedBy'] ?? 0) === $userId) {
            throw new \RuntimeException('The submitter cannot assess their own submission.');
        }

        $assessmentGrade = strtoupper(trim((string) ($data['AssessmentGrade'] ?? '')));
        $reviewerRecommendation = strtoupper(trim((string) ($data['ReviewerRecommendation'] ?? '')));
        $decisionNote = $this->nullableString($data['DecisionNote'] ?? null);
        $reviewerSummary = $this->nullableString($data['ReviewerSummary'] ?? null);
        $reviewerConditions = $this->nullableString($data['ReviewerConditions'] ?? null);
        $reviewerNextSteps = $this->nullableString($data['ReviewerNextSteps'] ?? null);
        $reviewerRanking = $this->nullableInt($data['ReviewerRanking'] ?? null);

        $validGrades = array_column($this->getFundingSubmissionAssessmentGradeOptions(), 'code');
        if ($assessmentGrade !== '' && !in_array($assessmentGrade, $validGrades, true)) {
            throw new \RuntimeException('Assessment grade is not valid.');
        }

        $validRecommendations = array_column($this->getFundingSubmissionReviewerRecommendationOptions(), 'code');
        if ($reviewerRecommendation !== '' && !in_array($reviewerRecommendation, $validRecommendations, true)) {
            throw new \RuntimeException('Reviewer recommendation is not valid.');
        }

        if ($reviewerRanking !== null && $reviewerRanking <= 0) {
            throw new \RuntimeException('Reviewer ranking must be greater than zero when provided.');
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFundingSubmission
            SET ReviewerRanking = :ReviewerRanking,
                AssessmentGrade = :AssessmentGrade,
                ReviewerRecommendation = :ReviewerRecommendation,
                DecisionNote = :DecisionNote,
                ReviewerSummary = :ReviewerSummary,
                ReviewerConditions = :ReviewerConditions,
                ReviewerNextSteps = :ReviewerNextSteps,
                ReviewedBy = :ReviewedBy,
                ReviewedDate = SYSDATETIME(),
                UpdatedBy = :UpdatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE StrategicFundingSubmissionID = :SubmissionID
        ");
        $stmt->execute([
            ':ReviewerRanking' => $reviewerRanking,
            ':AssessmentGrade' => $assessmentGrade !== '' ? $assessmentGrade : null,
            ':ReviewerRecommendation' => $reviewerRecommendation !== '' ? $reviewerRecommendation : null,
            ':DecisionNote' => $decisionNote,
            ':ReviewerSummary' => $reviewerSummary,
            ':ReviewerConditions' => $reviewerConditions,
            ':ReviewerNextSteps' => $reviewerNextSteps,
            ':ReviewedBy' => $userId,
            ':UpdatedBy' => $userId,
            ':SubmissionID' => $submissionId,
        ]);

        $historyNoteParts = [];
        if ($assessmentGrade !== '') {
            $historyNoteParts[] = 'Grade: ' . $assessmentGrade;
        }
        if ($reviewerRecommendation !== '') {
            $historyNoteParts[] = 'Recommendation: ' . $reviewerRecommendation;
        }
        if ($reviewerRanking !== null) {
            $historyNoteParts[] = 'Rank: ' . (string) $reviewerRanking;
        }
        $historyNote = $historyNoteParts !== [] ? implode('; ', $historyNoteParts) : 'Submission assessment updated.';
        $this->recordFundingSubmissionHistory($submissionId, null, 'ASSESS', $status, $status, $historyNote, $userId);
    }

    public function publishFundingSubmissionToSectorCeilings(int $submissionId, int $userId): array
    {
        if ($submissionId <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            throw new \RuntimeException('Funding submission workflow is not installed.');
        }
        if (!$this->supportsStrategicCeilings()) {
            throw new \RuntimeException('Strategic ceiling table is not installed.');
        }

        $submission = $this->getFundingSubmission($submissionId);
        if ($submission === null) {
            throw new \RuntimeException('Funding submission was not found.');
        }

        $status = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        if (!in_array($status, ['APPROVED', 'PARTIAL', 'FUNDED'], true)) {
            throw new \RuntimeException('Only approved, partial, or funded submissions can be published to sector ceilings.');
        }

        $lines = $this->listFundingSubmissionLines($submissionId);
        $eligibleLines = array_values(array_filter($lines, static function (array $line): bool {
            $status = strtoupper(trim((string) ($line['LineStatusCode'] ?? '')));
            return in_array($status, ['APPROVED', 'PARTIAL', 'FUNDED'], true)
                && (int) ($line['SectorID'] ?? 0) > 0
                && (float) ($line['CurrentYearApprovedAmount'] ?? 0) > 0;
        }));
        if ($eligibleLines === []) {
            throw new \RuntimeException('There are no approved lines with sector allocations to publish.');
        }

        $fiscalYearId = (int) ($submission['FiscalYearID'] ?? 0);
        $versionId = (int) ($submission['VersionID'] ?? 0);
        $bySector = [];
        foreach ($eligibleLines as $line) {
            $sectorId = (int) ($line['SectorID'] ?? 0);
            if (!isset($bySector[$sectorId])) {
                $bySector[$sectorId] = ['amount' => 0.0, 'lines' => []];
            }
            $bySector[$sectorId]['amount'] += (float) ($line['CurrentYearApprovedAmount'] ?? 0);
            $bySector[$sectorId]['lines'][] = (int) ($line['StrategicFundingSubmissionLineID'] ?? 0);
        }

        $created = 0;
        $updated = 0;
        $linesLinked = 0;

        $this->conn->beginTransaction();
        try {
            foreach ($bySector as $sectorId => $payload) {
                $existing = $this->findActiveSectorCeiling($fiscalYearId, $versionId, (int) $sectorId);
                $existingId = (int) ($existing['CeilingID'] ?? 0);
                $existingAmount = (float) ($existing['CeilingAmount'] ?? 0);
                $newAmount = round($existingAmount + (float) ($payload['amount'] ?? 0), 2);
                $note = 'Updated from funding submission #' . $submissionId . '.';

                $ceilingId = $this->saveSectorCeiling($fiscalYearId, $versionId, [
                    'SectorID' => (int) $sectorId,
                    'CeilingAmount' => $newAmount,
                    'Notes' => $existingId > 0
                        ? $this->mergeCeilingHelperNote((string) ($existing['Notes'] ?? ''), $note)
                        : $note,
                    'ActiveFlag' => 1,
                    'UserID' => $userId,
                ], $existingId > 0 ? $existingId : null);

                if ($existingId > 0) {
                    $updated++;
                } else {
                    $created++;
                }

                foreach ($payload['lines'] as $lineId) {
                    $this->conn->prepare("
                        UPDATE dbo.tblSbFundingSubmissionLine
                        SET PublishedCeilingID = :CeilingID,
                            PublishedPlanReference = :PublishedPlanReference,
                            LineStatusCode = N'FUNDED',
                            UpdatedBy = :UpdatedBy,
                            UpdatedDate = SYSDATETIME()
                        WHERE StrategicFundingSubmissionLineID = :LineID
                    ")->execute([
                        ':CeilingID' => $ceilingId,
                        ':PublishedPlanReference' => 'SECTOR_CEILING',
                        ':UpdatedBy' => $userId,
                        ':LineID' => $lineId,
                    ]);
                    $linesLinked++;
                    $this->recordFundingSubmissionHistory($submissionId, $lineId, 'PUBLISH_SECTOR_CEILING', null, 'FUNDED', 'Linked to sector ceiling #' . $ceilingId . '.', $userId);
                }
            }

            $this->conn->prepare("
                UPDATE dbo.tblSbFundingSubmission
                SET SubmissionStatusCode = N'FUNDED',
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE StrategicFundingSubmissionID = :SubmissionID
            ")->execute([
                ':UpdatedBy' => $userId,
                ':SubmissionID' => $submissionId,
            ]);
            $this->recordFundingSubmissionHistory($submissionId, null, 'PUBLISH_SECTOR_CEILING', $status, 'FUNDED', 'Published approved bid amounts into sector ceilings.', $userId);
            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'lines_linked' => $linesLinked,
        ];
    }

    public function listFundingSubmissionHistory(int $submissionId): array
    {
        if ($submissionId <= 0 || !$this->tableExists('dbo.tblSbFundingSubmissionHistory')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbFundingSubmissionHistory
            WHERE StrategicFundingSubmissionID = :submissionId
            ORDER BY ActionDate DESC, StrategicFundingSubmissionHistoryID DESC
        ");
        $stmt->execute([':submissionId' => $submissionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function syncFundingSubmissionTotals(int $submissionId): void
    {
        if ($submissionId <= 0 || !$this->supportsFundingSubmissionWorkflow()) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFundingSubmission
            SET TotalRequestedAmount = src.TotalRequestedAmount,
                TotalApprovedAmount = src.TotalApprovedAmount,
                UpdatedDate = SYSDATETIME()
            FROM dbo.tblSbFundingSubmission s
            CROSS APPLY (
                SELECT
                    COALESCE(SUM(COALESCE(l.CurrentYearRequestedAmount, 0)), 0) AS TotalRequestedAmount,
                    COALESCE(SUM(COALESCE(l.CurrentYearApprovedAmount, 0)), 0) AS TotalApprovedAmount
                FROM dbo.tblSbFundingSubmissionLine l
                WHERE l.StrategicFundingSubmissionID = s.StrategicFundingSubmissionID
            ) src
            WHERE s.StrategicFundingSubmissionID = :submissionId
        ");
        $stmt->execute([':submissionId' => $submissionId]);
    }

    private function refreshFundingSubmissionStatusFromLines(int $submissionId, int $userId): void
    {
        $submission = $this->getFundingSubmission($submissionId);
        if ($submission === null) {
            return;
        }

        $currentStatus = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        if (!in_array($currentStatus, ['PENDING', 'REVIEWED'], true)) {
            return;
        }

        $lines = $this->listFundingSubmissionLines($submissionId);
        if ($lines === []) {
            return;
        }

        $statuses = array_values(array_unique(array_map(
            static fn(array $line): string => strtoupper(trim((string) ($line['LineStatusCode'] ?? 'DRAFT'))),
            $lines
        )));

        $nextStatus = count(array_intersect($statuses, ['DRAFT', 'PENDING'])) > 0
            ? 'PENDING'
            : 'REVIEWED';

        if ($nextStatus === $currentStatus && !($nextStatus === 'REVIEWED' && (int) ($submission['ReviewedBy'] ?? 0) <= 0)) {
            return;
        }

        if ($nextStatus === 'REVIEWED') {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbFundingSubmission
                SET SubmissionStatusCode = :SubmissionStatusCode,
                    ReviewedBy = :ReviewedUserID,
                    ReviewedDate = SYSDATETIME(),
                    UpdatedBy = :UpdatedUserID,
                    UpdatedDate = SYSDATETIME()
                WHERE StrategicFundingSubmissionID = :SubmissionID
            ");
            $stmt->execute([
                ':SubmissionStatusCode' => $nextStatus,
                ':ReviewedUserID' => $userId,
                ':UpdatedUserID' => $userId,
                ':SubmissionID' => $submissionId,
            ]);
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFundingSubmission
            SET SubmissionStatusCode = :SubmissionStatusCode,
                UpdatedBy = :UpdatedUserID,
                UpdatedDate = SYSDATETIME()
            WHERE StrategicFundingSubmissionID = :SubmissionID
        ");
        $stmt->execute([
            ':SubmissionStatusCode' => $nextStatus,
            ':UpdatedUserID' => $userId,
            ':SubmissionID' => $submissionId,
        ]);
    }

    private function assertFundingSubmissionApprovalActorSeparation(int $submittedBy, int $reviewedBy, int $userId): void
    {
        if ($submittedBy > 0 && $submittedBy === $userId) {
            throw new \RuntimeException('The submitter cannot approve, reject, or fund their own submission.');
        }

        if ($reviewedBy > 0 && $reviewedBy === $userId) {
            throw new \RuntimeException('The reviewer cannot approve, reject, or fund the same submission they reviewed.');
        }
    }

    private function recordFundingSubmissionHistory(
        int $submissionId,
        ?int $lineId,
        string $actionCode,
        ?string $fromStatusCode,
        string $toStatusCode,
        ?string $note,
        int $userId
    ): void {
        if (!$this->tableExists('dbo.tblSbFundingSubmissionHistory')) {
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbFundingSubmissionHistory (
                StrategicFundingSubmissionID,
                StrategicFundingSubmissionLineID,
                WorkflowActionCode,
                FromStatusCode,
                ToStatusCode,
                ActionNote,
                ActionBy,
                ActionDate
            )
            VALUES (
                :SubmissionID,
                :LineID,
                :ActionCode,
                :FromStatusCode,
                :ToStatusCode,
                :ActionNote,
                :ActionBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':SubmissionID' => $submissionId,
            ':LineID' => $lineId,
            ':ActionCode' => strtoupper(trim($actionCode)),
            ':FromStatusCode' => $this->nullableString($fromStatusCode),
            ':ToStatusCode' => strtoupper(trim($toStatusCode)),
            ':ActionNote' => $this->nullableString($note),
            ':ActionBy' => $userId,
        ]);
    }

    private function findActiveSectorCeiling(int $fiscalYearId, int $versionId, int $sectorId): ?array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || $sectorId <= 0 || !$this->supportsStrategicCeilings()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblSbCeiling
            WHERE FiscalYearID = :FiscalYearID
              AND VersionID = :VersionID
              AND SectorID = :SectorID
              AND ScopeTypeCode = N'SECTOR'
              AND ActiveFlag = 1
            ORDER BY CeilingID ASC
        ");
        $stmt->execute([
            ':FiscalYearID' => $fiscalYearId,
            ':VersionID' => $versionId,
            ':SectorID' => $sectorId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function copyRequestedAmount($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function decimalToFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    private function nullableDecimal($value, bool $allowNull = true): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return $allowNull ? null : '0';
        }

        if (!is_numeric((string) $value)) {
            throw new \RuntimeException('Amount and score fields must be numeric.');
        }

        return (string) $value;
    }

    private function nullableString($value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function countDistinctMappedSegmentValues(int $fiscalYearId, string $dimensionCode): int
    {
        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, $dimensionCode);
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM (
                SELECT DISTINCT sv.SegmentCode
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND sv.ActiveFlag = 1
            ) src
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function countActiveRows(string $tableName): int
    {
        if (!$this->tableExists($tableName)) {
            return 0;
        }

        $stmt = $this->conn->query("SELECT COUNT(*) FROM {$tableName} WHERE ActiveFlag = 1");
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function resolveDataObjectDisplayName(int $fiscalYearId, string $dataObjectCode): string
    {
        $dataObjectCode = trim($dataObjectCode);
        if ($dataObjectCode === '0') {
            return 'Global';
        }
        if ($fiscalYearId <= 0 || $dataObjectCode === '') {
            return $dataObjectCode;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 DataObjectName
            FROM dbo.tblDataObjectCodes
            WHERE FiscalYearID = :fy
              AND DataObjectCode = :code
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':code' => $dataObjectCode,
        ]);

        $name = trim((string) ($stmt->fetchColumn() ?: ''));
        return $name !== '' ? $name : $dataObjectCode;
    }

    private function getDataObjectWorkflowStatus(int $fiscalYearId, int $versionId, string $dataObjectCode): string
    {
        $dataObjectCode = trim($dataObjectCode);
        if ($fiscalYearId <= 0 || $versionId <= 0 || $dataObjectCode === '') {
            return '';
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 COALESCE(Status, '')
            FROM dbo.tblDataObjectWorkflowStatus
            WHERE FiscalYearID = :fy
              AND DataObjectCode = :code
            ORDER BY CASE WHEN VersionID = :ver THEN 0 ELSE 1 END,
                     DateUpdated DESC,
                     WorkflowStatusID DESC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':code' => $dataObjectCode,
        ]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    private function getDataObjectScopeChain(int $fiscalYearId, string $dataObjectCode): array
    {
        $dataObjectCode = trim($dataObjectCode);
        if ($fiscalYearId <= 0 || $dataObjectCode === '') {
            return [];
        }
        if ($dataObjectCode === '0') {
            return ['0'];
        }

        $chain = [];
        $seen = [];
        $current = $dataObjectCode;

        while ($current !== '' && !isset($seen[$current])) {
            $chain[] = $current;
            $seen[$current] = true;

            $stmt = $this->conn->prepare("
                SELECT TOP 1 DataObjectCodeParent
                FROM dbo.tblDataObjectCodes
                WHERE FiscalYearID = :fy
                  AND DataObjectCode = :code
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':code' => $current,
            ]);
            $parent = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($parent === '' || isset($seen[$parent])) {
                break;
            }
            $current = $parent;
        }

        if (!isset($seen['0'])) {
            $chain[] = '0';
        }

        return $chain;
    }

    private function getDataObjectDescendantScope(int $fiscalYearId, string $dataObjectCode): array
    {
        $dataObjectCode = trim($dataObjectCode);
        if ($fiscalYearId <= 0 || $dataObjectCode === '') {
            return [];
        }

        $scope = [];
        $seen = [];
        $queue = [$dataObjectCode];

        while ($queue !== []) {
            $current = trim((string) array_shift($queue));
            if ($current === '' || isset($seen[$current])) {
                continue;
            }

            $seen[$current] = true;
            $scope[] = $current;

            $stmt = $this->conn->prepare("
                SELECT DataObjectCode
                FROM dbo.tblDataObjectCodes
                WHERE FiscalYearID = :fy
                  AND DataObjectCodeParent = :parentCode
                ORDER BY DataObjectCode ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':parentCode' => $current,
            ]);

            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $childCodeRaw) {
                $childCode = trim((string) $childCodeRaw);
                if ($childCode !== '' && !isset($seen[$childCode])) {
                    $queue[] = $childCode;
                }
            }
        }

        return $scope;
    }

    private function materializeOrgUnitsForDataObjectScope(int $fiscalYearId, array $dataObjectCodes, int $userId): void
    {
        if ($fiscalYearId <= 0 || $dataObjectCodes === []) {
            return;
        }

        $normalizedCodes = array_values(array_filter(array_map(static function ($code): string {
            return trim((string) $code);
        }, $dataObjectCodes), static fn(string $code): bool => $code !== ''));

        if ($normalizedCodes === []) {
            return;
        }

        foreach ($normalizedCodes as $code) {
            $this->ensureOrgUnitFromDataObject($fiscalYearId, $code, $userId > 0 ? $userId : 1);
        }
    }

    private function buildScopeInListParams(array $scopeCodes, string $prefix, array &$params): string
    {
        $placeholders = [];
        foreach (array_values($scopeCodes) as $index => $scopeCode) {
            $placeholder = ':' . $prefix . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $scopeCode;
        }

        if ($placeholders === []) {
            $placeholder = ':' . $prefix . 'Fallback';
            $params[$placeholder] = '';
            $placeholders[] = $placeholder;
        }

        return implode(', ', $placeholders);
    }

    private function resolveSegmentValueSourceRow(int $fiscalYearId, int $segmentNo, string $requestedDataObjectCode, string $segmentCode): ?array
    {
        $segmentCode = trim($segmentCode);
        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $requestedDataObjectCode);
        if ($fiscalYearId <= 0 || $segmentNo <= 0 || $segmentCode === '' || $scopeCodes === []) {
            return null;
        }

        $inParams = [];
        $params = [
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
            ':segmentCode' => $segmentCode,
        ];

        foreach ($scopeCodes as $index => $scopeCode) {
            $param = ':scope' . $index;
            $inParams[] = $param;
            $params[$param] = $scopeCode;
        }

        $rankParts = [];
        foreach ($scopeCodes as $index => $scopeCode) {
            $rankParts[] = "WHEN sv.DataObjectCode = :rankScope{$index} THEN {$index}";
            $params[':rankScope' . $index] = $scopeCode;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1
                sv.FiscalYearID,
                sv.DataObjectCode,
                sv.SegmentCode,
                sv.SegmentName,
                sv.ParentSegmentNo,
                sv.ParentSegmentCode
            FROM dbo.tblSegmentValues sv
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.SegmentCode = :segmentCode
              AND sv.ActiveFlag = 1
              AND sv.DataObjectCode IN (" . implode(', ', $inParams) . ")
            ORDER BY CASE " . implode(' ', $rankParts) . " ELSE 999 END ASC
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function listSegmentValueSourceCandidates(int $fiscalYearId, int $segmentNo, array $scopeCodes, string $segmentCode): array
    {
        $params = [
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
            ':segmentCode' => $segmentCode,
        ];
        $scopeSql = $this->buildScopeInListParams($scopeCodes, 'candidateScope', $params);

        $stmt = $this->conn->prepare("
            SELECT
                sv.DataObjectCode,
                sv.SegmentCode,
                sv.SegmentName,
                sv.ParentSegmentNo,
                sv.ParentSegmentCode
            FROM dbo.tblSegmentValues sv
            WHERE sv.FiscalYearID = :fy
              AND sv.SegmentNo = :segmentNo
              AND sv.SegmentCode = :segmentCode
              AND sv.ActiveFlag = 1
              AND sv.DataObjectCode IN ({$scopeSql})
            ORDER BY sv.DataObjectCode ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row) use ($fiscalYearId): array {
            $code = (string) ($row['DataObjectCode'] ?? '');
            return [
                'DataObjectCode' => $code,
                'DataObjectName' => $this->resolveDataObjectDisplayName($fiscalYearId, $code),
                'SegmentCode' => (string) ($row['SegmentCode'] ?? ''),
                'SegmentName' => (string) ($row['SegmentName'] ?? ''),
                'ParentSegmentNo' => (int) ($row['ParentSegmentNo'] ?? 0),
                'ParentSegmentCode' => (string) ($row['ParentSegmentCode'] ?? ''),
            ];
        }, $rows);
    }

    private function tableColumnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM sys.columns c
            INNER JOIN sys.objects o
                ON o.object_id = c.object_id
            INNER JOIN sys.schemas s
                ON s.schema_id = o.schema_id
            WHERE (s.name + '.' + o.name) = :tableName
              AND c.name = :columnName
        ");
        $stmt->execute([
            ':tableName' => $tableName,
            ':columnName' => $columnName,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM sys.objects o
            INNER JOIN sys.schemas s
                ON s.schema_id = o.schema_id
            WHERE (s.name + '.' + o.name) = :tableName
              AND o.type = 'U'
        ");
        $stmt->execute([':tableName' => $tableName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function resolveStrategicDimensionSource(int $fiscalYearId, string $dimensionCode, string $dataObjectCode, string $segmentCode): array
    {
        $dimensionCode = strtoupper(trim($dimensionCode));
        $dataObjectCode = trim($dataObjectCode);
        $segmentCode = trim($segmentCode);

        $result = [
            'dimension_code' => $dimensionCode,
            'requested_data_object_code' => $dataObjectCode,
            'requested_data_object_name' => $this->resolveDataObjectDisplayName($fiscalYearId, $dataObjectCode),
            'segment_code' => $segmentCode,
            'mapped_segment_no' => 0,
            'status' => 'invalid',
            'message' => 'Fiscal year, dimension, DataObjectCode, and segment code are required.',
            'resolution_type' => null,
            'requested_scope_chain' => [],
            'matched_row' => null,
            'candidates' => [],
        ];

        if ($fiscalYearId <= 0 || $dimensionCode === '' || $dataObjectCode === '' || $segmentCode === '') {
            return $result;
        }

        $mapping = $this->getStrategicSegmentMapping($fiscalYearId, $dimensionCode);
        $segmentNo = (int) ($mapping['SegmentNo'] ?? 0);
        $result['mapped_segment_no'] = $segmentNo;
        if ($segmentNo <= 0) {
            $result['status'] = 'unmapped';
            $result['message'] = 'This strategic dimension is not mapped for the active fiscal year.';
            return $result;
        }

        $scopeCodes = $this->getDataObjectScopeChain($fiscalYearId, $dataObjectCode);
        $result['requested_scope_chain'] = array_map(function (string $code) use ($fiscalYearId): array {
            return [
                'DataObjectCode' => $code,
                'DataObjectName' => $this->resolveDataObjectDisplayName($fiscalYearId, $code),
            ];
        }, $scopeCodes);

        $resolved = $this->resolveSegmentValueSourceRow($fiscalYearId, $segmentNo, $dataObjectCode, $segmentCode);
        $result['candidates'] = $this->listSegmentValueSourceCandidates($fiscalYearId, $segmentNo, $scopeCodes, $segmentCode);

        if ($resolved === null) {
            $result['status'] = 'not_found';
            $result['message'] = 'No active source row was found for this dimension, DataObjectCode scope, and segment code.';
            return $result;
        }

        $matchedCode = (string) ($resolved['DataObjectCode'] ?? '');
        $resolutionType = 'parent';
        if ($matchedCode === $dataObjectCode) {
            $resolutionType = 'exact';
        } elseif ($matchedCode === '0') {
            $resolutionType = 'global';
        }

        $result['status'] = 'resolved';
        $result['resolution_type'] = $resolutionType;
        $result['matched_row'] = [
            'DataObjectCode' => $matchedCode,
            'DataObjectName' => $this->resolveDataObjectDisplayName($fiscalYearId, $matchedCode),
            'SegmentCode' => (string) ($resolved['SegmentCode'] ?? ''),
            'SegmentName' => (string) ($resolved['SegmentName'] ?? ''),
            'ParentSegmentNo' => (int) ($resolved['ParentSegmentNo'] ?? 0),
            'ParentSegmentCode' => (string) ($resolved['ParentSegmentCode'] ?? ''),
        ];
        $result['message'] = match ($resolutionType) {
            'exact' => 'Resolved from an exact DataObjectCode match.',
            'global' => 'Resolved from the global DataObjectCode 0 scope.',
            default => 'Resolved from the nearest parent DataObjectCode scope.',
        };

        return $result;
    }

    public function supportsProgramOrgLinks(): bool
    {
        return $this->tableExists('dbo.tblSbProgramOrgLink');
    }

    public function supportsProjectSourceMaps(): bool
    {
        return $this->tableExists('dbo.tblSbProjectSourceMap');
    }

    public function supportsProjectProgramLinks(): bool
    {
        return $this->tableExists('dbo.tblSbProjectProgramLink');
    }

    public function supportsProjectObjectiveLinks(): bool
    {
        return $this->tableExists('dbo.tblSbProjectObjectiveLink');
    }

    public function supportsProjectOrgUnitLinks(): bool
    {
        return $this->tableExists('dbo.tblSbProjectOrgUnitLink');
    }

    public function supportsNarrativeProjectLink(): bool
    {
        return $this->tableColumnExists('dbo.tblSbNarrative', 'ProjectID');
    }

    public function supportsFiscalRiskProjectLink(): bool
    {
        return $this->tableColumnExists('dbo.tblSbFiscalRisk', 'ProjectID');
    }

    public function upsertProjectSourceMapping(array $data): void
    {
        if (!$this->supportsProjectSourceMaps()) {
            return;
        }

        $existing = $this->conn->prepare("
            SELECT TOP 1 ProjectSourceMapID
            FROM dbo.tblSbProjectSourceMap
            WHERE FiscalYearID = :FiscalYearID
              AND COALESCE(DataObjectCode, N'') = COALESCE(:DataObjectCode, N'')
              AND SourceSegmentNo = :SourceSegmentNo
              AND SourceSegmentCode = :SourceSegmentCode
            ORDER BY ProjectSourceMapID ASC
        ");
        $existing->execute([
            ':FiscalYearID' => $data['FiscalYearID'],
            ':DataObjectCode' => $data['DataObjectCode'],
            ':SourceSegmentNo' => $data['SourceSegmentNo'],
            ':SourceSegmentCode' => $data['SourceSegmentCode'],
        ]);
        $mappingId = (int) ($existing->fetchColumn() ?: 0);

        if ($mappingId > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblSbProjectSourceMap
                SET ProjectID = :ProjectID,
                    SourceSegmentName = :SourceSegmentName,
                    SourceSystemCode = :SourceSystemCode,
                    IsPrimaryFlag = :IsPrimaryFlag,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ProjectSourceMapID = :ProjectSourceMapID
            ");
            $stmt->execute([
                ':ProjectID' => $data['ProjectID'],
                ':SourceSegmentName' => $data['SourceSegmentName'],
                ':SourceSystemCode' => $data['SourceSystemCode'],
                ':IsPrimaryFlag' => $data['IsPrimaryFlag'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $data['UserID'],
                ':ProjectSourceMapID' => $mappingId,
            ]);
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbProjectSourceMap (
                ProjectID,
                FiscalYearID,
                DataObjectCode,
                SourceSegmentNo,
                SourceSegmentCode,
                SourceSegmentName,
                SourceSystemCode,
                IsPrimaryFlag,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ProjectID,
                :FiscalYearID,
                :DataObjectCode,
                :SourceSegmentNo,
                :SourceSegmentCode,
                :SourceSegmentName,
                :SourceSystemCode,
                :IsPrimaryFlag,
                :ActiveFlag,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':ProjectID' => $data['ProjectID'],
            ':FiscalYearID' => $data['FiscalYearID'],
            ':DataObjectCode' => $data['DataObjectCode'],
            ':SourceSegmentNo' => $data['SourceSegmentNo'],
            ':SourceSegmentCode' => $data['SourceSegmentCode'],
            ':SourceSegmentName' => $data['SourceSegmentName'],
            ':SourceSystemCode' => $data['SourceSystemCode'],
            ':IsPrimaryFlag' => $data['IsPrimaryFlag'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $data['UserID'],
        ]);
    }

    public function syncProjectProgramLinks(int $projectId, array $programIds, int $userId): void
    {
        if ($projectId <= 0 || !$this->supportsProjectProgramLinks()) {
            return;
        }

        $normalized = array_values(array_unique(array_filter(array_map('intval', $programIds), static fn(int $id): bool => $id > 0)));
        $this->conn->prepare("
            UPDATE dbo.tblSbProjectProgramLink
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ProjectID = :projectId
        ")->execute([
            ':userId' => $userId,
            ':projectId' => $projectId,
        ]);

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbProjectProgramLink (
                ProjectID,
                ProgramID,
                LinkTypeCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ProjectID,
                :ProgramID,
                N'PRIMARY',
                1,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        foreach ($normalized as $programId) {
            $stmt->execute([
                ':ProjectID' => $projectId,
                ':ProgramID' => $programId,
                ':CreatedBy' => $userId,
            ]);
        }
    }

    public function syncProjectObjectiveLinks(int $projectId, array $objectiveIds, int $userId): void
    {
        if ($projectId <= 0 || !$this->supportsProjectObjectiveLinks()) {
            return;
        }

        $normalized = array_values(array_unique(array_filter(array_map('intval', $objectiveIds), static fn(int $id): bool => $id > 0)));
        $this->conn->prepare("
            UPDATE dbo.tblSbProjectObjectiveLink
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ProjectID = :projectId
        ")->execute([
            ':userId' => $userId,
            ':projectId' => $projectId,
        ]);

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbProjectObjectiveLink (
                ProjectID,
                ObjectiveID,
                ContributionTypeCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ProjectID,
                :ObjectiveID,
                N'DIRECT',
                1,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        foreach ($normalized as $objectiveId) {
            $stmt->execute([
                ':ProjectID' => $projectId,
                ':ObjectiveID' => $objectiveId,
                ':CreatedBy' => $userId,
            ]);
        }
    }

    public function syncProjectOrgUnitsByDataObjectCodes(int $projectId, int $fiscalYearId, array $dataObjectCodes, array $excludedOrgUnitIds, int $userId): void
    {
        if ($projectId <= 0 || !$this->supportsProjectOrgUnitLinks()) {
            return;
        }

        $normalizedCodes = array_values(array_unique(array_filter(array_map(
            static fn(mixed $code): string => trim((string) $code),
            $dataObjectCodes
        ), static fn(string $code): bool => $code !== '')));
        $excludedOrgUnitIds = array_values(array_unique(array_filter(array_map('intval', $excludedOrgUnitIds), static fn(int $id): bool => $id > 0)));

        $this->conn->prepare("
            UPDATE dbo.tblSbProjectOrgUnitLink
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ProjectID = :projectId
        ")->execute([
            ':userId' => $userId,
            ':projectId' => $projectId,
        ]);

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblSbProjectOrgUnitLink (
                ProjectID,
                OrgUnitID,
                RoleCode,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ProjectID,
                :OrgUnitID,
                N'IMPLEMENTING',
                1,
                :CreatedBy,
                SYSDATETIME()
            )
        ");
        foreach ($normalizedCodes as $code) {
            $orgUnitId = $this->ensureOrgUnitFromDataObject($fiscalYearId, $code, $userId);
            if ($orgUnitId <= 0 || in_array($orgUnitId, $excludedOrgUnitIds, true)) {
                continue;
            }
            $stmt->execute([
                ':ProjectID' => $projectId,
                ':OrgUnitID' => $orgUnitId,
                ':CreatedBy' => $userId,
            ]);
        }
    }

    private function findActiveProjectByCodeOrName(string $projectCode, string $projectName): ?array
    {
        if (!$this->tableExists('dbo.tblSbProject')) {
            return null;
        }

        $projectCode = trim($projectCode);
        $projectName = trim($projectName);
        if ($projectCode === '' && $projectName === '') {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 ProjectID, ProjectCode, ProjectName
            FROM dbo.tblSbProject
            WHERE ActiveFlag = 1
              AND (
                    (:projectCodeCheck <> N'' AND ProjectCode = :projectCodeMatch)
                 OR (:projectNameCheck <> N'' AND ProjectName = :projectNameMatch)
              )
            ORDER BY CASE WHEN ProjectCode = :projectCodeOrder THEN 0 ELSE 1 END,
                     ProjectID ASC
        ");
        $stmt->execute([
            ':projectCodeCheck' => $projectCode,
            ':projectCodeMatch' => $projectCode,
            ':projectCodeOrder' => $projectCode,
            ':projectNameCheck' => $projectName,
            ':projectNameMatch' => $projectName,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function normalizeImportResetMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        return in_array($normalized, ['none', 'soft', 'hard'], true) ? $normalized : 'none';
    }

    private function resetStandaloneDimensionImportsForFiscalYear(
        int $fiscalYearId,
        int $segmentNo,
        int $userId,
        string $mode,
        array $config
    ): array {
        $mode = $this->normalizeImportResetMode($mode);
        $summary = [
            'mode' => $mode,
            'records_archived' => 0,
            'records_deleted' => 0,
            'records_preserved' => 0,
            'blocked' => 0,
        ];

        $tableName = (string) ($config['table'] ?? '');
        $keyColumn = (string) ($config['key_column'] ?? '');
        if (
            $fiscalYearId <= 0
            || $segmentNo <= 0
            || $mode === 'none'
            || $tableName === ''
            || $keyColumn === ''
            || !$this->tableExists($tableName)
            || !$this->hasSourceTrackingColumnsForTable($tableName)
        ) {
            return $summary;
        }

        foreach ($this->listStandaloneDimensionIdsForImportScope($tableName, $keyColumn, $fiscalYearId, $segmentNo) as $entityId) {
            if ($entityId <= 0) {
                continue;
            }

            $blockers = $this->getStandaloneDimensionResetUsageBlockers($entityId, $config['blockers'] ?? []);
            if ($blockers !== []) {
                $summary['blocked']++;
                $summary['records_preserved']++;
                continue;
            }

            if ($mode === 'hard') {
                $this->cleanupStandaloneDimensionRelations($entityId, $config['cleanup_tables'] ?? [], true, $userId);
                $this->deleteDimensionAttributeValuesForEntity((string) ($config['dimension_code'] ?? ''), $entityId);
                $this->deleteStandaloneDimensionRow($tableName, $keyColumn, $entityId);
                $summary['records_deleted']++;
                continue;
            }

            $this->cleanupStandaloneDimensionRelations($entityId, $config['cleanup_tables'] ?? [], false, $userId);
            $this->deleteDimensionAttributeValuesForEntity((string) ($config['dimension_code'] ?? ''), $entityId);
            $this->deactivateStandaloneDimensionRow($tableName, $keyColumn, $entityId, $userId);
            $summary['records_archived']++;
        }

        return $summary;
    }

    private function listStandaloneDimensionIdsForImportScope(
        string $tableName,
        string $keyColumn,
        int $fiscalYearId,
        int $segmentNo
    ): array {
        if (
            $tableName === ''
            || $keyColumn === ''
            || !$this->tableExists($tableName)
            || !$this->hasSourceTrackingColumnsForTable($tableName)
        ) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT {$keyColumn}
            FROM {$tableName}
            WHERE SourceFiscalYearID = :fy
              AND SourceSegmentNo = :segmentNo
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);

        return array_values(array_filter(
            array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []),
            static fn(int $id): bool => $id > 0
        ));
    }

    private function getStandaloneDimensionResetUsageBlockers(int $entityId, array $blockerConfigs): array
    {
        if ($entityId <= 0) {
            return [];
        }

        $blockers = [];
        foreach ($blockerConfigs as $config) {
            $tableName = (string) ($config['table'] ?? '');
            $columnName = (string) ($config['column'] ?? '');
            $label = (string) ($config['label'] ?? $tableName);
            $count = $this->countDimensionReferences($tableName, $columnName, $entityId);
            if ($count > 0) {
                $blockers[] = $label . ' (' . $count . ')';
            }
        }

        return $blockers;
    }

    private function countDimensionReferences(string $tableName, string $columnName, int $entityId): int
    {
        if (
            $entityId <= 0
            || $tableName === ''
            || $columnName === ''
            || !$this->tableExists($tableName)
            || !$this->tableColumnExists($tableName, $columnName)
        ) {
            return 0;
        }

        $where = "{$columnName} = :entityId";
        if ($this->tableColumnExists($tableName, 'ActiveFlag')) {
            $where .= ' AND ActiveFlag = 1';
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$tableName} WHERE {$where}");
        $stmt->execute([':entityId' => $entityId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function cleanupStandaloneDimensionRelations(int $entityId, array $cleanupTables, bool $hardDelete, int $userId): void
    {
        if ($entityId <= 0) {
            return;
        }

        foreach ($cleanupTables as $config) {
            $tableName = (string) ($config['table'] ?? '');
            $columnName = (string) ($config['column'] ?? '');
            if ($tableName === '' || $columnName === '' || !$this->tableExists($tableName) || !$this->tableColumnExists($tableName, $columnName)) {
                continue;
            }

            if (!$hardDelete && $this->tableColumnExists($tableName, 'ActiveFlag')) {
                $stmt = $this->conn->prepare("
                    UPDATE {$tableName}
                    SET ActiveFlag = 0,
                        UpdatedBy = :userId,
                        UpdatedDate = SYSDATETIME()
                    WHERE {$columnName} = :entityId
                      AND ActiveFlag = 1
                ");
                $stmt->execute([
                    ':userId' => $userId,
                    ':entityId' => $entityId,
                ]);
                continue;
            }

            $stmt = $this->conn->prepare("DELETE FROM {$tableName} WHERE {$columnName} = :entityId");
            $stmt->execute([':entityId' => $entityId]);
        }
    }

    private function deactivateStandaloneDimensionRow(string $tableName, string $keyColumn, int $entityId, int $userId): void
    {
        if (
            $entityId <= 0
            || $tableName === ''
            || $keyColumn === ''
            || !$this->tableExists($tableName)
            || !$this->tableColumnExists($tableName, $keyColumn)
        ) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE {$tableName}
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE {$keyColumn} = :entityId
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':entityId' => $entityId,
        ]);
    }

    private function deleteStandaloneDimensionRow(string $tableName, string $keyColumn, int $entityId): void
    {
        if (
            $entityId <= 0
            || $tableName === ''
            || $keyColumn === ''
            || !$this->tableExists($tableName)
            || !$this->tableColumnExists($tableName, $keyColumn)
        ) {
            return;
        }

        $stmt = $this->conn->prepare("DELETE FROM {$tableName} WHERE {$keyColumn} = :entityId");
        $stmt->execute([':entityId' => $entityId]);
    }

    private function deleteDimensionAttributeValuesForEntity(string $dimensionCode, int $entityId): void
    {
        $dimensionCode = strtoupper(trim($dimensionCode));
        if ($dimensionCode === '' || $entityId <= 0 || !$this->supportsStrategicDimensionAttributes()) {
            return;
        }

        $stmt = $this->conn->prepare("
            DELETE FROM dbo.tblSbDimensionAttributeValue
            WHERE StrategicDimensionCode = :dimensionCode
              AND EntityID = :entityId
        ");
        $stmt->execute([
            ':dimensionCode' => $dimensionCode,
            ':entityId' => $entityId,
        ]);
    }

    private function resetProjectImportsForFiscalYear(int $fiscalYearId, int $segmentNo, int $userId, string $mode): array
    {
        $summary = [
            'mode' => $mode,
            'source_maps_cleared' => 0,
            'projects_archived' => 0,
            'projects_deleted' => 0,
            'projects_preserved' => 0,
            'blocked' => 0,
        ];

        if ($fiscalYearId <= 0 || $segmentNo <= 0 || $mode === 'none' || !$this->tableExists('dbo.tblSbProject')) {
            return $summary;
        }

        foreach ($this->listProjectIdsForImportScope($fiscalYearId, $segmentNo) as $projectId) {
            if ($projectId <= 0) {
                continue;
            }

            $hasOtherMappings = $this->hasOtherActiveProjectSourceMaps($projectId, $fiscalYearId, $segmentNo);
            $project = $this->getProject($projectId);
            $ownsCurrentScope = $project !== null
                && (int) ($project['SourceFiscalYearID'] ?? 0) === $fiscalYearId
                && (int) ($project['SourceSegmentNo'] ?? 0) === $segmentNo;

            if ($this->supportsProjectSourceMaps()) {
                $cleared = $mode === 'hard'
                    ? $this->deleteProjectSourceMapsForFiscalYear($projectId, $fiscalYearId, $segmentNo)
                    : $this->deactivateProjectSourceMapsForFiscalYear($projectId, $fiscalYearId, $segmentNo, $userId);
                $summary['source_maps_cleared'] += $cleared;
            }

            if (!$ownsCurrentScope || $hasOtherMappings) {
                $summary['projects_preserved']++;
                continue;
            }

            $blockers = $this->getProjectImportResetUsageBlockers($projectId);
            if ($blockers !== []) {
                $summary['blocked']++;
                $summary['projects_preserved']++;
                continue;
            }

            if ($mode === 'hard') {
                $this->permanentlyDeleteProject($projectId);
                $summary['projects_deleted']++;
                continue;
            }

            $this->deactivateProjectMetadata($projectId, $userId);
            $this->deactivateProject($projectId, $userId);
            $summary['projects_archived']++;
        }

        return $summary;
    }

    private function listProjectIdsForImportScope(int $fiscalYearId, int $segmentNo): array
    {
        if ($this->supportsProjectSourceMaps()) {
            $stmt = $this->conn->prepare("
                SELECT DISTINCT ProjectID
                FROM dbo.tblSbProjectSourceMap
                WHERE FiscalYearID = :fy
                  AND SourceSegmentNo = :segmentNo
                  AND ActiveFlag = 1
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ]);
            return array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), static fn(int $id): bool => $id > 0));
        }

        $stmt = $this->conn->prepare("
            SELECT ProjectID
            FROM dbo.tblSbProject
            WHERE SourceFiscalYearID = :fy
              AND SourceSegmentNo = :segmentNo
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        return array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), static fn(int $id): bool => $id > 0));
    }

    private function hasOtherActiveProjectSourceMaps(int $projectId, int $fiscalYearId, int $segmentNo): bool
    {
        if ($projectId <= 0 || !$this->supportsProjectSourceMaps()) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblSbProjectSourceMap
            WHERE ProjectID = :projectId
              AND ActiveFlag = 1
              AND NOT (FiscalYearID = :fy AND SourceSegmentNo = :segmentNo)
        ");
        $stmt->execute([
            ':projectId' => $projectId,
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function deactivateProjectSourceMapsForFiscalYear(int $projectId, int $fiscalYearId, int $segmentNo, int $userId): int
    {
        if ($projectId <= 0 || !$this->supportsProjectSourceMaps()) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbProjectSourceMap
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ProjectID = :projectId
              AND FiscalYearID = :fy
              AND SourceSegmentNo = :segmentNo
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':projectId' => $projectId,
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        return $stmt->rowCount();
    }

    private function deleteProjectSourceMapsForFiscalYear(int $projectId, int $fiscalYearId, int $segmentNo): int
    {
        if ($projectId <= 0 || !$this->supportsProjectSourceMaps()) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            DELETE FROM dbo.tblSbProjectSourceMap
            WHERE ProjectID = :projectId
              AND FiscalYearID = :fy
              AND SourceSegmentNo = :segmentNo
        ");
        $stmt->execute([
            ':projectId' => $projectId,
            ':fy' => $fiscalYearId,
            ':segmentNo' => $segmentNo,
        ]);
        return $stmt->rowCount();
    }

    private function getProjectImportResetUsageBlockers(int $projectId): array
    {
        if ($projectId <= 0) {
            return [];
        }

        $summary = $this->getProjectUsageSummary($projectId);
        $candidates = [
            'funding submissions' => (int) ($summary['FundingSubmissionCount'] ?? 0),
            'activities' => (int) ($summary['ActivityCount'] ?? 0),
            'resource envelope targets' => (int) ($summary['ResourceEnvelopeCount'] ?? 0),
            'narratives' => (int) ($summary['NarrativeCount'] ?? 0),
            'fiscal risks' => (int) ($summary['FiscalRiskCount'] ?? 0),
        ];

        $blockers = [];
        foreach ($candidates as $label => $count) {
            if ($count > 0) {
                $blockers[] = $label . ' (' . $count . ')';
            }
        }

        return $blockers;
    }

    private function deactivateProjectMetadata(int $projectId, int $userId): void
    {
        if ($projectId <= 0) {
            return;
        }

        $this->deactivateProjectLinkTable('dbo.tblSbProjectProgramLink', 'ProjectID', $projectId, $userId);
        $this->deactivateProjectLinkTable('dbo.tblSbProjectObjectiveLink', 'ProjectID', $projectId, $userId);
        $this->deactivateProjectLinkTable('dbo.tblSbProjectOrgUnitLink', 'ProjectID', $projectId, $userId);
        $this->deleteProjectCustomAttributeValues($projectId);
    }

    private function permanentlyDeleteProject(int $projectId): void
    {
        if ($projectId <= 0 || !$this->tableExists('dbo.tblSbProject')) {
            return;
        }

        $this->deleteProjectLinkTable('dbo.tblSbProjectProgramLink', 'ProjectID', $projectId);
        $this->deleteProjectLinkTable('dbo.tblSbProjectObjectiveLink', 'ProjectID', $projectId);
        $this->deleteProjectLinkTable('dbo.tblSbProjectOrgUnitLink', 'ProjectID', $projectId);
        if ($this->supportsProjectSourceMaps()) {
            $this->deleteProjectLinkTable('dbo.tblSbProjectSourceMap', 'ProjectID', $projectId);
        }
        $this->deleteProjectCustomAttributeValues($projectId);

        $stmt = $this->conn->prepare("
            DELETE FROM dbo.tblSbProject
            WHERE ProjectID = :projectId
        ");
        $stmt->execute([':projectId' => $projectId]);
    }

    private function deactivateProjectLinkTable(string $tableName, string $keyColumn, int $projectId, int $userId): void
    {
        if ($projectId <= 0 || !$this->tableExists($tableName) || !$this->tableColumnExists($tableName, 'ActiveFlag')) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE {$tableName}
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE {$keyColumn} = :projectId
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':userId' => $userId,
            ':projectId' => $projectId,
        ]);
    }

    private function deleteProjectLinkTable(string $tableName, string $keyColumn, int $projectId): void
    {
        if ($projectId <= 0 || !$this->tableExists($tableName)) {
            return;
        }

        $stmt = $this->conn->prepare("
            DELETE FROM {$tableName}
            WHERE {$keyColumn} = :projectId
        ");
        $stmt->execute([':projectId' => $projectId]);
    }

    private function deleteProjectCustomAttributeValues(int $projectId): void
    {
        if ($projectId <= 0 || !$this->supportsStrategicDimensionAttributes()) {
            return;
        }

        $stmt = $this->conn->prepare("
            DELETE FROM dbo.tblSbDimensionAttributeValue
            WHERE StrategicDimensionCode = :dimensionCode
              AND EntityID = :entityId
        ");
        $stmt->execute([
            ':dimensionCode' => 'PROJECT',
            ':entityId' => $projectId,
        ]);
    }
}
