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
$containerCount = 0;
$terminalCount = 0;
$usageCount = 0;
foreach ($rows as $row) {
    if ((int) ($row['DataContainer'] ?? 0) === 1) {
        $containerCount++;
    } else {
        $terminalCount++;
    }
    $usageCount += (int) ($row['DataObjectUsageCount'] ?? 0);
}
$screenHeader = [
    'title' => 'Data Object Types',
    'icon' => 'bi-diagram-3',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : 'Not set') ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Type Rows</div><div class="fs-4 fw-semibold"><?= count($rows) ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Container Types</div><div class="fs-4 fw-semibold"><?= $containerCount ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Terminal Types</div><div class="fs-4 fw-semibold"><?= $terminalCount ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Data Object Usage</div><div class="fs-4 fw-semibold"><?= $usageCount ?></div></div></div></div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this register to maintain the shared data object type catalogue that classifies organisational codes, controls hierarchy depth, and helps Data Object Code maintenance understand which types can hold child records.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header"><h5 class="mb-0">Filters</h5></div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="dataobject-types/list">
            <div class="col-md-7">
              <input class="form-control" type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search id, type name, segment, or level">
            </div>
            <div class="col-md-3">
              <select name="container" class="form-select">
                <option value="" <?= (($filters['container'] ?? '') === '') ? 'selected' : '' ?>>All type roles</option>
                <option value="1" <?= (($filters['container'] ?? '') === '1') ? 'selected' : '' ?>>Container types only</option>
                <option value="0" <?= (($filters['container'] ?? '') === '0') ? 'selected' : '' ?>>Terminal types only</option>
              </select>
            </div>
            <div class="col-md-1 d-grid"><button type="submit" class="btn btn-sm btn-outline-primary">Filter</button></div>
            <div class="col-md-1 d-grid"><a class="btn btn-sm btn-outline-secondary" href="index.php?route=dataobject-types/list">Reset</a></div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Data Object Type Register</h5>
          <div class="d-flex gap-2">
            <a href="index.php?route=dataobjectcodes/index" class="btn btn-sm btn-outline-secondary">Data Object Codes</a>
            <a id="data-object-types-create-btn" href="index.php?route=dataobject-types/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Data Object Type</a>
          </div>
        </div>
        <div class="card-body">
          <div class="small text-muted mb-3">These type rows are global master data. The usage count below shows how many Data Object Code records currently point to each type across all fiscal years.</div>
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th class="text-end">Segment No</th>
                  <th class="text-end">Level</th>
                  <th>Type Role</th>
                  <th class="text-end">Data Object Usage</th>
                  <th>Last Updated</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="8" class="text-center text-muted py-3">No data object types found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <?php $isContainer = (int) ($row['DataContainer'] ?? 0) === 1; ?>
                    <tr>
                      <td class="fw-semibold"><?= (int) ($row['DataObjectTypeID'] ?? 0) ?></td>
                      <td>
                        <div><?= h((string) ($row['DataObjectTypeName'] ?? '')) ?></div>
                        <div class="small text-muted">Hierarchy level <?= (int) ($row['Level'] ?? 0) ?></div>
                      </td>
                      <td class="text-end"><?= ($row['SegmentNo'] ?? null) !== null ? (int) $row['SegmentNo'] : '' ?></td>
                      <td class="text-end"><?= (int) ($row['Level'] ?? 0) ?></td>
                      <td><span class="badge text-bg-<?= $isContainer ? 'primary' : 'secondary' ?>"><?= $isContainer ? 'Container' : 'Terminal' ?></span></td>
                      <td class="text-end"><?= (int) ($row['DataObjectUsageCount'] ?? 0) ?></td>
                      <td><?= h((string) ($row['DateUpdated'] ?? '')) ?></td>
                      <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="index.php?route=dataobject-types/form&id=<?= (int) ($row['DataObjectTypeID'] ?? 0) ?>">
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
