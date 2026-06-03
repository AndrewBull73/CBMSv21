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
$currencies = is_array($currencies ?? null) ? $currencies : [];
$contextLabels = is_array($contextLabels ?? null) ? $contextLabels : [];
$_csrf = $_csrf ?? csrf_token();
$yearLabel = trim((string) ($contextLabels['YearLabel'] ?? ''));
$versionLabel = trim((string) ($contextLabels['VersionLabel'] ?? ''));
$activeCount = 0;
$pairMap = [];
$latestRateDate = '';
foreach ($rows as $row) {
    if ((int) ($row['IsActive'] ?? 0) === 1) {
        $activeCount++;
    }
    $pairKey = (string) ($row['FromCurrencyCode'] ?? '') . '>' . (string) ($row['ToCurrencyCode'] ?? '');
    $pairMap[$pairKey] = true;
    $rateDate = (string) ($row['RateDate'] ?? '');
    if ($rateDate !== '' && ($latestRateDate === '' || strcmp($rateDate, $latestRateDate) > 0)) {
        $latestRateDate = $rateDate;
    }
}
$screenHeader = [
    'title' => 'Currency Rates',
    'icon' => 'bi-currency-exchange',
];
$exportQuery = http_build_query([
    'route' => 'currency-rates/exportExcel',
    'q' => (string) ($filters['q'] ?? ''),
    'from_currency_code' => (string) ($filters['from_currency_code'] ?? ''),
    'to_currency_code' => (string) ($filters['to_currency_code'] ?? ''),
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
              <div class="text-muted small">Rates</div>
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
              <div class="text-muted small">Currency Pairs</div>
              <div class="fs-4 fw-semibold"><?= count($pairMap) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Latest Rate Date</div>
              <div class="fs-4 fw-semibold"><?= h($latestRateDate !== '' ? $latestRateDate : '-') ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this register to maintain exchange rates between active currencies. Keep rate dates and rate types deliberate so later integrations or calculations can distinguish spot, budget, or policy rates cleanly.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Filters</h5>
        </div>
        <div class="card-body">
          <form method="get" action="index.php" class="row g-2">
            <input type="hidden" name="route" value="currency-rates/list">
            <div class="col-md-3">
              <select name="from_currency_code" class="form-select">
                <option value="">All from currencies</option>
                <?php foreach ($currencies as $currency): ?>
                  <?php $currencyCode = trim((string) ($currency['CurrencyCode'] ?? '')); ?>
                  <option value="<?= h($currencyCode) ?>" <?= (($filters['from_currency_code'] ?? '') === $currencyCode) ? 'selected' : '' ?>>
                    <?= h($currencyCode . ' - ' . (string) ($currency['CurrencyName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <select name="to_currency_code" class="form-select">
                <option value="">All to currencies</option>
                <?php foreach ($currencies as $currency): ?>
                  <?php $currencyCode = trim((string) ($currency['CurrencyCode'] ?? '')); ?>
                  <option value="<?= h($currencyCode) ?>" <?= (($filters['to_currency_code'] ?? '') === $currencyCode) ? 'selected' : '' ?>>
                    <?= h($currencyCode . ' - ' . (string) ($currency['CurrencyName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <input class="form-control" type="text" name="q" value="<?= h((string) ($filters['q'] ?? '')) ?>" placeholder="Search type, source, or notes">
            </div>
            <div class="col-md-2">
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
              <a class="btn btn-sm btn-outline-secondary" href="index.php?route=currency-rates/list">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Rate Register</h5>
          <div class="d-inline-flex gap-2 flex-wrap">
            <a href="index.php?route=currency-rates/downloadTemplate" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Template</a>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#currencyRatesUploadModal"><i class="bi bi-upload me-1"></i>Upload</button>
            <a href="index.php?<?= h($exportQuery) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-excel me-1"></i>Export</a>
            <a id="currency-rates-create-btn" href="index.php?route=currency-rates/form" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Currency Rate</a>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Pair</th>
                  <th>Rate Date</th>
                  <th>Type</th>
                  <th class="text-end">Rate</th>
                  <th>Source</th>
                  <th>Status</th>
                  <th class="text-end">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($rows === []): ?>
                  <tr><td colspan="7" class="text-center text-muted py-3">No currency rates found.</td></tr>
                <?php else: ?>
                  <?php foreach ($rows as $row): ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= h((string) ($row['FromCurrencyCode'] ?? '')) ?> to <?= h((string) ($row['ToCurrencyCode'] ?? '')) ?></div>
                        <div class="small text-muted"><?= h((string) ($row['FromCurrencyName'] ?? '')) ?> to <?= h((string) ($row['ToCurrencyName'] ?? '')) ?></div>
                      </td>
                      <td><?= h((string) ($row['RateDate'] ?? '')) ?></td>
                      <td><?= h((string) ($row['RateType'] ?? '')) ?></td>
                      <td class="text-end"><?= h(number_format((float) ($row['RateValue'] ?? 0), 8, '.', ',')) ?></td>
                      <td>
                        <div><?= h((string) ($row['RateSource'] ?? '')) ?></div>
                        <?php if (!empty($row['Notes'])): ?><div class="small text-muted"><?= h((string) ($row['Notes'] ?? '')) ?></div><?php endif; ?>
                      </td>
                      <td>
                        <span class="badge text-bg-<?= ((int) ($row['IsActive'] ?? 0) === 1) ? 'success' : 'secondary' ?>">
                          <?= ((int) ($row['IsActive'] ?? 0) === 1) ? 'Active' : 'Inactive' ?>
                        </span>
                      </td>
                      <td class="text-end">
                        <a class="btn btn-outline-primary btn-sm" href="index.php?route=currency-rates/form&id=<?= (int) ($row['CurrencyRateID'] ?? 0) ?>">
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

<div class="modal fade" id="currencyRatesUploadModal" tabindex="-1" aria-labelledby="currencyRatesUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="currencyRatesUploadModalLabel">Upload Currency Rates</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" action="index.php?route=currency-rates/uploadProcess" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="_csrf" value="<?= h((string) $_csrf) ?>">
          <div class="alert alert-light border small">
            Upload an Excel or CSV file using the <code>CurrencyRates</code> sheet. Start with the template download if you need the expected column order and examples.
          </div>
          <div class="mb-3">
            <label for="currencyRatesUploadFile" class="form-label">Spreadsheet File</label>
            <input type="file" class="form-control" id="currencyRatesUploadFile" name="uploadFile" accept=".xlsx,.xls,.csv" required>
          </div>
          <div class="small text-muted">
            Required columns: <code>FromCurrencyCode</code>, <code>ToCurrencyCode</code>, <code>RateDate</code>, and <code>RateValue</code>.
            Optional columns can maintain rate type, source, notes, and active flag. Import updates matching pair, date, and rate type rows.
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
