/*
    Reset Strategy module user input data for retesting without removing setup/configuration.

    What this script removes:
    - funding submissions, lines, history, and attachments
    - resource envelope rows
    - strategic ceilings
    - narratives
    - indicator targets
    - activity budgets
    - version workflow state/history
    - segment publication requests/lines
    - optionally, fiscal risks and program risk links

    What this script does NOT remove:
    - tblSbSegmentConfig
    - tblSegments
    - tblSegmentValues
    - setup/master dimensions such as sectors, programs, subprograms, projects,
      outputs, activities, funding types, funding sources, economic items, objectives
    - custom attribute definitions or setup metadata

    Scope options:
    - ALL_INPUT:
        Clears all Strategy input data listed above across all fiscal years/versions.
        This is the cleanest full retest reset.
    - CONTEXT:
        Clears only rows tied to the selected FiscalYearID / VersionID where those
        scope columns exist. Fiscal risks and program risk links are skipped in this
        mode because they are not fiscal/version scoped.

    Recommended use:
    1. Run the script as-is first and review the preview counts.
    2. Set @ExecuteDelete = 1.
    3. Choose @ScopeMode = N'ALL_INPUT' for a full retest reset, or N'CONTEXT'
       with FiscalYearID / VersionID populated for a narrower cleanup.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;

PRINT 'Running strategic user input reset against database: ' + DB_NAME();

DECLARE @ExecuteDelete BIT = 0;
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

IF OBJECT_ID('tempdb..#ResetCounts') IS NOT NULL DROP TABLE #ResetCounts;
IF OBJECT_ID('tempdb..#TargetSubmissions') IS NOT NULL DROP TABLE #TargetSubmissions;
IF OBJECT_ID('tempdb..#TargetSubmissionLines') IS NOT NULL DROP TABLE #TargetSubmissionLines;
IF OBJECT_ID('tempdb..#TargetSubmissionHistory') IS NOT NULL DROP TABLE #TargetSubmissionHistory;
IF OBJECT_ID('tempdb..#TargetSubmissionAttachments') IS NOT NULL DROP TABLE #TargetSubmissionAttachments;
IF OBJECT_ID('tempdb..#TargetPublishRequests') IS NOT NULL DROP TABLE #TargetPublishRequests;
IF OBJECT_ID('tempdb..#TargetPublishRequestLines') IS NOT NULL DROP TABLE #TargetPublishRequestLines;
IF OBJECT_ID('tempdb..#TargetWorkflow') IS NOT NULL DROP TABLE #TargetWorkflow;
IF OBJECT_ID('tempdb..#TargetWorkflowHistory') IS NOT NULL DROP TABLE #TargetWorkflowHistory;

CREATE TABLE #ResetCounts (
    SortOrder INT NOT NULL,
    Item NVARCHAR(120) NOT NULL,
    TotalRows INT NOT NULL,
    ScopeNote NVARCHAR(200) NULL
);

CREATE TABLE #TargetSubmissions (ID INT NOT NULL PRIMARY KEY);
CREATE TABLE #TargetSubmissionLines (ID INT NOT NULL PRIMARY KEY);
CREATE TABLE #TargetSubmissionHistory (ID INT NOT NULL PRIMARY KEY);
CREATE TABLE #TargetSubmissionAttachments (ID INT NOT NULL PRIMARY KEY);
CREATE TABLE #TargetPublishRequests (ID INT NOT NULL PRIMARY KEY);
CREATE TABLE #TargetPublishRequestLines (ID INT NOT NULL PRIMARY KEY);
CREATE TABLE #TargetWorkflow (ID INT NOT NULL PRIMARY KEY);
CREATE TABLE #TargetWorkflowHistory (ID INT NOT NULL PRIMARY KEY);

IF OBJECT_ID('dbo.tblSbFundingSubmission', 'U') IS NOT NULL
BEGIN
    INSERT INTO #TargetSubmissions (ID)
    SELECT s.StrategicFundingSubmissionID
    FROM dbo.tblSbFundingSubmission s
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (s.FiscalYearID = @FiscalYearID AND s.VersionID = @VersionID);
END;

IF OBJECT_ID('dbo.tblSbFundingSubmissionLine', 'U') IS NOT NULL
BEGIN
    INSERT INTO #TargetSubmissionLines (ID)
    SELECT l.StrategicFundingSubmissionLineID
    FROM dbo.tblSbFundingSubmissionLine l
    INNER JOIN #TargetSubmissions s ON s.ID = l.StrategicFundingSubmissionID;
END;

IF OBJECT_ID('dbo.tblSbFundingSubmissionHistory', 'U') IS NOT NULL
BEGIN
    INSERT INTO #TargetSubmissionHistory (ID)
    SELECT h.StrategicFundingSubmissionHistoryID
    FROM dbo.tblSbFundingSubmissionHistory h
    INNER JOIN #TargetSubmissions s ON s.ID = h.StrategicFundingSubmissionID;
END;

IF OBJECT_ID('dbo.tblSbFundingSubmissionAttachment', 'U') IS NOT NULL
BEGIN
    INSERT INTO #TargetSubmissionAttachments (ID)
    SELECT a.StrategicFundingSubmissionAttachmentID
    FROM dbo.tblSbFundingSubmissionAttachment a
    INNER JOIN #TargetSubmissions s ON s.ID = a.StrategicFundingSubmissionID;
END;

IF OBJECT_ID('dbo.tblSbSegmentPublishRequest', 'U') IS NOT NULL
BEGIN
    INSERT INTO #TargetPublishRequests (ID)
    SELECT r.StrategicSegmentPublishRequestID
    FROM dbo.tblSbSegmentPublishRequest r
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (r.FiscalYearID = @FiscalYearID AND ISNULL(r.VersionID, @VersionID) = @VersionID);
END;

IF OBJECT_ID('dbo.tblSbSegmentPublishRequestLine', 'U') IS NOT NULL
BEGIN
    INSERT INTO #TargetPublishRequestLines (ID)
    SELECT l.StrategicSegmentPublishRequestLineID
    FROM dbo.tblSbSegmentPublishRequestLine l
    INNER JOIN #TargetPublishRequests r ON r.ID = l.StrategicSegmentPublishRequestID;
END;

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NOT NULL
BEGIN
    INSERT INTO #TargetWorkflow (ID)
    SELECT w.StrategicVersionWorkflowID
    FROM dbo.tblSbVersionWorkflow w
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (w.FiscalYearID = @FiscalYearID AND w.VersionID = @VersionID);
END;

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NOT NULL
BEGIN
    INSERT INTO #TargetWorkflowHistory (ID)
    SELECT h.StrategicWorkflowHistoryID
    FROM dbo.tblSbVersionWorkflowHistory h
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (h.FiscalYearID = @FiscalYearID AND h.VersionID = @VersionID);
END;

INSERT INTO #ResetCounts (SortOrder, Item, TotalRows, ScopeNote)
SELECT 10, N'Funding Submission Attachments', COUNT(*), NULL
FROM #TargetSubmissionAttachments;

INSERT INTO #ResetCounts (SortOrder, Item, TotalRows, ScopeNote)
SELECT 20, N'Funding Submission History', COUNT(*), NULL
FROM #TargetSubmissionHistory;

INSERT INTO #ResetCounts (SortOrder, Item, TotalRows, ScopeNote)
SELECT 30, N'Funding Submission Lines', COUNT(*), NULL
FROM #TargetSubmissionLines;

INSERT INTO #ResetCounts (SortOrder, Item, TotalRows, ScopeNote)
SELECT 40, N'Funding Submissions', COUNT(*), NULL
FROM #TargetSubmissions;

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NOT NULL
    INSERT INTO #ResetCounts
    SELECT
        50,
        N'Resource Envelope',
        COUNT(*),
        NULL
    FROM dbo.tblSbResourceEnvelope re
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (re.FiscalYearID = @FiscalYearID AND re.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbCeiling', 'U') IS NOT NULL
    INSERT INTO #ResetCounts
    SELECT
        60,
        N'Strategic Ceilings',
        COUNT(*),
        NULL
    FROM dbo.tblSbCeiling c
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (c.FiscalYearID = @FiscalYearID AND c.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NOT NULL
    INSERT INTO #ResetCounts
    SELECT
        70,
        N'Narratives',
        COUNT(*),
        NULL
    FROM dbo.tblSbNarrative n
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (n.FiscalYearID = @FiscalYearID AND n.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbIndicatorTarget', 'U') IS NOT NULL
    INSERT INTO #ResetCounts
    SELECT
        80,
        N'Indicator Targets',
        COUNT(*),
        NULL
    FROM dbo.tblSbIndicatorTarget t
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (t.FiscalYearID = @FiscalYearID AND t.VersionID = @VersionID);

IF OBJECT_ID('dbo.tblSbActivityBudget', 'U') IS NOT NULL
    INSERT INTO #ResetCounts
    SELECT
        90,
        N'Activity Budgets',
        COUNT(*),
        NULL
    FROM dbo.tblSbActivityBudget b
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (b.FiscalYearID = @FiscalYearID AND b.VersionID = @VersionID);

INSERT INTO #ResetCounts (SortOrder, Item, TotalRows, ScopeNote)
SELECT 100, N'Workflow History', COUNT(*), NULL
FROM #TargetWorkflowHistory;

INSERT INTO #ResetCounts (SortOrder, Item, TotalRows, ScopeNote)
SELECT 110, N'Workflow State', COUNT(*), NULL
FROM #TargetWorkflow;

INSERT INTO #ResetCounts (SortOrder, Item, TotalRows, ScopeNote)
SELECT 120, N'Segment Publish Request Lines', COUNT(*), NULL
FROM #TargetPublishRequestLines;

INSERT INTO #ResetCounts (SortOrder, Item, TotalRows, ScopeNote)
SELECT 130, N'Segment Publish Requests', COUNT(*), NULL
FROM #TargetPublishRequests;

IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NOT NULL
BEGIN
    INSERT INTO #ResetCounts
    SELECT
        140,
        N'Program Risk Links',
        CASE WHEN @ScopeMode = N'ALL_INPUT' THEN COUNT(*) ELSE 0 END,
        CASE WHEN @ScopeMode = N'ALL_INPUT' THEN NULL ELSE N'Context mode skips non-scoped table' END
    FROM dbo.tblSbProgramRisk;
END;

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NOT NULL
BEGIN
    INSERT INTO #ResetCounts
    SELECT
        150,
        N'Fiscal Risks',
        CASE WHEN @ScopeMode = N'ALL_INPUT' THEN COUNT(*) ELSE 0 END,
        CASE WHEN @ScopeMode = N'ALL_INPUT' THEN NULL ELSE N'Context mode skips non-scoped table' END
    FROM dbo.tblSbFiscalRisk;
END;

PRINT 'Preview counts for strategic user input reset';
SELECT Item, TotalRows, ScopeNote
FROM #ResetCounts
ORDER BY SortOrder;

IF @ExecuteDelete = 0
BEGIN
    PRINT 'Preview only. Set @ExecuteDelete = 1 to execute the reset.';
    RETURN;
END;

BEGIN TRANSACTION;

IF OBJECT_ID('dbo.tblSbFundingSubmissionAttachment', 'U') IS NOT NULL
BEGIN
    DELETE a
    FROM dbo.tblSbFundingSubmissionAttachment a
    INNER JOIN #TargetSubmissionAttachments t ON t.ID = a.StrategicFundingSubmissionAttachmentID;
END;

IF OBJECT_ID('dbo.tblSbFundingSubmissionHistory', 'U') IS NOT NULL
BEGIN
    DELETE h
    FROM dbo.tblSbFundingSubmissionHistory h
    INNER JOIN #TargetSubmissionHistory t ON t.ID = h.StrategicFundingSubmissionHistoryID;
END;

IF OBJECT_ID('dbo.tblSbFundingSubmissionLine', 'U') IS NOT NULL
BEGIN
    DELETE l
    FROM dbo.tblSbFundingSubmissionLine l
    INNER JOIN #TargetSubmissionLines t ON t.ID = l.StrategicFundingSubmissionLineID;
END;

IF OBJECT_ID('dbo.tblSbFundingSubmission', 'U') IS NOT NULL
BEGIN
    DELETE s
    FROM dbo.tblSbFundingSubmission s
    INNER JOIN #TargetSubmissions t ON t.ID = s.StrategicFundingSubmissionID;
END;

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NOT NULL
BEGIN
    DELETE re
    FROM dbo.tblSbResourceEnvelope re
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (re.FiscalYearID = @FiscalYearID AND re.VersionID = @VersionID);
END;

IF OBJECT_ID('dbo.tblSbCeiling', 'U') IS NOT NULL
BEGIN
    DELETE c
    FROM dbo.tblSbCeiling c
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (c.FiscalYearID = @FiscalYearID AND c.VersionID = @VersionID);
END;

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NOT NULL
BEGIN
    DELETE n
    FROM dbo.tblSbNarrative n
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (n.FiscalYearID = @FiscalYearID AND n.VersionID = @VersionID);
END;

IF OBJECT_ID('dbo.tblSbIndicatorTarget', 'U') IS NOT NULL
BEGIN
    DELETE t
    FROM dbo.tblSbIndicatorTarget t
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (t.FiscalYearID = @FiscalYearID AND t.VersionID = @VersionID);
END;

IF OBJECT_ID('dbo.tblSbActivityBudget', 'U') IS NOT NULL
BEGIN
    DELETE b
    FROM dbo.tblSbActivityBudget b
    WHERE @ScopeMode = N'ALL_INPUT'
       OR (b.FiscalYearID = @FiscalYearID AND b.VersionID = @VersionID);
END;

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NOT NULL
BEGIN
    DELETE h
    FROM dbo.tblSbVersionWorkflowHistory h
    INNER JOIN #TargetWorkflowHistory t ON t.ID = h.StrategicWorkflowHistoryID;
END;

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NOT NULL
BEGIN
    DELETE w
    FROM dbo.tblSbVersionWorkflow w
    INNER JOIN #TargetWorkflow t ON t.ID = w.StrategicVersionWorkflowID;
END;

IF OBJECT_ID('dbo.tblSbSegmentPublishRequestLine', 'U') IS NOT NULL
BEGIN
    DELETE l
    FROM dbo.tblSbSegmentPublishRequestLine l
    INNER JOIN #TargetPublishRequestLines t ON t.ID = l.StrategicSegmentPublishRequestLineID;
END;

IF OBJECT_ID('dbo.tblSbSegmentPublishRequest', 'U') IS NOT NULL
BEGIN
    DELETE r
    FROM dbo.tblSbSegmentPublishRequest r
    INNER JOIN #TargetPublishRequests t ON t.ID = r.StrategicSegmentPublishRequestID;
END;

IF @ScopeMode = N'ALL_INPUT'
BEGIN
    IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NOT NULL
        DELETE FROM dbo.tblSbProgramRisk;

    IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NOT NULL
        DELETE FROM dbo.tblSbFiscalRisk;
END;

COMMIT TRANSACTION;

PRINT 'Strategic user input reset completed.';
