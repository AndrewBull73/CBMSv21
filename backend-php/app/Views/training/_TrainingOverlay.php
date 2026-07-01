<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('training_overlay_normalize_checkpoint')) {
    function training_overlay_normalize_checkpoint(array $checkpoint): array
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

$trainingGuide = is_array($trainingGuide ?? null) ? $trainingGuide : [];
if ($trainingGuide === []) {
    return;
}

$scenario = is_array($trainingGuide['scenario'] ?? null) ? $trainingGuide['scenario'] : [];
$trainingState = is_array($trainingGuide['state'] ?? null) ? $trainingGuide['state'] : [];
$currentStep = is_array($trainingGuide['step'] ?? null) ? $trainingGuide['step'] : null;
$steps = array_values(is_array($scenario['steps'] ?? null) ? $scenario['steps'] : []);
$isCompleted = (bool) ($trainingGuide['isCompleted'] ?? false);
$sampleValue = (string) ($trainingGuide['sampleValue'] ?? '');
$currentStepNumber = (int) ($trainingState['current_step'] ?? 0);
$totalSteps = (int) ($trainingState['total_steps'] ?? count($steps));
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
$currentCheckpoint = training_overlay_normalize_checkpoint(is_array($currentStep['checkpoint'] ?? null) ? $currentStep['checkpoint'] : []);
$currentStepTitleForCheckpoint = strtolower((string) ($currentStep['title'] ?? ''));
$currentStepInstructionForCheckpoint = strtolower((string) ($currentStep['instruction'] ?? ''));
$isWorkflowOverviewCheckpointStep = $currentStepNumber === 4
    && (
        (string) ($trainingState['scenario_id'] ?? '') === 'workflow_ops_overview'
        || str_contains($currentStepTitleForCheckpoint, 'checkpoint: governance purpose')
        || str_contains($currentStepInstructionForCheckpoint, 'which answer best explains why keeping workflow operations inside cbms improves project governance')
    );
if ($isWorkflowOverviewCheckpointStep && empty($currentCheckpoint['options'])) {
    $currentCheckpoint = $workflowOverviewCheckpoint;
}
$stopReturnUrl = $isCompleted
    ? 'index.php?route=training/dashboard'
    : (string) ($trainingGuide['runnerUrl'] ?? 'index.php?route=training/scenarios');

$overlayPayload = [
    'completeUrl' => (string) ($trainingGuide['completeUrl'] ?? ''),
    'stuckUrl' => (string) ($trainingGuide['stuckUrl'] ?? 'index.php?route=training/stuck'),
    'csrf' => (string) ($trainingGuide['csrf'] ?? ''),
    'isCompleted' => $isCompleted,
    'state' => $trainingState,
    'steps' => $steps,
    'scenarioTitle' => (string) ($scenario['title'] ?? __t('training_scenario_title_default')),
    'scenarioId' => (string) ($trainingState['scenario_id'] ?? ''),
    'runnerUrl' => (string) ($trainingGuide['runnerUrl'] ?? 'index.php?route=training/scenarios'),
    'workflowOverviewCheckpoint' => $workflowOverviewCheckpoint,
];
?>

<style>
  .training-demo-panel {
    position: fixed;
    right: 1.25rem;
    top: 8rem;
    z-index: 1080;
    width: min(360px, calc(100vw - 2rem));
    max-width: calc(100vw - 2rem);
  }
  .training-demo-panel .card {
    border: 1px solid #f5d48a;
    box-shadow: 0 .75rem 2rem rgba(217, 119, 6, .16);
  }
  .training-demo-panel .card-header {
    cursor: move;
    user-select: none;
    background: #fff6dd;
  }
  .training-demo-panel.is-collapsed .card-body {
    display: none;
  }
  .training-demo-highlight {
    position: relative;
    z-index: 2;
    box-shadow: 0 0 0 .24rem rgba(245, 158, 11, .18), 0 0 0 2px rgba(217, 119, 6, .72) !important;
    border-color: #d97706 !important;
    transition: box-shadow .2s ease, border-color .2s ease;
  }
  .modal.training-demo-highlight {
    z-index: 1065 !important;
  }
  .training-demo-highlight-label {
    color: #b45309 !important;
  }
  .training-demo-pulse {
    animation: trainingPulse 1.2s ease-in-out infinite alternate;
  }
  @keyframes trainingPulse {
    from { box-shadow: 0 0 0 .24rem rgba(245, 158, 11, .18), 0 0 0 2px rgba(217, 119, 6, .72) !important; }
    to { box-shadow: 0 0 0 .34rem rgba(251, 191, 36, .22), 0 0 0 2px rgba(180, 83, 9, .88) !important; }
  }
</style>

<div
  id="training-demo-panel"
  class="training-demo-panel"
  data-training='<?= h((string) json_encode($overlayPayload, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'
>
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
      <div class="d-flex align-items-center gap-2">
        <span class="badge text-bg-info"><?= __t('training_mode_badge') ?></span>
        <strong class="small"><?= h((string) ($scenario['title'] ?? __t('training_scenario_title_default'))) ?></strong>
      </div>
      <div class="d-flex align-items-center gap-2">
        <?php if (!$isCompleted && $currentStepNumber > 0 && $totalSteps > 0): ?>
          <span class="small text-muted" id="training-step-progress"><?= __t('training_step_of', ['current' => (string) $currentStepNumber, 'total' => (string) $totalSteps]) ?></span>
        <?php else: ?>
          <span class="small text-muted" id="training-step-progress"><?= __t('training_status_completed') ?></span>
        <?php endif; ?>
        <button type="button" id="training-demo-collapse" class="btn btn-sm btn-outline-secondary py-0 px-2" aria-label="Collapse training panel">
          <i class="bi bi-dash-lg"></i>
        </button>
      </div>
    </div>
    <div class="card-body py-3">
      <?php if ($isCompleted): ?>
        <div class="alert alert-success py-2 mb-3">
          <div class="fw-semibold"><?= __t('training_scenario_complete_title') ?></div>
          <div class="small"><?= __t('training_overlay_complete_message') ?></div>
        </div>
      <?php elseif ($currentStep !== null): ?>
        <div class="small text-uppercase text-muted fw-semibold mb-1"><?= __t('training_current_step_label') ?></div>
        <div class="fw-semibold mb-1" id="training-step-title"><?= h((string) ($currentStep['title'] ?? '')) ?></div>
        <div class="small text-muted mb-3" id="training-step-instruction"><?= h((string) ($currentStep['instruction'] ?? '')) ?></div>
        <div id="training-checkpoint-wrap" class="alert alert-info py-2 mb-3<?= empty($currentCheckpoint['question']) ? ' d-none' : '' ?>">
          <div class="small text-uppercase fw-semibold mb-1">Checkpoint<?= !empty($currentCheckpoint['required']) ? ' - Required' : '' ?></div>
          <div class="small" id="training-checkpoint-question"><?= h((string) ($currentCheckpoint['question'] ?? '')) ?></div>
          <div id="training-checkpoint-options" class="mt-2 d-none"></div>
          <div id="training-checkpoint-feedback" class="small mt-2 d-none"></div>
        </div>
        <div id="training-sample-wrap" class="small mb-3<?= $sampleValue === '' ? ' d-none' : '' ?>">
          <span class="text-muted"><?= __t('training_suggested_value') ?>:</span>
          <code id="training-sample-value"><?= h($sampleValue) ?></code>
        </div>
      <?php endif; ?>

      <div class="d-flex gap-2">
        <button type="button" id="training-step-continue" class="btn btn-sm btn-warning d-none">
          <i class="bi bi-arrow-right-circle me-1"></i><?= __t('training_continue') ?>
        </button>
        <button type="button" id="training-step-stuck" class="btn btn-sm btn-outline-secondary<?= $isCompleted ? ' d-none' : '' ?>">
          <i class="bi bi-question-circle me-1"></i>I'm stuck
        </button>
        <form method="post" action="<?= h((string) ($trainingGuide['stopUrl'] ?? 'index.php?route=training/stop')) ?>" class="d-inline">
          <input type="hidden" name="_csrf" value="<?= h((string) ($trainingGuide['csrf'] ?? '')) ?>">
          <input type="hidden" name="scenario_id" value="<?= h((string) ($trainingState['scenario_id'] ?? '')) ?>">
          <input type="hidden" name="return" value="<?= h($stopReturnUrl) ?>">
          <button type="submit" class="btn btn-sm <?= $isCompleted ? 'btn-outline-secondary' : 'btn-outline-danger' ?>">
            <i class="bi <?= $isCompleted ? 'bi-check2-circle' : 'bi-x-circle' ?> me-1"></i><?= $isCompleted ? __t('training_finish_scenario') : __t('training_leave_scenario') ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.getElementById('training-demo-panel');
  if (!panel) { return; }
  const collapseBtn = document.getElementById('training-demo-collapse');
  const header = panel.querySelector('.card-header');

  const rawConfig = panel.getAttribute('data-training');
  if (!rawConfig) { return; }

  let config;
  try {
    config = JSON.parse(rawConfig);
  } catch (error) {
    console.error('Training panel config failed to parse.', error);
    return;
  }

  const stepTitle = document.getElementById('training-step-title');
  const stepInstruction = document.getElementById('training-step-instruction');
  const checkpointWrap = document.getElementById('training-checkpoint-wrap');
  const checkpointQuestion = document.getElementById('training-checkpoint-question');
  const checkpointOptions = document.getElementById('training-checkpoint-options');
  const checkpointFeedback = document.getElementById('training-checkpoint-feedback');
  const stepProgress = document.getElementById('training-step-progress');
  const sampleWrap = document.getElementById('training-sample-wrap');
  const sampleValue = document.getElementById('training-sample-value');
  const continueBtn = document.getElementById('training-step-continue');
  const stuckBtn = document.getElementById('training-step-stuck');
  const stopForm = panel.querySelector('form');
  const stopButton = stopForm ? stopForm.querySelector('button[type="submit"]') : null;
  const stopButtonIcon = stopButton ? stopButton.querySelector('i') : null;

  let activeElements = [];
  let pending = false;
  let state = config.state || {};
  const steps = Array.isArray(config.steps) ? config.steps : [];
  let dragState = null;
  let activeCheckpoint = null;

  const getCurrentStep = () => {
    const currentNumber = Number(state.current_step || 0);
    return steps.find(step => Number(step.number || 0) === currentNumber) || null;
  };

  const clearHighlights = () => {
    activeElements.forEach(el => el.classList.remove('training-demo-highlight', 'training-demo-pulse', 'training-demo-highlight-label'));
    activeElements = [];
  };

  const resetCheckpointFeedback = () => {
    if (!checkpointFeedback) { return; }
    checkpointFeedback.textContent = '';
    checkpointFeedback.className = 'small mt-2 d-none';
  };

  const pauseAfterCheckpointSuccess = () => new Promise((resolve) => {
    window.setTimeout(resolve, 900);
  });

  const getCheckpointAnswer = () => {
    if (!checkpointOptions) { return ''; }
    const selected = checkpointOptions.querySelector('input[name="training_checkpoint_answer"]:checked');
    return selected ? String(selected.value || '') : '';
  };

  const normalizeCheckpoint = (checkpoint) => {
    if (!checkpoint || typeof checkpoint !== 'object') { return null; }
    if (String(checkpoint.type || '') === 'multiple_choice' && Array.isArray(checkpoint.options) && checkpoint.options.length > 0) {
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

  const checkpointForStep = (step) => {
    const checkpoint = normalizeCheckpoint(step && step.checkpoint && typeof step.checkpoint === 'object' ? step.checkpoint : null);
    const hasCheckpointOptions = checkpoint
      && checkpoint.type === 'multiple_choice'
      && Array.isArray(checkpoint.options)
      && checkpoint.options.length > 0;
    if (hasCheckpointOptions) {
      return checkpoint;
    }

    const stepNumber = Number(step && step.number ? step.number : state.current_step || 0);
    const stepTitleText = String(step && step.title ? step.title : '').toLowerCase();
    const stepInstructionText = String(step && step.instruction ? step.instruction : '').toLowerCase();
    if (stepNumber === 4 && (
      String(config.scenarioId || '') === 'workflow_ops_overview'
      || stepTitleText.includes('checkpoint: governance purpose')
      || stepInstructionText.includes('which answer best explains why keeping workflow operations inside cbms improves project governance')
    )) {
      return config.workflowOverviewCheckpoint || checkpoint;
    }

    return checkpoint;
  };

  const renderCheckpoint = (checkpoint) => {
    activeCheckpoint = normalizeCheckpoint(checkpoint);
    resetCheckpointFeedback();

    if (!checkpointWrap || !checkpointQuestion) { return; }

    const question = activeCheckpoint ? String(activeCheckpoint.question || '').trim() : '';
    if (question === '') {
      checkpointQuestion.textContent = '';
      if (checkpointOptions) {
        checkpointOptions.innerHTML = '';
        checkpointOptions.classList.add('d-none');
      }
      checkpointWrap.classList.add('d-none');
      return;
    }

    const label = checkpointWrap.querySelector('.small.text-uppercase');
    if (label) {
      label.textContent = activeCheckpoint.required ? 'Checkpoint - Required' : 'Checkpoint';
    }
    checkpointQuestion.textContent = question;

    if (checkpointOptions) {
      checkpointOptions.innerHTML = '';
      const options = Array.isArray(activeCheckpoint.options) ? activeCheckpoint.options : [];
      if (String(activeCheckpoint.type || '') === 'multiple_choice' && options.length > 0) {
        options.forEach((option, index) => {
          const key = String(option.key || '').trim();
          const optionLabel = String(option.label || '').trim();
          if (key === '' || optionLabel === '') { return; }

          const optionId = `training-checkpoint-option-${index}`;
          const wrap = document.createElement('div');
          wrap.className = 'form-check mt-1';

          const input = document.createElement('input');
          input.type = 'radio';
          input.className = 'form-check-input';
          input.id = optionId;
          input.name = 'training_checkpoint_answer';
          input.value = key;
          input.addEventListener('change', resetCheckpointFeedback);

          const labelEl = document.createElement('label');
          labelEl.className = 'form-check-label small';
          labelEl.setAttribute('for', optionId);
          labelEl.textContent = optionLabel;

          wrap.appendChild(input);
          wrap.appendChild(labelEl);
          checkpointOptions.appendChild(wrap);
        });
        checkpointOptions.classList.remove('d-none');
      } else {
        checkpointOptions.classList.add('d-none');
      }
    }

    checkpointWrap.classList.remove('d-none');
  };

  const validateCheckpointBeforeContinue = () => {
    if (!activeCheckpoint || String(activeCheckpoint.type || '') !== 'multiple_choice') {
      return true;
    }

    const answer = getCheckpointAnswer();
    if (answer === '') {
      if (checkpointFeedback) {
        checkpointFeedback.textContent = activeCheckpoint.required
          ? 'Select an answer before continuing.'
          : 'Select an answer or continue after reviewing the checkpoint.';
        checkpointFeedback.className = 'small mt-2 text-danger';
      }
      return !activeCheckpoint.required;
    }

    const correct = String(activeCheckpoint.correct || '').trim();
    if (correct !== '' && answer !== correct) {
      if (checkpointFeedback) {
        checkpointFeedback.textContent = 'That answer is not quite right. Review the choices and try again.';
        checkpointFeedback.className = 'small mt-2 text-danger';
      }
      return false;
    }

    if (checkpointFeedback) {
      checkpointFeedback.textContent = 'Correct.';
      checkpointFeedback.className = 'small mt-2 text-success';
    }
    return true;
  };

  const highlightElement = (step) => {
    clearHighlights();
    if (!step || !step.target) { return; }

    const target = document.getElementById(String(step.target));
    if (!target) { return; }

    activeElements.push(target);
    target.classList.add('training-demo-highlight', 'training-demo-pulse');

    if (target.id) {
      const label = document.querySelector(`label[for="${target.id}"]`);
      if (label) {
        label.classList.add('training-demo-highlight-label');
        activeElements.push(label);
      }
    }

    target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
  };

  const updatePanel = () => {
    const step = getCurrentStep();
    const isCompleted = String(state.status || '') === 'completed';

    if (stepProgress) {
      stepProgress.textContent = isCompleted
        ? '<?= addslashes(__t('training_status_completed')) ?>'
        : `<?= addslashes(__t('training_step_prefix')) ?> ${state.current_step} <?= addslashes(__t('training_of_label')) ?> ${state.total_steps}`;
    }

    if (stopButton) {
      stopButton.className = `btn btn-sm ${isCompleted ? 'btn-outline-secondary' : 'btn-outline-danger'}`;
      stopButton.lastChild.textContent = isCompleted
        ? '<?= addslashes(__t('training_finish_scenario')) ?>'
        : '<?= addslashes(__t('training_leave_scenario')) ?>';
    }
    if (stopButtonIcon) {
      stopButtonIcon.className = `bi ${isCompleted ? 'bi-check2-circle' : 'bi-x-circle'} me-1`;
    }

    if (isCompleted) {
      if (stepTitle) { stepTitle.textContent = '<?= addslashes(__t('training_scenario_complete_title')) ?>'; }
      if (stepInstruction) { stepInstruction.textContent = '<?= addslashes(__t('training_overlay_complete_message')) ?>'; }
      renderCheckpoint(null);
      if (sampleWrap) { sampleWrap.classList.add('d-none'); }
      if (continueBtn) {
        continueBtn.classList.add('d-none');
        continueBtn.onclick = null;
      }
      if (stuckBtn) {
        stuckBtn.classList.add('d-none');
        stuckBtn.onclick = null;
      }
      clearHighlights();
      return;
    }

    if (!step) {
      return;
    }

    if (stepTitle) { stepTitle.textContent = step.title || ''; }
    if (stepInstruction) { stepInstruction.textContent = step.instruction || ''; }
    renderCheckpoint(checkpointForStep(step));

    const currentSample = step.sample_key && state.samples ? String(state.samples[step.sample_key] || '') : '';
    if (sampleWrap && sampleValue) {
      if (currentSample !== '') {
        sampleValue.textContent = currentSample;
        sampleWrap.classList.remove('d-none');
      } else {
        sampleWrap.classList.add('d-none');
      }
    }

    if (continueBtn) {
      const needsManualContinue = String(step.completion_mode || '') === 'manual_continue';
      continueBtn.classList.toggle('d-none', !needsManualContinue);
      continueBtn.onclick = needsManualContinue
        ? async () => {
            if (!validateCheckpointBeforeContinue()) { return; }
            if (activeCheckpoint && String(activeCheckpoint.type || '') === 'multiple_choice') {
              continueBtn.disabled = true;
              await pauseAfterCheckpointSuccess();
              continueBtn.disabled = false;
            }
            postStepComplete(Number(step.number || 0));
          }
        : null;
    }

    if (stuckBtn) {
      stuckBtn.classList.remove('d-none');
      stuckBtn.onclick = () => postStuckEvent(step);
    }

    highlightElement(step);
    bindStepHandlers(step);
  };

  const postStuckEvent = async (step) => {
    if (!stuckBtn || !step) { return; }
    const previousHtml = stuckBtn.innerHTML;
    stuckBtn.disabled = true;
    stuckBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Recording';

    try {
      const body = new URLSearchParams();
      body.set('_csrf', String(config.csrf || ''));
      body.set('scenario_id', String(config.scenarioId || ''));
      body.set('step_number', String(step.number || state.current_step || ''));
      body.set('route', String(step.route || ''));
      body.set('target', String(step.target || ''));
      body.set('message', 'Learner requested help from the training overlay.');

      const response = await fetch(String(config.stuckUrl || ''), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        throw new Error(payload && payload.message ? String(payload.message) : 'Request failed');
      }
      stuckBtn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Recorded';
      window.setTimeout(() => {
        if (!stuckBtn) { return; }
        stuckBtn.innerHTML = previousHtml;
        stuckBtn.disabled = false;
      }, 1800);
    } catch (error) {
      console.error('Training stuck event failed.', error);
      stuckBtn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Try again';
      window.setTimeout(() => {
        if (!stuckBtn) { return; }
        stuckBtn.innerHTML = previousHtml;
        stuckBtn.disabled = false;
      }, 2200);
    }
  };

  const postStepComplete = async (stepNumber, options = {}) => {
    if (pending) { return null; }
    pending = true;

    try {
      const body = new URLSearchParams();
      body.set('_csrf', String(config.csrf || ''));
      body.set('scenario_id', String(config.scenarioId || ''));
      body.set('step_number', String(stepNumber));
      const checkpointAnswer = getCheckpointAnswer();
      if (checkpointAnswer !== '') {
        body.set('checkpoint_answer', checkpointAnswer);
      }

      const response = await fetch(String(config.completeUrl || ''), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: body.toString(),
      });

      const payload = await response.json();
      if (!response.ok || !payload.ok) {
        console.warn('Training step completion failed.', payload);
        return null;
      }

      state = payload.state || state;
      if (payload.completed) {
        updatePanel();
        return payload;
      }
      if (!options.skipPanelUpdate) {
        updatePanel();
      }
      return payload;
    } catch (error) {
      console.error('Training step completion request failed.', error);
      return null;
    } finally {
      pending = false;
    }
  };

  const isPlainPrimaryClick = (event) => {
    return event.button === 0 && !event.altKey && !event.ctrlKey && !event.metaKey && !event.shiftKey;
  };

  const findNavigationAnchor = (element) => {
    const source = element && typeof element.closest === 'function' ? element : null;
    const anchor = source ? source.closest('a[href]') : null;
    if (!anchor) { return null; }

    const href = String(anchor.getAttribute('href') || '').trim();
    if (href === '' || href === '#' || href.charAt(0) === '#' || href.toLowerCase().startsWith('javascript:')) {
      return null;
    }
    if ((anchor.getAttribute('target') || '').trim() !== '') {
      return null;
    }
    if (anchor.hasAttribute('data-bs-toggle') || anchor.hasAttribute('data-toggle')) {
      return null;
    }

    return anchor;
  };

  const findSubmitControl = (element) => {
    const source = element && typeof element.closest === 'function' ? element : null;
    const control = source ? source.closest('button,input') : null;
    if (!control) { return null; }

    const tagName = String(control.tagName || '').toLowerCase();
    const type = String(control.getAttribute('type') || (tagName === 'button' ? 'submit' : '')).toLowerCase();
    if (type !== 'submit') { return null; }

    const form = control.form || (typeof control.closest === 'function' ? control.closest('form') : null);
    return form ? { control, form } : null;
  };

  const resumeSubmit = (submitDetails) => {
    if (!submitDetails || !submitDetails.form) { return; }

    if (typeof submitDetails.form.requestSubmit === 'function') {
      submitDetails.form.requestSubmit(submitDetails.control || undefined);
      return;
    }

    submitDetails.form.submit();
  };

  const bindStepHandlers = (step) => {
    const target = step && step.target ? document.getElementById(String(step.target)) : null;
    if (!target) { return; }

    if (target.dataset.trainingBoundStep === String(step.number || '')) {
      return;
    }
    target.dataset.trainingBoundStep = String(step.number || '');

    const completionMode = String(step.completion_mode || '');
    if (completionMode === 'field_nonempty') {
      const maybeAdvance = () => {
        if (String(target.value || '').trim() !== '') {
          postStepComplete(Number(step.number || 0));
        }
      };
      target.addEventListener('input', maybeAdvance);
      target.addEventListener('change', maybeAdvance);
      target.addEventListener('blur', maybeAdvance);
      maybeAdvance();
    }

    if (completionMode === 'field_email') {
      const maybeAdvance = () => {
        const value = String(target.value || '').trim();
        if (value !== '' && target.checkValidity()) {
          postStepComplete(Number(step.number || 0));
        }
      };
      target.addEventListener('input', maybeAdvance);
      target.addEventListener('change', maybeAdvance);
      target.addEventListener('blur', maybeAdvance);
      maybeAdvance();
    }

    if (completionMode === 'field_prefilled') {
      const maybeAdvance = () => {
        const value = String(target.value || '').trim();
        if (value !== '') {
          postStepComplete(Number(step.number || 0));
        }
      };
      target.addEventListener('input', maybeAdvance);
      target.addEventListener('change', maybeAdvance);
      target.addEventListener('blur', maybeAdvance);
      window.setTimeout(maybeAdvance, 500);
      maybeAdvance();
    }

    if (completionMode === 'checkbox_checked') {
      const maybeAdvance = () => {
        if (target.checked) {
          postStepComplete(Number(step.number || 0));
        }
      };
      target.addEventListener('change', maybeAdvance);
      target.addEventListener('input', maybeAdvance);
      maybeAdvance();
    }

    if (completionMode === 'field_matches_sample') {
      const expectedSample = step.sample_key && state.samples ? String(state.samples[step.sample_key] || '').trim() : '';
      const maybeAdvance = () => {
        if (expectedSample !== '' && String(target.value || '').trim() === expectedSample) {
          postStepComplete(Number(step.number || 0));
        }
      };
      target.addEventListener('input', maybeAdvance);
      target.addEventListener('change', maybeAdvance);
      target.addEventListener('blur', maybeAdvance);
      maybeAdvance();
    }

    if (completionMode === 'click_target') {
      let completingClickTarget = false;
      let completedClickTarget = false;

      const maybeAdvance = async (event) => {
        if (completingClickTarget || completedClickTarget) {
          return;
        }

        const clickSource = event.target && typeof event.target.closest === 'function' ? event.target : target;
        const anchor = findNavigationAnchor(clickSource) || findNavigationAnchor(target);
        const submitDetails = findSubmitControl(clickSource) || findSubmitControl(target);

        if ((!anchor && !submitDetails) || !isPlainPrimaryClick(event)) {
          completingClickTarget = true;
          const payload = await postStepComplete(Number(step.number || 0));
          completedClickTarget = !!payload;
          completingClickTarget = false;
          return;
        }

        event.preventDefault();
        event.stopPropagation();

        completingClickTarget = true;
        const payload = await postStepComplete(Number(step.number || 0), { deferCompletedReload: true });
        completingClickTarget = false;
        if (!payload) { return; }

        completedClickTarget = true;

        if (anchor) {
          window.location.assign(anchor.href);
          return;
        }

        resumeSubmit(submitDetails);
      };
      target.addEventListener('click', maybeAdvance);
    }
  };

  const clampPanelPosition = () => {
    const rect = panel.getBoundingClientRect();
    const maxLeft = Math.max(8, window.innerWidth - rect.width - 8);
    const maxTop = Math.max(8, window.innerHeight - rect.height - 8);
    const left = Math.min(Math.max(rect.left, 8), maxLeft);
    const top = Math.min(Math.max(rect.top, 8), maxTop);
    panel.style.left = `${left}px`;
    panel.style.top = `${top}px`;
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
  };

  const startDrag = (clientX, clientY) => {
    const rect = panel.getBoundingClientRect();
    dragState = {
      offsetX: clientX - rect.left,
      offsetY: clientY - rect.top,
    };
    panel.style.left = `${rect.left}px`;
    panel.style.top = `${rect.top}px`;
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
  };

  if (header) {
    header.addEventListener('mousedown', (event) => {
      if (event.target && event.target.closest('button')) {
        return;
      }
      startDrag(event.clientX, event.clientY);
      event.preventDefault();
    });
  }

  document.addEventListener('mousemove', (event) => {
    if (!dragState) { return; }
    panel.style.left = `${event.clientX - dragState.offsetX}px`;
    panel.style.top = `${event.clientY - dragState.offsetY}px`;
    panel.style.right = 'auto';
    panel.style.bottom = 'auto';
    clampPanelPosition();
  });

  document.addEventListener('mouseup', () => {
    dragState = null;
  });

  if (collapseBtn) {
    collapseBtn.addEventListener('click', () => {
      panel.classList.toggle('is-collapsed');
      const icon = collapseBtn.querySelector('i');
      if (icon) {
        icon.className = panel.classList.contains('is-collapsed') ? 'bi bi-arrows-angle-expand' : 'bi bi-dash-lg';
      }
    });
  }

  window.addEventListener('resize', clampPanelPosition);

  if (!config.isCompleted) {
    updatePanel();
  }
});
</script>
