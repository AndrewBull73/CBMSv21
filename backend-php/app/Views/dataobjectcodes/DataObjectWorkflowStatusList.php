<?php declare(strict_types=1);
/** @var int $fiscalYearId */
/** @var int $versionId */
/** @var array $rows */
/** @var int $total */
/** @var array $summary */
/** @var array $contextLabels */
/** @var int $page */
/** @var int $pageSize */
/** @var string $q */
/** @var string $status */
/** @var array $statusOptions */
/** @var string $_csrf */

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : ['OPEN', 'IN PROGRESS', 'COMPLETED', 'APPROVED', 'REJECTED', 'CLOSED'];
$fiscalYearId = (int)($fiscalYearId ?? 0);
$versionId = (int)($versionId ?? 0);
$yearLabel = trim((string)($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string)($contextLabels['VersionLabel'] ?? ''));
$total = (int)($total ?? 0);
$page = max(1, (int)($page ?? 1));
$pageSize = max(1, (int)($pageSize ?? 50));
$q = (string)($q ?? '');
$status = strtoupper((string)($status ?? ''));
$pages = max(1, (int)ceil(($total ?: 0) / $pageSize));
$screenHeader = [
    'title' => 'Data Object Code Workflow Status',
    'icon' => 'bi-list-check',
];

$baseParams = [
    'route' => 'dataobjectworkflow/statuses',
    'q' => $q,
    'status' => $status,
    'pageSize' => $pageSize,
];
$qs = static fn(array $extra = []): string => 'index.php?' . http_build_query(array_replace($baseParams, $extra));
$returnQuery = http_build_query([
    'q' => $q,
    'status' => $status,
    'page' => $page,
    'pageSize' => $pageSize,
]);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string)($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($yearLabel !== '' ? $yearLabel : ($fiscalYearId > 0 ? (string)$fiscalYearId : 'Not set')) ?></strong>
        <?php if ($versionLabel !== '' || $versionId > 0): ?>
          <span class="mx-1">/</span>
          <strong><?= h($versionLabel !== '' ? $versionLabel : ('Version ' . (string)$versionId)) ?></strong>
        <?php endif; ?>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Data Object Codes</div><div class="fs-4 fw-semibold"><?= (int)($summary['total_codes'] ?? 0) ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Statuses Set</div><div class="fs-4 fw-semibold"><?= (int)($summary['set_count'] ?? 0) ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Missing Status</div><div class="fs-4 fw-semibold"><?= (int)($summary['missing_count'] ?? 0) ?></div></div></div></div>
        <div class="col-6 col-xl-3"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Open</div><div class="fs-4 fw-semibold"><?= (int)($summary['open_count'] ?? 0) ?></div></div></div></div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this register to initialise and maintain workflow status for each Data Object Code in the active fiscal year/version. Strategic submission entry expects the relevant DataScope status to be OPEN before lodgement activity can proceed.
      </div>

      <?php if ($fiscalYearId <= 0 || $versionId <= 0): ?>
        <div class="alert alert-warning border-0 shadow-sm mb-4">
          Select a fiscal year and version before maintaining data object workflow statuses.
        </div>
      <?php endif; ?>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="dataobjectworkflow/statuses">
            <div class="col-md-4">
              <input class="form-control" type="text" name="q" value="<?= h($q) ?>" placeholder="Search code or name">
            </div>
            <div class="col-md-3">
              <select class="form-select" name="status">
                <option value="">All statuses</option>
                <option value="NOT SET" <?= $status === 'NOT SET' ? 'selected' : '' ?>>Not Set</option>
                <?php foreach ($statusOptions as $option): ?>
                  <?php $option = strtoupper((string)$option); ?>
                  <option value="<?= h($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <select class="form-select" name="pageSize">
                <?php foreach ([25, 50, 100, 200] as $size): ?>
                  <option value="<?= $size ?>" <?= $pageSize === $size ? 'selected' : '' ?>><?= $size ?>/page</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-1 d-grid">
              <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            </div>
            <div class="col-md-2 d-grid">
              <a href="index.php?route=dataobjectworkflow/statuses" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Workflow Status Register</h5>
          <div class="d-flex gap-2">
            <a href="index.php?route=dataobjectcodes/index" class="btn btn-sm btn-outline-secondary">Data Object Codes</a>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#buildWorkflowStatusModal">
              Build Missing
            </button>
          </div>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?route=dataobjectworkflow/saveStatuses">
            <input type="hidden" name="_csrf" value="<?= h($_csrf ?? csrf_token()) ?>">
            <input type="hidden" name="returnQuery" value="<?= h($returnQuery) ?>">

            <div class="table-responsive">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Parent</th>
                    <th>Type</th>
                    <th>Workflow Status</th>
                    <th class="text-nowrap">Updated</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if ($rows === []): ?>
                    <tr><td colspan="6" class="text-center text-muted py-3">No data object codes found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                      <?php
                        $code = (string)($row['DataObjectCode'] ?? '');
                        $currentStatus = strtoupper(trim((string)($row['Status'] ?? '')));
                      ?>
                      <tr>
                        <td class="fw-semibold"><?= h($code) ?></td>
                        <td><?= h((string)($row['DataObjectName'] ?? '')) ?></td>
                        <td><?= h((string)($row['DataObjectCodeParent'] ?? '')) ?></td>
                        <td><?= h((string)($row['DataObjectTypeName'] ?? $row['DataObjectTypeID'] ?? '')) ?></td>
                        <td>
                          <select class="form-select form-select-sm" name="statuses[<?= h($code) ?>]">
                            <?php foreach ($statusOptions as $option): ?>
                              <?php $option = strtoupper((string)$option); ?>
                              <option value="<?= h($option) ?>" <?= $currentStatus === $option ? 'selected' : '' ?>><?= h($option) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <?php if ($currentStatus === ''): ?>
                            <div class="small text-warning mt-1">Not set</div>
                          <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= h((string)($row['DateUpdated'] ?? '')) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
              <div class="small text-muted">Showing <?= count($rows) ?> of <?= $total ?> data object code(s).</div>
              <button type="submit" class="btn btn-sm btn-primary" <?= $rows === [] ? 'disabled' : '' ?>>
                Save Statuses
              </button>
            </div>
          </form>

          <?php if ($pages > 1): ?>
            <nav class="mt-3" aria-label="Workflow status pages">
              <ul class="pagination justify-content-center mb-0">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= h($qs(['page' => max(1, $page - 1)])) ?>">Prev</a>
                </li>
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                  <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h($qs(['page' => $i])) ?>"><?= $i ?></a>
                  </li>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
                  <a class="page-link" href="<?= h($qs(['page' => min($pages, $page + 1)])) ?>">Next</a>
                </li>
              </ul>
            </nav>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="buildWorkflowStatusModal" tabindex="-1" aria-labelledby="buildWorkflowStatusModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="buildWorkflowStatusModalLabel">Build Missing Workflow Statuses</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php?route=dataobjectworkflow/buildStatuses">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= h($_csrf ?? csrf_token()) ?>">
          <p class="mb-3">Create missing workflow status records for every data object code in the current fiscal year/version?</p>
          <label class="form-label" for="DefaultStatus">Default status for new rows</label>
          <select class="form-select" id="DefaultStatus" name="DefaultStatus">
            <?php foreach ($statusOptions as $option): ?>
              <?php $option = strtoupper((string)$option); ?>
              <option value="<?= h($option) ?>" <?= $option === 'OPEN' ? 'selected' : '' ?>><?= h($option) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="alert alert-info mt-3 mb-0">
            Existing workflow status rows will not be overwritten by this build.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-sm btn-primary">
            Build Missing
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
