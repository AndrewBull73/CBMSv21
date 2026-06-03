/*
    Foundation schema for CBMSv21 SSRS / formal reporting management.

    This creates:
    - report definition catalogue
    - report run history
*/

USE [CBMSv2];
GO

IF OBJECT_ID(N'dbo.tblReportDefinition', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblReportDefinition
    (
        ReportDefinitionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ReportCode NVARCHAR(80) NOT NULL,
        ReportName NVARCHAR(180) NOT NULL,
        ModuleCode NVARCHAR(80) NULL,
        ReportGroupCode NVARCHAR(80) NULL,
        ReportDescription NVARCHAR(MAX) NULL,
        SsrsPath NVARCHAR(255) NULL,
        ServerUrlOverride NVARCHAR(255) NULL,
        OutputFormatsCsv NVARCHAR(150) NULL,
        DefaultFormatCode NVARCHAR(20) NULL,
        PermissionCode NVARCHAR(100) NULL,
        ContextRequiredFlag BIT NOT NULL CONSTRAINT DF_tblReportDefinition_ContextRequiredFlag DEFAULT (0),
        FiscalYearRequiredFlag BIT NOT NULL CONSTRAINT DF_tblReportDefinition_FiscalYearRequiredFlag DEFAULT (0),
        VersionRequiredFlag BIT NOT NULL CONSTRAINT DF_tblReportDefinition_VersionRequiredFlag DEFAULT (0),
        DataScopeRequiredFlag BIT NOT NULL CONSTRAINT DF_tblReportDefinition_DataScopeRequiredFlag DEFAULT (0),
        DateFromRequiredFlag BIT NOT NULL CONSTRAINT DF_tblReportDefinition_DateFromRequiredFlag DEFAULT (0),
        DateToRequiredFlag BIT NOT NULL CONSTRAINT DF_tblReportDefinition_DateToRequiredFlag DEFAULT (0),
        ParameterConfigJson NVARCHAR(MAX) NULL,
        SortOrder INT NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblReportDefinition_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblReportDefinition_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    CREATE UNIQUE INDEX UX_tblReportDefinition_ReportCode
        ON dbo.tblReportDefinition (ReportCode);

    CREATE INDEX IX_tblReportDefinition_ActiveModule
        ON dbo.tblReportDefinition (ActiveFlag, ModuleCode, ReportGroupCode, SortOrder, ReportName);
END;
GO

IF OBJECT_ID(N'dbo.tblReportRun', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblReportRun
    (
        ReportRunID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ReportDefinitionID INT NOT NULL,
        RunStatusCode NVARCHAR(30) NOT NULL,
        ExecutionMode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblReportRun_ExecutionMode DEFAULT (N'ssrs_url'),
        RequestedByUserID INT NULL,
        OutputFormatCode NVARCHAR(20) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        DataObjectCode NVARCHAR(80) NULL,
        DateFrom DATE NULL,
        DateTo DATE NULL,
        StartedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblReportRun_StartedAt DEFAULT (SYSDATETIME()),
        CompletedAt DATETIME2(0) NULL,
        DurationSeconds INT NULL,
        LaunchUrl NVARCHAR(MAX) NULL,
        ParameterPayloadJson NVARCHAR(MAX) NULL,
        SummaryText NVARCHAR(500) NULL,
        ErrorText NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblReportRun_CreatedDate DEFAULT (SYSDATETIME())
    );

    CREATE INDEX IX_tblReportRun_DefinitionStarted
        ON dbo.tblReportRun (ReportDefinitionID, StartedAt DESC);

    CREATE INDEX IX_tblReportRun_UserStarted
        ON dbo.tblReportRun (RequestedByUserID, StartedAt DESC);

    CREATE INDEX IX_tblReportRun_StatusStarted
        ON dbo.tblReportRun (RunStatusCode, StartedAt DESC);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblReportRun_Definition'
      AND parent_object_id = OBJECT_ID(N'dbo.tblReportRun')
)
BEGIN
    ALTER TABLE dbo.tblReportRun
    ADD CONSTRAINT FK_tblReportRun_Definition
        FOREIGN KEY (ReportDefinitionID)
        REFERENCES dbo.tblReportDefinition (ReportDefinitionID);
END;
GO
