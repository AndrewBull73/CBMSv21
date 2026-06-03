USE [CBMSv2];
GO

/*
Small test harness for dbo.spCheckCeilingBalance
- Shows before/after balances
- Executes ceiling check
- Shows status and balance deltas
*/

DECLARE @FiscalYearID int = 2026;
DECLARE @VersionID int = 1;
DECLARE @TransactionID int = 2;
DECLARE @UpdatedBy int = 1;

DECLARE @CeilingStatusCheck nvarchar(50);
DECLARE @ErrorMessage nvarchar(500);
DECLARE @CeilingDefinitionID int;

IF OBJECT_ID('tempdb..#before') IS NOT NULL DROP TABLE #before;
IF OBJECT_ID('tempdb..#after') IS NOT NULL DROP TABLE #after;

-- Resolve matched ceiling definition exactly as sproc does.
SELECT TOP 1
    @CeilingDefinitionID = cd.CeilingDefinitionID
FROM dbo.tblCeilingDefinition cd
INNER JOIN dbo.tblTransactionInput ti
    ON ti.TransactionID = @TransactionID
WHERE cd.FiscalYearID = @FiscalYearID
  AND cd.VersionID = @VersionID
  AND cd.ActiveFlag = 1
  AND cd.ApprovedFlag = 1
  AND (cd.DataObjectCode IS NULL OR cd.DataObjectCode = N'' OR cd.DataObjectCode = ti.DataObjectCode)
  AND (cd.TransactionTypeCode IS NULL OR cd.TransactionTypeCode = N'' OR cd.TransactionTypeCode = ti.TransactionTypeCode)
  AND (cd.Segment1Code IS NULL OR cd.Segment1Code = N'' OR cd.Segment1Code = ti.Segment1Code)
  AND (cd.Segment2Code IS NULL OR cd.Segment2Code = N'' OR cd.Segment2Code = ti.Segment2Code)
  AND (cd.Segment3Code IS NULL OR cd.Segment3Code = N'' OR cd.Segment3Code = ti.Segment3Code)
  AND (cd.Segment4Code IS NULL OR cd.Segment4Code = N'' OR cd.Segment4Code = ti.Segment4Code)
  AND (cd.Segment5Code IS NULL OR cd.Segment5Code = N'' OR cd.Segment5Code = ti.Segment5Code)
  AND (cd.Segment6Code IS NULL OR cd.Segment6Code = N'' OR cd.Segment6Code = ti.Segment6Code)
  AND (cd.Segment7Code IS NULL OR cd.Segment7Code = N'' OR cd.Segment7Code = ti.Segment7Code)
  AND (cd.Segment8Code IS NULL OR cd.Segment8Code = N'' OR cd.Segment8Code = ti.Segment8Code)
  AND (cd.Segment9Code IS NULL OR cd.Segment9Code = N'' OR cd.Segment9Code = ti.Segment9Code)
  AND (cd.Segment10Code IS NULL OR cd.Segment10Code = N'' OR cd.Segment10Code = ti.Segment10Code)
  AND (cd.Segment11Code IS NULL OR cd.Segment11Code = N'' OR cd.Segment11Code = ti.Segment11Code)
  AND (cd.Segment12Code IS NULL OR cd.Segment12Code = N'' OR cd.Segment12Code = ti.Segment12Code)
  AND (cd.Segment13Code IS NULL OR cd.Segment13Code = N'' OR cd.Segment13Code = ti.Segment13Code)
  AND (cd.Segment14Code IS NULL OR cd.Segment14Code = N'' OR cd.Segment14Code = ti.Segment14Code)
  AND (cd.Segment15Code IS NULL OR cd.Segment15Code = N'' OR cd.Segment15Code = ti.Segment15Code)
  AND (cd.Segment16Code IS NULL OR cd.Segment16Code = N'' OR cd.Segment16Code = ti.Segment16Code)
  AND (cd.Segment17Code IS NULL OR cd.Segment17Code = N'' OR cd.Segment17Code = ti.Segment17Code)
  AND (cd.Segment18Code IS NULL OR cd.Segment18Code = N'' OR cd.Segment18Code = ti.Segment18Code)
  AND (cd.Segment19Code IS NULL OR cd.Segment19Code = N'' OR cd.Segment19Code = ti.Segment19Code)
  AND (cd.Segment20Code IS NULL OR cd.Segment20Code = N'' OR cd.Segment20Code = ti.Segment20Code)
ORDER BY
    (
        CASE WHEN cd.DataObjectCode IS NULL OR cd.DataObjectCode = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.TransactionTypeCode IS NULL OR cd.TransactionTypeCode = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment1Code IS NULL OR cd.Segment1Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment2Code IS NULL OR cd.Segment2Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment3Code IS NULL OR cd.Segment3Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment4Code IS NULL OR cd.Segment4Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment5Code IS NULL OR cd.Segment5Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment6Code IS NULL OR cd.Segment6Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment7Code IS NULL OR cd.Segment7Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment8Code IS NULL OR cd.Segment8Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment9Code IS NULL OR cd.Segment9Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment10Code IS NULL OR cd.Segment10Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment11Code IS NULL OR cd.Segment11Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment12Code IS NULL OR cd.Segment12Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment13Code IS NULL OR cd.Segment13Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment14Code IS NULL OR cd.Segment14Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment15Code IS NULL OR cd.Segment15Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment16Code IS NULL OR cd.Segment16Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment17Code IS NULL OR cd.Segment17Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment18Code IS NULL OR cd.Segment18Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment19Code IS NULL OR cd.Segment19Code = N'' THEN 0 ELSE 1 END +
        CASE WHEN cd.Segment20Code IS NULL OR cd.Segment20Code = N'' THEN 0 ELSE 1 END
    ) DESC,
    cd.Priority ASC,
    cd.CeilingDefinitionID ASC;

IF @CeilingDefinitionID IS NULL
BEGIN
    SELECT 'NO MATCHING CEILING DEFINITION' AS HarnessStatus,
           @TransactionID AS TransactionID,
           @FiscalYearID AS FiscalYearID,
           @VersionID AS VersionID;
    RETURN;
END

SELECT
    cb.CeilingDefinitionID,
    cb.BalanceBP1, cb.BalanceBP2, cb.BalanceBP3, cb.BalanceBP4, cb.BalanceBP5, cb.BalanceBP6,
    cb.BalanceBP7, cb.BalanceBP8, cb.BalanceBP9, cb.BalanceBP10, cb.BalanceBP11, cb.BalanceBP12,
    cb.BalanceBPTotal,
    cb.LastTransactionID,
    cb.UpdatedDate
INTO #before
FROM dbo.tblCeilingBalance cb
WHERE cb.CeilingDefinitionID = @CeilingDefinitionID;

EXEC dbo.spCheckCeilingBalance
    @FiscalYearID = @FiscalYearID,
    @VersionID = @VersionID,
    @TransactionID = @TransactionID,
    @UpdatedBy = @UpdatedBy,
    @CeilingStatusCheck = @CeilingStatusCheck OUTPUT,
    @ErrorMessage = @ErrorMessage OUTPUT;

SELECT
    cb.CeilingDefinitionID,
    cb.BalanceBP1, cb.BalanceBP2, cb.BalanceBP3, cb.BalanceBP4, cb.BalanceBP5, cb.BalanceBP6,
    cb.BalanceBP7, cb.BalanceBP8, cb.BalanceBP9, cb.BalanceBP10, cb.BalanceBP11, cb.BalanceBP12,
    cb.BalanceBPTotal,
    cb.LastTransactionID,
    cb.UpdatedDate
INTO #after
FROM dbo.tblCeilingBalance cb
WHERE cb.CeilingDefinitionID = @CeilingDefinitionID;

SELECT
    @CeilingStatusCheck AS CeilingStatusCheck,
    @ErrorMessage AS ErrorMessage,
    @CeilingDefinitionID AS CeilingDefinitionID,
    @TransactionID AS TransactionID,
    @FiscalYearID AS FiscalYearID,
    @VersionID AS VersionID;

SELECT
    'BEFORE' AS BalanceState,
    *
FROM #before;

SELECT
    'AFTER' AS BalanceState,
    *
FROM #after;

SELECT
    ISNULL(a.CeilingDefinitionID, b.CeilingDefinitionID) AS CeilingDefinitionID,
    ISNULL(a.BalanceBP1,0) - ISNULL(b.BalanceBP1,0) AS DeltaBP1,
    ISNULL(a.BalanceBP2,0) - ISNULL(b.BalanceBP2,0) AS DeltaBP2,
    ISNULL(a.BalanceBP3,0) - ISNULL(b.BalanceBP3,0) AS DeltaBP3,
    ISNULL(a.BalanceBP4,0) - ISNULL(b.BalanceBP4,0) AS DeltaBP4,
    ISNULL(a.BalanceBP5,0) - ISNULL(b.BalanceBP5,0) AS DeltaBP5,
    ISNULL(a.BalanceBP6,0) - ISNULL(b.BalanceBP6,0) AS DeltaBP6,
    ISNULL(a.BalanceBP7,0) - ISNULL(b.BalanceBP7,0) AS DeltaBP7,
    ISNULL(a.BalanceBP8,0) - ISNULL(b.BalanceBP8,0) AS DeltaBP8,
    ISNULL(a.BalanceBP9,0) - ISNULL(b.BalanceBP9,0) AS DeltaBP9,
    ISNULL(a.BalanceBP10,0) - ISNULL(b.BalanceBP10,0) AS DeltaBP10,
    ISNULL(a.BalanceBP11,0) - ISNULL(b.BalanceBP11,0) AS DeltaBP11,
    ISNULL(a.BalanceBP12,0) - ISNULL(b.BalanceBP12,0) AS DeltaBP12,
    ISNULL(a.BalanceBPTotal,0) - ISNULL(b.BalanceBPTotal,0) AS DeltaBPTotal
FROM #after a
FULL OUTER JOIN #before b
    ON a.CeilingDefinitionID = b.CeilingDefinitionID;
GO
