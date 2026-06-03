<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\WorkflowAssignmentModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class WorkflowAssignmentController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_VIEW', 'BASE_CONFIG_EDIT', 'WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'archive' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function list(): void
    {
        $ctx = $this->context();
        $ctxFy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ctxVer = (int) ($ctx['VersionID'] ?? 0);
        $ctxScopeCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));
        $ctxScopeName = trim((string) SessionHelper::get('scope.dataobject_name', ''));
        $filters = [
            'fy' => trim((string) ($_GET['fy'] ?? ($ctxFy > 0 ? (string) $ctxFy : ''))),
            'version_id' => trim((string) ($_GET['version_id'] ?? ($ctxVer > 0 ? (string) $ctxVer : ''))),
            'workflow_area_code' => trim((string) ($_GET['workflow_area_code'] ?? '')),
            'workflow_stage_code' => trim((string) ($_GET['workflow_stage_code'] ?? '')),
            'data_object_code' => trim((string) ($_GET['data_object_code'] ?? $ctxScopeCode)),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = new WorkflowAssignmentModel($this->db);
        $rows = $model->annotateRowsWithAccessStatus($model->listRows($filters));

        $this->render('config/WorkflowAssignmentsList', [
            'title' => 'Workflow Assignments',
            'rows' => $rows,
            'filters' => $filters,
            'ctxScopeCode' => $ctxScopeCode,
            'ctxScopeName' => $ctxScopeName,
            'workflowAreas' => $model->getWorkflowAreaOptions(),
            'workflowStages' => $model->getWorkflowStageOptions(),
            'tableInstalled' => $model->supportsWorkflowAssignments(),
        ]);
    }

    public function form(): void
    {
        $ctx = $this->context();
        $ctxFy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ctxVer = (int) ($ctx['VersionID'] ?? 0);
        $ctxScopeCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));
        $ctxScopeName = trim((string) SessionHelper::get('scope.dataobject_name', ''));
        $id = (int) ($_GET['id'] ?? 0);

        $model = new WorkflowAssignmentModel($this->db);
        $record = $id > 0 ? $model->getById($id) : null;
        $workflowAreaCode = (string) ($record['WorkflowAreaCode'] ?? '');
        $workflowStageCode = (string) ($record['WorkflowStageCode'] ?? '');

        $this->render('config/WorkflowAssignmentForm', [
            'title' => $id > 0 ? 'Edit Workflow Assignment' : 'Create Workflow Assignment',
            'record' => $record,
            'ctxFy' => $ctxFy,
            'ctxVer' => $ctxVer,
            'ctxScopeCode' => $ctxScopeCode,
            'ctxScopeName' => $ctxScopeName,
            'workflowAreas' => $model->getWorkflowAreaOptions(),
            'workflowStages' => $model->getWorkflowStageOptions(),
            'workflowAccessRules' => $model->getWorkflowAccessRules(),
            'users' => $model->listAssignableUsers($workflowAreaCode, $workflowStageCode),
            'allUsers' => $model->listUsersWithPermissions(),
            'requiredPermissions' => $model->getRequiredPermissions($workflowAreaCode, $workflowStageCode),
            'tableInstalled' => $model->supportsWorkflowAssignments(),
        ]);
    }

    public function save(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=workflow-assignments/list');
            return;
        }

        $model = new WorkflowAssignmentModel($this->db);
        $id = (int) ($_POST['WorkflowAssignmentID'] ?? 0);
        try {
            $model->save([
                'WorkflowAssignmentID' => $id,
                'WorkflowAreaCode' => trim((string) ($_POST['WorkflowAreaCode'] ?? '')),
                'WorkflowStageCode' => trim((string) ($_POST['WorkflowStageCode'] ?? '')),
                'FiscalYearID' => trim((string) ($_POST['FiscalYearID'] ?? '')),
                'VersionID' => trim((string) ($_POST['VersionID'] ?? '')),
                'DataObjectCode' => trim((string) ($_POST['DataObjectCode'] ?? '')),
                'UserID' => (int) ($_POST['UserID'] ?? 0),
                'SequenceNo' => (int) ($_POST['SequenceNo'] ?? 1),
                'IsPrimary' => isset($_POST['IsPrimary']) ? 1 : 0,
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UpdatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ]);
            $this->flashSuccess(($id > 0) ? 'Workflow assignment updated.' : 'Workflow assignment created.');
            header('Location: index.php?route=workflow-assignments/list');
            return;
        } catch (\Throwable $e) {
            $this->flashError('Workflow assignment save failed: ' . $e->getMessage());
            header('Location: index.php?route=workflow-assignments/form' . ($id > 0 ? '&id=' . $id : ''));
            return;
        }
    }

    public function archive(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=workflow-assignments/list');
            return;
        }

        $id = (int) ($_POST['WorkflowAssignmentID'] ?? 0);
        $model = new WorkflowAssignmentModel($this->db);
        try {
            $model->archive($id, (int) SessionHelper::get('auth.user_id', 0));
            $this->flashSuccess('Workflow assignment archived.');
        } catch (\Throwable $e) {
            $this->flashError('Workflow assignment archive failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=workflow-assignments/list');
    }
}
