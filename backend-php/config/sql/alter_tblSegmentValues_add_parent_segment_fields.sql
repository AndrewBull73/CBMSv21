USE [CBMSv2];
GO

IF COL_LENGTH('dbo.tblSegmentValues', 'ParentSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSegmentValues
    ADD ParentSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSegmentValues', 'ParentSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSegmentValues
    ADD ParentSegmentCode NVARCHAR(50) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSegmentValues')
      AND name = 'IX_tblSegmentValues_ParentSegmentLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSegmentValues_ParentSegmentLookup
        ON dbo.tblSegmentValues (
            FiscalYearID,
            DataObjectCode,
            ParentSegmentNo,
            ParentSegmentCode
        )
        INCLUDE (
            SegmentNo,
            SegmentCode,
            SegmentName,
            ActiveFlag
        );
END
GO
