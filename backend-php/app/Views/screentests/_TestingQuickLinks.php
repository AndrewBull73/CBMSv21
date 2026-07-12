<?php
declare(strict_types=1);

$testingQuickLinksMode = (string) ($testingQuickLinksMode ?? 'tester');
$testingQuickLinks = [
    ['label' => 'My Test Scripts', 'route' => 'screen-tests/my-scripts', 'icon' => 'person-check', 'mode' => 'tester'],
    ['label' => 'All Test Scripts', 'route' => 'screen-tests/scenarios&view=all', 'icon' => 'list-check', 'mode' => 'tester'],
    ['label' => 'Test Results', 'route' => 'screen-tests/summary', 'icon' => 'table', 'mode' => 'tester'],
    ['label' => 'Testing Summary', 'route' => 'screen-tests-admin/summary', 'icon' => 'bar-chart-line', 'mode' => 'admin'],
    ['label' => 'Assign Scripts', 'route' => 'screen-tests-admin/assignments', 'icon' => 'person-plus', 'mode' => 'admin'],
    ['label' => 'Catalogue', 'route' => 'screen-tests-admin/scenarios', 'icon' => 'journal-text', 'mode' => 'admin'],
];
?>

<div class="d-flex gap-2 flex-wrap mb-3">
  <?php foreach ($testingQuickLinks as $testingQuickLink): ?>
    <?php
    $linkMode = (string) ($testingQuickLink['mode'] ?? 'tester');
    if ($testingQuickLinksMode === 'tester' && $linkMode === 'admin') {
        continue;
    }
    ?>
    <a href="index.php?route=<?= h((string) ($testingQuickLink['route'] ?? 'screen-tests/my-scripts')) ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-<?= h((string) ($testingQuickLink['icon'] ?? 'link-45deg')) ?> me-1"></i><?= h((string) ($testingQuickLink['label'] ?? 'Link')) ?>
    </a>
  <?php endforeach; ?>
</div>
