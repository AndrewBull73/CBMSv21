/*
Purpose
-------
Import segment values from CBMSGOL.dbo.tblSegmentValues into the current
database dbo.tblSegmentValues.

Default behavior is PREVIEW ONLY.

How to use
----------
1. Open/run this script while connected to the target database.
2. Review the parameter block.
3. Run once with @PreviewOnly = 1.
4. Set @PreviewOnly = 0 to update/insert.
5. Optional: set @DeleteExistingTargetSegmentRows = 1 for a clean reload of
   the selected segment numbers.

Column mapping
--------------
CBMSGOL.dbo.tblSegmentValues        current database dbo.tblSegmentValues
----------------------------------  -------------------------------------
@TargetFiscalYearID                 FiscalYearID
BusinessAreaCode                    DataObjectCode
SegmentNo                           SegmentNo
SegmentCode                         SegmentCode
SegmentName / SegmentNameL2         SegmentName
SegmentID                           SegmentExternalID
resolved after load                 ParentSegmentValueID
ParentSegmentNo                     ParentSegmentNo
ParentSegmentCode                   ParentSegmentCode
resolved from DataObject hierarchy  ParentSegmentDataObjectCode
Active                              ActiveFlag
UpdatedBy                           UpdatedBy
DateUpdated                         UpdatedDate
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

DECLARE @SourceDatabase sysname = N'CBMSGOL';
DECLARE @SourceBudgetID int = 8;
DECLARE @TargetFiscalYearID int = 2026;
DECLARE @OnlySegmentNo int = NULL;
DECLARE @DefaultUpdatedBy int = 1;

DECLARE @PreviewOnly bit = 1;
DECLARE @DeleteExistingTargetSegmentRows bit = 0;
DECLARE @IncludeInactive bit = 1;
DECLARE @ResolveParentSegmentValueID bit = 1;

/*
  Optional segment filter.

  If @OnlySegmentNo is set, only that segment is loaded.
  Otherwise, add rows below to load selected segments.
  If this table is empty and @OnlySegmentNo is NULL, every source segment found
  for @SourceBudgetID is loaded.

  Examples:
      INSERT INTO #SelectedSegmentNos VALUES (1), (2), (3);
      INSERT INTO #SelectedSegmentNos VALUES (4), (5), (13), (14);
*/
IF OBJECT_ID(N'tempdb..#SelectedSegmentNos') IS NOT NULL DROP TABLE #SelectedSegmentNos;
CREATE TABLE #SelectedSegmentNos (SegmentNo int NOT NULL PRIMARY KEY);

IF @OnlySegmentNo IS NOT NULL
BEGIN
    INSERT INTO #SelectedSegmentNos (SegmentNo)
    VALUES (@OnlySegmentNo);
END;

IF DB_ID(@SourceDatabase) IS NULL
BEGIN
    THROW 50000, 'Source database CBMSGOL does not exist or is not visible to this login.', 1;
END;

IF OBJECT_ID(N'CBMSGOL.dbo.tblSegmentValues', N'U') IS NULL
BEGIN
    THROW 50001, 'Source table CBMSGOL.dbo.tblSegmentValues does not exist or is not visible to this login.', 1;
END;

IF OBJECT_ID(N'dbo.tblSegmentValues', N'U') IS NULL
BEGIN
    THROW 50002, 'Target dbo.tblSegmentValues does not exist in the current database.', 1;
END;

IF OBJECT_ID(N'dbo.tblSegments', N'U') IS NULL
BEGIN
    THROW 50003, 'Target dbo.tblSegments does not exist in the current database.', 1;
END;

IF COL_LENGTH(N'dbo.tblSegmentValues', N'ParentSegmentDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSegmentValues
    ADD ParentSegmentDataObjectCode nvarchar(50) NULL;
END;

IF OBJECT_ID(N'dbo.tblFiscalYears', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM dbo.tblFiscalYears WHERE FiscalYearID = @TargetFiscalYearID)
BEGIN
    THROW 50004, 'Target FiscalYearID does not exist in dbo.tblFiscalYears.', 1;
END;

IF OBJECT_ID(N'tempdb..#SourceSegmentValues') IS NOT NULL DROP TABLE #SourceSegmentValues;
IF OBJECT_ID(N'tempdb..#CandidateSegmentValues') IS NOT NULL DROP TABLE #CandidateSegmentValues;
IF OBJECT_ID(N'tempdb..#RejectedRows') IS NOT NULL DROP TABLE #RejectedRows;

SELECT
    FiscalYearID = @TargetFiscalYearID,
    DataObjectCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.BusinessAreaCode))), N''),
    src.SegmentNo,
    SegmentCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SegmentCode))), N''),
    SegmentName = COALESCE(
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(200), src.SegmentName))), N''),
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(200), src.SegmentNameL2))), N''),
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SegmentCode))), N'')
    ),
    SegmentExternalID = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(100), src.SegmentID))), N''),
    ParentSegmentNo = NULLIF(src.ParentSegmentNo, 0),
    ParentSegmentCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.ParentSegmentCode))), N''),
    ActiveFlag = CONVERT(bit, CASE WHEN UPPER(LTRIM(RTRIM(COALESCE(src.Active, N'Y')))) IN (N'Y', N'1', N'T') THEN 1 ELSE 0 END),
    UpdatedBy = COALESCE(src.UpdatedBy, @DefaultUpdatedBy),
    UpdatedDate = COALESCE(src.DateUpdated, GETDATE()),
    SourceBudgetID = src.BudgetID
INTO #SourceSegmentValues
FROM CBMSGOL.dbo.tblSegmentValues src
LEFT JOIN #SelectedSegmentNos selected
    ON selected.SegmentNo = src.SegmentNo
WHERE src.BudgetID = @SourceBudgetID
  AND (@IncludeInactive = 1 OR UPPER(LTRIM(RTRIM(COALESCE(src.Active, N'Y')))) = N'Y')
  AND (
        NOT EXISTS (SELECT 1 FROM #SelectedSegmentNos)
        OR selected.SegmentNo IS NOT NULL
  );

;WITH Deduped AS (
    SELECT
        FiscalYearID,
        DataObjectCode,
        SegmentNo,
        SegmentCode,
        SegmentName = MAX(SegmentName),
        SegmentExternalID = MAX(SegmentExternalID),
        ParentSegmentValueID = CONVERT(int, NULL),
        SortOrder = CASE
            WHEN TRY_CONVERT(int, SegmentCode) IS NOT NULL THEN TRY_CONVERT(int, SegmentCode)
            ELSE 0
        END,
        ActiveFlag = CONVERT(bit, MAX(CONVERT(int, ActiveFlag))),
        UpdatedBy = MAX(UpdatedBy),
        UpdatedDate = MAX(UpdatedDate),
        ParentSegmentNo = MAX(ParentSegmentNo),
        ParentSegmentCode = MAX(ParentSegmentCode),
        ParentSegmentDataObjectCode = CONVERT(nvarchar(50), NULL),
        SourceDuplicateRows = COUNT(*)
    FROM #SourceSegmentValues
    WHERE DataObjectCode IS NOT NULL
      AND SegmentCode IS NOT NULL
      AND SegmentName IS NOT NULL
    GROUP BY
        FiscalYearID,
        DataObjectCode,
        SegmentNo,
        SegmentCode
),
Candidates AS (
    SELECT d.*
    FROM Deduped d
    INNER JOIN dbo.tblSegments s
        ON s.SegmentID = d.SegmentNo
)
SELECT
    FiscalYearID,
    DataObjectCode,
    SegmentNo,
    SegmentCode,
    SegmentName,
    SegmentExternalID,
    ParentSegmentValueID,
    SortOrder = CASE
        WHEN SortOrder > 0 THEN SortOrder
        ELSE ROW_NUMBER() OVER (
            PARTITION BY FiscalYearID, DataObjectCode, SegmentNo
            ORDER BY SegmentCode
        )
    END,
    ActiveFlag,
    UpdatedBy,
    UpdatedDate,
    ParentSegmentNo,
    ParentSegmentCode,
    ParentSegmentDataObjectCode,
    SourceDuplicateRows
INTO #CandidateSegmentValues
FROM Candidates;

IF OBJECT_ID(N'dbo.tblDataObjectCodes', N'U') IS NOT NULL
BEGIN
    ;WITH DataObjectScope AS (
        SELECT
            child.FiscalYearID,
            child.DataObjectCode,
            child.SegmentNo,
            child.SegmentCode,
            ScopeDataObjectCode = child.DataObjectCode,
            ScopeDepth = 0
        FROM #CandidateSegmentValues child
        WHERE child.ParentSegmentNo IS NOT NULL
          AND child.ParentSegmentCode IS NOT NULL

        UNION ALL

        SELECT
            scope.FiscalYearID,
            scope.DataObjectCode,
            scope.SegmentNo,
            scope.SegmentCode,
            ScopeDataObjectCode = CONVERT(nvarchar(50), doc.DataObjectCodeParent),
            ScopeDepth = scope.ScopeDepth + 1
        FROM DataObjectScope scope
        INNER JOIN dbo.tblDataObjectCodes doc
            ON doc.FiscalYearID = scope.FiscalYearID
           AND doc.DataObjectCode = scope.ScopeDataObjectCode
        WHERE NULLIF(LTRIM(RTRIM(ISNULL(doc.DataObjectCodeParent, N''))), N'') IS NOT NULL
          AND doc.DataObjectCodeParent <> scope.ScopeDataObjectCode
          AND scope.ScopeDepth < 20
    ),
    ParentValuePool AS (
        SELECT
            FiscalYearID,
            DataObjectCode,
            SegmentNo,
            SegmentCode,
            ActiveFlag
        FROM #CandidateSegmentValues

        UNION

        SELECT
            target.FiscalYearID,
            target.DataObjectCode,
            target.SegmentNo,
            target.SegmentCode,
            target.ActiveFlag
        FROM dbo.tblSegmentValues target
        WHERE target.FiscalYearID = @TargetFiscalYearID
          AND EXISTS (
                SELECT 1
                FROM #CandidateSegmentValues child
                WHERE child.FiscalYearID = target.FiscalYearID
                  AND child.ParentSegmentNo = target.SegmentNo
                  AND child.ParentSegmentCode = target.SegmentCode
          )
    ),
    ParentCandidates AS (
        SELECT
            child.FiscalYearID,
            child.DataObjectCode,
            child.SegmentNo,
            child.SegmentCode,
            ParentSegmentDataObjectCode = parent.DataObjectCode,
            rn = ROW_NUMBER() OVER (
                PARTITION BY child.FiscalYearID, child.DataObjectCode, child.SegmentNo, child.SegmentCode
                ORDER BY
                    CASE WHEN scope.ScopeDataObjectCode IS NOT NULL THEN 0 ELSE 1 END,
                    ISNULL(scope.ScopeDepth, 999),
                    CASE WHEN parent.DataObjectCode = child.DataObjectCode THEN 0 ELSE 1 END,
                    parent.DataObjectCode
            )
        FROM #CandidateSegmentValues child
        INNER JOIN ParentValuePool parent
            ON parent.FiscalYearID = child.FiscalYearID
           AND parent.SegmentNo = child.ParentSegmentNo
           AND parent.SegmentCode = child.ParentSegmentCode
           AND parent.ActiveFlag = 1
        LEFT JOIN DataObjectScope scope
            ON scope.FiscalYearID = child.FiscalYearID
           AND scope.DataObjectCode = child.DataObjectCode
           AND scope.SegmentNo = child.SegmentNo
           AND scope.SegmentCode = child.SegmentCode
           AND scope.ScopeDataObjectCode = parent.DataObjectCode
    )
    UPDATE child
    SET child.ParentSegmentDataObjectCode = parent.ParentSegmentDataObjectCode
    FROM #CandidateSegmentValues child
    INNER JOIN ParentCandidates parent
        ON parent.FiscalYearID = child.FiscalYearID
       AND parent.DataObjectCode = child.DataObjectCode
       AND parent.SegmentNo = child.SegmentNo
       AND parent.SegmentCode = child.SegmentCode
       AND parent.rn = 1;
END;

SELECT
    RejectionReason = CASE
        WHEN src.DataObjectCode IS NULL THEN N'Missing BusinessAreaCode/DataObjectCode'
        WHEN src.SegmentCode IS NULL THEN N'Missing SegmentCode'
        WHEN src.SegmentName IS NULL THEN N'Missing SegmentName'
        WHEN NOT EXISTS (SELECT 1 FROM dbo.tblSegments s WHERE s.SegmentID = src.SegmentNo) THEN N'Target SegmentNo not found'
        ELSE N'Filtered by dedupe'
    END,
    src.*
INTO #RejectedRows
FROM #SourceSegmentValues src
WHERE src.DataObjectCode IS NULL
   OR src.SegmentCode IS NULL
   OR src.SegmentName IS NULL
   OR NOT EXISTS (SELECT 1 FROM dbo.tblSegments s WHERE s.SegmentID = src.SegmentNo);

SELECT
    TargetDatabase = DB_NAME(),
    SourceRows = (SELECT COUNT(*) FROM #SourceSegmentValues),
    CandidateRows = (SELECT COUNT(*) FROM #CandidateSegmentValues),
    RejectedRows = (SELECT COUNT(*) FROM #RejectedRows),
    ExistingTargetRowsToUpdate = (
        SELECT COUNT(*)
        FROM dbo.tblSegmentValues target
        INNER JOIN #CandidateSegmentValues source
            ON source.FiscalYearID = target.FiscalYearID
           AND source.DataObjectCode = target.DataObjectCode
           AND source.SegmentNo = target.SegmentNo
           AND source.SegmentCode = target.SegmentCode
    ),
    NewTargetRowsToInsert = (
        SELECT COUNT(*)
        FROM #CandidateSegmentValues source
        WHERE NOT EXISTS (
            SELECT 1
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = source.FiscalYearID
              AND target.DataObjectCode = source.DataObjectCode
              AND target.SegmentNo = source.SegmentNo
              AND target.SegmentCode = source.SegmentCode
        )
    ),
    ExistingTargetSegmentRowsToDelete = CASE
        WHEN @DeleteExistingTargetSegmentRows = 1
        THEN (
            SELECT COUNT(*)
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = @TargetFiscalYearID
              AND EXISTS (
                    SELECT 1
                    FROM #CandidateSegmentValues candidate
                    WHERE candidate.SegmentNo = target.SegmentNo
              )
        )
        ELSE 0
    END;

SELECT
    SegmentNo,
    CandidateRows = COUNT(*),
    ActiveCandidateRows = SUM(CASE WHEN ActiveFlag = 1 THEN 1 ELSE 0 END),
    DistinctDataObjectCount = COUNT(DISTINCT DataObjectCode),
    SourceDuplicateRows = SUM(CASE WHEN SourceDuplicateRows > 1 THEN SourceDuplicateRows - 1 ELSE 0 END)
FROM #CandidateSegmentValues
GROUP BY SegmentNo
ORDER BY SegmentNo;

SELECT
    RejectionReason,
    Rows = COUNT(*)
FROM #RejectedRows
GROUP BY RejectionReason
ORDER BY RejectionReason;

SELECT TOP (100)
    FiscalYearID,
    DataObjectCode,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentSegmentNo,
    ParentSegmentCode,
    ParentSegmentDataObjectCode,
    ActiveFlag,
    SegmentExternalID
FROM #CandidateSegmentValues
ORDER BY SegmentNo, DataObjectCode, SegmentCode;

IF @PreviewOnly = 0
BEGIN
    BEGIN TRANSACTION;

        DECLARE @DeletedRows int = 0;
        DECLARE @UpdatedRows int = 0;
        DECLARE @InsertedRows int = 0;
        DECLARE @ResolvedParentRows int = 0;

        IF @DeleteExistingTargetSegmentRows = 1
        BEGIN
            DELETE target
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = @TargetFiscalYearID
              AND EXISTS (
                    SELECT 1
                    FROM #CandidateSegmentValues candidate
                    WHERE candidate.SegmentNo = target.SegmentNo
              );

            SET @DeletedRows = @@ROWCOUNT;
        END;

        UPDATE target
        SET
            target.SegmentName = source.SegmentName,
            target.SegmentExternalID = source.SegmentExternalID,
            target.ParentSegmentValueID = source.ParentSegmentValueID,
            target.SortOrder = source.SortOrder,
            target.ActiveFlag = source.ActiveFlag,
            target.UpdatedBy = source.UpdatedBy,
            target.UpdatedDate = source.UpdatedDate,
            target.ParentSegmentNo = source.ParentSegmentNo,
            target.ParentSegmentCode = source.ParentSegmentCode,
            target.ParentSegmentDataObjectCode = source.ParentSegmentDataObjectCode
        FROM dbo.tblSegmentValues target
        INNER JOIN #CandidateSegmentValues source
            ON source.FiscalYearID = target.FiscalYearID
           AND source.DataObjectCode = target.DataObjectCode
           AND source.SegmentNo = target.SegmentNo
           AND source.SegmentCode = target.SegmentCode;

        SET @UpdatedRows = @@ROWCOUNT;

        INSERT INTO dbo.tblSegmentValues (
            FiscalYearID,
            DataObjectCode,
            SegmentNo,
            SegmentCode,
            SegmentName,
            SegmentExternalID,
            ParentSegmentValueID,
            SortOrder,
            ActiveFlag,
            UpdatedBy,
            UpdatedDate,
            ParentSegmentNo,
            ParentSegmentCode,
            ParentSegmentDataObjectCode
        )
        SELECT
            source.FiscalYearID,
            source.DataObjectCode,
            source.SegmentNo,
            source.SegmentCode,
            source.SegmentName,
            source.SegmentExternalID,
            source.ParentSegmentValueID,
            source.SortOrder,
            source.ActiveFlag,
            source.UpdatedBy,
            source.UpdatedDate,
            source.ParentSegmentNo,
            source.ParentSegmentCode,
            source.ParentSegmentDataObjectCode
        FROM #CandidateSegmentValues source
        WHERE NOT EXISTS (
            SELECT 1
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = source.FiscalYearID
              AND target.DataObjectCode = source.DataObjectCode
              AND target.SegmentNo = source.SegmentNo
              AND target.SegmentCode = source.SegmentCode
        );

        SET @InsertedRows = @@ROWCOUNT;

        IF @ResolveParentSegmentValueID = 1
        BEGIN
            UPDATE child
            SET child.ParentSegmentValueID = parent.SegmentValueID
            FROM dbo.tblSegmentValues child
            INNER JOIN #CandidateSegmentValues loaded
                ON loaded.FiscalYearID = child.FiscalYearID
               AND loaded.DataObjectCode = child.DataObjectCode
               AND loaded.SegmentNo = child.SegmentNo
               AND loaded.SegmentCode = child.SegmentCode
            OUTER APPLY (
                SELECT TOP (1)
                    p.SegmentValueID
                FROM dbo.tblSegmentValues p
                WHERE p.FiscalYearID = child.FiscalYearID
                  AND p.SegmentNo = child.ParentSegmentNo
                  AND p.SegmentCode = child.ParentSegmentCode
                  AND p.ActiveFlag = 1
                  AND (
                        child.ParentSegmentDataObjectCode IS NULL
                        OR p.DataObjectCode = child.ParentSegmentDataObjectCode
                  )
                ORDER BY
                    CASE
                        WHEN p.DataObjectCode = child.ParentSegmentDataObjectCode THEN 0
                        WHEN p.DataObjectCode = child.DataObjectCode THEN 0
                        WHEN p.DataObjectCode = child.ParentSegmentCode THEN 1
                        WHEN LEN(LTRIM(RTRIM(ISNULL(child.DataObjectCode, N'')))) > 2
                         AND p.DataObjectCode = LEFT(
                                LTRIM(RTRIM(ISNULL(child.DataObjectCode, N''))),
                                LEN(LTRIM(RTRIM(ISNULL(child.DataObjectCode, N'')))) - 2
                             ) THEN 2
                        ELSE 9
                    END,
                    p.SegmentValueID
            ) parent
            WHERE child.ParentSegmentNo IS NOT NULL
              AND child.ParentSegmentCode IS NOT NULL
              AND parent.SegmentValueID IS NOT NULL;

            SET @ResolvedParentRows = @@ROWCOUNT;
        END;

        SELECT
            DeletedRows = @DeletedRows,
            UpdatedRows = @UpdatedRows,
            InsertedRows = @InsertedRows,
            ResolvedParentRows = @ResolvedParentRows;

    COMMIT TRANSACTION;
END
ELSE
BEGIN
    PRINT 'Preview only. Set @PreviewOnly = 0 to update/insert tblSegmentValues.';
END;
GO
