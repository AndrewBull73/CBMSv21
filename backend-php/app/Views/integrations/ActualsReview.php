<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$summary = is_array($summary ?? null) ? $summary : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$stagingInstalled = (bool) ($stagingInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');

$returnFields = static function (array $filters): void {
    foreach ([
        'run_id' => 'return_run_id',
        'status' => 'return_status',
        'fiscal_year' => 'return_fiscal_year',
        'version' => 'return_version',
        'period' => 'return_period',
        'scope' => 'return_scope',
        'q' => 'return_q',
    ] as $filterKey => $fieldName) {
        echo '<input type="hidden" name="' . h($fieldName) . '" value="' . h((string) ($filters[$filterKey] ?? '')) . '">';
    }
};

$statusBadge = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'validated' => 'text-bg-success',
        'rejected' => 'text-bg-danger',
        'posted' => 'text-bg-primary',
        'staged' => 'text-bg-warning',
        default => 'text-bg-secondary',
    };
};

$readinessBadge = static function (string $code): string {
    return match (strtolower(trim($code))) {
        'ready_to_post' => 'text-bg-success',
        'blocked' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
};
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <h3 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Actuals Import Review</h3>
        <div class="small text-muted mt-1">Review staged FMIS actuals before any controlled posting process is added.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=integration-admin/dashboard" class="btn btn-sm btn-outline-secondary"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a href="index.php?route=integration-admin/runs" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Run History</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run the integration foundation SQL before reviewing imports.</div>
      <?php elseif (!$stagingInstalled): ?>
        <div class="alert alert-warning mb-0">
          Run <code><?= h($installScriptPath) ?></code> before reviewing imported actuals.
        </div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-6 g-3 mb-4">
          <?php foreach ([
              'Total' => 'total_count',
              'Staged' => 'staged_count',
              'Validated' => 'validated_count',
              'Rejected' => 'rejected_count',
              'Posted' => 'posted_count',
          ] as $label => $key): ?>
            <div class="col">
              <div class="border rounded p-3 h-100 bg-white">
                <div class="small text-muted"><?= h($label) ?></div>
                <div class="fs-4 fw-semibold"><?= h((string) (int) ($summary[$key] ?? 0)) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="col">
            <div class="border rounded p-3 h-100 bg-white">
              <div class="small text-muted">Amount</div>
              <div class="fs-5 fw-semibold"><?= number_format((float) ($summary['total_amount'] ?? 0), 2) ?></div>
            </div>
          </div>
        </div>

        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="integration-admin/actuals-review">
          <div class="col-md-2">
            <input type="number" name="run_id" class="form-control" placeholder="Run ID" value="<?= h((string) ($filters['run_id'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <select name="status" class="form-select">
              <?php foreach ($statusOptions as $value => $label): ?>
                <option value="<?= h((string) $value) ?>" <?= ((string) ($filters['status'] ?? '') === (string) $value) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1">
            <input type="number" name="fiscal_year" class="form-control" placeholder="FY" value="<?= h((string) ($filters['fiscal_year'] ?? '')) ?>">
          </div>
          <div class="col-md-1">
            <input type="number" name="version" class="form-control" placeholder="Ver" value="<?= h((string) ($filters['version'] ?? '')) ?>">
          </div>
          <div class="col-md-1">
            <input type="number" name="period" class="form-control" placeholder="Period" value="<?= h((string) ($filters['period'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <input type="text" name="scope" class="form-control" placeholder="Scope" value="<?= h((string) ($filters['scope'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <input type="search" name="q" class="form-control" placeholder="Reference, code, supplier" value="<?= h((string) ($filters['q'] ?? '')) ?>">
          </div>
          <div class="col-md-1 d-grid">
            <button type="submit" class="btn btn-outline-primary">Filter</button>
          </div>
        </form>

        <div class="d-flex justify-content-end mb-3">
          <form method="post" action="index.php?route=integration-admin/check-actuals-postability-batch">
            <?= csrf_field() ?>
            <?php $returnFields($filters); ?>
            <button type="submit" class="btn btn-outline-secondary">
              <i class="bi bi-clipboard2-check me-1"></i>Check Filtered Postability
            </button>
          </form>
        </div>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Reference</th>
                <th>Run</th>
                <th>FY / Ver</th>
                <th>Period</th>
                <th>Scope</th>
                <th>Program</th>
                <th>Economic</th>
                <th>Supplier</th>
                <th class="text-end">Amount</th>
                <th>Status</th>
                <th>Postability</th>
                <th>Posting Target</th>
                <th>Message</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="14" class="text-center text-muted py-3">No staged actuals matched the current filters.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php
                  $stagingId = (int) ($row['IntegrationActualsImportStagingID'] ?? 0);
                  $status = strtolower((string) ($row['StagingStatusCode'] ?? ''));
                  $canReview = $status !== 'posted';
                  ?>
                  <tr>
                    <td>
                      <div class="font-monospace"><?= h((string) ($row['TransactionReference'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ExternalCorrelationID'] ?? '')) ?></div>
                    </td>
                    <td>
                      <a href="index.php?route=integration-admin/run-detail&amp;id=<?= (int) ($row['IntegrationRunID'] ?? 0) ?>">#<?= (int) ($row['IntegrationRunID'] ?? 0) ?></a>
                      <div class="small text-muted"><?= h((string) ($row['InterfaceCode'] ?? '')) ?></div>
                    </td>
                    <td><?= h((string) ($row['FiscalYearID'] ?? '')) ?> / <?= h((string) ($row['VersionID'] ?? '')) ?></td>
                    <td><?= h((string) ($row['PeriodNo'] ?? '')) ?></td>
                    <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['ProgramCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['EconomicCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['SupplierName'] ?? '')) ?></td>
                    <td class="text-end"><?= number_format((float) ($row['ActualAmount'] ?? 0), 2) ?> <?= h((string) ($row['CurrencyCode'] ?? '')) ?></td>
                    <td><span class="badge <?= h($statusBadge((string) ($row['StagingStatusCode'] ?? ''))) ?>"><?= h((string) ($row['StagingStatusCode'] ?? '')) ?></span></td>
                    <td>
                      <?php if (!empty($row['PostingReadinessCode'])): ?>
                        <span class="badge <?= h($readinessBadge((string) ($row['PostingReadinessCode'] ?? ''))) ?>"><?= h((string) ($row['PostingReadinessCode'] ?? '')) ?></span>
                        <div class="small text-muted mt-1"><?= h((string) ($row['PostingReadinessMessage'] ?? '')) ?></div>
                      <?php else: ?>
                        <span class="text-muted small">Not checked</span>
                      <?php endif; ?>
                    </td>
                    <td class="small"><?= h((string) ($row['PostingTarget'] ?? '')) ?></td>
                    <td class="small"><?= h((string) ($row['ValidationMessage'] ?? '')) ?></td>
                    <td class="text-end">
                      <?php if ($canReview): ?>
                        <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                          <form method="post" action="index.php?route=integration-admin/validate-actuals-import" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $stagingId ?>">
                            <?php $returnFields($filters); ?>
                            <button type="submit" class="btn btn-sm btn-outline-success" title="Validate">
                              <i class="bi bi-check2-circle"></i>
                            </button>
                          </form>
                          <form method="post" action="index.php?route=integration-admin/check-actuals-postability" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $stagingId ?>">
                            <?php $returnFields($filters); ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Check postability">
                              <i class="bi bi-clipboard2-check"></i>
                            </button>
                          </form>
                          <form method="post" action="index.php?route=integration-admin/reject-actuals-import" class="d-inline-flex gap-1">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= $stagingId ?>">
                            <?php $returnFields($filters); ?>
                            <input type="text" name="message" class="form-control form-control-sm" placeholder="Reason" style="width: 10rem;">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Reject">
                              <i class="bi bi-x-circle"></i>
                            </button>
                          </form>
                        </div>
                      <?php else: ?>
                        <span class="text-muted small">Locked</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
