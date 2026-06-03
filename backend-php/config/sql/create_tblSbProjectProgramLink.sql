USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbProjectProgramLink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProjectProgramLink (
        ProjectProgramLinkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectID INT NOT NULL,
        ProgramID INT NOT NULL,
        LinkTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProjectProgramLink_LinkTypeCode DEFAULT (N'PRIMARY'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectProgramLink_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProjectProgramLink_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProjectProgramLink_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectProgramLink_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectProgramLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectProgramLink
    ADD CONSTRAINT FK_tblSbProjectProgramLink_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectProgramLink_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectProgramLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectProgramLink
    ADD CONSTRAINT FK_tblSbProjectProgramLink_Program
        FOREIGN KEY (ProgramID) REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProjectProgramLink_ProjectID_ProgramID_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProjectProgramLink')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProjectProgramLink_ProjectID_ProgramID_Active
        ON dbo.tblSbProjectProgramLink (ProjectID, ProgramID)
        WHERE ActiveFlag = 1;
END
GO
