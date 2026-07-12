<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$rows = array_values(is_array($rows ?? null) ? $rows : []);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header"><h3 class="mb-0"><i class="bi bi-clock-history me-2"></i>AI Question Logs</h3></div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>When</th><th>Question</th><th>Response</th><th>Tokens</th><th>Helpful</th></tr></thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No assistant questions logged yet.</td></tr>
              <?php else: foreach ($rows as $row): ?>
                <tr>
                  <td><?= h((string) ($row['CreatedDate'] ?? '')) ?></td>
                  <td style="max-width: 20rem;"><?= h((string) ($row['Question'] ?? '')) ?></td>
                  <td style="max-width: 28rem;"><?= h(substr((string) ($row['Response'] ?? ''), 0, 240)) ?></td>
                  <td><?= h((string) (int) ($row['TotalTokens'] ?? 0)) ?></td>
                  <td><?= $row['Helpful'] === null ? '<span class="text-muted">None</span>' : ((int) $row['Helpful'] === 1 ? 'Yes' : 'No') ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
