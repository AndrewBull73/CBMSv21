<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : ['q' => '', 'module' => '', 'active' => '1'];
$foundationInstalled = (bool) ($foundationInstalled ?? false);
$installScriptPath = (string) ($installScriptPath ?? '');
$moduleOptions = is_array($moduleOptions ?? null) ? $moduleOptions : [];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header">
      <h3 class="mb-0"><i class="bi bi-journal-text me-2"></i>Report Definitions</h3>
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
          Run <code><?= h($installScriptPath) ?></code> before maintaining report definitions.
        </div>
      <?php else: ?>

        <div class="alert alert-info">
          Register one definition per formal SSRS report so CBMS can handle permissions, parameter screens, and run logging consistently.
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div class="small text-muted">
            Keep report codes stable. They become the long-term identifiers for audit, launch history, and downstream governance.
          </div>
          <a href="index.php?route=report-admin/definition-form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Definition</a>
        </div>

        <form method="get" action="index.php" class="row g-2 mb-3">
          <input type="hidden" name="route" value="report-admin/definitions">
          <div class="col-md-5">
            <input type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" class="form-control" placeholder="Search code, title, group, path, or permission">
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
            <select name="active" class="form-select">
              <option value="">All statuses</option>
              <option value="1" <?= ((string) ($filters['active'] ?? '') === '1') ? 'selected' : '' ?>>Active only</option>
              <option value="0" <?= ((string) ($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-outline-primary flex-fill">Filter</button>
            <a href="index.php?route=report-admin/definitions" class="btn btn-outline-secondary flex-fill">Reset</a>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>Report</th>
                <th>Module</th>
                <th>SSRS Path</th>
                <th>Permission</th>
                <th>Status</th>
                <th>Last Run</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No report definitions have been registered yet.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['ReportName'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ReportCode'] ?? '')) ?></div>
                      <div class="small text-muted"><?= h((string) ($row['ReportGroupCode'] ?? '')) ?></div>
                    </td>
                    <td><?= h((string) ($row['ModuleCode'] ?? '')) ?></td>
                    <td><code><?= h((string) ($row['SsrsPath'] ?? '')) ?></code></td>
                    <td><?= h((string) ($row['PermissionCode'] ?? '')) ?></td>
                    <td>
                      <span class="badge <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                        <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                      </span>
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
                        <i class="bi bi-play-circle"></i>
                      </a>
                      <a href="index.php?route=report-admin/definition-form&id=<?= (int) ($row['ReportDefinitionID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil-square"></i>
                      </a>
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
