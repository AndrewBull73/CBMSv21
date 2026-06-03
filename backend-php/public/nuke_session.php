<?php
declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

// wipe data
$_SESSION = [];
@session_unset();
@session_destroy();

// try to clear cookies on common paths
$paths = ['/', '/CBMSv21', '/CBMSv21/backend-php/public'];
foreach ($paths as $p) {
    setcookie('CBMSV2SESSID', '', time() - 3600, $p, '', false, true);
}

header('Content-Type: text/plain; charset=UTF-8');
echo "Session cleared.\n";
