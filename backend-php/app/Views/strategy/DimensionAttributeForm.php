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
$dataTypes = ['TEXT', 'LONG_TEXT', 'NUMBER', 'DATE', 'BOOLEAN', 'LIST'];
?>
<div class="container mt-4">
  <?php if (!$attributesAvailable): ?>
    <div class="alert alert-warning">Custom attributes are not available until the strategic dimension attribute migration is run.</div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><?= h((string) ($title ?? 'Custom Attribute')) ?></h3>
      <a href="index.php?route=strategy-config/custom-attributes" class="btn btn-sm btn-outline-secondary">Back to List</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Use this form to define one strategic custom attribute, including the target dimension, data type, and display behavior.</div>

      <form method="post" action="index.php?route=strategy-config/save-custom-attribute">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="AttributeID" value="<?= (int) ($record['AttributeID'] ?? 0) ?>">

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Dimension</label>
            <select name="StrategicDimensionCode" class="form-select form-select-sm" required>
              <option value="">Select dimension</option>
              <?php
                $selectedDimensionCode = (string) ($record['StrategicDimensionCode'] ?? $dimensionCode ?? '');
                foreach (($dimensionOptions ?? []) as $option):
                  $code = (string) ($option['Code'] ?? '');
              ?>
                <option value="<?= h($code) ?>" <?= $selectedDimensionCode === $code ? 'selected' : '' ?>><?= h((string) ($option['Label'] ?? $code)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Attribute Code</label>
            <input type="text" name="AttributeCode" class="form-control form-control-sm" required value="<?= h((string) ($record['AttributeCode'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Data Type</label>
            <select name="DataTypeCode" class="form-select form-select-sm" required>
              <?php $selectedType = strtoupper((string) ($record['DataTypeCode'] ?? 'TEXT')); ?>
              <?php foreach ($dataTypes as $type): ?>
                <option value="<?= h($type) ?>" <?= $selectedType === $type ? 'selected' : '' ?>><?= h($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-8">
            <label class="form-label">Attribute Name</label>
            <input type="text" name="AttributeName" class="form-control form-control-sm" required value="<?= h((string) ($record['AttributeName'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Display Order</label>
            <input type="number" name="DisplayOrder" class="form-control form-control-sm" value="<?= h((string) ($record['DisplayOrder'] ?? 0)) ?>">
          </div>
          <div class="col-12">
            <label class="form-label">Help Text</label>
            <textarea name="HelpText" class="form-control form-control-sm" rows="3"><?= h((string) ($record['HelpText'] ?? '')) ?></textarea>
          </div>
          <div class="col-md-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="IsRequired" id="attributeRequiredFlag" <?= (int) ($record['IsRequired'] ?? 0) === 1 ? 'checked' : '' ?>>
              <label class="form-check-label" for="attributeRequiredFlag">Required</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="ActiveFlag" id="attributeActiveFlag" <?= (int) ($record['ActiveFlag'] ?? 1) === 1 ? 'checked' : '' ?>>
              <label class="form-check-label" for="attributeActiveFlag">Active</label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <a href="index.php?route=strategy-config/custom-attributes" class="btn btn-sm btn-outline-secondary">Back</a>
          <button type="submit" class="btn btn-sm btn-primary">Save Attribute</button>
        </div>
      </form>
    </div>
  </div>
</div>
