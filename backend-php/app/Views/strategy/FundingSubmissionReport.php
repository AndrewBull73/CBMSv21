<?php
declare(strict_types=1);
/** @var array $contextLabels */
/** @var bool $workflowInstalled */
/** @var array $summary */
/** @var array $bySector */
/** @var array $byProgram */
/** @var array $submissions */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('money_fmt')) {
    function money_fmt($value): string
    {
        return number_format((float) $value, 2);
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
    'title' => 'Funding Submission Summary',
    'icon' => 'bi-bar-chart',
];
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
        <div class="small text-muted">
          Requested, approved, and published bid totals for
          <strong><?= h((string) ($contextLabels['YearLabel'] ?? '')) ?></strong>
          /
          <strong><?= h((string) ($contextLabels['VersionLabel'] ?? '')) ?></strong>.
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="index.php?route=strategy-submissions/list" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i>Submission List
          </a>
          <a href="index.php?route=strategy-submissions/form" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>New Lodgement
          </a>
        </div>
      </div>

      <?php if (!$workflowInstalled): ?>
        <div class="alert alert-warning">Run <code>create_tblSbFundingSubmission.sql</code> to enable funding submissions and summary reporting.</div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Submissions</div><div class="fs-4 fw-semibold"><?= (int) ($summary['SubmissionCount'] ?? 0) ?></div></div></div>
        </div>
        <div class="col-6 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Funding Items</div><div class="fs-4 fw-semibold"><?= (int) ($summary['LineCount'] ?? 0) ?></div></div></div>
        </div>
        <div class="col-12 col-md-4 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Requested</div><div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['RequestedAmount'] ?? 0), 0)) ?></div></div></div>
        </div>
        <div class="col-12 col-md-4 col-xl-2">
          <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Approved</div><div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['ApprovedAmount'] ?? 0), 0)) ?></div></div></div>
        </div>
        <div class="col-12 col-md-4 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Published</div><div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['PublishedAmount'] ?? 0), 0)) ?></div></div></div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-xl-6">
          <div class="card shadow-sm h-100">
        <div class="card-header"><h5 class="mb-0">By Sector</h5></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Sector</th>
                  <th class="text-end">Lines</th>
                  <th class="text-end">Requested</th>
                  <th class="text-end">Approved</th>
                  <th class="text-end">Published</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($bySector === []): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">No sector-level funding submissions yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($bySector as $row): ?>
                    <tr>
                      <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                      <td class="text-end"><?= (int) ($row['LineCount'] ?? 0) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($row['RequestedAmount'] ?? 0), 0)) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($row['ApprovedAmount'] ?? 0), 0)) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($row['PublishedAmount'] ?? 0), 0)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

        <div class="col-xl-6">
          <div class="card shadow-sm h-100">
        <div class="card-header"><h5 class="mb-0">By Program</h5></div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Program</th>
                  <th>Sector</th>
                  <th class="text-end">Lines</th>
                  <th class="text-end">Requested</th>
                  <th class="text-end">Approved</th>
                  <th class="text-end">Published</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($byProgram === []): ?>
                  <tr><td colspan="6" class="text-center text-muted py-3">No program-level funding submissions yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($byProgram as $row): ?>
                    <tr>
                      <td><?= h((string) ($row['ProgramName'] ?? '')) ?></td>
                      <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                      <td class="text-end"><?= (int) ($row['LineCount'] ?? 0) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($row['RequestedAmount'] ?? 0), 0)) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($row['ApprovedAmount'] ?? 0), 0)) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($row['PublishedAmount'] ?? 0), 0)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

      <div class="card shadow-sm mt-4">
    <div class="card-header"><h5 class="mb-0">Submission Status Snapshot</h5></div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Submission</th>
              <th>Assessment</th>
              <th>Scope</th>
              <th>Status</th>
              <th class="text-end">Lines</th>
              <th class="text-end">Requested</th>
              <th class="text-end">Approved</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if ($submissions === []): ?>
              <tr><td colspan="8" class="text-center text-muted py-3">No submissions in this context yet.</td></tr>
            <?php else: ?>
              <?php foreach ($submissions as $row): ?>
                <tr>
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
                  <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                  <td><span class="badge <?= h(funding_submission_status_badge_class((string) ($row['SubmissionStatusCode'] ?? ''))) ?>"><?= h(funding_submission_status_label((string) ($row['SubmissionStatusCode'] ?? ''))) ?></span></td>
                  <td class="text-end"><?= (int) ($row['LineCount'] ?? 0) ?></td>
                  <td class="text-end"><?= h(number_format((float) ($row['TotalRequestedAmount'] ?? 0), 0)) ?></td>
                  <td class="text-end"><?= h(number_format((float) ($row['TotalApprovedAmount'] ?? 0), 0)) ?></td>
                  <td class="text-end">
                    <a href="index.php?route=strategy-submissions/view&id=<?= (int) ($row['StrategicFundingSubmissionID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">
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
  </div>
</div>
