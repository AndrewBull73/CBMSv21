SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

/*
Phase 01 / Optional Step 08A
Install the current CBMSv21 testing-support tables.
Training/system settings are seeded by Step 03.
*/

/* Source: backend-php\config\sql\create_tblScreenTestRuns.sql */
/*
    Create storage for the CBMS screen test runner.

    This table stores one completed test-run result per attempt.
    If this table is not installed, the screen test runner still works
    in session-only mode for the current browser session.
*/

GO

IF OBJECT_ID(N'dbo.tblScreenTestRuns', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblScreenTestRuns
    (
        ScreenTestRunID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        ScenarioCode NVARCHAR(120) NOT NULL,
        ScenarioTitle NVARCHAR(200) NOT NULL,
        ModuleName NVARCHAR(120) NULL,
        ScreenFamily NVARCHAR(80) NULL,
        TargetRoute NVARCHAR(200) NULL,
        RunResult NVARCHAR(20) NOT NULL CONSTRAINT DF_tblScreenTestRuns_RunResult DEFAULT (N'blocked'),
        VerificationStatus NVARCHAR(20) NOT NULL CONSTRAINT DF_tblScreenTestRuns_VerificationStatus DEFAULT (N'not_run'),
        AttemptNo INT NOT NULL CONSTRAINT DF_tblScreenTestRuns_AttemptNo DEFAULT (1),
        FiscalYearID INT NULL,
        VersionID INT NULL,
        DataObjectCode NVARCHAR(80) NULL,
        ContextJson NVARCHAR(MAX) NULL,
        TestDataJson NVARCHAR(MAX) NULL,
        OutcomeSummary NVARCHAR(500) NULL,
        DefectReference NVARCHAR(120) NULL,
        TesterNotes NVARCHAR(MAX) NULL,
        StartedAt DATETIME2(0) NOT NULL,
        CompletedAt DATETIME2(0) NOT NULL,
        DurationSeconds INT NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblScreenTestRuns_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblScreenTestRuns_UpdatedDate DEFAULT (SYSDATETIME())
    );

    CREATE INDEX IX_tblScreenTestRuns_UserScenario
        ON dbo.tblScreenTestRuns (UserID, ScenarioCode, AttemptNo DESC);

    CREATE INDEX IX_tblScreenTestRuns_ModuleCompleted
        ON dbo.tblScreenTestRuns (ModuleName, CompletedAt DESC);

    CREATE INDEX IX_tblScreenTestRuns_Result
        ON dbo.tblScreenTestRuns (RunResult, VerificationStatus, CompletedAt DESC);
END;
GO


/* Source: backend-php\config\sql\create_tblScreenTestRunAttachment.sql */
/*
    Create storage for screenshot evidence captured from the screen test runner.

    This table stores files linked to a saved screen test run.
*/

GO

IF OBJECT_ID(N'dbo.tblScreenTestRunAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblScreenTestRunAttachment
    (
        ScreenTestRunAttachmentID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScreenTestRunID INT NOT NULL,
        OriginalFileName NVARCHAR(255) NOT NULL,
        StoredFileName NVARCHAR(255) NOT NULL,
        MimeType NVARCHAR(120) NULL,
        FileSizeBytes BIGINT NOT NULL CONSTRAINT DF_tblScreenTestRunAttachment_FileSizeBytes DEFAULT ((0)),
        StoragePath NVARCHAR(500) NOT NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblScreenTestRunAttachment_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblScreenTestRunAttachment_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    ALTER TABLE dbo.tblScreenTestRunAttachment
    ADD CONSTRAINT FK_tblScreenTestRunAttachment_Run
        FOREIGN KEY (ScreenTestRunID)
        REFERENCES dbo.tblScreenTestRuns (ScreenTestRunID);

    CREATE NONCLUSTERED INDEX IX_tblScreenTestRunAttachment_Run
        ON dbo.tblScreenTestRunAttachment (ScreenTestRunID, ActiveFlag, ScreenTestRunAttachmentID DESC);
END;
GO


/* Source: backend-php\config\sql\create_tblTrainingProgress.sql */
GO

IF OBJECT_ID(N'dbo.tblTrainingProgress', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingProgress
    (
        TrainingProgressID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        ScenarioCode NVARCHAR(100) NOT NULL,
        ScreenFamily NVARCHAR(100) NULL,
        Status NVARCHAR(20) NOT NULL CONSTRAINT DF_tblTrainingProgress_Status DEFAULT (N'active'),
        CurrentStep INT NOT NULL CONSTRAINT DF_tblTrainingProgress_CurrentStep DEFAULT ((1)),
        TotalSteps INT NOT NULL CONSTRAINT DF_tblTrainingProgress_TotalSteps DEFAULT ((1)),
        AttemptNo INT NOT NULL CONSTRAINT DF_tblTrainingProgress_AttemptNo DEFAULT ((1)),
        SamplesJson NVARCHAR(MAX) NULL,
        StartedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingProgress_StartedAt DEFAULT (SYSDATETIME()),
        CompletedAt DATETIME2(0) NULL,
        StoppedAt DATETIME2(0) NULL,
        LastActivityAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingProgress_LastActivityAt DEFAULT (SYSDATETIME()),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingProgress_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingProgress_UpdatedDate DEFAULT (SYSDATETIME())
    );

    CREATE UNIQUE INDEX UX_tblTrainingProgress_User_Scenario
        ON dbo.tblTrainingProgress (UserID, ScenarioCode);

    CREATE INDEX IX_tblTrainingProgress_Status
        ON dbo.tblTrainingProgress (Status, LastActivityAt DESC);
END
GO
