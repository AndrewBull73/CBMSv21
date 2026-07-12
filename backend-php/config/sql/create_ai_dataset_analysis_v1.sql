SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblAIDatasets', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIDatasets
    (
        DatasetID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        DatasetCode NVARCHAR(80) NOT NULL,
        DatasetName NVARCHAR(255) NOT NULL,
        Description NVARCHAR(2000) NULL,
        SourceObjectName NVARCHAR(256) NOT NULL,
        SourceType NVARCHAR(20) NOT NULL CONSTRAINT DF_tblAIDatasets_SourceType DEFAULT (N'VIEW'),
        SensitivityLevel NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAIDatasets_SensitivityLevel DEFAULT (N'RESTRICTED'),
        AllowedPermissionCodes NVARCHAR(500) NULL,
        DefaultFiscalYearColumn NVARCHAR(128) NULL,
        DefaultVersionColumn NVARCHAR(128) NULL,
        MaxRows INT NOT NULL CONSTRAINT DF_tblAIDatasets_MaxRows DEFAULT (100),
        RequireContext BIT NOT NULL CONSTRAINT DF_tblAIDatasets_RequireContext DEFAULT (1),
        IsActive BIT NOT NULL CONSTRAINT DF_tblAIDatasets_IsActive DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIDatasets_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        Notes NVARCHAR(1000) NULL
    );

    CREATE UNIQUE INDEX UX_tblAIDatasets_Code
        ON dbo.tblAIDatasets (DatasetCode);

    CREATE INDEX IX_tblAIDatasets_Active
        ON dbo.tblAIDatasets (IsActive, SensitivityLevel);
END;
GO

IF OBJECT_ID(N'dbo.tblAIDatasetColumns', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIDatasetColumns
    (
        DatasetColumnID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        DatasetID INT NOT NULL,
        ColumnName NVARCHAR(128) NOT NULL,
        DisplayName NVARCHAR(255) NULL,
        DataType NVARCHAR(80) NULL,
        SemanticType NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAIDatasetColumns_SemanticType DEFAULT (N'DIMENSION'),
        IsDimension BIT NOT NULL CONSTRAINT DF_tblAIDatasetColumns_IsDimension DEFAULT (1),
        IsMetric BIT NOT NULL CONSTRAINT DF_tblAIDatasetColumns_IsMetric DEFAULT (0),
        IsFilterable BIT NOT NULL CONSTRAINT DF_tblAIDatasetColumns_IsFilterable DEFAULT (1),
        IsSensitive BIT NOT NULL CONSTRAINT DF_tblAIDatasetColumns_IsSensitive DEFAULT (0),
        DefaultAggregation NVARCHAR(20) NULL,
        Description NVARCHAR(1000) NULL,
        DisplayOrder INT NOT NULL CONSTRAINT DF_tblAIDatasetColumns_DisplayOrder DEFAULT (0),
        IsActive BIT NOT NULL CONSTRAINT DF_tblAIDatasetColumns_IsActive DEFAULT (1),
        CONSTRAINT FK_tblAIDatasetColumns_Dataset
            FOREIGN KEY (DatasetID) REFERENCES dbo.tblAIDatasets(DatasetID)
    );

    CREATE UNIQUE INDEX UX_tblAIDatasetColumns_DatasetColumn
        ON dbo.tblAIDatasetColumns (DatasetID, ColumnName);
END;
GO

IF OBJECT_ID(N'dbo.tblAIDatasetQueries', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIDatasetQueries
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
        StatusCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAIDatasetQueries_StatusCode DEFAULT (N'SUCCESS'),
        ErrorMessage NVARCHAR(2000) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIDatasetQueries_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblAIDatasetQueries_Dataset
            FOREIGN KEY (DatasetID) REFERENCES dbo.tblAIDatasets(DatasetID)
    );

    CREATE INDEX IX_tblAIDatasetQueries_CreatedDate
        ON dbo.tblAIDatasetQueries (CreatedDate DESC);
END;
GO
