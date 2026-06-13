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
$usageCount = 0;
$systemDefaultTypeCount = 0;
foreach ($rows as $row) {
    if ((int) ($row['ActiveFlag'] ?? 0) === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    $code = strtoupper(trim((string) ($row['VersionTypeCode'] ?? '')));
    if ($code === 'SUBMISSION' || $code === 'EXECUTION') {
        $systemDefaultTypeCount++;
    }
    $usageCount += (int) ($row['VersionUsageCount'] ?? 0);
}
$screenHeader = [
    'title' => 'Version Types',
    'icon' => 'bi-diagram-3',
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
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Version Types</div><div class="fs-4 fw-semibold"><?= count($rows) ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Active</div><div class="fs-4 fw-semibold"><?= $activeCount ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Inactive</div><div class="fs-4 fw-semibold"><?= $inactiveCount ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Core Types Present</div><div class="fs-4 fw-semibold"><?= $systemDefaultTypeCount ?></div></div></div></div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this register to maintain the shared version-type catalogue used by version setup, readiness checks, and downstream logic that distinguishes submission and execution contexts.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Filters</h5></div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="version-types/list">
            <div class="col-md-6">
              <input class="form-control" type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search id, code, name, or description">
            </div>
            <div class="col-md-3">
              <select name="active" class="form-select">
                <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All statuses</option>
                <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active only</option>
                <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
              </select>
            </div>
            <div class="col-md-1 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary">Filter</button></div>
            <div class="col-md-2 d-grid"><a class="btn btn-sm btn-outline-secondary" href="index.php?route=version-types/list">Reset</a></div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Version Type Register</h5>
          <div class="d-flex gap-2">
            <a href="index.php?route=versions/list" class="btn btn-sm btn-outline-secondary">Versions</a>
            <a id="version-types-create-btn" href="index.php?route=version-types/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Version Type</a>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Code</th>
                  <th>Name</th>
                  <th>Description</th>
                  <th class="text-end">Version Usage</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No version types found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td class="fw-semibold"><?= (int) ($row['VersionTypeID'] ?? 0) ?></td>
                      <td><?= h((string) ($row['VersionTypeCode'] ?? '')) ?></td>
                      <td>
                        <div><?= h((string) ($row['VersionTypeName'] ?? '')) ?></div>
                        <?php
                          $typeCode = strtoupper(trim((string) ($row['VersionTypeCode'] ?? '')));
                          $isCoreType = $typeCode === 'SUBMISSION' || $typeCode === 'EXECUTION';
                        ?>
                        <?php if ($isCoreType): ?><div class="small text-muted">Core baseline type</div><?php endif; ?>
                      </td>
                      <td>
                        <div><?= h((string) ($row['Description'] ?? '')) ?></div>
                        <?php if (!empty($row['UpdatedDate']) || !empty($row['CreatedDate'])): ?>
                          <div class="small text-muted">Updated <?= h((string) ($row['UpdatedDate'] ?? $row['CreatedDate'] ?? '')) ?></div>
                        <?php endif; ?>
                      </td>
                      <td class="text-end"><?= (int) ($row['VersionUsageCount'] ?? 0) ?></td>
                      <td>
                        <?php if ($isCoreType): ?><span class="badge text-bg-primary me-1">Core</span><?php endif; ?>
                        <span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>"><?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Inactive' ?></span>
                      </td>
                      <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="index.php?route=version-types/form&id=<?= (int) ($row['VersionTypeID'] ?? 0) ?>">
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
