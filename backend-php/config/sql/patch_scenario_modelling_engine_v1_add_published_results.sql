USE [CBMSv2];
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
