<?php
declare(strict_types=1);
/** @var array $submission */
/** @var array $line */
/** @var bool $reviewResponseFieldsInstalled */
if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('money0')) {
    function money0($value): string
    {
        return number_format((float) $value, 0);
    }
}
$reviewResponseFieldsInstalled = !empty($reviewResponseFieldsInstalled);
$currentStatus = strtoupper(trim((string) ($line['LineStatusCode'] ?? 'DRAFT')));
$currentDecision = strtolower(trim((string) ($line['LineStatusCode'] ?? '')));
$reviewAction = match ($currentDecision) {
    'approved' => 'approve',
    'partial' => 'partial',
    'rejected' => 'reject',
    default => 'partial',
};

$amountRows = [
    [
        'label' => 'Current Year',
        'requested' => $line['CurrentYearRequestedAmount'] ?? 0,
        'approved_name' => 'CurrentYearApprovedAmount',
        'approved' => $line['CurrentYearApprovedAmount'] ?? $line['CurrentYearRequestedAmount'] ?? 0,
    ],
    [
        'label' => 'Outer Year 1',
        'requested' => $line['OuterYear1RequestedAmount'] ?? 0,
        'approved_name' => 'OuterYear1ApprovedAmount',
        'approved' => $line['OuterYear1ApprovedAmount'] ?? $line['OuterYear1RequestedAmount'] ?? 0,
    ],
    [
        'label' => 'Outer Year 2',
        'requested' => $line['OuterYear2RequestedAmount'] ?? 0,
        'approved_name' => 'OuterYear2ApprovedAmount',
        'approved' => $line['OuterYear2ApprovedAmount'] ?? $line['OuterYear2RequestedAmount'] ?? 0,
    ],
    [
        'label' => 'Outer Year 3',
        'requested' => $line['OuterYear3RequestedAmount'] ?? 0,
        'approved_name' => 'OuterYear3ApprovedAmount',
        'approved' => $line['OuterYear3ApprovedAmount'] ?? $line['OuterYear3RequestedAmount'] ?? 0,
    ],
    [
        'label' => 'Outer Year 4',
        'requested' => $line['OuterYear4RequestedAmount'] ?? 0,
        'approved_name' => 'OuterYear4ApprovedAmount',
        'approved' => $line['OuterYear4ApprovedAmount'] ?? $line['OuterYear4RequestedAmount'] ?? 0,
    ],
    [
        'label' => 'Outer Year 5',
        'requested' => $line['OuterYear5RequestedAmount'] ?? 0,
        'approved_name' => 'OuterYear5ApprovedAmount',
        'approved' => $line['OuterYear5ApprovedAmount'] ?? $line['OuterYear5RequestedAmount'] ?? 0,
    ],
];
?>
<div class="container-fluid py-3">
  <style>
    .container-fluid.py-3 {
      font-size: .95rem;
    }
    .review-shell {
      background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
      border: 1px solid #d9e6f2;
      border-radius: 1.15rem;
      overflow: hidden;
      box-shadow: 0 .45rem 1.35rem rgba(43, 63, 87, 0.06);
    }
    .review-hero {
      background: linear-gradient(135deg, #f3f9ff 0%, #ffffff 100%);
      border-bottom: 1px solid #dce8f3;
      padding: 1.2rem 1.3rem;
    }
    .review-title {
      font-size: 1.42rem;
      line-height: 1.2;
      letter-spacing: -.02em;
    }
    .review-subtext {
      font-size: .9rem;
      max-width: 52rem;
    }
    .review-section-title {
      font-size: .72rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: #6c757d;
      font-weight: 700;
      margin-bottom: .75rem;
    }
    .review-readonly {
      background-color: #eef3f8;
      border-color: #d7e0ea;
      color: #495057;
      font-weight: 600;
    }
    .review-amount-grid thead th {
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .review-amount-grid tbody td {
      vertical-align: middle;
    }
    .review-amount-grid .amount-label {
      font-weight: 600;
    }
    .review-amount-grid .requested-cell {
      background-color: #f3f6f9;
    }
    .review-decision-panel {
      background: linear-gradient(135deg, #fff7df 0%, #fff2bf 100%);
      border: 1px solid #f1d57a;
    }
    .review-panel {
      background: #fff;
      border: 1px solid #e4ebf2;
      border-radius: 1rem;
      padding: 1.05rem 1.1rem;
      box-shadow: 0 .35rem 1rem rgba(43, 63, 87, 0.05);
    }
    .review-decision-panel.review-panel {
      border-color: #f1d57a;
    }
    .review-decision-panel .form-label {
      color: #7a5a00;
      font-weight: 700;
    }
    .review-meta-chip {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .45rem .75rem;
      border-radius: 999px;
      background: #eef3f8;
      color: #435160;
      font-size: .84rem;
      font-weight: 600;
    }
  </style>

  <div class="review-shell mb-3">
    <div class="review-hero d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="review-section-title mb-2">Funding Item Review</div>
        <h1 class="review-title mb-2">Review Funding Item</h1>
        <div class="text-muted review-subtext">
          Record the reviewer decision, approved amounts, and formal response for this funding item under
          <strong><?= h((string) ($submission['RequestTitle'] ?? '')) ?></strong>.
        </div>
      </div>
      <a href="index.php?route=strategy-submissions/view&id=<?= (int) ($submission['StrategicFundingSubmissionID'] ?? 0) ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
  </div>

  <div class="card shadow-sm review-shell">
    <div class="card-body p-4">
      <?php if (!$reviewResponseFieldsInstalled): ?>
        <div class="alert alert-warning">
          Run the updated <code>create_tblSbFundingSubmission.sql</code> script to enable the reviewer response fields.
        </div>
      <?php endif; ?>

      <form method="post" action="index.php?route=strategy-submissions/save-review" id="FundingSubmissionReviewForm">
        <input type="hidden" name="submission_id" value="<?= (int) ($submission['StrategicFundingSubmissionID'] ?? 0) ?>">
        <input type="hidden" name="line_id" value="<?= (int) ($line['StrategicFundingSubmissionLineID'] ?? 0) ?>">

        <div class="review-panel mb-4">
          <div class="review-section-title">Funding Item Overview</div>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="review-meta-chip">Current Status: <?= h($currentStatus) ?></span>
            <?php if (!empty($line['SectorName'])): ?>
              <span class="review-meta-chip">Sector: <?= h((string) ($line['SectorName'] ?? '')) ?></span>
            <?php endif; ?>
            <?php if (!empty($line['ProgramName'])): ?>
              <span class="review-meta-chip">Program: <?= h((string) ($line['ProgramName'] ?? '')) ?></span>
            <?php endif; ?>
          </div>
          <div class="fs-5 fw-semibold"><?= h((string) ($line['BidTitle'] ?? '')) ?></div>
          <?php if (!empty($line['BusinessCaseSummary'])): ?>
            <div class="text-muted mt-2"><?= nl2br(h((string) ($line['BusinessCaseSummary'] ?? ''))) ?></div>
          <?php endif; ?>
        </div>

        <div class="row g-4">
          <div class="col-xl-4">
            <div class="review-decision-panel review-panel h-100">
              <div class="review-section-title mb-2">Decision</div>
              <div class="mb-3">
                <label class="form-label">Review Decision</label>
                <select name="ReviewAction" class="form-select form-select-lg border-warning-subtle" required>
                  <option value="approve" <?= $reviewAction === 'approve' ? 'selected' : '' ?>>Approve</option>
                  <option value="partial" <?= $reviewAction === 'partial' ? 'selected' : '' ?>>Partial Approve</option>
                  <option value="reject" <?= $reviewAction === 'reject' ? 'selected' : '' ?>>Reject</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Current Line Status</label>
                <input type="text" class="form-control review-readonly" value="<?= h($currentStatus) ?>" readonly>
              </div>
              <div class="small text-muted mb-0">
                Use <strong>Approve</strong> for the full request, <strong>Partial Approve</strong> for a reduced amount, and <strong>Reject</strong> to zero the approved values.
              </div>
            </div>
          </div>

          <div class="col-xl-8">
            <div class="review-panel h-100">
              <div class="review-section-title">Amount Review</div>
              <div class="table-responsive">
                <table class="table table-sm align-middle review-amount-grid mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Period</th>
                      <th class="text-end">Requested Amount</th>
                      <th class="text-end">Approved Amount</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($amountRows as $row): ?>
                      <tr>
                        <td class="amount-label"><?= h((string) ($row['label'] ?? '')) ?></td>
                        <td class="text-end requested-cell">
                          <input type="text" class="form-control text-end review-readonly" value="<?= h(money0($row['requested'] ?? 0)) ?>" readonly>
                        </td>
                        <td class="text-end">
                          <input
                            type="text"
                            inputmode="numeric"
                            name="<?= h((string) ($row['approved_name'] ?? '')) ?>"
                            class="form-control text-end js-money-whole"
                            value="<?= h(money0($row['approved'] ?? 0)) ?>"
                            autocomplete="off"
                          >
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="review-panel mb-4">
              <div class="review-section-title">Reviewer Scores</div>
              <div class="row g-3">
                <div class="col-md-6 col-xl-3">
                  <label class="form-label">Strategic Alignment</label>
                  <input type="number" step="0.01" min="0" max="5" name="ScoreStrategicAlignment" class="form-control" value="<?= h((string) ($line['ScoreStrategicAlignment'] ?? '')) ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                  <label class="form-label">Readiness</label>
                  <input type="number" step="0.01" min="0" max="5" name="ScoreReadiness" class="form-control" value="<?= h((string) ($line['ScoreReadiness'] ?? '')) ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                  <label class="form-label">Fiscal Affordability</label>
                  <input type="number" step="0.01" min="0" max="5" name="ScoreFiscalAffordability" class="form-control" value="<?= h((string) ($line['ScoreFiscalAffordability'] ?? '')) ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                  <label class="form-label">Service Impact</label>
                  <input type="number" step="0.01" min="0" max="5" name="ScoreServiceImpact" class="form-control" value="<?= h((string) ($line['ScoreServiceImpact'] ?? '')) ?>">
                </div>
              </div>
            </div>
          </div>

          <div class="col-12">
            <div class="review-panel">
              <div class="review-section-title">Reviewer Notes</div>
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Decision Note</label>
                  <textarea name="DecisionNote" class="form-control" rows="3"><?= h((string) ($line['DecisionNote'] ?? '')) ?></textarea>
                </div>
                <div class="col-12">
                  <label class="form-label">Reviewer Response</label>
                  <textarea name="ReviewerResponse" class="form-control" rows="4"<?= $reviewResponseFieldsInstalled ? '' : ' disabled' ?>><?= h((string) ($line['ReviewerResponse'] ?? '')) ?></textarea>
                  <div class="form-text">Use this for the formal reviewer response back to the proposal owner.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Conditions / Required Changes</label>
                  <textarea name="ReviewerConditions" class="form-control" rows="4"<?= $reviewResponseFieldsInstalled ? '' : ' disabled' ?>><?= h((string) ($line['ReviewerConditions'] ?? '')) ?></textarea>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Next Steps / Follow-up</label>
                  <textarea name="ReviewerNextSteps" class="form-control" rows="4"<?= $reviewResponseFieldsInstalled ? '' : ' disabled' ?>><?= h((string) ($line['ReviewerNextSteps'] ?? '')) ?></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-primary">Save Review</button>
          <a href="index.php?route=strategy-submissions/view&id=<?= (int) ($submission['StrategicFundingSubmissionID'] ?? 0) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('FundingSubmissionReviewForm');
    if (!form) {
        return;
    }

    const formatWhole = function (rawValue) {
        const normalized = String(rawValue || '').replace(/,/g, '').trim();
        if (normalized === '') {
            return '';
        }

        const parsed = Number(normalized);
        if (!Number.isFinite(parsed)) {
            return rawValue;
        }

        return new Intl.NumberFormat('en-US', {
            maximumFractionDigits: 0,
            minimumFractionDigits: 0
        }).format(Math.round(parsed));
    };

    const unformatWhole = function (rawValue) {
        return String(rawValue || '').replace(/,/g, '').trim();
    };

    form.querySelectorAll('.js-money-whole').forEach(function (input) {
        input.addEventListener('focus', function () {
            input.value = unformatWhole(input.value);
        });

        input.addEventListener('blur', function () {
            input.value = formatWhole(input.value);
        });

        input.value = formatWhole(input.value);
    });

    form.addEventListener('submit', function () {
        form.querySelectorAll('.js-money-whole').forEach(function (input) {
            input.value = unformatWhole(input.value);
        });
    });
});
</script>
