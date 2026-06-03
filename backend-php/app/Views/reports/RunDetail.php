<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$run = is_array($run ?? null) ? $run : [];
$parameterPayload = is_array($parameterPayload ?? null) ? $parameterPayload : [];
$canManageReports = (bool) ($canManageReports ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-file-earmark-medical me-2"></i>Report Run Detail</h3>
    </div>
    <div class="card-body">

      <div class="alert alert-info">
        This screen records the exact report, context, format, and launch URL used for one SSRS report run.
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Report</div>
            <div><?= h((string) ($run['ReportName'] ?? '')) ?></div>
            <div class="small text-muted"><?= h((string) ($run['ReportCode'] ?? '')) ?></div>
            <div class="small text-muted mt-2">Module: <?= h((string) ($run['ModuleCode'] ?? '')) ?></div>
            <div class="small text-muted">Group: <?= h((string) ($run['ReportGroupCode'] ?? '')) ?></div>
            <div class="small text-muted">Format: <?= h((string) ($run['OutputFormatCode'] ?? '')) ?></div>
            <div class="small text-muted">Status: <?= h((string) ($run['RunStatusCode'] ?? '')) ?></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="border rounded p-3 h-100">
            <div class="fw-semibold mb-2">Context</div>
            <div class="small text-muted">Fiscal Year: <?= h((string) ($run['FiscalYearID'] ?? '')) ?></div>
            <div class="small text-muted">Version: <?= h((string) ($run['VersionID'] ?? '')) ?></div>
            <div class="small text-muted">Data Scope: <?= h((string) ($run['DataObjectCode'] ?? '')) ?></div>
            <div class="small text-muted">From Date: <?= h((string) ($run['DateFrom'] ?? '')) ?></div>
            <div class="small text-muted">To Date: <?= h((string) ($run['DateTo'] ?? '')) ?></div>
            <div class="small text-muted mt-2">Started: <?= h((string) ($run['StartedAt'] ?? '')) ?></div>
            <div class="small text-muted">Completed: <?= h((string) ($run['CompletedAt'] ?? '')) ?></div>
          </div>
        </div>
      </div>

      <?php if (trim((string) ($run['SummaryText'] ?? '')) !== ''): ?>
        <div class="mb-3">
          <label class="form-label">Summary</label>
          <div class="form-control bg-light"><?= h((string) ($run['SummaryText'] ?? '')) ?></div>
        </div>
      <?php endif; ?>

      <?php if (trim((string) ($run['LaunchUrl'] ?? '')) !== ''): ?>
        <div class="mb-3">
          <label class="form-label">Launch URL</label>
          <textarea class="form-control font-monospace" rows="4" readonly><?= h((string) ($run['LaunchUrl'] ?? '')) ?></textarea>
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">Run Payload</label>
        <textarea class="form-control font-monospace" rows="14" readonly><?= h(json_encode($parameterPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></textarea>
      </div>

      <div class="d-flex justify-content-between">
        <a href="index.php?route=reports/history" class="btn btn-outline-secondary">Back</a>
        <?php if ($canManageReports && (int) ($run['ReportDefinitionID'] ?? 0) > 0): ?>
          <a href="index.php?route=report-admin/definition-form&id=<?= (int) ($run['ReportDefinitionID'] ?? 0) ?>" class="btn btn-outline-primary">Open Definition</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
