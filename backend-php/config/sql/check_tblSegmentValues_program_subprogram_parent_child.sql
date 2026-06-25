USE [CBMSv2_INITTEST];
GO

/*
  Purpose
  -------
  Diagnose parent-child relationship issues between Program and SubProgram
  rows loaded into dbo.tblSegmentValues.

  Defaults match the current strategic segment setup:
      Program    = SegmentNo 4
      SubProgram = SegmentNo 5

  This script is read-only. It reports issues and useful summary counts.
*/

SET NOCOUNT ON;

DECLARE @FiscalYearID INT = 2026;
DECLARE @ProgramSegmentNo INT = 4;
DECLARE @SubProgramSegmentNo INT = 5;

/*
  Set to 1 when each SubProgram should resolve to a Program in the child
  DataObjectCode hierarchy scope. This allows a child row at Cost Centre level
  to resolve to a parent row at Head level.
*/
DECLARE @RequireSameDataObjectCode BIT = 1;

/*
  Set to 1 when child DataObjectCode values are expected to begin with the
  resolved parent DataObjectCode.
*/
DECLARE @CheckSubProgramCodePrefix BIT = 1;

IF OBJECT_ID(N'dbo.tblSegmentValues', N'U') IS NULL
BEGIN
    THROW 50000, 'dbo.tblSegmentValues does not exist.', 1;
END;

IF COL_LENGTH(N'dbo.tblSegmentValues', N'ParentSegmentNo') IS NULL
   OR COL_LENGTH(N'dbo.tblSegmentValues', N'ParentSegmentCode') IS NULL
BEGIN
    THROW 50001, 'dbo.tblSegmentValues must have ParentSegmentNo and ParentSegmentCode columns.', 1;
END;

IF OBJECT_ID(N'tempdb..#ProgramSubProgramIssues') IS NOT NULL
BEGIN
    DROP TABLE #ProgramSubProgramIssues;
END;

IF OBJECT_ID(N'tempdb..#ActiveProgramSubProgramValues') IS NOT NULL
BEGIN
    DROP TABLE #ActiveProgramSubProgramValues;
END;

IF OBJECT_ID(N'tempdb..#SubProgramParentMatches') IS NOT NULL
BEGIN
    DROP TABLE #SubProgramParentMatches;
END;

IF OBJECT_ID(N'tempdb..#DataObjectScope') IS NOT NULL
BEGIN
    DROP TABLE #DataObjectScope;
END;

CREATE TABLE #ProgramSubProgramIssues (
    Severity NVARCHAR(20) NOT NULL,
    IssueType NVARCHAR(100) NOT NULL,
    FiscalYearID INT NULL,
    DataObjectCode NVARCHAR(50) NULL,
    SegmentValueID INT NULL,
    SegmentNo INT NULL,
    SegmentCode NVARCHAR(50) NULL,
    SegmentName NVARCHAR(255) NULL,
    ParentSegmentNo INT NULL,
    ParentSegmentCode NVARCHAR(50) NULL,
    MatchingParentCount INT NULL,
    Detail NVARCHAR(500) NOT NULL
);

SELECT
    sv.SegmentValueID,
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentValueID,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    sv.ActiveFlag
INTO #ActiveProgramSubProgramValues
FROM dbo.tblSegmentValues sv
WHERE sv.FiscalYearID = @FiscalYearID
  AND sv.ActiveFlag = 1
  AND sv.SegmentNo IN (@ProgramSegmentNo, @SubProgramSegmentNo);

;WITH DataObjectScope AS (
    SELECT
        child.SegmentValueID,
        ScopeDataObjectCode = CAST(child.DataObjectCode AS NVARCHAR(50)),
        ScopeDepth = 0
    FROM #ActiveProgramSubProgramValues child
    WHERE child.SegmentNo = @SubProgramSegmentNo

    UNION ALL

    SELECT
        scope.SegmentValueID,
        ScopeDataObjectCode = CAST(doc.DataObjectCodeParent AS NVARCHAR(50)),
        ScopeDepth = scope.ScopeDepth + 1
    FROM DataObjectScope scope
    INNER JOIN dbo.tblDataObjectCodes doc
        ON doc.FiscalYearID = @FiscalYearID
       AND doc.DataObjectCode = scope.ScopeDataObjectCode
    WHERE NULLIF(LTRIM(RTRIM(ISNULL(doc.DataObjectCodeParent, N''))), N'') IS NOT NULL
      AND doc.DataObjectCodeParent <> scope.ScopeDataObjectCode
      AND scope.ScopeDepth < 20
)
SELECT
    SegmentValueID,
    ScopeDataObjectCode,
    ScopeDepth
INTO #DataObjectScope
FROM DataObjectScope;

SELECT
    child.SegmentValueID,
    MatchingParentCount = COUNT(parent.SegmentValueID),
    ActiveParentCount = SUM(CASE WHEN parent.ActiveFlag = 1 THEN 1 ELSE 0 END),
    SameDataObjectParentCount = SUM(CASE
        WHEN parent.ActiveFlag = 1
         AND parent.DataObjectCode = child.DataObjectCode THEN 1
        ELSE 0
    END),
    ScopedParentCount = SUM(CASE
        WHEN parent.ActiveFlag = 1
         AND (
            scope.ScopeDataObjectCode IS NOT NULL
            OR parent.DataObjectCode = child.ParentSegmentCode
            OR parent.SegmentValueID = child.ParentSegmentValueID
            OR (
                LEN(LTRIM(RTRIM(ISNULL(child.DataObjectCode, N'')))) > 2
                AND parent.DataObjectCode = LEFT(
                    LTRIM(RTRIM(ISNULL(child.DataObjectCode, N''))),
                    LEN(LTRIM(RTRIM(ISNULL(child.DataObjectCode, N'')))) - 2
                )
            )
         ) THEN 1
        ELSE 0
    END)
INTO #SubProgramParentMatches
FROM #ActiveProgramSubProgramValues child
LEFT JOIN dbo.tblSegmentValues parent
    ON parent.FiscalYearID = child.FiscalYearID
   AND parent.SegmentNo = child.ParentSegmentNo
   AND parent.SegmentCode = child.ParentSegmentCode
LEFT JOIN #DataObjectScope scope
    ON scope.SegmentValueID = child.SegmentValueID
   AND scope.ScopeDataObjectCode = parent.DataObjectCode
WHERE child.SegmentNo = @SubProgramSegmentNo
GROUP BY child.SegmentValueID;

INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'ERROR',
    N'SubProgram parent segment number is missing or wrong',
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentValueID,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    NULL,
    N'Active SubProgram rows must have ParentSegmentNo = Program SegmentNo.'
FROM #ActiveProgramSubProgramValues sv
WHERE sv.SegmentNo = @SubProgramSegmentNo
  AND (sv.ParentSegmentNo IS NULL OR sv.ParentSegmentNo <> @ProgramSegmentNo);

INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'ERROR',
    N'SubProgram parent code is missing',
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentValueID,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    NULL,
    N'Active SubProgram rows must have a non-blank ParentSegmentCode.'
FROM #ActiveProgramSubProgramValues sv
WHERE sv.SegmentNo = @SubProgramSegmentNo
  AND NULLIF(LTRIM(RTRIM(ISNULL(sv.ParentSegmentCode, N''))), N'') IS NULL;

INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'ERROR',
    N'Parent Program not found',
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentValueID,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    ISNULL(pm.ActiveParentCount, 0),
    N'No active Program row exists for the SubProgram ParentSegmentNo/ParentSegmentCode.'
FROM #ActiveProgramSubProgramValues sv
LEFT JOIN #SubProgramParentMatches pm
    ON pm.SegmentValueID = sv.SegmentValueID
WHERE sv.SegmentNo = @SubProgramSegmentNo
  AND sv.ParentSegmentNo = @ProgramSegmentNo
  AND NULLIF(LTRIM(RTRIM(ISNULL(sv.ParentSegmentCode, N''))), N'') IS NOT NULL
  AND ISNULL(pm.ActiveParentCount, 0) = 0;

INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'ERROR',
    N'Parent Program not found in data-object scope',
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentValueID,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    ISNULL(pm.ScopedParentCount, 0),
    N'No active Program row exists in the SubProgram DataObjectCode, one of its parent DataObjectCodes, the explicit ParentSegmentValueID, the derived parent DataObjectCode, or the parent link code scope.'
FROM #ActiveProgramSubProgramValues sv
LEFT JOIN #SubProgramParentMatches pm
    ON pm.SegmentValueID = sv.SegmentValueID
WHERE @RequireSameDataObjectCode = 1
  AND sv.SegmentNo = @SubProgramSegmentNo
  AND sv.ParentSegmentNo = @ProgramSegmentNo
  AND NULLIF(LTRIM(RTRIM(ISNULL(sv.ParentSegmentCode, N''))), N'') IS NOT NULL
  AND ISNULL(pm.ScopedParentCount, 0) = 0;

INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'ERROR',
    N'ParentSegmentValueID is missing',
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentValueID,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    ISNULL(pm.ScopedParentCount, ISNULL(pm.ActiveParentCount, 0)),
    N'ParentSegmentNo and ParentSegmentCode are populated, but ParentSegmentValueID is not set. Run Resolve Parent Links so the SubProgram points to one exact Program row.'
FROM #ActiveProgramSubProgramValues sv
LEFT JOIN #SubProgramParentMatches pm
    ON pm.SegmentValueID = sv.SegmentValueID
WHERE sv.SegmentNo = @SubProgramSegmentNo
  AND sv.ParentSegmentNo = @ProgramSegmentNo
  AND NULLIF(LTRIM(RTRIM(ISNULL(sv.ParentSegmentCode, N''))), N'') IS NOT NULL
  AND sv.ParentSegmentValueID IS NULL;

INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'WARNING',
    N'Parent Program reference is ambiguous',
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentValueID,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    ISNULL(pm.ActiveParentCount, 0),
    N'More than one active Program row matches this parent code across data objects. This can make imports resolve the wrong parent unless DataObjectCode is included.'
FROM #ActiveProgramSubProgramValues sv
INNER JOIN #SubProgramParentMatches pm
  ON pm.SegmentValueID = sv.SegmentValueID
WHERE sv.SegmentNo = @SubProgramSegmentNo
  AND pm.ActiveParentCount > 1
  AND (@RequireSameDataObjectCode = 0 OR pm.ScopedParentCount <> 1);

INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'WARNING',
    N'SubProgram DataObjectCode does not start with parent DataObjectCode',
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentValueID,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    NULL,
    N'SubProgram DataObjectCode is expected to begin with the resolved parent DataObjectCode.'
FROM #ActiveProgramSubProgramValues sv
WHERE @CheckSubProgramCodePrefix = 1
  AND sv.SegmentNo = @SubProgramSegmentNo
  AND NULLIF(LTRIM(RTRIM(ISNULL(sv.ParentSegmentCode, N''))), N'') IS NOT NULL
  AND EXISTS (
        SELECT 1
        FROM dbo.tblSegmentValues parent
        LEFT JOIN #DataObjectScope scope
            ON scope.SegmentValueID = sv.SegmentValueID
           AND scope.ScopeDataObjectCode = parent.DataObjectCode
        WHERE parent.FiscalYearID = sv.FiscalYearID
          AND parent.SegmentNo = sv.ParentSegmentNo
          AND parent.SegmentCode = sv.ParentSegmentCode
          AND parent.ActiveFlag = 1
          AND (
                scope.ScopeDataObjectCode IS NOT NULL
                OR parent.DataObjectCode = sv.ParentSegmentCode
                OR parent.SegmentValueID = sv.ParentSegmentValueID
                OR (
                    LEN(LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N'')))) > 2
                    AND parent.DataObjectCode = LEFT(
                        LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N''))),
                        LEN(LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N'')))) - 2
                    )
                )
          )
  )
  AND NOT EXISTS (
        SELECT 1
        FROM dbo.tblSegmentValues parent
        LEFT JOIN #DataObjectScope scope
            ON scope.SegmentValueID = sv.SegmentValueID
           AND scope.ScopeDataObjectCode = parent.DataObjectCode
        WHERE parent.FiscalYearID = sv.FiscalYearID
          AND parent.SegmentNo = sv.ParentSegmentNo
          AND parent.SegmentCode = sv.ParentSegmentCode
          AND parent.ActiveFlag = 1
          AND (
                scope.ScopeDataObjectCode IS NOT NULL
                OR parent.DataObjectCode = sv.ParentSegmentCode
                OR parent.SegmentValueID = sv.ParentSegmentValueID
                OR (
                    LEN(LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N'')))) > 2
                    AND parent.DataObjectCode = LEFT(
                        LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N''))),
                        LEN(LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N'')))) - 2
                    )
                )
          )
          AND LEFT(LTRIM(RTRIM(ISNULL(sv.DataObjectCode, N''))), LEN(LTRIM(RTRIM(ISNULL(parent.DataObjectCode, N''))))) = LTRIM(RTRIM(ISNULL(parent.DataObjectCode, N'')))
  );

;WITH DuplicateKeys AS (
    SELECT
        FiscalYearID,
        DataObjectCode,
        SegmentNo,
        SegmentCode,
        DuplicateCount = COUNT(*)
    FROM dbo.tblSegmentValues
    WHERE FiscalYearID = @FiscalYearID
      AND ActiveFlag = 1
      AND SegmentNo IN (@ProgramSegmentNo, @SubProgramSegmentNo)
    GROUP BY FiscalYearID, DataObjectCode, SegmentNo, SegmentCode
    HAVING COUNT(*) > 1
)
INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'ERROR',
    N'Duplicate active segment key',
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentValueID,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.SegmentName,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode,
    dk.DuplicateCount,
    N'More than one active row has the same FiscalYearID, DataObjectCode, SegmentNo, and SegmentCode.'
FROM dbo.tblSegmentValues sv
INNER JOIN DuplicateKeys dk
    ON dk.FiscalYearID = sv.FiscalYearID
   AND dk.DataObjectCode = sv.DataObjectCode
   AND dk.SegmentNo = sv.SegmentNo
   AND dk.SegmentCode = sv.SegmentCode
WHERE sv.ActiveFlag = 1;

INSERT INTO #ProgramSubProgramIssues (
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
)
SELECT
    N'WARNING',
    N'ParentSegmentValueID points to a different parent',
    child.FiscalYearID,
    child.DataObjectCode,
    child.SegmentValueID,
    child.SegmentNo,
    child.SegmentCode,
    child.SegmentName,
    child.ParentSegmentNo,
    child.ParentSegmentCode,
    NULL,
    N'ParentSegmentValueID is populated but does not match ParentSegmentNo/ParentSegmentCode.'
FROM dbo.tblSegmentValues child
INNER JOIN dbo.tblSegmentValues parent
    ON parent.SegmentValueID = child.ParentSegmentValueID
WHERE child.FiscalYearID = @FiscalYearID
  AND child.ActiveFlag = 1
  AND child.SegmentNo = @SubProgramSegmentNo
  AND child.ParentSegmentValueID IS NOT NULL
  AND (
        parent.FiscalYearID <> child.FiscalYearID
        OR parent.SegmentNo <> child.ParentSegmentNo
        OR parent.SegmentCode <> child.ParentSegmentCode
        OR (
            @RequireSameDataObjectCode = 1
            AND NOT EXISTS (
                SELECT 1
                FROM #DataObjectScope parentScope
                WHERE parentScope.SegmentValueID = child.SegmentValueID
                  AND parentScope.ScopeDataObjectCode = parent.DataObjectCode
            )
            AND parent.DataObjectCode <> child.ParentSegmentCode
            AND parent.SegmentValueID <> child.ParentSegmentValueID
            AND (
                LEN(LTRIM(RTRIM(ISNULL(child.DataObjectCode, N'')))) <= 2
                OR parent.DataObjectCode <> LEFT(
                    LTRIM(RTRIM(ISNULL(child.DataObjectCode, N''))),
                    LEN(LTRIM(RTRIM(ISNULL(child.DataObjectCode, N'')))) - 2
                )
            )
        )
  );

SELECT
    CheckName = N'Program/SubProgram parent-child diagnostics',
    FiscalYearID = @FiscalYearID,
    ProgramSegmentNo = @ProgramSegmentNo,
    SubProgramSegmentNo = @SubProgramSegmentNo,
    ProgramRows = SUM(CASE WHEN SegmentNo = @ProgramSegmentNo THEN 1 ELSE 0 END),
    SubProgramRows = SUM(CASE WHEN SegmentNo = @SubProgramSegmentNo THEN 1 ELSE 0 END),
    IssueRows = (SELECT COUNT(*) FROM #ProgramSubProgramIssues),
    ErrorRows = (SELECT COUNT(*) FROM #ProgramSubProgramIssues WHERE Severity = N'ERROR'),
    WarningRows = (SELECT COUNT(*) FROM #ProgramSubProgramIssues WHERE Severity = N'WARNING')
FROM dbo.tblSegmentValues
WHERE FiscalYearID = @FiscalYearID
  AND ActiveFlag = 1
  AND SegmentNo IN (@ProgramSegmentNo, @SubProgramSegmentNo);

SELECT
    Severity,
    IssueType,
    IssueRows = COUNT(*)
FROM #ProgramSubProgramIssues
GROUP BY Severity, IssueType
ORDER BY
    CASE Severity WHEN N'ERROR' THEN 1 WHEN N'WARNING' THEN 2 ELSE 3 END,
    IssueType;

SELECT
    Severity,
    IssueType,
    FiscalYearID,
    DataObjectCode,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    MatchingParentCount,
    Detail
FROM #ProgramSubProgramIssues
ORDER BY
    CASE Severity WHEN N'ERROR' THEN 1 WHEN N'WARNING' THEN 2 ELSE 3 END,
    IssueType,
    DataObjectCode,
    SegmentCode,
    SegmentValueID;

SELECT TOP (200)
    SubProgramValueID = child.SegmentValueID,
    child.DataObjectCode,
    SubProgramCode = child.SegmentCode,
    SubProgramName = child.SegmentName,
    child.ParentSegmentNo,
    child.ParentSegmentCode,
    ProgramValueID = parent.SegmentValueID,
    ProgramDataObjectCode = parent.DataObjectCode,
    ProgramCode = parent.SegmentCode,
    ProgramName = parent.SegmentName
FROM dbo.tblSegmentValues child
LEFT JOIN dbo.tblSegmentValues parent
    ON parent.FiscalYearID = child.FiscalYearID
   AND parent.SegmentNo = child.ParentSegmentNo
   AND parent.SegmentCode = child.ParentSegmentCode
   AND parent.ActiveFlag = 1
   AND (
        @RequireSameDataObjectCode = 0
        OR EXISTS (
            SELECT 1
            FROM #DataObjectScope scope
            WHERE scope.SegmentValueID = child.SegmentValueID
              AND scope.ScopeDataObjectCode = parent.DataObjectCode
        )
        OR parent.DataObjectCode = child.ParentSegmentCode
        OR parent.SegmentValueID = child.ParentSegmentValueID
        OR (
            LEN(LTRIM(RTRIM(ISNULL(child.DataObjectCode, N'')))) > 2
            AND parent.DataObjectCode = LEFT(
                LTRIM(RTRIM(ISNULL(child.DataObjectCode, N''))),
                LEN(LTRIM(RTRIM(ISNULL(child.DataObjectCode, N'')))) - 2
            )
        )
   )
WHERE child.FiscalYearID = @FiscalYearID
  AND child.ActiveFlag = 1
  AND child.SegmentNo = @SubProgramSegmentNo
ORDER BY child.DataObjectCode, child.ParentSegmentCode, child.SegmentCode;
GO
