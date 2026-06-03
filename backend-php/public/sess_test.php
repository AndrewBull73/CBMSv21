<?php
declare(strict_types=1);
require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

use App\Shared\SessionHelper;

SessionHelper::set('auth.user_id', 42);
SessionHelper::set('auth.username', 'admin');
SessionHelper::set('flash.message', ['type' => 'success', 'text' => 'hi']);

header('Content-Type: text/plain; charset=UTF-8');
echo "SID=" . session_id() . PHP_EOL;
echo "auth.user_id=" . SessionHelper::get('auth.user_id') . PHP_EOL;
echo "auth.username=" . SessionHelper::get('auth.username') . PHP_EOL;
echo "flash.message=" . json_encode(SessionHelper::get('flash.message')) . PHP_EOL;

SessionHelper::forget('flash.message');
echo "flash.after.forget=" . json_encode(SessionHelper::get('flash.message')) . PHP_EOL;
