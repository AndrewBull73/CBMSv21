USE [CBMSv2];
GO

/*
Scenario Modelling Engine v1
----------------------------
This schema is intentionally separate from the current transaction/Redis batch
path. It introduces a generic model/scenario/node/result structure that a new
in-memory C# calculation engine can target.

Core design rules:
- SQL Server remains the system of record.
- Inputs are stored as scenario overrides in tblScenarioNodeValue.
- Formula nodes are evaluated in memory using explicit dependencies.
- Results are persisted in bulk by run.
- The engine should not perform SQL reads/writes inside the calculation loop.
*/

IF OBJECT_ID('dbo.tblCalcModel', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcModel (
        CalcModelID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ModelCode NVARCHAR(50) NOT NULL,
        ModelName NVARCHAR(200) NOT NULL,
        ModelVersion INT NOT NULL CONSTRAINT DF_tblCalcModel_ModelVersion DEFAULT (1),
        StatusCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcModel_StatusCode DEFAULT (N'DRAFT'),
        EffectiveFrom DATE NULL,
        EffectiveTo DATE NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblCalcModel_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcModel_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcModel_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcScenario', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcScenario (
        ScenarioID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcModelID INT NOT NULL,
        ParentScenarioID INT NULL,
        ScenarioCode NVARCHAR(50) NOT NULL,
        ScenarioName NVARCHAR(200) NOT NULL,
        ScenarioTypeCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcScenario_ScenarioTypeCode DEFAULT (N'BASE'),
        ScenarioStatusCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcScenario_ScenarioStatusCode DEFAULT (N'DRAFT'),
        SortOrder INT NOT NULL CONSTRAINT DF_tblCalcScenario_SortOrder DEFAULT (100),
        LockedFlag BIT NOT NULL CONSTRAINT DF_tblCalcScenario_LockedFlag DEFAULT (0),
        ApprovedFlag BIT NOT NULL CONSTRAINT DF_tblCalcScenario_ApprovedFlag DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblCalcScenario_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcScenario_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcScenario_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcPeriod', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcPeriod (
        PeriodID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcModelID INT NOT NULL,
        PeriodCode NVARCHAR(20) NOT NULL,
        FiscalYearID INT NULL,
        PeriodNo INT NULL,
        PeriodTypeCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcPeriod_PeriodTypeCode DEFAULT (N'MONTH'),
        StartDate DATE NULL,
        EndDate DATE NULL,
        SequenceNo INT NOT NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblCalcPeriod_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcPeriod_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcPeriod_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcCostObject', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcCostObject (
        CostObjectID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcModelID INT NOT NULL,
        ParentCostObjectID INT NULL,
        CostObjectCode NVARCHAR(100) NOT NULL,
        CostObjectName NVARCHAR(200) NOT NULL,
        CostObjectTypeCode NVARCHAR(50) NOT NULL CONSTRAINT DF_tblCalcCostObject_CostObjectTypeCode DEFAULT (N'GENERIC'),
        CostObjectGroupCode NVARCHAR(50) NULL,
        SourceEntityTypeCode NVARCHAR(30) NULL,
        SourceEntityCode NVARCHAR(100) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblCalcCostObject_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcCostObject_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcCostObject_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcNode', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcNode (
        NodeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcModelID INT NOT NULL,
        NodeCode NVARCHAR(100) NOT NULL,
        NodeName NVARCHAR(200) NOT NULL,
        NodeTypeCode NVARCHAR(20) NOT NULL,
        NodeCategoryCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblCalcNode_NodeCategoryCode DEFAULT (N'GENERAL'),
        DataTypeCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcNode_DataTypeCode DEFAULT (N'DECIMAL'),
        UnitOfMeasureCode NVARCHAR(20) NULL,
        DecimalScale TINYINT NOT NULL CONSTRAINT DF_tblCalcNode_DecimalScale DEFAULT (6),
        DefaultDecimalValue DECIMAL(19,6) NULL,
        DefaultTextValue NVARCHAR(4000) NULL,
        DefaultBitValue BIT NULL,
        NodeOrder INT NOT NULL CONSTRAINT DF_tblCalcNode_NodeOrder DEFAULT (100),
        OutputFlag BIT NOT NULL CONSTRAINT DF_tblCalcNode_OutputFlag DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblCalcNode_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcNode_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcNode_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcFormula', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcFormula (
        CalcFormulaID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        NodeID INT NOT NULL,
        ExpressionText NVARCHAR(MAX) NOT NULL,
        ExpressionHash VARCHAR(64) NULL,
        ExpressionLanguageCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblCalcFormula_ExpressionLanguageCode DEFAULT (N'TOKEN_ARITH'),
        ParserVersion NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcFormula_ParserVersion DEFAULT (N'v1'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblCalcFormula_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcFormula_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcFormula_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcDependency', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcDependency (
        CalcDependencyID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        NodeID INT NOT NULL,
        DependsOnNodeID INT NOT NULL,
        DependencyTypeCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcDependency_DependencyTypeCode DEFAULT (N'VALUE'),
        OffsetPeriods INT NOT NULL CONSTRAINT DF_tblCalcDependency_OffsetPeriods DEFAULT (0),
        RequiredFlag BIT NOT NULL CONSTRAINT DF_tblCalcDependency_RequiredFlag DEFAULT (1),
        SortOrder INT NOT NULL CONSTRAINT DF_tblCalcDependency_SortOrder DEFAULT (100),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcDependency_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcDependency_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblCalcDependency_NotSelf CHECK (NodeID <> DependsOnNodeID OR OffsetPeriods <> 0)
    );
END
GO

IF OBJECT_ID('dbo.tblScenarioNodeValue', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblScenarioNodeValue (
        ScenarioNodeValueID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioID INT NOT NULL,
        CostObjectID INT NOT NULL,
        PeriodID INT NOT NULL,
        NodeID INT NOT NULL,
        ValueDecimal DECIMAL(19,6) NULL,
        ValueText NVARCHAR(4000) NULL,
        ValueBit BIT NULL,
        ValueSourceCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblScenarioNodeValue_ValueSourceCode DEFAULT (N'OVERRIDE'),
        OverriddenFlag BIT NOT NULL CONSTRAINT DF_tblScenarioNodeValue_OverriddenFlag DEFAULT (1),
        CommentText NVARCHAR(500) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblScenarioNodeValue_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblScenarioNodeValue_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcRun', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcRun (
        CalcRunID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcModelID INT NOT NULL,
        ScenarioID INT NULL,
        RunCode UNIQUEIDENTIFIER NOT NULL CONSTRAINT DF_tblCalcRun_RunCode DEFAULT (NEWID()),
        RunTypeCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcRun_RunTypeCode DEFAULT (N'FULL'),
        RunStatusCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcRun_RunStatusCode DEFAULT (N'QUEUED'),
        TriggerSourceCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcRun_TriggerSourceCode DEFAULT (N'PHP'),
        EngineVersion NVARCHAR(50) NULL,
        InputSnapshotHash VARCHAR(64) NULL,
        TriggeredBy INT NOT NULL CONSTRAINT DF_tblCalcRun_TriggeredBy DEFAULT (1),
        StartedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcRun_StartedDate DEFAULT (SYSDATETIME()),
        CompletedDate DATETIME2(0) NULL,
        RowCountProcessed INT NOT NULL CONSTRAINT DF_tblCalcRun_RowCountProcessed DEFAULT (0),
        ErrorCount INT NOT NULL CONSTRAINT DF_tblCalcRun_ErrorCount DEFAULT (0),
        Notes NVARCHAR(500) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcRunResult', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcRunResult (
        CalcRunResultID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcRunID BIGINT NOT NULL,
        ScenarioID INT NOT NULL,
        CostObjectID INT NOT NULL,
        PeriodID INT NOT NULL,
        NodeID INT NOT NULL,
        ValueDecimal DECIMAL(19,6) NULL,
        ValueText NVARCHAR(4000) NULL,
        ValueBit BIT NULL,
        CalculationStatusCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcRunResult_CalculationStatusCode DEFAULT (N'OK'),
        CalculatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcRunResult_CalculatedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF OBJECT_ID('dbo.tblCalcRunError', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcRunError (
        CalcRunErrorID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcRunID BIGINT NOT NULL,
        ScenarioID INT NULL,
        CostObjectID INT NULL,
        PeriodID INT NULL,
        NodeID INT NULL,
        ErrorCode NVARCHAR(50) NOT NULL,
        ErrorSeverityCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcRunError_ErrorSeverityCode DEFAULT (N'ERROR'),
        ErrorMessage NVARCHAR(2000) NOT NULL,
        ExpressionText NVARCHAR(MAX) NULL,
        ContextJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcRunError_CreatedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF OBJECT_ID('dbo.tblCalcPublishEvent', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcPublishEvent (
        CalcPublishEventID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcRunID BIGINT NOT NULL,
        CalcModelID INT NOT NULL,
        ScenarioID INT NOT NULL,
        PublishStatusCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblCalcPublishEvent_PublishStatusCode DEFAULT (N'PUBLISHED'),
        PublishedBy INT NOT NULL CONSTRAINT DF_tblCalcPublishEvent_PublishedBy DEFAULT (1),
        PublishedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcPublishEvent_PublishedDate DEFAULT (SYSDATETIME()),
        PublishedRowCount INT NOT NULL CONSTRAINT DF_tblCalcPublishEvent_PublishedRowCount DEFAULT (0),
        Notes NVARCHAR(500) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcPublishedResult', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcPublishedResult (
        CalcPublishedResultID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcPublishEventID BIGINT NOT NULL,
        SourceCalcRunID BIGINT NOT NULL,
        CalcModelID INT NOT NULL,
        ScenarioID INT NOT NULL,
        CostObjectID INT NOT NULL,
        PeriodID INT NOT NULL,
        NodeID INT NOT NULL,
        ValueDecimal DECIMAL(19,6) NULL,
        ValueText NVARCHAR(4000) NULL,
        ValueBit BIT NULL,
        PublishedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcPublishedResult_PublishedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF OBJECT_ID('dbo.tblCalcTransactionBridge', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcTransactionBridge (
        CalcTransactionBridgeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcModelID INT NOT NULL,
        ScenarioID INT NOT NULL,
        CostObjectID INT NULL,
        LegacyCalculationID INT NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        TransactionTypeCode NVARCHAR(50) NULL,
        UOMCodeInpC NVARCHAR(50) NULL,
        PriorityNo INT NOT NULL CONSTRAINT DF_tblCalcTransactionBridge_PriorityNo DEFAULT (100),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblCalcTransactionBridge_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(500) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcTransactionBridge_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcTransactionBridge_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF OBJECT_ID('dbo.tblCalcTransactionNodeMap', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcTransactionNodeMap (
        CalcTransactionNodeMapID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        CalcTransactionBridgeID INT NOT NULL,
        NodeID INT NOT NULL,
        SourceTypeCode NVARCHAR(30) NOT NULL,
        SourceName NVARCHAR(128) NULL,
        ConstantDecimal DECIMAL(19,6) NULL,
        RequiredFlag BIT NOT NULL CONSTRAINT DF_tblCalcTransactionNodeMap_RequiredFlag DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblCalcTransactionNodeMap_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(500) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcTransactionNodeMap_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcTransactionNodeMap_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcScenario_tblCalcModel'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcScenario')
)
BEGIN
    ALTER TABLE dbo.tblCalcScenario
        ADD CONSTRAINT FK_tblCalcScenario_tblCalcModel
        FOREIGN KEY (CalcModelID) REFERENCES dbo.tblCalcModel (CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcScenario_tblCalcScenario_Parent'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcScenario')
)
BEGIN
    ALTER TABLE dbo.tblCalcScenario
        ADD CONSTRAINT FK_tblCalcScenario_tblCalcScenario_Parent
        FOREIGN KEY (ParentScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPeriod_tblCalcModel'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPeriod')
)
BEGIN
    ALTER TABLE dbo.tblCalcPeriod
        ADD CONSTRAINT FK_tblCalcPeriod_tblCalcModel
        FOREIGN KEY (CalcModelID) REFERENCES dbo.tblCalcModel (CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcCostObject_tblCalcModel'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcCostObject')
)
BEGIN
    ALTER TABLE dbo.tblCalcCostObject
        ADD CONSTRAINT FK_tblCalcCostObject_tblCalcModel
        FOREIGN KEY (CalcModelID) REFERENCES dbo.tblCalcModel (CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcCostObject_tblCalcCostObject_Parent'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcCostObject')
)
BEGIN
    ALTER TABLE dbo.tblCalcCostObject
        ADD CONSTRAINT FK_tblCalcCostObject_tblCalcCostObject_Parent
        FOREIGN KEY (ParentCostObjectID) REFERENCES dbo.tblCalcCostObject (CostObjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcNode_tblCalcModel'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcNode')
)
BEGIN
    ALTER TABLE dbo.tblCalcNode
        ADD CONSTRAINT FK_tblCalcNode_tblCalcModel
        FOREIGN KEY (CalcModelID) REFERENCES dbo.tblCalcModel (CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcFormula_tblCalcNode'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcFormula')
)
BEGIN
    ALTER TABLE dbo.tblCalcFormula
        ADD CONSTRAINT FK_tblCalcFormula_tblCalcNode
        FOREIGN KEY (NodeID) REFERENCES dbo.tblCalcNode (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcDependency_tblCalcNode'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcDependency')
)
BEGIN
    ALTER TABLE dbo.tblCalcDependency
        ADD CONSTRAINT FK_tblCalcDependency_tblCalcNode
        FOREIGN KEY (NodeID) REFERENCES dbo.tblCalcNode (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcDependency_tblCalcNode_DependsOn'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcDependency')
)
BEGIN
    ALTER TABLE dbo.tblCalcDependency
        ADD CONSTRAINT FK_tblCalcDependency_tblCalcNode_DependsOn
        FOREIGN KEY (DependsOnNodeID) REFERENCES dbo.tblCalcNode (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblScenarioNodeValue_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblScenarioNodeValue')
)
BEGIN
    ALTER TABLE dbo.tblScenarioNodeValue
        ADD CONSTRAINT FK_tblScenarioNodeValue_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblScenarioNodeValue_tblCalcCostObject'
      AND parent_object_id = OBJECT_ID('dbo.tblScenarioNodeValue')
)
BEGIN
    ALTER TABLE dbo.tblScenarioNodeValue
        ADD CONSTRAINT FK_tblScenarioNodeValue_tblCalcCostObject
        FOREIGN KEY (CostObjectID) REFERENCES dbo.tblCalcCostObject (CostObjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblScenarioNodeValue_tblCalcPeriod'
      AND parent_object_id = OBJECT_ID('dbo.tblScenarioNodeValue')
)
BEGIN
    ALTER TABLE dbo.tblScenarioNodeValue
        ADD CONSTRAINT FK_tblScenarioNodeValue_tblCalcPeriod
        FOREIGN KEY (PeriodID) REFERENCES dbo.tblCalcPeriod (PeriodID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblScenarioNodeValue_tblCalcNode'
      AND parent_object_id = OBJECT_ID('dbo.tblScenarioNodeValue')
)
BEGIN
    ALTER TABLE dbo.tblScenarioNodeValue
        ADD CONSTRAINT FK_tblScenarioNodeValue_tblCalcNode
        FOREIGN KEY (NodeID) REFERENCES dbo.tblCalcNode (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRun_tblCalcModel'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRun')
)
BEGIN
    ALTER TABLE dbo.tblCalcRun
        ADD CONSTRAINT FK_tblCalcRun_tblCalcModel
        FOREIGN KEY (CalcModelID) REFERENCES dbo.tblCalcModel (CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRun_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRun')
)
BEGIN
    ALTER TABLE dbo.tblCalcRun
        ADD CONSTRAINT FK_tblCalcRun_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunResult_tblCalcRun'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunResult
        ADD CONSTRAINT FK_tblCalcRunResult_tblCalcRun
        FOREIGN KEY (CalcRunID) REFERENCES dbo.tblCalcRun (CalcRunID) ON DELETE CASCADE;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunResult_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunResult
        ADD CONSTRAINT FK_tblCalcRunResult_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunResult_tblCalcCostObject'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunResult
        ADD CONSTRAINT FK_tblCalcRunResult_tblCalcCostObject
        FOREIGN KEY (CostObjectID) REFERENCES dbo.tblCalcCostObject (CostObjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunResult_tblCalcPeriod'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunResult
        ADD CONSTRAINT FK_tblCalcRunResult_tblCalcPeriod
        FOREIGN KEY (PeriodID) REFERENCES dbo.tblCalcPeriod (PeriodID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunResult_tblCalcNode'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunResult
        ADD CONSTRAINT FK_tblCalcRunResult_tblCalcNode
        FOREIGN KEY (NodeID) REFERENCES dbo.tblCalcNode (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunError_tblCalcRun'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunError')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunError
        ADD CONSTRAINT FK_tblCalcRunError_tblCalcRun
        FOREIGN KEY (CalcRunID) REFERENCES dbo.tblCalcRun (CalcRunID) ON DELETE CASCADE;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunError_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunError')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunError
        ADD CONSTRAINT FK_tblCalcRunError_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunError_tblCalcCostObject'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunError')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunError
        ADD CONSTRAINT FK_tblCalcRunError_tblCalcCostObject
        FOREIGN KEY (CostObjectID) REFERENCES dbo.tblCalcCostObject (CostObjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunError_tblCalcPeriod'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunError')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunError
        ADD CONSTRAINT FK_tblCalcRunError_tblCalcPeriod
        FOREIGN KEY (PeriodID) REFERENCES dbo.tblCalcPeriod (PeriodID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcRunError_tblCalcNode'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcRunError')
)
BEGIN
    ALTER TABLE dbo.tblCalcRunError
        ADD CONSTRAINT FK_tblCalcRunError_tblCalcNode
        FOREIGN KEY (NodeID) REFERENCES dbo.tblCalcNode (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishEvent_tblCalcRun'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishEvent')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishEvent
        ADD CONSTRAINT FK_tblCalcPublishEvent_tblCalcRun
        FOREIGN KEY (CalcRunID) REFERENCES dbo.tblCalcRun (CalcRunID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishEvent_tblCalcModel'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishEvent')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishEvent
        ADD CONSTRAINT FK_tblCalcPublishEvent_tblCalcModel
        FOREIGN KEY (CalcModelID) REFERENCES dbo.tblCalcModel (CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishEvent_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishEvent')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishEvent
        ADD CONSTRAINT FK_tblCalcPublishEvent_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishedResult_tblCalcPublishEvent'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishedResult
        ADD CONSTRAINT FK_tblCalcPublishedResult_tblCalcPublishEvent
        FOREIGN KEY (CalcPublishEventID) REFERENCES dbo.tblCalcPublishEvent (CalcPublishEventID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishedResult_tblCalcRun'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishedResult
        ADD CONSTRAINT FK_tblCalcPublishedResult_tblCalcRun
        FOREIGN KEY (SourceCalcRunID) REFERENCES dbo.tblCalcRun (CalcRunID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishedResult_tblCalcModel'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishedResult
        ADD CONSTRAINT FK_tblCalcPublishedResult_tblCalcModel
        FOREIGN KEY (CalcModelID) REFERENCES dbo.tblCalcModel (CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishedResult_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishedResult
        ADD CONSTRAINT FK_tblCalcPublishedResult_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishedResult_tblCalcCostObject'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishedResult
        ADD CONSTRAINT FK_tblCalcPublishedResult_tblCalcCostObject
        FOREIGN KEY (CostObjectID) REFERENCES dbo.tblCalcCostObject (CostObjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishedResult_tblCalcPeriod'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishedResult
        ADD CONSTRAINT FK_tblCalcPublishedResult_tblCalcPeriod
        FOREIGN KEY (PeriodID) REFERENCES dbo.tblCalcPeriod (PeriodID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcPublishedResult_tblCalcNode'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
)
BEGIN
    ALTER TABLE dbo.tblCalcPublishedResult
        ADD CONSTRAINT FK_tblCalcPublishedResult_tblCalcNode
        FOREIGN KEY (NodeID) REFERENCES dbo.tblCalcNode (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcTransactionBridge_tblCalcModel'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcTransactionBridge')
)
BEGIN
    ALTER TABLE dbo.tblCalcTransactionBridge
        ADD CONSTRAINT FK_tblCalcTransactionBridge_tblCalcModel
        FOREIGN KEY (CalcModelID) REFERENCES dbo.tblCalcModel (CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcTransactionBridge_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcTransactionBridge')
)
BEGIN
    ALTER TABLE dbo.tblCalcTransactionBridge
        ADD CONSTRAINT FK_tblCalcTransactionBridge_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcTransactionBridge_tblCalcCostObject'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcTransactionBridge')
)
BEGIN
    ALTER TABLE dbo.tblCalcTransactionBridge
        ADD CONSTRAINT FK_tblCalcTransactionBridge_tblCalcCostObject
        FOREIGN KEY (CostObjectID) REFERENCES dbo.tblCalcCostObject (CostObjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcTransactionNodeMap_tblCalcTransactionBridge'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcTransactionNodeMap')
)
BEGIN
    ALTER TABLE dbo.tblCalcTransactionNodeMap
        ADD CONSTRAINT FK_tblCalcTransactionNodeMap_tblCalcTransactionBridge
        FOREIGN KEY (CalcTransactionBridgeID) REFERENCES dbo.tblCalcTransactionBridge (CalcTransactionBridgeID) ON DELETE CASCADE;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcTransactionNodeMap_tblCalcNode'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcTransactionNodeMap')
)
BEGIN
    ALTER TABLE dbo.tblCalcTransactionNodeMap
        ADD CONSTRAINT FK_tblCalcTransactionNodeMap_tblCalcNode
        FOREIGN KEY (NodeID) REFERENCES dbo.tblCalcNode (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcModel')
      AND name = 'UX_tblCalcModel_ModelCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcModel_ModelCode
        ON dbo.tblCalcModel (ModelCode, ModelVersion);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcScenario')
      AND name = 'UX_tblCalcScenario_ModelScenarioCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcScenario_ModelScenarioCode
        ON dbo.tblCalcScenario (CalcModelID, ScenarioCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcScenario')
      AND name = 'IX_tblCalcScenario_Parent'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcScenario_Parent
        ON dbo.tblCalcScenario (ParentScenarioID, CalcModelID, SortOrder);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcPeriod')
      AND name = 'UX_tblCalcPeriod_ModelPeriodCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcPeriod_ModelPeriodCode
        ON dbo.tblCalcPeriod (CalcModelID, PeriodCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcPeriod')
      AND name = 'UX_tblCalcPeriod_ModelSequenceNo'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcPeriod_ModelSequenceNo
        ON dbo.tblCalcPeriod (CalcModelID, SequenceNo);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcCostObject')
      AND name = 'UX_tblCalcCostObject_ModelCostObjectCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcCostObject_ModelCostObjectCode
        ON dbo.tblCalcCostObject (CalcModelID, CostObjectCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcCostObject')
      AND name = 'IX_tblCalcCostObject_Parent'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcCostObject_Parent
        ON dbo.tblCalcCostObject (ParentCostObjectID, CalcModelID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcNode')
      AND name = 'UX_tblCalcNode_ModelNodeCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcNode_ModelNodeCode
        ON dbo.tblCalcNode (CalcModelID, NodeCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcNode')
      AND name = 'IX_tblCalcNode_ModelExecution'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcNode_ModelExecution
        ON dbo.tblCalcNode (CalcModelID, ActiveFlag, NodeOrder, NodeCode)
        INCLUDE (NodeTypeCode, NodeCategoryCode, OutputFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcFormula')
      AND name = 'UX_tblCalcFormula_NodeID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcFormula_NodeID
        ON dbo.tblCalcFormula (NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcDependency')
      AND name = 'UX_tblCalcDependency_NodeDependsOn'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcDependency_NodeDependsOn
        ON dbo.tblCalcDependency (NodeID, DependsOnNodeID, DependencyTypeCode, OffsetPeriods);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcDependency')
      AND name = 'IX_tblCalcDependency_DependsOn'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcDependency_DependsOn
        ON dbo.tblCalcDependency (DependsOnNodeID, NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblScenarioNodeValue')
      AND name = 'UX_tblScenarioNodeValue_Key'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblScenarioNodeValue_Key
        ON dbo.tblScenarioNodeValue (ScenarioID, CostObjectID, PeriodID, NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblScenarioNodeValue')
      AND name = 'IX_tblScenarioNodeValue_NodeLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblScenarioNodeValue_NodeLookup
        ON dbo.tblScenarioNodeValue (NodeID, ScenarioID, PeriodID)
        INCLUDE (CostObjectID, ValueDecimal, ValueText, ValueBit, OverriddenFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcRun')
      AND name = 'UX_tblCalcRun_RunCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcRun_RunCode
        ON dbo.tblCalcRun (RunCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcRun')
      AND name = 'IX_tblCalcRun_Status'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcRun_Status
        ON dbo.tblCalcRun (CalcModelID, RunStatusCode, StartedDate DESC)
        INCLUDE (ScenarioID, RunTypeCode, ErrorCount, RowCountProcessed);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcRunResult')
      AND name = 'UX_tblCalcRunResult_Key'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcRunResult_Key
        ON dbo.tblCalcRunResult (CalcRunID, ScenarioID, CostObjectID, PeriodID, NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcRunResult')
      AND name = 'IX_tblCalcRunResult_ScenarioView'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcRunResult_ScenarioView
        ON dbo.tblCalcRunResult (ScenarioID, CostObjectID, PeriodID, NodeID)
        INCLUDE (CalcRunID, ValueDecimal, ValueText, ValueBit, CalculationStatusCode, CalculatedDate);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcRunError')
      AND name = 'IX_tblCalcRunError_Run'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcRunError_Run
        ON dbo.tblCalcRunError (CalcRunID, ErrorSeverityCode, CreatedDate DESC)
        INCLUDE (ScenarioID, CostObjectID, PeriodID, NodeID, ErrorCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcPublishEvent')
      AND name = 'IX_tblCalcPublishEvent_ModelScenario'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcPublishEvent_ModelScenario
        ON dbo.tblCalcPublishEvent (CalcModelID, ScenarioID, PublishedDate DESC)
        INCLUDE (CalcRunID, PublishStatusCode, PublishedRowCount);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
      AND name = 'UX_tblCalcPublishedResult_CurrentKey'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcPublishedResult_CurrentKey
        ON dbo.tblCalcPublishedResult (CalcModelID, ScenarioID, CostObjectID, PeriodID, NodeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcPublishedResult')
      AND name = 'IX_tblCalcPublishedResult_PublishEvent'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcPublishedResult_PublishEvent
        ON dbo.tblCalcPublishedResult (CalcPublishEventID, SourceCalcRunID)
        INCLUDE (CalcModelID, ScenarioID, CostObjectID, PeriodID, NodeID, ValueDecimal, ValueText, ValueBit, PublishedDate);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcTransactionBridge')
      AND name = 'IX_tblCalcTransactionBridge_Route'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcTransactionBridge_Route
        ON dbo.tblCalcTransactionBridge (ActiveFlag, LegacyCalculationID, FiscalYearID, VersionID, TransactionTypeCode, UOMCodeInpC, PriorityNo)
        INCLUDE (CalcModelID, ScenarioID, CostObjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcTransactionNodeMap')
      AND name = 'UX_tblCalcTransactionNodeMap_Key'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcTransactionNodeMap_Key
        ON dbo.tblCalcTransactionNodeMap (CalcTransactionBridgeID, NodeID, SourceTypeCode, SourceName);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcTransactionNodeMap')
      AND name = 'IX_tblCalcTransactionNodeMap_Bridge'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblCalcTransactionNodeMap_Bridge
        ON dbo.tblCalcTransactionNodeMap (CalcTransactionBridgeID, ActiveFlag)
        INCLUDE (NodeID, SourceTypeCode, SourceName, ConstantDecimal, RequiredFlag);
END
GO

/*
Recommended bootstrap pattern:
1. Create one model row.
2. Create one BASE scenario row.
3. Load monthly periods for the model.
4. Insert at least one GLOBAL cost object plus the real cost objects.
5. Insert nodes and formulas.
6. Generate tblCalcDependency rows from parsed formula tokens.
7. Store scenario input overrides in tblScenarioNodeValue.
8. Create tblCalcTransactionBridge / tblCalcTransactionNodeMap rows when a
   transaction input path should call the new engine.
*/
