<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$usage = is_array($usage ?? null) ? $usage : [];
$daily = array_values(is_array($usage['daily'] ?? null) ? $usage['daily'] : []);
$byModel = array_values(is_array($usage['by_model'] ?? null) ? $usage['by_model'] : []);
$feedback = array_values(is_array($usage['feedback'] ?? null) ? $usage['feedback'] : []);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-bar-chart-line me-2"></i>AI Usage Dashboard</h3>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=ai-knowledge/admin" class="btn btn-sm btn-outline-secondary"><i class="bi bi-database me-1"></i>Knowledge Base</a>
        <a href="index.php?route=ai-knowledge/logs" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Logs</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <h4 class="h6">Daily Usage</h4>
        <div class="table-responsive mb-4">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Date</th><th class="text-end">Questions</th><th class="text-end">Tokens</th><th class="text-end">Avg Response</th></tr></thead>
            <tbody>
            <?php if ($daily === []): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No usage found.</td></tr>
            <?php else: foreach ($daily as $row): ?>
              <tr>
                <td><?= h((string) ($row['UsageDate'] ?? '')) ?></td>
                <td class="text-end"><?= h((string) (int) ($row['QuestionCount'] ?? 0)) ?></td>
                <td class="text-end"><?= h(number_format((int) ($row['TokenCount'] ?? 0))) ?></td>
                <td class="text-end"><?= h((string) (int) ($row['AvgResponseMs'] ?? 0)) ?> ms</td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <div class="row g-4">
          <div class="col-lg-7">
            <h4 class="h6">By Model</h4>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light"><tr><th>Provider</th><th>Model</th><th class="text-end">Questions</th><th class="text-end">Tokens</th></tr></thead>
                <tbody>
                <?php if ($byModel === []): ?>
                  <tr><td colspan="4" class="text-center text-muted py-3">No model usage found.</td></tr>
                <?php else: foreach ($byModel as $row): ?>
                  <tr>
                    <td><?= h((string) ($row['ProviderCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['ModelCode'] ?? '')) ?></td>
                    <td class="text-end"><?= h((string) (int) ($row['QuestionCount'] ?? 0)) ?></td>
                    <td class="text-end"><?= h(number_format((int) ($row['TokenCount'] ?? 0))) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="col-lg-5">
            <h4 class="h6">Feedback</h4>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle">
                <thead class="table-light"><tr><th>Rating</th><th class="text-end">Count</th></tr></thead>
                <tbody>
                <?php if ($feedback === []): ?>
                  <tr><td colspan="2" class="text-center text-muted py-3">No feedback found.</td></tr>
                <?php else: foreach ($feedback as $row): ?>
                  <tr>
                    <td><?php if ($row['Helpful'] === null): ?>No Feedback<?php else: ?><?= ((int) $row['Helpful']) === 1 ? 'Helpful' : 'Not Helpful' ?><?php endif; ?></td>
                    <td class="text-end"><?= h((string) (int) ($row['FeedbackCount'] ?? 0)) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
