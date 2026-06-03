USE [CBMSv2];
GO

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblSystemSettings
    WHERE SettingKey = N'SCREEN_TESTS_ENABLED'
)
BEGIN
    INSERT INTO dbo.tblSystemSettings
        (SettingKey, SettingValue, SettingType, Category, Description, UpdatedBy, UpdatedAt)
    VALUES
        (N'SCREEN_TESTS_ENABLED', N'1', N'bool', N'Application', N'Controls whether screen testing menus, buttons, and testing routes are available in this environment.', N'system', SYSDATETIME());
END
ELSE
BEGIN
    UPDATE dbo.tblSystemSettings
    SET SettingValue = N'1',
        SettingType = N'bool',
        Category = N'Application',
        Description = N'Controls whether screen testing menus, buttons, and testing routes are available in this environment.',
        UpdatedBy = N'system',
        UpdatedAt = SYSDATETIME()
    WHERE SettingKey = N'SCREEN_TESTS_ENABLED';
END
GO
