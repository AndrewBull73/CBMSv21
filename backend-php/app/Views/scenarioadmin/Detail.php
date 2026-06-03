<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

/** @var array $calcModel */
/** @var array $counts */
/** @var array $scenarios */
/** @var array $nodes */
/** @var array $dependencies */
/** @var array $recentRuns */
/** @var array $recentPublishes */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('short_formula')) {
    function short_formula(?string $expression): string
    {
        $expression = trim((string) $expression);
        if ($expression === '') {
            return '';
        }
        return mb_strlen($expression) > 80 ? mb_substr($expression, 0, 77) . '...' : $expression;
    }
}

$nodeTypeBuckets = [];
foreach ($nodes as $node) {
    $typeCode = trim((string) ($node['NodeTypeCode'] ?? 'OTHER'));
    if ($typeCode === '') {
        $typeCode = 'OTHER';
    }
    $nodeTypeBuckets[$typeCode] ??= [];
    $nodeTypeBuckets[$typeCode][] = $node;
}

$preferredNodeTypeOrder = ['INPUT', 'FORMULA', 'RESULT', 'REVENUE', 'SUMMARY', 'OTHER'];
$orderedNodeTypes = [];
foreach ($preferredNodeTypeOrder as $typeCode) {
    if (isset($nodeTypeBuckets[$typeCode])) {
        $orderedNodeTypes[] = $typeCode;
    }
}
foreach (array_keys($nodeTypeBuckets) as $typeCode) {
    if (!in_array($typeCode, $orderedNodeTypes, true)) {
        $orderedNodeTypes[] = $typeCode;
    }
}

$latestRunByScenario = [];
foreach (($recentRuns ?? []) as $run) {
    $scenarioId = (int) ($run['ScenarioID'] ?? 0);
    if ($scenarioId <= 0 || isset($latestRunByScenario[$scenarioId])) {
        continue;
    }
    $latestRunByScenario[$scenarioId] = $run;
}

$latestPublishByScenario = [];
foreach (($recentPublishes ?? []) as $publish) {
    $scenarioId = (int) ($publish['ScenarioID'] ?? 0);
    if ($scenarioId <= 0 || isset($latestPublishByScenario[$scenarioId])) {
        continue;
    }
    $latestPublishByScenario[$scenarioId] = $publish;
}
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><i class="bi bi-diagram-3 me-2"></i><?= h((string) $calcModel['ModelCode']) ?></strong>
      <div class="small text-muted"><?= h((string) $calcModel['ModelName']) ?>, version <?= h((string) $calcModel['ModelVersion']) ?></div>
    </div>
    <div class="btn-group btn-group-sm">
      <a href="index.php?route=scenario-admin/index" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </a>
      <a href="index.php?route=scenario-admin/model&id=<?= (int) $calcModel['CalcModelID'] ?>" class="btn btn-outline-primary">
        <i class="bi bi-pencil-square me-1"></i>Edit Model
      </a>
      <a href="index.php?route=scenario-admin/scenario&model_id=<?= (int) $calcModel['CalcModelID'] ?>" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Scenario
      </a>
      <form method="post" action="index.php?route=scenario-admin/resetModelScenarios" class="d-inline" onsubmit="return confirm('Reset this model back to one clean BASE scenario? This will delete scenario runs, publishes, overrides, values, and extra scenario definitions for this model.');">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="calc_model_id" value="<?= (int) $calcModel['CalcModelID'] ?>">
        <button type="submit" class="btn btn-outline-danger">
          <i class="bi bi-arrow-counterclockwise me-1"></i>Reset Scenarios
        </button>
      </form>
      <form method="post" action="index.php?route=scenario-admin/syncLegacyFormulas" class="d-inline" onsubmit="return confirm('Sync this model\\'s scenario formulas from its linked legacy calculation formulas?');">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="calc_model_id" value="<?= (int) $calcModel['CalcModelID'] ?>">
        <button type="submit" class="btn btn-outline-dark">
          <i class="bi bi-arrow-repeat me-1"></i>Sync Legacy Formulas
        </button>
      </form>
      <form method="post" action="index.php?route=scenario-admin/syncLegacyChain" class="d-inline" onsubmit="return confirm('Sync all linked scenario models in this legacy calculation chain from tblCalculationFormulas?');">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="calc_model_id" value="<?= (int) $calcModel['CalcModelID'] ?>">
        <button type="submit" class="btn btn-dark">
          <i class="bi bi-diagram-3 me-1"></i>Sync Legacy Chain
        </button>
      </form>
      <a href="index.php?route=scenario-admin/node&model_id=<?= (int) $calcModel['CalcModelID'] ?>" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i>Add Node
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Scenarios</div><div class="fs-5 fw-semibold"><?= number_format((int) ($counts['ScenarioCount'] ?? 0)) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Periods</div><div class="fs-5 fw-semibold"><?= number_format((int) ($counts['PeriodCount'] ?? 0)) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Cost objects</div><div class="fs-5 fw-semibold"><?= number_format((int) ($counts['CostObjectCount'] ?? 0)) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Nodes</div><div class="fs-5 fw-semibold"><?= number_format((int) ($counts['NodeCount'] ?? 0)) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Formulas</div><div class="fs-5 fw-semibold"><?= number_format((int) ($counts['FormulaCount'] ?? 0)) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Dependencies</div><div class="fs-5 fw-semibold"><?= number_format((int) ($counts['DependencyCount'] ?? 0)) ?></div></div></div>
    </div>

    <ul class="nav nav-tabs" id="scenarioModelTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="scenarios-tab" data-bs-toggle="tab" data-bs-target="#scenarios-pane" type="button" role="tab" aria-controls="scenarios-pane" aria-selected="true">
          Scenarios
          <span class="badge text-bg-light ms-1"><?= number_format((int) ($counts['ScenarioCount'] ?? 0)) ?></span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="nodes-tab" data-bs-toggle="tab" data-bs-target="#nodes-pane" type="button" role="tab" aria-controls="nodes-pane" aria-selected="false">
          Nodes and Formulas
          <span class="badge text-bg-light ms-1"><?= number_format((int) ($counts['NodeCount'] ?? 0)) ?></span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="dependencies-tab" data-bs-toggle="tab" data-bs-target="#dependencies-pane" type="button" role="tab" aria-controls="dependencies-pane" aria-selected="false">
          Dependencies
          <span class="badge text-bg-light ms-1"><?= number_format((int) ($counts['DependencyCount'] ?? 0)) ?></span>
        </button>
      </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-4 bg-white" id="scenarioModelTabContent">
      <div class="tab-pane fade show active" id="scenarios-pane" role="tabpanel" aria-labelledby="scenarios-tab" tabindex="0">
        <h5 class="mb-3">Scenarios</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Type</th>
                <th>Parent</th>
                <th class="text-end">Overrides</th>
                <th>Latest Run</th>
                <th>Latest Publish</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($scenarios === []): ?>
              <tr><td colspan="9" class="text-center text-muted py-3">No scenarios configured for this model.</td></tr>
            <?php else: ?>
              <?php foreach ($scenarios as $scenario): ?>
                <?php
                  $scenarioId = (int) ($scenario['ScenarioID'] ?? 0);
                  $latestRun = $latestRunByScenario[$scenarioId] ?? null;
                  $latestPublish = $latestPublishByScenario[$scenarioId] ?? null;
                ?>
                <tr>
                  <td class="fw-semibold"><?= h((string) $scenario['ScenarioCode']) ?></td>
                  <td><?= h((string) $scenario['ScenarioName']) ?></td>
                  <td><span class="badge text-bg-light"><?= h((string) $scenario['ScenarioTypeCode']) ?></span></td>
                  <td><?= h((string) ($scenario['ParentScenarioCode'] ?? '')) ?></td>
                  <td class="text-end"><?= number_format((int) ($scenario['ValueCount'] ?? 0)) ?></td>
                  <td>
                    <?php if ($latestRun === null): ?>
                      <span class="text-muted small">No runs yet</span>
                    <?php else: ?>
                      <div><span class="badge text-bg-light"><?= h((string) ($latestRun['RunStatusCode'] ?? '')) ?></span></div>
                      <div class="small text-muted">Run <?= h((string) ($latestRun['CalcRunID'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($latestRun['StartedDate'] ?? '')) ?></div>
                      <div class="mt-1">
                        <a href="index.php?route=scenario-admin/run-detail&run_id=<?= (int) ($latestRun['CalcRunID'] ?? 0) ?>" class="btn btn-sm btn-outline-dark">
                          View Run
                        </a>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($latestPublish === null): ?>
                      <span class="text-muted small">Not published</span>
                    <?php else: ?>
                      <div><span class="badge text-bg-light"><?= h((string) ($latestPublish['PublishStatusCode'] ?? '')) ?></span></div>
                      <div class="small text-muted">Event <?= h((string) ($latestPublish['CalcPublishEventID'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($latestPublish['PublishedDate'] ?? '')) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge text-bg-light"><?= h((string) $scenario['ScenarioStatusCode']) ?></span>
                    <?php if ((int) ($scenario['ActiveFlag'] ?? 0) === 1): ?><span class="badge bg-success">Active</span><?php endif; ?>
                    <?php if ((int) ($scenario['LockedFlag'] ?? 0) === 1): ?><span class="badge bg-warning text-dark">Locked</span><?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="d-flex justify-content-end gap-1 flex-wrap">
                      <form method="post" action="index.php?route=scenario-admin/runScenario" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="scenario_id" value="<?= $scenarioId ?>">
                        <button type="submit" class="btn btn-sm btn-dark" title="Run Scenario">
                          <i class="bi bi-play-fill"></i>
                        </button>
                      </form>
                      <form method="post" action="index.php?route=scenario-admin/publishScenario" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="scenario_id" value="<?= $scenarioId ?>">
                        <button type="submit" class="btn btn-sm btn-warning" title="Publish Scenario">
                          <i class="bi bi-upload"></i>
                        </button>
                      </form>
                      <?php if ((int) ($scenario['ParentScenarioID'] ?? 0) > 0): ?>
                        <form method="post" action="index.php?route=scenario-admin/resetScenario" class="d-inline" onsubmit="return confirm('Clear this scenario\\'s overrides and inherit the parent/base configuration again?');">
                          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                          <input type="hidden" name="scenario_id" value="<?= $scenarioId ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger" title="Reset To Parent/Base">
                            <i class="bi bi-arrow-counterclockwise"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                      <a href="index.php?route=scenario-admin/rate-overrides&scenario_id=<?= (int) $scenario['ScenarioID'] ?>" class="btn btn-outline-success" title="Edit Scenario Rate Overrides">
                        <i class="bi bi-currency-dollar"></i>
                      </a>
                      <a href="index.php?route=scenario-admin/values&scenario_id=<?= (int) $scenario['ScenarioID'] ?>" class="btn btn-outline-primary" title="Edit Scenario Values">
                        <i class="bi bi-sliders"></i>
                      </a>
                      <a href="index.php?route=scenario-admin/scenario&id=<?= (int) $scenario['ScenarioID'] ?>" class="btn btn-outline-secondary" title="Edit Scenario">
                        <i class="bi bi-pencil-square"></i>
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

      <div class="tab-pane fade" id="nodes-pane" role="tabpanel" aria-labelledby="nodes-tab" tabindex="0">
        <h5 class="mb-3">Nodes and Formulas</h5>
        <?php if ($nodes === []): ?>
          <div class="text-center text-muted py-3">No nodes configured for this model.</div>
        <?php else: ?>
          <ul class="nav nav-pills mb-3" id="nodeTypeTabs" role="tablist">
            <?php foreach ($orderedNodeTypes as $index => $typeCode): ?>
              <li class="nav-item" role="presentation">
                <button class="nav-link<?= $index === 0 ? ' active' : '' ?>"
                        id="node-type-<?= h(strtolower($typeCode)) ?>-tab"
                        data-bs-toggle="tab"
                        data-bs-target="#node-type-<?= h(strtolower($typeCode)) ?>-pane"
                        type="button"
                        role="tab"
                        aria-controls="node-type-<?= h(strtolower($typeCode)) ?>-pane"
                        aria-selected="<?= $index === 0 ? 'true' : 'false' ?>">
                  <?= h($typeCode) ?>
                  <span class="badge text-bg-light ms-1"><?= number_format(count($nodeTypeBuckets[$typeCode] ?? [])) ?></span>
                </button>
              </li>
            <?php endforeach; ?>
          </ul>

          <div class="tab-content" id="nodeTypeTabContent">
            <?php foreach ($orderedNodeTypes as $index => $typeCode): ?>
              <div class="tab-pane fade<?= $index === 0 ? ' show active' : '' ?>"
                   id="node-type-<?= h(strtolower($typeCode)) ?>-pane"
                   role="tabpanel"
                   aria-labelledby="node-type-<?= h(strtolower($typeCode)) ?>-tab"
                   tabindex="0">
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Node</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Data type</th>
                        <th>Formula</th>
                        <th class="text-end">Dependencies</th>
                        <th class="text-end">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($nodeTypeBuckets[$typeCode] ?? []) as $node): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h((string) $node['NodeCode']) ?></div>
                          <div class="small text-muted"><?= h((string) $node['NodeName']) ?></div>
                        </td>
                        <td><span class="badge text-bg-light"><?= h((string) $node['NodeTypeCode']) ?></span></td>
                        <td><?= h((string) $node['NodeCategoryCode']) ?></td>
                        <td><?= h((string) $node['DataTypeCode']) ?></td>
                        <td><code><?= h(short_formula(isset($node['ExpressionText']) ? (string) $node['ExpressionText'] : '')) ?></code></td>
                        <td class="text-end"><?= number_format((int) ($node['DependencyCount'] ?? 0)) ?></td>
                        <td class="text-end">
                          <a href="index.php?route=scenario-admin/node&id=<?= (int) $node['NodeID'] ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil-square"></i>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="tab-pane fade" id="dependencies-pane" role="tabpanel" aria-labelledby="dependencies-tab" tabindex="0">
        <h5 class="mb-3">Dependencies</h5>
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Target node</th>
                <th>Depends on</th>
                <th>Type</th>
                <th class="text-end">Offset</th>
                <th class="text-end">Sort</th>
                <th>Required</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($dependencies === []): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No dependencies are defined for this model.</td></tr>
            <?php else: ?>
              <?php foreach ($dependencies as $dependency): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h((string) $dependency['NodeCode']) ?></div>
                    <div class="small text-muted"><?= h((string) $dependency['NodeName']) ?></div>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= h((string) $dependency['DependsOnNodeCode']) ?></div>
                    <div class="small text-muted"><?= h((string) $dependency['DependsOnNodeName']) ?></div>
                  </td>
                  <td><?= h((string) $dependency['DependencyTypeCode']) ?></td>
                  <td class="text-end"><?= number_format((int) $dependency['OffsetPeriods']) ?></td>
                  <td class="text-end"><?= number_format((int) $dependency['SortOrder']) ?></td>
                  <td><?= ((int) ($dependency['RequiredFlag'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
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
