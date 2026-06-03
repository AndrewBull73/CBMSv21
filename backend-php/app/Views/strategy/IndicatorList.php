<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = h(csrf_token());
$records = is_array($records ?? null) ? $records : [];
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$activeCount = 0;
$typeCounts = [
    'OUTCOME' => 0,
    'OUTPUT' => 0,
];
$units = [];

foreach ($records as $row) {
    if ((int) ($row['ActiveFlag'] ?? 0) === 1) {
        $activeCount++;
    }
    $indicatorType = strtoupper(trim((string) ($row['IndicatorTypeCode'] ?? '')));
    if (isset($typeCounts[$indicatorType])) {
        $typeCounts[$indicatorType]++;
    }
    $unit = trim((string) ($row['UnitOfMeasure'] ?? ''));
    if ($unit !== '') {
        $units[$unit] = true;
    }
}

$screenHeader = [
    'title' => 'Strategic Indicators',
    'icon' => 'bi-speedometer2',
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
              <div class="text-muted small">Indicators in register</div>
              <div class="fs-4 fw-semibold"><?= count($records) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active indicators</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Outcome indicators</div>
              <div class="fs-4 fw-semibold"><?= (int) ($typeCounts['OUTCOME'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Units represented</div>
              <div class="fs-4 fw-semibold"><?= count($units) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Maintain the shared indicator register here before target entry begins. Use the type filter to keep outcome and output measures cleanly separated and confirm units and data sources are complete.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Search and Actions</h5>
          <a href="index.php?route=strategy-performance/indicator-form" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create Indicator
          </a>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="strategy-performance/indicators">
            <div class="col-md-6">
              <input
                type="text"
                name="q"
                value="<?= h((string) ($q ?? '')) ?>"
                class="form-control"
                placeholder="Search indicators"
              >
            </div>
            <div class="col-md-4">
              <select name="type_code" class="form-select">
                <option value="">All types</option>
                <option value="OUTCOME" <?= (($typeCode ?? '') === 'OUTCOME') ? 'selected' : '' ?>>OUTCOME</option>
                <option value="OUTPUT" <?= (($typeCode ?? '') === 'OUTPUT') ? 'selected' : '' ?>>OUTPUT</option>
              </select>
            </div>
            <div class="col-md-2 d-grid">
              <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Indicator Register</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Indicator</th>
                  <th>Type</th>
                  <th>Unit</th>
                  <th>Source</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($records === []): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-3">No indicators found.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($records as $row): ?>
                  <?php $isActive = (int) ($row['ActiveFlag'] ?? 0) === 1; ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['IndicatorName'] ?? '')) ?></div>
                      <?php if (!empty($row['IndicatorDefinition'])): ?>
                        <div class="small text-muted"><?= h((string) $row['IndicatorDefinition']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($row['IndicatorTypeCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['UnitOfMeasure'] ?? '')) ?></td>
                    <td><?= h((string) ($row['DataSource'] ?? '')) ?></td>
                    <td>
                      <span class="badge text-bg-<?= $isActive ? 'success' : 'secondary' ?>">
                        <?= $isActive ? 'Active' : 'Archived' ?>
                      </span>
                    </td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <a
                          href="index.php?route=strategy-performance/indicator-form&id=<?= (int) ($row['IndicatorID'] ?? 0) ?>"
                          class="btn btn-outline-primary btn-sm"
                        >
                          Edit
                        </a>
                        <?php if ($isActive): ?>
                          <form method="post" action="index.php?route=strategy-performance/delete-indicator" onsubmit="return confirm('Archive this indicator?');">
                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= (int) ($row['IndicatorID'] ?? 0) ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Archive</button>
                          </form>
                        <?php endif; ?>
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
