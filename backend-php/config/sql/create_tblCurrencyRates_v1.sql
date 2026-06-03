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
    THROW 50040, 'dbo.tblCurrencies must exist before create_tblCurrencyRates_v1.sql can run.', 1;
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
