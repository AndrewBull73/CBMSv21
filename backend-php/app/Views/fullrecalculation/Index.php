<?php
declare(strict_types=1);

/** @var array $selection */
/** @var array|null $job */
/** @var array $availableScopes */
/** @var array $availableModes */
/** @var array $availableCeilingModes */
/** @var array $availableTransactionTypes */
/** @var array $recentJobs */

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('fmtsecs')) {
    function fmtsecs(float $seconds): string
    {
        return number_format($seconds, 2, '.', ',') . 's';
    }
}

$jobStatus = (string) ($job['status'] ?? '');
$jobRunning = in_array($jobStatus, ['queued', 'running', 'cancel_requested'], true);
$jobSucceeded = $jobStatus === 'completed';
$jobFailed = in_array($jobStatus, ['failed', 'cancelled'], true);
$jobSelection = is_array($job['selection'] ?? null) ? $job['selection'] : $selection;
$jobSteps = is_array($job['steps'] ?? null) ? $job['steps'] : [];
$jobLogTail = is_array($job['logTail'] ?? null) ? $job['logTail'] : [];
$lastJobMessage = trim((string) ($job['lastMessage'] ?? ''));
$finishedAtRaw = trim((string) ($job['finishedAt'] ?? ''));
$finishedAtTs = $finishedAtRaw !== '' ? strtotime($finishedAtRaw) : false;
$showTerminalBanner = !$jobRunning && ($jobSucceeded || $jobFailed) && $finishedAtTs !== false && (time() - $finishedAtTs) <= 120;
$showLatestRunSummary = !$jobRunning && $job !== null;
$recentJobs = is_array($recentJobs ?? null) ? $recentJobs : [];
$availableTransactionTypes = is_array($availableTransactionTypes ?? null) ? $availableTransactionTypes : [];
$selectedTransactionTypes = is_array($selection['transaction_type_code_values'] ?? null)
    ? $selection['transaction_type_code_values']
    : [];
?>
<div class="card shadow-sm mt-4">
  <div class="card-header">
    <strong><i class="bi bi-cpu me-2"></i>Full Recalculation</strong>
    <div class="small text-muted">Run the new C# engine across the full transaction set, monitor progress, and cancel an active batch if needed.</div>
  </div>

  <div class="card-body">
    <?php if ($jobRunning): ?>
      <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-3" role="status" aria-live="polite">
        <div class="d-flex align-items-center gap-3">
          <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
          <div>
            <strong>Recalculation is running.</strong>
            <div class="small">
              <?= h((string) ($job['lastMessage'] ?? 'The batch is running in the background.')) ?>
              <?php if (!empty($job['currentStepLabel'])): ?>
                <span>Current step: <?= h((string) $job['currentStepLabel']) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <form method="post" action="index.php?route=full-recalculation/index" class="m-0">
          <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="cancel">
          <button type="submit" class="btn btn-danger">
            <i class="bi bi-stop-circle me-1"></i>Cancel Batch
          </button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($showTerminalBanner && $jobSucceeded): ?>
      <div class="alert alert-success" role="status">
        <strong>Recalculation completed.</strong>
        <?php if ($lastJobMessage !== ''): ?>
          <div class="small mt-1"><?= h($lastJobMessage) ?></div>
        <?php endif; ?>
      </div>
    <?php elseif ($showTerminalBanner && $jobFailed): ?>
      <div class="alert alert-danger" role="alert">
        <strong>Recalculation did not complete.</strong>
        <div class="small mt-1"><?= h($lastJobMessage !== '' ? $lastJobMessage : 'No error detail was captured for this batch.') ?></div>
      </div>
    <?php endif; ?>

    <?php if ($showLatestRunSummary): ?>
      <?php $summaryClass = $jobSucceeded ? 'alert-success' : ($jobFailed ? 'alert-danger' : 'alert-secondary'); ?>
      <div class="alert <?= h($summaryClass) ?> mb-4" role="status">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <div class="fw-bold">Latest Recalculation Run Summary</div>
            <div class="mt-1">
              Status:
              <strong><?= $jobSucceeded ? 'Completed' : ($jobFailed ? 'Did Not Complete' : h(ucwords(str_replace('_', ' ', $jobStatus ?: 'unknown')))) ?></strong>
            </div>
            <div class="small mt-1">
              Started: <?= h((string) ($job['startedAt'] ?? '-')) ?>
              |
              Finished: <?= h((string) ($job['finishedAt'] ?? '-')) ?>
            </div>
            <?php if ($lastJobMessage !== ''): ?>
              <div class="small mt-2"><?= h($lastJobMessage) ?></div>
            <?php endif; ?>
          </div>
          <div class="text-end">
            <div class="small">Steps Executed</div>
            <div class="fs-4 fw-semibold"><?= number_format(count($jobSteps)) ?></div>
          </div>
        </div>
        <?php if ($jobSteps !== []): ?>
          <hr>
          <div class="small fw-semibold mb-2">Step Outcomes</div>
          <?php foreach ($jobSteps as $step): ?>
            <?php
              $stepLabel = (string) ($step['label'] ?? 'Step');
              $stepStatus = (string) ($step['status'] ?? 'unknown');
              $stepOutputSummary = trim((string) ($step['outputSummary'] ?? ''));
              $stepErrorMessage = trim((string) ($step['errorMessage'] ?? ''));
            ?>
            <div class="small mb-1">
              <strong><?= h($stepLabel) ?>:</strong>
              <?= h(ucwords(str_replace('_', ' ', $stepStatus))) ?>
              <?php if ($stepOutputSummary !== ''): ?>
                |
                <?= h($stepOutputSummary) ?>
              <?php elseif ($stepErrorMessage !== ''): ?>
                |
                <?= h($stepErrorMessage) ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Fiscal Year</div><div class="fs-6 fw-semibold"><?= h((string) (($selection['fiscal_year_id'] ?? 0) > 0 ? $selection['fiscal_year_id'] : 'Not Set')) ?></div></div></div>
      <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Version</div><div class="fs-6 fw-semibold"><?= h((string) (($selection['version_id'] ?? 0) > 0 ? $selection['version_id'] : 'Not Set')) ?></div></div></div>
      <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Active Data Scope</div><div class="fs-6 fw-semibold"><?= h((string) (($selection['context_data_object_code'] ?? '') !== '' ? ($selection['context_data_object_code'] . ((string) ($selection['context_data_object_name'] ?? '') !== '' ? ' - ' . $selection['context_data_object_name'] : '')) : 'All DataObjectCodes')) ?></div></div></div>
    </div>

    <form method="post" action="index.php?route=full-recalculation/index" class="row g-4 align-items-start mb-4" id="fullRecalcForm">
      <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="start">

      <div class="col-lg-6">
        <label for="scope" class="form-label">Scope</label>
        <select class="form-select" id="scope" name="scope"<?= $jobRunning ? ' disabled' : '' ?>>
          <?php foreach ($availableScopes as $value => $meta): ?>
            <option value="<?= h($value) ?>"<?= ($selection['scope'] ?? 'all') === $value ? ' selected' : '' ?>>
              <?= h((string) $meta['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">
          <?php foreach ($availableScopes as $meta): ?>
            <div><strong><?= h((string) $meta['label']) ?>:</strong> <?= h((string) $meta['description']) ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-lg-6">
        <label for="mode" class="form-label">Mode</label>
        <select class="form-select" id="mode" name="mode"<?= $jobRunning ? ' disabled' : '' ?>>
          <?php foreach ($availableModes as $value => $meta): ?>
            <option value="<?= h($value) ?>"<?= ($selection['mode'] ?? 'benchmark') === $value ? ' selected' : '' ?>>
              <?= h((string) $meta['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">
          <?php foreach ($availableModes as $meta): ?>
            <div><strong><?= h((string) $meta['label']) ?>:</strong> <?= h((string) $meta['description']) ?></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="col-lg-6">
        <label for="ceiling_mode" class="form-label">Ceiling Checks</label>
        <select class="form-select" id="ceiling_mode" name="ceiling_mode"<?= $jobRunning ? ' disabled' : '' ?>>
          <?php foreach ($availableCeilingModes as $value => $meta): ?>
            <option value="<?= h($value) ?>"<?= ($selection['ceiling_mode'] ?? 'none') === $value ? ' selected' : '' ?>>
              <?= h((string) $meta['label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">
          <?php foreach ($availableCeilingModes as $meta): ?>
            <div><strong><?= h((string) $meta['label']) ?>:</strong> <?= h((string) $meta['description']) ?></div>
          <?php endforeach; ?>
          <div><strong>Note:</strong> Ceiling validation is only available for persistence modes, not in-memory benchmark runs.</div>
        </div>
      </div>

      <div class="col-lg-6">
        <label for="transaction_type_codes" class="form-label">TransactionTypes</label>
        <select class="form-select" id="transaction_type_codes" name="transaction_type_codes[]" multiple size="<?= h((string) max(4, min(10, count($availableTransactionTypes) === 0 ? 4 : count($availableTransactionTypes)))) ?>"<?= $jobRunning ? ' disabled' : '' ?>>
          <?php foreach ($availableTransactionTypes as $transactionType): ?>
            <?php
              $ttCode = trim((string) ($transactionType['TransactionTypeCode'] ?? ''));
              $ttName = trim((string) ($transactionType['TransactionTypeName'] ?? ''));
            ?>
            <?php if ($ttCode !== ''): ?>
              <option value="<?= h($ttCode) ?>"<?= in_array($ttCode, $selectedTransactionTypes, true) ? ' selected' : '' ?>>
                <?= h($ttCode . ($ttName !== '' ? ' - ' . $ttName : '')) ?>
              </option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <div class="form-text">Optional. Hold Ctrl or Cmd to select more than one transaction type.</div>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary" id="runRecalcButton"<?= $jobRunning ? ' disabled' : '' ?>>
          <i class="bi bi-play-circle me-1"></i>Run Recalculation
        </button>
      </div>
    </form>

    <?php if ($job !== null): ?>
      <h5 class="mb-3">Latest Run Detail</h5>
      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Scope</div><div class="fs-6 fw-semibold"><?= h((string) ($availableScopes[$jobSelection['scope']]['label'] ?? $jobSelection['scope'] ?? '')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Mode</div><div class="fs-6 fw-semibold"><?= h((string) ($availableModes[$jobSelection['mode']]['label'] ?? $jobSelection['mode'] ?? '')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Ceiling Checks</div><div class="fs-6 fw-semibold"><?= h((string) ($availableCeilingModes[$jobSelection['ceiling_mode']]['label'] ?? $jobSelection['ceiling_mode'] ?? 'No Ceiling Checks')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Status</div><div class="fs-6 fw-semibold <?= $jobSucceeded ? 'text-success' : ($jobFailed ? 'text-danger' : 'text-primary') ?>"><?= h(ucwords(str_replace('_', ' ', $jobStatus ?: 'unknown'))) ?></div></div></div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Started</div><div class="fs-6 fw-semibold"><?= h((string) ($job['startedAt'] ?? '-')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 bg-light h-100"><div class="small text-muted">Finished</div><div class="fs-6 fw-semibold"><?= h((string) ($job['finishedAt'] ?? '-')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Current Step</div><div class="fs-6 fw-semibold"><?= h((string) ($job['currentStepLabel'] ?? '-')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Steps</div><div class="fs-6 fw-semibold"><?= number_format(count($jobSteps)) ?></div></div></div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Fiscal Year</div><div class="fs-6 fw-semibold"><?= h((string) (($jobSelection['fiscal_year_id'] ?? 0) > 0 ? $jobSelection['fiscal_year_id'] : 'Not Set')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Version</div><div class="fs-6 fw-semibold"><?= h((string) (($jobSelection['version_id'] ?? 0) > 0 ? $jobSelection['version_id'] : 'Not Set')) ?></div></div></div>
        <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Active Data Scope</div><div class="fs-6 fw-semibold"><?= h((string) (($jobSelection['context_data_object_code'] ?? '') !== '' ? ($jobSelection['context_data_object_code'] . ((string) ($jobSelection['context_data_object_name'] ?? '') !== '' ? ' - ' . $jobSelection['context_data_object_name'] : '')) : 'All DataObjectCodes')) ?></div></div></div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="small text-muted">Effective Data Scope</div><div class="fs-6 fw-semibold"><?= h((string) (($jobSelection['effective_data_object_codes'] ?? '') !== '' ? $jobSelection['effective_data_object_codes'] : 'All')) ?></div></div></div>
        <div class="col-md-6"><div class="border rounded p-3 h-100"><div class="small text-muted">TransactionTypes Filter</div><div class="fs-6 fw-semibold"><?= h((string) (($jobSelection['transaction_type_codes'] ?? '') !== '' ? $jobSelection['transaction_type_codes'] : 'All')) ?></div></div></div>
      </div>

      <div class="table-responsive mb-4">
        <table class="table table-striped align-middle">
          <thead class="table-light">
            <tr>
              <th>Step</th>
              <th>Status</th>
              <th>Started</th>
              <th>Finished</th>
              <th class="text-end">Execution Time</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($jobSteps === []): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No steps have started yet.</td></tr>
          <?php else: ?>
            <?php foreach ($jobSteps as $step): ?>
              <?php
                $stepStatus = (string) ($step['status'] ?? 'unknown');
                $stepClass = $stepStatus === 'completed' ? 'text-success' : ($stepStatus === 'failed' ? 'text-danger' : 'text-primary');
                $stepErrorMessage = trim((string) ($step['errorMessage'] ?? ''));
                $stepOutputSummary = trim((string) ($step['outputSummary'] ?? ''));
              ?>
              <tr>
                <td><?= h((string) ($step['label'] ?? 'Step')) ?></td>
                <td class="<?= h($stepClass) ?>">
                  <?= h(ucwords(str_replace('_', ' ', $stepStatus))) ?>
                  <?php if ($stepOutputSummary !== '' && $stepOutputSummary !== $stepErrorMessage): ?>
                    <div class="small text-muted mt-1"><?= h($stepOutputSummary) ?></div>
                  <?php endif; ?>
                  <?php if ($stepErrorMessage !== ''): ?>
                    <div class="small text-danger mt-1"><?= h($stepErrorMessage) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= h((string) ($step['startedAt'] ?? '-')) ?></td>
                <td><?= h((string) ($step['finishedAt'] ?? '-')) ?></td>
                <td class="text-end"><?= isset($step['durationSeconds']) ? h(fmtsecs((float) $step['durationSeconds'])) : '-' ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div>
            <strong>Background Log</strong>
            <div class="small text-muted"><?= h((string) ($job['logPath'] ?? '')) ?></div>
          </div>
          <div class="small text-muted">Showing last <?= number_format(count($jobLogTail)) ?> lines</div>
        </div>
        <div class="card-body">
          <pre class="mb-0 small" style="white-space: pre-wrap; max-height: 420px; overflow:auto;"><?= h(implode(PHP_EOL, $jobLogTail)) ?></pre>
        </div>
      </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
      <div class="card-header">
        <strong>Recent Batch Jobs</strong>
        <div class="small text-muted">Recent recalculation runs and their saved log files.</div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Job</th>
                <th>Status</th>
                <th>Scope</th>
                <th>Mode</th>
                <th>Started</th>
                <th>Finished</th>
                <th>Steps</th>
                <th>Summary</th>
                <th>Log</th>
              </tr>
            </thead>
            <tbody>
            <?php if ($recentJobs === []): ?>
              <tr><td colspan="9" class="text-center text-muted py-3">No batch jobs found yet.</td></tr>
            <?php else: ?>
              <?php foreach ($recentJobs as $recentJob): ?>
                <?php
                  $recentStatus = (string) ($recentJob['status'] ?? 'unknown');
                  $recentStatusClass = $recentStatus === 'completed'
                    ? 'text-success'
                    : (in_array($recentStatus, ['failed', 'cancelled'], true) ? 'text-danger' : 'text-primary');
                  $isCurrentRecentJob = !empty($recentJob['is_current']);
                ?>
                <tr<?= $isCurrentRecentJob ? ' class="table-primary"' : '' ?>>
                  <td class="small"><?= h((string) ($recentJob['job_id'] ?? '')) ?></td>
                  <td class="<?= h($recentStatusClass) ?>"><?= h(ucwords(str_replace('_', ' ', $recentStatus))) ?></td>
                  <td><?= h((string) ($recentJob['scope_label'] ?? '')) ?></td>
                  <td><?= h((string) ($recentJob['mode_label'] ?? '')) ?></td>
                  <td class="small"><?= h((string) ($recentJob['started_at'] ?? '-')) ?></td>
                  <td class="small"><?= h((string) ($recentJob['finished_at'] ?? '-')) ?></td>
                  <td><?= h((string) ($recentJob['steps'] ?? 0)) ?></td>
                  <td class="small">
                    <?php if ($isCurrentRecentJob): ?>
                      <div><strong>Latest run</strong></div>
                    <?php endif; ?>
                    <?= h((string) ($recentJob['headline'] ?? '')) ?>
                  </td>
                  <td>
                    <?php if (!empty($recentJob['log_exists'])): ?>
                      <a class="btn btn-sm btn-outline-secondary" href="index.php?route=full-recalculation/log&amp;job=<?= urlencode((string) ($recentJob['job_id'] ?? '')) ?>" target="_blank" rel="noopener">
                        View Log
                      </a>
                    <?php else: ?>
                      <span class="text-muted small">No log</span>
                    <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('fullRecalcForm');
  var button = document.getElementById('runRecalcButton');
  var modeSelect = document.getElementById('mode');
  var ceilingSelect = document.getElementById('ceiling_mode');
  if (modeSelect && ceilingSelect) {
    function syncCeilingModeUi() {
      var benchmarkMode = modeSelect.value === 'benchmark';
      Array.from(ceilingSelect.options).forEach(function (option) {
        if (option.value === 'none') {
          option.disabled = false;
          return;
        }
        option.disabled = benchmarkMode;
      });

      if (benchmarkMode && ceilingSelect.value !== 'none') {
        ceilingSelect.value = 'none';
      }
    }

    modeSelect.addEventListener('change', syncCeilingModeUi);
    syncCeilingModeUi();
  }

  if (form && button && !button.disabled) {
    form.addEventListener('submit', function () {
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Starting...';
    });
  }

  <?php if ($jobRunning): ?>
  window.setTimeout(function () {
    window.location.reload();
  }, 5000);
  <?php endif; ?>
});
</script>
