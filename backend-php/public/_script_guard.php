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
            cbms_public_script_deny(403, 'You do not have permission to access this utility.');
        }

        if (!empty($options['csrfPost']) && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!csrf_check((string) ($_POST['_csrf'] ?? ''))) {
                cbms_public_script_deny(400, 'Invalid request token.');
            }
        }
    }
}

if (!function_exists('cbms_public_script_deny')) {
    function cbms_public_script_deny(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $lang = htmlspecialchars(\App\Shared\Lang::getActiveLang(), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars(\App\Shared\Lang::translateLiteral('Access denied'), ENT_QUOTES, 'UTF-8');
        $translatedMessage = htmlspecialchars(\App\Shared\Lang::translateLiteral($message), ENT_QUOTES, 'UTF-8');
        echo "<!doctype html><html lang='{$lang}'><head><meta charset='utf-8'><title>{$title}</title></head><body style='font-family:Segoe UI,Arial,sans-serif;margin:2rem;'><h2>{$title}</h2><p>{$translatedMessage}</p></body></html>";
        exit;
    }
}
