/*
    Full strategic configuration reset for retesting the setup and readiness flow.

    What this script removes:
    - strategic segment mappings
    - imported strategic overlays (sectors, programs, subprograms, funding, economic, org units)
    - planning framework reference data (strategic pillars, goals)
    - custom attribute definitions, options, and values
    - resource envelope rows
    - workflow state/history
    - strategic planning/delivery/governance records already entered in the module

    What this script does NOT remove:
    - dbo.tblSegments
    - dbo.tblSegmentValues
    - dbo.tblDataObjectCodes
    - base fiscal/version/source tables outside the strategic module

    Recommended use:
    1. Run the script as-is first and review the preview counts.
    2. If the counts look correct, set @ExecuteDelete = 1 and run it again.
*/

SET NOCOUNT ON;

DECLARE @ExecuteDelete BIT = 0;

IF OBJECT_ID('tempdb..#ResetCounts') IS NOT NULL
    DROP TABLE #ResetCounts;

CREATE TABLE #ResetCounts (
    SortOrder INT NOT NULL,
    Item NVARCHAR(120) NOT NULL,
    TotalRows INT NOT NULL
);

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (10, 'Workflow History', (SELECT COUNT(*) FROM dbo.tblSbVersionWorkflowHistory));

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (20, 'Workflow State', (SELECT COUNT(*) FROM dbo.tblSbVersionWorkflow));

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (30, 'Resource Envelope', (SELECT COUNT(*) FROM dbo.tblSbResourceEnvelope));

IF OBJECT_ID('dbo.tblSbDimensionAttributeValue', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (40, 'Custom Attribute Values', (SELECT COUNT(*) FROM dbo.tblSbDimensionAttributeValue));

IF OBJECT_ID('dbo.tblSbDimensionAttributeOption', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (50, 'Custom Attribute Options', (SELECT COUNT(*) FROM dbo.tblSbDimensionAttributeOption));

IF OBJECT_ID('dbo.tblSbDimensionAttribute', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (60, 'Custom Attributes', (SELECT COUNT(*) FROM dbo.tblSbDimensionAttribute));

IF OBJECT_ID('dbo.tblSbObjectiveCleanupStage', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (70, 'Objective Cleanup Stage', (SELECT COUNT(*) FROM dbo.tblSbObjectiveCleanupStage));

IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (80, 'Program Risks', (SELECT COUNT(*) FROM dbo.tblSbProgramRisk));

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (90, 'Narratives', (SELECT COUNT(*) FROM dbo.tblSbNarrative));

IF OBJECT_ID('dbo.tblSbActivityBudget', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (100, 'Activity Budgets', (SELECT COUNT(*) FROM dbo.tblSbActivityBudget));

IF OBJECT_ID('dbo.tblSbOutputIndicator', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (110, 'Output Indicator Links', (SELECT COUNT(*) FROM dbo.tblSbOutputIndicator));

IF OBJECT_ID('dbo.tblSbActivity', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (120, 'Activities', (SELECT COUNT(*) FROM dbo.tblSbActivity));

IF OBJECT_ID('dbo.tblSbOutput', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (130, 'Outputs', (SELECT COUNT(*) FROM dbo.tblSbOutput));

IF OBJECT_ID('dbo.tblSbIndicatorTarget', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (140, 'Indicator Targets', (SELECT COUNT(*) FROM dbo.tblSbIndicatorTarget));

IF OBJECT_ID('dbo.tblSbObjectiveIndicator', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (150, 'Objective Indicator Links', (SELECT COUNT(*) FROM dbo.tblSbObjectiveIndicator));

IF OBJECT_ID('dbo.tblSbObjectiveGoal', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (160, 'Objective Goal Links', (SELECT COUNT(*) FROM dbo.tblSbObjectiveGoal));

IF OBJECT_ID('dbo.tblSbIndicator', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (170, 'Indicators', (SELECT COUNT(*) FROM dbo.tblSbIndicator));

IF OBJECT_ID('dbo.tblSbObjective', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (180, 'Objectives', (SELECT COUNT(*) FROM dbo.tblSbObjective));

IF OBJECT_ID('dbo.tblSbCeiling', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (190, 'Strategic Ceilings', (SELECT COUNT(*) FROM dbo.tblSbCeiling));

IF OBJECT_ID('dbo.tblSbProgramOrgLink', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (200, 'Program Org Links', (SELECT COUNT(*) FROM dbo.tblSbProgramOrgLink));

IF OBJECT_ID('dbo.tblSbSubProgram', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (210, 'SubPrograms', (SELECT COUNT(*) FROM dbo.tblSbSubProgram));

IF OBJECT_ID('dbo.tblSbProgram', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (220, 'Programs', (SELECT COUNT(*) FROM dbo.tblSbProgram));

IF OBJECT_ID('dbo.tblSbFundingSource', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (230, 'Funding Sources', (SELECT COUNT(*) FROM dbo.tblSbFundingSource));

IF OBJECT_ID('dbo.tblSbFundingType', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (240, 'Funding Types', (SELECT COUNT(*) FROM dbo.tblSbFundingType));

IF OBJECT_ID('dbo.tblSbEconomicItem', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (250, 'Economic Items', (SELECT COUNT(*) FROM dbo.tblSbEconomicItem));

IF OBJECT_ID('dbo.tblSbGoal', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (260, 'Goals', (SELECT COUNT(*) FROM dbo.tblSbGoal));

IF OBJECT_ID('dbo.tblSbStrategicPillar', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (270, 'Strategic Pillars', (SELECT COUNT(*) FROM dbo.tblSbStrategicPillar));

IF OBJECT_ID('dbo.tblSbSector', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (280, 'Sectors', (SELECT COUNT(*) FROM dbo.tblSbSector));

IF OBJECT_ID('dbo.tblSbOrgUnit', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (290, 'Org Units', (SELECT COUNT(*) FROM dbo.tblSbOrgUnit));

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (300, 'Fiscal Risks', (SELECT COUNT(*) FROM dbo.tblSbFiscalRisk));

IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (310, 'Segment Config', (SELECT COUNT(*) FROM dbo.tblSbSegmentConfig));

PRINT 'Preview counts for full strategic configuration reset';
SELECT Item, TotalRows
FROM #ResetCounts
ORDER BY SortOrder;

IF @ExecuteDelete = 0
BEGIN
    PRINT 'Preview only. Set @ExecuteDelete = 1 to execute the reset.';
    RETURN;
END;

BEGIN TRANSACTION;

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbVersionWorkflowHistory;

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbVersionWorkflow;

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbResourceEnvelope;

IF OBJECT_ID('dbo.tblSbDimensionAttributeValue', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbDimensionAttributeValue;

IF OBJECT_ID('dbo.tblSbDimensionAttributeOption', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbDimensionAttributeOption;

IF OBJECT_ID('dbo.tblSbDimensionAttribute', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbDimensionAttribute;

IF OBJECT_ID('dbo.tblSbObjectiveCleanupStage', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbObjectiveCleanupStage;

IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProgramRisk;

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbNarrative;

IF OBJECT_ID('dbo.tblSbActivityBudget', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbActivityBudget;

IF OBJECT_ID('dbo.tblSbOutputIndicator', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbOutputIndicator;

IF OBJECT_ID('dbo.tblSbActivity', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbActivity;

IF OBJECT_ID('dbo.tblSbOutput', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbOutput;

IF OBJECT_ID('dbo.tblSbIndicatorTarget', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbIndicatorTarget;

IF OBJECT_ID('dbo.tblSbObjectiveIndicator', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbObjectiveIndicator;

IF OBJECT_ID('dbo.tblSbObjectiveGoal', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbObjectiveGoal;

IF OBJECT_ID('dbo.tblSbIndicator', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbIndicator;

IF OBJECT_ID('dbo.tblSbObjective', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbObjective;

IF OBJECT_ID('dbo.tblSbCeiling', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbCeiling;

IF OBJECT_ID('dbo.tblSbProgramOrgLink', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProgramOrgLink;

IF OBJECT_ID('dbo.tblSbSubProgram', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbSubProgram;

IF OBJECT_ID('dbo.tblSbProgram', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProgram;

IF OBJECT_ID('dbo.tblSbFundingSource', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbFundingSource;

IF OBJECT_ID('dbo.tblSbFundingType', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbFundingType;

IF OBJECT_ID('dbo.tblSbEconomicItem', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbEconomicItem;

IF OBJECT_ID('dbo.tblSbGoal', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbGoal;

IF OBJECT_ID('dbo.tblSbStrategicPillar', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbStrategicPillar;

IF OBJECT_ID('dbo.tblSbSector', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbSector;

IF OBJECT_ID('dbo.tblSbOrgUnit', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbOrgUnit;

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbFiscalRisk;

IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbSegmentConfig;

COMMIT TRANSACTION;

PRINT 'Strategic configuration reset completed.';
