<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class ScenarioPublishedResultsModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getFilterOptions(array $filters = []): array
    {
        return [
            'models' => $this->fetchOptions(
                'SELECT DISTINCT ModelCode AS ValueCode, ModelName AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent
                 ORDER BY ModelCode'
            ),
            'scenarios' => $this->fetchOptions(
                'SELECT DISTINCT ScenarioCode AS ValueCode, ScenarioName AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent'
                . ($this->hasFilter($filters, 'model') ? ' WHERE ModelCode = :modelCode' : '')
                . ' ORDER BY ScenarioCode',
                $this->hasFilter($filters, 'model') ? [':modelCode' => trim((string) $filters['model'])] : []
            ),
            'costObjects' => $this->fetchOptions(
                'SELECT DISTINCT CostObjectCode AS ValueCode, CostObjectName AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent'
                . $this->buildScopedWhere($filters, ['model', 'scenario'])
                . ' ORDER BY CostObjectCode',
                $this->buildScopedParams($filters, ['model', 'scenario'])
            ),
            'periods' => $this->fetchOptions(
                'SELECT DISTINCT PeriodCode AS ValueCode, PeriodCode AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent'
                . $this->buildScopedWhere($filters, ['model', 'scenario', 'cost_object'])
                . ' ORDER BY PeriodCode',
                $this->buildScopedParams($filters, ['model', 'scenario', 'cost_object'])
            ),
            'nodes' => $this->fetchOptions(
                'SELECT DISTINCT NodeCode AS ValueCode, NodeName AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent'
                . $this->buildScopedWhere($filters, ['model', 'scenario', 'cost_object', 'period'])
                . ' ORDER BY NodeCode',
                $this->buildScopedParams($filters, ['model', 'scenario', 'cost_object', 'period'])
            ),
        ];
    }

    public function getPublishedSummary(array $filters = []): array
    {
        $params = [];
        $where = $this->buildWhereClause($filters, $params);

        $sql = 'SELECT
                    COUNT(*) AS ResultRowCount,
                    COUNT(DISTINCT ModelCode) AS ModelCount,
                    COUNT(DISTINCT ScenarioCode) AS ScenarioCount,
                    COUNT(DISTINCT CostObjectCode) AS CostObjectCount,
                    COUNT(DISTINCT PeriodCode) AS PeriodCount,
                    COUNT(DISTINCT NodeCode) AS NodeCount,
                    MAX(PublishedDate) AS LatestPublishedDate
                FROM dbo.vwCalcPublishedResultCurrent'
            . $where;

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'ResultRowCount' => 0,
            'ModelCount' => 0,
            'ScenarioCount' => 0,
            'CostObjectCount' => 0,
            'PeriodCount' => 0,
            'NodeCount' => 0,
            'LatestPublishedDate' => null,
        ];
    }

    public function countPublishedResults(array $filters = []): int
    {
        $params = [];
        $where = $this->buildWhereClause($filters, $params);

        $sql = 'SELECT COUNT(*) FROM dbo.vwCalcPublishedResultCurrent' . $where;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function listPublishedResults(array $filters = [], int $page = 1, int $pageSize = 50): array
    {
        $page = max(1, $page);
        $pageSize = min(250, max(1, $pageSize));
        $offset = ($page - 1) * $pageSize;

        $params = [];
        $where = $this->buildWhereClause($filters, $params);

        $sql = 'SELECT
                    CalcPublishEventID,
                    SourceCalcRunID,
                    ModelCode,
                    ModelName,
                    ScenarioCode,
                    ScenarioName,
                    ScenarioTypeCode,
                    CostObjectCode,
                    CostObjectName,
                    PeriodCode,
                    PeriodSequenceNo,
                    NodeCode,
                    NodeName,
                    NodeTypeCode,
                    NodeCategoryCode,
                    UnitOfMeasureCode,
                    NodeOrder,
                    ValueDecimal,
                    ValueText,
                    ValueBit,
                    PublishedDate,
                    PublishedRowCount
                FROM dbo.vwCalcPublishedResultCurrent'
            . $where
            . ' ORDER BY
                    ModelCode,
                    ScenarioCode,
                    PeriodSequenceNo,
                    CostObjectCode,
                    NodeOrder,
                    NodeCode
                OFFSET :offset ROWS FETCH NEXT :pageSize ROWS ONLY';

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':pageSize', $pageSize, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getComparisonOptions(array $filters = []): array
    {
        $modelFilter = trim((string) ($filters['model'] ?? ''));

        return [
            'models' => $this->fetchOptions(
                'SELECT DISTINCT ModelCode AS ValueCode, ModelName AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent
                 ORDER BY ModelCode'
            ),
            'scenarios' => $this->fetchOptions(
                "SELECT DISTINCT
                    ScenarioCode AS ValueCode,
                    ScenarioName AS ValueLabel,
                    ScenarioTypeCode,
                    CASE WHEN ScenarioTypeCode = 'BASE' THEN 0 ELSE 1 END AS SortOrder
                 FROM dbo.vwCalcPublishedResultCurrent"
                . ($modelFilter !== '' ? ' WHERE ModelCode = :modelCode' : '')
                . " ORDER BY
                    SortOrder,
                    ScenarioCode",
                $modelFilter !== '' ? [':modelCode' => $modelFilter] : []
            ),
            'costObjects' => $this->fetchOptions(
                'SELECT DISTINCT CostObjectCode AS ValueCode, CostObjectName AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent'
                . ($modelFilter !== '' ? ' WHERE ModelCode = :modelCode' : '')
                . ' ORDER BY CostObjectCode',
                $modelFilter !== '' ? [':modelCode' => $modelFilter] : []
            ),
            'periods' => $this->fetchOptions(
                'SELECT DISTINCT PeriodCode AS ValueCode, PeriodCode AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent'
                . ($modelFilter !== '' ? ' WHERE ModelCode = :modelCode' : '')
                . ' ORDER BY PeriodCode',
                $modelFilter !== '' ? [':modelCode' => $modelFilter] : []
            ),
            'nodes' => $this->fetchOptions(
                'SELECT DISTINCT NodeCode AS ValueCode, NodeName AS ValueLabel
                 FROM dbo.vwCalcPublishedResultCurrent'
                . ($modelFilter !== '' ? ' WHERE ModelCode = :modelCode' : '')
                . ' ORDER BY NodeCode',
                $modelFilter !== '' ? [':modelCode' => $modelFilter] : []
            ),
        ];
    }

    public function listComparisonRows(array $filters = []): array
    {
        $compareMode = strtolower(trim((string) ($filters['compare_mode'] ?? 'legacy_budget')));

        if ($compareMode === 'scenario_base') {
            return $this->listScenarioComparisonRows($filters);
        }

        return $this->listLegacyBudgetComparisonRows($filters);
    }

    public function listScenarioComparisonRows(array $filters = []): array
    {
        $modelCode = trim((string) ($filters['model'] ?? ''));
        $baseScenario = trim((string) ($filters['base_scenario'] ?? ''));
        $compareScenarios = is_array($filters['compare_scenarios'] ?? null)
            ? array_values(array_unique(array_filter(array_map('strval', $filters['compare_scenarios']), static fn(string $value): bool => trim($value) !== '')))
            : [];

        if ($modelCode === '' || $baseScenario === '' || $compareScenarios === []) {
            return [];
        }

        $scenarioCodes = array_values(array_unique(array_merge([$baseScenario], $compareScenarios)));
        $params = [
            ':modelCode' => $modelCode,
        ];
        $where = ['ModelCode = :modelCode'];

        $scenarioPlaceholders = [];
        foreach ($scenarioCodes as $index => $scenarioCode) {
            $placeholder = ':scenario' . $index;
            $scenarioPlaceholders[] = $placeholder;
            $params[$placeholder] = $scenarioCode;
        }
        $where[] = 'ScenarioCode IN (' . implode(', ', $scenarioPlaceholders) . ')';

        if ($this->hasFilter($filters, 'cost_object')) {
            $where[] = 'CostObjectCode = :costObjectCode';
            $params[':costObjectCode'] = trim((string) $filters['cost_object']);
        }
        if ($this->hasFilter($filters, 'period')) {
            $where[] = 'PeriodCode = :periodCode';
            $params[':periodCode'] = trim((string) $filters['period']);
        }
        if ($this->hasFilter($filters, 'node')) {
            $where[] = 'NodeCode = :nodeCode';
            $params[':nodeCode'] = trim((string) $filters['node']);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(
                CostObjectCode LIKE :q
                OR CostObjectName LIKE :q
                OR PeriodCode LIKE :q
                OR NodeCode LIKE :q
                OR NodeName LIKE :q
            )';
            $params[':q'] = '%' . $q . '%';
        }

        $sql = 'SELECT
                    ModelCode,
                    ScenarioCode,
                    ScenarioName,
                    ScenarioTypeCode,
                    CostObjectCode,
                    CostObjectName,
                    PeriodCode,
                    PeriodSequenceNo,
                    NodeCode,
                    NodeName,
                    NodeTypeCode,
                    NodeCategoryCode,
                    ValueDecimal,
                    ValueText,
                    ValueBit
                FROM dbo.vwCalcPublishedResultCurrent
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY PeriodSequenceNo, CostObjectCode, NodeCode, ScenarioCode';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $sourceRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $grouped = [];
        foreach ($sourceRows as $row) {
            $groupKey = implode('|', [
                (string) ($row['CostObjectCode'] ?? ''),
                (string) ($row['PeriodCode'] ?? ''),
                (string) ($row['NodeCode'] ?? ''),
            ]);

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'ModelCode' => (string) ($row['ModelCode'] ?? ''),
                    'CostObjectCode' => (string) ($row['CostObjectCode'] ?? ''),
                    'CostObjectName' => (string) ($row['CostObjectName'] ?? ''),
                    'PeriodCode' => (string) ($row['PeriodCode'] ?? ''),
                    'PeriodSequenceNo' => (int) ($row['PeriodSequenceNo'] ?? 0),
                    'NodeCode' => (string) ($row['NodeCode'] ?? ''),
                    'NodeName' => (string) ($row['NodeName'] ?? ''),
                    'NodeTypeCode' => (string) ($row['NodeTypeCode'] ?? ''),
                    'NodeCategoryCode' => (string) ($row['NodeCategoryCode'] ?? ''),
                    'base' => null,
                    'comparisons' => [],
                ];
            }

            $value = $this->normalizePublishedValue($row);
            if ((string) ($row['ScenarioCode'] ?? '') === $baseScenario) {
                $grouped[$groupKey]['base'] = [
                    'scenarioCode' => (string) ($row['ScenarioCode'] ?? ''),
                    'scenarioName' => (string) ($row['ScenarioName'] ?? ''),
                    'value' => $value,
                ];
                continue;
            }

            $compareCode = (string) ($row['ScenarioCode'] ?? '');
            $grouped[$groupKey]['comparisons'][$compareCode] = [
                'scenarioCode' => $compareCode,
                'scenarioName' => (string) ($row['ScenarioName'] ?? ''),
                'value' => $value,
                'delta' => $this->calculateDelta($grouped[$groupKey]['base']['value'] ?? null, $value),
            ];
        }

        foreach ($grouped as &$group) {
            foreach ($compareScenarios as $scenarioCode) {
                if (isset($group['comparisons'][$scenarioCode])) {
                    continue;
                }

                $group['comparisons'][$scenarioCode] = [
                    'scenarioCode' => $scenarioCode,
                    'scenarioName' => $scenarioCode,
                    'value' => null,
                    'delta' => null,
                ];
            }

            $ordered = [];
            foreach ($compareScenarios as $scenarioCode) {
                $ordered[$scenarioCode] = $group['comparisons'][$scenarioCode];
            }
            $group['comparisons'] = $ordered;
        }
        unset($group);

        $rows = array_values($grouped);
        usort($rows, static function (array $a, array $b): int {
            return [$a['PeriodSequenceNo'], $a['CostObjectCode'], $a['NodeCode']]
                <=> [$b['PeriodSequenceNo'], $b['CostObjectCode'], $b['NodeCode']];
        });

        return $rows;
    }

    public function getComparisonSummary(array $filters, array $rows): array
    {
        $compareScenarios = is_array($filters['compare_scenarios'] ?? null) ? $filters['compare_scenarios'] : [];
        $changedCount = 0;

        foreach ($rows as $row) {
            foreach (($row['comparisons'] ?? []) as $comparison) {
                if (($comparison['delta']['has_difference'] ?? false) === true) {
                    $changedCount++;
                    break;
                }
            }
        }

        return [
            'RowCount' => count($rows),
            'CompareScenarioCount' => count($compareScenarios),
            'ChangedRowCount' => $changedCount,
        ];
    }

    public function getScenarioComparisonSummary(array $filters, array $rows): array
    {
        return $this->getComparisonSummary($filters, $rows);
    }

    private function fetchOptions(string $sql, array $params = []): array
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function listLegacyBudgetComparisonRows(array $filters = []): array
    {
        $modelCode = trim((string) ($filters['model'] ?? ''));
        $compareScenarios = is_array($filters['compare_scenarios'] ?? null)
            ? array_values(array_unique(array_filter(array_map('strval', $filters['compare_scenarios']), static fn(string $value): bool => trim($value) !== '')))
            : [];

        if ($modelCode === '' || $compareScenarios === []) {
            return [];
        }

        $legacyContexts = $this->loadLegacyBridgeContexts($modelCode);
        if ($legacyContexts === []) {
            return [];
        }

        $scenarioRows = $this->loadPublishedScenarioRows($modelCode, $compareScenarios, $filters);
        if ($scenarioRows === []) {
            return [];
        }

        $legacyBaseline = $this->loadLegacyBudgetBaselineRows($legacyContexts, $filters);
        if ($legacyBaseline === []) {
            return [];
        }

        $rows = [];
        foreach ($scenarioRows as $row) {
            $groupKey = implode('|', [
                (string) ($row['CostObjectCode'] ?? ''),
                (string) ($row['PeriodCode'] ?? ''),
                (string) ($row['NodeCode'] ?? ''),
            ]);

            if (!isset($legacyBaseline[$groupKey])) {
                continue;
            }

            if (!isset($rows[$groupKey])) {
                $rows[$groupKey] = [
                    'ModelCode' => (string) ($row['ModelCode'] ?? ''),
                    'CostObjectCode' => (string) ($row['CostObjectCode'] ?? ''),
                    'CostObjectName' => (string) ($row['CostObjectName'] ?? ''),
                    'PeriodCode' => (string) ($row['PeriodCode'] ?? ''),
                    'PeriodSequenceNo' => (int) ($row['PeriodSequenceNo'] ?? 0),
                    'NodeCode' => (string) ($row['NodeCode'] ?? ''),
                    'NodeName' => (string) ($row['NodeName'] ?? ''),
                    'NodeTypeCode' => (string) ($row['NodeTypeCode'] ?? ''),
                    'NodeCategoryCode' => (string) ($row['NodeCategoryCode'] ?? ''),
                    'base' => [
                        'scenarioCode' => 'BUDGET_BASE',
                        'scenarioName' => 'Budget Base',
                        'value' => $legacyBaseline[$groupKey],
                    ],
                    'comparisons' => [],
                ];
            }

            $compareCode = (string) ($row['ScenarioCode'] ?? '');
            $value = $this->normalizePublishedValue($row);
            $rows[$groupKey]['comparisons'][$compareCode] = [
                'scenarioCode' => $compareCode,
                'scenarioName' => (string) ($row['ScenarioName'] ?? ''),
                'value' => $value,
                'delta' => $this->calculateDelta($rows[$groupKey]['base']['value'] ?? null, $value),
            ];
        }

        foreach ($rows as &$row) {
            $ordered = [];
            foreach ($compareScenarios as $scenarioCode) {
                $ordered[$scenarioCode] = $row['comparisons'][$scenarioCode] ?? [
                    'scenarioCode' => $scenarioCode,
                    'scenarioName' => $scenarioCode,
                    'value' => null,
                    'delta' => null,
                ];
            }
            $row['comparisons'] = $ordered;
        }
        unset($row);

        $results = array_values($rows);
        usort($results, static function (array $a, array $b): int {
            return [$a['PeriodSequenceNo'], $a['CostObjectCode'], $a['NodeCode']]
                <=> [$b['PeriodSequenceNo'], $b['CostObjectCode'], $b['NodeCode']];
        });

        return $results;
    }

    private function normalizePublishedValue(array $row): ?array
    {
        if ($row['ValueDecimal'] !== null) {
            return [
                'type' => 'decimal',
                'raw' => (string) $row['ValueDecimal'],
                'number' => (float) $row['ValueDecimal'],
                'display' => number_format((float) $row['ValueDecimal'], 2),
            ];
        }

        if ($row['ValueText'] !== null && $row['ValueText'] !== '') {
            return [
                'type' => 'text',
                'raw' => (string) $row['ValueText'],
                'number' => null,
                'display' => (string) $row['ValueText'],
            ];
        }

        if ($row['ValueBit'] !== null) {
            $display = ((int) $row['ValueBit'] === 1) ? 'True' : 'False';
            return [
                'type' => 'bit',
                'raw' => (string) $row['ValueBit'],
                'number' => (int) $row['ValueBit'],
                'display' => $display,
            ];
        }

        return null;
    }

    private function createDecimalValue(float $number): array
    {
        return [
            'type' => 'decimal',
            'raw' => (string) $number,
            'number' => $number,
            'display' => number_format($number, 2),
        ];
    }

    private function calculateDelta(?array $baseValue, ?array $compareValue): ?array
    {
        if ($baseValue === null || $compareValue === null) {
            return null;
        }

        if ($baseValue['type'] === 'decimal' && $compareValue['type'] === 'decimal') {
            $amount = (float) $compareValue['number'] - (float) $baseValue['number'];
            return [
                'display' => number_format($amount, 2),
                'amount' => $amount,
                'has_difference' => abs($amount) > 0.0000001,
            ];
        }

        $hasDifference = (string) ($baseValue['raw'] ?? '') !== (string) ($compareValue['raw'] ?? '');
        return [
            'display' => $hasDifference ? 'Changed' : 'Same',
            'amount' => null,
            'has_difference' => $hasDifference,
        ];
    }

    private function loadLegacyBridgeContexts(string $modelCode): array
    {
        $stmt = $this->conn->prepare(
            'SELECT DISTINCT
                bridge.LegacyCalculationID,
                bridge.FiscalYearID,
                bridge.VersionID,
                costObject.CostObjectCode
             FROM dbo.tblCalcTransactionBridge bridge
             INNER JOIN dbo.tblCalcModel model
                ON model.CalcModelID = bridge.CalcModelID
             LEFT JOIN dbo.tblCalcCostObject costObject
                ON costObject.CostObjectID = bridge.CostObjectID
             WHERE model.ModelCode = :modelCode
               AND bridge.ActiveFlag = 1
               AND bridge.LegacyCalculationID IS NOT NULL
               AND bridge.FiscalYearID IS NOT NULL
               AND bridge.VersionID IS NOT NULL'
        );
        $stmt->execute([':modelCode' => $modelCode]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function loadPublishedScenarioRows(string $modelCode, array $compareScenarios, array $filters): array
    {
        $params = [
            ':modelCode' => $modelCode,
        ];
        $where = ['ModelCode = :modelCode'];

        $scenarioPlaceholders = [];
        foreach ($compareScenarios as $index => $scenarioCode) {
            $placeholder = ':scenario' . $index;
            $scenarioPlaceholders[] = $placeholder;
            $params[$placeholder] = $scenarioCode;
        }
        $where[] = 'ScenarioCode IN (' . implode(', ', $scenarioPlaceholders) . ')';

        if ($this->hasFilter($filters, 'cost_object')) {
            $where[] = 'CostObjectCode = :costObjectCode';
            $params[':costObjectCode'] = trim((string) $filters['cost_object']);
        }
        if ($this->hasFilter($filters, 'period')) {
            $where[] = 'PeriodCode = :periodCode';
            $params[':periodCode'] = trim((string) $filters['period']);
        }
        if ($this->hasFilter($filters, 'node')) {
            $where[] = 'NodeCode = :nodeCode';
            $params[':nodeCode'] = trim((string) $filters['node']);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(
                CostObjectCode LIKE :q
                OR CostObjectName LIKE :q
                OR PeriodCode LIKE :q
                OR NodeCode LIKE :q
                OR NodeName LIKE :q
            )';
            $params[':q'] = '%' . $q . '%';
        }

        $stmt = $this->conn->prepare(
            'SELECT
                ModelCode,
                ScenarioCode,
                ScenarioName,
                CostObjectCode,
                CostObjectName,
                PeriodCode,
                PeriodSequenceNo,
                NodeCode,
                NodeName,
                NodeTypeCode,
                NodeCategoryCode,
                ValueDecimal,
                ValueText,
                ValueBit
             FROM dbo.vwCalcPublishedResultCurrent
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY PeriodSequenceNo, CostObjectCode, NodeCode, ScenarioCode'
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function loadLegacyBudgetBaselineRows(array $contexts, array $filters): array
    {
        $params = [];
        $routeClauses = [];
        foreach (array_values($contexts) as $index => $context) {
            $calcParam = ':calcId' . $index;
            $fyParam = ':fy' . $index;
            $verParam = ':ver' . $index;
            $routeClauses[] = "(ti.CalculationID = {$calcParam} AND ti.FiscalYearID = {$fyParam} AND ti.VersionID = {$verParam})";
            $params[$calcParam] = (int) ($context['LegacyCalculationID'] ?? 0);
            $params[$fyParam] = (int) ($context['FiscalYearID'] ?? 0);
            $params[$verParam] = (int) ($context['VersionID'] ?? 0);
        }

        if ($routeClauses === []) {
            return [];
        }

        $stmt = $this->conn->prepare(
            'WITH Latest AS (
                SELECT
                    rf.*,
                    ROW_NUMBER() OVER (PARTITION BY rf.TransactionID ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC) AS rn
                FROM dbo.tblTransactionResultFlat rf
            )
            SELECT
                SUM(COALESCE(l.BP1, ti.BP1InpN, 0)) AS BP1,
                SUM(COALESCE(l.BP2, ti.BP2InpN, 0)) AS BP2,
                SUM(COALESCE(l.BP3, ti.BP3InpN, 0)) AS BP3,
                SUM(COALESCE(l.BP4, ti.BP4InpN, 0)) AS BP4,
                SUM(COALESCE(l.BP5, ti.BP5InpN, 0)) AS BP5,
                SUM(COALESCE(l.BP6, ti.BP6InpN, 0)) AS BP6,
                SUM(COALESCE(l.BP7, ti.BP7InpN, 0)) AS BP7,
                SUM(COALESCE(l.BP8, ti.BP8InpN, 0)) AS BP8,
                SUM(COALESCE(l.BP9, ti.BP9InpN, 0)) AS BP9,
                SUM(COALESCE(l.BP10, ti.BP10InpN, 0)) AS BP10,
                SUM(COALESCE(l.BP11, ti.BP11InpN, 0)) AS BP11,
                SUM(COALESCE(l.BP12, ti.BP12InpN, 0)) AS BP12,
                SUM(COALESCE(l.BPTotal, ti.BPTotalInpN, 0)) AS BPTotal,
                SUM(COALESCE(l.BPQ1, 0)) AS BPQ1,
                SUM(COALESCE(l.BPQ2, 0)) AS BPQ2,
                SUM(COALESCE(l.BPQ3, 0)) AS BPQ3,
                SUM(COALESCE(l.BPQ4, 0)) AS BPQ4,
                SUM(COALESCE(l.BPOY1, 0)) AS BPOY1,
                SUM(COALESCE(l.BPOY2, 0)) AS BPOY2,
                SUM(COALESCE(l.BPOY3, 0)) AS BPOY3,
                SUM(COALESCE(l.BPOY4, 0)) AS BPOY4,
                SUM(COALESCE(l.BPOY5, 0)) AS BPOY5,
                SUM(COALESCE(l.BPOY6, 0)) AS BPOY6,
                SUM(COALESCE(l.BPOY7, 0)) AS BPOY7,
                SUM(COALESCE(l.BPOY8, 0)) AS BPOY8,
                SUM(COALESCE(l.BPOY9, 0)) AS BPOY9,
                SUM(COALESCE(l.BPOY10, 0)) AS BPOY10
             FROM dbo.tblTransactionInput ti
             LEFT JOIN Latest l
                ON l.TransactionID = ti.TransactionID
               AND l.rn = 1
             WHERE ' . implode(' OR ', $routeClauses)
        );
        $stmt->execute($params);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $costObjectCode = trim((string) ($filters['cost_object'] ?? ''));
        if ($costObjectCode === '') {
            $costObjectCode = trim((string) ($contexts[0]['CostObjectCode'] ?? 'GLOBAL'));
        }
        if ($costObjectCode === '') {
            $costObjectCode = 'GLOBAL';
        }

        $periodCode = trim((string) ($filters['period'] ?? ''));
        if ($periodCode === '') {
            $periodCode = 'TXN';
        }

        $allowedNode = trim((string) ($filters['node'] ?? ''));
        $rows = [];
        foreach ($this->legacyBudgetNodeCodes() as $nodeCode) {
            if ($allowedNode !== '' && strcasecmp($allowedNode, $nodeCode) !== 0) {
                continue;
            }

            $number = isset($totals[$nodeCode]) ? (float) $totals[$nodeCode] : 0.0;
            $rows[$costObjectCode . '|' . $periodCode . '|' . $nodeCode] = $this->createDecimalValue($number);
        }

        return $rows;
    }

    private function legacyBudgetNodeCodes(): array
    {
        return [
            'BP1', 'BP2', 'BP3', 'BP4', 'BP5', 'BP6',
            'BP7', 'BP8', 'BP9', 'BP10', 'BP11', 'BP12',
            'BPTotal',
            'BPQ1', 'BPQ2', 'BPQ3', 'BPQ4',
            'BPOY1', 'BPOY2', 'BPOY3', 'BPOY4', 'BPOY5',
            'BPOY6', 'BPOY7', 'BPOY8', 'BPOY9', 'BPOY10',
        ];
    }

    private function buildWhereClause(array $filters, array &$params): string
    {
        $where = [];

        if ($this->hasFilter($filters, 'model')) {
            $where[] = 'ModelCode = :modelCode';
            $params[':modelCode'] = trim((string) $filters['model']);
        }
        if ($this->hasFilter($filters, 'scenario')) {
            $where[] = 'ScenarioCode = :scenarioCode';
            $params[':scenarioCode'] = trim((string) $filters['scenario']);
        }
        if ($this->hasFilter($filters, 'cost_object')) {
            $where[] = 'CostObjectCode = :costObjectCode';
            $params[':costObjectCode'] = trim((string) $filters['cost_object']);
        }
        if ($this->hasFilter($filters, 'period')) {
            $where[] = 'PeriodCode = :periodCode';
            $params[':periodCode'] = trim((string) $filters['period']);
        }
        if ($this->hasFilter($filters, 'node')) {
            $where[] = 'NodeCode = :nodeCode';
            $params[':nodeCode'] = trim((string) $filters['node']);
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(
                ModelCode LIKE :q
                OR ModelName LIKE :q
                OR ScenarioCode LIKE :q
                OR ScenarioName LIKE :q
                OR CostObjectCode LIKE :q
                OR CostObjectName LIKE :q
                OR PeriodCode LIKE :q
                OR NodeCode LIKE :q
                OR NodeName LIKE :q
            )';
            $params[':q'] = '%' . $q . '%';
        }

        return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    }

    private function buildScopedWhere(array $filters, array $keys): string
    {
        $where = [];

        foreach ($keys as $key) {
            if (!$this->hasFilter($filters, $key)) {
                continue;
            }

            $column = match ($key) {
                'model' => 'ModelCode',
                'scenario' => 'ScenarioCode',
                'cost_object' => 'CostObjectCode',
                'period' => 'PeriodCode',
                'node' => 'NodeCode',
                default => null,
            };

            if ($column !== null) {
                $where[] = $column . ' = :' . $this->parameterNameForFilter($key);
            }
        }

        return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    }

    private function buildScopedParams(array $filters, array $keys): array
    {
        $params = [];

        foreach ($keys as $key) {
            if (!$this->hasFilter($filters, $key)) {
                continue;
            }

            $params[':' . $this->parameterNameForFilter($key)] = trim((string) $filters[$key]);
        }

        return $params;
    }

    private function parameterNameForFilter(string $key): string
    {
        return match ($key) {
            'model' => 'modelCode',
            'scenario' => 'scenarioCode',
            'cost_object' => 'costObjectCode',
            'period' => 'periodCode',
            'node' => 'nodeCode',
            default => $key,
        };
    }

    private function hasFilter(array $filters, string $key): bool
    {
        return isset($filters[$key]) && trim((string) $filters[$key]) !== '';
    }
}
