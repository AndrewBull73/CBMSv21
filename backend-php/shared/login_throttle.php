<?php
declare(strict_types=1);

/**
 * Lightweight, file-based login throttle (per username + IP).
 * - Records failed attempts in a JSON file under a writable temp dir.
 * - Enforces per-minute and per-hour caps; on exceed, sets a lockout window.
 * - Safe on Windows: guarded unlink() and flock()-based writes to avoid races.
 *
 * Public API (idempotent, safe to call multiple times):
 *   lt_check(string $username, string $ip): array
 *   lt_register_failure(string $username, string $ip): void
 *   lt_success(string $username, string $ip): void
 *   lt_clear(string $username, string $ip): void
 *
 * Configuration via environment variables (all optional):
 *   LOGIN_THROTTLE_DIR        — directory for state files (default: sys_get_temp_dir()/cbms_throttle)
 *   LOGIN_MAX_PER_MIN         — default 5
 *   LOGIN_MAX_PER_HOUR        — default 20
 *   LOGIN_LOCKOUT_SECONDS     — default 900 (15 minutes)
 */

if (!function_exists('lt_now')) {
    function lt_now(): int { return time(); }
}
if (!function_exists('lt_epoch_minute')) {
    function lt_epoch_minute(int $t): int { return intdiv($t, 60) * 60; }
}
if (!function_exists('lt_epoch_hour')) {
    function lt_epoch_hour(int $t): int { return intdiv($t, 3600) * 3600; }
}

if (!function_exists('lt_config')) {
    function lt_config(): array {
        $dir = getenv('LOGIN_THROTTLE_DIR') ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cbms_throttle');
        $perMin = (int) (getenv('LOGIN_MAX_PER_MIN') ?: 5);
        $perHour = (int) (getenv('LOGIN_MAX_PER_HOUR') ?: 20);
        $lockout = (int) (getenv('LOGIN_LOCKOUT_SECONDS') ?: 900);
        if ($perMin < 1)  $perMin = 5;
        if ($perHour < 1) $perHour = 20;
        if ($lockout < 1) $lockout = 900;
        return ['dir'=>$dir, 'per_min'=>$perMin, 'per_hour'=>$perHour, 'lockout'=>$lockout];
    }
}

if (!function_exists('lt_dir')) {
    function lt_dir(): string {
        $dir = lt_config()['dir'];
        if (!is_dir($dir)) {
            // Try to create; suppress warnings, then verify.
            @mkdir($dir, 0700, true);
        }
        // If still not a dir or not writable, fallback to system temp.
        if (!is_dir($dir) || !is_writable($dir)) {
            $fallback = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cbms_throttle';
            if (!is_dir($fallback)) {
                @mkdir($fallback, 0700, true);
            }
            $dir = $fallback;
        }
        return $dir;
    }
}

if (!function_exists('lt_key')) {
    function lt_key(string $username, string $ip): string {
        $u = mb_strtolower(trim($username));
        $i = trim($ip ?: '0.0.0.0');
        return sha1($u . '|' . $i);
    }
}

if (!function_exists('lt_path')) {
    function lt_path(string $username, string $ip): string {
        return lt_dir() . DIRECTORY_SEPARATOR . lt_key($username, $ip) . '.json';
    }
}

if (!function_exists('lt_default_state')) {
    function lt_default_state(string $username, string $ip, int $now): array {
        return [
            'user'          => mb_strtolower(trim($username)),
            'ip'            => trim($ip ?: '0.0.0.0'),
            'first_seen'    => $now,
            'last_failed'   => null,
            'minute'        => ['start' => lt_epoch_minute($now), 'count' => 0],
            'hour'          => ['start' => lt_epoch_hour($now),   'count' => 0],
            'lockout_until' => 0,
        ];
    }
}

if (!function_exists('lt_read_state')) {
    function lt_read_state(string $username, string $ip): array {
        $now  = lt_now();
        $path = lt_path($username, $ip);
        if (!is_file($path)) {
            return lt_default_state($username, $ip, $now);
        }

        // Lock for read without blocking too long
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            // If can't open for read, assume default (best-effort)
            return lt_default_state($username, $ip, $now);
        }
        @flock($fh, LOCK_SH);
        $raw = @stream_get_contents($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);

        $data = $raw ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return lt_default_state($username, $ip, $now);
        }

        // Ensure required keys exist
        $data['minute'] = $data['minute'] ?? ['start'=>lt_epoch_minute($now),'count'=>0];
        $data['hour']   = $data['hour']   ?? ['start'=>lt_epoch_hour($now),'count'=>0];
        $data['lockout_until'] = (int)($data['lockout_until'] ?? 0);

        return $data;
    }
}

if (!function_exists('lt_write_state')) {
    function lt_write_state(string $username, string $ip, array $state): void {
        $path = lt_path($username, $ip);
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        // Write with exclusive lock, truncate, then flush; safe on Windows.
        $fh = @fopen($path, 'c+'); // create if not exists
        if (!$fh) {
            // As a fallback, attempt simple write; suppress warnings
            @file_put_contents($path, json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
            return;
        }
        @flock($fh, LOCK_EX);
        @ftruncate($fh, 0);
        @rewind($fh);
        @fwrite($fh, json_encode($state, JSON_UNESCAPED_SLASHES));
        @fflush($fh);
        @flock($fh, LOCK_UN);
        @fclose($fh);
    }
}

if (!function_exists('lt_rotate_windows')) {
    function lt_rotate_windows(array &$state, int $now): void {
        $minStart  = lt_epoch_minute($now);
        $hourStart = lt_epoch_hour($now);
        if (!isset($state['minute']['start']) || $state['minute']['start'] !== $minStart) {
            $state['minute'] = ['start' => $minStart, 'count' => 0];
        }
        if (!isset($state['hour']['start']) || $state['hour']['start'] !== $hourStart) {
            $state['hour'] = ['start' => $hourStart, 'count' => 0];
        }
    }
}

/**
 * Check throttle status for a username/IP.
 * Returns:
 * [
 *   'ok' => bool,                     // allowed to attempt now?
 *   'locked_until' => int|null,       // epoch seconds if locked, else null
 *   'retry_after' => int|null,        // seconds until unlock, else null
 *   'remaining_min' => int,           // remaining attempts in current minute window
 *   'remaining_hour'=> int,           // remaining attempts in current hour window
 * ]
 */
if (!function_exists('lt_check')) {
    function lt_check(string $username, string $ip): array {
        $cfg   = lt_config();
        $now   = lt_now();
        $state = lt_read_state($username, $ip);
        lt_rotate_windows($state, $now);

        $lockedUntil = (int)($state['lockout_until'] ?? 0);
        if ($lockedUntil > $now) {
            return [
                'ok'             => false,
                'locked_until'   => $lockedUntil,
                'retry_after'    => $lockedUntil - $now,
                'remaining_min'  => 0,
                'remaining_hour' => 0,
            ];
        }

        $remMin  = max(0, $cfg['per_min']  - (int)$state['minute']['count']);
        $remHour = max(0, $cfg['per_hour'] - (int)$state['hour']['count']);

        return [
            'ok'             => true,
            'locked_until'   => null,
            'retry_after'    => null,
            'remaining_min'  => $remMin,
            'remaining_hour' => $remHour,
        ];
    }
}

/**
 * Register a failed login attempt. Will update counters and set lockout if thresholds exceeded.
 */
if (!function_exists('lt_register_failure')) {
    function lt_register_failure(string $username, string $ip): void {
        $cfg   = lt_config();
        $now   = lt_now();
        $path  = lt_path($username, $ip);

        // Load + lock for update
        $state = lt_read_state($username, $ip);
        lt_rotate_windows($state, $now);

        $state['last_failed'] = $now;
        $state['minute']['count'] = (int)$state['minute']['count'] + 1;
        $state['hour']['count']   = (int)$state['hour']['count']   + 1;

        // If already locked and still within lock window, keep it unchanged
        $lockedUntil = (int)($state['lockout_until'] ?? 0);

        // Trigger new lockout if thresholds exceeded
        if ($state['minute']['count'] > $cfg['per_min'] || $state['hour']['count'] > $cfg['per_hour']) {
            $lockedUntil = max($lockedUntil, $now + (int)$cfg['lockout']);
            $state['lockout_until'] = $lockedUntil;
        }

        lt_write_state($username, $ip, $state);
    }
}

/**
 * Clear throttle state after a successful login (best-effort).
 * Safe if file does not exist or is temporarily locked.
 */
if (!function_exists('lt_clear')) {
    function lt_clear(string $username, string $ip): void {
        $path = lt_path($username, $ip);

        if (!is_file($path)) {
            return; // nothing to do
        }

        // Try to unlink quietly; if it fails (Windows lock), truncate.
        if (@unlink($path)) {
            return;
        }

        $fh = @fopen($path, 'c+');
        if ($fh) {
            @flock($fh, LOCK_EX);
            @ftruncate($fh, 0);
            @fflush($fh);
            @flock($fh, LOCK_UN);
            @fclose($fh);
        }
    }
}

/**
 * Alias for semantic clarity: call after a correct password to reset counts.
 */
if (!function_exists('lt_success')) {
    function lt_success(string $username, string $ip): void {
        // Only try to clear if a state file likely exists (optional micro-optimization)
        $path = lt_path($username, $ip);
        if (is_file($path)) {
            lt_clear($username, $ip);
        }
    }
}
