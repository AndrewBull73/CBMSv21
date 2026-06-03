USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbProjectObjectiveLink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProjectObjectiveLink (
        ProjectObjectiveLinkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectID INT NOT NULL,
        ObjectiveID INT NOT NULL,
        ContributionTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProjectObjectiveLink_ContributionTypeCode DEFAULT (N'DIRECT'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectObjectiveLink_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProjectObjectiveLink_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProjectObjectiveLink_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectObjectiveLink_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectObjectiveLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectObjectiveLink
    ADD CONSTRAINT FK_tblSbProjectObjectiveLink_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectObjectiveLink_Objective'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectObjectiveLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectObjectiveLink
    ADD CONSTRAINT FK_tblSbProjectObjectiveLink_Objective
        FOREIGN KEY (ObjectiveID) REFERENCES dbo.tblSbObjective (ObjectiveID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProjectObjectiveLink_ProjectID_ObjectiveID_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProjectObjectiveLink')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProjectObjectiveLink_ProjectID_ObjectiveID_Active
        ON dbo.tblSbProjectObjectiveLink (ProjectID, ObjectiveID)
        WHERE ActiveFlag = 1;
END
GO
