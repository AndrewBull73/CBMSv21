<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int) ($record['SectorID'] ?? 0);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><?= $id > 0 ? 'Edit Sector' : 'Create Sector' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=strategy-setup/save-sector">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="SectorID" value="<?= $id ?>">

        <div class="mb-3">
          <label class="form-label">Sector Name</label>
          <input type="text" name="SectorName" class="form-control" required value="<?= h((string) ($record['SectorName'] ?? '')) ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Description</label>
          <textarea name="SectorDescription" class="form-control" rows="4"><?= h((string) ($record['SectorDescription'] ?? '')) ?></textarea>
        </div>

        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" name="ActiveFlag" id="sectorActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="sectorActiveFlag">Active</label>
        </div>

        <div class="d-flex justify-content-between">
          <a href="index.php?route=strategy-setup/sectors" class="btn btn-secondary">Back</a>
          <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
