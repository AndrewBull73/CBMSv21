<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('warrant_status_badge_class')) {
    function warrant_status_badge_class(string $status): string
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
$selectedWarrant = is_array($selectedWarrant ?? null) ? $selectedWarrant : null;
$selectedWarrantLines = is_array($selectedWarrantLines ?? null) ? $selectedWarrantLines : [];
$warrants = is_array($warrants ?? null) ? $warrants : [];
$balanceCandidates = is_array($balanceCandidates ?? null) ? $balanceCandidates : [];
$summary = is_array($summary ?? null) ? $summary : [];
$openingSummary = is_array($openingSummary ?? null) ? $openingSummary : [];
$selectedWarrantWorkflow = is_array($selectedWarrantWorkflow ?? null) ? $selectedWarrantWorkflow : [];
$supportsWorkflowEngine = !empty($supportsWorkflowEngine);
$csrf = h(csrf_token());
$screenHeader = [
    'title' => 'Budget Execution Warrants',
    'icon' => 'bi-check2-square',
];
$selectedStatus = strtoupper(trim((string) ($selectedWarrant['WarrantStatusCode'] ?? '')));
$selectedWarrantId = (int) ($selectedWarrant['WarrantID'] ?? 0);
$selectedWorkflowInstance = is_array($selectedWarrantWorkflow['Instance'] ?? null) ? $selectedWarrantWorkflow['Instance'] : null;
$selectedWorkflowStage = is_array($selectedWarrantWorkflow['CurrentStage'] ?? null) ? $selectedWarrantWorkflow['CurrentStage'] : null;
$selectedWorkflowStageCode = strtoupper(trim((string) ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowStageName = trim((string) ($selectedWorkflowStage['WorkflowStageName'] ?? ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowHistory = is_array($selectedWarrantWorkflow['History'] ?? null) ? $selectedWarrantWorkflow['History'] : [];
$selectedWorkflowAllowedActions = array_map(
    static fn(array $action): string => strtoupper(trim((string) ($action['WorkflowActionCode'] ?? ''))),
    is_array($selectedWarrantWorkflow['AllowedActions'] ?? null) ? $selectedWarrantWorkflow['AllowedActions'] : []
);
$canEditSelectedWarrant = $selectedWarrant !== null
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
                <div class="text-muted small">Warrant Batches</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['WarrantCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Warrants</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['ApprovedWarrantCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Released</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['ApprovedReleasedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Remaining Warrant Capacity</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['RemainingWarrantCapacityTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use Warrants to release budget authority from the execution baseline before downstream reservations, commitments, and spending activity begin. Each warrant line draws against an opening balance line and reduces the remaining releasable amount for that balance.
      </div>

      <?php if (!$supportsWarrantFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Warrant tables are not installed.</strong> Run <code>create_budget_execution_warrants_v1.sql</code> before using this screen.
        </div>
      <?php endif; ?>

      <?php if (!$supportsExecutionFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Execution foundation tables are not installed.</strong> Run <code>create_budget_execution_foundation_v1.sql</code> before using Warrants.
        </div>
      <?php endif; ?>

      <?php if (!$supportsWorkflowEngine): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Shared workflow engine foundation is not installed.</strong> Run <code>create_workflow_engine_foundation_v1.sql</code> to enable multi-stage warrant workflow.
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
                <h5 class="mb-0">Create Warrant</h5>
              </div>
              <div class="card-body">
                <div class="small text-muted mb-3">Create a draft warrant batch first, then add release lines against the execution opening balances.</div>
                <form method="post" action="index.php?route=execution/save-warrant" id="warrant-create-form">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <div class="mb-3">
                    <label class="form-label" for="WarrantTitle">Warrant Title</label>
                    <input class="form-control" type="text" name="WarrantTitle" id="WarrantTitle" placeholder="e.g. Quarter 1 release for core operations" required>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label" for="WarrantDate">Warrant Date</label>
                      <input class="form-control" type="date" name="WarrantDate" id="WarrantDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="WarrantEffectiveDate">Effective Date</label>
                      <input class="form-control" type="date" name="EffectiveDate" id="WarrantEffectiveDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                  </div>
                  <div class="mt-3 mb-3">
                    <label class="form-label" for="WarrantHeaderNotes">Notes</label>
                    <textarea class="form-control" name="Notes" id="WarrantHeaderNotes" rows="3" placeholder="Optional release note or authority reference"></textarea>
                  </div>
                  <button type="submit" id="warrant-create-btn" class="btn btn-primary btn-sm" <?= !$supportsWarrantFoundation ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Create Warrant
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12 col-xl-7">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Execution Release Position</h5>
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
                        <td>Current Authorized Budget</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['CurrentAuthorizedAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                      <tr>
                        <td>Approved Released Through Warrants</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['ReleasedAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                      <tr>
                        <td>Planned Warrant Capacity Remaining</td>
                        <td class="text-end"><?= h(number_format((float) ($summary['RemainingWarrantCapacityTotal'] ?? 0), 2)) ?></td>
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
            <h5 class="mb-0">Warrant Register</h5>
          </div>
          <div class="card-body">
            <?php if (empty($warrants)): ?>
              <div class="text-muted">No warrants have been created for this execution version yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Warrant</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th class="text-end">Lines</th>
                      <th class="text-end">Release</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($warrants as $row): ?>
                      <?php $rowId = (int) ($row['WarrantID'] ?? 0); ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($row['WarrantNo'] ?? '')) ?></div>
                          <div class="small text-muted"><?= h((string) ($row['WarrantTitle'] ?? '')) ?></div>
                        </td>
                        <td><?= h((string) ($row['WarrantDate'] ?? '')) ?></td>
                        <?php $rowWorkflowStageName = trim((string) ($row['WorkflowStageName'] ?? '')); ?>
                        <td>
                          <span class="badge <?= h(warrant_status_badge_class((string) ($row['WarrantStatusCode'] ?? ''))) ?>"><?= h((string) ($row['WarrantStatusCode'] ?? '')) ?></span>
                          <?php if ($rowWorkflowStageName !== '' && strtoupper($rowWorkflowStageName) !== strtoupper((string) ($row['WarrantStatusCode'] ?? ''))): ?>
                            <div class="small text-muted mt-1"><?= h($rowWorkflowStageName) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-end"><?= h((string) ($row['LineCount'] ?? 0)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['TotalReleaseAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end">
                          <a href="index.php?route=execution/warrants&warrant_id=<?= $rowId ?>" id="warrant-open-btn-<?= $rowId ?>" class="btn btn-outline-primary btn-sm">Open</a>
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
                <h5 class="mb-0">Selected Warrant</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedWarrant === null): ?>
                  <div class="text-muted">Select a warrant from the register to review its lines and add releases.</div>
                <?php else: ?>
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                      <div class="fw-semibold"><?= h((string) ($selectedWarrant['WarrantNo'] ?? '')) ?></div>
                      <div class="text-muted"><?= h((string) ($selectedWarrant['WarrantTitle'] ?? '')) ?></div>
                      <?php if ($selectedWorkflowStageName !== '' && strtoupper($selectedWorkflowStageName) !== $selectedStatus): ?>
                        <div class="small text-muted mt-1">Workflow Stage: <?= h($selectedWorkflowStageName) ?></div>
                      <?php endif; ?>
                    </div>
                    <span class="badge <?= h(warrant_status_badge_class((string) ($selectedWarrant['WarrantStatusCode'] ?? ''))) ?>"><?= h((string) ($selectedWarrant['WarrantStatusCode'] ?? '')) ?></span>
                  </div>

                  <div class="row g-3 mb-3">
                    <div class="col-md-4">
                      <div class="text-muted small">Warrant Date</div>
                      <div class="fw-semibold"><?= h((string) ($selectedWarrant['WarrantDate'] ?? '')) ?></div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted small">Line Count</div>
                      <div class="fw-semibold"><?= h((string) ($selectedWarrant['LineCount'] ?? 0)) ?></div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted small">Release Total</div>
                      <div class="fw-semibold"><?= h(number_format((float) ($selectedWarrant['TotalReleaseAmount'] ?? 0), 2)) ?></div>
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

                  <?php if (trim((string) ($selectedWarrant['Notes'] ?? '')) !== ''): ?>
                    <div class="small text-muted mb-3"><?= nl2br(h((string) ($selectedWarrant['Notes'] ?? ''))) ?></div>
                  <?php endif; ?>

                  <div class="d-flex gap-2 flex-wrap mb-0">
                    <?php if ($supportsWorkflowEngine && in_array('SUBMIT', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/submit-warrant" class="d-inline" id="warrant-submit-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="WarrantID" value="<?= $selectedWarrantId ?>">
                        <button type="submit" id="warrant-submit-btn" class="btn btn-primary btn-sm">Submit For Review</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('FORWARD', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/forward-warrant" class="d-inline" id="warrant-forward-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="WarrantID" value="<?= $selectedWarrantId ?>">
                        <button type="submit" id="warrant-forward-btn" class="btn btn-primary btn-sm">Forward To Final Approval</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('RETURN', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/return-warrant" class="d-inline" id="warrant-return-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="WarrantID" value="<?= $selectedWarrantId ?>">
                        <button type="submit" id="warrant-return-btn" class="btn btn-outline-secondary btn-sm">Return</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((!$supportsWorkflowEngine && $selectedStatus === 'DRAFT' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('APPROVE', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/approve-warrant" class="d-inline" id="warrant-approve-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="WarrantID" value="<?= $selectedWarrantId ?>">
                        <button type="submit" id="warrant-approve-btn" class="btn btn-primary btn-sm">Approve Warrant</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((!$supportsWorkflowEngine && $selectedStatus !== 'CANCELLED' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('CANCEL', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/cancel-warrant" class="d-inline" id="warrant-cancel-form" onsubmit="return confirm('Cancel this warrant?');">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="WarrantID" value="<?= $selectedWarrantId ?>">
                        <button type="submit" id="warrant-cancel-btn" class="btn btn-outline-secondary btn-sm">Cancel Warrant</button>
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
                <h5 class="mb-0">Add Warrant Line</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedWarrant === null): ?>
                  <div class="text-muted">Create or open a warrant first.</div>
                <?php elseif (!$canEditSelectedWarrant): ?>
                  <div class="text-muted">Only draft warrants at the draft workflow stage can accept new lines.</div>
                <?php else: ?>
                  <form method="post" action="index.php?route=execution/save-warrant-line" id="warrant-line-form">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="WarrantID" value="<?= $selectedWarrantId ?>">
                    <div class="mb-3">
                      <label class="form-label" for="WarrantExecutionOpeningBalanceID">Execution Balance Line</label>
                      <select class="form-select" name="ExecutionOpeningBalanceID" id="WarrantExecutionOpeningBalanceID" required>
                        <option value="">Select opening balance line</option>
                        <?php foreach ($balanceCandidates as $row): ?>
                          <?php $rowId = (int) ($row['ExecutionOpeningBalanceID'] ?? 0); ?>
                          <option value="<?= $rowId ?>">
                            <?= h((string) ($row['DataObjectCode'] ?? '')) ?>
                            <?= ' | Program ' . h((string) ($row['ProgramID'] ?? '-')) ?>
                            <?= ' | Economic ' . h((string) ($row['EconomicItemID'] ?? '-')) ?>
                            <?= ' | Remaining ' . number_format((float) ($row['RemainingWarrantCapacity'] ?? 0), 2) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="WarrantReleaseAmount">Release Amount</label>
                      <input class="form-control" type="number" name="ReleaseAmount" id="WarrantReleaseAmount" min="0.01" step="0.01" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="WarrantLineNotes">Notes</label>
                      <textarea class="form-control" name="Notes" id="WarrantLineNotes" rows="3" placeholder="Optional line note"></textarea>
                    </div>
                    <button type="submit" id="warrant-add-line-btn" class="btn btn-primary btn-sm">Add Line</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-4 mb-4">
          <div class="card-header">
            <h5 class="mb-0">Warrant Lines</h5>
          </div>
          <div class="card-body">
            <?php if ($selectedWarrant === null): ?>
              <div class="text-muted">No warrant selected.</div>
            <?php elseif (empty($selectedWarrantLines)): ?>
              <div class="text-muted">This warrant does not contain any active lines yet.</div>
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
                      <th class="text-end">Release</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($selectedWarrantLines as $line): ?>
                      <tr>
                        <td><?= h((string) ($line['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($line['ProgramID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['ProjectID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['EconomicItemID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['BidTitle'] ?? '')) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($line['ReleaseAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end">
                          <?php if ($canEditSelectedWarrant): ?>
                            <form method="post" action="index.php?route=execution/delete-warrant-line" class="d-inline" id="warrant-remove-line-form-<?= h((string) ($line['WarrantLineID'] ?? '0')) ?>" onsubmit="return confirm('Remove this warrant line?');">
                              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                              <input type="hidden" name="WarrantID" value="<?= $selectedWarrantId ?>">
                              <input type="hidden" name="WarrantLineID" value="<?= h((string) ($line['WarrantLineID'] ?? '0')) ?>">
                              <button type="submit" id="warrant-remove-line-btn-<?= h((string) ($line['WarrantLineID'] ?? '0')) ?>" class="btn btn-outline-danger btn-sm">Remove</button>
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
            <h5 class="mb-0">Execution Balance Release Capacity</h5>
          </div>
          <div class="card-body">
            <?php if (empty($balanceCandidates)): ?>
              <div class="text-muted">No opening balances are available for release yet.</div>
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
                      <th class="text-end">Current Authorized</th>
                      <th class="text-end">Planned Warranted</th>
                      <th class="text-end">Remaining</th>
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
                        <td class="text-end"><?= h(number_format((float) ($row['CurrentAuthorizedAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['PlannedReleaseAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['RemainingWarrantCapacity'] ?? 0), 2)) ?></td>
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
