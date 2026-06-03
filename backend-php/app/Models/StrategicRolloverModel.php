<?php
declare(strict_types=1);

namespace App\Models;

use DateInterval;
use DateTimeImmutable;
use PDO;

final class StrategicRolloverModel
{
    private PDO $conn;

    private const VERSION_SCOPE_DEFINITIONS = [
        'fiscal_assumptions' => [
            'label' => 'Fiscal Assumptions',
            'mode' => 'table_version',
            'table' => 'dbo.tblSbFiscalAssumption',
        ],
        'resource_envelope' => [
            'label' => 'Resource Envelope',
            'mode' => 'table_version',
            'table' => 'dbo.tblSbResourceEnvelope',
        ],
        'sector_ceilings' => [
            'label' => 'Sector Ceilings',
            'mode' => 'table_version',
            'table' => 'dbo.tblSbCeiling',
        ],
        'indicator_targets' => [
            'label' => 'Indicator Targets',
            'mode' => 'table_version',
            'table' => 'dbo.tblSbIndicatorTarget',
        ],
        'narratives' => [
            'label' => 'Narratives',
            'mode' => 'table_version',
            'table' => 'dbo.tblSbNarrative',
        ],
        'activity_budgets' => [
            'label' => 'Activity Budgets',
            'mode' => 'table_version',
            'table' => 'dbo.tblSbActivityBudget',
        ],
        'funding_submissions' => [
            'label' => 'Funding Submissions',
            'mode' => 'funding_submissions',
        ],
    ];

    private const FISCAL_SCOPE_DEFINITIONS = [
        'segment_mapping' => [
            'label' => 'Strategic Segment Mapping',
            'mode' => 'table_fiscal',
            'table' => 'dbo.tblSbSegmentConfig',
        ],
        'phasing_profiles' => [
            'label' => 'Custom Phasing Profiles',
            'mode' => 'table_fiscal',
            'table' => 'dbo.tblSbPhasingProfile',
        ],
        'project_source_maps' => [
            'label' => 'Project Source Maps',
            'mode' => 'table_fiscal',
            'table' => 'dbo.tblSbProjectSourceMap',
        ],
        'fiscal_period_labels' => [
            'label' => 'Fiscal Period Labels',
            'mode' => 'table_fiscal',
            'table' => 'dbo.tblSbFiscalPeriodConfig',
        ],
    ];

    private array $columnMetadataCache = [];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsRollover(): bool
    {
        return $this->tableExists('dbo.tblFiscalYears') && $this->tableExists('dbo.tblVersions');
    }

    public function supportsVersionTyping(): bool
    {
        return $this->tableExists('dbo.tblVersionTypes');
    }

    public function getSubmissionVersionTypeId(): int
    {
        if (!$this->supportsVersionTyping()) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 VersionTypeID
            FROM dbo.tblVersionTypes
            WHERE UPPER(VersionTypeCode) = N'SUBMISSION'
              AND ISNULL(ActiveFlag, 1) = 1
        ");
        $stmt->execute();
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function listFiscalYears(): array
    {
        if (!$this->supportsRollover()) {
            return [];
        }

        $submissionTypeId = $this->getSubmissionVersionTypeId();
        $versionCountSql = $submissionTypeId > 0
            ? "(
                    SELECT COUNT(*)
                    FROM dbo.tblVersions v
                    WHERE v.FiscalYearID = fy.FiscalYearID
                      AND ISNULL(v.IsActive, 1) = 1
                      AND v.VersionTypeID = :submissionTypeId
               )"
            : "(
                    SELECT COUNT(*)
                    FROM dbo.tblVersions v
                    WHERE v.FiscalYearID = fy.FiscalYearID
                      AND ISNULL(v.IsActive, 1) = 1
               )";

        $sql = "
            SELECT
                fy.FiscalYearID,
                fy.YearLabel,
                fy.StartDate,
                fy.EndDate,
                fy.IsActive,
                {$versionCountSql} AS VersionCount
            FROM dbo.tblFiscalYears fy
            ORDER BY fy.FiscalYearID DESC
        ";

        $stmt = $this->conn->prepare($sql);
        if ($submissionTypeId > 0) {
            $stmt->bindValue(':submissionTypeId', $submissionTypeId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSubmissionVersions(int $fiscalYearId): array
    {
        $submissionTypeId = $this->getSubmissionVersionTypeId();
        if ($fiscalYearId <= 0 || $submissionTypeId <= 0) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                v.VersionID,
                v.FiscalYearID,
                v.VersionLabel,
                v.VersionStatus,
                v.IsDefault,
                v.IsActive,
                v.BaseFiscalYearID,
                v.BaseVersionID,
                src.VersionLabel AS SourceVersionLabel
            FROM dbo.tblVersions v
            LEFT JOIN dbo.tblVersions src
                ON src.FiscalYearID = v.BaseFiscalYearID
               AND src.VersionID = v.BaseVersionID
            WHERE v.FiscalYearID = :fy
              AND v.VersionTypeID = :submissionTypeId
              AND ISNULL(v.IsActive, 1) = 1
            ORDER BY ISNULL(v.IsDefault, 0) DESC, v.VersionID ASC
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':submissionTypeId' => $submissionTypeId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getSourceVersion(int $fiscalYearId, int $versionId): ?array
    {
        $submissionTypeId = $this->getSubmissionVersionTypeId();
        if ($fiscalYearId <= 0 || $versionId <= 0 || $submissionTypeId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1
                v.*,
                fy.YearLabel,
                src.VersionLabel AS SourceVersionLabel
            FROM dbo.tblVersions v
            INNER JOIN dbo.tblFiscalYears fy
                ON fy.FiscalYearID = v.FiscalYearID
            LEFT JOIN dbo.tblVersions src
                ON src.FiscalYearID = v.BaseFiscalYearID
               AND src.VersionID = v.BaseVersionID
            WHERE v.FiscalYearID = :fy
              AND v.VersionID = :ver
              AND v.VersionTypeID = :submissionTypeId
              AND ISNULL(v.IsActive, 1) = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':submissionTypeId' => $submissionTypeId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getFiscalYear(int $fiscalYearId): ?array
    {
        if ($fiscalYearId <= 0 || !$this->supportsRollover()) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 FiscalYearID, YearLabel, StartDate, EndDate, IsActive
            FROM dbo.tblFiscalYears
            WHERE FiscalYearID = :fy
        ");
        $stmt->execute([':fy' => $fiscalYearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getVersionScopeDefinitions(): array
    {
        $scopes = [];
        foreach (self::VERSION_SCOPE_DEFINITIONS as $code => $definition) {
            $tableName = (string) ($definition['table'] ?? '');
            $installed = $tableName === '' ? true : $this->tableExists($tableName);
            $scopes[$code] = $definition + [
                'code' => $code,
                'installed' => $installed,
            ];
        }

        return $scopes;
    }

    public function getFiscalScopeDefinitions(): array
    {
        $scopes = [];
        foreach (self::FISCAL_SCOPE_DEFINITIONS as $code => $definition) {
            $tableName = (string) ($definition['table'] ?? '');
            $installed = $tableName === '' ? true : $this->tableExists($tableName);
            $scopes[$code] = $definition + [
                'code' => $code,
                'installed' => $installed,
            ];
        }

        return $scopes;
    }

    public function buildVersionRolloverDefaults(int $sourceFiscalYearId, int $preferredVersionId = 0): array
    {
        $versions = $this->listSubmissionVersions($sourceFiscalYearId);
        $selectedVersionId = 0;

        foreach ($versions as $row) {
            if ((int) ($row['VersionID'] ?? 0) === $preferredVersionId) {
                $selectedVersionId = $preferredVersionId;
                break;
            }
        }

        if ($selectedVersionId <= 0 && $versions !== []) {
            $selectedVersionId = (int) ($versions[0]['VersionID'] ?? 0);
        }

        return [
            'SourceFiscalYearID' => $sourceFiscalYearId,
            'SourceVersionID' => $selectedVersionId,
            'TargetVersionLabel' => $this->suggestVersionLabel($sourceFiscalYearId),
            'IsDefault' => 0,
        ];
    }

    public function buildFiscalYearRolloverDefaults(int $sourceFiscalYearId, int $preferredVersionId = 0): array
    {
        $sourceYear = $this->getFiscalYear($sourceFiscalYearId);
        $seed = $this->buildNextFiscalYearSeed($sourceYear);
        $versions = $this->listSubmissionVersions($sourceFiscalYearId);
        $selectedVersionId = 0;

        foreach ($versions as $row) {
            if ((int) ($row['VersionID'] ?? 0) === $preferredVersionId) {
                $selectedVersionId = $preferredVersionId;
                break;
            }
        }

        if ($selectedVersionId <= 0 && $versions !== []) {
            $selectedVersionId = (int) ($versions[0]['VersionID'] ?? 0);
        }

        return [
            'SourceFiscalYearID' => $sourceFiscalYearId,
            'SourceVersionID' => $selectedVersionId,
            'TargetFiscalYearID' => (int) ($seed['FiscalYearID'] ?? ($sourceFiscalYearId > 0 ? $sourceFiscalYearId + 1 : ((int) date('Y') + 1))),
            'TargetYearLabel' => (string) ($seed['YearLabel'] ?? ''),
            'TargetStartDate' => (string) ($seed['StartDate'] ?? ''),
            'TargetEndDate' => (string) ($seed['EndDate'] ?? ''),
            'TargetVersionLabel' => 'Submission Version 1',
            'TargetFiscalYearActive' => 1,
            'IsDefault' => 1,
        ];
    }

    public function runVersionRollover(array $payload): array
    {
        if (!$this->supportsRollover()) {
            throw new \RuntimeException('Fiscal and version rollover is not available until fiscal year and version tables are installed.');
        }

        $sourceFiscalYearId = (int) ($payload['SourceFiscalYearID'] ?? 0);
        $sourceVersionId = (int) ($payload['SourceVersionID'] ?? 0);
        $targetVersionLabel = trim((string) ($payload['TargetVersionLabel'] ?? ''));
        $isDefault = !empty($payload['IsDefault']) ? 1 : 0;
        $userId = (int) ($payload['UserID'] ?? 0);
        $requestedScopes = $this->normalizeScopeSelection((array) ($payload['Scopes'] ?? []), $this->getVersionScopeDefinitions());

        if ($sourceFiscalYearId <= 0 || $sourceVersionId <= 0) {
            throw new \RuntimeException('Source fiscal year and source version are required.');
        }
        if ($targetVersionLabel === '') {
            throw new \RuntimeException('Target version label is required.');
        }
        if ($userId <= 0) {
            throw new \RuntimeException('A valid user is required to run rollover.');
        }

        $sourceVersion = $this->getSourceVersion($sourceFiscalYearId, $sourceVersionId);
        if ($sourceVersion === null) {
            throw new \RuntimeException('Source submission version was not found.');
        }

        $this->conn->beginTransaction();
        try {
            $targetVersionId = $this->createSubmissionVersion([
                'FiscalYearID' => $sourceFiscalYearId,
                'VersionLabel' => $targetVersionLabel,
                'VersionStatus' => null,
                'IsDefault' => $isDefault,
                'BaseFiscalYearID' => $sourceFiscalYearId,
                'BaseVersionID' => $sourceVersionId,
            ]);

            $scopeResults = $this->copyVersionScopes(
                $requestedScopes,
                $sourceFiscalYearId,
                $sourceVersionId,
                $sourceFiscalYearId,
                $targetVersionId,
                $userId
            );

            $this->conn->commit();

            return [
                'TargetFiscalYearID' => $sourceFiscalYearId,
                'TargetVersionID' => $targetVersionId,
                'TargetVersionLabel' => $targetVersionLabel,
                'ScopeResults' => $scopeResults,
            ];
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function runFiscalYearRollover(array $payload): array
    {
        if (!$this->supportsRollover()) {
            throw new \RuntimeException('Fiscal and version rollover is not available until fiscal year and version tables are installed.');
        }

        $sourceFiscalYearId = (int) ($payload['SourceFiscalYearID'] ?? 0);
        $sourceVersionId = (int) ($payload['SourceVersionID'] ?? 0);
        $targetFiscalYearId = (int) ($payload['TargetFiscalYearID'] ?? 0);
        $targetYearLabel = trim((string) ($payload['TargetYearLabel'] ?? ''));
        $targetStartDate = trim((string) ($payload['TargetStartDate'] ?? ''));
        $targetEndDate = trim((string) ($payload['TargetEndDate'] ?? ''));
        $targetVersionLabel = trim((string) ($payload['TargetVersionLabel'] ?? ''));
        $targetFiscalYearActive = !empty($payload['TargetFiscalYearActive']) ? 1 : 0;
        $isDefault = !empty($payload['IsDefault']) ? 1 : 0;
        $userId = (int) ($payload['UserID'] ?? 0);
        $fiscalScopes = $this->normalizeScopeSelection((array) ($payload['FiscalScopes'] ?? []), $this->getFiscalScopeDefinitions());
        $versionScopes = $this->normalizeScopeSelection((array) ($payload['VersionScopes'] ?? []), $this->getVersionScopeDefinitions());

        if ($sourceFiscalYearId <= 0 || $sourceVersionId <= 0) {
            throw new \RuntimeException('Source fiscal year and source version are required.');
        }
        if ($targetFiscalYearId <= 0) {
            throw new \RuntimeException('Target fiscal year is required.');
        }
        if ($targetYearLabel === '' || $targetStartDate === '' || $targetEndDate === '') {
            throw new \RuntimeException('Target fiscal year label, start date, and end date are required.');
        }
        if ($targetVersionLabel === '') {
            throw new \RuntimeException('Target initial version label is required.');
        }
        if ($userId <= 0) {
            throw new \RuntimeException('A valid user is required to run rollover.');
        }
        if ($this->getFiscalYear($targetFiscalYearId) !== null) {
            throw new \RuntimeException('The target fiscal year already exists.');
        }

        $sourceVersion = $this->getSourceVersion($sourceFiscalYearId, $sourceVersionId);
        if ($sourceVersion === null) {
            throw new \RuntimeException('Source submission version was not found.');
        }

        $startDate = $this->parseDate($targetStartDate);
        $endDate = $this->parseDate($targetEndDate);
        if ($startDate === null || $endDate === null) {
            throw new \RuntimeException('Target fiscal year dates are not valid.');
        }
        if ($endDate <= $startDate) {
            throw new \RuntimeException('Target fiscal year end date must be after the start date.');
        }

        $this->conn->beginTransaction();
        try {
            $this->createFiscalYear([
                'FiscalYearID' => $targetFiscalYearId,
                'YearLabel' => $targetYearLabel,
                'StartDate' => $startDate->format('Y-m-d'),
                'EndDate' => $endDate->format('Y-m-d'),
                'IsActive' => $targetFiscalYearActive,
            ]);

            $targetVersionId = $this->createSubmissionVersion([
                'FiscalYearID' => $targetFiscalYearId,
                'VersionLabel' => $targetVersionLabel,
                'VersionStatus' => null,
                'IsDefault' => $isDefault,
                'BaseFiscalYearID' => $sourceFiscalYearId,
                'BaseVersionID' => $sourceVersionId,
            ]);

            $fiscalScopeResults = $this->copyFiscalScopes(
                $fiscalScopes,
                $sourceFiscalYearId,
                $targetFiscalYearId,
                $userId
            );

            $versionScopeResults = $this->copyVersionScopes(
                $versionScopes,
                $sourceFiscalYearId,
                $sourceVersionId,
                $targetFiscalYearId,
                $targetVersionId,
                $userId
            );

            $this->conn->commit();

            return [
                'TargetFiscalYearID' => $targetFiscalYearId,
                'TargetVersionID' => $targetVersionId,
                'TargetVersionLabel' => $targetVersionLabel,
                'FiscalScopeResults' => $fiscalScopeResults,
                'VersionScopeResults' => $versionScopeResults,
            ];
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function suggestVersionLabel(int $fiscalYearId): string
    {
        $nextVersionId = $this->nextVersionId($fiscalYearId > 0 ? $fiscalYearId : ((int) date('Y')));
        return 'Submission Version ' . $nextVersionId;
    }

    private function createFiscalYear(array $data): void
    {
        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblFiscalYears (
                FiscalYearID,
                YearLabel,
                StartDate,
                EndDate,
                IsActive,
                CreatedAt,
                UpdatedAt
            )
            VALUES (
                :fiscalYearId,
                :yearLabel,
                :startDate,
                :endDate,
                :isActive,
                SYSDATETIME(),
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':fiscalYearId' => (int) ($data['FiscalYearID'] ?? 0),
            ':yearLabel' => (string) ($data['YearLabel'] ?? ''),
            ':startDate' => (string) ($data['StartDate'] ?? ''),
            ':endDate' => (string) ($data['EndDate'] ?? ''),
            ':isActive' => !empty($data['IsActive']) ? 1 : 0,
        ]);
    }

    private function createSubmissionVersion(array $data): int
    {
        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $versionLabel = trim((string) ($data['VersionLabel'] ?? ''));
        $submissionTypeId = $this->getSubmissionVersionTypeId();
        $versionStatus = $this->normalizeVersionStatus($data['VersionStatus'] ?? null);
        $isDefault = !empty($data['IsDefault']) ? 1 : 0;
        $baseFiscalYearId = $this->nullableInt($data['BaseFiscalYearID'] ?? null);
        $baseVersionId = $this->nullableInt($data['BaseVersionID'] ?? null);

        if ($fiscalYearId <= 0) {
            throw new \RuntimeException('Fiscal year is required.');
        }
        if ($versionLabel === '') {
            throw new \RuntimeException('Version label is required.');
        }
        if ($submissionTypeId <= 0) {
            throw new \RuntimeException('Submission version type is not configured.');
        }

        $versionId = $this->nextVersionId($fiscalYearId);

        if ($isDefault === 1) {
            $clearStmt = $this->conn->prepare("
                UPDATE dbo.tblVersions
                SET IsDefault = 0,
                    UpdatedAt = SYSDATETIME()
                WHERE FiscalYearID = :fy
                  AND VersionTypeID = :versionTypeId
                  AND ISNULL(IsActive, 1) = 1
            ");
            $clearStmt->execute([
                ':fy' => $fiscalYearId,
                ':versionTypeId' => $submissionTypeId,
            ]);
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblVersions (
                VersionID,
                FiscalYearID,
                VersionLabel,
                VersionTypeID,
                VersionStatus,
                BaseFiscalYearID,
                BaseVersionID,
                ActualsPeriodID,
                BaseCurrency,
                CeilingsOn,
                IsActive,
                IsDefault,
                CreatedAt,
                UpdatedAt
            )
            VALUES (
                :versionId,
                :fiscalYearId,
                :versionLabel,
                :versionTypeId,
                :versionStatus,
                :baseFiscalYearId,
                :baseVersionId,
                NULL,
                NULL,
                NULL,
                1,
                :isDefault,
                SYSDATETIME(),
                SYSDATETIME()
            )
        ");
        $stmt->execute([
            ':versionId' => $versionId,
            ':fiscalYearId' => $fiscalYearId,
            ':versionLabel' => $versionLabel,
            ':versionTypeId' => $submissionTypeId,
            ':versionStatus' => $versionStatus,
            ':baseFiscalYearId' => $baseFiscalYearId,
            ':baseVersionId' => $baseVersionId,
            ':isDefault' => $isDefault,
        ]);

        return $versionId;
    }

    private function copyFiscalScopes(array $scopeCodes, int $sourceFiscalYearId, int $targetFiscalYearId, int $userId): array
    {
        $results = [];
        $definitions = $this->getFiscalScopeDefinitions();

        foreach ($scopeCodes as $scopeCode) {
            $definition = $definitions[$scopeCode] ?? null;
            if (!is_array($definition)) {
                continue;
            }

            $copiedRows = 0;
            $mode = (string) ($definition['mode'] ?? '');
            if ($mode === 'table_fiscal') {
                $copiedRows = $this->cloneFiscalScopedTable(
                    (string) ($definition['table'] ?? ''),
                    $sourceFiscalYearId,
                    $targetFiscalYearId,
                    $userId
                );
            }

            $results[] = [
                'ScopeCode' => $scopeCode,
                'ScopeLabel' => (string) ($definition['label'] ?? $scopeCode),
                'RowsCopied' => $copiedRows,
            ];
        }

        return $results;
    }

    private function copyVersionScopes(
        array $scopeCodes,
        int $sourceFiscalYearId,
        int $sourceVersionId,
        int $targetFiscalYearId,
        int $targetVersionId,
        int $userId
    ): array {
        $results = [];
        $definitions = $this->getVersionScopeDefinitions();

        foreach ($scopeCodes as $scopeCode) {
            $definition = $definitions[$scopeCode] ?? null;
            if (!is_array($definition)) {
                continue;
            }

            $mode = (string) ($definition['mode'] ?? '');
            $copiedRows = 0;
            $detail = null;

            if ($mode === 'table_version') {
                $copiedRows = $this->cloneVersionScopedTable(
                    (string) ($definition['table'] ?? ''),
                    $sourceFiscalYearId,
                    $sourceVersionId,
                    $targetFiscalYearId,
                    $targetVersionId,
                    $userId
                );
            } elseif ($mode === 'funding_submissions') {
                $fundingSummary = $this->cloneFundingSubmissions(
                    $sourceFiscalYearId,
                    $sourceVersionId,
                    $targetFiscalYearId,
                    $targetVersionId,
                    $userId
                );
                $copiedRows = (int) ($fundingSummary['SubmissionsCopied'] ?? 0);
                $detail = $fundingSummary;
            }

            $results[] = [
                'ScopeCode' => $scopeCode,
                'ScopeLabel' => (string) ($definition['label'] ?? $scopeCode),
                'RowsCopied' => $copiedRows,
                'Detail' => $detail,
            ];
        }

        return $results;
    }

    private function cloneFiscalScopedTable(string $tableName, int $sourceFiscalYearId, int $targetFiscalYearId, int $userId): int
    {
        if ($tableName === '' || !$this->tableExists($tableName)) {
            return 0;
        }

        $columns = $this->getCloneableColumns($tableName);
        if ($columns === []) {
            return 0;
        }

        $insertColumns = [];
        $selectParts = [];
        foreach ($columns as $columnName) {
            $insertColumns[] = $this->quoteIdentifier($columnName);
            $selectParts[] = $this->buildCloneExpression($columnName, 'src', $targetFiscalYearId, null, $userId);
        }

        $sql = "
            INSERT INTO {$tableName} (" . implode(', ', $insertColumns) . ")
            SELECT " . implode(",\n                   ", $selectParts) . "
            FROM {$tableName} src
            WHERE src.FiscalYearID = :sourceFiscalYearId
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':sourceFiscalYearId' => $sourceFiscalYearId,
            ':targetFiscalYearId' => $targetFiscalYearId,
            ':userId' => $userId,
        ]);

        return $stmt->rowCount();
    }

    private function cloneVersionScopedTable(
        string $tableName,
        int $sourceFiscalYearId,
        int $sourceVersionId,
        int $targetFiscalYearId,
        int $targetVersionId,
        int $userId
    ): int {
        if ($tableName === '' || !$this->tableExists($tableName)) {
            return 0;
        }

        $columns = $this->getCloneableColumns($tableName);
        if ($columns === []) {
            return 0;
        }

        $insertColumns = [];
        $selectParts = [];
        foreach ($columns as $columnName) {
            $insertColumns[] = $this->quoteIdentifier($columnName);
            $selectParts[] = $this->buildCloneExpression($columnName, 'src', $targetFiscalYearId, $targetVersionId, $userId);
        }

        $sql = "
            INSERT INTO {$tableName} (" . implode(', ', $insertColumns) . ")
            SELECT " . implode(",\n                   ", $selectParts) . "
            FROM {$tableName} src
            WHERE src.FiscalYearID = :sourceFiscalYearId
              AND src.VersionID = :sourceVersionId
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':sourceFiscalYearId' => $sourceFiscalYearId,
            ':sourceVersionId' => $sourceVersionId,
            ':targetFiscalYearId' => $targetFiscalYearId,
            ':targetVersionId' => $targetVersionId,
            ':userId' => $userId,
        ]);

        return $stmt->rowCount();
    }

    private function cloneFundingSubmissions(
        int $sourceFiscalYearId,
        int $sourceVersionId,
        int $targetFiscalYearId,
        int $targetVersionId,
        int $userId
    ): array {
        if (!$this->tableExists('dbo.tblSbFundingSubmission') || !$this->tableExists('dbo.tblSbFundingSubmissionLine')) {
            return [
                'SubmissionsCopied' => 0,
                'LinesCopied' => 0,
            ];
        }

        $supportsReviewerFields = $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerRanking')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'AssessmentGrade')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerRecommendation')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerSummary')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerConditions')
            && $this->tableColumnExists('dbo.tblSbFundingSubmission', 'ReviewerNextSteps');

        $supportsLineReviewResponseFields = $this->tableColumnExists('dbo.tblSbFundingSubmissionLine', 'ReviewerResponse')
            && $this->tableColumnExists('dbo.tblSbFundingSubmissionLine', 'ReviewerConditions')
            && $this->tableColumnExists('dbo.tblSbFundingSubmissionLine', 'ReviewerNextSteps');

        $headerStmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbFundingSubmission
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
            ORDER BY StrategicFundingSubmissionID ASC
        ");
        $headerStmt->execute([
            ':fy' => $sourceFiscalYearId,
            ':ver' => $sourceVersionId,
        ]);
        $headers = $headerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $insertHeaderSql = "
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
                TotalRequestedAmount,
                TotalApprovedAmount,
                SubmittedBy,
                SubmittedDate,
                ReviewedBy,
                ReviewedDate,
                ApprovedBy,
                ApprovedDate,
                DecisionNote,
                ActiveFlag,
                CreatedBy,
                CreatedDate,
                UpdatedBy,
                UpdatedDate" . ($supportsReviewerFields ? ",
                ReviewerRanking,
                AssessmentGrade,
                ReviewerRecommendation,
                ReviewerSummary,
                ReviewerConditions,
                ReviewerNextSteps" : "") . "
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
                :TotalRequestedAmount,
                0,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                :ActiveFlag,
                :UserID,
                SYSDATETIME(),
                :UserID,
                SYSDATETIME()" . ($supportsReviewerFields ? ",
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL" : "") . "
            )
        ";
        $insertHeaderStmt = $this->conn->prepare($insertHeaderSql);

        $insertLineSql = "
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
                CurrentYearApprovedAmount,
                OuterYear1ApprovedAmount,
                OuterYear2ApprovedAmount,
                OuterYear3ApprovedAmount,
                OuterYear4ApprovedAmount,
                OuterYear5ApprovedAmount,
                PriorityRank,
                ScoreStrategicAlignment,
                ScoreReadiness,
                ScoreFiscalAffordability,
                ScoreServiceImpact,
                LineStatusCode,
                DecisionNote,
                PublishedCeilingID,
                PublishedPlanReference,
                ActiveFlag,
                CreatedBy,
                CreatedDate,
                UpdatedBy,
                UpdatedDate" . ($supportsLineReviewResponseFields ? ",
                ReviewerResponse,
                ReviewerConditions,
                ReviewerNextSteps" : "") . "
            )
            VALUES (
                :StrategicFundingSubmissionID,
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
                0,
                0,
                0,
                0,
                0,
                0,
                :PriorityRank,
                :ScoreStrategicAlignment,
                :ScoreReadiness,
                :ScoreFiscalAffordability,
                :ScoreServiceImpact,
                N'DRAFT',
                NULL,
                NULL,
                NULL,
                :ActiveFlag,
                :UserID,
                SYSDATETIME(),
                :UserID,
                SYSDATETIME()" . ($supportsLineReviewResponseFields ? ",
                NULL,
                NULL,
                NULL" : "") . "
            )
        ";
        $insertLineStmt = $this->conn->prepare($insertLineSql);

        $lineSelectStmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblSbFundingSubmissionLine
            WHERE StrategicFundingSubmissionID = :submissionId
            ORDER BY StrategicFundingSubmissionLineID ASC
        ");

        $submissionCount = 0;
        $lineCount = 0;

        foreach ($headers as $header) {
            $insertHeaderStmt->execute([
                ':FiscalYearID' => $targetFiscalYearId,
                ':VersionID' => $targetVersionId,
                ':DataObjectCode' => $header['DataObjectCode'] ?? null,
                ':OrgUnitID' => $header['OrgUnitID'] ?? null,
                ':RequestTitle' => $header['RequestTitle'] ?? null,
                ':RequestNotes' => $header['RequestNotes'] ?? null,
                ':SubmissionTypeCode' => $header['SubmissionTypeCode'] ?? null,
                ':PriorityCode' => $header['PriorityCode'] ?? null,
                ':TotalRequestedAmount' => $header['TotalRequestedAmount'] ?? 0,
                ':ActiveFlag' => (int) ($header['ActiveFlag'] ?? 1),
                ':UserID' => $userId,
            ]);
            $newSubmissionId = (int) $this->conn->lastInsertId();
            $submissionCount++;

            $lineSelectStmt->execute([
                ':submissionId' => (int) ($header['StrategicFundingSubmissionID'] ?? 0),
            ]);
            $lines = $lineSelectStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($lines as $line) {
                $insertLineStmt->execute([
                    ':StrategicFundingSubmissionID' => $newSubmissionId,
                    ':SectorID' => $line['SectorID'] ?? null,
                    ':ProgramID' => $line['ProgramID'] ?? null,
                    ':SubProgramID' => $line['SubProgramID'] ?? null,
                    ':ProjectID' => $line['ProjectID'] ?? null,
                    ':ActivityID' => $line['ActivityID'] ?? null,
                    ':OrgUnitID' => $line['OrgUnitID'] ?? null,
                    ':FundingTypeID' => $line['FundingTypeID'] ?? null,
                    ':FundingSourceID' => $line['FundingSourceID'] ?? null,
                    ':EconomicItemID' => $line['EconomicItemID'] ?? null,
                    ':BidTitle' => $line['BidTitle'] ?? null,
                    ':BusinessCaseSummary' => $line['BusinessCaseSummary'] ?? null,
                    ':ExpectedOutput' => $line['ExpectedOutput'] ?? null,
                    ':ExpectedOutcome' => $line['ExpectedOutcome'] ?? null,
                    ':CurrentYearRequestedAmount' => $line['CurrentYearRequestedAmount'] ?? 0,
                    ':OuterYear1RequestedAmount' => $line['OuterYear1RequestedAmount'] ?? null,
                    ':OuterYear2RequestedAmount' => $line['OuterYear2RequestedAmount'] ?? null,
                    ':OuterYear3RequestedAmount' => $line['OuterYear3RequestedAmount'] ?? null,
                    ':OuterYear4RequestedAmount' => $line['OuterYear4RequestedAmount'] ?? null,
                    ':OuterYear5RequestedAmount' => $line['OuterYear5RequestedAmount'] ?? null,
                    ':PriorityRank' => $line['PriorityRank'] ?? null,
                    ':ScoreStrategicAlignment' => $line['ScoreStrategicAlignment'] ?? null,
                    ':ScoreReadiness' => $line['ScoreReadiness'] ?? null,
                    ':ScoreFiscalAffordability' => $line['ScoreFiscalAffordability'] ?? null,
                    ':ScoreServiceImpact' => $line['ScoreServiceImpact'] ?? null,
                    ':ActiveFlag' => (int) ($line['ActiveFlag'] ?? 1),
                    ':UserID' => $userId,
                ]);
                $lineCount++;
            }

            $this->syncFundingSubmissionTotals($newSubmissionId);
        }

        return [
            'SubmissionsCopied' => $submissionCount,
            'LinesCopied' => $lineCount,
        ];
    }

    private function syncFundingSubmissionTotals(int $submissionId): void
    {
        if ($submissionId <= 0) {
            return;
        }

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblSbFundingSubmission
            SET TotalRequestedAmount = totals.TotalRequestedAmount,
                TotalApprovedAmount = totals.TotalApprovedAmount,
                UpdatedDate = SYSDATETIME()
            FROM dbo.tblSbFundingSubmission target
            CROSS APPLY (
                SELECT
                    COALESCE(SUM(
                        COALESCE(l.CurrentYearRequestedAmount, 0)
                        + COALESCE(l.OuterYear1RequestedAmount, 0)
                        + COALESCE(l.OuterYear2RequestedAmount, 0)
                        + COALESCE(l.OuterYear3RequestedAmount, 0)
                        + COALESCE(l.OuterYear4RequestedAmount, 0)
                        + COALESCE(l.OuterYear5RequestedAmount, 0)
                    ), 0) AS TotalRequestedAmount,
                    COALESCE(SUM(
                        COALESCE(l.CurrentYearApprovedAmount, 0)
                        + COALESCE(l.OuterYear1ApprovedAmount, 0)
                        + COALESCE(l.OuterYear2ApprovedAmount, 0)
                        + COALESCE(l.OuterYear3ApprovedAmount, 0)
                        + COALESCE(l.OuterYear4ApprovedAmount, 0)
                        + COALESCE(l.OuterYear5ApprovedAmount, 0)
                    ), 0) AS TotalApprovedAmount
                FROM dbo.tblSbFundingSubmissionLine l
                WHERE l.StrategicFundingSubmissionID = target.StrategicFundingSubmissionID
                  AND ISNULL(l.ActiveFlag, 1) = 1
            ) totals
            WHERE target.StrategicFundingSubmissionID = :submissionId
        ");
        $stmt->execute([':submissionId' => $submissionId]);
    }

    private function nextVersionId(int $fiscalYearId): int
    {
        $stmt = $this->conn->prepare("
            SELECT ISNULL(MAX(VersionID), 0) + 1
            FROM dbo.tblVersions
            WHERE FiscalYearID = :fy
        ");
        $stmt->execute([':fy' => $fiscalYearId]);
        return (int) ($stmt->fetchColumn() ?: 1);
    }

    private function buildNextFiscalYearSeed(?array $sourceYear): array
    {
        $fallbackFiscalYearId = ((int) date('Y')) + 1;
        if (!is_array($sourceYear) || $sourceYear === []) {
            $start = new DateTimeImmutable($fallbackFiscalYearId . '-04-01');
            $end = $start->add(new DateInterval('P1Y'))->sub(new DateInterval('P1D'));

            return [
                'FiscalYearID' => $fallbackFiscalYearId,
                'YearLabel' => $this->formatYearLabel($start, $end),
                'StartDate' => $start->format('Y-m-d'),
                'EndDate' => $end->format('Y-m-d'),
            ];
        }

        $sourceFiscalYearId = (int) ($sourceYear['FiscalYearID'] ?? 0);
        $sourceStart = $this->parseDate((string) ($sourceYear['StartDate'] ?? ''));
        $sourceEnd = $this->parseDate((string) ($sourceYear['EndDate'] ?? ''));

        if ($sourceStart === null || $sourceEnd === null) {
            $sourceStart = new DateTimeImmutable(($sourceFiscalYearId > 0 ? $sourceFiscalYearId : ((int) date('Y'))) . '-04-01');
            $sourceEnd = $sourceStart->add(new DateInterval('P1Y'))->sub(new DateInterval('P1D'));
        }

        $targetStart = $sourceStart->add(new DateInterval('P1Y'));
        $targetEnd = $sourceEnd->add(new DateInterval('P1Y'));
        $targetFiscalYearId = $sourceFiscalYearId > 0 ? $sourceFiscalYearId + 1 : ((int) $targetStart->format('Y'));

        return [
            'FiscalYearID' => $targetFiscalYearId,
            'YearLabel' => $this->formatYearLabel($targetStart, $targetEnd),
            'StartDate' => $targetStart->format('Y-m-d'),
            'EndDate' => $targetEnd->format('Y-m-d'),
        ];
    }

    private function formatYearLabel(DateTimeImmutable $startDate, DateTimeImmutable $endDate): string
    {
        return $startDate->format('Y') . '/' . $endDate->format('Y');
    }

    private function normalizeScopeSelection(array $requestedScopes, array $availableDefinitions): array
    {
        $scopeCodes = [];
        foreach ($requestedScopes as $scopeCode => $selected) {
            $normalizedCode = is_string($scopeCode) ? $scopeCode : (string) $selected;
            $normalizedCode = trim($normalizedCode);
            if ($normalizedCode === '' || !isset($availableDefinitions[$normalizedCode])) {
                continue;
            }

            if (!(bool) ($availableDefinitions[$normalizedCode]['installed'] ?? false)) {
                continue;
            }

            $scopeCodes[] = $normalizedCode;
        }

        return array_values(array_unique($scopeCodes));
    }

    private function normalizeVersionStatus(mixed $value): ?string
    {
        $status = strtoupper(trim((string) $value));
        if ($status === '') {
            return null;
        }

        $validStatuses = ['OPEN', 'ACTIVE', 'SUSPENDED', 'CLOSED'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \RuntimeException('Version status is not valid.');
        }

        return $status;
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function buildCloneExpression(string $columnName, string $sourceAlias, ?int $targetFiscalYearId, ?int $targetVersionId, int $userId): string
    {
        return match ($columnName) {
            'FiscalYearID' => ':targetFiscalYearId AS ' . $this->quoteIdentifier($columnName),
            'VersionID' => ':targetVersionId AS ' . $this->quoteIdentifier($columnName),
            'CreatedBy', 'UpdatedBy' => ':userId AS ' . $this->quoteIdentifier($columnName),
            'CreatedDate', 'UpdatedDate', 'CreatedAt', 'UpdatedAt' => 'SYSDATETIME() AS ' . $this->quoteIdentifier($columnName),
            default => $sourceAlias . '.' . $this->quoteIdentifier($columnName),
        };
    }

    private function getCloneableColumns(string $tableName): array
    {
        if (isset($this->columnMetadataCache[$tableName])) {
            return $this->columnMetadataCache[$tableName];
        }

        $stmt = $this->conn->prepare("
            SELECT
                c.name AS ColumnName,
                c.is_identity AS IsIdentity,
                c.is_computed AS IsComputed,
                TYPE_NAME(c.user_type_id) AS TypeName
            FROM sys.columns c
            INNER JOIN sys.tables t
                ON t.object_id = c.object_id
            INNER JOIN sys.schemas s
                ON s.schema_id = t.schema_id
            WHERE (s.name + '.' + t.name) = :tableName
            ORDER BY c.column_id ASC
        ");
        $stmt->execute([':tableName' => $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $columns = [];
        foreach ($rows as $row) {
            $columnName = (string) ($row['ColumnName'] ?? '');
            $typeName = strtolower((string) ($row['TypeName'] ?? ''));
            if ($columnName === '') {
                continue;
            }
            if ((int) ($row['IsIdentity'] ?? 0) === 1 || (int) ($row['IsComputed'] ?? 0) === 1) {
                continue;
            }
            if (in_array($typeName, ['timestamp', 'rowversion'], true)) {
                continue;
            }

            $columns[] = $columnName;
        }

        $this->columnMetadataCache[$tableName] = $columns;
        return $columns;
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

    private function quoteIdentifier(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }
}
