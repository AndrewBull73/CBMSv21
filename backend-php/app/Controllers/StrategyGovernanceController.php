<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Shared\SessionHelper;

final class StrategyGovernanceController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_GOVERNANCE_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function narratives(): void
    {
        $admin = $this->buildAdminModel();
        $reporting = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));
        $sectionCode = strtoupper(trim((string) ($_GET['section_code'] ?? '')));

        $this->render('strategy/NarrativeList', [
            'title' => 'BSP Narratives',
            'records' => $admin->listNarratives($fy, $ver, $q, $sectionCode !== '' ? $sectionCode : null),
            'contextLabels' => $reporting->getContextLabels($fy, $ver),
            'q' => $q,
            'sectionCode' => $sectionCode,
            'sectionOptions' => $this->narrativeSectionOptions(),
        ]);
    }

    public function narrativeForm(): void
    {
        $admin = $this->buildAdminModel();
        $reporting = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/NarrativeForm', [
            'title' => $id > 0 ? 'Edit BSP Narrative' : 'Create BSP Narrative',
            'record' => $id > 0 ? $admin->getNarrative($id) : null,
            'contextLabels' => $reporting->getContextLabels($fy, $ver),
            'sectionOptions' => $this->narrativeSectionOptions(),
            'orgUnitOptions' => $admin->listOrgUnitOptions(),
            'sectorOptions' => $admin->listSectorOptions(),
            'programOptions' => $admin->listProgramOptions(),
            'projectOptions' => $admin->listProjectOptions(),
            'supportsNarrativeProjectLink' => $admin->supportsNarrativeProjectLink(),
        ]);
    }

    public function saveNarrative(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-governance/narratives');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_POST['NarrativeID'] ?? 0);
        $sectionCode = strtoupper(trim((string) ($_POST['SectionCode'] ?? '')));
        $bodyText = trim((string) ($_POST['BodyText'] ?? ''));

        if ($fy <= 0 || $ver <= 0) {
            $this->flashError('Fiscal context is required before narrative entry.');
            header('Location: index.php?route=strategy-governance/narratives');
            exit;
        }
        if ($sectionCode === '' || $bodyText === '') {
            $this->flashError('Section and body text are required.');
            header('Location: index.php?route=strategy-governance/narrative-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $payload = [
            'VersionID' => $ver,
            'FiscalYearID' => $fy,
            'SectionCode' => $sectionCode,
            'OrgUnitID' => (int) ($_POST['OrgUnitID'] ?? 0) > 0 ? (int) $_POST['OrgUnitID'] : null,
            'SectorID' => (int) ($_POST['SectorID'] ?? 0) > 0 ? (int) $_POST['SectorID'] : null,
            'ProgramID' => (int) ($_POST['ProgramID'] ?? 0) > 0 ? (int) $_POST['ProgramID'] : null,
            'ProjectID' => (int) ($_POST['ProjectID'] ?? 0) > 0 ? (int) $_POST['ProjectID'] : null,
            'NarrativeTitle' => $this->nullableTrim($_POST['NarrativeTitle'] ?? null),
            'BodyText' => $bodyText,
            'SortOrder' => (int) ($_POST['SortOrder'] ?? 0),
            'LockedFlag' => isset($_POST['LockedFlag']) ? 1 : 0,
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        $this->buildAdminModel()->saveNarrative($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Narrative updated.' : 'Narrative created.');
        header('Location: index.php?route=strategy-governance/narratives');
        exit;
    }

    public function deleteNarrative(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-governance/narratives');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateNarrative($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Narrative archived.');
        }

        header('Location: index.php?route=strategy-governance/narratives');
        exit;
    }

    public function fiscalRisks(): void
    {
        $admin = $this->buildAdminModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $riskTypeCode = strtoupper(trim((string) ($_GET['risk_type_code'] ?? '')));

        $this->render('strategy/FiscalRiskList', [
            'title' => 'Fiscal Risks',
            'records' => $admin->listFiscalRisks($q, $riskTypeCode !== '' ? $riskTypeCode : null),
            'q' => $q,
            'riskTypeCode' => $riskTypeCode,
            'riskTypeOptions' => $this->riskTypeOptions(),
        ]);
    }

    public function fiscalRiskForm(): void
    {
        $admin = $this->buildAdminModel();
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/FiscalRiskForm', [
            'title' => $id > 0 ? 'Edit Fiscal Risk' : 'Create Fiscal Risk',
            'record' => $id > 0 ? $admin->getFiscalRisk($id) : null,
            'riskTypeOptions' => $this->riskTypeOptions(),
            'orgUnitOptions' => $admin->listOrgUnitOptions(),
            'projectOptions' => $admin->listProjectOptions(),
            'supportsFiscalRiskProjectLink' => $admin->supportsFiscalRiskProjectLink(),
        ]);
    }

    public function saveFiscalRisk(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-governance/fiscal-risks');

        $id = (int) ($_POST['FiscalRiskID'] ?? 0);
        $riskTypeCode = strtoupper(trim((string) ($_POST['RiskTypeCode'] ?? '')));
        $riskTitle = trim((string) ($_POST['RiskTitle'] ?? ''));

        if ($riskTypeCode === '' || $riskTitle === '') {
            $this->flashError('Risk type and title are required.');
            header('Location: index.php?route=strategy-governance/fiscal-risk-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $payload = [
            'RiskTypeCode' => $riskTypeCode,
            'RiskTitle' => $riskTitle,
            'RiskDescription' => $this->nullableTrim($_POST['RiskDescription'] ?? null),
            'LikelihoodScore' => $_POST['LikelihoodScore'] !== '' ? (int) $_POST['LikelihoodScore'] : null,
            'ImpactScore' => $_POST['ImpactScore'] !== '' ? (int) $_POST['ImpactScore'] : null,
            'EstimatedFiscalExposure' => $_POST['EstimatedFiscalExposure'] !== '' ? (float) $_POST['EstimatedFiscalExposure'] : null,
            'MitigationStrategy' => $this->nullableTrim($_POST['MitigationStrategy'] ?? null),
            'ProjectID' => (int) ($_POST['ProjectID'] ?? 0) > 0 ? (int) $_POST['ProjectID'] : null,
            'OwnerOrgUnitID' => (int) ($_POST['OwnerOrgUnitID'] ?? 0) > 0 ? (int) $_POST['OwnerOrgUnitID'] : null,
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        $this->buildAdminModel()->saveFiscalRisk($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Fiscal risk updated.' : 'Fiscal risk created.');
        header('Location: index.php?route=strategy-governance/fiscal-risks');
        exit;
    }

    public function deleteFiscalRisk(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-governance/fiscal-risks');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateFiscalRisk($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Fiscal risk archived.');
        }

        header('Location: index.php?route=strategy-governance/fiscal-risks');
        exit;
    }

    public function programRisks(): void
    {
        $admin = $this->buildAdminModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $programId = (int) ($_GET['program_id'] ?? 0);
        $fiscalRiskId = (int) ($_GET['fiscal_risk_id'] ?? 0);

        $this->render('strategy/ProgramRiskList', [
            'title' => 'Program Risk Links',
            'records' => $admin->listProgramRisks($q, $programId > 0 ? $programId : null, $fiscalRiskId > 0 ? $fiscalRiskId : null),
            'q' => $q,
            'programId' => $programId,
            'fiscalRiskId' => $fiscalRiskId,
            'programOptions' => $admin->listProgramOptions(),
            'fiscalRiskOptions' => $admin->listFiscalRiskOptions(),
        ]);
    }

    public function programRiskForm(): void
    {
        $admin = $this->buildAdminModel();
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/ProgramRiskForm', [
            'title' => $id > 0 ? 'Edit Program Risk Link' : 'Create Program Risk Link',
            'record' => $id > 0 ? $admin->getProgramRisk($id) : null,
            'programOptions' => $admin->listProgramOptions(),
            'fiscalRiskOptions' => $admin->listFiscalRiskOptions(),
        ]);
    }

    public function saveProgramRisk(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-governance/program-risks');

        $id = (int) ($_POST['ProgramRiskID'] ?? 0);
        $programId = (int) ($_POST['ProgramID'] ?? 0);
        $fiscalRiskId = (int) ($_POST['FiscalRiskID'] ?? 0);

        if ($programId <= 0 || $fiscalRiskId <= 0) {
            $this->flashError('Program and fiscal risk are required.');
            header('Location: index.php?route=strategy-governance/program-risk-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        try {
            $this->buildAdminModel()->saveProgramRisk([
                'ProgramID' => $programId,
                'FiscalRiskID' => $fiscalRiskId,
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ], $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Program risk link updated.' : 'Program risk link created.');
        } catch (\Throwable $e) {
            $this->flashError('Program risk link save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-governance/program-risk-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        header('Location: index.php?route=strategy-governance/program-risks');
        exit;
    }

    public function deleteProgramRisk(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-governance/program-risks');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deleteProgramRisk($id);
            $this->flashSuccess('Program risk link removed.');
        }

        header('Location: index.php?route=strategy-governance/program-risks');
        exit;
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

    private function buildReportingModel(): StrategicBudgetingModel
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

    protected function assertPostWithCsrf(string $redirectUrl = 'index.php?route=strategy-governance/narratives'): void
    {
        parent::assertPostWithCsrf($redirectUrl);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function narrativeSectionOptions(): array
    {
        return [
            'MACRO' => 'Macro',
            'REVENUE' => 'Revenue',
            'EXPENDITURE' => 'Expenditure',
            'PRIORITIES' => 'Priorities',
            'RISKS' => 'Risks',
            'MTFF' => 'MTFF',
        ];
    }

    private function riskTypeOptions(): array
    {
        return [
            'SOE' => 'SOE',
            'GUARANTEE' => 'Guarantee',
            'PPP' => 'PPP',
            'DISASTER' => 'Disaster',
            'REVENUE_SHOCK' => 'Revenue Shock',
            'MACRO' => 'Macro',
            'DEBT' => 'Debt',
            'OTHER' => 'Other',
        ];
    }
}
