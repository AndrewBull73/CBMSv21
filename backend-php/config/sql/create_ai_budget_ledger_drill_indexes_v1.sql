/*
    Add indexes for fast budget-line drill-through from ML predictions.
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerAnalysisSource', N'U') IS NULL
BEGIN
    RAISERROR('dbo.tblAIBudgetLedgerAnalysisSource was not found.', 16, 1);
    RETURN;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblAIBudgetLedgerAnalysisSource')
      AND name = N'IX_tblAIBudgetLedgerAnalysisSource_LineDrill'
)
BEGIN
    CREATE INDEX IX_tblAIBudgetLedgerAnalysisSource_LineDrill
        ON dbo.tblAIBudgetLedgerAnalysisSource
            (FiscalYearID, Segment1, Segment5, Segment11, IsActive, BudgetVersionID, PeriodNo)
        INCLUDE
            (BudgetLedgerAnalysisID, SourceRowReference, CurrencyCode, BudgetAmount, ActualAmount,
             AvailableBalance, ReleasedAmount, WarrantAmount, CommitmentAmount, ExecutionRate, PostingDate,
             DataLoadBatchCode, SourceSystemCode);
END;
GO
