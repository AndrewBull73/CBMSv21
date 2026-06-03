<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money_fmt')) {
    function money_fmt(mixed $value): string
    {
        return number_format((float) $value, 0);
    }
}

$record = $record ?? [];
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$periodLabels = is_array($periodLabels ?? null) ? $periodLabels : [];
$bpLabels = is_array($periodLabels['BP'] ?? null) ? $periodLabels['BP'] : [];
$outerYearLabels = is_array($periodLabels['OuterYear'] ?? null) ? $periodLabels['OuterYear'] : [];
$id = (int) ($record['ResourceEnvelopeID'] ?? 0);
$fundingTypeSegmentNo = (int) ($fundingTypeMapping['SegmentNo'] ?? 0);
$fundingSourceSegmentNo = (int) ($fundingSourceMapping['SegmentNo'] ?? 0);
$resourceEnvelopeMtffReady = !empty($resourceEnvelopeMtffReady);
$resourceEnvelopeRestrictionDetailsReady = !empty($resourceEnvelopeRestrictionDetailsReady);
$resourceEnvelopeRestrictionTargetsReady = !empty($resourceEnvelopeRestrictionTargetsReady);
$resourceEnvelopeOutYearsReady = !empty($resourceEnvelopeOutYearsReady);
$relatedLines = is_array($relatedLines ?? null) ? $relatedLines : [];
$currentYearAmountValue = (string) ($record['CurrentYearAmount'] ?? '');
$outerYear1AmountValue = (string) ($record['OuterYear1Amount'] ?? '');
$outerYear2AmountValue = (string) ($record['OuterYear2Amount'] ?? '');
$outerYear3AmountValue = (string) ($record['OuterYear3Amount'] ?? '');
$outerYear4AmountValue = (string) ($record['OuterYear4Amount'] ?? '');
$outerYear5AmountValue = (string) ($record['OuterYear5Amount'] ?? '');
$phasingBaseYear = (int) date('Y');
if (preg_match('/\b(20\d{2})\b/', $yearLabel, $matches)) {
    $phasingBaseYear = (int) $matches[1];
}
$selectedFundingTypeId = (int) ($record['FundingTypeID'] ?? 0);
$selectedFundingSourceId = (int) ($record['FundingSourceID'] ?? 0);
$selectedReliabilityCode = (string) ($record['ReliabilityCode'] ?? '');
$selectedRestrictionCode = (string) ($record['RestrictionCode'] ?? 'DISCRETIONARY');
$selectedRestrictionScopeTypeCode = (string) ($record['RestrictionScopeTypeCode'] ?? '');
$selectedRestrictionReference = (string) ($record['RestrictionReference'] ?? '');
$selectedRestrictionDescription = (string) ($record['RestrictionDescription'] ?? '');
$selectedRestrictedSectorId = (int) ($record['RestrictedSectorID'] ?? 0);
$selectedRestrictedProgramId = (int) ($record['RestrictedProgramID'] ?? 0);
$selectedRestrictedSubProgramId = (int) ($record['RestrictedSubProgramID'] ?? 0);
$selectedRestrictedOrgUnitId = (int) ($record['RestrictedOrgUnitID'] ?? 0);
$selectedRestrictedActivityId = (int) ($record['RestrictedActivityID'] ?? 0);
$selectedRestrictedEconomicItemId = (int) ($record['RestrictedEconomicItemID'] ?? 0);
$selectedRestrictedProjectId = (int) ($record['RestrictedProjectID'] ?? 0);
$selectedRestrictedProjectReference = (string) ($record['RestrictedProjectReference'] ?? '');
$selectedFinancingInstrumentCode = (string) ($record['FinancingInstrumentCode'] ?? '');
$selectedAssumptionBasisCode = (string) ($record['OuterYearAssumptionBasisCode'] ?? '');
$phasingProfiles = is_array($phasingProfiles ?? null) ? $phasingProfiles : [];
$supportsFundingTypeDefaultPhasing = !empty($supportsFundingTypeDefaultPhasing);
$inflationRateAssumption = isset($inflationRateAssumption) ? (float) $inflationRateAssumption : null;
$supportsFiscalAssumptions = !empty($supportsFiscalAssumptions);
$sectorOptions = is_array($sectorOptions ?? null) ? $sectorOptions : [];
$programOptions = is_array($programOptions ?? null) ? $programOptions : [];
$subProgramOptions = is_array($subProgramOptions ?? null) ? $subProgramOptions : [];
$orgUnitOptions = is_array($orgUnitOptions ?? null) ? $orgUnitOptions : [];
$activityOptions = is_array($activityOptions ?? null) ? $activityOptions : [];
$projectOptions = is_array($projectOptions ?? null) ? $projectOptions : [];
$supportsResourceEnvelopeProjectTargetId = !empty($supportsResourceEnvelopeProjectTargetId);
$economicItemOptions = is_array($economicItemOptions ?? null) ? $economicItemOptions : [];
$sectorNameById = [];
foreach ($sectorOptions as $option) { $sectorNameById[(int) ($option['SectorID'] ?? 0)] = (string) ($option['SectorName'] ?? ''); }
$programNameById = [];
foreach ($programOptions as $option) { $programNameById[(int) ($option['ProgramID'] ?? 0)] = (string) ($option['ProgramName'] ?? ''); }
$subProgramNameById = [];
foreach ($subProgramOptions as $option) { $subProgramNameById[(int) ($option['SubProgramID'] ?? 0)] = (string) ($option['SubProgramName'] ?? ''); }
$orgUnitNameById = [];
foreach ($orgUnitOptions as $option) { $orgUnitNameById[(int) ($option['OrgUnitID'] ?? 0)] = (string) ($option['OrgUnitName'] ?? ''); }
$activityNameById = [];
foreach ($activityOptions as $option) { $activityNameById[(int) ($option['ActivityID'] ?? 0)] = (string) ($option['ActivityName'] ?? ''); }
$projectLabelById = [];
foreach ($projectOptions as $option) {
    $projectLabelById[(int) ($option['ProjectID'] ?? 0)] = trim((string) (($option['ProjectCode'] ?? '') . ' - ' . ($option['ProjectName'] ?? '')), ' -');
}
$economicNameById = [];
foreach ($economicItemOptions as $option) { $economicNameById[(int) ($option['EconomicItemID'] ?? 0)] = trim((string) (($option['EconomicCode'] ?? '') . ' ' . ($option['EconomicName'] ?? ''))); }
$addNewHref = 'index.php?route=strategy-fiscal/resource-envelope-form';
$addNewParams = [];
if ($selectedFundingTypeId > 0) {
    $addNewParams['funding_type_id'] = $selectedFundingTypeId;
}
if ($selectedFundingSourceId > 0) {
    $addNewParams['funding_source_id'] = $selectedFundingSourceId;
}
if ($addNewParams !== []) {
    $addNewHref .= '&' . http_build_query($addNewParams);
}
$duplicateHref = $id > 0 ? 'index.php?route=strategy-fiscal/resource-envelope-form&duplicate_id=' . $id : '';
$screenHeader = [
    'title' => $id > 0 ? __t('strategy_edit_resource_envelope_line') : __t('strategy_add_resource_envelope_line'),
    'icon' => 'bi-cash-stack',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        <?= h(__t('strategy_fiscal_context')) ?>:
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span><strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div class="small text-muted">
          Maintain one consistent funding-line structure with explicit restriction, phasing, and outer-year controls so downstream inquiry, training, and UAT flows all land on stable records.
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="index.php?route=strategy-fiscal/resource-envelope-lines" id="resource-envelope-list-btn" class="btn btn-sm btn-outline-secondary"><?= h(__t('strategy_resource_envelope')) ?></a>
          <?php if ($id > 0): ?>
            <a href="<?= h($addNewHref) ?>" id="resource-envelope-add-new-btn" class="btn btn-sm btn-primary"><?= h(__t('strategy_add_new_record')) ?></a>
          <?php endif; ?>
        </div>
      </div>

      <?php if (!$resourceEnvelopeInstalled): ?>
        <div class="alert alert-warning"><?= h(__t('strategy_run_before_using_form')) ?> <code>create_tblSbResourceEnvelope.sql</code>.</div>
      <?php endif; ?>
      <?php if ($resourceEnvelopeInstalled && !$mappingReady): ?>
        <div class="alert alert-warning">
          <?= h(__t('strategy_resource_envelope_mapping_warning')) ?>
          <?= h(__t('strategy_funding_type_segment')) ?>:
          <strong><?= $fundingTypeSegmentNo > 0 ? (string) $fundingTypeSegmentNo : __t('strategy_not_mapped') ?></strong>.
          <?= h(__t('strategy_funding_source_segment')) ?>:
          <strong><?= $fundingSourceSegmentNo > 0 ? (string) $fundingSourceSegmentNo : __t('strategy_not_mapped') ?></strong>.
        </div>
      <?php endif; ?>
      <?php if ($resourceEnvelopeInstalled && !$resourceEnvelopeMtffReady): ?>
        <div class="alert alert-warning"><?= h(__t('strategy_mtff_attributes_not_available')) ?> <code>alter_tblSbResourceEnvelope_mtff_attributes_and_outyears.sql</code> <?= h(__t('strategy_is_run')) ?></div>
      <?php endif; ?>
      <?php if ($resourceEnvelopeInstalled && !$resourceEnvelopeOutYearsReady): ?>
        <div class="alert alert-warning"><?= h(__t('strategy_outer_years_not_available')) ?> <code>alter_tblSbResourceEnvelope_mtff_attributes_and_outyears.sql</code> <?= h(__t('strategy_is_run')) ?></div>
      <?php endif; ?>
      <?php if ($resourceEnvelopeInstalled && !$resourceEnvelopeRestrictionDetailsReady): ?>
        <div class="alert alert-warning">Restriction detail fields are not available until <code>alter_tblSbResourceEnvelope_add_restriction_detail.sql</code> is run.</div>
      <?php endif; ?>
      <?php if ($resourceEnvelopeInstalled && !$resourceEnvelopeRestrictionTargetsReady): ?>
        <div class="alert alert-warning">Restriction target fields are not available until <code>alter_tblSbResourceEnvelope_add_restriction_detail.sql</code> is run.</div>
      <?php endif; ?>

      <form method="post" action="index.php?route=strategy-fiscal/save-resource-envelope" id="resource-envelope-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="ResourceEnvelopeID" value="<?= $id ?>">
        <input type="hidden" name="SaveMode" id="SaveMode" value="close">

        <div class="alert alert-info">
          <?= h(__t('strategy_resource_envelope_form_intro')) ?>
        </div>

        <div class="card border-0 bg-light-subtle mb-4">
          <div class="card-body py-3">
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <span class="badge text-bg-secondary"><?= h(__t('strategy_context')) ?></span>
              <span class="badge text-bg-light border"><?= h($selectedFundingTypeId > 0 ? (__t('strategy_funding_type') . ' #' . $selectedFundingTypeId) : __t('strategy_funding_type_not_selected')) ?></span>
              <span class="badge text-bg-light border"><?= h($selectedFundingSourceId > 0 ? (__t('strategy_funding_source') . ' #' . $selectedFundingSourceId) : __t('strategy_type_level_no_source')) ?></span>
              <?php if ($resourceEnvelopeMtffReady): ?>
                <span class="badge text-bg-light border"><?= h($selectedReliabilityCode !== '' ? ('Reliability: ' . $selectedReliabilityCode) : 'Reliability not set') ?></span>
                <span class="badge text-bg-light border"><?= h($selectedRestrictionCode !== '' ? ('Restriction: ' . $selectedRestrictionCode) : 'Restriction not set') ?></span>
                <?php if ($resourceEnvelopeRestrictionDetailsReady && $selectedRestrictionReference !== ''): ?>
                  <span class="badge text-bg-light border"><?= h('Restriction Ref: ' . $selectedRestrictionReference) ?></span>
                <?php endif; ?>
                <span class="badge text-bg-light border"><?= h($selectedFinancingInstrumentCode !== '' ? ('Instrument: ' . $selectedFinancingInstrumentCode) : 'Instrument not set') ?></span>
                <span class="badge text-bg-light border"><?= h($selectedAssumptionBasisCode !== '' ? ('Assumption: ' . $selectedAssumptionBasisCode) : 'Assumption not set') ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-xl-8">
            <div class="card border-0 bg-light-subtle mb-4">
              <div class="card-body">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Funding Type</label>
                    <select name="FundingTypeID" id="FundingTypeID" class="form-select" required <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                      <option value="">Select funding type</option>
                      <?php foreach (($fundingTypeOptions ?? []) as $option): ?>
                        <option value="<?= (int) ($option['FundingTypeID'] ?? 0) ?>" data-default-phasing-profile-id="<?= $supportsFundingTypeDefaultPhasing ? (int) ($option['DefaultPhasingProfileID'] ?? 0) : 0 ?>" <?= ((int) ($record['FundingTypeID'] ?? 0) === (int) ($option['FundingTypeID'] ?? 0)) ? 'selected' : '' ?>>
                          <?= h((string) (($option['FundingTypeName'] ?? '') . ' (' . ($option['FundingTypeCode'] ?? '') . ')')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Funding Source</label>
                    <select name="FundingSourceID" id="FundingSourceID" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                      <option value="">Unspecified / type-level</option>
                      <?php foreach (($fundingSourceOptions ?? []) as $option): ?>
                        <option
                          value="<?= (int) ($option['FundingSourceID'] ?? 0) ?>"
                          data-funding-type-id="<?= (int) ($option['FundingTypeID'] ?? 0) ?>"
                          <?= ((int) ($record['FundingSourceID'] ?? 0) === (int) ($option['FundingSourceID'] ?? 0)) ? 'selected' : '' ?>
                        >
                          <?= h((string) ($option['FundingSourceName'] ?? '')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <?php if ($resourceEnvelopeMtffReady): ?>
                    <div class="col-md-3">
                      <label class="form-label">Reliability / Certainty</label>
                      <select name="ReliabilityCode" id="ReliabilityCode" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        <option value="">Not set</option>
                        <?php foreach (($reliabilityOptions ?? []) as $option): ?>
                          <option value="<?= h((string) ($option['code'] ?? '')) ?>" <?= ((string) ($record['ReliabilityCode'] ?? '') === (string) ($option['code'] ?? '')) ? 'selected' : '' ?>>
                            <?= h((string) ($option['label'] ?? '')) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Restriction / Earmark</label>
                      <select name="RestrictionCode" id="RestrictionCode" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        <option value="">Not set</option>
                        <?php foreach (($restrictionOptions ?? []) as $option): ?>
                          <option value="<?= h((string) ($option['code'] ?? '')) ?>" <?= ($selectedRestrictionCode === (string) ($option['code'] ?? '')) ? 'selected' : '' ?>>
                            <?= h((string) ($option['label'] ?? '')) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Financing Instrument</label>
                      <select name="FinancingInstrumentCode" id="FinancingInstrumentCode" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        <option value="">Not set</option>
                        <?php foreach (($financingInstrumentOptions ?? []) as $option): ?>
                          <option value="<?= h((string) ($option['code'] ?? '')) ?>" <?= ((string) ($record['FinancingInstrumentCode'] ?? '') === (string) ($option['code'] ?? '')) ? 'selected' : '' ?>>
                            <?= h((string) ($option['label'] ?? '')) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Outer-Year Assumption Basis</label>
                      <select name="OuterYearAssumptionBasisCode" id="OuterYearAssumptionBasisCode" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        <option value="">Not set</option>
                        <?php foreach (($assumptionBasisOptions ?? []) as $option): ?>
                          <option value="<?= h((string) ($option['code'] ?? '')) ?>" <?= ((string) ($record['OuterYearAssumptionBasisCode'] ?? '') === (string) ($option['code'] ?? '')) ? 'selected' : '' ?>>
                            <?= h((string) ($option['label'] ?? '')) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <?php if ($resourceEnvelopeRestrictionDetailsReady): ?>
                      <div class="col-12">
                        <div id="jsRestrictionDetailPanel" class="border rounded p-3 bg-white d-none">
                          <div class="fw-semibold mb-2">Restriction Control Detail</div>
                          <div class="small text-muted mb-3">
                            Use this to identify exactly what the funds are protected for. This gives us a control reference we can enforce later in ceilings and budget entry.
                          </div>
                          <div class="row g-3">
                            <div class="col-md-4">
                              <label class="form-label">Restriction Reference</label>
                              <input type="text" name="RestrictionReference" id="RestrictionReference" class="form-control" maxlength="100" value="<?= h($selectedRestrictionReference) ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-md-8">
                              <label class="form-label">Restriction Description</label>
                              <input type="text" name="RestrictionDescription" id="RestrictionDescription" class="form-control" maxlength="255" value="<?= h($selectedRestrictionDescription) ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                            </div>
                            <?php if ($resourceEnvelopeRestrictionTargetsReady): ?>
                              <div class="col-md-4">
                                <label class="form-label">Sector</label>
                                <select name="RestrictedSectorID" id="RestrictedSectorID" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                                  <option value="">Not restricted</option>
                                  <?php foreach (($sectorOptions ?? []) as $option): ?>
                                    <option value="<?= (int) ($option['SectorID'] ?? 0) ?>" <?= $selectedRestrictedSectorId === (int) ($option['SectorID'] ?? 0) ? 'selected' : '' ?>>
                                      <?= h((string) ($option['SectorName'] ?? '')) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Program</label>
                                <select name="RestrictedProgramID" id="RestrictedProgramID" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                                  <option value="">Not restricted</option>
                                  <?php foreach (($programOptions ?? []) as $option): ?>
                                    <option value="<?= (int) ($option['ProgramID'] ?? 0) ?>" <?= $selectedRestrictedProgramId === (int) ($option['ProgramID'] ?? 0) ? 'selected' : '' ?>>
                                      <?= h((string) ($option['ProgramName'] ?? '')) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">SubProgram</label>
                                <select name="RestrictedSubProgramID" id="RestrictedSubProgramID" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                                  <option value="">Not restricted</option>
                                  <?php foreach (($subProgramOptions ?? []) as $option): ?>
                                    <option value="<?= (int) ($option['SubProgramID'] ?? 0) ?>" data-program-id="<?= (int) ($option['ProgramID'] ?? 0) ?>" <?= $selectedRestrictedSubProgramId === (int) ($option['SubProgramID'] ?? 0) ? 'selected' : '' ?>>
                                      <?= h((string) (($option['ProgramName'] ?? '') . ' / ' . ($option['SubProgramName'] ?? ''))) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Ministry / Org Unit</label>
                                <select name="RestrictedOrgUnitID" id="RestrictedOrgUnitID" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                                  <option value="">Not restricted</option>
                                  <?php foreach (($orgUnitOptions ?? []) as $option): ?>
                                    <option value="<?= (int) ($option['OrgUnitID'] ?? 0) ?>" <?= $selectedRestrictedOrgUnitId === (int) ($option['OrgUnitID'] ?? 0) ? 'selected' : '' ?>>
                                      <?= h((string) ($option['OrgUnitName'] ?? '')) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Activity</label>
                                <select name="RestrictedActivityID" id="RestrictedActivityID" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                                  <option value="">Not restricted</option>
                                  <?php foreach (($activityOptions ?? []) as $option): ?>
                                    <option value="<?= (int) ($option['ActivityID'] ?? 0) ?>" <?= $selectedRestrictedActivityId === (int) ($option['ActivityID'] ?? 0) ? 'selected' : '' ?>>
                                      <?= h((string) (($option['ProgramName'] ?? '') . ' / ' . ($option['OutputName'] ?? '') . ' / ' . ($option['ActivityName'] ?? ''))) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <label class="form-label">Economic Item</label>
                                <select name="RestrictedEconomicItemID" id="RestrictedEconomicItemID" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                                  <option value="">Not restricted</option>
                                  <?php foreach (($economicItemOptions ?? []) as $option): ?>
                                    <option value="<?= (int) ($option['EconomicItemID'] ?? 0) ?>" <?= $selectedRestrictedEconomicItemId === (int) ($option['EconomicItemID'] ?? 0) ? 'selected' : '' ?>>
                                      <?= h((string) (($option['EconomicCode'] ?? '') . ' - ' . ($option['EconomicName'] ?? ''))) ?>
                                    </option>
                                  <?php endforeach; ?>
                                </select>
                              </div>
                              <div class="col-md-4">
                                <?php if ($supportsResourceEnvelopeProjectTargetId): ?>
                                  <label class="form-label">Project</label>
                                  <select name="RestrictedProjectID" id="RestrictedProjectID" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                                    <option value="">Not restricted</option>
                                    <?php foreach (($projectOptions ?? []) as $option): ?>
                                      <option value="<?= (int) ($option['ProjectID'] ?? 0) ?>" <?= $selectedRestrictedProjectId === (int) ($option['ProjectID'] ?? 0) ? 'selected' : '' ?>>
                                        <?= h(trim((string) (($option['ProjectCode'] ?? '') . ' - ' . ($option['ProjectName'] ?? '')), ' -')) ?>
                                      </option>
                                    <?php endforeach; ?>
                                  </select>
                                  <input type="hidden" name="RestrictedProjectReference" value="<?= h($selectedRestrictedProjectReference) ?>">
                                  <div class="form-text">Use the strategic project register to target project-specific restrictions.</div>
                                <?php else: ?>
                                  <label class="form-label">Project Reference</label>
                                  <input type="text" name="RestrictedProjectReference" id="RestrictedProjectReference" class="form-control" maxlength="100" value="<?= h($selectedRestrictedProjectReference) ?>" placeholder="Project record not configured yet" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                                  <div class="form-text">Project dimension is not yet configured in this resource-envelope schema, so this remains a reference field for now.</div>
                                <?php endif; ?>
                              </div>
                              <input type="hidden" name="RestrictionScopeTypeCode" id="RestrictionScopeTypeCode" value="MULTI_SCOPE">
                            <?php else: ?>
                              <input type="hidden" name="RestrictionScopeTypeCode" id="RestrictionScopeTypeCode" value="<?= h($selectedRestrictionScopeTypeCode) ?>">
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="card border-0 shadow-sm">
              <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Amount Entry Grid</h5>
                <span class="small text-muted">Tab down the value column for faster entry</span>
              </div>
              <div class="card-body">
                <div class="border rounded bg-light-subtle p-3 mb-4">
                  <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                      <label for="jsPhasingMethod" class="form-label mb-1">Phasing Helper</label>
                      <select id="jsPhasingMethod" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        <option value="EVEN">Phase Evenly</option>
                        <option value="CALENDAR_DAYS">Phase by Calendar Days</option>
                        <option value="WORKDAYS">Phase by Workdays</option>
                        <?php foreach ($phasingProfiles as $profile): ?>
                          <option value="PROFILE:<?= (int) ($profile['PhasingProfileID'] ?? 0) ?>">
                            <?= h((string) ($profile['ProfileName'] ?? 'Custom Profile')) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-lg-3">
                      <label for="jsPhasingScope" class="form-label mb-1">Apply To</label>
                      <select id="jsPhasingScope" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        <option value="ALL">Overwrite all BP periods</option>
                        <option value="EMPTY_ONLY">Empty BP periods only</option>
                      </select>
                    </div>
                    <div class="col-lg-5">
                      <div class="d-flex flex-wrap gap-2 mb-2">
                        <button type="button" id="jsApplyPhasing" class="btn btn-outline-primary" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>Apply Phasing</button>
                        <button type="button" id="jsClearPhasing" class="btn btn-outline-secondary" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>Clear BP Values</button>
                        <button type="button" id="jsCopyCurrentToOuterYears" class="btn btn-outline-secondary" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>Copy Current Year to Outer Years</button>
                      </div>
                      <div class="small text-muted" id="jsPhasingHelperNote">
                        Auto-distribute the current year amount across <?= h((string) ($bpLabels[1] ?? 'BP1')) ?> to <?= h((string) ($bpLabels[12] ?? 'BP12')) ?> using <?= h((string) $phasingBaseYear) ?> month weights, then adjust manually if needed.
                      </div>
                    </div>
                  </div>
                </div>
                <div class="border rounded bg-light-subtle p-3 mb-4">
                  <div class="row g-3 align-items-end">
                    <div class="col-lg-4">
                      <label for="jsOuterYearMethod" class="form-label mb-1">Outer-Year Projection</label>
                      <select id="jsOuterYearMethod" class="form-select" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        <option value="FLAT">Flat</option>
                        <option value="INFLATION">Inflation<?= $inflationRateAssumption !== null ? ' (' . h(number_format($inflationRateAssumption * 100, 2)) . '%)' : '' ?></option>
                        <option value="CUSTOM_RATE">Custom Rate</option>
                        <option value="MANUAL">Manual</option>
                      </select>
                    </div>
                    <div class="col-lg-3">
                      <label for="jsOuterYearRate" class="form-label mb-1">Annual Rate %</label>
                      <input type="number" step="0.01" id="jsOuterYearRate" class="form-control text-end" value="<?= h($inflationRateAssumption !== null ? number_format($inflationRateAssumption * 100, 2, '.', '') : '0') ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-lg-5">
                      <div class="d-flex flex-wrap gap-2 mb-2">
                        <button type="button" id="jsApplyOuterYearProjection" class="btn btn-outline-primary" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>Apply Projection</button>
                        <button type="button" id="jsClearOuterYears" class="btn btn-outline-secondary" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>Clear Outer Years</button>
                      </div>
                      <div class="small text-muted" id="jsOuterYearProjectionNote">
                        Project <?= h((string) ($outerYearLabels[1] ?? 'Outer Year 1')) ?> to <?= h((string) ($outerYearLabels[5] ?? 'Outer Year 5')) ?> from the current year amount using flat copy, inflation, or a custom annual rate.
                        <?php if ($supportsFiscalAssumptions): ?>
                          <?php if ($inflationRateAssumption !== null): ?>
                            The current fiscal assumption for inflation is <?= h(number_format($inflationRateAssumption * 100, 2)) ?>%.
                          <?php else: ?>
                            Add <code>INFLATION_RATE</code> on Fiscal Assumptions to drive the inflation option automatically.
                          <?php endif; ?>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="alert alert-warning d-none" id="jsBpMismatchAlert">
                  BP phasing does not currently match the current year amount.
                </div>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0" id="resource-envelope-amount-grid-table">
                    <thead class="table-light">
                      <tr>
                        <th style="min-width: 180px;">Amount Type</th>
                        <th class="text-end" style="min-width: 170px;">Value</th>
                        <th class="text-muted small">Note</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td class="fw-semibold fs-6">Current Year</td>
                        <td>
                          <input type="text" inputmode="numeric" name="CurrentYearAmount" id="CurrentYearAmount" class="form-control form-control-lg text-end js-envelope-number" value="<?= h($currentYearAmountValue) ?>" required <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        </td>
                        <td class="small text-muted">Main budget-year amount for this funding line.</td>
                      </tr>
                      <?php for ($i = 1; $i <= 12; $i++): $key = 'BP' . $i . 'Amount'; $bpLabel = (string) ($bpLabels[$i] ?? ('BP' . $i)); ?>
                        <tr>
                          <td><?= h($bpLabel) ?></td>
                          <td>
                            <input type="text" inputmode="numeric" name="<?= h($key) ?>" id="<?= h($key) ?>" class="form-control text-end js-envelope-number js-envelope-bp" value="<?= h((string) ($record[$key] ?? '')) ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                          </td>
                          <td class="small text-muted">Optional phasing for <?= h($bpLabel) ?>.</td>
                        </tr>
                      <?php endfor; ?>
                      <tr>
                        <td class="fw-semibold"><?= h((string) ($outerYearLabels[1] ?? 'Outer Year 1')) ?></td>
                        <td>
                          <input type="text" inputmode="numeric" name="OuterYear1Amount" id="OuterYear1Amount" class="form-control text-end js-envelope-number" value="<?= h($outerYear1AmountValue) ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        </td>
                        <td class="small text-muted">Optional MTFF outer-year amount.</td>
                      </tr>
                      <tr>
                        <td class="fw-semibold"><?= h((string) ($outerYearLabels[2] ?? 'Outer Year 2')) ?></td>
                        <td>
                          <input type="text" inputmode="numeric" name="OuterYear2Amount" id="OuterYear2Amount" class="form-control text-end js-envelope-number" value="<?= h($outerYear2AmountValue) ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                        </td>
                        <td class="small text-muted">Optional second outer-year amount.</td>
                      </tr>
                      <?php if ($resourceEnvelopeOutYearsReady): ?>
                        <tr>
                          <td class="fw-semibold"><?= h((string) ($outerYearLabels[3] ?? 'Outer Year 3')) ?></td>
                          <td>
                            <input type="text" inputmode="numeric" name="OuterYear3Amount" id="OuterYear3Amount" class="form-control text-end js-envelope-number" value="<?= h($outerYear3AmountValue) ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                          </td>
                          <td class="small text-muted">Optional third outer-year amount.</td>
                        </tr>
                        <tr>
                          <td class="fw-semibold"><?= h((string) ($outerYearLabels[4] ?? 'Outer Year 4')) ?></td>
                          <td>
                            <input type="text" inputmode="numeric" name="OuterYear4Amount" id="OuterYear4Amount" class="form-control text-end js-envelope-number" value="<?= h($outerYear4AmountValue) ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                          </td>
                          <td class="small text-muted">Optional fourth outer-year amount.</td>
                        </tr>
                        <tr>
                          <td class="fw-semibold"><?= h((string) ($outerYearLabels[5] ?? 'Outer Year 5')) ?></td>
                          <td>
                            <input type="text" inputmode="numeric" name="OuterYear5Amount" id="OuterYear5Amount" class="form-control text-end js-envelope-number" value="<?= h($outerYear5AmountValue) ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                          </td>
                          <td class="small text-muted">Optional fifth outer-year amount.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                      <tr>
                        <th>BP Total</th>
                        <th class="text-end" id="jsBpTotalTableDisplay">0</th>
                        <th class="small text-muted">Sum of all BP phasing periods.</th>
                      </tr>
                      <tr>
                        <th>Difference to Current Year</th>
                        <th class="text-end" id="jsVarianceTableDisplay">0</th>
                        <th class="small text-muted" id="jsVarianceTableNote">Should be zero before save when phasing is used.</th>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-4">
            <div class="card shadow-sm sticky-top" style="top: 1rem;">
              <div class="card-header bg-white">
                <h5 class="mb-0">Live Entry Check</h5>
              </div>
              <div class="card-body">
                <div class="border rounded p-3 mb-3">
                  <div class="small text-muted">Current Year</div>
                  <div class="fs-4 fw-semibold text-end" id="jsCurrentYearDisplay"><?= money_fmt($currentYearAmountValue !== '' ? $currentYearAmountValue : 0) ?></div>
                </div>
                <div class="border rounded p-3 mb-3">
                  <div class="small text-muted">Selected Period Range</div>
                  <div class="fw-semibold text-end"><?= h((string) ($bpLabels[1] ?? 'BP1')) ?> to <?= h((string) ($bpLabels[12] ?? 'BP12')) ?></div>
                </div>
                <div class="border rounded p-3 mb-3">
                  <div class="small text-muted">BP Phasing Total</div>
                  <div class="fs-5 fw-semibold text-end" id="jsBpTotalDisplay">0</div>
                </div>
                <div class="border rounded p-3 mb-3">
                  <div class="small text-muted">Variance</div>
                  <div class="fs-5 fw-semibold text-end" id="jsVarianceDisplay">0</div>
                  <div class="small mt-2" id="jsVarianceNote">Enter BP phasing if you want to spread the current year amount.</div>
                </div>
                <div class="border rounded p-3 mb-3">
                  <div class="small text-muted">Outer Years Total</div>
                  <div class="fs-5 fw-semibold text-end" id="jsOuterTotalDisplay"><?= money_fmt((float) ($outerYear1AmountValue !== '' ? $outerYear1AmountValue : 0) + (float) ($outerYear2AmountValue !== '' ? $outerYear2AmountValue : 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($outerYear3AmountValue !== '' ? $outerYear3AmountValue : 0) + (float) ($outerYear4AmountValue !== '' ? $outerYear4AmountValue : 0) + (float) ($outerYear5AmountValue !== '' ? $outerYear5AmountValue : 0)) : 0)) ?></div>
                </div>
                <div class="accordion mb-3" id="resourceEnvelopeNotesAccordion">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="resourceEnvelopeNotesHeading">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#resourceEnvelopeNotesCollapse" aria-expanded="false" aria-controls="resourceEnvelopeNotesCollapse">
                        Notes
                      </button>
                    </h2>
                    <div id="resourceEnvelopeNotesCollapse" class="accordion-collapse collapse" aria-labelledby="resourceEnvelopeNotesHeading" data-bs-parent="#resourceEnvelopeNotesAccordion">
                      <div class="accordion-body">
                        <textarea name="EnvelopeNotes" id="EnvelopeNotes" class="form-control" rows="4" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>><?= h((string) ($record['EnvelopeNotes'] ?? '')) ?></textarea>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="ActiveFlag" id="resourceEnvelopeActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?> <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>
                  <label class="form-check-label" for="resourceEnvelopeActiveFlag">Active</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="index.php?route=strategy-fiscal/resource-envelope-lines" id="resource-envelope-back-btn" class="btn btn-secondary">Back</a>
          <div class="d-flex flex-wrap gap-2">
            <?php if ($id > 0): ?>
              <a href="<?= h($duplicateHref) ?>" id="resource-envelope-duplicate-btn" class="btn btn-outline-secondary">Duplicate This Line</a>
            <?php endif; ?>
            <button type="submit" id="resource-envelope-save-stay-btn" class="btn btn-outline-primary js-save-mode" data-save-mode="stay" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>Save and Stay</button>
            <button type="submit" id="resource-envelope-save-add-another-btn" class="btn btn-outline-primary js-save-mode" data-save-mode="add-another" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>Save and Add Another</button>
            <button type="submit" id="resource-envelope-save-close-btn" class="btn btn-primary js-save-mode" data-save-mode="close" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>>Save Resource Envelope Line</button>
          </div>
        </div>
      </form>

      <?php if ($relatedLines !== []): ?>
        <div class="card shadow-sm mt-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Existing Matching Envelope Lines</h5>
            <span class="small text-muted"><?= (int) count($relatedLines) ?> line(s)</span>
          </div>
          <div class="card-body">
            <div class="small text-muted mb-3">
              These lines already match the selected Funding Type and Funding Source context. Review the MTFF attribute columns as well before deciding whether to edit an existing line or add a new one.
            </div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0" id="resource-envelope-related-lines-table">
                <thead class="table-light">
                  <tr>
                    <th>Funding Type</th>
                    <th>Funding Source</th>
                    <?php if ($resourceEnvelopeMtffReady): ?>
                      <th>Reliability</th>
                      <th>Restriction</th>
                      <?php if ($resourceEnvelopeRestrictionDetailsReady): ?>
                        <th>Restriction Detail</th>
                      <?php endif; ?>
                      <th>Instrument</th>
                    <?php endif; ?>
                    <th class="text-end">Current Year</th>
                    <th class="text-end">Outer Years</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($relatedLines as $line): ?>
                    <?php $outerYearsTotal = (float) ($line['OuterYear1Amount'] ?? 0) + (float) ($line['OuterYear2Amount'] ?? 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($line['OuterYear3Amount'] ?? 0) + (float) ($line['OuterYear4Amount'] ?? 0) + (float) ($line['OuterYear5Amount'] ?? 0)) : 0); ?>
                    <?php $lineTotal = (float) ($line['CurrentYearAmount'] ?? 0) + $outerYearsTotal; ?>
                    <tr<?= ((int) ($line['ResourceEnvelopeID'] ?? 0) === $id && $id > 0) ? ' class="table-primary"' : '' ?>>
                      <td>
                        <span><?= h((string) ($line['FundingTypeName'] ?? '')) ?></span>
                        <?php if (!empty($line['FundingTypeCode'])): ?>
                          <span class="small text-muted ms-2"><?= h((string) $line['FundingTypeCode']) ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span><?= h((string) ($line['FundingSourceName'] ?? 'Unspecified / Type-level')) ?></span>
                        <?php if (!empty($line['FundingSourceCode'])): ?>
                          <span class="small text-muted ms-2"><?= h((string) $line['FundingSourceCode']) ?></span>
                        <?php endif; ?>
                      </td>
                      <?php if ($resourceEnvelopeMtffReady): ?>
                        <td><?= h((string) ($line['ReliabilityCode'] ?? 'Not set')) ?></td>
                        <td><?= h((string) ($line['RestrictionCode'] ?? 'Not set')) ?></td>
                        <?php if ($resourceEnvelopeRestrictionDetailsReady): ?>
                          <td>
                            <?php
                              $reference = trim((string) ($line['RestrictionReference'] ?? ''));
                              $description = trim((string) ($line['RestrictionDescription'] ?? ''));
                              $targetBits = [];
                              $sectorId = (int) ($line['RestrictedSectorID'] ?? 0);
                              $programId = (int) ($line['RestrictedProgramID'] ?? 0);
                              $subProgramId = (int) ($line['RestrictedSubProgramID'] ?? 0);
                              $orgUnitId = (int) ($line['RestrictedOrgUnitID'] ?? 0);
                              $activityId = (int) ($line['RestrictedActivityID'] ?? 0);
                              $economicId = (int) ($line['RestrictedEconomicItemID'] ?? 0);
                              $projectId = (int) ($line['RestrictedProjectID'] ?? 0);
                              $projectRef = trim((string) ($line['RestrictedProjectReference'] ?? ''));
                              if ($sectorId > 0 && isset($sectorNameById[$sectorId])) { $targetBits[] = 'Sector: ' . $sectorNameById[$sectorId]; }
                              if ($programId > 0 && isset($programNameById[$programId])) { $targetBits[] = 'Program: ' . $programNameById[$programId]; }
                              if ($subProgramId > 0 && isset($subProgramNameById[$subProgramId])) { $targetBits[] = 'SubProgram: ' . $subProgramNameById[$subProgramId]; }
                              if ($orgUnitId > 0 && isset($orgUnitNameById[$orgUnitId])) { $targetBits[] = 'Org Unit: ' . $orgUnitNameById[$orgUnitId]; }
                              if ($activityId > 0 && isset($activityNameById[$activityId])) { $targetBits[] = 'Activity: ' . $activityNameById[$activityId]; }
                              if ($economicId > 0 && isset($economicNameById[$economicId])) { $targetBits[] = 'Economic: ' . $economicNameById[$economicId]; }
                              if ($projectId > 0 && isset($projectLabelById[$projectId])) { $targetBits[] = 'Project: ' . $projectLabelById[$projectId]; }
                              elseif ($projectRef !== '') { $targetBits[] = 'Project: ' . $projectRef; }
                              $detailBits = array_values(array_filter(array_merge($reference !== '' ? ['Ref: ' . $reference] : [], $targetBits, $description !== '' ? [$description] : []), static fn ($value) => $value !== ''));
                            ?>
                            <?= h($detailBits !== [] ? implode(' / ', $detailBits) : 'Not set') ?>
                          </td>
                        <?php endif; ?>
                        <td><?= h((string) ($line['FinancingInstrumentCode'] ?? 'Not set')) ?></td>
                      <?php endif; ?>
                      <td class="text-end"><?= money_fmt($line['CurrentYearAmount'] ?? 0) ?></td>
                      <td class="text-end"><?= money_fmt($outerYearsTotal) ?></td>
                      <td class="text-end fw-semibold"><?= money_fmt($lineTotal) ?></td>
                      <td class="text-end">
                        <?php if ((int) ($line['ResourceEnvelopeID'] ?? 0) === $id && $id > 0): ?>
                          <span class="badge bg-primary">Current</span>
                        <?php else: ?>
                          <a href="index.php?route=strategy-fiscal/resource-envelope-form&id=<?= (int) ($line['ResourceEnvelopeID'] ?? 0) ?>" id="resource-envelope-related-edit-btn-<?= (int) ($line['ResourceEnvelopeID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const phasingBaseYear = <?= (int) $phasingBaseYear ?>;
    const fundingType = document.getElementById('FundingTypeID');
    const fundingSource = document.getElementById('FundingSourceID');
    const restrictionCode = document.getElementById('RestrictionCode');
    const restrictionDetailPanel = document.getElementById('jsRestrictionDetailPanel');
    const restrictionScopeType = document.getElementById('RestrictionScopeTypeCode');
    const restrictionReference = document.getElementById('RestrictionReference');
    const restrictedProgram = document.getElementById('RestrictedProgramID');
    const restrictedSubProgram = document.getElementById('RestrictedSubProgramID');
    const currentYearInput = document.getElementById('CurrentYearAmount');
    const outerYear1Input = document.getElementById('OuterYear1Amount');
    const outerYear2Input = document.getElementById('OuterYear2Amount');
    const outerYear3Input = document.getElementById('OuterYear3Amount');
    const outerYear4Input = document.getElementById('OuterYear4Amount');
    const outerYear5Input = document.getElementById('OuterYear5Amount');
    const bpInputs = Array.from(document.querySelectorAll('.js-envelope-bp'));
    const currentYearDisplay = document.getElementById('jsCurrentYearDisplay');
    const bpTotalDisplay = document.getElementById('jsBpTotalDisplay');
    const bpTotalTableDisplay = document.getElementById('jsBpTotalTableDisplay');
    const varianceDisplay = document.getElementById('jsVarianceDisplay');
    const varianceTableDisplay = document.getElementById('jsVarianceTableDisplay');
    const varianceNote = document.getElementById('jsVarianceNote');
    const varianceTableNote = document.getElementById('jsVarianceTableNote');
    const outerTotalDisplay = document.getElementById('jsOuterTotalDisplay');
    const phasingMethod = document.getElementById('jsPhasingMethod');
    const phasingScope = document.getElementById('jsPhasingScope');
    const applyPhasingButton = document.getElementById('jsApplyPhasing');
    const clearPhasingButton = document.getElementById('jsClearPhasing');
    const copyCurrentToOuterYearsButton = document.getElementById('jsCopyCurrentToOuterYears');
    const outerYearMethod = document.getElementById('jsOuterYearMethod');
    const outerYearRate = document.getElementById('jsOuterYearRate');
    const applyOuterYearProjectionButton = document.getElementById('jsApplyOuterYearProjection');
    const clearOuterYearsButton = document.getElementById('jsClearOuterYears');
    const bpMismatchAlert = document.getElementById('jsBpMismatchAlert');
    const saveModeInput = document.getElementById('SaveMode');
    const resourceEnvelopeId = <?= $id ?>;
    const inflationRateAssumption = <?= json_encode($inflationRateAssumption, JSON_THROW_ON_ERROR) ?>;
    const customPhasingProfiles = <?= json_encode(array_reduce($phasingProfiles, static function (array $carry, array $profile): array {
        $weights = [];
        for ($i = 1; $i <= 12; $i++) {
            $weights[] = (float) ($profile['BP' . $i . 'Weight'] ?? 0);
        }
        $carry[(int) ($profile['PhasingProfileID'] ?? 0)] = [
            'name' => (string) ($profile['ProfileName'] ?? ''),
            'weights' => $weights,
        ];
        return $carry;
    }, []), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>;

    if (!fundingType || !fundingSource) {
        return;
    }

    const parseAmount = function (value) {
        const normalized = String(value || '0').replace(/,/g, '').trim();
        const parsed = parseFloat(normalized);
        return Number.isFinite(parsed) ? parsed : 0;
    };

    const formatAmount = function (value) {
        return value.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    };

    const buildMonthDayWeights = function (year) {
        return [31, (new Date(year, 1, 29).getMonth() === 1 ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    };

    const buildWorkdayWeights = function (year) {
        const weights = [];
        for (let month = 0; month < 12; month += 1) {
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            let workdays = 0;
            for (let day = 1; day <= daysInMonth; day += 1) {
                const weekday = new Date(year, month, day).getDay();
                if (weekday !== 0 && weekday !== 6) {
                    workdays += 1;
                }
            }
            weights.push(workdays);
        }
        return weights;
    };

    const allocateByWeights = function (total, weights) {
        const roundedTotal = Math.round(total);
        const safeWeights = Array.isArray(weights) && weights.length === 12 ? weights : new Array(12).fill(1);
        const weightSum = safeWeights.reduce(function (sum, weight) {
            return sum + (Number.isFinite(weight) ? weight : 0);
        }, 0);
        if (roundedTotal <= 0 || weightSum <= 0) {
            return new Array(12).fill('');
        }

        const rawAllocations = safeWeights.map(function (weight) {
            return (roundedTotal * weight) / weightSum;
        });
        const baseAllocations = rawAllocations.map(function (value) {
            return Math.floor(value);
        });
        let remainder = roundedTotal - baseAllocations.reduce(function (sum, value) {
            return sum + value;
        }, 0);

        const ranked = rawAllocations.map(function (value, index) {
            return {
                index: index,
                fraction: value - Math.floor(value),
            };
        }).sort(function (a, b) {
            if (b.fraction === a.fraction) {
                return a.index - b.index;
            }
            return b.fraction - a.fraction;
        });

        for (let i = 0; i < ranked.length && remainder > 0; i += 1) {
            baseAllocations[ranked[i].index] += 1;
            remainder -= 1;
        }

        return baseAllocations;
    };

    const normalizeInputValue = function (input) {
        if (!input) {
            return;
        }
        const raw = String(input.value || '').replace(/,/g, '').trim();
        if (raw === '') {
            input.value = '';
            return;
        }
        input.value = String(Math.round(parseAmount(raw)));
    };

    const formatInputValue = function (input) {
        if (!input) {
            return;
        }
        const raw = String(input.value || '').replace(/,/g, '').trim();
        if (raw === '') {
            input.value = '';
            return;
        }
        input.value = formatAmount(Math.round(parseAmount(raw)));
    };

    const applyFundingSourceFilter = function () {
        const selectedTypeId = fundingType.value;
        Array.from(fundingSource.options).forEach(function (option, index) {
            if (index === 0) {
                option.hidden = false;
                return;
            }
            const optionTypeId = option.getAttribute('data-funding-type-id') || '';
            const show = selectedTypeId === '' || optionTypeId === '' || optionTypeId === '0' || optionTypeId === selectedTypeId;
            option.hidden = !show;
            if (!show && option.selected) {
                fundingSource.value = '';
            }
        });
    };

    const applyFundingTypeDefaultPhasing = function () {
        if (!phasingMethod) {
            return;
        }
        const selectedOption = fundingType.options[fundingType.selectedIndex] || null;
        const defaultProfileId = selectedOption ? parseInt(selectedOption.getAttribute('data-default-phasing-profile-id') || '0', 10) : 0;
        if (defaultProfileId > 0 && customPhasingProfiles[defaultProfileId]) {
            phasingMethod.value = 'PROFILE:' + String(defaultProfileId);
            return;
        }
        phasingMethod.value = 'EVEN';
    };

    const refreshRestrictionDetailPanel = function () {
        if (!restrictionCode || !restrictionDetailPanel) {
            return;
        }
        const needsDetail = ['EARMARKED', 'RINGFENCED'].includes(String(restrictionCode.value || '').toUpperCase());
        restrictionDetailPanel.classList.toggle('d-none', !needsDetail);
        if (restrictionScopeType) {
            restrictionScopeType.required = needsDetail;
        }
        if (restrictionReference) {
            restrictionReference.required = needsDetail;
        }
        const restrictedFields = [
            document.getElementById('RestrictedSectorID'),
            restrictedProgram,
            restrictedSubProgram,
            document.getElementById('RestrictedOrgUnitID'),
            document.getElementById('RestrictedActivityID'),
            document.getElementById('RestrictedEconomicItemID'),
            document.getElementById('RestrictedProjectID'),
            document.getElementById('RestrictedProjectReference')
        ];
        if (!needsDetail) {
            if (restrictionScopeType) {
                restrictionScopeType.value = '';
            }
            if (restrictionReference) {
                restrictionReference.value = '';
            }
            const description = document.getElementById('RestrictionDescription');
            if (description) {
                description.value = '';
            }
            restrictedFields.forEach(function (field) {
                if (field) {
                    field.value = '';
                }
            });
        }
    };

    const applySubProgramFilter = function () {
        if (!restrictedProgram || !restrictedSubProgram) {
            return;
        }
        const selectedProgramId = String(restrictedProgram.value || '');
        Array.from(restrictedSubProgram.options).forEach(function (option, index) {
            if (index === 0) {
                option.hidden = false;
                return;
            }
            const optionProgramId = option.getAttribute('data-program-id') || '';
            const show = selectedProgramId === '' || optionProgramId === '' || optionProgramId === selectedProgramId;
            option.hidden = !show;
            if (!show && option.selected) {
                restrictedSubProgram.value = '';
            }
        });
    };

    const refreshEntryCheck = function () {
        if (!currentYearInput || !currentYearDisplay || !bpTotalDisplay || !varianceDisplay || !varianceNote || !outerTotalDisplay) {
            return;
        }

        const currentYear = parseAmount(currentYearInput.value);
        const bpTotal = bpInputs.reduce(function (sum, input) {
            return sum + parseAmount(input.value);
        }, 0);
        const outerTotal = parseAmount(outerYear1Input ? outerYear1Input.value : '0')
            + parseAmount(outerYear2Input ? outerYear2Input.value : '0')
            + parseAmount(outerYear3Input ? outerYear3Input.value : '0')
            + parseAmount(outerYear4Input ? outerYear4Input.value : '0')
            + parseAmount(outerYear5Input ? outerYear5Input.value : '0');
        const variance = currentYear - bpTotal;

        currentYearDisplay.textContent = formatAmount(currentYear);
        bpTotalDisplay.textContent = formatAmount(bpTotal);
        if (bpTotalTableDisplay) {
            bpTotalTableDisplay.textContent = formatAmount(bpTotal);
        }
        outerTotalDisplay.textContent = formatAmount(outerTotal);
        varianceDisplay.textContent = formatAmount(variance);
        if (varianceTableDisplay) {
            varianceTableDisplay.textContent = formatAmount(variance);
        }

        varianceDisplay.classList.remove('text-success', 'text-danger', 'text-body');
        if (varianceTableDisplay) {
            varianceTableDisplay.classList.remove('text-success', 'text-danger', 'text-body');
        }
        if (bpMismatchAlert) {
            bpMismatchAlert.classList.add('d-none');
        }
        if (bpTotal === 0) {
            varianceDisplay.classList.add('text-body');
            varianceNote.textContent = 'Enter BP phasing if you want to spread the current year amount.';
            if (varianceTableDisplay) {
                varianceTableDisplay.classList.add('text-body');
            }
            if (varianceTableNote) {
                varianceTableNote.textContent = 'Should be zero before save when phasing is used.';
            }
        } else if (Math.abs(variance) <= 0.01) {
            varianceDisplay.classList.add('text-success');
            varianceNote.textContent = 'BP phasing matches the current year amount.';
            if (varianceTableDisplay) {
                varianceTableDisplay.classList.add('text-success');
            }
            if (varianceTableNote) {
                varianceTableNote.textContent = 'BP phasing is aligned with the current year amount.';
            }
        } else {
            varianceDisplay.classList.add('text-danger');
            varianceNote.textContent = 'BP phasing does not yet match the current year amount.';
            if (varianceTableDisplay) {
                varianceTableDisplay.classList.add('text-danger');
            }
            if (varianceTableNote) {
                varianceTableNote.textContent = 'Review the phased values so the difference comes back to zero.';
            }
            if (bpMismatchAlert) {
                bpMismatchAlert.classList.remove('d-none');
            }
        }
    };

    const applyGeneratedPhasing = function () {
        if (!currentYearInput || !phasingMethod || bpInputs.length !== 12) {
            return;
        }

        const currentYear = parseAmount(currentYearInput.value);
        if (currentYear <= 0) {
            varianceNote.textContent = 'Enter a current year amount before applying phasing.';
            varianceDisplay.classList.remove('text-success', 'text-danger', 'text-body');
            varianceDisplay.classList.add('text-danger');
            return;
        }

        let weights = new Array(12).fill(1);
        if (String(phasingMethod.value || '').startsWith('PROFILE:')) {
            const profileId = parseInt(String(phasingMethod.value).split(':')[1] || '0', 10);
            weights = (customPhasingProfiles[profileId] && Array.isArray(customPhasingProfiles[profileId].weights))
                ? customPhasingProfiles[profileId].weights
                : weights;
        } else if (phasingMethod.value === 'CALENDAR_DAYS') {
            weights = buildMonthDayWeights(phasingBaseYear);
        } else if (phasingMethod.value === 'WORKDAYS') {
            weights = buildWorkdayWeights(phasingBaseYear);
        }

        const allocations = allocateByWeights(currentYear, weights);
        bpInputs.forEach(function (input, index) {
            const currentValue = String(input.value || '').replace(/,/g, '').trim();
            if (phasingScope && phasingScope.value === 'EMPTY_ONLY' && currentValue !== '') {
                formatInputValue(input);
                return;
            }
            input.value = allocations[index] === '' ? '' : String(allocations[index]);
            formatInputValue(input);
        });
        refreshEntryCheck();
    };

    const clearPhasing = function () {
        bpInputs.forEach(function (input) {
            input.value = '';
        });
        refreshEntryCheck();
    };

    const copyCurrentToOuterYears = function () {
        if (!currentYearInput) {
            return;
        }
        const currentYear = parseAmount(currentYearInput.value);
        [outerYear1Input, outerYear2Input, outerYear3Input, outerYear4Input, outerYear5Input].forEach(function (input) {
            if (!input) {
                return;
            }
            input.value = currentYear > 0 ? String(Math.round(currentYear)) : '';
            formatInputValue(input);
        });
        refreshEntryCheck();
    };

    const clearOuterYears = function () {
        [outerYear1Input, outerYear2Input, outerYear3Input, outerYear4Input, outerYear5Input].forEach(function (input) {
            if (input) {
                input.value = '';
            }
        });
        refreshEntryCheck();
    };

    const applyOuterYearProjection = function () {
        if (!currentYearInput || !outerYearMethod) {
            return;
        }
        const baseAmount = parseAmount(currentYearInput.value);
        if (baseAmount <= 0) {
            return;
        }

        const selectedMethod = outerYearMethod.value || 'FLAT';
        if (selectedMethod === 'MANUAL') {
            return;
        }

        let rate = 0;
        if (selectedMethod === 'INFLATION') {
            rate = Number.isFinite(inflationRateAssumption) ? Number(inflationRateAssumption) : 0;
        } else if (selectedMethod === 'CUSTOM_RATE') {
            rate = parseAmount(outerYearRate ? outerYearRate.value : '0') / 100;
        }

        const targets = [outerYear1Input, outerYear2Input, outerYear3Input, outerYear4Input, outerYear5Input];
        targets.forEach(function (input, index) {
            if (!input) {
                return;
            }
            const projected = selectedMethod === 'FLAT'
                ? baseAmount
                : Math.round(baseAmount * Math.pow(1 + rate, index + 1));
            input.value = String(Math.round(projected));
            formatInputValue(input);
        });
        refreshEntryCheck();
    };

    document.querySelectorAll('.js-envelope-number').forEach(function (input) {
        formatInputValue(input);

        input.addEventListener('focus', function () {
            normalizeInputValue(input);
            input.select();
        });

        input.addEventListener('blur', function () {
            formatInputValue(input);
            refreshEntryCheck();
        });
    });

    fundingType.addEventListener('change', function () {
        applyFundingSourceFilter();
        applyFundingTypeDefaultPhasing();
    });
    if (restrictionCode) {
        restrictionCode.addEventListener('change', refreshRestrictionDetailPanel);
    }
    if (restrictedProgram) {
        restrictedProgram.addEventListener('change', applySubProgramFilter);
    }
    if (currentYearInput) {
        currentYearInput.addEventListener('input', refreshEntryCheck);
    }
    if (outerYear1Input) {
        outerYear1Input.addEventListener('input', refreshEntryCheck);
    }
    if (outerYear2Input) {
        outerYear2Input.addEventListener('input', refreshEntryCheck);
    }
    if (outerYear3Input) {
        outerYear3Input.addEventListener('input', refreshEntryCheck);
    }
    if (outerYear4Input) {
        outerYear4Input.addEventListener('input', refreshEntryCheck);
    }
    if (outerYear5Input) {
        outerYear5Input.addEventListener('input', refreshEntryCheck);
    }
    bpInputs.forEach(function (input) {
        input.addEventListener('input', refreshEntryCheck);
    });

    if (applyPhasingButton) {
        applyPhasingButton.addEventListener('click', applyGeneratedPhasing);
    }
    if (clearPhasingButton) {
        clearPhasingButton.addEventListener('click', clearPhasing);
    }
    if (copyCurrentToOuterYearsButton) {
        copyCurrentToOuterYearsButton.addEventListener('click', copyCurrentToOuterYears);
    }
    if (applyOuterYearProjectionButton) {
        applyOuterYearProjectionButton.addEventListener('click', applyOuterYearProjection);
    }
    if (clearOuterYearsButton) {
        clearOuterYearsButton.addEventListener('click', clearOuterYears);
    }

    document.querySelectorAll('.js-save-mode').forEach(function (button) {
        button.addEventListener('click', function () {
            if (saveModeInput) {
                saveModeInput.value = button.getAttribute('data-save-mode') || 'close';
            }
        });
    });

    const form = document.querySelector('form[action="index.php?route=strategy-fiscal/save-resource-envelope"]');
    if (form) {
        form.addEventListener('submit', function () {
            document.querySelectorAll('.js-envelope-number').forEach(function (input) {
                normalizeInputValue(input);
            });
        });
    }

    applyFundingSourceFilter();
    if (resourceEnvelopeId <= 0) {
        applyFundingTypeDefaultPhasing();
    }
    applySubProgramFilter();
    refreshRestrictionDetailPanel();
    refreshEntryCheck();
});
</script>
