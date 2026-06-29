<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Models\SegmentValuesAdminModel;
use App\Shared\SessionHelper;

final class StrategyController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_VIEW', 'STRATEGY_REPORT_VIEW', 'STRATEGY_FISCAL_EDIT', 'STRATEGY_WORKFLOW_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'index' => ['auth' => true, 'permsAny' => ['STRATEGY_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'framework-guide' => ['auth' => true, 'permsAny' => ['STRATEGY_VIEW', 'STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'summary' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'readiness' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'submissionReadiness' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'sectorBudget' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'programBudget' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'projectBudget' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'programStructure' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'segmentParentChild' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'mtff' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'performance' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'overview' => ['auth' => true, 'permsAny' => ['STRATEGY_FISCAL_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'ceiling-vs-plan' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'STRATEGY_FISCAL_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'transition' => ['auth' => true, 'permsAny' => ['STRATEGY_WORKFLOW_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'workflowTransition' => ['auth' => true, 'permsAny' => ['STRATEGY_WORKFLOW_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'segmentParentChildIssueLookup' => ['auth' => true, 'permsAny' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'deleteSegmentParentChildIssueRow' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'resolveSegmentParentLinks' => ['auth' => true, 'permsAny' => ['BASE_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function index(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $labels = $model->getContextLabels($fy, $ver);
        $overview = $model->getOverview($fy, $ver);
        $workflowState = $model->getStrategicWorkflowState($fy, $ver);
        $sectorTotals = $model->listSectorTotals($fy, $ver, 8);
        $programTotals = $model->listProgramTotals($fy, $ver, 10);
        $economicTotals = $model->listEconomicTotals($fy, $ver, 10);
        $narratives = $model->listRecentNarratives($fy, $ver, 8);

        $this->render('strategy/Index', [
            'title' => 'Strategic Budgeting',
            'contextLabels' => $labels,
            'overview' => $overview,
            'workflowState' => $workflowState,
            'sectorTotals' => $sectorTotals,
            'programTotals' => $programTotals,
            'economicTotals' => $economicTotals,
            'narratives' => $narratives,
        ]);
    }

    public function summary(): void
    {
        $this->index();
    }

    public function frameworkGuide(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/FrameworkGuide', [
            'title' => 'Strategic Budget Framework Guide',
            'contextLabels' => $model->getContextLabels($fy, $ver),
        ]);
    }

    public function sectorBudget(): void
    {
        $model = $this->buildModel();
        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $projectId = (int) ($_GET['project_id'] ?? 0);

        $this->render('strategy/ReportSectorBudget', [
            'title' => 'Sector Budget Report',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'records' => $model->getSectorBudgetReport($fy, $ver, $projectId > 0 ? $projectId : null),
            'projectOptions' => $admin->listProjectOptions(),
            'selectedProjectId' => $projectId,
        ]);
    }

    public function programBudget(): void
    {
        $model = $this->buildModel();
        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $projectId = (int) ($_GET['project_id'] ?? 0);

        $this->render('strategy/ReportProgramBudget', [
            'title' => 'Program Budget Report',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'records' => $model->getProgramBudgetReport($fy, $ver, $projectId > 0 ? $projectId : null),
            'projectOptions' => $admin->listProjectOptions(),
            'selectedProjectId' => $projectId,
        ]);
    }

    public function projectBudget(): void
    {
        $model = $this->buildModel();
        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $projectId = (int) ($_GET['project_id'] ?? 0);

        $this->render('strategy/ReportProjectBudget', [
            'title' => 'Project Budget Report',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'records' => $model->getProjectBudgetReport($fy, $ver, $projectId > 0 ? $projectId : null),
            'projectOptions' => $admin->listProjectOptions(),
            'selectedProjectId' => $projectId,
        ]);
    }

    public function programStructure(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $diagnostics = $model->getProgramStructureDiagnostics($fy);

        $this->render('strategy/ReportProgramStructure', [
            'title' => 'Program Structure Diagnostics',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'summary' => $diagnostics['summary'] ?? [],
            'programCodeConflicts' => $diagnostics['program_code_conflicts'] ?? [],
            'programNameConflicts' => $diagnostics['program_name_conflicts'] ?? [],
            'subProgramCodeConflicts' => $diagnostics['sub_program_code_conflicts'] ?? [],
            'subProgramPrefixIssues' => $diagnostics['sub_program_prefix_issues'] ?? [],
        ]);
    }

    public function segmentParentChild(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $sessionKey = 'strategy.segment_parent_child.' . $fy;
        $hasSubmittedSegments = array_key_exists('child_segment_no', $_GET)
            || array_key_exists('parent_segment_no', $_GET);
        $lastSelectionRaw = SessionHelper::get($sessionKey, []);
        $lastSelection = is_array($lastSelectionRaw) ? $lastSelectionRaw : [];

        if ($hasSubmittedSegments) {
            $childSegmentNo = (int) ($_GET['child_segment_no'] ?? 0);
            $parentSegmentNo = (int) ($_GET['parent_segment_no'] ?? 0);
            $requireSameDataObjectCode = !isset($_GET['same_data_object']) || (string) ($_GET['same_data_object'] ?? '1') === '1';
            $checkCodePrefix = !isset($_GET['check_prefix']) || (string) ($_GET['check_prefix'] ?? '1') === '1';

            if ($childSegmentNo > 0 && $parentSegmentNo > 0) {
                SessionHelper::set($sessionKey, [
                    'child_segment_no' => $childSegmentNo,
                    'parent_segment_no' => $parentSegmentNo,
                    'same_data_object' => $requireSameDataObjectCode ? 1 : 0,
                    'check_prefix' => $checkCodePrefix ? 1 : 0,
                ]);
            } else {
                SessionHelper::forget($sessionKey);
            }
        } else {
            $childSegmentNo = (int) ($lastSelection['child_segment_no'] ?? 0);
            $parentSegmentNo = (int) ($lastSelection['parent_segment_no'] ?? 0);
            $requireSameDataObjectCode = (int) ($lastSelection['same_data_object'] ?? 1) === 1;
            $checkCodePrefix = (int) ($lastSelection['check_prefix'] ?? 1) === 1;
        }

        $diagnostics = $model->getSegmentParentChildDiagnostics(
            $fy,
            $childSegmentNo,
            $parentSegmentNo,
            $requireSameDataObjectCode,
            $checkCodePrefix
        );

        $this->render('strategy/ReportSegmentParentChild', [
            'title' => 'Segment Parent-Child Check',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'summary' => $diagnostics['summary'] ?? [],
            'segmentOptions' => $diagnostics['segment_options'] ?? [],
            'issueCounts' => $diagnostics['issue_counts'] ?? [],
            'issues' => $diagnostics['issues'] ?? [],
            'resolvedLinks' => $diagnostics['resolved_links'] ?? [],
        ]);
    }

    public function resolveSegmentParentLinks(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=strategy-reports/segment-parent-child');
            return;
        }

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $childSegmentNo = (int) ($_POST['child_segment_no'] ?? 0);
        $parentSegmentNo = (int) ($_POST['parent_segment_no'] ?? 0);
        $sameDataObject = (string) ($_POST['same_data_object'] ?? '1');
        $checkPrefix = (string) ($_POST['check_prefix'] ?? '1');

        try {
            $resolver = new SegmentValuesAdminModel($this->db);
            $result = $resolver->resolveParentSegmentValueIds($fy);
            $parentDataObjectRows = (int) ($result['ParentDataObjectRowsUpdated'] ?? 0);
            $parentValueRows = (int) ($result['ParentValueRowsUpdated'] ?? 0);
            $missingRows = (int) ($result['MissingParentValueIDRows'] ?? 0);

            $message = "Parent links resolved. Parent data-object rows updated: {$parentDataObjectRows}. Parent value IDs updated: {$parentValueRows}.";
            if ($missingRows > 0) {
                $message .= " Rows still missing ParentSegmentValueID: {$missingRows}.";
                $this->flashError($message);
            } else {
                $this->flashSuccess($message);
            }
            $this->auditEvent('RESOLVE_PARENT_LINKS', 'SegmentValue', (string) $fy, [
                'FiscalYearID' => $fy,
                'ParentSegmentNo' => $parentSegmentNo,
                'ChildSegmentNo' => $childSegmentNo,
                'ParentDataObjectRowsUpdated' => $parentDataObjectRows,
                'ParentValueRowsUpdated' => $parentValueRows,
                'MissingParentValueIDRows' => $missingRows,
            ]);
        } catch (\Throwable $e) {
            $this->logHandledException('StrategyController::resolveSegmentParentLinks failed', $e, [
                'fiscalYearId' => $fy,
                'parentSegmentNo' => $parentSegmentNo,
                'childSegmentNo' => $childSegmentNo,
            ]);
            $this->flashError('Parent link resolution failed: ' . $e->getMessage());
        }

        $query = http_build_query([
            'route' => 'strategy-reports/segment-parent-child',
            'parent_segment_no' => $parentSegmentNo,
            'child_segment_no' => $childSegmentNo,
            'same_data_object' => $sameDataObject,
            'check_prefix' => $checkPrefix,
        ]);
        header('Location: index.php?' . $query);
    }

    public function segmentParentChildIssueLookup(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $segmentValueId = (int) ($_GET['segment_value_id'] ?? 0);
            if ($segmentValueId <= 0) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'message' => 'SegmentValueID is required.',
                ]);
                return;
            }

            $model = $this->buildModel();
            echo json_encode([
                'ok' => true,
                'lookup' => $model->getSegmentParentChildIssueLookup($segmentValueId),
            ]);
        } catch (\Throwable $e) {
            $this->logHandledException('StrategyController::segmentParentChildIssueLookup failed', $e, [
                'segmentValueId' => (int) ($_GET['segment_value_id'] ?? 0),
            ]);
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Lookup failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function deleteSegmentParentChildIssueRow(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'ok' => false,
                'message' => __t('method_not_allowed'),
            ]);
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'message' => __t('security_check_failed'),
            ]);
            return;
        }

        $segmentValueId = (int) ($_POST['segment_value_id'] ?? 0);
        if ($segmentValueId <= 0) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'SegmentValueID is required.',
            ]);
            return;
        }

        try {
            $model = new SegmentValuesAdminModel($this->db);
            $deleted = $model->deleteById($segmentValueId);
            $this->auditEvent('DELETE', 'SegmentValue', (string) $segmentValueId, [
                'FiscalYearID' => (int) ($deleted['FiscalYearID'] ?? 0),
                'DataObjectCode' => (string) ($deleted['DataObjectCode'] ?? ''),
                'SegmentNo' => (int) ($deleted['SegmentNo'] ?? 0),
                'SegmentCode' => (string) ($deleted['SegmentCode'] ?? ''),
                'Source' => 'Segment Parent-Child Check',
            ]);

            echo json_encode([
                'ok' => true,
                'message' => 'Segment value deleted.',
                'deleted_segment_value_id' => $segmentValueId,
            ]);
        } catch (\Throwable $e) {
            $this->logHandledException('StrategyController::deleteSegmentParentChildIssueRow failed', $e, [
                'segmentValueId' => $segmentValueId,
            ]);
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Delete failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function mtff(): void
    {
        $model = $this->buildModel();
        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $projectId = (int) ($_GET['project_id'] ?? 0);

        $this->render('strategy/ReportMtff', [
            'title' => 'MTFF View',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'summaryRows' => $model->getMtffSummary($fy, $ver, 3, $projectId > 0 ? $projectId : null),
            'matrix' => $model->getMtffSectorMatrix($fy, $ver, 3, $projectId > 0 ? $projectId : null),
            'projectOptions' => $admin->listProjectOptions(),
            'selectedProjectId' => $projectId,
        ]);
    }

    public function performance(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/ReportPerformance', [
            'title' => 'Performance Framework Report',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'records' => $model->getPerformanceFrameworkReport($fy, $ver),
        ]);
    }

    public function fiscalOverview(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/FiscalOverview', [
            'title' => 'Fiscal Overview',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'dashboard' => $model->getFiscalFrameworkOverview($fy, $ver),
        ]);
    }

    public function resourceEnvelope(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/FiscalResourceEnvelope', [
            'title' => 'Resource Envelope',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'report' => $model->getResourceEnvelopeReport($fy, $ver),
        ]);
    }

    public function sectorCeilings(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/FiscalSectorCeilings', [
            'title' => 'Sector Ceilings',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'report' => $model->getSectorCeilingReport($fy, $ver),
        ]);
    }

    public function ceilingVsPlan(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/FiscalCeilingVsPlan', [
            'title' => 'Ceiling vs Strategic Plan',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'report' => $model->getCeilingVsPlanReport($fy, $ver),
        ]);
    }

    public function readiness(): void
    {
        $this->submissionReadiness();
    }

    public function submissionReadiness(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $dashboard = $model->getSubmissionReadinessDashboard($fy, $ver);

        $this->render('strategy/ReportReadiness', [
            'title' => 'Submission Readiness',
            'contextLabels' => $model->getContextLabels($fy, $ver),
            'workflowState' => $model->getStrategicWorkflowState($fy, $ver),
            'summary' => $dashboard['summary'] ?? [],
            'checks' => $dashboard['checks'] ?? [],
            'readinessType' => 'submission',
        ]);
    }

    public function workflowTransition(): void
    {
        $returnRoute = trim((string) ($_POST['return_route'] ?? 'strategy/index'));
        $this->assertPostWithCsrf('index.php?route=' . ($returnRoute !== '' ? $returnRoute : 'strategy/index'));

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $action = strtolower(trim((string) ($_POST['workflow_action'] ?? '')));

        if ($fy <= 0 || $ver <= 0) {
            $this->flashError('Fiscal context is required before changing strategic workflow state.');
            header('Location: index.php?route=' . ($returnRoute !== '' ? $returnRoute : 'strategy/index'));
            exit;
        }

        try {
            $state = $this->buildModel()->transitionStrategicWorkflow(
                $fy,
                $ver,
                $action,
                (int) \App\Shared\SessionHelper::get('auth.user_id', 1),
                $this->nullableTrim($_POST['status_note'] ?? null)
            );
            $this->flashSuccess('Strategic version is now ' . (string) ($state['WorkflowStatusLabel'] ?? 'updated') . '.');
        } catch (\Throwable $e) {
            $this->flashError('Strategic workflow update failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=' . ($returnRoute !== '' ? $returnRoute : 'strategy/index'));
        exit;
    }

    private function buildModel(): StrategicBudgetingModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new StrategicBudgetingModel($this->db);
    }

    private function buildAdminModel(): StrategicBudgetingAdminModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }

        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }

        return new StrategicBudgetingAdminModel($this->db);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
