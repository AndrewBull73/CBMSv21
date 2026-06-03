USE [CBMSv2];
GO

/*
Strategic Budgeting Module v2
-----------------------------
CBMSv21-compatible schema for program-based strategic budgeting.

Design goals:
- Reuse existing dbo.tblFiscalYears and dbo.tblVersions.
- Avoid naming collisions with existing CBMS tables by using tblSb* names.
- Keep the script idempotent.
- Enforce critical integrity rules:
  * Version/FiscalYear consistency via composite foreign keys.
  * Program/SubProgram consistency via composite foreign keys.
  * Explicit ceiling scope to avoid ambiguous ceiling rows.
*/

IF OBJECT_ID('dbo.tblFiscalYears', 'U') IS NULL
BEGIN
    THROW 50000, 'Strategic Budgeting Module v2 requires dbo.tblFiscalYears to exist first.', 1;
END
GO

IF OBJECT_ID('dbo.tblVersions', 'U') IS NULL
BEGIN
    THROW 50001, 'Strategic Budgeting Module v2 requires dbo.tblVersions to exist first.', 1;
END
GO

IF COL_LENGTH('dbo.tblVersions', 'FiscalYearID') IS NULL
BEGIN
    THROW 50002, 'Strategic Budgeting Module v2 requires dbo.tblVersions.FiscalYearID to exist.', 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblVersions')
      AND name = 'UX_tblVersions_VersionID_FiscalYearID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblVersions_VersionID_FiscalYearID
        ON dbo.tblVersions (VersionID, FiscalYearID);
END
GO

IF OBJECT_ID('dbo.tblSbOrgUnit', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbOrgUnit (
        OrgUnitID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ParentOrgUnitID INT NULL,
        OrgUnitTypeCode NVARCHAR(20) NOT NULL,
        VoteCode NVARCHAR(20) NULL,
        OrgUnitName NVARCHAR(200) NOT NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbOrgUnit_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbOrgUnit_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbOrgUnit_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbOrgUnit_TypeCode CHECK (OrgUnitTypeCode IN (N'VOTE', N'MDA', N'AGENCY', N'DEPT'))
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbOrgUnit_ParentOrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbOrgUnit')
)
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD CONSTRAINT FK_tblSbOrgUnit_ParentOrgUnit
        FOREIGN KEY (ParentOrgUnitID)
        REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOrgUnit')
      AND name = 'IX_tblSbOrgUnit_ParentOrgUnitID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbOrgUnit_ParentOrgUnitID
        ON dbo.tblSbOrgUnit (ParentOrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOrgUnit')
      AND name = 'IX_tblSbOrgUnit_TypeCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbOrgUnit_TypeCode
        ON dbo.tblSbOrgUnit (OrgUnitTypeCode, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOrgUnit')
      AND name = 'UX_tblSbOrgUnit_VoteCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbOrgUnit_VoteCode
        ON dbo.tblSbOrgUnit (VoteCode)
        WHERE VoteCode IS NOT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOrgUnit')
      AND name = 'UX_tblSbOrgUnit_SourceFiscalYearID_SourceDataObjectCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbOrgUnit_SourceFiscalYearID_SourceDataObjectCode
        ON dbo.tblSbOrgUnit (SourceFiscalYearID, SourceDataObjectCode)
        WHERE SourceFiscalYearID IS NOT NULL
          AND SourceDataObjectCode IS NOT NULL;
END
GO

IF OBJECT_ID('dbo.tblSbSector', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbSector (
        SectorID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        SectorName NVARCHAR(150) NOT NULL,
        SectorDescription NVARCHAR(MAX) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbSector_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbSector_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbSector_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSector')
      AND name = 'UX_tblSbSector_SectorName'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbSector_SectorName
        ON dbo.tblSbSector (SectorName);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSector')
      AND name = 'IX_tblSbSector_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSector_SourceLookup
        ON dbo.tblSbSector (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbProgram', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProgram (
        ProgramID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        OrgUnitID INT NOT NULL,
        SectorID INT NOT NULL,
        ProgramCode NVARCHAR(50) NULL,
        ProgramName NVARCHAR(200) NOT NULL,
        ProgramDescription NVARCHAR(MAX) NULL,
        ProgramManagerName NVARCHAR(150) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProgram_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProgram_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProgram_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProgram_OrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProgram')
)
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD CONSTRAINT FK_tblSbProgram_OrgUnit
        FOREIGN KEY (OrgUnitID)
        REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProgram_Sector'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProgram')
)
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD CONSTRAINT FK_tblSbProgram_Sector
        FOREIGN KEY (SectorID)
        REFERENCES dbo.tblSbSector (SectorID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgram')
      AND name = 'IX_tblSbProgram_OrgUnitID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProgram_OrgUnitID
        ON dbo.tblSbProgram (OrgUnitID, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgram')
      AND name = 'IX_tblSbProgram_SectorID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProgram_SectorID
        ON dbo.tblSbProgram (SectorID, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgram')
      AND name = 'UX_tblSbProgram_OrgUnitID_ProgramCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProgram_OrgUnitID_ProgramCode
        ON dbo.tblSbProgram (OrgUnitID, ProgramCode)
        WHERE ProgramCode IS NOT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgram')
      AND name = 'IX_tblSbProgram_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProgram_SourceLookup
        ON dbo.tblSbProgram (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbSubProgram', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbSubProgram (
        SubProgramID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProgramID INT NOT NULL,
        SubProgramCode NVARCHAR(50) NULL,
        SubProgramName NVARCHAR(200) NOT NULL,
        SubProgramDescription NVARCHAR(MAX) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbSubProgram_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbSubProgram_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbSubProgram_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbSubProgram_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSubProgram')
)
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD CONSTRAINT FK_tblSbSubProgram_Program
        FOREIGN KEY (ProgramID)
        REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSubProgram')
      AND name = 'IX_tblSbSubProgram_ProgramID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSubProgram_ProgramID
        ON dbo.tblSbSubProgram (ProgramID, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSubProgram')
      AND name = 'UX_tblSbSubProgram_ProgramID_SubProgramCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbSubProgram_ProgramID_SubProgramCode
        ON dbo.tblSbSubProgram (ProgramID, SubProgramCode)
        WHERE SubProgramCode IS NOT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSubProgram')
      AND name = 'UX_tblSbSubProgram_SubProgramID_ProgramID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbSubProgram_SubProgramID_ProgramID
        ON dbo.tblSbSubProgram (SubProgramID, ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbSubProgram')
      AND name = 'IX_tblSbSubProgram_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSubProgram_SourceLookup
        ON dbo.tblSbSubProgram (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbObjective', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbObjective (
        ObjectiveID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProgramID INT NOT NULL,
        SubProgramID INT NULL,
        ObjectiveText NVARCHAR(MAX) NOT NULL,
        PolicyLink NVARCHAR(200) NULL,
        PriorityRank INT NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbObjective_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbObjective_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbObjective_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbObjective_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbObjective')
)
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD CONSTRAINT FK_tblSbObjective_Program
        FOREIGN KEY (ProgramID)
        REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbObjective_SubProgramProgram'
      AND parent_object_id = OBJECT_ID('dbo.tblSbObjective')
)
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD CONSTRAINT FK_tblSbObjective_SubProgramProgram
        FOREIGN KEY (SubProgramID, ProgramID)
        REFERENCES dbo.tblSbSubProgram (SubProgramID, ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbObjective')
      AND name = 'IX_tblSbObjective_ProgramID_SubProgramID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbObjective_ProgramID_SubProgramID
        ON dbo.tblSbObjective (ProgramID, SubProgramID, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbObjective')
      AND name = 'IX_tblSbObjective_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbObjective_SourceLookup
        ON dbo.tblSbObjective (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbIndicator', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbIndicator (
        IndicatorID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        IndicatorTypeCode NVARCHAR(10) NOT NULL,
        IndicatorName NVARCHAR(200) NOT NULL,
        IndicatorDefinition NVARCHAR(MAX) NULL,
        UnitOfMeasure NVARCHAR(50) NULL,
        DataSource NVARCHAR(200) NULL,
        FrequencyCode NVARCHAR(20) NULL,
        Disaggregation NVARCHAR(200) NULL,
        QualityNotes NVARCHAR(MAX) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbIndicator_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbIndicator_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbIndicator_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbIndicator_TypeCode CHECK (IndicatorTypeCode IN (N'OUTCOME', N'OUTPUT'))
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbIndicator')
      AND name = 'IX_tblSbIndicator_TypeCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbIndicator_TypeCode
        ON dbo.tblSbIndicator (IndicatorTypeCode, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbIndicator')
      AND name = 'IX_tblSbIndicator_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbIndicator_SourceLookup
        ON dbo.tblSbIndicator (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbObjectiveIndicator', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbObjectiveIndicator (
        ObjectiveIndicatorID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ObjectiveID INT NOT NULL,
        IndicatorID INT NOT NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbObjectiveIndicator_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbObjectiveIndicator_CreatedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbObjectiveIndicator_Objective'
      AND parent_object_id = OBJECT_ID('dbo.tblSbObjectiveIndicator')
)
BEGIN
    ALTER TABLE dbo.tblSbObjectiveIndicator
    ADD CONSTRAINT FK_tblSbObjectiveIndicator_Objective
        FOREIGN KEY (ObjectiveID)
        REFERENCES dbo.tblSbObjective (ObjectiveID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbObjectiveIndicator_Indicator'
      AND parent_object_id = OBJECT_ID('dbo.tblSbObjectiveIndicator')
)
BEGIN
    ALTER TABLE dbo.tblSbObjectiveIndicator
    ADD CONSTRAINT FK_tblSbObjectiveIndicator_Indicator
        FOREIGN KEY (IndicatorID)
        REFERENCES dbo.tblSbIndicator (IndicatorID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbObjectiveIndicator')
      AND name = 'UX_tblSbObjectiveIndicator_ObjectiveID_IndicatorID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbObjectiveIndicator_ObjectiveID_IndicatorID
        ON dbo.tblSbObjectiveIndicator (ObjectiveID, IndicatorID);
END
GO

IF OBJECT_ID('dbo.tblSbIndicatorTarget', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbIndicatorTarget (
        IndicatorTargetID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        IndicatorID INT NOT NULL,
        VersionID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        BaselineValue DECIMAL(18,6) NULL,
        TargetValue DECIMAL(18,6) NOT NULL,
        Notes NVARCHAR(MAX) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbIndicatorTarget_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbIndicatorTarget_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbIndicatorTarget_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbIndicatorTarget_Indicator'
      AND parent_object_id = OBJECT_ID('dbo.tblSbIndicatorTarget')
)
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD CONSTRAINT FK_tblSbIndicatorTarget_Indicator
        FOREIGN KEY (IndicatorID)
        REFERENCES dbo.tblSbIndicator (IndicatorID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbIndicatorTarget_VersionFiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbIndicatorTarget')
)
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD CONSTRAINT FK_tblSbIndicatorTarget_VersionFiscalYear
        FOREIGN KEY (VersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbIndicatorTarget')
      AND name = 'UX_tblSbIndicatorTarget_IndicatorID_VersionID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbIndicatorTarget_IndicatorID_VersionID
        ON dbo.tblSbIndicatorTarget (IndicatorID, VersionID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbIndicatorTarget')
      AND name = 'IX_tblSbIndicatorTarget_VersionID_FiscalYearID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbIndicatorTarget_VersionID_FiscalYearID
        ON dbo.tblSbIndicatorTarget (VersionID, FiscalYearID, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbIndicatorTarget')
      AND name = 'IX_tblSbIndicatorTarget_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbIndicatorTarget_SourceLookup
        ON dbo.tblSbIndicatorTarget (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbOutput', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbOutput (
        OutputID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProgramID INT NOT NULL,
        SubProgramID INT NULL,
        OutputName NVARCHAR(200) NOT NULL,
        OutputDescription NVARCHAR(MAX) NULL,
        OutputOwnerOrgUnitID INT NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbOutput_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbOutput_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbOutput_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbOutput_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbOutput')
)
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD CONSTRAINT FK_tblSbOutput_Program
        FOREIGN KEY (ProgramID)
        REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbOutput_SubProgramProgram'
      AND parent_object_id = OBJECT_ID('dbo.tblSbOutput')
)
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD CONSTRAINT FK_tblSbOutput_SubProgramProgram
        FOREIGN KEY (SubProgramID, ProgramID)
        REFERENCES dbo.tblSbSubProgram (SubProgramID, ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbOutput_OutputOwnerOrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbOutput')
)
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD CONSTRAINT FK_tblSbOutput_OutputOwnerOrgUnit
        FOREIGN KEY (OutputOwnerOrgUnitID)
        REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOutput')
      AND name = 'IX_tblSbOutput_ProgramID_SubProgramID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbOutput_ProgramID_SubProgramID
        ON dbo.tblSbOutput (ProgramID, SubProgramID, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOutput')
      AND name = 'IX_tblSbOutput_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbOutput_SourceLookup
        ON dbo.tblSbOutput (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbOutputIndicator', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbOutputIndicator (
        OutputIndicatorID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        OutputID INT NOT NULL,
        IndicatorID INT NOT NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbOutputIndicator_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbOutputIndicator_CreatedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbOutputIndicator_Output'
      AND parent_object_id = OBJECT_ID('dbo.tblSbOutputIndicator')
)
BEGIN
    ALTER TABLE dbo.tblSbOutputIndicator
    ADD CONSTRAINT FK_tblSbOutputIndicator_Output
        FOREIGN KEY (OutputID)
        REFERENCES dbo.tblSbOutput (OutputID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbOutputIndicator_Indicator'
      AND parent_object_id = OBJECT_ID('dbo.tblSbOutputIndicator')
)
BEGIN
    ALTER TABLE dbo.tblSbOutputIndicator
    ADD CONSTRAINT FK_tblSbOutputIndicator_Indicator
        FOREIGN KEY (IndicatorID)
        REFERENCES dbo.tblSbIndicator (IndicatorID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbOutputIndicator')
      AND name = 'UX_tblSbOutputIndicator_OutputID_IndicatorID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbOutputIndicator_OutputID_IndicatorID
        ON dbo.tblSbOutputIndicator (OutputID, IndicatorID);
END
GO

IF OBJECT_ID('dbo.tblSbActivity', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbActivity (
        ActivityID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        OutputID INT NOT NULL,
        ActivityName NVARCHAR(200) NOT NULL,
        ActivityDescription NVARCHAR(MAX) NULL,
        ActivityTypeCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblSbActivity_ActivityTypeCode DEFAULT (N'OPERATIONAL'),
        LocationCode NVARCHAR(50) NULL,
        StartDate DATE NULL,
        EndDate DATE NULL,
        ImplementationStatusCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblSbActivity_ImplementationStatusCode DEFAULT (N'PLANNED'),
        ProcurementRequiredFlag BIT NOT NULL CONSTRAINT DF_tblSbActivity_ProcurementRequiredFlag DEFAULT (0),
        Dependencies NVARCHAR(MAX) NULL,
        RiskNotes NVARCHAR(MAX) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbActivity_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbActivity_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbActivity_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbActivity_ActivityTypeCode CHECK (ActivityTypeCode IN (N'OPERATIONAL', N'PROJECT')),
        CONSTRAINT CK_tblSbActivity_ImplementationStatusCode CHECK (ImplementationStatusCode IN (N'PLANNED', N'ONGOING', N'COMPLETE')),
        CONSTRAINT CK_tblSbActivity_Dates CHECK (EndDate IS NULL OR StartDate IS NULL OR EndDate >= StartDate)
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbActivity_Output'
      AND parent_object_id = OBJECT_ID('dbo.tblSbActivity')
)
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD CONSTRAINT FK_tblSbActivity_Output
        FOREIGN KEY (OutputID)
        REFERENCES dbo.tblSbOutput (OutputID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbActivity')
      AND name = 'IX_tblSbActivity_OutputID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbActivity_OutputID
        ON dbo.tblSbActivity (OutputID, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbActivity')
      AND name = 'IX_tblSbActivity_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbActivity_SourceLookup
        ON dbo.tblSbActivity (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbEconomicItem', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbEconomicItem (
        EconomicItemID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ParentEconomicItemID INT NULL,
        EconomicCode NVARCHAR(50) NOT NULL,
        EconomicName NVARCHAR(200) NOT NULL,
        EconomicLevel INT NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbEconomicItem_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbEconomicItem_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbEconomicItem_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbEconomicItem_ParentEconomicItem'
      AND parent_object_id = OBJECT_ID('dbo.tblSbEconomicItem')
)
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD CONSTRAINT FK_tblSbEconomicItem_ParentEconomicItem
        FOREIGN KEY (ParentEconomicItemID)
        REFERENCES dbo.tblSbEconomicItem (EconomicItemID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbEconomicItem')
      AND name = 'UX_tblSbEconomicItem_EconomicCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbEconomicItem_EconomicCode
        ON dbo.tblSbEconomicItem (EconomicCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbEconomicItem')
      AND name = 'IX_tblSbEconomicItem_ParentEconomicItemID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbEconomicItem_ParentEconomicItemID
        ON dbo.tblSbEconomicItem (ParentEconomicItemID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbEconomicItem')
      AND name = 'IX_tblSbEconomicItem_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbEconomicItem_SourceLookup
        ON dbo.tblSbEconomicItem (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbFundingType', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFundingType (
        FundingTypeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FundingTypeCode NVARCHAR(50) NOT NULL,
        FundingTypeName NVARCHAR(150) NOT NULL,
        FundingTypeDescription NVARCHAR(MAX) NULL,
        DefaultPhasingProfileID INT NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbFundingType_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbFundingType_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbFundingType_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingType')
      AND name = 'UX_tblSbFundingType_FundingTypeCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbFundingType_FundingTypeCode
        ON dbo.tblSbFundingType (FundingTypeCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingType')
      AND name = 'IX_tblSbFundingType_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingType_SourceLookup
        ON dbo.tblSbFundingType (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbFundingSource', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFundingSource (
        FundingSourceID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FundingTypeID INT NULL,
        FundingTypeCode NVARCHAR(50) NOT NULL,
        FundingSourceName NVARCHAR(200) NOT NULL,
        DonorName NVARCHAR(200) NULL,
        ConditionsText NVARCHAR(MAX) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbFundingSource_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbFundingSource_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbFundingSource_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFundingSource_FundingType'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSource')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD CONSTRAINT FK_tblSbFundingSource_FundingType
        FOREIGN KEY (FundingTypeID)
        REFERENCES dbo.tblSbFundingType (FundingTypeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSource')
      AND name = 'IX_tblSbFundingSource_FundingTypeCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSource_FundingTypeCode
        ON dbo.tblSbFundingSource (FundingTypeCode, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSource')
      AND name = 'IX_tblSbFundingSource_FundingTypeID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSource_FundingTypeID
        ON dbo.tblSbFundingSource (FundingTypeID, ActiveFlag);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSource')
      AND name = 'IX_tblSbFundingSource_SourceLookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSource_SourceLookup
        ON dbo.tblSbFundingSource (SourceFiscalYearID, SourceSegmentNo, SourceDataObjectCode, SourceSegmentCode);
END
GO

IF OBJECT_ID('dbo.tblSbActivityBudget', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbActivityBudget (
        ActivityBudgetID BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ActivityID INT NOT NULL,
        VersionID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        EconomicItemID INT NOT NULL,
        FundingSourceID INT NULL,
        Amount DECIMAL(18,2) NOT NULL CONSTRAINT DF_tblSbActivityBudget_Amount DEFAULT (0),
        CurrencyCode NVARCHAR(10) NULL,
        Notes NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbActivityBudget_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbActivityBudget_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbActivityBudget_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbActivityBudget_Amount CHECK (Amount >= 0)
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbActivityBudget_Activity'
      AND parent_object_id = OBJECT_ID('dbo.tblSbActivityBudget')
)
BEGIN
    ALTER TABLE dbo.tblSbActivityBudget
    ADD CONSTRAINT FK_tblSbActivityBudget_Activity
        FOREIGN KEY (ActivityID)
        REFERENCES dbo.tblSbActivity (ActivityID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbActivityBudget_VersionFiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbActivityBudget')
)
BEGIN
    ALTER TABLE dbo.tblSbActivityBudget
    ADD CONSTRAINT FK_tblSbActivityBudget_VersionFiscalYear
        FOREIGN KEY (VersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbActivityBudget_EconomicItem'
      AND parent_object_id = OBJECT_ID('dbo.tblSbActivityBudget')
)
BEGIN
    ALTER TABLE dbo.tblSbActivityBudget
    ADD CONSTRAINT FK_tblSbActivityBudget_EconomicItem
        FOREIGN KEY (EconomicItemID)
        REFERENCES dbo.tblSbEconomicItem (EconomicItemID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbActivityBudget_FundingSource'
      AND parent_object_id = OBJECT_ID('dbo.tblSbActivityBudget')
)
BEGIN
    ALTER TABLE dbo.tblSbActivityBudget
    ADD CONSTRAINT FK_tblSbActivityBudget_FundingSource
        FOREIGN KEY (FundingSourceID)
        REFERENCES dbo.tblSbFundingSource (FundingSourceID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbActivityBudget')
      AND name = 'UX_tblSbActivityBudget_ActivityID_VersionID_EconomicItemID_FundingSourceID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbActivityBudget_ActivityID_VersionID_EconomicItemID_FundingSourceID
        ON dbo.tblSbActivityBudget (ActivityID, VersionID, EconomicItemID, FundingSourceID)
        WHERE ActiveFlag = 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbActivityBudget')
      AND name = 'IX_tblSbActivityBudget_VersionID_FiscalYearID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbActivityBudget_VersionID_FiscalYearID
        ON dbo.tblSbActivityBudget (VersionID, FiscalYearID, ActiveFlag)
        INCLUDE (ActivityID, EconomicItemID, FundingSourceID, Amount);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbActivityBudget')
      AND name = 'IX_tblSbActivityBudget_EconomicItemID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbActivityBudget_EconomicItemID
        ON dbo.tblSbActivityBudget (EconomicItemID, VersionID, FiscalYearID);
END
GO

IF OBJECT_ID('dbo.tblSbCeiling', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbCeiling (
        CeilingID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        VersionID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        ScopeTypeCode NVARCHAR(30) NOT NULL,
        OrgUnitID INT NULL,
        SectorID INT NULL,
        ProgramID INT NULL,
        CeilingAmount DECIMAL(18,2) NOT NULL,
        Notes NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbCeiling_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbCeiling_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbCeiling_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbCeiling_Amount CHECK (CeilingAmount >= 0),
        CONSTRAINT CK_tblSbCeiling_ScopeTypeCode CHECK (ScopeTypeCode IN (
            N'GLOBAL', N'ORGUNIT', N'SECTOR', N'PROGRAM', N'ORGUNIT_PROGRAM', N'SECTOR_PROGRAM'
        )),
        CONSTRAINT CK_tblSbCeiling_ScopeMatch CHECK (
            (ScopeTypeCode = N'GLOBAL' AND OrgUnitID IS NULL AND SectorID IS NULL AND ProgramID IS NULL)
            OR
            (ScopeTypeCode = N'ORGUNIT' AND OrgUnitID IS NOT NULL AND SectorID IS NULL AND ProgramID IS NULL)
            OR
            (ScopeTypeCode = N'SECTOR' AND OrgUnitID IS NULL AND SectorID IS NOT NULL AND ProgramID IS NULL)
            OR
            (ScopeTypeCode = N'PROGRAM' AND OrgUnitID IS NULL AND SectorID IS NULL AND ProgramID IS NOT NULL)
            OR
            (ScopeTypeCode = N'ORGUNIT_PROGRAM' AND OrgUnitID IS NOT NULL AND SectorID IS NULL AND ProgramID IS NOT NULL)
            OR
            (ScopeTypeCode = N'SECTOR_PROGRAM' AND OrgUnitID IS NULL AND SectorID IS NOT NULL AND ProgramID IS NOT NULL)
        )
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbCeiling_VersionFiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbCeiling')
)
BEGIN
    ALTER TABLE dbo.tblSbCeiling
    ADD CONSTRAINT FK_tblSbCeiling_VersionFiscalYear
        FOREIGN KEY (VersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbCeiling_OrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbCeiling')
)
BEGIN
    ALTER TABLE dbo.tblSbCeiling
    ADD CONSTRAINT FK_tblSbCeiling_OrgUnit
        FOREIGN KEY (OrgUnitID)
        REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbCeiling_Sector'
      AND parent_object_id = OBJECT_ID('dbo.tblSbCeiling')
)
BEGIN
    ALTER TABLE dbo.tblSbCeiling
    ADD CONSTRAINT FK_tblSbCeiling_Sector
        FOREIGN KEY (SectorID)
        REFERENCES dbo.tblSbSector (SectorID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbCeiling_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbCeiling')
)
BEGIN
    ALTER TABLE dbo.tblSbCeiling
    ADD CONSTRAINT FK_tblSbCeiling_Program
        FOREIGN KEY (ProgramID)
        REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbCeiling')
      AND name = 'UX_tblSbCeiling_Scope'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbCeiling_Scope
        ON dbo.tblSbCeiling (VersionID, FiscalYearID, ScopeTypeCode, OrgUnitID, SectorID, ProgramID)
        WHERE ActiveFlag = 1;
END
GO

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFiscalRisk (
        FiscalRiskID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        RiskTypeCode NVARCHAR(30) NOT NULL,
        RiskTitle NVARCHAR(200) NOT NULL,
        RiskDescription NVARCHAR(MAX) NULL,
        LikelihoodScore TINYINT NULL,
        ImpactScore TINYINT NULL,
        EstimatedFiscalExposure DECIMAL(18,2) NULL,
        MitigationStrategy NVARCHAR(MAX) NULL,
        OwnerOrgUnitID INT NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbFiscalRisk_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbFiscalRisk_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbFiscalRisk_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbFiscalRisk_LikelihoodScore CHECK (LikelihoodScore IS NULL OR (LikelihoodScore BETWEEN 1 AND 5)),
        CONSTRAINT CK_tblSbFiscalRisk_ImpactScore CHECK (ImpactScore IS NULL OR (ImpactScore BETWEEN 1 AND 5))
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFiscalRisk_OwnerOrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFiscalRisk')
)
BEGIN
    ALTER TABLE dbo.tblSbFiscalRisk
    ADD CONSTRAINT FK_tblSbFiscalRisk_OwnerOrgUnit
        FOREIGN KEY (OwnerOrgUnitID)
        REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFiscalRisk')
      AND name = 'IX_tblSbFiscalRisk_RiskTypeCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFiscalRisk_RiskTypeCode
        ON dbo.tblSbFiscalRisk (RiskTypeCode, ActiveFlag);
END
GO

IF OBJECT_ID('dbo.tblSbProgramRisk', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProgramRisk (
        ProgramRiskID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProgramID INT NOT NULL,
        FiscalRiskID INT NOT NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProgramRisk_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProgramRisk_CreatedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProgramRisk_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProgramRisk')
)
BEGIN
    ALTER TABLE dbo.tblSbProgramRisk
    ADD CONSTRAINT FK_tblSbProgramRisk_Program
        FOREIGN KEY (ProgramID)
        REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProgramRisk_FiscalRisk'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProgramRisk')
)
BEGIN
    ALTER TABLE dbo.tblSbProgramRisk
    ADD CONSTRAINT FK_tblSbProgramRisk_FiscalRisk
        FOREIGN KEY (FiscalRiskID)
        REFERENCES dbo.tblSbFiscalRisk (FiscalRiskID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgramRisk')
      AND name = 'UX_tblSbProgramRisk_ProgramID_FiscalRiskID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProgramRisk_ProgramID_FiscalRiskID
        ON dbo.tblSbProgramRisk (ProgramID, FiscalRiskID);
END
GO

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbNarrative (
        NarrativeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        VersionID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        SectionCode NVARCHAR(30) NOT NULL,
        OrgUnitID INT NULL,
        SectorID INT NULL,
        ProgramID INT NULL,
        NarrativeTitle NVARCHAR(200) NULL,
        BodyText NVARCHAR(MAX) NOT NULL,
        SortOrder INT NOT NULL CONSTRAINT DF_tblSbNarrative_SortOrder DEFAULT (0),
        LockedFlag BIT NOT NULL CONSTRAINT DF_tblSbNarrative_LockedFlag DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbNarrative_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbNarrative_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbNarrative_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbNarrative_SectionCode CHECK (SectionCode IN (
            N'MACRO', N'REVENUE', N'EXPENDITURE', N'PRIORITIES', N'RISKS', N'MTFF'
        ))
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbNarrative_VersionFiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbNarrative')
)
BEGIN
    ALTER TABLE dbo.tblSbNarrative
    ADD CONSTRAINT FK_tblSbNarrative_VersionFiscalYear
        FOREIGN KEY (VersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbNarrative_OrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbNarrative')
)
BEGIN
    ALTER TABLE dbo.tblSbNarrative
    ADD CONSTRAINT FK_tblSbNarrative_OrgUnit
        FOREIGN KEY (OrgUnitID)
        REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbNarrative_Sector'
      AND parent_object_id = OBJECT_ID('dbo.tblSbNarrative')
)
BEGIN
    ALTER TABLE dbo.tblSbNarrative
    ADD CONSTRAINT FK_tblSbNarrative_Sector
        FOREIGN KEY (SectorID)
        REFERENCES dbo.tblSbSector (SectorID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbNarrative_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbNarrative')
)
BEGIN
    ALTER TABLE dbo.tblSbNarrative
    ADD CONSTRAINT FK_tblSbNarrative_Program
        FOREIGN KEY (ProgramID)
        REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbNarrative')
      AND name = 'IX_tblSbNarrative_VersionID_FiscalYearID_SectionCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbNarrative_VersionID_FiscalYearID_SectionCode
        ON dbo.tblSbNarrative (VersionID, FiscalYearID, SectionCode, SortOrder, ActiveFlag);
END
GO

/*
Optional business-rule triggers:
- ObjectiveIndicator may reference any indicator type, but the app should usually
  attach OUTCOME indicators here.
- OutputIndicator may reference any indicator type, but the app should usually
  attach OUTPUT indicators here.
These are left to application validation for now to keep the DDL deployable
across environments without cross-table type triggers.
*/
