SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/*
Phase 01 / Step 09
Verify that the fresh-client rebuild foundation is in place.
*/

SET NOCOUNT ON;

IF DB_NAME() <> N'CBMSv2_INITTEST'
BEGIN
    THROW 51030, 'This verification script is restricted to CBMSv2_INITTEST.', 1;
END;

SELECT
    DB_NAME() AS ActiveDatabase,
    (
        SELECT TOP (1) Username
        FROM dbo.tblUsers
        WHERE ISNULL(IsActive, 1) = 1
        ORDER BY CASE WHEN LOWER(LTRIM(RTRIM(Username))) = N'initconfig' THEN 0 ELSE 1 END, UserID
    ) AS PreservedUsername,
    (
        SELECT COUNT(*)
        FROM dbo.tblUsers
    ) AS UserCount;

SELECT FiscalYearID, YearLabel, StartDate, EndDate, IsActive
FROM dbo.tblFiscalYears
ORDER BY FiscalYearID;

SELECT
    v.VersionID,
    v.FiscalYearID,
    v.VersionLabel,
    vt.VersionTypeCode,
    v.VersionStatus,
    v.BaseFiscalYearID,
    v.BaseVersionID,
    v.IsActive,
    v.IsDefault
FROM dbo.tblVersions AS v
LEFT JOIN dbo.tblVersionTypes AS vt
    ON vt.VersionTypeID = v.VersionTypeID
ORDER BY v.FiscalYearID, v.VersionTypeID, v.VersionID;

SELECT
    CurrencyCode,
    CurrencyName,
    DecimalPlaces,
    IsSystemDefault,
    IsActive
FROM dbo.tblCurrencies
ORDER BY SortOrder, CurrencyCode;

SELECT
    SettingKey,
    SettingValue,
    Category
FROM dbo.tblSystemSettings
WHERE SettingKey IN (
    N'CLIENT_NAME',
    N'APP_URL',
    N'DEFAULT_FISCAL_YEAR',
    N'DEFAULT_VERSION',
    N'AUTH_LOGIN_URL',
    N'SESSION_IDLE_TIMEOUT_SEC',
    N'SESSION_ABSOLUTE_TIMEOUT_MIN',
    N'TRAINING_FEATURES_ENABLED'
)
ORDER BY SettingKey;

SELECT
    (SELECT COUNT(*) FROM dbo.tblRoles WHERE Active = 1) AS ActiveRoleCount,
    (SELECT COUNT(*) FROM dbo.tblPermissions WHERE Active = 1) AS ActivePermissionCount,
    (SELECT COUNT(*) FROM dbo.tblUserRoles) AS UserRoleCount,
    CASE WHEN OBJECT_ID(N'dbo.tblDataObjectTypes', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblDataObjectTypes) END AS DataObjectTypeCount,
    CASE WHEN OBJECT_ID(N'dbo.tblVersionTypes', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblVersionTypes) END AS VersionTypeCount,
    CASE WHEN OBJECT_ID(N'dbo.tblCurrencies', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblCurrencies) END AS CurrencyCount,
    CASE WHEN OBJECT_ID(N'dbo.tblCurrencyRates', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblCurrencyRates) END AS CurrencyRateCount,
    CASE WHEN OBJECT_ID(N'dbo.tblWorkflowDefinition', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblWorkflowDefinition) END AS WorkflowDefinitionCount,
    CASE WHEN OBJECT_ID(N'dbo.tblWorkflowDefinitionStage', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblWorkflowDefinitionStage) END AS WorkflowDefinitionStageCount,
    CASE WHEN OBJECT_ID(N'dbo.tblWorkflowDefinitionAction', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblWorkflowDefinitionAction) END AS WorkflowDefinitionActionCount,
    CASE WHEN OBJECT_ID(N'dbo.tblWorkflowAssignments', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblWorkflowAssignments) END AS WorkflowAssignmentCount;

SELECT
    DataObjectTypeID,
    DataObjectTypeName,
    SegmentNo,
    DataContainer,
    [Level]
FROM dbo.tblDataObjectTypes
ORDER BY DataObjectTypeID;

SELECT
    N'tblDataObjectCodes' AS TableName,
    COUNT(*) AS ResultCount
FROM dbo.tblDataObjectCodes
UNION ALL
SELECT N'tblSegmentValues', CASE WHEN OBJECT_ID(N'dbo.tblSegmentValues', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblSegmentValues) END
UNION ALL
SELECT N'tblTransactionTypeSegmentConfig', CASE WHEN OBJECT_ID(N'dbo.tblTransactionTypeSegmentConfig', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblTransactionTypeSegmentConfig) END
UNION ALL
SELECT N'tblSbOrgUnit', CASE WHEN OBJECT_ID(N'dbo.tblSbOrgUnit', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblSbOrgUnit) END
UNION ALL
SELECT N'tblBeExecutionOpeningBalance', CASE WHEN OBJECT_ID(N'dbo.tblBeExecutionOpeningBalance', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblBeExecutionOpeningBalance) END
UNION ALL
SELECT N'tblScreenTestRuns', CASE WHEN OBJECT_ID(N'dbo.tblScreenTestRuns', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblScreenTestRuns) END
UNION ALL
SELECT N'tblTrainingProgress', CASE WHEN OBJECT_ID(N'dbo.tblTrainingProgress', N'U') IS NULL THEN NULL ELSE (SELECT COUNT(*) FROM dbo.tblTrainingProgress) END;

SELECT
    N'Verification complete' AS Result,
    N'Expected empty client-owned tables remain empty until Phase 02 configuration begins.' AS Note;
