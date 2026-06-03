USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblCalcScenarioRateOverride', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcScenarioRateOverride (
        CalcScenarioRateOverrideID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        DataObjectCode NVARCHAR(20) NOT NULL,
        RateCode NVARCHAR(50) NOT NULL,
        BPRate DECIMAL(19,6) NULL,
        BP1Rate DECIMAL(19,6) NULL,
        BP2Rate DECIMAL(19,6) NULL,
        BP3Rate DECIMAL(19,6) NULL,
        BP4Rate DECIMAL(19,6) NULL,
        BP5Rate DECIMAL(19,6) NULL,
        BP6Rate DECIMAL(19,6) NULL,
        BP7Rate DECIMAL(19,6) NULL,
        BP8Rate DECIMAL(19,6) NULL,
        BP9Rate DECIMAL(19,6) NULL,
        BP10Rate DECIMAL(19,6) NULL,
        BP11Rate DECIMAL(19,6) NULL,
        BP12Rate DECIMAL(19,6) NULL,
        OY1Rate DECIMAL(19,6) NULL,
        OY2Rate DECIMAL(19,6) NULL,
        OY3Rate DECIMAL(19,6) NULL,
        OY4Rate DECIMAL(19,6) NULL,
        OY5Rate DECIMAL(19,6) NULL,
        OY6Rate DECIMAL(19,6) NULL,
        OY7Rate DECIMAL(19,6) NULL,
        OY8Rate DECIMAL(19,6) NULL,
        OY9Rate DECIMAL(19,6) NULL,
        OY10Rate DECIMAL(19,6) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcScenarioRateOverride_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcScenarioRateOverride_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcScenarioRateOverride_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcScenarioRateOverride')
)
BEGIN
    ALTER TABLE dbo.tblCalcScenarioRateOverride
        ADD CONSTRAINT FK_tblCalcScenarioRateOverride_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID) ON DELETE CASCADE;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcScenarioRateOverride')
      AND name = 'UX_tblCalcScenarioRateOverride_Key'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcScenarioRateOverride_Key
        ON dbo.tblCalcScenarioRateOverride (ScenarioID, FiscalYearID, VersionID, DataObjectCode, RateCode);
END
GO
