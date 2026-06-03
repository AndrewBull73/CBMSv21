<?php
declare(strict_types=1);
/** @var array $submission */
/** @var array|null $line */
/** @var string|null $effectiveDataObjectCode */
/** @var array $sectorOptions */
/** @var array $programOptions */
/** @var array $subProgramOptions */
/** @var array $projectOptions */
/** @var array $activityOptions */
/** @var array $orgUnitOptions */
/** @var array $fundingTypeOptions */
/** @var array $fundingSourceOptions */
/** @var array $economicItemOptions */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('option_label_with_code')) {
    function option_label_with_code(?string $code, ?string $label): string
    {
        $code = trim((string) $code);
        $label = trim((string) $label);
        if ($code !== '' && $label !== '') {
            return $code . ' - ' . $label;
        }
        return $label !== '' ? $label : $code;
    }
}
if (!function_exists('format_whole_amount')) {
    function format_whole_amount($value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $normalized = str_replace(',', '', $raw);
        if (!is_numeric($normalized)) {
            return $raw;
        }

        return number_format((float) $normalized, 0, '.', ',');
    }
}
$line = is_array($line ?? null) ? $line : null;
$selectedSectorId = (int) ($line['SectorID'] ?? 0);
$selectedProgramId = (int) ($line['ProgramID'] ?? 0);
$selectedSubProgramId = (int) ($line['SubProgramID'] ?? 0);
$selectedProjectId = (int) ($line['ProjectID'] ?? 0);
$selectedActivityId = (int) ($line['ActivityID'] ?? 0);
$selectedOrgUnitId = (int) ($line['OrgUnitID'] ?? 0);
$effectiveDataObjectCode = trim((string) ($effectiveDataObjectCode ?? ($submission['DataObjectCode'] ?? '')));
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
$sectorOptionData = array_map(static function (array $option): array {
    return [
        'id' => (int) ($option['SectorID'] ?? 0),
        'label' => option_label_with_code((string) ($option['SectorID'] ?? ''), (string) ($option['SectorName'] ?? '')),
    ];
}, $sectorOptions);
$programOptionData = array_map(static function (array $option): array {
    return [
        'id' => (int) ($option['ProgramID'] ?? 0),
        'label' => option_label_with_code((string) ($option['ProgramCode'] ?? ''), (string) ($option['ProgramName'] ?? '')),
        'sectorId' => (int) ($option['SectorID'] ?? 0),
        'orgUnitId' => (int) ($option['OrgUnitID'] ?? 0),
    ];
}, $programOptions);
$subProgramOptionData = array_map(static function (array $option): array {
    return [
        'id' => (int) ($option['SubProgramID'] ?? 0),
        'label' => option_label_with_code((string) ($option['SubProgramCode'] ?? ''), (string) ($option['SubProgramName'] ?? '')),
        'programId' => (int) ($option['ProgramID'] ?? 0),
        'sectorId' => (int) ($option['SectorID'] ?? 0),
        'orgUnitId' => (int) ($option['OrgUnitID'] ?? 0),
    ];
}, $subProgramOptions);
$projectOptionData = array_map(static function (array $option): array {
    return [
        'id' => (int) ($option['ProjectID'] ?? 0),
        'label' => option_label_with_code((string) ($option['ProjectCode'] ?? ''), (string) ($option['ProjectName'] ?? '')),
    ];
}, $projectOptions);
$activityOptionData = array_map(static function (array $option): array {
    return [
        'id' => (int) ($option['ActivityID'] ?? 0),
        'label' => trim((string) ($option['ActivityName'] ?? '')),
        'programId' => (int) ($option['ProgramID'] ?? 0),
        'subProgramId' => (int) ($option['SubProgramID'] ?? 0),
        'sectorId' => (int) ($option['SectorID'] ?? 0),
        'orgUnitId' => (int) ($option['OrgUnitID'] ?? 0),
    ];
}, $activityOptions);
$orgUnitOptionData = array_map(static function (array $option): array {
    return [
        'id' => (int) ($option['OrgUnitID'] ?? 0),
        'label' => option_label_with_code((string) ($option['SourceDataObjectCode'] ?? $option['VoteCode'] ?? ''), (string) ($option['OrgUnitName'] ?? '')),
    ];
}, $orgUnitOptions);
?>
<div class="container-fluid py-3">
  <style>
    .container-fluid.py-3 {
      font-size: .95rem;
    }
    .item-shell {
      background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
      border: 1px solid #d9e6f2;
      border-radius: 1.15rem;
      overflow: hidden;
      box-shadow: 0 .45rem 1.35rem rgba(43, 63, 87, 0.06);
    }
    .item-hero {
      background: linear-gradient(135deg, #f3f9ff 0%, #ffffff 100%);
      border-bottom: 1px solid #dce8f3;
      padding: 1.2rem 1.3rem;
    }
    .item-title {
      font-size: 1.42rem;
      line-height: 1.2;
      letter-spacing: -.02em;
    }
    .item-subtext {
      font-size: .9rem;
      max-width: 52rem;
    }
    .item-section-title {
      font-size: .72rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #6c757d;
      font-weight: 700;
      margin-bottom: .75rem;
    }
    .item-meta-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .45rem .75rem;
      border-radius: 999px;
      background: #eef3f8;
      color: #435160;
      font-size: .84rem;
      font-weight: 600;
    }
    .item-panel {
      background: #fff;
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .item-form .form-control,
    .item-form .form-select {
      font-size: .875rem;
    }
    .item-form textarea.form-control {
      min-height: 8rem;
    }
    .item-amount-grid thead th {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .item-amount-grid tbody td {
      vertical-align: middle;
    }
    .item-period-cell {
      font-weight: 600;
      color: #304255;
      white-space: nowrap;
    }
    .item-footer-note {
      font-size: .88rem;
    }
    .item-field-help {
      font-size: .8rem;
      color: #6c757d;
      margin-top: .35rem;
    }
  </style>

  <div class="item-shell mb-3">
    <div class="item-hero d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="item-section-title mb-2">Funding Item</div>
        <h1 class="item-title mb-2"><?= $line ? 'Edit Funding Item' : 'Add Funding Item' ?></h1>
        <div class="text-muted item-subtext">
          Capture the fiscal, program, and narrative detail for this funding item under
          <strong><?= h((string) ($submission['RequestTitle'] ?? '')) ?></strong>.
        </div>
      </div>
      <a href="index.php?route=strategy-submissions/view&id=<?= (int) ($submission['StrategicFundingSubmissionID'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
  </div>

  <div class="card shadow-sm item-shell">
    <div class="card-body p-4">
      <form method="post" action="index.php?route=strategy-submissions/save-line" class="item-form">
        <input type="hidden" name="StrategicFundingSubmissionID" value="<?= (int) ($submission['StrategicFundingSubmissionID'] ?? 0) ?>">
        <input type="hidden" name="StrategicFundingSubmissionLineID" value="<?= (int) ($line['StrategicFundingSubmissionLineID'] ?? 0) ?>">

        <div class="item-panel mb-4">
          <div class="item-section-title">Funding Item Overview</div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <?php if ($effectiveDataObjectCode !== ''): ?>
              <span class="item-meta-chip">System DataScope: <?= h($effectiveDataObjectCode) ?></span>
            <?php endif; ?>
            <?php if (!empty($submission['SubmissionTypeCode'])): ?>
              <span class="item-meta-chip">Submission Type: <?= h((string) ($submission['SubmissionTypeCode'] ?? '')) ?></span>
            <?php endif; ?>
            <?php if (!empty($submission['PriorityCode'])): ?>
              <span class="item-meta-chip">Priority: <?= h((string) ($submission['PriorityCode'] ?? '')) ?></span>
            <?php endif; ?>
          </div>
          <div class="fs-5 fw-semibold"><?= h((string) ($submission['RequestTitle'] ?? 'Funding Submission')) ?></div>
          <?php if (!empty($submission['RequestNotes'])): ?>
            <div class="text-muted mt-2"><?= nl2br(h((string) ($submission['RequestNotes'] ?? ''))) ?></div>
          <?php endif; ?>
        </div>

        <div class="row g-4">
          <div class="col-xl-8">
            <div class="item-panel mb-4">
              <div class="item-section-title">Core Details</div>
              <div class="row g-3">
                <div class="col-md-8">
                  <label class="form-label">Item Title</label>
                  <input type="text" name="BidTitle" class="form-control form-control-lg" required value="<?= h((string) ($line['BidTitle'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Priority Rank</label>
                  <input type="number" name="PriorityRank" class="form-control" value="<?= h((string) ($line['PriorityRank'] ?? '')) ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Sector</label>
                  <select name="SectorID" id="FundingItemSectorID" class="form-select">
                    <option value="">Select sector</option>
                    <?php foreach ($sectorOptions as $option): ?>
                      <option value="<?= (int) ($option['SectorID'] ?? 0) ?>" <?= $selectedSectorId === (int) ($option['SectorID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['SectorID'] ?? ''), (string) ($option['SectorName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="item-field-help">Sectors are limited to the ministries and programs available in the current System DataScope.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Program</label>
                  <select name="ProgramID" id="FundingItemProgramID" class="form-select">
                    <option value="">Select program</option>
                    <?php foreach ($programOptions as $option): ?>
                      <option value="<?= (int) ($option['ProgramID'] ?? 0) ?>" <?= $selectedProgramId === (int) ($option['ProgramID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['ProgramCode'] ?? ''), (string) ($option['ProgramName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">SubProgram</label>
                  <select name="SubProgramID" id="FundingItemSubProgramID" class="form-select">
                    <option value="">Select subprogram</option>
                    <?php foreach ($subProgramOptions as $option): ?>
                      <option value="<?= (int) ($option['SubProgramID'] ?? 0) ?>" <?= $selectedSubProgramId === (int) ($option['SubProgramID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['SubProgramCode'] ?? ''), (string) ($option['SubProgramName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Project</label>
                  <select name="ProjectID" id="FundingItemProjectID" class="form-select">
                    <option value="">Select project</option>
                    <?php foreach ($projectOptions as $option): ?>
                      <option value="<?= (int) ($option['ProjectID'] ?? 0) ?>" <?= $selectedProjectId === (int) ($option['ProjectID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['ProjectCode'] ?? ''), (string) ($option['ProjectName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="item-field-help">Projects are scoped to the current System DataScope.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Activity</label>
                  <select name="ActivityID" id="FundingItemActivityID" class="form-select">
                    <option value="">Select activity</option>
                    <?php foreach ($activityOptions as $option): ?>
                      <option value="<?= (int) ($option['ActivityID'] ?? 0) ?>" <?= $selectedActivityId === (int) ($option['ActivityID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h((string) ($option['ActivityName'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Org Unit</label>
                  <select name="OrgUnitID" id="FundingItemOrgUnitID" class="form-select">
                    <option value="">Select org unit</option>
                    <?php foreach ($orgUnitOptions as $option): ?>
                      <option value="<?= (int) ($option['OrgUnitID'] ?? 0) ?>" <?= $selectedOrgUnitId === (int) ($option['OrgUnitID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['SourceDataObjectCode'] ?? $option['VoteCode'] ?? ''), (string) ($option['OrgUnitName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="item-field-help">Org units are limited to the current System DataScope and narrow further when a sector is chosen.</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Funding Type</label>
                  <select name="FundingTypeID" class="form-select">
                    <option value="">Select funding type</option>
                    <?php foreach ($fundingTypeOptions as $option): ?>
                      <option value="<?= (int) ($option['FundingTypeID'] ?? 0) ?>" <?= (int) ($line['FundingTypeID'] ?? 0) === (int) ($option['FundingTypeID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['FundingTypeCode'] ?? ''), (string) ($option['FundingTypeName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Funding Source</label>
                  <select name="FundingSourceID" class="form-select">
                    <option value="">Select funding source</option>
                    <?php foreach ($fundingSourceOptions as $option): ?>
                      <option value="<?= (int) ($option['FundingSourceID'] ?? 0) ?>" <?= (int) ($line['FundingSourceID'] ?? 0) === (int) ($option['FundingSourceID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['FundingSourceCode'] ?? ''), (string) ($option['FundingSourceName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Economic Item</label>
                  <select name="EconomicItemID" class="form-select">
                    <option value="">Select economic item</option>
                    <?php foreach ($economicItemOptions as $option): ?>
                      <option value="<?= (int) ($option['EconomicItemID'] ?? 0) ?>" <?= (int) ($line['EconomicItemID'] ?? 0) === (int) ($option['EconomicItemID'] ?? 0) ? 'selected' : '' ?>>
                        <?= h(option_label_with_code((string) ($option['EconomicCode'] ?? ''), (string) ($option['EconomicName'] ?? ''))) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-12">
                  <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="ActiveFlag" id="ActiveFlag" <?= !isset($line['ActiveFlag']) || !empty($line['ActiveFlag']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="ActiveFlag">Active</label>
                  </div>
                </div>
              </div>
            </div>

            <div class="item-panel">
              <div class="item-section-title">Narrative</div>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Business Case Summary</label>
                  <textarea name="BusinessCaseSummary" class="form-control" rows="5"><?= h((string) ($line['BusinessCaseSummary'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Expected Output</label>
                  <textarea name="ExpectedOutput" class="form-control" rows="3"><?= h((string) ($line['ExpectedOutput'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Expected Outcome</label>
                  <textarea name="ExpectedOutcome" class="form-control" rows="3"><?= h((string) ($line['ExpectedOutcome'] ?? '')) ?></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-4">
            <div class="item-panel mb-4">
              <div class="item-section-title">Requested Amounts</div>
              <div class="table-responsive">
                <table class="table table-sm align-middle item-amount-grid mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Period</th>
                      <th class="text-end">Requested Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="item-period-cell">Current Year</td>
                      <td>
                        <input type="text" inputmode="numeric" name="CurrentYearRequestedAmount" class="form-control text-end js-whole-amount" required value="<?= h(format_whole_amount($line['CurrentYearRequestedAmount'] ?? '')) ?>">
                      </td>
                    </tr>
                    <tr>
                      <td class="item-period-cell">Outer Year 1</td>
                      <td>
                        <input type="text" inputmode="numeric" name="OuterYear1RequestedAmount" class="form-control text-end js-whole-amount" value="<?= h(format_whole_amount($line['OuterYear1RequestedAmount'] ?? '')) ?>">
                      </td>
                    </tr>
                    <tr>
                      <td class="item-period-cell">Outer Year 2</td>
                      <td>
                        <input type="text" inputmode="numeric" name="OuterYear2RequestedAmount" class="form-control text-end js-whole-amount" value="<?= h(format_whole_amount($line['OuterYear2RequestedAmount'] ?? '')) ?>">
                      </td>
                    </tr>
                    <tr>
                      <td class="item-period-cell">Outer Year 3</td>
                      <td>
                        <input type="text" inputmode="numeric" name="OuterYear3RequestedAmount" class="form-control text-end js-whole-amount" value="<?= h(format_whole_amount($line['OuterYear3RequestedAmount'] ?? '')) ?>">
                      </td>
                    </tr>
                    <tr>
                      <td class="item-period-cell">Outer Year 4</td>
                      <td>
                        <input type="text" inputmode="numeric" name="OuterYear4RequestedAmount" class="form-control text-end js-whole-amount" value="<?= h(format_whole_amount($line['OuterYear4RequestedAmount'] ?? '')) ?>">
                      </td>
                    </tr>
                    <tr>
                      <td class="item-period-cell">Outer Year 5</td>
                      <td>
                        <input type="text" inputmode="numeric" name="OuterYear5RequestedAmount" class="form-control text-end js-whole-amount" value="<?= h(format_whole_amount($line['OuterYear5RequestedAmount'] ?? '')) ?>">
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

          </div>

          <div class="col-12">
            <div class="item-panel">
              <div class="item-section-title">Preparation Notes</div>
              <div class="text-muted item-footer-note">
                This screen captures the detail for one funding item. Review outcomes and approved amounts are recorded in the separate review step.
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">Save Funding Item</button>
          <a href="index.php?route=strategy-submissions/view&id=<?= (int) ($submission['StrategicFundingSubmissionID'] ?? 0) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
  <script>
    (function () {
      const sectorSelect = document.getElementById('FundingItemSectorID');
      const programSelect = document.getElementById('FundingItemProgramID');
      const subProgramSelect = document.getElementById('FundingItemSubProgramID');
      const projectSelect = document.getElementById('FundingItemProjectID');
      const activitySelect = document.getElementById('FundingItemActivityID');
      const orgUnitSelect = document.getElementById('FundingItemOrgUnitID');
      const form = document.querySelector('.item-form');
      const amountInputs = Array.from(document.querySelectorAll('.js-whole-amount'));

      if (!sectorSelect || !programSelect || !subProgramSelect || !projectSelect || !activitySelect || !orgUnitSelect || !form) {
        return;
      }

      const optionData = {
        sectors: <?= json_encode($sectorOptionData, $jsonFlags) ?>,
        programs: <?= json_encode($programOptionData, $jsonFlags) ?>,
        subPrograms: <?= json_encode($subProgramOptionData, $jsonFlags) ?>,
        projects: <?= json_encode($projectOptionData, $jsonFlags) ?>,
        activities: <?= json_encode($activityOptionData, $jsonFlags) ?>,
        orgUnits: <?= json_encode($orgUnitOptionData, $jsonFlags) ?>,
      };

      function renderOptions(select, items, placeholder, selectedValue) {
        const wanted = String(selectedValue || '');
        select.innerHTML = '';

        const placeholderOption = document.createElement('option');
        placeholderOption.value = '';
        placeholderOption.textContent = placeholder;
        select.appendChild(placeholderOption);

        let hasSelectedValue = wanted === '';
        items.forEach((item) => {
          const option = document.createElement('option');
          option.value = String(item.id || '');
          option.textContent = String(item.label || '');
          if (option.value === wanted) {
            option.selected = true;
            hasSelectedValue = true;
          }
          select.appendChild(option);
        });

        if (!hasSelectedValue) {
          select.value = '';
        }

        select.disabled = items.length === 0;
      }

      function visiblePrograms(sectorId) {
        const wantedSectorId = Number(sectorId || 0);
        return optionData.programs.filter((item) => wantedSectorId <= 0 || Number(item.sectorId || 0) === wantedSectorId);
      }

      function visibleOrgUnits() {
        return optionData.orgUnits;
      }

      function visibleSubPrograms(programId) {
        const wantedProgramId = Number(programId || 0);
        return optionData.subPrograms.filter((item) => wantedProgramId <= 0 || Number(item.programId || 0) === wantedProgramId);
      }

      function visibleActivities(programId, subProgramId) {
        const wantedProgramId = Number(programId || 0);
        const wantedSubProgramId = Number(subProgramId || 0);
        return optionData.activities.filter((item) => {
          if (wantedProgramId > 0 && Number(item.programId || 0) !== wantedProgramId) {
            return false;
          }
          if (wantedSubProgramId > 0 && Number(item.subProgramId || 0) !== wantedSubProgramId) {
            return false;
          }
          return true;
        });
      }

      function syncPrograms() {
        const previousProgramId = programSelect.value;
        const items = visiblePrograms(sectorSelect.value);
        renderOptions(programSelect, items, 'Select program', previousProgramId);

        if (!programSelect.value && items.length === 1) {
          programSelect.value = String(items[0].id || '');
        }
      }

      function syncOrgUnits() {
        const previousOrgUnitId = orgUnitSelect.value;
        const items = visibleOrgUnits();
        renderOptions(orgUnitSelect, items, 'Select org unit', previousOrgUnitId);
      }

      function syncSubPrograms() {
        const previousSubProgramId = subProgramSelect.value;
        const items = visibleSubPrograms(programSelect.value);
        renderOptions(subProgramSelect, items, 'Select subprogram', previousSubProgramId);
      }

      function syncActivities() {
        const previousActivityId = activitySelect.value;
        const items = visibleActivities(programSelect.value, subProgramSelect.value);
        renderOptions(activitySelect, items, 'Select activity', previousActivityId);
      }

      function refreshDependents() {
        syncPrograms();
        syncOrgUnits();
        syncSubPrograms();
        syncActivities();
      }

      function sanitizeWholeAmount(value) {
        const cleaned = String(value || '').replace(/,/g, '').replace(/[^\d-]/g, '');
        if (cleaned === '' || cleaned === '-') {
          return '';
        }
        const parsed = Number(cleaned);
        if (!Number.isFinite(parsed)) {
          return '';
        }
        return String(Math.round(parsed));
      }

      function formatWholeAmountValue(value) {
        const sanitized = sanitizeWholeAmount(value);
        if (sanitized === '') {
          return '';
        }
        return Number(sanitized).toLocaleString('en-US', { maximumFractionDigits: 0 });
      }

      sectorSelect.addEventListener('change', function () {
        refreshDependents();
      });

      programSelect.addEventListener('change', function () {
        const selectedProgram = optionData.programs.find((item) => String(item.id || '') === String(programSelect.value || ''));
        if (selectedProgram && String(sectorSelect.value || '') === '') {
          sectorSelect.value = String(selectedProgram.sectorId || '');
        }
        syncOrgUnits();
        syncSubPrograms();
        syncActivities();
      });

      subProgramSelect.addEventListener('change', function () {
        syncActivities();
      });

      renderOptions(projectSelect, optionData.projects, 'Select project', projectSelect.value);
      refreshDependents();

      amountInputs.forEach(function (input) {
        input.addEventListener('focus', function () {
          input.value = sanitizeWholeAmount(input.value);
        });
        input.addEventListener('blur', function () {
          input.value = formatWholeAmountValue(input.value);
        });
        input.value = formatWholeAmountValue(input.value);
      });

      form.addEventListener('submit', function () {
        amountInputs.forEach(function (input) {
          input.value = sanitizeWholeAmount(input.value);
        });
      });
    })();
  </script>
</div>
