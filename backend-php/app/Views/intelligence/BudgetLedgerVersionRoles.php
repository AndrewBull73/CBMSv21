<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';

$fiscalYears = array_values(is_array($fiscalYears ?? null) ? $fiscalYears : []);
$candidates = array_values(is_array($candidates ?? null) ? $candidates : []);
$mappings = array_values(is_array($mappings ?? null) ? $mappings : []);
$selectedFiscalYear = (int) ($selectedFiscalYear ?? 0);
$rolesInstalled = (bool) ($rolesInstalled ?? false);

$currentBaselineId = 0;
$currentActualsId = 0;
foreach ($mappings as $mapping) {
    if ((int) ($mapping['ActiveFlag'] ?? 0) !== 1) {
        continue;
    }
    if ((int) ($mapping['IsBudgetBaseline'] ?? 0) === 1) {
        $currentBaselineId = (int) ($mapping['BudgetVersionID'] ?? 0);
    }
    if ((int) ($mapping['IsExecutionActuals'] ?? 0) === 1) {
        $currentActualsId = (int) ($mapping['BudgetVersionID'] ?? 0);
    }
}

$money = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    return number_format((float) $value, 2);
};
?>
<div class="container-fluid mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-diagram-2 me-2"></i>Budget Ledger Version Roles</h3>
        <div class="small text-muted mt-1">Govern which budget ledger versions are used as baseline budget and execution actuals for AI/ML analysis.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/ml-models">ML Models</a>
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/index">Dashboard</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php elseif (!$rolesInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> to install the budget ledger version-role mapping objects.</div>
      <?php else: ?>
        <form method="get" action="index.php" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="route" value="intelligence/budget-ledger-version-roles">
          <div class="col-sm-4 col-md-3 col-xl-2">
            <label class="form-label" for="FiscalYearID">Fiscal Year</label>
            <select class="form-select" id="FiscalYearID" name="FiscalYearID" onchange="this.form.submit()">
              <?php foreach ($fiscalYears as $year): ?>
                <?php $fy = (int) ($year['FiscalYearID'] ?? 0); ?>
                <option value="<?= h((string) $fy) ?>" <?= $fy === $selectedFiscalYear ? 'selected' : '' ?>><?= h((string) $fy) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </form>

        <?php if ($candidates === []): ?>
          <div class="alert alert-info mb-0">No budget ledger version candidates were found for the selected fiscal year.</div>
        <?php else: ?>
          <form method="post" action="index.php?route=intelligence/save-budget-ledger-version-roles">
            <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
            <input type="hidden" name="FiscalYearID" value="<?= h((string) $selectedFiscalYear) ?>">

            <div class="alert alert-info py-2">
              Select one annual budget baseline and one execution actuals version. The ML risk view uses these roles, not raw version IDs.
            </div>

            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Version</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th class="text-end">Rows</th>
                    <th class="text-end">Budget Amount</th>
                    <th class="text-end">Actual Amount</th>
                    <th>Current Role</th>
                    <th class="text-center">Budget Baseline</th>
                    <th class="text-center">Execution Actuals</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($candidates as $row): ?>
                  <?php
                    $versionId = (int) ($row['BudgetVersionID'] ?? 0);
                    $isBaseline = $versionId === $currentBaselineId;
                    $isActuals = $versionId === $currentActualsId;
                    $versionTypeCode = trim((string) ($row['VersionTypeCode'] ?? ''));
                    $suggestedRole = trim((string) ($row['SuggestedRoleCode'] ?? ''));
                  ?>
                  <tr>
                    <td>
                      <div class="fw-semibold">Version <?= h((string) $versionId) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['VersionLabel'] ?? '')) ?></div>
                    </td>
                    <td>
                      <?= $versionTypeCode !== '' ? h($versionTypeCode) : '<span class="text-muted">Unlinked</span>' ?>
                      <?php if ($suggestedRole !== '' && $suggestedRole !== 'REVIEW_REQUIRED'): ?>
                        <div class="small text-muted"><?= h($suggestedRole) ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= h((string) ($row['VersionStatus'] ?? '')) ?>
                      <?php if ((int) ($row['IsDefault'] ?? 0) === 1): ?>
                        <span class="badge text-bg-primary ms-1">Default</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><?= h(number_format((float) ($row['TotalRows'] ?? 0), 0)) ?></td>
                    <td class="text-end"><?= h($money($row['BudgetAmount'] ?? null)) ?></td>
                    <td class="text-end"><?= h($money($row['ActualAmount'] ?? null)) ?></td>
                    <td>
                      <?php if ($isBaseline): ?><span class="badge text-bg-success">Budget Baseline</span><?php endif; ?>
                      <?php if ($isActuals): ?><span class="badge text-bg-warning text-dark">Execution Actuals</span><?php endif; ?>
                      <?php if (!$isBaseline && !$isActuals): ?><span class="text-muted small">None</span><?php endif; ?>
                    </td>
                    <td class="text-center">
                      <input class="form-check-input" type="radio" name="BudgetBaselineVersionID" value="<?= h((string) $versionId) ?>" <?= $isBaseline ? 'checked' : '' ?> required>
                    </td>
                    <td class="text-center">
                      <input class="form-check-input" type="radio" name="ExecutionActualsVersionID" value="<?= h((string) $versionId) ?>" <?= $isActuals ? 'checked' : '' ?> required>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
              <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Version Roles</button>
            </div>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
