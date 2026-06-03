IF OBJECT_ID('dbo.tblSbFiscalAssumption', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFiscalAssumption (
        FiscalAssumptionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NULL,
        AssumptionCode NVARCHAR(50) NOT NULL,
        AssumptionName NVARCHAR(150) NOT NULL,
        AssumptionValue DECIMAL(19,6) NOT NULL,
        AssumptionNotes NVARCHAR(500) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbFiscalAssumption_ActiveFlag DEFAULT ((1)),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbFiscalAssumption_CreatedBy DEFAULT ((1)),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbFiscalAssumption_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFiscalAssumption')
      AND name = 'UX_tblSbFiscalAssumption_ContextCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbFiscalAssumption_ContextCode
        ON dbo.tblSbFiscalAssumption (FiscalYearID, VersionID, AssumptionCode);
END
GO
