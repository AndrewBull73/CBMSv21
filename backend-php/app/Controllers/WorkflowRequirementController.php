<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Rbac;
use App\Models\UserModel;
use App\Models\WorkflowLinkModel;
use App\Models\WorkflowProjectModel;
use App\Models\WorkflowRequirementModel;
use App\Models\WorkflowTaskModel;
use App\Models\WorkflowTaskStatusModel;
use App\Models\WorkflowTaskTypeModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/workflow_helpers.php';

final class WorkflowRequirementController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'summary' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'matrix' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'transition' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'delete' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'create-task' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'upload-attachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'download-attachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'delete-attachment' => ['auth' => true, 'permsAny' => ['WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function list(): void
    {
        $model = new WorkflowRequirementModel($this->db);
        $projectModel = new WorkflowProjectModel($this->db);

        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'workflowProjectID' => ($_GET['workflowProjectID'] ?? '') !== '' ? (int)$_GET['workflowProjectID'] : 0,
            'deliveryClass' => strtoupper(trim((string)($_GET['deliveryClass'] ?? ''))),
            'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
            'type' => strtoupper(trim((string)($_GET['type'] ?? ''))),
            'priority' => strtoupper(trim((string)($_GET['priority'] ?? ''))),
            'requirementLevel' => strtoupper(trim((string)($_GET['requirementLevel'] ?? ''))),
            'active' => trim((string)($_GET['active'] ?? '1')),
        ];

        $this->render('workflow/WorkflowRequirementList', [
            'title' => __t('workflow_requirements'),
            'rows' => $model->listRequirements($filters),
            'filters' => $filters,
            'deliveryClassOptions' => $model->deliveryClassOptions(),
            'typeOptions' => $model->typeOptions(),
            'priorityOptions' => $model->priorityOptions(),
            'statusOptions' => $model->statusOptions(),
            'requirementLevelOptions' => $model->requirementLevelOptions(),
            'workflowProjects' => $projectModel->supportsWorkflowProjects() ? $projectModel->listActiveProjects() : [],
            'tableInstalled' => $model->supportsRequirements(),
            'canCreateRequirement' => $this->canCreateRequirements(),
            'canEditRequirement' => $this->canEditRequirements(),
            'canDeleteRequirement' => $this->canDeleteRequirements(),
            'canCreateWorkflowTask' => $this->canCreateWorkflowTasks(),
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function summary(): void
    {
        $model = new WorkflowRequirementModel($this->db);
        $projectModel = new WorkflowProjectModel($this->db);

        $filters = [
            'workflowProjectID' => ($_GET['workflowProjectID'] ?? '') !== '' ? (int)$_GET['workflowProjectID'] : 0,
            'deliveryClass' => strtoupper(trim((string)($_GET['deliveryClass'] ?? ''))),
            'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
            'type' => strtoupper(trim((string)($_GET['type'] ?? ''))),
            'priority' => strtoupper(trim((string)($_GET['priority'] ?? ''))),
            'requirementLevel' => strtoupper(trim((string)($_GET['requirementLevel'] ?? ''))),
            'active' => trim((string)($_GET['active'] ?? '')),
        ];

        $this->render('workflow/WorkflowRequirementSummary', [
            'title' => __t('workflow_requirement_summary'),
            'summary' => $model->summarizeRequirements($filters),
            'filters' => $filters,
            'deliveryClassOptions' => $model->deliveryClassOptions(),
            'typeOptions' => $model->typeOptions(),
            'priorityOptions' => $model->priorityOptions(),
            'statusOptions' => $model->statusOptions(),
            'requirementLevelOptions' => $model->requirementLevelOptions(),
            'workflowProjects' => $projectModel->supportsWorkflowProjects() ? $projectModel->listActiveProjects() : [],
            'tableInstalled' => $model->supportsRequirements(),
            'canCreateRequirement' => $this->canCreateRequirements(),
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function matrix(): void
    {
        $model = new WorkflowRequirementModel($this->db);
        $projectModel = new WorkflowProjectModel($this->db);
        $linkModel = new WorkflowLinkModel($this->db);

        $coverageOptions = [
            'ALL' => 'workflow_requirement_matrix_coverage_all',
            'NEEDS_TASK' => 'workflow_requirement_matrix_coverage_needs_task',
            'OPEN_TASKS' => 'workflow_requirement_matrix_coverage_open_tasks',
            'NO_TESTING' => 'workflow_requirement_matrix_coverage_no_testing',
            'NO_TRAINING' => 'workflow_requirement_matrix_coverage_no_training',
            'MISSING_ACCEPTANCE' => 'workflow_requirement_matrix_coverage_missing_acceptance',
            'HAS_DEFECTS' => 'workflow_requirement_matrix_coverage_has_defects',
            'COMPLETE' => 'workflow_requirement_matrix_coverage_complete',
        ];

        $coverage = strtoupper(trim((string)($_GET['coverage'] ?? 'ALL')));
        if (!array_key_exists($coverage, $coverageOptions)) {
            $coverage = 'ALL';
        }

        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'workflowProjectID' => ($_GET['workflowProjectID'] ?? '') !== '' ? (int)$_GET['workflowProjectID'] : 0,
            'deliveryClass' => strtoupper(trim((string)($_GET['deliveryClass'] ?? ''))),
            'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
            'type' => strtoupper(trim((string)($_GET['type'] ?? ''))),
            'priority' => strtoupper(trim((string)($_GET['priority'] ?? ''))),
            'requirementLevel' => strtoupper(trim((string)($_GET['requirementLevel'] ?? ''))),
            'coverage' => $coverage,
            'active' => trim((string)($_GET['active'] ?? '1')),
        ];

        $rows = $model->listTraceabilityMatrix($filters);

        $this->render('workflow/WorkflowRequirementMatrix', [
            'title' => __t('workflow_requirement_matrix'),
            'rows' => $rows,
            'summary' => $model->summarizeTraceabilityMatrix($rows),
            'filters' => $filters,
            'coverageOptions' => $coverageOptions,
            'deliveryClassOptions' => $model->deliveryClassOptions(),
            'typeOptions' => $model->typeOptions(),
            'priorityOptions' => $model->priorityOptions(),
            'statusOptions' => $model->statusOptions(),
            'requirementLevelOptions' => $model->requirementLevelOptions(),
            'workflowProjects' => $projectModel->supportsWorkflowProjects() ? $projectModel->listActiveProjects() : [],
            'tableInstalled' => $model->supportsRequirements(),
            'workflowLinksInstalled' => $linkModel->supportsWorkflowLinks(),
            'canCreateRequirement' => $this->canCreateRequirements(),
            'canEditRequirement' => $this->canEditRequirements(),
            'canDeleteRequirement' => $this->canDeleteRequirements(),
            'canCreateWorkflowTask' => $this->canCreateWorkflowTasks(),
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function form(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $returnTo = $this->normalizeRequirementReturnTo((string)($_GET['returnTo'] ?? ''));
        $model = new WorkflowRequirementModel($this->db);
        $projectModel = new WorkflowProjectModel($this->db);
        $userModel = new UserModel($this->db);
        $linkModel = new WorkflowLinkModel($this->db);
        $tableInstalled = $model->supportsRequirements();
        $requirementHierarchyInstalled = $model->supportsRequirementHierarchy();

        $record = $tableInstalled && $id > 0 ? $model->findRequirement($id) : null;
        if ($id > 0 && $tableInstalled && !$record) {
            $this->flashError(__t('workflow_requirement_not_found'));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }
        if ($id <= 0 && !$this->canCreateRequirements()) {
            $this->flashError(__t('workflow_requirement_permission_create'));
            header('Location: ' . $this->defaultRequirementBackUrl($returnTo, null, (int)($_GET['workflowProjectID'] ?? 0)));
            return;
        }

        if ($id <= 0 && $tableInstalled && $requirementHierarchyInstalled && (int)($_GET['parentRequirementID'] ?? 0) > 0) {
            $parentRequirement = $model->findRequirement((int)$_GET['parentRequirementID']);
            if ($parentRequirement) {
                $record = [
                    'WorkflowProjectID' => (int)($parentRequirement['WorkflowProjectID'] ?? 0),
                    'ParentRequirementID' => (int)$parentRequirement['WorkflowRequirementID'],
                    'RequirementLevelCode' => 'DETAILED',
                    'RequirementStatusCode' => 'DRAFT',
                    'RequirementTypeCode' => (string)($parentRequirement['RequirementTypeCode'] ?? 'FUNCTIONAL'),
                    'DeliveryClassCode' => (string)($parentRequirement['DeliveryClassCode'] ?? 'ENHANCEMENT'),
                    'PriorityCode' => (string)($parentRequirement['PriorityCode'] ?? 'SHOULD'),
                    'ModuleCode' => (string)($parentRequirement['ModuleCode'] ?? ''),
                    'Active' => 1,
                ];
            }
        }

        if ($id <= 0 && $record === null && ($_GET['workflowProjectID'] ?? '') !== '') {
            $record = [
                'WorkflowProjectID' => (int)$_GET['workflowProjectID'],
                'RequirementLevelCode' => 'HIGH_LEVEL',
                'RequirementStatusCode' => 'DRAFT',
                'RequirementTypeCode' => 'FUNCTIONAL',
                'DeliveryClassCode' => 'ENHANCEMENT',
                'PriorityCode' => 'SHOULD',
                'Active' => 1,
            ];
        }

        $workflowProjectID = (int)($record['WorkflowProjectID'] ?? 0);
        $parentRequirementID = (int)($record['ParentRequirementID'] ?? 0);
        $selectedWorkflowProject = $workflowProjectID > 0 && $projectModel->supportsWorkflowProjects()
            ? $projectModel->findProject($workflowProjectID)
            : null;
        $requirementLinks = $id > 0 && $linkModel->supportsWorkflowLinks()
            ? $linkModel->listRequirementLinks($id)
            : [];
        $requirementRelatedLinks = $id > 0 && $linkModel->supportsWorkflowLinks()
            ? $linkModel->listRequirementLinkedTaskLinks($id)
            : [];
        $parentRequirements = $tableInstalled && $requirementHierarchyInstalled
            ? $model->listHighLevelRequirements($workflowProjectID, $id)
            : [];
        $childRequirements = $id > 0 && $requirementHierarchyInstalled
            ? $model->listChildRequirements($id)
            : [];
        $backUrl = $this->defaultRequirementBackUrl($returnTo, $parentRequirementID, $workflowProjectID);

        $this->render('workflow/WorkflowRequirementForm', [
            'title' => $id > 0 ? __t('workflow_requirement_edit') : __t('workflow_requirement_create'),
            'record' => $record,
            'returnTo' => $returnTo,
            'backUrl' => $backUrl,
            'deliveryClassOptions' => $model->deliveryClassOptions(),
            'typeOptions' => $model->typeOptions(),
            'priorityOptions' => $model->priorityOptions(),
            'statusOptions' => $model->statusOptions(),
            'requirementLevelOptions' => $model->requirementLevelOptions(),
            'workflowProjects' => $projectModel->supportsWorkflowProjects() ? $projectModel->listActiveProjects() : [],
            'requirementHierarchyInstalled' => $requirementHierarchyInstalled,
            'parentRequirements' => $parentRequirements,
            'childRequirements' => $childRequirements,
            'selectedWorkflowProject' => $selectedWorkflowProject,
            'users' => method_exists($userModel, 'listAll') ? $userModel->listAll() : [],
            'tableInstalled' => $tableInstalled,
            'workflowLinksInstalled' => $linkModel->supportsWorkflowLinks(),
            'requirementTraceability' => [
                'requirementLinks' => $requirementLinks,
                'relatedLinks' => $requirementRelatedLinks,
                'summary' => $linkModel->summarizeRequirementTraceability($requirementLinks, $requirementRelatedLinks),
            ],
            'currentUserId' => (int)SessionHelper::get('auth.user_id', 0),
            'requirementAttachmentsInstalled' => $model->supportsRequirementAttachments(),
            'requirementAttachments' => $id > 0 && $model->supportsRequirementAttachments() ? $model->listAttachments($id) : [],
            'requirementHistoryInstalled' => $model->supportsRequirementHistory(),
            'requirementHistory' => $id > 0 && $model->supportsRequirementHistory() ? $model->listRequirementHistory($id) : [],
            'canCreateRequirement' => $this->canCreateRequirements(),
            'canEditRequirement' => $this->canEditRequirements(),
            'canDeleteRequirement' => $this->canDeleteRequirements(),
            'canCreateWorkflowTask' => $this->canCreateWorkflowTasks(),
            'canReviewRequirement' => $this->canReviewRequirements(),
            'canApproveRequirement' => $this->canApproveRequirements(),
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function save(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-requirements/list');

        $model = new WorkflowRequirementModel($this->db);
        $returnTo = $this->normalizeRequirementReturnTo((string)($_POST['returnTo'] ?? ''));
        if (!$model->supportsRequirements()) {
            $this->flashError(__t('workflow_requirement_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }

        $id = (int)($_POST['WorkflowRequirementID'] ?? 0);
        $before = $id > 0 ? $model->findRequirement($id) : null;
        if ($id > 0 && !$before) {
            $this->flashError(__t('workflow_requirement_not_found'));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }
        if ($id <= 0 && !$this->canCreateRequirements()) {
            $this->flashError(__t('workflow_requirement_permission_create'));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }
        if ($id > 0 && !$this->canEditRequirements()) {
            $this->flashError(__t('workflow_requirement_permission_edit'));
            header('Location: ' . $this->requirementFormRedirectUrl($id, $returnTo));
            return;
        }

        $payload = [
            'WorkflowRequirementID' => $id,
            'RequirementCode' => trim((string)($_POST['RequirementCode'] ?? '')),
            'WorkflowProjectID' => ($_POST['WorkflowProjectID'] ?? '') !== '' ? (int)$_POST['WorkflowProjectID'] : null,
            'ParentRequirementID' => ($_POST['ParentRequirementID'] ?? '') !== '' ? (int)$_POST['ParentRequirementID'] : null,
            'RequirementLevelCode' => strtoupper(trim((string)($_POST['RequirementLevelCode'] ?? 'HIGH_LEVEL'))),
            'ModuleCode' => trim((string)($_POST['ModuleCode'] ?? '')),
            'RequirementTitle' => trim((string)($_POST['RequirementTitle'] ?? '')),
            'DeliveryClassCode' => strtoupper(trim((string)($_POST['DeliveryClassCode'] ?? 'ENHANCEMENT'))),
            'RequirementTypeCode' => strtoupper(trim((string)($_POST['RequirementTypeCode'] ?? 'FUNCTIONAL'))),
            'PriorityCode' => strtoupper(trim((string)($_POST['PriorityCode'] ?? 'SHOULD'))),
            'RequirementStatusCode' => strtoupper(trim((string)($_POST['RequirementStatusCode'] ?? 'DRAFT'))),
            'SourceDocument' => trim((string)($_POST['SourceDocument'] ?? '')),
            'SourceSection' => trim((string)($_POST['SourceSection'] ?? '')),
            'Description' => workflow_sanitize_rich_text((string)($_POST['Description'] ?? '')),
            'AcceptanceCriteria' => workflow_sanitize_rich_text((string)($_POST['AcceptanceCriteria'] ?? '')),
            'RequestedByUserID' => ($_POST['RequestedByUserID'] ?? '') !== '' ? (int)$_POST['RequestedByUserID'] : null,
            'OwnerUserID' => ($_POST['OwnerUserID'] ?? '') !== '' ? (int)$_POST['OwnerUserID'] : null,
            'ApprovedByUserID' => ($_POST['ApprovedByUserID'] ?? '') !== '' ? (int)$_POST['ApprovedByUserID'] : null,
            'ApprovedAt' => trim((string)($_POST['ApprovedAt'] ?? '')),
            'Active' => isset($_POST['Active']) && (string)$_POST['Active'] !== '0' ? 1 : 0,
        ];
        if ($id > 0 && (int)($before['Active'] ?? 0) === 1 && $payload['Active'] === 0 && !$this->canDeleteRequirements()) {
            $this->flashError(__t('workflow_requirement_permission_delete'));
            header('Location: ' . $this->requirementFormRedirectUrl($id, $returnTo));
            return;
        }
        if ($this->statusRequiresApprovalPermission($payload['RequirementStatusCode'])
            && (string)($before['RequirementStatusCode'] ?? '') !== $payload['RequirementStatusCode']
            && !$this->canApproveRequirements()
        ) {
            $this->flashError(__t('workflow_requirement_approval_permission_required'));
            header('Location: ' . $this->requirementFormRedirectUrl($id, $returnTo));
            return;
        }

        if ($payload['RequirementTitle'] === '') {
            $this->flashError(__t('workflow_requirement_title_required'));
            header('Location: ' . $this->requirementFormRedirectUrl(
                $id,
                $returnTo,
                $id > 0 ? [] : [
                    'parentRequirementID' => $payload['ParentRequirementID'],
                    'workflowProjectID' => $payload['WorkflowProjectID'],
                ]
            ));
            return;
        }

        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        try {
            $savedId = $model->saveRequirement($payload, $currentUserId);
            $saved = $model->findRequirement($savedId) ?: $payload;
            $model->recordRequirementSaveHistory($savedId, $before, $saved, $currentUserId);
            $requirementCode = trim((string)($saved['RequirementCode'] ?? $model->defaultRequirementCode($savedId)));
            $requirementTitle = trim((string)($saved['RequirementTitle'] ?? $payload['RequirementTitle']));
            $workflowProjectID = (int)($saved['WorkflowProjectID'] ?? $payload['WorkflowProjectID'] ?? 0);

            $linkModel = new WorkflowLinkModel($this->db);
            if ($linkModel->supportsWorkflowLinks()) {
                $linkModel->syncProjectEntityLink(
                    $workflowProjectID > 0 ? $workflowProjectID : null,
                    'REQUIREMENT',
                    'WorkflowRequirement',
                    $savedId,
                    $requirementCode,
                    $requirementTitle,
                    'index.php?route=workflow-requirements/form&id=' . $savedId,
                    null,
                    $currentUserId
                );
            }

            $this->auditEvent($id > 0 ? 'UPDATE' : 'CREATE', 'WorkflowRequirement', (string)$savedId, [
                'RequirementCode' => $requirementCode,
                'RequirementTitle' => $requirementTitle,
                'WorkflowProjectID' => $workflowProjectID > 0 ? $workflowProjectID : null,
                'ParentRequirementID' => !empty($saved['ParentRequirementID']) ? (int)$saved['ParentRequirementID'] : null,
                'RequirementLevelCode' => (string)($saved['RequirementLevelCode'] ?? $payload['RequirementLevelCode']),
                'DeliveryClassCode' => (string)($saved['DeliveryClassCode'] ?? $payload['DeliveryClassCode']),
                'RequirementStatusCode' => (string)($saved['RequirementStatusCode'] ?? $payload['RequirementStatusCode']),
                'PriorityCode' => (string)($saved['PriorityCode'] ?? $payload['PriorityCode']),
            ]);

            $this->flashSuccess($id > 0 ? __t('workflow_requirement_updated') : __t('workflow_requirement_created'));
            if ($returnTo === '') {
                $returnTo = $this->defaultRequirementBackUrl(
                    '',
                    !empty($saved['ParentRequirementID']) ? (int)$saved['ParentRequirementID'] : null,
                    $workflowProjectID
                );
            }
            header('Location: ' . $this->requirementFormRedirectUrl($savedId, $returnTo));
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowRequirementController::save failed', $e, [
                'WorkflowRequirementID' => $id,
                'RequirementTitle' => $payload['RequirementTitle'],
            ]);
            $this->flashError(__t('workflow_requirement_save_failed') . ': ' . $e->getMessage());
            header('Location: ' . $this->requirementFormRedirectUrl(
                $id,
                $returnTo,
                $id > 0 ? [] : [
                    'parentRequirementID' => $payload['ParentRequirementID'],
                    'workflowProjectID' => $payload['WorkflowProjectID'],
                ]
            ));
            return;
        }
    }

    public function delete(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-requirements/list');

        $model = new WorkflowRequirementModel($this->db);
        $returnTo = $this->normalizeRequirementReturnTo((string)($_POST['returnTo'] ?? ''));
        $redirect = $this->defaultRequirementBackUrl($returnTo, null, null);
        if (!$this->canDeleteRequirements()) {
            $this->flashError(__t('workflow_requirement_permission_delete'));
            header('Location: ' . $redirect);
            return;
        }
        if (!$model->supportsRequirements()) {
            $this->flashError(__t('workflow_requirement_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: ' . $redirect);
            return;
        }

        $id = (int)($_POST['WorkflowRequirementID'] ?? 0);
        $record = $id > 0 ? $model->findRequirement($id) : null;
        if (!$record) {
            $this->flashError(__t('workflow_requirement_not_found'));
            header('Location: ' . $redirect);
            return;
        }

        if ($returnTo === '') {
            $redirect = $this->defaultRequirementBackUrl('', !empty($record['ParentRequirementID']) ? (int)$record['ParentRequirementID'] : null, !empty($record['WorkflowProjectID']) ? (int)$record['WorkflowProjectID'] : null);
        }

        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        try {
            if (!$model->archiveRequirement($id, $currentUserId)) {
                throw new \RuntimeException($model->getLastError() ?: __t('workflow_task_unknown_error'));
            }
            $after = $model->findRequirement($id) ?: $record;
            $model->recordRequirementSaveHistory($id, $record, $after, $currentUserId);
            $this->auditEvent('DELETE', 'WorkflowRequirement', (string)$id, [
                'RequirementCode' => (string)($record['RequirementCode'] ?? ''),
                'RequirementTitle' => (string)($record['RequirementTitle'] ?? ''),
                'WorkflowProjectID' => !empty($record['WorkflowProjectID']) ? (int)$record['WorkflowProjectID'] : null,
            ]);
            $this->flashSuccess(__t('workflow_requirement_deleted'));
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowRequirementController::delete failed', $e, [
                'WorkflowRequirementID' => $id,
            ]);
            $this->flashError(__t('workflow_requirement_delete_failed') . ': ' . $e->getMessage());
        }

        header('Location: ' . $redirect);
    }

    public function transition(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-requirements/list');

        $model = new WorkflowRequirementModel($this->db);
        if (!$model->supportsRequirements()) {
            $this->flashError(__t('workflow_requirement_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }
        if (!$model->supportsRequirementHistory()) {
            $this->flashError(__t('workflow_requirement_history_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }

        $id = (int)($_POST['WorkflowRequirementID'] ?? 0);
        $toStatusCode = strtoupper(trim((string)($_POST['ToStatusCode'] ?? '')));
        $notes = trim((string)($_POST['ReviewNotes'] ?? ''));
        $redirect = $id > 0
            ? 'index.php?route=workflow-requirements/form&id=' . $id
            : 'index.php?route=workflow-requirements/list';

        $record = $id > 0 ? $model->findRequirement($id) : null;
        if (!$record) {
            $this->flashError(__t('workflow_requirement_not_found'));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }

        if (!array_key_exists($toStatusCode, $model->statusOptions())) {
            $this->flashError(__t('workflow_requirement_transition_invalid'));
            header('Location: ' . $redirect);
            return;
        }

        if ($this->statusRequiresApprovalPermission($toStatusCode) && !$this->canApproveRequirements()) {
            $this->flashError(__t('workflow_requirement_approval_permission_required'));
            header('Location: ' . $redirect);
            return;
        }

        if (!$this->canReviewRequirements()) {
            $this->flashError(__t('access_denied'));
            header('Location: ' . $redirect);
            return;
        }

        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        if (!$model->transitionRequirementStatus($id, $toStatusCode, $notes, $currentUserId)) {
            $message = trim($model->getLastError());
            $this->flashError(__t('workflow_requirement_transition_failed') . ($message !== '' ? ': ' . $message : ''));
            header('Location: ' . $redirect);
            return;
        }

        $this->auditEvent('STATUS_TRANSITION', 'WorkflowRequirement', (string)$id, [
            'FromStatusCode' => (string)($record['RequirementStatusCode'] ?? ''),
            'ToStatusCode' => $toStatusCode,
            'Notes' => $notes,
        ]);

        $this->flashSuccess(__t('workflow_requirement_transition_saved'));
        header('Location: ' . $redirect);
    }

    public function createTask(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-requirements/list');

        $requirementID = (int)($_POST['WorkflowRequirementID'] ?? 0);
        $requirementModel = new WorkflowRequirementModel($this->db);
        $projectModel = new WorkflowProjectModel($this->db);
        $taskModel = new WorkflowTaskModel($this->db);
        $statusModel = new WorkflowTaskStatusModel($this->db);
        $typeModel = new WorkflowTaskTypeModel($this->db);
        $linkModel = new WorkflowLinkModel($this->db);
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);

        $record = $requirementID > 0 ? $requirementModel->findRequirement($requirementID) : null;
        if (!$record) {
            $this->flashError(__t('workflow_requirement_not_found'));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }
        if (!$this->canViewRequirements()) {
            $this->flashError(__t('access_denied'));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }

        $redirect = 'index.php?route=workflow-requirements/form&id=' . $requirementID;
        $workflowProjectID = (int)($record['WorkflowProjectID'] ?? 0);
        if ($workflowProjectID <= 0) {
            $this->flashError(__t('workflow_requirement_task_requires_project'));
            header('Location: ' . $redirect);
            return;
        }

        if (!$linkModel->supportsWorkflowLinks()) {
            $this->flashError(__t('workflow_requirement_task_link_storage_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
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
        if ($taskTypeID <= 0) {
            $this->flashError(__t('workflow_project_task_type_missing'));
            header('Location: ' . $redirect);
            return;
        }

        $statusID = (int)($statusModel->findOpenStatusId() ?? 0);
        if ($statusID <= 0) {
            $this->flashError(__t('workflow_requirement_task_status_missing'));
            header('Location: ' . $redirect);
            return;
        }

        $taskTitle = trim((string)($_POST['TaskTitle'] ?? ''));
        if ($taskTitle === '') {
            $taskTitle = $this->defaultRequirementTaskTitle($record);
        }
        $taskTitle = $this->truncateRequirementTaskTitle($taskTitle);
        if ($taskTitle === '') {
            $this->flashError(__t('title_required'));
            header('Location: ' . $redirect);
            return;
        }

        $assignedToUserID = (int)($_POST['AssignedToUserID'] ?? 0);
        if ($assignedToUserID <= 0) {
            $assignedToUserID = (int)($record['OwnerUserID'] ?? 0);
        }
        if ($assignedToUserID <= 0) {
            $assignedToUserID = $currentUserId;
        }
        if ($assignedToUserID <= 0) {
            $this->flashError(__t('workflow_requirement_task_assignee_required'));
            header('Location: ' . $redirect);
            return;
        }

        $dueDate = $this->normalizeRequirementTaskDate($_POST['TaskDueDate'] ?? null);
        if ($dueDate === '') {
            $dueDate = $this->normalizeRequirementTaskDate($project['TargetEndDate'] ?? null);
        }
        if ($dueDate === '') {
            $dueDate = gmdate('Y-m-d', strtotime('+14 days') ?: time());
        }

        $projectStartDate = $this->normalizeRequirementTaskDate($project['StartDate'] ?? null);
        $projectEndDate = $this->normalizeRequirementTaskDate($project['TargetEndDate'] ?? null);
        if ($projectStartDate !== '' && $dueDate < $projectStartDate) {
            $this->flashError(__t('workflow_requirement_task_date_before_project', ['date' => $projectStartDate]));
            header('Location: ' . $redirect);
            return;
        }
        if ($projectEndDate !== '' && $dueDate > $projectEndDate) {
            $this->flashError(__t('workflow_requirement_task_date_after_project', ['date' => $projectEndDate]));
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

        $requirementCode = trim((string)($record['RequirementCode'] ?? $requirementModel->defaultRequirementCode($requirementID)));
        $requirementTitle = trim((string)($record['RequirementTitle'] ?? ''));
        $taskData = [
            'TaskTypeID' => $taskTypeID,
            'StatusID' => $statusID,
            'Title' => $taskTitle,
            'Description' => $this->buildRequirementTaskDescription($record),
            'CreatedByUserID' => $currentUserId,
            'AssignedToUserID' => $assignedToUserID,
            'RelatedEntity' => 'WorkflowRequirement',
            'RelatedKey' => $requirementCode !== '' ? $requirementCode : (string)$requirementID,
            'PriorityCode' => $this->priorityCodeForRequirementTask((string)($record['PriorityCode'] ?? 'SHOULD')),
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

            $taskModel->addActivity(
                $workflowTaskID,
                'CREATED',
                __t('workflow_requirement_task_created_activity', [
                    'code' => $requirementCode !== '' ? $requirementCode : (string)$requirementID,
                    'title' => $requirementTitle !== '' ? $requirementTitle : $taskTitle,
                ]),
                $currentUserId,
                null,
                $assignedToUserID,
                null,
                $statusID,
                [
                    'workflowRequirementID' => $requirementID,
                    'requirementCode' => $requirementCode,
                    'source' => 'REQUIREMENT_CREATE_TASK',
                ]
            );

            $workflowLinkID = $linkModel->saveTaskLink([
                'WorkflowProjectID' => $workflowProjectID,
                'WorkflowTaskID' => $workflowTaskID,
                'LinkTypeCode' => 'REQUIREMENT',
                'LinkedEntity' => 'WorkflowRequirement',
                'LinkedEntityID' => $requirementID,
                'LinkedEntityKey' => $requirementCode,
                'LinkedTitle' => $requirementTitle !== '' ? $requirementTitle : $taskTitle,
                'LinkedUrl' => 'index.php?route=workflow-requirements/form&id=' . $requirementID,
                'Notes' => __t('workflow_requirement_task_link_note'),
            ], $currentUserId);

            $taskModel->addActivity(
                $workflowTaskID,
                'LINK_ADDED',
                __t('workflow_link_added_activity', [
                    'type' => 'REQUIREMENT',
                    'title' => $requirementTitle !== '' ? $requirementTitle : $requirementCode,
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
                    'linkedEntityID' => $requirementID,
                    'linkedEntityKey' => $requirementCode,
                ]
            );

            if ($startedTransaction) {
                $this->db->commit();
            }

            $this->auditEvent('CREATE_TASK_FROM_REQUIREMENT', 'WorkflowRequirement', (string)$requirementID, [
                'WorkflowTaskID' => $workflowTaskID,
                'WorkflowProjectID' => $workflowProjectID,
                'RequirementCode' => $requirementCode,
                'TaskTitle' => $taskTitle,
            ]);

            $this->flashSuccess(__t('workflow_requirement_task_created', ['task' => $taskTitle]));
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logHandledException('WorkflowRequirementController::createTask failed', $e, [
                'WorkflowRequirementID' => $requirementID,
            ]);
            $this->flashError(__t('workflow_requirement_task_create_failed') . ': ' . $e->getMessage());
        }

        header('Location: ' . $redirect);
    }

    public function uploadAttachment(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-requirements/list');

        $model = new WorkflowRequirementModel($this->db);
        $id = (int)($_POST['WorkflowRequirementID'] ?? 0);
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        $record = $id > 0 ? $model->findRequirement($id) : null;

        if (!$record) {
            $this->flashError(__t('workflow_requirement_not_found'));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }

        if (!$model->supportsRequirementAttachments()) {
            $this->flashError(__t('workflow_requirement_attachments_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: index.php?route=workflow-requirements/form&id=' . $id);
            return;
        }

        try {
            $uploadedCount = $this->storeRequirementUploadedAttachments($model, $id, $currentUserId, 'RequirementAttachment', true);
            $this->flashSuccess($uploadedCount === 1
                ? __t('workflow_requirement_attachment_uploaded')
                : __t('workflow_requirement_attachments_uploaded', ['count' => $uploadedCount])
            );
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_requirement_attachment_upload_failed', ['msg' => $e->getMessage()]));
            app_log('Workflow requirement attachment upload failed', [
                'WorkflowRequirementID' => $id,
                'UserID' => $currentUserId,
                'error' => $e->getMessage(),
            ], 'error');
        }

        header('Location: index.php?route=workflow-requirements/form&id=' . $id);
    }

    public function downloadAttachment(): void
    {
        $model = new WorkflowRequirementModel($this->db);
        $attachmentId = (int)($_GET['id'] ?? 0);
        $attachment = $model->getAttachment($attachmentId);
        if (!$attachment) {
            http_response_code(404);
            echo __t('workflow_requirement_attachment_not_found');
            return;
        }

        $safePath = $this->resolveRequirementAttachmentPath((string)($attachment['StoragePath'] ?? ''));
        if ($safePath === null || !is_file($safePath) || !is_readable($safePath)) {
            http_response_code(404);
            echo __t('workflow_requirement_attachment_file_not_found');
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
        $this->assertPostWithCsrf('index.php?route=workflow-requirements/list');

        $model = new WorkflowRequirementModel($this->db);
        $attachmentId = (int)($_POST['WorkflowRequirementAttachmentID'] ?? 0);
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        $perms = is_array(SessionHelper::get('auth.perms', [])) ? SessionHelper::get('auth.perms', []) : [];
        $attachment = $model->getAttachment($attachmentId);
        $requirementId = (int)($attachment['WorkflowRequirementID'] ?? 0);

        if (!$attachment || $requirementId <= 0) {
            $this->flashError(__t('workflow_requirement_attachment_not_found'));
            header('Location: index.php?route=workflow-requirements/list');
            return;
        }

        if (!$this->canDeleteRequirementAttachment($attachment, $perms, $currentUserId)) {
            $this->flashError(__t('workflow_requirement_permission_delete_attachment'));
            header('Location: index.php?route=workflow-requirements/form&id=' . $requirementId);
            return;
        }

        try {
            if (!$model->markAttachmentDeleted($attachmentId, $currentUserId)) {
                throw new \RuntimeException($model->getLastError() ?: __t('workflow_task_unknown_error'));
            }

            $safePath = $this->resolveRequirementAttachmentPath((string)($attachment['StoragePath'] ?? ''));
            if ($safePath !== null && is_file($safePath)) {
                @unlink($safePath);
            }

            $this->auditEvent('ATTACHMENT_DELETE', 'WorkflowRequirement', (string)$requirementId, [
                'WorkflowRequirementAttachmentID' => $attachmentId,
                'OriginalFileName' => (string)($attachment['OriginalFileName'] ?? 'attachment'),
            ]);

            $this->flashSuccess(__t('workflow_requirement_attachment_removed'));
        } catch (\Throwable $e) {
            $this->flashError(__t('workflow_requirement_attachment_delete_failed', ['msg' => $e->getMessage()]));
            app_log('Workflow requirement attachment delete failed', [
                'WorkflowRequirementAttachmentID' => $attachmentId,
                'WorkflowRequirementID' => $requirementId,
                'UserID' => $currentUserId,
                'error' => $e->getMessage(),
            ], 'error');
        }

        header('Location: index.php?route=workflow-requirements/form&id=' . $requirementId);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function defaultRequirementTaskTitle(array $record): string
    {
        $code = trim((string)($record['RequirementCode'] ?? ''));
        $title = trim((string)($record['RequirementTitle'] ?? ''));
        if ($code !== '' && $title !== '') {
            return $code . ' - ' . $title;
        }
        return $title !== '' ? $title : $code;
    }

    private function truncateRequirementTaskTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
        if (strlen($title) <= 255) {
            return $title;
        }
        return rtrim(substr($title, 0, 252)) . '...';
    }

    private function normalizeRequirementTaskDate($value): string
    {
        $text = trim((string)$value);
        if ($text === '') {
            return '';
        }
        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return '';
        }
        return gmdate('Y-m-d', $timestamp);
    }

    private function priorityCodeForRequirementTask(string $requirementPriorityCode): string
    {
        return match (strtoupper(trim($requirementPriorityCode))) {
            'MUST' => 'HIGH',
            'COULD', 'WONT' => 'LOW',
            default => 'NORMAL',
        };
    }

    private function normalizeRequirementReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);
        if ($returnTo === '' || preg_match('~[\r\n]~', $returnTo)) {
            return '';
        }
        if (str_starts_with($returnTo, '?')) {
            $returnTo = 'index.php' . $returnTo;
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
            'workflow-requirements/list',
            'workflow-requirements/summary',
            'workflow-requirements/matrix',
            'workflow-requirements/form',
            'workflow-projects/summary',
            'workflow-projects/form',
            'workflow-projects/list',
        ];
        if (!in_array($route, $allowedRoutes, true)) {
            return '';
        }

        return $returnTo;
    }

    private function defaultRequirementBackUrl(string $returnTo, ?int $parentRequirementID, ?int $workflowProjectID): string
    {
        if ($returnTo !== '') {
            return $returnTo;
        }
        if ((int)$parentRequirementID > 0) {
            return 'index.php?route=workflow-requirements/form&id=' . (int)$parentRequirementID;
        }
        if ((int)$workflowProjectID > 0) {
            return 'index.php?route=workflow-requirements/list&workflowProjectID=' . (int)$workflowProjectID;
        }
        return 'index.php?route=workflow-requirements/list';
    }

    /**
     * @param array<string, int|null> $extra
     */
    private function requirementFormRedirectUrl(int $id, string $returnTo = '', array $extra = []): string
    {
        $params = ['route' => 'workflow-requirements/form'];
        if ($id > 0) {
            $params['id'] = $id;
        }
        foreach ($extra as $key => $value) {
            if ($value !== null && (int)$value > 0) {
                $params[$key] = (int)$value;
            }
        }
        if ($returnTo !== '') {
            $params['returnTo'] = $returnTo;
        }
        return 'index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function buildRequirementTaskDescription(array $record): string
    {
        $code = trim((string)($record['RequirementCode'] ?? ''));
        $title = trim((string)($record['RequirementTitle'] ?? ''));
        $sourceDocument = trim((string)($record['SourceDocument'] ?? ''));
        $sourceSection = trim((string)($record['SourceSection'] ?? ''));
        $description = workflow_sanitize_rich_text((string)($record['Description'] ?? ''));
        $acceptanceCriteria = workflow_sanitize_rich_text((string)($record['AcceptanceCriteria'] ?? ''));

        $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $parts = [
            '<p><strong>' . htmlspecialchars(__t('workflow_requirement_code'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . ($safeCode !== '' ? $safeCode : '-') . '</p>',
            '<p><strong>' . htmlspecialchars(__t('workflow_requirement_title'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . ($safeTitle !== '' ? $safeTitle : '-') . '</p>',
        ];

        if ($sourceDocument !== '' || $sourceSection !== '') {
            $sourceText = trim($sourceDocument . ($sourceDocument !== '' && $sourceSection !== '' ? ' / ' : '') . $sourceSection);
            $parts[] = '<p><strong>' . htmlspecialchars(__t('workflow_requirement_traceability_source'), ENT_QUOTES, 'UTF-8') . ':</strong> ' . htmlspecialchars($sourceText, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        if (workflow_rich_text_to_plain_text($description) !== '') {
            $parts[] = '<h5>' . htmlspecialchars(__t('description'), ENT_QUOTES, 'UTF-8') . '</h5>';
            $parts[] = $description;
        }

        if (workflow_rich_text_to_plain_text($acceptanceCriteria) !== '') {
            $parts[] = '<h5>' . htmlspecialchars(__t('workflow_requirement_acceptance_criteria'), ENT_QUOTES, 'UTF-8') . '</h5>';
            $parts[] = $acceptanceCriteria;
        }

        return workflow_sanitize_rich_text(implode("\n", $parts));
    }

    /**
     * @param array<string, mixed> $attachment
     * @param array<int, string> $perms
     */
    private function canDeleteRequirementAttachment(array $attachment, array $perms, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (in_array('WORKFLOW_REQUIREMENTS_DELETE', $perms, true)
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

    private function storeRequirementUploadedAttachments(
        WorkflowRequirementModel $model,
        int $requirementID,
        int $currentUserId,
        string $inputName,
        bool $requireAtLeastOne
    ): int {
        if ($requirementID <= 0 || $currentUserId <= 0) {
            throw new \RuntimeException(__t('workflow_requirement_attachment_store_context_required'));
        }

        $this->validateRequirementUploadedAttachments($inputName, $requireAtLeastOne);

        $uploadDir = $this->requirementAttachmentStorageRoot() . DIRECTORY_SEPARATOR . $requirementID;
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException(__t('workflow_requirement_attachment_storage_create_failed'));
        }

        $uploadedCount = 0;
        foreach ($this->normalizeRequirementUploadedFiles($inputName) as $file) {
            $rawOriginalName = trim((string)($file['name'] ?? ''));
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE && $rawOriginalName === '') {
                continue;
            }

            $originalName = $this->safeRequirementAttachmentOriginalName($rawOriginalName);
            $tmpPath = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $storedFileName = gmdate('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . $extension;
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $storedFileName;

            if (!move_uploaded_file($tmpPath, $targetPath)) {
                throw new \RuntimeException(__t('workflow_requirement_attachment_store_file_failed', ['file' => $originalName]));
            }

            $attachmentId = $model->saveAttachment($requirementID, [
                'OriginalFileName' => $originalName,
                'StoredFileName' => $storedFileName,
                'StoragePath' => $targetPath,
                'MimeType' => $this->detectRequirementAttachmentMimeType($targetPath),
                'FileSizeBytes' => $size,
                'UploadedByUserID' => $currentUserId,
            ]);

            if ($attachmentId <= 0) {
                @unlink($targetPath);
                throw new \RuntimeException($model->getLastError() ?: __t('workflow_requirement_attachment_metadata_save_failed'));
            }

            $this->auditEvent('ATTACHMENT_UPLOAD', 'WorkflowRequirement', (string)$requirementID, [
                'WorkflowRequirementAttachmentID' => $attachmentId,
                'OriginalFileName' => $originalName,
                'FileSizeBytes' => $size,
            ]);

            $uploadedCount++;
        }

        return $uploadedCount;
    }

    private function validateRequirementUploadedAttachments(string $inputName, bool $requireAtLeastOne = false): void
    {
        $seen = false;
        foreach ($this->normalizeRequirementUploadedFiles($inputName) as $file) {
            $originalName = trim((string)($file['name'] ?? ''));
            $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE && $originalName === '') {
                continue;
            }
            $seen = true;

            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new \RuntimeException($this->requirementUploadErrorMessage($errorCode));
            }

            $tmpPath = (string)($file['tmp_name'] ?? '');
            $size = (int)($file['size'] ?? 0);
            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                throw new \RuntimeException(__t('workflow_requirement_attachment_payload_invalid'));
            }
            if ($size <= 0) {
                throw new \RuntimeException(__t('workflow_requirement_attachment_empty', ['file' => $this->safeRequirementAttachmentOriginalName($originalName)]));
            }
            if ($size > $this->requirementAttachmentMaxBytes()) {
                throw new \RuntimeException(__t('workflow_requirement_attachment_too_large', ['file' => $this->safeRequirementAttachmentOriginalName($originalName)]));
            }

            $extension = strtolower(pathinfo($this->safeRequirementAttachmentOriginalName($originalName), PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, $this->requirementAttachmentAllowedExtensions(), true)) {
                throw new \RuntimeException(__t('workflow_requirement_attachment_type_not_allowed', ['file' => $this->safeRequirementAttachmentOriginalName($originalName)]));
            }
        }

        if ($requireAtLeastOne && !$seen) {
            throw new \RuntimeException(__t('workflow_requirement_choose_file'));
        }
    }

    /**
     * @return array<int, array{name: mixed, type: mixed, tmp_name: mixed, error: mixed, size: mixed}>
     */
    private function normalizeRequirementUploadedFiles(string $inputName): array
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

    private function requirementAttachmentStorageRoot(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'workflow-requirement-attachments';
    }

    /**
     * @return array<int, string>
     */
    private function requirementAttachmentAllowedExtensions(): array
    {
        return ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'zip'];
    }

    private function requirementAttachmentMaxBytes(): int
    {
        return 25 * 1024 * 1024;
    }

    private function safeRequirementAttachmentOriginalName(string $name): string
    {
        $name = trim(basename(str_replace('\\', '/', $name)));
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?? 'attachment';
        $name = trim($name, " .\t\n\r\0\x0B");
        return $name !== '' ? substr($name, 0, 255) : 'attachment';
    }

    private function detectRequirementAttachmentMimeType(string $path): ?string
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

    private function resolveRequirementAttachmentPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $root = realpath($this->requirementAttachmentStorageRoot());
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

    private function canReviewRequirements(): bool
    {
        return $this->canEditRequirements();
    }

    private function canApproveRequirements(): bool
    {
        return Rbac::canAny(['WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function canCreateRequirements(): bool
    {
        return Rbac::canAny(['WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function canViewRequirements(): bool
    {
        return Rbac::canAny(['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function canEditRequirements(): bool
    {
        return Rbac::canAny(['WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function canDeleteRequirements(): bool
    {
        return Rbac::canAny(['WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function canCreateWorkflowTasks(): bool
    {
        return Rbac::canAny(['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']);
    }

    private function statusRequiresApprovalPermission(string $statusCode): bool
    {
        return in_array(strtoupper(trim($statusCode)), ['APPROVED', 'IN_BUILD', 'IN_TEST', 'COMPLETED'], true);
    }

    private function requirementUploadErrorMessage(int $errorCode): string
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
}
