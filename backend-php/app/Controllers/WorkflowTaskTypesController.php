<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BaseConfigurationReadinessModel;
use App\Models\WorkflowTaskTypesAdminModel;
use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';

final class WorkflowTaskTypesController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'form' => ['auth' => true, 'permsAny' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'save' => ['auth' => true, 'permsAny' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function list(): void
    {
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'active' => trim((string) ($_GET['active'] ?? '1')),
        ];

        $model = new WorkflowTaskTypesAdminModel($this->db);
        $this->render('config/WorkflowTaskTypesList', [
            'title' => 'Workflow Task Types',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'contextLabels' => $this->buildContextLabels(),
        ]);
    }

    public function form(): void
    {
        $taskTypeId = (int) ($_GET['id'] ?? 0);
        $model = new WorkflowTaskTypesAdminModel($this->db);
        $record = $taskTypeId > 0 ? $model->getById($taskTypeId) : null;

        $this->render('config/WorkflowTaskTypesForm', [
            'title' => $record !== null ? 'Edit Workflow Task Type' : 'Create Workflow Task Type',
            'record' => $record,
            'contextLabels' => $this->buildContextLabels(),
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
            header('Location: index.php?route=workflow-task-types/list');
            return;
        }

        $payload = [
            'TaskTypeID' => (int) ($_POST['TaskTypeID'] ?? 0),
            'Code' => trim((string) ($_POST['Code'] ?? '')),
            'Name' => trim((string) ($_POST['Name'] ?? '')),
            'IsActive' => isset($_POST['IsActive']) ? 1 : 0,
        ];

        $model = new WorkflowTaskTypesAdminModel($this->db);
        try {
            $model->save($payload, (string) SessionHelper::get('auth.username', 'system'));
            $this->flashSuccess($payload['TaskTypeID'] > 0 ? 'Workflow task type updated.' : 'Workflow task type created.');
            header('Location: index.php?route=workflow-task-types/list');
            return;
        } catch (\Throwable $e) {
            $this->flashError('Save failed: ' . $e->getMessage());
            $query = http_build_query([
                'route' => 'workflow-task-types/form',
                'id' => (int) ($payload['TaskTypeID'] ?? 0),
            ]);
            header('Location: index.php?' . $query);
            return;
        }
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
