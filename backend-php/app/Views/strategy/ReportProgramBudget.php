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
$records = is_array($records ?? null) ? $records : [];
$selectedProjectId = (int) ($selectedProjectId ?? 0);
$totalAmount = 0.0;
$ownerNames = [];
foreach ($records as $row) {
    $totalAmount += (float) ($row['TotalAmount'] ?? 0);
    $ownerName = trim((string) ($row['OrgUnitName'] ?? ''));
    if ($ownerName !== '') {
        $ownerNames[$ownerName] = true;
    }
}

$screenHeader = [
    'title' => 'Program Budget Report',
    'icon' => 'bi-table',
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
              <div class="text-muted small">Program rows</div>
              <div class="fs-4 fw-semibold"><?= count($records) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Total amount</div>
              <div class="fs-4 fw-semibold"><?= money_fmt($totalAmount) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Owners represented</div>
              <div class="fs-4 fw-semibold"><?= count($ownerNames) ?></div>
            </div>
          </div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100">
            <div class="card-body">
              <div class="text-muted small">Project filter</div>
              <div class="fs-4 fw-semibold"><?= $selectedProjectId > 0 ? 'Scoped' : 'All' ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        Use this report to compare program-level budget structure across sectors, owners, and activity depth. Apply the project filter when you need to isolate investment-linked activity before export or review.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">Filter</h5>
        </div>
        <div class="card-body">
          <form method="get" class="row g-2">
            <input type="hidden" name="route" value="strategy-reports/program-budget">
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
              <a href="index.php?route=strategy-reports/program-budget" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header">
          <h5 class="mb-0">Program Budget Register</h5>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Program</th>
                  <th>Sector</th>
                  <th>Owner</th>
                  <th class="text-end">Outputs</th>
                  <th class="text-end">Activities</th>
                  <th class="text-end">Economic Lines</th>
                  <th class="text-end">Funding Sources</th>
                  <th class="text-end">Amount</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($records === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">No program budget rows yet for this context.</td></tr>
              <?php else: ?>
                <?php foreach ($records as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['ProgramName'] ?? '')) ?></div>
                      <?php if (!empty($row['ProgramCode'])): ?>
                        <div class="small text-muted"><?= h((string) $row['ProgramCode']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($row['SectorName'] ?? '')) ?></td>
                    <td><?= h((string) ($row['OrgUnitName'] ?? '')) ?></td>
                    <td class="text-end"><?= (int) ($row['OutputCount'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($row['ActivityCount'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($row['EconomicLines'] ?? 0) ?></td>
                    <td class="text-end"><?= (int) ($row['FundingSourceCount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['TotalAmount'] ?? 0) ?></td>
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
