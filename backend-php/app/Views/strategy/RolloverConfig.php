<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$context = is_array($context ?? null) ? $context : [];
$fiscalYears = is_array($fiscalYears ?? null) ? $fiscalYears : [];
$versionsByFiscalYear = is_array($versionsByFiscalYear ?? null) ? $versionsByFiscalYear : [];
$versionScopes = is_array($versionScopes ?? null) ? $versionScopes : [];
$fiscalScopes = is_array($fiscalScopes ?? null) ? $fiscalScopes : [];
$versionDefaults = is_array($versionDefaults ?? null) ? $versionDefaults : [];
$fiscalDefaults = is_array($fiscalDefaults ?? null) ? $fiscalDefaults : [];
$rolloverAvailable = !empty($rolloverAvailable);

$yearLabel = (string) ($context['YearLabel'] ?? '');
$versionLabel = (string) ($context['VersionLabel'] ?? '');

$versionScopeAvailableCount = count(array_filter($versionScopes, static fn(array $scope): bool => (bool) ($scope['installed'] ?? false)));
$fiscalScopeAvailableCount = count(array_filter($fiscalScopes, static fn(array $scope): bool => (bool) ($scope['installed'] ?? false)));

$screenHeader = [
    'title' => __t('strategy_rollover'),
    'icon' => 'bi-arrow-repeat',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h(__t('fiscal_year')) ?></div>
              <div class="fs-4 fw-semibold"><?= count($fiscalYears) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h(__t('strategy_scope_group_version')) ?></div>
              <div class="fs-4 fw-semibold"><?= $versionScopeAvailableCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h(__t('strategy_scope_group_fiscal')) ?></div>
              <div class="fs-4 fw-semibold"><?= $fiscalScopeAvailableCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h(__t('status')) ?></div>
              <div class="fs-4 fw-semibold"><?= $rolloverAvailable ? 'Ready' : 'Pending setup' ?></div>
            </div>
          </div>
        </div>
      </div>

      <?php if (!$rolloverAvailable): ?>
        <div class="alert alert-warning">
          <?= h(__t('strategy_rollover_not_available')) ?>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        <div><?= h(__t('strategy_rollover_intro')) ?></div>
        <div class="mt-2"><?= h(__t('strategy_rollover_runtime_note')) ?></div>
        <div class="mt-2"><?= h(__t('strategy_rollover_scope_note')) ?></div>
      </div>

      <div class="row g-4">
        <div class="col-xl-6">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0"><?= h(__t('strategy_version_rollover')) ?></h5>
            </div>
            <div class="card-body">
              <div class="small text-muted mb-3">
                <?= h(__t('strategy_rollover_version_intro')) ?>
              </div>

              <form id="version-rollover-form" method="post" action="index.php?route=strategy-config/run-version-rollover">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <h5 class="mb-0"><?= h(__t('strategy_source_context')) ?></h5>
                  </div>
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="version-rollover-source-fy" class="form-label"><?= h(__t('strategy_source_fiscal_year')) ?></label>
                        <select id="version-rollover-source-fy" name="SourceFiscalYearID" class="form-select" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                          <?php foreach ($fiscalYears as $row): ?>
                            <option value="<?= (int) ($row['FiscalYearID'] ?? 0) ?>" <?= (int) ($versionDefaults['SourceFiscalYearID'] ?? 0) === (int) ($row['FiscalYearID'] ?? 0) ? 'selected' : '' ?>>
                              <?= h((string) ($row['YearLabel'] ?? $row['FiscalYearID'] ?? '')) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label for="version-rollover-source-version" class="form-label"><?= h(__t('strategy_source_version')) ?></label>
                        <select id="version-rollover-source-version" name="SourceVersionID" class="form-select" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                          <option value=""><?= h(__t('strategy_rollover_no_submission_versions')) ?></option>
                        </select>
                        <div id="version-rollover-source-version-note" class="form-text"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <h5 class="mb-0"><?= h(__t('strategy_target_version')) ?></h5>
                  </div>
                  <div class="card-body">
                    <div class="mb-3">
                      <label for="version-rollover-target-label" class="form-label"><?= h(__t('strategy_target_version_label')) ?></label>
                      <input type="text" id="version-rollover-target-label" name="TargetVersionLabel" class="form-control" value="<?= h((string) ($versionDefaults['TargetVersionLabel'] ?? '')) ?>" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="IsDefault" id="version-rollover-default-flag" <?= !empty($versionDefaults['IsDefault']) ? 'checked' : '' ?> <?= $rolloverAvailable ? '' : 'disabled' ?>>
                      <label class="form-check-label" for="version-rollover-default-flag"><?= h(__t('strategy_make_default_version')) ?></label>
                    </div>
                  </div>
                </div>

                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <h5 class="mb-0"><?= h(__t('strategy_copy_scopes')) ?></h5>
                  </div>
                  <div class="card-body">
                    <div class="small text-muted mb-3"><?= h(__t('strategy_rollover_scope_selection_note')) ?></div>
                    <div class="row g-2">
                      <?php foreach ($versionScopes as $scopeCode => $scope): ?>
                        <?php $installed = !empty($scope['installed']); ?>
                        <div class="col-12">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="Scopes[<?= h((string) $scopeCode) ?>]" id="version-scope-<?= h((string) $scopeCode) ?>" value="1" <?= $installed && $rolloverAvailable ? 'checked' : '' ?> <?= $installed && $rolloverAvailable ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="version-scope-<?= h((string) $scopeCode) ?>">
                              <?= h(__t((string) ($scope['label'] ?? $scopeCode))) ?>
                              <?php if (!$installed): ?>
                                <span class="text-muted">(not installed)</span>
                              <?php endif; ?>
                            </label>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>

                <div class="d-flex justify-content-end">
                  <button type="submit" id="version-rollover-submit-btn" class="btn btn-primary" <?= $rolloverAvailable ? '' : 'disabled' ?>><?= h(__t('strategy_run_version_rollover')) ?></button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="col-xl-6">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0"><?= h(__t('strategy_fiscal_year_rollover')) ?></h5>
            </div>
            <div class="card-body">
              <div class="small text-muted mb-3">
                <?= h(__t('strategy_rollover_fiscal_intro')) ?>
              </div>

              <form id="fiscal-rollover-form" method="post" action="index.php?route=strategy-config/run-fiscal-year-rollover">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">

                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <h5 class="mb-0"><?= h(__t('strategy_source_context')) ?></h5>
                  </div>
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label for="fiscal-rollover-source-fy" class="form-label"><?= h(__t('strategy_source_fiscal_year')) ?></label>
                        <select id="fiscal-rollover-source-fy" name="SourceFiscalYearID" class="form-select" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                          <?php foreach ($fiscalYears as $row): ?>
                            <option value="<?= (int) ($row['FiscalYearID'] ?? 0) ?>" <?= (int) ($fiscalDefaults['SourceFiscalYearID'] ?? 0) === (int) ($row['FiscalYearID'] ?? 0) ? 'selected' : '' ?>>
                              <?= h((string) ($row['YearLabel'] ?? $row['FiscalYearID'] ?? '')) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-6">
                        <label for="fiscal-rollover-source-version" class="form-label"><?= h(__t('strategy_source_version')) ?></label>
                        <select id="fiscal-rollover-source-version" name="SourceVersionID" class="form-select" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                          <option value=""><?= h(__t('strategy_rollover_no_submission_versions')) ?></option>
                        </select>
                        <div id="fiscal-rollover-source-version-note" class="form-text"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <h5 class="mb-0"><?= h(__t('strategy_target_fiscal_year')) ?></h5>
                  </div>
                  <div class="card-body">
                    <div class="row g-3 mb-3">
                      <div class="col-md-4">
                        <label for="fiscal-rollover-target-fy" class="form-label"><?= h(__t('strategy_target_fiscal_year')) ?></label>
                        <input type="number" id="fiscal-rollover-target-fy" name="TargetFiscalYearID" class="form-control" value="<?= (int) ($fiscalDefaults['TargetFiscalYearID'] ?? 0) ?>" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                      </div>
                      <div class="col-md-8">
                        <label for="fiscal-rollover-target-year-label" class="form-label"><?= h(__t('strategy_target_year_label')) ?></label>
                        <input type="text" id="fiscal-rollover-target-year-label" name="TargetYearLabel" class="form-control" value="<?= h((string) ($fiscalDefaults['TargetYearLabel'] ?? '')) ?>" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                      </div>
                    </div>

                    <div class="row g-3 mb-3">
                      <div class="col-md-6">
                        <label for="fiscal-rollover-target-start-date" class="form-label"><?= h(__t('strategy_target_start_date')) ?></label>
                        <input type="date" id="fiscal-rollover-target-start-date" name="TargetStartDate" class="form-control" value="<?= h((string) ($fiscalDefaults['TargetStartDate'] ?? '')) ?>" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                      </div>
                      <div class="col-md-6">
                        <label for="fiscal-rollover-target-end-date" class="form-label"><?= h(__t('strategy_target_end_date')) ?></label>
                        <input type="date" id="fiscal-rollover-target-end-date" name="TargetEndDate" class="form-control" value="<?= h((string) ($fiscalDefaults['TargetEndDate'] ?? '')) ?>" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                      </div>
                    </div>

                    <div class="mb-3">
                      <label for="fiscal-rollover-target-version-label" class="form-label"><?= h(__t('strategy_target_initial_version_label')) ?></label>
                      <input type="text" id="fiscal-rollover-target-version-label" name="TargetVersionLabel" class="form-control" value="<?= h((string) ($fiscalDefaults['TargetVersionLabel'] ?? '')) ?>" <?= $rolloverAvailable ? '' : 'disabled' ?>>
                    </div>

                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" name="TargetFiscalYearActive" id="fiscal-rollover-target-active-flag" <?= !empty($fiscalDefaults['TargetFiscalYearActive']) ? 'checked' : '' ?> <?= $rolloverAvailable ? '' : 'disabled' ?>>
                      <label class="form-check-label" for="fiscal-rollover-target-active-flag"><?= h(__t('strategy_target_fiscal_year_active')) ?></label>
                    </div>

                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="IsDefault" id="fiscal-rollover-default-flag" <?= !empty($fiscalDefaults['IsDefault']) ? 'checked' : '' ?> <?= $rolloverAvailable ? '' : 'disabled' ?>>
                      <label class="form-check-label" for="fiscal-rollover-default-flag"><?= h(__t('strategy_make_default_version')) ?></label>
                    </div>
                  </div>
                </div>

                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <h5 class="mb-0"><?= h(__t('strategy_scope_group_fiscal')) ?></h5>
                  </div>
                  <div class="card-body">
                    <div class="row g-2">
                      <?php foreach ($fiscalScopes as $scopeCode => $scope): ?>
                        <?php $installed = !empty($scope['installed']); ?>
                        <div class="col-12">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="FiscalScopes[<?= h((string) $scopeCode) ?>]" id="fiscal-scope-<?= h((string) $scopeCode) ?>" value="1" <?= $installed && $rolloverAvailable ? 'checked' : '' ?> <?= $installed && $rolloverAvailable ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="fiscal-scope-<?= h((string) $scopeCode) ?>">
                              <?= h(__t((string) ($scope['label'] ?? $scopeCode))) ?>
                              <?php if (!$installed): ?>
                                <span class="text-muted">(not installed)</span>
                              <?php endif; ?>
                            </label>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>

                <div class="card shadow-sm mb-4">
                  <div class="card-header">
                    <h5 class="mb-0"><?= h(__t('strategy_scope_group_version')) ?></h5>
                  </div>
                  <div class="card-body">
                    <div class="row g-2">
                      <?php foreach ($versionScopes as $scopeCode => $scope): ?>
                        <?php $installed = !empty($scope['installed']); ?>
                        <div class="col-12">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="VersionScopes[<?= h((string) $scopeCode) ?>]" id="fiscal-version-scope-<?= h((string) $scopeCode) ?>" value="1" <?= $installed && $rolloverAvailable ? 'checked' : '' ?> <?= $installed && $rolloverAvailable ? '' : 'disabled' ?>>
                            <label class="form-check-label" for="fiscal-version-scope-<?= h((string) $scopeCode) ?>">
                              <?= h(__t((string) ($scope['label'] ?? $scopeCode))) ?>
                              <?php if (!$installed): ?>
                                <span class="text-muted">(not installed)</span>
                              <?php endif; ?>
                            </label>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>

                <div class="d-flex justify-content-end">
                  <button type="submit" id="fiscal-rollover-submit-btn" class="btn btn-primary" <?= $rolloverAvailable ? '' : 'disabled' ?>><?= h(__t('strategy_run_fiscal_rollover')) ?></button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const rolloverAvailable = <?= $rolloverAvailable ? 'true' : 'false' ?>;
    const versionsByFiscalYear = <?= json_encode($versionsByFiscalYear, JSON_THROW_ON_ERROR) ?>;
    const fiscalYears = <?= json_encode($fiscalYears, JSON_THROW_ON_ERROR) ?>;
    const versionDefaultVersionId = <?= (int) ($versionDefaults['SourceVersionID'] ?? 0) ?>;
    const fiscalDefaultVersionId = <?= (int) ($fiscalDefaults['SourceVersionID'] ?? 0) ?>;

    const fiscalYearMap = {};
    fiscalYears.forEach(function (row) {
        fiscalYearMap[String(row.FiscalYearID || '')] = row;
    });

    function buildVersionOptionLabel(row) {
        let label = String(row.VersionLabel || row.VersionID || '');
        if (Number(row.IsDefault || 0) === 1) {
            label += ' (default)';
        }
        return label;
    }

    function populateVersionSelect(selectElement, noteElement, fiscalYearId, preferredVersionId) {
        if (!selectElement) {
            return;
        }

        const rows = Array.isArray(versionsByFiscalYear[String(fiscalYearId)]) ? versionsByFiscalYear[String(fiscalYearId)] : [];
        const previousValue = String(preferredVersionId || selectElement.value || '');
        selectElement.innerHTML = '';

        if (rows.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = <?= json_encode(__t('strategy_rollover_no_submission_versions'), JSON_THROW_ON_ERROR) ?>;
            selectElement.appendChild(option);
            selectElement.disabled = true;
            if (noteElement) {
                noteElement.textContent = <?= json_encode(__t('strategy_rollover_no_submission_versions'), JSON_THROW_ON_ERROR) ?>;
            }
            return;
        }

        rows.forEach(function (row, index) {
            const option = document.createElement('option');
            option.value = String(row.VersionID || '');
            option.textContent = buildVersionOptionLabel(row);
            if (option.value === previousValue || (previousValue === '' && index === 0)) {
                option.selected = true;
            }
            selectElement.appendChild(option);
        });

        selectElement.disabled = !rolloverAvailable;
        if (noteElement) {
            noteElement.textContent = '';
        }
    }

    function syncSubmitState(selectElement, buttonElement) {
        if (!buttonElement) {
            return;
        }

        const hasValue = !!(selectElement && String(selectElement.value || '').trim() !== '');
        buttonElement.disabled = !rolloverAvailable || !hasValue;
    }

    function addOneYear(dateValue) {
        if (!dateValue) {
            return '';
        }

        const date = new Date(dateValue + 'T00:00:00');
        if (Number.isNaN(date.getTime())) {
            return '';
        }

        date.setFullYear(date.getFullYear() + 1);
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + month + '-' + day;
    }

    function buildYearLabel(startDate, endDate, fallbackFiscalYear) {
        if (startDate && endDate) {
            return String(startDate).slice(0, 4) + '/' + String(endDate).slice(0, 4);
        }
        const year = Number(fallbackFiscalYear || 0);
        if (year > 0) {
            return year + '/' + (year + 1);
        }
        return '';
    }

    function refreshFiscalTargetDefaults(sourceFiscalYearId) {
        const sourceRow = fiscalYearMap[String(sourceFiscalYearId)] || null;
        const targetFiscalYearInput = document.getElementById('fiscal-rollover-target-fy');
        const targetYearLabelInput = document.getElementById('fiscal-rollover-target-year-label');
        const targetStartDateInput = document.getElementById('fiscal-rollover-target-start-date');
        const targetEndDateInput = document.getElementById('fiscal-rollover-target-end-date');

        if (!targetFiscalYearInput || !targetYearLabelInput || !targetStartDateInput || !targetEndDateInput) {
            return;
        }

        const nextFiscalYearId = Number(sourceFiscalYearId || 0) > 0 ? Number(sourceFiscalYearId) + 1 : Number(targetFiscalYearInput.value || 0);
        const nextStartDate = sourceRow ? addOneYear(String(sourceRow.StartDate || '')) : '';
        const nextEndDate = sourceRow ? addOneYear(String(sourceRow.EndDate || '')) : '';

        targetFiscalYearInput.value = String(nextFiscalYearId || '');
        targetStartDateInput.value = nextStartDate;
        targetEndDateInput.value = nextEndDate;
        targetYearLabelInput.value = buildYearLabel(nextStartDate, nextEndDate, nextFiscalYearId);
    }

    const versionSourceFy = document.getElementById('version-rollover-source-fy');
    const versionSourceVersion = document.getElementById('version-rollover-source-version');
    const versionSourceVersionNote = document.getElementById('version-rollover-source-version-note');
    const versionSubmitButton = document.getElementById('version-rollover-submit-btn');
    const fiscalSourceFy = document.getElementById('fiscal-rollover-source-fy');
    const fiscalSourceVersion = document.getElementById('fiscal-rollover-source-version');
    const fiscalSourceVersionNote = document.getElementById('fiscal-rollover-source-version-note');
    const fiscalSubmitButton = document.getElementById('fiscal-rollover-submit-btn');

    if (versionSourceFy && versionSourceVersion) {
        populateVersionSelect(versionSourceVersion, versionSourceVersionNote, versionSourceFy.value, versionDefaultVersionId);
        syncSubmitState(versionSourceVersion, versionSubmitButton);
        versionSourceFy.addEventListener('change', function () {
            populateVersionSelect(versionSourceVersion, versionSourceVersionNote, versionSourceFy.value, 0);
            syncSubmitState(versionSourceVersion, versionSubmitButton);
        });
        versionSourceVersion.addEventListener('change', function () {
            syncSubmitState(versionSourceVersion, versionSubmitButton);
        });
    }

    if (fiscalSourceFy && fiscalSourceVersion) {
        populateVersionSelect(fiscalSourceVersion, fiscalSourceVersionNote, fiscalSourceFy.value, fiscalDefaultVersionId);
        refreshFiscalTargetDefaults(fiscalSourceFy.value);
        syncSubmitState(fiscalSourceVersion, fiscalSubmitButton);
        fiscalSourceFy.addEventListener('change', function () {
            populateVersionSelect(fiscalSourceVersion, fiscalSourceVersionNote, fiscalSourceFy.value, 0);
            refreshFiscalTargetDefaults(fiscalSourceFy.value);
            syncSubmitState(fiscalSourceVersion, fiscalSubmitButton);
        });
        fiscalSourceVersion.addEventListener('change', function () {
            syncSubmitState(fiscalSourceVersion, fiscalSubmitButton);
        });
    }
});
</script>
