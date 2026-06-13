<?php declare(strict_types=1);
/** @var string $title */
/** @var array  $rows */
/** @var int    $total */
/** @var int    $page */
/** @var int    $pageSize */
/** @var string $q */
/** @var ?int   $typeId */
/** @var string $status */
/** @var string $sort */
/** @var string $dir */
/** @var array  $types */
/** @var string $_csrf */
/** @var int    $fiscalYearId */

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$rows     = $rows ?? [];
$total    = (int)($total ?? 0);
$page     = max(1, (int)($page ?? 1));
$pageSize = max(1, (int)($pageSize ?? 25));
$sort     = $sort ?? 'DataObjectCode';
$dir      = strtoupper($dir ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
$q        = $q ?? '';
$status   = $status ?? '';
$typeId   = isset($typeId) && $typeId !== '' ? (int)$typeId : null;
$fiscalYearId = (int)($fiscalYearId ?? 0);

$pages = (int)ceil(($total ?: 0) / max(1, $pageSize));
if ($pages < 1) { $pages = 1; }

$baseParams = [
  'route'    => 'dataobjectcodes/index',
  'q'        => $q,
  'typeId'   => $typeId ?? '',
  'status'   => $status,
  'sort'     => $sort,
  'dir'      => $dir,
  'pageSize' => $pageSize,
];

$qs = fn(array $extra = []) => 'index.php?' . http_build_query(array_replace($baseParams, $extra));
$toggleSort = function (string $col) use ($sort, $dir, $qs): string {
  $newDir = ($sort === $col && $dir === 'ASC') ? 'DESC' : 'ASC';
  return $qs(['sort' => $col, 'dir' => $newDir, 'page' => 1]);
};

$printMode = ($_GET['print'] ?? '') === '1';
?>
<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-0"><i class="bi bi-collection me-2"></i><?= h(__t($title ?? 'docodes_title')) ?></h3>
      </div>
      <?php if (!$printMode): ?>
      <div class="d-inline-flex gap-2">
        <a href="index.php?route=dataobject-types/list" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-diagram-3 me-1"></i>Types
        </a>
        <a href="index.php?route=dataobjectcodes/hierarchy" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-diagram-2 me-1"></i>Hierarchy
        </a>
        <a href="index.php?route=dataobjectworkflow/statuses" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-list-check me-1"></i>Workflow Status
        </a>
        <a href="index.php?route=dataobjectcodes/downloadTemplate" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-download me-1"></i>Template
        </a>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#docUploadModal">
          <i class="bi bi-upload me-1"></i>Upload
        </button>
        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#docRebuildHierarchyModal">
          <i class="bi bi-diagram-2 me-1"></i>Rebuild Hierarchy
        </button>
        <a href="index.php?route=dataobjectcodes/export" class="btn btn-sm btn-outline-success">
          <i class="bi bi-file-earmark-excel me-1"></i><?= __t('export_excel') ?>
        </a>
        <a href="<?= $qs(['route' => 'dataobjectcodes/exportPdf']) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
          <i class="bi bi-filetype-pdf me-1"></i><?= __t('export_pdf') ?>
        </a>
        <a href="index.php?route=dataobjectcodes/create" id="dataobjectcodes-create-btn" class="btn btn-sm btn-primary">
          <i class="bi bi-plus-circle me-1"></i><?= __t('add') ?>
        </a>
      </div>
      <?php endif; ?>
    </div>

    <div class="card-body">
      <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= h((string) ($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
          <?= h((string) ($flash['text'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$printMode): ?>
      <form method="get" action="index.php" class="row g-2 mb-3">
        <input type="hidden" name="route" value="dataobjectcodes/index">
        <div class="col-md-4">
          <input type="text" id="dataobjectcodes-search-input" name="q" value="<?= h($q) ?>" class="form-control" placeholder="<?= __t('docodes_search_ph') ?>">
        </div>
        <div class="col-md-3">
          <select id="dataobjectcodes-type-filter" name="typeId" class="form-select">
            <option value=""><?= __t('all_types') ?></option>
            <?php foreach ($types as $t): ?>
              <?php $id = (int)($t['DataObjectTypeID'] ?? 0); $label = (string)($t['DataObjectTypeName'] ?? $id); ?>
              <option value="<?= $id ?>" <?= ($typeId === $id) ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <select id="dataobjectcodes-status-filter" name="status" class="form-select">
            <option value=""><?= __t('all_status') ?></option>
            <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>><?= __t('active') ?></option>
            <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>><?= __t('inactive') ?></option>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" id="dataobjectcodes-filter-btn" class="btn btn-outline-primary"><?= __t('filter') ?></button>
        </div>
      </form>

      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="small text-muted">Use this register to review the organisational code structure for the active fiscal context and maintain codes, parent links, types, and status values.</div>
        <div class="d-inline-flex gap-2">
          <select class="form-select form-select-sm" name="pageSize" onchange="window.location.href='<?= h($qs()) ?>&pageSize=' + encodeURIComponent(this.value);" style="width:auto;">
            <?php foreach ([10,25,50,100,200] as $ps): ?>
              <option value="<?= $ps ?>" <?= $pageSize === $ps ? 'selected' : '' ?>><?= $ps ?>/<?= __t('page') ?></option>
            <?php endforeach; ?>
          </select>
          <a class="btn btn-sm btn-outline-secondary" href="index.php?route=dataobjectcodes/index"><?= __t('reset') ?></a>
        </div>
      </div>
      <?php endif; ?>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle<?= $printMode ? ' table-sm' : '' ?> mb-0">
          <thead class="table-light">
            <tr>
              <th><a class="text-decoration-none" href="<?= $toggleSort('DataObjectCode') ?>"><?= __t('code') ?><?= $sort === 'DataObjectCode' ? ' ' . h($dir) : '' ?></a></th>
              <th><a class="text-decoration-none" href="<?= $toggleSort('DataObjectName') ?>"><?= __t('name') ?><?= $sort === 'DataObjectName' ? ' ' . h($dir) : '' ?></a></th>
              <th><a class="text-decoration-none" href="<?= $toggleSort('DataObjectCodeParent') ?>"><?= __t('parent') ?><?= $sort === 'DataObjectCodeParent' ? ' ' . h($dir) : '' ?></a></th>
              <th><a class="text-decoration-none" href="<?= $toggleSort('DataObjectTypeID') ?>"><?= __t('type') ?><?= $sort === 'DataObjectTypeID' ? ' ' . h($dir) : '' ?></a></th>
              <th><a class="text-decoration-none" href="<?= $toggleSort('DataObjectCodeStatus') ?>"><?= __t('status') ?><?= $sort === 'DataObjectCodeStatus' ? ' ' . h($dir) : '' ?></a></th>
              <th class="text-nowrap"><a class="text-decoration-none" href="<?= $toggleSort('DateUpdated') ?>"><?= __t('updated') ?><?= $sort === 'DateUpdated' ? ' ' . h($dir) : '' ?></a></th>
              <?php if (!$printMode): ?><th class="text-end text-nowrap"><?= __t('actions') ?></th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="<?= $printMode ? '6' : '7' ?>" class="text-center text-muted py-3"><?= __t('no_records_found') ?></td></tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= h((string) $r['DataObjectCode']) ?></td>
                  <td><?= h((string) $r['DataObjectName']) ?></td>
                  <td><?= h((string) ($r['DataObjectCodeParent'] ?? '')) ?></td>
                  <td><?= h($r['DataObjectTypeName'] ?? $r['DataObjectTypeID'] ?? '') ?></td>
                  <td>
                    <?php if (($r['DataObjectCodeStatus'] ?? '') === 'Active'): ?>
                      <span class="badge text-bg-success"><?= __t('active') ?></span>
                    <?php elseif (($r['DataObjectCodeStatus'] ?? '') === 'Inactive'): ?>
                      <span class="badge text-bg-danger"><?= __t('inactive') ?></span>
                    <?php else: ?>
                      <span class="badge text-bg-secondary"><?= h((string) ($r['DataObjectCodeStatus'] ?? '')) ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-nowrap"><?= h((string) ($r['DateUpdated'] ?? '')) ?></td>
                  <?php if (!$printMode): ?>
                  <td class="text-end text-nowrap">
                    <div class="d-inline-flex gap-1">
                      <a href="index.php?route=dataobjectcodes/edit&DataObjectCode=<?= urlencode((string) $r['DataObjectCode']) ?>" class="btn btn-sm btn-outline-secondary" title="<?= __t('edit') ?>">
                        <i class="bi bi-pencil-square"></i>
                      </a>
                      <button type="button"
                              class="btn btn-sm btn-outline-danger"
                              title="<?= __t('delete') ?>"
                              data-bs-toggle="modal"
                              data-bs-target="#docDeleteModal"
                              data-code="<?= h((string) $r['DataObjectCode']) ?>"
                              data-name="<?= h((string) $r['DataObjectName']) ?>">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
        <nav aria-label="<?= __t('pagination') ?>" class="mt-3">
          <ul class="pagination justify-content-center">
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $qs(['page' => max(1, $page - 1)]) ?>">&laquo; <?= __t('prev') ?></a>
            </li>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link" href="<?= $qs(['page' => $i]) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $qs(['page' => min($pages, $page + 1)]) ?>"><?= __t('next') ?> &raquo;</a>
            </li>
          </ul>
          <p class="text-center text-muted small mb-0"><?= __t('showing') ?> <?= count($rows) ?> <?= __t('of') ?> <?= $total ?> <?= __t('records') ?></p>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!$printMode): ?>
<div class="modal fade" id="docUploadModal" tabindex="-1" aria-labelledby="docUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="docUploadModalLabel">Upload Data Object Codes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php?route=dataobjectcodes/uploadProcess" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= h($_csrf ?? csrf_token()) ?>">
          <input type="hidden" name="UploadFiscalYearID" value="<?= $fiscalYearId ?>">

          <div class="alert alert-light border small">
            Upload an Excel or CSV file using the `DataObjectCodes` sheet. The easiest starting point is the template download or a file exported from this screen.
          </div>

          <div class="mb-3">
            <label for="docUploadFile" class="form-label">Spreadsheet File</label>
            <input type="file" class="form-control" id="docUploadFile" name="uploadFile" accept=".xlsx,.xls,.csv" required>
          </div>

          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="docUseCurrentFiscalYear" name="UseCurrentFiscalYear" <?= $fiscalYearId > 0 ? 'checked' : '' ?>>
            <label class="form-check-label" for="docUseCurrentFiscalYear">
              Use current fiscal year context<?= $fiscalYearId > 0 ? ' (FY ' . h((string)$fiscalYearId) . ')' : '' ?> for all rows
            </label>
          </div>

          <div class="small text-muted">
            Required columns: `DataObjectCode`, `DataObjectName`, and either `DataObjectTypeID` or `DataObjectTypeName`.
            Optional columns: `FiscalYearID`, `DataObjectCodeParent`, `DataObjectDesc`, `DataObjectCodeStatus`.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
          <button type="submit" class="btn btn-primary">Upload Spreadsheet</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="docRebuildHierarchyModal" tabindex="-1" aria-labelledby="docRebuildHierarchyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="docRebuildHierarchyModalLabel">Rebuild Data Object Hierarchy</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php?route=dataobjectcodes/rebuildHierarchy">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= h($_csrf ?? csrf_token()) ?>">
          <p class="mb-2">Rebuild hierarchy links for the current fiscal year<?= $fiscalYearId > 0 ? ' (FY ' . h((string)$fiscalYearId) . ')' : '' ?>?</p>
          <div class="alert alert-warning mb-0">
            This will replace existing hierarchy links in <code>tblDataObjectTree</code> for the current fiscal year using the parent codes in <code>tblDataObjectCodes</code>.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-diagram-2 me-1"></i>Rebuild Hierarchy
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="docDeleteModal" tabindex="-1" aria-labelledby="docDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="docDeleteModalLabel"><?= __t('confirm_delete') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><?= __t('delete_confirm_message') ?></p>
        <p><strong><?= __t('code') ?>:</strong> <span id="docDeleteCode"></span></p>
        <p><strong><?= __t('name') ?>:</strong> <span id="docDeleteName"></span></p>
      </div>
      <div class="modal-footer">
        <form method="post" action="index.php?route=dataobjectcodes/delete" style="display:inline;">
          <input type="hidden" name="_csrf" value="<?= h($_csrf ?? csrf_token()) ?>">
          <input type="hidden" name="DataObjectCode" id="docDeleteCodeInput" value="">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
          <button type="submit" class="btn btn-danger"><?= __t('delete') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('docDeleteModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    const code = button.getAttribute('data-code') || '';
    const name = button.getAttribute('data-name') || '';
    modal.querySelector('#docDeleteCode').textContent = code || '-';
    modal.querySelector('#docDeleteName').textContent = name || '-';
    modal.querySelector('#docDeleteCodeInput').value = code;
  });
});
</script>
<?php endif; ?>
