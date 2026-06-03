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
        return number_format((float) $value, 2);
    }
}

$csrf = h(csrf_token());
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$fundingTypeSegmentNo = (int) ($fundingTypeMapping['SegmentNo'] ?? 0);
$fundingSourceSegmentNo = (int) ($fundingSourceMapping['SegmentNo'] ?? 0);

$screenHeader = [
    'title' => 'Resource Envelope',
    'icon' => 'bi-cash-stack',
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

      <?php if (!$resourceEnvelopeInstalled): ?>
        <div class="alert alert-warning">Resource envelope maintenance is not available until <code>create_tblSbResourceEnvelope.sql</code> is run.</div>
      <?php endif; ?>

      <?php if ($resourceEnvelopeInstalled && !$mappingReady): ?>
        <div class="alert alert-warning">
          Resource envelope lines depend on both funding dimensions being configured in Strategic Segment Mapping.
          Funding Type segment:
          <strong><?= $fundingTypeSegmentNo > 0 ? (string) $fundingTypeSegmentNo : 'Not mapped' ?></strong>.
          Funding Source segment:
          <strong><?= $fundingSourceSegmentNo > 0 ? (string) $fundingSourceSegmentNo : 'Not mapped' ?></strong>.
        </div>
      <?php endif; ?>

      <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Current year total</div>
            <div class="fs-4 fw-semibold"><?= money_fmt($summary['CurrentYearAmount'] ?? 0) ?></div>
          </div></div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Outer year 1</div>
            <div class="fs-4 fw-semibold"><?= money_fmt($summary['OuterYear1Amount'] ?? 0) ?></div>
          </div></div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Outer year 2</div>
            <div class="fs-4 fw-semibold"><?= money_fmt($summary['OuterYear2Amount'] ?? 0) ?></div>
          </div></div>
        </div>
        <div class="col-6 col-xl-3">
          <div class="card shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Active envelope lines</div>
            <div class="fs-4 fw-semibold"><?= (int) ($summary['LineCount'] ?? 0) ?></div>
          </div></div>
        </div>
      </div>

      <div class="alert alert-info border-0 shadow-sm mb-4">
        This screen captures the total available resource envelope for the active fiscal year and version. Enter one or more funding lines, optionally phase the current year across BP1 to BP12, and optionally add outer-year amounts for the MTFF horizon.
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header">
          <h5 class="mb-0">BP Phasing Totals</h5>
        </div>
        <div class="card-body">
          <div class="row g-3">
            <?php for ($i = 1; $i <= 12; $i++): $key = 'BP' . $i . 'Amount'; ?>
              <div class="col-6 col-lg-3">
                <div class="border rounded p-2 h-100">
                  <div class="text-muted small">BP<?= $i ?></div>
                  <div class="fw-semibold"><?= money_fmt($summary[$key] ?? 0) ?></div>
                </div>
              </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
          <h5 class="mb-0">Envelope Lines</h5>
          <a href="index.php?route=strategy-fiscal/resource-envelope-form" id="resource-envelope-list-add-btn" class="btn btn-sm btn-primary <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'disabled' : '' ?>" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Add Envelope Line</a>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="resource-envelope-list-table">
              <thead class="table-light">
                <tr>
                  <th>Funding Type</th>
                  <th>Funding Source</th>
                  <th class="text-end">Current Year</th>
                  <th class="text-center">Phased</th>
                  <th class="text-end">Outer Year 1</th>
                  <th class="text-end">Outer Year 2</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php if (($records ?? []) === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-3">No resource envelope lines have been entered yet.</td></tr>
              <?php else: ?>
                <?php foreach ($records as $row): ?>
                  <tr>
                    <td>
                      <div class="fw-semibold"><?= h((string) ($row['FundingTypeName'] ?? '')) ?></div>
                      <?php if (!empty($row['FundingTypeCode'])): ?>
                        <div class="small text-muted"><?= h((string) $row['FundingTypeCode']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= h((string) ($row['FundingSourceName'] ?? 'Unspecified / Type-level')) ?></td>
                    <td class="text-end"><?= money_fmt($row['CurrentYearAmount'] ?? 0) ?></td>
                    <td class="text-center">
                      <span class="badge <?= ((int) ($row['HasPhasing'] ?? 0) === 1) ? 'bg-success' : 'bg-secondary' ?>">
                        <?= ((int) ($row['HasPhasing'] ?? 0) === 1) ? 'Yes' : 'No' ?>
                      </span>
                    </td>
                    <td class="text-end"><?= money_fmt($row['OuterYear1Amount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($row['OuterYear2Amount'] ?? 0) ?></td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <a href="index.php?route=strategy-fiscal/resource-envelope-form&id=<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>" id="resource-envelope-list-edit-btn-<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
                        <form method="post" action="index.php?route=strategy-fiscal/delete-resource-envelope" id="resource-envelope-list-archive-form-<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>" onsubmit="return confirm('Archive this resource envelope line?');">
                          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                          <input type="hidden" name="id" value="<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>">
                          <button type="submit" id="resource-envelope-list-archive-btn-<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
                        </form>
                      </div>
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
