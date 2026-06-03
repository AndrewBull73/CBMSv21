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
      <h3 class="mb-0">Strategic Custom Attributes</h3>
      <a href="index.php?route=strategy-config/custom-attribute-form<?= $dimensionCode !== '' ? '&dimension_code=' . urlencode((string) $dimensionCode) : '' ?>" class="btn btn-sm btn-primary">Create Attribute</a>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">Define extra client-specific fields for strategic dimensions without altering the core tables.</div>

      <form method="get" class="row g-3 mb-3">
        <input type="hidden" name="route" value="strategy-config/custom-attributes">
        <div class="col-md-4">
          <label class="form-label">Dimension</label>
          <select name="dimension_code" class="form-select form-select-sm">
            <option value="">All dimensions</option>
            <?php foreach (($dimensionOptions ?? []) as $option): ?>
              <?php $code = (string) ($option['Code'] ?? ''); ?>
              <option value="<?= h($code) ?>" <?= $dimensionCode === $code ? 'selected' : '' ?>><?= h((string) ($option['Label'] ?? $code)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Search</label>
          <input type="text" name="q" class="form-control form-control-sm" value="<?= h((string) ($q ?? '')) ?>" placeholder="Code, name, or help text">
        </div>
        <div class="col-md-2 d-flex align-items-end">
          <button type="submit" class="btn btn-sm btn-outline-secondary w-100">Filter</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped table-hover table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Dimension</th>
              <th>Code</th>
              <th>Name</th>
              <th>Type</th>
              <th>Required</th>
              <th>Options</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (($records ?? []) as $row): ?>
              <tr>
                <td><?= h((string) ($row['StrategicDimensionCode'] ?? '')) ?></td>
                <td><?= h((string) ($row['AttributeCode'] ?? '')) ?></td>
                <td>
                  <div><?= h((string) ($row['AttributeName'] ?? '')) ?></div>
                  <?php if (!empty($row['HelpText'])): ?><div class="text-muted small"><?= h((string) $row['HelpText']) ?></div><?php endif; ?>
                </td>
                <td><?= h((string) ($row['DataTypeCode'] ?? '')) ?></td>
                <td><?= (int) ($row['IsRequired'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
                <td><?= (int) ($row['OptionCount'] ?? 0) ?></td>
                <td><?= (int) ($row['ActiveFlag'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></td>
                <td class="text-end">
                  <a href="index.php?route=strategy-config/custom-attribute-form&id=<?= (int) $row['AttributeID'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                  <?php if (strtoupper((string) ($row['DataTypeCode'] ?? '')) === 'LIST'): ?>
                    <a href="index.php?route=strategy-config/custom-attribute-options&attribute_id=<?= (int) $row['AttributeID'] ?>" class="btn btn-sm btn-outline-secondary">Options</a>
                  <?php endif; ?>
                  <form method="post" action="index.php?route=strategy-config/delete-custom-attribute" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int) $row['AttributeID'] ?>">
                    <input type="hidden" name="dimension_code" value="<?= h((string) ($row['StrategicDimensionCode'] ?? '')) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Archive this custom attribute?')">Archive</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($records)): ?>
              <tr><td colspan="8" class="text-center text-muted py-4">No custom attributes found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
