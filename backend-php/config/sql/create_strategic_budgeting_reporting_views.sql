USE [CBMSv2];
GO

/*
Strategic Budgeting Reporting Views
----------------------------------
Read-only reporting views over the tblSb* strategic budgeting schema.
These are safe to recreate and are intended to support PHP list/report pages.
*/

CREATE OR ALTER VIEW dbo.vwSbActivityBudgetDetail
AS
SELECT
    ab.ActivityBudgetID,
    ab.VersionID,
    ab.FiscalYearID,
    ab.ActivityID,
    act.ActivityName,
    act.ActivityTypeCode,
    act.ImplementationStatusCode,
    act.ProjectID,
    prj.ProjectCode,
    prj.ProjectName,
    outp.OutputID,
    outp.OutputName,
    prg.ProgramID,
    prg.ProgramCode,
    prg.ProgramName,
    sp.SubProgramID,
    sp.SubProgramCode,
    sp.SubProgramName,
    sec.SectorID,
    sec.SectorName,
    ou.OrgUnitID,
    ou.OrgUnitName,
    ou.OrgUnitTypeCode,
    ei.EconomicItemID,
    ei.EconomicCode,
    ei.EconomicName,
    fs.FundingSourceID,
    fs.FundingSourceName,
    fs.FundingTypeCode,
    ab.Amount,
    ab.CurrencyCode,
    ab.Notes,
    ab.ActiveFlag
FROM dbo.tblSbActivityBudget ab
INNER JOIN dbo.tblSbActivity act
    ON act.ActivityID = ab.ActivityID
INNER JOIN dbo.tblSbOutput outp
    ON outp.OutputID = act.OutputID
INNER JOIN dbo.tblSbProgram prg
    ON prg.ProgramID = outp.ProgramID
LEFT JOIN dbo.tblSbProject prj
    ON prj.ProjectID = act.ProjectID
LEFT JOIN dbo.tblSbSubProgram sp
    ON sp.SubProgramID = outp.SubProgramID
INNER JOIN dbo.tblSbSector sec
    ON sec.SectorID = prg.SectorID
INNER JOIN dbo.tblSbOrgUnit ou
    ON ou.OrgUnitID = prg.OrgUnitID
INNER JOIN dbo.tblSbEconomicItem ei
    ON ei.EconomicItemID = ab.EconomicItemID
LEFT JOIN dbo.tblSbFundingSource fs
    ON fs.FundingSourceID = ab.FundingSourceID
WHERE ab.ActiveFlag = 1
  AND act.ActiveFlag = 1
  AND outp.ActiveFlag = 1
  AND prg.ActiveFlag = 1
  AND sec.ActiveFlag = 1
  AND ou.ActiveFlag = 1
  AND ei.ActiveFlag = 1;
GO

CREATE OR ALTER VIEW dbo.vwSbBudgetBySector
AS
SELECT
    d.VersionID,
    d.FiscalYearID,
    d.SectorID,
    d.SectorName,
    COUNT(DISTINCT d.ProgramID) AS ProgramCount,
    COUNT(DISTINCT d.OutputID) AS OutputCount,
    COUNT(DISTINCT d.ActivityID) AS ActivityCount,
    SUM(d.Amount) AS TotalAmount
FROM dbo.vwSbActivityBudgetDetail d
GROUP BY
    d.VersionID,
    d.FiscalYearID,
    d.SectorID,
    d.SectorName;
GO

CREATE OR ALTER VIEW dbo.vwSbBudgetByProgram
AS
SELECT
    d.VersionID,
    d.FiscalYearID,
    d.ProgramID,
    d.ProgramCode,
    d.ProgramName,
    d.SectorID,
    d.SectorName,
    d.OrgUnitID,
    d.OrgUnitName,
    COUNT(DISTINCT d.OutputID) AS OutputCount,
    COUNT(DISTINCT d.ActivityID) AS ActivityCount,
    SUM(d.Amount) AS TotalAmount
FROM dbo.vwSbActivityBudgetDetail d
GROUP BY
    d.VersionID,
    d.FiscalYearID,
    d.ProgramID,
    d.ProgramCode,
    d.ProgramName,
    d.SectorID,
    d.SectorName,
    d.OrgUnitID,
    d.OrgUnitName;
GO

CREATE OR ALTER VIEW dbo.vwSbBudgetByEconomicItem
AS
SELECT
    d.VersionID,
    d.FiscalYearID,
    d.EconomicItemID,
    d.EconomicCode,
    d.EconomicName,
    SUM(d.Amount) AS TotalAmount
FROM dbo.vwSbActivityBudgetDetail d
GROUP BY
    d.VersionID,
    d.FiscalYearID,
    d.EconomicItemID,
    d.EconomicCode,
    d.EconomicName;
GO

CREATE OR ALTER VIEW dbo.vwSbBudgetByProject
AS
SELECT
    d.VersionID,
    d.FiscalYearID,
    d.ProjectID,
    d.ProjectCode,
    d.ProjectName,
    COUNT(DISTINCT d.ProgramID) AS ProgramCount,
    COUNT(DISTINCT d.OutputID) AS OutputCount,
    COUNT(DISTINCT d.ActivityID) AS ActivityCount,
    COUNT(DISTINCT d.EconomicItemID) AS EconomicLines,
    COUNT(DISTINCT d.FundingSourceID) AS FundingSourceCount,
    SUM(d.Amount) AS TotalAmount
FROM dbo.vwSbActivityBudgetDetail d
WHERE d.ProjectID IS NOT NULL
GROUP BY
    d.VersionID,
    d.FiscalYearID,
    d.ProjectID,
    d.ProjectCode,
    d.ProjectName;
GO
