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

$preferredCategoryOrder = [
    'Fiscal Context',
    'Organisation Structure',
    'Segments And Dimensions',
];
$orderedGrouped = [];
foreach ($preferredCategoryOrder as $category) {
    if (array_key_exists($category, $grouped)) {
        $orderedGrouped[$category] = $grouped[$category];
        unset($grouped[$category]);
    }
}
$grouped = $orderedGrouped + $grouped;

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
$contextSummary = $yearLabel !== '' ? $yearLabel : 'Not set';
if ($versionLabel !== '') {
    $contextSummary .= ' / ' . $versionLabel;
}
$screenHeader = [
    'title' => 'Base Configuration Readiness',
    'icon' => 'bi-check2-square',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($contextSummary) ?></strong>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
        <div id="base-config-readiness-health-card" class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Health Score</div>
              <div class="fs-4 fw-semibold <?= h($scoreClass) ?>"><?= $healthScore ?>%</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Critical Blockers</div>
              <div class="fs-4 fw-semibold text-danger"><?= (int) ($summary['critical_checks'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Warning Checks</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['warning_checks'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Open Items</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['open_items'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div id="base-config-readiness-runbook" class="alert alert-info border-0 shadow-sm mb-4">
        <div class="fw-semibold mb-1">Configuration Runbook</div>
        <div class="mb-2">Use this dashboard to confirm fiscal context, organisation structure, segment design, scoped security, workflow routing, and system settings are in a usable state before broader testing or rollout.</div>
        <div class="small text-muted mb-2">This screen intentionally excludes transaction setup, input-sheet configuration, financial grouping, and calculation-engine setup, which are maintained in the separate financial and calculation configuration group.</div>
        <div class="small">Use the current base-configuration instruction set as the companion guide for this screen.</div>
        <div class="mt-1"><code>testing/inittest-pack/02_phase_configuration/01_initial_system_configuration_instructions.md</code></div>
        <div><code>testing/inittest-pack/02_phase_configuration/03_base_configuration_readiness_verification.sql</code></div>
      </div>

      <?php if ($blockers !== []): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4">
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
                          <div class="small text-muted mt-1"><strong>What to do:</strong> <?= h((string) ($row['instruction'] ?? '')) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php if (!empty($row['action_route']) && !empty($row['action_label'])): ?>
                          <a href="<?= h((string) ($row['action_route'] ?? '')) ?>" class="btn btn-outline-primary btn-sm">
                            <?= h((string) ($row['action_label'] ?? '')) ?>
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
