<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$record = is_array($record ?? null) ? $record : [];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$formatOptions = is_array($formatOptions ?? null) ? $formatOptions : [];
$id = (int) ($record['ReportDefinitionID'] ?? 0);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-file-earmark-ruled me-2"></i><?= $id > 0 ? 'Edit Report Definition' : 'Create Report Definition' ?></h3>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">
          Run <code><?= h($installScriptPath) ?></code> before creating report definitions.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          This form defines how CBMS should present, validate, and launch one formal SSRS report.
        </div>

        <?php if ($id > 0): ?>
          <div class="d-flex justify-content-end mb-3">
            <a href="index.php?route=reports/run&id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-play-circle me-1"></i>Open Runner</a>
          </div>
        <?php endif; ?>

        <form method="post" action="index.php?route=report-admin/save-definition">
          <?= csrf_field() ?>
          <input type="hidden" name="ReportDefinitionID" value="<?= $id ?>">

          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label">Report Code</label>
              <input type="text" name="ReportCode" class="form-control" value="<?= h((string) ($record['ReportCode'] ?? '')) ?>" required placeholder="RPT_APPROVED_BUDGET">
            </div>
            <div class="col-md-6">
              <label class="form-label">Report Name</label>
              <input type="text" name="ReportName" class="form-control" value="<?= h((string) ($record['ReportName'] ?? '')) ?>" required placeholder="Approved Budget Report">
            </div>
            <div class="col-md-3">
              <label class="form-label">Sort Order</label>
              <input type="number" name="SortOrder" class="form-control" value="<?= h((string) ($record['SortOrder'] ?? '')) ?>" placeholder="10">
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label">Module Code</label>
              <input type="text" name="ModuleCode" class="form-control" value="<?= h((string) ($record['ModuleCode'] ?? '')) ?>" placeholder="BUDGET_SUBMISSION">
            </div>
            <div class="col-md-4">
              <label class="form-label">Report Group</label>
              <input type="text" name="ReportGroupCode" class="form-control" value="<?= h((string) ($record['ReportGroupCode'] ?? '')) ?>" placeholder="FORMAL_BUDGET">
            </div>
            <div class="col-md-4">
              <label class="form-label">Permission Code</label>
              <input type="text" name="PermissionCode" class="form-control" value="<?= h((string) ($record['PermissionCode'] ?? '')) ?>" placeholder="STRATEGY_REPORT_VIEW">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Report Description</label>
            <textarea name="ReportDescription" class="form-control" rows="3" placeholder="Explain the purpose and business audience for this formal report."><?= h((string) ($record['ReportDescription'] ?? '')) ?></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">SSRS Path</label>
              <input type="text" name="SsrsPath" class="form-control" value="<?= h((string) ($record['SsrsPath'] ?? '')) ?>" placeholder="/CBMS/ApprovedBudgetReport">
              <div class="form-text">Use the SSRS report path. You can also paste a full URL if this report needs a special endpoint.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Server URL Override</label>
              <input type="text" name="ServerUrlOverride" class="form-control" value="<?= h((string) ($record['ServerUrlOverride'] ?? '')) ?>" placeholder="https://server/ReportServer">
              <div class="form-text">Optional. Leave blank to use <code>REPORT_SSRS_BASE_URL</code> or <code>SSRS_BASE_URL</code>.</div>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label">Supported Output Formats</label>
              <input type="text" name="OutputFormatsCsv" class="form-control" value="<?= h((string) ($record['OutputFormatsCsv'] ?? 'HTML,PDF,EXCEL')) ?>" placeholder="HTML,PDF,EXCEL">
              <div class="form-text">Use comma-separated values from: <?= h(implode(', ', array_keys($formatOptions))) ?>.</div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Default Format</label>
              <select name="DefaultFormatCode" class="form-select">
                <option value="">Choose default</option>
                <?php foreach ($formatOptions as $code => $label): ?>
                  <option value="<?= h((string) $code) ?>" <?= ((string) ($record['DefaultFormatCode'] ?? '') === (string) $code) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Required Inputs</label>
            <div class="row g-2">
              <?php
              $flags = [
                  'ContextRequiredFlag' => 'Linked context required',
                  'FiscalYearRequiredFlag' => 'Fiscal year required',
                  'VersionRequiredFlag' => 'Version required',
                  'DataScopeRequiredFlag' => 'Data scope required',
                  'DateFromRequiredFlag' => 'From date required',
                  'DateToRequiredFlag' => 'To date required',
              ];
              ?>
              <?php foreach ($flags as $field => $label): ?>
                <div class="col-md-4">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="<?= h($field) ?>" id="<?= h($field) ?>" value="1" <?= ((int) ($record[$field] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= h($field) ?>"><?= h($label) ?></label>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Parameter Configuration JSON</label>
            <textarea name="ParameterConfigJson" class="form-control font-monospace" rows="8" placeholder='{"ssrs_param_map":{"fiscal_year":"FiscalYearID","version":"VersionID","data_scope":"DataObjectCode","date_from":"DateFrom","date_to":"DateTo"},"static_params":{"rc:Toolbar":"true"}}'><?= h((string) ($record['ParameterConfigJson'] ?? '')) ?></textarea>
            <div class="form-text">Optional advanced mapping. Use <code>ssrs_param_map</code> to rename standard CBMS parameters and <code>static_params</code> for fixed SSRS parameters.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Notes</label>
            <textarea name="Notes" class="form-control" rows="4" placeholder="Document ownership, deployment notes, scheduling assumptions, or special rendering constraints."><?= h((string) ($record['Notes'] ?? '')) ?></textarea>
          </div>

          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="ActiveFlag" id="ActiveFlag" value="1" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ActiveFlag">Report definition is active and available in the catalogue</label>
          </div>

          <div class="d-flex justify-content-between">
            <a href="index.php?route=report-admin/definitions" class="btn btn-outline-secondary">Back</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Definition</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
