<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$definition = is_array($definition ?? null) ? $definition : [];
$inputs = is_array($inputs ?? null) ? $inputs : [];
$previewRequested = (bool) ($previewRequested ?? false);
$previewErrors = is_array($previewErrors ?? null) ? $previewErrors : [];
$previewUrl = (string) ($previewUrl ?? '');
$resolvedBaseUrl = (string) ($resolvedBaseUrl ?? '');
$availableFormats = is_array($availableFormats ?? null) ? $availableFormats : ['HTML', 'PDF', 'EXCEL'];
$canManageReports = (bool) ($canManageReports ?? false);
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$reportId = (int) ($definition['ReportDefinitionID'] ?? 0);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-play-circle me-2"></i>Run Report</h3>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">
          Run <code><?= h($installScriptPath) ?></code> before using the report runner.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          Use this screen to confirm the report context and launch the formal SSRS output in a new browser tab.
        </div>

        <div class="row g-3 mb-4">
          <div class="col-lg-7">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold"><?= h((string) ($definition['ReportName'] ?? '')) ?></div>
              <div class="small text-muted mb-2"><?= h((string) ($definition['ReportCode'] ?? '')) ?></div>
              <?php if (trim((string) ($definition['ReportDescription'] ?? '')) !== ''): ?>
                <div class="small mb-3"><?= h((string) ($definition['ReportDescription'] ?? '')) ?></div>
              <?php endif; ?>
              <div class="small text-muted">Module: <?= h((string) ($definition['ModuleCode'] ?? '')) ?></div>
              <div class="small text-muted">Group: <?= h((string) ($definition['ReportGroupCode'] ?? '')) ?></div>
              <div class="small text-muted">SSRS Path: <code><?= h((string) ($definition['SsrsPath'] ?? '')) ?></code></div>
              <div class="small text-muted">Resolved Base URL: <code><?= h($resolvedBaseUrl !== '' ? $resolvedBaseUrl : 'Not configured') ?></code></div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="border rounded p-3 h-100">
              <div class="fw-semibold mb-2">Required Inputs</div>
              <div class="small text-muted mb-1"><?= ((int) ($definition['FiscalYearRequiredFlag'] ?? 0) === 1) ? 'Fiscal Year' : 'Fiscal Year optional' ?></div>
              <div class="small text-muted mb-1"><?= ((int) ($definition['VersionRequiredFlag'] ?? 0) === 1) ? 'Version' : 'Version optional' ?></div>
              <div class="small text-muted mb-1"><?= ((int) ($definition['DataScopeRequiredFlag'] ?? 0) === 1) ? 'Data Scope' : 'Data Scope optional' ?></div>
              <div class="small text-muted mb-1"><?= ((int) ($definition['DateFromRequiredFlag'] ?? 0) === 1) ? 'From Date' : 'From Date optional' ?></div>
              <div class="small text-muted"><?= ((int) ($definition['DateToRequiredFlag'] ?? 0) === 1) ? 'To Date' : 'To Date optional' ?></div>
            </div>
          </div>
        </div>

        <form method="get" action="index.php" class="mb-4">
          <input type="hidden" name="route" value="reports/run">
          <input type="hidden" name="id" value="<?= $reportId ?>">
          <input type="hidden" name="preview" value="1">
          <input type="hidden" name="FiscalYearID" value="<?= h((string) ($inputs['FiscalYearID'] ?? '')) ?>">
          <input type="hidden" name="VersionID" value="<?= h((string) ($inputs['VersionID'] ?? '')) ?>">
          <input type="hidden" name="DataObjectCode" value="<?= h((string) ($inputs['DataObjectCode'] ?? '')) ?>">

          <div class="row g-3 mb-3">
            <div class="col-md-2">
              <label class="form-label">Fiscal Year</label>
              <input type="text" class="form-control" value="<?= h((string) ($inputs['FiscalYearID'] ?? '')) ?>" readonly>
              <div class="form-text">Taken from the active CBMS context.</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">Version</label>
              <input type="text" class="form-control" value="<?= h((string) ($inputs['VersionID'] ?? '')) ?>" readonly>
              <div class="form-text">Taken from the active CBMS context.</div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Data Scope</label>
              <input type="text" class="form-control" value="<?= h((string) ($inputs['DataObjectCode'] ?? '')) ?>" readonly>
              <div class="form-text">Taken from the active CBMS context.</div>
            </div>
            <div class="col-md-2">
              <label class="form-label">From Date</label>
              <input type="date" name="DateFrom" class="form-control" value="<?= h((string) ($inputs['DateFrom'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">To Date</label>
              <input type="date" name="DateTo" class="form-control" value="<?= h((string) ($inputs['DateTo'] ?? '')) ?>">
            </div>
            <div class="col-md-1">
              <label class="form-label">Format</label>
              <select name="OutputFormatCode" class="form-select">
                <?php foreach ($availableFormats as $format): ?>
                  <option value="<?= h((string) $format) ?>" <?= ((string) ($inputs['OutputFormatCode'] ?? '') === (string) $format) ? 'selected' : '' ?>><?= h((string) $format) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="d-flex justify-content-between">
            <a href="index.php?route=reports/catalogue" class="btn btn-outline-secondary">Back</a>
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Preview Launch</button>
          </div>
        </form>

        <?php if ($previewRequested): ?>
          <?php if ($previewErrors !== []): ?>
            <div class="alert alert-warning">
              <div class="fw-semibold mb-1">The report cannot be launched yet.</div>
              <ul class="mb-0">
                <?php foreach ($previewErrors as $error): ?>
                  <li><?= h((string) $error) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php else: ?>
            <div class="alert alert-success">
              Launch preview is ready. Use the button below to open the SSRS report in a new browser tab and log the run in CBMS.
            </div>

            <div class="mb-3">
              <label class="form-label">Launch URL Preview</label>
              <textarea class="form-control font-monospace" rows="5" readonly><?= h($previewUrl) ?></textarea>
            </div>

            <form method="post" action="index.php?route=reports/launch" target="_blank">
              <?= csrf_field() ?>
              <input type="hidden" name="ReportDefinitionID" value="<?= $reportId ?>">
              <input type="hidden" name="FiscalYearID" value="<?= h((string) ($inputs['FiscalYearID'] ?? '')) ?>">
              <input type="hidden" name="VersionID" value="<?= h((string) ($inputs['VersionID'] ?? '')) ?>">
              <input type="hidden" name="DataObjectCode" value="<?= h((string) ($inputs['DataObjectCode'] ?? '')) ?>">
              <input type="hidden" name="DateFrom" value="<?= h((string) ($inputs['DateFrom'] ?? '')) ?>">
              <input type="hidden" name="DateTo" value="<?= h((string) ($inputs['DateTo'] ?? '')) ?>">
              <input type="hidden" name="OutputFormatCode" value="<?= h((string) ($inputs['OutputFormatCode'] ?? '')) ?>">

              <div class="d-flex justify-content-between">
                <div class="small text-muted align-self-center">The CBMS run history will record the launch parameters and the resolved SSRS URL.</div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Open SSRS Report</button>
              </div>
            </form>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($canManageReports): ?>
          <div class="mt-4">
            <a href="index.php?route=report-admin/definition-form&id=<?= $reportId ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square me-1"></i>Edit Definition</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
