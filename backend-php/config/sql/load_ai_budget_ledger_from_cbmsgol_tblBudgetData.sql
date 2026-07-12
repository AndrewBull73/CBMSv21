/*
    Load CBMSGOL.dbo.tblBudgetData into dbo.tblAIBudgetLedgerAnalysisSource.

    Default behavior is PREVIEW ONLY.

    What this script does:
    - Reads wide monthly Budget Data rows from CBMSGOL.dbo.tblBudgetData.
    - Converts BM1..BM12 and AM1..AM12 into one row per period.
    - Loads only generic Segment1..Segment15 dimensions plus financial measures.
    - Leaves semantic aliases such as Vote, Program, and Economic to dbo.tblAIBudgetLedgerSegmentRoleMap
      and dbo.vwAI_BudgetLedgerAnalysis.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

DECLARE @SourceDatabase sysname = N'CBMSGOL';
DECLARE @SourceSchema sysname = N'dbo';
DECLARE @SourceTable sysname = N'tblBudgetData';

DECLARE @TargetFiscalYearID INT = 2026;
DECLARE @BudgetID INT = NULL;       -- Set to a specific BudgetID, or leave NULL for all.
DECLARE @VersionID INT = NULL;      -- Set to a specific VersionID, or leave NULL for all.
DECLARE @PreviewOnly BIT = 1;       -- Set to 0 to insert/update target rows.
DECLARE @IncludeZeroRows BIT = 0;   -- Set to 1 to include rows where both budget and actual are zero.
DECLARE @DataLoadBatchCode NVARCHAR(120) = N'CBMSGOL_tblBudgetData_2026';
DECLARE @LoadedBy INT = NULL;

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerAnalysisSource', N'U') IS NULL
BEGIN
    THROW 51000, 'Target table dbo.tblAIBudgetLedgerAnalysisSource was not found. Run create_ai_budget_ledger_analysis_dataset_v1.sql first.', 1;
END;

IF COL_LENGTH(N'dbo.tblAIBudgetLedgerAnalysisSource', N'VoteCode') IS NOT NULL
BEGIN
    THROW 51002, 'Target table still has legacy semantic columns such as VoteCode. Drop/recreate dbo.tblAIBudgetLedgerAnalysisSource in development before using this loader.', 1;
END;

IF DB_ID(@SourceDatabase) IS NULL
BEGIN
    THROW 51001, 'Source database was not found or is not visible to this login.', 1;
END;

DECLARE @SourceObject NVARCHAR(400) = QUOTENAME(@SourceDatabase) + N'.' + QUOTENAME(@SourceSchema) + N'.' + QUOTENAME(@SourceTable);
DECLARE @Sql NVARCHAR(MAX) = N'
SELECT
    SourceRowReference = N''CBMSGOL.tblBudgetData:'' + CONVERT(NVARCHAR(30), src.BudgetDataID) + N'':P'' + RIGHT(N''00'' + CONVERT(NVARCHAR(2), m.PeriodNo), 2),
    FiscalYearID = @TargetFiscalYearID,
    BudgetVersionID = src.VersionID,
    m.PeriodNo,
    PostingDate = CAST(NULL AS DATE),

    Segment1 = NULLIF(LTRIM(RTRIM(src.Segment1)), N''''),
    Segment2 = NULLIF(LTRIM(RTRIM(src.Segment2)), N''''),
    Segment3 = NULLIF(LTRIM(RTRIM(src.Segment3)), N''''),
    Segment4 = NULLIF(LTRIM(RTRIM(src.Segment4)), N''''),
    Segment5 = NULLIF(LTRIM(RTRIM(src.Segment5)), N''''),
    Segment6 = NULLIF(LTRIM(RTRIM(src.Segment6)), N''''),
    Segment7 = NULLIF(LTRIM(RTRIM(src.Segment7)), N''''),
    Segment8 = NULLIF(LTRIM(RTRIM(src.Segment8)), N''''),
    Segment9 = NULLIF(LTRIM(RTRIM(src.Segment9)), N''''),
    Segment10 = NULLIF(LTRIM(RTRIM(src.Segment10)), N''''),
    Segment11 = NULLIF(LTRIM(RTRIM(src.Segment11)), N''''),
    Segment12 = NULLIF(LTRIM(RTRIM(src.Segment12)), N''''),
    Segment13 = NULLIF(LTRIM(RTRIM(src.Segment13)), N''''),
    Segment14 = NULLIF(LTRIM(RTRIM(src.Segment14)), N''''),
    Segment15 = NULLIF(LTRIM(RTRIM(src.Segment15)), N''''),

    BudgetAmount = CAST(ROUND(ISNULL(m.BudgetAmount, 0) * CASE WHEN ISNULL(src.MathSignage, 0) = 0 THEN 1 ELSE src.MathSignage END, 2) AS DECIMAL(19,2)),
    ReleasedAmount = CAST(0 AS DECIMAL(19,2)),
    WarrantAmount = CAST(0 AS DECIMAL(19,2)),
    CommitmentAmount = CAST(0 AS DECIMAL(19,2)),
    ActualAmount = CAST(ROUND(ISNULL(m.ActualAmount, 0) * CASE WHEN ISNULL(src.MathSignage, 0) = 0 THEN 1 ELSE src.MathSignage END, 2) AS DECIMAL(19,2)),
    AvailableBalance = CAST(ROUND((ISNULL(m.BudgetAmount, 0) - ISNULL(m.ActualAmount, 0)) * CASE WHEN ISNULL(src.MathSignage, 0) = 0 THEN 1 ELSE src.MathSignage END, 2) AS DECIMAL(19,2)),
    ExecutionRate = CAST(CASE WHEN ISNULL(m.BudgetAmount, 0) <> 0 THEN (ISNULL(m.ActualAmount, 0) / NULLIF(ISNULL(m.BudgetAmount, 0), 0)) * 100.0 ELSE NULL END AS DECIMAL(9,4)),
    PriorYearBudgetAmount = CAST(NULL AS DECIMAL(19,2)),
    PriorYearActualAmount = CAST(NULL AS DECIMAL(19,2)),
    CurrencyCode = COALESCE(NULLIF(CONVERT(NVARCHAR(10), src.Currency), N''''), N''LSL''),
    SensitivityLabel = N''RESTRICTED'',
    DataLoadBatchCode = @DataLoadBatchCode,
    SourceSystemCode = N''CBMSGOL'',
    IsActive = CAST(1 AS BIT),
    LoadedBy = @LoadedBy,
    Notes =
        N''BudgetID='' + COALESCE(CONVERT(NVARCHAR(30), src.BudgetID), N'''') +
        N''; CostCentreID='' + COALESCE(CONVERT(NVARCHAR(30), src.CostCentreID), N'''') +
        N''; GLCode='' + COALESCE(CONVERT(NVARCHAR(30), src.GLCode), N'''') +
        N''; TransactionType='' + COALESCE(CONVERT(NVARCHAR(10), src.TransactionType), N'''') +
        N''; UploadID='' + COALESCE(CONVERT(NVARCHAR(80), src.UploadID), N'''')
INTO #MonthlyRows
FROM ' + @SourceObject + N' AS src
CROSS APPLY (VALUES
    (1, src.BM1, src.AM1),
    (2, src.BM2, src.AM2),
    (3, src.BM3, src.AM3),
    (4, src.BM4, src.AM4),
    (5, src.BM5, src.AM5),
    (6, src.BM6, src.AM6),
    (7, src.BM7, src.AM7),
    (8, src.BM8, src.AM8),
    (9, src.BM9, src.AM9),
    (10, src.BM10, src.AM10),
    (11, src.BM11, src.AM11),
    (12, src.BM12, src.AM12)
) AS m (PeriodNo, BudgetAmount, ActualAmount)
WHERE (@BudgetID IS NULL OR src.BudgetID = @BudgetID)
  AND (@VersionID IS NULL OR src.VersionID = @VersionID)
  AND (
      @IncludeZeroRows = 1
      OR ISNULL(m.BudgetAmount, 0) <> 0
      OR ISNULL(m.ActualAmount, 0) <> 0
  );
';

IF @PreviewOnly = 1
BEGIN
    SET @Sql += N'
SELECT COUNT(1) AS PreviewRowCount
FROM #MonthlyRows;

SELECT TOP (100) *
FROM #MonthlyRows
ORDER BY FiscalYearID, PeriodNo, SourceRowReference;';
END
ELSE
BEGIN
    SET @Sql += N'
UPDATE target
SET
    FiscalYearID = source.FiscalYearID,
    BudgetVersionID = source.BudgetVersionID,
    PeriodNo = source.PeriodNo,
    PostingDate = source.PostingDate,
    Segment1 = source.Segment1,
    Segment2 = source.Segment2,
    Segment3 = source.Segment3,
    Segment4 = source.Segment4,
    Segment5 = source.Segment5,
    Segment6 = source.Segment6,
    Segment7 = source.Segment7,
    Segment8 = source.Segment8,
    Segment9 = source.Segment9,
    Segment10 = source.Segment10,
    Segment11 = source.Segment11,
    Segment12 = source.Segment12,
    Segment13 = source.Segment13,
    Segment14 = source.Segment14,
    Segment15 = source.Segment15,
    BudgetAmount = source.BudgetAmount,
    ReleasedAmount = source.ReleasedAmount,
    WarrantAmount = source.WarrantAmount,
    CommitmentAmount = source.CommitmentAmount,
    ActualAmount = source.ActualAmount,
    AvailableBalance = source.AvailableBalance,
    ExecutionRate = source.ExecutionRate,
    PriorYearBudgetAmount = source.PriorYearBudgetAmount,
    PriorYearActualAmount = source.PriorYearActualAmount,
    CurrencyCode = source.CurrencyCode,
    SensitivityLabel = source.SensitivityLabel,
    DataLoadBatchCode = source.DataLoadBatchCode,
    SourceSystemCode = source.SourceSystemCode,
    IsActive = source.IsActive,
    LoadedBy = source.LoadedBy,
    LoadedDate = SYSUTCDATETIME(),
    Notes = source.Notes
FROM dbo.tblAIBudgetLedgerAnalysisSource AS target
INNER JOIN #MonthlyRows AS source
    ON source.SourceRowReference = target.SourceRowReference;

DECLARE @UpdatedRowCount INT = @@ROWCOUNT;

INSERT INTO dbo.tblAIBudgetLedgerAnalysisSource
    (SourceRowReference, FiscalYearID, BudgetVersionID, PeriodNo, PostingDate,
     Segment1, Segment2, Segment3, Segment4, Segment5, Segment6, Segment7, Segment8, Segment9, Segment10, Segment11, Segment12, Segment13, Segment14, Segment15,
     BudgetAmount, ReleasedAmount, WarrantAmount, CommitmentAmount, ActualAmount, AvailableBalance, ExecutionRate,
     PriorYearBudgetAmount, PriorYearActualAmount, CurrencyCode, SensitivityLabel, DataLoadBatchCode, SourceSystemCode,
     IsActive, LoadedBy, Notes)
SELECT
    source.SourceRowReference, source.FiscalYearID, source.BudgetVersionID, source.PeriodNo, source.PostingDate,
    source.Segment1, source.Segment2, source.Segment3, source.Segment4, source.Segment5, source.Segment6, source.Segment7, source.Segment8, source.Segment9, source.Segment10, source.Segment11, source.Segment12, source.Segment13, source.Segment14, source.Segment15,
    source.BudgetAmount, source.ReleasedAmount, source.WarrantAmount, source.CommitmentAmount, source.ActualAmount, source.AvailableBalance, source.ExecutionRate,
    source.PriorYearBudgetAmount, source.PriorYearActualAmount, source.CurrencyCode, source.SensitivityLabel, source.DataLoadBatchCode, source.SourceSystemCode,
    source.IsActive, source.LoadedBy, source.Notes
FROM #MonthlyRows AS source
LEFT JOIN dbo.tblAIBudgetLedgerAnalysisSource AS target
    ON target.SourceRowReference = source.SourceRowReference
WHERE target.SourceRowReference IS NULL;

DECLARE @InsertedRowCount INT = @@ROWCOUNT;

SELECT
    @UpdatedRowCount AS UpdatedRowCount,
    @InsertedRowCount AS InsertedRowCount,
    (@UpdatedRowCount + @InsertedRowCount) AS AffectedRowCount;

SELECT
    COUNT(1) AS TargetActiveRowCount,
    SUM(BudgetAmount) AS TotalBudgetAmount,
    SUM(ActualAmount) AS TotalActualAmount
FROM dbo.vwAI_BudgetLedgerGeneric
WHERE FiscalYearID = @TargetFiscalYearID
  AND DataLoadBatchCode = @DataLoadBatchCode;';
END;

EXEC sp_executesql
    @Sql,
    N'@TargetFiscalYearID INT, @BudgetID INT, @VersionID INT, @IncludeZeroRows BIT, @DataLoadBatchCode NVARCHAR(120), @LoadedBy INT',
    @TargetFiscalYearID = @TargetFiscalYearID,
    @BudgetID = @BudgetID,
    @VersionID = @VersionID,
    @IncludeZeroRows = @IncludeZeroRows,
    @DataLoadBatchCode = @DataLoadBatchCode,
    @LoadedBy = @LoadedBy;
GO
