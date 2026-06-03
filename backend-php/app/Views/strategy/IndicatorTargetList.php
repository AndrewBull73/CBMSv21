<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('num_fmt')) {
    function num_fmt(mixed $value): string
    {
        return $value === null || $value === '' ? '' : number_format((float) $value, 2);
    }
}

$csrf = h(csrf_token());
$records = is_array($records ?? null) ? $records : [];
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$activeCount = 0;
$indicatorNames = [];
$baselineCount = 0;

foreach ($records as $row) {
    if ((int) ($row['ActiveFlag'] ?? 0) === 1) {
        $activeCount++;
    }
    $indicatorName = trim((string) ($row['IndicatorName'] ?? ''));
    if ($indicatorName !== '') {
        $indicatorNames[$indicatorName] = true;
    }
    if ($row['BaselineValue'] !== null && $row['BaselineValue'] !== '') {
        $baselineCount++;
    }
}

$screenHeader = [
    'title' => 'Indicator Targets',
    'icon' => 'bi-graph-up-arrow',
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
              <div class="text-muted small">Targets in register</div>
              <div class="fs-4 fw-semibold"><?= count($records) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Active targets</div>
              <div class="fs-4 fw-semibold"><?= $activeCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Indicators represented</div>
              <div class="fs-4 fw-semibold"><?= count($indicatorNames) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Rows with baseline</div>
              <div class="fs-4 fw-semibold"><?= $baselineCount ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Target values are fiscal-context specific. Use this screen to confirm each active indicator has the right annual baseline and target before performance reporting or submission review.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Search and Actions</h5>
          <a href="index.php?route=strategy-performance/target-form" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create Target
          </a>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="strategy-performance/targets">
            <div class="col-md-5">
              <input
                type="text"
                name="q"
                value="<?= h((string) ($q ?? '')) ?>"
                class="form-control"
                placeholder="Search targets"
              >
            </div>
            <div class="col-md-5">
              <select name="indicator_id" class="form-select">
                <option value="">All indicators</option>
                <?php foreach (($indicatorOptions ?? []) as $option): ?>
                  <option value="<?= (int) $option['IndicatorID'] ?>" <?= ((int) ($indicatorId ?? 0) === (int) $option['IndicatorID']) ? 'selected' : '' ?>>
                    <?= h((string) $option['IndicatorTypeCode'] . ' / ' . $option['IndicatorName']) ?>
                  </option>
                <?php endforeach; ?>
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
          <h5 class="mb-0">Indicator Target Register</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Indicator</th>
                  <th>Type</th>
                  <th>Unit</th>
                  <th class="text-end">Baseline</th>
                  <th class="text-end">Target</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($records === []): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-3">No targets found for the active fiscal context.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($records as $row): ?>
                  <?php $isActive = (int) ($row['ActiveFlag'] ?? 0) === 1; ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['IndicatorName'] ?? '')) ?></div>
                      <?php if (!empty($row['Notes'])): ?>
                        <div class="small text-muted"><?= h((string) $row['Notes']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($row['IndicatorTypeCode'] ?? '')) ?></td>
                    <td><?= h((string) ($row['UnitOfMeasure'] ?? '')) ?></td>
                    <td class="text-end"><?= num_fmt($row['BaselineValue'] ?? null) ?></td>
                    <td class="text-end"><?= num_fmt($row['TargetValue'] ?? null) ?></td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <a
                          href="index.php?route=strategy-performance/target-form&id=<?= (int) ($row['IndicatorTargetID'] ?? 0) ?>"
                          class="btn btn-outline-primary btn-sm"
                        >
                          Edit
                        </a>
                        <?php if ($isActive): ?>
                          <form method="post" action="index.php?route=strategy-performance/delete-target" onsubmit="return confirm('Archive this target?');">
                            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                            <input type="hidden" name="id" value="<?= (int) ($row['IndicatorTargetID'] ?? 0) ?>">
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
