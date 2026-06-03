<?php declare(strict_types=1);

use App\Shared\SessionHelper;

// Always ensure a fresh session for login page
SessionHelper::ensureSession();

// GENERATE CSRF TOKEN — THIS IS THE MISSING LINE
csrf_token(); // ← ADD THIS

// 🧩 Regenerate CSRF token after forced logout (ensures fresh token)
if (($_GET['reason'] ?? '') === 'forced' && function_exists('csrf_regenerate')) {
    csrf_regenerate();
}

$title = $title ?? __t('login');
$returnUrl = isset($returnUrl) ? (string) $returnUrl : trim((string) ($_GET['return'] ?? ''));
?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container min-vh-100 d-flex align-items-center">
  <div class="row justify-content-center w-100">
    <div class="col-md-4">
      <h1 class="text-center mb-4">CBMSv21</h1>

      <?php
      // ✅ Show flash message once (then clear)
      $flash = SessionHelper::pull('flash.message');
      if (is_array($flash) && !empty($flash['text'])):
        $type = in_array($flash['type'] ?? 'info', ['success','danger','warning','info'], true)
          ? $flash['type'] : 'info';
      ?>
        <div class="alert alert-<?= htmlspecialchars($type, ENT_QUOTES) ?> alert-dismissible fade show" role="alert">
          <?php if (!empty($flash['title'])): ?>
            <div class="fw-semibold mb-1"><?= htmlspecialchars((string)$flash['title'], ENT_QUOTES) ?></div>
          <?php endif; ?>
          <div><?= htmlspecialchars((string)$flash['text'], ENT_QUOTES) ?></div>
          <?php if (!empty($flash['detail'])): ?>
            <div class="small mt-1 opacity-75"><?= htmlspecialchars((string)$flash['detail'], ENT_QUOTES) ?></div>
          <?php endif; ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= __t('close') ?>"></button>
        </div>
      <?php endif; ?>

      <?php
      // ✅ Forced logout banner (shows only once when redirected directly)
      if (($_GET['reason'] ?? '') === 'forced'):
      ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>
          <?= __t('session_forced_logout') ?? 'Your session was terminated by an administrator. Please log in again.' ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= __t('close') ?>"></button>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-header text-center">
          <h4 class="mb-0"><?= __t('login') ?></h4>
        </div>
        <div class="card-body">
          <form method="post" action="index.php?route=auth/login" autocomplete="off" novalidate>
            <?= csrf_field(); ?>
            <?php if ($returnUrl !== ''): ?>
              <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES) ?>">
            <?php endif; ?>

            <div class="mb-3">
              <label for="username" class="form-label"><?= __t('username') ?></label>
              <input type="text" name="username" id="username" class="form-control"
                     autocomplete="username" required autofocus>
            </div>

            <div class="mb-3">
              <label for="password" class="form-label"><?= __t('password') ?></label>
              <input type="password" name="password" id="password" class="form-control"
                     autocomplete="current-password" required>
            </div>

            <div class="d-grid">
              <button type="submit" class="btn btn-primary"><?= __t('login') ?></button>
            </div>
          </form>
        </div>
      </div>

      <p class="text-muted small text-center mt-3">
        <?= __t('login_password_notice') ?>
      </p>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
