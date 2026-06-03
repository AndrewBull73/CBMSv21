<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('reservation_status_badge_class')) {
    function reservation_status_badge_class(string $status): string
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
$selectedReservation = is_array($selectedReservation ?? null) ? $selectedReservation : null;
$selectedReservationLines = is_array($selectedReservationLines ?? null) ? $selectedReservationLines : [];
$reservations = is_array($reservations ?? null) ? $reservations : [];
$balanceCandidates = is_array($balanceCandidates ?? null) ? $balanceCandidates : [];
$summary = is_array($summary ?? null) ? $summary : [];
$openingSummary = is_array($openingSummary ?? null) ? $openingSummary : [];
$selectedReservationWorkflow = is_array($selectedReservationWorkflow ?? null) ? $selectedReservationWorkflow : [];
$supportsReservationFoundation = !empty($supportsReservationFoundation);
$supportsWarrantFoundation = !empty($supportsWarrantFoundation);
$supportsCommitmentFoundation = !empty($supportsCommitmentFoundation);
$supportsWorkflowEngine = !empty($supportsWorkflowEngine);
$csrf = h(csrf_token());
$screenHeader = [
    'title' => 'Budget Execution Reservations',
    'icon' => 'bi-bookmark-check',
];
$selectedStatus = strtoupper(trim((string) ($selectedReservation['ReservationStatusCode'] ?? '')));
$selectedReservationId = (int) ($selectedReservation['ReservationID'] ?? 0);
$selectedWorkflowInstance = is_array($selectedReservationWorkflow['Instance'] ?? null) ? $selectedReservationWorkflow['Instance'] : null;
$selectedWorkflowStage = is_array($selectedReservationWorkflow['CurrentStage'] ?? null) ? $selectedReservationWorkflow['CurrentStage'] : null;
$selectedWorkflowStageCode = strtoupper(trim((string) ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowStageName = trim((string) ($selectedWorkflowStage['WorkflowStageName'] ?? ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowHistory = is_array($selectedReservationWorkflow['History'] ?? null) ? $selectedReservationWorkflow['History'] : [];
$selectedWorkflowAllowedActions = array_map(
    static fn(array $action): string => strtoupper(trim((string) ($action['WorkflowActionCode'] ?? ''))),
    is_array($selectedReservationWorkflow['AllowedActions'] ?? null) ? $selectedReservationWorkflow['AllowedActions'] : []
);
$canEditSelectedReservation = $selectedReservation !== null
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
                <div class="text-muted small">Reservation Batches</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['ReservationCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Reservations</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['ApprovedReservationCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Reserved</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['ApprovedReservedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Remaining Reservation Capacity</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['AvailableReservationCapacityTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use Reservations to earmark approved released authority before commitments or expenditure proceed. Reservation lines reduce the available released balance for the selected execution balance line without changing the underlying authorized budget.
      </div>

      <?php if (!$supportsReservationFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Reservation tables are not installed.</strong> Run <code>create_budget_execution_reservations_v1.sql</code> before using this screen.
        </div>
      <?php endif; ?>

      <?php if (!$supportsWarrantFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Warrant tables are not installed.</strong> Reservations depend on approved warrant releases, so install <code>create_budget_execution_warrants_v1.sql</code> first.
        </div>
      <?php endif; ?>

      <?php if (!$supportsCommitmentFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Commitment tables are not installed.</strong> Reservation capacity now accounts for planned commitments, so install <code>create_budget_execution_commitments_v1.sql</code> as well.
        </div>
      <?php endif; ?>

      <?php if (!$supportsWorkflowEngine): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Shared workflow engine foundation is not installed.</strong> Run <code>create_workflow_engine_foundation_v1.sql</code> to enable multi-stage reservation workflow.
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
                <h5 class="mb-0">Create Reservation</h5>
              </div>
              <div class="card-body">
                <div class="small text-muted mb-3">Create a draft reservation batch first, then add lines against the approved released balances.</div>
                <form method="post" action="index.php?route=execution/save-reservation" id="reservation-create-form">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <div class="mb-3">
                    <label class="form-label" for="ReservationTitle">Reservation Title</label>
                    <input class="form-control" type="text" name="ReservationTitle" id="ReservationTitle" placeholder="e.g. Procurement hold for approved vehicle package" required>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label" for="ReservationDate">Reservation Date</label>
                      <input class="form-control" type="date" name="ReservationDate" id="ReservationDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="ReservationEffectiveDate">Effective Date</label>
                      <input class="form-control" type="date" name="EffectiveDate" id="ReservationEffectiveDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                  </div>
                  <div class="mt-3 mb-3">
                    <label class="form-label" for="ReservationHeaderNotes">Notes</label>
                    <textarea class="form-control" name="Notes" id="ReservationHeaderNotes" rows="3" placeholder="Optional reservation note or approval reference"></textarea>
                  </div>
                  <button type="submit" id="reservation-create-btn" class="btn btn-primary btn-sm" <?= !$supportsReservationFoundation || !$supportsWarrantFoundation || !$supportsCommitmentFoundation ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Create Reservation
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
                      <tr>
                        <td>Opening Balance Lines</td>
                        <td class="text-end"><?= h((string) ($openingSummary['LineCount'] ?? 0)) ?></td>
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
            <h5 class="mb-0">Reservation Register</h5>
          </div>
          <div class="card-body">
            <?php if (empty($reservations)): ?>
              <div class="text-muted">No reservations have been created for this execution version yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Reservation</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th class="text-end">Lines</th>
                      <th class="text-end">Amount</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                      <?php foreach ($reservations as $row): ?>
                        <?php $rowId = (int) ($row['ReservationID'] ?? 0); ?>
                        <tr>
                          <td>
                            <div class="fw-semibold"><?= h((string) ($row['ReservationNo'] ?? '')) ?></div>
                            <div class="small text-muted"><?= h((string) ($row['ReservationTitle'] ?? '')) ?></div>
                          </td>
                          <td><?= h((string) ($row['ReservationDate'] ?? '')) ?></td>
                        <?php $rowWorkflowStageName = trim((string) ($row['WorkflowStageName'] ?? '')); ?>
                        <td>
                          <span class="badge <?= h(reservation_status_badge_class((string) ($row['ReservationStatusCode'] ?? ''))) ?>"><?= h((string) ($row['ReservationStatusCode'] ?? '')) ?></span>
                          <?php if ($rowWorkflowStageName !== '' && strtoupper($rowWorkflowStageName) !== strtoupper((string) ($row['ReservationStatusCode'] ?? ''))): ?>
                            <div class="small text-muted mt-1"><?= h($rowWorkflowStageName) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-end"><?= h((string) ($row['LineCount'] ?? 0)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['TotalReservationAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end">
                          <a href="index.php?route=execution/reservations&reservation_id=<?= $rowId ?>" id="reservation-open-btn-<?= $rowId ?>" class="btn btn-outline-primary btn-sm">Open</a>
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
                <h5 class="mb-0">Selected Reservation</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedReservation === null): ?>
                  <div class="text-muted">Select a reservation from the register to review its lines and add reservations.</div>
                <?php else: ?>
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                      <div class="fw-semibold"><?= h((string) ($selectedReservation['ReservationNo'] ?? '')) ?></div>
                      <div class="text-muted"><?= h((string) ($selectedReservation['ReservationTitle'] ?? '')) ?></div>
                      <?php if ($selectedWorkflowStageName !== '' && strtoupper($selectedWorkflowStageName) !== $selectedStatus): ?>
                        <div class="small text-muted mt-1">Workflow Stage: <?= h($selectedWorkflowStageName) ?></div>
                      <?php endif; ?>
                    </div>
                    <span class="badge <?= h(reservation_status_badge_class((string) ($selectedReservation['ReservationStatusCode'] ?? ''))) ?>"><?= h((string) ($selectedReservation['ReservationStatusCode'] ?? '')) ?></span>
                  </div>

                  <div class="row g-3 mb-3">
                    <div class="col-md-4">
                      <div class="text-muted small">Reservation Date</div>
                      <div class="fw-semibold"><?= h((string) ($selectedReservation['ReservationDate'] ?? '')) ?></div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted small">Line Count</div>
                      <div class="fw-semibold"><?= h((string) ($selectedReservation['LineCount'] ?? 0)) ?></div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted small">Amount</div>
                      <div class="fw-semibold"><?= h(number_format((float) ($selectedReservation['TotalReservationAmount'] ?? 0), 2)) ?></div>
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

                  <?php if (trim((string) ($selectedReservation['Notes'] ?? '')) !== ''): ?>
                    <div class="small text-muted mb-3"><?= nl2br(h((string) ($selectedReservation['Notes'] ?? ''))) ?></div>
                  <?php endif; ?>

                  <div class="d-flex gap-2 flex-wrap mb-0">
                    <?php if ($supportsWorkflowEngine && in_array('SUBMIT', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/submit-reservation" class="d-inline" id="reservation-submit-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="ReservationID" value="<?= $selectedReservationId ?>">
                        <button type="submit" id="reservation-submit-btn" class="btn btn-primary btn-sm">Submit For Review</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('FORWARD', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/forward-reservation" class="d-inline" id="reservation-forward-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="ReservationID" value="<?= $selectedReservationId ?>">
                        <button type="submit" id="reservation-forward-btn" class="btn btn-primary btn-sm">Forward To Final Approval</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('RETURN', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/return-reservation" class="d-inline" id="reservation-return-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="ReservationID" value="<?= $selectedReservationId ?>">
                        <button type="submit" id="reservation-return-btn" class="btn btn-outline-secondary btn-sm">Return</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((!$supportsWorkflowEngine && $selectedStatus === 'DRAFT' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('APPROVE', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/approve-reservation" class="d-inline" id="reservation-approve-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="ReservationID" value="<?= $selectedReservationId ?>">
                        <button type="submit" id="reservation-approve-btn" class="btn btn-primary btn-sm">Approve Reservation</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((!$supportsWorkflowEngine && $selectedStatus !== 'CANCELLED' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('CANCEL', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/cancel-reservation" class="d-inline" id="reservation-cancel-form" onsubmit="return confirm('Cancel this reservation?');">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="ReservationID" value="<?= $selectedReservationId ?>">
                        <button type="submit" id="reservation-cancel-btn" class="btn btn-outline-secondary btn-sm">Cancel Reservation</button>
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
                <h5 class="mb-0">Add Reservation Line</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedReservation === null): ?>
                  <div class="text-muted">Create or open a reservation first.</div>
                <?php elseif (!$canEditSelectedReservation): ?>
                  <div class="text-muted">Only draft reservations at the draft workflow stage can accept new lines.</div>
                <?php else: ?>
                  <form method="post" action="index.php?route=execution/save-reservation-line" id="reservation-line-form">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="ReservationID" value="<?= $selectedReservationId ?>">
                    <div class="mb-3">
                      <label class="form-label" for="ReservationExecutionOpeningBalanceID">Execution Balance Line</label>
                      <select class="form-select" name="ExecutionOpeningBalanceID" id="ReservationExecutionOpeningBalanceID" required>
                        <option value="">Select released balance line</option>
                        <?php foreach ($balanceCandidates as $row): ?>
                          <?php $rowId = (int) ($row['ExecutionOpeningBalanceID'] ?? 0); ?>
                          <option value="<?= $rowId ?>">
                            <?= h((string) ($row['DataObjectCode'] ?? '')) ?>
                            <?= ' | Program ' . h((string) ($row['ProgramID'] ?? '-')) ?>
                            <?= ' | Economic ' . h((string) ($row['EconomicItemID'] ?? '-')) ?>
                            <?= ' | Available ' . number_format((float) ($row['AvailableReservationCapacity'] ?? 0), 2) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="ReservationAmount">Reservation Amount</label>
                      <input class="form-control" type="number" name="ReservationAmount" id="ReservationAmount" min="0.01" step="0.01" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="ReservationLineNotes">Notes</label>
                      <textarea class="form-control" name="Notes" id="ReservationLineNotes" rows="3" placeholder="Optional reservation note"></textarea>
                    </div>
                    <button type="submit" id="reservation-add-line-btn" class="btn btn-primary btn-sm">Add Line</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-4 mb-4">
          <div class="card-header">
            <h5 class="mb-0">Reservation Lines</h5>
          </div>
          <div class="card-body">
            <?php if ($selectedReservation === null): ?>
              <div class="text-muted">No reservation selected.</div>
            <?php elseif (empty($selectedReservationLines)): ?>
              <div class="text-muted">This reservation does not contain any active lines yet.</div>
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
                      <th class="text-end">Reservation</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($selectedReservationLines as $line): ?>
                      <tr>
                        <td><?= h((string) ($line['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($line['ProgramID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['ProjectID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['EconomicItemID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['BidTitle'] ?? '')) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($line['ReservationAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end">
                          <?php if ($canEditSelectedReservation): ?>
                            <form method="post" action="index.php?route=execution/delete-reservation-line" class="d-inline" id="reservation-remove-line-form-<?= h((string) ($line['ReservationLineID'] ?? '0')) ?>" onsubmit="return confirm('Remove this reservation line?');">
                              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                              <input type="hidden" name="ReservationID" value="<?= $selectedReservationId ?>">
                              <input type="hidden" name="ReservationLineID" value="<?= h((string) ($line['ReservationLineID'] ?? '0')) ?>">
                              <button type="submit" id="reservation-remove-line-btn-<?= h((string) ($line['ReservationLineID'] ?? '0')) ?>" class="btn btn-outline-danger btn-sm">Remove</button>
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
            <h5 class="mb-0">Released Balance Reservation Capacity</h5>
          </div>
          <div class="card-body">
            <?php if (empty($balanceCandidates)): ?>
              <div class="text-muted">No released balances are available for reservation yet.</div>
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
                        <td class="text-end"><?= h(number_format((float) ($row['AvailableReservationCapacity'] ?? 0), 2)) ?></td>
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
