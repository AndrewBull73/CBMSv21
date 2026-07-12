<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$model = is_array($model ?? null) ? $model : [];
$featureColumns = (string) ($featureColumns ?? '');
$isEdit = (int) ($model['MLModelID'] ?? 0) > 0;
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i><?= $isEdit ? 'Edit ML Model' : 'Register ML Model' ?></h3>
        <div class="small text-muted mt-1">Define approved source data, target, features, and governance status.</div>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/ml-models">Model Register</a>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <form method="post" action="index.php?route=intelligence/save-ml-model">
          <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
          <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">Model Code</label>
              <input class="form-control" name="ModelCode" required value="<?= h((string) ($model['ModelCode'] ?? '')) ?>" placeholder="BUDGET_EXECUTION_RISK">
            </div>
            <div class="col-md-8">
              <label class="form-label">Model Name</label>
              <input class="form-control" name="ModelName" required value="<?= h((string) ($model['ModelName'] ?? '')) ?>" placeholder="Budget Execution Risk Model">
            </div>
            <div class="col-md-4">
              <label class="form-label">Use Case</label>
              <select class="form-select" name="UseCaseCode" required>
                <?php
                  $useCases = ['BUDGET_EXECUTION_RISK', 'EXPENDITURE_FORECAST', 'REVENUE_FORECAST', 'ANOMALY_DETECTION', 'CASH_FLOW_FORECAST'];
                  $selectedUseCase = (string) ($model['UseCaseCode'] ?? 'BUDGET_EXECUTION_RISK');
                  foreach ($useCases as $code):
                ?>
                  <option value="<?= h($code) ?>" <?= $selectedUseCase === $code ? 'selected' : '' ?>><?= h($code) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Model Type</label>
              <select class="form-select" name="ModelTypeCode" required>
                <?php
                  $types = ['REGRESSION', 'CLASSIFICATION', 'TIME_SERIES', 'ANOMALY_DETECTION', 'RULE_ASSISTED'];
                  $selectedType = (string) ($model['ModelTypeCode'] ?? 'REGRESSION');
                  foreach ($types as $code):
                ?>
                  <option value="<?= h($code) ?>" <?= $selectedType === $code ? 'selected' : '' ?>><?= h($code) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Status</label>
              <select class="form-select" name="StatusCode">
                <?php
                  $statuses = ['DRAFT', 'READY', 'TRAINING', 'TRAINED', 'CHANGES_REQUESTED', 'REVIEWED', 'APPROVED', 'RETIRED'];
                  $selectedStatus = (string) ($model['StatusCode'] ?? 'DRAFT');
                  foreach ($statuses as $code):
                ?>
                  <option value="<?= h($code) ?>" <?= $selectedStatus === $code ? 'selected' : '' ?>><?= h($code) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Approved View/Table</label>
              <input class="form-control" name="ApprovedViewName" value="<?= h((string) ($model['ApprovedViewName'] ?? '')) ?>" placeholder="dbo.vwAI_BudgetLedgerAnalysis">
            </div>
            <div class="col-md-4">
              <label class="form-label">Target Column</label>
              <input class="form-control" name="TargetColumnName" value="<?= h((string) ($model['TargetColumnName'] ?? '')) ?>" placeholder="ExecutionRate">
            </div>
            <div class="col-md-2">
              <label class="form-label">Accuracy</label>
              <input class="form-control" name="AccuracyScore" inputmode="decimal" value="<?= h((string) ($model['AccuracyScore'] ?? '')) ?>" placeholder="0.8500">
            </div>
            <div class="col-12">
              <label class="form-label">Feature Columns</label>
              <textarea class="form-control font-monospace" name="FeatureColumns" rows="6" placeholder="One column per line"><?= h($featureColumns) ?></textarea>
              <div class="form-text">Use approved dataset/view column names only. This register stores the metadata; training execution is controlled separately.</div>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="ActiveFlag" id="ActiveFlag" value="1" <?= (int) ($model['ActiveFlag'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="ActiveFlag">Active</label>
              </div>
            </div>
          </div>
          <div class="mt-4 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Model</button>
            <a class="btn btn-outline-secondary" href="index.php?route=intelligence/ml-models">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
