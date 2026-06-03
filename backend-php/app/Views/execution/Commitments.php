<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('commitment_status_badge_class')) {
    function commitment_status_badge_class(string $status): string
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
$selectedCommitment = is_array($selectedCommitment ?? null) ? $selectedCommitment : null;
$selectedCommitmentLines = is_array($selectedCommitmentLines ?? null) ? $selectedCommitmentLines : [];
$commitments = is_array($commitments ?? null) ? $commitments : [];
$balanceCandidates = is_array($balanceCandidates ?? null) ? $balanceCandidates : [];
$approvedRies = is_array($approvedRies ?? null) ? $approvedRies : [];
$summary = is_array($summary ?? null) ? $summary : [];
$openingSummary = is_array($openingSummary ?? null) ? $openingSummary : [];
$selectedCommitmentWorkflow = is_array($selectedCommitmentWorkflow ?? null) ? $selectedCommitmentWorkflow : [];
$supportsWorkflowEngine = !empty($supportsWorkflowEngine);
$csrf = h(csrf_token());
$screenHeader = [
    'title' => 'Budget Execution Commitments',
    'icon' => 'bi-journal-check',
];
$selectedStatus = strtoupper(trim((string) ($selectedCommitment['CommitmentStatusCode'] ?? '')));
$selectedCommitmentId = (int) ($selectedCommitment['CommitmentID'] ?? 0);
$selectedWorkflowInstance = is_array($selectedCommitmentWorkflow['Instance'] ?? null) ? $selectedCommitmentWorkflow['Instance'] : null;
$selectedWorkflowStage = is_array($selectedCommitmentWorkflow['CurrentStage'] ?? null) ? $selectedCommitmentWorkflow['CurrentStage'] : null;
$selectedWorkflowStageCode = strtoupper(trim((string) ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowStageName = trim((string) ($selectedWorkflowStage['WorkflowStageName'] ?? ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowHistory = is_array($selectedCommitmentWorkflow['History'] ?? null) ? $selectedCommitmentWorkflow['History'] : [];
$selectedWorkflowAllowedActions = array_map(
    static fn(array $action): string => strtoupper(trim((string) ($action['WorkflowActionCode'] ?? ''))),
    is_array($selectedCommitmentWorkflow['AllowedActions'] ?? null) ? $selectedCommitmentWorkflow['AllowedActions'] : []
);
$canEditSelectedCommitment = $selectedCommitment !== null
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
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Commitment Batches</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['CommitmentCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Commitments</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['ApprovedCommitmentCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Committed</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['ApprovedCommittedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Remaining Commitment Capacity</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['AvailableCommitmentCapacityTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use Commitments to formally consume released budget authority after the appropriate reservation and approval steps. Commitment lines reduce the balance still available for later commitments or expenditure.
      </div>

      <?php if (!$supportsCommitmentFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Commitment tables are not installed.</strong> Run <code>create_budget_execution_commitments_v1.sql</code> before using this screen.
        </div>
      <?php endif; ?>

      <?php if (!$supportsWarrantFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Warrant tables are not installed.</strong> Commitments depend on approved warrant releases, so install <code>create_budget_execution_warrants_v1.sql</code> first.
        </div>
      <?php endif; ?>

      <?php if (!$supportsWorkflowEngine): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Shared workflow engine foundation is not installed.</strong> Run <code>create_workflow_engine_foundation_v1.sql</code> to enable multi-stage commitment workflow.
        </div>
      <?php endif; ?>

      <?php if (!$supportsRieFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>RIE tables are not installed.</strong> Commitments can still be entered, but they cannot yet be linked back to an approved RIE.
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
                <h5 class="mb-0">Create Commitment</h5>
              </div>
              <div class="card-body">
                <div class="small text-muted mb-3">Create a draft commitment batch first, then add lines against the available released balance.</div>
                <form method="post" action="index.php?route=execution/save-commitment" id="commitment-create-form">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <?php if ($supportsRieFoundation): ?>
                    <div class="mb-3">
                      <label class="form-label" for="CommitmentRieID">Linked Approved RIE</label>
                      <select class="form-select" name="RieID" id="CommitmentRieID">
                        <option value="">No linked RIE</option>
                        <?php foreach ($approvedRies as $rie): ?>
                          <option value="<?= (int) ($rie['RieID'] ?? 0) ?>">
                            <?= h((string) ($rie['RieNo'] ?? '')) ?>
                            <?php if (!empty($rie['RieTitle'])): ?> - <?= h((string) $rie['RieTitle']) ?><?php endif; ?>
                            | Available <?= h(number_format((float) ($rie['AvailableRieAmount'] ?? 0), 2)) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <div class="form-text">Optional. If selected, approvals will be controlled against the remaining approved RIE amount.</div>
                    </div>
                  <?php endif; ?>
                  <div class="mb-3">
                    <label class="form-label" for="CommitmentTitle">Commitment Title</label>
                    <input class="form-control" type="text" name="CommitmentTitle" id="CommitmentTitle" placeholder="e.g. Signed contract commitment for priority package" required>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label" for="CommitmentDate">Commitment Date</label>
                      <input class="form-control" type="date" name="CommitmentDate" id="CommitmentDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="CommitmentEffectiveDate">Effective Date</label>
                      <input class="form-control" type="date" name="EffectiveDate" id="CommitmentEffectiveDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                  </div>
                  <div class="mt-3 mb-3">
                    <label class="form-label" for="CommitmentHeaderNotes">Notes</label>
                    <textarea class="form-control" name="Notes" id="CommitmentHeaderNotes" rows="3" placeholder="Optional commitment note or reference"></textarea>
                  </div>
                  <button type="submit" id="commitment-create-btn" class="btn btn-primary btn-sm" <?= !$supportsCommitmentFoundation || !$supportsWarrantFoundation ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Create Commitment
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
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Measure</th>
                        <th class="text-end">Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Approved Released Through Warrants</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['ReleasedAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                      <tr>
                        <td>Approved Reserved</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['ReservedAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                      <tr>
                        <td>Approved Committed</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['CommittedAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                      <tr>
                        <td>Available Released Balance</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['AvailableReleasedAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Commitment Register</h5>
          </div>
          <div class="card-body">
            <?php if (empty($commitments)): ?>
              <div class="text-muted">No commitments have been created for this execution version yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Commitment</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th class="text-end">Lines</th>
                      <th class="text-end">Amount</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($commitments as $row): ?>
                      <?php $rowId = (int) ($row['CommitmentID'] ?? 0); ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($row['CommitmentNo'] ?? '')) ?></div>
                          <div class="small text-muted"><?= h((string) ($row['CommitmentTitle'] ?? '')) ?></div>
                        </td>
                        <td><?= h((string) ($row['CommitmentDate'] ?? '')) ?></td>
                        <?php $rowWorkflowStageName = trim((string) ($row['WorkflowStageName'] ?? '')); ?>
                        <td>
                          <span class="badge <?= h(commitment_status_badge_class((string) ($row['CommitmentStatusCode'] ?? ''))) ?>"><?= h((string) ($row['CommitmentStatusCode'] ?? '')) ?></span>
                          <?php if ($rowWorkflowStageName !== '' && strtoupper($rowWorkflowStageName) !== strtoupper((string) ($row['CommitmentStatusCode'] ?? ''))): ?>
                            <div class="small text-muted mt-1"><?= h($rowWorkflowStageName) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-end"><?= h((string) ($row['LineCount'] ?? 0)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['TotalCommitmentAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end">
                          <a href="index.php?route=execution/commitments&commitment_id=<?= $rowId ?>" id="commitment-open-btn-<?= $rowId ?>" class="btn btn-outline-primary btn-sm">Open</a>
                        </td>
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
                <h5 class="mb-0">Selected Commitment</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedCommitment === null): ?>
                  <div class="text-muted">Select a commitment from the register to review its lines and add commitments.</div>
                <?php else: ?>
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                      <div class="fw-semibold"><?= h((string) ($selectedCommitment['CommitmentNo'] ?? '')) ?></div>
                      <div class="text-muted"><?= h((string) ($selectedCommitment['CommitmentTitle'] ?? '')) ?></div>
                      <?php if ($selectedWorkflowStageName !== '' && strtoupper($selectedWorkflowStageName) !== $selectedStatus): ?>
                        <div class="small text-muted mt-1">Workflow Stage: <?= h($selectedWorkflowStageName) ?></div>
                      <?php endif; ?>
                    </div>
                    <span class="badge <?= h(commitment_status_badge_class((string) ($selectedCommitment['CommitmentStatusCode'] ?? ''))) ?>"><?= h((string) ($selectedCommitment['CommitmentStatusCode'] ?? '')) ?></span>
                  </div>

                  <div class="row g-3 mb-3">
                    <div class="col-md-4">
                      <div class="text-muted small">Commitment Date</div>
                      <div class="fw-semibold"><?= h((string) ($selectedCommitment['CommitmentDate'] ?? '')) ?></div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted small">Line Count</div>
                      <div class="fw-semibold"><?= h((string) ($selectedCommitment['LineCount'] ?? 0)) ?></div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted small">Amount</div>
                      <div class="fw-semibold"><?= h(number_format((float) ($selectedCommitment['TotalCommitmentAmount'] ?? 0), 2)) ?></div>
                    </div>
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

                  <?php if (!empty($selectedCommitment['RieNo'])): ?>
                    <div class="mb-3">
                      <div class="text-muted small">Linked Approved RIE</div>
                      <div class="fw-semibold">
                        <?= h((string) ($selectedCommitment['RieNo'] ?? '')) ?>
                        <?php if (!empty($selectedCommitment['RieTitle'])): ?>
                          <span class="text-muted fw-normal">- <?= h((string) $selectedCommitment['RieTitle']) ?></span>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endif; ?>

                  <?php if (trim((string) ($selectedCommitment['Notes'] ?? '')) !== ''): ?>
                    <div class="small text-muted mb-3"><?= nl2br(h((string) ($selectedCommitment['Notes'] ?? ''))) ?></div>
                  <?php endif; ?>

                  <div class="d-flex gap-2 flex-wrap mb-0">
                    <?php if ($supportsWorkflowEngine && in_array('SUBMIT', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/submit-commitment" class="d-inline" id="commitment-submit-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="CommitmentID" value="<?= $selectedCommitmentId ?>">
                        <button type="submit" id="commitment-submit-btn" class="btn btn-primary btn-sm">Submit For Review</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('FORWARD', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/forward-commitment" class="d-inline" id="commitment-forward-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="CommitmentID" value="<?= $selectedCommitmentId ?>">
                        <button type="submit" id="commitment-forward-btn" class="btn btn-primary btn-sm">Forward To Final Approval</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('RETURN', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/return-commitment" class="d-inline" id="commitment-return-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="CommitmentID" value="<?= $selectedCommitmentId ?>">
                        <button type="submit" id="commitment-return-btn" class="btn btn-outline-secondary btn-sm">Return</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((!$supportsWorkflowEngine && $selectedStatus === 'DRAFT' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('APPROVE', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/approve-commitment" class="d-inline" id="commitment-approve-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="CommitmentID" value="<?= $selectedCommitmentId ?>">
                        <button type="submit" id="commitment-approve-btn" class="btn btn-primary btn-sm">Approve Commitment</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((!$supportsWorkflowEngine && $selectedStatus !== 'CANCELLED' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('CANCEL', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/cancel-commitment" class="d-inline" id="commitment-cancel-form" onsubmit="return confirm('Cancel this commitment?');">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="CommitmentID" value="<?= $selectedCommitmentId ?>">
                        <button type="submit" id="commitment-cancel-btn" class="btn btn-outline-secondary btn-sm">Cancel Commitment</button>
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
                <h5 class="mb-0">Add Commitment Line</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedCommitment === null): ?>
                  <div class="text-muted">Create or open a commitment first.</div>
                <?php elseif (!$canEditSelectedCommitment): ?>
                  <div class="text-muted">Only draft commitments at the draft workflow stage can accept new lines.</div>
                <?php else: ?>
                  <form method="post" action="index.php?route=execution/save-commitment-line" id="commitment-line-form">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="CommitmentID" value="<?= $selectedCommitmentId ?>">
                    <div class="mb-3">
                      <label class="form-label" for="CommitmentExecutionOpeningBalanceID">Execution Balance Line</label>
                      <select class="form-select" name="ExecutionOpeningBalanceID" id="CommitmentExecutionOpeningBalanceID" required>
                        <option value="">Select released balance line</option>
                        <?php foreach ($balanceCandidates as $row): ?>
                          <?php $rowId = (int) ($row['ExecutionOpeningBalanceID'] ?? 0); ?>
                          <option value="<?= $rowId ?>">
                            <?= h((string) ($row['DataObjectCode'] ?? '')) ?>
                            <?= ' | Program ' . h((string) ($row['ProgramID'] ?? '-')) ?>
                            <?= ' | Economic ' . h((string) ($row['EconomicItemID'] ?? '-')) ?>
                            <?= ' | Available ' . number_format((float) ($row['AvailableCommitmentCapacity'] ?? 0), 2) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="CommitmentAmount">Commitment Amount</label>
                      <input class="form-control" type="number" name="CommitmentAmount" id="CommitmentAmount" min="0.01" step="0.01" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="CommitmentLineNotes">Notes</label>
                      <textarea class="form-control" name="Notes" id="CommitmentLineNotes" rows="3" placeholder="Optional commitment note"></textarea>
                    </div>
                    <button type="submit" id="commitment-add-line-btn" class="btn btn-primary btn-sm">Add Line</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-4 mb-4">
          <div class="card-header">
            <h5 class="mb-0">Commitment Lines</h5>
          </div>
          <div class="card-body">
            <?php if ($selectedCommitment === null): ?>
              <div class="text-muted">No commitment selected.</div>
            <?php elseif (empty($selectedCommitmentLines)): ?>
              <div class="text-muted">This commitment does not contain any active lines yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>DataScope</th>
                      <th>Program</th>
                      <th>Project</th>
                      <th>Economic</th>
                      <th>Bid Title</th>
                      <th class="text-end">Commitment</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($selectedCommitmentLines as $line): ?>
                      <tr>
                        <td><?= h((string) ($line['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($line['ProgramID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['ProjectID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['EconomicItemID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['BidTitle'] ?? '')) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($line['CommitmentAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end">
                          <?php if ($canEditSelectedCommitment): ?>
                            <form method="post" action="index.php?route=execution/delete-commitment-line" class="d-inline" id="commitment-remove-line-form-<?= h((string) ($line['CommitmentLineID'] ?? '0')) ?>" onsubmit="return confirm('Remove this commitment line?');">
                              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                              <input type="hidden" name="CommitmentID" value="<?= $selectedCommitmentId ?>">
                              <input type="hidden" name="CommitmentLineID" value="<?= h((string) ($line['CommitmentLineID'] ?? '0')) ?>">
                              <button type="submit" id="commitment-remove-line-btn-<?= h((string) ($line['CommitmentLineID'] ?? '0')) ?>" class="btn btn-outline-danger btn-sm">Remove</button>
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

        <div class="card shadow-sm mb-0">
          <div class="card-header">
            <h5 class="mb-0">Released Balance Commitment Capacity</h5>
          </div>
          <div class="card-body">
            <?php if (empty($balanceCandidates)): ?>
              <div class="text-muted">No released balances are available for commitment yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>DataScope</th>
                      <th>Program</th>
                      <th>Project</th>
                      <th>Economic</th>
                      <th>Bid Title</th>
                      <th class="text-end">Approved Released</th>
                      <th class="text-end">Planned Reserved</th>
                      <th class="text-end">Planned Committed</th>
                      <th class="text-end">Available</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($balanceCandidates as $row): ?>
                      <tr>
                        <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($row['ProgramID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['ProjectID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['EconomicItemID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['BidTitle'] ?? '')) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['ApprovedReleasedAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['PlannedReservedAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['PlannedCommitmentAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['AvailableCommitmentCapacity'] ?? 0), 2)) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
