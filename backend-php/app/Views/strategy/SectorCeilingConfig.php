<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../shared/csrf.php';

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('money_fmt')) {
    function money_fmt(mixed $value): string
    {
        return number_format((float) $value, 0);
    }
}

$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$summary = is_array($summary ?? null) ? $summary : [];
$records = is_array($records ?? null) ? $records : [];
$record = is_array($record ?? null) ? $record : [];
$sectorOptions = is_array($sectorOptions ?? null) ? $sectorOptions : [];
$id = (int) ($record['CeilingID'] ?? 0);
$envelopeAmount = (float) ($summary['EnvelopeAmount'] ?? 0);
$allocatedTotal = 0.0;
$plannedTotal = 0.0;
$varianceTotal = 0.0;
foreach ($records as $row) {
    $allocatedTotal += (float) ($row['CeilingAmount'] ?? 0);
    $plannedTotal += (float) ($row['PlannedAmount'] ?? 0);
    $varianceTotal += (float) ($row['VarianceAmount'] ?? 0);
}
$chartLabels = [];
$chartCeilings = [];
$chartPlans = [];
foreach ($records as $row) {
    $chartLabels[] = (string) ($row['SectorName'] ?? 'Unassigned');
    $chartCeilings[] = round((float) ($row['CeilingAmount'] ?? 0), 2);
    $chartPlans[] = round((float) ($row['PlannedAmount'] ?? 0), 2);
}

$screenHeader = [
    'title' => 'Sector Ceilings',
    'icon' => 'bi-pie-chart',
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

      <?php if (empty($supportsStrategicCeilings)): ?>
        <div class="alert alert-warning">Strategic ceiling table is not installed in this database.</div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Envelope total</div>
            <div class="fs-4 fw-semibold"><?= money_fmt($summary['EnvelopeAmount'] ?? 0) ?></div>
          </div></div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Allocated to sectors</div>
            <div class="fs-4 fw-semibold"><?= money_fmt($summary['AllocatedAmount'] ?? 0) ?></div>
          </div></div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Remaining balance</div>
            <div class="fs-4 fw-semibold <?= ((float) ($summary['RemainingAmount'] ?? 0)) < 0 ? 'text-danger' : 'text-success' ?>"><?= money_fmt($summary['RemainingAmount'] ?? 0) ?></div>
          </div></div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Over-ceiling sectors</div>
            <div class="fs-4 fw-semibold <?= ((int) ($summary['OverrunCount'] ?? 0)) > 0 ? 'text-danger' : 'text-success' ?>"><?= (int) ($summary['OverrunCount'] ?? 0) ?></div>
          </div></div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Sector ceilings are the first allocation layer from the resource envelope. Ministry ceilings are still a useful next step, but they should be maintained as a separate org-unit ceiling layer rather than being mixed into the sector rows.
      </div>

      <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6">
          <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="mb-0">Allocation Helpers</h5></div>
            <div class="card-body">
              <div class="d-flex flex-wrap gap-2 mb-3">
                <form method="post" action="index.php?route=strategy-fiscal/copy-sector-ceilings-from-plan" onsubmit="return confirm('Copy current strategic plan totals into sector ceilings and overwrite existing sector ceiling amounts?');">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <button type="submit" class="btn btn-outline-primary">Copy from Plan</button>
                </form>
                <form method="post" action="index.php?route=strategy-fiscal/allocate-remaining-sector-ceilings" class="d-flex flex-wrap gap-2 align-items-center">
                  <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                  <select name="AllocationMethod" class="form-select form-select-sm" style="min-width: 220px;">
                    <option value="EVEN">Allocate Remaining Evenly</option>
                    <option value="PLAN_SHARE">Allocate by Plan Share</option>
                    <option value="CEILING_SHARE">Allocate by Existing Ceiling Share</option>
                  </select>
                  <button type="submit" class="btn btn-outline-secondary">Allocate Remaining Balance</button>
                </form>
              </div>
              <div class="small text-muted">
                <div>Use <strong>Copy from Plan</strong> for a first-pass ceiling set based on the current strategic plan totals.</div>
                <div>Use <strong>Allocate Remaining Balance</strong> to spread any unallocated resource envelope across sectors using the selected method.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-xl-6">
          <div class="card shadow-sm h-100">
            <div class="card-header"><h5 class="mb-0">Ceiling vs Plan by Sector</h5></div>
            <div class="card-body">
              <?php if ($records === []): ?>
                <div class="text-muted small">Add sector ceilings to see the comparison chart.</div>
              <?php else: ?>
                <div style="height: 260px;">
                  <canvas id="sectorCeilingComparisonChart"></canvas>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-xl-7">
          <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
              <h5 class="mb-0">Current Sector Ceiling Allocations</h5>
              <a href="index.php?route=strategy-fiscal/sector-ceilings" class="btn btn-sm btn-primary">New Ceiling</a>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Sector</th>
                      <th class="text-end">Ceiling</th>
                      <th class="text-end">% Allocated</th>
                      <th class="text-end">Planned</th>
                      <th class="text-end">Variance</th>
                      <th class="text-center">Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php if ($records === []): ?>
                    <tr><td colspan="7" class="text-center text-muted py-3">No sector ceilings have been entered yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($records as $row): ?>
                      <?php $variance = (float) ($row['VarianceAmount'] ?? 0); ?>
                      <?php $over = (int) ($row['OverCeilingFlag'] ?? 0) === 1; ?>
                      <?php $allocationPct = $envelopeAmount > 0 ? (((float) ($row['CeilingAmount'] ?? 0) / $envelopeAmount) * 100) : 0.0; ?>
                      <tr<?= ((int) ($row['CeilingID'] ?? 0) === $id && $id > 0) ? ' class="table-primary"' : '' ?>>
                        <td>
                          <div class="fw-semibold"><?= h((string) ($row['SectorName'] ?? '')) ?></div>
                          <?php if (!empty($row['SourceSegmentCode'])): ?>
                            <div class="small text-muted"><?= h((string) $row['SourceSegmentCode']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td class="text-end"><?= money_fmt($row['CeilingAmount'] ?? 0) ?></td>
                        <td class="text-end"><?= number_format($allocationPct, 1) ?>%</td>
                        <td class="text-end"><?= money_fmt($row['PlannedAmount'] ?? 0) ?></td>
                        <td class="text-end <?= $variance < 0 ? 'text-danger' : 'text-success' ?>"><?= money_fmt($variance) ?></td>
                        <td class="text-center">
                          <span class="badge <?= $over ? 'bg-danger' : 'bg-success' ?>">
                            <?= $over ? 'Over Ceiling' : 'Within Ceiling' ?>
                          </span>
                        </td>
                        <td class="text-end">
                          <div class="d-inline-flex gap-1">
                            <a href="index.php?route=strategy-fiscal/sector-ceilings&id=<?= (int) ($row['CeilingID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
                            <form method="post" action="index.php?route=strategy-fiscal/delete-sector-ceiling" onsubmit="return confirm('Archive this sector ceiling?');">
                              <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                              <input type="hidden" name="id" value="<?= (int) ($row['CeilingID'] ?? 0) ?>">
                              <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                  </tbody>
                  <?php if ($records !== []): ?>
                    <?php $allocatedPctTotal = $envelopeAmount > 0 ? (($allocatedTotal / $envelopeAmount) * 100) : 0.0; ?>
                    <tfoot class="table-light">
                      <tr>
                        <th>Total</th>
                        <th class="text-end"><?= money_fmt($allocatedTotal) ?></th>
                        <th class="text-end"><?= number_format($allocatedPctTotal, 1) ?>%</th>
                        <th class="text-end"><?= money_fmt($plannedTotal) ?></th>
                        <th class="text-end <?= $varianceTotal < 0 ? 'text-danger' : 'text-success' ?>"><?= money_fmt($varianceTotal) ?></th>
                        <th class="text-center">Summary</th>
                        <th></th>
                      </tr>
                    </tfoot>
                  <?php endif; ?>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-5">
          <div class="card shadow-sm">
            <div class="card-header">
              <h5 class="mb-0"><?= $id > 0 ? 'Edit Sector Ceiling' : 'Add Sector Ceiling' ?></h5>
            </div>
            <div class="card-body">
              <form method="post" action="index.php?route=strategy-fiscal/save-sector-ceiling">
                <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="CeilingID" value="<?= $id ?>">

                <div class="mb-3">
                  <label class="form-label">Sector</label>
                  <select name="SectorID" class="form-select" required>
                    <option value="">Select sector</option>
                    <?php foreach ($sectorOptions as $option): ?>
                      <option value="<?= (int) ($option['SectorID'] ?? 0) ?>" <?= ((int) ($record['SectorID'] ?? 0) === (int) ($option['SectorID'] ?? 0)) ? 'selected' : '' ?>>
                        <?= h((string) ($option['SectorName'] ?? '')) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-3">
                  <label class="form-label">Current-Year Ceiling</label>
                  <input type="text" inputmode="numeric" name="CeilingAmount" id="CeilingAmount" class="form-control text-end js-sector-ceiling-number" required value="<?= h((string) ($record['CeilingAmount'] ?? '')) ?>">
                </div>

                <div class="mb-3">
                  <label class="form-label">Notes</label>
                  <textarea name="Notes" class="form-control" rows="4"><?= h((string) ($record['Notes'] ?? '')) ?></textarea>
                </div>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="ActiveFlag" id="sectorCeilingActiveFlag" <?= ((int) ($record['ActiveFlag'] ?? 1) === 1) ? 'checked' : '' ?>>
                  <label class="form-check-label" for="sectorCeilingActiveFlag">Active</label>
                </div>

                <div class="d-flex justify-content-between">
                  <a href="index.php?route=strategy-fiscal/sector-ceilings" class="btn btn-secondary">Back</a>
                  <button type="submit" class="btn btn-primary"><?= $id > 0 ? 'Save Sector Ceiling' : 'Add Sector Ceiling' ?></button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($records !== []): ?>
<script>
(function () {
  const chartCanvas = document.getElementById('sectorCeilingComparisonChart');
  if (!chartCanvas || typeof Chart === 'undefined') {
    return;
  }

  if (window.__sectorCeilingComparisonChart) {
    window.__sectorCeilingComparisonChart.destroy();
  }

  window.__sectorCeilingComparisonChart = new Chart(chartCanvas.getContext('2d'), {
    type: 'bar',
    data: {
      labels: <?= json_encode($chartLabels, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>,
      datasets: [
        {
          label: 'Ceiling',
          data: <?= json_encode($chartCeilings, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>,
          backgroundColor: '#2f6f4f',
          borderRadius: 6,
          maxBarThickness: 36
        },
        {
          label: 'Plan',
          data: <?= json_encode($chartPlans, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>,
          backgroundColor: '#c8b86a',
          borderRadius: 6,
          maxBarThickness: 36
        }
      ]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              return ctx.dataset.label + ': ' + Number(ctx.parsed.y || 0).toLocaleString();
            }
          }
        }
      },
      scales: {
        x: {
          ticks: {
            autoSkip: false,
            maxRotation: 45,
            minRotation: 0
          }
        },
        y: {
          beginAtZero: true,
          ticks: {
            callback: function (value) {
              return Number(value).toLocaleString();
            }
          }
        }
      }
    }
  });
})();
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const amountInputs = Array.from(document.querySelectorAll('.js-sector-ceiling-number'));
  if (amountInputs.length === 0) {
    return;
  }

  const parseAmount = function (value) {
    const normalized = String(value || '').replace(/,/g, '').trim();
    const parsed = parseFloat(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
  };

  const formatAmount = function (value) {
    return Number(value).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  };

  const normalizeInputValue = function (input) {
    const raw = String(input.value || '').replace(/,/g, '').trim();
    if (raw === '') {
      input.value = '';
      return;
    }
    input.value = String(Math.round(parseAmount(raw)));
  };

  const formatInputValue = function (input) {
    const raw = String(input.value || '').replace(/,/g, '').trim();
    if (raw === '') {
      input.value = '';
      return;
    }
    input.value = formatAmount(Math.round(parseAmount(raw)));
  };

  amountInputs.forEach(function (input) {
    formatInputValue(input);
    input.addEventListener('focus', function () {
      normalizeInputValue(input);
      input.select();
    });
    input.addEventListener('blur', function () {
      formatInputValue(input);
    });
  });

  const form = document.querySelector('form[action="index.php?route=strategy-fiscal/save-sector-ceiling"]');
  if (form) {
    form.addEventListener('submit', function () {
      amountInputs.forEach(function (input) {
        normalizeInputValue(input);
      });
    });
  }
});
</script>
