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
$years = (array) (($matrix ?? [])['years'] ?? []);
$matrixRows = (array) (($matrix ?? [])['rows'] ?? []);
$summaryRows = is_array($summaryRows ?? null) ? $summaryRows : [];
$selectedProjectId = (int) ($selectedProjectId ?? 0);
$summaryTotal = 0.0;
foreach ($summaryRows as $row) {
    $summaryTotal += (float) ($row['TotalAmount'] ?? 0);
}

$screenHeader = [
    'title' => 'MTFF View',
    'icon' => 'bi-calendar-range',
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
              <div class="text-muted small">Summary rows</div>
              <div class="fs-4 fw-semibold"><?= count($summaryRows) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Sector rows</div>
              <div class="fs-4 fw-semibold"><?= count($matrixRows) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Years in window</div>
              <div class="fs-4 fw-semibold"><?= count($years) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Total in summary</div>
              <div class="fs-4 fw-semibold"><?= money_fmt($summaryTotal) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        This report shows the rolling MTFF window aligned to the current fiscal context and version label. Use the project filter when you need a narrower view before reviewing the sector matrix.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Filter</h5>
        </div>
        <div class="card-body">
          <form method="get" class="row g-2">
            <input type="hidden" name="route" value="strategy-reports/mtff">
            <div class="col-md-6">
              <select name="project_id" class="form-select">
                <option value="">All projects</option>
                <?php foreach (($projectOptions ?? []) as $option): ?>
                  <option value="<?= (int) $option['ProjectID'] ?>" <?= $selectedProjectId === (int) $option['ProjectID'] ? 'selected' : '' ?>>
                    <?= h((string) (($option['ProjectCode'] ?? '') !== '' ? $option['ProjectCode'] . ' / ' : '') . ($option['ProjectName'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 d-flex gap-2">
              <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
              <a href="index.php?route=strategy-reports/mtff" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">MTFF Summary</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Fiscal Year</th>
                  <th>Version</th>
                  <th class="text-end">Programs</th>
                  <th class="text-end">Activities</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($summaryRows === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No MTFF rows found for this version label.</td></tr>
              <?php else: ?>
                <?php foreach ($summaryRows as $row): ?>
                  <tr>
                    <td><?= h((string) ($row['YearLabel'] ?? '')) ?></td>
                    <td><?= h((string) ($row['VersionLabel'] ?? '')) ?></td>
                    <td class="text-end"><?= (int) ($row['ProgramCount'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($row['ActivityCount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['TotalAmount'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Sector Matrix</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Sector</th>
                  <?php foreach ($years as $year): ?>
                    <th class="text-end"><?= h((string) ($year['YearLabel'] ?? '')) ?></th>
                  <?php endforeach; ?>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($matrixRows === []): ?>
                <tr><td colspan="<?= max(2, count($years) + 2) ?>" class="text-center text-muted py-3">No sector MTFF rows yet.</td></tr>
              <?php else: ?>
                <?php foreach ($matrixRows as $row): ?>
                  <tr>
                    <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                    <?php foreach ($years as $year): ?>
                      <?php $fyId = (int) ($year['FiscalYearID'] ?? 0); ?>
                      <td class="text-end"><?= money_fmt($row['Amounts'][$fyId] ?? 0) ?></td>
                    <?php endforeach; ?>
                    <td class="text-end fw-semibold"><?= money_fmt($row['TotalAmount'] ?? 0) ?></td>
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
