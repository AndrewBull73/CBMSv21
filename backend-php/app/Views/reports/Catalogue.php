<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => ''];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
$canManageReports = (bool) ($canManageReports ?? false);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Report Catalogue</h3>
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
          Run <code><?= h($installScriptPath) ?></code> before using the formal reporting catalogue.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          Use this catalogue to launch formal SSRS reports with a consistent CBMS parameter screen and run history.
        </div>

        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="reports/catalogue">
          <div class="col-md-7">
            <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control" placeholder="Search report code, title, group, or permission">
          </div>
          <div class="col-md-3">
            <select name="module" class="form-select">
              <option value="">All modules</option>
              <?php foreach ($moduleOptions as $moduleCode): ?>
                <option value="<?= h((string) $moduleCode) ?>" <?= ((string) ($filters['module'] ?? '') === (string) $moduleCode) ? 'selected' : '' ?>><?= h((string) $moduleCode) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-fill">Filter</button>
            <a href="index.php?route=reports/catalogue" class="btn btn-outline-secondary flex-fill">Reset</a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Report</th>
                <th>Module</th>
                <th>Group</th>
                <th>Formats</th>
                <th>Last Run</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No formal report definitions are available for your access right now.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['ReportName'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ReportCode'] ?? '')) ?></div>
                      <?php if (trim((string) ($row['ReportDescription'] ?? '')) !== ''): ?>
                        <div class="small text-muted mt-1"><?= h((string) ($row['ReportDescription'] ?? '')) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($row['ModuleCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['ReportGroupCode'] ?? '')) ?></td>
                    <td>
                      <?php
                        $formats = array_filter(array_map('trim', explode(',', strtoupper((string) ($row['OutputFormatsCsv'] ?? '')))));
                        if ($formats === []) {
                            $formats = ['HTML', 'PDF', 'EXCEL'];
                        }
                      ?>
                      <div class="d-flex flex-wrap gap-1">
                        <?php foreach ($formats as $format): ?>
                          <span class="badge text-bg-light border"><?= h((string) $format) ?></span>
                        <?php endforeach; ?>
                      </div>
                    </td>
                    <td>
                      <?php if (trim((string) ($row['LastRunStartedAt'] ?? '')) === ''): ?>
                        <span class="text-muted">Not run yet</span>
                      <?php else: ?>
                        <div><?= h((string) ($row['LastRunStartedAt'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($row['LastRunStatusCode'] ?? '')) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <a href="index.php?route=reports/run&id=<?= (int) ($row['ReportDefinitionID'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-play-circle me-1"></i>Run
                      </a>
                      <?php if ($canManageReports): ?>
                        <a href="index.php?route=report-admin/definition-form&id=<?= (int) ($row['ReportDefinitionID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
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
