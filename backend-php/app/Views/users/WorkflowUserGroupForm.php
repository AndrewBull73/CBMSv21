<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$users = is_array($users ?? null) ? $users : [];
$selectedUserIds = array_fill_keys(array_map('intval', is_array($selectedUserIds ?? null) ? $selectedUserIds : []), true);
$tableInstalled = !empty($tableInstalled);
$groupId = (int)($record['WorkflowUserGroupID'] ?? 0);
$screenHeader = [
    'title' => $groupId > 0 ? 'Edit Workflow User Group' : 'Create Workflow User Group',
    'icon' => 'bi-people',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (is_array($flash ?? null) && !empty($flash['text'])): ?>
        <div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= $flash['text'] ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm">
          Workflow user groups have not been installed yet. Run
          <code>backend-php/config/sql/create_workflow_user_groups.sql</code>
          before maintaining groups.
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=workflow-user-groups/save" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="WorkflowUserGroupID" value="<?= $groupId ?>">

        <div class="card shadow-sm mb-4">
          <div class="card-header"><h5 class="mb-0">Group Details</h5></div>
          <div class="card-body">
            <div class="row g-3">
              <div class="col-12 col-lg-6">
                <label class="form-label" for="workflowGroupName">Group Name</label>
                <input type="text" class="form-control" id="workflowGroupName" name="GroupName" value="<?= h((string)($record['GroupName'] ?? '')) ?>" maxlength="255" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                <div class="invalid-feedback">Group name is required.</div>
              </div>
              <div class="col-12 col-lg-6">
                <label class="form-label" for="workflowGroupDescription">Description</label>
                <input type="text" class="form-control" id="workflowGroupDescription" name="Description" value="<?= h((string)($record['Description'] ?? '')) ?>" maxlength="500" <?= !$tableInstalled ? 'disabled' : '' ?>>
              </div>
              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" id="workflowGroupActive" name="Active" value="1" <?= ((int)($record['Active'] ?? 1) === 1) ? 'checked' : '' ?> <?= !$tableInstalled ? 'disabled' : '' ?>>
                  <label class="form-check-label" for="workflowGroupActive">Active</label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <h5 class="mb-0">Group Members</h5>
            <span class="text-muted small">Only active users are shown.</span>
          </div>
          <div class="card-body">
            <label class="form-label" for="workflowGroupMembers">Users</label>
            <select class="form-select" id="workflowGroupMembers" name="UserIDs[]" multiple size="14" <?= !$tableInstalled ? 'disabled' : '' ?>>
              <?php foreach ($users as $user): ?>
                <?php
                  $userId = (int)($user['UserID'] ?? 0);
                  if ($userId <= 0) {
                      continue;
                  }
                  $label = trim((string)($user['DisplayName'] ?? ''));
                  if ($label === '') {
                      $label = trim((string)($user['Username'] ?? ('User #' . $userId)));
                  }
                  $email = trim((string)($user['Email'] ?? ''));
                  if ($email !== '') {
                      $label .= ' - ' . $email;
                  }
                ?>
                <option value="<?= $userId ?>" <?= isset($selectedUserIds[$userId]) ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Hold Ctrl to select multiple users. Members receive individual workflow tasks when this group is selected on task creation.</div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center">
          <a href="index.php?route=workflow-user-groups/list" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
          </a>
          <button type="submit" class="btn btn-primary" <?= !$tableInstalled ? 'disabled' : '' ?>>
            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
            <i class="bi bi-save me-1"></i>Save Group
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
        form.classList.add('was-validated');
        return;
      }
      const submitter = event.submitter || form.querySelector('button[type="submit"]');
      if (submitter) {
        const spinner = submitter.querySelector('.spinner-border');
        const icon = submitter.querySelector('.bi');
        if (spinner) spinner.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');
        submitter.disabled = true;
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
