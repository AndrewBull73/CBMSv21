<?php
declare(strict_types=1);
require __DIR__ . '/../ai/_helpers.php';
$rows = array_values(is_array($rows ?? null) ? $rows : []);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><i class="bi bi-database me-2"></i>AI Analysis Datasets</h3>
      <div class="d-inline-flex gap-2">
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=ai-dataset/index"><i class="bi bi-graph-up-arrow me-1"></i>Analyze</a>
        <a class="btn btn-sm btn-primary" href="index.php?route=ai-dataset/dataset-form"><i class="bi bi-plus-lg me-1"></i>Register</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Dataset</th><th>Source</th><th>Sensitivity</th><th class="text-end">Columns</th><th></th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No AI analysis datasets registered.</td></tr>
            <?php else: foreach ($rows as $row): ?>
              <tr>
                <td><div class="fw-semibold"><?= h((string) ($row['DatasetName'] ?? '')) ?></div><div class="small text-muted"><?= h((string) ($row['DatasetCode'] ?? '')) ?></div></td>
                <td><div><?= h((string) ($row['SourceObjectName'] ?? '')) ?></div><div class="small text-muted"><?= h((string) ($row['SourceType'] ?? '')) ?></div></td>
                <td><span class="badge text-bg-secondary"><?= h((string) ($row['SensitivityLevel'] ?? 'RESTRICTED')) ?></span></td>
                <td class="text-end"><?= h((string) (int) ($row['ColumnCount'] ?? 0)) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="index.php?route=ai-dataset/dataset-form&amp;id=<?= h((string) (int) ($row['DatasetID'] ?? 0)) ?>"><i class="bi bi-pencil"></i></a></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
