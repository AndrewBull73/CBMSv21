<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$model = is_array($model ?? null) ? $model : [];
$featureColumns = array_values(is_array($featureColumns ?? null) ? $featureColumns : []);
$trainingRuns = array_values(is_array($trainingRuns ?? null) ? $trainingRuns : []);
$predictions = array_values(is_array($predictions ?? null) ? $predictions : []);
$interpretations = array_values(is_array($interpretations ?? null) ? $interpretations : []);
$workflowEvents = array_values(is_array($workflowEvents ?? null) ? $workflowEvents : []);
$canAdmin = (bool) ($canAdmin ?? false);
$canApprove = (bool) ($canApprove ?? false);
$canTrain = (bool) ($canTrain ?? false);
$canInterpret = (bool) ($canInterpret ?? false);

$latestCompletedRun = null;
$latestTrainingResult = [];
foreach ($trainingRuns as $run) {
    if ((string) ($run['StatusCode'] ?? '') !== 'COMPLETED') {
        continue;
    }
    $decoded = json_decode((string) ($run['MetricsJson'] ?? ''), true);
    if (is_array($decoded)) {
        $latestCompletedRun = $run;
        $latestTrainingResult = $decoded;
        break;
    }
}

function ml_metric_value(array $metrics, string $key): string
{
    if (!array_key_exists($key, $metrics) || $metrics[$key] === null || $metrics[$key] === '') {
        return '';
    }
    return is_numeric($metrics[$key]) ? number_format((float) $metrics[$key], 4) : (string) $metrics[$key];
}

function ml_json_pretty(array $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
}

function ml_prediction_detail(array $predictionJson, string $key): string
{
    if (!array_key_exists($key, $predictionJson) || $predictionJson[$key] === null || $predictionJson[$key] === '') {
        return '';
    }
    return is_numeric($predictionJson[$key]) ? number_format((float) $predictionJson[$key], 4) : (string) $predictionJson[$key];
}

function ml_prediction_context_value(array $predictionJson, string $key): mixed
{
    if (array_key_exists($key, $predictionJson)) {
        return $predictionJson[$key];
    }
    $context = is_array($predictionJson['row_context'] ?? null) ? $predictionJson['row_context'] : [];
    return $context[$key] ?? null;
}

function ml_prediction_amount(array $predictionJson, string $key): string
{
    $value = ml_prediction_context_value($predictionJson, $key);
    if ($value === null || $value === '') {
        return '';
    }
    return is_numeric($value) ? number_format((float) $value, 2) : (string) $value;
}

function ml_prediction_plain_summary(array $prediction, array $predictionJson): array
{
    $riskLevel = strtoupper((string) ($predictionJson['risk_level'] ?? ''));
    if ($riskLevel === '') {
        $score = $prediction['RiskScore'] ?? null;
        $riskLevel = is_numeric($score) && (float) $score >= 0.35 ? 'HIGH' : (is_numeric($score) && (float) $score >= 0.15 ? 'MEDIUM' : 'LOW');
    }

    $budget = ml_prediction_context_value($predictionJson, 'BudgetAmount');
    $actual = ml_prediction_context_value($predictionJson, 'ActualAmount');
    $cumulativeRate = ml_prediction_context_value($predictionJson, 'CumulativeExecutionRate');
    $expectedRate = ml_prediction_context_value($predictionJson, 'ExpectedExecutionRate');
    $riskReason = trim((string) ml_prediction_context_value($predictionJson, 'RiskReason'));
    $anomalyType = trim((string) ml_prediction_context_value($predictionJson, 'AnomalyTypeCode'));

    $issue = 'Execution pattern needs review.';
    $why = $riskReason !== '' ? $riskReason : (string) ($predictionJson['interpretation'] ?? 'The model identified this item as unusual compared with the trained risk pattern.');
    $action = 'Review the budget ledger detail and confirm whether the coding, budget authority, and actual expenditure are correct.';

    if (is_numeric($budget) && abs((float) $budget) < 0.000001 && is_numeric($actual) && abs((float) $actual) > 0.000001) {
        $issue = 'Actual expenditure exists with no matching budget.';
        $why = 'The selected analysis data shows actual expenditure on this classification, but no baseline budget amount.';
        $action = 'Check the version-role mapping, then confirm whether the expenditure was posted to the correct segment, program, and economic code.';
    } elseif (is_numeric($cumulativeRate) && (float) $cumulativeRate > 100.0) {
        $issue = 'Cumulative execution is above budget.';
        $why = 'Year-to-date actual expenditure is above the available annual budget for this classification.';
        $action = 'Review overspending, virements, supplementary budget entries, and transaction coding.';
    } elseif (is_numeric($cumulativeRate) && is_numeric($expectedRate) && (float) $cumulativeRate < ((float) $expectedRate - 20.0)) {
        $issue = 'Execution is materially behind the expected rate.';
        $why = 'Spending is behind the expected year-to-date execution level.';
        $action = 'Review implementation delays, procurement timing, or whether the budget is no longer required.';
    } elseif ($riskLevel === 'HIGH') {
        $issue = 'High-priority anomaly.';
        $action = 'Open the drill-through rows and review the underlying budget and actual figures before taking action.';
    } elseif ($riskLevel === 'MEDIUM') {
        $issue = 'Moderate execution risk.';
        $action = 'Review if this area is material or sensitive.';
    }

    return [
        'risk_level' => $riskLevel,
        'issue' => $issue,
        'why' => $why,
        'action' => $action,
        'anomaly_type' => $anomalyType,
    ];
}

function ml_prediction_numeric(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }
    return is_numeric($value) ? (float) $value : 0.0;
}

function ml_prediction_budget_line(array $prediction, array $predictionJson): string
{
    $segment1 = trim((string) ml_prediction_context_value($predictionJson, 'Segment1'));
    $programCode = trim((string) ml_prediction_context_value($predictionJson, 'ProgramCode'));
    $economicCode = trim((string) ml_prediction_context_value($predictionJson, 'EconomicCode'));
    if ($segment1 !== '' && $programCode !== '' && $economicCode !== '') {
        return $segment1 . '|' . $programCode . '|' . $economicCode;
    }

    $parts = array_map('trim', explode('|', (string) ($prediction['EntityCode'] ?? '')));
    if (count($parts) >= 5) {
        return $parts[2] . '|' . $parts[3] . '|' . $parts[4];
    }
    if (count($parts) === 3) {
        return implode('|', $parts);
    }
    return (string) ($prediction['EntityCode'] ?? '');
}

function ml_prediction_grouped_rows(array $predictions): array
{
    $groups = [];
    foreach ($predictions as $prediction) {
        $predictionJson = json_decode((string) ($prediction['PredictionJson'] ?? ''), true);
        if (!is_array($predictionJson)) {
            $predictionJson = [];
        }
        $summary = ml_prediction_plain_summary($prediction, $predictionJson);
        $line = ml_prediction_budget_line($prediction, $predictionJson);
        if ($line === '') {
            $line = 'Prediction ' . (string) ($prediction['MLPredictionID'] ?? '');
        }

        $riskLevel = (string) ($summary['risk_level'] ?? '');
        $riskRank = $riskLevel === 'HIGH' ? 3 : ($riskLevel === 'MEDIUM' ? 2 : 1);
        $predictionValue = ml_prediction_numeric($prediction['PredictionValue'] ?? null);
        $riskScore = ml_prediction_numeric($prediction['RiskScore'] ?? null);
        $severity = max($predictionValue, $riskScore);

        if (!isset($groups[$line])) {
            $groups[$line] = [
                'budget_line' => $line,
                'count' => 0,
                'budget_total' => 0.0,
                'actual_total' => 0.0,
                'highest_risk_rank' => 0,
                'highest_severity' => 0.0,
                'representative' => $prediction,
                'representative_json' => $predictionJson,
                'summary' => $summary,
            ];
        }

        $groups[$line]['count']++;
        $groups[$line]['budget_total'] += ml_prediction_numeric(ml_prediction_context_value($predictionJson, 'BudgetAmount'));
        $groups[$line]['actual_total'] += ml_prediction_numeric(ml_prediction_context_value($predictionJson, 'ActualAmount'));

        if ($riskRank > $groups[$line]['highest_risk_rank'] || ($riskRank === $groups[$line]['highest_risk_rank'] && $severity > $groups[$line]['highest_severity'])) {
            $groups[$line]['highest_risk_rank'] = $riskRank;
            $groups[$line]['highest_severity'] = $severity;
            $groups[$line]['representative'] = $prediction;
            $groups[$line]['representative_json'] = $predictionJson;
            $groups[$line]['summary'] = $summary;
        }
    }

    usort($groups, static function (array $a, array $b): int {
        return [$b['highest_risk_rank'], $b['highest_severity'], abs($b['actual_total'])] <=> [$a['highest_risk_rank'], $a['highest_severity'], abs($a['actual_total'])];
    });
    return $groups;
}

$groupedPredictions = ml_prediction_grouped_rows($predictions);

$latestInterpretation = [];
if ($interpretations !== []) {
    $decodedInterpretation = json_decode((string) ($interpretations[0]['ResponseJson'] ?? ''), true);
    $latestInterpretation = is_array($decodedInterpretation) ? $decodedInterpretation : [];
}
?>
<div class="container mt-4">
  <div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i><?= h((string) ($model['ModelName'] ?? 'ML Model')) ?></h3>
        <div class="small text-muted mt-1"><?= h((string) ($model['ModelCode'] ?? '')) ?></div>
      </div>
      <div class="d-inline-flex gap-2">
        <?php if ($canAdmin): ?>
          <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/ml-model-form&id=<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/ml-models">Register</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-md-4 g-3 mb-3">
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Use Case</div><div class="fw-semibold"><?= h((string) ($model['UseCaseCode'] ?? '')) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Type</div><div class="fw-semibold"><?= h((string) ($model['ModelTypeCode'] ?? '')) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Status</div><div><span class="badge text-bg-<?= ((string) ($model['StatusCode'] ?? '')) === 'APPROVED' ? 'success' : 'secondary' ?>"><?= h((string) ($model['StatusCode'] ?? '')) ?></span></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Accuracy</div><div class="fw-semibold"><?= h($model['AccuracyScore'] === null ? '' : number_format((float) $model['AccuracyScore'], 4)) ?></div></div></div>
        </div>
        <ul class="nav nav-tabs mb-3" id="mlModelTabs" role="tablist">
          <li class="nav-item" role="presentation"><button class="nav-link active" type="button" data-ml-tab-target="overview"><i class="bi bi-info-circle me-1"></i>Overview</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-ml-tab-target="predictions"><i class="bi bi-lightning-charge me-1"></i>Predictions</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-ml-tab-target="training"><i class="bi bi-clipboard-data me-1"></i>Training</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-ml-tab-target="workflow"><i class="bi bi-kanban me-1"></i>Workflow</button></li>
          <li class="nav-item" role="presentation"><button class="nav-link" type="button" data-ml-tab-target="interpretation"><i class="bi bi-stars me-1"></i>AI Interpretation</button></li>
        </ul>
        <div class="row g-3" data-ml-tab-panel="overview">
          <div class="col-lg-7">
            <div class="border rounded p-3 bg-white h-100">
              <h4 class="h6">Model Definition</h4>
              <dl class="row mb-0">
                <dt class="col-sm-4">Approved Source</dt><dd class="col-sm-8"><?= h((string) ($model['ApprovedViewName'] ?? '')) ?></dd>
                <dt class="col-sm-4">Target Column</dt><dd class="col-sm-8"><?= h((string) ($model['TargetColumnName'] ?? '')) ?></dd>
                <dt class="col-sm-4">Last Trained</dt><dd class="col-sm-8"><?= h((string) ($model['LastTrainedDate'] ?? '')) ?></dd>
                <dt class="col-sm-4">Approved Date</dt><dd class="col-sm-8"><?= h((string) ($model['ApprovedDate'] ?? '')) ?></dd>
              </dl>
              <div class="small text-muted mt-3 mb-1">Feature Columns</div>
              <?php if ($featureColumns === []): ?>
                <div class="text-muted">No feature columns registered.</div>
              <?php else: ?>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach ($featureColumns as $column): ?>
                    <span class="badge text-bg-light border"><?= h((string) $column) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="border rounded p-3 bg-white h-100">
              <h4 class="h6">Governance Actions</h4>
              <?php if ($canApprove && (string) ($model['StatusCode'] ?? '') !== 'APPROVED'): ?>
                <form method="post" action="index.php?route=intelligence/approve-ml-model" class="mb-3">
                  <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
                  <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">
                  <button class="btn btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Approve Model</button>
                </form>
              <?php endif; ?>
              <?php if ($canTrain): ?>
                <form method="post" action="index.php?route=intelligence/queue-ml-training" class="js-ml-long-action" data-running-label="Training model">
                  <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
                  <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">
                  <div class="row g-2">
                    <div class="col-md-6">
                      <label class="form-label small">Training Start</label>
                      <input class="form-control form-control-sm" type="date" name="TrainingPeriodStart">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small">Training End</label>
                      <input class="form-control form-control-sm" type="date" name="TrainingPeriodEnd">
                    </div>
                  </div>
                  <button class="btn btn-primary btn-sm mt-2" type="submit"><i class="bi bi-play-fill me-1"></i>Run Training</button>
                </form>
                <form method="post" action="index.php?route=intelligence/run-ml-predictions" class="mt-3 js-ml-long-action" data-running-label="Generating predictions">
                  <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
                  <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">
                  <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-magic me-1"></i>Run Predictions</button>
                </form>
                <?php if ($canInterpret): ?>
                  <form method="post" action="index.php?route=intelligence/interpret-ml-results" class="mt-3 js-ml-long-action" data-running-label="Generating AI interpretation">
                    <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
                    <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">
                    <button class="btn btn-outline-dark btn-sm" type="submit"><i class="bi bi-stars me-1"></i>AI Interpretation</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <div class="text-muted">You do not have permission to queue training runs.</div>
              <?php endif; ?>
            </div>
          </div>
          <?php if ((string) ($model['ModelCode'] ?? '') === 'BUDGET_EXECUTION_RISK_V1'): ?>
            <div class="col-12">
              <div class="border rounded p-3 bg-white">
                <h4 class="h6"><i class="bi bi-search me-1"></i>What This Model Looks For</h4>
                <p class="small text-muted mb-2">
                  This model reviews budget execution data and prioritises budget lines that may need analyst review.
                  It is an advisory risk-screening tool, not a final audit finding.
                </p>
                <div class="row g-3">
                  <div class="col-lg-6">
                    <div class="small text-muted mb-1">Current anomaly checks</div>
                    <ul class="small mb-0">
                      <li>Actual expenditure with no matching budget baseline</li>
                      <li>Cumulative expenditure above budget or materially negative available balance</li>
                      <li>Budget lines materially behind expected year-to-date execution</li>
                      <li>Large actual-vs-expected execution variances</li>
                      <li>Material period-to-period spending spikes</li>
                      <li>Sharp changes compared with prior-year execution</li>
                      <li>Dormant budget lines with sudden material activity</li>
                    </ul>
                  </div>
                  <div class="col-lg-6">
                    <div class="small text-muted mb-1">How to use the results</div>
                    <ul class="small mb-0">
                      <li>Use predictions as a prioritised review queue.</li>
                      <li>Open Drill Through to confirm the underlying ledger records.</li>
                      <li>Check version-role mapping before concluding that expenditure is unfunded.</li>
                      <li>Do not treat a high score as proof of fraud or error without analyst review.</li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($latestTrainingResult !== []): ?>
    <?php
      $latestMetrics = is_array($latestTrainingResult['metrics'] ?? null) ? $latestTrainingResult['metrics'] : [];
      $artifact = is_array($latestTrainingResult['model_artifact'] ?? null) ? $latestTrainingResult['model_artifact'] : [];
      $topTerms = array_values(is_array($artifact['top_terms'] ?? null) ? $artifact['top_terms'] : []);
    ?>
    <div class="card shadow-sm mb-3" data-ml-tab-panel="training">
      <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div>
          <h4 class="h6 mb-0"><i class="bi bi-clipboard-data me-2"></i>Latest Training Results</h4>
          <div class="small text-muted mt-1">Run <?= h((string) (int) ($latestCompletedRun['MLTrainingRunID'] ?? 0)) ?> completed <?= h((string) ($latestCompletedRun['CompletedDate'] ?? '')) ?></div>
        </div>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#mlLatestTrainingRaw">
          <i class="bi bi-code-square me-1"></i>Raw Metrics
        </button>
      </div>
      <div class="card-body">
        <div class="row row-cols-1 row-cols-md-4 g-3 mb-3">
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">Accuracy Score</div><div class="fs-5 fw-semibold"><?= h(ml_metric_value($latestMetrics, 'accuracy_score')) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">R Squared</div><div class="fs-5 fw-semibold"><?= h(ml_metric_value($latestMetrics, 'r2')) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">RMSE</div><div class="fs-5 fw-semibold"><?= h(ml_metric_value($latestMetrics, 'rmse')) ?></div></div></div>
          <div class="col"><div class="border rounded p-3 bg-white"><div class="small text-muted">MAE</div><div class="fs-5 fw-semibold"><?= h(ml_metric_value($latestMetrics, 'mae')) ?></div></div></div>
        </div>
        <div class="row g-3">
          <div class="col-md-3"><div class="small text-muted">Algorithm</div><div class="fw-semibold"><?= h((string) ($latestTrainingResult['algorithm'] ?? $artifact['algorithm'] ?? '')) ?></div></div>
          <div class="col-md-3"><div class="small text-muted">Selected Feature</div><div class="fw-semibold"><?= h((string) ($latestTrainingResult['selected_feature'] ?? $artifact['selected_feature'] ?? '')) ?></div></div>
          <div class="col-md-2"><div class="small text-muted">Rows Used</div><div class="fw-semibold"><?= h(number_format((int) ($latestTrainingResult['row_count'] ?? 0))) ?></div></div>
          <div class="col-md-2"><div class="small text-muted">Training Rows</div><div class="fw-semibold"><?= h(number_format((int) ($latestTrainingResult['training_row_count'] ?? 0))) ?></div></div>
          <div class="col-md-2"><div class="small text-muted">Test Rows</div><div class="fw-semibold"><?= h(number_format((int) ($latestTrainingResult['test_row_count'] ?? 0))) ?></div></div>
        </div>
        <?php if ($topTerms !== []): ?>
          <div class="mt-3">
            <div class="small text-muted mb-1">Top Model Terms</div>
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Term</th><th>Source</th><th class="text-end">Weight</th></tr></thead>
                <tbody>
                  <?php foreach (array_slice($topTerms, 0, 8) as $term): ?>
                    <tr>
                      <td><?= h((string) ($term['term'] ?? '')) ?></td>
                      <td><?= h((string) ($term['source'] ?? '')) ?></td>
                      <td class="text-end"><?= h(isset($term['weight']) && is_numeric($term['weight']) ? number_format((float) $term['weight'], 4) : '') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
        <div class="collapse mt-3" id="mlLatestTrainingRaw">
          <pre class="small bg-light border rounded p-2 mb-0 text-break"><?= h(ml_json_pretty($latestTrainingResult)) ?></pre>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-3" data-ml-tab-panel="workflow">
    <div class="card-header">
      <h4 class="h6 mb-0"><i class="bi bi-kanban me-2"></i>ML Workflow</h4>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-lg-5">
          <div class="border rounded p-3 bg-white h-100">
            <div class="small text-muted mb-1">Current Status</div>
            <div class="mb-3"><span class="badge text-bg-<?= ((string) ($model['StatusCode'] ?? '')) === 'APPROVED' ? 'success' : (((string) ($model['StatusCode'] ?? '')) === 'CHANGES_REQUESTED' ? 'warning' : 'secondary') ?>"><?= h((string) ($model['StatusCode'] ?? '')) ?></span></div>
            <div class="small text-muted mb-2">Available Actions</div>
            <?php if ($canAdmin || $canApprove): ?>
              <form method="post" action="index.php?route=intelligence/ml-workflow-action" class="d-grid gap-2">
                <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
                <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">
                <label class="form-label small mb-0" for="mlWorkflowAction">Action</label>
                <select class="form-select form-select-sm" id="mlWorkflowAction" name="ActionCode">
                  <?php if ($canAdmin): ?>
                    <option value="SUBMIT_FOR_REVIEW">Submit for review</option>
                    <option value="MARK_RESULTS_REVIEWED">Mark results reviewed</option>
                    <option value="REOPEN_DRAFT">Reopen as draft</option>
                  <?php endif; ?>
                  <?php if ($canApprove): ?>
                    <option value="REQUEST_CHANGES">Request changes</option>
                    <option value="RETIRE_MODEL">Retire model</option>
                  <?php endif; ?>
                </select>
                <label class="form-label small mb-0" for="mlWorkflowNotes">Notes</label>
                <textarea class="form-control form-control-sm" id="mlWorkflowNotes" name="Notes" rows="4" placeholder="Record the review decision, evidence checked, or follow-up required."></textarea>
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-check2-square me-1"></i>Record Workflow Action</button>
              </form>
              <?php if ($canApprove && (string) ($model['StatusCode'] ?? '') !== 'APPROVED'): ?>
                <form method="post" action="index.php?route=intelligence/approve-ml-model" class="mt-3">
                  <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
                  <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($model['MLModelID'] ?? 0)) ?>">
                  <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Approve Model</button>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <div class="text-muted">You do not have permission to update workflow status.</div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-lg-7">
          <div class="border rounded p-3 bg-white h-100">
            <h5 class="h6">Workflow History</h5>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Action</th><th>Scope</th><th>Status</th><th>Notes</th></tr></thead>
                <tbody>
                  <?php if ($workflowEvents === []): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">No workflow history has been recorded yet.</td></tr>
                  <?php else: foreach ($workflowEvents as $event): ?>
                    <tr>
                      <td class="small"><?= h((string) ($event['CreatedDate'] ?? '')) ?></td>
                      <td><span class="badge text-bg-light border"><?= h((string) ($event['ActionCode'] ?? '')) ?></span></td>
                      <td class="small">
                        <?php if ((int) ($event['MLPredictionID'] ?? 0) > 0): ?>
                          <a href="index.php?route=intelligence/ml-prediction-drill&id=<?= h((string) (int) ($event['MLPredictionID'] ?? 0)) ?>">Prediction <?= h((string) (int) ($event['MLPredictionID'] ?? 0)) ?></a>
                        <?php else: ?>
                          Model
                        <?php endif; ?>
                      </td>
                      <td class="small"><?= h((string) ($event['FromStatusCode'] ?? '')) ?> &rarr; <?= h((string) ($event['ToStatusCode'] ?? '')) ?></td>
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

  <?php if ($latestInterpretation !== []): ?>
    <div class="card shadow-sm mb-3" data-ml-tab-panel="interpretation">
      <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <div>
          <h4 class="h6 mb-0"><i class="bi bi-stars me-2"></i>AI Interpretation</h4>
          <div class="small text-muted mt-1">
            Generated <?= h((string) ($interpretations[0]['CreatedDate'] ?? '')) ?>
            <?php if ((string) ($latestInterpretation['model'] ?? '') !== ''): ?>
              by <?= h((string) ($latestInterpretation['model'] ?? '')) ?>
            <?php endif; ?>
          </div>
        </div>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#mlAIInterpretationRaw">
          <i class="bi bi-code-square me-1"></i>Raw
        </button>
      </div>
      <div class="card-body">
        <div class="border rounded p-3 bg-white" style="white-space: pre-wrap;"><?= h((string) ($latestInterpretation['interpretation'] ?? '')) ?></div>
        <div class="collapse mt-3" id="mlAIInterpretationRaw">
          <pre class="small bg-light border rounded p-2 mb-0 text-break"><?= h(ml_json_pretty($latestInterpretation)) ?></pre>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12" data-ml-tab-panel="training">
      <div class="card shadow-sm h-100">
        <div class="card-header"><h4 class="h6 mb-0">Training Runs</h4></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Run</th><th>Status</th><th>Accuracy</th><th>Algorithm</th><th>Completed</th><th></th></tr></thead>
              <tbody>
                <?php if ($trainingRuns === []): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">No training runs yet.</td></tr>
                <?php else: foreach ($trainingRuns as $run): ?>
                  <?php
                    $runMetricsJson = json_decode((string) ($run['MetricsJson'] ?? ''), true);
                    $runMetrics = is_array($runMetricsJson['metrics'] ?? null) ? $runMetricsJson['metrics'] : [];
                    $runCollapseId = 'mlRunMetrics' . (int) ($run['MLTrainingRunID'] ?? 0);
                  ?>
                  <tr>
                    <td><?= h((string) (int) ($run['MLTrainingRunID'] ?? 0)) ?></td>
                    <td><?= h((string) ($run['StatusCode'] ?? '')) ?></td>
                    <td><?= h(ml_metric_value($runMetrics, 'accuracy_score')) ?></td>
                    <td class="small"><?= h(is_array($runMetricsJson) ? (string) ($runMetricsJson['algorithm'] ?? '') : '') ?></td>
                    <td class="small"><?= h((string) ($run['CompletedDate'] ?? '')) ?></td>
                    <td class="text-end">
                      <?php if (is_array($runMetricsJson)): ?>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= h($runCollapseId) ?>">Metrics</button>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php if (is_array($runMetricsJson)): ?>
                    <tr class="collapse" id="<?= h($runCollapseId) ?>">
                      <td colspan="6">
                        <pre class="small bg-light border rounded p-2 mb-0 text-break"><?= h(ml_json_pretty($runMetricsJson)) ?></pre>
                      </td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12" data-ml-tab-panel="predictions">
      <div class="card shadow-sm h-100">
        <div class="card-header"><h4 class="h6 mb-0">Recent Predictions</h4></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr><th>Risk</th><th>Budget Line</th><th>Why Flagged</th><th class="text-end">Total Budget</th><th class="text-end">Total Actual</th><th>Action</th></tr></thead>
              <tbody>
                <?php if ($groupedPredictions === []): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">No predictions yet.</td></tr>
                <?php else: foreach ($groupedPredictions as $group): ?>
                  <?php
                    $prediction = is_array($group['representative'] ?? null) ? $group['representative'] : [];
                    $predictionJson = is_array($group['representative_json'] ?? null) ? $group['representative_json'] : [];
                    $predictionCollapseId = 'mlPrediction' . (int) ($prediction['MLPredictionID'] ?? 0);
                    $summary = is_array($group['summary'] ?? null) ? $group['summary'] : ['risk_level' => '', 'issue' => '', 'why' => '', 'action' => ''];
                    $riskLevel = (string) ($summary['risk_level'] ?? '');
                    $riskClass = $riskLevel === 'HIGH' ? 'danger' : ($riskLevel === 'MEDIUM' ? 'warning' : 'success');
                  ?>
                  <tr>
                    <td>
                      <?php if ($riskLevel !== ''): ?>
                        <span class="badge text-bg-<?= h($riskClass) ?>"><?= h($riskLevel) ?></span>
                      <?php endif; ?>
                      <div class="small text-muted mt-1">Predicted <?= h($prediction['PredictionValue'] === null ? '' : number_format((float) $prediction['PredictionValue'], 2)) ?></div>
                      <div class="small text-muted">Reference <?= h($prediction['RiskScore'] === null ? '' : number_format((float) $prediction['RiskScore'], 2)) ?></div>
                    </td>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($group['budget_line'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h(number_format((int) ($group['count'] ?? 0))) ?> prediction record(s) consolidated</div>
                    </td>
                    <td class="small">
                      <div class="fw-semibold"><?= h((string) ($summary['issue'] ?? '')) ?></div>
                      <?php if ((string) ($summary['anomaly_type'] ?? '') !== ''): ?>
                        <div class="mb-1"><span class="badge text-bg-light border"><?= h((string) ($summary['anomaly_type'] ?? '')) ?></span></div>
                      <?php endif; ?>
                      <div class="text-muted"><?= h((string) ($summary['why'] ?? '')) ?></div>
                    </td>
                    <td class="text-end"><?= h(number_format((float) ($group['budget_total'] ?? 0), 2)) ?></td>
                    <td class="text-end"><?= h(number_format((float) ($group['actual_total'] ?? 0), 2)) ?></td>
                    <td class="small">
                      <div><?= h((string) ($summary['action'] ?? '')) ?></div>
                      <div class="mt-2 d-flex gap-2 flex-wrap">
                        <a class="btn btn-sm btn-outline-primary" href="index.php?route=intelligence/ml-prediction-drill&id=<?= h((string) (int) ($prediction['MLPredictionID'] ?? 0)) ?>"><i class="bi bi-search me-1"></i>Drill through</a>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?= h($predictionCollapseId) ?>">Raw details</button>
                      </div>
                      <div class="text-muted mt-1">
                        <?= h((string) ($prediction['CreatedDate'] ?? '')) ?>
                        <?php if (is_array($predictionJson) && (string) ($predictionJson['selected_feature'] ?? '') !== ''): ?>
                          &middot; Driver: <?= h((string) ($predictionJson['selected_feature'] ?? '')) ?>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <tr class="collapse" id="<?= h($predictionCollapseId) ?>">
                    <td colspan="6">
                      <div class="small text-muted mb-1">Representative highest-risk prediction from this consolidated budget line.</div>
                      <pre class="small bg-light border rounded p-2 mb-0 text-break"><?= h(ml_json_pretty($predictionJson)) ?></pre>
                    </td>
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

<div class="modal fade" id="mlLongActionModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-4">
        <div class="d-flex align-items-center gap-3">
          <div class="spinner-border text-primary flex-shrink-0" role="status" aria-hidden="true"></div>
          <div>
            <div class="fw-semibold" id="mlLongActionTitle">Processing</div>
            <div class="small text-muted">Elapsed <span id="mlLongActionElapsed">0s</span></div>
          </div>
        </div>
        <div class="progress mt-3" style="height: 6px;" aria-hidden="true">
          <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%;"></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const tabButtons = document.querySelectorAll('[data-ml-tab-target]');
  const tabPanels = document.querySelectorAll('[data-ml-tab-panel]');
  const showMlTab = (target) => {
    tabButtons.forEach((button) => {
      const active = button.dataset.mlTabTarget === target;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    tabPanels.forEach((panel) => {
      panel.classList.toggle('d-none', panel.dataset.mlTabPanel !== target);
    });
  };

  tabButtons.forEach((button) => {
    button.addEventListener('click', () => showMlTab(button.dataset.mlTabTarget || 'overview'));
  });
  showMlTab('overview');

  const forms = document.querySelectorAll('.js-ml-long-action');
  const modalElement = document.getElementById('mlLongActionModal');
  const titleElement = document.getElementById('mlLongActionTitle');
  const elapsedElement = document.getElementById('mlLongActionElapsed');
  let timer = null;

  forms.forEach((form) => {
    form.addEventListener('submit', () => {
      const label = form.dataset.runningLabel || 'Processing';
      const started = Date.now();
      titleElement.textContent = label;
      elapsedElement.textContent = '0s';

      document.querySelectorAll('.js-ml-long-action button[type="submit"]').forEach((button) => {
        button.disabled = true;
        button.dataset.originalHtml = button.innerHTML;
      });

      const submitButton = form.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + label;
      }

      timer = window.setInterval(() => {
        const seconds = Math.max(0, Math.floor((Date.now() - started) / 1000));
        const minutes = Math.floor(seconds / 60);
        const remainder = seconds % 60;
        elapsedElement.textContent = minutes > 0 ? `${minutes}m ${remainder}s` : `${seconds}s`;
      }, 1000);

      if (window.bootstrap && modalElement) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
      }
    }, { once: true });
  });

  window.addEventListener('pageshow', () => {
    if (timer) {
      window.clearInterval(timer);
    }
  });
});
</script>
