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
?>
<div class="container-fluid py-3">
  <style>
    .container-fluid.py-3 {
      font-size: .95rem;
    }
    .funding-report-shell {
      background: linear-gradient(180deg, #f7fafc 0%, #ffffff 100%);
    }
    .funding-report-hero {
      background: linear-gradient(135deg, #f3f9ff 0%, #ffffff 100%);
      border: 1px solid #dce8f3;
      border-radius: 1.2rem;
      padding: 1.25rem 1.35rem;
      box-shadow: 0 .45rem 1.35rem rgba(43, 63, 87, 0.06);
    }
    .funding-report-eyebrow {
      font-size: .72rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #6c757d;
      font-weight: 700;
      margin-bottom: .45rem;
    }
    .funding-report-title {
      font-size: 1.45rem;
      line-height: 1.2;
      letter-spacing: -.02em;
    }
    .funding-report-subtext {
      font-size: .9rem;
      max-width: 52rem;
    }
    .funding-report-stat {
      background: #fff;
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
      height: 100%;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .funding-report-label {
      font-size: .7rem;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: #7a8796;
      font-weight: 700;
      margin-bottom: .35rem;
    }
    .funding-report-value {
      font-size: .92rem;
      font-weight: 700;
      color: #203040;
      line-height: 1.35;
      word-break: break-word;
      padding-right: .1rem;
    }
    .funding-report-panel {
      background: #fff;
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      overflow: hidden;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .funding-report-table thead th {
      font-size: .72rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6f7f90;
    }
    .funding-report-table td,
    .funding-report-table th {
      font-size: .88rem;
      vertical-align: top;
    }
    .funding-report-table thead th,
    .funding-report-table tbody td {
      padding: .9rem 1.05rem;
    }
    .funding-report-table td {
      white-space: normal;
      word-break: break-word;
    }
    .funding-report-panel .card-header {
      padding: 1.05rem 1.15rem;
    }
    .funding-report-panel .card-header h5 {
      font-size: 1rem;
    }
  </style>
  <div class="funding-report-shell rounded-4 p-1 mb-3">
    <div class="funding-report-hero d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="funding-report-eyebrow">Funding Submission Reporting</div>
        <h1 class="funding-report-title mb-2">Funding Submission Summary</h1>
        <div class="text-muted funding-report-subtext">
        Requested, approved, and published bid totals for
        <strong><?= h((string) ($contextLabels['YearLabel'] ?? '')) ?></strong>
        /
        <strong><?= h((string) ($contextLabels['VersionLabel'] ?? '')) ?></strong>
        </div>
      </div>
      <div class="d-flex gap-2">
        <a href="index.php?route=strategy-submissions/list" class="btn btn-outline-secondary btn-sm">Submission List</a>
        <a href="index.php?route=strategy-submissions/form" class="btn btn-primary btn-sm">New Lodgement</a>
      </div>
    </div>
  </div>

  <?php if (!$workflowInstalled): ?>
    <div class="alert alert-warning">Run <code>create_tblSbFundingSubmission.sql</code> to enable funding submissions and summary reporting.</div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-2"><div class="funding-report-stat"><div class="funding-report-label">Submissions</div><div class="funding-report-value"><?= (int) ($summary['SubmissionCount'] ?? 0) ?></div></div></div>
    <div class="col-6 col-xl-2"><div class="funding-report-stat"><div class="funding-report-label">Funding Items</div><div class="funding-report-value"><?= (int) ($summary['LineCount'] ?? 0) ?></div></div></div>
    <div class="col-12 col-xl-4"><div class="funding-report-stat"><div class="funding-report-label">Requested</div><div class="funding-report-value"><?= h(number_format((float) ($summary['RequestedAmount'] ?? 0), 0)) ?></div></div></div>
    <div class="col-12 col-md-6 col-xl-2"><div class="funding-report-stat"><div class="funding-report-label">Approved</div><div class="funding-report-value"><?= h(number_format((float) ($summary['ApprovedAmount'] ?? 0), 0)) ?></div></div></div>
    <div class="col-12 col-md-6 col-xl-2"><div class="funding-report-stat"><div class="funding-report-label">Published</div><div class="funding-report-value"><?= h(number_format((float) ($summary['PublishedAmount'] ?? 0), 0)) ?></div></div></div>
  </div>

  <div class="row g-4">
    <div class="col-xl-6">
      <div class="funding-report-panel">
        <div class="card-header"><h5 class="mb-0">By Sector</h5></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 funding-report-table">
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
      <div class="funding-report-panel">
        <div class="card-header"><h5 class="mb-0">By Program</h5></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 funding-report-table">
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

  <div class="funding-report-panel mt-4">
    <div class="card-header"><h5 class="mb-0">Submission Status Snapshot</h5></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0 funding-report-table">
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
                  <td class="text-end"><a href="index.php?route=strategy-submissions/view&id=<?= (int) ($row['StrategicFundingSubmissionID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">Open</a></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
