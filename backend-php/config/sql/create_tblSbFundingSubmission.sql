USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbFundingSubmission', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFundingSubmission (
        StrategicFundingSubmissionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        DataObjectCode NVARCHAR(50) NOT NULL,
        OrgUnitID INT NULL,
        RequestTitle NVARCHAR(200) NOT NULL,
        RequestNotes NVARCHAR(MAX) NULL,
        SubmissionTypeCode NVARCHAR(30) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmission_SubmissionTypeCode DEFAULT (N'NEW_SPENDING'),
        PriorityCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmission_PriorityCode DEFAULT (N'MEDIUM'),
        SubmissionStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmission_SubmissionStatusCode DEFAULT (N'DRAFT'),
        TotalRequestedAmount DECIMAL(19,6) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmission_TotalRequestedAmount DEFAULT ((0)),
        TotalApprovedAmount DECIMAL(19,6) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmission_TotalApprovedAmount DEFAULT ((0)),
        SubmittedBy INT NULL,
        SubmittedDate DATETIME2(0) NULL,
        ReviewedBy INT NULL,
        ReviewedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        DecisionNote NVARCHAR(1000) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblSbFundingSubmission_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblSbFundingSubmission_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmission_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbFundingSubmission_SubmissionTypeCode CHECK (
            SubmissionTypeCode IN (N'NEW_SPENDING', N'EXPANSION', N'REALLOCATION', N'SAVINGS', N'CAPITAL', N'DONOR')
        ),
        CONSTRAINT CK_tblSbFundingSubmission_PriorityCode CHECK (
            PriorityCode IN (N'LOW', N'MEDIUM', N'HIGH', N'CRITICAL')
        ),
        CONSTRAINT CK_tblSbFundingSubmission_SubmissionStatusCode CHECK (
            SubmissionStatusCode IN (N'DRAFT', N'LODGED', N'PENDING', N'REVIEWED', N'APPROVED', N'PARTIAL', N'REJECTED', N'FUNDED')
        )
    );
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbFundingSubmission_SubmissionStatusCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSubmission')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmission
    DROP CONSTRAINT CK_tblSbFundingSubmission_SubmissionStatusCode;
END
GO

ALTER TABLE dbo.tblSbFundingSubmission
ADD CONSTRAINT CK_tblSbFundingSubmission_SubmissionStatusCode CHECK (
    SubmissionStatusCode IN (N'DRAFT', N'LODGED', N'PENDING', N'REVIEWED', N'APPROVED', N'PARTIAL', N'REJECTED', N'FUNDED')
);
GO

IF COL_LENGTH('dbo.tblSbFundingSubmission', 'ReviewerRanking') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmission
    ADD ReviewerRanking INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSubmission', 'AssessmentGrade') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmission
    ADD AssessmentGrade NVARCHAR(20) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSubmission', 'ReviewerRecommendation') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmission
    ADD ReviewerRecommendation NVARCHAR(40) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSubmission', 'ReviewerSummary') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmission
    ADD ReviewerSummary NVARCHAR(MAX) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSubmission', 'ReviewerConditions') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmission
    ADD ReviewerConditions NVARCHAR(1000) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSubmission', 'ReviewerNextSteps') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmission
    ADD ReviewerNextSteps NVARCHAR(1000) NULL;
END
GO

IF OBJECT_ID('dbo.tblSbFundingSubmissionLine', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFundingSubmissionLine (
        StrategicFundingSubmissionLineID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StrategicFundingSubmissionID INT NOT NULL,
        SectorID INT NULL,
        ProgramID INT NULL,
        SubProgramID INT NULL,
        ProjectID INT NULL,
        ActivityID INT NULL,
        OrgUnitID INT NULL,
        FundingTypeID INT NULL,
        FundingSourceID INT NULL,
        EconomicItemID INT NULL,
        BidTitle NVARCHAR(200) NOT NULL,
        BusinessCaseSummary NVARCHAR(MAX) NULL,
        ExpectedOutput NVARCHAR(500) NULL,
        ExpectedOutcome NVARCHAR(500) NULL,
        CurrentYearRequestedAmount DECIMAL(19,6) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionLine_CurrentYearRequestedAmount DEFAULT ((0)),
        OuterYear1RequestedAmount DECIMAL(19,6) NULL,
        OuterYear2RequestedAmount DECIMAL(19,6) NULL,
        OuterYear3RequestedAmount DECIMAL(19,6) NULL,
        OuterYear4RequestedAmount DECIMAL(19,6) NULL,
        OuterYear5RequestedAmount DECIMAL(19,6) NULL,
        CurrentYearApprovedAmount DECIMAL(19,6) NULL,
        OuterYear1ApprovedAmount DECIMAL(19,6) NULL,
        OuterYear2ApprovedAmount DECIMAL(19,6) NULL,
        OuterYear3ApprovedAmount DECIMAL(19,6) NULL,
        OuterYear4ApprovedAmount DECIMAL(19,6) NULL,
        OuterYear5ApprovedAmount DECIMAL(19,6) NULL,
        PriorityRank INT NULL,
        ScoreStrategicAlignment DECIMAL(9,2) NULL,
        ScoreReadiness DECIMAL(9,2) NULL,
        ScoreFiscalAffordability DECIMAL(9,2) NULL,
        ScoreServiceImpact DECIMAL(9,2) NULL,
        LineStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionLine_LineStatusCode DEFAULT (N'DRAFT'),
        DecisionNote NVARCHAR(1000) NULL,
        PublishedCeilingID INT NULL,
        PublishedPlanReference NVARCHAR(100) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionLine_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionLine_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionLine_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbFundingSubmissionLine_LineStatusCode CHECK (
            LineStatusCode IN (N'DRAFT', N'PENDING', N'APPROVED', N'PARTIAL', N'REJECTED', N'FUNDED')
        )
    );
END
GO

IF COL_LENGTH('dbo.tblSbFundingSubmissionLine', 'ReviewerResponse') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmissionLine
    ADD ReviewerResponse NVARCHAR(MAX) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSubmissionLine', 'ReviewerConditions') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmissionLine
    ADD ReviewerConditions NVARCHAR(1000) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSubmissionLine', 'ReviewerNextSteps') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmissionLine
    ADD ReviewerNextSteps NVARCHAR(1000) NULL;
END
GO

IF OBJECT_ID('dbo.tblSbFundingSubmissionHistory', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFundingSubmissionHistory (
        StrategicFundingSubmissionHistoryID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StrategicFundingSubmissionID INT NOT NULL,
        StrategicFundingSubmissionLineID INT NULL,
        WorkflowActionCode NVARCHAR(30) NOT NULL,
        FromStatusCode NVARCHAR(20) NULL,
        ToStatusCode NVARCHAR(20) NOT NULL,
        ActionNote NVARCHAR(1000) NULL,
        ActionBy INT NOT NULL,
        ActionDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionHistory_ActionDate DEFAULT (SYSDATETIME())
    );
END
GO

IF OBJECT_ID('dbo.tblSbFundingSubmissionAttachment', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFundingSubmissionAttachment (
        StrategicFundingSubmissionAttachmentID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StrategicFundingSubmissionID INT NOT NULL,
        OriginalFileName NVARCHAR(255) NOT NULL,
        StoredFileName NVARCHAR(255) NOT NULL,
        MimeType NVARCHAR(100) NULL,
        FileSizeBytes BIGINT NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionAttachment_FileSizeBytes DEFAULT ((0)),
        StoragePath NVARCHAR(500) NOT NULL,
        AttachmentNotes NVARCHAR(500) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionAttachment_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionAttachment_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblSbFundingSubmissionAttachment_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFundingSubmissionLine_Submission'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSubmissionLine')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmissionLine
    ADD CONSTRAINT FK_tblSbFundingSubmissionLine_Submission
        FOREIGN KEY (StrategicFundingSubmissionID)
        REFERENCES dbo.tblSbFundingSubmission (StrategicFundingSubmissionID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFundingSubmissionHistory_Submission'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSubmissionHistory')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmissionHistory
    ADD CONSTRAINT FK_tblSbFundingSubmissionHistory_Submission
        FOREIGN KEY (StrategicFundingSubmissionID)
        REFERENCES dbo.tblSbFundingSubmission (StrategicFundingSubmissionID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFundingSubmissionHistory_Line'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSubmissionHistory')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmissionHistory
    ADD CONSTRAINT FK_tblSbFundingSubmissionHistory_Line
        FOREIGN KEY (StrategicFundingSubmissionLineID)
        REFERENCES dbo.tblSbFundingSubmissionLine (StrategicFundingSubmissionLineID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFundingSubmissionAttachment_Submission'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSubmissionAttachment')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSubmissionAttachment
    ADD CONSTRAINT FK_tblSbFundingSubmissionAttachment_Submission
        FOREIGN KEY (StrategicFundingSubmissionID)
        REFERENCES dbo.tblSbFundingSubmission (StrategicFundingSubmissionID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSubmission')
      AND name = 'IX_tblSbFundingSubmission_ContextStatus'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSubmission_ContextStatus
        ON dbo.tblSbFundingSubmission (FiscalYearID, VersionID, SubmissionStatusCode, StrategicFundingSubmissionID DESC);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSubmissionAttachment')
      AND name = 'IX_tblSbFundingSubmissionAttachment_Submission'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSubmissionAttachment_Submission
        ON dbo.tblSbFundingSubmissionAttachment (StrategicFundingSubmissionID, ActiveFlag, StrategicFundingSubmissionAttachmentID DESC);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSubmissionLine')
      AND name = 'IX_tblSbFundingSubmissionLine_Submission'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSubmissionLine_Submission
        ON dbo.tblSbFundingSubmissionLine (StrategicFundingSubmissionID, LineStatusCode, StrategicFundingSubmissionLineID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSubmissionHistory')
      AND name = 'IX_tblSbFundingSubmissionHistory_Submission'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSubmissionHistory_Submission
        ON dbo.tblSbFundingSubmissionHistory (StrategicFundingSubmissionID, ActionDate DESC, StrategicFundingSubmissionHistoryID DESC);
END
GO
