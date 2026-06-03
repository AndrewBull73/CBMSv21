USE [CBMSv2];
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
