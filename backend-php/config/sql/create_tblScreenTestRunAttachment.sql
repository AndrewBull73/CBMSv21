/*
    Create storage for screenshot evidence captured from the screen test runner.

    This table stores files linked to a saved screen test run.
*/

USE [CBMSv2];
GO

IF OBJECT_ID(N'dbo.tblScreenTestRunAttachment', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblScreenTestRunAttachment
    (
        ScreenTestRunAttachmentID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScreenTestRunID INT NOT NULL,
        OriginalFileName NVARCHAR(255) NOT NULL,
        StoredFileName NVARCHAR(255) NOT NULL,
        MimeType NVARCHAR(120) NULL,
        FileSizeBytes BIGINT NOT NULL CONSTRAINT DF_tblScreenTestRunAttachment_FileSizeBytes DEFAULT ((0)),
        StoragePath NVARCHAR(500) NOT NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblScreenTestRunAttachment_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblScreenTestRunAttachment_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    ALTER TABLE dbo.tblScreenTestRunAttachment
    ADD CONSTRAINT FK_tblScreenTestRunAttachment_Run
        FOREIGN KEY (ScreenTestRunID)
        REFERENCES dbo.tblScreenTestRuns (ScreenTestRunID);

    CREATE NONCLUSTERED INDEX IX_tblScreenTestRunAttachment_Run
        ON dbo.tblScreenTestRunAttachment (ScreenTestRunID, ActiveFlag, ScreenTestRunAttachmentID DESC);
END;
GO
can you check 