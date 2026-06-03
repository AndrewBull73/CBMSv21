<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditModel;
use App\Models\SystemSettingsModel;
use App\Models\UserModel;
use App\Models\WorkflowTaskModel;
use App\Models\WorkflowTaskStatusModel;
use App\Models\WorkflowTaskTypeModel;
use App\Services\MailService;
use App\Shared\SessionHelper;

final class WorkflowController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN']],
        'edit' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN']],
        'transition' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN']],
        'delete' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN']],
    ];

    public function list(): void
    {
        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $typesModel = new WorkflowTaskTypeModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $userModel = new UserModel($conn);

        $userID = (int) SessionHelper::get('auth.user_id', 0);
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $canAdmin = in_array('WORKFLOW_ADMIN', $perms, true);
        $canEdit = $canAdmin || in_array('WORKFLOW_EDIT', $perms, true);

        $q = trim((string) ($_GET['q'] ?? ''));
        $typeID = ($_GET['typeID'] ?? '') !== '' ? (int) $_GET['typeID'] : null;
        $statusID = ($_GET['statusID'] ?? '') !== '' ? (int) $_GET['statusID'] : null;
        $statusFlag = (string) ($_GET['status'] ?? '');
        $onlyOpen = ($statusFlag === 'open');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($_GET['pageSize'] ?? 10)));
        $isIframe = !empty($_GET['iframe']);

        $mineRequested = ($_GET['mine'] ?? '') !== '' ? (int) $_GET['mine'] === 1 : !$canAdmin || $isIframe || $onlyOpen;
        $assignedToID = null;
        if ($mineRequested || !$canAdmin) {
            $assignedToID = $userID;
        } elseif (($_GET['assignedToUserID'] ?? '') !== '') {
            $assignedToID = (int) $_GET['assignedToUserID'];
        }

        $res = $tasksModel->listFiltered($assignedToID, $page, $pageSize, $q, $typeID, $statusID, $onlyOpen);
        $summary = $tasksModel->summarizeFiltered($assignedToID, $q, $typeID, $statusID);
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
            'showAdminScope' => $canAdmin,
            'mine' => $mineRequested,
            'canEditWorkflow' => $canEdit,
            'canAdminWorkflow' => $canAdmin,
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

    public function edit(): void
    {
        require __DIR__ . '/../../config/db.php';

        $id = (int) ($_GET['id'] ?? 0);
        $tasksModel = new WorkflowTaskModel($conn);
        $typesModel = new WorkflowTaskTypeModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $userModel = new UserModel($conn);

        $task = $id > 0 ? $tasksModel->find($id) : null;
        $users = method_exists($userModel, 'listAll')
            ? $userModel->listAll()
            : $userModel->all(1, 500, '');

        $params = [
            'title' => $id > 0 ? __t('edit_task') : __t('create_task'),
            'task' => $task,
            'types' => $typesModel->listActive(),
            'statuses' => $statusesModel->listActive(),
            'users' => $users,
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
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';
        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);
        $userModel = new UserModel($conn);
        $settingsModel = new SystemSettingsModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);

        $id = (int) ($_POST['WorkflowTaskID'] ?? 0);
        $existingTask = $id > 0 ? $tasksModel->find($id) : null;
        $statusID = (int) ($_POST['StatusID'] ?? 0);
        $isClosedStatus = $statusID > 0 ? $statusesModel->isClosedStatusId($statusID) : false;

        $data = [
            'TaskTypeID' => (int) ($_POST['TaskTypeID'] ?? 0),
            'StatusID' => $statusID,
            'Title' => trim((string) ($_POST['Title'] ?? '')),
            'Description' => trim((string) ($_POST['Description'] ?? '')),
            'CreatedByUserID' => (int) SessionHelper::get('auth.user_id', 0),
            'AssignedToUserID' => ($_POST['AssignedToUserID'] ?? '') !== '' ? (int) $_POST['AssignedToUserID'] : null,
            'RelatedEntity' => trim((string) ($_POST['RelatedEntity'] ?? '')),
            'RelatedKey' => trim((string) ($_POST['RelatedKey'] ?? '')),
            'DueDate' => ($_POST['DueDate'] ?? '') !== '' ? (string) $_POST['DueDate'] : null,
            'CompletedAt' => $isClosedStatus ? ($existingTask['CompletedAt'] ?? gmdate('Y-m-d H:i:s')) : null,
            'UpdatedBy' => (int) SessionHelper::get('auth.user_id', 0),
        ];

        $errors = [];
        if ($data['Title'] === '') {
            $errors[] = __t('title_required');
        }
        if ($data['Description'] === '') {
            $errors[] = __t('description_required');
        }
        if ($data['TaskTypeID'] <= 0) {
            $errors[] = __t('task_type_required');
        }
        if ($data['StatusID'] <= 0) {
            $errors[] = __t('status_required');
        }
        if ($data['AssignedToUserID'] === null) {
            $errors[] = __t('assigned_to_required');
        }
        if ($data['DueDate'] === null) {
            $errors[] = __t('due_date_required');
        }

        $context = $this->workflowRedirectContextFromPost();

        if ($errors) {
            $this->flashError(implode('<br>', $errors));
            $this->redirectToWorkflowEdit($id, $context);
        }

        try {
            $action = $id > 0 ? 'UPDATE' : 'CREATE';

            if ($id > 0) {
                $tasksModel->update($id, $data);
                $this->flashSuccess(__t('task_updated', ['task' => $data['Title']]));
            } else {
                $tasksModel->create($data);
                $this->flashSuccess(__t('task_created', ['task' => $data['Title']]));
            }

            $audit->insert([
                'UserID' => SessionHelper::get('auth.user_id'),
                'Username' => SessionHelper::get('auth.username', 'guest'),
                'Action' => $action,
                'Entity' => 'WorkflowTask',
                'EntityKey' => $id > 0 ? (string) $id : $data['Title'],
                'IPAddress' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'Details' => $data,
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
            ]);

            $this->notifyAssigneeIfNeeded($conn, $userModel, $settingsModel, $statusesModel, $existingTask, $data, $id, $action);
        } catch (\Throwable $e) {
            $this->flashError(__t('task_save_failed') . ': ' . $e->getMessage());
            app_log('[WorkflowController@save] Exception', ['error' => $e->getMessage()], 'error');
        }

        $this->redirectToWorkflowList($context);
    }

    public function transition(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';

        $tasksModel = new WorkflowTaskModel($conn);
        $statusesModel = new WorkflowTaskStatusModel($conn);
        $audit = new AuditModel($conn);

        $id = (int) ($_POST['WorkflowTaskID'] ?? 0);
        $transition = strtolower(trim((string) ($_POST['transition'] ?? '')));
        $context = $this->workflowRedirectContextFromPost();

        if ($id <= 0 || !in_array($transition, ['complete', 'reopen'], true)) {
            $this->flashError('The requested workflow action is not valid.');
            $this->redirectToWorkflowList($context);
        }

        $task = $tasksModel->find($id);
        if (!$task) {
            $this->flashError(__t('invalid_task'));
            $this->redirectToWorkflowList($context);
        }

        $statusID = $transition === 'complete'
            ? ($statusesModel->findCompletedStatusId() ?? (int) ($task['StatusID'] ?? 0))
            : ($statusesModel->findOpenStatusId() ?? (int) ($task['StatusID'] ?? 0));
        $completedAt = $transition === 'complete' ? gmdate('Y-m-d H:i:s') : null;

        if ($tasksModel->updateStatus($id, $statusID, $completedAt, (int) SessionHelper::get('auth.user_id', 0))) {
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
            $this->flashSuccess($transition === 'complete' ? 'Task marked complete.' : 'Task reopened.');
        } else {
            $this->flashError('The task status could not be updated.');
        }

        $this->redirectToWorkflowList($context);
    }

    public function delete(): void
    {
        $this->assertPostWithCsrf($this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));

        require __DIR__ . '/../../config/db.php';
        $tasksModel = new WorkflowTaskModel($conn);
        $audit = new AuditModel($conn);

        $id = (int) ($_POST['WorkflowTaskID'] ?? 0);
        if ($id <= 0) {
            $this->flashError(__t('invalid_task'));
            header('Location: ' . $this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));
            exit;
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

        header('Location: ' . $this->mergeLinkedContextIntoUrl('index.php?route=workflow/list'));
        exit;
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

        $appUrl = rtrim($settingsModel->get('APP_URL', 'http://localhost/CBMSv21'), '/');
        $taskUrl = $this->buildWorkflowTaskActionUrl($taskKey, $data, $appUrl);
        $linkLabel = $this->buildWorkflowTaskActionLabel($data);

        $body = '
            <p>Dear ' . htmlspecialchars($assigned['DisplayName'] ?? $assigned['Username']) . ',</p>
            <p>The following workflow task has been updated in CBMS:</p>
            <ul>
              <li><strong>Title:</strong> ' . htmlspecialchars($data['Title']) . '</li>
              <li><strong>Description:</strong> ' . nl2br(htmlspecialchars($data['Description'])) . '</li>
              <li><strong>Task Type:</strong> ' . htmlspecialchars($taskType ?? '-') . '</li>
              <li><strong>Status:</strong> ' . htmlspecialchars($statusName ?? '-') . '</li>
              <li><strong>Due Date:</strong> ' . htmlspecialchars($data['DueDate'] ?? '-') . '</li>
              <li><strong>Created By:</strong> ' . htmlspecialchars($createdBy['DisplayName'] ?? $createdBy['Username'] ?? '-') . '</li>
            </ul>
            <p><a href="' . htmlspecialchars($taskUrl) . '">' . htmlspecialchars($linkLabel) . '</a></p>
        ';

        try {
            $mailer = new MailService($conn);
            $mailer->sendEmail(
                (string) $assigned['Email'],
                $subject,
                $body,
                $settingsModel->get('EMAIL_ERROR_FROM', 'noreply@cbmsv2.local')
            );
        } catch (\Throwable $mailErr) {
            $this->flashError(__t('task_email_failed') . ': ' . $mailErr->getMessage());
            app_log('[WorkflowController@save] Mail failed', [
                'AssignedToUserID' => $data['AssignedToUserID'],
                'Email' => $assigned['Email'] ?? null,
                'error' => $mailErr->getMessage(),
            ], 'error');
        }
    }

    private function workflowRedirectContextFromPost(): array
    {
        return [
            'q' => (string) ($_POST['q'] ?? ''),
            'page' => max(1, (int) ($_POST['page'] ?? 1)),
            'pageSize' => max(1, (int) ($_POST['pageSize'] ?? 10)),
            'typeID' => ($_POST['typeID'] ?? '') !== '' ? (int) $_POST['typeID'] : null,
            'statusID' => ($_POST['statusID'] ?? '') !== '' ? (int) $_POST['statusID'] : null,
            'status' => (string) ($_POST['status'] ?? ''),
            'mine' => ($_POST['mine'] ?? '') !== '' ? (int) $_POST['mine'] : null,
            'assignedToUserID' => ($_POST['assignedToUserID'] ?? '') !== '' ? (int) $_POST['assignedToUserID'] : null,
            'iframe' => !empty($_POST['iframe']),
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
        if ($context['mine'] !== null) {
            $qs['mine'] = (string) $context['mine'];
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
        if ($context['mine'] !== null) {
            $qs['mine'] = (string) $context['mine'];
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
        $entity = strtoupper(trim((string) ($taskData['RelatedEntity'] ?? '')));
        $key = trim((string) ($taskData['RelatedKey'] ?? ''));

        if ($entity !== '' && ctype_digit($key)) {
            $entityId = (int) $key;
            switch ($entity) {
                case 'STRATEGICFUNDINGSUBMISSION':
                case 'FUNDINGSUBMISSION':
                    $route = 'strategy-submissions/view';
                    $params = ['id' => $entityId];
                    break;
                case 'STRATEGICSEGMENTPUBLISHREQUEST':
                case 'SEGMENTPUBLISHREQUEST':
                    $route = 'strategy-publish/request-view';
                    $params = ['id' => $entityId];
                    break;
                case 'WORKFLOWTASK':
                    $route = 'workflow/edit';
                    $params = ['id' => $entityId];
                    break;
            }
        }

        $qs = array_filter(
            ['route' => $route] + $params + $baseParams,
            static fn($v) => $v !== null && $v !== '' && $v !== 0
        );
        return $appUrl . '/backend-php/public/index.php?' . http_build_query($qs);
    }

    private function buildWorkflowTaskActionLabel(array $taskData): string
    {
        $explicitLabel = trim((string) ($taskData['TaskLinkLabel'] ?? ''));
        if ($explicitLabel !== '') {
            return $explicitLabel;
        }

        $entity = strtoupper(trim((string) ($taskData['RelatedEntity'] ?? '')));
        return match ($entity) {
            'STRATEGICFUNDINGSUBMISSION', 'FUNDINGSUBMISSION' => 'Open Funding Request in CBMS',
            'STRATEGICSEGMENTPUBLISHREQUEST', 'SEGMENTPUBLISHREQUEST' => 'Open Publication Request in CBMS',
            default => 'Open Task in CBMS',
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
