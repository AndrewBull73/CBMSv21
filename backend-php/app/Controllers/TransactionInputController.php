<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;

final class TransactionInputController extends BaseController
{
    protected array $acl = [
        'editor' => ['auth' => true, 'permsAny' => ['ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'stub' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'batchRunner' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'singleSaveLoadTest' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function editor(): void
    {
        $embedInLayout = true;
        ob_start();
        require __DIR__ . '/../../public/transaction_input_editor.php';
        $editorContent = (string)ob_get_clean();

        $this->render('transactioninput/EditorHost', [
            'title' => __t('menu_transaction_input_editor'),
            'editorContent' => $editorContent,
        ]);
    }

    public function list(): void
    {
        $ctxFy = (int)SessionHelper::get('FiscalYearID', 0);
        $ctxVer = (int)SessionHelper::get('VersionID', 0);
        $ctxDataObject = (string)SessionHelper::get('scope.dataobject_code', '');
        $ctxDataObjectName = (string)SessionHelper::get('scope.dataobject_name', '');
        $ctxDataObjectPath = [];

        if ($ctxFy > 0 && $ctxDataObject !== '') {
            try {
                $pathSql = "SELECT tr.Depth,
                                   c.DataObjectCode,
                                   c.DataObjectName,
                                   c.DataObjectTypeID,
                                   t.DataObjectTypeName
                            FROM dbo.tblDataObjectTree tr
                            INNER JOIN dbo.tblDataObjectCodes c
                                ON c.FiscalYearID = tr.FiscalYearID
                               AND c.DataObjectCode = tr.AncestorCode
                            LEFT JOIN dbo.tblDataObjectTypes t
                                ON t.DataObjectTypeID = c.DataObjectTypeID
                            WHERE tr.FiscalYearID = ?
                              AND tr.DescendantCode = ?
                            ORDER BY tr.Depth DESC";
                $pathStmt = $this->db->prepare($pathSql);
                $pathStmt->execute([$ctxFy, $ctxDataObject]);
                $ctxDataObjectPath = $pathStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $ctxDataObjectPath = [];
            }
        }

        $filters = [
            'q' => trim((string)($_GET['q'] ?? '')),
            'mode' => trim((string)($_GET['mode'] ?? 'summary')),
            'summary_by' => trim((string)($_GET['summary_by'] ?? 'gl_prefix')),
            'tt' => trim((string)($_GET['tt'] ?? '')),
            'gl_level' => trim((string)($_GET['gl_level'] ?? '1')),
            'gl_prefix' => trim((string)($_GET['gl_prefix'] ?? '')),
            'gl_code' => trim((string)($_GET['gl_code'] ?? '')),
            'account_code' => trim((string)($_GET['account_code'] ?? '')),
            'report_id' => trim((string)($_GET['report_id'] ?? '1')),
            'filter_field' => $_GET['filter_field'] ?? [],
            'filter_op' => $_GET['filter_op'] ?? [],
            'filter_value' => $_GET['filter_value'] ?? [],
        ];
        $ctxAccountPath = [];

        $segmentCol = 'Segment1Code';
        $segmentCol2 = 'Segment2Code';

        $allowedFilterFields = [
            'TransactionTypeCode' => true,
            'GLAccountCode' => true,
        ];
        for ($i = 1; $i <= 20; $i++) {
            $allowedFilterFields["Segment{$i}Code"] = true;
        }
        $allowedOps = ['equals', 'contains'];

        $dynamicFilters = [];
        if (is_array($filters['filter_field']) && is_array($filters['filter_op']) && is_array($filters['filter_value'])) {
            $count = min(count($filters['filter_field']), count($filters['filter_op']), count($filters['filter_value']));
            for ($i = 0; $i < $count; $i++) {
                $field = trim((string)$filters['filter_field'][$i]);
                $op = trim((string)$filters['filter_op'][$i]);
                $value = trim((string)$filters['filter_value'][$i]);
                if ($field === '' || $value === '') {
                    continue;
                }
                if (!isset($allowedFilterFields[$field])) {
                    continue;
                }
                if (!in_array($op, $allowedOps, true)) {
                    $op = 'equals';
                }
                $dynamicFilters[] = ['field' => $field, 'op' => $op, 'value' => $value];
            }
        }

        $rows = [];
        $summaryRows = [];
        $ceilingByTransactionType = [];
        $total = 0;
        $warning = '';
        $error = '';
        $loadCeilingByTransactionType = function (array $rows, string $txTypeField) use ($ctxFy, $ctxVer, $ctxDataObject): array {
            $out = [];
            $ttCodes = [];
            foreach ($rows as $sr) {
                $tt = trim((string)($sr[$txTypeField] ?? ''));
                if ($tt !== '') {
                    $ttCodes[$tt] = true;
                }
            }
            if (empty($ttCodes)) {
                return $out;
            }

            $ttList = array_keys($ttCodes);
            $in = implode(',', array_fill(0, count($ttList), '?'));
            $sqlDef = "SELECT CeilingDefinitionID, TransactionTypeCode, DataObjectCode, Priority, CeilingBPTotal
                       FROM dbo.tblCeilingDefinition
                       WHERE FiscalYearID = ?
                         AND VersionID = ?
                         AND ActiveFlag = 1
                         AND ApprovedFlag = 1
                         AND TransactionTypeCode IN ($in)";
            $defParams = array_merge([$ctxFy, $ctxVer], $ttList);
            $defStmt = $this->db->prepare($sqlDef);
            $defStmt->execute($defParams);
            $defs = $defStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $chosenDefByTt = [];
            foreach ($defs as $d) {
                $tt = (string)($d['TransactionTypeCode'] ?? '');
                if ($tt === '') {
                    continue;
                }
                $candidate = [
                    'id' => (int)$d['CeilingDefinitionID'],
                    'priority' => (int)($d['Priority'] ?? 999999),
                    'dataobject' => (string)($d['DataObjectCode'] ?? ''),
                    'exact_do' => ((string)($d['DataObjectCode'] ?? '') === $ctxDataObject) ? 1 : 0,
                    'ceiling_total' => (float)($d['CeilingBPTotal'] ?? 0),
                ];
                if (!isset($chosenDefByTt[$tt])) {
                    $chosenDefByTt[$tt] = $candidate;
                    continue;
                }
                $current = $chosenDefByTt[$tt];
                if ($candidate['exact_do'] > $current['exact_do']
                    || ($candidate['exact_do'] === $current['exact_do'] && $candidate['priority'] < $current['priority'])
                    || ($candidate['exact_do'] === $current['exact_do'] && $candidate['priority'] === $current['priority'] && $candidate['id'] < $current['id'])) {
                    $chosenDefByTt[$tt] = $candidate;
                }
            }

            if (empty($chosenDefByTt)) {
                return $out;
            }

            $defIds = array_values(array_map(static fn($x) => (int)$x['id'], $chosenDefByTt));
            if (!empty($defIds)) {
                $inDef = implode(',', array_fill(0, count($defIds), '?'));
                $balSql = "SELECT CeilingDefinitionID, BalanceBPTotal
                           FROM dbo.tblCeilingBalance
                           WHERE CeilingDefinitionID IN ($inDef)";
                $balStmt = $this->db->prepare($balSql);
                $balStmt->execute($defIds);
                $balRows = $balStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $balByDef = [];
                foreach ($balRows as $br) {
                    $balByDef[(int)$br['CeilingDefinitionID']] = (float)($br['BalanceBPTotal'] ?? 0);
                }

                foreach ($chosenDefByTt as $tt => $def) {
                    $ttKey = trim((string)$tt);
                    $defId = (int)$def['id'];
                    if ($ttKey === '') {
                        continue;
                    }
                    $dbBal = (float)($balByDef[$defId] ?? 0);
                    $out[$ttKey] = [
                        'balance' => $dbBal,
                        'ceiling' => (float)($def['ceiling_total'] ?? 0),
                        'remaining' => $dbBal - (float)($def['ceiling_total'] ?? 0),
                        'definition_id' => $defId,
                        'source' => 'db',
                    ];
                }
            }

            if (class_exists('\\Predis\\Client')) {
                $redis = new \Predis\Client([
                    'scheme' => 'tcp',
                    'host' => '127.0.0.1',
                    'port' => 6379,
                ]);
                foreach ($chosenDefByTt as $tt => $def) {
                    $ttKey = trim((string)$tt);
                    if ($ttKey === '') {
                        continue;
                    }
                    $key = 'ceiling:balance:v1:' . (int)$def['id'];
                    $vals = $redis->hmget($key, ['bal_total', 'ceil_total']);
                    $bal = isset($vals[0]) ? (float)$vals[0] : null;
                    $ceil = isset($vals[1]) ? (float)$vals[1] : null;
                    if ($bal === null && $ceil === null) {
                        continue;
                    }
                    $out[$ttKey] = [
                        'balance' => (float)($bal ?? 0),
                        'ceiling' => (float)($ceil ?? ($def['ceiling_total'] ?? 0)),
                        'remaining' => (float)($bal ?? 0) - (float)($ceil ?? ($def['ceiling_total'] ?? 0)),
                        'definition_id' => (int)$def['id'],
                        'source' => 'redis',
                    ];
                }
            }

            return $out;
        };

        if ($ctxFy <= 0 || $ctxVer <= 0 || $ctxDataObject === '') {
            $warning = __t('tx_set_context_warning');
        } else {
            try {
                $mode = strtolower($filters['mode']);
                if (!in_array($mode, ['summary', 'level2', 'level3', 'detail'], true)) {
                    $mode = 'summary';
                }

                // Auto-correct drill level based on provided params.
                if ($filters['account_code'] !== '') {
                    $mode = 'detail';
                } elseif ($filters['gl_code'] !== '') {
                    $mode = 'level3';
                } elseif ($filters['tt'] !== '' && $filters['gl_prefix'] !== '') {
                    $mode = 'detail';
                } elseif ($filters['tt'] !== '') {
                    $mode = 'level3';
                } elseif ($filters['gl_prefix'] !== '') {
                    $mode = 'level2';
                } else {
                    $mode = 'summary';
                }

                $filters['mode'] = $mode;
                $summaryBy = strtolower((string)$filters['summary_by']);
                if (!in_array($summaryBy, ['gl_prefix', 'transaction_type', 'transaction_type_gl_group'], true)) {
                    $summaryBy = 'gl_prefix';
                }
                $filters['summary_by'] = $summaryBy;

                // Build account/budget-class drill path for UI context.
                $effectiveGlPrefix = $filters['gl_prefix'];
                if ($effectiveGlPrefix === '' && $filters['gl_code'] !== '') {
                    $lvl = (int)$filters['gl_level'];
                    if (!in_array($lvl, [1, 2, 3], true)) {
                        $lvl = 1;
                    }
                    if ($lvl === 2) {
                        $effectiveGlPrefix = substr($filters['gl_code'], 0, 4);
                    } elseif ($lvl === 3) {
                        $effectiveGlPrefix = $filters['gl_code'];
                    } else {
                        $effectiveGlPrefix = substr($filters['gl_code'], 0, 2);
                    }
                }

                $ttName = '';
                if ($filters['tt'] !== '') {
                    try {
                        $ttStmt = $this->db->prepare("SELECT TOP 1 TransactionTypeName FROM dbo.tblTransactionTypes WHERE TransactionTypeCode = ?");
                        $ttStmt->execute([$filters['tt']]);
                        $ttName = (string)($ttStmt->fetchColumn() ?: '');
                    } catch (\Throwable $e) {
                        $ttName = '';
                    }
                }

                if ($mode === 'summary') {
                    if ($summaryBy === 'transaction_type') {
                        $ctxAccountPath[] = 'Budget Class Summary';
                    } elseif ($summaryBy === 'transaction_type_gl_group') {
                        $ctxAccountPath[] = 'Budget Class + GL Grouping Summary';
                    } else {
                        $ctxAccountPath[] = 'GL Prefix Summary';
                    }
                }
                if ($effectiveGlPrefix !== '') {
                    $ctxAccountPath[] = 'GL Prefix: ' . $effectiveGlPrefix;
                }
                if ($filters['gl_code'] !== '') {
                    $ctxAccountPath[] = 'GL Account: ' . $filters['gl_code'];
                }
                if ($filters['tt'] !== '') {
                    $ctxAccountPath[] = 'Budget Class: ' . $filters['tt'] . ($ttName !== '' ? ' (' . $ttName . ')' : '');
                }
                if ($filters['account_code'] !== '') {
                    $ctxAccountPath[] = 'Account: ' . $filters['account_code'];
                }

                if ($filters['mode'] === 'summary') {
                    $where = 'WHERE ti.FiscalYearID = ? AND ti.VersionID = ?
                              AND EXISTS (
                                  SELECT 1
                                  FROM dbo.tblDataObjectTree tr
                                  WHERE tr.FiscalYearID = ti.FiscalYearID
                                    AND tr.AncestorCode = ?
                                    AND tr.DescendantCode = ti.DataObjectCode
                              )';
                    $params = [$ctxFy, $ctxVer, $ctxDataObject];

                    foreach ($dynamicFilters as $df) {
                        if ($df['op'] === 'contains') {
                            $where .= " AND ti.{$df['field']} LIKE ?";
                            $params[] = '%' . $df['value'] . '%';
                        } else {
                            $where .= " AND ti.{$df['field']} = ?";
                            $params[] = $df['value'];
                        }
                    }

                    $glLevel = (int)$filters['gl_level'];
                    if (!in_array($glLevel, [1, 2, 3], true)) {
                        $glLevel = 1;
                    }
                    $glKeyExpr = $glLevel === 1
                        ? "LEFT(ti.GLAccountCode, 2)"
                        : ($glLevel === 2 ? "LEFT(ti.GLAccountCode, 4)" : "ti.GLAccountCode");
                    $reportId = (int)$filters['report_id'];
                    if ($reportId <= 0) {
                        $reportId = 1;
                    }

                    if ($summaryBy === 'transaction_type') {
                        $sql = "WITH Latest AS (
                                    SELECT rf.*,
                                           ROW_NUMBER() OVER (PARTITION BY rf.TransactionID ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC) AS rn
                                    FROM tblTransactionResultFlat rf
                                )
                                SELECT
                                    UPPER(COALESCE(ttt.StatementClass, '')) AS StatementClass,
                                    COALESCE(ttt.TransactionTypeName, ti.TransactionTypeCode) AS GroupName,
                                    ti.TransactionTypeCode AS GLKey,
                                    COUNT(*) AS TxCount,
                                    SUM(COALESCE(l.BP1, ti.BP1InpN, 0)) AS SumBP1,
                                    SUM(COALESCE(l.BP2, ti.BP2InpN, 0)) AS SumBP2,
                                    SUM(COALESCE(l.BP3, ti.BP3InpN, 0)) AS SumBP3,
                                    SUM(COALESCE(l.BP4, ti.BP4InpN, 0)) AS SumBP4,
                                    SUM(COALESCE(l.BP5, ti.BP5InpN, 0)) AS SumBP5,
                                    SUM(COALESCE(l.BP6, ti.BP6InpN, 0)) AS SumBP6,
                                    SUM(COALESCE(l.BP7, ti.BP7InpN, 0)) AS SumBP7,
                                    SUM(COALESCE(l.BP8, ti.BP8InpN, 0)) AS SumBP8,
                                    SUM(COALESCE(l.BP9, ti.BP9InpN, 0)) AS SumBP9,
                                    SUM(COALESCE(l.BP10, ti.BP10InpN, 0)) AS SumBP10,
                                    SUM(COALESCE(l.BP11, ti.BP11InpN, 0)) AS SumBP11,
                                    SUM(COALESCE(l.BP12, ti.BP12InpN, 0)) AS SumBP12,
                                    SUM(COALESCE(l.BPTotal, ti.BPTotalInpN, 0)) AS SumBPTotal
                                FROM tblTransactionInput ti
                                LEFT JOIN dbo.tblTransactionTypes ttt
                                    ON ttt.TransactionTypeCode = ti.TransactionTypeCode
                                LEFT JOIN Latest l
                                    ON l.TransactionID = ti.TransactionID AND l.rn = 1
                                {$where}
                                GROUP BY UPPER(COALESCE(ttt.StatementClass, '')), COALESCE(ttt.TransactionTypeName, ti.TransactionTypeCode), ti.TransactionTypeCode
                                ORDER BY CASE UPPER(COALESCE(ttt.StatementClass, '')) WHEN 'REVENUE' THEN 1 WHEN 'EXPENDITURE' THEN 2 ELSE 9 END,
                                         ti.TransactionTypeCode";
                    } elseif ($summaryBy === 'transaction_type_gl_group') {
                        $sql = "WITH Latest AS (
                                    SELECT rf.*,
                                           ROW_NUMBER() OVER (PARTITION BY rf.TransactionID ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC) AS rn
                                    FROM tblTransactionResultFlat rf
                                )
                                SELECT
                                    grp.StatementClass,
                                    COALESCE(ttt.TransactionTypeName, ti.TransactionTypeCode) AS TransactionTypeName,
                                    ti.TransactionTypeCode,
                                    grp.GroupName,
                                    grp.Prefix AS GLKey,
                                    COUNT(*) AS TxCount,
                                    SUM(COALESCE(l.BP1, ti.BP1InpN, 0)) AS SumBP1,
                                    SUM(COALESCE(l.BP2, ti.BP2InpN, 0)) AS SumBP2,
                                    SUM(COALESCE(l.BP3, ti.BP3InpN, 0)) AS SumBP3,
                                    SUM(COALESCE(l.BP4, ti.BP4InpN, 0)) AS SumBP4,
                                    SUM(COALESCE(l.BP5, ti.BP5InpN, 0)) AS SumBP5,
                                    SUM(COALESCE(l.BP6, ti.BP6InpN, 0)) AS SumBP6,
                                    SUM(COALESCE(l.BP7, ti.BP7InpN, 0)) AS SumBP7,
                                    SUM(COALESCE(l.BP8, ti.BP8InpN, 0)) AS SumBP8,
                                    SUM(COALESCE(l.BP9, ti.BP9InpN, 0)) AS SumBP9,
                                    SUM(COALESCE(l.BP10, ti.BP10InpN, 0)) AS SumBP10,
                                    SUM(COALESCE(l.BP11, ti.BP11InpN, 0)) AS SumBP11,
                                    SUM(COALESCE(l.BP12, ti.BP12InpN, 0)) AS SumBP12,
                                    SUM(COALESCE(l.BPTotal, ti.BPTotalInpN, 0)) AS SumBPTotal
                                FROM tblTransactionInput ti
                                LEFT JOIN dbo.tblTransactionTypes ttt
                                    ON ttt.TransactionTypeCode = ti.TransactionTypeCode
                                INNER JOIN dbo.tblGLGrouping grp
                                    ON grp.FiscalYearID = ti.FiscalYearID
                                   AND grp.VersionID = ti.VersionID
                                   AND grp.ReportID = ?
                                   AND grp.Level = ?
                                   AND grp.Prefix = {$glKeyExpr}
                                LEFT JOIN Latest l
                                    ON l.TransactionID = ti.TransactionID AND l.rn = 1
                                {$where}
                                GROUP BY grp.StatementClass, COALESCE(ttt.TransactionTypeName, ti.TransactionTypeCode), ti.TransactionTypeCode, grp.GroupName, grp.Prefix
                                ORDER BY COALESCE(ttt.TransactionTypeName, ti.TransactionTypeCode),
                                         CASE UPPER(COALESCE(grp.StatementClass, '')) WHEN 'REVENUE' THEN 1 WHEN 'EXPENDITURE' THEN 2 ELSE 9 END,
                                         MIN(COALESCE(grp.SortOrder, 999999)), grp.GroupName, grp.Prefix";
                        array_unshift($params, $reportId, $glLevel);
                    } else {
                        $sql = "WITH Latest AS (
                                    SELECT rf.*,
                                           ROW_NUMBER() OVER (PARTITION BY rf.TransactionID ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC) AS rn
                                    FROM tblTransactionResultFlat rf
                                )
                                SELECT
                                    grp.StatementClass,
                                    grp.GroupName,
                                    grp.Prefix AS GLKey,
                                    COUNT(*) AS TxCount,
                                    SUM(COALESCE(l.BP1, ti.BP1InpN, 0)) AS SumBP1,
                                    SUM(COALESCE(l.BP2, ti.BP2InpN, 0)) AS SumBP2,
                                    SUM(COALESCE(l.BP3, ti.BP3InpN, 0)) AS SumBP3,
                                    SUM(COALESCE(l.BP4, ti.BP4InpN, 0)) AS SumBP4,
                                    SUM(COALESCE(l.BP5, ti.BP5InpN, 0)) AS SumBP5,
                                    SUM(COALESCE(l.BP6, ti.BP6InpN, 0)) AS SumBP6,
                                    SUM(COALESCE(l.BP7, ti.BP7InpN, 0)) AS SumBP7,
                                    SUM(COALESCE(l.BP8, ti.BP8InpN, 0)) AS SumBP8,
                                    SUM(COALESCE(l.BP9, ti.BP9InpN, 0)) AS SumBP9,
                                    SUM(COALESCE(l.BP10, ti.BP10InpN, 0)) AS SumBP10,
                                    SUM(COALESCE(l.BP11, ti.BP11InpN, 0)) AS SumBP11,
                                    SUM(COALESCE(l.BP12, ti.BP12InpN, 0)) AS SumBP12,
                                    SUM(COALESCE(l.BPTotal, ti.BPTotalInpN, 0)) AS SumBPTotal
                                FROM tblTransactionInput ti
                                INNER JOIN dbo.tblGLGrouping grp
                                    ON grp.FiscalYearID = ti.FiscalYearID
                                   AND grp.VersionID = ti.VersionID
                                   AND grp.ReportID = ?
                                   AND grp.Level = ?
                                   AND grp.Prefix = {$glKeyExpr}
                                LEFT JOIN Latest l
                                    ON l.TransactionID = ti.TransactionID AND l.rn = 1
                                {$where}
                                GROUP BY grp.StatementClass, grp.GroupName, grp.Prefix
                                ORDER BY CASE UPPER(COALESCE(grp.StatementClass, '')) WHEN 'REVENUE' THEN 1 WHEN 'EXPENDITURE' THEN 2 ELSE 9 END,
                                         MIN(COALESCE(grp.SortOrder, 999999)), grp.GroupName, grp.Prefix";
                        array_unshift($params, $reportId, $glLevel);
                    }

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                    $summaryRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                    // Load ceiling balances for Budget Class summary rows.
                    if (in_array($summaryBy, ['transaction_type', 'transaction_type_gl_group'], true) && !empty($summaryRows)) {
                        try {
                            $ceilingKey = $summaryBy === 'transaction_type' ? 'GLKey' : 'TransactionTypeCode';
                            $ceilingByTransactionType = $loadCeilingByTransactionType($summaryRows, $ceilingKey);
                        } catch (\Throwable $e) {
                            $ceilingByTransactionType = [];
                        }
                    }

                } elseif ($filters['mode'] === 'level2') {
                    $where = 'WHERE ti.FiscalYearID = ? AND ti.VersionID = ?
                              AND EXISTS (
                                  SELECT 1
                                  FROM dbo.tblDataObjectTree tr
                                  WHERE tr.FiscalYearID = ti.FiscalYearID
                                    AND tr.AncestorCode = ?
                                    AND tr.DescendantCode = ti.DataObjectCode
                              )';
                    $params = [$ctxFy, $ctxVer, $ctxDataObject];

                    if ($filters['gl_prefix'] !== '') {
                        $glLevel = (int)$filters['gl_level'];
                        if (!in_array($glLevel, [1, 2, 3], true)) {
                            $glLevel = 1;
                        }
                        if ($glLevel === 1) {
                            $where .= ' AND LEFT(ti.GLAccountCode, 2) = ?';
                        } elseif ($glLevel === 2) {
                            $where .= ' AND LEFT(ti.GLAccountCode, 4) = ?';
                        } else {
                            $where .= ' AND ti.GLAccountCode = ?';
                        }
                        $params[] = $filters['gl_prefix'];
                    }
                    foreach ($dynamicFilters as $df) {
                        if ($df['op'] === 'contains') {
                            $where .= " AND ti.{$df['field']} LIKE ?";
                            $params[] = '%' . $df['value'] . '%';
                        } else {
                            $where .= " AND ti.{$df['field']} = ?";
                            $params[] = $df['value'];
                        }
                    }

                    $sql = "WITH Latest AS (
                                SELECT rf.*,
                                       ROW_NUMBER() OVER (PARTITION BY rf.TransactionID ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC) AS rn
                                FROM tblTransactionResultFlat rf
                            )
                            SELECT
                                ti.GLAccountCode,
                                ti.TransactionTypeCode,
                                ttt.TransactionTypeName,
                                COUNT(*) AS TxCount,
                                SUM(COALESCE(l.BP1, ti.BP1InpN, 0)) AS SumBP1,
                                SUM(COALESCE(l.BP2, ti.BP2InpN, 0)) AS SumBP2,
                                SUM(COALESCE(l.BP3, ti.BP3InpN, 0)) AS SumBP3,
                                SUM(COALESCE(l.BP4, ti.BP4InpN, 0)) AS SumBP4,
                                SUM(COALESCE(l.BP5, ti.BP5InpN, 0)) AS SumBP5,
                                SUM(COALESCE(l.BP6, ti.BP6InpN, 0)) AS SumBP6,
                                SUM(COALESCE(l.BP7, ti.BP7InpN, 0)) AS SumBP7,
                                SUM(COALESCE(l.BP8, ti.BP8InpN, 0)) AS SumBP8,
                                SUM(COALESCE(l.BP9, ti.BP9InpN, 0)) AS SumBP9,
                                SUM(COALESCE(l.BP10, ti.BP10InpN, 0)) AS SumBP10,
                                SUM(COALESCE(l.BP11, ti.BP11InpN, 0)) AS SumBP11,
                                SUM(COALESCE(l.BP12, ti.BP12InpN, 0)) AS SumBP12,
                                SUM(COALESCE(l.BPTotal, ti.BPTotalInpN, 0)) AS SumBPTotal
                            FROM tblTransactionInput ti
                            LEFT JOIN dbo.tblTransactionTypes ttt
                                ON ttt.TransactionTypeCode = ti.TransactionTypeCode
                            LEFT JOIN Latest l
                                ON l.TransactionID = ti.TransactionID AND l.rn = 1
                            {$where}
                            GROUP BY ti.GLAccountCode, ti.TransactionTypeCode, ttt.TransactionTypeName
                            ORDER BY ti.TransactionTypeCode, ti.GLAccountCode";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                    $summaryRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    try {
                        $ceilingByTransactionType = $loadCeilingByTransactionType($summaryRows, 'TransactionTypeCode');
                    } catch (\Throwable $e) {
                        $ceilingByTransactionType = [];
                    }

                } elseif ($filters['mode'] === 'level3') {
                    $where = 'WHERE ti.FiscalYearID = ? AND ti.VersionID = ?
                              AND EXISTS (
                                  SELECT 1
                                  FROM dbo.tblDataObjectTree tr
                                  WHERE tr.FiscalYearID = ti.FiscalYearID
                                    AND tr.AncestorCode = ?
                                    AND tr.DescendantCode = ti.DataObjectCode
                              )';
                    $params = [$ctxFy, $ctxVer, $ctxDataObject];

                    if ($filters['gl_code'] !== '') {
                        $where .= ' AND ti.GLAccountCode = ?';
                        $params[] = $filters['gl_code'];
                    }
                    if ($filters['tt'] !== '') {
                        $where .= ' AND ti.TransactionTypeCode = ?';
                        $params[] = $filters['tt'];
                    }
                    foreach ($dynamicFilters as $df) {
                        if ($df['op'] === 'contains') {
                            $where .= " AND ti.{$df['field']} LIKE ?";
                            $params[] = '%' . $df['value'] . '%';
                        } else {
                            $where .= " AND ti.{$df['field']} = ?";
                            $params[] = $df['value'];
                        }
                    }

                    $sql = "WITH Latest AS (
                                SELECT rf.*,
                                       ROW_NUMBER() OVER (PARTITION BY rf.TransactionID ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC) AS rn
                                FROM tblTransactionResultFlat rf
                            )
                            SELECT
                                ti.AccountCode,
                                ti.GLAccountCode,
                                ti.TransactionTypeCode,
                                ttt.TransactionTypeName,
                                COUNT(*) AS TxCount,
                                SUM(COALESCE(l.BP1, ti.BP1InpN, 0)) AS SumBP1,
                                SUM(COALESCE(l.BP2, ti.BP2InpN, 0)) AS SumBP2,
                                SUM(COALESCE(l.BP3, ti.BP3InpN, 0)) AS SumBP3,
                                SUM(COALESCE(l.BP4, ti.BP4InpN, 0)) AS SumBP4,
                                SUM(COALESCE(l.BP5, ti.BP5InpN, 0)) AS SumBP5,
                                SUM(COALESCE(l.BP6, ti.BP6InpN, 0)) AS SumBP6,
                                SUM(COALESCE(l.BP7, ti.BP7InpN, 0)) AS SumBP7,
                                SUM(COALESCE(l.BP8, ti.BP8InpN, 0)) AS SumBP8,
                                SUM(COALESCE(l.BP9, ti.BP9InpN, 0)) AS SumBP9,
                                SUM(COALESCE(l.BP10, ti.BP10InpN, 0)) AS SumBP10,
                                SUM(COALESCE(l.BP11, ti.BP11InpN, 0)) AS SumBP11,
                                SUM(COALESCE(l.BP12, ti.BP12InpN, 0)) AS SumBP12,
                                SUM(COALESCE(l.BPTotal, ti.BPTotalInpN, 0)) AS SumBPTotal
                            FROM tblTransactionInput ti
                            LEFT JOIN dbo.tblTransactionTypes ttt
                                ON ttt.TransactionTypeCode = ti.TransactionTypeCode
                            LEFT JOIN Latest l
                                ON l.TransactionID = ti.TransactionID AND l.rn = 1
                            {$where}
                            GROUP BY ti.AccountCode, ti.GLAccountCode, ti.TransactionTypeCode, ttt.TransactionTypeName
                            ORDER BY ti.TransactionTypeCode, ti.AccountCode, ti.GLAccountCode";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                    $summaryRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    try {
                        $ceilingByTransactionType = $loadCeilingByTransactionType($summaryRows, 'TransactionTypeCode');
                    } catch (\Throwable $e) {
                        $ceilingByTransactionType = [];
                    }

                } else {
                    $where = 'WHERE ti.FiscalYearID = ? AND ti.VersionID = ?
                              AND EXISTS (
                                  SELECT 1
                                  FROM dbo.tblDataObjectTree tr
                                  WHERE tr.FiscalYearID = ti.FiscalYearID
                                    AND tr.AncestorCode = ?
                                    AND tr.DescendantCode = ti.DataObjectCode
                              )';
                    $params = [$ctxFy, $ctxVer, $ctxDataObject];

                    if ($filters['q'] !== '') {
                        if (ctype_digit($filters['q'])) {
                            $where .= ' AND ti.TransactionID = ?';
                            $params[] = (int)$filters['q'];
                        } else {
                            $where .= ' AND ti.TransactionTypeCode LIKE ?';
                            $params[] = '%' . $filters['q'] . '%';
                        }
                    }
                    if ($filters['tt'] !== '') {
                        $where .= ' AND ti.TransactionTypeCode = ?';
                        $params[] = $filters['tt'];
                    }
                    if ($filters['gl_prefix'] !== '') {
                        $glLevel = (int)$filters['gl_level'];
                        if (!in_array($glLevel, [1, 2, 3], true)) {
                            $glLevel = 1;
                        }
                        if ($glLevel === 1) {
                            $where .= ' AND LEFT(ti.GLAccountCode, 2) = ?';
                        } elseif ($glLevel === 2) {
                            $where .= ' AND LEFT(ti.GLAccountCode, 4) = ?';
                        } else {
                            $where .= ' AND ti.GLAccountCode = ?';
                        }
                        $params[] = $filters['gl_prefix'];
                    }
                    if ($filters['gl_code'] !== '') {
                        $where .= ' AND ti.GLAccountCode = ?';
                        $params[] = $filters['gl_code'];
                    }
                    if ($filters['account_code'] !== '') {
                        $where .= ' AND ti.AccountCode = ?';
                        $params[] = $filters['account_code'];
                    }
                    foreach ($dynamicFilters as $df) {
                        if ($df['op'] === 'contains') {
                            $where .= " AND ti.{$df['field']} LIKE ?";
                            $params[] = '%' . $df['value'] . '%';
                        } else {
                            $where .= " AND ti.{$df['field']} = ?";
                            $params[] = $df['value'];
                        }
                    }

                    $sql = "WITH Latest AS (
                                SELECT rf.*,
                                       ROW_NUMBER() OVER (PARTITION BY rf.TransactionID ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC) AS rn
                                FROM tblTransactionResultFlat rf
                            )
                            SELECT TOP 200
                                ti.TransactionID, ti.AccountCode, ti.TransactionTypeCode, ttt.TransactionTypeName, ti.CalculationID, ti.GLAccountCode,
                                ti.{$segmentCol} AS SegmentCode,
                                ti.{$segmentCol2} AS SegmentCode2,
                                cs.CeilingDefinitionID,
                                cs.BalanceBPTotal AS CeilingBalance,
                                cs.CeilingBPTotal AS CeilingTotal,
                                COALESCE(l.BP1, ti.BP1InpN, 0) AS BP1,
                                COALESCE(l.BP2, ti.BP2InpN, 0) AS BP2,
                                COALESCE(l.BP3, ti.BP3InpN, 0) AS BP3,
                                COALESCE(l.BP4, ti.BP4InpN, 0) AS BP4,
                                COALESCE(l.BP5, ti.BP5InpN, 0) AS BP5,
                                COALESCE(l.BP6, ti.BP6InpN, 0) AS BP6,
                                COALESCE(l.BP7, ti.BP7InpN, 0) AS BP7,
                                COALESCE(l.BP8, ti.BP8InpN, 0) AS BP8,
                                COALESCE(l.BP9, ti.BP9InpN, 0) AS BP9,
                                COALESCE(l.BP10, ti.BP10InpN, 0) AS BP10,
                                COALESCE(l.BP11, ti.BP11InpN, 0) AS BP11,
                                COALESCE(l.BP12, ti.BP12InpN, 0) AS BP12,
                                COALESCE(l.BPTotal, ti.BPTotalInpN, 0) AS BPTotal
                            FROM tblTransactionInput ti
                            LEFT JOIN dbo.tblTransactionTypes ttt
                                ON ttt.TransactionTypeCode = ti.TransactionTypeCode
                            LEFT JOIN Latest l
                                ON l.TransactionID = ti.TransactionID AND l.rn = 1
                            OUTER APPLY (
                                SELECT TOP 1
                                    cb.CeilingDefinitionID,
                                    cb.BalanceBPTotal,
                                    cd.CeilingBPTotal
                                FROM dbo.tblCeilingBalance cb
                                LEFT JOIN dbo.tblCeilingDefinition cd
                                    ON cd.CeilingDefinitionID = cb.CeilingDefinitionID
                                WHERE cb.CeilingDefinitionID = ti.CeilingDefinitionID
                                   OR cb.LastTransactionID = ti.TransactionID
                                ORDER BY CASE WHEN cb.CeilingDefinitionID = ti.CeilingDefinitionID THEN 0 ELSE 1 END,
                                         cb.CeilingDefinitionID
                            ) cs
                            {$where}
                            ORDER BY ti.TransactionTypeCode, ti.TransactionID DESC";

                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                    $countSql = "SELECT COUNT(*) AS Cnt FROM tblTransactionInput ti {$where}";
                    $stmt2 = $this->db->prepare($countSql);
                    $stmt2->execute($params);
                    $total = (int)($stmt2->fetchColumn() ?: 0);
                }
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        $this->render('transactioninput/List', [
            'title' => __t('menu_transaction_input_list'),
            'ctxFy' => $ctxFy,
            'ctxVer' => $ctxVer,
            'ctxDataObject' => $ctxDataObject,
            'ctxDataObjectName' => $ctxDataObjectName,
            'ctxDataObjectPath' => $ctxDataObjectPath,
            'ctxAccountPath' => $ctxAccountPath,
            'ceilingByTransactionType' => $ceilingByTransactionType,
            'rows' => $rows,
            'summaryRows' => $summaryRows,
            'total' => $total,
            'filters' => $filters,
            'segmentCol' => $segmentCol,
            'segmentCol2' => $segmentCol2,
            'allowedFilterFields' => array_keys($allowedFilterFields),
            'warning' => $warning,
            'error' => $error,
        ]);
    }

    public function stub(): void
    {
        $url = 'process_transaction_stub.php';
        $query = [];
        if (isset($_GET['tx']) && $_GET['tx'] !== '') {
            $query['tx'] = (int)$_GET['tx'];
        }
        if (isset($_GET['invalidate_scope']) && $_GET['invalidate_scope'] === '1') {
            $query['invalidate_scope'] = '1';
        }
        if (isset($_GET['refresh_ceiling_cache']) && $_GET['refresh_ceiling_cache'] === '1') {
            $query['refresh_ceiling_cache'] = '1';
        }
        if (isset($_GET['reseed_ceiling_state']) && $_GET['reseed_ceiling_state'] === '1') {
            $query['reseed_ceiling_state'] = '1';
        }
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }
        header('Location: ' . $url);
        exit;
    }

    public function batchRunner(): void
    {
        header('Location: batch_runner.php');
        exit;
    }

    public function singleSaveLoadTest(): void
    {
        header('Location: single_save_load_test.php');
        exit;
    }
}
