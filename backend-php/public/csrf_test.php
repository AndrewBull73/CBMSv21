<?php
declare(strict_types=1);

require_once __DIR__ . '/_script_guard.php';
cbms_public_script_guard([
    'auth' => true,
    'permsAny' => ['ADMIN_ALL', 'SYSADMIN'],
    'debugOnly' => true,
    'csrfPost' => true,
]);

header('Cache-Control: no-store');

// Grab some debug info
$sessionName  = session_name();
$sessionId    = session_id();
$cookieInReq  = $_COOKIE[$sessionName] ?? '(no cookie)';
$prefix       = getenv('APP_SESSION_PREFIX') ?: 'cbmsv21';

$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';

if (!$isPost) {
    // Ensure token exists now and flush Set-Cookie to client before form submit
    $currentToken = csrf_token();
    session_write_close();
} else {
    $posted   = $_POST['_csrf'] ?? null;
    $expected = $_SESSION[$prefix]['_csrf'] ?? null; // read directly for debug
    $ok       = true;
}
?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars(\App\Shared\Lang::translateLiteral('CSRF Test (definitive)'), ENT_QUOTES, 'UTF-8') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>body{font-family:system-ui,Segoe UI,Arial;margin:2rem} code{background:#f4f4f4;padding:.1rem .3rem;border-radius:4px}</style>
</head>
<body>
  <h1>CSRF Test (definitive)</h1>

  <h3>Session</h3>
  <ul>
    <li>Session name: <code><?= htmlspecialchars($sessionName) ?></code></li>
    <li>Session ID: <code><?= htmlspecialchars($sessionId) ?></code></li>
    <li>Cookie in request: <code><?= htmlspecialchars($cookieInReq) ?></code></li>
    <li>Prefix: <code><?= htmlspecialchars($prefix) ?></code></li>
  </ul>

  <?php if ($isPost): ?>
    <h3>POST result</h3>
    <p>CSRF check: <strong><?= !empty($ok) ? 'OK ✅' : 'FAILED ❌' ?></strong></p>
    <ul>
      <li>Posted token: <code><?= htmlspecialchars((string)($posted ?? '')) ?></code></li>
      <li>Expected token: <code><?= htmlspecialchars((string)($expected ?? '')) ?></code></li>
    </ul>
    <p><a href="/CBMSv21/backend-php/public/csrf_test.php">Back</a></p>
  <?php else: ?>
    <h3>Form</h3>
    <form method="post" action="/CBMSv21/backend-php/public/csrf_test.php">
      <?= csrf_field(); ?>
      <button type="submit">Submit</button>
    </form>
    <p>Current token: <code><?= htmlspecialchars((string)($currentToken ?? '')) ?></code></p>
  <?php endif; ?>
</body>
</html>
