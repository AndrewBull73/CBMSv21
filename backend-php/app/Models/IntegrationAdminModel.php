<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class IntegrationAdminModel
{
    public function __construct(private PDO $conn)
    {
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsIntegrationFoundation(): bool
    {
        return $this->tableExists('dbo.tblIntegrationSystem')
            && $this->tableExists('dbo.tblIntegrationInterface')
            && $this->tableExists('dbo.tblIntegrationRun');
    }

    public function listSystems(array $filters = []): array
    {
        if (!$this->tableExists('dbo.tblIntegrationSystem')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (($filters['active'] ?? '') !== '') {
            $where[] = 's.ActiveFlag = :active';
            $params[':active'] = ((string) $filters['active'] === '1') ? 1 : 0;
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(
                s.SystemCode LIKE :q1
                OR s.SystemName LIKE :q2
                OR s.BaseUrl LIKE :q3
                OR s.AuthType LIKE :q4
            )';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params[':q1'] = $needle;
            $params[':q2'] = $needle;
            $params[':q3'] = $needle;
            $params[':q4'] = $needle;
        }

        $sql = "
            SELECT
                s.*,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblIntegrationInterface i
                    WHERE i.IntegrationSystemID = s.IntegrationSystemID
                ) AS InterfaceCount
            FROM dbo.tblIntegrationSystem s
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.SystemName ASC, s.SystemCode ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSystem(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblIntegrationSystem')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblIntegrationSystem
            WHERE IntegrationSystemID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveSystem(array $data, int $userId, ?int $id = null): int
    {
        $this->requireTable('dbo.tblIntegrationSystem');

        $payload = [
            ':SystemCode' => trim((string) ($data['SystemCode'] ?? '')),
            ':SystemName' => trim((string) ($data['SystemName'] ?? '')),
            ':BaseUrl' => $this->nullableString($data['BaseUrl'] ?? null),
            ':AuthType' => $this->nullableString($data['AuthType'] ?? null),
            ':CredentialReference' => $this->nullableString($data['CredentialReference'] ?? null),
            ':DefaultHeadersJson' => $this->nullableJson($data['DefaultHeadersJson'] ?? null),
            ':EnvironmentCode' => $this->nullableString($data['EnvironmentCode'] ?? null),
            ':ActiveFlag' => ((int) ($data['ActiveFlag'] ?? 1) === 1) ? 1 : 0,
            ':Notes' => $this->nullableString($data['Notes'] ?? null),
            ':UserID' => $userId > 0 ? $userId : null,
        ];

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblIntegrationSystem
                SET SystemCode = :SystemCode,
                    SystemName = :SystemName,
                    BaseUrl = :BaseUrl,
                    AuthType = :AuthType,
                    CredentialReference = :CredentialReference,
                    DefaultHeadersJson = :DefaultHeadersJson,
                    EnvironmentCode = :EnvironmentCode,
                    ActiveFlag = :ActiveFlag,
                    Notes = :Notes,
                    UpdatedBy = :UserID,
                    UpdatedDate = SYSDATETIME()
                WHERE IntegrationSystemID = :ID
            ");
            $stmt->execute($payload + [':ID' => $id]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblIntegrationSystem (
                SystemCode,
                SystemName,
                BaseUrl,
                AuthType,
                CredentialReference,
                DefaultHeadersJson,
                EnvironmentCode,
                ActiveFlag,
                Notes,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :SystemCode,
                :SystemName,
                :BaseUrl,
                :AuthType,
                :CredentialReference,
                :DefaultHeadersJson,
                :EnvironmentCode,
                :ActiveFlag,
                :Notes,
                :UserID,
                SYSDATETIME()
            )
        ");
        $stmt->execute($payload);

        return (int) $this->conn->lastInsertId();
    }

    public function listInterfaces(array $filters = []): array
    {
        if (!$this->tableExists('dbo.tblIntegrationInterface')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (($filters['system_id'] ?? '') !== '') {
            $where[] = 'i.IntegrationSystemID = :systemId';
            $params[':systemId'] = (int) $filters['system_id'];
        }
        if (($filters['direction'] ?? '') !== '') {
            $where[] = 'i.DirectionCode = :direction';
            $params[':direction'] = trim((string) $filters['direction']);
        }
        if (($filters['active'] ?? '') !== '') {
            $where[] = 'i.ActiveFlag = :active';
            $params[':active'] = ((string) $filters['active'] === '1') ? 1 : 0;
        }
        if (($filters['q'] ?? '') !== '') {
            $where[] = '(
                i.InterfaceCode LIKE :q1
                OR i.InterfaceName LIKE :q2
                OR i.ModuleCode LIKE :q3
                OR i.EntityCode LIKE :q4
                OR s.SystemName LIKE :q5
                OR i.BusinessOwner LIKE :q6
                OR i.SourceOwner LIKE :q7
            )';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params[':q1'] = $needle;
            $params[':q2'] = $needle;
            $params[':q3'] = $needle;
            $params[':q4'] = $needle;
            $params[':q5'] = $needle;
            $params[':q6'] = $needle;
            $params[':q7'] = $needle;
        }

        $sql = "
            SELECT
                i.*,
                s.SystemCode,
                s.SystemName,
                (
                    SELECT TOP 1 r.RunStatusCode
                    FROM dbo.tblIntegrationRun r
                    WHERE r.IntegrationInterfaceID = i.IntegrationInterfaceID
                    ORDER BY r.StartedAt DESC, r.IntegrationRunID DESC
                ) AS LastRunStatusCode,
                (
                    SELECT TOP 1 r.StartedAt
                    FROM dbo.tblIntegrationRun r
                    WHERE r.IntegrationInterfaceID = i.IntegrationInterfaceID
                    ORDER BY r.StartedAt DESC, r.IntegrationRunID DESC
                ) AS LastRunStartedAt
            FROM dbo.tblIntegrationInterface i
            INNER JOIN dbo.tblIntegrationSystem s
                ON s.IntegrationSystemID = i.IntegrationSystemID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.SystemName ASC, i.InterfaceName ASC, i.InterfaceCode ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getInterface(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblIntegrationInterface')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblIntegrationInterface
            WHERE IntegrationInterfaceID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getInterfaceDefinition(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblIntegrationInterface') || !$this->tableExists('dbo.tblIntegrationSystem')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT
                i.*,
                s.SystemCode,
                s.SystemName,
                s.BaseUrl,
                s.AuthType,
                s.CredentialReference,
                s.DefaultHeadersJson,
                s.EnvironmentCode
            FROM dbo.tblIntegrationInterface i
            INNER JOIN dbo.tblIntegrationSystem s
                ON s.IntegrationSystemID = i.IntegrationSystemID
            WHERE i.IntegrationInterfaceID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveInterface(array $data, int $userId, ?int $id = null): int
    {
        $this->requireTable('dbo.tblIntegrationInterface');

        $payload = [
            ':InterfaceCode' => trim((string) ($data['InterfaceCode'] ?? '')),
            ':InterfaceName' => trim((string) ($data['InterfaceName'] ?? '')),
            ':IntegrationSystemID' => (int) ($data['IntegrationSystemID'] ?? 0),
            ':DirectionCode' => trim((string) ($data['DirectionCode'] ?? '')),
            ':ModuleCode' => $this->nullableString($data['ModuleCode'] ?? null),
            ':EntityCode' => $this->nullableString($data['EntityCode'] ?? null),
            ':TriggerMode' => trim((string) ($data['TriggerMode'] ?? 'manual')),
            ':ScheduleExpression' => $this->nullableString($data['ScheduleExpression'] ?? null),
            ':EndpointPath' => $this->nullableString($data['EndpointPath'] ?? null),
            ':HttpMethod' => $this->nullableString($data['HttpMethod'] ?? null),
            ':PayloadFormat' => $this->nullableString($data['PayloadFormat'] ?? null),
            ':ContextRequiredFlag' => ((int) ($data['ContextRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':FiscalYearRequiredFlag' => ((int) ($data['FiscalYearRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':VersionRequiredFlag' => ((int) ($data['VersionRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':DataScopeRequiredFlag' => ((int) ($data['DataScopeRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':BatchSize' => $this->nullableInt($data['BatchSize'] ?? null),
            ':TimeoutSeconds' => $this->nullableInt($data['TimeoutSeconds'] ?? null),
            ':MappingConfigJson' => $this->nullableJson($data['MappingConfigJson'] ?? null),
            ':OutputProfilesJson' => $this->nullableJson($data['OutputProfilesJson'] ?? null),
            ':DefaultOutputProfileCode' => $this->nullableString($data['DefaultOutputProfileCode'] ?? null),
            ':BusinessOwner' => $this->nullableString($data['BusinessOwner'] ?? null),
            ':SourceOwner' => $this->nullableString($data['SourceOwner'] ?? null),
            ':ApprovalStage' => $this->nullableString($data['ApprovalStage'] ?? null),
            ':ReadinessStatus' => $this->nullableString($data['ReadinessStatus'] ?? null),
            ':ActiveFlag' => ((int) ($data['ActiveFlag'] ?? 1) === 1) ? 1 : 0,
            ':Notes' => $this->nullableString($data['Notes'] ?? null),
            ':UserID' => $userId > 0 ? $userId : null,
        ];

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblIntegrationInterface
                SET InterfaceCode = :InterfaceCode,
                    InterfaceName = :InterfaceName,
                    IntegrationSystemID = :IntegrationSystemID,
                    DirectionCode = :DirectionCode,
                    ModuleCode = :ModuleCode,
                    EntityCode = :EntityCode,
                    TriggerMode = :TriggerMode,
                    ScheduleExpression = :ScheduleExpression,
                    EndpointPath = :EndpointPath,
                    HttpMethod = :HttpMethod,
                    PayloadFormat = :PayloadFormat,
                    ContextRequiredFlag = :ContextRequiredFlag,
                    FiscalYearRequiredFlag = :FiscalYearRequiredFlag,
                    VersionRequiredFlag = :VersionRequiredFlag,
                    DataScopeRequiredFlag = :DataScopeRequiredFlag,
                    BatchSize = :BatchSize,
                    TimeoutSeconds = :TimeoutSeconds,
                    MappingConfigJson = :MappingConfigJson,
                    OutputProfilesJson = :OutputProfilesJson,
                    DefaultOutputProfileCode = :DefaultOutputProfileCode,
                    BusinessOwner = :BusinessOwner,
                    SourceOwner = :SourceOwner,
                    ApprovalStage = :ApprovalStage,
                    ReadinessStatus = :ReadinessStatus,
                    ActiveFlag = :ActiveFlag,
                    Notes = :Notes,
                    UpdatedBy = :UserID,
                    UpdatedDate = SYSDATETIME()
                WHERE IntegrationInterfaceID = :ID
            ");
            $stmt->execute($payload + [':ID' => $id]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblIntegrationInterface (
                InterfaceCode,
                InterfaceName,
                IntegrationSystemID,
                DirectionCode,
                ModuleCode,
                EntityCode,
                TriggerMode,
                ScheduleExpression,
                EndpointPath,
                HttpMethod,
                PayloadFormat,
                ContextRequiredFlag,
                FiscalYearRequiredFlag,
                VersionRequiredFlag,
                DataScopeRequiredFlag,
                BatchSize,
                TimeoutSeconds,
                MappingConfigJson,
                OutputProfilesJson,
                DefaultOutputProfileCode,
                BusinessOwner,
                SourceOwner,
                ApprovalStage,
                ReadinessStatus,
                ActiveFlag,
                Notes,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :InterfaceCode,
                :InterfaceName,
                :IntegrationSystemID,
                :DirectionCode,
                :ModuleCode,
                :EntityCode,
                :TriggerMode,
                :ScheduleExpression,
                :EndpointPath,
                :HttpMethod,
                :PayloadFormat,
                :ContextRequiredFlag,
                :FiscalYearRequiredFlag,
                :VersionRequiredFlag,
                :DataScopeRequiredFlag,
                :BatchSize,
                :TimeoutSeconds,
                :MappingConfigJson,
                :OutputProfilesJson,
                :DefaultOutputProfileCode,
                :BusinessOwner,
                :SourceOwner,
                :ApprovalStage,
                :ReadinessStatus,
                :ActiveFlag,
                :Notes,
                :UserID,
                SYSDATETIME()
            )
        ");
        $stmt->execute($payload);

        return (int) $this->conn->lastInsertId();
    }

    public function listSystemOptions(bool $activeOnly = true): array
    {
        if (!$this->tableExists('dbo.tblIntegrationSystem')) {
            return [];
        }

        $sql = "
            SELECT IntegrationSystemID, SystemCode, SystemName
            FROM dbo.tblIntegrationSystem
            " . ($activeOnly ? "WHERE ActiveFlag = 1" : "") . "
            ORDER BY SystemName ASC, SystemCode ASC
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listRuns(array $filters = []): array
    {
        if (!$this->tableExists('dbo.tblIntegrationRun')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (($filters['system_id'] ?? '') !== '') {
            $where[] = 'i.IntegrationSystemID = :systemId';
            $params[':systemId'] = (int) $filters['system_id'];
        }
        if (($filters['interface_id'] ?? '') !== '') {
            $where[] = 'r.IntegrationInterfaceID = :interfaceId';
            $params[':interfaceId'] = (int) $filters['interface_id'];
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'r.RunStatusCode = :status';
            $params[':status'] = trim((string) $filters['status']);
        }

        $sql = "
            SELECT TOP 250
                r.*,
                i.InterfaceCode,
                i.InterfaceName,
                i.DirectionCode,
                i.ModuleCode,
                i.EntityCode,
                s.SystemCode,
                s.SystemName,
                u.Username,
                u.DisplayName
            FROM dbo.tblIntegrationRun r
            INNER JOIN dbo.tblIntegrationInterface i
                ON i.IntegrationInterfaceID = r.IntegrationInterfaceID
            INNER JOIN dbo.tblIntegrationSystem s
                ON s.IntegrationSystemID = i.IntegrationSystemID
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = r.TriggeredByUserID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.StartedAt DESC, r.IntegrationRunID DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listInterfaceOptions(bool $activeOnly = false): array
    {
        if (!$this->tableExists('dbo.tblIntegrationInterface')) {
            return [];
        }

        $sql = "
            SELECT
                i.IntegrationInterfaceID,
                i.InterfaceCode,
                i.InterfaceName,
                i.DirectionCode,
                s.SystemCode,
                s.SystemName
            FROM dbo.tblIntegrationInterface i
            INNER JOIN dbo.tblIntegrationSystem s
                ON s.IntegrationSystemID = i.IntegrationSystemID
            " . ($activeOnly ? "WHERE i.ActiveFlag = 1" : "") . "
            ORDER BY s.SystemName ASC, i.InterfaceName ASC, i.InterfaceCode ASC
        ";

        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function startRun(array $data): int
    {
        $this->requireTable('dbo.tblIntegrationRun');

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblIntegrationRun (
                IntegrationInterfaceID,
                RunStatusCode,
                TriggerSourceCode,
                TriggeredByUserID,
                RequestCorrelationID,
                FiscalYearID,
                VersionID,
                DataObjectCode,
                StartedAt,
                SummaryText,
                RequestPayloadJson,
                CreatedDate
            )
            VALUES (
                :IntegrationInterfaceID,
                :RunStatusCode,
                :TriggerSourceCode,
                :TriggeredByUserID,
                :RequestCorrelationID,
                :FiscalYearID,
                :VersionID,
                :DataObjectCode,
                SYSDATETIME(),
                :SummaryText,
                :RequestPayloadJson,
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':IntegrationInterfaceID' => (int) ($data['IntegrationInterfaceID'] ?? 0),
            ':RunStatusCode' => trim((string) ($data['RunStatusCode'] ?? 'running')),
            ':TriggerSourceCode' => $this->nullableString($data['TriggerSourceCode'] ?? 'manual'),
            ':TriggeredByUserID' => $this->nullableInt($data['TriggeredByUserID'] ?? null),
            ':RequestCorrelationID' => $this->nullableString($data['RequestCorrelationID'] ?? null),
            ':FiscalYearID' => $this->nullableInt($data['FiscalYearID'] ?? null),
            ':VersionID' => $this->nullableInt($data['VersionID'] ?? null),
            ':DataObjectCode' => $this->nullableString($data['DataObjectCode'] ?? null),
            ':SummaryText' => $this->nullableString($data['SummaryText'] ?? null),
            ':RequestPayloadJson' => $this->nullableJson($data['RequestPayloadJson'] ?? null),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function completeRun(int $runId, array $data): void
    {
        $this->requireTable('dbo.tblIntegrationRun');
        if ($runId <= 0) {
            throw new \RuntimeException('Integration run ID is required.');
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblIntegrationRun
            SET RunStatusCode = :RunStatusCode,
                CompletedAt = SYSDATETIME(),
                DurationSeconds = DATEDIFF(SECOND, StartedAt, SYSDATETIME()),
                RecordsReceived = :RecordsReceived,
                RecordsProcessed = :RecordsProcessed,
                RecordsCreated = :RecordsCreated,
                RecordsUpdated = :RecordsUpdated,
                RecordsSkipped = :RecordsSkipped,
                RecordsFailed = :RecordsFailed,
                SummaryText = :SummaryText,
                ErrorText = :ErrorText,
                ResponsePayloadJson = :ResponsePayloadJson
            WHERE IntegrationRunID = :IntegrationRunID
        ");
        $stmt->execute([
            ':IntegrationRunID' => $runId,
            ':RunStatusCode' => trim((string) ($data['RunStatusCode'] ?? 'success')),
            ':RecordsReceived' => $this->nullableInt($data['RecordsReceived'] ?? null),
            ':RecordsProcessed' => $this->nullableInt($data['RecordsProcessed'] ?? null),
            ':RecordsCreated' => $this->nullableInt($data['RecordsCreated'] ?? null),
            ':RecordsUpdated' => $this->nullableInt($data['RecordsUpdated'] ?? null),
            ':RecordsSkipped' => $this->nullableInt($data['RecordsSkipped'] ?? null),
            ':RecordsFailed' => $this->nullableInt($data['RecordsFailed'] ?? null),
            ':SummaryText' => $this->nullableString($data['SummaryText'] ?? null),
            ':ErrorText' => $this->nullableString($data['ErrorText'] ?? null),
            ':ResponsePayloadJson' => $this->nullableJson($data['ResponsePayloadJson'] ?? null),
        ]);
    }

    public function previewSourceRows(array $mappingConfig, array $context = [], ?int $limit = 200): array
    {
        $sourceType = strtolower(trim((string) ($mappingConfig['source_type'] ?? '')));
        if ($sourceType !== 'sql_view' && $sourceType !== 'sql_table') {
            throw new \RuntimeException('Only sql_view or sql_table source types are supported for the test export runner.');
        }

        $sourceObject = trim((string) ($mappingConfig['source_object'] ?? ''));
        if ($sourceObject === '') {
            throw new \RuntimeException('Mapping configuration must define source_object.');
        }

        $fieldMap = is_array($mappingConfig['field_map'] ?? null) ? $mappingConfig['field_map'] : [];
        if ($fieldMap === []) {
            throw new \RuntimeException('Mapping configuration must define a field_map.');
        }

        $columns = [];
        foreach ($fieldMap as $targetField => $sourceColumn) {
            $sourceColumn = trim((string) $sourceColumn);
            if ($sourceColumn === '') {
                continue;
            }
            $this->assertSimpleIdentifier($sourceColumn, 'field_map column');
            $columns[$sourceColumn] = $sourceColumn;
        }

        $contextFilters = is_array($mappingConfig['context_filters'] ?? null) ? $mappingConfig['context_filters'] : [];
        foreach ($contextFilters as $contextKey => $sourceColumn) {
            $sourceColumn = trim((string) $sourceColumn);
            if ($sourceColumn === '') {
                continue;
            }
            $this->assertSimpleIdentifier($sourceColumn, 'context_filters column');
            $columns[$sourceColumn] = $sourceColumn;
        }

        $amountStructure = is_array($mappingConfig['amount_structure'] ?? null) ? $mappingConfig['amount_structure'] : [];
        foreach (is_array($amountStructure['monthly_fields'] ?? null) ? $amountStructure['monthly_fields'] : [] as $sourceColumn) {
            $sourceColumn = trim((string) $sourceColumn);
            if ($sourceColumn === '') {
                continue;
            }
            $this->assertSimpleIdentifier($sourceColumn, 'amount_structure monthly field');
            $columns[$sourceColumn] = $sourceColumn;
        }
        $totalField = trim((string) ($amountStructure['total_field'] ?? ''));
        if ($totalField !== '') {
            $this->assertSimpleIdentifier($totalField, 'amount_structure total field');
            $columns[$totalField] = $totalField;
        }

        if ($columns === []) {
            throw new \RuntimeException('No valid source columns were found in mapping configuration.');
        }

        $topClause = '';
        if ($limit !== null) {
            $limit = max(1, min(5000, $limit));
            $topClause = 'TOP ' . $limit . ' ';
        }
        $selectList = implode(', ', array_map(
            fn (string $column): string => $this->quoteIdentifier($column),
            array_values($columns)
        ));

        $filters = [];
        $params = [];
        foreach ($contextFilters as $contextKey => $sourceColumn) {
            $sourceColumn = trim((string) $sourceColumn);
            $contextValue = $context[$contextKey] ?? null;
            if ($contextValue === null || trim((string) $contextValue) === '') {
                continue;
            }
            if ($contextKey === 'data_scope') {
                $scopeCodes = $this->resolveScopeFilterValues(
                    (int) ($context['fiscal_year'] ?? 0),
                    trim((string) $contextValue)
                );
                if ($scopeCodes !== []) {
                    $scopeParams = [];
                    foreach (array_values($scopeCodes) as $index => $scopeCode) {
                        $paramKey = ':ctx_data_scope_' . $index;
                        $scopeParams[] = $paramKey;
                        $params[$paramKey] = $scopeCode;
                    }
                    $filters[] = $this->quoteIdentifier($sourceColumn) . ' IN (' . implode(', ', $scopeParams) . ')';
                    continue;
                }
            }

            $paramKey = ':ctx_' . preg_replace('/[^a-z0-9_]+/i', '_', (string) $contextKey);
            $filters[] = $this->quoteIdentifier($sourceColumn) . ' = ' . $paramKey;
            $params[$paramKey] = $contextValue;
        }

        $fixedFilters = is_array($mappingConfig['filters'] ?? null) ? $mappingConfig['filters'] : [];
        foreach ($fixedFilters as $column => $value) {
            $column = trim((string) $column);
            if ($column === '') {
                continue;
            }
            $this->assertSimpleIdentifier($column, 'filters column');
            $paramKey = ':flt_' . preg_replace('/[^a-z0-9_]+/i', '_', $column);
            $filters[] = $this->quoteIdentifier($column) . ' = ' . $paramKey;
            $params[$paramKey] = $value;
        }

        $sql = "
            SELECT " . $topClause . $selectList . "
            FROM " . $this->quoteQualifiedObject($sourceObject) . "
            " . ($filters !== [] ? ('WHERE ' . implode(' AND ', $filters)) : '') . "
        ";

        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function resolveScopeFilterValues(int $fiscalYearId, string $scopeCode): array
    {
        $scopeCode = trim($scopeCode);
        if ($fiscalYearId <= 0 || $scopeCode === '') {
            return $scopeCode !== '' ? [$scopeCode] : [];
        }

        if (!$this->tableExists('dbo.tblDataObjectTree')) {
            return [$scopeCode];
        }

        $stmt = $this->conn->prepare("
            SELECT DescendantCode
            FROM dbo.tblDataObjectTree
            WHERE FiscalYearID = :fy
              AND AncestorCode = :scopeCode
            ORDER BY DescendantCode ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':scopeCode' => $scopeCode,
        ]);

        $codes = array_values(array_filter(
            array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []),
            static fn (string $code): bool => trim($code) !== ''
        ));

        if ($codes === []) {
            return [$scopeCode];
        }

        if (!in_array($scopeCode, $codes, true)) {
            array_unshift($codes, $scopeCode);
        }

        return array_values(array_unique($codes));
    }

    public function listRecentRunsForInterface(int $interfaceId, int $limit = 10): array
    {
        if ($interfaceId <= 0 || !$this->tableExists('dbo.tblIntegrationRun')) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $stmt = $this->conn->prepare("
            SELECT TOP " . $limit . "
                r.*,
                u.Username,
                u.DisplayName
            FROM dbo.tblIntegrationRun r
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = r.TriggeredByUserID
            WHERE r.IntegrationInterfaceID = :interfaceId
            ORDER BY r.StartedAt DESC, r.IntegrationRunID DESC
        ");
        $stmt->execute([':interfaceId' => $interfaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRunDetail(int $runId): ?array
    {
        if ($runId <= 0 || !$this->tableExists('dbo.tblIntegrationRun')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT
                r.*,
                i.InterfaceCode,
                i.InterfaceName,
                i.DirectionCode,
                i.ModuleCode,
                i.EntityCode,
                i.PayloadFormat,
                i.BusinessOwner,
                i.SourceOwner,
                i.ApprovalStage,
                i.ReadinessStatus,
                i.DefaultOutputProfileCode,
                i.OutputProfilesJson,
                s.SystemCode,
                s.SystemName,
                s.EnvironmentCode,
                u.Username,
                u.DisplayName
            FROM dbo.tblIntegrationRun r
            INNER JOIN dbo.tblIntegrationInterface i
                ON i.IntegrationInterfaceID = r.IntegrationInterfaceID
            INNER JOIN dbo.tblIntegrationSystem s
                ON s.IntegrationSystemID = i.IntegrationSystemID
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = r.TriggeredByUserID
            WHERE r.IntegrationRunID = :runId
        ");
        $stmt->execute([':runId' => $runId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->conn->prepare("SELECT OBJECT_ID(:tableName, 'U')");
        $stmt->execute([':tableName' => $tableName]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function requireTable(string $tableName): void
    {
        if (!$this->tableExists($tableName)) {
            throw new \RuntimeException($tableName . ' is not installed.');
        }
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
        return $text !== '' ? $text : null;
    }

    private function assertSimpleIdentifier(string $identifier, string $label): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \RuntimeException($label . ' "' . $identifier . '" is not a valid SQL identifier.');
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        $this->assertSimpleIdentifier($identifier, 'identifier');
        return '[' . $identifier . ']';
    }

    private function quoteQualifiedObject(string $objectName): string
    {
        $parts = array_values(array_filter(array_map('trim', explode('.', $objectName)), static fn (string $part): bool => $part !== ''));
        if (count($parts) !== 2) {
            throw new \RuntimeException('source_object must be provided as schema.object.');
        }

        return $this->quoteIdentifier($parts[0]) . '.' . $this->quoteIdentifier($parts[1]);
    }
}
