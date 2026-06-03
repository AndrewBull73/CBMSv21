<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

if (!function_exists('screen_test_capture_bool')) {
    function screen_test_capture_bool(?string $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('screen_test_capture_enabled')) {
    function screen_test_capture_enabled(?\PDO $pdo = null): bool
    {
        static $cachedByMode = [];

        $cacheKey = $pdo instanceof \PDO ? 'db' : 'no-db';
        if (array_key_exists($cacheKey, $cachedByMode)) {
            return $cachedByMode[$cacheKey];
        }

        $envValue = envStr('SCREEN_TEST_CAPTURE_ENABLED', null);
        if ($envValue !== null && trim($envValue) !== '') {
            return $cachedByMode[$cacheKey] = screen_test_capture_bool($envValue, false);
        }

        if ($pdo instanceof \PDO) {
            try {
                require_once __DIR__ . '/../app/Models/SystemSettingsModel.php';
                $settings = new \App\Models\SystemSettingsModel($pdo);
                $settingValue = $settings->get('SCREEN_TEST_CAPTURE_ENABLED', null);
                if ($settingValue !== null && trim($settingValue) !== '') {
                    return $cachedByMode[$cacheKey] = screen_test_capture_bool($settingValue, false);
                }
            } catch (\Throwable $e) {
                // Fall through to environment mode default.
            }
        }

        $appEnv = strtolower(trim((string) envStr('APP_ENV', 'production')));
        return $cachedByMode[$cacheKey] = in_array($appEnv, ['local', 'dev', 'development', 'test', 'testing', 'uat'], true);
    }
}
