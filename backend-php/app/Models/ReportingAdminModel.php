<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class ReportingAdminModel
{
    public function __construct(private PDO $conn)
    {
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsReportingFoundation(): bool
    {
        return $this->tableExists('dbo.tblReportDefinition')
            && $this->tableExists('dbo.tblReportRun');
    }

    public function listDefinitions(array $filters = [], bool $activeOnly = false): array
    {
        if (!$this->tableExists('dbo.tblReportDefinition')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($activeOnly) {
            $where[] = 'd.ActiveFlag = 1';
        } elseif (($filters['active'] ?? '') !== '') {
            $where[] = 'd.ActiveFlag = :active';
            $params[':active'] = ((string) $filters['active'] === '1') ? 1 : 0;
        }

        if (($filters['module'] ?? '') !== '') {
            $where[] = 'd.ModuleCode = :moduleCode';
            $params[':moduleCode'] = trim((string) $filters['module']);
        }

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(
                d.ReportCode LIKE :q1
                OR d.ReportName LIKE :q2
                OR d.ModuleCode LIKE :q3
                OR d.ReportGroupCode LIKE :q4
                OR d.PermissionCode LIKE :q5
            )';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params[':q1'] = $needle;
            $params[':q2'] = $needle;
            $params[':q3'] = $needle;
            $params[':q4'] = $needle;
            $params[':q5'] = $needle;
        }

        $sql = "
            SELECT
                d.*,
                (
                    SELECT TOP 1 r.RunStatusCode
                    FROM dbo.tblReportRun r
                    WHERE r.ReportDefinitionID = d.ReportDefinitionID
                    ORDER BY r.StartedAt DESC, r.ReportRunID DESC
                ) AS LastRunStatusCode,
                (
                    SELECT TOP 1 r.StartedAt
                    FROM dbo.tblReportRun r
                    WHERE r.ReportDefinitionID = d.ReportDefinitionID
                    ORDER BY r.StartedAt DESC, r.ReportRunID DESC
                ) AS LastRunStartedAt
            FROM dbo.tblReportDefinition d
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE WHEN d.SortOrder IS NULL THEN 1 ELSE 0 END,
                d.SortOrder ASC,
                d.ModuleCode ASC,
                d.ReportGroupCode ASC,
                d.ReportName ASC,
                d.ReportCode ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDefinition(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('dbo.tblReportDefinition')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblReportDefinition
            WHERE ReportDefinitionID = :id
        ");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getDefinitionByCode(string $reportCode): ?array
    {
        $reportCode = trim($reportCode);
        if ($reportCode === '' || !$this->tableExists('dbo.tblReportDefinition')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblReportDefinition
            WHERE ReportCode = :reportCode
        ");
        $stmt->execute([':reportCode' => $reportCode]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveDefinition(array $data, int $userId, ?int $id = null): int
    {
        $this->requireTable('dbo.tblReportDefinition');

        $payload = [
            ':ReportCode' => trim((string) ($data['ReportCode'] ?? '')),
            ':ReportName' => trim((string) ($data['ReportName'] ?? '')),
            ':ModuleCode' => $this->nullableString($data['ModuleCode'] ?? null),
            ':ReportGroupCode' => $this->nullableString($data['ReportGroupCode'] ?? null),
            ':ReportDescription' => $this->nullableString($data['ReportDescription'] ?? null),
            ':SsrsPath' => $this->nullableString($data['SsrsPath'] ?? null),
            ':ServerUrlOverride' => $this->nullableString($data['ServerUrlOverride'] ?? null),
            ':OutputFormatsCsv' => $this->nullableString($data['OutputFormatsCsv'] ?? null),
            ':DefaultFormatCode' => $this->nullableString($data['DefaultFormatCode'] ?? null),
            ':PermissionCode' => $this->nullableString($data['PermissionCode'] ?? null),
            ':ContextRequiredFlag' => ((int) ($data['ContextRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':FiscalYearRequiredFlag' => ((int) ($data['FiscalYearRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':VersionRequiredFlag' => ((int) ($data['VersionRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':DataScopeRequiredFlag' => ((int) ($data['DataScopeRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':DateFromRequiredFlag' => ((int) ($data['DateFromRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':DateToRequiredFlag' => ((int) ($data['DateToRequiredFlag'] ?? 0) === 1) ? 1 : 0,
            ':ParameterConfigJson' => $this->nullableJson($data['ParameterConfigJson'] ?? null),
            ':SortOrder' => $this->nullableInt($data['SortOrder'] ?? null),
            ':ActiveFlag' => ((int) ($data['ActiveFlag'] ?? 1) === 1) ? 1 : 0,
            ':Notes' => $this->nullableString($data['Notes'] ?? null),
            ':UserID' => $userId > 0 ? $userId : null,
        ];

        if ($id !== null && $id > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblReportDefinition
                SET ReportCode = :ReportCode,
                    ReportName = :ReportName,
                    ModuleCode = :ModuleCode,
                    ReportGroupCode = :ReportGroupCode,
                    ReportDescription = :ReportDescription,
                    SsrsPath = :SsrsPath,
                    ServerUrlOverride = :ServerUrlOverride,
                    OutputFormatsCsv = :OutputFormatsCsv,
                    DefaultFormatCode = :DefaultFormatCode,
                    PermissionCode = :PermissionCode,
                    ContextRequiredFlag = :ContextRequiredFlag,
                    FiscalYearRequiredFlag = :FiscalYearRequiredFlag,
                    VersionRequiredFlag = :VersionRequiredFlag,
                    DataScopeRequiredFlag = :DataScopeRequiredFlag,
                    DateFromRequiredFlag = :DateFromRequiredFlag,
                    DateToRequiredFlag = :DateToRequiredFlag,
                    ParameterConfigJson = :ParameterConfigJson,
                    SortOrder = :SortOrder,
                    ActiveFlag = :ActiveFlag,
                    Notes = :Notes,
                    UpdatedBy = :UserID,
                    UpdatedDate = SYSDATETIME()
                WHERE ReportDefinitionID = :ID
            ");
            $stmt->execute($payload + [':ID' => $id]);
            return $id;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblReportDefinition (
                ReportCode,
                ReportName,
                ModuleCode,
                ReportGroupCode,
                ReportDescription,
                SsrsPath,
                ServerUrlOverride,
                OutputFormatsCsv,
                DefaultFormatCode,
                PermissionCode,
                ContextRequiredFlag,
                FiscalYearRequiredFlag,
                VersionRequiredFlag,
                DataScopeRequiredFlag,
                DateFromRequiredFlag,
                DateToRequiredFlag,
                ParameterConfigJson,
                SortOrder,
                ActiveFlag,
                Notes,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                :ReportCode,
                :ReportName,
                :ModuleCode,
                :ReportGroupCode,
                :ReportDescription,
                :SsrsPath,
                :ServerUrlOverride,
                :OutputFormatsCsv,
                :DefaultFormatCode,
                :PermissionCode,
                :ContextRequiredFlag,
                :FiscalYearRequiredFlag,
                :VersionRequiredFlag,
                :DataScopeRequiredFlag,
                :DateFromRequiredFlag,
                :DateToRequiredFlag,
                :ParameterConfigJson,
                :SortOrder,
                :ActiveFlag,
                :Notes,
                :UserID,
                SYSDATETIME()
            )
        ");
        $stmt->execute($payload);

        return (int) $this->conn->lastInsertId();
    }

    public function listModuleOptions(): array
    {
        if (!$this->tableExists('dbo.tblReportDefinition')) {
            return [];
        }

        $stmt = $this->conn->query("
            SELECT DISTINCT ModuleCode
            FROM dbo.tblReportDefinition
            WHERE ModuleCode IS NOT NULL
              AND LTRIM(RTRIM(ModuleCode)) <> ''
            ORDER BY ModuleCode ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_values(array_map(
            static fn(array $row): string => trim((string) ($row['ModuleCode'] ?? '')),
            $rows
        ));
    }

    public function listRuns(array $filters = [], ?int $userId = null, bool $restrictToUser = false): array
    {
        if (!$this->tableExists('dbo.tblReportRun')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($restrictToUser && $userId !== null && $userId > 0) {
            $where[] = 'r.RequestedByUserID = :userId';
            $params[':userId'] = $userId;
        }

        if (($filters['module'] ?? '') !== '') {
            $where[] = 'd.ModuleCode = :moduleCode';
            $params[':moduleCode'] = trim((string) $filters['module']);
        }

        if (($filters['status'] ?? '') !== '') {
            $where[] = 'r.RunStatusCode = :statusCode';
            $params[':statusCode'] = trim((string) $filters['status']);
        }

        if (($filters['q'] ?? '') !== '') {
            $where[] = '(
                d.ReportCode LIKE :q1
                OR d.ReportName LIKE :q2
                OR r.OutputFormatCode LIKE :q3
                OR r.DataObjectCode LIKE :q4
                OR r.SummaryText LIKE :q5
            )';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params[':q1'] = $needle;
            $params[':q2'] = $needle;
            $params[':q3'] = $needle;
            $params[':q4'] = $needle;
            $params[':q5'] = $needle;
        }

        $sql = "
            SELECT
                r.*,
                d.ReportCode,
                d.ReportName,
                d.ModuleCode
            FROM dbo.tblReportRun r
            INNER JOIN dbo.tblReportDefinition d
                ON d.ReportDefinitionID = r.ReportDefinitionID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.StartedAt DESC, r.ReportRunID DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getRunDetail(int $runId, ?int $userId = null, bool $restrictToUser = false): ?array
    {
        if ($runId <= 0 || !$this->tableExists('dbo.tblReportRun')) {
            return null;
        }

        $where = ['r.ReportRunID = :id'];
        $params = [':id' => $runId];

        if ($restrictToUser && $userId !== null && $userId > 0) {
            $where[] = 'r.RequestedByUserID = :userId';
            $params[':userId'] = $userId;
        }

        $stmt = $this->conn->prepare("
            SELECT
                r.*,
                d.ReportCode,
                d.ReportName,
                d.ModuleCode,
                d.ReportGroupCode,
                d.PermissionCode,
                d.SsrsPath,
                d.ServerUrlOverride
            FROM dbo.tblReportRun r
            INNER JOIN dbo.tblReportDefinition d
                ON d.ReportDefinitionID = r.ReportDefinitionID
            WHERE " . implode(' AND ', $where) . "
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function createRun(array $data): int
    {
        $this->requireTable('dbo.tblReportRun');

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblReportRun (
                ReportDefinitionID,
                RunStatusCode,
                ExecutionMode,
                RequestedByUserID,
                OutputFormatCode,
                FiscalYearID,
                VersionID,
                DataObjectCode,
                DateFrom,
                DateTo,
                StartedAt,
                CompletedAt,
                DurationSeconds,
                LaunchUrl,
                ParameterPayloadJson,
                SummaryText,
                ErrorText,
                CreatedDate
            )
            VALUES (
                :ReportDefinitionID,
                :RunStatusCode,
                :ExecutionMode,
                :RequestedByUserID,
                :OutputFormatCode,
                :FiscalYearID,
                :VersionID,
                :DataObjectCode,
                :DateFrom,
                :DateTo,
                SYSDATETIME(),
                :CompletedAt,
                :DurationSeconds,
                :LaunchUrl,
                :ParameterPayloadJson,
                :SummaryText,
                :ErrorText,
                SYSDATETIME()
            )
        ");

        $stmt->execute([
            ':ReportDefinitionID' => (int) ($data['ReportDefinitionID'] ?? 0),
            ':RunStatusCode' => trim((string) ($data['RunStatusCode'] ?? 'pending')),
            ':ExecutionMode' => trim((string) ($data['ExecutionMode'] ?? 'ssrs_url')),
            ':RequestedByUserID' => $this->nullableInt($data['RequestedByUserID'] ?? null),
            ':OutputFormatCode' => $this->nullableString($data['OutputFormatCode'] ?? null),
            ':FiscalYearID' => $this->nullableInt($data['FiscalYearID'] ?? null),
            ':VersionID' => $this->nullableInt($data['VersionID'] ?? null),
            ':DataObjectCode' => $this->nullableString($data['DataObjectCode'] ?? null),
            ':DateFrom' => $this->nullableString($data['DateFrom'] ?? null),
            ':DateTo' => $this->nullableString($data['DateTo'] ?? null),
            ':CompletedAt' => $this->nullableString($data['CompletedAt'] ?? null),
            ':DurationSeconds' => $this->nullableInt($data['DurationSeconds'] ?? null),
            ':LaunchUrl' => $this->nullableString($data['LaunchUrl'] ?? null),
            ':ParameterPayloadJson' => $this->nullableJson($data['ParameterPayloadJson'] ?? null),
            ':SummaryText' => $this->nullableString($data['SummaryText'] ?? null),
            ':ErrorText' => $this->nullableString($data['ErrorText'] ?? null),
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function tableExists(string $fullyQualifiedName): bool
    {
        $stmt = $this->conn->prepare('SELECT OBJECT_ID(:name)');
        $stmt->execute([':name' => $fullyQualifiedName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function requireTable(string $fullyQualifiedName): void
    {
        if (!$this->tableExists($fullyQualifiedName)) {
            throw new \RuntimeException('Required table is missing: ' . $fullyQualifiedName);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }

    private function nullableJson(mixed $value): ?string
    {
        $text = $this->nullableString($value);
        if ($text === null) {
            return null;
        }
        return $text;
    }
}
