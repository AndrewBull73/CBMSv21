USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbSegmentPublishRequest', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbSegmentPublishRequest (
        StrategicSegmentPublishRequestID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NULL,
        RequestTitle NVARCHAR(200) NOT NULL,
        RequestNotes NVARCHAR(MAX) NULL,
        RequestStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequest_RequestStatusCode DEFAULT (N'DRAFT'),
        SubmittedBy INT NULL,
        SubmittedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        RejectedBy INT NULL,
        RejectedDate DATETIME2(0) NULL,
        PublishedBy INT NULL,
        PublishedDate DATETIME2(0) NULL,
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequest_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequest_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequest_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbSegmentPublishRequest_RequestStatusCode CHECK (
            RequestStatusCode IN (N'DRAFT', N'PENDING', N'APPROVED', N'REJECTED', N'PUBLISHED', N'PARTIAL')
        )
    );
END
GO

IF OBJECT_ID('dbo.tblSbSegmentPublishRequestLine', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbSegmentPublishRequestLine (
        StrategicSegmentPublishRequestLineID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StrategicSegmentPublishRequestID INT NOT NULL,
        StrategicDimensionCode NVARCHAR(30) NOT NULL,
        SegmentNo INT NOT NULL,
        DataObjectCode NVARCHAR(50) NOT NULL,
        SegmentCode NVARCHAR(50) NOT NULL,
        SegmentName NVARCHAR(200) NULL,
        SegmentExternalID NVARCHAR(50) NULL,
        ParentSegmentNo INT NULL,
        ParentSegmentCode NVARCHAR(50) NULL,
        SortOrder INT NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequestLine_SortOrder DEFAULT (0),
        ActiveFlag BIT NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequestLine_ActiveFlag DEFAULT (1),
        LineStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequestLine_LineStatusCode DEFAULT (N'DRAFT'),
        LineStatusNote NVARCHAR(500) NULL,
        PublishedSegmentValueID INT NULL,
        PublishedBy INT NULL,
        PublishedDate DATETIME2(0) NULL,
        CreatedBy INT NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequestLine_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL
            CONSTRAINT DF_tblSbSegmentPublishRequestLine_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbSegmentPublishRequestLine_DimensionCode CHECK (
            StrategicDimensionCode IN (N'SECTOR', N'PROGRAM', N'SUBPROGRAM', N'OBJECTIVE', N'TARGET', N'OUTPUT', N'ACTIVITY', N'PROJECT', N'ECONOMIC', N'FUNDING_TYPE', N'FUNDING_SOURCE')
        ),
        CONSTRAINT CK_tblSbSegmentPublishRequestLine_LineStatusCode CHECK (
            LineStatusCode IN (N'DRAFT', N'PENDING', N'APPROVED', N'REJECTED', N'PUBLISHED', N'FAILED')
        )
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbSegmentPublishRequestLine_Request'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentPublishRequestLine')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentPublishRequestLine
    ADD CONSTRAINT FK_tblSbSegmentPublishRequestLine_Request
        FOREIGN KEY (StrategicSegmentPublishRequestID)
        REFERENCES dbo.tblSbSegmentPublishRequest (StrategicSegmentPublishRequestID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSegmentPublishRequest')
      AND name = 'IX_tblSbSegmentPublishRequest_ContextStatus'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSegmentPublishRequest_ContextStatus
        ON dbo.tblSbSegmentPublishRequest (FiscalYearID, VersionID, RequestStatusCode, StrategicSegmentPublishRequestID DESC);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSegmentPublishRequestLine')
      AND name = 'IX_tblSbSegmentPublishRequestLine_Request'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSegmentPublishRequestLine_Request
        ON dbo.tblSbSegmentPublishRequestLine (StrategicSegmentPublishRequestID, LineStatusCode, StrategicSegmentPublishRequestLineID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSegmentPublishRequestLine')
      AND name = 'IX_tblSbSegmentPublishRequestLine_TargetSegment'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSegmentPublishRequestLine_TargetSegment
        ON dbo.tblSbSegmentPublishRequestLine (SegmentNo, DataObjectCode, SegmentCode, LineStatusCode);
END
GO
