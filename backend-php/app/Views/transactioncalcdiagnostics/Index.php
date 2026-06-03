<?php
declare(strict_types=1);

/** @var int $transactionId */
/** @var array|null $inspection */
/** @var bool $notFound */

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('fmtv')) {
    function fmtv(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return (string) $value;
    }
}

if (!function_exists('fmtamt')) {
    function fmtamt(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (!is_numeric($value)) {
            return (string) $value;
        }
        return number_format((float) $value, 2, '.', ',');
    }
}

$transaction = $inspection['transaction'] ?? null;
$calculation = $inspection['calculation'] ?? null;
$bridgeCandidates = $inspection['bridgeCandidates'] ?? [];
$selectedBridge = $inspection['selectedBridge'] ?? null;
$resolvedMappings = $inspection['resolvedMappings'] ?? [];
$formulaRows = $inspection['formulaRows'] ?? [];
$resultSnapshot = $inspection['resultSnapshot'] ?? null;
$chainRows = $inspection['chainRows'] ?? [];
$resolvedOkCount = count(array_filter($resolvedMappings, static fn(array $row): bool => (string) ($row['ResolutionStatus'] ?? '') === 'OK'));
$resolvedMissingCount = count(array_filter($resolvedMappings, static fn(array $row): bool => (string) ($row['ResolutionStatus'] ?? '') === 'MISSING'));
$formulaCount = count($formulaRows);
$chainCount = count($chainRows);
$periodResultCount = count($resultSnapshot['periodRows'] ?? []);
$chainGrandTotal = 0.0;
foreach ($chainRows as $chainRow) {
    $rawTotal = $chainRow['LatestTotal'] ?? null;
    if (is_numeric($rawTotal)) {
        $chainGrandTotal += (float) $rawTotal;
    }
}
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <strong><i class="bi bi-search me-2"></i>Transaction Calculation Diagnostics</strong>
      <div class="small text-muted">Inspect a single transaction, the selected bridge, resolved inputs, formulas, and persisted outputs.</div>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="index.php" class="row g-3 align-items-end mb-4">
      <input type="hidden" name="route" value="transaction-calc-diagnostics/index">
      <div class="col-md-4">
        <label for="transaction_id" class="form-label">Transaction ID</label>
        <input type="number" min="1" class="form-control" id="transaction_id" name="transaction_id" value="<?= (int) $transactionId ?>" required>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-search me-1"></i>Inspect
        </button>
      </div>
    </form>

    <?php if ($inspection !== null && $transaction !== null): ?>
      <form method="post" action="index.php?route=transaction-calc-diagnostics/recalculate" class="mb-4">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="transaction_id" value="<?= (int) $transaction['TransactionID'] ?>">
        <button type="submit" class="btn btn-outline-success">
          <i class="bi bi-arrow-repeat me-1"></i>Recalculate Selected Transaction
        </button>
      </form>
    <?php endif; ?>

    <?php if ($notFound): ?>
      <div class="alert alert-warning mb-0">Transaction not found.</div>
    <?php elseif ($inspection !== null && $transaction !== null): ?>
      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Transaction ID</div><div class="fs-5 fw-semibold"><?= h((string) $transaction['TransactionID']) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Calculation ID</div><div class="fs-5 fw-semibold"><?= h((string) ($transaction['CalculationID'] ?? '')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Transaction Type</div><div class="fs-5 fw-semibold"><?= h((string) ($transaction['TransactionTypeCode'] ?? '')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">UOM / Rate Code</div><div class="fs-5 fw-semibold"><?= h((string) ($transaction['UOMCodeInpC'] ?? '')) ?></div></div></div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Resolved Inputs</div><div class="fs-5 fw-semibold"><?= number_format(count($resolvedMappings)) ?></div><div class="small text-success"><?= number_format($resolvedOkCount) ?> OK</div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Missing Inputs</div><div class="fs-5 fw-semibold"><?= number_format($resolvedMissingCount) ?></div><div class="small text-muted">Mappings needing attention</div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Formula Nodes</div><div class="fs-5 fw-semibold"><?= number_format($formulaCount) ?></div><div class="small text-muted">Current model size</div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Chain Rows</div><div class="fs-5 fw-semibold"><?= number_format($chainCount) ?></div><div class="small text-muted">Related transactions</div></div></div>
      </div>

      <ul class="nav nav-tabs" id="txCalcDiagTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="true">Overview</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="inputs-tab" data-bs-toggle="tab" data-bs-target="#inputs-pane" type="button" role="tab" aria-controls="inputs-pane" aria-selected="false">Inputs</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="formulas-tab" data-bs-toggle="tab" data-bs-target="#formulas-pane" type="button" role="tab" aria-controls="formulas-pane" aria-selected="false">Formulas</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="results-tab" data-bs-toggle="tab" data-bs-target="#results-pane" type="button" role="tab" aria-controls="results-pane" aria-selected="false">Results</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="chain-tab" data-bs-toggle="tab" data-bs-target="#chain-pane" type="button" role="tab" aria-controls="chain-pane" aria-selected="false">Chain</button>
        </li>
      </ul>

      <div class="tab-content border border-top-0 rounded-bottom p-4 bg-white" id="txCalcDiagTabContent">
        <div class="tab-pane fade show active" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab" tabindex="0">
          <div class="row g-4">
            <div class="col-xl-6">
              <h5 class="mb-3">Transaction Summary</h5>
              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                  <tbody>
                    <tr><th style="width: 220px;">HeadRecordID</th><td><?= h(fmtv($transaction['HeadRecordID'] ?? '')) ?></td><th style="width: 220px;">DataObjectCode</th><td><?= h(fmtv($transaction['DataObjectCode'] ?? '')) ?></td></tr>
                    <tr><th>FiscalYearID</th><td><?= h(fmtv($transaction['FiscalYearID'] ?? '')) ?></td><th>VersionID</th><td><?= h(fmtv($transaction['VersionID'] ?? '')) ?></td></tr>
                    <tr><th>AccountCode</th><td><?= h(fmtv($transaction['AccountCode'] ?? '')) ?></td><th>GLAccountCode</th><td><?= h(fmtv($transaction['GLAccountCode'] ?? '')) ?></td></tr>
                    <tr><th>Calculation Name</th><td><?= h(fmtv($calculation['CalculationName'] ?? '')) ?></td><th>ChildCalculationID</th><td><?= h(fmtv($calculation['ChildCalculationID'] ?? '')) ?></td></tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="col-xl-6">
              <h5 class="mb-3">Selected Bridge</h5>
              <?php if ($selectedBridge === null): ?>
                <div class="alert alert-warning mb-0">No active transaction bridge matched this transaction.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm table-striped align-middle mb-0">
                    <tbody>
                      <tr><th style="width: 220px;">Bridge ID</th><td><?= h((string) $selectedBridge['CalcTransactionBridgeID']) ?></td><th style="width: 220px;">Specificity</th><td><?= h((string) $selectedBridge['SpecificityScore']) ?></td></tr>
                      <tr><th>Model</th><td><?= h((string) $selectedBridge['ModelCode']) ?>, <?= h((string) $selectedBridge['ModelName']) ?></td><th>Scenario</th><td><?= h((string) $selectedBridge['ScenarioCode']) ?>, <?= h((string) $selectedBridge['ScenarioName']) ?></td></tr>
                      <tr><th>Cost Object</th><td><?= h((string) ($selectedBridge['CostObjectCode'] ?? '')) ?></td><th>Priority</th><td><?= h((string) $selectedBridge['PriorityNo']) ?></td></tr>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>

            <?php if (count($bridgeCandidates) > 1): ?>
              <div class="col-12">
                <h5 class="mb-3">Matching Bridge Candidates</h5>
                <div class="small text-muted mb-2">The highlighted row is the bridge the engine selected.</div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Bridge</th>
                        <th>Model</th>
                        <th>Scenario</th>
                        <th>Legacy Calc</th>
                        <th>FY</th>
                        <th>Version</th>
                        <th>Txn Type</th>
                        <th>UOM</th>
                        <th>Specificity</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($bridgeCandidates as $candidate): ?>
                        <tr<?= (int) $candidate['CalcTransactionBridgeID'] === (int) ($selectedBridge['CalcTransactionBridgeID'] ?? 0) ? ' class="table-primary"' : '' ?>>
                          <td><?= h((string) $candidate['CalcTransactionBridgeID']) ?></td>
                          <td><?= h((string) $candidate['ModelCode']) ?></td>
                          <td><?= h((string) $candidate['ScenarioCode']) ?></td>
                          <td><?= h(fmtv($candidate['LegacyCalculationID'] ?? '')) ?></td>
                          <td><?= h(fmtv($candidate['FiscalYearID'] ?? '')) ?></td>
                          <td><?= h(fmtv($candidate['VersionID'] ?? '')) ?></td>
                          <td><?= h(fmtv($candidate['TransactionTypeCode'] ?? '')) ?></td>
                          <td><?= h(fmtv($candidate['UOMCodeInpC'] ?? '')) ?></td>
                          <td><?= h((string) $candidate['SpecificityScore']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="tab-pane fade" id="inputs-pane" role="tabpanel" aria-labelledby="inputs-tab" tabindex="0">
          <h5 class="mb-3">Resolved Input Sources</h5>
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Node</th>
                  <th>Source Type</th>
                  <th>Source</th>
                  <th>Period</th>
                  <th>Resolved Value</th>
                  <th>Status</th>
                  <th>Detail</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($resolvedMappings === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No transaction mappings were resolved.</td></tr>
              <?php else: ?>
                <?php foreach ($resolvedMappings as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) $row['NodeCode']) ?></div>
                      <div class="small text-muted"><?= h((string) $row['NodeName']) ?></div>
                    </td>
                    <td><?= h((string) $row['SourceTypeCode']) ?></td>
                    <td><code><?= h((string) ($row['ExpandedSourceName'] !== '' ? $row['ExpandedSourceName'] : $row['SourceName'])) ?></code></td>
                    <td><?= h((string) $row['PeriodCode']) ?></td>
                    <td><?= h(fmtv($row['ResolvedValue'] ?? '')) ?></td>
                    <td>
                      <?php $status = (string) $row['ResolutionStatus']; ?>
                      <span class="badge <?= $status === 'OK' ? 'bg-success' : ($status === 'MISSING' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                        <?= h($status) ?>
                      </span>
                    </td>
                    <td class="small"><?= h((string) $row['ResolutionDetail']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="tab-pane fade" id="formulas-pane" role="tabpanel" aria-labelledby="formulas-tab" tabindex="0">
          <h5 class="mb-3">Model Nodes and Formulas</h5>
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Node</th>
                  <th>Type</th>
                  <th>Formula</th>
                  <th class="text-end">Dependencies</th>
                  <th>Current Result</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($formulaRows === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No model nodes were found.</td></tr>
              <?php else: ?>
                <?php foreach ($formulaRows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) $row['NodeCode']) ?></div>
                      <div class="small text-muted"><?= h((string) $row['NodeName']) ?></div>
                    </td>
                    <td><?= h((string) $row['NodeTypeCode']) ?></td>
                    <td><code><?= h((string) ($row['ExpressionText'] ?? '')) ?></code></td>
                    <td class="text-end"><?= h((string) $row['DependencyCount']) ?></td>
                    <td><?= h(fmtv($row['ResultValue'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="tab-pane fade" id="results-pane" role="tabpanel" aria-labelledby="results-tab" tabindex="0">
          <h5 class="mb-3">Head Record Result Summary</h5>
          <div class="small text-muted mb-3">Compact view of the selected transaction and all related child transactions. Showing totals only.</div>
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Transaction</th>
                  <th>Calculation</th>
                  <th>Order</th>
                  <th>Txn Type</th>
                  <th>UOM</th>
                  <th class="text-end">BPTotal</th>
                  <th>Calculated</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($chainRows === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No related transactions were found.</td></tr>
              <?php else: ?>
                <?php foreach ($chainRows as $row): ?>
                  <tr<?= (int) $row['TransactionID'] === (int) $transaction['TransactionID'] ? ' class="table-primary"' : '' ?>>
                    <td><?= h((string) $row['TransactionID']) ?></td>
                    <td>
                      <?php if (!empty($row['CalcModelID'])): ?>
                        <a href="index.php?route=scenario-admin/detail&id=<?= (int) $row['CalcModelID'] ?>">
                          <?= h((string) $row['CalculationID']) ?>, <?= h((string) ($row['CalculationName'] ?? '')) ?>
                        </a>
                        <?php if (!empty($row['ModelCode'])): ?>
                          <div class="small text-muted"><?= h((string) $row['ModelCode']) ?></div>
                        <?php endif; ?>
                      <?php else: ?>
                        <?= h((string) $row['CalculationID']) ?>, <?= h((string) ($row['CalculationName'] ?? '')) ?>
                      <?php endif; ?>
                    </td>
                    <td><?= h(fmtv($row['CalculationOrder'] ?? '')) ?></td>
                    <td><?= h((string) ($row['TransactionTypeCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['UOMCodeInpC'] ?? '')) ?></td>
                    <td class="text-end"><?= h(fmtamt($row['LatestTotal'] ?? '')) ?></td>
                    <td><?= h(fmtv($row['LatestCalculatedDate'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
              <?php if ($chainRows !== []): ?>
                <tfoot class="table-light">
                  <tr>
                    <th colspan="5" class="text-end">Total</th>
                    <th class="text-end"><?= h(fmtamt($chainGrandTotal)) ?></th>
                    <th></th>
                  </tr>
                </tfoot>
              <?php endif; ?>
            </table>
          </div>
        </div>

        <div class="tab-pane fade" id="chain-pane" role="tabpanel" aria-labelledby="chain-tab" tabindex="0">
          <h5 class="mb-3">Chain Transactions</h5>
          <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Transaction</th>
                  <th>Calculation</th>
                  <th>Order</th>
                  <th>Txn Type</th>
                  <th>UOM</th>
                  <th class="text-end">BPTotal</th>
                  <th>Results</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($chainRows === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">No chain context was found.</td></tr>
              <?php else: ?>
                <?php foreach ($chainRows as $row): ?>
                  <tr<?= (int) $row['TransactionID'] === (int) $transaction['TransactionID'] ? ' class="table-primary"' : '' ?>>
                    <td><?= h((string) $row['TransactionID']) ?></td>
                    <td>
                      <?php if (!empty($row['CalcModelID'])): ?>
                        <a href="index.php?route=scenario-admin/detail&id=<?= (int) $row['CalcModelID'] ?>">
                          <?= h((string) $row['CalculationID']) ?>, <?= h((string) ($row['CalculationName'] ?? '')) ?>
                        </a>
                        <?php if (!empty($row['ModelCode'])): ?>
                          <div class="small text-muted"><?= h((string) $row['ModelCode']) ?></div>
                        <?php endif; ?>
                      <?php else: ?>
                        <?= h((string) $row['CalculationID']) ?>, <?= h((string) ($row['CalculationName'] ?? '')) ?>
                      <?php endif; ?>
                    </td>
                    <td><?= h(fmtv($row['CalculationOrder'] ?? '')) ?></td>
                    <td><?= h((string) ($row['TransactionTypeCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['UOMCodeInpC'] ?? '')) ?></td>
                    <td class="text-end"><?= h(fmtamt($row['LatestTotal'] ?? '')) ?></td>
                    <td><?= !empty($row['HasResults']) ? 'Yes' : 'No' ?></td>
                    <td class="text-end">
                      <a href="index.php?route=transaction-calc-diagnostics/index&transaction_id=<?= (int) $row['TransactionID'] ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-search"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
