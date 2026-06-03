<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$scenarios = array_values(is_array($scenarios ?? null) ? $scenarios : []);
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => '', 'result' => ''];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$latestRuns = is_array($latestRuns ?? null) ? $latestRuns : [];
$activeRuns = is_array($activeRuns ?? null) ? $activeRuns : [];
$storageReady = (bool) ($storageReady ?? false);
$createTableScript = (string) ($createTableScript ?? '');

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
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-clipboard-check me-2"></i><?= __t('screen_tests_title') ?></h3>
    </div>
    <div class="card-body">

      <div class="alert alert-info">
        <?= __t('screen_tests_intro') ?>
      </div>

      <?php if (!$storageReady): ?>
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1"><?= __t('screen_tests_storage_missing_title') ?></div>
          <div class="small"><?= __t('screen_tests_storage_missing_help', ['script' => $createTableScript]) ?></div>
        </div>
      <?php endif; ?>

        <form method="get" action="index.php" class="row g-2 mb-4">
          <input type="hidden" name="route" value="screen-tests/scenarios">
          <div class="col-lg-5">
          <label for="screenTestSearch" class="form-label"><?= __t('search') ?></label>
          <input
            type="text"
            id="screenTestSearch"
            name="q"
            value="<?= h((string) ($filters['q'] ?? '')) ?>"
            class="form-control"
            placeholder="<?= h(__t('screen_tests_filter_search_placeholder')) ?>"
          >
        </div>
        <div class="col-lg-3">
          <label for="screenTestModule" class="form-label"><?= __t('screen_tests_filter_module') ?></label>
          <select id="screenTestModule" name="module" class="form-select">
            <option value=""><?= __t('screen_tests_all_modules') ?></option>
            <?php foreach ($moduleOptions as $moduleOption): ?>
              <option value="<?= h((string) $moduleOption) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleOption) ? 'selected' : '' ?>>
                <?= h((string) $moduleOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label for="screenTestResult" class="form-label"><?= __t('screen_tests_filter_result') ?></label>
          <select id="screenTestResult" name="result" class="form-select">
            <option value=""><?= __t('screen_tests_all_results') ?></option>
            <option value="not_run" <?= ((string) ($filters['result'] ?? '') === 'not_run') ? 'selected' : '' ?>><?= __t('screen_tests_status_not_run') ?></option>
            <option value="active" <?= ((string) ($filters['result'] ?? '') === 'active') ? 'selected' : '' ?>><?= __t('screen_tests_status_in_progress') ?></option>
            <option value="passed" <?= ((string) ($filters['result'] ?? '') === 'passed') ? 'selected' : '' ?>><?= __t('screen_tests_result_passed') ?></option>
            <option value="failed" <?= ((string) ($filters['result'] ?? '') === 'failed') ? 'selected' : '' ?>><?= __t('screen_tests_result_failed') ?></option>
            <option value="blocked" <?= ((string) ($filters['result'] ?? '') === 'blocked') ? 'selected' : '' ?>><?= __t('screen_tests_result_blocked') ?></option>
          </select>
        </div>
        <div class="col-lg-2 d-flex align-items-end gap-2">
          <button type="submit" class="btn btn-outline-primary flex-fill">
            <i class="bi bi-funnel me-1"></i><?= __t('filter') ?>
          </button>
          <a href="index.php?route=screen-tests/scenarios" class="btn btn-outline-secondary flex-fill">
            <?= __t('reset') ?>
          </a>
        </div>
      </form>

      <?php if ($scenarios === []): ?>
        <div class="text-center text-muted py-4"><?= __t('screen_tests_no_scenarios_match_filters') ?></div>
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
              $latestRun = $latestRuns[$scenarioId] ?? null;
              $activeRun = $activeRuns[$scenarioId] ?? null;
              $resultState = 'not_run';
              if (is_array($activeRun)) {
                  $resultState = 'active';
              } elseif (is_array($latestRun)) {
                  $resultState = strtolower(trim((string) ($latestRun['RunResult'] ?? ''))) ?: 'not_run';
              }
              $statusLabel = match ($resultState) {
                  'passed' => __t('screen_tests_result_passed'),
                  'failed' => __t('screen_tests_result_failed'),
                  'blocked' => __t('screen_tests_result_blocked'),
                  'active' => __t('screen_tests_status_in_progress'),
                  default => __t('screen_tests_status_not_run'),
              };
              $statusClass = match ($resultState) {
                  'passed' => 'text-bg-success',
                  'failed' => 'text-bg-danger',
                  'blocked' => 'text-bg-warning',
                  'active' => 'text-bg-primary',
                  default => 'text-bg-light border',
              };
              $launchLabel = is_array($activeRun) ? __t('screen_tests_resume_script') : __t('screen_tests_open_script');
              $stepCount = count(is_array($scenario['steps'] ?? null) ? $scenario['steps'] : []);
              $attemptNo = is_array($activeRun)
                  ? (int) ($activeRun['attempt_no'] ?? 0)
                  : (int) ($latestRun['AttemptNo'] ?? 0);
              ?>
              <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                  <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge text-bg-light border"><?= h((string) ($scenario['screen_family'] ?? 'test')) ?></span>
                      <span class="badge <?= h($statusClass) ?>"><?= h($statusLabel) ?></span>
                    </div>
                    <div class="small text-muted"><?= h((string) ($scenario['difficulty'] ?? '')) ?></div>
                  </div>
                  <div class="card-body">
                    <div class="fw-semibold mb-1"><?= h((string) ($scenario['title'] ?? $scenarioId)) ?></div>
                    <div class="small text-muted mb-3"><?= h((string) ($scenario['description'] ?? '')) ?></div>

                    <div class="row g-2 mb-3 small">
                      <div class="col-sm-6">
                        <div class="text-muted"><?= __t('screen_tests_target_screen') ?></div>
                        <div class="fw-semibold"><?= h((string) ($scenario['target_label'] ?? $scenario['target_route'] ?? '')) ?></div>
                      </div>
                      <div class="col-sm-3">
                        <div class="text-muted"><?= __t('training_steps_label') ?></div>
                        <div class="fw-semibold"><?= h((string) $stepCount) ?></div>
                      </div>
                      <div class="col-sm-3">
                        <div class="text-muted"><?= __t('training_attempt_label') ?></div>
                        <div class="fw-semibold"><?= h((string) max(0, $attemptNo)) ?></div>
                      </div>
                    </div>

                    <div class="small text-muted mb-3"><?= h((string) ($scenario['purpose'] ?? '')) ?></div>

                    <div class="d-flex gap-2 flex-wrap">
                      <a href="index.php?route=screen-tests/runner&scenario_id=<?= urlencode($scenarioId) ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-play-circle me-1"></i><?= h($launchLabel) ?>
                      </a>
                      <a href="index.php?route=screen-tests/summary&scenario_code=<?= urlencode($scenarioId) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-table me-1"></i><?= __t('screen_tests_view_results') ?>
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
