<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wf_requirement_render_rich')) {
    function wf_requirement_render_rich($value): string
    {
        if (function_exists('workflow_render_rich_text')) {
            return workflow_render_rich_text((string)$value);
        }
        return nl2br(h((string)$value));
    }
}

if (!function_exists('wf_requirement_user_label')) {
    function wf_requirement_user_label(array $user): string
    {
        $userId = (int)($user['UserID'] ?? 0);
        $label = trim((string)($user['DisplayName'] ?? ''));
        if ($label === '') {
            $label = trim((string)($user['Username'] ?? ''));
        }
        return $label !== '' ? $label : __t('user_number', ['id' => $userId]);
    }
}

if (!function_exists('wf_requirement_datetime_local')) {
    function wf_requirement_datetime_local($value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
    }
}

if (!function_exists('wf_requirement_format_datetime')) {
    function wf_requirement_format_datetime($value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        return $ts === false ? $raw : date('Y-m-d H:i', $ts);
    }
}

if (!function_exists('wf_requirement_format_file_size')) {
    function wf_requirement_format_file_size($value): string
    {
        $bytes = max(0, (int)$value);
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}

if (!function_exists('wf_requirement_date_value')) {
    function wf_requirement_date_value($value): string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        return $ts === false ? '' : date('Y-m-d', $ts);
    }
}

if (!function_exists('wf_requirement_task_closed')) {
    function wf_requirement_task_closed(array $link): bool
    {
        $statusCode = strtoupper(trim((string)($link['WorkflowTaskStatusCode'] ?? '')));
        $statusName = strtoupper(trim((string)($link['WorkflowTaskStatusName'] ?? '')));
        return trim((string)($link['WorkflowTaskCompletedAt'] ?? '')) !== ''
            || in_array($statusCode, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
            || in_array($statusName, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true);
    }
}

if (!function_exists('wf_requirement_link_type_label')) {
    function wf_requirement_link_type_label(string $code): string
    {
        $key = 'workflow_link_type_' . strtolower($code);
        return __t($key) === $key ? str_replace('_', ' ', $code) : __t($key);
    }
}

if (!function_exists('wf_requirement_status_label')) {
    function wf_requirement_status_label(string $code, array $statusOptions): string
    {
        $code = strtoupper(trim($code));
        $labelKey = (string)($statusOptions[$code] ?? '');
        if ($labelKey !== '') {
            return __t($labelKey);
        }
        return $code !== '' ? str_replace('_', ' ', $code) : '-';
    }
}

if (!function_exists('wf_requirement_history_event_label')) {
    function wf_requirement_history_event_label(string $code): string
    {
        $code = strtoupper(trim($code));
        $key = 'workflow_requirement_history_event_' . strtolower($code);
        $label = __t($key);
        return $label === $key ? ($code !== '' ? str_replace('_', ' ', $code) : '-') : $label;
    }
}

if (!function_exists('wf_requirement_history_field_label')) {
    function wf_requirement_history_field_label(string $field): string
    {
        $labels = [
            'RequirementCode' => 'workflow_requirement_code',
            'WorkflowProjectID' => 'workflow_project_project',
            'ParentRequirementID' => 'workflow_requirement_parent',
            'RequirementLevelCode' => 'workflow_requirement_level',
            'ModuleCode' => 'workflow_requirement_module',
            'RequirementTitle' => 'workflow_requirement_title',
            'DeliveryClassCode' => 'workflow_requirement_delivery_class',
            'RequirementTypeCode' => 'workflow_requirement_type',
            'PriorityCode' => 'workflow_requirement_priority',
            'RequirementStatusCode' => 'status',
            'SourceDocument' => 'workflow_requirement_source_document',
            'SourceSection' => 'workflow_requirement_source_section',
            'Description' => 'description',
            'AcceptanceCriteria' => 'workflow_requirement_acceptance_criteria',
            'RequestedByUserID' => 'workflow_requirement_requested_by',
            'OwnerUserID' => 'workflow_requirement_owner',
            'ApprovedByUserID' => 'workflow_requirement_approved_by',
            'ApprovedAt' => 'workflow_requirement_approved_at',
            'Active' => 'workflow_project_active',
        ];
        if (isset($labels[$field])) {
            return __t($labels[$field]);
        }
        $label = preg_replace('/(?<!^)[A-Z]/', ' $0', $field) ?? $field;
        $label = str_replace(' I D', ' ID', $label);
        return trim($label) !== '' ? trim($label) : __t('workflow_requirement_history_details');
    }
}

if (!function_exists('wf_requirement_history_value')) {
    function wf_requirement_history_value($value): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '-';
        }
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);
        if (strlen($text) > 180) {
            return rtrim(substr($text, 0, 177)) . '...';
        }
        return $text !== '' ? $text : '-';
    }
}

$record = is_array($record ?? null) ? $record : [];
$deliveryClassOptions = is_array($deliveryClassOptions ?? null) ? $deliveryClassOptions : [];
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$priorityOptions = is_array($priorityOptions ?? null) ? $priorityOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$requirementLevelOptions = is_array($requirementLevelOptions ?? null) ? $requirementLevelOptions : [];
$workflowProjects = is_array($workflowProjects ?? null) ? $workflowProjects : [];
$parentRequirements = is_array($parentRequirements ?? null) ? $parentRequirements : [];
$childRequirements = is_array($childRequirements ?? null) ? $childRequirements : [];
$selectedWorkflowProject = is_array($selectedWorkflowProject ?? null) ? $selectedWorkflowProject : [];
$users = is_array($users ?? null) ? $users : [];
$requirementTraceability = is_array($requirementTraceability ?? null) ? $requirementTraceability : [];
$requirementLinks = is_array($requirementTraceability['requirementLinks'] ?? null) ? $requirementTraceability['requirementLinks'] : [];
$requirementRelatedLinks = is_array($requirementTraceability['relatedLinks'] ?? null) ? $requirementTraceability['relatedLinks'] : [];
$traceabilitySummary = is_array($requirementTraceability['summary'] ?? null) ? $requirementTraceability['summary'] : [];
$requirementAttachments = is_array($requirementAttachments ?? null) ? $requirementAttachments : [];
$requirementAttachmentsInstalled = !empty($requirementAttachmentsInstalled);
$requirementHistory = is_array($requirementHistory ?? null) ? $requirementHistory : [];
$requirementHistoryInstalled = !empty($requirementHistoryInstalled);
$requirementHierarchyInstalled = !empty($requirementHierarchyInstalled);
$canReviewRequirement = !empty($canReviewRequirement);
$canApproveRequirement = !empty($canApproveRequirement);
$workflowLinksInstalled = !empty($workflowLinksInstalled);
$tableInstalled = !empty($tableInstalled);
$requirementId = (int)($record['WorkflowRequirementID'] ?? 0);
$workflowProjectID = (int)($record['WorkflowProjectID'] ?? 0);
$parentRequirementID = (int)($record['ParentRequirementID'] ?? 0);
$currentRequirementLevel = strtoupper(trim((string)($record['RequirementLevelCode'] ?? 'HIGH_LEVEL'))) ?: 'HIGH_LEVEL';
$currentUserId = (int)($currentUserId ?? 0);
$currentRequirementStatus = strtoupper(trim((string)($record['RequirementStatusCode'] ?? 'DRAFT'))) ?: 'DRAFT';
$approvalStatusCodes = ['APPROVED', 'IN_BUILD', 'IN_TEST', 'COMPLETED'];
$reviewStatusOptions = [];
foreach ($statusOptions as $code => $labelKey) {
    $statusCode = strtoupper((string)$code);
    if (!$canApproveRequirement && in_array($statusCode, $approvalStatusCodes, true)) {
        continue;
    }
    $reviewStatusOptions[$statusCode] = $labelKey;
}
$taskLinks = array_values(array_filter($requirementLinks, static fn(array $link): bool => (int)($link['WorkflowTaskID'] ?? 0) > 0));
$defaultTaskTitle = trim((string)($record['RequirementCode'] ?? ''));
if ($defaultTaskTitle !== '') {
    $defaultTaskTitle .= ' - ';
}
$defaultTaskTitle .= trim((string)($record['RequirementTitle'] ?? ''));
$defaultTaskTitle = trim($defaultTaskTitle);
$defaultTaskAssigneeID = (int)($record['OwnerUserID'] ?? 0);
if ($defaultTaskAssigneeID <= 0) {
    $defaultTaskAssigneeID = $currentUserId;
}
$defaultTaskDueDate = wf_requirement_date_value($selectedWorkflowProject['TargetEndDate'] ?? '');
if ($defaultTaskDueDate === '') {
    $defaultTaskDueDate = date('Y-m-d', strtotime('+14 days'));
}

$screenHeader = [
    'title' => $requirementId > 0 ? __t('workflow_requirement_edit') : __t('workflow_requirement_create'),
    'icon' => 'bi-journal-richtext',
];
$returnTo = trim((string)($returnTo ?? ''));
$backUrl = trim((string)($backUrl ?? ''));
if ($backUrl === '') {
    $backUrl = $parentRequirementID > 0
        ? 'index.php?route=workflow-requirements/form&id=' . $parentRequirementID
        : 'index.php?route=workflow-requirements/list' . ($workflowProjectID > 0 ? '&workflowProjectID=' . $workflowProjectID : '');
}
$currentRequirementUrl = $requirementId > 0 ? 'index.php?route=workflow-requirements/form&id=' . $requirementId : $backUrl;
$currentRequirementReturnParam = rawurlencode($currentRequirementUrl);
?>

<style>
  .requirement-rich-text-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: .25rem;
    margin-bottom: .35rem;
  }
  .requirement-rich-text-source {
    display: none;
  }
  .requirement-rich-text-editor {
    min-height: 9rem;
    overflow: auto;
  }
  .requirement-rich-text-editor:empty::before {
    content: attr(data-placeholder);
    color: #6c757d;
  }
  .requirement-rich-text-editor:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25);
    outline: 0;
  }
  .requirement-rich-text-content p,
  .requirement-rich-text-editor p {
    margin-bottom: .55rem;
  }
  .requirement-rich-text-content ul,
  .requirement-rich-text-content ol,
  .requirement-rich-text-editor ul,
  .requirement-rich-text-editor ol {
    margin-bottom: .55rem;
    padding-left: 1.35rem;
  }
  .requirement-attachment-name {
    word-break: break-word;
  }
  .requirement-attachment-meta {
    color: #6c757d;
    font-size: .8125rem;
  }
  .requirement-traceability-stat {
    min-width: 7.25rem;
  }
  .requirement-traceability-title {
    max-width: 34rem;
  }
  .requirement-edit-tabs {
    gap: .25rem;
    overflow-x: auto;
    overflow-y: hidden;
    flex-wrap: nowrap;
  }
  .requirement-edit-tabs .nav-link {
    white-space: nowrap;
  }
  .requirement-edit-tab-content {
    padding-top: 1rem;
  }
  .requirement-edit-tab-content > .tab-pane {
    display: none;
  }
  .requirement-edit-tab-content > .active {
    display: block;
  }
  .requirement-edit-section {
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 1rem;
  }
  .requirement-edit-section + .requirement-edit-section {
    padding-top: 1rem;
  }
  .requirement-section-title {
    display: flex;
    align-items: center;
    gap: .45rem;
    margin-bottom: .85rem;
    font-weight: 600;
    color: #23364d;
  }
  .requirement-edit-section:last-child {
    border-bottom: 0;
    padding-bottom: 0;
  }
  .requirement-sidebar-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: .85rem 1rem;
  }
  .requirement-sidebar-grid .span-2 {
    grid-column: 1 / -1;
  }
  .requirement-form-actions {
    border-top: 1px solid #e2e8f0;
    margin-top: 1.25rem;
    padding-top: 1rem;
  }
  @media (max-width: 767.98px) {
    .requirement-form-actions .btn,
    .requirement-form-actions-top .btn {
      width: 100%;
    }
    .requirement-sidebar-grid {
      grid-template-columns: 1fr;
    }
    .requirement-sidebar-grid .span-2 {
      grid-column: auto;
    }
  }
</style>

<div class="container-fluid px-3 px-xl-4 mt-4">
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
          <?= h(__t('workflow_requirement_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=workflow-requirements/save" class="needs-validation" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="WorkflowRequirementID" value="<?= $requirementId ?>">
        <input type="hidden" name="returnTo" value="<?= h($returnTo) ?>">

        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3 requirement-form-actions-top">
          <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary" <?= !$tableInstalled ? 'disabled' : '' ?>>
              <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
              <i class="bi bi-save me-1"></i><?= h(__t('workflow_requirement_save')) ?>
            </button>
            <?php if ($workflowProjectID > 0 && $requirementId > 0): ?>
              <a href="index.php?route=workflow/edit&workflowProjectID=<?= $workflowProjectID ?>&workflowRequirementID=<?= $requirementId ?>" class="btn btn-outline-success">
                <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_project_create_task')) ?>
              </a>
              <a href="index.php?route=workflow-projects/summary&id=<?= $workflowProjectID ?>" class="btn btn-outline-info">
                <i class="bi bi-speedometer2 me-1"></i><?= h(__t('workflow_project_summary')) ?>
              </a>
            <?php endif; ?>
          </div>
          <a href="<?= h($backUrl) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= h(__t('back')) ?>
          </a>
        </div>

        <ul class="nav nav-tabs requirement-edit-tabs" id="RequirementEditTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="RequirementContentTabButton" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#RequirementContentTab" aria-controls="RequirementContentTab" aria-selected="true">
              <i class="bi bi-journal-text me-1"></i><?= h(__t('workflow_requirement')) ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="RequirementAcceptanceTabButton" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#RequirementAcceptanceTab" aria-controls="RequirementAcceptanceTab" aria-selected="false">
              <i class="bi bi-check2-square me-1"></i><?= h(__t('workflow_requirement_acceptance_criteria')) ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="RequirementDetailsTabButton" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#RequirementDetailsTab" aria-controls="RequirementDetailsTab" aria-selected="false">
              <i class="bi bi-sliders me-1"></i><?= h(__t('details')) ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="RequirementReviewSourceTabButton" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#RequirementReviewSourceTab" aria-controls="RequirementReviewSourceTab" aria-selected="false">
              <i class="bi bi-person-lines-fill me-1"></i><?= h(__t('workflow_requirement_review_approval')) ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="RequirementTraceabilityTabButton" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#RequirementTraceabilityTab" aria-controls="RequirementTraceabilityTab" aria-selected="false">
              <i class="bi bi-diagram-3 me-1"></i><?= h(__t('workflow_requirement_traceability')) ?>
            </button>
          </li>
        </ul>

        <div class="tab-content requirement-edit-tab-content" id="RequirementEditTabContent">
          <div class="tab-pane fade show active" id="RequirementContentTab" role="tabpanel" aria-labelledby="RequirementContentTabButton" tabindex="0">
            <section class="requirement-edit-section">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label" for="RequirementTitle"><?= h(__t('workflow_requirement_title')) ?></label>
                  <input type="text" class="form-control" id="RequirementTitle" name="RequirementTitle" value="<?= h((string)($record['RequirementTitle'] ?? '')) ?>" maxlength="255" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                  <div class="invalid-feedback"><?= h(__t('workflow_requirement_title_required')) ?></div>
                </div>
              </div>
            </section>

            <section class="requirement-edit-section">
              <div class="requirement-section-title">
                <i class="bi bi-card-text"></i><?= h(__t('description')) ?>
              </div>
              <div data-requirement-rich-text>
                <label class="form-label" for="RequirementDescription"><?= h(__t('description')) ?></label>
                <div class="requirement-rich-text-toolbar" role="toolbar" aria-label="<?= h(__t('description')) ?>">
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="bold" title="<?= h(__t('workflow_task_description_format_bold')) ?>" aria-label="<?= h(__t('workflow_task_description_format_bold')) ?>"><i class="bi bi-type-bold"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="italic" title="<?= h(__t('workflow_task_description_format_italic')) ?>" aria-label="<?= h(__t('workflow_task_description_format_italic')) ?>"><i class="bi bi-type-italic"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="underline" title="<?= h(__t('workflow_task_description_format_underline')) ?>" aria-label="<?= h(__t('workflow_task_description_format_underline')) ?>"><i class="bi bi-type-underline"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="insertUnorderedList" title="<?= h(__t('workflow_task_description_format_bullets')) ?>" aria-label="<?= h(__t('workflow_task_description_format_bullets')) ?>"><i class="bi bi-list-ul"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="insertOrderedList" title="<?= h(__t('workflow_task_description_format_numbers')) ?>" aria-label="<?= h(__t('workflow_task_description_format_numbers')) ?>"><i class="bi bi-list-ol"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="createLink" title="<?= h(__t('workflow_task_description_format_link')) ?>" aria-label="<?= h(__t('workflow_task_description_format_link')) ?>"><i class="bi bi-link-45deg"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="removeFormat" title="<?= h(__t('workflow_task_description_format_clear')) ?>" aria-label="<?= h(__t('workflow_task_description_format_clear')) ?>"><i class="bi bi-eraser"></i></button>
                </div>
                <div class="form-control requirement-rich-text-editor" contenteditable="<?= $tableInstalled ? 'true' : 'false' ?>" data-requirement-rich-editor data-placeholder="<?= h(__t('description')) ?>"><?= wf_requirement_render_rich($record['Description'] ?? '') ?></div>
                <textarea id="RequirementDescription" name="Description" class="form-control requirement-rich-text-source" rows="5" <?= !$tableInstalled ? 'disabled' : '' ?>><?= h((string)($record['Description'] ?? '')) ?></textarea>
              </div>
            </section>
          </div>

          <div class="tab-pane fade" id="RequirementAcceptanceTab" role="tabpanel" aria-labelledby="RequirementAcceptanceTabButton" tabindex="0">
            <section class="requirement-edit-section">
              <div data-requirement-rich-text>
                <label class="form-label" for="AcceptanceCriteria"><?= h(__t('workflow_requirement_acceptance_criteria')) ?></label>
                <div class="requirement-rich-text-toolbar" role="toolbar" aria-label="<?= h(__t('workflow_requirement_acceptance_criteria')) ?>">
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="bold" title="<?= h(__t('workflow_task_description_format_bold')) ?>" aria-label="<?= h(__t('workflow_task_description_format_bold')) ?>"><i class="bi bi-type-bold"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="italic" title="<?= h(__t('workflow_task_description_format_italic')) ?>" aria-label="<?= h(__t('workflow_task_description_format_italic')) ?>"><i class="bi bi-type-italic"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="underline" title="<?= h(__t('workflow_task_description_format_underline')) ?>" aria-label="<?= h(__t('workflow_task_description_format_underline')) ?>"><i class="bi bi-type-underline"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="insertUnorderedList" title="<?= h(__t('workflow_task_description_format_bullets')) ?>" aria-label="<?= h(__t('workflow_task_description_format_bullets')) ?>"><i class="bi bi-list-ul"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="insertOrderedList" title="<?= h(__t('workflow_task_description_format_numbers')) ?>" aria-label="<?= h(__t('workflow_task_description_format_numbers')) ?>"><i class="bi bi-list-ol"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="createLink" title="<?= h(__t('workflow_task_description_format_link')) ?>" aria-label="<?= h(__t('workflow_task_description_format_link')) ?>"><i class="bi bi-link-45deg"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" data-requirement-rich-command="removeFormat" title="<?= h(__t('workflow_task_description_format_clear')) ?>" aria-label="<?= h(__t('workflow_task_description_format_clear')) ?>"><i class="bi bi-eraser"></i></button>
                </div>
                <div class="form-control requirement-rich-text-editor" contenteditable="<?= $tableInstalled ? 'true' : 'false' ?>" data-requirement-rich-editor data-placeholder="<?= h(__t('workflow_requirement_acceptance_criteria')) ?>"><?= wf_requirement_render_rich($record['AcceptanceCriteria'] ?? '') ?></div>
                <textarea id="AcceptanceCriteria" name="AcceptanceCriteria" class="form-control requirement-rich-text-source" rows="5" <?= !$tableInstalled ? 'disabled' : '' ?>><?= h((string)($record['AcceptanceCriteria'] ?? '')) ?></textarea>
              </div>
            </section>
          </div>

          <div class="tab-pane fade" id="RequirementDetailsTab" role="tabpanel" aria-labelledby="RequirementDetailsTabButton" tabindex="0">
            <section class="requirement-edit-section">
              <div class="requirement-sidebar-grid">
                <div>
                  <label class="form-label" for="RequirementCode"><?= h(__t('workflow_requirement_code')) ?></label>
                  <input type="text" class="form-control" id="RequirementCode" name="RequirementCode" value="<?= h((string)($record['RequirementCode'] ?? '')) ?>" maxlength="50" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
                <div>
                  <label class="form-label" for="ModuleCode"><?= h(__t('workflow_requirement_module')) ?></label>
                  <input type="text" class="form-control" id="ModuleCode" name="ModuleCode" value="<?= h((string)($record['ModuleCode'] ?? '')) ?>" maxlength="100" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="span-2">
                  <label class="form-label" for="WorkflowProjectID"><?= h(__t('workflow_project_project')) ?></label>
                  <select class="form-select" id="WorkflowProjectID" name="WorkflowProjectID" <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <option value=""><?= h(__t('workflow_project_no_project')) ?></option>
                    <?php foreach ($workflowProjects as $project): ?>
                      <?php $projectId = (int)($project['WorkflowProjectID'] ?? 0); ?>
                      <?php if ($projectId <= 0) { continue; } ?>
                      <option value="<?= $projectId ?>" <?= $workflowProjectID === $projectId ? 'selected' : '' ?>>
                        <?= h((string)($project['ProjectName'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="form-label" for="RequirementStatusCode"><?= h(__t('status')) ?></label>
                  <select class="form-select" id="RequirementStatusCode" name="RequirementStatusCode" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <?php foreach ($statusOptions as $code => $labelKey): ?>
                      <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['RequirementStatusCode'] ?? 'DRAFT')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="form-label" for="PriorityCode"><?= h(__t('workflow_requirement_priority')) ?></label>
                  <select class="form-select" id="PriorityCode" name="PriorityCode" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <?php foreach ($priorityOptions as $code => $labelKey): ?>
                      <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['PriorityCode'] ?? 'SHOULD')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="form-label" for="DeliveryClassCode"><?= h(__t('workflow_requirement_delivery_class')) ?></label>
                  <select class="form-select" id="DeliveryClassCode" name="DeliveryClassCode" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <?php foreach ($deliveryClassOptions as $code => $labelKey): ?>
                      <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['DeliveryClassCode'] ?? 'ENHANCEMENT')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div>
                  <label class="form-label" for="RequirementTypeCode"><?= h(__t('workflow_requirement_type')) ?></label>
                  <select class="form-select" id="RequirementTypeCode" name="RequirementTypeCode" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <?php foreach ($typeOptions as $code => $labelKey): ?>
                      <option value="<?= h((string)$code) ?>" <?= strtoupper((string)($record['RequirementTypeCode'] ?? 'FUNCTIONAL')) === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <?php if ($requirementHierarchyInstalled): ?>
                  <div>
                    <label class="form-label" for="RequirementLevelCode"><?= h(__t('workflow_requirement_level')) ?></label>
                    <select class="form-select" id="RequirementLevelCode" name="RequirementLevelCode" required <?= !$tableInstalled ? 'disabled' : '' ?>>
                      <?php foreach ($requirementLevelOptions as $code => $labelKey): ?>
                        <option value="<?= h((string)$code) ?>" <?= $currentRequirementLevel === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="span-2">
                    <label class="form-label" for="ParentRequirementID"><?= h(__t('workflow_requirement_parent')) ?></label>
                    <select class="form-select" id="ParentRequirementID" name="ParentRequirementID" data-requirement-parent-select <?= !$tableInstalled ? 'disabled' : '' ?>>
                      <option value=""><?= h(__t('workflow_requirement_no_parent')) ?></option>
                      <?php foreach ($parentRequirements as $parentRequirement): ?>
                        <?php $parentId = (int)($parentRequirement['WorkflowRequirementID'] ?? 0); ?>
                        <?php if ($parentId <= 0) { continue; } ?>
                        <option value="<?= $parentId ?>" <?= $parentRequirementID === $parentId ? 'selected' : '' ?>>
                          <?= h(trim((string)($parentRequirement['RequirementCode'] ?? '')) !== '' ? (string)$parentRequirement['RequirementCode'] . ' - ' . (string)($parentRequirement['RequirementTitle'] ?? '') : (string)($parentRequirement['RequirementTitle'] ?? '')) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?= h(__t('workflow_requirement_parent_help')) ?></div>
                  </div>
                <?php endif; ?>
                <div class="span-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="Active" name="Active" value="1" <?= ((int)($record['Active'] ?? 1) === 1) ? 'checked' : '' ?> <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="Active"><?= h(__t('workflow_project_active')) ?></label>
                  </div>
                </div>
              </div>
            </section>
          </div>

          <div class="tab-pane fade" id="RequirementReviewSourceTab" role="tabpanel" aria-labelledby="RequirementReviewSourceTabButton" tabindex="0">
            <section class="requirement-edit-section">
              <div class="requirement-sidebar-grid">
                <div class="span-2">
                  <label class="form-label" for="RequestedByUserID"><?= h(__t('workflow_requirement_requested_by')) ?></label>
                  <select class="form-select" id="RequestedByUserID" name="RequestedByUserID" <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <option value=""><?= h(__t('none')) ?></option>
                    <?php foreach ($users as $user): ?>
                      <?php $userId = (int)($user['UserID'] ?? 0); ?>
                      <?php if ($userId <= 0) { continue; } ?>
                      <option value="<?= $userId ?>" <?= (int)($record['RequestedByUserID'] ?? 0) === $userId ? 'selected' : '' ?>><?= h(wf_requirement_user_label($user)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="span-2">
                  <label class="form-label" for="OwnerUserID"><?= h(__t('workflow_requirement_owner')) ?></label>
                  <select class="form-select" id="OwnerUserID" name="OwnerUserID" <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <option value=""><?= h(__t('none')) ?></option>
                    <?php foreach ($users as $user): ?>
                      <?php $userId = (int)($user['UserID'] ?? 0); ?>
                      <?php if ($userId <= 0) { continue; } ?>
                      <option value="<?= $userId ?>" <?= (int)($record['OwnerUserID'] ?? 0) === $userId ? 'selected' : '' ?>><?= h(wf_requirement_user_label($user)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="span-2">
                  <label class="form-label" for="ApprovedByUserID"><?= h(__t('workflow_requirement_approved_by')) ?></label>
                  <select class="form-select" id="ApprovedByUserID" name="ApprovedByUserID" <?= !$tableInstalled ? 'disabled' : '' ?>>
                    <option value=""><?= h(__t('none')) ?></option>
                    <?php foreach ($users as $user): ?>
                      <?php $userId = (int)($user['UserID'] ?? 0); ?>
                      <?php if ($userId <= 0) { continue; } ?>
                      <option value="<?= $userId ?>" <?= (int)($record['ApprovedByUserID'] ?? 0) === $userId ? 'selected' : '' ?>><?= h(wf_requirement_user_label($user)) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="span-2">
                  <label class="form-label" for="ApprovedAt"><?= h(__t('workflow_requirement_approved_at')) ?></label>
                  <input type="datetime-local" class="form-control" id="ApprovedAt" name="ApprovedAt" value="<?= h(wf_requirement_datetime_local($record['ApprovedAt'] ?? '')) ?>" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
              </div>
            </section>

            <section class="requirement-edit-section">
              <div class="requirement-section-title">
                <i class="bi bi-file-earmark-text"></i><?= h(__t('source')) ?>
              </div>
              <div class="requirement-sidebar-grid">
                <div class="span-2">
                  <label class="form-label" for="SourceDocument"><?= h(__t('workflow_requirement_source_document')) ?></label>
                  <input type="text" class="form-control" id="SourceDocument" name="SourceDocument" value="<?= h((string)($record['SourceDocument'] ?? '')) ?>" maxlength="255" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
                <div class="span-2">
                  <label class="form-label" for="SourceSection"><?= h(__t('workflow_requirement_source_section')) ?></label>
                  <input type="text" class="form-control" id="SourceSection" name="SourceSection" value="<?= h((string)($record['SourceSection'] ?? '')) ?>" maxlength="255" <?= !$tableInstalled ? 'disabled' : '' ?>>
                </div>
              </div>
            </section>

          </div>

          <div class="tab-pane fade" id="RequirementTraceabilityTab" role="tabpanel" aria-labelledby="RequirementTraceabilityTabButton" tabindex="0"></div>
        </div>

        <div class="requirement-form-actions d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <a href="<?= h($backUrl) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= h(__t('back')) ?>
          </a>
          <div class="d-flex gap-2 flex-wrap">
            <?php if ($workflowProjectID > 0 && $requirementId > 0): ?>
              <a href="index.php?route=workflow/edit&workflowProjectID=<?= $workflowProjectID ?>&workflowRequirementID=<?= $requirementId ?>" class="btn btn-outline-success">
                <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_project_create_task')) ?>
              </a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" <?= !$tableInstalled ? 'disabled' : '' ?>>
              <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
              <i class="bi bi-save me-1"></i><?= h(__t('workflow_requirement_save')) ?>
            </button>
          </div>
        </div>
      </form>

      <?php if ($requirementHierarchyInstalled): ?>
        <div class="border-top mt-4 pt-4 d-none" data-requirement-tab-panel="RequirementDetailsTab">
          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
            <div>
              <div class="fw-semibold"><?= h(__t('workflow_requirement_hierarchy')) ?></div>
              <div class="text-muted small"><?= h(__t('workflow_requirement_hierarchy_help')) ?></div>
            </div>
            <?php if ($requirementId > 0): ?>
              <span class="badge text-bg-light border"><?= h(wf_requirement_status_label($currentRequirementLevel, $requirementLevelOptions)) ?></span>
            <?php endif; ?>
          </div>

          <?php if ($requirementId <= 0): ?>
            <div class="alert alert-info py-2 mb-0">
              <?= h(__t('workflow_requirement_hierarchy_save_first')) ?>
            </div>
          <?php elseif ($currentRequirementLevel === 'DETAILED'): ?>
            <div class="row g-3">
              <div class="col-12 col-lg-7">
                <div class="small text-muted"><?= h(__t('workflow_requirement_parent')) ?></div>
                <?php if ($parentRequirementID > 0): ?>
                  <a class="fw-semibold" href="index.php?route=workflow-requirements/form&id=<?= $parentRequirementID ?>&returnTo=<?= $currentRequirementReturnParam ?>">
                    <?= h(trim((string)($record['ParentRequirementCode'] ?? '')) !== '' ? (string)$record['ParentRequirementCode'] . ' - ' . (string)($record['ParentRequirementTitle'] ?? '') : (string)($record['ParentRequirementTitle'] ?? '')) ?>
                  </a>
                <?php else: ?>
                  <div class="text-muted"><?= h(__t('workflow_requirement_no_parent')) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php else: ?>
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
              <div class="fw-semibold"><?= h(__t('workflow_requirement_child_requirements')) ?></div>
              <a class="btn btn-sm btn-outline-primary" href="index.php?route=workflow-requirements/form&parentRequirementID=<?= $requirementId ?>&returnTo=<?= $currentRequirementReturnParam ?>">
                <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_requirement_add_child')) ?>
              </a>
            </div>

            <?php if ($childRequirements === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_no_child_requirements')) ?></p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th scope="col"><?= h(__t('workflow_requirement')) ?></th>
                      <th scope="col"><?= h(__t('status')) ?></th>
                      <th scope="col"><?= h(__t('workflow_requirement_priority')) ?></th>
                      <th scope="col" class="text-end"><?= h(__t('actions')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($childRequirements as $childRequirement): ?>
                      <?php
                        $childId = (int)($childRequirement['WorkflowRequirementID'] ?? 0);
                        $childStatus = strtoupper((string)($childRequirement['RequirementStatusCode'] ?? ''));
                        $childPriority = strtoupper((string)($childRequirement['PriorityCode'] ?? ''));
                      ?>
                      <?php if ($childId <= 0) { continue; } ?>
                      <tr>
                        <td>
                          <div class="fw-semibold">
                            <a href="index.php?route=workflow-requirements/form&id=<?= $childId ?>&returnTo=<?= $currentRequirementReturnParam ?>"><?= h((string)($childRequirement['RequirementTitle'] ?? '')) ?></a>
                          </div>
                          <div class="small text-muted"><?= h((string)($childRequirement['RequirementCode'] ?? __t('workflow_requirement_no_code'))) ?></div>
                        </td>
                        <td><span class="badge text-bg-info"><?= h(wf_requirement_status_label($childStatus, $statusOptions)) ?></span></td>
                        <td><span class="badge text-bg-light border"><?= h(wf_requirement_status_label($childPriority, $priorityOptions)) ?></span></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-outline-primary" href="index.php?route=workflow-requirements/form&id=<?= $childId ?>&returnTo=<?= $currentRequirementReturnParam ?>"><?= h(__t('open')) ?></a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="border-top mt-4 pt-4 d-none" data-requirement-tab-panel="RequirementReviewSourceTab">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
          <div>
            <div class="fw-semibold"><?= h(__t('workflow_requirement_approval_history')) ?></div>
            <div class="text-muted small"><?= h(__t('workflow_requirement_review_approval_help')) ?></div>
          </div>
          <?php if ($requirementId > 0): ?>
            <span class="badge text-bg-primary"><?= h(wf_requirement_status_label($currentRequirementStatus, $statusOptions)) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($requirementId <= 0): ?>
          <div class="alert alert-info py-2 mb-0">
            <?= h(__t('workflow_requirement_review_save_first')) ?>
          </div>
        <?php elseif (!$requirementHistoryInstalled): ?>
          <div class="alert alert-warning py-2 mb-0">
            <?= h(__t('workflow_requirement_history_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
          </div>
        <?php else: ?>
          <div class="row g-3 mb-3">
            <div class="col-12 col-md-4">
              <div class="small text-muted"><?= h(__t('workflow_requirement_current_status')) ?></div>
              <div class="fw-semibold"><?= h(wf_requirement_status_label($currentRequirementStatus, $statusOptions)) ?></div>
            </div>
            <div class="col-12 col-md-4">
              <div class="small text-muted"><?= h(__t('workflow_requirement_approved_by')) ?></div>
              <div class="fw-semibold"><?= h(trim((string)($record['ApprovedByName'] ?? '')) !== '' ? (string)$record['ApprovedByName'] : '-') ?></div>
            </div>
            <div class="col-12 col-md-4">
              <div class="small text-muted"><?= h(__t('workflow_requirement_approved_at')) ?></div>
              <div class="fw-semibold"><?= h(wf_requirement_format_datetime($record['ApprovedAt'] ?? '') ?: '-') ?></div>
            </div>
          </div>

          <?php if ($canReviewRequirement && $reviewStatusOptions !== []): ?>
            <form method="post"
                  action="index.php?route=workflow-requirements/transition"
                  class="needs-validation mb-4"
                  novalidate
                  data-confirm-message="<?= h(__t('workflow_requirement_transition_confirm')) ?>"
                  data-confirm-button="<?= h(__t('workflow_requirement_record_review_decision')) ?>"
                  data-confirm-button-class="btn-primary">
              <?= csrf_field() ?>
              <input type="hidden" name="WorkflowRequirementID" value="<?= $requirementId ?>">
              <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-3">
                  <label class="form-label" for="RequirementReviewStatus"><?= h(__t('workflow_requirement_review_status')) ?></label>
                  <select class="form-select" id="RequirementReviewStatus" name="ToStatusCode" required>
                    <?php foreach ($reviewStatusOptions as $code => $labelKey): ?>
                      <option value="<?= h((string)$code) ?>" <?= $currentRequirementStatus === $code ? 'selected' : '' ?>><?= h(__t((string)$labelKey)) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="invalid-feedback"><?= h(__t('workflow_requirement_transition_invalid')) ?></div>
                </div>
                <div class="col-12 col-lg">
                  <label class="form-label" for="RequirementReviewNotes"><?= h(__t('workflow_requirement_review_notes')) ?></label>
                  <textarea class="form-control" id="RequirementReviewNotes" name="ReviewNotes" rows="2" maxlength="2000" placeholder="<?= h(__t('workflow_requirement_review_notes_help')) ?>"></textarea>
                </div>
                <div class="col-12 col-lg-auto">
                  <button type="submit" class="btn btn-sm btn-primary w-100">
                    <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                    <i class="bi bi-check2-circle me-1"></i><?= h(__t('workflow_requirement_record_review_decision')) ?>
                  </button>
                </div>
              </div>
            </form>
          <?php endif; ?>

          <div class="d-flex justify-content-end align-items-center gap-2 flex-wrap mb-2">
            <span class="badge text-bg-light border"><?= count($requirementHistory) ?></span>
          </div>

          <?php if ($requirementHistory === []): ?>
            <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_history_empty')) ?></p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col"><?= h(__t('workflow_requirement_history_when')) ?></th>
                    <th scope="col"><?= h(__t('workflow_requirement_history_event')) ?></th>
                    <th scope="col"><?= h(__t('workflow_requirement_history_user')) ?></th>
                    <th scope="col"><?= h(__t('workflow_requirement_history_details')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($requirementHistory as $historyRow): ?>
                    <?php
                      $eventCode = strtoupper(trim((string)($historyRow['EventTypeCode'] ?? '')));
                      $fromStatus = strtoupper(trim((string)($historyRow['FromStatusCode'] ?? '')));
                      $toStatus = strtoupper(trim((string)($historyRow['ToStatusCode'] ?? '')));
                      $fieldName = trim((string)($historyRow['FieldName'] ?? ''));
                      $notes = trim((string)($historyRow['Notes'] ?? ''));
                      $details = [];

                      if ($eventCode === 'CREATED') {
                          $details[] = __t('workflow_requirement_history_created');
                      } elseif ($eventCode === 'REVIEW_NOTE') {
                          $details[] = __t('workflow_requirement_history_review_note_recorded');
                      } elseif ($fieldName === 'RequirementStatusCode' || ($fieldName === '' && ($fromStatus !== '' || $toStatus !== ''))) {
                          $details[] = __t('workflow_requirement_history_status_change', [
                              'from' => wf_requirement_status_label($fromStatus, $statusOptions),
                              'to' => wf_requirement_status_label($toStatus, $statusOptions),
                          ]);
                      } elseif ($fieldName !== '') {
                          $details[] = __t('workflow_requirement_history_field_change', [
                              'field' => wf_requirement_history_field_label($fieldName),
                              'from' => wf_requirement_history_value($historyRow['OldValue'] ?? ''),
                              'to' => wf_requirement_history_value($historyRow['NewValue'] ?? ''),
                          ]);
                      }

                      if ($notes !== '') {
                          $details[] = __t('workflow_requirement_history_note', ['note' => wf_requirement_history_value($notes)]);
                      }

                      if ($details === []) {
                          $details[] = wf_requirement_history_value($historyRow['NewValue'] ?? $historyRow['OldValue'] ?? '');
                      }
                    ?>
                    <tr>
                      <td class="text-nowrap"><?= h(wf_requirement_format_datetime($historyRow['ChangedAt'] ?? '') ?: '-') ?></td>
                      <td><span class="badge text-bg-light border"><?= h(wf_requirement_history_event_label($eventCode)) ?></span></td>
                      <td><?= h(trim((string)($historyRow['ChangedByName'] ?? '')) !== '' ? (string)$historyRow['ChangedByName'] : '-') ?></td>
                      <td>
                        <?php foreach ($details as $detail): ?>
                          <div><?= h($detail) ?></div>
                        <?php endforeach; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="border-top mt-4 pt-4 d-none" data-requirement-tab-panel="RequirementTraceabilityTab">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
          <div>
            <div class="fw-semibold"><?= h(__t('workflow_requirement_traceability')) ?></div>
            <div class="text-muted small"><?= h(__t('workflow_requirement_traceability_help')) ?></div>
          </div>
          <?php if ($workflowLinksInstalled): ?>
            <span class="badge text-bg-light border"><?= h(__t('workflow_requirement_traceability_task_count', ['count' => (int)($traceabilitySummary['taskLinks'] ?? 0)])) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($requirementId <= 0): ?>
          <div class="alert alert-info py-2 mb-0">
            <?= h(__t('workflow_requirement_traceability_save_first')) ?>
          </div>
        <?php elseif (!$workflowLinksInstalled): ?>
          <div class="alert alert-warning py-2 mb-0">
            <?= h(__t('workflow_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
          </div>
        <?php else: ?>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <div class="border rounded-2 px-3 py-2 requirement-traceability-stat">
              <div class="small text-muted"><?= h(__t('workflow_requirement_traceability_tasks')) ?></div>
              <div class="fs-5 fw-semibold"><?= (int)($traceabilitySummary['taskLinks'] ?? 0) ?></div>
            </div>
            <div class="border rounded-2 px-3 py-2 requirement-traceability-stat">
              <div class="small text-muted"><?= h(__t('workflow_requirement_traceability_open_tasks')) ?></div>
              <div class="fs-5 fw-semibold"><?= (int)($traceabilitySummary['openTasks'] ?? 0) ?></div>
            </div>
            <div class="border rounded-2 px-3 py-2 requirement-traceability-stat">
              <div class="small text-muted"><?= h(__t('workflow_requirement_traceability_testing')) ?></div>
              <div class="fs-5 fw-semibold"><?= (int)($traceabilitySummary['testing'] ?? 0) ?></div>
            </div>
            <div class="border rounded-2 px-3 py-2 requirement-traceability-stat">
              <div class="small text-muted"><?= h(__t('workflow_requirement_traceability_training')) ?></div>
              <div class="fs-5 fw-semibold"><?= (int)($traceabilitySummary['training'] ?? 0) ?></div>
            </div>
            <div class="border rounded-2 px-3 py-2 requirement-traceability-stat">
              <div class="small text-muted"><?= h(__t('workflow_requirement_attachments')) ?></div>
              <div class="fs-5 fw-semibold"><?= count($requirementAttachments) ?></div>
            </div>
          </div>

          <div class="mb-4">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_create_task_from_requirement')) ?></div>
            <?php if ($workflowProjectID <= 0): ?>
              <div class="alert alert-warning py-2 mb-0">
                <?= h(__t('workflow_requirement_task_requires_project')) ?>
              </div>
            <?php else: ?>
              <form method="post" action="index.php?route=workflow-requirements/create-task" class="needs-validation" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="WorkflowRequirementID" value="<?= $requirementId ?>">
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-lg-5">
                    <label class="form-label" for="RequirementTaskTitle"><?= h(__t('workflow_requirement_task_title')) ?></label>
                    <input type="text" class="form-control" id="RequirementTaskTitle" name="TaskTitle" value="<?= h($defaultTaskTitle) ?>" maxlength="255" required>
                    <div class="invalid-feedback"><?= h(__t('title_required')) ?></div>
                  </div>
                  <div class="col-12 col-lg-3">
                    <label class="form-label" for="RequirementTaskAssignee"><?= h(__t('workflow_requirement_task_assignee')) ?></label>
                    <select class="form-select" id="RequirementTaskAssignee" name="AssignedToUserID" required>
                      <option value=""><?= h(__t('none')) ?></option>
                      <?php foreach ($users as $user): ?>
                        <?php $userId = (int)($user['UserID'] ?? 0); ?>
                        <?php if ($userId <= 0) { continue; } ?>
                        <option value="<?= $userId ?>" <?= $defaultTaskAssigneeID === $userId ? 'selected' : '' ?>><?= h(wf_requirement_user_label($user)) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= h(__t('workflow_requirement_task_assignee_required')) ?></div>
                  </div>
                  <div class="col-12 col-lg-2">
                    <label class="form-label" for="RequirementTaskDueDate"><?= h(__t('workflow_requirement_task_due_date')) ?></label>
                    <input type="date" class="form-control" id="RequirementTaskDueDate" name="TaskDueDate" value="<?= h($defaultTaskDueDate) ?>" required>
                    <div class="invalid-feedback"><?= h(__t('workflow_requirement_task_due_required')) ?></div>
                  </div>
                  <div class="col-12 col-lg-2">
                    <button type="submit" class="btn btn-sm btn-outline-success w-100">
                      <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                      <i class="bi bi-plus-circle me-1"></i><?= h(__t('workflow_project_create_task')) ?>
                    </button>
                  </div>
                </div>
                <div class="form-text"><?= h(__t('workflow_requirement_create_task_help')) ?></div>
              </form>
            <?php endif; ?>
          </div>

          <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
              <div class="fw-semibold"><?= h(__t('workflow_requirement_traceability_tasks')) ?></div>
              <?php if ($taskLinks !== []): ?>
                <a href="index.php?route=workflow/list&workflowProjectID=<?= $workflowProjectID ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-list-task me-1"></i><?= h(__t('workflow_project_view_tasks')) ?>
                </a>
              <?php endif; ?>
            </div>
            <?php if ($taskLinks === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_traceability_no_tasks')) ?></p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th scope="col"><?= h(__t('workflow_task_task')) ?></th>
                      <th scope="col"><?= h(__t('status')) ?></th>
                      <th scope="col"><?= h(__t('assigned_to')) ?></th>
                      <th scope="col"><?= h(__t('due_date')) ?></th>
                      <th scope="col" class="text-end"><?= h(__t('actions')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($taskLinks as $link): ?>
                      <?php
                        $taskId = (int)($link['WorkflowTaskID'] ?? 0);
                        if ($taskId <= 0) {
                            continue;
                        }
                        $isClosed = wf_requirement_task_closed($link);
                        $statusLabel = trim((string)($link['WorkflowTaskStatusName'] ?? $link['WorkflowTaskStatusCode'] ?? ''));
                        $taskTitle = trim((string)($link['WorkflowTaskTitle'] ?? ''));
                      ?>
                      <tr>
                        <td>
                          <div class="fw-semibold requirement-traceability-title"><?= h($taskTitle !== '' ? $taskTitle : __t('workflow_task_task')) ?></div>
                          <div class="small text-muted">#<?= $taskId ?><?= trim((string)($link['WorkflowTaskPriorityCode'] ?? '')) !== '' ? ' / ' . h((string)$link['WorkflowTaskPriorityCode']) : '' ?></div>
                        </td>
                        <td>
                          <span class="badge <?= $isClosed ? 'text-bg-success' : 'text-bg-primary' ?>"><?= h($statusLabel !== '' ? $statusLabel : '-') ?></span>
                        </td>
                        <td><?= h((string)($link['WorkflowTaskAssignedToName'] ?? '-')) ?></td>
                        <td><?= h(wf_requirement_date_value($link['WorkflowTaskDueDate'] ?? '') ?: '-') ?></td>
                        <td class="text-end">
                          <a class="btn btn-sm btn-outline-primary" href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $workflowProjectID ?>">
                            <?= h(__t('open')) ?>
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <div>
            <div class="fw-semibold mb-2"><?= h(__t('workflow_requirement_traceability_related_work')) ?></div>
            <?php if ($requirementRelatedLinks === []): ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_traceability_no_related_work')) ?></p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th scope="col"><?= h(__t('workflow_link_type')) ?></th>
                      <th scope="col"><?= h(__t('workflow_link_title')) ?></th>
                      <th scope="col"><?= h(__t('workflow_task_task')) ?></th>
                      <th scope="col" class="text-end"><?= h(__t('actions')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($requirementRelatedLinks as $link): ?>
                      <?php
                        $taskId = (int)($link['WorkflowTaskID'] ?? 0);
                        $linkedUrl = trim((string)($link['LinkedUrl'] ?? ''));
                      ?>
                      <tr>
                        <td><?= h(wf_requirement_link_type_label((string)($link['LinkTypeCode'] ?? ''))) ?></td>
                        <td>
                          <div class="fw-semibold"><?= h((string)($link['LinkedTitle'] ?? $link['LinkedEntityKey'] ?? $link['LinkedEntity'] ?? '-')) ?></div>
                          <?php if (trim((string)($link['LinkedEntityKey'] ?? '')) !== ''): ?>
                            <div class="small text-muted"><?= h((string)$link['LinkedEntityKey']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?= h((string)($link['WorkflowTaskTitle'] ?? '-')) ?></td>
                        <td class="text-end">
                          <div class="d-inline-flex gap-1">
                            <?php if ($linkedUrl !== ''): ?>
                              <a class="btn btn-sm btn-outline-secondary" href="<?= h($linkedUrl) ?>"><?= h(__t('open')) ?></a>
                            <?php endif; ?>
                            <?php if ($taskId > 0): ?>
                              <a class="btn btn-sm btn-outline-primary" href="index.php?route=workflow/edit&id=<?= $taskId ?>&workflowProjectID=<?= $workflowProjectID ?>"><?= h(__t('workflow_task_task')) ?></a>
                            <?php endif; ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="border-top mt-4 pt-4">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
          <div>
            <div class="fw-semibold"><?= h(__t('workflow_requirement_attachments')) ?></div>
            <?php if ($requirementAttachmentsInstalled && $requirementId > 0): ?>
              <div class="text-muted small"><?= h(__t('workflow_task_attached_file_count', ['count' => count($requirementAttachments)])) ?></div>
            <?php endif; ?>
          </div>
          <?php if ($requirementAttachmentsInstalled && $requirementAttachments !== []): ?>
            <span class="badge text-bg-light border"><?= count($requirementAttachments) ?></span>
          <?php endif; ?>
        </div>

        <?php if ($requirementId <= 0): ?>
          <div class="alert alert-info py-2 mb-0">
            <?= h(__t('workflow_requirement_create_attachments_help')) ?>
          </div>
        <?php elseif (!$requirementAttachmentsInstalled): ?>
          <div class="alert alert-warning py-2 mb-0">
            <?= h(__t('workflow_requirement_attachments_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
          </div>
        <?php else: ?>
          <form method="post"
                action="index.php?route=workflow-requirements/upload-attachment"
                enctype="multipart/form-data"
                class="needs-validation mb-3"
                novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="WorkflowRequirementID" value="<?= $requirementId ?>">
            <div class="row g-2 align-items-end">
              <div class="col-12 col-lg">
                <label class="form-label" for="RequirementAttachment"><?= h(__t('workflow_requirement_attach_file')) ?></label>
                <input type="file"
                       class="form-control"
                       id="RequirementAttachment"
                       name="RequirementAttachment[]"
                       multiple
                       required
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.gif,.zip">
                <div class="invalid-feedback"><?= h(__t('workflow_requirement_choose_file')) ?></div>
              </div>
              <div class="col-12 col-lg-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                  <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                  <i class="bi bi-paperclip me-1"></i><?= h(__t('workflow_requirement_upload_attachment')) ?>
                </button>
              </div>
            </div>
            <div class="form-text"><?= h(__t('workflow_task_allowed_file_types_help')) ?></div>
          </form>

          <?php if ($requirementAttachments !== []): ?>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col"><?= h(__t('workflow_task_file')) ?></th>
                    <th scope="col"><?= h(__t('workflow_task_uploaded')) ?></th>
                    <th scope="col" class="text-end"><?= h(__t('actions')) ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($requirementAttachments as $attachment): ?>
                    <?php
                      $attachmentId = (int)($attachment['WorkflowRequirementAttachmentID'] ?? 0);
                      if ($attachmentId <= 0) {
                          continue;
                      }
                      $fileName = trim((string)($attachment['OriginalFileName'] ?? 'attachment'));
                      $uploadedBy = trim((string)($attachment['UploadedByName'] ?? ''));
                      $uploadedAt = wf_requirement_format_datetime($attachment['UploadedAt'] ?? '');
                    ?>
                    <tr>
                      <td>
                        <div class="d-flex align-items-start gap-2">
                          <i class="bi bi-file-earmark-text text-secondary mt-1"></i>
                          <div>
                            <div class="requirement-attachment-name"><?= h($fileName !== '' ? $fileName : 'attachment') ?></div>
                            <div class="requirement-attachment-meta"><?= h(wf_requirement_format_file_size($attachment['FileSizeBytes'] ?? 0)) ?></div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div class="small"><?= h($uploadedBy !== '' ? $uploadedBy : '-') ?></div>
                        <div class="requirement-attachment-meta"><?= h($uploadedAt !== '' ? $uploadedAt : '-') ?></div>
                      </td>
                      <td class="text-end">
                        <div class="d-inline-flex gap-1">
                          <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-requirements/download-attachment&id=<?= $attachmentId ?>">
                            <i class="bi bi-download me-1"></i><?= h(__t('workflow_task_download')) ?>
                          </a>
                          <form method="post"
                                action="index.php?route=workflow-requirements/delete-attachment"
                                class="needs-validation d-inline"
                                novalidate
                                data-confirm-message="<?= h(__t('workflow_requirement_remove_attachment_confirm')) ?>"
                                data-confirm-button="<?= h(__t('workflow_task_remove')) ?>"
                                data-confirm-button-class="btn-danger">
                            <?= csrf_field() ?>
                            <input type="hidden" name="WorkflowRequirementAttachmentID" value="<?= $attachmentId ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                              <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                              <i class="bi bi-trash me-1"></i><?= h(__t('workflow_task_remove')) ?>
                            </button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-0"><?= h(__t('workflow_requirement_no_attachments')) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  'use strict';

  const plainText = html => {
    const div = document.createElement('div');
    div.innerHTML = html || '';
    return (div.textContent || div.innerText || '').trim();
  };

  const safeUrl = url => {
    const value = (url || '').trim();
    if (!value) return '';
    if (/^(https?:\/\/|mailto:|\/|index\.php\?)/i.test(value)) return value;
    return 'https://' + value;
  };

  const levelSelect = document.getElementById('RequirementLevelCode');
  const parentSelect = document.getElementById('ParentRequirementID');
  if (levelSelect && parentSelect) {
    const syncParentSelect = () => {
      const isDetailed = levelSelect.value === 'DETAILED';
      parentSelect.disabled = !isDetailed;
      parentSelect.required = isDetailed;
      if (!isDetailed) parentSelect.value = '';
    };
    levelSelect.addEventListener('change', syncParentSelect);
    syncParentSelect();
  }

  document.querySelectorAll('[data-requirement-rich-text]').forEach(container => {
    const editor = container.querySelector('[data-requirement-rich-editor]');
    const source = container.querySelector('.requirement-rich-text-source');
    if (!editor || !source) return;

    const sync = () => {
      source.value = plainText(editor.innerHTML) ? editor.innerHTML.trim() : '';
    };

    editor.addEventListener('input', sync);
    editor.addEventListener('blur', sync);
    container.querySelectorAll('[data-requirement-rich-command]').forEach(button => {
      button.addEventListener('click', () => {
        editor.focus();
        const command = button.getAttribute('data-requirement-rich-command') || '';
        if (command === 'createLink') {
          const url = safeUrl(window.prompt(<?= json_encode(__t('workflow_task_description_link_prompt')) ?>) || '');
          if (url) document.execCommand(command, false, url);
        } else {
          document.execCommand(command, false, null);
        }
        sync();
      });
    });
    sync();
  });

  const showTabForControl = control => {
    const pane = control.closest('.tab-pane');
    if (!pane || !pane.id || pane.classList.contains('active')) return;

    const trigger = document.querySelector('[data-bs-target="#' + pane.id + '"]');
    if (!trigger) return;

    if (window.bootstrap && window.bootstrap.Tab) {
      window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    } else {
      trigger.click();
    }

    window.setTimeout(() => control.focus({ preventScroll: false }), 80);
  };

  const syncSupplementalTabPanels = () => {
    const activePane = document.querySelector('#RequirementEditTabContent > .tab-pane.active');
    const activePaneId = activePane ? activePane.id : 'RequirementContentTab';

    document.querySelectorAll('[data-requirement-tab-panel]').forEach(panel => {
      panel.classList.toggle('d-none', panel.getAttribute('data-requirement-tab-panel') !== activePaneId);
    });
  };

  document.querySelectorAll('#RequirementEditTabs [data-bs-toggle="tab"]').forEach(trigger => {
    trigger.addEventListener('shown.bs.tab', syncSupplementalTabPanels);
    trigger.addEventListener('click', () => window.setTimeout(syncSupplementalTabPanels, 0));
  });
  syncSupplementalTabPanels();

  document.querySelectorAll('.needs-validation').forEach(form => {
    form.addEventListener('submit', event => {
      form.querySelectorAll('[data-requirement-rich-text]').forEach(container => {
        const editor = container.querySelector('[data-requirement-rich-editor]');
        const source = container.querySelector('.requirement-rich-text-source');
        if (editor && source) {
          source.value = plainText(editor.innerHTML) ? editor.innerHTML.trim() : '';
        }
      });
      if (!form.checkValidity()) {
        const invalidControl = form.querySelector(':invalid');
        if (invalidControl) {
          showTabForControl(invalidControl);
        }
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    });
  });
})();
</script>
