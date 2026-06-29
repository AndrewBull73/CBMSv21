SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblTrainingPaths', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingPaths
    (
        TrainingPathID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        PathCode NVARCHAR(100) NOT NULL,
        PathTitle NVARCHAR(200) NOT NULL,
        Audience NVARCHAR(200) NULL,
        Description NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblTrainingPaths_ActiveFlag DEFAULT (1),
        SortOrder INT NOT NULL CONSTRAINT DF_tblTrainingPaths_SortOrder DEFAULT (0),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingPaths_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingPaths_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingPaths') AND name = N'UX_tblTrainingPaths_PathCode')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingPaths_PathCode ON dbo.tblTrainingPaths (PathCode);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingPathScenarios', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingPathScenarios
    (
        TrainingPathScenarioID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        PathCode NVARCHAR(100) NOT NULL,
        ScenarioCode NVARCHAR(100) NOT NULL,
        RequiredFlag BIT NOT NULL CONSTRAINT DF_tblTrainingPathScenarios_RequiredFlag DEFAULT (1),
        SortOrder INT NOT NULL CONSTRAINT DF_tblTrainingPathScenarios_SortOrder DEFAULT (0),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingPathScenarios_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingPathScenarios_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingPathScenarios') AND name = N'UX_tblTrainingPathScenarios_PathScenario')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingPathScenarios_PathScenario ON dbo.tblTrainingPathScenarios (PathCode, ScenarioCode);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingAssignments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingAssignments
    (
        TrainingAssignmentID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        PathCode NVARCHAR(100) NULL,
        ScenarioCode NVARCHAR(100) NULL,
        DueDate DATE NULL,
        Status NVARCHAR(20) NOT NULL CONSTRAINT DF_tblTrainingAssignments_Status DEFAULT (N'assigned'),
        AssignedBy INT NULL,
        AssignedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingAssignments_AssignedAt DEFAULT (SYSUTCDATETIME()),
        CompletedAt DATETIME2(0) NULL,
        Notes NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingAssignments_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingAssignments_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingAssignments') AND name = N'IX_tblTrainingAssignments_UserStatus')
BEGIN
    CREATE INDEX IX_tblTrainingAssignments_UserStatus ON dbo.tblTrainingAssignments (UserID, Status, DueDate);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingSessions', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingSessions
    (
        TrainingSessionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        SessionCode NVARCHAR(100) NOT NULL,
        SessionTitle NVARCHAR(200) NOT NULL,
        InstructorUserID INT NULL,
        PathCode NVARCHAR(100) NULL,
        ScenarioCode NVARCHAR(100) NULL,
        ScheduledAt DATETIME2(0) NULL,
        Status NVARCHAR(20) NOT NULL CONSTRAINT DF_tblTrainingSessions_Status DEFAULT (N'planned'),
        Notes NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingSessions_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingSessions_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingSessions') AND name = N'UX_tblTrainingSessions_SessionCode')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingSessions_SessionCode ON dbo.tblTrainingSessions (SessionCode);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingSessionUsers', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingSessionUsers
    (
        TrainingSessionUserID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TrainingSessionID INT NOT NULL,
        UserID INT NOT NULL,
        Status NVARCHAR(20) NOT NULL CONSTRAINT DF_tblTrainingSessionUsers_Status DEFAULT (N'assigned'),
        JoinedAt DATETIME2(0) NULL,
        CompletedAt DATETIME2(0) NULL,
        Notes NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingSessionUsers_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingSessionUsers_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingSessionUsers') AND name = N'UX_tblTrainingSessionUsers_SessionUser')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingSessionUsers_SessionUser ON dbo.tblTrainingSessionUsers (TrainingSessionID, UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingStepInstructorNotes', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingStepInstructorNotes
    (
        TrainingStepInstructorNoteID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(100) NOT NULL,
        StepNo INT NOT NULL,
        TrainerNote NVARCHAR(MAX) NULL,
        ExpectedOutcome NVARCHAR(MAX) NULL,
        CommonIssues NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingStepInstructorNotes_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingStepInstructorNotes_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingStepInstructorNotes') AND name = N'UX_tblTrainingStepInstructorNotes_CodeStep')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingStepInstructorNotes_CodeStep ON dbo.tblTrainingStepInstructorNotes (ScenarioCode, StepNo);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingStepCheckpoints', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingStepCheckpoints
    (
        TrainingStepCheckpointID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(100) NOT NULL,
        StepNo INT NOT NULL,
        QuestionText NVARCHAR(MAX) NULL,
        ExpectedAnswer NVARCHAR(MAX) NULL,
        RequiredFlag BIT NOT NULL CONSTRAINT DF_tblTrainingStepCheckpoints_RequiredFlag DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblTrainingStepCheckpoints_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingStepCheckpoints_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingStepCheckpoints_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingStepCheckpoints') AND name = N'UX_tblTrainingStepCheckpoints_CodeStep')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingStepCheckpoints_CodeStep ON dbo.tblTrainingStepCheckpoints (ScenarioCode, StepNo);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingEvidence', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingEvidence
    (
        TrainingEvidenceID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TrainingAttemptID INT NULL,
        TrainingSessionID INT NULL,
        UserID INT NOT NULL,
        ScenarioCode NVARCHAR(100) NOT NULL,
        AttemptNo INT NULL,
        EvidenceType NVARCHAR(50) NOT NULL CONSTRAINT DF_tblTrainingEvidence_EvidenceType DEFAULT (N'instructor_signoff'),
        EvidenceNote NVARCHAR(MAX) NULL,
        EvidenceBy INT NULL,
        EvidenceAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingEvidence_EvidenceAt DEFAULT (SYSUTCDATETIME()),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingEvidence_CreatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingStuckEvents', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingStuckEvents
    (
        TrainingStuckEventID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        ScenarioCode NVARCHAR(100) NOT NULL,
        StepNo INT NOT NULL,
        Route NVARCHAR(200) NULL,
        TargetElementID NVARCHAR(150) NULL,
        Message NVARCHAR(MAX) NULL,
        Status NVARCHAR(20) NOT NULL CONSTRAINT DF_tblTrainingStuckEvents_Status DEFAULT (N'open'),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingStuckEvents_CreatedAt DEFAULT (SYSUTCDATETIME()),
        ResolvedBy INT NULL,
        ResolvedAt DATETIME2(0) NULL,
        ResolutionNote NVARCHAR(MAX) NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingStuckEvents') AND name = N'IX_tblTrainingStuckEvents_Status')
BEGIN
    CREATE INDEX IX_tblTrainingStuckEvents_Status ON dbo.tblTrainingStuckEvents (Status, CreatedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingDataTags', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingDataTags
    (
        TrainingDataTagID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NULL,
        ScenarioCode NVARCHAR(100) NULL,
        TrainingSessionID INT NULL,
        TableName NVARCHAR(200) NOT NULL,
        RecordKey NVARCHAR(200) NOT NULL,
        CleanupStatus NVARCHAR(20) NOT NULL CONSTRAINT DF_tblTrainingDataTags_CleanupStatus DEFAULT (N'tagged'),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingDataTags_CreatedAt DEFAULT (SYSUTCDATETIME()),
        CleanedAt DATETIME2(0) NULL,
        CleanedBy INT NULL,
        CleanupNote NVARCHAR(MAX) NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingDataTags') AND name = N'IX_tblTrainingDataTags_Status')
BEGIN
    CREATE INDEX IX_tblTrainingDataTags_Status ON dbo.tblTrainingDataTags (CleanupStatus, CreatedAt DESC);
END;
GO
