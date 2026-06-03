<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$workflowAreas = is_array($workflowAreas ?? null) ? $workflowAreas : [];
$workflowStages = is_array($workflowStages ?? null) ? $workflowStages : [];
$csrf = h(csrf_token());
$ctxScopeCode = (string) ($ctxScopeCode ?? '');
$ctxScopeName = (string) ($ctxScopeName ?? '');
$tableInstalled = !empty($tableInstalled);

$orgUnitLabel = '';
if ($ctxScopeCode !== '') {
    $orgUnitLabel = 'Current Org Unit ' . $ctxScopeCode . ($ctxScopeName !== '' ? ' - ' . $ctxScopeName : '');
}
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Workflow Assignments</h3>
        <?php if ($orgUnitLabel !== ''): ?><div class="small text-muted mt-1"><?= h($orgUnitLabel) ?></div><?php endif; ?>
      </div>
      <a id="workflow-assignments-create-btn" href="index.php?route=workflow-assignments/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Assignment</a>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning alert-dismissible fade show">
          Run <code>create_tblWorkflowAssignments.sql</code> to install workflow assignment routing.
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="workflow-assignments/list">
        <input type="hidden" name="fy" value="<?= h((string) ($filters['fy'] ?? '')) ?>">
        <input type="hidden" name="version_id" value="<?= h((string) ($filters['version_id'] ?? '')) ?>">
        <input type="hidden" name="data_object_code" value="<?= h((string) ($filters['data_object_code'] ?? '')) ?>">
        <div class="col-md-4">
          <select name="workflow_area_code" class="form-select">
            <option value="">All workflow areas</option>
            <?php foreach ($workflowAreas as $area): ?>
              <?php $code = (string) ($area['code'] ?? ''); ?>
              <option value="<?= h($code) ?>" <?= (($filters['workflow_area_code'] ?? '') === $code) ? 'selected' : '' ?>><?= h((string) ($area['label'] ?? $code)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <select name="workflow_stage_code" class="form-select">
            <option value="">All stages</option>
            <?php foreach ($workflowStages as $stage): ?>
              <?php $code = (string) ($stage['code'] ?? ''); ?>
              <?php $stageAreaCode = (string) ($stage['workflow_area_code'] ?? ''); ?>
              <option value="<?= h($code) ?>" data-workflow-area-code="<?= h($stageAreaCode) ?>" <?= (($filters['workflow_stage_code'] ?? '') === $code) ? 'selected' : '' ?>><?= h((string) ($stage['label'] ?? $code)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="active" class="form-select">
            <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All statuses</option>
            <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Archived</option>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-outline-primary">Filter</button>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Assignment rules route workflow tasks for the active fiscal and DataScope context. Assignees shown here already match the required permissions for the selected workflow area and stage.</div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Workflow Area</th>
              <th>Stage</th>
              <th>Org Unit</th>
              <th>Assignee</th>
              <th>Sequence</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="7" class="text-center text-muted py-3">No workflow assignment rules found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?= h((string) ($row['WorkflowAreaCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['WorkflowStageCode'] ?? '')) ?></td>
                  <td>
                    <div><?= h((string) ($row['DataObjectCode'] ?? '')) ?></div>
                    <?php if (!empty($row['DataObjectName'])): ?><div class="small text-muted"><?= h((string) ($row['DataObjectName'] ?? '')) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <div><?= h((string) ($row['DisplayName'] ?? $row['Username'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['Username'] ?? '')) ?></div>
                    <?php if ((int) ($row['HasRequiredAccess'] ?? 1) !== 1): ?>
                      <div class="small text-danger mt-1"><?= h((string) ($row['AccessWarning'] ?? 'Assignee no longer has the required access.')) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= h((string) ($row['SequenceNo'] ?? '1')) ?>
                    <?php if ((int) ($row['IsPrimary'] ?? 0) === 1): ?><div class="small text-muted">Primary</div><?php endif; ?>
                  </td>
                  <td>
                    <span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                      <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Archived' ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <a href="index.php?route=workflow-assignments/form&id=<?= (int) ($row['WorkflowAssignmentID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <?php if ((int) ($row['ActiveFlag'] ?? 0) === 1): ?>
                        <form method="post" action="index.php?route=workflow-assignments/archive" onsubmit="return confirm('Archive this workflow assignment?');">
                          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                          <input type="hidden" name="WorkflowAssignmentID" value="<?= (int) ($row['WorkflowAssignmentID'] ?? 0) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-archive"></i>
                          </button>
                        </form>
                      <?php endif; ?>
                    </div>
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

<script>
(() => {
  const areaSelect = document.querySelector('select[name="workflow_area_code"]');
  const stageSelect = document.querySelector('select[name="workflow_stage_code"]');
  if (!areaSelect || !stageSelect) {
    return;
  }

  const stageOptions = Array.from(stageSelect.querySelectorAll('option')).map((option) => ({
    value: option.value,
    label: option.textContent || '',
    workflowAreaCode: option.dataset.workflowAreaCode || '',
    selected: option.selected
  }));

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

  areaSelect.addEventListener('change', renderStages);
  renderStages();
})();
</script>
