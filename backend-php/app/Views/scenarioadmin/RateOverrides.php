<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

/** @var array $calcModel */
/** @var array $scenario */
/** @var array $filters */
/** @var array $contexts */
/** @var array $rows */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rateColumns = [
    'BP1Rate', 'BP2Rate', 'BP3Rate', 'BP4Rate', 'BP5Rate', 'BP6Rate',
    'BP7Rate', 'BP8Rate', 'BP9Rate', 'BP10Rate', 'BP11Rate', 'BP12Rate',
];
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><i class="bi bi-currency-dollar me-2"></i>Scenario Rate Overrides</strong>
      <div class="small text-muted">
        <?= h((string) $calcModel['ModelCode']) ?> /
        <?= h((string) $scenario['ScenarioCode']) ?> -
        <?= h((string) $scenario['ScenarioName']) ?>
      </div>
    </div>
    <div class="btn-group btn-group-sm">
      <a href="index.php?route=scenario-admin/detail&id=<?= (int) $calcModel['CalcModelID'] ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
      <a href="index.php?route=scenario-admin/scenario&id=<?= (int) $scenario['ScenarioID'] ?>" class="btn btn-outline-primary">
        <i class="bi bi-pencil-square me-1"></i>Edit Scenario
      </a>
    </div>
  </div>

  <div class="card-body">
    <form method="get" action="index.php" class="row g-3 mb-4">
      <input type="hidden" name="route" value="scenario-admin/rate-overrides">
      <input type="hidden" name="scenario_id" value="<?= (int) $scenario['ScenarioID'] ?>">

      <div class="col-md-4">
        <label class="form-label">Rate Code</label>
        <input type="text" name="rate_code" class="form-control" value="<?= h((string) ($filters['rate_code'] ?? '')) ?>" placeholder="APS1">
      </div>

      <div class="col-md-4">
        <label class="form-label">DataObjectCode</label>
        <input type="text" name="data_object_code" class="form-control" value="<?= h((string) ($filters['data_object_code'] ?? '')) ?>" placeholder="302 or 0">
      </div>

      <div class="col-md-4 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-outline-primary">
          <i class="bi bi-search me-1"></i>Filter
        </button>
        <a href="index.php?route=scenario-admin/rate-overrides&scenario_id=<?= (int) $scenario['ScenarioID'] ?>" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
        </a>
      </div>
    </form>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">How It Works</div>
          <div>Override only the rate row you want, such as `APS1`.</div>
          <div>Anything left blank continues to come from `tblRates`.</div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Scenario</div>
          <div class="fw-semibold"><?= h((string) $scenario['ScenarioCode']) ?></div>
          <div><?= h((string) $scenario['ScenarioName']) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Resolved Rate Rows</div>
          <div class="fs-5 fw-semibold"><?= number_format(count($rows)) ?></div>
        </div>
      </div>
    </div>

    <form method="post" action="index.php?route=scenario-admin/saveRateOverrides">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="ScenarioID" value="<?= (int) $scenario['ScenarioID'] ?>">
      <input type="hidden" name="rate_code" value="<?= h((string) ($filters['rate_code'] ?? '')) ?>">
      <input type="hidden" name="data_object_code" value="<?= h((string) ($filters['data_object_code'] ?? '')) ?>">

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="min-width: 180px;">Context</th>
              <?php foreach ($rateColumns as $column): ?>
                <th style="min-width: 170px;"><?= h($column) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php if ($rows === []): ?>
            <tr>
              <td colspan="<?= 1 + count($rateColumns) ?>" class="text-center text-muted py-4">
                No rate rows matched the current filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $index => $row): ?>
              <tr>
                <td class="bg-light">
                  <input type="hidden" name="rows[<?= $index ?>][FiscalYearID]" value="<?= (int) $row['FiscalYearID'] ?>">
                  <input type="hidden" name="rows[<?= $index ?>][VersionID]" value="<?= (int) $row['VersionID'] ?>">
                  <input type="hidden" name="rows[<?= $index ?>][DataObjectCode]" value="<?= h((string) $row['DataObjectCode']) ?>">
                  <input type="hidden" name="rows[<?= $index ?>][RateCode]" value="<?= h((string) $row['RateCode']) ?>">
                  <div class="fw-semibold"><?= h((string) $row['RateCode']) ?></div>
                  <div><?= h((string) $row['DataObjectCode']) ?></div>
                  <div class="small text-muted">FY <?= h((string) $row['FiscalYearID']) ?> / V<?= h((string) $row['VersionID']) ?></div>
                  <?php if (($row['isInherited'] ?? false) === true && trim((string) ($row['sourceScenarioCode'] ?? '')) !== ''): ?>
                    <div class="small text-muted mt-2">Inherited from <?= h((string) $row['sourceScenarioCode']) ?></div>
                  <?php elseif (trim((string) ($row['sourceScenarioCode'] ?? '')) !== ''): ?>
                    <div class="small text-primary mt-2">Overridden in <?= h((string) $row['sourceScenarioCode']) ?></div>
                  <?php endif; ?>
                </td>
                <?php foreach ($rateColumns as $column): ?>
                  <td>
                    <input
                      type="number"
                      step="any"
                      class="form-control form-control-sm"
                      name="rows[<?= $index ?>][<?= h($column) ?>]"
                      value="<?= h((string) ($row['current'][$column] ?? '')) ?>"
                    >
                    <div class="small mt-2">
                      <div class="text-muted">Base: <?= h((string) (($row['base'][$column] ?? null) ?? '')) ?></div>
                      <?php if (($row['current'][$column] ?? null) !== null): ?>
                        <div class="text-primary">Override: <?= h((string) $row['current'][$column]) ?></div>
                      <?php elseif (($row['effective'][$column] ?? null) !== null && ($row['effective'][$column] ?? null) !== ($row['base'][$column] ?? null)): ?>
                        <div class="text-muted">Inherited: <?= h((string) $row['effective'][$column]) ?></div>
                      <?php endif; ?>
                    </div>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-4">
        <div class="small text-muted">Clear a cell to remove the scenario override for that month and inherit the normal rate again.</div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Save Rate Overrides
        </button>
      </div>
    </form>
  </div>
</div>
