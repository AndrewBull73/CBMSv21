USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbFiscalPeriodConfig', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFiscalPeriodConfig (
        FiscalPeriodConfigID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        StartMonthNo TINYINT NOT NULL CONSTRAINT DF_tblSbFiscalPeriodConfig_StartMonthNo DEFAULT ((4)),
        BP1Label NVARCHAR(50) NULL,
        BP2Label NVARCHAR(50) NULL,
        BP3Label NVARCHAR(50) NULL,
        BP4Label NVARCHAR(50) NULL,
        BP5Label NVARCHAR(50) NULL,
        BP6Label NVARCHAR(50) NULL,
        BP7Label NVARCHAR(50) NULL,
        BP8Label NVARCHAR(50) NULL,
        BP9Label NVARCHAR(50) NULL,
        BP10Label NVARCHAR(50) NULL,
        BP11Label NVARCHAR(50) NULL,
        BP12Label NVARCHAR(50) NULL,
        OuterYear1Label NVARCHAR(30) NULL,
        OuterYear2Label NVARCHAR(30) NULL,
        OuterYear3Label NVARCHAR(30) NULL,
        OuterYear4Label NVARCHAR(30) NULL,
        OuterYear5Label NVARCHAR(30) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbFiscalPeriodConfig_ActiveFlag DEFAULT ((1)),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbFiscalPeriodConfig_CreatedBy DEFAULT ((1)),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbFiscalPeriodConfig_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFiscalPeriodConfig')
      AND name = 'UX_tblSbFiscalPeriodConfig_FiscalYearID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbFiscalPeriodConfig_FiscalYearID
        ON dbo.tblSbFiscalPeriodConfig (FiscalYearID);
END
GO
