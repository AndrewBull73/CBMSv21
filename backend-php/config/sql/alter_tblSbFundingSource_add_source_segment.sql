USE [CBMSv2];
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSource')
      AND name = 'UX_tblSbFundingSource_SourceFiscalYearID_SourceSegmentCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbFundingSource_SourceFiscalYearID_SourceSegmentCode
        ON dbo.tblSbFundingSource (SourceFiscalYearID, SourceSegmentCode)
        WHERE SourceFiscalYearID IS NOT NULL
          AND SourceSegmentCode IS NOT NULL;
END
GO
