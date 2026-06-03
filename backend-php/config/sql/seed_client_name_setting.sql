USE [CBMSv2];
GO

MERGE dbo.tblSystemSettings AS target
USING (
    SELECT
        CAST('CLIENT_NAME' AS NVARCHAR(100)) AS SettingKey,
        CAST(' ff Government of Lesotho' AS NVARCHAR(MAX)) AS SettingValue,
        CAST('string' AS NVARCHAR(50)) AS SettingType,
        CAST('Client / tenant display name used in UI headings.' AS NVARCHAR(255)) AS Description
) AS source
ON target.SettingKey = source.SettingKey
WHEN MATCHED THEN
    UPDATE SET
        SettingValue = source.SettingValue,
        SettingType = source.SettingType,
        Description = source.Description,
        UpdatedAt = SYSDATETIME(),
        UpdatedBy = COALESCE(target.UpdatedBy, 'system')
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, SettingType, Description, UpdatedBy, UpdatedAt)
    VALUES (source.SettingKey, source.SettingValue, source.SettingType, source.Description, 'system', SYSDATETIME());
GO
