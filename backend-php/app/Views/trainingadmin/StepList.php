<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$scenario = is_array($scenario ?? null) ? $scenario : [];
$scenarioOptions = is_array($scenarioOptions ?? null) ? $scenarioOptions : [];
$scenarioCode = (string) ($scenarioCode ?? '');
$tableInstalled = (bool) ($tableInstalled ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-list-ol me-2"></i>Training Scenario Steps</h3>
        <?php if (!empty($scenario['ScenarioTitle'])): ?><div class="small text-muted mt-1"><?= h((string) ($scenario['ScenarioTitle'] ?? '')) ?><?= $scenarioCode !== '' ? ' (' . h($scenarioCode) . ')' : '' ?></div><?php endif; ?>
      </div>
      <div class="d-inline-flex gap-2">
        <?php if ($tableInstalled && $scenarioCode !== ''): ?>
          <a href="index.php?route=training-admin/translations&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-translate me-1"></i>Translations</a>
          <a href="index.php?route=training-admin/step-form&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Step</a>
        <?php endif; ?>
        <a href="index.php?route=training-admin/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Catalogue</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning alert-dismissible fade show">
          Run <code>create_training_scenario_catalog.sql</code> to install the training catalogue tables.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="training-admin/steps">
        <div class="col-md-8">
          <select name="scenario_code" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Select a training scenario</option>
            <?php foreach ($scenarioOptions as $option): ?>
              <?php $optionCode = (string) ($option['ScenarioCode'] ?? ''); ?>
              <option value="<?= h($optionCode) ?>" <?= $scenarioCode === $optionCode ? 'selected' : '' ?>>
                <?= h((string) ($option['ScenarioTitle'] ?? $optionCode)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">Open</button>
          <?php if ($scenarioCode !== ''): ?>
            <a href="index.php?route=training-admin/scenario-form&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-secondary flex-fill">Scenario</a>
          <?php endif; ?>
        </div>
      </form>

      <div class="small text-muted mb-3">Maintain the ordered step flow here. Use the step form to change target IDs, completion modes, and instructional wording for each step.</div>

      <div class="table-responsive">
        <table class="table table-striped table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Step</th>
              <th>Route</th>
              <th>Target</th>
              <th>Instruction</th>
              <th>Mode</th>
              <th>Sample Keys</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="8" class="text-center text-muted py-3"><?= $scenarioCode === '' ? 'Select a scenario to view its steps.' : 'No steps found for this scenario.' ?></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php $stepNo = (int) ($row['StepNo'] ?? 0); ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= $stepNo ?></div>
                    <div class="small text-muted"><?= h((string) ($row['StepTitle'] ?? '')) ?></div>
                  </td>
                  <td><code><?= h((string) ($row['Route'] ?? '')) ?></code></td>
                  <td><code><?= h((string) ($row['TargetElementID'] ?? '')) ?></code></td>
                  <td><?= h((string) ($row['InstructionText'] ?? '')) ?></td>
                  <td><code><?= h((string) ($row['CompletionMode'] ?? '')) ?></code></td>
                  <td>
                    <div><?= h((string) ($row['SampleKey'] ?? '')) ?></div>
                    <?php if (!empty($row['ExpectedUserSampleKey'])): ?><div class="small text-muted"><?= h((string) ($row['ExpectedUserSampleKey'] ?? '')) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                      <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Archived' ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <a href="index.php?route=training-admin/step-form&scenario_code=<?= urlencode($scenarioCode) ?>&step_no=<?= $stepNo ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <?php if ((int) ($row['ActiveFlag'] ?? 0) === 1): ?>
                        <form method="post" action="index.php?route=training-admin/archive-step" onsubmit="return confirm('Archive this training step?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="ScenarioCode" value="<?= h($scenarioCode) ?>">
                          <input type="hidden" name="StepNo" value="<?= $stepNo ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-archive"></i>
                          </button>
                        </form>
                      <?php endif; ?>
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
