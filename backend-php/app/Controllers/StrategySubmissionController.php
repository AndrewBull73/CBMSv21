<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\StrategicBudgetingAdminModel;
use App\Models\StrategicBudgetingModel;
use App\Models\SystemSettingsModel;
use App\Models\UserModel;
use App\Models\WorkflowTaskModel;
use App\Models\WorkflowAssignmentModel;
use App\Models\WorkflowTaskStatusModel;
use App\Models\WorkflowTaskTypeModel;
use App\Services\MailService;
use App\Shared\SessionHelper;

final class StrategySubmissionController extends BaseController
{
    private const PREPARER_PERMS = ['STRATEGY_SUBMISSION_PREPARE', 'ADMIN_ALL', 'SYSADMIN'];
    private const REVIEWER_PERMS = ['STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'];
    private const APPROVER_PERMS = ['STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'];
    private const PUBLISHER_PERMS = ['STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'];

    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['STRATEGY_SUBMISSION_PREPARE', 'STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['STRATEGY_SUBMISSION_PREPARE', 'STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN']],
        'lodgements' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'form' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'save' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'view' => ['auth' => true, 'permsAny' => ['STRATEGY_SUBMISSION_PREPARE', 'STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN']],
        'line-form' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'lineForm' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'save-line' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'saveLine' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'delete-line' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'deleteLine' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'upload-attachment' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'uploadAttachment' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'attachment-list' => ['auth' => true, 'permsAny' => ['STRATEGY_SUBMISSION_PREPARE', 'STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN']],
        'download-attachment' => ['auth' => true, 'permsAny' => ['STRATEGY_SUBMISSION_PREPARE', 'STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN']],
        'delete-attachment' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'deleteAttachment' => ['auth' => true, 'permsAny' => self::PREPARER_PERMS],
        'reviews' => ['auth' => true, 'permsAny' => self::REVIEWER_PERMS],
        'review-line' => ['auth' => true, 'permsAny' => self::REVIEWER_PERMS],
        'reviewLine' => ['auth' => true, 'permsAny' => self::REVIEWER_PERMS],
        'save-review' => ['auth' => true, 'permsAny' => self::REVIEWER_PERMS],
        'saveReview' => ['auth' => true, 'permsAny' => self::REVIEWER_PERMS],
        'save-assessment' => ['auth' => true, 'permsAny' => self::REVIEWER_PERMS],
        'saveAssessment' => ['auth' => true, 'permsAny' => self::REVIEWER_PERMS],
        'approvals' => ['auth' => true, 'permsAny' => self::APPROVER_PERMS],
        'transition' => ['auth' => true, 'permsAny' => ['STRATEGY_SUBMISSION_PREPARE', 'STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN']],
        'report' => ['auth' => true, 'permsAny' => ['STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN']],
        'publish-sector-ceilings' => ['auth' => true, 'permsAny' => self::PUBLISHER_PERMS],
        'publishSectorCeilings' => ['auth' => true, 'permsAny' => self::PUBLISHER_PERMS],
    ];

    protected bool $requiresContext = true;

    public function list(): void
    {
        $this->renderSubmissionListPage('all');
    }

    public function lodgements(): void
    {
        $this->renderSubmissionListPage('lodgements');
    }

    public function reviews(): void
    {
        $this->renderSubmissionListPage('reviews');
    }

    public function approvals(): void
    {
        $this->renderSubmissionListPage('approvals');
    }

    private function renderSubmissionListPage(string $mode): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $currentUserId = (int) SessionHelper::get('auth.user_id', 1);
        $submissions = $this->filterFundingSubmissionsForMode($admin->listFundingSubmissions($fy, $ver), $mode, $currentUserId);

        $pageTitle = match ($mode) {
            'lodgements' => 'Funding Lodgements',
            'reviews' => 'Funding Reviews',
            'approvals' => 'Funding Approvals',
            default => 'Funding Submissions',
        };
        $pageSubtitle = match ($mode) {
            'lodgements' => 'Your submitter workspace for drafting, lodging, tracking, and resubmitting funding requests.',
            'reviews' => 'Reviewer work queue for submissions awaiting or completing assessment.',
            'approvals' => 'Approver and publisher work queue for final decisions after review.',
            default => 'Funding lodgements and formal submissions across the workflow.',
        };

        $this->render('strategy/FundingSubmissionList', [
            'title' => $pageTitle,
            'context' => $ctx,
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'workflowInstalled' => $admin->supportsFundingSubmissionWorkflow(),
            'submissions' => $submissions,
            'pageTitle' => $pageTitle,
            'pageSubtitle' => $pageSubtitle,
            'listMode' => $mode,
        ]);
    }

    public function report(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);

        $summary = $admin->getFundingSubmissionSummaryReport($fy, $ver);

        $this->render('strategy/FundingSubmissionReport', [
            'title' => 'Funding Submission Summary',
            'context' => $ctx,
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'workflowInstalled' => $admin->supportsFundingSubmissionWorkflow(),
            'summary' => $summary['summary'],
            'bySector' => $summary['bySector'],
            'byProgram' => $summary['byProgram'],
            'submissions' => $summary['submissions'],
        ]);
    }

    public function form(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        $submission = $id > 0 ? $admin->getFundingSubmission($id) : null;
        $scopeDataObjectCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));
        $scopeDataObjectName = trim((string) SessionHelper::get('scope.dataobject_name', ''));

        $this->render('strategy/FundingSubmissionForm', [
            'title' => $id > 0 ? 'Edit Funding Lodgement' : 'New Funding Lodgement',
            'context' => $ctx,
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'submission' => $submission,
            'workflowInstalled' => $admin->supportsFundingSubmissionWorkflow(),
            'attachmentsInstalled' => $admin->supportsFundingSubmissionAttachments(),
            'dataObjectOptions' => $admin->listDataScopeOrgUnitOptions($fy, null),
            'scopeDataObjectCode' => $scopeDataObjectCode,
            'scopeDataObjectName' => $scopeDataObjectName,
            'submissionTypeOptions' => $admin->getFundingSubmissionTypeOptions(),
            'priorityOptions' => $admin->getFundingSubmissionPriorityOptions(),
            'attachments' => $submission !== null ? $admin->listFundingSubmissionAttachments((int) ($submission['StrategicFundingSubmissionID'] ?? 0)) : [],
        ]);
    }

    public function save(): void
    {
        $this->assertStrategicContextEditable('index.php?route=strategy-submissions/list');

        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_POST['StrategicFundingSubmissionID'] ?? 0);
        $scopeDataObjectCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));

        if ($scopeDataObjectCode === '') {
            $this->flashError('Select a DataScope in the menu header before saving a funding lodgement.');
            header('Location: index.php?route=strategy-submissions/form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }

        try {
            $submissionId = $admin->saveFundingSubmission([
                'FiscalYearID' => $fy,
                'VersionID' => $ver,
                'DataObjectCode' => $scopeDataObjectCode,
                'RequestTitle' => trim((string) ($_POST['RequestTitle'] ?? '')),
                'RequestNotes' => trim((string) ($_POST['RequestNotes'] ?? '')),
                'SubmissionTypeCode' => trim((string) ($_POST['SubmissionTypeCode'] ?? '')),
                'PriorityCode' => trim((string) ($_POST['PriorityCode'] ?? '')),
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ], $id > 0 ? $id : null);
            $uploadedCount = $this->storeUploadedSubmissionAttachments($admin, $submissionId);
            $this->flashSuccess('Funding submission saved.');
            if ($uploadedCount > 0) {
                $this->flashSuccess('Funding submission saved with ' . $uploadedCount . ' attachment(s).');
            }
            header('Location: index.php?route=strategy-submissions/view&id=' . $submissionId);
            exit;
        } catch (\Throwable $e) {
            $this->flashError('Funding submission save failed: ' . $e->getMessage());
            header('Location: index.php?route=strategy-submissions/form' . ($id > 0 ? '&id=' . $id : ''));
            exit;
        }
    }

    public function view(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $id = (int) ($_GET['id'] ?? 0);
        $submission = $admin->getFundingSubmission($id);

        if ($submission === null) {
            $this->flashError('Funding submission was not found.');
            header('Location: index.php?route=strategy-submissions/list');
            exit;
        }

        $this->render('strategy/FundingSubmissionView', [
            'title' => 'Funding Submission',
            'context' => $ctx,
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'workflowInstalled' => $admin->supportsFundingSubmissionWorkflow(),
            'attachmentsInstalled' => $admin->supportsFundingSubmissionAttachments(),
            'reviewResponseFieldsInstalled' => $admin->supportsFundingSubmissionReviewResponseFields(),
            'assessmentFieldsInstalled' => $admin->supportsFundingSubmissionHeaderAssessmentFields(),
            'assessmentGradeOptions' => $admin->getFundingSubmissionAssessmentGradeOptions(),
            'reviewerRecommendationOptions' => $admin->getFundingSubmissionReviewerRecommendationOptions(),
            'submission' => $submission,
            'lines' => $admin->listFundingSubmissionLines($id),
            'history' => $admin->listFundingSubmissionHistory($id),
            'attachments' => $admin->listFundingSubmissionAttachments($id),
            'canPrepareSubmission' => $this->canPrepareFundingSubmission(),
            'canReviewSubmission' => $this->canReviewFundingSubmission(),
            'canApproveSubmission' => $this->canApproveFundingSubmission(),
            'canPublishSubmission' => $this->canPublishFundingSubmission(),
            'supportsStrategicCeilings' => $admin->supportsStrategicCeilings(),
        ]);
    }

    public function lineForm(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $submissionId = (int) ($_GET['submission_id'] ?? 0);
        $lineId = (int) ($_GET['id'] ?? 0);
        $submission = $admin->getFundingSubmission($submissionId);
        $line = $lineId > 0 ? $admin->getFundingSubmissionLine($lineId) : null;

        if ($submission === null) {
            $this->flashError('Funding submission was not found.');
            header('Location: index.php?route=strategy-submissions/list');
            exit;
        }

        $submissionFiscalYearId = (int) ($submission['FiscalYearID'] ?? $fy);
        $submissionDataObjectCode = trim((string) ($submission['DataObjectCode'] ?? ''));
        $currentScopeDataObjectCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));
        $effectiveDataObjectCode = $this->resolveEffectiveFundingSubmissionScope(
            $admin,
            $submissionFiscalYearId,
            $submissionDataObjectCode,
            $currentScopeDataObjectCode
        );

        if ($line === null) {
            $defaultKey = $this->fundingItemSegmentDefaultsSessionKey($effectiveDataObjectCode);
            $savedDefaults = SessionHelper::get($defaultKey, []);
            if (is_array($savedDefaults) && $savedDefaults !== []) {
                $line = array_merge([
                    'SectorID' => 0,
                    'ProgramID' => 0,
                    'SubProgramID' => 0,
                    'ProjectID' => 0,
                    'ActivityID' => 0,
                    'OrgUnitID' => 0,
                    'FundingTypeID' => 0,
                    'FundingSourceID' => 0,
                ], $savedDefaults);
            }
        }

        $this->render('strategy/FundingSubmissionLineForm', [
            'title' => $lineId > 0 ? 'Edit Funding Item' : 'Add Funding Item',
            'context' => $ctx,
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'submission' => $submission,
            'effectiveDataObjectCode' => $effectiveDataObjectCode,
            'line' => $line,
            'sectorOptions' => $admin->listSectorOptionsForDataObject($submissionFiscalYearId, $effectiveDataObjectCode),
            'programOptions' => $admin->listProgramOptionsForDataObject($submissionFiscalYearId, $effectiveDataObjectCode),
            'subProgramOptions' => $admin->listSubProgramOptionsForDataObject($submissionFiscalYearId, $effectiveDataObjectCode),
            'projectOptions' => $admin->listProjectOptionsForDataObject($submissionFiscalYearId, $effectiveDataObjectCode),
            'activityOptions' => $admin->listActivityOptionsForDataObject($submissionFiscalYearId, $effectiveDataObjectCode),
            'orgUnitOptions' => $admin->listOrgUnitOptionsForDataObject($submissionFiscalYearId, $effectiveDataObjectCode, (int) SessionHelper::get('auth.user_id', 1)),
            'fundingTypeOptions' => $admin->listFundingTypeOptions(),
            'fundingSourceOptions' => $admin->listFundingSourceOptions(),
            'economicItemOptions' => $admin->listEconomicItemOptions(),
        ]);
    }

    public function saveLine(): void
    {
        $this->assertStrategicContextEditable('index.php?route=strategy-submissions/list');

        $admin = $this->buildAdminModel();
        $submissionId = (int) ($_POST['StrategicFundingSubmissionID'] ?? 0);
        $lineId = (int) ($_POST['StrategicFundingSubmissionLineID'] ?? 0);

        try {
            $admin->saveFundingSubmissionLine([
                'StrategicFundingSubmissionID' => $submissionId,
                'SectorID' => (int) ($_POST['SectorID'] ?? 0),
                'ProgramID' => (int) ($_POST['ProgramID'] ?? 0),
                'SubProgramID' => (int) ($_POST['SubProgramID'] ?? 0),
                'ProjectID' => (int) ($_POST['ProjectID'] ?? 0),
                'ActivityID' => (int) ($_POST['ActivityID'] ?? 0),
                'OrgUnitID' => (int) ($_POST['OrgUnitID'] ?? 0),
                'FundingTypeID' => (int) ($_POST['FundingTypeID'] ?? 0),
                'FundingSourceID' => (int) ($_POST['FundingSourceID'] ?? 0),
                'EconomicItemID' => (int) ($_POST['EconomicItemID'] ?? 0),
                'BidTitle' => trim((string) ($_POST['BidTitle'] ?? '')),
                'BusinessCaseSummary' => trim((string) ($_POST['BusinessCaseSummary'] ?? '')),
                'ExpectedOutput' => trim((string) ($_POST['ExpectedOutput'] ?? '')),
                'ExpectedOutcome' => trim((string) ($_POST['ExpectedOutcome'] ?? '')),
                'CurrentYearRequestedAmount' => (string) ($_POST['CurrentYearRequestedAmount'] ?? '0'),
                'OuterYear1RequestedAmount' => (string) ($_POST['OuterYear1RequestedAmount'] ?? ''),
                'OuterYear2RequestedAmount' => (string) ($_POST['OuterYear2RequestedAmount'] ?? ''),
                'OuterYear3RequestedAmount' => (string) ($_POST['OuterYear3RequestedAmount'] ?? ''),
                'OuterYear4RequestedAmount' => (string) ($_POST['OuterYear4RequestedAmount'] ?? ''),
                'OuterYear5RequestedAmount' => (string) ($_POST['OuterYear5RequestedAmount'] ?? ''),
                'PriorityRank' => (string) ($_POST['PriorityRank'] ?? ''),
                'ActiveFlag' => isset($_POST['ActiveFlag']) ? 1 : 0,
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ], $lineId > 0 ? $lineId : null);
            $submission = $admin->getFundingSubmission($submissionId);
            if ($submission !== null) {
                $submissionFiscalYearId = (int) ($submission['FiscalYearID'] ?? 0);
                $submissionDataObjectCode = trim((string) ($submission['DataObjectCode'] ?? ''));
                $currentScopeDataObjectCode = trim((string) SessionHelper::get('scope.dataobject_code', ''));
                $effectiveDataObjectCode = $this->resolveEffectiveFundingSubmissionScope(
                    $admin,
                    $submissionFiscalYearId,
                    $submissionDataObjectCode,
                    $currentScopeDataObjectCode
                );
                SessionHelper::set($this->fundingItemSegmentDefaultsSessionKey($effectiveDataObjectCode), [
                    'SectorID' => (int) ($_POST['SectorID'] ?? 0),
                    'ProgramID' => (int) ($_POST['ProgramID'] ?? 0),
                    'SubProgramID' => (int) ($_POST['SubProgramID'] ?? 0),
                    'ProjectID' => (int) ($_POST['ProjectID'] ?? 0),
                    'ActivityID' => (int) ($_POST['ActivityID'] ?? 0),
                    'OrgUnitID' => (int) ($_POST['OrgUnitID'] ?? 0),
                    'FundingTypeID' => (int) ($_POST['FundingTypeID'] ?? 0),
                    'FundingSourceID' => (int) ($_POST['FundingSourceID'] ?? 0),
                ]);
            }
            $this->flashSuccess('Funding item saved.');
        } catch (\Throwable $e) {
            $this->flashError('Funding item save failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-submissions/view&id=' . $submissionId);
        exit;
    }

    public function deleteLine(): void
    {
        $this->assertStrategicContextEditable('index.php?route=strategy-submissions/list');

        $admin = $this->buildAdminModel();
        $lineId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $submissionId = (int) ($_POST['submission_id'] ?? $_GET['submission_id'] ?? 0);

        try {
            $admin->deleteFundingSubmissionLine($lineId);
            $this->flashSuccess('Funding item removed.');
        } catch (\Throwable $e) {
            $this->flashError('Funding item delete failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-submissions/view&id=' . $submissionId);
        exit;
    }

    public function transition(): void
    {
        $admin = $this->buildAdminModel();
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $action = trim((string) ($_POST['submission_action'] ?? ''));
        $normalizedAction = strtolower($action);

        try {
            if (in_array($normalizedAction, ['lodge', 'submit'], true) && !$this->canPrepareFundingSubmission()) {
                throw new \RuntimeException('You do not have permission to lodge or submit funding requests.');
            }
            if (in_array($normalizedAction, ['approve', 'reject', 'fund'], true) && !$this->canApproveFundingSubmission()) {
                throw new \RuntimeException('You do not have permission to approve, reject, or fund submissions.');
            }
            $admin->transitionFundingSubmission($submissionId, $normalizedAction, (int) SessionHelper::get('auth.user_id', 1));
            $this->syncFundingSubmissionWorkflowTasks($submissionId, $normalizedAction);
            $this->flashSuccess('Funding submission updated.');
        } catch (\Throwable $e) {
            $this->flashError('Funding submission update failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-submissions/view&id=' . $submissionId);
        exit;
    }

    public function reviewLine(): void
    {
        $admin = $this->buildAdminModel();
        $report = $this->buildReportModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $submissionId = (int) ($_GET['submission_id'] ?? 0);
        $lineId = (int) ($_GET['id'] ?? 0);

        if (!$this->canReviewFundingSubmission()) {
            $this->flashError('You do not have permission to review funding submission lines.');
            header('Location: index.php?route=strategy-submissions/view&id=' . $submissionId);
            exit;
        }

        $submission = $admin->getFundingSubmission($submissionId);
        $line = $admin->getFundingSubmissionLine($lineId);
        if ($submission === null || $line === null || (int) ($line['StrategicFundingSubmissionID'] ?? 0) !== $submissionId) {
            $this->flashError('Funding item review record was not found.');
            header('Location: index.php?route=strategy-submissions/list');
            exit;
        }

        $this->render('strategy/FundingSubmissionReviewForm', [
            'title' => 'Review Funding Item',
            'context' => $ctx,
            'contextLabels' => $report->getContextLabels($fy, $ver),
            'submission' => $submission,
            'line' => $admin->getFundingSubmissionLineDetail($lineId) ?? $line,
            'reviewResponseFieldsInstalled' => $admin->supportsFundingSubmissionReviewResponseFields(),
        ]);
    }

    public function saveReview(): void
    {
        $admin = $this->buildAdminModel();
        $submissionId = (int) ($_POST['submission_id'] ?? 0);
        $lineId = (int) ($_POST['line_id'] ?? 0);

        try {
            if (!$this->canReviewFundingSubmission()) {
                throw new \RuntimeException('You do not have permission to review funding submission lines.');
            }

              $admin->saveFundingSubmissionLineReview($lineId, [
                'ReviewAction' => trim((string) ($_POST['ReviewAction'] ?? '')),
                'CurrentYearApprovedAmount' => (string) ($_POST['CurrentYearApprovedAmount'] ?? ''),
                'OuterYear1ApprovedAmount' => (string) ($_POST['OuterYear1ApprovedAmount'] ?? ''),
                'OuterYear2ApprovedAmount' => (string) ($_POST['OuterYear2ApprovedAmount'] ?? ''),
                'OuterYear3ApprovedAmount' => (string) ($_POST['OuterYear3ApprovedAmount'] ?? ''),
                'OuterYear4ApprovedAmount' => (string) ($_POST['OuterYear4ApprovedAmount'] ?? ''),
                'OuterYear5ApprovedAmount' => (string) ($_POST['OuterYear5ApprovedAmount'] ?? ''),
                'ScoreStrategicAlignment' => (string) ($_POST['ScoreStrategicAlignment'] ?? ''),
                'ScoreReadiness' => (string) ($_POST['ScoreReadiness'] ?? ''),
                'ScoreFiscalAffordability' => (string) ($_POST['ScoreFiscalAffordability'] ?? ''),
                'ScoreServiceImpact' => (string) ($_POST['ScoreServiceImpact'] ?? ''),
                'DecisionNote' => trim((string) ($_POST['DecisionNote'] ?? '')),
                'ReviewerResponse' => trim((string) ($_POST['ReviewerResponse'] ?? '')),
                'ReviewerConditions' => trim((string) ($_POST['ReviewerConditions'] ?? '')),
                'ReviewerNextSteps' => trim((string) ($_POST['ReviewerNextSteps'] ?? '')),
                  'UserID' => (int) SessionHelper::get('auth.user_id', 1),
              ]);
              $this->syncFundingSubmissionWorkflowTasks($submissionId, 'line_review');
              $this->flashSuccess('Funding item review saved.');
          } catch (\Throwable $e) {
              $this->flashError('Funding item review failed: ' . $e->getMessage());
          }

        header('Location: index.php?route=strategy-submissions/view&id=' . $submissionId);
        exit;
    }

    public function saveAssessment(): void
    {
        $admin = $this->buildAdminModel();
        $submissionId = (int) ($_POST['submission_id'] ?? 0);

        try {
            if (!$this->canReviewFundingSubmission()) {
                throw new \RuntimeException('You do not have permission to assess funding submissions.');
            }

              $admin->saveFundingSubmissionAssessment($submissionId, [
                'ReviewerRanking' => (string) ($_POST['ReviewerRanking'] ?? ''),
                'AssessmentGrade' => trim((string) ($_POST['AssessmentGrade'] ?? '')),
                'ReviewerRecommendation' => trim((string) ($_POST['ReviewerRecommendation'] ?? '')),
                'DecisionNote' => trim((string) ($_POST['DecisionNote'] ?? '')),
                'ReviewerSummary' => trim((string) ($_POST['ReviewerSummary'] ?? '')),
                'ReviewerConditions' => trim((string) ($_POST['ReviewerConditions'] ?? '')),
                'ReviewerNextSteps' => trim((string) ($_POST['ReviewerNextSteps'] ?? '')),
                  'UserID' => (int) SessionHelper::get('auth.user_id', 1),
              ]);
              $this->syncFundingSubmissionWorkflowTasks($submissionId, 'assessment');
              $this->flashSuccess('Submission assessment saved.');
          } catch (\Throwable $e) {
              $this->flashError('Submission assessment failed: ' . $e->getMessage());
          }

        header('Location: index.php?route=strategy-submissions/view&id=' . $submissionId);
        exit;
    }

    public function publishSectorCeilings(): void
    {
        $admin = $this->buildAdminModel();
        $submissionId = (int) ($_POST['submission_id'] ?? 0);

        try {
            if (!$this->canPublishFundingSubmission()) {
                throw new \RuntimeException('You do not have permission to publish approved funding submissions.');
            }

            $summary = $admin->publishFundingSubmissionToSectorCeilings($submissionId, (int) SessionHelper::get('auth.user_id', 1));
            $this->flashSuccess(
                'Approved bids published to sector ceilings. Created: '
                . (int) ($summary['created'] ?? 0)
                . '; Updated: '
                . (int) ($summary['updated'] ?? 0)
                . '; Lines linked: '
                . (int) ($summary['lines_linked'] ?? 0)
            );
        } catch (\Throwable $e) {
            $this->flashError('Publishing to sector ceilings failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-submissions/view&id=' . $submissionId);
        exit;
    }

    public function uploadAttachment(): void
    {
        $this->assertStrategicContextEditable('index.php?route=strategy-submissions/list');

        $admin = $this->buildAdminModel();
        $ctx = $this->context();
        $fy = (int) ($ctx['FiscalYearID'] ?? 0);
        $ver = (int) ($ctx['VersionID'] ?? 0);
        $submissionId = (int) ($_POST['StrategicFundingSubmissionID'] ?? 0);

        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new \RuntimeException('Method not allowed.');
            }

            if ($submissionId <= 0) {
                $submissionId = $admin->saveFundingSubmission([
                    'FiscalYearID' => $fy,
                    'VersionID' => $ver,
                    'DataObjectCode' => trim((string) ($_POST['DataObjectCode'] ?? '')),
                    'RequestTitle' => trim((string) ($_POST['RequestTitle'] ?? '')),
                    'RequestNotes' => trim((string) ($_POST['RequestNotes'] ?? '')),
                    'SubmissionTypeCode' => trim((string) ($_POST['SubmissionTypeCode'] ?? '')),
                    'PriorityCode' => trim((string) ($_POST['PriorityCode'] ?? '')),
                    'UserID' => (int) SessionHelper::get('auth.user_id', 1),
                ], null);
            }

            $uploadedCount = $this->storeUploadedSubmissionAttachments($admin, $submissionId);
            $attachments = $admin->listFundingSubmissionAttachments($submissionId);
            $this->jsonResponse([
                'ok' => true,
                'submission_id' => $submissionId,
                'uploaded_count' => $uploadedCount,
                'attachments_html' => $this->renderFundingSubmissionAttachmentList($attachments, $submissionId),
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function attachmentList(): void
    {
        $admin = $this->buildAdminModel();
        $submissionId = (int) ($_GET['submission_id'] ?? 0);
        $attachments = $admin->listFundingSubmissionAttachments($submissionId);
        echo $this->renderFundingSubmissionAttachmentList($attachments, $submissionId);
        exit;
    }

    public function downloadAttachment(): void
    {
        $admin = $this->buildAdminModel();
        $attachmentId = (int) ($_GET['id'] ?? 0);
        $attachment = $admin->getFundingSubmissionAttachment($attachmentId);

        if ($attachment === null || empty($attachment['StoragePath'])) {
            http_response_code(404);
            echo 'Attachment not found.';
            return;
        }

        $path = (string) $attachment['StoragePath'];
        if (!is_file($path) || !is_readable($path)) {
            http_response_code(404);
            echo 'Attachment file not found on disk.';
            return;
        }

        $mimeType = trim((string) ($attachment['MimeType'] ?? ''));
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string) ($attachment['OriginalFileName'] ?? 'attachment')) . '"');
        readfile($path);
        exit;
    }

    public function deleteAttachment(): void
    {
        $this->assertStrategicContextEditable('index.php?route=strategy-submissions/list');

        $admin = $this->buildAdminModel();
        $attachmentId = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        $submissionId = (int) ($_POST['submission_id'] ?? $_GET['submission_id'] ?? 0);

        try {
            $admin->deleteFundingSubmissionAttachment($attachmentId, (int) SessionHelper::get('auth.user_id', 1));
            if ($this->wantsJsonResponse()) {
                $attachments = $admin->listFundingSubmissionAttachments($submissionId);
                $this->jsonResponse([
                    'ok' => true,
                    'attachments_html' => $this->renderFundingSubmissionAttachmentList($attachments, $submissionId),
                ]);
            }
            $this->flashSuccess('Attachment removed.');
        } catch (\Throwable $e) {
            if ($this->wantsJsonResponse()) {
                $this->jsonResponse([
                    'ok' => false,
                    'error' => $e->getMessage(),
                ], 400);
            }
            $this->flashError('Attachment delete failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=strategy-submissions/form&id=' . $submissionId);
        exit;
    }

    private function syncFundingSubmissionWorkflowTasks(int $submissionId, string $triggerAction): void
    {
        if ($submissionId <= 0 || !$this->db instanceof \PDO) {
            return;
        }

        $admin = $this->buildAdminModel();
        $submission = $admin->getFundingSubmission($submissionId);
        if ($submission === null) {
            return;
        }

        $workflowTasks = new WorkflowTaskModel($this->db);
        $statusModel = new WorkflowTaskStatusModel($this->db);
        $typeModel = new WorkflowTaskTypeModel($this->db);

        $status = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
        $reviewTypeId = $typeModel->findIdByName('Review Task') ?? 3;
        $approvalTypeId = $typeModel->findIdByName('Approval Task') ?? 4;
        $openStatusId = $statusModel->findOpenStatusId() ?? 1;
        $completedStatusId = $statusModel->findCompletedStatusId() ?? 3;

        if ($status === 'PENDING' && in_array($triggerAction, ['submit', 'line_review', 'assessment'], true)) {
            $this->closeFundingSubmissionTasksByType($workflowTasks, $completedStatusId, $submissionId, $approvalTypeId);
            $assignees = $this->resolveFundingWorkflowAssignees(
                'REVIEW',
                $submission,
                ['STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
                [(int) ($submission['SubmittedBy'] ?? 0)]
            );
            $this->ensureFundingSubmissionTasks(
                $workflowTasks,
                $openStatusId,
                $reviewTypeId,
                $submission,
                $assignees,
                'Review Funding Request',
                'Review the submitted funding request and complete the line and header assessment.',
                'Open Funding Request for Review'
            );
            return;
        }

        if ($status === 'REVIEWED' && in_array($triggerAction, ['line_review', 'assessment', 'submit'], true)) {
            $this->closeFundingSubmissionTasksByType($workflowTasks, $completedStatusId, $submissionId, $reviewTypeId);
            $assignees = $this->resolveFundingWorkflowAssignees(
                'APPROVAL',
                $submission,
                ['STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
                [
                    (int) ($submission['SubmittedBy'] ?? 0),
                    (int) ($submission['ReviewedBy'] ?? 0),
                ]
            );
            $this->ensureFundingSubmissionTasks(
                $workflowTasks,
                $openStatusId,
                $approvalTypeId,
                $submission,
                $assignees,
                'Approve Funding Request',
                'Review the completed assessment and record the approval or rejection decision.',
                'Open Funding Request for Approval'
            );
            return;
        }

        if (in_array($status, ['APPROVED', 'PARTIAL', 'REJECTED', 'FUNDED'], true) || in_array($triggerAction, ['approve', 'reject', 'fund'], true)) {
            $this->closeFundingSubmissionTasksByType($workflowTasks, $completedStatusId, $submissionId, null);
        }
    }

    private function ensureFundingSubmissionTasks(
        WorkflowTaskModel $workflowTasks,
        int $openStatusId,
        int $taskTypeId,
        array $submission,
        array $assignees,
        string $taskVerb,
        string $descriptionLead,
        string $taskLinkLabel
    ): void {
        if ($assignees === []) {
            return;
        }

        $submissionId = (int) ($submission['StrategicFundingSubmissionID'] ?? 0);
        $titleBase = trim((string) ($submission['RequestTitle'] ?? 'Funding Request'));
        $scopeCode = trim((string) ($submission['DataObjectCode'] ?? ''));
        $scopeName = trim((string) ($submission['DataObjectName'] ?? $submission['OrgUnitName'] ?? ''));
        $fy = (int) ($submission['FiscalYearID'] ?? 0);
        $ver = (int) ($submission['VersionID'] ?? 0);
        $appUrl = $this->getAppBaseUrl();
        $taskUrl = $appUrl . '/backend-php/public/index.php?' . http_build_query(array_filter([
            'route' => 'strategy-submissions/view',
            'id' => $submissionId,
            'link_context' => 1,
            'scope_dataobject_code' => $scopeCode !== '' ? $scopeCode : null,
            'scope_dataobject_name' => $scopeName !== '' ? $scopeName : null,
            'fy' => $fy > 0 ? $fy : null,
            'ver' => $ver > 0 ? $ver : null,
        ], static fn($v) => $v !== null && $v !== ''));

        foreach ($assignees as $assignee) {
            $assigneeId = (int) ($assignee['UserID'] ?? 0);
            if ($assigneeId <= 0) {
                continue;
            }

            $existing = $workflowTasks->findOpenByRelatedEntityKeyAndAssignee('StrategicFundingSubmission', (string) $submissionId, $assigneeId);
            $data = [
                'TaskTypeID' => $taskTypeId,
                'StatusID' => $openStatusId,
                'Title' => $taskVerb . ': ' . $titleBase,
                'Description' => $descriptionLead
                    . "\n\nFunding Request: " . $titleBase
                    . ($scopeCode !== '' ? "\nDataScope: " . $scopeCode . ($scopeName !== '' ? ' - ' . $scopeName : '') : ''),
                'CreatedByUserID' => (int) SessionHelper::get('auth.user_id', 1),
                'AssignedToUserID' => $assigneeId,
                'RelatedEntity' => 'StrategicFundingSubmission',
                'RelatedKey' => (string) $submissionId,
                'DueDate' => date('Y-m-d', strtotime('+3 days')),
                'CompletedAt' => null,
                'UpdatedBy' => (int) SessionHelper::get('auth.user_id', 1),
                'TaskUrl' => $taskUrl,
                'TaskLinkLabel' => $taskLinkLabel,
            ];

            if ($existing !== null) {
                $workflowTasks->update((int) ($existing['WorkflowTaskID'] ?? 0), $data);
                continue;
            }

            $workflowTasks->create($data);
            $this->notifyFundingWorkflowTaskAssignee($assignee, $data);
        }
    }

    private function closeFundingSubmissionTasksByType(
        WorkflowTaskModel $workflowTasks,
        int $completedStatusId,
        int $submissionId,
        ?int $taskTypeId
    ): void {
        $openTasks = $workflowTasks->listOpenByRelatedEntityKey('StrategicFundingSubmission', (string) $submissionId);
        foreach ($openTasks as $task) {
            if ($taskTypeId !== null && (int) ($task['TaskTypeID'] ?? 0) !== $taskTypeId) {
                continue;
            }
            $workflowTasks->updateStatus(
                (int) ($task['WorkflowTaskID'] ?? 0),
                $completedStatusId,
                gmdate('Y-m-d H:i:s'),
                (int) SessionHelper::get('auth.user_id', 1)
            );
        }
    }

    private function listActiveUsersByPermissions(array $permissionCodes, array $excludeUserIds = []): array
    {
        if (!$this->db instanceof \PDO) {
            return [];
        }

        $permissionCodes = array_values(array_unique(array_filter(array_map(
            static fn($v) => strtoupper(trim((string) $v)),
            $permissionCodes
        ))));
        if ($permissionCodes === []) {
            return [];
        }

        $excludeLookup = [];
        foreach ($excludeUserIds as $excludeUserId) {
            $excludeId = (int) $excludeUserId;
            if ($excludeId > 0) {
                $excludeLookup[$excludeId] = true;
            }
        }

        $permPlaceholders = [];
        $params = [];
        foreach ($permissionCodes as $index => $code) {
            $placeholder = ':perm' . $index;
            $permPlaceholders[] = $placeholder;
            $params[$placeholder] = $code;
        }

        $sql = "
            SELECT DISTINCT
                u.UserID,
                u.Username,
                LTRIM(RTRIM(u.DisplayName)) AS DisplayName,
                u.Email
            FROM dbo.tblUsers u
            INNER JOIN dbo.tblUserRoles ur
                ON ur.UserID = u.UserID
            INNER JOIN dbo.tblRolePermissions rp
                ON rp.RoleID = ur.RoleID
            INNER JOIN dbo.tblPermissions p
                ON p.PermissionID = rp.PermissionID
            INNER JOIN dbo.tblRoles r
                ON r.RoleID = ur.RoleID
            WHERE u.IsActive = 1
              AND (r.Active = 1 OR r.Active IS NULL)
              AND (p.Active = 1 OR p.Active IS NULL)
              AND UPPER(p.PermissionCode) IN (" . implode(', ', $permPlaceholders) . ")
            ORDER BY LTRIM(RTRIM(u.DisplayName)) ASC, u.Username ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if ($excludeLookup === []) {
            return $rows;
        }

        return array_values(array_filter($rows, static function (array $row) use ($excludeLookup): bool {
            $userId = (int) ($row['UserID'] ?? 0);
            return $userId > 0 && !isset($excludeLookup[$userId]);
        }));
    }

    private function resolveFundingWorkflowAssignees(string $stageCode, array $submission, array $fallbackPermissions, array $excludeUserIds = []): array
    {
        if ($this->db instanceof \PDO) {
            $assignmentModel = new WorkflowAssignmentModel($this->db);
            if ($assignmentModel->supportsWorkflowAssignments()) {
                $assigned = $assignmentModel->resolveAssignments(
                    'FUNDING_REQUEST',
                    strtoupper(trim($stageCode)),
                    (int) ($submission['FiscalYearID'] ?? 0),
                    (int) ($submission['VersionID'] ?? 0),
                    (string) ($submission['DataObjectCode'] ?? '')
                );
                if ($assigned !== []) {
                    $excludeLookup = [];
                    foreach ($excludeUserIds as $excludeUserId) {
                        $excludeId = (int) $excludeUserId;
                        if ($excludeId > 0) {
                            $excludeLookup[$excludeId] = true;
                        }
                    }
                    return array_values(array_filter($assigned, static function (array $row) use ($excludeLookup): bool {
                        $userId = (int) ($row['UserID'] ?? 0);
                        return $userId > 0 && !isset($excludeLookup[$userId]);
                    }));
                }
            }
        }

        return $this->listActiveUsersByPermissions($fallbackPermissions, $excludeUserIds);
    }

    private function notifyFundingWorkflowTaskAssignee(array $assignee, array $taskData): void
    {
        if (!$this->db instanceof \PDO) {
            return;
        }

        $email = trim((string) ($assignee['Email'] ?? ''));
        if ($email === '') {
            return;
        }

        $settings = new SystemSettingsModel($this->db);
        $subject = 'CBMS workflow task: ' . (string) ($taskData['Title'] ?? 'Funding Request');
        $body = '
            <p>Dear ' . htmlspecialchars((string) ($assignee['DisplayName'] ?? $assignee['Username'] ?? 'User')) . ',</p>
            <p>A workflow task has been assigned to you in CBMS.</p>
            <ul>
              <li><strong>Task:</strong> ' . htmlspecialchars((string) ($taskData['Title'] ?? '')) . '</li>
              <li><strong>Due Date:</strong> ' . htmlspecialchars((string) ($taskData['DueDate'] ?? '-')) . '</li>
            </ul>
            <p>' . nl2br(htmlspecialchars((string) ($taskData['Description'] ?? ''))) . '</p>
            <p><a href="' . htmlspecialchars((string) ($taskData['TaskUrl'] ?? '')) . '">' . htmlspecialchars((string) ($taskData['TaskLinkLabel'] ?? 'Open Task in CBMS')) . '</a></p>
        ';

        try {
            $mailer = new MailService($this->db);
            $from = trim((string) $settings->get('EMAIL_ERROR_FROM', ''));
            $mailer->sendEmail(
                $email,
                $subject,
                $body,
                $from !== '' ? $from : null
            );
        } catch (\Throwable $e) {
            app_log('Funding workflow task email failed', [
                'userId' => (int) ($assignee['UserID'] ?? 0),
                'email' => $email,
                'error' => $e->getMessage(),
            ], 'error');
        }
    }

    private function getAppBaseUrl(): string
    {
        if (!$this->db instanceof \PDO) {
            return 'http://localhost/CBMSv21';
        }
        $settings = new SystemSettingsModel($this->db);
        return rtrim($settings->get('APP_URL', 'http://localhost/CBMSv21'), '/');
    }

    private function buildAdminModel(): StrategicBudgetingAdminModel
    {
        return new StrategicBudgetingAdminModel($this->db);
    }

    private function buildReportModel(): StrategicBudgetingModel
    {
        return new StrategicBudgetingModel($this->db);
    }

    private function resolveEffectiveFundingSubmissionScope(
        StrategicBudgetingAdminModel $admin,
        int $fiscalYearId,
        string $submissionDataObjectCode,
        string $currentScopeDataObjectCode
    ): string {
        $effectiveDataObjectCode = $submissionDataObjectCode;
        if ($currentScopeDataObjectCode !== ''
            && $submissionDataObjectCode !== ''
            && $admin->dataObjectCodeFallsWithinScope($fiscalYearId, $currentScopeDataObjectCode, $submissionDataObjectCode)
        ) {
            $effectiveDataObjectCode = $currentScopeDataObjectCode;
        }

        return $effectiveDataObjectCode;
    }

    private function fundingItemSegmentDefaultsSessionKey(string $effectiveDataObjectCode): string
    {
        $scopePart = preg_replace('/[^A-Za-z0-9_-]/', '_', trim($effectiveDataObjectCode));
        if ($scopePart === null || $scopePart === '') {
            $scopePart = 'global';
        }

        return 'funding_submission.line_defaults.' . $scopePart;
    }

    private function canPrepareFundingSubmission(): bool
    {
        return \App\Core\Rbac::canAny(self::PREPARER_PERMS);
    }

    private function canReviewFundingSubmission(): bool
    {
        return \App\Core\Rbac::canAny(self::REVIEWER_PERMS);
    }

    private function canApproveFundingSubmission(): bool
    {
        return \App\Core\Rbac::canAny(self::APPROVER_PERMS);
    }

    private function canPublishFundingSubmission(): bool
    {
        return \App\Core\Rbac::canAny(self::PUBLISHER_PERMS);
    }

    private function filterFundingSubmissionsForMode(array $submissions, string $mode, int $currentUserId = 0): array
    {
        if ($mode === 'lodgements') {
            return array_values(array_filter($submissions, static function (array $row) use ($currentUserId): bool {
                $createdBy = (int) ($row['CreatedBy'] ?? 0);
                $submittedBy = (int) ($row['SubmittedBy'] ?? 0);
                return $currentUserId > 0 && ($createdBy === $currentUserId || $submittedBy === $currentUserId);
            }));
        }

        $statusMap = [
            'reviews' => ['PENDING', 'REVIEWED'],
            'approvals' => ['REVIEWED', 'APPROVED', 'PARTIAL', 'FUNDED'],
        ];

        if (!isset($statusMap[$mode])) {
            return $submissions;
        }

        $allowed = $statusMap[$mode];
        return array_values(array_filter($submissions, static function (array $row) use ($allowed): bool {
            $status = strtoupper(trim((string) ($row['SubmissionStatusCode'] ?? 'DRAFT')));
            return in_array($status, $allowed, true);
        }));
    }

    private function storeUploadedSubmissionAttachments(StrategicBudgetingAdminModel $admin, int $submissionId): int
    {
        if ($submissionId <= 0 || !isset($_FILES['SubmissionAttachments'])) {
            return 0;
        }

        $names = $_FILES['SubmissionAttachments']['name'] ?? [];
        $tmpNames = $_FILES['SubmissionAttachments']['tmp_name'] ?? [];
        $errors = $_FILES['SubmissionAttachments']['error'] ?? [];
        $sizes = $_FILES['SubmissionAttachments']['size'] ?? [];

        if (!is_array($names) || !is_array($tmpNames) || !is_array($errors) || !is_array($sizes)) {
            return 0;
        }

        $uploadDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'funding-submission-attachments' . DIRECTORY_SEPARATOR . $submissionId;
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Attachment storage directory could not be created.');
        }

        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'png', 'jpg', 'jpeg'];
        $maxBytes = 10 * 1024 * 1024;
        $uploadedCount = 0;

        foreach ($names as $index => $originalNameRaw) {
            $originalName = trim((string) $originalNameRaw);
            $errorCode = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
            if ($errorCode === UPLOAD_ERR_NO_FILE || $originalName === '') {
                continue;
            }
            if ($errorCode !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('One of the attachments failed to upload.');
            }

            $tmpPath = (string) ($tmpNames[$index] ?? '');
            $size = (int) ($sizes[$index] ?? 0);
            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                throw new \RuntimeException('Uploaded attachment payload is invalid.');
            }
            if ($size <= 0) {
                throw new \RuntimeException('Attachment "' . $originalName . '" is empty.');
            }
            if ($size > $maxBytes) {
                throw new \RuntimeException('Attachment "' . $originalName . '" exceeds the 10 MB limit.');
            }

            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
                throw new \RuntimeException('Attachment "' . $originalName . '" has a file type that is not allowed.');
            }

            $storedFileName = bin2hex(random_bytes(16)) . ($extension !== '' ? '.' . $extension : '');
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $storedFileName;
            if (!move_uploaded_file($tmpPath, $targetPath)) {
                throw new \RuntimeException('Attachment "' . $originalName . '" could not be stored.');
            }

            $mimeType = null;
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $detected = finfo_file($finfo, $targetPath);
                    if (is_string($detected) && $detected !== '') {
                        $mimeType = $detected;
                    }
                    finfo_close($finfo);
                }
            }

            $admin->saveFundingSubmissionAttachment($submissionId, [
                'OriginalFileName' => $originalName,
                'StoredFileName' => $storedFileName,
                'MimeType' => $mimeType,
                'FileSizeBytes' => $size,
                'StoragePath' => $targetPath,
                'UserID' => (int) SessionHelper::get('auth.user_id', 1),
            ]);
            $uploadedCount++;
        }

        return $uploadedCount;
    }

    private function jsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function wantsJsonResponse(): bool
    {
        $accept = strtolower(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')));
        $requestedWith = strtolower(trim((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
    }

    private function renderFundingSubmissionAttachmentList(array $attachments, int $submissionId): string
    {
        ob_start();
        $attachmentsForPartial = $attachments;
        $submissionIdForPartial = $submissionId;
        $attachments = $attachmentsForPartial;
        $submissionId = $submissionIdForPartial;
        require __DIR__ . '/../Views/strategy/_FundingSubmissionAttachmentList.php';
        return (string) ob_get_clean();
    }
}
