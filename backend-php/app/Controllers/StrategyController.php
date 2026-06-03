<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;

final class StrategyController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_VIEW', 'STRATEGY_REPORT_VIEW', 'STRATEGY_FISCAL_EDIT', 'STRATEGY_WORKFLOW_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'workflowTransition' => ['auth' => true, 'permsAny' => ['STRATEGY_WORKFLOW_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
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
