SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblTrainingProgress', N'U') IS NOT NULL
BEGIN
    DECLARE @DropDefaultSql NVARCHAR(MAX) = N'';

    SELECT @DropDefaultSql = @DropDefaultSql
        + N'ALTER TABLE dbo.tblTrainingProgress DROP CONSTRAINT '
        + QUOTENAME(dc.name)
        + N';'
        + CHAR(13) + CHAR(10)
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
       AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID(N'dbo.tblTrainingProgress')
      AND c.name IN (N'StartedAt', N'LastActivityAt', N'CreatedDate', N'UpdatedDate');

    IF @DropDefaultSql <> N''
    BEGIN
        EXEC sp_executesql @DropDefaultSql;
    END;

    ALTER TABLE dbo.tblTrainingProgress
        ADD CONSTRAINT DF_tblTrainingProgress_StartedAt
            DEFAULT (SYSUTCDATETIME()) FOR StartedAt;

    ALTER TABLE dbo.tblTrainingProgress
        ADD CONSTRAINT DF_tblTrainingProgress_LastActivityAt
            DEFAULT (SYSUTCDATETIME()) FOR LastActivityAt;

    ALTER TABLE dbo.tblTrainingProgress
        ADD CONSTRAINT DF_tblTrainingProgress_CreatedDate
            DEFAULT (SYSUTCDATETIME()) FOR CreatedDate;

    ALTER TABLE dbo.tblTrainingProgress
        ADD CONSTRAINT DF_tblTrainingProgress_UpdatedDate
            DEFAULT (SYSUTCDATETIME()) FOR UpdatedDate;
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingAttempts', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingAttempts
    (
        TrainingAttemptID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NOT NULL,
        ScenarioCode NVARCHAR(100) NOT NULL,
        ScreenFamily NVARCHAR(100) NULL,
        AttemptNo INT NOT NULL,
        Status NVARCHAR(20) NOT NULL CONSTRAINT DF_tblTrainingAttempts_Status DEFAULT (N'active'),
        TotalSteps INT NOT NULL CONSTRAINT DF_tblTrainingAttempts_TotalSteps DEFAULT ((1)),
        CompletedSteps INT NOT NULL CONSTRAINT DF_tblTrainingAttempts_CompletedSteps DEFAULT ((0)),
        SamplesJson NVARCHAR(MAX) NULL,
        StartedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingAttempts_StartedAt DEFAULT (SYSUTCDATETIME()),
        CompletedAt DATETIME2(0) NULL,
        StoppedAt DATETIME2(0) NULL,
        LastActivityAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingAttempts_LastActivityAt DEFAULT (SYSUTCDATETIME()),
        DurationSeconds INT NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingAttempts_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingAttempts_UpdatedDate DEFAULT (SYSUTCDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingAttempts')
      AND name = N'UX_tblTrainingAttempts_User_Scenario_Attempt'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingAttempts_User_Scenario_Attempt
        ON dbo.tblTrainingAttempts (UserID, ScenarioCode, AttemptNo);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingAttempts')
      AND name = N'IX_tblTrainingAttempts_Status'
)
BEGIN
    CREATE INDEX IX_tblTrainingAttempts_Status
        ON dbo.tblTrainingAttempts (Status, LastActivityAt DESC);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingAttempts')
      AND name = N'IX_tblTrainingAttempts_User_Scenario'
)
BEGIN
    CREATE INDEX IX_tblTrainingAttempts_User_Scenario
        ON dbo.tblTrainingAttempts (UserID, ScenarioCode, StartedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingProgress', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblTrainingAttempts', N'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.tblTrainingAttempts
    (
        UserID,
        ScenarioCode,
        ScreenFamily,
        AttemptNo,
        Status,
        TotalSteps,
        CompletedSteps,
        SamplesJson,
        StartedAt,
        CompletedAt,
        StoppedAt,
        LastActivityAt,
        DurationSeconds,
        CreatedBy,
        CreatedDate,
        UpdatedBy,
        UpdatedDate
    )
    SELECT
        tp.UserID,
        tp.ScenarioCode,
        tp.ScreenFamily,
        tp.AttemptNo,
        LOWER(ISNULL(tp.Status, N'active')),
        tp.TotalSteps,
        CASE
            WHEN LOWER(ISNULL(tp.Status, N'')) = N'completed' THEN tp.TotalSteps
            ELSE CASE WHEN tp.CurrentStep > 1 THEN tp.CurrentStep - 1 ELSE 0 END
        END,
        tp.SamplesJson,
        tp.StartedAt,
        tp.CompletedAt,
        tp.StoppedAt,
        ISNULL(tp.LastActivityAt, tp.StartedAt),
        CASE
            WHEN tp.CompletedAt IS NOT NULL THEN DATEDIFF(SECOND, tp.StartedAt, tp.CompletedAt)
            WHEN tp.StoppedAt IS NOT NULL THEN DATEDIFF(SECOND, tp.StartedAt, tp.StoppedAt)
            ELSE NULL
        END,
        tp.CreatedBy,
        tp.CreatedDate,
        tp.UpdatedBy,
        tp.UpdatedDate
    FROM dbo.tblTrainingProgress tp
    WHERE NOT EXISTS (
        SELECT 1
        FROM dbo.tblTrainingAttempts ta
        WHERE ta.UserID = tp.UserID
          AND ta.ScenarioCode = tp.ScenarioCode
          AND ta.AttemptNo = tp.AttemptNo
    );
END;
GO
