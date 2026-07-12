SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblAIKnowledgeDocuments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIKnowledgeDocuments
    (
        DocumentID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        Title NVARCHAR(255) NOT NULL,
        Category NVARCHAR(80) NULL,
        Module NVARCHAR(80) NULL,
        AudienceCode NVARCHAR(40) NOT NULL CONSTRAINT DF_tblAIKnowledgeDocuments_AudienceCode DEFAULT (N'USER'),
        FiscalYearID INT NULL,
        VersionID INT NULL,
        CountryID INT NULL,
        MinistryCode NVARCHAR(80) NULL,
        FileName NVARCHAR(255) NULL,
        FileType NVARCHAR(20) NULL,
        UploadedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIKnowledgeDocuments_UploadedDate DEFAULT (SYSUTCDATETIME()),
        UploadedBy INT NULL,
        IsActive BIT NOT NULL CONSTRAINT DF_tblAIKnowledgeDocuments_IsActive DEFAULT (1),
        Notes NVARCHAR(1000) NULL
    );

    CREATE INDEX IX_tblAIKnowledgeDocuments_Context
        ON dbo.tblAIKnowledgeDocuments (IsActive, FiscalYearID, VersionID, Module, Category, AudienceCode);
END;
GO

IF OBJECT_ID(N'dbo.tblAIKnowledgeChunks', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIKnowledgeChunks
    (
        ChunkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        DocumentID INT NOT NULL,
        ChunkNumber INT NOT NULL,
        ChunkText NVARCHAR(MAX) NOT NULL,
        SourcePage NVARCHAR(40) NULL,
        Module NVARCHAR(80) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        Embedding VARBINARY(MAX) NULL,
        IsActive BIT NOT NULL CONSTRAINT DF_tblAIKnowledgeChunks_IsActive DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIKnowledgeChunks_CreatedDate DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT FK_tblAIKnowledgeChunks_Document
            FOREIGN KEY (DocumentID) REFERENCES dbo.tblAIKnowledgeDocuments(DocumentID)
    );

    CREATE UNIQUE INDEX UX_tblAIKnowledgeChunks_DocumentChunk
        ON dbo.tblAIKnowledgeChunks (DocumentID, ChunkNumber);

    CREATE INDEX IX_tblAIKnowledgeChunks_Context
        ON dbo.tblAIKnowledgeChunks (IsActive, FiscalYearID, VersionID, Module);
END;
GO

IF OBJECT_ID(N'dbo.tblAIQuestions', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblAIQuestions
    (
        QuestionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        UserID INT NULL,
        Question NVARCHAR(MAX) NOT NULL,
        Response NVARCHAR(MAX) NULL,
        DocumentsUsedJson NVARCHAR(MAX) NULL,
        ResponseTimeMs INT NULL,
        Helpful BIT NULL,
        Feedback NVARCHAR(1000) NULL,
        ProviderCode NVARCHAR(40) NULL,
        ModelCode NVARCHAR(120) NULL,
        PromptTokens INT NULL,
        CompletionTokens INT NULL,
        TotalTokens INT NULL,
        EstimatedCost DECIMAL(18,6) NULL,
        ContextJson NVARCHAR(MAX) NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblAIQuestions_CreatedDate DEFAULT (SYSUTCDATETIME())
    );

    CREATE INDEX IX_tblAIQuestions_CreatedDate
        ON dbo.tblAIQuestions (CreatedDate DESC);
END;
GO
