<?php
declare(strict_types=1);
/** @var array $requests */
/** @var bool $workflowInstalled */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 mb-1">Segment Publication Requests</h1>
      <div class="text-muted">Approval-controlled publication of new strategic segment values back to <code>tblSegmentValues</code>.</div>
    </div>
    <a href="index.php?route=strategy-publish/request-form" class="btn btn-primary">New Request</a>
  </div>

  <?php if (!$workflowInstalled): ?>
    <div class="alert alert-warning">Run <code>create_tblSbSegmentPublishRequest.sql</code> to enable segment publication approval workflow.</div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Status</th>
              <th class="text-end">Lines</th>
              <th class="text-end">Pending</th>
              <th class="text-end">Approved</th>
              <th class="text-end">Published</th>
              <th class="text-end">Failed</th>
              <th>Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($requests === []): ?>
              <tr><td colspan="10" class="text-center text-muted py-4">No publication requests yet.</td></tr>
            <?php else: ?>
              <?php foreach ($requests as $row): ?>
                <tr>
                  <td><?= (int) ($row['StrategicSegmentPublishRequestID'] ?? 0) ?></td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['RequestTitle'] ?? '')) ?></div>
                    <?php if (!empty($row['RequestNotes'])): ?>
                      <div class="small text-muted"><?= h((string) $row['RequestNotes']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge text-bg-secondary"><?= h((string) ($row['RequestStatusCode'] ?? 'DRAFT')) ?></span></td>
                  <td class="text-end"><?= (int) ($row['LineCount'] ?? 0) ?></td>
                  <td class="text-end"><?= (int) ($row['PendingCount'] ?? 0) ?></td>
                  <td class="text-end"><?= (int) ($row['ApprovedCount'] ?? 0) ?></td>
                  <td class="text-end"><?= (int) ($row['PublishedCount'] ?? 0) ?></td>
                  <td class="text-end"><?= (int) ($row['FailedCount'] ?? 0) ?></td>
                  <td><?= h((string) ($row['UpdatedDate'] ?? $row['CreatedDate'] ?? '')) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="index.php?route=strategy-publish/request-view&id=<?= (int) ($row['StrategicSegmentPublishRequestID'] ?? 0) ?>">Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
