<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class BaseConfigurationReadinessModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getDashboard(int $fiscalYearId, int $versionId): array
    {
        $checks = [];

        $checks[] = $this->checkFiscalYears();
        $checks[] = $this->checkFiscalYearLabels();
        $checks[] = $this->checkVersionTypes();
        $checks[] = $this->checkVersions();
        $checks[] = $this->checkVersionDefaultCoverage();
        $checks[] = $this->checkDefaultContextSettings();
        $checks[] = $this->checkCurrencies();
        $checks[] = $this->checkVersionIntegrity();
        $checks[] = $this->checkCurrencyRates();

        $checks[] = $this->checkSegments();
        $checks[] = $this->checkSegmentDefinitionHealth();
        $checks[] = $this->checkSegmentValues($fiscalYearId);
        $checks[] = $this->checkSegmentValueDuplicates($fiscalYearId);
        $checks[] = $this->checkSegmentHierarchySupport();
        $checks[] = $this->checkMissingRequiredSegmentParents($fiscalYearId);

        $checks[] = $this->checkDataObjectTypes();
        $checks[] = $this->checkDataObjectCodes($fiscalYearId);
        $checks[] = $this->checkDataObjectSegmentValueSync($fiscalYearId);
        $checks[] = $this->checkDataObjectParentReferences($fiscalYearId);
        $checks[] = $this->checkDataObjectContainerChildren($fiscalYearId);
        $checks[] = $this->checkDataObjectTree($fiscalYearId);
        $checks[] = $this->checkDataObjectWorkflowStatus($fiscalYearId, $versionId);

        $checks[] = $this->checkPermissionsCatalog();
        $checks[] = $this->checkRoles();
        $checks[] = $this->checkRolePermissions();
        $checks[] = $this->checkRolePermissionIntegrity();
        $checks[] = $this->checkUsersConfigured();
        $checks[] = $this->checkUserRoleCoverage();
        $checks[] = $this->checkAdminCoverage();
        $checks[] = $this->checkScopedAccessReadiness();
        $checks[] = $this->checkScopedAccessCoverage($fiscalYearId);

        $checks[] = $this->checkWorkflowTaskTypes();
        $checks[] = $this->checkWorkflowTaskStatuses();
        $checks[] = $this->checkWorkflowEngineFoundation();
        $checks[] = $this->checkWorkflowAssignmentCoverage($fiscalYearId, $versionId);
        $checks[] = $this->checkWorkflowAssignmentIntegrity($fiscalYearId, $versionId);

        $checks[] = $this->checkSystemSettings();
        $checks[] = $this->checkRequiredSystemSettings();
        $checks[] = $this->checkLoginUrlSettings();
        $checks[] = $this->checkEmailAndSmtpSettings();
        $checks[] = $this->checkWindowsSchedulerConfig();
        $checks[] = $this->checkDeprecatedSystemSettings();

        return [
            'summary' => $this->buildSummary($checks),
            'checks' => $checks,
        ];
    }

    public function getContextLabels(int $fiscalYearId, int $versionId): array
    {
        return [
            'YearLabel' => $this->getFiscalYearLabel($fiscalYearId),
            'VersionLabel' => $this->getVersionLabel($fiscalYearId, $versionId),
        ];
    }

    private function checkFiscalYears(): array
    {
        if (!$this->tableExists('dbo.tblFiscalYears')) {
            return $this->buildCheck(
                'Fiscal Context',
                'Fiscal Years Configured',
                0,
                1,
                'critical',
                'The fiscal year table does not exist, so no planning context can be established.',
                'index.php?route=fiscal-years/list',
                'Fiscal Years',
                'Create the fiscal year base table and load at least one active fiscal year before using the system.'
            );
        }

        $totalCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblFiscalYears');
        $activeCount = $this->columnExists('dbo.tblFiscalYears', 'IsActive')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblFiscalYears WHERE IsActive = 1')
            : $totalCount;

        if ($totalCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Fiscal Years Configured',
                0,
                1,
                'critical',
                'No fiscal years have been configured.',
                'index.php?route=fiscal-years/list',
                'Fiscal Years',
                'Load at least one fiscal year record so users can set context and work in a valid planning cycle.'
            );
        }

        if ($activeCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Fiscal Years Configured',
                $totalCount,
                $totalCount,
                'critical',
                'Fiscal year rows exist, but none are active.',
                'index.php?route=fiscal-years/list',
                'Fiscal Years',
                'Activate at least one fiscal year so users can enter and review data against a live context.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Fiscal Years Configured',
            $activeCount,
            0,
            'ready',
            $activeCount . ' active fiscal year(s) are available for context selection.',
            'index.php?route=fiscal-years/list',
            'Fiscal Years',
            ''
        );
    }

    private function checkFiscalYearLabels(): array
    {
        if (!$this->tableExists('dbo.tblFiscalYears')) {
            return $this->buildCheck(
                'Fiscal Context',
                'Fiscal Year Labels',
                0,
                1,
                'critical',
                'The fiscal year table does not exist, so fiscal year labels cannot be checked.',
                'index.php?route=fiscal-years/list',
                'Fiscal Years',
                'Create the fiscal year base table and load labelled fiscal year records.'
            );
        }

        if (!$this->columnExists('dbo.tblFiscalYears', 'YearLabel')) {
            return $this->buildCheck(
                'Fiscal Context',
                'Fiscal Year Labels',
                0,
                1,
                'warning',
                'The fiscal year table does not expose a YearLabel column.',
                'index.php?route=fiscal-years/list',
                'Fiscal Years',
                'Add or restore the YearLabel column so context selectors and readiness screens can show readable fiscal year names.'
            );
        }

        $activePredicate = $this->columnExists('dbo.tblFiscalYears', 'IsActive')
            ? 'ISNULL(IsActive, 1) = 1'
            : '1 = 1';
        $activeCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblFiscalYears
            WHERE {$activePredicate}
        ");

        if ($activeCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Fiscal Year Labels',
                0,
                0,
                'info',
                'No active fiscal years exist yet, so fiscal year labels are not currently in scope.',
                'index.php?route=fiscal-years/list',
                'Fiscal Years',
                'Activate at least one fiscal year, then rerun readiness to validate fiscal year labels.'
            );
        }

        $missingLabelCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblFiscalYears
            WHERE {$activePredicate}
              AND NULLIF(LTRIM(RTRIM(CONVERT(NVARCHAR(100), YearLabel))), N'') IS NULL
        ");

        if ($missingLabelCount > 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Fiscal Year Labels',
                $activeCount,
                $missingLabelCount,
                'warning',
                $missingLabelCount . ' active fiscal year(s) are missing a fiscal year label.',
                'index.php?route=fiscal-years/list',
                'Fiscal Years',
                'Open Fiscal Years, populate YearLabel for every active fiscal year, save, and rerun readiness.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Fiscal Year Labels',
            $activeCount,
            0,
            'ready',
            $activeCount . ' active fiscal year label(s) are populated.',
            'index.php?route=fiscal-years/list',
            'Fiscal Years',
            ''
        );
    }

    private function checkVersions(): array
    {
        if (!$this->tableExists('dbo.tblVersions')) {
            return $this->buildCheck(
                'Fiscal Context',
                'Versions Configured',
                0,
                1,
                'critical',
                'The version table does not exist, so no planning versions can be selected.',
                'index.php?route=versions/list',
                'Versions',
                'Create the version base table and link at least one version to each active fiscal year.'
            );
        }

        $activeYears = $this->tableExists('dbo.tblFiscalYears')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblFiscalYears WHERE IsActive = 1')
            : 0;
        $activeVersions = $this->columnExists('dbo.tblVersions', 'IsActive')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblVersions WHERE IsActive = 1')
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblVersions');

        $yearsWithoutVersions = 0;
        if ($this->tableExists('dbo.tblFiscalYears')) {
            $yearsWithoutVersions = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblFiscalYears fy
                WHERE fy.IsActive = 1
                  AND NOT EXISTS (
                        SELECT 1
                        FROM dbo.tblVersions v
                        WHERE v.FiscalYearID = fy.FiscalYearID
                          AND v.IsActive = 1
                  )
            ");
        }

        if ($activeVersions <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Versions Configured',
                $activeYears,
                max(1, $activeYears),
                'critical',
                'No active versions are configured for the fiscal-year framework.',
                'index.php?route=versions/list',
                'Versions',
                'Create at least one active version for the active fiscal year so users can work in a valid version context.'
            );
        }

        if ($yearsWithoutVersions > 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Versions Configured',
                $activeYears,
                $yearsWithoutVersions,
                'warning',
                $yearsWithoutVersions . ' active fiscal year(s) do not yet have an active version.',
                'index.php?route=versions/list',
                'Versions',
                'Add at least one active version for each active fiscal year to avoid context and workflow gaps.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Versions Configured',
            $activeVersions,
            0,
            'ready',
            $activeVersions . ' active version record(s) are linked to active fiscal years.',
            'index.php?route=versions/list',
            'Versions',
            ''
        );
    }

    private function checkVersionTypes(): array
    {
        if (!$this->tableExists('dbo.tblVersionTypes')) {
            return $this->missingTableCheck(
                'Fiscal Context',
                'Version Types Configured',
                'The version types table is missing.',
                'index.php?route=version-types/list',
                'Version Types',
                'Create the version types table and seed the active version-type catalogue before maintaining versions.'
            );
        }

        $totalCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblVersionTypes');
        $activeCount = $this->columnExists('dbo.tblVersionTypes', 'ActiveFlag')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblVersionTypes WHERE ActiveFlag = 1')
            : $totalCount;

        if ($totalCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Version Types Configured',
                0,
                1,
                'critical',
                'No version types are configured.',
                'index.php?route=version-types/list',
                'Version Types',
                'Seed the core version types first so every version can be classified consistently.'
            );
        }

        if ($activeCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Version Types Configured',
                $totalCount,
                $totalCount,
                'critical',
                'Version type rows exist, but none are active.',
                'index.php?route=version-types/list',
                'Version Types',
                'Activate at least one version type so version rows can be created and maintained against a valid type catalogue.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Version Types Configured',
            $activeCount,
            0,
            'ready',
            $activeCount . ' active version type row(s) are available.',
            'index.php?route=version-types/list',
            'Version Types',
            ''
        );
    }

    private function checkVersionDefaultCoverage(): array
    {
        if (!$this->tableExists('dbo.tblFiscalYears') || !$this->tableExists('dbo.tblVersions')) {
            return $this->buildCheck(
                'Fiscal Context',
                'Default Version Coverage',
                0,
                0,
                'info',
                'Default-version coverage becomes active once both fiscal years and versions are installed.',
                'index.php?route=versions/list',
                'Versions',
                'Install fiscal-year and version tables first, then rerun readiness to validate default-version behavior.'
            );
        }

        if (!$this->columnExists('dbo.tblVersions', 'IsDefault')) {
            return $this->buildCheck(
                'Fiscal Context',
                'Default Version Coverage',
                0,
                1,
                'warning',
                'The version table does not expose an IsDefault flag, so predictable default-version selection cannot be validated.',
                'index.php?route=versions/list',
                'Versions',
                'Add the IsDefault column to tblVersions so the application can distinguish the preferred default version for each active fiscal year.'
            );
        }

        $fiscalYearActivePredicate = $this->columnExists('dbo.tblFiscalYears', 'IsActive')
            ? 'ISNULL(fy.IsActive, 1) = 1'
            : '1 = 1';
        $versionActivePredicate = $this->columnExists('dbo.tblVersions', 'IsActive')
            ? 'ISNULL(v.IsActive, 1) = 1'
            : '1 = 1';

        $activeYearCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblFiscalYears fy
            WHERE {$fiscalYearActivePredicate}
        ");

        if ($activeYearCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Default Version Coverage',
                0,
                0,
                'info',
                'No active fiscal years exist yet, so default-version coverage is not currently in scope.',
                'index.php?route=fiscal-years/list',
                'Fiscal Years',
                'Activate at least one fiscal year, then rerun readiness to validate default-version coverage.'
            );
        }

        $activeContextCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM (
                SELECT v.FiscalYearID, ISNULL(v.VersionTypeID, 0) AS VersionTypeID
                FROM dbo.tblVersions v
                INNER JOIN dbo.tblFiscalYears fy
                    ON fy.FiscalYearID = v.FiscalYearID
                WHERE {$fiscalYearActivePredicate}
                  AND {$versionActivePredicate}
                GROUP BY v.FiscalYearID, ISNULL(v.VersionTypeID, 0)
            ) coverage
        ");

        $contextsWithoutDefault = $this->fetchCount("
            SELECT COUNT(*)
            FROM (
                SELECT v.FiscalYearID, ISNULL(v.VersionTypeID, 0) AS VersionTypeID
                FROM dbo.tblVersions v
                INNER JOIN dbo.tblFiscalYears fy
                    ON fy.FiscalYearID = v.FiscalYearID
                WHERE {$fiscalYearActivePredicate}
                  AND {$versionActivePredicate}
                GROUP BY v.FiscalYearID, ISNULL(v.VersionTypeID, 0)
                HAVING SUM(CASE WHEN ISNULL(v.IsDefault, 0) = 1 THEN 1 ELSE 0 END) = 0
            ) coverage
        ");

        $contextsWithMultipleDefaults = $this->fetchCount("
            SELECT COUNT(*)
            FROM (
                SELECT v.FiscalYearID, ISNULL(v.VersionTypeID, 0) AS VersionTypeID
                FROM dbo.tblVersions v
                INNER JOIN dbo.tblFiscalYears fy
                    ON fy.FiscalYearID = v.FiscalYearID
                WHERE {$fiscalYearActivePredicate}
                  AND {$versionActivePredicate}
                GROUP BY v.FiscalYearID, ISNULL(v.VersionTypeID, 0)
                HAVING SUM(CASE WHEN ISNULL(v.IsDefault, 0) = 1 THEN 1 ELSE 0 END) > 1
            ) coverage
        ");

        if ($contextsWithMultipleDefaults > 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Default Version Coverage',
                $activeContextCount,
                $contextsWithMultipleDefaults,
                'critical',
                $contextsWithMultipleDefaults . ' active fiscal year / version type context(s) have more than one active default version.',
                'index.php?route=versions/list',
                'Versions',
                'Ensure each active fiscal year and version type combination has exactly one active default version so login and context fallback remain deterministic.'
            );
        }

        if ($contextsWithoutDefault > 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Default Version Coverage',
                $activeContextCount,
                $contextsWithoutDefault,
                'warning',
                $contextsWithoutDefault . ' active fiscal year / version type context(s) do not have an active default version.',
                'index.php?route=versions/list',
                'Versions',
                'Mark one active version as default for each active fiscal year and version type combination so the application can resolve a predictable fallback context.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Default Version Coverage',
            $activeContextCount,
            0,
            'ready',
            'Each active fiscal year and version type combination has exactly one active default version.',
            'index.php?route=versions/list',
            'Versions',
            ''
        );
    }

    private function checkDefaultContextSettings(): array
    {
        if (!$this->tableExists('dbo.tblSystemSettings')) {
            return $this->buildCheck(
                'Fiscal Context',
                'Default Context Settings',
                0,
                1,
                'warning',
                'System settings are not available, so default fiscal year and version settings cannot be validated.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Load the system settings table and define default context values so login can resolve a sensible starting fiscal year and version.'
            );
        }

        $defaultFiscalYear = $this->getSettingValue(['DEFAULT_FISCAL_YEAR', 'Default_Fiscal_Year']);
        $defaultVersion = $this->getSettingValue(['DEFAULT_VERSION', 'Default_Version']);

        $issueCount = 0;
        $problems = [];

        if ($defaultFiscalYear === null || trim($defaultFiscalYear) === '') {
            $issueCount++;
            $problems[] = 'Default fiscal year is not set.';
        } elseif (!$this->existsByKey('dbo.tblFiscalYears', 'FiscalYearID', (int) $defaultFiscalYear)) {
            $issueCount++;
            $problems[] = 'Default fiscal year does not match an existing fiscal year.';
        } elseif ($this->columnExists('dbo.tblFiscalYears', 'IsActive')) {
            $defaultYearIsActive = $this->fetchCount('
                SELECT COUNT(*)
                FROM dbo.tblFiscalYears
                WHERE FiscalYearID = :fy
                  AND IsActive = 1
            ', [':fy' => (int) $defaultFiscalYear]);
            if ($defaultYearIsActive <= 0) {
                $issueCount++;
                $problems[] = 'Default fiscal year exists but is not active.';
            }
        }

        if ($defaultVersion === null || trim($defaultVersion) === '') {
            $issueCount++;
            $problems[] = 'Default version is not set.';
        } elseif ($defaultFiscalYear !== null && trim($defaultFiscalYear) !== '') {
            $versionExists = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions
                WHERE VersionID = :versionId
                  AND FiscalYearID = :fiscalYearId
            ", [
                ':versionId' => (int) $defaultVersion,
                ':fiscalYearId' => (int) $defaultFiscalYear,
            ]);
            if ($versionExists <= 0) {
                $issueCount++;
                $problems[] = 'Default version does not belong to the default fiscal year.';
            } else {
                if ($this->columnExists('dbo.tblVersions', 'IsActive')) {
                    $defaultVersionIsActive = $this->fetchCount("
                        SELECT COUNT(*)
                        FROM dbo.tblVersions
                        WHERE VersionID = :versionId
                          AND FiscalYearID = :fiscalYearId
                          AND IsActive = 1
                    ", [
                        ':versionId' => (int) $defaultVersion,
                        ':fiscalYearId' => (int) $defaultFiscalYear,
                    ]);
                    if ($defaultVersionIsActive <= 0) {
                        $issueCount++;
                        $problems[] = 'Default version exists but is not active.';
                    }
                }

                if ($this->columnExists('dbo.tblVersions', 'IsDefault')) {
                    $defaultVersionFlagged = $this->fetchCount("
                        SELECT COUNT(*)
                        FROM dbo.tblVersions
                        WHERE VersionID = :versionId
                          AND FiscalYearID = :fiscalYearId
                          AND ISNULL(IsDefault, 0) = 1
                    ", [
                        ':versionId' => (int) $defaultVersion,
                        ':fiscalYearId' => (int) $defaultFiscalYear,
                    ]);
                    if ($defaultVersionFlagged <= 0) {
                        $issueCount++;
                        $problems[] = 'Default version exists but is not flagged as the default version for its fiscal year and version type.';
                    }
                }
            }
        }

        if ($issueCount > 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Default Context Settings',
                2,
                $issueCount,
                'warning',
                implode(' ', $problems),
                'index.php?route=system-settings/list',
                'System Settings',
                'Open System Settings, set valid default fiscal year and version values, save them, and then rerun this check.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Default Context Settings',
            2,
            0,
            'ready',
            'Default fiscal year and default version settings are present and resolve to a valid context.',
            'index.php?route=system-settings/list',
            'System Settings',
            ''
        );
    }

    private function checkDataObjectTypes(): array
    {
        if (!$this->tableExists('dbo.tblDataObjectTypes')) {
            return $this->missingTableCheck(
                'Organisation Structure',
                'Data Object Types Configured',
                'The data object types table is missing.',
                'index.php?route=dataobject-types/list',
                'Data Object Types',
                'Create the data object types table and define the core organisational type records before loading DataScope codes.'
            );
        }

        $count = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectTypes');
        if ($count <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Types Configured',
                0,
                1,
                'critical',
                'No data object types are configured.',
                'index.php?route=dataobject-types/list',
                'Data Object Types',
                'Create the core organisational type records first so each DataScope code can be classified correctly.'
            );
        }

        return $this->buildCheck(
            'Organisation Structure',
            'Data Object Types Configured',
            $count,
            0,
            'ready',
            $count . ' data object type record(s) are available.',
            'index.php?route=dataobject-types/list',
            'Data Object Types',
            ''
        );
    }

    private function checkCurrencies(): array
    {
        if (!$this->tableExists('dbo.tblCurrencies')) {
            return $this->missingTableCheck(
                'Fiscal Context',
                'Currencies Configured',
                'The currencies table is missing.',
                'index.php?route=currencies/list',
                'Currencies',
                'Create the currencies table and load at least one active system-default currency before maintaining versions or exchange rates.'
            );
        }

        $totalCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCurrencies');
        $activeCount = $this->columnExists('dbo.tblCurrencies', 'IsActive')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCurrencies WHERE IsActive = 1')
            : $totalCount;
        $defaultCount = $this->columnExists('dbo.tblCurrencies', 'IsSystemDefault')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCurrencies WHERE IsSystemDefault = 1')
            : 0;
        $inactiveVersionCurrencyCount = 0;

        if ($this->tableExists('dbo.tblVersions') && $this->columnExists('dbo.tblVersions', 'BaseCurrency')) {
            $inactiveVersionCurrencyCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions v
                INNER JOIN dbo.tblCurrencies c
                    ON c.CurrencyCode = v.BaseCurrency
                WHERE NULLIF(LTRIM(RTRIM(ISNULL(v.BaseCurrency, ''))), '') IS NOT NULL
                  AND ISNULL(c.IsActive, 1) = 0
            ");
        }

        if ($totalCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Currencies Configured',
                0,
                1,
                'critical',
                'No currencies are configured.',
                'index.php?route=currencies/list',
                'Currencies',
                'Load at least one active currency and mark one row as the system default before maintaining versions or exchange rates.'
            );
        }

        if ($activeCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Currencies Configured',
                $totalCount,
                $totalCount,
                'critical',
                'Currency rows exist, but none are active.',
                'index.php?route=currencies/list',
                'Currencies',
                'Activate at least one currency and make sure the planned base currency can be selected on version setup screens.'
            );
        }

        if ($defaultCount !== 1) {
            return $this->buildCheck(
                'Fiscal Context',
                'Currencies Configured',
                $activeCount,
                abs($defaultCount - 1) + 1,
                'warning',
                $defaultCount <= 0
                    ? 'No system-default currency is defined.'
                    : 'More than one system-default currency is defined.',
                'index.php?route=currencies/list',
                'Currencies',
                'Keep exactly one active system-default currency so version setup and default currency selection remain predictable.'
            );
        }

        if ($inactiveVersionCurrencyCount > 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Currencies Configured',
                $activeCount,
                $inactiveVersionCurrencyCount,
                'warning',
                $inactiveVersionCurrencyCount . ' version record(s) reference a currency that is currently inactive.',
                'index.php?route=currencies/list',
                'Currencies',
                'Either reactivate the referenced currencies or update the affected versions so all version base currencies point to active currency rows.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Currencies Configured',
            $activeCount,
            0,
            'ready',
            $activeCount . ' active currency row(s) are available, including one system-default currency.',
            'index.php?route=currencies/list',
            'Currencies',
            ''
        );
    }

    private function checkCurrencyRates(): array
    {
        if (!$this->tableExists('dbo.tblCurrencyRates')) {
            return $this->missingTableCheck(
                'Fiscal Context',
                'Currency Rates Loaded',
                'The currency rates table is missing.',
                'index.php?route=currency-rates/list',
                'Currency Rates',
                'Create the currency rates table so exchange rates can be loaded when multi-currency planning or reporting is in scope.'
            );
        }

        $totalCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCurrencyRates');
        $activeCount = $this->columnExists('dbo.tblCurrencyRates', 'IsActive')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCurrencyRates WHERE IsActive = 1')
            : $totalCount;

        $invalidPairCount = 0;
        if ($this->tableExists('dbo.tblCurrencies')) {
            $invalidPairCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblCurrencyRates r
                LEFT JOIN dbo.tblCurrencies fc
                    ON fc.CurrencyCode = r.FromCurrencyCode
                LEFT JOIN dbo.tblCurrencies tc
                    ON tc.CurrencyCode = r.ToCurrencyCode
                WHERE fc.CurrencyCode IS NULL
                   OR tc.CurrencyCode IS NULL
                   OR r.FromCurrencyCode = r.ToCurrencyCode
            ");
        }

        if ($invalidPairCount > 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Currency Rates Loaded',
                max($totalCount, $activeCount),
                $invalidPairCount,
                'warning',
                $invalidPairCount . ' currency rate row(s) have invalid currency-pair references or self-referencing pairs.',
                'index.php?route=currency-rates/list',
                'Currency Rates',
                'Review the affected rate rows so each rate links two different valid currencies before multi-currency calculations rely on them.'
            );
        }

        if ($activeCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Currency Rates Loaded',
                0,
                0,
                'info',
                'No active currency rates are loaded yet, which is acceptable until multi-currency planning, comparison, or reporting is in scope.',
                'index.php?route=currency-rates/list',
                'Currency Rates',
                'Load exchange rates once the client needs to convert or compare values across more than one base currency.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Currency Rates Loaded',
            $activeCount,
            0,
            'ready',
            $activeCount . ' active currency rate row(s) are available for maintained currency pairs.',
            'index.php?route=currency-rates/list',
            'Currency Rates',
            ''
        );
    }

    private function checkVersionIntegrity(): array
    {
        if (!$this->tableExists('dbo.tblVersions')) {
            return $this->buildCheck(
                'Fiscal Context',
                'Version Integrity',
                0,
                0,
                'info',
                'Version-integrity validation becomes active once the version table is available.',
                'index.php?route=versions/list',
                'Versions',
                'Create the versions table first, then rerun readiness to validate version row integrity.'
            );
        }

        $totalCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblVersions');
        if ($totalCount <= 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Version Integrity',
                0,
                0,
                'info',
                'No version rows exist yet, so integrity validation is not currently in scope.',
                'index.php?route=versions/list',
                'Versions',
                'Create at least one version row, then rerun readiness to validate version integrity.'
            );
        }

        $problems = [];
        $issueCount = 0;

        $blankLabelCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblVersions
            WHERE NULLIF(LTRIM(RTRIM(ISNULL(VersionLabel, ''))), '') IS NULL
        ");
        if ($blankLabelCount > 0) {
            $issueCount += $blankLabelCount;
            $problems[] = $blankLabelCount . ' version row(s) have a blank VersionLabel.';
        }

        if ($this->columnExists('dbo.tblVersions', 'VersionStatus')) {
            $blankStatusCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions
                WHERE NULLIF(LTRIM(RTRIM(ISNULL(VersionStatus, ''))), '') IS NULL
            ");
            if ($blankStatusCount > 0) {
                $issueCount += $blankStatusCount;
                $problems[] = $blankStatusCount . ' version row(s) have a blank VersionStatus.';
            }
        }

        if ($this->tableExists('dbo.tblVersionTypes') && $this->columnExists('dbo.tblVersions', 'VersionTypeID')) {
            $invalidTypeCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions v
                LEFT JOIN dbo.tblVersionTypes vt
                    ON vt.VersionTypeID = v.VersionTypeID
                WHERE vt.VersionTypeID IS NULL
                   OR ISNULL(vt.ActiveFlag, 1) = 0
            ");
            if ($invalidTypeCount > 0) {
                $issueCount += $invalidTypeCount;
                $problems[] = $invalidTypeCount . ' version row(s) point to a missing or inactive version type.';
            }
        }

        if ($this->columnExists('dbo.tblVersions', 'BaseFiscalYearID') && $this->columnExists('dbo.tblVersions', 'BaseVersionID')) {
            $brokenBasePairCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions
                WHERE (BaseFiscalYearID IS NULL AND BaseVersionID IS NOT NULL)
                   OR (BaseFiscalYearID IS NOT NULL AND BaseVersionID IS NULL)
            ");
            if ($brokenBasePairCount > 0) {
                $issueCount += $brokenBasePairCount;
                $problems[] = $brokenBasePairCount . ' version row(s) have an incomplete base fiscal year / base version pair.';
            }

            $missingBaseVersionCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions v
                LEFT JOIN dbo.tblVersions base
                    ON base.FiscalYearID = v.BaseFiscalYearID
                   AND base.VersionID = v.BaseVersionID
                WHERE v.BaseFiscalYearID IS NOT NULL
                  AND v.BaseVersionID IS NOT NULL
                  AND base.VersionID IS NULL
            ");
            if ($missingBaseVersionCount > 0) {
                $issueCount += $missingBaseVersionCount;
                $problems[] = $missingBaseVersionCount . ' version row(s) reference a base version that does not exist.';
            }

            $selfReferenceCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions
                WHERE BaseFiscalYearID = FiscalYearID
                  AND BaseVersionID = VersionID
            ");
            if ($selfReferenceCount > 0) {
                $issueCount += $selfReferenceCount;
                $problems[] = $selfReferenceCount . ' version row(s) reference themselves as their own base version.';
            }
        }

        if ($this->tableExists('dbo.tblCurrencies') && $this->columnExists('dbo.tblVersions', 'BaseCurrency')) {
            $invalidBaseCurrencyCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions v
                LEFT JOIN dbo.tblCurrencies c
                    ON c.CurrencyCode = v.BaseCurrency
                WHERE NULLIF(LTRIM(RTRIM(ISNULL(v.BaseCurrency, ''))), '') IS NOT NULL
                  AND (
                        c.CurrencyCode IS NULL
                        OR ISNULL(c.IsActive, 1) = 0
                  )
            ");
            if ($invalidBaseCurrencyCount > 0) {
                $issueCount += $invalidBaseCurrencyCount;
                $problems[] = $invalidBaseCurrencyCount . ' version row(s) use a missing or inactive base currency.';
            }
        }

        if ($this->columnExists('dbo.tblVersions', 'IsDefault') && $this->columnExists('dbo.tblVersions', 'IsActive')) {
            $inactiveDefaultCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblVersions
                WHERE ISNULL(IsDefault, 0) = 1
                  AND ISNULL(IsActive, 1) = 0
            ");
            if ($inactiveDefaultCount > 0) {
                $issueCount += $inactiveDefaultCount;
                $problems[] = $inactiveDefaultCount . ' version row(s) are flagged as default while inactive.';
            }
        }

        if ($issueCount > 0) {
            return $this->buildCheck(
                'Fiscal Context',
                'Version Integrity',
                $totalCount,
                $issueCount,
                'critical',
                implode(' ', $problems),
                'index.php?route=versions/list',
                'Versions',
                'Open Versions, correct the invalid rows, and rerun readiness so every version has a valid type, status, base-version lineage, and currency reference.'
            );
        }

        return $this->buildCheck(
            'Fiscal Context',
            'Version Integrity',
            $totalCount,
            0,
            'ready',
            'Version rows have valid labels, types, statuses, base-version lineage, and currency references.',
            'index.php?route=versions/list',
            'Versions',
            ''
        );
    }

    private function checkDataObjectCodes(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblDataObjectCodes')) {
            return $this->missingTableCheck(
                'Organisation Structure',
                'Data Object Codes Loaded',
                'The data object codes table is missing.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Create the data object codes table and load the organisational structure for the fiscal year you want to use.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Codes Loaded',
                0,
                0,
                'info',
                'No current fiscal year is selected, so fiscal-year-specific DataScope coverage cannot be checked.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Set a fiscal year context first, then rerun readiness to confirm DataScope coverage for that year.'
            );
        }

        $count = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectCodes WHERE FiscalYearID = :fy', [':fy' => $fiscalYearId]);
        $missingTypeCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectCodes
            WHERE FiscalYearID = :fy
              AND (DataObjectTypeID IS NULL
                   OR NOT EXISTS (
                        SELECT 1
                        FROM dbo.tblDataObjectTypes t
                        WHERE t.DataObjectTypeID = dbo.tblDataObjectCodes.DataObjectTypeID
                   ))
        ", [':fy' => $fiscalYearId]);
        $missingTypeCoverageRows = $this->fetchRows("
            SELECT
                t.DataObjectTypeID,
                t.DataObjectTypeName
            FROM dbo.tblDataObjectTypes t
            WHERE NOT EXISTS (
                SELECT 1
                FROM dbo.tblDataObjectCodes c
                WHERE c.FiscalYearID = :fy
                  AND c.DataObjectTypeID = t.DataObjectTypeID
            )
            ORDER BY t.[Level] ASC, t.DataObjectTypeID ASC
        ", [':fy' => $fiscalYearId]);
        $missingTypeCoverageCount = count($missingTypeCoverageRows);

        if ($count <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Codes Loaded',
                0,
                1,
                'critical',
                'No DataScope organisation codes are loaded for the current fiscal year.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Load the current fiscal year organisation codes before users try to select scope, lodge workflows, or submit data.'
            );
        }

        if ($missingTypeCount > 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Codes Loaded',
                $count,
                $missingTypeCount,
                'warning',
                $missingTypeCount . ' organisation code(s) are missing a valid data object type.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Review the current fiscal year organisation codes and assign a valid type to each code that is missing one.'
            );
        }

        if ($missingTypeCoverageCount > 0) {
            $previewNames = array_map(
                static fn(array $row): string => trim((string) ($row['DataObjectTypeName'] ?? ('Type ' . (string) ($row['DataObjectTypeID'] ?? '')))),
                array_slice($missingTypeCoverageRows, 0, 5)
            );
            $preview = implode(', ', array_filter($previewNames, static fn(string $name): bool => $name !== ''));
            if ($missingTypeCoverageCount > 5) {
                $preview .= ($preview !== '' ? ', ' : '') . 'and ' . ($missingTypeCoverageCount - 5) . ' more';
            }

            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Codes Loaded',
                $count,
                $missingTypeCoverageCount,
                'critical',
                $missingTypeCoverageCount . ' data object type(s) have no organisation codes loaded for the current fiscal year' . ($preview !== '' ? ': ' . $preview . '.' : '.'),
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Load at least one current fiscal year data object code for each configured data object type before marking organisation structure setup as complete.'
            );
        }

        return $this->buildCheck(
            'Organisation Structure',
            'Data Object Codes Loaded',
            $count,
            0,
            'ready',
            $count . ' organisation code(s) are loaded for the current fiscal year and each has a valid type.',
            'index.php?route=dataobjectcodes/index',
            'Data Object Codes',
            ''
        );
    }

    private function checkDataObjectSegmentValueSync(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblDataObjectCodes')
            || !$this->tableExists('dbo.tblDataObjectTypes')
            || !$this->tableExists('dbo.tblSegmentValues')) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Segment Value Sync',
                0,
                0,
                'info',
                'Segment-to-data-object sync coverage cannot be checked until data object codes, data object types, and segment values are available.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Load data object types and current fiscal year segment values first, then rerun readiness.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Segment Value Sync',
                0,
                0,
                'info',
                'No current fiscal year is selected, so segment-to-data-object sync coverage cannot be checked.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Set a fiscal year context first, then rerun readiness to validate segment-backed organisation codes.'
            );
        }

        $mappedSegmentValueCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegmentValues sv
            INNER JOIN dbo.tblDataObjectTypes dot
                ON dot.SegmentNo = sv.SegmentNo
            WHERE sv.FiscalYearID = :fy
              AND sv.ActiveFlag = 1
              AND NULLIF(LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N''))), N'') IS NOT NULL
        ", [':fy' => $fiscalYearId]);

        if ($mappedSegmentValueCount <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Segment Value Sync',
                0,
                0,
                'info',
                'No active segment values currently match segment-backed data object types.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Confirm tblDataObjectTypes.SegmentNo is set for Head, Cost Centre, and Sub Cost Centre, then load segment values for the current fiscal year.'
            );
        }

        $missingDataObjectCodeCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegmentValues sv
            INNER JOIN dbo.tblDataObjectTypes dot
                ON dot.SegmentNo = sv.SegmentNo
            LEFT JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = sv.FiscalYearID
               AND doc.DataObjectCode = sv.DataObjectCode
            WHERE sv.FiscalYearID = :fy
              AND sv.ActiveFlag = 1
              AND NULLIF(LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N''))), N'') IS NOT NULL
              AND doc.DataObjectCode IS NULL
        ", [':fy' => $fiscalYearId]);

        $staleDataObjectCodeCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectCodes doc
            INNER JOIN dbo.tblDataObjectTypes dot
                ON dot.DataObjectTypeID = doc.DataObjectTypeID
               AND dot.SegmentNo IS NOT NULL
            LEFT JOIN dbo.tblSegmentValues sv
                ON sv.FiscalYearID = doc.FiscalYearID
               AND sv.DataObjectCode = doc.DataObjectCode
               AND sv.SegmentNo = dot.SegmentNo
               AND sv.ActiveFlag = 1
            WHERE doc.FiscalYearID = :fy
              AND sv.SegmentValueID IS NULL
        ", [':fy' => $fiscalYearId]);

        $issueCount = $missingDataObjectCodeCount + $staleDataObjectCodeCount;
        if ($issueCount > 0) {
            $parts = [];
            if ($missingDataObjectCodeCount > 0) {
                $parts[] = $missingDataObjectCodeCount . ' active segment value(s) are not loaded as Data Object Codes';
            }
            if ($staleDataObjectCodeCount > 0) {
                $parts[] = $staleDataObjectCodeCount . ' segment-backed Data Object Code(s) no longer match an active segment value';
            }

            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Segment Value Sync',
                $mappedSegmentValueCount,
                $issueCount,
                'warning',
                implode(', and ', $parts) . '.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Use Load Org Structure on the Data Object Codes screen to preview and load missing segment-backed codes. Review stale codes manually before archiving or deleting them.'
            );
        }

        return $this->buildCheck(
            'Organisation Structure',
            'Data Object Segment Value Sync',
            $mappedSegmentValueCount,
            0,
            'ready',
            'Active segment values for segment-backed data object types are represented in Data Object Codes for the current fiscal year.',
            'index.php?route=dataobjectcodes/index',
            'Data Object Codes',
            ''
        );
    }

    private function checkDataObjectTree(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblDataObjectTree')) {
            return $this->missingTableCheck(
                'Organisation Structure',
                'Data Object Hierarchy Links',
                'The data object tree table is missing.',
                'index.php?route=dataobjectcodes/hierarchy',
                'Data Object Hierarchy',
                'Create the data object hierarchy table and load parent-child relationships for the current fiscal year.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Hierarchy Links',
                0,
                0,
                'info',
                'No current fiscal year is selected, so fiscal-year hierarchy links cannot be checked.',
                'index.php?route=dataobjectcodes/hierarchy',
                'Data Object Hierarchy',
                'Set a fiscal year context first, then rerun readiness to validate the hierarchy for that year.'
            );
        }

        $codeCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectCodes WHERE FiscalYearID = :fy', [':fy' => $fiscalYearId]);
        $treeCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectTree WHERE FiscalYearID = :fy', [':fy' => $fiscalYearId]);
        $orphanLinks = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectTree tr
            LEFT JOIN dbo.tblDataObjectCodes ancestorCode
                ON ancestorCode.FiscalYearID = tr.FiscalYearID
               AND ancestorCode.DataObjectCode = tr.AncestorCode
            LEFT JOIN dbo.tblDataObjectCodes descendantCode
                ON descendantCode.FiscalYearID = tr.FiscalYearID
               AND descendantCode.DataObjectCode = tr.DescendantCode
            WHERE tr.FiscalYearID = :fy
              AND (
                    ancestorCode.DataObjectCode IS NULL
                    OR descendantCode.DataObjectCode IS NULL
              )
        ", [':fy' => $fiscalYearId]);

        if ($codeCount <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Hierarchy Links',
                0,
                0,
                'info',
                'No organisation codes exist for the current fiscal year, so hierarchy-link coverage is not yet in scope.',
                'index.php?route=dataobjectcodes/hierarchy',
                'Data Object Hierarchy',
                'Load the current fiscal year organisation codes first, then rerun readiness to assess parent-child hierarchy coverage.'
            );
        }

        if ($codeCount > 1 && $treeCount <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Hierarchy Links',
                $codeCount,
                $codeCount,
                'critical',
                'Organisation codes exist for the fiscal year, but no hierarchy links are loaded.',
                'index.php?route=dataobjectcodes/hierarchy',
                'Data Object Hierarchy',
                'Load or rebuild the data object tree so scope expansion, hierarchy reporting, and descendant access can work correctly.'
            );
        }

        if ($orphanLinks > 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Hierarchy Links',
                $treeCount,
                $orphanLinks,
                'warning',
                $orphanLinks . ' hierarchy link row(s) reference a missing parent or child organisation code.',
                'index.php?route=dataobjectcodes/hierarchy',
                'Data Object Hierarchy',
                'Review the hierarchy load and correct the parent-child links that point to missing organisation codes.'
            );
        }

        return $this->buildCheck(
            'Organisation Structure',
            'Data Object Hierarchy Links',
            $treeCount,
            0,
            'ready',
            $treeCount . ' hierarchy link row(s) are available for the current fiscal year.',
            'index.php?route=dataobjectcodes/hierarchy',
            'Data Object Hierarchy',
            ''
        );
    }

    private function checkDataObjectParentReferences(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblDataObjectCodes')) {
            return $this->missingTableCheck(
                'Organisation Structure',
                'Data Object Parent References',
                'The data object codes table is missing.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Create the data object codes table and load parent-child organisation code relationships.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Parent References',
                0,
                0,
                'info',
                'No current fiscal year is selected, so data object parent references cannot be checked.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Set a fiscal year context first, then rerun readiness to validate parent references.'
            );
        }

        $codeCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectCodes WHERE FiscalYearID = :fy', [':fy' => $fiscalYearId]);
        if ($codeCount <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Parent References',
                0,
                0,
                'info',
                'No organisation codes exist for the current fiscal year, so parent-reference checks are not yet in scope.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Load the current fiscal year organisation codes first, then rerun readiness.'
            );
        }

        if (!$this->tableExists('dbo.tblDataObjectTypes')) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Parent References',
                $codeCount,
                1,
                'warning',
                'Data object parent levels cannot be checked because data object types are not available.',
                'index.php?route=dataobject-types/list',
                'Data Object Types',
                'Load data object types so parent-child level rules can be validated.'
            );
        }

        $invalidParentCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectCodes child
            LEFT JOIN dbo.tblDataObjectCodes parent
                ON parent.FiscalYearID = child.FiscalYearID
               AND parent.DataObjectCode = child.DataObjectCodeParent
            LEFT JOIN dbo.tblDataObjectTypes childType
                ON childType.DataObjectTypeID = child.DataObjectTypeID
            LEFT JOIN dbo.tblDataObjectTypes parentType
                ON parentType.DataObjectTypeID = parent.DataObjectTypeID
            WHERE child.FiscalYearID = :fy
              AND (
                    child.DataObjectCodeParent = child.DataObjectCode
                    OR (
                        NULLIF(LTRIM(RTRIM(ISNULL(child.DataObjectCodeParent, N''))), N'') IS NOT NULL
                        AND parent.DataObjectCode IS NULL
                    )
                    OR (
                        NULLIF(LTRIM(RTRIM(ISNULL(child.DataObjectCodeParent, N''))), N'') IS NULL
                        AND childType.[Level] > (
                            SELECT MIN(rootType.[Level])
                            FROM dbo.tblDataObjectTypes rootType
                        )
                    )
                    OR (
                        parent.DataObjectCode IS NOT NULL
                        AND ISNULL(parentType.DataContainer, 1) = 0
                    )
                    OR (
                        parent.DataObjectCode IS NOT NULL
                        AND childType.[Level] IS NOT NULL
                        AND parentType.[Level] IS NOT NULL
                        AND parentType.[Level] >= childType.[Level]
                    )
                  )
        ", [':fy' => $fiscalYearId]);

        if ($invalidParentCount > 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Parent References',
                $codeCount,
                $invalidParentCount,
                'critical',
                $invalidParentCount . ' organisation code parent reference(s) are invalid, missing, blank for a child level, self-referencing, terminal, or not above the child level.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Review Data Object Codes and correct parent values so every child points to an existing higher-level container code in the same fiscal year.'
            );
        }

        return $this->buildCheck(
            'Organisation Structure',
            'Data Object Parent References',
            $codeCount,
            0,
            'ready',
            'Organisation code parent references resolve to valid higher-level container codes.',
            'index.php?route=dataobjectcodes/index',
            'Data Object Codes',
            ''
        );
    }

    private function checkDataObjectContainerChildren(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblDataObjectCodes') || !$this->tableExists('dbo.tblDataObjectTypes')) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Container Children',
                0,
                0,
                'info',
                'Container-child coverage cannot be checked until data object codes and data object types are available.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Load data object types and current fiscal year data object codes first, then rerun readiness.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Container Children',
                0,
                0,
                'info',
                'No current fiscal year is selected, so container-child coverage cannot be checked.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Set a fiscal year context first, then rerun readiness to validate container-child coverage.'
            );
        }

        $containerTypeCodeCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM (
                SELECT parent.DataObjectTypeID
                FROM dbo.tblDataObjectCodes parent
                INNER JOIN dbo.tblDataObjectTypes parentType
                    ON parentType.DataObjectTypeID = parent.DataObjectTypeID
                WHERE parent.FiscalYearID = :fy
                  AND ISNULL(parentType.DataContainer, 1) = 1
                GROUP BY parent.DataObjectTypeID
            ) typed
        ", [':fy' => $fiscalYearId]);

        if ($containerTypeCodeCount <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Container Children',
                0,
                0,
                'info',
                'No current fiscal year organisation codes use a container data object type.',
                'index.php?route=dataobject-types/list',
                'Data Object Types',
                'Mark parent-capable data object types as containers if this hierarchy should include parent nodes.'
            );
        }

        $containerTypesWithoutAnyParentCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectTypes parentType
            WHERE ISNULL(parentType.DataContainer, 1) = 1
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblDataObjectCodes typeCode
                    WHERE typeCode.FiscalYearID = :fyType
                      AND typeCode.DataObjectTypeID = parentType.DataObjectTypeID
              )
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblDataObjectCodes parent
                    INNER JOIN dbo.tblDataObjectCodes child
                        ON child.FiscalYearID = parent.FiscalYearID
                       AND child.DataObjectCodeParent = parent.DataObjectCode
                    WHERE parent.FiscalYearID = :fyParent
                      AND parent.DataObjectTypeID = parentType.DataObjectTypeID
              )
        ", [
            ':fyType' => $fiscalYearId,
            ':fyParent' => $fiscalYearId,
        ]);

        if ($containerTypesWithoutAnyParentCount > 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Container Children',
                $containerTypeCodeCount,
                $containerTypesWithoutAnyParentCount,
                'warning',
                $containerTypesWithoutAnyParentCount . ' container data object type(s) have codes loaded, but none of those codes currently act as parents.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Review parent-capable data object types. Add child codes where that type should include parent nodes, or mark the type as terminal if it should never contain children.'
            );
        }

        return $this->buildCheck(
            'Organisation Structure',
            'Data Object Container Children',
            $containerTypeCodeCount,
            0,
            'ready',
            'Every loaded container data object type has at least one organisation code acting as a parent in the current fiscal year.',
            'index.php?route=dataobjectcodes/index',
            'Data Object Codes',
            ''
        );
    }

    private function checkDataObjectWorkflowStatus(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblDataObjectWorkflowStatus')) {
            return $this->missingTableCheck(
                'Organisation Structure',
                'Data Object Workflow Status Coverage',
                'The data object workflow status table is missing.',
                'index.php?route=dataobjectworkflow/statuses',
                'Workflow Status',
                'Create the workflow status table so organisation-level workflow state can be stored for the active context.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Workflow Status Coverage',
                0,
                0,
                'info',
                'No current fiscal year/version context is selected, so workflow-status coverage cannot be checked.',
                'index.php?route=dataobjectworkflow/statuses',
                'Workflow Status',
                'Set both fiscal year and version context first, then rerun readiness to validate workflow status coverage.'
            );
        }

        $codeCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectCodes WHERE FiscalYearID = :fy', [':fy' => $fiscalYearId]);
        if ($codeCount <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Workflow Status Coverage',
                0,
                0,
                'info',
                'No organisation codes exist for the current fiscal year, so workflow-status coverage is not yet in scope.',
                'index.php?route=dataobjectcodes/index',
                'Data Object Codes',
                'Load organisation codes first, then rerun readiness to assess workflow status coverage.'
            );
        }

        $statusCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectWorkflowStatus
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        $missingCoverage = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectCodes c
            WHERE c.FiscalYearID = :codeFy
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblDataObjectWorkflowStatus w
                    WHERE w.FiscalYearID = :statusFy
                      AND w.VersionID = :statusVer
                      AND w.DataObjectCode = c.DataObjectCode
              )
        ", [
            ':codeFy' => $fiscalYearId,
            ':statusFy' => $fiscalYearId,
            ':statusVer' => $versionId,
        ]);

        if ($statusCount <= 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Workflow Status Coverage',
                $codeCount,
                $codeCount,
                'warning',
                'No organisation workflow statuses are set for the current fiscal year/version.',
                'index.php?route=dataobjectworkflow/statuses',
                'Workflow Status',
                'Initialize or update organisation workflow statuses for the active fiscal year/version so approvals and submission state can be tracked consistently.'
            );
        }

        if ($missingCoverage > 0) {
            return $this->buildCheck(
                'Organisation Structure',
                'Data Object Workflow Status Coverage',
                $codeCount,
                $missingCoverage,
                'warning',
                $missingCoverage . ' organisation code(s) do not yet have a workflow status for the current context.',
                'index.php?route=dataobjectworkflow/statuses',
                'Workflow Status',
                'Review workflow initialization for the active fiscal year/version and fill in the missing organisation statuses.'
            );
        }

        return $this->buildCheck(
            'Organisation Structure',
            'Data Object Workflow Status Coverage',
            $statusCount,
            0,
            'ready',
            'Organisation workflow statuses are present for the current fiscal year/version context.',
            'index.php?route=dataobjectworkflow/statuses',
            'Workflow Status',
            ''
        );
    }

    private function checkSegments(): array
    {
        if (!$this->tableExists('dbo.tblSegments')) {
            return $this->missingTableCheck(
                'Segments And Dimensions',
                'Segments Configured',
                'The segments table is missing.',
                'index.php?route=segments/list',
                'Segments',
                'Create the segments table and define the base segment catalogue before loading segment values.'
            );
        }

        $count = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblSegments');
        if ($count <= 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segments Configured',
                0,
                1,
                'critical',
                'No segment definitions are configured.',
                'index.php?route=segments/list',
                'Segments',
                'Create the segment definitions first so segment values, transaction mapping, and source-driven modules can work.'
            );
        }

        return $this->buildCheck(
            'Segments And Dimensions',
            'Segments Configured',
            $count,
            0,
            'ready',
            $count . ' segment definition(s) are available.',
            'index.php?route=segments/list',
            'Segments',
            ''
        );
    }

    private function checkSegmentDefinitionHealth(): array
    {
        if (!$this->tableExists('dbo.tblSegments')) {
            return $this->missingTableCheck(
                'Segments And Dimensions',
                'Segment Definition Health',
                'The segments table is missing.',
                'index.php?route=segments/list',
                'Segments',
                'Create the segments table before validating segment-definition completeness.'
            );
        }

        $segmentCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblSegments');
        if ($segmentCount <= 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Definition Health',
                0,
                0,
                'info',
                'Segment definition health becomes active once segment rows have been loaded.',
                'index.php?route=segments/list',
                'Segments',
                'Load the segment catalogue first, then rerun readiness to validate layout, dimension, and usage coverage.'
            );
        }

        $requiredColumns = [
            'SegmentCode',
            'SegmentName',
            'StartPoint',
            'EndPoint',
            'CBMSDimension',
            'SegmentGroup',
            'UsedInFinancialAccount',
            'UsedInStrategicPlanning',
            'UsedInOrgStructure',
        ];
        $missingColumns = [];
        foreach ($requiredColumns as $columnName) {
            if (!$this->columnExists('dbo.tblSegments', $columnName)) {
                $missingColumns[] = $columnName;
            }
        }

        if ($missingColumns !== []) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Definition Health',
                $segmentCount,
                count($missingColumns),
                'warning',
                'The segment catalogue is missing expected structural column(s): ' . implode(', ', $missingColumns) . '.',
                'index.php?route=segments/list',
                'Segments',
                'Update tblSegments so the readiness gate can validate segment code, layout, dimension, and usage completeness consistently.'
            );
        }

        $identityIssueCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegments
            WHERE NULLIF(LTRIM(RTRIM(ISNULL(SegmentCode, ''))), '') IS NULL
               OR NULLIF(LTRIM(RTRIM(ISNULL(SegmentName, ''))), '') IS NULL
        ");

        $layoutIssueCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegments
            WHERE (StartPoint IS NOT NULL AND StartPoint <= 0)
               OR (EndPoint IS NOT NULL AND EndPoint <= 0)
               OR (StartPoint IS NOT NULL AND EndPoint IS NOT NULL AND EndPoint < StartPoint)
               OR (MinLength IS NOT NULL AND MaxLength IS NOT NULL AND MinLength > MaxLength)
        ");

        $dimensionIssueCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegments
            WHERE NULLIF(LTRIM(RTRIM(ISNULL(CBMSDimension, ''))), '') IS NULL
        ");

        $groupIssueCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegments
            WHERE NULLIF(LTRIM(RTRIM(ISNULL(SegmentGroup, ''))), '') IS NULL
        ");

        $usageIssueCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegments
            WHERE ISNULL(UsedInFinancialAccount, 0) = 0
              AND ISNULL(UsedInStrategicPlanning, 0) = 0
              AND ISNULL(UsedInOrgStructure, 0) = 0
        ");

        $issueSegmentCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegments
            WHERE NULLIF(LTRIM(RTRIM(ISNULL(SegmentCode, ''))), '') IS NULL
               OR NULLIF(LTRIM(RTRIM(ISNULL(SegmentName, ''))), '') IS NULL
               OR (StartPoint IS NOT NULL AND StartPoint <= 0)
               OR (EndPoint IS NOT NULL AND EndPoint <= 0)
               OR (StartPoint IS NOT NULL AND EndPoint IS NOT NULL AND EndPoint < StartPoint)
               OR (MinLength IS NOT NULL AND MaxLength IS NOT NULL AND MinLength > MaxLength)
               OR NULLIF(LTRIM(RTRIM(ISNULL(CBMSDimension, ''))), '') IS NULL
               OR NULLIF(LTRIM(RTRIM(ISNULL(SegmentGroup, ''))), '') IS NULL
               OR (
                    ISNULL(UsedInFinancialAccount, 0) = 0
                    AND ISNULL(UsedInStrategicPlanning, 0) = 0
                    AND ISNULL(UsedInOrgStructure, 0) = 0
               )
        ");

        if ($issueSegmentCount > 0) {
            $parts = [];
            if ($identityIssueCount > 0) {
                $parts[] = $identityIssueCount . ' missing code/name';
            }
            if ($layoutIssueCount > 0) {
                $parts[] = $layoutIssueCount . ' layout issue(s)';
            }
            if ($dimensionIssueCount > 0) {
                $parts[] = $dimensionIssueCount . ' missing dimension';
            }
            if ($groupIssueCount > 0) {
                $parts[] = $groupIssueCount . ' missing group';
            }
            if ($usageIssueCount > 0) {
                $parts[] = $usageIssueCount . ' with no usage flag';
            }

            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Definition Health',
                $segmentCount,
                $issueSegmentCount,
                'warning',
                $issueSegmentCount . ' segment definition(s) have structural gaps: ' . implode('; ', $parts) . '.',
                'index.php?route=segments/list',
                'Segments',
                'Complete the missing segment metadata so numbering, dimensions, grouping, and downstream module mapping all behave consistently.'
            );
        }

        return $this->buildCheck(
            'Segments And Dimensions',
            'Segment Definition Health',
            $segmentCount,
            0,
            'ready',
            'All segment definitions expose usable code, naming, layout, grouping, dimension, and usage metadata.',
            'index.php?route=segments/list',
            'Segments',
            ''
        );
    }

    private function checkSegmentValues(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblSegments')) {
            return $this->missingTableCheck(
                'Segments And Dimensions',
                'Segment Values Loaded',
                'The segments table is missing.',
                'index.php?route=segments/list',
                'Segments',
                'Create the segment catalogue before validating segment value coverage.'
            );
        }

        if (!$this->tableExists('dbo.tblSegmentValues')) {
            return $this->missingTableCheck(
                'Segments And Dimensions',
                'Segment Values Loaded',
                'The segment values table is missing.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Create the segment values table and load the source values for the fiscal year that will be used.'
            );
        }

        $segmentCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblSegments');
        if ($segmentCount <= 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Values Loaded',
                0,
                0,
                'info',
                'Segment value coverage becomes active once segment definitions have been loaded.',
                'index.php?route=segments/list',
                'Segments',
                'Load the segment catalogue first, then load values for each configured segment.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Values Loaded',
                0,
                0,
                'info',
                'No current fiscal year is selected, so fiscal-year-specific segment value coverage cannot be checked.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Set a fiscal year context first, then rerun readiness to validate segment values for that year.'
            );
        }

        $count = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblSegmentValues WHERE FiscalYearID = :fy AND ActiveFlag = 1', [':fy' => $fiscalYearId]);
        $segmentsWithValues = $this->fetchCount("
            SELECT COUNT(*)
            FROM (
                SELECT DISTINCT SegmentNo
                FROM dbo.tblSegmentValues
                WHERE FiscalYearID = :fy
                  AND ActiveFlag = 1
            ) v
        ", [':fy' => $fiscalYearId]);

        if ($count <= 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Values Loaded',
                0,
                1,
                'critical',
                'No active segment values are loaded for the current fiscal year.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Load the current fiscal year segment values before users try to map dimensions or import source-backed records.'
            );
        }

        $missingRows = $this->fetchRows("
            SELECT
                s.SegmentID,
                s.SegmentCode,
                s.SegmentName
            FROM dbo.tblSegments s
            WHERE NOT EXISTS (
                SELECT 1
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.ActiveFlag = 1
                  AND sv.SegmentNo = s.SegmentID
            )
            ORDER BY s.SegmentID
        ", [':fy' => $fiscalYearId]);

        if ($missingRows !== []) {
            $preview = [];
            foreach (array_slice($missingRows, 0, 5) as $row) {
                $code = trim((string) ($row['SegmentCode'] ?? ''));
                $name = trim((string) ($row['SegmentName'] ?? ''));
                $preview[] = trim((string) ($row['SegmentID'] ?? '') . ' ' . ($code !== '' ? $code : $name));
            }
            $suffix = count($missingRows) > 5 ? ' and ' . (count($missingRows) - 5) . ' more' : '';

            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Values Loaded',
                $count,
                count($missingRows),
                'critical',
                $segmentsWithValues . ' of ' . $segmentCount . ' configured segment(s) have active values for the current fiscal year. Missing: ' . implode(', ', $preview) . $suffix . '.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Load active current-year values for every configured segment before marking base configuration ready.'
            );
        }

        return $this->buildCheck(
            'Segments And Dimensions',
            'Segment Values Loaded',
            $count,
            0,
            'ready',
            $count . ' active segment value row(s) are loaded across all ' . $segmentCount . ' configured segment(s) for the current fiscal year.',
            'index.php?route=segment-values/list',
            'Segment Values',
            ''
        );
    }

    private function checkSegmentValueDuplicates(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblSegmentValues')) {
            return $this->missingTableCheck(
                'Segments And Dimensions',
                'Segment Value Key Health',
                'The segment values table is missing.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Create the segment values table before checking for duplicate source keys.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Value Key Health',
                0,
                0,
                'info',
                'No current fiscal year is selected, so duplicate segment value keys cannot be checked for a specific year.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Set a fiscal year context first, then rerun readiness to review duplicate source keys for that year.'
            );
        }

        $duplicateKeys = $this->fetchCount("
            SELECT COUNT(*)
            FROM (
                SELECT FiscalYearID, DataObjectCode, SegmentNo, SegmentCode
                FROM dbo.tblSegmentValues
                WHERE FiscalYearID = :fy
                  AND ActiveFlag = 1
                GROUP BY FiscalYearID, DataObjectCode, SegmentNo, SegmentCode
                HAVING COUNT(*) > 1
            ) d
        ", [':fy' => $fiscalYearId]);

        $rowCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblSegmentValues WHERE FiscalYearID = :fy AND ActiveFlag = 1', [':fy' => $fiscalYearId]);
        if ($duplicateKeys > 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Value Key Health',
                $rowCount,
                $duplicateKeys,
                'warning',
                $duplicateKeys . ' active duplicate segment key combination(s) exist for the current fiscal year.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Review the current fiscal year segment values and remove or merge duplicate active rows that share the same fiscal year, scope, segment, and code.'
            );
        }

        return $this->buildCheck(
            'Segments And Dimensions',
            'Segment Value Key Health',
            $rowCount,
            0,
            'ready',
            'No duplicate active segment value keys were found for the current fiscal year.',
            'index.php?route=segment-values/list',
            'Segment Values',
            ''
        );
    }

    private function checkSegmentHierarchySupport(): array
    {
        if (!$this->tableExists('dbo.tblSegmentValues')) {
            return $this->missingTableCheck(
                'Segments And Dimensions',
                'Segment Hierarchy Support',
                'The segment values table is missing.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Create the segment values table before validating parent-child support for hierarchical segments.'
            );
        }

        $hasParentNo = $this->columnExists('dbo.tblSegmentValues', 'ParentSegmentNo');
        $hasParentCode = $this->columnExists('dbo.tblSegmentValues', 'ParentSegmentCode');

        if (!$hasParentNo || !$hasParentCode) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Segment Hierarchy Support',
                2,
                2 - (($hasParentNo ? 1 : 0) + ($hasParentCode ? 1 : 0)),
                'warning',
                'Parent segment support is incomplete because one or more parent-link columns are missing on tblSegmentValues.',
                'index.php?route=segment-values/list',
                'Segment Values',
                'Run the parent-segment migration for tblSegmentValues so hierarchical segment imports and parent-child validations can work.'
            );
        }

        return $this->buildCheck(
            'Segments And Dimensions',
            'Segment Hierarchy Support',
            2,
            0,
            'ready',
            'Parent segment fields are available on tblSegmentValues for hierarchical segment structures.',
            'index.php?route=segment-values/list',
            'Segment Values',
            ''
        );
    }

    private function checkMissingRequiredSegmentParents(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblSegments') || !$this->tableExists('dbo.tblSegmentValues')) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Required Parent Segment Links',
                0,
                0,
                'info',
                'Required parent-link coverage cannot be checked until both tblSegments and tblSegmentValues are available.',
                'index.php?route=strategy-reports/segment-parent-child',
                'Parent Child Check',
                'Make sure both segment definitions and segment values are loaded, then rerun readiness.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Required Parent Segment Links',
                0,
                0,
                'info',
                'No current fiscal year is selected, so required parent-link coverage cannot be checked for a specific year.',
                'index.php?route=strategy-reports/segment-parent-child',
                'Parent Child Check',
                'Set a fiscal year context first, then rerun readiness to check parent coverage for that year.'
            );
        }

        if (!$this->columnExists('dbo.tblSegmentValues', 'ParentSegmentNo') || !$this->columnExists('dbo.tblSegmentValues', 'ParentSegmentCode')) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Required Parent Segment Links',
                0,
                0,
                'info',
                'Parent-link columns are not available, so required parent coverage cannot be checked.',
                'index.php?route=strategy-reports/segment-parent-child',
                'Parent Child Check',
                'Run the parent-segment migration first, then rerun readiness to validate required parent links.'
            );
        }

        $hasParentSegmentValueID = $this->columnExists('dbo.tblSegmentValues', 'ParentSegmentValueID');

        $requiredRows = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegmentValues sv
            INNER JOIN dbo.tblSegments s
                ON s.SegmentID = sv.SegmentNo
            WHERE sv.FiscalYearID = :fy
              AND sv.ActiveFlag = 1
              AND ISNULL(s.ParentRequired, 0) = 1
              AND ISNULL(s.ParentSegmentNoDefault, 0) > 0
        ", [':fy' => $fiscalYearId]);

        $actionRoute = 'index.php?route=strategy-reports/segment-parent-child';
        $firstPairRows = $this->fetchRows("
            SELECT TOP 1
                ChildSegmentNo = sv.SegmentNo,
                ParentSegmentNo = s.ParentSegmentNoDefault
            FROM dbo.tblSegmentValues sv
            INNER JOIN dbo.tblSegments s
                ON s.SegmentID = sv.SegmentNo
            " . ($hasParentSegmentValueID ? "
            LEFT JOIN dbo.tblSegmentValues parent
                ON parent.SegmentValueID = sv.ParentSegmentValueID
               AND parent.FiscalYearID = sv.FiscalYearID
               AND parent.ActiveFlag = 1
            " : "") . "
            WHERE sv.FiscalYearID = :fy
              AND sv.ActiveFlag = 1
              AND ISNULL(s.ParentRequired, 0) = 1
              AND ISNULL(s.ParentSegmentNoDefault, 0) > 0
              AND (
                    (
                        sv.ParentSegmentNo IS NULL
                        OR sv.ParentSegmentNo = 0
                        OR sv.ParentSegmentNo <> s.ParentSegmentNoDefault
                        OR NULLIF(LTRIM(RTRIM(ISNULL(sv.ParentSegmentCode, ''))), '') IS NULL
                    )
                    " . ($hasParentSegmentValueID ? "
                    AND parent.SegmentValueID IS NULL
                    " : "") . "
              )
            ORDER BY sv.SegmentNo
        ", [':fy' => $fiscalYearId]);
        $firstPair = $firstPairRows[0] ?? [];
        $firstChildSegmentNo = (int) ($firstPair['ChildSegmentNo'] ?? 0);
        $firstParentSegmentNo = (int) ($firstPair['ParentSegmentNo'] ?? 0);
        if ($firstChildSegmentNo > 0 && $firstParentSegmentNo > 0) {
            $actionRoute = 'index.php?' . http_build_query([
                'route' => 'strategy-reports/segment-parent-child',
                'child_segment_no' => $firstChildSegmentNo,
                'parent_segment_no' => $firstParentSegmentNo,
                'same_data_object' => 1,
                'check_prefix' => 1,
            ]);
        }

        $missingParents = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblSegmentValues sv
            INNER JOIN dbo.tblSegments s
                ON s.SegmentID = sv.SegmentNo
            " . ($hasParentSegmentValueID ? "
            LEFT JOIN dbo.tblSegmentValues parent
                ON parent.SegmentValueID = sv.ParentSegmentValueID
               AND parent.FiscalYearID = sv.FiscalYearID
               AND parent.ActiveFlag = 1
            " : "") . "
            WHERE sv.FiscalYearID = :fy
              AND sv.ActiveFlag = 1
              AND ISNULL(s.ParentRequired, 0) = 1
              AND ISNULL(s.ParentSegmentNoDefault, 0) > 0
              AND (
                    (
                        sv.ParentSegmentNo IS NULL
                        OR sv.ParentSegmentNo = 0
                        OR sv.ParentSegmentNo <> s.ParentSegmentNoDefault
                        OR NULLIF(LTRIM(RTRIM(ISNULL(sv.ParentSegmentCode, ''))), '') IS NULL
                    )
                    " . ($hasParentSegmentValueID ? "
                    AND parent.SegmentValueID IS NULL
                    " : "") . "
              )
        ", [':fy' => $fiscalYearId]);

        if ($requiredRows <= 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Required Parent Segment Links',
                0,
                0,
                'info',
                'No active current-year segment values currently require a parent link.',
                $actionRoute,
                'Parent Child Check',
                'This check will become active when parent-required segment rows are loaded for the fiscal year.'
            );
        }

        if ($missingParents > 0) {
            return $this->buildCheck(
                'Segments And Dimensions',
                'Required Parent Segment Links',
                $requiredRows,
                $missingParents,
                'warning',
                $missingParents . ' parent-required segment value row(s) are missing a usable parent segment link.',
                $actionRoute,
                'Parent Child Check',
                'Open the Segment Parent-Child Check screen and use Resolve Parent Links, then review any remaining issue rows.'
            );
        }

        return $this->buildCheck(
            'Segments And Dimensions',
            'Required Parent Segment Links',
            $requiredRows,
            0,
            'ready',
            'All parent-required current-year segment values have a usable parent link.',
            $actionRoute,
            'Parent Child Check',
            ''
        );
    }

    private function checkRoles(): array
    {
        if (!$this->tableExists('dbo.tblRoles')) {
            return $this->missingTableCheck(
                'Security And Access',
                'Roles Configured',
                'The roles table is missing.',
                'index.php?route=roles/list',
                'Roles',
                'Create the roles table and define the base security roles before assigning users.'
            );
        }

        $count = $this->columnExists('dbo.tblRoles', 'Active')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblRoles WHERE Active = 1')
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblRoles');

        if ($count <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'Roles Configured',
                0,
                1,
                'critical',
                'No active roles are configured.',
                'index.php?route=roles/list',
                'Roles',
                'Create the core security roles first so permissions and user-role assignments can be maintained.'
            );
        }

        return $this->buildCheck(
            'Security And Access',
            'Roles Configured',
            $count,
            0,
            'ready',
            $count . ' active role(s) are configured.',
            'index.php?route=roles/list',
            'Roles',
            ''
        );
    }

    private function checkPermissionsCatalog(): array
    {
        if (!$this->tableExists('dbo.tblPermissions')) {
            return $this->buildCheck(
                'Security And Access',
                'Permissions Catalog Configured',
                0,
                1,
                'critical',
                'The permissions catalog table is missing.',
                'index.php?route=roles/list',
                'Roles',
                'Load the permissions catalog before assigning role-permission combinations so security rules can resolve correctly.'
            );
        }

        $count = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblPermissions');
        if ($count <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'Permissions Catalog Configured',
                0,
                1,
                'critical',
                'No permission records are configured.',
                'index.php?route=roles/list',
                'Roles',
                'Load the base permission codes first, then assign those permissions to the roles that should grant access.'
            );
        }

        return $this->buildCheck(
            'Security And Access',
            'Permissions Catalog Configured',
            $count,
            0,
            'ready',
            $count . ' permission record(s) are available for role assignment.',
            'index.php?route=roles/list',
            'Roles',
            ''
        );
    }

    private function checkRolePermissions(): array
    {
        if (!$this->tableExists('dbo.tblRoles') || !$this->tableExists('dbo.tblRolePermissions')) {
            return $this->buildCheck(
                'Security And Access',
                'Role Permission Coverage',
                0,
                1,
                'critical',
                'Role permissions cannot be validated because tblRoles or tblRolePermissions is missing.',
                'index.php?route=roles/list',
                'Roles',
                'Make sure both roles and role-permission tables exist, then assign permissions to the roles that should grant access.'
            );
        }

        $activeRoleCount = $this->columnExists('dbo.tblRoles', 'Active')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblRoles WHERE Active = 1')
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblRoles');

        $activeRolePredicate = $this->columnExists('dbo.tblRoles', 'Active') ? 'ISNULL(r.Active, 1) = 1' : '1 = 1';
        $rolesWithoutPermissions = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblRoles r
            WHERE {$activeRolePredicate}
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblRolePermissions rp
                    WHERE rp.RoleID = r.RoleID
              )
        ");

        if ($activeRoleCount <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'Role Permission Coverage',
                0,
                0,
                'info',
                'No active roles exist yet, so role-permission coverage is not in scope.',
                'index.php?route=roles/list',
                'Roles',
                'Create the base roles first, then assign permissions and rerun readiness.'
            );
        }

        if ($rolesWithoutPermissions > 0) {
            return $this->buildCheck(
                'Security And Access',
                'Role Permission Coverage',
                $activeRoleCount,
                $rolesWithoutPermissions,
                'warning',
                $rolesWithoutPermissions . ' active role(s) do not have any permissions assigned.',
                'index.php?route=roles/list',
                'Roles',
                'Review the active roles and assign the permissions they need so users do not end up with empty or inconsistent access.'
            );
        }

        return $this->buildCheck(
            'Security And Access',
            'Role Permission Coverage',
            $activeRoleCount,
            0,
            'ready',
            'All active roles have at least one permission assigned.',
            'index.php?route=roles/list',
            'Roles',
            ''
        );
    }

    private function checkRolePermissionIntegrity(): array
    {
        if (!$this->tableExists('dbo.tblRolePermissions') || !$this->tableExists('dbo.tblRoles') || !$this->tableExists('dbo.tblPermissions')) {
            return $this->buildCheck(
                'Security And Access',
                'Role Permission Integrity',
                0,
                1,
                'critical',
                'Role-permission integrity cannot be checked because roles, permissions, or role-permission tables are missing.',
                'index.php?route=roles/list',
                'Roles',
                'Make sure the roles, permissions, and role-permission tables are all present before validating security assignments.'
            );
        }

        $assignmentCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblRolePermissions');
        if ($assignmentCount <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'Role Permission Integrity',
                0,
                0,
                'info',
                'No role-permission assignments exist yet, so referential integrity issues are not currently in scope.',
                'index.php?route=roles/list',
                'Roles',
                'Assign permissions to roles first, then rerun readiness to validate the resulting role-permission assignments.'
            );
        }

        $orphanAssignments = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblRolePermissions rp
            LEFT JOIN dbo.tblRoles r
                ON r.RoleID = rp.RoleID
            LEFT JOIN dbo.tblPermissions p
                ON p.PermissionID = rp.PermissionID
            WHERE r.RoleID IS NULL
               OR p.PermissionID IS NULL
        ");

        if ($orphanAssignments > 0) {
            return $this->buildCheck(
                'Security And Access',
                'Role Permission Integrity',
                $assignmentCount,
                $orphanAssignments,
                'warning',
                $orphanAssignments . ' role-permission assignment(s) point to a missing role or permission.',
                'index.php?route=roles/list',
                'Roles',
                'Review the role-permission assignments and remove or repair any rows that reference deleted or missing roles or permissions.'
            );
        }

        return $this->buildCheck(
            'Security And Access',
            'Role Permission Integrity',
            $assignmentCount,
            0,
            'ready',
            'All role-permission assignments point to valid roles and permissions.',
            'index.php?route=roles/list',
            'Roles',
            ''
        );
    }

    private function checkUsersConfigured(): array
    {
        if (!$this->tableExists('dbo.tblUsers')) {
            return $this->buildCheck(
                'Security And Access',
                'Users Configured',
                0,
                1,
                'critical',
                'The users table is missing.',
                'index.php?route=users/list',
                'Users',
                'Create the users table and load at least one administrator-capable user account before testing sign-in or permissions.'
            );
        }

        $activeUserPredicate = $this->columnExists('dbo.tblUsers', 'IsActive') ? 'WHERE IsActive = 1' : '';
        $count = $this->fetchCount("SELECT COUNT(*) FROM dbo.tblUsers {$activeUserPredicate}");

        if ($count <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'Users Configured',
                0,
                1,
                'critical',
                'No active users are configured.',
                'index.php?route=users/list',
                'Users',
                'Create or activate user accounts so login, access testing, and workflow routing can be exercised properly.'
            );
        }

        return $this->buildCheck(
            'Security And Access',
            'Users Configured',
            $count,
            0,
            'ready',
            $count . ' active user account(s) are configured.',
            'index.php?route=users/list',
            'Users',
            ''
        );
    }

    private function checkUserRoleCoverage(): array
    {
        if (!$this->tableExists('dbo.tblUsers') || !$this->tableExists('dbo.tblUserRoles')) {
            return $this->buildCheck(
                'Security And Access',
                'User Role Coverage',
                0,
                1,
                'critical',
                'User-role coverage cannot be checked because tblUsers or tblUserRoles is missing.',
                'index.php?route=users/list',
                'Users',
                'Make sure both the user table and user-role table exist, then assign at least one role to each active user.'
            );
        }

        $activeUserPredicate = $this->columnExists('dbo.tblUsers', 'IsActive') ? 'WHERE IsActive = 1' : '';
        $activeUserCount = $this->fetchCount("SELECT COUNT(*) FROM dbo.tblUsers {$activeUserPredicate}");
        $usersWithoutRoles = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblUsers u
            WHERE " . ($this->columnExists('dbo.tblUsers', 'IsActive') ? 'u.IsActive = 1 AND ' : '') . "
                  NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblUserRoles ur
                    WHERE ur.UserID = u.UserID
              )
        ");

        if ($activeUserCount <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'User Role Coverage',
                0,
                1,
                'warning',
                'No active users are configured.',
                'index.php?route=users/list',
                'Users',
                'Create or activate user accounts before testing security, workflow, and scoped access.'
            );
        }

        if ($usersWithoutRoles > 0) {
            return $this->buildCheck(
                'Security And Access',
                'User Role Coverage',
                $activeUserCount,
                $usersWithoutRoles,
                'warning',
                $usersWithoutRoles . ' active user(s) do not have any role assigned.',
                'index.php?route=users/list',
                'Users',
                'Review active users and assign at least one role to each user who should be able to access the system.'
            );
        }

        return $this->buildCheck(
            'Security And Access',
            'User Role Coverage',
            $activeUserCount,
            0,
            'ready',
            'All active users currently have at least one role assigned.',
            'index.php?route=users/list',
            'Users',
            ''
        );
    }

    private function checkScopedAccessReadiness(): array
    {
        $hasCodeAccess = $this->tableExists('dbo.tblDataObjectCodeAccess');
        $hasUserAccess = $this->tableExists('dbo.tblUserDataObjectAccess');

        if (!$hasCodeAccess && !$hasUserAccess) {
            return $this->buildCheck(
                'Security And Access',
                'Scoped Access Readiness',
                0,
                0,
                'info',
                'Scoped DataScope access tables are not installed. This is only needed if you want to manage explicit organisation-code access.',
                'index.php?route=dataobjectcodes/access',
                'DataScope Access',
                'If you plan to control access by organisation code, install and configure the scoped access tables and then rerun readiness.'
            );
        }

        if (!$hasCodeAccess || !$hasUserAccess) {
            return $this->buildCheck(
                'Security And Access',
                'Scoped Access Readiness',
                (int) $hasCodeAccess + (int) $hasUserAccess,
                1,
                'warning',
                'Scoped DataScope access is only partially configured because one of the required access tables is missing.',
                'index.php?route=dataobjectcodes/access',
                'DataScope Access',
                'Install both scoped access tables so organisation-code access can be maintained consistently for users and rules.'
            );
        }

        $codeAccessCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectCodeAccess');
        $userAccessCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblUserDataObjectAccess');

        return $this->buildCheck(
            'Security And Access',
            'Scoped Access Readiness',
            $codeAccessCount + $userAccessCount,
            0,
            'ready',
            'Scoped access tables are available' . (($codeAccessCount + $userAccessCount) > 0 ? ' and currently contain ' . ($codeAccessCount + $userAccessCount) . ' access record(s).' : ', but no scoped access records have been loaded yet.'),
            'index.php?route=dataobjectcodes/access',
            'DataScope Access',
            ''
        );
    }

    private function checkScopedAccessCoverage(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblDataObjectCodeAccess')) {
            return $this->buildCheck(
                'Security And Access',
                'Scoped Access Coverage',
                0,
                0,
                'info',
                'Scoped access coverage becomes active once direct DataScope access tables are installed.',
                'index.php?route=dataobjectcodes/access',
                'DataScope Access',
                'Install the scoped access tables first, then rerun readiness to validate coverage and data quality.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'Scoped Access Coverage',
                0,
                0,
                'info',
                'No current fiscal year is selected, so scoped access coverage cannot be checked for a specific year.',
                'index.php?route=dataobjectcodes/access',
                'DataScope Access',
                'Set a fiscal year context first, then rerun readiness to review scoped access coverage for that year.'
            );
        }

        $codeCount = $this->tableExists('dbo.tblDataObjectCodes')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectCodes WHERE FiscalYearID = :fy', [':fy' => $fiscalYearId])
            : 0;
        if ($codeCount <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'Scoped Access Coverage',
                0,
                0,
                'info',
                'No organisation codes are loaded for the current fiscal year, so scoped access coverage is not yet in scope.',
                'index.php?route=dataobjectcodes/access',
                'DataScope Access',
                'Load the organisation structure first, then rerun readiness to validate scoped access coverage.'
            );
        }

        $activeUserPredicate = $this->columnExists('dbo.tblUsers', 'IsActive') ? 'WHERE IsActive = 1' : '';
        $activeUserCount = $this->tableExists('dbo.tblUsers')
            ? $this->fetchCount("SELECT COUNT(*) FROM dbo.tblUsers {$activeUserPredicate}")
            : 0;
        $revokedPredicate = $this->columnExists('dbo.tblDataObjectCodeAccess', 'Revoked')
            ? 'ISNULL(a.Revoked, 0) = 0'
            : '1 = 1';

        $directAccessCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectCodeAccess a
            WHERE a.FiscalYearID = :fy
              AND {$revokedPredicate}
        ", [':fy' => $fiscalYearId]);

        $inactiveUserPredicate = $this->columnExists('dbo.tblUsers', 'IsActive')
            ? ' OR ISNULL(u.IsActive, 0) = 0'
            : '';
        $orphanDirectCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectCodeAccess a
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = a.UserID
            LEFT JOIN dbo.tblDataObjectCodes c
                ON c.FiscalYearID = a.FiscalYearID
               AND c.DataObjectCode = a.DataObjectCode
            WHERE a.FiscalYearID = :fy
              AND {$revokedPredicate}
              AND (
                    u.UserID IS NULL{$inactiveUserPredicate}
                    OR c.DataObjectCode IS NULL
              )
        ", [':fy' => $fiscalYearId]);

        $grantCount = 0;
        $orphanGrantCount = 0;
        if ($this->tableExists('dbo.tblUserDataObjectAccess')) {
            $grantCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblUserDataObjectAccess
                WHERE FiscalYearID = :fy
            ", [':fy' => $fiscalYearId]);

            $orphanGrantCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblUserDataObjectAccess a
                LEFT JOIN dbo.tblUsers u
                    ON u.UserID = a.UserID
                WHERE a.FiscalYearID = :fy
                  AND (
                        u.UserID IS NULL{$inactiveUserPredicate}
                        OR NULLIF(LTRIM(RTRIM(ISNULL(a.GrantCode, ''))), '') IS NULL
                  )
            ", [':fy' => $fiscalYearId]);
        }

        if (($orphanDirectCount + $orphanGrantCount) > 0) {
            return $this->buildCheck(
                'Security And Access',
                'Scoped Access Coverage',
                $directAccessCount + $grantCount,
                $orphanDirectCount + $orphanGrantCount,
                'warning',
                ($orphanDirectCount + $orphanGrantCount) . ' scoped-access row(s) point to a missing/inactive user, blank grant, or missing organisation code.',
                'index.php?route=dataobjectcodes/access',
                'DataScope Access',
                'Review scoped access maintenance and repair the rows that no longer point to a valid user, grant, or organisation code.'
            );
        }

        if ($directAccessCount <= 0) {
            if ($activeUserCount <= 1) {
                return $this->buildCheck(
                    'Security And Access',
                    'Scoped Access Coverage',
                    $activeUserCount,
                    0,
                    'info',
                    'No direct scoped access rows are configured yet. This is acceptable while the environment still has only the initial admin-style user.',
                    'index.php?route=dataobjectcodes/access',
                    'DataScope Access',
                    'Add scoped access rows when more users or organisation-specific access rules are introduced.'
                );
            }

            return $this->buildCheck(
                'Security And Access',
                'Scoped Access Coverage',
                $activeUserCount,
                $activeUserCount,
                'warning',
                'No direct scoped access rows are configured for the current fiscal year, even though multiple active users exist.',
                'index.php?route=dataobjectcodes/access',
                'DataScope Access',
                'Grant organisation-code access to the users who should be restricted or routed by scope, then rerun readiness.'
            );
        }

        return $this->buildCheck(
            'Security And Access',
            'Scoped Access Coverage',
            $directAccessCount + $grantCount,
            0,
            'ready',
            'Scoped access is configured with ' . $directAccessCount . ' direct row(s)' . ($grantCount > 0 ? ' and ' . $grantCount . ' grant row(s)' : '') . ' for the current fiscal year.',
            'index.php?route=dataobjectcodes/access',
            'DataScope Access',
            ''
        );
    }

    private function checkAdminCoverage(): array
    {
        if (!$this->tableExists('dbo.tblRoles') || !$this->tableExists('dbo.tblUserRoles') || !$this->tableExists('dbo.tblUsers')) {
            return $this->buildCheck(
                'Security And Access',
                'Administrative Access Coverage',
                0,
                1,
                'critical',
                'Administrative access cannot be validated because one or more user/role tables are missing.',
                'index.php?route=users/list',
                'Users',
                'Ensure the user, role, and user-role tables are available, then confirm at least one administrator can sign in and maintain configuration.'
            );
        }

        $adminUserCount = 0;
        if ($this->tableExists('dbo.tblRolePermissions') && $this->tableExists('dbo.tblPermissions')) {
            $adminUserCount = $this->fetchCount("
                SELECT COUNT(DISTINCT u.UserID)
                FROM dbo.tblUsers u
                INNER JOIN dbo.tblUserRoles ur
                    ON ur.UserID = u.UserID
                INNER JOIN dbo.tblRoles r
                    ON r.RoleID = ur.RoleID
                INNER JOIN dbo.tblRolePermissions rp
                    ON rp.RoleID = r.RoleID
                INNER JOIN dbo.tblPermissions p
                    ON p.PermissionID = rp.PermissionID
                WHERE " . ($this->columnExists('dbo.tblUsers', 'IsActive') ? 'u.IsActive = 1 AND ' : '') . "
                      (
                          p.PermissionCode IN (
                              'ADMIN_ALL',
                              'SYSADMIN',
                              'USERS_ADMIN',
                              'ROLES_ADMIN',
                              'SYSSETTINGS_ADMIN',
                              'BASE_CONFIG_EDIT',
                              'FIN_CONFIG_EDIT',
                              'DATAOBJECTCODES_ADMIN'
                          )
                      )
            ");
        } else {
            $adminUserCount = $this->fetchCount("
                SELECT COUNT(DISTINCT u.UserID)
                FROM dbo.tblUsers u
                INNER JOIN dbo.tblUserRoles ur
                    ON ur.UserID = u.UserID
                INNER JOIN dbo.tblRoles r
                    ON r.RoleID = ur.RoleID
                WHERE " . ($this->columnExists('dbo.tblUsers', 'IsActive') ? 'u.IsActive = 1 AND ' : '') . "
                      LOWER(LTRIM(RTRIM(r.RoleName))) IN (
                          'admin',
                          'administrator',
                          'system administrator',
                          'configuration administrator',
                          'base configuration administrator',
                          'financial configuration administrator',
                          'strategy configuration administrator'
                      )
            ");
        }

        if ($adminUserCount <= 0) {
            return $this->buildCheck(
                'Security And Access',
                'Administrative Access Coverage',
                0,
                1,
                'critical',
                'No active user currently holds an administrative access role or permission bundle.',
                'index.php?route=users/list',
                'Users',
                'Assign System Administrator or an appropriate configuration administration role to at least one active user so the system can still be managed if other users are unavailable.'
            );
        }

        return $this->buildCheck(
            'Security And Access',
            'Administrative Access Coverage',
            $adminUserCount,
            0,
            'ready',
            $adminUserCount . ' active user(s) currently hold administrative access coverage.',
            'index.php?route=users/list',
            'Users',
            ''
        );
    }

    private function checkWorkflowTaskTypes(): array
    {
        if (!$this->tableExists('dbo.tblWorkflowTaskTypes')) {
            return $this->missingTableCheck(
                'Workflow Configuration',
                'Workflow Task Types Configured',
                'The workflow task type table is missing.',
                'index.php?route=workflow-task-types/list',
                'Workflow Task Types',
                'Create the workflow task type table and load the task-type catalogue used by workflow tasks.'
            );
        }

        $count = $this->columnExists('dbo.tblWorkflowTaskTypes', 'IsActive')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblWorkflowTaskTypes WHERE IsActive = 1')
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblWorkflowTaskTypes');

        if ($count <= 0) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Task Types Configured',
                0,
                1,
                'critical',
                'No active workflow task types are configured.',
                'index.php?route=workflow-task-types/list',
                'Workflow Task Types',
                'Load the workflow task type catalogue so tasks can be categorised and routed properly.'
            );
        }

        return $this->buildCheck(
            'Workflow Configuration',
            'Workflow Task Types Configured',
            $count,
            0,
            'ready',
            $count . ' active workflow task type(s) are configured.',
            'index.php?route=workflow-task-types/list',
            'Workflow Task Types',
            ''
        );
    }

    private function checkWorkflowTaskStatuses(): array
    {
        if (!$this->tableExists('dbo.tblWorkflowTaskStatuses')) {
            return $this->missingTableCheck(
                'Workflow Configuration',
                'Workflow Task Statuses Configured',
                'The workflow task status table is missing.',
                'index.php?route=workflow-task-statuses/list',
                'Workflow Task Statuses',
                'Create the workflow task status table and load the statuses used by workflow tasks.'
            );
        }

        $count = $this->columnExists('dbo.tblWorkflowTaskStatuses', 'IsActive')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblWorkflowTaskStatuses WHERE IsActive = 1')
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblWorkflowTaskStatuses');

        if ($count <= 0) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Task Statuses Configured',
                0,
                1,
                'critical',
                'No active workflow task statuses are configured.',
                'index.php?route=workflow-task-statuses/list',
                'Workflow Task Statuses',
                'Load the workflow task statuses so workflow items can move through valid state transitions.'
            );
        }

        return $this->buildCheck(
            'Workflow Configuration',
            'Workflow Task Statuses Configured',
            $count,
            0,
            'ready',
            $count . ' active workflow task status record(s) are configured.',
            'index.php?route=workflow-task-statuses/list',
            'Workflow Task Statuses',
            ''
        );
    }

    private function checkWorkflowEngineFoundation(): array
    {
        $requiredTables = [
            'dbo.tblWorkflowDefinition',
            'dbo.tblWorkflowDefinitionStage',
            'dbo.tblWorkflowDefinitionAction',
            'dbo.tblWorkflowInstance',
            'dbo.tblWorkflowInstanceHistory',
        ];

        $installedCount = 0;
        foreach ($requiredTables as $tableName) {
            if ($this->tableExists($tableName)) {
                $installedCount++;
            }
        }

        if ($installedCount < count($requiredTables)) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Engine Foundation',
                $installedCount,
                count($requiredTables),
                'warning',
                'The shared enterprise workflow engine foundation is not fully installed yet.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Run create_workflow_engine_foundation_v1.sql so definitions, stages, actions, instances, and workflow history are available across the solution.'
            );
        }

        $definitionCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblWorkflowDefinition WHERE ActiveFlag = 1');
        $stageCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblWorkflowDefinitionStage WHERE ActiveFlag = 1');
        $actionCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblWorkflowDefinitionAction WHERE ActiveFlag = 1');

        return $this->buildCheck(
            'Workflow Configuration',
            'Workflow Engine Foundation',
            $definitionCount,
            0,
            'ready',
            $definitionCount . ' workflow definition(s), ' . $stageCount . ' stage(s), and ' . $actionCount . ' action rule(s) are installed for the shared workflow engine.',
            'index.php?route=workflow-assignments/list',
            'Workflow Assignments',
            ''
        );
    }

    private function checkWorkflowAssignmentCoverage(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblWorkflowAssignments')) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Coverage',
                0,
                1,
                'warning',
                'The workflow assignments table is not installed yet.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Install tblWorkflowAssignments so client-specific workflow routing can be configured for the active context.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Coverage',
                0,
                0,
                'info',
                'No current fiscal year/version context is selected, so workflow assignment coverage cannot be checked yet.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Set both fiscal year and version context first, then rerun readiness to validate workflow routing coverage.'
            );
        }

        $codeCount = $this->tableExists('dbo.tblDataObjectCodes')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblDataObjectCodes WHERE FiscalYearID = :fy', [':fy' => $fiscalYearId])
            : 0;
        if ($codeCount <= 0) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Coverage',
                0,
                0,
                'info',
                'Organisation codes are not loaded yet, so client-specific workflow routing coverage is not yet in scope.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Load organisation structure first, then configure workflow assignments for the current fiscal year/version.'
            );
        }

        $activeAssignmentPredicate = $this->columnExists('dbo.tblWorkflowAssignments', 'ActiveFlag')
            ? 'ISNULL(a.ActiveFlag, 1) = 1'
            : '1 = 1';
        $assignmentCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblWorkflowAssignments a
            WHERE {$activeAssignmentPredicate}
              AND (a.FiscalYearID = :fy OR a.FiscalYearID IS NULL)
              AND (a.VersionID = :ver OR a.VersionID IS NULL)
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        $definitionCount = 0;
        $areasWithoutCoverage = 0;
        if ($this->tableExists('dbo.tblWorkflowDefinition')) {
            $definitionCount = $this->fetchCount('
                SELECT COUNT(*)
                FROM dbo.tblWorkflowDefinition
                WHERE ActiveFlag = 1
            ');

            $areasWithoutCoverage = $this->fetchCount("
                SELECT COUNT(*)
                FROM dbo.tblWorkflowDefinition d
                WHERE d.ActiveFlag = 1
                  AND NOT EXISTS (
                        SELECT 1
                        FROM dbo.tblWorkflowAssignments a
                        WHERE {$activeAssignmentPredicate}
                          AND a.WorkflowAreaCode = d.WorkflowAreaCode
                          AND (a.FiscalYearID = :fy OR a.FiscalYearID IS NULL)
                          AND (a.VersionID = :ver OR a.VersionID IS NULL)
                  )
            ", [
                ':fy' => $fiscalYearId,
                ':ver' => $versionId,
            ]);
        }

        if ($assignmentCount <= 0) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Coverage',
                max(1, $definitionCount),
                max(1, $definitionCount),
                'warning',
                'No active workflow assignments are configured for the current fiscal year/version context.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Create workflow routing rows for the active fiscal year/version so approvals and reviewer paths are not left undefined during testing.'
            );
        }

        if ($areasWithoutCoverage > 0) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Coverage',
                $definitionCount,
                $areasWithoutCoverage,
                'warning',
                $areasWithoutCoverage . ' active workflow area(s) do not yet have any assignment row for the current context.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Review workflow areas and add assignment coverage for the areas that should route through users or scoped approvers in this environment.'
            );
        }

        return $this->buildCheck(
            'Workflow Configuration',
            'Workflow Assignment Coverage',
            $assignmentCount,
            0,
            'ready',
            $assignmentCount . ' workflow assignment row(s) are available for the current fiscal year/version context.',
            'index.php?route=workflow-assignments/list',
            'Workflow Assignments',
            ''
        );
    }

    private function checkWorkflowAssignmentIntegrity(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblWorkflowAssignments')) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Integrity',
                0,
                0,
                'info',
                'Workflow assignment integrity becomes active once tblWorkflowAssignments is installed.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Install and load workflow assignments first, then rerun readiness to validate routing integrity.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Integrity',
                0,
                0,
                'info',
                'No current fiscal year/version context is selected, so workflow assignment integrity cannot be checked yet.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Set both fiscal year and version context first, then rerun readiness to validate assignment integrity.'
            );
        }

        $activeAssignmentPredicate = $this->columnExists('dbo.tblWorkflowAssignments', 'ActiveFlag')
            ? 'ISNULL(a.ActiveFlag, 1) = 1'
            : '1 = 1';
        $assignmentCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblWorkflowAssignments a
            WHERE {$activeAssignmentPredicate}
              AND (a.FiscalYearID = :fy OR a.FiscalYearID IS NULL)
              AND (a.VersionID = :ver OR a.VersionID IS NULL)
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($assignmentCount <= 0) {
            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Integrity',
                0,
                0,
                'info',
                'No workflow assignments exist yet for the current context, so integrity checks are not currently in scope.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Load workflow assignments first, then rerun readiness to validate assignment quality.'
            );
        }

        $inactiveUserPredicate = $this->columnExists('dbo.tblUsers', 'IsActive')
            ? ' OR ISNULL(u.IsActive, 0) = 0'
            : '';
        $orphanAssignmentCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblWorkflowAssignments a
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = a.UserID
            LEFT JOIN dbo.tblDataObjectCodes c
                ON c.FiscalYearID = COALESCE(a.FiscalYearID, :fy)
               AND c.DataObjectCode = a.DataObjectCode
            LEFT JOIN dbo.tblVersions v
                ON v.FiscalYearID = COALESCE(a.FiscalYearID, :fy)
               AND v.VersionID = a.VersionID
            WHERE {$activeAssignmentPredicate}
              AND (a.FiscalYearID = :fy OR a.FiscalYearID IS NULL)
              AND (a.VersionID = :ver OR a.VersionID IS NULL)
              AND (
                    u.UserID IS NULL{$inactiveUserPredicate}
                    OR NULLIF(LTRIM(RTRIM(ISNULL(a.DataObjectCode, ''))), '') IS NULL
                    OR (
                        UPPER(LTRIM(RTRIM(ISNULL(a.DataObjectCode, '')))) NOT IN ('0', 'GLOBAL')
                        AND c.DataObjectCode IS NULL
                    )
                    OR (a.FiscalYearID IS NOT NULL AND NOT EXISTS (
                        SELECT 1
                        FROM dbo.tblFiscalYears fy
                        WHERE fy.FiscalYearID = a.FiscalYearID
                    ))
                    OR (a.VersionID IS NOT NULL AND a.FiscalYearID IS NOT NULL AND v.VersionID IS NULL)
              )
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        $duplicatePrimaryCount = 0;
        if ($this->columnExists('dbo.tblWorkflowAssignments', 'IsPrimary')) {
            $duplicatePrimaryCount = $this->fetchCount("
                SELECT COUNT(*)
                FROM (
                    SELECT
                        WorkflowAreaCode,
                        WorkflowStageCode,
                        ISNULL(FiscalYearID, 0) AS FiscalYearID,
                        ISNULL(VersionID, 0) AS VersionID,
                        DataObjectCode
                    FROM dbo.tblWorkflowAssignments a
                    WHERE {$activeAssignmentPredicate}
                      AND (a.FiscalYearID = :fy OR a.FiscalYearID IS NULL)
                      AND (a.VersionID = :ver OR a.VersionID IS NULL)
                      AND ISNULL(a.IsPrimary, 0) = 1
                    GROUP BY
                        WorkflowAreaCode,
                        WorkflowStageCode,
                        ISNULL(FiscalYearID, 0),
                        ISNULL(VersionID, 0),
                        DataObjectCode
                    HAVING COUNT(*) > 1
                ) duplicates
            ", [
                ':fy' => $fiscalYearId,
                ':ver' => $versionId,
            ]);
        }

        if (($orphanAssignmentCount + $duplicatePrimaryCount) > 0) {
            $parts = [];
            if ($orphanAssignmentCount > 0) {
                $parts[] = $orphanAssignmentCount . ' orphan or invalid assignment row(s)';
            }
            if ($duplicatePrimaryCount > 0) {
                $parts[] = $duplicatePrimaryCount . ' scope(s) with multiple primary assignees';
            }

            return $this->buildCheck(
                'Workflow Configuration',
                'Workflow Assignment Integrity',
                $assignmentCount,
                $orphanAssignmentCount + $duplicatePrimaryCount,
                'warning',
                'Workflow assignment issues were found: ' . implode('; ', $parts) . '.',
                'index.php?route=workflow-assignments/list',
                'Workflow Assignments',
                'Repair invalid assignees, invalid scope links, or competing primary assignments so workflow routing remains predictable.'
            );
        }

        return $this->buildCheck(
            'Workflow Configuration',
            'Workflow Assignment Integrity',
            $assignmentCount,
            0,
            'ready',
            'Workflow assignments point to valid users and scope rows, and no duplicate primary routes were found for the current context.',
            'index.php?route=workflow-assignments/list',
            'Workflow Assignments',
            ''
        );
    }

    private function checkSystemSettings(): array
    {
        if (!$this->tableExists('dbo.tblSystemSettings')) {
            return $this->missingTableCheck(
                'System Configuration',
                'System Settings Available',
                'The system settings table is missing.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Create the system settings table and load the core configuration keys used by login, session control, and services.'
            );
        }

        $count = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblSystemSettings');
        if ($count <= 0) {
            return $this->buildCheck(
                'System Configuration',
                'System Settings Available',
                0,
                1,
                'critical',
                'No system settings are configured.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Add the core system settings before relying on defaults, timeouts, mail configuration, or environment-specific behaviour.'
            );
        }

        return $this->buildCheck(
            'System Configuration',
            'System Settings Available',
            $count,
            0,
            'ready',
            $count . ' system setting record(s) are available.',
            'index.php?route=system-settings/list',
            'System Settings',
            ''
        );
    }

    private function checkRequiredSystemSettings(): array
    {
        if (!$this->tableExists('dbo.tblSystemSettings')) {
            return $this->buildCheck(
                'System Configuration',
                'Required System Settings',
                0,
                1,
                'critical',
                'Required system settings cannot be checked because tblSystemSettings is missing.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Create the system settings table and then define the required keys used by login and session handling.'
            );
        }

        $required = [
            ['label' => 'Application URL', 'keys' => ['APP_URL']],
            ['label' => 'Login URL', 'keys' => ['AUTH_LOGIN_URL']],
            ['label' => 'Login mode', 'keys' => ['AUTH_LOGIN_MODE']],
            ['label' => 'Default fiscal year', 'keys' => ['DEFAULT_FISCAL_YEAR', 'Default_Fiscal_Year']],
            ['label' => 'Default version', 'keys' => ['DEFAULT_VERSION', 'Default_Version']],
            ['label' => 'Login max attempts', 'keys' => ['AUTH_LOGIN_MAX_ATTEMPTS']],
            ['label' => 'Login lockout minutes', 'keys' => ['AUTH_LOGIN_LOCKOUT_MIN']],
            ['label' => 'Session idle timeout', 'keys' => ['SESSION_IDLE_TIMEOUT_SEC', 'SESSION_IDLE_LIMIT']],
            ['label' => 'Session absolute timeout', 'keys' => ['SESSION_ABSOLUTE_TIMEOUT_MIN', 'SESSION_TIMEOUT_MIN']],
        ];

        $missing = [];
        foreach ($required as $item) {
            $value = $this->getSettingValue($item['keys']);
            if ($value === null || trim($value) === '') {
                $missing[] = $item['label'];
            }
        }

        if ($missing !== []) {
            return $this->buildCheck(
                'System Configuration',
                'Required System Settings',
                count($required),
                count($missing),
                'warning',
                'Missing or blank required system settings: ' . implode(', ', $missing) . '.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Open System Settings, populate the missing required keys, save them, and rerun readiness so login and session defaults behave predictably.'
            );
        }

        return $this->buildCheck(
            'System Configuration',
            'Required System Settings',
            count($required),
            0,
            'ready',
            'The key system settings used for application URLs, login behavior, default context, and session control are populated.',
            'index.php?route=system-settings/list',
            'System Settings',
            ''
        );
    }

    private function checkLoginUrlSettings(): array
    {
        if (!$this->tableExists('dbo.tblSystemSettings')) {
            return $this->buildCheck(
                'System Configuration',
                'Login And URL Settings',
                0,
                0,
                'info',
                'Login and URL validation becomes active once system settings are available.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Create and seed system settings first, then rerun readiness to validate login and URL consistency.'
            );
        }

        $appUrl = trim((string) ($this->getSettingValue(['APP_URL']) ?? ''));
        $loginUrl = trim((string) ($this->getSettingValue(['AUTH_LOGIN_URL']) ?? ''));
        $loginMode = trim((string) ($this->getSettingValue(['AUTH_LOGIN_MODE']) ?? ''));
        $maxAttemptsRaw = trim((string) ($this->getSettingValue(['AUTH_LOGIN_MAX_ATTEMPTS']) ?? ''));
        $lockoutMinutesRaw = trim((string) ($this->getSettingValue(['AUTH_LOGIN_LOCKOUT_MIN']) ?? ''));

        $problems = [];
        if ($appUrl === '') {
            $problems[] = 'APP_URL is blank.';
        }
        if ($loginUrl === '') {
            $problems[] = 'AUTH_LOGIN_URL is blank.';
        }
        if ($loginMode === '') {
            $problems[] = 'AUTH_LOGIN_MODE is blank.';
        }
        if ($maxAttemptsRaw === '' || (int) $maxAttemptsRaw <= 0) {
            $problems[] = 'AUTH_LOGIN_MAX_ATTEMPTS is blank or not greater than zero.';
        }
        if ($lockoutMinutesRaw === '' || (int) $lockoutMinutesRaw < 0) {
            $problems[] = 'AUTH_LOGIN_LOCKOUT_MIN is blank or negative.';
        }
        if ($appUrl !== '' && $loginUrl !== '' && strpos($loginUrl, $appUrl) !== 0) {
            $problems[] = 'AUTH_LOGIN_URL does not start with APP_URL.';
        }
        if ($loginUrl !== '' && stripos($loginUrl, 'route=auth/loginForm') === false) {
            $problems[] = 'AUTH_LOGIN_URL does not appear to point to the login form route.';
        }

        if ($problems !== []) {
            return $this->buildCheck(
                'System Configuration',
                'Login And URL Settings',
                5,
                count($problems),
                'warning',
                implode(' ', $problems),
                'index.php?route=system-settings/list',
                'System Settings',
                'Review APP_URL and authentication-related settings so generated login links and lockout behavior stay consistent with the live environment.'
            );
        }

        return $this->buildCheck(
            'System Configuration',
            'Login And URL Settings',
            5,
            0,
            'ready',
            'Application URL, login URL, login mode, and core lockout settings are populated and internally consistent.',
            'index.php?route=system-settings/list',
            'System Settings',
            ''
        );
    }

    private function checkEmailAndSmtpSettings(): array
    {
        if (!$this->tableExists('dbo.tblSystemSettings')) {
            return $this->buildCheck(
                'System Configuration',
                'Email And SMTP Settings',
                0,
                0,
                'info',
                'Email and SMTP validation becomes active once system settings are available.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Create and seed system settings first, then rerun readiness to validate outbound email configuration.'
            );
        }

        $smtpEnabled = $this->settingEnabled(['SMTP_ENABLED']);
        $smtpHost = trim((string) ($this->getSettingValue(['SMTP_HOST']) ?? ''));
        $smtpPortRaw = trim((string) ($this->getSettingValue(['SMTP_PORT']) ?? ''));
        $smtpFrom = trim((string) ($this->getSettingValue(['SMTP_FROM']) ?? ''));
        $smtpUser = trim((string) ($this->getSettingValue(['SMTP_USER']) ?? ''));
        $smtpPass = trim((string) ($this->getSettingValue(['SMTP_PASS']) ?? ''));
        $smtpSecure = trim((string) ($this->getSettingValue(['SMTP_SECURE']) ?? ''));
        $smtpSsl = trim((string) ($this->getSettingValue(['SMTP_SSL']) ?? ''));

        $errorEmailEnabled = $this->settingEnabled(['EMAIL_ERROR_ENABLED', 'ERROR_EMAIL_ENABLED']);
        $errorEmailFrom = trim((string) ($this->getSettingValue(['EMAIL_ERROR_FROM', 'ERROR_EMAIL_FROM']) ?? ''));
        $errorEmailTo = trim((string) ($this->getSettingValue(['EMAIL_ERROR_TO', 'ERROR_EMAIL_TO']) ?? ''));

        if (!$smtpEnabled && !$errorEmailEnabled) {
            return $this->buildCheck(
                'System Configuration',
                'Email And SMTP Settings',
                2,
                0,
                'info',
                'Outbound email and error-notification email are currently disabled, which is acceptable for a fresh-start baseline until email delivery is in scope.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Enable SMTP and populate the mail settings when the client is ready to test notifications, secure links, or workflow email delivery.'
            );
        }

        $problems = [];
        $validatedCount = 0;

        if ($smtpEnabled) {
            $validatedCount += 5;
            if ($smtpHost === '') {
                $problems[] = 'SMTP_HOST is blank.';
            }
            if ($smtpPortRaw === '' || (int) $smtpPortRaw <= 0) {
                $problems[] = 'SMTP_PORT is blank or not greater than zero.';
            }
            if ($smtpFrom === '') {
                $problems[] = 'SMTP_FROM is blank.';
            }
            if (($smtpUser === '') !== ($smtpPass === '')) {
                $problems[] = 'SMTP_USER and SMTP_PASS should either both be provided or both be blank.';
            }
            if ($smtpSecure !== '' && !in_array(strtolower($smtpSecure), ['ssl', 'tls', 'starttls'], true)) {
                $problems[] = 'SMTP_SECURE is populated with an unrecognised value.';
            }
            if ($smtpSsl !== '' && !$this->isBoolLike($smtpSsl)) {
                $problems[] = 'SMTP_SSL is populated with a non-boolean value.';
            }
        }

        if ($errorEmailEnabled) {
            $validatedCount += 3;
            if (!$smtpEnabled) {
                $problems[] = 'EMAIL_ERROR_ENABLED is true while SMTP_ENABLED is false.';
            }
            if ($errorEmailTo === '') {
                $problems[] = 'EMAIL_ERROR_TO is blank.';
            }
            if ($errorEmailFrom === '' && $smtpFrom === '') {
                $problems[] = 'EMAIL_ERROR_FROM is blank and SMTP_FROM is also blank.';
            }
        }

        if ($problems !== []) {
            return $this->buildCheck(
                'System Configuration',
                'Email And SMTP Settings',
                max(1, $validatedCount),
                count($problems),
                'warning',
                implode(' ', $problems),
                'index.php?route=diagnostics/index',
                'Diagnostics',
                'Populate the SMTP and notification settings, then use Diagnostics to send a test email so outbound delivery is verified before wider workflow testing starts.'
            );
        }

        return $this->buildCheck(
            'System Configuration',
            'Email And SMTP Settings',
            max(1, $validatedCount),
            0,
            'ready',
            'The enabled email and SMTP settings are populated consistently. Use Diagnostics to confirm live delivery with a test email.',
            'index.php?route=diagnostics/index',
            'Diagnostics',
            ''
        );
    }

    private function checkWindowsSchedulerConfig(): array
    {
        return $this->buildCheck(
            'System Configuration',
            'Windows Scheduler Config',
            1,
            0,
            'info',
            'Configure Windows Task Scheduler to run workflow reminders and overdue escalations periodically. This runs the workflow notification processor in the background so automatic task reminders and overdue escalation emails are actually sent.',
            '',
            '',
            'Use Create Task, not Basic Task, and configure the action to run PHP from the CBMS working directory.',
            [
                'Open Windows Task Scheduler and choose Create Task.',
                'General tab: name the task CBMS Workflow Task Notifications.',
                'General tab: select Run whether user is logged on or not.',
                'General tab: use a Windows account that can read C:\\CBMS\\CBMSv21 and connect to the CBMS database.',
                'Triggers tab: add a Daily trigger, then set Repeat task every to 1 hour and For a duration of to Indefinitely.',
                'Actions tab: set Program/script to C:\\xampp\\php\\php.exe.',
                'Actions tab: set Add arguments to "C:\\CBMS\\CBMSv21\\scripts\\process_workflow_task_reminders.php" --limit=100.',
                'Actions tab: set Start in to C:\\CBMS\\CBMSv21.',
                'Settings tab: enable Allow task to be run on demand.',
                'Settings tab: enable Run task as soon as possible after a scheduled start is missed.',
                'Save the task, enter the Windows account password if prompted, then right-click the task and choose Run.',
                'After testing, confirm Last Run Result is 0x0 and that automatic reminder or overdue escalation emails are logged or delivered.'
            ]
        );
    }

    private function checkDeprecatedSystemSettings(): array
    {
        if (!$this->tableExists('dbo.tblSystemSettings')) {
            return $this->buildCheck(
                'System Configuration',
                'Deprecated System Settings',
                0,
                0,
                'info',
                'System settings are not available, so deprecated-key usage cannot be checked yet.',
                'index.php?route=system-settings/list',
                'System Settings',
                'Create the system settings table first, then rerun readiness to check for old or retired key names.'
            );
        }

        $deprecatedKeys = [
            'GLAccountSegmentNo',
            'LOGIN_AUTH_MODE',
            'LOGIN_DECAY_MIN',
            'LOGIN_DECAY_HOUR_MIN',
            'LOGIN_LOCKOUT_MIN',
            'LOGIN_LOCKOUT_PERMANENT',
            'LOGIN_MAX_ATTEMPTS',
            'LOGIN_MAX_ATTEMPTS_HOUR',
            'CBMS_LOGIN_URL',
            'CBMS_SECURE_LOGIN_TTL_MINUTES',
            'CBMS_TOKEN_LOGIN_URL_BASE',
            'SESSION_IDLE_LIMIT',
            'SESSION_TIMEOUT_MIN',
            'ERROR_EMAIL_ENABLED',
            'ERROR_EMAIL_FROM',
            'ERROR_EMAIL_TO',
            'Default_Fiscal_Year',
            'Default_Version',
        ];
        $stmt = $this->conn->prepare('SELECT SettingKey FROM dbo.tblSystemSettings');
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $keysFound = [];
        foreach ($rows as $row) {
            $settingKey = trim((string) ($row['SettingKey'] ?? ''));
            if ($settingKey === '') {
                continue;
            }
            if (in_array($settingKey, $deprecatedKeys, true)) {
                $keysFound[] = $settingKey;
            }
        }
        $keysFound = array_values(array_unique($keysFound));
        $deprecatedCount = count($keysFound);

        if ($deprecatedCount <= 0) {
            return $this->buildCheck(
                'System Configuration',
                'Deprecated System Settings',
                count($deprecatedKeys),
                0,
                'ready',
                'No deprecated system setting keys were found in the current catalogue.',
                'index.php?route=system-settings/list',
                'System Settings',
                ''
            );
        }

        return $this->buildCheck(
            'System Configuration',
            'Deprecated System Settings',
            count($deprecatedKeys),
            $deprecatedCount,
            'warning',
            'Deprecated system setting key(s) are still present: ' . implode(', ', $keysFound) . '.',
            'index.php?route=system-settings/list',
            'System Settings',
            'Open System Settings and remove or rename the deprecated keys so the catalogue stays aligned to the current naming standard.'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $checks
     * @return array<string, mixed>
     */
    private function buildSummary(array $checks): array
    {
        $summary = [
            'ready_checks' => 0,
            'warning_checks' => 0,
            'critical_checks' => 0,
            'info_checks' => 0,
            'open_items' => 0,
            'health_score' => 100,
            'blockers' => [],
        ];

        $applicableChecks = 0;
        $scorePoints = 0.0;

        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'info');
            $issueCount = (int) ($check['issue_count'] ?? 0);
            $summary['open_items'] += max(0, $issueCount);

            if ($status === 'ready') {
                $summary['ready_checks']++;
                $applicableChecks++;
                $scorePoints += 1.0;
                continue;
            }

            if ($status === 'warning') {
                $summary['warning_checks']++;
                $applicableChecks++;
                $scorePoints += 0.5;
                continue;
            }

            if ($status === 'critical') {
                $summary['critical_checks']++;
                $applicableChecks++;
                $summary['blockers'][] = [
                    'title' => (string) ($check['title'] ?? ''),
                    'issue_count' => $issueCount,
                ];
                continue;
            }

            $summary['info_checks']++;
        }

        if ($applicableChecks > 0) {
            $summary['health_score'] = (int) round(($scorePoints / $applicableChecks) * 100);
        }

        return $summary;
    }

    private function buildCheck(
        string $category,
        string $title,
        int $totalCount,
        int $issueCount,
        string $status,
        string $message,
        string $actionRoute,
        string $actionLabel,
        string $instruction,
        array $details = []
    ): array {
        return [
            'category' => $category,
            'title' => $title,
            'total_count' => max(0, $totalCount),
            'issue_count' => max(0, $issueCount),
            'status' => $status,
            'message' => $message,
            'instruction' => $instruction,
            'details' => $details,
            'action_route' => $actionRoute,
            'action_label' => $actionLabel,
        ];
    }

    private function missingTableCheck(
        string $category,
        string $title,
        string $message,
        string $actionRoute,
        string $actionLabel,
        string $instruction
    ): array {
        return $this->buildCheck($category, $title, 0, 1, 'critical', $message, $actionRoute, $actionLabel, $instruction);
    }

    /**
     * @param array<int, string> $keys
     */
    private function getSettingValue(array $keys): ?string
    {
        if ($keys === [] || !$this->tableExists('dbo.tblSystemSettings')) {
            return null;
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($keys) as $index => $key) {
            $placeholder = ':settingKey' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $key;
        }

        $sql = '
            SELECT TOP 1 SettingValue
            FROM dbo.tblSystemSettings
            WHERE SettingKey IN (' . implode(', ', $placeholders) . ')
            ORDER BY CASE
                WHEN SettingKey = :orderSettingKey THEN 0
                ELSE 1
            END, SettingKey ASC
        ';

        $params[':orderSettingKey'] = (string) $keys[0];

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    /**
     * @param array<int, string> $keys
     */
    private function settingEnabled(array $keys): bool
    {
        $value = trim((string) ($this->getSettingValue($keys) ?? ''));
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    private function isBoolLike(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true);
    }

    private function getFiscalYearLabel(int $fiscalYearId): string
    {
        if ($fiscalYearId <= 0 || !$this->tableExists('dbo.tblFiscalYears')) {
            return '';
        }

        $stmt = $this->conn->prepare('SELECT TOP 1 YearLabel FROM dbo.tblFiscalYears WHERE FiscalYearID = :fy');
        $stmt->execute([':fy' => $fiscalYearId]);
        $label = $stmt->fetchColumn();

        return $label === false ? '' : trim((string) $label);
    }

    private function getVersionLabel(int $fiscalYearId, int $versionId): string
    {
        if ($fiscalYearId <= 0 || $versionId <= 0 || !$this->tableExists('dbo.tblVersions')) {
            return '';
        }

        $stmt = $this->conn->prepare('
            SELECT TOP 1 VersionLabel
            FROM dbo.tblVersions
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
        ');
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);
        $label = $stmt->fetchColumn();

        return $label === false ? '' : trim((string) $label);
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

    /**
     * @param array<string, scalar|null> $params
     * @return array<int, array<string, mixed>>
     */
    private function fetchRows(string $sql, array $params = []): array
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function existsByKey(string $tableName, string $columnName, int $value): bool
    {
        if (!$this->tableExists($tableName) || $value <= 0) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$tableName} WHERE {$columnName} = :value");
        $stmt->execute([':value' => $value]);

        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function tableExists(string $qualifiedName): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM sys.objects
            WHERE object_id = OBJECT_ID(:qualifiedName)
              AND type = 'U'
        ");
        $stmt->execute([':qualifiedName' => $qualifiedName]);

        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function columnExists(string $qualifiedName, string $columnName): bool
    {
        $stmt = $this->conn->prepare('SELECT COL_LENGTH(:qualifiedName, :columnName)');
        $stmt->execute([
            ':qualifiedName' => $qualifiedName,
            ':columnName' => $columnName,
        ]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null;
    }
}
