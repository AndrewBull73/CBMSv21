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
$scenario = is_array($scenario ?? null) ? $scenario : [];
$scenarioOptions = is_array($scenarioOptions ?? null) ? $scenarioOptions : [];
$completionModes = is_array($completionModes ?? null) ? $completionModes : [];
$scenarioCode = (string) ($scenarioCode ?? ($record['ScenarioCode'] ?? ''));
$tableInstalled = (bool) ($tableInstalled ?? false);
$managementInstalled = (bool) ($managementInstalled ?? false);
$stepSupport = is_array($stepSupport ?? null) ? $stepSupport : [];
$supportNote = is_array($stepSupport['note'] ?? null) ? $stepSupport['note'] : [];
$supportCheckpoint = is_array($stepSupport['checkpoint'] ?? null) ? $stepSupport['checkpoint'] : [];
$stepNo = (int) ($record['StepNo'] ?? 0);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-ui-checks-grid me-2"></i><?= $stepNo > 0 ? 'Edit Training Step' : 'Create Training Step' ?></h3>
        <?php if (!empty($scenario['ScenarioTitle'])): ?><div class="small text-muted mt-1"><?= h((string) ($scenario['ScenarioTitle'] ?? '')) ?><?= $scenarioCode !== '' ? ' (' . h($scenarioCode) . ')' : '' ?></div><?php endif; ?>
      </div>
      <div class="d-inline-flex gap-2">
        <?php if ($scenarioCode !== ''): ?>
          <a href="index.php?route=training-admin/steps&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ol me-1"></i>Back to Steps</a>
        <?php else: ?>
          <a href="index.php?route=training-admin/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Catalogue</a>
        <?php endif; ?>
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

      <div class="small text-muted mb-3">Configure how this step is highlighted and what action completes it. The target element ID should match the actual field, tab, or button on the live screen.</div>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-info mb-0">The training catalogue schema is not installed yet, so step maintenance is unavailable on this screen.</div>
      <?php else: ?>
      <?php if (!$managementInstalled): ?>
        <div class="alert alert-info border-0 shadow-sm">
          Run <code>create_training_management_features.sql</code> to enable instructor notes and checkpoint support for steps.
        </div>
      <?php endif; ?>
      <form method="post" action="index.php?route=training-admin/save-step" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="OldStepNo" value="<?= $stepNo ?>">

        <div class="col-md-6">
          <label class="form-label">Scenario</label>
          <select name="ScenarioCode" class="form-select form-select-sm" required <?= $scenarioCode !== '' ? 'readonly disabled' : '' ?>>
            <option value="">Select a scenario</option>
            <?php foreach ($scenarioOptions as $option): ?>
              <?php $optionCode = (string) ($option['ScenarioCode'] ?? ''); ?>
              <option value="<?= h($optionCode) ?>" <?= $scenarioCode === $optionCode ? 'selected' : '' ?>>
                <?= h((string) ($option['ScenarioTitle'] ?? $optionCode)) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($scenarioCode !== ''): ?><input type="hidden" name="ScenarioCode" value="<?= h($scenarioCode) ?>"><?php endif; ?>
        </div>
        <div class="col-md-3">
          <label class="form-label">Step No</label>
          <input type="number" name="StepNo" class="form-control form-control-sm" value="<?= h((string) ($record['StepNo'] ?? '')) ?>" min="1" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Sort Order</label>
          <input type="number" name="SortOrder" class="form-control form-control-sm" value="<?= h((string) ($record['SortOrder'] ?? ($record['StepNo'] ?? '0'))) ?>" min="0" step="10">
        </div>

        <div class="col-md-6">
          <label class="form-label">Route</label>
          <input type="text" name="Route" class="form-control form-control-sm" value="<?= h((string) ($record['Route'] ?? '')) ?>" required placeholder="users/edit">
        </div>
        <div class="col-md-6">
          <label class="form-label">Target Element ID</label>
          <input type="text" name="TargetElementID" class="form-control form-control-sm" value="<?= h((string) ($record['TargetElementID'] ?? '')) ?>" placeholder="users-save-btn">
        </div>

        <div class="col-md-6">
          <label class="form-label">Step Title</label>
          <input type="text" name="StepTitle" class="form-control form-control-sm" value="<?= h((string) ($record['StepTitle'] ?? '')) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Completion Mode</label>
          <select name="CompletionMode" class="form-select form-select-sm" required>
            <option value="">Select completion mode</option>
            <?php foreach ($completionModes as $modeValue => $modeLabel): ?>
              <option value="<?= h($modeValue) ?>" <?= ((string) ($record['CompletionMode'] ?? '') === $modeValue) ? 'selected' : '' ?>><?= h($modeLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Sample Key</label>
          <input type="text" name="SampleKey" class="form-control form-control-sm" value="<?= h((string) ($record['SampleKey'] ?? '')) ?>" placeholder="Email">
        </div>
        <div class="col-md-6">
          <label class="form-label">Expected User Sample Key</label>
          <input type="text" name="ExpectedUserSampleKey" class="form-control form-control-sm" value="<?= h((string) ($record['ExpectedUserSampleKey'] ?? '')) ?>" placeholder="TargetUserID">
        </div>

        <div class="col-12">
          <label class="form-label">Instruction</label>
          <textarea name="InstructionText" class="form-control form-control-sm" rows="4" required><?= h((string) ($record['InstructionText'] ?? '')) ?></textarea>
        </div>

        <div class="col-12">
          <div class="card shadow-sm">
            <div class="card-header">
              <h5 class="mb-0">Instructor Support</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Trainer Note</label>
                  <textarea name="TrainerNote" class="form-control form-control-sm" rows="4" <?= !$managementInstalled ? 'disabled' : '' ?>><?= h((string) ($supportNote['TrainerNote'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Expected Outcome</label>
                  <textarea name="ExpectedOutcome" class="form-control form-control-sm" rows="4" <?= !$managementInstalled ? 'disabled' : '' ?>><?= h((string) ($supportNote['ExpectedOutcome'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Common Issues</label>
                  <textarea name="CommonIssues" class="form-control form-control-sm" rows="4" <?= !$managementInstalled ? 'disabled' : '' ?>><?= h((string) ($supportNote['CommonIssues'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Checkpoint Question</label>
                  <textarea name="QuestionText" class="form-control form-control-sm" rows="3" <?= !$managementInstalled ? 'disabled' : '' ?>><?= h((string) ($supportCheckpoint['QuestionText'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Expected Answer</label>
                  <textarea name="ExpectedAnswer" class="form-control form-control-sm" rows="3" <?= !$managementInstalled ? 'disabled' : '' ?>><?= h((string) ($supportCheckpoint['ExpectedAnswer'] ?? '')) ?></textarea>
                </div>
                <div class="col-12 d-flex flex-wrap gap-3">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="checkpoint-required-flag" name="CheckpointRequired" <?= ((int) ($supportCheckpoint['RequiredFlag'] ?? 0) === 1) ? 'checked' : '' ?> <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="checkpoint-required-flag">Required checkpoint</label>
                  </div>
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="checkpoint-active-flag" name="CheckpointActive" <?= ((int) ($supportCheckpoint['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?> <?= !$managementInstalled ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="checkpoint-active-flag">Active checkpoint</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="step-active-flag" name="ActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="step-active-flag">Active step</label>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-between">
          <a href="index.php?route=training-admin/steps&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Step</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
