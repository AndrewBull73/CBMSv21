<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => '', 'status' => ''];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$canManageReports = (bool) ($canManageReports ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-clock-history me-2"></i>Report Run History</h3>
    </div>
    <div class="card-body">
      <?php if (!$foundationInstalled): ?>
        <div class="alert alert-warning mb-0">
          Run <code><?= h($installScriptPath) ?></code> before using report run history.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          Review recent report launches, the context used, and the generated SSRS launch details.
        </div>

        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="reports/history">
          <div class="col-md-5">
            <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control" placeholder="Search report, format, scope, or summary">
          </div>
          <div class="col-md-3">
            <select name="module" class="form-select">
              <option value="">All modules</option>
              <?php foreach ($moduleOptions as $moduleCode): ?>
                <option value="<?= h((string) $moduleCode) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleCode) ? 'selected' : '' ?>><?= h((string) $moduleCode) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <select name="status" class="form-select">
              <option value="">All statuses</option>
              <?php foreach ($statusOptions as $code => $label): ?>
                <option value="<?= h((string) $code) ?>" <?= ((string) ($filters['status'] ?? '') === (string) $code) ? 'selected' : '' ?>><?= h((string) $label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-fill">Filter</button>
            <a href="index.php?route=reports/history" class="btn btn-outline-secondary flex-fill">Reset</a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Report</th>
                <th>Format</th>
                <th>Context</th>
                <th>Status</th>
                <th>Started</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No report runs have been logged yet.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['ReportName'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ReportCode'] ?? '')) ?></div>
                    </td>
                    <td><?= h((string) ($row['OutputFormatCode'] ?? '')) ?></td>
                    <td>
                      <div class="small">FY <?= h((string) ($row['FiscalYearID'] ?? '')) ?> / Ver <?= h((string) ($row['VersionID'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['DataObjectCode'] ?? '')) ?></div>
                    </td>
                    <td>
                      <span class="badge <?= ((string) ($row['RunStatusCode'] ?? '') === 'launched') ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= h((string) ($row['RunStatusCode'] ?? '')) ?>
                      </span>
                    </td>
                    <td>
                      <div><?= h((string) ($row['StartedAt'] ?? '')) ?></div>
                      <?php if (trim((string) ($row['SummaryText'] ?? '')) !== ''): ?>
                        <div class="small text-muted"><?= h((string) ($row['SummaryText'] ?? '')) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <a href="index.php?route=reports/run-detail&id=<?= (int) ($row['ReportRunID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                      </a>
                      <?php if ($canManageReports && (int) ($row['ReportDefinitionID'] ?? 0) > 0): ?>
                        <a href="index.php?route=report-admin/definition-form&id=<?= (int) ($row['ReportDefinitionID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
