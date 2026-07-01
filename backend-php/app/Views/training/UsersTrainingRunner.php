<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('training_runner_normalize_checkpoint')) {
    function training_runner_normalize_checkpoint(array $checkpoint): array
    {
        if (($checkpoint['type'] ?? '') === 'multiple_choice' && !empty($checkpoint['options'])) {
            return $checkpoint;
        }

        $expectedAnswer = trim((string) ($checkpoint['expected_answer'] ?? ''));
        if ($expectedAnswer === '' || !str_starts_with($expectedAnswer, '{')) {
            return $checkpoint;
        }

        $payload = json_decode($expectedAnswer, true);
        if (!is_array($payload) || strtolower(trim((string) ($payload['type'] ?? ''))) !== 'multiple_choice') {
            return $checkpoint;
        }

        $options = [];
        foreach (array_values(is_array($payload['options'] ?? null) ? $payload['options'] : []) as $option) {
            if (!is_array($option)) {
                continue;
            }
            $key = trim((string) ($option['key'] ?? ''));
            $label = trim((string) ($option['label'] ?? $option['text'] ?? ''));
            if ($key === '' || $label === '') {
                continue;
            }
            $options[] = [
                'key' => $key,
                'label' => $label,
            ];
        }

        if ($options === []) {
            return $checkpoint;
        }

        $checkpoint['type'] = 'multiple_choice';
        $checkpoint['correct'] = trim((string) ($payload['correct'] ?? ''));
        $checkpoint['options'] = $options;
        $checkpoint['explanation'] = trim((string) ($payload['explanation'] ?? ($checkpoint['explanation'] ?? '')));

        return $checkpoint;
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
$assignmentMode = strtolower(trim((string) ($_GET['assignment_mode'] ?? ($trainingState['assignment_mode'] ?? 'self_paced'))));
$assignmentMode = $assignmentMode === 'instructor_led' ? 'instructor_led' : 'self_paced';
$assignmentId = (int) ($_GET['assignment_id'] ?? ($trainingState['assignment_id'] ?? 0));
$scenarioFinishUrl = $isCompleted ? 'index.php?route=training/dashboard' : $scenarioRunnerUrl;
$workflowOverviewCheckpoint = [
    'question' => 'Which answer best explains why keeping Workflow Operations inside CBMS improves project governance?',
    'expected_answer' => '{"type":"multiple_choice","correct":"B","options":[{"key":"A","label":"It replaces the need for project ownership and review meetings."},{"key":"B","label":"It links projects, requirements, tasks, issues, evidence, and ownership in one governed record set."},{"key":"C","label":"It only changes the page layout so the screens are easier to read."},{"key":"D","label":"It prevents any changes after a project has been created."}],"explanation":"Integrated workflow records improve governance by giving visibility and control across project scope, ownership, tasks, issues, evidence, quality, and lifecycle support."}',
    'required' => true,
    'type' => 'multiple_choice',
    'correct' => 'B',
    'options' => [
        ['key' => 'A', 'label' => 'It replaces the need for project ownership and review meetings.'],
        ['key' => 'B', 'label' => 'It links projects, requirements, tasks, issues, evidence, and ownership in one governed record set.'],
        ['key' => 'C', 'label' => 'It only changes the page layout so the screens are easier to read.'],
        ['key' => 'D', 'label' => 'It prevents any changes after a project has been created.'],
    ],
    'explanation' => 'Integrated workflow records improve governance by giving visibility and control across project scope, ownership, tasks, issues, evidence, quality, and lifecycle support.',
];
$currentStepTitleForCheckpoint = strtolower((string) ($currentStep['title'] ?? ''));
$currentStepInstructionForCheckpoint = strtolower((string) ($currentStep['instruction'] ?? ''));
$isWorkflowOverviewCheckpointStep = $currentStepNumber === 4
    && (
        $scenarioId === 'workflow_ops_overview'
        || str_contains($currentStepTitleForCheckpoint, 'checkpoint: governance purpose')
        || str_contains($currentStepInstructionForCheckpoint, 'which answer best explains why keeping workflow operations inside cbms improves project governance')
    );
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
    'completeUrl' => 'index.php?route=training/complete',
    'hasActiveState' => $trainingState !== null,
    'totalSteps' => $totalStepCount,
    'scenarioTitle' => $scenarioTitle,
    'scenarioId' => $scenarioId,
    'csrf' => csrf_token(),
    'canManageTraining' => $canManageTraining,
    'workflowOverviewCheckpoint' => $workflowOverviewCheckpoint,
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
                    <input type="hidden" name="assignment_mode" value="<?= h($assignmentMode) ?>">
                    <input type="hidden" name="assignment_id" value="<?= h((string) $assignmentId) ?>">
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
                  <div id="training-runner-checkpoint-wrap">
                    <?php if ($isWorkflowOverviewCheckpointStep): ?>
                      <form method="post" action="index.php?route=training/complete" class="alert alert-info py-2 mb-3" data-training-checkpoint-form>
                        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                        <input type="hidden" name="step_number" value="<?= h((string) $currentStepNumber) ?>">
                        <div class="small text-uppercase fw-semibold mb-1">Checkpoint - Required</div>
                        <div class="small mb-2"><?= h($workflowOverviewCheckpoint['question']) ?></div>
                        <?php foreach ($workflowOverviewCheckpoint['options'] as $option): ?>
                          <?php
                          $optionKey = (string) ($option['key'] ?? '');
                          $optionId = 'training-runner-workflow-overview-' . preg_replace('/[^A-Za-z0-9_-]/', '', $optionKey);
                          ?>
                          <div class="form-check small">
                            <input class="form-check-input" type="radio" name="checkpoint_answer" id="<?= h($optionId) ?>" value="<?= h($optionKey) ?>">
                            <label class="form-check-label" for="<?= h($optionId) ?>">
                              <span class="fw-semibold"><?= h($optionKey) ?>.</span> <?= h((string) ($option['label'] ?? '')) ?>
                            </label>
                          </div>
                        <?php endforeach; ?>
                        <div class="small mt-2 d-none" data-training-checkpoint-feedback></div>
                        <button type="submit" class="btn btn-sm btn-primary mt-2">Submit Answer and Continue</button>
                      </form>
                    <?php else: ?>
                    <?php $currentCheckpoint = training_runner_normalize_checkpoint(is_array($currentStep['checkpoint'] ?? null) ? $currentStep['checkpoint'] : []); ?>
                    <?php
                    $hasCheckpointOptions = ($currentCheckpoint['type'] ?? '') === 'multiple_choice'
                        && !empty($currentCheckpoint['options'])
                        && is_array($currentCheckpoint['options']);
                    if (!$hasCheckpointOptions && $isWorkflowOverviewCheckpointStep) {
                        $currentCheckpoint = $workflowOverviewCheckpoint;
                    }
                    ?>
                    <?php if (!empty($currentCheckpoint['question'])): ?>
                      <div class="alert alert-info py-2 mb-3">
                        <div class="small text-uppercase fw-semibold mb-1">Checkpoint<?= !empty($currentCheckpoint['required']) ? ' - Required' : '' ?></div>
                        <div class="small"><?= h((string) ($currentCheckpoint['question'] ?? '')) ?></div>
                        <?php $checkpointOptions = array_values(is_array($currentCheckpoint['options'] ?? null) ? $currentCheckpoint['options'] : []); ?>
                        <?php if (($currentCheckpoint['type'] ?? '') === 'multiple_choice' && $checkpointOptions !== []): ?>
                          <form method="post" action="index.php?route=training/complete" class="mt-2" data-training-checkpoint-form>
                            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                            <input type="hidden" name="step_number" value="<?= h((string) $currentStepNumber) ?>">
                            <?php foreach ($checkpointOptions as $option): ?>
                              <?php
                              $optionKey = (string) ($option['key'] ?? '');
                              $optionId = 'training-runner-checkpoint-' . preg_replace('/[^A-Za-z0-9_-]/', '', $optionKey);
                              ?>
                              <div class="form-check small">
                                <input class="form-check-input" type="radio" name="checkpoint_answer" id="<?= h($optionId) ?>" value="<?= h($optionKey) ?>">
                                <label class="form-check-label" for="<?= h($optionId) ?>">
                                  <span class="fw-semibold"><?= h($optionKey) ?>.</span> <?= h((string) ($option['label'] ?? '')) ?>
                                </label>
                              </div>
                            <?php endforeach; ?>
                            <div class="small mt-2 d-none" data-training-checkpoint-feedback></div>
                            <button type="submit" class="btn btn-sm btn-primary mt-2">Submit Answer and Continue</button>
                          </form>
                        <?php endif; ?>
                        <?php if ($canManageTraining && !empty($currentCheckpoint['expected_answer'])): ?>
                          <div class="small text-muted mt-2">
                            <span class="fw-semibold">Expected answer:</span>
                            <?= h((string) ($currentCheckpoint['explanation'] ?? $currentCheckpoint['expected_answer'] ?? '')) ?>
                          </div>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                    <?php endif; ?>
                  </div>
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
                      <input type="hidden" name="assignment_mode" value="<?= h($assignmentMode) ?>">
                      <input type="hidden" name="assignment_id" value="<?= h((string) $assignmentId) ?>">
                      <input type="hidden" name="start_mode" value="current">
                      <button type="submit" class="btn btn-sm btn-outline-primary"><?= __t('training_restart_current') ?></button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="index.php?route=training/start" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                    <input type="hidden" name="assignment_mode" value="<?= h($assignmentMode) ?>">
                    <input type="hidden" name="assignment_id" value="<?= h((string) $assignmentId) ?>">
                    <input type="hidden" name="start_mode" value="beginning">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= __t('training_restart_beginning') ?></button>
                  </form>
                  <form method="post" action="index.php?route=training/stop" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="scenario_id" value="<?= h($scenarioId) ?>">
                    <input type="hidden" name="return" value="<?= h($scenarioFinishUrl) ?>">
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
  const checkpointWrap = document.getElementById('training-runner-checkpoint-wrap');

  const escapeHtml = (value) => String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));

  const normalizeCheckpoint = (checkpoint) => {
    if (!checkpoint || typeof checkpoint !== 'object') { return null; }
    if (checkpoint.type === 'multiple_choice' && Array.isArray(checkpoint.options) && checkpoint.options.length > 0) {
      return checkpoint;
    }

    const expectedAnswer = String(checkpoint.expected_answer || '').trim();
    if (!expectedAnswer.startsWith('{')) { return checkpoint; }

    try {
      const payload = JSON.parse(expectedAnswer);
      if (!payload || String(payload.type || '').toLowerCase() !== 'multiple_choice') {
        return checkpoint;
      }

      const options = Array.isArray(payload.options)
        ? payload.options
            .map((option) => ({
              key: String(option && option.key ? option.key : '').trim(),
              label: String(option && (option.label || option.text) ? (option.label || option.text) : '').trim(),
            }))
            .filter((option) => option.key !== '' && option.label !== '')
        : [];
      if (options.length === 0) { return checkpoint; }

      return {
        ...checkpoint,
        type: 'multiple_choice',
        correct: String(payload.correct || '').trim(),
        options,
        explanation: String(payload.explanation || checkpoint.explanation || '').trim(),
      };
    } catch (error) {
      return checkpoint;
    }
  };

  const checkpointForStep = (step, currentStepNumber) => {
    const stepNumber = Number(step && step.number ? step.number : currentStepNumber);
    const stepTitle = String(step && step.title ? step.title : '').toLowerCase();
    const stepInstruction = String(step && step.instruction ? step.instruction : '').toLowerCase();
    const checkpoint = normalizeCheckpoint(step && step.checkpoint ? step.checkpoint : null);
    const hasCheckpointOptions = checkpoint
      && checkpoint.type === 'multiple_choice'
      && Array.isArray(checkpoint.options)
      && checkpoint.options.length > 0;
    if (hasCheckpointOptions) {
      return checkpoint;
    }
    if (stepNumber === 4 && (
      String(config.scenarioId || '') === 'workflow_ops_overview'
      || stepTitle.includes('checkpoint: governance purpose')
      || stepInstruction.includes('which answer best explains why keeping workflow operations inside cbms improves project governance')
    )) {
      return config.workflowOverviewCheckpoint || null;
    }
    return checkpoint;
  };

  const renderCheckpoint = (checkpoint, stepNumber) => {
    if (!checkpointWrap) { return; }
    checkpoint = normalizeCheckpoint(checkpoint);

    if (!checkpoint || !checkpoint.question) {
      checkpointWrap.innerHTML = '';
      return;
    }

    const options = Array.isArray(checkpoint.options) ? checkpoint.options : [];
    const requiredLabel = checkpoint.required ? ' - Required' : '';
    let html = `
      <div class="alert alert-info py-2 mb-3">
        <div class="small text-uppercase fw-semibold mb-1">Checkpoint${requiredLabel}</div>
        <div class="small">${escapeHtml(checkpoint.question)}</div>
    `;

    if (checkpoint.type === 'multiple_choice' && options.length > 0) {
      html += `
        <form method="post" action="${escapeHtml(config.completeUrl || 'index.php?route=training/complete')}" class="mt-2" data-training-checkpoint-form>
          <input type="hidden" name="_csrf" value="${escapeHtml(config.csrf || '')}">
          <input type="hidden" name="scenario_id" value="${escapeHtml(config.scenarioId || '')}">
          <input type="hidden" name="step_number" value="${escapeHtml(stepNumber)}">
      `;
      options.forEach((option) => {
        const key = String(option.key || '');
        const label = String(option.label || '');
        const optionId = `training-runner-checkpoint-${key.replace(/[^A-Za-z0-9_-]/g, '')}`;
        html += `
          <div class="form-check small">
            <input class="form-check-input" type="radio" name="checkpoint_answer" id="${escapeHtml(optionId)}" value="${escapeHtml(key)}">
            <label class="form-check-label" for="${escapeHtml(optionId)}"><span class="fw-semibold">${escapeHtml(key)}.</span> ${escapeHtml(label)}</label>
          </div>
        `;
      });
      html += `
          <div class="small mt-2 d-none" data-training-checkpoint-feedback></div>
          <button type="submit" class="btn btn-sm btn-primary mt-2">Submit Answer and Continue</button>
        </form>
      `;
    }

    if (config.canManageTraining && checkpoint.expected_answer) {
      html += `
        <div class="small text-muted mt-2">
          <span class="fw-semibold">Expected answer:</span>
          ${escapeHtml(checkpoint.explanation || checkpoint.expected_answer || '')}
        </div>
      `;
    }

    html += '</div>';
    checkpointWrap.innerHTML = html;
  };

  const renderVisibleWorkflowOverviewCheckpoint = () => {
    if (!checkpointWrap || checkpointWrap.querySelector('[data-training-checkpoint-form]')) {
      return;
    }

    const visibleTitle = String(stepTitleEl ? stepTitleEl.textContent : '').toLowerCase();
    const visibleInstruction = String(stepInstructionEl ? stepInstructionEl.textContent : '').toLowerCase();
    const isGovernanceCheckpoint = visibleTitle.includes('checkpoint: governance purpose')
      || visibleInstruction.includes('which answer best explains why keeping workflow operations inside cbms improves project governance');
    if (!isGovernanceCheckpoint) {
      return;
    }

    renderCheckpoint(config.workflowOverviewCheckpoint || null, 4);
  };

  runner.addEventListener('submit', async (event) => {
    const checkpointForm = event.target && event.target.closest
      ? event.target.closest('[data-training-checkpoint-form]')
      : null;
    if (!checkpointForm || !runner.contains(checkpointForm)) { return; }

    event.preventDefault();

    const feedback = checkpointForm.querySelector('[data-training-checkpoint-feedback]');
    if (feedback) {
      feedback.textContent = '';
      feedback.className = 'small mt-2 d-none';
    }

    const selected = checkpointForm.querySelector('input[name="checkpoint_answer"]:checked');
    if (!selected) {
      if (feedback) {
        feedback.textContent = 'Select an answer before continuing.';
        feedback.className = 'small mt-2 text-danger';
      }
      return;
    }

    const submitButton = checkpointForm.querySelector('button[type="submit"]');
    if (submitButton) {
      submitButton.disabled = true;
    }

    try {
      const response = await fetch(String(config.completeUrl || checkpointForm.action || ''), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: new URLSearchParams(new FormData(checkpointForm)).toString(),
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        if (feedback) {
          feedback.textContent = payload && payload.message ? String(payload.message) : 'The answer could not be submitted.';
          feedback.className = 'small mt-2 text-danger';
        }
        return;
      }

      if (feedback) {
        feedback.textContent = 'Correct.';
        feedback.className = 'small mt-2 text-success';
      }
      window.setTimeout(() => renderState(payload), 900);
    } catch (error) {
      console.error('Training checkpoint submission failed.', error);
      if (feedback) {
        feedback.textContent = 'The answer could not be submitted.';
        feedback.className = 'small mt-2 text-danger';
      }
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
      }
    }
  });

  const renderState = (payload) => {
    const state = payload && payload.state ? payload.state : null;
    const step = payload && payload.step ? payload.step : null;
    const isCompleted = state && state.status === 'completed';
    const currentStepNumber = Number(state && state.current_step ? state.current_step : 0);
    const totalSteps = Number((state && state.total_steps) || config.totalSteps || 0);
    const checkpoint = checkpointForStep(step, currentStepNumber);

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

    renderCheckpoint(isCompleted ? null : checkpoint, Number(step && step.number ? step.number : currentStepNumber));
    renderVisibleWorkflowOverviewCheckpoint();

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

  renderVisibleWorkflowOverviewCheckpoint();
  pollState();
  window.setInterval(pollState, 2000);
});
</script>
