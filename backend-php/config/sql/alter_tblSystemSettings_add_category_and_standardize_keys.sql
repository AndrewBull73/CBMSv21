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

IF EXISTS (
    SELECT 1
    FROM dbo.tblSystemSettings
    WHERE SettingKey = N'GLAccountSegmentNo'
)
AND NOT EXISTS (
    SELECT 1
    FROM dbo.tblSystemSettings
    WHERE SettingKey = N'FIN_GL_ACCOUNT_SEGMENT_NO'
)
BEGIN
    UPDATE dbo.tblSystemSettings
       SET SettingKey = N'FIN_GL_ACCOUNT_SEGMENT_NO',
           Description = COALESCE(NULLIF(Description, N''), N'Segment number used to derive GLAccountCode for this client.'),
           Category = N'Financial Configuration',
           UpdatedBy = COALESCE(UpdatedBy, N'system'),
           UpdatedAt = SYSDATETIME()
     WHERE SettingKey = N'GLAccountSegmentNo';
END
GO

UPDATE dbo.tblSystemSettings
   SET Category = CASE
       WHEN SettingKey IN (N'APP_DEBUG', N'APP_DEBUG_LOG_ENABLED', N'APP_LOG_RETENTION_DAYS', N'APP_URL', N'CLIENT_NAME')
           THEN N'Application'
       WHEN SettingKey IN (
            N'LOGIN_AUTH_MODE',
            N'LOGIN_DECAY_HOUR_MIN',
            N'LOGIN_DECAY_MIN',
            N'LOGIN_LOCKOUT_MIN',
            N'LOGIN_LOCKOUT_PERMANENT',
            N'LOGIN_MAX_ATTEMPTS',
            N'LOGIN_MAX_ATTEMPTS_HOUR',
            N'CBMS_LOGIN_URL',
            N'CBMS_SECURE_LOGIN_TTL_MINUTES',
            N'CBMS_TOKEN_LOGIN_URL_BASE'
       )
           THEN N'Authentication'
       WHEN SettingKey IN (N'DEFAULT_FISCAL_YEAR', N'DEFAULT_LANGUAGE', N'DEFAULT_VERSION')
           THEN N'Base Configuration'
       WHEN SettingKey IN (N'ERROR_EMAIL_ENABLED', N'ERROR_EMAIL_FROM', N'ERROR_EMAIL_TO', N'SLOW_REQUEST_ALERTS_ENABLED', N'SLOW_REQUEST_THRESHOLD_MS')
           THEN N'Monitoring & Alerts'
       WHEN SettingKey IN (N'SMTP_ENABLED', N'SMTP_FROM', N'SMTP_HOST', N'SMTP_PASS', N'SMTP_PORT', N'SMTP_SECURE', N'SMTP_SSL', N'SMTP_USER')
           THEN N'Email'
       WHEN SettingKey IN (N'SESSION_HEARTBEAT_THROTTLE_SEC', N'SESSION_IDLE_LIMIT', N'SESSION_IDLE_TIMEOUT_SEC', N'SESSION_RETENTION_DAYS', N'SESSION_TIMEOUT_MIN')
           THEN N'Session Management'
       WHEN SettingKey IN (N'FIN_GL_ACCOUNT_SEGMENT_NO', N'GLAccountSegmentNo')
           THEN N'Financial Configuration'
       ELSE COALESCE(NULLIF(Category, N''), N'Other')
   END,
       UpdatedBy = COALESCE(UpdatedBy, N'system'),
       UpdatedAt = SYSDATETIME();
GO

UPDATE dbo.tblSystemSettings
   SET Description = N'Segment number used to derive GLAccountCode for this client.'
 WHERE SettingKey = N'FIN_GL_ACCOUNT_SEGMENT_NO'
   AND (Description IS NULL OR LTRIM(RTRIM(Description)) = N'');
GO

SELECT SettingKey, Category, SettingType, Description
FROM dbo.tblSystemSettings
ORDER BY Category, SettingKey;
GO
