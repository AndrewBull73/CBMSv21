<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$summary = is_array($summary ?? null) ? $summary : [];
$readinessSummary = array_values(is_array($readinessSummary ?? null) ? $readinessSummary : []);
$recentRuns = array_values(is_array($recentRuns ?? null) ? $recentRuns : []);

$statusBadge = static function (string $status): string {
    return match (strtolower(trim($status))) {
        'success', 'completed' => 'text-bg-success',
        'running' => 'text-bg-primary',
        'failed', 'error' => 'text-bg-danger',
        'warning', 'partial' => 'text-bg-warning',
        default => 'text-bg-secondary',
    };
};
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Integration Dashboard</h3>
        <div class="small text-muted mt-1">Monitor external systems, interface readiness, and recent API/import/export run activity.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=integration-admin/systems" class="btn btn-sm btn-outline-secondary"><i class="bi bi-diagram-3 me-1"></i>Systems</a>
        <a href="index.php?route=integration-admin/interfaces" class="btn btn-sm btn-outline-secondary"><i class="bi bi-plug me-1"></i>Interfaces</a>
        <a href="index.php?route=integration-admin/crosswalks" class="btn btn-sm btn-outline-secondary"><i class="bi bi-shuffle me-1"></i>Crosswalks</a>
        <a href="index.php?route=integration-admin/actuals-review" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-check me-1"></i>Actuals Review</a>
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
        <div class="alert alert-warning mb-0">
          Run <code><?= h($installScriptPath) ?></code> to install the integration foundation tables before using the dashboard.
        </div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4">
          <?php foreach ([
              'Systems' => 'system_count',
              'Active Systems' => 'active_system_count',
              'Interfaces' => 'interface_count',
              'Active Interfaces' => 'active_interface_count',
              'Ready Interfaces' => 'ready_interface_count',
              'Runs Last 24h' => 'run_count_24h',
              'Successful Last 24h' => 'success_run_count_24h',
              'Failed Last 24h' => 'failed_run_count_24h',
          ] as $label => $key): ?>
            <div class="col">
              <div class="border rounded p-3 h-100 bg-white">
                <div class="small text-muted"><?= h($label) ?></div>
                <div class="fs-4 fw-semibold"><?= h((string) (int) ($summary[$key] ?? 0)) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="row g-4">
          <div class="col-xl-4">
            <h4 class="h5 mb-3">Interface Readiness</h4>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Status</th>
                    <th class="text-end">Interfaces</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($readinessSummary === []): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No interfaces registered.</td></tr>
                  <?php else: ?>
                    <?php foreach ($readinessSummary as $row): ?>
                      <tr>
                        <td><?= h((string) ($row['ReadinessStatus'] ?? 'Not Set')) ?></td>
                        <td class="text-end"><?= h((string) (int) ($row['InterfaceCount'] ?? 0)) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="col-xl-8">
            <h4 class="h5 mb-3">Recent Runs</h4>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Run</th>
                    <th>Interface</th>
                    <th>System</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th class="text-end">Records</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($recentRuns === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No integration runs have been recorded yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($recentRuns as $run): ?>
                      <?php
                      $isPreviewOnly = (string) ($run['TriggerSourceCode'] ?? '') === 'manual_preview';
                      $isMockSystem = str_starts_with(strtoupper((string) ($run['SystemCode'] ?? '')), 'MOCK_');
                      ?>
                      <tr>
                        <td>
                          <a href="index.php?route=integration-admin/run-detail&amp;id=<?= h((string) (int) ($run['IntegrationRunID'] ?? 0)) ?>">
                            #<?= h((string) (int) ($run['IntegrationRunID'] ?? 0)) ?>
                          </a>
                        </td>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($run['InterfaceName'] ?? '')) ?></div>
                          <div class="small text-muted"><?= h((string) ($run['InterfaceCode'] ?? '')) ?></div>
                        </td>
                        <td>
                          <div><?= h((string) ($run['SystemName'] ?? '')) ?></div>
                          <?php if ($isPreviewOnly || $isMockSystem): ?>
                            <div class="small mt-1"><span class="badge text-bg-warning">No external dispatch</span></div>
                          <?php endif; ?>
                        </td>
                        <td><span class="badge <?= h($statusBadge((string) ($run['RunStatusCode'] ?? ''))) ?>"><?= h((string) ($run['RunStatusCode'] ?? '')) ?></span></td>
                        <td><?= h((string) ($run['StartedAt'] ?? '')) ?></td>
                        <td class="text-end"><?= h((string) (int) ($run['RecordsProcessed'] ?? 0)) ?></td>
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
</div>
