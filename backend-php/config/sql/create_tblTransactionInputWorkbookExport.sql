IF OBJECT_ID('dbo.tblTransactionInputWorkbookExport', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTransactionInputWorkbookExport
    (
        WorkbookExportID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WorkbookToken VARCHAR(64) NOT NULL,
        TemplateVersion VARCHAR(20) NOT NULL,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        DataObjectCode NVARCHAR(50) NULL,
        GeneratedByUserID INT NULL,
        GeneratedByUsername NVARCHAR(255) NULL,
        GeneratedAtUTC DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblTransactionInputWorkbookExport_GeneratedAtUTC DEFAULT (SYSUTCDATETIME()),
        WorkbookStatus VARCHAR(20) NOT NULL
            CONSTRAINT DF_tblTransactionInputWorkbookExport_WorkbookStatus DEFAULT ('GENERATED'),
        UploadedAtUTC DATETIME2(0) NULL,
        UploadedByUserID INT NULL,
        UploadedByUsername NVARCHAR(255) NULL,
        LastValidationJson NVARCHAR(MAX) NULL
    );

    CREATE UNIQUE INDEX UX_tblTransactionInputWorkbookExport_WorkbookToken
        ON dbo.tblTransactionInputWorkbookExport (WorkbookToken);

    CREATE INDEX IX_tblTransactionInputWorkbookExport_Context
        ON dbo.tblTransactionInputWorkbookExport (FiscalYearID, VersionID, DataObjectCode, WorkbookStatus, GeneratedAtUTC);
END;

IF COL_LENGTH('dbo.tblTransactionInputWorkbookExport', 'UploadedByUserID') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInputWorkbookExport ADD UploadedByUserID INT NULL;
END;

IF COL_LENGTH('dbo.tblTransactionInputWorkbookExport', 'UploadedByUsername') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInputWorkbookExport ADD UploadedByUsername NVARCHAR(255) NULL;
END;

IF COL_LENGTH('dbo.tblTransactionInputWorkbookExport', 'LastValidationJson') IS NULL
BEGIN
    ALTER TABLE dbo.tblTransactionInputWorkbookExport ADD LastValidationJson NVARCHAR(MAX) NULL;
END;
