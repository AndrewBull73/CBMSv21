<?php
declare(strict_types=1);
require __DIR__ . '/_helpers.php';
$rows = array_values(is_array($rows ?? null) ? $rows : []);
$canAdmin = (bool) ($canAdmin ?? false);
$canApprove = (bool) ($canApprove ?? false);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <div>
        <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>ML Model Register</h3>
        <div class="small text-muted mt-1">Controlled catalogue of approved analytical and predictive models.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <?php if ($canAdmin): ?>
          <a class="btn btn-sm btn-primary" href="index.php?route=intelligence/ml-model-form"><i class="bi bi-plus-lg me-1"></i>Register Model</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary" href="index.php?route=intelligence/index">Dashboard</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">Run <code><?= h((string) $installScriptPath) ?></code> first.</div>
      <?php else: ?>
        <div class="alert alert-info py-2">Training runs are queued here and will be executed by the Python Intelligence Engine once the training endpoint is connected.</div>
        <div class="table-responsive">
          <table class="table table-sm table-hover align-middle">
            <thead class="table-light"><tr><th>Model</th><th>Use Case</th><th>Type</th><th>Target</th><th>Status</th><th>Accuracy</th><th>Runs</th><th>Predictions</th><th></th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="9" class="text-center text-muted py-3">No ML models registered.</td></tr>
            <?php else: foreach ($rows as $row): ?>
              <tr>
                <td><div class="fw-semibold"><?= h((string) ($row['ModelName'] ?? '')) ?></div><div class="small text-muted"><?= h((string) ($row['ModelCode'] ?? '')) ?></div></td>
                <td><?= h((string) ($row['UseCaseCode'] ?? '')) ?></td>
                <td><?= h((string) ($row['ModelTypeCode'] ?? '')) ?></td>
                <td><?= h((string) ($row['TargetColumnName'] ?? '')) ?></td>
                <td><span class="badge text-bg-<?= ((string) ($row['StatusCode'] ?? '')) === 'APPROVED' ? 'success' : 'secondary' ?>"><?= h((string) ($row['StatusCode'] ?? '')) ?></span></td>
                <td><?= h($row['AccuracyScore'] === null ? '' : number_format((float) $row['AccuracyScore'], 4)) ?></td>
                <td><?= h((string) (int) ($row['TrainingRunCount'] ?? 0)) ?></td>
                <td><?= h((string) (int) ($row['PredictionCount'] ?? 0)) ?></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <a class="btn btn-outline-secondary" href="index.php?route=intelligence/ml-model-detail&id=<?= h((string) (int) ($row['MLModelID'] ?? 0)) ?>">Open</a>
                    <?php if ($canAdmin): ?>
                      <a class="btn btn-outline-secondary" href="index.php?route=intelligence/ml-model-form&id=<?= h((string) (int) ($row['MLModelID'] ?? 0)) ?>">Edit</a>
                    <?php endif; ?>
                    <?php if ($canApprove && (string) ($row['StatusCode'] ?? '') !== 'APPROVED'): ?>
                      <form method="post" action="index.php?route=intelligence/approve-ml-model" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= h((string) $csrf) ?>">
                        <input type="hidden" name="MLModelID" value="<?= h((string) (int) ($row['MLModelID'] ?? 0)) ?>">
                        <button class="btn btn-outline-success" type="submit">Approve</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
