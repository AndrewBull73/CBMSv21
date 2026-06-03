<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$workflowState = is_array($workflowState ?? null) ? $workflowState : [];
$returnRoute = (string) ($workflowReturnRoute ?? 'strategy/index');
$statusCode = strtoupper(trim((string) ($workflowState['WorkflowStatusCode'] ?? 'DRAFT')));
$statusLabel = (string) ($workflowState['WorkflowStatusLabel'] ?? 'Draft');
$statusMessage = (string) ($workflowState['StatusMessage'] ?? '');
$allowedActions = array_map(
    static fn(mixed $value): string => strtolower(trim((string) $value)),
    is_array($workflowState['AllowedActions'] ?? null) ? $workflowState['AllowedActions'] : []
);
$workflowInstalled = (bool) ($workflowState['WorkflowInstalled'] ?? false);
$workflowHistoryInstalled = (bool) ($workflowState['WorkflowHistoryInstalled'] ?? false);
$historyRows = is_array($workflowState['History'] ?? null) ? $workflowState['History'] : [];
$badgeClass = match ($statusCode) {
    'SUBMITTED' => 'text-bg-warning',
    'APPROVED' => 'text-bg-success',
    'LOCKED' => 'text-bg-dark',
    default => 'text-bg-secondary',
};
$actionLabels = [
    'submit' => 'Submit',
    'approve' => 'Approve',
    'lock' => 'Lock',
    'reopen' => 'Reopen to Draft',
    'unlock' => 'Unlock to Approved',
];
?>

<div class="card shadow-sm mb-4">
  <div class="card-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2">
    <div>
      <h5 class="mb-0">Strategic Workflow</h5>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="small text-muted">Status</span>
      <span class="badge <?= $badgeClass ?>"><?= h($statusLabel) ?></span>
    </div>
  </div>
  <div class="card-body">
    <p class="text-muted mb-3"><?= h($statusMessage !== '' ? $statusMessage : 'Workflow state is available for this strategic version.') ?></p>

    <?php if (!$workflowInstalled): ?>
      <div class="alert alert-warning mb-0">
        Run the <code>create_tblSbVersionWorkflow.sql</code> migration to enable submit, approve, and lock controls for strategic versions.
      </div>
    <?php else: ?>
      <div class="row g-3 mb-3">
        <div class="col-12 col-xl-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted mb-1">Submitted</div>
            <div class="fw-semibold"><?= h((string) ($workflowState['SubmittedByName'] ?? 'Not yet submitted')) ?></div>
            <?php if (!empty($workflowState['SubmittedDate'])): ?>
              <div class="small text-muted"><?= h((string) $workflowState['SubmittedDate']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-xl-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted mb-1">Approved</div>
            <div class="fw-semibold"><?= h((string) ($workflowState['ApprovedByName'] ?? 'Not yet approved')) ?></div>
            <?php if (!empty($workflowState['ApprovedDate'])): ?>
              <div class="small text-muted"><?= h((string) $workflowState['ApprovedDate']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-12 col-xl-4">
          <div class="border rounded p-3 h-100">
            <div class="small text-muted mb-1">Locked</div>
            <div class="fw-semibold"><?= h((string) ($workflowState['LockedByName'] ?? 'Not locked')) ?></div>
            <?php if (!empty($workflowState['LockedDate'])): ?>
              <div class="small text-muted"><?= h((string) $workflowState['LockedDate']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if (!empty($workflowState['StatusNote'])): ?>
        <div class="small text-muted mb-3">
          Latest note: <?= h((string) $workflowState['StatusNote']) ?>
        </div>
      <?php endif; ?>

      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($actionLabels as $actionCode => $label): ?>
          <?php if (!in_array($actionCode, $allowedActions, true)) { continue; } ?>
          <form method="post" action="index.php?route=strategy-workflow/transition" class="d-inline" id="strategy-workflow-action-form-<?= h($actionCode) ?>">
            <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="workflow_action" value="<?= h($actionCode) ?>">
            <input type="hidden" name="return_route" value="<?= h($returnRoute) ?>">
            <button type="submit" id="strategy-workflow-action-btn-<?= h($actionCode) ?>" class="btn btn-sm <?= in_array($actionCode, ['submit', 'approve', 'lock'], true) ? 'btn-primary' : 'btn-outline-secondary' ?>">
              <?= h($label) ?>
            </button>
          </form>
        <?php endforeach; ?>
      </div>

      <div class="mt-4">
        <h6 class="mb-2">Workflow History</h6>
        <?php if (!$workflowHistoryInstalled): ?>
          <div class="small text-muted">Run the latest workflow migration to start capturing transition history.</div>
        <?php elseif ($historyRows === []): ?>
          <div class="small text-muted">No workflow actions have been recorded for this version yet.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Action</th>
                  <th>From</th>
                  <th>To</th>
                  <th>By</th>
                  <th>Date</th>
                  <th>Note</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($historyRows as $row): ?>
                  <tr>
                    <td class="fw-semibold"><?= h((string) ($row['WorkflowActionLabel'] ?? '')) ?></td>
                    <td><?= h((string) ($row['FromStatusLabel'] ?? '')) ?></td>
                    <td><?= h((string) ($row['ToStatusLabel'] ?? '')) ?></td>
                    <td><?= h((string) ($row['ActionByName'] ?? ('User #' . (int) ($row['ActionBy'] ?? 0)))) ?></td>
                    <td><?= h((string) ($row['ActionDate'] ?? '')) ?></td>
                    <td><?= h((string) ($row['StatusNote'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
