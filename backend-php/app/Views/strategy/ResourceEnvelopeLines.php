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

$csrf = h(csrf_token());
$yearLabel = (string) ($contextLabels['YearLabel'] ?? '');
$versionLabel = (string) ($contextLabels['VersionLabel'] ?? '');
$periodLabels = is_array($periodLabels ?? null) ? $periodLabels : [];
$outerYearLabels = is_array($periodLabels['OuterYear'] ?? null) ? $periodLabels['OuterYear'] : [];
$resourceEnvelopeMtffReady = !empty($resourceEnvelopeMtffReady);
$resourceEnvelopeOutYearsReady = !empty($resourceEnvelopeOutYearsReady);
$typeTotals = [];
$sourceTotals = [];
$reliabilityTotals = [];
$restrictionTotals = [];
$instrumentTotals = [];
$grandTypeTotals = [
    'CurrentYearAmount' => 0.0,
    'OuterYear1Amount' => 0.0,
    'OuterYear2Amount' => 0.0,
    'OuterYear3Amount' => 0.0,
    'OuterYear4Amount' => 0.0,
    'OuterYear5Amount' => 0.0,
    'TotalAmount' => 0.0,
];
$grandSourceTotals = [
    'CurrentYearAmount' => 0.0,
    'OuterYear1Amount' => 0.0,
    'OuterYear2Amount' => 0.0,
    'OuterYear3Amount' => 0.0,
    'OuterYear4Amount' => 0.0,
    'OuterYear5Amount' => 0.0,
    'TotalAmount' => 0.0,
];
$grandReliabilityTotals = [
    'CurrentYearAmount' => 0.0,
    'OuterYear1Amount' => 0.0,
    'OuterYear2Amount' => 0.0,
    'OuterYear3Amount' => 0.0,
    'OuterYear4Amount' => 0.0,
    'OuterYear5Amount' => 0.0,
    'TotalAmount' => 0.0,
];
$grandRestrictionTotals = $grandReliabilityTotals;
$grandInstrumentTotals = $grandReliabilityTotals;
foreach (($records ?? []) as $row) {
    $fundingTypeName = trim((string) ($row['FundingTypeName'] ?? 'Unspecified Funding Type'));
    if ($fundingTypeName === '') {
        $fundingTypeName = (string) __t('strategy_unspecified_funding_type');
    }
    $fundingTypeCode = trim((string) ($row['FundingTypeCode'] ?? ''));
    $fundingSourceName = trim((string) ($row['FundingSourceName'] ?? 'Unspecified / Type-level'));
    if ($fundingSourceName === '') {
        $fundingSourceName = (string) __t('strategy_unspecified_type_level');
    }
    $current = (float) ($row['CurrentYearAmount'] ?? 0);
    $outer1 = (float) ($row['OuterYear1Amount'] ?? 0);
    $outer2 = (float) ($row['OuterYear2Amount'] ?? 0);
    $outer3 = (float) ($row['OuterYear3Amount'] ?? 0);
    $outer4 = (float) ($row['OuterYear4Amount'] ?? 0);
    $outer5 = (float) ($row['OuterYear5Amount'] ?? 0);
    $lineTotal = $current + $outer1 + $outer2 + $outer3 + $outer4 + $outer5;
    $reliabilityName = trim((string) ($row['ReliabilityCode'] ?? ''));
    if ($reliabilityName === '') {
        $reliabilityName = (string) __t('not_set');
    }
    $restrictionName = trim((string) ($row['RestrictionCode'] ?? ''));
    if ($restrictionName === '') {
        $restrictionName = (string) __t('not_set');
    }
    $instrumentName = trim((string) ($row['FinancingInstrumentCode'] ?? ''));
    if ($instrumentName === '') {
        $instrumentName = (string) __t('not_set');
    }

    if (!isset($typeTotals[$fundingTypeName])) {
        $typeTotals[$fundingTypeName] = [
            'FundingTypeID' => (int) ($row['FundingTypeID'] ?? 0),
            'FundingTypeCode' => $fundingTypeCode,
            'CurrentYearAmount' => 0.0,
            'OuterYear1Amount' => 0.0,
            'OuterYear2Amount' => 0.0,
            'OuterYear3Amount' => 0.0,
            'OuterYear4Amount' => 0.0,
            'OuterYear5Amount' => 0.0,
            'TotalAmount' => 0.0,
            'LineCount' => 0,
        ];
    }
    $typeTotals[$fundingTypeName]['CurrentYearAmount'] += $current;
    $typeTotals[$fundingTypeName]['OuterYear1Amount'] += $outer1;
    $typeTotals[$fundingTypeName]['OuterYear2Amount'] += $outer2;
    $typeTotals[$fundingTypeName]['OuterYear3Amount'] += $outer3;
    $typeTotals[$fundingTypeName]['OuterYear4Amount'] += $outer4;
    $typeTotals[$fundingTypeName]['OuterYear5Amount'] += $outer5;
    $typeTotals[$fundingTypeName]['TotalAmount'] += $lineTotal;
    $typeTotals[$fundingTypeName]['LineCount']++;
    $grandTypeTotals['CurrentYearAmount'] += $current;
    $grandTypeTotals['OuterYear1Amount'] += $outer1;
    $grandTypeTotals['OuterYear2Amount'] += $outer2;
    $grandTypeTotals['OuterYear3Amount'] += $outer3;
    $grandTypeTotals['OuterYear4Amount'] += $outer4;
    $grandTypeTotals['OuterYear5Amount'] += $outer5;
    $grandTypeTotals['TotalAmount'] += $lineTotal;

    $sourceKey = $fundingTypeName . '||' . $fundingSourceName;
    if (!isset($sourceTotals[$sourceKey])) {
        $sourceTotals[$sourceKey] = [
            'FundingTypeID' => (int) ($row['FundingTypeID'] ?? 0),
            'FundingSourceID' => (int) ($row['FundingSourceID'] ?? 0),
            'FundingTypeName' => $fundingTypeName,
            'FundingTypeCode' => $fundingTypeCode,
            'FundingSourceName' => $fundingSourceName,
            'CurrentYearAmount' => 0.0,
            'OuterYear1Amount' => 0.0,
            'OuterYear2Amount' => 0.0,
            'OuterYear3Amount' => 0.0,
            'OuterYear4Amount' => 0.0,
            'OuterYear5Amount' => 0.0,
            'TotalAmount' => 0.0,
            'LineCount' => 0,
        ];
    }
    $sourceTotals[$sourceKey]['CurrentYearAmount'] += $current;
    $sourceTotals[$sourceKey]['OuterYear1Amount'] += $outer1;
    $sourceTotals[$sourceKey]['OuterYear2Amount'] += $outer2;
    $sourceTotals[$sourceKey]['OuterYear3Amount'] += $outer3;
    $sourceTotals[$sourceKey]['OuterYear4Amount'] += $outer4;
    $sourceTotals[$sourceKey]['OuterYear5Amount'] += $outer5;
    $sourceTotals[$sourceKey]['TotalAmount'] += $lineTotal;
    $sourceTotals[$sourceKey]['LineCount']++;
    $grandSourceTotals['CurrentYearAmount'] += $current;
    $grandSourceTotals['OuterYear1Amount'] += $outer1;
    $grandSourceTotals['OuterYear2Amount'] += $outer2;
    $grandSourceTotals['OuterYear3Amount'] += $outer3;
    $grandSourceTotals['OuterYear4Amount'] += $outer4;
    $grandSourceTotals['OuterYear5Amount'] += $outer5;
    $grandSourceTotals['TotalAmount'] += $lineTotal;

    if (!isset($reliabilityTotals[$reliabilityName])) {
        $reliabilityTotals[$reliabilityName] = [
            'CurrentYearAmount' => 0.0,
            'OuterYear1Amount' => 0.0,
            'OuterYear2Amount' => 0.0,
            'OuterYear3Amount' => 0.0,
            'OuterYear4Amount' => 0.0,
            'OuterYear5Amount' => 0.0,
            'TotalAmount' => 0.0,
            'LineCount' => 0,
        ];
    }
    $reliabilityTotals[$reliabilityName]['CurrentYearAmount'] += $current;
    $reliabilityTotals[$reliabilityName]['OuterYear1Amount'] += $outer1;
    $reliabilityTotals[$reliabilityName]['OuterYear2Amount'] += $outer2;
    $reliabilityTotals[$reliabilityName]['OuterYear3Amount'] += $outer3;
    $reliabilityTotals[$reliabilityName]['OuterYear4Amount'] += $outer4;
    $reliabilityTotals[$reliabilityName]['OuterYear5Amount'] += $outer5;
    $reliabilityTotals[$reliabilityName]['TotalAmount'] += $lineTotal;
    $reliabilityTotals[$reliabilityName]['LineCount']++;
    $grandReliabilityTotals['CurrentYearAmount'] += $current;
    $grandReliabilityTotals['OuterYear1Amount'] += $outer1;
    $grandReliabilityTotals['OuterYear2Amount'] += $outer2;
    $grandReliabilityTotals['OuterYear3Amount'] += $outer3;
    $grandReliabilityTotals['OuterYear4Amount'] += $outer4;
    $grandReliabilityTotals['OuterYear5Amount'] += $outer5;
    $grandReliabilityTotals['TotalAmount'] += $lineTotal;

    if (!isset($restrictionTotals[$restrictionName])) {
        $restrictionTotals[$restrictionName] = [
            'CurrentYearAmount' => 0.0,
            'OuterYear1Amount' => 0.0,
            'OuterYear2Amount' => 0.0,
            'OuterYear3Amount' => 0.0,
            'OuterYear4Amount' => 0.0,
            'OuterYear5Amount' => 0.0,
            'TotalAmount' => 0.0,
            'LineCount' => 0,
        ];
    }
    $restrictionTotals[$restrictionName]['CurrentYearAmount'] += $current;
    $restrictionTotals[$restrictionName]['OuterYear1Amount'] += $outer1;
    $restrictionTotals[$restrictionName]['OuterYear2Amount'] += $outer2;
    $restrictionTotals[$restrictionName]['OuterYear3Amount'] += $outer3;
    $restrictionTotals[$restrictionName]['OuterYear4Amount'] += $outer4;
    $restrictionTotals[$restrictionName]['OuterYear5Amount'] += $outer5;
    $restrictionTotals[$restrictionName]['TotalAmount'] += $lineTotal;
    $restrictionTotals[$restrictionName]['LineCount']++;
    $grandRestrictionTotals['CurrentYearAmount'] += $current;
    $grandRestrictionTotals['OuterYear1Amount'] += $outer1;
    $grandRestrictionTotals['OuterYear2Amount'] += $outer2;
    $grandRestrictionTotals['OuterYear3Amount'] += $outer3;
    $grandRestrictionTotals['OuterYear4Amount'] += $outer4;
    $grandRestrictionTotals['OuterYear5Amount'] += $outer5;
    $grandRestrictionTotals['TotalAmount'] += $lineTotal;

    if (!isset($instrumentTotals[$instrumentName])) {
        $instrumentTotals[$instrumentName] = [
            'CurrentYearAmount' => 0.0,
            'OuterYear1Amount' => 0.0,
            'OuterYear2Amount' => 0.0,
            'OuterYear3Amount' => 0.0,
            'OuterYear4Amount' => 0.0,
            'OuterYear5Amount' => 0.0,
            'TotalAmount' => 0.0,
            'LineCount' => 0,
        ];
    }
    $instrumentTotals[$instrumentName]['CurrentYearAmount'] += $current;
    $instrumentTotals[$instrumentName]['OuterYear1Amount'] += $outer1;
    $instrumentTotals[$instrumentName]['OuterYear2Amount'] += $outer2;
    $instrumentTotals[$instrumentName]['OuterYear3Amount'] += $outer3;
    $instrumentTotals[$instrumentName]['OuterYear4Amount'] += $outer4;
    $instrumentTotals[$instrumentName]['OuterYear5Amount'] += $outer5;
    $instrumentTotals[$instrumentName]['TotalAmount'] += $lineTotal;
    $instrumentTotals[$instrumentName]['LineCount']++;
    $grandInstrumentTotals['CurrentYearAmount'] += $current;
    $grandInstrumentTotals['OuterYear1Amount'] += $outer1;
    $grandInstrumentTotals['OuterYear2Amount'] += $outer2;
    $grandInstrumentTotals['OuterYear3Amount'] += $outer3;
    $grandInstrumentTotals['OuterYear4Amount'] += $outer4;
    $grandInstrumentTotals['OuterYear5Amount'] += $outer5;
    $grandInstrumentTotals['TotalAmount'] += $lineTotal;
}
ksort($typeTotals);
ksort($reliabilityTotals);
ksort($restrictionTotals);
ksort($instrumentTotals);
uasort($sourceTotals, static function (array $a, array $b): int {
    return [$a['FundingTypeName'], $a['FundingSourceName']] <=> [$b['FundingTypeName'], $b['FundingSourceName']];
});
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <h3 class="mb-0"><?= h(__t('strategy_resource_envelope')) ?></h3>
      <div class="d-flex flex-wrap gap-2">
        <a href="index.php?route=strategy-fiscal/resource-envelope" id="resource-envelope-lines-summary-btn" class="btn btn-sm btn-outline-secondary"><?= h(__t('strategy_resource_envelope_summary')) ?></a>
        <a href="index.php?route=strategy-fiscal/resource-envelope-form" id="resource-envelope-lines-add-btn" class="btn btn-sm btn-primary" <?= (!$resourceEnvelopeInstalled || !$mappingReady) ? 'aria-disabled="true"' : '' ?>><?= h(__t('strategy_add_envelope_line')) ?></a>
      </div>
    </div>
    <div class="card-body">
      <div class="small text-muted mb-3">
        <?= h(__t('strategy_funding_lines_for')) ?>
        <strong><?= h($yearLabel !== '' ? $yearLabel : __t('not_set')) ?></strong>
        <?php if ($versionLabel !== ''): ?>
          <span class="mx-1">/</span><strong><?= h($versionLabel) ?></strong>
        <?php endif; ?>
      </div>

  <div class="alert alert-info border-0 shadow-sm mb-4">
    <?= h(__t('strategy_resource_envelope_lines_intro')) ?>
  </div>
  <?php if (!$resourceEnvelopeMtffReady): ?>
    <div class="alert alert-warning"><?= h(__t('strategy_mtff_attributes_not_available')) ?> <code>alter_tblSbResourceEnvelope_mtff_attributes_and_outyears.sql</code> <?= h(__t('strategy_is_run')) ?></div>
  <?php endif; ?>
  <?php if (!$resourceEnvelopeOutYearsReady): ?>
    <div class="alert alert-warning"><?= h(__t('strategy_outer_years_not_available')) ?> <code>alter_tblSbResourceEnvelope_mtff_attributes_and_outyears.sql</code> <?= h(__t('strategy_is_run')) ?></div>
  <?php endif; ?>

  <?php
    $summaryTabs = [
        [
            'tab_id' => 'summary-type-tab',
            'pane_id' => 'summary-type',
            'label' => __t('strategy_funding_type'),
            'title' => __t('strategy_summary_by_funding_type'),
        ],
        [
            'tab_id' => 'summary-source-tab',
            'pane_id' => 'summary-source',
            'label' => __t('strategy_funding_source'),
            'title' => __t('strategy_summary_by_funding_source'),
        ],
    ];
    if ($resourceEnvelopeMtffReady) {
        $summaryTabs[] = [
            'tab_id' => 'summary-reliability-tab',
            'pane_id' => 'summary-reliability',
            'label' => __t('strategy_reliability_certainty'),
            'title' => __t('strategy_summary_by_reliability'),
        ];
        $summaryTabs[] = [
            'tab_id' => 'summary-restriction-tab',
            'pane_id' => 'summary-restriction',
            'label' => __t('strategy_restriction_earmark'),
            'title' => __t('strategy_summary_by_restriction'),
        ];
        $summaryTabs[] = [
            'tab_id' => 'summary-instrument-tab',
            'pane_id' => 'summary-instrument',
            'label' => __t('strategy_financing_instrument'),
            'title' => __t('strategy_summary_by_financing_instrument'),
        ];
    }
  ?>

  <ul class="nav nav-tabs mb-4" id="resourceEnvelopeSummaryTabs" role="tablist">
    <?php foreach ($summaryTabs as $index => $tab): ?>
      <li class="nav-item" role="presentation">
        <button
          class="nav-link <?= $index === 0 ? 'active' : '' ?>"
          id="<?= h($tab['tab_id']) ?>"
          data-bs-toggle="tab"
          data-bs-target="#<?= h($tab['pane_id']) ?>"
          type="button"
          role="tab"
          aria-controls="<?= h($tab['pane_id']) ?>"
          aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
        ><?= h($tab['label']) ?></button>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="tab-content mb-4" id="resourceEnvelopeSummaryTabsContent">
    <div class="tab-pane fade show active" id="summary-type" role="tabpanel" aria-labelledby="summary-type-tab" tabindex="0">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Summary by Funding Type</h5>
          <span class="small text-muted"><?= count($typeTotals) ?> type group(s)</span>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="resource-envelope-type-summary-table">
              <thead class="table-light">
                <tr>
                  <th>Funding Type</th>
                  <th class="text-end">Current Year</th>
              <th class="text-end"><?= h((string) ($outerYearLabels[1] ?? 'Outer Year 1')) ?></th>
              <th class="text-end"><?= h((string) ($outerYearLabels[2] ?? 'Outer Year 2')) ?></th>
                  <th class="text-end">Outer Years</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($typeTotals === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No funding type summaries yet.</td></tr>
              <?php else: ?>
                <?php foreach ($typeTotals as $fundingTypeName => $totals): ?>
                  <?php $typeHref = 'index.php?route=strategy-fiscal/resource-envelope-form'; ?>
                  <?php if ((int) ($totals['FundingTypeID'] ?? 0) > 0): ?>
                    <?php $typeHref .= '&funding_type_id=' . (int) $totals['FundingTypeID']; ?>
                  <?php endif; ?>
                  <?php $typeOuterYearsTotal = (float) ($totals['OuterYear1Amount'] ?? 0) + (float) ($totals['OuterYear2Amount'] ?? 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($totals['OuterYear3Amount'] ?? 0) + (float) ($totals['OuterYear4Amount'] ?? 0) + (float) ($totals['OuterYear5Amount'] ?? 0)) : 0); ?>
                  <tr>
                    <td>
                      <a href="<?= h($typeHref) ?>" class="text-decoration-none fw-semibold">
                        <?= h((string) $fundingTypeName) ?>
                      </a>
                      <?php if (($totals['FundingTypeCode'] ?? '') !== ''): ?>
                        <span class="small text-muted ms-2"><?= h((string) $totals['FundingTypeCode']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><?= money_fmt($totals['CurrentYearAmount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($totals['OuterYear1Amount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($totals['OuterYear2Amount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($typeOuterYearsTotal) ?></td>
                    <td class="text-end fw-semibold"><?= money_fmt($totals['TotalAmount'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php $grandTypeOuterYearsTotal = (float) ($grandTypeTotals['OuterYear1Amount'] ?? 0) + (float) ($grandTypeTotals['OuterYear2Amount'] ?? 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($grandTypeTotals['OuterYear3Amount'] ?? 0) + (float) ($grandTypeTotals['OuterYear4Amount'] ?? 0) + (float) ($grandTypeTotals['OuterYear5Amount'] ?? 0)) : 0); ?>
                <tr class="table-light fw-semibold">
                  <td>Grand Total</td>
                  <td class="text-end"><?= money_fmt($grandTypeTotals['CurrentYearAmount'] ?? 0) ?></td>
                  <td class="text-end"><?= money_fmt($grandTypeTotals['OuterYear1Amount'] ?? 0) ?></td>
                  <td class="text-end"><?= money_fmt($grandTypeTotals['OuterYear2Amount'] ?? 0) ?></td>
                  <td class="text-end"><?= money_fmt($grandTypeOuterYearsTotal) ?></td>
                  <td class="text-end"><?= money_fmt($grandTypeTotals['TotalAmount'] ?? 0) ?></td>
                </tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="summary-source" role="tabpanel" aria-labelledby="summary-source-tab" tabindex="0">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Summary by Funding Source</h5>
          <span class="small text-muted"><?= count($sourceTotals) ?> source group(s)</span>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="resource-envelope-source-summary-table">
              <thead class="table-light">
                <tr>
                  <th>Funding Source</th>
                  <th>Funding Type</th>
                  <th class="text-end">Current Year</th>
                  <th class="text-end">Outer Years</th>
                  <th class="text-end">Total</th>
                </tr>
              </thead>
              <tbody>
              <?php if ($sourceTotals === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No funding source summaries yet.</td></tr>
              <?php else: ?>
                <?php foreach ($sourceTotals as $totals): ?>
                  <?php $sourceHref = 'index.php?route=strategy-fiscal/resource-envelope-form'; ?>
                  <?php if ((int) ($totals['FundingTypeID'] ?? 0) > 0): ?>
                    <?php $sourceHref .= '&funding_type_id=' . (int) $totals['FundingTypeID']; ?>
                  <?php endif; ?>
                  <?php if ((int) ($totals['FundingSourceID'] ?? 0) > 0): ?>
                    <?php $sourceHref .= '&funding_source_id=' . (int) $totals['FundingSourceID']; ?>
                  <?php endif; ?>
                  <?php $sourceOuterYearsTotal = (float) ($totals['OuterYear1Amount'] ?? 0) + (float) ($totals['OuterYear2Amount'] ?? 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($totals['OuterYear3Amount'] ?? 0) + (float) ($totals['OuterYear4Amount'] ?? 0) + (float) ($totals['OuterYear5Amount'] ?? 0)) : 0); ?>
                  <tr>
                    <td>
                      <a href="<?= h($sourceHref) ?>" class="text-decoration-none">
                        <?= h((string) ($totals['FundingSourceName'] ?? '')) ?>
                      </a>
                    </td>
                    <td>
                      <a href="<?= h($sourceHref) ?>" class="text-decoration-none fw-semibold">
                        <?= h((string) ($totals['FundingTypeName'] ?? '')) ?>
                      </a>
                      <?php if (($totals['FundingTypeCode'] ?? '') !== ''): ?>
                        <span class="small text-muted ms-2"><?= h((string) $totals['FundingTypeCode']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end"><?= money_fmt($totals['CurrentYearAmount'] ?? 0) ?></td>
                    <td class="text-end"><?= money_fmt($sourceOuterYearsTotal) ?></td>
                    <td class="text-end fw-semibold"><?= money_fmt($totals['TotalAmount'] ?? 0) ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php $grandSourceOuterYearsTotal = (float) ($grandSourceTotals['OuterYear1Amount'] ?? 0) + (float) ($grandSourceTotals['OuterYear2Amount'] ?? 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($grandSourceTotals['OuterYear3Amount'] ?? 0) + (float) ($grandSourceTotals['OuterYear4Amount'] ?? 0) + (float) ($grandSourceTotals['OuterYear5Amount'] ?? 0)) : 0); ?>
                <tr class="table-light fw-semibold">
                  <td>Grand Total</td>
                  <td></td>
                  <td class="text-end"><?= money_fmt($grandSourceTotals['CurrentYearAmount'] ?? 0) ?></td>
                  <td class="text-end"><?= money_fmt($grandSourceOuterYearsTotal) ?></td>
                  <td class="text-end"><?= money_fmt($grandSourceTotals['TotalAmount'] ?? 0) ?></td>
                </tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <?php if ($resourceEnvelopeMtffReady): ?>
      <?php
        $mtffTabs = [
            'summary-reliability' => ['Summary by Reliability / Certainty', 'summary-reliability-tab', $reliabilityTotals, $grandReliabilityTotals],
            'summary-restriction' => ['Summary by Restriction / Earmark', 'summary-restriction-tab', $restrictionTotals, $grandRestrictionTotals],
            'summary-instrument' => ['Summary by Financing Instrument', 'summary-instrument-tab', $instrumentTotals, $grandInstrumentTotals],
        ];
      ?>
      <?php foreach ($mtffTabs as $paneId => [$title, $tabId, $totalsSet, $grandTotalsSet]): ?>
        <div class="tab-pane fade" id="<?= h($paneId) ?>" role="tabpanel" aria-labelledby="<?= h($tabId) ?>" tabindex="0">
          <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
              <h5 class="mb-0"><?= h($title) ?></h5>
              <span class="small text-muted"><?= count($totalsSet) ?> group(s)</span>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="resource-envelope-mtff-summary-table-<?= h($paneId) ?>">
                  <thead class="table-light">
                    <tr>
                      <th>Attribute</th>
                      <th class="text-end">Current Year</th>
                      <th class="text-end">Outer Years</th>
                      <th class="text-end">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                  <?php if ($totalsSet === []): ?>
                    <tr><td colspan="4" class="text-center text-muted py-3">No summaries yet.</td></tr>
                  <?php else: ?>
                    <?php foreach ($totalsSet as $label => $totals): ?>
                      <?php $outerYearsTotal = (float) ($totals['OuterYear1Amount'] ?? 0) + (float) ($totals['OuterYear2Amount'] ?? 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($totals['OuterYear3Amount'] ?? 0) + (float) ($totals['OuterYear4Amount'] ?? 0) + (float) ($totals['OuterYear5Amount'] ?? 0)) : 0); ?>
                      <tr>
                        <td class="fw-semibold"><?= h((string) $label) ?></td>
                        <td class="text-end"><?= money_fmt($totals['CurrentYearAmount'] ?? 0) ?></td>
                        <td class="text-end"><?= money_fmt($outerYearsTotal) ?></td>
                        <td class="text-end fw-semibold"><?= money_fmt($totals['TotalAmount'] ?? 0) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php $grandOuterYearsTotal = (float) ($grandTotalsSet['OuterYear1Amount'] ?? 0) + (float) ($grandTotalsSet['OuterYear2Amount'] ?? 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($grandTotalsSet['OuterYear3Amount'] ?? 0) + (float) ($grandTotalsSet['OuterYear4Amount'] ?? 0) + (float) ($grandTotalsSet['OuterYear5Amount'] ?? 0)) : 0); ?>
                    <tr class="table-light fw-semibold">
                      <td>Grand Total</td>
                      <td class="text-end"><?= money_fmt($grandTotalsSet['CurrentYearAmount'] ?? 0) ?></td>
                      <td class="text-end"><?= money_fmt($grandOuterYearsTotal) ?></td>
                      <td class="text-end"><?= money_fmt($grandTotalsSet['TotalAmount'] ?? 0) ?></td>
                    </tr>
                  <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Resource Envelope Lines</h5>
      <span class="small text-muted"><?= (int) ($summary['LineCount'] ?? 0) ?> active line(s)</span>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="resource-envelope-lines-table">
          <thead class="table-light">
            <tr>
              <th>Funding Type</th>
              <th>Funding Source</th>
              <th class="text-end">Current Year</th>
              <th class="text-center">Phased</th>
              <th class="text-end">Outer Years</th>
              <th class="text-end">Total</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (($records ?? []) === []): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No resource envelope lines have been entered yet.</td></tr>
          <?php else: ?>
            <?php foreach ($records as $row): ?>
              <?php $outerYearsTotal = (float) ($row['OuterYear1Amount'] ?? 0) + (float) ($row['OuterYear2Amount'] ?? 0) + ($resourceEnvelopeOutYearsReady ? ((float) ($row['OuterYear3Amount'] ?? 0) + (float) ($row['OuterYear4Amount'] ?? 0) + (float) ($row['OuterYear5Amount'] ?? 0)) : 0); ?>
              <?php $lineTotal = (float) ($row['CurrentYearAmount'] ?? 0) + $outerYearsTotal; ?>
              <tr>
                <td>
                  <span class="fw-semibold"><?= h((string) ($row['FundingTypeName'] ?? '')) ?></span>
                  <?php if (!empty($row['FundingTypeCode'])): ?>
                    <span class="small text-muted ms-2"><?= h((string) $row['FundingTypeCode']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <span><?= h((string) ($row['FundingSourceName'] ?? 'Unspecified / Type-level')) ?></span>
                  <?php if (!empty($row['FundingSourceCode'])): ?>
                    <span class="small text-muted ms-2"><?= h((string) $row['FundingSourceCode']) ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= money_fmt($row['CurrentYearAmount'] ?? 0) ?></td>
                <td class="text-center">
                  <span class="badge <?= ((int) ($row['HasPhasing'] ?? 0) === 1) ? 'bg-success' : 'bg-secondary' ?>">
                    <?= ((int) ($row['HasPhasing'] ?? 0) === 1) ? 'Yes' : 'No' ?>
                  </span>
                </td>
                <td class="text-end"><?= money_fmt($outerYearsTotal) ?></td>
                <td class="text-end fw-semibold"><?= money_fmt($lineTotal) ?></td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a href="index.php?route=strategy-fiscal/resource-envelope-form&id=<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>" id="resource-envelope-lines-edit-btn-<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i></a>
                    <form method="post" action="index.php?route=strategy-fiscal/delete-resource-envelope" id="resource-envelope-lines-archive-form-<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>" onsubmit="return confirm('Archive this resource envelope line?');">
                      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="id" value="<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>">
                      <button type="submit" id="resource-envelope-lines-archive-btn-<?= (int) ($row['ResourceEnvelopeID'] ?? 0) ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i></button>
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
