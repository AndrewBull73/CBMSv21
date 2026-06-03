USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbSegmentConfig (
        StrategicSegmentConfigID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        StrategicDimensionCode NVARCHAR(30) NOT NULL,
        SegmentNo INT NULL,
        Notes NVARCHAR(200) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbSegmentConfig_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbSegmentConfig_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbSegmentConfig_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode CHECK (
            StrategicDimensionCode IN (
                N'PROGRAM',
                N'SUBPROGRAM',
                N'PROJECT',
                N'SECTOR',
                N'ECONOMIC',
                N'FUNDING_TYPE',
                N'FUNDING_SOURCE',
                N'OBJECTIVE',
                N'INDICATOR',
                N'TARGET',
                N'ACTIVITY',
                N'OUTPUT'
            )
        ),
        CONSTRAINT CK_tblSbSegmentConfig_SegmentNo CHECK (SegmentNo IS NULL OR SegmentNo BETWEEN 1 AND 20)
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbSegmentConfig_FiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentConfig
    ADD CONSTRAINT FK_tblSbSegmentConfig_FiscalYear
        FOREIGN KEY (FiscalYearID)
        REFERENCES dbo.tblFiscalYears (FiscalYearID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbSegmentConfig_FiscalYearID_StrategicDimensionCode'
      AND object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbSegmentConfig_FiscalYearID_StrategicDimensionCode
        ON dbo.tblSbSegmentConfig (FiscalYearID, StrategicDimensionCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbSegmentConfig_SegmentNo'
      AND object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSegmentConfig_SegmentNo
        ON dbo.tblSbSegmentConfig (SegmentNo, FiscalYearID, ActiveFlag);
END
GO
