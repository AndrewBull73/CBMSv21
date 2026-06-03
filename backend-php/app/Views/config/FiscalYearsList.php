<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$activeCount = 0;
$inactiveCount = 0;
$systemDefaultCount = 0;
foreach ($rows as $row) {
    if ((int) ($row['IsActive'] ?? 0) === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if ((int) ($row['IsSystemDefault'] ?? 0) === 1) {
        $systemDefaultCount++;
    }
}
$screenHeader = [
    'title' => 'Fiscal Years',
    'icon' => 'bi-calendar3',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Fiscal Years</div>
              <div class="fs-4 fw-semibold"><?= count($rows) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Inactive</div>
              <div class="fs-4 fw-semibold"><?= $inactiveCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">System Defaults</div>
              <div class="fs-4 fw-semibold"><?= $systemDefaultCount ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this register to confirm the fiscal year baseline, active date ranges, and default context year before maintaining versions, segment values, and broader client configuration.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="fiscal-years/list">
            <div class="col-md-5">
              <input class="form-control" type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search fiscal year id or label">
            </div>
            <div class="col-md-3">
              <select name="active" class="form-select">
                <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All statuses</option>
                <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active only</option>
                <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            </div>
            <div class="col-md-2 d-grid">
              <a class="btn btn-sm btn-outline-secondary" href="index.php?route=fiscal-years/list">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Fiscal Year Register</h5>
          <a id="fiscal-years-create-btn" href="index.php?route=fiscal-years/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Fiscal Year</a>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Fiscal Year</th>
                  <th>Label</th>
                  <th>Period</th>
                  <th class="text-end">Versions</th>
                  <th>Default Context</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No fiscal years found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td class="fw-semibold"><?= (int) ($row['FiscalYearID'] ?? 0) ?></td>
                      <td><?= h((string) ($row['YearLabel'] ?? '')) ?></td>
                      <td>
                        <div><?= h((string) ($row['StartDate'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($row['EndDate'] ?? '')) ?></div>
                      </td>
                      <td class="text-end"><?= (int) ($row['VersionCount'] ?? 0) ?></td>
                      <td>
                        <?php if ((int) ($row['IsSystemDefault'] ?? 0) === 1): ?>
                          <span class="badge text-bg-primary">System Default FY</span>
                          <?php if (!empty($row['DefaultVersionLabel'])): ?>
                            <div class="small text-muted mt-1">Default version: <?= h((string) ($row['DefaultVersionLabel'] ?? '')) ?></div>
                          <?php endif; ?>
                        <?php elseif (!empty($row['DefaultVersionLabel'])): ?>
                          <span class="small text-muted"><?= h((string) ($row['DefaultVersionLabel'] ?? '')) ?></span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="badge text-bg-<?= ((int) ($row['IsActive'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                          <?= ((int) ($row['IsActive'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <div class="d-inline-flex gap-1">
                          <a class="btn btn-outline-primary btn-sm" href="index.php?route=fiscal-years/form&id=<?= (int) ($row['FiscalYearID'] ?? 0) ?>">
                            Edit
                          </a>
                          <a class="btn btn-outline-secondary btn-sm" href="index.php?route=versions/list&fy=<?= (int) ($row['FiscalYearID'] ?? 0) ?>">
                            Versions
                          </a>
                        </div>
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
</div>
