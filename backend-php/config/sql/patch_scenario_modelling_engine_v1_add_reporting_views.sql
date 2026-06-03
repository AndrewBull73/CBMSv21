USE [CBMSv2];
GO

CREATE OR ALTER VIEW dbo.vwCalcPublishedResultCurrent
AS
SELECT
    pr.CalcPublishedResultID,
    pr.CalcPublishEventID,
    pr.SourceCalcRunID,
    pe.CalcRunID,
    pe.PublishStatusCode,
    pe.PublishedBy,
    pe.PublishedDate,
    pe.PublishedRowCount,
    pe.Notes AS PublishNotes,
    pr.CalcModelID,
    m.ModelCode,
    m.ModelName,
    m.ModelVersion,
    pr.ScenarioID,
    s.ScenarioCode,
    s.ScenarioName,
    s.ScenarioTypeCode,
    pr.CostObjectID,
    co.CostObjectCode,
    co.CostObjectName,
    co.CostObjectTypeCode,
    pr.PeriodID,
    p.PeriodCode,
    p.SequenceNo AS PeriodSequenceNo,
    p.StartDate AS PeriodStartDate,
    p.EndDate AS PeriodEndDate,
    pr.NodeID,
    n.NodeCode,
    n.NodeName,
    n.NodeTypeCode,
    n.NodeCategoryCode,
    n.UnitOfMeasureCode,
    n.NodeOrder,
    pr.ValueDecimal,
    pr.ValueText,
    pr.ValueBit,
    pr.PublishedDate AS ResultPublishedDate
FROM dbo.tblCalcPublishedResult AS pr
INNER JOIN dbo.tblCalcPublishEvent AS pe
    ON pe.CalcPublishEventID = pr.CalcPublishEventID
INNER JOIN dbo.tblCalcModel AS m
    ON m.CalcModelID = pr.CalcModelID
INNER JOIN dbo.tblCalcScenario AS s
    ON s.ScenarioID = pr.ScenarioID
INNER JOIN dbo.tblCalcCostObject AS co
    ON co.CostObjectID = pr.CostObjectID
INNER JOIN dbo.tblCalcPeriod AS p
    ON p.PeriodID = pr.PeriodID
INNER JOIN dbo.tblCalcNode AS n
    ON n.NodeID = pr.NodeID;
GO

CREATE OR ALTER VIEW dbo.vwCalcPublishedScenarioSummary
AS
SELECT
    pr.CalcModelID,
    m.ModelCode,
    m.ModelName,
    m.ModelVersion,
    pr.ScenarioID,
    s.ScenarioCode,
    s.ScenarioName,
    s.ScenarioTypeCode,
    MIN(pe.CalcPublishEventID) AS CalcPublishEventID,
    MIN(pe.CalcRunID) AS CalcRunID,
    MIN(pe.PublishStatusCode) AS PublishStatusCode,
    MAX(pe.PublishedDate) AS PublishedDate,
    MIN(pe.PublishedBy) AS PublishedBy,
    MAX(pe.PublishedRowCount) AS PublishedRowCount,
    COUNT_BIG(*) AS ResultRowCount,
    COUNT(DISTINCT pr.CostObjectID) AS CostObjectCount,
    COUNT(DISTINCT pr.PeriodID) AS PeriodCount,
    COUNT(DISTINCT pr.NodeID) AS NodeCount
FROM dbo.tblCalcPublishedResult AS pr
INNER JOIN dbo.tblCalcPublishEvent AS pe
    ON pe.CalcPublishEventID = pr.CalcPublishEventID
INNER JOIN dbo.tblCalcModel AS m
    ON m.CalcModelID = pr.CalcModelID
INNER JOIN dbo.tblCalcScenario AS s
    ON s.ScenarioID = pr.ScenarioID
GROUP BY
    pr.CalcModelID,
    m.ModelCode,
    m.ModelName,
    m.ModelVersion,
    pr.ScenarioID,
    s.ScenarioCode,
    s.ScenarioName,
    s.ScenarioTypeCode;
GO
