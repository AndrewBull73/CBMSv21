<?php
declare(strict_types=1);
require __DIR__ . '/../ai/_helpers.php';
$rows = array_values(is_array($rows ?? null) ? $rows : []);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-clock-history me-2"></i>Analysis Dataset Logs</h3>
      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=ai-dataset/index">Analyze</a>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Date</th><th>Dataset</th><th>Question</th><th>Status</th><th class="text-end">Rows</th><th class="text-end">Time</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">No dataset analysis logs found.</td></tr>
            <?php else: foreach ($rows as $row): ?>
              <tr>
                <td class="small"><?= h((string) ($row['CreatedDate'] ?? '')) ?></td>
                <td><div><?= h((string) ($row['DatasetName'] ?? '')) ?></div><div class="small text-muted"><?= h((string) ($row['DatasetCode'] ?? '')) ?></div></td>
                <td><div class="text-truncate" style="max-width: 440px;"><?= h((string) ($row['Question'] ?? '')) ?></div><div class="small text-muted"><?= h((string) ($row['ResponseSummary'] ?? $row['ErrorMessage'] ?? '')) ?></div></td>
                <td><span class="badge text-bg-<?= (string) ($row['StatusCode'] ?? '') === 'SUCCESS' ? 'success' : 'danger' ?>"><?= h((string) ($row['StatusCode'] ?? '')) ?></span></td>
                <td class="text-end"><?= h((string) (int) ($row['RowCount'] ?? 0)) ?></td>
                <td class="text-end"><?= h((string) (int) ($row['ResponseTimeMs'] ?? 0)) ?> ms</td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
