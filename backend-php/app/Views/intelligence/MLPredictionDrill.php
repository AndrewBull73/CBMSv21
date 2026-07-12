<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';

$model = is_array($model ?? null) ? $model : [];
$prediction = is_array($prediction ?? null) ? $prediction : [];
$drillRows = array_values(is_array($drillRows ?? null) ? $drillRows : []);
$underlyingRows = array_values(is_array($underlyingRows ?? null) ? $underlyingRows : []);
$workflowEvents = array_values(is_array($workflowEvents ?? null) ? $workflowEvents : []);
$csrf = (string) ($csrf ?? '');
$predictionJson = json_decode((string) ($prediction['PredictionJson'] ?? ''), true);
$predictionJson = is_array($predictionJson) ? $predictionJson : [];
$rowContext = is_array($predictionJson['row_context'] ?? null) ? $predictionJson['row_context'] : [];

function ml_drill_value(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (is_numeric($value)) {
        $number = (float) $value;
        return abs($number) >= 1000 || str_contains((string) $value, '.') ? number_format($number, 2) : (string) $value;
    }
    return (string) $value;
}

function ml_drill_rows_table(array $rows): void
{
    if ($rows === []) {
        echo '<div class="alert alert-info mb-0">No indexed drill rows have been stored for this prediction yet. Regenerate predictions after installing the drill snapshot table.</div>';
        return;
    }
    $columns = [
        'FiscalYearID',
        'BudgetVersionID',
        'PeriodNo',
        'Segment1',
        'ProgramCode',
        'EconomicCode',
        'CurrencyCode',
        'BudgetAmount',
        'ActualAmount',
        'AvailableBalance',
        'CumulativeExecutionRate',
        'ExpectedExecutionRate',
        'VarianceAmount',
        'RiskScore',
        'RiskLabel',
        'AnomalyTypeCode',
        'RiskReason',
    ];
    echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">';
    echo '<thead class="table-light"><tr>';
    foreach ($columns as $column) {
        echo '<th>' . h($column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            $class = is_numeric($value) ? ' class="text-end"' : '';
            echo '<td' . $class . '>' . h(ml_drill_value($value)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function ml_underlying_rows_table(array $rows): void
{
    if ($rows === []) {
        echo '<div class="alert alert-info mb-0">No underlying ledger records were found for this budget line.</div>';
        return;
    }
    $columns = [
        'BudgetLedgerAnalysisID',
        'SourceRowReference',
        'FiscalYearID',
        'BudgetVersionID',
        'PeriodNo',
        'Segment1',
        'ProgramCode',
        'EconomicCode',
        'CurrencyCode',
        'BudgetAmount',
        'ActualAmount',
        'AvailableBalance',
        'PostingDate',
    ];
    echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">';
    echo '<thead class="table-light"><tr>';
    foreach ($columns as $column) {
        echo '<th>' . h($column) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            $class = is_numeric($value) ? ' class="text-end"' : '';
            echo '<td' . $class . '>' . h(ml_drill_value($value)) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

?>
<div class="container mt-4">
  <div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-search me-2"></i>Prediction Drill Through</h3>
        <div class="small text-muted mt-1"><?= h((string) ($model['ModelName'] ?? 'ML Model')) ?></div>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/ml-model-detail&id=<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">Back to Model</a>
    </div>
    <div class="card-body">
      <div class="row row-cols-1 row-cols-md-4 g-3">
        <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Budget Line</div><div class="fw-semibold"><?= h((string) ($prediction['EntityCode'] ?? '')) ?></div></div></div>
        <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Predicted Risk</div><div class="fw-semibold"><?= h($prediction['PredictionValue'] === null ? '' : number_format((float) $prediction['PredictionValue'], 2)) ?></div></div></div>
        <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Reference Risk</div><div class="fw-semibold"><?= h($prediction['RiskScore'] === null ? '' : number_format((float) $prediction['RiskScore'], 2)) ?></div></div></div>
        <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Created</div><div class="fw-semibold"><?= h((string) ($prediction['CreatedDate'] ?? '')) ?></div></div></div>
      </div>
      <?php if ($predictionJson !== []): ?>
        <div class="mt-3 small text-muted">
          <?= h((string) ($predictionJson['interpretation'] ?? '')) ?>
          <?php if ((string) ($predictionJson['selected_feature'] ?? '') !== ''): ?>
            &middot; Driver: <?= h((string) ($predictionJson['selected_feature'] ?? '')) ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header"><h4 class="h6 mb-0"><i class="bi bi-kanban me-2"></i>Prediction Workflow</h4></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-lg-5">
          <form method="post" action="index.php?route=intelligence/ml-prediction-workflow-action" class="border rounded p-3 bg-white h-100">
            <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">
            <input type="hidden" name="MLPredictionID" value="<?= h((string) (int) ($prediction['MLPredictionID'] ?? 0)) ?>">
            <label class="form-label small" for="mlPredictionWorkflowAction">Review Action</label>
            <select class="form-select form-select-sm" id="mlPredictionWorkflowAction" name="ActionCode">
              <option value="MARK_PREDICTION_REVIEWED">Mark prediction reviewed</option>
              <option value="ACCEPT_AS_RISK">Accept as risk item</option>
              <option value="DISMISS_PREDICTION">Dismiss after review</option>
              <option value="REFER_FOR_FOLLOW_UP">Refer for follow-up</option>
            </select>
            <label class="form-label small mt-3" for="mlPredictionWorkflowNotes">Review Notes</label>
            <textarea class="form-control form-control-sm" id="mlPredictionWorkflowNotes" name="Notes" rows="4" placeholder="Record what was checked and the decision made."></textarea>
            <button class="btn btn-sm btn-primary mt-3" type="submit"><i class="bi bi-check2-square me-1"></i>Record Review</button>
          </form>
        </div>
        <div class="col-lg-7">
          <div class="border rounded p-3 bg-white h-100">
            <h5 class="h6">Prediction Review History</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Action</th><th>Notes</th></tr></thead>
                <tbody>
                  <?php if ($workflowEvents === []): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No review history has been recorded for this prediction.</td></tr>
                  <?php else: foreach ($workflowEvents as $event): ?>
                    <tr>
                      <td class="small"><?= h((string) ($event['CreatedDate'] ?? '')) ?></td>
                      <td><span class="badge text-bg-light border"><?= h((string) ($event['ActionCode'] ?? '')) ?></span></td>
                      <td class="small"><?= h((string) ($event['Notes'] ?? '')) ?></td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($rowContext !== []): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header"><h4 class="h6 mb-0">Stored Prediction Context</h4></div>
      <div class="card-body">
        <div class="small text-muted mb-2">Captured when predictions were generated. This opens quickly and avoids scanning the full ledger.</div>
        <div class="row row-cols-1 row-cols-md-4 g-3">
          <?php foreach ($rowContext as $key => $value): ?>
            <div class="col">
              <div class="border rounded p-3 bg-white h-100">
                <div class="small text-muted"><?= h((string) $key) ?></div>
                <div class="fw-semibold text-break"><?= h(ml_drill_value($value)) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-header"><h4 class="h6 mb-0">Underlying Ledger Records</h4></div>
    <div class="card-body">
      <div class="small text-muted mb-2">Actual rows from <code>dbo.tblAIBudgetLedgerAnalysisSource</code> for this budget line.</div>
      <?php ml_underlying_rows_table($underlyingRows); ?>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header"><h4 class="h6 mb-0">Indexed Drill Snapshot</h4></div>
    <div class="card-body">
      <div class="small text-muted mb-2">Rows captured when predictions were generated. This avoids querying the large ledger view live.</div>
      <?php ml_drill_rows_table($drillRows); ?>
    </div>
  </div>

  <?php if ($predictionJson !== []): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header"><h4 class="h6 mb-0">Raw Prediction Details</h4></div>
      <div class="card-body">
        <pre class="small bg-light border rounded p-2 mb-0 text-break"><?= h(json_encode($predictionJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '') ?></pre>
      </div>
    </div>
  <?php endif; ?>
</div>
