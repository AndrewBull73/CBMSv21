<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

/** @var array $calcModel */
/** @var array|null $node */
/** @var array|null $formula */
/** @var array $dependencies */
/** @var array $nodeTypeOptions */
/** @var array $nodeCategoryOptions */
/** @var array $dataTypeOptions */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$id = (int) ($node['NodeID'] ?? 0);
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><i class="bi bi-bezier2 me-2"></i><?= $id > 0 ? 'Edit Node' : 'Create Node' ?></strong>
      <div class="small text-muted"><?= h((string) $calcModel['ModelCode']) ?> - <?= h((string) $calcModel['ModelName']) ?></div>
    </div>
    <a href="index.php?route=scenario-admin/detail&id=<?= (int) $calcModel['CalcModelID'] ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back
    </a>
  </div>

  <div class="card-body">
    <form method="post" action="index.php?route=scenario-admin/saveNode">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="CalcModelID" value="<?= (int) $calcModel['CalcModelID'] ?>">
      <?php if ($id > 0): ?>
        <input type="hidden" name="NodeID" value="<?= $id ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Node Code</label>
          <input type="text" name="NodeCode" class="form-control" required value="<?= h((string) ($node['NodeCode'] ?? '')) ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Node Name</label>
          <input type="text" name="NodeName" class="form-control" required value="<?= h((string) ($node['NodeName'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Node Type</label>
          <select name="NodeTypeCode" class="form-select">
            <?php foreach ($nodeTypeOptions as $option): ?>
              <option value="<?= h($option) ?>" <?= (($node['NodeTypeCode'] ?? 'INPUT') === $option) ? 'selected' : '' ?>><?= h($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label">Node Order</label>
          <input type="number" min="1" name="NodeOrder" class="form-control" value="<?= (int) ($node['NodeOrder'] ?? 100) ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Category</label>
          <select name="NodeCategoryCode" class="form-select">
            <?php foreach ($nodeCategoryOptions as $option): ?>
              <option value="<?= h($option) ?>" <?= (($node['NodeCategoryCode'] ?? 'GENERAL') === $option) ? 'selected' : '' ?>><?= h($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Data Type</label>
          <select name="DataTypeCode" class="form-select">
            <?php foreach ($dataTypeOptions as $option): ?>
              <option value="<?= h($option) ?>" <?= (($node['DataTypeCode'] ?? 'DECIMAL') === $option) ? 'selected' : '' ?>><?= h($option) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Unit of Measure</label>
          <input type="text" name="UnitOfMeasureCode" class="form-control" value="<?= h((string) ($node['UnitOfMeasureCode'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Decimal Scale</label>
          <input type="number" min="0" max="6" name="DecimalScale" class="form-control" value="<?= (int) ($node['DecimalScale'] ?? 6) ?>">
        </div>

        <div class="col-md-4">
          <label class="form-label">Default Decimal Value</label>
          <input type="number" step="0.000001" name="DefaultDecimalValue" class="form-control" value="<?= h((string) ($node['DefaultDecimalValue'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Default Text Value</label>
          <input type="text" name="DefaultTextValue" class="form-control" value="<?= h((string) ($node['DefaultTextValue'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Default Boolean Value</label>
          <select name="DefaultBitValue" class="form-select">
            <option value="">None</option>
            <option value="1" <?= ((string) ($node['DefaultBitValue'] ?? '') === '1') ? 'selected' : '' ?>>True</option>
            <option value="0" <?= ((string) ($node['DefaultBitValue'] ?? '') === '0') ? 'selected' : '' ?>>False</option>
          </select>
        </div>

        <div class="col-12">
          <label class="form-label">Formula Expression</label>
          <textarea name="ExpressionText" rows="5" class="form-control" placeholder="@DriverA@ * @DriverB@"><?= h((string) ($formula['ExpressionText'] ?? '')) ?></textarea>
          <div class="form-text">Leave blank for pure input or metadata-only nodes. Use `@Token@` references for formula-based nodes.</div>
        </div>

        <div class="col-md-6 d-flex align-items-end">
          <div class="form-check me-3">
            <input type="checkbox" class="form-check-input" id="FormulaActiveFlag" name="FormulaActiveFlag" value="1" <?= ((int) ($formula['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="FormulaActiveFlag">Formula Active</label>
          </div>
          <div class="form-check me-3">
            <input type="checkbox" class="form-check-input" id="OutputFlag" name="OutputFlag" value="1" <?= ((int) ($node['OutputFlag'] ?? 0) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="OutputFlag">Output Node</label>
          </div>
          <div class="form-check">
            <input type="checkbox" class="form-check-input" id="ActiveFlag" name="ActiveFlag" value="1" <?= ((int) ($node['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ActiveFlag">Active</label>
          </div>
        </div>
      </div>

      <hr class="my-4">
      <div class="d-flex justify-content-between align-items-center">
        <div class="small text-muted">Dependencies are read-only in this first admin slice and still managed directly in SQL.</div>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i>Save Node
        </button>
      </div>
    </form>

    <?php if ($id > 0): ?>
      <hr class="my-4">
      <h5 class="mb-3">Current Dependencies</h5>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Depends On</th>
              <th>Type</th>
              <th class="text-end">Offset</th>
              <th class="text-end">Sort</th>
              <th>Required</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($dependencies === []): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No dependencies are defined for this node.</td></tr>
          <?php else: ?>
            <?php foreach ($dependencies as $dependency): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= h((string) $dependency['DependsOnNodeCode']) ?></div>
                  <div class="small text-muted"><?= h((string) $dependency['DependsOnNodeName']) ?></div>
                </td>
                <td><?= h((string) $dependency['DependencyTypeCode']) ?></td>
                <td class="text-end"><?= number_format((int) $dependency['OffsetPeriods']) ?></td>
                <td class="text-end"><?= number_format((int) $dependency['SortOrder']) ?></td>
                <td><?= ((int) ($dependency['RequiredFlag'] ?? 0) === 1) ? 'Yes' : 'No' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
