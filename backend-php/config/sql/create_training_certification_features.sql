SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblTrainingCertifications', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingCertifications
    (
        TrainingCertificationID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CertificationCode NVARCHAR(100) NOT NULL,
        CertificationTitle NVARCHAR(200) NOT NULL,
        ModuleName NVARCHAR(150) NOT NULL,
        Description NVARCHAR(MAX) NULL,
        PassPercent DECIMAL(5,2) NOT NULL CONSTRAINT DF_tblTrainingCertifications_PassPercent DEFAULT (80),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblTrainingCertifications_ActiveFlag DEFAULT (1),
        SortOrder INT NOT NULL CONSTRAINT DF_tblTrainingCertifications_SortOrder DEFAULT (0),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingCertifications_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingCertifications_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingCertifications') AND name = N'UX_tblTrainingCertifications_Code')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingCertifications_Code ON dbo.tblTrainingCertifications (CertificationCode);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingCertificationQuestions', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingCertificationQuestions
    (
        TrainingCertificationQuestionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CertificationCode NVARCHAR(100) NOT NULL,
        QuestionNo INT NOT NULL,
        QuestionText NVARCHAR(MAX) NOT NULL,
        OptionsJson NVARCHAR(MAX) NOT NULL,
        CorrectOptionKey NVARCHAR(20) NOT NULL,
        Explanation NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblTrainingCertificationQuestions_ActiveFlag DEFAULT (1),
        SortOrder INT NOT NULL CONSTRAINT DF_tblTrainingCertificationQuestions_SortOrder DEFAULT (0),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingCertificationQuestions_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingCertificationQuestions_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingCertificationQuestions') AND name = N'UX_tblTrainingCertificationQuestions_CodeNo')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingCertificationQuestions_CodeNo ON dbo.tblTrainingCertificationQuestions (CertificationCode, QuestionNo);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingCertificationAttempts', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingCertificationAttempts
    (
        TrainingCertificationAttemptID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        CertificationCode NVARCHAR(100) NOT NULL,
        AttemptNo INT NOT NULL,
        Status NVARCHAR(20) NOT NULL CONSTRAINT DF_tblTrainingCertificationAttempts_Status DEFAULT (N'in_progress'),
        QuestionCount INT NOT NULL CONSTRAINT DF_tblTrainingCertificationAttempts_QuestionCount DEFAULT (0),
        CorrectCount INT NOT NULL CONSTRAINT DF_tblTrainingCertificationAttempts_CorrectCount DEFAULT (0),
        ScorePercent DECIMAL(5,2) NULL,
        PassPercent DECIMAL(5,2) NOT NULL CONSTRAINT DF_tblTrainingCertificationAttempts_PassPercent DEFAULT (80),
        PassedFlag BIT NOT NULL CONSTRAINT DF_tblTrainingCertificationAttempts_PassedFlag DEFAULT (0),
        StartedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingCertificationAttempts_StartedAt DEFAULT (SYSUTCDATETIME()),
        SubmittedAt DATETIME2(0) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingCertificationAttempts_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingCertificationAttempts_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingCertificationAttempts') AND name = N'IX_tblTrainingCertificationAttempts_UserCode')
BEGIN
    CREATE INDEX IX_tblTrainingCertificationAttempts_UserCode ON dbo.tblTrainingCertificationAttempts (UserID, CertificationCode, AttemptNo DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingCertificationAnswers', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingCertificationAnswers
    (
        TrainingCertificationAnswerID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TrainingCertificationAttemptID INT NOT NULL,
        TrainingCertificationQuestionID INT NOT NULL,
        QuestionNo INT NOT NULL,
        SelectedOptionKey NVARCHAR(20) NULL,
        CorrectOptionKey NVARCHAR(20) NOT NULL,
        CorrectFlag BIT NOT NULL CONSTRAINT DF_tblTrainingCertificationAnswers_CorrectFlag DEFAULT (0),
        AnsweredAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingCertificationAnswers_AnsweredAt DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblTrainingCertificationAnswers') AND name = N'UX_tblTrainingCertificationAnswers_AttemptQuestion')
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingCertificationAnswers_AttemptQuestion ON dbo.tblTrainingCertificationAnswers (TrainingCertificationAttemptID, TrainingCertificationQuestionID);
END;
GO
