/*
Purpose
-------
Migrate selected rows from CBMSGOL3.dbo.tblBudgetTransactions into
CBMSv2.dbo.tblTransactionInput using the draft mapping found in the
user's local autorecover SQL files.

Default behavior is PREVIEW ONLY.

How to use
----------
1. Review the parameter block below.
2. Run once in preview mode and confirm the row count/sample output.
3. Set @PreviewOnly = 0 to perform the insert.

Notes
-----
- This script does not delete from dbo.tblTransactionInput.
- A simple NOT EXISTS guard is included to reduce duplicate inserts.
- The target database should be the CBMSv2/CBMSv21 database.
*/

USE [CBMSv2];
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

DECLARE @SourceDatabase sysname = N'CBMSGOL3';
DECLARE @SourceSchema sysname = N'dbo';
DECLARE @SourceTable sysname = N'tblBudgetTransactions';

DECLARE @BudgetID int = 8;
DECLARE @VersionID int = 5;
DECLARE @TargetFiscalYearID int = 2026;
DECLARE @TargetTransactionTypeCode nvarchar(4) = N'';

DECLARE @RecordTypeCode char(2) = 'BU';
DECLARE @TAccountCode nvarchar(50) = N'DR';
DECLARE @TAccountOperator int = 1;
DECLARE @RecurrentFlag char(1) = 'Y';
DECLARE @CostItemID int = 0;
DECLARE @CalculationID int = 2;
DECLARE @CurrencyInpC nvarchar(3) = N'MAL';
DECLARE @DeletedFlag char(1) = 'N';
DECLARE @UOMCodeInpC nvarchar(50) = N'APS1';
DECLARE @CreatedBy int = 1;

DECLARE @PreviewOnly bit = 1;
DECLARE @SkipExisting bit = 1;

IF DB_ID(@SourceDatabase) IS NULL
BEGIN
    THROW 51000, 'Source database does not exist or is not visible to this login.', 1;
END;

DECLARE @Sql nvarchar(max) = N'
;WITH SourceRows AS (
    SELECT
        src.BudgetTransactionID,
        src.BudgetRecordID,
        src.VersionID,
        src.TransactionType,
        src.AccountCode,
        src.GLCode,
        src.BPY0Calculation,
        src.BP1,
        src.BP2,
        src.BP3,
        src.BP4,
        src.BP5,
        src.BP6,
        src.BP7,
        src.BP8,
        src.BP9,
        src.BP10,
        src.BP11,
        src.BP12,
        src.BP2Cost,
        src.BP3Cost,
        src.BP4Cost,
        src.BP5Cost,
        src.BP6Cost,
        src.BP7Cost,
        src.BP8Cost,
        src.BP9Cost,
        src.BP10Cost,
        src.BP11Cost,
        src.BP12Cost,
        src.Segment1,
        src.Segment2,
        src.Segment3,
        src.Segment4,
        src.Segment5,
        src.Segment6,
        src.Segment7,
        src.Segment8,
        src.Segment9,
        src.Segment10,
        src.Segment11,
        src.Segment12,
        src.Segment13,
        src.Segment14,
        src.Segment15
    FROM ' + QUOTENAME(@SourceDatabase) + N'.' + QUOTENAME(@SourceSchema) + N'.' + QUOTENAME(@SourceTable) + N' AS src
    WHERE src.BudgetID = @BudgetID
      AND src.VersionID = @VersionID
),
MappedRows AS (
    SELECT
        src.BudgetTransactionID,
        HeadRecordID = src.BudgetRecordID,
        RecordTypeCode = @RecordTypeCode,
        FiscalYearID = @TargetFiscalYearID,
        VersionID = src.VersionID,
        DataObjectCode = COALESCE(src.Segment1, '''') + COALESCE(src.Segment2, '''') + COALESCE(src.Segment3, ''''),
        TransactionTypeCode = COALESCE(NULLIF(@TargetTransactionTypeCode, ''''), src.TransactionType),
        AccountCode = src.AccountCode,
        GLAccountCode = CONVERT(nvarchar(50), src.GLCode),
        TAccountCode = @TAccountCode,
        TAccountOperator = @TAccountOperator,
        RecurrentFlag = @RecurrentFlag,
        CostItemID = @CostItemID,
        CalculationID = @CalculationID,
        TransactionStartDate = CAST(NULL AS datetime),
        TransactionEndDate = CAST(NULL AS datetime),
        CurrencyInpC = @CurrencyInpC,
        DeletedFlag = @DeletedFlag,
        UOMCodeInpC = @UOMCodeInpC,
        PY5UOMRate = CAST(0 AS decimal(18,6)),
        PY4UOMRate = CAST(0 AS decimal(18,6)),
        PY3UOMRate = CAST(0 AS decimal(18,6)),
        PY2UOMRate = CAST(0 AS decimal(18,6)),
        PY1UOMRate = CAST(0 AS decimal(18,6)),
        BP1UOMRate = CAST(0 AS decimal(18,6)),
        BP2UOMRate = CAST(0 AS decimal(18,6)),
        BP3UOMRate = CAST(0 AS decimal(18,6)),
        BP4UOMRate = CAST(0 AS decimal(18,6)),
        BP5UOMRate = CAST(0 AS decimal(18,6)),
        BP6UOMRate = CAST(0 AS decimal(18,6)),
        BP7UOMRate = CAST(0 AS decimal(18,6)),
        BP8UOMRate = CAST(0 AS decimal(18,6)),
        BP9UOMRate = CAST(0 AS decimal(18,6)),
        BP10UOMRate = CAST(0 AS decimal(18,6)),
        BP11UOMRate = CAST(0 AS decimal(18,6)),
        BP12UOMRate = CAST(0 AS decimal(18,6)),
        BPOY1UOMRate = CAST(0 AS decimal(18,6)),
        BPOY2UOMRate = CAST(0 AS decimal(18,6)),
        BPOY3UOMRate = CAST(0 AS decimal(18,6)),
        BPOY4UOMRate = CAST(0 AS decimal(18,6)),
        BPOY5UOMRate = CAST(0 AS decimal(18,6)),
        BPOY6UOMRate = CAST(0 AS decimal(18,6)),
        BPOY7UOMRate = CAST(0 AS decimal(18,6)),
        BPOY8UOMRate = CAST(0 AS decimal(18,6)),
        BPOY9UOMRate = CAST(0 AS decimal(18,6)),
        BPOY10UOMRate = CAST(0 AS decimal(18,6)),
        BP1InpN = CAST(COALESCE(src.BPY0Calculation, 0) AS decimal(18,6)),
        BP2InpN = CAST(COALESCE(src.BP2Cost, 0) AS decimal(18,6)),
        BP3InpN = CAST(COALESCE(src.BP3Cost, 0) AS decimal(18,6)),
        BP4InpN = CAST(COALESCE(src.BP4Cost, 0) AS decimal(18,6)),
        BP5InpN = CAST(COALESCE(src.BP5Cost, 0) AS decimal(18,6)),
        BP6InpN = CAST(COALESCE(src.BP6Cost, 0) AS decimal(18,6)),
        BP7InpN = CAST(COALESCE(src.BP7Cost, 0) AS decimal(18,6)),
        BP8InpN = CAST(COALESCE(src.BP8Cost, 0) AS decimal(18,6)),
        BP9InpN = CAST(COALESCE(src.BP9Cost, 0) AS decimal(18,6)),
        BP10InpN = CAST(COALESCE(src.BP10Cost, 0) AS decimal(18,6)),
        BP11InpN = CAST(COALESCE(src.BP11Cost, 0) AS decimal(18,6)),
        BP12InpN = CAST(COALESCE(src.BP12Cost, 0) AS decimal(18,6)),
        BPQ1InpN = CAST(COALESCE(src.BP1, 0) + COALESCE(src.BP2, 0) + COALESCE(src.BP3, 0) AS decimal(18,6)),
        BPQ2InpN = CAST(COALESCE(src.BP4, 0) + COALESCE(src.BP5, 0) AS decimal(18,6)),
        BPQ3InpN = CAST(COALESCE(src.BP6, 0) AS decimal(18,6)),
        BPQ4InpN = CAST(COALESCE(src.BP7, 0) + COALESCE(src.BP8, 0) + COALESCE(src.BP9, 0) AS decimal(18,6)),
        BPTotalInpN = CAST(
            COALESCE(src.BP1, 0) + COALESCE(src.BP2, 0) + COALESCE(src.BP3, 0) +
            COALESCE(src.BP4, 0) + COALESCE(src.BP5, 0) + COALESCE(src.BP6, 0) +
            COALESCE(src.BP7, 0) + COALESCE(src.BP8, 0) + COALESCE(src.BP9, 0) +
            COALESCE(src.BP10, 0) + COALESCE(src.BP11, 0) + COALESCE(src.BP12, 0)
            AS decimal(18,6)
        ),
        Segment1Code = src.Segment1,
        Segment2Code = src.Segment2,
        Segment3Code = src.Segment3,
        Segment4Code = src.Segment4,
        Segment5Code = src.Segment5,
        Segment6Code = src.Segment6,
        Segment7Code = src.Segment7,
        Segment8Code = src.Segment8,
        Segment9Code = src.Segment9,
        Segment10Code = src.Segment10,
        Segment11Code = src.Segment11,
        Segment12Code = src.Segment12,
        Segment13Code = src.Segment13,
        Segment14Code = src.Segment14,
        Segment15Code = src.Segment15,
        CreatedBy = @CreatedBy
    FROM SourceRows src
)
SELECT *
INTO #MappedRows
FROM MappedRows;

SELECT COUNT(*) AS CandidateRows
FROM #MappedRows;

SELECT TOP (20) *
FROM #MappedRows
ORDER BY HeadRecordID, AccountCode;

IF @PreviewOnly = 0
BEGIN
    INSERT INTO dbo.tblTransactionInput (
        HeadRecordID,
        RecordTypeCode,
        FiscalYearID,
        VersionID,
        DataObjectCode,
        TransactionTypeCode,
        AccountCode,
        GLAccountCode,
        TAccountCode,
        TAccountOperator,
        RecurrentFlag,
        CostItemID,
        CalculationID,
        TransactionStartDate,
        TransactionEndDate,
        CurrencyInpC,
        DeletedFlag,
        UOMCodeInpC,
        PY5UOMRate, PY4UOMRate, PY3UOMRate, PY2UOMRate, PY1UOMRate,
        BP1UOMRate, BP2UOMRate, BP3UOMRate, BP4UOMRate, BP5UOMRate,
        BP6UOMRate, BP7UOMRate, BP8UOMRate, BP9UOMRate, BP10UOMRate,
        BP11UOMRate, BP12UOMRate,
        BPOY1UOMRate, BPOY2UOMRate, BPOY3UOMRate, BPOY4UOMRate, BPOY5UOMRate,
        BPOY6UOMRate, BPOY7UOMRate, BPOY8UOMRate, BPOY9UOMRate, BPOY10UOMRate,
        BP1InpN, BP2InpN, BP3InpN, BP4InpN, BP5InpN, BP6InpN,
        BP7InpN, BP8InpN, BP9InpN, BP10InpN, BP11InpN, BP12InpN,
        BPQ1InpN, BPQ2InpN, BPQ3InpN, BPQ4InpN,
        BPTotalInpN,
        Segment1Code, Segment2Code, Segment3Code, Segment4Code, Segment5Code,
        Segment6Code, Segment7Code, Segment8Code, Segment9Code, Segment10Code,
        Segment11Code, Segment12Code, Segment13Code, Segment14Code, Segment15Code,
        CreatedBy
    )
    SELECT
        mr.HeadRecordID,
        mr.RecordTypeCode,
        mr.FiscalYearID,
        mr.VersionID,
        mr.DataObjectCode,
        mr.TransactionTypeCode,
        mr.AccountCode,
        mr.GLAccountCode,
        mr.TAccountCode,
        mr.TAccountOperator,
        mr.RecurrentFlag,
        mr.CostItemID,
        mr.CalculationID,
        mr.TransactionStartDate,
        mr.TransactionEndDate,
        mr.CurrencyInpC,
        mr.DeletedFlag,
        mr.UOMCodeInpC,
        mr.PY5UOMRate, mr.PY4UOMRate, mr.PY3UOMRate, mr.PY2UOMRate, mr.PY1UOMRate,
        mr.BP1UOMRate, mr.BP2UOMRate, mr.BP3UOMRate, mr.BP4UOMRate, mr.BP5UOMRate,
        mr.BP6UOMRate, mr.BP7UOMRate, mr.BP8UOMRate, mr.BP9UOMRate, mr.BP10UOMRate,
        mr.BP11UOMRate, mr.BP12UOMRate,
        mr.BPOY1UOMRate, mr.BPOY2UOMRate, mr.BPOY3UOMRate, mr.BPOY4UOMRate, mr.BPOY5UOMRate,
        mr.BPOY6UOMRate, mr.BPOY7UOMRate, mr.BPOY8UOMRate, mr.BPOY9UOMRate, mr.BPOY10UOMRate,
        mr.BP1InpN, mr.BP2InpN, mr.BP3InpN, mr.BP4InpN, mr.BP5InpN, mr.BP6InpN,
        mr.BP7InpN, mr.BP8InpN, mr.BP9InpN, mr.BP10InpN, mr.BP11InpN, mr.BP12InpN,
        mr.BPQ1InpN, mr.BPQ2InpN, mr.BPQ3InpN, mr.BPQ4InpN,
        mr.BPTotalInpN,
        mr.Segment1Code, mr.Segment2Code, mr.Segment3Code, mr.Segment4Code, mr.Segment5Code,
        mr.Segment6Code, mr.Segment7Code, mr.Segment8Code, mr.Segment9Code, mr.Segment10Code,
        mr.Segment11Code, mr.Segment12Code, mr.Segment13Code, mr.Segment14Code, mr.Segment15Code,
        mr.CreatedBy
    FROM #MappedRows mr
    WHERE @SkipExisting = 0
       OR NOT EXISTS (
            SELECT 1
            FROM dbo.tblTransactionInput ti
            WHERE ti.HeadRecordID = mr.HeadRecordID
              AND ti.VersionID = mr.VersionID
              AND ti.DataObjectCode = mr.DataObjectCode
              AND ti.TransactionTypeCode = mr.TransactionTypeCode
              AND ti.AccountCode = mr.AccountCode
       );

    SELECT @@ROWCOUNT AS InsertedRows;
END
ELSE
BEGIN
    PRINT ''Preview only. Set @PreviewOnly = 0 to insert rows.'';
END;
';

EXEC sp_executesql
    @Sql,
    N'@BudgetID int,
      @VersionID int,
      @TargetFiscalYearID int,
      @TargetTransactionTypeCode nvarchar(4),
      @RecordTypeCode char(2),
      @TAccountCode nvarchar(50),
      @TAccountOperator int,
      @RecurrentFlag char(1),
      @CostItemID int,
      @CalculationID int,
      @CurrencyInpC nvarchar(3),
      @DeletedFlag char(1),
      @UOMCodeInpC nvarchar(50),
      @CreatedBy int,
      @PreviewOnly bit,
    @SkipExisting bit',
    @BudgetID = @BudgetID,
    @VersionID = @VersionID,
    @TargetFiscalYearID = @TargetFiscalYearID,
    @TargetTransactionTypeCode = @TargetTransactionTypeCode,
    @RecordTypeCode = @RecordTypeCode,
    @TAccountCode = @TAccountCode,
    @TAccountOperator = @TAccountOperator,
    @RecurrentFlag = @RecurrentFlag,
    @CostItemID = @CostItemID,
    @CalculationID = @CalculationID,
    @CurrencyInpC = @CurrencyInpC,
    @DeletedFlag = @DeletedFlag,
    @UOMCodeInpC = @UOMCodeInpC,
    @CreatedBy = @CreatedBy,
    @PreviewOnly = @PreviewOnly,
    @SkipExisting = @SkipExisting;
GO
