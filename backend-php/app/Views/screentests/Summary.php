<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = array_values(is_array($rows ?? null) ? $rows : []);
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'scenario_code' => '', 'module' => '', 'result' => '', 'verification' => ''];
$scenarioOptions = is_array($scenarioOptions ?? null) ? $scenarioOptions : [];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$storageReady = (bool) ($storageReady ?? false);
$createTableScript = (string) ($createTableScript ?? '');
$canViewAllRuns = (bool) ($canViewAllRuns ?? false);
$attachmentsByRunId = is_array($attachmentsByRunId ?? null) ? $attachmentsByRunId : [];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-table me-2"></i><?= __t('screen_tests_results_title') ?></h3>
    </div>
    <div class="card-body">

      <div class="alert alert-info">
        <?= __t('screen_tests_summary_intro') ?>
      </div>

      <?php if (!$storageReady): ?>
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1"><?= __t('screen_tests_storage_session_only') ?></div>
          <div class="small"><?= __t('screen_tests_storage_session_only_help', ['script' => $createTableScript]) ?></div>
        </div>
      <?php endif; ?>

        <form method="get" action="index.php" class="row g-2 mb-4">
          <input type="hidden" name="route" value="screen-tests/summary">
          <div class="col-lg-3">
          <label for="screenTestSummaryScenario" class="form-label"><?= __t('screen_tests_scenario_label') ?></label>
          <select id="screenTestSummaryScenario" name="scenario_code" class="form-select">
            <option value=""><?= __t('screen_tests_all_scripts') ?></option>
            <?php foreach ($scenarioOptions as $scenarioCode => $scenarioTitle): ?>
              <option value="<?= h((string) $scenarioCode) ?>" <?= ((string) ($filters['scenario_code'] ?? '') === (string) $scenarioCode) ? 'selected' : '' ?>>
                <?= h((string) $scenarioTitle) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label for="screenTestSummaryModule" class="form-label"><?= __t('screen_tests_filter_module') ?></label>
          <select id="screenTestSummaryModule" name="module" class="form-select">
            <option value=""><?= __t('screen_tests_all_modules') ?></option>
            <?php foreach ($moduleOptions as $moduleOption): ?>
              <option value="<?= h((string) $moduleOption) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleOption) ? 'selected' : '' ?>>
                <?= h((string) $moduleOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-2">
          <label for="screenTestSummaryResult" class="form-label"><?= __t('screen_tests_filter_result') ?></label>
          <select id="screenTestSummaryResult" name="result" class="form-select">
            <option value=""><?= __t('screen_tests_all_results') ?></option>
            <option value="passed" <?= ((string) ($filters['result'] ?? '') === 'passed') ? 'selected' : '' ?>><?= __t('screen_tests_result_passed') ?></option>
            <option value="failed" <?= ((string) ($filters['result'] ?? '') === 'failed') ? 'selected' : '' ?>><?= __t('screen_tests_result_failed') ?></option>
            <option value="blocked" <?= ((string) ($filters['result'] ?? '') === 'blocked') ? 'selected' : '' ?>><?= __t('screen_tests_result_blocked') ?></option>
          </select>
        </div>
        <div class="col-lg-2">
          <label for="screenTestSummaryVerification" class="form-label"><?= __t('screen_tests_filter_verification') ?></label>
          <select id="screenTestSummaryVerification" name="verification" class="form-select">
            <option value=""><?= __t('screen_tests_all_verification_statuses') ?></option>
            <option value="not_run" <?= ((string) ($filters['verification'] ?? '') === 'not_run') ? 'selected' : '' ?>><?= __t('screen_tests_verification_not_run') ?></option>
            <option value="manual_pass" <?= ((string) ($filters['verification'] ?? '') === 'manual_pass') ? 'selected' : '' ?>><?= __t('screen_tests_verification_passed') ?></option>
            <option value="manual_fail" <?= ((string) ($filters['verification'] ?? '') === 'manual_fail') ? 'selected' : '' ?>><?= __t('screen_tests_verification_failed') ?></option>
          </select>
        </div>
        <div class="col-lg-3">
          <label for="screenTestSummarySearch" class="form-label"><?= __t('search') ?></label>
          <input
            type="text"
            id="screenTestSummarySearch"
            name="q"
            value="<?= h((string) ($filters['q'] ?? '')) ?>"
            class="form-control"
            placeholder="<?= h(__t('screen_tests_filter_search_placeholder')) ?>"
          >
        </div>
        <div class="col-12 d-flex gap-2">
          <button type="submit" class="btn btn-outline-primary">
            <i class="bi bi-funnel me-1"></i><?= __t('filter') ?>
          </button>
          <a href="index.php?route=screen-tests/summary" class="btn btn-outline-secondary">
            <?= __t('reset') ?>
          </a>
        </div>
      </form>

      <?php if ($rows === []): ?>
        <div class="text-center text-muted py-4"><?= __t('screen_tests_no_results') ?></div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= __t('screen_tests_scenario_label') ?></th>
                <?php if ($canViewAllRuns): ?>
                  <th><?= __t('screen_tests_tester_label') ?></th>
                <?php endif; ?>
                <th><?= __t('training_attempt_label') ?></th>
                <th><?= __t('screen_tests_result_label') ?></th>
                <th><?= __t('screen_tests_verification_label') ?></th>
                <th><?= __t('screen_tests_context_label') ?></th>
                <th><?= __t('screen_tests_completed_at') ?></th>
                <th><?= __t('screen_tests_outcome_summary') ?></th>
                <th><?= __t('screen_tests_defect_reference') ?></th>
                <th><?= __t('screen_tests_evidence_label') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <?php
                $result = strtolower((string) ($row['RunResult'] ?? ''));
                $verification = strtolower((string) ($row['VerificationStatus'] ?? ''));
                $resultLabel = match ($result) {
                    'passed' => __t('screen_tests_result_passed'),
                    'failed' => __t('screen_tests_result_failed'),
                    'blocked' => __t('screen_tests_result_blocked'),
                    default => __t('screen_tests_status_not_run'),
                };
                $resultClass = match ($result) {
                    'passed' => 'text-bg-success',
                    'failed' => 'text-bg-danger',
                    'blocked' => 'text-bg-warning',
                    default => 'text-bg-light border',
                };
                $verificationLabel = match ($verification) {
                    'manual_pass' => __t('screen_tests_verification_passed'),
                    'manual_fail' => __t('screen_tests_verification_failed'),
                    default => __t('screen_tests_verification_not_run'),
                };
                $contextLabel = trim(implode(' / ', array_filter([
                    ((int) ($row['FiscalYearID'] ?? 0)) > 0 ? 'FY ' . (int) ($row['FiscalYearID'] ?? 0) : '',
                    ((int) ($row['VersionID'] ?? 0)) > 0 ? 'Ver ' . (int) ($row['VersionID'] ?? 0) : '',
                    trim((string) ($row['DataObjectCode'] ?? '')) !== '' ? trim((string) ($row['DataObjectCode'] ?? '')) : '',
                ])));
                $runAttachments = $attachmentsByRunId[(int) ($row['ScreenTestRunID'] ?? 0)] ?? [];
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['ScenarioTitle'] ?? $row['ScenarioCode'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['ModuleName'] ?? '')) ?></div>
                  </td>
                  <?php if ($canViewAllRuns): ?>
                    <td><?= h((string) ($row['DisplayName'] ?? $row['Username'] ?? '')) ?></td>
                  <?php endif; ?>
                  <td><?= h((string) ($row['AttemptNo'] ?? '')) ?></td>
                  <td><span class="badge rounded-pill <?= h($resultClass) ?>"><?= h($resultLabel) ?></span></td>
                  <td><?= h($verificationLabel) ?></td>
                  <td><?= h($contextLabel !== '' ? $contextLabel : 'n/a') ?></td>
                  <td><?= h((string) ($row['CompletedAt'] ?? '')) ?></td>
                  <td><?= h((string) ($row['OutcomeSummary'] ?? '')) ?></td>
                  <td><?= h((string) ($row['DefectReference'] ?? '')) ?></td>
                  <td>
                    <?php if ($runAttachments === []): ?>
                      <span class="text-muted small"><?= __t('screen_tests_no_evidence') ?></span>
                    <?php else: ?>
                      <div class="d-flex gap-1 flex-wrap">
                        <?php foreach (array_slice($runAttachments, 0, 3) as $attachment): ?>
                          <a
                            href="index.php?route=screen-tests/view-attachment&id=<?= (int) ($attachment['ScreenTestRunAttachmentID'] ?? 0) ?>"
                            target="_blank"
                            rel="noopener"
                            class="btn btn-sm btn-outline-secondary"
                          >
                            <?= __t('screen_tests_view_evidence') ?>
                          </a>
                        <?php endforeach; ?>
                        <?php if (count($runAttachments) > 3): ?>
                          <span class="small text-muted align-self-center">+<?= (int) (count($runAttachments) - 3) ?></span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
