<?php
declare(strict_types=1);

$helpEyebrow = (string)($helpEyebrow ?? 'Screen Help');
$title = (string)($title ?? 'Help');
$icon = (string)($icon ?? 'bi-question-circle');
$intro = (string)($intro ?? '');
$sections = is_array($sections ?? null) ? $sections : [];
$note = (string)($note ?? '');

$sectionIconFor = static function (string $heading): string {
    $key = strtolower($heading);
    $has = static function (string $needle) use ($key): bool {
        return strpos($key, $needle) !== false;
    };
    if ($has('tab')) {
        return 'bi-layout-three-columns';
    }
    if ($has('field')) {
        return 'bi-input-cursor-text';
    }
    if ($has('filter')) {
        return 'bi-funnel';
    }
    if ($has('permission') || $has('deletion')) {
        return 'bi-shield-lock';
    }
    if ($has('workflow') || $has('practice') || $has('pattern')) {
        return 'bi-arrow-repeat';
    }
    if ($has('traceability') || $has('related')) {
        return 'bi-diagram-3';
    }
    if ($has('gap')) {
        return 'bi-exclamation-diamond';
    }
    if ($has('type')) {
        return 'bi-tags';
    }
    if ($has('action')) {
        return 'bi-lightning-charge';
    }
    return 'bi-check2-circle';
};
?>

<style>
  .screen-help {
    color: #212529;
  }
  .screen-help-header {
    display: flex;
    gap: .75rem;
    align-items: flex-start;
    padding-bottom: .85rem;
    border-bottom: 1px solid #dee2e6;
  }
  .screen-help-header-icon,
  .screen-help-section-icon,
  .screen-help-bullet {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
  }
  .screen-help-header-icon {
    width: 2.25rem;
    height: 2.25rem;
    border: 1px solid #cfe2ff;
    border-radius: .5rem;
    color: #0d6efd;
    background: #f8fbff;
    font-size: 1.15rem;
  }
  .screen-help-section {
    display: grid;
    grid-template-columns: 2rem minmax(0, 1fr);
    gap: .65rem;
    padding: 1rem 0;
    border-bottom: 1px solid #f1f3f5;
  }
  .screen-help-section:last-of-type {
    border-bottom: 0;
  }
  .screen-help-section-title {
    margin-bottom: .35rem;
  }
  .screen-help-section-icon {
    width: 2rem;
    height: 2rem;
    color: #495057;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: .45rem;
  }
  .screen-help-list {
    list-style: none;
    padding-left: 0;
    margin-bottom: 0;
  }
  .screen-help-list li {
    display: flex;
    gap: .45rem;
    align-items: flex-start;
    margin-bottom: .4rem;
  }
  .screen-help-bullet {
    width: 1rem;
    height: 1rem;
    margin-top: .2rem;
    color: #6c757d;
    font-size: .78rem;
  }
  .screen-help-note {
    display: flex;
    gap: .5rem;
    align-items: flex-start;
    padding: .75rem;
    border: 1px solid #dee2e6;
    border-left: .25rem solid #0d6efd;
    background: #fff;
  }
  .screen-help-note i {
    color: #0d6efd;
    margin-top: .1rem;
  }
</style>

<div class="help-content screen-help p-3">
  <div class="screen-help-header mb-2">
    <div class="screen-help-header-icon">
      <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
    </div>
    <div>
      <div class="small text-uppercase text-muted fw-semibold mb-1"><?= htmlspecialchars($helpEyebrow, ENT_QUOTES, 'UTF-8') ?></div>
      <h5 class="mb-1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h5>
      <?php if ($intro !== ''): ?>
        <p class="text-muted mb-0"><?= htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <?php foreach ($sections as $section): ?>
    <?php
      $heading = (string)($section['heading'] ?? '');
      $sectionIcon = (string)($section['icon'] ?? $sectionIconFor($heading));
      $items = is_array($section['items'] ?? null) ? $section['items'] : [];
    ?>
    <div class="screen-help-section">
      <span class="screen-help-section-icon"><i class="bi <?= htmlspecialchars($sectionIcon, ENT_QUOTES, 'UTF-8') ?>"></i></span>
      <div>
        <?php if ($heading !== ''): ?>
          <h6 class="screen-help-section-title"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h6>
        <?php endif; ?>
        <?php if ($items !== []): ?>
          <ul class="screen-help-list">
            <?php foreach ($items as $item): ?>
              <li><span class="screen-help-bullet"><i class="bi bi-dot"></i></span><span><?= $item ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($note !== ''): ?>
    <div class="screen-help-note small mt-2">
      <i class="bi bi-info-circle"></i>
      <div><?= $note ?></div>
    </div>
  <?php endif; ?>
</div>
