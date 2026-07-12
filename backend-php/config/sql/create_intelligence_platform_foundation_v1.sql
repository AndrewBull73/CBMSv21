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
        TrainingPeriodStart DATE NULL,
        TrainingPeriodEnd DATE NULL,
        MetricsJson NVARCHAR(MAX) NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblMLTrainingRuns_StatusCode DEFAULT (N'PENDING'),
        ErrorMessage NVARCHAR(2000) NULL,
        StartedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblMLTrainingRuns_StartedDate DEFAULT (SYSUTCDATETIME()),
        CompletedDate DATETIME2(0) NULL,
        CONSTRAINT FK_tblMLTrainingRuns_Model
            FOREIGN KEY (MLModelID) REFERENCES dbo.tblMLModels(MLModelID)
    );
END;
GO

IF OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblMLPredictions
    (
        MLPredictionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        MLModelID INT NOT NULL,
        EntityTypeCode NVARCHAR(60) NOT NULL,
        EntityCode NVARCHAR(120) NOT NULL,
        PredictionValue DECIMAL(28,8) NULL,
        RiskScore DECIMAL(9,4) NULL,
        ConfidenceScore DECIMAL(9,4) NULL,
        PredictionJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblMLPredictions_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblMLPredictions_Model
            FOREIGN KEY (MLModelID) REFERENCES dbo.tblMLModels(MLModelID)
    );
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
