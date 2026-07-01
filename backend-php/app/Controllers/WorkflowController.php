<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditModel;
use App\Models\EmailTemplateModel;
use App\Models\SystemSettingsModel;
use App\Models\UserModel;
use App\Models\WorkflowLinkModel;
use App\Models\WorkflowProjectModel;
use App\Models\WorkflowRequirementModel;
use App\Models\WorkflowTaskModel;
use App\Models\WorkflowTaskStatusModel;
use App\Models\WorkflowTaskTypeModel;
use App\Models\WorkflowUserGroupModel;
use App\Services\MailService;
use App\Services\WorkflowTaskReminderService;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/workflow_helpers.php';

final class WorkflowController extends BaseController
{
    private const MANUAL_REMINDER_COOLDOWN_SECONDS = 60;

    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'exportExcel' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'edit' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'transition' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'respond' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'forward' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'send-reminder' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-link' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'delete-link' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-comment' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'delete-comment' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'upload-attachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'download-attachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'delete-attachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'delete' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function list(): void
    {
        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $typesModel = new WorkflowTaskTypeModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $userModel = new UserModel($conn);
        $projectModel = new WorkflowProjectModel($conn);

        $userID = (int) SessionHelper::get('auth.user_id', 0);
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $canAdmin = $this->canAdminWorkflowTasks($perms);
        $canEdit = $canAdmin || in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true);
        $canView = $canEdit || in_array('WORKFLOW_OPERATIONS_VIEW', $perms, true);

        $q = trim((string) ($_GET['q'] ?? ''));
        $typeID = ($_GET['typeID'] ?? '') !== '' ? (int) $_GET['typeID'] : null;
        $statusID = ($_GET['statusID'] ?? '') !== '' ? (int) $_GET['statusID'] : null;
        $workflowProjectContextID = $this->workflowProjectFilterFromRequest();
        $workflowProjectID = $workflowProjectContextID > 0 ? $workflowProjectContextID : null;
        $statusFlag = strtolower(trim((string) ($_GET['status'] ?? '')));
        if (!in_array($statusFlag, ['open', 'closed'], true)) {
            $statusFlag = '';
        }
        $dueState = strtolower(trim((string) ($_GET['due_state'] ?? '')));
        if (!in_array($dueState, ['overdue', 'today', 'soon'], true)) {
            $dueState = '';
        }
        if ($dueState !== '') {
            $statusFlag = 'open';
        }
        $onlyOpen = ($statusFlag === 'open');
        $onlyClosed = ($statusFlag === 'closed');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($_GET['pageSize'] ?? 10)));
        $isIframe = !empty($_GET['iframe']);

        $mineRequested = ($_GET['mine'] ?? '') !== '' ? (int) $_GET['mine'] === 1 : !$canAdmin || $isIframe || $onlyOpen;
        $taskScope = strtolower(trim((string) ($_GET['task_scope'] ?? 'received')));
        if (!in_array($taskScope, ['received', 'created'], true)) {
            $taskScope = 'received';
        }
        $assignedToID = null;
        $createdByID = null;
        if ($mineRequested || !$canAdmin) {
            $mineRequested = true;
            if ($taskScope === 'created') {
                $createdByID = $userID;
            } else {
                $taskScope = 'received';
                $assignedToID = $userID;
            }
        } elseif (($_GET['assignedToUserID'] ?? '') !== '') {
            $assignedToID = (int) $_GET['assignedToUserID'];
        }

        if ($assignedToID === null && $createdByID === null && !$canAdmin) {
            $assignedToID = $userID;
        }

        $res = $tasksModel->listFiltered($assignedToID, $page, $pageSize, $q, $typeID, $statusID, $onlyOpen, $createdByID, $onlyClosed, $dueState, $workflowProjectID);
        $summary = $tasksModel->summarizeFiltered($assignedToID, $q, $typeID, $statusID, $createdByID, $workflowProjectID);
        $tasks = $res['items'] ?? [];
        $total = (int) ($res['total'] ?? 0);
        $totalPages = (int) max(1, ceil(($total ?: 0) / ($pageSize ?: 1)));

        $params = [
            'title' => __t('workflow_tasks'),
            'tasks' => $tasks,
            'total' => $total,
            'totalPages' => $totalPages,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
            'typeID' => $typeID,
            'statusID' => $statusID,
            'statuses' => $statusesModel->listActive(),
            'types' => $typesModel->listActive(),
            'users' => $canAdmin ? $userModel->listAll() : [],
            'assignedToUserID' => $assignedToID,
            'workflowProjectID' => $workflowProjectID,
            'workflowProjects' => $projectModel->supportsWorkflowProjects() ? $projectModel->listActiveProjects() : [],
            'workflowProjectsInstalled' => $projectModel->supportsWorkflowProjects(),
            'showAdminScope' => $canAdmin,
            'mine' => $mineRequested,
            'canEditWorkflow' => $canEdit,
            'canViewWorkflow' => $canView,
            'canAdminWorkflow' => $canAdmin,
            'currentUserId' => $userID,
            'taskScope' => $mineRequested ? $taskScope : 'all',
            'dueState' => $dueState,
            'summary' => $summary,
            'flash' => SessionHelper::get('flash.message', null),
        ];

        if ($isIframe) {
            $this->renderPartial('workflow/WorkflowList', $params);
        } else {
            $this->render('workflow/WorkflowList', $params);
        }

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function exportExcel(): void
    {
        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $userID = (int) SessionHelper::get('auth.user_id', 0);
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $canAdmin = $this->canAdminWorkflowTasks($perms);

        $q = trim((string) ($_GET['q'] ?? ''));
        $typeID = ($_GET['typeID'] ?? '') !== '' ? (int) $_GET['typeID'] : null;
        $statusID = ($_GET['statusID'] ?? '') !== '' ? (int) $_GET['statusID'] : null;
        $workflowProjectContextID = $this->workflowProjectFilterFromRequest();
        $workflowProjectID = $workflowProjectContextID > 0 ? $workflowProjectContextID : null;
        $statusFlag = strtolower(trim((string) ($_GET['status'] ?? '')));
        if (!in_array($statusFlag, ['open', 'closed'], true)) {
            $statusFlag = '';
        }
        $dueState = strtolower(trim((string) ($_GET['due_state'] ?? '')));
        if (!in_array($dueState, ['overdue', 'today', 'soon'], true)) {
            $dueState = '';
        }
        if ($dueState !== '') {
            $statusFlag = 'open';
        }
        $onlyOpen = ($statusFlag === 'open');
        $onlyClosed = ($statusFlag === 'closed');
        $isIframe = !empty($_GET['iframe']);

        $mineRequested = ($_GET['mine'] ?? '') !== '' ? (int) $_GET['mine'] === 1 : !$canAdmin || $isIframe || $onlyOpen;
        $taskScope = strtolower(trim((string) ($_GET['task_scope'] ?? 'received')));
        if (!in_array($taskScope, ['received', 'created'], true)) {
            $taskScope = 'received';
        }
        $assignedToID = null;
        $createdByID = null;
        if ($mineRequested || !$canAdmin) {
            $mineRequested = true;
            if ($taskScope === 'created') {
                $createdByID = $userID;
            } else {
                $assignedToID = $userID;
            }
        } elseif (($_GET['assignedToUserID'] ?? '') !== '') {
            $assignedToID = (int) $_GET['assignedToUserID'];
        }
        if ($assignedToID === null && $createdByID === null && !$canAdmin) {
            $assignedToID = $userID;
        }

        $result = $tasksModel->listFiltered($assignedToID, 1, 5000, $q, $typeID, $statusID, $onlyOpen, $createdByID, $onlyClosed, $dueState, $workflowProjectID);
        $rows = is_array($result['items'] ?? null) ? $result['items'] : [];

        $this->downloadExcel('Workflow Tasks', 'WorkflowTasks', [
            ['label' => 'Task ID', 'key' => 'WorkflowTaskID'],
            ['label' => __t('workflow_task_task'), 'key' => 'Title'],
            ['label' => 'Project', 'value' => static fn(array $row): string => trim((string)($row['ProjectCode'] ?? '') . ' ' . (string)($row['ProjectName'] ?? ''))],
            ['label' => __t('workflow_task_priority'), 'key' => 'PriorityCode'],
            ['label' => __t('status'), 'value' => static fn(array $row): string => (string)($row['StatusName'] ?? $row['StatusCode'] ?? '')],
            ['label' => 'Task Type', 'key' => 'TaskTypeName'],
            ['label' => __t('workflow_task_assigned_to'), 'key' => 'AssignedToName'],
            ['label' => __t('workflow_task_created_by'), 'key' => 'CreatedByName'],
            ['label' => __t('workflow_task_due_date'), 'key' => 'DueDate'],
            ['label' => 'Planned Start', 'key' => 'PlannedStartDate'],
            ['label' => 'Planned End', 'key' => 'PlannedEndDate'],
            ['label' => 'Percent Complete', 'key' => 'PercentComplete'],
            ['label' => __t('workflow_task_completed'), 'key' => 'CompletedAt'],
            ['label' => 'Related Entity', 'key' => 'RelatedEntity'],
            ['label' => 'Related Key', 'key' => 'RelatedKey'],
        ], $rows);
    }

    public function edit(): void
    {
        require __DIR__ . '/../../config/db.php';

        $id = (int) ($_GET['id'] ?? 0);
        $tasksModel = new WorkflowTaskModel($conn);
        $typesModel = new WorkflowTaskTypeModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $userModel = new UserModel($conn);
        $workflowUserGroupModel = new WorkflowUserGroupModel($conn);
        $projectModel = new WorkflowProjectModel($conn);
        $linkModel = new WorkflowLinkModel($conn);
        $requirementModel = new WorkflowRequirementModel($conn);

        $task = $id > 0 ? $tasksModel->find($id) : null;
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int) SessionHelper::get('auth.user_id', 0);
        $canAdmin = $this->canAdminWorkflowTasks($perms);
        $canEdit = $canAdmin || in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true);
        $returnTo = $this->normalizeWorkflowReturnTo((string)($_GET['returnTo'] ?? ''));
        if ($returnTo === '') {
            $returnTo = $this->normalizeWorkflowReturnTo((string)($_SERVER['HTTP_REFERER'] ?? ''));
        }

        if ($id > 0 && !$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($this->workflowRedirectContextFromGet());
        }
        if ($id > 0 && !$this->canAccessWorkflowTask($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_open'));
            $this->redirectToWorkflowList($this->workflowRedirectContextFromGet());
        }
        if ($id <= 0 && !$canEdit) {
            $this->flashError(__t('workflow_task_permission_create'));
            $this->redirectToWorkflowList($this->workflowRedirectContextFromGet());
        }
        $selectedWorkflowProject = null;
        $workflowProjectIDFromContext = $this->workflowProjectIdFromArray($_GET);
        $workflowRequirementIDFromContext = $this->workflowRequirementIdFromArray($_GET);
        if ($id <= 0 && $workflowProjectIDFromContext <= 0 && $workflowRequirementIDFromContext > 0 && $requirementModel->supportsRequirements()) {
            $contextRequirement = $requirementModel->findRequirement($workflowRequirementIDFromContext);
            if ($contextRequirement) {
                $workflowProjectIDFromContext = (int)($contextRequirement['WorkflowProjectID'] ?? 0);
            }
        }
        if ($id <= 0 && $workflowProjectIDFromContext > 0) {
            $selectedWorkflowProject = $projectModel->findProject($workflowProjectIDFromContext);
            if (!$selectedWorkflowProject) {
                $this->flashError(__t('workflow_project_not_found'));
                header('Location: index.php?route=workflow-projects/list');
                exit;
            }
        }
        $viewsInstalled = $tasksModel->supportsWorkflowTaskViews();
        if ($id > 0 && $viewsInstalled) {
            $tasksModel->recordTaskView($id, $currentUserId);
            $task = $tasksModel->find($id) ?: $task;
        }

        $users = method_exists($userModel, 'listAll')
            ? $userModel->listAll()
            : $userModel->all(1, 500, '');

        $canManageTaskDetails = $id <= 0 || $this->canManageWorkflowTaskDetails($task, $perms, $currentUserId);
        $canRespondTask = $id > 0 && $this->canRespondWorkflowTask($task, $perms, $currentUserId);
        $canForwardTask = $id > 0 && $this->canForwardWorkflowTask($task, $perms, $currentUserId);
        $canTransitionTask = $id > 0 && $this->canTransitionWorkflowTask($task, $perms, $currentUserId);
        $canSendTaskReminder = $id > 0 && $this->canSendWorkflowTaskReminder($task, $perms, $currentUserId);
        $attachmentsInstalled = $tasksModel->supportsWorkflowTaskAttachments();
        $canUploadTaskAttachment = $id > 0 && $this->canAccessWorkflowTask($task, $perms, $currentUserId);
        $commentsInstalled = $tasksModel->supportsWorkflowTaskComments();
        $canAddTaskComment = $id > 0 && $this->canAccessWorkflowTask($task, $perms, $currentUserId);
        $workflowProjectIDForOptions = $id > 0
            ? (int)($task['WorkflowProjectID'] ?? 0)
            : $workflowProjectIDFromContext;
        $projectTaskOptions = $workflowProjectIDForOptions > 0
            ? $tasksModel->listProjectTaskOptions($workflowProjectIDForOptions, $id > 0 ? $id : null)
            : [];
        $taskDependencies = $id > 0 ? $tasksModel->listDependencies($id) : [];
        $workflowLinksInstalled = $linkModel->supportsWorkflowLinks();
        $workflowRequirementsInstalled = $requirementModel->supportsRequirements();
        $taskLinks = $id > 0 && $workflowLinksInstalled ? $linkModel->listTaskLinks($id) : [];
        $workflowRequirementOptions = $workflowRequirementsInstalled && $workflowProjectIDForOptions > 0
            ? $requirementModel->listProjectRequirements($workflowProjectIDForOptions)
            : [];
        $selectedWorkflowRequirementID = 0;
        foreach ($taskLinks as $link) {
            if (strtoupper(trim((string)($link['LinkedEntity'] ?? ''))) === 'WORKFLOWREQUIREMENT'
                && strtoupper(trim((string)($link['LinkTypeCode'] ?? ''))) === 'REQUIREMENT'
                && (int)($link['LinkedEntityID'] ?? 0) > 0
            ) {
                $selectedWorkflowRequirementID = (int)$link['LinkedEntityID'];
                break;
            }
        }
        if ($selectedWorkflowRequirementID <= 0
            && strtoupper(trim((string)($task['RelatedEntity'] ?? ''))) === 'WORKFLOWREQUIREMENT'
        ) {
            $relatedKey = trim((string)($task['RelatedKey'] ?? ''));
            foreach ($workflowRequirementOptions as $requirementOption) {
                $optionID = (int)($requirementOption['WorkflowRequirementID'] ?? 0);
                $optionCode = trim((string)($requirementOption['RequirementCode'] ?? ''));
                if ($optionID > 0 && ($relatedKey === (string)$optionID || strcasecmp($relatedKey, $optionCode) === 0)) {
                    $selectedWorkflowRequirementID = $optionID;
                    break;
                }
            }
        }
        if ($id <= 0 && $workflowRequirementsInstalled && $workflowRequirementIDFromContext > 0) {
            $candidateRequirement = $requirementModel->findRequirement($workflowRequirementIDFromContext);
            if ($candidateRequirement && (int)($candidateRequirement['WorkflowProjectID'] ?? 0) === $workflowProjectIDForOptions) {
                $selectedWorkflowRequirementID = $workflowRequirementIDFromContext;
            }
        }
        if ($selectedWorkflowRequirementID > 0) {
            $hasSelectedRequirement = false;
            foreach ($workflowRequirementOptions as $requirementOption) {
                if ((int)($requirementOption['WorkflowRequirementID'] ?? 0) === $selectedWorkflowRequirementID) {
                    $hasSelectedRequirement = true;
                    break;
                }
            }
            if (!$hasSelectedRequirement) {
                $selectedRequirement = $requirementModel->findRequirement($selectedWorkflowRequirementID);
                if ($selectedRequirement && (int)($selectedRequirement['WorkflowProjectID'] ?? 0) === $workflowProjectIDForOptions) {
                    $workflowRequirementOptions[] = $selectedRequirement;
                }
            }
        }

        $params = [
            'title' => $id > 0 ? __t('edit_task') : __t('create_task'),
            'task' => $task,
            'taskActivity' => $id > 0 ? $tasksModel->listActivity($id) : [],
            'taskLinks' => $taskLinks,
            'taskAttachments' => $id > 0 && $attachmentsInstalled ? $tasksModel->listAttachments($id) : [],
            'taskComments' => $id > 0 && $commentsInstalled ? $tasksModel->listComments($id) : [],
            'taskViews' => $id > 0 && $viewsInstalled ? $tasksModel->listTaskViews($id) : [],
            'types' => $typesModel->listActive(),
            'statuses' => $statusesModel->listActive(),
            'defaultStatusID' => $id <= 0 ? (int) ($statusesModel->findOpenStatusId() ?? 0) : 0,
            'users' => $users,
            'projectTaskTypeID' => $typesModel->findIdByCode('PROJECT_TASK'),
            'selectedWorkflowProject' => $selectedWorkflowProject,
            'projectTaskOptions' => $projectTaskOptions,
            'workflowRequirementOptions' => $workflowRequirementOptions,
            'selectedWorkflowRequirementID' => $selectedWorkflowRequirementID,
            'taskDependencies' => $taskDependencies,
            'workflowLinkTypeOptions' => $linkModel->linkTypeOptions(),
            'workflowLinksInstalled' => $workflowLinksInstalled,
            'workflowRequirementsInstalled' => $workflowRequirementsInstalled,
            'workflowTaskDependenciesInstalled' => $tasksModel->supportsWorkflowTaskDependencies(),
            'workflowProjects' => $projectModel->supportsWorkflowProjects() ? $projectModel->listActiveProjects() : [],
            'workflowProjectsInstalled' => $projectModel->supportsWorkflowProjects(),
            'workflowUserGroups' => $id <= 0 ? $workflowUserGroupModel->listGroups('', '1') : [],
            'workflowUserGroupsInstalled' => $workflowUserGroupModel->supportsWorkflowUserGroups(),
            'canManageTaskDetails' => $canManageTaskDetails,
            'canRespondTask' => $canRespondTask,
            'canForwardTask' => $canForwardTask,
            'canTransitionTask' => $canTransitionTask,
            'canSendTaskReminder' => $canSendTaskReminder,
            'canUploadTaskAttachment' => $canUploadTaskAttachment,
            'workflowTaskAttachmentsInstalled' => $attachmentsInstalled,
            'canAddTaskComment' => $canAddTaskComment,
            'workflowTaskCommentsInstalled' => $commentsInstalled,
            'workflowTaskViewsInstalled' => $viewsInstalled,
            'canAdminWorkflow' => $canAdmin,
            'currentUserId' => $currentUserId,
            'returnTo' => $returnTo,
            'flash' => SessionHelper::get('flash.message', null),
        ];

        $isIframe = !empty($_GET['iframe']);
        if ($isIframe) {
            $this->renderPartial('workflow/WorkflowForm', $params);
        } else {
            $this->render('workflow/WorkflowForm', $params);
        }

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function save(): void
    {
        $safeReturnTo = $this->normalizeWorkflowReturnTo((string)($_POST['returnTo'] ?? ''));
        $csrfRedirect = $safeReturnTo !== '' ? $safeReturnTo : 'index.php?route=workflow/list';
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl($csrfRedirect));

        require __DIR__ . '/../../config/db.php';
        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);
        $userModel = new UserModel($conn);
        $settingsModel = new SystemSettingsModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $typesModel = new WorkflowTaskTypeModel($conn);
        $workflowUserGroupModel = new WorkflowUserGroupModel($conn);
        $projectModel = new WorkflowProjectModel($conn);
        $linkModel = new WorkflowLinkModel($conn);
        $requirementModel = new WorkflowRequirementModel($conn);

        $id = (int) ($_POST['WorkflowTaskID'] ?? 0);
        $existingTask = $id > 0 ? $tasksModel->find($id) : null;
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int) SessionHelper::get('auth.user_id', 0);
        $statusID = $id > 0
            ? (int) ($_POST['StatusID'] ?? 0)
            : (int) ($statusesModel->findOpenStatusId() ?? 0);
        $isClosedStatus = $statusID > 0 ? $statusesModel->isClosedStatusId($statusID) : false;
        $postedWorkflowProjectID = ($_POST['WorkflowProjectID'] ?? '') !== '' ? (int) $_POST['WorkflowProjectID'] : null;
        $contextWorkflowProjectID = ($_POST['workflowProjectID'] ?? '') !== '' ? (int) $_POST['workflowProjectID'] : null;
        $workflowProjectID = $id > 0 && $existingTask
            ? (isset($existingTask['WorkflowProjectID']) ? (int)$existingTask['WorkflowProjectID'] : null)
            : $postedWorkflowProjectID;
        if (($workflowProjectID ?? 0) <= 0) {
            $workflowProjectID = null;
        }
        $selectedWorkflowProject = ($workflowProjectID ?? 0) > 0 ? $projectModel->findProject((int)$workflowProjectID) : null;
        $postedWorkflowRequirementID = ($_POST['WorkflowRequirementID'] ?? '') !== '' ? (int)$_POST['WorkflowRequirementID'] : 0;
        $workflowRequirementSelectedForSave = null;
        $existingWorkflowRequirementID = 0;
        $workflowRequirementsInstalled = $requirementModel->supportsRequirements();
        $workflowLinksInstalled = $linkModel->supportsWorkflowLinks();
        $shouldSyncWorkflowRequirement = ($workflowProjectID ?? 0) > 0
            && array_key_exists('WorkflowRequirementID', $_POST)
            && $workflowRequirementsInstalled
            && $workflowLinksInstalled;
        if ($id > 0 && $workflowLinksInstalled) {
            foreach ($linkModel->listTaskLinks($id) as $link) {
                if (strtoupper(trim((string)($link['LinkedEntity'] ?? ''))) === 'WORKFLOWREQUIREMENT'
                    && strtoupper(trim((string)($link['LinkTypeCode'] ?? ''))) === 'REQUIREMENT'
                    && (int)($link['LinkedEntityID'] ?? 0) > 0
                ) {
                    $existingWorkflowRequirementID = (int)$link['LinkedEntityID'];
                    break;
                }
            }
        }
        $parentWorkflowTaskID = ($workflowProjectID ?? 0) > 0 && ($_POST['ParentWorkflowTaskID'] ?? '') !== ''
            ? (int)$_POST['ParentWorkflowTaskID']
            : null;
        if (($parentWorkflowTaskID ?? 0) <= 0) {
            $parentWorkflowTaskID = null;
        }
        $dependencyWorkflowTaskIDs = ($workflowProjectID ?? 0) > 0
            ? $this->normalizeWorkflowTaskIds($_POST['DependsOnWorkflowTaskIDs'] ?? [])
            : [];
        $assignedGroupIds = $id > 0 || ($workflowProjectID ?? 0) > 0
            ? []
            : $this->normalizeWorkflowUserGroupIds($_POST['WorkflowUserGroupIDs'] ?? []);
        $assignedUserIds = $id > 0
            ? $this->normalizeAssignedWorkflowUserIds($_POST['AssignedToUserID'] ?? [])
            : $this->normalizeAssignedWorkflowUserIds($_POST['AssignedToUserIDs'] ?? ($_POST['AssignedToUserID'] ?? []));
        $groupRecipientRows = [];
        $workflowUserGroupsInstalled = $workflowUserGroupModel->supportsWorkflowUserGroups();
        if ($id <= 0 && $assignedGroupIds !== [] && $workflowUserGroupsInstalled) {
            $assignedUserIdMap = [];
            foreach ($assignedUserIds as $userId) {
                if ($userId > 0) {
                    $assignedUserIdMap[$userId] = $userId;
                }
            }
            $groupRecipientRows = $workflowUserGroupModel->listActiveMembersForGroups($assignedGroupIds);
            foreach ($groupRecipientRows as $row) {
                $userId = (int)($row['UserID'] ?? 0);
                if ($userId > 0) {
                    $assignedUserIdMap[$userId] = $userId;
                }
            }
            $assignedUserIds = array_values($assignedUserIdMap);
        }
        $completionRule = $id <= 0
            ? $this->normalizeWorkflowTaskCompletionRule($_POST['WorkflowTaskCompletionRule'] ?? 'INDIVIDUAL')
            : (string)($existingTask['WorkflowTaskCompletionRule'] ?? 'INDIVIDUAL');
        $workflowTaskBatchID = $id <= 0 && (count($assignedUserIds) > 1 || $assignedGroupIds !== [])
            ? $this->newWorkflowTaskBatchId()
            : (string)($existingTask['WorkflowTaskBatchID'] ?? '');
        $projectTaskTypeID = $typesModel->findIdByCode('PROJECT_TASK');
        $taskTypeID = (int) ($_POST['TaskTypeID'] ?? 0);
        if (($workflowProjectID ?? 0) > 0 && ($projectTaskTypeID ?? 0) > 0) {
            $taskTypeID = (int)$projectTaskTypeID;
        }

        $data = [
            'TaskTypeID' => $taskTypeID,
            'StatusID' => $statusID,
            'Title' => trim((string) ($_POST['Title'] ?? '')),
            'Description' => workflow_sanitize_rich_text((string) ($_POST['Description'] ?? '')),
            'CreatedByUserID' => $id > 0 && $existingTask
                ? (int) ($existingTask['CreatedByUserID'] ?? 0)
                : (int) SessionHelper::get('auth.user_id', 0),
            'AssignedToUserID' => count($assignedUserIds) === 1 ? $assignedUserIds[0] : null,
            'RelatedEntity' => trim((string) ($_POST['RelatedEntity'] ?? '')),
            'RelatedKey' => trim((string) ($_POST['RelatedKey'] ?? '')),
            'PriorityCode' => $this->normalizeWorkflowPriorityCode($_POST['PriorityCode'] ?? 'NORMAL'),
            'DueDate' => ($_POST['DueDate'] ?? '') !== '' ? (string) $_POST['DueDate'] : null,
            'WorkflowProjectID' => $workflowProjectID,
            'ParentWorkflowTaskID' => $parentWorkflowTaskID,
            'PlannedStartDate' => ($_POST['PlannedStartDate'] ?? '') !== '' ? (string) $_POST['PlannedStartDate'] : null,
            'PlannedEndDate' => ($_POST['PlannedEndDate'] ?? '') !== '' ? (string) $_POST['PlannedEndDate'] : null,
            'PercentComplete' => $isClosedStatus ? 100 : $this->normalizeWorkflowPercentComplete($_POST['PercentComplete'] ?? 0),
            'ProjectUtilisationPercent' => ($workflowProjectID ?? 0) > 0 ? $this->normalizeWorkflowPercentComplete($_POST['ProjectUtilisationPercent'] ?? 0) : 0,
            'CompletedAt' => $isClosedStatus ? ($existingTask['CompletedAt'] ?? gmdate('Y-m-d H:i:s')) : null,
            'NotifyCreatorOnCompletion' => !empty($_POST['NotifyCreatorOnCompletion']) ? 1 : 0,
            'NotifyCreatorOnUpdate' => !empty($_POST['NotifyCreatorOnUpdate']) ? 1 : 0,
            'NotifyAudienceOnComment' => !empty($_POST['NotifyAudienceOnComment']) ? 1 : 0,
            'AutoReminderEnabled' => !empty($_POST['AutoReminderEnabled']) ? 1 : 0,
            'AutoReminderDaysBeforeDue' => $this->normalizeWorkflowReminderDays($_POST['AutoReminderDaysBeforeDue'] ?? 1),
            'OverdueEscalationEnabled' => !empty($_POST['OverdueEscalationEnabled']) ? 1 : 0,
            'OverdueEscalationDaysAfterDue' => $this->normalizeWorkflowEscalationDays($_POST['OverdueEscalationDaysAfterDue'] ?? 1),
            'WorkflowTaskBatchID' => $workflowTaskBatchID !== '' ? $workflowTaskBatchID : null,
            'WorkflowTaskCompletionRule' => $completionRule,
            'DependsOnWorkflowTaskIDs' => $dependencyWorkflowTaskIDs,
            'UpdatedBy' => (int) SessionHelper::get('auth.user_id', 0),
        ];
        if (($data['WorkflowProjectID'] ?? 0) > 0 && $postedWorkflowRequirementID > 0) {
            $workflowRequirementSelectedForSave = $workflowRequirementsInstalled
                ? $requirementModel->findRequirement($postedWorkflowRequirementID)
                : null;
            if ($workflowRequirementSelectedForSave) {
                $requirementCode = trim((string)($workflowRequirementSelectedForSave['RequirementCode'] ?? ''));
                $data['RelatedEntity'] = 'WorkflowRequirement';
                $data['RelatedKey'] = $requirementCode !== ''
                    ? $requirementCode
                    : (string)$postedWorkflowRequirementID;
            }
        }
        if ($id > 0 && $existingTask) {
            $existingReminderDueDate = $this->normalizeWorkflowDateForCompare($existingTask['DueDate'] ?? null);
            $postedReminderDueDate = $this->normalizeWorkflowDateForCompare($data['DueDate'] ?? null);
            $data['AutoReminderReset'] = (
                (int)($existingTask['AutoReminderEnabled'] ?? 0) !== (int)$data['AutoReminderEnabled']
                || (int)($existingTask['AutoReminderDaysBeforeDue'] ?? 1) !== (int)$data['AutoReminderDaysBeforeDue']
                || $existingReminderDueDate !== $postedReminderDueDate
            ) ? 1 : 0;
            $data['OverdueEscalationReset'] = (
                (int)($existingTask['OverdueEscalationEnabled'] ?? 0) !== (int)$data['OverdueEscalationEnabled']
                || (int)($existingTask['OverdueEscalationDaysAfterDue'] ?? 1) !== (int)$data['OverdueEscalationDaysAfterDue']
                || $existingReminderDueDate !== $postedReminderDueDate
            ) ? 1 : 0;
        }

        $errors = [];
        if ($data['Title'] === '') {
            $errors[] = __t('title_required');
        }
        if (workflow_rich_text_to_plain_text((string)$data['Description']) === '') {
            $errors[] = __t('description_required');
        }
        if ($data['TaskTypeID'] <= 0) {
            $errors[] = __t('task_type_required');
        }
        if (($data['WorkflowProjectID'] ?? 0) > 0 && ($projectTaskTypeID ?? 0) <= 0) {
            $errors[] = __t('workflow_project_task_type_missing');
        }
        if (($data['WorkflowProjectID'] ?? 0) > 0 && !$selectedWorkflowProject) {
            $errors[] = __t('workflow_project_not_found');
        }
        if ($id <= 0 && ($data['WorkflowProjectID'] ?? 0) > 0 && (int)($contextWorkflowProjectID ?? 0) !== (int)$data['WorkflowProjectID']) {
            $errors[] = __t('workflow_project_task_create_from_project_required');
        }
        if ($id > 0 && $existingTask && (int)($postedWorkflowProjectID ?? 0) !== (int)($data['WorkflowProjectID'] ?? 0)) {
            $errors[] = __t('workflow_project_task_project_locked');
        }
        if (($data['WorkflowProjectID'] ?? 0) <= 0 && ($projectTaskTypeID ?? 0) > 0 && (int)$data['TaskTypeID'] === (int)$projectTaskTypeID) {
            $errors[] = __t('workflow_project_task_requires_project');
        }
        if (($data['WorkflowProjectID'] ?? 0) > 0 && $postedWorkflowRequirementID > 0) {
            if (!$workflowRequirementsInstalled) {
                $errors[] = __t('workflow_requirement_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']);
            } elseif (!$workflowRequirementSelectedForSave) {
                $errors[] = __t('workflow_task_requirement_invalid');
            } elseif ((int)($workflowRequirementSelectedForSave['Active'] ?? 0) !== 1) {
                $errors[] = __t('workflow_task_requirement_invalid');
            } elseif ((int)($workflowRequirementSelectedForSave['WorkflowProjectID'] ?? 0) !== (int)$data['WorkflowProjectID']) {
                $errors[] = __t('workflow_task_requirement_project_mismatch');
            }
            if (!$workflowLinksInstalled) {
                $errors[] = __t('workflow_requirement_task_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']);
            }
        }
        if (($data['WorkflowProjectID'] ?? 0) > 0) {
            $projectTaskOptions = $tasksModel->listProjectTaskOptions((int)$data['WorkflowProjectID'], $id > 0 ? $id : null);
            $projectTaskOptionMap = [];
            foreach ($projectTaskOptions as $optionTask) {
                $optionTaskID = (int)($optionTask['WorkflowTaskID'] ?? 0);
                if ($optionTaskID > 0) {
                    $projectTaskOptionMap[$optionTaskID] = $optionTask;
                }
            }

            if (($data['ParentWorkflowTaskID'] ?? 0) > 0) {
                $parentID = (int)$data['ParentWorkflowTaskID'];
                if (!isset($projectTaskOptionMap[$parentID])) {
                    $errors[] = __t('workflow_project_task_parent_invalid');
                } elseif ($id > 0 && (int)($projectTaskOptionMap[$parentID]['ParentWorkflowTaskID'] ?? 0) === $id) {
                    $errors[] = __t('workflow_project_task_parent_cycle');
                }
            }

            if ($dependencyWorkflowTaskIDs !== [] && !$tasksModel->supportsWorkflowTaskDependencies()) {
                $errors[] = __t('workflow_project_task_dependencies_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']);
            }
            foreach ($dependencyWorkflowTaskIDs as $dependencyTaskID) {
                if (!isset($projectTaskOptionMap[$dependencyTaskID])) {
                    $errors[] = __t('workflow_project_task_dependency_invalid');
                    break;
                }
            }
        }
        if ($data['StatusID'] <= 0) {
            $errors[] = __t('status_required');
        }
        if ($id <= 0 && $assignedGroupIds !== [] && !$workflowUserGroupsInstalled) {
            $errors[] = 'Workflow user groups have not been installed. Run backend-php/config/sql/create_workflow_user_groups.sql first.';
        }
        if ($id <= 0 && $assignedGroupIds !== [] && $groupRecipientRows === [] && $assignedUserIds === []) {
            $errors[] = 'Selected workflow user groups do not contain any active users.';
        }
        if (empty($assignedUserIds)) {
            $errors[] = __t('assigned_to_required');
        }
        if ($data['DueDate'] === null) {
            $errors[] = __t('due_date_required');
        }
        if ($data['PlannedStartDate'] !== null && $data['PlannedEndDate'] !== null) {
            $plannedStartTs = strtotime((string)$data['PlannedStartDate']);
            $plannedEndTs = strtotime((string)$data['PlannedEndDate']);
            if ($plannedStartTs !== false && $plannedEndTs !== false && $plannedEndTs < $plannedStartTs) {
                $errors[] = __t('workflow_task_planned_dates_invalid');
            }
        }
        if (($data['WorkflowProjectID'] ?? 0) > 0 && $selectedWorkflowProject) {
            $projectStartDate = $this->normalizeWorkflowDateForCompare($selectedWorkflowProject['StartDate'] ?? null);
            $projectEndDate = $this->normalizeWorkflowDateForCompare($selectedWorkflowProject['TargetEndDate'] ?? null);
            $projectTaskDateFields = [
                'DueDate' => __t('workflow_task_due_date'),
                'PlannedStartDate' => __t('workflow_task_planned_start'),
                'PlannedEndDate' => __t('workflow_task_planned_end'),
            ];
            foreach ($projectTaskDateFields as $field => $label) {
                $taskDate = $this->normalizeWorkflowDateForCompare($data[$field] ?? null);
                if ($taskDate === '') {
                    continue;
                }
                if ($projectStartDate !== '' && $taskDate < $projectStartDate) {
                    $errors[] = __t('workflow_project_task_date_before_start', [
                        'field' => $label,
                        'date' => $projectStartDate,
                    ]);
                }
                if ($projectEndDate !== '' && $taskDate > $projectEndDate) {
                    $errors[] = __t('workflow_project_task_date_after_end', [
                        'field' => $label,
                        'date' => $projectEndDate,
                    ]);
                }
            }
        }
        $createAttachmentsPresent = $id <= 0 && $this->hasWorkflowTaskUploadPayload('TaskAttachments');
        if ($createAttachmentsPresent && !$tasksModel->supportsWorkflowTaskAttachments()) {
            $errors[] = __t('workflow_task_attachments_missing', ['script' => 'backend-php/config/sql/alter_workflow_tasks_add_response_forwarding_activity.sql']);
        }
        if ($createAttachmentsPresent) {
            try {
                $this->validateWorkflowTaskUploadedAttachments('TaskAttachments', true);
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        $context = $this->workflowRedirectContextFromPost();

        if ($id > 0 && !$existingTask) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        if ($id > 0 && !$this->canManageWorkflowTaskDetails($existingTask, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_edit_details'));
            $this->redirectToWorkflowList($context);
        }

        if ($errors) {
            $this->flashError(implode('<br>', $errors));
            $this->redirectToWorkflowEdit($id, $context);
        }

        try {
            $action = $id > 0 ? 'UPDATE' : 'CREATE';

            if ($id > 0) {
                $tasksModel->update($id, $data);
                if (!$tasksModel->saveDependencies($id, $dependencyWorkflowTaskIDs, $currentUserId)) {
                    throw new \RuntimeException($tasksModel->getLastError() ?: __t('workflow_project_task_dependency_save_failed'));
                }
                if ($shouldSyncWorkflowRequirement) {
                    $workflowLinkID = $this->syncWorkflowTaskRequirementLink(
                        $linkModel,
                        $tasksModel,
                        $id,
                        $postedWorkflowRequirementID,
                        $workflowRequirementSelectedForSave,
                        $existingWorkflowRequirementID,
                        $currentUserId
                    );
                    if ($postedWorkflowRequirementID > 0 && $workflowLinkID <= 0) {
                        throw new \RuntimeException($linkModel->getLastError() ?: __t('workflow_task_requirement_link_failed'));
                    }
                }
                $this->recordTaskUpdateActivity($tasksModel, $id, $existingTask, $data, $currentUserId);
                $linkedCompleted = 0;
                $wasClosed = !empty($existingTask['CompletedAt'])
                    || $statusesModel->isClosedStatusId((int)($existingTask['StatusID'] ?? 0));
                $isClosedNow = !empty($data['CompletedAt'])
                    || $statusesModel->isClosedStatusId((int)($data['StatusID'] ?? 0));
                if (!$wasClosed && $isClosedNow) {
                    $sourceTask = $existingTask;
                    $sourceTask['WorkflowTaskID'] = $id;
                    $sourceTask['StatusID'] = $data['StatusID'];
                    $sourceTask['WorkflowTaskBatchID'] = $data['WorkflowTaskBatchID'] ?? $existingTask['WorkflowTaskBatchID'] ?? null;
                    $sourceTask['WorkflowTaskCompletionRule'] = $data['WorkflowTaskCompletionRule'] ?? $existingTask['WorkflowTaskCompletionRule'] ?? 'INDIVIDUAL';
                    $linkedCompleted = $this->completeLinkedWorkflowTasksIfRequired(
                        $tasksModel,
                        $audit,
                        $sourceTask,
                        $data['StatusID'],
                        (string)($data['CompletedAt'] ?? gmdate('Y-m-d H:i:s')),
                        $currentUserId
                    );
                }
                $message = __t('task_updated', ['task' => $data['Title']]);
                if ($linkedCompleted > 0) {
                    $message .= ' ' . $linkedCompleted . ' linked task(s) were also marked completed.';
                }
                $this->flashSuccess($message);
                $this->notifyAssigneeIfNeeded($conn, $userModel, $settingsModel, $statusesModel, $existingTask, $data, $id, $action);
                $this->notifyCreatorIfNeeded($conn, $userModel, $settingsModel, $statusesModel, $existingTask, $data, $id, 'UPDATED', $currentUserId);
            } else {
                $createdTaskIds = [];
                $createdTaskNotifications = [];
                $createdAttachmentCount = 0;
                foreach ($assignedUserIds as $assignedUserId) {
                    $taskData = $data;
                    $taskData['AssignedToUserID'] = $assignedUserId;
                    $newTaskId = $tasksModel->createAndReturnId($taskData);
                    if ($newTaskId <= 0) {
                        throw new \RuntimeException($tasksModel->getLastError() ?: __t('workflow_task_insert_failed'));
                    }
                    if (!$tasksModel->saveDependencies($newTaskId, $dependencyWorkflowTaskIDs, $currentUserId)) {
                        throw new \RuntimeException($tasksModel->getLastError() ?: __t('workflow_project_task_dependency_save_failed'));
                    }
                    if ($shouldSyncWorkflowRequirement) {
                        $workflowLinkID = $this->syncWorkflowTaskRequirementLink(
                            $linkModel,
                            $tasksModel,
                            $newTaskId,
                            $postedWorkflowRequirementID,
                            $workflowRequirementSelectedForSave,
                            0,
                            $currentUserId
                        );
                        if ($postedWorkflowRequirementID > 0 && $workflowLinkID <= 0) {
                            throw new \RuntimeException($linkModel->getLastError() ?: __t('workflow_task_requirement_link_failed'));
                        }
                    }
                    $createdTaskIds[] = $newTaskId;
                    $tasksModel->addActivity(
                        $newTaskId,
                        'CREATED',
                        __t('workflow_task_created_activity'),
                        $currentUserId,
                        null,
                        $assignedUserId,
                        null,
                        $statusID,
                        [
                            'priority' => $taskData['PriorityCode'] ?? 'NORMAL',
                            'workflowUserGroupIds' => $assignedGroupIds,
                            'recipientSource' => $assignedGroupIds !== [] ? 'GROUP_OR_USER_SELECTION' : 'USER_SELECTION',
                            'workflowTaskBatchID' => $taskData['WorkflowTaskBatchID'] ?? null,
                            'workflowTaskCompletionRule' => $taskData['WorkflowTaskCompletionRule'] ?? 'INDIVIDUAL',
                            'parentWorkflowTaskID' => $taskData['ParentWorkflowTaskID'] ?? null,
                            'dependsOnWorkflowTaskIDs' => $dependencyWorkflowTaskIDs,
                        ]
                    );
                    if ($createAttachmentsPresent) {
                        $createdAttachmentCount += $this->storeWorkflowTaskUploadedAttachments(
                            $tasksModel,
                            $audit,
                            $newTaskId,
                            $currentUserId,
                            'TaskAttachments',
                            true,
                            false
                        );
                    }

                    $audit->insert([
                        'UserID' => SessionHelper::get('auth.user_id'),
                        'Username' => SessionHelper::get('auth.username', 'guest'),
                        'Action' => $action,
                        'Entity' => 'WorkflowTask',
                        'EntityKey' => (string) $newTaskId,
                        'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'Details' => $taskData,
                        'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                        'VersionID' => SessionHelper::get('VersionID'),
                    ]);

                    $createdTaskNotifications[] = [$taskData, $newTaskId];
                }

                $createdCount = count($createdTaskIds);
                $message = $createdCount === 1
                    ? __t('task_created', ['task' => $data['Title']])
                    : $createdCount . ' workflow tasks created.';
                if ($createdAttachmentCount > 0) {
                    $message .= ' Attachment(s) added to created task(s).';
                }
                $this->flashSuccess($message);

                foreach ($createdTaskNotifications as [$taskData, $newTaskId]) {
                    $this->notifyAssigneeIfNeeded($conn, $userModel, $settingsModel, $statusesModel, null, $taskData, $newTaskId, $action);
                }
            }

            if ($id > 0) {
                $audit->insert([
                    'UserID' => SessionHelper::get('auth.user_id'),
                    'Username' => SessionHelper::get('auth.username', 'guest'),
                    'Action' => $action,
                    'Entity' => 'WorkflowTask',
                    'EntityKey' => (string) $id,
                    'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'Details' => $data,
                    'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                    'VersionID' => SessionHelper::get('VersionID'),
                ]);
            }
        } catch (\Throwable $e) {
            $this->flashError(__t('task_save_failed') . ': ' . $e->getMessage());
            app_log('[WorkflowController@save] Exception', ['error' => $e->getMessage()], 'error');
        }

        if (!empty($context['returnTo'])) {
            header('Location: ' . $this->mergeLinkedContextIntoUrl((string)$context['returnTo']));
            exit;
        }

        $this->redirectToWorkflowList($context);
    }

    /**
     * @param array<string, mixed>|null $requirement
     */
    private function syncWorkflowTaskRequirementLink(
        WorkflowLinkModel $linkModel,
        WorkflowTaskModel $tasksModel,
        int $workflowTaskID,
        int $workflowRequirementID,
        ?array $requirement,
        int $existingWorkflowRequirementID,
        int $currentUserId
    ): int {
        if ($workflowTaskID <= 0) {
            return 0;
        }

        if ($workflowRequirementID <= 0) {
            $linkModel->syncTaskRequirementLink(
                $workflowTaskID,
                null,
                null,
                null,
                null,
                null,
                $currentUserId
            );
            if ($existingWorkflowRequirementID > 0) {
                $tasksModel->addActivity(
                    $workflowTaskID,
                    'LINK_REMOVED',
                    __t('workflow_link_removed_activity', [
                        'type' => 'REQUIREMENT',
                        'title' => __t('workflow_requirement'),
                    ]),
                    $currentUserId,
                    null,
                    null,
                    null,
                    null,
                    [
                        'linkTypeCode' => 'REQUIREMENT',
                        'linkedEntity' => 'WorkflowRequirement',
                        'linkedEntityID' => $existingWorkflowRequirementID,
                    ]
                );
            }
            return 0;
        }

        if (!$requirement) {
            return 0;
        }

        $requirementCode = trim((string)($requirement['RequirementCode'] ?? ''));
        $requirementTitle = trim((string)($requirement['RequirementTitle'] ?? ''));
        $linkTitle = $requirementTitle !== ''
            ? $requirementTitle
            : ($requirementCode !== '' ? $requirementCode : __t('workflow_requirement'));
        $workflowLinkID = $linkModel->syncTaskRequirementLink(
            $workflowTaskID,
            $workflowRequirementID,
            $requirementCode,
            $linkTitle,
            'index.php?route=workflow-requirements/form&id=' . $workflowRequirementID,
            __t('workflow_task_requirement_link_note'),
            $currentUserId
        );

        if ($workflowLinkID > 0 && $workflowRequirementID !== $existingWorkflowRequirementID) {
            $tasksModel->addActivity(
                $workflowTaskID,
                'LINK_ADDED',
                __t('workflow_task_requirement_linked_activity', [
                    'code' => $requirementCode !== '' ? $requirementCode : (string)$workflowRequirementID,
                    'title' => $linkTitle,
                ]),
                $currentUserId,
                null,
                null,
                null,
                null,
                [
                    'workflowLinkID' => $workflowLinkID,
                    'linkTypeCode' => 'REQUIREMENT',
                    'linkedEntity' => 'WorkflowRequirement',
                    'linkedEntityID' => $workflowRequirementID,
                    'linkedEntityKey' => $requirementCode,
                ]
            );
        }

        return $workflowLinkID;
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private function normalizeAssignedWorkflowUserIds($raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $ids = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private function normalizeWorkflowUserGroupIds($raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $ids = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param mixed $raw
     * @return array<int, int>
     */
    private function normalizeWorkflowTaskIds($raw): array
    {
        $values = is_array($raw) ? $raw : [$raw];
        $ids = [];
        foreach ($values as $value) {
            $id = (int)$value;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function normalizeWorkflowTaskCompletionRule($raw): string
    {
        $rule = strtoupper(trim((string) $raw));
        return in_array($rule, ['INDIVIDUAL', 'ANY_COMPLETES_ALL'], true) ? $rule : 'INDIVIDUAL';
    }

    private function normalizeWorkflowReminderDays($raw): int
    {
        return max(0, min(365, (int)$raw));
    }

    private function normalizeWorkflowEscalationDays($raw): int
    {
        return max(1, min(365, (int)$raw));
    }

    private function normalizeWorkflowPercentComplete($raw): float
    {
        return max(0, min(100, round((float)$raw, 2)));
    }

    private function normalizeWorkflowDueState($raw): string
    {
        $state = strtolower(trim((string)$raw));
        return in_array($state, ['overdue', 'today', 'soon'], true) ? $state : '';
    }

    private function normalizeWorkflowDateForCompare($raw): string
    {
        $value = trim((string)$raw);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp ? gmdate('Y-m-d', $timestamp) : $value;
    }

    private function newWorkflowTaskBatchId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * @param array<string, mixed> $sourceTask
     */
    private function completeLinkedWorkflowTasksIfRequired(
        WorkflowTaskModel $tasksModel,
        AuditModel $audit,
        array $sourceTask,
        int $completedStatusID,
        string $completedAt,
        int $currentUserId
    ): int {
        $rule = $this->normalizeWorkflowTaskCompletionRule($sourceTask['WorkflowTaskCompletionRule'] ?? 'INDIVIDUAL');
        $batchID = trim((string)($sourceTask['WorkflowTaskBatchID'] ?? ''));
        $sourceTaskID = (int)($sourceTask['WorkflowTaskID'] ?? 0);

        if ($rule !== 'ANY_COMPLETES_ALL' || $batchID === '' || $sourceTaskID <= 0 || $completedStatusID <= 0 || $currentUserId <= 0) {
            return 0;
        }

        $completedAt = trim($completedAt) !== '' ? $completedAt : gmdate('Y-m-d H:i:s');
        $siblings = $tasksModel->listOpenBatchTasks($batchID, $sourceTaskID);
        $completedCount = 0;

        foreach ($siblings as $sibling) {
            $siblingTaskID = (int)($sibling['WorkflowTaskID'] ?? 0);
            if ($siblingTaskID <= 0) {
                continue;
            }

            $oldStatusID = (int)($sibling['StatusID'] ?? 0);
            if (!$tasksModel->updateStatus($siblingTaskID, $completedStatusID, $completedAt, $currentUserId)) {
                app_log('Linked workflow task completion failed', [
                    'WorkflowTaskID' => $siblingTaskID,
                    'SourceWorkflowTaskID' => $sourceTaskID,
                    'BatchID' => $batchID,
                    'error' => $tasksModel->getLastError(),
                ], 'error');
                continue;
            }

            $tasksModel->addActivity(
                $siblingTaskID,
                'COMPLETED_BY_GROUP',
                __t('workflow_task_completed_by_group_activity', ['taskID' => $sourceTaskID]),
                $currentUserId,
                null,
                null,
                $oldStatusID > 0 ? $oldStatusID : null,
                $completedStatusID,
                [
                    'sourceWorkflowTaskID' => $sourceTaskID,
                    'workflowTaskBatchID' => $batchID,
                    'workflowTaskCompletionRule' => $rule,
                ]
            );

            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'COMPLETE_LINKED',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string)$siblingTaskID,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => [
                    'SourceWorkflowTaskID' => $sourceTaskID,
                    'WorkflowTaskBatchID' => $batchID,
                    'WorkflowTaskCompletionRule' => $rule,
                    'NewStatusID' => $completedStatusID,
                ],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);

            $completedCount++;
        }

        return $completedCount;
    }

    /**
     * @param array<int, string> $perms
     */
    private function canAdminWorkflowTasks(array $perms): bool
    {
        return in_array('WORKFLOW_OPERATIONS_ADMIN', $perms, true)
            || in_array('ADMIN_ALL', $perms, true)
            || in_array('SYSADMIN', $perms, true);
    }

    /**
     * @param array<string, mixed>|null $task
     * @param array<int, string> $perms
     */
    private function canManageWorkflowTaskDetails(?array $task, array $perms, int $userId): bool
    {
        if (!$task || $userId <= 0) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }
        if (!in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true)) {
            return false;
        }

        return (int)($task['CreatedByUserID'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed>|null $task
     * @param array<int, string> $perms
     */
    private function canAccessWorkflowTask(?array $task, array $perms, int $userId): bool
    {
        if (!$task || $userId <= 0) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }
        if (
            !in_array('WORKFLOW_OPERATIONS_VIEW', $perms, true)
            && !in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true)
        ) {
            return false;
        }

        return (int)($task['AssignedToUserID'] ?? 0) === $userId
            || (int)($task['CreatedByUserID'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed>|null $task
     * @param array<int, string> $perms
     */
    private function canRespondWorkflowTask(?array $task, array $perms, int $userId): bool
    {
        if (!$task || $userId <= 0) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }
        if (
            !in_array('WORKFLOW_OPERATIONS_VIEW', $perms, true)
            && !in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true)
        ) {
            return false;
        }

        return (int)($task['AssignedToUserID'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed>|null $task
     * @param array<int, string> $perms
     */
    private function canForwardWorkflowTask(?array $task, array $perms, int $userId): bool
    {
        if (!$task || $userId <= 0) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }
        if (
            !in_array('WORKFLOW_OPERATIONS_VIEW', $perms, true)
            && !in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true)
        ) {
            return false;
        }

        return (int)($task['AssignedToUserID'] ?? 0) === $userId
            || (int)($task['CreatedByUserID'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed>|null $task
     * @param array<int, string> $perms
     */
    private function canTransitionWorkflowTask(?array $task, array $perms, int $userId): bool
    {
        if (!$task || $userId <= 0) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }
        if (
            !in_array('WORKFLOW_OPERATIONS_VIEW', $perms, true)
            && !in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true)
        ) {
            return false;
        }

        return (int)($task['AssignedToUserID'] ?? 0) === $userId
            || (int)($task['CreatedByUserID'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed>|null $task
     * @param array<int, string> $perms
     */
    private function canSendWorkflowTaskReminder(?array $task, array $perms, int $userId): bool
    {
        if (!$task || $userId <= 0 || $this->isWorkflowTaskClosed($task)) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }
        if (!in_array('WORKFLOW_OPERATIONS_EDIT', $perms, true)) {
            return false;
        }

        return (int)($task['CreatedByUserID'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed>|null $task
     * @param array<int, string> $perms
     */
    private function canDeleteWorkflowTask(?array $task, array $perms, int $userId): bool
    {
        if (!$task || $userId <= 0) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }

        return (int)($task['CreatedByUserID'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed> $task
     */
    private function isWorkflowTaskClosed(array $task): bool
    {
        $statusCode = strtoupper(trim((string)($task['StatusCode'] ?? '')));
        $statusName = strtoupper(trim((string)($task['StatusName'] ?? '')));
        return !empty($task['CompletedAt'])
            || in_array($statusCode, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
            || in_array($statusName, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true);
    }

    /**
     * @param array<string, mixed>|null $existingTask
     * @param array<string, mixed> $data
     */
    private function recordTaskUpdateActivity(
        WorkflowTaskModel $tasksModel,
        int $workflowTaskID,
        ?array $existingTask,
        array $data,
        int $currentUserId
    ): void {
        if (!$existingTask || $workflowTaskID <= 0 || $currentUserId <= 0) {
            return;
        }

        $oldAssignee = (int) ($existingTask['AssignedToUserID'] ?? 0);
        $newAssignee = (int) ($data['AssignedToUserID'] ?? 0);
        if ($oldAssignee !== $newAssignee) {
            $tasksModel->addActivity(
                $workflowTaskID,
                'REASSIGNED',
                __t('workflow_task_reassigned_activity'),
                $currentUserId,
                $oldAssignee > 0 ? $oldAssignee : null,
                $newAssignee > 0 ? $newAssignee : null
            );
        }

        $oldPriority = $this->normalizeWorkflowPriorityCode($existingTask['PriorityCode'] ?? 'NORMAL');
        $newPriority = $this->normalizeWorkflowPriorityCode($data['PriorityCode'] ?? 'NORMAL');
        if ($oldPriority !== $newPriority) {
            $tasksModel->addActivity(
                $workflowTaskID,
                'PRIORITY_CHANGED',
                __t('workflow_task_priority_changed_activity', ['from' => $oldPriority, 'to' => $newPriority]),
                $currentUserId,
                null,
                null,
                null,
                null,
                ['fromPriority' => $oldPriority, 'toPriority' => $newPriority]
            );
        }

        $tasksModel->addActivity($workflowTaskID, 'UPDATED', __t('workflow_task_updated_activity'), $currentUserId);
    }

    private function normalizeWorkflowPriorityCode($value): string
    {
        $code = strtoupper(trim((string) $value));
        return in_array($code, ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true) ? $code : 'NORMAL';
    }

    public function transition(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $userModel = new UserModel($conn);
        $settingsModel = new SystemSettingsModel($conn);
        $audit = new AuditModel($conn);

        $id = (int) ($_POST['WorkflowTaskID'] ?? 0);
        $transition = strtolower(trim((string) ($_POST['transition'] ?? '')));
        $returnTo = strtolower(trim((string) ($_POST['return_to'] ?? '')));
        $context = $this->workflowRedirectContextFromPost();

        if ($id <= 0 || !in_array($transition, ['complete', 'in_progress', 'reopen'], true)) {
            $this->flashError(__t('workflow_task_invalid_action'));
            $this->redirectToWorkflowList($context);
        }

        $task = $tasksModel->find($id);
        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int) SessionHelper::get('auth.user_id', 0);
        if (!$this->canTransitionWorkflowTask($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_update_status'));
            $this->redirectToWorkflowList($context);
        }
        $postedRecipientResponse = array_key_exists('RecipientResponse', $_POST)
            ? trim((string)$_POST['RecipientResponse'])
            : null;
        $canSaveResponseOnComplete = $transition === 'complete'
            && $postedRecipientResponse !== null
            && $postedRecipientResponse !== ''
            && $this->canRespondWorkflowTask($task, $perms, $currentUserId);

        if ($transition === 'complete') {
            $statusID = $statusesModel->findCompletedStatusId() ?? (int) ($task['StatusID'] ?? 0);
        } elseif ($transition === 'in_progress') {
            $statusID = $statusesModel->findInProgressStatusId()
                ?? $statusesModel->findOpenStatusId()
                ?? (int) ($task['StatusID'] ?? 0);
        } else {
            $statusID = $statusesModel->findOpenStatusId() ?? (int) ($task['StatusID'] ?? 0);
        }
        $completedAt = $transition === 'complete' ? gmdate('Y-m-d H:i:s') : null;
        $activityType = match ($transition) {
            'complete' => 'COMPLETED',
            'in_progress' => 'IN_PROGRESS',
            default => 'REOPENED',
        };
        $activityNote = match ($transition) {
            'complete' => __t('workflow_task_marked_complete'),
            'in_progress' => __t('workflow_task_marked_in_progress'),
            default => __t('workflow_task_reopened'),
        };
        $flashMessage = match ($transition) {
            'complete' => __t('workflow_task_marked_complete'),
            'in_progress' => __t('workflow_task_marked_in_progress'),
            default => __t('workflow_task_reopened'),
        };
        $notificationEvent = $transition === 'complete' ? 'COMPLETED' : 'UPDATED';

        if ($transition === 'complete' && $this->isWorkflowTaskClosed($task)) {
            $this->flashSuccess(__t('workflow_task_already_complete'));
            if ($returnTo === 'edit' && $id > 0) {
                $this->redirectToWorkflowEdit($id, $context);
            }
            $this->redirectToWorkflowList($context);
        }

        if ($transition === 'complete') {
            $statusUpdateResult = $tasksModel->completeStatusIfOpen($id, $statusID, (string)$completedAt, $currentUserId);
            $statusUpdated = $statusUpdateResult > 0;
            if (!$statusUpdated && $statusUpdateResult === 0) {
                $latestTask = $tasksModel->find($id);
                if ($latestTask && $this->isWorkflowTaskClosed($latestTask)) {
                    $this->flashSuccess(__t('workflow_task_already_complete'));
                    if ($returnTo === 'edit' && $id > 0) {
                        $this->redirectToWorkflowEdit($id, $context);
                    }
                    $this->redirectToWorkflowList($context);
                }
            }
        } else {
            $statusUpdated = $tasksModel->updateStatus($id, $statusID, $completedAt, $currentUserId);
        }

        if ($statusUpdated) {
            $tasksModel->addActivity(
                $id,
                $activityType,
                $activityNote,
                $currentUserId,
                null,
                null,
                (int) ($task['StatusID'] ?? 0),
                $statusID
            );
            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => strtoupper($transition),
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string) $id,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => [
                    'Title' => $task['Title'] ?? '',
                    'Transition' => $transition,
                    'NewStatusID' => $statusID,
                ],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);
            $updatedTask = $task;
            $updatedTask['StatusID'] = $statusID;
            $updatedTask['CompletedAt'] = $completedAt;
            $updatedTask['UpdatedBy'] = $currentUserId;
            if ($canSaveResponseOnComplete) {
                $existingResponse = trim((string)($task['RecipientResponse'] ?? ''));
                if ($postedRecipientResponse !== $existingResponse) {
                    if ($tasksModel->updateRecipientResponse($id, $postedRecipientResponse, $currentUserId)) {
                        $tasksModel->addActivity($id, 'RESPONSE', $postedRecipientResponse, $currentUserId);
                        $updatedTask['RecipientResponse'] = $postedRecipientResponse;
                        $updatedTask['RespondedByUserID'] = $currentUserId;
                        $updatedTask['RespondedAt'] = gmdate('Y-m-d H:i:s');
                    } else {
                        app_log('Workflow task response could not be saved during completion', [
                            'WorkflowTaskID' => $id,
                            'UserID' => $currentUserId,
                            'error' => $tasksModel->getLastError(),
                        ], 'error');
                    }
                }
            }
            $linkedCompleted = 0;
            if ($transition === 'complete') {
                $linkedCompleted = $this->completeLinkedWorkflowTasksIfRequired(
                    $tasksModel,
                    $audit,
                    $updatedTask,
                    $statusID,
                    (string)$completedAt,
                    $currentUserId
                );
            }
            $this->notifyCreatorIfNeeded(
                $conn,
                $userModel,
                $settingsModel,
                $statusesModel,
                $task,
                $updatedTask,
                $id,
                $notificationEvent,
                $currentUserId
            );
            if ($linkedCompleted > 0) {
                $flashMessage .= ' ' . __t('workflow_task_linked_completed', ['count' => $linkedCompleted]);
            }
            $this->flashSuccess($flashMessage);
        } else {
            $this->flashError(__t('workflow_task_status_update_failed'));
        }

        if ($returnTo === 'edit' && $id > 0) {
            $this->redirectToWorkflowEdit($id, $context);
        }

        $this->redirectToWorkflowList($context);
    }

    public function respond(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $id = (int) ($_POST['WorkflowTaskID'] ?? 0);
        $response = trim((string) ($_POST['RecipientResponse'] ?? ''));
        $context = $this->workflowRedirectContextFromPost();
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int) SessionHelper::get('auth.user_id', 0);

        $task = $id > 0 ? $tasksModel->find($id) : null;
        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canRespondWorkflowTask($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_respond'));
            $this->redirectToWorkflowEdit($id, $context);
        }
        if ($response === '') {
            $this->flashError(__t('workflow_task_response_required'));
            $this->redirectToWorkflowEdit($id, $context);
        }

        if ($tasksModel->updateRecipientResponse($id, $response, $currentUserId)) {
            $tasksModel->addActivity($id, 'RESPONSE', $response, $currentUserId);
            $this->flashSuccess(__t('workflow_task_response_saved'));
        } else {
            $this->flashError(__t('workflow_task_response_save_failed', ['msg' => $tasksModel->getLastError()]));
        }

        $this->redirectToWorkflowEdit($id, $context);
    }

    public function forward(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $userModel = new UserModel($conn);
        $settingsModel = new SystemSettingsModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $audit = new AuditModel($conn);

        $id = (int) ($_POST['WorkflowTaskID'] ?? 0);
        $newAssignedToUserID = (int) ($_POST['ForwardToUserID'] ?? 0);
        $reason = trim((string) ($_POST['ForwardReason'] ?? ''));
        $context = $this->workflowRedirectContextFromPost();
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int) SessionHelper::get('auth.user_id', 0);

        $task = $id > 0 ? $tasksModel->find($id) : null;
        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canForwardWorkflowTask($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_forward'));
            $this->redirectToWorkflowEdit($id, $context);
        }
        if ($newAssignedToUserID <= 0 || !$userModel->findById($newAssignedToUserID)) {
            $this->flashError(__t('workflow_task_forward_user_required'));
            $this->redirectToWorkflowEdit($id, $context);
        }
        if ((int) ($task['AssignedToUserID'] ?? 0) === $newAssignedToUserID) {
            $this->flashError(__t('workflow_task_forward_user_same'));
            $this->redirectToWorkflowEdit($id, $context);
        }
        if ($reason === '') {
            $this->flashError(__t('workflow_task_forward_reason_required'));
            $this->redirectToWorkflowEdit($id, $context);
        }

        $oldAssignedToUserID = (int) ($task['AssignedToUserID'] ?? 0);
        if ($tasksModel->forwardTask($id, $newAssignedToUserID, $reason, $currentUserId)) {
            $forwardedTask = $task;
            $forwardedTask['AssignedToUserID'] = $newAssignedToUserID;
            $forwardedTask['UpdatedBy'] = $currentUserId;
            $forwardedTask['PriorityCode'] = $task['PriorityCode'] ?? 'NORMAL';
            $tasksModel->addActivity(
                $id,
                'FORWARDED',
                $reason,
                $currentUserId,
                $oldAssignedToUserID > 0 ? $oldAssignedToUserID : null,
                $newAssignedToUserID
            );

            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'FORWARD',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string) $id,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => [
                    'FromUserID' => $oldAssignedToUserID,
                    'ToUserID' => $newAssignedToUserID,
                    'Reason' => $reason,
                ],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);

            $this->flashSuccess(__t('workflow_task_forwarded'));
            $this->notifyAssigneeIfNeeded($conn, $userModel, $settingsModel, $statusesModel, $task, $forwardedTask, $id, 'FORWARD');
            $this->notifyCreatorIfNeeded($conn, $userModel, $settingsModel, $statusesModel, $task, $forwardedTask, $id, 'UPDATED', $currentUserId);
        } else {
            $this->flashError(__t('workflow_task_forward_failed', ['msg' => $tasksModel->getLastError()]));
        }

        $this->redirectToWorkflowEdit($id, $context);
    }

    public function sendReminder(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);

        $id = (int)($_POST['WorkflowTaskID'] ?? 0);
        $context = $this->workflowRedirectContextFromPost();
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        $task = $id > 0 ? $tasksModel->find($id) : null;
        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canSendWorkflowTaskReminder($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_send_reminder'));
            $this->redirectToWorkflowEdit($id, $context);
        }

        if (!$tasksModel->acquireManualReminderLock($id, 0)) {
            $this->flashInfo(__t('workflow_task_reminder_lock_active'));
            $this->redirectToWorkflowEdit($id, $context);
        }

        try {
            if ($tasksModel->wasManualReminderSentRecently($id, self::MANUAL_REMINDER_COOLDOWN_SECONDS)) {
                $this->flashInfo(__t('workflow_task_reminder_recent'));
            } else {
                $reminderService = new WorkflowTaskReminderService($conn);
                if (!$reminderService->sendReminder($task, $id, 'MANUAL', $currentUserId)) {
                    $this->flashError(__t('workflow_task_reminder_send_failed', ['msg' => $reminderService->getLastError()]));
                } else {
                    $sentAtUtc = gmdate('Y-m-d H:i:s');
                    $recipientName = trim((string)($task['AssignedToName'] ?? ''));
                    if ($recipientName === '') {
                        $assignedToUserID = (int)($task['AssignedToUserID'] ?? 0);
                        $recipientName = $assignedToUserID > 0
                            ? __t('user_number', ['id' => $assignedToUserID])
                            : __t('workflow_task_recipient');
                    }

                    if (!$tasksModel->recordManualReminderSent($id, $currentUserId)) {
                        app_log('Workflow manual reminder timestamp update failed', [
                            'WorkflowTaskID' => $id,
                            'UserID' => $currentUserId,
                            'error' => $tasksModel->getLastError(),
                        ], 'warn');
                    }
                    $tasksModel->addActivity(
                        $id,
                        'REMINDER_SENT',
                        __t('workflow_task_manual_reminder_activity', [
                            'recipient' => $recipientName,
                            'sentAt' => $sentAtUtc,
                        ]),
                        $currentUserId,
                        null,
                        (int)($task['AssignedToUserID'] ?? 0),
                        null,
                        null,
                        [
                            'reminderType' => 'MANUAL',
                            'sentAtUtc' => $sentAtUtc,
                            'cooldownSeconds' => self::MANUAL_REMINDER_COOLDOWN_SECONDS,
                        ]
                    );
                    $audit->insert([
                        'UserID' => SessionHelper::get('auth.user_id'),
                        'Username' => SessionHelper::get('auth.username', 'guest'),
                        'Action' => 'REMINDER_SENT',
                        'Entity' => 'WorkflowTask',
                        'EntityKey' => (string)$id,
                        'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        'Details' => [
                            'Title' => $task['Title'] ?? '',
                            'AssignedToUserID' => (int)($task['AssignedToUserID'] ?? 0),
                            'SentAtUTC' => $sentAtUtc,
                        ],
                        'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                        'VersionID' => SessionHelper::get('VersionID'),
                    ]);

                    $this->flashSuccess(__t('workflow_task_reminder_sent'));
                }
            }
        } finally {
            if (!$tasksModel->releaseManualReminderLock($id)) {
                app_log('Workflow manual reminder lock release failed', [
                    'WorkflowTaskID' => $id,
                    'UserID' => $currentUserId,
                    'error' => $tasksModel->getLastError(),
                ], 'warn');
            }
        }

        $this->redirectToWorkflowEdit($id, $context);
    }

    public function delete(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';
        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);

        $id = (int) ($_POST['WorkflowTaskID'] ?? 0);
        $context = $this->workflowRedirectContextFromPost();
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        if ($id <= 0) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        $task = $tasksModel->find($id);
        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canDeleteWorkflowTask($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_delete'));
            $this->redirectToWorkflowList($context);
        }

        try {
            $tasksModel->delete($id);
            $this->flashSuccess(__t('task_deleted', ['id' => $id]));

            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'DELETE',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string) $id,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => ['Message' => "Task $id deleted"],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowController::delete failed', $e, [
                'workflowTaskId' => $id,
            ]);
            $this->flashError(__t('task_delete_failed') . ': ' . $e->getMessage());
        }

        $this->redirectToWorkflowList($context);
    }

    public function saveLink(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $linkModel = new WorkflowLinkModel($conn);
        $audit = new AuditModel($conn);

        $id = (int)($_POST['WorkflowTaskID'] ?? 0);
        $context = $this->workflowRedirectContextFromPost();
        $task = $id > 0 ? $tasksModel->find($id) : null;
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canManageWorkflowTaskDetails($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_edit_details'));
            $this->redirectToWorkflowEdit($id, $context);
        }
        if (!$linkModel->supportsWorkflowLinks()) {
            $this->flashError(__t('workflow_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            $this->redirectToWorkflowEdit($id, $context);
        }

        $payload = [
            'WorkflowTaskID' => $id,
            'WorkflowProjectID' => (int)($task['WorkflowProjectID'] ?? 0),
            'LinkTypeCode' => strtoupper(trim((string)($_POST['LinkTypeCode'] ?? 'RELATED_ITEM'))),
            'LinkedEntity' => trim((string)($_POST['LinkedEntity'] ?? '')),
            'LinkedEntityID' => ($_POST['LinkedEntityID'] ?? '') !== '' ? (int)$_POST['LinkedEntityID'] : null,
            'LinkedEntityKey' => trim((string)($_POST['LinkedEntityKey'] ?? '')),
            'LinkedTitle' => trim((string)($_POST['LinkedTitle'] ?? '')),
            'LinkedUrl' => trim((string)($_POST['LinkedUrl'] ?? '')),
            'Notes' => trim((string)($_POST['Notes'] ?? '')),
        ];

        if ($payload['LinkedEntity'] === '') {
            $this->flashError(__t('workflow_link_entity_required'));
            $this->redirectToWorkflowEdit($id, $context);
        }
        if (($payload['LinkedEntityID'] ?? 0) <= 0
            && $payload['LinkedEntityKey'] === ''
            && $payload['LinkedTitle'] === ''
            && $payload['LinkedUrl'] === ''
        ) {
            $this->flashError(__t('workflow_link_target_required'));
            $this->redirectToWorkflowEdit($id, $context);
        }

        try {
            $workflowLinkID = $linkModel->saveTaskLink($payload, $currentUserId);
            $tasksModel->addActivity(
                $id,
                'LINK_ADDED',
                __t('workflow_link_added_activity', [
                    'type' => $payload['LinkTypeCode'],
                    'title' => $payload['LinkedTitle'] !== '' ? $payload['LinkedTitle'] : $payload['LinkedEntity'],
                ]),
                $currentUserId,
                null,
                null,
                null,
                null,
                [
                    'workflowLinkID' => $workflowLinkID,
                    'linkTypeCode' => $payload['LinkTypeCode'],
                    'linkedEntity' => $payload['LinkedEntity'],
                    'linkedEntityID' => $payload['LinkedEntityID'],
                    'linkedEntityKey' => $payload['LinkedEntityKey'],
                ]
            );
            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'LINK_ADDED',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string)$id,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => $payload + ['WorkflowLinkID' => $workflowLinkID],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);
            $this->flashSuccess(__t('workflow_link_added'));
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_link_save_failed') . ': ' . $e->getMessage());
        }

        $this->redirectToWorkflowEdit($id, $context);
    }

    public function deleteLink(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $linkModel = new WorkflowLinkModel($conn);
        $audit = new AuditModel($conn);

        $workflowLinkID = (int)($_POST['WorkflowLinkID'] ?? 0);
        $context = $this->workflowRedirectContextFromPost();
        $link = $workflowLinkID > 0 ? $linkModel->findLink($workflowLinkID) : null;
        $id = (int)($link['WorkflowTaskID'] ?? ($_POST['WorkflowTaskID'] ?? 0));
        $task = $id > 0 ? $tasksModel->find($id) : null;
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        if (!$link || !$task) {
            $this->flashError(__t('workflow_link_not_found'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canManageWorkflowTaskDetails($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_edit_details'));
            $this->redirectToWorkflowEdit($id, $context);
        }

        if ($linkModel->deactivateLink($workflowLinkID, $currentUserId)) {
            $tasksModel->addActivity(
                $id,
                'LINK_REMOVED',
                __t('workflow_link_removed_activity', [
                    'type' => (string)($link['LinkTypeCode'] ?? ''),
                    'title' => (string)($link['LinkedTitle'] ?? $link['LinkedEntity'] ?? ''),
                ]),
                $currentUserId,
                null,
                null,
                null,
                null,
                ['workflowLinkID' => $workflowLinkID]
            );
            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'LINK_REMOVED',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string)$id,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => ['WorkflowLinkID' => $workflowLinkID, 'Link' => $link],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);
            $this->flashSuccess(__t('workflow_link_removed'));
        } else {
            $this->flashError(__t('workflow_link_delete_failed') . ': ' . $linkModel->getLastError());
        }

        $this->redirectToWorkflowEdit($id, $context);
    }

    public function saveComment(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);
        $userModel = new UserModel($conn);
        $settingsModel = new SystemSettingsModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $id = (int)($_POST['WorkflowTaskID'] ?? 0);
        $commentText = trim((string)($_POST['CommentText'] ?? ''));
        $notifyAudience = !empty($_POST['NotifyAudienceOnComment']);
        $context = $this->workflowRedirectContextFromPost();
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        $task = $id > 0 ? $tasksModel->find($id) : null;
        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canAccessWorkflowTask($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_add_comment'));
            $this->redirectToWorkflowList($context);
        }
        if (!$tasksModel->supportsWorkflowTaskComments()) {
            $this->flashError(__t('workflow_task_comments_missing', ['script' => 'backend-php/config/sql/alter_workflow_tasks_add_response_forwarding_activity.sql']));
            $this->redirectToWorkflowEdit($id, $context);
        }
        if ($commentText === '') {
            $this->flashError(__t('workflow_task_comment_required'));
            $this->redirectToWorkflowEdit($id, $context);
        }

        try {
            $commentId = $tasksModel->saveComment($id, $commentText, $currentUserId);
            if ($commentId <= 0) {
                throw new \RuntimeException($tasksModel->getLastError() ?: __t('workflow_task_unknown_error'));
            }

            $tasksModel->addActivity(
                $id,
                'COMMENT',
                $commentText,
                $currentUserId,
                null,
                null,
                null,
                null,
                ['commentId' => $commentId]
            );

            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'COMMENT_ADD',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string)$id,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => [
                    'WorkflowTaskCommentID' => $commentId,
                    'Title' => $task['Title'] ?? '',
                ],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);

            $this->flashSuccess(__t('workflow_task_comment_added'));
            if ($notifyAudience) {
                $this->notifyTaskAudienceOfComment(
                    $conn,
                    $userModel,
                    $settingsModel,
                    $statusesModel,
                    $task,
                    $id,
                    $commentText,
                    $currentUserId
                );
            }
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_task_comment_save_failed', ['msg' => $e->getMessage()]));
            app_log('Workflow task comment save failed', [
                'WorkflowTaskID' => $id,
                'UserID' => $currentUserId,
                'error' => $e->getMessage(),
            ], 'error');
        }

        $this->redirectToWorkflowEdit($id, $context);
    }

    public function deleteComment(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);
        $commentId = (int)($_POST['WorkflowTaskCommentID'] ?? 0);
        $context = $this->workflowRedirectContextFromPost();
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        $comment = $tasksModel->getComment($commentId);
        $workflowTaskId = (int)($comment['WorkflowTaskID'] ?? 0);
        if (!$comment || $workflowTaskId <= 0) {
            $this->flashError(__t('workflow_task_comment_not_found'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canAccessWorkflowTask($comment, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_delete_comment'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canDeleteWorkflowTaskComment($comment, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_delete_comment'));
            $this->redirectToWorkflowEdit($workflowTaskId, $context);
        }

        try {
            if (!$tasksModel->markCommentDeleted($commentId, $currentUserId)) {
                throw new \RuntimeException($tasksModel->getLastError() ?: __t('workflow_task_unknown_error'));
            }

            $tasksModel->addActivity(
                $workflowTaskId,
                'COMMENT_DELETED',
                __t('workflow_task_comment_removed'),
                $currentUserId,
                null,
                null,
                null,
                null,
                ['commentId' => $commentId]
            );

            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'COMMENT_DELETE',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string)$workflowTaskId,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => [
                    'WorkflowTaskCommentID' => $commentId,
                    'CommentPreview' => substr((string)($comment['CommentText'] ?? ''), 0, 250),
                ],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);

            $this->flashSuccess(__t('workflow_task_comment_removed'));
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_task_comment_remove_failed', ['msg' => $e->getMessage()]));
            app_log('Workflow task comment delete failed', [
                'WorkflowTaskCommentID' => $commentId,
                'WorkflowTaskID' => $workflowTaskId,
                'UserID' => $currentUserId,
                'error' => $e->getMessage(),
            ], 'error');
        }

        $this->redirectToWorkflowEdit($workflowTaskId, $context);
    }

    public function uploadAttachment(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);
        $id = (int)($_POST['WorkflowTaskID'] ?? 0);
        $context = $this->workflowRedirectContextFromPost();
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        $task = $id > 0 ? $tasksModel->find($id) : null;
        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canAccessWorkflowTask($task, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_add_attachment'));
            $this->redirectToWorkflowList($context);
        }
        if (!$tasksModel->supportsWorkflowTaskAttachments()) {
            $this->flashError(__t('workflow_task_attachments_missing', ['script' => 'backend-php/config/sql/alter_workflow_tasks_add_response_forwarding_activity.sql']));
            $this->redirectToWorkflowEdit($id, $context);
        }

        try {
            $uploadedCount = $this->storeWorkflowTaskUploadedAttachments(
                $tasksModel,
                $audit,
                $id,
                $currentUserId,
                'TaskAttachment',
                false,
                true
            );

            $this->flashSuccess($uploadedCount === 1
                ? __t('workflow_task_attachment_uploaded')
                : __t('workflow_task_attachments_uploaded', ['count' => $uploadedCount])
            );
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_task_attachment_upload_failed', ['msg' => $e->getMessage()]));
            app_log('Workflow task attachment upload failed', [
                'WorkflowTaskID' => $id,
                'UserID' => $currentUserId,
                'error' => $e->getMessage(),
            ], 'error');
        }

        $this->redirectToWorkflowEdit($id, $context);
    }

    public function downloadAttachment(): void
    {
        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $attachmentId = (int)($_GET['id'] ?? 0);
        $attachment = $tasksModel->getAttachment($attachmentId);
        if (!$attachment) {
            http_response_code(404);
            echo __t('workflow_task_attachment_not_found');
            return;
        }

        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        if (!$this->canAccessWorkflowTask($attachment, $perms, $currentUserId)) {
            http_response_code(403);
            echo __t('workflow_task_attachment_download_forbidden');
            return;
        }

        $path = (string)($attachment['StoragePath'] ?? '');
        $safePath = $this->resolveWorkflowAttachmentPath($path);
        if ($safePath === null || !is_file($safePath) || !is_readable($safePath)) {
            http_response_code(404);
            echo __t('workflow_task_attachment_file_not_found');
            return;
        }

        $mimeType = trim((string)($attachment['MimeType'] ?? ''));
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string)filesize($safePath));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)($attachment['OriginalFileName'] ?? 'attachment')) . '"');
        readfile($safePath);
        exit;
    }

    public function deleteAttachment(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);
        $attachmentId = (int)($_POST['WorkflowTaskAttachmentID'] ?? 0);
        $context = $this->workflowRedirectContextFromPost();
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        $attachment = $tasksModel->getAttachment($attachmentId);
        $workflowTaskId = (int)($attachment['WorkflowTaskID'] ?? 0);
        if (!$attachment || $workflowTaskId <= 0) {
            $this->flashError(__t('workflow_task_attachment_not_found'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canAccessWorkflowTask($attachment, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_delete_attachment'));
            $this->redirectToWorkflowList($context);
        }
        if (!$this->canDeleteWorkflowTaskAttachment($attachment, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_task_permission_delete_attachment'));
            $this->redirectToWorkflowEdit($workflowTaskId, $context);
        }

        try {
            if (!$tasksModel->markAttachmentDeleted($attachmentId, $currentUserId)) {
                throw new \RuntimeException($tasksModel->getLastError() ?: __t('workflow_task_unknown_error'));
            }

            $safePath = $this->resolveWorkflowAttachmentPath((string)($attachment['StoragePath'] ?? ''));
            if ($safePath !== null && is_file($safePath)) {
                @unlink($safePath);
            }

            $originalName = (string)($attachment['OriginalFileName'] ?? 'attachment');
            $tasksModel->addActivity(
                $workflowTaskId,
                'ATTACHMENT_DELETED',
                __t('workflow_task_attachment_removed_activity', ['file' => $originalName]),
                $currentUserId,
                null,
                null,
                null,
                null,
                ['attachmentId' => $attachmentId]
            );

            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'ATTACHMENT_DELETE',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string)$workflowTaskId,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => [
                    'WorkflowTaskAttachmentID' => $attachmentId,
                    'OriginalFileName' => $originalName,
                ],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);

            $this->flashSuccess(__t('workflow_task_attachment_removed'));
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_task_attachment_delete_failed', ['msg' => $e->getMessage()]));
            app_log('Workflow task attachment delete failed', [
                'WorkflowTaskAttachmentID' => $attachmentId,
                'WorkflowTaskID' => $workflowTaskId,
                'UserID' => $currentUserId,
                'error' => $e->getMessage(),
            ], 'error');
        }

        $this->redirectToWorkflowEdit($workflowTaskId, $context);
    }

    /**
     * @param array<string, mixed> $attachment
     * @param array<int, string> $perms
     */
    private function canDeleteWorkflowTaskAttachment(array $attachment, array $perms, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }

        return (int)($attachment['UploadedByUserID'] ?? 0) === $userId
            || (int)($attachment['CreatedByUserID'] ?? 0) === $userId;
    }

    /**
     * @param array<string, mixed> $comment
     * @param array<int, string> $perms
     */
    private function canDeleteWorkflowTaskComment(array $comment, array $perms, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if ($this->canAdminWorkflowTasks($perms)) {
            return true;
        }

        return (int)($comment['CommentCreatedByUserID'] ?? 0) === $userId
            || (int)($comment['CreatedByUserID'] ?? 0) === $userId;
    }

    private function hasWorkflowTaskUploadPayload(string $inputName): bool
    {
        foreach ($this->normalizeWorkflowTaskUploadedFiles($inputName) as $file) {
            $name = trim((string)($file['name'] ?? ''));
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($name !== '' || $errorCode !== UPLOAD_ERR_NO_FILE) {
                return true;
            }
        }

        return false;
    }

    private function validateWorkflowTaskUploadedAttachments(string $inputName, bool $requireAtLeastOne = false): void
    {
        $seen = false;
        foreach ($this->normalizeWorkflowTaskUploadedFiles($inputName) as $file) {
            $originalName = trim((string)($file['name'] ?? ''));
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE && $originalName === '') {
                continue;
            }
            $seen = true;

            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new \RuntimeException($this->workflowUploadErrorMessage($errorCode));
            }

            $tmpPath = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                throw new \RuntimeException('Uploaded attachment payload is invalid.');
            }
            if ($size <= 0) {
                throw new \RuntimeException('Attachment "' . $this->safeWorkflowAttachmentOriginalName($originalName) . '" is empty.');
            }
            if ($size > $this->workflowAttachmentMaxBytes()) {
                throw new \RuntimeException('Attachment "' . $this->safeWorkflowAttachmentOriginalName($originalName) . '" exceeds the 25 MB limit.');
            }

            $extension = strtolower(pathinfo($this->safeWorkflowAttachmentOriginalName($originalName), PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, $this->workflowAttachmentAllowedExtensions(), true)) {
                throw new \RuntimeException('Attachment "' . $this->safeWorkflowAttachmentOriginalName($originalName) . '" has a file type that is not allowed.');
            }
        }

        if ($requireAtLeastOne && !$seen) {
            throw new \RuntimeException('Please choose a file to attach.');
        }
    }

    private function storeWorkflowTaskUploadedAttachments(
        WorkflowTaskModel $tasksModel,
        AuditModel $audit,
        int $workflowTaskID,
        int $currentUserId,
        string $inputName,
        bool $copyUploadedFile,
        bool $requireAtLeastOne
    ): int {
        if ($workflowTaskID <= 0 || $currentUserId <= 0) {
            throw new \RuntimeException(__t('workflow_task_attachment_store_context_required'));
        }

        $this->validateWorkflowTaskUploadedAttachments($inputName, $requireAtLeastOne);

        $uploadDir = $this->workflowAttachmentStorageRoot() . DIRECTORY_SEPARATOR . $workflowTaskID;
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException(__t('workflow_task_attachment_storage_create_failed'));
        }

        $uploadedCount = 0;
        foreach ($this->normalizeWorkflowTaskUploadedFiles($inputName) as $file) {
            $rawOriginalName = trim((string)($file['name'] ?? ''));
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE && $rawOriginalName === '') {
                continue;
            }

            $originalName = $this->safeWorkflowAttachmentOriginalName($rawOriginalName);
            $tmpPath = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedFileName = gmdate('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $storedFileName;

            $stored = $copyUploadedFile
                ? @copy($tmpPath, $targetPath)
                : move_uploaded_file($tmpPath, $targetPath);
            if (!$stored) {
                throw new \RuntimeException(__t('workflow_task_attachment_store_file_failed', ['file' => $originalName]));
            }

            $attachmentId = $tasksModel->saveAttachment($workflowTaskID, [
                'OriginalFileName' => $originalName,
                'StoredFileName' => $storedFileName,
                'StoragePath' => $targetPath,
                'MimeType' => $this->detectWorkflowAttachmentMimeType($targetPath),
                'FileSizeBytes' => $size,
                'UploadedByUserID' => $currentUserId,
            ]);

            if ($attachmentId <= 0) {
                @unlink($targetPath);
                throw new \RuntimeException($tasksModel->getLastError() ?: __t('workflow_task_attachment_metadata_save_failed'));
            }

            $tasksModel->addActivity(
                $workflowTaskID,
                'ATTACHMENT_UPLOADED',
                __t('workflow_task_attachment_uploaded_activity', ['file' => $originalName]),
                $currentUserId,
                null,
                null,
                null,
                null,
                ['attachmentId' => $attachmentId, 'fileSizeBytes' => $size]
            );

            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => 'ATTACHMENT_UPLOAD',
                'Entity' => 'WorkflowTask',
                'EntityKey' => (string)$workflowTaskID,
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => [
                    'WorkflowTaskAttachmentID' => $attachmentId,
                    'OriginalFileName' => $originalName,
                    'FileSizeBytes' => $size,
                ],
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);

            $uploadedCount++;
        }

        return $uploadedCount;
    }

    /**
     * @return array<int, array{name: mixed, type: mixed, tmp_name: mixed, error: mixed, size: mixed}>
     */
    private function normalizeWorkflowTaskUploadedFiles(string $inputName): array
    {
        $input = $_FILES[$inputName] ?? null;
        if (!is_array($input)) {
            return [];
        }

        if (!is_array($input['name'] ?? null)) {
            return [[
                'name' => $input['name'] ?? '',
                'type' => $input['type'] ?? '',
                'tmp_name' => $input['tmp_name'] ?? '',
                'error' => $input['error'] ?? UPLOAD_ERR_NO_FILE,
                'size' => $input['size'] ?? 0,
            ]];
        }

        $files = [];
        $names = $input['name'] ?? [];
        foreach ($names as $index => $name) {
            $files[] = [
                'name' => $name,
                'type' => is_array($input['type'] ?? null) ? ($input['type'][$index] ?? '') : '',
                'tmp_name' => is_array($input['tmp_name'] ?? null) ? ($input['tmp_name'][$index] ?? '') : '',
                'error' => is_array($input['error'] ?? null) ? ($input['error'][$index] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
                'size' => is_array($input['size'] ?? null) ? ($input['size'][$index] ?? 0) : 0,
            ];
        }

        return $files;
    }

    private function workflowAttachmentStorageRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'workflow-task-attachments';
    }

    /**
     * @return array<int, string>
     */
    private function workflowAttachmentAllowedExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip'];
    }

    private function workflowAttachmentMaxBytes(): int
    {
        return 25 * 1024 * 1024;
    }

    private function safeWorkflowAttachmentOriginalName(string $name): string
    {
        $name = trim(basename(str_replace('\\', '/', $name)));
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?? 'attachment';
        $name = trim($name, " .\t\n\r\0\x0B");
        return $name !== '' ? substr($name, 0, 255) : 'attachment';
    }

    private function detectWorkflowAttachmentMimeType(string $path): ?string
    {
        if (!is_file($path) || !function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $detected = finfo_file($finfo, $path);
        finfo_close($finfo);
        return is_string($detected) && trim($detected) !== '' ? $detected : null;
    }

    private function resolveWorkflowAttachmentPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $root = realpath($this->workflowAttachmentStorageRoot());
        $file = realpath($path);
        if ($root === false || $file === false) {
            return null;
        }

        $rootNormalized = rtrim(str_replace('\\', '/', strtolower($root)), '/') . '/';
        $fileNormalized = str_replace('\\', '/', strtolower($file));
        if (!str_starts_with($fileNormalized, $rootNormalized)) {
            return null;
        }

        return $file;
    }

    private function workflowUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __t('workflow_task_upload_too_large'),
            UPLOAD_ERR_PARTIAL => __t('workflow_task_upload_partial'),
            UPLOAD_ERR_NO_TMP_DIR => __t('workflow_task_upload_tmp_missing'),
            UPLOAD_ERR_CANT_WRITE => __t('workflow_task_upload_write_failed'),
            UPLOAD_ERR_EXTENSION => __t('workflow_task_upload_extension_stopped'),
            default => __t('workflow_task_upload_failed'),
        };
    }

    private function notifyAssigneeIfNeeded(
        \PDO $conn,
        UserModel $userModel,
        SystemSettingsModel $settingsModel,
        WorkflowTaskStatusModel $statusesModel,
        ?array $existingTask,
        array $data,
        int $id,
        string $action
    ): void {
        if (empty($data['AssignedToUserID'])) {
            return;
        }

        $statusName = $statusesModel->findNameById($data['StatusID']);
        $taskType = (new WorkflowTaskTypeModel($conn))->findNameById($data['TaskTypeID']);
        $assigned = $userModel->findById((int) $data['AssignedToUserID']);
        $createdBy = $userModel->findById((int) $data['CreatedByUserID']);
        if (!$assigned || empty($assigned['Email'])) {
            return;
        }

        $assigneeChanged = $existingTask && (int) ($existingTask['AssignedToUserID'] ?? 0) !== (int) $data['AssignedToUserID'];
        $statusChanged = $existingTask && (int) ($existingTask['StatusID'] ?? 0) !== (int) $data['StatusID'];
        $dueDateChanged = $existingTask && (string) ($existingTask['DueDate'] ?? '') !== (string) ($data['DueDate'] ?? '');
        $shouldNotify = $action === 'CREATE' || $assigneeChanged || $statusChanged || $dueDateChanged;

        if (!$shouldNotify) {
            return;
        }

        $taskKey = $id > 0 ? $id : $this->findLatestTaskIdForNotification($conn, $data);
        $subject = $action === 'CREATE'
            ? 'You have been assigned a new CBMS task'
            : ($assigneeChanged ? 'A CBMS task has been reassigned to you' : 'A CBMS task assigned to you has changed');

        $appUrl = $this->resolveWorkflowAppUrl($settingsModel);
        $taskUrl = $this->buildWorkflowTaskActionUrl($taskKey, $data, $appUrl);
        $linkLabel = $this->buildWorkflowTaskActionLabel($data);

        $body = '
            <p>Dear ' . htmlspecialchars($assigned['DisplayName'] ?? $assigned['Username']) . ',</p>
            <p>The following workflow task has been updated in CBMS:</p>
            <ul>
              <li><strong>Title:</strong> ' . htmlspecialchars($data['Title']) . '</li>
              <li><strong>Description:</strong><div>' . workflow_render_rich_text((string)$data['Description']) . '</div></li>
              <li><strong>Task Type:</strong> ' . htmlspecialchars($taskType ?? '-') . '</li>
              <li><strong>Status:</strong> ' . htmlspecialchars($statusName ?? '-') . '</li>
              <li><strong>Due Date:</strong> ' . htmlspecialchars($data['DueDate'] ?? '-') . '</li>
              <li><strong>Created By:</strong> ' . htmlspecialchars($createdBy['DisplayName'] ?? $createdBy['Username'] ?? '-') . '</li>
            </ul>
            <p><a href="' . htmlspecialchars($taskUrl) . '">' . htmlspecialchars($linkLabel) . '</a></p>
        ';

        try {
            $mailer = new MailService($conn);
            $from = trim((string) $settingsModel->get('EMAIL_ERROR_FROM', ''));
            $sent = $mailer->sendEmail(
                (string) $assigned['Email'],
                $subject,
                $body,
                $from !== '' ? $from : null
            );
            if ($sent && $taskKey > 0) {
                (new WorkflowTaskModel($conn))->addActivity(
                    $taskKey,
                    'ASSIGNEE_NOTIFICATION_SENT',
                    'Task notification email sent to ' . $this->workflowUserDisplayName($assigned) . '.',
                    (int)($data['UpdatedBy'] ?? $data['CreatedByUserID'] ?? 0),
                    null,
                    (int)$data['AssignedToUserID'],
                    null,
                    null,
                    [
                        'action' => $action,
                        'subject' => $subject,
                    ]
                );
            } elseif (!$sent) {
                app_log('[WorkflowController@save] Mail failed', [
                    'AssignedToUserID' => $data['AssignedToUserID'],
                    'Email' => $assigned['Email'] ?? null,
                    'error' => $mailer->getLastError(),
                ], 'error');
            }
        } catch (\Throwable $mailErr) {
            $this->flashError(__t('task_email_failed') . ': ' . $mailErr->getMessage());
            app_log('[WorkflowController@save] Mail failed', [
                'AssignedToUserID' => $data['AssignedToUserID'],
                'Email' => $assigned['Email'] ?? null,
                'error' => $mailErr->getMessage(),
            ], 'error');
        }
    }

    /**
     * @param array<string, mixed> $task
     */
    private function notifyTaskAudienceOfComment(
        \PDO $conn,
        UserModel $userModel,
        SystemSettingsModel $settingsModel,
        WorkflowTaskStatusModel $statusesModel,
        array $task,
        int $workflowTaskId,
        string $commentText,
        int $actionByUserId
    ): void {
        if ($workflowTaskId <= 0 || $commentText === '' || $actionByUserId <= 0) {
            return;
        }

        $recipientIds = [];
        foreach (['CreatedByUserID', 'AssignedToUserID'] as $key) {
            $recipientId = (int)($task[$key] ?? 0);
            if ($recipientId > 0 && $recipientId !== $actionByUserId) {
                $recipientIds[$recipientId] = $recipientId;
            }
        }
        if ($recipientIds === []) {
            return;
        }

        $actionBy = $userModel->findById($actionByUserId);
        $statusName = $statusesModel->findNameById((int)($task['StatusID'] ?? 0)) ?? (string)($task['StatusName'] ?? '-');
        $taskType = (new WorkflowTaskTypeModel($conn))->findNameById((int)($task['TaskTypeID'] ?? 0))
            ?? (string)($task['TaskTypeName'] ?? '-');
        $assigned = !empty($task['AssignedToUserID'])
            ? $userModel->findById((int)$task['AssignedToUserID'])
            : null;
        $appName = trim((string)$settingsModel->get('APP_NAME', 'CBMSv21'));
        if ($appName === '') {
            $appName = 'CBMSv21';
        }
        $appUrl = $this->resolveWorkflowAppUrl($settingsModel);
        $taskUrl = $this->buildWorkflowTaskActionUrl($workflowTaskId, $task, $appUrl);
        $templateModel = new EmailTemplateModel($conn);
        $mailer = new MailService($conn);
        $from = trim((string)$settingsModel->get('EMAIL_ERROR_FROM', ''));
        $commentAuthor = $actionBy ? $this->workflowUserDisplayName($actionBy) : 'CBMS';

        foreach ($recipientIds as $recipientId) {
            $recipient = $userModel->findById($recipientId);
            if (!$recipient || trim((string)($recipient['Email'] ?? '')) === '') {
                continue;
            }

            $tokens = [
                'APP_NAME' => $appName,
                'RECIPIENT_NAME' => $this->workflowUserDisplayName($recipient),
                'COMMENT_AUTHOR' => $commentAuthor,
                'COMMENT_TEXT' => $commentText,
                'COMMENT_HTML' => nl2br(htmlspecialchars($commentText, ENT_QUOTES, 'UTF-8')),
                'TASK_ID' => (string)$workflowTaskId,
                'TASK_TITLE' => (string)($task['Title'] ?? ''),
                'TASK_DESCRIPTION' => workflow_rich_text_to_plain_text((string)($task['Description'] ?? '')),
                'TASK_DESCRIPTION_HTML' => workflow_render_rich_text((string)($task['Description'] ?? '')),
                'TASK_TYPE' => $taskType,
                'TASK_STATUS' => $statusName,
                'TASK_PRIORITY' => $this->normalizeWorkflowPriorityCode($task['PriorityCode'] ?? 'NORMAL'),
                'TASK_DUE_DATE' => (string)($task['DueDate'] ?? '-'),
                'ASSIGNED_TO' => $assigned ? $this->workflowUserDisplayName($assigned) : '-',
                'TASK_URL' => $taskUrl,
                'TASK_LINK' => '<a href="' . htmlspecialchars($taskUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(__t('workflow_task_open_in_cbms'), ENT_QUOTES, 'UTF-8') . '</a>',
                'COMMENTED_AT' => $this->formatWorkflowNotificationDateTime(gmdate('Y-m-d H:i:s')),
            ];

            $rendered = $templateModel->render('WORKFLOW_TASK_COMMENT_ADDED', $tokens);
            if ($rendered === null) {
                $rendered = $this->fallbackWorkflowTaskCommentNotification($templateModel, $tokens);
            }

            try {
                $sent = $mailer->sendEmail(
                    trim((string)$recipient['Email']),
                    (string)($rendered['subject'] ?? ''),
                    (string)($rendered['html'] ?? ''),
                    $from !== '' ? $from : null
                );
                if ($sent) {
                    (new WorkflowTaskModel($conn))->addActivity(
                        $workflowTaskId,
                        'COMMENT_NOTIFICATION_SENT',
                        __t('workflow_task_comment_notification_activity', [
                            'recipient' => $this->workflowUserDisplayName($recipient),
                        ]),
                        $actionByUserId,
                        null,
                        $recipientId,
                        null,
                        null,
                        [
                            'templateKey' => 'WORKFLOW_TASK_COMMENT_ADDED',
                            'subject' => (string)($rendered['subject'] ?? ''),
                        ]
                    );
                } else {
                    app_log('Workflow task comment notification failed', [
                        'WorkflowTaskID' => $workflowTaskId,
                        'RecipientUserID' => $recipientId,
                        'Email' => $recipient['Email'] ?? null,
                        'error' => $mailer->getLastError(),
                    ], 'error');
                }
            } catch (\Throwable $mailErr) {
                app_log('Workflow task comment notification failed', [
                    'WorkflowTaskID' => $workflowTaskId,
                    'RecipientUserID' => $recipientId,
                    'Email' => $recipient['Email'] ?? null,
                    'error' => $mailErr->getMessage(),
                ], 'error');
            }
        }
    }

    private function notifyCreatorIfNeeded(
        \PDO $conn,
        UserModel $userModel,
        SystemSettingsModel $settingsModel,
        WorkflowTaskStatusModel $statusesModel,
        ?array $existingTask,
        array $data,
        int $id,
        string $event,
        int $actionByUserId
    ): void {
        if (!$existingTask || $id <= 0) {
            return;
        }

        $creatorId = (int)($existingTask['CreatedByUserID'] ?? $data['CreatedByUserID'] ?? 0);
        if ($creatorId <= 0 || $creatorId === $actionByUserId) {
            return;
        }

        $wasClosed = !empty($existingTask['CompletedAt'])
            || $statusesModel->isClosedStatusId((int)($existingTask['StatusID'] ?? 0));
        $isClosed = !empty($data['CompletedAt'])
            || $statusesModel->isClosedStatusId((int)($data['StatusID'] ?? 0));
        $isCompletionEvent = strtoupper($event) === 'COMPLETED' || (!$wasClosed && $isClosed);

        $notifyOnCompletion = array_key_exists('NotifyCreatorOnCompletion', $data)
            ? !empty($data['NotifyCreatorOnCompletion'])
            : !empty($existingTask['NotifyCreatorOnCompletion']);
        $notifyOnUpdate = array_key_exists('NotifyCreatorOnUpdate', $data)
            ? !empty($data['NotifyCreatorOnUpdate'])
            : !empty($existingTask['NotifyCreatorOnUpdate']);

        if ($isCompletionEvent) {
            if (!$notifyOnCompletion) {
                return;
            }
        } elseif (!$notifyOnUpdate) {
            return;
        }

        $changeSummary = $this->buildWorkflowTaskChangeSummary($conn, $userModel, $statusesModel, $existingTask, $data);
        if (!$isCompletionEvent && $changeSummary === []) {
            return;
        }

        $creator = $userModel->findById($creatorId);
        if (!$creator || trim((string)($creator['Email'] ?? '')) === '') {
            return;
        }

        $actionBy = $actionByUserId > 0 ? $userModel->findById($actionByUserId) : null;
        $assigned = !empty($data['AssignedToUserID'])
            ? $userModel->findById((int)$data['AssignedToUserID'])
            : null;
        $statusName = $statusesModel->findNameById((int)($data['StatusID'] ?? 0)) ?? (string)($data['StatusName'] ?? '-');
        $taskType = (new WorkflowTaskTypeModel($conn))->findNameById((int)($data['TaskTypeID'] ?? 0))
            ?? (string)($data['TaskTypeName'] ?? '-');
        $appName = trim((string)$settingsModel->get('APP_NAME', 'CBMSv21'));
        if ($appName === '') {
            $appName = 'CBMSv21';
        }
        $appUrl = $this->resolveWorkflowAppUrl($settingsModel);
        $taskUrl = $this->buildWorkflowTaskActionUrl($id, $data, $appUrl);

        $tokens = [
            'APP_NAME' => $appName,
            'CREATOR_NAME' => $this->workflowUserDisplayName($creator),
            'TASK_ID' => (string)$id,
            'TASK_TITLE' => (string)($data['Title'] ?? $existingTask['Title'] ?? ''),
            'TASK_DESCRIPTION' => workflow_rich_text_to_plain_text((string)($data['Description'] ?? $existingTask['Description'] ?? '')),
            'TASK_DESCRIPTION_HTML' => workflow_render_rich_text((string)($data['Description'] ?? $existingTask['Description'] ?? '')),
            'TASK_TYPE' => $taskType,
            'TASK_STATUS' => $statusName,
            'TASK_PRIORITY' => $this->normalizeWorkflowPriorityCode($data['PriorityCode'] ?? $existingTask['PriorityCode'] ?? 'NORMAL'),
            'TASK_DUE_DATE' => (string)($data['DueDate'] ?? $existingTask['DueDate'] ?? '-'),
            'ASSIGNED_TO' => $assigned ? $this->workflowUserDisplayName($assigned) : '-',
            'ACTION_BY' => $actionBy ? $this->workflowUserDisplayName($actionBy) : 'CBMS',
            'ACTION_LABEL' => $isCompletionEvent ? 'completed' : 'updated',
            'TASK_URL' => $taskUrl,
            'TASK_LINK' => '<a href="' . htmlspecialchars($taskUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(__t('workflow_task_open_in_cbms'), ENT_QUOTES, 'UTF-8') . '</a>',
            'COMPLETED_AT' => $this->formatWorkflowNotificationDateTime((string)($data['CompletedAt'] ?? '')),
            'UPDATED_AT' => $this->formatWorkflowNotificationDateTime(gmdate('Y-m-d H:i:s')),
            'CHANGE_SUMMARY' => $this->workflowChangeSummaryText($changeSummary),
            'CHANGE_SUMMARY_HTML' => $this->workflowChangeSummaryHtml($changeSummary),
        ];

        $templateKey = $isCompletionEvent ? 'WORKFLOW_TASK_COMPLETED' : 'WORKFLOW_TASK_UPDATED';
        $templateModel = new EmailTemplateModel($conn);
        $rendered = $templateModel->render($templateKey, $tokens);
        if ($rendered === null) {
            $rendered = $this->fallbackWorkflowTaskNotification($templateModel, $templateKey, $tokens);
        }

        try {
            $mailer = new MailService($conn);
            $from = trim((string)$settingsModel->get('EMAIL_ERROR_FROM', ''));
            $sent = $mailer->sendEmail(
                trim((string)$creator['Email']),
                (string)($rendered['subject'] ?? ''),
                (string)($rendered['html'] ?? ''),
                $from !== '' ? $from : null
            );
            if ($sent) {
                (new WorkflowTaskModel($conn))->addActivity(
                    $id,
                    $isCompletionEvent ? 'CREATOR_COMPLETION_NOTIFICATION_SENT' : 'CREATOR_UPDATE_NOTIFICATION_SENT',
                    ($isCompletionEvent ? 'Completion' : 'Update') . ' notification email sent to ' . $this->workflowUserDisplayName($creator) . '.',
                    $actionByUserId,
                    null,
                    $creatorId,
                    null,
                    null,
                    [
                        'templateKey' => $templateKey,
                        'subject' => (string)($rendered['subject'] ?? ''),
                    ]
                );
            } else {
                app_log('Workflow task creator notification failed', [
                    'WorkflowTaskID' => $id,
                    'TemplateKey' => $templateKey,
                    'CreatorUserID' => $creatorId,
                    'Email' => $creator['Email'] ?? null,
                    'error' => $mailer->getLastError(),
                ], 'error');
            }
        } catch (\Throwable $mailErr) {
            app_log('Workflow task creator notification failed', [
                'WorkflowTaskID' => $id,
                'TemplateKey' => $templateKey,
                'CreatorUserID' => $creatorId,
                'Email' => $creator['Email'] ?? null,
                'error' => $mailErr->getMessage(),
            ], 'error');
        }
    }

    /**
     * @return array<int, array{label: string, old: string, new: string}>
     */
    private function buildWorkflowTaskChangeSummary(
        \PDO $conn,
        UserModel $userModel,
        WorkflowTaskStatusModel $statusesModel,
        array $existingTask,
        array $data
    ): array {
        $changes = [];
        $this->addWorkflowTaskChange($changes, 'Title', $existingTask['Title'] ?? '', $data['Title'] ?? '');
        $this->addWorkflowTaskChange(
            $changes,
            'Description',
            workflow_rich_text_to_plain_text((string)($existingTask['Description'] ?? '')),
            workflow_rich_text_to_plain_text((string)($data['Description'] ?? ''))
        );
        $this->addWorkflowTaskChange($changes, 'Due Date', $existingTask['DueDate'] ?? '', $data['DueDate'] ?? '');
        $oldProject = (string)($existingTask['ProjectName'] ?? '');
        $newProject = '';
        if (!empty($data['WorkflowProjectID'])) {
            $project = (new WorkflowProjectModel($conn))->findProject((int)$data['WorkflowProjectID']);
            $newProject = $project ? (string)($project['ProjectName'] ?? '') : '';
        }
        $this->addWorkflowTaskChange($changes, 'Project', $oldProject, $newProject);
        $this->addWorkflowTaskChange($changes, 'Planned Start', $existingTask['PlannedStartDate'] ?? '', $data['PlannedStartDate'] ?? '');
        $this->addWorkflowTaskChange($changes, 'Planned End', $existingTask['PlannedEndDate'] ?? '', $data['PlannedEndDate'] ?? '');
        $this->addWorkflowTaskChange($changes, 'Percent Complete', $existingTask['PercentComplete'] ?? '', $data['PercentComplete'] ?? '');
        $this->addWorkflowTaskChange($changes, 'Project Utilisation', $existingTask['ProjectUtilisationPercent'] ?? '', $data['ProjectUtilisationPercent'] ?? '');
        $oldParentTask = (string)($existingTask['ParentWorkflowTaskTitle'] ?? '');
        $newParentTask = '';
        if (!empty($data['ParentWorkflowTaskID'])) {
            $parentTask = (new WorkflowTaskModel($conn))->find((int)$data['ParentWorkflowTaskID']);
            $newParentTask = $parentTask ? (string)($parentTask['Title'] ?? '') : '';
        }
        $this->addWorkflowTaskChange($changes, 'Parent Task', $oldParentTask, $newParentTask);
        $this->addWorkflowTaskChange(
            $changes,
            'Priority',
            $this->normalizeWorkflowPriorityCode($existingTask['PriorityCode'] ?? 'NORMAL'),
            $this->normalizeWorkflowPriorityCode($data['PriorityCode'] ?? 'NORMAL')
        );

        $oldStatus = (string)($existingTask['StatusName'] ?? $statusesModel->findNameById((int)($existingTask['StatusID'] ?? 0)) ?? '');
        $newStatus = (string)($statusesModel->findNameById((int)($data['StatusID'] ?? 0)) ?? $data['StatusName'] ?? '');
        $this->addWorkflowTaskChange($changes, 'Status', $oldStatus, $newStatus);

        $oldType = (string)($existingTask['TaskTypeName'] ?? '');
        $newType = (string)((new WorkflowTaskTypeModel($conn))->findNameById((int)($data['TaskTypeID'] ?? 0)) ?? $data['TaskTypeName'] ?? '');
        $this->addWorkflowTaskChange($changes, 'Task Type', $oldType, $newType);

        $oldAssigned = (string)($existingTask['AssignedToName'] ?? '');
        $newAssignedUser = !empty($data['AssignedToUserID']) ? $userModel->findById((int)$data['AssignedToUserID']) : null;
        $newAssigned = $newAssignedUser ? $this->workflowUserDisplayName($newAssignedUser) : '';
        $this->addWorkflowTaskChange($changes, 'Assigned To', $oldAssigned, $newAssigned);

        $this->addWorkflowTaskChange($changes, 'Related Entity', $existingTask['RelatedEntity'] ?? '', $data['RelatedEntity'] ?? '');
        $this->addWorkflowTaskChange($changes, 'Related Key', $existingTask['RelatedKey'] ?? '', $data['RelatedKey'] ?? '');

        return $changes;
    }

    /**
     * @param array<int, array{label: string, old: string, new: string}> $changes
     */
    private function addWorkflowTaskChange(array &$changes, string $label, $oldValue, $newValue): void
    {
        $old = trim((string)$oldValue);
        $new = trim((string)$newValue);
        if ($old === $new) {
            return;
        }

        $changes[] = [
            'label' => $label,
            'old' => $old !== '' ? $old : '-',
            'new' => $new !== '' ? $new : '-',
        ];
    }

    /**
     * @param array<int, array{label: string, old: string, new: string}> $changes
     */
    private function workflowChangeSummaryText(array $changes): string
    {
        if ($changes === []) {
            return 'No field-level changes were recorded.';
        }

        $lines = [];
        foreach ($changes as $change) {
            $lines[] = $change['label'] . ': ' . $change['old'] . ' -> ' . $change['new'];
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<int, array{label: string, old: string, new: string}> $changes
     */
    private function workflowChangeSummaryHtml(array $changes): string
    {
        if ($changes === []) {
            return '<p>No field-level changes were recorded.</p>';
        }

        $items = [];
        foreach ($changes as $change) {
            $items[] = '<li><strong>' . htmlspecialchars($change['label'], ENT_QUOTES, 'UTF-8') . ':</strong> '
                . htmlspecialchars($change['old'], ENT_QUOTES, 'UTF-8')
                . ' &rarr; '
                . htmlspecialchars($change['new'], ENT_QUOTES, 'UTF-8')
                . '</li>';
        }
        return '<ul>' . implode('', $items) . '</ul>';
    }

    /**
     * @param array<string, mixed> $user
     */
    private function workflowUserDisplayName(array $user): string
    {
        foreach (['DisplayName', 'FullName', 'Username', 'Email'] as $key) {
            $value = trim((string)($user[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return __t('user_number', ['id' => (int)($user['UserID'] ?? 0)]);
    }

    private function formatWorkflowNotificationDateTime(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '-';
        }
        $normalized = preg_replace('/(\.\d{6})\d+$/', '$1', $raw) ?? $raw;
        try {
            $utc = new \DateTimeImmutable($normalized, new \DateTimeZone('UTC'));
            return $utc->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            $ts = strtotime($raw . ' UTC');
            return $ts ? date('Y-m-d H:i', $ts) : $raw;
        }
    }

    /**
     * @param array<string, scalar|null> $tokens
     * @return array<string, string>
     */
    private function fallbackWorkflowTaskNotification(EmailTemplateModel $templateModel, string $templateKey, array $tokens): array
    {
        if ($templateKey === 'WORKFLOW_TASK_COMPLETED') {
            $subject = 'Task completed: {{TASK_TITLE}}';
            $html = '<p>Hello {{CREATOR_NAME}},</p>'
                . '<p>The workflow task <strong>{{TASK_TITLE}}</strong> has been completed by {{ACTION_BY}}.</p>'
                . '<p><strong>Status:</strong> {{TASK_STATUS}}<br><strong>Completed At:</strong> {{COMPLETED_AT}}</p>'
                . '<p>{{TASK_LINK}}</p>';
            $text = "Hello {{CREATOR_NAME}},\n\nThe workflow task {{TASK_TITLE}} has been completed by {{ACTION_BY}}.\n\nStatus: {{TASK_STATUS}}\nCompleted At: {{COMPLETED_AT}}\n\n{{TASK_URL}}";
        } else {
            $subject = 'Task updated: {{TASK_TITLE}}';
            $html = '<p>Hello {{CREATOR_NAME}},</p>'
                . '<p>The workflow task <strong>{{TASK_TITLE}}</strong> has been updated by {{ACTION_BY}}.</p>'
                . '{{CHANGE_SUMMARY_HTML}}'
                . '<p>{{TASK_LINK}}</p>';
            $text = "Hello {{CREATOR_NAME}},\n\nThe workflow task {{TASK_TITLE}} has been updated by {{ACTION_BY}}.\n\n{{CHANGE_SUMMARY}}\n\n{{TASK_URL}}";
        }

        return [
            'subject' => $templateModel->applyTokens($subject, $tokens, false),
            'html' => $templateModel->applyTokens($html, $tokens, true),
            'text' => $templateModel->applyTokens($text, $tokens, false),
        ];
    }

    /**
     * @param array<string, scalar|null> $tokens
     * @return array<string, string>
     */
    private function fallbackWorkflowTaskCommentNotification(EmailTemplateModel $templateModel, array $tokens): array
    {
        $subject = 'New note on task: {{TASK_TITLE}}';
        $html = '<p>Hello {{RECIPIENT_NAME}},</p>'
            . '<p>{{COMMENT_AUTHOR}} added a discussion note to workflow task <strong>{{TASK_TITLE}}</strong>.</p>'
            . '<div style="border-left:3px solid #d8e4f0;padding-left:12px;margin:12px 0;">{{COMMENT_HTML}}</div>'
            . '<p><strong>Status:</strong> {{TASK_STATUS}}<br><strong>Due Date:</strong> {{TASK_DUE_DATE}}</p>'
            . '<p>{{TASK_LINK}}</p>';
        $text = "Hello {{RECIPIENT_NAME}},\n\n{{COMMENT_AUTHOR}} added a discussion note to workflow task {{TASK_TITLE}}.\n\n{{COMMENT_TEXT}}\n\nStatus: {{TASK_STATUS}}\nDue Date: {{TASK_DUE_DATE}}\n\n{{TASK_URL}}";

        return [
            'subject' => $templateModel->applyTokens($subject, $tokens, false),
            'html' => $templateModel->applyTokens($html, $tokens, true),
            'text' => $templateModel->applyTokens($text, $tokens, false),
        ];
    }

    private function normalizeWorkflowReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);
        if ($returnTo === '' || preg_match('~[\r\n]~', $returnTo)) {
            return '';
        }
        if (preg_match('~^https?://~i', $returnTo)) {
            $parts = parse_url($returnTo);
            $path = (string)($parts['path'] ?? '');
            $query = (string)($parts['query'] ?? '');
            $fragment = (string)($parts['fragment'] ?? '');
            $indexPos = stripos($path, 'index.php');
            if ($indexPos === false) {
                return '';
            }
            $returnTo = substr($path, $indexPos);
            if ($query !== '') {
                $returnTo .= '?' . $query;
            }
            if ($fragment !== '') {
                $returnTo .= '#' . $fragment;
            }
        }
        if (str_starts_with($returnTo, '?')) {
            $returnTo = 'index.php' . $returnTo;
        }
        if (!str_starts_with($returnTo, 'index.php')) {
            $indexPos = stripos($returnTo, 'index.php');
            if ($indexPos !== false) {
                $returnTo = substr($returnTo, $indexPos);
            }
        }
        if (!str_starts_with($returnTo, 'index.php')) {
            return '';
        }

        $path = parse_url($returnTo, PHP_URL_PATH);
        if ($path !== 'index.php') {
            return '';
        }

        $query = parse_url($returnTo, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        $route = trim((string)($params['route'] ?? ''));
        $allowedRoutes = [
            'workflow/list',
            'workflow/edit',
            'workflow-projects/list',
            'workflow-projects/summary',
            'workflow-projects/form',
            'workflow-requirements/list',
            'workflow-requirements/summary',
            'workflow-requirements/matrix',
            'workflow-requirements/form',
            'workflow-issues/list',
            'workflow-issues/form',
        ];
        if (!in_array($route, $allowedRoutes, true)) {
            return '';
        }

        return $returnTo;
    }

    private function workflowRedirectContextFromPost(): array
    {
        $taskScope = strtolower(trim((string) ($_POST['task_scope'] ?? 'received')));
        if (!in_array($taskScope, ['received', 'created'], true)) {
            $taskScope = 'received';
        }

        return [
            'q' => (string) ($_POST['q'] ?? ''),
            'page' => max(1, (int) ($_POST['page'] ?? 1)),
            'pageSize' => max(1, (int) ($_POST['pageSize'] ?? 10)),
            'typeID' => ($_POST['typeID'] ?? '') !== '' ? (int) $_POST['typeID'] : null,
            'statusID' => ($_POST['statusID'] ?? '') !== '' ? (int) $_POST['statusID'] : null,
            'status' => (string) ($_POST['status'] ?? ''),
            'due_state' => $this->normalizeWorkflowDueState($_POST['due_state'] ?? ''),
            'workflowProjectID' => $this->workflowProjectIdFromArray($_POST) ?: null,
            'workflowRequirementID' => $this->workflowRequirementIdFromArray($_POST) ?: null,
            'mine' => ($_POST['mine'] ?? '') !== '' ? (int) $_POST['mine'] : null,
            'task_scope' => $taskScope,
            'assignedToUserID' => ($_POST['assignedToUserID'] ?? '') !== '' ? (int) $_POST['assignedToUserID'] : null,
            'iframe' => !empty($_POST['iframe']),
            'returnTo' => $this->normalizeWorkflowReturnTo((string)($_POST['returnTo'] ?? '')),
        ];
    }

    private function workflowRedirectContextFromGet(): array
    {
        $taskScope = strtolower(trim((string) ($_GET['task_scope'] ?? 'received')));
        if (!in_array($taskScope, ['received', 'created'], true)) {
            $taskScope = 'received';
        }

        return [
            'q' => (string) ($_GET['q'] ?? ''),
            'page' => max(1, (int) ($_GET['page'] ?? 1)),
            'pageSize' => max(1, (int) ($_GET['pageSize'] ?? 10)),
            'typeID' => ($_GET['typeID'] ?? '') !== '' ? (int) $_GET['typeID'] : null,
            'statusID' => ($_GET['statusID'] ?? '') !== '' ? (int) $_GET['statusID'] : null,
            'status' => (string) ($_GET['status'] ?? ''),
            'due_state' => $this->normalizeWorkflowDueState($_GET['due_state'] ?? ''),
            'workflowProjectID' => $this->workflowProjectIdFromArray($_GET) ?: null,
            'workflowRequirementID' => $this->workflowRequirementIdFromArray($_GET) ?: null,
            'mine' => ($_GET['mine'] ?? '') !== '' ? (int) $_GET['mine'] : null,
            'task_scope' => $taskScope,
            'assignedToUserID' => ($_GET['assignedToUserID'] ?? '') !== '' ? (int) $_GET['assignedToUserID'] : null,
            'iframe' => !empty($_GET['iframe']),
            'returnTo' => $this->normalizeWorkflowReturnTo((string)($_GET['returnTo'] ?? '')),
        ];
    }

    private function redirectToWorkflowEdit(int $id, array $context): void
    {
        $qs = [
            'route' => 'workflow/edit',
            'q' => $context['q'],
            'page' => $context['page'],
            'pageSize' => $context['pageSize'],
        ];
        if ($id > 0) {
            $qs['id'] = (string) $id;
        }
        if ($context['typeID'] !== null) {
            $qs['typeID'] = (string) $context['typeID'];
        }
        if ($context['statusID'] !== null) {
            $qs['statusID'] = (string) $context['statusID'];
        }
        if ($context['status'] !== '') {
            $qs['status'] = $context['status'];
        }
        if (!empty($context['due_state'])) {
            $qs['due_state'] = (string) $context['due_state'];
        }
        if (!empty($context['workflowProjectID'])) {
            $qs['workflowProjectID'] = (string) $context['workflowProjectID'];
        }
        if (!empty($context['workflowRequirementID'])) {
            $qs['workflowRequirementID'] = (string) $context['workflowRequirementID'];
        }
        if ($context['mine'] !== null) {
            $qs['mine'] = (string) $context['mine'];
        }
        if (!empty($context['task_scope'])) {
            $qs['task_scope'] = (string) $context['task_scope'];
        }
        if ($context['assignedToUserID'] !== null) {
            $qs['assignedToUserID'] = (string) $context['assignedToUserID'];
        }
        if ($context['iframe']) {
            $qs['iframe'] = '1';
        }
        if (!empty($context['returnTo'])) {
            $qs['returnTo'] = (string)$context['returnTo'];
        }

        header('Location: ' . $this->mergeLinkedContextIntoUrl('index.php?' . http_build_query($qs)));
        exit;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function workflowRequirementIdFromArray(array $source): int
    {
        foreach (['WorkflowRequirementID', 'workflowRequirementID', 'RequirementID', 'requirementID'] as $key) {
            if (($source[$key] ?? '') !== '') {
                $id = (int)$source[$key];
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function workflowProjectIdFromArray(array $source): int
    {
        foreach (['WorkflowProjectID', 'workflowProjectID'] as $key) {
            if (($source[$key] ?? '') !== '') {
                $id = (int)$source[$key];
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return 0;
    }

    private function redirectToWorkflowList(array $context): void
    {
        $qs = [
            'route' => 'workflow/list',
            'q' => $context['q'],
            'page' => $context['page'],
            'pageSize' => $context['pageSize'],
        ];
        if ($context['typeID'] !== null) {
            $qs['typeID'] = (string) $context['typeID'];
        }
        if ($context['statusID'] !== null) {
            $qs['statusID'] = (string) $context['statusID'];
        }
        if ($context['status'] !== '') {
            $qs['status'] = $context['status'];
        }
        if (!empty($context['due_state'])) {
            $qs['due_state'] = (string) $context['due_state'];
        }
        if (!empty($context['workflowProjectID'])) {
            $qs['workflowProjectID'] = (string) $context['workflowProjectID'];
        }
        if ($context['mine'] !== null) {
            $qs['mine'] = (string) $context['mine'];
        }
        if (!empty($context['task_scope'])) {
            $qs['task_scope'] = (string) $context['task_scope'];
        }
        if ($context['assignedToUserID'] !== null) {
            $qs['assignedToUserID'] = (string) $context['assignedToUserID'];
        }
        if ($context['iframe']) {
            $qs['iframe'] = '1';
        }

        header('Location: ' . $this->mergeLinkedContextIntoUrl('index.php?' . http_build_query($qs)));
        exit;
    }

    private function buildWorkflowTaskActionUrl(int $taskId, array $taskData, string $appUrl): string
    {
        $explicitUrl = trim((string) ($taskData['TaskUrl'] ?? ''));
        if ($explicitUrl !== '') {
            return $explicitUrl;
        }

        $baseParams = [
            'link_context' => 1,
            'fy' => (int) SessionHelper::get('FiscalYearID', 0),
            'ver' => (int) SessionHelper::get('VersionID', 0),
        ];

        $route = 'workflow/edit';
        $params = ['id' => $taskId];

        $qs = array_filter(
            ['route' => $route] + $params + $baseParams,
            static fn($v) => $v !== null && $v !== '' && $v !== 0
        );
        return $this->workflowPublicIndexUrl($appUrl) . '?' . http_build_query($qs);
    }

    private function resolveWorkflowAppUrl(SystemSettingsModel $settingsModel): string
    {
        $configured = trim((string)$settingsModel->get('APP_URL', ''));
        $requestUrl = $this->currentRequestAppUrl();

        if ($configured === '') {
            return $requestUrl !== '' ? $requestUrl : 'http://localhost/CBMSv21';
        }

        $configured = rtrim($configured, '/');
        if ($requestUrl !== '' && $this->shouldUseRequestAppUrl($configured, $requestUrl)) {
            return $requestUrl;
        }

        return $configured;
    }

    private function currentRequestAppUrl(): string
    {
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host === '') {
            return '';
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $script = str_replace('\\', '/', (string)($requestPath ?: ($_SERVER['SCRIPT_NAME'] ?? '/backend-php/public/index.php')));
        $marker = '/backend-php/public/index.php';
        $pos = stripos($script, $marker);
        $basePath = $pos !== false ? substr($script, 0, $pos) : '';

        return rtrim($scheme . '://' . $host . $basePath, '/');
    }

    private function shouldUseRequestAppUrl(string $configuredUrl, string $requestUrl): bool
    {
        $configured = parse_url($configuredUrl);
        $request = parse_url($requestUrl);

        $configuredHost = strtolower((string)($configured['host'] ?? ''));
        $requestHost = strtolower((string)($request['host'] ?? ''));
        if ($configuredHost === '' || $requestHost === '') {
            return false;
        }

        if ($this->isLoopbackHost($configuredHost) && !$this->isLoopbackHost($requestHost)) {
            return true;
        }

        if ($configuredHost === $requestHost) {
            $configuredPort = (int)($configured['port'] ?? 0);
            $requestPort = (int)($request['port'] ?? 0);
            $configuredPath = rtrim((string)($configured['path'] ?? ''), '/');
            $requestPath = rtrim((string)($request['path'] ?? ''), '/');

            return $configuredPort !== $requestPort || $configuredPath !== $requestPath;
        }

        return false;
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = trim(strtolower($host), '[]');
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function workflowPublicIndexUrl(string $appUrl): string
    {
        $base = rtrim($appUrl, '/');
        $lower = strtolower($base);
        if (str_ends_with($lower, '/backend-php/public/index.php')) {
            return $base;
        }
        if (str_ends_with($lower, '/backend-php/public')) {
            return $base . '/index.php';
        }

        return $base . '/backend-php/public/index.php';
    }

    private function buildWorkflowTaskActionLabel(array $taskData): string
    {
        $explicitLabel = trim((string) ($taskData['TaskLinkLabel'] ?? ''));
        if ($explicitLabel !== '') {
            return $explicitLabel;
        }

        $entity = strtoupper(trim((string) ($taskData['RelatedEntity'] ?? '')));
        return match ($entity) {
            'STRATEGICFUNDINGSUBMISSION', 'FUNDINGSUBMISSION' => __t('workflow_task_open_funding_request_in_cbms'),
            'STRATEGICSEGMENTPUBLISHREQUEST', 'SEGMENTPUBLISHREQUEST' => __t('workflow_task_open_publication_request_in_cbms'),
            default => __t('workflow_task_open_in_cbms'),
        };
    }

    private function findLatestTaskIdForNotification(\PDO $conn, array $taskData): int
    {
        try {
            $sql = "
                SELECT TOP 1 WorkflowTaskID
                FROM dbo.tblWorkflowTasks
                WHERE CreatedByUserID = :createdBy
                  AND AssignedToUserID = :assignedTo
                  AND Title = :title
                ORDER BY WorkflowTaskID DESC
            ";
            $st = $conn->prepare($sql);
            $st->execute([
                ':createdBy' => (int) ($taskData['CreatedByUserID'] ?? 0),
                ':assignedTo' => (int) ($taskData['AssignedToUserID'] ?? 0),
                ':title' => (string) ($taskData['Title'] ?? ''),
            ]);
            return (int) ($st->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
