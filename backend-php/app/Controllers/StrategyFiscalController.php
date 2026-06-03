<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Shared\SessionHelper;

final class StrategyFiscalController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_FISCAL_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function resourceEnvelope(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $fundingTypeMapping = $admin->getStrategicSegmentMapping($fy, 'FUNDING_TYPE');
        $fundingSourceMapping = $admin->getStrategicSegmentMapping($fy, 'FUNDING_SOURCE');

        $this->render('strategy/ResourceEnvelopeSummary', [
            'title' => __t('strategy_resource_envelope_summary'),
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'periodLabels' => $admin->getFiscalPeriodLabels($fy),
            'resourceEnvelopeInstalled' => $admin->supportsResourceEnvelope(),
            'resourceEnvelopeMtffReady' => $admin->supportsResourceEnvelopeMtffAttributes(),
            'resourceEnvelopeOutYearsReady' => $admin->supportsResourceEnvelopeExtendedOutYears(),
            'summary' => $admin->getResourceEnvelopeSummary($fy, $ver),
            'fundingTypeMapping' => $fundingTypeMapping,
            'fundingSourceMapping' => $fundingSourceMapping,
            'mappingReady' => (int) ($fundingTypeMapping['SegmentNo'] ?? 0) > 0 && (int) ($fundingSourceMapping['SegmentNo'] ?? 0) > 0,
        ]);
    }

    public function resourceEnvelopeLines(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $fundingTypeMapping = $admin->getStrategicSegmentMapping($fy, 'FUNDING_TYPE');
        $fundingSourceMapping = $admin->getStrategicSegmentMapping($fy, 'FUNDING_SOURCE');

        $this->render('strategy/ResourceEnvelopeLines', [
            'title' => __t('strategy_resource_envelope'),
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'periodLabels' => $admin->getFiscalPeriodLabels($fy),
            'resourceEnvelopeInstalled' => $admin->supportsResourceEnvelope(),
            'resourceEnvelopeMtffReady' => $admin->supportsResourceEnvelopeMtffAttributes(),
            'resourceEnvelopeOutYearsReady' => $admin->supportsResourceEnvelopeExtendedOutYears(),
            'summary' => $admin->getResourceEnvelopeSummary($fy, $ver),
            'records' => $admin->listResourceEnvelopeLines($fy, $ver),
            'fundingTypeMapping' => $fundingTypeMapping,
            'fundingSourceMapping' => $fundingSourceMapping,
            'mappingReady' => (int) ($fundingTypeMapping['SegmentNo'] ?? 0) > 0 && (int) ($fundingSourceMapping['SegmentNo'] ?? 0) > 0,
        ]);
    }

    public function resourceEnvelopeForm(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        $duplicateId = (int) ($_GET['duplicate_id'] ?? 0);
        $prefillFundingTypeId = (int) ($_GET['funding_type_id'] ?? 0);
        $prefillFundingSourceId = (int) ($_GET['funding_source_id'] ?? 0);
        $prefillRestrictedProjectId = (int) ($_GET['restricted_project_id'] ?? 0);
        $prefillRestrictionCode = trim((string) ($_GET['restriction_code'] ?? ''));
        $prefillRestrictionScopeTypeCode = trim((string) ($_GET['restriction_scope_type_code'] ?? ''));
        $prefillRestrictionReference = trim((string) ($_GET['restriction_reference'] ?? ''));
        $allLines = $admin->listResourceEnvelopeLines($fy, $ver);

        $record = $id > 0 ? $admin->getResourceEnvelopeLine($id) : null;
        if ($id <= 0 && $duplicateId > 0) {
            $duplicateRecord = $admin->getResourceEnvelopeLine($duplicateId);
            if ($duplicateRecord !== null) {
                unset($duplicateRecord['ResourceEnvelopeID'], $duplicateRecord['CreatedBy'], $duplicateRecord['CreatedDate'], $duplicateRecord['UpdatedBy'], $duplicateRecord['UpdatedDate']);
                $record = $duplicateRecord;
            }
        }
        if ($record === null) {
            $record = [];
        }

        if ($id <= 0) {
            if ($prefillFundingTypeId > 0) {
                $record['FundingTypeID'] = $prefillFundingTypeId;
            }
            if ($prefillFundingSourceId > 0) {
                $record['FundingSourceID'] = $prefillFundingSourceId;
            }
            if ($prefillRestrictedProjectId > 0 && $admin->supportsResourceEnvelopeProjectTargetId()) {
                $record['RestrictedProjectID'] = $prefillRestrictedProjectId;
            }
            if ($prefillRestrictionCode !== '') {
                $record['RestrictionCode'] = strtoupper($prefillRestrictionCode);
            }
            if ($prefillRestrictionScopeTypeCode !== '') {
                $record['RestrictionScopeTypeCode'] = strtoupper($prefillRestrictionScopeTypeCode);
            }
            if ($prefillRestrictionReference !== '') {
                $record['RestrictionReference'] = $prefillRestrictionReference;
            }
        }

        $selectedFundingTypeId = (int) ($record['FundingTypeID'] ?? 0);
        $selectedFundingSourceId = (int) ($record['FundingSourceID'] ?? 0);
        $relatedLines = [];
        foreach ($allLines as $line) {
            $lineFundingTypeId = (int) ($line['FundingTypeID'] ?? 0);
            $lineFundingSourceId = (int) ($line['FundingSourceID'] ?? 0);

            if ($selectedFundingTypeId > 0 && $lineFundingTypeId !== $selectedFundingTypeId) {
                continue;
            }
            if ($selectedFundingSourceId > 0 && $lineFundingSourceId !== $selectedFundingSourceId) {
                continue;
            }

            $relatedLines[] = $line;
        }

        $this->render('strategy/ResourceEnvelopeForm', [
            'title' => $id > 0 ? __t('strategy_edit_resource_envelope_line') : __t('strategy_add_resource_envelope_line'),
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'periodLabels' => $admin->getFiscalPeriodLabels($fy),
            'resourceEnvelopeInstalled' => $admin->supportsResourceEnvelope(),
            'resourceEnvelopeMtffReady' => $admin->supportsResourceEnvelopeMtffAttributes(),
            'resourceEnvelopeRestrictionDetailsReady' => $admin->supportsResourceEnvelopeRestrictionDetails(),
            'resourceEnvelopeRestrictionTargetsReady' => $admin->supportsResourceEnvelopeRestrictionTargets(),
            'resourceEnvelopeOutYearsReady' => $admin->supportsResourceEnvelopeExtendedOutYears(),
            'record' => $record,
            'relatedLines' => $relatedLines,
            'reliabilityOptions' => $admin->getResourceEnvelopeReliabilityOptions(),
            'restrictionOptions' => $admin->getResourceEnvelopeRestrictionOptions(),
            'restrictionScopeOptions' => $admin->getResourceEnvelopeRestrictionScopeOptions(),
            'financingInstrumentOptions' => $admin->getResourceEnvelopeFinancingInstrumentOptions(),
            'assumptionBasisOptions' => $admin->getResourceEnvelopeAssumptionBasisOptions(),
            'sectorOptions' => $admin->listSectorOptions(),
            'programOptions' => $admin->listProgramOptions(),
            'subProgramOptions' => $admin->listSubProgramOptions(),
            'orgUnitOptions' => $admin->listOrgUnitOptions(),
            'activityOptions' => $admin->listActivityOptions(),
            'projectOptions' => $admin->listProjectOptions(),
            'economicItemOptions' => $admin->listEconomicItemOptions(),
            'supportsResourceEnvelopeProjectTargetId' => $admin->supportsResourceEnvelopeProjectTargetId(),
            'fundingTypeMapping' => $admin->getStrategicSegmentMapping($fy, 'FUNDING_TYPE'),
            'fundingSourceMapping' => $admin->getStrategicSegmentMapping($fy, 'FUNDING_SOURCE'),
            'fundingTypeOptions' => $admin->listFundingTypeOptions(),
            'fundingSourceOptions' => $admin->listFundingSourceOptions(),
            'phasingProfiles' => $admin->listActivePhasingProfiles($fy),
            'supportsFundingTypeDefaultPhasing' => $admin->supportsFundingTypeDefaultPhasing(),
            'inflationRateAssumption' => $admin->getFiscalAssumptionValue($fy, $ver, 'INFLATION_RATE'),
            'supportsFiscalAssumptions' => $admin->supportsFiscalAssumptionConfig(),
            'mappingReady' => (int) (($admin->getStrategicSegmentMapping($fy, 'FUNDING_TYPE')['SegmentNo'] ?? 0)) > 0
                && (int) (($admin->getStrategicSegmentMapping($fy, 'FUNDING_SOURCE')['SegmentNo'] ?? 0)) > 0,
        ]);
    }

    public function sectorCeilings(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? $admin->getSectorCeiling($id) : null;

        $this->render('strategy/SectorCeilingConfig', [
            'title' => 'Sector Ceilings',
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'supportsStrategicCeilings' => $admin->supportsStrategicCeilings(),
            'summary' => $admin->getSectorCeilingSummary($fy, $ver),
            'records' => $admin->listSectorCeilings($fy, $ver),
            'record' => $record,
            'sectorOptions' => $admin->listSectorOptions(),
        ]);
    }

    public function saveResourceEnvelope(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-fiscal/resource-envelope');

        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_POST['ResourceEnvelopeID'] ?? 0);
        $saveMode = trim((string) ($_POST['SaveMode'] ?? 'close'));

        if (!$admin->supportsResourceEnvelope()) {
            $this->flashError('Run create_tblSbResourceEnvelope.sql before maintaining the resource envelope.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope');
            exit;
        }

        $fundingTypeMapping = $admin->getStrategicSegmentMapping($fy, 'FUNDING_TYPE');
        $fundingSourceMapping = $admin->getStrategicSegmentMapping($fy, 'FUNDING_SOURCE');
        if ((int) ($fundingTypeMapping['SegmentNo'] ?? 0) <= 0 || (int) ($fundingSourceMapping['SegmentNo'] ?? 0) <= 0) {
            $this->flashError('Funding Type and Funding Source must both be mapped before maintaining the resource envelope.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope');
            exit;
        }

        $fundingTypeId = (int) ($_POST['FundingTypeID'] ?? 0);
        $fundingSourceId = (int) ($_POST['FundingSourceID'] ?? 0);
        $currentYearAmount = $this->decimalValue($_POST['CurrentYearAmount'] ?? null, false);
        $outerYear1Amount = $this->decimalValue($_POST['OuterYear1Amount'] ?? null, true);
        $outerYear2Amount = $this->decimalValue($_POST['OuterYear2Amount'] ?? null, true);
        $outerYear3Amount = $this->decimalValue($_POST['OuterYear3Amount'] ?? null, true);
        $outerYear4Amount = $this->decimalValue($_POST['OuterYear4Amount'] ?? null, true);
        $outerYear5Amount = $this->decimalValue($_POST['OuterYear5Amount'] ?? null, true);

        $bpValues = [];
        $bpSum = 0.0;
        $hasBpValues = false;
        for ($i = 1; $i <= 12; $i++) {
            $value = $this->decimalValue($_POST['BP' . $i . 'Amount'] ?? null, true);
            $bpValues['BP' . $i . 'Amount'] = $value;
            if ($value !== null) {
                $hasBpValues = true;
                $bpSum += (float) $value;
            }
        }

        if ($fundingTypeId <= 0) {
            $this->flashError('Funding Type is required.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        if ($currentYearAmount === null || (float) $currentYearAmount <= 0) {
            $this->flashError('Current year amount is required and must be greater than zero.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $fundingSourceLookup = [];
        foreach ($admin->listFundingSourceOptions() as $option) {
            $fundingSourceLookup[(int) ($option['FundingSourceID'] ?? 0)] = $option;
        }
        if ($fundingSourceId > 0) {
            $selectedSource = $fundingSourceLookup[$fundingSourceId] ?? null;
            if ($selectedSource === null) {
                $this->flashError('Selected Funding Source was not found.');
                header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
                exit;
            }
            if ((int) ($selectedSource['FundingTypeID'] ?? 0) > 0 && (int) ($selectedSource['FundingTypeID'] ?? 0) !== $fundingTypeId) {
                $this->flashError('Selected Funding Source does not belong to the selected Funding Type.');
                header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
                exit;
            }
        } else {
            $fundingSourceId = 0;
        }

        if ($hasBpValues && abs($bpSum - (float) $currentYearAmount) > 0.01) {
            $this->flashError('The sum of BP1 to BP12 must equal the current year amount when phasing is entered.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $restrictionCode = $this->nullableTrim($_POST['RestrictionCode'] ?? 'DISCRETIONARY');
        $restrictionNeedsDetail = in_array((string) $restrictionCode, ['EARMARKED', 'RINGFENCED'], true);
        $restrictionScopeTypeCode = $restrictionNeedsDetail ? $this->nullableTrim($_POST['RestrictionScopeTypeCode'] ?? null) : null;
        $restrictionReference = $restrictionNeedsDetail ? $this->nullableTrim($_POST['RestrictionReference'] ?? null) : null;
        $restrictionDescription = $restrictionNeedsDetail ? $this->nullableTrim($_POST['RestrictionDescription'] ?? null) : null;
        $restrictedSectorId = $restrictionNeedsDetail ? (int) ($_POST['RestrictedSectorID'] ?? 0) : 0;
        $restrictedProgramId = $restrictionNeedsDetail ? (int) ($_POST['RestrictedProgramID'] ?? 0) : 0;
        $restrictedSubProgramId = $restrictionNeedsDetail ? (int) ($_POST['RestrictedSubProgramID'] ?? 0) : 0;
        $restrictedOrgUnitId = $restrictionNeedsDetail ? (int) ($_POST['RestrictedOrgUnitID'] ?? 0) : 0;
        $restrictedActivityId = $restrictionNeedsDetail ? (int) ($_POST['RestrictedActivityID'] ?? 0) : 0;
        $restrictedEconomicItemId = $restrictionNeedsDetail ? (int) ($_POST['RestrictedEconomicItemID'] ?? 0) : 0;
        $restrictedProjectId = $restrictionNeedsDetail ? (int) ($_POST['RestrictedProjectID'] ?? 0) : 0;
        $restrictedProjectReference = $restrictionNeedsDetail ? $this->nullableTrim($_POST['RestrictedProjectReference'] ?? null) : null;

        if ($restrictionNeedsDetail && ($restrictionScopeTypeCode === null || $restrictionScopeTypeCode === '')) {
            $this->flashError('Select a restriction scope for earmarked or ring-fenced funds.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        if ($restrictionNeedsDetail && ($restrictionReference === null || $restrictionReference === '')) {
            $this->flashError('Enter a restriction reference for earmarked or ring-fenced funds.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $hasRestrictionTarget = $restrictedSectorId > 0
            || $restrictedProgramId > 0
            || $restrictedSubProgramId > 0
            || $restrictedOrgUnitId > 0
            || $restrictedActivityId > 0
            || $restrictedEconomicItemId > 0
            || $restrictedProjectId > 0
            || ($restrictedProjectReference !== null && $restrictedProjectReference !== '');

        if ($restrictionNeedsDetail && !$hasRestrictionTarget) {
            $this->flashError('Select at least one restriction target for earmarked or ring-fenced funds.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $payload = [
            'FiscalYearID' => $fy,
            'VersionID' => $ver,
            'FundingTypeID' => $fundingTypeId,
            'FundingSourceID' => $fundingSourceId > 0 ? $fundingSourceId : null,
            'ReliabilityCode' => $this->nullableTrim($_POST['ReliabilityCode'] ?? null),
            'RestrictionCode' => $restrictionCode,
            'RestrictionScopeTypeCode' => $restrictionScopeTypeCode,
            'RestrictionReference' => $restrictionReference,
            'RestrictionDescription' => $restrictionDescription,
            'RestrictedSectorID' => $restrictedSectorId > 0 ? $restrictedSectorId : null,
            'RestrictedProgramID' => $restrictedProgramId > 0 ? $restrictedProgramId : null,
            'RestrictedSubProgramID' => $restrictedSubProgramId > 0 ? $restrictedSubProgramId : null,
            'RestrictedOrgUnitID' => $restrictedOrgUnitId > 0 ? $restrictedOrgUnitId : null,
            'RestrictedActivityID' => $restrictedActivityId > 0 ? $restrictedActivityId : null,
            'RestrictedEconomicItemID' => $restrictedEconomicItemId > 0 ? $restrictedEconomicItemId : null,
            'RestrictedProjectID' => $restrictedProjectId > 0 ? $restrictedProjectId : null,
            'RestrictedProjectReference' => $restrictedProjectReference,
            'FinancingInstrumentCode' => $this->nullableTrim($_POST['FinancingInstrumentCode'] ?? null),
            'OuterYearAssumptionBasisCode' => $this->nullableTrim($_POST['OuterYearAssumptionBasisCode'] ?? null),
            'CurrentYearAmount' => $currentYearAmount,
            'OuterYear1Amount' => $outerYear1Amount,
            'OuterYear2Amount' => $outerYear2Amount,
            'OuterYear3Amount' => $outerYear3Amount,
            'OuterYear4Amount' => $outerYear4Amount,
            'OuterYear5Amount' => $outerYear5Amount,
            'EnvelopeNotes' => $this->nullableTrim($_POST['EnvelopeNotes'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ] + $bpValues;

        $existingLine = $admin->findResourceEnvelopeLineByContext(
            $fy,
            $ver,
            $fundingTypeId,
            $fundingSourceId > 0 ? $fundingSourceId : null,
            $payload['ReliabilityCode'],
            $payload['RestrictionCode'],
            $payload['RestrictionScopeTypeCode'],
            $payload['RestrictionReference'],
            $payload['RestrictedSectorID'],
            $payload['RestrictedProgramID'],
            $payload['RestrictedSubProgramID'],
            $payload['RestrictedOrgUnitID'],
            $payload['RestrictedActivityID'],
            $payload['RestrictedEconomicItemID'],
            $payload['RestrictedProjectID'],
            $payload['RestrictedProjectReference'],
            $payload['FinancingInstrumentCode'],
            $payload['OuterYearAssumptionBasisCode'],
            $id > 0 ? $id : null
        );
        if ($existingLine !== null) {
            $existingId = (int) ($existingLine['ResourceEnvelopeID'] ?? 0);
            $this->flashError('A resource envelope line already exists for this Funding Type, Funding Source, restriction detail, and MTFF attribute combination in the active fiscal context. Open the existing line and edit it instead.');
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form&id=' . $existingId);
            exit;
        }

        try {
            $savedId = $admin->saveResourceEnvelopeLine($payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Resource envelope line updated.' : 'Resource envelope line created.');
        } catch (\Throwable $e) {
            $this->flashError('Resource envelope save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        if ($saveMode === 'stay') {
            header('Location: index.php?route=strategy-fiscal/resource-envelope-form&id=' . $savedId);
            exit;
        }

        if ($saveMode === 'add-another') {
            $params = [
                'route' => 'strategy-fiscal/resource-envelope-form',
                'funding_type_id' => (string) $fundingTypeId,
            ];
            if ($fundingSourceId > 0) {
                $params['funding_source_id'] = (string) $fundingSourceId;
            }
            header('Location: index.php?' . http_build_query($params));
            exit;
        }

        header('Location: index.php?route=strategy-fiscal/resource-envelope-lines');
        exit;
    }

    public function deleteResourceEnvelope(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-fiscal/resource-envelope');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateResourceEnvelopeLine($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Resource envelope line archived.');
        }

        header('Location: index.php?route=strategy-fiscal/resource-envelope-lines');
        exit;
    }

    public function saveSectorCeiling(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-fiscal/sector-ceilings');

        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_POST['CeilingID'] ?? 0);

        if (!$admin->supportsStrategicCeilings()) {
            $this->flashError('Strategic ceiling table is not installed.');
            header('Location: index.php?route=strategy-fiscal/sector-ceilings');
            exit;
        }

        $payload = [
            'SectorID' => (int) ($_POST['SectorID'] ?? 0),
            'CeilingAmount' => $this->decimalValue($_POST['CeilingAmount'] ?? null, false),
            'Notes' => $this->nullableTrim($_POST['Notes'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        try {
            $admin->saveSectorCeiling($fy, $ver, $payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Sector ceiling updated.' : 'Sector ceiling created.');
        } catch (\Throwable $e) {
            $this->flashError('Sector ceiling save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-fiscal/sector-ceilings' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        header('Location: index.php?route=strategy-fiscal/sector-ceilings');
        exit;
    }

    public function deleteSectorCeiling(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-fiscal/sector-ceilings');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateSectorCeiling($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Sector ceiling archived.');
        }

        header('Location: index.php?route=strategy-fiscal/sector-ceilings');
        exit;
    }

    public function copySectorCeilingsFromPlan(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-fiscal/sector-ceilings');

        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        try {
            $summary = $admin->copySectorCeilingsFromPlan($fy, $ver, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Sector ceilings copied from plan. Created: ' . (int) ($summary['created'] ?? 0) . ', updated: ' . (int) ($summary['updated'] ?? 0) . '.');
        } catch (\Throwable $e) {
            $this->flashError('Copy from plan failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-fiscal/sector-ceilings');
        exit;
    }

    public function allocateRemainingSectorCeilings(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-fiscal/sector-ceilings');

        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $method = trim((string) ($_POST['AllocationMethod'] ?? 'EVEN'));

        try {
            $summary = $admin->allocateRemainingSectorCeilingBalance($fy, $ver, $method, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Remaining balance allocated using ' . strtolower((string) ($summary['method'] ?? $method)) . '.');
        } catch (\Throwable $e) {
            $this->flashError('Allocate remaining balance failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-fiscal/sector-ceilings');
        exit;
    }

    private function buildAdminModel(): StrategicBudgetingAdminModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }
        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new StrategicBudgetingAdminModel($this->db);
    }

    private function buildReportModel(): StrategicBudgetingModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }
        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new StrategicBudgetingModel($this->db);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function decimalValue(mixed $value, bool $nullable): ?float
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return $nullable ? null : 0.0;
        }

        if (!is_numeric($trimmed)) {
            return $nullable ? null : 0.0;
        }

        return round((float) $trimmed, 6);
    }

    protected function assertPostWithCsrf(string $redirectUrl = 'index.php?route=strategy-fiscal/overview'): void
    {
        parent::assertPostWithCsrf($redirectUrl);
    }
}
