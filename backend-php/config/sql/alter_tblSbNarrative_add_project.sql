USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbNarrative does not exist. Run create_strategic_budgeting_module_v2.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbNarrative', 'ProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbNarrative ADD ProjectID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbNarrative_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbNarrative')
)
BEGIN
    ALTER TABLE dbo.tblSbNarrative
    ADD CONSTRAINT FK_tblSbNarrative_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbNarrative_ProjectID_ActiveFlag'
      AND object_id = OBJECT_ID('dbo.tblSbNarrative')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbNarrative_ProjectID_ActiveFlag
        ON dbo.tblSbNarrative (ProjectID, ActiveFlag, FiscalYearID, VersionID);
END
GO
