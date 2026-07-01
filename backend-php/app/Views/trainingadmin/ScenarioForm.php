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
$scenarioOptions = is_array($scenarioOptions ?? null) ? $scenarioOptions : [];
$tableInstalled = (bool) ($tableInstalled ?? false);
$scenarioCode = (string) ($record['ScenarioCode'] ?? '');
$prerequisites = is_array($record['Prerequisites'] ?? null) ? $record['Prerequisites'] : [];
$samples = is_array($record['Samples'] ?? null) ? $record['Samples'] : [];
$sampleLines = [];
foreach ($samples as $sample) {
    $sampleKey = trim((string) ($sample['SampleKey'] ?? ''));
    if ($sampleKey === '') {
        continue;
    }
    $sampleLines[] = $sampleKey . '=' . (string) ($sample['SampleValueTemplate'] ?? '');
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-journal-text me-2"></i><?= $scenarioCode !== '' ? 'Edit Training Scenario' : 'Create Training Scenario' ?></h3>
      </div>
      <div class="d-inline-flex gap-2">
        <?php if ($scenarioCode !== ''): ?>
          <a href="index.php?route=training-admin/steps&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ol me-1"></i>Steps</a>
          <a href="index.php?route=training-admin/translations&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-translate me-1"></i>Translations</a>
        <?php endif; ?>
        <a href="index.php?route=training-admin/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
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

      <div class="small text-muted mb-3">Define the core scenario metadata here. Maintain step-by-step flow on the dedicated Steps screen and multilingual wording on the Translations screen.</div>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-info mb-0">The training catalogue schema is not installed yet, so scenario maintenance is unavailable on this screen.</div>
      <?php else: ?>
      <form method="post" action="index.php?route=training-admin/save-scenario" class="row g-3">
        <?= csrf_field() ?>

        <div class="col-md-4">
          <label class="form-label">Scenario Code</label>
          <input id="TrainingScenarioCode" type="text" name="ScenarioCode" class="form-control form-control-sm" value="<?= h($scenarioCode) ?>" <?= $scenarioCode !== '' ? 'readonly' : 'required' ?> placeholder="users_create_demo">
        </div>
        <div class="col-md-5">
          <label class="form-label">Scenario Title</label>
          <input id="TrainingScenarioTitle" type="text" name="ScenarioTitle" class="form-control form-control-sm" value="<?= h((string) ($record['ScenarioTitle'] ?? '')) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select id="TrainingScenarioActiveFlag" name="ActiveFlag" class="form-select form-select-sm">
            <option value="1" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= ((int) ($record['ActiveFlag'] ?? 1) === 0) ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Screen Family</label>
          <input id="TrainingScenarioScreenFamily" type="text" name="ScreenFamily" class="form-control form-control-sm" value="<?= h((string) ($record['ScreenFamily'] ?? '')) ?>" required placeholder="users">
        </div>
        <div class="col-md-4">
          <label class="form-label">Module</label>
          <input id="TrainingScenarioModuleName" type="text" name="ModuleName" class="form-control form-control-sm" value="<?= h((string) ($record['ModuleName'] ?? '')) ?>" required placeholder="Administration">
        </div>
        <div class="col-md-4">
          <label class="form-label">Audience</label>
          <input id="TrainingScenarioAudience" type="text" name="Audience" class="form-control form-control-sm" value="<?= h((string) ($record['Audience'] ?? '')) ?>" placeholder="System Administrator / User Administrator">
        </div>

        <div class="col-md-4">
          <label class="form-label">Difficulty</label>
          <input id="TrainingScenarioDifficulty" type="text" name="Difficulty" class="form-control form-control-sm" value="<?= h((string) ($record['Difficulty'] ?? '')) ?>" placeholder="Introductory">
        </div>
        <div class="col-md-4">
          <label class="form-label">Runner Route</label>
          <input id="TrainingScenarioRunnerRoute" type="text" name="RunnerRoute" class="form-control form-control-sm" value="<?= h((string) ($record['RunnerRoute'] ?? '')) ?>" required placeholder="training/users">
        </div>
        <div class="col-md-4">
          <label class="form-label">Next Scenario</label>
          <select id="TrainingScenarioNextScenarioCode" name="NextScenarioCode" class="form-select form-select-sm">
            <option value="">No next scenario</option>
            <?php foreach ($scenarioOptions as $option): ?>
              <?php $optionCode = (string) ($option['ScenarioCode'] ?? ''); ?>
              <?php if ($optionCode === $scenarioCode) { continue; } ?>
              <option value="<?= h($optionCode) ?>" <?= ((string) ($record['NextScenarioCode'] ?? '') === $optionCode) ? 'selected' : '' ?>>
                <?= h((string) ($option['ScenarioTitle'] ?? $optionCode)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-2">
          <label class="form-label">Sort Order</label>
          <input id="TrainingScenarioSortOrder" type="number" name="SortOrder" class="form-control form-control-sm" value="<?= h((string) ($record['SortOrder'] ?? '0')) ?>" min="0" step="10">
        </div>
        <div class="col-md-10">
          <label class="form-label">Description</label>
          <textarea id="TrainingScenarioDescription" name="Description" class="form-control form-control-sm" rows="3"><?= h((string) ($record['Description'] ?? '')) ?></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label">Prerequisites</label>
          <textarea id="TrainingScenarioPrerequisites" name="Prerequisites" class="form-control form-control-sm font-monospace" rows="7" placeholder="One prerequisite per line"><?= h(implode(PHP_EOL, array_map('strval', $prerequisites))) ?></textarea>
          <div class="small text-muted mt-1">One prerequisite per line. These appear on the scenario catalogue and runner.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Sample Templates</label>
          <textarea id="TrainingScenarioSamples" name="Samples" class="form-control form-control-sm font-monospace" rows="7" placeholder="SampleKey=SampleValueTemplate"><?= h(implode(PHP_EOL, $sampleLines)) ?></textarea>
          <div class="small text-muted mt-1">Use one <code>key=value</code> entry per line. Sample keys can then be referenced from steps.</div>
        </div>

        <div class="col-12 d-flex justify-content-between">
          <a href="index.php?route=training-admin/scenarios" class="btn btn-sm btn-outline-secondary">Back</a>
          <button id="training-scenario-save-btn" type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Scenario</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
