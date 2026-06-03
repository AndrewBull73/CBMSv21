<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$scenarios = array_values(is_array($scenarios ?? null) ? $scenarios : []);
$userScenarioStates = is_array($userScenarioStates ?? null) ? $userScenarioStates : [];
$setupRequired = (bool) ($setupRequired ?? false);
$createTableScript = (string) ($createTableScript ?? '');
$trainingGuide = is_array($trainingGuide ?? null) ? $trainingGuide : null;
$trainingEnabled = (bool) ($trainingEnabled ?? false);
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => '', 'status' => ''];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$trainingScenarioRequest = trim((string) ($_GET['training_scenario_id'] ?? ''));

$scenariosByModule = [];
foreach ($scenarios as $scenario) {
    $moduleName = trim((string) ($scenario['module'] ?? ''));
    if ($moduleName === '') {
        $moduleName = __t('training_module_uncategorized');
    }
    if (!isset($scenariosByModule[$moduleName])) {
        $scenariosByModule[$moduleName] = [];
    }
    $scenariosByModule[$moduleName][] = $scenario;
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-mortarboard me-2"></i><?= __t('training_scenarios_title') ?></h3>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=training/summary" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-table me-1"></i><?= __t('training_summary_title') ?>
        </a>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3"><?= __t('training_scenarios_intro') ?></div>

      <form method="get" action="index.php" class="row g-2 mb-4">
        <input type="hidden" name="route" value="training/scenarios">
        <?php if ($trainingScenarioRequest !== ''): ?>
          <input type="hidden" name="training_scenario_id" value="<?= h($trainingScenarioRequest) ?>">
        <?php endif; ?>
        <div class="col-lg-4">
          <label for="trainingScenarioSearch" class="form-label small text-muted mb-1"><?= __t('search') ?></label>
          <input
            type="text"
            id="trainingScenarioSearch"
            name="q"
            value="<?= h((string) ($filters['q'] ?? '')) ?>"
            class="form-control form-control-sm"
            placeholder="<?= h(__t('training_filter_search_placeholder')) ?>"
          >
        </div>
        <div class="col-lg-3">
          <label for="trainingScenarioModule" class="form-label small text-muted mb-1"><?= __t('training_filter_module') ?></label>
          <select id="trainingScenarioModule" name="module" class="form-select form-select-sm">
            <option value=""><?= __t('training_all_modules') ?></option>
            <?php foreach ($moduleOptions as $moduleOption): ?>
              <option value="<?= h((string) $moduleOption) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleOption) ? 'selected' : '' ?>>
                <?= h((string) $moduleOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3">
          <label for="trainingScenarioStatus" class="form-label small text-muted mb-1"><?= __t('status') ?></label>
          <select id="trainingScenarioStatus" name="status" class="form-select form-select-sm">
            <option value=""><?= __t('training_all_statuses') ?></option>
            <option value="not_started" <?= ((string) ($filters['status'] ?? '') === 'not_started') ? 'selected' : '' ?>><?= __t('training_status_not_started') ?></option>
            <option value="active" <?= ((string) ($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>><?= __t('training_status_in_progress') ?></option>
            <option value="stopped" <?= ((string) ($filters['status'] ?? '') === 'stopped') ? 'selected' : '' ?>><?= __t('training_status_stopped') ?></option>
            <option value="completed" <?= ((string) ($filters['status'] ?? '') === 'completed') ? 'selected' : '' ?>><?= __t('training_status_completed') ?></option>
          </select>
        </div>
        <div class="col-lg-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">
            <i class="bi bi-funnel me-1"></i><?= __t('filter') ?>
          </button>
          <a href="index.php?route=training/scenarios" class="btn btn-sm btn-outline-secondary flex-fill">
            <?= __t('reset') ?>
          </a>
        </div>
      </form>

      <?php if ($setupRequired): ?>
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1"><?= __t('training_progress_table_missing') ?></div>
          <div class="small">
            <?= __t('training_progress_table_missing_help', ['script' => $createTableScript]) ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($scenarios === []): ?>
        <div class="text-center text-muted py-4"><?= __t('training_no_scenarios_match_filters') ?></div>
      <?php else: ?>
        <?php foreach ($scenariosByModule as $moduleName => $moduleScenarios): ?>
          <div class="d-flex justify-content-between align-items-center mb-2 mt-4">
            <div class="d-flex align-items-center gap-2">
              <h5 class="mb-0"><?= h($moduleName) ?></h5>
              <span class="badge text-bg-light border"><?= h((string) count($moduleScenarios)) ?></span>
            </div>
          </div>

          <div class="row g-3">
            <?php foreach ($moduleScenarios as $scenario): ?>
              <?php
              $scenarioId = (string) ($scenario['id'] ?? '');
              $state = $scenarioId !== '' ? ($userScenarioStates[$scenarioId] ?? null) : null;
              $status = strtolower((string) ($state['Status'] ?? 'not_started'));
              $statusLabel = match ($status) {
                  'completed' => __t('training_status_completed'),
                  'active' => __t('training_status_in_progress'),
                  'stopped' => __t('training_status_stopped'),
                  default => __t('training_status_not_started'),
              };
              $statusClass = match ($status) {
                  'completed' => 'text-bg-success',
                  'active' => 'text-bg-primary',
                  'stopped' => 'text-bg-warning',
                  default => 'text-bg-light border',
              };
              $stepCount = count(is_array($scenario['steps'] ?? null) ? $scenario['steps'] : []);
              $currentStep = (int) ($state['CurrentStep'] ?? 0);
              $totalSteps = (int) ($state['TotalSteps'] ?? $stepCount);
              $attemptNo = (int) ($state['AttemptNo'] ?? 0);
              $prerequisites = array_values(is_array($scenario['prerequisites'] ?? null) ? $scenario['prerequisites'] : []);
              $nextScenarioId = (string) ($scenario['next_scenario_id'] ?? '');
              $nextScenario = $nextScenarioId !== '' ? \App\Shared\TrainingScenarioCatalog::get($nextScenarioId) : null;
              $launchLabel = $status === 'active' ? __t('training_resume_scenario') : __t('training_open_scenario');
              ?>
              <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge text-bg-light border"><?= h((string) ($scenario['screen_family'] ?? 'training')) ?></span>
                      <span class="badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                    </div>
                    <div class="small text-muted"><?= h((string) ($scenario['difficulty'] ?? '')) ?></div>
                  </div>
                  <div class="card-body">
                    <div class="fw-semibold mb-1"><?= h((string) ($scenario['title'] ?? __t('training_scenario_title_default'))) ?></div>
                    <div class="small text-muted mb-3"><?= h((string) ($scenario['description'] ?? '')) ?></div>

                    <div class="row g-2 mb-3 small">
                      <div class="col-sm-6">
                        <div class="text-muted"><?= __t('training_module_label') ?></div>
                        <div class="fw-semibold"><?= h((string) ($scenario['module'] ?? '')) ?></div>
                      </div>
                      <div class="col-sm-6">
                        <div class="text-muted"><?= __t('training_audience_label') ?></div>
                        <div class="fw-semibold"><?= h((string) ($scenario['audience'] ?? '')) ?></div>
                      </div>
                      <div class="col-sm-4">
                        <div class="text-muted"><?= __t('training_steps_label') ?></div>
                        <div class="fw-semibold"><?= h((string) $stepCount) ?></div>
                      </div>
                      <div class="col-sm-4">
                        <div class="text-muted"><?= __t('training_progress_label') ?></div>
                        <div class="fw-semibold"><?= h((string) $currentStep) ?> / <?= h((string) $totalSteps) ?></div>
                      </div>
                      <div class="col-sm-4">
                        <div class="text-muted"><?= __t('training_attempt_label') ?></div>
                        <div class="fw-semibold"><?= h((string) max(0, $attemptNo)) ?></div>
                      </div>
                    </div>

                    <?php if ($prerequisites !== []): ?>
                      <div class="small mb-3">
                        <div class="text-muted mb-1"><?= __t('training_prerequisites_title') ?></div>
                        <ul class="mb-0 ps-3">
                          <?php foreach ($prerequisites as $item): ?>
                            <li><?= h((string) $item) ?></li>
                          <?php endforeach; ?>
                        </ul>
                      </div>
                    <?php endif; ?>

                    <?php if (is_array($nextScenario)): ?>
                      <div class="small text-muted mb-3">
                        <?= __t('training_next_recommended') ?>:
                        <span class="fw-semibold text-body"><?= h((string) ($nextScenario['title'] ?? $nextScenarioId)) ?></span>
                      </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2">
                      <a href="<?= h(\App\Shared\TrainingScenarioCatalog::startRoute($scenarioId)) ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-play-circle me-1"></i><?= h($launchLabel) ?>
                      </a>
                      <a href="index.php?route=training/summary&scenario_code=<?= urlencode($scenarioId) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-table me-1"></i><?= __t('training_view_summary') ?>
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
