<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$scenario = is_array($scenario ?? null) ? $scenario : [];
$bundle = is_array($bundle ?? null) ? $bundle : ['scenario' => [], 'steps' => [], 'stepTranslations' => [], 'samples' => [], 'sampleTranslations' => []];
$scenarioOptions = is_array($scenarioOptions ?? null) ? $scenarioOptions : [];
$languageOptions = is_array($languageOptions ?? null) ? $languageOptions : [];
$scenarioCode = (string) ($scenarioCode ?? '');
$languageCode = (string) ($languageCode ?? '');
$tableInstalled = (bool) ($tableInstalled ?? false);
$scenarioTranslation = is_array($bundle['scenario'] ?? null) ? $bundle['scenario'] : [];
$steps = is_array($bundle['steps'] ?? null) ? $bundle['steps'] : [];
$stepTranslations = is_array($bundle['stepTranslations'] ?? null) ? $bundle['stepTranslations'] : [];
$samples = is_array($bundle['samples'] ?? null) ? $bundle['samples'] : [];
$sampleTranslations = is_array($bundle['sampleTranslations'] ?? null) ? $bundle['sampleTranslations'] : [];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-translate me-2"></i>Training Translations</h3>
        <?php if (!empty($scenario['ScenarioTitle'])): ?><div class="small text-muted mt-1"><?= h((string) ($scenario['ScenarioTitle'] ?? '')) ?><?= $languageCode !== '' ? ' / ' . h($languageCode) : '' ?></div><?php endif; ?>
      </div>
      <div class="d-inline-flex gap-2">
        <?php if ($scenarioCode !== ''): ?>
          <a href="index.php?route=training-admin/steps&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ol me-1"></i>Steps</a>
          <a href="index.php?route=training-admin/scenario-form&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>Scenario</a>
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

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="training-admin/translations">
        <div class="col-md-6">
          <select name="scenario_code" class="form-select form-select-sm">
            <option value="">Select a training scenario</option>
            <?php foreach ($scenarioOptions as $option): ?>
              <?php $optionCode = (string) ($option['ScenarioCode'] ?? ''); ?>
              <option value="<?= h($optionCode) ?>" <?= $scenarioCode === $optionCode ? 'selected' : '' ?>>
                <?= h((string) ($option['ScenarioTitle'] ?? $optionCode)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="language_code" class="form-select form-select-sm">
            <?php foreach ($languageOptions as $option): ?>
              <option value="<?= h((string) $option) ?>" <?= $languageCode === (string) $option ? 'selected' : '' ?>><?= h((string) $option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">Open</button>
          <a href="index.php?route=training-admin/scenarios" class="btn btn-sm btn-outline-secondary flex-fill">Catalogue</a>
        </div>
      </form>

      <div class="small text-muted mb-3">Maintain translated scenario text, translated step wording, and translated sample values for the selected language. Leave a translation blank to fall back to the base English content.</div>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-info mb-0">The training catalogue schema is not installed yet, so translation maintenance is unavailable on this screen.</div>
      <?php elseif ($scenarioCode !== '' && $languageCode !== ''): ?>
        <form method="post" action="index.php?route=training-admin/save-translations" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="ScenarioCode" value="<?= h($scenarioCode) ?>">
          <input type="hidden" name="LanguageCode" value="<?= h($languageCode) ?>">

          <div class="col-md-6">
            <label class="form-label">Scenario Title</label>
            <input type="text" name="ScenarioTitle" class="form-control form-control-sm" value="<?= h((string) ($scenarioTranslation['ScenarioTitle'] ?? '')) ?>" placeholder="<?= h((string) ($scenario['ScenarioTitle'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Module</label>
            <input type="text" name="ModuleName" class="form-control form-control-sm" value="<?= h((string) ($scenarioTranslation['ModuleName'] ?? '')) ?>" placeholder="<?= h((string) ($scenario['ModuleName'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Audience</label>
            <input type="text" name="Audience" class="form-control form-control-sm" value="<?= h((string) ($scenarioTranslation['Audience'] ?? '')) ?>" placeholder="<?= h((string) ($scenario['Audience'] ?? '')) ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Description</label>
            <textarea name="Description" class="form-control form-control-sm" rows="4" placeholder="<?= h((string) ($scenario['Description'] ?? '')) ?>"><?= h((string) ($scenarioTranslation['Description'] ?? '')) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Prerequisites</label>
            <textarea name="Prerequisites" class="form-control form-control-sm font-monospace" rows="4" placeholder="One translated prerequisite per line"><?= h(implode(PHP_EOL, array_map('strval', is_array($scenarioTranslation['Prerequisites'] ?? null) ? $scenarioTranslation['Prerequisites'] : []))) ?></textarea>
          </div>

          <div class="col-12">
            <h5 class="mb-2">Translated Step Wording</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Step</th>
                    <th>Base Title</th>
                    <th>Translated Title</th>
                    <th>Base Instruction</th>
                    <th>Translated Instruction</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($steps === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No steps found for this scenario.</td></tr>
                  <?php else: ?>
                    <?php foreach ($steps as $step): ?>
                      <?php $stepNo = (int) ($step['StepNo'] ?? 0); ?>
                      <?php $stepTranslation = is_array($stepTranslations[$stepNo] ?? null) ? $stepTranslations[$stepNo] : []; ?>
                      <tr>
                        <td><?= $stepNo ?></td>
                        <td><?= h((string) ($step['StepTitle'] ?? '')) ?></td>
                        <td><input type="text" name="StepTitles[<?= $stepNo ?>]" class="form-control form-control-sm" value="<?= h((string) ($stepTranslation['StepTitle'] ?? '')) ?>"></td>
                        <td><?= h((string) ($step['InstructionText'] ?? '')) ?></td>
                        <td><textarea name="StepInstructions[<?= $stepNo ?>]" class="form-control form-control-sm" rows="3"><?= h((string) ($stepTranslation['InstructionText'] ?? '')) ?></textarea></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="col-12">
            <h5 class="mb-2">Translated Sample Values</h5>
            <div class="table-responsive">
              <table class="table table-striped table-hover table-sm align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Sample Key</th>
                    <th>Base Template</th>
                    <th>Translated Template</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($samples === []): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No sample values found for this scenario.</td></tr>
                  <?php else: ?>
                    <?php foreach ($samples as $sample): ?>
                      <?php $sampleKey = (string) ($sample['SampleKey'] ?? ''); ?>
                      <?php $sampleTranslation = is_array($sampleTranslations[$sampleKey] ?? null) ? $sampleTranslations[$sampleKey] : []; ?>
                      <tr>
                        <td><code><?= h($sampleKey) ?></code></td>
                        <td><code><?= h((string) ($sample['SampleValueTemplate'] ?? '')) ?></code></td>
                        <td><input type="text" name="SampleValues[<?= h($sampleKey) ?>]" class="form-control form-control-sm" value="<?= h((string) ($sampleTranslation['SampleValueTemplate'] ?? '')) ?>"></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="col-12 d-flex justify-content-between">
            <a href="index.php?route=training-admin/steps&scenario_code=<?= urlencode($scenarioCode) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-save me-1"></i>Save Translations</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
