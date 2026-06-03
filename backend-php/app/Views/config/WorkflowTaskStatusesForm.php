<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$statusId = (int) ($record['StatusID'] ?? 0);
$screenHeader = [
    'title' => $statusId > 0 ? 'Edit Workflow Task Status' : 'Create Workflow Task Status',
    'icon' => 'bi-signpost-split',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Define the workflow status code and display name used by task transitions and completion logic.
      </div>

      <form method="post" action="index.php?route=workflow-task-statuses/save" id="workflow-task-statuses-form">
        <?= csrf_field() ?>
        <input type="hidden" name="StatusID" value="<?= $statusId ?>">

        <div class="card shadow-sm mb-4">
          <div class="card-header"><h5 class="mb-0">Status Details</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label" for="workflowTaskStatusCode">Code</label>
                <input type="text" class="form-control" id="workflowTaskStatusCode" name="Code" value="<?= h((string) ($record['Code'] ?? '')) ?>" maxlength="50" required>
              </div>
              <div class="col-md-8">
                <label class="form-label" for="workflowTaskStatusName">Name</label>
                <input type="text" class="form-control" id="workflowTaskStatusName" name="Name" value="<?= h((string) ($record['Name'] ?? '')) ?>" maxlength="150" required>
              </div>
              <div class="col-md-4">
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="workflowTaskStatusIsActive" name="IsActive" value="1" <?= ((int) ($record['IsActive'] ?? 1) === 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="workflowTaskStatusIsActive">Active</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <a id="workflow-task-statuses-back-btn" href="index.php?route=workflow-task-statuses/list" class="btn btn-outline-secondary">Back</a>
          <button id="workflow-task-statuses-save-btn" type="submit" class="btn btn-primary">Save Status</button>
        </div>
      </form>
    </div>
  </div>
</div>
