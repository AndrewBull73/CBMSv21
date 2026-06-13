SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/*
Phase 01 / Step 03
Install the core fresh-client platform foundation.

This seeds only the minimum shared foundation needed for a new client:
- modern tblSegments / tblSegmentValues schema
- baseline data object types
- version typing
- one baseline fiscal year
- one baseline version
- safe system settings defaults

Client-owned setup data such as data object codes, segment values, workflow
assignments, rates, and calculations are intentionally left empty.
*/

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;
SET XACT_ABORT ON;

IF DB_NAME() <> N'CBMSv2_INITTEST'
BEGIN
    THROW 51010, 'This foundation install script is restricted to CBMSv2_INITTEST.', 1;
END;

DECLARE @ClientName NVARCHAR(200) = N'New Client';
DECLARE @AppUrl NVARCHAR(500) = N'http://localhost/CBMSv21';
DECLARE @DefaultFiscalYearID INT = 2026;
DECLARE @DefaultFiscalYearLabel NVARCHAR(20) = N'2026/27';
DECLARE @DefaultFiscalYearStart DATE = '2026-07-01';
DECLARE @DefaultFiscalYearEnd DATE = '2027-06-30';
DECLARE @DefaultVersionID INT = 1;
DECLARE @DefaultVersionLabel NVARCHAR(100) = N'Version 1';
DECLARE @DefaultLanguage NVARCHAR(20) = N'en';
DECLARE @TrainingFeaturesEnabled BIT = 1;
DECLARE @SessionIdleTimeoutSec INT = 1800;
DECLARE @SessionAbsoluteTimeoutMin INT = 600;
DECLARE @SessionRetentionDays INT = 7;
DECLARE @GlAccountSegmentNo INT = 1;

/*
Standalone mode:
- edit the variables above if you are running this script directly

Master-script mode:
- if #CbmsInitTestProfile exists, its values override the defaults above
*/
SET @ClientName = N'Client Name Pending';

IF OBJECT_ID('tempdb..#CbmsInitTestProfile') IS NOT NULL
BEGIN
    DECLARE @ProfileSql NVARCHAR(MAX);

    SET @ProfileSql = N'
        SELECT TOP (1)
            @ClientNameOut = ClientName,
            @AppUrlOut = AppUrl,
            @DefaultFiscalYearIDOut = DefaultFiscalYearID,
            @DefaultFiscalYearLabelOut = DefaultFiscalYearLabel,
            @DefaultFiscalYearStartOut = DefaultFiscalYearStart,
            @DefaultFiscalYearEndOut = DefaultFiscalYearEnd,
            @DefaultVersionIDOut = DefaultVersionID,
            @DefaultVersionLabelOut = DefaultVersionLabel,
            @DefaultLanguageOut = DefaultLanguage,
            @TrainingFeaturesEnabledOut = TrainingFeaturesEnabled,
            @SessionIdleTimeoutSecOut = SessionIdleTimeoutSec,
            @SessionAbsoluteTimeoutMinOut = SessionAbsoluteTimeoutMin,
            @SessionRetentionDaysOut = SessionRetentionDays,
            @GlAccountSegmentNoOut = GlAccountSegmentNo
        FROM #CbmsInitTestProfile;';

    EXEC sp_executesql
        @ProfileSql,
        N'@ClientNameOut NVARCHAR(200) OUTPUT,
          @AppUrlOut NVARCHAR(500) OUTPUT,
          @DefaultFiscalYearIDOut INT OUTPUT,
          @DefaultFiscalYearLabelOut NVARCHAR(20) OUTPUT,
          @DefaultFiscalYearStartOut DATE OUTPUT,
          @DefaultFiscalYearEndOut DATE OUTPUT,
          @DefaultVersionIDOut INT OUTPUT,
          @DefaultVersionLabelOut NVARCHAR(100) OUTPUT,
          @DefaultLanguageOut NVARCHAR(20) OUTPUT,
          @TrainingFeaturesEnabledOut BIT OUTPUT,
          @SessionIdleTimeoutSecOut INT OUTPUT,
          @SessionAbsoluteTimeoutMinOut INT OUTPUT,
          @SessionRetentionDaysOut INT OUTPUT,
          @GlAccountSegmentNoOut INT OUTPUT',
        @ClientNameOut = @ClientName OUTPUT,
        @AppUrlOut = @AppUrl OUTPUT,
        @DefaultFiscalYearIDOut = @DefaultFiscalYearID OUTPUT,
        @DefaultFiscalYearLabelOut = @DefaultFiscalYearLabel OUTPUT,
        @DefaultFiscalYearStartOut = @DefaultFiscalYearStart OUTPUT,
        @DefaultFiscalYearEndOut = @DefaultFiscalYearEnd OUTPUT,
        @DefaultVersionIDOut = @DefaultVersionID OUTPUT,
        @DefaultVersionLabelOut = @DefaultVersionLabel OUTPUT,
        @DefaultLanguageOut = @DefaultLanguage OUTPUT,
        @TrainingFeaturesEnabledOut = @TrainingFeaturesEnabled OUTPUT,
        @SessionIdleTimeoutSecOut = @SessionIdleTimeoutSec OUTPUT,
        @SessionAbsoluteTimeoutMinOut = @SessionAbsoluteTimeoutMin OUTPUT,
        @SessionRetentionDaysOut = @SessionRetentionDays OUTPUT,
        @GlAccountSegmentNoOut = @GlAccountSegmentNo OUTPUT;
END;

IF NULLIF(LTRIM(RTRIM(@DefaultVersionLabel)), N'') IS NULL
BEGIN
    SET @DefaultVersionLabel = CONCAT(CAST(@DefaultFiscalYearID AS NVARCHAR(20)), N' v ', CAST(@DefaultVersionID AS NVARCHAR(20)));
END;

IF OBJECT_ID(N'dbo.tblSegments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSegments
    (
        SegmentID INT NOT NULL PRIMARY KEY,
        SegmentCode NVARCHAR(50) NOT NULL,
        SegmentName NVARCHAR(150) NOT NULL,
        MaxLength INT NULL,
        MinLength INT NULL,
        StartPoint INT NULL,
        EndPoint INT NULL,
        Attribute1Name NVARCHAR(100) NULL,
        Attribute2Name NVARCHAR(100) NULL,
        Attribute3Name NVARCHAR(100) NULL,
        Attribute4Name NVARCHAR(100) NULL,
        [Type] NVARCHAR(50) NULL,
        DefaultBusinessArea NVARCHAR(50) NULL,
        CBMSDimension NVARCHAR(50) NULL,
        Editable NVARCHAR(10) NULL,
        Static NVARCHAR(10) NULL,
        SegmentGroup NVARCHAR(100) NULL,
        UsedInFinancialAccount BIT NOT NULL CONSTRAINT DF_tblSegments_UsedInFinancialAccount DEFAULT (0),
        UsedInStrategicPlanning BIT NOT NULL CONSTRAINT DF_tblSegments_UsedInStrategicPlanning DEFAULT (0),
        UsedInOrgStructure BIT NOT NULL CONSTRAINT DF_tblSegments_UsedInOrgStructure DEFAULT (0),
        DisplayOrder INT NULL,
        Delimiter NVARCHAR(10) NULL,
        ParentSegmentNoDefault INT NULL,
        ParentRequired BIT NOT NULL CONSTRAINT DF_tblSegments_ParentRequired DEFAULT (0),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME NULL
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblSegments')
      AND name = N'UX_tblSegments_SegmentCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSegments_SegmentCode
        ON dbo.tblSegments (SegmentCode);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblSegments')
      AND name = N'IX_tblSegments_DisplayOrder'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSegments_DisplayOrder
        ON dbo.tblSegments (DisplayOrder, SegmentID);
END;

IF OBJECT_ID(N'dbo.tblSegmentValues', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSegmentValues
    (
        SegmentValueID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        DataObjectCode NVARCHAR(50) NOT NULL,
        SegmentNo INT NOT NULL,
        SegmentCode NVARCHAR(50) NOT NULL,
        SegmentName NVARCHAR(200) NULL,
        SegmentExternalID NVARCHAR(100) NULL,
        ParentSegmentValueID INT NULL,
        SortOrder INT NOT NULL CONSTRAINT DF_tblSegmentValues_SortOrder DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSegmentValues_ActiveFlag DEFAULT (1),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME NOT NULL CONSTRAINT DF_tblSegmentValues_UpdatedDate DEFAULT (GETDATE()),
        ParentSegmentNo INT NULL,
        ParentSegmentCode NVARCHAR(50) NULL
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblSegmentValues_FiscalYear'
      AND parent_object_id = OBJECT_ID(N'dbo.tblSegmentValues')
)
BEGIN
    ALTER TABLE dbo.tblSegmentValues
    ADD CONSTRAINT FK_tblSegmentValues_FiscalYear
        FOREIGN KEY (FiscalYearID)
        REFERENCES dbo.tblFiscalYears (FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblSegmentValues_Segment'
      AND parent_object_id = OBJECT_ID(N'dbo.tblSegmentValues')
)
BEGIN
    ALTER TABLE dbo.tblSegmentValues
    ADD CONSTRAINT FK_tblSegmentValues_Segment
        FOREIGN KEY (SegmentNo)
        REFERENCES dbo.tblSegments (SegmentID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblSegmentValues_ParentValue'
      AND parent_object_id = OBJECT_ID(N'dbo.tblSegmentValues')
)
BEGIN
    ALTER TABLE dbo.tblSegmentValues
    ADD CONSTRAINT FK_tblSegmentValues_ParentValue
        FOREIGN KEY (ParentSegmentValueID)
        REFERENCES dbo.tblSegmentValues (SegmentValueID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblSegmentValues')
      AND name = N'IX_tblSegmentValues_ContextLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSegmentValues_ContextLookup
        ON dbo.tblSegmentValues (FiscalYearID, DataObjectCode, SegmentNo, ActiveFlag, SortOrder, SegmentCode);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblSegmentValues')
      AND name = N'IX_tblSegmentValues_ParentSegmentLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSegmentValues_ParentSegmentLookup
        ON dbo.tblSegmentValues (FiscalYearID, SegmentNo, ParentSegmentNo, ParentSegmentCode, ActiveFlag);
END;

IF OBJECT_ID(N'dbo.tblDataObjectTypes', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblDataObjectTypes
    (
        DataObjectTypeID INT NOT NULL PRIMARY KEY,
        DataObjectTypeName NVARCHAR(100) NOT NULL,
        SegmentNo INT NULL,
        DataContainer BIT NOT NULL CONSTRAINT DF_tblDataObjectTypes_DataContainer DEFAULT (1),
        UpdatedBy INT NULL,
        DateUpdated DATETIME NULL,
        Level TINYINT NOT NULL
    );
END;

MERGE dbo.tblDataObjectTypes AS target
USING (
    SELECT 1 AS DataObjectTypeID, N'Government' AS DataObjectTypeName, 1 AS SegmentNo, CAST(1 AS BIT) AS DataContainer, CAST(1 AS TINYINT) AS [Level]
    UNION ALL SELECT 2, N'Head', 2, CAST(1 AS BIT), CAST(2 AS TINYINT)
    UNION ALL SELECT 3, N'Cost Centre', 3, CAST(1 AS BIT), CAST(3 AS TINYINT)
    UNION ALL SELECT 4, N'Sub Cost Centre', 4, CAST(1 AS BIT), CAST(4 AS TINYINT)
    UNION ALL SELECT 5, N'Project', 5, CAST(1 AS BIT), CAST(5 AS TINYINT)
    UNION ALL SELECT 6, N'Sub Project', 6, CAST(0 AS BIT), CAST(6 AS TINYINT)
) AS source
    ON target.DataObjectTypeID = source.DataObjectTypeID
WHEN MATCHED THEN
    UPDATE SET
        DataObjectTypeName = source.DataObjectTypeName,
        SegmentNo = source.SegmentNo,
        DataContainer = source.DataContainer,
        UpdatedBy = 1,
        DateUpdated = GETDATE(),
        [Level] = source.[Level]
WHEN NOT MATCHED THEN
    INSERT (DataObjectTypeID, DataObjectTypeName, SegmentNo, DataContainer, UpdatedBy, DateUpdated, [Level])
    VALUES (source.DataObjectTypeID, source.DataObjectTypeName, source.SegmentNo, source.DataContainer, 1, GETDATE(), source.[Level]);

IF OBJECT_ID(N'dbo.tblTransactionTypeSegmentConfig', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTransactionTypeSegmentConfig
    (
        TransactionTypeSegmentConfigID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        TransactionTypeCode NVARCHAR(20) NOT NULL,
        SegmentNo INT NOT NULL,
        VisibleFlag BIT NOT NULL CONSTRAINT DF_ttsc_visible DEFAULT (1),
        RequiredFlag BIT NOT NULL CONSTRAINT DF_ttsc_required DEFAULT (0),
        LookupSourceType NVARCHAR(30) NOT NULL CONSTRAINT DF_ttsc_lookup DEFAULT (N'tblSegments'),
        LookupFilter NVARCHAR(200) NULL,
        DisplayOrder INT NOT NULL CONSTRAINT DF_ttsc_display DEFAULT (1),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_ttsc_active DEFAULT (1),
        UpdatedBy INT NOT NULL CONSTRAINT DF_ttsc_updatedby DEFAULT (1),
        UpdatedDate DATETIME NOT NULL CONSTRAINT DF_ttsc_updateddate DEFAULT (GETDATE())
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTransactionTypeSegmentConfig')
      AND name = N'UX_tblTransactionTypeSegmentConfig_Key'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblTransactionTypeSegmentConfig_Key
        ON dbo.tblTransactionTypeSegmentConfig (TransactionTypeCode, SegmentNo, FiscalYearID, VersionID);
END;

IF COL_LENGTH(N'dbo.tblSystemSettings', N'Category') IS NULL
BEGIN
    ALTER TABLE dbo.tblSystemSettings
        ADD Category NVARCHAR(100) NULL;
END;

IF OBJECT_ID(N'dbo.tblVersionTypes', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblVersionTypes
    (
        VersionTypeID INT NOT NULL PRIMARY KEY,
        VersionTypeCode NVARCHAR(30) NOT NULL,
        VersionTypeName NVARCHAR(100) NOT NULL,
        Description NVARCHAR(255) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblVersionTypes_ActiveFlag DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblVersionTypes_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblVersionTypes_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblVersionTypes')
      AND name = N'UX_tblVersionTypes_Code'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblVersionTypes_Code
        ON dbo.tblVersionTypes (VersionTypeCode);
END;

MERGE dbo.tblVersionTypes AS target
USING (
    SELECT 1 AS VersionTypeID, N'SUBMISSION' AS VersionTypeCode, N'Budget Submission' AS VersionTypeName, N'Used for strategy and submission versions.' AS Description
    UNION ALL
    SELECT 2, N'EXECUTION', N'Budget Execution', N'Used for budget execution versions and opening balances.'
) AS source
    ON target.VersionTypeID = source.VersionTypeID
WHEN MATCHED THEN
    UPDATE SET
        VersionTypeCode = source.VersionTypeCode,
        VersionTypeName = source.VersionTypeName,
        Description = source.Description,
        ActiveFlag = 1,
        UpdatedDate = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT (VersionTypeID, VersionTypeCode, VersionTypeName, Description, ActiveFlag, CreatedDate, UpdatedDate)
    VALUES (source.VersionTypeID, source.VersionTypeCode, source.VersionTypeName, source.Description, 1, SYSDATETIME(), SYSDATETIME());

IF OBJECT_ID('dbo.tblCurrencies', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCurrencies
    (
        CurrencyCode CHAR(3) NOT NULL
            CONSTRAINT PK_tblCurrencies PRIMARY KEY,
        CurrencyName NVARCHAR(100) NOT NULL,
        CurrencySymbol NVARCHAR(10) NULL,
        IsoNumericCode CHAR(3) NULL,
        DecimalPlaces TINYINT NOT NULL
            CONSTRAINT DF_tblCurrencies_DecimalPlaces DEFAULT ((2)),
        IsSystemDefault BIT NOT NULL
            CONSTRAINT DF_tblCurrencies_IsSystemDefault DEFAULT ((0)),
        IsActive BIT NOT NULL
            CONSTRAINT DF_tblCurrencies_IsActive DEFAULT ((1)),
        SortOrder INT NOT NULL
            CONSTRAINT DF_tblCurrencies_SortOrder DEFAULT ((100)),
        CreatedAt DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblCurrencies_CreatedAt DEFAULT (SYSDATETIME()),
        UpdatedAt DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblCurrencies_UpdatedAt DEFAULT (SYSDATETIME())
    );
END;

MERGE dbo.tblCurrencies AS target
USING (
    SELECT CAST(N'LSL' AS CHAR(3)) AS CurrencyCode, N'Lesotho Loti' AS CurrencyName, N'L' AS CurrencySymbol, CAST(N'426' AS CHAR(3)) AS IsoNumericCode, CAST(2 AS TINYINT) AS DecimalPlaces, CAST(1 AS BIT) AS IsSystemDefault, CAST(1 AS BIT) AS IsActive, 10 AS SortOrder
    UNION ALL
    SELECT CAST(N'USD' AS CHAR(3)), N'US Dollar', N'$', CAST(N'840' AS CHAR(3)), CAST(2 AS TINYINT), CAST(0 AS BIT), CAST(1 AS BIT), 20
    UNION ALL
    SELECT CAST(N'EUR' AS CHAR(3)), N'Euro', N'EUR', CAST(N'978' AS CHAR(3)), CAST(2 AS TINYINT), CAST(0 AS BIT), CAST(1 AS BIT), 30
    UNION ALL
    SELECT CAST(N'GBP' AS CHAR(3)), N'Pound Sterling', N'GBP', CAST(N'826' AS CHAR(3)), CAST(2 AS TINYINT), CAST(0 AS BIT), CAST(1 AS BIT), 40
    UNION ALL
    SELECT CAST(N'ZAR' AS CHAR(3)), N'South African Rand', N'R', CAST(N'710' AS CHAR(3)), CAST(2 AS TINYINT), CAST(0 AS BIT), CAST(1 AS BIT), 50
    UNION ALL
    SELECT CAST(N'AUD' AS CHAR(3)), N'Australian Dollar', N'A$', CAST(N'036' AS CHAR(3)), CAST(2 AS TINYINT), CAST(0 AS BIT), CAST(1 AS BIT), 60
) AS source
    ON target.CurrencyCode = source.CurrencyCode
WHEN MATCHED THEN
    UPDATE SET
        CurrencyName = source.CurrencyName,
        CurrencySymbol = source.CurrencySymbol,
        IsoNumericCode = source.IsoNumericCode,
        DecimalPlaces = source.DecimalPlaces,
        IsSystemDefault = source.IsSystemDefault,
        IsActive = source.IsActive,
        SortOrder = source.SortOrder,
        UpdatedAt = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT (CurrencyCode, CurrencyName, CurrencySymbol, IsoNumericCode, DecimalPlaces, IsSystemDefault, IsActive, SortOrder, CreatedAt, UpdatedAt)
    VALUES (source.CurrencyCode, source.CurrencyName, source.CurrencySymbol, source.IsoNumericCode, source.DecimalPlaces, source.IsSystemDefault, source.IsActive, source.SortOrder, SYSDATETIME(), SYSDATETIME());

IF OBJECT_ID('dbo.tblVersions', 'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.tblCurrencies (CurrencyCode, CurrencyName, DecimalPlaces, IsSystemDefault, IsActive, SortOrder, CreatedAt, UpdatedAt)
    SELECT
        src.CurrencyCode,
        src.CurrencyCode,
        2,
        0,
        1,
        900,
        SYSDATETIME(),
        SYSDATETIME()
    FROM (
        SELECT DISTINCT CAST(UPPER(LTRIM(RTRIM(BaseCurrency))) AS CHAR(3)) AS CurrencyCode
        FROM dbo.tblVersions
        WHERE NULLIF(LTRIM(RTRIM(ISNULL(BaseCurrency, ''))), '') IS NOT NULL
    ) AS src
    WHERE NOT EXISTS (
        SELECT 1
        FROM dbo.tblCurrencies c
        WHERE c.CurrencyCode = src.CurrencyCode
    );
END;

UPDATE dbo.tblCurrencies
SET IsSystemDefault = CASE WHEN CurrencyCode = 'LSL' THEN 1 ELSE 0 END,
    UpdatedAt = SYSDATETIME()
WHERE CurrencyCode IN ('LSL')
   OR IsSystemDefault = 1;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCurrencies')
      AND name = 'IX_tblCurrencies_Active_Sort'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCurrencies_Active_Sort
        ON dbo.tblCurrencies (IsActive, SortOrder, CurrencyCode);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCurrencies')
      AND name = 'UX_tblCurrencies_SystemDefault'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCurrencies_SystemDefault
        ON dbo.tblCurrencies (IsSystemDefault)
        WHERE IsSystemDefault = 1;
END;

IF OBJECT_ID('dbo.tblCurrencyRates', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCurrencyRates
    (
        CurrencyRateID INT IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_tblCurrencyRates PRIMARY KEY,
        FromCurrencyCode CHAR(3) NOT NULL,
        ToCurrencyCode CHAR(3) NOT NULL,
        RateDate DATE NOT NULL,
        RateType NVARCHAR(30) NOT NULL
            CONSTRAINT DF_tblCurrencyRates_RateType DEFAULT (N'SPOT'),
        RateValue DECIMAL(19,8) NOT NULL,
        RateSource NVARCHAR(100) NULL,
        Notes NVARCHAR(255) NULL,
        IsActive BIT NOT NULL
            CONSTRAINT DF_tblCurrencyRates_IsActive DEFAULT ((1)),
        CreatedAt DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblCurrencyRates_CreatedAt DEFAULT (SYSDATETIME()),
        UpdatedAt DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblCurrencyRates_UpdatedAt DEFAULT (SYSDATETIME())
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCurrencyRates_FromCurrency'
      AND parent_object_id = OBJECT_ID('dbo.tblCurrencyRates')
)
BEGIN
    ALTER TABLE dbo.tblCurrencyRates
    ADD CONSTRAINT FK_tblCurrencyRates_FromCurrency
        FOREIGN KEY (FromCurrencyCode)
        REFERENCES dbo.tblCurrencies (CurrencyCode);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCurrencyRates_ToCurrency'
      AND parent_object_id = OBJECT_ID('dbo.tblCurrencyRates')
)
BEGIN
    ALTER TABLE dbo.tblCurrencyRates
    ADD CONSTRAINT FK_tblCurrencyRates_ToCurrency
        FOREIGN KEY (ToCurrencyCode)
        REFERENCES dbo.tblCurrencies (CurrencyCode);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblCurrencyRates_DifferentCurrencies'
      AND parent_object_id = OBJECT_ID('dbo.tblCurrencyRates')
)
BEGIN
    ALTER TABLE dbo.tblCurrencyRates
    ADD CONSTRAINT CK_tblCurrencyRates_DifferentCurrencies
        CHECK (FromCurrencyCode <> ToCurrencyCode);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblCurrencyRates_PositiveRate'
      AND parent_object_id = OBJECT_ID('dbo.tblCurrencyRates')
)
BEGIN
    ALTER TABLE dbo.tblCurrencyRates
    ADD CONSTRAINT CK_tblCurrencyRates_PositiveRate
        CHECK (RateValue > 0);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCurrencyRates')
      AND name = 'UX_tblCurrencyRates_PairDateType'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCurrencyRates_PairDateType
        ON dbo.tblCurrencyRates (FromCurrencyCode, ToCurrencyCode, RateDate, RateType);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCurrencyRates')
      AND name = 'IX_tblCurrencyRates_ActiveDate'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCurrencyRates_ActiveDate
        ON dbo.tblCurrencyRates (IsActive, RateDate DESC, FromCurrencyCode, ToCurrencyCode);
END;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblFiscalYears
    WHERE FiscalYearID = @DefaultFiscalYearID
)
BEGIN
    INSERT INTO dbo.tblFiscalYears
        (FiscalYearID, YearLabel, StartDate, EndDate, IsActive, CreatedAt, UpdatedAt)
    VALUES
        (@DefaultFiscalYearID, @DefaultFiscalYearLabel, @DefaultFiscalYearStart, @DefaultFiscalYearEnd, 1, SYSDATETIME(), SYSDATETIME());
END;

UPDATE dbo.tblFiscalYears
SET YearLabel = @DefaultFiscalYearLabel,
    StartDate = @DefaultFiscalYearStart,
    EndDate = @DefaultFiscalYearEnd,
    IsActive = CASE WHEN FiscalYearID = @DefaultFiscalYearID THEN 1 ELSE 0 END,
    UpdatedAt = SYSDATETIME()
WHERE FiscalYearID = @DefaultFiscalYearID;

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblVersions')
      AND name = N'UX_tblVersions_DefaultPerFY'
)
BEGIN
    DROP INDEX UX_tblVersions_DefaultPerFY
        ON dbo.tblVersions;
END;

MERGE dbo.tblVersions AS target
USING (
    SELECT
        @DefaultVersionID AS VersionID,
        @DefaultFiscalYearID AS FiscalYearID,
        @DefaultVersionLabel AS VersionLabel,
        1 AS VersionTypeID,
        N'DRAFT' AS VersionStatus,
        CAST(NULL AS INT) AS BaseFiscalYearID,
        CAST(NULL AS INT) AS BaseVersionID,
        CAST(NULL AS INT) AS ActualsPeriodID,
        CAST(N'LSL' AS CHAR(3)) AS BaseCurrency,
        CAST(0 AS BIT) AS CeilingsOn,
        CAST(1 AS BIT) AS IsActive,
        CAST(1 AS BIT) AS IsDefault
) AS source
    ON target.VersionID = source.VersionID
   AND target.FiscalYearID = source.FiscalYearID
WHEN MATCHED THEN
    UPDATE SET
        VersionLabel = source.VersionLabel,
        VersionTypeID = source.VersionTypeID,
        VersionStatus = source.VersionStatus,
        BaseFiscalYearID = source.BaseFiscalYearID,
        BaseVersionID = source.BaseVersionID,
        ActualsPeriodID = source.ActualsPeriodID,
        BaseCurrency = source.BaseCurrency,
        CeilingsOn = source.CeilingsOn,
        IsActive = source.IsActive,
        IsDefault = source.IsDefault,
        UpdatedAt = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT
        (VersionID, FiscalYearID, VersionLabel, VersionTypeID, VersionStatus, BaseFiscalYearID, BaseVersionID, ActualsPeriodID, BaseCurrency, CeilingsOn, IsActive, IsDefault, CreatedAt, UpdatedAt)
    VALUES
        (source.VersionID, source.FiscalYearID, source.VersionLabel, source.VersionTypeID, source.VersionStatus, source.BaseFiscalYearID, source.BaseVersionID, source.ActualsPeriodID, source.BaseCurrency, source.CeilingsOn, source.IsActive, source.IsDefault, SYSDATETIME(), SYSDATETIME());

UPDATE dbo.tblVersions
SET VersionTypeID = 1
WHERE VersionTypeID IS NULL;

UPDATE dbo.tblVersions
SET BaseCurrency = UPPER(LTRIM(RTRIM(BaseCurrency)))
WHERE NULLIF(LTRIM(RTRIM(ISNULL(BaseCurrency, ''))), '') IS NOT NULL
  AND BaseCurrency <> UPPER(LTRIM(RTRIM(BaseCurrency)));

UPDATE dbo.tblVersions
SET VersionStatus = N'DRAFT'
WHERE NULLIF(LTRIM(RTRIM(ISNULL(VersionStatus, N''))), N'') IS NULL;

UPDATE dbo.tblVersions
SET CeilingsOn = 0
WHERE CeilingsOn IS NULL;

UPDATE dbo.tblVersions
SET IsDefault = 0
WHERE IsDefault IS NULL;

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID(N'dbo.tblVersions')
      AND name = N'VersionTypeID'
      AND is_nullable = 1
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE object_id = OBJECT_ID(N'dbo.tblVersions')
          AND name = N'UX_tblVersions_DefaultPerFYType'
    )
    BEGIN
        DROP INDEX UX_tblVersions_DefaultPerFYType
            ON dbo.tblVersions;
    END;

    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE object_id = OBJECT_ID(N'dbo.tblVersions')
          AND name = N'IX_tblVersions_FY_Type_Active'
    )
    BEGIN
        DROP INDEX IX_tblVersions_FY_Type_Active
            ON dbo.tblVersions;
    END;

    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE object_id = OBJECT_ID(N'dbo.tblVersions')
          AND name = N'IX_tblVersions_BaseVersion'
    )
    BEGIN
        DROP INDEX IX_tblVersions_BaseVersion
            ON dbo.tblVersions;
    END;

    ALTER TABLE dbo.tblVersions
    ALTER COLUMN VersionTypeID INT NOT NULL;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
       AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID(N'dbo.tblVersions')
      AND c.name = N'VersionStatus'
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT DF_tblVersions_VersionStatus
        DEFAULT (N'DRAFT') FOR VersionStatus;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
       AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID(N'dbo.tblVersions')
      AND c.name = N'CeilingsOn'
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT DF_tblVersions_CeilingsOn
        DEFAULT ((0)) FOR CeilingsOn;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
       AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID(N'dbo.tblVersions')
      AND c.name = N'IsDefault'
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT DF_tblVersions_IsDefault
        DEFAULT ((0)) FOR IsDefault;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblVersions_BaseCurrency'
      AND parent_object_id = OBJECT_ID(N'dbo.tblVersions')
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT FK_tblVersions_BaseCurrency
        FOREIGN KEY (BaseCurrency)
        REFERENCES dbo.tblCurrencies (CurrencyCode);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblVersions_VersionType'
      AND parent_object_id = OBJECT_ID(N'dbo.tblVersions')
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT FK_tblVersions_VersionType
        FOREIGN KEY (VersionTypeID)
        REFERENCES dbo.tblVersionTypes (VersionTypeID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = N'CK_tblVersions_BaseVersionPair'
      AND parent_object_id = OBJECT_ID(N'dbo.tblVersions')
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT CK_tblVersions_BaseVersionPair CHECK (
        (BaseFiscalYearID IS NULL AND BaseVersionID IS NULL)
        OR (BaseFiscalYearID IS NOT NULL AND BaseVersionID IS NOT NULL)
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblVersions_BaseVersion'
      AND parent_object_id = OBJECT_ID(N'dbo.tblVersions')
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT FK_tblVersions_BaseVersion
        FOREIGN KEY (BaseVersionID, BaseFiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblVersions')
      AND name = N'UX_tblVersions_DefaultPerFYType'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblVersions_DefaultPerFYType
        ON dbo.tblVersions (FiscalYearID, VersionTypeID)
        WHERE IsDefault = 1
          AND IsActive = 1
          AND VersionTypeID IS NOT NULL;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblVersions')
      AND name = N'IX_tblVersions_FY_Type_Active'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblVersions_FY_Type_Active
        ON dbo.tblVersions (FiscalYearID, VersionTypeID, IsActive, IsDefault, VersionID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblVersions')
      AND name = N'IX_tblVersions_BaseVersion'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblVersions_BaseVersion
        ON dbo.tblVersions (BaseFiscalYearID, BaseVersionID, VersionTypeID, IsActive, VersionID);
END;

IF OBJECT_ID('tempdb..#SystemSettingSeed') IS NOT NULL
BEGIN
    DROP TABLE #SystemSettingSeed;
END;

CREATE TABLE #SystemSettingSeed
(
    SettingKey NVARCHAR(200) NOT NULL PRIMARY KEY,
    SettingValue NVARCHAR(MAX) NULL,
    SettingType NVARCHAR(50) NOT NULL,
    Category NVARCHAR(100) NULL,
    [Description] NVARCHAR(255) NULL
);

INSERT INTO #SystemSettingSeed (SettingKey, SettingValue, SettingType, Category, [Description])
VALUES
    (N'APP_DEBUG', N'false', N'bool', N'Application', N'Enable verbose application debugging output.'),
    (N'APP_DEBUG_LOG_ENABLED', N'true', N'bool', N'Application', N'Write debug output to the application log.'),
    (N'APP_LOG_RETENTION_DAYS', N'30', N'int', N'Application', N'Number of days to keep application logs.'),
    (N'APP_URL', @AppUrl, N'string', N'Application', N'Base application URL for this environment.'),
    (N'CLIENT_NAME', @ClientName, N'string', N'Application', N'Client display name shown in headers and generated output.'),
    (N'TRAINING_FEATURES_ENABLED', CASE WHEN @TrainingFeaturesEnabled = 1 THEN N'1' ELSE N'0' END, N'bool', N'Application', N'Controls whether training routes and progress tracking are enabled.'),
    (N'AUTH_LOGIN_MODE', N'form', N'string', N'Authentication', N'Authentication mode used by the login screen.'),
    (N'AUTH_LOGIN_DECAY_MIN', N'10', N'int', N'Authentication', N'Standard login-attempt decay window in minutes.'),
    (N'AUTH_LOGIN_DECAY_HOUR_MIN', N'60', N'int', N'Authentication', N'Extended login-attempt decay window in minutes.'),
    (N'AUTH_LOGIN_LOCKOUT_MIN', N'15', N'int', N'Authentication', N'Temporary lockout duration in minutes after repeated failures.'),
    (N'AUTH_LOGIN_LOCKOUT_PERMANENT', N'false', N'bool', N'Authentication', N'Whether lockout becomes permanent after too many failed attempts.'),
    (N'AUTH_LOGIN_MAX_ATTEMPTS', N'5', N'int', N'Authentication', N'Maximum failed login attempts in the standard decay window.'),
    (N'AUTH_LOGIN_MAX_ATTEMPTS_HOUR', N'20', N'int', N'Authentication', N'Maximum failed login attempts in the hourly decay window.'),
    (N'AUTH_LOGIN_URL', @AppUrl + N'/backend-php/public/index.php?route=auth/loginForm', N'string', N'Authentication', N'Absolute login URL used by system-generated messages.'),
    (N'AUTH_TOKEN_LOGIN_URL_BASE', @AppUrl + N'/backend-php/public/index.php?route=auth/tokenLogin', N'string', N'Authentication', N'Base URL for token-login links.'),
    (N'AUTH_SECURE_LOGIN_TTL_MINUTES', N'1440', N'int', N'Authentication', N'Time-to-live in minutes for secure token-login links.'),
    (N'DEFAULT_FISCAL_YEAR', CAST(@DefaultFiscalYearID AS NVARCHAR(20)), N'int', N'Base Configuration', N'Default fiscal year selected after login.'),
    (N'DEFAULT_VERSION', CAST(@DefaultVersionID AS NVARCHAR(20)), N'int', N'Base Configuration', N'Default version selected after login.'),
    (N'DEFAULT_LANGUAGE', @DefaultLanguage, N'string', N'Base Configuration', N'Default language/culture code.'),
    (N'FIN_GL_ACCOUNT_SEGMENT_NO', CAST(@GlAccountSegmentNo AS NVARCHAR(20)), N'int', N'Financial Configuration', N'Segment number used to derive GL account codes for this client.'),
    (N'EMAIL_ERROR_ENABLED', N'false', N'bool', N'Monitoring & Alerts', N'Whether application errors send email notifications.'),
    (N'EMAIL_ERROR_FROM', N'', N'string', N'Monitoring & Alerts', N'Sender address for application error notifications.'),
    (N'EMAIL_ERROR_TO', N'', N'string', N'Monitoring & Alerts', N'Recipient address for application error notifications.'),
    (N'SLOW_REQUEST_ALERTS_ENABLED', N'false', N'bool', N'Monitoring & Alerts', N'Whether slow-request alerts are enabled.'),
    (N'SLOW_REQUEST_THRESHOLD_MS', N'10000', N'int', N'Monitoring & Alerts', N'Request duration threshold in milliseconds for slow-request alerts.'),
    (N'SMTP_ENABLED', N'false', N'bool', N'Email', N'Whether SMTP delivery is enabled.'),
    (N'SMTP_FROM', N'', N'string', N'Email', N'Default sender address for SMTP mail.'),
    (N'SMTP_HOST', N'', N'string', N'Email', N'SMTP host name.'),
    (N'SMTP_PASS', N'', N'string', N'Email', N'SMTP password or API credential.'),
    (N'SMTP_PORT', N'25', N'int', N'Email', N'SMTP port.'),
    (N'SMTP_SECURE', N'', N'string', N'Email', N'SMTP transport security mode.'),
    (N'SMTP_SSL', N'false', N'bool', N'Email', N'Whether legacy SSL mode is enabled for SMTP.'),
    (N'SMTP_USER', N'', N'string', N'Email', N'SMTP user or account name.'),
    (N'SESSION_HEARTBEAT_THROTTLE_SEC', N'30', N'int', N'Session Management', N'Minimum interval in seconds between heartbeat writes.'),
    (N'SESSION_IDLE_TIMEOUT_SEC', CAST(@SessionIdleTimeoutSec AS NVARCHAR(20)), N'int', N'Session Management', N'Idle timeout in seconds before logout.'),
    (N'SESSION_ABSOLUTE_TIMEOUT_MIN', CAST(@SessionAbsoluteTimeoutMin AS NVARCHAR(20)), N'int', N'Session Management', N'Absolute session lifetime in minutes before forced logout.'),
    (N'SESSION_RETENTION_DAYS', CAST(@SessionRetentionDays AS NVARCHAR(20)), N'int', N'Session Management', N'How long to retain session history.');

DECLARE @SystemSettingsMergeSql NVARCHAR(MAX) = N'
MERGE dbo.tblSystemSettings AS target
USING #SystemSettingSeed AS source
    ON target.SettingKey = source.SettingKey
WHEN MATCHED THEN
    UPDATE SET
        SettingValue = source.SettingValue,
        SettingType = source.SettingType,
        Category = source.Category,
        [Description] = source.[Description],
        UpdatedBy = N''system'',
        UpdatedAt = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT (SettingKey, SettingValue, SettingType, Category, [Description], UpdatedBy, UpdatedAt)
    VALUES (source.SettingKey, source.SettingValue, source.SettingType, source.Category, source.[Description], N''system'', SYSDATETIME());';

EXEC sp_executesql @SystemSettingsMergeSql;

SELECT
    N'Core platform foundation installed' AS Result,
    @DefaultFiscalYearID AS DefaultFiscalYearID,
    @DefaultVersionID AS DefaultVersionID,
    @ClientName AS ClientName,
    @AppUrl AS AppUrl;
