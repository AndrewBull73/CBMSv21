SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblIntelligenceEngineRuns', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntelligenceEngineRuns
    (
        EngineRunID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        RunTypeCode NVARCHAR(60) NOT NULL,
        RequestJson NVARCHAR(MAX) NULL,
        ResponseJson NVARCHAR(MAX) NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblIntelligenceEngineRuns_StatusCode DEFAULT (N'SUCCESS'),
        ErrorMessage NVARCHAR(2000) NULL,
        ResponseTimeMs INT NULL,
        ProviderCode NVARCHAR(80) NULL,
        ExternalServiceUsed BIT NOT NULL CONSTRAINT DF_tblIntelligenceEngineRuns_ExternalServiceUsed DEFAULT (0),
        DataMaskingUsed BIT NOT NULL CONSTRAINT DF_tblIntelligenceEngineRuns_DataMaskingUsed DEFAULT (0),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntelligenceEngineRuns_CreatedDate DEFAULT (SYSUTCDATETIME())
    );

    CREATE INDEX IX_tblIntelligenceEngineRuns_CreatedDate
        ON dbo.tblIntelligenceEngineRuns (CreatedDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblIntelligenceForecasts', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntelligenceForecasts
    (
        ForecastID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ForecastCode NVARCHAR(80) NOT NULL,
        ForecastName NVARCHAR(255) NOT NULL,
        ForecastTypeCode NVARCHAR(60) NOT NULL,
        MethodCode NVARCHAR(60) NOT NULL,
        SourceDatasetCode NVARCHAR(80) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        AssumptionsJson NVARCHAR(MAX) NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblIntelligenceForecasts_StatusCode DEFAULT (N'DRAFT'),
        ConfidenceScore DECIMAL(9,4) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntelligenceForecasts_CreatedDate DEFAULT (SYSUTCDATETIME()),
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntelligenceForecasts_ActiveFlag DEFAULT (1)
    );

    CREATE UNIQUE INDEX UX_tblIntelligenceForecasts_Code
        ON dbo.tblIntelligenceForecasts (ForecastCode);
END;
GO

IF OBJECT_ID(N'dbo.tblIntelligenceForecastResults', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntelligenceForecastResults
    (
        ForecastResultID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ForecastID INT NOT NULL,
        PeriodCode NVARCHAR(40) NULL,
        DimensionCode NVARCHAR(120) NULL,
        DimensionName NVARCHAR(255) NULL,
        ForecastAmount DECIMAL(28,6) NULL,
        LowerBoundAmount DECIMAL(28,6) NULL,
        UpperBoundAmount DECIMAL(28,6) NULL,
        DriverJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntelligenceForecastResults_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblIntelligenceForecastResults_Forecast
            FOREIGN KEY (ForecastID) REFERENCES dbo.tblIntelligenceForecasts(ForecastID)
    );
END;
GO

IF OBJECT_ID(N'dbo.tblIntelligenceScenarios', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntelligenceScenarios
    (
        ScenarioID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(80) NOT NULL,
        ScenarioName NVARCHAR(255) NOT NULL,
        ScenarioTypeCode NVARCHAR(60) NOT NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        BaselineReference NVARCHAR(255) NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblIntelligenceScenarios_StatusCode DEFAULT (N'DRAFT'),
        RiskLevelCode NVARCHAR(40) NULL,
        AdvisoryDisclaimer NVARCHAR(500) NOT NULL CONSTRAINT DF_tblIntelligenceScenarios_AdvisoryDisclaimer DEFAULT (N'This recommendation is advisory only. Final decisions remain subject to authorised CBMS review and approval.'),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntelligenceScenarios_CreatedDate DEFAULT (SYSUTCDATETIME()),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntelligenceScenarios_ActiveFlag DEFAULT (1)
    );

    CREATE UNIQUE INDEX UX_tblIntelligenceScenarios_Code
        ON dbo.tblIntelligenceScenarios (ScenarioCode);
END;
GO

IF OBJECT_ID(N'dbo.tblIntelligenceScenarioAssumptions', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntelligenceScenarioAssumptions
    (
        ScenarioAssumptionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioID INT NOT NULL,
        AssumptionCode NVARCHAR(80) NOT NULL,
        AssumptionName NVARCHAR(255) NOT NULL,
        AssumptionValue DECIMAL(28,8) NULL,
        AssumptionText NVARCHAR(1000) NULL,
        ImpactDirectionCode NVARCHAR(20) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntelligenceScenarioAssumptions_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblIntelligenceScenarioAssumptions_Scenario
            FOREIGN KEY (ScenarioID) REFERENCES dbo.tblIntelligenceScenarios(ScenarioID)
    );
END;
GO

IF OBJECT_ID(N'dbo.tblIntelligenceScenarioResults', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntelligenceScenarioResults
    (
        ScenarioResultID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioID INT NOT NULL,
        ResultTypeCode NVARCHAR(60) NOT NULL,
        DimensionCode NVARCHAR(120) NULL,
        DimensionName NVARCHAR(255) NULL,
        BaselineAmount DECIMAL(28,6) NULL,
        ScenarioAmount DECIMAL(28,6) NULL,
        VarianceAmount AS (ScenarioAmount - BaselineAmount),
        VariancePercent DECIMAL(18,6) NULL,
        ResultJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntelligenceScenarioResults_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblIntelligenceScenarioResults_Scenario
            FOREIGN KEY (ScenarioID) REFERENCES dbo.tblIntelligenceScenarios(ScenarioID)
    );
END;
GO

IF OBJECT_ID(N'dbo.tblIntelligenceRiskScores', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntelligenceRiskScores
    (
        RiskScoreID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        EntityTypeCode NVARCHAR(60) NOT NULL,
        EntityCode NVARCHAR(120) NOT NULL,
        EntityName NVARCHAR(255) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        RiskScore DECIMAL(9,4) NOT NULL,
        RiskLevelCode NVARCHAR(40) NOT NULL,
        RiskDriversJson NVARCHAR(MAX) NULL,
        RecommendedActionsJson NVARCHAR(MAX) NULL,
        SourceRunID INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntelligenceRiskScores_CreatedDate DEFAULT (SYSUTCDATETIME())
    );

    CREATE INDEX IX_tblIntelligenceRiskScores_Context
        ON dbo.tblIntelligenceRiskScores (FiscalYearID, VersionID, EntityTypeCode, RiskLevelCode);
END;
GO

IF OBJECT_ID(N'dbo.tblIntelligenceInsights', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntelligenceInsights
    (
        InsightID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        InsightTypeCode NVARCHAR(60) NOT NULL,
        Title NVARCHAR(255) NOT NULL,
        Description NVARCHAR(MAX) NULL,
        SeverityCode NVARCHAR(40) NOT NULL,
        EvidenceJson NVARCHAR(MAX) NULL,
        RecommendedAction NVARCHAR(1000) NULL,
        RelatedRoute NVARCHAR(255) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        SourceRunID INT NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblIntelligenceInsights_StatusCode DEFAULT (N'OPEN'),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntelligenceInsights_CreatedDate DEFAULT (SYSUTCDATETIME())
    );

    CREATE INDEX IX_tblIntelligenceInsights_Context
        ON dbo.tblIntelligenceInsights (FiscalYearID, VersionID, SeverityCode, StatusCode);
END;
GO

IF OBJECT_ID(N'dbo.tblAnalyticsRuns', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAnalyticsRuns
    (
        AnalyticsRunID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        AnalysisTypeCode NVARCHAR(80) NOT NULL,
        AnalysisName NVARCHAR(255) NULL,
        DatasetID INT NULL,
        DatasetCode NVARCHAR(80) NULL,
        SourceObjectName NVARCHAR(256) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        ParametersJson NVARCHAR(MAX) NULL,
        SummaryJson NVARCHAR(MAX) NULL,
        InputRecordCount INT NULL,
        OutputRecordCount INT NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAnalyticsRuns_StatusCode DEFAULT (N'PENDING'),
        ErrorMessage NVARCHAR(2000) NULL,
        SourceEngineRunID INT NULL,
        StartedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAnalyticsRuns_StartedDate DEFAULT (SYSUTCDATETIME()),
        CompletedDate DATETIME2(0) NULL,
        ResponseTimeMs INT NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAnalyticsRuns_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblAnalyticsRuns_EngineRun
            FOREIGN KEY (SourceEngineRunID) REFERENCES dbo.tblIntelligenceEngineRuns(EngineRunID)
    );

    CREATE INDEX IX_tblAnalyticsRuns_Context
        ON dbo.tblAnalyticsRuns (FiscalYearID, VersionID, AnalysisTypeCode, StatusCode, StartedDate DESC);

    CREATE INDEX IX_tblAnalyticsRuns_Dataset
        ON dbo.tblAnalyticsRuns (DatasetCode, StatusCode, StartedDate DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblAnalyticsRunResults', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAnalyticsRunResults
    (
        AnalyticsResultID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        AnalyticsRunID INT NOT NULL,
        ResultTypeCode NVARCHAR(80) NOT NULL,
        EntityTypeCode NVARCHAR(80) NULL,
        EntityCode NVARCHAR(200) NULL,
        EntityName NVARCHAR(255) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        PeriodNo INT NULL,
        MetricName NVARCHAR(128) NULL,
        MetricValue DECIMAL(38,8) NULL,
        MetricUnitCode NVARCHAR(40) NULL,
        RankNo INT NULL,
        DimensionJson NVARCHAR(MAX) NULL,
        ResultJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAnalyticsRunResults_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblAnalyticsRunResults_Run
            FOREIGN KEY (AnalyticsRunID) REFERENCES dbo.tblAnalyticsRuns(AnalyticsRunID)
    );

    CREATE INDEX IX_tblAnalyticsRunResults_Run
        ON dbo.tblAnalyticsRunResults (AnalyticsRunID, ResultTypeCode, RankNo);

    CREATE INDEX IX_tblAnalyticsRunResults_Entity
        ON dbo.tblAnalyticsRunResults (EntityTypeCode, EntityCode, FiscalYearID, VersionID, PeriodNo)
        INCLUDE (MetricName, MetricValue);
END;
GO

IF OBJECT_ID(N'dbo.tblAnalyticsFindings', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAnalyticsFindings
    (
        AnalyticsFindingID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        AnalyticsRunID INT NOT NULL,
        FindingTypeCode NVARCHAR(80) NOT NULL,
        SeverityCode NVARCHAR(40) NOT NULL,
        Title NVARCHAR(255) NOT NULL,
        [Description] NVARCHAR(MAX) NULL,
        EntityTypeCode NVARCHAR(80) NULL,
        EntityCode NVARCHAR(200) NULL,
        EntityName NVARCHAR(255) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        PeriodNo INT NULL,
        Score DECIMAL(18,8) NULL,
        ConfidenceScore DECIMAL(9,4) NULL,
        EvidenceJson NVARCHAR(MAX) NULL,
        RecommendedAction NVARCHAR(1000) NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAnalyticsFindings_StatusCode DEFAULT (N'OPEN'),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAnalyticsFindings_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblAnalyticsFindings_Run
            FOREIGN KEY (AnalyticsRunID) REFERENCES dbo.tblAnalyticsRuns(AnalyticsRunID)
    );

    CREATE INDEX IX_tblAnalyticsFindings_Context
        ON dbo.tblAnalyticsFindings (FiscalYearID, VersionID, SeverityCode, StatusCode, CreatedDate DESC);

    CREATE INDEX IX_tblAnalyticsFindings_Entity
        ON dbo.tblAnalyticsFindings (EntityTypeCode, EntityCode, FindingTypeCode, SeverityCode);
END;
GO

IF OBJECT_ID(N'dbo.tblAnalyticsFeatureSignals', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAnalyticsFeatureSignals
    (
        AnalyticsFeatureSignalID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        AnalyticsRunID INT NOT NULL,
        AnalyticsFindingID BIGINT NULL,
        FeatureSetCode NVARCHAR(80) NOT NULL,
        EntityTypeCode NVARCHAR(80) NOT NULL,
        EntityCode NVARCHAR(200) NOT NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        PeriodNo INT NULL,
        FeatureName NVARCHAR(128) NOT NULL,
        FeatureValue DECIMAL(38,8) NULL,
        FeatureTextValue NVARCHAR(500) NULL,
        FeatureJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAnalyticsFeatureSignals_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblAnalyticsFeatureSignals_Run
            FOREIGN KEY (AnalyticsRunID) REFERENCES dbo.tblAnalyticsRuns(AnalyticsRunID),
        CONSTRAINT FK_tblAnalyticsFeatureSignals_Finding
            FOREIGN KEY (AnalyticsFindingID) REFERENCES dbo.tblAnalyticsFindings(AnalyticsFindingID)
    );

    CREATE INDEX IX_tblAnalyticsFeatureSignals_FeatureLookup
        ON dbo.tblAnalyticsFeatureSignals
            (FeatureSetCode, EntityTypeCode, EntityCode, FiscalYearID, VersionID, PeriodNo, FeatureName)
        INCLUDE (FeatureValue, FeatureTextValue, AnalyticsRunID, AnalyticsFindingID);

    CREATE INDEX IX_tblAnalyticsFeatureSignals_Run
        ON dbo.tblAnalyticsFeatureSignals (AnalyticsRunID, FeatureSetCode, FeatureName);
END;
GO

IF OBJECT_ID(N'dbo.vwAnalyticsMLFeatureSignals', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwAnalyticsMLFeatureSignals AS SELECT CAST(NULL AS BIGINT) AS AnalyticsFeatureSignalID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwAnalyticsMLFeatureSignals
AS
SELECT
    s.AnalyticsFeatureSignalID,
    s.AnalyticsRunID,
    r.AnalysisTypeCode,
    r.DatasetCode,
    r.SourceObjectName,
    s.AnalyticsFindingID,
    f.FindingTypeCode,
    f.SeverityCode,
    s.FeatureSetCode,
    s.EntityTypeCode,
    s.EntityCode,
    s.FiscalYearID,
    s.VersionID,
    s.PeriodNo,
    s.FeatureName,
    s.FeatureValue,
    s.FeatureTextValue,
    s.FeatureJson,
    s.CreatedDate
FROM dbo.tblAnalyticsFeatureSignals s
INNER JOIN dbo.tblAnalyticsRuns r
    ON r.AnalyticsRunID = s.AnalyticsRunID
LEFT JOIN dbo.tblAnalyticsFindings f
    ON f.AnalyticsFindingID = s.AnalyticsFindingID;
');
GO

IF OBJECT_ID(N'dbo.vwAnalyticsMLFeatureMatrix', N'V') IS NULL
BEGIN
    EXEC(N'CREATE VIEW dbo.vwAnalyticsMLFeatureMatrix AS SELECT CAST(NULL AS INT) AS AnalyticsRunID WHERE 1 = 0;');
END;
GO

EXEC(N'
ALTER VIEW dbo.vwAnalyticsMLFeatureMatrix
AS
SELECT
    s.AnalyticsRunID,
    r.AnalysisTypeCode,
    r.DatasetCode,
    r.SourceObjectName,
    s.AnalyticsFindingID,
    f.FindingTypeCode,
    f.SeverityCode,
    s.FeatureSetCode,
    s.EntityTypeCode,
    s.EntityCode,
    s.FiscalYearID,
    s.VersionID,
    s.PeriodNo,
    TargetScore = CAST(COALESCE(
        f.Score,
        MAX(CASE WHEN s.FeatureName IN (N''TargetScore'', N''RiskScore'', N''Score'') THEN s.FeatureValue END)
    ) AS DECIMAL(18,8)),
    ConfidenceScore = CAST(COALESCE(
        f.ConfidenceScore,
        MAX(CASE WHEN s.FeatureName = N''ConfidenceScore'' THEN s.FeatureValue END)
    ) AS DECIMAL(9,4)),
    BudgetAmount = CAST(MAX(CASE WHEN s.FeatureName = N''BudgetAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    ReleasedAmount = CAST(MAX(CASE WHEN s.FeatureName = N''ReleasedAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    WarrantAmount = CAST(MAX(CASE WHEN s.FeatureName = N''WarrantAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    CommitmentAmount = CAST(MAX(CASE WHEN s.FeatureName = N''CommitmentAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    ActualAmount = CAST(MAX(CASE WHEN s.FeatureName = N''ActualAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    AvailableBalance = CAST(MAX(CASE WHEN s.FeatureName = N''AvailableBalance'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    ExecutionRate = CAST(MAX(CASE WHEN s.FeatureName = N''ExecutionRate'' THEN s.FeatureValue END) AS DECIMAL(18,8)),
    BudgetSharePct = CAST(MAX(CASE WHEN s.FeatureName = N''BudgetSharePct'' THEN s.FeatureValue END) AS DECIMAL(18,8)),
    ActualSharePct = CAST(MAX(CASE WHEN s.FeatureName = N''ActualSharePct'' THEN s.FeatureValue END) AS DECIMAL(18,8)),
    CumulativeBudgetAmount = CAST(MAX(CASE WHEN s.FeatureName = N''CumulativeBudgetAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    CumulativeActualAmount = CAST(MAX(CASE WHEN s.FeatureName = N''CumulativeActualAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    CumulativeExecutionRate = CAST(MAX(CASE WHEN s.FeatureName = N''CumulativeExecutionRate'' THEN s.FeatureValue END) AS DECIMAL(18,8)),
    ExpectedExecutionRate = CAST(MAX(CASE WHEN s.FeatureName = N''ExpectedExecutionRate'' THEN s.FeatureValue END) AS DECIMAL(18,8)),
    VarianceAmount = CAST(MAX(CASE WHEN s.FeatureName = N''VarianceAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    VariancePct = CAST(MAX(CASE WHEN s.FeatureName = N''VariancePct'' THEN s.FeatureValue END) AS DECIMAL(18,8)),
    ActualSpikePct = CAST(MAX(CASE WHEN s.FeatureName = N''ActualSpikePct'' THEN s.FeatureValue END) AS DECIMAL(18,8)),
    PriorYearActualChangePct = CAST(MAX(CASE WHEN s.FeatureName = N''PriorYearActualChangePct'' THEN s.FeatureValue END) AS DECIMAL(18,8)),
    MaterialityAmount = CAST(MAX(CASE WHEN s.FeatureName = N''MaterialityAmount'' THEN s.FeatureValue END) AS DECIMAL(38,8)),
    IsActualWithoutBudget = CAST(MAX(CASE WHEN s.FeatureName = N''IsActualWithoutBudget'' THEN s.FeatureValue END) AS DECIMAL(9,4)),
    IsNegativeAvailableBalance = CAST(MAX(CASE WHEN s.FeatureName = N''IsNegativeAvailableBalance'' THEN s.FeatureValue END) AS DECIMAL(9,4)),
    IsActualSpike = CAST(MAX(CASE WHEN s.FeatureName = N''IsActualSpike'' THEN s.FeatureValue END) AS DECIMAL(9,4)),
    IsPriorYearActualSpike = CAST(MAX(CASE WHEN s.FeatureName = N''IsPriorYearActualSpike'' THEN s.FeatureValue END) AS DECIMAL(9,4)),
    IsDormantLineActivity = CAST(MAX(CASE WHEN s.FeatureName = N''IsDormantLineActivity'' THEN s.FeatureValue END) AS DECIMAL(9,4)),
    IsBudgetWithoutExecution = CAST(MAX(CASE WHEN s.FeatureName = N''IsBudgetWithoutExecution'' THEN s.FeatureValue END) AS DECIMAL(9,4)),
    IsAboveExpectedYTD = CAST(MAX(CASE WHEN s.FeatureName = N''IsAboveExpectedYTD'' THEN s.FeatureValue END) AS DECIMAL(9,4)),
    FeatureCount = COUNT_BIG(1),
    LastSignalDate = MAX(s.CreatedDate)
FROM dbo.tblAnalyticsFeatureSignals s
INNER JOIN dbo.tblAnalyticsRuns r
    ON r.AnalyticsRunID = s.AnalyticsRunID
LEFT JOIN dbo.tblAnalyticsFindings f
    ON f.AnalyticsFindingID = s.AnalyticsFindingID
GROUP BY
    s.AnalyticsRunID,
    r.AnalysisTypeCode,
    r.DatasetCode,
    r.SourceObjectName,
    s.AnalyticsFindingID,
    f.FindingTypeCode,
    f.SeverityCode,
    f.Score,
    f.ConfidenceScore,
    s.FeatureSetCode,
    s.EntityTypeCode,
    s.EntityCode,
    s.FiscalYearID,
    s.VersionID,
    s.PeriodNo;
');
GO

IF OBJECT_ID(N'dbo.tblMLModels', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblMLModels
    (
        MLModelID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ModelCode NVARCHAR(80) NOT NULL,
        ModelName NVARCHAR(255) NOT NULL,
        UseCaseCode NVARCHAR(80) NOT NULL,
        ModelTypeCode NVARCHAR(80) NOT NULL,
        ApprovedViewName NVARCHAR(256) NULL,
        TargetColumnName NVARCHAR(128) NULL,
        FeatureColumnsJson NVARCHAR(MAX) NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblMLModels_StatusCode DEFAULT (N'DRAFT'),
        AccuracyScore DECIMAL(9,4) NULL,
        LastTrainedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblMLModels_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblMLModels_CreatedDate DEFAULT (SYSUTCDATETIME())
    );

    CREATE UNIQUE INDEX UX_tblMLModels_Code
        ON dbo.tblMLModels (ModelCode);
END;
GO

IF OBJECT_ID(N'dbo.tblMLTrainingRuns', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblMLTrainingRuns
    (
        MLTrainingRunID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MLModelID INT NOT NULL,
        SourceAnalyticsRunID INT NULL,
        TrainingPeriodStart DATE NULL,
        TrainingPeriodEnd DATE NULL,
        MetricsJson NVARCHAR(MAX) NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblMLTrainingRuns_StatusCode DEFAULT (N'PENDING'),
        ErrorMessage NVARCHAR(2000) NULL,
        StartedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblMLTrainingRuns_StartedDate DEFAULT (SYSUTCDATETIME()),
        CompletedDate DATETIME2(0) NULL,
        CONSTRAINT FK_tblMLTrainingRuns_Model
            FOREIGN KEY (MLModelID) REFERENCES dbo.tblMLModels(MLModelID),
        CONSTRAINT FK_tblMLTrainingRuns_AnalyticsRun
            FOREIGN KEY (SourceAnalyticsRunID) REFERENCES dbo.tblAnalyticsRuns(AnalyticsRunID)
    );
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblMLPredictions
    (
        MLPredictionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MLModelID INT NOT NULL,
        SourceAnalyticsRunID INT NULL,
        SourceAnalyticsFindingID BIGINT NULL,
        EntityTypeCode NVARCHAR(60) NOT NULL,
        EntityCode NVARCHAR(120) NOT NULL,
        PredictionValue DECIMAL(28,8) NULL,
        RiskScore DECIMAL(9,4) NULL,
        ConfidenceScore DECIMAL(9,4) NULL,
        PredictionJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblMLPredictions_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblMLPredictions_Model
            FOREIGN KEY (MLModelID) REFERENCES dbo.tblMLModels(MLModelID),
        CONSTRAINT FK_tblMLPredictions_AnalyticsRun
            FOREIGN KEY (SourceAnalyticsRunID) REFERENCES dbo.tblAnalyticsRuns(AnalyticsRunID),
        CONSTRAINT FK_tblMLPredictions_AnalyticsFinding
            FOREIGN KEY (SourceAnalyticsFindingID) REFERENCES dbo.tblAnalyticsFindings(AnalyticsFindingID)
    );
END;
GO

IF OBJECT_ID(N'dbo.tblMLTrainingRuns', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblMLTrainingRuns', N'SourceAnalyticsRunID') IS NULL
BEGIN
    ALTER TABLE dbo.tblMLTrainingRuns
    ADD SourceAnalyticsRunID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblMLTrainingRuns', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblAnalyticsRuns', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_tblMLTrainingRuns_AnalyticsRun')
BEGIN
    ALTER TABLE dbo.tblMLTrainingRuns
    ADD CONSTRAINT FK_tblMLTrainingRuns_AnalyticsRun
        FOREIGN KEY (SourceAnalyticsRunID) REFERENCES dbo.tblAnalyticsRuns(AnalyticsRunID);
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblMLPredictions', N'SourceAnalyticsRunID') IS NULL
BEGIN
    ALTER TABLE dbo.tblMLPredictions
    ADD SourceAnalyticsRunID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblMLPredictions', N'SourceAnalyticsFindingID') IS NULL
BEGIN
    ALTER TABLE dbo.tblMLPredictions
    ADD SourceAnalyticsFindingID BIGINT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblAnalyticsRuns', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_tblMLPredictions_AnalyticsRun')
BEGIN
    ALTER TABLE dbo.tblMLPredictions
    ADD CONSTRAINT FK_tblMLPredictions_AnalyticsRun
        FOREIGN KEY (SourceAnalyticsRunID) REFERENCES dbo.tblAnalyticsRuns(AnalyticsRunID);
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblAnalyticsFindings', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = N'FK_tblMLPredictions_AnalyticsFinding')
BEGIN
    ALTER TABLE dbo.tblMLPredictions
    ADD CONSTRAINT FK_tblMLPredictions_AnalyticsFinding
        FOREIGN KEY (SourceAnalyticsFindingID) REFERENCES dbo.tblAnalyticsFindings(AnalyticsFindingID);
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NOT NULL
   AND NOT EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE object_id = OBJECT_ID(N'dbo.tblMLPredictions')
          AND name = N'IX_tblMLPredictions_AnalyticsSource'
   )
BEGIN
    CREATE INDEX IX_tblMLPredictions_AnalyticsSource
        ON dbo.tblMLPredictions (SourceAnalyticsRunID, SourceAnalyticsFindingID)
        WHERE SourceAnalyticsRunID IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblMLWorkflowEvents', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblMLWorkflowEvents
    (
        MLWorkflowEventID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MLModelID INT NOT NULL,
        MLPredictionID INT NULL,
        ActionCode NVARCHAR(80) NOT NULL,
        FromStatusCode NVARCHAR(40) NULL,
        ToStatusCode NVARCHAR(40) NULL,
        Notes NVARCHAR(2000) NULL,
        EvidenceJson NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblMLWorkflowEvents_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblMLWorkflowEvents_Model
            FOREIGN KEY (MLModelID) REFERENCES dbo.tblMLModels(MLModelID),
        CONSTRAINT FK_tblMLWorkflowEvents_Prediction
            FOREIGN KEY (MLPredictionID) REFERENCES dbo.tblMLPredictions(MLPredictionID)
    );

    CREATE INDEX IX_tblMLWorkflowEvents_ModelDate
        ON dbo.tblMLWorkflowEvents (MLModelID, CreatedDate DESC, MLWorkflowEventID DESC);

    CREATE INDEX IX_tblMLWorkflowEvents_PredictionDate
        ON dbo.tblMLWorkflowEvents (MLPredictionID, CreatedDate DESC, MLWorkflowEventID DESC)
        WHERE MLPredictionID IS NOT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictionDrillRows', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblMLPredictionDrillRows
    (
        MLPredictionDrillRowID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MLPredictionID INT NOT NULL,
        MLModelID INT NOT NULL,
        EntityTypeCode NVARCHAR(60) NOT NULL,
        EntityCode NVARCHAR(120) NOT NULL,
        FiscalYearID INT NULL,
        BudgetVersionID INT NULL,
        PeriodNo INT NULL,
        Segment1 NVARCHAR(50) NULL,
        Segment2 NVARCHAR(50) NULL,
        Segment3 NVARCHAR(50) NULL,
        ProgramCode NVARCHAR(80) NULL,
        EconomicCode NVARCHAR(80) NULL,
        CurrencyCode NVARCHAR(10) NULL,
        BudgetAmount DECIMAL(28,2) NULL,
        ActualAmount DECIMAL(28,2) NULL,
        AvailableBalance DECIMAL(28,2) NULL,
        ExecutionRate DECIMAL(18,6) NULL,
        CumulativeBudgetAmount DECIMAL(28,2) NULL,
        CumulativeActualAmount DECIMAL(28,2) NULL,
        CumulativeExecutionRate DECIMAL(18,6) NULL,
        ExpectedExecutionRate DECIMAL(18,6) NULL,
        VarianceAmount DECIMAL(28,2) NULL,
        VariancePct DECIMAL(18,6) NULL,
        RiskScore DECIMAL(9,4) NULL,
        RiskLabel NVARCHAR(20) NULL,
        AnomalyTypeCode NVARCHAR(80) NULL,
        RiskReason NVARCHAR(500) NULL,
        DetailJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblMLPredictionDrillRows_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblMLPredictionDrillRows_Prediction
            FOREIGN KEY (MLPredictionID) REFERENCES dbo.tblMLPredictions(MLPredictionID),
        CONSTRAINT FK_tblMLPredictionDrillRows_Model
            FOREIGN KEY (MLModelID) REFERENCES dbo.tblMLModels(MLModelID)
    );

    CREATE INDEX IX_tblMLPredictionDrillRows_Prediction
        ON dbo.tblMLPredictionDrillRows (MLPredictionID);

    CREATE INDEX IX_tblMLPredictionDrillRows_Entity
        ON dbo.tblMLPredictionDrillRows
            (MLModelID, FiscalYearID, BudgetVersionID, PeriodNo, Segment1, ProgramCode, EconomicCode)
        INCLUDE (BudgetAmount, ActualAmount, AvailableBalance, RiskScore, RiskLabel);
END;
GO

IF OBJECT_ID(N'dbo.tblAIProviders', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIProviders
    (
        AIProviderID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProviderCode NVARCHAR(80) NOT NULL,
        ProviderName NVARCHAR(255) NOT NULL,
        ProviderTypeCode NVARCHAR(80) NOT NULL,
        BaseUrl NVARCHAR(500) NULL,
        ModelCode NVARCHAR(120) NULL,
        AllowsSensitiveData BIT NOT NULL CONSTRAINT DF_tblAIProviders_AllowsSensitiveData DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblAIProviders_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(1000) NULL
    );

    CREATE UNIQUE INDEX UX_tblAIProviders_Code
        ON dbo.tblAIProviders (ProviderCode);
END;
GO

IF OBJECT_ID(N'dbo.tblAIModuleSettings', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIModuleSettings
    (
        AIModuleSettingID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ModuleCode NVARCHAR(80) NOT NULL,
        ProviderCode NVARCHAR(80) NOT NULL,
        SensitivityRuleCode NVARCHAR(80) NOT NULL CONSTRAINT DF_tblAIModuleSettings_SensitivityRuleCode DEFAULT (N'MASK_EXTERNAL'),
        AllowExternalAI BIT NOT NULL CONSTRAINT DF_tblAIModuleSettings_AllowExternalAI DEFAULT (0),
        MaskExternalData BIT NOT NULL CONSTRAINT DF_tblAIModuleSettings_MaskExternalData DEFAULT (1),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblAIModuleSettings_ActiveFlag DEFAULT (1),
        SettingsJson NVARCHAR(MAX) NULL
    );

    CREATE UNIQUE INDEX UX_tblAIModuleSettings_Module
        ON dbo.tblAIModuleSettings (ModuleCode);
END;
GO

IF OBJECT_ID(N'dbo.tblAIPromptTemplates', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIPromptTemplates
    (
        PromptTemplateID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        TemplateCode NVARCHAR(80) NOT NULL,
        TemplateName NVARCHAR(255) NOT NULL,
        ModuleCode NVARCHAR(80) NOT NULL,
        TemplateText NVARCHAR(MAX) NOT NULL,
        VersionNo INT NOT NULL CONSTRAINT DF_tblAIPromptTemplates_VersionNo DEFAULT (1),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblAIPromptTemplates_ActiveFlag DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIPromptTemplates_CreatedDate DEFAULT (SYSUTCDATETIME())
    );

    CREATE UNIQUE INDEX UX_tblAIPromptTemplates_CodeVersion
        ON dbo.tblAIPromptTemplates (TemplateCode, VersionNo);
END;
GO

MERGE dbo.tblAIProviders AS target
USING (
    SELECT N'OPENAI' AS ProviderCode, N'OpenAI' AS ProviderName, N'OPENAI' AS ProviderTypeCode, N'https://api.openai.com/v1' AS BaseUrl, N'gpt-4.1' AS ModelCode, CAST(0 AS BIT) AS AllowsSensitiveData
    UNION ALL SELECT N'LOCAL_OLLAMA', N'Local Ollama', N'LOCAL_OLLAMA', N'http://localhost:11434', NULL, CAST(1 AS BIT)
    UNION ALL SELECT N'AZURE_OPENAI', N'Azure OpenAI', N'AZURE_OPENAI', NULL, NULL, CAST(0 AS BIT)
    UNION ALL SELECT N'DISABLED', N'Disabled', N'DISABLED', NULL, NULL, CAST(0 AS BIT)
) AS source
ON target.ProviderCode = source.ProviderCode
WHEN MATCHED THEN
    UPDATE SET ProviderName = source.ProviderName,
               ProviderTypeCode = source.ProviderTypeCode,
               BaseUrl = source.BaseUrl,
               ModelCode = source.ModelCode,
               AllowsSensitiveData = source.AllowsSensitiveData,
               ActiveFlag = 1
WHEN NOT MATCHED THEN
    INSERT (ProviderCode, ProviderName, ProviderTypeCode, BaseUrl, ModelCode, AllowsSensitiveData, ActiveFlag)
    VALUES (source.ProviderCode, source.ProviderName, source.ProviderTypeCode, source.BaseUrl, source.ModelCode, source.AllowsSensitiveData, 1);
GO

MERGE dbo.tblAIModuleSettings AS target
USING (
    SELECT N'USER_HELP' AS ModuleCode, N'OPENAI' AS ProviderCode, N'APPROVED_DOCS' AS SensitivityRuleCode, CAST(1 AS BIT) AS AllowExternalAI, CAST(0 AS BIT) AS MaskExternalData
    UNION ALL SELECT N'ANALYTICS', N'LOCAL_OLLAMA', N'MASK_EXTERNAL', CAST(0 AS BIT), CAST(1 AS BIT)
    UNION ALL SELECT N'CEILING_ASSISTANT', N'LOCAL_OLLAMA', N'SENSITIVE_BUDGET', CAST(0 AS BIT), CAST(1 AS BIT)
    UNION ALL SELECT N'REPORTING', N'OPENAI', N'AGGREGATED_ONLY', CAST(1 AS BIT), CAST(1 AS BIT)
) AS source
ON target.ModuleCode = source.ModuleCode
WHEN MATCHED THEN
    UPDATE SET ProviderCode = source.ProviderCode,
               SensitivityRuleCode = source.SensitivityRuleCode,
               AllowExternalAI = source.AllowExternalAI,
               MaskExternalData = source.MaskExternalData,
               ActiveFlag = 1
WHEN NOT MATCHED THEN
    INSERT (ModuleCode, ProviderCode, SensitivityRuleCode, AllowExternalAI, MaskExternalData, ActiveFlag)
    VALUES (source.ModuleCode, source.ProviderCode, source.SensitivityRuleCode, source.AllowExternalAI, source.MaskExternalData, 1);
GO
