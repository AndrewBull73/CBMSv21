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
$sectorRows = (array) ($report['sector_rows'] ?? []);
$programRows = (array) ($report['program_rows'] ?? []);
$sectorOverrunCount = 0;
$programOverrunCount = 0;

foreach ($sectorRows as $row) {
    if ((float) ($row['VarianceAmount'] ?? 0) < 0) {
        $sectorOverrunCount++;
    }
}
foreach ($programRows as $row) {
    if ((float) ($row['VarianceAmount'] ?? 0) < 0) {
        $programOverrunCount++;
    }
}

$screenHeader = [
    'title' => 'Ceiling vs Strategic Plan',
    'icon' => 'bi-clipboard-data',
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
              <div class="text-muted small">Sector rows</div>
              <div class="fs-4 fw-semibold"><?= count($sectorRows) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Sector overruns</div>
              <div class="fs-4 fw-semibold <?= $sectorOverrunCount > 0 ? 'text-danger' : 'text-success' ?>"><?= $sectorOverrunCount ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Program rows</div>
              <div class="fs-4 fw-semibold"><?= count($programRows) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Program overruns</div>
              <div class="fs-4 fw-semibold <?= $programOverrunCount > 0 ? 'text-danger' : 'text-success' ?>"><?= $programOverrunCount ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        This screen compares approved ceilings to current strategic plan totals. Review negative variances first, then work from sector level down into the programmes driving the gap.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Sector Comparison</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Sector</th>
                  <th class="text-end">Ceiling</th>
                  <th class="text-end">Plan</th>
                  <th class="text-end">Variance</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($sectorRows === []): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No sector comparison rows available.</td></tr>
              <?php else: ?>
                <?php foreach ($sectorRows as $row): ?>
                  <?php $variance = (float) ($row['VarianceAmount'] ?? 0); ?>
                  <tr>
                    <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                    <td class="text-end"><?= money_fmt($row['CeilingAmount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['PlannedAmount'] ?? 0) ?></td>
                    <td class="text-end <?= $variance < 0 ? 'text-danger' : 'text-success' ?>"><?= money_fmt($variance) ?></td>
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
          <h5 class="mb-0">Program Comparison</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Program</th>
                  <th>Sector</th>
                  <th class="text-end">Ceiling</th>
                  <th class="text-end">Plan</th>
                  <th class="text-end">Variance</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($programRows === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No program comparison rows available.</td></tr>
              <?php else: ?>
                <?php foreach ($programRows as $row): ?>
                  <?php $variance = (float) ($row['VarianceAmount'] ?? 0); ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['ProgramName'] ?? '')) ?></div>
                      <?php if (!empty($row['ProgramCode'])): ?>
                        <div class="small text-muted"><?= h((string) $row['ProgramCode']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                    <td class="text-end"><?= money_fmt($row['CeilingAmount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['PlannedAmount'] ?? 0) ?></td>
                    <td class="text-end <?= $variance < 0 ? 'text-danger' : 'text-success' ?>"><?= money_fmt($variance) ?></td>
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
