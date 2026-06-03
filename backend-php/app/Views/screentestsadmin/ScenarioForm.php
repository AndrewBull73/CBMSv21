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
$sourceState = (string) ($sourceState ?? 'custom');
$storagePath = (string) ($storagePath ?? '');
$scenarioId = (string) ($record['id'] ?? '');
$baselineContext = is_array($record['baseline_context'] ?? null) ? $record['baseline_context'] : [];
$prerequisites = array_values(is_array($record['prerequisites'] ?? null) ? $record['prerequisites'] : []);
$testDataDefs = is_array($record['test_data_defs'] ?? null) ? $record['test_data_defs'] : [];
$steps = array_values(is_array($record['steps'] ?? null) ? $record['steps'] : []);
$expectedVisible = array_values(is_array($record['expected_visible'] ?? null) ? $record['expected_visible'] : []);
$expectedData = array_values(is_array($record['expected_data'] ?? null) ? $record['expected_data'] : []);
$verificationQueries = array_values(is_array($record['verification_queries'] ?? null) ? $record['verification_queries'] : []);
$resetScripts = array_values(is_array($record['reset_scripts'] ?? null) ? $record['reset_scripts'] : []);
$isExisting = $scenarioId !== '';
$sourceLabel = match ($sourceState) {
    'built_in' => 'Built-in',
    'override' => 'Built-in + Override',
    default => 'Custom',
};
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-journal-richtext me-2"></i><?= $isExisting ? 'Edit Test Script' : 'Create Test Script' ?></h3>
        <div class="small text-muted mt-1">Update tester-facing wording, step instructions, expected outcomes, and supporting script metadata.</div>
      </div>
      <div class="d-inline-flex gap-2 align-items-center">
        <span class="badge text-bg-light border"><?= h($sourceLabel) ?></span>
        <a href="index.php?route=screen-tests-admin/scenarios" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="alert alert-info">
        Changes made here are saved as editable overrides in <code><?= h($storagePath) ?></code>. Built-in scripts are not modified in source code.
      </div>

      <form method="post" action="index.php?route=screen-tests-admin/save-script" class="row g-3">
        <?= csrf_field() ?>

        <div class="col-md-4">
          <label class="form-label">Script ID</label>
          <input type="text" name="id" class="form-control form-control-sm" value="<?= h($scenarioId) ?>" <?= $isExisting ? 'readonly' : 'required' ?> placeholder="strategy_config_readiness_smoke">
        </div>
        <div class="col-md-5">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control form-control-sm" value="<?= h((string) ($record['title'] ?? '')) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label">Difficulty</label>
          <input type="text" name="difficulty" class="form-control form-control-sm" value="<?= h((string) ($record['difficulty'] ?? '')) ?>" placeholder="Introductory">
        </div>

        <div class="col-md-4">
          <label class="form-label">Module</label>
          <input type="text" name="module" class="form-control form-control-sm" value="<?= h((string) ($record['module'] ?? '')) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Screen Family</label>
          <input type="text" name="screen_family" class="form-control form-control-sm" value="<?= h((string) ($record['screen_family'] ?? '')) ?>" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Audience</label>
          <input type="text" name="audience" class="form-control form-control-sm" value="<?= h((string) ($record['audience'] ?? '')) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Target Route</label>
          <input type="text" name="target_route" class="form-control form-control-sm" value="<?= h((string) ($record['target_route'] ?? '')) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Target Label</label>
          <input type="text" name="target_label" class="form-control form-control-sm" value="<?= h((string) ($record['target_label'] ?? '')) ?>">
        </div>

        <div class="col-md-6">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control form-control-sm" rows="3"><?= h((string) ($record['description'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Purpose</label>
          <textarea name="purpose" class="form-control form-control-sm" rows="3"><?= h((string) ($record['purpose'] ?? '')) ?></textarea>
        </div>

        <div class="col-12">
          <h5 class="border-bottom pb-2">Baseline Context</h5>
          <div class="small text-muted mb-2">Use key/value rows for the tester context notes shown in the runner.</div>
          <div data-repeater="baseline">
            <div class="vstack gap-2">
              <?php foreach ($baselineContext as $label => $value): ?>
                <div class="row g-2 align-items-start" data-row>
                  <div class="col-md-4"><input type="text" name="baseline_label[]" class="form-control form-control-sm" value="<?= h((string) $label) ?>" placeholder="Label"></div>
                  <div class="col-md-7"><input type="text" name="baseline_value[]" class="form-control form-control-sm" value="<?= h((string) $value) ?>" placeholder="Value"></div>
                  <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
                </div>
              <?php endforeach; ?>
            </div>
            <template>
              <div class="row g-2 align-items-start" data-row>
                <div class="col-md-4"><input type="text" name="baseline_label[]" class="form-control form-control-sm" placeholder="Label"></div>
                <div class="col-md-7"><input type="text" name="baseline_value[]" class="form-control form-control-sm" placeholder="Value"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
              </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-row>Add Baseline Row</button>
          </div>
        </div>

        <div class="col-md-6">
          <h5 class="border-bottom pb-2">Prerequisites</h5>
          <div data-repeater="prerequisites">
            <div class="vstack gap-2">
              <?php foreach ($prerequisites as $item): ?>
                <div class="row g-2 align-items-start" data-row>
                  <div class="col-11"><input type="text" name="prerequisites[]" class="form-control form-control-sm" value="<?= h((string) $item) ?>" placeholder="Prerequisite"></div>
                  <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
                </div>
              <?php endforeach; ?>
            </div>
            <template>
              <div class="row g-2 align-items-start" data-row>
                <div class="col-11"><input type="text" name="prerequisites[]" class="form-control form-control-sm" placeholder="Prerequisite"></div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
              </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-row>Add Prerequisite</button>
          </div>
        </div>

        <div class="col-md-6">
          <h5 class="border-bottom pb-2">Test Data Templates</h5>
          <div data-repeater="samples">
            <div class="vstack gap-2">
              <?php foreach ($testDataDefs as $key => $value): ?>
                <div class="row g-2 align-items-start" data-row>
                  <div class="col-md-4"><input type="text" name="sample_key[]" class="form-control form-control-sm" value="<?= h((string) $key) ?>" placeholder="Sample key"></div>
                  <div class="col-md-7"><input type="text" name="sample_value[]" class="form-control form-control-sm" value="<?= h((string) $value) ?>" placeholder="Template value"></div>
                  <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
                </div>
              <?php endforeach; ?>
            </div>
            <template>
              <div class="row g-2 align-items-start" data-row>
                <div class="col-md-4"><input type="text" name="sample_key[]" class="form-control form-control-sm" placeholder="Sample key"></div>
                <div class="col-md-7"><input type="text" name="sample_value[]" class="form-control form-control-sm" placeholder="Template value"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
              </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-row>Add Sample Template</button>
          </div>
        </div>

        <div class="col-12">
          <h5 class="border-bottom pb-2">Test Steps</h5>
          <div data-repeater="steps">
            <div class="vstack gap-2">
              <?php foreach ($steps as $step): ?>
                <div class="row g-2 align-items-start" data-row>
                  <div class="col-md-1"><input type="number" min="1" step="1" name="step_number[]" class="form-control form-control-sm" value="<?= h((string) ($step['number'] ?? '1')) ?>"></div>
                  <div class="col-md-3"><input type="text" name="step_title[]" class="form-control form-control-sm" value="<?= h((string) ($step['title'] ?? '')) ?>" placeholder="Step title"></div>
                  <div class="col-md-7"><textarea name="step_instruction[]" class="form-control form-control-sm" rows="2" placeholder="Instruction"><?= h((string) ($step['instruction'] ?? '')) ?></textarea></div>
                  <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
                </div>
              <?php endforeach; ?>
            </div>
            <template>
              <div class="row g-2 align-items-start" data-row>
                <div class="col-md-1"><input type="number" min="1" step="1" name="step_number[]" class="form-control form-control-sm" value="1"></div>
                <div class="col-md-3"><input type="text" name="step_title[]" class="form-control form-control-sm" placeholder="Step title"></div>
                <div class="col-md-7"><textarea name="step_instruction[]" class="form-control form-control-sm" rows="2" placeholder="Instruction"></textarea></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
              </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-row>Add Step</button>
          </div>
        </div>

        <div class="col-md-6">
          <h5 class="border-bottom pb-2">Expected Visible Results</h5>
          <div data-repeater="expected-visible">
            <div class="vstack gap-2">
              <?php foreach ($expectedVisible as $item): ?>
                <div class="row g-2 align-items-start" data-row>
                  <div class="col-11"><input type="text" name="expected_visible[]" class="form-control form-control-sm" value="<?= h((string) $item) ?>" placeholder="Expected visible result"></div>
                  <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
                </div>
              <?php endforeach; ?>
            </div>
            <template>
              <div class="row g-2 align-items-start" data-row>
                <div class="col-11"><input type="text" name="expected_visible[]" class="form-control form-control-sm" placeholder="Expected visible result"></div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
              </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-row>Add Visible Result</button>
          </div>
        </div>

        <div class="col-md-6">
          <h5 class="border-bottom pb-2">Expected Data Results</h5>
          <div data-repeater="expected-data">
            <div class="vstack gap-2">
              <?php foreach ($expectedData as $item): ?>
                <div class="row g-2 align-items-start" data-row>
                  <div class="col-11"><input type="text" name="expected_data[]" class="form-control form-control-sm" value="<?= h((string) $item) ?>" placeholder="Expected data result"></div>
                  <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
                </div>
              <?php endforeach; ?>
            </div>
            <template>
              <div class="row g-2 align-items-start" data-row>
                <div class="col-11"><input type="text" name="expected_data[]" class="form-control form-control-sm" placeholder="Expected data result"></div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
              </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-row>Add Data Result</button>
          </div>
        </div>

        <div class="col-md-6">
          <h5 class="border-bottom pb-2">Verification Queries</h5>
          <div data-repeater="queries">
            <div class="vstack gap-2">
              <?php foreach ($verificationQueries as $query): ?>
                <div class="row g-2 align-items-start" data-row>
                  <div class="col-11"><textarea name="verification_queries[]" class="form-control form-control-sm font-monospace" rows="3" placeholder="SQL verification query"><?= h((string) $query) ?></textarea></div>
                  <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
                </div>
              <?php endforeach; ?>
            </div>
            <template>
              <div class="row g-2 align-items-start" data-row>
                <div class="col-11"><textarea name="verification_queries[]" class="form-control form-control-sm font-monospace" rows="3" placeholder="SQL verification query"></textarea></div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
              </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-row>Add Verification Query</button>
          </div>
        </div>

        <div class="col-md-6">
          <h5 class="border-bottom pb-2">Reset Scripts</h5>
          <div data-repeater="reset-scripts">
            <div class="vstack gap-2">
              <?php foreach ($resetScripts as $item): ?>
                <div class="row g-2 align-items-start" data-row>
                  <div class="col-md-4"><input type="text" name="reset_path[]" class="form-control form-control-sm" value="<?= h((string) ($item['path'] ?? '')) ?>" placeholder="Script path"></div>
                  <div class="col-md-7"><input type="text" name="reset_note[]" class="form-control form-control-sm" value="<?= h((string) ($item['note'] ?? '')) ?>" placeholder="Note"></div>
                  <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
                </div>
              <?php endforeach; ?>
            </div>
            <template>
              <div class="row g-2 align-items-start" data-row>
                <div class="col-md-4"><input type="text" name="reset_path[]" class="form-control form-control-sm" placeholder="Script path"></div>
                <div class="col-md-7"><input type="text" name="reset_note[]" class="form-control form-control-sm" placeholder="Note"></div>
                <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger w-100" data-remove-row>&times;</button></div>
              </div>
            </template>
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-row>Add Reset Script</button>
          </div>
        </div>

        <div class="col-12 d-flex justify-content-between">
          <a href="index.php?route=screen-tests-admin/scenarios" class="btn btn-sm btn-outline-secondary">Back</a>
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Script</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  document.querySelectorAll('[data-repeater]').forEach(function (repeater) {
    const addButton = repeater.querySelector('[data-add-row]');
    const template = repeater.querySelector('template');
    const stack = repeater.querySelector('.vstack');
    if (!addButton || !template || !stack) {
      return;
    }

    addButton.addEventListener('click', function () {
      const fragment = template.content.cloneNode(true);
      stack.appendChild(fragment);
    });

    repeater.addEventListener('click', function (event) {
      const target = event.target;
      if (!(target instanceof HTMLElement) || !target.matches('[data-remove-row]')) {
        return;
      }
      const row = target.closest('[data-row]');
      if (row) {
        row.remove();
      }
    });
  });
})();
</script>
