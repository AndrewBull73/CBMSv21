SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;

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

IF OBJECT_ID('dbo.tblVersions', 'U') IS NOT NULL
   AND COL_LENGTH('dbo.tblVersions', 'BaseCurrency') IS NOT NULL
BEGIN
    UPDATE dbo.tblVersions
    SET BaseCurrency = UPPER(LTRIM(RTRIM(BaseCurrency)))
    WHERE NULLIF(LTRIM(RTRIM(ISNULL(BaseCurrency, ''))), '') IS NOT NULL
      AND BaseCurrency <> UPPER(LTRIM(RTRIM(BaseCurrency)));

    IF NOT EXISTS (
        SELECT 1
        FROM sys.foreign_keys
        WHERE name = 'FK_tblVersions_BaseCurrency'
          AND parent_object_id = OBJECT_ID('dbo.tblVersions')
    )
    BEGIN
        ALTER TABLE dbo.tblVersions
        ADD CONSTRAINT FK_tblVersions_BaseCurrency
            FOREIGN KEY (BaseCurrency)
            REFERENCES dbo.tblCurrencies (CurrencyCode);
    END;
END;
