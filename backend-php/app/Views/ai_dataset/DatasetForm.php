<?php
declare(strict_types=1);
require __DIR__ . '/../ai/_helpers.php';
$dataset = is_array($dataset ?? null) ? $dataset : [];
$columns = array_values(is_array($columns ?? null) ? $columns : []);
$field = static fn (string $name, string $default = ''): string => (string) ($dataset[$name] ?? $default);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-database-add me-2"></i><?= $dataset ? 'Edit Analysis Dataset' : 'Register Analysis Dataset' ?></h3>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=ai-dataset/datasets">Datasets</a>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <form method="post" action="index.php?route=ai-dataset/save-dataset">
          <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
          <input type="hidden" name="DatasetID" value="<?= h((string) (int) ($dataset['DatasetID'] ?? 0)) ?>">
          <div class="row g-3">
            <div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="DatasetCode" value="<?= h($field('DatasetCode')) ?>" required></div>
            <div class="col-md-5"><label class="form-label">Name</label><input class="form-control" name="DatasetName" value="<?= h($field('DatasetName')) ?>" required></div>
            <div class="col-md-4"><label class="form-label">Source Object</label><input class="form-control" name="SourceObjectName" value="<?= h($field('SourceObjectName')) ?>" placeholder="dbo.vwExecutiveBudgetAnalysis" required></div>
            <div class="col-md-3"><label class="form-label">Source Type</label><select class="form-select" name="SourceType"><option <?= $field('SourceType', 'VIEW') === 'VIEW' ? 'selected' : '' ?>>VIEW</option><option <?= $field('SourceType') === 'TABLE' ? 'selected' : '' ?>>TABLE</option></select></div>
            <div class="col-md-3"><label class="form-label">Sensitivity</label><select class="form-select" name="SensitivityLevel"><?php foreach (['RESTRICTED','CONFIDENTIAL','EXECUTIVE','INTERNAL'] as $s): ?><option value="<?= h($s) ?>" <?= $field('SensitivityLevel', 'RESTRICTED') === $s ? 'selected' : '' ?>><?= h($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Fiscal Year Column</label><input class="form-control" name="DefaultFiscalYearColumn" value="<?= h($field('DefaultFiscalYearColumn')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Version Column</label><input class="form-control" name="DefaultVersionColumn" value="<?= h($field('DefaultVersionColumn')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Max Rows</label><input class="form-control" name="MaxRows" value="<?= h($field('MaxRows', '100')) ?>"></div>
            <div class="col-md-9"><label class="form-label">Allowed Permission Codes</label><input class="form-control" name="AllowedPermissionCodes" value="<?= h($field('AllowedPermissionCodes', 'ANALYSIS_DATASET_ANALYZE')) ?>"></div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="Description" rows="2"><?= h($field('Description')) ?></textarea></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="Notes" rows="2"><?= h($field('Notes')) ?></textarea></div>
            <div class="col-md-3"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="RequireContext" value="1" id="RequireContext" <?= (int) ($dataset['RequireContext'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="RequireContext">Require fiscal context</label></div></div>
            <div class="col-md-3"><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="IsActive" value="1" id="IsActive" <?= (int) ($dataset['IsActive'] ?? 1) === 1 ? 'checked' : '' ?>><label class="form-check-label" for="IsActive">Active</label></div></div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Dataset</button>
            <a class="btn btn-outline-secondary" href="index.php?route=ai-dataset/datasets">Cancel</a>
          </div>
        </form>

        <?php if ((int) ($dataset['DatasetID'] ?? 0) > 0): ?>
          <hr>
          <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
            <h4 class="h6 mb-0">Approved Columns</h4>
            <form method="post" action="index.php?route=ai-dataset/import-columns">
              <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
              <input type="hidden" name="DatasetID" value="<?= h((string) (int) ($dataset['DatasetID'] ?? 0)) ?>">
              <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Import Columns</button>
            </form>
          </div>
          <form method="post" action="index.php?route=ai-dataset/save-columns">
            <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
            <input type="hidden" name="DatasetID" value="<?= h((string) (int) ($dataset['DatasetID'] ?? 0)) ?>">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle">
              <thead class="table-light"><tr><th>Column</th><th>Semantic</th><th>Usage</th><th>Default</th><th>Description</th></tr></thead>
              <tbody>
              <?php if ($columns === []): ?>
                <tr><td colspan="5" class="text-muted text-center py-3">No columns imported yet.</td></tr>
              <?php else: foreach ($columns as $column): ?>
                <?php $columnId = (int) ($column['DatasetColumnID'] ?? 0); ?>
                <tr>
                  <td style="min-width: 190px;">
                    <input class="form-control form-control-sm mb-1" name="columns[<?= h((string) $columnId) ?>][DisplayName]" value="<?= h((string) ($column['DisplayName'] ?? '')) ?>">
                    <div class="small text-muted"><?= h((string) ($column['ColumnName'] ?? '')) ?> · <?= h((string) ($column['DataType'] ?? '')) ?></div>
                  </td>
                  <td>
                    <select class="form-select form-select-sm" name="columns[<?= h((string) $columnId) ?>][SemanticType]">
                      <?php foreach (['DIMENSION','METRIC','DATE','IDENTIFIER'] as $semantic): ?>
                        <option value="<?= h($semantic) ?>" <?= (string) ($column['SemanticType'] ?? '') === $semantic ? 'selected' : '' ?>><?= h($semantic) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="small" style="min-width: 220px;">
                    <?php foreach ([['IsDimension','Dimension'],['IsMetric','Metric'],['IsFilterable','Filter'],['IsSensitive','Sensitive'],['IsActive','Active']] as $flag): ?>
                      <label class="me-2"><input type="checkbox" name="columns[<?= h((string) $columnId) ?>][<?= h($flag[0]) ?>]" value="1" <?= (int) ($column[$flag[0]] ?? 0) === 1 ? 'checked' : '' ?>> <?= h($flag[1]) ?></label>
                    <?php endforeach; ?>
                  </td>
                  <td style="max-width: 110px;"><input class="form-control form-control-sm" name="columns[<?= h((string) $columnId) ?>][DefaultAggregation]" value="<?= h((string) ($column['DefaultAggregation'] ?? '')) ?>"></td>
                  <td><input class="form-control form-control-sm" name="columns[<?= h((string) $columnId) ?>][Description]" value="<?= h((string) ($column['Description'] ?? '')) ?>"></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($columns !== []): ?>
            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Column Metadata</button>
          <?php endif; ?>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
