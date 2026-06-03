<?php
declare(strict_types=1);

namespace App\Models;

final class SystemSettingsModel
{
    private \PDO $pdo;
    private string $lastError = '';
    private ?array $columnCache = null;

    private const KEY_ALIASES = [
        'AUTH_LOGIN_MODE' => ['AUTH_LOGIN_MODE', 'LOGIN_AUTH_MODE'],
        'LOGIN_AUTH_MODE' => ['LOGIN_AUTH_MODE', 'AUTH_LOGIN_MODE'],
        'AUTH_LOGIN_DECAY_MIN' => ['AUTH_LOGIN_DECAY_MIN', 'LOGIN_DECAY_MIN'],
        'LOGIN_DECAY_MIN' => ['LOGIN_DECAY_MIN', 'AUTH_LOGIN_DECAY_MIN'],
        'AUTH_LOGIN_DECAY_HOUR_MIN' => ['AUTH_LOGIN_DECAY_HOUR_MIN', 'LOGIN_DECAY_HOUR_MIN'],
        'LOGIN_DECAY_HOUR_MIN' => ['LOGIN_DECAY_HOUR_MIN', 'AUTH_LOGIN_DECAY_HOUR_MIN'],
        'AUTH_LOGIN_LOCKOUT_MIN' => ['AUTH_LOGIN_LOCKOUT_MIN', 'LOGIN_LOCKOUT_MIN'],
        'LOGIN_LOCKOUT_MIN' => ['LOGIN_LOCKOUT_MIN', 'AUTH_LOGIN_LOCKOUT_MIN'],
        'AUTH_LOGIN_LOCKOUT_PERMANENT' => ['AUTH_LOGIN_LOCKOUT_PERMANENT', 'LOGIN_LOCKOUT_PERMANENT'],
        'LOGIN_LOCKOUT_PERMANENT' => ['LOGIN_LOCKOUT_PERMANENT', 'AUTH_LOGIN_LOCKOUT_PERMANENT'],
        'AUTH_LOGIN_MAX_ATTEMPTS' => ['AUTH_LOGIN_MAX_ATTEMPTS', 'LOGIN_MAX_ATTEMPTS'],
        'LOGIN_MAX_ATTEMPTS' => ['LOGIN_MAX_ATTEMPTS', 'AUTH_LOGIN_MAX_ATTEMPTS'],
        'AUTH_LOGIN_MAX_ATTEMPTS_HOUR' => ['AUTH_LOGIN_MAX_ATTEMPTS_HOUR', 'LOGIN_MAX_ATTEMPTS_HOUR'],
        'LOGIN_MAX_ATTEMPTS_HOUR' => ['LOGIN_MAX_ATTEMPTS_HOUR', 'AUTH_LOGIN_MAX_ATTEMPTS_HOUR'],
        'AUTH_LOGIN_URL' => ['AUTH_LOGIN_URL', 'CBMS_LOGIN_URL'],
        'CBMS_LOGIN_URL' => ['CBMS_LOGIN_URL', 'AUTH_LOGIN_URL'],
        'AUTH_SECURE_LOGIN_TTL_MINUTES' => ['AUTH_SECURE_LOGIN_TTL_MINUTES', 'CBMS_SECURE_LOGIN_TTL_MINUTES'],
        'CBMS_SECURE_LOGIN_TTL_MINUTES' => ['CBMS_SECURE_LOGIN_TTL_MINUTES', 'AUTH_SECURE_LOGIN_TTL_MINUTES'],
        'AUTH_TOKEN_LOGIN_URL_BASE' => ['AUTH_TOKEN_LOGIN_URL_BASE', 'CBMS_TOKEN_LOGIN_URL_BASE'],
        'CBMS_TOKEN_LOGIN_URL_BASE' => ['CBMS_TOKEN_LOGIN_URL_BASE', 'AUTH_TOKEN_LOGIN_URL_BASE'],
        'SESSION_IDLE_TIMEOUT_SEC' => ['SESSION_IDLE_TIMEOUT_SEC', 'SESSION_IDLE_LIMIT'],
        'SESSION_IDLE_LIMIT' => ['SESSION_IDLE_LIMIT', 'SESSION_IDLE_TIMEOUT_SEC'],
        'SESSION_ABSOLUTE_TIMEOUT_MIN' => ['SESSION_ABSOLUTE_TIMEOUT_MIN', 'SESSION_TIMEOUT_MIN'],
        'SESSION_TIMEOUT_MIN' => ['SESSION_TIMEOUT_MIN', 'SESSION_ABSOLUTE_TIMEOUT_MIN'],
        'EMAIL_ERROR_ENABLED' => ['EMAIL_ERROR_ENABLED', 'ERROR_EMAIL_ENABLED'],
        'ERROR_EMAIL_ENABLED' => ['ERROR_EMAIL_ENABLED', 'EMAIL_ERROR_ENABLED'],
        'EMAIL_ERROR_FROM' => ['EMAIL_ERROR_FROM', 'ERROR_EMAIL_FROM'],
        'ERROR_EMAIL_FROM' => ['ERROR_EMAIL_FROM', 'EMAIL_ERROR_FROM'],
        'EMAIL_ERROR_TO' => ['EMAIL_ERROR_TO', 'ERROR_EMAIL_TO'],
        'ERROR_EMAIL_TO' => ['ERROR_EMAIL_TO', 'EMAIL_ERROR_TO'],
        'FIN_GL_ACCOUNT_SEGMENT_NO' => ['FIN_GL_ACCOUNT_SEGMENT_NO', 'GLAccountSegmentNo'],
        'GLAccountSegmentNo' => ['GLAccountSegmentNo', 'FIN_GL_ACCOUNT_SEGMENT_NO'],
    ];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        try {
            $keys = $this->resolveSettingKeys($key);
            $placeholders = [];
            $params = [];
            foreach ($keys as $index => $settingKey) {
                $placeholder = ':k' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $settingKey;
            }

            $order = [];
            foreach ($keys as $index => $settingKey) {
                $placeholder = ':o' . $index;
                $order[] = 'WHEN SettingKey = ' . $placeholder . ' THEN ' . $index;
                $params[$placeholder] = $settingKey;
            }

            $sql = '
                SELECT TOP 1 SettingValue
                FROM dbo.tblSystemSettings
                WHERE SettingKey IN (' . implode(', ', $placeholders) . ')
                ORDER BY CASE ' . implode(' ', $order) . ' ELSE 999 END
            ';

            $st = $this->pdo->prepare($sql);
            $st->execute($params);
            $val = $st->fetchColumn();
            return $val !== false ? (string) $val : $default;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return $default;
        }
    }

    public function listAll(): array
    {
        try {
            $columns = $this->getAvailableColumns();
            $select = ['SettingKey', 'SettingValue', 'SettingType'];
            $select[] = in_array('Description', $columns, true) ? 'Description' : 'CAST(NULL AS NVARCHAR(255)) AS Description';
            $select[] = in_array('UpdatedBy', $columns, true) ? 'UpdatedBy' : 'CAST(NULL AS NVARCHAR(255)) AS UpdatedBy';
            $select[] = in_array('UpdatedAt', $columns, true) ? 'UpdatedAt' : 'CAST(NULL AS DATETIME2) AS UpdatedAt';
            $select[] = in_array('Category', $columns, true) ? 'Category' : 'CAST(NULL AS NVARCHAR(100)) AS Category';

            $st = $this->pdo->query('SELECT ' . implode(', ', $select) . ' FROM dbo.tblSystemSettings ORDER BY SettingKey ASC');
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function set(
        string $key,
        string $val,
        string $type,
        string $updatedBy,
        ?string $description = null,
        ?string $category = null
    ): bool {
        try {
            $columns = $this->getAvailableColumns();
            $setParts = [
                'SettingValue = :v',
                'SettingType = :t',
            ];

            if (in_array('Description', $columns, true)) {
                $setParts[] = 'Description = :d';
            }
            if (in_array('Category', $columns, true)) {
                $setParts[] = 'Category = :c';
            }
            if (in_array('UpdatedBy', $columns, true)) {
                $setParts[] = 'UpdatedBy = :u';
            }
            if (in_array('UpdatedAt', $columns, true)) {
                $setParts[] = 'UpdatedAt = SYSDATETIME()';
            }

            $upd = $this->pdo->prepare('
                UPDATE dbo.tblSystemSettings
                   SET ' . implode(', ', $setParts) . '
                 WHERE SettingKey = :k
            ');

            $params = [
                ':v' => $val,
                ':t' => $type,
                ':k' => $key,
            ];
            if (in_array('Description', $columns, true)) {
                $params[':d'] = $description;
            }
            if (in_array('Category', $columns, true)) {
                $params[':c'] = $category;
            }
            if (in_array('UpdatedBy', $columns, true)) {
                $params[':u'] = $updatedBy;
            }

            $upd->execute($params);
            if ($upd->rowCount() > 0 || $this->settingExists($key)) {
                return true;
            }

            $insertColumns = ['SettingKey', 'SettingValue', 'SettingType'];
            $insertValues = [':k', ':v', ':t'];
            $insertParams = [
                ':k' => $key,
                ':v' => $val,
                ':t' => $type,
            ];

            if (in_array('Description', $columns, true)) {
                $insertColumns[] = 'Description';
                $insertValues[] = ':d';
                $insertParams[':d'] = $description;
            }
            if (in_array('Category', $columns, true)) {
                $insertColumns[] = 'Category';
                $insertValues[] = ':c';
                $insertParams[':c'] = $category;
            }
            if (in_array('UpdatedBy', $columns, true)) {
                $insertColumns[] = 'UpdatedBy';
                $insertValues[] = ':u';
                $insertParams[':u'] = $updatedBy;
            }
            if (in_array('UpdatedAt', $columns, true)) {
                $insertColumns[] = 'UpdatedAt';
                $insertValues[] = 'SYSDATETIME()';
            }

            $ins = $this->pdo->prepare('
                INSERT INTO dbo.tblSystemSettings
                    (' . implode(', ', $insertColumns) . ')
                VALUES
                    (' . implode(', ', $insertValues) . ')
            ');
            $ins->execute($insertParams);
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    private function resolveSettingKeys(string $key): array
    {
        $keys = self::KEY_ALIASES[$key] ?? [$key];
        return array_values(array_unique(array_filter(array_map('trim', $keys), static fn(string $value): bool => $value !== '')));
    }

    private function getAvailableColumns(): array
    {
        if ($this->columnCache !== null) {
            return $this->columnCache;
        }

        try {
            $st = $this->pdo->query("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = 'dbo'
                  AND TABLE_NAME = 'tblSystemSettings'
                ORDER BY ORDINAL_POSITION
            ");
            $this->columnCache = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {
            $this->columnCache = ['SettingKey', 'SettingValue', 'SettingType', 'Description', 'UpdatedBy', 'UpdatedAt'];
        }

        return $this->columnCache;
    }

    private function settingExists(string $key): bool
    {
        $st = $this->pdo->prepare('SELECT COUNT(*) FROM dbo.tblSystemSettings WHERE SettingKey = :k');
        $st->execute([':k' => $key]);
        return (int) $st->fetchColumn() > 0;
    }
}
