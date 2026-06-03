<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Shared\SessionHelper;

require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/login_throttle_db.php';

final class AuthController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true],
        'loginForm' => ['auth' => false],
        'login' => ['auth' => false],
        'tokenLogin' => ['auth' => false],
        'logout' => ['auth' => true],
        'account' => ['auth' => true],
        'refreshAccess' => ['auth' => true],
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function loginForm(): void
{
    require_once __DIR__ . '/../../shared/csrf.php';

    // GENERATE CSRF TOKEN — THIS WAS MISSING
    csrf_token();

    if (($_GET['reason'] ?? '') === 'forced') {
        \App\Shared\SessionHelper::set('flash.message', [
            'type' => 'warning',
            'text' => 'Your session was terminated by an administrator. Please log in again.'
        ]);
        session_write_close();
        header('Location: index.php?route=auth/loginForm&shown=1');
        exit;
    }

    if (($_GET['shown'] ?? '') === '1') {
        session_write_close();
        header('Location: index.php?route=auth/loginForm');
        exit;
    }

    $returnUrl = $this->normalizeReturnUrl((string) ($_GET['return'] ?? ''));

    if (SessionHelper::get('auth.user_id')) {
        session_write_close();
        header('Location: ' . ($returnUrl !== '' ? $returnUrl : 'index.php?route=home/index'));
        exit;
    }

    require __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../Models/SystemSettingsModel.php';
    $settingsModel = new \App\Models\SystemSettingsModel($conn);
    $authMode = strtolower($settingsModel->get('AUTH_LOGIN_MODE', 'forms'));

    // ... rest of windows auth ...

    $this->render('auth/login', [
        'title' => __t('login'),
        'returnUrl' => $returnUrl,
    ]);
}

    public function login(): void
{
    require_once __DIR__ . '/../../shared/csrf.php';
    SessionHelper::ensureSession();
    app_log('[SESSION DEBUG] login() start SID=' . session_id());
   
    require __DIR__ . '/../../config/db.php';
    require_once __DIR__ . '/../Models/SystemSettingsModel.php';
    $settingsModel = new \App\Models\SystemSettingsModel($conn);
    $authMode = strtolower($settingsModel->get('AUTH_LOGIN_MODE', 'forms'));

    if ($authMode === 'windows') {
        $this->flashError(__t('method_not_allowed'));
        session_write_close();
        header('Location: index.php?route=auth/loginForm');
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo __t('method_not_allowed');
        return;
    }

    if (!csrf_check($_POST['_csrf'] ?? '')) {
        $this->flashError(__t('security_check_failed'));
        session_write_close();
        header('Location: index.php?route=auth/loginForm');
        exit;
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $returnUrl = $this->normalizeReturnUrl((string) ($_POST['return'] ?? ''));
    $ipRaw = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $ip = trim(explode(',', $ipRaw)[0]);

    if ($username === '' || $password === '') {
        $this->flashError(__t('username_password_required'));
        session_write_close();
        header('Location: index.php?route=auth/loginForm');
        exit;
    }

    $uKey = mb_strtolower($username, 'UTF-8');
    $cfg = lt_get_config($conn);
    $maxAttempts = (int)$cfg['maxAttempts'];
    $decaySeconds = (int)$cfg['decaySeconds'];
    $lockSeconds = (int)$cfg['lockSeconds'];
    $permanentLock = (bool)$cfg['permanentLockout'];

    $pre = lt_precheck($conn, $uKey, $ip);
    if (!empty($pre['locked'])) {
        if (($pre['retry_after'] ?? 0) === -1) {
            $this->flashError(__t('account_locked_permanent'));
        } else {
            $minutes = max(1, (int)ceil(((int)$pre['retry_after']) / 60));
            if (!headers_sent() && isset($pre['retry_after'])) {
                header('Retry-After: ' . (int)$pre['retry_after']);
            }
            $this->flashError(__t('too_many_attempts', ['minutes' => (string)$minutes]));
        }
        session_write_close();
        header('Location: index.php?route=auth/loginForm');
        exit;
    }

    $user = null;
    if ($conn instanceof \PDO) {
        $st = $conn->prepare("
            SELECT UserID, Username, PasswordHash, IsActive
            FROM dbo.tblUsers
            WHERE Username = :u
        ");
        $st->execute(['u' => $username]);
        $user = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
    } elseif (is_resource($conn)) {
        $st = sqlsrv_prepare(
            $conn,
            "SELECT UserID, Username, PasswordHash, IsActive 
             FROM dbo.tblUsers WHERE Username = ?",
            [$username]
        );
        if ($st && sqlsrv_execute($st)) {
            $user = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC) ?: null;
            sqlsrv_free_stmt($st);
        }
    }

    $invalid =
        (!$user) ||
        ((int)($user['IsActive'] ?? 1) === 0) ||
        (!password_verify($password, (string)($user['PasswordHash'] ?? '')));

    if ($invalid) {
        $res = lt_fail($conn, $uKey, $ip, $maxAttempts, $decaySeconds, $lockSeconds, $permanentLock);
        if ($user && isset($user['UserID'])) {
            if ($conn instanceof \PDO) {
                $st = $conn->prepare("
                    UPDATE dbo.tblUsers
                    SET FailedLoginCount = ISNULL(FailedLoginCount,0) + 1,
                        LastFailedLoginAt = SYSUTCDATETIME()
                    WHERE UserID = :id
                ");
                $st->execute(['id' => (int)$user['UserID']]);
            } elseif (is_resource($conn)) {
                $st = sqlsrv_prepare(
                    $conn,
                    "UPDATE dbo.tblUsers
                    SET FailedLoginCount = ISNULL(FailedLoginCount,0) + 1,
                        LastFailedLoginAt = SYSUTCDATETIME()
                    WHERE UserID = ?",
                    [(int)$user['UserID']]
                );
                if ($st) sqlsrv_execute($st);
            }
        }

        if (!empty($res['locked'])) {
            if (($res['retry_after'] ?? 0) === -1) {
                $this->flashError(__t('account_locked_permanent'));
            } else {
                $minutes = max(1, (int)ceil(((int)($res['retry_after'] ?? 60)) / 60));
                if (!headers_sent() && isset($res['retry_after'])) {
                    header('Retry-After: ' . (int)$res['retry_after']);
                }
                $this->flashError(__t('too_many_attempts', ['minutes' => (string)$minutes]));
            }
        } else {
            $remaining = (int)($res['remaining'] ?? 0);
            $msg = __t('invalid_login');
            if ($remaining > 0) {
                $msg .= ' ' . __t('remaining_attempts', ['count' => (string)$remaining]);
            }
            $this->flashError($msg);
        }
        session_write_close();
        header('Location: index.php?route=auth/loginForm');
        exit;
    }

    lt_success($conn, $uKey, $ip);

    $this->completeLogin($conn, $user, $returnUrl);
}

    public function tokenLogin(): void
    {
        $token = trim((string)($_GET['token'] ?? ''));
        $returnUrl = $this->normalizeReturnUrl((string) ($_GET['return'] ?? ''));
        if ($token === '') {
            $this->flashError('Invalid or missing secure login token.');
            session_write_close();
            header('Location: index.php?route=auth/loginForm');
            exit;
        }

        require __DIR__ . '/../../config/db.php';
        require_once __DIR__ . '/../Models/LoginTokenModel.php';

        try {
            $tokenModel = new \App\Models\LoginTokenModel($conn);
            $user = $tokenModel->consume($token);
        } catch (\Throwable $e) {
            app_log('Token login failed', ['error' => $e->getMessage()], 'error');
            $user = null;
        }

        if (!$user || (int)($user['IsActive'] ?? 1) === 0) {
            $this->flashError('This secure login link is invalid, expired, or already used.');
            session_write_close();
            header('Location: index.php?route=auth/loginForm');
            exit;
        }

        $this->completeLogin($conn, $user, $returnUrl);
    }

    private function completeLogin(\PDO $conn, array $user, string $returnUrl = ''): void
    {
        require_once __DIR__ . '/../../shared/csrf.php';
        require_once __DIR__ . '/../Models/SystemSettingsModel.php';
        SessionHelper::ensureSession();
        $settingsModel = new \App\Models\SystemSettingsModel($conn);

    session_regenerate_id(true);
    $newSessionId = session_id();
    // REGENERATE CSRF TOKEN ON LOGIN
    csrf_regenerate();
    app_log("[SESSION DEBUG] After regenerate, ID=" . $newSessionId);
    app_log("[SESSION DEBUG] Cookie params=" . json_encode(session_get_cookie_params()));

    SessionHelper::set('auth.user_id', (int)$user['UserID']);
    SessionHelper::set('auth.username', (string)$user['Username']);
    SessionHelper::set('auth.login_time', time());
    SessionHelper::set('auth.last_activity', time());
    SessionHelper::set('auth.just_logged_in', true);
    csrf_regenerate();

    $rbac = new \App\Core\Rbac($conn);
    $rbac->loadForUser((int)$user['UserID']);
    $roles = $rbac->roles();
    $perms = $rbac->perms();
    SessionHelper::set('auth.roles', $roles);
    SessionHelper::set('auth.perms', $perms);
    app_log("Login: Set roles and perms", ['userId' => $user['UserID'], 'roles' => $roles, 'perms' => $perms, 'session_id' => $newSessionId], 'info');

    // Initialize fiscal context (system defaults first, then first valid active FY/version)
    require_once __DIR__ . '/../Models/FiscalContextModel.php';
    $fc = new \App\Models\FiscalContextModel($conn);

    $defFy = (int) (
        $settingsModel->get('DEFAULT_FISCAL_YEAR')
        ?? $settingsModel->get('Default_Fiscal_Year')
        ?? 0
    );
    $defVer = (int) (
        $settingsModel->get('DEFAULT_VERSION')
        ?? $settingsModel->get('Default_Version')
        ?? 0
    );

    $bestFy = 0;
    $bestVer = 0;

    if ($defFy > 0 && $defVer > 0) {
        $vList = $fc->listVersions($defFy);
        foreach ($vList as $v) {
            if ((int)$v['VersionID'] === $defVer) {
                $bestFy = $defFy;
                $bestVer = $defVer;
                break;
            }
        }
    }

    if ($bestFy === 0 || $bestVer === 0) {
        $years = $fc->listFiscalYears();
        foreach ($years as $y) {
            $candidateFy = (int)$y['FiscalYearID'];
            $vList = $fc->listVersions($candidateFy);
            if (!empty($vList)) {
                $bestFy = $candidateFy;
                $bestVer = (int)$vList[0]['VersionID']; // listVersions orders default first
                break;
            }
        }
    }

    if ($bestFy > 0 && $bestVer > 0) {
        SessionHelper::set('FiscalYearID', $bestFy);
        SessionHelper::set('VersionID', $bestVer);
        app_log('Fiscal context set in login', ['fy' => $bestFy, 'ver' => $bestVer, 'session_id' => $newSessionId], 'info');
    } else {
        SessionHelper::set('FiscalYearID', 0);
        SessionHelper::set('VersionID', 0);
        app_log('No valid fiscal context found during login', ['session_id' => $newSessionId], 'warn');
    }

    // Ensure session record is created
    try {
        require_once __DIR__ . '/../Models/UserSessionModel.php';
        $sessionModel = new \App\Models\UserSessionModel($conn);
        $sessionModel->ensure(
            $newSessionId,
            (int)$user['UserID'],
            (string)$user['Username'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            1200
        );
        app_log("[SESSION DEBUG] Session record created", ['session_id' => $newSessionId, 'user_id' => $user['UserID']], 'info');
    } catch (\Throwable $e) {
        app_log('UserSessionModel.ensure failed', ['error' => $e->getMessage(), 'session_id' => $newSessionId], 'error');
    }

    $this->applyDeepLinkContextFromUrl($returnUrl);

    session_write_close();
    app_log("[SESSION DEBUG] redirect SID=" . $newSessionId, [
        'FiscalYearID' => SessionHelper::get('FiscalYearID'),
        'VersionID' => SessionHelper::get('VersionID'),
        'UserID' => (int)$user['UserID'],
        'Username' => (string)$user['Username'],
        'roles' => $roles,
        'perms' => $perms
    ], 'info');

    header('Location: ' . ($returnUrl !== '' ? $returnUrl : 'index.php?route=home/index'));
    exit;
    }

    private function normalizeReturnUrl(string $returnUrl): string
    {
        $returnUrl = trim($returnUrl);
        if ($returnUrl === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $returnUrl)) {
            $parts = parse_url($returnUrl);
            $path = (string) ($parts['path'] ?? '');
            $query = (string) ($parts['query'] ?? '');
            $indexPos = stripos($path, 'index.php');
            if ($indexPos === false) {
                return '';
            }
            $returnUrl = substr($path, $indexPos);
            if ($query !== '') {
                $returnUrl .= '?' . $query;
            }
        }

        if (str_starts_with($returnUrl, '?')) {
            $returnUrl = 'index.php' . $returnUrl;
        }

        if (!str_starts_with($returnUrl, 'index.php')) {
            return '';
        }

        if (preg_match('~[\r\n]~', $returnUrl)) {
            return '';
        }

        return $returnUrl;
    }

    private function applyDeepLinkContextFromUrl(string $returnUrl): void
    {
        if ($returnUrl === '') {
            return;
        }

        $query = parse_url($returnUrl, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return;
        }

        parse_str($query, $params);

        if (array_key_exists('scope_dataobject_code', $params)) {
            $scopeCode = trim((string) $params['scope_dataobject_code']);
            if ($scopeCode !== '') {
                SessionHelper::set('scope.dataobject_code', $scopeCode);
                SessionHelper::set('scope.dataobject_name', trim((string) ($params['scope_dataobject_name'] ?? '')));
            } else {
                SessionHelper::forget('scope.dataobject_code');
                SessionHelper::forget('scope.dataobject_name');
            }
        }

        if (!empty($params['fy']) && is_numeric($params['fy'])) {
            SessionHelper::set('FiscalYearID', (int) $params['fy']);
        }
        if (!empty($params['ver']) && is_numeric($params['ver'])) {
            SessionHelper::set('VersionID', (int) $params['ver']);
        }
    }

    public function logout(): void
    {
        SessionHelper::ensureSession();

        $reason = (string)($_GET['reason'] ?? 'manual');

        try {
            if (session_status() === PHP_SESSION_NONE) {
                \App\Shared\SessionHelper::ensureSession();
            }
            $sid = session_id();
            if ($sid) {
                require_once __DIR__ . '/../Models/UserSessionModel.php';
                require __DIR__ . '/../../config/db.php';
                $sessionModel = new \App\Models\UserSessionModel($conn);
                $sessionModel->logout($sid);
            }
        } catch (\Throwable $e) {
            app_log('UserSessionModel.logout failed', ['error' => $e->getMessage(), 'session_id' => session_id()], 'error');
        }

        switch ($reason) {
            case 'idle':
                $msg = __t('session_expired_idle');
                break;
            case 'absolute':
                $msg = __t('session_expired_absolute');
                break;
            case 'forced':
                $msg = __t('session_forced_logout');
                break;
            default:
                $msg = __t('logged_out');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool)$params['secure'],
                (bool)$params['httponly']
            );
        }

        session_destroy();
        \App\Shared\SessionHelper::ensureSession();
        \App\Shared\SessionHelper::set('flash.message', [
            'type' => 'info',
            'text' => $msg,
        ]);

        csrf_regenerate();
        session_write_close();
        header('Location: index.php?route=auth/loginForm');
        exit;
    }

    public function account(): void
    {
        require __DIR__ . '/../../config/db.php';
        $currentUserId = (int)SessionHelper::get('auth.user_id');
        $currentUsername = (string)(SessionHelper::get('auth.username') ?? '');
        $adminPerms = SessionHelper::get('auth.perms', []);
        $targetUserId = isset($_GET['UserID']) ? (int)$_GET['UserID'] : $currentUserId;

        $isAdmin = in_array('USERS_ADMIN', $adminPerms, true) || in_array('USERS_VIEW', $adminPerms, true);
        if ($targetUserId !== $currentUserId && !$isAdmin) {
            http_response_code(403);
            $this->render('errors/403.php', ['message' => 'You do not have permission to view other users\' accounts.']);
            return;
        }

        $st = $conn->prepare("
            SELECT UserID, Username, IsActive, LastLoginAt, LastLoginIP
            FROM dbo.tblUsers
            WHERE UserID = :id
        ");
        $st->execute(['id' => $targetUserId]);
        $account = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$account) {
            http_response_code(404);
            $this->render('errors/404.php', ['message' => 'User not found']);
            return;
        }

        $targetUsername = (string)$account['Username'];
        require_once __DIR__ . '/../Models/UserRoleModel.php';
        $userRoleModel = new \App\Models\UserRoleModel($conn);
        $roleRows = $userRoleModel->listByUser($targetUserId);
        $roles = array_map(fn($r) => (string)($r['RoleName'] ?? ''), $roleRows);
        $roles = array_filter($roles);

        $perms = [];
        if (!empty($roles)) {
            $sql = <<<SQL
                SELECT DISTINCT p.PermissionCode
                FROM dbo.tblUserRoles ur
                JOIN dbo.tblRolePermissions rp ON rp.RoleID = ur.RoleID
                JOIN dbo.tblPermissions p ON p.PermissionID = rp.PermissionID
                JOIN dbo.tblRoles r ON r.RoleID = ur.RoleID
                WHERE ur.UserID = :uid
                  AND (p.Active = 1 OR p.Active IS NULL)
                  AND (r.Active = 1 OR r.Active IS NULL)
            SQL;
            $stPerms = $conn->prepare($sql);
            $stPerms->execute(['uid' => $targetUserId]);
            $rows = $stPerms->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $perms = array_map(static fn($r) => strtoupper((string)$r['PermissionCode']), $rows);
        }

        $params = [
            'title' => __t('account_access'),
            'userId' => (int)$account['UserID'],
            'username' => $targetUsername,
            'roles' => $roles,
            'perms' => $perms,
            'refreshedAt' => SessionHelper::get('auth.access_refreshed_at'),
            'backUrl' => "index.php?route=users/list",
            'isAdminView' => ($isAdmin && $targetUserId !== $currentUserId),
        ];

        $isIframe = !empty($_GET['iframe']);
        if ($isIframe) {
            $this->renderPartial('auth/RefreshAccessView', $params);
        } else {
            $this->render('auth/RefreshAccessView', $params);
        }
    }

    public function refreshAccess(): void
    {
        if (!SessionHelper::get('auth.user_id')) {
            $this->flashError(__t('please_login'));
            session_write_close();
            header('Location: index.php?route=auth/loginForm');
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }
        require_once __DIR__ . '/../../shared/csrf.php';
        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            session_write_close();
            header('Location: index.php?route=auth/loginForm');
            return;
        }

        require __DIR__ . '/../../config/db.php';
        $currentUserId = (int)SessionHelper::get('auth.user_id');
        $currentUsername = (string)(SessionHelper::get('auth.username') ?? '');
        $adminPerms = SessionHelper::get('auth.perms', []);
        $targetUserId = isset($_GET['UserID']) ? (int)$_GET['UserID'] : $currentUserId;

        $isAdmin = in_array('USERS_ADMIN', $adminPerms, true) || in_array('USERS_VIEW', $adminPerms, true);
        if ($targetUserId !== $currentUserId && !$isAdmin) {
            http_response_code(403);
            $this->render('errors/403.php', ['message' => 'Not authorized to refresh another user’s access.']);
            return;
        }

        $st = $conn->prepare("SELECT Username FROM dbo.tblUsers WHERE UserID = :id");
        $st->execute(['id' => $targetUserId]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        $targetUsername = $row['Username'] ?? ('User#' . $targetUserId);

        require_once __DIR__ . '/../Models/UserRoleModel.php';
        $userRoleModel = new \App\Models\UserRoleModel($conn);
        $roleRows = $userRoleModel->listByUser($targetUserId);
        $rolesAfter = array_map(fn($r) => (string)($r['RoleName'] ?? ''), $roleRows);
        $rolesAfter = array_filter($rolesAfter);

        $permsAfter = [];
        if ($rolesAfter) {
            $sql = <<<SQL
                SELECT DISTINCT p.PermissionCode
                FROM dbo.tblUserRoles ur
                JOIN dbo.tblRolePermissions rp ON rp.RoleID = ur.RoleID
                JOIN dbo.tblPermissions p ON p.PermissionID = rp.PermissionID
                JOIN dbo.tblRoles r ON r.RoleID = ur.RoleID
                WHERE ur.UserID = :uid
                  AND (p.Active = 1 OR p.Active IS NULL)
                  AND (r.Active = 1 OR r.Active IS NULL)
            SQL;
            $stPerms = $conn->prepare($sql);
            $stPerms->execute(['uid' => $targetUserId]);
            $rows = $stPerms->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $permsAfter = array_map(static fn($r) => strtoupper((string)$r['PermissionCode']), $rows);
        }

        $rolesBefore = SessionHelper::get('auth.roles', []);
        $permsBefore = $adminPerms;

        $when = gmdate('Y-m-d H:i:s') . ' UTC';
        SessionHelper::set('auth.access_refreshed_at', $when);

        $dbName = null;
        if ($conn instanceof \PDO) {
            $stDb = $conn->query("SELECT DB_NAME() AS name");
            $dbName = ($stDb && ($dbRow = $stDb->fetch(\PDO::FETCH_ASSOC))) ? ($dbRow['name'] ?? null) : null;
        }

        try {
            require_once __DIR__ . '/../Models/AuditModel.php';
            $audit = new \App\Models\AuditModel($conn);
            $audit->insert([
                'UserID' => $currentUserId,
                'Username' => $currentUsername,
                'Action' => 'ACCESS_REFRESH',
                'Entity' => 'AUTH',
                'EntityKey' => (string)$targetUserId,
                'FiscalYearID' => SessionHelper::get('FiscalYearID'),
                'VersionID' => SessionHelper::get('VersionID'),
                'Details' => [
                    'targetUserID' => $targetUserId,
                    'targetUsername' => $targetUsername,
                    'asAdmin' => ($targetUserId !== $currentUserId),
                    'when' => $when,
                ],
            ]);
        } catch (\Throwable $e) {
            app_log('audit.insert failed in refreshAccess()', ['error' => $e->getMessage(), 'targetUserID' => $targetUserId], 'error');
        }

        $params = [
            'title' => __t('access_refreshed'),
            'userId' => $targetUserId,
            'username' => $targetUsername,
            'dbName' => $dbName ?? '(unknown)',
            'rolesBefore' => $rolesBefore,
            'permsBefore' => $permsBefore,
            'rolesAfter' => $rolesAfter,
            'permsAfter' => $permsAfter,
            'refreshedAt' => $when,
            'backUrl' => "index.php?route=auth/account&UserID={$targetUserId}&iframe=1",
        ];

        $isIframe = !empty($_GET['iframe']);
        if ($isIframe) {
            $this->renderPartial('auth/RefreshAccessView', $params);
        } else {
            $this->render('auth/RefreshAccessView', $params);
        }
    }
}
