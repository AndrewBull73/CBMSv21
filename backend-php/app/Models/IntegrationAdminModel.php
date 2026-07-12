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

    public function supportsIntegrationMappings(): bool
    {
        return $this->tableExists('dbo.tblIntegrationCodeCrosswalk');
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

    public function dashboardSummary(): array
    {
        $empty = [
            'system_count' => 0,
            'active_system_count' => 0,
            'interface_count' => 0,
            'active_interface_count' => 0,
            'ready_interface_count' => 0,
            'run_count_24h' => 0,
            'success_run_count_24h' => 0,
            'failed_run_count_24h' => 0,
            'latest_run_at' => null,
        ];

        if (!$this->supportsIntegrationFoundation()) {
            return $empty;
        }

        $systemRow = $this->conn->query("
            SELECT
                COUNT(1) AS SystemCount,
                SUM(CASE WHEN ActiveFlag = 1 THEN 1 ELSE 0 END) AS ActiveSystemCount
            FROM dbo.tblIntegrationSystem
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $interfaceRow = $this->conn->query("
            SELECT
                COUNT(1) AS InterfaceCount,
                SUM(CASE WHEN ActiveFlag = 1 THEN 1 ELSE 0 END) AS ActiveInterfaceCount,
                SUM(CASE WHEN ActiveFlag = 1 AND UPPER(ISNULL(ReadinessStatus, N'')) IN (N'READY', N'TEST_READY', N'UAT_READY', N'PRODUCTION_READY', N'APPROVED', N'LIVE') THEN 1 ELSE 0 END) AS ReadyInterfaceCount
            FROM dbo.tblIntegrationInterface
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        $runRow = $this->conn->query("
            SELECT
                COUNT(1) AS RunCount24h,
                SUM(CASE WHEN RunStatusCode IN (N'success', N'completed') THEN 1 ELSE 0 END) AS SuccessRunCount24h,
                SUM(CASE WHEN RunStatusCode IN (N'failed', N'error') THEN 1 ELSE 0 END) AS FailedRunCount24h,
                MAX(StartedAt) AS LatestRunAt
            FROM dbo.tblIntegrationRun
            WHERE StartedAt >= DATEADD(HOUR, -24, SYSDATETIME())
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'system_count' => (int) ($systemRow['SystemCount'] ?? 0),
            'active_system_count' => (int) ($systemRow['ActiveSystemCount'] ?? 0),
            'interface_count' => (int) ($interfaceRow['InterfaceCount'] ?? 0),
            'active_interface_count' => (int) ($interfaceRow['ActiveInterfaceCount'] ?? 0),
            'ready_interface_count' => (int) ($interfaceRow['ReadyInterfaceCount'] ?? 0),
            'run_count_24h' => (int) ($runRow['RunCount24h'] ?? 0),
            'success_run_count_24h' => (int) ($runRow['SuccessRunCount24h'] ?? 0),
            'failed_run_count_24h' => (int) ($runRow['FailedRunCount24h'] ?? 0),
            'latest_run_at' => $runRow['LatestRunAt'] ?? null,
        ];
    }

    public function listInterfaceReadinessSummary(): array
    {
        if (!$this->tableExists('dbo.tblIntegrationInterface')) {
            return [];
        }

        return $this->conn->query("
            SELECT
                ReadinessStatus = COALESCE(NULLIF(LTRIM(RTRIM(ReadinessStatus)), N''), N'Not Set'),
                COUNT(1) AS InterfaceCount
            FROM dbo.tblIntegrationInterface
            GROUP BY COALESCE(NULLIF(LTRIM(RTRIM(ReadinessStatus)), N''), N'Not Set')
            ORDER BY InterfaceCount DESC, ReadinessStatus ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    public function listCodeCrosswalks(array $filters = []): array
    {
        if (!$this->tableExists('dbo.tblIntegrationCodeCrosswalk')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if (($filters['system_id'] ?? '') !== '') {
            $where[] = 'cw.IntegrationSystemID = :systemId';
            $params[':systemId'] = (int) $filters['system_id'];
        }
        if (($filters['interface_id'] ?? '') !== '') {
            $where[] = 'cw.IntegrationInterfaceID = :interfaceId';
            $params[':interfaceId'] = (int) $filters['interface_id'];
        }
        if (($filters['mapping_type'] ?? '') !== '') {
            $where[] = 'cw.MappingTypeCode = :mappingType';
            $params[':mappingType'] = trim((string) $filters['mapping_type']);
        }
        if (($filters['active'] ?? '') !== '') {
            $where[] = 'cw.ActiveFlag = :active';
            $params[':active'] = ((string) $filters['active'] === '1') ? 1 : 0;
        }
        if (($filters['q'] ?? '') !== '') {
            $needle = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(
                cw.ExternalCode LIKE :q1
                OR cw.ExternalDescription LIKE :q2
                OR cw.CbmsCode LIKE :q3
                OR cw.CbmsDescription LIKE :q4
            )';
            $params[':q1'] = $needle;
            $params[':q2'] = $needle;
            $params[':q3'] = $needle;
            $params[':q4'] = $needle;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 500
                cw.*,
                s.SystemCode,
                s.SystemName,
                i.InterfaceCode,
                i.InterfaceName
            FROM dbo.tblIntegrationCodeCrosswalk cw
            INNER JOIN dbo.tblIntegrationSystem s
                ON s.IntegrationSystemID = cw.IntegrationSystemID
            LEFT JOIN dbo.tblIntegrationInterface i
                ON i.IntegrationInterfaceID = cw.IntegrationInterfaceID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY cw.MappingTypeCode ASC, s.SystemCode ASC, cw.ExternalCode ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveCodeCrosswalk(array $data, int $userId): int
    {
        $this->requireTable('dbo.tblIntegrationCodeCrosswalk');
        $id = (int) ($data['IntegrationCodeCrosswalkID'] ?? 0);
        $payload = [
            ':IntegrationSystemID' => (int) ($data['IntegrationSystemID'] ?? 0),
            ':IntegrationInterfaceID' => $this->nullableInt($data['IntegrationInterfaceID'] ?? null),
            ':MappingTypeCode' => trim((string) ($data['MappingTypeCode'] ?? '')),
            ':ExternalCode' => trim((string) ($data['ExternalCode'] ?? '')),
            ':ExternalDescription' => $this->nullableString($data['ExternalDescription'] ?? null),
            ':CbmsCode' => trim((string) ($data['CbmsCode'] ?? '')),
            ':CbmsDescription' => $this->nullableString($data['CbmsDescription'] ?? null),
            ':FiscalYearID' => $this->nullableInt($data['FiscalYearID'] ?? null),
            ':VersionID' => $this->nullableInt($data['VersionID'] ?? null),
            ':EffectiveFrom' => $this->nullableString($data['EffectiveFrom'] ?? null),
            ':EffectiveTo' => $this->nullableString($data['EffectiveTo'] ?? null),
            ':ActiveFlag' => ((int) ($data['ActiveFlag'] ?? 1) === 1) ? 1 : 0,
            ':Notes' => $this->nullableString($data['Notes'] ?? null),
            ':UserID' => $userId > 0 ? $userId : null,
        ];

        if ($id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblIntegrationCodeCrosswalk
                SET IntegrationSystemID = :IntegrationSystemID,
                    IntegrationInterfaceID = :IntegrationInterfaceID,
                    MappingTypeCode = :MappingTypeCode,
                    ExternalCode = :ExternalCode,
                    ExternalDescription = :ExternalDescription,
                    CbmsCode = :CbmsCode,
                    CbmsDescription = :CbmsDescription,
                    FiscalYearID = :FiscalYearID,
                    VersionID = :VersionID,
                    EffectiveFrom = :EffectiveFrom,
                    EffectiveTo = :EffectiveTo,
                    ActiveFlag = :ActiveFlag,
                    Notes = :Notes,
                    UpdatedBy = :UserID,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE IntegrationCodeCrosswalkID = :ID
            ");
            $stmt->execute($payload + [':ID' => $id]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblIntegrationCodeCrosswalk (
                IntegrationSystemID,
                IntegrationInterfaceID,
                MappingTypeCode,
                ExternalCode,
                ExternalDescription,
                CbmsCode,
                CbmsDescription,
                FiscalYearID,
                VersionID,
                EffectiveFrom,
                EffectiveTo,
                ActiveFlag,
                Notes,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :IntegrationSystemID,
                :IntegrationInterfaceID,
                :MappingTypeCode,
                :ExternalCode,
                :ExternalDescription,
                :CbmsCode,
                :CbmsDescription,
                :FiscalYearID,
                :VersionID,
                :EffectiveFrom,
                :EffectiveTo,
                :ActiveFlag,
                :Notes,
                :UserID,
                SYSUTCDATETIME()
            )
        ");
        $stmt->execute($payload);
        return (int) $this->conn->lastInsertId();
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

    public function supportsActualsImportStaging(): bool
    {
        return $this->tableExists('dbo.tblIntegrationActualsImportStaging')
            && $this->columnExists('dbo.tblIntegrationActualsImportStaging', 'PostingReadinessCode')
            && $this->columnExists('dbo.tblIntegrationActualsImportStaging', 'PostingReadinessMessage')
            && $this->columnExists('dbo.tblIntegrationActualsImportStaging', 'PostingTarget');
    }

    public function stageActualsImportRows(int $runId, array $records, string $correlationId, int $userId): int
    {
        $this->requireTable('dbo.tblIntegrationActualsImportStaging');
        if ($runId <= 0) {
            throw new \RuntimeException('Integration run ID is required before staging actuals.');
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblIntegrationActualsImportStaging (
                IntegrationRunID,
                TransactionReference,
                ExternalCorrelationID,
                FiscalYearID,
                VersionID,
                PeriodNo,
                PostingDate,
                DataObjectCode,
                ProgramCode,
                EconomicCode,
                SupplierName,
                ActualAmount,
                CurrencyCode,
                StagingStatusCode,
                SourcePayloadJson,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :IntegrationRunID,
                :TransactionReference,
                :ExternalCorrelationID,
                :FiscalYearID,
                :VersionID,
                :PeriodNo,
                :PostingDate,
                :DataObjectCode,
                :ProgramCode,
                :EconomicCode,
                :SupplierName,
                :ActualAmount,
                :CurrencyCode,
                N'staged',
                :SourcePayloadJson,
                :CreatedBy,
                SYSUTCDATETIME()
            )
        ");

        $created = 0;
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $reference = trim((string) ($record['transactionReference'] ?? ''));
            if ($reference === '') {
                continue;
            }

            $stmt->execute([
                ':IntegrationRunID' => $runId,
                ':TransactionReference' => $reference,
                ':ExternalCorrelationID' => $this->nullableString($correlationId),
                ':FiscalYearID' => $this->nullableInt($record['fiscalYear'] ?? null),
                ':VersionID' => $this->nullableInt($record['version'] ?? null),
                ':PeriodNo' => $this->nullableInt($record['period'] ?? null),
                ':PostingDate' => $this->nullableString($record['postingDate'] ?? null),
                ':DataObjectCode' => $this->nullableString($record['dataObjectCode'] ?? null),
                ':ProgramCode' => $this->nullableString($record['programCode'] ?? null),
                ':EconomicCode' => $this->nullableString($record['economicCode'] ?? null),
                ':SupplierName' => $this->nullableString($record['supplierName'] ?? null),
                ':ActualAmount' => is_numeric($record['actualAmount'] ?? null) ? (string) $record['actualAmount'] : null,
                ':CurrencyCode' => $this->nullableString($record['currencyCode'] ?? null),
                ':SourcePayloadJson' => json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':CreatedBy' => $userId > 0 ? $userId : null,
            ]);
            $created++;
        }

        return $created;
    }

    public function listActualsImportStagingForRun(int $runId): array
    {
        if ($runId <= 0 || !$this->tableExists('dbo.tblIntegrationActualsImportStaging')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                IntegrationActualsImportStagingID,
                TransactionReference,
                ExternalCorrelationID,
                FiscalYearID,
                VersionID,
                PeriodNo,
                PostingDate,
                DataObjectCode,
                ProgramCode,
                EconomicCode,
                SupplierName,
                ActualAmount,
                CurrencyCode,
                StagingStatusCode,
                ValidationMessage,
                PostingReadinessCode,
                PostingReadinessMessage,
                PostingTarget,
                CreatedDate
            FROM dbo.tblIntegrationActualsImportStaging
            WHERE IntegrationRunID = :runId
              AND ActiveFlag = 1
            ORDER BY IntegrationActualsImportStagingID ASC
        ");
        $stmt->execute([':runId' => $runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function actualsImportStagingSummary(): array
    {
        $empty = [
            'total_count' => 0,
            'staged_count' => 0,
            'validated_count' => 0,
            'rejected_count' => 0,
            'posted_count' => 0,
            'total_amount' => 0.0,
        ];

        if (!$this->tableExists('dbo.tblIntegrationActualsImportStaging')) {
            return $empty;
        }

        $row = $this->conn->query("
            SELECT
                COUNT(1) AS TotalCount,
                SUM(CASE WHEN StagingStatusCode = N'staged' THEN 1 ELSE 0 END) AS StagedCount,
                SUM(CASE WHEN StagingStatusCode = N'validated' THEN 1 ELSE 0 END) AS ValidatedCount,
                SUM(CASE WHEN StagingStatusCode = N'rejected' THEN 1 ELSE 0 END) AS RejectedCount,
                SUM(CASE WHEN StagingStatusCode = N'posted' THEN 1 ELSE 0 END) AS PostedCount,
                SUM(ISNULL(ActualAmount, 0)) AS TotalAmount
            FROM dbo.tblIntegrationActualsImportStaging
            WHERE ActiveFlag = 1
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_count' => (int) ($row['TotalCount'] ?? 0),
            'staged_count' => (int) ($row['StagedCount'] ?? 0),
            'validated_count' => (int) ($row['ValidatedCount'] ?? 0),
            'rejected_count' => (int) ($row['RejectedCount'] ?? 0),
            'posted_count' => (int) ($row['PostedCount'] ?? 0),
            'total_amount' => (float) ($row['TotalAmount'] ?? 0),
        ];
    }

    public function listActualsImportStaging(array $filters = []): array
    {
        if (!$this->tableExists('dbo.tblIntegrationActualsImportStaging')) {
            return [];
        }

        $where = ['stg.ActiveFlag = 1'];
        $params = [];

        if (($filters['run_id'] ?? '') !== '') {
            $where[] = 'stg.IntegrationRunID = :runId';
            $params[':runId'] = (int) $filters['run_id'];
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'stg.StagingStatusCode = :status';
            $params[':status'] = trim((string) $filters['status']);
        }
        if (($filters['fiscal_year'] ?? '') !== '') {
            $where[] = 'stg.FiscalYearID = :fy';
            $params[':fy'] = (int) $filters['fiscal_year'];
        }
        if (($filters['version'] ?? '') !== '') {
            $where[] = 'stg.VersionID = :versionId';
            $params[':versionId'] = (int) $filters['version'];
        }
        if (($filters['period'] ?? '') !== '') {
            $where[] = 'stg.PeriodNo = :periodNo';
            $params[':periodNo'] = (int) $filters['period'];
        }
        if (($filters['scope'] ?? '') !== '') {
            $where[] = 'stg.DataObjectCode = :scope';
            $params[':scope'] = trim((string) $filters['scope']);
        }
        if (($filters['q'] ?? '') !== '') {
            $needle = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(
                stg.TransactionReference LIKE :q1
                OR stg.ProgramCode LIKE :q2
                OR stg.EconomicCode LIKE :q3
                OR stg.SupplierName LIKE :q4
                OR stg.ExternalCorrelationID LIKE :q5
            )';
            $params[':q1'] = $needle;
            $params[':q2'] = $needle;
            $params[':q3'] = $needle;
            $params[':q4'] = $needle;
            $params[':q5'] = $needle;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 500
                stg.*,
                r.StartedAt,
                r.RunStatusCode,
                i.InterfaceCode,
                i.InterfaceName,
                s.IntegrationSystemID,
                s.SystemCode,
                s.SystemName
            FROM dbo.tblIntegrationActualsImportStaging stg
            INNER JOIN dbo.tblIntegrationRun r
                ON r.IntegrationRunID = stg.IntegrationRunID
            INNER JOIN dbo.tblIntegrationInterface i
                ON i.IntegrationInterfaceID = r.IntegrationInterfaceID
            INNER JOIN dbo.tblIntegrationSystem s
                ON s.IntegrationSystemID = i.IntegrationSystemID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY stg.CreatedDate DESC, stg.IntegrationActualsImportStagingID DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function checkActualsImportPostabilityRow(int $stagingId, int $userId): array
    {
        $this->requireTable('dbo.tblIntegrationActualsImportStaging');
        $row = $this->getActualsImportStagingRow($stagingId);
        if ($row === null) {
            throw new \RuntimeException('Actuals staging row not found.');
        }

        $result = $this->buildActualsImportPostabilityResult($row);
        $this->updateActualsImportPostability(
            $stagingId,
            $result['code'],
            $result['message'],
            $result['target'],
            $userId
        );

        return $result;
    }

    public function checkActualsImportPostabilityForFilters(array $filters, int $userId): array
    {
        $rows = $this->listActualsImportStaging($filters);
        $checked = 0;
        $ready = 0;
        $blocked = 0;

        foreach ($rows as $row) {
            $stagingId = (int) ($row['IntegrationActualsImportStagingID'] ?? 0);
            if ($stagingId <= 0) {
                continue;
            }
            $result = $this->buildActualsImportPostabilityResult($row);
            $this->updateActualsImportPostability(
                $stagingId,
                $result['code'],
                $result['message'],
                $result['target'],
                $userId
            );
            $checked++;
            if ($result['code'] === 'ready_to_post') {
                $ready++;
            } else {
                $blocked++;
            }
        }

        return ['checked' => $checked, 'ready' => $ready, 'blocked' => $blocked];
    }

    public function validateActualsImportStagingRow(int $stagingId, int $userId): array
    {
        $this->requireTable('dbo.tblIntegrationActualsImportStaging');
        $row = $this->getActualsImportStagingRow($stagingId);
        if ($row === null) {
            throw new \RuntimeException('Actuals staging row not found.');
        }
        if ((string) ($row['StagingStatusCode'] ?? '') === 'posted') {
            throw new \RuntimeException('Posted actuals import rows cannot be revalidated.');
        }

        $issues = $this->validateActualsImportStagingPayload($row);
        $status = $issues === [] ? 'validated' : 'rejected';
        $message = $issues === [] ? 'Validated and ready for posting.' : implode(' ', $issues);
        $this->updateActualsImportStagingStatus($stagingId, $status, $message, $userId);

        return ['status' => $status, 'message' => $message];
    }

    public function rejectActualsImportStagingRow(int $stagingId, string $message, int $userId): void
    {
        $this->requireTable('dbo.tblIntegrationActualsImportStaging');
        $row = $this->getActualsImportStagingRow($stagingId);
        if ($row === null) {
            throw new \RuntimeException('Actuals staging row not found.');
        }
        if ((string) ($row['StagingStatusCode'] ?? '') === 'posted') {
            throw new \RuntimeException('Posted actuals import rows cannot be rejected.');
        }

        $this->updateActualsImportStagingStatus(
            $stagingId,
            'rejected',
            trim($message) !== '' ? trim($message) : 'Rejected during import review.',
            $userId
        );
    }

    private function getActualsImportStagingRow(int $stagingId): ?array
    {
        if ($stagingId <= 0 || !$this->tableExists('dbo.tblIntegrationActualsImportStaging')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT
                stg.*,
                r.IntegrationInterfaceID,
                i.IntegrationSystemID
            FROM dbo.tblIntegrationActualsImportStaging stg
            INNER JOIN dbo.tblIntegrationRun r
                ON r.IntegrationRunID = stg.IntegrationRunID
            INNER JOIN dbo.tblIntegrationInterface i
                ON i.IntegrationInterfaceID = r.IntegrationInterfaceID
            WHERE stg.IntegrationActualsImportStagingID = :id
              AND stg.ActiveFlag = 1
        ");
        $stmt->execute([':id' => $stagingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function validateActualsImportStagingPayload(array $row): array
    {
        $issues = [];
        foreach ([
            'TransactionReference' => 'Transaction reference',
            'FiscalYearID' => 'Fiscal year',
            'VersionID' => 'Version',
            'PeriodNo' => 'Period',
            'ProgramCode' => 'Program code',
            'EconomicCode' => 'Economic code',
            'ActualAmount' => 'Actual amount',
        ] as $field => $label) {
            if (!array_key_exists($field, $row) || trim((string) $row[$field]) === '') {
                $issues[] = $label . ' is missing.';
            }
        }

        $period = (int) ($row['PeriodNo'] ?? 0);
        if ($period < 1 || $period > 12) {
            $issues[] = 'Period must be between 1 and 12.';
        }
        if (!is_numeric($row['ActualAmount'] ?? null)) {
            $issues[] = 'Actual amount must be numeric.';
        } elseif ((float) $row['ActualAmount'] === 0.0) {
            $issues[] = 'Actual amount cannot be zero.';
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(1)
            FROM dbo.tblIntegrationActualsImportStaging
            WHERE ActiveFlag = 1
              AND TransactionReference = :reference
              AND IntegrationActualsImportStagingID <> :id
              AND StagingStatusCode IN (N'staged', N'validated', N'posted')
        ");
        $stmt->execute([
            ':reference' => (string) ($row['TransactionReference'] ?? ''),
            ':id' => (int) ($row['IntegrationActualsImportStagingID'] ?? 0),
        ]);
        if ((int) ($stmt->fetchColumn() ?: 0) > 0) {
            $issues[] = 'Transaction reference already exists in active staging.';
        }

        return $issues;
    }

    private function updateActualsImportStagingStatus(int $stagingId, string $status, string $message, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblIntegrationActualsImportStaging
            SET StagingStatusCode = :status,
                ValidationMessage = :message,
                UpdatedBy = :userId,
                UpdatedDate = SYSUTCDATETIME()
            WHERE IntegrationActualsImportStagingID = :id
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':id' => $stagingId,
            ':status' => $status,
            ':message' => $this->nullableString($message),
            ':userId' => $userId > 0 ? $userId : null,
        ]);
    }

    private function buildActualsImportPostabilityResult(array $row): array
    {
        $issues = [];
        $notes = [];

        if ((string) ($row['StagingStatusCode'] ?? '') !== 'validated') {
            $issues[] = 'Row must be validated before it can be posted.';
        }

        $fy = (int) ($row['FiscalYearID'] ?? 0);
        $version = (int) ($row['VersionID'] ?? 0);
        $period = (int) ($row['PeriodNo'] ?? 0);
        $scope = trim((string) ($row['DataObjectCode'] ?? ''));
        $program = trim((string) ($row['ProgramCode'] ?? ''));
        $economic = trim((string) ($row['EconomicCode'] ?? ''));
        $systemId = (int) ($row['IntegrationSystemID'] ?? 0);
        $interfaceId = (int) ($row['IntegrationInterfaceID'] ?? 0);

        $scopeMap = $this->resolveIntegrationCodeCrosswalk($systemId, $interfaceId, 'data_object', $scope, $fy, $version);
        $programMap = $this->resolveIntegrationCodeCrosswalk($systemId, $interfaceId, 'program', $program, $fy, $version);
        $economicMap = $this->resolveIntegrationCodeCrosswalk($systemId, $interfaceId, 'economic', $economic, $fy, $version);
        $scopeForCheck = $scopeMap['target_code'];
        $programForCheck = $programMap['target_code'];
        $economicForCheck = $economicMap['target_code'];

        foreach ([
            'Scope' => $scopeMap,
            'Program' => $programMap,
            'Economic' => $economicMap,
        ] as $label => $map) {
            if ($map['external_code'] !== '' && $map['mapping_required'] && !$map['mapped']) {
                $issues[] = $label . ' code "' . $map['external_code'] . '" has no active integration crosswalk.';
            } elseif ($map['mapped']) {
                $notes[] = $label . ' mapped ' . $map['external_code'] . ' to ' . $map['target_code'] . '.';
            }
        }

        if ($fy <= 0) {
            $issues[] = 'Fiscal year is missing.';
        } elseif ($this->tableExists('dbo.tblFiscalYears') && !$this->existsByConditions('dbo.tblFiscalYears', ['FiscalYearID' => $fy])) {
            $issues[] = 'Fiscal year was not found in CBMS.';
        }

        if ($version <= 0) {
            $issues[] = 'Version is missing.';
        } elseif ($this->tableExists('dbo.tblVersions')) {
            $conditions = ['VersionID' => $version];
            if ($this->columnExists('dbo.tblVersions', 'FiscalYearID') && $fy > 0) {
                $conditions['FiscalYearID'] = $fy;
            }
            if (!$this->existsByConditions('dbo.tblVersions', $conditions)) {
                $issues[] = 'Version was not found for the fiscal context.';
            }
        }

        if ($period < 1 || $period > 12) {
            $issues[] = 'Period must be between 1 and 12.';
        }

        if ($scopeForCheck !== '' && $this->tableExists('dbo.tblDataObjectCodes')) {
            $conditions = ['DataObjectCode' => $scopeForCheck];
            if ($this->columnExists('dbo.tblDataObjectCodes', 'FiscalYearID') && $fy > 0) {
                $conditions['FiscalYearID'] = $fy;
            }
            if (!$this->existsByConditions('dbo.tblDataObjectCodes', $conditions)) {
                $issues[] = 'Scope/data object code is not configured in CBMS.';
            }
        }

        if ($programForCheck !== '' && $this->tableExists('dbo.tblSegmentValues')) {
            if (!$this->segmentValueExists($fy, $programForCheck)) {
                $issues[] = 'Program code is not configured as an active segment value.';
            }
        } elseif ($programForCheck !== '') {
            $notes[] = 'Program code reference table was not available for checking.';
        }

        if ($economicForCheck !== '' && $this->tableExists('dbo.tblSegmentValues')) {
            if (!$this->segmentValueExists($fy, $economicForCheck)) {
                $issues[] = 'Economic code is not configured as an active segment value.';
            }
        } elseif ($economicForCheck !== '') {
            $notes[] = 'Economic code reference table was not available for checking.';
        }

        if ($this->duplicatePostedOrReadyActualReferenceExists($row)) {
            $issues[] = 'Transaction reference is already ready or posted in staging.';
        }

        $target = 'Posting target not configured - staging review only';
        if ($issues === []) {
            $message = 'Ready to post once the final CBMS actuals posting target is configured.';
            if ($notes !== []) {
                $message .= ' ' . implode(' ', $notes);
            }
            return ['code' => 'ready_to_post', 'message' => $message, 'target' => $target];
        }

        return [
            'code' => 'blocked',
            'message' => implode(' ', array_merge($issues, $notes)),
            'target' => $target,
        ];
    }

    private function resolveIntegrationCodeCrosswalk(int $systemId, int $interfaceId, string $mappingType, string $externalCode, int $fiscalYearId, int $versionId): array
    {
        $externalCode = trim($externalCode);
        if ($externalCode === '' || !$this->tableExists('dbo.tblIntegrationCodeCrosswalk') || $systemId <= 0) {
            return [
                'external_code' => $externalCode,
                'target_code' => $externalCode,
                'mapped' => false,
                'mapping_required' => false,
            ];
        }

        $mappingExists = $this->integrationCrosswalkTypeExists($systemId, $interfaceId, $mappingType);
        $stmt = $this->conn->prepare("
            SELECT TOP 1 CbmsCode
            FROM dbo.tblIntegrationCodeCrosswalk
            WHERE IntegrationSystemID = :systemId
              AND MappingTypeCode = :mappingType
              AND ExternalCode = :externalCode
              AND ActiveFlag = 1
              AND (IntegrationInterfaceID = :interfaceId OR IntegrationInterfaceID IS NULL)
              AND (FiscalYearID = :fy OR FiscalYearID IS NULL)
              AND (VersionID = :versionId OR VersionID IS NULL)
              AND (EffectiveFrom IS NULL OR EffectiveFrom <= CAST(SYSUTCDATETIME() AS DATE))
              AND (EffectiveTo IS NULL OR EffectiveTo >= CAST(SYSUTCDATETIME() AS DATE))
            ORDER BY
                CASE WHEN IntegrationInterfaceID = :interfaceId THEN 0 ELSE 1 END,
                CASE WHEN FiscalYearID = :fy THEN 0 ELSE 1 END,
                CASE WHEN VersionID = :versionId THEN 0 ELSE 1 END,
                IntegrationCodeCrosswalkID DESC
        ");
        $stmt->execute([
            ':systemId' => $systemId,
            ':interfaceId' => $interfaceId > 0 ? $interfaceId : -1,
            ':mappingType' => $mappingType,
            ':externalCode' => $externalCode,
            ':fy' => $fiscalYearId > 0 ? $fiscalYearId : -1,
            ':versionId' => $versionId > 0 ? $versionId : -1,
        ]);
        $mappedCode = trim((string) ($stmt->fetchColumn() ?: ''));

        return [
            'external_code' => $externalCode,
            'target_code' => $mappedCode !== '' ? $mappedCode : $externalCode,
            'mapped' => $mappedCode !== '',
            'mapping_required' => $mappingExists,
        ];
    }

    private function integrationCrosswalkTypeExists(int $systemId, int $interfaceId, string $mappingType): bool
    {
        if (!$this->tableExists('dbo.tblIntegrationCodeCrosswalk') || $systemId <= 0) {
            return false;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(1)
            FROM dbo.tblIntegrationCodeCrosswalk
            WHERE IntegrationSystemID = :systemId
              AND MappingTypeCode = :mappingType
              AND ActiveFlag = 1
              AND (IntegrationInterfaceID = :interfaceId OR IntegrationInterfaceID IS NULL)
        ");
        $stmt->execute([
            ':systemId' => $systemId,
            ':interfaceId' => $interfaceId > 0 ? $interfaceId : -1,
            ':mappingType' => $mappingType,
        ]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function updateActualsImportPostability(int $stagingId, string $code, string $message, string $target, int $userId): void
    {
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblIntegrationActualsImportStaging
            SET PostingReadinessCode = :code,
                PostingReadinessMessage = :message,
                PostingTarget = :target,
                UpdatedBy = :userId,
                UpdatedDate = SYSUTCDATETIME()
            WHERE IntegrationActualsImportStagingID = :id
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':id' => $stagingId,
            ':code' => $this->nullableString($code),
            ':message' => $this->nullableString($message),
            ':target' => $this->nullableString($target),
            ':userId' => $userId > 0 ? $userId : null,
        ]);
    }

    private function duplicatePostedOrReadyActualReferenceExists(array $row): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(1)
            FROM dbo.tblIntegrationActualsImportStaging
            WHERE ActiveFlag = 1
              AND TransactionReference = :reference
              AND IntegrationActualsImportStagingID <> :id
              AND (
                    StagingStatusCode = N'posted'
                    OR PostingReadinessCode = N'ready_to_post'
                  )
        ");
        $stmt->execute([
            ':reference' => (string) ($row['TransactionReference'] ?? ''),
            ':id' => (int) ($row['IntegrationActualsImportStagingID'] ?? 0),
        ]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function segmentValueExists(int $fiscalYearId, string $segmentCode): bool
    {
        $conditions = ['SegmentCode' => $segmentCode];
        if ($this->columnExists('dbo.tblSegmentValues', 'FiscalYearID') && $fiscalYearId > 0) {
            $conditions['FiscalYearID'] = $fiscalYearId;
        }
        if ($this->columnExists('dbo.tblSegmentValues', 'ActiveFlag')) {
            $conditions['ActiveFlag'] = 1;
        }

        return $this->existsByConditions('dbo.tblSegmentValues', $conditions);
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->conn->prepare("SELECT OBJECT_ID(:tableName, 'U')");
        $stmt->execute([':tableName' => $tableName]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->conn->prepare('SELECT COL_LENGTH(:tableName, :columnName)');
        $stmt->execute([
            ':tableName' => $tableName,
            ':columnName' => $columnName,
        ]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null;
    }

    private function existsByConditions(string $tableName, array $conditions): bool
    {
        $this->assertQualifiedTableName($tableName);
        if ($conditions === []) {
            return false;
        }

        $where = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            $column = (string) $column;
            $this->assertSimpleIdentifier($column, 'condition column');
            $param = ':p_' . preg_replace('/[^a-z0-9_]+/i', '_', $column);
            $where[] = $this->quoteIdentifier($column) . ' = ' . $param;
            $params[$param] = $value;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(1)
            FROM " . $this->quoteQualifiedObject($tableName) . "
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);
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

    private function assertQualifiedTableName(string $tableName): void
    {
        $parts = array_values(array_filter(array_map('trim', explode('.', $tableName)), static fn (string $part): bool => $part !== ''));
        if (count($parts) !== 2) {
            throw new \RuntimeException('Table name must be provided as schema.object.');
        }
        $this->assertSimpleIdentifier($parts[0], 'schema name');
        $this->assertSimpleIdentifier($parts[1], 'table name');
    }
}
