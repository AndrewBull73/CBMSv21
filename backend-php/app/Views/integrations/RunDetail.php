<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$run = is_array($run ?? null) ? $run : [];
$requestPayloadPretty = (string) ($requestPayloadPretty ?? '');
$responsePayloadPretty = (string) ($responsePayloadPretty ?? '');
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Integration Run Detail</h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>


      <div class="alert alert-info">
        Review the documented context, counts, and payloads for this integration run.
      </div>

      <div class="d-flex justify-content-end gap-2 flex-wrap mb-3">
        <a href="index.php?route=integration-admin/download-run-summary&id=<?= (int) ($run['IntegrationRunID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Download Summary</a>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        <a href="index.php?route=integration-admin/runs" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Run ID</div><div class="fs-4 fw-semibold"><?= (int) ($run['IntegrationRunID'] ?? 0) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Status</div><div class="fs-4 fw-semibold"><?= h((string) ($run['RunStatusCode'] ?? '')) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Records Processed</div><div class="fs-4 fw-semibold"><?= (int) ($run['RecordsProcessed'] ?? 0) ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Records Failed</div><div class="fs-4 fw-semibold"><?= (int) ($run['RecordsFailed'] ?? 0) ?></div></div></div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header">Run Context</div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-sm-4">Interface</dt><dd class="col-sm-8"><?= h((string) ($run['InterfaceName'] ?? '')) ?> <span class="text-muted">(<?= h((string) ($run['InterfaceCode'] ?? '')) ?>)</span></dd>
                <dt class="col-sm-4">System</dt><dd class="col-sm-8"><?= h((string) ($run['SystemName'] ?? '')) ?> <span class="text-muted">(<?= h((string) ($run['SystemCode'] ?? '')) ?>)</span></dd>
                <dt class="col-sm-4">Direction</dt><dd class="col-sm-8"><?= h((string) ($run['DirectionCode'] ?? '')) ?></dd>
                <dt class="col-sm-4">Module / Entity</dt><dd class="col-sm-8"><?= h(trim((string) (($run['ModuleCode'] ?? '') . ' / ' . ($run['EntityCode'] ?? '')), ' /')) ?></dd>
                <dt class="col-sm-4">Fiscal / Version</dt><dd class="col-sm-8">FY <?= h((string) ($run['FiscalYearID'] ?? '')) ?> / Version <?= h((string) ($run['VersionID'] ?? '')) ?></dd>
                <dt class="col-sm-4">Scope</dt><dd class="col-sm-8"><?= h((string) ($run['DataObjectCode'] ?? '')) ?></dd>
                <dt class="col-sm-4">Trigger</dt><dd class="col-sm-8"><?= h((string) ($run['TriggerSourceCode'] ?? '')) ?></dd>
                <dt class="col-sm-4">User</dt><dd class="col-sm-8"><?= h((string) (($run['DisplayName'] ?? '') !== '' ? $run['DisplayName'] : ($run['Username'] ?? ''))) ?></dd>
              </dl>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header">Interface Governance</div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-sm-4">Business Owner</dt><dd class="col-sm-8"><?= h((string) ($run['BusinessOwner'] ?? '')) ?></dd>
                <dt class="col-sm-4">Source Owner</dt><dd class="col-sm-8"><?= h((string) ($run['SourceOwner'] ?? '')) ?></dd>
                <dt class="col-sm-4">Approval Stage</dt><dd class="col-sm-8"><?= h((string) ($run['ApprovalStage'] ?? '')) ?></dd>
                <dt class="col-sm-4">Readiness</dt><dd class="col-sm-8"><?= h((string) ($run['ReadinessStatus'] ?? '')) ?></dd>
                <dt class="col-sm-4">Started</dt><dd class="col-sm-8"><?= h((string) ($run['StartedAt'] ?? '')) ?></dd>
                <dt class="col-sm-4">Completed</dt><dd class="col-sm-8"><?= h((string) ($run['CompletedAt'] ?? '')) ?></dd>
                <dt class="col-sm-4">Duration</dt><dd class="col-sm-8"><?= h((string) ($run['DurationSeconds'] ?? '')) ?> sec</dd>
                <dt class="col-sm-4">Summary</dt><dd class="col-sm-8"><?= h((string) ($run['SummaryText'] ?? '')) ?></dd>
              </dl>
              <?php if (!empty($run['ErrorText'])): ?>
                <div class="alert alert-danger mt-3 mb-0"><?= h((string) $run['ErrorText']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header">Request Payload</div>
            <div class="card-body">
              <textarea class="form-control font-monospace" rows="20" readonly><?= h($requestPayloadPretty) ?></textarea>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header">Response Payload</div>
            <div class="card-body">
              <textarea class="form-control font-monospace" rows="20" readonly><?= h($responsePayloadPretty) ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
