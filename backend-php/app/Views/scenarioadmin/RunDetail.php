<?php
declare(strict_types=1);

/** @var array $calcModel */
/** @var array $run */
/** @var array $errors */
/** @var array $results */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$calcModelId = (int) ($calcModel['CalcModelID'] ?? 0);
$resultCount = (int) ($run['ResultRowCount'] ?? 0);
$errorCount = (int) ($run['ErrorCount'] ?? count($errors));
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><i class="bi bi-clipboard-data me-2"></i>Scenario Run <?= h((string) ($run['CalcRunID'] ?? '')) ?></strong>
      <div class="small text-muted">
        <?= h((string) ($calcModel['ModelCode'] ?? '')) ?> /
        <?= h((string) ($run['ScenarioCode'] ?? '')) ?>
      </div>
    </div>
    <div class="btn-group btn-group-sm">
      <a href="index.php?route=scenario-admin/detail&id=<?= $calcModelId ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Model
      </a>
    </div>
  </div>

  <div class="card-body">
    <div class="row g-3 mb-4">
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Status</div><div class="fs-6 fw-semibold"><?= h((string) ($run['RunStatusCode'] ?? '')) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Run Type</div><div class="fs-6 fw-semibold"><?= h((string) ($run['RunTypeCode'] ?? '')) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Results</div><div class="fs-6 fw-semibold"><?= number_format($resultCount) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Errors</div><div class="fs-6 fw-semibold"><?= number_format($errorCount) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Started</div><div class="small fw-semibold"><?= h((string) ($run['StartedDate'] ?? '')) ?></div></div></div>
      <div class="col-md-2"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Completed</div><div class="small fw-semibold"><?= h((string) ($run['CompletedDate'] ?? '')) ?></div></div></div>
    </div>

    <?php if (trim((string) ($run['Notes'] ?? '')) !== ''): ?>
      <div class="alert alert-secondary py-2">
        <strong>Notes:</strong> <?= h((string) $run['Notes']) ?>
      </div>
    <?php endif; ?>

    <h5 class="mb-3">Run Errors</h5>
    <div class="table-responsive mb-4">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Severity</th>
            <th>Error</th>
            <th>Node</th>
            <th>Period</th>
            <th>Cost Object</th>
            <th>Message</th>
            <th>Created</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($errors === []): ?>
          <tr><td colspan="7" class="text-center text-muted py-3">No run errors were recorded.</td></tr>
        <?php else: ?>
          <?php foreach ($errors as $error): ?>
            <tr>
              <td><span class="badge text-bg-light"><?= h((string) ($error['ErrorSeverityCode'] ?? '')) ?></span></td>
              <td>
                <div class="fw-semibold"><?= h((string) ($error['ErrorCode'] ?? '')) ?></div>
                <?php if (trim((string) ($error['ExpressionText'] ?? '')) !== ''): ?>
                  <div class="small text-muted"><code><?= h((string) $error['ExpressionText']) ?></code></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= h((string) ($error['NodeCode'] ?? '')) ?></div>
                <div class="small text-muted"><?= h((string) ($error['NodeName'] ?? '')) ?></div>
              </td>
              <td><?= h((string) ($error['PeriodCode'] ?? '')) ?></td>
              <td>
                <div class="fw-semibold"><?= h((string) ($error['CostObjectCode'] ?? '')) ?></div>
                <div class="small text-muted"><?= h((string) ($error['CostObjectName'] ?? '')) ?></div>
              </td>
              <td>
                <div><?= h((string) ($error['ErrorMessage'] ?? '')) ?></div>
                <?php if (trim((string) ($error['ContextJson'] ?? '')) !== ''): ?>
                  <details class="mt-1">
                    <summary class="small text-muted">Context</summary>
                    <pre class="small mb-0 mt-1"><?= h((string) $error['ContextJson']) ?></pre>
                  </details>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?= h((string) ($error['CreatedDate'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <h5 class="mb-3">Sample Results</h5>
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Node</th>
            <th>Period</th>
            <th>Cost Object</th>
            <th class="text-end">Value</th>
            <th>Status</th>
            <th>Calculated</th>
          </tr>
        </thead>
        <tbody>
        <?php if ($results === []): ?>
          <tr><td colspan="6" class="text-center text-muted py-3">No result rows were recorded for this run.</td></tr>
        <?php else: ?>
          <?php foreach ($results as $result): ?>
            <?php
              $value = $result['ValueDecimal'];
              if ($value === null || $value === '') {
                  $value = $result['ValueText'];
              }
              if (($value === null || $value === '') && $result['ValueBit'] !== null && $result['ValueBit'] !== '') {
                  $value = ((int) $result['ValueBit'] === 1) ? 'True' : 'False';
              }
            ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= h((string) ($result['NodeCode'] ?? '')) ?></div>
                <div class="small text-muted"><?= h((string) ($result['NodeName'] ?? '')) ?></div>
              </td>
              <td><?= h((string) ($result['PeriodCode'] ?? '')) ?></td>
              <td>
                <div class="fw-semibold"><?= h((string) ($result['CostObjectCode'] ?? '')) ?></div>
                <div class="small text-muted"><?= h((string) ($result['CostObjectName'] ?? '')) ?></div>
              </td>
              <td class="text-end"><?= h((string) $value) ?></td>
              <td><span class="badge text-bg-light"><?= h((string) ($result['CalculationStatusCode'] ?? '')) ?></span></td>
              <td class="small text-muted"><?= h((string) ($result['CalculatedDate'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
