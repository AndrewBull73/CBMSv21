<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

/** @var array|null $record */
/** @var array|null $flash */

if (!function_exists('h')) {
    function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = h(csrf_token());
$id   = $record['ExampleID'] ?? 0;
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0">
        <i class="bi bi-box me-2"></i>
        <?= $id > 0 ? __t('edit_example') : __t('create_example') ?>
      </h3>
    </div>
    <div class="card-body">

      <!-- Flash -->
      <?php if ($flash): ?>
        <div class="alert alert-<?= h($flash['type'] ?? 'info') ?> alert-dismissible fade show mb-3" role="alert">
          <?= h($flash['text'] ?? '') ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=example/save">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <input type="hidden" name="ExampleID" value="<?= h((string)$id) ?>">

        <div class="mb-3">
          <label class="form-label"><?= __t('name') ?></label>
          <input type="text" name="Name" value="<?= h($record['Name'] ?? '') ?>" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label"><?= __t('description') ?></label>
          <textarea name="Description" class="form-control" rows="3"><?= h($record['Description'] ?? '') ?></textarea>
        </div>

        <div class="d-flex justify-content-between">
          <a href="index.php?route=example/list" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> <?= __t('back') ?>
          </a>
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> <?= __t('save') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
