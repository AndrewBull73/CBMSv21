<?php
declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

use App\Shared\SessionHelper;

SessionHelper::set('auth.user_id', 999);
SessionHelper::set('auth.username', 'admin');
SessionHelper::set('flash.message', ['type' => 'success', 'text' => 'hello']);

$root = getenv('APP_SESSION_PREFIX') ?: 'cbms';
header('Content-Type: text/plain; charset=UTF-8');
echo "Root key: {$root}\n\n";
print_r($_SESSION[$root]);
