<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$fiscalYears = is_array($fiscalYears ?? null) ? $fiscalYears : [];
$segments = is_array($segments ?? null) ? $segments : [];
$returnTo = 'index.php?route=segment-values/list';
if (!empty($filters)) {
    $returnTo .= '&' . http_build_query($filters);
}
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Segment Values</h3>
        <div class="small text-muted mt-1">Maintain source segment values and parent links for the base configuration.</div>
      </div>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=segment-values/downloadTemplate" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Template</a>
        <a href="index.php?route=segment-values/upload" class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i>Upload</a>
        <a id="segment-values-create-btn" href="index.php?route=segment-values/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Segment Value</a>
      </div>
    </div>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="segment-values/list">
        <div class="col-md-2">
          <select name="fy" class="form-select">
            <option value="">All fiscal years</option>
            <?php foreach ($fiscalYears as $fy): ?>
              <?php $fyId = (string) ($fy['FiscalYearID'] ?? ''); ?>
              <option value="<?= h($fyId) ?>" <?= (($filters['fy'] ?? '') === $fyId) ? 'selected' : '' ?>>
                <?= h($fyId . ' - ' . (string) ($fy['YearLabel'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select name="segment_no" class="form-select">
            <option value="">All segments</option>
            <?php foreach ($segments as $segment): ?>
              <?php $segmentNo = (string) ($segment['SegmentNo'] ?? ''); ?>
              <option value="<?= h($segmentNo) ?>" <?= (($filters['segment_no'] ?? '') === $segmentNo) ? 'selected' : '' ?>>
                <?= h($segmentNo . ' - ' . (string) ($segment['SegmentName'] ?? '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <input class="form-control" type="text" name="data_object_code" value="<?= h((string) ($filters['data_object_code'] ?? '')) ?>" placeholder="Org unit code">
        </div>
        <div class="col-md-4">
          <input class="form-control" type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search code, name, or org unit">
        </div>
        <div class="col-md-1">
          <select name="active" class="form-select">
            <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All</option>
            <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Archived</option>
          </select>
        </div>
        <div class="col-md-1 d-grid">
          <button type="submit" class="btn btn-outline-primary">Filter</button>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Use this register to review source values by fiscal year, segment, and Org Unit. Use Upload for batch maintenance and Create Segment Value for individual records.</div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Fiscal Year</th>
              <th>Org Unit</th>
              <th>Segment</th>
              <th>Code</th>
              <th>Name</th>
              <th>Parent</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="8" class="text-center text-muted py-3">No segment values found.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td>
                    <div><?= h((string) ($row['FiscalYearID'] ?? '')) ?></div>
                    <?php if (!empty($row['YearLabel'])): ?><div class="small text-muted"><?= h((string) ($row['YearLabel'] ?? '')) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <div><?= h((string) ($row['DataObjectCode'] ?? '')) ?></div>
                    <?php if (!empty($row['DataObjectName'])): ?><div class="small text-muted"><?= h((string) ($row['DataObjectName'] ?? '')) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <div><?= h((string) ($row['SegmentNo'] ?? '')) ?></div>
                    <?php if (!empty($row['SegmentLabel'])): ?><div class="small text-muted"><?= h((string) ($row['SegmentLabel'] ?? '')) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <div><?= h((string) ($row['SegmentCode'] ?? '')) ?></div>
                    <?php if (!empty($row['SegmentExternalID'])): ?><div class="small text-muted">Ext: <?= h((string) ($row['SegmentExternalID'] ?? '')) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <div><?= h((string) ($row['SegmentName'] ?? '')) ?></div>
                    <?php if (!empty($row['SortOrder'])): ?><div class="small text-muted">Sort: <?= h((string) ($row['SortOrder'] ?? '0')) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($row['ParentSegmentValueID'])): ?><div>ID: <?= h((string) ($row['ParentSegmentValueID'] ?? '')) ?></div><?php endif; ?>
                    <?php if (!empty($row['ParentSegmentNo'])): ?><div class="small text-muted">Seg <?= h((string) ($row['ParentSegmentNo'] ?? '')) ?><?= !empty($row['ParentSegmentLabel']) ? ' - ' . h((string) ($row['ParentSegmentLabel'] ?? '')) : '' ?></div><?php endif; ?>
                    <?php if (!empty($row['ParentSegmentCode'])): ?><div class="small text-muted">Code: <?= h((string) ($row['ParentSegmentCode'] ?? '')) ?></div><?php endif; ?>
                  </td>
                  <td>
                    <span class="badge text-bg-<?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                      <?= ((int) ($row['ActiveFlag'] ?? 0) === 1) ? 'Active' : 'Archived' ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <a href="index.php?route=segment-values/form&id=<?= (int) ($row['SegmentValueID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <?php if ((int) ($row['ActiveFlag'] ?? 0) === 1): ?>
                        <form method="post" action="index.php?route=segment-values/archive" onsubmit="return confirm('Archive this segment value?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="SegmentValueID" value="<?= (int) ($row['SegmentValueID'] ?? 0) ?>">
                          <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-archive"></i>
                          </button>
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
