<?php
declare(strict_types=1);
/** @var array $submission */
/** @var array $lines */
/** @var array $history */
/** @var bool $canPrepareSubmission */
/** @var bool $canReviewSubmission */
/** @var bool $canApproveSubmission */
/** @var bool $canPublishSubmission */
/** @var bool $supportsStrategicCeilings */
/** @var bool $attachmentsInstalled */
/** @var bool $reviewResponseFieldsInstalled */
/** @var bool $assessmentFieldsInstalled */
/** @var array $attachments */
/** @var array $assessmentGradeOptions */
/** @var array $reviewerRecommendationOptions */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('funding_submission_status_label')) {
    function funding_submission_status_label(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'DRAFT' => 'Draft Lodgement',
            'LODGED' => 'Lodged',
            'PENDING' => 'Submitted for Review',
            'REVIEWED' => 'Reviewed - Awaiting Approval',
            'APPROVED' => 'Approved',
            'PARTIAL' => 'Partially Approved',
            'REJECTED' => 'Rejected',
            'FUNDED' => 'Funded / Published',
            default => $status,
        };
    }
}
if (!function_exists('funding_submission_status_badge_class')) {
    function funding_submission_status_badge_class(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'APPROVED', 'FUNDED' => 'text-bg-success',
            'REVIEWED' => 'text-bg-info',
            'PENDING', 'LODGED', 'PARTIAL' => 'text-bg-warning',
            'REJECTED' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }
}
$status = strtoupper(trim((string) ($submission['SubmissionStatusCode'] ?? 'DRAFT')));
$canPrepareSubmission = !empty($canPrepareSubmission);
$canReviewSubmission = !empty($canReviewSubmission);
$canApproveSubmission = !empty($canApproveSubmission);
$canPublishSubmission = !empty($canPublishSubmission);
$reviewResponseFieldsInstalled = !empty($reviewResponseFieldsInstalled);
$assessmentFieldsInstalled = !empty($assessmentFieldsInstalled);
$attachments = is_array($attachments ?? null) ? $attachments : [];
$assessmentGradeOptions = is_array($assessmentGradeOptions ?? null) ? $assessmentGradeOptions : [];
$reviewerRecommendationOptions = is_array($reviewerRecommendationOptions ?? null) ? $reviewerRecommendationOptions : [];
$dataScopeWorkflowStatus = strtoupper(trim((string) ($submission['DataObjectWorkflowStatus'] ?? '')));
$totalRequestedAmount = 0.0;
$totalApprovedAmount = 0.0;
foreach ($lines as $lineForTotals) {
    $totalRequestedAmount += (float) ($lineForTotals['CurrentYearRequestedAmount'] ?? 0);
    $totalApprovedAmount += (float) ($lineForTotals['CurrentYearApprovedAmount'] ?? 0);
}
?>
<div class="container-fluid py-3">
  <style>
    .container-fluid.py-3 {
      font-size: .95rem;
    }
    .submission-shell {
      background: linear-gradient(180deg, #f7fafc 0%, #ffffff 100%);
    }
    .submission-hero {
      background: linear-gradient(135deg, #f3f9ff 0%, #ffffff 100%);
      border: 1px solid #dce8f3;
      border-radius: 1.25rem;
      padding: 1.35rem;
      box-shadow: 0 .5rem 1.5rem rgba(43, 63, 87, 0.06);
    }
    .submission-eyebrow {
      font-size: .72rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #6c757d;
      font-weight: 700;
      margin-bottom: .65rem;
    }
    .submission-chip {
      display: inline-flex;
      align-items: center;
      gap: .45rem;
      padding: .45rem .8rem;
      border-radius: 999px;
      background: #fff;
      border: 1px solid #d9e5f0;
      color: #445566;
      font-size: .84rem;
      font-weight: 600;
    }
    .submission-summary-card {
      background: #fff;
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
      height: 100%;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .submission-summary-label {
      font-size: .7rem;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #7a8796;
      font-weight: 700;
      margin-bottom: .35rem;
    }
    .submission-summary-value {
      font-size: .95rem;
      font-weight: 700;
      color: #203040;
    }
    .submission-notes-panel {
      background: #fff;
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
    }
    .submission-title {
      font-size: 1.55rem;
      line-height: 1.2;
      letter-spacing: -.02em;
    }
    .submission-subtext {
      font-size: .9rem;
      max-width: 58rem;
    }
    .assessment-shell {
      background: linear-gradient(180deg, #f9fbfd 0%, #ffffff 100%);
      border: 1px solid #dfe9f2;
      border-radius: 1.15rem;
      overflow: hidden;
    }
    .assessment-header {
      background: linear-gradient(135deg, #eff6fb 0%, #f9fbfd 100%);
      border-bottom: 1px solid #dfe9f2;
      padding: 1.05rem 1.15rem;
    }
    .assessment-stat {
      background: #fff;
      border: 1px solid #e3ebf2;
      border-radius: .95rem;
      padding: 1.05rem 1.1rem;
      height: 100%;
      box-shadow: 0 .3rem .9rem rgba(43, 63, 87, 0.05);
    }
    .assessment-copy {
      background: #fff;
      border: 1px solid #e3ebf2;
      border-radius: .95rem;
      padding: 1.05rem 1.1rem;
      height: 100%;
    }
    .assessment-form-panel {
      background: #fff;
      border: 1px solid #dfe7ef;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
    }
    .submission-card {
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .submission-card .card-header {
      padding: 1.05rem 1.15rem;
      background: #fff;
      border-bottom: 1px solid #e4ebf2;
    }
    .submission-card .card-body {
      padding: 0;
    }
    .submission-table thead th {
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6f7f90;
    }
    .submission-table td,
    .submission-table th {
      font-size: .9rem;
      padding: .85rem 1rem;
    }
    .submission-score-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: .35rem .75rem;
      margin-top: .6rem;
    }
    .submission-score-item {
      font-size: .78rem;
      color: #5f7083;
    }
    .submission-score-item strong {
      color: #203040;
      font-weight: 700;
    }
    .history-shell {
      background: #fff;
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .history-shell .card-header {
      padding: 1.05rem 1.15rem;
      background: #fff;
      border-bottom: 1px solid #e4ebf2;
    }
    .history-table thead th {
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6f7f90;
    }
    .history-table td,
    .history-table th {
      font-size: .85rem;
      vertical-align: top;
      padding: .8rem .95rem;
    }
    .history-note {
      max-width: 24rem;
      white-space: normal;
      word-break: break-word;
    }
  </style>

  <div class="submission-shell rounded-4 p-1 mb-3">
    <div class="submission-hero">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
        <div class="pe-xl-4">
          <div class="submission-eyebrow">Funding Submission Header</div>
          <h1 class="submission-title mb-2"><?= h((string) ($submission['RequestTitle'] ?? 'Funding Submission')) ?></h1>
          <div class="text-muted submission-subtext">
            Funding request owned by <?= h((string) ($submission['OrgUnitName'] ?? $submission['DataObjectName'] ?? $submission['DataObjectCode'] ?? 'Unassigned scope')) ?> and moving through the formal lodgement, review, and approval workflow.
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
          <span class="submission-chip">Workflow: <span class="badge <?= h(funding_submission_status_badge_class($status)) ?>"><?= h(funding_submission_status_label($status)) ?></span></span>
          <?php if (!empty($submission['DataObjectCode'])): ?>
            <span class="submission-chip">System DataScope: <?= h((string) ($submission['DataObjectCode'] ?? '')) ?></span>
          <?php endif; ?>
          <?php if (!empty($submission['DataObjectWorkflowStatus'])): ?>
            <span class="submission-chip">Scope Status: <?= h((string) ($submission['DataObjectWorkflowStatus'] ?? '')) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex gap-2 flex-wrap justify-content-end mb-3 submission-action-row">
      <a href="index.php?route=strategy-submissions/list" class="btn btn-outline-secondary">Back</a>
      <?php if ($canPrepareSubmission && in_array($status, ['DRAFT', 'REJECTED'], true)): ?>
        <a href="index.php?route=strategy-submissions/form&id=<?= (int) $submission['StrategicFundingSubmissionID'] ?>" class="btn btn-outline-primary">Edit Header</a>
        <a href="index.php?route=strategy-submissions/line-form&submission_id=<?= (int) $submission['StrategicFundingSubmissionID'] ?>" class="btn btn-primary">Add Funding Item</a>
        <form method="post" action="index.php?route=strategy-submissions/transition" class="d-inline">
          <input type="hidden" name="submission_id" value="<?= (int) $submission['StrategicFundingSubmissionID'] ?>">
          <input type="hidden" name="submission_action" value="lodge">
          <button type="submit" class="btn btn-success">Lodge Submission</button>
        </form>
      <?php endif; ?>
      <?php if ($canPrepareSubmission && $status === 'LODGED'): ?>
        <form method="post" action="index.php?route=strategy-submissions/transition" class="d-inline">
          <input type="hidden" name="submission_id" value="<?= (int) $submission['StrategicFundingSubmissionID'] ?>">
          <input type="hidden" name="submission_action" value="submit">
          <button type="submit" class="btn btn-success">Submit for Review</button>
        </form>
      <?php endif; ?>
      <?php if ($status === 'REVIEWED' && $canApproveSubmission): ?>
        <form method="post" action="index.php?route=strategy-submissions/transition" class="d-inline">
          <input type="hidden" name="submission_id" value="<?= (int) $submission['StrategicFundingSubmissionID'] ?>">
          <input type="hidden" name="submission_action" value="approve">
          <button type="submit" class="btn btn-success">Approve</button>
        </form>
        <form method="post" action="index.php?route=strategy-submissions/transition" class="d-inline">
          <input type="hidden" name="submission_id" value="<?= (int) $submission['StrategicFundingSubmissionID'] ?>">
          <input type="hidden" name="submission_action" value="reject">
          <button type="submit" class="btn btn-outline-danger">Reject</button>
        </form>
      <?php endif; ?>
      <?php if (in_array($status, ['APPROVED', 'PARTIAL'], true) && $canApproveSubmission): ?>
        <form method="post" action="index.php?route=strategy-submissions/transition" class="d-inline">
          <input type="hidden" name="submission_id" value="<?= (int) $submission['StrategicFundingSubmissionID'] ?>">
          <input type="hidden" name="submission_action" value="fund">
          <button type="submit" class="btn btn-success">Mark Funded</button>
        </form>
      <?php endif; ?>
      <?php if (!empty($supportsStrategicCeilings) && in_array($status, ['APPROVED', 'PARTIAL', 'FUNDED'], true) && $canPublishSubmission): ?>
        <form method="post" action="index.php?route=strategy-submissions/publish-sector-ceilings" class="d-inline">
          <input type="hidden" name="submission_id" value="<?= (int) $submission['StrategicFundingSubmissionID'] ?>">
          <button type="submit" class="btn btn-outline-success">Publish to Sector Ceilings</button>
        </form>
      <?php endif; ?>
      </div>

      <div class="row g-3">
        <div class="col-md-6 col-xl-3">
          <div class="submission-summary-card">
            <div class="submission-summary-label">System DataScope</div>
            <div class="submission-summary-value"><?= h((string) ($submission['DataObjectCode'] ?? '')) ?></div>
            <?php if (!empty($submission['DataObjectName'])): ?>
              <div class="text-muted small mt-1"><?= h((string) ($submission['DataObjectName'] ?? '')) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="submission-summary-card">
            <div class="submission-summary-label">Submission Type</div>
            <div class="submission-summary-value"><?= h((string) ($submission['SubmissionTypeCode'] ?? '')) ?></div>
          </div>
        </div>
        <div class="col-md-6 col-xl-2">
          <div class="submission-summary-card">
            <div class="submission-summary-label">Priority</div>
            <div class="submission-summary-value"><?= h((string) ($submission['PriorityCode'] ?? '')) ?></div>
          </div>
        </div>
        <div class="col-md-6 col-xl-2">
          <div class="submission-summary-card">
            <div class="submission-summary-label">Requested</div>
            <div class="submission-summary-value"><?= h(number_format((float) ($submission['TotalRequestedAmount'] ?? 0), 0)) ?></div>
          </div>
        </div>
        <div class="col-md-6 col-xl-2">
          <div class="submission-summary-card">
            <div class="submission-summary-label">Approved</div>
            <div class="submission-summary-value"><?= h(number_format((float) ($submission['TotalApprovedAmount'] ?? 0), 0)) ?></div>
          </div>
        </div>
      </div>

      <?php if (!empty($submission['RequestNotes'])): ?>
        <div class="submission-notes-panel mt-3">
          <div class="submission-summary-label">Submission Narrative</div>
          <div><?= nl2br(h((string) $submission['RequestNotes'])) ?></div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($attachmentsInstalled)): ?>
    <div class="alert alert-warning">Run the updated <code>create_tblSbFundingSubmission.sql</code> script to enable funding submission attachments.</div>
  <?php endif; ?>
  <?php if (in_array($status, ['DRAFT', 'REJECTED'], true) && $dataScopeWorkflowStatus !== 'OPEN'): ?>
    <div class="alert alert-warning">This lodgement cannot move forward until the linked DataScope status is <strong>Open</strong>.</div>
  <?php endif; ?>
  <?php if ($status === 'LODGED'): ?>
    <div class="alert alert-info">This request has been lodged and is waiting for formal submission into the review workflow.</div>
  <?php elseif ($status === 'PENDING'): ?>
    <div class="alert alert-info">This submission is in review. Reviewers can assess funding items and complete the header assessment before it moves to final approval.</div>
  <?php elseif ($status === 'REVIEWED'): ?>
    <div class="alert alert-info">Review is complete and the submission is now waiting for an approver who is different from both the submitter and reviewer.</div>
  <?php endif; ?>

  <div class="card shadow-sm submission-card mb-3">
    <div class="card-header"><h5 class="mb-0">Funding Items</h5></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 submission-table">
          <thead class="table-light">
            <tr>
              <th>Bid</th>
              <th>Sector / Program</th>
              <th>Funding</th>
              <th class="text-end">Requested</th>
              <th class="text-end">Approved</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($lines === []): ?>
              <tr><td colspan="7" class="text-center text-muted py-4">No funding items added yet.</td></tr>
            <?php else: ?>
              <?php foreach ($lines as $line): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($line['BidTitle'] ?? '')) ?></div>
                    <?php if (!empty($line['BusinessCaseSummary'])): ?>
                      <div class="small text-muted"><?= h((string) $line['BusinessCaseSummary']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div><?= h((string) ($line['SectorName'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($line['ProgramName'] ?? '')) ?></div>
                  </td>
                  <td>
                    <div><?= h((string) ($line['FundingTypeName'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($line['FundingSourceName'] ?? '')) ?></div>
                  </td>
                  <td class="text-end"><?= h(number_format((float) ($line['CurrentYearRequestedAmount'] ?? 0), 0)) ?></td>
                  <td class="text-end">
                    <?= h(number_format((float) ($line['CurrentYearApprovedAmount'] ?? 0), 0)) ?>
                    <?php if (!empty($line['PublishedCeilingID'])): ?>
                      <div class="small text-success">Ceiling #<?= (int) $line['PublishedCeilingID'] ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge <?= h(funding_submission_status_badge_class((string) ($line['LineStatusCode'] ?? 'DRAFT'))) ?>"><?= h(funding_submission_status_label((string) ($line['LineStatusCode'] ?? 'DRAFT'))) ?></span>
                    <?php if (!empty($line['DecisionNote'])): ?>
                      <div class="small text-muted mt-1"><?= h((string) ($line['DecisionNote'] ?? '')) ?></div>
                    <?php endif; ?>
                    <?php if ($reviewResponseFieldsInstalled && !empty($line['ReviewerResponse'])): ?>
                      <div class="small mt-2"><strong>Response:</strong> <?= h((string) ($line['ReviewerResponse'] ?? '')) ?></div>
                    <?php endif; ?>
                    <?php if ($reviewResponseFieldsInstalled && !empty($line['ReviewerConditions'])): ?>
                      <div class="small text-muted mt-1"><strong>Conditions:</strong> <?= h((string) ($line['ReviewerConditions'] ?? '')) ?></div>
                    <?php endif; ?>
                    <?php if ($reviewResponseFieldsInstalled && !empty($line['ReviewerNextSteps'])): ?>
                      <div class="small text-muted mt-1"><strong>Next Steps:</strong> <?= h((string) ($line['ReviewerNextSteps'] ?? '')) ?></div>
                    <?php endif; ?>
                    <?php if (
                        ($line['ScoreStrategicAlignment'] ?? null) !== null
                        || ($line['ScoreReadiness'] ?? null) !== null
                        || ($line['ScoreFiscalAffordability'] ?? null) !== null
                        || ($line['ScoreServiceImpact'] ?? null) !== null
                    ): ?>
                      <div class="submission-score-grid">
                        <div class="submission-score-item"><strong>Alignment:</strong> <?= h((string) ($line['ScoreStrategicAlignment'] ?? '-')) ?></div>
                        <div class="submission-score-item"><strong>Readiness:</strong> <?= h((string) ($line['ScoreReadiness'] ?? '-')) ?></div>
                        <div class="submission-score-item"><strong>Affordability:</strong> <?= h((string) ($line['ScoreFiscalAffordability'] ?? '-')) ?></div>
                        <div class="submission-score-item"><strong>Impact:</strong> <?= h((string) ($line['ScoreServiceImpact'] ?? '-')) ?></div>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php if ($canPrepareSubmission && in_array($status, ['DRAFT', 'REJECTED'], true)): ?>
                      <div class="btn-group btn-group-sm">
                        <a href="index.php?route=strategy-submissions/line-form&submission_id=<?= (int) $submission['StrategicFundingSubmissionID'] ?>&id=<?= (int) $line['StrategicFundingSubmissionLineID'] ?>" class="btn btn-outline-primary">Edit</a>
                        <form method="post" action="index.php?route=strategy-submissions/delete-line" onsubmit="return confirm('Remove this funding item?');">
                          <input type="hidden" name="id" value="<?= (int) $line['StrategicFundingSubmissionLineID'] ?>">
                          <input type="hidden" name="submission_id" value="<?= (int) $submission['StrategicFundingSubmissionID'] ?>">
                          <button type="submit" class="btn btn-outline-danger">Delete</button>
                        </form>
                      </div>
                    <?php elseif ($canReviewSubmission && in_array($status, ['PENDING', 'REVIEWED'], true)): ?>
                      <a href="index.php?route=strategy-submissions/review-line&submission_id=<?= (int) $submission['StrategicFundingSubmissionID'] ?>&id=<?= (int) $line['StrategicFundingSubmissionLineID'] ?>" class="btn btn-sm btn-outline-primary">Review</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr class="table-light fw-semibold">
                <td colspan="3">Total</td>
                <td class="text-end"><?= h(number_format($totalRequestedAmount, 0)) ?></td>
                <td class="text-end"><?= h(number_format($totalApprovedAmount, 0)) ?></td>
                <td></td>
                <td></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="card shadow-sm submission-card mb-3">
    <div class="card-header"><h5 class="mb-0">Attachments</h5></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 submission-table">
          <thead class="table-light">
            <tr>
              <th>File</th>
              <th class="text-end">Size</th>
              <th>Uploaded</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($attachments === []): ?>
              <tr><td colspan="4" class="text-center text-muted py-3">No attachments uploaded.</td></tr>
            <?php else: ?>
              <?php foreach ($attachments as $attachment): ?>
                <tr>
                  <td><?= h((string) ($attachment['OriginalFileName'] ?? '')) ?></td>
                  <td class="text-end"><?= h(number_format(((int) ($attachment['FileSizeBytes'] ?? 0)) / 1024, 1)) ?> KB</td>
                  <td><?= h((string) ($attachment['CreatedDate'] ?? '')) ?></td>
                  <td class="text-end">
                    <a href="index.php?route=strategy-submissions/download-attachment&id=<?= (int) ($attachment['StrategicFundingSubmissionAttachmentID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">Download</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="assessment-shell shadow-sm mb-3">
    <div class="assessment-header">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
          <div class="submission-eyebrow mb-1">Reviewer Assessment</div>
          <h5 class="mb-1">Submission Assessment</h5>
          <div class="text-muted small">Capture the overall reviewer view of the package before it moves to final approval.</div>
        </div>
        <?php if (!empty($submission['AssessmentGrade']) || !empty($submission['ReviewerRecommendation'])): ?>
          <div class="submission-chip">Assessment Recorded</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body p-3 p-lg-4">
      <?php if (!$assessmentFieldsInstalled): ?>
        <div class="alert alert-warning mb-0">Run the updated <code>create_tblSbFundingSubmission.sql</code> script to enable submission assessment fields.</div>
      <?php else: ?>
        <div class="row g-3 mb-3">
          <div class="col-md-4"><div class="assessment-stat"><div class="submission-summary-label">Assessment Grade</div><div class="submission-summary-value"><?= !empty($submission['AssessmentGrade']) ? h((string) $submission['AssessmentGrade']) : 'Not set' ?></div></div></div>
          <div class="col-md-4"><div class="assessment-stat"><div class="submission-summary-label">Reviewer Recommendation</div><div class="submission-summary-value"><?= !empty($submission['ReviewerRecommendation']) ? h((string) $submission['ReviewerRecommendation']) : 'Not set' ?></div></div></div>
          <div class="col-md-4"><div class="assessment-stat"><div class="submission-summary-label">Reviewer Ranking</div><div class="submission-summary-value"><?= !empty($submission['ReviewerRanking']) ? h((string) $submission['ReviewerRanking']) : 'Not set' ?></div></div></div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-lg-4">
            <div class="assessment-copy">
              <div class="submission-summary-label">Overall Decision Note</div>
              <div><?= !empty($submission['DecisionNote']) ? nl2br(h((string) $submission['DecisionNote'])) : '<span class="text-muted">Not set</span>' ?></div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="assessment-copy">
              <div class="submission-summary-label">Reviewer Summary</div>
              <div><?= !empty($submission['ReviewerSummary']) ? nl2br(h((string) $submission['ReviewerSummary'])) : '<span class="text-muted">Not set</span>' ?></div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="assessment-copy">
              <div class="submission-summary-label">Conditions / Required Changes</div>
              <div><?= !empty($submission['ReviewerConditions']) ? nl2br(h((string) $submission['ReviewerConditions'])) : '<span class="text-muted">Not set</span>' ?></div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="assessment-copy">
              <div class="submission-summary-label">Next Steps / Follow-up</div>
              <div><?= !empty($submission['ReviewerNextSteps']) ? nl2br(h((string) $submission['ReviewerNextSteps'])) : '<span class="text-muted">Not set</span>' ?></div>
            </div>
          </div>
        </div>

        <?php if ($canReviewSubmission && in_array($status, ['PENDING', 'REVIEWED'], true)): ?>
          <div class="assessment-form-panel">
            <form method="post" action="index.php?route=strategy-submissions/save-assessment">
              <input type="hidden" name="submission_id" value="<?= (int) $submission['StrategicFundingSubmissionID'] ?>">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label">Assessment Grade</label>
                  <select name="AssessmentGrade" class="form-select form-select-sm">
                    <option value="">Select grade</option>
                    <?php foreach ($assessmentGradeOptions as $option): ?>
                      <option value="<?= h((string) ($option['code'] ?? '')) ?>" <?= ((string) ($submission['AssessmentGrade'] ?? '')) === (string) ($option['code'] ?? '') ? 'selected' : '' ?>>
                        <?= h((string) ($option['label'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Reviewer Recommendation</label>
                  <select name="ReviewerRecommendation" class="form-select form-select-sm">
                    <option value="">Select recommendation</option>
                    <?php foreach ($reviewerRecommendationOptions as $option): ?>
                      <option value="<?= h((string) ($option['code'] ?? '')) ?>" <?= ((string) ($submission['ReviewerRecommendation'] ?? '')) === (string) ($option['code'] ?? '') ? 'selected' : '' ?>>
                        <?= h((string) ($option['label'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Reviewer Ranking</label>
                  <input type="number" min="1" step="1" name="ReviewerRanking" class="form-control form-control-sm" value="<?= h((string) ($submission['ReviewerRanking'] ?? '')) ?>">
                </div>
                <div class="col-12">
                  <label class="form-label">Overall Decision Note</label>
                  <textarea name="DecisionNote" class="form-control form-control-sm" rows="3"><?= h((string) ($submission['DecisionNote'] ?? '')) ?></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label">Reviewer Summary</label>
                  <textarea name="ReviewerSummary" class="form-control form-control-sm" rows="4"><?= h((string) ($submission['ReviewerSummary'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Conditions / Required Changes</label>
                  <textarea name="ReviewerConditions" class="form-control form-control-sm" rows="4"><?= h((string) ($submission['ReviewerConditions'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Next Steps / Follow-up</label>
                  <textarea name="ReviewerNextSteps" class="form-control form-control-sm" rows="4"><?= h((string) ($submission['ReviewerNextSteps'] ?? '')) ?></textarea>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-outline-primary btn-sm">Save Submission Assessment</button>
              </div>
            </form>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="history-shell">
    <div class="card-header"><h5 class="mb-0">Workflow History</h5></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 history-table">
          <thead class="table-light">
            <tr>
              <th>Action</th>
              <th>From</th>
              <th>To</th>
              <th>Note</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($history === []): ?>
              <tr><td colspan="5" class="text-center text-muted py-3">No workflow history yet.</td></tr>
            <?php else: ?>
              <?php foreach ($history as $row): ?>
                <tr>
                  <td><?= h((string) ($row['WorkflowActionCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['FromStatusCode'] ?? '')) ?></td>
                  <td><?= h((string) ($row['ToStatusCode'] ?? '')) ?></td>
                  <td class="history-note"><?= h((string) ($row['ActionNote'] ?? '')) ?></td>
                  <td><?= h((string) ($row['ActionDate'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
