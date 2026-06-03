USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbSegmentConfig does not exist. Run create_tblSbSegmentConfig.sql first.', 1;
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbSegmentConfig_StrategicDimensionCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentConfig
    DROP CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode;
END
GO

ALTER TABLE dbo.tblSbSegmentConfig
ADD CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode CHECK (
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
);
GO
