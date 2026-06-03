<?php
declare(strict_types=1);

/** @var array $models */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$models = is_array($models ?? null) ? $models : [];
$activeModels = 0;
$scenarioCount = 0;
$nodeCount = 0;
$formulaCount = 0;
foreach ($models as $model) {
    if ((int) ($model['ActiveFlag'] ?? 0) === 1) {
        $activeModels++;
    }
    $scenarioCount += (int) ($model['ScenarioCount'] ?? 0);
    $nodeCount += (int) ($model['NodeCount'] ?? 0);
    $formulaCount += (int) ($model['FormulaCount'] ?? 0);
}

$screenHeader = [
    'title' => 'Scenario Configuration',
    'icon' => 'bi-sliders2-vertical',
];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this register as the main entry point for model maintenance, published results, and deeper scenario design work. Keep an eye on model activity and object counts before running or publishing scenarios.
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Models</div>
              <div class="fs-4 fw-semibold"><?= count($models) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active models</div>
              <div class="fs-4 fw-semibold"><?= $activeModels ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Scenarios</div>
              <div class="fs-4 fw-semibold"><?= number_format($scenarioCount) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Nodes / formulas</div>
              <div class="fs-4 fw-semibold"><?= number_format($nodeCount) ?> / <?= number_format($formulaCount) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Scenario Models</h5>
          <a href="index.php?route=scenario-admin/model" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create Model
          </a>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-striped table-hover table-admin table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Model</th>
                  <th>Status</th>
                  <th class="text-end">Scenarios</th>
                  <th class="text-end">Periods</th>
                  <th class="text-end">Cost objects</th>
                  <th class="text-end">Nodes</th>
                  <th class="text-end">Formulas</th>
                  <th class="text-end">Dependencies</th>
                  <th class="text-end">Values</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($models === []): ?>
                <tr>
                  <td colspan="10" class="text-center text-muted py-4">No scenario calculation models have been configured yet.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($models as $model): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) $model['ModelCode']) ?></div>
                      <div class="small text-muted"><?= h((string) $model['ModelName']) ?></div>
                      <div class="small text-muted">Version <?= h((string) $model['ModelVersion']) ?></div>
                    </td>
                    <td>
                      <span class="badge text-bg-light"><?= h((string) $model['StatusCode']) ?></span>
                      <?php if ((int) ($model['ActiveFlag'] ?? 0) === 1): ?>
                        <span class="badge bg-success">Active</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><?= number_format((int) ($model['ScenarioCount'] ?? 0)) ?></td>
                    <td class="text-end"><?= number_format((int) ($model['PeriodCount'] ?? 0)) ?></td>
                    <td class="text-end"><?= number_format((int) ($model['CostObjectCount'] ?? 0)) ?></td>
                    <td class="text-end"><?= number_format((int) ($model['NodeCount'] ?? 0)) ?></td>
                    <td class="text-end"><?= number_format((int) ($model['FormulaCount'] ?? 0)) ?></td>
                    <td class="text-end"><?= number_format((int) ($model['DependencyCount'] ?? 0)) ?></td>
                    <td class="text-end"><?= number_format((int) ($model['ValueCount'] ?? 0)) ?></td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <a href="index.php?route=scenario-admin/detail&id=<?= (int) $model['CalcModelID'] ?>" class="btn btn-outline-primary" title="Open">
                          <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <a href="index.php?route=scenario-admin/model&id=<?= (int) $model['CalcModelID'] ?>" class="btn btn-outline-secondary" title="Edit model">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                        <a href="index.php?route=scenario-results/index&model=<?= urlencode((string) $model['ModelCode']) ?>" class="btn btn-outline-success" title="Published results">
                          <i class="bi bi-bar-chart"></i>
                        </a>
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
  </div>
</div>
