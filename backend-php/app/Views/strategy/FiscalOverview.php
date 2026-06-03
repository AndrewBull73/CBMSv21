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
$cards = (array) ($dashboard['cards'] ?? []);
$monthly = (array) ($dashboard['monthly'] ?? []);
$topDataObjects = (array) ($dashboard['top_data_objects'] ?? []);
$sectorRows = (array) ($dashboard['sector_rows'] ?? []);
$programRows = (array) ($dashboard['program_rows'] ?? []);
$headroom = (float) ($cards['HeadroomAmount'] ?? 0);

$screenHeader = [
    'title' => 'Fiscal Overview',
    'icon' => 'bi-bar-chart-line',
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
              <div class="text-muted small">Approved ceiling total</div>
              <div class="fs-4 fw-semibold"><?= money_fmt($cards['ApprovedCeilingTotal'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Strategic plan total</div>
              <div class="fs-4 fw-semibold"><?= money_fmt($cards['StrategicPlanTotal'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Headroom / gap</div>
              <div class="fs-4 fw-semibold <?= $headroom < 0 ? 'text-danger' : 'text-success' ?>"><?= money_fmt($headroom) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Approved ceiling lines</div>
              <div class="fs-4 fw-semibold"><?= (int) ($cards['ApprovedCeilingLines'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this overview to check the fiscal position for the active context before drilling into sector ceilings, resource envelope detail, or ceiling-versus-plan analysis. Focus first on headroom and the largest negative variances.
      </div>

      <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0">Envelope Snapshot</h5>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-6">
                  <div class="text-muted small">Scoped DataObjects</div>
                  <div class="fs-5 fw-semibold"><?= (int) ($cards['ScopedDataObjects'] ?? 0) ?></div>
                </div>
                <div class="col-6">
                  <div class="text-muted small">Sector overruns</div>
                  <div class="fs-5 fw-semibold <?= ((int) ($cards['SectorOverruns'] ?? 0)) > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= (int) ($cards['SectorOverruns'] ?? 0) ?>
                  </div>
                </div>
                <div class="col-6">
                  <div class="text-muted small">Program overruns</div>
                  <div class="fs-5 fw-semibold <?= ((int) ($cards['ProgramOverruns'] ?? 0)) > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= (int) ($cards['ProgramOverruns'] ?? 0) ?>
                  </div>
                </div>
                <div class="col-6">
                  <div class="text-muted small">Monthly average ceiling</div>
                  <div class="fs-5 fw-semibold"><?= money_fmt(((float) ($monthly['CeilingBPTotal'] ?? 0)) / 12) ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-6">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0">Monthly Envelope</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Month</th>
                      <th class="text-end">Approved Ceiling</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php for ($month = 1; $month <= 12; $month++): ?>
                      <?php $key = 'CeilingBP' . $month; ?>
                      <tr>
                        <td>BP<?= $month ?></td>
                        <td class="text-end"><?= money_fmt($monthly[$key] ?? 0) ?></td>
                      </tr>
                    <?php endfor; ?>
                    <tr class="table-light fw-semibold">
                      <td>Total</td>
                      <td class="text-end"><?= money_fmt($monthly['CeilingBPTotal'] ?? 0) ?></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-12 col-xl-4">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0">Top Envelope Scopes</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>DataScope</th>
                      <th class="text-end">Ceiling</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php if ($topDataObjects === []): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No approved ceiling rows found.</td></tr>
                  <?php else: ?>
                    <?php foreach ($topDataObjects as $row): ?>
                      <tr>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($row['DataObjectName'] ?? '')) ?></div>
                          <?php if (!empty($row['DataObjectCode'])): ?>
                            <div class="small text-muted"><?= h((string) $row['DataObjectCode']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-end"><?= money_fmt($row['CeilingBPTotal'] ?? 0) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12 col-xl-4">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0">Largest Sector Variances</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Sector</th>
                      <th class="text-end">Variance</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php if ($sectorRows === []): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No sector ceiling comparison available.</td></tr>
                  <?php else: ?>
                    <?php foreach ($sectorRows as $row): ?>
                      <?php $variance = (float) ($row['VarianceAmount'] ?? 0); ?>
                      <tr>
                        <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
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

        <div class="col-12 col-xl-4">
          <div class="card shadow-sm h-100">
            <div class="card-header">
              <h5 class="mb-0">Largest Program Variances</h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Program</th>
                      <th class="text-end">Variance</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php if ($programRows === []): ?>
                    <tr><td colspan="2" class="text-center text-muted py-3">No program ceiling comparison available.</td></tr>
                  <?php else: ?>
                    <?php foreach ($programRows as $row): ?>
                      <?php $variance = (float) ($row['VarianceAmount'] ?? 0); ?>
                      <tr>
                        <td><?= h((string) ($row['ProgramName'] ?? '')) ?></td>
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
  </div>
</div>
