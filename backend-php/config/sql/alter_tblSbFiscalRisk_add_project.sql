USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbFiscalRisk does not exist. Run create_strategic_budgeting_module_v2.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbFiscalRisk', 'ProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFiscalRisk ADD ProjectID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFiscalRisk_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFiscalRisk')
)
BEGIN
    ALTER TABLE dbo.tblSbFiscalRisk
    ADD CONSTRAINT FK_tblSbFiscalRisk_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbFiscalRisk_ProjectID_ActiveFlag'
      AND object_id = OBJECT_ID('dbo.tblSbFiscalRisk')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFiscalRisk_ProjectID_ActiveFlag
        ON dbo.tblSbFiscalRisk (ProjectID, ActiveFlag);
END
GO
