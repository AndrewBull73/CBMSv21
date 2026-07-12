/*
    Create the ML and executive risk view for budget execution.

    Design rule:
    - Raw BudgetVersionID values are not compared directly.
    - Annual BudgetAmount comes from the fiscal year's mapped budget baseline role.
    - Period ActualAmount comes from the fiscal year's mapped execution actuals role.
    - Execution risk compares cumulative actuals to the annual mapped baseline budget and the
      expected year-to-date execution rate.

    Required setup before running:
    1. create_ai_budget_ledger_analysis_dataset_v1.sql
    2. create_ai_budget_ledger_semantic_mapping_v1.sql
    3. load_ai_budget_ledger_from_cbmsgol_tblBudgetData.sql
    4. create_ai_budget_ledger_version_role_mapping_v1.sql
    5. Populate dbo.tblAIBudgetLedgerVersionRoleMap for the fiscal year(s) being analyzed.
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.vwAI_BudgetLedgerAnalysis', N'V') IS NULL
BEGIN
    RAISERROR('dbo.vwAI_BudgetLedgerAnalysis was not found. Run create_ai_budget_ledger_semantic_mapping_v1.sql first.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.tblAIBudgetLedgerVersionRoleMap', N'U') IS NULL
BEGIN
    RAISERROR('dbo.tblAIBudgetLedgerVersionRoleMap was not found. Run create_ai_budget_ledger_version_role_mapping_v1.sql first.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.vwML_BudgetExecutionRiskTraining', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwML_BudgetExecutionRiskTraining AS SELECT CAST(NULL AS INT) AS FiscalYearID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwML_BudgetExecutionRiskTraining
AS
WITH Periods AS
(
    SELECT 1 AS PeriodNo UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
    UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
),
RoleRows AS
(
    SELECT
        a.FiscalYearID,
        a.BudgetVersionID,
        a.PeriodNo,
        Segment1 = NULLIF(LTRIM(RTRIM(a.Segment1)), N''''),
        Segment5 = NULLIF(LTRIM(RTRIM(a.ProgramCode)), N''''),
        Segment11 = NULLIF(LTRIM(RTRIM(a.EconomicCode)), N''''),
        ProgramCode = NULLIF(LTRIM(RTRIM(a.ProgramCode)), N''''),
        EconomicCode = NULLIF(LTRIM(RTRIM(a.EconomicCode)), N''''),
        a.CurrencyCode,
        a.BudgetAmount,
        a.ReleasedAmount,
        a.WarrantAmount,
        a.CommitmentAmount,
        a.ActualAmount,
        VersionTypeID = COALESCE(m.VersionTypeID, v.VersionTypeID),
        m.VersionRoleCode,
        m.VersionRoleName,
        m.IsBudgetBaseline,
        m.IsExecutionActuals
    FROM dbo.vwAI_BudgetLedgerAnalysis a
    INNER JOIN dbo.tblAIBudgetLedgerVersionRoleMap m
        ON m.FiscalYearID = a.FiscalYearID
       AND m.BudgetVersionID = a.BudgetVersionID
       AND m.ActiveFlag = 1
    LEFT JOIN dbo.tblVersions v
        ON v.FiscalYearID = a.FiscalYearID
       AND v.VersionID = a.BudgetVersionID
    WHERE a.PeriodNo BETWEEN 1 AND 12
),
AnnualBudgetBaseline AS
(
    SELECT
        FiscalYearID,
        Segment1,
        Segment5,
        Segment11,
        ProgramCode,
        EconomicCode,
        CurrencyCode,
        BudgetBaselineVersionID = MIN(BudgetVersionID),
        BudgetBaselineVersionTypeID = MIN(VersionTypeID),
        BudgetBaselineRoleCode = MIN(VersionRoleCode),
        BudgetBaselineRoleName = MIN(VersionRoleName),
        AnnualBudgetAmount = CAST(SUM(BudgetAmount) AS DECIMAL(28,2)),
        AnnualReleasedAmount = CAST(SUM(ReleasedAmount) AS DECIMAL(28,2)),
        AnnualWarrantAmount = CAST(SUM(WarrantAmount) AS DECIMAL(28,2))
    FROM RoleRows
    WHERE IsBudgetBaseline = 1
    GROUP BY
        FiscalYearID,
        Segment1,
        Segment5,
        Segment11,
        ProgramCode,
        EconomicCode,
        CurrencyCode
),
PeriodExecutionActuals AS
(
    SELECT
        FiscalYearID,
        PeriodNo,
        Segment1,
        Segment5,
        Segment11,
        ProgramCode,
        EconomicCode,
        CurrencyCode,
        ExecutionActualsVersionID = MIN(BudgetVersionID),
        ExecutionActualsVersionTypeID = MIN(VersionTypeID),
        ExecutionActualsRoleCode = MIN(VersionRoleCode),
        ExecutionActualsRoleName = MIN(VersionRoleName),
        CommitmentAmount = CAST(SUM(CommitmentAmount) AS DECIMAL(28,2)),
        ActualAmount = CAST(SUM(ActualAmount) AS DECIMAL(28,2))
    FROM RoleRows
    WHERE IsExecutionActuals = 1
    GROUP BY
        FiscalYearID,
        PeriodNo,
        Segment1,
        Segment5,
        Segment11,
        ProgramCode,
        EconomicCode,
        CurrencyCode
),
AnalysisKeys AS
(
    SELECT FiscalYearID, Segment1, Segment5, Segment11, ProgramCode, EconomicCode, CurrencyCode
    FROM AnnualBudgetBaseline
    UNION
    SELECT FiscalYearID, Segment1, Segment5, Segment11, ProgramCode, EconomicCode, CurrencyCode
    FROM PeriodExecutionActuals
),
PeriodAggregate AS
(
    SELECT
        k.FiscalYearID,
        p.PeriodNo,
        k.Segment1,
        Segment2 = CAST(NULL AS NVARCHAR(50)),
        Segment3 = CAST(NULL AS NVARCHAR(50)),
        Segment4 = CAST(NULL AS NVARCHAR(50)),
        k.Segment5,
        Segment6 = CAST(NULL AS NVARCHAR(50)),
        Segment7 = CAST(NULL AS NVARCHAR(50)),
        k.Segment11,
        k.ProgramCode,
        k.EconomicCode,
        k.CurrencyCode,
        b.BudgetBaselineVersionID,
        e.ExecutionActualsVersionID,
        BudgetVersionID = COALESCE(e.ExecutionActualsVersionID, b.BudgetBaselineVersionID),
        b.BudgetBaselineVersionTypeID,
        e.ExecutionActualsVersionTypeID,
        b.BudgetBaselineRoleCode,
        b.BudgetBaselineRoleName,
        e.ExecutionActualsRoleCode,
        e.ExecutionActualsRoleName,
        BudgetAmount = CAST(COALESCE(b.AnnualBudgetAmount, 0) AS DECIMAL(28,2)),
        PeriodBudgetAmount = CAST((COALESCE(b.AnnualBudgetAmount, 0) / 12.0) AS DECIMAL(28,2)),
        ReleasedAmount = CAST(COALESCE(b.AnnualReleasedAmount, 0) AS DECIMAL(28,2)),
        WarrantAmount = CAST(COALESCE(b.AnnualWarrantAmount, 0) AS DECIMAL(28,2)),
        CommitmentAmount = CAST(COALESCE(e.CommitmentAmount, 0) AS DECIMAL(28,2)),
        ActualAmount = CAST(COALESCE(e.ActualAmount, 0) AS DECIMAL(28,2))
    FROM AnalysisKeys k
    CROSS JOIN Periods p
    LEFT JOIN AnnualBudgetBaseline b
        ON b.FiscalYearID = k.FiscalYearID
       AND ISNULL(b.Segment1, N'''') = ISNULL(k.Segment1, N'''')
       AND ISNULL(b.ProgramCode, N'''') = ISNULL(k.ProgramCode, N'''')
       AND ISNULL(b.EconomicCode, N'''') = ISNULL(k.EconomicCode, N'''')
       AND ISNULL(b.CurrencyCode, N'''') = ISNULL(k.CurrencyCode, N'''')
    LEFT JOIN PeriodExecutionActuals e
        ON e.FiscalYearID = k.FiscalYearID
       AND e.PeriodNo = p.PeriodNo
       AND ISNULL(e.Segment1, N'''') = ISNULL(k.Segment1, N'''')
       AND ISNULL(e.ProgramCode, N'''') = ISNULL(k.ProgramCode, N'''')
       AND ISNULL(e.EconomicCode, N'''') = ISNULL(k.EconomicCode, N'''')
       AND ISNULL(e.CurrencyCode, N'''') = ISNULL(k.CurrencyCode, N'''')
),
PeriodTotals AS
(
    SELECT
        FiscalYearID,
        PeriodNo,
        TotalBudgetAmount = CAST(SUM(BudgetAmount) AS DECIMAL(28,2)),
        TotalActualAmount = CAST(SUM(ActualAmount) AS DECIMAL(28,2))
    FROM PeriodAggregate
    GROUP BY FiscalYearID, PeriodNo
),
WithCumulative AS
(
    SELECT
        p.*,
        CumulativeActualAmount = CAST(SUM(p.ActualAmount) OVER (
            PARTITION BY p.FiscalYearID, p.Segment1, p.ProgramCode, p.EconomicCode, p.CurrencyCode
            ORDER BY p.PeriodNo
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS DECIMAL(28,2)),
        CumulativeCommitmentAmount = CAST(SUM(p.CommitmentAmount) OVER (
            PARTITION BY p.FiscalYearID, p.Segment1, p.ProgramCode, p.EconomicCode, p.CurrencyCode
            ORDER BY p.PeriodNo
            ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
        ) AS DECIMAL(28,2)),
        PriorPeriodActualAmount = CAST(LAG(p.ActualAmount) OVER (
            PARTITION BY p.FiscalYearID, p.Segment1, p.ProgramCode, p.EconomicCode, p.CurrencyCode
            ORDER BY p.PeriodNo
        ) AS DECIMAL(28,2))
    FROM PeriodAggregate p
),
WithMeasures AS
(
    SELECT
        c.*,
        CumulativeBudgetAmount = CAST(c.BudgetAmount AS DECIMAL(28,2)),
        ExpectedExecutionRate = CAST((CAST(c.PeriodNo AS DECIMAL(18,6)) / 12.0) * 100.0 AS DECIMAL(18,6)),
        ExpectedActualToDateAmount = CAST(c.BudgetAmount * (CAST(c.PeriodNo AS DECIMAL(18,6)) / 12.0) AS DECIMAL(28,2)),
        AvailableBalance = CAST(c.BudgetAmount - c.CumulativeActualAmount - c.CumulativeCommitmentAmount AS DECIMAL(28,2)),
        ExecutionRate = CAST(CASE WHEN c.BudgetAmount <> 0 THEN (c.CumulativeActualAmount / NULLIF(c.BudgetAmount, 0)) * 100.0 ELSE NULL END AS DECIMAL(18,6)),
        BudgetSharePct = CAST(CASE WHEN t.TotalBudgetAmount <> 0 THEN (c.BudgetAmount / NULLIF(t.TotalBudgetAmount, 0)) * 100.0 ELSE NULL END AS DECIMAL(18,6)),
        ActualSharePct = CAST(CASE WHEN t.TotalActualAmount <> 0 THEN (c.ActualAmount / NULLIF(t.TotalActualAmount, 0)) * 100.0 ELSE NULL END AS DECIMAL(18,6)),
        PriorPeriodExecutionRate = CAST(CASE
            WHEN c.BudgetAmount <> 0 THEN (COALESCE(LAG(c.CumulativeActualAmount) OVER (
                PARTITION BY c.FiscalYearID, c.Segment1, c.ProgramCode, c.EconomicCode, c.CurrencyCode
                ORDER BY c.PeriodNo
            ), 0) / NULLIF(c.BudgetAmount, 0)) * 100.0
            ELSE NULL
        END AS DECIMAL(18,6))
    FROM WithCumulative c
    INNER JOIN PeriodTotals t
        ON t.FiscalYearID = c.FiscalYearID
       AND t.PeriodNo = c.PeriodNo
),
WithPriorYear AS
(
    SELECT
        m.*,
        PriorYearActualAmount = py.CumulativeActualAmount,
        PriorYearBudgetAmount = py.BudgetAmount
    FROM WithMeasures m
    LEFT JOIN WithMeasures py
        ON py.FiscalYearID = m.FiscalYearID - 1
       AND py.PeriodNo = m.PeriodNo
       AND ISNULL(py.Segment1, N'''') = ISNULL(m.Segment1, N'''')
       AND ISNULL(py.ProgramCode, N'''') = ISNULL(m.ProgramCode, N'''')
       AND ISNULL(py.EconomicCode, N'''') = ISNULL(m.EconomicCode, N'''')
       AND ISNULL(py.CurrencyCode, N'''') = ISNULL(m.CurrencyCode, N'''')
),
Scored AS
(
    SELECT
        *,
        CumulativeExecutionRate = ExecutionRate,
        VarianceAmount = CAST(CumulativeActualAmount - ExpectedActualToDateAmount AS DECIMAL(28,2)),
        VariancePct = CAST(CASE WHEN ExpectedActualToDateAmount <> 0 THEN ((CumulativeActualAmount - ExpectedActualToDateAmount) / NULLIF(ExpectedActualToDateAmount, 0)) * 100.0 ELSE NULL END AS DECIMAL(18,6)),
        PriorPeriodActualVariance = CAST(ActualAmount - COALESCE(PriorPeriodActualAmount, 0) AS DECIMAL(28,2)),
        PriorYearActualVariance = CAST(CASE WHEN PriorYearActualAmount IS NULL THEN NULL ELSE CumulativeActualAmount - PriorYearActualAmount END AS DECIMAL(28,2)),
        ActualSpikePct = CAST(CASE
            WHEN ABS(COALESCE(PriorPeriodActualAmount, 0)) > 0 THEN ((ActualAmount - PriorPeriodActualAmount) / NULLIF(ABS(PriorPeriodActualAmount), 0)) * 100.0
            WHEN ABS(COALESCE(PriorPeriodActualAmount, 0)) = 0 AND ABS(ActualAmount) >= 1000000.0 THEN 999999.0
            ELSE NULL
        END AS DECIMAL(18,6)),
        PriorYearActualChangePct = CAST(CASE
            WHEN ABS(COALESCE(PriorYearActualAmount, 0)) > 0 THEN ((CumulativeActualAmount - PriorYearActualAmount) / NULLIF(ABS(PriorYearActualAmount), 0)) * 100.0
            WHEN ABS(COALESCE(PriorYearActualAmount, 0)) = 0 AND ABS(CumulativeActualAmount) >= 1000000.0 THEN 999999.0
            ELSE NULL
        END AS DECIMAL(18,6)),
        MaterialityAmount = CAST(CASE
            WHEN ABS(COALESCE(BudgetAmount, 0)) >= ABS(COALESCE(CumulativeActualAmount, 0)) THEN ABS(COALESCE(BudgetAmount, 0))
            ELSE ABS(COALESCE(CumulativeActualAmount, 0))
        END AS DECIMAL(28,2)),
        IsActualWithoutBudget = CAST(CASE WHEN BudgetAmount = 0 AND ABS(CumulativeActualAmount) > 0 THEN 1 ELSE 0 END AS BIT),
        IsNegativeAvailableBalance = CAST(CASE WHEN AvailableBalance < 0 THEN 1 ELSE 0 END AS BIT),
        IsActualSpike = CAST(CASE
            WHEN ABS(ActualAmount) >= 1000000.0
             AND (ABS(COALESCE(PriorPeriodActualAmount, 0)) = 0 OR ABS(ActualAmount - COALESCE(PriorPeriodActualAmount, 0)) >= ABS(COALESCE(PriorPeriodActualAmount, 0)) * 2.0)
            THEN 1 ELSE 0 END AS BIT),
        IsPriorYearActualSpike = CAST(CASE
            WHEN ABS(CumulativeActualAmount) >= 1000000.0
             AND (PriorYearActualAmount IS NULL OR ABS(COALESCE(PriorYearActualAmount, 0)) = 0 OR ABS(CumulativeActualAmount - COALESCE(PriorYearActualAmount, 0)) >= ABS(COALESCE(PriorYearActualAmount, 0)) * 2.0)
            THEN 1 ELSE 0 END AS BIT),
        IsDormantLineActivity = CAST(CASE
            WHEN ABS(ActualAmount) >= 1000000.0
             AND ABS(COALESCE(PriorPeriodActualAmount, 0)) = 0
             AND ABS(COALESCE(PriorYearActualAmount, 0)) = 0
            THEN 1 ELSE 0 END AS BIT),
        IsBudgetWithoutExecution = CAST(CASE
            WHEN (CASE
                    WHEN ABS(COALESCE(BudgetAmount, 0)) >= ABS(COALESCE(CumulativeActualAmount, 0)) THEN ABS(COALESCE(BudgetAmount, 0))
                    ELSE ABS(COALESCE(CumulativeActualAmount, 0))
                  END) >= 1000000.0
             AND CumulativeActualAmount = 0 AND BudgetAmount > 0 AND PeriodNo >= 6 THEN 1 ELSE 0 END AS BIT),
        IsAboveExpectedYTD = CAST(CASE
            WHEN CumulativeActualAmount > ExpectedActualToDateAmount AND ABS(CumulativeActualAmount - ExpectedActualToDateAmount) >= 1000000.0 THEN 1 ELSE 0 END AS BIT)
    FROM WithPriorYear
),
Risked AS
(
    SELECT
        *,
        RiskScore = CAST(CASE
            WHEN BudgetBaselineVersionID IS NULL AND ABS(CumulativeActualAmount) >= 1000000.0 THEN 95.0000
            WHEN BudgetAmount = 0 AND ABS(CumulativeActualAmount) >= 1000000.0 THEN 92.0000
            WHEN MaterialityAmount >= 1000000.0 AND ExecutionRate >= 120.0 THEN 90.0000
            WHEN MaterialityAmount >= 1000000.0 AND AvailableBalance <= -1000000.0 THEN 88.0000
            WHEN IsDormantLineActivity = 1 THEN 84.0000
            WHEN IsActualSpike = 1 THEN 82.0000
            WHEN MaterialityAmount >= 1000000.0 AND ExecutionRate >= 105.0 THEN 75.0000
            WHEN IsPriorYearActualSpike = 1 THEN 72.0000
            WHEN MaterialityAmount >= 1000000.0 AND PeriodNo >= 6 AND ExecutionRate <= ExpectedExecutionRate - 30.0 THEN 78.0000
            WHEN MaterialityAmount >= 1000000.0 AND PeriodNo >= 6 AND ExecutionRate <= ExpectedExecutionRate - 20.0 THEN 65.0000
            WHEN ABS(COALESCE(VariancePct, 0)) >= 50.0 AND ABS(VarianceAmount) >= 1000000.0 THEN 60.0000
            WHEN MaterialityAmount >= 1000000.0 AND CumulativeActualAmount = 0 AND BudgetAmount > 0 AND PeriodNo >= 6 THEN 58.0000
            WHEN MaterialityAmount < 1000000.0 AND ExecutionRate >= 120.0 THEN 45.0000
            WHEN MaterialityAmount < 1000000.0 AND PeriodNo >= 6 AND CumulativeActualAmount = 0 AND BudgetAmount > 0 THEN 35.0000
            ELSE 25.0000
        END AS DECIMAL(9,4)),
        RiskLabel = CASE
            WHEN BudgetBaselineVersionID IS NULL AND ABS(CumulativeActualAmount) >= 1000000.0 THEN N''HIGH''
            WHEN BudgetAmount = 0 AND ABS(CumulativeActualAmount) >= 1000000.0 THEN N''HIGH''
            WHEN MaterialityAmount >= 1000000.0 AND ExecutionRate >= 120.0 THEN N''HIGH''
            WHEN MaterialityAmount >= 1000000.0 AND AvailableBalance <= -1000000.0 THEN N''HIGH''
            WHEN IsDormantLineActivity = 1 THEN N''HIGH''
            WHEN IsActualSpike = 1 THEN N''HIGH''
            WHEN MaterialityAmount >= 1000000.0 AND ExecutionRate >= 105.0 THEN N''MEDIUM''
            WHEN IsPriorYearActualSpike = 1 THEN N''MEDIUM''
            WHEN MaterialityAmount >= 1000000.0 AND PeriodNo >= 6 AND ExecutionRate <= ExpectedExecutionRate - 30.0 THEN N''HIGH''
            WHEN MaterialityAmount >= 1000000.0 AND PeriodNo >= 6 AND ExecutionRate <= ExpectedExecutionRate - 20.0 THEN N''MEDIUM''
            WHEN ABS(COALESCE(VariancePct, 0)) >= 50.0 AND ABS(VarianceAmount) >= 1000000.0 THEN N''MEDIUM''
            WHEN MaterialityAmount >= 1000000.0 AND CumulativeActualAmount = 0 AND BudgetAmount > 0 AND PeriodNo >= 6 THEN N''MEDIUM''
            ELSE N''LOW''
        END,
        RiskReason = CASE
            WHEN BudgetBaselineVersionID IS NULL AND ABS(CumulativeActualAmount) >= 1000000.0 THEN N''Material actual expenditure exists but no mapped annual budget baseline was found for this fiscal year and semantic grain.''
            WHEN BudgetAmount = 0 AND ABS(CumulativeActualAmount) >= 1000000.0 THEN N''Material cumulative actual expenditure exists with no annual budget baseline.''
            WHEN MaterialityAmount >= 1000000.0 AND ExecutionRate >= 120.0 THEN N''Material cumulative execution is significantly above the annual budget baseline.''
            WHEN MaterialityAmount >= 1000000.0 AND AvailableBalance <= -1000000.0 THEN N''Material negative available balance exists after cumulative actuals and commitments.''
            WHEN IsDormantLineActivity = 1 THEN N''Material actual expenditure appeared on a line with no prior-period or prior-year activity.''
            WHEN IsActualSpike = 1 THEN N''Material actual expenditure spiked compared with the prior period.''
            WHEN MaterialityAmount >= 1000000.0 AND ExecutionRate >= 105.0 THEN N''Material cumulative execution is above the annual budget baseline.''
            WHEN IsPriorYearActualSpike = 1 THEN N''Material cumulative actual expenditure changed sharply compared with the prior year.''
            WHEN MaterialityAmount >= 1000000.0 AND PeriodNo >= 6 AND ExecutionRate <= ExpectedExecutionRate - 30.0 THEN N''Material cumulative execution is significantly behind the expected year-to-date rate.''
            WHEN MaterialityAmount >= 1000000.0 AND PeriodNo >= 6 AND ExecutionRate <= ExpectedExecutionRate - 20.0 THEN N''Material cumulative execution is behind the expected year-to-date rate.''
            WHEN ABS(COALESCE(VariancePct, 0)) >= 50.0 AND ABS(VarianceAmount) >= 1000000.0 THEN N''Large value variance compared with the expected year-to-date execution amount.''
            WHEN MaterialityAmount >= 1000000.0 AND CumulativeActualAmount = 0 AND BudgetAmount > 0 AND PeriodNo >= 6 THEN N''Material budget exists but no cumulative actual expenditure has been recorded by mid-year.''
            WHEN MaterialityAmount < 1000000.0 AND ExecutionRate >= 120.0 THEN N''Percentage execution is high, but the monetary value is below the executive materiality threshold.''
            WHEN MaterialityAmount < 1000000.0 AND PeriodNo >= 6 AND CumulativeActualAmount = 0 AND BudgetAmount > 0 THEN N''No cumulative actual expenditure has been recorded, but the monetary value is below the executive materiality threshold.''
            ELSE N''No major execution risk rule triggered.''
        END,
        AnomalyTypeCode = CASE
            WHEN BudgetBaselineVersionID IS NULL AND ABS(CumulativeActualAmount) >= 1000000.0 THEN N''MISSING_BASELINE''
            WHEN BudgetAmount = 0 AND ABS(CumulativeActualAmount) >= 1000000.0 THEN N''ACTUAL_WITHOUT_BUDGET''
            WHEN MaterialityAmount >= 1000000.0 AND ExecutionRate >= 120.0 THEN N''OVER_EXECUTION''
            WHEN MaterialityAmount >= 1000000.0 AND AvailableBalance <= -1000000.0 THEN N''NEGATIVE_AVAILABLE_BALANCE''
            WHEN IsDormantLineActivity = 1 THEN N''DORMANT_LINE_ACTIVITY''
            WHEN IsActualSpike = 1 THEN N''PERIOD_SPENDING_SPIKE''
            WHEN IsPriorYearActualSpike = 1 THEN N''PRIOR_YEAR_CHANGE''
            WHEN MaterialityAmount >= 1000000.0 AND PeriodNo >= 6 AND ExecutionRate <= ExpectedExecutionRate - 20.0 THEN N''UNDER_EXECUTION''
            WHEN ABS(COALESCE(VariancePct, 0)) >= 50.0 AND ABS(VarianceAmount) >= 1000000.0 THEN N''EXPECTED_YTD_VARIANCE''
            WHEN MaterialityAmount >= 1000000.0 AND CumulativeActualAmount = 0 AND BudgetAmount > 0 AND PeriodNo >= 6 THEN N''BUDGET_WITHOUT_EXECUTION''
            ELSE N''NONE''
        END
    FROM Scored
)
SELECT
    FiscalYearID,
    BudgetVersionID,
    BudgetBaselineVersionID,
    ExecutionActualsVersionID,
    BudgetBaselineVersionTypeID,
    ExecutionActualsVersionTypeID,
    BudgetBaselineRoleCode,
    BudgetBaselineRoleName,
    ExecutionActualsRoleCode,
    ExecutionActualsRoleName,
    PeriodNo,
    EntityTypeCode = N''SEGMENT1_PROGRAM_ECONOMIC'',
    EntityCode = CONCAT(COALESCE(Segment1, N''[blank]''), N''|'', COALESCE(ProgramCode, N''[blank]''), N''|'', COALESCE(EconomicCode, N''[blank]'')),
    Segment1,
    Segment2,
    Segment3,
    Segment4,
    Segment5,
    Segment6,
    Segment7,
    Segment11,
    ProgramCode,
    EconomicCode,
    CurrencyCode,
    BudgetAmount,
    PeriodBudgetAmount,
    ReleasedAmount,
    WarrantAmount,
    CommitmentAmount,
    ActualAmount,
    CumulativeActualAmount,
    AvailableBalance,
    ExecutionRate,
    BudgetSharePct,
    ActualSharePct,
    CumulativeBudgetAmount,
    CumulativeExecutionRate,
    ExpectedExecutionRate,
    ExpectedActualToDateAmount,
    MaterialityAmount,
    PriorPeriodActualAmount,
    PriorPeriodExecutionRate,
    PriorPeriodActualVariance,
    PriorYearBudgetAmount,
    PriorYearActualAmount,
    PriorYearActualVariance,
    ActualSpikePct,
    PriorYearActualChangePct,
    VarianceAmount,
    VariancePct,
    IsOverspent = CAST(CASE WHEN ExecutionRate > 100.0 THEN 1 ELSE 0 END AS BIT),
    IsUnderExecuting = CAST(CASE WHEN PeriodNo >= 6 AND ExecutionRate < ExpectedExecutionRate - 20.0 THEN 1 ELSE 0 END AS BIT),
    IsActualWithoutBudget,
    IsNegativeAvailableBalance,
    IsActualSpike,
    IsPriorYearActualSpike,
    IsDormantLineActivity,
    IsBudgetWithoutExecution,
    IsAboveExpectedYTD,
    RiskScore,
    RiskLabel,
    RiskReason,
    AnomalyTypeCode
FROM Risked;
');
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblAIBudgetLedgerAnalysisSource')
      AND name = N'IX_tblAIBudgetLedgerAnalysisSource_MLSegmentGrain'
)
BEGIN
    CREATE INDEX IX_tblAIBudgetLedgerAnalysisSource_MLSegmentGrain
        ON dbo.tblAIBudgetLedgerAnalysisSource
            (FiscalYearID, BudgetVersionID, PeriodNo, Segment1, Segment5, Segment11, IsActive)
        INCLUDE
            (BudgetAmount, ReleasedAmount, WarrantAmount, CommitmentAmount, ActualAmount, AvailableBalance, CurrencyCode);
END;
GO

IF OBJECT_ID(N'dbo.tblAIDatasets', N'U') IS NOT NULL
BEGIN
    UPDATE dbo.tblAIDatasets
    SET DatasetName = N'Budget Execution Risk Training',
        [Description] = N'Role-normalized budget execution risk and anomaly dataset comparing annual mapped budget baseline, execution actuals, spending spikes, prior-year changes, dormant-line activity, and negative balances by fiscal year.',
        SourceObjectName = N'dbo.vwML_BudgetExecutionRiskTraining',
        SourceType = N'VIEW',
        SensitivityLevel = N'RESTRICTED',
        AllowedPermissionCodes = N'AI_DATASET_ANALYZE',
        DefaultFiscalYearColumn = N'FiscalYearID',
        DefaultVersionColumn = NULL,
        MaxRows = 250,
        RequireContext = 1,
        IsActive = 1,
        Notes = N'Uses dbo.tblAIBudgetLedgerVersionRoleMap. BudgetAmount is annual baseline; ActualAmount is period actual. Risk rules include budget-vs-actual, spending spikes, prior-year changes, dormant-line activity, and negative balances.',
        UpdatedDate = SYSUTCDATETIME()
    WHERE DatasetCode = N'BUDGET_EXECUTION_RISK_TRAINING';

    IF @@ROWCOUNT = 0
    BEGIN
        INSERT INTO dbo.tblAIDatasets
            (DatasetCode, DatasetName, [Description], SourceObjectName, SourceType, SensitivityLevel, AllowedPermissionCodes,
             DefaultFiscalYearColumn, DefaultVersionColumn, MaxRows, RequireContext, IsActive, Notes)
        VALUES
            (N'BUDGET_EXECUTION_RISK_TRAINING', N'Budget Execution Risk Training',
             N'Role-normalized budget execution risk and anomaly dataset comparing annual mapped budget baseline, execution actuals, spending spikes, prior-year changes, dormant-line activity, and negative balances by fiscal year.',
             N'dbo.vwML_BudgetExecutionRiskTraining', N'VIEW', N'RESTRICTED', N'AI_DATASET_ANALYZE',
             N'FiscalYearID', NULL, 250, 1, 1,
             N'Uses dbo.tblAIBudgetLedgerVersionRoleMap. BudgetAmount is annual baseline; ActualAmount is period actual. Risk rules include budget-vs-actual, spending spikes, prior-year changes, dormant-line activity, and negative balances.');
    END;
END;
GO

IF OBJECT_ID(N'dbo.tblMLModels', N'U') IS NOT NULL
BEGIN
    UPDATE dbo.tblMLModels
    SET ModelName = N'Budget Execution Risk Model v1',
        UseCaseCode = N'BUDGET_EXECUTION_RISK',
        ModelTypeCode = N'REGRESSION',
        ApprovedViewName = N'dbo.vwML_BudgetExecutionRiskTraining',
        TargetColumnName = N'RiskScore',
        FeatureColumnsJson = N'["FiscalYearID","PeriodNo","Segment1","ProgramCode","EconomicCode","BudgetAmount","ActualAmount","CumulativeActualAmount","AvailableBalance","ExecutionRate","BudgetSharePct","ActualSharePct","PriorPeriodActualAmount","PriorPeriodExecutionRate","PriorPeriodActualVariance","ActualSpikePct","CumulativeBudgetAmount","CumulativeExecutionRate","ExpectedExecutionRate","ExpectedActualToDateAmount","PriorYearBudgetAmount","PriorYearActualAmount","PriorYearActualVariance","PriorYearActualChangePct","MaterialityAmount","IsActualWithoutBudget","IsNegativeAvailableBalance","IsActualSpike","IsPriorYearActualSpike","IsDormantLineActivity","IsBudgetWithoutExecution","IsAboveExpectedYTD"]',
        ActiveFlag = 1
    WHERE ModelCode = N'BUDGET_EXECUTION_RISK_V1';

    IF @@ROWCOUNT = 0
    BEGIN
        INSERT INTO dbo.tblMLModels
            (ModelCode, ModelName, UseCaseCode, ModelTypeCode, ApprovedViewName, TargetColumnName, FeatureColumnsJson, StatusCode, ActiveFlag)
        VALUES
            (N'BUDGET_EXECUTION_RISK_V1', N'Budget Execution Risk Model v1', N'BUDGET_EXECUTION_RISK', N'REGRESSION',
             N'dbo.vwML_BudgetExecutionRiskTraining', N'RiskScore',
             N'["FiscalYearID","PeriodNo","Segment1","ProgramCode","EconomicCode","BudgetAmount","ActualAmount","CumulativeActualAmount","AvailableBalance","ExecutionRate","BudgetSharePct","ActualSharePct","PriorPeriodActualAmount","PriorPeriodExecutionRate","PriorPeriodActualVariance","ActualSpikePct","CumulativeBudgetAmount","CumulativeExecutionRate","ExpectedExecutionRate","ExpectedActualToDateAmount","PriorYearBudgetAmount","PriorYearActualAmount","PriorYearActualVariance","PriorYearActualChangePct","MaterialityAmount","IsActualWithoutBudget","IsNegativeAvailableBalance","IsActualSpike","IsPriorYearActualSpike","IsDormantLineActivity","IsBudgetWithoutExecution","IsAboveExpectedYTD"]',
             N'DRAFT', 1);
    END;
END;
GO

SELECT TOP (100)
    FiscalYearID,
    PeriodNo,
    Segment1,
    ProgramCode,
    EconomicCode,
    BudgetBaselineVersionID,
    ExecutionActualsVersionID,
    BudgetAmount,
    ActualAmount,
    CumulativeActualAmount,
    ExecutionRate,
    ExpectedExecutionRate,
    AnomalyTypeCode,
    RiskScore,
    RiskLabel,
    RiskReason
FROM dbo.vwML_BudgetExecutionRiskTraining
ORDER BY RiskScore DESC, MaterialityAmount DESC;
GO
