<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function runCeilingCacheWarmup($options = []) {
    global $conn;
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    @ini_set('max_execution_time', '0');

    $fy = isset($options['fy']) && (int)$options['fy'] > 0 ? (int)$options['fy'] : null;
    $version = isset($options['version']) && (int)$options['version'] > 0 ? (int)$options['version'] : null;
    $ttl = isset($options['ttl']) ? max(1, (int)$options['ttl']) : 86400;
    $dryRun = !empty($options['dry_run']);
    $verbose = !empty($options['verbose']);

    $where = [];
    $params = [];
    if ($fy !== null) {
        $where[] = 't.FiscalYearID = ?';
        $params[] = $fy;
    }
    if ($version !== null) {
        $where[] = 't.VersionID = ?';
        $params[] = $version;
    }
    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    $sql = "
;WITH DistinctTx AS (
    SELECT DISTINCT
        t.FiscalYearID,
        t.VersionID,
        ISNULL(t.DataObjectCode, N'') AS DataObjectCode,
        ISNULL(t.TransactionTypeCode, N'') AS TransactionTypeCode,
        ISNULL(t.Segment1Code, N'') AS Segment1Code,   ISNULL(t.Segment2Code, N'') AS Segment2Code,
        ISNULL(t.Segment3Code, N'') AS Segment3Code,   ISNULL(t.Segment4Code, N'') AS Segment4Code,
        ISNULL(t.Segment5Code, N'') AS Segment5Code,   ISNULL(t.Segment6Code, N'') AS Segment6Code,
        ISNULL(t.Segment7Code, N'') AS Segment7Code,   ISNULL(t.Segment8Code, N'') AS Segment8Code,
        ISNULL(t.Segment9Code, N'') AS Segment9Code,   ISNULL(t.Segment10Code, N'') AS Segment10Code,
        ISNULL(t.Segment11Code, N'') AS Segment11Code, ISNULL(t.Segment12Code, N'') AS Segment12Code,
        ISNULL(t.Segment13Code, N'') AS Segment13Code, ISNULL(t.Segment14Code, N'') AS Segment14Code,
        ISNULL(t.Segment15Code, N'') AS Segment15Code, ISNULL(t.Segment16Code, N'') AS Segment16Code,
        ISNULL(t.Segment17Code, N'') AS Segment17Code, ISNULL(t.Segment18Code, N'') AS Segment18Code,
        ISNULL(t.Segment19Code, N'') AS Segment19Code, ISNULL(t.Segment20Code, N'') AS Segment20Code
    FROM dbo.tblTransactionInput t
    {$whereSql}
)
SELECT
    tx.*,
    picked.CeilingDefinitionID,
    picked.CeilingFiscalYearID,
    picked.CeilingVersionID,
    picked.CeilingPriority,
    picked.CeilingDataObjectCode,
    picked.CeilingTransactionTypeCode,
    picked.CeilingSegment1Code, picked.CeilingSegment2Code, picked.CeilingSegment3Code,
    picked.CeilingSegment4Code, picked.CeilingSegment5Code, picked.CeilingSegment6Code,
    picked.CeilingSegment7Code, picked.CeilingSegment8Code, picked.CeilingSegment9Code,
    picked.CeilingSegment10Code, picked.CeilingSegment11Code, picked.CeilingSegment12Code,
    picked.CeilingSegment13Code, picked.CeilingSegment14Code, picked.CeilingSegment15Code,
    picked.CeilingSegment16Code, picked.CeilingSegment17Code, picked.CeilingSegment18Code,
    picked.CeilingSegment19Code, picked.CeilingSegment20Code,
    picked.CeilingBP1, picked.CeilingBP2, picked.CeilingBP3, picked.CeilingBP4, picked.CeilingBP5, picked.CeilingBP6,
    picked.CeilingBP7, picked.CeilingBP8, picked.CeilingBP9, picked.CeilingBP10, picked.CeilingBP11, picked.CeilingBP12,
    picked.CeilingBPTotal,
    picked.BalanceBP1, picked.BalanceBP2, picked.BalanceBP3, picked.BalanceBP4, picked.BalanceBP5, picked.BalanceBP6,
    picked.BalanceBP7, picked.BalanceBP8, picked.BalanceBP9, picked.BalanceBP10, picked.BalanceBP11, picked.BalanceBP12,
    picked.BalanceBPTotal,
    picked.LastTransactionID,
    picked.BalanceUpdatedDate
FROM DistinctTx tx
OUTER APPLY (
    SELECT TOP 1
        cd.CeilingDefinitionID,
        cd.FiscalYearID AS CeilingFiscalYearID,
        cd.VersionID AS CeilingVersionID,
        cd.Priority AS CeilingPriority,
        cd.DataObjectCode AS CeilingDataObjectCode,
        cd.TransactionTypeCode AS CeilingTransactionTypeCode,
        cd.Segment1Code AS CeilingSegment1Code, cd.Segment2Code AS CeilingSegment2Code, cd.Segment3Code AS CeilingSegment3Code,
        cd.Segment4Code AS CeilingSegment4Code, cd.Segment5Code AS CeilingSegment5Code, cd.Segment6Code AS CeilingSegment6Code,
        cd.Segment7Code AS CeilingSegment7Code, cd.Segment8Code AS CeilingSegment8Code, cd.Segment9Code AS CeilingSegment9Code,
        cd.Segment10Code AS CeilingSegment10Code, cd.Segment11Code AS CeilingSegment11Code, cd.Segment12Code AS CeilingSegment12Code,
        cd.Segment13Code AS CeilingSegment13Code, cd.Segment14Code AS CeilingSegment14Code, cd.Segment15Code AS CeilingSegment15Code,
        cd.Segment16Code AS CeilingSegment16Code, cd.Segment17Code AS CeilingSegment17Code, cd.Segment18Code AS CeilingSegment18Code,
        cd.Segment19Code AS CeilingSegment19Code, cd.Segment20Code AS CeilingSegment20Code,
        cd.CeilingBP1, cd.CeilingBP2, cd.CeilingBP3, cd.CeilingBP4, cd.CeilingBP5, cd.CeilingBP6,
        cd.CeilingBP7, cd.CeilingBP8, cd.CeilingBP9, cd.CeilingBP10, cd.CeilingBP11, cd.CeilingBP12,
        cd.CeilingBPTotal,
        cb.BalanceBP1, cb.BalanceBP2, cb.BalanceBP3, cb.BalanceBP4, cb.BalanceBP5, cb.BalanceBP6,
        cb.BalanceBP7, cb.BalanceBP8, cb.BalanceBP9, cb.BalanceBP10, cb.BalanceBP11, cb.BalanceBP12,
        cb.BalanceBPTotal,
        cb.LastTransactionID,
        cb.UpdatedDate AS BalanceUpdatedDate
    FROM dbo.tblCeilingDefinition cd
    LEFT JOIN dbo.tblCeilingBalance cb
        ON cb.CeilingDefinitionID = cd.CeilingDefinitionID
    WHERE cd.FiscalYearID = tx.FiscalYearID
      AND cd.VersionID = tx.VersionID
      AND cd.ActiveFlag = 1
      AND cd.ApprovedFlag = 1
      AND (cd.DataObjectCode IS NULL OR cd.DataObjectCode = N'' OR cd.DataObjectCode = tx.DataObjectCode)
      AND (cd.TransactionTypeCode IS NULL OR cd.TransactionTypeCode = N'' OR cd.TransactionTypeCode = tx.TransactionTypeCode)
      AND (cd.Segment1Code IS NULL OR cd.Segment1Code = N'' OR cd.Segment1Code = tx.Segment1Code)
      AND (cd.Segment2Code IS NULL OR cd.Segment2Code = N'' OR cd.Segment2Code = tx.Segment2Code)
      AND (cd.Segment3Code IS NULL OR cd.Segment3Code = N'' OR cd.Segment3Code = tx.Segment3Code)
      AND (cd.Segment4Code IS NULL OR cd.Segment4Code = N'' OR cd.Segment4Code = tx.Segment4Code)
      AND (cd.Segment5Code IS NULL OR cd.Segment5Code = N'' OR cd.Segment5Code = tx.Segment5Code)
      AND (cd.Segment6Code IS NULL OR cd.Segment6Code = N'' OR cd.Segment6Code = tx.Segment6Code)
      AND (cd.Segment7Code IS NULL OR cd.Segment7Code = N'' OR cd.Segment7Code = tx.Segment7Code)
      AND (cd.Segment8Code IS NULL OR cd.Segment8Code = N'' OR cd.Segment8Code = tx.Segment8Code)
      AND (cd.Segment9Code IS NULL OR cd.Segment9Code = N'' OR cd.Segment9Code = tx.Segment9Code)
      AND (cd.Segment10Code IS NULL OR cd.Segment10Code = N'' OR cd.Segment10Code = tx.Segment10Code)
      AND (cd.Segment11Code IS NULL OR cd.Segment11Code = N'' OR cd.Segment11Code = tx.Segment11Code)
      AND (cd.Segment12Code IS NULL OR cd.Segment12Code = N'' OR cd.Segment12Code = tx.Segment12Code)
      AND (cd.Segment13Code IS NULL OR cd.Segment13Code = N'' OR cd.Segment13Code = tx.Segment13Code)
      AND (cd.Segment14Code IS NULL OR cd.Segment14Code = N'' OR cd.Segment14Code = tx.Segment14Code)
      AND (cd.Segment15Code IS NULL OR cd.Segment15Code = N'' OR cd.Segment15Code = tx.Segment15Code)
      AND (cd.Segment16Code IS NULL OR cd.Segment16Code = N'' OR cd.Segment16Code = tx.Segment16Code)
      AND (cd.Segment17Code IS NULL OR cd.Segment17Code = N'' OR cd.Segment17Code = tx.Segment17Code)
      AND (cd.Segment18Code IS NULL OR cd.Segment18Code = N'' OR cd.Segment18Code = tx.Segment18Code)
      AND (cd.Segment19Code IS NULL OR cd.Segment19Code = N'' OR cd.Segment19Code = tx.Segment19Code)
      AND (cd.Segment20Code IS NULL OR cd.Segment20Code = N'' OR cd.Segment20Code = tx.Segment20Code)
    ORDER BY
      (
        CASE WHEN cd.DataObjectCode IS NULL OR cd.DataObjectCode = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.TransactionTypeCode IS NULL OR cd.TransactionTypeCode = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment1Code IS NULL OR cd.Segment1Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment2Code IS NULL OR cd.Segment2Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment3Code IS NULL OR cd.Segment3Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment4Code IS NULL OR cd.Segment4Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment5Code IS NULL OR cd.Segment5Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment6Code IS NULL OR cd.Segment6Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment7Code IS NULL OR cd.Segment7Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment8Code IS NULL OR cd.Segment8Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment9Code IS NULL OR cd.Segment9Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment10Code IS NULL OR cd.Segment10Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment11Code IS NULL OR cd.Segment11Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment12Code IS NULL OR cd.Segment12Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment13Code IS NULL OR cd.Segment13Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment14Code IS NULL OR cd.Segment14Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment15Code IS NULL OR cd.Segment15Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment16Code IS NULL OR cd.Segment16Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment17Code IS NULL OR cd.Segment17Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment18Code IS NULL OR cd.Segment18Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment19Code IS NULL OR cd.Segment19Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment20Code IS NULL OR cd.Segment20Code = N'' THEN 0 ELSE 1 END
      ) DESC,
      cd.Priority ASC,
      cd.CeilingDefinitionID ASC
) picked
ORDER BY tx.FiscalYearID, tx.VersionID, tx.DataObjectCode, tx.TransactionTypeCode;
";

    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
    ]);

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    $startedAt = microtime(true);
    $processed = 0;
    $unmatched = 0;
    $snapshotWrites = 0;
    $balanceWrites = 0;
    $balanceWarmed = [];
    $cachedAt = gmdate('c');

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $processed++;
        if (empty($row['CeilingDefinitionID'])) {
            $unmatched++;
            continue;
        }

        $parts = [
            $row['FiscalYearID'] ?? '',
            $row['VersionID'] ?? '',
            $row['DataObjectCode'] ?? '',
            $row['TransactionTypeCode'] ?? '',
        ];
        for ($s = 1; $s <= 20; $s++) {
            $parts[] = $row["Segment{$s}Code"] ?? '';
        }
        $snapshotKey = 'ceiling:snapshot:' . md5(implode('|', $parts));

        $snapshotData = [
            'CeilingDefinitionID' => (int)$row['CeilingDefinitionID'],
            'CeilingFiscalYearID' => isset($row['CeilingFiscalYearID']) ? (int)$row['CeilingFiscalYearID'] : null,
            'CeilingVersionID' => isset($row['CeilingVersionID']) ? (int)$row['CeilingVersionID'] : null,
            'CeilingPriority' => isset($row['CeilingPriority']) ? (int)$row['CeilingPriority'] : null,
            'CeilingDataObjectCode' => $row['CeilingDataObjectCode'] ?? null,
            'CeilingTransactionTypeCode' => $row['CeilingTransactionTypeCode'] ?? null,
            'CeilingBPTotal' => (float)($row['CeilingBPTotal'] ?? 0),
            'BalanceBPTotal' => isset($row['BalanceBPTotal']) ? (float)$row['BalanceBPTotal'] : null,
            'LastTransactionID' => isset($row['LastTransactionID']) ? (int)$row['LastTransactionID'] : null,
            'BalanceUpdatedDate' => $row['BalanceUpdatedDate'] ?? null,
        ];
        for ($s = 1; $s <= 20; $s++) {
            $snapshotData["CeilingSegment{$s}Code"] = $row["CeilingSegment{$s}Code"] ?? null;
        }
        for ($m = 1; $m <= 12; $m++) {
            $snapshotData["CeilingBP{$m}"] = (float)($row["CeilingBP{$m}"] ?? 0);
            $snapshotData["BalanceBP{$m}"] = isset($row["BalanceBP{$m}"]) ? (float)$row["BalanceBP{$m}"] : null;
        }

        if (!$dryRun) {
            $payload = [
                'meta' => [
                    'source' => 'warmup_ceiling_cache.php',
                    'cached_at' => $cachedAt,
                ],
                'data' => $snapshotData,
            ];
            $redis->setex($snapshotKey, $ttl, json_encode($payload));
        }
        $snapshotWrites++;

        $defId = (int)$row['CeilingDefinitionID'];
        if (!isset($balanceWarmed[$defId])) {
            $balanceKey = 'ceiling:balance:v1:' . $defId;
            $hash = [];
            for ($m = 1; $m <= 12; $m++) {
                $ceil = (float)($row["CeilingBP{$m}"] ?? 0);
                $bal = isset($row["BalanceBP{$m}"]) ? (float)$row["BalanceBP{$m}"] : $ceil;
                $hash['ceil_bp' . $m] = (string)$ceil;
                $hash['bal_bp' . $m] = (string)$bal;
            }
            $ceilTotal = (float)($row['CeilingBPTotal'] ?? 0);
            $balTotal = isset($row['BalanceBPTotal']) ? (float)$row['BalanceBPTotal'] : $ceilTotal;
            $hash['ceil_total'] = (string)$ceilTotal;
            $hash['bal_total'] = (string)$balTotal;
            $hash['last_tx'] = isset($row['LastTransactionID']) && $row['LastTransactionID'] !== null
                ? (string)$row['LastTransactionID']
                : '';

            if (!$dryRun) {
                $redis->hmset($balanceKey, $hash);
                $redis->expire($balanceKey, $ttl);
            }
            $balanceWarmed[$defId] = true;
            $balanceWrites++;
        }

        if ($verbose && $processed % 1000 === 0) {
            echo "Processed {$processed} signatures...\n";
        }
    }

    // Ensure every active+approved ceiling definition gets a Redis balance key,
    // even when no transaction signature currently matches it.
    $defWhere = ['cd.ActiveFlag = 1', 'cd.ApprovedFlag = 1'];
    $defParams = [];
    if ($fy !== null) {
        $defWhere[] = 'cd.FiscalYearID = ?';
        $defParams[] = $fy;
    }
    if ($version !== null) {
        $defWhere[] = 'cd.VersionID = ?';
        $defParams[] = $version;
    }
    $defSql = "
        SELECT
            cd.CeilingDefinitionID,
            cd.CeilingBP1, cd.CeilingBP2, cd.CeilingBP3, cd.CeilingBP4, cd.CeilingBP5, cd.CeilingBP6,
            cd.CeilingBP7, cd.CeilingBP8, cd.CeilingBP9, cd.CeilingBP10, cd.CeilingBP11, cd.CeilingBP12,
            cd.CeilingBPTotal,
            cb.BalanceBP1, cb.BalanceBP2, cb.BalanceBP3, cb.BalanceBP4, cb.BalanceBP5, cb.BalanceBP6,
            cb.BalanceBP7, cb.BalanceBP8, cb.BalanceBP9, cb.BalanceBP10, cb.BalanceBP11, cb.BalanceBP12,
            cb.BalanceBPTotal,
            cb.LastTransactionID
        FROM dbo.tblCeilingDefinition cd
        LEFT JOIN dbo.tblCeilingBalance cb
            ON cb.CeilingDefinitionID = cd.CeilingDefinitionID
        WHERE " . implode(' AND ', $defWhere) . "
        ORDER BY cd.CeilingDefinitionID ASC
    ";
    $defStmt = $conn->prepare($defSql);
    $defStmt->execute($defParams);
    while ($defRow = $defStmt->fetch(PDO::FETCH_ASSOC)) {
        $defId = (int)$defRow['CeilingDefinitionID'];
        if ($defId <= 0 || isset($balanceWarmed[$defId])) {
            continue;
        }

        $balanceKey = 'ceiling:balance:v1:' . $defId;
        $hash = [];
        for ($m = 1; $m <= 12; $m++) {
            $ceil = (float)($defRow["CeilingBP{$m}"] ?? 0);
            $bal = isset($defRow["BalanceBP{$m}"]) ? (float)$defRow["BalanceBP{$m}"] : $ceil;
            $hash['ceil_bp' . $m] = (string)$ceil;
            $hash['bal_bp' . $m] = (string)$bal;
        }
        $ceilTotal = (float)($defRow['CeilingBPTotal'] ?? 0);
        $balTotal = isset($defRow['BalanceBPTotal']) ? (float)$defRow['BalanceBPTotal'] : $ceilTotal;
        $hash['ceil_total'] = (string)$ceilTotal;
        $hash['bal_total'] = (string)$balTotal;
        $hash['last_tx'] = isset($defRow['LastTransactionID']) && $defRow['LastTransactionID'] !== null
            ? (string)$defRow['LastTransactionID']
            : '';

        if (!$dryRun) {
            $redis->hmset($balanceKey, $hash);
            $redis->expire($balanceKey, $ttl);
        }
        $balanceWarmed[$defId] = true;
        $balanceWrites++;
    }

    return [
        'ok' => true,
        'dry_run' => $dryRun,
        'fy' => $fy,
        'version' => $version,
        'ttl' => $ttl,
        'signatures_processed' => $processed,
        'signatures_unmatched' => $unmatched,
        'snapshot_keys_written' => $snapshotWrites,
        'balance_keys_written' => $balanceWrites,
        'elapsed_ms' => (int)round((microtime(true) - $startedAt) * 1000),
    ];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (php_sapi_name() !== 'cli') {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "This script must be run from CLI.\n";
        exit(1);
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    @ini_set('max_execution_time', '0');

    $args = array_slice($argv, 1);
    $opts = ['ttl' => 86400, 'verbose' => true];
    foreach ($args as $arg) {
        if (strpos($arg, '--fy=') === 0) {
            $opts['fy'] = (int)substr($arg, 5);
        } elseif (strpos($arg, '--version=') === 0) {
            $opts['version'] = (int)substr($arg, 10);
        } elseif (strpos($arg, '--ttl=') === 0) {
            $opts['ttl'] = max(1, (int)substr($arg, 6));
        } elseif ($arg === '--dry-run') {
            $opts['dry_run'] = true;
        }
    }

    try {
        $summary = runCeilingCacheWarmup($opts);
        echo "Warmup complete.\n";
        echo "Dry run: " . (!empty($summary['dry_run']) ? 'yes' : 'no') . "\n";
        echo "Filters: fy=" . ($summary['fy'] ?? 'all') . ", version=" . ($summary['version'] ?? 'all') . "\n";
        echo "TTL seconds: " . (int)$summary['ttl'] . "\n";
        echo "Signatures processed: " . (int)$summary['signatures_processed'] . "\n";
        echo "Signatures unmatched: " . (int)$summary['signatures_unmatched'] . "\n";
        echo "Snapshot keys written: " . (int)$summary['snapshot_keys_written'] . "\n";
        echo "Balance keys written: " . (int)$summary['balance_keys_written'] . "\n";
        echo "Elapsed ms: " . (int)$summary['elapsed_ms'] . "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "Warmup failed: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
