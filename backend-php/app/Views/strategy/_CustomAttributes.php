<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$customAttributeFields = is_array($customAttributeFields ?? null) ? $customAttributeFields : [];
if ($customAttributeFields === []) {
    return;
}
?>
<div class="col-12">
  <hr class="my-2">
  <h5 class="mb-3">Custom Attributes</h5>
  <div class="row g-3">
    <?php foreach ($customAttributeFields as $field): ?>
      <?php
        $attributeId = (int) ($field['AttributeID'] ?? 0);
        $type = strtoupper((string) ($field['DataTypeCode'] ?? 'TEXT'));
        $label = (string) ($field['AttributeName'] ?? '');
        $helpText = (string) ($field['HelpText'] ?? '');
        $required = (int) ($field['IsRequired'] ?? 0) === 1;
        $currentValue = $field['CurrentValue'] ?? null;
        $inputName = 'custom_attributes[' . $attributeId . ']';
      ?>
      <div class="col-md-<?= $type === 'LONG_TEXT' ? '12' : '6' ?>">
        <label class="form-label"><?= h($label) ?><?= $required ? ' *' : '' ?></label>
        <?php if ($type === 'LONG_TEXT'): ?>
          <textarea name="<?= h($inputName) ?>" class="form-control" rows="4" <?= $required ? 'required' : '' ?>><?= h((string) ($currentValue ?? '')) ?></textarea>
        <?php elseif ($type === 'NUMBER'): ?>
          <input type="number" step="any" name="<?= h($inputName) ?>" class="form-control" value="<?= h((string) ($currentValue ?? '')) ?>" <?= $required ? 'required' : '' ?>>
        <?php elseif ($type === 'DATE'): ?>
          <input type="date" name="<?= h($inputName) ?>" class="form-control" value="<?= h((string) ($currentValue ?? '')) ?>" <?= $required ? 'required' : '' ?>>
        <?php elseif ($type === 'BOOLEAN'): ?>
          <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="<?= h($inputName) ?>" id="attr<?= $attributeId ?>" value="1" <?= (int) ($currentValue ?? 0) === 1 ? 'checked' : '' ?>>
            <label class="form-check-label" for="attr<?= $attributeId ?>">Yes</label>
          </div>
        <?php elseif ($type === 'LIST'): ?>
          <select name="<?= h($inputName) ?>" class="form-select" <?= $required ? 'required' : '' ?>>
            <option value="">Select option</option>
            <?php foreach (($field['Options'] ?? []) as $option): ?>
              <?php $optionCode = (string) ($option['OptionCode'] ?? ''); ?>
              <option value="<?= h($optionCode) ?>" <?= (string) ($currentValue ?? '') === $optionCode ? 'selected' : '' ?>><?= h((string) ($option['OptionLabel'] ?? $optionCode)) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <input type="text" name="<?= h($inputName) ?>" class="form-control" value="<?= h((string) ($currentValue ?? '')) ?>" <?= $required ? 'required' : '' ?>>
        <?php endif; ?>
        <?php if ($helpText !== ''): ?><div class="form-text"><?= h($helpText) ?></div><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
