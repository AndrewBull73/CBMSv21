<?php
declare(strict_types=1);

/** Expected: $task, $types, $statuses, $users, $title (optional), $flash (optional) */

require_once __DIR__ . '/../../../shared/workflow_helpers.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
/** Normalize any date string to YYYY-MM-DD for <input type="date"> */
if (!function_exists('toIsoDate')) {
    function toIsoDate(?string $v): string {
        if (!$v) return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
        $ts = strtotime($v);
        return $ts ? date('Y-m-d', $ts) : '';
    }
}
if (!function_exists('wf_form_task_option_label')) {
    function wf_form_task_option_label(array $row): string {
        $id = (int)($row['WorkflowTaskID'] ?? 0);
        $title = trim((string)($row['Title'] ?? ''));
        $label = __t('workflow_task_number', ['id' => $id]);
        if ($title !== '') {
            $label .= ' - ' . $title;
        }
        $assignee = trim((string)($row['AssignedToName'] ?? ''));
        $due = toIsoDate(is_string($row['DueDate'] ?? null) ? (string)$row['DueDate'] : null);
        $details = [];
        if ($assignee !== '') {
            $details[] = $assignee;
        }
        if ($due !== '') {
            $details[] = __t('workflow_task_due') . ' ' . $due;
        }
        if ($details !== []) {
            $label .= ' (' . implode(', ', $details) . ')';
        }
        return $label;
    }
}
if (!function_exists('wf_form_requirement_option_label')) {
    function wf_form_requirement_option_label(array $row): string {
        $code = trim((string)($row['RequirementCode'] ?? ''));
        $title = trim((string)($row['RequirementTitle'] ?? ''));
        $module = trim((string)($row['ModuleCode'] ?? ''));
        $status = trim((string)($row['RequirementStatusCode'] ?? ''));
        $label = $code !== ''
            ? $code
            : __t('workflow_requirement_number', ['id' => (int)($row['WorkflowRequirementID'] ?? 0)]);
        if ($title !== '') {
            $label .= ' - ' . $title;
        }
        $details = [];
        if ($module !== '') {
            $details[] = $module;
        }
        if ($status !== '') {
            $details[] = $status;
        }
        if ($details !== []) {
            $label .= ' (' . implode(', ', $details) . ')';
        }
        return $label;
    }
}

// ---- Safe fallbacks for possibly-missing vars ----
$task     = $task     ?? null;
$types    = is_array($types ?? null)    ? $types    : [];
$statuses = is_array($statuses ?? null) ? $statuses : [];
$users    = is_array($users ?? null)    ? $users    : [];
$workflowProjects = is_array($workflowProjects ?? null) ? $workflowProjects : [];
$workflowProjectsInstalled = !empty($workflowProjectsInstalled);
$projectTaskOptions = is_array($projectTaskOptions ?? null) ? $projectTaskOptions : [];
$workflowRequirementOptions = is_array($workflowRequirementOptions ?? null) ? $workflowRequirementOptions : [];
$workflowRequirementsInstalled = !empty($workflowRequirementsInstalled);
$selectedWorkflowRequirementID = (int)($selectedWorkflowRequirementID ?? 0);
$taskDependencies = is_array($taskDependencies ?? null) ? $taskDependencies : [];
$taskLinks = is_array($taskLinks ?? null) ? $taskLinks : [];
$workflowLinkTypeOptions = is_array($workflowLinkTypeOptions ?? null) ? $workflowLinkTypeOptions : [];
$workflowLinksInstalled = !empty($workflowLinksInstalled);
$workflowTaskDependenciesInstalled = !empty($workflowTaskDependenciesInstalled);
$workflowUserGroups = is_array($workflowUserGroups ?? null) ? $workflowUserGroups : [];
$workflowUserGroupsInstalled = !empty($workflowUserGroupsInstalled);
$taskActivity = is_array($taskActivity ?? null) ? $taskActivity : [];
$taskAttachments = is_array($taskAttachments ?? null) ? $taskAttachments : [];
$taskComments = is_array($taskComments ?? null) ? $taskComments : [];
$taskViews = is_array($taskViews ?? null) ? $taskViews : [];
$workflowTaskAttachmentsInstalled = !empty($workflowTaskAttachmentsInstalled);
$workflowTaskCommentsInstalled = !empty($workflowTaskCommentsInstalled);
$workflowTaskViewsInstalled = !empty($workflowTaskViewsInstalled);
$title    = $title    ?? __t(($task && !empty($task['WorkflowTaskID'])) ? 'edit_task' : 'create_task');
$defaultStatusID = (int)($defaultStatusID ?? 0);
$canManageTaskDetails = !empty($canManageTaskDetails);
$canRespondTask = !empty($canRespondTask);
$canForwardTask = !empty($canForwardTask);
$canTransitionTask = !empty($canTransitionTask);
$canSendTaskReminder = !empty($canSendTaskReminder);
$canUploadTaskAttachment = !empty($canUploadTaskAttachment);
$canAddTaskComment = !empty($canAddTaskComment);
$canAdminWorkflow = !empty($canAdminWorkflow);
$currentUserId = (int)($currentUserId ?? 0);
$projectTaskTypeID = (int)($projectTaskTypeID ?? 0);
$detailsDisabled = $canManageTaskDetails ? '' : 'disabled';

// Preserve navigation/query context if provided
$q        = (string)($_GET['q']        ?? '');
$typeID   = ($_GET['typeID']  ?? '') !== '' ? (int)$_GET['typeID']  : null;
$statusID = ($_GET['statusID']?? '') !== '' ? (int)$_GET['statusID'] : null;
$status   = (string)($_GET['status']   ?? '');
$mine     = ($_GET['mine'] ?? '') !== '' ? (int)$_GET['mine'] : null;
$taskScope = strtolower(trim((string)($_GET['task_scope'] ?? 'received')));
if (!in_array($taskScope, ['received', 'created'], true)) {
    $taskScope = 'received';
}
$assignedToUserIDFilter = ($_GET['assignedToUserID'] ?? '') !== '' ? (int)$_GET['assignedToUserID'] : null;
$workflowProjectIDFilter = ($_GET['workflowProjectID'] ?? '') !== '' ? (int)$_GET['workflowProjectID'] : null;
$page     = (int)($_GET['page']        ?? 1);
$pageSize = (int)($_GET['pageSize']    ?? 10);
$isIframe = !empty($_GET['iframe']);

if (!function_exists('wf_form_context_params')) {
    function wf_form_context_params(): array {
        $params = [];
        $dueState = strtolower(trim((string) ($_GET['due_state'] ?? '')));
        if (in_array($dueState, ['overdue', 'today', 'soon'], true)) {
            $params['due_state'] = $dueState;
        }
        $projectID = ($_GET['workflowProjectID'] ?? $_GET['WorkflowProjectID'] ?? '');
        if ($projectID !== '' && is_numeric($projectID)) {
            $params['workflowProjectID'] = (string) ((int) $projectID);
        }
        $requirementID = ($_GET['workflowRequirementID'] ?? $_GET['WorkflowRequirementID'] ?? '');
        if ($requirementID !== '' && is_numeric($requirementID)) {
            $params['workflowRequirementID'] = (string) ((int) $requirementID);
        }
        if ((string) ($_GET['link_context'] ?? '') === '1') {
            $params['link_context'] = '1';
        }
        if (isset($_GET['fy']) && is_numeric($_GET['fy'])) {
            $params['fy'] = (string) ((int) $_GET['fy']);
        }
        if (isset($_GET['ver']) && is_numeric($_GET['ver'])) {
            $params['ver'] = (string) ((int) $_GET['ver']);
        }
        if (array_key_exists('scope_dataobject_code', $_GET)) {
            $params['scope_dataobject_code'] = (string) $_GET['scope_dataobject_code'];
            $scopeName = trim((string) ($_GET['scope_dataobject_name'] ?? ''));
            if ($params['scope_dataobject_code'] !== '' && $scopeName !== '') {
                $params['scope_dataobject_name'] = $scopeName;
            }
        }
        return $params;
    }
}

if (!function_exists('wf_form_build_query')) {
    function wf_form_build_query(array $params): string {
        $merged = $params + wf_form_context_params();
        if (($merged['scope_dataobject_code'] ?? null) === '') {
            unset($merged['scope_dataobject_name']);
        }
        $filtered = array_filter($merged, static function ($value, $key): bool {
            return $value !== null && ($value !== '' || $key === 'scope_dataobject_code');
        }, ARRAY_FILTER_USE_BOTH);
        return 'index.php?' . http_build_query($filtered);
    }
}

if (!function_exists('wf_form_render_context_inputs')) {
    function wf_form_render_context_inputs(): string {
        $html = '';
        foreach (wf_form_context_params() as $key => $value) {
            $html .= '<input type="hidden" name="' . h((string) $key) . '" value="' . h((string) $value) . '">' . PHP_EOL;
        }
        return $html;
    }
}

// Current values for form fields
$id               = (int)($task['WorkflowTaskID'] ?? 0);
$valTitle         = (string)($task['Title'] ?? '');
$valDescription   = (string)($task['Description'] ?? '');
$valTaskTypeID    = (int)($task['TaskTypeID'] ?? 0);
$valStatusID      = (int)($task['StatusID'] ?? ($id <= 0 ? $defaultStatusID : 0));
$valAssignedToID  = isset($task['AssignedToUserID']) ? (int)$task['AssignedToUserID'] : 0;
$valCreatedByID   = isset($task['CreatedByUserID']) ? (int)$task['CreatedByUserID'] : 0;
$valRelatedEntity = (string)($task['RelatedEntity'] ?? '');
$valRelatedKey    = (string)($task['RelatedKey'] ?? '');
if ($selectedWorkflowRequirementID <= 0) {
    foreach ($taskLinks as $link) {
        if (strtoupper(trim((string)($link['LinkedEntity'] ?? ''))) === 'WORKFLOWREQUIREMENT'
            && strtoupper(trim((string)($link['LinkTypeCode'] ?? ''))) === 'REQUIREMENT'
            && (int)($link['LinkedEntityID'] ?? 0) > 0
        ) {
            $selectedWorkflowRequirementID = (int)$link['LinkedEntityID'];
            break;
        }
    }
}
if ($selectedWorkflowRequirementID <= 0
    && strtoupper(trim($valRelatedEntity)) === 'WORKFLOWREQUIREMENT'
    && trim($valRelatedKey) !== ''
) {
    foreach ($workflowRequirementOptions as $requirementOption) {
        $optionID = (int)($requirementOption['WorkflowRequirementID'] ?? 0);
        $optionCode = trim((string)($requirementOption['RequirementCode'] ?? ''));
        if ($optionID > 0 && (trim($valRelatedKey) === (string)$optionID || strcasecmp(trim($valRelatedKey), $optionCode) === 0)) {
            $selectedWorkflowRequirementID = $optionID;
            break;
        }
    }
}
$valWorkflowProjectID = isset($task['WorkflowProjectID']) ? (int)$task['WorkflowProjectID'] : ($workflowProjectIDFilter ?? 0);
$valParentWorkflowTaskID = isset($task['ParentWorkflowTaskID']) ? (int)$task['ParentWorkflowTaskID'] : 0;
$valDependsOnWorkflowTaskIDs = [];
foreach ($taskDependencies as $dependencyRow) {
    $dependencyTaskID = (int)($dependencyRow['DependsOnWorkflowTaskID'] ?? 0);
    if ($dependencyTaskID > 0) {
        $valDependsOnWorkflowTaskIDs[$dependencyTaskID] = $dependencyTaskID;
    }
}
$valPercentComplete = max(0, min(100, (float)($task['PercentComplete'] ?? 0)));
$valProjectUtilisationPercent = max(0, min(100, (float)($task['ProjectUtilisationPercent'] ?? 0)));
$selectedWorkflowProject = is_array($selectedWorkflowProject ?? null) ? $selectedWorkflowProject : [];
$isProjectTaskContext = $valWorkflowProjectID > 0;
if ($isProjectTaskContext && $projectTaskTypeID > 0) {
    $valTaskTypeID = $projectTaskTypeID;
}
$projectTaskTypeLabel = $projectTaskTypeID > 0
    ? (string)($types[$projectTaskTypeID] ?? __t('workflow_project_task_type_name'))
    : __t('workflow_project_task_type_name');
$lockedWorkflowProject = $selectedWorkflowProject;
if ($isProjectTaskContext && $lockedWorkflowProject === []) {
    foreach ($workflowProjects as $project) {
        if ((int)($project['WorkflowProjectID'] ?? 0) === $valWorkflowProjectID) {
            $lockedWorkflowProject = $project;
            break;
        }
    }
}
$lockedProjectLabel = '';
$lockedProjectStartDate = '';
$lockedProjectEndDate = '';
if ($isProjectTaskContext) {
    $lockedProjectLabel = trim((string)($lockedWorkflowProject['ProjectName'] ?? $task['ProjectName'] ?? ''));
    if ($lockedProjectLabel === '') {
        $lockedProjectLabel = __t('workflow_project_number', ['id' => $valWorkflowProjectID]);
    }
    $lockedProjectCode = trim((string)($lockedWorkflowProject['ProjectCode'] ?? $task['ProjectCode'] ?? ''));
    if ($lockedProjectCode !== '') {
        $lockedProjectLabel = $lockedProjectCode . ' - ' . $lockedProjectLabel;
    }
    $lockedProjectStartRaw = $lockedWorkflowProject['StartDate'] ?? $task['ProjectStartDate'] ?? null;
    $lockedProjectEndRaw = $lockedWorkflowProject['TargetEndDate'] ?? $task['ProjectTargetEndDate'] ?? null;
    $lockedProjectStartDate = toIsoDate(is_string($lockedProjectStartRaw) ? $lockedProjectStartRaw : null);
    $lockedProjectEndDate = toIsoDate(is_string($lockedProjectEndRaw) ? $lockedProjectEndRaw : null);
}
$valPriorityCode  = strtoupper(trim((string)($task['PriorityCode'] ?? 'NORMAL')));
if (!in_array($valPriorityCode, ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true)) {
    $valPriorityCode = 'NORMAL';
}
$valNotifyCreatorOnCompletion = ((int)($task['NotifyCreatorOnCompletion'] ?? ($id <= 0 ? 1 : 0))) === 1;
$valNotifyCreatorOnUpdate = ((int)($task['NotifyCreatorOnUpdate'] ?? 0)) === 1;
$valNotifyAudienceOnComment = ((int)($task['NotifyAudienceOnComment'] ?? ($id <= 0 ? 1 : 0))) === 1;
$valAutoReminderEnabled = ((int)($task['AutoReminderEnabled'] ?? 0)) === 1;
$valAutoReminderDaysBeforeDue = max(0, min(365, (int)($task['AutoReminderDaysBeforeDue'] ?? 1)));
$valOverdueEscalationEnabled = ((int)($task['OverdueEscalationEnabled'] ?? 0)) === 1;
$valOverdueEscalationDaysAfterDue = max(1, min(365, (int)($task['OverdueEscalationDaysAfterDue'] ?? 1)));
$valWorkflowTaskCompletionRule = strtoupper(trim((string)($task['WorkflowTaskCompletionRule'] ?? 'INDIVIDUAL')));
if (!in_array($valWorkflowTaskCompletionRule, ['INDIVIDUAL', 'ANY_COMPLETES_ALL'], true)) {
    $valWorkflowTaskCompletionRule = 'INDIVIDUAL';
}
$valRecipientResponse = (string)($task['RecipientResponse'] ?? '');
$taskStatusCode = strtoupper(trim((string)($task['StatusCode'] ?? '')));
$taskStatusName = strtoupper(trim((string)($task['StatusName'] ?? '')));
$isTaskInProgress = in_array($taskStatusCode, ['INPROGRESS', 'IN_PROGRESS'], true)
    || in_array($taskStatusName, ['IN PROGRESS', 'IN-PROGRESS'], true);
$isTaskClosed = $id > 0 && (
    !empty($task['CompletedAt'])
    || in_array($taskStatusCode, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
    || in_array($taskStatusName, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
);

// Normalize due date for date input
$rawDue     = $task['DueDate'] ?? $task['DateDue'] ?? $task['Due'] ?? $task['Due_On'] ?? null;
$valDueDate = toIsoDate(is_string($rawDue) ? $rawDue : null);
$valPlannedStartDate = toIsoDate(is_string($task['PlannedStartDate'] ?? null) ? (string)$task['PlannedStartDate'] : null);
$valPlannedEndDate = toIsoDate(is_string($task['PlannedEndDate'] ?? null) ? (string)$task['PlannedEndDate'] : null);
$isTaskOverdue = !$isTaskClosed
    && $valDueDate !== ''
    && strtotime(date('Y-m-d')) > strtotime($valDueDate);
$isTaskRecipient = $id > 0 && $currentUserId > 0 && $valAssignedToID === $currentUserId;
$isTaskCreator = $id > 0 && $currentUserId > 0 && $valCreatedByID === $currentUserId;
$isRecipientSimpleView = $id > 0 && $isTaskRecipient && !$isTaskCreator && !$canManageTaskDetails && !$canAdminWorkflow;
if ($isRecipientSimpleView) {
    $title = __t('workflow_task_view_title');
}
$lastNotificationSentAt = '';
$lastNotificationLabel = '';
foreach ([
    __t('workflow_task_notification_auto_reminder') => (string)($task['AutoReminderSentAt'] ?? ''),
    __t('workflow_task_notification_manual_reminder') => (string)($task['LastManualReminderSentAt'] ?? ''),
    __t('workflow_task_notification_overdue_escalation') => (string)($task['OverdueEscalationSentAt'] ?? ''),
] as $label => $sentAt) {
    $sentAt = trim($sentAt);
    if ($sentAt === '') {
        continue;
    }
    $currentTs = strtotime($sentAt);
    $lastTs = $lastNotificationSentAt !== '' ? strtotime($lastNotificationSentAt) : false;
    if ($lastNotificationSentAt === '' || ($currentTs !== false && ($lastTs === false || $currentTs > $lastTs))) {
        $lastNotificationSentAt = $sentAt;
        $lastNotificationLabel = $label;
    }
}

// Optional one-off flash (controller may pass it)
$flash = $flash ?? null;

if (!function_exists('wf_form_format_datetime')) {
    function wf_form_format_datetime(?string $dt): string {
        $raw = trim((string) $dt);
        if ($raw === '') {
            return '';
        }
        $normalized = preg_replace('/(\.\d{6})\d+$/', '$1', $raw) ?? $raw;
        try {
            $utc = new DateTimeImmutable($normalized, new DateTimeZone('UTC'));
            return $utc->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i');
        } catch (Throwable $e) {
            $ts = strtotime($raw . ' UTC');
            return $ts ? date('Y-m-d H:i', $ts) : $raw;
        }
    }
}

if (!function_exists('wf_form_user_label')) {
    function wf_form_user_label(array $row, string $nameKey, string $idKey): string {
        $name = trim((string)($row[$nameKey] ?? ''));
        if ($name !== '') {
            return $name;
        }
        $id = (int)($row[$idKey] ?? 0);
        return $id > 0 ? __t('user_number', ['id' => $id]) : '-';
    }
}

if (!function_exists('wf_form_format_file_size')) {
    function wf_form_format_file_size($bytes): string {
        $size = max(0.0, (float)$bytes);
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        return number_format($size, $unitIndex === 0 ? 0 : 1) . ' ' . $units[$unitIndex];
    }
}

if (!function_exists('wf_form_activity_label')) {
    function wf_form_activity_label(string $type): string {
        $normalizedType = strtolower(trim($type));
        $key = 'workflow_task_activity_' . $normalizedType;
        $translated = __t($key);
        return $translated === $key ? ucwords(strtolower(str_replace('_', ' ', $type))) : $translated;
    }
}

if (!function_exists('wf_form_link_type_label')) {
    function wf_form_link_type_label(string $type, array $options): string {
        $code = strtoupper(trim($type));
        $key = (string)($options[$code] ?? '');
        if ($key !== '') {
            $translated = __t($key);
            return $translated !== $key ? $translated : ucwords(strtolower(str_replace('_', ' ', $code)));
        }
        return $code !== '' ? ucwords(strtolower(str_replace('_', ' ', $code))) : __t('workflow_link_type_related_item');
    }
}

if (!function_exists('wf_form_notification_history')) {
    function wf_form_notification_history(?array $task, array $activityRows): array {
        $items = [];
        $activityTypesSeen = [];
        $notificationLabels = [
            'REMINDER_SENT' => 'workflow_task_notification_manual_reminder',
            'AUTOMATIC_REMINDER_SENT' => 'workflow_task_notification_auto_reminder',
            'OVERDUE_ESCALATION_SENT' => 'workflow_task_notification_overdue_escalation',
            'ASSIGNEE_NOTIFICATION_SENT' => 'workflow_task_notification_assignee',
            'COMMENT_NOTIFICATION_SENT' => 'workflow_task_notification_comment',
            'CREATOR_COMPLETION_NOTIFICATION_SENT' => 'workflow_task_notification_completion',
            'CREATOR_UPDATE_NOTIFICATION_SENT' => 'workflow_task_notification_update',
        ];

        $addItem = static function (
            string $type,
            string $label,
            ?string $sentAt,
            ?string $recipient,
            ?string $actor,
            ?string $note,
            string $source
        ) use (&$items): void {
            $sentAt = trim((string)$sentAt);
            if ($sentAt === '') {
                return;
            }
            $items[] = [
                'type' => $type,
                'label' => $label,
                'sentAt' => $sentAt,
                'recipient' => trim((string)$recipient),
                'actor' => trim((string)$actor),
                'note' => trim((string)$note),
                'source' => $source,
                'sort' => strtotime($sentAt . ' UTC') ?: 0,
            ];
        };

        foreach ($activityRows as $activity) {
            $type = strtoupper(trim((string)($activity['ActivityType'] ?? '')));
            if (!isset($notificationLabels[$type])) {
                continue;
            }
            $activityTypesSeen[$type] = true;
            $addItem(
                $type,
                __t($notificationLabels[$type]),
                (string)($activity['ActionAt'] ?? ''),
                (string)($activity['ToUserName'] ?? ''),
                (string)($activity['ActionByName'] ?? ''),
                (string)($activity['ActivityNote'] ?? ''),
                'activity'
            );
        }

        if ($task) {
            if (empty($activityTypesSeen['REMINDER_SENT'])) {
                $manualBy = (string)($task['LastManualReminderByName'] ?? '');
                $addItem(
                    'REMINDER_SENT',
                    __t('workflow_task_notification_manual_reminder'),
                    (string)($task['LastManualReminderSentAt'] ?? ''),
                    (string)($task['AssignedToName'] ?? ''),
                    $manualBy !== '' ? $manualBy : null,
                    __t('workflow_task_latest_manual_reminder_note'),
                    'task'
                );
            }
            if (empty($activityTypesSeen['AUTOMATIC_REMINDER_SENT'])) {
                $addItem(
                    'AUTOMATIC_REMINDER_SENT',
                    __t('workflow_task_notification_auto_reminder'),
                    (string)($task['AutoReminderSentAt'] ?? ''),
                    (string)($task['AssignedToName'] ?? ''),
                    __t('workflow_task_scheduler'),
                    __t('workflow_task_auto_reminder_recorded_note'),
                    'task'
                );
            }
            if (empty($activityTypesSeen['OVERDUE_ESCALATION_SENT'])) {
                $addItem(
                    'OVERDUE_ESCALATION_SENT',
                    __t('workflow_task_notification_overdue_escalation'),
                    (string)($task['OverdueEscalationSentAt'] ?? ''),
                    __t('workflow_task_escalation_recipients'),
                    __t('workflow_task_scheduler'),
                    __t('workflow_task_overdue_escalation_recorded_note'),
                    'task'
                );
            }
        }

        usort($items, static function (array $a, array $b): int {
            return ($b['sort'] <=> $a['sort']) ?: strcmp((string)$b['label'], (string)$a['label']);
        });

        return $items;
    }
}

if (!function_exists('wf_form_completion_rule_label')) {
    function wf_form_completion_rule_label(string $rule): string {
        return strtoupper(trim($rule)) === 'ANY_COMPLETES_ALL'
            ? __t('workflow_task_completion_rule_any')
            : __t('workflow_task_completion_rule_individual');
    }
}

$workflowTimezone = date_default_timezone_get();
$notificationHistory = wf_form_notification_history($task, $taskActivity);
?>

<?php if ($isIframe): ?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
  <meta charset="utf-8">
  <title><?= h($title) ?></title>
  <!-- Local Bootstrap for iframe/standalone rendering -->
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/icons/bootstrap-icons.css" rel="stylesheet">
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light p-3">
<div class="container-fluid">
<?php endif; ?>

<style>
  .workflow-form-shell {
    font-size: .95rem;
  }
  .workflow-form-shell > .card.shadow-sm {
    background: #fff;
    border: 1px solid #e4ebf2;
    border-radius: 1rem;
    overflow: hidden;
    box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05) !important;
  }
  .workflow-form-shell .card-header {
    background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
    border-bottom: 1px solid #e4ebf2;
    padding: 1.05rem 1.15rem;
  }
  .workflow-form-shell .card-body {
    padding: 1.05rem 1.15rem;
  }
  .workflow-form-shell .form-control,
  .workflow-form-shell .form-select {
    font-size: .875rem;
  }
  .workflow-form-shell .form-label {
    font-size: .84rem;
    font-weight: 600;
    color: #415161;
    margin-bottom: .4rem;
  }
  .workflow-form-shell textarea.form-control {
    min-height: 8rem;
  }
  .workflow-form-shell .workflow-rich-text-toolbar {
    display: none;
    gap: .35rem;
    flex-wrap: wrap;
    align-items: center;
    border: 1px solid #ced4da;
    border-bottom: 0;
    border-radius: .65rem .65rem 0 0;
    background: #f8fbff;
    padding: .35rem;
  }
  .workflow-form-shell .workflow-rich-text.is-enhanced .workflow-rich-text-toolbar {
    display: flex;
  }
  .workflow-form-shell .workflow-rich-text-source {
    margin-top: 0;
  }
  .workflow-form-shell .workflow-rich-text.is-enhanced .workflow-rich-text-source {
    display: none;
  }
  .workflow-form-shell .workflow-rich-text-editor {
    display: none;
    min-height: 9.5rem;
    overflow: auto;
    border-radius: 0 0 .65rem .65rem;
    line-height: 1.5;
  }
  .workflow-form-shell .workflow-rich-text.is-enhanced .workflow-rich-text-editor {
    display: block;
  }
  .workflow-form-shell .workflow-rich-text-editor:empty::before {
    content: attr(data-placeholder);
    color: #8795a1;
  }
  .workflow-form-shell .workflow-rich-text-editor:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25);
    outline: 0;
  }
  .workflow-form-shell .workflow-rich-text-editor.is-invalid,
  .workflow-form-shell .workflow-rich-text-source.is-invalid {
    border-color: #dc3545;
  }
  .workflow-form-shell .workflow-rich-text.is-invalid .invalid-feedback {
    display: block;
  }
  .workflow-form-shell .workflow-rich-text-content p,
  .workflow-form-shell .workflow-rich-text-editor p,
  .workflow-form-shell .workflow-recipient-description p {
    margin: 0 0 .65rem;
  }
  .workflow-form-shell .workflow-rich-text-content ul,
  .workflow-form-shell .workflow-rich-text-content ol,
  .workflow-form-shell .workflow-rich-text-editor ul,
  .workflow-form-shell .workflow-rich-text-editor ol,
  .workflow-form-shell .workflow-recipient-description ul,
  .workflow-form-shell .workflow-recipient-description ol {
    margin: 0 0 .65rem 1.25rem;
    padding-left: 1rem;
  }
  .workflow-form-shell .workflow-rich-text-content blockquote,
  .workflow-form-shell .workflow-rich-text-editor blockquote,
  .workflow-form-shell .workflow-recipient-description blockquote {
    border-left: .25rem solid #d8e2ec;
    color: #536679;
    margin: 0 0 .65rem;
    padding-left: .8rem;
  }
  .workflow-form-shell .btn {
    border-radius: .7rem;
  }
  .workflow-form-shell .btn.btn-sm,
  .workflow-form-shell .btn-group-sm .btn {
    border-radius: .65rem;
  }
  .workflow-form-shell .alert {
    border-radius: .9rem;
  }
  .workflow-form-shell .workflow-form-meta {
    background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    border: 1px solid #e4ebf2;
    border-radius: .9rem;
    padding: .95rem 1rem;
  }
  .workflow-form-shell .workflow-meta-label {
    color: #6c7a89;
    font-size: .74rem;
    font-weight: 700;
    letter-spacing: 0;
    text-transform: uppercase;
  }
  .workflow-form-shell .workflow-meta-value {
    color: #263746;
    font-size: .9rem;
    font-weight: 600;
    line-height: 1.25;
  }
  .workflow-form-shell .workflow-form-footer {
    border-top: 1px solid #e4ebf2;
    padding-top: 1rem;
  }
  .workflow-form-shell .workflow-action-panel {
    border: 1px solid #e4ebf2;
    border-radius: .9rem;
    background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    padding: 1rem;
  }
  .workflow-form-shell .workflow-recipient-summary {
    border: 1px solid #dce6ef;
    border-radius: .9rem;
    background: #fff;
    padding: 1.1rem;
  }
  .workflow-form-shell .workflow-recipient-title {
    color: #1f2f3f;
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.25;
    margin: 0;
  }
  .workflow-form-shell .workflow-recipient-description {
    color: #2f4050;
    font-size: .96rem;
    line-height: 1.55;
  }
  .workflow-form-shell .workflow-recipient-facts {
    display: grid;
    gap: .75rem;
    grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
  }
  .workflow-form-shell .workflow-recipient-fact {
    border: 1px solid #edf2f7;
    border-radius: .75rem;
    background: #f8fbff;
    padding: .75rem .85rem;
  }
  .workflow-form-shell .workflow-secondary-tabs {
    border-bottom: 1px solid #dce6ef;
  }
  .workflow-form-shell .workflow-secondary-tabs .nav-link {
    border-radius: .65rem .65rem 0 0;
    color: #536679;
    font-size: .86rem;
    padding: .55rem .75rem;
  }
  .workflow-form-shell .workflow-secondary-tabs .nav-link.active {
    color: #24384d;
    font-weight: 700;
  }
  .workflow-form-shell .workflow-secondary-tab-content {
    background: #fff;
    border: 1px solid #dce6ef;
    border-top: 0;
    border-radius: 0 0 .9rem .9rem;
    padding: 1rem;
  }
  .workflow-form-shell .workflow-activity-item {
    border-left: 3px solid #d8e4f0;
    padding: .2rem 0 .8rem .85rem;
    margin-left: .2rem;
  }
  .workflow-form-shell .workflow-activity-item:last-child {
    padding-bottom: .2rem;
  }
  .workflow-form-shell .workflow-activity-label {
    font-size: .78rem;
    font-weight: 700;
    color: #40566e;
    text-transform: uppercase;
    letter-spacing: 0;
  }
  .workflow-form-shell .workflow-notification-type {
    font-weight: 700;
    color: #263746;
  }
  .workflow-form-shell .workflow-notification-meta {
    color: #6c7a89;
    font-size: .78rem;
  }
  .workflow-form-shell .workflow-attachment-name {
    font-weight: 600;
    color: #263746;
    word-break: break-word;
  }
  .workflow-form-shell .workflow-attachment-meta {
    color: #6c7a89;
    font-size: .78rem;
  }
  .workflow-form-shell .workflow-comment-item {
    border: 1px solid #e4ebf2;
    border-radius: .75rem;
    background: #fff;
    padding: .8rem .9rem;
  }
  .workflow-form-shell .workflow-comment-meta {
    color: #6c7a89;
    font-size: .78rem;
  }
  .workflow-form-shell .workflow-link-item {
    border: 1px solid #e4ebf2;
    border-radius: .75rem;
    background: #fff;
    padding: .8rem .9rem;
  }
  .workflow-form-shell .workflow-link-title {
    color: #263746;
    font-weight: 700;
    word-break: break-word;
  }
  .workflow-form-shell .workflow-link-meta {
    color: #6c7a89;
    font-size: .78rem;
  }
  @media print {
    body {
      background: #fff !important;
    }
    .workflow-form-shell {
      font-size: 11pt;
    }
    .workflow-form-shell > .card.shadow-sm {
      border: 0 !important;
      box-shadow: none !important;
    }
    .workflow-form-shell .card-header {
      background: #fff !important;
      border-bottom: 1px solid #94a3b8;
      padding: .5rem 0;
    }
    .workflow-form-shell .card-body {
      padding: .75rem 0;
    }
    .workflow-form-shell .workflow-screen-actions,
    .workflow-form-shell .workflow-form-footer,
    .workflow-form-shell .btn,
    .workflow-form-shell .form-text,
    .workflow-form-shell .invalid-feedback,
    .workflow-form-shell .btn-close {
      display: none !important;
    }
    .workflow-form-shell .workflow-action-panel,
    .workflow-form-shell .workflow-form-meta {
      background: #fff !important;
      border: 1px solid #d6dee8;
      break-inside: avoid;
    }
    .workflow-form-shell .form-control,
    .workflow-form-shell .form-select {
      background: #fff !important;
      border: 0 !important;
      box-shadow: none !important;
      color: #000 !important;
      padding-left: 0 !important;
    }
    .workflow-form-shell textarea.form-control {
      min-height: auto;
    }
  }
</style>

<!-- Safety net: if rendered via renderPartial WITHOUT iframe=1, inject Bootstrap so styling still applies -->
<script>
(function () {
  if (!window.bootstrap) {
    var head = document.head || document.getElementsByTagName('head')[0];

    var css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'assets/css/bootstrap.min.css';
    head.appendChild(css);

    var icons = document.createElement('link');
    icons.rel = 'stylesheet';
    icons.href = 'assets/icons/bootstrap-icons.css';
    head.appendChild(icons);

    var js = document.createElement('script');
    js.src = 'assets/js/bootstrap.bundle.min.js';
    head.appendChild(js);
  }
})();
</script>

<div class="workflow-form-shell">
<div class="card shadow-sm">
  <!-- Header: consistent with DataObjectCodesForm/UserForm -->
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <i class="bi bi-clipboard<?= $id > 0 ? '-check' : '-plus' ?> me-2"></i>
      <strong><?= h($title) ?></strong>
    </div>

    <?php
      // Build a resilient back URL
      $qs = [
        'route'    => 'workflow/list',
        'q'        => $q,
        'page'     => $page,
        'pageSize' => $pageSize,
      ];
      if ($typeID   !== null) $qs['typeID']   = (string)$typeID;
      if ($statusID !== null) $qs['statusID'] = (string)$statusID;
      if ($status   !== '')   $qs['status']   = $status;
      if ($mine !== null)     $qs['mine']     = (string)$mine;
      if ($mine !== null)     $qs['task_scope'] = $taskScope;
      if ($assignedToUserIDFilter !== null) $qs['assignedToUserID'] = (string)$assignedToUserIDFilter;
      if ($isIframe)          $qs['iframe']   = '1';
      $backUrl = wf_form_build_query($qs);
    ?>
    <div class="d-flex gap-2 workflow-screen-actions">
      <?php if ($id > 0): ?>
        <?php if ($canSendTaskReminder && !$isTaskClosed): ?>
          <form method="post" action="index.php?route=workflow/send-reminder" class="needs-validation d-inline workflow-reminder-form" novalidate>
            <?php if (function_exists('csrf_field')): ?>
              <?= csrf_field(); ?>
            <?php endif; ?>
            <?= wf_form_render_context_inputs() ?>
            <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
            <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
            <input type="hidden" name="q" value="<?= h($q) ?>">
            <input type="hidden" name="page" value="<?= h((string)$page) ?>">
            <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
            <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
            <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
            <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
            <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
            <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>
            <button type="submit" class="btn btn-sm btn-outline-primary">
              <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
              <i class="bi bi-envelope-paper me-1"></i><?= h(__t('workflow_task_send_reminder')) ?>
            </button>
          </form>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary workflow-print-btn" onclick="window.print()">
          <i class="bi bi-printer me-1"></i><?= h(__t('workflow_task_print')) ?>
        </button>
      <?php endif; ?>
      <a href="<?= h($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= __t('back') ?>
      </a>
    </div>
  </div>

  <div class="card-body">
    <?php if (is_array($flash) && !empty($flash['text'])): ?>
      <div class="alert alert-<?= h($flash['type'] ?? 'info') ?> alert-dismissible fade show mb-3" role="alert">
        <?= $flash['text'] /* controller controls content */ ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= __t('close') ?>"></button>
      </div>
    <?php endif; ?>

    <!-- Form (needs-validation for consistent Bootstrap feedback) -->
    <form method="post" action="index.php?route=workflow/save" enctype="multipart/form-data" class="needs-validation" novalidate>
      <?php if (function_exists('csrf_field')): ?>
        <?= csrf_field(); ?>
      <?php elseif (function_exists('csrf_token')): ?>
        <input type="hidden" name="_csrf" value="<?= h((string)csrf_token()) ?>">
      <?php endif; ?>
      <?= wf_form_render_context_inputs() ?>

      <?php if ($id > 0): ?>
        <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
      <?php endif; ?>

      <!-- Preserve navigation context on SAVE -->
      <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
      <input type="hidden" name="q"        value="<?= h($q) ?>">
      <input type="hidden" name="page"     value="<?= h((string)$page) ?>">
      <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
      <?php if ($typeID   !== null): ?><input type="hidden" name="typeID"   value="<?= h((string)$typeID)   ?>"><?php endif; ?>
      <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
      <?php if ($status   !== ''):   ?><input type="hidden" name="status"   value="<?= h($status) ?>"><?php endif; ?>
      <?php if ($mine     !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
      <?php if ($mine     !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
      <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>

      <?php if ($isRecipientSimpleView): ?>
        <div class="workflow-recipient-summary mb-3">
          <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
              <div class="workflow-meta-label mb-1"><?= h(__t('workflow_task_summary')) ?></div>
              <h2 class="workflow-recipient-title"><?= h($valTitle !== '' ? $valTitle : __t('untitled')) ?></h2>
            </div>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
              <span class="badge <?= $isTaskOverdue ? 'text-bg-danger' : ($isTaskClosed ? 'text-bg-success' : 'text-bg-primary') ?>">
                <?= h((string)($task['StatusName'] ?? $statuses[$valStatusID] ?? '-')) ?>
              </span>
              <span class="badge <?= $valPriorityCode === 'URGENT' ? 'text-bg-danger' : ($valPriorityCode === 'HIGH' ? 'text-bg-warning' : ($valPriorityCode === 'LOW' ? 'text-bg-secondary' : 'text-bg-info')) ?>">
                <?= h(__t([
                    'LOW' => 'workflow_task_priority_low',
                    'NORMAL' => 'workflow_task_priority_normal',
                    'HIGH' => 'workflow_task_priority_high',
                    'URGENT' => 'workflow_task_priority_urgent',
                ][$valPriorityCode] ?? 'workflow_task_priority_normal')) ?>
              </span>
            </div>
          </div>

          <?php if ($valDescription !== ''): ?>
            <div class="workflow-recipient-description workflow-rich-text-content mb-3"><?= workflow_render_rich_text($valDescription) ?></div>
          <?php endif; ?>

          <div class="workflow-recipient-facts">
            <div class="workflow-recipient-fact">
              <div class="workflow-meta-label"><?= h(__t('workflow_task_due')) ?></div>
              <div class="workflow-meta-value <?= $isTaskOverdue ? 'text-danger' : '' ?>">
                <?= h($valDueDate !== '' ? wf_form_format_datetime($valDueDate) : __t('workflow_task_not_set')) ?>
              </div>
            </div>
            <div class="workflow-recipient-fact">
              <div class="workflow-meta-label"><?= h(__t('workflow_task_from')) ?></div>
              <div class="workflow-meta-value"><?= h(wf_form_user_label((array)$task, 'CreatedByName', 'CreatedByUserID')) ?></div>
            </div>
            <div class="workflow-recipient-fact">
              <div class="workflow-meta-label"><?= h(__t('type')) ?></div>
              <div class="workflow-meta-value"><?= h((string)($task['TaskTypeName'] ?? $types[$valTaskTypeID] ?? '-')) ?></div>
            </div>
            <?php if (!empty($task['ProjectName'])): ?>
              <div class="workflow-recipient-fact">
                <div class="workflow-meta-label"><?= h(__t('workflow_project_project')) ?></div>
                <div class="workflow-meta-value"><?= h((string)$task['ProjectName']) ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
      <div class="row mb-3">
        <div class="col-12 col-lg-8">
          <label class="form-label"><?= h(__t('title')) ?></label>
          <input type="text" name="Title" class="form-control" required value="<?= h($valTitle) ?>" <?= $detailsDisabled ?>>
          <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label"><?= h(__t('due_date')) ?></label>
          <input type="date" name="DueDate" id="DueDate" class="form-control" required value="<?= h($valDueDate) ?>" <?= $detailsDisabled ?>>
          <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="WorkflowTaskDescription"><?= h(__t('description')) ?></label>
        <div class="workflow-rich-text" data-workflow-rich-text>
          <div class="workflow-rich-text-toolbar" role="toolbar" aria-label="<?= h(__t('description')) ?>">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-rich-command="bold" title="<?= h(__t('workflow_task_description_format_bold')) ?>" aria-label="<?= h(__t('workflow_task_description_format_bold')) ?>" <?= $detailsDisabled ?>>
              <i class="bi bi-type-bold"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-rich-command="italic" title="<?= h(__t('workflow_task_description_format_italic')) ?>" aria-label="<?= h(__t('workflow_task_description_format_italic')) ?>" <?= $detailsDisabled ?>>
              <i class="bi bi-type-italic"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-rich-command="underline" title="<?= h(__t('workflow_task_description_format_underline')) ?>" aria-label="<?= h(__t('workflow_task_description_format_underline')) ?>" <?= $detailsDisabled ?>>
              <i class="bi bi-type-underline"></i>
            </button>
            <span class="vr mx-1"></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-rich-command="insertUnorderedList" title="<?= h(__t('workflow_task_description_format_bullets')) ?>" aria-label="<?= h(__t('workflow_task_description_format_bullets')) ?>" <?= $detailsDisabled ?>>
              <i class="bi bi-list-ul"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-rich-command="insertOrderedList" title="<?= h(__t('workflow_task_description_format_numbers')) ?>" aria-label="<?= h(__t('workflow_task_description_format_numbers')) ?>" <?= $detailsDisabled ?>>
              <i class="bi bi-list-ol"></i>
            </button>
            <span class="vr mx-1"></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-rich-command="createLink" title="<?= h(__t('workflow_task_description_format_link')) ?>" aria-label="<?= h(__t('workflow_task_description_format_link')) ?>" <?= $detailsDisabled ?>>
              <i class="bi bi-link-45deg"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-workflow-rich-command="removeFormat" title="<?= h(__t('workflow_task_description_format_clear')) ?>" aria-label="<?= h(__t('workflow_task_description_format_clear')) ?>" <?= $detailsDisabled ?>>
              <i class="bi bi-eraser"></i>
            </button>
          </div>
          <div
            class="form-control workflow-rich-text-editor"
            contenteditable="<?= $detailsDisabled === '' ? 'true' : 'false' ?>"
            data-workflow-rich-editor
            data-placeholder="<?= h(__t('description')) ?>"
            aria-labelledby="WorkflowTaskDescription"
          ><?= workflow_render_rich_text($valDescription) ?></div>
          <textarea id="WorkflowTaskDescription" name="Description" rows="4" class="form-control workflow-rich-text-source" required <?= $detailsDisabled ?>><?= h($valDescription) ?></textarea>
          <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
        </div>
      </div>

      <?php if ($isProjectTaskContext): ?>
        <div class="workflow-action-panel mb-3">
          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
            <div>
              <div class="fw-semibold"><?= h(__t('workflow_project_project_plan')) ?></div>
              <div class="text-muted small"><?= h(__t('workflow_project_task_fields_help')) ?></div>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-projects/list">
              <i class="bi bi-kanban me-1"></i><?= h(__t('workflow_projects')) ?>
            </a>
          </div>
          <?php if (!$workflowProjectsInstalled): ?>
            <div class="alert alert-warning py-2 mb-0">
              <?= h(__t('workflow_project_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
            </div>
          <?php else: ?>
            <input type="hidden"
                   id="WorkflowProjectID"
                   name="WorkflowProjectID"
                   value="<?= h((string)$valWorkflowProjectID) ?>"
                   data-project-start="<?= h($lockedProjectStartDate) ?>"
                   data-project-end="<?= h($lockedProjectEndDate) ?>">
            <div class="row g-3">
              <div class="col-12 col-lg-4">
                <label class="form-label" for="WorkflowProjectLocked"><?= h(__t('workflow_project_project')) ?></label>
                <input type="text" class="form-control" id="WorkflowProjectLocked" value="<?= h($lockedProjectLabel) ?>" readonly>
              </div>
              <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="PlannedStartDate"><?= h(__t('workflow_task_planned_start')) ?></label>
                <input type="date" class="form-control" id="PlannedStartDate" name="PlannedStartDate" value="<?= h($valPlannedStartDate) ?>" <?= $detailsDisabled ?>>
              </div>
              <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="PlannedEndDate"><?= h(__t('workflow_task_planned_end')) ?></label>
                <input type="date" class="form-control" id="PlannedEndDate" name="PlannedEndDate" value="<?= h($valPlannedEndDate) ?>" <?= $detailsDisabled ?>>
                <div class="invalid-feedback"><?= h(__t('workflow_task_planned_dates_invalid')) ?></div>
              </div>
              <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="PercentComplete"><?= h(__t('workflow_task_percent_complete')) ?></label>
                <input type="number" min="0" max="100" step="1" class="form-control" id="PercentComplete" name="PercentComplete" value="<?= h((string)(int)$valPercentComplete) ?>" <?= $detailsDisabled ?>>
              </div>
              <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="ProjectUtilisationPercent"><?= h(__t('workflow_task_project_utilisation')) ?></label>
                <input type="number" min="0" max="100" step="1" class="form-control" id="ProjectUtilisationPercent" name="ProjectUtilisationPercent" value="<?= h((string)(int)$valProjectUtilisationPercent) ?>" <?= $detailsDisabled ?>>
              </div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-12 col-lg-8">
                <label class="form-label" for="WorkflowRequirementID"><?= h(__t('workflow_task_requirement')) ?></label>
                <?php if (!$workflowLinksInstalled): ?>
                  <div class="alert alert-warning py-2 mb-0">
                    <?= h(__t('workflow_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
                  </div>
                <?php elseif (!$workflowRequirementsInstalled): ?>
                  <div class="alert alert-warning py-2 mb-0">
                    <?= h(__t('workflow_requirement_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
                  </div>
                <?php else: ?>
                  <select class="form-select"
                          id="WorkflowRequirementID"
                          name="WorkflowRequirementID"
                          <?= $detailsDisabled ?>>
                    <option value=""><?= h(__t('workflow_task_requirement_none')) ?></option>
                    <?php foreach ($workflowRequirementOptions as $requirementOption): ?>
                      <?php
                        $requirementID = (int)($requirementOption['WorkflowRequirementID'] ?? 0);
                        if ($requirementID <= 0) {
                            continue;
                        }
                        $selected = $requirementID === $selectedWorkflowRequirementID ? 'selected' : '';
                      ?>
                      <option value="<?= h((string)$requirementID) ?>" <?= $selected ?>>
                        <?= h(wf_form_requirement_option_label($requirementOption)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ($workflowRequirementOptions === []): ?>
                    <div class="form-text"><?= h(__t('workflow_task_requirement_no_project_requirements')) ?></div>
                  <?php else: ?>
                    <div class="form-text"><?= h(__t('workflow_task_requirement_help')) ?></div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
              <div class="col-12 col-lg-4 d-flex align-items-end">
                <a class="btn btn-sm btn-outline-secondary w-100" href="index.php?route=workflow-requirements/matrix&workflowProjectID=<?= h((string)$valWorkflowProjectID) ?>">
                  <i class="bi bi-diagram-3 me-1"></i><?= h(__t('workflow_requirement_matrix')) ?>
                </a>
              </div>
            </div>
            <div class="row g-3 mt-1">
              <div class="col-12 col-lg-6">
                <label class="form-label" for="ParentWorkflowTaskID"><?= h(__t('workflow_project_task_parent')) ?></label>
                <select class="form-select"
                        id="ParentWorkflowTaskID"
                        name="ParentWorkflowTaskID"
                        <?= $detailsDisabled ?>>
                  <option value=""><?= h(__t('workflow_project_task_no_parent')) ?></option>
                  <?php foreach ($projectTaskOptions as $optionTask): ?>
                    <?php
                      $optionTaskID = (int)($optionTask['WorkflowTaskID'] ?? 0);
                      if ($optionTaskID <= 0) {
                          continue;
                      }
                      $selected = $optionTaskID === $valParentWorkflowTaskID ? 'selected' : '';
                    ?>
                    <option value="<?= h((string)$optionTaskID) ?>" <?= $selected ?>>
                      <?= h(wf_form_task_option_label($optionTask)) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <div class="form-text"><?= h(__t('workflow_project_task_parent_help')) ?></div>
              </div>
              <div class="col-12 col-lg-6">
                <label class="form-label" for="DependsOnWorkflowTaskIDs"><?= h(__t('workflow_project_task_dependencies')) ?></label>
                <?php if (!$workflowTaskDependenciesInstalled): ?>
                  <div class="alert alert-warning py-2 mb-0">
                    <?= h(__t('workflow_project_task_dependencies_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
                  </div>
                <?php else: ?>
                  <select class="form-select"
                          id="DependsOnWorkflowTaskIDs"
                          name="DependsOnWorkflowTaskIDs[]"
                          multiple
                          size="<?= h((string)min(5, max(2, count($projectTaskOptions)))) ?>"
                          <?= $detailsDisabled ?>>
                    <?php foreach ($projectTaskOptions as $optionTask): ?>
                      <?php
                        $optionTaskID = (int)($optionTask['WorkflowTaskID'] ?? 0);
                        if ($optionTaskID <= 0) {
                            continue;
                        }
                        $selected = isset($valDependsOnWorkflowTaskIDs[$optionTaskID]) ? 'selected' : '';
                      ?>
                      <option value="<?= h((string)$optionTaskID) ?>" <?= $selected ?>>
                        <?= h(wf_form_task_option_label($optionTask)) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="form-text"><?= h(__t('workflow_project_task_dependencies_help')) ?></div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="row mb-3">
        <div class="col-12 col-md-3">
          <label class="form-label"><?= h(__t('type')) ?></label>
          <?php if ($isProjectTaskContext): ?>
            <input type="hidden"
                   name="TaskTypeID"
                   id="TaskTypeID"
                   value="<?= h((string)$projectTaskTypeID) ?>"
                   data-project-task-type-id="<?= h((string)$projectTaskTypeID) ?>">
            <input type="text" class="form-control" value="<?= h($projectTaskTypeLabel) ?>" readonly>
            <div class="form-text" data-project-task-type-help><?= h(__t('workflow_project_task_type_auto_help')) ?></div>
          <?php else: ?>
            <select name="TaskTypeID" id="TaskTypeID" class="form-select" required data-project-task-type-id="<?= h((string)$projectTaskTypeID) ?>" <?= $detailsDisabled ?>>
              <option value=""><?= h(__t('select_type')) ?></option>
              <?php foreach ($types as $tid => $tname): ?>
                <?php
                  if ($projectTaskTypeID > 0 && (int)$tid === $projectTaskTypeID) {
                      continue;
                  }
                  $sel = ((int)$tid === $valTaskTypeID) ? 'selected' : '';
                ?>
                <option value="<?= h((string)$tid) ?>" <?= $sel ?>><?= h((string)$tname) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
          <?php endif; ?>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label"><?= h(__t('status')) ?></label>
          <?php if ($id <= 0): ?>
            <input type="hidden" name="StatusID" value="<?= h((string)$valStatusID) ?>">
            <input type="text" class="form-control" value="<?= h((string)($statuses[$valStatusID] ?? __t('workflow_task_open'))) ?>" readonly>
          <?php else: ?>
            <select name="StatusID" class="form-select" required <?= $detailsDisabled ?>>
              <option value=""><?= h(__t('select_status')) ?></option>
              <?php foreach ($statuses as $sid => $sname): ?>
                <?php $sel = ((int)$sid === $valStatusID) ? 'selected' : ''; ?>
                <option value="<?= h((string)$sid) ?>" <?= $sel ?>><?= h((string)$sname) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
          <?php endif; ?>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label"><?= h(__t('workflow_task_priority')) ?></label>
          <select name="PriorityCode" class="form-select" required <?= $detailsDisabled ?>>
            <?php foreach (['LOW' => 'workflow_task_priority_low', 'NORMAL' => 'workflow_task_priority_normal', 'HIGH' => 'workflow_task_priority_high', 'URGENT' => 'workflow_task_priority_urgent'] as $code => $labelKey): ?>
              <option value="<?= h($code) ?>" <?= $valPriorityCode === $code ? 'selected' : '' ?>><?= h(__t($labelKey)) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
        </div>

        <div class="col-12 col-md-3">
          <label class="form-label"><?= h(__t('assigned_to')) ?></label>
          <select name="<?= $id > 0 ? 'AssignedToUserID' : 'AssignedToUserIDs[]' ?>"
                  id="workflowAssignedUsers"
                  class="form-select"
                  <?= $id > 0 ? '' : 'multiple size="6"' ?>
                  <?= $id > 0 ? 'required' : '' ?>
                  <?= $id <= 0 ? 'data-workflow-assignees="1"' : '' ?>
                  <?= $detailsDisabled ?>>
            <?php if ($id > 0): ?>
              <option value=""><?= h(__t('select_user')) ?></option>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
              <?php
                $uid   = (int)($u['UserID'] ?? 0);
                $label = (string)($u['DisplayName'] ?? $u['Username'] ?? __t('user_number', ['id' => $uid]));
                $sel   = $uid === $valAssignedToID ? 'selected' : '';
              ?>
              <option value="<?= h((string)$uid) ?>" <?= $sel ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($id <= 0): ?>
            <div class="form-text"><?= h(__t('workflow_task_select_recipients_help')) ?></div>
          <?php endif; ?>
          <div class="invalid-feedback"><?= $id <= 0 ? h(__t('workflow_task_select_recipient_or_group')) : h(__t('required_field')) ?></div>
        </div>
      </div>

      <?php if ($id <= 0): ?>
        <div class="workflow-action-panel mb-3">
          <div class="fw-semibold mb-2"><?= h(__t('workflow_task_completion_rule')) ?></div>
          <div class="row g-2">
            <div class="col-12 col-lg-6">
              <div class="form-check">
                <input class="form-check-input"
                       type="radio"
                       name="WorkflowTaskCompletionRule"
                       id="WorkflowTaskCompletionRuleIndividual"
                       value="INDIVIDUAL"
                       checked
                       <?= $detailsDisabled ?>>
                <label class="form-check-label" for="WorkflowTaskCompletionRuleIndividual">
                  <?= h(__t('workflow_task_completion_rule_individual')) ?>
                </label>
              </div>
            </div>
            <div class="col-12 col-lg-6">
              <div class="form-check">
                <input class="form-check-input"
                       type="radio"
                       name="WorkflowTaskCompletionRule"
                       id="WorkflowTaskCompletionRuleAny"
                       value="ANY_COMPLETES_ALL"
                       <?= $detailsDisabled ?>>
                <label class="form-check-label" for="WorkflowTaskCompletionRuleAny">
                  <?= h(__t('workflow_task_completion_rule_any')) ?>
                </label>
              </div>
            </div>
          </div>
          <div class="form-text">
            <?= h(__t('workflow_task_completion_rule_help')) ?>
          </div>
        </div>

        <div class="workflow-action-panel mb-3" data-workflow-groups-panel="1">
          <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
            <div>
              <div class="fw-semibold"><?= h(__t('workflow_task_user_groups')) ?></div>
              <div class="text-muted small"><?= h(__t('workflow_task_user_groups_help')) ?></div>
            </div>
            <a class="btn btn-sm btn-outline-secondary" href="index.php?route=workflow-user-groups/list">
              <i class="bi bi-people me-1"></i><?= h(__t('workflow_task_manage_groups')) ?>
            </a>
          </div>
          <?php if (!$workflowUserGroupsInstalled): ?>
            <div class="alert alert-warning py-2 mb-0">
              <?= h(__t('workflow_task_user_groups_missing', ['script' => 'backend-php/config/sql/create_workflow_user_groups.sql'])) ?>
            </div>
          <?php elseif ($workflowUserGroups === []): ?>
            <div class="alert alert-info py-2 mb-0">
              <?= h(__t('workflow_task_no_active_groups')) ?>
            </div>
          <?php else: ?>
            <select name="WorkflowUserGroupIDs[]"
                    id="workflowUserGroups"
                    class="form-select"
                    multiple
                    size="5"
                    data-workflow-groups="1"
                    <?= $detailsDisabled ?>>
              <?php foreach ($workflowUserGroups as $group): ?>
                <?php
                  $groupId = (int)($group['WorkflowUserGroupID'] ?? 0);
                  if ($groupId <= 0) {
                      continue;
                  }
                  $memberCount = (int)($group['ActiveMemberCount'] ?? 0);
                  $label = trim((string)($group['GroupName'] ?? ''));
                  if ($label === '') {
                      $label = 'Workflow Group #' . $groupId;
                  }
                ?>
                <option value="<?= $groupId ?>"><?= h($label) ?> (<?= $memberCount ?> active)</option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>
        </div>

        <div class="workflow-action-panel mb-3">
          <div class="fw-semibold mb-2"><?= h(__t('workflow_task_attachments')) ?></div>
          <?php if (!$workflowTaskAttachmentsInstalled): ?>
            <div class="alert alert-warning py-2 mb-0">
              <?= h(__t('workflow_task_attachments_missing', ['script' => 'backend-php/config/sql/alter_workflow_tasks_add_response_forwarding_activity.sql'])) ?>
            </div>
          <?php else: ?>
            <label class="form-label"><?= h(__t('workflow_task_attach_files')) ?></label>
            <input type="file"
                   name="TaskAttachments[]"
                   class="form-control"
                   multiple
                   accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.gif,.zip"
                   <?= $detailsDisabled ?>>
            <div class="form-text">
              <?= h(__t('workflow_task_create_attachments_help')) ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($isProjectTaskContext): ?>
        <input type="hidden" name="RelatedEntity" value="<?= h($valRelatedEntity) ?>">
        <input type="hidden" name="RelatedKey" value="<?= h($valRelatedKey) ?>">
      <?php else: ?>
        <div class="row mb-3">
          <div class="col-12 col-md-6">
            <label class="form-label"><?= h(__t('related_entity')) ?></label>
            <input type="text" name="RelatedEntity" class="form-control" value="<?= h($valRelatedEntity) ?>" <?= $detailsDisabled ?>>
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label"><?= h(__t('related_key')) ?></label>
            <input type="text" name="RelatedKey" class="form-control" value="<?= h($valRelatedKey) ?>" <?= $detailsDisabled ?>>
          </div>
        </div>
      <?php endif; ?>

      <div class="workflow-action-panel mb-3">
        <div class="fw-semibold mb-2"><?= h(__t('workflow_task_notifications')) ?></div>
        <div class="row g-2">
          <div class="col-12 col-lg-6">
            <div class="form-check">
              <input type="checkbox"
                     class="form-check-input"
                     id="NotifyCreatorOnCompletion"
                     name="NotifyCreatorOnCompletion"
                     value="1"
                     <?= $valNotifyCreatorOnCompletion ? 'checked' : '' ?>
                     <?= $detailsDisabled ?>>
              <label class="form-check-label" for="NotifyCreatorOnCompletion">
                <?= h(__t('workflow_task_notify_completion')) ?>
              </label>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="form-check">
              <input type="checkbox"
                     class="form-check-input"
                     id="NotifyCreatorOnUpdate"
                     name="NotifyCreatorOnUpdate"
                     value="1"
                     <?= $valNotifyCreatorOnUpdate ? 'checked' : '' ?>
                     <?= $detailsDisabled ?>>
              <label class="form-check-label" for="NotifyCreatorOnUpdate">
                <?= h(__t('workflow_task_notify_update')) ?>
              </label>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="form-check">
              <input type="checkbox"
                     class="form-check-input"
                     id="NotifyAudienceOnComment"
                     name="NotifyAudienceOnComment"
                     value="1"
                     <?= $valNotifyAudienceOnComment ? 'checked' : '' ?>
                     <?= $detailsDisabled ?>>
              <label class="form-check-label" for="NotifyAudienceOnComment">
                <?= h(__t('workflow_task_notify_comment')) ?>
              </label>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="form-check">
              <input type="checkbox"
                     class="form-check-input"
                     id="AutoReminderEnabled"
                     name="AutoReminderEnabled"
                     value="1"
                     <?= $valAutoReminderEnabled ? 'checked' : '' ?>
                     <?= $detailsDisabled ?>>
              <label class="form-check-label" for="AutoReminderEnabled">
                <?= h(__t('workflow_task_auto_reminder_enable')) ?>
              </label>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="AutoReminderDaysBeforeDue"><?= h(__t('workflow_task_reminder_timing')) ?></label>
            <div class="input-group input-group-sm">
              <input type="number"
                     class="form-control"
                     id="AutoReminderDaysBeforeDue"
                     name="AutoReminderDaysBeforeDue"
                     min="1"
                     max="365"
                     step="1"
                     value="<?= h((string)$valAutoReminderDaysBeforeDue) ?>"
                     <?= $detailsDisabled ?>>
              <span class="input-group-text"><?= h(__t('workflow_task_days_before_due')) ?></span>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <div class="form-check">
              <input type="checkbox"
                     class="form-check-input"
                     id="OverdueEscalationEnabled"
                     name="OverdueEscalationEnabled"
                     value="1"
                     <?= $valOverdueEscalationEnabled ? 'checked' : '' ?>
                     <?= $detailsDisabled ?>>
              <label class="form-check-label" for="OverdueEscalationEnabled">
                <?= h(__t('workflow_task_overdue_escalation_enable')) ?>
              </label>
            </div>
          </div>
          <div class="col-12 col-lg-6">
            <label class="form-label" for="OverdueEscalationDaysAfterDue"><?= h(__t('workflow_task_escalation_timing')) ?></label>
            <div class="input-group input-group-sm">
              <input type="number"
                     class="form-control"
                     id="OverdueEscalationDaysAfterDue"
                     name="OverdueEscalationDaysAfterDue"
                     min="0"
                     max="365"
                     step="1"
                     value="<?= h((string)$valOverdueEscalationDaysAfterDue) ?>"
                     <?= $detailsDisabled ?>>
              <span class="input-group-text"><?= h(__t('workflow_task_days_after_due')) ?></span>
            </div>
          </div>
          <?php if ($id > 0): ?>
            <?php if ($lastNotificationSentAt !== ''): ?>
              <div class="col-12">
                <div class="small text-muted border rounded px-3 py-2">
                  <span class="fw-semibold text-body"><?= h(__t('workflow_task_last_notification_sent')) ?></span>
                  <span class="badge text-bg-success ms-1"><?= h($lastNotificationLabel) ?></span>
                  <span class="ms-1"><?= h(wf_form_format_datetime($lastNotificationSentAt)) ?></span>
                </div>
              </div>
            <?php endif; ?>
            <div class="col-12">
              <div class="small text-muted border rounded px-3 py-2">
                <span class="fw-semibold text-body"><?= h(__t('workflow_task_auto_reminder_status')) ?></span>
                <?php if (!empty($task['AutoReminderSentAt'])): ?>
                  <span class="badge text-bg-success ms-1"><?= h(__t('workflow_task_status_sent')) ?></span>
                  <span class="ms-1"><?= h(wf_form_format_datetime((string)$task['AutoReminderSentAt'])) ?></span>
                <?php elseif ($valAutoReminderEnabled && $isTaskOverdue && !empty($task['OverdueEscalationSentAt'])): ?>
                  <span class="badge text-bg-info ms-1"><?= h(__t('workflow_task_status_superseded')) ?></span>
                  <span class="ms-1"><?= h(__t('workflow_task_auto_reminder_superseded_help')) ?></span>
                <?php elseif ($valAutoReminderEnabled && $isTaskOverdue): ?>
                  <span class="badge text-bg-secondary ms-1"><?= h(__t('workflow_task_status_past_due')) ?></span>
                  <span class="ms-1"><?= h(__t('workflow_task_auto_reminder_past_due_help')) ?></span>
                <?php elseif ($valAutoReminderEnabled): ?>
                  <span class="badge text-bg-warning ms-1"><?= h(__t('workflow_task_status_pending')) ?></span>
                  <span class="ms-1"><?= h(__t('workflow_task_auto_reminder_pending_help')) ?></span>
                <?php else: ?>
                  <span class="badge text-bg-secondary ms-1"><?= h(__t('workflow_task_status_off')) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-12">
              <div class="small text-muted border rounded px-3 py-2">
                <span class="fw-semibold text-body"><?= h(__t('workflow_task_overdue_escalation_status')) ?></span>
                <?php if (!empty($task['OverdueEscalationSentAt'])): ?>
                  <span class="badge text-bg-danger ms-1"><?= h(__t('workflow_task_status_sent')) ?></span>
                  <span class="ms-1"><?= h(wf_form_format_datetime((string)$task['OverdueEscalationSentAt'])) ?></span>
                <?php elseif ($valOverdueEscalationEnabled): ?>
                  <span class="badge text-bg-warning ms-1"><?= h(__t('workflow_task_status_pending')) ?></span>
                  <span class="ms-1"><?= h(__t('workflow_task_overdue_escalation_pending_help')) ?></span>
                <?php else: ?>
                  <span class="badge text-bg-secondary ms-1"><?= h(__t('workflow_task_status_off')) ?></span>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($id > 0): ?>
        <div class="mb-3 workflow-form-meta">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div class="fw-semibold"><?= h(__t('task_details')) ?></div>
            <div class="small text-muted"><?= h(__t('workflow_task_times_shown_in', ['timezone' => $workflowTimezone])) ?></div>
          </div>
          <div class="row g-3">
            <div class="col-12 col-md-4 col-xl-2">
              <div class="workflow-meta-label"><?= h(__t('task_id')) ?></div>
              <div class="workflow-meta-value">#<?= h((string)$id) ?></div>
            </div>
            <div class="col-12 col-md-4 col-xl-2">
              <div class="workflow-meta-label"><?= h(__t('created_by')) ?></div>
              <div class="workflow-meta-value"><?= h(wf_form_user_label((array)$task, 'CreatedByName', 'CreatedByUserID')) ?></div>
            </div>
            <?php if (!empty($task['CreatedAt'])): ?>
              <div class="col-12 col-md-4 col-xl-2">
                <div class="workflow-meta-label"><?= __t('created_at') ?></div>
                <div class="workflow-meta-value"><?= h(wf_form_format_datetime((string)$task['CreatedAt'])) ?></div>
              </div>
            <?php endif; ?>
            <div class="col-12 col-md-4 col-xl-2">
              <div class="workflow-meta-label"><?= h(__t('updated_by')) ?></div>
              <div class="workflow-meta-value"><?= h(wf_form_user_label((array)$task, 'UpdatedByName', 'UpdatedBy')) ?></div>
            </div>
            <?php if (!empty($task['UpdatedAt'])): ?>
              <div class="col-12 col-md-4 col-xl-2">
                <div class="workflow-meta-label"><?= __t('updated_at') ?></div>
                <div class="workflow-meta-value"><?= h(wf_form_format_datetime((string)$task['UpdatedAt'])) ?></div>
              </div>
            <?php endif; ?>
            <?php if (!empty($task['CompletedAt'])): ?>
              <div class="col-12 col-md-4 col-xl-2">
                <div class="workflow-meta-label"><?= h(__t('completed_at')) ?></div>
                <div class="workflow-meta-value"><?= h(wf_form_format_datetime((string)$task['CompletedAt'])) ?></div>
              </div>
            <?php endif; ?>
            <?php if (!empty($task['WorkflowTaskBatchID'])): ?>
              <div class="col-12 col-md-4 col-xl-2">
                <div class="workflow-meta-label"><?= h(__t('workflow_task_completion_rule_meta')) ?></div>
                <div class="workflow-meta-value"><?= h(wf_form_completion_rule_label($valWorkflowTaskCompletionRule)) ?></div>
              </div>
            <?php endif; ?>
            <div class="col-12 col-md-4 col-xl-2">
              <div class="workflow-meta-label"><?= h(__t('workflow_task_auto_reminder')) ?></div>
              <div class="workflow-meta-value">
                <?= $valAutoReminderEnabled ? h((string)$valAutoReminderDaysBeforeDue . ' ' . __t('workflow_task_days_before_due')) : '<span class="text-muted">' . h(__t('workflow_task_status_off')) . '</span>' ?>
              </div>
            </div>
            <div class="col-12 col-md-4 col-xl-2">
              <div class="workflow-meta-label"><?= h(__t('workflow_task_overdue_escalation')) ?></div>
              <div class="workflow-meta-value">
                <?= $valOverdueEscalationEnabled ? h((string)$valOverdueEscalationDaysAfterDue . ' ' . __t('workflow_task_days_after_due')) : '<span class="text-muted">' . h(__t('workflow_task_status_off')) . '</span>' ?>
              </div>
            </div>
            <?php if (!empty($task['AutoReminderSentAt'])): ?>
              <div class="col-12 col-md-4 col-xl-2">
                <div class="workflow-meta-label"><?= h(__t('workflow_task_auto_reminder_sent')) ?></div>
                <div class="workflow-meta-value"><?= h(wf_form_format_datetime((string)$task['AutoReminderSentAt'])) ?></div>
              </div>
            <?php endif; ?>
            <?php if (!empty($task['OverdueEscalationSentAt'])): ?>
              <div class="col-12 col-md-4 col-xl-2">
                <div class="workflow-meta-label"><?= h(__t('workflow_task_escalation_sent')) ?></div>
                <div class="workflow-meta-value"><?= h(wf_form_format_datetime((string)$task['OverdueEscalationSentAt'])) ?></div>
              </div>
            <?php endif; ?>
            <?php if (!empty($task['LastManualReminderSentAt'])): ?>
              <div class="col-12 col-md-4 col-xl-2">
                <div class="workflow-meta-label"><?= h(__t('workflow_task_manual_reminder')) ?></div>
                <div class="workflow-meta-value">
                  <?= h(wf_form_format_datetime((string)$task['LastManualReminderSentAt'])) ?>
                  <?php if (!empty($task['LastManualReminderByName'])): ?>
                    <span class="text-muted small"><?= h(__t('workflow_task_by')) ?> <?= h((string)$task['LastManualReminderByName']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
            <?php if ($workflowTaskViewsInstalled): ?>
              <div class="col-12 col-md-4 col-xl-2">
                <div class="workflow-meta-label"><?= h(__t('workflow_task_recipient_viewed')) ?></div>
                <div class="workflow-meta-value">
                  <?php if (!empty($task['RecipientLastViewedAt'])): ?>
                    <?= h(wf_form_format_datetime((string)$task['RecipientLastViewedAt'])) ?>
                    <?php if ((int)($task['RecipientViewCount'] ?? 0) > 1): ?>
                      <span class="text-muted small">(<?= h(__t('workflow_task_view_count_meta', ['count' => (int)$task['RecipientViewCount']])) ?>)</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-muted"><?= h(__t('workflow_task_not_viewed_yet')) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Bottom bar (consistent muted line + small buttons) -->
      <div class="d-flex justify-content-between align-items-center workflow-form-footer">
        <p class="text-muted small mb-0">
          <?= h(__t('form_save_hint')) ?>
        </p>
        <div class="d-flex gap-2">
          <a href="<?= h($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= __t('back') ?>
          </a>
          <?php if ($canManageTaskDetails): ?>
            <button type="submit" class="btn btn-sm btn-primary">
              <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
              <i class="bi bi-save me-1"></i><?= __t('save') ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </form>

    <?php if ($id > 0): ?>
      <div class="row g-3 mt-3<?= $isRecipientSimpleView ? ' workflow-recipient-actions' : '' ?>">
        <div class="col-12">
          <div class="workflow-action-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
              <div>
                <div class="fw-semibold"><?= h(__t('workflow_task_status_panel')) ?></div>
                <div class="text-muted small">
                  <?= h(__t('workflow_task_current_status')) ?>
                  <span class="fw-semibold"><?= h((string)($task['StatusName'] ?? $statuses[$valStatusID] ?? '-')) ?></span>
                  <?php if (!empty($task['CompletedAt'])): ?>
                    <span class="ms-2"><?= h(__t('workflow_task_completed_at_inline', ['time' => wf_form_format_datetime((string)$task['CompletedAt'])])) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($canTransitionTask): ?>
                <form method="post" action="index.php?route=workflow/transition" class="needs-validation" novalidate>
                  <?php if (function_exists('csrf_field')): ?>
                    <?= csrf_field(); ?>
                  <?php endif; ?>
                  <?= wf_form_render_context_inputs() ?>
                  <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
                  <input type="hidden" name="return_to" value="edit">
                  <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                  <input type="hidden" name="q" value="<?= h($q) ?>">
                  <input type="hidden" name="page" value="<?= h((string)$page) ?>">
                  <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
                  <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
                  <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
                  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                  <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
                  <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                  <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>
                  <input type="hidden" name="transition" value="" data-transition-field="1">
                  <?php if ($canRespondTask): ?>
                    <input type="hidden" name="RecipientResponse" value="" data-transition-response-field="1">
                  <?php endif; ?>
                  <div class="d-flex gap-2 flex-wrap">
                    <?php if (!$isTaskInProgress || $isTaskClosed): ?>
                      <button type="submit" class="btn btn-sm btn-outline-warning" data-transition-value="in_progress">
                        <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-play-circle me-1"></i><?= h(__t('workflow_task_mark_in_progress')) ?>
                      </button>
                    <?php endif; ?>
                    <?php if (!$isTaskClosed): ?>
                      <button type="submit" class="btn btn-sm btn-success" data-transition-value="complete">
                        <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-check2-circle me-1"></i><?= h(__t('workflow_task_mark_completed')) ?>
                      </button>
                    <?php endif; ?>
                  </div>
                </form>
              <?php else: ?>
                <span class="badge text-bg-secondary"><?= h(__t('workflow_task_read_only')) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="<?= $isRecipientSimpleView ? 'col-12' : 'col-12 col-xl-6' ?>">
          <div class="workflow-action-panel h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="fw-semibold"><?= h(__t('workflow_task_recipient_response')) ?></div>
                <?php if (!empty($task['RespondedAt'])): ?>
                  <div class="text-muted small">
                    <?= h(__t('workflow_task_last_updated_by_at', [
                        'user' => (string)($task['RespondedByName'] ?? ''),
                        'time' => wf_form_format_datetime((string)$task['RespondedAt']),
                    ])) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <?php if ($canRespondTask): ?>
              <form method="post" action="index.php?route=workflow/respond" class="needs-validation" novalidate>
                <?php if (function_exists('csrf_field')): ?>
                  <?= csrf_field(); ?>
                <?php endif; ?>
                <?= wf_form_render_context_inputs() ?>
                <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
                <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                <input type="hidden" name="q" value="<?= h($q) ?>">
                <input type="hidden" name="page" value="<?= h((string)$page) ?>">
                <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
                <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
                <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
                <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
                <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>
                <textarea name="RecipientResponse" class="form-control mb-2" rows="5" required><?= h($valRecipientResponse) ?></textarea>
                <button type="submit" class="btn btn-sm btn-primary">
                  <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                  <i class="bi bi-chat-left-text me-1"></i><?= h(__t('workflow_task_save_response')) ?>
                </button>
              </form>
            <?php elseif ($valRecipientResponse !== ''): ?>
              <div class="border rounded bg-white p-2 small"><?= nl2br(h($valRecipientResponse)) ?></div>
            <?php else: ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_task_no_recipient_response')) ?></p>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!$isRecipientSimpleView): ?>
        <div class="col-12 col-xl-6">
          <div class="workflow-action-panel h-100">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_task_forward_reassign')) ?></div>
            <?php if (!empty($task['LastForwardedAt'])): ?>
              <div class="text-muted small mb-2">
                <?= h(__t('workflow_task_last_forwarded_by_at', [
                    'user' => (string)($task['LastForwardedByName'] ?? ''),
                    'time' => wf_form_format_datetime((string)$task['LastForwardedAt']),
                ])) ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($task['LastForwardReason'])): ?>
              <div class="border rounded bg-white p-2 small mb-2"><?= nl2br(h((string)$task['LastForwardReason'])) ?></div>
            <?php endif; ?>
            <?php if ($canForwardTask): ?>
              <form method="post" action="index.php?route=workflow/forward" class="needs-validation" novalidate>
                <?php if (function_exists('csrf_field')): ?>
                  <?= csrf_field(); ?>
                <?php endif; ?>
                <?= wf_form_render_context_inputs() ?>
                <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
                <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                <input type="hidden" name="q" value="<?= h($q) ?>">
                <input type="hidden" name="page" value="<?= h((string)$page) ?>">
                <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
                <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
                <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
                <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
                <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>
                <div class="mb-2">
                  <label class="form-label"><?= h(__t('workflow_task_forward_to')) ?></label>
                  <select name="ForwardToUserID" class="form-select" required>
                    <option value=""><?= h(__t('select_user')) ?></option>
                    <?php foreach ($users as $u): ?>
                      <?php
                        $uid = (int)($u['UserID'] ?? 0);
                        $label = (string)($u['DisplayName'] ?? $u['Username'] ?? ('#'.$uid));
                      ?>
                      <option value="<?= h((string)$uid) ?>"><?= h($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
                </div>
                <div class="mb-2">
                  <label class="form-label"><?= h(__t('workflow_task_reason')) ?></label>
                  <textarea name="ForwardReason" class="form-control" rows="3" required></textarea>
                  <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
                </div>
                <button type="submit" class="btn btn-sm btn-outline-primary">
                  <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                  <i class="bi bi-send me-1"></i><?= h(__t('workflow_task_forward_task')) ?>
                </button>
              </form>
            <?php else: ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_task_cannot_forward_readonly')) ?></p>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="col-12">
          <ul class="nav nav-tabs workflow-secondary-tabs flex-nowrap overflow-auto" role="tablist">
            <?php if (!$isRecipientSimpleView): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="workflow-tab-notifications-tab" data-bs-toggle="tab" data-bs-target="#workflow-tab-notifications" type="button" role="tab" aria-controls="workflow-tab-notifications" aria-selected="true">
                <i class="bi bi-bell me-1"></i><?= h(__t('workflow_task_tab_notifications')) ?>
                <?php if ($notificationHistory !== []): ?><span class="badge text-bg-light border ms-1"><?= count($notificationHistory) ?></span><?php endif; ?>
              </button>
            </li>
            <?php endif; ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link<?= $isRecipientSimpleView ? ' active' : '' ?>" id="workflow-tab-discussion-tab" data-bs-toggle="tab" data-bs-target="#workflow-tab-discussion" type="button" role="tab" aria-controls="workflow-tab-discussion" aria-selected="<?= $isRecipientSimpleView ? 'true' : 'false' ?>">
                <i class="bi bi-chat-dots me-1"></i><?= h(__t('workflow_task_tab_discussion')) ?>
                <?php if ($workflowTaskCommentsInstalled && $taskComments !== []): ?><span class="badge text-bg-light border ms-1"><?= count($taskComments) ?></span><?php endif; ?>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="workflow-tab-files-tab" data-bs-toggle="tab" data-bs-target="#workflow-tab-files" type="button" role="tab" aria-controls="workflow-tab-files" aria-selected="false">
                <i class="bi bi-paperclip me-1"></i><?= h(__t('workflow_task_tab_files')) ?>
                <?php if ($workflowTaskAttachmentsInstalled && $taskAttachments !== []): ?><span class="badge text-bg-light border ms-1"><?= count($taskAttachments) ?></span><?php endif; ?>
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="workflow-tab-linked-tab" data-bs-toggle="tab" data-bs-target="#workflow-tab-linked" type="button" role="tab" aria-controls="workflow-tab-linked" aria-selected="false">
                <i class="bi bi-link-45deg me-1"></i><?= h(__t('workflow_task_tab_linked_work')) ?>
                <?php if ($workflowLinksInstalled && $taskLinks !== []): ?><span class="badge text-bg-light border ms-1"><?= count($taskLinks) ?></span><?php endif; ?>
              </button>
            </li>
            <?php if ($workflowTaskViewsInstalled): ?>
              <?php if (!$isRecipientSimpleView): ?>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="workflow-tab-views-tab" data-bs-toggle="tab" data-bs-target="#workflow-tab-views" type="button" role="tab" aria-controls="workflow-tab-views" aria-selected="false">
                  <i class="bi bi-eye me-1"></i><?= h(__t('workflow_task_tab_views')) ?>
                  <?php if ($taskViews !== []): ?><span class="badge text-bg-light border ms-1"><?= count($taskViews) ?></span><?php endif; ?>
                </button>
              </li>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (!$isRecipientSimpleView): ?>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="workflow-tab-activity-tab" data-bs-toggle="tab" data-bs-target="#workflow-tab-activity" type="button" role="tab" aria-controls="workflow-tab-activity" aria-selected="false">
                <i class="bi bi-clock-history me-1"></i><?= h(__t('workflow_task_tab_activity')) ?>
                <?php if ($taskActivity !== []): ?><span class="badge text-bg-light border ms-1"><?= count($taskActivity) ?></span><?php endif; ?>
              </button>
            </li>
            <?php endif; ?>
          </ul>
          <div class="tab-content workflow-secondary-tab-content">
            <?php if (!$isRecipientSimpleView): ?>
            <div class="tab-pane fade show active" id="workflow-tab-notifications" role="tabpanel" aria-labelledby="workflow-tab-notifications-tab" tabindex="0">
              <div class="workflow-action-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
              <div>
                <div class="fw-semibold"><?= h(__t('workflow_task_notification_history')) ?></div>
                <div class="text-muted small"><?= h(__t('workflow_task_notifications_help')) ?></div>
              </div>
              <?php if ($notificationHistory !== []): ?>
                <span class="badge text-bg-light border"><?= count($notificationHistory) ?></span>
              <?php endif; ?>
            </div>

            <?php if ($notificationHistory !== []): ?>
              <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                  <thead>
                    <tr>
                      <th scope="col"><?= h(__t('workflow_task_notification')) ?></th>
                      <th scope="col"><?= h(__t('workflow_task_notification_recipient')) ?></th>
                      <th scope="col"><?= h(__t('workflow_task_notification_sent')) ?></th>
                      <th scope="col"><?= h(__t('workflow_task_notification_triggered_by')) ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($notificationHistory as $notification): ?>
                      <tr>
                        <td>
                          <div class="workflow-notification-type"><?= h((string)$notification['label']) ?></div>
                          <?php if (!empty($notification['note'])): ?>
                            <div class="workflow-notification-meta"><?= h((string)$notification['note']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?= h((string)($notification['recipient'] !== '' ? $notification['recipient'] : '-')) ?></td>
                        <td>
                          <?= h(wf_form_format_datetime((string)$notification['sentAt'])) ?>
                          <?php if (($notification['source'] ?? '') === 'task'): ?>
                            <div class="workflow-notification-meta"><?= h(__t('workflow_task_recorded_on_task')) ?></div>
                          <?php endif; ?>
                        </td>
                        <td><?= h((string)($notification['actor'] !== '' ? $notification['actor'] : '-')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_task_no_notifications')) ?></p>
            <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="tab-pane fade<?= $isRecipientSimpleView ? ' show active' : '' ?>" id="workflow-tab-discussion" role="tabpanel" aria-labelledby="workflow-tab-discussion-tab" tabindex="0">
              <div class="workflow-action-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
              <div>
                <div class="fw-semibold"><?= h(__t('workflow_task_discussion_notes')) ?></div>
                <?php if ($workflowTaskCommentsInstalled): ?>
                  <div class="text-muted small"><?= h(__t('workflow_task_note_count', ['count' => count($taskComments)])) ?></div>
                <?php endif; ?>
              </div>
              <?php if ($workflowTaskCommentsInstalled && $taskComments !== []): ?>
                <span class="badge text-bg-light border"><?= count($taskComments) ?></span>
              <?php endif; ?>
            </div>

            <?php if (!$workflowTaskCommentsInstalled): ?>
              <div class="alert alert-warning py-2 mb-0">
                <?= h(__t('workflow_task_comments_missing', ['script' => 'backend-php/config/sql/alter_workflow_tasks_add_response_forwarding_activity.sql'])) ?>
              </div>
            <?php else: ?>
              <?php if ($canAddTaskComment): ?>
                <form method="post" action="index.php?route=workflow/save-comment" class="needs-validation mb-3" novalidate>
                  <?php if (function_exists('csrf_field')): ?>
                    <?= csrf_field(); ?>
                  <?php endif; ?>
                  <?= wf_form_render_context_inputs() ?>
                  <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
                  <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                  <input type="hidden" name="q" value="<?= h($q) ?>">
                  <input type="hidden" name="page" value="<?= h((string)$page) ?>">
                  <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
                  <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
                  <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
                  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                  <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
                  <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                  <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>

                  <label class="form-label"><?= h(__t('workflow_task_add_note')) ?></label>
                  <textarea name="CommentText" class="form-control mb-2" rows="3" required></textarea>
                  <div class="invalid-feedback"><?= h(__t('workflow_task_note_required')) ?></div>
                  <div class="form-check mb-2">
                    <input type="checkbox"
                           class="form-check-input"
                           id="NotifyAudienceForThisComment"
                           name="NotifyAudienceOnComment"
                           value="1"
                           <?= $valNotifyAudienceOnComment ? 'checked' : '' ?>>
                    <label class="form-check-label" for="NotifyAudienceForThisComment">
                      <?= h(__t('workflow_task_email_task_audience')) ?>
                    </label>
                  </div>
                  <button type="submit" class="btn btn-sm btn-outline-primary">
                    <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                    <i class="bi bi-chat-dots me-1"></i><?= h(__t('workflow_task_add_note')) ?>
                  </button>
                </form>
              <?php endif; ?>

              <?php if ($taskComments !== []): ?>
                <div class="d-grid gap-2">
                  <?php foreach ($taskComments as $comment): ?>
                    <?php
                      $commentId = (int)($comment['WorkflowTaskCommentID'] ?? 0);
                      if ($commentId <= 0) {
                          continue;
                      }
                      $commentAuthor = trim((string)($comment['CreatedByName'] ?? ''));
                      $commentAt = wf_form_format_datetime((string)($comment['CreatedAt'] ?? ''));
                      $canDeleteComment = $canAdminWorkflow
                          || (int)($task['CreatedByUserID'] ?? 0) === $currentUserId
                          || (int)($comment['CreatedByUserID'] ?? 0) === $currentUserId;
                    ?>
                    <div class="workflow-comment-item">
                      <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                          <div class="fw-semibold small"><?= h($commentAuthor !== '' ? $commentAuthor : '-') ?></div>
                          <div class="workflow-comment-meta"><?= h($commentAt !== '' ? $commentAt : '-') ?></div>
                        </div>
                        <?php if ($canDeleteComment): ?>
                          <form method="post"
                                action="index.php?route=workflow/delete-comment"
                                class="needs-validation"
                                novalidate
                                data-confirm-message="<?= h(__t('workflow_task_remove_discussion_confirm')) ?>"
                                data-confirm-button="<?= h(__t('workflow_task_remove')) ?>"
                                data-confirm-button-class="btn-danger">
                            <?php if (function_exists('csrf_field')): ?>
                              <?= csrf_field(); ?>
                            <?php endif; ?>
                            <?= wf_form_render_context_inputs() ?>
                            <input type="hidden" name="WorkflowTaskCommentID" value="<?= h((string)$commentId) ?>">
                            <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                            <input type="hidden" name="q" value="<?= h($q) ?>">
                            <input type="hidden" name="page" value="<?= h((string)$page) ?>">
                            <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
                            <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
                            <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
                            <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                            <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
                            <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                            <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                              <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                              <i class="bi bi-trash me-1"></i><?= h(__t('workflow_task_remove')) ?>
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                      <div class="small mt-2"><?= nl2br(h((string)($comment['CommentText'] ?? ''))) ?></div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <p class="text-muted small mb-0"><?= h(__t('workflow_task_no_discussion_notes')) ?></p>
              <?php endif; ?>
            <?php endif; ?>
              </div>
            </div>

            <div class="tab-pane fade" id="workflow-tab-files" role="tabpanel" aria-labelledby="workflow-tab-files-tab" tabindex="0">
              <div class="workflow-action-panel">
            <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
              <div>
                <div class="fw-semibold"><?= h(__t('workflow_task_attachments')) ?></div>
                <?php if ($workflowTaskAttachmentsInstalled): ?>
                  <div class="text-muted small"><?= h(__t('workflow_task_attached_file_count', ['count' => count($taskAttachments)])) ?></div>
                <?php endif; ?>
              </div>
              <?php if ($workflowTaskAttachmentsInstalled && $taskAttachments !== []): ?>
                <span class="badge text-bg-light border"><?= count($taskAttachments) ?></span>
              <?php endif; ?>
            </div>

            <?php if (!$workflowTaskAttachmentsInstalled): ?>
              <div class="alert alert-warning py-2 mb-0">
                <?= h(__t('workflow_task_attachments_missing', ['script' => 'backend-php/config/sql/alter_workflow_tasks_add_response_forwarding_activity.sql'])) ?>
              </div>
            <?php else: ?>
              <?php if ($canUploadTaskAttachment): ?>
                <form method="post"
                      action="index.php?route=workflow/upload-attachment"
                      enctype="multipart/form-data"
                      class="needs-validation mb-3"
                      novalidate>
                  <?php if (function_exists('csrf_field')): ?>
                    <?= csrf_field(); ?>
                  <?php endif; ?>
                  <?= wf_form_render_context_inputs() ?>
                  <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
                  <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                  <input type="hidden" name="q" value="<?= h($q) ?>">
                  <input type="hidden" name="page" value="<?= h((string)$page) ?>">
                  <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
                  <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
                  <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
                  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                  <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
                  <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                  <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>

                  <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg">
                      <label class="form-label"><?= h(__t('workflow_task_attach_file')) ?></label>
                      <input type="file"
                             name="TaskAttachment"
                             class="form-control"
                             required
                             accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.png,.jpg,.jpeg,.gif,.zip">
                      <div class="invalid-feedback"><?= h(__t('workflow_task_choose_file')) ?></div>
                    </div>
                    <div class="col-12 col-lg-auto">
                      <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                        <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                        <i class="bi bi-paperclip me-1"></i><?= h(__t('workflow_task_upload')) ?>
                      </button>
                    </div>
                  </div>
                  <div class="form-text"><?= h(__t('workflow_task_allowed_file_types_help')) ?></div>
                </form>
              <?php endif; ?>

              <?php if ($taskAttachments !== []): ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th scope="col"><?= h(__t('workflow_task_file')) ?></th>
                        <th scope="col"><?= h(__t('workflow_task_uploaded')) ?></th>
                        <th scope="col" class="text-end"><?= h(__t('workflow_task_actions')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($taskAttachments as $attachment): ?>
                        <?php
                          $attachmentId = (int)($attachment['WorkflowTaskAttachmentID'] ?? 0);
                          if ($attachmentId <= 0) {
                              continue;
                          }
                          $fileName = trim((string)($attachment['OriginalFileName'] ?? 'attachment'));
                          $uploadedBy = trim((string)($attachment['UploadedByName'] ?? ''));
                          $uploadedAt = wf_form_format_datetime((string)($attachment['UploadedAt'] ?? ''));
                          $downloadUrl = wf_form_build_query([
                              'route' => 'workflow/download-attachment',
                              'id' => (string)$attachmentId,
                          ]);
                          $canDeleteAttachment = $canAdminWorkflow
                              || (int)($task['CreatedByUserID'] ?? 0) === $currentUserId
                              || (int)($attachment['UploadedByUserID'] ?? 0) === $currentUserId;
                        ?>
                        <tr>
                          <td>
                            <div class="d-flex align-items-start gap-2">
                              <i class="bi bi-file-earmark-text text-secondary mt-1"></i>
                              <div>
                                <div class="workflow-attachment-name"><?= h($fileName !== '' ? $fileName : 'attachment') ?></div>
                                <div class="workflow-attachment-meta"><?= h(wf_form_format_file_size($attachment['FileSizeBytes'] ?? 0)) ?></div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <div class="small"><?= h($uploadedBy !== '' ? $uploadedBy : '-') ?></div>
                            <div class="workflow-attachment-meta"><?= h($uploadedAt !== '' ? $uploadedAt : '-') ?></div>
                          </td>
                          <td class="text-end">
                            <div class="d-inline-flex gap-1">
                              <a class="btn btn-sm btn-outline-secondary" href="<?= h($downloadUrl) ?>">
                                <i class="bi bi-download me-1"></i><?= h(__t('workflow_task_download')) ?>
                              </a>
                              <?php if ($canDeleteAttachment): ?>
                                <form method="post"
                                      action="index.php?route=workflow/delete-attachment"
                                      class="needs-validation d-inline"
                                      novalidate
                                      data-confirm-message="<?= h(__t('workflow_task_remove_attachment_confirm')) ?>"
                                      data-confirm-button="<?= h(__t('workflow_task_remove')) ?>"
                                      data-confirm-button-class="btn-danger">
                                  <?php if (function_exists('csrf_field')): ?>
                                    <?= csrf_field(); ?>
                                  <?php endif; ?>
                                  <?= wf_form_render_context_inputs() ?>
                                  <input type="hidden" name="WorkflowTaskAttachmentID" value="<?= h((string)$attachmentId) ?>">
                                  <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                                  <input type="hidden" name="q" value="<?= h($q) ?>">
                                  <input type="hidden" name="page" value="<?= h((string)$page) ?>">
                                  <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
                                  <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
                                  <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
                                  <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                                  <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
                                  <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                                  <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>
                                  <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                    <i class="bi bi-trash me-1"></i><?= h(__t('workflow_task_remove')) ?>
                                  </button>
                                </form>
                              <?php endif; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-muted small mb-0"><?= h(__t('workflow_task_no_attachments')) ?></p>
              <?php endif; ?>
            <?php endif; ?>
              </div>
            </div>

            <div class="tab-pane fade" id="workflow-tab-linked" role="tabpanel" aria-labelledby="workflow-tab-linked-tab" tabindex="0">
              <div class="workflow-action-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">
                  <div>
                    <div class="fw-semibold"><?= h(__t('workflow_task_linked_work')) ?></div>
                    <div class="text-muted small"><?= h(__t('workflow_task_linked_work_help')) ?></div>
                  </div>
                  <?php if ($workflowLinksInstalled && $taskLinks !== []): ?>
                    <span class="badge text-bg-light border"><?= count($taskLinks) ?></span>
                  <?php endif; ?>
                </div>

                <?php if (!$workflowLinksInstalled): ?>
                  <div class="alert alert-warning py-2 mb-0">
                    <?= h(__t('workflow_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql'])) ?>
                  </div>
                <?php else: ?>
                  <?php if ($canManageTaskDetails): ?>
                    <form method="post" action="index.php?route=workflow/save-link" class="needs-validation mb-3" novalidate>
                      <?php if (function_exists('csrf_field')): ?>
                        <?= csrf_field(); ?>
                      <?php endif; ?>
                      <?= wf_form_render_context_inputs() ?>
                      <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
                      <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                      <input type="hidden" name="q" value="<?= h($q) ?>">
                      <input type="hidden" name="page" value="<?= h((string)$page) ?>">
                      <input type="hidden" name="pageSize" value="<?= h((string)$pageSize) ?>">
                      <?php if ($typeID !== null): ?><input type="hidden" name="typeID" value="<?= h((string)$typeID) ?>"><?php endif; ?>
                      <?php if ($statusID !== null): ?><input type="hidden" name="statusID" value="<?= h((string)$statusID) ?>"><?php endif; ?>
                      <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= h($status) ?>"><?php endif; ?>
                      <?php if ($mine !== null): ?><input type="hidden" name="mine" value="<?= h((string)$mine) ?>"><?php endif; ?>
                      <?php if ($mine !== null): ?><input type="hidden" name="task_scope" value="<?= h($taskScope) ?>"><?php endif; ?>
                      <?php if ($assignedToUserIDFilter !== null): ?><input type="hidden" name="assignedToUserID" value="<?= h((string)$assignedToUserIDFilter) ?>"><?php endif; ?>

                      <div class="row g-2 align-items-end">
                        <div class="col-12 col-lg-3">
                          <label class="form-label" for="WorkflowLinkTypeCode"><?= h(__t('workflow_link_type')) ?></label>
                          <select class="form-select" id="WorkflowLinkTypeCode" name="LinkTypeCode" required>
                            <?php foreach ($workflowLinkTypeOptions as $code => $labelKey): ?>
                              <option value="<?= h((string)$code) ?>"><?= h(__t((string)$labelKey)) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="col-12 col-lg-3">
                          <label class="form-label" for="WorkflowLinkedEntity"><?= h(__t('workflow_link_entity')) ?></label>
                          <input type="text" class="form-control" id="WorkflowLinkedEntity" name="LinkedEntity" maxlength="100" required>
                          <div class="invalid-feedback"><?= h(__t('required_field')) ?></div>
                        </div>
                        <div class="col-12 col-lg-2">
                          <label class="form-label" for="WorkflowLinkedEntityID"><?= h(__t('workflow_link_entity_id')) ?></label>
                          <input type="number" min="1" step="1" class="form-control" id="WorkflowLinkedEntityID" name="LinkedEntityID">
                        </div>
                        <div class="col-12 col-lg-4">
                          <label class="form-label" for="WorkflowLinkedEntityKey"><?= h(__t('workflow_link_entity_key')) ?></label>
                          <input type="text" class="form-control" id="WorkflowLinkedEntityKey" name="LinkedEntityKey" maxlength="255">
                        </div>
                        <div class="col-12 col-lg-5">
                          <label class="form-label" for="WorkflowLinkedTitle"><?= h(__t('workflow_link_title')) ?></label>
                          <input type="text" class="form-control" id="WorkflowLinkedTitle" name="LinkedTitle" maxlength="255">
                        </div>
                        <div class="col-12 col-lg-5">
                          <label class="form-label" for="WorkflowLinkedUrl"><?= h(__t('workflow_link_url')) ?></label>
                          <input type="text" class="form-control" id="WorkflowLinkedUrl" name="LinkedUrl" maxlength="1000">
                        </div>
                        <div class="col-12 col-lg-2">
                          <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                            <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                            <i class="bi bi-link-45deg me-1"></i><?= h(__t('workflow_link_add')) ?>
                          </button>
                        </div>
                        <div class="col-12">
                          <label class="form-label" for="WorkflowLinkNotes"><?= h(__t('workflow_link_notes')) ?></label>
                          <textarea class="form-control" id="WorkflowLinkNotes" name="Notes" rows="2"></textarea>
                        </div>
                      </div>
                    </form>
                  <?php endif; ?>

                  <?php if ($taskLinks === []): ?>
                    <p class="text-muted small mb-0"><?= h(__t('workflow_task_no_linked_work')) ?></p>
                  <?php else: ?>
                    <div class="d-grid gap-2">
                      <?php foreach ($taskLinks as $link): ?>
                        <?php
                          $workflowLinkID = (int)($link['WorkflowLinkID'] ?? 0);
                          if ($workflowLinkID <= 0) {
                              continue;
                          }
                          $linkTypeLabel = wf_form_link_type_label((string)($link['LinkTypeCode'] ?? ''), $workflowLinkTypeOptions);
                          $linkedTitle = trim((string)($link['LinkedTitle'] ?? ''));
                          $linkedEntity = trim((string)($link['LinkedEntity'] ?? ''));
                          $linkedKey = trim((string)($link['LinkedEntityKey'] ?? ''));
                          $linkedUrl = trim((string)($link['LinkedUrl'] ?? ''));
                          $linkedId = (int)($link['LinkedEntityID'] ?? 0);
                          $linkHeading = $linkedTitle !== '' ? $linkedTitle : ($linkedKey !== '' ? $linkedKey : ($linkedEntity !== '' ? $linkedEntity : __t('workflow_task_linked_work')));
                        ?>
                        <div class="workflow-link-item">
                          <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                              <div class="workflow-link-title">
                                <?php if ($linkedUrl !== ''): ?>
                                  <a href="<?= h($linkedUrl) ?>"><?= h($linkHeading) ?></a>
                                <?php else: ?>
                                  <?= h($linkHeading) ?>
                                <?php endif; ?>
                              </div>
                              <div class="workflow-link-meta">
                                <span class="badge text-bg-light border me-1"><?= h($linkTypeLabel) ?></span>
                                <?= h($linkedEntity !== '' ? $linkedEntity : '-') ?>
                                <?php if ($linkedId > 0): ?> #<?= h((string)$linkedId) ?><?php endif; ?>
                                <?php if ($linkedKey !== ''): ?> &middot; <?= h($linkedKey) ?><?php endif; ?>
                              </div>
                              <?php if (!empty($link['Notes'])): ?>
                                <div class="small mt-2"><?= nl2br(h((string)$link['Notes'])) ?></div>
                              <?php endif; ?>
                              <div class="workflow-link-meta mt-1">
                                <?= h(__t('workflow_link_added_by_at', [
                                    'user' => (string)($link['CreatedByName'] ?? '-'),
                                    'time' => wf_form_format_datetime((string)($link['CreatedAt'] ?? '')),
                                ])) ?>
                              </div>
                            </div>
                            <?php if ($canManageTaskDetails): ?>
                              <form method="post"
                                    action="index.php?route=workflow/delete-link"
                                    class="needs-validation"
                                    novalidate
                                    data-confirm-message="<?= h(__t('workflow_link_remove_confirm')) ?>"
                                    data-confirm-button="<?= h(__t('workflow_task_remove')) ?>"
                                    data-confirm-button-class="btn-danger">
                                <?php if (function_exists('csrf_field')): ?>
                                  <?= csrf_field(); ?>
                                <?php endif; ?>
                                <?= wf_form_render_context_inputs() ?>
                                <input type="hidden" name="WorkflowLinkID" value="<?= h((string)$workflowLinkID) ?>">
                                <input type="hidden" name="WorkflowTaskID" value="<?= h((string)$id) ?>">
                                <?php if ($isIframe): ?><input type="hidden" name="iframe" value="1"><?php endif; ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                  <span class="spinner-border spinner-border-sm me-1 d-none" role="status" aria-hidden="true"></span>
                                  <i class="bi bi-trash me-1"></i><?= h(__t('workflow_task_remove')) ?>
                                </button>
                              </form>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($workflowTaskViewsInstalled): ?>
              <?php if (!$isRecipientSimpleView): ?>
              <div class="tab-pane fade" id="workflow-tab-views" role="tabpanel" aria-labelledby="workflow-tab-views-tab" tabindex="0">
                <div class="workflow-action-panel">
              <div class="fw-semibold mb-2"><?= h(__t('workflow_task_view_history')) ?></div>
              <?php if ($taskViews !== []): ?>
                <div class="table-responsive">
                  <table class="table table-sm align-middle mb-0">
                    <thead>
                      <tr>
                        <th scope="col"><?= h(__t('user')) ?></th>
                        <th scope="col"><?= h(__t('workflow_task_first_viewed')) ?></th>
                        <th scope="col"><?= h(__t('workflow_task_last_viewed')) ?></th>
                        <th scope="col" class="text-end"><?= h(__t('workflow_task_views')) ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($taskViews as $viewRow): ?>
                        <tr>
                          <td><?= h((string)($viewRow['ViewedByName'] ?? '')) ?></td>
                          <td><?= h(wf_form_format_datetime((string)($viewRow['FirstViewedAt'] ?? ''))) ?></td>
                          <td><?= h(wf_form_format_datetime((string)($viewRow['LastViewedAt'] ?? ''))) ?></td>
                          <td class="text-end"><?= (int)($viewRow['ViewCount'] ?? 0) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-muted small mb-0"><?= h(__t('workflow_task_no_views')) ?></p>
              <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>
            <?php endif; ?>

            <?php if (!$isRecipientSimpleView): ?>
            <div class="tab-pane fade" id="workflow-tab-activity" role="tabpanel" aria-labelledby="workflow-tab-activity-tab" tabindex="0">
              <div class="workflow-action-panel">
            <div class="fw-semibold mb-2"><?= h(__t('workflow_task_activity_history')) ?></div>
            <?php if ($taskActivity !== []): ?>
              <?php foreach ($taskActivity as $activity): ?>
                <div class="workflow-activity-item">
                  <div class="workflow-activity-label"><?= h(wf_form_activity_label((string)($activity['ActivityType'] ?? ''))) ?></div>
                  <div class="small">
                    <?= h((string)($activity['ActionByName'] ?? '')) ?>
                    <span class="text-muted"><?= h(__t('workflow_task_activity_at', ['time' => wf_form_format_datetime((string)($activity['ActionAt'] ?? ''))])) ?></span>
                  </div>
                  <?php if (!empty($activity['FromUserName']) || !empty($activity['ToUserName'])): ?>
                    <div class="text-muted small">
                      <?= h((string)($activity['FromUserName'] ?? '')) ?>
                      <?php if (!empty($activity['ToUserName'])): ?> &rarr; <?= h((string)$activity['ToUserName']) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($activity['FromStatusName']) || !empty($activity['ToStatusName'])): ?>
                    <div class="text-muted small">
                      <?= h((string)($activity['FromStatusName'] ?? '')) ?>
                      <?php if (!empty($activity['ToStatusName'])): ?> &rarr; <?= h((string)$activity['ToStatusName']) ?><?php endif; ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($activity['ActivityNote'])): ?>
                    <div class="small mt-1"><?= nl2br(h((string)$activity['ActivityNote'])) ?></div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted small mb-0"><?= h(__t('workflow_task_no_activity')) ?></p>
            <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
</div>

<!-- Bootstrap validation (consistent across forms) -->
<script>
(() => {
  'use strict';
  const richTextMessages = {
    linkPrompt: <?= json_encode(__t('workflow_task_description_link_prompt'), JSON_UNESCAPED_SLASHES) ?>,
  };
  const allowedRichTextTags = new Set(['a', 'b', 'blockquote', 'br', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'i', 'li', 'ol', 'p', 'pre', 's', 'strong', 'u', 'ul']);
  const safeRichTextUrl = value => {
    const url = String(value || '').trim();
    if (!url) return '';
    const lower = url.toLowerCase();
    if (lower.startsWith('javascript:') || lower.startsWith('data:') || lower.startsWith('vbscript:')) {
      return '';
    }
    if (/^[a-z][a-z0-9+.-]*:/i.test(url) && !/^(https?|mailto|tel):/i.test(url)) {
      return '';
    }
    return url.slice(0, 1000);
  };
  const sanitizeRichTextNode = node => {
    Array.from(node.childNodes).forEach(child => {
      if (child.nodeType === Node.TEXT_NODE) {
        return;
      }
      if (child.nodeType !== Node.ELEMENT_NODE) {
        child.remove();
        return;
      }

      const tag = child.tagName.toLowerCase();
      sanitizeRichTextNode(child);
      if (!allowedRichTextTags.has(tag)) {
        while (child.firstChild) {
          node.insertBefore(child.firstChild, child);
        }
        child.remove();
        return;
      }

      const href = tag === 'a' ? safeRichTextUrl(child.getAttribute('href') || '') : '';
      Array.from(child.attributes).forEach(attribute => child.removeAttribute(attribute.name));
      if (tag === 'a' && href) {
        child.setAttribute('href', href);
        child.setAttribute('target', '_blank');
        child.setAttribute('rel', 'noopener noreferrer');
      }
    });
  };
  const sanitizeRichTextHtml = html => {
    const parser = new DOMParser();
    const documentFragment = parser.parseFromString(`<div>${String(html || '')}</div>`, 'text/html');
    const root = documentFragment.body.firstElementChild;
    if (!root) {
      return '';
    }
    sanitizeRichTextNode(root);
    const output = root.innerHTML.trim();
    return richTextPlainText(output) ? output : '';
  };
  const escapeRichText = value => {
    const div = document.createElement('div');
    div.textContent = String(value || '');
    return div.innerHTML;
  };
  const plainTextToRichTextHtml = value => {
    const text = String(value || '').replace(/\r\n?/g, '\n').trim();
    if (!text) return '';
    return text
      .split(/\n{2,}/)
      .map(paragraph => `<p>${paragraph.split('\n').map(line => escapeRichText(line)).join('<br>')}</p>`)
      .join('');
  };
  const richTextPlainText = html => {
    const parser = new DOMParser();
    const documentFragment = parser.parseFromString(String(html || ''), 'text/html');
    return (documentFragment.body.textContent || '').replace(/\u00a0/g, ' ').trim();
  };
  const insertRichTextHtml = html => {
    document.execCommand('insertHTML', false, sanitizeRichTextHtml(html));
  };
  const setupWorkflowRichTextEditors = () => {
    document.querySelectorAll('[data-workflow-rich-text]').forEach(container => {
      const source = container.querySelector('.workflow-rich-text-source');
      const editor = container.querySelector('[data-workflow-rich-editor]');
      if (!source || !editor || source.disabled) {
        return;
      }

      container.classList.add('is-enhanced');
      container.dataset.workflowRichRequired = source.required ? '1' : '0';
      source.required = false;
      source.setAttribute('aria-hidden', 'true');
      editor.innerHTML = sanitizeRichTextHtml(editor.innerHTML || plainTextToRichTextHtml(source.value));
      source.value = editor.innerHTML;

      container.querySelectorAll('[data-workflow-rich-command]').forEach(button => {
        button.addEventListener('click', () => {
          editor.focus();
          const command = button.getAttribute('data-workflow-rich-command') || '';
          if (command === 'createLink') {
            const url = safeRichTextUrl(window.prompt(richTextMessages.linkPrompt || 'Enter the link URL') || '');
            if (url) {
              document.execCommand('createLink', false, url);
            }
          } else if (command === 'removeFormat') {
            document.execCommand('removeFormat', false, null);
            document.execCommand('unlink', false, null);
          } else if (command) {
            document.execCommand(command, false, null);
          }
          editor.innerHTML = sanitizeRichTextHtml(editor.innerHTML);
          source.value = editor.innerHTML;
        });
      });

      editor.addEventListener('paste', event => {
        event.preventDefault();
        const clipboard = event.clipboardData || window.clipboardData;
        const html = clipboard ? clipboard.getData('text/html') : '';
        const text = clipboard ? clipboard.getData('text/plain') : '';
        insertRichTextHtml(html || plainTextToRichTextHtml(text));
        source.value = sanitizeRichTextHtml(editor.innerHTML);
      });

      editor.addEventListener('input', () => {
        source.value = sanitizeRichTextHtml(editor.innerHTML);
        container.classList.remove('is-invalid');
        editor.classList.remove('is-invalid');
      });
      editor.addEventListener('blur', () => {
        editor.innerHTML = sanitizeRichTextHtml(editor.innerHTML);
        source.value = editor.innerHTML;
      });
    });
  };
  const syncWorkflowRichTextEditors = form => {
    let valid = true;
    form.querySelectorAll('[data-workflow-rich-text].is-enhanced').forEach(container => {
      const source = container.querySelector('.workflow-rich-text-source');
      const editor = container.querySelector('[data-workflow-rich-editor]');
      if (!source || !editor || source.disabled) {
        return;
      }
      editor.innerHTML = sanitizeRichTextHtml(editor.innerHTML);
      source.value = editor.innerHTML;
      const required = container.dataset.workflowRichRequired === '1';
      const empty = required && !richTextPlainText(source.value);
      container.classList.toggle('is-invalid', empty);
      editor.classList.toggle('is-invalid', empty);
      if (empty) {
        valid = false;
      }
    });
    return valid;
  };
  setupWorkflowRichTextEditors();

  const projectSelect = document.getElementById('WorkflowProjectID');
  const taskTypeSelect = document.getElementById('TaskTypeID');
  const projectTaskTypeHelp = document.querySelector('[data-project-task-type-help]');
  const workflowGroupsPanel = document.querySelector('[data-workflow-groups-panel]');
  const workflowGroupsSelect = document.querySelector('[data-workflow-groups]');
  const dueDateInput = document.getElementById('DueDate');
  const plannedStartInput = document.getElementById('PlannedStartDate');
  const plannedEndInput = document.getElementById('PlannedEndDate');
  const projectBoundedDateInputs = [dueDateInput, plannedStartInput, plannedEndInput].filter(input => input);
  const plannedDatesInvalidMessage = <?= json_encode(__t('workflow_task_planned_dates_invalid'), JSON_UNESCAPED_SLASHES) ?>;
  const taskTypeInitiallyDisabled = taskTypeSelect ? taskTypeSelect.disabled : false;
  const workflowGroupsInitiallyDisabled = workflowGroupsSelect ? workflowGroupsSelect.disabled : false;
  const projectTaskTypeID = taskTypeSelect ? parseInt(taskTypeSelect.getAttribute('data-project-task-type-id') || '0', 10) : 0;
  const getProjectValue = () => projectSelect ? projectSelect.value : '';
  const getProjectAttribute = attr => {
    if (!projectSelect) {
      return '';
    }
    if (projectSelect.tagName === 'SELECT') {
      const selectedOption = projectSelect.options[projectSelect.selectedIndex];
      return selectedOption ? (selectedOption.getAttribute(attr) || '') : '';
    }
    return projectSelect.getAttribute(attr) || '';
  };
  const syncProjectTaskType = () => {
    const hasProject = getProjectValue() !== '';
    const projectStartDate = hasProject ? getProjectAttribute('data-project-start') : '';
    const projectEndDate = hasProject ? getProjectAttribute('data-project-end') : '';
    const lockProjectTaskType = !!hasProject && projectTaskTypeID > 0;
    if (lockProjectTaskType && taskTypeSelect) {
      taskTypeSelect.value = String(projectTaskTypeID);
    }
    if (taskTypeSelect && taskTypeSelect.tagName === 'SELECT') {
      taskTypeSelect.disabled = taskTypeInitiallyDisabled || lockProjectTaskType;
    }
    if (projectTaskTypeHelp) {
      projectTaskTypeHelp.classList.toggle('d-none', !hasProject);
    }
    projectBoundedDateInputs.forEach(input => {
      if (hasProject && projectStartDate !== '') {
        input.setAttribute('min', projectStartDate);
      } else {
        input.removeAttribute('min');
      }
      if (hasProject && projectEndDate !== '') {
        input.setAttribute('max', projectEndDate);
      } else {
        input.removeAttribute('max');
      }
    });
    syncPlannedDateConstraints(projectStartDate);
    if (workflowGroupsPanel) {
      workflowGroupsPanel.classList.toggle('d-none', !!hasProject);
    }
    if (workflowGroupsSelect) {
      workflowGroupsSelect.disabled = workflowGroupsInitiallyDisabled || !!hasProject;
      if (hasProject) {
        Array.from(workflowGroupsSelect.options || []).forEach(option => {
          option.selected = false;
        });
      }
    }
  };
  const syncPlannedDateConstraints = (projectStartDate = '') => {
    if (!plannedEndInput) {
      return;
    }

    const plannedStartDate = plannedStartInput ? plannedStartInput.value : '';
    const minDate = [projectStartDate, plannedStartDate]
      .filter(value => value !== '')
      .sort()
      .pop() || '';

    if (minDate !== '') {
      plannedEndInput.setAttribute('min', minDate);
    } else {
      plannedEndInput.removeAttribute('min');
    }

    const invalidRange = plannedStartDate !== ''
      && plannedEndInput.value !== ''
      && plannedEndInput.value < plannedStartDate;
    plannedEndInput.setCustomValidity(invalidRange ? plannedDatesInvalidMessage : '');
  };
  if (projectSelect && projectSelect.tagName === 'SELECT') {
    projectSelect.addEventListener('change', syncProjectTaskType);
  }
  [plannedStartInput, plannedEndInput].forEach(input => {
    if (input) {
      input.addEventListener('input', syncProjectTaskType);
      input.addEventListener('change', syncProjectTaskType);
    }
  });
  syncProjectTaskType();

  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', e => {
      syncProjectTaskType();
      syncPlannedDateConstraints(getProjectValue() !== '' ? getProjectAttribute('data-project-start') : '');
      if (!syncWorkflowRichTextEditors(form)) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        return;
      }

      const transitionValue = e.submitter ? e.submitter.getAttribute('data-transition-value') : '';
      if (transitionValue) {
        const transitionField = form.querySelector('[data-transition-field]');
        if (transitionField) {
          transitionField.value = transitionValue;
        }

        if (transitionValue === 'complete') {
          const responseField = form.querySelector('[data-transition-response-field]');
          const responseTextarea = document.querySelector('textarea[name="RecipientResponse"]');
          if (responseField && responseTextarea) {
            responseField.value = responseTextarea.value;
          }
        }
      }

      const assignees = form.querySelector('[data-workflow-assignees]');
      if (assignees) {
        const groups = form.querySelector('[data-workflow-groups]');
        const hasProject = getProjectValue() !== '';
        const hasAssignee = Array.from(assignees.selectedOptions || []).some(option => option.value !== '');
        const hasGroup = !hasProject && groups && !groups.disabled
          ? Array.from(groups.selectedOptions || []).some(option => option.value !== '')
          : false;
        assignees.setCustomValidity(hasAssignee || hasGroup ? '' : (hasProject ? <?= json_encode(__t('assigned_to_required'), JSON_UNESCAPED_SLASHES) ?> : <?= json_encode(__t('workflow_task_select_recipient_or_group'), JSON_UNESCAPED_SLASHES) ?>));
      }

      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        return;
      }

      const submitter = e.submitter || form.querySelector('button[type="submit"]');
      if (submitter) {
        const spinner = submitter.querySelector('.spinner-border');
        const icon = submitter.querySelector('.bi');
        if (spinner) spinner.classList.remove('d-none');
        if (icon) icon.classList.add('d-none');
        submitter.setAttribute('aria-busy', 'true');
      }

      form.querySelectorAll('button[type="submit"]').forEach(button => {
        button.disabled = true;
      });

      form.classList.add('was-validated');
    }, false);
  });
})();
</script>

<?php if ($isIframe): ?>
</div>
</body>
</html>
<?php endif; ?>
