USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbObjectiveCleanupStage', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbObjectiveCleanupStage (
        ObjectiveCleanupStageID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NULL,
        VoteCode NVARCHAR(20) NULL,
        StrategicObjectiveCode NVARCHAR(50) NULL,
        StrategicObjectiveText NVARCHAR(MAX) NULL,
        LegacyGoalID NVARCHAR(20) NULL,
        LegacyStrategicPillarID NVARCHAR(20) NULL,
        ProposedProgramCode NVARCHAR(50) NULL,
        ProposedSubProgramCode NVARCHAR(50) NULL,
        ProposedGoalCode NVARCHAR(50) NULL,
        CleanObjectiveText NVARCHAR(MAX) NULL,
        KeepFlag BIT NOT NULL CONSTRAINT DF_tblSbObjectiveCleanupStage_KeepFlag DEFAULT (1),
        ReviewStatusCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblSbObjectiveCleanupStage_ReviewStatusCode DEFAULT (N'PENDING'),
        ReviewNotes NVARCHAR(MAX) NULL,
        SourceTag NVARCHAR(100) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbObjectiveCleanupStage_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbObjectiveCleanupStage_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbObjectiveCleanupStage')
      AND name = 'IX_tblSbObjectiveCleanupStage_ReviewStatusCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbObjectiveCleanupStage_ReviewStatusCode
        ON dbo.tblSbObjectiveCleanupStage (ReviewStatusCode, KeepFlag, VoteCode);
END
GO
