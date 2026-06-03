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
$rows = (array) ($report['rows'] ?? []);
$screenHeader = [
    'title' => 'Sector Ceilings',
    'icon' => 'bi-buildings',
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
              <div class="text-muted small">Sectors in comparison</div>
              <div class="fs-4 fw-semibold"><?= (int) ($summary['SectorCount'] ?? 0) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Over ceiling</div>
              <div class="fs-4 fw-semibold <?= ((int) ($summary['OverrunCount'] ?? 0)) > 0 ? 'text-danger' : 'text-success' ?>">
                <?= (int) ($summary['OverrunCount'] ?? 0) ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this view to compare sector ceilings against current strategic plan totals. Resolve any over-ceiling sectors here before moving into deeper programme or report review.
      </div>

      <div class="card shadow-sm mb-0">
        <div class="card-header">
          <h5 class="mb-0">Sector Ceiling Comparison</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Sector</th>
                  <th class="text-end">Ceiling Lines</th>
                  <th class="text-end">Approved Ceiling</th>
                  <th class="text-end">Strategic Plan</th>
                  <th class="text-end">Variance</th>
                  <th class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No sector ceiling comparison available for this context.</td></tr>
              <?php else: ?>
                <?php foreach ($rows as $row): ?>
                  <?php $variance = (float) ($row['VarianceAmount'] ?? 0); ?>
                  <?php $over = (int) ($row['OverCeilingFlag'] ?? 0) === 1; ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['SectorName'] ?? '')) ?></div>
                      <?php if (!empty($row['SourceSegmentCode'])): ?>
                        <div class="small text-muted"><?= h((string) $row['SourceSegmentCode']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><?= (int) ($row['CeilingLineCount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['CeilingAmount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['PlannedAmount'] ?? 0) ?></td>
                    <td class="text-end <?= $variance < 0 ? 'text-danger' : 'text-success' ?>"><?= money_fmt($variance) ?></td>
                    <td class="text-center">
                      <span class="badge <?= $over ? 'bg-danger' : 'bg-success' ?>">
                        <?= $over ? 'Over Ceiling' : 'Within Ceiling' ?>
                      </span>
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
