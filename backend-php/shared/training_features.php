<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

if (!function_exists('training_features_bool')) {
    function training_features_bool(?string $value, bool $default = false): bool
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

if (!function_exists('training_features_enabled')) {
    function training_features_enabled(?\PDO $pdo = null): bool
    {
        static $cachedByMode = [];

        $cacheKey = $pdo instanceof \PDO ? 'db' : 'no-db';
        if (array_key_exists($cacheKey, $cachedByMode)) {
            return $cachedByMode[$cacheKey];
        }

        $envValue = envStr('TRAINING_FEATURES_ENABLED', null);
        if ($envValue !== null && trim($envValue) !== '') {
            return $cachedByMode[$cacheKey] = training_features_bool($envValue, false);
        }

        if ($pdo instanceof \PDO) {
            try {
                require_once __DIR__ . '/../app/Models/SystemSettingsModel.php';
                $settings = new \App\Models\SystemSettingsModel($pdo);
                $settingValue = $settings->get('TRAINING_FEATURES_ENABLED', null);
                if ($settingValue !== null && trim($settingValue) !== '') {
                    return $cachedByMode[$cacheKey] = training_features_bool($settingValue, false);
                }
            } catch (\Throwable $e) {
                // Fall through to default false.
            }
        }

        return $cachedByMode[$cacheKey] = false;
    }
}
