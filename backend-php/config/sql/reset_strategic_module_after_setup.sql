/*
    Full Strategy module reset back to "post-configuration zero".

    Use this when you want to keep the Strategy configuration foundation,
    but remove both:
    - user-entered Strategy transaction/input data
    - Strategy setup/master records created or imported after setup

    What this script removes:
    - funding submissions, lines, history, attachments
    - resource envelope rows
    - strategic ceilings
    - narratives
    - fiscal risks and program risk links
    - indicator targets
    - activity budgets
    - workflow state/history
    - segment publication requests/lines
    - strategic setup/master records:
        org units, sectors, programs, subprograms, projects,
        funding types, funding sources, economic items,
        objectives, indicators, outputs, activities,
        strategic pillars, goals
    - supporting link/source-map tables for those masters
    - custom attribute values only

    What this script does NOT remove:
    - tblSbSegmentConfig
    - tblSegments
    - tblSegmentValues
    - tblDataObjectCodes
    - fiscal years / versions
    - strategic config/setup metadata such as:
        tblSbDimensionAttribute
        tblSbDimensionAttributeOption
        tblSbFiscalAssumption
        tblSbFiscalPeriodConfig
        tblSbPhasingProfile
    - any base/reference/source tables outside the strategic module

    Recommended use:
    1. Run as-is and review preview counts.
    2. Set @ExecuteDelete = 1 and rerun.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT 'Running post-setup Strategy reset against database: ' + DB_NAME();

DECLARE @ExecuteDelete BIT = 0;

IF OBJECT_ID('tempdb..#ResetCounts') IS NOT NULL DROP TABLE #ResetCounts;

CREATE TABLE #ResetCounts (
    SortOrder INT NOT NULL,
    Item NVARCHAR(140) NOT NULL,
    TotalRows INT NOT NULL
);

IF OBJECT_ID('dbo.tblSbFundingSubmissionAttachment', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (10, N'Funding Submission Attachments', (SELECT COUNT(*) FROM dbo.tblSbFundingSubmissionAttachment));

IF OBJECT_ID('dbo.tblSbFundingSubmissionHistory', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (20, N'Funding Submission History', (SELECT COUNT(*) FROM dbo.tblSbFundingSubmissionHistory));

IF OBJECT_ID('dbo.tblSbFundingSubmissionLine', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (30, N'Funding Submission Lines', (SELECT COUNT(*) FROM dbo.tblSbFundingSubmissionLine));

IF OBJECT_ID('dbo.tblSbFundingSubmission', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (40, N'Funding Submissions', (SELECT COUNT(*) FROM dbo.tblSbFundingSubmission));

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (50, N'Resource Envelope', (SELECT COUNT(*) FROM dbo.tblSbResourceEnvelope));

IF OBJECT_ID('dbo.tblSbCeiling', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (60, N'Strategic Ceilings', (SELECT COUNT(*) FROM dbo.tblSbCeiling));

IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (70, N'Program Risk Links', (SELECT COUNT(*) FROM dbo.tblSbProgramRisk));

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (80, N'Fiscal Risks', (SELECT COUNT(*) FROM dbo.tblSbFiscalRisk));

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (90, N'Narratives', (SELECT COUNT(*) FROM dbo.tblSbNarrative));

IF OBJECT_ID('dbo.tblSbActivityBudget', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (100, N'Activity Budgets', (SELECT COUNT(*) FROM dbo.tblSbActivityBudget));

IF OBJECT_ID('dbo.tblSbOutputIndicator', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (110, N'Output Indicator Links', (SELECT COUNT(*) FROM dbo.tblSbOutputIndicator));

IF OBJECT_ID('dbo.tblSbIndicatorTarget', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (120, N'Indicator Targets', (SELECT COUNT(*) FROM dbo.tblSbIndicatorTarget));

IF OBJECT_ID('dbo.tblSbObjectiveIndicator', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (130, N'Objective Indicator Links', (SELECT COUNT(*) FROM dbo.tblSbObjectiveIndicator));

IF OBJECT_ID('dbo.tblSbObjectiveGoal', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (140, N'Objective Goal Links', (SELECT COUNT(*) FROM dbo.tblSbObjectiveGoal));

IF OBJECT_ID('dbo.tblSbProgramOrgLink', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (150, N'Program Org Links', (SELECT COUNT(*) FROM dbo.tblSbProgramOrgLink));

IF OBJECT_ID('dbo.tblSbProjectProgramLink', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (160, N'Project Program Links', (SELECT COUNT(*) FROM dbo.tblSbProjectProgramLink));

IF OBJECT_ID('dbo.tblSbProjectObjectiveLink', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (170, N'Project Objective Links', (SELECT COUNT(*) FROM dbo.tblSbProjectObjectiveLink));

IF OBJECT_ID('dbo.tblSbProjectOrgUnitLink', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (180, N'Project Org Unit Links', (SELECT COUNT(*) FROM dbo.tblSbProjectOrgUnitLink));

IF OBJECT_ID('dbo.tblSbProjectSourceMap', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (190, N'Project Source Maps', (SELECT COUNT(*) FROM dbo.tblSbProjectSourceMap));

IF OBJECT_ID('dbo.tblSbSegmentPublishRequestLine', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (200, N'Segment Publish Request Lines', (SELECT COUNT(*) FROM dbo.tblSbSegmentPublishRequestLine));

IF OBJECT_ID('dbo.tblSbSegmentPublishRequest', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (210, N'Segment Publish Requests', (SELECT COUNT(*) FROM dbo.tblSbSegmentPublishRequest));

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (220, N'Workflow History', (SELECT COUNT(*) FROM dbo.tblSbVersionWorkflowHistory));

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (230, N'Workflow State', (SELECT COUNT(*) FROM dbo.tblSbVersionWorkflow));

IF OBJECT_ID('dbo.tblSbDimensionAttributeValue', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (240, N'Custom Attribute Values', (SELECT COUNT(*) FROM dbo.tblSbDimensionAttributeValue));

IF OBJECT_ID('dbo.tblSbActivity', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (250, N'Activities', (SELECT COUNT(*) FROM dbo.tblSbActivity));

IF OBJECT_ID('dbo.tblSbOutput', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (260, N'Outputs', (SELECT COUNT(*) FROM dbo.tblSbOutput));

IF OBJECT_ID('dbo.tblSbIndicator', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (270, N'Indicators', (SELECT COUNT(*) FROM dbo.tblSbIndicator));

IF OBJECT_ID('dbo.tblSbObjective', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (280, N'Objectives', (SELECT COUNT(*) FROM dbo.tblSbObjective));

IF OBJECT_ID('dbo.tblSbSubProgram', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (290, N'SubPrograms', (SELECT COUNT(*) FROM dbo.tblSbSubProgram));

IF OBJECT_ID('dbo.tblSbProgram', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (300, N'Programs', (SELECT COUNT(*) FROM dbo.tblSbProgram));

IF OBJECT_ID('dbo.tblSbProject', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (310, N'Projects', (SELECT COUNT(*) FROM dbo.tblSbProject));

IF OBJECT_ID('dbo.tblSbFundingSource', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (320, N'Funding Sources', (SELECT COUNT(*) FROM dbo.tblSbFundingSource));

IF OBJECT_ID('dbo.tblSbFundingType', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (330, N'Funding Types', (SELECT COUNT(*) FROM dbo.tblSbFundingType));

IF OBJECT_ID('dbo.tblSbEconomicItem', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (340, N'Economic Items', (SELECT COUNT(*) FROM dbo.tblSbEconomicItem));

IF OBJECT_ID('dbo.tblSbGoal', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (350, N'Goals', (SELECT COUNT(*) FROM dbo.tblSbGoal));

IF OBJECT_ID('dbo.tblSbStrategicPillar', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (360, N'Strategic Pillars', (SELECT COUNT(*) FROM dbo.tblSbStrategicPillar));

IF OBJECT_ID('dbo.tblSbSector', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (370, N'Sectors', (SELECT COUNT(*) FROM dbo.tblSbSector));

IF OBJECT_ID('dbo.tblSbOrgUnit', 'U') IS NOT NULL
    INSERT INTO #ResetCounts VALUES (380, N'Org Units', (SELECT COUNT(*) FROM dbo.tblSbOrgUnit));

PRINT 'Preview counts for full Strategy module reset after setup';
SELECT Item, TotalRows
FROM #ResetCounts
ORDER BY SortOrder;

IF @ExecuteDelete = 0
BEGIN
    PRINT 'Preview only. Set @ExecuteDelete = 1 to execute the reset.';
    RETURN;
END;

BEGIN TRANSACTION;

IF OBJECT_ID('dbo.tblSbFundingSubmissionAttachment', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbFundingSubmissionAttachment;

IF OBJECT_ID('dbo.tblSbFundingSubmissionHistory', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbFundingSubmissionHistory;

IF OBJECT_ID('dbo.tblSbFundingSubmissionLine', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbFundingSubmissionLine;

IF OBJECT_ID('dbo.tblSbFundingSubmission', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbFundingSubmission;

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbResourceEnvelope;

IF OBJECT_ID('dbo.tblSbCeiling', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbCeiling;

IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProgramRisk;

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbFiscalRisk;

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbNarrative;

IF OBJECT_ID('dbo.tblSbActivityBudget', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbActivityBudget;

IF OBJECT_ID('dbo.tblSbOutputIndicator', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbOutputIndicator;

IF OBJECT_ID('dbo.tblSbIndicatorTarget', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbIndicatorTarget;

IF OBJECT_ID('dbo.tblSbObjectiveIndicator', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbObjectiveIndicator;

IF OBJECT_ID('dbo.tblSbObjectiveGoal', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbObjectiveGoal;

IF OBJECT_ID('dbo.tblSbProgramOrgLink', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProgramOrgLink;

IF OBJECT_ID('dbo.tblSbProjectProgramLink', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProjectProgramLink;

IF OBJECT_ID('dbo.tblSbProjectObjectiveLink', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProjectObjectiveLink;

IF OBJECT_ID('dbo.tblSbProjectOrgUnitLink', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProjectOrgUnitLink;

IF OBJECT_ID('dbo.tblSbProjectSourceMap', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProjectSourceMap;

IF OBJECT_ID('dbo.tblSbSegmentPublishRequestLine', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbSegmentPublishRequestLine;

IF OBJECT_ID('dbo.tblSbSegmentPublishRequest', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbSegmentPublishRequest;

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbVersionWorkflowHistory;

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbVersionWorkflow;

IF OBJECT_ID('dbo.tblSbDimensionAttributeValue', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbDimensionAttributeValue;

IF OBJECT_ID('dbo.tblSbActivity', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbActivity;

IF OBJECT_ID('dbo.tblSbOutput', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbOutput;

IF OBJECT_ID('dbo.tblSbIndicator', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbIndicator;

IF OBJECT_ID('dbo.tblSbObjective', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbObjective;

IF OBJECT_ID('dbo.tblSbSubProgram', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbSubProgram;

IF OBJECT_ID('dbo.tblSbProgram', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProgram;

IF OBJECT_ID('dbo.tblSbProject', 'U') IS NOT NULL
    DELETE FROM dbo.tblSbProject;

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

COMMIT TRANSACTION;

PRINT 'Full Strategy module reset after setup completed.';
