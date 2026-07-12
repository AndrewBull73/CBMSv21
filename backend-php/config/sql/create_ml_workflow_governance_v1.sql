/*
    Add lightweight governance workflow history for ML models.

    This records model lifecycle actions such as submit for review, approve,
    request changes, mark results reviewed, and retire. The ML model status
    remains on dbo.tblMLModels; this table gives the audit trail.
*/

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblMLModels', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblMLPredictions', N'U') IS NULL
BEGIN
    RAISERROR('ML model and prediction tables were not found. Run create_intelligence_platform_foundation_v1.sql first.', 16, 1);
    RETURN;
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
END;
GO

IF COL_LENGTH(N'dbo.tblMLWorkflowEvents', N'MLPredictionID') IS NULL
BEGIN
    ALTER TABLE dbo.tblMLWorkflowEvents
        ADD MLPredictionID INT NULL;
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID(N'dbo.tblMLWorkflowEvents')
      AND name = N'FK_tblMLWorkflowEvents_Prediction'
)
BEGIN
    ALTER TABLE dbo.tblMLWorkflowEvents
        ADD CONSTRAINT FK_tblMLWorkflowEvents_Prediction
            FOREIGN KEY (MLPredictionID) REFERENCES dbo.tblMLPredictions(MLPredictionID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblMLWorkflowEvents')
      AND name = N'IX_tblMLWorkflowEvents_ModelDate'
)
BEGIN
    CREATE INDEX IX_tblMLWorkflowEvents_ModelDate
        ON dbo.tblMLWorkflowEvents (MLModelID, CreatedDate DESC, MLWorkflowEventID DESC);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblMLWorkflowEvents')
      AND name = N'IX_tblMLWorkflowEvents_PredictionDate'
)
BEGIN
    CREATE INDEX IX_tblMLWorkflowEvents_PredictionDate
        ON dbo.tblMLWorkflowEvents (MLPredictionID, CreatedDate DESC, MLWorkflowEventID DESC)
        WHERE MLPredictionID IS NOT NULL;
END;
GO

SELECT
    MLWorkflowEventsInstalled = CASE WHEN OBJECT_ID(N'dbo.tblMLWorkflowEvents', N'U') IS NULL THEN 0 ELSE 1 END;
GO
