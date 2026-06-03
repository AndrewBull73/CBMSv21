<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

/** @var array $calcModel */
/** @var array $scenario */
/** @var array $costObjects */
/** @var int $selectedCostObjectId */
/** @var string $search */
/** @var array $periods */
/** @var array $nodes */
/** @var array $values */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$selectedCostObject = null;
foreach ($costObjects as $costObject) {
    if ((int) ($costObject['CostObjectID'] ?? 0) === $selectedCostObjectId) {
        $selectedCostObject = $costObject;
        break;
    }
}
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><i class="bi bi-sliders me-2"></i>Scenario Values</strong>
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
      <input type="hidden" name="route" value="scenario-admin/values">
      <input type="hidden" name="scenario_id" value="<?= (int) $scenario['ScenarioID'] ?>">

      <div class="col-md-4">
        <label class="form-label">Cost Object</label>
        <select name="cost_object_id" class="form-select" onchange="this.form.submit()">
          <?php foreach ($costObjects as $costObject): ?>
            <option value="<?= (int) $costObject['CostObjectID'] ?>"<?= ((int) ($costObject['CostObjectID'] ?? 0) === $selectedCostObjectId) ? ' selected' : '' ?>>
              <?= h((string) $costObject['CostObjectCode']) ?> - <?= h((string) $costObject['CostObjectName']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-5">
        <label class="form-label">Find Input Node</label>
        <input type="text" name="q" class="form-control" value="<?= h($search) ?>" placeholder="APS1, WageRate, Headcount, Rate...">
      </div>

      <div class="col-md-3 d-flex align-items-end gap-2">
        <button type="submit" class="btn btn-outline-primary">
          <i class="bi bi-search me-1"></i>Filter
        </button>
        <a href="index.php?route=scenario-admin/values&scenario_id=<?= (int) $scenario['ScenarioID'] ?>&cost_object_id=<?= (int) $selectedCostObjectId ?>" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
        </a>
      </div>
    </form>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Scenario</div>
          <div class="fw-semibold"><?= h((string) $scenario['ScenarioCode']) ?></div>
          <div><?= h((string) $scenario['ScenarioName']) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Selected Cost Object</div>
          <div class="fw-semibold"><?= h((string) ($selectedCostObject['CostObjectCode'] ?? '')) ?></div>
          <div><?= h((string) ($selectedCostObject['CostObjectName'] ?? '')) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">How This Works</div>
          <div>Enter a value to override.</div>
          <div>Clear a value to inherit from the parent/base scenario again.</div>
        </div>
      </div>
    </div>

    <form method="post" action="index.php?route=scenario-admin/saveValues">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="ScenarioID" value="<?= (int) $scenario['ScenarioID'] ?>">
      <input type="hidden" name="CostObjectID" value="<?= (int) $selectedCostObjectId ?>">
      <input type="hidden" name="q" value="<?= h($search) ?>">

      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th style="min-width: 260px;">Input Node</th>
              <?php foreach ($periods as $period): ?>
                <th style="min-width: 170px;">
                  <div class="fw-semibold"><?= h((string) $period['PeriodCode']) ?></div>
                  <div class="small text-muted"><?= h((string) $period['PeriodTypeCode']) ?></div>
                </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php if ($nodes === []): ?>
            <tr>
              <td colspan="<?= 1 + count($periods) ?>" class="text-center text-muted py-4">
                No editable input nodes were found for this model and filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($nodes as $node): ?>
              <?php $nodeId = (int) ($node['NodeID'] ?? 0); ?>
              <tr>
                <td class="bg-light">
                  <div class="fw-semibold"><?= h((string) $node['NodeCode']) ?></div>
                  <div><?= h((string) $node['NodeName']) ?></div>
                  <div class="small text-muted">
                    <?= h((string) $node['DataTypeCode']) ?>
                    <?php if (trim((string) ($node['UnitOfMeasureCode'] ?? '')) !== ''): ?>
                      / <?= h((string) $node['UnitOfMeasureCode']) ?>
                    <?php endif; ?>
                  </div>
                </td>
                <?php foreach ($periods as $period): ?>
                  <?php
                    $periodId = (int) ($period['PeriodID'] ?? 0);
                    $cell = $values[$nodeId][$periodId] ?? [
                        'current' => null,
                        'effective' => null,
                        'sourceScenarioCode' => null,
                        'sourceScenarioName' => null,
                        'isInherited' => false,
                    ];
                    $inputType = strtoupper((string) ($node['DataTypeCode'] ?? 'DECIMAL')) === 'TEXT' ? 'text' : 'number';
                    $step = strtoupper((string) ($node['DataTypeCode'] ?? 'DECIMAL')) === 'TEXT' ? null : 'any';
                  ?>
                  <td>
                    <input
                      type="<?= h($inputType) ?>"
                      name="values[<?= $nodeId ?>][<?= $periodId ?>]"
                      class="form-control form-control-sm"
                      value="<?= h((string) ($cell['current'] ?? '')) ?>"
                      <?= $step !== null ? 'step="' . h($step) . '"' : '' ?>
                    >
                    <div class="small mt-2">
                      <?php if (($cell['current'] ?? null) !== null): ?>
                        <div class="text-primary">Override: <?= h((string) $cell['current']) ?></div>
                      <?php elseif (($cell['effective'] ?? null) !== null): ?>
                        <div class="text-muted">
                          Inherited: <?= h((string) $cell['effective']) ?>
                          <?php if (trim((string) ($cell['sourceScenarioCode'] ?? '')) !== ''): ?>
                            from <?= h((string) $cell['sourceScenarioCode']) ?>
                          <?php endif; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-muted">No current value</div>
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
        <div class="small text-muted">This screen edits `tblScenarioNodeValue` overrides for the selected scenario and cost object.</div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Save Values
        </button>
      </div>
    </form>
  </div>
</div>
