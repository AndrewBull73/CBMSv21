<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SystemSettingsModel;
use App\Shared\SessionHelper;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/../../shared/csrf.php';

final class TransactionInputController extends BaseController
{
    private const WORKBOOK_TEMPLATE_VERSION = '1.0';
    private const WORKBOOK_PROTECTION_PASSWORD = 'CBMS_CTX_LOCK';
    private const WORKBOOK_PASSWORD_SETTING_KEYS = [
        'TX_WORKBOOK_OPEN_PASSWORD',
        'BUDGET_WORKBOOK_OPEN_PASSWORD',
    ];

    protected array $acl = [
        'editor' => ['auth' => true, 'permsAny' => ['ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'list' => ['auth' => true, 'permsAny' => ['ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'download-template' => ['auth' => true, 'permsAny' => ['ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'downloadTemplate' => ['auth' => true, 'permsAny' => ['ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'upload-process' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'uploadProcess' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'stub' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'batch-runner' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'batchRunner' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        'single-save-load-test' => ['auth' => true, 'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
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
            '_csrf' => csrf_token(),
        ]);
    }

    public function downloadTemplate(): void
    {
        $ctxFy = (int) SessionHelper::get('FiscalYearID', 0);
        $ctxVer = (int) SessionHelper::get('VersionID', 0);
        $ctxDataObject = trim((string) SessionHelper::get('scope.dataobject_code', ''));

        if ($ctxFy <= 0 || $ctxVer <= 0) {
            $this->flashError('Set Fiscal Year and Version in the header before downloading the workbook.');
            header('Location: index.php?route=transaction-input/list');
            return;
        }

        $contextMeta = $this->loadWorkbookContextMeta($ctxFy, $ctxVer, $ctxDataObject);
        $exportMeta = $this->createWorkbookExportRecord($contextMeta);
        $contextMeta = array_merge($contextMeta, $exportMeta);
        $spreadsheet = $this->buildTemplateWorkbook($contextMeta);
        $openPassword = $this->resolveWorkbookOpenPassword();
        if ($openPassword === '') {
            $this->flashError('Workbook password is not configured in System Settings.');
            header('Location: index.php?route=transaction-input/list');
            return;
        }
        $filename = 'BudgetSubmissionWorkbook_'
            . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) ($contextMeta['dataObjectCode'] ?? 'Context'))
            . '_'
            . date('Ymd_His')
            . '.xlsx';

        $this->auditEvent('DOWNLOAD_TEMPLATE', 'TransactionInputWorkbook', 'context', [
            'FiscalYearID' => $ctxFy,
            'VersionID' => $ctxVer,
            'DataObjectCode' => $ctxDataObject !== '' ? $ctxDataObject : '(none)',
            'WorkbookExportID' => (int) ($contextMeta['workbookExportId'] ?? 0),
            'WorkbookTemplateVersion' => (string) ($contextMeta['workbookTemplateVersion'] ?? self::WORKBOOK_TEMPLATE_VERSION),
            'FileName' => $filename,
        ]);

        if (ob_get_length()) {
            @ob_end_clean();
        }
        $tempInput = tempnam(sys_get_temp_dir(), 'cbms_tx_wb_');
        $tempOutput = tempnam(sys_get_temp_dir(), 'cbms_tx_wb_pw_');
        if ($tempInput === false || $tempOutput === false) {
            $this->flashError('Failed to prepare temporary workbook files.');
            header('Location: index.php?route=transaction-input/list');
            return;
        }

        $tempInputXlsx = $tempInput . '.xlsx';
        $tempOutputXlsx = $tempOutput . '.xlsx';
        if (is_file($tempInput)) {
            unlink($tempInput);
        }
        if (is_file($tempOutput)) {
            unlink($tempOutput);
        }

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempInputXlsx);
            $this->protectWorkbookWithExcel($tempInputXlsx, $tempOutputXlsx, $openPassword);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            readfile($tempOutputXlsx);
            return;
        } catch (\Throwable $e) {
            $this->logHandledException('TransactionInputController::downloadTemplate password protect failed', $e, [
                'fiscalYearId' => $ctxFy,
                'versionId' => $ctxVer,
                'dataObjectCode' => $ctxDataObject,
                'workbookExportId' => (int) ($contextMeta['workbookExportId'] ?? 0),
            ]);
            $this->flashError('Workbook download failed: ' . $e->getMessage());
            header('Location: index.php?route=transaction-input/list');
            return;
        } finally {
            if (is_file($tempInputXlsx)) {
                unlink($tempInputXlsx);
            }
            if (is_file($tempOutputXlsx)) {
                unlink($tempOutputXlsx);
            }
        }
    }

    public function uploadProcess(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=transaction-input/list');
            return;
        }

        if (!isset($_FILES['uploadFile']) || (int) ($_FILES['uploadFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flashError('Select a CBMS budget workbook to upload.');
            header('Location: index.php?route=transaction-input/list');
            return;
        }

        $ctxFy = (int) SessionHelper::get('FiscalYearID', 0);
        $ctxVer = (int) SessionHelper::get('VersionID', 0);
        $ctxDataObject = trim((string) SessionHelper::get('scope.dataobject_code', ''));
        if ($ctxFy <= 0 || $ctxVer <= 0) {
            $this->flashError('Set Fiscal Year and Version in the header before uploading a workbook.');
            header('Location: index.php?route=transaction-input/list');
            return;
        }

        $openPassword = $this->resolveWorkbookOpenPassword();
        if ($openPassword === '') {
            $this->flashError('Workbook password is not configured in System Settings.');
            header('Location: index.php?route=transaction-input/list');
            return;
        }

        $tempInput = (string) ($_FILES['uploadFile']['tmp_name'] ?? '');
        $tempOutput = tempnam(sys_get_temp_dir(), 'cbms_tx_wb_upload_');
        if ($tempInput === '' || $tempOutput === false) {
            $this->flashError('Failed to prepare uploaded workbook for validation.');
            header('Location: index.php?route=transaction-input/list');
            return;
        }
        $tempOutputXlsx = $tempOutput . '.xlsx';
        if (is_file($tempOutput)) {
            unlink($tempOutput);
        }

        try {
            $this->ensureWorkbookExportTable();
            $this->decryptWorkbookWithExcel($tempInput, $tempOutputXlsx, $openPassword);
            $spreadsheet = IOFactory::load($tempOutputXlsx);
            $result = $this->validateUploadedWorkbook($spreadsheet, $ctxFy, $ctxVer, $ctxDataObject);

            if (!$result['valid']) {
                $this->markWorkbookUploadRejected($result);
                $previewErrors = array_slice($result['errors'], 0, 10);
                if (count($result['errors']) > 10) {
                    $previewErrors[] = 'Additional errors: ' . (count($result['errors']) - 10) . '.';
                }
                $this->flashError('Workbook upload validation failed. ' . implode(' | ', $previewErrors));
                header('Location: index.php?route=transaction-input/list');
                return;
            }

            $this->markWorkbookUploaded($result);
            $this->auditEvent('UPLOAD_VALIDATE', 'TransactionInputWorkbook', (string) $result['workbookExportId'], [
                'WorkbookExportID' => $result['workbookExportId'],
                'FiscalYearID' => $ctxFy,
                'VersionID' => $ctxVer,
                'DataObjectCode' => $ctxDataObject !== '' ? $ctxDataObject : '(none)',
                'AcceptedRows' => $result['acceptedRows'],
                'RejectedRows' => $result['rejectedRows'],
                'SkippedRows' => $result['skippedRows'],
                'FileName' => (string) ($_FILES['uploadFile']['name'] ?? ''),
            ]);

            $message = 'Workbook validated and registered. Accepted rows: ' . (int) $result['acceptedRows']
                . ', rejected rows: ' . (int) $result['rejectedRows']
                . ', skipped blank rows: ' . (int) $result['skippedRows'] . '.';
            $this->flashSuccess($message);
        } catch (\Throwable $e) {
            $this->logHandledException('TransactionInputController::uploadProcess failed', $e, [
                'fileName' => (string) ($_FILES['uploadFile']['name'] ?? ''),
                'fiscalYearId' => $ctxFy,
                'versionId' => $ctxVer,
                'dataObjectCode' => $ctxDataObject,
            ]);
            $this->flashError('Workbook upload failed: ' . $e->getMessage());
        } finally {
            if (is_file($tempOutputXlsx)) {
                unlink($tempOutputXlsx);
            }
        }

        header('Location: index.php?route=transaction-input/list');
    }

    private function loadWorkbookContextMeta(int $fiscalYearId, int $versionId, string $dataObjectCode): array
    {
        $meta = [
            'fiscalYearId' => $fiscalYearId,
            'fiscalYearName' => '',
            'versionId' => $versionId,
            'versionName' => '',
            'dataObjectCode' => $dataObjectCode,
            'dataObjectName' => trim((string) SessionHelper::get('scope.dataobject_name', '')),
            'preparedBy' => trim((string) SessionHelper::get('auth.username', '')),
            'preparedDate' => date('Y-m-d H:i:s'),
            'preparedByUserId' => (int) SessionHelper::get('auth.user_id', 0),
        ];

        try {
            $stmt = $this->db->prepare("
                SELECT fy.YearLabel AS FiscalYearName, v.VersionLabel
                FROM dbo.tblFiscalYears fy
                INNER JOIN dbo.tblVersions v
                    ON v.FiscalYearID = fy.FiscalYearID
                WHERE fy.FiscalYearID = ?
                  AND v.VersionID = ?
            ");
            $stmt->execute([$fiscalYearId, $versionId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $meta['fiscalYearName'] = trim((string) ($row['FiscalYearName'] ?? ''));
            $meta['versionName'] = trim((string) ($row['VersionLabel'] ?? ''));
        } catch (\Throwable $e) {
            $meta['fiscalYearName'] = '';
            $meta['versionName'] = '';
        }

        if ($meta['dataObjectName'] === '') {
            try {
                $stmt = $this->db->prepare("
                    SELECT TOP 1 DataObjectName
                    FROM dbo.tblDataObjectCodes
                    WHERE FiscalYearID = ?
                      AND DataObjectCode = ?
                ");
                $stmt->execute([$fiscalYearId, $dataObjectCode]);
                $meta['dataObjectName'] = trim((string) ($stmt->fetchColumn() ?: ''));
            } catch (\Throwable $e) {
                $meta['dataObjectName'] = '';
            }
        }

        if ($meta['dataObjectCode'] === '') {
            $meta['dataObjectCode'] = '';
            $meta['dataObjectName'] = $meta['dataObjectName'] !== '' ? $meta['dataObjectName'] : 'Not selected';
        }

        return $meta;
    }

    private function validateUploadedWorkbook(Spreadsheet $spreadsheet, int $ctxFy, int $ctxVer, string $ctxDataObject): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'workbookExportId' => 0,
            'workbookToken' => '',
            'acceptedRows' => 0,
            'rejectedRows' => 0,
            'skippedRows' => 0,
            'validationJson' => '',
        ];

        $contextSheet = $spreadsheet->getSheetByName('Context');
        if ($contextSheet === null) {
            $result['errors'][] = 'Missing required Context sheet.';
            return $this->finalizeWorkbookValidationResult($result);
        }

        $context = $this->readWorkbookContextSheet($spreadsheet);
        foreach (['FiscalYearID', 'VersionID', 'WorkbookExportID', 'WorkbookToken', 'WorkbookTemplateVersion'] as $requiredKey) {
            if (!array_key_exists($requiredKey, $context) || trim((string) $context[$requiredKey]) === '') {
                $result['errors'][] = 'Context is missing required field: ' . $requiredKey . '.';
            }
        }

        $workbookExportId = (int) ($context['WorkbookExportID'] ?? 0);
        $workbookToken = trim((string) ($context['WorkbookToken'] ?? ''));
        $workbookFy = (int) ($context['FiscalYearID'] ?? 0);
        $workbookVer = (int) ($context['VersionID'] ?? 0);
        $workbookDataObject = trim((string) ($context['DataObjectCode'] ?? ''));
        $templateVersion = trim((string) ($context['WorkbookTemplateVersion'] ?? ''));

        $result['workbookExportId'] = $workbookExportId;
        $result['workbookToken'] = $workbookToken;

        if ($workbookFy !== $ctxFy) {
            $result['errors'][] = 'Workbook FiscalYearID does not match the current context.';
        }
        if ($workbookVer !== $ctxVer) {
            $result['errors'][] = 'Workbook VersionID does not match the current context.';
        }
        if ($workbookDataObject !== $ctxDataObject) {
            $result['errors'][] = 'Workbook DataObjectCode does not match the current context.';
        }
        if ($templateVersion !== self::WORKBOOK_TEMPLATE_VERSION) {
            $result['errors'][] = 'Workbook template version is not supported.';
        }

        $exportRow = null;
        if ($workbookExportId > 0 && $workbookToken !== '') {
            $exportRow = $this->loadWorkbookExportRecord($workbookExportId, $workbookToken);
        }
        if ($exportRow === null) {
            $result['errors'][] = 'Workbook export identity was not found in CBMS.';
        } else {
            $status = strtoupper(trim((string) ($exportRow['WorkbookStatus'] ?? '')));
            if ($status !== 'GENERATED') {
                $result['errors'][] = 'Workbook status is ' . ($status !== '' ? $status : 'UNKNOWN') . '; only GENERATED workbooks can be uploaded.';
            }
            if ((int) ($exportRow['FiscalYearID'] ?? 0) !== $ctxFy || (int) ($exportRow['VersionID'] ?? 0) !== $ctxVer) {
                $result['errors'][] = 'Registered workbook context does not match the current context.';
            }
            if (trim((string) ($exportRow['DataObjectCode'] ?? '')) !== $ctxDataObject) {
                $result['errors'][] = 'Registered workbook data object does not match the current context.';
            }
        }

        $transactionsSheet = $spreadsheet->getSheetByName('Transactions');
        if ($transactionsSheet === null) {
            $result['errors'][] = 'Missing required Transactions sheet.';
            return $this->finalizeWorkbookValidationResult($result);
        }

        $rows = $transactionsSheet->toArray(null, true, true, false);
        if ($rows === []) {
            $result['errors'][] = 'Transactions sheet is empty.';
            return $this->finalizeWorkbookValidationResult($result);
        }

        $headerMap = $this->buildHeaderMap($rows[0] ?? []);
        foreach ($this->requiredTransactionWorkbookHeaders() as $requiredHeader) {
            if (!array_key_exists(strtoupper($requiredHeader), $headerMap)) {
                $result['errors'][] = 'Transactions sheet is missing required column: ' . $requiredHeader . '.';
            }
        }

        if ($result['errors'] !== []) {
            return $this->finalizeWorkbookValidationResult($result);
        }

        $valueFor = static function (array $sourceRow, string $header) use ($headerMap): string {
            $index = $headerMap[strtoupper($header)] ?? null;
            return $index === null ? '' : trim((string) ($sourceRow[$index] ?? ''));
        };

        for ($rowIndex = 1; $rowIndex < count($rows); $rowIndex++) {
            $row = $rows[$rowIndex];
            if (!array_filter($row, static fn($value): bool => trim((string) $value) !== '')) {
                $result['skippedRows']++;
                continue;
            }
            if (!$this->workbookRowHasTransactionData($row, $headerMap)) {
                $result['skippedRows']++;
                continue;
            }

            $rowErrors = [];
            $rowNumber = $rowIndex + 1;
            $rowAction = strtoupper($valueFor($row, 'RowAction'));
            $transactionId = $valueFor($row, 'TransactionID');
            $transactionTypeCode = $valueFor($row, 'TransactionTypeCode');

            if ($rowAction === '' && $transactionId === '') {
                $rowAction = 'NEW';
            }
            if (!in_array($rowAction, ['NEW', 'UPDATE'], true)) {
                $rowErrors[] = 'RowAction must be NEW or UPDATE.';
            }
            if ($rowAction === 'UPDATE' && $transactionId === '') {
                $rowErrors[] = 'UPDATE rows require TransactionID.';
            }
            if ($transactionId !== '' && !ctype_digit($transactionId)) {
                $rowErrors[] = 'TransactionID must be numeric.';
            }
            if ($transactionTypeCode === '') {
                $rowErrors[] = 'TransactionTypeCode is required.';
            } elseif (!$this->transactionTypeExists($transactionTypeCode)) {
                $rowErrors[] = 'TransactionTypeCode does not exist: ' . $transactionTypeCode . '.';
            }

            for ($bp = 1; $bp <= 12; $bp++) {
                $amount = $valueFor($row, 'BP' . $bp . 'InpN');
                if ($amount !== '' && !is_numeric(str_replace(',', '', $amount))) {
                    $rowErrors[] = 'BP' . $bp . 'InpN must be numeric.';
                }
            }

            if ($rowAction === 'UPDATE' && $transactionId !== '' && ctype_digit($transactionId)) {
                if (!$this->transactionInputHeadRowExists((int) $transactionId, $ctxFy, $ctxVer, $ctxDataObject)) {
                    $rowErrors[] = 'TransactionID is not an editable head row in the current context.';
                }
            }

            if ($rowErrors !== []) {
                $result['rejectedRows']++;
                $result['errors'][] = 'Row ' . $rowNumber . ': ' . implode(' ', $rowErrors);
            } else {
                $result['acceptedRows']++;
            }
        }

        if ($result['acceptedRows'] <= 0 && $result['rejectedRows'] <= 0) {
            $result['errors'][] = 'Transactions sheet does not contain any upload rows.';
        }

        return $this->finalizeWorkbookValidationResult($result);
    }

    private function finalizeWorkbookValidationResult(array $result): array
    {
        $result['valid'] = $result['errors'] === [];
        $result['validationJson'] = json_encode([
            'valid' => $result['valid'],
            'acceptedRows' => (int) $result['acceptedRows'],
            'rejectedRows' => (int) $result['rejectedRows'],
            'skippedRows' => (int) $result['skippedRows'],
            'errors' => array_slice($result['errors'], 0, 50),
            'validatedAtUTC' => gmdate('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_SLASHES);

        return $result;
    }

    private function readWorkbookContextSheet(Spreadsheet $spreadsheet): array
    {
        $sheet = $spreadsheet->getSheetByName('Context');
        if ($sheet === null) {
            return [];
        }

        $context = [];
        $highestRow = min(100, $sheet->getHighestRow());
        for ($row = 1; $row <= $highestRow; $row++) {
            $key = trim((string) $sheet->getCell('A' . $row)->getCalculatedValue());
            if ($key === '') {
                continue;
            }
            $context[$key] = $sheet->getCell('B' . $row)->getCalculatedValue();
        }

        return $context;
    }

    private function buildHeaderMap(array $headerRow): array
    {
        $headerMap = [];
        $seen = [];
        foreach ($headerRow as $index => $value) {
            $label = strtoupper(trim((string) $value));
            if ($label === '') {
                continue;
            }
            if (isset($seen[$label])) {
                throw new \RuntimeException('Duplicate Transactions column: ' . $label . '.');
            }
            $seen[$label] = true;
            $headerMap[$label] = $index;
        }

        return $headerMap;
    }

    private function requiredTransactionWorkbookHeaders(): array
    {
        return [
            'RowAction',
            'TransactionID',
            'TransactionTypeCode',
            'UOMCodeInpC',
            'CurrencyInpC',
            'BP1InpN',
            'BP2InpN',
            'BP3InpN',
            'BP4InpN',
            'BP5InpN',
            'BP6InpN',
            'BP7InpN',
            'BP8InpN',
            'BP9InpN',
            'BP10InpN',
            'BP11InpN',
            'BP12InpN',
        ];
    }

    private function workbookRowHasTransactionData(array $row, array $headerMap): bool
    {
        $meaningfulHeaders = [
            'TransactionID',
            'TransactionTypeCode',
            'UOMCodeInpC',
            'CurrencyInpC',
        ];
        for ($bp = 1; $bp <= 12; $bp++) {
            $meaningfulHeaders[] = 'BP' . $bp . 'InpN';
        }

        foreach ($meaningfulHeaders as $header) {
            $index = $headerMap[strtoupper($header)] ?? null;
            if ($index !== null && trim((string) ($row[$index] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function loadWorkbookExportRecord(int $workbookExportId, string $workbookToken): ?array
    {
        $stmt = $this->db->prepare("
            SELECT TOP 1 *
            FROM dbo.tblTransactionInputWorkbookExport
            WHERE WorkbookExportID = ?
              AND WorkbookToken = ?
        ");
        $stmt->execute([$workbookExportId, $workbookToken]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function transactionTypeExists(string $transactionTypeCode): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(1) FROM dbo.tblTransactionTypes WHERE TransactionTypeCode = ?");
        $stmt->execute([$transactionTypeCode]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function transactionInputHeadRowExists(int $transactionId, int $fiscalYearId, int $versionId, string $dataObjectCode): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(1)
            FROM dbo.tblTransactionInput
            WHERE TransactionID = ?
              AND FiscalYearID = ?
              AND VersionID = ?
              AND COALESCE(DataObjectCode, '') = ?
              AND (HeadRecordID IS NULL OR HeadRecordID = TransactionID)
        ");
        $stmt->execute([$transactionId, $fiscalYearId, $versionId, $dataObjectCode]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function markWorkbookUploaded(array $result): void
    {
        if ((int) ($result['workbookExportId'] ?? 0) <= 0 || trim((string) ($result['workbookToken'] ?? '')) === '') {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblTransactionInputWorkbookExport
            SET WorkbookStatus = 'UPLOADED',
                UploadedAtUTC = SYSUTCDATETIME(),
                UploadedByUserID = :uploaded_by_user_id,
                UploadedByUsername = :uploaded_by_username,
                LastValidationJson = :validation_json
            WHERE WorkbookExportID = :workbook_export_id
              AND WorkbookToken = :workbook_token
        ");
        $stmt->execute([
            ':uploaded_by_user_id' => (int) SessionHelper::get('auth.user_id', 0) > 0 ? (int) SessionHelper::get('auth.user_id', 0) : null,
            ':uploaded_by_username' => trim((string) SessionHelper::get('auth.username', '')),
            ':validation_json' => (string) ($result['validationJson'] ?? ''),
            ':workbook_export_id' => (int) $result['workbookExportId'],
            ':workbook_token' => (string) $result['workbookToken'],
        ]);
    }

    private function markWorkbookUploadRejected(array $result): void
    {
        if ((int) ($result['workbookExportId'] ?? 0) <= 0 || trim((string) ($result['workbookToken'] ?? '')) === '') {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblTransactionInputWorkbookExport
            SET WorkbookStatus = 'REJECTED',
                UploadedAtUTC = SYSUTCDATETIME(),
                UploadedByUserID = :uploaded_by_user_id,
                UploadedByUsername = :uploaded_by_username,
                LastValidationJson = :validation_json
            WHERE WorkbookExportID = :workbook_export_id
              AND WorkbookToken = :workbook_token
              AND WorkbookStatus = 'GENERATED'
        ");
        $stmt->execute([
            ':uploaded_by_user_id' => (int) SessionHelper::get('auth.user_id', 0) > 0 ? (int) SessionHelper::get('auth.user_id', 0) : null,
            ':uploaded_by_username' => trim((string) SessionHelper::get('auth.username', '')),
            ':validation_json' => (string) ($result['validationJson'] ?? ''),
            ':workbook_export_id' => (int) $result['workbookExportId'],
            ':workbook_token' => (string) $result['workbookToken'],
        ]);
    }

    private function createWorkbookExportRecord(array $meta): array
    {
        $this->ensureWorkbookExportTable();

        $token = bin2hex(random_bytes(16));
        $templateVersion = self::WORKBOOK_TEMPLATE_VERSION;
        $generatedAtUtc = gmdate('Y-m-d H:i:s');
        $exportId = 0;

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblTransactionInputWorkbookExport
            (
                WorkbookToken,
                TemplateVersion,
                FiscalYearID,
                VersionID,
                DataObjectCode,
                GeneratedByUserID,
                GeneratedByUsername,
                GeneratedAtUTC,
                WorkbookStatus
            )
            VALUES
            (
                :token,
                :template_version,
                :fy,
                :ver,
                :data_object_code,
                :generated_by_user_id,
                :generated_by_username,
                SYSUTCDATETIME(),
                :workbook_status
            )
        ");
        $stmt->execute([
            ':token' => $token,
            ':template_version' => $templateVersion,
            ':fy' => (int) ($meta['fiscalYearId'] ?? 0),
            ':ver' => (int) ($meta['versionId'] ?? 0),
            ':data_object_code' => trim((string) ($meta['dataObjectCode'] ?? '')) !== '' ? (string) $meta['dataObjectCode'] : null,
            ':generated_by_user_id' => (int) ($meta['preparedByUserId'] ?? 0) > 0 ? (int) $meta['preparedByUserId'] : null,
            ':generated_by_username' => (string) ($meta['preparedBy'] ?? ''),
            ':workbook_status' => 'GENERATED',
        ]);

        try {
            $exportId = (int) $this->db->lastInsertId();
        } catch (\Throwable $e) {
            $lookup = $this->db->prepare("
                SELECT TOP 1 WorkbookExportID
                FROM dbo.tblTransactionInputWorkbookExport
                WHERE WorkbookToken = ?
                ORDER BY WorkbookExportID DESC
            ");
            $lookup->execute([$token]);
            $exportId = (int) ($lookup->fetchColumn() ?: 0);
        }

        return [
            'workbookExportId' => $exportId,
            'workbookToken' => $token,
            'workbookTemplateVersion' => $templateVersion,
            'workbookGeneratedAtUtc' => $generatedAtUtc,
            'workbookStatus' => 'GENERATED',
        ];
    }

    private function ensureWorkbookExportTable(): void
    {
        $sql = "
IF OBJECT_ID('dbo.tblTransactionInputWorkbookExport', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTransactionInputWorkbookExport
    (
        WorkbookExportID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WorkbookToken VARCHAR(64) NOT NULL,
        TemplateVersion VARCHAR(20) NOT NULL,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        DataObjectCode NVARCHAR(50) NULL,
        GeneratedByUserID INT NULL,
        GeneratedByUsername NVARCHAR(255) NULL,
        GeneratedAtUTC DATETIME2(0) NOT NULL CONSTRAINT DF_tblTransactionInputWorkbookExport_GeneratedAtUTC DEFAULT (SYSUTCDATETIME()),
        WorkbookStatus VARCHAR(20) NOT NULL CONSTRAINT DF_tblTransactionInputWorkbookExport_WorkbookStatus DEFAULT ('GENERATED'),
        UploadedAtUTC DATETIME2(0) NULL
    );

    CREATE UNIQUE INDEX UX_tblTransactionInputWorkbookExport_WorkbookToken
        ON dbo.tblTransactionInputWorkbookExport (WorkbookToken);

    CREATE INDEX IX_tblTransactionInputWorkbookExport_Context
        ON dbo.tblTransactionInputWorkbookExport (FiscalYearID, VersionID, DataObjectCode, WorkbookStatus, GeneratedAtUTC);
END;

IF COL_LENGTH('dbo.tblTransactionInputWorkbookExport', 'UploadedByUserID') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInputWorkbookExport ADD UploadedByUserID INT NULL;
END;

IF COL_LENGTH('dbo.tblTransactionInputWorkbookExport', 'UploadedByUsername') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInputWorkbookExport ADD UploadedByUsername NVARCHAR(255) NULL;
END;

IF COL_LENGTH('dbo.tblTransactionInputWorkbookExport', 'LastValidationJson') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInputWorkbookExport ADD LastValidationJson NVARCHAR(MAX) NULL;
END;
";
        $this->db->exec($sql);
    }

    private function resolveWorkbookOpenPassword(): string
    {
        $settings = new SystemSettingsModel($this->db);
        foreach (self::WORKBOOK_PASSWORD_SETTING_KEYS as $key) {
            $value = trim((string) ($settings->get($key, '') ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function protectWorkbookWithExcel(string $inputPath, string $outputPath, string $openPassword): void
    {
        $inputPath = str_replace('/', '\\', $inputPath);
        $outputPath = str_replace('/', '\\', $outputPath);
        $escapedInput = str_replace("'", "''", $inputPath);
        $escapedOutput = str_replace("'", "''", $outputPath);
        $escapedPassword = str_replace("'", "''", $openPassword);
        $diagPath = tempnam(sys_get_temp_dir(), 'cbms_tx_wb_diag_');
        if ($diagPath === false) {
            $diagPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cbms_tx_wb_diag_' . bin2hex(random_bytes(4)) . '.log';
        }
        $diagPath = str_replace('/', '\\', $diagPath);
        $escapedDiag = str_replace("'", "''", $diagPath);
        $scriptPath = tempnam(sys_get_temp_dir(), 'cbms_tx_wb_ps_');
        if ($scriptPath === false) {
            $scriptPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cbms_tx_wb_ps_' . bin2hex(random_bytes(4));
        }
        $scriptPath .= '.ps1';

        $script = <<<PS
\$ErrorActionPreference = 'Stop'
\$inputPath = '{$escapedInput}'
\$outputPath = '{$escapedOutput}'
\$password = '{$escapedPassword}'
\$diagPath = '{$escapedDiag}'
\$excel = \$null
\$workbook = \$null
function Write-Diag([string]\$message) {
    \$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss.fff'
    \$line = \$timestamp + ' ' + \$message
    Add-Content -LiteralPath \$diagPath -Value \$line
    Write-Output \$message
}
try {
    Set-Content -LiteralPath \$diagPath -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss.fff') + ' START')
    Write-Diag 'STEP:CreateExcelApplication'
    \$excel = New-Object -ComObject Excel.Application
    \$excel.DisplayAlerts = \$false
    \$excel.Visible = \$false
    Write-Diag 'STEP:OpenWorkbook'
    \$workbook = \$excel.Workbooks.Open(\$inputPath)
    Write-Diag 'STEP:SavePasswordProtectedCopy'
    \$workbook.SaveAs(\$outputPath, 51, \$password)
    Write-Diag 'STEP:CloseWorkbook'
    \$workbook.Close(\$false)
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$workbook) | Out-Null
    \$workbook = \$null
    Write-Diag 'STEP:QuitExcel'
    \$excel.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$excel) | Out-Null
    \$excel = \$null
    Write-Diag 'STEP:Completed'
} catch {
    Write-Diag ('ERROR_MESSAGE:' + \$_.Exception.Message)
    if (\$_.InvocationInfo -and \$_.InvocationInfo.PositionMessage) {
        Write-Diag ('ERROR_POSITION:' + \$_.InvocationInfo.PositionMessage)
    }
    if (\$_.ScriptStackTrace) {
        Write-Diag ('ERROR_STACK:' + \$_.ScriptStackTrace)
    }
    exit 1
} finally {
    if (\$workbook -ne \$null) {
        try { \$workbook.Close(\$false) } catch {}
        try { [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$workbook) | Out-Null } catch {}
    }
    if (\$excel -ne \$null) {
        try { \$excel.Quit() } catch {}
        try { [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$excel) | Out-Null } catch {}
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
PS;

        file_put_contents($scriptPath, $script);

        try {
            $command = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File ' . escapeshellarg($scriptPath) . ' 2>&1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $outputText = trim(implode(PHP_EOL, $output));
            $diagText = is_file($diagPath) ? trim((string) file_get_contents($diagPath)) : '';
            $outputExists = is_file($outputPath);
            $outputSize = $outputExists ? (int) filesize($outputPath) : 0;

            if ($exitCode !== 0 || !$outputExists || $outputSize <= 0) {
                $diagnosticParts = [
                    'exitCode=' . $exitCode,
                    'inputExists=' . (is_file($inputPath) ? 'yes' : 'no'),
                    'inputSize=' . (is_file($inputPath) ? (string) filesize($inputPath) : '0'),
                    'outputExists=' . ($outputExists ? 'yes' : 'no'),
                    'outputSize=' . $outputSize,
                ];
                if ($outputText !== '') {
                    $diagnosticParts[] = 'powershellOutput=' . preg_replace('/\s+/', ' ', $outputText);
                }
                if ($diagText !== '') {
                    $diagnosticParts[] = 'diagLog=' . preg_replace('/\s+/', ' ', $diagText);
                }

                throw new \RuntimeException('Excel password protection failed. ' . implode('; ', $diagnosticParts));
            }
        } finally {
            if (is_file($diagPath)) {
                unlink($diagPath);
            }
            if (is_file($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    private function decryptWorkbookWithExcel(string $inputPath, string $outputPath, string $openPassword): void
    {
        $inputPath = str_replace('/', '\\', $inputPath);
        $outputPath = str_replace('/', '\\', $outputPath);
        $escapedInput = str_replace("'", "''", $inputPath);
        $escapedOutput = str_replace("'", "''", $outputPath);
        $escapedPassword = str_replace("'", "''", $openPassword);
        $diagPath = tempnam(sys_get_temp_dir(), 'cbms_tx_wb_upload_diag_');
        if ($diagPath === false) {
            $diagPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cbms_tx_wb_upload_diag_' . bin2hex(random_bytes(4)) . '.log';
        }
        $diagPath = str_replace('/', '\\', $diagPath);
        $escapedDiag = str_replace("'", "''", $diagPath);
        $scriptPath = tempnam(sys_get_temp_dir(), 'cbms_tx_wb_upload_ps_');
        if ($scriptPath === false) {
            $scriptPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cbms_tx_wb_upload_ps_' . bin2hex(random_bytes(4));
        }
        $scriptPath .= '.ps1';

        $script = <<<PS
\$ErrorActionPreference = 'Stop'
\$inputPath = '{$escapedInput}'
\$outputPath = '{$escapedOutput}'
\$password = '{$escapedPassword}'
\$diagPath = '{$escapedDiag}'
\$excel = \$null
\$workbook = \$null
function Write-Diag([string]\$message) {
    \$timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss.fff'
    \$line = \$timestamp + ' ' + \$message
    Add-Content -LiteralPath \$diagPath -Value \$line
    Write-Output \$message
}
try {
    Set-Content -LiteralPath \$diagPath -Value ((Get-Date -Format 'yyyy-MM-dd HH:mm:ss.fff') + ' START')
    Write-Diag 'STEP:CreateExcelApplication'
    \$excel = New-Object -ComObject Excel.Application
    \$excel.DisplayAlerts = \$false
    \$excel.Visible = \$false
    Write-Diag 'STEP:OpenEncryptedWorkbook'
    \$workbook = \$excel.Workbooks.Open(\$inputPath, 0, \$true, 5, \$password)
    Write-Diag 'STEP:SaveDecryptedCopy'
    \$workbook.SaveAs(\$outputPath, 51, '')
    Write-Diag 'STEP:CloseWorkbook'
    \$workbook.Close(\$false)
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$workbook) | Out-Null
    \$workbook = \$null
    Write-Diag 'STEP:QuitExcel'
    \$excel.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$excel) | Out-Null
    \$excel = \$null
    Write-Diag 'STEP:Completed'
} catch {
    Write-Diag ('ERROR_MESSAGE:' + \$_.Exception.Message)
    if (\$_.InvocationInfo -and \$_.InvocationInfo.PositionMessage) {
        Write-Diag ('ERROR_POSITION:' + \$_.InvocationInfo.PositionMessage)
    }
    if (\$_.ScriptStackTrace) {
        Write-Diag ('ERROR_STACK:' + \$_.ScriptStackTrace)
    }
    exit 1
} finally {
    if (\$workbook -ne \$null) {
        try { \$workbook.Close(\$false) } catch {}
        try { [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$workbook) | Out-Null } catch {}
    }
    if (\$excel -ne \$null) {
        try { \$excel.Quit() } catch {}
        try { [System.Runtime.InteropServices.Marshal]::ReleaseComObject(\$excel) | Out-Null } catch {}
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}
PS;

        file_put_contents($scriptPath, $script);

        try {
            $command = 'powershell.exe -NoProfile -NonInteractive -ExecutionPolicy Bypass -File ' . escapeshellarg($scriptPath) . ' 2>&1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $outputText = trim(implode(PHP_EOL, $output));
            $diagText = is_file($diagPath) ? trim((string) file_get_contents($diagPath)) : '';
            $outputExists = is_file($outputPath);
            $outputSize = $outputExists ? (int) filesize($outputPath) : 0;

            if ($exitCode !== 0 || !$outputExists || $outputSize <= 0) {
                $diagnosticParts = [
                    'exitCode=' . $exitCode,
                    'inputExists=' . (is_file($inputPath) ? 'yes' : 'no'),
                    'inputSize=' . (is_file($inputPath) ? (string) filesize($inputPath) : '0'),
                    'outputExists=' . ($outputExists ? 'yes' : 'no'),
                    'outputSize=' . $outputSize,
                ];
                if ($outputText !== '') {
                    $diagnosticParts[] = 'powershellOutput=' . preg_replace('/\s+/', ' ', $outputText);
                }
                if ($diagText !== '') {
                    $diagnosticParts[] = 'diagLog=' . preg_replace('/\s+/', ' ', $diagText);
                }

                throw new \RuntimeException('Excel workbook decrypt failed. ' . implode('; ', $diagnosticParts));
            }
        } finally {
            if (is_file($diagPath)) {
                unlink($diagPath);
            }
            if (is_file($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    private function buildTemplateWorkbook(array $meta): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getSecurity()->setLockStructure(true);
        $spreadsheet->getSecurity()->setWorkbookPassword(self::WORKBOOK_PROTECTION_PASSWORD);
        $contextSheet = $spreadsheet->getActiveSheet();
        $contextSheet->setTitle('Context');

        $contextSheet->setCellValue('A1', 'CBMS Budget Submission Workbook');
        $contextSheet->setCellValue('A2', 'This workbook was generated from the active CBMS context.');
        $contextSheet->setCellValue('A4', 'Context Field');
        $contextSheet->setCellValue('B4', 'Value');
        $contextSheet->setCellValue('A5', 'FiscalYearID');
        $contextSheet->setCellValue('B5', (int) ($meta['fiscalYearId'] ?? 0));
        $contextSheet->setCellValue('A6', 'FiscalYearName');
        $contextSheet->setCellValue('B6', (string) ($meta['fiscalYearName'] ?? ''));
        $contextSheet->setCellValue('A7', 'VersionID');
        $contextSheet->setCellValue('B7', (int) ($meta['versionId'] ?? 0));
        $contextSheet->setCellValue('A8', 'VersionName');
        $contextSheet->setCellValue('B8', (string) ($meta['versionName'] ?? ''));
        $contextSheet->setCellValue('A9', 'DataObjectCode');
        $contextSheet->setCellValue('B9', (string) ($meta['dataObjectCode'] ?? ''));
        $contextSheet->setCellValue('A10', 'DataObjectName');
        $contextSheet->setCellValue('B10', (string) ($meta['dataObjectName'] ?? ''));
        $contextSheet->setCellValue('A11', 'PreparedBy');
        $contextSheet->setCellValue('B11', (string) ($meta['preparedBy'] ?? ''));
        $contextSheet->setCellValue('A12', 'PreparedDate');
        $contextSheet->setCellValue('B12', (string) ($meta['preparedDate'] ?? ''));
        $contextSheet->setCellValue('A13', 'WorkbookExportID');
        $contextSheet->setCellValue('B13', (int) ($meta['workbookExportId'] ?? 0));
        $contextSheet->setCellValue('A14', 'WorkbookTemplateVersion');
        $contextSheet->setCellValue('B14', (string) ($meta['workbookTemplateVersion'] ?? self::WORKBOOK_TEMPLATE_VERSION));
        $contextSheet->setCellValue('A15', 'WorkbookToken');
        $contextSheet->setCellValue('B15', (string) ($meta['workbookToken'] ?? ''));
        $contextSheet->setCellValue('A16', 'WorkbookGeneratedAtUTC');
        $contextSheet->setCellValue('B16', (string) ($meta['workbookGeneratedAtUtc'] ?? ''));
        $contextSheet->setCellValue('A17', 'WorkbookStatus');
        $contextSheet->setCellValue('B17', (string) ($meta['workbookStatus'] ?? 'GENERATED'));
        $contextSheet->setCellValue('A19', 'Instructions');
        $contextSheet->setCellValue('A20', '1. Use this workbook only for the context shown above.');
        $contextSheet->setCellValue('A21', '2. Enter or review transactions on the Transactions sheet.');
        $contextSheet->setCellValue('A22', '3. Do not change the context fields before upload.');
        $contextSheet->setCellValue('A23', '4. CBMS upload should only accept registered workbook codes.');

        $contextSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $contextSheet->getStyle('A4:B4')->getFont()->setBold(true);
        $contextSheet->getStyle('A19')->getFont()->setBold(true);
        $contextSheet->getColumnDimension('A')->setWidth(24);
        $contextSheet->getColumnDimension('B')->setWidth(56);
        foreach (range(20, 23) as $rowNo) {
            $contextSheet->mergeCells('A' . $rowNo . ':B' . $rowNo);
        }
        $contextSheet->getStyle('B5:B17')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE9ECEF');
        $contextSheet->getStyle('B5:B17')->getFont()->getColor()->setARGB('FF495057');
        $contextSheet->getStyle('B5:B17')->getProtection()->setLocked(Protection::PROTECTION_PROTECTED);
        $contextSheet->getProtection()->setPassword(self::WORKBOOK_PROTECTION_PASSWORD);
        $contextSheet->getProtection()->setSheet(true);
        $contextSheet->getProtection()->setSort(false);
        $contextSheet->getProtection()->setInsertRows(false);
        $contextSheet->getProtection()->setFormatCells(false);

        $transactionsSheet = $spreadsheet->createSheet();
        $transactionsSheet->setTitle('Transactions');
        $headers = [
            'RowAction',
            'TransactionID',
            'TransactionTypeCode',
            'UOMCodeInpC',
            'CurrencyInpC',
            'BP1InpN',
            'BP2InpN',
            'BP3InpN',
            'BP4InpN',
            'BP5InpN',
            'BP6InpN',
            'BP7InpN',
            'BP8InpN',
            'BP9InpN',
            'BP10InpN',
            'BP11InpN',
            'BP12InpN',
            'Notes',
        ];

        $columnIndex = 1;
        foreach ($headers as $header) {
            $columnLetter = Coordinate::stringFromColumnIndex($columnIndex);
            $transactionsSheet->setCellValue($columnLetter . '1', $header);
            $transactionsSheet->getColumnDimension($columnLetter)->setWidth($header === 'Notes' ? 24 : 16);
            $columnIndex++;
        }

        $transactionsSheet->setCellValue('A2', 'NEW');
        $transactionsSheet->setCellValue('R2', 'Transaction sheet starter layout. Final column set will be expanded later.');
        $transactionsSheet->freezePane('A2');
        $transactionsSheet->getStyle('A1:R1')->getFont()->setBold(true);

        $spreadsheet->setActiveSheetIndex(0);
        return $spreadsheet;
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
