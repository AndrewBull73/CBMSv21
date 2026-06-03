<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$screenHeader = is_array($screenHeader ?? null) ? $screenHeader : [];
$screenHeaderTitleKey = trim((string) ($screenHeader['titleKey'] ?? ''));
$screenHeaderTitle = trim((string) ($screenHeader['title'] ?? ''));
$screenHeaderTitle = $screenHeaderTitleKey !== ''
    ? __t($screenHeaderTitleKey)
    : __t($screenHeaderTitle !== '' ? $screenHeaderTitle : ' ');
$screenHeaderIcon = trim((string) ($screenHeader['icon'] ?? ''));
$screenTestingEnabled = (bool) ($screenTestingEnabled ?? false);
$screenTestLauncher = is_array($screenTestLauncher ?? null) ? $screenTestLauncher : null;
$screenTestTitle = trim((string) ($screenTestLauncher['scenarioTitle'] ?? ''));
$screenTestHint = $screenTestTitle !== ''
    ? __t('screen_tests_open_current_script', ['title' => $screenTestTitle])
    : __t('screen_tests_find_for_current_screen', ['route' => (string) ($screenTestLauncher['route'] ?? '')]);
?>
<div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
  <h3 class="mb-0">
    <?php if ($screenHeaderIcon !== ''): ?>
      <i class="bi <?= h($screenHeaderIcon) ?> me-2"></i>
    <?php endif; ?>
    <?= h($screenHeaderTitle) ?>
  </h3>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <?php if ($screenTestingEnabled && $screenTestLauncher !== null): ?>
      <a
        class="btn btn-sm btn-outline-primary"
        id="screenCardTestBtn"
        href="<?= h((string) ($screenTestLauncher['url'] ?? 'index.php?route=screen-tests/scenarios')) ?>"
        target="_blank"
        rel="noopener noreferrer"
        title="<?= h($screenTestHint) ?>"
      >
        <i class="bi bi-clipboard-check me-1"></i><?= h(__t('screen_tests_title')) ?>
      </a>
    <?php endif; ?>
  </div>
</div>
