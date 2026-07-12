<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$rows = array_values(is_array($rows ?? null) ? $rows : []);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-clock-history me-2"></i>Intelligence Engine Runs</h3>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/index">Dashboard</a>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Status</th><th>Provider</th><th>External</th><th>Masked</th><th class="text-end">Time</th><th>Error</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="8" class="text-center text-muted py-3">No intelligence engine runs found.</td></tr>
            <?php else: foreach ($rows as $row): ?>
              <tr>
                <td class="small"><?= h((string) ($row['CreatedDate'] ?? '')) ?></td>
                <td><?= h((string) ($row['RunTypeCode'] ?? '')) ?></td>
                <td><span class="badge text-bg-<?= (string) ($row['StatusCode'] ?? '') === 'SUCCESS' ? 'success' : 'danger' ?>"><?= h((string) ($row['StatusCode'] ?? '')) ?></span></td>
                <td><?= h((string) ($row['ProviderCode'] ?? '')) ?></td>
                <td><?= (int) ($row['ExternalServiceUsed'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
                <td><?= (int) ($row['DataMaskingUsed'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
                <td class="text-end"><?= h((string) (int) ($row['ResponseTimeMs'] ?? 0)) ?> ms</td>
                <td class="small text-muted"><?= h((string) ($row['ErrorMessage'] ?? '')) ?></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
