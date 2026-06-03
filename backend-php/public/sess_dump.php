<?php
declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
]);

use App\Shared\SessionHelper;

SessionHelper::ensureSession();

$sn   = session_name();
$sid  = session_id();
$cookieVal = $_COOKIE[$sn] ?? '(no cookie)';
$prefix = getenv('APP_SESSION_PREFIX') ?: 'cbmsv21';

// helper to list dot-paths under the namespaced root
function flatten_paths(array $a, string $base = ''): array {
    $out = [];
    foreach ($a as $k => $v) {
        $key = $base === '' ? (string)$k : $base . '.' . $k;
        if (is_array($v)) {
            $out[] = $key . ' (array)';
            $out   = array_merge($out, flatten_paths($v, $key));
        } else {
            $out[] = $key . ' = ' . (is_scalar($v) ? var_export($v, true) : gettype($v));
        }
    }
    return $out;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
<meta charset="utf-8">
<title><?= htmlspecialchars(\App\Shared\Lang::translateLiteral('Session dump'), ENT_QUOTES, 'UTF-8') ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: ui-sans-serif, system-ui, Segoe UI, Arial; margin: 1.25rem; }
  pre { background:#f7f7f9; padding:1rem; border:1px solid #e1e1e8; overflow:auto; }
  code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
</style>
</head>
<body>
<h1>Session dump</h1>

<h2>Session</h2>
<ul>
  <li><strong>Session name:</strong> <code><?= htmlspecialchars($sn) ?></code></li>
  <li><strong>Session ID:</strong> <code><?= htmlspecialchars($sid) ?></code></li>
  <li><strong>Cookie in request:</strong> <code><?= htmlspecialchars((string)$cookieVal) ?></code></li>
  <li><strong>Prefix:</strong> <code><?= htmlspecialchars($prefix) ?></code></li>
</ul>

<h2>Namespaced (<?= htmlspecialchars($prefix) ?>)</h2>
<?php $root = $_SESSION[$prefix] ?? []; ?>
<?php if ($root === []): ?>
  <p>(empty)</p>
<?php else: ?>
  <pre><?php echo htmlspecialchars(print_r($root, true)); ?></pre>
<?php endif; ?>

<h3>Flattened keys</h3>
<pre><?php echo htmlspecialchars(implode("\n", flatten_paths($root))); ?></pre>

<h2>Raw $_SESSION (full)</h2>
<pre><?php echo htmlspecialchars(print_r($_SESSION, true)); ?></pre>

<p style="color:#a00"><strong>Reminder:</strong> This page can expose sensitive data. Remove it before going to production.</p>
</body>
</html>
