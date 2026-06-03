<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/training_features.php';
require_once __DIR__ . '/../../../shared/testing_features.php';
require_once __DIR__ . '/../../Core/Rbac.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$currentRoute = (string) ($_GET['route'] ?? '');
$testingEnabled = screen_testing_features_enabled($GLOBALS['conn'] ?? null);
$trainingEnabled = training_features_enabled($GLOBALS['conn'] ?? null);
$isAdmin = (new \App\Core\Rbac($GLOBALS['conn'] ?? null))->canAny(['ADMIN_ALL', 'SYSADMIN']);
$groups = require __DIR__ . '/../../../config/quick_links.php';

$filterLinks = static function (array $links) use ($testingEnabled, $trainingEnabled, $isAdmin): array {
    $filtered = [];
    foreach ($links as $link) {
        if (!empty($link['requiresTesting']) && !$testingEnabled) {
            continue;
        }
        if (!empty($link['requiresTraining']) && !$trainingEnabled) {
            continue;
        }
        if (!empty($link['requiresAdmin']) && !$isAdmin) {
            continue;
        }
        $filtered[] = $link;
    }
    return $filtered;
};

$activeGroup = null;
foreach ($groups as $group) {
    foreach (($group['patterns'] ?? []) as $pattern) {
        $isPrefix = str_ends_with((string) $pattern, '/');
        if (($isPrefix && str_starts_with($currentRoute, (string) $pattern)) || (!$isPrefix && $currentRoute === (string) $pattern)) {
            $group['links'] = $filterLinks(is_array($group['links'] ?? null) ? $group['links'] : []);
            if ($group['links'] !== []) {
                $activeGroup = $group;
            }
            break 2;
        }
    }
}

if ($activeGroup === null) {
    return;
}
?>
<div class="container-fluid mt-3">
  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-3">
      <div class="strategy-quick-nav-group">
        <div class="small text-uppercase text-muted fw-semibold mb-2">
          <?= h('Quick Links') ?>: <?= h((string) ($activeGroup['title'] ?? 'Quick Links')) ?>
          <?php if (!empty($activeGroup['code'])): ?>
            <span class="badge text-bg-light border ms-2"><?= h((string) $activeGroup['code']) ?></span>
          <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2" data-quick-nav-code="<?= h((string) ($activeGroup['code'] ?? 'GEN')) ?>">
          <?php foreach (($activeGroup['links'] ?? []) as $link): ?>
            <?php $isActive = $currentRoute === (string) ($link['route'] ?? ''); ?>
            <a
              href="index.php?route=<?= urlencode((string) ($link['route'] ?? '')) ?>"
              class="btn btn-sm d-inline-flex align-items-center gap-2 <?= $isActive ? 'btn-primary' : 'btn-outline-secondary' ?>"
              title="<?= h((string) (((string) ($link['code'] ?? '')) . ' - ' . ((string) ($link['label'] ?? '')))) ?>"
            >
              <?php if (!empty($link['code'])): ?>
                <span class="badge <?= $isActive ? 'text-bg-light text-dark' : 'text-bg-light' ?> border"><?= h((string) $link['code']) ?></span>
              <?php endif; ?>
              <?= h((string) ($link['label'] ?? '')) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
