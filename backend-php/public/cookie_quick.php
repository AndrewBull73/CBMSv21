<?php
declare(strict_types=1);
require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

setcookie('CBMS21_TEST', '1', 0, '/', '', false, false); // non-HttpOnly test cookie
header('Content-Type: text/plain; charset=UTF-8');
echo 'CBMS21_TEST in request: ' . ($_COOKIE['CBMS21_TEST'] ?? '(no cookie)') . PHP_EOL;
