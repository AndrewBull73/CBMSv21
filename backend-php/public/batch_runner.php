<?php
// Simple UI to launch parallel batch runs with Redis batch mode toggling.

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN'],
    'csrfPost' => true,
]);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$repoRoot = dirname(__DIR__, 2);
$psScript = $repoRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'run_parallel_batch.ps1';

$status = '';
$logPath = '';
$tail = '';
$progress = null;
$shardStatus = [];
$batchMeta = [];
$batchStart = null;
$batchEndMs = null;
$stopFile = null;
$cancelNotice = '';
$lockFile = $repoRoot . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'batch.lock';
$runningShardCount = null;
$heartbeatAt = null;
$activeBatchLog = null;
$selectedLogPath = null;
$recordProgress = null;
$shardMetrics = [];
$performanceSummary = null;
$batchCancelled = false;
$selectedLogHasEnded = false;
$activeRuns = [];
$activeRunWarning = '';
$batchStalled = false;
$logLooksActive = false;
$redisStatus = null;
$monitorSummary = null;
$monitorRedisRows = [];
$monitorRecentFailures = [];
$monitorError = '';
$controlNotice = '';
$controlError = '';

$defaultFy = (int)($_POST['fy'] ?? $_GET['fy'] ?? 2026);
$defaultVersion = (int)($_POST['version'] ?? $_GET['version'] ?? 5);
$defaultType = trim((string)($_POST['type'] ?? $_GET['type'] ?? ''));
$defaultCeilingFinalEngine = strtolower(trim((string)($_POST['ceiling_final_engine'] ?? $_GET['ceiling_final_engine'] ?? 'sproc')));
if (!in_array($defaultCeilingFinalEngine, ['sproc', 'redis'], true)) {
    $defaultCeilingFinalEngine = 'sproc';
}

function fmtDurationSeconds(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '-';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $secs = $seconds % 60;
    if ($hours > 0) {
        return sprintf('%dh %02dm %02ds', $hours, $minutes, $secs);
    }
    if ($minutes > 0) {
        return sprintf('%dm %02ds', $minutes, $secs);
    }
    return sprintf('%ds', $secs);
}

function batchLogShowsCompletion(string $logPath): bool
{
    if (!is_file($logPath) || !is_readable($logPath)) {
        return false;
    }

    $lines = @file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || empty($lines)) {
        return false;
    }

    $tailLines = array_reverse(array_slice($lines, -50));
    foreach ($tailLines as $line) {
        if (str_contains($line, 'BATCH_END|') || str_contains($line, 'BATCH_CANCELLED|')) {
            return true;
        }
    }

    return false;
}

function findLatestBatchLog(string $logsDir): ?string
{
    $matches = glob($logsDir . DIRECTORY_SEPARATOR . 'batch_run_*.log');
    if ($matches === false || empty($matches)) {
        return null;
    }

    usort($matches, static function (string $a, string $b): int {
        return filemtime($b) <=> filemtime($a);
    });

    return $matches[0] ?? null;
}

function forceStopBatchProcesses(): void
{
    $psCommand = <<<'PS'
$phpTargets = Get-CimInstance Win32_Process -Filter "name='php.exe'" | Where-Object {
    ($_.CommandLine -like '*process_transaction_stub.php*') -and ($_.CommandLine -like '*--bulk-run*')
}
foreach ($proc in $phpTargets) {
    try { Stop-Process -Id $proc.ProcessId -Force -ErrorAction Stop } catch {}
}
$psTargets = Get-CimInstance Win32_Process -Filter "name='powershell.exe'" | Where-Object {
    $_.CommandLine -like '*run_parallel_batch.ps1*'
}
foreach ($proc in $psTargets) {
    try { Stop-Process -Id $proc.ProcessId -Force -ErrorAction Stop } catch {}
}
PS;

    $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psCommand);
    @pclose(@popen($command, 'r'));
}

function getActiveBatchRuns(): array
{
    $psCommand = <<<'PS'
$runs = Get-CimInstance Win32_Process -Filter "name='powershell.exe'" | Where-Object {
    $_.CommandLine -like '*run_parallel_batch.ps1*'
} | ForEach-Object {
    $cmd = $_.CommandLine
    $logPath = ''
    if ($cmd -match '-LogPath\s+"([^"]+)"') {
        $logPath = $Matches[1]
    }
    [PSCustomObject]@{
        ProcessId = $_.ProcessId
        CreationDate = $_.CreationDate.ToString('s')
        LogPath = $logPath
        CommandLine = $cmd
    }
}
$runs | ConvertTo-Json -Compress
PS;

    $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($psCommand);
    $json = shell_exec($command);
    if (!is_string($json) || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    if (isset($decoded['ProcessId'])) {
        $decoded = [$decoded];
    }
    usort($decoded, static function (array $a, array $b): int {
        return strcmp((string)($b['CreationDate'] ?? ''), (string)($a['CreationDate'] ?? ''));
    });
    return $decoded;
}

function formatRedisUnixTime($value): ?string
{
    $ts = is_numeric($value) ? (int)$value : 0;
    if ($ts <= 0) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

function normalizeRedisConfigResponse($response): array
{
    if (is_array($response)) {
        if (array_is_list($response)) {
            $out = [];
            for ($i = 0; $i < count($response); $i += 2) {
                $key = (string)($response[$i] ?? '');
                if ($key === '') {
                    continue;
                }
                $out[$key] = (string)($response[$i + 1] ?? '');
            }
            return $out;
        }
        return array_map(static fn($v): string => is_scalar($v) ? (string)$v : '', $response);
    }
    return [];
}

function getRedisStatus(): array
{
    $status = [
        'ok' => false,
        'source' => 'unavailable',
        'message' => 'Redis status unavailable.',
        'connected' => false,
        'stop_writes_on_bgsave_error' => null,
        'save_policy' => null,
        'rdb_last_bgsave_status' => null,
        'rdb_changes_since_last_save' => null,
        'rdb_last_save_time' => null,
        'loading' => null,
        'risk' => 'unknown',
    ];

    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379, 1.5);
            $pong = $redis->ping();
            if (is_string($pong)) {
                $pong = trim($pong, "+ \t\n\r\0\x0B");
            }
            $info = $redis->info('persistence');
            $saveCfg = normalizeRedisConfigResponse($redis->config('GET', 'save'));
            $stopCfg = normalizeRedisConfigResponse($redis->config('GET', 'stop-writes-on-bgsave-error'));
            $lastStatus = strtolower(trim((string)($info['rdb_last_bgsave_status'] ?? '')));
            $stopWrites = strtolower(trim((string)($stopCfg['stop-writes-on-bgsave-error'] ?? '')));
            $status['ok'] = strtoupper((string)$pong) === 'PONG';
            $status['source'] = 'phpredis';
            $status['message'] = $status['ok'] ? 'Redis reachable.' : 'Redis ping did not return PONG.';
            $status['connected'] = $status['ok'];
            $status['stop_writes_on_bgsave_error'] = $stopWrites !== '' ? $stopWrites : null;
            $status['save_policy'] = ($saveCfg['save'] ?? '') !== '' ? (string)$saveCfg['save'] : '(disabled)';
            $status['rdb_last_bgsave_status'] = $lastStatus !== '' ? $lastStatus : null;
            $status['rdb_changes_since_last_save'] = isset($info['rdb_changes_since_last_save']) ? (int)$info['rdb_changes_since_last_save'] : null;
            $status['rdb_last_save_time'] = formatRedisUnixTime($info['rdb_last_save_time'] ?? null);
            $status['loading'] = isset($info['loading']) ? (string)$info['loading'] : null;
            $status['risk'] = ($stopWrites === 'yes' && $lastStatus !== '' && $lastStatus !== 'ok') ? 'write_blocked' : 'ok';
            return $status;
        } catch (Throwable $e) {
            $status['message'] = 'phpredis check failed: ' . $e->getMessage();
        }
    }

    $redisCli = 'C:\\Redis\\redis-cli.exe';
    if (is_file($redisCli)) {
        try {
            $infoRaw = shell_exec('"' . $redisCli . '" INFO persistence 2>NUL');
            $saveRaw = shell_exec('"' . $redisCli . '" CONFIG GET save 2>NUL');
            $stopRaw = shell_exec('"' . $redisCli . '" CONFIG GET stop-writes-on-bgsave-error 2>NUL');
            if (is_string($infoRaw) && trim($infoRaw) !== '') {
                $info = [];
                foreach (preg_split('/\r\n|\n|\r/', trim($infoRaw)) as $line) {
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $info[trim($parts[0])] = trim($parts[1]);
                    }
                }

                $saveLines = preg_split('/\r\n|\n|\r/', trim((string)$saveRaw));
                $stopLines = preg_split('/\r\n|\n|\r/', trim((string)$stopRaw));
                $saveValue = trim((string)($saveLines[1] ?? ''));
                $stopValue = strtolower(trim((string)($stopLines[1] ?? '')));
                $lastStatus = strtolower(trim((string)($info['rdb_last_bgsave_status'] ?? '')));
                $status['ok'] = true;
                $status['source'] = 'redis-cli';
                $status['message'] = 'Redis reachable.';
                $status['connected'] = true;
                $status['stop_writes_on_bgsave_error'] = $stopValue !== '' ? $stopValue : null;
                $status['save_policy'] = $saveValue !== '' ? $saveValue : '(disabled)';
                $status['rdb_last_bgsave_status'] = $lastStatus !== '' ? $lastStatus : null;
                $status['rdb_changes_since_last_save'] = isset($info['rdb_changes_since_last_save']) ? (int)$info['rdb_changes_since_last_save'] : null;
                $status['rdb_last_save_time'] = formatRedisUnixTime($info['rdb_last_save_time'] ?? null);
                $status['loading'] = isset($info['loading']) ? (string)$info['loading'] : null;
                $status['risk'] = ($stopValue === 'yes' && $lastStatus !== '' && $lastStatus !== 'ok') ? 'write_blocked' : 'ok';
                return $status;
            }
        } catch (Throwable $e) {
            $status['message'] = 'redis-cli check failed: ' . $e->getMessage();
        }
    }

    return $status;
}

function parseBatchStartMeta(?string $payload): array
{
    if (!is_string($payload) || trim($payload) === '') {
        return [];
    }

    $meta = [];
    if (preg_match('/Fy=(\d+)/', $payload, $m)) {
        $meta['fy'] = (int)$m[1];
    }
    if (preg_match('/Version=(\d+)/', $payload, $m)) {
        $meta['version'] = (int)$m[1];
    }
    if (preg_match('/Type=([^|]*)/', $payload, $m)) {
        $meta['type'] = trim((string)$m[1]);
    }
    if (preg_match('/Shards=(\d+)/', $payload, $m)) {
        $meta['shards'] = (int)$m[1];
    }
    if (preg_match('/CeilingFinalEngine=([^|]*)/', $payload, $m)) {
        $meta['ceiling_final_engine'] = strtolower(trim((string)$m[1]));
    }
    if (preg_match('/NoCeilingChecks=(True|False)/i', $payload, $m)) {
        $meta['no_ceiling_checks'] = (strcasecmp((string)$m[1], 'True') === 0);
    }

    return $meta;
}

function batchRunnerRedisClient(): ?Predis\Client
{
    if (!class_exists('Predis\\Client')) {
        return null;
    }

    return new Predis\Client([
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
    ]);
}

function collectBatchMonitorData(PDO $conn, int $fy, int $version, string $type = '', int $redisRowLimit = 20, int $failureLimit = 25): array
{
    $type = trim($type);
    $params = [$fy, $version];
    $typeSql = '';
    if ($type !== '') {
        $typeSql = ' AND TransactionTypeCode = ?';
        $params[] = $type;
    }

    $summarySql = "
        SELECT
            COUNT(1) AS TotalRows,
            SUM(CASE WHEN HeadRecordID IS NULL OR HeadRecordID = TransactionID THEN 1 ELSE 0 END) AS HeadRows,
            SUM(CASE WHEN CeilingLastCheckedDate IS NOT NULL THEN 1 ELSE 0 END) AS CheckedRows,
            SUM(CASE WHEN CeilingStatus IN ('OK', 'Success') THEN 1 ELSE 0 END) AS SuccessRows,
            SUM(CASE WHEN CeilingFailedFlag = 1 THEN 1 ELSE 0 END) AS FailedRows,
            SUM(CASE WHEN CeilingStatusCheck = 'ERROR BEFORE CEILING CHECK' THEN 1 ELSE 0 END) AS ErrorBeforeCheckRows,
            SUM(CASE WHEN CeilingLastCheckedDate IS NULL THEN 1 ELSE 0 END) AS PendingRows
        FROM dbo.tblTransactionInput
        WHERE FiscalYearID = ?
          AND VersionID = ?" . $typeSql;
    $summaryStmt = $conn->prepare($summarySql);
    $summaryStmt->execute($params);
    $txSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $recentSql = "
        SELECT TOP {$failureLimit}
            TransactionID,
            HeadRecordID,
            TransactionTypeCode,
            CeilingStatus,
            CeilingStatusCheck,
            CeilingFailedFlag,
            CeilingDefinitionID,
            CeilingEngine,
            CeilingLastCheckedDate
        FROM dbo.tblTransactionInput
        WHERE FiscalYearID = ?
          AND VersionID = ?" .
          ($type !== '' ? ' AND TransactionTypeCode = ?' : '') . "
          AND (
                CeilingFailedFlag = 1
                OR CeilingStatusCheck = 'ERROR BEFORE CEILING CHECK'
              )
        ORDER BY CeilingLastCheckedDate DESC, TransactionID DESC";
    $recentStmt = $conn->prepare($recentSql);
    $recentStmt->execute($params);
    $recentFailures = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $breakdownBaseParams = [$fy, $version];
    $breakdownTypeSql = '';
    if ($type !== '') {
        $breakdownTypeSql = ' AND TransactionTypeCode = ?';
        $breakdownBaseParams[] = $type;
    }

    $reasonSql = "
        SELECT TOP 10
            COALESCE(NULLIF(CeilingStatusCheck, ''), '(blank)') AS BreakdownKey,
            COUNT(1) AS FailureCount
        FROM dbo.tblTransactionInput
        WHERE FiscalYearID = ?
          AND VersionID = ?" . $breakdownTypeSql . "
          AND (
                CeilingFailedFlag = 1
                OR CeilingStatusCheck = 'ERROR BEFORE CEILING CHECK'
              )
        GROUP BY COALESCE(NULLIF(CeilingStatusCheck, ''), '(blank)')
        ORDER BY COUNT(1) DESC, BreakdownKey ASC";
    $reasonStmt = $conn->prepare($reasonSql);
    $reasonStmt->execute($breakdownBaseParams);
    $failureByReason = $reasonStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $engineSql = "
        SELECT TOP 10
            COALESCE(NULLIF(CeilingEngine, ''), '(blank)') AS BreakdownKey,
            COUNT(1) AS FailureCount
        FROM dbo.tblTransactionInput
        WHERE FiscalYearID = ?
          AND VersionID = ?" . $breakdownTypeSql . "
          AND (
                CeilingFailedFlag = 1
                OR CeilingStatusCheck = 'ERROR BEFORE CEILING CHECK'
              )
        GROUP BY COALESCE(NULLIF(CeilingEngine, ''), '(blank)')
        ORDER BY COUNT(1) DESC, BreakdownKey ASC";
    $engineStmt = $conn->prepare($engineSql);
    $engineStmt->execute($breakdownBaseParams);
    $failureByEngine = $engineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $definitionSql = "
        SELECT TOP 10
            COALESCE(CAST(CeilingDefinitionID AS varchar(20)), '(null)') AS BreakdownKey,
            COUNT(1) AS FailureCount
        FROM dbo.tblTransactionInput
        WHERE FiscalYearID = ?
          AND VersionID = ?" . $breakdownTypeSql . "
          AND (
                CeilingFailedFlag = 1
                OR CeilingStatusCheck = 'ERROR BEFORE CEILING CHECK'
              )
        GROUP BY COALESCE(CAST(CeilingDefinitionID AS varchar(20)), '(null)')
        ORDER BY COUNT(1) DESC, BreakdownKey ASC";
    $definitionStmt = $conn->prepare($definitionSql);
    $definitionStmt->execute($breakdownBaseParams);
    $failureByDefinition = $definitionStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $typeBreakdownSql = "
        SELECT TOP 10
            COALESCE(NULLIF(TransactionTypeCode, ''), '(blank)') AS BreakdownKey,
            COUNT(1) AS FailureCount
        FROM dbo.tblTransactionInput
        WHERE FiscalYearID = ?
          AND VersionID = ?" . $breakdownTypeSql . "
          AND (
                CeilingFailedFlag = 1
                OR CeilingStatusCheck = 'ERROR BEFORE CEILING CHECK'
              )
        GROUP BY COALESCE(NULLIF(TransactionTypeCode, ''), '(blank)')
        ORDER BY COUNT(1) DESC, BreakdownKey ASC";
    $typeStmt = $conn->prepare($typeBreakdownSql);
    $typeStmt->execute($breakdownBaseParams);
    $failureByTransactionType = $typeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $defParams = [$fy, $version];
    $defSql = "
        SELECT CeilingDefinitionID, DataObjectCode, TransactionTypeCode, CeilingBPTotal
        FROM dbo.tblCeilingDefinition
        WHERE ActiveFlag = 1
          AND ApprovedFlag = 1
          AND FiscalYearID = ?
          AND VersionID = ?" . ($type !== '' ? ' AND TransactionTypeCode = ?' : '') . "
        ORDER BY Priority ASC, CeilingDefinitionID ASC";
    if ($type !== '') {
        $defParams[] = $type;
    }
    $defStmt = $conn->prepare($defSql);
    $defStmt->execute($defParams);
    $definitions = $defStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $defMap = [];
    foreach ($definitions as $def) {
        $defId = (int)($def['CeilingDefinitionID'] ?? 0);
        if ($defId > 0) {
            $defMap[$defId] = $def;
        }
    }

    $redisSummary = [
        'definitions_expected' => count($definitions),
        'keys_loaded' => 0,
        'missing_keys' => count($definitions),
        'total_balance' => 0.0,
        'total_ceiling' => 0.0,
        'breach_count' => 0,
        'rows' => [],
    ];

    $redis = batchRunnerRedisClient();
    if ($redis !== null && !empty($defMap)) {
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
                foreach ((array)$scan[1] as $key) {
                    $keys[] = (string)$key;
                }
            } while ($cursor !== 0);
        } else {
            $raw = $redis->keys($pattern);
            $keys = is_array($raw) ? $raw : [];
        }

        sort($keys, SORT_NATURAL);
        foreach ($keys as $key) {
            $parts = explode(':', $key);
            $defId = (int)end($parts);
            if (!isset($defMap[$defId])) {
                continue;
            }

            $vals = $redis->hmget($key, ['bal_total', 'ceil_total', 'last_tx']);
            $bal = (float)($vals[0] ?? 0);
            $ceil = (float)($vals[1] ?? ($defMap[$defId]['CeilingBPTotal'] ?? 0));
            $ttl = (int)$redis->ttl($key);
            $lastTxRaw = $vals[2] ?? null;
            $remaining = $ceil - $bal;

            $redisSummary['keys_loaded']++;
            $redisSummary['total_balance'] += $bal;
            $redisSummary['total_ceiling'] += $ceil;
            if ($remaining < 0) {
                $redisSummary['breach_count']++;
            }

            if (count($redisSummary['rows']) < $redisRowLimit) {
                $redisSummary['rows'][] = [
                    'ceiling_definition_id' => $defId,
                    'data_object_code' => (string)($defMap[$defId]['DataObjectCode'] ?? ''),
                    'transaction_type_code' => (string)($defMap[$defId]['TransactionTypeCode'] ?? ''),
                    'balance_total' => $bal,
                    'ceiling_total' => $ceil,
                    'remaining' => $remaining,
                    'last_tx' => ($lastTxRaw === null || $lastTxRaw === '') ? null : (int)$lastTxRaw,
                    'ttl' => $ttl,
                ];
            }
        }
        $redisSummary['missing_keys'] = max(0, $redisSummary['definitions_expected'] - $redisSummary['keys_loaded']);
    }

    return [
        'tx' => [
            'total_rows' => (int)($txSummary['TotalRows'] ?? 0),
            'head_rows' => (int)($txSummary['HeadRows'] ?? 0),
            'checked_rows' => (int)($txSummary['CheckedRows'] ?? 0),
            'success_rows' => (int)($txSummary['SuccessRows'] ?? 0),
            'failed_rows' => (int)($txSummary['FailedRows'] ?? 0),
            'pending_rows' => (int)($txSummary['PendingRows'] ?? 0),
            'error_before_check_rows' => (int)($txSummary['ErrorBeforeCheckRows'] ?? 0),
        ],
        'failure_breakdown' => [
            'by_reason' => $failureByReason,
            'by_engine' => $failureByEngine,
            'by_definition' => $failureByDefinition,
            'by_transaction_type' => $failureByTransactionType,
        ],
        'redis' => $redisSummary,
        'recent_failures' => $recentFailures,
    ];
}

function deleteRedisKeysByPattern(Predis\Client $redis, string $pattern): int
{
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

    return $deleted;
}

function batchRunnerWarmupCeilings(int $fy, int $version): array
{
    require_once __DIR__ . '/warmup_ceiling_cache.php';
    if (!function_exists('runCeilingCacheWarmup')) {
        throw new RuntimeException('Ceiling warmup function is unavailable.');
    }

    return runCeilingCacheWarmup([
        'fy' => $fy > 0 ? $fy : null,
        'version' => $version > 0 ? $version : null,
        'ttl' => 86400,
        'dry_run' => false,
        'verbose' => false,
    ]);
}

function batchRunnerSyncCeilingsFromRedis(PDO $conn, int $fy, int $version): array
{
    $redis = batchRunnerRedisClient();
    if ($redis === null) {
        throw new RuntimeException('Predis client not available.');
    }

    $sql = "
        SELECT CeilingDefinitionID, FiscalYearID, VersionID
        FROM dbo.tblCeilingDefinition
        WHERE ActiveFlag = 1
          AND ApprovedFlag = 1
          AND FiscalYearID = ?
          AND VersionID = ?
        ORDER BY CeilingDefinitionID ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fy, $version]);
    $defs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $updateSql = "
        UPDATE dbo.tblCeilingBalance
        SET
            BalanceBP1 = ?, BalanceBP2 = ?, BalanceBP3 = ?, BalanceBP4 = ?, BalanceBP5 = ?, BalanceBP6 = ?,
            BalanceBP7 = ?, BalanceBP8 = ?, BalanceBP9 = ?, BalanceBP10 = ?, BalanceBP11 = ?, BalanceBP12 = ?,
            BalanceBPTotal = ?, LastTransactionID = ?, UpdatedBy = ?, UpdatedDate = GETDATE()
        WHERE CeilingDefinitionID = ?";
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
        )";
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
        if (!is_array($hash) || ($hash[0] ?? null) === null) {
            $summary['skipped_no_redis']++;
            continue;
        }

        $summary['redis_found']++;
        $bp = [];
        for ($i = 0; $i < 12; $i++) {
            $bp[] = (float)($hash[$i] ?? 0);
        }
        $balTotal = (float)($hash[12] ?? 0);
        $lastTxRaw = $hash[13] ?? null;
        $lastTx = ($lastTxRaw === null || $lastTxRaw === '') ? null : (int)$lastTxRaw;

        try {
            $updateStmt->execute([
                $bp[0], $bp[1], $bp[2], $bp[3], $bp[4], $bp[5],
                $bp[6], $bp[7], $bp[8], $bp[9], $bp[10], $bp[11],
                $balTotal, $lastTx, 1, $defId
            ]);
            if ($updateStmt->rowCount() > 0) {
                $summary['updated']++;
            } else {
                $insertStmt->execute([
                    $defId, (int)$def['FiscalYearID'], (int)$def['VersionID'],
                    $bp[0], $bp[1], $bp[2], $bp[3], $bp[4], $bp[5],
                    $bp[6], $bp[7], $bp[8], $bp[9], $bp[10], $bp[11],
                    $balTotal, $lastTx, 1
                ]);
                $summary['inserted']++;
            }
            $summary['synced']++;
        } catch (Throwable $e) {
            $summary['errors']++;
        }
    }

    return $summary;
}

function batchRunnerReloadCeilingBalances(PDO $conn, int $fy, int $version): array
{
    $conn->beginTransaction();
    try {
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
              AND d.FiscalYearID = ?
              AND d.VersionID = ?
              AND b.CeilingDefinitionID IS NULL";
        $ins = $conn->prepare($insertSql);
        $ins->execute([$fy, $version]);
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
              AND d.FiscalYearID = ?
              AND d.VersionID = ?";
        $upd = $conn->prepare($updateSql);
        $upd->execute([$fy, $version]);
        $updated = (int)$upd->rowCount();

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    $warmup = batchRunnerWarmupCeilings($fy, $version);

    return [
        'inserted' => $inserted,
        'updated' => $updated,
        'keys_written' => (int)($warmup['balance_keys_written'] ?? 0),
    ];
}

function batchRunnerResetBalancesAndClearRedis(PDO $conn, int $fy, int $version): array
{
    $summary = [
        'updated_rows' => 0,
        'inserted_rows' => 0,
        'deleted_redis_balance_keys' => 0,
        'deleted_redis_snapshot_keys' => 0,
    ];

    $conn->beginTransaction();
    try {
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
                cb.UpdatedBy = 1,
                cb.UpdatedDate = GETDATE()
            FROM dbo.tblCeilingBalance cb
            INNER JOIN dbo.tblCeilingDefinition cd
                ON cd.CeilingDefinitionID = cb.CeilingDefinitionID
            WHERE cd.ActiveFlag = 1
              AND cd.ApprovedFlag = 1
              AND cd.FiscalYearID = ?
              AND cd.VersionID = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute([$fy, $version]);
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
                cd.CeilingBPTotal, NULL, 1, GETDATE()
            FROM dbo.tblCeilingDefinition cd
            LEFT JOIN dbo.tblCeilingBalance cb
                ON cb.CeilingDefinitionID = cd.CeilingDefinitionID
            WHERE cb.CeilingDefinitionID IS NULL
              AND cd.ActiveFlag = 1
              AND cd.ApprovedFlag = 1
              AND cd.FiscalYearID = ?
              AND cd.VersionID = ?";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->execute([$fy, $version]);
        $summary['inserted_rows'] = (int)$insertStmt->rowCount();
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    $redis = batchRunnerRedisClient();
    if ($redis !== null) {
        $summary['deleted_redis_balance_keys'] = deleteRedisKeysByPattern($redis, 'ceiling:balance:v1:*');
        $summary['deleted_redis_snapshot_keys'] = deleteRedisKeysByPattern($redis, 'ceiling:snapshot:*');
    }

    return $summary;
}

function batchRunnerResetAndWarmup(PDO $conn, int $fy, int $version): array
{
    $reset = batchRunnerResetBalancesAndClearRedis($conn, $fy, $version);
    $warmup = batchRunnerWarmupCeilings($fy, $version);

    return [
        'reset' => $reset,
        'warmup' => $warmup,
    ];
}

function batchRunnerResetTransactionCeilingState(PDO $conn, int $fy, int $version): array
{
    $sql = "
        UPDATE dbo.tblTransactionInput
        SET
            CeilingStatus = NULL,
            CeilingStatusCheck = NULL,
            CeilingErrorMessage = NULL,
            CeilingFailedFlag = 0,
            CeilingDefinitionID = NULL,
            CeilingEngine = NULL,
            CeilingLastCheckedDate = NULL,
            CeilingCheckDate = NULL,
            CeilingAppliedBP1 = NULL,
            CeilingAppliedBP2 = NULL,
            CeilingAppliedBP3 = NULL,
            CeilingAppliedBP4 = NULL,
            CeilingAppliedBP5 = NULL,
            CeilingAppliedBP6 = NULL,
            CeilingAppliedBP7 = NULL,
            CeilingAppliedBP8 = NULL,
            CeilingAppliedBP9 = NULL,
            CeilingAppliedBP10 = NULL,
            CeilingAppliedBP11 = NULL,
            CeilingAppliedBP12 = NULL,
            CeilingAppliedTotal = NULL
        WHERE FiscalYearID = ?
          AND VersionID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$fy, $version]);

    return [
        'rows_reset' => (int)$stmt->rowCount(),
    ];
}

function batchRunnerFullCeilingReset(PDO $conn, int $fy, int $version): array
{
    $tx = batchRunnerResetTransactionCeilingState($conn, $fy, $version);
    $ceilings = batchRunnerResetAndWarmup($conn, $fy, $version);

    return [
        'transactions' => $tx,
        'ceilings' => $ceilings,
    ];
}

$activeRuns = getActiveBatchRuns();
$redisStatus = getRedisStatus();
if (count($activeRuns) > 1) {
    $activeRunWarning = 'Multiple batch runner processes are active. The page may reflect mixed log states until the extras are cancelled.';
}

if (file_exists($lockFile) || !empty($activeRuns)) {
    $logsDir = $repoRoot . DIRECTORY_SEPARATOR . 'logs';
    $latestBatchLog = null;
    foreach ($activeRuns as $run) {
        $candidateLog = trim((string)($run['LogPath'] ?? ''));
        if ($candidateLog !== '' && is_file($candidateLog)) {
            $latestBatchLog = $candidateLog;
            break;
        }
    }
    if ($latestBatchLog === null) {
        $latestBatchLog = findLatestBatchLog($logsDir);
    }
    if ($latestBatchLog !== null && batchLogShowsCompletion($latestBatchLog)) {
        @unlink($lockFile);
    } else {
        $activeBatchLog = $latestBatchLog;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isCancelRequest = (!empty($_POST['action']) && $_POST['action'] === 'cancel');
    $isForceUnlockRequest = (!empty($_POST['action']) && $_POST['action'] === 'force_unlock');
    $isControlRequest = in_array((string)($_POST['action'] ?? ''), [
        'full_ceiling_reset',
        'warmup_cache',
        'sync_from_redis',
        'reload_balances',
        'reset_balances_clear_redis',
        'reset_then_warmup',
        'reset_tx_ceiling_state',
    ], true);
    if ($isCancelRequest) {
        $requestedStop = $_POST['stop_file'] ?? '';
        $logDir = realpath($repoRoot . DIRECTORY_SEPARATOR . 'logs');
        $candidate = realpath(dirname($requestedStop));
        if ($logDir && $candidate && str_starts_with($candidate, $logDir)) {
            @file_put_contents($requestedStop, "stop\n");
            forceStopBatchProcesses();
            @unlink($lockFile);
            $cancelNotice = 'Cancel requested. Stopping batch workers now...';
        }
    }
    if ($isForceUnlockRequest) {
        if (file_exists($lockFile)) {
            @unlink($lockFile);
            $status = 'Batch lock cleared.';
        } else {
            $status = 'No batch lock file was present.';
        }
    }

    if ($isControlRequest) {
        $controlFy = max(0, (int)($_POST['control_fy'] ?? $defaultFy));
        $controlVersion = max(0, (int)($_POST['control_version'] ?? $defaultVersion));
        if ($controlFy <= 0 || $controlVersion <= 0) {
            $controlError = 'Fiscal Year and Version are required for control actions.';
        } elseif (file_exists($lockFile) || !empty(getActiveBatchRuns())) {
            $controlError = 'Finish or cancel the active batch before running ceiling control actions.';
        } else {
            try {
                $action = (string)$_POST['action'];
                if ($action === 'full_ceiling_reset') {
                    $summary = batchRunnerFullCeilingReset($conn, $controlFy, $controlVersion);
                    $controlNotice = 'Full ceiling reset completed. Tx rows cleared: ' . (int)($summary['transactions']['rows_reset'] ?? 0) . ', balance rows updated: ' . (int)($summary['ceilings']['reset']['updated_rows'] ?? 0) . ', keys written: ' . (int)($summary['ceilings']['warmup']['balance_keys_written'] ?? 0) . '.';
                } elseif ($action === 'warmup_cache') {
                    $summary = batchRunnerWarmupCeilings($controlFy, $controlVersion);
                    $controlNotice = 'Ceiling cache warmed. Balance keys written: ' . (int)($summary['balance_keys_written'] ?? 0) . '.';
                } elseif ($action === 'sync_from_redis') {
                    $summary = batchRunnerSyncCeilingsFromRedis($conn, $controlFy, $controlVersion);
                    $controlNotice = 'Redis synced to DB. Synced: ' . (int)$summary['synced'] . ', updated: ' . (int)$summary['updated'] . ', inserted: ' . (int)$summary['inserted'] . '.';
                } elseif ($action === 'reload_balances') {
                    $summary = batchRunnerReloadCeilingBalances($conn, $controlFy, $controlVersion);
                    $controlNotice = 'Ceiling balances reloaded from definitions. Inserted: ' . (int)$summary['inserted'] . ', reset: ' . (int)$summary['updated'] . ', keys written: ' . (int)$summary['keys_written'] . '.';
                } elseif ($action === 'reset_balances_clear_redis') {
                    $summary = batchRunnerResetBalancesAndClearRedis($conn, $controlFy, $controlVersion);
                    $controlNotice = 'Balances reset to ceilings and Redis cleared. Updated: ' . (int)$summary['updated_rows'] . ', inserted: ' . (int)$summary['inserted_rows'] . ', balance keys deleted: ' . (int)$summary['deleted_redis_balance_keys'] . '.';
                } elseif ($action === 'reset_then_warmup') {
                    $summary = batchRunnerResetAndWarmup($conn, $controlFy, $controlVersion);
                    $controlNotice = 'Reset + warmup completed. Updated: ' . (int)($summary['reset']['updated_rows'] ?? 0) . ', inserted: ' . (int)($summary['reset']['inserted_rows'] ?? 0) . ', keys written: ' . (int)($summary['warmup']['balance_keys_written'] ?? 0) . '.';
                } elseif ($action === 'reset_tx_ceiling_state') {
                    $summary = batchRunnerResetTransactionCeilingState($conn, $controlFy, $controlVersion);
                    $controlNotice = 'Transaction ceiling warnings reset in tblTransactionInput. Rows cleared: ' . (int)($summary['rows_reset'] ?? 0) . '.';
                }
                $defaultFy = $controlFy;
                $defaultVersion = $controlVersion;
            } catch (Throwable $e) {
                $controlError = $e->getMessage();
            }
        }
    }

    if (!$isCancelRequest && !$isForceUnlockRequest && !$isControlRequest) {
        $fy = (int)($_POST['fy'] ?? 2026);
        $version = (int)($_POST['version'] ?? 5);
        $shards = max(1, (int)($_POST['shards'] ?? 4));
        $retry = max(0, (int)($_POST['retry'] ?? 15));
        $type = trim($_POST['type'] ?? '');
        $ceilingFinalEngine = strtolower(trim((string)($_POST['ceiling_final_engine'] ?? 'sproc')));
        if (!in_array($ceilingFinalEngine, ['sproc', 'redis'], true)) {
            $ceilingFinalEngine = 'sproc';
        }
        $invalidate = !empty($_POST['invalidate']) ? ' -InvalidateScope' : '';
        $noCeilingChecks = !empty($_POST['no_ceiling_checks']) ? ' -NoCeilingChecks' : '';
        $postRunRecovery = !empty($_POST['post_run_recovery']) ? ' -PostRunErrorBeforeCeilingCheck' : '';

        if (file_exists($lockFile) || !empty(getActiveBatchRuns())) {
            $status = 'A batch is already running. Please wait or cancel it.';
        } else {
            @file_put_contents($lockFile, date('c'));
            $logDir = $repoRoot . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $logPath = $logDir . DIRECTORY_SEPARATOR . 'batch_run_' . date('Ymd_His') . '.log';
            $stopFile = $logPath . '.stop';

            $cmd = sprintf(
                'start /b powershell -NoProfile -ExecutionPolicy Bypass -File "%s" -Fy %d -Version %d -Shards %d -Retry %d -CeilingFinalEngine %s%s -LogPath "%s" -StopFile "%s"%s%s%s',
                $psScript,
                $fy,
                $version,
                $shards,
                $retry,
                $ceilingFinalEngine,
                $type !== '' ? ' -Type ' . escapeshellarg($type) : '',
                $logPath,
                $stopFile,
                $invalidate,
                $noCeilingChecks,
                $postRunRecovery
            );

            // Fire-and-forget
            @pclose(@popen($cmd, 'r'));
            header('Location: ?log=' . urlencode($logPath) . '&started=1');
            exit;
        }
    }
}

if (!isset($_GET['log']) && $activeBatchLog !== null) {
    header('Location: ?log=' . urlencode($activeBatchLog));
    exit;
}

if (!empty($activeRuns)) {
    $newestActiveLog = trim((string)($activeRuns[0]['LogPath'] ?? ''));
    if ($newestActiveLog !== '' && is_file($newestActiveLog)) {
        $requestedLog = isset($_GET['log']) ? trim((string)$_GET['log']) : '';
        $requestedReal = $requestedLog !== '' ? realpath($requestedLog) : false;
        $activeReal = realpath($newestActiveLog);
        if ($activeReal && $requestedReal !== $activeReal) {
            header('Location: ?log=' . urlencode($activeReal));
            exit;
        }
    }
}

if (isset($_GET['started']) && $_GET['started'] === '1') {
    $status = 'Batch run started.';
}

// If a log is requested, show the last N lines.
if (isset($_GET['log']) && $_GET['log'] !== '') {
    $requested = $_GET['log'];
    $logDir = realpath($repoRoot . DIRECTORY_SEPARATOR . 'logs');
    $candidate = realpath($requested);
    if ($logDir && $candidate && str_starts_with($candidate, $logDir) && is_file($candidate)) {
        $selectedLogPath = $candidate;
        $lines = @file($candidate, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            $tailLines = array_slice($lines, -200);
            $tail = implode("\n", $tailLines);
            $logPath = $candidate;

            // Parse full log for shard status and progress.
            $totalShards = null;
            $completed = 0;
            $lastUpdate = null;
            $shardRecordProgress = [];
            $batchEndAt = null;
            $lastWorkerActivityAt = null;
            foreach ($lines as $line) {
                if (str_starts_with($line, '[')) {
                    $parts = explode('] ', $line, 2);
                    $payload = $parts[1] ?? '';
                    $lastUpdate = trim($parts[0], '[]');
                } else {
                    $payload = $line;
                }
                if (str_starts_with($payload, 'BATCH_START|')) {
                    $batchMeta['raw'] = $payload;
                    if (preg_match('/Shards=(\d+)/', $payload, $m)) {
                        $totalShards = (int)$m[1];
                    }
                    $batchStart = $lastUpdate;
                } elseif (str_starts_with($payload, 'BATCH_STOP_FILE|')) {
                    if (preg_match('/Path=(.*)$/', $payload, $m)) {
                        $stopFile = trim($m[1]);
                    }
                } elseif (str_starts_with($payload, 'SHARD_START|')) {
                    if (preg_match('/Idx=(\d+).*TotalShards=(\d+)/', $payload, $m)) {
                        $idx = (int)$m[1];
                        $shardStatus[$idx] = $shardStatus[$idx] ?? [];
                        $shardStatus[$idx]['status'] = 'running';
                    }
                    $lastWorkerActivityAt = $lastUpdate;
                } elseif (str_starts_with($payload, 'SHARD_END|')) {
                    if (preg_match('/Idx=(\d+).*Total=(\d+).*Success=(\d+).*Failed=(\d+).*ElapsedMs=(\d+)/', $payload, $m)) {
                        $idx = (int)$m[1];
                        $shardStatus[$idx] = [
                            'status' => 'done',
                            'total' => (int)$m[2],
                            'ok' => (int)$m[3],
                            'fail' => (int)$m[4],
                            'elapsed' => (int)$m[5],
                        ];
                        $completed++;
                    }
                    $lastWorkerActivityAt = $lastUpdate;
                } elseif (str_starts_with($payload, 'SHARD_METRICS|')) {
                    if (preg_match('/Idx=(\d+)\|TxAvgMs=(\d+)\|CeilingSnapshotMs=(\d+)\|CeilingPrecheckMs=(\d+)\|CeilingFinalSprocMs=(\d+)\|CeilingFinalRedisMs=(\d+)\|CeilingSyncBackMs=(\d+)\|DbQueries=(\d+)\|RedisGets=(\d+)\|RedisSets=(\d+)/', $payload, $m)) {
                        $idx = (int)$m[1];
                        $shardMetrics[$idx] = [
                            'tx_avg_ms' => (int)$m[2],
                            'ceiling_snapshot_ms' => (int)$m[3],
                            'ceiling_precheck_ms' => (int)$m[4],
                            'ceiling_final_sproc_ms' => (int)$m[5],
                            'ceiling_final_redis_ms' => (int)$m[6],
                            'ceiling_sync_back_ms' => (int)$m[7],
                            'db_queries' => (int)$m[8],
                            'redis_gets' => (int)$m[9],
                            'redis_sets' => (int)$m[10],
                        ];
                    }
                    $lastWorkerActivityAt = $lastUpdate;
                } elseif (str_starts_with($payload, 'SHARD_OUT|')) {
                    if (preg_match('/Idx=(\d+)\|Bulk Init \| Total: (\d+)/', $payload, $m)) {
                        $idx = (int)$m[1];
                        $shardRecordProgress[$idx] = $shardRecordProgress[$idx] ?? [
                            'processed' => 0,
                            'total' => 0,
                            'ok' => 0,
                            'fail' => 0,
                        ];
                        $shardRecordProgress[$idx]['total'] = (int)$m[2];
                    } elseif (preg_match('/Idx=(\d+)\|Bulk Progress \| Processed: (\d+) \/ (\d+) \| Success: (\d+) \| Failed: (\d+)/', $payload, $m)) {
                        $idx = (int)$m[1];
                        $shardRecordProgress[$idx] = [
                            'processed' => (int)$m[2],
                            'total' => (int)$m[3],
                            'ok' => (int)$m[4],
                            'fail' => (int)$m[5],
                        ];
                    }
                    if (preg_match('/Idx=\\|?(\\d+)\\|.*.*Total:\s+(\d+)\s+\|\s+Success:\s+(\d+)\s+\|\s+Failed:\s+(\d+)\s+\|\s+Elapsed:\s+(\d+)\s+ms/', $payload, $m)) {
                        $idx = (int)$m[1];
                        $shardStatus[$idx] = [
                            'status' => 'done',
                            'total' => (int)$m[2],
                            'ok' => (int)$m[3],
                            'fail' => (int)$m[4],
                            'elapsed' => (int)$m[5],
                        ];
                    }
                    $lastWorkerActivityAt = $lastUpdate;
                } elseif (str_starts_with($payload, 'BATCH_END|')) {
                    $selectedLogHasEnded = true;
                    if (preg_match('/ElapsedMs=(\d+)/', $payload, $m)) {
                        $batchEndMs = (int)$m[1];
                    }
                    $batchEndAt = $lastUpdate;
                } elseif (str_starts_with($payload, 'BATCH_HEARTBEAT|')) {
                    if (preg_match('/RunningShards=(\d+)/', $payload, $m)) {
                        $runningShardCount = (int)$m[1];
                    }
                    $heartbeatAt = $lastUpdate;
                    $lastWorkerActivityAt = $lastUpdate;
                } elseif (str_starts_with($payload, 'BATCH_CANCELLED|')) {
                    $batchCancelled = true;
                    if (file_exists($lockFile)) {
                        @unlink($lockFile);
                    }
                }
            }
            if (!empty($shardRecordProgress)) {
                $recordsProcessed = 0;
                $recordsTotal = 0;
                $recordsOk = 0;
                $recordsFail = 0;
                foreach ($shardRecordProgress as $row) {
                    $recordsProcessed += (int)($row['processed'] ?? 0);
                    $recordsTotal += (int)($row['total'] ?? 0);
                    $recordsOk += (int)($row['ok'] ?? 0);
                    $recordsFail += (int)($row['fail'] ?? 0);
                }
                $recordProgress = [
                    'processed' => $recordsProcessed,
                    'total' => $recordsTotal,
                    'ok' => $recordsOk,
                    'fail' => $recordsFail,
                ];
            }
            if (!empty($shardMetrics)) {
                $performanceSummary = [
                    'tx_avg_ms' => 0,
                    'ceiling_snapshot_ms' => 0,
                    'ceiling_precheck_ms' => 0,
                    'ceiling_final_sproc_ms' => 0,
                    'ceiling_final_redis_ms' => 0,
                    'ceiling_sync_back_ms' => 0,
                    'db_queries' => 0,
                    'redis_gets' => 0,
                    'redis_sets' => 0,
                    'shards' => count($shardMetrics),
                ];
                foreach ($shardMetrics as $row) {
                    $performanceSummary['tx_avg_ms'] += (int)($row['tx_avg_ms'] ?? 0);
                    $performanceSummary['ceiling_snapshot_ms'] += (int)($row['ceiling_snapshot_ms'] ?? 0);
                    $performanceSummary['ceiling_precheck_ms'] += (int)($row['ceiling_precheck_ms'] ?? 0);
                    $performanceSummary['ceiling_final_sproc_ms'] += (int)($row['ceiling_final_sproc_ms'] ?? 0);
                    $performanceSummary['ceiling_final_redis_ms'] += (int)($row['ceiling_final_redis_ms'] ?? 0);
                    $performanceSummary['ceiling_sync_back_ms'] += (int)($row['ceiling_sync_back_ms'] ?? 0);
                    $performanceSummary['db_queries'] += (int)($row['db_queries'] ?? 0);
                    $performanceSummary['redis_gets'] += (int)($row['redis_gets'] ?? 0);
                    $performanceSummary['redis_sets'] += (int)($row['redis_sets'] ?? 0);
                }
                $performanceSummary['tx_avg_ms'] = (int)round($performanceSummary['tx_avg_ms'] / max(1, (int)$performanceSummary['shards']));
                $performanceSummary['ceiling_total_ms'] =
                    (int)$performanceSummary['ceiling_snapshot_ms'] +
                    (int)$performanceSummary['ceiling_precheck_ms'] +
                    (int)$performanceSummary['ceiling_final_sproc_ms'] +
                    (int)$performanceSummary['ceiling_final_redis_ms'] +
                    (int)$performanceSummary['ceiling_sync_back_ms'];
            }
            if ($totalShards === null && !empty($shardStatus)) {
                $totalShards = max(array_keys($shardStatus)) + 1;
            }
            if (!empty($shardStatus)) {
                $completed = 0;
                foreach ($shardStatus as $row) {
                    if (($row['status'] ?? '') === 'done') {
                        $completed++;
                    }
                }
            }
            if ($totalShards !== null) {
                $progress = [
                    'total' => $totalShards,
                    'done' => $completed,
                    'last_update' => $lastUpdate,
                ];
            }
            if ($batchEndMs !== null && file_exists($lockFile)) {
                @unlink($lockFile);
            }
            $batchEndTs = $batchEndAt !== null ? strtotime($batchEndAt) : false;
            $lastWorkerActivityTs = $lastWorkerActivityAt !== null ? strtotime($lastWorkerActivityAt) : false;
            if (
                $batchEndMs !== null &&
                $batchEndTs !== false &&
                $lastWorkerActivityTs !== false &&
                $lastWorkerActivityTs > $batchEndTs
            ) {
                $selectedLogHasEnded = false;
                $batchEndMs = null;
            }
        }
    }
}
$displayLogPath = $selectedLogPath ?? $logPath ?? $activeBatchLog;
$isViewingSelectedLog = $selectedLogPath !== null;
$hasActiveRunner = !empty($activeRuns);
$lastUpdateTs = !empty($progress['last_update'] ?? null) ? strtotime((string)$progress['last_update']) : (!empty($heartbeatAt) ? strtotime((string)$heartbeatAt) : false);
if (
    $isViewingSelectedLog &&
    !$selectedLogHasEnded &&
    $lastUpdateTs !== false &&
    $lastUpdateTs !== null &&
    (time() - $lastUpdateTs) <= 30
) {
    $logLooksActive = true;
}
if (
    $isViewingSelectedLog &&
    !$selectedLogHasEnded &&
    !$hasActiveRunner &&
    !$logLooksActive &&
    !file_exists($lockFile)
) {
    $batchStalled = true;
}
$isBatchRunning = $isViewingSelectedLog
    ? (!$selectedLogHasEnded && !$batchStalled && ($hasActiveRunner || $logLooksActive))
    : (($hasActiveRunner || file_exists($lockFile)) && $batchEndMs === null);
$batchStateLabel = $batchCancelled
    ? 'Batch cancelled'
    : ($batchStalled
        ? 'Batch stopped unexpectedly'
        : ($isBatchRunning ? 'Batch is running' : (($selectedLogHasEnded || $batchEndMs !== null) ? 'Batch completed' : 'Batch status unknown')));
$elapsedSeconds = null;
$etaSeconds = null;
if ($batchEndMs !== null) {
    $elapsedSeconds = (int)round($batchEndMs / 1000);
} elseif ($isBatchRunning && $batchStart !== null) {
    $batchStartTs = strtotime($batchStart);
    if ($batchStartTs !== false) {
        $elapsedSeconds = max(0, time() - $batchStartTs);
    }
}
if (
    $isBatchRunning &&
    $elapsedSeconds !== null &&
    $recordProgress &&
    (int)($recordProgress['processed'] ?? 0) > 0 &&
    (int)($recordProgress['total'] ?? 0) > (int)($recordProgress['processed'] ?? 0)
) {
    $processed = (int)$recordProgress['processed'];
    $total = (int)$recordProgress['total'];
    $remaining = max(0, $total - $processed);
    $secondsPerRecord = $elapsedSeconds / max(1, $processed);
    $etaSeconds = (int)round($remaining * $secondsPerRecord);
}

$parsedBatchMeta = parseBatchStartMeta($batchMeta['raw'] ?? null);
$monitorFy = (int)($parsedBatchMeta['fy'] ?? $defaultFy);
$monitorVersion = (int)($parsedBatchMeta['version'] ?? $defaultVersion);
$monitorType = trim((string)($parsedBatchMeta['type'] ?? $defaultType));
$batchCeilingFinalEngine = strtolower(trim((string)($parsedBatchMeta['ceiling_final_engine'] ?? $defaultCeilingFinalEngine)));
if (!in_array($batchCeilingFinalEngine, ['sproc', 'redis'], true)) {
    $batchCeilingFinalEngine = 'sproc';
}
$batchNoCeilingChecks = (bool)($parsedBatchMeta['no_ceiling_checks'] ?? false);
$ceilingModeSummary = $batchNoCeilingChecks
    ? 'Ceiling checks OFF'
    : ('Redis precheck ON | Final ceiling engine: ' . $batchCeilingFinalEngine);
$ceilingModeDetail = $batchNoCeilingChecks
    ? 'This run skips both Redis precheck and the final ceiling check.'
    : (
        $batchCeilingFinalEngine === 'redis'
            ? 'This batch runner uses Redis for the precheck path and Redis for the final ceiling commit check.'
            : 'This batch runner uses Redis for the precheck path, then runs the final ceiling enforcement through spCheckCeilingBalance.'
    );

try {
    $monitorSummary = collectBatchMonitorData($conn, $monitorFy, $monitorVersion, $monitorType);
    $monitorRedisRows = $monitorSummary['redis']['rows'] ?? [];
    $monitorRecentFailures = $monitorSummary['recent_failures'] ?? [];
} catch (Throwable $e) {
    $monitorError = $e->getMessage();
}
?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars(\App\Shared\Lang::translateLiteral('Batch Runner'), ENT_QUOTES, 'UTF-8') ?></title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/icons/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --batch-shell-max: 1440px;
        }
        .page-shell {
            max-width: var(--batch-shell-max);
            margin: 0 auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 1rem;
        }
        .page-title {
            margin: 0;
            font-size: 1.75rem;
            line-height: 1.15;
            font-weight: 700;
        }
        .page-subtitle {
            margin-top: .35rem;
            color: var(--bs-secondary-color);
            font-size: .95rem;
        }
        .stack-gap > * + * { margin-top: 1rem; }
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .75rem;
        }
        .section-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
        }
        .section-body { padding: 1rem; }
        .field label {
            display: block;
            margin-bottom: .35rem;
            font-size: .8125rem;
            font-weight: 700;
        }
        .check-stack {
            display: grid;
            gap: .625rem;
            margin-top: 1rem;
        }
        .check-stack label {
            display: flex;
            align-items: center;
            gap: .625rem;
            margin: 0;
            font-weight: 500;
        }
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: .625rem;
            margin-top: 1rem;
        }
        code { background: var(--bs-gray-200); padding: 2px 6px; border-radius: 4px; }
        .batch-banner {
            padding: .9rem 1rem;
            border-radius: .5rem;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-light);
        }
        .batch-banner.running { background: #eefaf2; border-color: #9dd7af; }
        .batch-banner.completed { background: #eef5ff; border-color: #9ec5fe; }
        .batch-banner.cancelled { background: #fff7e8; border-color: #f2c078; }
        .banner-title { font-weight: 700; }
        .redis-panel {
            padding: .9rem 1rem;
            border-radius: .5rem;
            border: 1px solid var(--bs-border-color);
            background: var(--bs-light);
        }
        .redis-panel.ok { background: #eef5ff; border-color: #9ec5fe; }
        .redis-panel.warn { background: #fff7e8; border-color: #f2c078; }
        .redis-panel.bad { background: #fff1f1; border-color: #f1aeb5; }
        .kv-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; margin-top: 10px; }
        .kv-grid .label { font-weight: 700; }
        .summary-panel {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .75rem;
        }
        .summary-stat {
            padding: .9rem;
            border: 1px solid var(--bs-border-color);
            border-radius: .5rem;
            background: var(--bs-light);
        }
        .summary-stat .label {
            font-size: .75rem;
            font-weight: 700;
            color: var(--bs-secondary-color);
            text-transform: uppercase;
        }
        .summary-stat .value {
            margin-top: 6px;
            font-size: 1.35rem;
            font-weight: 700;
        }
        .summary-stat .subvalue {
            margin-top: 4px;
            color: var(--bs-secondary-color);
            font-size: .8rem;
        }
        .progress-block + .progress-block { margin-top: 12px; }
        .progress-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6px;
            font-size: .875rem;
        }
        .progress-track {
            background: var(--bs-gray-300);
            border-radius: 999px;
            overflow: hidden;
            height: 12px;
        }
        .progress-bar {
            height: 12px;
            border-radius: 999px;
            background: linear-gradient(90deg, #0d6efd, #3d8bfd);
        }
        .indeterminate { position: relative; background: var(--bs-gray-300); border-radius: 6px; overflow: hidden; height: 14px; margin-top: 8px; }
        .indeterminate::before { content: ""; position: absolute; top: 0; left: -35%; width: 35%; height: 100%; background: linear-gradient(90deg, #16a34a, #22c55e); animation: batch-slide 1.4s linear infinite; }
        .table-wrap {
            border: 1px solid var(--bs-border-color);
            border-radius: .5rem;
            overflow: auto;
        }
        .table-wrap table { margin-bottom: 0; }
        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 999px;
            padding: .25rem .6rem;
            font-size: .75rem;
            font-weight: 700;
            background: #eef5ff;
            color: #0b5ed7;
        }
        .status-chip.warn {
            background: #fff4db;
            color: #a16207;
        }
        .status-chip.bad {
            background: #ffe4e6;
            color: #be123c;
        }
        pre {
            margin: 0;
            background: #0f172a;
            color: #e5eefc;
            padding: 16px;
            border-radius: 10px;
            max-height: 420px;
            overflow: auto;
            font-size: 12px;
            line-height: 1.5;
        }
        @keyframes batch-slide { from { left: -35%; } to { left: 100%; } }
        @media (max-width: 980px) {
            .summary-panel, .metric-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 700px) {
            .page-header, .section-head, .progress-head { flex-direction: column; align-items: flex-start; }
            .kv-grid { grid-template-columns: 1fr; }
            .toolbar { flex-direction: column; }
            .toolbar > * { width: 100%; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-3">
    <div class="page-shell">
        <div class="page-header">
            <div>
                <h1 class="page-title">Parallel Batch Runner</h1>
                <div class="page-subtitle">Run transaction-input batch processing with the same cleaner CBMS screen structure used elsewhere in the app.</div>
            </div>
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to CBMS Main
            </a>
        </div>

        <div class="card shadow-sm mt-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong><i class="bi bi-diagram-3 me-2"></i>Batch Operations</strong>
                <div class="text-muted small">Parallel shard execution, Redis health visibility, and live log progress.</div>
            </div>
            <div class="card-body">
                <?php if ($status): ?>
                    <div class="alert alert-primary py-2 mb-3"><?= htmlspecialchars($status) ?></div>
                <?php endif; ?>
                <?php if ($cancelNotice): ?>
                    <div class="alert alert-warning py-2 mb-3"><?= htmlspecialchars($cancelNotice) ?></div>
                <?php endif; ?>
                <?php if ($activeRunWarning !== ''): ?>
                    <div class="alert alert-warning py-2 mb-3"><?= htmlspecialchars($activeRunWarning) ?></div>
                <?php endif; ?>
                <?php if ($controlNotice !== ''): ?>
                    <div class="alert alert-success py-2 mb-3"><?= htmlspecialchars($controlNotice) ?></div>
                <?php endif; ?>
                <?php if ($controlError !== ''): ?>
                    <div class="alert alert-danger py-2 mb-3"><?= htmlspecialchars($controlError) ?></div>
                <?php endif; ?>

                <div class="row g-3">
                    <div class="col-xl-7">
                        <div class="stack-gap">
                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <div class="section-head w-100">
                                <h3 class="section-title">Run Setup</h3>
                                <div class="text-muted small">Start a new batch with the current operational options.</div>
                            </div>
                            </div>
                            <div class="section-body">
                                <form method="post">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6 field">
                                            <label>Fiscal Year</label>
                                            <input type="number" class="form-control" name="fy" value="<?= (int)$monitorFy ?>">
                                        </div>
                                        <div class="col-md-6 field">
                                            <label>Version</label>
                                            <input type="number" class="form-control" name="version" value="<?= (int)$monitorVersion ?>">
                                        </div>
                                        <div class="col-md-6 field">
                                            <label>Shards</label>
                                            <input type="number" class="form-control" name="shards" value="4" min="1">
                                        </div>
                                        <div class="col-md-6 field">
                                            <label>Deadlock Retries</label>
                                            <input type="number" class="form-control" name="retry" value="15" min="0">
                                        </div>
                                        <div class="col-12 field">
                                            <label>Transaction Type</label>
                                            <input type="text" class="form-control" name="type" value="<?= htmlspecialchars($monitorType) ?>" placeholder="Optional, e.g. ESAL">
                                        </div>
                                        <div class="col-md-6 field">
                                            <label>Final Ceiling Engine</label>
                                            <select class="form-select" name="ceiling_final_engine">
                                                <option value="sproc" <?= $batchCeilingFinalEngine === 'sproc' ? 'selected' : '' ?>>sproc</option>
                                                <option value="redis" <?= $batchCeilingFinalEngine === 'redis' ? 'selected' : '' ?>>redis</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="check-stack">
                                        <label><input class="form-check-input mt-0" type="checkbox" name="invalidate"> Invalidate cache scope before processing</label>
                                        <label><input class="form-check-input mt-0" type="checkbox" name="no_ceiling_checks"> Disable ceiling checks for this run</label>
                                        <label><input class="form-check-input mt-0" type="checkbox" name="post_run_recovery" checked> Post-run retry for <code>ERROR BEFORE CEILING CHECK</code></label>
                                    </div>
                                    <div class="text-muted small mt-2">Batch mode always keeps <strong>Redis precheck ON</strong>. Choose whether the final enforcing check uses <strong>sproc</strong> or <strong>redis</strong>. Turn on <strong>Disable ceiling checks</strong> only for debugging.</div>
                                    <div class="toolbar">
                                        <button class="btn btn-primary" type="submit"><i class="bi bi-play-circle me-1"></i>Start Batch Run</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if (file_exists($lockFile)): ?>
                            <div class="card shadow-sm">
                                <div class="card-header bg-white">
                                    <div class="section-head w-100">
                                    <h3 class="section-title">Lock Recovery</h3>
                                    <div class="text-muted small">Use this only when a stale batch lock blocks a new run.</div>
                                </div>
                                </div>
                                <div class="section-body">
                                    <form method="post">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="action" value="force_unlock">
                                        <button class="btn btn-outline-warning" type="submit"><i class="bi bi-unlock me-1"></i>Force Clear Batch Lock</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <div class="section-head w-100">
                                <h3 class="section-title">Operational Links</h3>
                                <div class="text-muted small">Drill into the dedicated ceiling tools when you need deeper analysis.</div>
                            </div>
                            </div>
                            <div class="section-body">
                                <div class="toolbar mt-0">
                                    <a class="btn btn-outline-secondary" href="index.php?route=ceilings/balances&limit=500">
                                        <i class="bi bi-table me-1"></i>Ceiling Balances
                                    </a>
                                    <a class="btn btn-outline-secondary" href="index.php?route=ceilings/balances-keys&limit=500">
                                        <i class="bi bi-key me-1"></i>Redis Ceiling Keys
                                    </a>
                                    <a class="btn btn-outline-secondary" href="ceiling_monitor.php?limit=200&recent_limit=100" target="_blank" rel="noopener">
                                        <i class="bi bi-activity me-1"></i>Ceiling Monitor
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <div class="section-head w-100">
                                <h3 class="section-title">Ceiling Controls</h3>
                                <div class="text-muted small">Corrective actions for the selected FY/version when you need to reseed or reconcile ceiling state.</div>
                            </div>
                            </div>
                            <div class="section-body">
                                <form method="post">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6 field">
                                            <label>Control Fiscal Year</label>
                                            <input type="number" class="form-control" name="control_fy" value="<?= (int)$monitorFy ?>">
                                        </div>
                                        <div class="col-md-6 field">
                                            <label>Control Version</label>
                                            <input type="number" class="form-control" name="control_version" value="<?= (int)$monitorVersion ?>">
                                        </div>
                                    </div>
                                    <div class="toolbar">
                                        <button class="btn btn-danger" type="submit" name="action" value="full_ceiling_reset" onclick="return confirm('Run a full ceiling reset for this FY/version: clear transaction ceiling warnings, reset balances to definitions, clear Redis keys, and warm Redis again?');">
                                            <i class="bi bi-shield-fill-exclamation me-1"></i>Full Ceiling Reset
                                        </button>
                                        <button class="btn btn-outline-dark" type="submit" name="action" value="reset_tx_ceiling_state" onclick="return confirm('Clear ceiling warnings, statuses, and applied ceiling amounts from tblTransactionInput for this FY/version?');">
                                            <i class="bi bi-eraser me-1"></i>Reset Tx Ceiling Warnings
                                        </button>
                                        <button class="btn btn-primary" type="submit" name="action" value="reset_then_warmup" onclick="return confirm('Reset balances to ceilings, clear Redis ceiling keys, then warm Redis again for this FY/version?');">
                                            <i class="bi bi-magic me-1"></i>Reset + Warmup
                                        </button>
                                        <button class="btn btn-outline-primary" type="submit" name="action" value="warmup_cache">
                                            <i class="bi bi-fire me-1"></i>Warmup Redis Cache
                                        </button>
                                        <button class="btn btn-outline-secondary" type="submit" name="action" value="sync_from_redis">
                                            <i class="bi bi-arrow-left-right me-1"></i>Sync Redis To DB
                                        </button>
                                        <button class="btn btn-outline-warning" type="submit" name="action" value="reload_balances">
                                            <i class="bi bi-arrow-repeat me-1"></i>Reload DB Balances
                                        </button>
                                        <button class="btn btn-outline-danger" type="submit" name="action" value="reset_balances_clear_redis" onclick="return confirm('Reset balances to ceiling definitions and clear Redis keys for ceilings?');">
                                            <i class="bi bi-exclamation-triangle me-1"></i>Reset DB + Clear Redis
                                        </button>
                                    </div>
                                    <div class="text-muted small mt-2">Fastest clean restart after fixing ceiling definitions: <strong>Full Ceiling Reset</strong>. Use the smaller actions only when you need a partial repair.</div>
                                </form>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="col-xl-5">
                        <div class="stack-gap">
                        <?php if ($redisStatus !== null): ?>
                            <?php
                                $redisPanelClass = !$redisStatus['connected']
                                    ? 'bad'
                                    : (($redisStatus['risk'] ?? 'unknown') === 'write_blocked' ? 'warn' : 'ok');
                                $redisHeadline = !$redisStatus['connected']
                                    ? 'Redis unavailable'
                                    : (($redisStatus['risk'] ?? 'unknown') === 'write_blocked' ? 'Redis connected, writes at risk' : 'Redis healthy');
                            ?>
                            <div class="card shadow-sm">
                                <div class="card-header bg-white">
                                    <div class="section-head w-100">
                                    <h3 class="section-title">Redis Status</h3>
                                    <div class="text-muted small">Live persistence and write-safety check before a run starts.</div>
                                </div>
                                </div>
                                <div class="section-body">
                                    <div class="redis-panel <?= htmlspecialchars($redisPanelClass) ?>">
                                        <div class="banner-title"><?= htmlspecialchars($redisHeadline) ?></div>
                                        <div class="text-muted small mt-1"><?= htmlspecialchars((string)($redisStatus['message'] ?? '')) ?></div>
                                        <div class="kv-grid">
                                            <div><span class="label">Source:</span> <?= htmlspecialchars((string)($redisStatus['source'] ?? '-')) ?></div>
                                            <div><span class="label">Connected:</span> <?= !empty($redisStatus['connected']) ? 'yes' : 'no' ?></div>
                                            <div><span class="label">Save Policy:</span> <code><?= htmlspecialchars((string)($redisStatus['save_policy'] ?? '-')) ?></code></div>
                                            <div><span class="label">Stop Writes On BGSAVE Error:</span> <?= htmlspecialchars((string)($redisStatus['stop_writes_on_bgsave_error'] ?? '-')) ?></div>
                                            <div><span class="label">Last BGSAVE:</span> <?= htmlspecialchars((string)($redisStatus['rdb_last_bgsave_status'] ?? '-')) ?></div>
                                            <div><span class="label">Changes Since Save:</span> <?= htmlspecialchars((string)($redisStatus['rdb_changes_since_last_save'] ?? '-')) ?></div>
                                            <div><span class="label">Last Save Time:</span> <?= htmlspecialchars((string)($redisStatus['rdb_last_save_time'] ?? '-')) ?></div>
                                            <div><span class="label">Loading:</span> <?= htmlspecialchars((string)($redisStatus['loading'] ?? '-')) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card shadow-sm">
                            <div class="card-header bg-white">
                                <div class="section-head w-100">
                                <h3 class="section-title">Current Run</h3>
                                <div class="text-muted small">Latest active or selected batch log summary.</div>
                            </div>
                            </div>
                            <div class="section-body">
                                <?php if ($displayLogPath): ?>
                                    <?php $bannerClass = $batchCancelled ? 'cancelled' : ($isBatchRunning ? 'running' : 'completed'); ?>
                                    <div class="batch-banner <?= $bannerClass ?>">
                                        <div class="banner-title"><?= htmlspecialchars($batchStateLabel) ?></div>
                                        <div class="kv-grid">
                                            <div><span class="label">Log file:</span> <code><?= htmlspecialchars($displayLogPath) ?></code></div>
                                            <div><span class="label">Status:</span> <?= htmlspecialchars($batchStateLabel) ?></div>
                                            <div><span class="label">Ceiling mode:</span> <?= htmlspecialchars($ceilingModeSummary) ?></div>
                                            <div><span class="label">Started:</span> <?= htmlspecialchars((string)($batchStart ?? '-')) ?></div>
                                            <div><span class="label">Last heartbeat:</span> <?= htmlspecialchars((string)($heartbeatAt ?? ($progress['last_update'] ?? '-'))) ?></div>
                                            <div><span class="label">Elapsed:</span> <?= htmlspecialchars($elapsedSeconds !== null ? fmtDurationSeconds($elapsedSeconds) : '-') ?></div>
                                            <div><span class="label">ETA:</span> <?= htmlspecialchars($etaSeconds !== null ? fmtDurationSeconds($etaSeconds) : '-') ?></div>
                                        </div>
                                        <div class="text-muted small mt-2"><?= htmlspecialchars($ceilingModeDetail) ?></div>
                                        <div class="toolbar">
                                            <a href="?log=<?= urlencode($displayLogPath) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-text me-1"></i>Open Log View</a>
                                            <?php if ($isBatchRunning && $stopFile): ?>
                                                <form method="post">
                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="stop_file" value="<?= htmlspecialchars($stopFile) ?>">
                                                    <button class="btn btn-danger btn-sm" type="submit"><i class="bi bi-stop-circle me-1"></i>Cancel Batch</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($isBatchRunning && (!$recordProgress || (int)($recordProgress['processed'] ?? 0) === 0)): ?>
                                            <div class="indeterminate"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted small">No batch log is selected yet. Start a run to populate live status and progress.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <div class="section-head w-100">
                        <h3 class="section-title">Run Scope Health</h3>
                        <div class="text-muted small">Live transaction and Redis ceiling state for FY <?= (int)$monitorFy ?> / Version <?= (int)$monitorVersion ?><?= $monitorType !== '' ? ' / Type ' . htmlspecialchars($monitorType) : '' ?>.</div>
                    </div>
                    </div>
                    <div class="section-body">
                        <?php if ($monitorError !== ''): ?>
                            <div class="alert alert-warning py-2 mb-0"><?= htmlspecialchars($monitorError) ?></div>
                        <?php elseif ($monitorSummary !== null): ?>
                            <div class="metric-grid">
                                <div class="summary-stat">
                                    <div class="label">Transactions</div>
                                    <div class="value"><?= number_format((int)$monitorSummary['tx']['total_rows']) ?></div>
                                    <div class="subvalue">Head rows <?= number_format((int)$monitorSummary['tx']['head_rows']) ?></div>
                                </div>
                                <div class="summary-stat">
                                    <div class="label">Checked</div>
                                    <div class="value"><?= number_format((int)$monitorSummary['tx']['checked_rows']) ?></div>
                                    <div class="subvalue">Pending <?= number_format((int)$monitorSummary['tx']['pending_rows']) ?></div>
                                </div>
                                <div class="summary-stat">
                                    <div class="label">Successful</div>
                                    <div class="value"><?= number_format((int)$monitorSummary['tx']['success_rows']) ?></div>
                                    <div class="subvalue">Rows marked successful</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="label">Failures</div>
                                    <div class="value"><?= number_format((int)$monitorSummary['tx']['failed_rows']) ?></div>
                                    <div class="subvalue">Error-before-check <?= number_format((int)$monitorSummary['tx']['error_before_check_rows']) ?></div>
                                </div>
                            </div>

                            <div class="summary-panel" style="margin-top:16px;">
                                <div class="summary-stat">
                                    <div class="label">Redis Keys Loaded</div>
                                    <div class="value"><?= number_format((int)$monitorSummary['redis']['keys_loaded']) ?></div>
                                    <div class="subvalue">Expected definitions <?= number_format((int)$monitorSummary['redis']['definitions_expected']) ?></div>
                                </div>
                                <div class="summary-stat">
                                    <div class="label">Missing Redis Keys</div>
                                    <div class="value"><?= number_format((int)$monitorSummary['redis']['missing_keys']) ?></div>
                                    <div class="subvalue">Warmup if this is not zero</div>
                                </div>
                                <div class="summary-stat">
                                    <div class="label">Potential Breaches</div>
                                    <div class="value"><?= number_format((int)$monitorSummary['redis']['breach_count']) ?></div>
                                    <div class="subvalue">Balance <?= number_format((float)$monitorSummary['redis']['total_balance'], 2) ?> / Ceiling <?= number_format((float)$monitorSummary['redis']['total_ceiling'], 2) ?></div>
                                </div>
                            </div>

                            <div class="toolbar">
                                <span class="status-chip <?= (int)$monitorSummary['redis']['missing_keys'] > 0 ? 'warn' : '' ?>">
                                    Redis coverage <?= (int)$monitorSummary['redis']['definitions_expected'] > 0 ? (int)round((((int)$monitorSummary['redis']['keys_loaded']) / max(1, (int)$monitorSummary['redis']['definitions_expected'])) * 100) : 0 ?>%
                                </span>
                                <?php if ((int)$monitorSummary['tx']['failed_rows'] > 0): ?>
                                    <span class="status-chip bad">Ceiling failures present</span>
                                <?php endif; ?>
                                <?php if ((int)$monitorSummary['tx']['error_before_check_rows'] > 0): ?>
                                    <span class="status-chip warn">Recovery candidates present</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($progress || $isBatchRunning): ?>
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <div class="section-head w-100">
                            <h3 class="section-title">Progress</h3>
                            <div class="text-muted small"><?= $isBatchRunning ? 'Live progress refreshes every 15 seconds.' : 'Latest captured progress from the selected log.' ?></div>
                        </div>
                        </div>
                        <div class="section-body">
                            <?php if ($progress): ?>
                                <div class="summary-panel">
                                    <div class="summary-stat">
                                        <div class="label">Batch State</div>
                                        <div class="value"><?= htmlspecialchars($batchStateLabel) ?></div>
                                        <div class="subvalue"><?= $runningShardCount !== null ? 'Active shards: ' . (int)$runningShardCount : 'Waiting for shard updates' ?></div>
                                    </div>
                                    <div class="summary-stat">
                                        <div class="label">Shard Completion</div>
                                        <div class="value"><?= (int)$progress['done'] ?> / <?= (int)$progress['total'] ?></div>
                                        <div class="subvalue">Completed shards</div>
                                    </div>
                                    <div class="summary-stat">
                                        <div class="label">Timing</div>
                                        <div class="value"><?= htmlspecialchars($elapsedSeconds !== null ? fmtDurationSeconds($elapsedSeconds) : '-') ?></div>
                                        <div class="subvalue"><?= $etaSeconds !== null ? 'ETA ' . fmtDurationSeconds($etaSeconds) : 'No ETA yet' ?></div>
                                    </div>
                                </div>

                                <div class="progress-block" style="margin-top:16px;">
                                    <?php $pct = $progress['total'] > 0 ? (int)round(($progress['done'] / $progress['total']) * 100) : 0; ?>
                                    <div class="progress-head">
                                        <span>Shard progress</span>
                                        <span><?= $pct ?>%</span>
                                    </div>
                                    <div class="progress-track"><div class="progress-bar" style="width:<?= $pct ?>%;"></div></div>
                                </div>

                                <?php if ($recordProgress && (int)$recordProgress['total'] > 0): ?>
                                    <?php $recordPct = (int)round(((int)$recordProgress['processed'] / max(1, (int)$recordProgress['total'])) * 100); ?>
                                    <div class="progress-block">
                                        <div class="progress-head">
                                            <span>Records <?= number_format((int)$recordProgress['processed']) ?> / <?= number_format((int)$recordProgress['total']) ?></span>
                                            <span><?= $recordPct ?>%</span>
                                        </div>
                                        <div class="progress-track"><div class="progress-bar" style="width:<?= $recordPct ?>%;"></div></div>
                                        <div class="text-muted small mt-2">Success <?= number_format((int)$recordProgress['ok']) ?> | Failed <?= number_format((int)$recordProgress['fail']) ?></div>
                                    </div>
                                <?php endif; ?>

                                <?php if ($performanceSummary !== null): ?>
                                    <div class="summary-panel" style="margin-top:16px;">
                                        <div class="summary-stat">
                                            <div class="label">Avg Tx Time</div>
                                            <div class="value"><?= number_format((int)$performanceSummary['tx_avg_ms']) ?> ms</div>
                                            <div class="subvalue">Average across <?= number_format((int)$performanceSummary['shards']) ?> shard summaries</div>
                                        </div>
                                        <div class="summary-stat">
                                            <div class="label">Ceiling Time</div>
                                            <div class="value"><?= number_format((int)$performanceSummary['ceiling_total_ms']) ?> ms</div>
                                            <div class="subvalue">Snapshot + precheck + final check + sync-back</div>
                                        </div>
                                        <div class="summary-stat">
                                            <div class="label">DB / Redis Ops</div>
                                            <div class="value"><?= number_format((int)$performanceSummary['db_queries']) ?></div>
                                            <div class="subvalue">Redis gets <?= number_format((int)$performanceSummary['redis_gets']) ?> | sets <?= number_format((int)$performanceSummary['redis_sets']) ?></div>
                                        </div>
                                    </div>

                                    <div class="table-wrap" style="margin-top:16px;">
                                        <table class="table table-sm align-middle mb-0">
                                            <tr>
                                                <th>Shard</th>
                                                <th class="text-end">Avg Tx ms</th>
                                                <th class="text-end">Snapshot ms</th>
                                                <th class="text-end">Precheck ms</th>
                                                <th class="text-end">Final sproc ms</th>
                                                <th class="text-end">Final redis ms</th>
                                                <th class="text-end">Sync-back ms</th>
                                            </tr>
                                            <?php ksort($shardMetrics); foreach ($shardMetrics as $idx => $row): ?>
                                                <tr>
                                                    <td><?= (int)$idx ?></td>
                                                    <td class="text-end"><?= number_format((int)($row['tx_avg_ms'] ?? 0)) ?></td>
                                                    <td class="text-end"><?= number_format((int)($row['ceiling_snapshot_ms'] ?? 0)) ?></td>
                                                    <td class="text-end"><?= number_format((int)($row['ceiling_precheck_ms'] ?? 0)) ?></td>
                                                    <td class="text-end"><?= number_format((int)($row['ceiling_final_sproc_ms'] ?? 0)) ?></td>
                                                    <td class="text-end"><?= number_format((int)($row['ceiling_final_redis_ms'] ?? 0)) ?></td>
                                                    <td class="text-end"><?= number_format((int)($row['ceiling_sync_back_ms'] ?? 0)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="text-muted small">Batch is running. Waiting for shard progress details to be parsed from the active log.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($monitorSummary !== null): ?>
                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-white">
                                    <div class="section-head w-100">
                                    <h3 class="section-title">Failure Breakdown</h3>
                                    <div class="text-muted small">Grouped view of the current ceiling issues so we can spot the dominant cause quickly.</div>
                                </div>
                                </div>
                                <div class="section-body">
                                    <div class="row g-3">
                                        <div class="col-xl-3 col-md-6">
                                            <div class="table-wrap">
                                                <table class="table table-sm align-middle mb-0">
                                                    <tr><th>By Check</th><th class="text-end">Rows</th></tr>
                                                    <?php foreach (($monitorSummary['failure_breakdown']['by_reason'] ?? []) as $row): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars((string)($row['BreakdownKey'] ?? '-')) ?></td>
                                                            <td class="text-end"><?= number_format((int)($row['FailureCount'] ?? 0)) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-md-6">
                                            <div class="table-wrap">
                                                <table class="table table-sm align-middle mb-0">
                                                    <tr><th>By Engine</th><th class="text-end">Rows</th></tr>
                                                    <?php foreach (($monitorSummary['failure_breakdown']['by_engine'] ?? []) as $row): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars((string)($row['BreakdownKey'] ?? '-')) ?></td>
                                                            <td class="text-end"><?= number_format((int)($row['FailureCount'] ?? 0)) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-md-6">
                                            <div class="table-wrap">
                                                <table class="table table-sm align-middle mb-0">
                                                    <tr><th>By Definition</th><th class="text-end">Rows</th></tr>
                                                    <?php foreach (($monitorSummary['failure_breakdown']['by_definition'] ?? []) as $row): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars((string)($row['BreakdownKey'] ?? '-')) ?></td>
                                                            <td class="text-end"><?= number_format((int)($row['FailureCount'] ?? 0)) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-md-6">
                                            <div class="table-wrap">
                                                <table class="table table-sm align-middle mb-0">
                                                    <tr><th>By Tx Type</th><th class="text-end">Rows</th></tr>
                                                    <?php foreach (($monitorSummary['failure_breakdown']['by_transaction_type'] ?? []) as $row): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars((string)($row['BreakdownKey'] ?? '-')) ?></td>
                                                            <td class="text-end"><?= number_format((int)($row['FailureCount'] ?? 0)) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6">
                            <div class="card shadow-sm h-100">
                                <div class="card-header bg-white">
                                    <div class="section-head w-100">
                                    <h3 class="section-title">Redis Ceiling Coverage</h3>
                                    <div class="text-muted small">Sample of loaded Redis ceiling balance keys for this run scope.</div>
                                </div>
                                </div>
                                <div class="section-body">
                                    <?php if (empty($monitorRedisRows)): ?>
                                        <div class="text-muted small">No Redis ceiling keys were found for the selected run scope.</div>
                                    <?php else: ?>
                                        <div class="table-wrap">
                                            <table class="table table-sm align-middle">
                                                <tr><th>Definition</th><th>Data Object</th><th>Type</th><th>Balance</th><th>Ceiling</th><th>Remaining</th><th>TTL</th></tr>
                                                <?php foreach ($monitorRedisRows as $row): ?>
                                                    <tr>
                                                        <td><?= (int)$row['ceiling_definition_id'] ?></td>
                                                        <td><?= htmlspecialchars((string)$row['data_object_code']) ?></td>
                                                        <td><?= htmlspecialchars((string)$row['transaction_type_code']) ?></td>
                                                        <td><?= number_format((float)$row['balance_total'], 2) ?></td>
                                                        <td><?= number_format((float)$row['ceiling_total'], 2) ?></td>
                                                        <td class="<?= (float)$row['remaining'] < 0 ? 'text-danger fw-semibold' : 'text-success fw-semibold' ?>">
                                                            <?= number_format((float)$row['remaining'], 2) ?>
                                                        </td>
                                                        <td><?= (int)$row['ttl'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-6">
                            <div class="card shadow-sm h-100">
                                <div class="card-header bg-white">
                                    <div class="section-head w-100">
                                    <h3 class="section-title">Recent Ceiling Issues</h3>
                                    <div class="text-muted small">Latest failed or pre-check error rows for the same FY/version/type.</div>
                                </div>
                                </div>
                                <div class="section-body">
                                    <?php if (empty($monitorRecentFailures)): ?>
                                        <div class="text-muted small">No recent ceiling failures were found for this run scope.</div>
                                    <?php else: ?>
                                        <div class="table-wrap">
                                            <table class="table table-sm align-middle">
                                                <tr><th>Tx</th><th>Head</th><th>Type</th><th>Status</th><th>Check</th><th>Engine</th><th>Checked</th></tr>
                                                <?php foreach ($monitorRecentFailures as $row): ?>
                                                    <tr>
                                                        <td><?= (int)($row['TransactionID'] ?? 0) ?></td>
                                                        <td><?= htmlspecialchars((string)($row['HeadRecordID'] ?? '-')) ?></td>
                                                        <td><?= htmlspecialchars((string)($row['TransactionTypeCode'] ?? '')) ?></td>
                                                        <td><?= htmlspecialchars((string)($row['CeilingStatus'] ?? '-')) ?></td>
                                                        <td><?= htmlspecialchars((string)($row['CeilingStatusCheck'] ?? '-')) ?></td>
                                                        <td><?= htmlspecialchars((string)($row['CeilingEngine'] ?? '-')) ?></td>
                                                        <td><?= htmlspecialchars((string)($row['CeilingLastCheckedDate'] ?? '-')) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($shardStatus)): ?>
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <div class="section-head w-100">
                            <h3 class="section-title">Shard Status</h3>
                            <div class="text-muted small">Per-shard totals and failure counts parsed from the batch log.</div>
                        </div>
                        </div>
                        <div class="section-body">
                            <div class="table-wrap">
                                <table class="table table-sm align-middle">
                                    <tr><th>Shard</th><th>Status</th><th>Total</th><th>Success</th><th>Failed</th><th>Elapsed (ms)</th></tr>
                                    <?php ksort($shardStatus); foreach ($shardStatus as $idx => $row): ?>
                                        <tr>
                                            <td><?= (int)$idx ?></td>
                                            <td><?= htmlspecialchars($row['status'] ?? 'unknown') ?></td>
                                            <td><?= htmlspecialchars((string)($row['total'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($row['ok'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($row['fail'] ?? '-')) ?></td>
                                            <td><?= htmlspecialchars((string)($row['elapsed'] ?? '-')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($tail): ?>
                    <div class="card shadow-sm mt-4">
                        <div class="card-header bg-white">
                            <div class="section-head w-100">
                            <h3 class="section-title">Log Tail</h3>
                            <div class="text-muted small"><?= $isBatchRunning ? 'Auto-refresh every 15 seconds while the batch is running.' : 'Latest captured log output for the selected batch.' ?></div>
                        </div>
                        </div>
                        <div class="section-body">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleLogTail">Show Log Tail</button>
                            <div id="logTailWrap" class="mt-3" style="display:none;">
                                <pre><?= htmlspecialchars($tail) ?></pre>
                            </div>
                        </div>
                    </div>
                    <script>
                        const toggleBtn = document.getElementById('toggleLogTail');
                        const logTailWrap = document.getElementById('logTailWrap');
                        if (toggleBtn && logTailWrap) {
                            toggleBtn.addEventListener('click', () => {
                                const isHidden = logTailWrap.style.display === 'none';
                                logTailWrap.style.display = isHidden ? 'block' : 'none';
                                toggleBtn.textContent = isHidden ? 'Hide Log Tail' : 'Show Log Tail';
                            });
                        }
                        <?php if ($isBatchRunning): ?>
                        setTimeout(() => location.reload(), 15000);
                        <?php endif; ?>
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
</body>
</html>

