IF OBJECT_ID('dbo.tblSbPhasingProfile', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbPhasingProfile (
        PhasingProfileID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        ProfileCode NVARCHAR(30) NOT NULL,
        ProfileName NVARCHAR(150) NOT NULL,
        ProfileDescription NVARCHAR(500) NULL,
        BP1Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP1Weight DEFAULT ((1)),
        BP2Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP2Weight DEFAULT ((1)),
        BP3Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP3Weight DEFAULT ((1)),
        BP4Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP4Weight DEFAULT ((1)),
        BP5Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP5Weight DEFAULT ((1)),
        BP6Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP6Weight DEFAULT ((1)),
        BP7Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP7Weight DEFAULT ((1)),
        BP8Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP8Weight DEFAULT ((1)),
        BP9Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP9Weight DEFAULT ((1)),
        BP10Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP10Weight DEFAULT ((1)),
        BP11Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP11Weight DEFAULT ((1)),
        BP12Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP12Weight DEFAULT ((1)),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbPhasingProfile_ActiveFlag DEFAULT ((1)),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbPhasingProfile_CreatedBy DEFAULT ((1)),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbPhasingProfile')
      AND name = 'UX_tblSbPhasingProfile_FiscalYear_ProfileCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbPhasingProfile_FiscalYear_ProfileCode
        ON dbo.tblSbPhasingProfile (FiscalYearID, ProfileCode);
END;
GO
