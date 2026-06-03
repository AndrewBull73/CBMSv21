USE [CBMSv2];
GO

/*
Link strategic org units to existing DataScope org units (tblDataObjectCodes)
so the strategic budgeting module can reuse the established hierarchy.
*/

IF COL_LENGTH('dbo.tblSbOrgUnit', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOrgUnit', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOrgUnit')
      AND name = 'UX_tblSbOrgUnit_SourceFiscalYearID_SourceDataObjectCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbOrgUnit_SourceFiscalYearID_SourceDataObjectCode
        ON dbo.tblSbOrgUnit (SourceFiscalYearID, SourceDataObjectCode)
        WHERE SourceFiscalYearID IS NOT NULL
          AND SourceDataObjectCode IS NOT NULL;
END
GO
