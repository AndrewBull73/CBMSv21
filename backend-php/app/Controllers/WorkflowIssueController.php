<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Rbac;
use App\Models\UserModel;
use App\Models\WorkflowIssueModel;
use App\Models\WorkflowLinkModel;
use App\Models\WorkflowProjectModel;
use App\Models\WorkflowRequirementModel;
use App\Models\WorkflowTaskModel;
use App\Models\WorkflowTaskStatusModel;
use App\Models\WorkflowTaskTypeModel;
use App\Shared\SessionHelper;

final class WorkflowIssueController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_VIEW', 'WORKFLOW_ISSUES_CREATE', 'WORKFLOW_ISSUES_EDIT', 'WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_VIEW', 'WORKFLOW_ISSUES_CREATE', 'WORKFLOW_ISSUES_EDIT', 'WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'exportExcel' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_VIEW', 'WORKFLOW_ISSUES_CREATE', 'WORKFLOW_ISSUES_EDIT', 'WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_VIEW', 'WORKFLOW_ISSUES_CREATE', 'WORKFLOW_ISSUES_EDIT', 'WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_CREATE', 'WORKFLOW_ISSUES_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'delete' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_VIEW', 'WORKFLOW_ISSUES_CREATE', 'WORKFLOW_ISSUES_EDIT', 'WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'createTask' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'uploadAttachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'downloadAttachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_VIEW', 'WORKFLOW_ISSUES_CREATE', 'WORKFLOW_ISSUES_EDIT', 'WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'deleteAttachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_ISSUES_EDIT', 'WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function list(): void
    {
        $model = new WorkflowIssueModel($this->db);
        $projectModel = new WorkflowProjectModel($this->db);
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
            'severity' => strtoupper(trim((string)($_GET['severity'] ?? ''))),
            'workflowProjectID' => $this->workflowProjectFilterFromRequest(),
            'active' => trim((string)($_GET['active'] ?? '1')),
        ];

        $this->render('workflow/WorkflowIssueList', [
            'title' => __t('workflow_issues'),
            'rows' => $model->listIssues($filters),
            'filters' => $filters,
            'statusOptions' => $model->statusOptions(),
            'severityOptions' => $model->severityOptions(),
            'typeOptions' => $model->typeOptions(),
            'workflowProjects' => $projectModel->supportsWorkflowProjects() ? $projectModel->listActiveProjects() : [],
            'tableInstalled' => $model->supportsIssues(),
            'canCreateIssue' => $this->canCreateIssues(),
            'canEditIssue' => $this->canEditIssues(),
            'canDeleteIssue' => $this->canDeleteIssues(),
            'currentUserId' => (int)SessionHelper::get('auth.user_id', 0),
            'canCreateWorkflowTask' => $this->canCreateWorkflowTasks(),
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function exportExcel(): void
    {
        $model = new WorkflowIssueModel($this->db);
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
            'severity' => strtoupper(trim((string)($_GET['severity'] ?? ''))),
            'workflowProjectID' => $this->workflowProjectFilterFromRequest(),
            'active' => trim((string)($_GET['active'] ?? '1')),
        ];
        $statusOptions = $model->statusOptions();
        $severityOptions = $model->severityOptions();
        $label = static function (?string $code, array $options): string {
            $code = strtoupper(trim((string)$code));
            $key = $options[$code] ?? '';
            return $key !== '' ? __t($key) : $code;
        };

        $this->downloadExcel('Workflow Issues', 'WorkflowIssues', [
            ['label' => 'Issue Code', 'key' => 'IssueCode'],
            ['label' => __t('workflow_issue'), 'key' => 'IssueTitle'],
            ['label' => __t('workflow_project_project'), 'key' => 'ProjectName'],
            ['label' => __t('workflow_requirement'), 'value' => static fn(array $row): string => trim((string)($row['RequirementCode'] ?? '') . ' ' . (string)($row['RequirementTitle'] ?? ''))],
            ['label' => __t('workflow_issue_severity'), 'value' => static fn(array $row): string => $label((string)($row['SeverityCode'] ?? ''), $severityOptions)],
            ['label' => __t('status'), 'value' => static fn(array $row): string => $label((string)($row['IssueStatusCode'] ?? ''), $statusOptions)],
            ['label' => __t('workflow_issue_owner'), 'key' => 'OwnerName'],
            ['label' => __t('workflow_issue_raised_by'), 'key' => 'RaisedByName'],
            ['label' => __t('workflow_issue_raised_at'), 'key' => 'RaisedAt'],
            ['label' => __t('workflow_issue_due_date'), 'key' => 'DueDate'],
            ['label' => __t('workflow_project_tasks'), 'key' => 'TaskCount'],
            ['label' => __t('workflow_task_open'), 'key' => 'OpenTaskCount'],
            ['label' => 'Active', 'value' => static fn(array $row): string => (int)($row['Active'] ?? 0) === 1 ? 'Yes' : 'No'],
        ], $model->listIssues($filters));
    }

    public function form(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $returnTo = $this->normalizeIssueReturnTo((string)($_GET['returnTo'] ?? ''));
        $backUrl = $this->defaultIssueBackUrl($returnTo);
        $model = new WorkflowIssueModel($this->db);
        $projectModel = new WorkflowProjectModel($this->db);
        $requirementModel = new WorkflowRequirementModel($this->db);
        $linkModel = new WorkflowLinkModel($this->db);
        $userModel = new UserModel($this->db);
        $tableInstalled = $model->supportsIssues();

        $record = $tableInstalled && $id > 0 ? $model->findIssue($id) : null;
        if ($id > 0 && $tableInstalled && !$record) {
            $this->flashError(__t('workflow_issue_not_found'));
            header('Location: ' . $backUrl);
            return;
        }
        if ($id <= 0 && !$this->canCreateIssues()) {
            $this->flashError(__t('workflow_issue_permission_create'));
            header('Location: ' . $backUrl);
            return;
        }

        if ($id <= 0) {
            $record = [
                'WorkflowProjectID' => $this->workflowProjectFilterFromRequest(),
                'WorkflowRequirementID' => (int)($_GET['workflowRequirementID'] ?? 0),
                'IssueTypeCode' => '',
                'SeverityCode' => 'MEDIUM',
                'PriorityCode' => 'SHOULD',
                'IssueStatusCode' => 'OPEN',
                'RaisedByUserID' => (int)SessionHelper::get('auth.user_id', 0),
                'RaisedAt' => gmdate('Y-m-d H:i:s'),
                'Active' => 1,
            ];
            if ((int)$record['WorkflowRequirementID'] > 0) {
                $requirement = $requirementModel->findRequirement((int)$record['WorkflowRequirementID']);
                if ($requirement) {
                    $record['WorkflowProjectID'] = (int)($requirement['WorkflowProjectID'] ?? 0);
                }
            }
        }

        $workflowProjectID = (int)($record['WorkflowProjectID'] ?? 0);
        if ($workflowProjectID > 0) {
            $this->rememberWorkflowProjectContext($workflowProjectID);
        }
        $selectedWorkflowProject = $workflowProjectID > 0 && $projectModel->supportsWorkflowProjects()
            ? $projectModel->findProject($workflowProjectID)
            : null;
        $workflowRequirements = [];
        if ($requirementModel->supportsRequirements()) {
            $workflowRequirements = $requirementModel->listRequirements(['active' => '1']);
        }
        $this->render('workflow/WorkflowIssueForm', [
            'title' => $id > 0 ? __t('workflow_issue_edit') : __t('workflow_issue_create'),
            'record' => $record,
            'workflowProjects' => $projectModel->supportsWorkflowProjects() ? $projectModel->listActiveProjects() : [],
            'selectedWorkflowProject' => $selectedWorkflowProject,
            'workflowRequirements' => $workflowRequirements,
            'users' => method_exists($userModel, 'listAll') ? $userModel->listAll() : [],
            'issueTaskLinks' => $id > 0 ? $linkModel->listIssueLinks($id) : [],
            'issueAttachmentsInstalled' => $model->supportsIssueAttachments(),
            'issueAttachments' => $id > 0 && $model->supportsIssueAttachments() ? $model->listAttachments($id) : [],
            'statusOptions' => $model->statusOptions(),
            'severityOptions' => $model->severityOptions(),
            'typeOptions' => $model->typeOptions(),
            'priorityOptions' => $model->priorityOptions(),
            'tableInstalled' => $tableInstalled,
            'returnTo' => $returnTo,
            'backUrl' => $backUrl,
            'canSaveIssue' => $id > 0 ? $this->canEditIssues() : $this->canCreateIssues(),
            'canDeleteIssue' => $this->canDeleteIssueRecord($record),
            'canCreateWorkflowTask' => $this->canCreateWorkflowTasks(),
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function save(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-issues/list');

        $returnTo = $this->normalizeIssueReturnTo((string)($_POST['returnTo'] ?? ''));
        $model = new WorkflowIssueModel($this->db);
        if (!$model->supportsIssues()) {
            $this->flashError(__t('workflow_issue_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: ' . $this->defaultIssueBackUrl($returnTo));
            return;
        }

        $id = (int)($_POST['WorkflowIssueID'] ?? 0);
        $before = $id > 0 ? $model->findIssue($id) : null;
        if ($id > 0 && !$before) {
            $this->flashError(__t('workflow_issue_not_found'));
            header('Location: ' . $this->defaultIssueBackUrl($returnTo));
            return;
        }
        if ($id <= 0 && !$this->canCreateIssues()) {
            $this->flashError(__t('workflow_issue_permission_create'));
            header('Location: ' . $this->defaultIssueBackUrl($returnTo));
            return;
        }
        if ($id > 0 && !$this->canEditIssues()) {
            $this->flashError(__t('workflow_issue_permission_edit'));
            header('Location: ' . $this->issueFormRedirectUrl($id, $returnTo));
            return;
        }

        $payload = [
            'WorkflowIssueID' => $id,
            'WorkflowProjectID' => ($_POST['WorkflowProjectID'] ?? '') !== '' ? (int)$_POST['WorkflowProjectID'] : null,
            'WorkflowRequirementID' => ($_POST['WorkflowRequirementID'] ?? '') !== '' ? (int)$_POST['WorkflowRequirementID'] : null,
            'IssueCode' => trim((string)($_POST['IssueCode'] ?? '')),
            'IssueTitle' => trim((string)($_POST['IssueTitle'] ?? '')),
            'IssueDescription' => trim((string)($_POST['IssueDescription'] ?? '')),
            'IssueTypeCode' => strtoupper(trim((string)($_POST['IssueTypeCode'] ?? ''))),
            'SeverityCode' => strtoupper(trim((string)($_POST['SeverityCode'] ?? 'MEDIUM'))),
            'PriorityCode' => strtoupper(trim((string)($_POST['PriorityCode'] ?? 'SHOULD'))),
            'IssueStatusCode' => strtoupper(trim((string)($_POST['IssueStatusCode'] ?? 'OPEN'))),
            'RaisedByUserID' => ($_POST['RaisedByUserID'] ?? '') !== '' ? (int)$_POST['RaisedByUserID'] : null,
            'OwnerUserID' => ($_POST['OwnerUserID'] ?? '') !== '' ? (int)$_POST['OwnerUserID'] : null,
            'RaisedAt' => trim((string)($_POST['RaisedAt'] ?? '')),
            'DueDate' => trim((string)($_POST['DueDate'] ?? '')),
            'ResolvedAt' => trim((string)($_POST['ResolvedAt'] ?? '')),
            'ResolutionSummary' => trim((string)($_POST['ResolutionSummary'] ?? '')),
            'Active' => isset($_POST['Active']) && (string)$_POST['Active'] !== '0' ? 1 : 0,
        ];

        if (!empty($payload['WorkflowRequirementID'])) {
            $requirementModel = new WorkflowRequirementModel($this->db);
            $requirement = $requirementModel->findRequirement((int)$payload['WorkflowRequirementID']);
            if ($requirement) {
                $payload['WorkflowProjectID'] = (int)($requirement['WorkflowProjectID'] ?? 0) ?: $payload['WorkflowProjectID'];
            }
        }

        if ($payload['IssueTitle'] === '') {
            $this->flashError(__t('workflow_issue_title_required'));
            header('Location: ' . $this->issueFormRedirectUrl($id, $returnTo));
            return;
        }
        if ($payload['IssueTypeCode'] === '') {
            $this->flashError(__t('workflow_issue_type_required'));
            header('Location: ' . $this->issueFormRedirectUrl($id, $returnTo));
            return;
        }
        if ($id > 0 && (int)($before['Active'] ?? 0) === 1 && $payload['Active'] === 0 && !$this->canDeleteIssueRecord($before)) {
            $this->flashError(__t('workflow_issue_permission_delete'));
            header('Location: ' . $this->issueFormRedirectUrl($id, $returnTo));
            return;
        }

        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        try {
            $savedId = $model->saveIssue($payload, $currentUserId);
            if (!empty($payload['WorkflowProjectID'])) {
                $this->rememberWorkflowProjectContext((int)$payload['WorkflowProjectID']);
            }
            $this->auditEvent($id > 0 ? 'UPDATE' : 'CREATE', 'WorkflowIssue', (string)$savedId, [
                'IssueCode' => $payload['IssueCode'],
                'IssueTitle' => $payload['IssueTitle'],
                'IssueStatusCode' => $payload['IssueStatusCode'],
                'SeverityCode' => $payload['SeverityCode'],
            ]);
            $this->flashSuccess($id > 0 ? __t('workflow_issue_updated') : __t('workflow_issue_created'));
            header('Location: ' . ($returnTo !== '' ? $returnTo : 'index.php?route=workflow-issues/form&id=' . $savedId));
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowIssueController::save failed', $e, [
                'WorkflowIssueID' => $id,
                'IssueTitle' => $payload['IssueTitle'],
            ]);
            $this->flashError(__t('workflow_issue_save_failed') . ': ' . $e->getMessage());
            header('Location: ' . $this->issueFormRedirectUrl($id, $returnTo));
            return;
        }
    }

    public function delete(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-issues/list');

        $returnTo = $this->normalizeIssueReturnTo((string)($_POST['returnTo'] ?? ''));
        $redirect = $this->defaultIssueBackUrl($returnTo);
        $id = (int)($_POST['WorkflowIssueID'] ?? 0);
        $model = new WorkflowIssueModel($this->db);
        $record = $id > 0 ? $model->findIssue($id) : null;
        if (!$record) {
            $this->flashError(__t('workflow_issue_not_found'));
            header('Location: ' . $redirect);
            return;
        }
        if (!$this->canDeleteIssueRecord($record)) {
            $this->flashError(__t('workflow_issue_permission_delete'));
            header('Location: ' . $redirect);
            return;
        }

        try {
            if (!$model->archiveIssue($id, (int)SessionHelper::get('auth.user_id', 0))) {
                throw new \RuntimeException($model->getLastError() ?: __t('workflow_task_unknown_error'));
            }
            $this->auditEvent('DELETE', 'WorkflowIssue', (string)$id, [
                'IssueCode' => (string)($record['IssueCode'] ?? ''),
                'IssueTitle' => (string)($record['IssueTitle'] ?? ''),
            ]);
            $this->flashSuccess(__t('workflow_issue_deleted'));
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowIssueController::delete failed', $e, ['WorkflowIssueID' => $id]);
            $this->flashError(__t('workflow_issue_delete_failed') . ': ' . $e->getMessage());
        }

        header('Location: ' . $redirect);
    }

    public function createTask(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-issues/list');

        $issueID = (int)($_POST['WorkflowIssueID'] ?? 0);
        $issueModel = new WorkflowIssueModel($this->db);
        $projectModel = new WorkflowProjectModel($this->db);
        $taskModel = new WorkflowTaskModel($this->db);
        $statusModel = new WorkflowTaskStatusModel($this->db);
        $typeModel = new WorkflowTaskTypeModel($this->db);
        $linkModel = new WorkflowLinkModel($this->db);
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        $record = $issueID > 0 ? $issueModel->findIssue($issueID) : null;
        $redirect = $issueID > 0 ? 'index.php?route=workflow-issues/form&id=' . $issueID : 'index.php?route=workflow-issues/list';

        if (!$record) {
            $this->flashError(__t('workflow_issue_not_found'));
            header('Location: index.php?route=workflow-issues/list');
            return;
        }
        if (!$this->canCreateWorkflowTasks()) {
            $this->flashError(__t('workflow_task_permission_create'));
            header('Location: ' . $redirect);
            return;
        }

        $workflowProjectID = (int)($record['WorkflowProjectID'] ?? 0);
        if ($workflowProjectID <= 0) {
            $this->flashError(__t('workflow_issue_task_requires_project'));
            header('Location: ' . $redirect);
            return;
        }
        if (!$linkModel->supportsWorkflowLinks()) {
            $this->flashError(__t('workflow_issue_task_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: ' . $redirect);
            return;
        }
        $project = $projectModel->findProject($workflowProjectID);
        if (!$project) {
            $this->flashError(__t('workflow_project_not_found'));
            header('Location: ' . $redirect);
            return;
        }

        $taskTypeID = (int)($typeModel->findIdByCode('PROJECT_TASK') ?? 0);
        $statusID = (int)($statusModel->findOpenStatusId() ?? 0);
        if ($taskTypeID <= 0 || $statusID <= 0) {
            $this->flashError(__t('workflow_issue_task_status_missing'));
            header('Location: ' . $redirect);
            return;
        }

        $taskTitle = trim((string)($_POST['TaskTitle'] ?? ''));
        if ($taskTitle === '') {
            $taskTitle = $this->defaultIssueTaskTitle($record);
        }
        $assignedToUserID = (int)($_POST['AssignedToUserID'] ?? 0);
        if ($assignedToUserID <= 0) {
            $assignedToUserID = (int)($record['OwnerUserID'] ?? 0) ?: $currentUserId;
        }
        if ($assignedToUserID <= 0) {
            $this->flashError(__t('workflow_issue_task_assignee_required'));
            header('Location: ' . $redirect);
            return;
        }
        $dueDate = $this->normalizeIssueDate($_POST['TaskDueDate'] ?? null);
        if ($dueDate === '') {
            $dueDate = $this->normalizeIssueDate($record['DueDate'] ?? null) ?: gmdate('Y-m-d', strtotime('+14 days') ?: time());
        }
        $projectStartDate = $this->normalizeIssueDate($project['StartDate'] ?? null);
        $projectEndDate = $this->normalizeIssueDate($project['TargetEndDate'] ?? null);
        if ($projectStartDate !== '' && $dueDate < $projectStartDate) {
            $this->flashError(__t('workflow_issue_task_date_before_project', ['date' => $projectStartDate]));
            header('Location: ' . $redirect);
            return;
        }
        if ($projectEndDate !== '' && $dueDate > $projectEndDate) {
            $this->flashError(__t('workflow_issue_task_date_after_project', ['date' => $projectEndDate]));
            header('Location: ' . $redirect);
            return;
        }

        $plannedStartDate = gmdate('Y-m-d');
        if ($projectStartDate !== '' && $plannedStartDate < $projectStartDate) {
            $plannedStartDate = $projectStartDate;
        }
        if ($plannedStartDate > $dueDate) {
            $plannedStartDate = $dueDate;
        }

        $issueCode = trim((string)($record['IssueCode'] ?? $issueModel->defaultIssueCode($issueID)));
        $issueTitle = trim((string)($record['IssueTitle'] ?? ''));
        $taskData = [
            'TaskTypeID' => $taskTypeID,
            'StatusID' => $statusID,
            'Title' => substr($taskTitle, 0, 255),
            'Description' => $this->buildIssueTaskDescription($record),
            'CreatedByUserID' => $currentUserId,
            'AssignedToUserID' => $assignedToUserID,
            'RelatedEntity' => 'WorkflowIssue',
            'RelatedKey' => $issueCode !== '' ? $issueCode : (string)$issueID,
            'PriorityCode' => $this->priorityCodeForIssueTask((string)($record['PriorityCode'] ?? 'SHOULD')),
            'DueDate' => $dueDate,
            'WorkflowProjectID' => $workflowProjectID,
            'ParentWorkflowTaskID' => null,
            'PlannedStartDate' => $plannedStartDate,
            'PlannedEndDate' => $dueDate,
            'PercentComplete' => 0,
            'ProjectUtilisationPercent' => 0,
            'CompletedAt' => null,
            'NotifyCreatorOnCompletion' => 1,
            'NotifyCreatorOnUpdate' => 0,
            'NotifyAudienceOnComment' => 1,
            'AutoReminderEnabled' => 0,
            'AutoReminderDaysBeforeDue' => 1,
            'OverdueEscalationEnabled' => 0,
            'OverdueEscalationDaysAfterDue' => 1,
            'WorkflowTaskBatchID' => null,
            'WorkflowTaskCompletionRule' => 'INDIVIDUAL',
        ];

        $startedTransaction = false;
        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }
            $workflowTaskID = $taskModel->createAndReturnId($taskData);
            if ($workflowTaskID <= 0) {
                throw new \RuntimeException($taskModel->getLastError() ?: __t('workflow_task_insert_failed'));
            }
            $workflowLinkID = $linkModel->saveTaskLink([
                'WorkflowProjectID' => $workflowProjectID,
                'WorkflowTaskID' => $workflowTaskID,
                'LinkTypeCode' => 'ISSUE',
                'LinkedEntity' => 'WorkflowIssue',
                'LinkedEntityID' => $issueID,
                'LinkedEntityKey' => $issueCode,
                'LinkedTitle' => $issueTitle !== '' ? $issueTitle : $taskTitle,
                'LinkedUrl' => 'index.php?route=workflow-issues/form&id=' . $issueID,
                'Notes' => __t('workflow_issue_task_link_note'),
            ], $currentUserId);
            $taskModel->addActivity($workflowTaskID, 'LINK_ADDED', __t('workflow_issue_task_created_activity', [
                'code' => $issueCode !== '' ? $issueCode : (string)$issueID,
                'title' => $issueTitle !== '' ? $issueTitle : $taskTitle,
            ]), $currentUserId, null, null, null, null, [
                'workflowLinkID' => $workflowLinkID,
                'workflowIssueID' => $issueID,
                'source' => 'ISSUE_CREATE_TASK',
            ]);
            if ($startedTransaction) {
                $this->db->commit();
            }
            $this->auditEvent('CREATE_TASK_FROM_ISSUE', 'WorkflowIssue', (string)$issueID, [
                'WorkflowTaskID' => $workflowTaskID,
                'WorkflowProjectID' => $workflowProjectID,
                'IssueCode' => $issueCode,
                'TaskTitle' => $taskTitle,
            ]);
            $this->flashSuccess(__t('workflow_issue_task_created', ['task' => $taskTitle]));
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logHandledException('WorkflowIssueController::createTask failed', $e, ['WorkflowIssueID' => $issueID]);
            $this->flashError(__t('workflow_issue_task_create_failed') . ': ' . $e->getMessage());
        }

        header('Location: ' . $redirect);
    }

    public function uploadAttachment(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-issues/list');

        $model = new WorkflowIssueModel($this->db);
        $id = (int)($_POST['WorkflowIssueID'] ?? 0);
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        $record = $id > 0 ? $model->findIssue($id) : null;

        if (!$record) {
            $this->flashError(__t('workflow_issue_not_found'));
            header('Location: index.php?route=workflow-issues/list');
            return;
        }
        if (!$this->canEditIssues()) {
            $this->flashError(__t('workflow_issue_permission_edit'));
            header('Location: index.php?route=workflow-issues/form&id=' . $id);
            return;
        }
        if (!$model->supportsIssueAttachments()) {
            $this->flashError(__t('workflow_issue_attachments_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: index.php?route=workflow-issues/form&id=' . $id);
            return;
        }

        try {
            $uploadedCount = $this->storeIssueUploadedAttachments($model, $id, $currentUserId, 'IssueAttachment', true);
            $this->flashSuccess($uploadedCount === 1
                ? __t('workflow_issue_attachment_uploaded')
                : __t('workflow_issue_attachments_uploaded', ['count' => $uploadedCount])
            );
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_issue_attachment_upload_failed', ['msg' => $e->getMessage()]));
            app_log('Workflow issue attachment upload failed', [
                'WorkflowIssueID' => $id,
                'UserID' => $currentUserId,
                'error' => $e->getMessage(),
            ], 'error');
        }

        header('Location: index.php?route=workflow-issues/form&id=' . $id);
    }

    public function downloadAttachment(): void
    {
        $model = new WorkflowIssueModel($this->db);
        $attachmentId = (int)($_GET['id'] ?? 0);
        $attachment = $model->getAttachment($attachmentId);
        if (!$attachment) {
            http_response_code(404);
            echo __t('workflow_issue_attachment_not_found');
            return;
        }

        $safePath = $this->resolveIssueAttachmentPath((string)($attachment['StoragePath'] ?? ''));
        if ($safePath === null || !is_file($safePath) || !is_readable($safePath)) {
            http_response_code(404);
            echo __t('workflow_issue_attachment_file_not_found');
            return;
        }

        $mimeType = trim((string)($attachment['MimeType'] ?? '')) ?: 'application/octet-stream';
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
        $this->assertPostWithCsrf('index.php?route=workflow-issues/list');

        $model = new WorkflowIssueModel($this->db);
        $attachmentId = (int)($_POST['WorkflowIssueAttachmentID'] ?? 0);
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $attachment = $model->getAttachment($attachmentId);
        $issueId = (int)($attachment['WorkflowIssueID'] ?? 0);

        if (!$attachment || $issueId <= 0) {
            $this->flashError(__t('workflow_issue_attachment_not_found'));
            header('Location: index.php?route=workflow-issues/list');
            return;
        }

        if (!$this->canDeleteIssueAttachment($attachment, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_issue_permission_delete_attachment'));
            header('Location: index.php?route=workflow-issues/form&id=' . $issueId);
            return;
        }

        try {
            if (!$model->markAttachmentDeleted($attachmentId, $currentUserId)) {
                throw new \RuntimeException($model->getLastError() ?: __t('workflow_task_unknown_error'));
            }

            $safePath = $this->resolveIssueAttachmentPath((string)($attachment['StoragePath'] ?? ''));
            if ($safePath !== null && is_file($safePath)) {
                @unlink($safePath);
            }

            $this->auditEvent('ATTACHMENT_DELETE', 'WorkflowIssue', (string)$issueId, [
                'WorkflowIssueAttachmentID' => $attachmentId,
                'OriginalFileName' => (string)($attachment['OriginalFileName'] ?? 'attachment'),
            ]);

            $this->flashSuccess(__t('workflow_issue_attachment_removed'));
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_issue_attachment_delete_failed', ['msg' => $e->getMessage()]));
            app_log('Workflow issue attachment delete failed', [
                'WorkflowIssueAttachmentID' => $attachmentId,
                'WorkflowIssueID' => $issueId,
                'UserID' => $currentUserId,
                'error' => $e->getMessage(),
            ], 'error');
        }

        header('Location: index.php?route=workflow-issues/form&id=' . $issueId);
    }

    private function normalizeIssueReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);
        if ($returnTo === '' || preg_match('~[\r\n]~', $returnTo)) {
            return '';
        }
        if (str_starts_with($returnTo, '?')) {
            $returnTo = 'index.php' . $returnTo;
        }
        if (!str_starts_with($returnTo, 'index.php') || parse_url($returnTo, PHP_URL_PATH) !== 'index.php') {
            return '';
        }
        $query = parse_url($returnTo, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return '';
        }
        parse_str($query, $params);
        $route = trim((string)($params['route'] ?? ''));
        $allowedRoutes = [
            'workflow-issues/list',
            'workflow-issues/form',
            'workflow-projects/list',
            'workflow-projects/summary',
            'workflow-projects/form',
            'workflow-requirements/list',
            'workflow-requirements/summary',
            'workflow-requirements/matrix',
            'workflow-requirements/form',
            'workflow/list',
            'workflow/edit',
        ];
        return in_array($route, $allowedRoutes, true) ? $returnTo : '';
    }

    private function defaultIssueBackUrl(string $returnTo): string
    {
        return $returnTo !== '' ? $returnTo : 'index.php?route=workflow-issues/list';
    }

    private function issueFormRedirectUrl(int $id, string $returnTo = ''): string
    {
        $params = ['route' => 'workflow-issues/form'];
        if ($id > 0) {
            $params['id'] = $id;
        }
        if ($returnTo !== '') {
            $params['returnTo'] = $returnTo;
        }
        return 'index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function defaultIssueTaskTitle(array $record): string
    {
        $code = trim((string)($record['IssueCode'] ?? ''));
        $title = trim((string)($record['IssueTitle'] ?? ''));
        return trim(($code !== '' ? $code . ' - ' : '') . $title);
    }

    private function buildIssueTaskDescription(array $record): string
    {
        $parts = [];
        $parts[] = 'Issue: ' . $this->defaultIssueTaskTitle($record);
        $description = trim((string)($record['IssueDescription'] ?? ''));
        if ($description !== '') {
            $parts[] = $description;
        }
        $parts[] = 'Severity: ' . trim((string)($record['SeverityCode'] ?? ''));
        return implode("\n\n", array_filter($parts));
    }

    private function canDeleteIssueAttachment(array $attachment, array $perms, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (in_array('WORKFLOW_ISSUES_DELETE', $perms, true)
            || in_array('WORKFLOW_OPERATIONS_ADMIN', $perms, true)
            || in_array('ADMIN_ALL', $perms, true)
            || in_array('SYSADMIN', $perms, true)
        ) {
            return true;
        }

        return (int)($attachment['UploadedByUserID'] ?? 0) === $userId
            || (int)($attachment['OwnerUserID'] ?? 0) === $userId
            || (int)($attachment['CreatedBy'] ?? 0) === $userId;
    }

    private function storeIssueUploadedAttachments(
        WorkflowIssueModel $model,
        int $issueID,
        int $currentUserId,
        string $inputName,
        bool $requireAtLeastOne
    ): int {
        if ($issueID <= 0 || $currentUserId <= 0) {
            throw new \RuntimeException(__t('workflow_issue_attachment_store_context_required'));
        }

        $this->validateIssueUploadedAttachments($inputName, $requireAtLeastOne);

        $uploadDir = $this->issueAttachmentStorageRoot() . DIRECTORY_SEPARATOR . $issueID;
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException(__t('workflow_issue_attachment_storage_create_failed'));
        }

        $uploadedCount = 0;
        foreach ($this->normalizeIssueUploadedFiles($inputName) as $file) {
            $rawOriginalName = trim((string)($file['name'] ?? ''));
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE && $rawOriginalName === '') {
                continue;
            }

            $originalName = $this->safeIssueAttachmentOriginalName($rawOriginalName);
            $tmpPath = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedFileName = gmdate('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $storedFileName;

            if (!move_uploaded_file($tmpPath, $targetPath)) {
                throw new \RuntimeException(__t('workflow_issue_attachment_store_file_failed', ['file' => $originalName]));
            }

            $attachmentId = $model->saveAttachment($issueID, [
                'OriginalFileName' => $originalName,
                'StoredFileName' => $storedFileName,
                'StoragePath' => $targetPath,
                'MimeType' => $this->detectIssueAttachmentMimeType($targetPath),
                'FileSizeBytes' => $size,
                'UploadedByUserID' => $currentUserId,
            ]);

            if ($attachmentId <= 0) {
                @unlink($targetPath);
                throw new \RuntimeException($model->getLastError() ?: __t('workflow_issue_attachment_metadata_save_failed'));
            }

            $this->auditEvent('ATTACHMENT_UPLOAD', 'WorkflowIssue', (string)$issueID, [
                'WorkflowIssueAttachmentID' => $attachmentId,
                'OriginalFileName' => $originalName,
                'FileSizeBytes' => $size,
            ]);

            $uploadedCount++;
        }

        return $uploadedCount;
    }

    private function validateIssueUploadedAttachments(string $inputName, bool $requireAtLeastOne = false): void
    {
        $seen = false;
        foreach ($this->normalizeIssueUploadedFiles($inputName) as $file) {
            $originalName = trim((string)($file['name'] ?? ''));
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE && $originalName === '') {
                continue;
            }
            $seen = true;

            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new \RuntimeException($this->issueUploadErrorMessage($errorCode));
            }

            $tmpPath = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                throw new \RuntimeException(__t('workflow_issue_attachment_payload_invalid'));
            }
            if ($size <= 0) {
                throw new \RuntimeException(__t('workflow_issue_attachment_empty', ['file' => $this->safeIssueAttachmentOriginalName($originalName)]));
            }
            if ($size > $this->issueAttachmentMaxBytes()) {
                throw new \RuntimeException(__t('workflow_issue_attachment_too_large', ['file' => $this->safeIssueAttachmentOriginalName($originalName)]));
            }

            $extension = strtolower(pathinfo($this->safeIssueAttachmentOriginalName($originalName), PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, $this->issueAttachmentAllowedExtensions(), true)) {
                throw new \RuntimeException(__t('workflow_issue_attachment_type_not_allowed', ['file' => $this->safeIssueAttachmentOriginalName($originalName)]));
            }
        }

        if ($requireAtLeastOne && !$seen) {
            throw new \RuntimeException(__t('workflow_issue_choose_file'));
        }
    }

    private function normalizeIssueUploadedFiles(string $inputName): array
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
        foreach (($input['name'] ?? []) as $index => $name) {
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

    private function issueAttachmentStorageRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'workflow-issue-attachments';
    }

    private function issueAttachmentAllowedExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip'];
    }

    private function issueAttachmentMaxBytes(): int
    {
        return 25 * 1024 * 1024;
    }

    private function safeIssueAttachmentOriginalName(string $name): string
    {
        $name = trim(basename(str_replace('\\', '/', $name)));
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?? 'attachment';
        $name = trim($name, " .\t\n\r\0\x0B");
        return $name !== '' ? substr($name, 0, 255) : 'attachment';
    }

    private function detectIssueAttachmentMimeType(string $path): ?string
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

    private function resolveIssueAttachmentPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }
        $root = realpath($this->issueAttachmentStorageRoot());
        $file = realpath($path);
        if ($root === false || $file === false) {
            return null;
        }
        $rootNormalized = rtrim(str_replace('\\', '/', strtolower($root)), '/') . '/';
        $fileNormalized = str_replace('\\', '/', strtolower($file));
        return str_starts_with($fileNormalized, $rootNormalized) ? $file : null;
    }

    private function issueUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __t('workflow_issue_attachment_too_large', ['file' => 'attachment']),
            UPLOAD_ERR_PARTIAL => __t('workflow_issue_attachment_payload_invalid'),
            default => __t('workflow_issue_attachment_payload_invalid'),
        };
    }

    private function normalizeIssueDate($value): string
    {
        $value = trim((string)$value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private function priorityCodeForIssueTask(string $issuePriorityCode): string
    {
        return match (strtoupper(trim($issuePriorityCode))) {
            'MUST' => 'HIGH',
            'COULD' => 'LOW',
            'WONT' => 'LOW',
            default => 'NORMAL',
        };
    }

    private function canCreateIssues(): bool
    {
        return Rbac::canAny(['WORKFLOW_ISSUES_CREATE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function canEditIssues(): bool
    {
        return Rbac::canAny(['WORKFLOW_ISSUES_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function canDeleteIssues(): bool
    {
        return Rbac::canAny(['WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    /**
     * @param array<string, mixed>|null $record
     */
    private function canDeleteIssueRecord(?array $record): bool
    {
        if ($this->canDeleteIssues()) {
            return true;
        }
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        return $record !== null
            && $currentUserId > 0
            && (int)($record['CreatedBy'] ?? 0) === $currentUserId;
    }

    private function canCreateWorkflowTasks(): bool
    {
        return Rbac::canAny(['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }
}
