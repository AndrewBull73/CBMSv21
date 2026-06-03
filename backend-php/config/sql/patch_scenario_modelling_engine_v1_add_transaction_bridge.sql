USE [CBMSv2];
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
