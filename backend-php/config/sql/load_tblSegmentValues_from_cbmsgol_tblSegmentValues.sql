/*
Purpose
-------
Copy segment values from legacy CBMSGOL.dbo.tblSegmentValues into the current
CBMSv2_INITTEST.dbo.tblSegmentValues table.

Default behavior is PREVIEW ONLY.

How to use
----------
1. Review the parameter block below.
2. Run once with @PreviewOnly = 1 and check the summary/sample rows.
3. Set @PreviewOnly = 0 to insert/update rows.
4. Optional: set @ReplaceExisting = 1 to delete existing target rows for the
   selected FiscalYearID and loaded SegmentNo values before inserting.

Column mapping
--------------
CBMSGOL.dbo.tblSegmentValues      CBMSv2_INITTEST.dbo.tblSegmentValues
-----------------------------    ------------------------------------
@TargetFiscalYearID              FiscalYearID
BusinessAreaCode                 DataObjectCode
SegmentNo                        SegmentNo
SegmentCode                      SegmentCode
SegmentName / SegmentNameL2      SegmentName
SegmentID                        SegmentExternalID
ParentSegmentNo                  ParentSegmentNo
ParentSegmentCode                ParentSegmentCode
Active                           ActiveFlag
UpdatedBy                        UpdatedBy
DateUpdated                      UpdatedDate
*/

USE [CBMSv2_INITTEST];
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

DECLARE @SourceDatabase sysname = N'CBMSGOL';
DECLARE @SourceSchema sysname = N'dbo';
DECLARE @SourceTable sysname = N'tblSegmentValues';

DECLARE @SourceBudgetID int = 8;
DECLARE @TargetFiscalYearID int = 2026;
DECLARE @OnlySegmentNo int = 3;
DECLARE @DefaultUpdatedBy int = 1;

DECLARE @PreviewOnly bit = 0;
DECLARE @ReplaceExisting bit = 0;

/*
  Limit the load to selected SegmentNo values.
  Set @OnlySegmentNo above to load one SegmentNo.
  Leave empty to load every SegmentNo found in the source.

  Example for strategic mappings:
      INSERT INTO #SelectedSegmentNos VALUES (4), (5), (13), (14);
*/
IF OBJECT_ID(N'tempdb..#SelectedSegmentNos') IS NOT NULL
BEGIN
    DROP TABLE #SelectedSegmentNos;
END;

CREATE TABLE #SelectedSegmentNos (
    SegmentNo int NOT NULL PRIMARY KEY
);

IF @OnlySegmentNo IS NOT NULL
BEGIN
    INSERT INTO #SelectedSegmentNos (SegmentNo)
    VALUES (@OnlySegmentNo);
END;

IF DB_ID(@SourceDatabase) IS NULL
BEGIN
    RAISERROR('Source database does not exist or is not visible to this login.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblSegmentValues', N'U') IS NULL
BEGIN
    RAISERROR('Target dbo.tblSegmentValues does not exist.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblSegments', N'U') IS NULL
BEGIN
    RAISERROR('Target dbo.tblSegments does not exist.', 16, 1);
    RETURN;
END;

IF OBJECT_ID(N'dbo.tblFiscalYears', N'U') IS NULL
BEGIN
    RAISERROR('Target dbo.tblFiscalYears does not exist.', 16, 1);
    RETURN;
END;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblFiscalYears
    WHERE FiscalYearID = @TargetFiscalYearID
)
BEGIN
    RAISERROR('Target FiscalYearID does not exist in dbo.tblFiscalYears.', 16, 1);
    RETURN;
END;

DECLARE @Sql nvarchar(max) = N'
IF OBJECT_ID(N''tempdb..#SourceSegmentValues'') IS NOT NULL
BEGIN
    DROP TABLE #SourceSegmentValues;
END;

IF OBJECT_ID(N''tempdb..#CandidateSegmentValues'') IS NOT NULL
BEGIN
    DROP TABLE #CandidateSegmentValues;
END;

SELECT
    FiscalYearID = @TargetFiscalYearID,
    DataObjectCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.BusinessAreaCode))), N''''),
    src.SegmentNo,
    SegmentCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SegmentCode))), N''''),
    SegmentName = COALESCE(
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(200), src.SegmentName))), N''''),
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(200), src.SegmentNameL2))), N''''),
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SegmentCode))), N'''')
    ),
    SegmentExternalID = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(100), src.SegmentID))), N''''),
    ParentSegmentNo = src.ParentSegmentNo,
    ParentSegmentCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.ParentSegmentCode))), N''''),
    ActiveFlag = CONVERT(bit, CASE WHEN UPPER(LTRIM(RTRIM(COALESCE(src.Active, N''Y'')))) IN (N''Y'', N''1'', N''T'') THEN 1 ELSE 0 END),
    UpdatedBy = COALESCE(src.UpdatedBy, @DefaultUpdatedBy),
    UpdatedDate = COALESCE(src.DateUpdated, GETDATE())
INTO #SourceSegmentValues
FROM ' + QUOTENAME(@SourceDatabase) + N'.' + QUOTENAME(@SourceSchema) + N'.' + QUOTENAME(@SourceTable) + N' AS src
LEFT JOIN #SelectedSegmentNos selected
    ON selected.SegmentNo = src.SegmentNo
WHERE src.BudgetID = @SourceBudgetID
  AND NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.BusinessAreaCode))), N'''') IS NOT NULL
  AND NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SegmentCode))), N'''') IS NOT NULL
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
            WHEN ISNUMERIC(SegmentCode) = 1
             AND SegmentCode NOT LIKE N''%.%''
             AND SegmentCode NOT LIKE N''%,%''
             AND SegmentCode NOT LIKE N''%e%''
             AND SegmentCode NOT LIKE N''%E%''
            THEN CONVERT(int, SegmentCode)
            ELSE 0
        END,
        ActiveFlag = CONVERT(bit, MAX(CONVERT(int, ActiveFlag))),
        UpdatedBy = MAX(UpdatedBy),
        UpdatedDate = MAX(UpdatedDate),
        ParentSegmentNo = MAX(ParentSegmentNo),
        ParentSegmentCode = MAX(ParentSegmentCode)
    FROM #SourceSegmentValues
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
    ParentSegmentCode
INTO #CandidateSegmentValues
FROM Candidates;

SELECT
    SegmentNo,
    ValueCount = COUNT(*),
    ActiveValueCount = SUM(CASE WHEN ActiveFlag = 1 THEN 1 ELSE 0 END),
    DistinctDataObjectCount = COUNT(DISTINCT DataObjectCode)
FROM #CandidateSegmentValues
GROUP BY SegmentNo
ORDER BY SegmentNo;

SELECT TOP (100) *
FROM #CandidateSegmentValues
ORDER BY SegmentNo, DataObjectCode, SegmentCode;

SELECT
    MissingTargetSegmentNo = src.SegmentNo,
    SourceValueCount = COUNT(*)
FROM #SourceSegmentValues src
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblSegments s
    WHERE s.SegmentID = src.SegmentNo
)
GROUP BY src.SegmentNo
ORDER BY src.SegmentNo;

IF @PreviewOnly = 0
BEGIN
    BEGIN TRANSACTION;

        IF @ReplaceExisting = 1
        BEGIN
            DELETE target
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = @TargetFiscalYearID
              AND EXISTS (
                    SELECT 1
                    FROM #CandidateSegmentValues candidate
                    WHERE candidate.SegmentNo = target.SegmentNo
              );
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
            target.ParentSegmentCode = source.ParentSegmentCode
        FROM dbo.tblSegmentValues target
        INNER JOIN #CandidateSegmentValues source
            ON source.FiscalYearID = target.FiscalYearID
           AND source.DataObjectCode = target.DataObjectCode
           AND source.SegmentNo = target.SegmentNo
           AND source.SegmentCode = target.SegmentCode;

        DECLARE @UpdatedRows int = @@ROWCOUNT;

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
            ParentSegmentCode
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
            source.ParentSegmentCode
        FROM #CandidateSegmentValues source
        WHERE NOT EXISTS (
            SELECT 1
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = source.FiscalYearID
              AND target.DataObjectCode = source.DataObjectCode
              AND target.SegmentNo = source.SegmentNo
              AND target.SegmentCode = source.SegmentCode
        );

        SELECT
            UpdatedRows = @UpdatedRows,
            InsertedRows = @@ROWCOUNT;

    COMMIT TRANSACTION;
END
ELSE
BEGIN
    PRINT ''Preview only. Set @PreviewOnly = 0 to insert/update tblSegmentValues.'';
END;
';

EXEC sp_executesql
    @Sql,
    N'@SourceBudgetID int,
      @TargetFiscalYearID int,
      @DefaultUpdatedBy int,
      @PreviewOnly bit,
      @ReplaceExisting bit',
    @SourceBudgetID = @SourceBudgetID,
    @TargetFiscalYearID = @TargetFiscalYearID,
    @DefaultUpdatedBy = @DefaultUpdatedBy,
    @PreviewOnly = @PreviewOnly,
    @ReplaceExisting = @ReplaceExisting;
GO
