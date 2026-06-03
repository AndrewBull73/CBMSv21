/*
Phase 01 / Step 02A
Execute variant of the full fresh-client reset.
This file is intended for use by the master rebuild script.
*/SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/*
Phase 01 / Step 02
Full fresh-client reset for CBMSv2_INITTEST.

Default mode is preview-only. Review the counts first, then set:
    DECLARE @ExecuteReset BIT = 1;

This script preserves one admin-capable login so the rebuilt environment
remains accessible after the reset.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

IF DB_NAME() <> N'CBMSv2_INITTEST'
BEGIN
    THROW 51000, 'This reset script is restricted to CBMSv2_INITTEST.', 1;
END;

DECLARE @ExecuteReset BIT = 1;
DECLARE @DefaultAdminUsername NVARCHAR(50) = N'InitConfig';
DECLARE @DefaultAdminPasswordHash NVARCHAR(255) = N'$2y$10$2drX2NUg7wHjV8JQOJyLOOwpuHinOv5zYsAo4k63uw7wE/NNMJ6pK';

DECLARE @PreservedUserID INT;
DECLARE @PreservedUsername NVARCHAR(50);

SELECT TOP (1)
    @PreservedUserID = u.UserID,
    @PreservedUsername = u.Username
FROM dbo.tblUsers AS u
WHERE ISNULL(u.IsActive, 1) = 1
ORDER BY CASE WHEN LOWER(LTRIM(RTRIM(u.Username))) = N'admin' THEN 0 ELSE 1 END,
         u.UserID;

IF @PreservedUserID IS NULL
BEGIN
    THROW 51001, 'No user exists to preserve for post-reset login access.', 1;
END;

DECLARE @Preview TABLE
(
    StepOrder INT NOT NULL,
    ActionType NVARCHAR(30) NOT NULL,
    ObjectName NVARCHAR(256) NOT NULL,
    RowCountValue BIGINT NULL,
    Notes NVARCHAR(400) NULL
);

DECLARE @CountSql NVARCHAR(MAX);
DECLARE @Count BIGINT;

DECLARE @CountTargets TABLE
(
    StepOrder INT NOT NULL,
    ActionType NVARCHAR(30) NOT NULL,
    ObjectName NVARCHAR(256) NOT NULL,
    Notes NVARCHAR(400) NULL
);

INSERT INTO @CountTargets (StepOrder, ActionType, ObjectName, Notes)
VALUES
    (10, N'DELETE', N'dbo.tblUserSessions', N'Clears active and stale browser sessions.'),
    (20, N'DELETE', N'dbo.tblAuditLog', N'Clears prior audit history from the legacy clone.'),
    (30, N'DELETE', N'dbo.tblBudgetData', N'Clears imported budget fact data.'),
    (40, N'DELETE', N'dbo.tblTransactions', N'Clears transaction input rows.'),
    (50, N'DELETE', N'dbo.tblRates', N'Clears client-specific rates.'),
    (51, N'DELETE', N'dbo.tblCurrencyRates', N'Clears configured currency exchange rates.'),
    (55, N'DELETE', N'dbo.tblUOMs', N'Clears client-specific units of measure.'),
    (60, N'DELETE', N'dbo.tblCalculations', N'Clears client-specific calculation rules.'),
    (65, N'DELETE', N'dbo.tblGLAccountCodes', N'Clears client-specific GL account codes.'),
    (70, N'DELETE', N'dbo.tblBudgetClassConfig', N'Clears client budget class configuration.'),
    (80, N'DELETE', N'dbo.tblSystemMessageAck', N'Clears message acknowledgements.'),
    (81, N'DELETE', N'dbo.tblSystemMessageDataObject', N'Clears system-message scope links.'),
    (82, N'DELETE', N'dbo.tblSystemMessageDocTarget', N'Clears system-message document targets.'),
    (83, N'DELETE', N'dbo.tblSystemMessageEvent', N'Clears system-message event links.'),
    (84, N'DELETE', N'dbo.tblSystemMessageRole', N'Clears system-message role links.'),
    (85, N'DELETE', N'dbo.tblSystemMessageRoleTarget', N'Clears system-message role targets.'),
    (86, N'DELETE', N'dbo.tblSystemMessageUser', N'Clears system-message user links.'),
    (87, N'DELETE', N'dbo.tblSystemMessageUserTarget', N'Clears system-message user targets.'),
    (88, N'DELETE', N'dbo.tblSystemMessage', N'Clears system messages.'),
    (89, N'DELETE', N'dbo.tblLoginAttempts', N'Clears login-attempt history.'),
    (90, N'DELETE', N'dbo.tblLoginLocks', N'Clears login lock records.'),
    (100, N'DELETE', N'dbo.tblDataObjectAttributes', N'Clears client-specific data-object attributes.'),
    (100, N'DELETE', N'dbo.tblDataObjectTypes', N'Clears organisation type definitions before reseeding the fresh-start baseline.'),
    (101, N'DELETE', N'dbo.tblDataObjectCodeAccess', N'Clears scope access assignments.'),
    (102, N'DELETE', N'dbo.tblDataObjectCodeValues', N'Clears auxiliary data-object values.'),
    (103, N'DELETE', N'dbo.tblDataObjectTree', N'Clears organisation hierarchy links.'),
    (104, N'DELETE', N'dbo.tblDataObjectWorkflowStatus', N'Clears organisation workflow statuses.'),
    (105, N'DELETE', N'dbo.tblDataObjectCodes', N'Clears organisation codes for all years.'),
    (110, N'DELETE', N'dbo.tblWorkflowTasks', N'Clears legacy workflow task rows.'),
    (120, N'DELETE', N'dbo.tblUserRoles', N'Removes all role assignments before reseeding.'),
    (121, N'DELETE', N'dbo.tblRolePermissions', N'Removes all role-permission mappings before reseeding.'),
    (122, N'DELETE', N'dbo.tblRoles', N'Removes seeded roles before reseeding.'),
    (123, N'DELETE', N'dbo.tblPermissions', N'Removes seeded permissions before reseeding.'),
    (130, N'DELETE', N'dbo.tblVersions', N'Clears all version rows before reseeding.'),
    (131, N'DELETE', N'dbo.tblVersionTypes', N'Clears version types before reseeding.'),
    (132, N'DELETE', N'dbo.tblCurrencies', N'Reseeds the baseline currency master reference data.'),
    (133, N'DELETE', N'dbo.tblFiscalYears', N'Clears fiscal years before reseeding.'),
    (140, N'DELETE', N'dbo.tblSystemSettings', N'Clears system settings before reseeding.'),
    (150, N'DELETE', N'dbo.tblUsers_except_preserved', N'Removes all users except the preserved admin-capable account.'),
    (200, N'DROP', N'dbo.tblTransactionTypeSegmentConfig', N'Rebuilds financial segment-rule table from the new baseline.'),
    (201, N'DROP', N'dbo.tblSegmentValues', N'Rebuilds segment values using the current application schema.'),
    (202, N'DROP', N'dbo.tblSegments', N'Rebuilds segments using the current application schema.'),
    (210, N'DROP_GROUP', N'dbo.tblWorkflowAssignments + workflow-engine tables', N'Rebuilds workflow engine schema and definitions.'),
    (220, N'DROP_GROUP', N'dbo.tblSb*', N'Rebuilds strategic module tables from the current scripts.'),
    (230, N'DROP_GROUP', N'dbo.tblBe*', N'Rebuilds budget-execution tables from the current scripts.'),
    (240, N'DROP_GROUP', N'dbo.tblScreenTestRuns/tblScreenTestRunAttachment/tblTrainingProgress', N'Rebuilds test-support tables from the current scripts.');

DECLARE preview_cursor CURSOR LOCAL FAST_FORWARD FOR
    SELECT ActionType, ObjectName, StepOrder, Notes
    FROM @CountTargets
    ORDER BY StepOrder;

DECLARE @ActionType NVARCHAR(30);
DECLARE @ObjectName NVARCHAR(256);
DECLARE @StepOrder INT;
DECLARE @Notes NVARCHAR(400);

OPEN preview_cursor;
FETCH NEXT FROM preview_cursor INTO @ActionType, @ObjectName, @StepOrder, @Notes;

WHILE @@FETCH_STATUS = 0
BEGIN
    SET @Count = NULL;

    IF @ObjectName = N'dbo.tblUsers_except_preserved'
    BEGIN
        SELECT @Count = COUNT(*)
        FROM dbo.tblUsers
        WHERE UserID <> @PreservedUserID;
    END
    ELSE IF @ActionType = N'DROP_GROUP' AND @ObjectName = N'dbo.tblWorkflowAssignments + workflow-engine tables'
    BEGIN
        SELECT @Count = COUNT(*)
        FROM sys.tables
        WHERE schema_id = SCHEMA_ID(N'dbo')
          AND (
                name = N'tblWorkflowAssignments'
                OR name LIKE N'tblWorkflowDefinition%'
                OR name LIKE N'tblWorkflowInstance%'
              );
    END
    ELSE IF @ActionType = N'DROP_GROUP' AND @ObjectName = N'dbo.tblSb*'
    BEGIN
        SELECT @Count = COUNT(*)
        FROM sys.tables
        WHERE schema_id = SCHEMA_ID(N'dbo')
          AND name LIKE N'tblSb%';
    END
    ELSE IF @ActionType = N'DROP_GROUP' AND @ObjectName = N'dbo.tblBe*'
    BEGIN
        SELECT @Count = COUNT(*)
        FROM sys.tables
        WHERE schema_id = SCHEMA_ID(N'dbo')
          AND name LIKE N'tblBe%';
    END
    ELSE IF @ActionType = N'DROP_GROUP' AND @ObjectName = N'dbo.tblScreenTestRuns/tblScreenTestRunAttachment/tblTrainingProgress'
    BEGIN
        SELECT @Count = COUNT(*)
        FROM sys.tables
        WHERE schema_id = SCHEMA_ID(N'dbo')
          AND name IN (N'tblScreenTestRuns', N'tblScreenTestRunAttachment', N'tblTrainingProgress');
    END
    ELSE IF OBJECT_ID(@ObjectName, N'U') IS NOT NULL
    BEGIN
        SET @CountSql = N'SELECT @RowCountOut = COUNT_BIG(*) FROM ' + @ObjectName + N';';
        EXEC sp_executesql @CountSql, N'@RowCountOut BIGINT OUTPUT', @RowCountOut = @Count OUTPUT;
    END;

    INSERT INTO @Preview (StepOrder, ActionType, ObjectName, RowCountValue, Notes)
    VALUES (@StepOrder, @ActionType, @ObjectName, @Count, @Notes);

    FETCH NEXT FROM preview_cursor INTO @ActionType, @ObjectName, @StepOrder, @Notes;
END;

CLOSE preview_cursor;
DEALLOCATE preview_cursor;

SELECT
    DB_NAME() AS ActiveDatabase,
    @PreservedUserID AS PreservedUserID,
    @PreservedUsername AS PreservedUsername,
    @ExecuteReset AS ExecuteReset;

SELECT StepOrder, ActionType, ObjectName, RowCountValue, Notes
FROM @Preview
ORDER BY StepOrder;

IF @ExecuteReset = 0
BEGIN
    PRINT N'Preview only. Set @ExecuteReset = 1 and rerun to perform the full fresh-client reset.';
    RETURN;
END;

PRINT N'Starting full fresh-client reset for CBMSv2_INITTEST.';

IF OBJECT_ID(N'dbo.tblUserSessions', N'U') IS NOT NULL DELETE FROM dbo.tblUserSessions;
IF OBJECT_ID(N'dbo.tblAuditLog', N'U') IS NOT NULL DELETE FROM dbo.tblAuditLog;
IF OBJECT_ID(N'dbo.tblBudgetData', N'U') IS NOT NULL DELETE FROM dbo.tblBudgetData;
IF OBJECT_ID(N'dbo.tblTransactions', N'U') IS NOT NULL DELETE FROM dbo.tblTransactions;
IF OBJECT_ID(N'dbo.tblRates', N'U') IS NOT NULL DELETE FROM dbo.tblRates;
IF OBJECT_ID(N'dbo.tblCurrencyRates', N'U') IS NOT NULL DELETE FROM dbo.tblCurrencyRates;
IF OBJECT_ID(N'dbo.tblUOMs', N'U') IS NOT NULL DELETE FROM dbo.tblUOMs;
IF OBJECT_ID(N'dbo.tblCalculations', N'U') IS NOT NULL DELETE FROM dbo.tblCalculations;
IF OBJECT_ID(N'dbo.tblGLAccountCodes', N'U') IS NOT NULL DELETE FROM dbo.tblGLAccountCodes;
IF OBJECT_ID(N'dbo.tblBudgetClassConfig', N'U') IS NOT NULL DELETE FROM dbo.tblBudgetClassConfig;

IF OBJECT_ID(N'dbo.tblSystemMessageAck', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessageAck;
IF OBJECT_ID(N'dbo.tblSystemMessageDataObject', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessageDataObject;
IF OBJECT_ID(N'dbo.tblSystemMessageDocTarget', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessageDocTarget;
IF OBJECT_ID(N'dbo.tblSystemMessageEvent', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessageEvent;
IF OBJECT_ID(N'dbo.tblSystemMessageRole', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessageRole;
IF OBJECT_ID(N'dbo.tblSystemMessageRoleTarget', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessageRoleTarget;
IF OBJECT_ID(N'dbo.tblSystemMessageUser', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessageUser;
IF OBJECT_ID(N'dbo.tblSystemMessageUserTarget', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessageUserTarget;
IF OBJECT_ID(N'dbo.tblSystemMessage', N'U') IS NOT NULL DELETE FROM dbo.tblSystemMessage;
IF OBJECT_ID(N'dbo.tblLoginAttempts', N'U') IS NOT NULL DELETE FROM dbo.tblLoginAttempts;
IF OBJECT_ID(N'dbo.tblLoginLocks', N'U') IS NOT NULL DELETE FROM dbo.tblLoginLocks;

IF OBJECT_ID(N'dbo.tblDataObjectCodeValues', N'U') IS NOT NULL DELETE FROM dbo.tblDataObjectCodeValues;
IF OBJECT_ID(N'dbo.tblDataObjectCodeAccess', N'U') IS NOT NULL DELETE FROM dbo.tblDataObjectCodeAccess;
IF OBJECT_ID(N'dbo.tblDataObjectAttributes', N'U') IS NOT NULL DELETE FROM dbo.tblDataObjectAttributes;
IF OBJECT_ID(N'dbo.tblDataObjectTypes', N'U') IS NOT NULL DELETE FROM dbo.tblDataObjectTypes;
IF OBJECT_ID(N'dbo.tblDataObjectTree', N'U') IS NOT NULL DELETE FROM dbo.tblDataObjectTree;
IF OBJECT_ID(N'dbo.tblDataObjectWorkflowStatus', N'U') IS NOT NULL DELETE FROM dbo.tblDataObjectWorkflowStatus;
IF OBJECT_ID(N'dbo.tblDataObjectCodes', N'U') IS NOT NULL DELETE FROM dbo.tblDataObjectCodes;

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL DELETE FROM dbo.tblWorkflowTasks;

IF COL_LENGTH(N'dbo.tblUsers', N'RoleID') IS NOT NULL
BEGIN
    UPDATE dbo.tblUsers
    SET RoleID = NULL,
        UpdatedBy = CASE WHEN UserID = @PreservedUserID THEN @PreservedUserID ELSE UpdatedBy END,
        UpdatedAt = CASE WHEN COL_LENGTH(N'dbo.tblUsers', N'UpdatedAt') IS NOT NULL THEN GETDATE() ELSE UpdatedAt END
    WHERE RoleID IS NOT NULL;
END;

IF OBJECT_ID(N'dbo.tblUserRoles', N'U') IS NOT NULL DELETE FROM dbo.tblUserRoles;
IF OBJECT_ID(N'dbo.tblRolePermissions', N'U') IS NOT NULL DELETE FROM dbo.tblRolePermissions;
IF OBJECT_ID(N'dbo.tblRoles', N'U') IS NOT NULL DELETE FROM dbo.tblRoles;
IF OBJECT_ID(N'dbo.tblPermissions', N'U') IS NOT NULL DELETE FROM dbo.tblPermissions;

IF OBJECT_ID(N'dbo.tblVersions', N'U') IS NOT NULL DELETE FROM dbo.tblVersions;
IF OBJECT_ID(N'dbo.tblVersionTypes', N'U') IS NOT NULL DELETE FROM dbo.tblVersionTypes;
IF OBJECT_ID(N'dbo.tblCurrencies', N'U') IS NOT NULL DELETE FROM dbo.tblCurrencies;
IF OBJECT_ID(N'dbo.tblFiscalYears', N'U') IS NOT NULL DELETE FROM dbo.tblFiscalYears;

IF OBJECT_ID(N'dbo.tblSystemSettings', N'U') IS NOT NULL DELETE FROM dbo.tblSystemSettings;

DELETE FROM dbo.tblUsers
WHERE UserID <> @PreservedUserID;

UPDATE dbo.tblUsers
SET IsActive = 1,
    Username = @DefaultAdminUsername,
    PasswordHash = @DefaultAdminPasswordHash,
    RoleID = NULL,
    FailedLoginCount = 0,
    LastFailedLoginAt = NULL,
    ForcePasswordReset = 0,
    MustChangePassword = 0,
    PasswordChangedAt = GETDATE(),
    PasswordExpiryAt = NULL,
    LoginCount = 0,
    LastSessionID = NULL,
    UpdatedBy = @PreservedUserID,
    UpdatedAt = GETDATE()
WHERE UserID = @PreservedUserID;

DECLARE @DropTables TABLE (TableName SYSNAME NOT NULL PRIMARY KEY);

INSERT INTO @DropTables (TableName)
SELECT t.name
FROM sys.tables AS t
WHERE t.schema_id = SCHEMA_ID(N'dbo')
  AND (
        t.name IN (N'tblWorkflowAssignments', N'tblTransactionTypeSegmentConfig', N'tblSegmentValues', N'tblSegments', N'tblScreenTestRuns', N'tblScreenTestRunAttachment', N'tblTrainingProgress')
        OR t.name LIKE N'tblWorkflowDefinition%'
        OR t.name LIKE N'tblWorkflowInstance%'
        OR t.name LIKE N'tblSb%'
        OR t.name LIKE N'tblBe%'
      );

DECLARE @DropFkSql NVARCHAR(MAX) = N'';
SELECT @DropFkSql = @DropFkSql + N'
ALTER TABLE ' + QUOTENAME(SCHEMA_NAME(parent_t.schema_id)) + N'.' + QUOTENAME(parent_t.name)
    + N' DROP CONSTRAINT ' + QUOTENAME(fk.name) + N';'
FROM sys.foreign_keys AS fk
INNER JOIN sys.tables AS parent_t
    ON parent_t.object_id = fk.parent_object_id
LEFT JOIN sys.tables AS ref_t
    ON ref_t.object_id = fk.referenced_object_id
WHERE parent_t.name IN (SELECT TableName FROM @DropTables)
   OR ref_t.name IN (SELECT TableName FROM @DropTables);

IF @DropFkSql <> N''
BEGIN
    EXEC sp_executesql @DropFkSql;
END;

DECLARE @DropTableSql NVARCHAR(MAX) = N'';
SELECT @DropTableSql = @DropTableSql + N'
DROP TABLE ' + QUOTENAME(N'dbo') + N'.' + QUOTENAME(TableName) + N';'
FROM @DropTables
ORDER BY CASE
            WHEN TableName = N'tblScreenTestRunAttachment' THEN 1
            WHEN TableName = N'tblScreenTestRuns' THEN 2
            WHEN TableName = N'tblTrainingProgress' THEN 3
            ELSE 10
         END,
         TableName;

IF @DropTableSql <> N''
BEGIN
    EXEC sp_executesql @DropTableSql;
END;

IF OBJECT_ID(N'dbo.tblPermissions', N'U') IS NOT NULL DBCC CHECKIDENT ('dbo.tblPermissions', RESEED, 0) WITH NO_INFOMSGS;
IF OBJECT_ID(N'dbo.tblRoles', N'U') IS NOT NULL DBCC CHECKIDENT ('dbo.tblRoles', RESEED, 0) WITH NO_INFOMSGS;
IF OBJECT_ID(N'dbo.tblRolePermissions', N'U') IS NOT NULL DBCC CHECKIDENT ('dbo.tblRolePermissions', RESEED, 0) WITH NO_INFOMSGS;
IF OBJECT_ID(N'dbo.tblUserRoles', N'U') IS NOT NULL DBCC CHECKIDENT ('dbo.tblUserRoles', RESEED, 0) WITH NO_INFOMSGS;

SELECT
    N'Reset complete' AS Result,
    DB_NAME() AS ActiveDatabase,
    @PreservedUserID AS PreservedUserID,
    @DefaultAdminUsername AS DefaultUsername,
    N'ChangeMe123!' AS DefaultPassword;
