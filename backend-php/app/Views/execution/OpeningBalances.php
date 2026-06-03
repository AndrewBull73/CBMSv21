<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$ctx = is_array($context ?? null) ? $context : [];
$fy = (int) ($ctx['FiscalYearID'] ?? 0);
$ver = (int) ($ctx['VersionID'] ?? 0);
$currentVersionLabel = trim((string) ($currentVersion['VersionLabel'] ?? ''));
$screenHeader = [
    'title' => 'Budget Execution Opening Balances',
    'icon' => 'bi-table',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h((string) $fy) ?></strong>
        <?php if ($currentVersionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($currentVersionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <?php if ($isExecutionVersion): ?>
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Opening Lines</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['LineCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Opening Amount</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['OpeningAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Current Authorized</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['CurrentAuthorizedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Supplementaries</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['SupplementaryAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Released By Warrants</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['ReleasedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Reserved</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['ReservedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Committed</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['CommittedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Review the opening execution baseline loaded from the approved submission version before you begin warrants, transfers, supplementaries, reservations, commitments, or other Budget Execution transactions.
      </div>

      <?php if (!$supportsExecutionFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Execution foundation tables are not installed.</strong> Run <code>create_budget_execution_foundation_v1.sql</code> before using this inquiry.
        </div>
      <?php endif; ?>

      <?php if (!$isExecutionVersion): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          The current context is not an execution version. Select an execution version from Budget Execution Setup first.
        </div>

        <?php if (!empty($executionVersions)): ?>
          <div class="card shadow-sm mb-0">
            <div class="card-header">
              <h5 class="mb-0">Available Execution Versions</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="execution-version-options-table">
                  <thead class="table-light">
                    <tr>
                      <th>Version</th>
                      <th>Status</th>
                      <th>Source</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($executionVersions as $row): ?>
                      <tr>
                        <td><?= h((string) ($row['VersionLabel'] ?? '')) ?></td>
                        <td><?= h((string) ($row['VersionStatus'] ?? '')) ?></td>
                        <td><?= h((string) (($row['SourceVersionLabel'] ?? '') !== '' ? $row['SourceVersionLabel'] : '-')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="card shadow-sm mb-0">
          <div class="card-header">
            <h5 class="mb-0">Opening Balance Detail</h5>
          </div>
          <div class="card-body">
            <?php if (empty($rows)): ?>
              <div class="text-muted">No opening balances have been created for the current execution version yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="execution-opening-balance-table">
                  <thead class="table-light">
                    <tr>
                      <th>DataScope</th>
                      <th>Sector</th>
                      <th>Program</th>
                      <th>Project</th>
                      <th>Economic</th>
                      <th>Bid Title</th>
                      <th class="text-end">Opening</th>
                      <th class="text-end">Current</th>
                      <th class="text-end">Supplementary</th>
                      <th class="text-end">Released</th>
                      <th class="text-end">Reserved</th>
                      <th class="text-end">Committed</th>
                      <th class="text-end">Available Released</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $row): ?>
                      <tr>
                        <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($row['SectorID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['ProgramID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['ProjectID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['EconomicItemID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['BidTitle'] ?? '')) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['OpeningAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['CurrentAuthorizedAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['SupplementaryAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['ReleasedAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['ReservedAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['CommittedAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['AvailableReleasedAmount'] ?? 0), 2)) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
