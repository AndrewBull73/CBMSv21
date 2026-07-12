/*
    Create mapping and crosswalk storage for external integration data.

    Mapping profiles describe source-to-target field rules at interface level.
    Code crosswalks translate external system codes into CBMS codes for review,
    validation, and posting preparation.
*/

USE [CBMSv2];
GO

IF OBJECT_ID(N'dbo.tblIntegrationSystem', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblIntegrationInterface', N'U') IS NULL
BEGIN
    RAISERROR('Integration foundation tables were not found. Run create_api_integration_foundation_v1.sql before create_integration_mapping_crosswalks.sql.', 16, 1);
    RETURN;
END;
GO

IF OBJECT_ID(N'dbo.tblIntegrationMappingProfile', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationMappingProfile
    (
        IntegrationMappingProfileID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        IntegrationInterfaceID INT NOT NULL,
        ProfileCode NVARCHAR(80) NOT NULL,
        ProfileName NVARCHAR(180) NOT NULL,
        DirectionCode NVARCHAR(20) NULL,
        MappingConfigJson NVARCHAR(MAX) NULL,
        ValidationConfigJson NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationMappingProfile_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationMappingProfile_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    ALTER TABLE dbo.tblIntegrationMappingProfile
    ADD CONSTRAINT FK_tblIntegrationMappingProfile_Interface
        FOREIGN KEY (IntegrationInterfaceID)
        REFERENCES dbo.tblIntegrationInterface (IntegrationInterfaceID);

    CREATE UNIQUE NONCLUSTERED INDEX UX_tblIntegrationMappingProfile_InterfaceCode
        ON dbo.tblIntegrationMappingProfile (IntegrationInterfaceID, ProfileCode);
END;
GO

IF OBJECT_ID(N'dbo.tblIntegrationCodeCrosswalk', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblIntegrationCodeCrosswalk
    (
        IntegrationCodeCrosswalkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        IntegrationSystemID INT NOT NULL,
        IntegrationInterfaceID INT NULL,
        MappingTypeCode NVARCHAR(50) NOT NULL,
        ExternalCode NVARCHAR(120) NOT NULL,
        ExternalDescription NVARCHAR(255) NULL,
        CbmsCode NVARCHAR(120) NOT NULL,
        CbmsDescription NVARCHAR(255) NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        EffectiveFrom DATE NULL,
        EffectiveTo DATE NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblIntegrationCodeCrosswalk_ActiveFlag DEFAULT (1),
        Notes NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblIntegrationCodeCrosswalk_CreatedDate DEFAULT (SYSUTCDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );

    ALTER TABLE dbo.tblIntegrationCodeCrosswalk
    ADD CONSTRAINT FK_tblIntegrationCodeCrosswalk_System
        FOREIGN KEY (IntegrationSystemID)
        REFERENCES dbo.tblIntegrationSystem (IntegrationSystemID);

    ALTER TABLE dbo.tblIntegrationCodeCrosswalk
    ADD CONSTRAINT FK_tblIntegrationCodeCrosswalk_Interface
        FOREIGN KEY (IntegrationInterfaceID)
        REFERENCES dbo.tblIntegrationInterface (IntegrationInterfaceID);

    CREATE NONCLUSTERED INDEX IX_tblIntegrationCodeCrosswalk_Lookup
        ON dbo.tblIntegrationCodeCrosswalk (IntegrationSystemID, MappingTypeCode, ExternalCode, FiscalYearID, VersionID, ActiveFlag);

    CREATE NONCLUSTERED INDEX IX_tblIntegrationCodeCrosswalk_InterfaceLookup
        ON dbo.tblIntegrationCodeCrosswalk (IntegrationInterfaceID, MappingTypeCode, ExternalCode, FiscalYearID, VersionID, ActiveFlag);
END;
GO
