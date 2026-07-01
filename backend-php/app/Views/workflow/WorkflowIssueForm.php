<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('wf_issue_datetime_local')) {
    function wf_issue_datetime_local($value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d\TH:i', $ts) : '';
    }
}
if (!function_exists('wf_issue_date_value')) {
    function wf_issue_date_value($value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}
if (!function_exists('wf_issue_datetime_text')) {
    function wf_issue_datetime_text($value): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i', $ts) : '';
    }
}
if (!function_exists('wf_issue_user_label')) {
    function wf_issue_user_label(array $user): string
    {
        return trim((string)($user['DisplayName'] ?? '')) ?: (trim((string)($user['Username'] ?? '')) ?: ('User #' . (int)($user['UserID'] ?? 0)));
    }
}
if (!function_exists('wf_issue_format_file_size')) {
    function wf_issue_format_file_size($bytes): string
    {
        $bytes = max(0, (int)$bytes);
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}

$record = is_array($record ?? null) ? $record : [];
$workflowProjects = is_array($workflowProjects ?? null) ? $workflowProjects : [];
$selectedWorkflowProject = is_array($selectedWorkflowProject ?? null) ? $selectedWorkflowProject : [];
$workflowRequirements = is_array($workflowRequirements ?? null) ? $workflowRequirements : [];
$users = is_array($users ?? null) ? $users : [];
$issueTaskLinks = is_array($issueTaskLinks ?? null) ? $issueTaskLinks : [];
$issueAttachments = is_array($issueAttachments ?? null) ? $issueAttachments : [];
$issueAttachmentsInstalled = !empty($issueAttachmentsInstalled);
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$severityOptions = is_array($severityOptions ?? null) ? $severityOptions : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$priorityOptions = is_array($priorityOptions ?? null) ? $priorityOptions : [];
$tableInstalled = !empty($tableInstalled);
$canSaveIssue = !empty($canSaveIssue);
$canDeleteIssue = !empty($canDeleteIssue);
$canCreateWorkflowTask = !empty($canCreateWorkflowTask);
$issueId = (int)($record['WorkflowIssueID'] ?? 0);
$workflowProjectID = (int)($record['WorkflowProjectID'] ?? 0);
$workflowRequirementID = (int)($record['WorkflowRequirementID'] ?? 0);
$fieldsDisabled = !$canSaveIssue;
$returnTo = trim((string)($returnTo ?? ''));
$backUrl = trim((string)($backUrl ?? 'index.php?route=workflow-issues/list'));

$screenHeader = [
    'title' => $issueId > 0 ? __t('workflow_issue_edit') : __t('workflow_issue_create'),
    'icon' => 'bi-exclamation-triangle',
];
$currentIssueUrl = $issueId > 0 ? 'index.php?route=workflow-issues/form&id=' . $issueId : $backUrl;
$projectStartDate = wf_issue_date_value($selectedWorkflowProject['StartDate'] ?? '');
$projectEndDate = wf_issue_date_value($selectedWorkflowProject['TargetEndDate'] ?? '');
$issueTaskDueDate = wf_issue_date_value($record['DueDate'] ?? '');
if ($issueTaskDueDate === '' && $projectEndDate !== '') {
    $issueTaskDueDate = $projectEndDate;
}
if ($issueTaskDueDate !== '' && $projectStartDate !== '' && $issueTaskDueDate < $projectStartDate) {
    $issueTaskDueDate = $projectStartDate;
}
if ($issueTaskDueDate !== '' && $projectEndDate !== '' && $issueTaskDueDate > $projectEndDate) {
    $issueTaskDueDate = $projectEndDate;
}
$projectDateRangeText = '';
if ($projectStartDate !== '' || $projectEndDate !== '') {
    $projectDateRangeText = __t('workflow_issue_task_valid_date_range', [
        'start' => $projectStartDate !== '' ? $projectStartDate : '-',
        'end' => $projectEndDate !== '' ? $projectEndDate : '-',
    ]);
}
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!$tableInstalled): ?>
        <div class="alert alert-warning border-0 shadow-sm">
          <?= h(__t('workflow_issue_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
        </div>
      <?php endif; ?>

      <form id="workflow-issue-form" method="post" action="index.php?route=workflow-issues/save" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="WorkflowIssueID" value="<?= $issueId ?>">
        <input type="hidden" name="returnTo" value="<?= h($returnTo) ?>">

        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
          <a href="<?= h($backUrl) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= h(__t('back')) ?>
          </a>
          <div class="d-flex gap-2 flex-wrap">
            <?php if ($issueId > 0 && $workflowProjectID > 0 && $canCreateWorkflowTask): ?>
              <a id="workflow-issue-create-task-jump-btn" href="#issue-task-create" class="btn btn-outline-success">
                <i class="bi bi-plus-lg me-1"></i><?= h(__t('workflow_project_task')) ?>
              </a>
            <?php endif; ?>
            <?php if ($canSaveIssue): ?>
              <button id="workflow-issue-save-btn" type="submit" class="btn btn-primary">
                <i class="bi bi-save me-1"></i><?= h(__t('workflow_issue_save')) ?>
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-lg-8">
            <label class="form-label" for="IssueTitle"><?= h(__t('workflow_issue_title')) ?></label>
            <input type="text" class="form-control" id="IssueTitle" name="IssueTitle" value="<?= h((string)($record['IssueTitle'] ?? '')) ?>" maxlength="255" required <?= $fieldsDisabled ? 'disabled' : '' ?>>
            <div class="invalid-feedback"><?= h(__t('workflow_issue_title_required')) ?></div>
          </div>
          <div class="col-12 col-lg-4 cbms-readonly-field">
            <label class="form-label" for="IssueCode">
              <?= h(__t('workflow_issue_code')) ?>
              <span class="cbms-readonly-badge"><?= h(__t('read_only')) ?></span>
            </label>
            <input type="text" class="form-control cbms-readonly-control" id="IssueCode" value="<?= h((string)($record['IssueCode'] ?? ($issueId > 0 ? '' : __t('workflow_issue_code_generated_on_save')))) ?>" readonly aria-readonly="true">
          </div>
          <div class="col-12">
            <label class="form-label" for="IssueDescription"><?= h(__t('workflow_issue_description')) ?></label>
            <textarea class="form-control" id="IssueDescription" name="IssueDescription" rows="5" <?= $fieldsDisabled ? 'disabled' : '' ?>><?= h((string)($record['IssueDescription'] ?? '')) ?></textarea>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="WorkflowProjectID"><?= h(__t('workflow_project_project')) ?></label>
            <select class="form-select" id="WorkflowProjectID" name="WorkflowProjectID" <?= $fieldsDisabled ? 'disabled' : '' ?>>
              <option value=""><?= h(__t('workflow_project_no_project')) ?></option>
              <?php foreach ($workflowProjects as $project): ?>
                <?php $projectId = (int)($project['WorkflowProjectID'] ?? 0); ?>
                <option value="<?= $projectId ?>" <?= $workflowProjectID === $projectId ? 'selected' : '' ?>><?= h((string)($project['ProjectName'] ?? '')) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="WorkflowRequirementID"><?= h(__t('workflow_requirement')) ?></label>
            <select class="form-select" id="WorkflowRequirementID" name="WorkflowRequirementID" data-selected-requirement-id="<?= $workflowRequirementID ?>" <?= $fieldsDisabled ? 'disabled' : '' ?>>
              <option value=""><?= h(__t('workflow_requirement_no_parent')) ?></option>
              <?php foreach ($workflowRequirements as $requirement): ?>
                <?php $requirementId = (int)($requirement['WorkflowRequirementID'] ?? 0); ?>
                <option value="<?= $requirementId ?>" data-project-id="<?= (int)($requirement['WorkflowProjectID'] ?? 0) ?>" <?= $workflowRequirementID === $requirementId ? 'selected' : '' ?>>
                  <?= h(trim((string)($requirement['RequirementCode'] ?? '')) !== '' ? (string)$requirement['RequirementCode'] . ' - ' . (string)($requirement['RequirementTitle'] ?? '') : (string)($requirement['RequirementTitle'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><?= h(__t('workflow_issue_requirement_help')) ?></div>
          </div>

          <div class="col-6 col-md-3">
            <label class="form-label" for="IssueTypeCode"><?= h(__t('workflow_issue_type')) ?></label>
            <select class="form-select" id="IssueTypeCode" name="IssueTypeCode" required <?= $fieldsDisabled ? 'disabled' : '' ?>>
              <option value="" <?= trim((string)($record['IssueTypeCode'] ?? '')) === '' ? 'selected' : '' ?>><?= h(__t('workflow_issue_type_select')) ?></option>
              <?php foreach ($typeOptions as $code => $labelKey): ?>
                <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['IssueTypeCode'] ?? '')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= h(__t('workflow_issue_type_required')) ?></div>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label" for="SeverityCode"><?= h(__t('workflow_issue_severity')) ?></label>
            <select class="form-select" id="SeverityCode" name="SeverityCode" required <?= $fieldsDisabled ? 'disabled' : '' ?>>
              <?php foreach ($severityOptions as $code => $labelKey): ?>
                <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['SeverityCode'] ?? 'MEDIUM')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label" for="PriorityCode"><?= h(__t('workflow_requirement_priority')) ?></label>
            <select class="form-select" id="PriorityCode" name="PriorityCode" required <?= $fieldsDisabled ? 'disabled' : '' ?>>
              <?php foreach ($priorityOptions as $code => $labelKey): ?>
                <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['PriorityCode'] ?? 'SHOULD')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label" for="IssueStatusCode"><?= h(__t('status')) ?></label>
            <select class="form-select" id="IssueStatusCode" name="IssueStatusCode" required <?= $fieldsDisabled ? 'disabled' : '' ?>>
              <?php foreach ($statusOptions as $code => $labelKey): ?>
                <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['IssueStatusCode'] ?? 'OPEN')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label" for="RaisedByUserID"><?= h(__t('workflow_issue_raised_by')) ?></label>
            <select class="form-select" id="RaisedByUserID" name="RaisedByUserID" <?= $fieldsDisabled ? 'disabled' : '' ?>>
              <option value=""><?= h(__t('none')) ?></option>
              <?php foreach ($users as $user): ?>
                <?php $userId = (int)($user['UserID'] ?? 0); ?>
                <option value="<?= $userId ?>" <?= (int)($record['RaisedByUserID'] ?? 0) === $userId ? 'selected' : '' ?>><?= h(wf_issue_user_label($user)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label" for="OwnerUserID"><?= h(__t('workflow_issue_owner')) ?></label>
            <select class="form-select" id="OwnerUserID" name="OwnerUserID" <?= $fieldsDisabled ? 'disabled' : '' ?>>
              <option value=""><?= h(__t('none')) ?></option>
              <?php foreach ($users as $user): ?>
                <?php $userId = (int)($user['UserID'] ?? 0); ?>
                <option value="<?= $userId ?>" <?= (int)($record['OwnerUserID'] ?? 0) === $userId ? 'selected' : '' ?>><?= h(wf_issue_user_label($user)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="RaisedAt"><?= h(__t('workflow_issue_raised_at')) ?></label>
            <input type="datetime-local" class="form-control" id="RaisedAt" name="RaisedAt" value="<?= h(wf_issue_datetime_local($record['RaisedAt'] ?? '')) ?>" <?= $fieldsDisabled ? 'disabled' : '' ?>>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="DueDate"><?= h(__t('workflow_issue_due_date')) ?></label>
            <input type="date" class="form-control" id="DueDate" name="DueDate" value="<?= h(wf_issue_date_value($record['DueDate'] ?? '')) ?>" <?= $fieldsDisabled ? 'disabled' : '' ?>>
          </div>
          <div class="col-12 col-md-4">
            <label class="form-label" for="ResolvedAt"><?= h(__t('workflow_issue_resolved_at')) ?></label>
            <input type="datetime-local" class="form-control" id="ResolvedAt" name="ResolvedAt" value="<?= h(wf_issue_datetime_local($record['ResolvedAt'] ?? '')) ?>" <?= $fieldsDisabled ? 'disabled' : '' ?>>
          </div>
          <div class="col-12">
            <label class="form-label" for="ResolutionSummary"><?= h(__t('workflow_issue_resolution_summary')) ?></label>
            <textarea class="form-control" id="ResolutionSummary" name="ResolutionSummary" rows="3" <?= $fieldsDisabled ? 'disabled' : '' ?>><?= h((string)($record['ResolutionSummary'] ?? '')) ?></textarea>
          </div>
          <div class="col-12">
            <?php if (!$canDeleteIssue && $issueId > 0): ?>
              <input type="hidden" name="Active" value="<?= ((int)($record['Active'] ?? 1) === 1) ? '1' : '0' ?>">
            <?php endif; ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="Active" name="Active" value="1" <?= ((int)($record['Active'] ?? 1) === 1) ? 'checked' : '' ?> <?= ($fieldsDisabled || (!$canDeleteIssue && $issueId > 0)) ? 'disabled' : '' ?>>
              <label class="form-check-label" for="Active"><?= h(__t('workflow_project_active')) ?></label>
            </div>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap border-top mt-4 pt-3">
          <a href="<?= h($backUrl) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= h(__t('back')) ?>
          </a>
          <?php if ($canSaveIssue): ?>
            <button id="workflow-issue-save-footer-btn" type="submit" class="btn btn-primary">
              <i class="bi bi-save me-1"></i><?= h(__t('workflow_issue_save')) ?>
            </button>
          <?php endif; ?>
        </div>
      </form>

      <?php if ($canDeleteIssue && $issueId > 0 && (int)($record['Active'] ?? 0) === 1): ?>
        <form id="workflow-issue-delete-form" method="post" action="index.php?route=workflow-issues/delete" class="mt-2 text-end" data-confirm-message="<?= h(__t('workflow_issue_delete_confirm')) ?>" data-confirm-button="<?= h(__t('delete')) ?>" data-confirm-button-class="btn-danger">
          <?= csrf_field() ?>
          <input type="hidden" name="WorkflowIssueID" value="<?= $issueId ?>">
          <input type="hidden" name="returnTo" value="<?= h($backUrl) ?>">
          <button id="workflow-issue-delete-btn" type="submit" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-archive me-1"></i><?= h(__t('delete')) ?>
          </button>
        </form>
      <?php endif; ?>

      <div class="border-top mt-4 pt-4" id="issue-attachments">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
          <div>
            <div class="fw-semibold"><?= h(__t('workflow_issue_attachments')) ?></div>
            <?php if ($issueAttachmentsInstalled && $issueId > 0): ?>
              <div class="text-muted small"><?= h(__t('workflow_task_attached_file_count', ['count' => count($issueAttachments)])) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($issueAttachmentsInstalled && $issueAttachments !== []): ?>
            <span class="badge text-bg-light border"><?= count($issueAttachments) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($issueId <= 0): ?>
          <div class="alert alert-info py-2"><?= h(__t('workflow_issue_create_attachments_help')) ?></div>
        <?php elseif (!$issueAttachmentsInstalled): ?>
          <div class="alert alert-warning py-2"><?= h(__t('workflow_issue_attachments_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?></div>
        <?php else: ?>
          <?php if ($canSaveIssue): ?>
            <form id="workflow-issue-attachment-upload-form" method="post" action="index.php?route=workflow-issues/upload-attachment" enctype="multipart/form-data" class="row g-2 align-items-end mb-3 needs-validation" novalidate>
              <?= csrf_field() ?>
              <input type="hidden" name="WorkflowIssueID" value="<?= $issueId ?>">
              <div class="col-12 col-lg-10">
                <label class="form-label" for="IssueAttachment"><?= h(__t('workflow_issue_attach_file')) ?></label>
                <input type="file" class="form-control" id="IssueAttachment" name="IssueAttachment[]" multiple required>
                <div class="invalid-feedback"><?= h(__t('workflow_issue_choose_file')) ?></div>
              </div>
              <div class="col-12 col-lg-2 d-grid">
                <button id="workflow-issue-attachment-upload-btn" type="submit" class="btn btn-outline-primary">
                  <i class="bi bi-paperclip me-1"></i><?= h(__t('workflow_issue_upload_attachment')) ?>
                </button>
              </div>
            </form>
          <?php endif; ?>

          <?php if ($issueAttachments === []): ?>
            <p class="text-muted small mb-0"><?= h(__t('workflow_issue_no_attachments')) ?></p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th><?= h(__t('workflow_requirement_attachments')) ?></th>
                    <th><?= h(__t('workflow_issue_raised_by')) ?></th>
                    <th><?= h(__t('workflow_requirement_history_when')) ?></th>
                    <th class="text-end"><?= h(__t('actions')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($issueAttachments as $attachment): ?>
                    <?php $attachmentId = (int)($attachment['WorkflowIssueAttachmentID'] ?? 0); ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string)($attachment['OriginalFileName'] ?? 'attachment')) ?></div>
                        <div class="text-muted small"><?= h(wf_issue_format_file_size($attachment['FileSizeBytes'] ?? 0)) ?></div>
                      </td>
                      <td><?= h((string)($attachment['UploadedByName'] ?? '')) ?></td>
                      <td><?= h(wf_issue_datetime_text($attachment['UploadedAt'] ?? '')) ?></td>
                      <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end">
                          <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-issues/download-attachment&id=<?= $attachmentId ?>">
                            <i class="bi bi-download"></i>
                          </a>
                          <?php if ($canSaveIssue || $canDeleteIssue): ?>
                            <form method="post" action="index.php?route=workflow-issues/delete-attachment" data-confirm-message="<?= h(__t('workflow_issue_remove_attachment_confirm')) ?>" data-confirm-button="<?= h(__t('delete')) ?>" data-confirm-button-class="btn-danger">
                              <?= csrf_field() ?>
                              <input type="hidden" name="WorkflowIssueAttachmentID" value="<?= $attachmentId ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="border-top mt-4 pt-4" id="issue-task-create">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
          <div>
            <div class="fw-semibold"><?= h(__t('workflow_issue_create_task_from_issue')) ?></div>
            <div class="text-muted small"><?= h(__t('workflow_issue_create_task_help')) ?></div>
          </div>
          <span class="badge text-bg-light border"><?= count($issueTaskLinks) ?> <?= h(__t('workflow_requirement_traceability_tasks')) ?></span>
        </div>

        <?php if ($issueId <= 0): ?>
          <div class="alert alert-info py-2"><?= h(__t('workflow_issue_traceability_save_first')) ?></div>
        <?php elseif ($workflowProjectID <= 0): ?>
          <div class="alert alert-warning py-2"><?= h(__t('workflow_issue_task_requires_project')) ?></div>
        <?php elseif ($canCreateWorkflowTask): ?>
          <?php if ($projectDateRangeText !== ''): ?>
            <div class="alert alert-info py-2 mb-3">
              <?= h($projectDateRangeText) ?>
            </div>
          <?php endif; ?>
          <form id="workflow-issue-create-task-form" method="post" action="index.php?route=workflow-issues/create-task" class="row g-2 align-items-end mb-3 needs-validation" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="WorkflowIssueID" value="<?= $issueId ?>">
            <div class="col-12 col-lg-5">
              <label class="form-label" for="IssueTaskTitle"><?= h(__t('workflow_requirement_task_title')) ?></label>
              <input type="text" class="form-control" id="IssueTaskTitle" name="TaskTitle" value="<?= h(trim(((string)($record['IssueCode'] ?? '') !== '' ? (string)$record['IssueCode'] . ' - ' : '') . (string)($record['IssueTitle'] ?? ''))) ?>" maxlength="255" required>
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label" for="IssueTaskAssignee"><?= h(__t('workflow_requirement_task_assignee')) ?></label>
              <select class="form-select" id="IssueTaskAssignee" name="AssignedToUserID" required>
                <?php foreach ($users as $user): ?>
                  <?php $userId = (int)($user['UserID'] ?? 0); ?>
                  <option value="<?= $userId ?>" <?= (int)($record['OwnerUserID'] ?? 0) === $userId ? 'selected' : '' ?>><?= h(wf_issue_user_label($user)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-8 col-lg-2">
              <label class="form-label" for="IssueTaskDueDate"><?= h(__t('workflow_requirement_task_due_date')) ?></label>
              <input type="date"
                     class="form-control"
                     id="IssueTaskDueDate"
                     name="TaskDueDate"
                     value="<?= h($issueTaskDueDate) ?>"
                     <?= $projectStartDate !== '' ? 'min="' . h($projectStartDate) . '"' : '' ?>
                     <?= $projectEndDate !== '' ? 'max="' . h($projectEndDate) . '"' : '' ?>
                     required>
              <?php if ($projectDateRangeText !== ''): ?>
                <div class="form-text"><?= h($projectDateRangeText) ?></div>
              <?php endif; ?>
            </div>
            <div class="col-4 col-lg-1 d-grid">
              <button id="workflow-issue-create-task-submit-btn" type="submit" class="btn btn-outline-success"><i class="bi bi-plus-lg"></i></button>
            </div>
          </form>
        <?php endif; ?>

        <?php if ($issueTaskLinks === []): ?>
          <p class="text-muted small mb-0"><?= h(__t('workflow_issue_no_tasks')) ?></p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th><?= h(__t('workflow_task_task')) ?></th>
                  <th><?= h(__t('status')) ?></th>
                  <th><?= h(__t('workflow_requirement_task_due_date')) ?></th>
                  <th class="text-end"><?= h(__t('actions')) ?></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($issueTaskLinks as $link): ?>
                  <?php $taskId = (int)($link['WorkflowTaskID'] ?? 0); ?>
                  <?php if ($taskId <= 0) { continue; } ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string)($link['WorkflowTaskTitle'] ?? __t('workflow_task_task'))) ?></div>
                      <div class="text-muted small">#<?= $taskId ?></div>
                    </td>
                    <td><?= h((string)($link['WorkflowTaskStatusName'] ?? $link['WorkflowTaskStatusCode'] ?? '')) ?></td>
                    <td><?= h(wf_issue_date_value($link['WorkflowTaskDueDate'] ?? '')) ?></td>
                    <td class="text-end">
                      <a class="btn btn-sm btn-outline-primary" href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $workflowProjectID ?>&returnTo=<?= rawurlencode($currentIssueUrl) ?>"><?= h(__t('open')) ?></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const projectSelect = document.getElementById('WorkflowProjectID');
  const requirementSelect = document.getElementById('WorkflowRequirementID');
  if (!projectSelect || !requirementSelect) return;

  const syncRequirementOptions = () => {
    const projectId = projectSelect.value || '';
    let selectedVisible = false;
    Array.from(requirementSelect.options).forEach(option => {
      if (option.value === '') {
        option.hidden = false;
        return;
      }
      const optionProjectId = option.getAttribute('data-project-id') || '';
      const visible = projectId === '' || optionProjectId === projectId;
      option.hidden = !visible;
      if (visible && option.selected) selectedVisible = true;
    });
    if (!selectedVisible) {
      requirementSelect.value = '';
    }
  };

  projectSelect.addEventListener('change', syncRequirementOptions);
  syncRequirementOptions();
})();
</script>
