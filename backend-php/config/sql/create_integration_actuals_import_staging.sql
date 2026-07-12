/*
    Create staging storage for FMIS actuals imported through integration runs.

    Rows in this table are landed import records. They are intentionally separate
    from the core actuals/ledger tables so imported data can be reviewed,
    reconciled, and posted by a controlled process later.
*/


GO

IF OBJECT_ID(N'dbo.tblIntegrationRun', N'U') IS NULL
BEGIN
    RAISERROR('Integration run table was not found. Run create_api_integration_foundation_v1.sql before create_integration_actuals_import_staging.sql.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.tblIntegrationActualsImportStaging', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationActualsImportStaging
    (
        IntegrationActualsImportStagingID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        IntegrationRunID INT NOT NULL,
        TransactionReference NVARCHAR(120) NOT NULL,
        ExternalCorrelationID NVARCHAR(120) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        PeriodNo INT NULL,
        PostingDate DATE NULL,
        DataObjectCode NVARCHAR(80) NULL,
        ProgramCode NVARCHAR(80) NULL,
        EconomicCode NVARCHAR(80) NULL,
        SupplierName NVARCHAR(200) NULL,
        ActualAmount DECIMAL(18,2) NULL,
        CurrencyCode NVARCHAR(10) NULL,
        StagingStatusCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblIntegrationActualsImportStaging_Status DEFAULT (N'staged'),
        ValidationMessage NVARCHAR(1000) NULL,
        PostingReadinessCode NVARCHAR(40) NULL,
        PostingReadinessMessage NVARCHAR(1000) NULL,
        PostingTarget NVARCHAR(200) NULL,
        SourcePayloadJson NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationActualsImportStaging_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationActualsImportStaging_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    ALTER TABLE dbo.tblIntegrationActualsImportStaging
    ADD CONSTRAINT FK_tblIntegrationActualsImportStaging_Run
        FOREIGN KEY (IntegrationRunID)
        REFERENCES dbo.tblIntegrationRun (IntegrationRunID);

    CREATE NONCLUSTERED INDEX IX_tblIntegrationActualsImportStaging_Run
        ON dbo.tblIntegrationActualsImportStaging (IntegrationRunID, ActiveFlag, IntegrationActualsImportStagingID DESC);

    CREATE NONCLUSTERED INDEX IX_tblIntegrationActualsImportStaging_Context
        ON dbo.tblIntegrationActualsImportStaging (FiscalYearID, VersionID, PeriodNo, DataObjectCode, StagingStatusCode);

    CREATE UNIQUE NONCLUSTERED INDEX UX_tblIntegrationActualsImportStaging_RunReference
        ON dbo.tblIntegrationActualsImportStaging (IntegrationRunID, TransactionReference);
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationActualsImportStaging', N'PostingReadinessCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationActualsImportStaging
    ADD PostingReadinessCode NVARCHAR(40) NULL;
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationActualsImportStaging', N'PostingReadinessMessage') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationActualsImportStaging
    ADD PostingReadinessMessage NVARCHAR(1000) NULL;
END;
GO

IF COL_LENGTH(N'dbo.tblIntegrationActualsImportStaging', N'PostingTarget') IS NULL
BEGIN
    ALTER TABLE dbo.tblIntegrationActualsImportStaging
    ADD PostingTarget NVARCHAR(200) NULL;
END;
GO
