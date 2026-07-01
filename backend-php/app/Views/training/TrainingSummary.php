<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => '', 'status' => '', 'scenario_code' => ''];
$scenarioOptions = is_array($scenarioOptions ?? null) ? $scenarioOptions : [];
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$setupRequired = (bool) ($setupRequired ?? false);
$createTableScript = (string) ($createTableScript ?? '');
$canManageTraining = (bool) ($canManageTraining ?? false);
$formatTrainingTimestamp = static function ($value): string {
    $raw = trim((string) $value);
    if ($raw === '') {
        return '';
    }

    try {
        $utc = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
        return $utc
            ->setTimezone(new DateTimeZone(date_default_timezone_get()))
            ->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return $raw;
    }
};
require_once __DIR__ . '/../../../shared/csrf.php';
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-mortarboard me-2"></i><?= __t('training_summary_title') ?></h3>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=training/scenarios" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-list-check me-1"></i><?= __t('training_scenarios_title') ?>
        </a>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3"><?= __t('training_summary_intro') ?></div>

      <?php if ($setupRequired): ?>
        <div class="alert alert-warning">
          <div class="fw-semibold mb-1"><?= __t('training_progress_table_missing') ?></div>
          <div class="small">
            <?= __t('training_progress_table_summary_help', ['script' => $createTableScript]) ?>
          </div>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="training/summary">
        <div class="col-md-3">
          <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control form-control-sm" placeholder="Search user, email, or scenario">
        </div>
        <div class="col-md-3">
          <select name="module" class="form-select form-select-sm">
            <option value="">All modules</option>
            <?php foreach ($moduleOptions as $moduleValue => $moduleLabel): ?>
              <option value="<?= h((string) $moduleValue) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleValue) ? 'selected' : '' ?>>
                <?= h((string) $moduleLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select name="scenario_code" class="form-select form-select-sm">
            <option value=""><?= __t('training_all_scenarios') ?></option>
            <?php foreach ($scenarioOptions as $scenarioCode => $scenarioLabel): ?>
              <option value="<?= h((string) $scenarioCode) ?>" <?= ((string) ($filters['scenario_code'] ?? '') === (string) $scenarioCode) ? 'selected' : '' ?>>
                <?= h((string) $scenarioLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select form-select-sm">
            <option value=""><?= __t('training_all_statuses') ?></option>
            <?php foreach (['active' => __t('training_status_active'), 'completed' => __t('training_status_completed'), 'stopped' => __t('training_status_stopped')] as $statusValue => $statusLabel): ?>
              <option value="<?= h($statusValue) ?>" <?= ((string) ($filters['status'] ?? '') === $statusValue) ? 'selected' : '' ?>>
                <?= h($statusLabel) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-1 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-outline-primary flex-fill">Filter</button>
        </div>
        <div class="col-md-12">
          <a href="index.php?route=training/summary" class="btn btn-sm btn-outline-secondary">Reset</a>
        </div>
      </form>

      <div class="small text-muted mb-3"><?= __t('training_summary_register_note') ?></div>

      <?php if ($canManageTraining && !$setupRequired): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
          <div class="small text-muted"><?= __t('training_admin_reset_note') ?></div>
          <form method="post" action="index.php?route=training/reset" onsubmit="return confirm('<?= h(__t('training_confirm_reset_all')) ?>');">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="reset_action" value="all">
            <input type="hidden" name="return_q" value="<?= h((string) ($filters['q'] ?? '')) ?>">
            <input type="hidden" name="return_module" value="<?= h((string) ($filters['module'] ?? '')) ?>">
            <input type="hidden" name="return_status" value="<?= h((string) ($filters['status'] ?? '')) ?>">
            <input type="hidden" name="return_scenario_code" value="<?= h((string) ($filters['scenario_code'] ?? '')) ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger">
              <i class="bi bi-arrow-counterclockwise me-1"></i><?= __t('training_reset_all_records') ?>
            </button>
          </form>
        </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-striped table-hover table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th><?= __t('training_scenario_label') ?></th>
              <th><?= __t('status') ?></th>
              <th><?= __t('training_progress_label') ?></th>
              <th><?= __t('training_attempt_label') ?></th>
              <th><?= __t('training_started_label') ?></th>
              <th><?= __t('training_completed_label') ?></th>
              <th><?= __t('training_last_activity_label') ?></th>
              <?php if ($canManageTraining): ?>
                <th><?= __t('actions') ?></th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="<?= $canManageTraining ? '9' : '8' ?>" class="text-center text-muted py-3"><?= __t('training_no_records') ?></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                $status = strtolower((string) ($row['Status'] ?? ''));
                $statusClass = match ($status) {
                    'completed' => 'text-bg-success',
                    'active' => 'text-bg-primary',
                    'stopped' => 'text-bg-warning',
                    default => 'text-bg-light border',
                };
                $scenarioCode = (string) ($row['ScenarioCode'] ?? '');
                $scenarioLabel = $scenarioOptions[$scenarioCode] ?? $scenarioCode;
                $displayName = trim((string) ($row['DisplayName'] ?? ''));
                $username = trim((string) ($row['Username'] ?? ''));
                $userLabel = $displayName !== '' ? $displayName : $username;
                $currentStep = (int) ($row['CurrentStep'] ?? 0);
                $totalSteps = (int) ($row['TotalSteps'] ?? 0);
                ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h($userLabel) ?></div>
                    <div class="small text-muted"><?= h($username) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['Email'] ?? '')) ?></div>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= h((string) $scenarioLabel) ?></div>
                    <div class="small text-muted"><?= h($scenarioCode) ?></div>
                  </td>
                  <td><span class="badge <?= h($statusClass) ?>"><?= h(ucfirst($status !== '' ? $status : 'unknown')) ?></span></td>
                  <td><?= h((string) $currentStep) ?> / <?= h((string) $totalSteps) ?></td>
                  <td><?= h((string) ($row['AttemptNo'] ?? '1')) ?></td>
                  <td><?= h($formatTrainingTimestamp($row['StartedAt'] ?? '')) ?></td>
                  <td><?= h($formatTrainingTimestamp($row['CompletedAt'] ?? '')) ?></td>
                  <td><?= h($formatTrainingTimestamp($row['LastActivityAt'] ?? '')) ?></td>
                  <?php if ($canManageTraining): ?>
                    <td>
                      <?php $rowResetConfirm = __t('training_confirm_reset_row', ['user' => $userLabel]); ?>
                      <form method="post" action="index.php?route=training/reset" onsubmit="return confirm(<?= h((string) json_encode($rowResetConfirm, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>);">
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="reset_action" value="row">
                        <input type="hidden" name="target_user_id" value="<?= h((string) ($row['UserID'] ?? '0')) ?>">
                        <input type="hidden" name="target_scenario_code" value="<?= h($scenarioCode) ?>">
                        <input type="hidden" name="return_q" value="<?= h((string) ($filters['q'] ?? '')) ?>">
                        <input type="hidden" name="return_module" value="<?= h((string) ($filters['module'] ?? '')) ?>">
                        <input type="hidden" name="return_status" value="<?= h((string) ($filters['status'] ?? '')) ?>">
                        <input type="hidden" name="return_scenario_code" value="<?= h((string) ($filters['scenario_code'] ?? '')) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= __t('reset') ?></button>
                      </form>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
