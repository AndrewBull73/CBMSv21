<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;
require_once __DIR__ . '/../../shared/csrf.php';

final class CeilingBalancesController extends BaseController
{
    protected array $acl = [
        'index' => ['auth' => true, 'permsAny' => ['FIN_CONFIG_VIEW', 'FIN_CONFIG_EDIT', 'ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'indexKeys' => ['auth' => true, 'permsAny' => ['FIN_CONFIG_VIEW', 'FIN_CONFIG_EDIT', 'ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'reload' => ['auth' => true, 'permsAny' => ['FIN_CONFIG_EDIT', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
        'reloadBalances' => ['auth' => true, 'permsAny' => ['FIN_CONFIG_EDIT', 'ESTIMATES_EDIT', 'ADMIN_ALL', 'SYSADMIN']],
    ];

    protected bool $requiresContext = true;

    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->renderBalances('definitions');
    }

    public function indexKeys(): void
    {
        $this->renderBalances('keys');
    }

    private function renderBalances(string $mode): void
    {
        $limit = max(1, min(5000, (int)($_GET['limit'] ?? 500)));
        $refresh = max(0, min(120, (int)($_GET['refresh'] ?? 0)));
        $currentOnly = true;
        $txTypeFilter = trim((string)($_GET['tt'] ?? ''));

        $ctxFy = (int)SessionHelper::get('FiscalYearID', 0);
        $ctxVer = (int)SessionHelper::get('VersionID', 0);
        $ctxDataObject = (string)SessionHelper::get('scope.dataobject_code', '');
        $ctxDataObject = trim($ctxDataObject);

        $error = '';
        $rows = [];
        $summary = [
            'keys_returned' => 0,
            'total_balance' => 0.0,
            'total_ceiling' => 0.0,
            'breach_count' => 0,
        ];

        try {
            if (!class_exists('\\Predis\\Client')) {
                throw new \RuntimeException('Predis client not found. Run composer install.');
            }

            $redis = new \Predis\Client([
                'scheme' => 'tcp',
                'host' => '127.0.0.1',
                'port' => 6379,
            ]);

            // If a specific data scope is selected, only show balances for that scope subtree.
            $scopeCodes = null;
            if ($ctxDataObject !== '' && strtoupper($ctxDataObject) !== 'ALL' && $ctxFy > 0) {
                $scopeCodes = [$ctxDataObject => true];
                $stmtScope = $this->db->prepare(
                    "SELECT DescendantCode
                     FROM dbo.tblDataObjectTree
                     WHERE FiscalYearID = ?
                       AND AncestorCode = ?"
                );
                $stmtScope->execute([$ctxFy, $ctxDataObject]);
                foreach (($stmtScope->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $sr) {
                    $dc = (string)($sr['DescendantCode'] ?? '');
                    if ($dc !== '') {
                        $scopeCodes[$dc] = true;
                    }
                }
            }

            if ($mode === 'keys') {
                $pattern = 'ceiling:balance:v1:*';
                $keys = [];
                if (method_exists($redis, 'scan')) {
                    $cursor = 0;
                    do {
                        $scan = $redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 500]);
                        if (!is_array($scan) || count($scan) !== 2) {
                            break;
                        }
                        $cursor = (int)$scan[0];
                        $batch = is_array($scan[1]) ? $scan[1] : [];
                        foreach ($batch as $key) {
                            $keys[] = (string)$key;
                        }
                    } while ($cursor !== 0);
                } else {
                    $raw = $redis->keys($pattern);
                    $keys = is_array($raw) ? $raw : [];
                }
                sort($keys, SORT_NATURAL);

                $defMap = [];
                $ids = [];
                foreach ($keys as $k) {
                    $parts = explode(':', $k);
                    $id = (int)end($parts);
                    if ($id > 0) {
                        $ids[$id] = true;
                    }
                }
                if (!empty($ids)) {
                    $idList = array_keys($ids);
                    foreach (array_chunk($idList, 2000) as $chunk) {
                        if (empty($chunk)) {
                            continue;
                        }
                        $in = implode(',', array_fill(0, count($chunk), '?'));
                        $stmtDef = $this->db->prepare(
                            "SELECT CeilingDefinitionID, FiscalYearID, VersionID, DataObjectCode, TransactionTypeCode, CeilingBPTotal
                             FROM dbo.tblCeilingDefinition
                             WHERE CeilingDefinitionID IN ($in)"
                        );
                        $stmtDef->execute($chunk);
                        foreach (($stmtDef->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $d) {
                            $defMap[(int)$d['CeilingDefinitionID']] = $d;
                        }
                    }
                }

                foreach ($keys as $key) {
                    if (count($rows) >= $limit) {
                        break;
                    }
                    $parts = explode(':', $key);
                    $defId = (int)end($parts);
                    $def = $defMap[$defId] ?? null;
                    if (!$def) {
                        continue;
                    }
                    if ($currentOnly && ((int)$def['FiscalYearID'] !== $ctxFy || (int)$def['VersionID'] !== $ctxVer)) {
                        continue;
                    }
                    if ($txTypeFilter !== '' && (string)$def['TransactionTypeCode'] !== $txTypeFilter) {
                        continue;
                    }
                    if (is_array($scopeCodes)) {
                        $defCode = (string)($def['DataObjectCode'] ?? '');
                        if ($defCode === '' || !isset($scopeCodes[$defCode])) {
                            continue;
                        }
                    }

                    $vals = $redis->hmget($key, ['bal_total', 'ceil_total', 'last_tx']);
                    $bal = (float)($vals[0] ?? 0);
                    $ceil = (float)($vals[1] ?? ($def['CeilingBPTotal'] ?? 0));
                    $lastTxRaw = $vals[2] ?? null;
                    $lastTx = ($lastTxRaw === null || $lastTxRaw === '') ? null : (int)$lastTxRaw;
                    $ttl = (int)$redis->ttl($key);
                    $remaining = $ceil - $bal;
                    if ($remaining < 0) {
                        $summary['breach_count']++;
                    }
                    $summary['keys_returned']++;
                    $summary['total_balance'] += $bal;
                    $summary['total_ceiling'] += $ceil;
                    $rows[] = [
                        'ceiling_definition_id' => $defId,
                        'fiscal_year_id' => (int)$def['FiscalYearID'],
                        'version_id' => (int)$def['VersionID'],
                        'data_object_code' => (string)$def['DataObjectCode'],
                        'transaction_type_code' => (string)$def['TransactionTypeCode'],
                        'balance_total' => $bal,
                        'ceiling_total' => $ceil,
                        'remaining' => $remaining,
                        'last_tx' => $lastTx,
                        'ttl' => $ttl,
                    ];
                }
            } else {
                $where = [];
                $params = [];
                // Redis cache warmup only includes active+approved definitions;
                // keep definitions view aligned so totals are comparable.
                $where[] = 'd.ActiveFlag = 1 AND d.ApprovedFlag = 1';
                if ($currentOnly && $ctxFy > 0 && $ctxVer > 0) {
                    $where[] = 'd.FiscalYearID = ? AND d.VersionID = ?';
                    $params[] = $ctxFy;
                    $params[] = $ctxVer;
                }
                if ($txTypeFilter !== '') {
                    $where[] = 'd.TransactionTypeCode = ?';
                    $params[] = $txTypeFilter;
                }
                if ($ctxDataObject !== '' && strtoupper($ctxDataObject) !== 'ALL' && $ctxFy > 0) {
                    $where[] = "EXISTS (
                        SELECT 1
                        FROM dbo.tblDataObjectTree dt
                        WHERE dt.FiscalYearID = ?
                          AND dt.AncestorCode = ?
                          AND dt.DescendantCode = d.DataObjectCode
                    )";
                    $params[] = $ctxFy;
                    $params[] = $ctxDataObject;
                }
                $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
                $sql = "
                    SELECT TOP {$limit}
                        d.CeilingDefinitionID, d.FiscalYearID, d.VersionID, d.DataObjectCode, d.TransactionTypeCode,
                        d.CeilingBPTotal, d.Priority, d.ActiveFlag, d.ApprovedFlag,
                        b.BalanceBPTotal, b.LastTransactionID
                    FROM dbo.tblCeilingDefinition d
                    LEFT JOIN dbo.tblCeilingBalance b
                        ON b.CeilingDefinitionID = d.CeilingDefinitionID
                    {$whereSql}
                    ORDER BY d.FiscalYearID DESC, d.VersionID DESC, d.Priority ASC, d.CeilingDefinitionID DESC
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $defs = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($defs as $def) {
                    $defId = (int)$def['CeilingDefinitionID'];
                    $key = 'ceiling:balance:v1:' . $defId;
                    $bal = (float)($def['BalanceBPTotal'] ?? $def['CeilingBPTotal'] ?? 0);
                    $ceil = (float)($def['CeilingBPTotal'] ?? 0);
                    $lastTx = isset($def['LastTransactionID']) ? (int)$def['LastTransactionID'] : null;
                    // In definitions mode, DB is the source of truth (sproc updates).
                    // Keep Redis-only visibility in the separate keys mode.
                    $ttl = -1;

                    $remaining = $ceil - $bal;

                    if ($remaining < 0) {
                        $summary['breach_count']++;
                    }

                    $summary['keys_returned']++;
                    $summary['total_balance'] += $bal;
                    $summary['total_ceiling'] += $ceil;

                    $rows[] = [
                        'ceiling_definition_id' => $defId,
                        'fiscal_year_id' => (int)$def['FiscalYearID'],
                        'version_id' => (int)$def['VersionID'],
                        'data_object_code' => (string)$def['DataObjectCode'],
                        'transaction_type_code' => (string)$def['TransactionTypeCode'],
                        'balance_total' => $bal,
                        'ceiling_total' => $ceil,
                        'remaining' => $remaining,
                        'last_tx' => $lastTx,
                        'ttl' => $ttl,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $this->render('ceilings/Balances', [
            'title' => $mode === 'keys' ? 'Ceiling Balances (Keys)' : 'Ceiling Balances (Definitions)',
            'rows' => $rows,
            'summary' => $summary,
            'error' => $error,
            'limit' => $limit,
            'refresh' => $refresh,
            'currentOnly' => $currentOnly,
            'txTypeFilter' => $txTypeFilter,
            'mode' => $mode,
            'ctxFy' => $ctxFy,
            'ctxVer' => $ctxVer,
            'ctxDataObject' => $ctxDataObject,
        ]);
    }

    public function reload(): void
    {
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('csrf_failed'));
            header('Location: ' . $this->returnUrlFromQuery());
            exit;
        }

        $ctxFy = (int)SessionHelper::get('FiscalYearID', 0);
        $ctxVer = (int)SessionHelper::get('VersionID', 0);

        try {
            require_once __DIR__ . '/../../public/warmup_ceiling_cache.php';
            if (!function_exists('runCeilingCacheWarmup')) {
                throw new \RuntimeException('Warmup function not available.');
            }
            $summary = runCeilingCacheWarmup([
                'fy' => $ctxFy > 0 ? $ctxFy : null,
                'version' => $ctxVer > 0 ? $ctxVer : null,
                'verbose' => false,
                'dry_run' => false,
                'ttl' => 86400,
            ]);
            $written = (int)($summary['balance_keys_written'] ?? 0);
            $this->flashSuccess('Ceiling cache reloaded. Keys written: ' . $written);
        } catch (\Throwable $e) {
            $this->flashError('Ceiling reload failed: ' . $e->getMessage());
        }

        header('Location: ' . $this->returnUrlFromQuery());
        exit;
    }

    public function reloadBalances(): void
    {
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('csrf_failed'));
            header('Location: ' . $this->returnUrlFromQuery());
            exit;
        }

        try {
            $this->db->beginTransaction();

            $insertSql = "
                INSERT INTO dbo.tblCeilingBalance
                (
                  CeilingDefinitionID, FiscalYearID, VersionID,
                  BalanceBP1, BalanceBP2, BalanceBP3, BalanceBP4, BalanceBP5, BalanceBP6,
                  BalanceBP7, BalanceBP8, BalanceBP9, BalanceBP10, BalanceBP11, BalanceBP12,
                  BalanceBPTotal, LastTransactionID, UpdatedBy, UpdatedDate
                )
                SELECT
                  d.CeilingDefinitionID, d.FiscalYearID, d.VersionID,
                  d.CeilingBP1, d.CeilingBP2, d.CeilingBP3, d.CeilingBP4, d.CeilingBP5, d.CeilingBP6,
                  d.CeilingBP7, d.CeilingBP8, d.CeilingBP9, d.CeilingBP10, d.CeilingBP11, d.CeilingBP12,
                  d.CeilingBPTotal, NULL, 1, GETDATE()
                FROM dbo.tblCeilingDefinition d
                LEFT JOIN dbo.tblCeilingBalance b
                  ON b.CeilingDefinitionID = d.CeilingDefinitionID
                WHERE d.ActiveFlag = 1
                  AND d.ApprovedFlag = 1
                  AND b.CeilingDefinitionID IS NULL
            ";
            $ins = $this->db->prepare($insertSql);
            $ins->execute();
            $inserted = (int)$ins->rowCount();

            $updateSql = "
                UPDATE b
                SET
                  b.BalanceBP1 = d.CeilingBP1, b.BalanceBP2 = d.CeilingBP2, b.BalanceBP3 = d.CeilingBP3,
                  b.BalanceBP4 = d.CeilingBP4, b.BalanceBP5 = d.CeilingBP5, b.BalanceBP6 = d.CeilingBP6,
                  b.BalanceBP7 = d.CeilingBP7, b.BalanceBP8 = d.CeilingBP8, b.BalanceBP9 = d.CeilingBP9,
                  b.BalanceBP10 = d.CeilingBP10, b.BalanceBP11 = d.CeilingBP11, b.BalanceBP12 = d.CeilingBP12,
                  b.BalanceBPTotal = d.CeilingBPTotal,
                  b.LastTransactionID = NULL,
                  b.UpdatedDate = GETDATE()
                FROM dbo.tblCeilingBalance b
                JOIN dbo.tblCeilingDefinition d
                  ON d.CeilingDefinitionID = b.CeilingDefinitionID
                WHERE d.ActiveFlag = 1
                  AND d.ApprovedFlag = 1
            ";
            $upd = $this->db->prepare($updateSql);
            $upd->execute();
            $updated = (int)$upd->rowCount();

            $this->db->commit();

            // Keep Redis cache in sync with DB so all active definitions are available as keys.
            $keysWritten = 0;
            try {
                require_once __DIR__ . '/../../public/warmup_ceiling_cache.php';
                if (function_exists('runCeilingCacheWarmup')) {
                    $summary = runCeilingCacheWarmup([
                        'fy' => null,
                        'version' => null,
                        'verbose' => false,
                        'dry_run' => false,
                        'ttl' => 86400,
                    ]);
                    $keysWritten = (int)($summary['balance_keys_written'] ?? 0);
                }
            } catch (\Throwable $e2) {
                $this->flashError('Balances reloaded, but cache warmup failed: ' . $e2->getMessage());
            }

            $this->flashSuccess("Ceiling balances reloaded. Inserted: {$inserted}, Reset: {$updated}, Cache keys written: {$keysWritten}.");
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->flashError('Reload ceiling balances failed: ' . $e->getMessage());
        }

        header('Location: ' . $this->returnUrlFromQuery());
        exit;
    }

    private function returnUrlFromQuery(): string
    {
        $limit = max(1, min(5000, (int)($_GET['limit'] ?? $_POST['limit'] ?? 500)));
        $refresh = max(0, min(120, (int)($_GET['refresh'] ?? $_POST['refresh'] ?? 0)));
        $current = '1';
        $tt = trim((string)($_GET['tt'] ?? $_POST['tt'] ?? ''));
        $mode = trim((string)($_GET['mode'] ?? $_POST['mode'] ?? 'definitions'));
        $route = ($mode === 'keys') ? 'ceilings/balances-keys' : 'ceilings/balances';

        return 'index.php?route=' . $route
            . '&limit=' . $limit
            . '&refresh=' . $refresh
            . '&current=' . $current
            . '&mode=' . urlencode($mode)
            . ($tt !== '' ? ('&tt=' . urlencode($tt)) : '');
    }
}
