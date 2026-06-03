<?php
declare(strict_types=1);

/** @var array $filters */
/** @var array $rows */
/** @var array $summary */
/** @var array $options */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$compareScenarioCodes = is_array($filters['compare_scenarios'] ?? null)
    ? $filters['compare_scenarios']
    : [];

$scenarioNames = [];
foreach (($options['scenarios'] ?? []) as $scenario) {
    $scenarioNames[(string) ($scenario['ValueCode'] ?? '')] = (string) (($scenario['ValueLabel'] ?? '') !== '' ? $scenario['ValueLabel'] : ($scenario['ValueCode'] ?? ''));
}

$compareMode = strtolower((string) ($filters['compare_mode'] ?? 'legacy_budget'));
$isLegacyBudgetMode = $compareMode !== 'scenario_base';
$baseColumnLabel = $isLegacyBudgetMode
    ? 'Budget Base'
    : ((string) ($filters['base_scenario'] ?? '') !== '' ? (string) ($filters['base_scenario'] ?? '') : 'Base');
?>
<div class="card shadow-sm mt-4">
  <div class="card-header">
    <strong><i class="bi bi-columns-gap me-2"></i>Scenario Compare</strong>
    <div class="small text-muted">Compare published scenarios against either the original budget results or another published base scenario.</div>
  </div>

  <div class="card-body">
    <form method="get" action="index.php" class="row g-3 mb-4">
      <input type="hidden" name="route" value="scenario-results/compare">

      <div class="col-md-3">
        <label class="form-label">Compare Mode</label>
        <select name="compare_mode" class="form-select" id="compareModeSelect">
          <option value="legacy_budget"<?= $isLegacyBudgetMode ? ' selected' : '' ?>>Budget vs Scenarios</option>
          <option value="scenario_base"<?= !$isLegacyBudgetMode ? ' selected' : '' ?>>Scenario vs Scenario</option>
        </select>
        <div class="form-text">Use `Scenario vs Scenario` when you want to compare one scenario against another scenario.</div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Model</label>
        <select name="model" class="form-select" required>
          <option value="">Select model</option>
          <?php foreach (($options['models'] ?? []) as $option): ?>
            <?php $code = (string) ($option['ValueCode'] ?? ''); ?>
            <option value="<?= h($code) ?>"<?= (($filters['model'] ?? '') === $code) ? ' selected' : '' ?>>
              <?= h($code) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3" id="baseScenarioField"<?= $isLegacyBudgetMode ? ' style="display:none;"' : '' ?>>
        <label class="form-label">Base Scenario</label>
        <select name="base_scenario" class="form-select" id="baseScenarioSelect"<?= $isLegacyBudgetMode ? '' : ' required' ?>>
          <option value="">Select base scenario</option>
          <?php foreach (($options['scenarios'] ?? []) as $option): ?>
            <?php $code = (string) ($option['ValueCode'] ?? ''); ?>
            <?php $label = (string) (($option['ValueLabel'] ?? '') !== '' ? $option['ValueLabel'] : $code); ?>
            <option value="<?= h($code) ?>"<?= (($filters['base_scenario'] ?? '') === $code) ? ' selected' : '' ?>>
              <?= h($code . ($label !== $code ? ' - ' . $label : '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3" id="budgetBaseField"<?= $isLegacyBudgetMode ? '' : ' style="display:none;"' ?>>
        <label class="form-label">Base Source</label>
        <div class="form-control bg-light">Original Budget Transactions</div>
        <div class="form-text">Uses the latest calculated totals from the legacy budget transaction results.</div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Compare Scenarios</label>
        <select name="compare_scenarios[]" class="form-select" id="compareScenariosSelect" multiple size="6" required>
          <?php foreach (($options['scenarios'] ?? []) as $option): ?>
            <?php $code = (string) ($option['ValueCode'] ?? ''); ?>
            <?php $label = (string) (($option['ValueLabel'] ?? '') !== '' ? $option['ValueLabel'] : $code); ?>
            <option value="<?= h($code) ?>"<?= in_array($code, $compareScenarioCodes, true) ? ' selected' : '' ?>>
              <?= h($code . ($label !== $code ? ' - ' . $label : '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Hold Ctrl or Cmd to select more than one scenario.</div>
      </div>

      <div class="col-md-3">
        <label class="form-label">Cost Object</label>
        <select name="cost_object" class="form-select">
          <option value="">All cost objects</option>
          <?php foreach (($options['costObjects'] ?? []) as $option): ?>
            <?php $code = (string) ($option['ValueCode'] ?? ''); ?>
            <option value="<?= h($code) ?>"<?= (($filters['cost_object'] ?? '') === $code) ? ' selected' : '' ?>>
              <?= h($code) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Period</label>
        <select name="period" class="form-select">
          <option value="">All periods</option>
          <?php foreach (($options['periods'] ?? []) as $option): ?>
            <?php $code = (string) ($option['ValueCode'] ?? ''); ?>
            <option value="<?= h($code) ?>"<?= (($filters['period'] ?? '') === $code) ? ' selected' : '' ?>>
              <?= h($code) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Node</label>
        <select name="node" class="form-select">
          <option value="">All nodes</option>
          <?php foreach (($options['nodes'] ?? []) as $option): ?>
            <?php $code = (string) ($option['ValueCode'] ?? ''); ?>
            <option value="<?= h($code) ?>"<?= (($filters['node'] ?? '') === $code) ? ' selected' : '' ?>>
              <?= h($code) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label">Search</label>
        <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control" placeholder="Cost object, period, or node">
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-search me-1"></i>Compare
        </button>
        <a href="index.php?route=scenario-results/compare" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
        </a>
      </div>
    </form>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Compared Rows</div>
          <div class="fs-5 fw-semibold"><?= number_format((int) ($summary['RowCount'] ?? 0)) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Compared Scenarios</div>
          <div class="fs-5 fw-semibold"><?= number_format((int) ($summary['CompareScenarioCount'] ?? 0)) ?></div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="border rounded p-3 bg-light h-100">
          <div class="small text-muted">Rows With Differences</div>
          <div class="fs-5 fw-semibold"><?= number_format((int) ($summary['ChangedRowCount'] ?? 0)) ?></div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Cost Object</th>
            <th>Period</th>
            <th>Node</th>
            <th class="text-end"><?= h($baseColumnLabel) ?></th>
            <?php foreach ($compareScenarioCodes as $scenarioCode): ?>
              <th class="text-end"><?= h($scenarioCode) ?></th>
              <th class="text-end">Delta</th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
          <tr>
            <td colspan="<?= h((string) max(4, 4 + (count($compareScenarioCodes) * 2))) ?>" class="text-center text-muted py-4">
              <?= h($isLegacyBudgetMode
                  ? 'Choose a model and at least one scenario to compare back to the original budget.'
                  : 'Choose a model, a base scenario, and at least one comparison scenario to see differences.') ?>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h((string) ($row['CostObjectCode'] ?? '')) ?></div>
                <div class="small text-muted"><?= h((string) ($row['CostObjectName'] ?? '')) ?></div>
              </td>
              <td><?= h((string) ($row['PeriodCode'] ?? '')) ?></td>
              <td>
                <div class="fw-semibold"><?= h((string) ($row['NodeCode'] ?? '')) ?></div>
                <div class="small text-muted"><?= h((string) ($row['NodeName'] ?? '')) ?></div>
              </td>
              <td class="text-end fw-semibold">
                <?= h((string) (($row['base']['value']['display'] ?? ''))) ?>
              </td>
              <?php foreach ($compareScenarioCodes as $scenarioCode): ?>
                <?php $comparison = $row['comparisons'][$scenarioCode] ?? null; ?>
                <?php $delta = $comparison['delta'] ?? null; ?>
                <?php
                  $deltaClass = '';
                  if (($delta['amount'] ?? null) !== null) {
                      $deltaClass = ((float) $delta['amount'] > 0)
                          ? 'text-success'
                          : (((float) $delta['amount'] < 0) ? 'text-danger' : 'text-muted');
                  } elseif (($delta['has_difference'] ?? false) === true) {
                      $deltaClass = 'text-warning';
                  } else {
                      $deltaClass = 'text-muted';
                  }
                ?>
                <td class="text-end"><?= h((string) (($comparison['value']['display'] ?? ''))) ?></td>
                <td class="text-end <?= h($deltaClass) ?>"><?= h((string) (($delta['display'] ?? ''))) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
  (function () {
    const modeSelect = document.getElementById('compareModeSelect');
    const baseScenarioField = document.getElementById('baseScenarioField');
    const budgetBaseField = document.getElementById('budgetBaseField');
    const baseScenarioSelect = document.getElementById('baseScenarioSelect');
    const compareScenariosSelect = document.getElementById('compareScenariosSelect');
    if (!modeSelect || !baseScenarioField || !budgetBaseField || !baseScenarioSelect || !compareScenariosSelect) {
      return;
    }

    const syncMode = () => {
      const isLegacy = modeSelect.value !== 'scenario_base';
      baseScenarioField.style.display = isLegacy ? 'none' : '';
      budgetBaseField.style.display = isLegacy ? '' : 'none';
      baseScenarioSelect.required = !isLegacy;
      baseScenarioSelect.disabled = isLegacy;
      if (isLegacy) {
        baseScenarioSelect.setCustomValidity('');
      }
      compareScenariosSelect.required = true;
    };

    const validateCompareSelections = () => {
      const selectedCount = Array.from(compareScenariosSelect.options).filter((option) => option.selected).length;
      if (selectedCount > 0) {
        compareScenariosSelect.setCustomValidity('');
      } else {
        compareScenariosSelect.setCustomValidity('Select at least one scenario to compare.');
      }
    };

    modeSelect.addEventListener('change', syncMode);
    compareScenariosSelect.addEventListener('change', validateCompareSelections);
    syncMode();
    validateCompareSelections();
  }());
</script>
