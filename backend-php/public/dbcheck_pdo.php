<?php
declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

function envStr(string $k, ?string $d=null){ $v=getenv($k); return $v===false?$d:$v; }
function envBool(string $k, bool $d=false){ $v=envStr($k,null); if($v===null) return $d; return in_array(strtolower($v),['1','true','yes','on'],true); }

$host     = envStr('DB_HOST',     envStr('DB_SERVER','localhost'));
$name     = envStr('DB_NAME',     envStr('DB_DATABASE','CBMSv2'));
$user     = envStr('DB_USER',     envStr('DB_USERNAME','sa'));
$pass     = envStr('DB_PASS',     envStr('DB_PASSWORD',''));
$encrypt  = envBool('DB_ENCRYPT', true);
$trust    = envBool('DB_TRUST_CERT', true);
$timeout  = (int)(envStr('DB_LOGIN_TIMEOUT','5') ?? '5');

header('Content-Type: text/plain; charset=UTF-8');

try {
    $dsn = 'sqlsrv:'.implode(';', [
        "Server={$host}",
        "Database={$name}",
        "LoginTimeout={$timeout}",
        "Encrypt=" . ($encrypt ? 'yes':'no'),
        "TrustServerCertificate=" . ($trust ? 'yes':'no')
    ]);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "PDO + pdo_sqlsrv: OK\n";

    // tiny query smoke test
    $stmt = $pdo->query('SELECT DB_NAME() AS dbname');
    $row  = $stmt->fetch();
    echo "Connected DB: " . ($row['dbname'] ?? '(unknown)') . "\n";
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
}
