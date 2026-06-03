IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbSegmentConfig does not exist. Run create_tblSbSegmentConfig.sql first.', 1;
END;
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbSegmentConfig_SegmentNo'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentConfig
    DROP CONSTRAINT CK_tblSbSegmentConfig_SegmentNo;
END
GO

ALTER TABLE dbo.tblSbSegmentConfig
ALTER COLUMN SegmentNo INT NULL;
GO

ALTER TABLE dbo.tblSbSegmentConfig
ADD CONSTRAINT CK_tblSbSegmentConfig_SegmentNo
CHECK (SegmentNo IS NULL OR SegmentNo BETWEEN 1 AND 20);
GO
