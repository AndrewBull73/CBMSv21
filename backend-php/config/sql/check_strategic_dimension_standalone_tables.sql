USE [CBMSv2];
GO

WITH required_dimensions AS (
    SELECT N'ORG_UNIT' AS StrategicDimensionCode, N'dbo.tblSbOrgUnit' AS TableName
    UNION ALL SELECT N'SECTOR', N'dbo.tblSbSector'
    UNION ALL SELECT N'PROGRAM', N'dbo.tblSbProgram'
    UNION ALL SELECT N'SUBPROGRAM', N'dbo.tblSbSubProgram'
    UNION ALL SELECT N'OBJECTIVE', N'dbo.tblSbObjective'
    UNION ALL SELECT N'INDICATOR', N'dbo.tblSbIndicator'
    UNION ALL SELECT N'TARGET', N'dbo.tblSbIndicatorTarget'
    UNION ALL SELECT N'OUTPUT', N'dbo.tblSbOutput'
    UNION ALL SELECT N'ACTIVITY', N'dbo.tblSbActivity'
    UNION ALL SELECT N'ECONOMIC', N'dbo.tblSbEconomicItem'
    UNION ALL SELECT N'FUNDING_TYPE', N'dbo.tblSbFundingType'
    UNION ALL SELECT N'FUNDING_SOURCE', N'dbo.tblSbFundingSource'
)
SELECT
    d.StrategicDimensionCode,
    d.TableName,
    CASE WHEN OBJECT_ID(d.TableName, 'U') IS NOT NULL THEN 1 ELSE 0 END AS TableExists,
    CASE WHEN COL_LENGTH(d.TableName, 'SourceFiscalYearID') IS NOT NULL THEN 1 ELSE 0 END AS HasSourceFiscalYearID,
    CASE WHEN COL_LENGTH(d.TableName, 'SourceDataObjectCode') IS NOT NULL THEN 1 ELSE 0 END AS HasSourceDataObjectCode,
    CASE WHEN COL_LENGTH(d.TableName, 'SourceSegmentNo') IS NOT NULL THEN 1 ELSE 0 END AS HasSourceSegmentNo,
    CASE WHEN COL_LENGTH(d.TableName, 'SourceSegmentCode') IS NOT NULL THEN 1 ELSE 0 END AS HasSourceSegmentCode
FROM required_dimensions d
ORDER BY d.StrategicDimensionCode;
GO
