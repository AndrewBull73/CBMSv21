<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class FinancialConfigurationReadinessModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getDashboard(int $fiscalYearId, int $versionId): array
    {
        $checks = [];

        $checks[] = $this->checkTransactionTypes();
        $checks[] = $this->checkTransactionTypeSegmentConfig($fiscalYearId, $versionId);
        $checks[] = $this->checkTransactionTypeSegmentIntegrity($fiscalYearId, $versionId);
        $checks[] = $this->checkUoms($fiscalYearId);
        $checks[] = $this->checkRates($fiscalYearId, $versionId);
        $checks[] = $this->checkVariableSources();

        $checks[] = $this->checkGlGrouping($fiscalYearId, $versionId);
        $checks[] = $this->checkCeilingDefinitions($fiscalYearId, $versionId);
        $checks[] = $this->checkCeilingBalanceCoverage($fiscalYearId, $versionId);

        $checks[] = $this->checkLegacyCalculations($fiscalYearId);
        $checks[] = $this->checkScenarioModels();
        $checks[] = $this->checkScenarioStructure();
        $checks[] = $this->checkCalculationBridgeCoverage($fiscalYearId, $versionId);

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

    private function checkTransactionTypes(): array
    {
        if (!$this->tableExists('dbo.tblTransactionTypes')) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Types Configured',
                0,
                1,
                'critical',
                'The transaction types table is missing.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Create the transaction type table and load the core transaction types before maintaining transaction input or calculation rules.'
            );
        }

        $count = $this->columnExists('dbo.tblTransactionTypes', 'Active')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblTransactionTypes WHERE ' . $this->activePredicate('dbo.tblTransactionTypes', 'Active'))
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblTransactionTypes');

        if ($count <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Types Configured',
                0,
                1,
                'critical',
                'No active transaction types are configured.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Load the core transaction types first so input sheets, segment rules, grouping, and calculations have valid transaction-type codes to work with.'
            );
        }

        return $this->buildCheck(
            'Financial Transaction Configuration',
            'Transaction Types Configured',
            $count,
            0,
            'ready',
            $count . ' active transaction type record(s) are available.',
            'index.php?route=transaction-type-segment-config/list',
            'Segment Rules',
            ''
        );
    }

    private function checkTransactionTypeSegmentConfig(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblTransactionTypeSegmentConfig')) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Type Segment Rules',
                0,
                1,
                'critical',
                'The transaction type segment configuration table is missing.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Install the transaction type segment configuration table so each transaction type can define which segments are visible and required.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Type Segment Rules',
                0,
                0,
                'info',
                'No current fiscal year/version is selected, so transaction-type segment rules cannot be checked for a planning context.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Set both fiscal year and version context first, then rerun readiness to validate current transaction-type segment rules.'
            );
        }

        $activeRulePredicate = $this->activePredicate('dbo.tblTransactionTypeSegmentConfig', 'ActiveFlag');
        $configCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblTransactionTypeSegmentConfig
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND {$activeRulePredicate}
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($configCount <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Type Segment Rules',
                0,
                1,
                'warning',
                'No active transaction-type segment rules are configured for the current fiscal year and version.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Add segment rules for the current planning context so transaction entry and validation know which dimensions to show and require.'
            );
        }

        return $this->buildCheck(
            'Financial Transaction Configuration',
            'Transaction Type Segment Rules',
            $configCount,
            0,
            'ready',
            $configCount . ' active transaction-type segment rule row(s) are configured for the current fiscal year and version.',
            'index.php?route=transaction-type-segment-config/list',
            'Segment Rules',
            ''
        );
    }

    private function checkTransactionTypeSegmentIntegrity(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblTransactionTypeSegmentConfig') || !$this->tableExists('dbo.tblTransactionTypes')) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Type Rule Integrity',
                0,
                1,
                'critical',
                'Transaction-type rule integrity cannot be checked because transaction types or transaction-type segment rules are missing.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Make sure both transaction types and transaction-type segment rules are available before validating the rule catalogue.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Type Rule Integrity',
                0,
                0,
                'info',
                'No current fiscal year/version is selected, so transaction-type rule integrity cannot be checked for the active context.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Set fiscal year and version context first, then rerun readiness to validate the current transaction-type rule set.'
            );
        }

        $activeRulePredicate = $this->activePredicate('dbo.tblTransactionTypeSegmentConfig', 'ActiveFlag');
        $ruleCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblTransactionTypeSegmentConfig
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND {$activeRulePredicate}
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($ruleCount <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Type Rule Integrity',
                0,
                0,
                'info',
                'No active transaction-type segment rules exist yet for the current context, so integrity issues are not currently in scope.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Create the current context rules first, then rerun readiness to validate them.'
            );
        }

        $orphanRules = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblTransactionTypeSegmentConfig c
            LEFT JOIN dbo.tblTransactionTypes tt
              ON tt.TransactionTypeCode = c.TransactionTypeCode
            WHERE c.FiscalYearID = :fy
              AND c.VersionID = :ver
              AND " . $this->activePredicate('dbo.tblTransactionTypeSegmentConfig', 'ActiveFlag', 'c') . "
              AND tt.TransactionTypeCode IS NULL
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($orphanRules > 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Transaction Type Rule Integrity',
                $ruleCount,
                $orphanRules,
                'warning',
                $orphanRules . ' active transaction-type segment rule row(s) reference a missing transaction type.',
                'index.php?route=transaction-type-segment-config/list',
                'Segment Rules',
                'Review the transaction-type segment rules and repair or remove any rows whose transaction type no longer exists in the master transaction type table.'
            );
        }

        return $this->buildCheck(
            'Financial Transaction Configuration',
            'Transaction Type Rule Integrity',
            $ruleCount,
            0,
            'ready',
            'All active transaction-type segment rules for the current context point to a valid transaction type.',
            'index.php?route=transaction-type-segment-config/list',
            'Segment Rules',
            ''
        );
    }

    private function checkUoms(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblUOMs')) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Units Of Measure Configured',
                0,
                1,
                'warning',
                'The units-of-measure table is missing.',
                '',
                '',
                'Add a maintenance path for units of measure and load the UOM catalogue used by transaction input and calculations.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Units Of Measure Configured',
                0,
                0,
                'info',
                'No current fiscal year is selected, so UOM coverage cannot be checked for a planning context.',
                '',
                '',
                'Set the fiscal year context first, then rerun readiness to check UOM coverage for that year.'
            );
        }

        $count = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblUOMs WHERE FiscalYearID = :fy', [
            ':fy' => $fiscalYearId,
        ]);

        if ($count <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Units Of Measure Configured',
                0,
                1,
                'warning',
                'No units of measure are configured for the current fiscal year.',
                '',
                '',
                'Load the UOM catalogue for the fiscal year so transaction types and calculation rules can reference consistent input and output units.'
            );
        }

        return $this->buildCheck(
            'Financial Transaction Configuration',
            'Units Of Measure Configured',
            $count,
            0,
            'ready',
            $count . ' unit-of-measure row(s) are configured for the current fiscal year.',
            '',
            '',
            ''
        );
    }

    private function checkRates(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblRates')) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Rates Configured',
                0,
                1,
                'warning',
                'The rates table is missing.',
                '',
                '',
                'Create the rates table and load the rate catalogue used by calculation rules and scenario modelling.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Rates Configured',
                0,
                0,
                'info',
                'No current fiscal year/version is selected, so rates cannot be checked for the active planning context.',
                '',
                '',
                'Set fiscal year and version context first, then rerun readiness to validate rate coverage.'
            );
        }

        $count = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblRates
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($count <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Rates Configured',
                0,
                1,
                'warning',
                'No rates are configured for the current fiscal year and version.',
                '',
                '',
                'Load the current context rate rows so calculation rules that depend on rates can resolve their inputs consistently.'
            );
        }

        return $this->buildCheck(
            'Financial Transaction Configuration',
            'Rates Configured',
            $count,
            0,
            'ready',
            $count . ' rate row(s) are configured for the current fiscal year and version.',
            '',
            '',
            ''
        );
    }

    private function checkVariableSources(): array
    {
        if (!$this->tableExists('dbo.tblVariableSources')) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Variable Sources Configured',
                0,
                1,
                'warning',
                'The variable source table is missing.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Create the variable source catalogue so calculations and scenarios can resolve reusable variables from consistent source definitions.'
            );
        }

        $count = $this->columnExists('dbo.tblVariableSources', 'ActiveFlag')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblVariableSources WHERE ' . $this->activePredicate('dbo.tblVariableSources', 'ActiveFlag'))
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblVariableSources');

        if ($count <= 0) {
            return $this->buildCheck(
                'Financial Transaction Configuration',
                'Variable Sources Configured',
                0,
                1,
                'warning',
                'No active variable source definitions are configured.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Load the variable source catalogue if your calculation models depend on sourced variables instead of hard-coded formulas alone.'
            );
        }

        return $this->buildCheck(
            'Financial Transaction Configuration',
            'Variable Sources Configured',
            $count,
            0,
            'ready',
            $count . ' active variable source definition(s) are available.',
            'index.php?route=scenario-admin/index',
            'Scenario Config',
            ''
        );
    }

    private function checkGlGrouping(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblGLGrouping')) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'GL Grouping Configured',
                0,
                1,
                'warning',
                'The GL grouping table is missing.',
                '',
                '',
                'Create the GL grouping table before relying on grouped financial reporting or grouped transaction summaries.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'GL Grouping Configured',
                0,
                0,
                'info',
                'No current fiscal year/version is selected, so GL grouping cannot be checked for a reporting context.',
                '',
                '',
                'Set fiscal year and version context first, then rerun readiness to validate GL grouping coverage.'
            );
        }

        $count = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblGLGrouping
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($count <= 0) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'GL Grouping Configured',
                0,
                1,
                'warning',
                'No GL grouping rows are configured for the current fiscal year and version.',
                '',
                '',
                'Load the current context GL grouping rows so grouped financial summaries and report rollups can classify transactions correctly.'
            );
        }

        return $this->buildCheck(
            'Financial Reporting And Controls',
            'GL Grouping Configured',
            $count,
            0,
            'ready',
            $count . ' GL grouping row(s) are configured for the current fiscal year and version.',
            '',
            '',
            ''
        );
    }

    private function checkCeilingDefinitions(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblCeilingDefinition')) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'Ceiling Definitions Configured',
                0,
                0,
                'info',
                'The ceiling definition table is not installed in this database.',
                'index.php?route=ceilings/balances',
                'Ceiling Balances',
                'Install the ceiling definition tables only if you plan to use transaction-level ceiling controls.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'Ceiling Definitions Configured',
                0,
                0,
                'info',
                'No current fiscal year/version is selected, so ceiling definitions cannot be checked for the active context.',
                'index.php?route=ceilings/balances',
                'Ceiling Balances',
                'Set fiscal year and version context first, then rerun readiness to validate ceiling configuration.'
            );
        }

        $count = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblCeilingDefinition
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND {$this->activePredicate('dbo.tblCeilingDefinition', 'ActiveFlag')}
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($count <= 0) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'Ceiling Definitions Configured',
                0,
                1,
                'warning',
                'No active ceiling definitions are configured for the current fiscal year and version.',
                'index.php?route=ceilings/balances',
                'Ceiling Balances',
                'Load ceiling definitions for the current planning context if you want transaction entry and budget control to enforce ceiling limits.'
            );
        }

        return $this->buildCheck(
            'Financial Reporting And Controls',
            'Ceiling Definitions Configured',
            $count,
            0,
            'ready',
            $count . ' active ceiling definition row(s) are configured for the current fiscal year and version.',
            'index.php?route=ceilings/balances',
            'Ceiling Balances',
            ''
        );
    }

    private function checkCeilingBalanceCoverage(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblCeilingDefinition') || !$this->tableExists('dbo.tblCeilingBalance')) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'Ceiling Balance Coverage',
                0,
                0,
                'info',
                'Ceiling balance coverage cannot be checked until both ceiling definitions and ceiling balances are available.',
                'index.php?route=ceilings/balances',
                'Ceiling Balances',
                'Install both ceiling definition and ceiling balance tables if you want ceiling balances to be preloaded and tracked.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'Ceiling Balance Coverage',
                0,
                0,
                'info',
                'No current fiscal year/version is selected, so ceiling balance coverage cannot be checked for the active context.',
                'index.php?route=ceilings/balances',
                'Ceiling Balances',
                'Set fiscal year and version context first, then rerun readiness to check ceiling balance coverage.'
            );
        }

        $definitionCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblCeilingDefinition
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND {$this->activePredicate('dbo.tblCeilingDefinition', 'ActiveFlag')}
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($definitionCount <= 0) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'Ceiling Balance Coverage',
                0,
                0,
                'info',
                'No active ceiling definitions exist for the current context, so ceiling balance coverage is not yet in scope.',
                'index.php?route=ceilings/balances',
                'Ceiling Balances',
                'Load active ceiling definitions first, then rerun readiness to confirm balance coverage.'
            );
        }

        $missingBalanceCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblCeilingDefinition d
            LEFT JOIN dbo.tblCeilingBalance b
              ON b.CeilingDefinitionID = d.CeilingDefinitionID
            WHERE d.FiscalYearID = :fy
              AND d.VersionID = :ver
              AND " . $this->activePredicate('dbo.tblCeilingDefinition', 'ActiveFlag', 'd') . "
              AND b.CeilingDefinitionID IS NULL
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($missingBalanceCount > 0) {
            return $this->buildCheck(
                'Financial Reporting And Controls',
                'Ceiling Balance Coverage',
                $definitionCount,
                $missingBalanceCount,
                'warning',
                $missingBalanceCount . ' active ceiling definition(s) do not yet have a matching ceiling balance row.',
                'index.php?route=ceilings/balances',
                'Ceiling Balances',
                'Reload or initialize ceiling balances so every active ceiling definition has a current balance row before transaction entry relies on them.'
            );
        }

        return $this->buildCheck(
            'Financial Reporting And Controls',
            'Ceiling Balance Coverage',
            $definitionCount,
            0,
            'ready',
            'Every active ceiling definition for the current context has a matching ceiling balance row.',
            'index.php?route=ceilings/balances',
            'Ceiling Balances',
            ''
        );
    }

    private function checkLegacyCalculations(int $fiscalYearId): array
    {
        if (!$this->tableExists('dbo.tblCalculations')) {
            return $this->buildCheck(
                'Calculation Engine',
                'Legacy Calculation Rules',
                0,
                1,
                'warning',
                'The legacy calculations table is missing.',
                'index.php?route=full-recalculation/index',
                'Full Recalculation',
                'Install the legacy calculation tables if your transaction engine still depends on CalculationID-based processing.'
            );
        }

        if ($fiscalYearId <= 0) {
            return $this->buildCheck(
                'Calculation Engine',
                'Legacy Calculation Rules',
                0,
                0,
                'info',
                'No current fiscal year is selected, so legacy calculation coverage cannot be checked for the active planning context.',
                'index.php?route=full-recalculation/index',
                'Full Recalculation',
                'Set the fiscal year context first, then rerun readiness to validate the current calculation catalogue.'
            );
        }

        $count = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalculations WHERE FiscalYearID = :fy', [
            ':fy' => $fiscalYearId,
        ]);

        if ($count <= 0) {
            return $this->buildCheck(
                'Calculation Engine',
                'Legacy Calculation Rules',
                0,
                1,
                'warning',
                'No legacy calculation rows are configured for the current fiscal year.',
                'index.php?route=full-recalculation/index',
                'Full Recalculation',
                'Load or generate the current fiscal year legacy calculation rules if transaction processing still relies on CalculationID-driven logic.'
            );
        }

        return $this->buildCheck(
            'Calculation Engine',
            'Legacy Calculation Rules',
            $count,
            0,
            'ready',
            $count . ' legacy calculation row(s) are configured for the current fiscal year.',
            'index.php?route=full-recalculation/index',
            'Full Recalculation',
            ''
        );
    }

    private function checkScenarioModels(): array
    {
        if (!$this->tableExists('dbo.tblCalcModel')) {
            return $this->buildCheck(
                'Calculation Engine',
                'Scenario Models Configured',
                0,
                1,
                'warning',
                'The scenario modelling table is missing.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Install the scenario modelling engine tables if you want model-based calculation design and scenario publishing.'
            );
        }

        $count = $this->columnExists('dbo.tblCalcModel', 'ActiveFlag')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalcModel WHERE ' . $this->activePredicate('dbo.tblCalcModel', 'ActiveFlag'))
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalcModel');

        if ($count <= 0) {
            return $this->buildCheck(
                'Calculation Engine',
                'Scenario Models Configured',
                0,
                1,
                'warning',
                'No active scenario models are configured.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Create at least one active calculation model if you plan to use the scenario modelling engine.'
            );
        }

        return $this->buildCheck(
            'Calculation Engine',
            'Scenario Models Configured',
            $count,
            0,
            'ready',
            $count . ' active scenario model(s) are configured.',
            'index.php?route=scenario-admin/index',
            'Scenario Config',
            ''
        );
    }

    private function checkScenarioStructure(): array
    {
        if (
            !$this->tableExists('dbo.tblCalcModel')
            || !$this->tableExists('dbo.tblCalcScenario')
            || !$this->tableExists('dbo.tblCalcNode')
            || !$this->tableExists('dbo.tblCalcFormula')
            || !$this->tableExists('dbo.tblCalcDependency')
        ) {
            return $this->buildCheck(
                'Calculation Engine',
                'Scenario Structure Health',
                0,
                1,
                'warning',
                'Scenario structure health cannot be checked because one or more scenario engine tables are missing.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Install the full scenario modelling engine table set before expecting model, node, formula, and dependency health checks to pass.'
            );
        }

        $modelCount = $this->columnExists('dbo.tblCalcModel', 'ActiveFlag')
            ? $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalcModel WHERE ' . $this->activePredicate('dbo.tblCalcModel', 'ActiveFlag'))
            : $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalcModel');

        if ($modelCount <= 0) {
            return $this->buildCheck(
                'Calculation Engine',
                'Scenario Structure Health',
                0,
                0,
                'info',
                'No active scenario models exist yet, so scenario structure health is not currently in scope.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Create an active scenario model first, then rerun readiness to validate its structure.'
            );
        }

        $scenarioCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalcScenario WHERE ' . $this->activePredicate('dbo.tblCalcScenario', 'ActiveFlag'));
        $nodeCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalcNode WHERE ' . $this->activePredicate('dbo.tblCalcNode', 'ActiveFlag'));
        $formulaCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalcFormula WHERE ' . $this->activePredicate('dbo.tblCalcFormula', 'ActiveFlag'));
        $dependencyCount = $this->fetchCount('SELECT COUNT(*) FROM dbo.tblCalcDependency');

        $issueCount = 0;
        $problems = [];
        if ($scenarioCount <= 0) {
            $issueCount++;
            $problems[] = 'No active scenarios are configured.';
        }
        if ($nodeCount <= 0) {
            $issueCount++;
            $problems[] = 'No active nodes are configured.';
        }
        if ($formulaCount <= 0) {
            $issueCount++;
            $problems[] = 'No active formulas are configured.';
        }
        if ($dependencyCount <= 0) {
            $issueCount++;
            $problems[] = 'No calculation dependencies are configured.';
        }

        if ($issueCount > 0) {
            return $this->buildCheck(
                'Calculation Engine',
                'Scenario Structure Health',
                $modelCount,
                $issueCount,
                'warning',
                implode(' ', $problems),
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Open Scenario Config and complete the model structure so scenarios, nodes, formulas, and dependencies are all present before relying on scenario runs.'
            );
        }

        return $this->buildCheck(
            'Calculation Engine',
            'Scenario Structure Health',
            $modelCount,
            0,
            'ready',
            'Scenario models have active scenarios, nodes, formulas, and dependency rows available.',
            'index.php?route=scenario-admin/index',
            'Scenario Config',
            ''
        );
    }

    private function checkCalculationBridgeCoverage(int $fiscalYearId, int $versionId): array
    {
        if (!$this->tableExists('dbo.tblCalcTransactionBridge') || !$this->tableExists('dbo.tblCalcTransactionNodeMap')) {
            return $this->buildCheck(
                'Calculation Engine',
                'Calculation Bridge Coverage',
                0,
                1,
                'warning',
                'The transaction-to-scenario bridge tables are missing.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Install the transaction bridge tables if you want scenario models to map directly to transaction inputs.'
            );
        }

        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return $this->buildCheck(
                'Calculation Engine',
                'Calculation Bridge Coverage',
                0,
                0,
                'info',
                'No current fiscal year/version is selected, so bridge coverage cannot be checked for the active context.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Set fiscal year and version context first, then rerun readiness to validate bridge coverage.'
            );
        }

        $bridgeCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblCalcTransactionBridge
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND {$this->activePredicate('dbo.tblCalcTransactionBridge', 'ActiveFlag')}
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($bridgeCount <= 0) {
            return $this->buildCheck(
                'Calculation Engine',
                'Calculation Bridge Coverage',
                0,
                1,
                'warning',
                'No active transaction bridge rows are configured for the current fiscal year and version.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Create bridge rows for the current context if transaction inputs should feed the scenario modelling engine.'
            );
        }

        $nodeMapCount = $this->fetchCount("
            SELECT COUNT(*)
            FROM dbo.tblCalcTransactionNodeMap map
            INNER JOIN dbo.tblCalcTransactionBridge bridge
              ON bridge.CalcTransactionBridgeID = map.CalcTransactionBridgeID
            WHERE bridge.FiscalYearID = :fy
              AND bridge.VersionID = :ver
              AND " . $this->activePredicate('dbo.tblCalcTransactionBridge', 'ActiveFlag', 'bridge') . "
              AND " . $this->activePredicate('dbo.tblCalcTransactionNodeMap', 'ActiveFlag', 'map') . "
        ", [
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        if ($nodeMapCount <= 0) {
            return $this->buildCheck(
                'Calculation Engine',
                'Calculation Bridge Coverage',
                $bridgeCount,
                1,
                'warning',
                'Active transaction bridge rows exist for the current context, but none of them have active transaction-node mappings.',
                'index.php?route=scenario-admin/index',
                'Scenario Config',
                'Populate the transaction-node mappings so bridge rows can resolve transaction values into the calculation model structure.'
            );
        }

        return $this->buildCheck(
            'Calculation Engine',
            'Calculation Bridge Coverage',
            $bridgeCount,
            0,
            'ready',
            $bridgeCount . ' active bridge row(s) and ' . $nodeMapCount . ' active transaction-node mapping row(s) are configured for the current context.',
            'index.php?route=scenario-admin/index',
            'Scenario Config',
            ''
        );
    }

    private function buildSummary(array $checks): array
    {
        $critical = 0;
        $warnings = 0;
        $openItems = 0;
        $blockers = [];

        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'info');
            $issues = (int) ($check['issue_count'] ?? 0);
            $openItems += max(0, $issues);

            if ($status === 'critical') {
                $critical++;
                $blockers[] = $check;
            } elseif ($status === 'warning') {
                $warnings++;
            }
        }

        $score = 100 - ($critical * 18) - ($warnings * 8) - min(20, $openItems);
        $score = max(0, min(100, $score));

        return [
            'health_score' => $score,
            'critical_checks' => $critical,
            'warning_checks' => $warnings,
            'open_items' => $openItems,
            'blockers' => $blockers,
        ];
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
        string $instruction
    ): array {
        return [
            'category' => $category,
            'title' => $title,
            'total_count' => max(0, $totalCount),
            'issue_count' => max(0, $issueCount),
            'status' => $status,
            'message' => $message,
            'instruction' => $instruction,
            'action_route' => $actionRoute,
            'action_label' => $actionLabel,
        ];
    }

    private function fetchCount(string $sql, array $params = []): int
    {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();

        return $value === false ? 0 : (int) $value;
    }

    private function tableExists(string $qualifiedName): bool
    {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA + '.' + TABLE_NAME = :qualifiedName
        ");
        $stmt->execute([':qualifiedName' => $qualifiedName]);

        return (int) $stmt->fetchColumn() > 0;
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

    private function activePredicate(string $qualifiedName, string $columnName, string $alias = ''): string
    {
        $columnRef = $alias !== '' ? ($alias . '.' . $columnName) : $columnName;
        $dataType = $this->getColumnDataType($qualifiedName, $columnName);

        if (in_array($dataType, ['char', 'nchar', 'varchar', 'nvarchar', 'text', 'ntext'], true)) {
            return "UPPER(LTRIM(RTRIM(COALESCE({$columnRef}, '')))) IN ('1', 'Y', 'YES', 'TRUE', 'T', 'ACTIVE')";
        }

        return "COALESCE({$columnRef}, 1) = 1";
    }

    private function getColumnDataType(string $qualifiedName, string $columnName): string
    {
        $parts = explode('.', $qualifiedName, 2);
        $schema = count($parts) === 2 ? $parts[0] : 'dbo';
        $table = count($parts) === 2 ? $parts[1] : $parts[0];

        $stmt = $this->conn->prepare("
            SELECT DATA_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :schemaName
              AND TABLE_NAME = :tableName
              AND COLUMN_NAME = :columnName
        ");
        $stmt->execute([
            ':schemaName' => $schema,
            ':tableName' => $table,
            ':columnName' => $columnName,
        ]);
        $value = $stmt->fetchColumn();

        return $value === false ? '' : strtolower(trim((string) $value));
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
}
