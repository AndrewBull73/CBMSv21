<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BaseConfigurationReadinessModel;
use App\Models\WorkflowTaskStatusesAdminModel;

require_once __DIR__ . '/../../shared/csrf.php';

final class WorkflowTaskStatusesController extends BaseController
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

        $model = new WorkflowTaskStatusesAdminModel($this->db);
        $this->render('config/WorkflowTaskStatusesList', [
            'title' => 'Workflow Task Statuses',
            'rows' => $model->listRows($filters),
            'filters' => $filters,
            'contextLabels' => $this->buildContextLabels(),
        ]);
    }

    public function form(): void
    {
        $statusId = (int) ($_GET['id'] ?? 0);
        $model = new WorkflowTaskStatusesAdminModel($this->db);
        $record = $statusId > 0 ? $model->getById($statusId) : null;

        $this->render('config/WorkflowTaskStatusesForm', [
            'title' => $record !== null ? 'Edit Workflow Task Status' : 'Create Workflow Task Status',
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
            header('Location: index.php?route=workflow-task-statuses/list');
            return;
        }

        $payload = [
            'StatusID' => (int) ($_POST['StatusID'] ?? 0),
            'Code' => trim((string) ($_POST['Code'] ?? '')),
            'Name' => trim((string) ($_POST['Name'] ?? '')),
            'IsActive' => isset($_POST['IsActive']) ? 1 : 0,
        ];

        $model = new WorkflowTaskStatusesAdminModel($this->db);
        try {
            $model->save($payload);
            $this->flashSuccess($payload['StatusID'] > 0 ? 'Workflow task status updated.' : 'Workflow task status created.');
            header('Location: index.php?route=workflow-task-statuses/list');
            return;
        } catch (\Throwable $e) {
            $this->flashError('Save failed: ' . $e->getMessage());
            $query = http_build_query([
                'route' => 'workflow-task-statuses/form',
                'id' => (int) ($payload['StatusID'] ?? 0),
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
