<?php
declare(strict_types=1);
/** @var array|null $request */
/** @var bool $workflowInstalled */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
$request = is_array($request ?? null) ? $request : null;
?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h3 mb-1"><?= $request ? 'Edit Segment Publication Request' : 'New Segment Publication Request' ?></h1>
      <div class="text-muted">Create a request batch before adding the segment values that need approval.</div>
    </div>
    <a href="index.php?route=strategy-publish/requests" class="btn btn-outline-secondary">Back</a>
  </div>

  <?php if (!$workflowInstalled): ?>
    <div class="alert alert-warning">Run <code>create_tblSbSegmentPublishRequest.sql</code> to enable segment publication approval workflow.</div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" action="index.php?route=strategy-publish/save-request">
        <input type="hidden" name="StrategicSegmentPublishRequestID" value="<?= (int) ($request['StrategicSegmentPublishRequestID'] ?? 0) ?>">
        <div class="mb-3">
          <label class="form-label">Request Title</label>
          <input type="text" name="RequestTitle" class="form-control" required value="<?= h((string) ($request['RequestTitle'] ?? '')) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Request Notes</label>
          <textarea name="RequestNotes" class="form-control" rows="4"><?= h((string) ($request['RequestNotes'] ?? '')) ?></textarea>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">Save Request</button>
          <a href="index.php?route=strategy-publish/requests" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>
