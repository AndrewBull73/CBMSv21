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
        return number_format((float) $value, 0);
    }
}

$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$periodLabels = is_array($periodLabels ?? null) ? $periodLabels : [];
$bpLabels = is_array($periodLabels['BP'] ?? null) ? $periodLabels['BP'] : [];
$outerYearLabels = is_array($periodLabels['OuterYear'] ?? null) ? $periodLabels['OuterYear'] : [];
$fundingTypeSegmentNo = (int) ($fundingTypeMapping['SegmentNo'] ?? 0);
$fundingSourceSegmentNo = (int) ($fundingSourceMapping['SegmentNo'] ?? 0);
$currentYearAmount = (float) ($summary['CurrentYearAmount'] ?? 0);
$outerYear1Amount = (float) ($summary['OuterYear1Amount'] ?? 0);
$outerYear2Amount = (float) ($summary['OuterYear2Amount'] ?? 0);
$outerYear3Amount = (float) ($summary['OuterYear3Amount'] ?? 0);
$outerYear4Amount = (float) ($summary['OuterYear4Amount'] ?? 0);
$outerYear5Amount = (float) ($summary['OuterYear5Amount'] ?? 0);
$outerYearsTotal = $outerYear1Amount + $outerYear2Amount + $outerYear3Amount + $outerYear4Amount + $outerYear5Amount;
$horizonTotal = $currentYearAmount + $outerYearsTotal;
$bpTotal = 0.0;
$bpChartLabels = [];
$bpChartValues = [];
for ($i = 1; $i <= 12; $i++) {
    $key = 'BP' . $i . 'Amount';
    $value = (float) ($summary[$key] ?? 0);
    $bpTotal += $value;
    $bpChartLabels[] = (string) ($bpLabels[$i] ?? ('BP' . $i));
    $bpChartValues[] = $value;
}
$bpVariance = $currentYearAmount - $bpTotal;
$horizonChartLabels = [$yearLabel !== '' ? $yearLabel : 'Current Year'];
$horizonChartValues = [$currentYearAmount];
for ($i = 1; $i <= 5; $i++) {
    $amount = (float) ($summary['OuterYear' . $i . 'Amount'] ?? 0);
    if ($i <= 2 || !empty($resourceEnvelopeOutYearsReady)) {
        $horizonChartLabels[] = (string) ($outerYearLabels[$i] ?? ('Outer Year ' . $i));
        $horizonChartValues[] = $amount;
    }
}
$horizonChartLabelsJson = json_encode($horizonChartLabels, JSON_UNESCAPED_SLASHES);
$horizonChartValuesJson = json_encode($horizonChartValues, JSON_UNESCAPED_SLASHES);
$bpChartLabelsJson = json_encode($bpChartLabels, JSON_UNESCAPED_SLASHES);
$bpChartValuesJson = json_encode($bpChartValues, JSON_UNESCAPED_SLASHES);
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><?= h(__t('strategy_resource_envelope_summary')) ?></h3>
      <div class="d-flex flex-wrap gap-2">
        <a href="index.php?route=strategy-fiscal/overview" id="resource-envelope-summary-overview-btn" class="btn btn-sm btn-outline-secondary"><?= h(__t('strategy_fiscal_overview')) ?></a>
        <a href="index.php?route=strategy-fiscal/resource-envelope-lines" id="resource-envelope-summary-lines-btn" class="btn btn-sm btn-outline-secondary"><?= h(__t('strategy_resource_envelope')) ?></a>
        <a href="index.php?route=strategy-fiscal/resource-envelope-form" id="resource-envelope-summary-add-btn" class="btn btn-sm btn-primary" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'aria-disabled="true"' : '' ?>><?= h(__t('strategy_add_envelope_line')) ?></a>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">
        <?= h(__t('strategy_available_funds_for')) ?>
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span><strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

  <div class="alert alert-info border-0 shadow-sm mb-4">
    <?= h(__t('strategy_resource_envelope_summary_intro')) ?>
  </div>

  <?php if (!$resourceEnvelopeInstalled): ?>
    <div class="alert alert-warning"><?= h(__t('strategy_resource_envelope_install_warning')) ?> <code>create_tblSbResourceEnvelope.sql</code> <?= h(__t('strategy_is_run')) ?></div>
  <?php endif; ?>

  <?php if ($resourceEnvelopeInstalled && !$mappingReady): ?>
    <div class="alert alert-warning">
      <?= h(__t('strategy_resource_envelope_mapping_warning')) ?>
      <?= h(__t('strategy_funding_type_segment')) ?>:
      <strong><?= $fundingTypeSegmentNo > 0 ? (string) $fundingTypeSegmentNo : __t('strategy_not_mapped') ?></strong>.
      <?= h(__t('strategy_funding_source_segment')) ?>:
      <strong><?= $fundingSourceSegmentNo > 0 ? (string) $fundingSourceSegmentNo : __t('strategy_not_mapped') ?></strong>.
    </div>
  <?php endif; ?>
  <?php if ($resourceEnvelopeInstalled && empty($resourceEnvelopeMtffReady)): ?>
    <div class="alert alert-warning">
      <?= h(__t('strategy_mtff_attributes_not_available')) ?> <code>alter_tblSbResourceEnvelope_mtff_attributes_and_outyears.sql</code> <?= h(__t('strategy_to_enable_mtff_attributes')) ?>
    </div>
  <?php endif; ?>
  <?php if ($resourceEnvelopeInstalled && empty($resourceEnvelopeOutYearsReady)): ?>
    <div class="alert alert-warning">
      <?= h(__t('strategy_outer_years_not_available')) ?> <code>alter_tblSbResourceEnvelope_mtff_attributes_and_outyears.sql</code> <?= h(__t('strategy_to_enable_outer_years')) ?>
    </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <a href="index.php?route=strategy-fiscal/resource-envelope-form" class="text-decoration-none text-reset">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-muted small"><?= h(__t('strategy_current_year_total')) ?></div>
          <div class="fs-4 fw-semibold"><?= money_fmt($currentYearAmount) ?></div>
          <div class="small text-primary mt-2"><?= h(__t('strategy_add_resource_envelope_line')) ?></div>
        </div></div>
      </a>
    </div>
    <div class="col-6 col-xl-3">
      <a href="index.php?route=strategy-fiscal/resource-envelope-form" class="text-decoration-none text-reset">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-muted small"><?= h(__t('strategy_outer_years_total')) ?></div>
          <div class="fs-4 fw-semibold"><?= money_fmt($outerYearsTotal) ?></div>
          <div class="small text-primary mt-2"><?= h(__t('strategy_add_resource_envelope_line')) ?></div>
        </div></div>
      </a>
    </div>
    <div class="col-6 col-xl-3">
      <a href="index.php?route=strategy-fiscal/resource-envelope-form" class="text-decoration-none text-reset">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-muted small"><?= h(__t('strategy_total_horizon')) ?></div>
          <div class="fs-4 fw-semibold"><?= money_fmt($horizonTotal) ?></div>
          <div class="small text-primary mt-2"><?= h(__t('strategy_add_resource_envelope_line')) ?></div>
        </div></div>
      </a>
    </div>
    <div class="col-6 col-xl-3">
      <a href="index.php?route=strategy-fiscal/resource-envelope-form" class="text-decoration-none text-reset">
        <div class="card shadow-sm h-100"><div class="card-body">
          <div class="text-muted small"><?= h(__t('strategy_active_envelope_lines')) ?></div>
          <div class="fs-4 fw-semibold"><?= (int) ($summary['LineCount'] ?? 0) ?></div>
          <div class="small text-primary mt-2"><?= h(__t('strategy_add_resource_envelope_line')) ?></div>
        </div></div>
      </a>
    </div>
  </div>

  <?php if (!empty($resourceEnvelopeOutYearsReady)): ?>
    <div class="row g-3 mb-4">
      <?php for ($i = 3; $i <= 5; $i++): $key = 'OuterYear' . $i . 'Amount'; ?>
        <div class="col-6 col-xl-4">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small"><?= h((string) ($outerYearLabels[$i] ?? ('Outer Year ' . $i))) ?></div>
              <div class="fs-5 fw-semibold"><?= money_fmt($summary[$key] ?? 0) ?></div>
            </div>
          </div>
        </div>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><?= h(__t('strategy_horizon_profile')) ?></h5>
          <span class="badge text-bg-light border"><?= count($horizonChartLabels) ?> <?= h(__t('strategy_periods')) ?></span>
        </div>
        <div class="card-body">
          <div style="height: 320px;">
            <canvas id="resourceEnvelopeHorizonChart"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-5">
      <div class="card shadow-sm h-100">
        <div class="card-header">
          <h5 class="mb-0"><?= h(__t('strategy_horizon_insights')) ?></h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6">
              <div class="border rounded p-3 h-100">
                <div class="small text-muted"><?= h(__t('strategy_current_year_share')) ?></div>
                <div class="fs-5 fw-semibold">
                  <?= $horizonTotal > 0 ? money_fmt(($currentYearAmount / $horizonTotal) * 100) : '0' ?>%
                </div>
              </div>
            </div>
            <div class="col-6">
              <div class="border rounded p-3 h-100">
                <div class="small text-muted"><?= h(__t('strategy_bp_variance')) ?></div>
                <div class="fs-5 fw-semibold <?= abs($bpVariance) < 0.5 ? 'text-success' : 'text-danger' ?>">
                  <?= money_fmt($bpVariance) ?>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="border rounded p-3">
                <div class="small text-muted mb-2"><?= h(__t('strategy_summary_observation')) ?></div>
                <div class="small">
                  <?php if ($horizonTotal <= 0): ?>
                    <?= h(__t('strategy_no_envelope_entered_yet')) ?>
                  <?php elseif (abs($bpVariance) >= 0.5): ?>
                    <?= h(__t('strategy_bp_variance_observation')) ?>
                  <?php else: ?>
                    <?= h(__t('strategy_bp_balanced_observation')) ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <div class="col-12">
              <a href="index.php?route=strategy-fiscal/resource-envelope-form" id="resource-envelope-summary-body-add-btn" class="btn btn-primary btn-sm"><?= h(__t('strategy_add_resource_envelope_line')) ?></a>
              <a href="index.php?route=strategy-fiscal/resource-envelope-lines" id="resource-envelope-summary-body-open-btn" class="btn btn-outline-secondary btn-sm"><?= h(__t('strategy_open_resource_envelope')) ?></a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-8">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><?= h(__t('strategy_bp_phasing_totals')) ?></h5>
          <a href="index.php?route=strategy-fiscal/resource-envelope-form" id="resource-envelope-summary-chart-add-btn" class="btn btn-outline-primary btn-sm"><?= h(__t('strategy_add_resource_envelope_line')) ?></a>
        </div>
        <div class="card-body">
          <div style="height: 320px;">
            <canvas id="resourceEnvelopeBpChart"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-4">
      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0"><?= h(__t('strategy_bp_totals_table')) ?></h5>
        </div>
        <div class="card-body">
          <div class="row g-2">
            <?php for ($i = 1; $i <= 12; $i++): $key = 'BP' . $i . 'Amount'; ?>
              <div class="col-6">
                <div class="border rounded p-2 h-100">
                  <div class="text-muted small"><?= h((string) ($bpLabels[$i] ?? ('BP' . $i))) ?></div>
                  <div class="fw-semibold"><?= money_fmt($summary[$key] ?? 0) ?></div>
                </div>
              </div>
            <?php endfor; ?>
            <div class="col-12">
              <div class="border rounded p-2 mt-2 bg-light-subtle">
                <div class="d-flex justify-content-between small">
                  <span><?= h(__t('strategy_bp_total')) ?></span>
                  <strong><?= money_fmt($bpTotal) ?></strong>
                </div>
                <div class="d-flex justify-content-between small mt-1">
                  <span><?= h(__t('strategy_difference_to_current_year')) ?></span>
                  <strong class="<?= abs($bpVariance) < 0.5 ? 'text-success' : 'text-danger' ?>"><?= money_fmt($bpVariance) ?></strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
</div>

<script>
(function () {
  const horizonLabels = <?= $horizonChartLabelsJson ?>;
  const horizonValues = <?= $horizonChartValuesJson ?>;
  const bpLabels = <?= $bpChartLabelsJson ?>;
  const bpValues = <?= $bpChartValuesJson ?>;

  function renderCharts() {
    const horizonCanvas = document.getElementById('resourceEnvelopeHorizonChart');
    const bpCanvas = document.getElementById('resourceEnvelopeBpChart');
    if (!horizonCanvas || !bpCanvas) {
      return;
    }

    if (window.__resourceEnvelopeHorizonChart) {
      window.__resourceEnvelopeHorizonChart.destroy();
    }
    if (window.__resourceEnvelopeBpChart) {
      window.__resourceEnvelopeBpChart.destroy();
    }

    window.__resourceEnvelopeHorizonChart = new Chart(horizonCanvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: horizonLabels,
        datasets: [{
          label: '<?= h(__t('strategy_resource_envelope')) ?>',
          data: horizonValues,
          backgroundColor: ['#2f6f4f', '#6c8f53', '#97a95b', '#c8b86a', '#d7a95b', '#bf7f3f'],
          borderRadius: 6,
          maxBarThickness: 48
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return ctx.dataset.label + ': ' + Number(ctx.parsed.y || 0).toLocaleString();
              }
            }
          }
        },
        scales: {
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

    window.__resourceEnvelopeBpChart = new Chart(bpCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels: bpLabels,
        datasets: [{
          label: '<?= h(__t('strategy_bp_phasing_totals')) ?>',
          data: bpValues,
          borderColor: '#285e61',
          backgroundColor: 'rgba(40, 94, 97, 0.15)',
          fill: true,
          tension: 0.25,
          pointRadius: 3,
          pointHoverRadius: 4
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                return '<?= h(__t('strategy_amount')) ?>: ' + Number(ctx.parsed.y || 0).toLocaleString();
              }
            }
          }
        },
        scales: {
          x: {
            ticks: {
              maxRotation: 0,
              autoSkip: true
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
  }

  if (typeof window.Chart === 'undefined') {
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    s.onload = renderCharts;
    document.head.appendChild(s);
  } else {
    renderCharts();
  }
})();
</script>
