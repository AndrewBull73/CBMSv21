<?php declare(strict_types=1);
/** @var string $title */
/** @var int $fiscalYearId */
/** @var string $rootCode */
/** @var string $rootName */
/** @var bool $includeInactive */
/** @var bool $applied */
/** @var array $summary */
/** @var array $rootType */
/** @var array $segmentTypes */
/** @var array $candidates */
/** @var array $rejected */
/** @var string $_csrf */

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

$fiscalYearId = (int)($fiscalYearId ?? 0);
$rootCode = (string)($rootCode ?? 'GOV');
$rootName = (string)($rootName ?? 'Government');
$includeInactive = !empty($includeInactive);
$applied = !empty($applied);
$summary = $summary ?? [];
$rootType = $rootType ?? [];
$segmentTypes = $segmentTypes ?? [];
$candidates = $candidates ?? [];
$rejected = $rejected ?? [];

$metric = static function (array $summary, string $key): string {
    return number_format((int)($summary[$key] ?? 0));
};

$screenHeader = [
    'title' => (string)($title ?? 'Load Data Object Codes from Segment Values'),
    'icon' => 'bi-diagram-3',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div class="text-muted small">
        Fiscal Year <?= h((string)$fiscalYearId) ?> &middot; Root <?= h($rootCode) ?> &middot; <?= $includeInactive ? 'Including inactive segment values' : 'Active segment values only' ?>
        </div>
        <div class="d-inline-flex gap-2">
          <a href="index.php?route=dataobjectcodes/index" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
          </a>
          <?php if (!$applied): ?>
            <form method="post" action="index.php?route=dataobjectcodes/syncOrgFromSegments" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= h($_csrf ?? csrf_token()) ?>">
              <input type="hidden" name="apply" value="1">
              <input type="hidden" name="root_code" value="<?= h($rootCode) ?>">
              <input type="hidden" name="root_name" value="<?= h($rootName) ?>">
              <?php if ($includeInactive): ?>
                <input type="hidden" name="include_inactive" value="1">
              <?php endif; ?>
              <button type="submit" class="btn btn-primary">
                <i class="bi bi-database-check me-1"></i>Load DataObjectCodes
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>

  <?php if (!empty($flash)): ?>
    <div class="alert alert-<?= h((string)($flash['type'] ?? 'info')) ?> alert-dismissible fade show">
      <?= h((string)($flash['text'] ?? '')) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!$applied): ?>
    <div class="alert alert-info">
      This is a preview. Review the candidate and rejected rows, then choose <strong>Load DataObjectCodes</strong> to update the tables and rebuild the hierarchy.
    </div>
  <?php else: ?>
    <div class="alert alert-success">
      Data Object Codes were loaded from segment values and <code>tblDataObjectTree</code> was rebuilt.
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="border rounded p-3 h-100">
        <div class="text-muted small">Source Rows</div>
        <div class="fs-4 fw-semibold"><?= $metric($summary, 'SourceRows') ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3 h-100">
        <div class="text-muted small">Candidate Rows</div>
        <div class="fs-4 fw-semibold"><?= $metric($summary, 'CandidateRows') ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3 h-100">
        <div class="text-muted small">Rejected Rows</div>
        <div class="fs-4 fw-semibold"><?= $metric($summary, 'RejectedRows') ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="border rounded p-3 h-100">
        <div class="text-muted small"><?= $applied ? 'Created / Updated' : 'New / Existing' ?></div>
        <div class="fs-4 fw-semibold">
          <?= $applied ? $metric($summary, 'CreatedRows') . ' / ' . $metric($summary, 'UpdatedRows') : $metric($summary, 'NewRowsToInsert') . ' / ' . $metric($summary, 'ExistingRowsToUpdate') ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-5">
      <div class="border rounded p-3 h-100">
        <h5 class="mb-3">Root Type</h5>
        <dl class="row mb-0 small">
          <dt class="col-5">Type</dt>
          <dd class="col-7"><?= h((string)($rootType['DataObjectTypeName'] ?? '')) ?></dd>
          <dt class="col-5">Type ID</dt>
          <dd class="col-7"><?= h((string)($rootType['DataObjectTypeID'] ?? '')) ?></dd>
          <dt class="col-5">Level</dt>
          <dd class="col-7"><?= h((string)($rootType['Level'] ?? '')) ?></dd>
          <dt class="col-5">Root Code</dt>
          <dd class="col-7"><code><?= h($rootCode) ?></code></dd>
        </dl>
      </div>
    </div>
    <div class="col-md-7">
      <div class="border rounded p-3 h-100">
        <h5 class="mb-3">Segment-Backed Types</h5>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Segment No</th>
                <th>Type</th>
                <th>Type ID</th>
                <th>Level</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($segmentTypes)): ?>
                <tr><td colspan="4" class="text-muted">No segment-backed types found.</td></tr>
              <?php else: ?>
                <?php foreach ($segmentTypes as $type): ?>
                  <tr>
                    <td><?= h((string)($type['SegmentNo'] ?? '')) ?></td>
                    <td><?= h((string)($type['DataObjectTypeName'] ?? '')) ?></td>
                    <td><?= h((string)($type['DataObjectTypeID'] ?? '')) ?></td>
                    <td><?= h((string)($type['Level'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="border rounded p-3 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Candidate Rows</h5>
      <span class="text-muted small">Showing <?= h((string)min(count($candidates), 200)) ?> of <?= h((string)count($candidates)) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Parent</th>
            <th>Type</th>
            <th>Segment</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($candidates)): ?>
            <tr><td colspan="6" class="text-center text-muted">No candidate rows.</td></tr>
          <?php else: ?>
            <?php foreach (array_slice($candidates, 0, 200) as $row): ?>
              <tr>
                <td><code><?= h((string)($row['DataObjectCode'] ?? '')) ?></code></td>
                <td><?= h((string)($row['DataObjectName'] ?? '')) ?></td>
                <td><code><?= h((string)($row['DataObjectCodeParent'] ?? '')) ?></code></td>
                <td><?= h((string)($row['DataObjectTypeName'] ?? $row['DataObjectTypeID'] ?? '')) ?></td>
                <td><?= h((string)($row['SegmentNo'] ?? '')) ?> / <?= h((string)($row['SegmentCode'] ?? '')) ?></td>
                <td><?= h((string)($row['DataObjectCodeStatus'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="border rounded p-3 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Rejected Rows</h5>
      <span class="text-muted small">Showing <?= h((string)min(count($rejected), 200)) ?> of <?= h((string)count($rejected)) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Reason</th>
            <th>Code</th>
            <th>Name</th>
            <th>Parent</th>
            <th>Segment</th>
            <th>Segment Value ID</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rejected)): ?>
            <tr><td colspan="6" class="text-center text-muted">No rejected rows.</td></tr>
          <?php else: ?>
            <?php foreach (array_slice($rejected, 0, 200) as $row): ?>
              <tr>
                <td><?= h((string)($row['RejectionReason'] ?? '')) ?></td>
                <td><code><?= h((string)($row['DataObjectCode'] ?? '')) ?></code></td>
                <td><?= h((string)($row['SegmentName'] ?? $row['DataObjectName'] ?? '')) ?></td>
                <td><code><?= h((string)($row['ParentDataObjectCode'] ?? $row['DataObjectCodeParent'] ?? '')) ?></code></td>
                <td><?= h((string)($row['SegmentNo'] ?? '')) ?> / <?= h((string)($row['SegmentCode'] ?? '')) ?></td>
                <td><?= h((string)($row['SegmentValueID'] ?? '')) ?></td>
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
