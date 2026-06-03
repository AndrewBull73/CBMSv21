IF OBJECT_ID('dbo.tblSbProgramOrgLink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProgramOrgLink (
        ProgramOrgLinkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProgramID INT NOT NULL,
        OrgUnitID INT NOT NULL,
        LinkTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProgramOrgLink_LinkTypeCode DEFAULT (N'PARTICIPATING'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProgramOrgLink_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProgramOrgLink_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProgramOrgLink_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbProgramOrgLink_LinkTypeCode
            CHECK (LinkTypeCode IN (N'PARTICIPATING', N'CONTRIBUTING', N'IMPLEMENTING', N'REPORTING'))
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProgramOrgLink_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProgramOrgLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProgramOrgLink
    ADD CONSTRAINT FK_tblSbProgramOrgLink_Program
        FOREIGN KEY (ProgramID)
        REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProgramOrgLink_OrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProgramOrgLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProgramOrgLink
    ADD CONSTRAINT FK_tblSbProgramOrgLink_OrgUnit
        FOREIGN KEY (OrgUnitID)
        REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgramOrgLink')
      AND name = 'UX_tblSbProgramOrgLink_ProgramID_OrgUnitID_Active'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProgramOrgLink_ProgramID_OrgUnitID_Active
        ON dbo.tblSbProgramOrgLink (ProgramID, OrgUnitID)
        WHERE ActiveFlag = 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgramOrgLink')
      AND name = 'IX_tblSbProgramOrgLink_OrgUnitID_ActiveFlag'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProgramOrgLink_OrgUnitID_ActiveFlag
        ON dbo.tblSbProgramOrgLink (OrgUnitID, ActiveFlag);
END
GO
