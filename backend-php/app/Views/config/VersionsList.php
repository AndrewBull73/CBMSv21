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
$fiscalYears = is_array($fiscalYears ?? null) ? $fiscalYears : [];
$versionTypes = is_array($versionTypes ?? null) ? $versionTypes : [];
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$createFy = (int) ($filters['fy'] ?? 0);
$activeCount = 0;
$defaultCount = 0;
$systemDefaultCount = 0;
foreach ($rows as $row) {
    if ((int) ($row['IsActive'] ?? 0) === 1) {
        $activeCount++;
    }
    if ((int) ($row['IsDefault'] ?? 0) === 1) {
        $defaultCount++;
    }
    if ((int) ($row['IsSystemDefault'] ?? 0) === 1) {
        $systemDefaultCount++;
    }
}
$screenHeader = [
    'title' => 'Versions',
    'icon' => 'bi-layers',
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
              <div class="text-muted small">Versions</div>
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
              <div class="text-muted small">FY Defaults</div>
              <div class="fs-4 fw-semibold"><?= $defaultCount ?></div>
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
        Use this register to confirm each active fiscal year has the right submission or execution versions, the right default version, and the right base-version lineage before wider testing begins.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="versions/list">
            <div class="col-md-3">
              <select name="fy" class="form-select">
                <option value="0">All fiscal years</option>
                <?php foreach ($fiscalYears as $fy): ?>
                  <?php $fyId = (int) ($fy['FiscalYearID'] ?? 0); ?>
                  <option value="<?= $fyId ?>" <?= ((int) ($filters['fy'] ?? 0) === $fyId) ? 'selected' : '' ?>>
                    <?= h((string) $fyId . ' - ' . (string) ($fy['YearLabel'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <select name="version_type_id" class="form-select">
                <option value="0">All version types</option>
                <?php foreach ($versionTypes as $type): ?>
                  <?php $typeId = (int) ($type['VersionTypeID'] ?? 0); ?>
                  <option value="<?= $typeId ?>" <?= ((int) ($filters['version_type_id'] ?? 0) === $typeId) ? 'selected' : '' ?>>
                    <?= h((string) ($type['VersionTypeName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <input class="form-control" type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search version id or label">
            </div>
            <div class="col-md-2">
              <select name="active" class="form-select">
                <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All statuses</option>
                <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active only</option>
                <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            </div>
            <div class="col-md-1 d-grid">
              <a class="btn btn-sm btn-outline-secondary" href="index.php?route=versions/list">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Version Register</h5>
          <div class="d-flex gap-2">
            <a href="index.php?route=version-types/list" class="btn btn-sm btn-outline-secondary">Version Types</a>
            <a id="versions-create-btn" href="index.php?route=versions/form<?= $createFy > 0 ? '&fy=' . $createFy : '' ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Version</a>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Fiscal Year</th>
                  <th>Version</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Flags</th>
                  <th>Base Version</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No versions found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= (int) ($row['FiscalYearID'] ?? 0) ?></div>
                        <?php if (!empty($row['YearLabel'])): ?><div class="small text-muted"><?= h((string) ($row['YearLabel'] ?? '')) ?></div><?php endif; ?>
                      </td>
                      <td>
                        <div><?= h((string) ($row['VersionLabel'] ?? '')) ?></div>
                        <div class="small text-muted">Version <?= (int) ($row['VersionID'] ?? 0) ?></div>
                      </td>
                      <td>
                        <div><?= h((string) ($row['VersionTypeName'] ?? '')) ?></div>
                        <?php if (!empty($row['VersionTypeCode'])): ?><div class="small text-muted"><?= h((string) ($row['VersionTypeCode'] ?? '')) ?></div><?php endif; ?>
                      </td>
                      <td><?= h((string) ($row['VersionStatus'] ?? '')) ?></td>
                      <td>
                        <?php if ((int) ($row['IsDefault'] ?? 0) === 1): ?><span class="badge text-bg-success me-1">FY Default</span><?php endif; ?>
                        <?php if ((int) ($row['IsSystemDefault'] ?? 0) === 1): ?><span class="badge text-bg-primary me-1">System Default</span><?php endif; ?>
                        <span class="badge text-bg-<?= ((int) ($row['IsActive'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                          <?= ((int) ($row['IsActive'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                        </span>
                      </td>
                      <td>
                        <?php if (!empty($row['BaseFiscalYearID']) && !empty($row['BaseVersionID'])): ?>
                          <div><?= (int) ($row['BaseFiscalYearID'] ?? 0) ?> / <?= (int) ($row['BaseVersionID'] ?? 0) ?></div>
                          <?php if (!empty($row['BaseVersionLabel'])): ?><div class="small text-muted"><?= h((string) ($row['BaseVersionLabel'] ?? '')) ?></div><?php endif; ?>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="index.php?route=versions/form&fy=<?= (int) ($row['FiscalYearID'] ?? 0) ?>&id=<?= (int) ($row['VersionID'] ?? 0) ?>">
                          Edit
                        </a>
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
