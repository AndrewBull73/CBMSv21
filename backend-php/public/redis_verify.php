<?php
// redis_verify.php
// Place this file in: backend-php/public/redis_verify.php
// Purpose: verify Redis connectivity, write a persistent marker, and list cbmsv21:* keys.
// Usage (CLI):
//   C:\xampp82\php\php.exe C:\xampp82\htdocs\CBMSv21\backend-php\public\redis_verify.php
// Usage (browser):
//   http://localhost:8080/backend-php/public/redis_verify.php

declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'allowCli' => true,
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

// Keep raw PHP errors out of browser output; this script prints its own diagnostics.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Make output plain text for browser readability
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

// Resolve Composer autoload (adjust relative path from this file to project root)
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "ERROR: Composer autoload not found at: {$autoload}\n";
    echo "Run 'composer install' in the project root (C:\\xampp82\\htdocs\\CBMSv21) and try again.\n";
    exit(1);
}
require_once $autoload;

$host = '127.0.0.1';
$port = 6379;
$pattern = 'cbmsv21:*';
$markerKey = 'cbmsv21:last_load';
$markerValue = date('c');

$connected = false;
$clientType = 'none';
$errors = [];

try {
    if (class_exists('Redis')) {
        // Native phpredis extension
        $clientType = 'phpredis (native)';
        $redis = new Redis();
        // connect may throw
        $redis->connect($host, $port);
        // write persistent marker (no TTL)
        $redis->set($markerKey, $markerValue);
        $pong = $redis->ping();
        if (is_string($pong)) {
            $pong = trim($pong, "+ \t\n\r\0\x0B");
        }
        $connected = (strtoupper((string)$pong) === 'PONG');
    } else {
        // Predis fallback
        $clientType = 'predis (php client)';
        $predis = new Predis\Client([
            'scheme' => 'tcp',
            'host'   => $host,
            'port'   => $port,
            // 'timeout' => 2.0, 'read_write_timeout' => 2.0, // optional
        ]);
        // write persistent marker (no TTL)
        $predis->set($markerKey, $markerValue);
        $pong = $predis->ping();
        $connected = (strtoupper((string)$pong) === 'PONG');

        // Provide a thin proxy variable named $redis so later code can use $redis uniformly
        $redis = new class($predis) {
            private $client;
            public function __construct($client) { $this->client = $client; }
            public function __call($name, $args) { return $this->client->__call($name, $args); }
            public function get($key) { return $this->client->get($key); }
            public function set($key, $value, $options = null) {
                if ($options === null) return $this->client->set($key, $value);
                if (is_array($options) && isset($options['ex'])) {
                    return $this->client->setex($key, $options['ex'], $value);
                }
                return $this->client->set($key, $value);
            }
            // scan wrapper for Predis: returns [cursor, keys]
            public function scan_iter($pattern, $count = 100) {
                $cursor = '0';
                $all = [];
                do {
                    $res = $this->client->scan($cursor, ['match' => $pattern, 'count' => $count]);
                    $cursor = $res[0];
                    $keys = $res[1];
                    foreach ($keys as $k) $all[] = $k;
                } while ($cursor !== '0');
                return $all;
            }
        };
    }
} catch (Throwable $e) {
    $errors[] = $e->getMessage();
    $connected = false;
}

// Output header
echo "Redis verify\n";
echo "============\n";
echo "Client attempted: {$clientType}\n";
echo "Host: {$host}:{$port}\n";

if ($connected) {
    echo "PING response: " . (isset($pong) ? trim((string)$pong) : '(no response)') . "\n";
    echo "Status: SUCCESS — Redis is reachable and the client is connected.\n";
    echo "Wrote marker: {$markerKey} -> " . (string)$redis->get($markerKey) . "\n";
} else {
    echo "Status: FAILURE — Redis not connected.\n";
    if (!empty($errors)) {
        echo "Errors:\n";
        foreach ($errors as $err) {
            echo " - {$err}\n";
        }
    }
    echo "Suggestions:\n";
    echo " - Ensure a Redis server is running on {$host}:{$port}.\n";
    echo " - If using Docker, ensure port 6379 is published to the host.\n";
    echo " - If you expect the native Redis extension, install/enable phpredis and restart Apache/PHP.\n";
    echo " - Ensure RedisInsight or other tools connect to the same host/port and DB index.\n";
    // Exit early on failure
    exit(1);
}

// List keys matching pattern using a safe SCAN approach
echo "\nScanning keys matching pattern: {$pattern}\n";
$foundKeys = [];

try {
    if (class_exists('Redis')) {
        // phpredis: use scan with iterator
        $iterator = null;
        // phpredis scan signature: scan(&$iterator, $pattern = null, $count = 0)
        // iterate until scan returns false or empty
        $iterator = 0;
        do {
            $keys = $redis->scan($iterator, $pattern, 100);
            if ($keys === false) break;
            foreach ($keys as $k) $foundKeys[] = $k;
        } while ($iterator !== 0);
    } else {
        // Predis proxy provides scan_iter
        $foundKeys = $redis->scan_iter($pattern, 100);
    }
} catch (Throwable $e) {
    echo "Scan error: " . $e->getMessage() . "\n";
    exit(1);
}

if (empty($foundKeys)) {
    echo "No keys matched pattern {$pattern}\n";
    // Still success because marker was written; return code 0
    exit(0);
}

echo "Total keys found: " . count($foundKeys) . "\n\n";

// Show each key with type and a sample of its value
foreach ($foundKeys as $k) {
    try {
        $type = $redis->type($k);
    } catch (Throwable $e) {
        // Predis returns type as string; phpredis returns integer constants in some builds
        try {
            $type = (is_object($redis) && method_exists($redis, 'type')) ? $redis->type($k) : 'unknown';
        } catch (Throwable $_) {
            $type = 'unknown';
        }
    }

    // Normalize type to string for phpredis numeric return values
    if (is_int($type)) {
        // phpredis constants: 0 = none, 1 = string, 2 = set, 3 = list, 4 = zset, 5 = hash, 6 = stream
        $map = [
            0 => 'none',
            1 => 'string',
            2 => 'set',
            3 => 'list',
            4 => 'zset',
            5 => 'hash',
            6 => 'stream',
        ];
        $type = $map[$type] ?? 'unknown';
    }

    echo "Key: {$k}\n";
    echo "  Type: {$type}\n";

    try {
        switch ($type) {
            case 'string':
                $val = $redis->get($k);
                echo "  Value: " . (string)$val . "\n";
                break;
            case 'hash':
                $h = $redis->hgetall($k);
                if (is_array($h)) {
                    foreach ($h as $field => $v) {
                        echo "  {$field} => {$v}\n";
                    }
                } else {
                    echo "  (hash read returned non-array)\n";
                }
                break;
            case 'list':
                $items = $redis->lrange($k, 0, 99);
                if (is_array($items)) {
                    $i = 0;
                    foreach ($items as $it) {
                        echo "  [{$i}] {$it}\n";
                        $i++;
                    }
                } else {
                    echo "  (list read returned non-array)\n";
                }
                break;
            case 'set':
                $members = $redis->smembers($k);
                if (is_array($members)) {
                    foreach ($members as $m) {
                        echo "  - {$m}\n";
                    }
                } else {
                    echo "  (set read returned non-array)\n";
                }
                break;
            case 'zset':
                // show with scores
                if (method_exists($redis, 'zrange')) {
                    $z = $redis->zrange($k, 0, -1, ['withscores' => true]);
                    if (is_array($z)) {
                        foreach ($z as $member => $score) {
                            echo "  {$member} => {$score}\n";
                        }
                    } else {
                        echo "  (zset read returned non-array)\n";
                    }
                } else {
                    echo "  (zset read not supported by client)\n";
                }
                break;
            default:
                echo "  (unsupported type display)\n";
        }
    } catch (Throwable $e) {
        echo "  (error reading key: " . $e->getMessage() . ")\n";
    }

    // show TTL
    try {
        $ttl = $redis->ttl($k);
        echo "  TTL: {$ttl}\n";
    } catch (Throwable $_) {
        // ignore TTL errors
    }

    echo "\n";
}

exit(0);
