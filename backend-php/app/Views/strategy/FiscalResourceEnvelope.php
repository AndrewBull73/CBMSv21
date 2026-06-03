<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money_fmt')) {
    function money_fmt(mixed $value): string
    {
        return number_format((float) $value, 2);
    }
}

$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$summary = (array) ($report['summary'] ?? []);
$rows = (array) ($report['data_object_rows'] ?? []);
$screenHeader = [
    'title' => 'Resource Envelope',
    'icon' => 'bi-wallet2',
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
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Approved envelope</div>
              <div class="fs-4 fw-semibold"><?= money_fmt($summary['CeilingBPTotal'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Approved ceiling lines</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['CeilingLineCount'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Scoped DataObjects</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['DataObjectCount'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Average month</div>
              <div class="fs-4 fw-semibold"><?= money_fmt(((float) ($summary['CeilingBPTotal'] ?? 0)) / 12) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        This view shows the approved ceiling envelope across the current scope. Use it to confirm overall envelope shape and monthly spread before reviewing sector ceilings or detailed plan variances.
      </div>

      <div class="row g-4 mb-4">
        <div class="col-12 col-xl-4">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0">Envelope Summary</h5>
            </div>
            <div class="card-body">
              <div class="mb-2"><strong>Approved ceiling lines:</strong> <?= (int) ($summary['CeilingLineCount'] ?? 0) ?></div>
              <div class="mb-2"><strong>Scoped DataObjects:</strong> <?= (int) ($summary['DataObjectCount'] ?? 0) ?></div>
              <div class="mb-0"><strong>Total approved envelope:</strong> <?= money_fmt($summary['CeilingBPTotal'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-8">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0">Monthly Approved Envelope</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <?php for ($month = 1; $month <= 12; $month++): ?>
                  <?php $key = 'CeilingBP' . $month; ?>
                  <div class="col-6 col-lg-3">
                    <div class="card shadow-sm h-100">
                      <div class="card-body py-3">
                        <div class="text-muted small">BP<?= $month ?></div>
                        <div class="fw-semibold"><?= money_fmt($summary[$key] ?? 0) ?></div>
                      </div>
                    </div>
                  </div>
                <?php endfor; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Envelope by DataScope</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>DataScope</th>
                  <th class="text-end">Ceiling Lines</th>
                  <th class="text-end">BP1</th>
                  <th class="text-end">BP2</th>
                  <th class="text-end">BP3</th>
                  <th class="text-end">BP4</th>
                  <th class="text-end">BP5</th>
                  <th class="text-end">BP6</th>
                  <th class="text-end">BP7</th>
                  <th class="text-end">BP8</th>
                  <th class="text-end">BP9</th>
                  <th class="text-end">BP10</th>
                  <th class="text-end">BP11</th>
                  <th class="text-end">BP12</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="15" class="text-center text-muted py-3">No approved ceiling rows found for this context.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['DataObjectName'] ?? '')) ?></div>
                      <?php if (!empty($row['DataObjectCode'])): ?>
                        <div class="small text-muted"><?= h((string) $row['DataObjectCode']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><?= (int) ($row['CeilingLineCount'] ?? 0) ?></td>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                      <?php $key = 'CeilingBP' . $month; ?>
                      <td class="text-end"><?= money_fmt($row[$key] ?? 0) ?></td>
                    <?php endfor; ?>
                    <td class="text-end fw-semibold"><?= money_fmt($row['CeilingBPTotal'] ?? 0) ?></td>
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
