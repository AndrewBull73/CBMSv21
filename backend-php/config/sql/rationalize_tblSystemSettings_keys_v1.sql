USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

IF COL_LENGTH('dbo.tblSystemSettings', 'Category') IS NULL
BEGIN
    ALTER TABLE dbo.tblSystemSettings
        ADD Category NVARCHAR(100) NULL;
END
GO

DECLARE @Map TABLE
(
    OldKey NVARCHAR(200) NOT NULL,
    NewKey NVARCHAR(200) NOT NULL,
    Category NVARCHAR(100) NULL,
    Description NVARCHAR(255) NULL
);

INSERT INTO @Map (OldKey, NewKey, Category, Description)
VALUES
    (N'GLAccountSegmentNo', N'FIN_GL_ACCOUNT_SEGMENT_NO', N'Financial Configuration', N'Segment number used to derive GLAccountCode for this client.'),
    (N'LOGIN_AUTH_MODE', N'AUTH_LOGIN_MODE', N'Authentication', N'Authentication mode used by the login screen.'),
    (N'LOGIN_DECAY_MIN', N'AUTH_LOGIN_DECAY_MIN', N'Authentication', N'Decay window in minutes for login attempt throttling.'),
    (N'LOGIN_DECAY_HOUR_MIN', N'AUTH_LOGIN_DECAY_HOUR_MIN', N'Authentication', N'Extended decay window in minutes for hourly login throttling.'),
    (N'LOGIN_LOCKOUT_MIN', N'AUTH_LOGIN_LOCKOUT_MIN', N'Authentication', N'Temporary lockout duration in minutes after too many failed logins.'),
    (N'LOGIN_LOCKOUT_PERMANENT', N'AUTH_LOGIN_LOCKOUT_PERMANENT', N'Authentication', N'Whether login lockout becomes permanent after the threshold is reached.'),
    (N'LOGIN_MAX_ATTEMPTS', N'AUTH_LOGIN_MAX_ATTEMPTS', N'Authentication', N'Maximum failed login attempts allowed in the standard decay window.'),
    (N'LOGIN_MAX_ATTEMPTS_HOUR', N'AUTH_LOGIN_MAX_ATTEMPTS_HOUR', N'Authentication', N'Maximum failed login attempts allowed in the hourly decay window.'),
    (N'CBMS_LOGIN_URL', N'AUTH_LOGIN_URL', N'Authentication', N'Primary login URL used in system messages and email links.'),
    (N'CBMS_SECURE_LOGIN_TTL_MINUTES', N'AUTH_SECURE_LOGIN_TTL_MINUTES', N'Authentication', N'Time to live in minutes for secure token-based login links.'),
    (N'CBMS_TOKEN_LOGIN_URL_BASE', N'AUTH_TOKEN_LOGIN_URL_BASE', N'Authentication', N'Base URL used when generating secure token login links.'),
    (N'SESSION_IDLE_LIMIT', N'SESSION_IDLE_TIMEOUT_SEC', N'Session Management', N'Idle timeout in seconds before the user session expires.'),
    (N'SESSION_TIMEOUT_MIN', N'SESSION_ABSOLUTE_TIMEOUT_MIN', N'Session Management', N'Absolute session lifetime in minutes before forced logout.'),
    (N'ERROR_EMAIL_ENABLED', N'EMAIL_ERROR_ENABLED', N'Monitoring & Alerts', N'Whether system error notifications should be sent by email.'),
    (N'ERROR_EMAIL_FROM', N'EMAIL_ERROR_FROM', N'Monitoring & Alerts', N'Sender address used for error and diagnostics email notifications.'),
    (N'ERROR_EMAIL_TO', N'EMAIL_ERROR_TO', N'Monitoring & Alerts', N'Recipient address used for error and diagnostics email notifications.');

DECLARE
    @OldKey NVARCHAR(200),
    @NewKey NVARCHAR(200),
    @Category NVARCHAR(100),
    @Description NVARCHAR(255);

DECLARE cur CURSOR LOCAL FAST_FORWARD FOR
    SELECT OldKey, NewKey, Category, Description
    FROM @Map
    ORDER BY OldKey;

OPEN cur;
FETCH NEXT FROM cur INTO @OldKey, @NewKey, @Category, @Description;

WHILE @@FETCH_STATUS = 0
BEGIN
    IF EXISTS (SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = @OldKey)
    BEGIN
        IF EXISTS (SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = @NewKey)
        BEGIN
            UPDATE target
               SET target.SettingValue = CASE
                        WHEN target.SettingValue IS NULL OR LTRIM(RTRIM(target.SettingValue)) = N''
                            THEN source.SettingValue
                        ELSE target.SettingValue
                    END,
                   target.SettingType = CASE
                        WHEN target.SettingType IS NULL OR LTRIM(RTRIM(target.SettingType)) = N''
                            THEN source.SettingType
                        ELSE target.SettingType
                    END,
                   target.Description = COALESCE(NULLIF(target.Description, N''), @Description, source.Description),
                   target.Category = COALESCE(NULLIF(target.Category, N''), @Category),
                   target.UpdatedBy = COALESCE(target.UpdatedBy, source.UpdatedBy, N'system'),
                   target.UpdatedAt = SYSDATETIME()
            FROM dbo.tblSystemSettings target
            INNER JOIN dbo.tblSystemSettings source
                ON source.SettingKey = @OldKey
            WHERE target.SettingKey = @NewKey;

            DELETE FROM dbo.tblSystemSettings
            WHERE SettingKey = @OldKey;
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSystemSettings
               SET SettingKey = @NewKey,
                   Description = COALESCE(NULLIF(Description, N''), @Description),
                   Category = COALESCE(NULLIF(Category, N''), @Category),
                   UpdatedBy = COALESCE(UpdatedBy, N'system'),
                   UpdatedAt = SYSDATETIME()
             WHERE SettingKey = @OldKey;
        END
    END

    FETCH NEXT FROM cur INTO @OldKey, @NewKey, @Category, @Description;
END

CLOSE cur;
DEALLOCATE cur;
GO

UPDATE dbo.tblSystemSettings
   SET Category = CASE
       WHEN SettingKey IN (N'APP_DEBUG', N'APP_DEBUG_LOG_ENABLED', N'APP_LOG_RETENTION_DAYS', N'APP_URL', N'CLIENT_NAME')
           THEN N'Application'
       WHEN SettingKey IN (
            N'AUTH_LOGIN_MODE',
            N'AUTH_LOGIN_DECAY_MIN',
            N'AUTH_LOGIN_DECAY_HOUR_MIN',
            N'AUTH_LOGIN_LOCKOUT_MIN',
            N'AUTH_LOGIN_LOCKOUT_PERMANENT',
            N'AUTH_LOGIN_MAX_ATTEMPTS',
            N'AUTH_LOGIN_MAX_ATTEMPTS_HOUR',
            N'AUTH_LOGIN_URL',
            N'AUTH_SECURE_LOGIN_TTL_MINUTES',
            N'AUTH_TOKEN_LOGIN_URL_BASE'
       )
           THEN N'Authentication'
       WHEN SettingKey IN (N'DEFAULT_FISCAL_YEAR', N'DEFAULT_LANGUAGE', N'DEFAULT_VERSION')
           THEN N'Base Configuration'
       WHEN SettingKey IN (N'EMAIL_ERROR_ENABLED', N'EMAIL_ERROR_FROM', N'EMAIL_ERROR_TO', N'SLOW_REQUEST_ALERTS_ENABLED', N'SLOW_REQUEST_THRESHOLD_MS')
           THEN N'Monitoring & Alerts'
       WHEN SettingKey IN (N'SMTP_ENABLED', N'SMTP_FROM', N'SMTP_HOST', N'SMTP_PASS', N'SMTP_PORT', N'SMTP_SECURE', N'SMTP_SSL', N'SMTP_USER')
           THEN N'Email'
       WHEN SettingKey IN (N'SESSION_HEARTBEAT_THROTTLE_SEC', N'SESSION_IDLE_TIMEOUT_SEC', N'SESSION_IDLE_LIMIT', N'SESSION_RETENTION_DAYS', N'SESSION_ABSOLUTE_TIMEOUT_MIN', N'SESSION_TIMEOUT_MIN')
           THEN N'Session Management'
       WHEN SettingKey IN (N'FIN_GL_ACCOUNT_SEGMENT_NO')
           THEN N'Financial Configuration'
       ELSE COALESCE(NULLIF(Category, N''), N'Other')
   END,
       UpdatedBy = COALESCE(UpdatedBy, N'system'),
       UpdatedAt = SYSDATETIME();
GO

SELECT SettingKey, Category, SettingType, Description
FROM dbo.tblSystemSettings
ORDER BY Category, SettingKey;
GO
