<?php
declare(strict_types=1);

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                $k = trim($k); $v = trim($v);
                if ($k === '') continue;
                if (getenv($k) === false) {
                    putenv($k.'='.$v);
                }
            }
        }

        if (function_exists('cbms_apply_app_timezone')) {
            cbms_apply_app_timezone();
        }
    }
}

if (!function_exists('envStr')) {
    function envStr(string $key, ?string $default = null): ?string
    {
        $v = getenv($key);
        return ($v === false) ? $default : $v;
    }
}

if (!function_exists('envBool')) {
    function envBool(string $key, bool $default = false): bool
    {
        $v = envStr($key, null);
        if ($v === null) return $default;
        $v = strtolower(trim($v));
        return in_array($v, ['1','true','yes','on'], true);
    }
}

if (!function_exists('cbms_apply_app_timezone')) {
    function cbms_apply_app_timezone(): void
    {
        static $lastApplied = null;

        $timezone = trim((string) envStr('APP_TIMEZONE', 'Australia/Sydney'));
        if ($timezone === '') {
            $timezone = 'Australia/Sydney';
        }

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Australia/Sydney';
        }

        if ($lastApplied === $timezone) {
            return;
        }

        date_default_timezone_set($timezone);
        $lastApplied = $timezone;
    }
}

cbms_apply_app_timezone();
