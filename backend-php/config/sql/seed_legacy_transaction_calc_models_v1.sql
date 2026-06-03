USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

DECLARE @LegacyModels TABLE (
    ModelCode NVARCHAR(50) NOT NULL PRIMARY KEY,
    ModelName NVARCHAR(200) NOT NULL,
    LegacyCalculationID INT NOT NULL,
    ScenarioCode NVARCHAR(50) NOT NULL,
    ScenarioName NVARCHAR(200) NOT NULL,
    FiscalYearID INT NOT NULL,
    VersionID INT NOT NULL
);

INSERT INTO @LegacyModels (ModelCode, ModelName, LegacyCalculationID, ScenarioCode, ScenarioName, FiscalYearID, VersionID)
VALUES
    (N'LEGACY_CALC_1_DEFAULT_V1', N'Legacy Calculation 1 Default', 1, N'BASE', N'Base Scenario', 2026, 5),
    (N'LEGACY_CALC_2_WAGES_V1', N'Legacy Calculation 2 Wages', 2, N'BASE', N'Base Scenario', 2026, 5),
    (N'LEGACY_CALC_3_PENSION_V1', N'Legacy Calculation 3 Pension', 3, N'BASE', N'Base Scenario', 2026, 5),
    (N'LEGACY_CALC_4_WORKERSCOMP_V1', N'Legacy Calculation 4 Workers Compensation', 4, N'BASE', N'Base Scenario', 2026, 5),
    (N'LEGACY_CALC_5_INSERVCHARGE_V1', N'Legacy Calculation 5 In-Service Charge', 5, N'BASE', N'Base Scenario', 2026, 5),
    (N'LEGACY_CALC_15_HALF_A_V1', N'Legacy Calculation 15 In-Service Charge Half A', 15, N'BASE', N'Base Scenario', 2026, 5),
    (N'LEGACY_CALC_16_HALF_B_V1', N'Legacy Calculation 16 In-Service Charge Half B', 16, N'BASE', N'Base Scenario', 2026, 5);

INSERT INTO dbo.tblCalcModel
(
    ModelCode,
    ModelName,
    ModelVersion,
    StatusCode,
    ActiveFlag,
    CreatedBy
)
SELECT
    m.ModelCode,
    m.ModelName,
    1,
    N'ACTIVE',
    1,
    1
FROM @LegacyModels m
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcModel existing
    WHERE existing.ModelCode = m.ModelCode
      AND existing.ModelVersion = 1
);

INSERT INTO dbo.tblCalcScenario
(
    CalcModelID,
    ParentScenarioID,
    ScenarioCode,
    ScenarioName,
    ScenarioTypeCode,
    ScenarioStatusCode,
    SortOrder,
    LockedFlag,
    ApprovedFlag,
    ActiveFlag,
    CreatedBy
)
SELECT
    model.CalcModelID,
    NULL,
    spec.ScenarioCode,
    spec.ScenarioName,
    N'BASE',
    N'ACTIVE',
    10,
    0,
    1,
    1,
    1
FROM @LegacyModels spec
INNER JOIN dbo.tblCalcModel model
    ON model.ModelCode = spec.ModelCode
   AND model.ModelVersion = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcScenario existing
    WHERE existing.CalcModelID = model.CalcModelID
      AND existing.ScenarioCode = spec.ScenarioCode
);

INSERT INTO dbo.tblCalcPeriod
(
    CalcModelID,
    PeriodCode,
    FiscalYearID,
    PeriodNo,
    PeriodTypeCode,
    StartDate,
    EndDate,
    SequenceNo,
    ActiveFlag,
    CreatedBy
)
SELECT
    model.CalcModelID,
    N'TXN',
    spec.FiscalYearID,
    1,
    N'TXN',
    CAST('2026-01-01' AS DATE),
    CAST('2026-12-31' AS DATE),
    1,
    1,
    1
FROM @LegacyModels spec
INNER JOIN dbo.tblCalcModel model
    ON model.ModelCode = spec.ModelCode
   AND model.ModelVersion = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcPeriod existing
    WHERE existing.CalcModelID = model.CalcModelID
      AND existing.PeriodCode = N'TXN'
);

INSERT INTO dbo.tblCalcCostObject
(
    CalcModelID,
    ParentCostObjectID,
    CostObjectCode,
    CostObjectName,
    CostObjectTypeCode,
    ActiveFlag,
    CreatedBy
)
SELECT
    model.CalcModelID,
    NULL,
    N'GLOBAL',
    N'Global Transaction Cost Object',
    N'TRANSACTION',
    1,
    1
FROM @LegacyModels spec
INNER JOIN dbo.tblCalcModel model
    ON model.ModelCode = spec.ModelCode
   AND model.ModelVersion = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcCostObject existing
    WHERE existing.CalcModelID = model.CalcModelID
      AND existing.CostObjectCode = N'GLOBAL'
);

DECLARE @NodeSeed TABLE (
    ModelCode NVARCHAR(50) NOT NULL,
    NodeCode NVARCHAR(100) NOT NULL,
    NodeName NVARCHAR(200) NOT NULL,
    NodeTypeCode NVARCHAR(20) NOT NULL,
    NodeCategoryCode NVARCHAR(30) NOT NULL,
    DataTypeCode NVARCHAR(20) NOT NULL,
    UnitOfMeasureCode NVARCHAR(20) NULL,
    NodeOrder INT NOT NULL,
    OutputFlag BIT NOT NULL,
    ExpressionText NVARCHAR(MAX) NULL
);

;WITH Months AS (
    SELECT 1 AS MonthNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
),
OutYears AS (
    SELECT 1 AS YearNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
)
INSERT INTO @NodeSeed (ModelCode, NodeCode, NodeName, NodeTypeCode, NodeCategoryCode, DataTypeCode, UnitOfMeasureCode, NodeOrder, OutputFlag, ExpressionText)
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'InpN', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N' Input', N'INPUT', N'DRIVER', N'DECIMAL', N'CUR', 10 + m.MonthNo, 0, NULL
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'Inflation OY' + CAST(y.YearNo AS NVARCHAR(2)), N'INPUT', N'RATE', N'DECIMAL', N'RATE', 40 + y.YearNo, 0, NULL
FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 100 + m.MonthNo, 1, N'@BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'InpN@'
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPTotal', N'BP Total', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 200, 1, N'SUM(@BP1@, @BP2@, @BP3@, @BP4@, @BP5@, @BP6@, @BP7@, @BP8@, @BP9@, @BP10@, @BP11@, @BP12@)'
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPQ1', N'BP Quarter 1', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 210, 1, N'@BP1@ + @BP2@ + @BP3@'
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPQ2', N'BP Quarter 2', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 220, 1, N'@BP4@ + @BP5@ + @BP6@'
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPQ3', N'BP Quarter 3', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 230, 1, N'@BP7@ + @BP8@ + @BP9@'
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPQ4', N'BP Quarter 4', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 240, 1, N'@BP10@ + @BP11@ + @BP12@'
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BP Outyear ' + CAST(y.YearNo AS NVARCHAR(2)), N'FORMULA', N'OUTYEAR', N'DECIMAL', N'CUR',
       300 + y.YearNo, 1,
       CASE WHEN y.YearNo = 1
            THEN N'@BPTotal@ * @InflationOY1@'
            ELSE N'@BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) + N'@ * @InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) + N'@'
       END
FROM OutYears y;

;WITH Months AS (
    SELECT 1 AS MonthNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
),
OutYears AS (
    SELECT 1 AS YearNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
)
INSERT INTO @NodeSeed (ModelCode, NodeCode, NodeName, NodeTypeCode, NodeCategoryCode, DataTypeCode, UnitOfMeasureCode, NodeOrder, OutputFlag, ExpressionText)
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'QtyInpN', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N' Quantity Input', N'INPUT', N'DRIVER', N'DECIMAL', N'UNIT', 10 + m.MonthNo, 0, NULL
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N' Rate', N'INPUT', N'RATE', N'DECIMAL', N'CUR', 40 + m.MonthNo, 0, NULL
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'Inflation OY' + CAST(y.YearNo AS NVARCHAR(2)), N'INPUT', N'RATE', N'DECIMAL', N'RATE', 70 + y.YearNo, 0, NULL
FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 100 + m.MonthNo, 1,
       N'@BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate@ * @BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'QtyInpN@'
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPTotal', N'BP Total', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 200, 1, N'SUM(@BP1@, @BP2@, @BP3@, @BP4@, @BP5@, @BP6@, @BP7@, @BP8@, @BP9@, @BP10@, @BP11@, @BP12@)'
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPQ1', N'BP Quarter 1', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 210, 1, N'@BP1@ + @BP2@ + @BP3@'
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPQ2', N'BP Quarter 2', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 220, 1, N'@BP4@ + @BP5@ + @BP6@'
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPQ3', N'BP Quarter 3', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 230, 1, N'@BP7@ + @BP8@ + @BP9@'
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPQ4', N'BP Quarter 4', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 240, 1, N'@BP10@ + @BP11@ + @BP12@'
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BP Outyear ' + CAST(y.YearNo AS NVARCHAR(2)), N'FORMULA', N'OUTYEAR', N'DECIMAL', N'CUR',
       300 + y.YearNo, 1,
       CASE WHEN y.YearNo = 1
            THEN N'@BPTotal@ * @InflationOY1@'
            ELSE N'@BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) + N'@ * @InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) + N'@'
       END
FROM OutYears y;

;WITH Months AS (
    SELECT 1 AS MonthNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
),
OutYears AS (
    SELECT 1 AS YearNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
)
INSERT INTO @NodeSeed (ModelCode, NodeCode, NodeName, NodeTypeCode, NodeCategoryCode, DataTypeCode, UnitOfMeasureCode, NodeOrder, OutputFlag, ExpressionText)
SELECT N'LEGACY_CALC_3_PENSION_V1', N'WAGES_BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'Wages BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'INPUT', N'CHAIN', N'DECIMAL', N'CUR', 10 + m.MonthNo, 0, NULL
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'PENSION_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate', N'Pension BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N' Rate', N'INPUT', N'RATE', N'DECIMAL', N'RATE', 40 + m.MonthNo, 0, NULL
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'Inflation OY' + CAST(y.YearNo AS NVARCHAR(2)), N'INPUT', N'RATE', N'DECIMAL', N'RATE', 70 + y.YearNo, 0, NULL
FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 100 + m.MonthNo, 1,
       N'@WAGES_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'@ * @PENSION_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate@'
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPTotal', N'BP Total', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 200, 1, N'SUM(@BP1@, @BP2@, @BP3@, @BP4@, @BP5@, @BP6@, @BP7@, @BP8@, @BP9@, @BP10@, @BP11@, @BP12@)'
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPQ1', N'BP Quarter 1', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 210, 1, N'@BP1@ + @BP2@ + @BP3@'
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPQ2', N'BP Quarter 2', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 220, 1, N'@BP4@ + @BP5@ + @BP6@'
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPQ3', N'BP Quarter 3', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 230, 1, N'@BP7@ + @BP8@ + @BP9@'
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPQ4', N'BP Quarter 4', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 240, 1, N'@BP10@ + @BP11@ + @BP12@'
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BP Outyear ' + CAST(y.YearNo AS NVARCHAR(2)), N'FORMULA', N'OUTYEAR', N'DECIMAL', N'CUR',
       300 + y.YearNo, 1,
       CASE WHEN y.YearNo = 1
            THEN N'@BPTotal@ * @InflationOY1@'
            ELSE N'@BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) + N'@ * @InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) + N'@'
       END
FROM OutYears y;

;WITH Months AS (
    SELECT 1 AS MonthNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
),
OutYears AS (
    SELECT 1 AS YearNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
)
INSERT INTO @NodeSeed (ModelCode, NodeCode, NodeName, NodeTypeCode, NodeCategoryCode, DataTypeCode, UnitOfMeasureCode, NodeOrder, OutputFlag, ExpressionText)
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'WAGES_BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'Wages BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'INPUT', N'CHAIN', N'DECIMAL', N'CUR', 10 + m.MonthNo, 0, NULL
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'WCOMP_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate', N'Workers Comp BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N' Rate', N'INPUT', N'RATE', N'DECIMAL', N'RATE', 40 + m.MonthNo, 0, NULL
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'Inflation OY' + CAST(y.YearNo AS NVARCHAR(2)), N'INPUT', N'RATE', N'DECIMAL', N'RATE', 70 + y.YearNo, 0, NULL
FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 100 + m.MonthNo, 1,
       N'@WAGES_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'@ * @WCOMP_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate@'
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPTotal', N'BP Total', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 200, 1, N'SUM(@BP1@, @BP2@, @BP3@, @BP4@, @BP5@, @BP6@, @BP7@, @BP8@, @BP9@, @BP10@, @BP11@, @BP12@)'
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPQ1', N'BP Quarter 1', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 210, 1, N'@BP1@ + @BP2@ + @BP3@'
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPQ2', N'BP Quarter 2', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 220, 1, N'@BP4@ + @BP5@ + @BP6@'
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPQ3', N'BP Quarter 3', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 230, 1, N'@BP7@ + @BP8@ + @BP9@'
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPQ4', N'BP Quarter 4', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 240, 1, N'@BP10@ + @BP11@ + @BP12@'
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BP Outyear ' + CAST(y.YearNo AS NVARCHAR(2)), N'FORMULA', N'OUTYEAR', N'DECIMAL', N'CUR',
       300 + y.YearNo, 1,
       CASE WHEN y.YearNo = 1
            THEN N'@BPTotal@ * @InflationOY1@'
            ELSE N'@BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) + N'@ * @InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) + N'@'
       END
FROM OutYears y;

;WITH Months AS (
    SELECT 1 AS MonthNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
),
OutYears AS (
    SELECT 1 AS YearNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
)
INSERT INTO @NodeSeed (ModelCode, NodeCode, NodeName, NodeTypeCode, NodeCategoryCode, DataTypeCode, UnitOfMeasureCode, NodeOrder, OutputFlag, ExpressionText)
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'QtyInpN', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N' Quantity Input', N'INPUT', N'DRIVER', N'DECIMAL', N'UNIT', 10 + m.MonthNo, 0, NULL
FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'WAGES_BP1', N'Wages BP1', N'INPUT', N'CHAIN', N'DECIMAL', N'CUR', 50, 0, NULL
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'Inflation OY' + CAST(y.YearNo AS NVARCHAR(2)), N'INPUT', N'RATE', N'DECIMAL', N'RATE', 70 + y.YearNo, 0, NULL
FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP1', N'BP1', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 101, 1, N'@BP1QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP2', N'BP2', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 102, 1, N'@WAGES_BP1@'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP3', N'BP3', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 103, 1, N'@BP3QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP4', N'BP4', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 104, 1, N'@BP4QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP5', N'BP5', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 105, 1, N'@BP5QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP6', N'BP6', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 106, 1, N'@BP6QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP7', N'BP7', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 107, 1, N'@BP7QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP8', N'BP8', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 108, 1, N'@BP8QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP9', N'BP9', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 109, 1, N'@BP9QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP10', N'BP10', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 110, 1, N'@BP10QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP11', N'BP11', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 111, 1, N'@BP11QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP12', N'BP12', N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 112, 1, N'@BP12QtyInpN@ * 105'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPTotal', N'BP Total', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 200, 1, N'SUM(@BP1@, @BP2@, @BP3@, @BP4@, @BP5@, @BP6@, @BP7@, @BP8@, @BP9@, @BP10@, @BP11@, @BP12@)'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPQ1', N'BP Quarter 1', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 210, 1, N'@BP1@ + @BP2@ + @BP3@'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPQ2', N'BP Quarter 2', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 220, 1, N'@BP4@ + @BP5@ + @BP6@'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPQ3', N'BP Quarter 3', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 230, 1, N'@BP7@ + @BP8@ + @BP9@'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPQ4', N'BP Quarter 4', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 240, 1, N'@BP10@ + @BP11@ + @BP12@'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BP Outyear ' + CAST(y.YearNo AS NVARCHAR(2)), N'FORMULA', N'OUTYEAR', N'DECIMAL', N'CUR',
       300 + y.YearNo, 1,
       CASE WHEN y.YearNo = 1
            THEN N'@BPTotal@ * @InflationOY1@'
            ELSE N'@BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) + N'@ * @InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) + N'@'
       END
FROM OutYears y;

;WITH Months AS (
    SELECT 1 AS MonthNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
),
OutYears AS (
    SELECT 1 AS YearNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
)
INSERT INTO @NodeSeed (ModelCode, NodeCode, NodeName, NodeTypeCode, NodeCategoryCode, DataTypeCode, UnitOfMeasureCode, NodeOrder, OutputFlag, ExpressionText)
SELECT modelCode, N'INSERVCHARGE_BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'In-Service Charge BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'INPUT', N'CHAIN', N'DECIMAL', N'CUR', 10 + m.MonthNo, 0, NULL
FROM Months m
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'Inflation OY' + CAST(y.YearNo AS NVARCHAR(2)), N'INPUT', N'RATE', N'DECIMAL', N'RATE', 70 + y.YearNo, 0, NULL
FROM OutYears y
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'FORMULA', N'OUTPUT', N'DECIMAL', N'CUR', 100 + m.MonthNo, 1,
       N'@INSERVCHARGE_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'@ * 0.5'
FROM Months m
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPTotal', N'BP Total', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 200, 1, N'SUM(@BP1@, @BP2@, @BP3@, @BP4@, @BP5@, @BP6@, @BP7@, @BP8@, @BP9@, @BP10@, @BP11@, @BP12@)'
FROM (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPQ1', N'BP Quarter 1', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 210, 1, N'@BP1@ + @BP2@ + @BP3@'
FROM (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPQ2', N'BP Quarter 2', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 220, 1, N'@BP4@ + @BP5@ + @BP6@'
FROM (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPQ3', N'BP Quarter 3', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 230, 1, N'@BP7@ + @BP8@ + @BP9@'
FROM (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPQ4', N'BP Quarter 4', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 240, 1, N'@BP10@ + @BP11@ + @BP12@'
FROM (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BP Outyear ' + CAST(y.YearNo AS NVARCHAR(2)), N'FORMULA', N'OUTYEAR', N'DECIMAL', N'CUR',
       300 + y.YearNo, 1,
       CASE WHEN y.YearNo = 1
            THEN N'@BPTotal@ * @InflationOY1@'
            ELSE N'@BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) + N'@ * @InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) + N'@'
       END
FROM OutYears y
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode);

INSERT INTO dbo.tblCalcNode
(
    CalcModelID,
    NodeCode,
    NodeName,
    NodeTypeCode,
    NodeCategoryCode,
    DataTypeCode,
    UnitOfMeasureCode,
    DecimalScale,
    NodeOrder,
    OutputFlag,
    ActiveFlag,
    CreatedBy
)
SELECT
    model.CalcModelID,
    seed.NodeCode,
    seed.NodeName,
    seed.NodeTypeCode,
    seed.NodeCategoryCode,
    seed.DataTypeCode,
    seed.UnitOfMeasureCode,
    6,
    seed.NodeOrder,
    seed.OutputFlag,
    1,
    1
FROM @NodeSeed seed
INNER JOIN dbo.tblCalcModel model
    ON model.ModelCode = seed.ModelCode
   AND model.ModelVersion = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcNode existing
    WHERE existing.CalcModelID = model.CalcModelID
      AND existing.NodeCode = seed.NodeCode
);

INSERT INTO dbo.tblCalcFormula
(
    NodeID,
    ExpressionText,
    ExpressionLanguageCode,
    ParserVersion,
    ActiveFlag,
    CreatedBy
)
SELECT
    node.NodeID,
    seed.ExpressionText,
    N'TOKEN_ARITH',
    N'v1',
    1,
    1
FROM @NodeSeed seed
INNER JOIN dbo.tblCalcModel model
    ON model.ModelCode = seed.ModelCode
   AND model.ModelVersion = 1
INNER JOIN dbo.tblCalcNode node
    ON node.CalcModelID = model.CalcModelID
   AND node.NodeCode = seed.NodeCode
WHERE seed.ExpressionText IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.tblCalcFormula existing
      WHERE existing.NodeID = node.NodeID
        AND existing.ActiveFlag = 1
  );

DECLARE @DependencySeed TABLE (
    ModelCode NVARCHAR(50) NOT NULL,
    NodeCode NVARCHAR(100) NOT NULL,
    DependsOnNodeCode NVARCHAR(100) NOT NULL
);

;WITH Months AS (
    SELECT 1 AS MonthNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
),
OutYears AS (
    SELECT 1 AS YearNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
)
INSERT INTO @DependencySeed (ModelCode, NodeCode, DependsOnNodeCode)
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'InpN' FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPTotal', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPQ1', dep FROM (VALUES (N'BP1'), (N'BP2'), (N'BP3')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPQ2', dep FROM (VALUES (N'BP4'), (N'BP5'), (N'BP6')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPQ3', dep FROM (VALUES (N'BP7'), (N'BP8'), (N'BP9')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPQ4', dep FROM (VALUES (N'BP10'), (N'BP11'), (N'BP12')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPOY1', N'BPTotal'
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPOY1', N'InflationOY1'
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'QtyInpN' FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate' FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPTotal', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPQ1', dep FROM (VALUES (N'BP1'), (N'BP2'), (N'BP3')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPQ2', dep FROM (VALUES (N'BP4'), (N'BP5'), (N'BP6')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPQ3', dep FROM (VALUES (N'BP7'), (N'BP8'), (N'BP9')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPQ4', dep FROM (VALUES (N'BP10'), (N'BP11'), (N'BP12')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPOY1', N'BPTotal'
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPOY1', N'InflationOY1'
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'WAGES_BP' + CAST(m.MonthNo AS NVARCHAR(2)) FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'PENSION_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate' FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPTotal', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPQ1', dep FROM (VALUES (N'BP1'), (N'BP2'), (N'BP3')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPQ2', dep FROM (VALUES (N'BP4'), (N'BP5'), (N'BP6')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPQ3', dep FROM (VALUES (N'BP7'), (N'BP8'), (N'BP9')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPQ4', dep FROM (VALUES (N'BP10'), (N'BP11'), (N'BP12')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPOY1', N'BPTotal'
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPOY1', N'InflationOY1'
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'WAGES_BP' + CAST(m.MonthNo AS NVARCHAR(2)) FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'WCOMP_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate' FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPTotal', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPQ1', dep FROM (VALUES (N'BP1'), (N'BP2'), (N'BP3')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPQ2', dep FROM (VALUES (N'BP4'), (N'BP5'), (N'BP6')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPQ3', dep FROM (VALUES (N'BP7'), (N'BP8'), (N'BP9')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPQ4', dep FROM (VALUES (N'BP10'), (N'BP11'), (N'BP12')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPOY1', N'BPTotal'
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPOY1', N'InflationOY1'
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP1', N'BP1QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP2', N'WAGES_BP1'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP3', N'BP3QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP4', N'BP4QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP5', N'BP5QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP6', N'BP6QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP7', N'BP7QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP8', N'BP8QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP9', N'BP9QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP10', N'BP10QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP11', N'BP11QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP12', N'BP12QtyInpN'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPTotal', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPQ1', dep FROM (VALUES (N'BP1'), (N'BP2'), (N'BP3')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPQ2', dep FROM (VALUES (N'BP4'), (N'BP5'), (N'BP6')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPQ3', dep FROM (VALUES (N'BP7'), (N'BP8'), (N'BP9')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPQ4', dep FROM (VALUES (N'BP10'), (N'BP11'), (N'BP12')) q(dep)
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPOY1', N'BPTotal'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPOY1', N'InflationOY1'
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)) FROM OutYears y WHERE y.YearNo > 1
UNION ALL
SELECT modelCode, N'BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'INSERVCHARGE_BP' + CAST(m.MonthNo AS NVARCHAR(2))
FROM Months m
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPTotal', N'BP' + CAST(m.MonthNo AS NVARCHAR(2))
FROM Months m
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPQ1', dep FROM (VALUES (N'BP1'), (N'BP2'), (N'BP3')) q(dep)
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPQ2', dep FROM (VALUES (N'BP4'), (N'BP5'), (N'BP6')) q(dep)
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPQ3', dep FROM (VALUES (N'BP7'), (N'BP8'), (N'BP9')) q(dep)
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPQ4', dep FROM (VALUES (N'BP10'), (N'BP11'), (N'BP12')) q(dep)
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPOY1', N'BPTotal' FROM (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPOY1', N'InflationOY1' FROM (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'BPOY' + CAST(y.YearNo - 1 AS NVARCHAR(2))
FROM OutYears y
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
WHERE y.YearNo > 1
UNION ALL
SELECT modelCode, N'BPOY' + CAST(y.YearNo AS NVARCHAR(2)), N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2))
FROM OutYears y
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
WHERE y.YearNo > 1;

INSERT INTO dbo.tblCalcDependency
(
    NodeID,
    DependsOnNodeID,
    DependencyTypeCode,
    OffsetPeriods,
    RequiredFlag,
    SortOrder,
    CreatedBy
)
SELECT
    targetNode.NodeID,
    sourceNode.NodeID,
    N'VALUE',
    0,
    1,
    100,
    1
FROM @DependencySeed seed
INNER JOIN dbo.tblCalcModel model
    ON model.ModelCode = seed.ModelCode
   AND model.ModelVersion = 1
INNER JOIN dbo.tblCalcNode targetNode
    ON targetNode.CalcModelID = model.CalcModelID
   AND targetNode.NodeCode = seed.NodeCode
INNER JOIN dbo.tblCalcNode sourceNode
    ON sourceNode.CalcModelID = model.CalcModelID
   AND sourceNode.NodeCode = seed.DependsOnNodeCode
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcDependency existing
    WHERE existing.NodeID = targetNode.NodeID
      AND existing.DependsOnNodeID = sourceNode.NodeID
      AND existing.OffsetPeriods = 0
);

DECLARE @BridgeSeed TABLE (
    ModelCode NVARCHAR(50) NOT NULL,
    LegacyCalculationID INT NOT NULL,
    FiscalYearID INT NOT NULL,
    VersionID INT NOT NULL,
    PriorityNo INT NOT NULL
);

INSERT INTO @BridgeSeed (ModelCode, LegacyCalculationID, FiscalYearID, VersionID, PriorityNo)
VALUES
    (N'LEGACY_CALC_1_DEFAULT_V1', 1, 2026, 5, 10),
    (N'LEGACY_CALC_2_WAGES_V1', 2, 2026, 5, 10),
    (N'LEGACY_CALC_3_PENSION_V1', 3, 2026, 5, 10),
    (N'LEGACY_CALC_4_WORKERSCOMP_V1', 4, 2026, 5, 10),
    (N'LEGACY_CALC_5_INSERVCHARGE_V1', 5, 2026, 5, 10),
    (N'LEGACY_CALC_15_HALF_A_V1', 15, 2026, 5, 10),
    (N'LEGACY_CALC_16_HALF_B_V1', 16, 2026, 5, 10);

INSERT INTO dbo.tblCalcTransactionBridge
(
    CalcModelID,
    ScenarioID,
    CostObjectID,
    LegacyCalculationID,
    FiscalYearID,
    VersionID,
    PriorityNo,
    ActiveFlag,
    Notes,
    CreatedBy
)
SELECT
    model.CalcModelID,
    scenario.ScenarioID,
    costObject.CostObjectID,
    bridge.LegacyCalculationID,
    bridge.FiscalYearID,
    bridge.VersionID,
    bridge.PriorityNo,
    1,
    N'Legacy transaction calculation bridge.',
    1
FROM @BridgeSeed bridge
INNER JOIN dbo.tblCalcModel model
    ON model.ModelCode = bridge.ModelCode
   AND model.ModelVersion = 1
INNER JOIN dbo.tblCalcScenario scenario
    ON scenario.CalcModelID = model.CalcModelID
   AND scenario.ScenarioCode = N'BASE'
INNER JOIN dbo.tblCalcCostObject costObject
    ON costObject.CalcModelID = model.CalcModelID
   AND costObject.CostObjectCode = N'GLOBAL'
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcTransactionBridge existing
    WHERE existing.CalcModelID = model.CalcModelID
      AND existing.ScenarioID = scenario.ScenarioID
      AND existing.CostObjectID = costObject.CostObjectID
      AND existing.LegacyCalculationID = bridge.LegacyCalculationID
      AND existing.FiscalYearID = bridge.FiscalYearID
      AND existing.VersionID = bridge.VersionID
);

DECLARE @MapSeed TABLE (
    ModelCode NVARCHAR(50) NOT NULL,
    NodeCode NVARCHAR(100) NOT NULL,
    SourceTypeCode NVARCHAR(30) NOT NULL,
    SourceName NVARCHAR(128) NULL,
    ConstantDecimal DECIMAL(19,6) NULL,
    RequiredFlag BIT NOT NULL
);

;WITH Months AS (
    SELECT 1 AS MonthNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
    UNION ALL SELECT 11
    UNION ALL SELECT 12
),
OutYears AS (
    SELECT 1 AS YearNo
    UNION ALL SELECT 2
    UNION ALL SELECT 3
    UNION ALL SELECT 4
    UNION ALL SELECT 5
    UNION ALL SELECT 6
    UNION ALL SELECT 7
    UNION ALL SELECT 8
    UNION ALL SELECT 9
    UNION ALL SELECT 10
)
INSERT INTO @MapSeed (ModelCode, NodeCode, SourceTypeCode, SourceName, ConstantDecimal, RequiredFlag)
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'InpN', N'COLUMN', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'InpN', NULL, 0 FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_1_DEFAULT_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'RATE_LOOKUP', N'OY' + CAST(y.YearNo AS NVARCHAR(2)) + N'Rate|LITERAL:INFLATION', NULL, 1 FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'QtyInpN', N'COLUMN', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'QtyInpN', NULL, 0 FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate', N'RATE_LOOKUP', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate|FIELD:UOMCodeInpC', NULL, 1 FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_2_WAGES_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'RATE_LOOKUP', N'OY' + CAST(y.YearNo AS NVARCHAR(2)) + N'Rate|LITERAL:INFLATION', NULL, 1 FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'WAGES_BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'CHAIN_RESULT', N'WAGES|BP' + CAST(m.MonthNo AS NVARCHAR(2)), NULL, 1 FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'PENSION_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate', N'RATE_LOOKUP', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate|LITERAL:PENSION', NULL, 1 FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_3_PENSION_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'RATE_LOOKUP', N'OY' + CAST(y.YearNo AS NVARCHAR(2)) + N'Rate|LITERAL:INFLATION', NULL, 1 FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'WAGES_BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'CHAIN_RESULT', N'WAGES|BP' + CAST(m.MonthNo AS NVARCHAR(2)), NULL, 1 FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'WCOMP_BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate', N'RATE_LOOKUP', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'Rate|LITERAL:WCOMP', NULL, 1 FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_4_WORKERSCOMP_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'RATE_LOOKUP', N'OY' + CAST(y.YearNo AS NVARCHAR(2)) + N'Rate|LITERAL:INFLATION', NULL, 1 FROM OutYears y
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'QtyInpN', N'COLUMN', N'BP' + CAST(m.MonthNo AS NVARCHAR(2)) + N'QtyInpN', NULL, 0 FROM Months m
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'WAGES_BP1', N'CHAIN_RESULT', N'WAGES|BP1', NULL, 1
UNION ALL
SELECT N'LEGACY_CALC_5_INSERVCHARGE_V1', N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'RATE_LOOKUP', N'OY' + CAST(y.YearNo AS NVARCHAR(2)) + N'Rate|LITERAL:INFLATION', NULL, 1 FROM OutYears y
UNION ALL
SELECT modelCode, N'INSERVCHARGE_BP' + CAST(m.MonthNo AS NVARCHAR(2)), N'CHAIN_RESULT', N'INSERVCHARGE|BP' + CAST(m.MonthNo AS NVARCHAR(2)), NULL, 1
FROM Months m
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode)
UNION ALL
SELECT modelCode, N'InflationOY' + CAST(y.YearNo AS NVARCHAR(2)), N'RATE_LOOKUP', N'OY' + CAST(y.YearNo AS NVARCHAR(2)) + N'Rate|LITERAL:INFLATION', NULL, 1
FROM OutYears y
CROSS JOIN (VALUES (N'LEGACY_CALC_15_HALF_A_V1'), (N'LEGACY_CALC_16_HALF_B_V1')) models(modelCode);

INSERT INTO dbo.tblCalcTransactionNodeMap
(
    CalcTransactionBridgeID,
    NodeID,
    SourceTypeCode,
    SourceName,
    ConstantDecimal,
    RequiredFlag,
    ActiveFlag,
    Notes,
    CreatedBy
)
SELECT
    bridge.CalcTransactionBridgeID,
    node.NodeID,
    map.SourceTypeCode,
    map.SourceName,
    map.ConstantDecimal,
    map.RequiredFlag,
    1,
    N'Legacy transaction node mapping.',
    1
FROM @MapSeed map
INNER JOIN dbo.tblCalcModel model
    ON model.ModelCode = map.ModelCode
   AND model.ModelVersion = 1
INNER JOIN dbo.tblCalcNode node
    ON node.CalcModelID = model.CalcModelID
   AND node.NodeCode = map.NodeCode
INNER JOIN dbo.tblCalcTransactionBridge bridge
    ON bridge.CalcModelID = model.CalcModelID
   AND bridge.LegacyCalculationID = (
       SELECT spec.LegacyCalculationID
       FROM @LegacyModels spec
       WHERE spec.ModelCode = map.ModelCode
   )
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcTransactionNodeMap existing
    WHERE existing.CalcTransactionBridgeID = bridge.CalcTransactionBridgeID
      AND existing.NodeID = node.NodeID
      AND existing.SourceTypeCode = map.SourceTypeCode
      AND ISNULL(existing.SourceName, N'') = ISNULL(map.SourceName, N'')
);

SELECT
    model.ModelCode,
    bridge.LegacyCalculationID,
    COUNT(DISTINCT node.NodeID) AS NodeCount,
    COUNT(DISTINCT formula.CalcFormulaID) AS FormulaCount,
    COUNT(DISTINCT dependency.CalcDependencyID) AS DependencyCount,
    COUNT(DISTINCT map.CalcTransactionNodeMapID) AS MapCount
FROM dbo.tblCalcModel model
LEFT JOIN dbo.tblCalcNode node
    ON node.CalcModelID = model.CalcModelID
LEFT JOIN dbo.tblCalcFormula formula
    ON formula.NodeID = node.NodeID
LEFT JOIN dbo.tblCalcDependency dependency
    ON dependency.NodeID = node.NodeID
LEFT JOIN dbo.tblCalcTransactionBridge bridge
    ON bridge.CalcModelID = model.CalcModelID
LEFT JOIN dbo.tblCalcTransactionNodeMap map
    ON map.CalcTransactionBridgeID = bridge.CalcTransactionBridgeID
WHERE model.ModelCode IN (SELECT ModelCode FROM @LegacyModels)
GROUP BY model.ModelCode, bridge.LegacyCalculationID
ORDER BY bridge.LegacyCalculationID, model.ModelCode;
GO
