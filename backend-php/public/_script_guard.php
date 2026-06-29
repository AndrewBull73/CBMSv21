<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../shared/csrf.php';
require_once __DIR__ . '/../app/Core/Rbac.php';

if (!function_exists('cbms_public_script_guard')) {
    function cbms_public_script_guard(array $options = []): void
    {
        $allowCli = !empty($options['allowCli']);
        if ($allowCli && PHP_SAPI === 'cli') {
            return;
        }

        $isEmbedded = !empty($options['isEmbedded']);
        if (!empty($options['embeddedOnly']) && !$isEmbedded) {
            cbms_public_script_deny(404, 'Not found.');
        }

        if (!empty($options['debugOnly']) && !envFlag('APP_DEBUG', false)) {
            cbms_public_script_deny(404, 'Not found.');
        }

        $authRequired = !array_key_exists('auth', $options) || !empty($options['auth']);
        $userId = (int) \App\Shared\SessionHelper::get('auth.user_id', 0);
        if ($authRequired && $userId <= 0) {
            cbms_public_script_deny(403, 'You must be signed in to access this utility.');
        }

        $permsAny = array_values(array_filter(array_map(
            static fn (mixed $perm): string => strtoupper(trim((string) $perm)),
            is_array($options['permsAny'] ?? null) ? $options['permsAny'] : []
        )));
        if ($permsAny !== [] && !\App\Core\Rbac::canAny($permsAny)) {
            cbms_public_script_deny(403, cbms_public_script_access_message($permsAny), $permsAny);
        }

        if (!empty($options['csrfPost']) && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
                cbms_public_script_deny(400, 'Invalid request token.');
            }
        }
    }
}

if (!function_exists('cbms_public_script_access_message')) {
    function cbms_public_script_access_message(array $permissionCodes): string
    {
        $labels = [];
        foreach ($permissionCodes as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code === '') {
                continue;
            }
            $labels[] = cbms_public_script_permission_label($code);
        }
        $labels = array_values(array_unique($labels));
        $required = $labels !== [] ? implode(' or ', $labels) : 'the required access';

        return 'Your account does not include the access needed for this utility. Required access: ' . $required . '. Ask a system administrator to update your assigned roles if this access is required for your work.';
    }
}

if (!function_exists('cbms_public_script_permission_label')) {
    function cbms_public_script_permission_label(string $code): string
    {
        $labels = [
            'ADMIN_ALL' => 'Super Administrator access',
            'SYSADMIN' => 'System Administrator access',
            'CALC_ADMIN' => 'Calculation Administration access',
            'ESTIMATES_EDIT' => 'Budget Planning edit access',
            'ESTIMATES_VIEW' => 'Budget Planning view access',
        ];

        return ($labels[$code] ?? ucwords(strtolower(str_replace('_', ' ', $code)))) . ' (' . $code . ')';
    }
}

if (!function_exists('cbms_public_script_deny')) {
    function cbms_public_script_deny(int $statusCode, string $message, array $missingPermissions = []): void
    {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        $lang = htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8');
        $noticeTitle = $statusCode === 403 ? 'Access Restricted' : \App\Shared\Lang::translateLiteral('Access denied');
        $noticeText = \App\Shared\Lang::translateLiteral($message);
        $noticeRequirement = $statusCode === 403
            ? 'If this access is required for your work, ask a system administrator to update your assigned roles.'
            : '';
        $noticeMissingPermissions = array_values(array_unique(array_filter(array_map(
            static fn ($code): string => strtoupper(trim((string) $code)),
            $missingPermissions
        ))));

        ob_start();
        $noticeVariant = 'compact';
        $partial = __DIR__ . '/../app/Views/shared/AccessDeniedNotice.php';
        if (is_file($partial)) {
            require $partial;
            $body = (string) ob_get_clean();
        } else {
            ob_end_clean();
            $body = '<h2>' . htmlspecialchars($noticeTitle, ENT_QUOTES, 'UTF-8') . '</h2><p>' . htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        echo "<!doctype html><html lang='{$lang}'><head><meta charset='utf-8'><title>"
            . htmlspecialchars($noticeTitle, ENT_QUOTES, 'UTF-8')
            . "</title></head><body style='font-family:Segoe UI,Arial,sans-serif;margin:2rem;'>{$body}</body></html>";
        exit;
    }
}
