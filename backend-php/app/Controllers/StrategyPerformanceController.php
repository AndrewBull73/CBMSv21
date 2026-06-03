<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AuditModel;
use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Shared\SessionHelper;

final class StrategyPerformanceController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_PERFORMANCE_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function objectives(): void
    {
        $model = $this->buildAdminModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $programId = (int) ($_GET['program_id'] ?? 0);
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/ObjectiveList', [
            'title' => 'Strategic Objectives',
            'records' => $model->listObjectives($q, $programId > 0 ? $programId : null),
            'q' => $q,
            'programId' => $programId,
            'programOptions' => $model->listProgramOptions(),
            'segmentMapping' => $model->getStrategicSegmentMapping($fy, 'OBJECTIVE'),
            'lastImport' => $model->getLatestDimensionImportHistory($fy, 'OBJECTIVE'),
            'contextLabels' => $this->buildReportingModel()->getContextLabels($fy, $ver),
        ]);
    }

    public function strategicPillars(): void
    {
        $model = $this->buildAdminModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/StrategicPillarList', [
            'title' => 'Strategic Pillars',
            'records' => $model->listStrategicPillars($q),
            'q' => $q,
            'contextLabels' => $this->buildReportingModel()->getContextLabels($fy, $ver),
        ]);
    }

    public function strategicPillarForm(): void
    {
        $model = $this->buildAdminModel();
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/StrategicPillarForm', [
            'title' => $id > 0 ? 'Edit Strategic Pillar' : 'Create Strategic Pillar',
            'record' => $id > 0 ? $model->getStrategicPillar($id) : null,
        ]);
    }

    public function saveStrategicPillar(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/strategic-pillars');

        $model = $this->buildAdminModel();
        $id = (int) ($_POST['StrategicPillarID'] ?? 0);
        $payload = [
            'StrategicPillarCode' => trim((string) ($_POST['StrategicPillarCode'] ?? '')),
            'StrategicPillarName' => trim((string) ($_POST['StrategicPillarName'] ?? '')),
            'FrameworkCode' => trim((string) ($_POST['FrameworkCode'] ?? 'FYDP_II')),
            'StrategicPillarDescription' => $this->nullableTrim($_POST['StrategicPillarDescription'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        if ($payload['StrategicPillarCode'] === '' || $payload['StrategicPillarName'] === '') {
            $this->flashError('Strategic Pillar code and name are required.');
            header('Location: index.php?route=strategy-performance/strategic-pillar-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $model->saveStrategicPillar($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Strategic Pillar updated.' : 'Strategic Pillar created.');
        header('Location: index.php?route=strategy-performance/strategic-pillars');
        exit;
    }

    public function deleteStrategicPillar(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/strategic-pillars');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateStrategicPillar($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Strategic Pillar archived.');
        }

        header('Location: index.php?route=strategy-performance/strategic-pillars');
        exit;
    }

    public function goals(): void
    {
        $model = $this->buildAdminModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/GoalList', [
            'title' => 'Goals',
            'records' => $model->listGoals($q),
            'q' => $q,
            'contextLabels' => $this->buildReportingModel()->getContextLabels($fy, $ver),
        ]);
    }

    public function goalForm(): void
    {
        $model = $this->buildAdminModel();
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/GoalForm', [
            'title' => $id > 0 ? 'Edit Goal' : 'Create Goal',
            'record' => $id > 0 ? $model->getGoal($id) : null,
            'strategicPillarOptions' => $model->listStrategicPillars(),
        ]);
    }

    public function saveGoal(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/goals');

        $model = $this->buildAdminModel();
        $id = (int) ($_POST['GoalID'] ?? 0);
        $payload = [
            'GoalCode' => trim((string) ($_POST['GoalCode'] ?? '')),
            'GoalName' => trim((string) ($_POST['GoalName'] ?? '')),
            'GoalTypeCode' => strtoupper(trim((string) ($_POST['GoalTypeCode'] ?? 'SDG'))),
            'StrategicPillarID' => (int) ($_POST['StrategicPillarID'] ?? 0) ?: null,
            'GoalDescription' => $this->nullableTrim($_POST['GoalDescription'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        if ($payload['GoalCode'] === '' || $payload['GoalName'] === '') {
            $this->flashError('Goal code and name are required.');
            header('Location: index.php?route=strategy-performance/goal-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $model->saveGoal($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Goal updated.' : 'Goal created.');
        header('Location: index.php?route=strategy-performance/goals');
        exit;
    }

    public function deleteGoal(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/goals');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateGoal($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Goal archived.');
        }

        header('Location: index.php?route=strategy-performance/goals');
        exit;
    }

    public function objectiveForm(): void
    {
        $model = $this->buildAdminModel();
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/ObjectiveForm', [
            'title' => $id > 0 ? 'Edit Objective' : 'Create Objective',
            'record' => $id > 0 ? $model->getObjective($id) : null,
            'programOptions' => $model->listProgramOptions(),
            'subProgramOptions' => $model->listSubProgramOptions(),
            'goalOptions' => $model->listGoals(),
            'selectedGoalIds' => $id > 0 ? $model->getObjectiveGoalIds($id) : [],
            'customAttributeFields' => $model->getDimensionAttributeFields('OBJECTIVE', $id > 0 ? $id : null),
        ]);
    }

    public function saveObjective(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/objectives');

        $model = $this->buildAdminModel();
        $id = (int) ($_POST['ObjectiveID'] ?? 0);
        $programId = (int) ($_POST['ProgramID'] ?? 0);
        $subProgramId = (int) ($_POST['SubProgramID'] ?? 0);
        $userId = (int) SessionHelper::get('auth.user_id', 1);

        if ($programId <= 0 || trim((string) ($_POST['ObjectiveText'] ?? '')) === '') {
            $this->flashError('Objective text and program are required.');
            header('Location: index.php?route=strategy-performance/objective-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
        if (!$model->subProgramBelongsToProgram($subProgramId > 0 ? $subProgramId : null, $programId)) {
            $this->flashError('Selected subprogram does not belong to the selected program.');
            header('Location: index.php?route=strategy-performance/objective-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        try {
            $this->db->beginTransaction();

            $payload = [
                'ProgramID' => $programId,
                'SubProgramID' => $subProgramId > 0 ? $subProgramId : null,
                'ObjectiveText' => trim((string) ($_POST['ObjectiveText'] ?? '')),
                'PolicyLink' => $this->nullableTrim($_POST['PolicyLink'] ?? null),
                'PriorityRank' => $_POST['PriorityRank'] !== '' ? (int) $_POST['PriorityRank'] : null,
                'GoalIDs' => is_array($_POST['GoalIDs'] ?? null) ? $_POST['GoalIDs'] : [],
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UserID' => $userId,
            ];

            $objectiveId = $model->saveObjective($payload, $id > 0 ? $id : null);
            $model->syncDimensionAttributeValues(
                'OBJECTIVE',
                $objectiveId,
                is_array($_POST['custom_attributes'] ?? null) ? $_POST['custom_attributes'] : [],
                $userId
            );

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }

            $this->flashSuccess($id > 0 ? 'Objective updated.' : 'Objective created.');
            header('Location: index.php?route=strategy-performance/objectives');
            exit;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logHandledException('Objective save failed', $e, [
                'objectiveId' => $id,
                'programId' => $programId,
                'subProgramId' => $subProgramId,
            ]);
            $this->flashError('Objective save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-performance/objective-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
    }

    public function deleteObjective(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/objectives');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateObjective($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Objective archived.');
        }

        header('Location: index.php?route=strategy-performance/objectives');
        exit;
    }

    public function importObjectiveOverlays(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/objectives');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $resetMode = (string) ($_POST['reset_mode'] ?? 'none');

        if ($fy <= 0) {
            $this->flashError('Fiscal context is required before importing objective records.');
            header('Location: index.php?route=strategy-performance/objectives');
            exit;
        }

        try {
            $summary = $this->buildAdminModel()->importObjectiveOverlaysFromSegments($fy, (int) SessionHelper::get('auth.user_id', 1), $resetMode);
            $this->recordDimensionImport('OBJECTIVE', $fy, $summary, $resetMode);
            $this->flashSuccess(
                'Objective records imported. Created: '
                . (int) ($summary['created'] ?? 0)
                . ', skipped: ' . (int) ($summary['skipped'] ?? 0)
                . ', missing parent link: ' . (int) ($summary['missing_parent_link'] ?? 0)
                . ', missing parent record: ' . (int) ($summary['missing_parent_overlay'] ?? 0)
                . '.'
                . $this->formatImportResetMessage($summary)
            );
        } catch (\Throwable $e) {
            $this->flashError('Objective record import failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-performance/objectives');
        exit;
    }

    public function indicators(): void
    {
        $model = $this->buildAdminModel();
        $q = trim((string) ($_GET['q'] ?? ''));
        $typeCode = strtoupper(trim((string) ($_GET['type_code'] ?? '')));
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $this->render('strategy/IndicatorList', [
            'title' => 'Strategic Indicators',
            'records' => $model->listIndicators($q, $typeCode !== '' ? $typeCode : null),
            'q' => $q,
            'typeCode' => $typeCode,
            'contextLabels' => $this->buildReportingModel()->getContextLabels($fy, $ver),
        ]);
    }

    public function indicatorForm(): void
    {
        $model = $this->buildAdminModel();
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/IndicatorForm', [
            'title' => $id > 0 ? 'Edit Indicator' : 'Create Indicator',
            'record' => $id > 0 ? $model->getIndicator($id) : null,
        ]);
    }

    public function saveIndicator(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/indicators');

        $model = $this->buildAdminModel();
        $id = (int) ($_POST['IndicatorID'] ?? 0);
        $payload = [
            'IndicatorTypeCode' => strtoupper(trim((string) ($_POST['IndicatorTypeCode'] ?? ''))),
            'IndicatorName' => trim((string) ($_POST['IndicatorName'] ?? '')),
            'IndicatorDefinition' => $this->nullableTrim($_POST['IndicatorDefinition'] ?? null),
            'UnitOfMeasure' => $this->nullableTrim($_POST['UnitOfMeasure'] ?? null),
            'DataSource' => $this->nullableTrim($_POST['DataSource'] ?? null),
            'FrequencyCode' => $this->nullableTrim($_POST['FrequencyCode'] ?? null),
            'Disaggregation' => $this->nullableTrim($_POST['Disaggregation'] ?? null),
            'QualityNotes' => $this->nullableTrim($_POST['QualityNotes'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        if ($payload['IndicatorTypeCode'] === '' || $payload['IndicatorName'] === '') {
            $this->flashError('Indicator type and name are required.');
            header('Location: index.php?route=strategy-performance/indicator-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $model->saveIndicator($payload, $id > 0 ? $id : null);
        $this->flashSuccess($id > 0 ? 'Indicator updated.' : 'Indicator created.');
        header('Location: index.php?route=strategy-performance/indicators');
        exit;
    }

    public function deleteIndicator(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/indicators');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateIndicator($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Indicator archived.');
        }

        header('Location: index.php?route=strategy-performance/indicators');
        exit;
    }

    public function targets(): void
    {
        $admin = $this->buildAdminModel();
        $reporting = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $q = trim((string) ($_GET['q'] ?? ''));
        $indicatorId = (int) ($_GET['indicator_id'] ?? 0);

        $this->render('strategy/IndicatorTargetList', [
            'title' => 'Indicator Targets',
            'records' => $admin->listIndicatorTargets($fy, $ver, $q, $indicatorId > 0 ? $indicatorId : null),
            'contextLabels' => $reporting->getContextLabels($fy, $ver),
            'q' => $q,
            'indicatorId' => $indicatorId,
            'indicatorOptions' => $admin->listIndicators(),
        ]);
    }

    public function targetForm(): void
    {
        $admin = $this->buildAdminModel();
        $reporting = $this->buildReportingModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);

        $this->render('strategy/IndicatorTargetForm', [
            'title' => $id > 0 ? 'Edit Indicator Target' : 'Create Indicator Target',
            'record' => $id > 0 ? $admin->getIndicatorTarget($id) : null,
            'indicatorOptions' => $admin->listIndicators(),
            'contextLabels' => $reporting->getContextLabels($fy, $ver),
        ]);
    }

    public function saveTarget(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/targets');

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $admin = $this->buildAdminModel();
        $id = (int) ($_POST['IndicatorTargetID'] ?? 0);
        $indicatorId = (int) ($_POST['IndicatorID'] ?? 0);

        if ($fy <= 0 || $ver <= 0) {
            $this->flashError('Fiscal context is required before target entry.');
            header('Location: index.php?route=strategy-performance/targets');
            exit;
        }
        if ($indicatorId <= 0 || trim((string) ($_POST['TargetValue'] ?? '')) === '') {
            $this->flashError('Indicator and target value are required.');
            header('Location: index.php?route=strategy-performance/target-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        $payload = [
            'IndicatorID' => $indicatorId,
            'VersionID' => $ver,
            'FiscalYearID' => $fy,
            'BaselineValue' => $_POST['BaselineValue'] !== '' ? (float) $_POST['BaselineValue'] : null,
            'TargetValue' => (float) ($_POST['TargetValue'] ?? 0),
            'Notes' => $this->nullableTrim($_POST['Notes'] ?? null),
            'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
            'UserID' => (int) SessionHelper::get('auth.user_id', 1),
        ];

        try {
            $admin->saveIndicatorTarget($payload, $id > 0 ? $id : null);
            $this->flashSuccess($id > 0 ? 'Indicator target updated.' : 'Indicator target created.');
        } catch (\Throwable $e) {
            $this->flashError('Indicator target save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-performance/target-form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        header('Location: index.php?route=strategy-performance/targets');
        exit;
    }

    public function deleteTarget(): void
    {
        $this->assertPostWithCsrf();
        $this->assertStrategicContextEditable('index.php?route=strategy-performance/targets');

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $this->buildAdminModel()->deactivateIndicatorTarget($id, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Indicator target archived.');
        }

        header('Location: index.php?route=strategy-performance/targets');
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

    protected function assertPostWithCsrf(string $redirectUrl = 'index.php?route=strategy-performance/objectives'): void
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
