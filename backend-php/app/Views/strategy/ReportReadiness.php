<?php
declare(strict_types=1);

/** @var array $contextLabels */
/** @var array $workflowState */
/** @var array $summary */
/** @var array $checks */
/** @var string $readinessType */

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$checks = is_array($checks ?? null) ? $checks : [];
$readinessType = (string) ($readinessType ?? 'submission');
$isSubmissionReadiness = $readinessType !== 'configuration';

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

$screenHeader = [
    'title' => $isSubmissionReadiness ? __t('strategy_submission_readiness') : __t('strategy_configuration_readiness'),
    'icon' => $isSubmissionReadiness ? 'bi-clipboard-check' : 'bi-shield-check',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        <?= h(__t('strategy_fiscal_context')) ?>:
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small"><?= h(__t('strategy_checks_passed')) ?></div>
          <div class="fs-4 fw-semibold"><?= (int) ($summary['ready_checks'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small"><?= h(__t('strategy_needs_attention')) ?></div>
          <div class="fs-4 fw-semibold"><?= (int) ($summary['critical_checks'] ?? 0) + (int) ($summary['warning_checks'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small"><?= h(__t('strategy_open_items')) ?></div>
          <div class="fs-4 fw-semibold"><?= (int) ($summary['open_items'] ?? 0) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small"><?= h(__t('strategy_informational_checks')) ?></div>
          <div class="fs-4 fw-semibold"><?= (int) ($summary['info_checks'] ?? 0) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-info border-0 shadow-sm mb-4">
    <?php if ($isSubmissionReadiness): ?>
      This dashboard focuses on active strategic records and the active fiscal year/version. Use it to spot missing planning links, missing budgets or targets, and incomplete BSP governance content before submission.
    <?php else: ?>
      This dashboard focuses on setup quality for the active fiscal year. Use it to confirm mappings, source hierarchy, imports, and planning framework reference data are in place before users begin or repeat detailed strategic entry.
    <?php endif; ?>
  </div>

  <?php if ($isSubmissionReadiness): ?>
    <?php
      $workflowReturnRoute = 'strategy-reports/submission-readiness';
      require __DIR__ . '/_WorkflowPanel.php';
    ?>
  <?php endif; ?>

  <?php foreach ($grouped as $category => $rows): ?>
    <div class="card shadow-sm mb-4">
      <div class="card-header">
        <h5 class="mb-0"><?= h((string) $category) ?></h5>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle mb-0">
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
</div>
</div>
