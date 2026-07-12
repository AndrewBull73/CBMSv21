<?php
declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AIDatasetAnalysisModel;

final class AIDatasetAnalysisService
{
    public function __construct(
        private AIDatasetAnalysisModel $model,
        private AIProviderInterface $provider
    ) {
    }

    public function analyse(array $dataset, array $columns, string $question, array $context): array
    {
        $started = microtime(true);
        $planResult = $this->buildPlan($dataset, $columns, $question, $context);
        $execution = $this->model->executeValidatedPlan($dataset, $columns, $planResult['plan'], $context);
        $rows = $this->addShareOfTotalColumn(
            is_array($execution['rows'] ?? null) ? $execution['rows'] : [],
            $planResult['plan'],
            $question,
            is_array($execution['metric_totals'] ?? null) ? $execution['metric_totals'] : []
        );
        $summary = $this->summariseResult($question, $dataset, $planResult['plan'], $execution);

        return [
            'plan' => $planResult['plan'],
            'plan_source' => $planResult['source'],
            'provider_error' => $planResult['error'],
            'usage' => $planResult['usage'],
            'provider' => $this->provider->code(),
            'model' => $this->provider->model(),
            'sql' => $execution['sql'],
            'params' => $execution['params'],
            'rows' => $this->formatRowsForDisplay(
                $rows,
                is_array($execution['dimensions'] ?? null) ? $execution['dimensions'] : []
            ),
            'row_count' => $execution['row_count'],
            'summary' => $summary,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function buildPlan(array $dataset, array $columns, string $question, array $context): array
    {
        $metadata = [];
        foreach ($columns as $column) {
            $metadata[] = [
                'name' => (string) ($column['ColumnName'] ?? ''),
                'label' => (string) ($column['DisplayName'] ?? $column['ColumnName'] ?? ''),
                'type' => (string) ($column['DataType'] ?? ''),
                'semantic_type' => (string) ($column['SemanticType'] ?? ''),
                'dimension' => (int) ($column['IsDimension'] ?? 0) === 1,
                'metric' => (int) ($column['IsMetric'] ?? 0) === 1,
                'filterable' => (int) ($column['IsFilterable'] ?? 0) === 1,
            ];
        }

        $instructions = implode("\n", [
            'You create safe analysis plans for CBMS datasets.',
            'Return only valid JSON. Do not return SQL.',
            'Use only the supplied column names.',
            'The JSON shape must be: {"dimensions":[],"metrics":[{"column":"","aggregation":"SUM"}],"filters":[],"limit":50,"rationale":""}.',
            'Allowed aggregations: SUM, AVG, MIN, MAX, COUNT.',
            'Allowed filter operators: eq, contains, gte, lte, gt, lt.',
            'Do not include sensitive columns as dimensions.',
        ]);

        $input = json_encode([
            'question' => $question,
            'dataset' => [
                'name' => (string) ($dataset['DatasetName'] ?? ''),
                'description' => (string) ($dataset['Description'] ?? ''),
                'sensitivity' => (string) ($dataset['SensitivityLevel'] ?? 'RESTRICTED'),
            ],
            'context' => $context,
            'columns' => $metadata,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $usage = [];
        try {
            $generated = $this->provider->generate($instructions, (string) $input);
            $usage = is_array($generated['usage'] ?? null) ? $generated['usage'] : [];
            $plan = $this->decodePlan((string) ($generated['text'] ?? ''));
            if ($plan !== null) {
                return ['plan' => $plan, 'source' => 'provider', 'error' => null, 'usage' => $usage];
            }
            return ['plan' => $this->fallbackPlan($columns, $question), 'source' => 'fallback', 'error' => 'Provider returned an unreadable analysis plan.', 'usage' => $usage];
        } catch (\Throwable $e) {
            return ['plan' => $this->fallbackPlan($columns, $question), 'source' => 'fallback', 'error' => $e->getMessage(), 'usage' => $usage];
        }
    }

    private function decodePlan(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text) ?? $text;
        }
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            return null;
        }
        return [
            'dimensions' => array_values((array) ($decoded['dimensions'] ?? [])),
            'metrics' => array_values((array) ($decoded['metrics'] ?? [])),
            'filters' => array_values((array) ($decoded['filters'] ?? [])),
            'limit' => max(1, min(100, (int) ($decoded['limit'] ?? 50))),
            'rationale' => trim((string) ($decoded['rationale'] ?? '')),
        ];
    }

    private function fallbackPlan(array $columns, string $question): array
    {
        $questionLower = strtolower($question);
        $columnByLower = [];
        $dimensionColumns = [];
        $metricColumns = [];
        $filterableColumns = [];

        foreach ($columns as $column) {
            $name = (string) ($column['ColumnName'] ?? '');
            if ($name === '') {
                continue;
            }
            $lowerName = strtolower($name);
            $columnByLower[$lowerName] = $column;
            if ((int) ($column['IsDimension'] ?? 0) === 1 && (int) ($column['IsSensitive'] ?? 0) !== 1) {
                $dimensionColumns[$lowerName] = $name;
            }
            if ((int) ($column['IsMetric'] ?? 0) === 1) {
                $metricColumns[$lowerName] = $name;
            }
            if ((int) ($column['IsFilterable'] ?? 0) === 1) {
                $filterableColumns[$lowerName] = $name;
            }
        }

        $countQuestion = preg_match('/\b(count|number of|how many)\b/i', $question) === 1;
        $dimensions = $countQuestion ? [] : $this->fallbackDimensions($questionLower, $dimensionColumns);
        $metric = $this->fallbackMetric($questionLower, $metricColumns);
        $filters = $this->fallbackFilters($question, $filterableColumns, $columnByLower);
        $limit = $this->fallbackLimit($questionLower);

        return [
            'dimensions' => $dimensions,
            'metrics' => $metric !== null && !$countQuestion
                ? [['column' => $metric, 'aggregation' => 'SUM']]
                : [['column' => '*', 'aggregation' => 'COUNT']],
            'filters' => $filters,
            'limit' => $limit,
            'rationale' => 'Fallback plan generated because an AI analysis plan was not available.',
        ];
    }

    private function fallbackDimensions(string $questionLower, array $dimensionColumns): array
    {
        $matches = [];
        foreach ($dimensionColumns as $lowerName => $name) {
            if (str_contains($questionLower, $lowerName)) {
                $matches[] = $name;
            }
        }

        $semanticHints = [
            'segment1' => ['segment 1', 'segment1', 'vote'],
            'segment2' => ['segment 2', 'segment2', 'ministry'],
            'segment3' => ['segment 3', 'segment3', 'department'],
            'segment4' => ['segment 4', 'segment4', 'fund'],
            'segment5' => ['segment 5', 'segment5'],
            'programcode' => ['program'],
            'economiccode' => ['economic', 'economic indicator', 'gl'],
            'activitycode' => ['activity'],
            'fundcode' => ['fund'],
            'ministrycode' => ['ministry'],
            'departmentcode' => ['department'],
            'votecode' => ['vote'],
        ];
        foreach ($semanticHints as $column => $tokens) {
            if (!isset($dimensionColumns[$column])) {
                continue;
            }
            foreach ($tokens as $token) {
                if (str_contains($questionLower, $token)) {
                    $matches[] = $dimensionColumns[$column];
                    break;
                }
            }
        }

        if ($matches === []) {
            foreach ($dimensionColumns as $name) {
                $matches[] = $name;
                break;
            }
        }

        return array_values(array_unique(array_slice($matches, 0, 4)));
    }

    private function fallbackMetric(string $questionLower, array $metricColumns): ?string
    {
        $hints = [
            'ActualAmount' => ['actual expenditure', 'actual amount', 'actual', 'expenditure', 'spent', 'spending'],
            'BudgetAmount' => ['budget amount', 'budget', 'allocation', 'appropriation'],
            'AvailableBalance' => ['available balance', 'balance', 'remaining'],
            'ExecutionRate' => ['execution rate', 'execution'],
            'ReleasedAmount' => ['released', 'release'],
            'WarrantAmount' => ['warrant'],
            'CommitmentAmount' => ['commitment', 'committed'],
        ];
        foreach ($hints as $column => $tokens) {
            $key = strtolower($column);
            if (!isset($metricColumns[$key])) {
                continue;
            }
            foreach ($tokens as $token) {
                if (str_contains($questionLower, $token)) {
                    return $metricColumns[$key];
                }
            }
        }
        foreach ($metricColumns as $name) {
            return $name;
        }
        return null;
    }

    private function fallbackFilters(string $question, array $filterableColumns, array $columnByLower): array
    {
        $filters = [];
        if (preg_match('/\b(?:fiscal\s*year|fy)\s*(20\d{2})\b/i', $question, $match) === 1 && isset($filterableColumns['fiscalyearid'])) {
            $filters[] = ['column' => $filterableColumns['fiscalyearid'], 'operator' => 'eq', 'value' => (int) $match[1]];
        }
        if (preg_match('/\b(?:version|budget\s*version)\s*(\d+)\b/i', $question, $match) === 1) {
            foreach (['budgetversionid', 'versionid'] as $candidate) {
                if (isset($filterableColumns[$candidate])) {
                    $filters[] = ['column' => $filterableColumns[$candidate], 'operator' => 'eq', 'value' => (int) $match[1]];
                    break;
                }
            }
        }
        if (preg_match('/\b(?:period|month)\s*(\d{1,2})\b/i', $question, $match) === 1 && isset($filterableColumns['periodno'])) {
            $filters[] = ['column' => $filterableColumns['periodno'], 'operator' => 'eq', 'value' => (int) $match[1]];
        }

        foreach ($columnByLower as $lowerName => $column) {
            if (!isset($filterableColumns[$lowerName]) || !preg_match('/^(segment\d+|[a-z]+code)$/', $lowerName)) {
                continue;
            }
            $pattern = '/\b' . preg_quote((string) ($column['ColumnName'] ?? ''), '/') . '\s*(?:=|is|equals)\s*[\'"]?([A-Za-z0-9_.-]+)[\'"]?/i';
            if (preg_match($pattern, $question, $match) === 1) {
                $filters[] = ['column' => $filterableColumns[$lowerName], 'operator' => 'eq', 'value' => (string) $match[1]];
            }
        }
        return array_slice($filters, 0, 6);
    }

    private function fallbackLimit(string $questionLower): int
    {
        if (preg_match('/\btop\s+(\d{1,3})\b/i', $questionLower, $match) === 1) {
            return max(1, min(100, (int) $match[1]));
        }
        if (preg_match('/\b(highest|largest|lowest|smallest|rank|ranking)\b/i', $questionLower) === 1) {
            return 10;
        }
        return 50;
    }

    private function summariseResult(string $question, array $dataset, array $plan, array $execution): string
    {
        $rowCount = (int) ($execution['row_count'] ?? 0);
        $summary = 'Analysis completed for ' . (string) ($dataset['DatasetName'] ?? 'the selected dataset') . '. ';
        $summary .= 'Returned ' . $rowCount . ' row' . ($rowCount === 1 ? '' : 's') . '.';
        if (trim((string) ($plan['rationale'] ?? '')) !== '') {
            $summary .= ' Plan: ' . trim((string) $plan['rationale']);
        }
        if ($rowCount === 0) {
            $summary .= ' No data matched the approved filters and current context.';
        }
        return $summary;
    }

    private function addShareOfTotalColumn(array $rows, array $plan, string $question, array $metricTotals): array
    {
        if ($rows === [] || preg_match('/\b(percent|percentage|share|proportion|contribution)\b/i', $question) !== 1) {
            return $rows;
        }

        $metricAlias = $this->shareMetricAlias($plan, $question, $rows[0] ?? []);
        if ($metricAlias === null) {
            return $rows;
        }

        $total = $this->numericValue($metricTotals[$metricAlias] ?? null) ?? 0.0;
        if ($total == 0.0) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $total += $this->numericValue($row[$metricAlias] ?? null) ?? 0.0;
            }
        }
        if ($total == 0.0) {
            return $rows;
        }

        $shareAlias = preg_replace('/^(SUM|AVG|MIN|MAX|COUNT)_/i', '', $metricAlias) ?: $metricAlias;
        $shareAlias = $shareAlias . 'SharePct';
        $updatedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $value = $this->numericValue($row[$metricAlias] ?? null) ?? 0.0;
            $row[$shareAlias] = ($value / $total) * 100.0;
            $updatedRows[] = $row;
        }
        return $updatedRows;
    }

    private function shareMetricAlias(array $plan, string $question, array $sampleRow): ?string
    {
        $aliases = [];
        foreach ((array) ($plan['metrics'] ?? []) as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $aggregation = strtoupper((string) ($metric['aggregation'] ?? 'SUM'));
            $column = (string) ($metric['column'] ?? '');
            if ($column === '') {
                continue;
            }
            $aliases[] = $aggregation . '_' . ($column === '*' ? 'Rows' : $column);
        }

        $available = array_fill_keys(array_keys($sampleRow), true);
        $questionLower = strtolower($question);
        foreach ($aliases as $alias) {
            if (!isset($available[$alias])) {
                continue;
            }
            $name = strtolower($alias);
            if (
                (str_contains($questionLower, 'budget') && str_contains($name, 'budget'))
                || (str_contains($questionLower, 'actual') && str_contains($name, 'actual'))
                || (str_contains($questionLower, 'expenditure') && (str_contains($name, 'actual') || str_contains($name, 'expenditure')))
            ) {
                return $alias;
            }
        }

        foreach ($aliases as $alias) {
            if (isset($available[$alias])) {
                return $alias;
            }
        }
        foreach (array_keys($sampleRow) as $column) {
            $name = strtolower((string) $column);
            if (str_starts_with($name, 'sum_') || str_starts_with($name, 'avg_') || str_starts_with($name, 'count_')) {
                return (string) $column;
            }
        }
        return null;
    }

    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = str_replace(',', '', trim((string) $value));
        if (preg_match('/^-?\.\d+$/', $text) === 1) {
            $text = str_replace('.', '0.', $text);
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $text) !== 1) {
            return null;
        }
        return (float) $text;
    }

    private function formatRowsForDisplay(array $rows, array $dimensions): array
    {
        $dimensionMap = [];
        foreach ($dimensions as $dimension) {
            $dimensionMap[strtolower((string) $dimension)] = true;
        }

        $formattedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $formatted = [];
            foreach ($row as $column => $value) {
                $formatted[$column] = $this->formatCellForDisplay((string) $column, $value, $dimensionMap);
            }
            $formattedRows[] = $formatted;
        }
        return $formattedRows;
    }

    private function formatCellForDisplay(string $column, mixed $value, array $dimensionMap): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $name = strtolower($column);
        if (isset($dimensionMap[$name]) || $this->isCodeLikeColumn($name)) {
            return (string) $value;
        }

        $text = trim((string) $value);
        $numericText = str_replace(',', '', $text);
        if (preg_match('/^-?\.\d+$/', $numericText) === 1) {
            $numericText = str_replace('.', '0.', $numericText);
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $numericText) !== 1) {
            return $value;
        }

        $number = (float) $numericText;
        if (str_contains($name, 'rate') || str_contains($name, 'pct') || str_contains($name, 'percent')) {
            return number_format($number, 2) . '%';
        }
        if (
            str_contains($name, 'amount')
            || str_contains($name, 'balance')
            || str_contains($name, 'actual')
            || str_contains($name, 'budget')
            || str_contains($name, 'expenditure')
            || str_starts_with($name, 'sum_')
            || str_starts_with($name, 'avg_')
            || str_starts_with($name, 'min_')
            || str_starts_with($name, 'max_')
        ) {
            return number_format($number, 2);
        }
        if (floor($number) === $number) {
            return number_format($number, 0);
        }
        return number_format($number, 4);
    }

    private function isCodeLikeColumn(string $name): bool
    {
        return str_starts_with($name, 'segment')
            || str_ends_with($name, 'code')
            || str_ends_with($name, 'id')
            || in_array($name, ['periodno', 'fiscalyearid', 'budgetversionid', 'versionid'], true);
    }
}
