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
$records = is_array($records ?? null) ? $records : [];
$context = is_array($context ?? null) ? $context : [];
$assumptionDefinitions = is_array($assumptionDefinitions ?? null) ? $assumptionDefinitions : [];
$id = (int) ($record['FiscalAssumptionID'] ?? 0);
$yearLabel = (string) ($context['YearLabel'] ?? '');
$versionLabel = (string) ($context['VersionLabel'] ?? '');
$activeCount = 0;
$codeCount = [];
foreach ($records as $row) {
    if ((int) ($row['ActiveFlag'] ?? 0) === 1) {
        $activeCount++;
    }
    $code = trim((string) ($row['AssumptionCode'] ?? ''));
    if ($code !== '') {
        $codeCount[$code] = true;
    }
}

$screenHeader = [
    'title' => 'Fiscal Assumptions',
    'icon' => 'bi-calculator',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Assumptions in register</div>
              <div class="fs-4 fw-semibold"><?= count($records) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active assumptions</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Definition codes</div>
              <div class="fs-4 fw-semibold"><?= count($codeCount) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Setup status</div>
              <div class="fs-4 fw-semibold"><?= empty($fiscalAssumptionAvailable) ? 'Pending setup' : 'Ready' ?></div>
            </div>
          </div>
        </div>
      </div>

      <?php if (empty($fiscalAssumptionAvailable)): ?>
        <div class="alert alert-warning">Run <code>create_tblSbFiscalAssumption.sql</code> before maintaining fiscal assumptions.</div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use fiscal assumptions to drive MTFF helpers such as outer-year inflation projection. The resource envelope projection helper will use the active <code>INFLATION_RATE</code> assumption when available.
      </div>

      <div class="row g-4">
        <div class="col-xl-7">
          <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <h5 class="mb-0">Assumption Register</h5>
              <a href="index.php?route=strategy-config/fiscal-assumptions" class="btn btn-sm btn-primary">New Assumption</a>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Code</th>
                      <th>Name</th>
                      <th class="text-end">Value</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($records === []): ?>
                      <tr><td colspan="5" class="text-center text-muted py-3">No fiscal assumptions have been configured yet.</td></tr>
                    <?php else: ?>
                      <?php foreach ($records as $row): ?>
                        <tr<?= ((int) ($row['FiscalAssumptionID'] ?? 0) === $id && $id > 0) ? ' class="table-primary"' : '' ?>>
                          <td><?= h((string) ($row['AssumptionCode'] ?? '')) ?></td>
                          <td>
                            <span class="fw-semibold"><?= h((string) ($row['AssumptionName'] ?? '')) ?></span>
                            <?php if (!empty($row['AssumptionNotes'])): ?>
                              <span class="small text-muted ms-2"><?= h((string) $row['AssumptionNotes']) ?></span>
                            <?php endif; ?>
                          </td>
                          <td class="text-end"><?= number_format((float) ($row['AssumptionValue'] ?? 0), 4) ?></td>
                          <td>
                            <span class="badge <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'bg-success' : 'bg-secondary' ?>">
                              <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                            </span>
                          </td>
                          <td class="text-end">
                            <div class="d-inline-flex gap-1">
                              <a href="index.php?route=strategy-config/fiscal-assumptions&id=<?= (int) ($row['FiscalAssumptionID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
                              <form method="post" action="index.php?route=strategy-config/delete-fiscal-assumption" onsubmit="return confirm('Archive this fiscal assumption?');">
                                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= (int) ($row['FiscalAssumptionID'] ?? 0) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                              </form>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-5">
          <div class="card shadow-sm">
            <div class="card-header">
              <h5 class="mb-0"><?= $id > 0 ? 'Edit Fiscal Assumption' : 'Add Fiscal Assumption' ?></h5>
            </div>
            <div class="card-body">
              <div class="small text-muted mb-3">Maintain one fiscal assumption value for the active context. These assumptions support MTFF helpers and projection tools.</div>

              <form method="post" action="index.php?route=strategy-config/save-fiscal-assumption">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="FiscalAssumptionID" value="<?= $id ?>">

                <div class="row g-3 mb-3">
                  <div class="col-md-6">
                    <label class="form-label">Assumption Code</label>
                    <select name="AssumptionCode" id="AssumptionCode" class="form-select form-select-sm" required>
                      <?php foreach ($assumptionDefinitions as $definition): ?>
                        <option value="<?= h((string) ($definition['code'] ?? '')) ?>" data-name="<?= h((string) ($definition['name'] ?? '')) ?>" <?= ((string) ($record['AssumptionCode'] ?? 'INFLATION_RATE') === (string) ($definition['code'] ?? '')) ? 'selected' : '' ?>>
                          <?= h((string) ($definition['code'] ?? '')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Assumption Name</label>
                    <input type="text" name="AssumptionName" id="AssumptionName" class="form-control form-control-sm" required value="<?= h((string) ($record['AssumptionName'] ?? 'Inflation Rate')) ?>">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Assumption Value</label>
                    <input type="number" step="0.0001" name="AssumptionValue" class="form-control form-control-sm text-end" required value="<?= h((string) ($record['AssumptionValue'] ?? '0')) ?>">
                    <div class="form-text">Use decimal format for rates, for example <code>0.0500</code> for 5% inflation.</div>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Notes</label>
                    <input type="text" name="AssumptionNotes" class="form-control form-control-sm" value="<?= h((string) ($record['AssumptionNotes'] ?? '')) ?>">
                  </div>
                </div>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="ActiveFlag" id="fiscalAssumptionActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="fiscalAssumptionActiveFlag">Active</label>
                </div>

                <div class="d-flex justify-content-end">
                  <button type="submit" class="btn btn-sm btn-primary">Save Fiscal Assumption</button>
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
    const codeInput = document.getElementById('AssumptionCode');
    const nameInput = document.getElementById('AssumptionName');
    if (!codeInput || !nameInput) {
        return;
    }
    codeInput.addEventListener('change', function () {
        const selected = codeInput.options[codeInput.selectedIndex] || null;
        if (!selected) {
            return;
        }
        if (nameInput.value.trim() === '' || nameInput.value === nameInput.defaultValue) {
            nameInput.value = selected.getAttribute('data-name') || '';
        }
    });
});
</script>
