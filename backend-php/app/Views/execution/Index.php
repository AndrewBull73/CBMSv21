<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$ctx = is_array($context ?? null) ? $context : [];
$fy = (int) ($ctx['FiscalYearID'] ?? 0);
$ver = (int) ($ctx['VersionID'] ?? 0);
$currentTypeCode = strtoupper(trim((string) ($currentVersion['VersionTypeCode'] ?? '')));
$isCurrentExecution = $currentTypeCode === 'EXECUTION';
$csrf = h(csrf_token());
$currentVersionLabel = trim((string) ($currentVersion['VersionLabel'] ?? ''));
$screenHeader = [
    'title' => 'Budget Execution Setup',
    'icon' => 'bi-gear-wide-connected',
];
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

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Execution Versions</div>
              <div class="fs-4 fw-semibold"><?= count($executionVersions) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Submission Versions</div>
              <div class="fs-4 fw-semibold"><?= count($submissionVersions) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Rollover Runs</div>
              <div class="fs-4 fw-semibold"><?= count($rolloverRuns) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Execution Context</div>
              <div class="fs-4 fw-semibold <?= $isCurrentExecution ? 'text-success' : 'text-warning' ?>">
                <?= $isCurrentExecution ? 'Ready' : 'Review' ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this screen to create the execution version, link it back to the approved submission baseline, and run the initial rollover before testing warrants, transfers, reservations, commitments, or other Budget Execution transactions.
      </div>

      <?php if (!$supportsVersionTyping): ?>
        <div class="alert alert-danger border-0 shadow-sm mb-4">
          <strong>Version typing is not installed.</strong> Run <code>alter_tblVersions_budget_versioning_v1.sql</code> before using Budget Execution setup.
        </div>
      <?php endif; ?>

      <?php if (!$supportsExecutionFoundation): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          <strong>Execution foundation tables are not installed.</strong> Run <code>create_budget_execution_foundation_v1.sql</code> before using rollover and opening balances.
        </div>
      <?php endif; ?>

      <?php if (!$isCurrentExecution): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          The current context is not an execution version. Select one of the execution versions below before running opening-balance inquiries or testing execution transactions.
        </div>
      <?php endif; ?>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Execution Versions</h5>
        </div>
        <div class="card-body">
          <?php if (empty($executionVersions)): ?>
            <div class="text-muted">No execution versions exist yet for this fiscal year.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Default</th>
                    <th>Source</th>
                    <th class="text-end">Opening</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($executionVersions as $row): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) ($row['VersionLabel'] ?? '')) ?></div>
                        <div class="small text-muted">Version <?= h((string) ($row['VersionID'] ?? '')) ?></div>
                      </td>
                      <td><?= h((string) ($row['VersionStatus'] ?? '')) ?></td>
                      <td><?= (int) ($row['IsDefault'] ?? 0) === 1 ? 'Yes' : 'No' ?></td>
                      <td><?= h((string) (($row['SourceVersionLabel'] ?? '') !== '' ? $row['SourceVersionLabel'] : '-')) ?></td>
                      <td class="text-end">
                        <?php if (array_key_exists('OpeningAmountTotal', $row)): ?>
                          <?= h(number_format((float) ($row['OpeningAmountTotal'] ?? 0), 2)) ?>
                        <?php else: ?>
                          -
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <form method="post" action="index.php?route=execution/use-version" class="d-inline">
                          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                          <input type="hidden" name="FiscalYearID" value="<?= h((string) $fy) ?>">
                          <input type="hidden" name="VersionID" value="<?= h((string) ($row['VersionID'] ?? '0')) ?>">
                          <button type="submit" class="btn btn-outline-primary btn-sm">Use In Context</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($executionAdmin): ?>
        <div class="row g-3 mb-4">
          <div class="col-12 col-xl-5">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Create Execution Version</h5>
              </div>
              <div class="card-body">
                <div class="small text-muted mb-3">Create the live execution ledger for this fiscal year. Most clients will have one active execution version at a time.</div>
                <form method="post" action="index.php?route=execution/save-version">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <div class="mb-3">
                    <label class="form-label">Version Label</label>
                    <input class="form-control" type="text" name="VersionLabel" placeholder="e.g. 2026 Execution Version 1" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="VersionStatus">
                      <option value="OPEN">OPEN</option>
                      <option value="ACTIVE">ACTIVE</option>
                      <option value="SUSPENDED">SUSPENDED</option>
                      <option value="CLOSED">CLOSED</option>
                    </select>
                  </div>
                  <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="IsDefault" id="IsDefault">
                    <label class="form-check-label" for="IsDefault">Set as default execution version for this fiscal year</label>
                  </div>
                  <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Create Execution Version
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-12 col-xl-7">
            <div class="card shadow-sm h-100">
              <div class="card-header">
                <h5 class="mb-0">Initialize Execution Version</h5>
              </div>
              <div class="card-body">
                <div class="small text-muted mb-3">Rollover copies approved current-year submission lines into opening execution balances. This should normally be done once before live execution testing begins.</div>
                <form method="post" action="index.php?route=execution/run-rollover">
                  <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Target Execution Version</label>
                      <select class="form-select" name="ExecutionVersionID" required>
                        <option value="">Select execution version</option>
                        <?php foreach ($executionVersions as $row): ?>
                          <option value="<?= h((string) ($row['VersionID'] ?? '')) ?>" <?= (int) ($row['VersionID'] ?? 0) === $ver ? 'selected' : '' ?>>
                            <?= h((string) ($row['VersionLabel'] ?? '')) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Source Submission Version</label>
                      <select class="form-select" name="SourceVersionID" required>
                        <option value="">Select submission version</option>
                        <?php foreach ($submissionVersions as $row): ?>
                          <option value="<?= h((string) ($row['VersionID'] ?? '')) ?>">
                            <?= h((string) ($row['VersionLabel'] ?? '')) ?>
                            <?= ' | Approved Lines: ' . (int) ($row['ApprovedLineCount'] ?? 0) ?>
                            <?= ' | Approved Amount: ' . number_format((float) ($row['ApprovedAmount'] ?? 0), 2) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="mt-3">
                    <button type="submit" class="btn btn-primary btn-sm" <?= empty($executionVersions) || empty($submissionVersions) || !$supportsExecutionFoundation ? 'disabled' : '' ?>>
                      <i class="bi bi-arrow-repeat me-1"></i>Run Rollover
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Recent Rollover Runs</h5>
        </div>
        <div class="card-body">
          <?php if (empty($rolloverRuns)): ?>
            <div class="text-muted">No rollover runs have been recorded yet.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Started</th>
                    <th>Execution Version</th>
                    <th>Source Version</th>
                    <th>Status</th>
                    <th class="text-end">Lines</th>
                    <th class="text-end">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rolloverRuns as $row): ?>
                    <tr>
                      <td><?= h((string) ($row['StartedDate'] ?? '')) ?></td>
                      <td><?= h((string) ($row['ExecutionVersionLabel'] ?? '')) ?></td>
                      <td><?= h((string) ($row['SourceVersionLabel'] ?? '')) ?></td>
                      <td><?= h((string) ($row['RolloverStatusCode'] ?? '')) ?></td>
                      <td class="text-end"><?= h((string) ($row['InsertedBalanceLineCount'] ?? '0')) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($row['TotalOpeningAmount'] ?? 0), 2)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
