USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbProject', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbProject does not exist. Run create_tblSbProject.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbProject', 'ExternalReference') IS NULL
    ALTER TABLE dbo.tblSbProject ADD ExternalReference NVARCHAR(100) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'ProjectTypeCode') IS NULL
    ALTER TABLE dbo.tblSbProject ADD ProjectTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProject_ProjectTypeCode DEFAULT (N'OTHER');
GO
IF COL_LENGTH('dbo.tblSbProject', 'ProjectCategoryCode') IS NULL
    ALTER TABLE dbo.tblSbProject ADD ProjectCategoryCode NVARCHAR(30) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'LifecycleStatusCode') IS NULL
    ALTER TABLE dbo.tblSbProject ADD LifecycleStatusCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProject_LifecycleStatusCode DEFAULT (N'PIPELINE');
GO
IF COL_LENGTH('dbo.tblSbProject', 'PriorityCode') IS NULL
    ALTER TABLE dbo.tblSbProject ADD PriorityCode NVARCHAR(20) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'LeadOrgUnitID') IS NULL
    ALTER TABLE dbo.tblSbProject ADD LeadOrgUnitID INT NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'SponsorOrgUnitID') IS NULL
    ALTER TABLE dbo.tblSbProject ADD SponsorOrgUnitID INT NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'ProjectManagerName') IS NULL
    ALTER TABLE dbo.tblSbProject ADD ProjectManagerName NVARCHAR(150) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'CapitalFlag') IS NULL
    ALTER TABLE dbo.tblSbProject ADD CapitalFlag BIT NOT NULL CONSTRAINT DF_tblSbProject_CapitalFlag DEFAULT (0);
GO
IF COL_LENGTH('dbo.tblSbProject', 'ProcurementRequiredFlag') IS NULL
    ALTER TABLE dbo.tblSbProject ADD ProcurementRequiredFlag BIT NOT NULL CONSTRAINT DF_tblSbProject_ProcurementRequiredFlag DEFAULT (0);
GO
IF COL_LENGTH('dbo.tblSbProject', 'StartDate') IS NULL
    ALTER TABLE dbo.tblSbProject ADD StartDate DATE NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'EndDate') IS NULL
    ALTER TABLE dbo.tblSbProject ADD EndDate DATE NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'EstimatedTotalCost') IS NULL
    ALTER TABLE dbo.tblSbProject ADD EstimatedTotalCost DECIMAL(19,6) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'ApprovedTotalCost') IS NULL
    ALTER TABLE dbo.tblSbProject ADD ApprovedTotalCost DECIMAL(19,6) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'FundingGapAmount') IS NULL
    ALTER TABLE dbo.tblSbProject ADD FundingGapAmount DECIMAL(19,6) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'CurrencyCode') IS NULL
    ALTER TABLE dbo.tblSbProject ADD CurrencyCode NVARCHAR(10) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'FundingStatusCode') IS NULL
    ALTER TABLE dbo.tblSbProject ADD FundingStatusCode NVARCHAR(30) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'RiskRatingCode') IS NULL
    ALTER TABLE dbo.tblSbProject ADD RiskRatingCode NVARCHAR(20) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'LocationCode') IS NULL
    ALTER TABLE dbo.tblSbProject ADD LocationCode NVARCHAR(50) NULL;
GO
IF COL_LENGTH('dbo.tblSbProject', 'LocationDescription') IS NULL
    ALTER TABLE dbo.tblSbProject ADD LocationDescription NVARCHAR(255) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbProject_LifecycleStatusCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProject')
)
BEGIN
    ALTER TABLE dbo.tblSbProject
    ADD CONSTRAINT CK_tblSbProject_LifecycleStatusCode CHECK (
        LifecycleStatusCode IN (N'IDEA', N'PIPELINE', N'APPRAISED', N'APPROVED', N'ACTIVE', N'ON_HOLD', N'COMPLETED', N'CANCELLED')
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbProject_ProjectTypeCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProject')
)
BEGIN
    ALTER TABLE dbo.tblSbProject
    ADD CONSTRAINT CK_tblSbProject_ProjectTypeCode CHECK (
        ProjectTypeCode IN (N'CAPITAL', N'REFORM', N'ICT', N'INFRASTRUCTURE', N'SERVICE_DELIVERY', N'DONOR', N'OTHER')
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbProject_ProjectCode_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProject')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProject_ProjectCode_Active
        ON dbo.tblSbProject (ProjectCode, ActiveFlag);
END
GO
