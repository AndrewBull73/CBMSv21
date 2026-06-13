<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$fiscalYears = is_array($fiscalYears ?? null) ? $fiscalYears : [];
$versionTypes = is_array($versionTypes ?? null) ? $versionTypes : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : ['DRAFT', 'OPEN', 'ACTIVE', 'SUSPENDED', 'CLOSED'];
$baseVersions = is_array($baseVersions ?? null) ? $baseVersions : [];
$currencyOptions = is_array($currencyOptions ?? null) ? $currencyOptions : [];
$hasCurrenciesTable = !empty($hasCurrenciesTable);
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$isEditing = !empty($isEditing);
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$fiscalYearId = (int) ($record['FiscalYearID'] ?? 0);
$versionId = (int) ($record['VersionID'] ?? 0);
$selectedBaseFiscalYearId = (int) ($selectedBaseFiscalYearId ?? ($record['BaseFiscalYearID'] ?? 0));
$selectedBaseVersionId = (int) ($record['BaseVersionID'] ?? 0);
$selectedBaseCurrency = trim((string) ($record['BaseCurrency'] ?? ''));
$screenHeader = [
    'title' => $isEditing ? 'Edit Version' : 'Create Version',
    'icon' => 'bi-layers',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this form to create or refine one version record within a fiscal year. Confirm the fiscal year, version type, and default behavior first, then set any rollover lineage or optional execution metadata.
      </div>

      <form method="post" action="index.php?route=versions/save" id="versions-form">
        <?= csrf_field() ?>
        <input type="hidden" name="_editing" value="<?= $isEditing ? '1' : '0' ?>">
        <?php if ($isEditing): ?>
          <input type="hidden" name="OriginalFiscalYearID" value="<?= $fiscalYearId ?>">
          <input type="hidden" name="OriginalVersionID" value="<?= $versionId ?>">
        <?php endif; ?>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Identity</h5>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Fiscal Year</label>
                <select id="versionFiscalYearID" class="form-select" name="FiscalYearID" <?= $isEditing ? 'disabled' : 'required' ?>>
                  <option value="">Select fiscal year</option>
                  <?php foreach ($fiscalYears as $fy): ?>
                    <?php $fyId = (int) ($fy['FiscalYearID'] ?? 0); ?>
                    <option value="<?= $fyId ?>" <?= $fiscalYearId === $fyId ? 'selected' : '' ?>>
                      <?= h((string) $fyId . ' - ' . (string) ($fy['YearLabel'] ?? '')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($isEditing): ?>
                  <input type="hidden" name="FiscalYearID" value="<?= $fiscalYearId ?>">
                <?php endif; ?>
              </div>
              <div class="col-md-6">
                <label class="form-label">Version ID</label>
                <input id="versionVersionID" class="form-control" type="number" min="1" name="VersionID" value="<?= $versionId > 0 ? h((string) $versionId) : '' ?>" <?= $isEditing ? 'readonly' : '' ?>>
                <div class="form-text">Leave blank on create to use the next available version number for the selected fiscal year.</div>
              </div>
            </div>

            <div class="mb-0">
              <label class="form-label">Version Label</label>
              <input id="versionLabel" class="form-control" type="text" name="VersionLabel" value="<?= h((string) ($record['VersionLabel'] ?? '')) ?>" required>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Type And Status</h5>
          </div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Version Type</label>
                <select id="versionTypeID" class="form-select" name="VersionTypeID" required>
                  <option value="">Select version type</option>
                  <?php foreach ($versionTypes as $type): ?>
                    <?php $typeId = (int) ($type['VersionTypeID'] ?? 0); ?>
                    <option value="<?= $typeId ?>" <?= ((int) ($record['VersionTypeID'] ?? 0) === $typeId) ? 'selected' : '' ?>>
                      <?= h((string) ($type['VersionTypeName'] ?? '')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Version Status</label>
                <select id="versionStatus" class="form-select" name="VersionStatus" required>
                  <?php $currentStatus = strtoupper(trim((string) ($record['VersionStatus'] ?? 'DRAFT'))); ?>
                  <?php foreach ($statusOptions as $statusOption): ?>
                    <?php $statusValue = strtoupper(trim((string) $statusOption)); ?>
                    <option value="<?= h($statusValue) ?>" <?= $currentStatus === $statusValue ? 'selected' : '' ?>>
                      <?= h($statusValue) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Lineage And Execution Metadata</h5>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Base Fiscal Year</label>
                <select class="form-select" name="BaseFiscalYearID" id="baseFiscalYearSelect">
                  <option value="">Select base fiscal year</option>
                  <?php foreach ($fiscalYears as $fy): ?>
                    <?php $baseFyId = (int) ($fy['FiscalYearID'] ?? 0); ?>
                    <option value="<?= $baseFyId ?>" <?= $selectedBaseFiscalYearId === $baseFyId ? 'selected' : '' ?>>
                      <?= h((string) $baseFyId . ' - ' . (string) ($fy['YearLabel'] ?? '')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Base Version</label>
                <select class="form-select" name="BaseVersionID" id="baseVersionSelect">
                  <option value="">Select base version</option>
                  <?php foreach ($baseVersions as $baseVersion): ?>
                    <?php
                      $baseFyId = (int) ($baseVersion['FiscalYearID'] ?? 0);
                      $baseVerId = (int) ($baseVersion['VersionID'] ?? 0);
                      $baseTypeCode = trim((string) ($baseVersion['VersionTypeCode'] ?? ''));
                    ?>
                    <option
                      value="<?= $baseVerId ?>"
                      data-fiscal-year-id="<?= $baseFyId ?>"
                      <?= ($selectedBaseFiscalYearId === $baseFyId && $selectedBaseVersionId === $baseVerId) ? 'selected' : '' ?>
                    >
                      <?= h((string) $baseVerId . ' - ' . (string) ($baseVersion['VersionLabel'] ?? '')) ?><?= $baseTypeCode !== '' ? ' [' . h($baseTypeCode) . ']' : '' ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text">The list only shows versions from the fiscal year selected above.</div>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Actuals Period ID</label>
                <input class="form-control" type="number" min="1" name="ActualsPeriodID" value="<?= h((string) ($record['ActualsPeriodID'] ?? '')) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Base Currency</label>
                <?php if ($hasCurrenciesTable): ?>
                  <select class="form-select" name="BaseCurrency">
                    <option value="">Select currency</option>
                    <?php foreach ($currencyOptions as $currency): ?>
                      <?php $currencyCode = trim((string) ($currency['CurrencyCode'] ?? '')); ?>
                      <?php $currencyName = trim((string) ($currency['CurrencyName'] ?? $currencyCode)); ?>
                      <option value="<?= h($currencyCode) ?>" <?= $selectedBaseCurrency === $currencyCode ? 'selected' : '' ?>>
                        <?= h($currencyCode . ($currencyName !== '' && $currencyName !== $currencyCode ? ' - ' . $currencyName : '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <input class="form-control" type="text" name="BaseCurrency" value="<?= h($selectedBaseCurrency) ?>" maxlength="3">
                  <div class="form-text">This should come from <code>tblCurrencies</code>. The current environment does not have that table yet, so the form is using a temporary text fallback.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Flags</h5>
          </div>
          <div class="card-body">
            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="versionIsActive" name="IsActive" <?= ((int) ($record['IsActive'] ?? 1) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="versionIsActive">Active version</label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="versionIsDefault" name="IsDefault" <?= ((int) ($record['IsDefault'] ?? 0) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="versionIsDefault">Default version for this fiscal year and type</label>
            </div>

            <div class="form-check mb-2">
              <input class="form-check-input" type="checkbox" id="versionIsSystemDefault" name="IsSystemDefault" <?= ((int) ($record['IsSystemDefault'] ?? 0) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="versionIsSystemDefault">Set as system default fiscal context version</label>
            </div>

            <div class="form-check mb-0">
              <input class="form-check-input" type="checkbox" id="versionCeilingsOn" name="CeilingsOn" <?= ((int) ($record['CeilingsOn'] ?? 0) === 1) ? 'checked' : '' ?>>
              <label class="form-check-label" for="versionCeilingsOn">Ceilings enabled</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between">
          <a id="versions-back-btn" href="index.php?route=versions/list<?= $fiscalYearId > 0 ? '&fy=' . $fiscalYearId : '' ?>" class="btn btn-sm btn-outline-secondary">Back</a>
          <button id="versions-save-btn" type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fiscalYearSelect = document.getElementById('baseFiscalYearSelect');
    const versionSelect = document.getElementById('baseVersionSelect');
    if (!fiscalYearSelect || !versionSelect) {
        return;
    }

    const originalOptions = Array.from(versionSelect.querySelectorAll('option')).map((option) => ({
        value: option.value,
        label: option.textContent,
        fiscalYearId: option.getAttribute('data-fiscal-year-id') || '',
        selected: option.selected
    }));

    const placeholder = originalOptions.find((option) => option.value === '') || {
        value: '',
        label: 'Select base version',
        fiscalYearId: '',
        selected: false
    };

    const syncBaseVersions = function () {
        const selectedFiscalYearId = fiscalYearSelect.value;
        const currentValue = versionSelect.value;
        const matchingOptions = originalOptions.filter((option) => option.value === '' || option.fiscalYearId === selectedFiscalYearId);

        versionSelect.innerHTML = '';

        matchingOptions.forEach((option, index) => {
            const nextOption = document.createElement('option');
            nextOption.value = option.value;
            nextOption.textContent = option.label;
            if (option.fiscalYearId !== '') {
                nextOption.setAttribute('data-fiscal-year-id', option.fiscalYearId);
            }
            if (option.value !== '' && option.value === currentValue) {
                nextOption.selected = true;
            } else if (index === 0 && currentValue === '') {
                nextOption.selected = true;
            }
            versionSelect.appendChild(nextOption);
        });

        const hasCurrentValue = matchingOptions.some((option) => option.value !== '' && option.value === currentValue);
        if (!hasCurrentValue) {
            versionSelect.value = '';
        }
    };

    fiscalYearSelect.addEventListener('change', syncBaseVersions);
    syncBaseVersions();
});
</script>
