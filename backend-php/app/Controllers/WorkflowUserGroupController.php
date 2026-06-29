<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\WorkflowUserGroupModel;
use App\Shared\SessionHelper;

final class WorkflowUserGroupController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_ADMIN', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'USERS_VIEW', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_ADMIN', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_ADMIN', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function list(): void
    {
        $model = new WorkflowUserGroupModel($this->db);
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'active' => trim((string)($_GET['active'] ?? '1')),
        ];

        $this->render('users/WorkflowUserGroupList', [
            'title' => 'Workflow User Groups',
            'rows' => $model->listGroups($filters['q'], $filters['active']),
            'filters' => $filters,
            'tableInstalled' => $model->supportsWorkflowUserGroups(),
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function form(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $model = new WorkflowUserGroupModel($this->db);
        $userModel = new UserModel($this->db);
        $tableInstalled = $model->supportsWorkflowUserGroups();

        $record = $tableInstalled && $id > 0 ? $model->findGroup($id) : null;
        if ($id > 0 && $tableInstalled && !$record) {
            $this->flashError('Workflow user group not found.');
            header('Location: index.php?route=workflow-user-groups/list');
            return;
        }

        $this->render('users/WorkflowUserGroupForm', [
            'title' => $id > 0 ? 'Edit Workflow User Group' : 'Create Workflow User Group',
            'record' => $record,
            'users' => method_exists($userModel, 'listAll') ? $userModel->listAll() : [],
            'selectedUserIds' => $tableInstalled && $id > 0 ? $model->listMemberUserIds($id) : [],
            'tableInstalled' => $tableInstalled,
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function save(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-user-groups/list');

        $model = new WorkflowUserGroupModel($this->db);
        if (!$model->supportsWorkflowUserGroups()) {
            $this->flashError('Workflow user group tables are not installed. Run backend-php/config/sql/create_workflow_user_groups.sql first.');
            header('Location: index.php?route=workflow-user-groups/list');
            return;
        }

        $id = (int)($_POST['WorkflowUserGroupID'] ?? 0);
        $payload = [
            'WorkflowUserGroupID' => $id,
            'GroupName' => trim((string)($_POST['GroupName'] ?? '')),
            'Description' => trim((string)($_POST['Description'] ?? '')),
            'Active' => isset($_POST['Active']) ? 1 : 0,
        ];
        $memberUserIds = is_array($_POST['UserIDs'] ?? null) ? $_POST['UserIDs'] : [];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        $ownsTransaction = $this->db instanceof \PDO && !$this->db->inTransaction();

        try {
            if ($ownsTransaction) {
                $this->db->beginTransaction();
            }
            $savedId = $model->saveGroup($payload, $currentUserId);
            $model->saveMembers($savedId, $memberUserIds, $currentUserId);
            if ($ownsTransaction && $this->db instanceof \PDO && $this->db->inTransaction()) {
                $this->db->commit();
            }
            $this->auditEvent($id > 0 ? 'UPDATE' : 'CREATE', 'WorkflowUserGroup', (string)$savedId, [
                'GroupName' => $payload['GroupName'],
                'Active' => $payload['Active'],
                'MemberCount' => count(array_unique(array_map('intval', $memberUserIds))),
            ]);
            $this->flashSuccess($id > 0 ? 'Workflow user group updated.' : 'Workflow user group created.');
            header('Location: index.php?route=workflow-user-groups/list');
            return;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db instanceof \PDO && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logHandledException('WorkflowUserGroupController::save failed', $e, [
                'WorkflowUserGroupID' => $id,
                'GroupName' => $payload['GroupName'],
            ]);
            $this->flashError('Workflow user group save failed: ' . $e->getMessage());
            header('Location: index.php?route=workflow-user-groups/form' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }
    }
}
