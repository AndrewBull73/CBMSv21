<?php
declare(strict_types=1);

namespace App\Controllers;

require_once __DIR__ . '/../../shared/csrf.php';

use App\Core\Rbac;
use App\Models\BudgetExecutionModel;
use App\Shared\SessionHelper;

final class BudgetExecutionController extends BaseController
{
    private const EXECUTION_ROLES = [
        'Budget Execution User',
        'Budget Execution Reviewer',
        'Budget Execution Administrator',
        'System Administrator',
    ];

    private const EXECUTION_ADMIN_ROLES = [
        'Budget Execution Administrator',
        'System Administrator',
    ];

    private const EXECUTION_REVIEW_ROLES = [
        'Budget Execution Reviewer',
        'Budget Execution Administrator',
        'System Administrator',
    ];

    protected array $acl = [
        '*' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_ROLES,
        ],
        'saveVersion' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_ADMIN_ROLES,
        ],
        'runRollover' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_ADMIN_ROLES,
        ],
        'forwardWarrant' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'returnWarrant' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'approveWarrant' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'cancelWarrant' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'forwardReservation' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'returnReservation' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'approveReservation' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'cancelReservation' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'forwardSupplementary' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'returnSupplementary' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'approveSupplementary' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'cancelSupplementary' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'forwardCommitment' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'returnCommitment' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'approveCommitment' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'cancelCommitment' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'forwardRie' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'returnRie' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'approveRie' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
        'cancelRie' => [
            'auth' => true,
            'rolesAny' => self::EXECUTION_REVIEW_ROLES,
        ],
    ];

    protected bool $requiresContext = true;

    public function index(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);

        $this->render('execution/Index', [
            'title' => 'Budget Execution Setup',
            'context' => $ctx,
            'currentVersion' => $currentVersion,
            'executionAccess' => true,
            'executionAdmin' => $this->canAdministerExecution(),
            'supportsVersionTyping' => $model->supportsVersionTyping(),
            'supportsExecutionFoundation' => $model->supportsExecutionFoundation(),
            'executionVersions' => $model->listExecutionVersions($fy),
            'submissionVersions' => $model->listSubmissionVersionCandidates($fy),
            'rolloverRuns' => $model->listRolloverRuns($fy),
        ]);
    }

    public function tasks(): void
    {
        header('Location: index.php?route=execution/index');
        exit;
    }

    public function useVersion(): void
    {
        $this->assertPostWithCsrf();

        $fy = (int) ($_POST['FiscalYearID'] ?? 0);
        $ver = (int) ($_POST['VersionID'] ?? 0);
        $model = $this->buildModel();
        $version = $model->getVersion($fy, $ver);

        if ($version === null || strtoupper(trim((string) ($version['VersionTypeCode'] ?? ''))) !== 'EXECUTION') {
            $this->flashError('Selected version is not an execution version.');
            header('Location: index.php?route=execution/index');
            exit;
        }

        $this->setFiscalContext($fy, $ver);
        $this->flashSuccess('Execution context updated.');
        header('Location: index.php?route=execution/index');
        exit;
    }

    public function saveVersion(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $model = $this->buildModel();

        try {
            $versionId = $model->createExecutionVersion([
                'FiscalYearID' => $fy,
                'VersionLabel' => trim((string) ($_POST['VersionLabel'] ?? '')),
                'VersionStatus' => trim((string) ($_POST['VersionStatus'] ?? 'OPEN')),
                'IsDefault' => isset($_POST['IsDefault']) ? 1 : 0,
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->setFiscalContext($fy, $versionId);
            $this->flashSuccess('Execution version created and set as the current context.');
        } catch (\Throwable $e) {
            $this->flashError('Execution version save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/index');
        exit;
    }

    public function runRollover(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($_POST['ExecutionVersionID'] ?? 0);
        $sourceVersionId = (int) ($_POST['SourceVersionID'] ?? 0);
        $model = $this->buildModel();

        try {
            $summary = $model->rolloverExecutionVersion(
                $fy,
                $executionVersionId,
                $sourceVersionId,
                (int) SessionHelper::get('auth.user_id', 1)
            );
            $this->setFiscalContext($fy, $executionVersionId);
            $this->flashSuccess(
                'Execution rollover completed. Opening lines: '
                . (int) ($summary['InsertedBalanceLineCount'] ?? 0)
                . '; Total amount: '
                . number_format((float) ($summary['TotalOpeningAmount'] ?? 0), 2)
            );
            header('Location: index.php?route=execution/opening-balances');
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Execution rollover failed: ' . $e->getMessage());
            header('Location: index.php?route=execution/index');
            exit;
        }
    }

    public function openingBalances(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);
        $isExecutionVersion = $currentVersion !== null
            && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';

        $summary = $isExecutionVersion
            ? $model->getOpeningBalanceSummary($fy, $ver)
            : ['LineCount' => 0, 'OpeningAmountTotal' => 0.0, 'CurrentAuthorizedAmountTotal' => 0.0];
        $rows = $isExecutionVersion ? $model->listOpeningBalanceRows($fy, $ver) : [];

        $this->render('execution/OpeningBalances', [
            'title' => 'Execution Opening Balances',
            'context' => $ctx,
            'currentVersion' => $currentVersion,
            'isExecutionVersion' => $isExecutionVersion,
            'executionVersions' => $model->listExecutionVersions($fy),
            'supportsExecutionFoundation' => $model->supportsExecutionFoundation(),
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }

    public function warrants(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);
        $isExecutionVersion = $currentVersion !== null
            && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';
        $supportsWorkflowEngine = $model->supportsWorkflowEngineFoundation();

        $selectedWarrantId = (int) ($_GET['warrant_id'] ?? 0);
        $warrants = ($isExecutionVersion && $model->supportsWarrantFoundation())
            ? $model->listWarrants($fy, $ver)
            : [];
        if ($supportsWorkflowEngine && !empty($warrants)) {
            $warrants = $model->annotateWarrantsWithWorkflowState($fy, $ver, $warrants);
        }
        if ($selectedWarrantId <= 0 && !empty($warrants)) {
            $selectedWarrantId = (int) ($warrants[0]['WarrantID'] ?? 0);
        }
        $selectedWarrant = ($isExecutionVersion && $selectedWarrantId > 0)
            ? $model->getWarrant($fy, $ver, $selectedWarrantId)
            : null;
        $selectedWarrantLines = ($selectedWarrant !== null)
            ? $model->listWarrantLines($fy, $ver, (int) ($selectedWarrant['WarrantID'] ?? 0))
            : [];
        $selectedWarrantWorkflow = ($isExecutionVersion && $supportsWorkflowEngine && $selectedWarrant !== null)
            ? $model->getWarrantWorkflowState($fy, $ver, (int) ($selectedWarrant['WarrantID'] ?? 0))
            : [];

        $this->render('execution/Warrants', [
            'title' => 'Execution Warrants',
            'context' => $ctx,
            'currentVersion' => $currentVersion,
            'isExecutionVersion' => $isExecutionVersion,
            'executionAdmin' => $this->canAdministerExecution(),
            'executionReviewer' => $this->canReviewExecution(),
            'supportsExecutionFoundation' => $model->supportsExecutionFoundation(),
            'supportsWarrantFoundation' => $model->supportsWarrantFoundation(),
            'supportsWorkflowEngine' => $supportsWorkflowEngine,
            'summary' => $isExecutionVersion ? $model->getWarrantModuleSummary($fy, $ver) : [],
            'openingSummary' => $isExecutionVersion ? $model->getOpeningBalanceSummary($fy, $ver) : [],
            'warrants' => $warrants,
            'selectedWarrant' => $selectedWarrant,
            'selectedWarrantLines' => $selectedWarrantLines,
            'selectedWarrantWorkflow' => $selectedWarrantWorkflow,
            'balanceCandidates' => ($isExecutionVersion && $model->supportsExecutionFoundation()) ? $model->listWarrantBalanceCandidates($fy, $ver) : [],
        ]);
    }

    public function reservations(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);
        $isExecutionVersion = $currentVersion !== null
            && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';
        $supportsWorkflowEngine = $model->supportsWorkflowEngineFoundation();

        $selectedReservationId = (int) ($_GET['reservation_id'] ?? 0);
        $reservations = ($isExecutionVersion && $model->supportsReservationFoundation())
            ? $model->listReservations($fy, $ver)
            : [];
        if ($supportsWorkflowEngine && !empty($reservations)) {
            $reservations = $model->annotateReservationsWithWorkflowState($fy, $ver, $reservations);
        }
        if ($selectedReservationId <= 0 && !empty($reservations)) {
            $selectedReservationId = (int) ($reservations[0]['ReservationID'] ?? 0);
        }
        $selectedReservation = ($isExecutionVersion && $selectedReservationId > 0)
            ? $model->getReservation($fy, $ver, $selectedReservationId)
            : null;
        $selectedReservationLines = ($selectedReservation !== null)
            ? $model->listReservationLines($fy, $ver, (int) ($selectedReservation['ReservationID'] ?? 0))
            : [];
        $selectedReservationWorkflow = ($isExecutionVersion && $supportsWorkflowEngine && $selectedReservation !== null)
            ? $model->getReservationWorkflowState($fy, $ver, (int) ($selectedReservation['ReservationID'] ?? 0))
            : [];

        $this->render('execution/Reservations', [
            'title' => 'Execution Reservations',
            'context' => $ctx,
            'currentVersion' => $currentVersion,
            'isExecutionVersion' => $isExecutionVersion,
            'executionAdmin' => $this->canAdministerExecution(),
            'executionReviewer' => $this->canReviewExecution(),
            'supportsExecutionFoundation' => $model->supportsExecutionFoundation(),
            'supportsWarrantFoundation' => $model->supportsWarrantFoundation(),
            'supportsReservationFoundation' => $model->supportsReservationFoundation(),
            'supportsCommitmentFoundation' => $model->supportsCommitmentFoundation(),
            'supportsWorkflowEngine' => $supportsWorkflowEngine,
            'summary' => $isExecutionVersion ? $model->getReservationModuleSummary($fy, $ver) : [],
            'openingSummary' => $isExecutionVersion ? $model->getOpeningBalanceSummary($fy, $ver) : [],
            'reservations' => $reservations,
            'selectedReservation' => $selectedReservation,
            'selectedReservationLines' => $selectedReservationLines,
            'selectedReservationWorkflow' => $selectedReservationWorkflow,
            'balanceCandidates' => ($isExecutionVersion && $model->supportsWarrantFoundation() && $model->supportsReservationFoundation() && $model->supportsCommitmentFoundation()) ? $model->listReservationBalanceCandidates($fy, $ver) : [],
        ]);
    }

    public function supplementaries(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);
        $isExecutionVersion = $currentVersion !== null
            && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';
        $supportsWorkflowEngine = $model->supportsWorkflowEngineFoundation();

        $selectedSupplementaryId = (int) ($_GET['supplementary_id'] ?? 0);
        $supplementaries = ($isExecutionVersion && $model->supportsSupplementaryFoundation())
            ? $model->listSupplementaries($fy, $ver)
            : [];
        if ($isExecutionVersion && $supportsWorkflowEngine) {
            $supplementaries = $model->annotateSupplementariesWithWorkflowState($fy, $ver, $supplementaries);
        }
        if ($selectedSupplementaryId <= 0 && !empty($supplementaries)) {
            $selectedSupplementaryId = (int) ($supplementaries[0]['SupplementaryBudgetID'] ?? 0);
        }
        $selectedSupplementary = ($isExecutionVersion && $selectedSupplementaryId > 0)
            ? $model->getSupplementary($fy, $ver, $selectedSupplementaryId)
            : null;
        $selectedSupplementaryLines = ($selectedSupplementary !== null)
            ? $model->listSupplementaryLines($fy, $ver, (int) ($selectedSupplementary['SupplementaryBudgetID'] ?? 0))
            : [];
        $selectedSupplementaryWorkflow = ($selectedSupplementary !== null && $supportsWorkflowEngine)
            ? $model->getSupplementaryWorkflowState($fy, $ver, (int) ($selectedSupplementary['SupplementaryBudgetID'] ?? 0))
            : [];

        $this->render('execution/Supplementaries', [
            'title' => 'Execution Supplementary Budgets',
            'context' => $ctx,
            'currentVersion' => $currentVersion,
            'isExecutionVersion' => $isExecutionVersion,
            'executionAdmin' => $this->canAdministerExecution(),
            'executionReviewer' => $this->canReviewExecution(),
            'supportsExecutionFoundation' => $model->supportsExecutionFoundation(),
            'supportsWarrantFoundation' => $model->supportsWarrantFoundation(),
            'supportsSupplementaryFoundation' => $model->supportsSupplementaryFoundation(),
            'supportsWorkflowEngine' => $supportsWorkflowEngine,
            'summary' => $isExecutionVersion ? $model->getSupplementaryModuleSummary($fy, $ver) : [],
            'openingSummary' => $isExecutionVersion ? $model->getOpeningBalanceSummary($fy, $ver) : [],
            'supplementaries' => $supplementaries,
            'selectedSupplementary' => $selectedSupplementary,
            'selectedSupplementaryLines' => $selectedSupplementaryLines,
            'selectedSupplementaryWorkflow' => $selectedSupplementaryWorkflow,
            'balanceCandidates' => ($isExecutionVersion && $model->supportsExecutionFoundation()) ? $model->listSupplementaryBalanceCandidates($fy, $ver) : [],
        ]);
    }

    public function budgetReductions(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);
        $isExecutionVersion = $currentVersion !== null
            && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';
        $sessionScopeDataObjectCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));
        $mappedDimensions = $model->listBudgetReductionMappedDimensions($fy);

        $defaults = [
            'ReductionTitle' => '',
            'ReductionNotes' => '',
            'ReductionMethod' => 'PERCENTAGE',
            'ReductionValue' => '',
            'DataObjectCode' => '',
            'SessionScopeDataObjectCode' => $sessionScopeDataObjectCode,
            'DimensionFilters' => [],
        ];
        foreach ($mappedDimensions as $dimension) {
            $defaults['DimensionFilters'][(string) ($dimension['Code'] ?? '')] = '';
        }

        $effectiveScopeCode = trim((string) ($defaults['DataObjectCode'] ?? '')) !== ''
            ? trim((string) $defaults['DataObjectCode'])
            : $sessionScopeDataObjectCode;

        $this->renderBudgetReductionScreen(
            $model,
            $ctx,
            $currentVersion,
            $isExecutionVersion,
            $defaults,
            null,
            $mappedDimensions,
            $effectiveScopeCode
        );
    }

    public function previewBudgetReduction(): void
    {
        $this->assertPostWithCsrf();

        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);
        $isExecutionVersion = $currentVersion !== null
            && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';

        $form = $this->readBudgetReductionForm();
        $mappedDimensions = $model->listBudgetReductionMappedDimensions($fy);
        foreach ($mappedDimensions as $dimension) {
            $code = (string) ($dimension['Code'] ?? '');
            if ($code !== '' && !array_key_exists($code, $form['DimensionFilters'])) {
                $form['DimensionFilters'][$code] = '';
            }
        }
        $preview = null;

        if ($isExecutionVersion) {
            try {
                $preview = $model->previewBudgetReduction($fy, $ver, $form);
            } catch (\Throwable $e) {
                $this->flashError('Budget reduction preview failed: ' . $e->getMessage());
            }
        }

        $effectiveScopeCode = trim((string) ($form['DataObjectCode'] ?? '')) !== ''
            ? trim((string) $form['DataObjectCode'])
            : trim((string) ($form['SessionScopeDataObjectCode'] ?? ''));

        $this->renderBudgetReductionScreen(
            $model,
            $ctx,
            $currentVersion,
            $isExecutionVersion,
            $form,
            $preview,
            $mappedDimensions,
            $effectiveScopeCode
        );
    }

    public function generateBudgetReduction(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $model = $this->buildModel();
        $form = $this->readBudgetReductionForm();
        $selectedIds = is_array($_POST['SelectedOpeningBalanceIDs'] ?? null) ? $_POST['SelectedOpeningBalanceIDs'] : [];
        $mappedDimensions = $model->listBudgetReductionMappedDimensions($fy);
        foreach ($mappedDimensions as $dimension) {
            $code = (string) ($dimension['Code'] ?? '');
            if ($code !== '' && !array_key_exists($code, $form['DimensionFilters'])) {
                $form['DimensionFilters'][$code] = '';
            }
        }

        try {
            $result = $model->generateBudgetReductionBatch(
                $fy,
                $ver,
                $form,
                $selectedIds,
                (string) ($form['ReductionTitle'] ?? ''),
                (string) ($form['ReductionNotes'] ?? ''),
                (int) SessionHelper::get('auth.user_id', 1)
            );
            $this->flashSuccess(
                'Budget reduction draft generated. Lines: '
                . (int) ($result['GeneratedLineCount'] ?? 0)
                . '; Total reduction: '
                . number_format((float) ($result['GeneratedReductionTotal'] ?? 0), 2)
            );
            header('Location: index.php?route=execution/supplementaries&supplementary_id=' . (int) ($result['SupplementaryBudgetID'] ?? 0));
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Budget reduction generation failed: ' . $e->getMessage());
            $preview = null;
            try {
                $preview = $model->previewBudgetReduction($fy, $ver, $form);
            } catch (\Throwable $previewError) {
                $this->flashError('Budget reduction preview refresh failed: ' . $previewError->getMessage());
            }

            $currentVersion = $model->getVersion($fy, $ver);
            $isExecutionVersion = $currentVersion !== null
                && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';

            $effectiveScopeCode = trim((string) ($form['DataObjectCode'] ?? '')) !== ''
                ? trim((string) $form['DataObjectCode'])
                : trim((string) ($form['SessionScopeDataObjectCode'] ?? ''));

            $this->renderBudgetReductionScreen(
                $model,
                $ctx,
                $currentVersion,
                $isExecutionVersion,
                $form,
                $preview,
                $mappedDimensions,
                $effectiveScopeCode
            );
        }
    }

    public function commitments(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);
        $isExecutionVersion = $currentVersion !== null
            && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';
        $supportsWorkflowEngine = $model->supportsWorkflowEngineFoundation();

        $selectedCommitmentId = (int) ($_GET['commitment_id'] ?? 0);
        $commitments = ($isExecutionVersion && $model->supportsCommitmentFoundation())
            ? $model->listCommitments($fy, $ver)
            : [];
        if ($supportsWorkflowEngine && !empty($commitments)) {
            $commitments = $model->annotateCommitmentsWithWorkflowState($fy, $ver, $commitments);
        }
        if ($selectedCommitmentId <= 0 && !empty($commitments)) {
            $selectedCommitmentId = (int) ($commitments[0]['CommitmentID'] ?? 0);
        }
        $selectedCommitment = ($isExecutionVersion && $selectedCommitmentId > 0)
            ? $model->getCommitment($fy, $ver, $selectedCommitmentId)
            : null;
        $selectedCommitmentLines = ($selectedCommitment !== null)
            ? $model->listCommitmentLines($fy, $ver, (int) ($selectedCommitment['CommitmentID'] ?? 0))
            : [];
        $selectedCommitmentWorkflow = ($isExecutionVersion && $supportsWorkflowEngine && $selectedCommitment !== null)
            ? $model->getCommitmentWorkflowState($fy, $ver, (int) ($selectedCommitment['CommitmentID'] ?? 0))
            : [];

        $this->render('execution/Commitments', [
            'title' => 'Execution Commitments',
            'context' => $ctx,
            'currentVersion' => $currentVersion,
            'isExecutionVersion' => $isExecutionVersion,
            'executionAdmin' => $this->canAdministerExecution(),
            'executionReviewer' => $this->canReviewExecution(),
            'supportsExecutionFoundation' => $model->supportsExecutionFoundation(),
            'supportsWarrantFoundation' => $model->supportsWarrantFoundation(),
            'supportsReservationFoundation' => $model->supportsReservationFoundation(),
            'supportsCommitmentFoundation' => $model->supportsCommitmentFoundation(),
            'supportsRieFoundation' => $model->supportsRieFoundation(),
            'supportsWorkflowEngine' => $supportsWorkflowEngine,
            'summary' => $isExecutionVersion ? $model->getCommitmentModuleSummary($fy, $ver) : [],
            'openingSummary' => $isExecutionVersion ? $model->getOpeningBalanceSummary($fy, $ver) : [],
            'commitments' => $commitments,
            'selectedCommitment' => $selectedCommitment,
            'selectedCommitmentLines' => $selectedCommitmentLines,
            'selectedCommitmentWorkflow' => $selectedCommitmentWorkflow,
            'balanceCandidates' => ($isExecutionVersion && $model->supportsWarrantFoundation() && $model->supportsCommitmentFoundation()) ? $model->listCommitmentBalanceCandidates($fy, $ver) : [],
            'approvedRies' => ($isExecutionVersion && $model->supportsRieFoundation() && $model->supportsCommitmentFoundation()) ? $model->listApprovedRies($fy, $ver) : [],
        ]);
    }

    public function ries(): void
    {
        $model = $this->buildModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentVersion = $model->getVersion($fy, $ver);
        $isExecutionVersion = $currentVersion !== null
            && strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? ''))) === 'EXECUTION';

        $selectedRieId = (int) ($_GET['rie_id'] ?? 0);
        $supportsWorkflowEngine = $model->supportsWorkflowEngineFoundation();
        $ries = ($isExecutionVersion && $model->supportsRieFoundation())
            ? $model->listRies($fy, $ver)
            : [];
        if ($supportsWorkflowEngine && !empty($ries)) {
            $ries = $model->annotateRiesWithWorkflowState($fy, $ver, $ries);
        }
        if ($selectedRieId <= 0 && !empty($ries)) {
            $selectedRieId = (int) ($ries[0]['RieID'] ?? 0);
        }
        $selectedRie = ($isExecutionVersion && $selectedRieId > 0)
            ? $model->getRie($fy, $ver, $selectedRieId)
            : null;
        $selectedRieLines = ($selectedRie !== null)
            ? $model->listRieLines($fy, $ver, (int) ($selectedRie['RieID'] ?? 0))
            : [];
        $selectedRieWorkflow = ($isExecutionVersion && $supportsWorkflowEngine && $selectedRie !== null)
            ? $model->getRieWorkflowState($fy, $ver, (int) ($selectedRie['RieID'] ?? 0))
            : [];

        $this->render('execution/Rie', [
            'title' => 'Execution RIE',
            'context' => $ctx,
            'currentVersion' => $currentVersion,
            'isExecutionVersion' => $isExecutionVersion,
            'executionAdmin' => $this->canAdministerExecution(),
            'executionReviewer' => $this->canReviewExecution(),
            'supportsExecutionFoundation' => $model->supportsExecutionFoundation(),
            'supportsWarrantFoundation' => $model->supportsWarrantFoundation(),
            'supportsReservationFoundation' => $model->supportsReservationFoundation(),
            'supportsCommitmentFoundation' => $model->supportsCommitmentFoundation(),
            'supportsRieFoundation' => $model->supportsRieFoundation(),
            'supportsWorkflowEngine' => $supportsWorkflowEngine,
            'summary' => $isExecutionVersion ? $model->getRieModuleSummary($fy, $ver) : [],
            'openingSummary' => $isExecutionVersion ? $model->getOpeningBalanceSummary($fy, $ver) : [],
            'ries' => $ries,
            'selectedRie' => $selectedRie,
            'selectedRieLines' => $selectedRieLines,
            'selectedRieWorkflow' => $selectedRieWorkflow,
            'balanceCandidates' => ($isExecutionVersion && $model->supportsWarrantFoundation() && $model->supportsRieFoundation()) ? $model->listRieBalanceCandidates($fy, $ver) : [],
        ]);
    }

    public function saveWarrant(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $model = $this->buildModel();

        try {
            $warrantId = $model->createWarrant([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'WarrantTitle' => trim((string) ($_POST['WarrantTitle'] ?? '')),
                'WarrantDate' => (string) ($_POST['WarrantDate'] ?? ''),
                'EffectiveDate' => (string) ($_POST['EffectiveDate'] ?? ''),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'ScopeDataObjectCode' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('Warrant created.');
            header('Location: index.php?route=execution/warrants&warrant_id=' . $warrantId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Warrant save failed: ' . $e->getMessage());
            header('Location: index.php?route=execution/warrants');
            exit;
        }
    }

    public function saveWarrantLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $warrantId = (int) ($_POST['WarrantID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->addWarrantLine([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'WarrantID' => $warrantId,
                'ExecutionOpeningBalanceID' => (int) ($_POST['ExecutionOpeningBalanceID'] ?? 0),
                'ReleaseAmount' => (float) ($_POST['ReleaseAmount'] ?? 0),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('Warrant line added.');
        } catch (\Throwable $e) {
            $this->flashError('Warrant line save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/warrants&warrant_id=' . $warrantId);
        exit;
    }

    public function deleteWarrantLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $warrantId = (int) ($_POST['WarrantID'] ?? 0);
        $lineId = (int) ($_POST['WarrantLineID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->deleteWarrantLine($fy, $ver, $lineId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Warrant line removed.');
        } catch (\Throwable $e) {
            $this->flashError('Warrant line removal failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/warrants&warrant_id=' . $warrantId);
        exit;
    }

    public function submitWarrant(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $warrantId = (int) ($_POST['WarrantID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionWarrantWorkflow($fy, $ver, $warrantId, 'SUBMIT', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Warrant submitted for Technical Review.');
        } catch (\Throwable $e) {
            $this->flashError('Warrant workflow submission failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/warrants&warrant_id=' . $warrantId);
        exit;
    }

    public function forwardWarrant(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $warrantId = (int) ($_POST['WarrantID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionWarrantWorkflow($fy, $ver, $warrantId, 'FORWARD', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Warrant forwarded to Final Approval.');
        } catch (\Throwable $e) {
            $this->flashError('Warrant workflow forwarding failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/warrants&warrant_id=' . $warrantId);
        exit;
    }

    public function returnWarrant(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $warrantId = (int) ($_POST['WarrantID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionWarrantWorkflow($fy, $ver, $warrantId, 'RETURN', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Warrant returned to the previous workflow stage.');
        } catch (\Throwable $e) {
            $this->flashError('Warrant workflow return failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/warrants&warrant_id=' . $warrantId);
        exit;
    }

    public function approveWarrant(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $warrantId = (int) ($_POST['WarrantID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->approveWarrant($fy, $ver, $warrantId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Warrant approved.');
        } catch (\Throwable $e) {
            $this->flashError('Warrant approval failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/warrants&warrant_id=' . $warrantId);
        exit;
    }

    public function cancelWarrant(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $warrantId = (int) ($_POST['WarrantID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->cancelWarrant($fy, $ver, $warrantId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Warrant cancelled.');
        } catch (\Throwable $e) {
            $this->flashError('Warrant cancellation failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/warrants&warrant_id=' . $warrantId);
        exit;
    }

    public function saveReservation(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $model = $this->buildModel();

        try {
            $reservationId = $model->createReservation([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'ReservationTitle' => trim((string) ($_POST['ReservationTitle'] ?? '')),
                'ReservationDate' => (string) ($_POST['ReservationDate'] ?? ''),
                'EffectiveDate' => (string) ($_POST['EffectiveDate'] ?? ''),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'ScopeDataObjectCode' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('Reservation created.');
            header('Location: index.php?route=execution/reservations&reservation_id=' . $reservationId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Reservation save failed: ' . $e->getMessage());
            header('Location: index.php?route=execution/reservations');
            exit;
        }
    }

    public function saveReservationLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $reservationId = (int) ($_POST['ReservationID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->addReservationLine([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'ReservationID' => $reservationId,
                'ExecutionOpeningBalanceID' => (int) ($_POST['ExecutionOpeningBalanceID'] ?? 0),
                'ReservationAmount' => (float) ($_POST['ReservationAmount'] ?? 0),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('Reservation line added.');
        } catch (\Throwable $e) {
            $this->flashError('Reservation line save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/reservations&reservation_id=' . $reservationId);
        exit;
    }

    public function deleteReservationLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $reservationId = (int) ($_POST['ReservationID'] ?? 0);
        $lineId = (int) ($_POST['ReservationLineID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->deleteReservationLine($fy, $ver, $lineId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Reservation line removed.');
        } catch (\Throwable $e) {
            $this->flashError('Reservation line removal failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/reservations&reservation_id=' . $reservationId);
        exit;
    }

    public function submitReservation(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $reservationId = (int) ($_POST['ReservationID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionReservationWorkflow($fy, $ver, $reservationId, 'SUBMIT', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Reservation submitted for Technical Review.');
        } catch (\Throwable $e) {
            $this->flashError('Reservation workflow submission failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/reservations&reservation_id=' . $reservationId);
        exit;
    }

    public function forwardReservation(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $reservationId = (int) ($_POST['ReservationID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionReservationWorkflow($fy, $ver, $reservationId, 'FORWARD', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Reservation forwarded to Final Approval.');
        } catch (\Throwable $e) {
            $this->flashError('Reservation workflow forwarding failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/reservations&reservation_id=' . $reservationId);
        exit;
    }

    public function returnReservation(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $reservationId = (int) ($_POST['ReservationID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionReservationWorkflow($fy, $ver, $reservationId, 'RETURN', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Reservation returned to the previous workflow stage.');
        } catch (\Throwable $e) {
            $this->flashError('Reservation workflow return failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/reservations&reservation_id=' . $reservationId);
        exit;
    }

    public function approveReservation(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $reservationId = (int) ($_POST['ReservationID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->approveReservation($fy, $ver, $reservationId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Reservation approved.');
        } catch (\Throwable $e) {
            $this->flashError('Reservation approval failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/reservations&reservation_id=' . $reservationId);
        exit;
    }

    public function cancelReservation(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $reservationId = (int) ($_POST['ReservationID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->cancelReservation($fy, $ver, $reservationId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Reservation cancelled.');
        } catch (\Throwable $e) {
            $this->flashError('Reservation cancellation failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/reservations&reservation_id=' . $reservationId);
        exit;
    }

    public function saveSupplementary(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $model = $this->buildModel();

        try {
            $supplementaryId = $model->createSupplementary([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'SupplementaryTitle' => trim((string) ($_POST['SupplementaryTitle'] ?? '')),
                'SupplementaryDate' => (string) ($_POST['SupplementaryDate'] ?? ''),
                'EffectiveDate' => (string) ($_POST['EffectiveDate'] ?? ''),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'ScopeDataObjectCode' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('Supplementary budget created.');
            header('Location: index.php?route=execution/supplementaries&supplementary_id=' . $supplementaryId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Supplementary budget save failed: ' . $e->getMessage());
            header('Location: index.php?route=execution/supplementaries');
            exit;
        }
    }

    public function saveSupplementaryLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $supplementaryId = (int) ($_POST['SupplementaryBudgetID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->addSupplementaryLine([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'SupplementaryBudgetID' => $supplementaryId,
                'ExecutionOpeningBalanceID' => (int) ($_POST['ExecutionOpeningBalanceID'] ?? 0),
                'AdjustmentAmount' => (float) ($_POST['AdjustmentAmount'] ?? 0),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('Supplementary line added.');
        } catch (\Throwable $e) {
            $this->flashError('Supplementary line save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/supplementaries&supplementary_id=' . $supplementaryId);
        exit;
    }

    public function submitSupplementary(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $supplementaryId = (int) ($_POST['SupplementaryBudgetID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionSupplementaryWorkflow($fy, $ver, $supplementaryId, 'SUBMIT', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Supplementary budget submitted for Technical Review.');
        } catch (\Throwable $e) {
            $this->flashError('Supplementary workflow submission failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/supplementaries&supplementary_id=' . $supplementaryId);
        exit;
    }

    public function forwardSupplementary(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $supplementaryId = (int) ($_POST['SupplementaryBudgetID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionSupplementaryWorkflow($fy, $ver, $supplementaryId, 'FORWARD', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Supplementary budget forwarded to Final Approval.');
        } catch (\Throwable $e) {
            $this->flashError('Supplementary workflow forwarding failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/supplementaries&supplementary_id=' . $supplementaryId);
        exit;
    }

    public function returnSupplementary(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $supplementaryId = (int) ($_POST['SupplementaryBudgetID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionSupplementaryWorkflow($fy, $ver, $supplementaryId, 'RETURN', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Supplementary budget returned to the previous workflow stage.');
        } catch (\Throwable $e) {
            $this->flashError('Supplementary workflow return failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/supplementaries&supplementary_id=' . $supplementaryId);
        exit;
    }

    public function deleteSupplementaryLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $supplementaryId = (int) ($_POST['SupplementaryBudgetID'] ?? 0);
        $lineId = (int) ($_POST['SupplementaryBudgetLineID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->deleteSupplementaryLine($fy, $ver, $lineId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Supplementary line removed.');
        } catch (\Throwable $e) {
            $this->flashError('Supplementary line removal failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/supplementaries&supplementary_id=' . $supplementaryId);
        exit;
    }

    public function approveSupplementary(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $supplementaryId = (int) ($_POST['SupplementaryBudgetID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->approveSupplementary($fy, $ver, $supplementaryId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Supplementary budget approved.');
        } catch (\Throwable $e) {
            $this->flashError('Supplementary budget approval failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/supplementaries&supplementary_id=' . $supplementaryId);
        exit;
    }

    public function cancelSupplementary(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $supplementaryId = (int) ($_POST['SupplementaryBudgetID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->cancelSupplementary($fy, $ver, $supplementaryId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Supplementary budget cancelled.');
        } catch (\Throwable $e) {
            $this->flashError('Supplementary budget cancellation failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/supplementaries&supplementary_id=' . $supplementaryId);
        exit;
    }

    public function saveCommitment(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $model = $this->buildModel();

        try {
            $commitmentId = $model->createCommitment([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'RieID' => (int) ($_POST['RieID'] ?? 0),
                'CommitmentTitle' => trim((string) ($_POST['CommitmentTitle'] ?? '')),
                'CommitmentDate' => (string) ($_POST['CommitmentDate'] ?? ''),
                'EffectiveDate' => (string) ($_POST['EffectiveDate'] ?? ''),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'ScopeDataObjectCode' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('Commitment created.');
            header('Location: index.php?route=execution/commitments&commitment_id=' . $commitmentId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Commitment save failed: ' . $e->getMessage());
            header('Location: index.php?route=execution/commitments');
            exit;
        }
    }

    public function saveCommitmentLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $commitmentId = (int) ($_POST['CommitmentID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->addCommitmentLine([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'CommitmentID' => $commitmentId,
                'ExecutionOpeningBalanceID' => (int) ($_POST['ExecutionOpeningBalanceID'] ?? 0),
                'CommitmentAmount' => (float) ($_POST['CommitmentAmount'] ?? 0),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('Commitment line added.');
        } catch (\Throwable $e) {
            $this->flashError('Commitment line save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/commitments&commitment_id=' . $commitmentId);
        exit;
    }

    public function deleteCommitmentLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $commitmentId = (int) ($_POST['CommitmentID'] ?? 0);
        $lineId = (int) ($_POST['CommitmentLineID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->deleteCommitmentLine($fy, $ver, $lineId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Commitment line removed.');
        } catch (\Throwable $e) {
            $this->flashError('Commitment line removal failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/commitments&commitment_id=' . $commitmentId);
        exit;
    }

    public function submitCommitment(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $commitmentId = (int) ($_POST['CommitmentID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionCommitmentWorkflow($fy, $ver, $commitmentId, 'SUBMIT', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Commitment submitted for Technical Review.');
        } catch (\Throwable $e) {
            $this->flashError('Commitment workflow submission failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/commitments&commitment_id=' . $commitmentId);
        exit;
    }

    public function forwardCommitment(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $commitmentId = (int) ($_POST['CommitmentID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionCommitmentWorkflow($fy, $ver, $commitmentId, 'FORWARD', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Commitment forwarded to Final Approval.');
        } catch (\Throwable $e) {
            $this->flashError('Commitment workflow forwarding failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/commitments&commitment_id=' . $commitmentId);
        exit;
    }

    public function returnCommitment(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $commitmentId = (int) ($_POST['CommitmentID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionCommitmentWorkflow($fy, $ver, $commitmentId, 'RETURN', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Commitment returned to the previous workflow stage.');
        } catch (\Throwable $e) {
            $this->flashError('Commitment workflow return failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/commitments&commitment_id=' . $commitmentId);
        exit;
    }

    public function approveCommitment(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $commitmentId = (int) ($_POST['CommitmentID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->approveCommitment($fy, $ver, $commitmentId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Commitment approved.');
        } catch (\Throwable $e) {
            $this->flashError('Commitment approval failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/commitments&commitment_id=' . $commitmentId);
        exit;
    }

    public function cancelCommitment(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $commitmentId = (int) ($_POST['CommitmentID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->cancelCommitment($fy, $ver, $commitmentId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('Commitment cancelled.');
        } catch (\Throwable $e) {
            $this->flashError('Commitment cancellation failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/commitments&commitment_id=' . $commitmentId);
        exit;
    }

    public function saveRie(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $model = $this->buildModel();

        try {
            $rieId = $model->createRie([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'RieTitle' => trim((string) ($_POST['RieTitle'] ?? '')),
                'RieDate' => (string) ($_POST['RieDate'] ?? ''),
                'EffectiveDate' => (string) ($_POST['EffectiveDate'] ?? ''),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'ScopeDataObjectCode' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('RIE created.');
            header('Location: index.php?route=execution/rie&rie_id=' . $rieId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('RIE save failed: ' . $e->getMessage());
            header('Location: index.php?route=execution/rie');
            exit;
        }
    }

    public function saveRieLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $rieId = (int) ($_POST['RieID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->addRieLine([
                'FiscalYearID' => $fy,
                'ExecutionVersionID' => $ver,
                'RieID' => $rieId,
                'ExecutionOpeningBalanceID' => (int) ($_POST['ExecutionOpeningBalanceID'] ?? 0),
                'RieAmount' => (float) ($_POST['RieAmount'] ?? 0),
                'Notes' => trim((string) ($_POST['Notes'] ?? '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $this->flashSuccess('RIE line added.');
        } catch (\Throwable $e) {
            $this->flashError('RIE line save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/rie&rie_id=' . $rieId);
        exit;
    }

    public function deleteRieLine(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $rieId = (int) ($_POST['RieID'] ?? 0);
        $lineId = (int) ($_POST['RieLineID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->deleteRieLine($fy, $ver, $lineId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('RIE line removed.');
        } catch (\Throwable $e) {
            $this->flashError('RIE line removal failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/rie&rie_id=' . $rieId);
        exit;
    }

    public function submitRie(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $rieId = (int) ($_POST['RieID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionRieWorkflow($fy, $ver, $rieId, 'SUBMIT', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('RIE submitted for Technical Review.');
        } catch (\Throwable $e) {
            $this->flashError('RIE workflow submission failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/rie&rie_id=' . $rieId);
        exit;
    }

    public function forwardRie(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $rieId = (int) ($_POST['RieID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionRieWorkflow($fy, $ver, $rieId, 'FORWARD', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('RIE forwarded to Final Approval.');
        } catch (\Throwable $e) {
            $this->flashError('RIE workflow forwarding failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/rie&rie_id=' . $rieId);
        exit;
    }

    public function returnRie(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $rieId = (int) ($_POST['RieID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->transitionRieWorkflow($fy, $ver, $rieId, 'RETURN', (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('RIE returned to the previous workflow stage.');
        } catch (\Throwable $e) {
            $this->flashError('RIE workflow return failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/rie&rie_id=' . $rieId);
        exit;
    }

    public function approveRie(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $rieId = (int) ($_POST['RieID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->approveRie($fy, $ver, $rieId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('RIE approved.');
        } catch (\Throwable $e) {
            $this->flashError('RIE approval failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/rie&rie_id=' . $rieId);
        exit;
    }

    public function cancelRie(): void
    {
        $this->assertPostWithCsrf();

        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $rieId = (int) ($_POST['RieID'] ?? 0);
        $model = $this->buildModel();

        try {
            $model->cancelRie($fy, $ver, $rieId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess('RIE cancelled.');
        } catch (\Throwable $e) {
            $this->flashError('RIE cancellation failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=execution/rie&rie_id=' . $rieId);
        exit;
    }

    private function buildModel(): BudgetExecutionModel
    {
        return new BudgetExecutionModel($this->db);
    }

    private function canAdministerExecution(): bool
    {
        return Rbac::hasAnyRole(self::EXECUTION_ADMIN_ROLES);
    }

    private function canReviewExecution(): bool
    {
        return Rbac::hasAnyRole(self::EXECUTION_REVIEW_ROLES);
    }

    protected function assertPostWithCsrf(string $redirectUrl = 'index.php?route=execution/index'): void
    {
        parent::assertPostWithCsrf($redirectUrl);
    }

    private function setFiscalContext(int $fiscalYearId, int $versionId): void
    {
        SessionHelper::set('FiscalYearID', $fiscalYearId);
        SessionHelper::set('VersionID', $versionId);
        SessionHelper::set('context.fiscal_year_id', $fiscalYearId);
        SessionHelper::set('context.version_id', $versionId);
    }

    private function readBudgetReductionForm(): array
    {
        $dimensionFilters = [];
        $postedDimensionFilters = is_array($_POST['DimensionFilters'] ?? null) ? $_POST['DimensionFilters'] : [];
        foreach ($postedDimensionFilters as $code => $value) {
            $dimensionFilters[strtoupper(trim((string) $code))] = trim((string) $value);
        }

        return [
            'ReductionTitle' => trim((string) ($_POST['ReductionTitle'] ?? '')),
            'ReductionNotes' => trim((string) ($_POST['ReductionNotes'] ?? '')),
            'ReductionMethod' => strtoupper(trim((string) ($_POST['ReductionMethod'] ?? 'PERCENTAGE'))),
            'ReductionValue' => trim((string) ($_POST['ReductionValue'] ?? '')),
            'DataObjectCode' => trim((string) ($_POST['DataObjectCode'] ?? '')),
            'SessionScopeDataObjectCode' => trim((string) SessionHelper::get('scope.dataobject_code', '')),
            'DimensionFilters' => $dimensionFilters,
        ];
    }

    private function renderBudgetReductionScreen(
        BudgetExecutionModel $model,
        array $context,
        ?array $currentVersion,
        bool $isExecutionVersion,
        array $form,
        ?array $preview,
        array $mappedDimensions,
        string $effectiveScopeCode
    ): void {
        $fy = (int) ($context['FiscalYearID'] ?? 0);
        $this->render('execution/BudgetReductions', [
            'title' => 'Budget Reduction Wizard',
            'context' => $context,
            'currentVersion' => $currentVersion,
            'isExecutionVersion' => $isExecutionVersion,
            'executionAccess' => true,
            'supportsExecutionFoundation' => $model->supportsExecutionFoundation(),
            'supportsSupplementaryFoundation' => $model->supportsSupplementaryFoundation(),
            'supportsWarrantFoundation' => $model->supportsWarrantFoundation(),
            'openingSummary' => $isExecutionVersion ? $model->getOpeningBalanceSummary($fy, (int) ($context['VersionID'] ?? 0)) : [],
            'form' => $form,
            'preview' => $preview,
            'mappedDimensions' => $mappedDimensions,
            'dataObjectOptions' => $model->listBudgetReductionDataObjectOptions($fy, trim((string) ($form['SessionScopeDataObjectCode'] ?? ''))),
            'dimensionOptions' => $model->listBudgetReductionDimensionOptions($fy, $effectiveScopeCode, $mappedDimensions),
            'sessionScopeDataObjectCode' => trim((string) ($form['SessionScopeDataObjectCode'] ?? '')),
        ]);
    }
}
