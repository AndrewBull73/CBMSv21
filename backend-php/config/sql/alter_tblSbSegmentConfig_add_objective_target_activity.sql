USE [CBMSv2];
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
