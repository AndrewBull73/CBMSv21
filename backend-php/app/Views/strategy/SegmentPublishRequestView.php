<?php
declare(strict_types=1);
/** @var array $request */
/** @var array $lines */
/** @var array $dimensions */
/** @var bool $canApprovePublication */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
$status = strtoupper(trim((string) ($request['RequestStatusCode'] ?? 'DRAFT')));
$canApprovePublication = !empty($canApprovePublication);
$dimensionLabels = [];
foreach ($dimensions as $dimension) {
    $dimensionLabels[(string) ($dimension['Code'] ?? '')] = (string) ($dimension['Label'] ?? $dimension['Code'] ?? '');
}
?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
    <div>
      <h1 class="h3 mb-1"><?= h((string) ($request['RequestTitle'] ?? 'Segment Publication Request')) ?></h1>
      <div class="text-muted">Status: <span class="badge text-bg-secondary"><?= h($status) ?></span></div>
      <?php if (!empty($request['RequestNotes'])): ?>
        <div class="mt-2"><?= nl2br(h((string) $request['RequestNotes'])) ?></div>
      <?php endif; ?>
    </div>
    <div class="d-flex gap-2 flex-wrap justify-content-end">
      <a href="index.php?route=strategy-publish/requests" class="btn btn-outline-secondary">Back</a>
      <?php if (in_array($status, ['DRAFT', 'REJECTED'], true)): ?>
        <a href="index.php?route=strategy-publish/request-form&id=<?= (int) $request['StrategicSegmentPublishRequestID'] ?>" class="btn btn-outline-primary">Edit Request</a>
        <a href="index.php?route=strategy-publish/line-form&request_id=<?= (int) $request['StrategicSegmentPublishRequestID'] ?>" class="btn btn-primary">Add Line</a>
        <form method="post" action="index.php?route=strategy-publish/transition" class="d-inline">
          <input type="hidden" name="request_id" value="<?= (int) $request['StrategicSegmentPublishRequestID'] ?>">
          <input type="hidden" name="publish_action" value="submit">
          <button type="submit" class="btn btn-success">Submit for Approval</button>
        </form>
      <?php endif; ?>
      <?php if ($status === 'PENDING' && $canApprovePublication): ?>
        <form method="post" action="index.php?route=strategy-publish/transition" class="d-inline">
          <input type="hidden" name="request_id" value="<?= (int) $request['StrategicSegmentPublishRequestID'] ?>">
          <input type="hidden" name="publish_action" value="approve">
          <button type="submit" class="btn btn-success">Approve</button>
        </form>
        <form method="post" action="index.php?route=strategy-publish/transition" class="d-inline">
          <input type="hidden" name="request_id" value="<?= (int) $request['StrategicSegmentPublishRequestID'] ?>">
          <input type="hidden" name="publish_action" value="reject">
          <button type="submit" class="btn btn-outline-danger">Reject</button>
        </form>
      <?php endif; ?>
      <?php if (in_array($status, ['APPROVED', 'PARTIAL'], true) && $canApprovePublication): ?>
        <form method="post" action="index.php?route=strategy-publish/publish" class="d-inline">
          <input type="hidden" name="request_id" value="<?= (int) $request['StrategicSegmentPublishRequestID'] ?>">
          <button type="submit" class="btn btn-success">Publish Approved Lines</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header">
      <h5 class="mb-0">Request Lines</h5>
    </div>
    <?php if ($status === 'PENDING' && !$canApprovePublication): ?>
      <div class="card-body border-bottom">
        <div class="alert alert-info mb-0">This request is pending approval. Only users with the <code>admin</code> or <code>strategy_approver</code> role can approve, reject, or publish it.</div>
      </div>
    <?php endif; ?>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Dimension</th>
              <th>DataObject</th>
              <th>Segment</th>
              <th>Parent</th>
              <th>Status</th>
              <th>Published</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($lines === []): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No lines added yet.</td></tr>
            <?php else: ?>
              <?php foreach ($lines as $line): ?>
                <tr>
                  <td><?= h($dimensionLabels[(string) ($line['StrategicDimensionCode'] ?? '')] ?? (string) ($line['StrategicDimensionCode'] ?? '')) ?></td>
                  <td><?= h((string) ($line['DataObjectCode'] ?? '')) ?></td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($line['SegmentCode'] ?? '')) ?></div>
                    <div class="text-muted small"><?= h((string) ($line['SegmentName'] ?? '')) ?></div>
                  </td>
                  <td>
                    <?php if (!empty($line['ParentSegmentNo']) || !empty($line['ParentSegmentCode'])): ?>
                      <div><?= h((string) ($line['ParentSegmentCode'] ?? '')) ?></div>
                      <div class="text-muted small">Segment <?= (int) ($line['ParentSegmentNo'] ?? 0) ?></div>
                    <?php else: ?>
                      <span class="text-muted">None</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge text-bg-secondary"><?= h((string) ($line['LineStatusCode'] ?? 'DRAFT')) ?></span>
                    <?php if (!empty($line['LineStatusNote'])): ?>
                      <div class="small text-muted mt-1"><?= h((string) $line['LineStatusNote']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($line['PublishedSegmentValueID'])): ?>
                      <span class="text-success">SegmentValueID <?= (int) $line['PublishedSegmentValueID'] ?></span>
                    <?php else: ?>
                      <span class="text-muted">Not yet</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if (in_array($status, ['DRAFT', 'REJECTED'], true) && in_array(strtoupper(trim((string) ($line['LineStatusCode'] ?? 'DRAFT'))), ['DRAFT', 'REJECTED', 'FAILED'], true)): ?>
                      <div class="btn-group btn-group-sm">
                        <a href="index.php?route=strategy-publish/line-form&request_id=<?= (int) $request['StrategicSegmentPublishRequestID'] ?>&id=<?= (int) $line['StrategicSegmentPublishRequestLineID'] ?>" class="btn btn-outline-primary">Edit</a>
                        <form method="post" action="index.php?route=strategy-publish/delete-line" onsubmit="return confirm('Remove this line?');">
                          <input type="hidden" name="id" value="<?= (int) $line['StrategicSegmentPublishRequestLineID'] ?>">
                          <input type="hidden" name="request_id" value="<?= (int) $request['StrategicSegmentPublishRequestID'] ?>">
                          <button type="submit" class="btn btn-outline-danger">Delete</button>
                        </form>
                      </div>
                    <?php endif; ?>
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
