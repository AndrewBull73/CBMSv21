/*
    Reset imported strategic hierarchy overlays for a fiscal year so Sector -> Program -> SubProgram
    can be re-imported cleanly from tblSegmentValues.

    What this script removes:
    - source-backed Sectors for the selected fiscal year
    - source-backed Programs for the selected fiscal year
    - source-backed SubPrograms for the selected fiscal year
    - dependent strategic data linked to those Programs/SubPrograms:
      Objectives, ObjectiveGoal links, Indicators, IndicatorTargets,
      Outputs, Activities, ActivityBudgets, ProgramRisk links, ProgramOrg links

    What this script does NOT remove:
    - tblSbSegmentConfig
    - tblSegmentValues
    - Goals / Strategic Pillars / custom attribute definitions
    - Fiscal risks master records
    - Workflow/config/reference tables

    Review the preview block first, then uncomment the DELETE block to execute.
*/

SET NOCOUNT ON;

DECLARE @FiscalYearID INT = 2026;

DECLARE @SectorSegmentNo INT = (
    SELECT TOP 1 SegmentNo
    FROM dbo.tblSbSegmentConfig
    WHERE FiscalYearID = @FiscalYearID
      AND StrategicDimensionCode = 'SECTOR'
      AND ActiveFlag = 1
);

DECLARE @ProgramSegmentNo INT = (
    SELECT TOP 1 SegmentNo
    FROM dbo.tblSbSegmentConfig
    WHERE FiscalYearID = @FiscalYearID
      AND StrategicDimensionCode = 'PROGRAM'
      AND ActiveFlag = 1
);

DECLARE @SubProgramSegmentNo INT = (
    SELECT TOP 1 SegmentNo
    FROM dbo.tblSbSegmentConfig
    WHERE FiscalYearID = @FiscalYearID
      AND StrategicDimensionCode = 'SUBPROGRAM'
      AND ActiveFlag = 1
);

IF @FiscalYearID <= 0
BEGIN
    THROW 50000, 'FiscalYearID is required.', 1;
END;

IF @ProgramSegmentNo IS NULL OR @SubProgramSegmentNo IS NULL
BEGIN
    THROW 50000, 'PROGRAM and SUBPROGRAM mappings must exist in tblSbSegmentConfig for the selected fiscal year.', 1;
END;

IF OBJECT_ID('tempdb..#ProgramsToReset') IS NOT NULL DROP TABLE #ProgramsToReset;
IF OBJECT_ID('tempdb..#SubProgramsToReset') IS NOT NULL DROP TABLE #SubProgramsToReset;
IF OBJECT_ID('tempdb..#SectorsToReset') IS NOT NULL DROP TABLE #SectorsToReset;
IF OBJECT_ID('tempdb..#ObjectivesToReset') IS NOT NULL DROP TABLE #ObjectivesToReset;
IF OBJECT_ID('tempdb..#IndicatorsToReset') IS NOT NULL DROP TABLE #IndicatorsToReset;
IF OBJECT_ID('tempdb..#OutputsToReset') IS NOT NULL DROP TABLE #OutputsToReset;
IF OBJECT_ID('tempdb..#ActivitiesToReset') IS NOT NULL DROP TABLE #ActivitiesToReset;

SELECT DISTINCT p.ProgramID
INTO #ProgramsToReset
FROM dbo.tblSbProgram p
WHERE p.SourceFiscalYearID = @FiscalYearID
  AND p.SourceSegmentNo = @ProgramSegmentNo;

SELECT DISTINCT sp.SubProgramID
INTO #SubProgramsToReset
FROM dbo.tblSbSubProgram sp
INNER JOIN #ProgramsToReset p
    ON p.ProgramID = sp.ProgramID
WHERE sp.SourceFiscalYearID = @FiscalYearID
  AND sp.SourceSegmentNo = @SubProgramSegmentNo;

SELECT DISTINCT s.SectorID
INTO #SectorsToReset
FROM dbo.tblSbSector s
WHERE s.SourceFiscalYearID = @FiscalYearID
  AND (@SectorSegmentNo IS NULL OR s.SourceSegmentNo = @SectorSegmentNo);

SELECT DISTINCT o.ObjectiveID
INTO #ObjectivesToReset
FROM dbo.tblSbObjective o
INNER JOIN #ProgramsToReset p
    ON p.ProgramID = o.ProgramID;

SELECT DISTINCT i.IndicatorID
INTO #IndicatorsToReset
FROM dbo.tblSbIndicator i
INNER JOIN dbo.tblSbObjectiveIndicator oi
    ON oi.IndicatorID = i.IndicatorID
INNER JOIN #ObjectivesToReset o
    ON o.ObjectiveID = oi.ObjectiveID;

SELECT DISTINCT o.OutputID
INTO #OutputsToReset
FROM dbo.tblSbOutput o
INNER JOIN #ProgramsToReset p
    ON p.ProgramID = o.ProgramID;

SELECT DISTINCT a.ActivityID
INTO #ActivitiesToReset
FROM dbo.tblSbActivity a
INNER JOIN #OutputsToReset o
    ON o.OutputID = a.OutputID;

PRINT 'Preview counts for reset';
SELECT 'Programs' AS Item, COUNT(*) AS [RowCount] FROM #ProgramsToReset
UNION ALL
SELECT 'SubPrograms', COUNT(*) AS [RowCount] FROM #SubProgramsToReset
UNION ALL
SELECT 'Objectives', COUNT(*) AS [RowCount] FROM #ObjectivesToReset
UNION ALL
SELECT 'Indicators', COUNT(*) AS [RowCount] FROM #IndicatorsToReset
UNION ALL
SELECT 'Outputs', COUNT(*) AS [RowCount] FROM #OutputsToReset
UNION ALL
SELECT 'Activities', COUNT(*) AS [RowCount] FROM #ActivitiesToReset
UNION ALL
SELECT 'Sectors', COUNT(*) AS [RowCount] FROM #SectorsToReset;

/*
BEGIN TRANSACTION;

DELETE ab
FROM dbo.tblSbActivityBudget ab
INNER JOIN #ActivitiesToReset a
    ON a.ActivityID = ab.ActivityID;

DELETE a
FROM dbo.tblSbActivity a
INNER JOIN #ActivitiesToReset x
    ON x.ActivityID = a.ActivityID;

DELETE o
FROM dbo.tblSbOutput o
INNER JOIN #OutputsToReset x
    ON x.OutputID = o.OutputID;

DELETE it
FROM dbo.tblSbIndicatorTarget it
INNER JOIN #IndicatorsToReset i
    ON i.IndicatorID = it.IndicatorID;

IF OBJECT_ID('dbo.tblSbObjectiveIndicator', 'U') IS NOT NULL
BEGIN
    DELETE oi
    FROM dbo.tblSbObjectiveIndicator oi
    INNER JOIN #ObjectivesToReset o
        ON o.ObjectiveID = oi.ObjectiveID;
END;

DELETE i
FROM dbo.tblSbIndicator i
INNER JOIN #IndicatorsToReset x
    ON x.IndicatorID = i.IndicatorID;

IF OBJECT_ID('dbo.tblSbObjectiveGoal', 'U') IS NOT NULL
BEGIN
    DELETE og
    FROM dbo.tblSbObjectiveGoal og
    INNER JOIN #ObjectivesToReset o
        ON o.ObjectiveID = og.ObjectiveID;
END;

DELETE o
FROM dbo.tblSbObjective o
INNER JOIN #ObjectivesToReset x
    ON x.ObjectiveID = o.ObjectiveID;

IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NOT NULL
BEGIN
    DELETE pr
    FROM dbo.tblSbProgramRisk pr
    INNER JOIN #ProgramsToReset p
        ON p.ProgramID = pr.ProgramID;
END;

IF OBJECT_ID('dbo.tblSbProgramOrgLink', 'U') IS NOT NULL
BEGIN
    DELETE pol
    FROM dbo.tblSbProgramOrgLink pol
    INNER JOIN #ProgramsToReset p
        ON p.ProgramID = pol.ProgramID;
END;

DELETE sp
FROM dbo.tblSbSubProgram sp
INNER JOIN #SubProgramsToReset x
    ON x.SubProgramID = sp.SubProgramID;

DELETE p
FROM dbo.tblSbProgram p
INNER JOIN #ProgramsToReset x
    ON x.ProgramID = p.ProgramID;

DELETE s
FROM dbo.tblSbSector s
INNER JOIN #SectorsToReset x
    ON x.SectorID = s.SectorID
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblSbProgram p
    WHERE p.SectorID = s.SectorID
);

COMMIT TRANSACTION;
*/
