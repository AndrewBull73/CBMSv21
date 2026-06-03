<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/request_id.php';

use App\Shared\SessionHelper;
use App\Models\SystemSettingsModel;
use App\Services\MailService;

function app_log(string $message, array $context = [], string $level = 'info'): void
{
    global $conn;
    static $settingsCache = null;

    $logDir = __DIR__ . '/../logs';
    $today = date('Y-m-d');
    $defaultPath = "$logDir/app-$today.log";
    $logFile = envStr('APP_LOG_PATH', $defaultPath);

    // Ensure settingsCache has defaults
    if ($settingsCache === null) {
        $settingsCache = [
            'retentionDays' => 30,
            'emailEnabled' => envFlag('ERROR_EMAIL_ENABLED', false),
            'emailTo' => envStr('ERROR_EMAIL_TO', ''),
            'emailFrom' => envStr('ERROR_EMAIL_FROM', 'noreply@cbmsv2.local'),
            'slowAlertsEnabled' => false,
            'slowThreshold' => 500,
            'lastFetched' => 0
        ];
    }

    // Cache settings from DB if available
    if ($conn instanceof \PDO && ($settingsCache['lastFetched'] === 0)) {
        try {
            require_once __DIR__ . '/../app/Models/SystemSettingsModel.php';
            $ss = new \App\Models\SystemSettingsModel($conn);
            $settingsCache = [
                'retentionDays' => (int)$ss->get('APP_LOG_RETENTION_DAYS', '30'),
                'emailEnabled' => filter_var($ss->get('ERROR_EMAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
                'emailTo' => $ss->get('ERROR_EMAIL_TO', envStr('ERROR_EMAIL_TO', '')),
                'emailFrom' => $ss->get('ERROR_EMAIL_FROM', envStr('ERROR_EMAIL_FROM', 'noreply@cbmsv2.local')),
                'slowAlertsEnabled' => filter_var($ss->get('SLOW_REQUEST_ALERTS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
                'slowThreshold' => (int)$ss->get('SLOW_REQUEST_THRESHOLD_MS', '500'),
                'lastFetched' => time()
            ];
        } catch (\Throwable $e) {
            // keep defaults already set above
            $settingsCache['lastFetched'] = time();
        }
    } elseif ($conn instanceof \PDO && (time() - ($settingsCache['lastFetched'] ?? 0)) > 300) {
        // Refresh cache every 5 minutes
        try {
            $ss = new \App\Models\SystemSettingsModel($conn);
            $settingsCache['retentionDays'] = (int)$ss->get('APP_LOG_RETENTION_DAYS', '30');
            $settingsCache['emailEnabled'] = filter_var($ss->get('ERROR_EMAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
            $settingsCache['emailTo'] = $ss->get('ERROR_EMAIL_TO', envStr('ERROR_EMAIL_TO', ''));
            $settingsCache['emailFrom'] = $ss->get('ERROR_EMAIL_FROM', envStr('ERROR_EMAIL_FROM', 'noreply@cbmsv2.local'));
            $settingsCache['slowAlertsEnabled'] = filter_var($ss->get('SLOW_REQUEST_ALERTS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
            $settingsCache['slowThreshold'] = (int)$ss->get('SLOW_REQUEST_THRESHOLD_MS', '500');
            $settingsCache['lastFetched'] = time();
        } catch (\Throwable $e) {
            // ignore refresh errors, keep previous cache
        }
    }

    // Ensure retentionDays is a valid integer
    $retentionDays = isset($settingsCache['retentionDays']) && is_numeric($settingsCache['retentionDays'])
        ? (int)$settingsCache['retentionDays']
        : 30;

    // Create cutoff safely using DateInterval
    try {
        $tzUtc = new \DateTimeZone('UTC');
        $nowUtc = new \DateTimeImmutable('now', $tzUtc);
        if ($retentionDays > 0) {
            $cutoff = $nowUtc->sub(new \DateInterval("P{$retentionDays}D"));
        } else {
            $cutoff = $nowUtc;
        }
    } catch (\Throwable $e) {
        // Fallback: set cutoff to epoch far past to avoid accidental deletion
        $cutoff = new \DateTimeImmutable('@0');
    }

    // Rotate / delete old logs
    foreach (glob($logDir . "/app-*.log") as $file) {
        if (preg_match('/app-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m)) {
            $fileDate = \DateTimeImmutable::createFromFormat('Y-m-d', $m[1], new \DateTimeZone('UTC'));
            if ($fileDate instanceof \DateTimeImmutable && $fileDate < $cutoff) {
                @unlink($file);
            }
        }
    }

    $debugEnabled = envFlag('APP_DEBUG', false);
    $infoEnabled = envFlag('APP_DEBUG_LOG_ENABLED', false);
    $levelLower = strtolower($level);

    if ($levelLower === 'debug' && (!$debugEnabled || !$infoEnabled)) return;
    if ($levelLower === 'info' && !$infoEnabled) return;

    $meta = [
        'FiscalYearID' => SessionHelper::get('FiscalYearID'),
        'VersionID' => SessionHelper::get('VersionID'),
        'UserID' => SessionHelper::get('auth.user_id'),
        'Username' => SessionHelper::get('auth.username'),
    ];

    // Ensure $context is an array and merge safely
    if (!is_array($context)) {
        $context = [];
    }
    $context = array_merge($meta, $context);

    $reqId = function_exists('cbms_request_id') ? cbms_request_id() : null;
    $context['RequestID'] = $reqId;

    $context += [
        'method' => $_SERVER['REQUEST_METHOD'] ?? null,
        'uri' => $_SERVER['REQUEST_URI'] ?? null,
        'route' => $_GET['route'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    $msgJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $plainLine = sprintf("[%s] [%s] %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message, $msgJson);
    @file_put_contents($logFile, $plainLine, FILE_APPEND | LOCK_EX);

    switch ($levelLower) {
        case 'debug': $ansiLevel = "\033[34mDEBUG\033[0m"; break;
        case 'error': $ansiLevel = "\033[31mERROR\033[0m"; break;
        case 'info': $ansiLevel = "\033[32mINFO\033[0m"; break;
        case 'warn': $ansiLevel = "\033[33mWARN\033[0m"; break;
        default: $ansiLevel = strtoupper($level);
    }
    error_log(sprintf("[%s] [%s] %s %s", date('Y-m-d H:i:s'), $ansiLevel, $message, $msgJson));

    static $lastEmailTime = 0;
    static $emailQueue = [];

    try {
        if ($conn instanceof \PDO) {
            $emailEnabled = $settingsCache['emailEnabled'] ?? false;
            $emailTo = $settingsCache['emailTo'] ?? '';
            $emailFrom = $settingsCache['emailFrom'] ?? envStr('ERROR_EMAIL_FROM', 'noreply@cbmsv2.local');
            $slowAlertsEnabled = $settingsCache['slowAlertsEnabled'] ?? false;
            $slowThreshold = isset($settingsCache['slowThreshold']) && is_numeric($settingsCache['slowThreshold'])
                ? (int)$settingsCache['slowThreshold'] : 500;

            $shouldQueue = false;
            $subject = '';
            $body = '';

            $ridTag = ($context['RequestID'] ?? null) ? " [RID: {$context['RequestID']}]" : '';

            if ($levelLower === 'error' && $emailEnabled) {
                $shouldQueue = true;
                $subject = "[CBMS Error]{$ridTag} {$message}";
                $body = buildLogEmailBody($message, $context, $level);
            } elseif ($levelLower === 'warn' && $slowAlertsEnabled) {
                $timeMs = (float)($context['time_ms'] ?? 0);
                if ($timeMs >= $slowThreshold) {
                    $shouldQueue = true;
                    $subject = "[CBMS Slow Request]{$ridTag} {$message} took {$timeMs} ms";
                    $body = buildLogEmailBody($message, $context, $level);
                }
            }

            if ($shouldQueue && $emailTo !== '') {
                $emailQueue[] = ['to' => $emailTo, 'subject' => $subject, 'body' => $body, 'from' => $emailFrom];
            }

            // Send emails every 60 seconds if queue is non-empty
            if ($emailQueue && (time() - $lastEmailTime) >= 60) {
                $mailer = new MailService($conn);
                foreach ($emailQueue as $email) {
                    try {
                        $mailer->sendEmail($email['to'], $email['subject'], $email['body'], $email['from']);
                        @file_put_contents($logFile, sprintf("[%s] [INFO] Email sent: %s\n", date('Y-m-d H:i:s'), $email['subject']), FILE_APPEND | LOCK_EX);
                    } catch (\Throwable $e) {
                        @file_put_contents($logFile, sprintf("[%s] [ERROR] Email failed: %s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND | LOCK_EX);
                    }
                }
                $emailQueue = [];
                $lastEmailTime = time();
            }
        }
    } catch (\Throwable $e) {
        @file_put_contents($logFile, sprintf("[%s] [ERROR] app_log email setup failed: %s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND | LOCK_EX);
    }
}

function envFlag(string $key, bool $default = false): bool
{
    $val = strtolower(trim((string)(envStr($key, $default ? '1' : '0'))));
    return in_array($val, ['1','true','yes','on'], true);
}

function buildLogEmailBody(string $message, array $context, string $level): string
{
    $time = date('Y-m-d H:i:s');
    $fy = $context['FiscalYearID'] ?? '(n/a)';
    $ver = $context['VersionID'] ?? '(n/a)';
    $uid = $context['UserID'] ?? '(n/a)';
    $user = $context['Username'] ?? '(n/a)';
    $rid = $context['RequestID'] ?? '(n/a)';
    $method = $context['method'] ?? '(n/a)';
    $uri = $context['uri'] ?? '(n/a)';
    $route = $context['route'] ?? '(n/a)';
    $ip = $context['ip'] ?? '(n/a)';

    $body = "<h3>CBMS " . htmlspecialchars($level, ENT_QUOTES, 'UTF-8') . " Alert</h3>";
    $body .= "<p><strong>Time:</strong> {$time}</p>";
    $body .= "<p><strong>User:</strong> " . htmlspecialchars((string)$user, ENT_QUOTES, 'UTF-8') . " (ID={$uid})</p>";
    $body .= "<p><strong>Fiscal Context:</strong> FY={$fy}, Version={$ver}</p>";
    $body .= "<p><strong>Request ID:</strong> " . htmlspecialchars((string)$rid, ENT_QUOTES, 'UTF-8') . "</p>";
    $body .= "<p><strong>Request:</strong> " . htmlspecialchars("$method $uri (route=$route, ip=$ip)", ENT_QUOTES, 'UTF-8') . "</p>";
    $body .= "<p><strong>Message:</strong> " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>";
    $body .= "<pre>" . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . "</pre>";
    return $body;
}

function app_log_set_conn($conn): void
{
    try {
        $st = $conn->query("
            SELECT SettingKey, SettingValue
            FROM dbo.tblSystemSettings
            WHERE SettingKey IN ('APP_DEBUG','APP_DEBUG_LOG_ENABLED')
        ");
        foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if (!empty($row['SettingKey'])) {
                putenv($row['SettingKey'].'='.$row['SettingValue']);
            }
        }
        error_log("app_log_set_conn: APP_DEBUG=" . getenv('APP_DEBUG') . " APP_DEBUG_LOG_ENABLED=" . getenv('APP_DEBUG_LOG_ENABLED'));
    } catch (\Throwable $e) {
        error_log("app_log_set_conn failed: " . $e->getMessage());
    }
}