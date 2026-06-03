<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$workflowAreas = is_array($workflowAreas ?? null) ? $workflowAreas : [];
$workflowStages = is_array($workflowStages ?? null) ? $workflowStages : [];
$workflowAccessRules = is_array($workflowAccessRules ?? null) ? $workflowAccessRules : [];
$users = is_array($users ?? null) ? $users : [];
$allUsers = is_array($allUsers ?? null) ? $allUsers : [];
$requiredPermissions = is_array($requiredPermissions ?? null) ? $requiredPermissions : [];
$ctxScopeCode = (string) ($ctxScopeCode ?? '');
$ctxScopeName = (string) ($ctxScopeName ?? '');

$id = (int) ($record['WorkflowAssignmentID'] ?? 0);
$form = [
    'WorkflowAssignmentID' => $id,
    'WorkflowAreaCode' => (string) ($record['WorkflowAreaCode'] ?? ''),
    'WorkflowStageCode' => (string) ($record['WorkflowStageCode'] ?? ''),
    'FiscalYearID' => (string) ($record['FiscalYearID'] ?? ($ctxFy > 0 ? (string) $ctxFy : '')),
    'VersionID' => (string) ($record['VersionID'] ?? ($ctxVer > 0 ? (string) $ctxVer : '')),
    'DataObjectCode' => (string) ($record['DataObjectCode'] ?? $ctxScopeCode),
    'UserID' => (string) ($record['UserID'] ?? ''),
    'SequenceNo' => (string) ($record['SequenceNo'] ?? '1'),
    'IsPrimary' => (int) ($record['IsPrimary'] ?? 0),
    'ActiveFlag' => (int) ($record['ActiveFlag'] ?? 1),
];

$allUsersForJs = array_map(static function (array $user): array {
    return [
        'UserID' => (int) ($user['UserID'] ?? 0),
        'Username' => (string) ($user['Username'] ?? ''),
        'DisplayName' => (string) ($user['DisplayName'] ?? ''),
        'PermissionCodes' => array_values(is_array($user['PermissionCodes'] ?? null) ? $user['PermissionCodes'] : []),
    ];
}, $allUsers);
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><?= $id > 0 ? 'Edit Workflow Assignment' : 'Create Workflow Assignment' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (empty($tableInstalled)): ?>
        <div class="alert alert-warning alert-dismissible fade show">
          Run <code>create_tblWorkflowAssignments.sql</code> to install workflow assignment routing.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=workflow-assignments/save" id="workflow-assignments-form">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="WorkflowAssignmentID" value="<?= (int) $form['WorkflowAssignmentID'] ?>">
        <input type="hidden" name="FiscalYearID" value="<?= h($form['FiscalYearID']) ?>">
        <input type="hidden" name="VersionID" value="<?= h($form['VersionID']) ?>">
        <input type="hidden" name="DataObjectCode" value="<?= h($form['DataObjectCode']) ?>">

        <div class="mb-3">
          <label class="form-label">Workflow Area</label>
          <select id="workflowAssignmentAreaCode" name="WorkflowAreaCode" class="form-select" required>
            <option value="">Select workflow area</option>
            <?php foreach ($workflowAreas as $area): ?>
              <?php $code = (string) ($area['code'] ?? ''); ?>
              <option value="<?= h($code) ?>" <?= $form['WorkflowAreaCode'] === $code ? 'selected' : '' ?>><?= h((string) ($area['label'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Stage</label>
          <select id="workflowAssignmentStageCode" name="WorkflowStageCode" class="form-select" required>
            <option value="">Select stage</option>
            <?php foreach ($workflowStages as $stage): ?>
              <?php $code = (string) ($stage['code'] ?? ''); ?>
              <?php $stageAreaCode = (string) ($stage['workflow_area_code'] ?? ''); ?>
              <option value="<?= h($code) ?>" data-workflow-area-code="<?= h($stageAreaCode) ?>" <?= $form['WorkflowStageCode'] === $code ? 'selected' : '' ?>><?= h((string) ($stage['label'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Assignee</label>
          <select id="workflowAssignmentUserID" name="UserID" class="form-select" required>
            <option value="">Select user</option>
            <?php foreach ($users as $user): ?>
              <?php $uid = (string) ($user['UserID'] ?? ''); ?>
              <option value="<?= h($uid) ?>" <?= $form['UserID'] === $uid ? 'selected' : '' ?>><?= h(trim((string) (($user['DisplayName'] ?? $user['Username'] ?? '') . ' (' . ($user['Username'] ?? '') . ')'))) ?></option>
            <?php endforeach; ?>
          </select>
          <div id="workflowAssignmentEligibilityHelp" class="small text-muted mt-1">
            <?php if ($requiredPermissions !== []): ?>
              Eligible users must currently have one of: <?= h(implode(', ', $requiredPermissions)) ?>
            <?php else: ?>
              Select a workflow area and stage to narrow the assignee list to eligible users.
            <?php endif; ?>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Sequence</label>
          <input id="workflowAssignmentSequenceNo" class="form-control" type="number" min="1" name="SequenceNo" value="<?= h($form['SequenceNo']) ?>">
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" name="IsPrimary" id="workflowAssignmentPrimary" <?= ((int) $form['IsPrimary'] === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="workflowAssignmentPrimary">Primary</label>
        </div>

        <div class="form-check mb-4">
          <input class="form-check-input" type="checkbox" name="ActiveFlag" id="workflowAssignmentActive" <?= ((int) $form['ActiveFlag'] === 1) ? 'checked' : '' ?>>
          <label class="form-check-label" for="workflowAssignmentActive">Active</label>
        </div>

        <div class="d-flex justify-content-between">
          <a id="workflow-assignments-back-btn" href="index.php?route=workflow-assignments/list" class="btn btn-secondary">Back</a>
          <button id="workflow-assignments-save-btn" type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(() => {
  const areaSelect = document.querySelector('select[name="WorkflowAreaCode"]');
  const stageSelect = document.querySelector('select[name="WorkflowStageCode"]');
  const userSelect = document.getElementById('workflowAssignmentUserID');
  const helpText = document.getElementById('workflowAssignmentEligibilityHelp');
  if (!areaSelect || !stageSelect || !userSelect || !helpText) {
    return;
  }

  const allUsers = <?= json_encode($allUsersForJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const accessRules = <?= json_encode($workflowAccessRules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const selectedUserId = <?= json_encode((string) $form['UserID']) ?>;
  const stageOptions = Array.from(stageSelect.querySelectorAll('option')).map((option) => ({
    value: option.value,
    label: option.textContent || '',
    workflowAreaCode: option.dataset.workflowAreaCode || ''
  }));

  function buildLabel(user) {
    const display = (user.DisplayName || '').trim();
    const username = (user.Username || '').trim();
    if (display && username) return `${display} (${username})`;
    return display || username || `User ${user.UserID}`;
  }

  function renderStages() {
    const area = (areaSelect.value || '').toUpperCase();
    const previous = stageSelect.value || '';
    const filtered = stageOptions.filter((option, index) => {
      if (index === 0 || option.value === '') {
        return true;
      }
      if (!option.workflowAreaCode) {
        return true;
      }
      return !area || option.workflowAreaCode.toUpperCase() === area;
    });

    stageSelect.innerHTML = '';
    filtered.forEach((option) => {
      const node = document.createElement('option');
      node.value = option.value;
      node.textContent = option.label;
      if (option.workflowAreaCode) {
        node.dataset.workflowAreaCode = option.workflowAreaCode;
      }
      if (option.value === previous) {
        node.selected = true;
      }
      stageSelect.appendChild(node);
    });

    if (previous && !filtered.some((option) => option.value === previous)) {
      stageSelect.value = '';
    }
  }

  function renderUsers() {
    const area = (areaSelect.value || '').toUpperCase();
    const stage = (stageSelect.value || '').toUpperCase();
    const required = (accessRules[area] && accessRules[area][stage]) ? accessRules[area][stage] : [];
    const previous = userSelect.value || selectedUserId || '';

    userSelect.innerHTML = '';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select user';
    userSelect.appendChild(placeholder);

    const eligibleUsers = allUsers.filter((user) => {
      if (!required.length) return true;
      const perms = Array.isArray(user.PermissionCodes) ? user.PermissionCodes : [];
      return required.some((perm) => perms.includes(perm));
    });

    eligibleUsers.forEach((user) => {
      const opt = document.createElement('option');
      opt.value = String(user.UserID || '');
      opt.textContent = buildLabel(user);
      if (opt.value === previous) {
        opt.selected = true;
      }
      userSelect.appendChild(opt);
    });

    if (previous && !eligibleUsers.some((user) => String(user.UserID || '') === previous)) {
      const existingUser = allUsers.find((user) => String(user.UserID || '') === previous);
      if (existingUser) {
        const opt = document.createElement('option');
        opt.value = previous;
        opt.textContent = `${buildLabel(existingUser)} [access mismatch]`;
        opt.selected = true;
        userSelect.appendChild(opt);
      }
    }

    if (required.length) {
      helpText.textContent = `Eligible users must currently have one of: ${required.join(', ')}`;
      if (previous && !eligibleUsers.some((user) => String(user.UserID || '') === previous)) {
        helpText.textContent += ' Current selection no longer matches the required access.';
      }
    } else {
      helpText.textContent = 'Select a workflow area and stage to narrow the assignee list to eligible users.';
    }
  }

  areaSelect.addEventListener('change', () => {
    renderStages();
    renderUsers();
  });
  stageSelect.addEventListener('change', renderUsers);
  renderStages();
  renderUsers();
})();
</script>
