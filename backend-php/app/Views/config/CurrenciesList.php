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
$_csrf = $_csrf ?? csrf_token();
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$activeCount = 0;
$inactiveCount = 0;
$systemDefaultCount = 0;
foreach ($rows as $row) {
    if ((int) ($row['IsActive'] ?? 0) === 1) {
        $activeCount++;
    } else {
        $inactiveCount++;
    }
    if ((int) ($row['IsSystemDefault'] ?? 0) === 1) {
        $systemDefaultCount++;
    }
}
$screenHeader = [
    'title' => 'Currencies',
    'icon' => 'bi-currency-dollar',
];
$exportQuery = http_build_query([
    'route' => 'currencies/exportExcel',
    'q' => (string) ($filters['q'] ?? ''),
    'active' => (string) ($filters['active'] ?? '1'),
]);
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
              <div class="text-muted small">Currencies</div>
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
              <div class="text-muted small">Inactive</div>
              <div class="fs-4 fw-semibold"><?= $inactiveCount ?></div>
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
        Use this register to maintain the shared currency master used by versions, exchange-rate maintenance, and any later fiscal or transaction features that need a controlled currency list.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="currencies/list">
            <div class="col-md-6">
              <input class="form-control" type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search currency code, name, or symbol">
            </div>
            <div class="col-md-3">
              <select name="active" class="form-select">
                <option value="" <?= (($filters['active'] ?? '') === '') ? 'selected' : '' ?>>All statuses</option>
                <option value="1" <?= (($filters['active'] ?? '1') === '1') ? 'selected' : '' ?>>Active only</option>
                <option value="0" <?= (($filters['active'] ?? '') === '0') ? 'selected' : '' ?>>Inactive only</option>
              </select>
            </div>
            <div class="col-md-1 d-grid">
              <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            </div>
            <div class="col-md-2 d-grid">
              <a class="btn btn-sm btn-outline-secondary" href="index.php?route=currencies/list">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Currency Register</h5>
          <div class="d-inline-flex gap-2 flex-wrap">
            <a href="index.php?route=currencies/downloadTemplate" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Template</a>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#currenciesUploadModal"><i class="bi bi-upload me-1"></i>Upload</button>
            <a href="index.php?<?= h($exportQuery) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-excel me-1"></i>Export</a>
            <a id="currencies-create-btn" href="index.php?route=currencies/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Currency</a>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Code</th>
                  <th>Name</th>
                  <th>Symbol</th>
                  <th class="text-end">Decimals</th>
                  <th>Status</th>
                  <th>Usage</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No currencies found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td class="fw-semibold"><?= h((string) ($row['CurrencyCode'] ?? '')) ?></td>
                      <td>
                        <div><?= h((string) ($row['CurrencyName'] ?? '')) ?></div>
                        <?php if (!empty($row['IsoNumericCode'])): ?><div class="small text-muted">ISO numeric: <?= h((string) ($row['IsoNumericCode'] ?? '')) ?></div><?php endif; ?>
                      </td>
                      <td><?= h((string) ($row['CurrencySymbol'] ?? '')) ?></td>
                      <td class="text-end"><?= (int) ($row['DecimalPlaces'] ?? 0) ?></td>
                      <td>
                        <?php if ((int) ($row['IsSystemDefault'] ?? 0) === 1): ?>
                          <span class="badge text-bg-primary me-1">System Default</span>
                        <?php endif; ?>
                        <span class="badge text-bg-<?= ((int) ($row['IsActive'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                          <?= ((int) ($row['IsActive'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                        </span>
                      </td>
                      <td>
                        <div class="small text-muted">Versions: <?= (int) ($row['VersionUsageCount'] ?? 0) ?></div>
                        <div class="small text-muted">Rates: <?= (int) ($row['RateUsageCount'] ?? 0) ?></div>
                      </td>
                      <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="index.php?route=currencies/form&code=<?= urlencode((string) ($row['CurrencyCode'] ?? '')) ?>">
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

<div class="modal fade" id="currenciesUploadModal" tabindex="-1" aria-labelledby="currenciesUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="currenciesUploadModalLabel">Upload Currencies</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php?route=currencies/uploadProcess" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= h((string) $_csrf) ?>">
          <div class="alert alert-light border small">
            Upload an Excel or CSV file using the <code>Currencies</code> sheet. Start with the template download if you need the expected column order and sample values.
          </div>
          <div class="mb-3">
            <label for="currenciesUploadFile" class="form-label">Spreadsheet File</label>
            <input type="file" class="form-control" id="currenciesUploadFile" name="uploadFile" accept=".xlsx,.xls,.csv" required>
          </div>
          <div class="small text-muted">
            Required columns: <code>CurrencyCode</code> and <code>CurrencyName</code>.
            Optional columns can maintain symbol, ISO numeric code, decimal places, system default, active flag, and sort order.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Upload Spreadsheet</button>
        </div>
      </form>
    </div>
  </div>
</div>
