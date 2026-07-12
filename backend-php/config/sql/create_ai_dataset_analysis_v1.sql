SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasets', N'U') IS NULL
   AND OBJECT_ID(N'dbo.tblAIDatasets', N'U') IS NOT NULL
BEGIN
    EXEC sys.sp_rename N'dbo.tblAIDatasets', N'tblAnalysisDatasets', N'OBJECT';
END;
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasetColumns', N'U') IS NULL
   AND OBJECT_ID(N'dbo.tblAIDatasetColumns', N'U') IS NOT NULL
BEGIN
    EXEC sys.sp_rename N'dbo.tblAIDatasetColumns', N'tblAnalysisDatasetColumns', N'OBJECT';
END;
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasetQueries', N'U') IS NULL
   AND OBJECT_ID(N'dbo.tblAIDatasetQueries', N'U') IS NOT NULL
BEGIN
    EXEC sys.sp_rename N'dbo.tblAIDatasetQueries', N'tblAnalysisDatasetQueries', N'OBJECT';
END;
GO

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblAnalysisDatasets') AND name = N'UX_tblAIDatasets_Code')
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblAnalysisDatasets') AND name = N'UX_tblAnalysisDatasets_Code')
BEGIN
    EXEC sys.sp_rename N'dbo.tblAnalysisDatasets.UX_tblAIDatasets_Code', N'UX_tblAnalysisDatasets_Code', N'INDEX';
END;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblAnalysisDatasets') AND name = N'IX_tblAIDatasets_Active')
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblAnalysisDatasets') AND name = N'IX_tblAnalysisDatasets_Active')
BEGIN
    EXEC sys.sp_rename N'dbo.tblAnalysisDatasets.IX_tblAIDatasets_Active', N'IX_tblAnalysisDatasets_Active', N'INDEX';
END;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblAnalysisDatasetColumns') AND name = N'UX_tblAIDatasetColumns_DatasetColumn')
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblAnalysisDatasetColumns') AND name = N'UX_tblAnalysisDatasetColumns_DatasetColumn')
BEGIN
    EXEC sys.sp_rename N'dbo.tblAnalysisDatasetColumns.UX_tblAIDatasetColumns_DatasetColumn', N'UX_tblAnalysisDatasetColumns_DatasetColumn', N'INDEX';
END;

IF EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblAnalysisDatasetQueries') AND name = N'IX_tblAIDatasetQueries_CreatedDate')
   AND NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID(N'dbo.tblAnalysisDatasetQueries') AND name = N'IX_tblAnalysisDatasetQueries_CreatedDate')
BEGIN
    EXEC sys.sp_rename N'dbo.tblAnalysisDatasetQueries.IX_tblAIDatasetQueries_CreatedDate', N'IX_tblAnalysisDatasetQueries_CreatedDate', N'INDEX';
END;
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasets', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAnalysisDatasets
    (
        DatasetID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        DatasetCode NVARCHAR(80) NOT NULL,
        DatasetName NVARCHAR(255) NOT NULL,
        Description NVARCHAR(2000) NULL,
        SourceObjectName NVARCHAR(256) NOT NULL,
        SourceType NVARCHAR(20) NOT NULL CONSTRAINT DF_tblAnalysisDatasets_SourceType DEFAULT (N'VIEW'),
        SensitivityLevel NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAnalysisDatasets_SensitivityLevel DEFAULT (N'RESTRICTED'),
        AllowedPermissionCodes NVARCHAR(500) NULL,
        DefaultFiscalYearColumn NVARCHAR(128) NULL,
        DefaultVersionColumn NVARCHAR(128) NULL,
        MaxRows INT NOT NULL CONSTRAINT DF_tblAnalysisDatasets_MaxRows DEFAULT (100),
        RequireContext BIT NOT NULL CONSTRAINT DF_tblAnalysisDatasets_RequireContext DEFAULT (1),
        IsActive BIT NOT NULL CONSTRAINT DF_tblAnalysisDatasets_IsActive DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAnalysisDatasets_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        Notes NVARCHAR(1000) NULL
    );

    CREATE UNIQUE INDEX UX_tblAnalysisDatasets_Code
        ON dbo.tblAnalysisDatasets (DatasetCode);

    CREATE INDEX IX_tblAnalysisDatasets_Active
        ON dbo.tblAnalysisDatasets (IsActive, SensitivityLevel);
END;
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasetColumns', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAnalysisDatasetColumns
    (
        DatasetColumnID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        DatasetID INT NOT NULL,
        ColumnName NVARCHAR(128) NOT NULL,
        DisplayName NVARCHAR(255) NULL,
        DataType NVARCHAR(80) NULL,
        SemanticType NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAnalysisDatasetColumns_SemanticType DEFAULT (N'DIMENSION'),
        IsDimension BIT NOT NULL CONSTRAINT DF_tblAnalysisDatasetColumns_IsDimension DEFAULT (1),
        IsMetric BIT NOT NULL CONSTRAINT DF_tblAnalysisDatasetColumns_IsMetric DEFAULT (0),
        IsFilterable BIT NOT NULL CONSTRAINT DF_tblAnalysisDatasetColumns_IsFilterable DEFAULT (1),
        IsSensitive BIT NOT NULL CONSTRAINT DF_tblAnalysisDatasetColumns_IsSensitive DEFAULT (0),
        DefaultAggregation NVARCHAR(20) NULL,
        Description NVARCHAR(1000) NULL,
        DisplayOrder INT NOT NULL CONSTRAINT DF_tblAnalysisDatasetColumns_DisplayOrder DEFAULT (0),
        IsActive BIT NOT NULL CONSTRAINT DF_tblAnalysisDatasetColumns_IsActive DEFAULT (1),
        CONSTRAINT FK_tblAnalysisDatasetColumns_Dataset
            FOREIGN KEY (DatasetID) REFERENCES dbo.tblAnalysisDatasets(DatasetID)
    );

    CREATE UNIQUE INDEX UX_tblAnalysisDatasetColumns_DatasetColumn
        ON dbo.tblAnalysisDatasetColumns (DatasetID, ColumnName);
END;
GO

IF OBJECT_ID(N'dbo.tblAnalysisDatasetQueries', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAnalysisDatasetQueries
    (
        DatasetQueryID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        DatasetID INT NULL,
        UserID INT NULL,
        Question NVARCHAR(MAX) NOT NULL,
        AnalysisPlanJson NVARCHAR(MAX) NULL,
        ExecutedSql NVARCHAR(MAX) NULL,
        ParametersJson NVARCHAR(MAX) NULL,
        ResponseSummary NVARCHAR(MAX) NULL,
        [RowCount] INT NULL,
        ResponseTimeMs INT NULL,
        ProviderCode NVARCHAR(40) NULL,
        ModelCode NVARCHAR(120) NULL,
        PromptTokens INT NULL,
        CompletionTokens INT NULL,
        TotalTokens INT NULL,
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAnalysisDatasetQueries_StatusCode DEFAULT (N'SUCCESS'),
        ErrorMessage NVARCHAR(2000) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAnalysisDatasetQueries_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblAnalysisDatasetQueries_Dataset
            FOREIGN KEY (DatasetID) REFERENCES dbo.tblAnalysisDatasets(DatasetID)
    );

    CREATE INDEX IX_tblAnalysisDatasetQueries_CreatedDate
        ON dbo.tblAnalysisDatasetQueries (CreatedDate DESC);
END;
GO
