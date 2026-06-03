<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class TransactionCalculationDiagnosticsModel
{
    private PDO $conn;

    /** @var array<int, array<string, mixed>|null> */
    private array $resultSnapshotCache = [];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function inspectTransaction(int $transactionId): ?array
    {
        $transaction = $this->findTransaction($transactionId);
        if ($transaction === null) {
            return null;
        }

        $calculation = $this->findCalculation(
            (int) ($transaction['CalculationID'] ?? 0),
            (int) ($transaction['FiscalYearID'] ?? 0)
        );
        $bridgeCandidates = $this->findBridgeCandidates($transaction);
        $selectedBridge = $bridgeCandidates[0] ?? null;
        $periods = $selectedBridge !== null ? $this->listModelPeriods((int) $selectedBridge['CalcModelID']) : [];
        $mappings = $selectedBridge !== null ? $this->listBridgeMappings((int) $selectedBridge['CalcTransactionBridgeID']) : [];
        $resolvedMappings = $this->resolveMappings($transaction, $periods, $mappings);
        $formulaRows = $selectedBridge !== null ? $this->listModelFormulas((int) $selectedBridge['CalcModelID']) : [];
        $resultSnapshot = $this->getLatestResultSnapshot($transactionId);
        $formulaRows = $this->attachResultValues($formulaRows, $resultSnapshot);

        return [
            'transaction' => $transaction,
            'calculation' => $calculation,
            'bridgeCandidates' => $bridgeCandidates,
            'selectedBridge' => $selectedBridge,
            'periods' => $periods,
            'mappings' => $mappings,
            'resolvedMappings' => $resolvedMappings,
            'formulaRows' => $formulaRows,
            'resultSnapshot' => $resultSnapshot,
            'chainRows' => $this->listChainTransactions((int) ($transaction['HeadRecordID'] ?? 0)),
        ];
    }

    private function findTransaction(int $transactionId): ?array
    {
        $stmt = $this->conn->prepare('
            SELECT *
            FROM dbo.tblTransactionInput
            WHERE TransactionID = :id
        ');
        $stmt->execute([':id' => $transactionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function findCalculation(int $calculationId, int $fiscalYearId): ?array
    {
        if ($calculationId <= 0 || $fiscalYearId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare('
            SELECT TOP 1 *
            FROM dbo.tblCalculations
            WHERE CalculationID = :calcId
              AND FiscalYearID = :fy
        ');
        $stmt->execute([
            ':calcId' => $calculationId,
            ':fy' => $fiscalYearId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function findBridgeCandidates(array $transaction): array
    {
        $specificity = "
            (CASE WHEN b.LegacyCalculationID IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN b.FiscalYearID IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN b.VersionID IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(b.TransactionTypeCode, ''))), '') IS NOT NULL THEN 1 ELSE 0 END) +
            (CASE WHEN NULLIF(LTRIM(RTRIM(ISNULL(b.UOMCodeInpC, ''))), '') IS NOT NULL THEN 1 ELSE 0 END)
        ";

        $sql = "
            SELECT
                b.CalcTransactionBridgeID,
                b.CalcModelID,
                b.ScenarioID,
                b.CostObjectID,
                b.LegacyCalculationID,
                b.FiscalYearID,
                b.VersionID,
                b.TransactionTypeCode,
                b.UOMCodeInpC,
                b.PriorityNo,
                b.ActiveFlag,
                b.Notes,
                m.ModelCode,
                m.ModelName,
                s.ScenarioCode,
                s.ScenarioName,
                co.CostObjectCode,
                co.CostObjectName,
                {$specificity} AS SpecificityScore
            FROM dbo.tblCalcTransactionBridge b
            INNER JOIN dbo.tblCalcModel m
                ON m.CalcModelID = b.CalcModelID
            INNER JOIN dbo.tblCalcScenario s
                ON s.ScenarioID = b.ScenarioID
            LEFT JOIN dbo.tblCalcCostObject co
                ON co.CostObjectID = b.CostObjectID
            WHERE b.ActiveFlag = 1
              AND (b.LegacyCalculationID IS NULL OR b.LegacyCalculationID = :calcId)
              AND (b.FiscalYearID IS NULL OR b.FiscalYearID = :fy)
              AND (b.VersionID IS NULL OR b.VersionID = :ver)
              AND (NULLIF(LTRIM(RTRIM(ISNULL(b.TransactionTypeCode, ''))), '') IS NULL OR b.TransactionTypeCode = :txnType)
              AND (NULLIF(LTRIM(RTRIM(ISNULL(b.UOMCodeInpC, ''))), '') IS NULL OR b.UOMCodeInpC = :uom)
            ORDER BY SpecificityScore DESC, b.PriorityNo, b.CalcTransactionBridgeID
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':calcId' => (int) ($transaction['CalculationID'] ?? 0),
            ':fy' => (int) ($transaction['FiscalYearID'] ?? 0),
            ':ver' => (int) ($transaction['VersionID'] ?? 0),
            ':txnType' => (string) ($transaction['TransactionTypeCode'] ?? ''),
            ':uom' => (string) ($transaction['UOMCodeInpC'] ?? ''),
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function listModelPeriods(int $calcModelId): array
    {
        $stmt = $this->conn->prepare('
            SELECT PeriodID, PeriodCode, PeriodNo, SequenceNo, ActiveFlag
            FROM dbo.tblCalcPeriod
            WHERE CalcModelID = :modelId
            ORDER BY SequenceNo, PeriodCode
        ');
        $stmt->execute([':modelId' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function listBridgeMappings(int $bridgeId): array
    {
        $stmt = $this->conn->prepare('
            SELECT
                map.CalcTransactionNodeMapID,
                map.CalcTransactionBridgeID,
                map.NodeID,
                map.SourceTypeCode,
                map.SourceName,
                map.ConstantDecimal,
                map.RequiredFlag,
                map.ActiveFlag,
                map.Notes,
                node.NodeCode,
                node.NodeName,
                node.NodeTypeCode,
                node.NodeCategoryCode,
                node.UnitOfMeasureCode,
                node.NodeOrder,
                formula.ExpressionText
            FROM dbo.tblCalcTransactionNodeMap map
            INNER JOIN dbo.tblCalcNode node
                ON node.NodeID = map.NodeID
            LEFT JOIN dbo.tblCalcFormula formula
                ON formula.NodeID = node.NodeID
               AND formula.ActiveFlag = 1
            WHERE map.CalcTransactionBridgeID = :bridgeId
            ORDER BY node.NodeOrder, node.NodeCode, map.CalcTransactionNodeMapID
        ');
        $stmt->execute([':bridgeId' => $bridgeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function resolveMappings(array $transaction, array $periods, array $mappings): array
    {
        if ($periods === []) {
            $periods = [[
                'PeriodCode' => 'TXN',
                'PeriodNo' => 1,
                'SequenceNo' => 1,
            ]];
        }

        $rows = [];
        foreach ($mappings as $mapping) {
            foreach ($periods as $period) {
                $rows[] = $this->resolveMapping($transaction, $period, $mapping);
            }
        }

        return $rows;
    }

    private function resolveMapping(array $transaction, array $period, array $mapping): array
    {
        $sourceType = strtoupper((string) ($mapping['SourceTypeCode'] ?? ''));
        $sourceName = (string) ($mapping['SourceName'] ?? '');
        $expandedSource = $this->expandSourceName($sourceName, $period);

        $resolved = [
            'NodeCode' => (string) ($mapping['NodeCode'] ?? ''),
            'NodeName' => (string) ($mapping['NodeName'] ?? ''),
            'SourceTypeCode' => $sourceType,
            'SourceName' => $sourceName,
            'ExpandedSourceName' => $expandedSource,
            'PeriodCode' => (string) ($period['PeriodCode'] ?? 'TXN'),
            'ResolvedValue' => null,
            'ResolutionStatus' => 'UNRESOLVED',
            'ResolutionDetail' => '',
        ];

        if ($sourceType === 'CONSTANT') {
            $resolved['ResolvedValue'] = $mapping['ConstantDecimal'];
            $resolved['ResolutionStatus'] = 'OK';
            $resolved['ResolutionDetail'] = 'Constant value from transaction node map.';
            return $resolved;
        }

        if ($sourceType === 'COLUMN' || $sourceType === 'COLUMN_PATTERN') {
            $value = $this->decimalOrNull($transaction[$expandedSource] ?? null);
            $resolved['ResolvedValue'] = $value;
            $resolved['ResolutionStatus'] = $value !== null ? 'OK' : 'MISSING';
            $resolved['ResolutionDetail'] = $value !== null
                ? 'Resolved from tblTransactionInput.' 
                : 'Column value was blank or missing on tblTransactionInput.';
            return $resolved;
        }

        if ($sourceType === 'RATE_LOOKUP') {
            $rate = $this->resolveRateLookup($transaction, $expandedSource);
            $resolved['ResolvedValue'] = $rate['value'];
            $resolved['ResolutionStatus'] = $rate['value'] !== null ? 'OK' : 'MISSING';
            $resolved['ResolutionDetail'] = $rate['detail'];
            return $resolved;
        }

        if ($sourceType === 'CHAIN_RESULT' || $sourceType === 'CHAIN_RESULT_PATTERN') {
            $chain = $this->resolveChainResult($transaction, $expandedSource);
            $resolved['ResolvedValue'] = $chain['value'];
            $resolved['ResolutionStatus'] = $chain['value'] !== null ? 'OK' : 'UNRESOLVED';
            $resolved['ResolutionDetail'] = $chain['detail'];
            return $resolved;
        }

        if ($sourceType === 'PREVIOUS_RESULT' || $sourceType === 'PREVIOUS_RESULT_PATTERN') {
            $prev = $this->resolveLatestResultValue((int) ($transaction['TransactionID'] ?? 0), $expandedSource);
            $resolved['ResolvedValue'] = $prev['value'];
            $resolved['ResolutionStatus'] = $prev['value'] !== null ? 'OK' : 'UNRESOLVED';
            $resolved['ResolutionDetail'] = $prev['detail'];
            return $resolved;
        }

        $resolved['ResolutionDetail'] = 'Unsupported source type for diagnostics.';
        return $resolved;
    }

    private function listModelFormulas(int $calcModelId): array
    {
        $stmt = $this->conn->prepare('
            SELECT
                node.NodeID,
                node.NodeCode,
                node.NodeName,
                node.NodeTypeCode,
                node.NodeCategoryCode,
                node.UnitOfMeasureCode,
                node.NodeOrder,
                formula.ExpressionText,
                ISNULL(dep.DependencyCount, 0) AS DependencyCount
            FROM dbo.tblCalcNode node
            LEFT JOIN dbo.tblCalcFormula formula
                ON formula.NodeID = node.NodeID
               AND formula.ActiveFlag = 1
            LEFT JOIN (
                SELECT NodeID, COUNT(*) AS DependencyCount
                FROM dbo.tblCalcDependency
                GROUP BY NodeID
            ) dep ON dep.NodeID = node.NodeID
            WHERE node.CalcModelID = :modelId
              AND node.ActiveFlag = 1
            ORDER BY node.NodeOrder, node.NodeCode
        ');
        $stmt->execute([':modelId' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function attachResultValues(array $formulaRows, ?array $snapshot): array
    {
        if ($snapshot === null) {
            return $formulaRows;
        }

        $periodMap = $snapshot['periodMap'] ?? [];
        $flatRow = $snapshot['flatRow'] ?? [];

        foreach ($formulaRows as &$row) {
            $nodeCode = (string) ($row['NodeCode'] ?? '');
            $row['ResultValue'] = $periodMap[$nodeCode] ?? ($flatRow[$nodeCode] ?? null);
        }

        unset($row);
        return $formulaRows;
    }

    private function listChainTransactions(int $headRecordId): array
    {
        if ($headRecordId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare('
            SELECT
                t.TransactionID,
                t.HeadRecordID,
                t.CalculationID,
                t.TransactionTypeCode,
                t.UOMCodeInpC,
                t.DataObjectCode,
                c.CalculationName,
                c.CalculationOrder,
                c.ChildCalculationID,
                bridge.CalcModelID,
                model.ModelCode,
                model.ModelName
            FROM dbo.tblTransactionInput t
            LEFT JOIN dbo.tblCalculations c
                ON c.CalculationID = t.CalculationID
               AND c.FiscalYearID = t.FiscalYearID
            LEFT JOIN dbo.tblCalcTransactionBridge bridge
                ON bridge.LegacyCalculationID = t.CalculationID
               AND (bridge.FiscalYearID IS NULL OR bridge.FiscalYearID = t.FiscalYearID)
               AND (bridge.VersionID IS NULL OR bridge.VersionID = t.VersionID)
               AND bridge.ActiveFlag = 1
            LEFT JOIN dbo.tblCalcModel model
                ON model.CalcModelID = bridge.CalcModelID
            WHERE t.HeadRecordID = :headRecordId
            ORDER BY ISNULL(c.CalculationOrder, 9999), t.CalculationID, t.TransactionID
        ');
        $stmt->execute([':headRecordId' => $headRecordId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $snapshot = $this->getLatestResultSnapshot((int) $row['TransactionID']);
            $row['HasResults'] = $snapshot !== null;
            $row['LatestResultId'] = $snapshot['header']['TransactionResultID'] ?? null;
            $row['LatestTotal'] = $snapshot['flatRow']['BPTotal'] ?? null;
            $row['LatestCalculatedDate'] = $snapshot['flatRow']['CalculatedDate'] ?? ($snapshot['header']['CalculatedDate'] ?? null);
        }

        unset($row);
        return $rows;
    }

    private function resolveRateLookup(array $transaction, string $sourceName): array
    {
        $parts = explode('|', $sourceName, 2);
        if (count($parts) !== 2) {
            return [
                'value' => null,
                'detail' => 'Invalid RATE_LOOKUP source format.',
            ];
        }

        $rateColumn = trim($parts[0]);
        $selector = trim($parts[1]);
        $rateCode = $this->resolveRateCode($transaction, $selector);
        $fiscalYearId = (int) ($transaction['FiscalYearID'] ?? 0);
        $versionId = (int) ($transaction['VersionID'] ?? 0);
        $dataObjectCode = trim((string) ($transaction['DataObjectCode'] ?? '0'));

        if ($rateColumn === '' || $rateCode === '' || $fiscalYearId <= 0 || $versionId <= 0) {
            return [
                'value' => null,
                'detail' => 'Rate lookup could not be resolved because one or more lookup keys were blank.',
            ];
        }

        $primary = $this->queryRateValue($fiscalYearId, $versionId, $dataObjectCode, $rateCode, $rateColumn);
        if ($primary['value'] !== null) {
            return $primary + [
                'detail' => sprintf(
                    'Matched tblRates using FY=%d, Version=%d, DataObjectCode=%s, RateCode=%s, Column=%s.',
                    $fiscalYearId,
                    $versionId,
                    $dataObjectCode,
                    $rateCode,
                    $rateColumn
                ),
            ];
        }

        if ($dataObjectCode !== '0') {
            $fallback = $this->queryRateValue($fiscalYearId, $versionId, '0', $rateCode, $rateColumn);
            if ($fallback['value'] !== null) {
                return $fallback + [
                    'detail' => sprintf(
                        'Matched tblRates fallback row using FY=%d, Version=%d, DataObjectCode=0, RateCode=%s, Column=%s.',
                        $fiscalYearId,
                        $versionId,
                        $rateCode,
                        $rateColumn
                    ),
                ];
            }
        }

        return [
            'value' => null,
            'detail' => sprintf(
                'No tblRates row matched FY=%d, Version=%d, DataObjectCode=%s (or fallback 0), RateCode=%s, Column=%s.',
                $fiscalYearId,
                $versionId,
                $dataObjectCode,
                $rateCode,
                $rateColumn
            ),
        ];
    }

    private function queryRateValue(int $fiscalYearId, int $versionId, string $dataObjectCode, string $rateCode, string $rateColumn): array
    {
        $safeColumn = $this->toSafeIdentifier($rateColumn);
        if ($safeColumn === null) {
            return [
                'value' => null,
                'detail' => 'Unsafe rate column name.',
            ];
        }

        $sql = '
            SELECT TOP 1 ' . $safeColumn . ' AS RateValue
            FROM dbo.tblRates
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND DataObjectCode = :doc
              AND RateCode = :rateCode
        ';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':doc' => $dataObjectCode,
            ':rateCode' => $rateCode,
        ]);

        $value = $stmt->fetchColumn();
        return [
            'value' => $this->decimalOrNull($value),
        ];
    }

    private function resolveRateCode(array $transaction, string $selector): string
    {
        if (str_starts_with(strtoupper($selector), 'FIELD:')) {
            $fieldName = substr($selector, 6);
            return trim((string) ($transaction[$fieldName] ?? ''));
        }

        if (str_starts_with(strtoupper($selector), 'LITERAL:')) {
            return trim(substr($selector, 8));
        }

        return trim($selector);
    }

    private function resolveChainResult(array $transaction, string $sourceName): array
    {
        $parts = explode('|', $sourceName, 2);
        if (count($parts) !== 2) {
            return [
                'value' => null,
                'detail' => 'Invalid CHAIN_RESULT source format.',
            ];
        }

        $stageName = trim($parts[0]);
        $nodeCode = trim($parts[1]);
        $headRecordId = (int) ($transaction['HeadRecordID'] ?? 0);
        if ($headRecordId <= 0 || $stageName === '' || $nodeCode === '') {
            return [
                'value' => null,
                'detail' => 'Chain lookup keys were incomplete.',
            ];
        }

        $stmt = $this->conn->prepare('
            SELECT TOP 1 t.TransactionID
            FROM dbo.tblTransactionInput t
            INNER JOIN dbo.tblCalculations c
                ON c.CalculationID = t.CalculationID
               AND c.FiscalYearID = t.FiscalYearID
            WHERE t.HeadRecordID = :headRecordId
              AND c.CalculationName = :stageName
            ORDER BY t.TransactionID
        ');
        $stmt->execute([
            ':headRecordId' => $headRecordId,
            ':stageName' => $stageName,
        ]);

        $sourceTransactionId = (int) ($stmt->fetchColumn() ?: 0);
        if ($sourceTransactionId <= 0) {
            return [
                'value' => null,
                'detail' => sprintf('No sibling transaction was found for stage %s under HeadRecordID %d.', $stageName, $headRecordId),
            ];
        }

        $resolved = $this->resolveLatestResultValue($sourceTransactionId, $nodeCode);
        $detail = $resolved['value'] !== null
            ? sprintf('Resolved from sibling TransactionID %d stage %s.', $sourceTransactionId, $stageName)
            : sprintf('Sibling TransactionID %d stage %s exists, but node %s was not found in its latest result set.', $sourceTransactionId, $stageName, $nodeCode);

        return [
            'value' => $resolved['value'],
            'detail' => $detail,
        ];
    }

    private function resolveLatestResultValue(int $transactionId, string $nodeCode): array
    {
        $snapshot = $this->getLatestResultSnapshot($transactionId);
        if ($snapshot === null) {
            return [
                'value' => null,
                'detail' => 'No persisted transaction result was found.',
            ];
        }

        $value = $snapshot['periodMap'][$nodeCode] ?? ($snapshot['flatRow'][$nodeCode] ?? null);
        return [
            'value' => $this->decimalOrNull($value),
            'detail' => $value !== null
                ? 'Resolved from latest persisted transaction result.'
                : 'Latest persisted transaction result did not contain that node code.',
        ];
    }

    private function getLatestResultSnapshot(int $transactionId): ?array
    {
        if (array_key_exists($transactionId, $this->resultSnapshotCache)) {
            return $this->resultSnapshotCache[$transactionId];
        }

        $stmt = $this->conn->prepare('
            SELECT TOP 1 *
            FROM dbo.tblTransactionResult
            WHERE TransactionID = :transactionId
            ORDER BY TransactionResultID DESC
        ');
        $stmt->execute([':transactionId' => $transactionId]);
        $header = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($header === null) {
            $this->resultSnapshotCache[$transactionId] = null;
            return null;
        }

        $resultId = (int) ($header['TransactionResultID'] ?? 0);

        $flatStmt = $this->conn->prepare('
            SELECT TOP 1 *
            FROM dbo.tblTransactionResultFlat
            WHERE TransactionResultID = :resultId
            ORDER BY TransactionResultFlatID DESC
        ');
        $flatStmt->execute([':resultId' => $resultId]);
        $flatRow = $flatStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $periodStmt = $this->conn->prepare('
            SELECT PeriodCode, Amount
            FROM dbo.tblTransactionResultPeriod
            WHERE TransactionResultID = :resultId
            ORDER BY TransactionResultPeriodID
        ');
        $periodStmt->execute([':resultId' => $resultId]);
        $periodRows = $periodStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $periodMap = [];
        foreach ($periodRows as $row) {
            $periodMap[(string) $row['PeriodCode']] = $this->decimalOrNull($row['Amount']);
        }

        $snapshot = [
            'header' => $header,
            'flatRow' => $flatRow,
            'periodRows' => $periodRows,
            'periodMap' => $periodMap,
        ];

        $this->resultSnapshotCache[$transactionId] = $snapshot;
        return $snapshot;
    }

    private function expandSourceName(string $pattern, array $period): string
    {
        return str_replace(
            ['{PeriodNo}', '{PeriodCode}', '{SequenceNo}'],
            [
                (string) ($period['PeriodNo'] ?? ''),
                (string) ($period['PeriodCode'] ?? ''),
                (string) ($period['SequenceNo'] ?? ''),
            ],
            $pattern
        );
    }

    private function decimalOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 6, '.', '');
        }

        return null;
    }

    private function toSafeIdentifier(string $columnName): ?string
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $columnName) === 1
            ? '[' . $columnName . ']'
            : null;
    }
}
