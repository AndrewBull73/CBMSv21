<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('rie_status_badge_class')) {
    function rie_status_badge_class(string $status): string
    {
        return match (strtoupper(trim($status))) {
            'APPROVED' => 'text-bg-success',
            'CANCELLED' => 'text-bg-secondary',
            default => 'text-bg-warning',
        };
    }
}

$ctx = is_array($context ?? null) ? $context : [];
$fy = (int) ($ctx['FiscalYearID'] ?? 0);
$ver = (int) ($ctx['VersionID'] ?? 0);
$currentVersionLabel = trim((string) ($currentVersion['VersionLabel'] ?? ''));
$selectedRie = is_array($selectedRie ?? null) ? $selectedRie : null;
$selectedRieLines = is_array($selectedRieLines ?? null) ? $selectedRieLines : [];
$ries = is_array($ries ?? null) ? $ries : [];
$balanceCandidates = is_array($balanceCandidates ?? null) ? $balanceCandidates : [];
$summary = is_array($summary ?? null) ? $summary : [];
$openingSummary = is_array($openingSummary ?? null) ? $openingSummary : [];
$selectedRieWorkflow = is_array($selectedRieWorkflow ?? null) ? $selectedRieWorkflow : [];
$supportsWorkflowEngine = !empty($supportsWorkflowEngine);
$csrf = h(csrf_token());
$screenHeader = [
    'title' => 'Budget Execution RIE',
    'icon' => 'bi-file-earmark-check',
];
$selectedStatus = strtoupper(trim((string) ($selectedRie['RieStatusCode'] ?? '')));
$selectedRieId = (int) ($selectedRie['RieID'] ?? 0);
$selectedWorkflowInstance = is_array($selectedRieWorkflow['Instance'] ?? null) ? $selectedRieWorkflow['Instance'] : null;
$selectedWorkflowStage = is_array($selectedRieWorkflow['CurrentStage'] ?? null) ? $selectedRieWorkflow['CurrentStage'] : null;
$selectedWorkflowStageCode = strtoupper(trim((string) ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowStageName = trim((string) ($selectedWorkflowStage['WorkflowStageName'] ?? ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowHistory = is_array($selectedRieWorkflow['History'] ?? null) ? $selectedRieWorkflow['History'] : [];
$selectedWorkflowAllowedActions = array_map(
    static fn(array $action): string => strtoupper(trim((string) ($action['WorkflowActionCode'] ?? ''))),
    is_array($selectedRieWorkflow['AllowedActions'] ?? null) ? $selectedRieWorkflow['AllowedActions'] : []
);
$canEditSelectedRie = $selectedRie !== null
    && $selectedStatus === 'DRAFT'
    && (!$supportsWorkflowEngine || $selectedWorkflowStageCode === '' || $selectedWorkflowStageCode === 'DRAFT');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h((string) $fy) ?></strong>
        <?php if ($currentVersionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($currentVersionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <?php if ($isExecutionVersion): ?>
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">RIE Batches</div><div class="fs-4 fw-semibold"><?= h((string) ($summary['RieCount'] ?? 0)) ?></div></div></div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Approved RIEs</div><div class="fs-4 fw-semibold"><?= h((string) ($summary['ApprovedRieCount'] ?? 0)) ?></div></div></div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Approved Requested</div><div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['ApprovedRieAmountTotal'] ?? 0), 2)) ?></div></div></div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Current Available Released</div><div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['CurrentAvailableReleasedTotal'] ?? 0), 2)) ?></div></div></div>
          </div>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use RIE as the formal request-and-approval step before a Commitment is raised. An approved RIE does not consume budget by itself, but linked Commitments can be controlled against it.
      </div>

      <?php if (!$supportsRieFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>RIE tables are not installed.</strong> Run <code>create_budget_execution_rie_v1.sql</code> before using this screen.
        </div>
      <?php endif; ?>

      <?php if (!$supportsWorkflowEngine): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Shared workflow engine foundation is not installed.</strong> Run <code>create_workflow_engine_foundation_v1.sql</code> to enable multi-stage RIE workflow.
        </div>
      <?php endif; ?>

      <?php if (!$supportsWarrantFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Warrant tables are not installed.</strong> RIEs depend on approved warrant releases, so install <code>create_budget_execution_warrants_v1.sql</code> first.
        </div>
      <?php endif; ?>

      <?php if (!$isExecutionVersion): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-0">
          The current context is not an execution version. Select an execution version from Budget Execution Setup first.
        </div>
      <?php else: ?>
        <div class="row g-3 mb-4">
          <div class="col-12 col-xl-5">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Create RIE</h5>
              </div>
              <div class="card-body">
                <div class="small text-muted mb-3">Create a draft Request to Incur Expenditure first, then add the requested lines for approval.</div>
                <form method="post" action="index.php?route=execution/save-rie" id="rie-create-form">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <div class="mb-3">
                    <label class="form-label" for="RieTitle">RIE Title</label>
                    <input class="form-control" type="text" name="RieTitle" id="RieTitle" placeholder="e.g. RIE for priority procurement package" required>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label" for="RieDate">RIE Date</label>
                      <input class="form-control" type="date" name="RieDate" id="RieDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="RieEffectiveDate">Effective Date</label>
                      <input class="form-control" type="date" name="EffectiveDate" id="RieEffectiveDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                  </div>
                  <div class="mt-3 mb-3">
                    <label class="form-label" for="RieHeaderNotes">Notes</label>
                    <textarea class="form-control" name="Notes" id="RieHeaderNotes" rows="3" placeholder="Optional justification or reference"></textarea>
                  </div>
                  <button type="submit" id="rie-create-btn" class="btn btn-primary btn-sm" <?= !$supportsRieFoundation || !$supportsWarrantFoundation ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Create RIE
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12 col-xl-7">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Released Balance Position</h5>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0" id="rie-register-table">
                    <thead class="table-light">
                      <tr><th>Measure</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                      <tr><td>Approved Released Through Warrants</td><td class="text-end"><?= h(number_format((float) ($openingSummary['ReleasedAmountTotal'] ?? 0), 2)) ?></td></tr>
                      <tr><td>Approved Reserved</td><td class="text-end"><?= h(number_format((float) ($openingSummary['ReservedAmountTotal'] ?? 0), 2)) ?></td></tr>
                      <tr><td>Approved Committed</td><td class="text-end"><?= h(number_format((float) ($openingSummary['CommittedAmountTotal'] ?? 0), 2)) ?></td></tr>
                      <tr><td>Available Released Balance</td><td class="text-end"><?= h(number_format((float) ($openingSummary['AvailableReleasedAmountTotal'] ?? 0), 2)) ?></td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">RIE Register</h5>
          </div>
          <div class="card-body">
            <?php if (empty($ries)): ?>
              <div class="text-muted">No RIEs have been created for this execution version yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>RIE</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th class="text-end">Lines</th>
                      <th class="text-end">Amount</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($ries as $row): ?>
                      <?php $rowId = (int) ($row['RieID'] ?? 0); ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($row['RieNo'] ?? '')) ?></div>
                          <div class="small text-muted"><?= h((string) ($row['RieTitle'] ?? '')) ?></div>
                        </td>
                        <td><?= h((string) ($row['RieDate'] ?? '')) ?></td>
                        <?php $rowWorkflowStageName = trim((string) ($row['WorkflowStageName'] ?? '')); ?>
                        <td>
                          <span class="badge <?= h(rie_status_badge_class((string) ($row['RieStatusCode'] ?? ''))) ?>"><?= h((string) ($row['RieStatusCode'] ?? '')) ?></span>
                          <?php if ($rowWorkflowStageName !== '' && strtoupper($rowWorkflowStageName) !== strtoupper((string) ($row['RieStatusCode'] ?? ''))): ?>
                            <div class="small text-muted mt-1"><?= h($rowWorkflowStageName) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-end"><?= h((string) ($row['LineCount'] ?? 0)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['TotalRieAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><a href="index.php?route=execution/rie&rie_id=<?= $rowId ?>" id="rie-open-btn-<?= $rowId ?>" class="btn btn-outline-primary btn-sm">Open</a></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Selected RIE</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedRie === null): ?>
                  <div class="text-muted">Select an RIE from the register to review its lines and actions.</div>
                <?php else: ?>
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                      <div class="fw-semibold"><?= h((string) ($selectedRie['RieNo'] ?? '')) ?></div>
                      <div class="text-muted"><?= h((string) ($selectedRie['RieTitle'] ?? '')) ?></div>
                      <?php if ($selectedWorkflowStageName !== '' && strtoupper($selectedWorkflowStageName) !== $selectedStatus): ?>
                        <div class="small text-muted mt-1">Workflow Stage: <?= h($selectedWorkflowStageName) ?></div>
                      <?php endif; ?>
                    </div>
                    <span class="badge <?= h(rie_status_badge_class((string) ($selectedRie['RieStatusCode'] ?? ''))) ?>"><?= h((string) ($selectedRie['RieStatusCode'] ?? '')) ?></span>
                  </div>

                  <div class="row g-3 mb-3">
                    <div class="col-md-4"><div class="text-muted small">RIE Date</div><div class="fw-semibold"><?= h((string) ($selectedRie['RieDate'] ?? '')) ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Line Count</div><div class="fw-semibold"><?= h((string) ($selectedRie['LineCount'] ?? 0)) ?></div></div>
                    <div class="col-md-4"><div class="text-muted small">Amount</div><div class="fw-semibold"><?= h(number_format((float) ($selectedRie['TotalRieAmount'] ?? 0), 2)) ?></div></div>
                  </div>

                  <?php if ($supportsWorkflowEngine && $selectedWorkflowStageName !== ''): ?>
                    <div class="row g-3 mb-3">
                      <div class="col-md-6">
                        <div class="text-muted small">Workflow Stage</div>
                        <div class="fw-semibold"><?= h($selectedWorkflowStageName) ?></div>
                      </div>
                      <div class="col-md-6">
                        <div class="text-muted small">Workflow Record</div>
                        <div class="fw-semibold"><?= h((string) ($selectedWorkflowInstance['WorkflowInstanceID'] ?? '')) ?></div>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (!empty($selectedRie['Notes'])): ?>
                    <div class="small text-muted mb-3"><?= nl2br(h((string) $selectedRie['Notes'])) ?></div>
                  <?php endif; ?>

                  <div class="d-flex gap-2 flex-wrap">
                    <?php if ($supportsWorkflowEngine && in_array('SUBMIT', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/submit-rie" id="rie-submit-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="RieID" value="<?= $selectedRieId ?>">
                        <button type="submit" id="rie-submit-btn" class="btn btn-primary btn-sm">Submit For Review</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('FORWARD', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/forward-rie" id="rie-forward-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="RieID" value="<?= $selectedRieId ?>">
                        <button type="submit" id="rie-forward-btn" class="btn btn-primary btn-sm">Forward To Final Approval</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('RETURN', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/return-rie" id="rie-return-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="RieID" value="<?= $selectedRieId ?>">
                        <button type="submit" id="rie-return-btn" class="btn btn-outline-secondary btn-sm">Return</button>
                      </form>
                    <?php endif; ?>
                    <?php if ((!$supportsWorkflowEngine && $selectedStatus === 'DRAFT' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('APPROVE', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/approve-rie" id="rie-approve-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="RieID" value="<?= $selectedRieId ?>">
                        <button type="submit" id="rie-approve-btn" class="btn btn-success btn-sm">Approve RIE</button>
                      </form>
                    <?php endif; ?>
                    <?php if ((!$supportsWorkflowEngine && $selectedStatus !== 'CANCELLED' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('CANCEL', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/cancel-rie" id="rie-cancel-form" onsubmit="return confirm('Cancel this RIE?');">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="RieID" value="<?= $selectedRieId ?>">
                        <button type="submit" id="rie-cancel-btn" class="btn btn-outline-danger btn-sm">Cancel RIE</button>
                      </form>
                    <?php endif; ?>
                  </div>

                  <?php if ($supportsWorkflowEngine && !empty($selectedWorkflowHistory)): ?>
                    <div class="mt-3">
                      <div class="text-muted small mb-2">Workflow History</div>
                      <div class="small">
                        <?php foreach (array_slice($selectedWorkflowHistory, 0, 5) as $historyRow): ?>
                          <div class="mb-1">
                            <strong><?= h((string) ($historyRow['WorkflowActionCode'] ?? '')) ?></strong>
                            <?= h((string) ($historyRow['ToStageCode'] ?? '')) ?>
                            <?php if (trim((string) ($historyRow['ActionDate'] ?? '')) !== ''): ?>
                              <span class="text-muted">on <?= h((string) ($historyRow['ActionDate'] ?? '')) ?></span>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Add RIE Line</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedRie === null): ?>
                  <div class="text-muted">Create or open an RIE before adding lines.</div>
                <?php elseif (!$canEditSelectedRie): ?>
                  <div class="text-muted">Only draft RIEs can accept new lines.</div>
                <?php else: ?>
                  <form method="post" action="index.php?route=execution/save-rie-line" id="rie-line-form">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="RieID" value="<?= $selectedRieId ?>">
                    <div class="mb-3">
                      <label class="form-label" for="RieExecutionOpeningBalanceID">Execution Balance Line</label>
                      <select class="form-select" name="ExecutionOpeningBalanceID" id="RieExecutionOpeningBalanceID" required>
                        <option value="">Select a balance line</option>
                        <?php foreach ($balanceCandidates as $candidate): ?>
                          <?php $candidateId = (int) ($candidate['ExecutionOpeningBalanceID'] ?? 0); ?>
                          <option value="<?= $candidateId ?>">
                            <?= h((string) ($candidate['DataObjectCode'] ?? '')) ?>
                            <?php if (!empty($candidate['BidTitle'])): ?> - <?= h((string) $candidate['BidTitle']) ?><?php endif; ?>
                            | Available <?= h(number_format((float) ($candidate['AvailableReleasedForRie'] ?? 0), 2)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="row g-3">
                      <div class="col-md-6">
                        <label class="form-label" for="RieAmount">Requested Amount</label>
                        <input class="form-control" type="number" step="0.01" min="0.01" name="RieAmount" id="RieAmount" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label" for="RieLineNotes">Notes</label>
                        <input class="form-control" type="text" name="Notes" id="RieLineNotes" placeholder="Optional line note">
                      </div>
                    </div>
                    <div class="mt-3">
                      <button type="submit" id="rie-add-line-btn" class="btn btn-primary btn-sm">Add RIE Line</button>
                    </div>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-4 mb-4">
          <div class="card-header">
            <h5 class="mb-0">RIE Lines</h5>
          </div>
          <div class="card-body">
            <?php if (empty($selectedRieLines)): ?>
              <div class="text-muted">No lines exist for the selected RIE yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="rie-lines-table">
                  <thead class="table-light">
                    <tr>
                      <th>Data Object</th>
                      <th>Bid Title</th>
                      <th class="text-end">Requested</th>
                      <th>Notes</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($selectedRieLines as $line): ?>
                      <tr>
                        <td><?= h((string) ($line['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($line['BidTitle'] ?? '')) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($line['RieAmount'] ?? 0), 2)) ?></td>
                        <td><?= h((string) ($line['Notes'] ?? '')) ?></td>
                        <td class="text-end">
                          <?php if ($canEditSelectedRie): ?>
                            <form method="post" action="index.php?route=execution/delete-rie-line" class="d-inline" id="rie-remove-line-form-<?= (int) ($line['RieLineID'] ?? 0) ?>" onsubmit="return confirm('Remove this RIE line?');">
                              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                              <input type="hidden" name="RieID" value="<?= $selectedRieId ?>">
                              <input type="hidden" name="RieLineID" value="<?= (int) ($line['RieLineID'] ?? 0) ?>">
                              <button type="submit" id="rie-remove-line-btn-<?= (int) ($line['RieLineID'] ?? 0) ?>" class="btn btn-outline-danger btn-sm">Remove</button>
                            </form>
                          <?php else: ?>
                            <span class="text-muted small">Locked</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header">
            <h5 class="mb-0">RIE Reference Capacity</h5>
          </div>
          <div class="card-body">
            <div class="small text-muted mb-3">This view helps users choose lines where released balance still exists. Approved RIEs do not reserve these balances by themselves.</div>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Data Object</th>
                    <th>Bid Title</th>
                    <th class="text-end">Approved Released</th>
                    <th class="text-end">Approved Reserved</th>
                    <th class="text-end">Approved Committed</th>
                    <th class="text-end">Available Released</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($balanceCandidates as $candidate): ?>
                    <tr>
                      <td><?= h((string) ($candidate['DataObjectCode'] ?? '')) ?></td>
                      <td><?= h((string) ($candidate['BidTitle'] ?? '')) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($candidate['ApprovedReleasedAmountTotal'] ?? 0), 2)) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($candidate['ApprovedReservedAmountTotal'] ?? 0), 2)) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($candidate['ApprovedCommittedAmountTotal'] ?? 0), 2)) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($candidate['AvailableReleasedForRie'] ?? 0), 2)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
