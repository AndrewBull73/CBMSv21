<?php
declare(strict_types=1);

/**
 * Exposes $conn as a PDO instance.
 *
 * Supported drivers (choose via .env DB_DRIVER):
 *   - sqlsrv (default)  → PDO_SQLSRV to Microsoft SQL Server
 *   - mysql             → PDO MySQL
 *
 * .env keys (SQL Server style kept; MySQL keys also supported):
 *   APP_ENV=local|production
 *   DB_DRIVER=sqlsrv|mysql
 *
 *   # Common
 *   DB_HOST=localhost           (fallback to DB_SERVER if not set)
 *   DB_NAME=CBMSv2              (fallback to DB_DATABASE if not set)
 *   DB_USER=CRUser              (fallback to DB_USERNAME if not set)
 *   DB_PASS=secret              (fallback to DB_PASSWORD if not set)
 *   DB_AUTH=sql|windows         (sql default; windows uses the current Windows account)
 *   DB_PORT=1433 (sqlsrv) | 3306 (mysql)
 *
 *   # SQL Server (pdo_sqlsrv)
 *   DB_ENCRYPT=true|false
 *   DB_TRUST_CERT=true|false
 *   DB_LOGIN_TIMEOUT=5
 */

require_once __DIR__ . '/../shared/env.php';
require_once __DIR__ . '/../shared/logger.php';

loadEnv(__DIR__ . '/../.env');

$APP_ENV = envStr('APP_ENV', 'local');
$driver  = strtolower((string) envStr('DB_DRIVER', 'sqlsrv'));

$host = envStr('DB_HOST',     envStr('DB_SERVER',  'localhost'));
$name = envStr('DB_NAME',     envStr('DB_DATABASE','CBMSv2'));
$user = envStr('DB_USER',     envStr('DB_USERNAME',''));
$pass = envStr('DB_PASS',     envStr('DB_PASSWORD',''));
$auth = strtolower((string) envStr('DB_AUTH', 'sql'));
$port = envStr('DB_PORT',     null); // optional

// SQL Server-specific options
$encrypt   = envBool('DB_ENCRYPT', true);
$trustCert = envBool('DB_TRUST_CERT', true);
$timeout   = (int) (envStr('DB_LOGIN_TIMEOUT', '5') ?? '5');

try {
    if ($driver === 'mysql') {
        // ---- MySQL via PDO ----
        $portPart = $port ? ";port={$port}" : '';
        $dsn = "mysql:host={$host}{$portPart};dbname={$name};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $conn = new PDO($dsn, $user, $pass, $options);

    } else {
        // ---- SQL Server via PDO_SQLSRV (default) ----
        $server = $host . ($port ? ',' . $port : '');

        $params = [
            "Server={$server}",
            "Database={$name}",
            "LoginTimeout={$timeout}",
            "Encrypt=" . ($encrypt ? 'yes' : 'no'),
            "TrustServerCertificate=" . ($trustCert ? 'yes' : 'no'),
        ];
        $dsn = 'sqlsrv:' . implode(';', $params);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (defined('PDO::SQLSRV_ATTR_ENCODING') && defined('PDO::SQLSRV_ENCODING_UTF8')) {
            $options[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
        }

        if ($auth === 'windows' || $auth === 'trusted' || $auth === 'integrated') {
            $conn = new PDO($dsn, null, null, $options);
        } else {
            $conn = new PDO($dsn, $user, $pass, $options);
        }
    }

    // Expose connection globally for legacy code
    $GLOBALS['conn'] = $conn;

} catch (Throwable $e) {
    if (function_exists('app_log')) {
        app_log('DB: connect failed', [
            'driver' => $driver,
            'error'  => $e->getMessage()
        ], 'error');
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    if ($APP_ENV === 'local') {
        echo "Database connection failed.\n", $e->getMessage(), "\n";
    } else {
        echo "Database connection failed.";
    }
    exit;
}
