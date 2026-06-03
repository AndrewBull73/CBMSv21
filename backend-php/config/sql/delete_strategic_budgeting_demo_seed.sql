USE [CBMSv2];
GO

/*
  Strategic Budgeting Demo Cleanup
  --------------------------------
  Removes the seeded demo strategic content created by:
  - seed_strategic_budgeting_demo.sql
  - seed_strategic_budgeting_demo_expanded.sql

  This script is designed to leave configuration data alone, including:
  - tblSbSegmentConfig
  - Strategic Segment Mapping
  - tblSegmentValues
  - setup/config screens and app metadata
  - workflow configuration tables

  It removes only demo rows identified by DEMO codes/names and rows linked to
  those demo programmes/subprogrammes/outputs/activities.

  Safe to preview first:
  - run only the SELECT statements near the top
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

BEGIN TRY
    BEGIN TRAN;

    DECLARE @DemoPrograms TABLE (ProgramID INT PRIMARY KEY);
    DECLARE @DemoSubPrograms TABLE (SubProgramID INT PRIMARY KEY);
    DECLARE @DemoObjectives TABLE (ObjectiveID INT PRIMARY KEY);
    DECLARE @DemoOutputs TABLE (OutputID INT PRIMARY KEY);
    DECLARE @DemoActivities TABLE (ActivityID INT PRIMARY KEY);
    DECLARE @DemoIndicators TABLE (IndicatorID INT PRIMARY KEY);
    DECLARE @DemoRisks TABLE (FiscalRiskID INT PRIMARY KEY);
    DECLARE @DemoSectors TABLE (SectorID INT PRIMARY KEY);
    DECLARE @DemoOrgUnits TABLE (OrgUnitID INT PRIMARY KEY);
    DECLARE @DemoFundingTypes TABLE (FundingTypeID INT PRIMARY KEY);
    DECLARE @DemoFundingSources TABLE (FundingSourceID INT PRIMARY KEY);
    DECLARE @DemoEconomicItems TABLE (EconomicItemID INT PRIMARY KEY);

    INSERT INTO @DemoPrograms (ProgramID)
    SELECT p.ProgramID
    FROM dbo.tblSbProgram p
    WHERE COALESCE(p.ProgramCode, N'') LIKE N'DEMO-%'
       OR COALESCE(p.ProgramName, N'') LIKE N'Demo %';

    INSERT INTO @DemoSubPrograms (SubProgramID)
    SELECT sp.SubProgramID
    FROM dbo.tblSbSubProgram sp
    WHERE COALESCE(sp.SubProgramCode, N'') LIKE N'DEMO-%'
       OR COALESCE(sp.SubProgramName, N'') LIKE N'Demo %'
       OR sp.ProgramID IN (SELECT ProgramID FROM @DemoPrograms);

    INSERT INTO @DemoObjectives (ObjectiveID)
    SELECT o.ObjectiveID
    FROM dbo.tblSbObjective o
    WHERE o.ProgramID IN (SELECT ProgramID FROM @DemoPrograms)
       OR COALESCE(o.ObjectiveText, N'') LIKE N'Demo %'
       OR COALESCE(o.PolicyLink, N'') LIKE N'DEMO-%';

    INSERT INTO @DemoOutputs (OutputID)
    SELECT o.OutputID
    FROM dbo.tblSbOutput o
    WHERE o.ProgramID IN (SELECT ProgramID FROM @DemoPrograms)
       OR COALESCE(o.OutputName, N'') LIKE N'Demo %';

    INSERT INTO @DemoActivities (ActivityID)
    SELECT a.ActivityID
    FROM dbo.tblSbActivity a
    WHERE a.OutputID IN (SELECT OutputID FROM @DemoOutputs)
       OR COALESCE(a.ActivityName, N'') LIKE N'Demo %'
       OR COALESCE(a.LocationCode, N'') LIKE N'DEMO-%';

    INSERT INTO @DemoIndicators (IndicatorID)
    SELECT DISTINCT i.IndicatorID
    FROM dbo.tblSbIndicator i
    LEFT JOIN dbo.tblSbObjectiveIndicator oi
        ON oi.IndicatorID = i.IndicatorID
    LEFT JOIN dbo.tblSbOutputIndicator outi
        ON outi.IndicatorID = i.IndicatorID
    WHERE COALESCE(i.IndicatorName, N'') LIKE N'Demo %'
       OR COALESCE(i.DataSource, N'') LIKE N'Demo %'
       OR COALESCE(i.QualityNotes, N'') LIKE N'%demo%'
       OR oi.ObjectiveID IN (SELECT ObjectiveID FROM @DemoObjectives)
       OR outi.OutputID IN (SELECT OutputID FROM @DemoOutputs);

    INSERT INTO @DemoRisks (FiscalRiskID)
    SELECT fr.FiscalRiskID
    FROM dbo.tblSbFiscalRisk fr
    WHERE COALESCE(fr.RiskTitle, N'') LIKE N'Demo %'
       OR COALESCE(fr.RiskDescription, N'') LIKE N'Demo %'
       OR COALESCE(fr.MitigationStrategy, N'') LIKE N'%demo%'
       OR fr.OwnerOrgUnitID IN (
            SELECT o.OrgUnitID
            FROM dbo.tblSbOrgUnit o
            WHERE COALESCE(o.VoteCode, N'') LIKE N'SB-DEMO-%'
               OR COALESCE(o.OrgUnitName, N'') LIKE N'Demo %'
       );

    INSERT INTO @DemoSectors (SectorID)
    SELECT s.SectorID
    FROM dbo.tblSbSector s
    WHERE COALESCE(s.SectorName, N'') LIKE N'DEMO %'
       OR COALESCE(s.SectorName, N'') LIKE N'DEMO-%'
       OR COALESCE(s.SectorDescription, N'') LIKE N'Demo %';

    INSERT INTO @DemoOrgUnits (OrgUnitID)
    SELECT o.OrgUnitID
    FROM dbo.tblSbOrgUnit o
    WHERE COALESCE(o.VoteCode, N'') LIKE N'SB-DEMO-%'
       OR COALESCE(o.OrgUnitName, N'') LIKE N'Demo %';

    INSERT INTO @DemoFundingTypes (FundingTypeID)
    SELECT ft.FundingTypeID
    FROM dbo.tblSbFundingType ft
    WHERE COALESCE(ft.FundingTypeCode, N'') LIKE N'DEMO-%'
       OR COALESCE(ft.FundingTypeName, N'') LIKE N'Demo %';

    INSERT INTO @DemoFundingSources (FundingSourceID)
    SELECT fs.FundingSourceID
    FROM dbo.tblSbFundingSource fs
    WHERE COALESCE(fs.FundingTypeCode, N'') LIKE N'DEMO-%'
       OR COALESCE(fs.FundingSourceName, N'') LIKE N'Demo %'
       OR COALESCE(fs.DonorName, N'') LIKE N'Demo %';

    INSERT INTO @DemoEconomicItems (EconomicItemID)
    SELECT ei.EconomicItemID
    FROM dbo.tblSbEconomicItem ei
    WHERE COALESCE(ei.EconomicCode, N'') LIKE N'DEMO-%'
       OR COALESCE(ei.EconomicName, N'') LIKE N'Demo %';

    /*
      Preview counts
    */
    SELECT
        (SELECT COUNT(*) FROM @DemoPrograms) AS DemoPrograms,
        (SELECT COUNT(*) FROM @DemoSubPrograms) AS DemoSubPrograms,
        (SELECT COUNT(*) FROM @DemoObjectives) AS DemoObjectives,
        (SELECT COUNT(*) FROM @DemoOutputs) AS DemoOutputs,
        (SELECT COUNT(*) FROM @DemoActivities) AS DemoActivities,
        (SELECT COUNT(*) FROM @DemoIndicators) AS DemoIndicators,
        (SELECT COUNT(*) FROM @DemoRisks) AS DemoRisks,
        (SELECT COUNT(*) FROM @DemoSectors) AS DemoSectors,
        (SELECT COUNT(*) FROM @DemoOrgUnits) AS DemoOrgUnits,
        (SELECT COUNT(*) FROM @DemoFundingTypes) AS DemoFundingTypes,
        (SELECT COUNT(*) FROM @DemoFundingSources) AS DemoFundingSources,
        (SELECT COUNT(*) FROM @DemoEconomicItems) AS DemoEconomicItems;

    DELETE FROM dbo.tblSbProgramRisk
    WHERE ProgramID IN (SELECT ProgramID FROM @DemoPrograms)
       OR FiscalRiskID IN (SELECT FiscalRiskID FROM @DemoRisks);

    DELETE FROM dbo.tblSbActivityBudget
    WHERE ActivityID IN (SELECT ActivityID FROM @DemoActivities)
       OR FundingSourceID IN (SELECT FundingSourceID FROM @DemoFundingSources)
       OR EconomicItemID IN (SELECT EconomicItemID FROM @DemoEconomicItems);

    DELETE FROM dbo.tblSbOutputIndicator
    WHERE OutputID IN (SELECT OutputID FROM @DemoOutputs)
       OR IndicatorID IN (SELECT IndicatorID FROM @DemoIndicators);

    DELETE FROM dbo.tblSbObjectiveIndicator
    WHERE ObjectiveID IN (SELECT ObjectiveID FROM @DemoObjectives)
       OR IndicatorID IN (SELECT IndicatorID FROM @DemoIndicators);

    DELETE FROM dbo.tblSbIndicatorTarget
    WHERE IndicatorID IN (SELECT IndicatorID FROM @DemoIndicators)
       OR COALESCE(Notes, N'') LIKE N'%demo%';

    DELETE FROM dbo.tblSbNarrative
    WHERE COALESCE(NarrativeTitle, N'') LIKE N'Demo %'
       OR COALESCE(NarrativeTitle, N'') LIKE N'Expanded Demo %'
       OR COALESCE(BodyText, N'') LIKE N'Demo %'
       OR ProgramID IN (SELECT ProgramID FROM @DemoPrograms)
       OR SectorID IN (SELECT SectorID FROM @DemoSectors);

    DELETE FROM dbo.tblSbActivity
    WHERE ActivityID IN (SELECT ActivityID FROM @DemoActivities);

    DELETE FROM dbo.tblSbOutput
    WHERE OutputID IN (SELECT OutputID FROM @DemoOutputs);

    DELETE FROM dbo.tblSbObjective
    WHERE ObjectiveID IN (SELECT ObjectiveID FROM @DemoObjectives);

    DELETE FROM dbo.tblSbIndicator
    WHERE IndicatorID IN (SELECT IndicatorID FROM @DemoIndicators);

    DELETE FROM dbo.tblSbSubProgram
    WHERE SubProgramID IN (SELECT SubProgramID FROM @DemoSubPrograms);

    IF OBJECT_ID('dbo.tblSbProgramOrgLink', 'U') IS NOT NULL
    BEGIN
        DELETE FROM dbo.tblSbProgramOrgLink
        WHERE ProgramID IN (SELECT ProgramID FROM @DemoPrograms)
           OR OrgUnitID IN (SELECT OrgUnitID FROM @DemoOrgUnits);
    END;

    DELETE FROM dbo.tblSbProgram
    WHERE ProgramID IN (SELECT ProgramID FROM @DemoPrograms);

    DELETE FROM dbo.tblSbFiscalRisk
    WHERE FiscalRiskID IN (SELECT FiscalRiskID FROM @DemoRisks);

    DELETE FROM dbo.tblSbFundingSource
    WHERE FundingSourceID IN (SELECT FundingSourceID FROM @DemoFundingSources);

    IF OBJECT_ID('dbo.tblSbFundingType', 'U') IS NOT NULL
    BEGIN
        DELETE FROM dbo.tblSbFundingType
        WHERE FundingTypeID IN (SELECT FundingTypeID FROM @DemoFundingTypes);
    END;

    DELETE FROM dbo.tblSbEconomicItem
    WHERE EconomicItemID IN (SELECT EconomicItemID FROM @DemoEconomicItems);

    DELETE FROM dbo.tblSbSector
    WHERE SectorID IN (SELECT SectorID FROM @DemoSectors);

    DELETE FROM dbo.tblSbOrgUnit
    WHERE OrgUnitID IN (SELECT OrgUnitID FROM @DemoOrgUnits);

    COMMIT TRAN;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;
END CATCH;
GO
