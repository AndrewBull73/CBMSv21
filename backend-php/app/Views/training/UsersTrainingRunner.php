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
$trainingState = is_array($trainingState ?? null) ? $trainingState : null;
$currentStep = is_array($currentStep ?? null) ? $currentStep : null;
$steps = is_array($scenario['steps'] ?? null) ? $scenario['steps'] : [];
$samples = is_array($trainingState['samples'] ?? null) ? $trainingState['samples'] : [];
$isCompleted = ($trainingState['status'] ?? '') === 'completed';
$totalStepCount = count($steps);
$currentStepNumber = (int) ($trainingState['current_step'] ?? 0);
$attemptNo = (int) ($trainingState['attempt_no'] ?? 0);
$canManageTraining = (bool) ($canManageTraining ?? false);
$scenarioPrerequisites = array_values(is_array($scenarioPrerequisites ?? null) ? $scenarioPrerequisites : []);
$nextScenario = is_array($nextScenario ?? null) ? $nextScenario : null;
$currentStepNote = trim((string) ($currentStepNote ?? ''));
$scenarioId = (string) ($scenario['id'] ?? \App\Shared\TrainingScenarioCatalog::USERS_CREATE_DEMO);
$scenarioTitle = (string) ($scenario['title'] ?? __t('training_scenario_title_default'));
$scenarioRunnerUrl = \App\Shared\TrainingScenarioCatalog::startRoute($scenarioId);
$startedAt = trim((string) ($trainingState['started_at'] ?? ''));
$completedAt = trim((string) ($trainingState['completed_at'] ?? ''));
$durationLabel = '';
if ($startedAt !== '') {
    $from = strtotime($startedAt . ' UTC') ?: strtotime($startedAt);
    $toRaw = $completedAt !== '' ? $completedAt : gmdate('Y-m-d H:i:s');
    $to = strtotime($toRaw . ' UTC') ?: strtotime($toRaw);
    if ($from !== false && $to !== false && $to >= $from) {
        $seconds = $to - $from;
        $minutes = intdiv($seconds, 60);
        $hours = intdiv($minutes, 60);
        if ($hours > 0) {
            $durationLabel = $hours . 'h ' . ($minutes % 60) . 'm';
        } elseif ($minutes > 0) {
            $durationLabel = $minutes . 'm ' . ($seconds % 60) . 's';
        } else {
            $durationLabel = $seconds . 's';
        }
    }
}
$runnerPayload = [
    'stateUrl' => 'index.php?route=training/state&scenario_id=' . rawurlencode($scenarioId),
    'hasActiveState' => $trainingState !== null,
    'totalSteps' => $totalStepCount,
    'scenarioTitle' => $scenarioTitle,
];
?>

<div class="container mt-4" id="users-training-runner" data-training-runner='<?= h((string) json_encode($runnerPayload, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-mortarboard me-2"></i><?= h($scenarioTitle) ?> <?= __t('training_scenario_suffix') ?></h3>
      <div class="d-flex gap-2">
        <?php if ($trainingState !== null): ?>
          <a href="<?= h((string) ($openTargetHref ?? 'index.php?route=users/list')) ?>" id="training-open-target-btn" class="btn btn-sm btn-primary"><?= __t('training_open_guided_screen') ?></a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3"><?= __t('training_runner_intro') ?></div>

      <?php if ($scenarioPrerequisites !== []): ?>
        <div class="alert alert-info py-2">
          <div class="fw-semibold mb-1"><?= __t('training_prerequisites_title') ?></div>
          <ul class="small mb-0 ps-3">
            <?php foreach ($scenarioPrerequisites as $check): ?>
              <li class="<?= !empty($check['ok']) ? 'text-success' : 'text-danger' ?>">
                <?= !empty($check['ok']) ? __t('training_ready_prefix') : __t('training_check_prefix') ?> <?= h((string) ($check['label'] ?? '')) ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <div class="col-lg-7">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0"><?= __t('training_scenario_label') ?></h5>
            </div>
            <div class="card-body">
              <div class="fw-semibold mb-2"><?= h($scenarioTitle) ?></div>
              <p class="mb-3"><?= h((string) ($scenario['description'] ?? '')) ?></p>

              <div class="row g-2 mb-3 small">
                <div class="col-sm-4">
                  <div class="text-muted"><?= __t('training_attempt_label') ?></div>
                  <div class="fw-semibold"><?= h((string) max(0, $attemptNo)) ?></div>
                </div>
                <div class="col-sm-4">
                  <div class="text-muted"><?= __t('training_current_step_label') ?></div>
                  <div class="fw-semibold"><?= h((string) $currentStepNumber) ?> / <?= h((string) $totalStepCount) ?></div>
                </div>
                <div class="col-sm-4">
                  <div class="text-muted"><?= $isCompleted ? __t('training_time_taken_label') : __t('training_elapsed_label') ?></div>
                  <div class="fw-semibold"><?= h($durationLabel !== '' ? $durationLabel : '—') ?></div>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th><?= __t('training_step_label') ?></th>
                      <th><?= __t('training_instruction_label') ?></th>
                      <th><?= __t('status') ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($steps as $step): ?>
                      <?php
                      $stepNumber = (int) ($step['number'] ?? 0);
                      $statusLabel = __t('training_status_pending');
                      $statusClass = 'text-bg-light border';
                      if ($trainingState !== null) {
                          $currentStepNumber = (int) ($trainingState['current_step'] ?? 0);
                          $stepIsComplete = $isCompleted
                              || $stepNumber < $currentStepNumber
                              || ($currentStepNumber >= $totalStepCount && $stepNumber === $totalStepCount);
                          if ($stepIsComplete) {
                              $statusLabel = __t('training_status_complete');
                              $statusClass = 'text-bg-success';
                          } elseif ($stepNumber === $currentStepNumber && !$isCompleted) {
                              $statusLabel = __t('training_status_current');
                              $statusClass = 'text-bg-primary';
                          }
                      }
                      ?>
                      <tr data-training-step-row="<?= $stepNumber ?>">
                        <td class="fw-semibold"><?= $stepNumber ?></td>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($step['title'] ?? '')) ?></div>
                          <div class="small text-muted"><?= h((string) ($step['instruction'] ?? '')) ?></div>
                        </td>
                        <td><span class="badge <?= h($statusClass) ?>" data-training-step-status="<?= $stepNumber ?>"><?= h($statusLabel) ?></span></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-5">
          <div class="card shadow-sm mb-3">
            <div class="card-header">
              <h5 class="mb-0"><?= __t('training_state_title') ?></h5>
            </div>
            <div class="card-body">
              <?php if ($trainingState === null): ?>
                  <div class="small text-muted mb-3"><?= __t('training_no_active_state') ?></div>
                  <form method="post" action="index.php?route=training/start">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                    <input type="hidden" name="start_mode" value="beginning">
                    <button type="submit" class="btn btn-sm btn-primary"><?= __t('training_start_scenario') ?></button>
                  </form>
              <?php else: ?>
                <div class="small text-muted mb-2"><?= __t('status') ?></div>
                <div class="fw-semibold mb-3" id="training-runner-status"><?= h($isCompleted ? __t('training_status_completed') : __t('training_status_in_progress')) ?></div>

                <?php if ($currentStep !== null && !$isCompleted): ?>
                  <div class="small text-muted mb-2" id="training-current-step-label"><?= __t('training_current_step_label') ?></div>
                  <div class="fw-semibold" id="training-current-step-title"><?= h((string) ($currentStep['title'] ?? '')) ?></div>
                  <div class="small text-muted mb-3" id="training-current-step-instruction"><?= h((string) ($currentStep['instruction'] ?? '')) ?></div>
                <?php elseif ($isCompleted): ?>
                  <div class="alert alert-success py-2 mb-3" id="training-complete-alert">
                    <div class="fw-semibold"><?= __t('training_scenario_complete_title') ?></div>
                    <div class="small">The <?= h($scenarioTitle) ?> training scenario is complete. Attempt <?= h((string) max(0, $attemptNo)) ?> finished in <?= h($durationLabel !== '' ? $durationLabel : '—') ?>.</div>
                    <?php if (is_array($nextScenario)): ?>
                      <div class="small mt-2">Recommended next scenario: <strong><?= h((string) ($nextScenario['title'] ?? '')) ?></strong></div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="d-flex gap-2 flex-wrap">
                  <?php if (!$isCompleted): ?>
                    <form method="post" action="index.php?route=training/start" class="d-inline">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                      <input type="hidden" name="start_mode" value="current">
                      <button type="submit" class="btn btn-sm btn-outline-primary"><?= __t('training_restart_current') ?></button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="index.php?route=training/start" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                    <input type="hidden" name="start_mode" value="beginning">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= __t('training_restart_beginning') ?></button>
                  </form>
                  <form method="post" action="index.php?route=training/stop" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                    <input type="hidden" name="return" value="<?= h($scenarioRunnerUrl) ?>">
                    <button type="submit" class="btn btn-sm <?= $isCompleted ? 'btn-outline-secondary' : 'btn-outline-danger' ?>"><?= $isCompleted ? __t('training_finish_scenario') : __t('training_leave_scenario') ?></button>
                  </form>
                </div>

                <?php if ($canManageTraining): ?>
                  <hr>
                  <div class="small text-muted mb-2">Trainer / admin controls</div>
                  <div class="d-flex gap-2 flex-wrap mb-2">
                    <form method="post" action="index.php?route=training/manage" class="d-inline">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                      <input type="hidden" name="return" value="<?= h($scenarioRunnerUrl) ?>">
                      <input type="hidden" name="manage_action" value="skip">
                      <button type="submit" class="btn btn-sm btn-outline-warning">Skip Current Step</button>
                    </form>
                    <form method="post" action="index.php?route=training/manage" class="d-inline">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                      <input type="hidden" name="return" value="<?= h($scenarioRunnerUrl) ?>">
                      <input type="hidden" name="manage_action" value="mark_complete">
                      <button type="submit" class="btn btn-sm btn-outline-success">Mark Complete</button>
                    </form>
                    <form method="post" action="index.php?route=training/manage" class="d-inline">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                      <input type="hidden" name="return" value="<?= h($scenarioRunnerUrl) ?>">
                      <input type="hidden" name="manage_action" value="reopen">
                      <button type="submit" class="btn btn-sm btn-outline-secondary">Reopen Scenario</button>
                    </form>
                    <form method="post" action="index.php?route=training/manage" class="d-inline" onsubmit="return confirm('Reset this scenario for the current user?');">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                      <input type="hidden" name="return" value="<?= h($scenarioRunnerUrl) ?>">
                      <input type="hidden" name="manage_action" value="reset_current">
                      <button type="submit" class="btn btn-sm btn-outline-danger">Reset This Scenario</button>
                    </form>
                  </div>
                  <form method="post" action="index.php?route=training/manage" class="row g-2 align-items-end">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                    <input type="hidden" name="return" value="<?= h($scenarioRunnerUrl) ?>">
                    <input type="hidden" name="manage_action" value="jump">
                    <div class="col-sm-6">
                      <label for="training-target-step" class="form-label small text-muted mb-1">Jump to step</label>
                      <input type="number" min="1" max="<?= h((string) $totalStepCount) ?>" id="training-target-step" name="target_step" value="<?= h((string) max(1, $currentStepNumber)) ?>" class="form-control form-control-sm">
                    </div>
                    <div class="col-sm-6">
                      <button type="submit" class="btn btn-sm btn-outline-primary w-100">Jump</button>
                    </div>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($trainingState !== null): ?>
            <div class="card shadow-sm mb-3">
              <div class="card-header">
                <h5 class="mb-0">Step Notes</h5>
              </div>
              <div class="card-body">
                <form method="post" action="index.php?route=training/save-note">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                  <input type="hidden" name="step_number" value="<?= h((string) max(1, $currentStepNumber)) ?>">
                  <input type="hidden" name="return" value="<?= h($scenarioRunnerUrl) ?>">
                  <label for="training-step-note" class="form-label small text-muted mb-1">Record a note for this step</label>
                  <textarea id="training-step-note" name="step_note" rows="3" class="form-control form-control-sm mb-2" placeholder="Record anything unclear, useful, or blocked for this step."><?= h($currentStepNote) ?></textarea>
                  <button type="submit" class="btn btn-sm btn-outline-secondary">Save Note</button>
                </form>
              </div>
            </div>
          <?php endif; ?>

          <div class="card shadow-sm">
            <div class="card-header">
              <h5 class="mb-0">Suggested Sample Values</h5>
            </div>
            <div class="card-body">
              <?php if ($samples === []): ?>
                <div class="small text-muted">Sample values will be generated when the training scenario starts.</div>
              <?php else: ?>
                <?php foreach ($samples as $sampleKey => $sampleVal): ?>
                  <?php if ($sampleKey === 'TargetUserID' || $sampleVal === '') { continue; } ?>
                  <?php $sampleLabel = trim((string) preg_replace('/(?<!^)([A-Z])/', ' $1', (string) $sampleKey)); ?>
                  <div class="mb-2"><strong><?= h($sampleLabel) ?>:</strong> <code><?= h((string) $sampleVal) ?></code></div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const runner = document.getElementById('users-training-runner');
  if (!runner) { return; }

  const rawConfig = runner.getAttribute('data-training-runner');
  if (!rawConfig) { return; }

  let config;
  try {
    config = JSON.parse(rawConfig);
  } catch (error) {
    console.error('Training runner config failed to parse.', error);
    return;
  }

  if (!config.hasActiveState) {
    return;
  }

  const statusEl = document.getElementById('training-runner-status');
  const stepTitleEl = document.getElementById('training-current-step-title');
  const stepInstructionEl = document.getElementById('training-current-step-instruction');
  const stepLabelEl = document.getElementById('training-current-step-label');
  const openTargetBtn = document.getElementById('training-open-target-btn');

  const renderState = (payload) => {
    const state = payload && payload.state ? payload.state : null;
    const step = payload && payload.step ? payload.step : null;
    const isCompleted = state && state.status === 'completed';
    const currentStepNumber = Number(state && state.current_step ? state.current_step : 0);
    const totalSteps = Number((state && state.total_steps) || config.totalSteps || 0);

    document.querySelectorAll('[data-training-step-status]').forEach((badge) => {
      const stepNumber = Number(badge.getAttribute('data-training-step-status') || '0');
      let label = 'Pending';
      let className = 'badge text-bg-light border';

      if (state) {
        const stepIsComplete = isCompleted
          || stepNumber < currentStepNumber
          || (totalSteps > 0 && currentStepNumber >= totalSteps && stepNumber === totalSteps);
        if (stepIsComplete) {
          label = 'Complete';
          className = 'badge text-bg-success';
        } else if (stepNumber === currentStepNumber && !isCompleted) {
          label = 'Current';
          className = 'badge text-bg-primary';
        }
      }

      badge.className = className;
      badge.textContent = label;
    });

    if (statusEl) {
      statusEl.textContent = isCompleted ? 'Completed' : 'In Progress';
    }

    if (stepTitleEl && stepInstructionEl) {
      if (isCompleted || !step) {
        stepTitleEl.textContent = 'Scenario complete';
        stepInstructionEl.textContent = `The ${config.scenarioTitle || 'training'} scenario has been completed successfully.`;
      } else {
        stepTitleEl.textContent = step.title || '';
        stepInstructionEl.textContent = step.instruction || '';
      }
    }

    if (stepLabelEl) {
      stepLabelEl.textContent = isCompleted ? 'Outcome' : 'Current Step';
    }

    let completeAlert = document.getElementById('training-complete-alert');
    if (isCompleted && !completeAlert && stepInstructionEl) {
      completeAlert = document.createElement('div');
      completeAlert.id = 'training-complete-alert';
      completeAlert.className = 'alert alert-success py-2 mb-3';
      completeAlert.textContent = `The ${config.scenarioTitle || 'training'} scenario is complete.`;
      stepInstructionEl.parentNode.insertBefore(completeAlert, stepInstructionEl.nextSibling);
    } else if (!isCompleted && completeAlert) {
      completeAlert.remove();
    }

    if (openTargetBtn && payload && payload.openTargetHref) {
      openTargetBtn.href = payload.openTargetHref;
    }
  };

  const pollState = async () => {
    try {
      const response = await fetch(String(config.stateUrl || ''), { credentials: 'same-origin' });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        return;
      }
      renderState(payload);
    } catch (error) {
      console.error('Training runner state refresh failed.', error);
    }
  };

  pollState();
  window.setInterval(pollState, 2000);
});
</script>
