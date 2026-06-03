<?php
declare(strict_types=1);
require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

header('Content-Type: text/plain; charset=UTF-8');
echo "Session name: " . session_name() . PHP_EOL;
echo "Session ID:   " . session_id()   . PHP_EOL;
echo "Cookie in request: " . ($_COOKIE[session_name()] ?? '(no cookie)') . PHP_EOL . PHP_EOL;

echo "Headers (response):" . PHP_EOL;
foreach (headers_list() as $h) { echo " > $h" . PHP_EOL; }
