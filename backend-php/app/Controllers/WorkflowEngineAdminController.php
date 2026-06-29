<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BaseConfigurationReadinessModel;
use App\Models\WorkflowEngineAdminModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class WorkflowEngineAdminController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'stage-form' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'stageForm' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'action-form' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'actionForm' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'diagnostics' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'inquiry' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-stage' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'saveStage' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save-action' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'saveAction' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'archive' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'archive-stage' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'archiveStage' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'archive-action' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'archiveAction' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function list(): void
    {
        $model = $this->buildModel();
        $filters = [
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $this->render('config/WorkflowEngineList', [
            'title' => 'Workflow Engine',
            'titleKey' => 'workflow_engine_title',
            'contextLabels' => $this->buildContextLabels(),
            'tableInstalled' => $model->supportsWorkflowDefinitions(),
            'rows' => $model->listDefinitions($filters['active']),
            'filters' => $filters,
        ]);
    }

    public function form(): void
    {
        $model = $this->buildModel();
        $workflowAreaCode = strtoupper(trim((string) ($_GET['workflow_area_code'] ?? '')));
        $record = $workflowAreaCode !== '' ? $model->getDefinition($workflowAreaCode) : null;
        $effectiveAreaCode = strtoupper(trim((string) ($record['WorkflowAreaCode'] ?? $workflowAreaCode)));

        $this->render('config/WorkflowEngineForm', [
            'title' => $record !== null ? 'Edit Workflow Definition' : 'Create Workflow Definition',
            'titleKey' => $record !== null ? 'workflow_engine_edit_definition_title' : 'workflow_engine_create_definition_title',
            'contextLabels' => $this->buildContextLabels(),
            'tableInstalled' => $model->supportsWorkflowDefinitions(),
            'record' => $record,
            'stageRows' => $effectiveAreaCode !== '' ? $model->listStages($effectiveAreaCode) : [],
            'actionRows' => $effectiveAreaCode !== '' ? $model->listActions($effectiveAreaCode) : [],
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
            header('Location: index.php?route=workflow-engine/list');
            return;
        }

        $model = $this->buildModel();
        try {
            $payload = [
                'OriginalWorkflowAreaCode' => trim((string) ($_POST['OriginalWorkflowAreaCode'] ?? '')),
                'WorkflowAreaCode' => trim((string) ($_POST['WorkflowAreaCode'] ?? '')),
                'WorkflowAreaName' => trim((string) ($_POST['WorkflowAreaName'] ?? '')),
                'ModuleCode' => trim((string) ($_POST['ModuleCode'] ?? '')),
                'RecordTableName' => trim((string) ($_POST['RecordTableName'] ?? '')),
                'Description' => trim((string) ($_POST['Description'] ?? '')),
                'RouteByDataObjectHierarchy' => isset($_POST['RouteByDataObjectHierarchy']) ? 1 : 0,
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UpdatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ];
            $areaCode = $model->saveDefinition([
                ...$payload,
            ]);
            $this->auditEvent($payload['OriginalWorkflowAreaCode'] !== '' ? 'UPDATE' : 'CREATE', 'WorkflowDefinition', $areaCode, $payload);
            $this->flashSuccess('workflow_engine_definition_saved');
            header('Location: index.php?route=workflow-engine/form&workflow_area_code=' . urlencode($areaCode));
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowEngineAdminController::save failed', $e, [
                'workflowAreaCode' => trim((string) ($_POST['WorkflowAreaCode'] ?? '')),
            ]);
            $this->flashError('workflow_engine_definition_save_failed_detail', ['msg' => $e->getMessage()]);
            $redirectArea = trim((string) ($_POST['OriginalWorkflowAreaCode'] ?? ($_POST['WorkflowAreaCode'] ?? '')));
            header('Location: index.php?route=workflow-engine/form' . ($redirectArea !== '' ? '&workflow_area_code=' . urlencode($redirectArea) : ''));
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
            header('Location: index.php?route=workflow-engine/list');
            return;
        }

        $workflowAreaCode = trim((string) ($_POST['WorkflowAreaCode'] ?? ''));
        $model = $this->buildModel();
        try {
            $model->archiveDefinition($workflowAreaCode, (int) SessionHelper::get('auth.user_id', 0));
            $this->auditEvent('ARCHIVE', 'WorkflowDefinition', $workflowAreaCode, []);
            $this->flashSuccess('workflow_engine_definition_archived');
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowEngineAdminController::archive failed', $e, [
                'workflowAreaCode' => $workflowAreaCode,
            ]);
            $this->flashError('workflow_engine_definition_archive_failed_detail', ['msg' => $e->getMessage()]);
        }

        header('Location: index.php?route=workflow-engine/list');
    }

    public function stageForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? $model->getStageById($id) : null;
        $workflowAreaCode = strtoupper(trim((string) ($record['WorkflowAreaCode'] ?? ($_GET['workflow_area_code'] ?? ''))));

        $this->render('config/WorkflowEngineStageForm', [
            'title' => $record !== null ? 'Edit Workflow Stage' : 'Create Workflow Stage',
            'titleKey' => $record !== null ? 'workflow_engine_edit_stage_title' : 'workflow_engine_create_stage_title',
            'contextLabels' => $this->buildContextLabels(),
            'tableInstalled' => $model->supportsWorkflowDefinitions(),
            'record' => $record,
            'workflowAreaCode' => $workflowAreaCode,
            'workflowAreas' => $model->listDefinitions(''),
            'stageTypeOptions' => $model->getStageTypeOptions(),
        ]);
    }

    public function saveStage(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=workflow-engine/list');
            return;
        }

        $workflowAreaCode = strtoupper(trim((string) ($_POST['WorkflowAreaCode'] ?? '')));
        $model = $this->buildModel();
        try {
            $payload = [
                'WorkflowDefinitionStageID' => (int) ($_POST['WorkflowDefinitionStageID'] ?? 0),
                'WorkflowAreaCode' => $workflowAreaCode,
                'WorkflowStageCode' => trim((string) ($_POST['WorkflowStageCode'] ?? '')),
                'WorkflowStageName' => trim((string) ($_POST['WorkflowStageName'] ?? '')),
                'StageOrder' => (int) ($_POST['StageOrder'] ?? 0),
                'StageType' => trim((string) ($_POST['StageType'] ?? '')),
                'RequiredPermissionCodes' => trim((string) ($_POST['RequiredPermissionCodes'] ?? '')),
                'RouteByDataObjectHierarchy' => isset($_POST['RouteByDataObjectHierarchy']) ? 1 : 0,
                'AllowReturn' => isset($_POST['AllowReturn']) ? 1 : 0,
                'AllowReject' => isset($_POST['AllowReject']) ? 1 : 0,
                'AllowCancel' => isset($_POST['AllowCancel']) ? 1 : 0,
                'AllowsDelegation' => isset($_POST['AllowsDelegation']) ? 1 : 0,
                'RequireDifferentActorFromPreviousStage' => isset($_POST['RequireDifferentActorFromPreviousStage']) ? 1 : 0,
                'IsDraftStage' => isset($_POST['IsDraftStage']) ? 1 : 0,
                'IsFinalStage' => isset($_POST['IsFinalStage']) ? 1 : 0,
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UpdatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ];
            $model->saveStage($payload);
            $this->auditEvent($payload['WorkflowDefinitionStageID'] > 0 ? 'UPDATE' : 'CREATE', 'WorkflowDefinitionStage', $payload['WorkflowDefinitionStageID'] > 0 ? (string) $payload['WorkflowDefinitionStageID'] : ($workflowAreaCode . ':' . $payload['WorkflowStageCode']), $payload);
            $this->flashSuccess('workflow_engine_stage_saved');
            header('Location: index.php?route=workflow-engine/form&workflow_area_code=' . urlencode($workflowAreaCode));
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowEngineAdminController::saveStage failed', $e, [
                'workflowAreaCode' => $workflowAreaCode,
                'workflowDefinitionStageId' => (int) ($_POST['WorkflowDefinitionStageID'] ?? 0),
            ]);
            $this->flashError('workflow_engine_stage_save_failed_detail', ['msg' => $e->getMessage()]);
            $id = (int) ($_POST['WorkflowDefinitionStageID'] ?? 0);
            $query = $id > 0 ? '&id=' . $id : '&workflow_area_code=' . urlencode($workflowAreaCode);
            header('Location: index.php?route=workflow-engine/stage-form' . $query);
            return;
        }
    }

    public function archiveStage(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=workflow-engine/list');
            return;
        }

        $id = (int) ($_POST['WorkflowDefinitionStageID'] ?? 0);
        $workflowAreaCode = trim((string) ($_POST['WorkflowAreaCode'] ?? ''));
        $model = $this->buildModel();
        try {
            $model->archiveStage($id, (int) SessionHelper::get('auth.user_id', 0));
            $this->auditEvent('ARCHIVE', 'WorkflowDefinitionStage', (string) $id, [
                'WorkflowAreaCode' => $workflowAreaCode,
            ]);
            $this->flashSuccess('workflow_engine_stage_archived');
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowEngineAdminController::archiveStage failed', $e, [
                'workflowDefinitionStageId' => $id,
                'workflowAreaCode' => $workflowAreaCode,
            ]);
            $this->flashError('workflow_engine_stage_archive_failed_detail', ['msg' => $e->getMessage()]);
        }

        header('Location: index.php?route=workflow-engine/form&workflow_area_code=' . urlencode($workflowAreaCode));
    }

    public function actionForm(): void
    {
        $model = $this->buildModel();
        $id = (int) ($_GET['id'] ?? 0);
        $record = $id > 0 ? $model->getActionById($id) : null;
        $workflowAreaCode = strtoupper(trim((string) ($record['WorkflowAreaCode'] ?? ($_GET['workflow_area_code'] ?? ''))));

        $this->render('config/WorkflowEngineActionForm', [
            'title' => $record !== null ? 'Edit Workflow Action' : 'Create Workflow Action',
            'titleKey' => $record !== null ? 'workflow_engine_edit_action_title' : 'workflow_engine_create_action_title',
            'contextLabels' => $this->buildContextLabels(),
            'tableInstalled' => $model->supportsWorkflowDefinitions(),
            'record' => $record,
            'workflowAreaCode' => $workflowAreaCode,
            'workflowAreas' => $model->listDefinitions(''),
            'workflowStages' => $workflowAreaCode !== '' ? $model->listStages($workflowAreaCode, true) : $model->listStages('', true),
            'actionTypeOptions' => $model->getActionTypeOptions(),
        ]);
    }

    public function saveAction(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=workflow-engine/list');
            return;
        }

        $workflowAreaCode = strtoupper(trim((string) ($_POST['WorkflowAreaCode'] ?? '')));
        $model = $this->buildModel();
        try {
            $payload = [
                'WorkflowDefinitionActionID' => (int) ($_POST['WorkflowDefinitionActionID'] ?? 0),
                'WorkflowAreaCode' => $workflowAreaCode,
                'WorkflowActionCode' => trim((string) ($_POST['WorkflowActionCode'] ?? '')),
                'WorkflowActionName' => trim((string) ($_POST['WorkflowActionName'] ?? '')),
                'FromStageCode' => trim((string) ($_POST['FromStageCode'] ?? '')),
                'ToStageCode' => trim((string) ($_POST['ToStageCode'] ?? '')),
                'ActionType' => trim((string) ($_POST['ActionType'] ?? '')),
                'RequiredPermissionCodes' => trim((string) ($_POST['RequiredPermissionCodes'] ?? '')),
                'RequireNote' => isset($_POST['RequireNote']) ? 1 : 0,
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UpdatedBy' => (int) SessionHelper::get('auth.user_id', 0),
            ];
            $model->saveAction($payload);
            $this->auditEvent($payload['WorkflowDefinitionActionID'] > 0 ? 'UPDATE' : 'CREATE', 'WorkflowDefinitionAction', $payload['WorkflowDefinitionActionID'] > 0 ? (string) $payload['WorkflowDefinitionActionID'] : ($workflowAreaCode . ':' . $payload['WorkflowActionCode']), $payload);
            $this->flashSuccess('workflow_engine_action_saved');
            header('Location: index.php?route=workflow-engine/form&workflow_area_code=' . urlencode($workflowAreaCode));
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowEngineAdminController::saveAction failed', $e, [
                'workflowAreaCode' => $workflowAreaCode,
                'workflowDefinitionActionId' => (int) ($_POST['WorkflowDefinitionActionID'] ?? 0),
            ]);
            $this->flashError('workflow_engine_action_save_failed_detail', ['msg' => $e->getMessage()]);
            $id = (int) ($_POST['WorkflowDefinitionActionID'] ?? 0);
            $query = $id > 0 ? '&id=' . $id : '&workflow_area_code=' . urlencode($workflowAreaCode);
            header('Location: index.php?route=workflow-engine/action-form' . $query);
            return;
        }
    }

    public function archiveAction(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=workflow-engine/list');
            return;
        }

        $id = (int) ($_POST['WorkflowDefinitionActionID'] ?? 0);
        $workflowAreaCode = trim((string) ($_POST['WorkflowAreaCode'] ?? ''));
        $model = $this->buildModel();
        try {
            $model->archiveAction($id, (int) SessionHelper::get('auth.user_id', 0));
            $this->auditEvent('ARCHIVE', 'WorkflowDefinitionAction', (string) $id, [
                'WorkflowAreaCode' => $workflowAreaCode,
            ]);
            $this->flashSuccess('workflow_engine_action_archived');
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowEngineAdminController::archiveAction failed', $e, [
                'workflowDefinitionActionId' => $id,
                'workflowAreaCode' => $workflowAreaCode,
            ]);
            $this->flashError('workflow_engine_action_archive_failed_detail', ['msg' => $e->getMessage()]);
        }

        header('Location: index.php?route=workflow-engine/form&workflow_area_code=' . urlencode($workflowAreaCode));
    }

    public function diagnostics(): void
    {
        $ctx = $this->context();
        $model = $this->buildModel();
        $filters = [
            'workflow_area_code' => trim((string) ($_GET['workflow_area_code'] ?? '')),
            'workflow_stage_code' => trim((string) ($_GET['workflow_stage_code'] ?? '')),
            'fy' => trim((string) ($_GET['fy'] ?? ((int) ($ctx['FiscalYearID'] ?? 0) > 0 ? (string) ((int) $ctx['FiscalYearID']) : ''))),
            'version_id' => trim((string) ($_GET['version_id'] ?? ((int) ($ctx['VersionID'] ?? 0) > 0 ? (string) ((int) $ctx['VersionID']) : ''))),
            'data_object_code' => trim((string) ($_GET['data_object_code'] ?? (string) SessionHelper::get('scope.dataobject_code', ''))),
        ];

        $this->render('config/WorkflowEngineDiagnostics', [
            'title' => 'Workflow Engine Diagnostics',
            'titleKey' => 'workflow_engine_diagnostics_title',
            'contextLabels' => $this->buildContextLabels(),
            'tableInstalled' => $model->supportsWorkflowDefinitions(),
            'supportsAssignments' => $model->supportsWorkflowAssignments(),
            'diagnostics' => $model->getDiagnostics($filters),
        ]);
    }

    public function inquiry(): void
    {
        $ctx = $this->context();
        $model = $this->buildModel();
        $filters = [
            'workflow_area_code' => trim((string) ($_GET['workflow_area_code'] ?? '')),
            'current_stage_code' => trim((string) ($_GET['current_stage_code'] ?? '')),
            'fy' => trim((string) ($_GET['fy'] ?? ((int) ($ctx['FiscalYearID'] ?? 0) > 0 ? (string) ((int) $ctx['FiscalYearID']) : ''))),
            'version_id' => trim((string) ($_GET['version_id'] ?? ((int) ($ctx['VersionID'] ?? 0) > 0 ? (string) ((int) $ctx['VersionID']) : ''))),
            'data_object_code' => trim((string) ($_GET['data_object_code'] ?? (string) SessionHelper::get('scope.dataobject_code', ''))),
            'assigned_user_id' => trim((string) ($_GET['assigned_user_id'] ?? '')),
            'state_bucket' => trim((string) ($_GET['state_bucket'] ?? 'OPEN')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];
        $selectedWorkflowInstanceId = (int) ($_GET['workflow_instance_id'] ?? 0);

        $this->render('config/WorkflowEngineInquiry', [
            'title' => 'Workflow Engine Inquiry',
            'titleKey' => 'workflow_engine_inquiry_title',
            'contextLabels' => $this->buildContextLabels(),
            'tableInstalled' => $model->supportsWorkflowInstances(),
            'supportsAssignments' => $model->supportsWorkflowAssignments(),
            'inquiry' => $model->getInquiry($filters, $selectedWorkflowInstanceId),
        ]);
    }

    private function buildModel(): WorkflowEngineAdminModel
    {
        return new WorkflowEngineAdminModel($this->db);
    }

    private function buildContextLabels(): array
    {
        if (!$this->db instanceof \PDO) {
            return [];
        }

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $model = new BaseConfigurationReadinessModel($this->db);
        return $model->getContextLabels($fy, $ver);
    }
}
