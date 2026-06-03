DECLARE @AppUrl NVARCHAR(500) = (
    SELECT TOP 1 SettingValue
    FROM dbo.tblSystemSettings
    WHERE SettingKey = 'APP_URL'
);

IF @AppUrl IS NULL OR LTRIM(RTRIM(@AppUrl)) = ''
BEGIN
    SET @AppUrl = 'http://localhost/CBMSv21';
END;

SET @AppUrl = RTRIM(@AppUrl);
IF RIGHT(@AppUrl, 1) = '/'
BEGIN
    SET @AppUrl = LEFT(@AppUrl, LEN(@AppUrl) - 1);
END;

MERGE dbo.tblSystemSettings AS target
USING (
    SELECT 'CBMS_LOGIN_URL' AS SettingKey, @AppUrl + '/backend-php/public/index.php?route=auth/loginForm' AS SettingValue, 'string' AS SettingType
    UNION ALL
    SELECT 'CBMS_TOKEN_LOGIN_URL_BASE', @AppUrl + '/backend-php/public/index.php?route=auth/tokenLogin', 'string'
    UNION ALL
    SELECT 'CBMS_SECURE_LOGIN_TTL_MINUTES', '1440', 'int'
) AS source
ON target.SettingKey = source.SettingKey
WHEN MATCHED THEN
    UPDATE SET
        SettingValue = source.SettingValue,
        SettingType = source.SettingType,
        UpdatedBy = 'system',
        UpdatedAt = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, SettingType, Description, UpdatedBy, UpdatedAt)
    VALUES (source.SettingKey, source.SettingValue, source.SettingType, NULL, 'system', SYSDATETIME());
