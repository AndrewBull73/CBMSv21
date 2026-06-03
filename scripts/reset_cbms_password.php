<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$repoRoot = dirname(__DIR__);

require_once $repoRoot . '/vendor/autoload.php';
require_once $repoRoot . '/backend-php/config/db.php';
require_once $repoRoot . '/backend-php/shared/login_throttle_db.php';

/**
 * Reset a CBMSv21 user password and clear login lockout state.
 *
 * Usage:
 *   php scripts/reset_cbms_password.php <username> <new-password> [--force-reset=0|1] [--must-change=0|1]
 *
 * Example:
 *   php scripts/reset_cbms_password.php admin NewPass!234
 *   php scripts/reset_cbms_password.php admin NewPass!234 --must-change=1
 */

function usage(): void
{
    $message = <<<TXT
Usage:
  php scripts/reset_cbms_password.php <username> <new-password> [--force-reset=0|1] [--must-change=0|1]

Examples:
  php scripts/reset_cbms_password.php admin NewPass!234
  php scripts/reset_cbms_password.php admin NewPass!234 --must-change=1

TXT;

    fwrite(STDOUT, $message);
}

function parseBoolFlag(array $options, string $name, int $default): int
{
    if (!array_key_exists($name, $options) || $options[$name] === false) {
        return $default;
    }

    $value = strtolower(trim((string) $options[$name]));
    return in_array($value, ['1', 'true', 'yes', 'y', 'on'], true) ? 1 : 0;
}

$options = getopt('', ['force-reset::', 'must-change::', 'help']);

if (isset($options['help'])) {
    usage();
    exit(0);
}

$args = $_SERVER['argv'] ?? [];
array_shift($args);

$positionals = [];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--')) {
        continue;
    }
    $positionals[] = $arg;
}

if (count($positionals) < 2) {
    usage();
    exit(1);
}

$username = trim((string) $positionals[0]);
$newPassword = (string) $positionals[1];
$forceReset = parseBoolFlag($options, 'force-reset', 0);
$mustChange = parseBoolFlag($options, 'must-change', 0);

if ($username === '') {
    fwrite(STDERR, "Username cannot be empty.\n");
    exit(1);
}

if ($newPassword === '') {
    fwrite(STDERR, "Password cannot be empty.\n");
    exit(1);
}

if (!isset($conn) || !($conn instanceof PDO)) {
    fwrite(STDERR, "Database connection is not available.\n");
    exit(1);
}

$st = $conn->prepare('
    SELECT UserID, Username, IsActive
    FROM dbo.tblUsers
    WHERE Username = :username
');
$st->execute([':username' => $username]);
$user = $st->fetch(PDO::FETCH_ASSOC) ?: null;

if ($user === null) {
    fwrite(STDERR, "User not found: {$username}\n");
    exit(1);
}

$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);
if ($passwordHash === false) {
    fwrite(STDERR, "Failed to generate password hash.\n");
    exit(1);
}

try {
    $conn->beginTransaction();

    $update = $conn->prepare('
        UPDATE dbo.tblUsers
        SET PasswordHash = :passwordHash,
            FailedLoginCount = 0,
            LastFailedLoginAt = NULL,
            ForcePasswordReset = :forceReset,
            MustChangePassword = :mustChange,
            UpdatedAt = SYSUTCDATETIME()
        WHERE UserID = :userId
    ');

    $update->execute([
        ':passwordHash' => $passwordHash,
        ':forceReset' => $forceReset,
        ':mustChange' => $mustChange,
        ':userId' => (int) $user['UserID'],
    ]);

    lt_success($conn, mb_strtolower((string) $user['Username'], 'UTF-8'), '');

    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    fwrite(STDERR, "Password reset failed: " . $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "Password reset successful for user '{$user['Username']}'.\n");
fwrite(STDOUT, "ForcePasswordReset={$forceReset}; MustChangePassword={$mustChange}\n");
