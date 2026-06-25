/*
Purpose
-------
Load Sub Cost Centre values from legacy CBMSGOL.dbo.tblCostCentres into
CBMSv2_INITTEST.dbo.tblSegmentValues.

Default behavior is PREVIEW ONLY.

Mapping
-------
CBMSGOL.dbo.tblCostCentres                 CBMSv2_INITTEST.dbo.tblSegmentValues
-----------------------------------------  ------------------------------------
@TargetFiscalYearID                        FiscalYearID
CostCentreID with leading "1" removed      DataObjectCode
@TargetSegmentNo                           SegmentNo
SourceCode                                 SegmentCode
CostCentreName / CostCentreNameL2          SegmentName
CostCentreID                               SegmentExternalID
matching parent Segment 2 row              ParentSegmentValueID
@ParentSegmentNo                           ParentSegmentNo
parent Cost Centre ProgramCode             ParentSegmentCode
Active                                     ActiveFlag
UpdatedBy                                  UpdatedBy
DateUpdated                                UpdatedDate

Expected source shape for FY2026/BudgetID 8:
    Cost Centre parent: 13010100 -> target DataObjectCode 30101, SegmentCode 01
    Sub Cost Centre:    13010101 -> target DataObjectCode 3010101, SegmentCode 01
*/

USE [CBMSv2_INITTEST];
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

DECLARE @SourceDatabase sysname = N'CBMSGOL';
DECLARE @SourceSchema sysname = N'dbo';
DECLARE @SourceTable sysname = N'tblCostCentres';

DECLARE @SourceBudgetID int = 8;
DECLARE @SourceCostObjectTypeID int = 2;
DECLARE @SourceCostCentrePrefix varchar(10) = '13';
DECLARE @SourcePrefixRemoveChars int = 1;

DECLARE @TargetFiscalYearID int = 2026;
DECLARE @TargetSegmentNo int = 3; -- Sub Cost Centre
DECLARE @ParentSegmentNo int = 2; -- Cost Centre
DECLARE @DefaultUpdatedBy int = 1;

DECLARE @PreviewOnly bit = 1;
DECLARE @ReplaceExisting bit = 0;
DECLARE @DeleteExistingTargetSegmentRows bit = 0;
DECLARE @IncludeInactive bit = 0;
DECLARE @RequireExistingDataObject bit = 1;
DECLARE @RequireParentSegmentValue bit = 1;

IF DB_ID(@SourceDatabase) IS NULL
BEGIN
    THROW 50000, 'Source database does not exist or is not visible to this login.', 1;
END;

IF OBJECT_ID(QUOTENAME(@SourceDatabase) + N'.' + QUOTENAME(@SourceSchema) + N'.' + QUOTENAME(@SourceTable), N'U') IS NULL
BEGIN
    THROW 50001, 'Source table CBMSGOL.dbo.tblCostCentres does not exist or is not visible to this login.', 1;
END;

IF OBJECT_ID(N'dbo.tblSegmentValues', N'U') IS NULL
BEGIN
    THROW 50002, 'Target dbo.tblSegmentValues does not exist.', 1;
END;

IF OBJECT_ID(N'dbo.tblSegments', N'U') IS NULL
BEGIN
    THROW 50003, 'Target dbo.tblSegments does not exist.', 1;
END;

IF OBJECT_ID(N'dbo.tblDataObjectCodes', N'U') IS NULL
BEGIN
    THROW 50004, 'Target dbo.tblDataObjectCodes does not exist.', 1;
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tblSegments WHERE SegmentID = @TargetSegmentNo)
BEGIN
    THROW 50005, 'Target Sub Cost Centre SegmentNo does not exist in dbo.tblSegments.', 1;
END;

IF NOT EXISTS (SELECT 1 FROM dbo.tblSegments WHERE SegmentID = @ParentSegmentNo)
BEGIN
    THROW 50006, 'Parent Cost Centre SegmentNo does not exist in dbo.tblSegments.', 1;
END;

IF OBJECT_ID(N'tempdb..#SourceSubCostCentres') IS NOT NULL DROP TABLE #SourceSubCostCentres;
IF OBJECT_ID(N'tempdb..#CandidateRaw') IS NOT NULL DROP TABLE #CandidateRaw;
IF OBJECT_ID(N'tempdb..#CandidateSegmentValues') IS NOT NULL DROP TABLE #CandidateSegmentValues;
IF OBJECT_ID(N'tempdb..#RejectedRows') IS NOT NULL DROP TABLE #RejectedRows;

SELECT
    child.CostCentreID,
    child.BudgetID,
    child.CostCentreName,
    child.CostCentreNameL2,
    child.CostObjectTypeID,
    child.ParentCostCentreID,
    child.ProgramCode,
    child.SourceCode,
    child.Active,
    child.UpdatedBy,
    child.DateUpdated,
    parent.CostCentreID AS ParentSourceCostCentreID,
    parent.ProgramCode AS ParentProgramCode,
    parent.SourceCode AS ParentSourceCode,
    parent.CostObjectTypeID AS ParentCostObjectTypeID
INTO #SourceSubCostCentres
FROM CBMSGOL.dbo.tblCostCentres child
LEFT JOIN CBMSGOL.dbo.tblCostCentres parent
    ON parent.BudgetID = child.BudgetID
   AND parent.CostCentreID = child.ParentCostCentreID
WHERE child.BudgetID = @SourceBudgetID
  AND child.CostObjectTypeID = @SourceCostObjectTypeID
  AND child.CostCentreID > 0
  AND CONVERT(varchar(20), child.CostCentreID) LIKE @SourceCostCentrePrefix + '%'
  AND (@IncludeInactive = 1 OR UPPER(LTRIM(RTRIM(COALESCE(child.Active, 'Y')))) = 'Y');

SELECT
    FiscalYearID = @TargetFiscalYearID,
    DataObjectCode = NULLIF(SUBSTRING(CONVERT(varchar(20), src.CostCentreID), @SourcePrefixRemoveChars + 1, 50), ''),
    SegmentNo = @TargetSegmentNo,
    SegmentCode = COALESCE(
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SourceCode))), N''),
        CASE
            WHEN LEN(LTRIM(RTRIM(CONVERT(nvarchar(50), src.ProgramCode)))) > LEN(LTRIM(RTRIM(CONVERT(nvarchar(50), src.ParentProgramCode))))
            THEN RIGHT(
                LTRIM(RTRIM(CONVERT(nvarchar(50), src.ProgramCode))),
                LEN(LTRIM(RTRIM(CONVERT(nvarchar(50), src.ProgramCode)))) - LEN(LTRIM(RTRIM(CONVERT(nvarchar(50), src.ParentProgramCode))))
            )
            ELSE NULL
        END
    ),
    SegmentName = COALESCE(
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(255), src.CostCentreName))), N''),
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(255), src.CostCentreNameL2))), N''),
        NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SourceCode))), N'')
    ),
    SegmentExternalID = CONVERT(nvarchar(100), src.CostCentreID),
    ParentDataObjectCode = CASE
        WHEN src.ParentSourceCostCentreID IS NULL THEN NULL
        WHEN CONVERT(varchar(20), src.ParentSourceCostCentreID) NOT LIKE @SourceCostCentrePrefix + '%' THEN NULL
        ELSE
            CASE
                WHEN RIGHT(SUBSTRING(CONVERT(varchar(20), src.ParentSourceCostCentreID), @SourcePrefixRemoveChars + 1, 50), 2) = '00'
                THEN LEFT(
                    SUBSTRING(CONVERT(varchar(20), src.ParentSourceCostCentreID), @SourcePrefixRemoveChars + 1, 50),
                    LEN(SUBSTRING(CONVERT(varchar(20), src.ParentSourceCostCentreID), @SourcePrefixRemoveChars + 1, 50)) - 2
                )
                ELSE SUBSTRING(CONVERT(varchar(20), src.ParentSourceCostCentreID), @SourcePrefixRemoveChars + 1, 50)
            END
    END,
    ParentSegmentNo = @ParentSegmentNo,
    ParentSegmentCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.ParentProgramCode))), N''),
    SortOrder = CASE
        WHEN TRY_CONVERT(int, NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SourceCode))), N'')) IS NOT NULL
        THEN TRY_CONVERT(int, NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), src.SourceCode))), N''))
        ELSE 0
    END,
    ActiveFlag = CONVERT(bit, CASE WHEN UPPER(LTRIM(RTRIM(COALESCE(src.Active, 'Y')))) IN ('Y', '1', 'T') THEN 1 ELSE 0 END),
    UpdatedBy = COALESCE(src.UpdatedBy, @DefaultUpdatedBy),
    UpdatedDate = COALESCE(src.DateUpdated, GETDATE()),
    src.CostCentreID,
    src.ParentCostCentreID
INTO #CandidateRaw
FROM #SourceSubCostCentres src;

;WITH Ranked AS (
    SELECT
        raw.*,
        ParentSegmentValueID = parent.SegmentValueID,
        HasTargetDataObject = CASE WHEN doc.DataObjectCode IS NULL THEN 0 ELSE 1 END,
        DuplicateRank = ROW_NUMBER() OVER (
            PARTITION BY raw.FiscalYearID, raw.DataObjectCode, raw.SegmentNo, raw.SegmentCode
            ORDER BY raw.CostCentreID
        )
    FROM #CandidateRaw raw
    LEFT JOIN dbo.tblDataObjectCodes doc
        ON doc.FiscalYearID = raw.FiscalYearID
       AND doc.DataObjectCode = raw.DataObjectCode
    LEFT JOIN dbo.tblSegmentValues parent
        ON parent.FiscalYearID = raw.FiscalYearID
       AND parent.DataObjectCode = raw.ParentDataObjectCode
       AND parent.SegmentNo = raw.ParentSegmentNo
       AND parent.SegmentCode = raw.ParentSegmentCode
       AND parent.ActiveFlag = 1
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
            PARTITION BY FiscalYearID, ParentDataObjectCode, SegmentNo
            ORDER BY DataObjectCode, SegmentCode
        )
    END,
    ActiveFlag,
    UpdatedBy,
    UpdatedDate,
    ParentSegmentNo,
    ParentSegmentCode,
    ParentDataObjectCode,
    SourceCostCentreID = CostCentreID,
    SourceParentCostCentreID = ParentCostCentreID
INTO #CandidateSegmentValues
FROM Ranked
WHERE DuplicateRank = 1
  AND DataObjectCode IS NOT NULL
  AND SegmentCode IS NOT NULL
  AND SegmentName IS NOT NULL
  AND (@RequireExistingDataObject = 0 OR HasTargetDataObject = 1)
  AND (@RequireParentSegmentValue = 0 OR ParentSegmentValueID IS NOT NULL);

;WITH Ranked AS (
    SELECT
        raw.*,
        ParentSegmentValueID = parent.SegmentValueID,
        HasTargetDataObject = CASE WHEN doc.DataObjectCode IS NULL THEN 0 ELSE 1 END,
        DuplicateRank = ROW_NUMBER() OVER (
            PARTITION BY raw.FiscalYearID, raw.DataObjectCode, raw.SegmentNo, raw.SegmentCode
            ORDER BY raw.CostCentreID
        )
    FROM #CandidateRaw raw
    LEFT JOIN dbo.tblDataObjectCodes doc
        ON doc.FiscalYearID = raw.FiscalYearID
       AND doc.DataObjectCode = raw.DataObjectCode
    LEFT JOIN dbo.tblSegmentValues parent
        ON parent.FiscalYearID = raw.FiscalYearID
       AND parent.DataObjectCode = raw.ParentDataObjectCode
       AND parent.SegmentNo = raw.ParentSegmentNo
       AND parent.SegmentCode = raw.ParentSegmentCode
       AND parent.ActiveFlag = 1
)
SELECT
    RejectionReason = CASE
        WHEN DuplicateRank > 1 THEN N'Duplicate source key'
        WHEN DataObjectCode IS NULL THEN N'Missing mapped DataObjectCode'
        WHEN SegmentCode IS NULL THEN N'Missing SegmentCode/SourceCode'
        WHEN SegmentName IS NULL THEN N'Missing SegmentName'
        WHEN @RequireExistingDataObject = 1 AND HasTargetDataObject = 0 THEN N'Target DataObjectCode not found'
        WHEN @RequireParentSegmentValue = 1 AND ParentSegmentValueID IS NULL THEN N'Target parent Segment 2 row not found'
        ELSE N'Loadable when current require flags are relaxed'
    END,
    FiscalYearID,
    DataObjectCode,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentDataObjectCode,
    ParentSegmentNo,
    ParentSegmentCode,
    ParentSegmentValueID,
    SourceCostCentreID = CostCentreID,
    SourceParentCostCentreID = ParentCostCentreID
INTO #RejectedRows
FROM Ranked
WHERE DuplicateRank > 1
   OR DataObjectCode IS NULL
   OR SegmentCode IS NULL
   OR SegmentName IS NULL
   OR (@RequireExistingDataObject = 1 AND HasTargetDataObject = 0)
   OR (@RequireParentSegmentValue = 1 AND ParentSegmentValueID IS NULL);

SELECT
    SourceRows = (SELECT COUNT(*) FROM #SourceSubCostCentres),
    CandidateRows = (SELECT COUNT(*) FROM #CandidateRaw),
    LoadableRows = (SELECT COUNT(*) FROM #CandidateSegmentValues),
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
              AND target.SegmentNo = @TargetSegmentNo
        )
        WHEN @ReplaceExisting = 1
        THEN (
            SELECT COUNT(*)
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = @TargetFiscalYearID
              AND target.SegmentNo = @TargetSegmentNo
              AND EXISTS (
                    SELECT 1
                    FROM #CandidateSegmentValues source
                    WHERE source.FiscalYearID = target.FiscalYearID
                      AND source.DataObjectCode = target.DataObjectCode
                      AND source.SegmentNo = target.SegmentNo
                      AND source.SegmentCode = target.SegmentCode
              )
        )
        ELSE 0
    END;

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
    ParentDataObjectCode,
    ParentSegmentNo,
    ParentSegmentCode,
    ParentSegmentValueID,
    SourceCostCentreID,
    SourceParentCostCentreID
FROM #CandidateSegmentValues
ORDER BY DataObjectCode, SegmentCode;

SELECT TOP (100)
    RejectionReason,
    FiscalYearID,
    DataObjectCode,
    SegmentNo,
    SegmentCode,
    SegmentName,
    ParentDataObjectCode,
    ParentSegmentNo,
    ParentSegmentCode,
    SourceCostCentreID,
    SourceParentCostCentreID
FROM #RejectedRows
ORDER BY RejectionReason, SourceCostCentreID;

IF @PreviewOnly = 0
BEGIN
    BEGIN TRANSACTION;

        DECLARE @DeletedRows int = 0;

        IF @DeleteExistingTargetSegmentRows = 1
        BEGIN
            DELETE target
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = @TargetFiscalYearID
              AND target.SegmentNo = @TargetSegmentNo;

            SET @DeletedRows = @@ROWCOUNT;
        END
        ELSE IF @ReplaceExisting = 1
        BEGIN
            DELETE target
            FROM dbo.tblSegmentValues target
            WHERE target.FiscalYearID = @TargetFiscalYearID
              AND target.SegmentNo = @TargetSegmentNo
              AND EXISTS (
                    SELECT 1
                    FROM #CandidateSegmentValues source
                    WHERE source.FiscalYearID = target.FiscalYearID
                      AND source.DataObjectCode = target.DataObjectCode
                      AND source.SegmentNo = target.SegmentNo
                      AND source.SegmentCode = target.SegmentCode
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
            DeletedRows = @DeletedRows,
            UpdatedRows = @UpdatedRows,
            InsertedRows = @@ROWCOUNT;

    COMMIT TRANSACTION;
END
ELSE
BEGIN
    PRINT 'Preview only. Set @PreviewOnly = 0 to insert/update tblSegmentValues.';
END;
GO
