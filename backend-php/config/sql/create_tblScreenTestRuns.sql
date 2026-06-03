/*
    Create storage for the CBMS screen test runner.

    This table stores one completed test-run result per attempt.
    If this table is not installed, the screen test runner still works
    in session-only mode for the current browser session.
*/

USE [CBMSv2];
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
