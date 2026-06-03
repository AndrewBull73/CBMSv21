<?php
declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ESTIMATES_EDIT', 'CALC_ADMIN', 'ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../vendor/autoload.php';

function monitorRedisClient() {
    return new Predis\Client([
        'scheme' => 'tcp',
        'host'   => '127.0.0.1',
        'port'   => 6379,
    ]);
}

function monitorCollectData($limit, $recentLimit) {
    global $conn;

    $limit = max(1, min(5000, (int)$limit));
    $recentLimit = max(1, min(500, (int)$recentLimit));

    $redis = monitorRedisClient();
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
                if (count($keys) >= $limit) {
                    break 2;
                }
            }
        } while ($cursor !== 0);
    } else {
        $raw = $redis->keys($pattern);
        $keys = array_slice(is_array($raw) ? $raw : [], 0, $limit);
    }

    sort($keys, SORT_NATURAL);
    $rows = [];
    $totalBal = 0.0;
    $totalCeil = 0.0;
    $breachCount = 0;

    foreach ($keys as $key) {
        $parts = explode(':', $key);
        $defId = (int)end($parts);
        $vals = $redis->hmget($key, ['bal_total', 'ceil_total', 'last_tx']);
        $bal = (float)($vals[0] ?? 0);
        $ceil = (float)($vals[1] ?? 0);
        $lastTxRaw = $vals[2] ?? null;
        $lastTx = ($lastTxRaw === null || $lastTxRaw === '') ? null : (int)$lastTxRaw;
        $ttl = (int)$redis->ttl($key);
        $remaining = $bal - $ceil;
        if ($remaining < 0) {
            $breachCount++;
        }
        $rows[] = [
            'ceiling_definition_id' => $defId,
            'balance_total' => $bal,
            'ceiling_total' => $ceil,
            'remaining' => $remaining,
            'last_tx' => $lastTx,
            'ttl' => $ttl,
        ];
        $totalBal += $bal;
        $totalCeil += $ceil;
    }

    $recentSql = "
        SELECT TOP {$recentLimit}
            TransactionID,
            HeadRecordID,
            CeilingStatus,
            CeilingStatusCheck,
            CeilingFailedFlag,
            CeilingDefinitionID,
            CeilingEngine,
            CeilingLastCheckedDate
        FROM dbo.tblTransactionInput
        WHERE CeilingLastCheckedDate IS NOT NULL
          AND CeilingFailedFlag = 1
        ORDER BY CeilingLastCheckedDate DESC, TransactionID DESC
    ";
    $recentStmt = $conn->prepare($recentSql);
    $recentStmt->execute();
    $recentRows = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

    $statsSql = "
        SELECT
            COUNT(1) AS CheckedRows,
            SUM(CASE WHEN CeilingFailedFlag = 1 THEN 1 ELSE 0 END) AS FailedRows,
            SUM(CASE WHEN CeilingStatus = 'Success' THEN 1 ELSE 0 END) AS SuccessRows
        FROM dbo.tblTransactionInput
        WHERE CeilingLastCheckedDate IS NOT NULL
    ";
    $statsStmt = $conn->prepare($statsSql);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['CheckedRows' => 0, 'FailedRows' => 0, 'SuccessRows' => 0];

    return [
        'captured_at' => gmdate('c'),
        'redis' => [
            'keys_returned' => count($rows),
            'limit' => $limit,
            'total_balance' => round($totalBal, 2),
            'total_ceiling' => round($totalCeil, 2),
            'breach_count' => $breachCount,
            'rows' => $rows,
        ],
        'transactions' => [
            'checked_rows' => (int)($stats['CheckedRows'] ?? 0),
            'failed_rows' => (int)($stats['FailedRows'] ?? 0),
            'success_rows' => (int)($stats['SuccessRows'] ?? 0),
            'recent_limit' => $recentLimit,
            'recent_rows' => $recentRows,
        ],
    ];
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
$recentLimit = isset($_GET['recent_limit']) ? (int)$_GET['recent_limit'] : 100;
$refreshSec = isset($_GET['refresh']) ? max(2, min(120, (int)$_GET['refresh'])) : 5;
$jsonMode = isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json';

if ($jsonMode) {
    header('Content-Type: application/json; charset=UTF-8');
    try {
        echo json_encode(['ok' => true, 'data' => monitorCollectData($limit, $recentLimit)], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars(\App\Shared\Lang::translateLiteral('Ceiling Monitor'), ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 24px; background: #f4f6f8; color: #1f2937; }
    h1, h2 { margin: 0 0 10px 0; }
    .meta { margin-bottom: 14px; font-size: 13px; color: #4b5563; }
    .cards { display: grid; grid-template-columns: repeat(5, minmax(120px, 1fr)); gap: 10px; margin-bottom: 14px; }
    .card { background: #fff; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px; }
    .card .k { font-size: 12px; color: #6b7280; }
    .card .v { font-size: 18px; font-weight: 700; margin-top: 6px; }
    table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #d1d5db; margin-bottom: 18px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
    th { background: #111827; color: #fff; position: sticky; top: 0; }
    .neg { color: #b91c1c; font-weight: 700; }
    .ok { color: #047857; font-weight: 700; }
    .row-wrap { max-height: 380px; overflow: auto; border: 1px solid #d1d5db; border-radius: 8px; }
  </style>
</head>
<body>
  <h1>Ceiling Monitor</h1>
  <div class="meta">
    Auto-refresh every <strong id="refreshSec"><?php echo (int)$refreshSec; ?></strong>s |
    Redis limit: <strong id="limitVal"><?php echo (int)$limit; ?></strong> |
    Recent tx limit: <strong id="recentLimitVal"><?php echo (int)$recentLimit; ?></strong>
  </div>
  <div class="cards">
    <div class="card"><div class="k">Captured At (UTC)</div><div class="v" id="capAt">-</div></div>
    <div class="card"><div class="k">Redis Keys</div><div class="v" id="redisKeys">-</div></div>
    <div class="card"><div class="k">Total Balance</div><div class="v" id="totalBal">-</div></div>
    <div class="card"><div class="k">Total Ceiling</div><div class="v" id="totalCeil">-</div></div>
    <div class="card"><div class="k">Potential Breaches</div><div class="v" id="breachCount">-</div></div>
  </div>

  <h2>Redis Ceiling Balances</h2>
  <div class="row-wrap">
    <table>
      <thead>
        <tr>
          <th>CeilingDefinitionID</th><th>Balance</th><th>Ceiling</th><th>Remaining</th><th>Last Tx</th><th>TTL</th>
        </tr>
      </thead>
      <tbody id="redisRows"></tbody>
    </table>
  </div>

  <h2>Recent Ceiling Checks</h2>
  <div class="row-wrap">
    <table>
      <thead>
        <tr>
          <th>TransactionID</th><th>HeadRecordID</th><th>Status</th><th>Status Check</th><th>Failed</th><th>DefinitionID</th><th>Engine</th><th>Last Checked</th>
        </tr>
      </thead>
      <tbody id="txRows"></tbody>
    </table>
  </div>

  <script>
    const params = new URLSearchParams(window.location.search);
    const refreshSec = parseInt(params.get('refresh') || '<?php echo (int)$refreshSec; ?>', 10);
    const limit = parseInt(params.get('limit') || '<?php echo (int)$limit; ?>', 10);
    const recentLimit = parseInt(params.get('recent_limit') || '<?php echo (int)$recentLimit; ?>', 10);

    function fmt(n) {
      const v = Number(n || 0);
      return Number.isFinite(v) ? v.toLocaleString(undefined, { maximumFractionDigits: 2 }) : '-';
    }

    function render(data) {
      const d = data.data;
      document.getElementById('capAt').textContent = d.captured_at || '-';
      document.getElementById('redisKeys').textContent = d.redis.keys_returned;
      document.getElementById('totalBal').textContent = fmt(d.redis.total_balance);
      document.getElementById('totalCeil').textContent = fmt(d.redis.total_ceiling);
      document.getElementById('breachCount').textContent = d.redis.breach_count;

      const redisRows = document.getElementById('redisRows');
      redisRows.innerHTML = '';
      d.redis.rows.forEach(r => {
        const tr = document.createElement('tr');
        const cls = Number(r.remaining) < 0 ? 'neg' : 'ok';
        tr.innerHTML = `
          <td>${r.ceiling_definition_id}</td>
          <td>${fmt(r.balance_total)}</td>
          <td>${fmt(r.ceiling_total)}</td>
          <td class="${cls}">${fmt(r.remaining)}</td>
          <td>${r.last_tx ?? '-'}</td>
          <td>${r.ttl}</td>`;
        redisRows.appendChild(tr);
      });

      const txRows = document.getElementById('txRows');
      txRows.innerHTML = '';
      d.transactions.recent_rows.forEach(r => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${r.TransactionID ?? '-'}</td>
          <td>${r.HeadRecordID ?? '-'}</td>
          <td>${r.CeilingStatus ?? '-'}</td>
          <td>${r.CeilingStatusCheck ?? '-'}</td>
          <td>${r.CeilingFailedFlag ?? 0}</td>
          <td>${r.CeilingDefinitionID ?? '-'}</td>
          <td>${r.CeilingEngine ?? '-'}</td>
          <td>${r.CeilingLastCheckedDate ?? '-'}</td>`;
        txRows.appendChild(tr);
      });
    }

    async function load() {
      const u = new URL(window.location.href);
      u.searchParams.set('format', 'json');
      u.searchParams.set('limit', String(limit));
      u.searchParams.set('recent_limit', String(recentLimit));
      const res = await fetch(u.toString(), { cache: 'no-store' });
      const json = await res.json();
      if (json.ok) {
        render(json);
      }
    }

    load();
    setInterval(load, Math.max(2, refreshSec) * 1000);
  </script>
</body>
</html>
