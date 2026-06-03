USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbGoal', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbGoal (
        GoalID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        GoalCode NVARCHAR(50) NOT NULL,
        GoalName NVARCHAR(300) NOT NULL,
        GoalTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbGoal_GoalTypeCode DEFAULT (N'SDG'),
        GoalDescription NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbGoal_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbGoal_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbGoal_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbGoal')
      AND name = 'UX_tblSbGoal_GoalCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbGoal_GoalCode
        ON dbo.tblSbGoal (GoalCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbGoal_GoalTypeCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbGoal')
)
BEGIN
    ALTER TABLE dbo.tblSbGoal
    ADD CONSTRAINT CK_tblSbGoal_GoalTypeCode
        CHECK (GoalTypeCode IN (N'SDG', N'NDP', N'NSDP', N'GOVT_PRIORITY', N'OTHER'));
END
GO

IF OBJECT_ID('dbo.tblSbObjectiveGoal', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbObjectiveGoal (
        ObjectiveGoalID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ObjectiveID INT NOT NULL,
        GoalID INT NOT NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbObjectiveGoal_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbObjectiveGoal_CreatedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbObjectiveGoal_Objective'
      AND parent_object_id = OBJECT_ID('dbo.tblSbObjectiveGoal')
)
BEGIN
    ALTER TABLE dbo.tblSbObjectiveGoal
    ADD CONSTRAINT FK_tblSbObjectiveGoal_Objective
        FOREIGN KEY (ObjectiveID)
        REFERENCES dbo.tblSbObjective (ObjectiveID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbObjectiveGoal_Goal'
      AND parent_object_id = OBJECT_ID('dbo.tblSbObjectiveGoal')
)
BEGIN
    ALTER TABLE dbo.tblSbObjectiveGoal
    ADD CONSTRAINT FK_tblSbObjectiveGoal_Goal
        FOREIGN KEY (GoalID)
        REFERENCES dbo.tblSbGoal (GoalID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbObjectiveGoal')
      AND name = 'UX_tblSbObjectiveGoal_ObjectiveID_GoalID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbObjectiveGoal_ObjectiveID_GoalID
        ON dbo.tblSbObjectiveGoal (ObjectiveID, GoalID);
END
GO
