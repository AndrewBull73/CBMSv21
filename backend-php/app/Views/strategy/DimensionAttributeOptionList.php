<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<div class="container mt-4">
  <?php if (!$attributesAvailable): ?>
    <div class="alert alert-warning">Custom attributes are not available until the strategic dimension attribute migration is run.</div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0">Custom Attribute Options</h3>
      </div>
      <div class="d-flex gap-2">
        <a href="index.php?route=strategy-config/custom-attributes" class="btn btn-sm btn-outline-secondary">Back to Attributes</a>
        <?php if ($attribute): ?>
          <a href="index.php?route=strategy-config/custom-attribute-option-form&attribute_id=<?= (int) $attribute['AttributeID'] ?>" class="btn btn-sm btn-primary">Create Option</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">
        <?= $attribute ? h((string) ($attribute['AttributeName'] ?? '')) . ' (' . h((string) ($attribute['AttributeCode'] ?? '')) . ')' : 'Select a LIST attribute first.' ?>
      </div>

      <?php if (!$attribute): ?>
        <div class="alert alert-info mb-0">This screen is only used for attributes with data type <code>LIST</code>.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-striped table-hover table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Code</th>
                <th>Label</th>
                <th>Order</th>
                <th>Status</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (($records ?? []) as $row): ?>
                <tr>
                  <td><?= h((string) ($row['OptionCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['OptionLabel'] ?? '')) ?></td>
                  <td><?= (int) ($row['DisplayOrder'] ?? 0) ?></td>
                <td><?= (int) ($row['ActiveFlag'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></td>
                <td class="text-end">
                    <a href="index.php?route=strategy-config/custom-attribute-option-form&id=<?= (int) $row['AttributeOptionID'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="post" action="index.php?route=strategy-config/delete-custom-attribute-option" class="d-inline">
                      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                      <input type="hidden" name="id" value="<?= (int) $row['AttributeOptionID'] ?>">
                      <input type="hidden" name="attribute_id" value="<?= (int) $attribute['AttributeID'] ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Archive this option?')">Archive</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($records)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No options defined.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
