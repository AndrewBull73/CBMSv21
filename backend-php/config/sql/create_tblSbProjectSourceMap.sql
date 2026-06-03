USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbProjectSourceMap', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProjectSourceMap (
        ProjectSourceMapID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        DataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NOT NULL,
        SourceSegmentCode NVARCHAR(50) NOT NULL,
        SourceSegmentName NVARCHAR(200) NULL,
        SourceSystemCode NVARCHAR(30) NULL,
        IsPrimaryFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectSourceMap_IsPrimaryFlag DEFAULT (1),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectSourceMap_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProjectSourceMap_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProjectSourceMap_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectSourceMap_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectSourceMap')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectSourceMap
    ADD CONSTRAINT FK_tblSbProjectSourceMap_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProjectSourceMap_Source_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProjectSourceMap')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProjectSourceMap_Source_Active
        ON dbo.tblSbProjectSourceMap (FiscalYearID, DataObjectCode, SourceSegmentNo, SourceSegmentCode)
        WHERE ActiveFlag = 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbProjectSourceMap_ProjectID_FiscalYearID'
      AND object_id = OBJECT_ID('dbo.tblSbProjectSourceMap')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProjectSourceMap_ProjectID_FiscalYearID
        ON dbo.tblSbProjectSourceMap (ProjectID, FiscalYearID, ActiveFlag);
END
GO
