<?php
// process_transaction_stub.php - Chain processing + CHILD TRANSACTION CREATION

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'allowCli' => true,
    'auth' => true,
    'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN'],
]);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/warmup_ceiling_cache.php';

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

const CACHE_TTL_SECONDS = 3600;
const CACHE_META_TTL_SECONDS = 900;
const CEILING_CACHE_TTL_SECONDS = 86400;

// --------------------------------------------------
// Helpers
// --------------------------------------------------

function allowLongRunningExecution() {
    // This endpoint can process large transaction chains/bulk runs.
    // Disable PHP execution timeout when possible.
    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    @ini_set('max_execution_time', '0');
}

function getRedis() {
    return new Predis\Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);
}

function buildKey($context, $keyName) {
    $txSig = $context['TxCacheSig'] ?? 'nosig';
    return sprintf(
        'calc:%s:%d:%d:%s:%s:%s',
        $context['FormulaSetCode'] ?? 'BUDGET-FY26',
        $context['FiscalYearID'],
        $context['VersionID'],
        $context['DataObjectCode'],
        $keyName,
        $txSig
    );
}

function getContextScope($context) {
    return sprintf(
        '%s:%d:%d:%s',
        $context['FormulaSetCode'] ?? 'BUDGET-FY26',
        $context['FiscalYearID'],
        $context['VersionID'],
        $context['DataObjectCode']
    );
}

function getScopeVersionKey($context) {
    return 'calc:scopever:' . getContextScope($context);
}

function getOrInitScopeVersion($redis, $context) {
    $versionKey = getScopeVersionKey($context);
    $version = $redis->get($versionKey);
    if ($version === null) {
        $redis->set($versionKey, 1);
        return 1;
    }
    return (int)$version;
}

function invalidateCalcScope($redis, $context) {
    return (int)$redis->incr(getScopeVersionKey($context));
}

function buildScopedCacheKey($context, $scopeVersion, $keyName, $field) {
    $base = buildKey($context, $keyName);
    return "calculations:v{$scopeVersion}:{$base}:{$field}";
}

function runStatement($stmt, $params, &$metrics, $queryType = 'read') {
    $metrics['db_query_count']++;
    if ($queryType === 'write') {
        $metrics['db_queries_write']++;
    } else {
        $metrics['db_queries_read']++;
    }
    return $stmt->execute($params);
}

function redisGetWithMetrics($redis, $key, &$metrics) {
    $metrics['redis_get_count']++;
    $value = $redis->get($key);
    if ($value === null || $value === false) {
        $metrics['cache_miss_count']++;
    } else {
        $metrics['cache_hit_count']++;
    }
    return $value;
}

function redisSetWithMetrics($redis, $key, $value, &$metrics) {
    $metrics['redis_set_count']++;
    return $redis->set($key, $value);
}

function redisSetExWithMetrics($redis, $key, $ttl, $value, &$metrics) {
    $metrics['redis_set_count']++;
    return $redis->setex($key, $ttl, $value);
}

function redisDelWithMetrics($redis, $key, &$metrics) {
    $metrics['redis_set_count']++;
    return $redis->del([$key]);
}

function redisHGetWithMetrics($redis, $key, $field, &$metrics) {
    $metrics['redis_get_count']++;
    $value = $redis->hget($key, $field);
    if ($value === null || $value === false) {
        $metrics['cache_miss_count']++;
    } else {
        $metrics['cache_hit_count']++;
    }
    return $value;
}

function redisHmsetExWithMetrics($redis, $key, $data, $ttl, &$metrics) {
    $metrics['redis_set_count']++;
    $redis->hmset($key, $data);
    $redis->expire($key, $ttl);
    return true;
}

function redisEvalWithMetrics($redis, $script, $numKeys, $args, &$metrics) {
    $metrics['redis_get_count']++;
    $metrics['redis_set_count']++;
    return $redis->eval($script, $numKeys, ...$args);
}

function addDurationMetric(array &$metrics, string $key, float $startedAt): void {
    $elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);
    $metrics[$key] = (int)($metrics[$key] ?? 0) + $elapsedMs;
}

function incrementMetric(array &$metrics, string $key, int $amount = 1): void {
    $metrics[$key] = (int)($metrics[$key] ?? 0) + $amount;
}

function getCeilingSnapshotCacheKey($txRow) {
    $parts = [
        $txRow['FiscalYearID'] ?? '',
        $txRow['VersionID'] ?? '',
        $txRow['DataObjectCode'] ?? '',
        $txRow['TransactionTypeCode'] ?? '',
    ];
    for ($s = 1; $s <= 20; $s++) {
        $parts[] = $txRow["Segment{$s}Code"] ?? '';
    }
    return 'ceiling:snapshot:' . md5(implode('|', $parts));
}

function normalizeCeilingSnapshotRow($row) {
    if (!$row) {
        return null;
    }
    for ($m = 1; $m <= 12; $m++) {
        $ceilingCol = "CeilingBP{$m}";
        $balanceCol = "BalanceBP{$m}";
        if (!isset($row[$balanceCol]) || $row[$balanceCol] === null) {
            $row[$balanceCol] = $row[$ceilingCol] ?? 0;
        }
    }
    if (!isset($row['BalanceBPTotal']) || $row['BalanceBPTotal'] === null) {
        $row['BalanceBPTotal'] = $row['CeilingBPTotal'] ?? 0;
    }
    return $row;
}

function loadCeilingSnapshotForTx($conn, $redis, $txRow, &$metrics, $skipRedisWrites = false, $forceRefresh = false, &$snapshotMeta = null) {
    $startedAt = microtime(true);
    incrementMetric($metrics, 'ceiling_snapshot_count');
    $cacheKey = getCeilingSnapshotCacheKey($txRow);
    try {
        if (!$forceRefresh) {
            $cached = redisGetWithMetrics($redis, $cacheKey, $metrics);
            if ($cached !== null && $cached !== false) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded) && isset($decoded['data']) && is_array($decoded['data'])) {
                    $snapshotMeta = [
                        'source' => 'redis',
                        'cache_key' => $cacheKey,
                        'cached_at' => $decoded['meta']['cached_at'] ?? null,
                    ];
                    return normalizeCeilingSnapshotRow($decoded['data']);
                }
                if (is_array($decoded)) {
                    // Backward compatibility for older cached payloads.
                    $snapshotMeta = [
                        'source' => 'redis',
                        'cache_key' => $cacheKey,
                        'cached_at' => null,
                    ];
                    return normalizeCeilingSnapshotRow($decoded);
                }
            }
        }

        $sql = "
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
        WHERE cd.FiscalYearID = ?
          AND cd.VersionID = ?
          AND cd.ActiveFlag = 1
          AND cd.ApprovedFlag = 1
          AND (cd.DataObjectCode IS NULL OR cd.DataObjectCode = N'' OR cd.DataObjectCode = ?)
          AND (cd.TransactionTypeCode IS NULL OR cd.TransactionTypeCode = N'' OR cd.TransactionTypeCode = ?)
          AND (cd.Segment1Code IS NULL OR cd.Segment1Code = N'' OR cd.Segment1Code = ?)
          AND (cd.Segment2Code IS NULL OR cd.Segment2Code = N'' OR cd.Segment2Code = ?)
          AND (cd.Segment3Code IS NULL OR cd.Segment3Code = N'' OR cd.Segment3Code = ?)
          AND (cd.Segment4Code IS NULL OR cd.Segment4Code = N'' OR cd.Segment4Code = ?)
          AND (cd.Segment5Code IS NULL OR cd.Segment5Code = N'' OR cd.Segment5Code = ?)
          AND (cd.Segment6Code IS NULL OR cd.Segment6Code = N'' OR cd.Segment6Code = ?)
          AND (cd.Segment7Code IS NULL OR cd.Segment7Code = N'' OR cd.Segment7Code = ?)
          AND (cd.Segment8Code IS NULL OR cd.Segment8Code = N'' OR cd.Segment8Code = ?)
          AND (cd.Segment9Code IS NULL OR cd.Segment9Code = N'' OR cd.Segment9Code = ?)
          AND (cd.Segment10Code IS NULL OR cd.Segment10Code = N'' OR cd.Segment10Code = ?)
          AND (cd.Segment11Code IS NULL OR cd.Segment11Code = N'' OR cd.Segment11Code = ?)
          AND (cd.Segment12Code IS NULL OR cd.Segment12Code = N'' OR cd.Segment12Code = ?)
          AND (cd.Segment13Code IS NULL OR cd.Segment13Code = N'' OR cd.Segment13Code = ?)
          AND (cd.Segment14Code IS NULL OR cd.Segment14Code = N'' OR cd.Segment14Code = ?)
          AND (cd.Segment15Code IS NULL OR cd.Segment15Code = N'' OR cd.Segment15Code = ?)
          AND (cd.Segment16Code IS NULL OR cd.Segment16Code = N'' OR cd.Segment16Code = ?)
          AND (cd.Segment17Code IS NULL OR cd.Segment17Code = N'' OR cd.Segment17Code = ?)
          AND (cd.Segment18Code IS NULL OR cd.Segment18Code = N'' OR cd.Segment18Code = ?)
          AND (cd.Segment19Code IS NULL OR cd.Segment19Code = N'' OR cd.Segment19Code = ?)
          AND (cd.Segment20Code IS NULL OR cd.Segment20Code = N'' OR cd.Segment20Code = ?)
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
    ";

        $params = [
            $txRow['FiscalYearID'],
            $txRow['VersionID'],
            $txRow['DataObjectCode'] ?? null,
            $txRow['TransactionTypeCode'] ?? null,
        ];
        for ($s = 1; $s <= 20; $s++) {
            $params[] = $txRow["Segment{$s}Code"] ?? null;
        }

        $stmt = $conn->prepare($sql);
        runStatement($stmt, $params, $metrics);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $snapshot = normalizeCeilingSnapshotRow($row);
        $cachedAt = gmdate('c');

        if ($snapshot && !$skipRedisWrites) {
            $payload = [
                'meta' => [
                    'cached_at' => $cachedAt,
                ],
                'data' => $snapshot,
            ];
            redisSetExWithMetrics($redis, $cacheKey, CEILING_CACHE_TTL_SECONDS, json_encode($payload), $metrics);
        }
        $snapshotMeta = [
            'source' => 'db',
            'cache_key' => $cacheKey,
            'cached_at' => $cachedAt,
        ];
        return $snapshot;
    } finally {
        addDurationMetric($metrics, 'ceiling_snapshot_ms', $startedAt);
    }
}

function runRedisCeilingPrecheck($conn, $redis, $txRow, $currentMonthly, $currentTotal, &$metrics, $skipRedisWrites = false, $forceRefresh = false) {
    $startedAt = microtime(true);
    incrementMetric($metrics, 'ceiling_precheck_count');
    try {
        $snapshot = loadCeilingSnapshotForTx($conn, $redis, $txRow, $metrics, $skipRedisWrites, $forceRefresh);
        if (!$snapshot) {
            return;
        }

        $hasPeriodCeilings = false;
        for ($m = 1; $m <= 12; $m++) {
            if ((float)($snapshot["CeilingBP{$m}"] ?? 0) != 0.0) {
                $hasPeriodCeilings = true;
                break;
            }
        }

        $newTotal = (float)($snapshot['BalanceBPTotal'] ?? 0) - (float)$currentTotal;
        if ($newTotal < 0) {
            throw new Exception('CEILING EXCEEDED (Redis pre-check): insufficient BPTotal balance.');
        }

        if ($hasPeriodCeilings) {
            for ($m = 1; $m <= 12; $m++) {
                $period = "BP{$m}";
                $newBal = (float)($snapshot["Balance{$period}"] ?? 0) - (float)($currentMonthly[$period] ?? 0);
                if ($newBal < 0) {
                    throw new Exception("CEILING EXCEEDED (Redis pre-check): insufficient {$period} balance.");
                }
            }
        }
    } finally {
        addDurationMetric($metrics, 'ceiling_precheck_ms', $startedAt);
    }
}

function runCeilingSprocCheck($conn, $txRow, $transactionId, &$metrics, $updatedBy = 1) {
    $startedAt = microtime(true);
    incrementMetric($metrics, 'ceiling_final_sproc_count');
    $sql = "
        DECLARE @CeilingStatusCheck nvarchar(50), @ErrorMessage nvarchar(500);
        EXEC dbo.spCheckCeilingBalance
            @FiscalYearID = ?,
            @VersionID = ?,
            @TransactionID = ?,
            @UpdatedBy = ?,
            @CheckMode = ?,
            @EnforcePeriod = ?,
            @CeilingStatusCheck = @CeilingStatusCheck OUTPUT,
            @ErrorMessage = @ErrorMessage OUTPUT;
        SELECT @CeilingStatusCheck AS CeilingStatusCheck, @ErrorMessage AS ErrorMessage;
    ";
    $mode = 'TRANSACTION';
    if (!empty($txRow['_ceiling_check_mode']) && $txRow['_ceiling_check_mode'] === 'group_headrecord') {
        $mode = 'HEAD_RECORD';
    }
    $enforcePeriod = 1;
    if (array_key_exists('_ceiling_period_enforcement', $txRow)) {
        $enforcePeriod = $txRow['_ceiling_period_enforcement'] ? 1 : 0;
    }
    try {
        $stmt = $conn->prepare($sql);
        runStatement($stmt, [
            $txRow['FiscalYearID'],
            $txRow['VersionID'],
            $transactionId,
            $updatedBy,
            $mode,
            $enforcePeriod
        ], $metrics, 'write');

        $statusRow = null;
        do {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $statusRow = $row;
                break;
            }
        } while ($stmt->nextRowset());

        if (!$statusRow) {
            throw new Exception('Ceiling check failed: no status returned from spCheckCeilingBalance.');
        }

        $status = $statusRow['CeilingStatusCheck'] ?? 'ERROR';
        $errorMessage = $statusRow['ErrorMessage'] ?? '';
        if ($status !== 'CEILING OK') {
            throw new Exception('Ceiling check failed: ' . $status . ($errorMessage ? " ({$errorMessage})" : ''));
        }
        return [
            'status' => $status,
            'error' => $errorMessage,
        ];
    } finally {
        addDurationMetric($metrics, 'ceiling_final_sproc_ms', $startedAt);
    }
}

function getRedisCeilingBalanceKey($ceilingDefinitionId) {
    return 'ceiling:balance:v1:' . (int)$ceilingDefinitionId;
}

function reseedCeilingStateForTx($conn, $redis, $txRow, &$metrics, $skipRedisWrites = false) {
    $snapshotMeta = null;
    $snapshot = loadCeilingSnapshotForTx($conn, $redis, $txRow, $metrics, $skipRedisWrites, true, $snapshotMeta);
    if (!$snapshot || empty($snapshot['CeilingDefinitionID'])) {
        return null;
    }

    $ceilingDefinitionId = (int)$snapshot['CeilingDefinitionID'];

    $updateSql = "
        UPDATE cb
        SET
            cb.BalanceBP1 = cd.CeilingBP1,
            cb.BalanceBP2 = cd.CeilingBP2,
            cb.BalanceBP3 = cd.CeilingBP3,
            cb.BalanceBP4 = cd.CeilingBP4,
            cb.BalanceBP5 = cd.CeilingBP5,
            cb.BalanceBP6 = cd.CeilingBP6,
            cb.BalanceBP7 = cd.CeilingBP7,
            cb.BalanceBP8 = cd.CeilingBP8,
            cb.BalanceBP9 = cd.CeilingBP9,
            cb.BalanceBP10 = cd.CeilingBP10,
            cb.BalanceBP11 = cd.CeilingBP11,
            cb.BalanceBP12 = cd.CeilingBP12,
            cb.BalanceBPTotal = cd.CeilingBPTotal,
            cb.LastTransactionID = NULL,
            cb.UpdatedDate = GETDATE()
        FROM dbo.tblCeilingBalance cb
        INNER JOIN dbo.tblCeilingDefinition cd
            ON cd.CeilingDefinitionID = cb.CeilingDefinitionID
        WHERE cb.CeilingDefinitionID = ?
    ";
    $stmt = $conn->prepare($updateSql);
    runStatement($stmt, [$ceilingDefinitionId], $metrics, 'write');

    if ($stmt->rowCount() === 0) {
        $insertSql = "
            INSERT INTO dbo.tblCeilingBalance (
                CeilingDefinitionID, FiscalYearID, VersionID,
                BalanceBP1, BalanceBP2, BalanceBP3, BalanceBP4, BalanceBP5, BalanceBP6,
                BalanceBP7, BalanceBP8, BalanceBP9, BalanceBP10, BalanceBP11, BalanceBP12,
                BalanceBPTotal, LastTransactionID, UpdatedBy, UpdatedDate
            )
            SELECT
                cd.CeilingDefinitionID, cd.FiscalYearID, cd.VersionID,
                cd.CeilingBP1, cd.CeilingBP2, cd.CeilingBP3, cd.CeilingBP4, cd.CeilingBP5, cd.CeilingBP6,
                cd.CeilingBP7, cd.CeilingBP8, cd.CeilingBP9, cd.CeilingBP10, cd.CeilingBP11, cd.CeilingBP12,
                cd.CeilingBPTotal, NULL, ?, GETDATE()
            FROM dbo.tblCeilingDefinition cd
            WHERE cd.CeilingDefinitionID = ?
        ";
        $stmt = $conn->prepare($insertSql);
        runStatement($stmt, [(int)($txRow['UpdatedBy'] ?? $txRow['CreatedBy'] ?? 1), $ceilingDefinitionId], $metrics, 'write');
    }

    if (!$skipRedisWrites) {
        redisDelWithMetrics($redis, getRedisCeilingBalanceKey($ceilingDefinitionId), $metrics);
        redisDelWithMetrics($redis, getCeilingSnapshotCacheKey($txRow), $metrics);
    }

    return $ceilingDefinitionId;
}

function ensureRedisCeilingBalanceState($conn, $redis, $txRow, &$metrics, $skipRedisWrites = false, $forceRefresh = false) {
    $snapshotMeta = null;
    $snapshot = loadCeilingSnapshotForTx($conn, $redis, $txRow, $metrics, $skipRedisWrites, $forceRefresh, $snapshotMeta);
    if (!$snapshot || empty($snapshot['CeilingDefinitionID'])) {
        return null;
    }

    $ceilingDefinitionId = (int)$snapshot['CeilingDefinitionID'];
    $balanceKey = getRedisCeilingBalanceKey($ceilingDefinitionId);
    $existingBalTotal = redisHGetWithMetrics($redis, $balanceKey, 'bal_total', $metrics);
    if (!$forceRefresh && $existingBalTotal !== null && $existingBalTotal !== false && $existingBalTotal !== '') {
        return [
            'ceiling_definition_id' => $ceilingDefinitionId,
            'balance_key' => $balanceKey,
            'source' => 'redis',
            'cached_at' => null,
        ];
    }

    $seed = [];
    for ($m = 1; $m <= 12; $m++) {
        $seed['ceil_bp' . $m] = (string)((float)($snapshot['CeilingBP' . $m] ?? 0));
        $seed['bal_bp' . $m] = (string)((float)($snapshot['BalanceBP' . $m] ?? ($snapshot['CeilingBP' . $m] ?? 0)));
    }
    $seed['ceil_total'] = (string)((float)($snapshot['CeilingBPTotal'] ?? 0));
    $seed['bal_total'] = (string)((float)($snapshot['BalanceBPTotal'] ?? ($snapshot['CeilingBPTotal'] ?? 0)));
    $seed['last_tx'] = isset($snapshot['LastTransactionID']) && $snapshot['LastTransactionID'] !== null
        ? (string)$snapshot['LastTransactionID']
        : '';
    $seed['updated_at'] = gmdate('c');

    if (!$skipRedisWrites) {
        redisHmsetExWithMetrics($redis, $balanceKey, $seed, CEILING_CACHE_TTL_SECONDS, $metrics);
    }

    return [
        'ceiling_definition_id' => $ceilingDefinitionId,
        'balance_key' => $balanceKey,
        'source' => $snapshotMeta['source'] ?? 'db',
        'cached_at' => $seed['updated_at'],
    ];
}

function runRedisCeilingCommitCheck($conn, $redis, $txRow, $transactionId, $amountMonthly, $amountTotal, $enforcePeriod, &$metrics, $skipRedisWrites = false, $forceRefresh = false) {
    $startedAt = microtime(true);
    incrementMetric($metrics, 'ceiling_final_redis_count');
    try {
        $state = ensureRedisCeilingBalanceState($conn, $redis, $txRow, $metrics, $skipRedisWrites, $forceRefresh);
        if (!$state) {
            throw new Exception('Ceiling check failed: CEILING NOT FOUND (Redis balance state missing).');
        }

        $lua = <<<'LUA'
local key = KEYS[1]
local txid = tostring(ARGV[1])
local enforce = tonumber(ARGV[2]) or 1
local nowTs = tostring(ARGV[3])
local balTotal = tonumber(redis.call('HGET', key, 'bal_total') or '0')
local amtTotal = tonumber(ARGV[16]) or 0
local newTotal = balTotal - amtTotal
if newTotal < 0 then
  return {'CEILING EXCEEDED', 'Insufficient ceiling balance (total).', '0', tostring(balTotal)}
end
local hasPeriodCeilings = 0
for i = 1, 12 do
  local ceilVal = tonumber(redis.call('HGET', key, 'ceil_bp' .. i) or '0')
  if ceilVal ~= 0 then
    hasPeriodCeilings = 1
    break
  end
end
local updates = {}
for i = 1, 12 do
  local bal = tonumber(redis.call('HGET', key, 'bal_bp' .. i) or '0')
  local amt = tonumber(ARGV[i + 3]) or 0
  local newBal = bal - amt
  if enforce == 1 and hasPeriodCeilings == 1 and newBal < 0 then
    return {'CEILING EXCEEDED', 'Insufficient ceiling balance (BP' .. i .. ').', '0', tostring(balTotal)}
  end
  table.insert(updates, 'bal_bp' .. i)
  table.insert(updates, tostring(newBal))
end
table.insert(updates, 'bal_total')
table.insert(updates, tostring(newTotal))
table.insert(updates, 'last_tx')
table.insert(updates, txid)
table.insert(updates, 'updated_at')
table.insert(updates, nowTs)
redis.call('HMSET', key, unpack(updates))
return {'CEILING OK', 'Transaction committed (redis).', '0', tostring(newTotal)}
LUA;

        $argv = [
            $state['balance_key'],
            (string)$transactionId,
            $enforcePeriod ? '1' : '0',
            gmdate('c'),
        ];
        for ($m = 1; $m <= 12; $m++) {
            $argv[] = (string)((float)($amountMonthly['BP' . $m] ?? 0));
        }
        $argv[] = (string)((float)$amountTotal);

        $result = redisEvalWithMetrics($redis, $lua, 1, $argv, $metrics);
        if (!is_array($result) || count($result) < 2) {
            throw new Exception('Ceiling check failed: invalid Redis response.');
        }

        $status = $result[0] ?? 'ERROR';
        $message = $result[1] ?? '';
        $alreadyProcessed = (($result[2] ?? '0') === '1');
        $newBalanceTotal = isset($result[3]) ? (float)$result[3] : null;

        if ($status !== 'CEILING OK') {
            throw new Exception('Ceiling check failed: ' . $status . ($message ? " ({$message})" : ''));
        }

        return [
            'status' => $status,
            'error' => $message,
            'already_processed' => $alreadyProcessed,
            'balance_after_total' => $newBalanceTotal,
            'engine' => 'redis',
            'ceiling_definition_id' => $state['ceiling_definition_id'],
        ];
    } finally {
        addDurationMetric($metrics, 'ceiling_final_redis_ms', $startedAt);
    }
}

function syncCeilingBalanceRowFromRedis($conn, $redis, $ceilingDefinitionId, &$metrics, $updatedBy = 1) {
    $startedAt = microtime(true);
    incrementMetric($metrics, 'ceiling_sync_back_count');
    $defId = (int)$ceilingDefinitionId;
    try {
        if ($defId <= 0) {
            return;
        }

        $key = getRedisCeilingBalanceKey($defId);
        $metrics['redis_get_count']++;
        $hash = $redis->hgetall($key);
        if (!is_array($hash) || empty($hash)) {
            return;
        }

        $vals = [];
        for ($m = 1; $m <= 12; $m++) {
            $vals["BalanceBP{$m}"] = isset($hash['bal_bp' . $m]) ? (float)$hash['bal_bp' . $m] : 0.0;
        }
        $vals['BalanceBPTotal'] = isset($hash['bal_total']) ? (float)$hash['bal_total'] : 0.0;
        $lastTx = null;
        if (array_key_exists('last_tx', $hash) && $hash['last_tx'] !== '') {
            $lastTx = (int)$hash['last_tx'];
        }

        $sql = "
        UPDATE b
        SET
            b.BalanceBP1 = ?, b.BalanceBP2 = ?, b.BalanceBP3 = ?, b.BalanceBP4 = ?, b.BalanceBP5 = ?, b.BalanceBP6 = ?,
            b.BalanceBP7 = ?, b.BalanceBP8 = ?, b.BalanceBP9 = ?, b.BalanceBP10 = ?, b.BalanceBP11 = ?, b.BalanceBP12 = ?,
            b.BalanceBPTotal = ?, b.LastTransactionID = ?, b.UpdatedBy = ?, b.UpdatedDate = GETDATE()
        FROM dbo.tblCeilingBalance b
        WHERE b.CeilingDefinitionID = ?
    ";
        $stmt = $conn->prepare($sql);
        runStatement($stmt, [
            $vals['BalanceBP1'], $vals['BalanceBP2'], $vals['BalanceBP3'], $vals['BalanceBP4'], $vals['BalanceBP5'], $vals['BalanceBP6'],
            $vals['BalanceBP7'], $vals['BalanceBP8'], $vals['BalanceBP9'], $vals['BalanceBP10'], $vals['BalanceBP11'], $vals['BalanceBP12'],
            $vals['BalanceBPTotal'], $lastTx, (int)$updatedBy, $defId
        ], $metrics, 'write');
    } finally {
        addDurationMetric($metrics, 'ceiling_sync_back_ms', $startedAt);
    }
}

function addGroupAmounts(&$groupMonthly, &$groupTotal, $currentMonthly, $currentTotal) {
    for ($m = 1; $m <= 12; $m++) {
        $period = 'BP' . $m;
        $groupMonthly[$period] = (float)($groupMonthly[$period] ?? 0) + (float)($currentMonthly[$period] ?? 0);
    }
    $groupTotal += (float)$currentTotal;
}

function getCeilingAmountSnapshot($conn, $transactionId, $checkMode, &$metrics) {
    if ($checkMode === 'group_headrecord') {
        $sql = "
            WITH Anchor AS (
                SELECT COALESCE(HeadRecordID, TransactionID) AS AnchorHeadRecordID
                FROM tblTransactionInput
                WHERE TransactionID = ?
            ),
            LatestByTx AS (
                SELECT
                    rf.TransactionID,
                    rf.BP1, rf.BP2, rf.BP3, rf.BP4, rf.BP5, rf.BP6,
                    rf.BP7, rf.BP8, rf.BP9, rf.BP10, rf.BP11, rf.BP12,
                    rf.BPTotal,
                    ROW_NUMBER() OVER (
                        PARTITION BY rf.TransactionID
                        ORDER BY rf.CalculatedDate DESC, rf.TransactionResultFlatID DESC
                    ) AS rn
                FROM tblTransactionResultFlat rf
                INNER JOIN tblTransactionInput ti2
                    ON ti2.TransactionID = rf.TransactionID
                CROSS JOIN Anchor a
                WHERE COALESCE(ti2.HeadRecordID, ti2.TransactionID) = a.AnchorHeadRecordID
            )
            SELECT
                COALESCE(SUM(COALESCE(BP1,0)),0) AS AmtBP1,
                COALESCE(SUM(COALESCE(BP2,0)),0) AS AmtBP2,
                COALESCE(SUM(COALESCE(BP3,0)),0) AS AmtBP3,
                COALESCE(SUM(COALESCE(BP4,0)),0) AS AmtBP4,
                COALESCE(SUM(COALESCE(BP5,0)),0) AS AmtBP5,
                COALESCE(SUM(COALESCE(BP6,0)),0) AS AmtBP6,
                COALESCE(SUM(COALESCE(BP7,0)),0) AS AmtBP7,
                COALESCE(SUM(COALESCE(BP8,0)),0) AS AmtBP8,
                COALESCE(SUM(COALESCE(BP9,0)),0) AS AmtBP9,
                COALESCE(SUM(COALESCE(BP10,0)),0) AS AmtBP10,
                COALESCE(SUM(COALESCE(BP11,0)),0) AS AmtBP11,
                COALESCE(SUM(COALESCE(BP12,0)),0) AS AmtBP12,
                COALESCE(SUM(COALESCE(BPTotal,0)),0) AS AmtBPTotal
            FROM LatestByTx
            WHERE rn = 1
        ";
        $stmt = $conn->prepare($sql);
        runStatement($stmt, [$transactionId], $metrics);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $stmt = $conn->prepare("
        SELECT TOP 1
            BP1 AS AmtBP1, BP2 AS AmtBP2, BP3 AS AmtBP3, BP4 AS AmtBP4, BP5 AS AmtBP5, BP6 AS AmtBP6,
            BP7 AS AmtBP7, BP8 AS AmtBP8, BP9 AS AmtBP9, BP10 AS AmtBP10, BP11 AS AmtBP11, BP12 AS AmtBP12,
            BPTotal AS AmtBPTotal
        FROM tblTransactionResultFlat
        WHERE TransactionID = ?
        ORDER BY CalculatedDate DESC, TransactionResultFlatID DESC
    ");
    runStatement($stmt, [$transactionId], $metrics);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return $row;
    }

    // Fallback for first-run preview when no result-flat row exists yet.
    $stmt = $conn->prepare("
        SELECT
            COALESCE(BP1InpN, 0) AS AmtBP1,
            COALESCE(BP2InpN, 0) AS AmtBP2,
            COALESCE(BP3InpN, 0) AS AmtBP3,
            COALESCE(BP4InpN, 0) AS AmtBP4,
            COALESCE(BP5InpN, 0) AS AmtBP5,
            COALESCE(BP6InpN, 0) AS AmtBP6,
            COALESCE(BP7InpN, 0) AS AmtBP7,
            COALESCE(BP8InpN, 0) AS AmtBP8,
            COALESCE(BP9InpN, 0) AS AmtBP9,
            COALESCE(BP10InpN, 0) AS AmtBP10,
            COALESCE(BP11InpN, 0) AS AmtBP11,
            COALESCE(BP12InpN, 0) AS AmtBP12,
            COALESCE(
                BPTotalInpN,
                COALESCE(BP1InpN, 0) + COALESCE(BP2InpN, 0) + COALESCE(BP3InpN, 0) + COALESCE(BP4InpN, 0) +
                COALESCE(BP5InpN, 0) + COALESCE(BP6InpN, 0) + COALESCE(BP7InpN, 0) + COALESCE(BP8InpN, 0) +
                COALESCE(BP9InpN, 0) + COALESCE(BP10InpN, 0) + COALESCE(BP11InpN, 0) + COALESCE(BP12InpN, 0)
            ) AS AmtBPTotal
        FROM tblTransactionInput
        WHERE TransactionID = ?
    ");
    runStatement($stmt, [$transactionId], $metrics);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function getCeilingCheckPreview($conn, $redis, $transactionId, $checkMode, &$metrics, $skipRedisWrites = false, $forceRefresh = false, $overrideAmounts = null, $stage = null) {
    $stmt = $conn->prepare("SELECT * FROM tblTransactionInput WHERE TransactionID = ?");
    runStatement($stmt, [$transactionId], $metrics);
    $tx = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$tx) {
        return [
            'ok' => false,
            'reason' => 'TRANSACTION_NOT_FOUND',
            'transaction_id' => (int)$transactionId,
            'check_mode' => $checkMode,
            'stage' => $stage,
        ];
    }

    $amounts = $overrideAmounts;
    if (!is_array($amounts)) {
        $amounts = getCeilingAmountSnapshot($conn, $transactionId, $checkMode, $metrics);
    }
    if (!$amounts) {
        $txSegments = [];
        for ($s = 1; $s <= 20; $s++) {
            $txSegments["Segment{$s}Code"] = $tx["Segment{$s}Code"] ?? null;
        }
        return [
            'ok' => false,
            'reason' => 'AMOUNT_SNAPSHOT_NOT_FOUND',
            'transaction_id' => (int)$transactionId,
            'tx_row_transaction_id' => isset($tx['TransactionID']) ? (int)$tx['TransactionID'] : null,
            'tx_head_record_id' => isset($tx['HeadRecordID']) ? (int)$tx['HeadRecordID'] : null,
            'check_mode' => $checkMode,
            'stage' => $stage,
            'tx_data_object_code' => $tx['DataObjectCode'] ?? null,
            'tx_transaction_type_code' => $tx['TransactionTypeCode'] ?? null,
            'tx_segments' => $txSegments,
        ];
    }

    $snapshotMeta = null;
    $ceiling = loadCeilingSnapshotForTx($conn, $redis, $tx, $metrics, $skipRedisWrites, $forceRefresh, $snapshotMeta);
    if (!$ceiling) {
        $txSegments = [];
        for ($s = 1; $s <= 20; $s++) {
            $txSegments["Segment{$s}Code"] = $tx["Segment{$s}Code"] ?? null;
        }
        return [
            'ok' => false,
            'reason' => 'CEILING_DEFINITION_NOT_FOUND',
            'transaction_id' => (int)$transactionId,
            'tx_row_transaction_id' => isset($tx['TransactionID']) ? (int)$tx['TransactionID'] : null,
            'tx_head_record_id' => isset($tx['HeadRecordID']) ? (int)$tx['HeadRecordID'] : null,
            'check_mode' => $checkMode,
            'stage' => $stage,
            'tx_data_object_code' => $tx['DataObjectCode'] ?? null,
            'tx_transaction_type_code' => $tx['TransactionTypeCode'] ?? null,
            'tx_segments' => $txSegments,
        ];
    }

    $preview = [
        'ok' => true,
        'transaction_id' => (int)$transactionId,
        'tx_row_transaction_id' => isset($tx['TransactionID']) ? (int)$tx['TransactionID'] : null,
        'tx_head_record_id' => isset($tx['HeadRecordID']) ? (int)$tx['HeadRecordID'] : null,
        'check_mode' => $checkMode,
        'tx_data_object_code' => $tx['DataObjectCode'] ?? null,
        'tx_transaction_type_code' => $tx['TransactionTypeCode'] ?? null,
        'ceiling_definition_id' => (int)$ceiling['CeilingDefinitionID'],
        'ceiling_fiscal_year_id' => isset($ceiling['CeilingFiscalYearID']) ? (int)$ceiling['CeilingFiscalYearID'] : null,
        'ceiling_version_id' => isset($ceiling['CeilingVersionID']) ? (int)$ceiling['CeilingVersionID'] : null,
        'ceiling_priority' => isset($ceiling['CeilingPriority']) ? (int)$ceiling['CeilingPriority'] : null,
        'ceiling_data_object_code' => $ceiling['CeilingDataObjectCode'] ?? null,
        'ceiling_transaction_type_code' => $ceiling['CeilingTransactionTypeCode'] ?? null,
        'ceiling_total' => (float)($ceiling['CeilingBPTotal'] ?? 0),
        'ceiling_balance' => (float)($ceiling['BalanceBPTotal'] ?? $ceiling['CeilingBPTotal'] ?? 0),
        'amount_total' => (float)($amounts['AmtBPTotal'] ?? 0),
        'balance_total_before' => (float)($ceiling['BalanceBPTotal'] ?? $ceiling['CeilingBPTotal'] ?? 0),
        'source' => $snapshotMeta['source'] ?? 'db',
        'cached_at' => $snapshotMeta['cached_at'] ?? null,
        'last_transaction_id' => isset($ceiling['LastTransactionID']) ? (int)$ceiling['LastTransactionID'] : null,
        'balance_updated_date' => $ceiling['BalanceUpdatedDate'] ?? null,
        'stage' => $stage,
    ];
    $definitionSegments = [];
    for ($s = 1; $s <= 20; $s++) {
        $definitionSegments["Segment{$s}Code"] = $ceiling["CeilingSegment{$s}Code"] ?? null;
    }
    $preview['ceiling_definition_segments'] = $definitionSegments;
    $definitionPeriods = [];
    for ($m = 1; $m <= 12; $m++) {
        $definitionPeriods["BP{$m}"] = (float)($ceiling["CeilingBP{$m}"] ?? 0);
    }
    $preview['ceiling_definition_periods'] = $definitionPeriods;
    $txSegments = [];
    for ($s = 1; $s <= 20; $s++) {
        $txSegments["Segment{$s}Code"] = $tx["Segment{$s}Code"] ?? null;
    }
    $preview['tx_segments'] = $txSegments;
    $preview['balance_total_after'] = $preview['balance_total_before'] - $preview['amount_total'];
    $preview['already_processed'] = (
        $preview['last_transaction_id'] !== null &&
        $preview['last_transaction_id'] === $preview['transaction_id']
    );

    $redisBalanceKey = getRedisCeilingBalanceKey($preview['ceiling_definition_id']);
    $redisBalTotal = redisHGetWithMetrics($redis, $redisBalanceKey, 'bal_total', $metrics);
    if ($redisBalTotal !== null && $redisBalTotal !== false && $redisBalTotal !== '') {
        $redisLastTx = redisHGetWithMetrics($redis, $redisBalanceKey, 'last_tx', $metrics);
        $redisUpdatedAt = redisHGetWithMetrics($redis, $redisBalanceKey, 'updated_at', $metrics);
        $preview['ceiling_balance'] = (float)$redisBalTotal;
        $preview['balance_total_before'] = (float)$redisBalTotal;
        $preview['balance_total_after'] = $preview['balance_total_before'] - $preview['amount_total'];
        $preview['last_transaction_id'] = ($redisLastTx === null || $redisLastTx === false || $redisLastTx === '') ? null : (int)$redisLastTx;
        $preview['balance_updated_date'] = ($redisUpdatedAt === null || $redisUpdatedAt === false || $redisUpdatedAt === '') ? $preview['balance_updated_date'] : $redisUpdatedAt;
        $preview['already_processed'] = (
            $preview['last_transaction_id'] !== null &&
            $preview['last_transaction_id'] === $preview['transaction_id']
        );
        $preview['source'] = 'redis_balance';
    }

    return $preview;
}

function buildCeilingDefinitionScopeText($preview) {
    $parts = [];
    if (!empty($preview['ceiling_data_object_code'])) {
        $parts[] = 'DataObject=' . $preview['ceiling_data_object_code'];
    }
    if (!empty($preview['ceiling_transaction_type_code'])) {
        $parts[] = 'TransactionType=' . $preview['ceiling_transaction_type_code'];
    }
    $segments = $preview['ceiling_definition_segments'] ?? [];
    foreach ($segments as $segmentName => $segmentValue) {
        if ($segmentValue !== null && $segmentValue !== '') {
            $parts[] = $segmentName . '=' . $segmentValue;
        }
    }
    if (empty($parts)) {
        return 'GLOBAL (no specific dimension filters)';
    }
    return implode(', ', $parts);
}

function buildCeilingDefinitionPeriodsText($preview) {
    $periods = $preview['ceiling_definition_periods'] ?? [];
    $parts = [];
    foreach ($periods as $periodCode => $periodValue) {
        if ((float)$periodValue !== 0.0) {
            $parts[] = $periodCode . '=' . number_format((float)$periodValue, 2, '.', '');
        }
    }
    if (empty($parts)) {
        return 'none (total-only ceiling)';
    }
    return implode(', ', $parts);
}

function buildCeilingDefinitionMatchCriteriaText($preview) {
    $parts = [];
    $parts[] = 'DataObject=' . (($preview['ceiling_data_object_code'] ?? null) === null || $preview['ceiling_data_object_code'] === '' ? 'ANY' : $preview['ceiling_data_object_code']);
    $parts[] = 'TransactionType=' . (($preview['ceiling_transaction_type_code'] ?? null) === null || $preview['ceiling_transaction_type_code'] === '' ? 'ANY' : $preview['ceiling_transaction_type_code']);
    $segments = $preview['ceiling_definition_segments'] ?? [];
    for ($s = 1; $s <= 20; $s++) {
        $name = "Segment{$s}Code";
        $value = $segments[$name] ?? null;
        $parts[] = $name . '=' . (($value === null || $value === '') ? 'ANY' : $value);
    }
    return implode(', ', $parts);
}

function buildTransactionContextMatchText($preview) {
    $parts = [];
    $parts[] = 'RequestedTxID=' . (($preview['transaction_id'] ?? null) === null ? '-' : (string)$preview['transaction_id']);
    $parts[] = 'LoadedTxID=' . (($preview['tx_row_transaction_id'] ?? null) === null ? '-' : (string)$preview['tx_row_transaction_id']);
    $parts[] = 'HeadRecordID=' . (($preview['tx_head_record_id'] ?? null) === null ? '-' : (string)$preview['tx_head_record_id']);
    $parts[] = 'DataObject=' . (($preview['tx_data_object_code'] ?? null) === null || $preview['tx_data_object_code'] === '' ? '-' : $preview['tx_data_object_code']);
    $parts[] = 'TransactionType=' . (($preview['tx_transaction_type_code'] ?? null) === null || $preview['tx_transaction_type_code'] === '' ? '-' : $preview['tx_transaction_type_code']);
    $segments = $preview['tx_segments'] ?? [];
    for ($s = 1; $s <= 20; $s++) {
        $name = "Segment{$s}Code";
        $value = $segments[$name] ?? null;
        $parts[] = $name . '=' . (($value === null || $value === '') ? '-' : $value);
    }
    return implode(', ', $parts);
}

function printCeilingCheckPreview($preview, $enforcementMode, $isBrowser) {
    if (!$preview) {
        echo $isBrowser ? '<p class="error">Ceiling preview unavailable.</p>' : "Ceiling preview unavailable.\n";
        return;
    }
    if (isset($preview['ok']) && $preview['ok'] === false) {
        $text = sprintf(
            "Ceiling Preview Unavailable | stage=%s | mode=%s | reason=%s | tx=%d",
            ($preview['stage'] ?? 'n/a'),
            ($preview['check_mode'] ?? 'n/a'),
            ($preview['reason'] ?? 'UNKNOWN'),
            (int)($preview['transaction_id'] ?? 0)
        );
        echo $isBrowser ? '<p class="error"><strong>' . htmlspecialchars($text) . '</strong></p>' : $text . "\n";
        if (isset($preview['tx_segments'])) {
            $txContextText = "Transaction Context | values=" . buildTransactionContextMatchText($preview);
            echo $isBrowser ? '<p><strong>' . htmlspecialchars($txContextText) . '</strong></p>' : $txContextText . "\n";
        }
        return;
    }
    $text = sprintf(
        "Ceiling Preview | stage=%s | mode=%s | enforcement=%s | source=%s | cached_at=%s | tx=%d | definition=%d | ceiling_total=%.2f | ceiling_balance=%.2f | amount_total=%.2f | balance_before=%.2f | balance_after=%.2f | last_tx=%s | balance_updated=%s | already_processed=%s",
        ($preview['stage'] ?? 'n/a'),
        $preview['check_mode'],
        $enforcementMode,
        $preview['source'] ?? 'db',
        $preview['cached_at'] ?? '-',
        $preview['transaction_id'],
        $preview['ceiling_definition_id'],
        $preview['ceiling_total'],
        $preview['ceiling_balance'],
        $preview['amount_total'],
        $preview['balance_total_before'],
        $preview['balance_total_after'],
        ($preview['last_transaction_id'] === null ? '-' : (string)$preview['last_transaction_id']),
        ($preview['balance_updated_date'] ?? '-'),
        (!empty($preview['already_processed']) ? 'yes' : 'no')
    );
    echo $isBrowser ? '<p><strong>' . htmlspecialchars($text) . '</strong></p>' : $text . "\n";

    $definitionText = sprintf(
        "Ceiling Definition | id=%d | fiscal_year=%s | version=%s | priority=%s | scope=%s | period_ceilings=%s",
        $preview['ceiling_definition_id'],
        ($preview['ceiling_fiscal_year_id'] === null ? '-' : (string)$preview['ceiling_fiscal_year_id']),
        ($preview['ceiling_version_id'] === null ? '-' : (string)$preview['ceiling_version_id']),
        ($preview['ceiling_priority'] === null ? '-' : (string)$preview['ceiling_priority']),
        buildCeilingDefinitionScopeText($preview),
        buildCeilingDefinitionPeriodsText($preview)
    );
    echo $isBrowser ? '<p><strong>' . htmlspecialchars($definitionText) . '</strong></p>' : $definitionText . "\n";

    $criteriaText = "Ceiling Match Criteria | definition_looks_for=" . buildCeilingDefinitionMatchCriteriaText($preview);
    echo $isBrowser ? '<p><strong>' . htmlspecialchars($criteriaText) . '</strong></p>' : $criteriaText . "\n";

    $txContextText = "Transaction Context | values=" . buildTransactionContextMatchText($preview);
    echo $isBrowser ? '<p><strong>' . htmlspecialchars($txContextText) . '</strong></p>' : $txContextText . "\n";
}

function getTransactionInputCeilingColumns($conn, &$metrics) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $sql = "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = 'dbo'
          AND TABLE_NAME = 'tblTransactionInput'
          AND COLUMN_NAME IN (
              'CeilingStatus',
              'CeilingStatusCheck',
              'CeilingErrorMessage',
              'CeilingLastCheckedDate',
              'CeilingCheckDate',
              'CeilingFailedFlag',
              'CeilingDefinitionID',
              'CeilingEngine',
              'CeilingAppliedBP1',
              'CeilingAppliedBP2',
              'CeilingAppliedBP3',
              'CeilingAppliedBP4',
              'CeilingAppliedBP5',
              'CeilingAppliedBP6',
              'CeilingAppliedBP7',
              'CeilingAppliedBP8',
              'CeilingAppliedBP9',
              'CeilingAppliedBP10',
              'CeilingAppliedBP11',
              'CeilingAppliedBP12',
              'CeilingAppliedTotal'
          )
    ";
    $stmt = $conn->prepare($sql);
    runStatement($stmt, [], $metrics);
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $cached = array_fill_keys($cols ?: [], true);
    return $cached;
}

function markCeilingFailureOnTransaction($conn, $transactionId, $message, &$metrics, $ceilingDefinitionId = null, $ceilingEngine = null) {
    $colSet = getTransactionInputCeilingColumns($conn, $metrics);
    if (empty($colSet)) {
        return false;
    }

    $clearDefinition = false;
    if (isCeilingFailureMessage($message) && strpos((string)$message, 'CEILING NOT FOUND') !== false) {
        $clearDefinition = true;
    }

    $sets = [];
    $params = [];

    if (isset($colSet['CeilingStatus'])) {
        $sets[] = "CeilingStatus = ?";
        $params[] = 'FAILED';
    }
    if (isset($colSet['CeilingStatusCheck'])) {
        $sets[] = "CeilingStatusCheck = ?";
        $params[] = 'CEILING FAILED';
    }
    if (isset($colSet['CeilingErrorMessage'])) {
        $sets[] = "CeilingErrorMessage = ?";
        $params[] = substr((string)$message, 0, 500);
    }
    if (isset($colSet['CeilingFailedFlag'])) {
        $sets[] = "CeilingFailedFlag = ?";
        $params[] = 1;
    }
    if (isset($colSet['CeilingDefinitionID'])) {
        if ($clearDefinition) {
            $sets[] = "CeilingDefinitionID = NULL";
        } elseif ($ceilingDefinitionId !== null && (int)$ceilingDefinitionId > 0) {
            $sets[] = "CeilingDefinitionID = ?";
            $params[] = (int)$ceilingDefinitionId;
        }
    }
    if (isset($colSet['CeilingEngine'])) {
        if ($clearDefinition && ($ceilingEngine === null || $ceilingEngine === '')) {
            $sets[] = "CeilingEngine = NULL";
        } elseif ($ceilingEngine !== null && $ceilingEngine !== '') {
            $sets[] = "CeilingEngine = ?";
            $params[] = substr((string)$ceilingEngine, 0, 20);
        }
    }
    if (isset($colSet['CeilingLastCheckedDate'])) {
        $sets[] = "CeilingLastCheckedDate = GETDATE()";
    }
    if (isset($colSet['CeilingCheckDate'])) {
        $sets[] = "CeilingCheckDate = GETDATE()";
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = (int)$transactionId;
    $updateSql = "UPDATE dbo.tblTransactionInput SET " . implode(', ', $sets) . " WHERE TransactionID = ?";
    $stmt = $conn->prepare($updateSql);
    runStatement($stmt, $params, $metrics, 'write');
    return true;
}

function markCeilingFailureOnHeadRecord($conn, $headRecordId, $message, &$metrics) {
    $colSet = getTransactionInputCeilingColumns($conn, $metrics);
    if (empty($colSet)) {
        return false;
    }

    $sets = [];
    $params = [];

    if (isset($colSet['CeilingStatus'])) {
        $sets[] = "CeilingStatus = ?";
        $params[] = 'FAILED';
    }
    if (isset($colSet['CeilingStatusCheck'])) {
        $sets[] = "CeilingStatusCheck = ?";
        $params[] = 'CEILING FAILED';
    }
    if (isset($colSet['CeilingErrorMessage'])) {
        $sets[] = "CeilingErrorMessage = ?";
        $params[] = substr((string)$message, 0, 500);
    }
    if (isset($colSet['CeilingFailedFlag'])) {
        $sets[] = "CeilingFailedFlag = ?";
        $params[] = 1;
    }
    // Intentionally do NOT set CeilingDefinitionID / CeilingEngine here.
    // Each transaction may map to a different ceiling; per-row updates handle that.
    if (isset($colSet['CeilingLastCheckedDate'])) {
        $sets[] = "CeilingLastCheckedDate = GETDATE()";
    }
    if (isset($colSet['CeilingCheckDate'])) {
        $sets[] = "CeilingCheckDate = GETDATE()";
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = (int)$headRecordId;
    $updateSql = "UPDATE dbo.tblTransactionInput SET " . implode(', ', $sets) . " WHERE HeadRecordID = ?";
    $stmt = $conn->prepare($updateSql);
    runStatement($stmt, $params, $metrics, 'write');
    return true;
}

function markCeilingSuccessOnTransaction($conn, $transactionId, &$metrics, $ceilingDefinitionId = null, $ceilingEngine = null) {
    $colSet = getTransactionInputCeilingColumns($conn, $metrics);
    if (empty($colSet)) {
        return false;
    }

    $sets = [];
    $params = [];

    if (isset($colSet['CeilingStatus'])) {
        $sets[] = "CeilingStatus = ?";
        $params[] = 'OK';
    }
    if (isset($colSet['CeilingStatusCheck'])) {
        $sets[] = "CeilingStatusCheck = ?";
        $params[] = 'CEILING OK';
    }
    if (isset($colSet['CeilingErrorMessage'])) {
        $sets[] = "CeilingErrorMessage = NULL";
    }
    if (isset($colSet['CeilingFailedFlag'])) {
        $sets[] = "CeilingFailedFlag = ?";
        $params[] = 0;
    }
    if (isset($colSet['CeilingDefinitionID']) && $ceilingDefinitionId !== null && (int)$ceilingDefinitionId > 0) {
        $sets[] = "CeilingDefinitionID = ?";
        $params[] = (int)$ceilingDefinitionId;
    }
    if (isset($colSet['CeilingEngine']) && $ceilingEngine !== null && $ceilingEngine !== '') {
        $sets[] = "CeilingEngine = ?";
        $params[] = substr((string)$ceilingEngine, 0, 20);
    }
    if (isset($colSet['CeilingLastCheckedDate'])) {
        $sets[] = "CeilingLastCheckedDate = GETDATE()";
    }
    if (isset($colSet['CeilingCheckDate'])) {
        $sets[] = "CeilingCheckDate = GETDATE()";
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = (int)$transactionId;
    $updateSql = "UPDATE dbo.tblTransactionInput SET " . implode(', ', $sets) . " WHERE TransactionID = ?";
    $stmt = $conn->prepare($updateSql);
    runStatement($stmt, $params, $metrics, 'write');
    return true;
}

function resolveCeilingDefinitionIdForTxRow($conn, $redis, $txRow, &$metrics, $skipRedisWrites = false, $forceRefresh = false) {
    try {
        $snapshotMeta = null;
        $snapshot = loadCeilingSnapshotForTx($conn, $redis, $txRow, $metrics, $skipRedisWrites, $forceRefresh, $snapshotMeta);
        return (int)($snapshot['CeilingDefinitionID'] ?? 0);
    } catch (Throwable $ignored) {
        return 0;
    }
}

function markChildCeilingFailureOnHeadRecord($conn, $headRecordId, $childTransactionId, $message, &$metrics) {
    $colSet = getTransactionInputCeilingColumns($conn, $metrics);
    if (empty($colSet)) {
        return false;
    }

    $sets = [];
    $params = [];

    if (isset($colSet['CeilingStatus'])) {
        $sets[] = "CeilingStatus = ?";
        $params[] = 'FAILED';
    }
    if (isset($colSet['CeilingStatusCheck'])) {
        $sets[] = "CeilingStatusCheck = ?";
        $params[] = 'CHILD CEILING FAILED';
    }
    if (isset($colSet['CeilingErrorMessage'])) {
        $childMsg = substr("Child tx {$childTransactionId} failed: " . (string)$message, 0, 450);
        $sets[] = "CeilingErrorMessage = LEFT(CASE WHEN CeilingErrorMessage IS NULL OR CeilingErrorMessage = '' THEN ? ELSE CeilingErrorMessage + ' | ' + ? END, 500)";
        $params[] = $childMsg;
        $params[] = $childMsg;
    }
    if (isset($colSet['CeilingFailedFlag'])) {
        $sets[] = "CeilingFailedFlag = ?";
        $params[] = 1;
    }
    if (isset($colSet['CeilingLastCheckedDate'])) {
        $sets[] = "CeilingLastCheckedDate = GETDATE()";
    }
    if (isset($colSet['CeilingCheckDate'])) {
        $sets[] = "CeilingCheckDate = GETDATE()";
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = (int)$headRecordId;
    $updateSql = "UPDATE dbo.tblTransactionInput SET " . implode(', ', $sets) . " WHERE TransactionID = ?";
    $stmt = $conn->prepare($updateSql);
    runStatement($stmt, $params, $metrics, 'write');
    return true;
}

function markCeilingProcessingErrorOnTransaction($conn, $transactionId, $message, &$metrics, $ceilingEngine = null) {
    $colSet = getTransactionInputCeilingColumns($conn, $metrics);
    if (empty($colSet)) {
        return false;
    }

    $sets = [];
    $params = [];

    if (isset($colSet['CeilingStatus'])) {
        $sets[] = "CeilingStatus = ?";
        $params[] = 'ERROR_BEFORE_CHECK';
    }
    if (isset($colSet['CeilingStatusCheck'])) {
        $sets[] = "CeilingStatusCheck = ?";
        $params[] = 'ERROR BEFORE CEILING CHECK';
    }
    if (isset($colSet['CeilingErrorMessage'])) {
        $sets[] = "CeilingErrorMessage = ?";
        $params[] = substr((string)$message, 0, 500);
    }
    if (isset($colSet['CeilingFailedFlag'])) {
        $sets[] = "CeilingFailedFlag = ?";
        $params[] = 1;
    }
    if (isset($colSet['CeilingEngine']) && $ceilingEngine !== null && $ceilingEngine !== '') {
        $sets[] = "CeilingEngine = ?";
        $params[] = substr((string)$ceilingEngine, 0, 20);
    }
    if (isset($colSet['CeilingLastCheckedDate'])) {
        $sets[] = "CeilingLastCheckedDate = GETDATE()";
    }
    if (isset($colSet['CeilingCheckDate'])) {
        $sets[] = "CeilingCheckDate = GETDATE()";
    }

    if (empty($sets)) {
        return false;
    }

    $params[] = (int)$transactionId;
    $updateSql = "UPDATE dbo.tblTransactionInput SET " . implode(', ', $sets) . " WHERE TransactionID = ?";
    $stmt = $conn->prepare($updateSql);
    runStatement($stmt, $params, $metrics, 'write');
    return true;
}

function markCeilingAppliedAmountsOnTransaction($conn, $transactionId, $appliedMonthly, $appliedTotal, &$metrics) {
    $colSet = getTransactionInputCeilingColumns($conn, $metrics);
    if (empty($colSet)) {
        return false;
    }
    $sets = [];
    $params = [];
    for ($m = 1; $m <= 12; $m++) {
        $col = 'CeilingAppliedBP' . $m;
        if (isset($colSet[$col])) {
            $sets[] = $col . " = ?";
            $params[] = (float)($appliedMonthly['BP' . $m] ?? 0);
        }
    }
    if (isset($colSet['CeilingAppliedTotal'])) {
        $sets[] = "CeilingAppliedTotal = ?";
        $params[] = (float)$appliedTotal;
    }
    if (empty($sets)) {
        return false;
    }
    $params[] = (int)$transactionId;
    $stmt = $conn->prepare("UPDATE dbo.tblTransactionInput SET " . implode(', ', $sets) . " WHERE TransactionID = ?");
    runStatement($stmt, $params, $metrics, 'write');
    return true;
}

function getCeilingAppliedAmountsFromTxRow($txRow) {
    $appliedMonthly = [];
    for ($m = 1; $m <= 12; $m++) {
        $appliedMonthly['BP' . $m] = (float)($txRow['CeilingAppliedBP' . $m] ?? 0);
    }
    $appliedTotal = (float)($txRow['CeilingAppliedTotal'] ?? 0);
    return [
        'monthly' => $appliedMonthly,
        'total' => $appliedTotal,
    ];
}

function getCeilingDeltaFromTxRow($txRow, $currentMonthly, $currentTotal) {
    $applied = getCeilingAppliedAmountsFromTxRow($txRow);
    $deltaMonthly = [];
    for ($m = 1; $m <= 12; $m++) {
        $period = 'BP' . $m;
        $deltaMonthly[$period] = (float)($currentMonthly[$period] ?? 0) - (float)($applied['monthly'][$period] ?? 0);
    }
    $deltaTotal = (float)$currentTotal - (float)$applied['total'];
    return [
        'delta_monthly' => $deltaMonthly,
        'delta_total' => $deltaTotal,
        'applied_monthly' => $applied['monthly'],
        'applied_total' => $applied['total'],
    ];
}

function isCeilingFailureMessage($message) {
    if ($message === null || $message === '') {
        return false;
    }
    return (strpos($message, 'Ceiling check failed:') === 0) || (strpos($message, 'CEILING EXCEEDED') !== false);
}

function resolveRateColumnForPeriod($sourceColumn, $periodCode) {
    if (!$sourceColumn) {
        return null;
    }

    // If source column is monthly BPxRate, map to the current BP period's rate column.
    if (preg_match('/^BP\d+Rate$/i', $sourceColumn)) {
        if (preg_match('/^BP(\d{1,2})$/i', $periodCode, $m)) {
            return 'BP' . (int)$m[1] . 'Rate';
        }
        return $sourceColumn;
    }

    return $sourceColumn;
}

function evaluateFormulaForPeriod($formula, $periodCode, $txRow, $currentMonthly, $currentTotal, $varSourceMap, $calcRow, $context, $scopeVersion, $redis, $conn, $options, &$metrics) {
    if ($formula === null || $formula === '') {
        return 0;
    }

    $working = $formula;

    // Prefer computed monthly values for BPxInpN when evaluating total/outyear formulas.
    $working = preg_replace_callback('/@BP(\d{1,2})InpN@/i', function ($m) use ($currentMonthly) {
        $idx = (int)$m[1];
        return $currentMonthly["BP{$idx}"] ?? 0;
    }, $working);

    // Allow totals to reference computed total directly.
    $working = preg_replace('/@BPTotalInpN@/i', (string)$currentTotal, $working);
    $working = preg_replace('/@BPTotal@/i', (string)$currentTotal, $working);

    $working = preg_replace_callback('/@([^@]+)@/', function ($match) use ($txRow, $conn, $context, $varSourceMap, $redis, $calcRow, $scopeVersion, $periodCode, $options, &$metrics) {
        $varNameOriginal = $match[1];
        $varName = strtoupper($varNameOriginal);

        if (!isset($varSourceMap[$varName])) {
            return $txRow[$varNameOriginal] ?? 0;
        }

        $source = $varSourceMap[$varName];

        if ($source['SourceType'] === 'previous_calc') {
            $calcName = $source['ReferencedCalcName'] ?? null;
            if (!$calcName) {
                return 0;
            }

            $sourceColumn = $source['SourceColumn'] ?? 'total';
            $previousValueKey = buildScopedCacheKey($context, $scopeVersion, strtolower($calcName), $sourceColumn);
            $value = redisGetWithMetrics($redis, $previousValueKey, $metrics);
            return $value !== false ? $value : 0;
        }

        if ($source['SourceType'] === 'transaction') {
            return $txRow[$varNameOriginal] ?? $source['DefaultValue'];
        }

        if ($source['SourceType'] === 'rates') {
            $lookupKey = $source['LookupKeyColumn'] ?? null;
            $rateCode = null;
            if ($lookupKey !== null && $lookupKey !== '') {
                // If LookupKeyColumn matches a tx field, use its value; otherwise treat as literal.
                if (isset($txRow[$lookupKey])) {
                    $rateCode = $txRow[$lookupKey];
                } else {
                    $rateCode = $lookupKey;
                }
            }
            if ($rateCode === null || $rateCode === '') {
                $rateCode = $calcRow['RateLookupCode'] ?? null;
            }
            if (!$rateCode || strtoupper((string)$rateCode) === 'UOM' || strtoupper((string)$rateCode) === 'UOMCODE') {
                $rateCode = $txRow['UOMCodeInpC'] ?? null;
            }

            if (!$rateCode) {
                return $source['DefaultValue'];
            }

            $sourceTable = $source['SourceTable'] ?? 'tblRates';
            $col = resolveRateColumnForPeriod($source['SourceColumn'] ?? null, $periodCode);
            if (!$col) {
                return $source['DefaultValue'];
            }

            $rateDataObject = $context['DataObjectCode'];
            $ratesCacheKey = sprintf(
                'calcmeta:ratehash:v%s:%s:%s:%s:%s:%s',
                $scopeVersion,
                strtolower($sourceTable),
                $context['FiscalYearID'],
                $context['VersionID'],
                $rateDataObject,
                strtolower((string)$rateCode)
            );
            $cachedRate = redisHGetWithMetrics($redis, $ratesCacheKey, $col, $metrics);
            if ($cachedRate !== null && $cachedRate !== false && $cachedRate !== '') {
                $value = $cachedRate;
            } else {
                $sql = "SELECT " . implode(', ', getRateColumns()) . " FROM {$sourceTable} 
                        WHERE FiscalYearID = ? AND VersionID = ? 
                          AND DataObjectCode = ? AND RateCode = ?";
                $stmt = $conn->prepare($sql);
                runStatement($stmt, [
                    $context['FiscalYearID'],
                    $context['VersionID'],
                    $rateDataObject,
                    $rateCode
                ], $metrics);
                $rateRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$rateRow) {
                    $rateDataObject = '0';
                    $ratesCacheKey = sprintf(
                        'calcmeta:ratehash:v%s:%s:%s:%s:%s:%s',
                        $scopeVersion,
                        strtolower($sourceTable),
                        $context['FiscalYearID'],
                        $context['VersionID'],
                        $rateDataObject,
                        strtolower((string)$rateCode)
                    );
                    $stmt = $conn->prepare($sql);
                    runStatement($stmt, [
                        $context['FiscalYearID'],
                        $context['VersionID'],
                        $rateDataObject,
                        $rateCode
                    ], $metrics);
                    $rateRow = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if ($rateRow) {
                    $hashPayload = [];
                    foreach (getRateColumns() as $rateField) {
                        $hashPayload[$rateField] = $rateRow[$rateField] ?? '';
                    }
                    if (!$options['skip_redis_writes']) {
                        redisHmsetExWithMetrics($redis, $ratesCacheKey, $hashPayload, CACHE_META_TTL_SECONDS, $metrics);
                    }
                    $value = $rateRow[$col] ?? $source['DefaultValue'];
                } else {
                    $value = $source['DefaultValue'];
                }
            }

            return $value;
        }

        return 0;
    }, $working);

    try {
        $expr = new ExpressionLanguage();
        $value = $expr->evaluate($working);
    } catch (Exception $e) {
        throw new Exception("Formula evaluation error for {$periodCode}: " . $e->getMessage());
    }

    return is_numeric($value) ? (float)$value : 0;
}

function getRateColumns() {
    return [
        'BP1Rate', 'BP2Rate', 'BP3Rate', 'BP4Rate', 'BP5Rate', 'BP6Rate',
        'BP7Rate', 'BP8Rate', 'BP9Rate', 'BP10Rate', 'BP11Rate', 'BP12Rate',
        'OY1Rate', 'OY2Rate', 'OY3Rate', 'OY4Rate', 'OY5Rate',
        'OY6Rate', 'OY7Rate', 'OY8Rate', 'OY9Rate', 'OY10Rate',
    ];
}

function cacheKeyCalcRow($scopeVersion, $fiscalYearId, $calculationId) {
    return sprintf(
        'calcmeta:calcrow:v%d:%d:%d',
        $scopeVersion,
        $fiscalYearId,
        $calculationId
    );
}

function cacheKeyFormulaMap($scopeVersion, $fiscalYearId, $calculationId) {
    return sprintf(
        'calcmeta:formulas:v%d:%d:%d:active1',
        $scopeVersion,
        $fiscalYearId,
        $calculationId
    );
}

function tryLoadCachedCalculationResult($redis, $context, $scopeVersion, $keyName, $minSourceUpdatedTs, &$metrics) {
    $computedAtKey = buildScopedCacheKey($context, $scopeVersion, $keyName, 'computed_at');
    $computedAtRaw = redisGetWithMetrics($redis, $computedAtKey, $metrics);
    if ($computedAtRaw === null || $computedAtRaw === false) {
        return null;
    }

    $computedAtTs = (int)$computedAtRaw;
    if ($computedAtTs <= 0) {
        return null;
    }

    if ($minSourceUpdatedTs !== null && $computedAtTs < (int)$minSourceUpdatedTs) {
        return null;
    }

    $monthly = [];
    for ($m = 1; $m <= 12; $m++) {
        $periodCode = "BP{$m}";
        $cacheKey = buildScopedCacheKey($context, $scopeVersion, $keyName, $periodCode);
        $value = redisGetWithMetrics($redis, $cacheKey, $metrics);
        if ($value === null || $value === false) {
            return null;
        }
        $monthly[$periodCode] = (float)$value;
    }

    $totalKey = buildScopedCacheKey($context, $scopeVersion, $keyName, 'total');
    $total = redisGetWithMetrics($redis, $totalKey, $metrics);
    if ($total === null || $total === false) {
        return null;
    }

    return [
        'monthly' => $monthly,
        'total' => (float)$total,
    ];
}

function getRowUpdatedTs($row) {
    $raw = $row['UpdatedDate'] ?? $row['CreatedDate'] ?? null;
    if (!$raw) {
        return null;
    }
    $ts = strtotime((string)$raw);
    if ($ts === false) {
        return null;
    }
    return $ts;
}

function computeTxCacheSignature($txRow) {
    // Signature anchors cache keys to transaction-specific inputs and dimensions.
    // This prevents stale cross-transaction reuse when the same FY/version/context is shared.
    $fields = [
        'TransactionID',
        'HeadRecordID',
        'FiscalYearID',
        'VersionID',
        'DataObjectCode',
        'TransactionTypeCode',
        'UOMCodeInpC',
        'CurrencyInpC',
        'AccountCode',
        'GLAccountCode',
        'CostItemID',
    ];
    for ($s = 1; $s <= 20; $s++) {
        $fields[] = "Segment{$s}Code";
    }
    for ($m = 1; $m <= 12; $m++) {
        $fields[] = "BP{$m}InpN";
    }
    $fields[] = 'BPTotalInpN';
    $fields[] = 'PY5InpN';
    $fields[] = 'PY4InpN';
    $fields[] = 'PY3InpN';
    $fields[] = 'PY2InpN';
    $fields[] = 'PY1InpN';
    $fields[] = 'BPOpBalInpN';

    $payload = [];
    foreach ($fields as $field) {
        $payload[$field] = $txRow[$field] ?? null;
    }
    return substr(sha1(json_encode($payload)), 0, 16);
}

function generateUuidV4() {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    $hex = bin2hex($data);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function insertTransactionResultRow($conn, $txId, $flatRow, $currentMonthly, $currentTotal, $quarterly, $outYears, $formulaVersion, $runId, $context, $scopeVersion, $engineVersion, $startedAt, &$metrics) {
    // Remove previous results for this transaction to avoid duplicates on recompute.
    $stmt = $conn->prepare("DELETE FROM tblTransactionResultFlat WHERE TransactionID = ?");
    runStatement($stmt, [$txId], $metrics, 'write');
    $stmt = $conn->prepare("DELETE FROM tblTransactionResult WHERE TransactionID = ?");
    runStatement($stmt, [$txId], $metrics, 'write');

    $resultJson = json_encode([
        'monthly' => $currentMonthly,
        'total' => $currentTotal,
        'quarterly' => $quarterly ?? [],
        'outyears' => $outYears ?? [],
    ]);
    $durationMsSnapshot = (int)round((microtime(true) - $startedAt) * 1000);
    $stmt = $conn->prepare("
        INSERT INTO tblTransactionResult (
            TransactionID, ResultJSON, CalculatedDate, FormulaVersion,
            RunID, FormulaSetCode, ScopeVersion, EngineVersion,
            Status, DurationMs, BPTotal, ErrorMessage
        )
        VALUES (?, ?, GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    runStatement($stmt, [
        $txId,
        $resultJson,
        $formulaVersion,
        $runId,
        $context['FormulaSetCode'],
        $scopeVersion,
        $engineVersion,
        'Success',
        $durationMsSnapshot,
        $currentTotal,
        null
    ], $metrics, 'write');

    $transactionResultId = (int)$conn->lastInsertId();
    if ($transactionResultId <= 0) {
        return;
    }

    $flatStmt = $conn->prepare("
        INSERT INTO tblTransactionResultFlat (
            TransactionResultID,
            TransactionID,
            HeadRecordID,
            RecordTypeCode,
            FiscalYearID,
            VersionID,
            DataObjectCode,
            TransactionTypeCode,
            AccountCode,
            GLAccountCode,
            CostItemID,
            CalculationID,
            CurrencyInpC,
            UOMCodeInpC,
            Segment1Code, Segment2Code, Segment3Code, Segment4Code, Segment5Code,
            Segment6Code, Segment7Code, Segment8Code, Segment9Code, Segment10Code,
            Segment11Code, Segment12Code, Segment13Code, Segment14Code, Segment15Code,
            Segment16Code, Segment17Code, Segment18Code, Segment19Code, Segment20Code,
            BP1, BP2, BP3, BP4, BP5, BP6,
            BP7, BP8, BP9, BP10, BP11, BP12,
            BPTotal,
            PY5, PY4, PY3, PY2, PY1,
            BPOpBal,
            BPQ1, BPQ2, BPQ3, BPQ4,
            BPOY1, BPOY2, BPOY3, BPOY4, BPOY5,
            BPOY6, BPOY7, BPOY8, BPOY9, BPOY10,
            CalculatedDate
        )
        VALUES (
            ?, ?,
            ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?,
            ?, ?, ?, ?, ?,
            ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            GETDATE()
        )
    ");

    $bp1 = $currentMonthly['BP1'] ?? 0;
    $bp2 = $currentMonthly['BP2'] ?? 0;
    $bp3 = $currentMonthly['BP3'] ?? 0;
    $bp4 = $currentMonthly['BP4'] ?? 0;
    $bp5 = $currentMonthly['BP5'] ?? 0;
    $bp6 = $currentMonthly['BP6'] ?? 0;
    $bp7 = $currentMonthly['BP7'] ?? 0;
    $bp8 = $currentMonthly['BP8'] ?? 0;
    $bp9 = $currentMonthly['BP9'] ?? 0;
    $bp10 = $currentMonthly['BP10'] ?? 0;
    $bp11 = $currentMonthly['BP11'] ?? 0;
    $bp12 = $currentMonthly['BP12'] ?? 0;

    $bpq1 = $quarterly['BPQ1'] ?? ($bp1 + $bp2 + $bp3);
    $bpq2 = $quarterly['BPQ2'] ?? ($bp4 + $bp5 + $bp6);
    $bpq3 = $quarterly['BPQ3'] ?? ($bp7 + $bp8 + $bp9);
    $bpq4 = $quarterly['BPQ4'] ?? ($bp10 + $bp11 + $bp12);
    $oyBase = $bp12;
    $bpoY1 = $outYears['BPOY1'] ?? $oyBase;
    $bpoY2 = $outYears['BPOY2'] ?? $oyBase;
    $bpoY3 = $outYears['BPOY3'] ?? $oyBase;
    $bpoY4 = $outYears['BPOY4'] ?? $oyBase;
    $bpoY5 = $outYears['BPOY5'] ?? $oyBase;
    $bpoY6 = $outYears['BPOY6'] ?? $oyBase;
    $bpoY7 = $outYears['BPOY7'] ?? $oyBase;
    $bpoY8 = $outYears['BPOY8'] ?? $oyBase;
    $bpoY9 = $outYears['BPOY9'] ?? $oyBase;
    $bpoY10 = $outYears['BPOY10'] ?? $oyBase;

    runStatement($flatStmt, [
        $transactionResultId,
        $txId,
        $flatRow['HeadRecordID'] ?? null,
        $flatRow['RecordTypeCode'] ?? null,
        $flatRow['FiscalYearID'] ?? null,
        $flatRow['VersionID'] ?? null,
        $flatRow['DataObjectCode'] ?? null,
        $flatRow['TransactionTypeCode'] ?? null,
        $flatRow['AccountCode'] ?? null,
        $flatRow['GLAccountCode'] ?? null,
        $flatRow['CostItemID'] ?? null,
        $flatRow['CalculationID'] ?? null,
        $flatRow['CurrencyInpC'] ?? null,
        $flatRow['UOMCodeInpC'] ?? null,
        $flatRow['Segment1Code'] ?? null,
        $flatRow['Segment2Code'] ?? null,
        $flatRow['Segment3Code'] ?? null,
        $flatRow['Segment4Code'] ?? null,
        $flatRow['Segment5Code'] ?? null,
        $flatRow['Segment6Code'] ?? null,
        $flatRow['Segment7Code'] ?? null,
        $flatRow['Segment8Code'] ?? null,
        $flatRow['Segment9Code'] ?? null,
        $flatRow['Segment10Code'] ?? null,
        $flatRow['Segment11Code'] ?? null,
        $flatRow['Segment12Code'] ?? null,
        $flatRow['Segment13Code'] ?? null,
        $flatRow['Segment14Code'] ?? null,
        $flatRow['Segment15Code'] ?? null,
        $flatRow['Segment16Code'] ?? null,
        $flatRow['Segment17Code'] ?? null,
        $flatRow['Segment18Code'] ?? null,
        $flatRow['Segment19Code'] ?? null,
        $flatRow['Segment20Code'] ?? null,
        $bp1,
        $bp2,
        $bp3,
        $bp4,
        $bp5,
        $bp6,
        $bp7,
        $bp8,
        $bp9,
        $bp10,
        $bp11,
        $bp12,
        $currentTotal,
        $flatRow['PY5InpN'] ?? null,
        $flatRow['PY4InpN'] ?? null,
        $flatRow['PY3InpN'] ?? null,
        $flatRow['PY2InpN'] ?? null,
        $flatRow['PY1InpN'] ?? null,
        $flatRow['BPOpBalInpN'] ?? null,
        $bpq1,
        $bpq2,
        $bpq3,
        $bpq4,
        $bpoY1,
        $bpoY2,
        $bpoY3,
        $bpoY4,
        $bpoY5,
        $bpoY6,
        $bpoY7,
        $bpoY8,
        $bpoY9,
        $bpoY10
    ], $metrics, 'write');
}

// --------------------------------------------------
// Main function
// --------------------------------------------------

function processTransactionStub($transactionId, $options = []) {
    global $conn;
    allowLongRunningExecution();
    $options = array_merge([
        'invalidate_scope' => false,
        'skip_db_writes' => false,
        'skip_redis_writes' => false,
        'force_refresh_ceiling_cache' => false,
        'force_reseed_ceiling_state' => false,
        'enable_ceiling_redis_precheck' => true,
        'enable_ceiling_sproc_check' => false,
        'ceiling_engine' => 'redis', // sproc | redis
        'ceiling_check_mode' => 'per_transaction', // group_headrecord | per_transaction
        'ceiling_period_enforcement' => true, // true=total+period, false=total-only
        'skip_ceiling_previews' => false,
        'force_text_output' => false,
        'show_action_buttons' => true,
        'retry_deadlocks' => 15,
    ], $options);
    $startedAt = microtime(true);
    $retryAttempts = (int)($options['retry_deadlocks'] ?? 0);
    $maxRetryAttempts = max(0, $retryAttempts);
    $attempt = 0;
    $metrics = [
        'db_query_count' => 0,
        'db_queries_read' => 0,
        'db_queries_write' => 0,
        'redis_get_count' => 0,
        'redis_set_count' => 0,
        'cache_hit_count' => 0,
        'cache_miss_count' => 0,
        'ceiling_snapshot_count' => 0,
        'ceiling_snapshot_ms' => 0,
        'ceiling_precheck_count' => 0,
        'ceiling_precheck_ms' => 0,
        'ceiling_final_sproc_count' => 0,
        'ceiling_final_sproc_ms' => 0,
        'ceiling_final_redis_count' => 0,
        'ceiling_final_redis_ms' => 0,
        'ceiling_sync_back_count' => 0,
        'ceiling_sync_back_ms' => 0,
        'duration_ms' => 0,
    ];
    $result = null;
    $runId = generateUuidV4();
    $engineVersion = 'process_transaction_stub/v1';
    $formulaVersion = 1;
    $context = null;
    $scopeVersion = null;
    $lastCeilingCheckContext = null;

    $isBrowser = (php_sapi_name() !== 'cli') && empty($options['force_text_output']);
    if ($isBrowser) {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><title>' . htmlspecialchars(\App\Shared\Lang::translateLiteral('Calculation Chain Results - Tx'), ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($transactionId) . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f7fa;line-height:1.6;}';
        echo 'h1,h2{color:#2c3e50;}table{width:100%;border-collapse:collapse;margin:30px 0;background:white;box-shadow:0 4px 10px rgba(0,0,0,0.1);border-radius:8px;overflow:hidden;}';
        echo 'th,td{padding:14px 16px;text-align:left;border-bottom:1px solid #e0e0e0;}th{background:#3498db;color:white;font-weight:bold;}';
        echo 'tr:nth-child(even){background:#f8f9fa;}.value{font-family:Consolas,monospace;background:#ecf0f1;padding:4px 8px;border-radius:4px;}';
        echo '.success{color:#27ae60;font-weight:bold;}.error{color:#e74c3c;font-weight:bold;}.summary{background:#e8f4f8;padding:20px;border-radius:8px;margin:30px 0;}.chain{border-left:4px solid #3498db;padding-left:15px;margin-top:40px;}';
        echo '.btn{display:inline-block;padding:10px 14px;background:#2c3e50;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;}.btn:hover{background:#1f2d3a;}</style></head><body>';
    }

    echo $isBrowser ? '<h1>Calculation Chain Results - Transaction ID: ' . htmlspecialchars($transactionId) . '</h1>' : "Calculation Chain Results - Transaction ID: $transactionId\n\n";

retry_tx:
    $conn->beginTransaction();
    $ceilingFailureOccurred = false;
    $ceilingFailureMessages = [];
    $attempt++;

    try {
        // 1. Load parent transaction
        $stmt = $conn->prepare("SELECT * FROM tblTransactionInput WHERE TransactionID = ?");
        runStatement($stmt, [$transactionId], $metrics);
        $txRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$txRow) {
            throw new Exception("Transaction not found: $transactionId");
        }
        $txUpdatedTs = getRowUpdatedTs($txRow);
        $headRecordId = (int)($txRow['HeadRecordID'] ?? $transactionId);
        $isHeadRecord = (empty($txRow['HeadRecordID']) || (int)$txRow['HeadRecordID'] === (int)$transactionId);
        $desiredRecordType = $isHeadRecord ? 'H' : 'C';
        if (!$options['skip_db_writes'] && ($txRow['RecordTypeCode'] ?? null) !== $desiredRecordType) {
            $stmt = $conn->prepare("UPDATE dbo.tblTransactionInput SET RecordTypeCode = ? WHERE TransactionID = ?");
            runStatement($stmt, [$desiredRecordType, (int)$transactionId], $metrics, 'write');
            $txRow['RecordTypeCode'] = $desiredRecordType;
        }
        $redis = getRedis();
        $reseedCeilingDefinitionId = null;

        if (!empty($options['force_reseed_ceiling_state'])) {
            $reseedCeilingDefinitionId = reseedCeilingStateForTx(
                $conn,
                $redis,
                $txRow,
                $metrics,
                $options['skip_redis_writes']
            );
        }

        // Summary
        echo $isBrowser ? '<div class="summary">' : "Summary:\n";
        echo $isBrowser ? '<strong>Cost Centre:</strong> ' . htmlspecialchars($txRow['DataObjectCode'] ?? '-') . '<br>' : "Cost Centre: " . ($txRow['DataObjectCode'] ?? '-') . "\n";
        echo $isBrowser ? '<strong>UOM:</strong> ' . htmlspecialchars($txRow['UOMCodeInpC'] ?? '-') . '<br>' : "UOM: " . ($txRow['UOMCodeInpC'] ?? '-') . "\n";
        echo $isBrowser ? '<strong>Fiscal Year / Version:</strong> ' . $txRow['FiscalYearID'] . ' / ' . $txRow['VersionID'] . '<br>' : "Fiscal Year / Version: {$txRow['FiscalYearID']} / {$txRow['VersionID']}\n";
        echo $isBrowser ? '</div>' : "\n";

        // Transaction key fields table
        echo $isBrowser ? '<h2>Parent Transaction Input Data</h2><table><tr><th>Field</th><th>Value</th></tr>' : "Parent Transaction Input Data:\n";
        $keyFields = [
            'FiscalYearID'       => $txRow['FiscalYearID'] ?? '-',
            'VersionID'          => $txRow['VersionID'] ?? '-',
            'DataObjectCode'     => $txRow['DataObjectCode'] ?? '-',
            'TransactionTypeCode'=> $txRow['TransactionTypeCode'] ?? '-',
            'UOMCodeInpC'        => $txRow['UOMCodeInpC'] ?? '-',
            'CurrencyInpC'       => $txRow['CurrencyInpC'] ?? '-',
            'HeadRecordID'       => $txRow['HeadRecordID'] ?? '-',
        ];
        foreach ($keyFields as $field => $val) {
            echo $isBrowser ? "<tr><td>$field</td><td>" . htmlspecialchars($val) . "</td></tr>" : "$field: $val\n";
        }
        echo $isBrowser ? '</table>' : "\n";
        if (!empty($options['force_reseed_ceiling_state'])) {
            $msg = $reseedCeilingDefinitionId
                ? "Ceiling state reseeded for CeilingDefinitionID {$reseedCeilingDefinitionId} (DB reset + Redis cleared)."
                : "Ceiling state reseed requested, but no matching ceiling definition was found.";
            echo $isBrowser ? '<p class="success"><strong>' . htmlspecialchars($msg) . '</strong></p>' : $msg . "\n";
        }

        $ceilingModeText = ($options['ceiling_check_mode'] === 'group_headrecord') ? 'group_headrecord' : 'per_transaction';
        $ceilingEnforcementText = !empty($options['ceiling_period_enforcement']) ? 'period+total' : 'total-only';
        $ceilingPrecheckText = !empty($options['enable_ceiling_redis_precheck']) ? 'ON' : 'OFF';
        $ceilingSprocText = !empty($options['enable_ceiling_sproc_check']) ? 'ON' : 'OFF';
        $ceilingRefreshText = !empty($options['force_refresh_ceiling_cache']) ? 'ON' : 'OFF';
        $ceilingEngineText = in_array(($options['ceiling_engine'] ?? 'sproc'), ['sproc', 'redis'], true) ? $options['ceiling_engine'] : 'sproc';
        $ceilingConfigText = sprintf(
            'Ceiling Checks | redis_precheck=%s | sproc_check=%s | engine=%s | mode=%s | enforcement=%s | refresh_ceiling_cache=%s',
            $ceilingPrecheckText,
            $ceilingSprocText,
            $ceilingEngineText,
            $ceilingModeText,
            $ceilingEnforcementText,
            $ceilingRefreshText
        );
        echo $isBrowser ? '<p><strong>' . htmlspecialchars($ceilingConfigText) . '</strong></p>' : $ceilingConfigText . "\n";
        if ($isBrowser && !empty($options['show_action_buttons'])) {
            $refreshParams = $_GET;
            $refreshParams['tx'] = (int)$transactionId;
            $refreshParams['refresh_ceiling_cache'] = '1';
            $refreshParams['reseed_ceiling_state'] = '1';
            $refreshUrl = htmlspecialchars('?' . http_build_query($refreshParams), ENT_QUOTES, 'UTF-8');
            $reseedParams = $_GET;
            $reseedParams['tx'] = (int)$transactionId;
            $reseedParams['refresh_ceiling_cache'] = '1';
            $reseedParams['reseed_ceiling_state'] = '1';
            $reseedUrl = htmlspecialchars('?' . http_build_query($reseedParams), ENT_QUOTES, 'UTF-8');
            $syncParams = $_GET;
            $syncParams['sync_ceiling_balances'] = '1';
            $syncUrl = htmlspecialchars('?' . http_build_query($syncParams), ENT_QUOTES, 'UTF-8');
            $warmupParams = $_GET;
            $warmupParams['warmup_ceiling_cache'] = '1';
            $warmupUrl = htmlspecialchars('?' . http_build_query($warmupParams), ENT_QUOTES, 'UTF-8');
            $redisViewParams = $_GET;
            $redisViewParams['view_redis_balances'] = '1';
            $redisViewUrl = htmlspecialchars('?' . http_build_query($redisViewParams), ENT_QUOTES, 'UTF-8');
            $monitorUrl = htmlspecialchars('ceiling_monitor.php', ENT_QUOTES, 'UTF-8');
            $globalResetParams = $_GET;
            $globalResetParams['global_reset_balances'] = '1';
            $globalResetUrl = htmlspecialchars('?' . http_build_query($globalResetParams), ENT_QUOTES, 'UTF-8');
            $bulkParams = $_GET;
            unset($bulkParams['tx']);
            unset($bulkParams['refresh_ceiling_cache']);
            unset($bulkParams['reseed_ceiling_state']);
            unset($bulkParams['sync_ceiling_balances']);
            unset($bulkParams['warmup_ceiling_cache']);
            unset($bulkParams['global_reset_balances']);
            unset($bulkParams['view_redis_balances']);
            $bulkParams['bulk_run'] = '1';
            $bulkUrl = htmlspecialchars('?' . http_build_query($bulkParams), ENT_QUOTES, 'UTF-8');
            echo '<p><a class="btn" href="' . $refreshUrl . '" onclick="return confirm(\'Refresh will reseed DB balance from ceiling definition and clear Redis ceiling state. Continue?\');">Refresh Ceilings (Reset + Sync)</a> <a class="btn" href="' . $reseedUrl . '" onclick="return confirm(\'Reseed ceiling state from DB definition and clear Redis balance/cache?\');">Reseed/Reset Ceiling State</a> <a class="btn" href="' . $globalResetUrl . '" onclick="return confirm(\'Global reset will set all active/approved balances equal to ceilings and clear Redis ceiling keys. Continue?\');">Global Reset Balances to Ceilings</a> <a class="btn" href="' . $syncUrl . '" onclick="return confirm(\'Sync Redis ceiling balances to tblCeilingBalance now?\');">Sync Ceiling Balances Now</a> <a class="btn" href="' . $warmupUrl . '" onclick="return confirm(\'Warm Redis ceiling snapshot/balance cache now?\');">Warmup Ceiling Cache Now</a> <a class="btn" href="' . $redisViewUrl . '">View Redis Balances</a> <a class="btn" href="' . $monitorUrl . '" target="_blank" rel="noopener">Ceiling Monitor</a> <a class="btn" href="' . $bulkUrl . '" onclick="return confirm(\'Run transaction save for every row currently in tblTransactionInput?\');">Run Save For ALL Transactions</a></p>';
        }

        if (empty($options['skip_ceiling_previews'])) {
            $initialPreview = getCeilingCheckPreview(
                $conn,
                $redis,
                (int)$transactionId,
                $ceilingModeText,
                $metrics,
                $options['skip_redis_writes'],
                !empty($options['force_refresh_ceiling_cache']),
                null,
                'pre-run'
            );
            printCeilingCheckPreview($initialPreview, !empty($options['ceiling_period_enforcement']) ? 'period' : 'total', $isBrowser);
        }

        // Lookup initial CalculationID:
        // 1) prefer explicit TransactionInput.CalculationID when valid for the FY
        // 2) otherwise derive from tblUOMs mapping
        $currentCalcId = null;
        $txCalcId = isset($txRow['CalculationID']) ? (int)$txRow['CalculationID'] : 0;
        if ($txCalcId > 0) {
            $stmt = $conn->prepare("
                SELECT TOP 1 CalculationID
                FROM tblCalculations
                WHERE FiscalYearID = ?
                  AND CalculationID = ?
            ");
            runStatement($stmt, [
                $txRow['FiscalYearID'],
                $txCalcId
            ], $metrics);
            $currentCalcId = $stmt->fetchColumn();
        }

        if (!$currentCalcId) {
            $stmt = $conn->prepare("
                SELECT CalculationID 
                FROM tblUOMs 
                WHERE FiscalYearID = ? 
                  AND TransactionType = ? 
                  AND UOMCode = ? 
                  AND UOMStatus = 'Active'
            ");
            runStatement($stmt, [
                $txRow['FiscalYearID'],
                $txRow['TransactionTypeCode'],
                $txRow['UOMCodeInpC']
            ], $metrics);
            $currentCalcId = $stmt->fetchColumn();
        }

        if (!$currentCalcId) {
            throw new Exception(
                "No starting CalculationID found. tx.CalculationID=" . ($txCalcId ?: 'null')
                . ", TransactionType=" . ($txRow['TransactionTypeCode'] ?? 'null')
                . ", UOM=" . ($txRow['UOMCodeInpC'] ?? 'null')
            );
        }

        echo $isBrowser ? '<h2>Starting CalculationID</h2><p>' . $currentCalcId . '</p>' : "Starting CalculationID: $currentCalcId\n";

        $startingCalcId = $currentCalcId;  // Track parent calc to skip its child creation

        // Context
        $context = [
            'FormulaSetCode'   => 'BUDGET-FY26',
            'FiscalYearID'     => $txRow['FiscalYearID'],
            'VersionID'        => $txRow['VersionID'],
            'DataObjectCode'   => $txRow['DataObjectCode'],
            'TxCacheSig'       => computeTxCacheSignature($txRow),
        ];
        if ($options['invalidate_scope']) {
            $scopeVersion = invalidateCalcScope($redis, $context);
        } else {
            $scopeVersion = getOrInitScopeVersion($redis, $context);
        }

        // Load variable sources
        $varSourcesCacheKey = "calcmeta:varsources:active:v{$scopeVersion}";
        $cachedVarSources = redisGetWithMetrics($redis, $varSourcesCacheKey, $metrics);
        if ($cachedVarSources !== null) {
            $varSources = json_decode($cachedVarSources, true) ?: [];
        } else {
            $stmt = $conn->prepare("
                SELECT VariableName, SourceType, SourceTable, SourceColumn, LookupKeyColumn, DefaultValue, ReferencedCalcName
                FROM tblVariableSources WHERE ActiveFlag = 1
            ");
            runStatement($stmt, [], $metrics);
            $varSources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$options['skip_redis_writes']) {
                redisSetExWithMetrics($redis, $varSourcesCacheKey, CACHE_META_TTL_SECONDS, json_encode($varSources), $metrics);
            }
        }

        $varSourceMap = [];
        foreach ($varSources as $source) {
            $varSourceMap[strtoupper($source['VariableName'])] = $source;  // Normalize to uppercase for case-insensitive lookup
        }

        if (!$options['skip_db_writes']) {
            $parentHeadRecordId = $txRow['HeadRecordID'] ?? $transactionId;
            echo $isBrowser
                ? '<p class="success">Child transactions are preserved and reused for HeadRecordID ' . htmlspecialchars((string)$parentHeadRecordId) . '.</p>'
                : "Child transactions are preserved and reused for HeadRecordID {$parentHeadRecordId}.\n";
        }

        // Chain processing loop
        $processedCalcs = [];
        $previousMonthly = [];
        $ceilingAnchorTxId = $transactionId;
        $groupCeilingMonthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $groupCeilingMonthly['BP' . $m] = 0.0;
        }
        $groupCeilingTotal = 0.0;

        while ($currentCalcId && !in_array($currentCalcId, $processedCalcs)) {
            $processedCalcs[] = $currentCalcId;

            // Load current calculation metadata (Redis cached)
            $calcRowCacheKey = cacheKeyCalcRow($scopeVersion, (int)$txRow['FiscalYearID'], (int)$currentCalcId);
            $cachedCalcRow = redisGetWithMetrics($redis, $calcRowCacheKey, $metrics);
            if ($cachedCalcRow !== null && $cachedCalcRow !== false) {
                $calcRow = json_decode($cachedCalcRow, true) ?: null;
            } else {
                $stmt = $conn->prepare("SELECT * FROM tblCalculations WHERE FiscalYearID = ? AND CalculationID = ?");
                runStatement($stmt, [$txRow['FiscalYearID'], $currentCalcId], $metrics);
                $calcRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($calcRow && !$options['skip_redis_writes']) {
                    redisSetExWithMetrics($redis, $calcRowCacheKey, CACHE_META_TTL_SECONDS, json_encode($calcRow), $metrics);
                }
            }

            if (!$calcRow) {
                throw new Exception("Calculation not found in chain: ID $currentCalcId");
            }

            echo $isBrowser ? '<div class="chain"><h2>Processing Calculation: ' . htmlspecialchars($calcRow['CalculationName']) . ' (ID: ' . $currentCalcId . ')</h2>' : "\n--- Processing Calculation: {$calcRow['CalculationName']} (ID: $currentCalcId) ---\n";
            $keyName = $calcRow['RedisKeyName'] ?? strtolower($calcRow['CalculationName']);
            $cachedCalcResult = tryLoadCachedCalculationResult($redis, $context, $scopeVersion, $keyName, $txUpdatedTs, $metrics);
            $usedCachedResult = false;
            if ($cachedCalcResult !== null) {
                $currentMonthly = $cachedCalcResult['monthly'];
                $currentTotal = round($cachedCalcResult['total'], 2);
                $usedCachedResult = true;
                echo $isBrowser ? '<p class="success">Using cached output for this calculation.</p>' : "Using cached output for this calculation.\n";
                $formulasByPeriod = [];
                $formulasCacheKey = cacheKeyFormulaMap($scopeVersion, (int)$txRow['FiscalYearID'], (int)$currentCalcId);
                $cachedFormulas = redisGetWithMetrics($redis, $formulasCacheKey, $metrics);
                if ($cachedFormulas !== null && $cachedFormulas !== false) {
                    $formulasByPeriod = json_decode($cachedFormulas, true) ?: [];
                } else {
                    $stmt = $conn->prepare("
                        SELECT PeriodCode, FormulaText
                        FROM tblCalculationFormulas
                        WHERE FiscalYearID = ?
                          AND CalculationID = ?
                          AND ActiveFlag = 1
                        ORDER BY PeriodCode
                    ");
                    runStatement($stmt, [$txRow['FiscalYearID'], $currentCalcId], $metrics);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $formulasByPeriod[$row['PeriodCode']] = $row['FormulaText'];
                    }
                    if (!$options['skip_redis_writes']) {
                        redisSetExWithMetrics($redis, $formulasCacheKey, CACHE_META_TTL_SECONDS, json_encode($formulasByPeriod), $metrics);
                    }
                }

                echo $isBrowser
                    ? '<table><tr><th>Period</th><th>Original Formula</th><th>Substituted Formula</th><th>Evaluated Value</th><th>Status</th></tr>'
                    : "Periods:\n";
                for ($m = 1; $m <= 12; $m++) {
                    $periodCode = "BP{$m}";
                    $formula = $formulasByPeriod[$periodCode] ?? '';
                    $value = isset($currentMonthly[$periodCode]) ? round((float)$currentMonthly[$periodCode], 2) : 0;
                    echo $isBrowser
                        ? "<tr><td>BP$m</td><td class=\"value\">" . htmlspecialchars($formula) . "</td><td class=\"value\">(cached)</td><td class=\"value success\">$value</td><td class=\"success\">Cached</td></tr>"
                        : "BP$m | Original: $formula | Substituted: (cached) | Value: $value | Cached\n";
                }
                echo $isBrowser ? '</table><h2>Total (BP1-BP12 Sum)</h2><p class="success">' . $currentTotal . '</p>' : "\nTotal: $currentTotal\n\n";
            }

            if (!$usedCachedResult) {
                // Load formulas for this calculation (Redis cached)
                $formulasCacheKey = cacheKeyFormulaMap($scopeVersion, (int)$txRow['FiscalYearID'], (int)$currentCalcId);
                $cachedFormulas = redisGetWithMetrics($redis, $formulasCacheKey, $metrics);
                if ($cachedFormulas !== null && $cachedFormulas !== false) {
                    $formulasByPeriod = json_decode($cachedFormulas, true) ?: [];
                } else {
                    $stmt = $conn->prepare("
                        SELECT PeriodCode, FormulaText 
                        FROM tblCalculationFormulas 
                        WHERE FiscalYearID = ? 
                          AND CalculationID = ? 
                          AND ActiveFlag = 1
                        ORDER BY PeriodCode
                    ");
                    runStatement($stmt, [$txRow['FiscalYearID'], $currentCalcId], $metrics);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $formulasByPeriod = [];
                    foreach ($rows as $row) {
                        $formulasByPeriod[$row['PeriodCode']] = $row['FormulaText'];
                    }
                    if (!$options['skip_redis_writes']) {
                        redisSetExWithMetrics($redis, $formulasCacheKey, CACHE_META_TTL_SECONDS, json_encode($formulasByPeriod), $metrics);
                    }
                }

                if (empty($formulasByPeriod)) {
                    echo $isBrowser ? '<p class="error">No formulas for this calculation</p>' : "No formulas for this calculation\n";
                }

            // Process periods
            $currentMonthly = [];
            $expr = new ExpressionLanguage();

            echo $isBrowser ? '<table><tr><th>Period</th><th>Original Formula</th><th>Substituted Formula</th><th>Evaluated Value</th><th>Status</th></tr>' : "Periods:\n";

            for ($m = 1; $m <= 12; $m++) {
                $periodCode = "BP{$m}";
                $formula = $formulasByPeriod[$periodCode] ?? '';
                if (!$formula) {
                    echo $isBrowser ? '<tr><td>BP' . $m . '</td><td colspan="4">(no formula)</td></tr>' : "BP$m: (no formula)\n";
                    continue;
                }

                $original = $formula;

                // Inherit from previous calculation if flag is 'Y'
                if ($calcRow['ChildCalculationInherit'] === 'Y' && !empty($previousMonthly)) {
                    $formula = str_replace('@BP' . $m . 'InpN@', $previousMonthly["BP$m"] ?? 0, $formula);
                }

                // Fully dynamic substitution using tblVariableSources
                $formula = preg_replace_callback('/@([^@]+)@/', function ($match) use ($txRow, $conn, $context, $varSourceMap, $redis, $isBrowser, $calcRow, $scopeVersion, $periodCode, $options, &$metrics) {
                    $varNameOriginal = $match[1];
                    $varName = strtoupper($varNameOriginal);  // Normalize to uppercase for lookup

                    if (!isset($varSourceMap[$varName])) {
                        return $txRow[$varNameOriginal] ?? 0;
                    }

                    $source = $varSourceMap[$varName];

                    if ($source['SourceType'] === 'previous_calc') {
                        $calcName = $source['ReferencedCalcName'] ?? null;
                        if (!$calcName) {
                            return 0;
                        }

                        $sourceColumn = $source['SourceColumn'] ?? 'total';
                        $previousValueKey = buildScopedCacheKey($context, $scopeVersion, strtolower($calcName), $sourceColumn);
                        $value = redisGetWithMetrics($redis, $previousValueKey, $metrics);
                        return $value !== false ? $value : 0;
                    }

                    if ($source['SourceType'] === 'transaction') {
                        return $txRow[$varNameOriginal] ?? $source['DefaultValue'];
                    }

                    if ($source['SourceType'] === 'rates') {
                        // Use RateLookupCode from current calculation unless overridden by variable source.
                        $lookupKey = $source['LookupKeyColumn'] ?? null;
                        $rateCode = null;
                        if ($lookupKey !== null && $lookupKey !== '') {
                            if (isset($txRow[$lookupKey])) {
                                $rateCode = $txRow[$lookupKey];
                            } else {
                                $rateCode = $lookupKey;
                            }
                        }
                        if ($rateCode === null || $rateCode === '') {
                            $rateCode = $calcRow['RateLookupCode'] ?? null;
                        }
                        if (!$rateCode || strtoupper((string)$rateCode) === 'UOM' || strtoupper((string)$rateCode) === 'UOMCODE') {
                            $rateCode = $txRow['UOMCodeInpC'] ?? null;
                        }

                        if (!$rateCode) {
                            return $source['DefaultValue'];
                        }

                        $sourceTable = $source['SourceTable'] ?? 'tblRates';
                        $col = resolveRateColumnForPeriod($source['SourceColumn'] ?? null, $periodCode);
                        if (!$col) {
                            return $source['DefaultValue'];
                        }

                        $rateDataObject = $context['DataObjectCode'];
                        $ratesCacheKey = sprintf(
                            'calcmeta:ratehash:v%s:%s:%s:%s:%s:%s',
                            $scopeVersion,
                            strtolower($sourceTable),
                            $context['FiscalYearID'],
                            $context['VersionID'],
                            $rateDataObject,
                            strtolower((string)$rateCode)
                        );
                        $cachedRate = redisHGetWithMetrics($redis, $ratesCacheKey, $col, $metrics);
                        if ($cachedRate !== null && $cachedRate !== false && $cachedRate !== '') {
                            $value = $cachedRate;
                        } else {
                            $sql = "SELECT " . implode(', ', getRateColumns()) . " FROM {$sourceTable} 
                                    WHERE FiscalYearID = ? AND VersionID = ? 
                                      AND DataObjectCode = ? AND RateCode = ?";
                            $stmt = $conn->prepare($sql);
                            runStatement($stmt, [
                                $context['FiscalYearID'],
                                $context['VersionID'],
                                $rateDataObject,
                                $rateCode
                            ], $metrics);
                            $rateRow = $stmt->fetch(PDO::FETCH_ASSOC);
                            if (!$rateRow) {
                                // Fallback to DataObjectCode=0 when specific rate not found.
                                $rateDataObject = '0';
                                $ratesCacheKey = sprintf(
                                    'calcmeta:ratehash:v%s:%s:%s:%s:%s:%s',
                                    $scopeVersion,
                                    strtolower($sourceTable),
                                    $context['FiscalYearID'],
                                    $context['VersionID'],
                                    $rateDataObject,
                                    strtolower((string)$rateCode)
                                );
                                $stmt = $conn->prepare($sql);
                                runStatement($stmt, [
                                    $context['FiscalYearID'],
                                    $context['VersionID'],
                                    $rateDataObject,
                                    $rateCode
                                ], $metrics);
                                $rateRow = $stmt->fetch(PDO::FETCH_ASSOC);
                            }
                            if ($rateRow) {
                                $hashPayload = [];
                                foreach (getRateColumns() as $rateField) {
                                    $hashPayload[$rateField] = $rateRow[$rateField] ?? '';
                                }
                                if (!$options['skip_redis_writes']) {
                                    redisHmsetExWithMetrics($redis, $ratesCacheKey, $hashPayload, CACHE_META_TTL_SECONDS, $metrics);
                                }
                                $value = $rateRow[$col] ?? false;
                            } else {
                                $value = false;
                            }
                        }
                        return $value !== false ? $value : $source['DefaultValue'];
                    }

                    if ($source['SourceType'] === 'fixed') {
                        return $source['DefaultValue'];
                    }

                    return $source['DefaultValue'];
                }, $formula);

                $formula = trim($formula);
                $formula = ltrim($formula, '= ');

                try {
                    $value = $expr->evaluate($formula);
                    $value = round($value, 2);
                    $statusClass = 'success';
                    $statusText = 'Success';
                } catch (Exception $e) {
                    $value = 0;
                    $statusClass = 'error';
                    $statusText = 'Error: ' . $e->getMessage();
                }

                $currentMonthly[$periodCode] = $value;

                // Cache the FINAL calculated value
                $periodValueKey = buildScopedCacheKey($context, $scopeVersion, $keyName, $periodCode);
                if (!$options['skip_redis_writes']) {
                    redisSetExWithMetrics($redis, $periodValueKey, CACHE_TTL_SECONDS, $value, $metrics);
                }

                echo $isBrowser ? "<tr><td>BP$m</td><td class=\"value\">" . htmlspecialchars($original) . "</td><td class=\"value\">" . htmlspecialchars($formula) . "</td><td class=\"value $statusClass\">$value</td><td class=\"$statusClass\">$statusText</td></tr>" : "BP$m | Original: $original | Substituted: $formula | Value: $value | $statusText\n";
            }

                $currentTotal = round(array_sum($currentMonthly), 2);
                if (!$options['skip_redis_writes']) {
                    redisSetExWithMetrics($redis, buildScopedCacheKey($context, $scopeVersion, $keyName, 'total'), CACHE_TTL_SECONDS, $currentTotal, $metrics);
                    redisSetExWithMetrics($redis, buildScopedCacheKey($context, $scopeVersion, $keyName, 'computed_at'), CACHE_TTL_SECONDS, time(), $metrics);
                }

            echo $isBrowser ? '</table><h2>Total (BP1–BP12 Sum)</h2><p class="success">' . $currentTotal . '</p>' : "\nTotal: $currentTotal\n\n";
            }

            // Quarterly totals are always derived from monthly values.
            $quarterly = [
                'BPQ1' => ($currentMonthly['BP1'] ?? 0) + ($currentMonthly['BP2'] ?? 0) + ($currentMonthly['BP3'] ?? 0),
                'BPQ2' => ($currentMonthly['BP4'] ?? 0) + ($currentMonthly['BP5'] ?? 0) + ($currentMonthly['BP6'] ?? 0),
                'BPQ3' => ($currentMonthly['BP7'] ?? 0) + ($currentMonthly['BP8'] ?? 0) + ($currentMonthly['BP9'] ?? 0),
                'BPQ4' => ($currentMonthly['BP10'] ?? 0) + ($currentMonthly['BP11'] ?? 0) + ($currentMonthly['BP12'] ?? 0),
            ];

            // Out-years can be formula-driven (BPOY1..BPOY10); fallback to BP12 when missing.
            $outYears = [];
            for ($oy = 1; $oy <= 10; $oy++) {
                $periodCode = "BPOY{$oy}";
                $formula = $formulasByPeriod[$periodCode] ?? '';
                // Allow references to previous out-year values (e.g., @BPOY1@ in BPOY2)
                if ($formula !== '' && !empty($outYears)) {
                    foreach ($outYears as $prevCode => $prevValue) {
                        $formula = preg_replace('/@' . preg_quote($prevCode, '/') . '@/i', (string)$prevValue, $formula);
                    }
                }
                if ($formula !== '') {
                    $value = evaluateFormulaForPeriod(
                        $formula,
                        $periodCode,
                        $txRow,
                        $currentMonthly,
                        $currentTotal,
                        $varSourceMap,
                        $calcRow,
                        $context,
                        $scopeVersion,
                        $redis,
                        $conn,
                        $options,
                        $metrics
                    );
                    $outYears[$periodCode] = round($value, 2);
                } else {
                    $outYears[$periodCode] = $currentMonthly['BP12'] ?? 0;
                }

                if (!$options['skip_redis_writes']) {
                    $outYearValueKey = buildScopedCacheKey($context, $scopeVersion, $keyName, $periodCode);
                    redisSetExWithMetrics($redis, $outYearValueKey, CACHE_TTL_SECONDS, $outYears[$periodCode], $metrics);
                }
            }

            // Persist parent transaction result for the starting calculation.
            if (!$options['skip_db_writes'] && $currentCalcId == $startingCalcId) {
                insertTransactionResultRow(
                    $conn,
                    $transactionId,
                    $txRow,
                    $currentMonthly,
                    $currentTotal,
                    $quarterly,
                    $outYears,
                    $formulaVersion,
                    $runId,
                    $context,
                    $scopeVersion,
                    $engineVersion,
                    $startedAt,
                    $metrics
                );
                $parentCeilingDelta = getCeilingDeltaFromTxRow(
                    $txRow,
                    $currentMonthly,
                    $currentTotal
                );
                $ceilingMonthlyForCheck = $parentCeilingDelta['delta_monthly'];
                $ceilingTotalForCheck = $parentCeilingDelta['delta_total'];

                if ($options['enable_ceiling_redis_precheck'] && $options['ceiling_check_mode'] === 'per_transaction') {
                    try {
                        runRedisCeilingPrecheck(
                            $conn,
                            $redis,
                            $txRow,
                            $ceilingMonthlyForCheck,
                            $ceilingTotalForCheck,
                            $metrics,
                            $options['skip_redis_writes'],
                            !empty($options['force_refresh_ceiling_cache'])
                        );
                    } catch (Exception $e) {
                        if (isCeilingFailureMessage($e->getMessage())) {
                            $ceilingFailureOccurred = true;
                            $ceilingFailureMessages[] = $e->getMessage();
                            $lastCeilingCheckContext = [
                                'transaction_id' => (int)$transactionId,
                                'scope' => 'parent_precheck',
                                'engine' => ($options['ceiling_engine'] ?? 'sproc'),
                                'ceiling_definition_id' => 0,
                            ];
                            $resolvedDefId = resolveCeilingDefinitionIdForTxRow(
                                $conn,
                                $redis,
                                $txRow,
                                $metrics,
                                $options['skip_redis_writes'],
                                !empty($options['force_refresh_ceiling_cache'])
                            );
                            $lastCeilingCheckContext['ceiling_definition_id'] = $resolvedDefId;
                            markCeilingFailureOnTransaction(
                                $conn,
                                (int)$transactionId,
                                $e->getMessage(),
                                $metrics,
                                $resolvedDefId,
                                ($options['ceiling_engine'] ?? 'sproc')
                            );
                            markCeilingFailureOnHeadRecord(
                                $conn,
                                $headRecordId,
                                $e->getMessage(),
                                $metrics
                            );
                        } else {
                            throw $e;
                        }
                    }
                }
                addGroupAmounts($groupCeilingMonthly, $groupCeilingTotal, $currentMonthly, $currentTotal);

                if ($options['enable_ceiling_sproc_check'] && $options['ceiling_check_mode'] === 'per_transaction') {
                    $ceilingTxRow = $txRow;
                    $ceilingTxRow['_ceiling_check_mode'] = 'per_transaction';
                    $ceilingTxRow['_ceiling_period_enforcement'] = !empty($options['ceiling_period_enforcement']);
                    $preview = null;
                    $ceilingDefinitionIdForMark = 0;
                    if (empty($options['skip_ceiling_previews'])) {
                        $preview = getCeilingCheckPreview(
                            $conn,
                            $redis,
                            (int)$transactionId,
                            'per_transaction',
                            $metrics,
                            $options['skip_redis_writes'],
                            !empty($options['force_refresh_ceiling_cache'])
                        );
                        printCeilingCheckPreview($preview, !empty($options['ceiling_period_enforcement']) ? 'period' : 'total', $isBrowser);
                        $ceilingDefinitionIdForMark = (int)($preview['ceiling_definition_id'] ?? 0);
                    }
                    $lastCeilingCheckContext = [
                        'transaction_id' => (int)$transactionId,
                        'scope' => 'parent_per_transaction',
                        'engine' => ($options['ceiling_engine'] ?? 'sproc'),
                        'ceiling_definition_id' => $ceilingDefinitionIdForMark,
                    ];
                    $checkResult = null;
                    $checkOk = true;
                    try {
                        if (($options['ceiling_engine'] ?? 'sproc') === 'redis') {
                            $checkResult = runRedisCeilingCommitCheck(
                            $conn,
                            $redis,
                            $ceilingTxRow,
                            (int)$transactionId,
                            $ceilingMonthlyForCheck,
                            $ceilingTotalForCheck,
                            !empty($options['ceiling_period_enforcement']),
                            $metrics,
                            $options['skip_redis_writes'],
                                !empty($options['force_refresh_ceiling_cache'])
                            );
                        } else {
                            $checkResult = runCeilingSprocCheck(
                                $conn,
                                $ceilingTxRow,
                                $transactionId,
                                $metrics,
                                (int)($ceilingTxRow['UpdatedBy'] ?? $ceilingTxRow['CreatedBy'] ?? 1)
                            );
                        }
                    } catch (Exception $e) {
                        if (isCeilingFailureMessage($e->getMessage())) {
                            $checkOk = false;
                            $ceilingFailureOccurred = true;
                            $ceilingFailureMessages[] = $e->getMessage();
                            $statusText = "Ceiling Check Status: FAILED";
                            echo $isBrowser ? '<p class="error">' . htmlspecialchars($statusText) . '</p>' : $statusText . "\n";
                            if ((int)$ceilingDefinitionIdForMark <= 0) {
                                $ceilingDefinitionIdForMark = resolveCeilingDefinitionIdForTxRow(
                                    $conn,
                                    $redis,
                                    $ceilingTxRow,
                                    $metrics,
                                    $options['skip_redis_writes'],
                                    !empty($options['force_refresh_ceiling_cache'])
                                );
                            }
                            $lastCeilingCheckContext['ceiling_definition_id'] = $ceilingDefinitionIdForMark;
                            markCeilingFailureOnTransaction(
                                $conn,
                                (int)$transactionId,
                                $e->getMessage(),
                                $metrics,
                                $ceilingDefinitionIdForMark,
                                ($options['ceiling_engine'] ?? 'sproc')
                            );
                            markCeilingFailureOnHeadRecord(
                                $conn,
                                $headRecordId,
                                $e->getMessage(),
                                $metrics
                            );
                        } else {
                            throw $e;
                        }
                    }
                    if ($checkOk) {
                        $statusText = "Ceiling Check Status: " . ($checkResult['status'] ?? 'UNKNOWN');
                        echo $isBrowser ? '<p class="success">' . htmlspecialchars($statusText) . '</p>' : $statusText . "\n";
                        if ($ceilingDefinitionIdForMark === 0) {
                            $ceilingDefinitionIdForMark = (int)($checkResult['ceiling_definition_id'] ?? 0);
                        }
                        if ($ceilingDefinitionIdForMark === 0) {
                            $ceilingDefinitionIdForMark = resolveCeilingDefinitionIdForTxRow(
                                $conn,
                                $redis,
                                $ceilingTxRow,
                                $metrics,
                                $options['skip_redis_writes'],
                                !empty($options['force_refresh_ceiling_cache'])
                            );
                        }
                        $lastCeilingCheckContext['ceiling_definition_id'] = $ceilingDefinitionIdForMark;
                        markCeilingSuccessOnTransaction(
                            $conn,
                            (int)$transactionId,
                            $metrics,
                            $ceilingDefinitionIdForMark,
                            ($options['ceiling_engine'] ?? 'sproc')
                        );
                        markCeilingAppliedAmountsOnTransaction(
                            $conn,
                            (int)$transactionId,
                            $currentMonthly,
                            $currentTotal,
                            $metrics
                        );
                        if (($options['ceiling_engine'] ?? 'sproc') === 'redis' && $ceilingDefinitionIdForMark > 0) {
                            syncCeilingBalanceRowFromRedis(
                                $conn,
                                $redis,
                                $ceilingDefinitionIdForMark,
                                $metrics,
                                (int)($txRow['UpdatedBy'] ?? $txRow['CreatedBy'] ?? 1)
                            );
                        }
                        if (empty($options['skip_ceiling_previews'])) {
                            $postCommitPreview = getCeilingCheckPreview(
                                $conn,
                                $redis,
                                (int)$transactionId,
                                'per_transaction',
                                $metrics,
                                $options['skip_redis_writes'],
                                !empty($options['force_refresh_ceiling_cache']),
                                [
                                    'AmtBPTotal' => $currentTotal,
                                ],
                                'post-commit'
                            );
                            printCeilingCheckPreview($postCommitPreview, !empty($options['ceiling_period_enforcement']) ? 'period' : 'total', $isBrowser);
                        }
                    }
                }
            }

            // Generate child transaction if flag is set AND NOT the starting (parent) calc
            if (!$options['skip_db_writes'] && $calcRow['GenerateTransaction'] == 1 && $currentCalcId != $startingCalcId) {
                echo $isBrowser ? '<p class="success">Generating child transaction for ' . htmlspecialchars($calcRow['CalculationName']) . '</p>' : "Generating child transaction for {$calcRow['CalculationName']}\n";

                // Fetch dimensions from tblCalculations for this child calc ID
                $stmt = $conn->prepare("
                    SELECT UOMCodeDefault, DataObjectCode, TransactionTypeCode, GLAccountCode,
                           Segment1Code, Segment2Code, Segment3Code, Segment4Code, Segment5Code,
                           Segment6Code, Segment7Code, Segment8Code, Segment9Code, Segment10Code,
                           Segment11Code, Segment12Code, Segment13Code, Segment14Code, Segment15Code,
                           Segment16Code, Segment17Code, Segment18Code, Segment19Code, Segment20Code,
                           RateLookupCode
                    FROM tblCalculations WHERE CalculationID = ?
                ");
                runStatement($stmt, [$currentCalcId], $metrics);
                $dimensionsRow = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$dimensionsRow) {
                    throw new Exception("No dimensions found in tblCalculations for child CalculationID $currentCalcId");
                }

                $existingChild = null;
                $stmt = $conn->prepare("
                    SELECT TOP 1 *
                    FROM tblTransactionInput
                    WHERE HeadRecordID = ?
                      AND CalculationID = ?
                      AND RecordTypeCode = 'C'
                    ORDER BY TransactionID ASC
                ");
                runStatement($stmt, [$headRecordId, $currentCalcId], $metrics);
                $existingChild = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

                // Build child data - base off parent row and optionally preserve existing child ceiling-applied fields.
                $childData = $txRow;
                unset($childData['TransactionID']);
                foreach (array_keys($childData) as $field) {
                    // Internal runtime-only flags must never become DB column names.
                    if (isset($field[0]) && $field[0] === '_') {
                        unset($childData[$field]);
                    }
                }

                // Override child-specific fields using dimension rules
                $childData['HeadRecordID'] = $headRecordId;
                $childData['CalculationID'] = $currentCalcId;
                $childData['RecordTypeCode'] = 'C';

                // UOMCodeInpC: always from tblCalculations.UOMCodeDefault
                $childData['UOMCodeInpC'] = $dimensionsRow['UOMCodeDefault'] ?? $txRow['UOMCodeInpC'];  // fallback if missing

                // DataObjectCode / TransactionTypeCode / GLAccountCode:
                // if child calc has a non-empty, non-zero override, use it; otherwise inherit.
                $childData['DataObjectCode'] = ($dimensionsRow['DataObjectCode'] == 0 || $dimensionsRow['DataObjectCode'] === null || $dimensionsRow['DataObjectCode'] === '') ? $txRow['DataObjectCode'] : $dimensionsRow['DataObjectCode'];
                $childData['TransactionTypeCode'] = ($dimensionsRow['TransactionTypeCode'] == 0 || $dimensionsRow['TransactionTypeCode'] === null || $dimensionsRow['TransactionTypeCode'] === '') ? $txRow['TransactionTypeCode'] : $dimensionsRow['TransactionTypeCode'];
                $childData['GLAccountCode'] = ($dimensionsRow['GLAccountCode'] == 0 || $dimensionsRow['GLAccountCode'] === null || $dimensionsRow['GLAccountCode'] === '') ? $txRow['GLAccountCode'] : $dimensionsRow['GLAccountCode'];


                // Segment1Code to Segment20Code: same rule
                for ($s = 1; $s <= 20; $s++) {
                    $segmentField = "Segment{$s}Code";
                    $childData[$segmentField] = ($dimensionsRow[$segmentField] == 0 || $dimensionsRow[$segmentField] === null) ? $txRow[$segmentField] : $dimensionsRow[$segmentField];
                }

                // Populate computed monthly values
                for ($m = 1; $m <= 12; $m++) {
                    $childData["BP{$m}InpN"] = $currentMonthly["BP{$m}"] ?? 0;
                }
                $childData['BPTotalInpN'] = $currentTotal;

                // Reset unnecessary parent fields
                $resetFields = [
                    'PY5UOMRate', 'PY4UOMRate', 'PY3UOMRate', 'PY2UOMRate', 'PY1UOMRate',
                    'PY5QtyInpN', 'PY4QtyInpN', 'PY3QtyInpN', 'PY2QtyInpN', 'PY1QtyInpN',
                    'CeilingStatus', 'CeilingStatusCheck', 'CeilingErrorMessage', 'CeilingDefinitionID', 'CeilingEngine'
                ];
                foreach ($resetFields as $field) {
                    if (array_key_exists($field, $childData)) {
                        $childData[$field] = NULL;
                    }
                }
                if ($existingChild) {
                    for ($m = 1; $m <= 12; $m++) {
                        $k = 'CeilingAppliedBP' . $m;
                        if (array_key_exists($k, $childData)) {
                            $childData[$k] = $existingChild[$k] ?? null;
                        }
                    }
                    if (array_key_exists('CeilingAppliedTotal', $childData)) {
                        $childData['CeilingAppliedTotal'] = $existingChild['CeilingAppliedTotal'] ?? null;
                    }
                } else {
                    for ($m = 1; $m <= 12; $m++) {
                        $k = 'CeilingAppliedBP' . $m;
                        if (array_key_exists($k, $childData)) {
                            $childData[$k] = null;
                        }
                    }
                    if (array_key_exists('CeilingAppliedTotal', $childData)) {
                        $childData['CeilingAppliedTotal'] = null;
                    }
                }
                if (array_key_exists('CeilingFailedFlag', $childData)) {
                    $childData['CeilingFailedFlag'] = 0;
                }

                if ($existingChild) {
                    $childTxId = (int)$existingChild['TransactionID'];
                    $setClauses = [];
                    $updateValues = [];
                    foreach ($childData as $col => $val) {
                        $setClauses[] = "{$col} = ?";
                        $updateValues[] = $val;
                    }
                    $updateValues[] = $childTxId;
                    $sql = "UPDATE tblTransactionInput SET " . implode(', ', $setClauses) . " WHERE TransactionID = ?";
                    $stmt = $conn->prepare($sql);
                    runStatement($stmt, $updateValues, $metrics, 'write');
                } else {
                    // Build INSERT without TransactionID
                    $columns = implode(', ', array_keys($childData));
                    $placeholders = implode(', ', array_fill(0, count($childData), '?'));
                    $sql = "INSERT INTO tblTransactionInput ($columns) VALUES ($placeholders)";
                    $stmt = $conn->prepare($sql);
                    runStatement($stmt, array_values($childData), $metrics, 'write');
                    $childTxId = (int)$conn->lastInsertId();
                }
                $childData['TransactionID'] = $childTxId;
                $ceilingAnchorTxId = $childTxId;

                if ($existingChild) {
                    echo $isBrowser ? '<p class="success">Child transaction reused: ID ' . $childTxId . '</p>' : "Child transaction reused: ID $childTxId\n";
                } else {
                    echo $isBrowser ? '<p class="success">Child transaction created: ID ' . $childTxId . '</p>' : "Child transaction created: ID $childTxId\n";
                }

                insertTransactionResultRow(
                    $conn,
                    $childTxId,
                    $childData,
                    $currentMonthly,
                    $currentTotal,
                    $quarterly,
                    $outYears,
                    $formulaVersion,
                    $runId,
                    $context,
                    $scopeVersion,
                    $engineVersion,
                    $startedAt,
                    $metrics
                );
                $childCeilingDelta = getCeilingDeltaFromTxRow(
                    $childData,
                    $currentMonthly,
                    $currentTotal
                );
                $childCeilingMonthlyForCheck = $childCeilingDelta['delta_monthly'];
                $childCeilingTotalForCheck = $childCeilingDelta['delta_total'];

                if ($options['enable_ceiling_redis_precheck'] && $options['ceiling_check_mode'] === 'per_transaction') {
                    try {
                        runRedisCeilingPrecheck(
                            $conn,
                            $redis,
                            $childData,
                            $childCeilingMonthlyForCheck,
                            $childCeilingTotalForCheck,
                            $metrics,
                            $options['skip_redis_writes'],
                            !empty($options['force_refresh_ceiling_cache'])
                        );
                    } catch (Exception $e) {
                        if (isCeilingFailureMessage($e->getMessage())) {
                            $ceilingFailureOccurred = true;
                            $ceilingFailureMessages[] = $e->getMessage();
                            $lastCeilingCheckContext = [
                                'transaction_id' => (int)$childTxId,
                                'scope' => 'child_precheck',
                                'engine' => ($options['ceiling_engine'] ?? 'sproc'),
                                'ceiling_definition_id' => 0,
                            ];
                            $resolvedDefId = resolveCeilingDefinitionIdForTxRow(
                                $conn,
                                $redis,
                                $childData,
                                $metrics,
                                $options['skip_redis_writes'],
                                !empty($options['force_refresh_ceiling_cache'])
                            );
                            $lastCeilingCheckContext['ceiling_definition_id'] = $resolvedDefId;
                            markCeilingFailureOnTransaction(
                                $conn,
                                (int)$childTxId,
                                $e->getMessage(),
                                $metrics,
                                $resolvedDefId,
                                ($options['ceiling_engine'] ?? 'sproc')
                            );
                            markChildCeilingFailureOnHeadRecord(
                                $conn,
                                $headRecordId,
                                (int)$childTxId,
                                $e->getMessage(),
                                $metrics
                            );
                        } else {
                            throw $e;
                        }
                    }
                }
                addGroupAmounts($groupCeilingMonthly, $groupCeilingTotal, $currentMonthly, $currentTotal);

                if ($options['enable_ceiling_sproc_check'] && $options['ceiling_check_mode'] === 'per_transaction') {
                    $childData['_ceiling_check_mode'] = 'per_transaction';
                    $childData['_ceiling_period_enforcement'] = !empty($options['ceiling_period_enforcement']);
                    $preview = null;
                    $ceilingDefinitionIdForMark = 0;
                    if (empty($options['skip_ceiling_previews'])) {
                        $preview = getCeilingCheckPreview(
                            $conn,
                            $redis,
                            (int)$childTxId,
                            'per_transaction',
                            $metrics,
                            $options['skip_redis_writes'],
                            !empty($options['force_refresh_ceiling_cache'])
                        );
                        printCeilingCheckPreview($preview, !empty($options['ceiling_period_enforcement']) ? 'period' : 'total', $isBrowser);
                        $ceilingDefinitionIdForMark = (int)($preview['ceiling_definition_id'] ?? 0);
                    }
                    $lastCeilingCheckContext = [
                        'transaction_id' => (int)$childTxId,
                        'scope' => 'child_per_transaction',
                        'engine' => ($options['ceiling_engine'] ?? 'sproc'),
                        'ceiling_definition_id' => $ceilingDefinitionIdForMark,
                    ];
                    $checkResult = null;
                    $checkOk = true;
                    try {
                        if (($options['ceiling_engine'] ?? 'sproc') === 'redis') {
                            $checkResult = runRedisCeilingCommitCheck(
                            $conn,
                            $redis,
                            $childData,
                            (int)$childTxId,
                            $childCeilingMonthlyForCheck,
                            $childCeilingTotalForCheck,
                            !empty($options['ceiling_period_enforcement']),
                            $metrics,
                            $options['skip_redis_writes'],
                                !empty($options['force_refresh_ceiling_cache'])
                            );
                        } else {
                            $checkResult = runCeilingSprocCheck(
                                $conn,
                                $childData,
                                $childTxId,
                                $metrics,
                                (int)($childData['UpdatedBy'] ?? $childData['CreatedBy'] ?? 1)
                            );
                        }
                    } catch (Exception $e) {
                        if (isCeilingFailureMessage($e->getMessage())) {
                            $checkOk = false;
                            $ceilingFailureOccurred = true;
                            $ceilingFailureMessages[] = $e->getMessage();
                            $statusText = "Ceiling Check Status: FAILED";
                            echo $isBrowser ? '<p class="error">' . htmlspecialchars($statusText) . '</p>' : $statusText . "\n";
                            if ((int)$ceilingDefinitionIdForMark <= 0) {
                                $ceilingDefinitionIdForMark = resolveCeilingDefinitionIdForTxRow(
                                    $conn,
                                    $redis,
                                    $childData,
                                    $metrics,
                                    $options['skip_redis_writes'],
                                    !empty($options['force_refresh_ceiling_cache'])
                                );
                            }
                            $lastCeilingCheckContext['ceiling_definition_id'] = $ceilingDefinitionIdForMark;
                            markCeilingFailureOnTransaction(
                                $conn,
                                (int)$childTxId,
                                $e->getMessage(),
                                $metrics,
                                $ceilingDefinitionIdForMark,
                                ($options['ceiling_engine'] ?? 'sproc')
                            );
                            markChildCeilingFailureOnHeadRecord(
                                $conn,
                                $headRecordId,
                                (int)$childTxId,
                                $e->getMessage(),
                                $metrics
                            );
                        } else {
                            throw $e;
                        }
                    }
                    if ($checkOk) {
                        $statusText = "Ceiling Check Status: " . ($checkResult['status'] ?? 'UNKNOWN');
                        echo $isBrowser ? '<p class="success">' . htmlspecialchars($statusText) . '</p>' : $statusText . "\n";
                        if ($ceilingDefinitionIdForMark === 0) {
                            $ceilingDefinitionIdForMark = (int)($checkResult['ceiling_definition_id'] ?? 0);
                        }
                        if ($ceilingDefinitionIdForMark === 0) {
                            $ceilingDefinitionIdForMark = resolveCeilingDefinitionIdForTxRow(
                                $conn,
                                $redis,
                                $childData,
                                $metrics,
                                $options['skip_redis_writes'],
                                !empty($options['force_refresh_ceiling_cache'])
                            );
                        }
                        $lastCeilingCheckContext['ceiling_definition_id'] = $ceilingDefinitionIdForMark;
                        markCeilingSuccessOnTransaction(
                            $conn,
                            (int)$childTxId,
                            $metrics,
                            $ceilingDefinitionIdForMark,
                            ($options['ceiling_engine'] ?? 'sproc')
                        );
                        markCeilingAppliedAmountsOnTransaction(
                            $conn,
                            (int)$childTxId,
                            $currentMonthly,
                            $currentTotal,
                            $metrics
                        );
                        if (($options['ceiling_engine'] ?? 'sproc') === 'redis' && $ceilingDefinitionIdForMark > 0) {
                            syncCeilingBalanceRowFromRedis(
                                $conn,
                                $redis,
                                $ceilingDefinitionIdForMark,
                                $metrics,
                                (int)($childData['UpdatedBy'] ?? $childData['CreatedBy'] ?? 1)
                            );
                        }
                        if (empty($options['skip_ceiling_previews'])) {
                            $postCommitPreview = getCeilingCheckPreview(
                                $conn,
                                $redis,
                                (int)$childTxId,
                                'per_transaction',
                                $metrics,
                                $options['skip_redis_writes'],
                                !empty($options['force_refresh_ceiling_cache']),
                                [
                                    'AmtBPTotal' => $currentTotal,
                                ],
                                'post-commit'
                            );
                            printCeilingCheckPreview($postCommitPreview, !empty($options['ceiling_period_enforcement']) ? 'period' : 'total', $isBrowser);
                        }
                    }
                }
            }

            $previousMonthly = $currentMonthly;
            $currentCalcId = $calcRow['ChildCalculationID'] ?? null;
        }

        if (
            !$options['skip_db_writes'] &&
            $options['enable_ceiling_sproc_check'] &&
            $options['ceiling_check_mode'] === 'group_headrecord'
        ) {
            $anchorTxRow = $txRow;
            $anchorTxRow['_ceiling_check_mode'] = 'group_headrecord';
            $anchorTxRow['_ceiling_period_enforcement'] = !empty($options['ceiling_period_enforcement']);
            $postRunAmounts = [
                'AmtBPTotal' => $groupCeilingTotal,
            ];
            $preview = null;
            $ceilingDefinitionIdForMark = 0;
            if (empty($options['skip_ceiling_previews'])) {
                $preview = getCeilingCheckPreview(
                    $conn,
                    $redis,
                    (int)$ceilingAnchorTxId,
                    'group_headrecord',
                    $metrics,
                    $options['skip_redis_writes'],
                    !empty($options['force_refresh_ceiling_cache']),
                    $postRunAmounts,
                    'post-run'
                );
                printCeilingCheckPreview($preview, !empty($options['ceiling_period_enforcement']) ? 'period' : 'total', $isBrowser);
                $ceilingDefinitionIdForMark = (int)($preview['ceiling_definition_id'] ?? 0);
            }
            $lastCeilingCheckContext = [
                'transaction_id' => (int)$ceilingAnchorTxId,
                'scope' => 'group_headrecord',
                'engine' => ($options['ceiling_engine'] ?? 'sproc'),
                'ceiling_definition_id' => $ceilingDefinitionIdForMark,
            ];
            $checkResult = null;
            $checkOk = true;
            try {
                if (($options['ceiling_engine'] ?? 'sproc') === 'redis') {
                    $checkResult = runRedisCeilingCommitCheck(
                        $conn,
                        $redis,
                        $anchorTxRow,
                        (int)$ceilingAnchorTxId,
                        $groupCeilingMonthly,
                        $groupCeilingTotal,
                        !empty($options['ceiling_period_enforcement']),
                        $metrics,
                        $options['skip_redis_writes'],
                        !empty($options['force_refresh_ceiling_cache'])
                    );
                } else {
                    $checkResult = runCeilingSprocCheck(
                        $conn,
                        $anchorTxRow,
                        (int)$ceilingAnchorTxId,
                        $metrics,
                        (int)($txRow['UpdatedBy'] ?? $txRow['CreatedBy'] ?? 1)
                    );
                }
            } catch (Exception $e) {
                if (isCeilingFailureMessage($e->getMessage())) {
                    $checkOk = false;
                    $ceilingFailureOccurred = true;
                    $ceilingFailureMessages[] = $e->getMessage();
                    $statusText = "Ceiling Check Status: FAILED";
                    echo $isBrowser ? '<p class="error">' . htmlspecialchars($statusText) . '</p>' : $statusText . "\n";
                    $lastCeilingCheckContext['ceiling_definition_id'] = $ceilingDefinitionIdForMark;
                    markCeilingFailureOnTransaction(
                        $conn,
                        (int)$ceilingAnchorTxId,
                        $e->getMessage(),
                        $metrics,
                        $ceilingDefinitionIdForMark,
                        ($options['ceiling_engine'] ?? 'sproc')
                    );
                    markCeilingFailureOnHeadRecord(
                        $conn,
                        $headRecordId,
                        $e->getMessage(),
                        $metrics
                    );
                } else {
                    throw $e;
                }
            }
            if ($checkOk) {
                $statusText = "Ceiling Check Status: " . ($checkResult['status'] ?? 'UNKNOWN');
                echo $isBrowser ? '<p class="success">' . htmlspecialchars($statusText) . '</p>' : $statusText . "\n";
                if ($ceilingDefinitionIdForMark === 0) {
                    $ceilingDefinitionIdForMark = (int)($checkResult['ceiling_definition_id'] ?? 0);
                }
                if ($ceilingDefinitionIdForMark === 0) {
                    $ceilingDefinitionIdForMark = resolveCeilingDefinitionIdForTxRow(
                        $conn,
                        $redis,
                        $anchorTxRow,
                        $metrics,
                        $options['skip_redis_writes'],
                        !empty($options['force_refresh_ceiling_cache'])
                    );
                }
                $lastCeilingCheckContext['ceiling_definition_id'] = $ceilingDefinitionIdForMark;
                markCeilingSuccessOnTransaction(
                    $conn,
                    (int)$ceilingAnchorTxId,
                    $metrics,
                    $ceilingDefinitionIdForMark,
                    ($options['ceiling_engine'] ?? 'sproc')
                );
                if (($options['ceiling_engine'] ?? 'sproc') === 'redis' && $ceilingDefinitionIdForMark > 0) {
                    syncCeilingBalanceRowFromRedis(
                        $conn,
                        $redis,
                        $ceilingDefinitionIdForMark,
                        $metrics,
                        (int)($anchorTxRow['UpdatedBy'] ?? $anchorTxRow['CreatedBy'] ?? 1)
                    );
                }
                if (empty($options['skip_ceiling_previews'])) {
                    $postCommitPreview = getCeilingCheckPreview(
                        $conn,
                        $redis,
                        (int)$ceilingAnchorTxId,
                        'group_headrecord',
                        $metrics,
                        $options['skip_redis_writes'],
                        !empty($options['force_refresh_ceiling_cache']),
                        $postRunAmounts,
                        'post-commit'
                    );
                    printCeilingCheckPreview($postCommitPreview, !empty($options['ceiling_period_enforcement']) ? 'period' : 'total', $isBrowser);
                }
            }
        }

        $conn->commit();
        $metrics['duration_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        $metricsSummary = sprintf(
            "Metrics: duration=%dms, db_queries=%d (read=%d, write=%d), redis_get=%d, redis_set=%d, cache_hit=%d, cache_miss=%d",
            $metrics['duration_ms'],
            $metrics['db_query_count'],
            $metrics['db_queries_read'],
            $metrics['db_queries_write'],
            $metrics['redis_get_count'],
            $metrics['redis_set_count'],
            $metrics['cache_hit_count'],
            $metrics['cache_miss_count']
        );
        echo $isBrowser ? '<p><strong>' . htmlspecialchars($metricsSummary) . '</strong></p>' : $metricsSummary . "\n";
        echo $isBrowser ? '<p class="success">All changes committed successfully.</p>' : "All changes committed successfully.\n";
        if ($ceilingFailureOccurred) {
            $warningText = 'Ceiling failure occurred; records were saved and flagged.';
            echo $isBrowser ? '<p class="error">' . htmlspecialchars($warningText) . '</p>' : $warningText . "\n";
        }
        $result = [
            'ok' => true,
            'transaction_id' => $transactionId,
            'run_id' => $runId,
            'scope_version' => $scopeVersion,
            'metrics' => $metrics,
            'ceiling_failed' => $ceilingFailureOccurred,
            'ceiling_failure_messages' => $ceilingFailureMessages,
        ];

    } catch (Exception $e) {
        $conn->rollBack();
        $metrics['duration_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        $errorMessage = $e->getMessage();
        $isDeadlock = (strpos($errorMessage, 'deadlocked') !== false) || (strpos($errorMessage, 'SQLSTATE[40001]') !== false);
        if ($isDeadlock && $attempt <= $maxRetryAttempts) {
            $delayMs = (int)(200 * pow(2, max(0, $attempt - 1)));
            $delayMs += random_int(0, 150);
            usleep($delayMs * 1000);
            goto retry_tx;
        }
        $isCeilingFailure = (strpos($e->getMessage(), 'Ceiling check failed:') === 0) || (strpos($e->getMessage(), 'CEILING EXCEEDED') !== false);
        $failedTxId = (int)($lastCeilingCheckContext['transaction_id'] ?? $transactionId);
        $headRecordId = (int)((isset($txRow) && isset($txRow['HeadRecordID'])) ? $txRow['HeadRecordID'] : $transactionId);

        // Best-effort error audit row (outside main transaction) so failed runs are traceable.
        try {
            $errorResultJson = json_encode([
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
                'failed_transaction_id' => $failedTxId,
                'ceiling_failure' => $isCeilingFailure,
                'ceiling_context' => $lastCeilingCheckContext,
            ]);
            $formulaSetCode = $context['FormulaSetCode'] ?? 'BUDGET-FY26';
            $scopeVersionForError = $scopeVersion ?? 0;
            $stmt = $conn->prepare("DELETE FROM tblTransactionResultFlat WHERE TransactionID = ?");
            runStatement($stmt, [$failedTxId], $metrics, 'write');
            $stmt = $conn->prepare("DELETE FROM tblTransactionResult WHERE TransactionID = ?");
            runStatement($stmt, [$failedTxId], $metrics, 'write');

            $stmt = $conn->prepare("
                INSERT INTO tblTransactionResult (
                    TransactionID, ResultJSON, CalculatedDate, FormulaVersion,
                    RunID, FormulaSetCode, ScopeVersion, EngineVersion,
                    Status, DurationMs, BPTotal, ErrorMessage
                )
                VALUES (?, ?, GETDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            runStatement($stmt, [
                $failedTxId,
                $errorResultJson,
                $formulaVersion,
                $runId,
                $formulaSetCode,
                $scopeVersionForError,
                $engineVersion,
                ($isCeilingFailure ? 'CeilingFailed' : 'Error'),
                $metrics['duration_ms'],
                null,
                $e->getMessage()
            ], $metrics, 'write');

            if ($isCeilingFailure) {
                markCeilingFailureOnTransaction(
                    $conn,
                    $failedTxId,
                    $e->getMessage(),
                    $metrics,
                    (int)($lastCeilingCheckContext['ceiling_definition_id'] ?? 0),
                    ($lastCeilingCheckContext['engine'] ?? ($options['ceiling_engine'] ?? 'sproc'))
                );
                markCeilingFailureOnHeadRecord(
                    $conn,
                    $headRecordId,
                    $e->getMessage(),
                    $metrics,
                    (int)($lastCeilingCheckContext['ceiling_definition_id'] ?? 0),
                    ($lastCeilingCheckContext['engine'] ?? ($options['ceiling_engine'] ?? 'sproc'))
                );
            } else {
                markCeilingProcessingErrorOnTransaction(
                    $conn,
                    $failedTxId,
                    $e->getMessage(),
                    $metrics,
                    ($lastCeilingCheckContext['engine'] ?? ($options['ceiling_engine'] ?? 'sproc'))
                );
            }
        } catch (Exception $ignored) {
            // Do not mask the original calculation error if audit logging also fails.
        }

        $metricsSummary = sprintf(
            "Metrics: duration=%dms, db_queries=%d (read=%d, write=%d), redis_get=%d, redis_set=%d, cache_hit=%d, cache_miss=%d",
            $metrics['duration_ms'],
            $metrics['db_query_count'],
            $metrics['db_queries_read'],
            $metrics['db_queries_write'],
            $metrics['redis_get_count'],
            $metrics['redis_set_count'],
            $metrics['cache_hit_count'],
            $metrics['cache_miss_count']
        );
        echo $isBrowser ? '<p><strong>' . htmlspecialchars($metricsSummary) . '</strong></p>' : $metricsSummary . "\n";
        echo $isBrowser ? '<p class="error">Error: ' . htmlspecialchars($e->getMessage()) . '</p>' : "Error: " . $e->getMessage() . "\n";
        $result = [
            'ok' => false,
            'transaction_id' => $transactionId,
            'run_id' => $runId,
            'metrics' => $metrics,
            'error' => $e->getMessage(),
        ];
    }

    if ($isBrowser) echo '</body></html>';
    return $result;
}

function averageMetric($rows, $key) {
    if (empty($rows)) {
        return 0;
    }
    $sum = 0;
    foreach ($rows as $row) {
        $sum += $row[$key] ?? 0;
    }
    return $sum / count($rows);
}

function percentileMetric($rows, $key, $percentile) {
    if (empty($rows)) {
        return 0;
    }
    $values = [];
    foreach ($rows as $row) {
        $values[] = (float)($row[$key] ?? 0);
    }
    sort($values);
    $count = count($values);
    $rank = (int)ceil(($percentile / 100) * $count) - 1;
    $rank = max(0, min($rank, $count - 1));
    return $values[$rank];
}

function runBenchmark($transactionId, $runs, $invalidateFirstRun = true, $noWrites = false, $skipRedisWrites = false, $enableCeilingRedisPrecheck = false, $enableCeilingSprocCheck = false) {
    if ($runs < 1) {
        $runs = 1;
    }

    $runMetrics = [];
    for ($i = 1; $i <= $runs; $i++) {
        $options = [
            'invalidate_scope' => ($invalidateFirstRun && $i === 1),
            'skip_db_writes' => $noWrites,
            'skip_redis_writes' => $skipRedisWrites,
            'enable_ceiling_redis_precheck' => $enableCeilingRedisPrecheck,
            'enable_ceiling_sproc_check' => $enableCeilingSprocCheck,
        ];

        // Suppress verbose chain output and retain only benchmark summary output.
        ob_start();
        $result = processTransactionStub($transactionId, $options);
        ob_end_clean();

        if (!$result || empty($result['ok'])) {
            $error = $result['error'] ?? 'Unknown benchmark error';
            echo "Benchmark aborted on run {$i}: {$error}\n";
            return;
        }

        $runMetrics[] = $result['metrics'];
    }

    $cold = $runMetrics[0];
    $warm = array_slice($runMetrics, 1);

    echo "Benchmark Summary (tx={$transactionId}, runs={$runs})\n";
    echo "Cold run: duration={$cold['duration_ms']}ms, db_queries={$cold['db_query_count']} (read={$cold['db_queries_read']}, write={$cold['db_queries_write']}), redis_get={$cold['redis_get_count']}, redis_set={$cold['redis_set_count']}, cache_hit={$cold['cache_hit_count']}, cache_miss={$cold['cache_miss_count']}\n";

    if (!empty($warm)) {
        $warmAvgDuration = averageMetric($warm, 'duration_ms');
        $warmP95Duration = percentileMetric($warm, 'duration_ms', 95);
        $warmAvgDb = averageMetric($warm, 'db_query_count');
        $warmAvgDbRead = averageMetric($warm, 'db_queries_read');
        $warmAvgDbWrite = averageMetric($warm, 'db_queries_write');
        $warmAvgRedisGet = averageMetric($warm, 'redis_get_count');
        $warmAvgRedisSet = averageMetric($warm, 'redis_set_count');
        $warmAvgHit = averageMetric($warm, 'cache_hit_count');
        $warmAvgMiss = averageMetric($warm, 'cache_miss_count');

        echo sprintf(
            "Warm avg (%d runs): duration=%.2fms (p95=%.2fms), db_queries=%.2f (read=%.2f, write=%.2f), redis_get=%.2f, redis_set=%.2f, cache_hit=%.2f, cache_miss=%.2f\n",
            count($warm),
            $warmAvgDuration,
            $warmP95Duration,
            $warmAvgDb,
            $warmAvgDbRead,
            $warmAvgDbWrite,
            $warmAvgRedisGet,
            $warmAvgRedisSet,
            $warmAvgHit,
            $warmAvgMiss
        );
    }
}

function printRunIdSummary($runId) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT
            TransactionResultID,
            TransactionID,
            CalculatedDate,
            FormulaVersion,
            FormulaSetCode,
            ScopeVersion,
            EngineVersion,
            Status,
            DurationMs,
            BPTotal,
            ErrorMessage
        FROM tblTransactionResult
        WHERE RunID = ?
        ORDER BY CalculatedDate ASC, TransactionResultID ASC
    ");
    $stmt->execute([$runId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "RunID Summary: no rows found for {$runId}\n";
        return;
    }

    $successCount = 0;
    $errorCount = 0;
    $maxDuration = 0;
    foreach ($rows as $row) {
        if (($row['Status'] ?? '') === 'Success') {
            $successCount++;
        } elseif (($row['Status'] ?? '') === 'Error') {
            $errorCount++;
        }
        $duration = (int)($row['DurationMs'] ?? 0);
        if ($duration > $maxDuration) {
            $maxDuration = $duration;
        }
    }

    echo "RunID Summary ({$runId})\n";
    echo "Rows=" . count($rows) . ", Success={$successCount}, Error={$errorCount}, MaxDurationMs={$maxDuration}\n";
    foreach ($rows as $row) {
        $line = sprintf(
            "ResultID=%s TxID=%s Status=%s DurationMs=%s BPTotal=%s ScopeVersion=%s CalculatedDate=%s",
            $row['TransactionResultID'] ?? 'NULL',
            $row['TransactionID'] ?? 'NULL',
            $row['Status'] ?? 'NULL',
            $row['DurationMs'] ?? 'NULL',
            $row['BPTotal'] ?? 'NULL',
            $row['ScopeVersion'] ?? 'NULL',
            $row['CalculatedDate'] ?? 'NULL'
        );
        if (!empty($row['ErrorMessage'])) {
            $line .= " ErrorMessage=" . $row['ErrorMessage'];
        }
        echo $line . "\n";
    }
}

function syncCeilingBalancesFromRedis($limit = 0, $fy = null, $version = null, $updatedBy = 1) {
    global $conn;

    $startedAt = microtime(true);
    $redis = getRedis();
    $limit = max(0, (int)$limit);
    $updatedBy = (int)$updatedBy;

    $where = ["cd.ActiveFlag = 1", "cd.ApprovedFlag = 1"];
    $params = [];
    if ($fy !== null && (int)$fy > 0) {
        $where[] = "cd.FiscalYearID = ?";
        $params[] = (int)$fy;
    }
    if ($version !== null && (int)$version > 0) {
        $where[] = "cd.VersionID = ?";
        $params[] = (int)$version;
    }

    $topSql = $limit > 0 ? "TOP {$limit} " : "";
    $sql = "
        SELECT {$topSql}
            cd.CeilingDefinitionID,
            cd.FiscalYearID,
            cd.VersionID
        FROM dbo.tblCeilingDefinition cd
        WHERE " . implode(" AND ", $where) . "
        ORDER BY cd.CeilingDefinitionID ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $defs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updateSql = "
        UPDATE dbo.tblCeilingBalance
        SET
            BalanceBP1 = ?, BalanceBP2 = ?, BalanceBP3 = ?, BalanceBP4 = ?, BalanceBP5 = ?, BalanceBP6 = ?,
            BalanceBP7 = ?, BalanceBP8 = ?, BalanceBP9 = ?, BalanceBP10 = ?, BalanceBP11 = ?, BalanceBP12 = ?,
            BalanceBPTotal = ?, LastTransactionID = ?, UpdatedBy = ?, UpdatedDate = GETDATE()
        WHERE CeilingDefinitionID = ?
    ";
    $insertSql = "
        INSERT INTO dbo.tblCeilingBalance (
            CeilingDefinitionID, FiscalYearID, VersionID,
            BalanceBP1, BalanceBP2, BalanceBP3, BalanceBP4, BalanceBP5, BalanceBP6,
            BalanceBP7, BalanceBP8, BalanceBP9, BalanceBP10, BalanceBP11, BalanceBP12,
            BalanceBPTotal, LastTransactionID, UpdatedBy, UpdatedDate
        ) VALUES (
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, GETDATE()
        )
    ";
    $updateStmt = $conn->prepare($updateSql);
    $insertStmt = $conn->prepare($insertSql);

    $summary = [
        'definitions_scanned' => 0,
        'redis_found' => 0,
        'synced' => 0,
        'updated' => 0,
        'inserted' => 0,
        'skipped_no_redis' => 0,
        'errors' => 0,
        'elapsed_ms' => 0,
    ];

    $fields = [
        'bal_bp1', 'bal_bp2', 'bal_bp3', 'bal_bp4', 'bal_bp5', 'bal_bp6',
        'bal_bp7', 'bal_bp8', 'bal_bp9', 'bal_bp10', 'bal_bp11', 'bal_bp12',
        'bal_total', 'last_tx'
    ];

    foreach ($defs as $def) {
        $summary['definitions_scanned']++;
        $defId = (int)$def['CeilingDefinitionID'];
        $hash = $redis->hmget('ceiling:balance:v1:' . $defId, $fields);
        if (!is_array($hash) || $hash[0] === null) {
            $summary['skipped_no_redis']++;
            continue;
        }

        $summary['redis_found']++;
        $bp = [];
        for ($m = 1; $m <= 12; $m++) {
            $bp[] = (float)($hash[$m - 1] ?? 0);
        }
        $balTotal = (float)($hash[12] ?? 0);
        $lastTxRaw = $hash[13] ?? null;
        $lastTx = ($lastTxRaw === null || $lastTxRaw === '') ? null : (int)$lastTxRaw;

        try {
            $updateStmt->execute([
                $bp[0], $bp[1], $bp[2], $bp[3], $bp[4], $bp[5],
                $bp[6], $bp[7], $bp[8], $bp[9], $bp[10], $bp[11],
                $balTotal, $lastTx, $updatedBy, $defId
            ]);

            if ($updateStmt->rowCount() > 0) {
                $summary['updated']++;
            } else {
                $insertStmt->execute([
                    $defId,
                    (int)$def['FiscalYearID'],
                    (int)$def['VersionID'],
                    $bp[0], $bp[1], $bp[2], $bp[3], $bp[4], $bp[5],
                    $bp[6], $bp[7], $bp[8], $bp[9], $bp[10], $bp[11],
                    $balTotal,
                    $lastTx,
                    $updatedBy
                ]);
                $summary['inserted']++;
            }
            $summary['synced']++;
        } catch (Throwable $e) {
            $summary['errors']++;
        }
    }

    $summary['elapsed_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
    return $summary;
}

function listRedisCeilingBalances($limit = 500) {
    $redis = getRedis();
    $limit = max(1, (int)$limit);
    $pattern = 'ceiling:balance:v1:*';
    $keys = [];

    if (method_exists($redis, 'scan')) {
        $cursor = 0;
        do {
            $result = $redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 500]);
            if (is_array($result) && count($result) === 2) {
                $cursor = (int)$result[0];
                $batch = is_array($result[1]) ? $result[1] : [];
                foreach ($batch as $k) {
                    $keys[] = (string)$k;
                    if (count($keys) >= $limit) {
                        break 2;
                    }
                }
            } else {
                break;
            }
        } while ($cursor !== 0);
    } else {
        $found = $redis->keys($pattern);
        $keys = array_slice(is_array($found) ? $found : [], 0, $limit);
    }

    sort($keys, SORT_NATURAL);
    $rows = [];
    foreach ($keys as $key) {
        $parts = explode(':', $key);
        $defId = (int)end($parts);
        $hash = $redis->hmget($key, [
            'bal_total', 'ceil_total', 'last_tx',
            'bal_bp1', 'bal_bp2', 'bal_bp3', 'bal_bp4', 'bal_bp5', 'bal_bp6',
            'bal_bp7', 'bal_bp8', 'bal_bp9', 'bal_bp10', 'bal_bp11', 'bal_bp12',
        ]);
        $ttl = (int)$redis->ttl($key);
        $rows[] = [
            'key' => $key,
            'ceiling_definition_id' => $defId,
            'bal_total' => isset($hash[0]) ? (float)$hash[0] : 0.0,
            'ceil_total' => isset($hash[1]) ? (float)$hash[1] : 0.0,
            'last_tx' => ($hash[2] ?? '') !== '' ? (int)$hash[2] : null,
            'ttl' => $ttl,
            'bp' => [
                isset($hash[3]) ? (float)$hash[3] : 0.0,
                isset($hash[4]) ? (float)$hash[4] : 0.0,
                isset($hash[5]) ? (float)$hash[5] : 0.0,
                isset($hash[6]) ? (float)$hash[6] : 0.0,
                isset($hash[7]) ? (float)$hash[7] : 0.0,
                isset($hash[8]) ? (float)$hash[8] : 0.0,
                isset($hash[9]) ? (float)$hash[9] : 0.0,
                isset($hash[10]) ? (float)$hash[10] : 0.0,
                isset($hash[11]) ? (float)$hash[11] : 0.0,
                isset($hash[12]) ? (float)$hash[12] : 0.0,
                isset($hash[13]) ? (float)$hash[13] : 0.0,
                isset($hash[14]) ? (float)$hash[14] : 0.0,
            ],
        ];
    }

    return [
        'limit' => $limit,
        'returned' => count($rows),
        'rows' => $rows,
    ];
}

function globalResetBalancesToCeilings($updatedBy = 1) {
    global $conn;

    $startedAt = microtime(true);
    $updatedBy = (int)$updatedBy;
    $summary = [
        'updated_rows' => 0,
        'inserted_rows' => 0,
        'deleted_redis_balance_keys' => 0,
        'deleted_redis_snapshot_keys' => 0,
        'errors' => 0,
        'elapsed_ms' => 0,
    ];

    try {
        $conn->beginTransaction();

        $updateSql = "
            UPDATE cb
            SET
                cb.BalanceBP1 = cd.CeilingBP1,
                cb.BalanceBP2 = cd.CeilingBP2,
                cb.BalanceBP3 = cd.CeilingBP3,
                cb.BalanceBP4 = cd.CeilingBP4,
                cb.BalanceBP5 = cd.CeilingBP5,
                cb.BalanceBP6 = cd.CeilingBP6,
                cb.BalanceBP7 = cd.CeilingBP7,
                cb.BalanceBP8 = cd.CeilingBP8,
                cb.BalanceBP9 = cd.CeilingBP9,
                cb.BalanceBP10 = cd.CeilingBP10,
                cb.BalanceBP11 = cd.CeilingBP11,
                cb.BalanceBP12 = cd.CeilingBP12,
                cb.BalanceBPTotal = cd.CeilingBPTotal,
                cb.LastTransactionID = NULL,
                cb.UpdatedBy = ?,
                cb.UpdatedDate = GETDATE()
            FROM dbo.tblCeilingBalance cb
            INNER JOIN dbo.tblCeilingDefinition cd
                ON cd.CeilingDefinitionID = cb.CeilingDefinitionID
            WHERE cd.ActiveFlag = 1
              AND cd.ApprovedFlag = 1
        ";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$updatedBy]);
        $summary['updated_rows'] = (int)$updateStmt->rowCount();

        $insertSql = "
            INSERT INTO dbo.tblCeilingBalance (
                CeilingDefinitionID, FiscalYearID, VersionID,
                BalanceBP1, BalanceBP2, BalanceBP3, BalanceBP4, BalanceBP5, BalanceBP6,
                BalanceBP7, BalanceBP8, BalanceBP9, BalanceBP10, BalanceBP11, BalanceBP12,
                BalanceBPTotal, LastTransactionID, UpdatedBy, UpdatedDate
            )
            SELECT
                cd.CeilingDefinitionID, cd.FiscalYearID, cd.VersionID,
                cd.CeilingBP1, cd.CeilingBP2, cd.CeilingBP3, cd.CeilingBP4, cd.CeilingBP5, cd.CeilingBP6,
                cd.CeilingBP7, cd.CeilingBP8, cd.CeilingBP9, cd.CeilingBP10, cd.CeilingBP11, cd.CeilingBP12,
                cd.CeilingBPTotal, NULL, ?, GETDATE()
            FROM dbo.tblCeilingDefinition cd
            LEFT JOIN dbo.tblCeilingBalance cb
                ON cb.CeilingDefinitionID = cd.CeilingDefinitionID
            WHERE cb.CeilingDefinitionID IS NULL
              AND cd.ActiveFlag = 1
              AND cd.ApprovedFlag = 1
        ";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->execute([$updatedBy]);
        $summary['inserted_rows'] = (int)$insertStmt->rowCount();

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $summary['errors']++;
    }

    try {
        $redis = getRedis();
        $patterns = ['ceiling:balance:v1:*', 'ceiling:snapshot:*'];
        foreach ($patterns as $pattern) {
            $deleted = 0;
            if (method_exists($redis, 'scan')) {
                $cursor = 0;
                do {
                    $scan = $redis->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 500]);
                    if (!is_array($scan) || count($scan) !== 2) {
                        break;
                    }
                    $cursor = (int)$scan[0];
                    $keys = is_array($scan[1]) ? $scan[1] : [];
                    if (!empty($keys)) {
                        $deleted += (int)$redis->del($keys);
                    }
                } while ($cursor !== 0);
            } else {
                $keys = $redis->keys($pattern);
                if (is_array($keys) && !empty($keys)) {
                    $deleted += (int)$redis->del($keys);
                }
            }
            if ($pattern === 'ceiling:balance:v1:*') {
                $summary['deleted_redis_balance_keys'] = $deleted;
            } else {
                $summary['deleted_redis_snapshot_keys'] = $deleted;
            }
        }
    } catch (Throwable $e) {
        $summary['errors']++;
    }

    $summary['elapsed_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
    return $summary;
}

function getBulkTransactionIds($limit = 0, $headOnly = false, $transactionTypeCode = null, $minId = null, $maxId = null, $shardIndex = null, $shardCount = null, $fiscalYearId = null, $versionId = null, $ceilingStatusCheck = null) {
    global $conn;
    $limit = max(0, (int)$limit);
    $topSql = $limit > 0 ? "TOP {$limit} " : '';
    $whereParts = [];
    $params = [];
    if ($fiscalYearId !== null && (int)$fiscalYearId > 0) {
        $whereParts[] = "FiscalYearID = :fy";
        $params[':fy'] = (int)$fiscalYearId;
    }
    if ($versionId !== null && (int)$versionId > 0) {
        $whereParts[] = "VersionID = :ver";
        $params[':ver'] = (int)$versionId;
    } else {
        $whereParts[] = "VersionID = 5";
    }
    if ($transactionTypeCode !== null && $transactionTypeCode !== '') {
        $whereParts[] = "TransactionTypeCode = :transactionTypeCode";
        $params[':transactionTypeCode'] = $transactionTypeCode;
    }
    if ($ceilingStatusCheck !== null && $ceilingStatusCheck !== '') {
        $whereParts[] = "CeilingStatusCheck = :ceilingStatusCheck";
        $params[':ceilingStatusCheck'] = $ceilingStatusCheck;
    }
    if ($headOnly) {
        $whereParts[] = "(HeadRecordID IS NULL OR HeadRecordID = TransactionID)";
    }
    if ($minId !== null) {
        $whereParts[] = "TransactionID >= :minId";
        $params[':minId'] = (int)$minId;
    }
    if ($maxId !== null) {
        $whereParts[] = "TransactionID <= :maxId";
        $params[':maxId'] = (int)$maxId;
    }
    if ($shardIndex !== null && $shardCount !== null && $shardCount > 0) {
        $whereParts[] = "(TransactionID % :shardCount) = :shardIndex";
        $params[':shardCount'] = (int)$shardCount;
        $params[':shardIndex'] = (int)$shardIndex;
    }
    $whereSql = "WHERE " . implode(" AND ", $whereParts);
    $sql = "SELECT {$topSql}TransactionID FROM tblTransactionInput {$whereSql} ORDER BY TransactionID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return array_map('intval', $rows ?: []);
}

function runBulkTransactionSave($processOptions, $limit = 0, $headOnly = false, $captureLogs = false, $transactionTypeCode = null, $minId = null, $maxId = null, $shardIndex = null, $shardCount = null, $fiscalYearId = null, $versionId = null, $ceilingStatusCheck = null) {
    $txIds = getBulkTransactionIds($limit, $headOnly, $transactionTypeCode, $minId, $maxId, $shardIndex, $shardCount, $fiscalYearId, $versionId, $ceilingStatusCheck);
    $startedAt = microtime(true);
    $results = [];
    $okCount = 0;
    $errorCount = 0;
    $errorSummary = [];
    $aggregateMetrics = [
        'duration_ms' => 0,
        'db_query_count' => 0,
        'db_queries_read' => 0,
        'db_queries_write' => 0,
        'redis_get_count' => 0,
        'redis_set_count' => 0,
        'cache_hit_count' => 0,
        'cache_miss_count' => 0,
        'ceiling_snapshot_count' => 0,
        'ceiling_snapshot_ms' => 0,
        'ceiling_precheck_count' => 0,
        'ceiling_precheck_ms' => 0,
        'ceiling_final_sproc_count' => 0,
        'ceiling_final_sproc_ms' => 0,
        'ceiling_final_redis_count' => 0,
        'ceiling_final_redis_ms' => 0,
        'ceiling_sync_back_count' => 0,
        'ceiling_sync_back_ms' => 0,
    ];
    $totalCount = count($txIds);
    $progressEvery = 100;

    if ($captureLogs) {
        echo "Bulk Init | Total: {$totalCount}\n";
    }

    foreach ($txIds as $idx => $txId) {
        if ($captureLogs) {
            ob_start();
        }
        $result = processTransactionStub($txId, array_merge($processOptions, [
            'force_text_output' => true,
            'show_action_buttons' => false,
        ]));
        if ($captureLogs) {
            // Swallow verbose per-transaction debug output during bulk runs.
            ob_get_clean();
        }

        if (!empty($result['ok'])) {
            $okCount++;
        } else {
            $errorCount++;
            $err = $result['error'] ?? 'Unknown error';
            if (!isset($errorSummary[$err])) {
                $errorSummary[$err] = 0;
            }
            $errorSummary[$err]++;
        }

        $results[] = [
            'transaction_id' => $txId,
            'ok' => !empty($result['ok']),
            'duration_ms' => (int)($result['metrics']['duration_ms'] ?? 0),
            'error' => $result['error'] ?? null,
        ];
        foreach (array_keys($aggregateMetrics) as $metricKey) {
            $aggregateMetrics[$metricKey] += (int)($result['metrics'][$metricKey] ?? 0);
        }

        if ($captureLogs) {
            $processedCount = $idx + 1;
            if (
                $processedCount === 1 ||
                $processedCount === $totalCount ||
                ($processedCount % $progressEvery) === 0
            ) {
                echo "Bulk Progress | Processed: {$processedCount} / {$totalCount} | Success: {$okCount} | Failed: {$errorCount}\n";
            }
        }
    }

    return [
        'transaction_ids' => $txIds,
        'total' => $totalCount,
        'ok' => $okCount,
        'error' => $errorCount,
        'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
        'aggregate_metrics' => $aggregateMetrics,
        'results' => $results,
        'error_summary' => $errorSummary,
    ];
}

// --------------------------------------------------
// Run it!
// --------------------------------------------------

$testTxId = 2;  // CHANGE TO YOUR REAL PARENT ID
$txId = $testTxId;
$invalidateScope = false;
$benchmarkMode = false;
$benchmarkRuns = 10;
$benchmarkNoWrites = false;
$benchmarkSkipRedisWrites = false;
$enableCeilingRedisPrecheck = true;
$enableCeilingSprocCheck = false;
$ceilingEngine = 'redis'; // redis | sproc
$ceilingCheckMode = 'per_transaction';
$ceilingEnforcement = 'period'; // period | total
$refreshCeilingCache = false;
$reseedCeilingState = false;
$runIdSummary = null;
$bulkRunCli = false;
$bulkLimitCli = 0;
$bulkHeadOnlyCli = false;
$bulkIncludePreviewsCli = false;
$bulkTransactionTypeCli = null;
$bulkCeilingStatusCheckCli = null;
$warmupCacheCli = false;
$warmupFyCli = null;
$warmupVersionCli = null;
$warmupTxTypeCli = null;
$bulkMinIdCli = null;
$bulkMaxIdCli = null;
$bulkShardIndexCli = null;
$bulkShardCountCli = null;
$retryDeadlocksCli = 0;
$bulkFyCli = null;
$bulkVersionCli = null;
$batchModeOnCli = false;
$batchModeOffCli = false;
$batchSavePolicyCli = '900 1 300 1000 60 100000';
$batchRunAllCli = false;
$batchRunFyCli = null;
$batchRunVersionCli = null;
$batchRunShardsCli = 4;
$batchRunRetryDeadlocksCli = 5;

if (php_sapi_name() === 'cli') {
    $args = array_slice($argv, 1);
    foreach ($args as $arg) {
        if ($arg === '--invalidate-scope') {
            $invalidateScope = true;
        } elseif ($arg === '--refresh-ceiling-cache') {
            $refreshCeilingCache = true;
        } elseif ($arg === '--reseed-ceiling-state') {
            $reseedCeilingState = true;
        } elseif ($arg === '--benchmark') {
            $benchmarkMode = true;
        } elseif ($arg === '--benchmark-no-writes') {
            $benchmarkNoWrites = true;
        } elseif ($arg === '--benchmark-skip-redis-writes') {
            $benchmarkSkipRedisWrites = true;
        } elseif ($arg === '--bulk-run') {
            $bulkRunCli = true;
        } elseif ($arg === '--bulk-head-only') {
            $bulkHeadOnlyCli = true;
        } elseif ($arg === '--bulk-include-previews') {
            $bulkIncludePreviewsCli = true;
        } elseif ($arg === '--bulk-esal') {
            $bulkRunCli = true;
            $bulkTransactionTypeCli = 'ESAL';
            $bulkHeadOnlyCli = true;
        } elseif (strpos($arg, '--bulk-limit=') === 0) {
            $bulkLimitCli = (int)substr($arg, 13);
        } elseif (strpos($arg, '--bulk-transaction-type=') === 0) {
            $bulkTransactionTypeCli = strtoupper(trim(substr($arg, 24)));
        } elseif (strpos($arg, '--bulk-ceiling-status-check=') === 0) {
            $bulkCeilingStatusCheckCli = trim(substr($arg, 28));
        } elseif ($arg === '--warmup-cache') {
            $warmupCacheCli = true;
        } elseif (strpos($arg, '--warmup-fy=') === 0) {
            $warmupFyCli = (int)substr($arg, 12);
        } elseif (strpos($arg, '--warmup-version=') === 0) {
            $warmupVersionCli = (int)substr($arg, 17);
        } elseif (strpos($arg, '--warmup-transaction-type=') === 0) {
            $warmupTxTypeCli = strtoupper(trim(substr($arg, 27)));
        } elseif (strpos($arg, '--bulk-min-id=') === 0) {
            $bulkMinIdCli = (int)substr($arg, 14);
        } elseif (strpos($arg, '--bulk-max-id=') === 0) {
            $bulkMaxIdCli = (int)substr($arg, 14);
        } elseif (strpos($arg, '--bulk-shard-index=') === 0) {
            $bulkShardIndexCli = (int)substr($arg, 19);
        } elseif (strpos($arg, '--bulk-shard-count=') === 0) {
            $bulkShardCountCli = (int)substr($arg, 19);
        } elseif (strpos($arg, '--retry-deadlocks=') === 0) {
            $retryDeadlocksCli = (int)substr($arg, 19);
        } elseif (strpos($arg, '--bulk-fy=') === 0) {
            $bulkFyCli = (int)substr($arg, 10);
        } elseif (strpos($arg, '--bulk-version=') === 0) {
            $bulkVersionCli = (int)substr($arg, 15);
        } elseif ($arg === '--batch-mode-on') {
            $batchModeOnCli = true;
        } elseif ($arg === '--batch-mode-off') {
            $batchModeOffCli = true;
        } elseif (strpos($arg, '--batch-save-policy=') === 0) {
            $batchSavePolicyCli = trim(substr($arg, 20));
        } elseif ($arg === '--batch-run-all') {
            $batchRunAllCli = true;
        } elseif (strpos($arg, '--batch-run-fy=') === 0) {
            $batchRunFyCli = (int)substr($arg, 15);
        } elseif (strpos($arg, '--batch-run-version=') === 0) {
            $batchRunVersionCli = (int)substr($arg, 20);
        } elseif (strpos($arg, '--batch-run-shards=') === 0) {
            $batchRunShardsCli = max(1, (int)substr($arg, 19));
        } elseif (strpos($arg, '--batch-run-retry=') === 0) {
            $batchRunRetryDeadlocksCli = max(0, (int)substr($arg, 18));
        } elseif ($arg === '--ceiling-precheck' || $arg === '--ceiling-precheck=1') {
            $enableCeilingRedisPrecheck = true;
        } elseif ($arg === '--ceiling-precheck=0' || $arg === '--no-ceiling-precheck') {
            $enableCeilingRedisPrecheck = false;
        } elseif ($arg === '--ceiling-sproc-check') {
            $enableCeilingSprocCheck = true;
        } elseif ($arg === '--no-ceiling-check' || $arg === '--ceiling-sproc-check=0') {
            $enableCeilingSprocCheck = false;
        } elseif (strpos($arg, '--ceiling-check-mode=') === 0) {
            $ceilingCheckMode = strtolower(trim(substr($arg, 21)));
        } elseif (strpos($arg, '--ceiling-engine=') === 0) {
            $ceilingEngine = strtolower(trim(substr($arg, 17)));
        } elseif (strpos($arg, '--ceiling-enforcement=') === 0) {
            $ceilingEnforcement = strtolower(trim(substr($arg, 22)));
        } elseif (strpos($arg, '--tx=') === 0) {
            $txId = (int)substr($arg, 5);
        } elseif (strpos($arg, '--runs=') === 0) {
            $benchmarkRuns = (int)substr($arg, 7);
        } elseif (strpos($arg, '--runid-summary=') === 0) {
            $runIdSummary = substr($arg, 16);
        }
    }

    if ($bulkRunCli) {
        // Safety: never process child records in bulk unless explicitly requested.
        if (!$bulkHeadOnlyCli) {
            $bulkHeadOnlyCli = true;
        }
        $bulkResults = runBulkTransactionSave([
            'force_refresh_ceiling_cache' => $refreshCeilingCache,
            'force_reseed_ceiling_state' => $reseedCeilingState,
            'ceiling_check_mode' => $ceilingCheckMode,
            'ceiling_enforcement' => $ceilingEnforcement,
            'enable_ceiling_redis_precheck' => $enableCeilingRedisPrecheck,
            'enable_ceiling_sproc_check' => $enableCeilingSprocCheck,
            'ceiling_engine' => $ceilingEngine,
            'skip_ceiling_previews' => !$bulkIncludePreviewsCli,
            'invalidate_scope' => $invalidateScope,
            'retry_deadlocks' => $retryDeadlocksCli,
        ], $bulkLimitCli, $bulkHeadOnlyCli, true, $bulkTransactionTypeCli, $bulkMinIdCli, $bulkMaxIdCli, $bulkShardIndexCli, $bulkShardCountCli, $bulkFyCli, $bulkVersionCli, $bulkCeilingStatusCheckCli);

        $typeText = $bulkTransactionTypeCli ? $bulkTransactionTypeCli : 'ALL';
        $statusText = $bulkCeilingStatusCheckCli ? $bulkCeilingStatusCheckCli : 'ALL';
        echo "Bulk Summary (type={$typeText}, ceiling_status={$statusText})\n";
        echo "Total: " . (int)$bulkResults['total'] .
            " | Success: " . (int)$bulkResults['ok'] .
            " | Failed: " . (int)$bulkResults['error'] .
            " | Elapsed: " . (int)$bulkResults['duration_ms'] . " ms\n";
        $bulkMetrics = $bulkResults['aggregate_metrics'] ?? [];
        $avgTxMs = (int)round(((int)($bulkMetrics['duration_ms'] ?? 0)) / max(1, (int)$bulkResults['total']));
        echo "Bulk Metrics | TxAvgMs: {$avgTxMs}" .
            " | CeilingSnapshotMs: " . (int)($bulkMetrics['ceiling_snapshot_ms'] ?? 0) .
            " | CeilingPrecheckMs: " . (int)($bulkMetrics['ceiling_precheck_ms'] ?? 0) .
            " | CeilingFinalSprocMs: " . (int)($bulkMetrics['ceiling_final_sproc_ms'] ?? 0) .
            " | CeilingFinalRedisMs: " . (int)($bulkMetrics['ceiling_final_redis_ms'] ?? 0) .
            " | CeilingSyncBackMs: " . (int)($bulkMetrics['ceiling_sync_back_ms'] ?? 0) .
            " | DbQueries: " . (int)($bulkMetrics['db_query_count'] ?? 0) .
            " | RedisGets: " . (int)($bulkMetrics['redis_get_count'] ?? 0) .
            " | RedisSets: " . (int)($bulkMetrics['redis_set_count'] ?? 0) . "\n";
        if (!empty($bulkResults['error_summary'])) {
            arsort($bulkResults['error_summary']);
            echo "Top Errors:\n";
            $top = array_slice($bulkResults['error_summary'], 0, 10, true);
            foreach ($top as $msg => $count) {
                echo "- {$count} | {$msg}\n";
            }
        }
        exit;
    }

    if ($batchModeOnCli || $batchModeOffCli) {
        $redis = getRedis();
        $backupKey = 'batchmode:redis:save:backup';
        if ($batchModeOnCli) {
            $currentSave = $redis->config('GET', 'save');
            $currentSaveValue = is_array($currentSave) ? ($currentSave['save'] ?? null) : null;
            if ($currentSaveValue !== null) {
                $redis->set($backupKey, $currentSaveValue);
            }
            $redis->config('SET', 'save', $batchSavePolicyCli);
            echo "Batch mode ON. Redis save policy set to: {$batchSavePolicyCli}\n";
        } else {
            $backupValue = $redis->get($backupKey);
            if ($backupValue !== null && $backupValue !== false && $backupValue !== '') {
                $redis->config('SET', 'save', $backupValue);
                echo "Batch mode OFF. Redis save policy restored to: {$backupValue}\n";
            } else {
                echo "Batch mode OFF requested, but no backup save policy found.\n";
            }
        }
        exit;
    }

    if ($batchRunAllCli) {
        $redis = getRedis();
        $backupKey = 'batchmode:redis:save:backup';
        $currentSave = $redis->config('GET', 'save');
        $currentSaveValue = is_array($currentSave) ? ($currentSave['save'] ?? null) : null;
        if ($currentSaveValue !== null) {
            $redis->set($backupKey, $currentSaveValue);
        }
        $redis->config('SET', 'save', $batchSavePolicyCli);
        echo "Batch mode ON. Redis save policy set to: {$batchSavePolicyCli}\n";

        $fy = $batchRunFyCli > 0 ? $batchRunFyCli : 0;
        $ver = $batchRunVersionCli > 0 ? $batchRunVersionCli : 0;
        $shards = $batchRunShardsCli;
        $retry = $batchRunRetryDeadlocksCli;

        $overallStart = microtime(true);
        for ($i = 0; $i < $shards; $i++) {
            $results = runBulkTransactionSave([
                'force_refresh_ceiling_cache' => $refreshCeilingCache,
                'force_reseed_ceiling_state' => $reseedCeilingState,
                'ceiling_check_mode' => $ceilingCheckMode,
                'ceiling_enforcement' => $ceilingEnforcement,
                'enable_ceiling_redis_precheck' => $enableCeilingRedisPrecheck,
                'enable_ceiling_sproc_check' => $enableCeilingSprocCheck,
                'ceiling_engine' => $ceilingEngine,
                'skip_ceiling_previews' => true,
                'invalidate_scope' => $invalidateScope,
                'retry_deadlocks' => $retry,
            ], $bulkLimitCli, true, true, $bulkTransactionTypeCli, $bulkMinIdCli, $bulkMaxIdCli, $i, $shards, $fy, $ver, $bulkCeilingStatusCheckCli);

            echo "Shard {$i}/" . ($shards - 1) . " | Total: {$results['total']} | Success: {$results['ok']} | Failed: {$results['error']} | Elapsed: {$results['duration_ms']} ms\n";
        }
        $elapsed = (int)round((microtime(true) - $overallStart) * 1000);
        echo "Batch run complete. Total elapsed: {$elapsed} ms\n";

        $backupValue = $redis->get($backupKey);
        if ($backupValue !== null && $backupValue !== false && $backupValue !== '') {
            $redis->config('SET', 'save', $backupValue);
            echo "Batch mode OFF. Redis save policy restored to: {$backupValue}\n";
        } else {
            echo "Batch mode OFF requested, but no backup save policy found.\n";
        }
        exit;
    }

    if ($warmupCacheCli) {
        $fy = $warmupFyCli > 0 ? $warmupFyCli : 0;
        $ver = $warmupVersionCli > 0 ? $warmupVersionCli : 0;
        $txType = $warmupTxTypeCli ?: null;
        $redis = getRedis();
        $startedAt = microtime(true);

        $where = [];
        $params = [];
        if ($fy > 0) {
            $where[] = "FiscalYearID = ?";
            $params[] = $fy;
        }
        if ($ver > 0) {
            $where[] = "VersionID = ?";
            $params[] = $ver;
        }
        if ($txType) {
            $where[] = "TransactionTypeCode = ?";
            $params[] = $txType;
        }
        $whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

        $stmt = $conn->prepare("SELECT DISTINCT FiscalYearID, VersionID FROM tblCalculations {$whereSql}");
        $stmt->execute($params);
        $contexts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalCalcs = 0;
        $totalFormulas = 0;
        $totalRates = 0;
        foreach ($contexts as $ctx) {
            $fyCtx = (int)$ctx['FiscalYearID'];
            $verCtx = (int)$ctx['VersionID'];
            $context = [
                'FormulaSetCode' => 'BUDGET-FY26',
                'FiscalYearID' => $fyCtx,
                'VersionID' => $verCtx,
                'DataObjectCode' => null,
                'TxCacheSig' => 'warmup',
            ];
            $scopeVersion = getOrInitScopeVersion($redis, $context);

            // Variable sources
            $varSourcesCacheKey = "calcmeta:varsources:active:v{$scopeVersion}";
            $cachedVarSources = redisGetWithMetrics($redis, $varSourcesCacheKey, $metrics);
            if ($cachedVarSources === null) {
                $stmt = $conn->prepare("SELECT VariableName, SourceType, SourceTable, SourceColumn, LookupKeyColumn, DefaultValue, ReferencedCalcName FROM tblVariableSources WHERE ActiveFlag = 1");
                $stmt->execute();
                $varSources = $stmt->fetchAll(PDO::FETCH_ASSOC);
                redisSetExWithMetrics($redis, $varSourcesCacheKey, CACHE_META_TTL_SECONDS, json_encode($varSources), $metrics);
            }

            // Calculations + formulas
            $stmt = $conn->prepare("SELECT CalculationID FROM tblCalculations WHERE FiscalYearID = ? AND VersionID = ?");
            $stmt->execute([$fyCtx, $verCtx]);
            $calcIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($calcIds as $calcId) {
                $calcId = (int)$calcId;
                $calcRowCacheKey = cacheKeyCalcRow($scopeVersion, $fyCtx, $calcId);
                $cachedCalcRow = redisGetWithMetrics($redis, $calcRowCacheKey, $metrics);
                if ($cachedCalcRow === null) {
                    $stmt2 = $conn->prepare("SELECT * FROM tblCalculations WHERE FiscalYearID = ? AND CalculationID = ?");
                    $stmt2->execute([$fyCtx, $calcId]);
                    $calcRow = $stmt2->fetch(PDO::FETCH_ASSOC);
                    if ($calcRow) {
                        redisSetExWithMetrics($redis, $calcRowCacheKey, CACHE_META_TTL_SECONDS, json_encode($calcRow), $metrics);
                        $totalCalcs++;
                    }
                }

                $formulasCacheKey = cacheKeyFormulaMap($scopeVersion, $fyCtx, $calcId);
                $cachedFormulas = redisGetWithMetrics($redis, $formulasCacheKey, $metrics);
                if ($cachedFormulas === null) {
                    $stmt3 = $conn->prepare("SELECT PeriodCode, FormulaText FROM tblCalculationFormulas WHERE FiscalYearID = ? AND CalculationID = ? AND ActiveFlag = 1 ORDER BY PeriodCode");
                    $stmt3->execute([$fyCtx, $calcId]);
                    $rows = $stmt3->fetchAll(PDO::FETCH_ASSOC);
                    $map = [];
                    foreach ($rows as $row) {
                        $map[$row['PeriodCode']] = $row['FormulaText'];
                    }
                    redisSetExWithMetrics($redis, $formulasCacheKey, CACHE_META_TTL_SECONDS, json_encode($map), $metrics);
                    $totalFormulas += count($map);
                }
            }

            // Rates warmup for UOMs in inputs
            $rateStmt = $conn->prepare("
                SELECT DISTINCT ti.UOMCodeInpC
                FROM tblTransactionInput ti
                WHERE ti.FiscalYearID = ? AND ti.VersionID = ?
            ");
            $rateStmt->execute([$fyCtx, $verCtx]);
            $uoms = $rateStmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($uoms as $uom) {
                if (!$uom) {
                    continue;
                }
                $rateDataObject = '0';
                $ratesCacheKey = sprintf(
                    'calcmeta:ratehash:v%s:%s:%s:%s:%s:%s',
                    $scopeVersion,
                    'tblrates',
                    $fyCtx,
                    $verCtx,
                    $rateDataObject,
                    strtolower((string)$uom)
                );
                $cached = redisHGetWithMetrics($redis, $ratesCacheKey, 'BP1Rate', $metrics);
                if ($cached === null || $cached === false || $cached === '') {
                    $stmt4 = $conn->prepare("SELECT " . implode(', ', getRateColumns()) . " FROM tblRates WHERE FiscalYearID = ? AND VersionID = ? AND DataObjectCode = ? AND RateCode = ?");
                    $stmt4->execute([$fyCtx, $verCtx, $rateDataObject, $uom]);
                    $rateRow = $stmt4->fetch(PDO::FETCH_ASSOC);
                    if ($rateRow) {
                        redisHmsetExWithMetrics($redis, $ratesCacheKey, $rateRow, CACHE_META_TTL_SECONDS, $metrics);
                        $totalRates++;
                    }
                }
            }
        }

        $elapsed = (int)round((microtime(true) - $startedAt) * 1000);
        echo "Warmup Summary\n";
        echo "Contexts: " . count($contexts) . " | Calculations cached: {$totalCalcs} | Formula entries cached: {$totalFormulas} | Rates cached: {$totalRates} | Elapsed: {$elapsed} ms\n";
        exit;
    }

    if ($runIdSummary !== null && $runIdSummary !== '') {
        printRunIdSummary($runIdSummary);
    } elseif ($benchmarkMode) {
        runBenchmark(
            $txId,
            $benchmarkRuns,
            true,
            $benchmarkNoWrites,
            $benchmarkSkipRedisWrites,
            $enableCeilingRedisPrecheck,
            $enableCeilingSprocCheck
        );
    } else {
        processTransactionStub($txId, [
            'invalidate_scope' => $invalidateScope,
            'force_refresh_ceiling_cache' => $refreshCeilingCache,
            'force_reseed_ceiling_state' => $reseedCeilingState,
            'enable_ceiling_redis_precheck' => $enableCeilingRedisPrecheck,
            'enable_ceiling_sproc_check' => $enableCeilingSprocCheck,
            'ceiling_engine' => $ceilingEngine,
            'ceiling_check_mode' => $ceilingCheckMode,
            'ceiling_period_enforcement' => ($ceilingEnforcement !== 'total'),
        ]);
    }
} else {
    $hasTxParam = isset($_GET['tx']) && trim((string)$_GET['tx']) !== '';
    $bulkRun = isset($_GET['bulk_run']) && $_GET['bulk_run'] === '1';
    $bulkLimit = isset($_GET['bulk_limit']) ? max(0, (int)$_GET['bulk_limit']) : 0;
    $bulkHeadOnly = isset($_GET['bulk_head_only']) && $_GET['bulk_head_only'] === '1';
    $bulkIncludePreviews = isset($_GET['bulk_include_previews']) && $_GET['bulk_include_previews'] === '1';
    $globalResetBalances = isset($_GET['global_reset_balances']) && $_GET['global_reset_balances'] === '1';
    $syncCeilingBalances = isset($_GET['sync_ceiling_balances']) && $_GET['sync_ceiling_balances'] === '1';
    $warmupCeilingCache = isset($_GET['warmup_ceiling_cache']) && $_GET['warmup_ceiling_cache'] === '1';
    $viewRedisBalances = isset($_GET['view_redis_balances']) && $_GET['view_redis_balances'] === '1';
    $redisBalanceLimit = isset($_GET['redis_balance_limit']) ? max(1, (int)$_GET['redis_balance_limit']) : 500;
    $syncLimit = isset($_GET['sync_limit']) ? max(0, (int)$_GET['sync_limit']) : 0;
    $syncFy = isset($_GET['sync_fy']) ? (int)$_GET['sync_fy'] : null;
    $syncVersion = isset($_GET['sync_version']) ? (int)$_GET['sync_version'] : null;
    $warmupFy = isset($_GET['warmup_fy']) ? (int)$_GET['warmup_fy'] : null;
    $warmupVersion = isset($_GET['warmup_version']) ? (int)$_GET['warmup_version'] : null;
    $warmupTtl = isset($_GET['warmup_ttl']) ? max(1, (int)$_GET['warmup_ttl']) : 86400;
    $warmupDryRun = isset($_GET['warmup_dry_run']) && $_GET['warmup_dry_run'] === '1';

    if ($hasTxParam) {
        $txId = (int)$_GET['tx'];
    }
    if (isset($_GET['invalidate_scope']) && $_GET['invalidate_scope'] === '1') {
        $invalidateScope = true;
    }
    if (isset($_GET['refresh_ceiling_cache']) && $_GET['refresh_ceiling_cache'] === '1') {
        $refreshCeilingCache = true;
    }
    if (isset($_GET['reseed_ceiling_state']) && $_GET['reseed_ceiling_state'] === '1') {
        $reseedCeilingState = true;
    }
    if (isset($_GET['ceiling_precheck'])) {
        if ($_GET['ceiling_precheck'] === '1') {
            $enableCeilingRedisPrecheck = true;
        } elseif ($_GET['ceiling_precheck'] === '0') {
            $enableCeilingRedisPrecheck = false;
        }
    }
    if (isset($_GET['ceiling_sproc_check'])) {
        if ($_GET['ceiling_sproc_check'] === '1') {
            $enableCeilingSprocCheck = true;
        } elseif ($_GET['ceiling_sproc_check'] === '0') {
            $enableCeilingSprocCheck = false;
        }
    }
    if (isset($_GET['ceiling_check_mode']) && $_GET['ceiling_check_mode'] !== '') {
        $ceilingCheckMode = strtolower(trim($_GET['ceiling_check_mode']));
    }
    if (isset($_GET['ceiling_engine']) && $_GET['ceiling_engine'] !== '') {
        $ceilingEngine = strtolower(trim($_GET['ceiling_engine']));
    }
    if (isset($_GET['ceiling_enforcement']) && $_GET['ceiling_enforcement'] !== '') {
        $ceilingEnforcement = strtolower(trim($_GET['ceiling_enforcement']));
    }
    if (isset($_GET['runid_summary']) && $_GET['runid_summary'] !== '') {
        printRunIdSummary($_GET['runid_summary']);
    } elseif ($globalResetBalances) {
        $resetSummary = globalResetBalancesToCeilings(1);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><title>' . htmlspecialchars(\App\Shared\Lang::translateLiteral('Global Reset Balances to Ceilings'), ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f7fa;line-height:1.5;}';
        echo 'h1{color:#1f2937;}table{border-collapse:collapse;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.08);}';
        echo 'th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;}th{background:#1f2937;color:#fff;}';
        echo '.btn{display:inline-block;padding:10px 14px;background:#1f2937;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;}</style></head><body>';
        echo '<h1>Global Reset Balances to Ceilings</h1>';
        echo '<p><a class="btn" href="?tx=' . htmlspecialchars((string)$txId, ENT_QUOTES, 'UTF-8') . '">Back to Transaction View</a></p>';
        echo '<table>';
        echo '<tr><th>Metric</th><th>Value</th></tr>';
        echo '<tr><td>DB rows updated</td><td>' . (int)$resetSummary['updated_rows'] . '</td></tr>';
        echo '<tr><td>DB rows inserted</td><td>' . (int)$resetSummary['inserted_rows'] . '</td></tr>';
        echo '<tr><td>Redis balance keys deleted</td><td>' . (int)$resetSummary['deleted_redis_balance_keys'] . '</td></tr>';
        echo '<tr><td>Redis snapshot keys deleted</td><td>' . (int)$resetSummary['deleted_redis_snapshot_keys'] . '</td></tr>';
        echo '<tr><td>Errors</td><td>' . (int)$resetSummary['errors'] . '</td></tr>';
        echo '<tr><td>Elapsed (ms)</td><td>' . (int)$resetSummary['elapsed_ms'] . '</td></tr>';
        echo '</table>';
        echo '<p style="margin-top:14px;">Next step: click <strong>Warmup Ceiling Cache Now</strong> to repopulate Redis from reset DB balances.</p>';
        echo '</body></html>';
    } elseif ($viewRedisBalances) {
        $redisBalances = listRedisCeilingBalances($redisBalanceLimit);
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><title>' . htmlspecialchars(\App\Shared\Lang::translateLiteral('Redis Ceiling Balances'), ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f7fa;line-height:1.5;}';
        echo 'h1{color:#1f2937;}table{width:100%;border-collapse:collapse;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.08);}';
        echo 'th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:13px;}th{background:#1f2937;color:#fff;}';
        echo '.btn{display:inline-block;padding:10px 14px;background:#1f2937;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;}';
        echo '.mono{font-family:Consolas,monospace;}</style></head><body>';
        echo '<h1>Redis Ceiling Balances</h1>';
        echo '<p><a class="btn" href="?tx=' . htmlspecialchars((string)$txId, ENT_QUOTES, 'UTF-8') . '">Back to Transaction View</a></p>';
        echo '<p><strong>Rows returned:</strong> ' . (int)$redisBalances['returned'] . ' (limit ' . (int)$redisBalances['limit'] . ')</p>';
        echo '<table><tr><th>CeilingDefinitionID</th><th>Balance Total</th><th>Ceiling Total</th><th>Last Tx</th><th>TTL (sec)</th><th>BP1..BP12 Balances</th><th>Redis Key</th></tr>';
        foreach ($redisBalances['rows'] as $row) {
            $bpText = implode(', ', array_map(static function ($v) { return (string)round((float)$v, 2); }, $row['bp']));
            echo '<tr>';
            echo '<td>' . (int)$row['ceiling_definition_id'] . '</td>';
            echo '<td>' . round((float)$row['bal_total'], 2) . '</td>';
            echo '<td>' . round((float)$row['ceil_total'], 2) . '</td>';
            echo '<td>' . (($row['last_tx'] !== null) ? (int)$row['last_tx'] : '-') . '</td>';
            echo '<td>' . (int)$row['ttl'] . '</td>';
            echo '<td class="mono">' . htmlspecialchars($bpText, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="mono">' . htmlspecialchars((string)$row['key'], ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</table></body></html>';
    } elseif ($warmupCeilingCache) {
        $warmupSummary = runCeilingCacheWarmup([
            'fy' => ($warmupFy !== null && $warmupFy > 0) ? $warmupFy : null,
            'version' => ($warmupVersion !== null && $warmupVersion > 0) ? $warmupVersion : null,
            'ttl' => $warmupTtl,
            'dry_run' => $warmupDryRun,
            'verbose' => false,
        ]);

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><title>' . htmlspecialchars(\App\Shared\Lang::translateLiteral('Ceiling Cache Warmup Results'), ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f7fa;line-height:1.5;}';
        echo 'h1{color:#1f2937;}table{border-collapse:collapse;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.08);}';
        echo 'th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;}th{background:#1f2937;color:#fff;}';
        echo '.btn{display:inline-block;padding:10px 14px;background:#1f2937;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;}</style></head><body>';
        echo '<h1>Ceiling Cache Warmup Results</h1>';
        echo '<p><a class="btn" href="?tx=' . htmlspecialchars((string)$txId, ENT_QUOTES, 'UTF-8') . '">Back to Transaction View</a></p>';
        echo '<table>';
        echo '<tr><th>Metric</th><th>Value</th></tr>';
        echo '<tr><td>Dry run</td><td>' . (!empty($warmupSummary['dry_run']) ? 'yes' : 'no') . '</td></tr>';
        echo '<tr><td>Filter FiscalYear</td><td>' . (($warmupSummary['fy'] ?? null) !== null ? (int)$warmupSummary['fy'] : 'all') . '</td></tr>';
        echo '<tr><td>Filter Version</td><td>' . (($warmupSummary['version'] ?? null) !== null ? (int)$warmupSummary['version'] : 'all') . '</td></tr>';
        echo '<tr><td>TTL (seconds)</td><td>' . (int)($warmupSummary['ttl'] ?? 0) . '</td></tr>';
        echo '<tr><td>Signatures processed</td><td>' . (int)($warmupSummary['signatures_processed'] ?? 0) . '</td></tr>';
        echo '<tr><td>Signatures unmatched</td><td>' . (int)($warmupSummary['signatures_unmatched'] ?? 0) . '</td></tr>';
        echo '<tr><td>Snapshot keys written</td><td>' . (int)($warmupSummary['snapshot_keys_written'] ?? 0) . '</td></tr>';
        echo '<tr><td>Balance keys written</td><td>' . (int)($warmupSummary['balance_keys_written'] ?? 0) . '</td></tr>';
        echo '<tr><td>Elapsed (ms)</td><td>' . (int)($warmupSummary['elapsed_ms'] ?? 0) . '</td></tr>';
        echo '</table></body></html>';
    } elseif ($syncCeilingBalances) {
        $syncSummary = syncCeilingBalancesFromRedis(
            $syncLimit,
            ($syncFy !== null && $syncFy > 0) ? $syncFy : null,
            ($syncVersion !== null && $syncVersion > 0) ? $syncVersion : null,
            1
        );

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><title>' . htmlspecialchars(\App\Shared\Lang::translateLiteral('Ceiling Balance Sync Results'), ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f7fa;line-height:1.5;}';
        echo 'h1{color:#1f2937;}table{border-collapse:collapse;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.08);}';
        echo 'th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;}th{background:#1f2937;color:#fff;}';
        echo '.btn{display:inline-block;padding:10px 14px;background:#1f2937;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;}</style></head><body>';
        echo '<h1>Ceiling Balance Sync Results</h1>';
        echo '<p><a class="btn" href="?tx=' . htmlspecialchars((string)$txId, ENT_QUOTES, 'UTF-8') . '">Back to Transaction View</a></p>';
        echo '<table>';
        echo '<tr><th>Metric</th><th>Value</th></tr>';
        echo '<tr><td>Definitions scanned</td><td>' . (int)$syncSummary['definitions_scanned'] . '</td></tr>';
        echo '<tr><td>Redis balances found</td><td>' . (int)$syncSummary['redis_found'] . '</td></tr>';
        echo '<tr><td>Rows synced</td><td>' . (int)$syncSummary['synced'] . '</td></tr>';
        echo '<tr><td>Rows updated</td><td>' . (int)$syncSummary['updated'] . '</td></tr>';
        echo '<tr><td>Rows inserted</td><td>' . (int)$syncSummary['inserted'] . '</td></tr>';
        echo '<tr><td>Skipped (no Redis key)</td><td>' . (int)$syncSummary['skipped_no_redis'] . '</td></tr>';
        echo '<tr><td>Errors</td><td>' . (int)$syncSummary['errors'] . '</td></tr>';
        echo '<tr><td>Elapsed (ms)</td><td>' . (int)$syncSummary['elapsed_ms'] . '</td></tr>';
        echo '<tr><td>Filter FiscalYear</td><td>' . (($syncFy !== null && $syncFy > 0) ? (int)$syncFy : 'all') . '</td></tr>';
        echo '<tr><td>Filter Version</td><td>' . (($syncVersion !== null && $syncVersion > 0) ? (int)$syncVersion : 'all') . '</td></tr>';
        echo '<tr><td>Limit</td><td>' . ($syncLimit > 0 ? (int)$syncLimit : 'none') . '</td></tr>';
        echo '</table></body></html>';
    } elseif ($bulkRun) {
        $bulkResults = runBulkTransactionSave([
            'invalidate_scope' => $invalidateScope,
            // Never reseed/reset during bulk save execution.
            'force_refresh_ceiling_cache' => false,
            'force_reseed_ceiling_state' => false,
            'enable_ceiling_redis_precheck' => $enableCeilingRedisPrecheck,
            'enable_ceiling_sproc_check' => $enableCeilingSprocCheck,
            'ceiling_engine' => $ceilingEngine,
            'ceiling_check_mode' => $ceilingCheckMode,
            'ceiling_period_enforcement' => ($ceilingEnforcement !== 'total'),
            'skip_ceiling_previews' => !$bulkIncludePreviews,
        ], $bulkLimit, $bulkHeadOnly, true);

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><title>' . htmlspecialchars(\App\Shared\Lang::translateLiteral('Bulk Transaction Save Results'), ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f7fa;line-height:1.5;}';
        echo 'h1,h2{color:#2c3e50;}table{width:100%;border-collapse:collapse;margin:20px 0;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,0.08);}';
        echo 'th,td{padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:left;}th{background:#1f2937;color:#fff;}';
        echo '.ok{color:#16a34a;font-weight:bold;}.err{color:#dc2626;font-weight:bold;}';
        echo '.btn{display:inline-block;padding:10px 14px;background:#1f2937;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;}';
        echo 'details{margin:8px 0;}pre{white-space:pre-wrap;background:#111827;color:#e5e7eb;padding:12px;border-radius:6px;}</style></head><body>';
        echo '<h1>Bulk Transaction Save Results</h1>';
        echo '<p><a class="btn" href="?tx=' . htmlspecialchars((string)$txId, ENT_QUOTES, 'UTF-8') . '">Back to Single Transaction View</a></p>';
        echo '<p><strong>Total rows processed:</strong> ' . (int)$bulkResults['total'] .
            ' | <span class="ok">Success: ' . (int)$bulkResults['ok'] . '</span>' .
            ' | <span class="err">Failed: ' . (int)$bulkResults['error'] . '</span>' .
            ' | <strong>Elapsed:</strong> ' . (int)$bulkResults['duration_ms'] . ' ms</p>';
        echo '<p><strong>Mode:</strong> ' . ($bulkHeadOnly ? 'Head records only' : 'All rows in tblTransactionInput') .
            ' | <strong>Limit:</strong> ' . ($bulkLimit > 0 ? (int)$bulkLimit : 'none') . '</p>';

        echo '<table><tr><th>TransactionID</th><th>Status</th><th>Duration (ms)</th><th>Error</th></tr>';
        foreach ($bulkResults['results'] as $row) {
            $statusText = $row['ok'] ? '<span class="ok">OK</span>' : '<span class="err">FAILED</span>';
            $errorText = htmlspecialchars((string)($row['error'] ?? ''), ENT_QUOTES, 'UTF-8');
            echo '<tr><td>' . (int)$row['transaction_id'] . '</td><td>' . $statusText . '</td><td>' . (int)$row['duration_ms'] . '</td><td>' . $errorText . '</td></tr>';
        }
        echo '</table>';

        echo '</body></html>';
    } elseif ($hasTxParam) {
        processTransactionStub($txId, [
            'invalidate_scope' => $invalidateScope,
            'force_refresh_ceiling_cache' => $refreshCeilingCache,
            'force_reseed_ceiling_state' => $reseedCeilingState,
            'enable_ceiling_redis_precheck' => $enableCeilingRedisPrecheck,
            'enable_ceiling_sproc_check' => $enableCeilingSprocCheck,
            'ceiling_engine' => $ceilingEngine,
            'ceiling_check_mode' => $ceilingCheckMode,
            'ceiling_period_enforcement' => ($ceilingEnforcement !== 'total'),
        ]);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8') . '"><head><meta charset="UTF-8"><title>' . htmlspecialchars(\App\Shared\Lang::translateLiteral('Calculation Chain Tools'), ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;background:#f5f7fa;line-height:1.6;}';
        echo 'h1{color:#1f2937;}p{color:#374151;}';
        echo '.btn{display:inline-block;margin:6px 8px 6px 0;padding:10px 14px;background:#1f2937;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold;}';
        echo '.btn:hover{background:#111827;}';
        echo '.card{background:#fff;padding:18px;border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,0.08);}</style></head><body>';
        echo '<h1>Calculation Chain Tools</h1>';
        echo '<div class="card">';
        echo '<p>No <strong>tx</strong> parameter was provided. Use the actions below, or open with <code>?tx=123</code> to process a specific transaction.</p>';
        echo '<p>';
        echo '<a class="btn" href="?global_reset_balances=1" onclick="return confirm(\'Global reset will set all active/approved balances equal to ceilings and clear Redis ceiling keys. Continue?\');">Global Reset Balances to Ceilings</a>';
        echo '<a class="btn" href="?warmup_ceiling_cache=1" onclick="return confirm(\'Warm Redis ceiling snapshot/balance cache now?\');">Warmup Ceiling Cache Now</a>';
        echo '<a class="btn" href="?sync_ceiling_balances=1" onclick="return confirm(\'Sync Redis ceiling balances to tblCeilingBalance now?\');">Sync Ceiling Balances Now</a>';
        echo '<a class="btn" href="?view_redis_balances=1">View Redis Balances</a>';
        echo '<a class="btn" href="ceiling_monitor.php" target="_blank" rel="noopener">Ceiling Monitor</a>';
        echo '<a class="btn" href="?bulk_run=1" onclick="return confirm(\'Run transaction save for all rows in tblTransactionInput WHERE VersionID = 5?\');">Run Save For ALL VersionID=5 Transactions</a>';
        echo '</p></div></body></html>';
    }
}






