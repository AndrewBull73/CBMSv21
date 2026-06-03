<?php
declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

header('Content-Type: text/plain; charset=UTF-8');

$keys = [
  'APP_ENV','APP_DEBUG','APP_DEBUG_LOG_ENABLED',
  'APP_SESSION_NAME','APP_COOKIE_PATH','APP_SESSION_PREFIX',
];
foreach ($keys as $k) {
  echo $k . '=' . (getenv($k) ?? '(null)') . PHP_EOL;
}
