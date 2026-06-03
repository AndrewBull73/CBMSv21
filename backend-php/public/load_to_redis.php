<?php
// load_table_to_redis.php
declare(strict_types=1);
require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'allowCli' => true,
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

ini_set('display_errors', '0'); ini_set('display_startup_errors', '0'); error_reporting(E_ALL);
if (php_sapi_name() !== 'cli') header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../../vendor/autoload.php'; // adjust path if needed

$dbServer = (string) envStr('DB_HOST', envStr('DB_SERVER', 'localhost'));
$dbName = (string) envStr('DB_NAME', envStr('DB_DATABASE', 'CBMSv2'));
$dbUser = (string) envStr('DB_USER', envStr('DB_USERNAME', 'sa'));
$dbPass = (string) envStr('DB_PASS', envStr('DB_PASSWORD', ''));
$table = trim((string) envStr('REDIS_LOAD_TABLE', 'TestFxRates'));
$idCol = trim((string) envStr('REDIS_LOAD_ID_COLUMN', 'Currency'));
$prefix = trim((string) envStr('REDIS_KEY_PREFIX', 'cbmsv21'));
$batchSize = max(1, (int) envStr('REDIS_LOAD_BATCH_SIZE', '500'));

// Create PDO (SQL Server example)
$dsn = "sqlsrv:Server={$dbServer};Database={$dbName}";
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "DB connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Create Redis client (Predis)
try {
    $predis = new Predis\Client(['scheme'=>'tcp','host'=>'127.0.0.1','port'=>6379]);
} catch (Throwable $e) {
    echo "Redis connection failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Count rows
$countStmt = $pdo->query("SELECT COUNT(1) AS cnt FROM {$table}");
$total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
echo "Total rows to load: {$total}\n";

$offset = 0;
$loaded = 0;

while ($offset < $total) {
    // For SQL Server use OFFSET/FETCH (SQL Server 2012+)
    $sql = "SELECT * FROM {$table} ORDER BY {$idCol} OFFSET {$offset} ROWS FETCH NEXT {$batchSize} ROWS ONLY";
    $st = $pdo->query($sql);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) break;

    foreach ($rows as $row) {
        if (!isset($row[$idCol])) continue;
        $id = $row[$idCol];
        $key = "{$prefix}:{$table}:{$id}"; // e.g., cbmsv21:dbo.YourTable:123

        // Convert all values to strings; remove nulls if desired
        $hash = [];
        foreach ($row as $col => $val) {
            $hash[$col] = $val === null ? '' : (string)$val;
        }

        // Write as Redis hash
        $predis->hmset($key, $hash);
        // Optionally set TTL: $predis->expire($key, 86400);
        $loaded++;
    }

    echo "Loaded batch: offset {$offset}, rows " . count($rows) . "\n";
    $offset += $batchSize;
}

echo "Done. Total loaded: {$loaded}\n";

// Write marker
$predis->set("{$prefix}:last_load", date('c'));
echo "Wrote marker {$prefix}:last_load\n";
