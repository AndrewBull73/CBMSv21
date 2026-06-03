<?php
// Concurrent single-save load test runner UI.

declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN'],
    'csrfPost' => true,
]);

$repoRoot = dirname(__DIR__, 2);
$psScript = $repoRoot . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'load_test_single_save.ps1';
$logsDir = $repoRoot . DIRECTORY_SEPARATOR . 'logs';
$lockFile = $logsDir . DIRECTORY_SEPARATOR . 'single_save_load_test.lock';

$status = '';
$statusType = 'info';
$selectedLog = '';
$tail = '';
$stopFile = '';
$isRunning = false;
$hasActiveLock = false;
$activeRunnerCount = 0;

function countLoadTestProcesses(): int
{
    $cmd = "powershell -NoProfile -ExecutionPolicy Bypass -Command \"(Get-CimInstance Win32_Process | Where-Object { `\$_.ProcessId -ne `\$PID -and `\$_.Name -eq 'powershell.exe' -and `\$_.CommandLine -match '-File\\s+\"\"?.*load_test_single_save\\.ps1' }).Count\"";
    $out = @shell_exec($cmd);
    if ($out === null) {
        return 0;
    }
    return max(0, (int)trim($out));
}

function stopLoadTestProcesses(): int
{
    $cmd = "powershell -NoProfile -ExecutionPolicy Bypass -Command \"Get-CimInstance Win32_Process | Where-Object { `\$_.ProcessId -ne `\$PID -and `\$_.Name -eq 'powershell.exe' -and `\$_.CommandLine -match '-File\\s+\"\"?.*load_test_single_save\\.ps1' } | ForEach-Object { Stop-Process -Id `\$_.ProcessId -Force -ErrorAction SilentlyContinue }; (Get-CimInstance Win32_Process | Where-Object { `\$_.ProcessId -ne `\$PID -and `\$_.Name -eq 'powershell.exe' -and `\$_.CommandLine -match '-File\\s+\"\"?.*load_test_single_save\\.ps1' }).Count\"";
    $out = @shell_exec($cmd);
    if ($out === null) {
        return 0;
    }
    return max(0, (int)trim($out));
}

function parseLoadTestAssessment(string $logPath): ?array
{
    if (!is_file($logPath)) {
        return null;
    }

    $lines = @file($logPath, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return null;
    }

    $start = null;
    $planTotalOps = null;
    $done = null;
    $cancelled = false;
    $stopPath = '';

    foreach ($lines as $line) {
        if (preg_match('/Load test start \| Fy=(\d+) Version=(\d+) Workers=(\d+) IterationsPerWorker=(\d+) Candidates=(\d+)(?:\s+Scenario=([a-z_]+)\s+PerCeilingLimit=(\d+)\s+ThinkTimeMinMs=(\d+)\s+ThinkTimeMaxMs=(\d+)\s+OnlyCeilingStatusNull=(True|False))?\s+CeilingEngine=([a-z]+)\s+NoCeilingChecks=(True|False)/i', $line, $m)) {
            $start = [
                'fy' => (int)$m[1],
                'version' => (int)$m[2],
                'workers' => (int)$m[3],
                'iterations' => (int)$m[4],
                'candidates' => (int)$m[5],
                'scenario' => isset($m[6]) && $m[6] !== '' ? strtolower((string)$m[6]) : 'shuffle',
                'per_ceiling_limit' => isset($m[7]) && $m[7] !== '' ? (int)$m[7] : 0,
                'think_min_ms' => isset($m[8]) && $m[8] !== '' ? (int)$m[8] : 0,
                'think_max_ms' => isset($m[9]) && $m[9] !== '' ? (int)$m[9] : 0,
                'only_ceiling_status_null' => isset($m[10]) && $m[10] !== '' ? (strtolower((string)$m[10]) === 'true') : false,
                'engine' => strtolower((string)$m[11]),
                'no_ceiling' => (strtolower((string)$m[12]) === 'true'),
            ];
        }
        if (preg_match('/Load plan \| TotalOps=(\d+) PlannedUniqueTx=(\d+)/i', $line, $m)) {
            $planTotalOps = (int)$m[1];
        }
        if (preg_match('/Load test stop file \| Path=(.+)$/', $line, $m)) {
            $stopPath = trim((string)$m[1]);
        }
        if (preg_match('/Load test complete \| Total=(\d+) Ok=(\d+) Failed=(\d+) DeadlockTagged=(\d+) CeilingFailedTagged=(\d+) AvgMs=([0-9,.]+) ElapsedMs=(\d+)/i', $line, $m)) {
            $done = [
                'total' => (int)$m[1],
                'ok' => (int)$m[2],
                'failed' => (int)$m[3],
                'deadlock' => (int)$m[4],
                'ceiling_failed' => (int)$m[5],
                'avg_ms' => (float)str_replace(',', '', (string)$m[6]),
                'elapsed_ms' => (int)$m[7],
            ];
        }
        if (stripos($line, 'Load test cancelled') !== false) {
            $cancelled = true;
        }
    }

    $csvPath = $logPath . '.csv';
    $csvRows = 0;
    if (is_file($csvPath)) {
        $count = 0;
        $fh = @fopen($csvPath, 'r');
        if ($fh !== false) {
            while (!feof($fh)) {
                $row = fgets($fh);
                if ($row !== false) {
                    $count++;
                }
            }
            fclose($fh);
        }
        $csvRows = max(0, $count - 1);
    }

    $expected = null;
    if ($start !== null) {
        $expected = $start['workers'] * $start['iterations'];
    }
    if ($planTotalOps !== null) {
        $expected = $planTotalOps;
    }

    $txPerSec = null;
    if ($done !== null && $done['elapsed_ms'] > 0) {
        $txPerSec = round(((float)$done['total'] / ((float)$done['elapsed_ms'] / 1000.0)), 2);
    }

    $verdict = 'RUNNING';
    if ($cancelled) {
        $verdict = 'CANCELLED';
    } elseif ($done !== null) {
        $verdict = ($done['failed'] === 0 && $done['deadlock'] === 0) ? 'PASS' : 'REVIEW';
        if ($expected !== null && $done['total'] !== $expected) {
            $verdict = 'REVIEW';
        }
    }

    return [
        'start' => $start,
        'done' => $done,
        'expected' => $expected,
        'csv_rows' => $csvRows,
        'tx_per_sec' => $txPerSec,
        'verdict' => $verdict,
        'csv_path' => $csvPath,
        'log_mtime' => filemtime($logPath) ?: null,
        'stop_path' => $stopPath,
    ];
}

function getLatestLogPath(string $logsDir): string
{
    $files = glob($logsDir . DIRECTORY_SEPARATOR . 'single_save_load_test_*.log') ?: [];
    if (empty($files)) {
        return '';
    }
    usort($files, static fn($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    return (string)$files[0];
}

function getRecentRuns(string $logsDir, int $limit = 10): array
{
    $files = glob($logsDir . DIRECTORY_SEPARATOR . 'single_save_load_test_*.log') ?: [];
    usort($files, static fn($a, $b) => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    $rows = [];
    foreach (array_slice($files, 0, $limit) as $f) {
        $a = parseLoadTestAssessment($f);
        if ($a === null) {
            continue;
        }
        $rows[] = ['log' => $f, 'assessment' => $a];
    }
    return $rows;
}

if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0777, true);
}

if (file_exists($lockFile)) {
    $hasActiveLock = true;
    $lockLines = @file($lockFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lockLines as $line) {
        if (str_starts_with($line, 'log=')) {
            $selectedLog = substr($line, 4);
        } elseif (str_starts_with($line, 'stop=')) {
            $stopFile = substr($line, 5);
        }
    }
    $activeRunnerCount = countLoadTestProcesses();
    $isRunning = ($activeRunnerCount > 0);
    if (!$isRunning) {
        @unlink($lockFile);
        $hasActiveLock = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'start');
    $redirectAfterPost = false;

    if ($action === 'force_unlock') {
        @unlink($lockFile);
        $isRunning = false;
        $status = 'Lock cleared.';
        $statusType = 'warn';
        $redirectAfterPost = true;
    } elseif ($action === 'emergency_stop') {
        $remaining = stopLoadTestProcesses();
        @unlink($lockFile);
        $hasActiveLock = false;
        $isRunning = false;
        $activeRunnerCount = max(0, $remaining);
        $status = $remaining === 0 ? 'All load test runner processes stopped.' : 'Some runner processes may still be active.';
        $statusType = 'warn';
        $redirectAfterPost = true;
    } elseif ($action === 'cancel') {
        $requestedStop = (string)($_POST['stop_file'] ?? '');
        $logDir = realpath($logsDir);
        $candidate = realpath(dirname($requestedStop));
        if ($logDir && $candidate && str_starts_with($candidate, $logDir)) {
            @file_put_contents($requestedStop, "stop\n");
            $remaining = stopLoadTestProcesses();
            if ($remaining === 0) {
                @unlink($lockFile);
                $isRunning = false;
                $status = 'Load test cancelled.';
                $statusType = 'warn';
            } else {
                $status = 'Cancel requested. Waiting for workers to stop...';
                $statusType = 'warn';
            }
            $stopFile = $requestedStop;
        } else {
            $status = 'Unable to locate stop file for running load test.';
            $statusType = 'error';
        }
        $redirectAfterPost = true;
    } else {
        if (!file_exists($psScript)) {
            $status = 'PowerShell script not found: scripts/load_test_single_save.ps1';
            $statusType = 'error';
        } elseif (file_exists($lockFile) && countLoadTestProcesses() > 0) {
            $status = 'A load test is already running.';
            $statusType = 'error';
        } else {
            $fy = max(1, (int)($_POST['fy'] ?? 2026));
            $version = max(1, (int)($_POST['version'] ?? 5));
            $workers = max(1, (int)($_POST['workers'] ?? 4));
            $iterations = max(1, (int)($_POST['iterations'] ?? 20));
            $retry = max(0, (int)($_POST['retry'] ?? 15));
            $maxCandidates = max(1, (int)($_POST['max_candidates'] ?? 2000));
            $scenario = strtolower(trim((string)($_POST['scenario'] ?? 'shuffle')));
            if (!in_array($scenario, ['shuffle', 'spread_ceiling'], true)) {
                $scenario = 'shuffle';
            }
            $perCeilingLimit = max(1, (int)($_POST['per_ceiling_limit'] ?? 5));
            $thinkMinMs = max(0, (int)($_POST['think_min_ms'] ?? 0));
            $thinkMaxMs = max(0, (int)($_POST['think_max_ms'] ?? 0));
            if ($thinkMaxMs < $thinkMinMs) {
                $thinkMaxMs = $thinkMinMs;
            }
            $onlyCeilingStatusNull = !empty($_POST['only_ceiling_status_null']) ? ' -OnlyCeilingStatusNull' : '';
            $type = trim((string)($_POST['type'] ?? ''));
            $txIds = trim((string)($_POST['txids'] ?? ''));
            $minTx = max(0, (int)($_POST['min_tx'] ?? 0));
            $maxTx = max(0, (int)($_POST['max_tx'] ?? 0));
            $engine = strtolower(trim((string)($_POST['engine'] ?? 'sproc')));
            if (!in_array($engine, ['sproc', 'redis'], true)) {
                $engine = 'sproc';
            }
            $noCeilingChecks = !empty($_POST['no_ceiling_checks']) ? ' -NoCeilingChecks' : '';

            $selectedLog = $logsDir . DIRECTORY_SEPARATOR . 'single_save_load_test_' . date('Ymd_His') . '.log';
            $stopFile = $selectedLog . '.stop';
            if (file_exists($stopFile)) {
                @unlink($stopFile);
            }
            @file_put_contents($lockFile, "started=" . date('c') . PHP_EOL . "log=" . $selectedLog . PHP_EOL . "stop=" . $stopFile . PHP_EOL);
            $isRunning = true;

            $cmd = sprintf(
                    'start /b powershell -NoProfile -ExecutionPolicy Bypass -File "%s" -Fy %d -Version %d -Workers %d -IterationsPerWorker %d -RetryDeadlocks %d -MaxCandidates %d -Scenario %s -PerCeilingLimit %d -ThinkTimeMinMs %d -ThinkTimeMaxMs %d -CeilingEngine %s -LogPath "%s" -StopFile "%s"%s%s%s%s%s%s > "%s" 2>&1',
                    $psScript,
                    $fy,
                    $version,
                $workers,
                $iterations,
                $retry,
                $maxCandidates,
                escapeshellarg($scenario),
                $perCeilingLimit,
                $thinkMinMs,
                $thinkMaxMs,
                escapeshellarg($engine),
                $selectedLog . '.csv',
                $stopFile,
                $type !== '' ? ' -Type ' . escapeshellarg($type) : '',
                    $txIds !== '' ? ' -TxIds ' . escapeshellarg($txIds) : '',
                    $minTx > 0 ? ' -MinTxId ' . $minTx : '',
                    $maxTx > 0 ? ' -MaxTxId ' . $maxTx : '',
                    $onlyCeilingStatusNull,
                    $noCeilingChecks,
                    $selectedLog
                );

            @pclose(@popen($cmd, 'r'));
            $status = 'Load test started.';
            $statusType = 'success';
            $redirectAfterPost = true;
        }
    }

    if ($redirectAfterPost) {
        $q = [];
        if ($selectedLog !== '') {
            $q['log'] = $selectedLog;
        }
        $q['msg'] = $status;
        $q['msgtype'] = $statusType;
        header('Location: single_save_load_test.php?' . http_build_query($q), true, 303);
        exit;
    }
}

if (isset($_GET['log']) && $_GET['log'] !== '') {
    $requested = (string)$_GET['log'];
    $logDir = realpath($logsDir);
    $candidate = realpath($requested);
    if ($logDir && $candidate && str_starts_with($candidate, $logDir) && is_file($candidate)) {
        $selectedLog = $candidate;
    }
}
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $status = (string)$_GET['msg'];
    $statusType = (string)($_GET['msgtype'] ?? 'info');
}

if ($selectedLog === '') {
    $selectedLog = getLatestLogPath($logsDir);
}

$assessment = null;
if ($selectedLog !== '' && is_file($selectedLog)) {
    $assessment = parseLoadTestAssessment($selectedLog);
    $lines = @file($selectedLog, FILE_IGNORE_NEW_LINES);
    if ($lines !== false) {
        $tail = implode("\n", array_slice($lines, -120));
    }
    if ($assessment !== null && $assessment['stop_path'] !== '') {
        $stopFile = $assessment['stop_path'];
    }
    if ($assessment !== null && !empty($assessment['done'])) {
        $isRunning = false;
        if (file_exists($lockFile)) {
            @unlink($lockFile);
            $hasActiveLock = false;
        }
    }
}

if (!$isRunning && file_exists($lockFile)) {
    @unlink($lockFile);
    $hasActiveLock = false;
}
if ($activeRunnerCount === 0) {
    $activeRunnerCount = countLoadTestProcesses();
}

$recentRuns = getRecentRuns($logsDir, 12);

$state = 'IDLE';
if ($isRunning) {
    $state = 'RUNNING';
} elseif ($assessment !== null) {
    $state = (string)$assessment['verdict'];
}

$stateClass = 'idle';
if ($state === 'RUNNING') {
    $stateClass = 'run';
} elseif ($state === 'PASS') {
    $stateClass = 'pass';
} elseif ($state === 'REVIEW' || $state === 'CANCELLED') {
    $stateClass = 'review';
}
?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
    <meta charset="utf-8">
    <title>Single Save Load Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f5f7fb; color: #18212f; }
        .layout { display: grid; gap: 16px; grid-template-columns: 1.1fr 1fr; }
        .card { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 4px 14px rgba(0,0,0,0.08); }
        .full { grid-column: 1 / -1; }
        h1, h2, h3 { margin: 0 0 10px; }
        h1 { font-size: 22px; }
        h2 { font-size: 18px; }
        h3 { font-size: 15px; }
        .row { display: flex; gap: 12px; }
        .row > div { flex: 1; }
        label { display: block; margin-top: 10px; font-weight: 600; font-size: 13px; }
        input[type="number"], input[type="text"], select { width: 100%; padding: 8px; margin-top: 5px; box-sizing: border-box; }
        .btn { margin-top: 12px; padding: 9px 12px; background: #26415e; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .btn-danger { background: #b02828; }
        .btn-muted { background: #6f7b88; }
        .state { display: inline-block; padding: 6px 10px; border-radius: 999px; font-weight: 700; font-size: 12px; letter-spacing: 0.4px; }
        .state.idle { background: #eef2f7; color: #3d4f67; }
        .state.run { background: #e6f6ea; color: #0a6d37; }
        .state.pass { background: #e6f2ff; color: #124c8d; }
        .state.review { background: #fff1e8; color: #a14a19; }
        .status { margin-top: 8px; padding: 8px 10px; border-radius: 6px; font-size: 13px; }
        .status.info { background: #eef2f7; }
        .status.success { background: #e6f6ea; }
        .status.warn { background: #fff1e8; }
        .status.error { background: #ffe8e8; }
        .muted { color: #5b6778; font-size: 12px; }
        .kv { margin: 5px 0; font-size: 13px; }
        .kv strong { min-width: 140px; display: inline-block; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px; }
        th, td { border-bottom: 1px solid #e8edf4; padding: 6px; text-align: left; }
        pre { background: #101820; color: #d4e0ef; padding: 10px; border-radius: 6px; max-height: 260px; overflow: auto; font-size: 12px; }
        @media (max-width: 980px) { .layout { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <h1>Single Save Load Test</h1>

    <div class="layout">
        <div class="card">
            <h2>Start Test</h2>
            <form method="post">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <label>Presets</label>
                <div class="row">
                    <div><button class="btn" type="button" onclick="applyLoadPreset('baseline')">Baseline</button></div>
                    <div><button class="btn" type="button" onclick="applyLoadPreset('realistic')">Realistic UI</button></div>
                    <div><button class="btn" type="button" onclick="applyLoadPreset('stress')">Stress</button></div>
                </div>
                <div class="row">
                    <div>
                        <label>Fiscal Year</label>
                        <input type="number" name="fy" value="2026" min="1">
                    </div>
                    <div>
                        <label>Version</label>
                        <input type="number" name="version" value="5" min="1">
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Workers</label>
                        <input type="number" name="workers" value="4" min="1">
                    </div>
                    <div>
                        <label>Iterations / Worker</label>
                        <input type="number" name="iterations" value="20" min="1">
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Deadlock Retries</label>
                        <input type="number" name="retry" value="15" min="0">
                    </div>
                    <div>
                        <label>Max Candidates</label>
                        <input type="number" name="max_candidates" value="2000" min="1">
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Scenario</label>
                        <select name="scenario">
                            <option value="shuffle" selected>shuffle (random mix)</option>
                            <option value="spread_ceiling">spread_ceiling (broader ceilings)</option>
                        </select>
                    </div>
                    <div>
                        <label>Per Ceiling Limit</label>
                        <input type="number" name="per_ceiling_limit" value="5" min="1">
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Think Time Min (ms)</label>
                        <input type="number" name="think_min_ms" value="0" min="0">
                    </div>
                    <div>
                        <label>Think Time Max (ms)</label>
                        <input type="number" name="think_max_ms" value="0" min="0">
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>Ceiling Engine</label>
                        <select name="engine">
                            <option value="sproc" selected>sproc</option>
                            <option value="redis">redis</option>
                        </select>
                    </div>
                    <div>
                        <label>Transaction Type (optional)</label>
                        <input type="text" name="type" placeholder="e.g. 21">
                    </div>
                </div>
                <label>Transaction IDs (optional, comma separated)</label>
                <input type="text" name="txids" placeholder="e.g. 200603,202230,134284">
                <div class="row">
                    <div>
                        <label>Min TX</label>
                        <input type="number" name="min_tx" value="0" min="0">
                    </div>
                    <div>
                        <label>Max TX</label>
                        <input type="number" name="max_tx" value="0" min="0">
                    </div>
                </div>
                <label><input type="checkbox" name="no_ceiling_checks"> Disable ceiling checks</label>
                <label><input type="checkbox" name="only_ceiling_status_null" checked> Only process rows where CeilingStatus is NULL</label>
                <div class="muted">Only head records are processed.</div>
                <button class="btn" type="submit">Start Load Test</button>
            </form>
            <?php if ($status !== ''): ?>
                <div class="status <?= htmlspecialchars($statusType) ?>"><?= htmlspecialchars($status) ?></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Current Run</h2>
            <div class="state <?= htmlspecialchars($stateClass) ?>"><?= htmlspecialchars($state) ?></div>
            <?php if ($assessment !== null): ?>
                <div class="kv"><strong>Log:</strong> <a href="?log=<?= urlencode($selectedLog) ?>">open selected run</a></div>
                <div class="kv"><strong>Log File:</strong> <span class="muted"><?= htmlspecialchars($selectedLog) ?></span></div>
                <div class="kv"><strong>CSV File:</strong> <span class="muted"><?= htmlspecialchars((string)$assessment['csv_path']) ?></span></div>
                <?php if (!empty($assessment['start'])): ?>
                    <div class="kv"><strong>Config:</strong> FY <?= (int)$assessment['start']['fy'] ?> | Ver <?= (int)$assessment['start']['version'] ?> | W <?= (int)$assessment['start']['workers'] ?> | I <?= (int)$assessment['start']['iterations'] ?> | <?= htmlspecialchars((string)$assessment['start']['engine']) ?></div>
                    <div class="kv"><strong>Scenario:</strong> <?= htmlspecialchars((string)($assessment['start']['scenario'] ?? 'shuffle')) ?><?php if (!empty($assessment['start']['per_ceiling_limit'])): ?> | Per ceiling <?= (int)$assessment['start']['per_ceiling_limit'] ?><?php endif; ?><?php if (!empty($assessment['start']['think_max_ms']) || !empty($assessment['start']['think_min_ms'])): ?> | Think <?= (int)$assessment['start']['think_min_ms'] ?>-<?= (int)$assessment['start']['think_max_ms'] ?> ms<?php endif; ?> | CeilingStatus NULL only <?= !empty($assessment['start']['only_ceiling_status_null']) ? 'ON' : 'OFF' ?></div>
                <?php endif; ?>
                <div class="kv"><strong>Expected Ops:</strong> <?= (int)($assessment['expected'] ?? 0) ?></div>
                <?php if (!empty($assessment['done'])): ?>
                    <div class="kv"><strong>Actual Ops:</strong> <?= (int)$assessment['done']['total'] ?> | <strong>CSV Rows:</strong> <?= (int)$assessment['csv_rows'] ?></div>
                    <div class="kv"><strong>Result:</strong> OK <?= (int)$assessment['done']['ok'] ?> | Failed <?= (int)$assessment['done']['failed'] ?> | Deadlock <?= (int)$assessment['done']['deadlock'] ?> | Ceiling <?= (int)$assessment['done']['ceiling_failed'] ?></div>
                    <div class="kv"><strong>Performance:</strong> <?= number_format(((int)$assessment['done']['elapsed_ms'])/1000, 2) ?>s total | <?= number_format((float)$assessment['done']['avg_ms'], 2) ?>ms avg | <?= number_format((float)($assessment['tx_per_sec'] ?? 0), 2) ?> tx/s</div>
                <?php else: ?>
                    <div class="kv"><strong>Progress:</strong> Run started, waiting for completion summary...</div>
                <?php endif; ?>
                <?php if (!empty($assessment['log_mtime'])): ?>
                    <div class="kv"><strong>Last Log Update:</strong> <?= htmlspecialchars(date('Y-m-d H:i:s', (int)$assessment['log_mtime'])) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="kv">No run log found yet.</div>
            <?php endif; ?>

            <?php if (($isRunning || $hasActiveLock) && $stopFile !== ''): ?>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="stop_file" value="<?= htmlspecialchars($stopFile) ?>">
                    <button class="btn btn-danger" type="submit">Cancel Load Test</button>
                </form>
            <?php endif; ?>

            <?php if ($isRunning || $hasActiveLock || $activeRunnerCount > 0): ?>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="emergency_stop">
                    <button class="btn btn-danger" type="submit">Emergency Stop</button>
                </form>
            <?php endif; ?>

            <?php if ($isRunning || $hasActiveLock): ?>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="force_unlock">
                    <button class="btn btn-muted" type="submit">Force Clear Lock</button>
                </form>
            <?php endif; ?>

            <div class="muted" style="margin-top:6px;">Active runner processes: <strong><?= (int)$activeRunnerCount ?></strong></div>

            <div class="muted" style="margin-top:8px;">Page auto-refreshes every 30 seconds.</div>
        </div>

        <div class="card full">
            <h2>Recent Runs</h2>
            <?php if (empty($recentRuns)): ?>
                <div class="muted">No runs found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Verdict</th>
                            <th>Ops</th>
                            <th>Failed</th>
                            <th>Deadlock</th>
                            <th>Throughput</th>
                            <th>Log</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRuns as $r): ?>
                            <?php $a = $r['assessment']; ?>
                            <tr>
                                <td><?= htmlspecialchars(date('Y-m-d H:i:s', (int)($a['log_mtime'] ?? time()))) ?></td>
                                <td><?= htmlspecialchars((string)$a['verdict']) ?></td>
                                <td><?= (int)($a['done']['total'] ?? 0) ?>/<?= (int)($a['expected'] ?? 0) ?></td>
                                <td><?= (int)($a['done']['failed'] ?? 0) ?></td>
                                <td><?= (int)($a['done']['deadlock'] ?? 0) ?></td>
                                <td><?= number_format((float)($a['tx_per_sec'] ?? 0), 2) ?> tx/s</td>
                                <td><a href="?log=<?= urlencode((string)$r['log']) ?>">view</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <?php if ($tail !== ''): ?>
            <div class="card full">
                <h3>Selected Run Log Tail</h3>
                <pre><?= htmlspecialchars($tail) ?></pre>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isRunning): ?>
        <script>
            setTimeout(() => location.reload(), 30000);
        </script>
    <?php endif; ?>
    <script>
        function setField(name, value) {
            const el = document.querySelector('[name="' + name + '"]');
            if (!el) return;
            if (el.type === 'checkbox') {
                el.checked = !!value;
            } else {
                el.value = String(value);
            }
        }

        function applyLoadPreset(preset) {
            if (preset === 'baseline') {
                setField('workers', 8);
                setField('iterations', 20);
                setField('retry', 15);
                setField('max_candidates', 1000);
                setField('scenario', 'shuffle');
                setField('per_ceiling_limit', 5);
                setField('think_min_ms', 0);
                setField('think_max_ms', 0);
                setField('engine', 'sproc');
                setField('no_ceiling_checks', false);
                setField('only_ceiling_status_null', true);
            } else if (preset === 'realistic') {
                setField('workers', 72);
                setField('iterations', 40);
                setField('retry', 15);
                setField('max_candidates', 3000);
                setField('scenario', 'spread_ceiling');
                setField('per_ceiling_limit', 5);
                setField('think_min_ms', 3000);
                setField('think_max_ms', 10000);
                setField('engine', 'sproc');
                setField('no_ceiling_checks', false);
                setField('only_ceiling_status_null', true);
            } else if (preset === 'stress') {
                setField('workers', 50);
                setField('iterations', 40);
                setField('retry', 15);
                setField('max_candidates', 5000);
                setField('scenario', 'spread_ceiling');
                setField('per_ceiling_limit', 3);
                setField('think_min_ms', 0);
                setField('think_max_ms', 200);
                setField('engine', 'sproc');
                setField('no_ceiling_checks', false);
                setField('only_ceiling_status_null', true);
            }
        }
    </script>
</body>
</html>
