USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbProject', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProject (
        ProjectID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectCode NVARCHAR(50) NULL,
        ProjectName NVARCHAR(200) NOT NULL,
        ProjectDescription NVARCHAR(MAX) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProject_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProject_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProject_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProject_Source'
      AND object_id = OBJECT_ID('dbo.tblSbProject')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProject_Source
        ON dbo.tblSbProject (SourceFiscalYearID, SourceDataObjectCode, SourceSegmentCode)
        WHERE SourceFiscalYearID IS NOT NULL
          AND SourceSegmentCode IS NOT NULL
          AND ActiveFlag = 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbProject_Code'
      AND object_id = OBJECT_ID('dbo.tblSbProject')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProject_Code
        ON dbo.tblSbProject (ProjectCode, ActiveFlag);
END
GO
