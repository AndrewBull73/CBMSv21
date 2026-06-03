<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$record = $record ?? [];
?>
<div class="container mt-4">
  <?php if (!$attributesAvailable): ?>
    <div class="alert alert-warning">Custom attributes are not available until the strategic dimension attribute migration is run.</div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><?= h((string) ($title ?? 'Custom Attribute Option')) ?></h3>
      <a href="index.php?route=strategy-config/custom-attribute-options&attribute_id=<?= (int) ($attribute['AttributeID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">Back to Options</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Use this form to maintain one selectable option for a LIST-type strategic custom attribute.</div>

      <form method="post" action="index.php?route=strategy-config/save-custom-attribute-option">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="AttributeOptionID" value="<?= (int) ($record['AttributeOptionID'] ?? 0) ?>">
        <input type="hidden" name="AttributeID" value="<?= (int) ($attribute['AttributeID'] ?? $record['AttributeID'] ?? 0) ?>">

        <div class="mb-3">
          <label class="form-label">Attribute</label>
          <input type="text" class="form-control form-control-sm" readonly value="<?= $attribute ? h((string) ($attribute['AttributeName'] ?? '')) . ' (' . h((string) ($attribute['AttributeCode'] ?? '')) . ')' : '' ?>">
        </div>

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Option Code</label>
            <input type="text" name="OptionCode" class="form-control form-control-sm" required value="<?= h((string) ($record['OptionCode'] ?? '')) ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label">Option Label</label>
            <input type="text" name="OptionLabel" class="form-control form-control-sm" required value="<?= h((string) ($record['OptionLabel'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Display Order</label>
            <input type="number" name="DisplayOrder" class="form-control form-control-sm" value="<?= h((string) ($record['DisplayOrder'] ?? 0)) ?>">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="attributeOptionActiveFlag" <?= (int) ($record['ActiveFlag'] ?? 1) === 1 ? 'checked' : '' ?>>
              <label class="form-check-label" for="attributeOptionActiveFlag">Active</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="index.php?route=strategy-config/custom-attribute-options&attribute_id=<?= (int) ($attribute['AttributeID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">Back</a>
          <button type="submit" class="btn btn-sm btn-primary">Save Option</button>
        </div>
      </form>
    </div>
  </div>
</div>
