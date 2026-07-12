<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class AIDatasetAnalysisModel
{
    public function __construct(private PDO $conn)
    {
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsDatasetAnalysis(): bool
    {
        return $this->tableExists('dbo.tblAnalysisDatasets')
            && $this->tableExists('dbo.tblAnalysisDatasetColumns')
            && $this->tableExists('dbo.tblAnalysisDatasetQueries');
    }

    public function summary(): array
    {
        if (!$this->supportsDatasetAnalysis()) {
            return ['dataset_count' => 0, 'active_dataset_count' => 0, 'query_count_7d' => 0, 'latest_query_at' => null];
        }

        $dataset = $this->conn->query("
            SELECT COUNT(1) AS DatasetCount, SUM(CASE WHEN IsActive = 1 THEN 1 ELSE 0 END) AS ActiveDatasetCount
            FROM dbo.tblAnalysisDatasets
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $query = $this->conn->query("
            SELECT COUNT(1) AS QueryCount7d, MAX(CreatedDate) AS LatestQueryAt
            FROM dbo.tblAnalysisDatasetQueries
            WHERE CreatedDate >= DATEADD(DAY, -7, SYSUTCDATETIME())
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'dataset_count' => (int) ($dataset['DatasetCount'] ?? 0),
            'active_dataset_count' => (int) ($dataset['ActiveDatasetCount'] ?? 0),
            'query_count_7d' => (int) ($query['QueryCount7d'] ?? 0),
            'latest_query_at' => $query['LatestQueryAt'] ?? null,
        ];
    }

    public function listDatasets(bool $activeOnly = false): array
    {
        if (!$this->tableExists('dbo.tblAnalysisDatasets')) {
            return [];
        }

        $where = $activeOnly ? 'WHERE d.IsActive = 1' : '';
        $stmt = $this->conn->query("
            SELECT
                d.*,
                (SELECT COUNT(1) FROM dbo.tblAnalysisDatasetColumns c WHERE c.DatasetID = d.DatasetID AND c.IsActive = 1) AS ColumnCount
            FROM dbo.tblAnalysisDatasets d
            {$where}
            ORDER BY d.DatasetName ASC, d.DatasetID ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDataset(int $datasetId): ?array
    {
        if ($datasetId <= 0 || !$this->tableExists('dbo.tblAnalysisDatasets')) {
            return null;
        }

        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblAnalysisDatasets WHERE DatasetID = :id');
        $stmt->execute([':id' => $datasetId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listColumns(int $datasetId, bool $activeOnly = true): array
    {
        if ($datasetId <= 0 || !$this->tableExists('dbo.tblAnalysisDatasetColumns')) {
            return [];
        }

        $where = ['DatasetID = :id'];
        if ($activeOnly) {
            $where[] = 'IsActive = 1';
        }
        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblAnalysisDatasetColumns
            WHERE " . implode(' AND ', $where) . "
            ORDER BY DisplayOrder ASC, ColumnName ASC
        ");
        $stmt->execute([':id' => $datasetId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveDataset(array $data, int $userId): int
    {
        $this->requireFoundation();
        $datasetId = (int) ($data['DatasetID'] ?? 0);
        $sourceObject = $this->normaliseSourceObject((string) ($data['SourceObjectName'] ?? ''));
        if ($sourceObject === '') {
            throw new \InvalidArgumentException('Source object is required.');
        }
        if (!$this->sourceObjectExists($sourceObject)) {
            throw new \InvalidArgumentException('Source table or view was not found.');
        }

        $payload = [
            ':DatasetCode' => strtoupper(trim((string) ($data['DatasetCode'] ?? ''))),
            ':DatasetName' => trim((string) ($data['DatasetName'] ?? '')),
            ':Description' => $this->nullableString($data['Description'] ?? null),
            ':SourceObjectName' => $sourceObject,
            ':SourceType' => strtoupper(trim((string) ($data['SourceType'] ?? 'VIEW'))) === 'TABLE' ? 'TABLE' : 'VIEW',
            ':SensitivityLevel' => $this->normaliseSensitivity((string) ($data['SensitivityLevel'] ?? 'RESTRICTED')),
            ':AllowedPermissionCodes' => $this->normalisePermissionCsv((string) ($data['AllowedPermissionCodes'] ?? 'ANALYSIS_DATASET_ANALYZE')),
            ':DefaultFiscalYearColumn' => $this->nullableString($data['DefaultFiscalYearColumn'] ?? null),
            ':DefaultVersionColumn' => $this->nullableString($data['DefaultVersionColumn'] ?? null),
            ':MaxRows' => max(1, min(500, (int) ($data['MaxRows'] ?? 100))),
            ':RequireContext' => ((int) ($data['RequireContext'] ?? 1) === 1) ? 1 : 0,
            ':IsActive' => ((int) ($data['IsActive'] ?? 1) === 1) ? 1 : 0,
            ':Notes' => $this->nullableString($data['Notes'] ?? null),
        ];

        if ($payload[':DatasetCode'] === '' || $payload[':DatasetName'] === '') {
            throw new \InvalidArgumentException('Dataset code and name are required.');
        }

        if ($datasetId > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblAnalysisDatasets
                SET DatasetCode = :DatasetCode,
                    DatasetName = :DatasetName,
                    Description = :Description,
                    SourceObjectName = :SourceObjectName,
                    SourceType = :SourceType,
                    SensitivityLevel = :SensitivityLevel,
                    AllowedPermissionCodes = :AllowedPermissionCodes,
                    DefaultFiscalYearColumn = :DefaultFiscalYearColumn,
                    DefaultVersionColumn = :DefaultVersionColumn,
                    MaxRows = :MaxRows,
                    RequireContext = :RequireContext,
                    IsActive = :IsActive,
                    Notes = :Notes,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE DatasetID = :DatasetID
            ");
            $stmt->execute($payload + [':UpdatedBy' => $userId > 0 ? $userId : null, ':DatasetID' => $datasetId]);
            return $datasetId;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblAnalysisDatasets (
                DatasetCode, DatasetName, Description, SourceObjectName, SourceType, SensitivityLevel,
                AllowedPermissionCodes, DefaultFiscalYearColumn, DefaultVersionColumn, MaxRows,
                RequireContext, IsActive, CreatedBy, Notes
            )
            VALUES (
                :DatasetCode, :DatasetName, :Description, :SourceObjectName, :SourceType, :SensitivityLevel,
                :AllowedPermissionCodes, :DefaultFiscalYearColumn, :DefaultVersionColumn, :MaxRows,
                :RequireContext, :IsActive, :CreatedBy, :Notes
            )
        ");
        $stmt->execute($payload + [':CreatedBy' => $userId > 0 ? $userId : null]);
        return (int) $this->conn->lastInsertId();
    }

    public function importColumnsFromSource(int $datasetId): int
    {
        $dataset = $this->getDataset($datasetId);
        if ($dataset === null) {
            throw new \InvalidArgumentException('Dataset was not found.');
        }

        $columns = $this->introspectSourceColumns((string) $dataset['SourceObjectName']);
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("
                MERGE dbo.tblAnalysisDatasetColumns AS target
                USING (
                    SELECT
                        :DatasetID AS DatasetID,
                        :ColumnName AS ColumnName,
                        :DisplayName AS DisplayName,
                        :DataType AS DataType,
                        :SemanticType AS SemanticType,
                        :IsDimension AS IsDimension,
                        :IsMetric AS IsMetric,
                        :IsFilterable AS IsFilterable,
                        :DefaultAggregation AS DefaultAggregation,
                        :DisplayOrder AS DisplayOrder
                ) AS source
                ON target.DatasetID = source.DatasetID AND target.ColumnName = source.ColumnName
                WHEN MATCHED THEN
                    UPDATE SET
                        DisplayName = COALESCE(target.DisplayName, source.DisplayName),
                        DataType = source.DataType,
                        SemanticType = source.SemanticType,
                        IsDimension = source.IsDimension,
                        IsMetric = source.IsMetric,
                        IsFilterable = source.IsFilterable,
                        DefaultAggregation = source.DefaultAggregation,
                        DisplayOrder = source.DisplayOrder,
                        IsActive = 1
                WHEN NOT MATCHED THEN
                    INSERT (DatasetID, ColumnName, DisplayName, DataType, SemanticType, IsDimension, IsMetric, IsFilterable, DefaultAggregation, DisplayOrder, IsActive)
                    VALUES (source.DatasetID, source.ColumnName, source.DisplayName, source.DataType, source.SemanticType, source.IsDimension, source.IsMetric, source.IsFilterable, source.DefaultAggregation, source.DisplayOrder, 1);
            ");

            foreach ($columns as $index => $column) {
                $stmt->execute([
                    ':DatasetID' => $datasetId,
                    ':ColumnName' => $column['ColumnName'],
                    ':DisplayName' => $this->displayName((string) $column['ColumnName']),
                    ':DataType' => $column['DataType'],
                    ':SemanticType' => $column['SemanticType'],
                    ':IsDimension' => $column['IsDimension'],
                    ':IsMetric' => $column['IsMetric'],
                    ':IsFilterable' => 1,
                    ':DefaultAggregation' => $column['DefaultAggregation'],
                    ':DisplayOrder' => $index + 1,
                ]);
            }
            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }

        return count($columns);
    }

    public function updateColumnMetadata(int $datasetId, array $rows): void
    {
        $this->requireFoundation();
        if ($datasetId <= 0) {
            throw new \InvalidArgumentException('Dataset is required.');
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblAnalysisDatasetColumns
            SET DisplayName = :DisplayName,
                SemanticType = :SemanticType,
                IsDimension = :IsDimension,
                IsMetric = :IsMetric,
                IsFilterable = :IsFilterable,
                IsSensitive = :IsSensitive,
                DefaultAggregation = :DefaultAggregation,
                Description = :Description,
                IsActive = :IsActive
            WHERE DatasetID = :DatasetID
              AND DatasetColumnID = :DatasetColumnID
        ");

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $columnId = (int) ($row['DatasetColumnID'] ?? 0);
            if ($columnId <= 0) {
                continue;
            }
            $isMetric = (int) ($row['IsMetric'] ?? 0) === 1;
            $isDimension = (int) ($row['IsDimension'] ?? 0) === 1;
            $semantic = strtoupper(trim((string) ($row['SemanticType'] ?? ($isMetric ? 'METRIC' : 'DIMENSION'))));
            if (!in_array($semantic, ['DIMENSION', 'METRIC', 'DATE', 'IDENTIFIER'], true)) {
                $semantic = $isMetric ? 'METRIC' : 'DIMENSION';
            }
            $stmt->execute([
                ':DatasetID' => $datasetId,
                ':DatasetColumnID' => $columnId,
                ':DisplayName' => $this->nullableString($row['DisplayName'] ?? null),
                ':SemanticType' => $semantic,
                ':IsDimension' => $isDimension ? 1 : 0,
                ':IsMetric' => $isMetric ? 1 : 0,
                ':IsFilterable' => (int) ($row['IsFilterable'] ?? 0) === 1 ? 1 : 0,
                ':IsSensitive' => (int) ($row['IsSensitive'] ?? 0) === 1 ? 1 : 0,
                ':DefaultAggregation' => $isMetric ? $this->normaliseAggregation((string) ($row['DefaultAggregation'] ?? 'SUM')) : null,
                ':Description' => $this->nullableString($row['Description'] ?? null),
                ':IsActive' => (int) ($row['IsActive'] ?? 0) === 1 ? 1 : 0,
            ]);
        }
    }

    public function executeValidatedPlan(array $dataset, array $columns, array $plan, array $context): array
    {
        $columnMap = [];
        foreach ($columns as $column) {
            $columnMap[strtolower((string) $column['ColumnName'])] = $column;
        }

        $dimensions = [];
        foreach ((array) ($plan['dimensions'] ?? []) as $columnName) {
            $column = $this->validatedColumn((string) $columnName, $columnMap, 'dimension');
            if ($column !== null) {
                $dimensions[] = (string) $column['ColumnName'];
            }
        }
        $dimensions = array_values(array_unique(array_slice($dimensions, 0, 4)));

        $metrics = [];
        foreach ((array) ($plan['metrics'] ?? []) as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $column = $this->validatedColumn((string) ($metric['column'] ?? ''), $columnMap, 'metric');
            if ($column === null) {
                continue;
            }
            $aggregation = $this->normaliseAggregation((string) ($metric['aggregation'] ?? $column['DefaultAggregation'] ?? 'SUM'));
            $metrics[] = ['column' => (string) $column['ColumnName'], 'aggregation' => $aggregation];
        }
        if ($metrics === []) {
            $metrics[] = ['column' => '*', 'aggregation' => 'COUNT'];
        }
        $metrics = array_slice($metrics, 0, 5);

        $where = [];
        $params = [];
        $paramIndex = 0;

        $fyColumn = trim((string) ($dataset['DefaultFiscalYearColumn'] ?? ''));
        if ((int) ($dataset['RequireContext'] ?? 1) === 1 && $fyColumn !== '' && isset($columnMap[strtolower($fyColumn)]) && (int) ($context['FiscalYearID'] ?? 0) > 0) {
            $where[] = $this->quoteIdentifier($fyColumn) . ' = :ctxFy';
            $params[':ctxFy'] = (int) $context['FiscalYearID'];
        }
        $versionColumn = trim((string) ($dataset['DefaultVersionColumn'] ?? ''));
        if ((int) ($dataset['RequireContext'] ?? 1) === 1 && $versionColumn !== '' && isset($columnMap[strtolower($versionColumn)]) && (int) ($context['VersionID'] ?? 0) > 0) {
            $where[] = $this->quoteIdentifier($versionColumn) . ' = :ctxVer';
            $params[':ctxVer'] = (int) $context['VersionID'];
        }

        foreach ((array) ($plan['filters'] ?? []) as $filter) {
            if (!is_array($filter)) {
                continue;
            }
            $column = $this->validatedColumn((string) ($filter['column'] ?? ''), $columnMap, 'filter');
            if ($column === null) {
                continue;
            }
            $operator = strtolower((string) ($filter['operator'] ?? 'eq'));
            $value = $filter['value'] ?? null;
            $param = ':p' . (++$paramIndex);
            $quoted = $this->quoteIdentifier((string) $column['ColumnName']);
            if ($operator === 'contains') {
                $where[] = 'CAST(' . $quoted . ' AS NVARCHAR(MAX)) LIKE ' . $param;
                $params[$param] = '%' . (string) $value . '%';
            } elseif (in_array($operator, ['gte', 'lte', 'gt', 'lt'], true)) {
                $op = ['gte' => '>=', 'lte' => '<=', 'gt' => '>', 'lt' => '<'][$operator];
                $where[] = $quoted . ' ' . $op . ' ' . $param;
                $params[$param] = $value;
            } else {
                $where[] = $quoted . ' = ' . $param;
                $params[$param] = $value;
            }
        }

        $select = [];
        foreach ($dimensions as $dimension) {
            $select[] = $this->quoteIdentifier($dimension);
        }
        foreach ($metrics as $metric) {
            $alias = $metric['aggregation'] . '_' . ($metric['column'] === '*' ? 'Rows' : $metric['column']);
            $expr = $metric['column'] === '*'
                ? 'COUNT_BIG(1)'
                : $metric['aggregation'] . '(TRY_CONVERT(DECIMAL(38, 6), ' . $this->quoteIdentifier($metric['column']) . '))';
            if ($metric['aggregation'] === 'COUNT' && $metric['column'] !== '*') {
                $expr = 'COUNT(' . $this->quoteIdentifier($metric['column']) . ')';
            }
            $select[] = $expr . ' AS ' . $this->quoteIdentifier($alias);
        }
        if ($select === []) {
            $select[] = 'COUNT_BIG(1) AS [COUNT_Rows]';
        }

        $limit = max(1, min((int) ($dataset['MaxRows'] ?? 100), (int) ($plan['limit'] ?? 50), 500));
        $sql = 'SELECT TOP ' . $limit . ' ' . implode(', ', $select)
            . ' FROM ' . $this->quoteSourceObject((string) $dataset['SourceObjectName']);
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if ($dimensions !== []) {
            $grouped = array_map(fn (string $dimension): string => $this->quoteIdentifier($dimension), $dimensions);
            $sql .= ' GROUP BY ' . implode(', ', $grouped);
        }
        if ($metrics !== []) {
            $firstAlias = $metrics[0]['aggregation'] . '_' . ($metrics[0]['column'] === '*' ? 'Rows' : $metrics[0]['column']);
            $sql .= ' ORDER BY ' . $this->quoteIdentifier($firstAlias) . ' DESC';
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'sql' => $sql,
            'params' => $params,
            'rows' => $rows,
            'row_count' => count($rows),
            'dimensions' => $dimensions,
            'metrics' => $metrics,
            'metric_totals' => $this->metricTotals((string) $dataset['SourceObjectName'], $metrics, $where, $params),
        ];
    }

    private function metricTotals(string $sourceObjectName, array $metrics, array $where, array $params): array
    {
        $select = [];
        foreach ($metrics as $metric) {
            if (!is_array($metric)) {
                continue;
            }
            $aggregation = $this->normaliseAggregation((string) ($metric['aggregation'] ?? 'SUM'));
            $column = (string) ($metric['column'] ?? '');
            $alias = $aggregation . '_' . ($column === '*' ? 'Rows' : $column);
            if ($column === '*') {
                $select[] = 'COUNT_BIG(1) AS ' . $this->quoteIdentifier($alias);
                continue;
            }
            if ($aggregation === 'COUNT') {
                $select[] = 'COUNT(' . $this->quoteIdentifier($column) . ') AS ' . $this->quoteIdentifier($alias);
                continue;
            }
            $select[] = 'SUM(TRY_CONVERT(DECIMAL(38, 6), ' . $this->quoteIdentifier($column) . ')) AS ' . $this->quoteIdentifier($alias);
        }
        if ($select === []) {
            return [];
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . $this->quoteSourceObject($sourceObjectName);
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function logQuery(array $payload): int
    {
        $this->requireFoundation();
        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblAnalysisDatasetQueries (
                DatasetID, UserID, Question, AnalysisPlanJson, ExecutedSql, ParametersJson,
                ResponseSummary, [RowCount], ResponseTimeMs, ProviderCode, ModelCode,
                PromptTokens, CompletionTokens, TotalTokens, StatusCode, ErrorMessage
            )
            VALUES (
                :DatasetID, :UserID, :Question, :AnalysisPlanJson, :ExecutedSql, :ParametersJson,
                :ResponseSummary, :RowCount, :ResponseTimeMs, :ProviderCode, :ModelCode,
                :PromptTokens, :CompletionTokens, :TotalTokens, :StatusCode, :ErrorMessage
            )
        ");
        $stmt->execute([
            ':DatasetID' => $this->nullableInt($payload['DatasetID'] ?? null),
            ':UserID' => $this->nullableInt($payload['UserID'] ?? null),
            ':Question' => (string) ($payload['Question'] ?? ''),
            ':AnalysisPlanJson' => $this->nullableJson($payload['AnalysisPlanJson'] ?? null),
            ':ExecutedSql' => $this->nullableString($payload['ExecutedSql'] ?? null),
            ':ParametersJson' => $this->nullableJson($payload['ParametersJson'] ?? null),
            ':ResponseSummary' => $this->nullableString($payload['ResponseSummary'] ?? null),
            ':RowCount' => $this->nullableInt($payload['RowCount'] ?? null),
            ':ResponseTimeMs' => $this->nullableInt($payload['ResponseTimeMs'] ?? null),
            ':ProviderCode' => $this->nullableString($payload['ProviderCode'] ?? null),
            ':ModelCode' => $this->nullableString($payload['ModelCode'] ?? null),
            ':PromptTokens' => $this->nullableInt($payload['PromptTokens'] ?? null),
            ':CompletionTokens' => $this->nullableInt($payload['CompletionTokens'] ?? null),
            ':TotalTokens' => $this->nullableInt($payload['TotalTokens'] ?? null),
            ':StatusCode' => $this->nullableString($payload['StatusCode'] ?? 'SUCCESS'),
            ':ErrorMessage' => $this->nullableString($payload['ErrorMessage'] ?? null),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function recentQueries(int $limit = 50): array
    {
        if (!$this->tableExists('dbo.tblAnalysisDatasetQueries')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->conn->query("
            SELECT TOP {$limit} q.*, d.DatasetName, d.DatasetCode
            FROM dbo.tblAnalysisDatasetQueries q
            LEFT JOIN dbo.tblAnalysisDatasets d ON d.DatasetID = q.DatasetID
            ORDER BY q.CreatedDate DESC, q.DatasetQueryID DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sourceObjectExists(string $sourceObject): bool
    {
        $sourceObject = $this->normaliseSourceObject($sourceObject);
        if ($sourceObject === '') {
            return false;
        }
        $stmt = $this->conn->prepare("SELECT OBJECT_ID(:objectName, 'U') UNION SELECT OBJECT_ID(:objectNameView, 'V')");
        $stmt->execute([':objectName' => $sourceObject, ':objectNameView' => $sourceObject]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $value) {
            if ((int) $value > 0) {
                return true;
            }
        }
        return false;
    }

    private function introspectSourceColumns(string $sourceObject): array
    {
        [$schema, $object] = $this->splitSourceObject($sourceObject);
        $stmt = $this->conn->prepare("
            SELECT
                c.name AS ColumnName,
                t.name AS DataType,
                c.column_id AS ColumnOrder
            FROM sys.columns c
            INNER JOIN sys.types t ON t.user_type_id = c.user_type_id
            INNER JOIN sys.objects o ON o.object_id = c.object_id
            INNER JOIN sys.schemas s ON s.schema_id = o.schema_id
            WHERE s.name = :schemaName
              AND o.name = :objectName
            ORDER BY c.column_id ASC
        ");
        $stmt->execute([':schemaName' => $schema, ':objectName' => $object]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $dataType = strtolower((string) ($row['DataType'] ?? ''));
            $isMetric = in_array($dataType, ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'money', 'smallmoney', 'float', 'real'], true);
            $isDate = in_array($dataType, ['date', 'datetime', 'datetime2', 'smalldatetime'], true);
            $rows[] = [
                'ColumnName' => (string) $row['ColumnName'],
                'DataType' => $dataType,
                'SemanticType' => $isDate ? 'DATE' : ($isMetric ? 'METRIC' : 'DIMENSION'),
                'IsDimension' => $isMetric ? 0 : 1,
                'IsMetric' => $isMetric ? 1 : 0,
                'DefaultAggregation' => $isMetric ? 'SUM' : null,
            ];
        }
        return $rows;
    }

    private function validatedColumn(string $name, array $columnMap, string $usage): ?array
    {
        $key = strtolower(trim($name));
        if ($key === '' || !isset($columnMap[$key])) {
            return null;
        }
        $column = $columnMap[$key];
        if ((int) ($column['IsActive'] ?? 1) !== 1) {
            return null;
        }
        if ($usage === 'dimension' && (int) ($column['IsDimension'] ?? 0) !== 1) {
            return null;
        }
        if ($usage === 'metric' && (int) ($column['IsMetric'] ?? 0) !== 1) {
            return null;
        }
        if ($usage === 'filter' && (int) ($column['IsFilterable'] ?? 0) !== 1) {
            return null;
        }
        if ((int) ($column['IsSensitive'] ?? 0) === 1 && $usage === 'dimension') {
            return null;
        }
        return $column;
    }

    private function normaliseSourceObject(string $sourceObject): string
    {
        $sourceObject = trim($sourceObject);
        if ($sourceObject === '') {
            return '';
        }
        if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\.)?[A-Za-z_][A-Za-z0-9_]*$/', $sourceObject)) {
            return '';
        }
        return str_contains($sourceObject, '.') ? $sourceObject : 'dbo.' . $sourceObject;
    }

    private function splitSourceObject(string $sourceObject): array
    {
        $sourceObject = $this->normaliseSourceObject($sourceObject);
        $parts = explode('.', $sourceObject, 2);
        return [$parts[0] ?? 'dbo', $parts[1] ?? ''];
    }

    private function quoteSourceObject(string $sourceObject): string
    {
        [$schema, $object] = $this->splitSourceObject($sourceObject);
        return $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($object);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }

    private function normaliseAggregation(string $aggregation): string
    {
        $aggregation = strtoupper(trim($aggregation));
        return in_array($aggregation, ['SUM', 'AVG', 'MIN', 'MAX', 'COUNT'], true) ? $aggregation : 'SUM';
    }

    private function normaliseSensitivity(string $sensitivity): string
    {
        $sensitivity = strtoupper(trim($sensitivity));
        return in_array($sensitivity, ['INTERNAL', 'RESTRICTED', 'CONFIDENTIAL', 'EXECUTIVE'], true) ? $sensitivity : 'RESTRICTED';
    }

    private function normalisePermissionCsv(string $csv): ?string
    {
        $codes = [];
        foreach (preg_split('/[,\s]+/', strtoupper($csv)) ?: [] as $code) {
            $code = trim($code);
            if ($code !== '' && preg_match('/^[A-Z0-9_]+$/', $code)) {
                $codes[$code] = true;
            }
        }
        return $codes !== [] ? implode(',', array_keys($codes)) : 'ANALYSIS_DATASET_ANALYZE';
    }

    private function displayName(string $columnName): string
    {
        $name = preg_replace('/(?<!^)([A-Z])/', ' $1', $columnName) ?? $columnName;
        return trim(str_replace(['_', '-'], ' ', $name));
    }

    private function requireFoundation(): void
    {
        if (!$this->supportsDatasetAnalysis()) {
            throw new \RuntimeException('Analysis dataset schema is not installed.');
        }
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->conn->prepare("SELECT OBJECT_ID(:tableName, 'U')");
        $stmt->execute([':tableName' => $tableName]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return (int) $value;
    }

    private function nullableJson(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        json_decode($text, true);
        return json_last_error() === JSON_ERROR_NONE ? $text : null;
    }
}
