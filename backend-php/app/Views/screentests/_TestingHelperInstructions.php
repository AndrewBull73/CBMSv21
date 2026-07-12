<?php
declare(strict_types=1);

$testingHelperTitle = trim((string) ($testingHelperTitle ?? 'Helper Instructions'));
$testingHelperItems = array_values(is_array($testingHelperItems ?? null) ? $testingHelperItems : []);
?>

<?php if ($testingHelperItems !== []): ?>
  <div class="alert alert-light border mb-3">
    <div class="fw-semibold mb-2"><i class="bi bi-info-circle me-1"></i><?= h($testingHelperTitle) ?></div>
    <ul class="mb-0 ps-3 small">
      <?php foreach ($testingHelperItems as $testingHelperItem): ?>
        <li><?= $testingHelperItem ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>
