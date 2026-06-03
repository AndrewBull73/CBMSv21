<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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

$overlayPayload = [
    'completeUrl' => (string) ($trainingGuide['completeUrl'] ?? ''),
    'csrf' => (string) ($trainingGuide['csrf'] ?? ''),
    'isCompleted' => $isCompleted,
    'state' => $trainingState,
    'steps' => $steps,
    'scenarioTitle' => (string) ($scenario['title'] ?? __t('training_scenario_title_default')),
    'scenarioId' => (string) ($trainingState['scenario_id'] ?? ''),
    'runnerUrl' => (string) ($trainingGuide['runnerUrl'] ?? 'index.php?route=training/scenarios'),
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
        <div id="training-sample-wrap" class="small mb-3<?= $sampleValue === '' ? ' d-none' : '' ?>">
          <span class="text-muted"><?= __t('training_suggested_value') ?>:</span>
          <code id="training-sample-value"><?= h($sampleValue) ?></code>
        </div>
      <?php endif; ?>

      <div class="d-flex gap-2">
        <button type="button" id="training-step-continue" class="btn btn-sm btn-warning d-none">
          <i class="bi bi-arrow-right-circle me-1"></i><?= __t('training_continue') ?>
        </button>
        <form method="post" action="<?= h((string) ($trainingGuide['stopUrl'] ?? 'index.php?route=training/stop')) ?>" class="d-inline">
          <input type="hidden" name="_csrf" value="<?= h((string) ($trainingGuide['csrf'] ?? '')) ?>">
          <input type="hidden" name="scenario_id" value="<?= h((string) ($trainingState['scenario_id'] ?? '')) ?>">
          <input type="hidden" name="return" value="<?= h((string) ($trainingGuide['runnerUrl'] ?? 'index.php?route=training/scenarios')) ?>">
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
  const stepProgress = document.getElementById('training-step-progress');
  const sampleWrap = document.getElementById('training-sample-wrap');
  const sampleValue = document.getElementById('training-sample-value');
  const continueBtn = document.getElementById('training-step-continue');
  const stopForm = panel.querySelector('form');
  const stopButton = stopForm ? stopForm.querySelector('button[type="submit"]') : null;
  const stopButtonIcon = stopButton ? stopButton.querySelector('i') : null;

  let activeElements = [];
  let pending = false;
  let state = config.state || {};
  const steps = Array.isArray(config.steps) ? config.steps : [];
  let dragState = null;

  const getCurrentStep = () => {
    const currentNumber = Number(state.current_step || 0);
    return steps.find(step => Number(step.number || 0) === currentNumber) || null;
  };

  const clearHighlights = () => {
    activeElements.forEach(el => el.classList.remove('training-demo-highlight', 'training-demo-pulse', 'training-demo-highlight-label'));
    activeElements = [];
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
      if (sampleWrap) { sampleWrap.classList.add('d-none'); }
      if (continueBtn) {
        continueBtn.classList.add('d-none');
        continueBtn.onclick = null;
      }
      clearHighlights();
      return;
    }

    if (!step) {
      return;
    }

    if (stepTitle) { stepTitle.textContent = step.title || ''; }
    if (stepInstruction) { stepInstruction.textContent = step.instruction || ''; }

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
        ? () => postStepComplete(Number(step.number || 0))
        : null;
    }

    highlightElement(step);
    bindStepHandlers(step);
  };

  const postStepComplete = async (stepNumber) => {
    if (pending) { return; }
    pending = true;

    try {
      const body = new URLSearchParams();
      body.set('_csrf', String(config.csrf || ''));
      body.set('scenario_id', String(config.scenarioId || ''));
      body.set('step_number', String(stepNumber));

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
        return;
      }

      state = payload.state || state;
      if (payload.completed) {
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.set('training_scenario_id', String(config.scenarioId || ''));
        window.location.replace(nextUrl.toString());
        return;
      }
      updatePanel();
    } catch (error) {
      console.error('Training step completion request failed.', error);
    } finally {
      pending = false;
    }
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
      const maybeAdvance = () => {
        postStepComplete(Number(step.number || 0));
      };
      target.addEventListener('click', maybeAdvance, { once: true });
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
