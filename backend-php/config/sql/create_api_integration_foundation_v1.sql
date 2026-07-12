/*
    Foundation schema for CBMSv21 API / integration management.

    This creates:
    - external system registry
    - interface registry
    - run log
    - optional item-level run log
*/


GO

IF OBJECT_ID(N'dbo.tblIntegrationSystem', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationSystem
    (
        IntegrationSystemID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        SystemCode NVARCHAR(50) NOT NULL,
        SystemName NVARCHAR(150) NOT NULL,
        BaseUrl NVARCHAR(255) NULL,
        AuthType NVARCHAR(50) NULL,
        CredentialReference NVARCHAR(255) NULL,
        DefaultHeadersJson NVARCHAR(MAX) NULL,
        EnvironmentCode NVARCHAR(30) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationSystem_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationSystem_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    CREATE UNIQUE INDEX UX_tblIntegrationSystem_SystemCode
        ON dbo.tblIntegrationSystem (SystemCode);
END;
GO

IF OBJECT_ID(N'dbo.tblIntegrationInterface', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationInterface
    (
        IntegrationInterfaceID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        InterfaceCode NVARCHAR(80) NOT NULL,
        InterfaceName NVARCHAR(180) NOT NULL,
        IntegrationSystemID INT NOT NULL,
        DirectionCode NVARCHAR(20) NOT NULL,
        ModuleCode NVARCHAR(80) NULL,
        EntityCode NVARCHAR(80) NULL,
        TriggerMode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblIntegrationInterface_TriggerMode DEFAULT (N'manual'),
        ScheduleExpression NVARCHAR(120) NULL,
        EndpointPath NVARCHAR(255) NULL,
        HttpMethod NVARCHAR(20) NULL,
        PayloadFormat NVARCHAR(30) NULL,
        ContextRequiredFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationInterface_ContextRequiredFlag DEFAULT (0),
        FiscalYearRequiredFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationInterface_FyRequiredFlag DEFAULT (0),
        VersionRequiredFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationInterface_VerRequiredFlag DEFAULT (0),
        DataScopeRequiredFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationInterface_ScopeRequiredFlag DEFAULT (0),
        BatchSize INT NULL,
        TimeoutSeconds INT NULL,
        MappingConfigJson NVARCHAR(MAX) NULL,
        OutputProfilesJson NVARCHAR(MAX) NULL,
        DefaultOutputProfileCode NVARCHAR(80) NULL,
        BusinessOwner NVARCHAR(150) NULL,
        SourceOwner NVARCHAR(150) NULL,
        ApprovalStage NVARCHAR(50) NULL,
        ReadinessStatus NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationInterface_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationInterface_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    CREATE UNIQUE INDEX UX_tblIntegrationInterface_InterfaceCode
        ON dbo.tblIntegrationInterface (InterfaceCode);

    CREATE INDEX IX_tblIntegrationInterface_SystemDirection
        ON dbo.tblIntegrationInterface (IntegrationSystemID, DirectionCode, ActiveFlag);
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationInterface', N'OutputProfilesJson') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationInterface
    ADD OutputProfilesJson NVARCHAR(MAX) NULL;
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationInterface', N'DefaultOutputProfileCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationInterface
    ADD DefaultOutputProfileCode NVARCHAR(80) NULL;
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationInterface', N'BusinessOwner') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationInterface
    ADD BusinessOwner NVARCHAR(150) NULL;
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationInterface', N'SourceOwner') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationInterface
    ADD SourceOwner NVARCHAR(150) NULL;
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationInterface', N'ApprovalStage') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationInterface
    ADD ApprovalStage NVARCHAR(50) NULL;
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationInterface', N'ReadinessStatus') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationInterface
    ADD ReadinessStatus NVARCHAR(50) NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblIntegrationInterface_System'
      AND parent_object_id = OBJECT_ID(N'dbo.tblIntegrationInterface')
)
BEGIN
    ALTER TABLE dbo.tblIntegrationInterface
    ADD CONSTRAINT FK_tblIntegrationInterface_System
        FOREIGN KEY (IntegrationSystemID)
        REFERENCES dbo.tblIntegrationSystem (IntegrationSystemID);
END;
GO

IF OBJECT_ID(N'dbo.tblIntegrationRun', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationRun
    (
        IntegrationRunID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        IntegrationInterfaceID INT NOT NULL,
        RunStatusCode NVARCHAR(30) NOT NULL,
        TriggerSourceCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblIntegrationRun_TriggerSource DEFAULT (N'manual'),
        TriggeredByUserID INT NULL,
        RequestCorrelationID NVARCHAR(100) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        DataObjectCode NVARCHAR(80) NULL,
        StartedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationRun_StartedAt DEFAULT (SYSDATETIME()),
        CompletedAt DATETIME2(0) NULL,
        DurationSeconds INT NULL,
        RecordsReceived INT NULL,
        RecordsProcessed INT NULL,
        RecordsCreated INT NULL,
        RecordsUpdated INT NULL,
        RecordsSkipped INT NULL,
        RecordsFailed INT NULL,
        SummaryText NVARCHAR(500) NULL,
        ErrorText NVARCHAR(MAX) NULL,
        RequestPayloadJson NVARCHAR(MAX) NULL,
        ResponsePayloadJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationRun_CreatedDate DEFAULT (SYSDATETIME())
    );

    CREATE INDEX IX_tblIntegrationRun_InterfaceStarted
        ON dbo.tblIntegrationRun (IntegrationInterfaceID, StartedAt DESC);

    CREATE INDEX IX_tblIntegrationRun_StatusStarted
        ON dbo.tblIntegrationRun (RunStatusCode, StartedAt DESC);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblIntegrationRun_Interface'
      AND parent_object_id = OBJECT_ID(N'dbo.tblIntegrationRun')
)
BEGIN
    ALTER TABLE dbo.tblIntegrationRun
    ADD CONSTRAINT FK_tblIntegrationRun_Interface
        FOREIGN KEY (IntegrationInterfaceID)
        REFERENCES dbo.tblIntegrationInterface (IntegrationInterfaceID);
END;
GO

IF OBJECT_ID(N'dbo.tblIntegrationRunItem', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationRunItem
    (
        IntegrationRunItemID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        IntegrationRunID INT NOT NULL,
        ExternalRecordKey NVARCHAR(120) NULL,
        ItemStatusCode NVARCHAR(30) NOT NULL,
        EntityKey NVARCHAR(120) NULL,
        SummaryText NVARCHAR(255) NULL,
        ErrorText NVARCHAR(MAX) NULL,
        PayloadJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationRunItem_CreatedDate DEFAULT (SYSDATETIME())
    );

    CREATE INDEX IX_tblIntegrationRunItem_Run
        ON dbo.tblIntegrationRunItem (IntegrationRunID, IntegrationRunItemID DESC);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblIntegrationRunItem_Run'
      AND parent_object_id = OBJECT_ID(N'dbo.tblIntegrationRunItem')
)
BEGIN
    ALTER TABLE dbo.tblIntegrationRunItem
    ADD CONSTRAINT FK_tblIntegrationRunItem_Run
        FOREIGN KEY (IntegrationRunID)
        REFERENCES dbo.tblIntegrationRun (IntegrationRunID);
END;
GO
