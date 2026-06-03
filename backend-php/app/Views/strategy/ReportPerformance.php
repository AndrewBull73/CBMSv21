<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('num_fmt')) {
    function num_fmt(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return number_format((float) $value, 2);
    }
}

$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$records = is_array($records ?? null) ? $records : [];
$programs = [];
$indicators = [];
$targetCount = 0;
foreach ($records as $row) {
    $programName = trim((string) ($row['ProgramName'] ?? ''));
    if ($programName !== '') {
        $programs[$programName] = true;
    }
    $indicatorName = trim((string) ($row['IndicatorName'] ?? ''));
    if ($indicatorName !== '') {
        $indicators[$indicatorName] = true;
    }
    if ($row['TargetValue'] !== null && $row['TargetValue'] !== '') {
        $targetCount++;
    }
}
$screenHeader = [
    'title' => 'Performance Framework Report',
    'icon' => 'bi-graph-up',
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
              <div class="text-muted small">Framework rows</div>
              <div class="fs-4 fw-semibold"><?= count($records) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Programs represented</div>
              <div class="fs-4 fw-semibold"><?= count($programs) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Indicators represented</div>
              <div class="fs-4 fw-semibold"><?= count($indicators) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Rows with targets</div>
              <div class="fs-4 fw-semibold"><?= $targetCount ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        This report joins programmes, objectives, indicators, and target values into one review view. Use it to spot gaps in the performance chain for the active planning context.
      </div>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Performance Framework Register</h5>
        </div>
        <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Program / Objective</th>
              <th>Indicator</th>
              <th>Type</th>
              <th>Measure</th>
              <th>Frequency</th>
              <th class="text-end">Baseline</th>
              <th class="text-end">Target</th>
            </tr>
          </thead>
          <tbody>
          <?php if ($records === []): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No performance framework rows yet for this context.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $row): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= h((string) ($row['ProgramName'] ?? '')) ?></div>
                  <?php if (!empty($row['SubProgramName'])): ?>
                    <div class="small text-muted"><?= h((string) $row['SubProgramName']) ?></div>
                  <?php endif; ?>
                  <div class="small"><?= h((string) ($row['ObjectiveText'] ?? '')) ?></div>
                </td>
                <td>
                  <div><?= h((string) ($row['IndicatorName'] ?? '')) ?></div>
                  <?php if (!empty($row['DataSource'])): ?>
                    <div class="small text-muted"><?= h((string) $row['DataSource']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= h((string) ($row['IndicatorTypeCode'] ?? '')) ?></td>
                <td><?= h((string) ($row['UnitOfMeasure'] ?? '')) ?></td>
                <td><?= h((string) ($row['FrequencyCode'] ?? '')) ?></td>
                <td class="text-end"><?= num_fmt($row['BaselineValue'] ?? null) ?></td>
                <td class="text-end"><?= num_fmt($row['TargetValue'] ?? null) ?></td>
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
