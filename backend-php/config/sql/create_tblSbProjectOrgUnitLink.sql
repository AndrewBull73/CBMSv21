USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbProjectOrgUnitLink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProjectOrgUnitLink (
        ProjectOrgUnitLinkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectID INT NOT NULL,
        OrgUnitID INT NOT NULL,
        RoleCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProjectOrgUnitLink_RoleCode DEFAULT (N'IMPLEMENTING'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectOrgUnitLink_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProjectOrgUnitLink_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProjectOrgUnitLink_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectOrgUnitLink_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectOrgUnitLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectOrgUnitLink
    ADD CONSTRAINT FK_tblSbProjectOrgUnitLink_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectOrgUnitLink_OrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectOrgUnitLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectOrgUnitLink
    ADD CONSTRAINT FK_tblSbProjectOrgUnitLink_OrgUnit
        FOREIGN KEY (OrgUnitID) REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProjectOrgUnitLink_ProjectID_OrgUnitID_RoleCode_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProjectOrgUnitLink')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProjectOrgUnitLink_ProjectID_OrgUnitID_RoleCode_Active
        ON dbo.tblSbProjectOrgUnitLink (ProjectID, OrgUnitID, RoleCode)
        WHERE ActiveFlag = 1;
END
GO
