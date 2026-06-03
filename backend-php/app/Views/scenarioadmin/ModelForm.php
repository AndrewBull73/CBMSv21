<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

/** @var array|null $calcModel */
/** @var array $statusOptions */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int) ($calcModel['CalcModelID'] ?? 0);
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-box me-2"></i><?= $id > 0 ? 'Edit Scenario Model' : 'Create Scenario Model' ?></strong>
    <a href="index.php?route=scenario-admin/<?= $id > 0 ? 'detail&id=' . $id : 'index' ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>

  <div class="card-body">
    <form method="post" action="index.php?route=scenario-admin/saveModel">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <?php if ($id > 0): ?>
        <input type="hidden" name="CalcModelID" value="<?= $id ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Model Code</label>
          <input type="text" name="ModelCode" class="form-control" required value="<?= h((string) ($calcModel['ModelCode'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Model Name</label>
          <input type="text" name="ModelName" class="form-control" required value="<?= h((string) ($calcModel['ModelName'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Version</label>
          <input type="number" min="1" name="ModelVersion" class="form-control" value="<?= (int) ($calcModel['ModelVersion'] ?? 1) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Status</label>
          <select name="StatusCode" class="form-select">
            <?php foreach ($statusOptions as $status): ?>
              <option value="<?= h($status) ?>" <?= (($calcModel['StatusCode'] ?? 'DRAFT') === $status) ? 'selected' : '' ?>><?= h($status) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Effective From</label>
          <input type="date" name="EffectiveFrom" class="form-control" value="<?= h((string) ($calcModel['EffectiveFrom'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Effective To</label>
          <input type="date" name="EffectiveTo" class="form-control" value="<?= h((string) ($calcModel['EffectiveTo'] ?? '')) ?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="ActiveFlag" name="ActiveFlag" value="1" <?= ((int) ($calcModel['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ActiveFlag">Active</label>
          </div>
        </div>
      </div>

      <hr class="my-4">
      <div class="d-flex justify-content-between align-items-center">
        <div class="small text-muted">This defines the root metadata and lifecycle for a scenario model.</div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Save Model
        </button>
      </div>
    </form>
  </div>
</div>
