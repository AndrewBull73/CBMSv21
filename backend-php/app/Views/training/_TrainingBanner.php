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
$step = is_array($trainingGuide['step'] ?? null) ? $trainingGuide['step'] : null;
$isCompleted = (bool) ($trainingGuide['isCompleted'] ?? false);
$runnerUrl = (string) ($trainingGuide['runnerUrl'] ?? 'index.php?route=training/scenarios');
$scenarioTitle = (string) ($scenario['title'] ?? 'Training Scenario');
$currentStep = (int) ($trainingState['current_step'] ?? 0);
$totalSteps = (int) ($trainingState['total_steps'] ?? 0);
?>

<div class="container mt-4 mb-3">
  <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3 shadow-sm">
    <div>
      <div class="fw-semibold">
        <i class="bi bi-mortarboard me-2"></i><?= h($scenarioTitle) ?>
      </div>
      <?php if ($isCompleted): ?>
        <div class="small"><?= __t('training_banner_complete') ?></div>
      <?php else: ?>
        <div class="small">
          <?= __t('training_step_of', ['current' => (string) $currentStep, 'total' => (string) $totalSteps]) ?>:
          <strong><?= h((string) ($step['title'] ?? 'Current step')) ?></strong>
        </div>
      <?php endif; ?>
    </div>
    <a href="<?= h($runnerUrl) ?>" class="btn btn-sm btn-outline-dark">
      <i class="bi bi-layout-text-sidebar-reverse me-1"></i><?= __t('training_view_runner') ?>
    </a>
  </div>
</div>
