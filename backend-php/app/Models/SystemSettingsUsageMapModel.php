<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class SystemSettingsUsageMapModel
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    public function getDashboard(): array
    {
        $settingsModel = new SystemSettingsModel($this->conn);
        $rows = $settingsModel->listAll();

        $liveByKey = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['SettingKey'] ?? ''));
            if ($key === '') {
                continue;
            }
            $liveByKey[$key] = $row;
        }

        $definitions = $this->definitions();
        $grouped = [];
        $summary = [
            'total' => 0,
            'configured' => 0,
            'alias_only' => 0,
            'missing' => 0,
        ];

        foreach ($definitions as $definition) {
            $canonicalKey = $definition['key'];
            $aliases = $definition['aliases'];
            $allKeys = array_values(array_unique(array_merge([$canonicalKey], $aliases)));

            $liveRow = null;
            $matchedKey = null;
            foreach ($allKeys as $candidate) {
                if (isset($liveByKey[$candidate])) {
                    $liveRow = $liveByKey[$candidate];
                    $matchedKey = $candidate;
                    break;
                }
            }

            $status = 'missing';
            if ($liveRow !== null && $matchedKey === $canonicalKey) {
                $status = 'configured';
            } elseif ($liveRow !== null) {
                $status = 'alias_only';
            }

            $summary['total']++;
            $summary[$status]++;

            $value = (string) ($liveRow['SettingValue'] ?? '');
            if (!empty($definition['sensitive']) && $value !== '') {
                $value = '********';
            }

            $entry = [
                'category' => $definition['category'],
                'key' => $canonicalKey,
                'status' => $status,
                'matched_key' => $matchedKey,
                'type' => (string) ($liveRow['SettingType'] ?? ''),
                'value' => $value,
                'description' => $definition['description'],
                'aliases' => $aliases,
                'used_by' => $definition['used_by'],
            ];

            $grouped[$definition['category']][] = $entry;
        }

        ksort($grouped);

        return [
            'summary' => $summary,
            'groups' => $grouped,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'category' => 'Application',
                'key' => 'APP_URL',
                'aliases' => [],
                'description' => 'Base application URL used to build links into the platform.',
                'used_by' => [
                    'Workflow email links',
                    'System message fallback login URLs',
                ],
            ],
            [
                'category' => 'Application',
                'key' => 'APP_DEBUG',
                'aliases' => [],
                'description' => 'Controls whether detailed application errors are shown.',
                'used_by' => [
                    'Global error and diagnostics behaviour',
                ],
            ],
            [
                'category' => 'Application',
                'key' => 'APP_DEBUG_LOG_ENABLED',
                'aliases' => [],
                'description' => 'Controls verbose debug logging to the application log.',
                'used_by' => [
                    'Application logging pipeline',
                ],
            ],
            [
                'category' => 'Application',
                'key' => 'APP_LOG_RETENTION_DAYS',
                'aliases' => [],
                'description' => 'Retains application log history for the configured number of days.',
                'used_by' => [
                    'Log maintenance and archive retention',
                ],
            ],
            [
                'category' => 'Application',
                'key' => 'CLIENT_NAME',
                'aliases' => [],
                'description' => 'Client or tenant display name shown in UI headings.',
                'used_by' => [
                    'Home page heading',
                    'General application branding',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_LOGIN_MODE',
                'aliases' => ['LOGIN_AUTH_MODE'],
                'description' => 'Controls whether login uses form auth or another configured mode.',
                'used_by' => [
                    'Login form handling',
                    'Authentication controller',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_LOGIN_DECAY_MIN',
                'aliases' => ['LOGIN_DECAY_MIN'],
                'description' => 'Standard login throttle decay window in minutes.',
                'used_by' => [
                    'Login throttling rules',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_LOGIN_DECAY_HOUR_MIN',
                'aliases' => ['LOGIN_DECAY_HOUR_MIN'],
                'description' => 'Hourly login throttle decay window in minutes.',
                'used_by' => [
                    'Extended login throttling policy',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_LOGIN_LOCKOUT_MIN',
                'aliases' => ['LOGIN_LOCKOUT_MIN'],
                'description' => 'Temporary lockout period after repeated failed logins.',
                'used_by' => [
                    'Login throttling rules',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_LOGIN_LOCKOUT_PERMANENT',
                'aliases' => ['LOGIN_LOCKOUT_PERMANENT'],
                'description' => 'Whether severe login lockouts become permanent.',
                'used_by' => [
                    'Login throttling rules',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_LOGIN_MAX_ATTEMPTS',
                'aliases' => ['LOGIN_MAX_ATTEMPTS'],
                'description' => 'Maximum failed login attempts in the standard window.',
                'used_by' => [
                    'Login throttling rules',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_LOGIN_MAX_ATTEMPTS_HOUR',
                'aliases' => ['LOGIN_MAX_ATTEMPTS_HOUR'],
                'description' => 'Maximum failed login attempts allowed in the hourly window.',
                'used_by' => [
                    'Extended login throttling policy',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_LOGIN_URL',
                'aliases' => ['CBMS_LOGIN_URL'],
                'description' => 'Primary login URL used in secure and regular login messaging.',
                'used_by' => [
                    'System message templates',
                    'Secure login message links',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_SECURE_LOGIN_TTL_MINUTES',
                'aliases' => ['CBMS_SECURE_LOGIN_TTL_MINUTES'],
                'description' => 'Lifetime of secure token login links in minutes.',
                'used_by' => [
                    'System message secure login tokens',
                ],
            ],
            [
                'category' => 'Authentication',
                'key' => 'AUTH_TOKEN_LOGIN_URL_BASE',
                'aliases' => ['CBMS_TOKEN_LOGIN_URL_BASE'],
                'description' => 'Base URL used when generating secure token login links.',
                'used_by' => [
                    'System message secure login tokens',
                ],
            ],
            [
                'category' => 'Base Configuration',
                'key' => 'DEFAULT_FISCAL_YEAR',
                'aliases' => ['Default_Fiscal_Year'],
                'description' => 'Default fiscal year selected at login.',
                'used_by' => [
                    'Login context selection',
                    'Base configuration readiness',
                ],
            ],
            [
                'category' => 'Base Configuration',
                'key' => 'DEFAULT_VERSION',
                'aliases' => ['Default_Version'],
                'description' => 'Default version selected at login.',
                'used_by' => [
                    'Login context selection',
                    'Base configuration readiness',
                ],
            ],
            [
                'category' => 'Base Configuration',
                'key' => 'DEFAULT_LANGUAGE',
                'aliases' => [],
                'description' => 'Default interface language when no user selection is present.',
                'used_by' => [
                    'Bootstrap language initialization',
                ],
            ],
            [
                'category' => 'Email',
                'key' => 'SMTP_ENABLED',
                'aliases' => [],
                'description' => 'Whether outbound SMTP mail is enabled.',
                'used_by' => [
                    'Mail service configuration',
                ],
            ],
            [
                'category' => 'Email',
                'key' => 'SMTP_HOST',
                'aliases' => [],
                'description' => 'SMTP server host name.',
                'used_by' => [
                    'Mail service configuration',
                ],
            ],
            [
                'category' => 'Email',
                'key' => 'SMTP_PORT',
                'aliases' => [],
                'description' => 'SMTP server port.',
                'used_by' => [
                    'Mail service configuration',
                ],
            ],
            [
                'category' => 'Email',
                'key' => 'SMTP_USER',
                'aliases' => [],
                'description' => 'SMTP login user name.',
                'used_by' => [
                    'Mail service configuration',
                ],
            ],
            [
                'category' => 'Email',
                'key' => 'SMTP_PASS',
                'aliases' => [],
                'description' => 'SMTP password or token.',
                'used_by' => [
                    'Mail service configuration',
                ],
                'sensitive' => true,
            ],
            [
                'category' => 'Email',
                'key' => 'SMTP_FROM',
                'aliases' => [],
                'description' => 'Default sender address for outbound mail.',
                'used_by' => [
                    'Mail service default sender',
                ],
            ],
            [
                'category' => 'Email',
                'key' => 'SMTP_SECURE',
                'aliases' => [],
                'description' => 'SMTP transport security mode.',
                'used_by' => [
                    'Mail service transport mode',
                ],
            ],
            [
                'category' => 'Email',
                'key' => 'SMTP_SSL',
                'aliases' => [],
                'description' => 'Legacy boolean flag used by the mail service to enable secure SMTP.',
                'used_by' => [
                    'Mail service transport mode',
                ],
            ],
            [
                'category' => 'Financial Configuration',
                'key' => 'FIN_GL_ACCOUNT_SEGMENT_NO',
                'aliases' => ['GLAccountSegmentNo'],
                'description' => 'Segment number used to derive GL account codes during transaction input.',
                'used_by' => [
                    'Transaction input editor',
                    'Budget input GL account derivation',
                ],
            ],
            [
                'category' => 'Monitoring & Alerts',
                'key' => 'EMAIL_ERROR_ENABLED',
                'aliases' => ['ERROR_EMAIL_ENABLED'],
                'description' => 'Whether application error and diagnostics emails are enabled.',
                'used_by' => [
                    'Diagnostics controller',
                    'Operational alerting',
                ],
            ],
            [
                'category' => 'Monitoring & Alerts',
                'key' => 'EMAIL_ERROR_FROM',
                'aliases' => ['ERROR_EMAIL_FROM'],
                'description' => 'Sender address used for diagnostics and error emails.',
                'used_by' => [
                    'Diagnostics controller',
                    'Workflow notifications',
                ],
            ],
            [
                'category' => 'Monitoring & Alerts',
                'key' => 'EMAIL_ERROR_TO',
                'aliases' => ['ERROR_EMAIL_TO'],
                'description' => 'Recipient address for diagnostics and error emails.',
                'used_by' => [
                    'Diagnostics controller',
                    'Operational alerting',
                ],
            ],
            [
                'category' => 'Monitoring & Alerts',
                'key' => 'SLOW_REQUEST_ALERTS_ENABLED',
                'aliases' => [],
                'description' => 'Whether slow-request alerting is enabled.',
                'used_by' => [
                    'Diagnostics and logging thresholds',
                ],
            ],
            [
                'category' => 'Monitoring & Alerts',
                'key' => 'SLOW_REQUEST_THRESHOLD_MS',
                'aliases' => [],
                'description' => 'Request duration threshold used to classify slow requests.',
                'used_by' => [
                    'Base controller render timing',
                    'Diagnostics display',
                ],
            ],
            [
                'category' => 'Session Management',
                'key' => 'SESSION_IDLE_TIMEOUT_SEC',
                'aliases' => ['SESSION_IDLE_LIMIT'],
                'description' => 'Idle timeout in seconds before the user session expires.',
                'used_by' => [
                    'Bootstrap session timeout',
                    'Base controller session enforcement',
                ],
            ],
            [
                'category' => 'Session Management',
                'key' => 'SESSION_ABSOLUTE_TIMEOUT_MIN',
                'aliases' => ['SESSION_TIMEOUT_MIN'],
                'description' => 'Absolute session lifetime in minutes before forced logout.',
                'used_by' => [
                    'Bootstrap session timeout',
                    'Base controller session enforcement',
                ],
            ],
            [
                'category' => 'Session Management',
                'key' => 'SESSION_HEARTBEAT_THROTTLE_SEC',
                'aliases' => [],
                'description' => 'Minimum interval between session heartbeat updates.',
                'used_by' => [
                    'Base controller session heartbeat',
                ],
            ],
            [
                'category' => 'Session Management',
                'key' => 'SESSION_RETENTION_DAYS',
                'aliases' => [],
                'description' => 'Retention period for session history rows before purge.',
                'used_by' => [
                    'Automatic session maintenance purge',
                ],
            ],
        ];
    }
}
