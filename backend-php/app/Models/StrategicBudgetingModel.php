<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class StrategicBudgetingModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getOverview(int $fiscalYearId, int $versionId): array
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM dbo.tblSbProgram WHERE ActiveFlag = 1) AS ProgramCount,
                (SELECT COUNT(*) FROM dbo.tblSbOutput WHERE ActiveFlag = 1) AS OutputCount,
                (SELECT COUNT(*) FROM dbo.tblSbActivity WHERE ActiveFlag = 1) AS ActivityCount,
                (SELECT COUNT(*) FROM dbo.tblSbIndicator WHERE ActiveFlag = 1) AS IndicatorCount,
                (SELECT COUNT(*) FROM dbo.tblSbObjective WHERE ActiveFlag = 1) AS ObjectiveCount,
                (SELECT COUNT(*) FROM dbo.tblSbSector WHERE ActiveFlag = 1) AS SectorCount,
                (SELECT COUNT(*) FROM dbo.tblSbOrgUnit WHERE ActiveFlag = 1) AS OrgUnitCount,
                COALESCE((
                    SELECT SUM(TotalAmount)
                    FROM dbo.vwSbBudgetBySector
                    WHERE FiscalYearID = :fy AND VersionID = :ver
                ), 0) AS TotalBudgetAmount
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSectorTotals(int $fiscalYearId, int $versionId, int $limit = 10): array
    {
        $sql = "
            SELECT TOP ($limit)
                SectorID,
                SectorName,
                ProgramCount,
                OutputCount,
                ActivityCount,
                TotalAmount
            FROM dbo.vwSbBudgetBySector
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
            ORDER BY TotalAmount DESC, SectorName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProgramTotals(int $fiscalYearId, int $versionId, int $limit = 10): array
    {
        $sql = "
            SELECT TOP ($limit)
                ProgramID,
                ProgramCode,
                ProgramName,
                SectorName,
                OrgUnitName,
                OutputCount,
                ActivityCount,
                TotalAmount
            FROM dbo.vwSbBudgetByProgram
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
            ORDER BY TotalAmount DESC, ProgramName ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listEconomicTotals(int $fiscalYearId, int $versionId, int $limit = 10): array
    {
        $sql = "
            SELECT TOP ($limit)
                EconomicItemID,
                EconomicCode,
                EconomicName,
                TotalAmount
            FROM dbo.vwSbBudgetByEconomicItem
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
            ORDER BY TotalAmount DESC, EconomicCode ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listRecentNarratives(int $fiscalYearId, int $versionId, int $limit = 8): array
    {
        $sql = "
            SELECT TOP ($limit)
                NarrativeID,
                SectionCode,
                NarrativeTitle,
                SortOrder,
                LockedFlag
            FROM dbo.tblSbNarrative
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND ActiveFlag = 1
            ORDER BY SectionCode ASC, SortOrder ASC, NarrativeID ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSectorBudgetReport(int $fiscalYearId, int $versionId, ?int $projectId = null): array
    {
        if ($projectId !== null && $projectId > 0) {
            $stmt = $this->conn->prepare("
                SELECT
                    d.SectorID,
                    d.SectorName,
                    COUNT(DISTINCT d.ProgramID) AS ProgramCount,
                    COUNT(DISTINCT d.OutputID) AS OutputCount,
                    COUNT(DISTINCT d.ActivityID) AS ActivityCount,
                    COUNT(DISTINCT d.EconomicItemID) AS EconomicLines,
                    COUNT(DISTINCT d.FundingSourceID) AS FundingSourceCount,
                    COALESCE(SUM(d.Amount), 0) AS TotalAmount
                FROM dbo.vwSbActivityBudgetDetail d
                WHERE d.FiscalYearID = :fy
                  AND d.VersionID = :ver
                  AND d.ProjectID = :projectId
                GROUP BY d.SectorID, d.SectorName
                ORDER BY TotalAmount DESC, d.SectorName ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':ver' => $versionId,
                ':projectId' => $projectId,
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                s.SectorID,
                s.SectorName,
                s.ProgramCount,
                s.OutputCount,
                s.ActivityCount,
                s.TotalAmount,
                COALESCE(e.EconomicLines, 0) AS EconomicLines,
                COALESCE(e.FundingSourceCount, 0) AS FundingSourceCount
            FROM dbo.vwSbBudgetBySector s
            LEFT JOIN (
                SELECT
                    d.SectorID,
                    COUNT(DISTINCT d.EconomicItemID) AS EconomicLines,
                    COUNT(DISTINCT d.FundingSourceID) AS FundingSourceCount
                FROM dbo.vwSbActivityBudgetDetail d
                WHERE d.FiscalYearID = :fyEconomic
                  AND d.VersionID = :verEconomic
                GROUP BY d.SectorID
            ) e
                ON e.SectorID = s.SectorID
            WHERE s.FiscalYearID = :fy
              AND s.VersionID = :ver
            ORDER BY s.TotalAmount DESC, s.SectorName ASC
        ");
        $stmt->execute([
            ':fyEconomic' => $fiscalYearId,
            ':verEconomic' => $versionId,
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProgramBudgetReport(int $fiscalYearId, int $versionId, ?int $projectId = null): array
    {
        if ($projectId !== null && $projectId > 0) {
            $stmt = $this->conn->prepare("
                SELECT
                    d.ProgramID,
                    MAX(d.ProgramCode) AS ProgramCode,
                    MAX(d.ProgramName) AS ProgramName,
                    MAX(d.SectorName) AS SectorName,
                    MAX(d.OrgUnitName) AS OrgUnitName,
                    COUNT(DISTINCT d.OutputID) AS OutputCount,
                    COUNT(DISTINCT d.ActivityID) AS ActivityCount,
                    COUNT(DISTINCT d.EconomicItemID) AS EconomicLines,
                    COUNT(DISTINCT d.FundingSourceID) AS FundingSourceCount,
                    COALESCE(SUM(d.Amount), 0) AS TotalAmount
                FROM dbo.vwSbActivityBudgetDetail d
                WHERE d.FiscalYearID = :fy
                  AND d.VersionID = :ver
                  AND d.ProjectID = :projectId
                GROUP BY d.ProgramID
                ORDER BY TotalAmount DESC, ProgramName ASC
            ");
            $stmt->execute([
                ':fy' => $fiscalYearId,
                ':ver' => $versionId,
                ':projectId' => $projectId,
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                p.ProgramID,
                p.ProgramCode,
                p.ProgramName,
                p.SectorName,
                p.OrgUnitName,
                p.OutputCount,
                p.ActivityCount,
                p.TotalAmount,
                COALESCE(x.EconomicLines, 0) AS EconomicLines,
                COALESCE(x.FundingSourceCount, 0) AS FundingSourceCount
            FROM dbo.vwSbBudgetByProgram p
            LEFT JOIN (
                SELECT
                    d.ProgramID,
                    COUNT(DISTINCT d.EconomicItemID) AS EconomicLines,
                    COUNT(DISTINCT d.FundingSourceID) AS FundingSourceCount
                FROM dbo.vwSbActivityBudgetDetail d
                WHERE d.FiscalYearID = :fyEconomic
                  AND d.VersionID = :verEconomic
                GROUP BY d.ProgramID
            ) x
                ON x.ProgramID = p.ProgramID
            WHERE p.FiscalYearID = :fy
              AND p.VersionID = :ver
            ORDER BY p.TotalAmount DESC, p.ProgramName ASC
        ");
        $stmt->execute([
            ':fyEconomic' => $fiscalYearId,
            ':verEconomic' => $versionId,
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getProjectBudgetReport(int $fiscalYearId, int $versionId, ?int $projectId = null): array
    {
        $sql = "
            SELECT
                p.ProjectID,
                p.ProjectCode,
                p.ProjectName,
                p.ProgramCount,
                p.OutputCount,
                p.ActivityCount,
                p.EconomicLines,
                p.FundingSourceCount,
                p.TotalAmount
            FROM dbo.vwSbBudgetByProject p
            WHERE p.FiscalYearID = :fy
              AND p.VersionID = :ver
        ";
        $params = [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ];
        if ($projectId !== null && $projectId > 0) {
            $sql .= ' AND p.ProjectID = :projectId';
            $params[':projectId'] = $projectId;
        }
        $sql .= ' ORDER BY p.TotalAmount DESC, p.ProjectName ASC';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMtffWindowYears(int $fiscalYearId, int $window = 3): array
    {
        if ($fiscalYearId <= 0) {
            return [];
        }

        $window = max(1, $window);
        $stmt = $this->conn->prepare("
            WITH anchor AS (
                SELECT StartDate
                FROM dbo.tblFiscalYears
                WHERE FiscalYearID = :fyAnchor
            )
            SELECT TOP ($window)
                fy.FiscalYearID,
                fy.YearLabel,
                fy.StartDate,
                fy.EndDate
            FROM dbo.tblFiscalYears fy
            CROSS JOIN anchor a
            WHERE fy.StartDate >= a.StartDate
            ORDER BY fy.StartDate ASC, fy.FiscalYearID ASC
        ");
        $stmt->execute([':fyAnchor' => $fiscalYearId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMtffSummary(int $fiscalYearId, int $versionId, int $window = 3, ?int $projectId = null): array
    {
        $years = $this->getMtffWindowYears($fiscalYearId, $window);
        if ($years === []) {
            return [];
        }

        $versionLabel = $this->getVersionLabel($versionId);
        if ($versionLabel === '') {
            return [];
        }

        $fyIds = array_map(static fn(array $row): int => (int) ($row['FiscalYearID'] ?? 0), $years);
        $inList = implode(',', array_map('intval', $fyIds));

        $projectJoinFilter = $projectId !== null && $projectId > 0 ? ' AND d.ProjectID = :projectId' : '';
        $stmt = $this->conn->prepare("
            SELECT
                fy.FiscalYearID,
                fy.YearLabel,
                v.VersionID,
                v.VersionLabel,
                COALESCE(SUM(d.Amount), 0) AS TotalAmount,
                COUNT(DISTINCT d.ActivityID) AS ActivityCount,
                COUNT(DISTINCT d.ProgramID) AS ProgramCount
            FROM dbo.tblFiscalYears fy
            LEFT JOIN dbo.tblVersions v
                ON v.FiscalYearID = fy.FiscalYearID
               AND v.VersionLabel = :verLabel
            LEFT JOIN dbo.vwSbActivityBudgetDetail d
                ON d.FiscalYearID = fy.FiscalYearID
               AND d.VersionID = v.VersionID" . $projectJoinFilter . "
            WHERE fy.FiscalYearID IN ($inList)
            GROUP BY fy.FiscalYearID, fy.YearLabel, v.VersionID, v.VersionLabel, fy.StartDate
            ORDER BY fy.StartDate ASC, fy.FiscalYearID ASC
        ");
        $params = [
            ':verLabel' => $versionLabel,
        ];
        if ($projectId !== null && $projectId > 0) {
            $params[':projectId'] = $projectId;
        }
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMtffSectorMatrix(int $fiscalYearId, int $versionId, int $window = 3, ?int $projectId = null): array
    {
        $years = $this->getMtffWindowYears($fiscalYearId, $window);
        if ($years === []) {
            return ['years' => [], 'rows' => []];
        }

        $versionLabel = $this->getVersionLabel($versionId);
        if ($versionLabel === '') {
            return ['years' => $years, 'rows' => []];
        }

        $fyIds = array_map(static fn(array $row): int => (int) ($row['FiscalYearID'] ?? 0), $years);
        $inList = implode(',', array_map('intval', $fyIds));

        $projectJoinFilter = $projectId !== null && $projectId > 0 ? ' AND d.ProjectID = :projectId' : '';
        $stmt = $this->conn->prepare("
            SELECT
                fy.FiscalYearID,
                fy.YearLabel,
                d.SectorID,
                d.SectorName,
                COALESCE(SUM(d.Amount), 0) AS TotalAmount
            FROM dbo.tblFiscalYears fy
            INNER JOIN dbo.tblVersions v
                ON v.FiscalYearID = fy.FiscalYearID
               AND v.VersionLabel = :verLabel
            INNER JOIN dbo.vwSbActivityBudgetDetail d
                ON d.FiscalYearID = fy.FiscalYearID
               AND d.VersionID = v.VersionID" . $projectJoinFilter . "
            WHERE fy.FiscalYearID IN ($inList)
            GROUP BY fy.FiscalYearID, fy.YearLabel, fy.StartDate, d.SectorID, d.SectorName
            ORDER BY d.SectorName ASC, fy.StartDate ASC
        ");
        $params = [
            ':verLabel' => $versionLabel,
        ];
        if ($projectId !== null && $projectId > 0) {
            $params[':projectId'] = $projectId;
        }
        $stmt->execute($params);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rowsBySector = [];
        foreach ($raw as $row) {
            $sectorId = (int) ($row['SectorID'] ?? 0);
            $yearId = (int) ($row['FiscalYearID'] ?? 0);
            if (!isset($rowsBySector[$sectorId])) {
                $rowsBySector[$sectorId] = [
                    'SectorID' => $sectorId,
                    'SectorName' => (string) ($row['SectorName'] ?? ''),
                    'Amounts' => [],
                    'TotalAmount' => 0.0,
                ];
            }
            $amount = (float) ($row['TotalAmount'] ?? 0);
            $rowsBySector[$sectorId]['Amounts'][$yearId] = $amount;
            $rowsBySector[$sectorId]['TotalAmount'] += $amount;
        }

        foreach ($rowsBySector as &$sectorRow) {
            foreach ($years as $year) {
                $yearId = (int) ($year['FiscalYearID'] ?? 0);
                if (!array_key_exists($yearId, $sectorRow['Amounts'])) {
                    $sectorRow['Amounts'][$yearId] = 0.0;
                }
            }
        }
        unset($sectorRow);

        uasort($rowsBySector, static function (array $left, array $right): int {
            $amountCompare = ($right['TotalAmount'] <=> $left['TotalAmount']);
            if ($amountCompare !== 0) {
                return $amountCompare;
            }
            return strcmp((string) $left['SectorName'], (string) $right['SectorName']);
        });

        return [
            'years' => $years,
            'rows' => array_values($rowsBySector),
        ];
    }

    public function getPerformanceFrameworkReport(int $fiscalYearId, int $versionId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                p.ProgramID,
                p.ProgramCode,
                p.ProgramName,
                sp.SubProgramID,
                sp.SubProgramCode,
                sp.SubProgramName,
                o.ObjectiveID,
                o.ObjectiveText,
                o.PolicyLink,
                o.PriorityRank,
                i.IndicatorID,
                i.IndicatorTypeCode,
                i.IndicatorName,
                i.UnitOfMeasure,
                i.DataSource,
                i.FrequencyCode,
                t.BaselineValue,
                t.TargetValue,
                t.Notes AS TargetNotes
            FROM dbo.tblSbObjective o
            INNER JOIN dbo.tblSbProgram p
                ON p.ProgramID = o.ProgramID
               AND p.ActiveFlag = 1
            LEFT JOIN dbo.tblSbSubProgram sp
                ON sp.SubProgramID = o.SubProgramID
               AND sp.ActiveFlag = 1
            LEFT JOIN dbo.tblSbObjectiveIndicator oi
                ON oi.ObjectiveID = o.ObjectiveID
            LEFT JOIN dbo.tblSbIndicator i
                ON i.IndicatorID = oi.IndicatorID
               AND i.ActiveFlag = 1
            LEFT JOIN dbo.tblSbIndicatorTarget t
                ON t.IndicatorID = i.IndicatorID
               AND t.FiscalYearID = :fyTarget
               AND t.VersionID = :verTarget
               AND t.ActiveFlag = 1
            WHERE o.ActiveFlag = 1
            ORDER BY p.ProgramName ASC, sp.SubProgramName ASC, o.PriorityRank ASC, o.ObjectiveID ASC, i.IndicatorName ASC
        ");
        $stmt->execute([
            ':fyTarget' => $fiscalYearId,
            ':verTarget' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getFiscalFrameworkOverview(int $fiscalYearId, int $versionId): array
    {
        $ceilingSummary = $this->getApprovedCeilingSummary($fiscalYearId, $versionId);
        $sectorRows = $this->getSectorCeilingComparison($fiscalYearId, $versionId);
        $programRows = $this->getProgramCeilingComparison($fiscalYearId, $versionId);
        $topDataObjects = array_slice($this->getResourceEnvelopeByDataObject($fiscalYearId, $versionId), 0, 8);

        $plannedAmount = (float) ($this->getOverview($fiscalYearId, $versionId)['TotalBudgetAmount'] ?? 0);
        $ceilingAmount = (float) ($ceilingSummary['CeilingBPTotal'] ?? 0);

        $sectorOverruns = 0;
        foreach ($sectorRows as $row) {
            if ((int) ($row['OverCeilingFlag'] ?? 0) === 1) {
                $sectorOverruns++;
            }
        }

        $programOverruns = 0;
        foreach ($programRows as $row) {
            if ((int) ($row['OverCeilingFlag'] ?? 0) === 1) {
                $programOverruns++;
            }
        }

        return [
            'cards' => [
                'ApprovedCeilingTotal' => $ceilingAmount,
                'StrategicPlanTotal' => $plannedAmount,
                'HeadroomAmount' => $ceilingAmount - $plannedAmount,
                'ApprovedCeilingLines' => (int) ($ceilingSummary['CeilingLineCount'] ?? 0),
                'ScopedDataObjects' => (int) ($ceilingSummary['DataObjectCount'] ?? 0),
                'SectorOverruns' => $sectorOverruns,
                'ProgramOverruns' => $programOverruns,
            ],
            'monthly' => $ceilingSummary,
            'top_data_objects' => $topDataObjects,
            'sector_rows' => array_slice($sectorRows, 0, 8),
            'program_rows' => array_slice($programRows, 0, 8),
        ];
    }

    public function getResourceEnvelopeReport(int $fiscalYearId, int $versionId): array
    {
        return [
            'summary' => $this->getApprovedCeilingSummary($fiscalYearId, $versionId),
            'data_object_rows' => $this->getResourceEnvelopeByDataObject($fiscalYearId, $versionId),
        ];
    }

    public function getSectorCeilingReport(int $fiscalYearId, int $versionId): array
    {
        $rows = $this->getSectorCeilingComparison($fiscalYearId, $versionId);
        $overruns = 0;
        foreach ($rows as $row) {
            if ((int) ($row['OverCeilingFlag'] ?? 0) === 1) {
                $overruns++;
            }
        }

        return [
            'rows' => $rows,
            'summary' => [
                'SectorCount' => count($rows),
                'OverrunCount' => $overruns,
            ],
        ];
    }

    public function getCeilingVsPlanReport(int $fiscalYearId, int $versionId): array
    {
        return [
            'sector_rows' => $this->getSectorCeilingComparison($fiscalYearId, $versionId),
            'program_rows' => $this->getProgramCeilingComparison($fiscalYearId, $versionId),
        ];
    }

    public function getSubmissionReadinessDashboard(int $fiscalYearId, int $versionId): array
    {
        $checks = [];

        $configuredMappings = $this->getConfiguredStrategicDimensionCodes($fiscalYearId);
        $configuredMappingCount = count($configuredMappings);
        $checks[] = [
            'category' => 'Foundation',
            'title' => 'Mapped for source-backed import',
            'total_count' => $configuredMappingCount,
            'issue_count' => 0,
            'status' => $configuredMappingCount > 0 ? 'ready' : 'info',
            'message' => $configuredMappingCount > 0
                ? $configuredMappingCount . ' strategic dimension mapping(s) are configured for source-backed import in this fiscal year.'
                : 'No strategic dimensions are currently mapped for source-backed import. This is fine until you choose to import from CBMS segments.',
            'action_route' => 'index.php?route=strategy-config/segment-mapping',
            'action_label' => 'Manage Mapping',
        ];

        $programTotal = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbProgram
            WHERE ActiveFlag = 1
        ");
        $programsWithoutSector = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbProgram p
            LEFT JOIN dbo.tblSbSector s
                ON s.SectorID = p.SectorID
               AND s.ActiveFlag = 1
            WHERE p.ActiveFlag = 1
              AND s.SectorID IS NULL
        ");
        $checks[] = $this->buildReadinessCheck(
            'Foundation',
            'Programs linked to active sectors',
            $programTotal,
            $programsWithoutSector,
            'All active programs are linked to active sectors.',
            $programsWithoutSector . ' active program(s) need a valid sector assignment.',
            'index.php?route=strategy-setup/programs',
            'Manage Programs'
        );

        $programsWithoutObjectives = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbProgram p
            WHERE p.ActiveFlag = 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSbObjective o
                    WHERE o.ProgramID = p.ProgramID
                      AND o.ActiveFlag = 1
                )
        ");
        $checks[] = $this->buildReadinessCheck(
            'Performance',
            'Programs with objectives',
            $programTotal,
            $programsWithoutObjectives,
            'Every active program has at least one objective.',
            $programsWithoutObjectives . ' active program(s) still need an objective.',
            'index.php?route=strategy-performance/objectives',
            'Manage Objectives'
        );

        $objectiveTotal = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbObjective
            WHERE ActiveFlag = 1
        ");
        $objectivesWithoutIndicators = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbObjective o
            WHERE o.ActiveFlag = 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSbObjectiveIndicator oi
                    INNER JOIN dbo.tblSbIndicator i
                        ON i.IndicatorID = oi.IndicatorID
                       AND i.ActiveFlag = 1
                    WHERE oi.ObjectiveID = o.ObjectiveID
                )
        ");
        $checks[] = $this->buildReadinessCheck(
            'Performance',
            'Objectives with indicators',
            $objectiveTotal,
            $objectivesWithoutIndicators,
            'Every active objective is linked to at least one active indicator.',
            $objectivesWithoutIndicators . ' active objective(s) still need an indicator link.',
            'index.php?route=strategy-performance/objectives',
            'Review Objectives'
        );

        $targetedIndicatorTotal = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbIndicator i
            WHERE i.ActiveFlag = 1
              AND (
                    EXISTS (
                        SELECT 1
                        FROM dbo.tblSbObjectiveIndicator oi
                        WHERE oi.IndicatorID = i.IndicatorID
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM dbo.tblSbOutputIndicator oi2
                        WHERE oi2.IndicatorID = i.IndicatorID
                    )
                )
        ");
        $indicatorsWithoutTargets = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbIndicator i
            WHERE i.ActiveFlag = 1
              AND (
                    EXISTS (
                        SELECT 1
                        FROM dbo.tblSbObjectiveIndicator oi
                        WHERE oi.IndicatorID = i.IndicatorID
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM dbo.tblSbOutputIndicator oi2
                        WHERE oi2.IndicatorID = i.IndicatorID
                    )
                )
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSbIndicatorTarget t
                    WHERE t.IndicatorID = i.IndicatorID
                      AND t.FiscalYearID = :fyTargetMissing
                      AND t.VersionID = :verTargetMissing
                      AND t.ActiveFlag = 1
                )
        ", [
            ':fyTargetMissing' => $fiscalYearId,
            ':verTargetMissing' => $versionId,
        ]);
        $checks[] = $this->buildReadinessCheck(
            'Performance',
            'Indicators with targets in active context',
            $targetedIndicatorTotal,
            $indicatorsWithoutTargets,
            'All linked indicators have targets for the active fiscal context.',
            $indicatorsWithoutTargets . ' linked indicator(s) still need a target for this fiscal year/version.',
            'index.php?route=strategy-performance/targets',
            'Manage Targets'
        );

        $outputTotal = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbOutput
            WHERE ActiveFlag = 1
        ");
        $outputsWithoutActivities = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbOutput o
            WHERE o.ActiveFlag = 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSbActivity a
                    WHERE a.OutputID = o.OutputID
                      AND a.ActiveFlag = 1
                )
        ");
        $checks[] = $this->buildReadinessCheck(
            'Delivery',
            'Outputs with activities',
            $outputTotal,
            $outputsWithoutActivities,
            'Every active output has at least one active activity.',
            $outputsWithoutActivities . ' active output(s) still need an activity.',
            'index.php?route=strategy-delivery/activities',
            'Manage Activities'
        );

        $activityTotal = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbActivity
            WHERE ActiveFlag = 1
        ");
        $activitiesWithoutBudgets = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbActivity a
            WHERE a.ActiveFlag = 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSbActivityBudget ab
                    WHERE ab.ActivityID = a.ActivityID
                      AND ab.FiscalYearID = :fyBudgetMissing
                      AND ab.VersionID = :verBudgetMissing
                      AND ab.ActiveFlag = 1
                )
        ", [
            ':fyBudgetMissing' => $fiscalYearId,
            ':verBudgetMissing' => $versionId,
        ]);
        $checks[] = $this->buildReadinessCheck(
            'Delivery',
            'Activities with budgets in active context',
            $activityTotal,
            $activitiesWithoutBudgets,
            'All active activities have at least one budget line in the active fiscal context.',
            $activitiesWithoutBudgets . ' active activity(s) still need a budget line for this fiscal year/version.',
            'index.php?route=strategy-delivery/budgets',
            'Manage Budgets'
        );

        $requiredSections = ['MACRO', 'REVENUE', 'EXPENDITURE', 'PRIORITIES', 'RISKS', 'MTFF'];
        $presentSections = $this->getNarrativeSectionsPresent($fiscalYearId, $versionId);
        $missingSections = array_values(array_diff($requiredSections, $presentSections));
        $checks[] = $this->buildReadinessCheck(
            'Governance',
            'BSP narrative sections',
            count($requiredSections),
            count($missingSections),
            'All core BSP narrative sections have at least one active paragraph.',
            count($missingSections) > 0
                ? 'Missing sections: ' . implode(', ', $missingSections) . '.'
                : '',
            'index.php?route=strategy-governance/narratives',
            'Manage Narratives'
        );

        $fiscalRiskTotal = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbFiscalRisk
            WHERE ActiveFlag = 1
        ");
        $fiscalRisksWithoutPrograms = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSbFiscalRisk fr
            WHERE fr.ActiveFlag = 1
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSbProgramRisk pr
                    WHERE pr.FiscalRiskID = fr.FiscalRiskID
                )
        ");
        $checks[] = $this->buildReadinessCheck(
            'Governance',
            'Fiscal risks linked to programs',
            $fiscalRiskTotal,
            $fiscalRisksWithoutPrograms,
            'Every active fiscal risk is linked to at least one program.',
            $fiscalRisksWithoutPrograms . ' active fiscal risk(s) are not yet linked to any program.',
            'index.php?route=strategy-governance/program-risks',
            'Manage Program Risks'
        );

        $summary = [
            'total_checks' => count($checks),
            'ready_checks' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'ready')),
            'warning_checks' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'warning')),
            'info_checks' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'info')),
            'open_items' => array_sum(array_map(static fn(array $check): int => (int) $check['issue_count'], $checks)),
        ];

        return [
            'summary' => $summary,
            'checks' => $checks,
        ];
    }

    public function getReadinessDashboard(int $fiscalYearId, int $versionId): array
    {
        return $this->getSubmissionReadinessDashboard($fiscalYearId, $versionId);
    }

    public function getConfigurationReadinessDashboard(int $fiscalYearId): array
    {
        $checks = [];

        $configuredMappings = $this->getConfiguredStrategicDimensionCodes($fiscalYearId);
        $configuredMappingCount = count($configuredMappings);
        $checks[] = [
            'category' => 'Mappings',
            'title' => 'Mapped for source-backed import',
            'total_count' => $configuredMappingCount,
            'issue_count' => 0,
            'status' => $configuredMappingCount > 0 ? 'ready' : 'info',
            'message' => $configuredMappingCount > 0
                ? $configuredMappingCount . ' strategic dimension mapping(s) are configured for source-backed import in this fiscal year.'
                : 'No strategic dimensions are currently mapped for source-backed import. Add mappings only for dimensions you want to source from CBMS segments.',
            'action_route' => 'index.php?route=strategy-config/segment-mapping',
            'action_label' => 'Manage Mapping',
        ];

        $allDimensions = $this->getAllStrategicDimensionCodes();
        $decidedDimensions = $this->getDecidedStrategicDimensionCodes($fiscalYearId);
        $undecidedDimensions = array_values(array_diff($allDimensions, $decidedDimensions));
        $checks[] = $this->buildReadinessCheck(
            'Mappings',
            'Mapping decisions completed',
            count($allDimensions),
            count($undecidedDimensions),
            'Every strategic dimension has an explicit mapping decision.',
            count($undecidedDimensions) > 0
                ? 'No explicit decision has been recorded for: ' . implode(', ', $undecidedDimensions) . '.'
                : '',
            'index.php?route=strategy-config/segment-mapping',
            'Review Decisions'
        );

        $sectorSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'SECTOR');
        $programSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'PROGRAM');
        $subProgramSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'SUBPROGRAM');
        $projectSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'PROJECT');
        $economicSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'ECONOMIC');
        $fundingTypeSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'FUNDING_TYPE');
        $fundingSourceSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'FUNDING_SOURCE');
        $objectiveSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'OBJECTIVE');
        $indicatorSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'INDICATOR');
        $targetSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'TARGET');
        $outputSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'OUTPUT');
        $activitySegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'ACTIVITY');

        if ($sectorSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Hierarchy',
                'Sector records created in Strategy',
                'SECTOR is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
            $sectorSourceCount = 0;
        } else {
            $sectorSourceCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM (
                    SELECT DISTINCT SegmentCode
                    FROM dbo.tblSegmentValues
                    WHERE FiscalYearID = :fySectorSource
                      AND SegmentNo = :sectorSegmentNo
                      AND ActiveFlag = 1
                ) src
            ", [
                ':fySectorSource' => $fiscalYearId,
                ':sectorSegmentNo' => $sectorSegmentNo,
            ]);
            $sectorOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbSector');
            $missingSectorOverlays = max(0, $sectorSourceCount - $sectorOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Hierarchy',
                'Sector records created in Strategy',
                $sectorSourceCount,
                $missingSectorOverlays,
                'Sector records have been created in Strategy for the mapped source values.',
                $missingSectorOverlays . ' sector source value(s) have not yet been created as active sector records in Strategy.',
                'index.php?route=strategy-setup/sectors',
                'Manage Sectors'
            );
        }

        if ($programSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Hierarchy',
                'Program records created in Strategy',
                'PROGRAM is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
            $programSourceCount = 0;
        } else {
            $programSourceCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM (
                    SELECT DISTINCT SourceDataObjectCode, SegmentCode
                    FROM (
                        SELECT
                            COALESCE(NULLIF(ParentScope.SourceDataObjectCode, ''), sv.DataObjectCode) AS SourceDataObjectCode,
                            sv.SegmentCode
                        FROM dbo.tblSegmentValues sv
                        OUTER APPLY (
                            SELECT TOP 1 psv.DataObjectCode AS SourceDataObjectCode
                            FROM dbo.tblSegmentValues psv
                            WHERE psv.FiscalYearID = sv.FiscalYearID
                              AND psv.SegmentNo = sv.ParentSegmentNo
                              AND psv.SegmentCode = sv.ParentSegmentCode
                              AND psv.ActiveFlag = 1
                        ) ParentScope
                        WHERE sv.FiscalYearID = :fyProgramSource
                          AND sv.SegmentNo = :programSegmentNo
                          AND sv.ActiveFlag = 1
                    ) scoped
                ) src
            ", [
                ':fyProgramSource' => $fiscalYearId,
                ':programSegmentNo' => $programSegmentNo,
            ]);
            $programOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbProgram');
            $missingProgramOverlays = max(0, $programSourceCount - $programOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Hierarchy',
                'Program records created in Strategy',
                $programSourceCount,
                $missingProgramOverlays,
                'Program records have been created in Strategy for the mapped source values.',
                $missingProgramOverlays . ' program source value(s) have not yet been created as active program records in Strategy.',
                'index.php?route=strategy-setup/programs',
                'Manage Programs'
            );
        }

        if ($subProgramSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Hierarchy',
                'SubProgram records created in Strategy',
                'SUBPROGRAM is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
            $subProgramSourceCount = 0;
        } else {
            $subProgramSourceCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM (
                    SELECT DISTINCT sv.DataObjectCode, sv.SegmentCode
                    FROM dbo.tblSegmentValues sv
                    WHERE sv.FiscalYearID = :fySubProgramSource
                      AND sv.SegmentNo = :subProgramSegmentNo
                      AND sv.ActiveFlag = 1
                ) src
            ", [
                ':fySubProgramSource' => $fiscalYearId,
                ':subProgramSegmentNo' => $subProgramSegmentNo,
            ]);
            $subProgramOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbSubProgram');
            $missingSubProgramOverlays = max(0, $subProgramSourceCount - $subProgramOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Hierarchy',
                'SubProgram records created in Strategy',
                $subProgramSourceCount,
                $missingSubProgramOverlays,
                'SubProgram records have been created in Strategy for the mapped source values.',
                $missingSubProgramOverlays . ' subprogram source value(s) have not yet been created as active subprogram records in Strategy.',
                'index.php?route=strategy-setup/sub-programs',
                'Manage SubPrograms'
            );
        }

        if ($projectSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Hierarchy',
                'Project records created in Strategy',
                'PROJECT is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $projectSourceCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM (
                    SELECT DISTINCT sv.DataObjectCode, sv.SegmentCode
                    FROM dbo.tblSegmentValues sv
                    WHERE sv.FiscalYearID = :fyProjectSource
                      AND sv.SegmentNo = :projectSegmentNo
                      AND sv.ActiveFlag = 1
                ) src
            ", [
                ':fyProjectSource' => $fiscalYearId,
                ':projectSegmentNo' => $projectSegmentNo,
            ]);
            $projectOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbProject');
            $missingProjectOverlays = max(0, $projectSourceCount - $projectOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Hierarchy',
                'Project records created in Strategy',
                $projectSourceCount,
                $missingProjectOverlays,
                'Project records have been created in Strategy for the mapped source values.',
                $missingProjectOverlays . ' project source value(s) have not yet been created as active project records in Strategy.',
                'index.php?route=strategy-setup/projects',
                'Manage Projects'
            );
        }

        if ($fundingTypeSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Reference Data',
                'Funding Type records created in Strategy',
                'FUNDING_TYPE is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $fundingTypeSourceCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM (
                    SELECT DISTINCT SegmentCode
                    FROM dbo.tblSegmentValues
                    WHERE FiscalYearID = :fyFundingTypeSource
                      AND SegmentNo = :fundingTypeSegmentNo
                      AND ActiveFlag = 1
                ) src
            ", [
                ':fyFundingTypeSource' => $fiscalYearId,
                ':fundingTypeSegmentNo' => $fundingTypeSegmentNo,
            ]);
            $fundingTypeOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbFundingType');
            $missingFundingTypeOverlays = max(0, $fundingTypeSourceCount - $fundingTypeOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Reference Data',
                'Funding Type records created in Strategy',
                $fundingTypeSourceCount,
                $missingFundingTypeOverlays,
                'Funding type records have been created in Strategy for the mapped source values.',
                $missingFundingTypeOverlays . ' funding type source value(s) have not yet been created as active funding type records in Strategy.',
                'index.php?route=strategy-setup/funding-types',
                'Manage Funding Types'
            );
        }

        if ($fundingSourceSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Reference Data',
                'Funding Source records created in Strategy',
                'FUNDING_SOURCE is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $fundingSourceSourceCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM (
                    SELECT DISTINCT SegmentCode
                    FROM dbo.tblSegmentValues
                    WHERE FiscalYearID = :fyFundingSourceSource
                      AND SegmentNo = :fundingSourceSegmentNo
                      AND ActiveFlag = 1
                ) src
            ", [
                ':fyFundingSourceSource' => $fiscalYearId,
                ':fundingSourceSegmentNo' => $fundingSourceSegmentNo,
            ]);
            $fundingSourceOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbFundingSource');
            $missingFundingSourceOverlays = max(0, $fundingSourceSourceCount - $fundingSourceOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Reference Data',
                'Funding Source records created in Strategy',
                $fundingSourceSourceCount,
                $missingFundingSourceOverlays,
                'Funding source records have been created in Strategy for the mapped source values.',
                $missingFundingSourceOverlays . ' funding source value(s) have not yet been created as active funding source records in Strategy.',
                'index.php?route=strategy-setup/funding-sources',
                'Manage Funding Sources'
            );
        }

        if ($economicSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Reference Data',
                'Economic Item records created in Strategy',
                'ECONOMIC is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $economicSourceCount = $this->countDistinctSegmentSourceValues($fiscalYearId, $economicSegmentNo);
            $economicOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbEconomicItem');
            $missingEconomicOverlays = max(0, $economicSourceCount - $economicOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Reference Data',
                'Economic Item records created in Strategy',
                $economicSourceCount,
                $missingEconomicOverlays,
                'Economic item records have been created in Strategy for the mapped source values.',
                $missingEconomicOverlays . ' economic source value(s) have not yet been created as active economic item records in Strategy.',
                'index.php?route=strategy-setup/economic-items',
                'Manage Economic Items'
            );
        }

        if ($objectiveSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Planning',
                'Objective records created in Strategy',
                'OBJECTIVE is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $objectiveSourceCount = $this->countDistinctSegmentSourceValues($fiscalYearId, $objectiveSegmentNo);
            $objectiveOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbObjective');
            $missingObjectiveOverlays = max(0, $objectiveSourceCount - $objectiveOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Planning',
                'Objective records created in Strategy',
                $objectiveSourceCount,
                $missingObjectiveOverlays,
                'Objective records have been created in Strategy for the mapped source values.',
                $missingObjectiveOverlays . ' objective source value(s) have not yet been created as active objective records in Strategy.',
                'index.php?route=strategy-performance/objectives',
                'Manage Objectives'
            );
        }

        if ($outputSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Delivery',
                'Output records created in Strategy',
                'OUTPUT is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $outputSourceCount = $this->countDistinctSegmentSourceValues($fiscalYearId, $outputSegmentNo);
            $outputOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbOutput');
            $missingOutputOverlays = max(0, $outputSourceCount - $outputOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Delivery',
                'Output records created in Strategy',
                $outputSourceCount,
                $missingOutputOverlays,
                'Output records have been created in Strategy for the mapped source values.',
                $missingOutputOverlays . ' output source value(s) have not yet been created as active output records in Strategy.',
                'index.php?route=strategy-delivery/outputs',
                'Manage Outputs'
            );
        }

        if ($activitySegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Delivery',
                'Activity records created in Strategy',
                'ACTIVITY is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $activitySourceCount = $this->countDistinctSegmentSourceValues($fiscalYearId, $activitySegmentNo);
            $activityOverlayCount = $this->countActiveRowsIfTableExists('dbo.tblSbActivity');
            $missingActivityOverlays = max(0, $activitySourceCount - $activityOverlayCount);
            $checks[] = $this->buildReadinessCheck(
                'Delivery',
                'Activity records created in Strategy',
                $activitySourceCount,
                $missingActivityOverlays,
                'Activity records have been created in Strategy for the mapped source values.',
                $missingActivityOverlays . ' activity source value(s) have not yet been created as active activity records in Strategy.',
                'index.php?route=strategy-delivery/activities',
                'Manage Activities'
            );
        }

        if ($indicatorSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Planning',
                'Indicator source mapping review',
                'INDICATOR is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $indicatorSourceCount = $this->countDistinctSegmentSourceValues($fiscalYearId, $indicatorSegmentNo);
            $checks[] = [
                'category' => 'Planning',
                'title' => 'Indicator source mapping review',
                'total_count' => $indicatorSourceCount,
                'issue_count' => $indicatorSourceCount > 0 ? $indicatorSourceCount : 0,
                'status' => $indicatorSourceCount > 0 ? 'warning' : 'info',
                'message' => $indicatorSourceCount > 0
                    ? 'INDICATOR is mapped and has source rows, but indicator import is not implemented yet. Maintain indicators directly in the strategic module.'
                    : 'INDICATOR is mapped, but no active source values are currently in scope.',
                'action_route' => 'index.php?route=strategy-performance/indicators',
                'action_label' => 'Manage Indicators',
            ];
        }

        if ($targetSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Planning',
                'Target source mapping review',
                'TARGET is not mapped for source-backed import in this fiscal year.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $targetSourceCount = $this->countDistinctSegmentSourceValues($fiscalYearId, $targetSegmentNo);
            $checks[] = [
                'category' => 'Planning',
                'title' => 'Target source mapping review',
                'total_count' => $targetSourceCount,
                'issue_count' => $targetSourceCount > 0 ? $targetSourceCount : 0,
                'status' => $targetSourceCount > 0 ? 'warning' : 'info',
                'message' => $targetSourceCount > 0
                    ? 'TARGET is mapped and has source rows, but target import is not supported from tblSegmentValues because numeric target values are required.'
                    : 'TARGET is mapped, but no active source values are currently in scope.',
                'action_route' => 'index.php?route=strategy-performance/targets',
                'action_label' => 'Manage Targets',
            ];
        }

        if ($programSegmentNo <= 0 || $sectorSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Hierarchy',
                'Program to sector parent links',
                'This check only applies when both PROGRAM and SECTOR are mapped for source-backed import.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $programRowsWithMissingSectorParent = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fyProgramParent
                  AND sv.SegmentNo = :programSegmentNoParent
                  AND sv.ActiveFlag = 1
                  AND (
                        sv.ParentSegmentNo IS NULL
                        OR sv.ParentSegmentNo <> :sectorSegmentNoParent
                        OR NULLIF(LTRIM(RTRIM(sv.ParentSegmentCode)), '') IS NULL
                  )
            ", [
                ':fyProgramParent' => $fiscalYearId,
                ':programSegmentNoParent' => $programSegmentNo,
                ':sectorSegmentNoParent' => $sectorSegmentNo,
            ]);
            $checks[] = $this->buildReadinessCheck(
                'Hierarchy',
                'Program to sector parent links',
                $programSourceCount,
                $programRowsWithMissingSectorParent,
                'Every mapped program source row has a sector parent link.',
                $programRowsWithMissingSectorParent . ' program source row(s) are missing a valid sector parent link.',
                'index.php?route=segment-values/list',
                'Review Segment Values'
            );
        }

        if ($subProgramSegmentNo <= 0 || $programSegmentNo <= 0) {
            $checks[] = $this->buildOutOfScopeReadinessCheck(
                'Hierarchy',
                'SubProgram to program parent links',
                'This check only applies when both SUBPROGRAM and PROGRAM are mapped for source-backed import.',
                'index.php?route=strategy-config/segment-mapping',
                'Manage Mapping'
            );
        } else {
            $subProgramRowsWithMissingProgramParent = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fySubProgramParent
                  AND sv.SegmentNo = :subProgramSegmentNoParent
                  AND sv.ActiveFlag = 1
                  AND (
                        sv.ParentSegmentNo IS NULL
                        OR sv.ParentSegmentNo <> :programSegmentNoParent
                        OR NULLIF(LTRIM(RTRIM(sv.ParentSegmentCode)), '') IS NULL
                  )
            ", [
                ':fySubProgramParent' => $fiscalYearId,
                ':subProgramSegmentNoParent' => $subProgramSegmentNo,
                ':programSegmentNoParent' => $programSegmentNo,
            ]);
            $checks[] = $this->buildReadinessCheck(
                'Hierarchy',
                'SubProgram to program parent links',
                $subProgramSourceCount,
                $subProgramRowsWithMissingProgramParent,
                'Every mapped subprogram source row has a program parent link.',
                $subProgramRowsWithMissingProgramParent . ' subprogram source row(s) are missing a valid program parent link.',
                'index.php?route=segment-values/list',
                'Review Segment Values'
            );
        }

        $strategicPillarCount = $this->countActiveRowsIfTableExists('dbo.tblSbStrategicPillar');
        $checks[] = $this->buildReadinessCheck(
            'Planning Framework',
            'Strategic Pillars configured',
            max(1, $strategicPillarCount),
            $strategicPillarCount > 0 ? 0 : 1,
            'Strategic pillars have been configured.',
            'No active strategic pillars are configured yet.',
            'index.php?route=strategy-performance/strategic-pillars',
            'Manage Strategic Pillars'
        );

        $goalCount = $this->countActiveRowsIfTableExists('dbo.tblSbGoal');
        $checks[] = $this->buildReadinessCheck(
            'Planning Framework',
            'Goals configured',
            max(1, $goalCount),
            $goalCount > 0 ? 0 : 1,
            'Goals have been configured.',
            'No active goals are configured yet.',
            'index.php?route=strategy-performance/goals',
            'Manage Goals'
        );

        $attributeFrameworkInstalled = $this->tableExists('dbo.tblSbDimensionAttribute')
            && $this->tableExists('dbo.tblSbDimensionAttributeOption')
            && $this->tableExists('dbo.tblSbDimensionAttributeValue');
        $checks[] = $this->buildReadinessCheck(
            'Planning Framework',
            'Custom attribute framework installed',
            1,
            $attributeFrameworkInstalled ? 0 : 1,
            'The custom attribute framework is installed.',
            'Run the strategic dimension attribute migration before defining client-specific extra fields.',
            'index.php?route=strategy-config/custom-attributes',
            'Manage Custom Attributes'
        );

        $summary = [
            'total_checks' => count($checks),
            'ready_checks' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'ready')),
            'warning_checks' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'warning')),
            'info_checks' => count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'info')),
            'open_items' => array_sum(array_map(static fn(array $check): int => (int) $check['issue_count'], $checks)),
        ];

        return [
            'summary' => $summary,
            'checks' => $checks,
        ];
    }

    public function getStrategicWorkflowState(int $fiscalYearId, int $versionId): array
    {
        $state = [
            'WorkflowInstalled' => $this->tableExists('dbo.tblSbVersionWorkflow'),
            'WorkflowHistoryInstalled' => $this->tableExists('dbo.tblSbVersionWorkflowHistory'),
            'FiscalYearID' => $fiscalYearId,
            'VersionID' => $versionId,
            'WorkflowStatusCode' => 'DRAFT',
            'WorkflowStatusLabel' => 'Draft',
            'StatusNote' => null,
            'SubmittedBy' => null,
            'SubmittedDate' => null,
            'SubmittedByName' => null,
            'ApprovedBy' => null,
            'ApprovedDate' => null,
            'ApprovedByName' => null,
            'LockedBy' => null,
            'LockedDate' => null,
            'LockedByName' => null,
            'IsEditable' => true,
            'AllowedActions' => [],
            'StatusMessage' => 'Strategic version is editable.',
            'History' => [],
        ];

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            $state['IsEditable'] = false;
            $state['StatusMessage'] = 'Fiscal context is not set.';
            return $state;
        }

        if (!$state['WorkflowInstalled']) {
            $state['AllowedActions'] = [];
            $state['StatusMessage'] = 'Workflow controls are available after create_tblSbVersionWorkflow.sql is run.';
            return $state;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1
                w.WorkflowStatusCode,
                w.StatusNote,
                w.SubmittedBy,
                w.SubmittedDate,
                COALESCE(NULLIF(LTRIM(RTRIM(COALESCE(sub.FirstName, N'') + N' ' + COALESCE(sub.LastName, N''))), N''), sub.Username) AS SubmittedByName,
                w.ApprovedBy,
                w.ApprovedDate,
                COALESCE(NULLIF(LTRIM(RTRIM(COALESCE(app.FirstName, N'') + N' ' + COALESCE(app.LastName, N''))), N''), app.Username) AS ApprovedByName,
                w.LockedBy,
                w.LockedDate,
                COALESCE(NULLIF(LTRIM(RTRIM(COALESCE(lck.FirstName, N'') + N' ' + COALESCE(lck.LastName, N''))), N''), lck.Username) AS LockedByName
            FROM dbo.tblSbVersionWorkflow w
            LEFT JOIN dbo.tblUsers sub
                ON sub.UserID = w.SubmittedBy
            LEFT JOIN dbo.tblUsers app
                ON app.UserID = w.ApprovedBy
            LEFT JOIN dbo.tblUsers lck
                ON lck.UserID = w.LockedBy
            WHERE FiscalYearID = :fyWorkflow
              AND VersionID = :verWorkflow
        ");
        $stmt->execute([
            ':fyWorkflow' => $fiscalYearId,
            ':verWorkflow' => $versionId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row !== null) {
            $state = array_merge($state, $row);
            $state['WorkflowStatusCode'] = strtoupper(trim((string) ($row['WorkflowStatusCode'] ?? 'DRAFT')));
        }

        $state['WorkflowStatusLabel'] = $this->workflowStatusLabel((string) $state['WorkflowStatusCode']);
        $state['IsEditable'] = ((string) $state['WorkflowStatusCode']) === 'DRAFT';
        $state['AllowedActions'] = $this->workflowAllowedActions((string) $state['WorkflowStatusCode']);
        $state['StatusMessage'] = match ((string) $state['WorkflowStatusCode']) {
            'SUBMITTED' => 'Strategic version is submitted and read-only until it is reopened or approved.',
            'APPROVED' => 'Strategic version is approved and read-only until it is reopened or locked.',
            'LOCKED' => 'Strategic version is locked and cannot be edited until it is unlocked.',
            default => 'Strategic version is editable.',
        };
        if ($state['WorkflowHistoryInstalled']) {
            $state['History'] = $this->getStrategicWorkflowHistory($fiscalYearId, $versionId);
        }

        return $state;
    }

    public function transitionStrategicWorkflow(int $fiscalYearId, int $versionId, string $action, int $userId, ?string $note = null): array
    {
        $state = $this->getStrategicWorkflowState($fiscalYearId, $versionId);
        if (!$state['WorkflowInstalled']) {
            throw new \RuntimeException('Strategic workflow requires the create_tblSbVersionWorkflow.sql migration.');
        }
        if ($fiscalYearId <= 0 || $versionId <= 0) {
            throw new \RuntimeException('Fiscal context is required before changing strategic workflow state.');
        }

        $action = strtolower(trim($action));
        $allowed = array_map('strtolower', $state['AllowedActions']);
        if (!in_array($action, $allowed, true)) {
            throw new \RuntimeException('Workflow action "' . $action . '" is not allowed while status is ' . $state['WorkflowStatusLabel'] . '.');
        }

        $nextStatus = match ($action) {
            'submit' => 'SUBMITTED',
            'approve' => 'APPROVED',
            'lock' => 'LOCKED',
            'reopen' => 'DRAFT',
            'unlock' => 'APPROVED',
            default => throw new \RuntimeException('Unsupported workflow action.'),
        };

        $existing = $this->conn->prepare("
            SELECT TOP 1 StrategicVersionWorkflowID
            FROM dbo.tblSbVersionWorkflow
            WHERE FiscalYearID = :fyExisting
              AND VersionID = :verExisting
        ");
        $existing->execute([
            ':fyExisting' => $fiscalYearId,
            ':verExisting' => $versionId,
        ]);
        $rowId = (int) ($existing->fetchColumn() ?: 0);

        $effectiveNote = $this->defaultWorkflowNote($action, $note);
        $fromStatus = (string) ($state['WorkflowStatusCode'] ?? 'DRAFT');

        $startedTransaction = !$this->conn->inTransaction();
        if ($startedTransaction) {
            $this->conn->beginTransaction();
        }

        try {
            if ($rowId > 0) {
                $stmt = $this->conn->prepare("
                    UPDATE dbo.tblSbVersionWorkflow
                    SET WorkflowStatusCode = :WorkflowStatusCode,
                        StatusNote = :StatusNote,
                        SubmittedBy = CASE WHEN :ActionSubmit = 1 THEN :UserID ELSE SubmittedBy END,
                        SubmittedDate = CASE WHEN :ActionSubmit = 1 THEN SYSDATETIME() ELSE SubmittedDate END,
                        ApprovedBy = CASE WHEN :ActionApprove = 1 THEN :UserID ELSE ApprovedBy END,
                        ApprovedDate = CASE WHEN :ActionApprove = 1 THEN SYSDATETIME() ELSE ApprovedDate END,
                        LockedBy = CASE WHEN :ActionLock = 1 THEN :UserID ELSE LockedBy END,
                        LockedDate = CASE WHEN :ActionLock = 1 THEN SYSDATETIME() ELSE LockedDate END,
                        UpdatedBy = :UserID,
                        UpdatedDate = SYSDATETIME()
                    WHERE StrategicVersionWorkflowID = :WorkflowID
                ");
                $stmt->execute([
                    ':WorkflowStatusCode' => $nextStatus,
                    ':StatusNote' => $effectiveNote,
                    ':ActionSubmit' => $action === 'submit' ? 1 : 0,
                    ':ActionApprove' => $action === 'approve' ? 1 : 0,
                    ':ActionLock' => $action === 'lock' ? 1 : 0,
                    ':UserID' => $userId,
                    ':WorkflowID' => $rowId,
                ]);
            } else {
                $stmt = $this->conn->prepare("
                    INSERT INTO dbo.tblSbVersionWorkflow (
                        FiscalYearID,
                        VersionID,
                        WorkflowStatusCode,
                        StatusNote,
                        SubmittedBy,
                        SubmittedDate,
                        ApprovedBy,
                        ApprovedDate,
                        LockedBy,
                        LockedDate,
                        CreatedBy,
                        CreatedDate,
                        UpdatedBy,
                        UpdatedDate
                    )
                    VALUES (
                        :FiscalYearID,
                        :VersionID,
                        :WorkflowStatusCode,
                        :StatusNote,
                        :SubmittedBy,
                        CASE WHEN :HasSubmitted = 1 THEN SYSDATETIME() ELSE NULL END,
                        :ApprovedBy,
                        CASE WHEN :HasApproved = 1 THEN SYSDATETIME() ELSE NULL END,
                        :LockedBy,
                        CASE WHEN :HasLocked = 1 THEN SYSDATETIME() ELSE NULL END,
                        :CreatedBy,
                        SYSDATETIME(),
                        :UpdatedBy,
                        SYSDATETIME()
                    )
                ");
                $stmt->execute([
                    ':FiscalYearID' => $fiscalYearId,
                    ':VersionID' => $versionId,
                    ':WorkflowStatusCode' => $nextStatus,
                    ':StatusNote' => $effectiveNote,
                    ':SubmittedBy' => $action === 'submit' ? $userId : null,
                    ':HasSubmitted' => $action === 'submit' ? 1 : 0,
                    ':ApprovedBy' => $action === 'approve' ? $userId : null,
                    ':HasApproved' => $action === 'approve' ? 1 : 0,
                    ':LockedBy' => $action === 'lock' ? $userId : null,
                    ':HasLocked' => $action === 'lock' ? 1 : 0,
                    ':CreatedBy' => $userId,
                    ':UpdatedBy' => $userId,
                ]);
            }

            if ($this->tableExists('dbo.tblSbVersionWorkflowHistory')) {
                $history = $this->conn->prepare("
                    INSERT INTO dbo.tblSbVersionWorkflowHistory (
                        FiscalYearID,
                        VersionID,
                        WorkflowActionCode,
                        FromStatusCode,
                        ToStatusCode,
                        StatusNote,
                        ActionBy,
                        ActionDate
                    )
                    VALUES (
                        :FiscalYearID,
                        :VersionID,
                        :WorkflowActionCode,
                        :FromStatusCode,
                        :ToStatusCode,
                        :StatusNote,
                        :ActionBy,
                        SYSDATETIME()
                    )
                ");
                $history->execute([
                    ':FiscalYearID' => $fiscalYearId,
                    ':VersionID' => $versionId,
                    ':WorkflowActionCode' => strtoupper($action),
                    ':FromStatusCode' => $fromStatus,
                    ':ToStatusCode' => $nextStatus,
                    ':StatusNote' => $effectiveNote,
                    ':ActionBy' => $userId,
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

        return $this->getStrategicWorkflowState($fiscalYearId, $versionId);
    }

    public function getContextLabels(int $fiscalYearId, int $versionId): array
    {
        $sql = "
            SELECT TOP 1
                fy.YearLabel,
                v.VersionLabel
            FROM dbo.tblFiscalYears fy
            INNER JOIN dbo.tblVersions v
                ON v.FiscalYearID = fy.FiscalYearID
            WHERE fy.FiscalYearID = :fy
              AND v.VersionID = :ver
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'YearLabel' => (string) $fiscalYearId,
            'VersionLabel' => (string) $versionId,
        ];
    }

    public function listProjectOptions(): array
    {
        if (!$this->tableExists('dbo.tblSbProject')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                ProjectID,
                ProjectCode,
                ProjectName
            FROM dbo.tblSbProject
            WHERE ActiveFlag = 1
            ORDER BY ProjectName ASC, ProjectCode ASC, ProjectID ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getVersionLabel(int $versionId): string
    {
        if ($versionId <= 0) {
            return '';
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 VersionLabel
            FROM dbo.tblVersions
            WHERE VersionID = :ver
        ");
        $stmt->execute([':ver' => $versionId]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    private function workflowStatusLabel(string $statusCode): string
    {
        return match (strtoupper(trim($statusCode))) {
            'SUBMITTED' => 'Submitted',
            'APPROVED' => 'Approved',
            'LOCKED' => 'Locked',
            default => 'Draft',
        };
    }

    private function workflowActionLabel(string $actionCode): string
    {
        return match (strtoupper(trim($actionCode))) {
            'SUBMIT' => 'Submitted',
            'APPROVE' => 'Approved',
            'LOCK' => 'Locked',
            'REOPEN' => 'Reopened to Draft',
            'UNLOCK' => 'Unlocked to Approved',
            default => ucfirst(strtolower(trim($actionCode))),
        };
    }

    /**
     * @return list<string>
     */
    private function workflowAllowedActions(string $statusCode): array
    {
        return match (strtoupper(trim($statusCode))) {
            'SUBMITTED' => ['approve', 'reopen'],
            'APPROVED' => ['lock', 'reopen'],
            'LOCKED' => ['unlock'],
            default => ['submit'],
        };
    }

    private function defaultWorkflowNote(string $action, ?string $note): ?string
    {
        $trimmed = trim((string) $note);
        if ($trimmed !== '') {
            return $trimmed;
        }

        return match (strtolower(trim($action))) {
            'submit' => 'Submitted for review.',
            'approve' => 'Approved for use.',
            'lock' => 'Locked against further changes.',
            'reopen' => 'Reopened to draft.',
            'unlock' => 'Unlocked back to approved.',
            default => null,
        };
    }

    private function tableExists(string $qualifiedName): bool
    {
        $parts = explode('.', str_replace(['[', ']'], '', $qualifiedName));
        $schema = count($parts) > 1 ? $parts[0] : 'dbo';
        $table = count($parts) > 1 ? $parts[1] : $parts[0];

        $stmt = $this->conn->prepare("
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
        return ((int) ($stmt->fetchColumn() ?: 0)) > 0;
    }

    private function getStrategicWorkflowHistory(int $fiscalYearId, int $versionId): array
    {
        $stmt = $this->conn->prepare("
            SELECT
                h.StrategicWorkflowHistoryID,
                h.WorkflowActionCode,
                h.FromStatusCode,
                h.ToStatusCode,
                h.StatusNote,
                h.ActionBy,
                h.ActionDate,
                COALESCE(NULLIF(LTRIM(RTRIM(COALESCE(u.FirstName, N'') + N' ' + COALESCE(u.LastName, N''))), N''), u.Username) AS ActionByName
            FROM dbo.tblSbVersionWorkflowHistory h
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = h.ActionBy
            WHERE h.FiscalYearID = :fyHistory
              AND h.VersionID = :verHistory
            ORDER BY h.ActionDate DESC, h.StrategicWorkflowHistoryID DESC
        ");
        $stmt->execute([
            ':fyHistory' => $fiscalYearId,
            ':verHistory' => $versionId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['WorkflowActionLabel'] = $this->workflowActionLabel((string) ($row['WorkflowActionCode'] ?? ''));
            $row['FromStatusLabel'] = $this->workflowStatusLabel((string) ($row['FromStatusCode'] ?? 'DRAFT'));
            $row['ToStatusLabel'] = $this->workflowStatusLabel((string) ($row['ToStatusCode'] ?? 'DRAFT'));
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function getAllStrategicDimensionCodes(): array
    {
        return [
            'PROGRAM',
            'SUBPROGRAM',
            'PROJECT',
            'SECTOR',
            'ECONOMIC',
            'FUNDING_TYPE',
            'FUNDING_SOURCE',
            'OBJECTIVE',
            'INDICATOR',
            'TARGET',
            'ACTIVITY',
            'OUTPUT',
        ];
    }

    /**
     * @return list<string>
     */
    private function getConfiguredStrategicDimensionCodes(int $fiscalYearId): array
    {
        if ($fiscalYearId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT StrategicDimensionCode
            FROM dbo.tblSbSegmentConfig
            WHERE FiscalYearID = :fyMappings
              AND ActiveFlag = 1
        ");
        $stmt->execute([':fyMappings' => $fiscalYearId]);

        return array_values(array_filter(array_map(
            static fn(mixed $value): string => strtoupper(trim((string) $value)),
            $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        )));
    }

    /**
     * @return list<string>
     */
    private function getDecidedStrategicDimensionCodes(int $fiscalYearId): array
    {
        if ($fiscalYearId <= 0 || !$this->tableExists('dbo.tblSbSegmentConfig')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT DISTINCT StrategicDimensionCode
            FROM dbo.tblSbSegmentConfig
            WHERE FiscalYearID = :fyDecisions
              AND (
                    ActiveFlag = 1
                    OR Notes = :notMappedNote
              )
        ");
        $stmt->execute([
            ':fyDecisions' => $fiscalYearId,
            ':notMappedNote' => '[NOT_MAPPED]',
        ]);

        return array_values(array_filter(array_map(
            static fn(mixed $value): string => strtoupper(trim((string) $value)),
            $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        )));
    }

    /**
     * @return list<string>
     */
    private function getNarrativeSectionsPresent(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT DISTINCT SectionCode
            FROM dbo.tblSbNarrative
            WHERE FiscalYearID = :fyNarrative
              AND VersionID = :verNarrative
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':fyNarrative' => $fiscalYearId,
            ':verNarrative' => $versionId,
        ]);

        return array_values(array_filter(array_map(
            static fn(mixed $value): string => strtoupper(trim((string) $value)),
            $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        )));
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function fetchCount(string $sql, array $params = []): int
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function countActiveRowsIfTableExists(string $qualifiedName): int
    {
        if (!$this->tableExists($qualifiedName)) {
            return 0;
        }

        return $this->fetchCount("SELECT COUNT(*) FROM {$qualifiedName} WHERE ActiveFlag = 1");
    }

    private function buildReadinessCheck(
        string $category,
        string $title,
        int $totalCount,
        int $issueCount,
        string $readyMessage,
        string $warningMessage,
        string $actionRoute,
        string $actionLabel
    ): array {
        if ($totalCount <= 0) {
            $status = 'info';
            $message = 'No active records are in scope for this check yet.';
        } elseif ($issueCount > 0) {
            $status = 'warning';
            $message = $warningMessage;
        } else {
            $status = 'ready';
            $message = $readyMessage;
        }

        return [
            'category' => $category,
            'title' => $title,
            'total_count' => $totalCount,
            'issue_count' => $issueCount,
            'status' => $status,
            'message' => $message,
            'instruction' => $this->buildReadinessInstruction($category, $title, $status, $actionLabel),
            'action_route' => $actionRoute,
            'action_label' => $actionLabel,
        ];
    }

    private function buildOutOfScopeReadinessCheck(
        string $category,
        string $title,
        string $message,
        string $actionRoute,
        string $actionLabel
    ): array {
        return [
            'category' => $category,
            'title' => $title,
            'total_count' => 0,
            'issue_count' => 0,
            'status' => 'info',
            'message' => $message,
            'instruction' => $this->buildReadinessInstruction($category, $title, 'info', $actionLabel),
            'action_route' => $actionRoute,
            'action_label' => $actionLabel,
        ];
    }

    private function buildReadinessInstruction(
        string $category,
        string $title,
        string $status,
        string $actionLabel
    ): string {
        $category = strtoupper(trim($category));
        $title = strtoupper(trim($title));
        $actionLabel = trim($actionLabel);

        if ($status === 'ready') {
            return '';
        }

        if ($category === 'MAPPINGS' && str_contains($title, 'MAPPING DECISIONS')) {
            return 'Open ' . ($actionLabel !== '' ? $actionLabel : 'the mapping screen')
                . '. Mark each strategic dimension as either source-backed or manually maintained, save the decisions, then rerun readiness.';
        }

        if ($category === 'MAPPINGS') {
            return 'Open ' . ($actionLabel !== '' ? $actionLabel : 'the mapping screen')
                . '. Confirm the correct segment is mapped for the fiscal year, or leave the dimension intentionally unmapped if it should be maintained directly in Strategy.';
        }

        if (str_contains($title, 'RECORDS CREATED IN STRATEGY')) {
            return 'Open ' . ($actionLabel !== '' ? $actionLabel : 'the management screen')
                . '. Use Import Dimensions to create the missing Strategy records from the mapped segment values. If the source list looks wrong, correct the segment values first and then reimport.';
        }

        if (str_contains($title, 'CUSTOM ATTRIBUTE')) {
            return 'Review the custom attribute setup and complete any required values before continuing. If the definitions are correct, return to the data-entry screens and fill the missing attribute values.';
        }

        if (str_contains($title, 'STRATEGIC PILLAR') || str_contains($title, 'GOAL') || str_contains($title, 'OBJECTIVE') || str_contains($title, 'INDICATOR')) {
            return 'Open ' . ($actionLabel !== '' ? $actionLabel : 'the relevant planning screen')
                . '. Create or complete the missing framework records, then rerun readiness to confirm the planning structure is in place.';
        }

        if (str_contains($title, 'FUNDING') || str_contains($title, 'RESOURCE ENVELOPE') || str_contains($title, 'CEILING')) {
            return 'Open ' . ($actionLabel !== '' ? $actionLabel : 'the relevant fiscal screen')
                . '. Complete the missing fiscal setup or entries, save, and rerun readiness to confirm the issue is cleared.';
        }

        if (str_contains($title, 'NARRATIVE') || str_contains($title, 'RISK') || str_contains($title, 'WORKFLOW')) {
            return 'Open ' . ($actionLabel !== '' ? $actionLabel : 'the related governance screen')
                . '. Complete the missing governance content or status steps, then rerun readiness.';
        }

        if ($status === 'info') {
            return 'Use ' . ($actionLabel !== '' ? $actionLabel : 'the related setup screen')
                . ' if you want to bring this area into scope. Otherwise this item can remain informational.';
        }

        return 'Open ' . ($actionLabel !== '' ? $actionLabel : 'the related screen')
            . ', address the missing items shown by this check, save your changes, and then rerun readiness.';
    }

    private function countDistinctSegmentSourceValues(int $fiscalYearId, int $segmentNo): int
    {
        if ($fiscalYearId <= 0 || $segmentNo <= 0) {
            return 0;
        }

        return $this->fetchCount("
            SELECT COUNT(*)
            FROM (
                SELECT DISTINCT SegmentCode
                FROM dbo.tblSegmentValues
                WHERE FiscalYearID = :fySourceCount
                  AND SegmentNo = :segmentNoSourceCount
                  AND ActiveFlag = 1
            ) src
        ", [
            ':fySourceCount' => $fiscalYearId,
            ':segmentNoSourceCount' => $segmentNo,
        ]);
    }

    private function getMappedSegmentNo(int $fiscalYearId, string $dimensionCode): int
    {
        if ($fiscalYearId <= 0 || $dimensionCode === '' || !$this->tableExists('dbo.tblSbSegmentConfig')) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 SegmentNo
            FROM dbo.tblSbSegmentConfig
            WHERE FiscalYearID = :fy
              AND StrategicDimensionCode = :dimensionCode
              AND ActiveFlag = 1
            ORDER BY StrategicSegmentConfigID DESC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':dimensionCode' => strtoupper(trim($dimensionCode)),
        ]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function queryAll(string $sql, array $params): array
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getApprovedCeilingSummary(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->tableExists('dbo.tblCeilingDefinition')) {
            return [
                'CeilingLineCount' => 0,
                'DataObjectCount' => 0,
                'CeilingBP1' => 0.0,
                'CeilingBP2' => 0.0,
                'CeilingBP3' => 0.0,
                'CeilingBP4' => 0.0,
                'CeilingBP5' => 0.0,
                'CeilingBP6' => 0.0,
                'CeilingBP7' => 0.0,
                'CeilingBP8' => 0.0,
                'CeilingBP9' => 0.0,
                'CeilingBP10' => 0.0,
                'CeilingBP11' => 0.0,
                'CeilingBP12' => 0.0,
                'CeilingBPTotal' => 0.0,
            ];
        }

        $stmt = $this->conn->prepare("
            SELECT
                COUNT(*) AS CeilingLineCount,
                COUNT(DISTINCT NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS DataObjectCount,
                COALESCE(SUM(CeilingBP1), 0) AS CeilingBP1,
                COALESCE(SUM(CeilingBP2), 0) AS CeilingBP2,
                COALESCE(SUM(CeilingBP3), 0) AS CeilingBP3,
                COALESCE(SUM(CeilingBP4), 0) AS CeilingBP4,
                COALESCE(SUM(CeilingBP5), 0) AS CeilingBP5,
                COALESCE(SUM(CeilingBP6), 0) AS CeilingBP6,
                COALESCE(SUM(CeilingBP7), 0) AS CeilingBP7,
                COALESCE(SUM(CeilingBP8), 0) AS CeilingBP8,
                COALESCE(SUM(CeilingBP9), 0) AS CeilingBP9,
                COALESCE(SUM(CeilingBP10), 0) AS CeilingBP10,
                COALESCE(SUM(CeilingBP11), 0) AS CeilingBP11,
                COALESCE(SUM(CeilingBP12), 0) AS CeilingBP12,
                COALESCE(SUM(CeilingBPTotal), 0) AS CeilingBPTotal
            FROM dbo.tblCeilingDefinition
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND ActiveFlag = 1
              AND ApprovedFlag = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function getResourceEnvelopeByDataObject(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->tableExists('dbo.tblCeilingDefinition')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                NULLIF(LTRIM(RTRIM(cd.DataObjectCode)), '') AS DataObjectCode,
                COALESCE(doc.DataObjectName, N'Unscoped / Global') AS DataObjectName,
                COUNT(*) AS CeilingLineCount,
                COALESCE(SUM(cd.CeilingBPTotal), 0) AS CeilingBPTotal,
                COALESCE(SUM(cd.CeilingBP1), 0) AS CeilingBP1,
                COALESCE(SUM(cd.CeilingBP2), 0) AS CeilingBP2,
                COALESCE(SUM(cd.CeilingBP3), 0) AS CeilingBP3,
                COALESCE(SUM(cd.CeilingBP4), 0) AS CeilingBP4,
                COALESCE(SUM(cd.CeilingBP5), 0) AS CeilingBP5,
                COALESCE(SUM(cd.CeilingBP6), 0) AS CeilingBP6,
                COALESCE(SUM(cd.CeilingBP7), 0) AS CeilingBP7,
                COALESCE(SUM(cd.CeilingBP8), 0) AS CeilingBP8,
                COALESCE(SUM(cd.CeilingBP9), 0) AS CeilingBP9,
                COALESCE(SUM(cd.CeilingBP10), 0) AS CeilingBP10,
                COALESCE(SUM(cd.CeilingBP11), 0) AS CeilingBP11,
                COALESCE(SUM(cd.CeilingBP12), 0) AS CeilingBP12
            FROM dbo.tblCeilingDefinition cd
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.DataObjectCode = cd.DataObjectCode
            WHERE cd.FiscalYearID = :fy
              AND cd.VersionID = :ver
              AND cd.ActiveFlag = 1
              AND cd.ApprovedFlag = 1
            GROUP BY
                NULLIF(LTRIM(RTRIM(cd.DataObjectCode)), ''),
                COALESCE(doc.DataObjectName, N'Unscoped / Global')
            ORDER BY CeilingBPTotal DESC, DataObjectName ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getSectorCeilingComparison(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->tableExists('dbo.tblCeilingDefinition')) {
            return [];
        }

        $sectorSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'SECTOR');
        if ($sectorSegmentNo <= 0 || $sectorSegmentNo > 20) {
            return [];
        }

        $column = $this->segmentCodeColumn($sectorSegmentNo);

        $sql = "
            WITH CeilingAgg AS (
                SELECT
                    NULLIF(LTRIM(RTRIM(cd.{$column})), '') AS SourceSegmentCode,
                    COUNT(*) AS CeilingLineCount,
                    COALESCE(SUM(cd.CeilingBPTotal), 0) AS CeilingAmount
                FROM dbo.tblCeilingDefinition cd
                WHERE cd.FiscalYearID = :fyCeiling
                  AND cd.VersionID = :verCeiling
                  AND cd.ActiveFlag = 1
                  AND cd.ApprovedFlag = 1
                GROUP BY NULLIF(LTRIM(RTRIM(cd.{$column})), '')
            ),
            PlanAgg AS (
                SELECT
                    s.SectorID,
                    s.SectorName,
                    NULLIF(LTRIM(RTRIM(s.SourceSegmentCode)), '') AS SourceSegmentCode,
                    COALESCE(SUM(ab.Amount), 0) AS PlannedAmount
                FROM dbo.tblSbSector s
                LEFT JOIN dbo.tblSbProgram p
                    ON p.SectorID = s.SectorID
                   AND p.ActiveFlag = 1
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
                WHERE s.ActiveFlag = 1
                GROUP BY s.SectorID, s.SectorName, NULLIF(LTRIM(RTRIM(s.SourceSegmentCode)), '')
            )
            SELECT
                p.SectorID,
                COALESCE(p.SectorName, CONCAT(N'Unmatched sector code: ', c.SourceSegmentCode), N'Unassigned sector') AS SectorName,
                COALESCE(p.SourceSegmentCode, c.SourceSegmentCode) AS SourceSegmentCode,
                COALESCE(c.CeilingLineCount, 0) AS CeilingLineCount,
                COALESCE(c.CeilingAmount, 0) AS CeilingAmount,
                COALESCE(p.PlannedAmount, 0) AS PlannedAmount,
                COALESCE(c.CeilingAmount, 0) - COALESCE(p.PlannedAmount, 0) AS VarianceAmount,
                CASE WHEN COALESCE(p.PlannedAmount, 0) > COALESCE(c.CeilingAmount, 0) THEN 1 ELSE 0 END AS OverCeilingFlag
            FROM PlanAgg p
            FULL OUTER JOIN CeilingAgg c
                ON c.SourceSegmentCode = p.SourceSegmentCode
            ORDER BY OverCeilingFlag DESC, ABS(COALESCE(c.CeilingAmount, 0) - COALESCE(p.PlannedAmount, 0)) DESC, SectorName ASC
        ";

        return $this->queryAll($sql, [
            ':fyCeiling' => $fiscalYearId,
            ':verCeiling' => $versionId,
            ':fyPlan' => $fiscalYearId,
            ':verPlan' => $versionId,
        ]);
    }

    private function getProgramCeilingComparison(int $fiscalYearId, int $versionId): array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->tableExists('dbo.tblCeilingDefinition')) {
            return [];
        }

        $programSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'PROGRAM');
        if ($programSegmentNo <= 0 || $programSegmentNo > 20) {
            return [];
        }

        $column = $this->segmentCodeColumn($programSegmentNo);

        $sql = "
            WITH CeilingAgg AS (
                SELECT
                    NULLIF(LTRIM(RTRIM(cd.{$column})), '') AS SourceSegmentCode,
                    COUNT(*) AS CeilingLineCount,
                    COALESCE(SUM(cd.CeilingBPTotal), 0) AS CeilingAmount
                FROM dbo.tblCeilingDefinition cd
                WHERE cd.FiscalYearID = :fyCeiling
                  AND cd.VersionID = :verCeiling
                  AND cd.ActiveFlag = 1
                  AND cd.ApprovedFlag = 1
                GROUP BY NULLIF(LTRIM(RTRIM(cd.{$column})), '')
            ),
            PlanAgg AS (
                SELECT
                    p.ProgramID,
                    p.ProgramCode,
                    p.ProgramName,
                    NULLIF(LTRIM(RTRIM(p.SourceSegmentCode)), '') AS SourceSegmentCode,
                    sec.SectorName,
                    COALESCE(SUM(ab.Amount), 0) AS PlannedAmount
                FROM dbo.tblSbProgram p
                LEFT JOIN dbo.tblSbSector sec
                    ON sec.SectorID = p.SectorID
                   AND sec.ActiveFlag = 1
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
                GROUP BY p.ProgramID, p.ProgramCode, p.ProgramName, NULLIF(LTRIM(RTRIM(p.SourceSegmentCode)), ''), sec.SectorName
            )
            SELECT
                p.ProgramID,
                p.ProgramCode,
                COALESCE(p.ProgramName, CONCAT(N'Unmatched program code: ', c.SourceSegmentCode), N'Unassigned program') AS ProgramName,
                p.SectorName,
                COALESCE(p.SourceSegmentCode, c.SourceSegmentCode) AS SourceSegmentCode,
                COALESCE(c.CeilingLineCount, 0) AS CeilingLineCount,
                COALESCE(c.CeilingAmount, 0) AS CeilingAmount,
                COALESCE(p.PlannedAmount, 0) AS PlannedAmount,
                COALESCE(c.CeilingAmount, 0) - COALESCE(p.PlannedAmount, 0) AS VarianceAmount,
                CASE WHEN COALESCE(p.PlannedAmount, 0) > COALESCE(c.CeilingAmount, 0) THEN 1 ELSE 0 END AS OverCeilingFlag
            FROM PlanAgg p
            FULL OUTER JOIN CeilingAgg c
                ON c.SourceSegmentCode = p.SourceSegmentCode
            ORDER BY OverCeilingFlag DESC, ABS(COALESCE(c.CeilingAmount, 0) - COALESCE(p.PlannedAmount, 0)) DESC, ProgramName ASC
        ";

        return $this->queryAll($sql, [
            ':fyCeiling' => $fiscalYearId,
            ':verCeiling' => $versionId,
            ':fyPlan' => $fiscalYearId,
            ':verPlan' => $versionId,
        ]);
    }

    private function segmentCodeColumn(int $segmentNo): string
    {
        if ($segmentNo < 1 || $segmentNo > 20) {
            throw new \InvalidArgumentException('Segment number must be between 1 and 20.');
        }

        return 'Segment' . $segmentNo . 'Code';
    }

    public function getProgramStructureDiagnostics(int $fiscalYearId): array
    {
        $programSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'PROGRAM');
        $subProgramSegmentNo = $this->getMappedSegmentNo($fiscalYearId, 'SUBPROGRAM');

        $summary = [
            'program_segment_no' => $programSegmentNo,
            'sub_program_segment_no' => $subProgramSegmentNo,
            'program_code_count' => 0,
            'program_name_count' => 0,
            'sub_program_code_count' => 0,
            'sub_program_name_count' => 0,
            'program_code_conflict_count' => 0,
            'program_name_conflict_count' => 0,
            'sub_program_code_conflict_count' => 0,
            'sub_program_prefix_issue_count' => 0,
            'mapping_ready' => $programSegmentNo > 0 && $subProgramSegmentNo > 0,
        ];

        if ($fiscalYearId <= 0 || !$summary['mapping_ready']) {
            return [
                'summary' => $summary,
                'program_code_conflicts' => [],
                'program_name_conflicts' => [],
                'sub_program_code_conflicts' => [],
                'sub_program_prefix_issues' => [],
            ];
        }

        $summary['program_code_count'] = $this->fetchCount("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(SegmentCode)), ''))
            FROM dbo.tblSegmentValues
            WHERE FiscalYearID = :programFyCount
              AND SegmentNo = :programSegmentNoCount
              AND ActiveFlag = 1
        ", [
            ':programFyCount' => $fiscalYearId,
            ':programSegmentNoCount' => $programSegmentNo,
        ]);
        $summary['program_name_count'] = $this->fetchCount("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(SegmentName)), ''))
            FROM dbo.tblSegmentValues
            WHERE FiscalYearID = :programFyNameCount
              AND SegmentNo = :programSegmentNoNameCount
              AND ActiveFlag = 1
        ", [
            ':programFyNameCount' => $fiscalYearId,
            ':programSegmentNoNameCount' => $programSegmentNo,
        ]);
        $summary['sub_program_code_count'] = $this->fetchCount("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(SegmentCode)), ''))
            FROM dbo.tblSegmentValues
            WHERE FiscalYearID = :subProgramFyCount
              AND SegmentNo = :subProgramSegmentNoCount
              AND ActiveFlag = 1
        ", [
            ':subProgramFyCount' => $fiscalYearId,
            ':subProgramSegmentNoCount' => $subProgramSegmentNo,
        ]);
        $summary['sub_program_name_count'] = $this->fetchCount("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(SegmentName)), ''))
            FROM dbo.tblSegmentValues
            WHERE FiscalYearID = :subProgramFyNameCount
              AND SegmentNo = :subProgramSegmentNoNameCount
              AND ActiveFlag = 1
        ", [
            ':subProgramFyNameCount' => $fiscalYearId,
            ':subProgramSegmentNoNameCount' => $subProgramSegmentNo,
        ]);

        $programCodeConflicts = $this->queryAll("
            WITH base AS (
                SELECT
                    LTRIM(RTRIM(SegmentCode)) AS SegmentCode,
                    LTRIM(RTRIM(SegmentName)) AS SegmentName,
                    DataObjectCode
                FROM dbo.tblSegmentValues
                WHERE FiscalYearID = :programFyConflict
                  AND SegmentNo = :programSegmentNoConflict
                  AND ActiveFlag = 1
                  AND NULLIF(LTRIM(RTRIM(SegmentCode)), '') IS NOT NULL
                  AND NULLIF(LTRIM(RTRIM(SegmentName)), '') IS NOT NULL
            ),
            code_counts AS (
                SELECT
                    SegmentCode,
                    COUNT(*) AS ConflictRowCount,
                    COUNT(DISTINCT SegmentName) AS DistinctNameCount
                FROM base
                GROUP BY SegmentCode
                HAVING COUNT(DISTINCT SegmentName) > 1
            ),
            name_lists AS (
                SELECT
                    dn.SegmentCode,
                    STRING_AGG(dn.SegmentName, '; ') WITHIN GROUP (ORDER BY dn.SegmentName) AS ProgramNames
                FROM (
                    SELECT DISTINCT SegmentCode, SegmentName
                    FROM base
                ) dn
                GROUP BY dn.SegmentCode
            ),
            scope_lists AS (
                SELECT
                    ds.SegmentCode,
                    STRING_AGG(ds.DataObjectCode, ', ') WITHIN GROUP (ORDER BY ds.DataObjectCode) AS DataObjectCodes
                FROM (
                    SELECT DISTINCT SegmentCode, DataObjectCode
                    FROM base
                ) ds
                GROUP BY ds.SegmentCode
            )
            SELECT
                cc.SegmentCode AS ProgramCode,
                cc.ConflictRowCount AS TotalRows,
                cc.DistinctNameCount,
                COALESCE(nl.ProgramNames, '') AS ProgramNames,
                COALESCE(sl.DataObjectCodes, '') AS DataObjectCodes
            FROM code_counts cc
            LEFT JOIN name_lists nl
                ON nl.SegmentCode = cc.SegmentCode
            LEFT JOIN scope_lists sl
                ON sl.SegmentCode = cc.SegmentCode
            ORDER BY cc.DistinctNameCount DESC, cc.SegmentCode ASC
        ", [
            ':programFyConflict' => $fiscalYearId,
            ':programSegmentNoConflict' => $programSegmentNo,
        ]);

        $programNameConflicts = $this->queryAll("
            WITH base AS (
                SELECT
                    LTRIM(RTRIM(SegmentCode)) AS SegmentCode,
                    LTRIM(RTRIM(SegmentName)) AS SegmentName,
                    DataObjectCode
                FROM dbo.tblSegmentValues
                WHERE FiscalYearID = :programFyNameConflict
                  AND SegmentNo = :programSegmentNoNameConflict
                  AND ActiveFlag = 1
                  AND NULLIF(LTRIM(RTRIM(SegmentCode)), '') IS NOT NULL
                  AND NULLIF(LTRIM(RTRIM(SegmentName)), '') IS NOT NULL
            ),
            name_counts AS (
                SELECT
                    SegmentName,
                    COUNT(*) AS ConflictRowCount,
                    COUNT(DISTINCT SegmentCode) AS DistinctCodeCount
                FROM base
                GROUP BY SegmentName
                HAVING COUNT(DISTINCT SegmentCode) > 1
            ),
            code_lists AS (
                SELECT
                    dc.SegmentName,
                    STRING_AGG(dc.SegmentCode, ', ') WITHIN GROUP (ORDER BY dc.SegmentCode) AS ProgramCodes
                FROM (
                    SELECT DISTINCT SegmentName, SegmentCode
                    FROM base
                ) dc
                GROUP BY dc.SegmentName
            ),
            scope_lists AS (
                SELECT
                    ds.SegmentName,
                    STRING_AGG(ds.DataObjectCode, ', ') WITHIN GROUP (ORDER BY ds.DataObjectCode) AS DataObjectCodes
                FROM (
                    SELECT DISTINCT SegmentName, DataObjectCode
                    FROM base
                ) ds
                GROUP BY ds.SegmentName
            )
            SELECT
                nc.SegmentName AS ProgramName,
                nc.ConflictRowCount AS TotalRows,
                nc.DistinctCodeCount,
                COALESCE(cl.ProgramCodes, '') AS ProgramCodes,
                COALESCE(sl.DataObjectCodes, '') AS DataObjectCodes
            FROM name_counts nc
            LEFT JOIN code_lists cl
                ON cl.SegmentName = nc.SegmentName
            LEFT JOIN scope_lists sl
                ON sl.SegmentName = nc.SegmentName
            ORDER BY nc.DistinctCodeCount DESC, nc.SegmentName ASC
        ", [
            ':programFyNameConflict' => $fiscalYearId,
            ':programSegmentNoNameConflict' => $programSegmentNo,
        ]);

        $subProgramCodeConflicts = $this->queryAll("
            WITH base AS (
                SELECT
                    LTRIM(RTRIM(SegmentCode)) AS SegmentCode,
                    LTRIM(RTRIM(SegmentName)) AS SegmentName,
                    DataObjectCode,
                    LTRIM(RTRIM(COALESCE(ParentSegmentCode, ''))) AS ParentSegmentCode
                FROM dbo.tblSegmentValues
                WHERE FiscalYearID = :subProgramFyConflict
                  AND SegmentNo = :subProgramSegmentNoConflict
                  AND ActiveFlag = 1
                  AND NULLIF(LTRIM(RTRIM(SegmentCode)), '') IS NOT NULL
                  AND NULLIF(LTRIM(RTRIM(SegmentName)), '') IS NOT NULL
            ),
            code_counts AS (
                SELECT
                    SegmentCode,
                    COUNT(*) AS ConflictRowCount,
                    COUNT(DISTINCT SegmentName) AS DistinctNameCount
                FROM base
                GROUP BY SegmentCode
                HAVING COUNT(DISTINCT SegmentName) > 1
            ),
            name_lists AS (
                SELECT
                    dn.SegmentCode,
                    STRING_AGG(dn.SegmentName, '; ') WITHIN GROUP (ORDER BY dn.SegmentName) AS SubProgramNames
                FROM (
                    SELECT DISTINCT SegmentCode, SegmentName
                    FROM base
                ) dn
                GROUP BY dn.SegmentCode
            ),
            parent_lists AS (
                SELECT
                    dp.SegmentCode,
                    STRING_AGG(dp.ParentSegmentCode, ', ') WITHIN GROUP (ORDER BY dp.ParentSegmentCode) AS ParentProgramCodes
                FROM (
                    SELECT DISTINCT SegmentCode, ParentSegmentCode
                    FROM base
                    WHERE ParentSegmentCode <> ''
                ) dp
                GROUP BY dp.SegmentCode
            )
            SELECT
                cc.SegmentCode AS SubProgramCode,
                cc.ConflictRowCount AS TotalRows,
                cc.DistinctNameCount,
                COALESCE(nl.SubProgramNames, '') AS SubProgramNames,
                COALESCE(pl.ParentProgramCodes, '') AS ParentProgramCodes
            FROM code_counts cc
            LEFT JOIN name_lists nl
                ON nl.SegmentCode = cc.SegmentCode
            LEFT JOIN parent_lists pl
                ON pl.SegmentCode = cc.SegmentCode
            ORDER BY cc.DistinctNameCount DESC, cc.SegmentCode ASC
        ", [
            ':subProgramFyConflict' => $fiscalYearId,
            ':subProgramSegmentNoConflict' => $subProgramSegmentNo,
        ]);

        $subProgramPrefixIssues = $this->queryAll("
            SELECT
                sv.SegmentCode AS SubProgramCode,
                sv.SegmentName AS SubProgramName,
                sv.ParentSegmentCode AS ParentProgramCode,
                sv.DataObjectCode
            FROM dbo.tblSegmentValues sv
            WHERE sv.FiscalYearID = :subProgramFyPrefix
              AND sv.SegmentNo = :subProgramSegmentNoPrefix
              AND sv.ActiveFlag = 1
              AND sv.ParentSegmentNo = :programSegmentNoPrefix
              AND NULLIF(LTRIM(RTRIM(COALESCE(sv.ParentSegmentCode, ''))), '') IS NOT NULL
              AND LEFT(LTRIM(RTRIM(COALESCE(sv.SegmentCode, ''))), LEN(LTRIM(RTRIM(COALESCE(sv.ParentSegmentCode, ''))))) <> LTRIM(RTRIM(COALESCE(sv.ParentSegmentCode, '')))
            ORDER BY sv.ParentSegmentCode ASC, sv.SegmentCode ASC, sv.DataObjectCode ASC
        ", [
            ':subProgramFyPrefix' => $fiscalYearId,
            ':subProgramSegmentNoPrefix' => $subProgramSegmentNo,
            ':programSegmentNoPrefix' => $programSegmentNo,
        ]);

        $summary['program_code_conflict_count'] = count($programCodeConflicts);
        $summary['program_name_conflict_count'] = count($programNameConflicts);
        $summary['sub_program_code_conflict_count'] = count($subProgramCodeConflicts);
        $summary['sub_program_prefix_issue_count'] = count($subProgramPrefixIssues);

        return [
            'summary' => $summary,
            'program_code_conflicts' => $programCodeConflicts,
            'program_name_conflicts' => $programNameConflicts,
            'sub_program_code_conflicts' => $subProgramCodeConflicts,
            'sub_program_prefix_issues' => $subProgramPrefixIssues,
        ];
    }
}
