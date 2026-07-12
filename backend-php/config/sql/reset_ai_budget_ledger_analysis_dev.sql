/*
    DEVELOPMENT ONLY reset for the AI Budget Ledger analysis source.

    This drops the derived views and dbo.tblAIBudgetLedgerAnalysisSource so the table can be
    recreated using the generic Segment1..Segment15-only design.

    Do not run this in production.
    This removes loaded AI budget ledger analysis rows and requires a reload.

    Recommended sequence after running this script:
    1. create_ai_budget_ledger_analysis_dataset_v1.sql
    2. create_ai_budget_ledger_semantic_mapping_v1.sql
    3. load_ai_budget_ledger_from_cbmsgol_tblBudgetData.sql with @PreviewOnly = 0
    4. create_ai_budget_ledger_version_role_mapping_v1.sql
    5. Populate dbo.tblAIBudgetLedgerVersionRoleMap
    6. create_ml_budget_execution_risk_training_view_v1.sql
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.vwML_BudgetExecutionRiskTraining', N'V') IS NOT NULL
BEGIN
    DROP VIEW dbo.vwML_BudgetExecutionRiskTraining;
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerMultiYearRoleTrend', N'V') IS NOT NULL
BEGIN
    DROP VIEW dbo.vwAI_BudgetLedgerMultiYearRoleTrend;
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerSingleYearRoleAnalysis', N'V') IS NOT NULL
BEGIN
    DROP VIEW dbo.vwAI_BudgetLedgerSingleYearRoleAnalysis;
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerVersionedAnalysis', N'V') IS NOT NULL
BEGIN
    DROP VIEW dbo.vwAI_BudgetLedgerVersionedAnalysis;
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerVersionRoleCandidates', N'V') IS NOT NULL
BEGIN
    DROP VIEW dbo.vwAI_BudgetLedgerVersionRoleCandidates;
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerAnalysis', N'V') IS NOT NULL
BEGIN
    DROP VIEW dbo.vwAI_BudgetLedgerAnalysis;
END;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerGeneric', N'V') IS NOT NULL
BEGIN
    DROP VIEW dbo.vwAI_BudgetLedgerGeneric;
END;
GO

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerAnalysisSource', N'U') IS NOT NULL
BEGIN
    DROP TABLE dbo.tblAIBudgetLedgerAnalysisSource;
END;
GO

PRINT 'Development reset complete. Re-run the AI budget ledger create, semantic mapping, load, version-role mapping, and ML view scripts.';
GO
