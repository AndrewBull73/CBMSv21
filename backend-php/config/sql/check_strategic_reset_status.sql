/*
    Quick post-reset verification for Strategy module cleanup.

    This script reports row counts for the main Strategy input/transaction tables
    that should normally be zero after a clean retest reset.

    Optional:
    - set @ScopeMode = N'CONTEXT' with FiscalYearID / VersionID to verify one context
    - otherwise use ALL_INPUT to see total rows across the module
*/

SET NOCOUNT ON;

PRINT 'Running Strategy reset verification against database: ' + DB_NAME();

DECLARE @ScopeMode NVARCHAR(20) = N'ALL_INPUT'; -- N'ALL_INPUT' or N'CONTEXT'
DECLARE @FiscalYearID INT = NULL;
DECLARE @VersionID INT = NULL;

SET @ScopeMode = UPPER(LTRIM(RTRIM(@ScopeMode)));

IF @ScopeMode NOT IN (N'ALL_INPUT', N'CONTEXT')
BEGIN
    THROW 50000, 'Invalid @ScopeMode. Use ALL_INPUT or CONTEXT.', 1;
END;

IF @ScopeMode = N'CONTEXT' AND (@FiscalYearID IS NULL OR @VersionID IS NULL)
BEGIN
    THROW 50000, 'FiscalYearID and VersionID are required when @ScopeMode = CONTEXT.', 1;
END;

DECLARE @Results TABLE (
    SortOrder INT NOT NULL,
    Item NVARCHAR(140) NOT NULL,
    RowCount INT NOT NULL
);

IF OBJECT_ID('dbo.tblSbFundingSubmissionAttachment', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 10, N'Funding Submission Attachments', COUNT(*)
    FROM dbo.tblSbFundingSubmissionAttachment a
    WHERE @ScopeMode = N'ALL_INPUT'
       OR EXISTS (
            SELECT 1
            FROM dbo.tblSbFundingSubmission s
            WHERE s.StrategicFundingSubmissionID = a.StrategicFundingSubmissionID
              AND s.FiscalYearID = @FiscalYearID
              AND s.VersionID = @VersionID
       );

IF OBJECT_ID('dbo.tblSbFundingSubmissionHistory', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 20, N'Funding Submission History', COUNT(*)
    FROM dbo.tblSbFundingSubmissionHistory h
    WHERE @ScopeMode = N'ALL_INPUT'
       OR EXISTS (
            SELECT 1
            FROM dbo.tblSbFundingSubmission s
            WHERE s.StrategicFundingSubmissionID = h.StrategicFundingSubmissionID
              AND s.FiscalYearID = @FiscalYearID
              AND s.VersionID = @VersionID
       );

IF OBJECT_ID('dbo.tblSbFundingSubmissionLine', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 30, N'Funding Submission Lines', COUNT(*)
    FROM dbo.tblSbFundingSubmissionLine l
    WHERE @ScopeMode = N'ALL_INPUT'
       OR EXISTS (
            SELECT 1
            FROM dbo.tblSbFundingSubmission s
            WHERE s.StrategicFundingSubmissionID = l.StrategicFundingSubmissionID
              AND s.FiscalYearID = @FiscalYearID
              AND s.VersionID = @VersionID
       );

IF OBJECT_ID('dbo.tblSbFundingSubmission', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 40, N'Funding Submissions', COUNT(*)
    FROM dbo.tblSbFundingSubmission s
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (s.FiscalYearID = @FiscalYearID AND s.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 50, N'Resource Envelope', COUNT(*)
    FROM dbo.tblSbResourceEnvelope re
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (re.FiscalYearID = @FiscalYearID AND re.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbCeiling', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 60, N'Strategic Ceilings', COUNT(*)
    FROM dbo.tblSbCeiling c
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (c.FiscalYearID = @FiscalYearID AND c.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 70, N'Narratives', COUNT(*)
    FROM dbo.tblSbNarrative n
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (n.FiscalYearID = @FiscalYearID AND n.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 80, N'Fiscal Risks', COUNT(*)
    FROM dbo.tblSbFiscalRisk;

IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 90, N'Program Risk Links', COUNT(*)
    FROM dbo.tblSbProgramRisk;

IF OBJECT_ID('dbo.tblSbIndicatorTarget', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 100, N'Indicator Targets', COUNT(*)
    FROM dbo.tblSbIndicatorTarget t
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (t.FiscalYearID = @FiscalYearID AND t.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbActivityBudget', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 110, N'Activity Budgets', COUNT(*)
    FROM dbo.tblSbActivityBudget b
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (b.FiscalYearID = @FiscalYearID AND b.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 120, N'Workflow History', COUNT(*)
    FROM dbo.tblSbVersionWorkflowHistory h
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (h.FiscalYearID = @FiscalYearID AND h.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 130, N'Workflow State', COUNT(*)
    FROM dbo.tblSbVersionWorkflow w
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (w.FiscalYearID = @FiscalYearID AND w.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbSegmentPublishRequestLine', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 140, N'Segment Publish Request Lines', COUNT(*)
    FROM dbo.tblSbSegmentPublishRequestLine l
    WHERE @ScopeMode = N'ALL_INPUT'
       OR EXISTS (
            SELECT 1
            FROM dbo.tblSbSegmentPublishRequest r
            WHERE r.StrategicSegmentPublishRequestID = l.StrategicSegmentPublishRequestID
              AND r.FiscalYearID = @FiscalYearID
              AND ISNULL(r.VersionID, @VersionID) = @VersionID
       );

IF OBJECT_ID('dbo.tblSbSegmentPublishRequest', 'U') IS NOT NULL
    INSERT INTO @Results
    SELECT 150, N'Segment Publish Requests', COUNT(*)
    FROM dbo.tblSbSegmentPublishRequest r
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (r.FiscalYearID = @FiscalYearID AND ISNULL(r.VersionID, @VersionID) = @VersionID);

SELECT
    Item,
    RowCount,
    CASE WHEN RowCount = 0 THEN N'CLEAR' ELSE N'REMAINING' END AS ResetStatus
FROM @Results
ORDER BY SortOrder;
