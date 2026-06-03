USE [CBMSv2];
GO

/*
Scenario Modelling Engine v1 demo seed
--------------------------------------
Creates a minimal generic model that can be used to validate:
- model/scenario/period loading
- node and formula metadata loading
- dependency graph wiring
- base scenario input values

The sample is intentionally domain-neutral.
*/

SET NOCOUNT ON;
GO

DECLARE @ModelCode NVARCHAR(50) = N'SCENARIO_V1_DEMO';
DECLARE @CalcModelID INT;
DECLARE @ScenarioID INT;
DECLARE @WhatIfScenarioID INT;
DECLARE @CostObjectID INT;

SELECT @CalcModelID = CalcModelID
FROM dbo.tblCalcModel
WHERE ModelCode = @ModelCode
  AND ModelVersion = 1;

IF @CalcModelID IS NULL
BEGIN
    INSERT INTO dbo.tblCalcModel
    (
        ModelCode,
        ModelName,
        ModelVersion,
        StatusCode,
        ActiveFlag,
        CreatedBy
    )
    VALUES
    (
        @ModelCode,
        N'Scenario Engine Demo Model',
        1,
        N'ACTIVE',
        1,
        1
    );

    SET @CalcModelID = SCOPE_IDENTITY();
END
GO

DECLARE @ModelCode NVARCHAR(50) = N'SCENARIO_V1_DEMO';
DECLARE @CalcModelID INT;
DECLARE @ScenarioID INT;
DECLARE @WhatIfScenarioID INT;
DECLARE @CostObjectID INT;

SELECT @CalcModelID = CalcModelID
FROM dbo.tblCalcModel
WHERE ModelCode = @ModelCode
  AND ModelVersion = 1;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcScenario
    WHERE CalcModelID = @CalcModelID
      AND ScenarioCode = N'BASE'
)
BEGIN
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
    VALUES
    (
        @CalcModelID,
        NULL,
        N'BASE',
        N'Base Scenario',
        N'BASE',
        N'ACTIVE',
        10,
        0,
        1,
        1,
        1
    );
END

SELECT @ScenarioID = ScenarioID
FROM dbo.tblCalcScenario
WHERE CalcModelID = @CalcModelID
  AND ScenarioCode = N'BASE';

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcScenario
    WHERE CalcModelID = @CalcModelID
      AND ScenarioCode = N'WHATIF_HEADCOUNT_UP'
)
BEGIN
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
    VALUES
    (
        @CalcModelID,
        @ScenarioID,
        N'WHATIF_HEADCOUNT_UP',
        N'What If Headcount Up',
        N'WHATIF',
        N'ACTIVE',
        20,
        0,
        0,
        1,
        1
    );
END

SELECT @WhatIfScenarioID = ScenarioID
FROM dbo.tblCalcScenario
WHERE CalcModelID = @CalcModelID
  AND ScenarioCode = N'WHATIF_HEADCOUNT_UP';

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcCostObject
    WHERE CalcModelID = @CalcModelID
      AND CostObjectCode = N'GLOBAL'
)
BEGIN
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
    VALUES
    (
        @CalcModelID,
        NULL,
        N'GLOBAL',
        N'Global Demo Cost Object',
        N'GENERIC',
        1,
        1
    );
END

SELECT @CostObjectID = CostObjectID
FROM dbo.tblCalcCostObject
WHERE CalcModelID = @CalcModelID
  AND CostObjectCode = N'GLOBAL';

;WITH Periods AS (
    SELECT
        1 AS SequenceNo, N'2026-01' AS PeriodCode, CAST('2026-01-01' AS DATE) AS StartDate, CAST('2026-01-31' AS DATE) AS EndDate
    UNION ALL SELECT 2, N'2026-02', '2026-02-01', '2026-02-28'
    UNION ALL SELECT 3, N'2026-03', '2026-03-01', '2026-03-31'
    UNION ALL SELECT 4, N'2026-04', '2026-04-01', '2026-04-30'
    UNION ALL SELECT 5, N'2026-05', '2026-05-01', '2026-05-31'
    UNION ALL SELECT 6, N'2026-06', '2026-06-01', '2026-06-30'
    UNION ALL SELECT 7, N'2026-07', '2026-07-01', '2026-07-31'
    UNION ALL SELECT 8, N'2026-08', '2026-08-01', '2026-08-31'
    UNION ALL SELECT 9, N'2026-09', '2026-09-01', '2026-09-30'
    UNION ALL SELECT 10, N'2026-10', '2026-10-01', '2026-10-31'
    UNION ALL SELECT 11, N'2026-11', '2026-11-01', '2026-11-30'
    UNION ALL SELECT 12, N'2026-12', '2026-12-01', '2026-12-31'
)
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
    @CalcModelID,
    p.PeriodCode,
    2026,
    p.SequenceNo,
    N'MONTH',
    p.StartDate,
    p.EndDate,
    p.SequenceNo,
    1,
    1
FROM Periods p
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcPeriod existing
    WHERE existing.CalcModelID = @CalcModelID
      AND existing.PeriodCode = p.PeriodCode
);

;WITH Nodes AS (
    SELECT N'Headcount' AS NodeCode, N'Headcount' AS NodeName, N'INPUT' AS NodeTypeCode, N'DRIVER' AS NodeCategoryCode, N'DECIMAL' AS DataTypeCode, N'FTE' AS UnitOfMeasureCode, 10 AS NodeOrder, 0 AS OutputFlag, CAST(0 AS DECIMAL(19,6)) AS DefaultDecimalValue
    UNION ALL SELECT N'WageRate', N'Wage Rate', N'INPUT', N'DRIVER', N'DECIMAL', N'CUR', 20, 0, 0
    UNION ALL SELECT N'UtilisationPct', N'Utilisation Percent', N'INPUT', N'DRIVER', N'DECIMAL', N'PCT', 30, 0, 1
    UNION ALL SELECT N'RevenueVolume', N'Revenue Volume', N'INPUT', N'DRIVER', N'DECIMAL', N'UNIT', 40, 0, 0
    UNION ALL SELECT N'AveragePrice', N'Average Price', N'INPUT', N'DRIVER', N'DECIMAL', N'CUR', 50, 0, 0
    UNION ALL SELECT N'DirectCost', N'Direct Cost', N'FORMULA', N'COST', N'DECIMAL', N'CUR', 100, 1, NULL
    UNION ALL SELECT N'Revenue', N'Revenue', N'FORMULA', N'REVENUE', N'DECIMAL', N'CUR', 110, 1, NULL
    UNION ALL SELECT N'Margin', N'Margin', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 120, 1, NULL
    UNION ALL SELECT N'RevenuePerHead', N'Revenue Per Head', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 130, 1, NULL
    UNION ALL SELECT N'PositiveMarginFlag', N'Positive Margin Flag', N'FORMULA', N'SUMMARY', N'DECIMAL', N'FLAG', 140, 1, NULL
    UNION ALL SELECT N'MarginFloor', N'Margin Floor', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 150, 1, NULL
    UNION ALL SELECT N'RevenuePreviousPeriod', N'Revenue Previous Period', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 160, 1, NULL
    UNION ALL SELECT N'RevenueNextPeriod', N'Revenue Next Period', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 170, 1, NULL
    UNION ALL SELECT N'RevenueThreeMonthWindow', N'Revenue Three Month Window', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 180, 1, NULL
    UNION ALL SELECT N'RevenueMarchBaseline', N'Revenue March Baseline', N'FORMULA', N'SUMMARY', N'DECIMAL', N'CUR', 190, 1, NULL
    UNION ALL SELECT N'IfShortCircuitProof', N'IF Short Circuit Proof', N'FORMULA', N'SUMMARY', N'DECIMAL', N'FLAG', 200, 1, NULL
)
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
    DefaultDecimalValue,
    NodeOrder,
    OutputFlag,
    ActiveFlag,
    CreatedBy
)
SELECT
    @CalcModelID,
    n.NodeCode,
    n.NodeName,
    n.NodeTypeCode,
    n.NodeCategoryCode,
    n.DataTypeCode,
    n.UnitOfMeasureCode,
    6,
    n.DefaultDecimalValue,
    n.NodeOrder,
    n.OutputFlag,
    1,
    1
FROM Nodes n
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcNode existing
    WHERE existing.CalcModelID = @CalcModelID
      AND existing.NodeCode = n.NodeCode
);

;WITH FormulaSeed AS (
    SELECT N'DirectCost' AS NodeCode, N'@Headcount@ * @WageRate@ * @UtilisationPct@' AS ExpressionText
    UNION ALL SELECT N'Revenue', N'@RevenueVolume@ * @AveragePrice@'
    UNION ALL SELECT N'Margin', N'@Revenue@ - @DirectCost@'
    UNION ALL SELECT N'RevenuePerHead', N'ROUND(@Revenue@ / @Headcount@, 2)'
    UNION ALL SELECT N'PositiveMarginFlag', N'IF(@Margin@ > 0, 1, 0)'
    UNION ALL SELECT N'MarginFloor', N'MAX(@Margin@, 0)'
    UNION ALL SELECT N'RevenuePreviousPeriod', N'@Revenue[-1]@'
    UNION ALL SELECT N'RevenueNextPeriod', N'@Revenue[+1]@'
    UNION ALL SELECT N'RevenueThreeMonthWindow', N'@Revenue[-1]@ + @Revenue@ + @Revenue[+1]@'
    UNION ALL SELECT N'RevenueMarchBaseline', N'@Revenue[2026-03]@'
    UNION ALL SELECT N'IfShortCircuitProof', N'IF(@Headcount@ > 0, 1, 1 / 0)'
)
INSERT INTO dbo.tblCalcFormula
(
    NodeID,
    ExpressionText,
    ExpressionHash,
    ExpressionLanguageCode,
    ParserVersion,
    ActiveFlag,
    CreatedBy
)
SELECT
    n.NodeID,
    f.ExpressionText,
    CONVERT(VARCHAR(64), HASHBYTES('SHA2_256', CONVERT(VARBINARY(MAX), f.ExpressionText)), 2),
    N'TOKEN_ARITH',
    N'v1',
    1,
    1
FROM FormulaSeed f
INNER JOIN dbo.tblCalcNode n
    ON n.CalcModelID = @CalcModelID
   AND n.NodeCode = f.NodeCode
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcFormula existing
    WHERE existing.NodeID = n.NodeID
);

;WITH DependencySeed AS (
    SELECT 1 AS SeedOrder, N'DirectCost' AS NodeCode, N'Headcount' AS DependsOnNodeCode
    UNION ALL SELECT 2, N'DirectCost', N'WageRate'
    UNION ALL SELECT 3, N'DirectCost', N'UtilisationPct'
    UNION ALL SELECT 4, N'Revenue', N'RevenueVolume'
    UNION ALL SELECT 5, N'Revenue', N'AveragePrice'
    UNION ALL SELECT 6, N'Margin', N'Revenue'
    UNION ALL SELECT 7, N'Margin', N'DirectCost'
    UNION ALL SELECT 8, N'RevenuePerHead', N'Revenue'
    UNION ALL SELECT 9, N'RevenuePerHead', N'Headcount'
    UNION ALL SELECT 10, N'PositiveMarginFlag', N'Margin'
    UNION ALL SELECT 11, N'MarginFloor', N'Margin'
    UNION ALL SELECT 12, N'RevenuePreviousPeriod', N'Revenue'
    UNION ALL SELECT 13, N'RevenueNextPeriod', N'Revenue'
    UNION ALL SELECT 14, N'RevenueThreeMonthWindow', N'Revenue'
    UNION ALL SELECT 15, N'RevenueThreeMonthWindow', N'Revenue'
    UNION ALL SELECT 16, N'RevenueThreeMonthWindow', N'Revenue'
    UNION ALL SELECT 17, N'RevenueMarchBaseline', N'Revenue'
    UNION ALL SELECT 18, N'IfShortCircuitProof', N'Headcount'
),
NumberedDependencySeed AS (
    SELECT
        SeedOrder,
        NodeCode,
        DependsOnNodeCode,
        ROW_NUMBER() OVER (PARTITION BY NodeCode, DependsOnNodeCode ORDER BY SeedOrder) AS RowNo
    FROM DependencySeed
)
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
    nodeTarget.NodeID,
    nodeSource.NodeID,
    N'VALUE',
    CASE
        WHEN d.NodeCode = N'RevenuePreviousPeriod' THEN -1
        WHEN d.NodeCode = N'RevenueNextPeriod' THEN 1
        WHEN d.NodeCode = N'RevenueThreeMonthWindow' AND d.RowNo = 1 THEN -1
        WHEN d.NodeCode = N'RevenueThreeMonthWindow' AND d.RowNo = 3 THEN 1
        ELSE 0
    END,
    CASE
        WHEN d.NodeCode IN (N'RevenuePreviousPeriod', N'RevenueNextPeriod') THEN 0
        WHEN d.NodeCode = N'RevenueThreeMonthWindow' AND d.RowNo IN (1, 3) THEN 0
        ELSE 1
    END,
    100,
    1
FROM NumberedDependencySeed d
INNER JOIN dbo.tblCalcNode nodeTarget
    ON nodeTarget.CalcModelID = @CalcModelID
   AND nodeTarget.NodeCode = d.NodeCode
INNER JOIN dbo.tblCalcNode nodeSource
    ON nodeSource.CalcModelID = @CalcModelID
   AND nodeSource.NodeCode = d.DependsOnNodeCode
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcDependency existing
    WHERE existing.NodeID = nodeTarget.NodeID
      AND existing.DependsOnNodeID = nodeSource.NodeID
      AND existing.DependencyTypeCode = N'VALUE'
      AND existing.OffsetPeriods = CASE
          WHEN d.NodeCode = N'RevenuePreviousPeriod' THEN -1
          WHEN d.NodeCode = N'RevenueNextPeriod' THEN 1
          WHEN d.NodeCode = N'RevenueThreeMonthWindow' AND d.RowNo = 1 THEN -1
          WHEN d.NodeCode = N'RevenueThreeMonthWindow' AND d.RowNo = 3 THEN 1
          ELSE 0
      END
);

;WITH InputSeed AS (
    SELECT N'Headcount' AS NodeCode, CAST(10.000000 AS DECIMAL(19,6)) AS ValueDecimal
    UNION ALL SELECT N'WageRate', 8000.000000
    UNION ALL SELECT N'UtilisationPct', 0.850000
    UNION ALL SELECT N'RevenueVolume', 1000.000000
    UNION ALL SELECT N'AveragePrice', 15.000000
)
INSERT INTO dbo.tblScenarioNodeValue
(
    ScenarioID,
    CostObjectID,
    PeriodID,
    NodeID,
    ValueDecimal,
    ValueSourceCode,
    OverriddenFlag,
    CreatedBy
)
SELECT
    @ScenarioID,
    @CostObjectID,
    p.PeriodID,
    n.NodeID,
    i.ValueDecimal,
    N'BASE',
    0,
    1
FROM InputSeed i
INNER JOIN dbo.tblCalcNode n
    ON n.CalcModelID = @CalcModelID
   AND n.NodeCode = i.NodeCode
INNER JOIN dbo.tblCalcPeriod p
    ON p.CalcModelID = @CalcModelID
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblScenarioNodeValue existing
    WHERE existing.ScenarioID = @ScenarioID
      AND existing.CostObjectID = @CostObjectID
      AND existing.PeriodID = p.PeriodID
      AND existing.NodeID = n.NodeID
);

;WITH RevenueVolumeByPeriod AS (
    SELECT N'2026-01' AS PeriodCode, CAST(950.000000 AS DECIMAL(19,6)) AS ValueDecimal
    UNION ALL SELECT N'2026-02', 975.000000
    UNION ALL SELECT N'2026-03', 1000.000000
    UNION ALL SELECT N'2026-04', 1025.000000
    UNION ALL SELECT N'2026-05', 1050.000000
    UNION ALL SELECT N'2026-06', 1075.000000
    UNION ALL SELECT N'2026-07', 1100.000000
    UNION ALL SELECT N'2026-08', 1125.000000
    UNION ALL SELECT N'2026-09', 1150.000000
    UNION ALL SELECT N'2026-10', 1175.000000
    UNION ALL SELECT N'2026-11', 1200.000000
    UNION ALL SELECT N'2026-12', 1225.000000
)
UPDATE target
SET
    target.ValueDecimal = revenue.ValueDecimal,
    target.ValueSourceCode = N'BASE',
    target.OverriddenFlag = 0,
    target.CommentText = N'Demo seasonal revenue pattern for cross-period validation.',
    target.UpdatedBy = 1,
    target.UpdatedDate = SYSDATETIME()
FROM dbo.tblScenarioNodeValue target
INNER JOIN dbo.tblCalcPeriod periodMap
    ON periodMap.PeriodID = target.PeriodID
INNER JOIN RevenueVolumeByPeriod revenue
    ON revenue.PeriodCode = periodMap.PeriodCode
INNER JOIN dbo.tblCalcNode revenueNode
    ON revenueNode.NodeID = target.NodeID
WHERE target.ScenarioID = @ScenarioID
  AND target.CostObjectID = @CostObjectID
  AND revenueNode.CalcModelID = @CalcModelID
  AND revenueNode.NodeCode = N'RevenueVolume';

INSERT INTO dbo.tblScenarioNodeValue
(
    ScenarioID,
    CostObjectID,
    PeriodID,
    NodeID,
    ValueDecimal,
    ValueSourceCode,
    OverriddenFlag,
    CommentText,
    CreatedBy
)
SELECT
    @WhatIfScenarioID,
    @CostObjectID,
    p.PeriodID,
    n.NodeID,
    CAST(12.000000 AS DECIMAL(19,6)),
    N'OVERRIDE',
    1,
    N'Increase headcount from 10 to 12 for inheritance validation.',
    1
FROM dbo.tblCalcNode n
INNER JOIN dbo.tblCalcPeriod p
    ON p.CalcModelID = @CalcModelID
WHERE n.CalcModelID = @CalcModelID
  AND n.NodeCode = N'Headcount'
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.tblScenarioNodeValue existing
      WHERE existing.ScenarioID = @WhatIfScenarioID
        AND existing.CostObjectID = @CostObjectID
        AND existing.PeriodID = p.PeriodID
        AND existing.NodeID = n.NodeID
  );

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcTransactionBridge bridge
    WHERE bridge.CalcModelID = @CalcModelID
      AND bridge.ScenarioID = @ScenarioID
      AND bridge.CostObjectID = @CostObjectID
      AND bridge.LegacyCalculationID = 2002
      AND bridge.FiscalYearID = 2026
      AND bridge.TransactionTypeCode = N'11'
      AND bridge.UOMCodeInpC = N'APS1'
)
BEGIN
    INSERT INTO dbo.tblCalcTransactionBridge
    (
        CalcModelID,
        ScenarioID,
        CostObjectID,
        LegacyCalculationID,
        FiscalYearID,
        TransactionTypeCode,
        UOMCodeInpC,
        PriorityNo,
        ActiveFlag,
        Notes,
        CreatedBy
    )
    VALUES
    (
        @CalcModelID,
        @ScenarioID,
        @CostObjectID,
        2002,
        2026,
        N'11',
        N'APS1',
        10,
        1,
        N'Demo-only bridge marker; not used for legacy transaction routing.',
        1
    );
END

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
    N'COLUMN_PATTERN',
    N'BP{PeriodNo}InpN',
    NULL,
    0,
    1,
    N'Overlay RevenueVolume from monthly transaction input columns.',
    1
FROM dbo.tblCalcTransactionBridge bridge
INNER JOIN dbo.tblCalcNode node
    ON node.CalcModelID = bridge.CalcModelID
   AND node.NodeCode = N'RevenueVolume'
WHERE bridge.CalcModelID = @CalcModelID
  AND bridge.ScenarioID = @ScenarioID
  AND bridge.CostObjectID = @CostObjectID
  AND bridge.LegacyCalculationID = 2002
  AND bridge.FiscalYearID = 2026
  AND bridge.TransactionTypeCode = N'11'
  AND bridge.UOMCodeInpC = N'APS1'
  AND NOT EXISTS (
      SELECT 1
      FROM dbo.tblCalcTransactionNodeMap existing
      WHERE existing.CalcTransactionBridgeID = bridge.CalcTransactionBridgeID
        AND existing.NodeID = node.NodeID
        AND existing.SourceTypeCode = N'COLUMN_PATTERN'
        AND existing.SourceName = N'BP{PeriodNo}InpN'
  );

SELECT
    m.CalcModelID,
    m.ModelCode,
    s.ScenarioID,
    s.ScenarioCode,
    co.CostObjectID,
    co.CostObjectCode,
    (SELECT COUNT(*) FROM dbo.tblCalcPeriod p WHERE p.CalcModelID = m.CalcModelID) AS PeriodCount,
    (SELECT COUNT(*) FROM dbo.tblCalcNode n WHERE n.CalcModelID = m.CalcModelID) AS NodeCount,
    (SELECT COUNT(*) FROM dbo.tblCalcFormula f INNER JOIN dbo.tblCalcNode n ON n.NodeID = f.NodeID WHERE n.CalcModelID = m.CalcModelID) AS FormulaCount,
    (SELECT COUNT(*) FROM dbo.tblCalcDependency d INNER JOIN dbo.tblCalcNode n ON n.NodeID = d.NodeID WHERE n.CalcModelID = m.CalcModelID) AS DependencyCount
FROM dbo.tblCalcModel m
INNER JOIN dbo.tblCalcScenario s
    ON s.CalcModelID = m.CalcModelID
   AND s.ScenarioCode IN (N'BASE', N'WHATIF_HEADCOUNT_UP')
INNER JOIN dbo.tblCalcCostObject co
    ON co.CalcModelID = m.CalcModelID
   AND co.CostObjectCode = N'GLOBAL'
WHERE m.CalcModelID = @CalcModelID;
GO
