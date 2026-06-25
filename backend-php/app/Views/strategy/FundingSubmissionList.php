<?php
declare(strict_types=1);
/** @var array $submissions */
/** @var array $contextLabels */
/** @var bool $workflowInstalled */
/** @var string $pageTitle */
/** @var string $pageSubtitle */
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

$screenHeader = [
    'title' => (string) ($pageTitle ?? 'Funding Submissions'),
    'icon' => 'bi-inbox',
];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div class="small text-muted">
          <?= h((string) ($pageSubtitle ?? 'Funding lodgements and formal submissions across the workflow.')) ?>
          For
          <strong><?= h((string) ($contextLabels['YearLabel'] ?? '')) ?></strong>
          /
          <strong><?= h((string) ($contextLabels['VersionLabel'] ?? '')) ?></strong>.
        </div>
        <a href="index.php?route=strategy-submissions/form" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-circle me-1"></i>New Lodgement
        </a>
      </div>

      <?php if (!$workflowInstalled): ?>
        <div class="alert alert-warning">Run <code>create_tblSbFundingSubmission.sql</code> to enable funding submissions.</div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Assessment</th>
              <th>DataScope</th>
              <th>Status</th>
              <th class="text-end">Lines</th>
              <th class="text-end">Requested</th>
              <th class="text-end">Approved</th>
              <th>Updated</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($submissions === []): ?>
              <tr><td colspan="10" class="text-center text-muted py-4">No funding submissions yet.</td></tr>
            <?php else: ?>
              <?php foreach ($submissions as $row): ?>
                <tr>
                  <td><?= (int) ($row['StrategicFundingSubmissionID'] ?? 0) ?></td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['RequestTitle'] ?? '')) ?></div>
                    <div class="small text-muted"><?= h((string) ($row['SubmissionTypeCode'] ?? '')) ?></div>
                  </td>
                  <td>
                    <?php if (!empty($row['AssessmentGrade']) || !empty($row['ReviewerRecommendation'])): ?>
                      <?php if (!empty($row['AssessmentGrade'])): ?>
                        <div class="fw-semibold"><?= h((string) ($row['AssessmentGrade'] ?? '')) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($row['ReviewerRecommendation'])): ?>
                        <div class="small text-muted"><?= h((string) ($row['ReviewerRecommendation'] ?? '')) ?></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted small">Not assessed</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="fw-semibold"><?= h((string) ($row['DataObjectCode'] ?? '')) ?></div>
                    <?php if (!empty($row['DataObjectName']) || !empty($row['OrgUnitName'])): ?>
                      <div class="small text-muted"><?= h((string) ($row['DataObjectName'] ?? $row['OrgUnitName'] ?? '')) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($row['DataObjectWorkflowStatus'])): ?>
                      <div class="small text-muted">Status: <?= h((string) ($row['DataObjectWorkflowStatus'] ?? '')) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge <?= h(funding_submission_status_badge_class((string) ($row['SubmissionStatusCode'] ?? 'DRAFT'))) ?>"><?= h(funding_submission_status_label((string) ($row['SubmissionStatusCode'] ?? 'DRAFT'))) ?></span></td>
                  <td class="text-end"><?= (int) ($row['LineCount'] ?? 0) ?></td>
                  <td class="text-end"><?= h(number_format((float) ($row['TotalRequestedAmount'] ?? 0), 0)) ?></td>
                  <td class="text-end"><?= h(number_format((float) ($row['TotalApprovedAmount'] ?? 0), 0)) ?></td>
                  <td><?= h((string) ($row['UpdatedDate'] ?? $row['CreatedDate'] ?? '')) ?></td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="index.php?route=strategy-submissions/view&id=<?= (int) ($row['StrategicFundingSubmissionID'] ?? 0) ?>">
                      <i class="bi bi-folder2-open me-1"></i>Open
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
