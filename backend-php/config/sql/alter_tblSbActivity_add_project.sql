USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbActivity', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbActivity does not exist. Run create_strategic_budgeting_module_v2.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'ProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity ADD ProjectID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbActivity_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbActivity')
)
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD CONSTRAINT FK_tblSbActivity_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbActivity_ProjectID_ActiveFlag'
      AND object_id = OBJECT_ID('dbo.tblSbActivity')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbActivity_ProjectID_ActiveFlag
        ON dbo.tblSbActivity (ProjectID, ActiveFlag);
END
GO
