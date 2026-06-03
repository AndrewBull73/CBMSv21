<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditModel;
use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Shared\SessionHelper;

final class StrategyDeliveryController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_DELIVERY_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function outputs(): void
    {
        $model = $this->buildAdminModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $programId = (int) ($_GET['program_id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);

        $this->render('strategy/OutputList', [
            'title' => 'Strategic Outputs',
            'records' => $model->listOutputs($q, $programId > 0 ? $programId : null),
            'q' => $q,
            'programId' => $programId,
            'programOptions' => $model->listProgramOptions(),
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'OUTPUT'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'OUTPUT'),
        ]);
    }

    public function outputForm(): void
    {
        $model = $this->buildAdminModel();
        $id = (int) ($_GET['id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');

        $this->render('strategy/OutputForm', [
            'title' => $id > 0 ? 'Edit Output' : 'Create Output',
            'record' => $id > 0 ? $model->getOutput($id) : null,
            'programOptions' => $model->listProgramOptions(),
            'subProgramOptions' => $model->listSubProgramOptions(),
            'orgUnitOptions' => $model->listDataScopeOrgUnitOptions($fy),
        ]);
    }

    public function saveOutput(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-delivery/outputs');

        $model = $this->buildAdminModel();
        $id = (int) ($_POST['OutputID'] ?? 0);
        $programId = (int) ($_POST['ProgramID'] ?? 0);
        $subProgramId = (int) ($_POST['SubProgramID'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ownerOrgUnitCode = trim((string) ($_POST['OutputOwnerDataObjectCode'] ?? ''));
        $userId = (int) SessionHelper::get('auth.user_id', 1);

        if ($programId <= 0 || trim((string) ($_POST['OutputName'] ?? '')) === '') {
            $this->flashError('Output name and program are required.');
            header('Location: index.php?route=strategy-delivery/output-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        if (!$model->subProgramBelongsToProgram($subProgramId > 0 ? $subProgramId : null, $programId)) {
            $this->flashError('Selected subprogram does not belong to the selected program.');
            header('Location: index.php?route=strategy-delivery/output-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        try {
            $this->db->beginTransaction();

            $payload = [
                'ProgramID' => $programId,
                'SubProgramID' => $subProgramId > 0 ? $subProgramId : null,
                'OutputName' => trim((string) ($_POST['OutputName'] ?? '')),
                'OutputDescription' => $this->nullableTrim($_POST['OutputDescription'] ?? null),
                'OutputOwnerOrgUnitID' => ($fy > 0 && $ownerOrgUnitCode !== '') ? $model->ensureOrgUnitFromDataObject($fy, $ownerOrgUnitCode, $userId) : null,
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UserID' => $userId,
            ];

            $model->saveOutput($payload, $id > 0 ? $id : null);

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }

            $this->flashSuccess($id > 0 ? 'Output updated.' : 'Output created.');
            header('Location: index.php?route=strategy-delivery/outputs');
            exit;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logHandledException('Output save failed', $e, [
                'outputId' => $id,
                'programId' => $programId,
                'subProgramId' => $subProgramId,
                'fiscalYearId' => $fy,
                'ownerDataObjectCode' => $ownerOrgUnitCode,
            ]);
            $this->flashError('Output save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-delivery/output-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
    }

    public function deleteOutput(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-delivery/outputs');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateOutput($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Output archived.');
        }

        header('Location: index.php?route=strategy-delivery/outputs');
        exit;
    }

    public function importOutputOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-delivery/outputs');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing output records.');
            header('Location: index.php?route=strategy-delivery/outputs');
            exit;
        }

        try {
            $summary = $this->buildAdminModel()->importOutputOverlaysFromSegments($fy, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('OUTPUT', $fy, $summary, $resetMode);
            $this->flashSuccess(
                'Output records imported. Created: '
                . (int) ($summary['created'] ?? 0)
                . ', skipped: ' . (int) ($summary['skipped'] ?? 0)
                . ', missing parent link: ' . (int) ($summary['missing_parent_link'] ?? 0)
                . ', missing parent record: ' . (int) ($summary['missing_parent_overlay'] ?? 0)
                . '.'
                . $this->formatImportResetMessage($summary)
            );
        } catch (\Throwable $e) {
            $this->flashError('Output record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-delivery/outputs');
        exit;
    }

    public function activities(): void
    {
        $model = $this->buildAdminModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $outputId = (int) ($_GET['output_id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');

        $this->render('strategy/ActivityList', [
            'title' => 'Strategic Activities',
            'records' => $model->listActivities($q, $outputId > 0 ? $outputId : null),
            'q' => $q,
            'outputId' => $outputId,
            'outputOptions' => $model->listOutputOptions(),
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'ACTIVITY'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'ACTIVITY'),
        ]);
    }

    public function activityForm(): void
    {
        $model = $this->buildAdminModel();
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/ActivityForm', [
            'title' => $id > 0 ? 'Edit Activity' : 'Create Activity',
            'record' => $id > 0 ? $model->getActivity($id) : null,
            'outputOptions' => $model->listOutputOptions(),
            'projectOptions' => $model->listProjectOptions(),
            'supportsActivityProjectLink' => $model->supportsActivityProjectLink(),
        ]);
    }

    public function saveActivity(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-delivery/activities');

        $model = $this->buildAdminModel();
        $id = (int) ($_POST['ActivityID'] ?? 0);
        $outputId = (int) ($_POST['OutputID'] ?? 0);
        $projectId = (int) ($_POST['ProjectID'] ?? 0);
        $activityTypeCode = strtoupper(trim((string) ($_POST['ActivityTypeCode'] ?? 'OPERATIONAL')));

        if ($outputId <= 0 || trim((string) ($_POST['ActivityName'] ?? '')) === '') {
            $this->flashError('Activity name and output are required.');
            header('Location: index.php?route=strategy-delivery/activity-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
        if ($model->supportsActivityProjectLink() && $activityTypeCode === 'PROJECT' && $projectId <= 0) {
            $this->flashError('Select a project when the activity type is PROJECT.');
            header('Location: index.php?route=strategy-delivery/activity-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $payload = [
            'OutputID' => $outputId,
            'ActivityName' => trim((string) ($_POST['ActivityName'] ?? '')),
            'ActivityDescription' => $this->nullableTrim($_POST['ActivityDescription'] ?? null),
            'ActivityTypeCode' => $activityTypeCode,
            'LocationCode' => $this->nullableTrim($_POST['LocationCode'] ?? null),
            'StartDate' => $this->nullableTrim($_POST['StartDate'] ?? null),
            'EndDate' => $this->nullableTrim($_POST['EndDate'] ?? null),
            'ImplementationStatusCode' => strtoupper(trim((string) ($_POST['ImplementationStatusCode'] ?? 'PLANNED'))),
            'ProcurementRequiredFlag' => isset($_POST['ProcurementRequiredFlag']) ? 1 : 0,
            'Dependencies' => $this->nullableTrim($_POST['Dependencies'] ?? null),
            'RiskNotes' => $this->nullableTrim($_POST['RiskNotes'] ?? null),
            'ProjectID' => $projectId > 0 ? $projectId : null,
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        $model->saveActivity($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Activity updated.' : 'Activity created.');
        header('Location: index.php?route=strategy-delivery/activities');
        exit;
    }

    public function deleteActivity(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-delivery/activities');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateActivity($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Activity archived.');
        }

        header('Location: index.php?route=strategy-delivery/activities');
        exit;
    }

    public function importActivityOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-delivery/activities');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing activity records.');
            header('Location: index.php?route=strategy-delivery/activities');
            exit;
        }

        try {
            $summary = $this->buildAdminModel()->importActivityOverlaysFromSegments($fy, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('ACTIVITY', $fy, $summary, $resetMode);
            $this->flashSuccess(
                'Activity records imported. Created: '
                . (int) ($summary['created'] ?? 0)
                . ', skipped: ' . (int) ($summary['skipped'] ?? 0)
                . ', missing parent link: ' . (int) ($summary['missing_parent_link'] ?? 0)
                . ', missing parent record: ' . (int) ($summary['missing_parent_overlay'] ?? 0)
                . '.'
                . $this->formatImportResetMessage($summary)
            );
        } catch (\Throwable $e) {
            $this->flashError('Activity record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-delivery/activities');
        exit;
    }

    public function budgets(): void
    {
        $admin = $this->buildAdminModel();
        $reporting = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));
        $activityId = (int) ($_GET['activity_id'] ?? 0);

        $this->render('strategy/ActivityBudgetList', [
            'title' => 'Strategic Activity Budgets',
            'records' => $admin->listActivityBudgets($fy, $ver, $q, $activityId > 0 ? $activityId : null),
            'summary' => $admin->getActivityBudgetSummary($fy, $ver),
            'contextLabels' => $reporting->getContextLabels($fy, $ver),
            'q' => $q,
            'activityId' => $activityId,
            'activityOptions' => $admin->listActivityOptions(),
        ]);
    }

    public function budgetForm(): void
    {
        $admin = $this->buildAdminModel();
        $reporting = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/ActivityBudgetForm', [
            'title' => $id > 0 ? 'Edit Activity Budget' : 'Create Activity Budget',
            'record' => $id > 0 ? $admin->getActivityBudget($id) : null,
            'activityOptions' => $admin->listActivityOptions(),
            'economicItemOptions' => $admin->listEconomicItemOptions(),
            'fundingSourceOptions' => $admin->listFundingSourceOptions(),
            'contextLabels' => $reporting->getContextLabels($fy, $ver),
            'FiscalYearID' => $fy,
            'VersionID' => $ver,
        ]);
    }

    public function saveBudget(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-delivery/budgets');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $admin = $this->buildAdminModel();
        $id = (int) ($_POST['ActivityBudgetID'] ?? 0);
        $activityId = (int) ($_POST['ActivityID'] ?? 0);
        $economicItemId = (int) ($_POST['EconomicItemID'] ?? 0);
        $fundingSourceId = (int) ($_POST['FundingSourceID'] ?? 0);
        $amount = (float) ($_POST['Amount'] ?? 0);

        if ($fy <= 0 || $ver <= 0) {
            $this->flashError('Fiscal context is required before budget entry.');
            header('Location: index.php?route=strategy-delivery/budgets');
            exit;
        }
        if ($activityId <= 0 || $economicItemId <= 0) {
            $this->flashError('Activity and economic item are required.');
            header('Location: index.php?route=strategy-delivery/budget-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
        if ($amount < 0) {
            $this->flashError('Amount cannot be negative.');
            header('Location: index.php?route=strategy-delivery/budget-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $payload = [
            'ActivityID' => $activityId,
            'VersionID' => $ver,
            'FiscalYearID' => $fy,
            'EconomicItemID' => $economicItemId,
            'FundingSourceID' => $fundingSourceId > 0 ? $fundingSourceId : null,
            'Amount' => $amount,
            'CurrencyCode' => $this->nullableTrim($_POST['CurrencyCode'] ?? null),
            'Notes' => $this->nullableTrim($_POST['Notes'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        try {
            $admin->saveActivityBudget($payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Activity budget updated.' : 'Activity budget created.');
        } catch (\Throwable $e) {
            $this->flashError('Activity budget save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-delivery/budget-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        header('Location: index.php?route=strategy-delivery/budgets');
        exit;
    }

    public function deleteBudget(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-delivery/budgets');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateActivityBudget($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Activity budget archived.');
        }

        header('Location: index.php?route=strategy-delivery/budgets');
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

    protected function assertPostWithCsrf(string $redirectUrl = 'index.php?route=strategy-delivery/outputs'): void
    {
        parent::assertPostWithCsrf($redirectUrl);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function buildAuditModel(): AuditModel
    {
        if (!$this->db instanceof \PDO) {
            require __DIR__ . '/../../config/db.php';
            $this->db = $GLOBALS['conn'] ?? null;
        }
        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('Database connection is not available.');
        }
        return new AuditModel($this->db);
    }

    private function recordDimensionImport(string $dimensionCode, int $fiscalYearId, array $summary, string $resetMode): void
    {
        try {
            $this->buildAuditModel()->insert([
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
                'Username' => (string) SessionHelper::get('auth.username', 'system'),
                'Action' => 'IMPORT_DIMENSION',
                'Entity' => 'STRATEGY_DIMENSION',
                'EntityKey' => strtoupper(trim($dimensionCode)),
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => (int) ($this->context()['VersionID'] ?? 0) ?: null,
                'Details' => [
                    'reset_mode' => strtolower(trim($resetMode)) ?: 'none',
                    'summary' => $summary,
                ],
            ]);
        } catch (\Throwable) {
        }
    }

    private function formatImportResetMessage(array $summary): string
    {
        $reset = is_array($summary['reset'] ?? null) ? $summary['reset'] : [];
        if (($reset['mode'] ?? 'none') === 'none') {
            return '';
        }

        return ' Reset summary: archived '
            . (int) ($reset['records_archived'] ?? 0)
            . ', deleted ' . (int) ($reset['records_deleted'] ?? 0)
            . ', preserved ' . (int) ($reset['records_preserved'] ?? 0)
            . ', blocked ' . (int) ($reset['blocked'] ?? 0)
            . '.';
    }
}
