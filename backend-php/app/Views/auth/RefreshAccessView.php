<?php
declare(strict_types=1);

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

// CSRF
require_once __DIR__ . '/../../../shared/csrf.php';
$csrf = csrf_token();

// Expected: $userId, $username, $roles, $perms, $refreshedAt passed in by controller
$uid         = $userId      ?? null;
$username    = $username    ?? __t('guest');
$roles       = is_array($roles ?? null) ? $roles : [];
$perms       = is_array($perms ?? null) ? $perms : [];
$refreshedAt = $refreshedAt ?? null;
$backUrl     = 'index.php?route=home/index';

$areaOrder = [
    'Platform',
    'Strategic Framework',
    'Budget Submission',
    'Budget Execution',
    'Reporting',
    'Analytics',
    'Dashboards',
    'Configuration',
    'Administration',
    'Other',
];

$groupRoleArea = static function (string $roleName): string {
    $name = trim($roleName);
    return match (true) {
        $name === 'System Administrator' => 'Platform',
        str_starts_with($name, 'Strategic Framework') => 'Strategic Framework',
        str_starts_with($name, 'Budget Submission') => 'Budget Submission',
        str_starts_with($name, 'Budget Execution') => 'Budget Execution',
        str_starts_with($name, 'Reporting') => 'Reporting',
        str_starts_with($name, 'Analytics') => 'Analytics',
        str_starts_with($name, 'Dashboard') => 'Dashboards',
        str_contains($name, 'Configuration') => 'Configuration',
        $name === 'RatesEditor' => 'Budget Submission',
        default => 'Other',
    };
};

$groupPermArea = static function (string $permCode): string {
    $code = strtoupper(trim($permCode));
    return match (true) {
        str_starts_with($code, 'STRATEGY_') => 'Strategic Framework',
        str_starts_with($code, 'ESTIMATES_'), str_starts_with($code, 'RATES_') => 'Budget Submission',
        str_starts_with($code, 'ANALYTICS_') => 'Analytics',
        str_starts_with($code, 'BASE_CONFIG_'), str_starts_with($code, 'FIN_CONFIG_'), str_starts_with($code, 'SYSSETTINGS_'), $code === 'CALC_ADMIN' => 'Configuration',
        str_starts_with($code, 'USERS_'), str_starts_with($code, 'ROLES_'), str_starts_with($code, 'AUDIT_'), str_starts_with($code, 'SESSION_'), str_starts_with($code, 'WORKFLOW_'), str_starts_with($code, 'METRICS_'), str_starts_with($code, 'LOGS_'), str_starts_with($code, 'ERRORLOG_'), str_starts_with($code, 'DIAG_'), str_starts_with($code, 'DATAOBJECT') => 'Administration',
        $code === 'ADMIN_ALL', $code === 'SYSADMIN' => 'Platform',
        default => 'Other',
    };
};

$groupItems = static function (array $items, callable $resolver, array $areaOrder): array {
    $grouped = [];
    foreach ($areaOrder as $area) {
        $grouped[$area] = [];
    }
    foreach ($items as $item) {
        $item = trim((string)$item);
        if ($item === '') continue;
        $area = $resolver($item);
        $grouped[$area][] = $item;
    }
    return array_filter($grouped, static fn(array $vals): bool => $vals !== []);
};

$rolesByArea = $groupItems($roles, $groupRoleArea, $areaOrder);
$permsByArea = $groupItems($perms, $groupPermArea, $areaOrder);

$isIframe = !empty($_GET['iframe']);
$refreshParams = ['route' => 'auth/refreshAccess', 'UserID' => (string) $uid];
if ($isIframe) {
    $refreshParams['iframe'] = '1';
}
if ((string) ($_GET['link_context'] ?? '') === '1') {
    $refreshParams['link_context'] = '1';
}
if (isset($_GET['fy']) && is_numeric($_GET['fy'])) {
    $refreshParams['fy'] = (string) ((int) $_GET['fy']);
}
if (isset($_GET['ver']) && is_numeric($_GET['ver'])) {
    $refreshParams['ver'] = (string) ((int) $_GET['ver']);
}
if (array_key_exists('scope_dataobject_code', $_GET)) {
    $refreshParams['scope_dataobject_code'] = (string) $_GET['scope_dataobject_code'];
    $scopeName = trim((string) ($_GET['scope_dataobject_name'] ?? ''));
    if ($refreshParams['scope_dataobject_code'] !== '' && $scopeName !== '') {
        $refreshParams['scope_dataobject_name'] = $scopeName;
    }
}
$refreshUrl = 'index.php?' . http_build_query($refreshParams);
?>

<?php if ($isIframe): ?>
<!doctype html>
<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">
<head>
  <meta charset="utf-8">
  <title><?= h($title ?? __t('account_access')) ?></title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/icons/bootstrap-icons.css" rel="stylesheet">
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</head>
<body class="p-3 bg-light">
<div class="container-fluid">
<?php endif; ?>

<div class="card shadow-sm mt-4">
  <!-- Header -->
  <div class="card-header d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <i class="bi bi-person-gear me-2"></i>
      <strong><?= __t('account_access') ?></strong>
    </div>
    <div class="d-flex align-items-center gap-2">
      <form method="post"
            action="<?= h($refreshUrl) ?>"
            class="d-inline">
        <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="bi bi-arrow-repeat me-1"></i><?= __t('refresh_access') ?>
        </button>
      </form>
     </div>
  </div>

  <div class="card-body">
    <p class="text-muted mb-3"><?= __t('account_access_intro') ?></p>
    <style>
      .access-group-card .card-header {
        background: #fff;
        padding: .55rem .8rem;
      }
      .access-group-card .card-body {
        padding: .75rem .8rem;
      }
      .access-chip {
        font-size: .78rem;
        font-weight: 600;
        border-radius: 999px;
        padding: .35rem .55rem;
      }
      .access-section-label {
        font-size: .82rem;
        font-weight: 700;
        color: #536273;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin-bottom: .45rem;
      }
      .access-empty {
        font-size: .88rem;
      }
    </style>

    <div class="row g-3">
      <!-- User info -->
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100">
          <div class="card-header"><strong><?= __t('user') ?></strong></div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-sm-4"><?= __t('user_id') ?></dt>
              <dd class="col-sm-8"><?= h($uid ?? '—') ?></dd>

              <dt class="col-sm-4"><?= __t('username_label') ?></dt>
              <dd class="col-sm-8"><?= h($username) ?></dd>

              <dt class="col-sm-4"><?= __t('last_refreshed') ?></dt>
              <dd class="col-sm-8"><?= h($refreshedAt ?: __t('not_refreshed_yet')) ?></dd>
            </dl>
          </div>
        </div>
      </div>

      <!-- Roles -->
      <div class="col-12 col-lg-6">
        <div class="card shadow-sm h-100 access-group-card">
          <div class="card-header"><strong><?= __t('roles') ?></strong></div>
          <div class="card-body">
            <?php if ($rolesByArea): ?>
              <div class="row g-3">
                <?php foreach ($rolesByArea as $area => $items): ?>
                  <div class="col-12">
                    <div class="access-section-label"><?= h($area) ?></div>
                    <div class="d-flex flex-wrap gap-2">
                      <?php foreach ($items as $r): ?>
                        <span class="badge text-bg-primary access-chip"><?= h($r) ?></span>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-muted access-empty"><?= __t('no_roles_in_session') ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Permissions -->
      <div class="col-12">
        <div class="card shadow-sm access-group-card">
          <div class="card-header"><strong><?= __t('permissions') ?></strong></div>
          <div class="card-body">
            <?php if ($permsByArea): ?>
              <div class="row g-3">
                <?php foreach ($permsByArea as $area => $items): ?>
                  <div class="col-12 col-xl-6">
                    <div class="border rounded-3 p-3 h-100">
                      <div class="access-section-label"><?= h($area) ?></div>
                      <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($items as $p): ?>
                          <span class="badge text-bg-secondary access-chip"><?= h($p) ?></span>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-muted access-empty"><?= __t('no_perms_in_session') ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <hr class="mt-4 mb-2">
    <p class="text-muted small mb-0">
      <?= __t('access_refreshed') ?> <?= __t('at_time') ?>: <?= h($refreshedAt ?: __t('not_refreshed_yet')) ?>
    </p>
  </div>
</div>

<?php if ($isIframe): ?>
</div>
</body>
</html>
<?php endif; ?>
