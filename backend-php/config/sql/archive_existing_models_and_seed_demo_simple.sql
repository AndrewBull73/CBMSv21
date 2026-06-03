USE [CBMSv2];

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

DECLARE @DemoModelCode NVARCHAR(50) = N'DEMO_SIMPLE';
DECLARE @DemoModelName NVARCHAR(100) = N'Demo Simple Scenario Model';
DECLARE @DemoModelVersion INT = 1;

DECLARE @CalcModelID INT;
DECLARE @BaseScenarioID INT;
DECLARE @RateUpScenarioID INT;
DECLARE @CostObjectID INT;
DECLARE @PeriodID INT;
DECLARE @QtyNodeID INT;
DECLARE @RateNodeID INT;
DECLARE @TotalNodeID INT;
DECLARE @ArchivedModels TABLE (CalcModelID INT NOT NULL PRIMARY KEY);

INSERT INTO @ArchivedModels (CalcModelID)
SELECT m.CalcModelID
FROM dbo.tblCalcModel m
WHERE m.ModelCode <> @DemoModelCode
  AND (m.ActiveFlag = 1 OR ISNULL(m.StatusCode, N'') <> N'ARCHIVED');

UPDATE m
SET m.ActiveFlag = 0,
    m.StatusCode = N'ARCHIVED',
    m.UpdatedBy = 1,
    m.UpdatedDate = SYSUTCDATETIME()
FROM dbo.tblCalcModel m
INNER JOIN @ArchivedModels archived ON archived.CalcModelID = m.CalcModelID;

UPDATE s
SET s.ActiveFlag = 0,
    s.ScenarioStatusCode = N'ARCHIVED',
    s.UpdatedBy = 1,
    s.UpdatedDate = SYSUTCDATETIME()
FROM dbo.tblCalcScenario s
INNER JOIN @ArchivedModels archived ON archived.CalcModelID = s.CalcModelID;

UPDATE co
SET co.ActiveFlag = 0,
    co.UpdatedBy = 1,
    co.UpdatedDate = SYSUTCDATETIME()
FROM dbo.tblCalcCostObject co
INNER JOIN @ArchivedModels archived ON archived.CalcModelID = co.CalcModelID;

UPDATE p
SET p.ActiveFlag = 0,
    p.UpdatedBy = 1,
    p.UpdatedDate = SYSUTCDATETIME()
FROM dbo.tblCalcPeriod p
INNER JOIN @ArchivedModels archived ON archived.CalcModelID = p.CalcModelID;

UPDATE n
SET n.ActiveFlag = 0,
    n.UpdatedBy = 1,
    n.UpdatedDate = SYSUTCDATETIME()
FROM dbo.tblCalcNode n
INNER JOIN @ArchivedModels archived ON archived.CalcModelID = n.CalcModelID;

UPDATE f
SET f.ActiveFlag = 0,
    f.UpdatedBy = 1,
    f.UpdatedDate = SYSUTCDATETIME()
FROM dbo.tblCalcFormula f
INNER JOIN dbo.tblCalcNode n ON n.NodeID = f.NodeID
INNER JOIN @ArchivedModels archived ON archived.CalcModelID = n.CalcModelID;

UPDATE b
SET b.ActiveFlag = 0,
    b.UpdatedBy = 1,
    b.UpdatedDate = SYSUTCDATETIME()
FROM dbo.tblCalcTransactionBridge b
INNER JOIN @ArchivedModels archived ON archived.CalcModelID = b.CalcModelID;

DELETE published
FROM dbo.tblCalcPublishedResult published
INNER JOIN @ArchivedModels archived ON archived.CalcModelID = published.CalcModelID;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcModel
    WHERE ModelCode = @DemoModelCode
      AND ModelVersion = @DemoModelVersion
)
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
        @DemoModelCode,
        @DemoModelName,
        @DemoModelVersion,
        N'ACTIVE',
        1,
        1
    );
END;

UPDATE dbo.tblCalcModel
SET ModelName = @DemoModelName,
    StatusCode = N'ACTIVE',
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE ModelCode = @DemoModelCode
  AND ModelVersion = @DemoModelVersion;

SELECT @CalcModelID = CalcModelID
FROM dbo.tblCalcModel
WHERE ModelCode = @DemoModelCode
  AND ModelVersion = @DemoModelVersion;

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
END;

SELECT @BaseScenarioID = ScenarioID
FROM dbo.tblCalcScenario
WHERE CalcModelID = @CalcModelID
  AND ScenarioCode = N'BASE';

UPDATE dbo.tblCalcScenario
SET ParentScenarioID = NULL,
    ScenarioName = N'Base Scenario',
    ScenarioTypeCode = N'BASE',
    ScenarioStatusCode = N'ACTIVE',
    SortOrder = 10,
    LockedFlag = 0,
    ApprovedFlag = 1,
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE ScenarioID = @BaseScenarioID;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcScenario
    WHERE CalcModelID = @CalcModelID
      AND ScenarioCode = N'RATE_UP'
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
        @BaseScenarioID,
        N'RATE_UP',
        N'Rate Up Scenario',
        N'WHATIF',
        N'ACTIVE',
        20,
        0,
        0,
        1,
        1
    );
END;

SELECT @RateUpScenarioID = ScenarioID
FROM dbo.tblCalcScenario
WHERE CalcModelID = @CalcModelID
  AND ScenarioCode = N'RATE_UP';

UPDATE dbo.tblCalcScenario
SET ParentScenarioID = @BaseScenarioID,
    ScenarioName = N'Rate Up Scenario',
    ScenarioTypeCode = N'WHATIF',
    ScenarioStatusCode = N'ACTIVE',
    SortOrder = 20,
    LockedFlag = 0,
    ApprovedFlag = 0,
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE ScenarioID = @RateUpScenarioID;

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
        N'Global',
        N'GENERIC',
        1,
        1
    );
END;

SELECT @CostObjectID = CostObjectID
FROM dbo.tblCalcCostObject
WHERE CalcModelID = @CalcModelID
  AND CostObjectCode = N'GLOBAL';

UPDATE dbo.tblCalcCostObject
SET CostObjectName = N'Global',
    CostObjectTypeCode = N'GENERIC',
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE CostObjectID = @CostObjectID;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcPeriod
    WHERE CalcModelID = @CalcModelID
      AND PeriodCode = N'TXN'
)
BEGIN
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
    VALUES
    (
        @CalcModelID,
        N'TXN',
        2026,
        1,
        N'TXN',
        CAST('2026-01-01' AS DATE),
        CAST('2026-12-31' AS DATE),
        1,
        1,
        1
    );
END;

SELECT @PeriodID = PeriodID
FROM dbo.tblCalcPeriod
WHERE CalcModelID = @CalcModelID
  AND PeriodCode = N'TXN';

UPDATE dbo.tblCalcPeriod
SET FiscalYearID = 2026,
    PeriodNo = 1,
    PeriodTypeCode = N'TXN',
    StartDate = CAST('2026-01-01' AS DATE),
    EndDate = CAST('2026-12-31' AS DATE),
    SequenceNo = 1,
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE PeriodID = @PeriodID;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcNode
    WHERE CalcModelID = @CalcModelID
      AND NodeCode = N'Qty'
)
BEGIN
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
    VALUES
    (
        @CalcModelID,
        N'Qty',
        N'Quantity',
        N'INPUT',
        N'DRIVER',
        N'DECIMAL',
        N'UNIT',
        2,
        0,
        10,
        0,
        1,
        1
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcNode
    WHERE CalcModelID = @CalcModelID
      AND NodeCode = N'Rate'
)
BEGIN
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
    VALUES
    (
        @CalcModelID,
        N'Rate',
        N'Rate',
        N'INPUT',
        N'DRIVER',
        N'DECIMAL',
        N'CUR',
        2,
        0,
        20,
        0,
        1,
        1
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcNode
    WHERE CalcModelID = @CalcModelID
      AND NodeCode = N'Total'
)
BEGIN
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
    VALUES
    (
        @CalcModelID,
        N'Total',
        N'Total',
        N'FORMULA',
        N'SUMMARY',
        N'DECIMAL',
        N'CUR',
        2,
        30,
        1,
        1,
        1
    );
END;

SELECT @QtyNodeID = NodeID
FROM dbo.tblCalcNode
WHERE CalcModelID = @CalcModelID
  AND NodeCode = N'Qty';

SELECT @RateNodeID = NodeID
FROM dbo.tblCalcNode
WHERE CalcModelID = @CalcModelID
  AND NodeCode = N'Rate';

SELECT @TotalNodeID = NodeID
FROM dbo.tblCalcNode
WHERE CalcModelID = @CalcModelID
  AND NodeCode = N'Total';

UPDATE dbo.tblCalcNode
SET NodeName = N'Quantity',
    NodeTypeCode = N'INPUT',
    NodeCategoryCode = N'DRIVER',
    DataTypeCode = N'DECIMAL',
    UnitOfMeasureCode = N'UNIT',
    DecimalScale = 2,
    DefaultDecimalValue = 0,
    NodeOrder = 10,
    OutputFlag = 0,
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE NodeID = @QtyNodeID;

UPDATE dbo.tblCalcNode
SET NodeName = N'Rate',
    NodeTypeCode = N'INPUT',
    NodeCategoryCode = N'DRIVER',
    DataTypeCode = N'DECIMAL',
    UnitOfMeasureCode = N'CUR',
    DecimalScale = 2,
    DefaultDecimalValue = 0,
    NodeOrder = 20,
    OutputFlag = 0,
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE NodeID = @RateNodeID;

UPDATE dbo.tblCalcNode
SET NodeName = N'Total',
    NodeTypeCode = N'FORMULA',
    NodeCategoryCode = N'SUMMARY',
    DataTypeCode = N'DECIMAL',
    UnitOfMeasureCode = N'CUR',
    DecimalScale = 2,
    NodeOrder = 30,
    OutputFlag = 1,
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE NodeID = @TotalNodeID;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcFormula
    WHERE NodeID = @TotalNodeID
)
BEGIN
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
    VALUES
    (
        @TotalNodeID,
        N'@Qty@ * @Rate@',
        NULL,
        N'EXPR',
        N'1',
        1,
        1
    );
END;

UPDATE dbo.tblCalcFormula
SET ExpressionText = N'@Qty@ * @Rate@',
    ExpressionLanguageCode = N'EXPR',
    ParserVersion = N'1',
    ActiveFlag = 1,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE NodeID = @TotalNodeID;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcDependency
    WHERE NodeID = @TotalNodeID
      AND DependsOnNodeID = @QtyNodeID
)
BEGIN
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
    VALUES
    (
        @TotalNodeID,
        @QtyNodeID,
        N'DIRECT',
        0,
        1,
        10,
        1
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM dbo.tblCalcDependency
    WHERE NodeID = @TotalNodeID
      AND DependsOnNodeID = @RateNodeID
)
BEGIN
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
    VALUES
    (
        @TotalNodeID,
        @RateNodeID,
        N'DIRECT',
        0,
        1,
        20,
        1
    );
END;

UPDATE dbo.tblCalcDependency
SET DependencyTypeCode = N'DIRECT',
    OffsetPeriods = 0,
    RequiredFlag = 1,
    SortOrder = CASE
        WHEN DependsOnNodeID = @QtyNodeID THEN 10
        WHEN DependsOnNodeID = @RateNodeID THEN 20
        ELSE SortOrder
    END,
    UpdatedBy = 1,
    UpdatedDate = SYSUTCDATETIME()
WHERE NodeID = @TotalNodeID
  AND DependsOnNodeID IN (@QtyNodeID, @RateNodeID);

DELETE FROM dbo.tblScenarioNodeValue
WHERE ScenarioID IN (@BaseScenarioID, @RateUpScenarioID)
  AND NodeID IN (@QtyNodeID, @RateNodeID)
  AND PeriodID = @PeriodID
  AND CostObjectID = @CostObjectID;

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
VALUES
(@BaseScenarioID, @CostObjectID, @PeriodID, @QtyNodeID, 10.00, N'SEED', 1, N'Base quantity', 1),
(@BaseScenarioID, @CostObjectID, @PeriodID, @RateNodeID, 100.00, N'SEED', 1, N'Base rate', 1),
(@RateUpScenarioID, @CostObjectID, @PeriodID, @RateNodeID, 120.00, N'SEED', 1, N'What-if rate override', 1);

COMMIT TRANSACTION;
