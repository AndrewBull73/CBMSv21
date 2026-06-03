<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('supplementary_status_badge_class')) {
    function supplementary_status_badge_class(string $status): string
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
$selectedSupplementary = is_array($selectedSupplementary ?? null) ? $selectedSupplementary : null;
$selectedSupplementaryLines = is_array($selectedSupplementaryLines ?? null) ? $selectedSupplementaryLines : [];
$supplementaries = is_array($supplementaries ?? null) ? $supplementaries : [];
$balanceCandidates = is_array($balanceCandidates ?? null) ? $balanceCandidates : [];
$summary = is_array($summary ?? null) ? $summary : [];
$openingSummary = is_array($openingSummary ?? null) ? $openingSummary : [];
$selectedSupplementaryWorkflow = is_array($selectedSupplementaryWorkflow ?? null) ? $selectedSupplementaryWorkflow : [];
$supportsWorkflowEngine = !empty($supportsWorkflowEngine);
$csrf = h(csrf_token());
$screenHeader = [
    'title' => 'Budget Execution Supplementary Budgets',
    'icon' => 'bi-plus-square',
];
$selectedStatus = strtoupper(trim((string) ($selectedSupplementary['SupplementaryStatusCode'] ?? '')));
$selectedSupplementaryId = (int) ($selectedSupplementary['SupplementaryBudgetID'] ?? 0);
$selectedWorkflowInstance = is_array($selectedSupplementaryWorkflow['Instance'] ?? null) ? $selectedSupplementaryWorkflow['Instance'] : null;
$selectedWorkflowStage = is_array($selectedSupplementaryWorkflow['CurrentStage'] ?? null) ? $selectedSupplementaryWorkflow['CurrentStage'] : null;
$selectedWorkflowStageCode = strtoupper(trim((string) ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowStageName = trim((string) ($selectedWorkflowStage['WorkflowStageName'] ?? ($selectedWorkflowInstance['CurrentStageCode'] ?? '')));
$selectedWorkflowHistory = is_array($selectedSupplementaryWorkflow['History'] ?? null) ? $selectedSupplementaryWorkflow['History'] : [];
$selectedWorkflowAllowedActions = array_map(
    static fn(array $action): string => strtoupper(trim((string) ($action['WorkflowActionCode'] ?? ''))),
    is_array($selectedSupplementaryWorkflow['AllowedActions'] ?? null) ? $selectedSupplementaryWorkflow['AllowedActions'] : []
);
$canEditSelectedSupplementary = $selectedSupplementary !== null
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
                <div class="text-muted small">Supplementary Batches</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['SupplementaryCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Batches</div>
                <div class="fs-4 fw-semibold"><?= h((string) ($summary['ApprovedSupplementaryCount'] ?? 0)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Approved Net Adjustment</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($summary['ApprovedNetAdjustmentTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card shadow-sm h-100">
              <div class="card-body">
                <div class="text-muted small">Current Authorized</div>
                <div class="fs-4 fw-semibold"><?= h(number_format((float) ($openingSummary['CurrentAuthorizedAmountTotal'] ?? 0), 2)) ?></div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use Supplementary Budgets to formally increase or reduce the execution budget after rollover. Approved lines adjust the current authorized budget for the selected execution balance lines, while warrants continue to release authority from that updated amount.
      </div>

      <?php if (!$supportsSupplementaryFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Supplementary budget tables are not installed.</strong> Run <code>create_budget_execution_supplementaries_v1.sql</code> before using this screen.
        </div>
      <?php endif; ?>

      <?php if (!$supportsWorkflowEngine): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Shared workflow engine foundation is not installed.</strong> Run <code>create_workflow_engine_foundation_v1.sql</code> to enable multi-stage supplementary workflow.
        </div>
      <?php endif; ?>

      <?php if (!$supportsExecutionFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Execution foundation tables are not installed.</strong> Run <code>create_budget_execution_foundation_v1.sql</code> before using Supplementary Budgets.
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
                <h5 class="mb-0">Create Supplementary Budget</h5>
              </div>
              <div class="card-body">
                <div class="small text-muted mb-3">Create a draft supplementary budget batch first, then add increase or reduction lines before approval.</div>
                <form method="post" action="index.php?route=execution/save-supplementary" id="supplementary-create-form">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <div class="mb-3">
                    <label class="form-label" for="SupplementaryTitle">Supplementary Title</label>
                    <input class="form-control" type="text" name="SupplementaryTitle" id="SupplementaryTitle" placeholder="e.g. Mid-year appropriation adjustment" required>
                  </div>
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label" for="SupplementaryDate">Supplementary Date</label>
                      <input class="form-control" type="date" name="SupplementaryDate" id="SupplementaryDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label" for="SupplementaryEffectiveDate">Effective Date</label>
                      <input class="form-control" type="date" name="EffectiveDate" id="SupplementaryEffectiveDate" value="<?= h(date('Y-m-d')) ?>">
                    </div>
                  </div>
                  <div class="mt-3 mb-3">
                    <label class="form-label" for="SupplementaryHeaderNotes">Notes</label>
                    <textarea class="form-control" name="Notes" id="SupplementaryHeaderNotes" rows="3" placeholder="Optional reference or approval note"></textarea>
                  </div>
                  <button type="submit" id="supplementary-create-btn" class="btn btn-primary btn-sm" <?= !$supportsSupplementaryFoundation ? 'disabled' : '' ?>>
                    <i class="bi bi-plus-circle me-1"></i>Create Supplementary Budget
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12 col-xl-7">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Authorized Budget Position</h5>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0" id="supplementary-register-table">
                    <thead class="table-light">
                      <tr>
                        <th>Measure</th>
                        <th class="text-end">Amount</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr>
                        <td>Opening Amount</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['OpeningAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                      <tr>
                        <td>Approved Supplementary Net Change</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['SupplementaryAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                      <tr>
                        <td>Current Authorized Budget</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['CurrentAuthorizedAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                      <tr>
                        <td>Planned Warrant Released</td>
                        <td class="text-end"><?= h(number_format((float) ($openingSummary['ReleasedAmountTotal'] ?? 0), 2)) ?></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div class="small text-muted mt-3">Positive line amounts increase authorized budget. Negative line amounts reduce it, but approval is blocked if the reduction would take a balance line below its planned released authority.</div>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mb-4">
          <div class="card-header">
            <h5 class="mb-0">Supplementary Register</h5>
          </div>
          <div class="card-body">
            <?php if (empty($supplementaries)): ?>
              <div class="text-muted">No supplementary budgets have been created for this execution version yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Supplementary</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th class="text-end">Lines</th>
                      <th class="text-end">Net Adjustment</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($supplementaries as $row): ?>
                      <?php $rowId = (int) ($row['SupplementaryBudgetID'] ?? 0); ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($row['SupplementaryNo'] ?? '')) ?></div>
                          <div class="small text-muted"><?= h((string) ($row['SupplementaryTitle'] ?? '')) ?></div>
                        </td>
                        <td><?= h((string) ($row['SupplementaryDate'] ?? '')) ?></td>
                        <?php $rowWorkflowStageName = trim((string) ($row['WorkflowStageName'] ?? '')); ?>
                        <td>
                          <span class="badge <?= h(supplementary_status_badge_class((string) ($row['SupplementaryStatusCode'] ?? ''))) ?>"><?= h((string) ($row['SupplementaryStatusCode'] ?? '')) ?></span>
                          <?php if ($rowWorkflowStageName !== '' && strtoupper($rowWorkflowStageName) !== strtoupper((string) ($row['SupplementaryStatusCode'] ?? ''))): ?>
                            <div class="small text-muted mt-1"><?= h($rowWorkflowStageName) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-end"><?= h((string) ($row['LineCount'] ?? 0)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['TotalAdjustmentAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end">
                          <a href="index.php?route=execution/supplementaries&supplementary_id=<?= $rowId ?>" id="supplementary-open-btn-<?= $rowId ?>" class="btn btn-outline-primary btn-sm">Open</a>
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
                <h5 class="mb-0">Selected Supplementary Budget</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedSupplementary === null): ?>
                  <div class="text-muted">Select a supplementary budget from the register to review its lines and approve it.</div>
                <?php else: ?>
                  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
                    <div>
                      <div class="fw-semibold"><?= h((string) ($selectedSupplementary['SupplementaryNo'] ?? '')) ?></div>
                      <div class="text-muted"><?= h((string) ($selectedSupplementary['SupplementaryTitle'] ?? '')) ?></div>
                      <?php if ($selectedWorkflowStageName !== '' && strtoupper($selectedWorkflowStageName) !== $selectedStatus): ?>
                        <div class="small text-muted mt-1">Workflow Stage: <?= h($selectedWorkflowStageName) ?></div>
                      <?php endif; ?>
                    </div>
                    <span class="badge <?= h(supplementary_status_badge_class((string) ($selectedSupplementary['SupplementaryStatusCode'] ?? ''))) ?>"><?= h((string) ($selectedSupplementary['SupplementaryStatusCode'] ?? '')) ?></span>
                  </div>

                  <div class="row g-3 mb-3">
                    <div class="col-md-4">
                      <div class="text-muted small">Supplementary Date</div>
                      <div class="fw-semibold"><?= h((string) ($selectedSupplementary['SupplementaryDate'] ?? '')) ?></div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted small">Line Count</div>
                      <div class="fw-semibold"><?= h((string) ($selectedSupplementary['LineCount'] ?? 0)) ?></div>
                    </div>
                    <div class="col-md-4">
                      <div class="text-muted small">Net Adjustment</div>
                      <div class="fw-semibold"><?= h(number_format((float) ($selectedSupplementary['TotalAdjustmentAmount'] ?? 0), 2)) ?></div>
                    </div>
                  </div>

                  <div class="row g-3 mb-3">
                    <div class="col-md-6">
                      <div class="text-muted small">Total Increases</div>
                      <div class="fw-semibold"><?= h(number_format((float) ($selectedSupplementary['TotalIncreaseAmount'] ?? 0), 2)) ?></div>
                    </div>
                    <div class="col-md-6">
                      <div class="text-muted small">Total Reductions</div>
                      <div class="fw-semibold"><?= h(number_format((float) ($selectedSupplementary['TotalReductionAmount'] ?? 0), 2)) ?></div>
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

                  <?php if (trim((string) ($selectedSupplementary['Notes'] ?? '')) !== ''): ?>
                    <div class="small text-muted mb-3"><?= nl2br(h((string) ($selectedSupplementary['Notes'] ?? ''))) ?></div>
                  <?php endif; ?>

                  <div class="d-flex gap-2 flex-wrap mb-0">
                    <?php if ($supportsWorkflowEngine && in_array('SUBMIT', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/submit-supplementary" class="d-inline" id="supplementary-submit-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="SupplementaryBudgetID" value="<?= $selectedSupplementaryId ?>">
                        <button type="submit" id="supplementary-submit-btn" class="btn btn-primary btn-sm">Submit For Review</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('FORWARD', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/forward-supplementary" class="d-inline" id="supplementary-forward-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="SupplementaryBudgetID" value="<?= $selectedSupplementaryId ?>">
                        <button type="submit" id="supplementary-forward-btn" class="btn btn-primary btn-sm">Forward To Final Approval</button>
                      </form>
                    <?php endif; ?>

                    <?php if ($supportsWorkflowEngine && $executionReviewer && in_array('RETURN', $selectedWorkflowAllowedActions, true)): ?>
                      <form method="post" action="index.php?route=execution/return-supplementary" class="d-inline" id="supplementary-return-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="SupplementaryBudgetID" value="<?= $selectedSupplementaryId ?>">
                        <button type="submit" id="supplementary-return-btn" class="btn btn-outline-secondary btn-sm">Return</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((!$supportsWorkflowEngine && $selectedStatus === 'DRAFT' && $executionReviewer) || ($supportsWorkflowEngine && $executionReviewer && in_array('APPROVE', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/approve-supplementary" class="d-inline" id="supplementary-approve-form">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="SupplementaryBudgetID" value="<?= $selectedSupplementaryId ?>">
                        <button type="submit" id="supplementary-approve-btn" class="btn btn-primary btn-sm">Approve Supplementary</button>
                      </form>
                    <?php endif; ?>

                    <?php if ((!$supportsWorkflowEngine && $selectedStatus !== 'CANCELLED') || ($supportsWorkflowEngine && $executionReviewer && in_array('CANCEL', $selectedWorkflowAllowedActions, true))): ?>
                      <form method="post" action="index.php?route=execution/cancel-supplementary" class="d-inline" id="supplementary-cancel-form" onsubmit="return confirm('Cancel this supplementary budget?');">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="SupplementaryBudgetID" value="<?= $selectedSupplementaryId ?>">
                        <button type="submit" id="supplementary-cancel-btn" class="btn btn-outline-secondary btn-sm">Cancel Supplementary</button>
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
                <h5 class="mb-0">Add Supplementary Line</h5>
              </div>
              <div class="card-body">
                <?php if ($selectedSupplementary === null): ?>
                  <div class="text-muted">Create or open a supplementary budget first.</div>
                <?php elseif (!$canEditSelectedSupplementary): ?>
                  <div class="text-muted">Only draft supplementary budgets can accept new lines.</div>
                <?php else: ?>
                  <form method="post" action="index.php?route=execution/save-supplementary-line" id="supplementary-line-form">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="SupplementaryBudgetID" value="<?= $selectedSupplementaryId ?>">
                    <div class="mb-3">
                      <label class="form-label" for="SupplementaryExecutionOpeningBalanceID">Execution Balance Line</label>
                      <select class="form-select" name="ExecutionOpeningBalanceID" id="SupplementaryExecutionOpeningBalanceID" required>
                        <option value="">Select opening balance line</option>
                        <?php foreach ($balanceCandidates as $row): ?>
                          <?php $rowId = (int) ($row['ExecutionOpeningBalanceID'] ?? 0); ?>
                          <?php $reductionHeadroom = (float) ($row['CurrentAuthorizedAmount'] ?? 0) - (float) ($row['PlannedReleaseAmountTotal'] ?? 0); ?>
                          <option value="<?= $rowId ?>">
                            <?= h((string) ($row['DataObjectCode'] ?? '')) ?>
                            <?= ' | Program ' . h((string) ($row['ProgramID'] ?? '-')) ?>
                            <?= ' | Current ' . number_format((float) ($row['CurrentAuthorizedAmount'] ?? 0), 2) ?>
                            <?= ' | Reduction headroom ' . number_format($reductionHeadroom, 2) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="mb-3">
                      <label class="form-label" for="SupplementaryAdjustmentAmount">Adjustment Amount</label>
                      <input class="form-control" type="number" name="AdjustmentAmount" id="SupplementaryAdjustmentAmount" step="0.01" required>
                    </div>
                    <div class="small text-muted mb-3">Use a positive amount for an increase and a negative amount for a reduction.</div>
                    <div class="mb-3">
                      <label class="form-label" for="SupplementaryLineNotes">Notes</label>
                      <textarea class="form-control" name="Notes" id="SupplementaryLineNotes" rows="3" placeholder="Optional line note"></textarea>
                    </div>
                    <button type="submit" id="supplementary-add-line-btn" class="btn btn-primary btn-sm">Add Line</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card shadow-sm mt-4 mb-4">
          <div class="card-header">
            <h5 class="mb-0">Supplementary Lines</h5>
          </div>
          <div class="card-body">
            <?php if ($selectedSupplementary === null): ?>
              <div class="text-muted">No supplementary budget selected.</div>
            <?php elseif (empty($selectedSupplementaryLines)): ?>
              <div class="text-muted">This supplementary budget does not contain any active lines yet.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="supplementary-lines-table">
                  <thead class="table-light">
                    <tr>
                      <th>DataScope</th>
                      <th>Program</th>
                      <th>Project</th>
                      <th>Economic</th>
                      <th>Bid Title</th>
                      <th class="text-end">Adjustment</th>
                      <th class="text-end">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($selectedSupplementaryLines as $line): ?>
                      <tr>
                        <td><?= h((string) ($line['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($line['ProgramID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['ProjectID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['EconomicItemID'] ?? '')) ?></td>
                        <td><?= h((string) ($line['BidTitle'] ?? '')) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($line['AdjustmentAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end">
                          <?php if ($canEditSelectedSupplementary): ?>
                            <form method="post" action="index.php?route=execution/delete-supplementary-line" class="d-inline" id="supplementary-remove-line-form-<?= h((string) ($line['SupplementaryBudgetLineID'] ?? '0')) ?>" onsubmit="return confirm('Remove this supplementary line?');">
                              <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                              <input type="hidden" name="SupplementaryBudgetID" value="<?= $selectedSupplementaryId ?>">
                              <input type="hidden" name="SupplementaryBudgetLineID" value="<?= h((string) ($line['SupplementaryBudgetLineID'] ?? '0')) ?>">
                              <button type="submit" id="supplementary-remove-line-btn-<?= h((string) ($line['SupplementaryBudgetLineID'] ?? '0')) ?>" class="btn btn-outline-danger btn-sm">Remove</button>
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
            <h5 class="mb-0">Execution Balance Adjustment Capacity</h5>
          </div>
          <div class="card-body">
            <?php if (empty($balanceCandidates)): ?>
              <div class="text-muted">No opening balances are available for adjustment yet.</div>
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
                      <th class="text-end">Opening</th>
                      <th class="text-end">Current Authorized</th>
                      <th class="text-end">Planned Released</th>
                      <th class="text-end">Reduction Headroom</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($balanceCandidates as $row): ?>
                      <?php $reductionHeadroom = (float) ($row['CurrentAuthorizedAmount'] ?? 0) - (float) ($row['PlannedReleaseAmountTotal'] ?? 0); ?>
                      <tr>
                        <td><?= h((string) ($row['DataObjectCode'] ?? '')) ?></td>
                        <td><?= h((string) ($row['ProgramID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['ProjectID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['EconomicItemID'] ?? '')) ?></td>
                        <td><?= h((string) ($row['BidTitle'] ?? '')) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['OpeningAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['CurrentAuthorizedAmount'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format((float) ($row['PlannedReleaseAmountTotal'] ?? 0), 2)) ?></td>
                        <td class="text-end"><?= h(number_format($reductionHeadroom, 2)) ?></td>
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
