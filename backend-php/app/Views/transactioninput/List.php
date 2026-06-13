<?php
declare(strict_types=1);
/** @var string $title */
/** @var int $ctxFy */
/** @var int $ctxVer */
/** @var string $ctxDataObject */
/** @var string $ctxDataObjectName */
/** @var array $ctxDataObjectPath */
/** @var array $ctxAccountPath */
/** @var array $ceilingByTransactionType */
/** @var array $rows */
/** @var array $summaryRows */
/** @var int $total */
/** @var array $filters */
/** @var string $segmentCol */
/** @var string $segmentCol2 */
/** @var array $allowedFilterFields */
/** @var string $warning */
/** @var string $error */
/** @var string $_csrf */

if (!function_exists('h')) {
    function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('fmt_amt')) {
    function fmt_amt($v): string {
        $n = (float)$v;
        $abs = number_format(abs($n), 0);
        return $n < 0 ? '(' . $abs . ')' : $abs;
    }
}
if (!function_exists('amt_span')) {
    function amt_span($v): string {
        $n = (float)$v;
        $cls = $n < 0 ? 'text-danger' : '';
        return '<span class="' . $cls . '">' . fmt_amt($n) . '</span>';
    }
}

$mode = strtolower((string)($filters['mode'] ?? 'summary'));
if (!in_array($mode, ['summary', 'level2', 'level3', 'detail'], true)) {
    $mode = 'summary';
}
$summaryBy = strtolower((string)($filters['summary_by'] ?? 'gl_prefix'));
if (!in_array($summaryBy, ['gl_prefix', 'transaction_type', 'transaction_type_gl_group'], true)) {
    $summaryBy = 'gl_prefix';
}
$isBudgetClassSummary = ($summaryBy === 'transaction_type');
$isBudgetClassGlGroupSummary = ($summaryBy === 'transaction_type_gl_group');
$summaryLeadColspan = $isBudgetClassSummary ? 1 : ($isBudgetClassGlGroupSummary ? 3 : 2);
$summaryNoRecordsColspan = $isBudgetClassSummary ? 8 : ($isBudgetClassGlGroupSummary ? 10 : 9);
$segmentLabel = $segmentCol ?? 'Segment1Code';
$segmentLabel2 = $segmentCol2 ?? 'Segment2Code';
$ctxPathParts = [];
if (!empty($ctxDataObjectPath) && is_array($ctxDataObjectPath)) {
    foreach ($ctxDataObjectPath as $node) {
        $typeName = trim((string)($node['DataObjectTypeName'] ?? 'Level'));
        $code = (string)($node['DataObjectCode'] ?? '');
        $name = trim((string)($node['DataObjectName'] ?? ''));
        if ($code === '') {
            continue;
        }
        $ctxPathParts[] = h($typeName) . ': ' . h($code . ($name !== '' ? ' (' . $name . ')' : ''));
    }
}
$accountPathParts = [];
if (!empty($ctxAccountPath) && is_array($ctxAccountPath)) {
    foreach ($ctxAccountPath as $part) {
        $part = trim((string)$part);
        if ($part === '') {
            continue;
        }
        $accountPathParts[] = h($part);
    }
}

$glLevelForBack = (int)($filters['gl_level'] ?? 1);
if (!in_array($glLevelForBack, [1, 2, 3], true)) {
    $glLevelForBack = 1;
}
$derivePrefixForBack = static function (string $glCode, int $glLevel): string {
    if ($glCode === '') {
        return '';
    }
    if ($glLevel === 2) {
        return substr($glCode, 0, 4);
    }
    if ($glLevel === 3) {
        return $glCode;
    }
    return substr($glCode, 0, 2);
};
$backParams = [
    'route' => 'transaction-input/list',
    'summary_by' => $summaryBy,
    'report_id' => (string)($filters['report_id'] ?? '1'),
    'gl_level' => (string)$glLevelForBack,
    'q' => (string)($filters['q'] ?? ''),
];
$backLabel = __t('tx_back_to_summary');

if ($mode === 'level2') {
    $backParams['mode'] = 'summary';
    $backLabel = __t('tx_back_to_summary');
} elseif ($mode === 'level3') {
    $glCode = (string)($filters['gl_code'] ?? '');
    $tt = (string)($filters['tt'] ?? '');
    if ($glCode !== '' && $tt !== '') {
        $backParams['mode'] = 'level2';
        $backParams['gl_prefix'] = (string)($filters['gl_prefix'] ?? '');
        if ($backParams['gl_prefix'] === '') {
            $backParams['gl_prefix'] = $derivePrefixForBack($glCode, $glLevelForBack);
        }
        $backLabel = __t('tx_back_to_gl_accounts');
    } else {
        $backParams['mode'] = 'summary';
        $backLabel = __t('tx_back_to_summary');
    }
} elseif ($mode === 'detail') {
    $glCode = (string)($filters['gl_code'] ?? '');
    $tt = (string)($filters['tt'] ?? '');
    $accountCode = (string)($filters['account_code'] ?? '');
    if ($accountCode !== '' || $glCode !== '' || $tt !== '') {
        $backParams['mode'] = 'level3';
        if ($glCode !== '') {
            $backParams['gl_code'] = $glCode;
            $backParams['gl_prefix'] = (string)($filters['gl_prefix'] ?? '');
            if ($backParams['gl_prefix'] === '') {
                $backParams['gl_prefix'] = $derivePrefixForBack($glCode, $glLevelForBack);
            }
        }
        if ($tt !== '') {
            $backParams['tt'] = $tt;
        }
        $backLabel = __t('tx_back_to_gl_accounts');
    } elseif ((string)($filters['gl_prefix'] ?? '') !== '') {
        $backParams['mode'] = 'level2';
        $backParams['gl_prefix'] = (string)$filters['gl_prefix'];
        $backLabel = __t('tx_back_to_gl_accounts');
    } else {
        $backParams['mode'] = 'summary';
        $backLabel = __t('tx_back_to_summary');
    }
}
$backOneLevelUrl = 'index.php?' . http_build_query($backParams);
$sharedNavParams = [
    'route' => 'transaction-input/list',
    'summary_by' => $summaryBy,
    'report_id' => (string)($filters['report_id'] ?? '1'),
    'gl_level' => (string)$glLevelForBack,
];
if ((string)($filters['q'] ?? '') !== '') {
    $sharedNavParams['q'] = (string)$filters['q'];
}
if ((string)($filters['gl_prefix'] ?? '') !== '') {
    $sharedNavParams['gl_prefix'] = (string)$filters['gl_prefix'];
}
if ((string)($filters['gl_code'] ?? '') !== '') {
    $sharedNavParams['gl_code'] = (string)$filters['gl_code'];
}
if ((string)($filters['tt'] ?? '') !== '') {
    $sharedNavParams['tt'] = (string)$filters['tt'];
}
if ((string)($filters['account_code'] ?? '') !== '') {
    $sharedNavParams['account_code'] = (string)$filters['account_code'];
}
if (!empty($filters['filter_field']) && is_array($filters['filter_field'])) {
    $sharedNavParams['filter_field'] = $filters['filter_field'];
}
if (!empty($filters['filter_op']) && is_array($filters['filter_op'])) {
    $sharedNavParams['filter_op'] = $filters['filter_op'];
}
if (!empty($filters['filter_value']) && is_array($filters['filter_value'])) {
    $sharedNavParams['filter_value'] = $filters['filter_value'];
}
$buildListUrl = static function (array $overrides) use ($sharedNavParams): string {
    return 'index.php?' . http_build_query(array_merge($sharedNavParams, $overrides));
};
?>
<div class="card shadow-sm mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong><i class="bi bi-journal-text me-2"></i><?= h($title) ?></strong>
    <div class="btn-group">
      <a href="index.php?route=transaction-input/editor" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-circle me-1"></i> <?= __t('tx_new_transaction') ?>
      </a>
      <a href="index.php?route=transaction-input/editor" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-pencil-square me-1"></i> <?= __t('tx_open_editor') ?>
      </a>
      <a href="index.php?route=transaction-input/download-template" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-download me-1"></i> <?= __t('tx_download_workbook') ?>
      </a>
      <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transactionWorkbookUploadModal">
        <i class="bi bi-upload me-1"></i> Upload Workbook
      </button>
    </div>
  </div>

  <div class="card-body">
    <?php if (!empty($warning)): ?>
      <div class="alert alert-warning mb-3"><?= h($warning) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger mb-3"><?= __t('tx_query_failed') ?>: <?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($mode === 'level3'): ?>
      <?php
        $filterFields = is_array($filters['filter_field'] ?? null) ? $filters['filter_field'] : [];
        $filterOps = is_array($filters['filter_op'] ?? null) ? $filters['filter_op'] : [];
        $filterValues = is_array($filters['filter_value'] ?? null) ? $filters['filter_value'] : [];
        $rowsCount = max(3, count($filterFields));
      ?>
      <div class="mb-3">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#searchFilterPanel" aria-expanded="false" aria-controls="searchFilterPanel">
          <i class="bi bi-funnel me-1"></i>Show Search & Filters
        </button>
      </div>
      <div class="collapse mb-3" id="searchFilterPanel">
        <div class="card card-body">
          <form method="get" action="index.php" class="row">
            <input type="hidden" name="route" value="transaction-input/list">
            <input type="hidden" name="mode" value="<?= h($mode) ?>">
            <input type="hidden" name="summary_by" value="<?= h($summaryBy) ?>">
            <input type="hidden" name="tt" value="<?= h((string)($filters['tt'] ?? '')) ?>">
            <input type="hidden" name="gl_code" value="<?= h((string)($filters['gl_code'] ?? '')) ?>">
            <input type="hidden" name="account_code" value="<?= h((string)($filters['account_code'] ?? '')) ?>">

            <div class="col-md-3 mb-2">
              <input type="text" name="q" value="<?= h($filters['q'] ?? '') ?>" class="form-control" placeholder="<?= __t('tx_search_ph') ?>">
            </div>
            <div class="col-md-3 mb-2">
              <input type="number" name="report_id" value="<?= h($filters['report_id'] ?? '1') ?>" class="form-control" placeholder="<?= __t('tx_report_id') ?>">
            </div>
            <div class="col-md-3 mb-2">
              <select name="gl_level" class="form-select">
                <?php $glLevel = $filters['gl_level'] ?? '1'; ?>
                <option value="1" <?= $glLevel === '1' ? 'selected' : '' ?>><?= __t('tx_gl_level_1') ?></option>
                <option value="2" <?= $glLevel === '2' ? 'selected' : '' ?>><?= __t('tx_gl_level_2') ?></option>
                <option value="3" <?= $glLevel === '3' ? 'selected' : '' ?>><?= __t('tx_gl_level_3') ?></option>
              </select>
            </div>
            <div class="col-md-3 mb-2">
              <input type="text" name="gl_prefix" value="<?= h($filters['gl_prefix'] ?? '') ?>" class="form-control" placeholder="<?= __t('tx_gl_prefix_filter') ?>">
            </div>

            <h6 class="mt-2"><?= __t('tx_advanced_filters_title') ?></h6>
            <div id="advFilterRows">
              <?php for ($i = 0; $i < $rowsCount; $i++): ?>
                <div class="row g-2 adv-filter-row mb-1">
                  <div class="col-md-4">
                    <select name="filter_field[]" class="form-select">
                      <option value=""><?= __t('tx_field') ?></option>
                      <?php foreach ($allowedFilterFields as $fld): ?>
                        <?php $sel = ($filterFields[$i] ?? '') === $fld ? 'selected' : ''; ?>
                        <option value="<?= h($fld) ?>" <?= $sel ?>><?= h($fld) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <?php $op = $filterOps[$i] ?? 'equals'; ?>
                    <select name="filter_op[]" class="form-select">
                      <option value="equals" <?= $op === 'equals' ? 'selected' : '' ?>><?= __t('tx_equals') ?></option>
                      <option value="contains" <?= $op === 'contains' ? 'selected' : '' ?>><?= __t('tx_contains') ?></option>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <input type="text" name="filter_value[]" value="<?= h($filterValues[$i] ?? '') ?>" class="form-control" placeholder="<?= __t('value') ?>">
                  </div>
                </div>
              <?php endfor; ?>
            </div>

            <div class="col-md-12 mb-2 d-flex gap-2">
              <button type="submit" class="btn btn-primary flex-fill">
                <i class="bi bi-search me-1"></i><?= __t('tx_apply_filters') ?>
              </button>
              <button type="button" class="btn btn-outline-secondary flex-fill" id="addFilterRowBtn">
                <i class="bi bi-plus-circle me-1"></i><?= __t('tx_add_filter_row') ?>
              </button>
              <a href="index.php?route=transaction-input/list" class="btn btn-outline-secondary flex-fill">
                <i class="bi bi-x-circle me-1"></i><?= __t('tx_clear_filters') ?>
              </a>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($mode === 'summary'): ?>
      <div class="mb-3">
        <div class="btn-group" role="group" aria-label="<?= __t('tx_summary_grouping') ?>">
          <a class="btn btn-sm <?= $summaryBy === 'gl_prefix' ? 'btn-primary' : 'btn-outline-primary' ?>"
             href="<?= h($buildListUrl(['mode' => 'summary', 'summary_by' => 'gl_prefix', 'tt' => '', 'gl_code' => '', 'account_code' => '', 'gl_prefix' => ''])) ?>">
            <?= __t('tx_gl_prefix_summary') ?>
          </a>
          <a class="btn btn-sm <?= $summaryBy === 'transaction_type' ? 'btn-primary' : 'btn-outline-primary' ?>"
             href="<?= h($buildListUrl(['mode' => 'summary', 'summary_by' => 'transaction_type', 'tt' => '', 'gl_code' => '', 'account_code' => '', 'gl_prefix' => ''])) ?>">
            <?= __t('tx_transaction_type_summary') ?>
          </a>
          <a class="btn btn-sm <?= $summaryBy === 'transaction_type_gl_group' ? 'btn-primary' : 'btn-outline-primary' ?>"
             href="<?= h($buildListUrl(['mode' => 'summary', 'summary_by' => 'transaction_type_gl_group', 'tt' => '', 'gl_code' => '', 'account_code' => '', 'gl_prefix' => ''])) ?>">
            Budget Class + GL Grouping
          </a>
        </div>
      </div>
    <?php endif; ?>

    <div class="text-muted mb-2">
      <?= __t('tx_context') ?>: <?= __t('fiscal_year') ?> <?= h((string)$ctxFy) ?> | <?= __t('version') ?> <?= h((string)$ctxVer) ?>
    </div>
    <div class="text-muted mb-2">
      Mode: <strong><?= h($mode) ?></strong> | summary_by: <strong><?= h($summaryBy) ?></strong>
    </div>
    <?php if (!empty($ctxPathParts)): ?>
      <div class="text-muted mb-3">
        Path: <?= implode(' &gt; ', $ctxPathParts) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($accountPathParts)): ?>
      <div class="text-muted mb-3">
        Account Path: <?= implode(' &gt; ', $accountPathParts) ?>
      </div>
    <?php endif; ?>

    <?php if ($mode === 'summary'): ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= $isBudgetClassSummary ? __t('tx_transaction_type') : ($isBudgetClassGlGroupSummary ? __t('tx_transaction_type') : __t('tx_gl_group')) ?></th>
              <?php if ($isBudgetClassGlGroupSummary): ?>
                <th><?= __t('tx_gl_group') ?></th>
              <?php endif; ?>
              <?php if (!$isBudgetClassSummary): ?>
                <th><?= __t('tx_gl_prefix') ?></th>
              <?php endif; ?>
              <th class="text-end">Q1</th>
              <th class="text-end">Q2</th>
              <th class="text-end">Q3</th>
              <th class="text-end">Q4</th>
              <th class="text-end">Annual</th>
              <th class="text-end">Ceiling Balance</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($summaryRows)): ?>
              <tr><td colspan="<?= $summaryNoRecordsColspan ?>" class="text-center text-muted py-3"><?= __t('no_records_found') ?></td></tr>
            <?php else: ?>
              <?php
                $rev = ['count' => 0, 'q1' => 0.0, 'q2' => 0.0, 'q3' => 0.0, 'q4' => 0.0, 'annual' => 0.0];
                $exp = ['count' => 0, 'q1' => 0.0, 'q2' => 0.0, 'q3' => 0.0, 'q4' => 0.0, 'annual' => 0.0];
                $section = ['count' => 0, 'q1' => 0.0, 'q2' => 0.0, 'q3' => 0.0, 'q4' => 0.0, 'annual' => 0.0];
                $currentStmt = null;
                $printedHeader = false;
              ?>
              <?php foreach ($summaryRows as $row): ?>
                <?php
                  $q1 = (float)$row['SumBP1'] + (float)$row['SumBP2'] + (float)$row['SumBP3'];
                  $q2 = (float)$row['SumBP4'] + (float)$row['SumBP5'] + (float)$row['SumBP6'];
                  $q3 = (float)$row['SumBP7'] + (float)$row['SumBP8'] + (float)$row['SumBP9'];
                  $q4 = (float)$row['SumBP10'] + (float)$row['SumBP11'] + (float)$row['SumBP12'];
                  $annual = (float)$row['SumBPTotal'];
                  $stmtClass = strtoupper((string)($row['StatementClass'] ?? ''));

                  if ($currentStmt !== null && $stmtClass !== $currentStmt) {
                      $label = $currentStmt === 'REVENUE' ? __t('tx_total_revenue') : ($currentStmt === 'EXPENDITURE' ? __t('tx_total_expenditure') : __t('tx_total'));
                ?>
                  <tr class="table-light fw-semibold">
                    <td colspan="<?= $summaryLeadColspan ?>" class="text-end"><?= h($label) ?></td>
                    <td class="text-end"><?= amt_span($section['q1']) ?></td>
                    <td class="text-end"><?= amt_span($section['q2']) ?></td>
                    <td class="text-end"><?= amt_span($section['q3']) ?></td>
                    <td class="text-end"><?= amt_span($section['q4']) ?></td>
                    <td class="text-end"><?= amt_span($section['annual']) ?></td>
                    <td></td>
                    <td></td>
                  </tr>
                <?php
                      $section = ['count' => 0, 'q1' => 0.0, 'q2' => 0.0, 'q3' => 0.0, 'q4' => 0.0, 'annual' => 0.0];
                  }
                  if ($currentStmt !== $stmtClass) {
                      $currentStmt = $stmtClass;
                  }
                  $section['count'] += (int)$row['TxCount'];
                  $section['q1'] += $q1; $section['q2'] += $q2; $section['q3'] += $q3; $section['q4'] += $q4; $section['annual'] += $annual;
                  if ($stmtClass === 'REVENUE') {
                    $rev['count'] += (int)$row['TxCount'];
                    $rev['q1'] += $q1; $rev['q2'] += $q2; $rev['q3'] += $q3; $rev['q4'] += $q4; $rev['annual'] += $annual;
                  } elseif ($stmtClass === 'EXPENDITURE') {
                    $exp['count'] += (int)$row['TxCount'];
                    $exp['q1'] += $q1; $exp['q2'] += $q2; $exp['q3'] += $q3; $exp['q4'] += $q4; $exp['annual'] += $annual;
                  }
                ?>
                <tr>
                  <td>
                    <?php if ($isBudgetClassSummary): ?>
                      <?= h((string)$row['GLKey'] . (((string)($row['GroupName'] ?? '')) !== '' ? ' - ' . (string)$row['GroupName'] : '')) ?>
                    <?php elseif ($isBudgetClassGlGroupSummary): ?>
                      <?= h((string)$row['TransactionTypeCode'] . (((string)($row['TransactionTypeName'] ?? '')) !== '' ? ' - ' . (string)$row['TransactionTypeName'] : '')) ?>
                    <?php else: ?>
                      <?= h((string)$row['GroupName']) ?>
                    <?php endif; ?>
                  </td>
                  <?php if ($isBudgetClassGlGroupSummary): ?>
                    <td><?= h((string)$row['GroupName']) ?></td>
                  <?php endif; ?>
                  <?php if (!$isBudgetClassSummary): ?>
                    <td><?= h((string)$row['GLKey']) ?></td>
                  <?php endif; ?>
                  <td class="text-end"><?= amt_span($q1) ?></td>
                  <td class="text-end"><?= amt_span($q2) ?></td>
                  <td class="text-end"><?= amt_span($q3) ?></td>
                  <td class="text-end"><?= amt_span($q4) ?></td>
                  <td class="text-end"><?= amt_span($annual) ?></td>
                  <td class="text-end">
                    <?php
                      $ttKey = $isBudgetClassSummary
                        ? trim((string)$row['GLKey'])
                        : ($isBudgetClassGlGroupSummary ? trim((string)$row['TransactionTypeCode']) : '');
                    ?>
                    <?php if (($isBudgetClassSummary || $isBudgetClassGlGroupSummary) && $ttKey !== '' && !empty($ceilingByTransactionType[$ttKey])): ?>
                      <?php $cb = $ceilingByTransactionType[$ttKey]; ?>
                      <?= amt_span($cb['balance']) ?> / <?= amt_span($cb['ceiling']) ?>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <?php
                      if ($summaryBy === 'transaction_type') {
                        $drillParams = ['mode' => 'level3', 'tt' => (string)$row['GLKey'], 'gl_code' => '', 'account_code' => ''];
                      } elseif ($summaryBy === 'transaction_type_gl_group') {
                        $drillParams = ['mode' => 'detail', 'tt' => (string)$row['TransactionTypeCode'], 'gl_prefix' => (string)$row['GLKey'], 'gl_code' => '', 'account_code' => ''];
                      } else {
                        $drillParams = ['mode' => 'detail', 'gl_prefix' => (string)$row['GLKey'], 'gl_code' => '', 'tt' => '', 'account_code' => ''];
                      }
                    ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= h($buildListUrl($drillParams)) ?>">
                      <i class="bi bi-box-arrow-in-down"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($currentStmt !== null): ?>
                <?php
                  $label = $currentStmt === 'REVENUE' ? __t('tx_total_revenue') : ($currentStmt === 'EXPENDITURE' ? __t('tx_total_expenditure') : __t('tx_total'));
                ?>
                <tr class="table-light fw-semibold">
                  <td colspan="<?= $summaryLeadColspan ?>" class="text-end"><?= h($label) ?></td>
                  <td class="text-end"><?= amt_span($section['q1']) ?></td>
                  <td class="text-end"><?= amt_span($section['q2']) ?></td>
                  <td class="text-end"><?= amt_span($section['q3']) ?></td>
                  <td class="text-end"><?= amt_span($section['q4']) ?></td>
                  <td class="text-end"><?= amt_span($section['annual']) ?></td>
                  <td></td>
                  <td></td>
                </tr>
              <?php endif; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($summaryRows)): ?>
          <tfoot class="table-light">
            <tr>
              <th colspan="<?= $summaryLeadColspan ?>" class="text-end"><?= __t('tx_net_revenue_expenditure') ?></th>
              <th class="text-end"><?= amt_span($rev['q1'] - $exp['q1']) ?></th>
              <th class="text-end"><?= amt_span($rev['q2'] - $exp['q2']) ?></th>
              <th class="text-end"><?= amt_span($rev['q3'] - $exp['q3']) ?></th>
              <th class="text-end"><?= amt_span($rev['q4'] - $exp['q4']) ?></th>
              <th class="text-end"><?= amt_span($rev['annual'] - $exp['annual']) ?></th>
              <th></th>
              <th></th>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    <?php elseif ($mode === 'level2'): ?>
      <div class="mb-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= h($backOneLevelUrl) ?>">
          <i class="bi bi-arrow-left me-1"></i><?= h($backLabel) ?>
        </a>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= __t('tx_gl_account_code') ?></th>
              <th><?= __t('tx_transaction_type') ?></th>
              <th class="text-end">Q1</th>
              <th class="text-end">Q2</th>
              <th class="text-end">Q3</th>
              <th class="text-end">Q4</th>
              <th class="text-end">Annual</th>
              <th class="text-end">Ceiling Balance</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($summaryRows)): ?>
              <tr><td colspan="9" class="text-center text-muted py-3"><?= __t('no_records_found') ?></td></tr>
            <?php else: ?>
              <?php
                $gQ1 = 0.0; $gQ2 = 0.0; $gQ3 = 0.0; $gQ4 = 0.0; $gAnnual = 0.0;
              ?>
              <?php foreach ($summaryRows as $row): ?>
                <?php
                  $q1 = (float)$row['SumBP1'] + (float)$row['SumBP2'] + (float)$row['SumBP3'];
                  $q2 = (float)$row['SumBP4'] + (float)$row['SumBP5'] + (float)$row['SumBP6'];
                  $q3 = (float)$row['SumBP7'] + (float)$row['SumBP8'] + (float)$row['SumBP9'];
                  $q4 = (float)$row['SumBP10'] + (float)$row['SumBP11'] + (float)$row['SumBP12'];
                  $annual = (float)$row['SumBPTotal'];
                  $gQ1 += $q1; $gQ2 += $q2; $gQ3 += $q3; $gQ4 += $q4; $gAnnual += $annual;
                ?>
                <tr>
                  <td><?= h((string)$row['GLAccountCode']) ?></td>
                  <td><?= h((string)$row['TransactionTypeCode'] . (((string)($row['TransactionTypeName'] ?? '')) !== '' ? ' - ' . (string)$row['TransactionTypeName'] : '')) ?></td>
                  <td class="text-end"><?= amt_span($q1) ?></td>
                  <td class="text-end"><?= amt_span($q2) ?></td>
                  <td class="text-end"><?= amt_span($q3) ?></td>
                  <td class="text-end"><?= amt_span($q4) ?></td>
                  <td class="text-end"><?= amt_span($annual) ?></td>
                  <td class="text-end">
                    <?php $ttKey = trim((string)$row['TransactionTypeCode']); ?>
                    <?php if ($ttKey !== '' && !empty($ceilingByTransactionType[$ttKey])): ?>
                      <?php $cb = $ceilingByTransactionType[$ttKey]; ?>
                      <?= amt_span($cb['balance']) ?> / <?= amt_span($cb['ceiling']) ?>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= h($buildListUrl([
                      'mode' => 'level3',
                      'gl_prefix' => (string)($filters['gl_prefix'] ?? ''),
                      'gl_code' => (string)$row['GLAccountCode'],
                      'tt' => (string)$row['TransactionTypeCode'],
                      'account_code' => '',
                    ])) ?>">
                      <i class="bi bi-box-arrow-in-down"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($summaryRows)): ?>
          <tfoot class="table-light">
            <tr>
              <th colspan="2" class="text-end"><?= __t('tx_total') ?></th>
              <th class="text-end"><?= amt_span($gQ1) ?></th>
              <th class="text-end"><?= amt_span($gQ2) ?></th>
              <th class="text-end"><?= amt_span($gQ3) ?></th>
              <th class="text-end"><?= amt_span($gQ4) ?></th>
              <th class="text-end"><?= amt_span($gAnnual) ?></th>
              <th></th>
              <th></th>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    <?php elseif ($mode === 'level3'): ?>
      <div class="mb-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= h($backOneLevelUrl) ?>">
          <i class="bi bi-arrow-left me-1"></i><?= h($backLabel) ?>
        </a>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= __t('tx_account_code') ?></th>
              <th><?= __t('tx_gl_account_code') ?></th>
              <th><?= __t('tx_transaction_type') ?></th>
              <th class="text-end">Q1</th>
              <th class="text-end">Q2</th>
              <th class="text-end">Q3</th>
              <th class="text-end">Q4</th>
              <th class="text-end">Annual</th>
              <th class="text-end">Ceiling Balance</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($summaryRows)): ?>
              <tr><td colspan="10" class="text-center text-muted py-3"><?= __t('no_records_found') ?></td></tr>
            <?php else: ?>
              <?php
                $gQ1 = 0.0; $gQ2 = 0.0; $gQ3 = 0.0; $gQ4 = 0.0; $gAnnual = 0.0;
              ?>
              <?php foreach ($summaryRows as $row): ?>
                <?php
                  $q1 = (float)$row['SumBP1'] + (float)$row['SumBP2'] + (float)$row['SumBP3'];
                  $q2 = (float)$row['SumBP4'] + (float)$row['SumBP5'] + (float)$row['SumBP6'];
                  $q3 = (float)$row['SumBP7'] + (float)$row['SumBP8'] + (float)$row['SumBP9'];
                  $q4 = (float)$row['SumBP10'] + (float)$row['SumBP11'] + (float)$row['SumBP12'];
                  $annual = (float)$row['SumBPTotal'];
                  $gQ1 += $q1; $gQ2 += $q2; $gQ3 += $q3; $gQ4 += $q4; $gAnnual += $annual;
                ?>
                <tr>
                  <td><?= h((string)$row['AccountCode']) ?></td>
                  <td><?= h((string)$row['GLAccountCode']) ?></td>
                  <td><?= h((string)$row['TransactionTypeCode'] . (((string)($row['TransactionTypeName'] ?? '')) !== '' ? ' - ' . (string)$row['TransactionTypeName'] : '')) ?></td>
                  <td class="text-end"><?= amt_span($q1) ?></td>
                  <td class="text-end"><?= amt_span($q2) ?></td>
                  <td class="text-end"><?= amt_span($q3) ?></td>
                  <td class="text-end"><?= amt_span($q4) ?></td>
                  <td class="text-end"><?= amt_span($annual) ?></td>
                  <td class="text-end">
                    <?php $ttKey = trim((string)$row['TransactionTypeCode']); ?>
                    <?php if ($ttKey !== '' && !empty($ceilingByTransactionType[$ttKey])): ?>
                      <?php $cb = $ceilingByTransactionType[$ttKey]; ?>
                      <?= amt_span($cb['balance']) ?> / <?= amt_span($cb['ceiling']) ?>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= h($buildListUrl([
                      'mode' => 'detail',
                      'gl_prefix' => (string)($filters['gl_prefix'] ?? ''),
                      'gl_code' => (string)$row['GLAccountCode'],
                      'tt' => (string)$row['TransactionTypeCode'],
                      'account_code' => (string)$row['AccountCode'],
                    ])) ?>">
                      <i class="bi bi-box-arrow-in-down"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($summaryRows)): ?>
          <tfoot class="table-light">
            <tr>
              <th colspan="3" class="text-end"><?= __t('tx_total') ?></th>
              <th class="text-end"><?= amt_span($gQ1) ?></th>
              <th class="text-end"><?= amt_span($gQ2) ?></th>
              <th class="text-end"><?= amt_span($gQ3) ?></th>
              <th class="text-end"><?= amt_span($gQ4) ?></th>
              <th class="text-end"><?= amt_span($gAnnual) ?></th>
              <th></th>
              <th></th>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>
    <?php else: ?>
      <div class="mb-2">
        <a class="btn btn-sm btn-outline-secondary" href="<?= h($backOneLevelUrl) ?>">
          <i class="bi bi-arrow-left me-1"></i><?= h($backLabel) ?>
        </a>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= __t('tx_transaction_id') ?></th>
              <th><?= __t('tx_account_code') ?></th>
              <th><?= __t('tx_gl_account_code') ?></th>
              <th><?= __t('tx_transaction_type') ?></th>
              <th><?= h($segmentLabel) ?></th>
              <th><?= h($segmentLabel2) ?></th>
              <th class="text-end">Q1</th>
              <th class="text-end">Q2</th>
              <th class="text-end">Q3</th>
              <th class="text-end">Q4</th>
              <th class="text-end">Annual</th>
              <th class="text-end">Ceiling Balance</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr><td colspan="14" class="text-center text-muted py-3"><?= __t('no_records_found') ?></td></tr>
            <?php else: ?>
              <?php
                $gQ1 = 0.0; $gQ2 = 0.0; $gQ3 = 0.0; $gQ4 = 0.0; $gAnnual = 0.0;
              ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $q1 = (float)$row['BP1'] + (float)$row['BP2'] + (float)$row['BP3'];
                  $q2 = (float)$row['BP4'] + (float)$row['BP5'] + (float)$row['BP6'];
                  $q3 = (float)$row['BP7'] + (float)$row['BP8'] + (float)$row['BP9'];
                  $q4 = (float)$row['BP10'] + (float)$row['BP11'] + (float)$row['BP12'];
                  $annual = (float)$row['BPTotal'];
                  $gQ1 += $q1; $gQ2 += $q2; $gQ3 += $q3; $gQ4 += $q4; $gAnnual += $annual;
                ?>
                <tr>
                  <td>
                    <a href="index.php?route=transaction-input/editor&tx=<?= urlencode((string)$row['TransactionID']) ?>">
                      <?= h((string)$row['TransactionID']) ?>
                    </a>
                  </td>
                  <td><?= h((string)$row['AccountCode']) ?></td>
                  <td><?= h((string)$row['GLAccountCode']) ?></td>
                  <td><?= h((string)$row['TransactionTypeCode'] . (((string)($row['TransactionTypeName'] ?? '')) !== '' ? ' - ' . (string)$row['TransactionTypeName'] : '')) ?></td>
                  <td><?= h((string)$row['SegmentCode']) ?></td>
                  <td><?= h((string)$row['SegmentCode2']) ?></td>
                  <td class="text-end"><?= amt_span($q1) ?></td>
                  <td class="text-end"><?= amt_span($q2) ?></td>
                  <td class="text-end"><?= amt_span($q3) ?></td>
                  <td class="text-end"><?= amt_span($q4) ?></td>
                  <td class="text-end"><?= amt_span($annual) ?></td>
                  <td class="text-end">
                    <?php if ($row['CeilingBalance'] !== null): ?>
                      <?= amt_span((float)$row['CeilingBalance']) ?> / <?= amt_span((float)($row['CeilingTotal'] ?? 0)) ?>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <a class="btn btn-sm btn-outline-secondary" href="index.php?route=transaction-input/editor&tx=<?= urlencode((string)$row['TransactionID']) ?>">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
          <?php if (!empty($rows)): ?>
          <tfoot class="table-light">
            <tr>
              <th colspan="7" class="text-end"><?= __t('tx_grand_total') ?></th>
              <th class="text-end"><?= amt_span($gQ1) ?></th>
              <th class="text-end"><?= amt_span($gQ2) ?></th>
              <th class="text-end"><?= amt_span($gQ3) ?></th>
              <th class="text-end"><?= amt_span($gQ4) ?></th>
              <th class="text-end"><?= amt_span($gAnnual) ?></th>
              <th></th>
              <th></th>
            </tr>
          </tfoot>
          <?php endif; ?>
        </table>
      </div>

      <p class="text-muted small mt-2"><?= __t('showing') ?> <?= count($rows) ?> <?= __t('of') ?> <?= (int)$total ?> <?= __t('records') ?>.</p>
    <?php endif; ?>

    <script>
      (function () {
        const addBtn = document.getElementById('addFilterRowBtn');
        const rows = document.getElementById('advFilterRows');
        if (!addBtn || !rows) return;

        const allowedFields = <?php echo json_encode($allowedFilterFields); ?> || [];

        const template = () => {
          const row = document.createElement('div');
          row.className = 'row g-2 adv-filter-row mb-1';
          const options = ['<option value=\"\">Field</option>']
            .concat(allowedFields.map(f => `<option value=\"${f}\">${f}</option>`))
            .join('');

          row.innerHTML =
            '<div class=\"col-md-4\">' +
              '<select name=\"filter_field[]\" class=\"form-select\">' +
                options +
              '</select>' +
            '</div>' +
            '<div class=\"col-md-2\">' +
              '<select name=\"filter_op[]\" class=\"form-select\">' +
                '<option value=\"equals\"><?= __t('tx_equals') ?></option>' +
                '<option value=\"contains\"><?= __t('tx_contains') ?></option>' +
              '</select>' +
            '</div>' +
            '<div class=\"col-md-6\">' +
              '<input type=\"text\" name=\"filter_value[]\" class=\"form-control\" placeholder=\"<?= __t('value') ?>\">' +
            '</div>';

          return row;
        };

        addBtn.addEventListener('click', () => {
          rows.appendChild(template());
        });
      })();
    </script>
  </div>
</div>

<div class="modal fade" id="transactionWorkbookUploadModal" tabindex="-1" aria-labelledby="transactionWorkbookUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="index.php?route=transaction-input/upload-process" enctype="multipart/form-data">
        <input type="hidden" name="_csrf" value="<?= h((string)($_csrf ?? '')) ?>">
        <div class="modal-header">
          <h5 class="modal-title" id="transactionWorkbookUploadModalLabel"><i class="bi bi-upload me-2"></i>Upload Budget Workbook</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __t('close') ?>"></button>
        </div>
        <div class="modal-body">
          <div class="small text-muted mb-3">
            Upload the password-protected workbook generated from this Transaction Input screen. CBMS will validate the workbook identity and context before any transaction rows can be committed.
          </div>
          <label for="transactionWorkbookUploadFile" class="form-label">Workbook file</label>
          <input type="file" class="form-control" id="transactionWorkbookUploadFile" name="uploadFile" accept=".xlsx" required>
          <div class="form-text">The workbook must match Fiscal Year <?= h((string)$ctxFy) ?>, Version <?= h((string)$ctxVer) ?><?= $ctxDataObject !== '' ? ', Data Object ' . h($ctxDataObject) : '' ?>.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= __t('cancel') ?></button>
          <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>Validate Upload</button>
        </div>
      </form>
    </div>
  </div>
</div>
