<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\WorkflowLinkModel;
use App\Models\WorkflowProjectModel;
use App\Models\WorkflowRequirementModel;
use App\Models\WorkflowTaskModel;
use App\Shared\SessionHelper;

final class WorkflowProjectController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'summary' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    public function list(): void
    {
        $model = new WorkflowProjectModel($this->db);
        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'status' => strtoupper(trim((string)($_GET['status'] ?? ''))),
            'active' => trim((string)($_GET['active'] ?? '1')),
        ];

        $this->render('workflow/WorkflowProjectList', [
            'title' => __t('workflow_projects'),
            'rows' => $model->listProjects($filters['q'], $filters['status'], $filters['active']),
            'filters' => $filters,
            'statusOptions' => $model->statusOptions(),
            'tableInstalled' => $model->supportsWorkflowProjects(),
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function summary(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $returnTo = $this->normalizeProjectReturnTo((string)($_GET['returnTo'] ?? ''));
        $backUrl = $this->defaultProjectBackUrl($returnTo);
        $model = new WorkflowProjectModel($this->db);
        $taskModel = new WorkflowTaskModel($this->db);
        $linkModel = new WorkflowLinkModel($this->db);
        $requirementModel = new WorkflowRequirementModel($this->db);
        $tableInstalled = $model->supportsWorkflowProjects();

        if (!$tableInstalled) {
            $this->flashError(__t('workflow_project_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: index.php?route=workflow-projects/list');
            return;
        }

        $record = $tableInstalled && $id > 0 ? $model->findProject($id) : null;
        if ($id <= 0 || !$record) {
            $this->flashError(__t('workflow_project_not_found'));
            header('Location: ' . $backUrl);
            return;
        }

        $this->render('workflow/WorkflowProjectSummary', [
            'title' => __t('workflow_project_summary'),
            'record' => $record,
            'projectUsers' => $model->listProjectUsers($id),
            'projectTasks' => $taskModel->listProjectTaskOptions($id),
            'projectLinks' => $linkModel->listProjectLinks($id, 50),
            'projectRequirements' => $requirementModel->listHighLevelRequirements($id),
            'projectLinkSummary' => $linkModel->summarizeProjectLinks($id),
            'requirementStatusOptions' => $requirementModel->statusOptions(),
            'requirementPriorityOptions' => $requirementModel->priorityOptions(),
            'workflowLinkTypeOptions' => $linkModel->linkTypeOptions(),
            'workflowLinksInstalled' => $linkModel->supportsWorkflowLinks(),
            'statusOptions' => $model->statusOptions(),
            'tableInstalled' => $tableInstalled,
            'backUrl' => $backUrl,
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function form(): void
    {
        $id = (int)($_GET['id'] ?? 0);
        $returnTo = $this->normalizeProjectReturnTo((string)($_GET['returnTo'] ?? ''));
        $backUrl = $this->defaultProjectBackUrl($returnTo);
        $model = new WorkflowProjectModel($this->db);
        $taskModel = new WorkflowTaskModel($this->db);
        $userModel = new UserModel($this->db);
        $tableInstalled = $model->supportsWorkflowProjects();

        $record = $tableInstalled && $id > 0 ? $model->findProject($id) : null;
        if ($id > 0 && $tableInstalled && !$record) {
            $this->flashError(__t('workflow_project_not_found'));
            header('Location: ' . $backUrl);
            return;
        }

        $this->render('workflow/WorkflowProjectForm', [
            'title' => $id > 0 ? __t('workflow_project_edit') : __t('workflow_project_create'),
            'record' => $record,
            'projectTasks' => $tableInstalled && $id > 0 ? $taskModel->listProjectTaskOptions($id) : [],
            'users' => method_exists($userModel, 'listAll') ? $userModel->listAll() : [],
            'statusOptions' => $model->statusOptions(),
            'tableInstalled' => $tableInstalled,
            'returnTo' => $returnTo,
            'backUrl' => $backUrl,
            'flash' => SessionHelper::get('flash.message', null),
        ]);

        if (SessionHelper::has('flash.message')) {
            SessionHelper::forget('flash.message');
        }
    }

    public function save(): void
    {
        $this->assertPostWithCsrf('index.php?route=workflow-projects/list');

        $returnTo = $this->normalizeProjectReturnTo((string)($_POST['returnTo'] ?? ''));
        $model = new WorkflowProjectModel($this->db);
        if (!$model->supportsWorkflowProjects()) {
            $this->flashError(__t('workflow_project_tables_missing', ['script' => 'backend-php/config/sql/create_workflow_projects.sql']));
            header('Location: ' . $this->defaultProjectBackUrl($returnTo));
            return;
        }

        $id = (int)($_POST['WorkflowProjectID'] ?? 0);
        $payload = [
            'WorkflowProjectID' => $id,
            'ProjectCode' => trim((string)($_POST['ProjectCode'] ?? '')),
            'ProjectName' => trim((string)($_POST['ProjectName'] ?? '')),
            'Description' => trim((string)($_POST['Description'] ?? '')),
            'ProjectOwnerUserID' => ($_POST['ProjectOwnerUserID'] ?? '') !== '' ? (int)$_POST['ProjectOwnerUserID'] : null,
            'ProjectStatusCode' => strtoupper(trim((string)($_POST['ProjectStatusCode'] ?? 'PLANNED'))),
            'StartDate' => trim((string)($_POST['StartDate'] ?? '')),
            'TargetEndDate' => trim((string)($_POST['TargetEndDate'] ?? '')),
            'ActualEndDate' => trim((string)($_POST['ActualEndDate'] ?? '')),
            'Active' => isset($_POST['Active']) ? 1 : 0,
            'ProjectUserIDs' => $_POST['ProjectUserIDs'] ?? [],
        ];
        $currentUserId = (int)SessionHelper::get('auth.user_id', 0);
        $validationError = $this->validateProjectPayload($payload, $model);
        if ($validationError !== '') {
            $this->flashError($validationError);
            header('Location: ' . $this->projectFormRedirectUrl($id, $returnTo));
            return;
        }

        try {
            $savedId = $model->saveProject($payload, $currentUserId);
            $this->auditEvent($id > 0 ? 'UPDATE' : 'CREATE', 'WorkflowProject', (string)$savedId, [
                'ProjectCode' => $payload['ProjectCode'],
                'ProjectName' => $payload['ProjectName'],
                'ProjectStatusCode' => $payload['ProjectStatusCode'],
                'Active' => $payload['Active'],
                'ProjectUserIDs' => $payload['ProjectUserIDs'],
            ]);
            $this->flashSuccess($id > 0 ? __t('workflow_project_updated') : __t('workflow_project_created'));
            header('Location: ' . $this->defaultProjectBackUrl($returnTo));
            return;
        } catch (\InvalidArgumentException $e) {
            $message = $e->getMessage() === 'Project code already exists.'
                ? __t('workflow_project_code_duplicate')
                : $e->getMessage();
            $this->flashError($message);
            header('Location: ' . $this->projectFormRedirectUrl($id, $returnTo));
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('WorkflowProjectController::save failed', $e, [
                'WorkflowProjectID' => $id,
                'ProjectName' => $payload['ProjectName'],
            ]);
            $this->flashError(__t('workflow_project_save_failed') . ': ' . $e->getMessage());
            header('Location: ' . $this->projectFormRedirectUrl($id, $returnTo));
            return;
        }
    }

    private function normalizeProjectReturnTo(string $returnTo): string
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
            'workflow-projects/list',
            'workflow-projects/summary',
            'workflow-projects/form',
            'workflow/list',
            'workflow/edit',
            'workflow-requirements/list',
            'workflow-requirements/summary',
            'workflow-requirements/matrix',
            'workflow-requirements/form',
        ];
        if (!in_array($route, $allowedRoutes, true)) {
            return '';
        }

        return $returnTo;
    }

    private function defaultProjectBackUrl(string $returnTo): string
    {
        return $returnTo !== '' ? $returnTo : 'index.php?route=workflow-projects/list';
    }

    private function projectFormRedirectUrl(int $id, string $returnTo = ''): string
    {
        $params = ['route' => 'workflow-projects/form'];
        if ($id > 0) {
            $params['id'] = $id;
        }
        if ($returnTo !== '') {
            $params['returnTo'] = $returnTo;
        }
        return 'index.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function validateProjectPayload(array $payload, WorkflowProjectModel $model): string
    {
        if (trim((string)($payload['ProjectName'] ?? '')) === '') {
            return __t('workflow_project_name_required');
        }

        $projectCode = trim((string)($payload['ProjectCode'] ?? ''));
        $projectId = (int)($payload['WorkflowProjectID'] ?? 0);
        if ($projectCode !== '' && $model->projectCodeExists($projectCode, $projectId)) {
            return __t('workflow_project_code_duplicate');
        }

        $dateLabels = [
            'StartDate' => __t('workflow_project_start_date'),
            'TargetEndDate' => __t('workflow_project_target_end_date'),
            'ActualEndDate' => __t('workflow_project_actual_end_date'),
        ];
        $dates = [];
        foreach ($dateLabels as $field => $label) {
            $rawDate = trim((string)($payload[$field] ?? ''));
            if ($rawDate === '') {
                continue;
            }
            $date = $this->parseProjectDate($rawDate);
            if (!$date) {
                return __t('workflow_project_date_invalid', ['field' => $label]);
            }
            $dates[$field] = $date;
        }

        if (isset($dates['StartDate'], $dates['TargetEndDate']) && $dates['TargetEndDate'] < $dates['StartDate']) {
            return __t('workflow_project_target_before_start');
        }
        if (isset($dates['StartDate'], $dates['ActualEndDate']) && $dates['ActualEndDate'] < $dates['StartDate']) {
            return __t('workflow_project_actual_before_start');
        }

        return '';
    }

    private function parseProjectDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($errors)
            && (((int)($errors['warning_count'] ?? 0) > 0) || ((int)($errors['error_count'] ?? 0) > 0));

        if (!$date || $hasErrors || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
