<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$checks = is_array($checks ?? null) ? $checks : [];
$summary = is_array($summary ?? null) ? $summary : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));

$grouped = [];
foreach ($checks as $check) {
    $category = (string) ($check['category'] ?? 'Other');
    $grouped[$category][] = $check;
}

$statusBadge = static function (string $status): string {
    return match ($status) {
        'ready' => 'text-bg-success',
        'warning' => 'text-bg-warning',
        'critical' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
};

$healthScore = (int) ($summary['health_score'] ?? 100);
$scoreClass = $healthScore >= 85
    ? 'text-success'
    : ($healthScore >= 60 ? 'text-warning' : 'text-danger');
$blockers = is_array($summary['blockers'] ?? null) ? $summary['blockers'] : [];
?>

<style>
  .config-readiness .metric-card .metric-value {
    font-size: 1.35rem;
    line-height: 1.1;
  }
  .config-readiness .card-header {
    background: #fff;
    padding-top: .6rem;
    padding-bottom: .6rem;
  }
  .config-readiness .table td,
  .config-readiness .table th {
    font-size: .875rem;
  }
</style>

<div class="container mt-4 config-readiness">
  <div class="mb-3">
    <div>
      <h2 class="mb-1">Financial &amp; Calculation Configuration Readiness</h2>
      <div class="text-muted small mb-0">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100 metric-card">
        <div class="card-body py-3">
          <div class="text-muted small">Health Score</div>
          <div class="metric-value fw-semibold <?= h($scoreClass) ?>"><?= $healthScore ?>%</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100 metric-card">
        <div class="card-body py-3">
          <div class="text-muted small">Critical Blockers</div>
          <div class="metric-value fw-semibold text-danger"><?= (int) ($summary['critical_checks'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100 metric-card">
        <div class="card-body py-3">
          <div class="text-muted small">Warning Checks</div>
          <div class="metric-value fw-semibold"><?= (int) ($summary['warning_checks'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100 metric-card">
        <div class="card-body py-3">
          <div class="text-muted small">Open Items</div>
          <div class="metric-value fw-semibold"><?= (int) ($summary['open_items'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-info border-0 shadow-sm py-2 mb-3 small">
    This dashboard focuses on the transaction setup, financial controls, reporting/grouping foundations, and calculation engine configuration that sit underneath budget input and financial processing.
  </div>

  <div class="alert alert-light border shadow-sm py-2 mb-3 small">
    Budget input sheet design, deeper financial report configuration, and other advanced calculation-engine maintenance are still evolving. This readiness screen is meant to help you validate the current foundation and identify which next setup areas still need dedicated admin screens.
  </div>

  <?php if ($blockers !== []): ?>
    <div class="alert alert-danger border-0 shadow-sm py-2 mb-3">
      <div class="fw-semibold mb-1">Critical blockers need attention first</div>
      <div class="small">
        <?php foreach ($blockers as $index => $blocker): ?>
          <?php if ($index > 0): ?><span class="mx-1">|</span><?php endif; ?>
          <strong><?= h((string) ($blocker['title'] ?? '')) ?></strong>
          <?php if ((int) ($blocker['issue_count'] ?? 0) > 0): ?>
            (<?= (int) ($blocker['issue_count'] ?? 0) ?>)
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php foreach ($grouped as $category => $rows): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white">
        <h5 class="mb-0 fs-6"><?= h((string) $category) ?></h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover table-admin align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Check</th>
                <th>Status</th>
                <th class="text-end">Scope</th>
                <th class="text-end">Issues</th>
                <th>Detail</th>
                <th class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td class="fw-semibold"><?= h((string) ($row['title'] ?? '')) ?></td>
                <td>
                  <span class="badge <?= $statusBadge((string) ($row['status'] ?? 'info')) ?>">
                    <?= h(ucfirst((string) ($row['status'] ?? 'info'))) ?>
                  </span>
                </td>
                <td class="text-end"><?= (int) ($row['total_count'] ?? 0) ?></td>
                <td class="text-end"><?= (int) ($row['issue_count'] ?? 0) ?></td>
                <td>
                  <div><?= h((string) ($row['message'] ?? '')) ?></div>
                  <?php if (!empty($row['instruction'])): ?>
                    <div class="small text-muted mt-1"><strong>What to do:</strong> <?= h((string) $row['instruction']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if (!empty($row['action_route']) && !empty($row['action_label'])): ?>
                    <a href="<?= h((string) $row['action_route']) ?>" class="btn btn-outline-primary btn-sm">
                      <?= h((string) $row['action_label']) ?>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
