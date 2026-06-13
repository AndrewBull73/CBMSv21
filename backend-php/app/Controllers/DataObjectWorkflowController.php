<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DataObjectWorkflowStatusModel;
use App\Models\BaseConfigurationReadinessModel;
use App\Shared\SessionHelper;

class DataObjectWorkflowController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN']],
        'index' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN']],
        'buildStatuses' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN']],
        'saveStatuses' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN']],
        'getStatus' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN', 'STRATEGY_VIEW', 'STRATEGY_SETUP_EDIT', 'STRATEGY_SUBMISSION_PREPARE']],
        'setStatus' => ['auth' => true, 'permsAny' => ['DATAOBJECTCODES_ADMIN']],
    ];

    private DataObjectWorkflowStatusModel $model;

    public function __construct()
    {
        parent::__construct();

        // Explicitly load DB
        require __DIR__ . '/../../config/db.php'; // defines $conn (PDO)
        require_once __DIR__ . '/../Models/DataObjectWorkflowStatusModel.php';
        $this->model = new DataObjectWorkflowStatusModel($conn);
    }

    public function index(): void
    {
        require_once __DIR__ . '/../../shared/csrf.php';

        $fy = (int)SessionHelper::get('FiscalYearID', 0);
        $ver = (int)SessionHelper::get('VersionID', 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pageSize = max(1, min(200, (int)($_GET['pageSize'] ?? 50)));
        $q = trim((string)($_GET['q'] ?? ''));
        $status = strtoupper(trim((string)($_GET['status'] ?? '')));

        $summary = [
            'total_codes' => 0,
            'set_count' => 0,
            'missing_count' => 0,
            'open_count' => 0,
            'closed_count' => 0,
        ];

        try {
            $result = $this->model->listPaged($fy, $ver, $page, $pageSize, $q, $status);
            $summary = $this->model->summary($fy, $ver);
        } catch (\Throwable $e) {
            $this->logHandledException('DataObjectWorkflowController::index failed', $e, [
                'FiscalYearID' => $fy,
                'VersionID' => $ver,
            ]);
            $this->flashError('Failed to load workflow statuses: ' . $e->getMessage());
            $result = ['rows' => [], 'total' => 0];
        }

        $this->render('dataobjectcodes/DataObjectWorkflowStatusList', [
            'title' => 'Data Object Code Workflow Status',
            'fiscalYearId' => $fy,
            'versionId' => $ver,
            'rows' => $result['rows'] ?? [],
            'total' => (int)($result['total'] ?? 0),
            'summary' => $summary,
            'page' => $page,
            'pageSize' => $pageSize,
            'q' => $q,
            'status' => $status,
            'statusOptions' => $this->model->statusOptions(),
            'contextLabels' => $this->buildContextLabels($fy, $ver),
            '_csrf' => csrf_token(),
        ]);
    }

    private function buildContextLabels(int $fy, int $ver): array
    {
        if (!$this->db instanceof \PDO) {
            return [];
        }

        require_once __DIR__ . '/../Models/BaseConfigurationReadinessModel.php';
        $model = new BaseConfigurationReadinessModel($this->db);
        return $model->getContextLabels($fy, $ver);
    }

    public function buildStatuses(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        require_once __DIR__ . '/../../shared/csrf.php';
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError('Security check failed.');
            header('Location: index.php?route=dataobjectworkflow/statuses');
            return;
        }

        $fy = (int)SessionHelper::get('FiscalYearID', 0);
        $ver = (int)SessionHelper::get('VersionID', 0);
        $userId = (int)SessionHelper::get('auth.user_id', 0);
        $defaultStatus = (string)($_POST['DefaultStatus'] ?? 'OPEN');

        try {
            $created = $this->model->buildMissingStatuses($fy, $ver, $defaultStatus, $userId);
            $this->auditEvent('BUILD', 'DataObjectWorkflowStatus', $fy . ':' . $ver, [
                'FiscalYearID' => $fy,
                'VersionID' => $ver,
                'DefaultStatus' => strtoupper(trim($defaultStatus)),
                'Created' => $created,
            ]);
            $this->flashSuccess('Workflow status build completed. Created: ' . $created . '.');
        } catch (\Throwable $e) {
            $this->logHandledException('DataObjectWorkflowController::buildStatuses failed', $e, [
                'FiscalYearID' => $fy,
                'VersionID' => $ver,
            ]);
            $this->flashError('Build failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=dataobjectworkflow/statuses');
    }

    public function saveStatuses(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            return;
        }

        require_once __DIR__ . '/../../shared/csrf.php';
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError('Security check failed.');
            header('Location: index.php?route=dataobjectworkflow/statuses');
            return;
        }

        $fy = (int)SessionHelper::get('FiscalYearID', 0);
        $ver = (int)SessionHelper::get('VersionID', 0);
        $userId = (int)SessionHelper::get('auth.user_id', 0);
        $statuses = is_array($_POST['statuses'] ?? null) ? $_POST['statuses'] : [];

        try {
            $updated = $this->model->saveStatuses($fy, $ver, $statuses, $userId);
            $this->auditEvent('UPDATE', 'DataObjectWorkflowStatus', $fy . ':' . $ver, [
                'FiscalYearID' => $fy,
                'VersionID' => $ver,
                'Updated' => $updated,
            ]);
            $this->flashSuccess('Workflow statuses saved. Updated: ' . $updated . '.');
        } catch (\Throwable $e) {
            $this->logHandledException('DataObjectWorkflowController::saveStatuses failed', $e, [
                'FiscalYearID' => $fy,
                'VersionID' => $ver,
            ]);
            $this->flashError('Save failed: ' . $e->getMessage());
        }

        $returnQuery = trim((string)($_POST['returnQuery'] ?? ''));
        $location = 'index.php?route=dataobjectworkflow/statuses';
        if ($returnQuery !== '') {
            $location .= '&' . ltrim($returnQuery, '&?');
        }
        header('Location: ' . $location);
    }

    // GET current status
    public function getStatus(): void
    {
        $fy   = (int)($_GET['FiscalYearID'] ?? 0);
        $ver  = (int)($_GET['VersionID'] ?? 0);
        $code = $_GET['DataObjectCode'] ?? '';

        if (!$fy || !$ver || !$code) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing parameters']);
            return;
        }

        $status = $this->model->getStatus($fy, $ver, $code);
        if ($status === null) {
            http_response_code(404);
            echo json_encode(['warning' => 'No workflow status found', 'status' => 'Not Set']);
            return;
        }

        echo json_encode(['status' => $status]);
    }

    // POST update status
public function setStatus(): void
{
    $fy     = (int)($_POST['FiscalYearID'] ?? 0);
    $ver    = (int)($_POST['VersionID'] ?? 0);
    $code   = $_POST['DataObjectCode'] ?? '';
    $status = $_POST['Status'] ?? '';
    $userId = (int) SessionHelper::get('auth.user_id', 0); // ✅ Use numeric UserID

    if (!$fy || !$ver || !$code || !$status || !$userId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing parameters']);
        return;
    }

    try {
        $this->model->setStatus($fy, $ver, $code, $status, $userId);
        echo json_encode(['success' => true, 'status' => $status]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'error'   => 'Failed to update status',
            'details' => $e->getMessage(),
        ]);
    }
}

}
