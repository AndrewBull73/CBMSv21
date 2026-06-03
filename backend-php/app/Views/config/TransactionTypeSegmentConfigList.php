<?php
declare(strict_types=1);
/** @var array $rows */
/** @var array $transactionTypes */
/** @var array $filters */
/** @var array|null $editRow */
/** @var int $ctxFy */
/** @var int $ctxVer */

if (!function_exists('h')) {
    function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$isEdit = is_array($editRow) && !empty($editRow);
$form = [
    'TransactionTypeSegmentConfigID' => (int)($editRow['TransactionTypeSegmentConfigID'] ?? 0),
    'FiscalYearID' => (string)($editRow['FiscalYearID'] ?? ($filters['fy'] ?? ($ctxFy > 0 ? (string)$ctxFy : ''))),
    'VersionID' => (string)($editRow['VersionID'] ?? ($filters['ver'] ?? ($ctxVer > 0 ? (string)$ctxVer : ''))),
    'TransactionTypeCode' => (string)($editRow['TransactionTypeCode'] ?? ($filters['tt'] ?? '')),
    'SegmentNo' => (string)($editRow['SegmentNo'] ?? '1'),
    'VisibleFlag' => (int)($editRow['VisibleFlag'] ?? 1),
    'RequiredFlag' => (int)($editRow['RequiredFlag'] ?? 0),
    'LookupSourceType' => (string)($editRow['LookupSourceType'] ?? 'tblSegments'),
    'LookupFilter' => (string)($editRow['LookupFilter'] ?? ''),
    'DisplayOrder' => (string)($editRow['DisplayOrder'] ?? '1'),
    'ActiveFlag' => (int)($editRow['ActiveFlag'] ?? 1),
];

$returnTo = 'index.php?route=transaction-type-segment-config/list';
if (!empty($filters)) {
    $returnTo .= '&' . http_build_query($filters);
}
?>

<div class="container mt-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0"><i class="bi bi-sliders me-2"></i>Transaction Type Segment Config</h3>
      <div class="text-muted small">Admin maintenance for transaction-type-driven segment visibility and validation rules.</div>
    </div>
    <a href="index.php?route=transaction-type-segment-config/list" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-clockwise me-1"></i>Reset
    </a>
  </div>

  <div class="alert alert-info border-0 shadow-sm py-2 mb-3">
    <div class="small">This page now follows the same compact Strategy configuration layout so filters, edit forms, and row actions read consistently with the rest of the module.</div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white py-2">
      <strong><i class="bi bi-funnel me-1"></i>Filters</strong>
    </div>
    <div class="card-body">
      <form method="get" action="index.php" class="row g-2 align-items-end">
        <input type="hidden" name="route" value="transaction-type-segment-config/list">
        <div class="col-md-2">
          <label class="form-label form-label-sm">Fiscal Year ID</label>
          <input class="form-control form-control-sm" type="number" name="fy" value="<?= h((string)($filters['fy'] ?? '')) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Version ID</label>
          <input class="form-control form-control-sm" type="number" name="ver" value="<?= h((string)($filters['ver'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label form-label-sm">Transaction Type</label>
          <select name="tt" class="form-select form-select-sm">
            <option value="">All</option>
            <?php foreach ($transactionTypes as $tt): ?>
              <?php $code = (string)($tt['TransactionTypeCode'] ?? ''); ?>
              <?php $name = (string)($tt['TransactionTypeName'] ?? ''); ?>
              <option value="<?= h($code) ?>" <?= (($filters['tt'] ?? '') === $code) ? 'selected' : '' ?>>
                <?= h($code . ($name !== '' ? ' - ' . $name : '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Active</label>
          <select name="active" class="form-select form-select-sm">
            <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All</option>
            <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-search me-1"></i>Apply
          </button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header bg-white py-2">
      <strong><i class="bi bi-pencil-square me-1"></i><?= $isEdit ? 'Edit Row' : 'New Row' ?></strong>
    </div>
    <div class="card-body">
      <form method="post" action="index.php?route=transaction-type-segment-config/save" class="row g-2">
        <?= csrf_field() ?>
        <input type="hidden" name="TransactionTypeSegmentConfigID" value="<?= (int)$form['TransactionTypeSegmentConfigID'] ?>">
        <div class="col-md-2">
          <label class="form-label form-label-sm">Fiscal Year ID</label>
          <input class="form-control form-control-sm" type="number" name="FiscalYearID" value="<?= h($form['FiscalYearID']) ?>" placeholder="Blank = global">
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Version ID</label>
          <input class="form-control form-control-sm" type="number" name="VersionID" value="<?= h($form['VersionID']) ?>" placeholder="Blank = global">
        </div>
        <div class="col-md-3">
          <label class="form-label form-label-sm">Transaction Type Code</label>
          <select name="TransactionTypeCode" class="form-select form-select-sm" required>
            <option value="">Select</option>
            <?php foreach ($transactionTypes as $tt): ?>
              <?php $code = (string)($tt['TransactionTypeCode'] ?? ''); ?>
              <?php $name = (string)($tt['TransactionTypeName'] ?? ''); ?>
              <option value="<?= h($code) ?>" <?= ($form['TransactionTypeCode'] === $code) ? 'selected' : '' ?>>
                <?= h($code . ($name !== '' ? ' - ' . $name : '')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Segment No</label>
          <input class="form-control form-control-sm" type="number" min="1" max="20" name="SegmentNo" value="<?= h($form['SegmentNo']) ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label form-label-sm">Display Order</label>
          <input class="form-control form-control-sm" type="number" min="1" name="DisplayOrder" value="<?= h($form['DisplayOrder']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label form-label-sm">Lookup Source Type</label>
          <input class="form-control form-control-sm" type="text" name="LookupSourceType" value="<?= h($form['LookupSourceType']) ?>" placeholder="tblSegments">
        </div>
        <div class="col-md-6">
          <label class="form-label form-label-sm">Lookup Filter</label>
          <input class="form-control form-control-sm" type="text" name="LookupFilter" value="<?= h($form['LookupFilter']) ?>" placeholder="optional sql-like filter text">
        </div>
        <div class="col-md-3 d-flex align-items-end gap-3">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="VisibleFlag" name="VisibleFlag" <?= ((int)$form['VisibleFlag'] === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="VisibleFlag">Visible</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="RequiredFlag" name="RequiredFlag" <?= ((int)$form['RequiredFlag'] === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="RequiredFlag">Required</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="ActiveFlag" name="ActiveFlag" <?= ((int)$form['ActiveFlag'] === 1) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ActiveFlag">Active</label>
          </div>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-1"></i><?= $isEdit ? 'Update' : 'Create' ?>
          </button>
          <?php if ($isEdit): ?>
            <a href="index.php?route=transaction-type-segment-config/list" class="btn btn-outline-secondary btn-sm ms-2">Cancel Edit</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
      <strong><i class="bi bi-table me-1"></i>Rows</strong>
      <span class="badge bg-secondary"><?= count($rows) ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped table-hover table-admin table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>FY</th>
              <th>Ver</th>
              <th>Tx Type</th>
              <th>Name</th>
              <th>Seg</th>
              <th>Visible</th>
              <th>Required</th>
              <th>Lookup Source</th>
              <th>Lookup Filter</th>
              <th>Order</th>
              <th>Active</th>
              <th>Updated</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="14" class="text-center text-muted py-3">No configuration rows found.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= (int)$r['TransactionTypeSegmentConfigID'] ?></td>
                  <td><?= ($r['FiscalYearID'] === null ? '-' : (int)$r['FiscalYearID']) ?></td>
                  <td><?= ($r['VersionID'] === null ? '-' : (int)$r['VersionID']) ?></td>
                  <td><?= h((string)$r['TransactionTypeCode']) ?></td>
                  <td><?= h((string)($r['TransactionTypeName'] ?? '')) ?></td>
                  <td><?= (int)$r['SegmentNo'] ?></td>
                  <td><?= ((int)$r['VisibleFlag'] === 1) ? 'Y' : 'N' ?></td>
                  <td><?= ((int)$r['RequiredFlag'] === 1) ? 'Y' : 'N' ?></td>
                  <td><?= h((string)$r['LookupSourceType']) ?></td>
                  <td><?= h((string)($r['LookupFilter'] ?? '')) ?></td>
                  <td><?= (int)$r['DisplayOrder'] ?></td>
                  <td><?= ((int)$r['ActiveFlag'] === 1) ? 'Y' : 'N' ?></td>
                  <td><?= h((string)$r['UpdatedDate']) ?></td>
                  <td class="text-end">
                    <a class="btn btn-outline-primary btn-sm" href="index.php?route=transaction-type-segment-config/list&<?= h(http_build_query($filters)) ?>&edit_id=<?= (int)$r['TransactionTypeSegmentConfigID'] ?>">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form class="d-inline" method="post" action="index.php?route=transaction-type-segment-config/delete">
                      <?= csrf_field() ?>
                      <input type="hidden" name="TransactionTypeSegmentConfigID" value="<?= (int)$r['TransactionTypeSegmentConfigID'] ?>">
                      <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                      <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
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
