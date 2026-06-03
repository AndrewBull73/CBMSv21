/*
Phase 01 / Step 07
Install the current CBMSv21 strategy foundation.
This is a curated merged copy of the active strategy SQL assets.
*/

/* Source: backend-php\config\sql\create_strategic_budgeting_module_v2.sql */
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


/* Source: backend-php\config\sql\create_tblSbGoal_and_ObjectiveGoal.sql */
GO

IF OBJECT_ID('dbo.tblSbGoal', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbGoal (
        GoalID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        GoalCode NVARCHAR(50) NOT NULL,
        GoalName NVARCHAR(300) NOT NULL,
        GoalTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbGoal_GoalTypeCode DEFAULT (N'SDG'),
        GoalDescription NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbGoal_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbGoal_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbGoal_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbGoal')
      AND name = 'UX_tblSbGoal_GoalCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbGoal_GoalCode
        ON dbo.tblSbGoal (GoalCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbGoal_GoalTypeCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbGoal')
)
BEGIN
    ALTER TABLE dbo.tblSbGoal
    ADD CONSTRAINT CK_tblSbGoal_GoalTypeCode
        CHECK (GoalTypeCode IN (N'SDG', N'NDP', N'NSDP', N'GOVT_PRIORITY', N'OTHER'));
END
GO

IF OBJECT_ID('dbo.tblSbObjectiveGoal', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbObjectiveGoal (
        ObjectiveGoalID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ObjectiveID INT NOT NULL,
        GoalID INT NOT NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbObjectiveGoal_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbObjectiveGoal_CreatedDate DEFAULT (SYSDATETIME())
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbObjectiveGoal_Objective'
      AND parent_object_id = OBJECT_ID('dbo.tblSbObjectiveGoal')
)
BEGIN
    ALTER TABLE dbo.tblSbObjectiveGoal
    ADD CONSTRAINT FK_tblSbObjectiveGoal_Objective
        FOREIGN KEY (ObjectiveID)
        REFERENCES dbo.tblSbObjective (ObjectiveID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbObjectiveGoal_Goal'
      AND parent_object_id = OBJECT_ID('dbo.tblSbObjectiveGoal')
)
BEGIN
    ALTER TABLE dbo.tblSbObjectiveGoal
    ADD CONSTRAINT FK_tblSbObjectiveGoal_Goal
        FOREIGN KEY (GoalID)
        REFERENCES dbo.tblSbGoal (GoalID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbObjectiveGoal')
      AND name = 'UX_tblSbObjectiveGoal_ObjectiveID_GoalID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbObjectiveGoal_ObjectiveID_GoalID
        ON dbo.tblSbObjectiveGoal (ObjectiveID, GoalID);
END
GO


/* Source: backend-php\config\sql\create_tblSbStrategicPillar_and_link_goal.sql */
GO

IF OBJECT_ID('dbo.tblSbStrategicPillar', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbStrategicPillar (
        StrategicPillarID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StrategicPillarCode NVARCHAR(50) NOT NULL,
        StrategicPillarName NVARCHAR(300) NOT NULL,
        FrameworkCode NVARCHAR(50) NOT NULL CONSTRAINT DF_tblSbStrategicPillar_FrameworkCode DEFAULT (N'FYDP_II'),
        StrategicPillarDescription NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbStrategicPillar_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbStrategicPillar_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbStrategicPillar_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbStrategicPillar')
      AND name = 'UX_tblSbStrategicPillar_Code'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbStrategicPillar_Code
        ON dbo.tblSbStrategicPillar (StrategicPillarCode);
END
GO

IF COL_LENGTH('dbo.tblSbGoal', 'StrategicPillarID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbGoal
    ADD StrategicPillarID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbGoal_StrategicPillar'
      AND parent_object_id = OBJECT_ID('dbo.tblSbGoal')
)
BEGIN
    ALTER TABLE dbo.tblSbGoal
    ADD CONSTRAINT FK_tblSbGoal_StrategicPillar
        FOREIGN KEY (StrategicPillarID)
        REFERENCES dbo.tblSbStrategicPillar (StrategicPillarID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbGoal')
      AND name = 'IX_tblSbGoal_StrategicPillarID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbGoal_StrategicPillarID
        ON dbo.tblSbGoal (StrategicPillarID, GoalTypeCode, ActiveFlag);
END
GO


/* Source: backend-php\config\sql\create_tblSbProgramOrgLink.sql */
IF OBJECT_ID('dbo.tblSbProgramOrgLink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProgramOrgLink (
        ProgramOrgLinkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProgramID INT NOT NULL,
        OrgUnitID INT NOT NULL,
        LinkTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProgramOrgLink_LinkTypeCode DEFAULT (N'PARTICIPATING'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProgramOrgLink_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProgramOrgLink_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProgramOrgLink_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbProgramOrgLink_LinkTypeCode
            CHECK (LinkTypeCode IN (N'PARTICIPATING', N'CONTRIBUTING', N'IMPLEMENTING', N'REPORTING'))
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProgramOrgLink_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProgramOrgLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProgramOrgLink
    ADD CONSTRAINT FK_tblSbProgramOrgLink_Program
        FOREIGN KEY (ProgramID)
        REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProgramOrgLink_OrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProgramOrgLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProgramOrgLink
    ADD CONSTRAINT FK_tblSbProgramOrgLink_OrgUnit
        FOREIGN KEY (OrgUnitID)
        REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgramOrgLink')
      AND name = 'UX_tblSbProgramOrgLink_ProgramID_OrgUnitID_Active'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProgramOrgLink_ProgramID_OrgUnitID_Active
        ON dbo.tblSbProgramOrgLink (ProgramID, OrgUnitID)
        WHERE ActiveFlag = 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbProgramOrgLink')
      AND name = 'IX_tblSbProgramOrgLink_OrgUnitID_ActiveFlag'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProgramOrgLink_OrgUnitID_ActiveFlag
        ON dbo.tblSbProgramOrgLink (OrgUnitID, ActiveFlag);
END
GO


/* Source: backend-php\config\sql\create_tblSbResourceEnvelope.sql */
GO

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbResourceEnvelope (
        ResourceEnvelopeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        FundingTypeID INT NOT NULL,
        FundingSourceID INT NULL,
        ReliabilityCode NVARCHAR(30) NULL,
        RestrictionCode NVARCHAR(30) NULL,
        RestrictionScopeTypeCode NVARCHAR(30) NULL,
        RestrictionReference NVARCHAR(100) NULL,
        RestrictionDescription NVARCHAR(255) NULL,
        RestrictedSectorID INT NULL,
        RestrictedProgramID INT NULL,
        RestrictedSubProgramID INT NULL,
        RestrictedOrgUnitID INT NULL,
        RestrictedActivityID INT NULL,
        RestrictedEconomicItemID INT NULL,
        RestrictedProjectReference NVARCHAR(100) NULL,
        FinancingInstrumentCode NVARCHAR(50) NULL,
        OuterYearAssumptionBasisCode NVARCHAR(50) NULL,
        CurrentYearAmount DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbResourceEnvelope_CurrentYearAmount DEFAULT ((0)),
        BP1Amount DECIMAL(19,6) NULL,
        BP2Amount DECIMAL(19,6) NULL,
        BP3Amount DECIMAL(19,6) NULL,
        BP4Amount DECIMAL(19,6) NULL,
        BP5Amount DECIMAL(19,6) NULL,
        BP6Amount DECIMAL(19,6) NULL,
        BP7Amount DECIMAL(19,6) NULL,
        BP8Amount DECIMAL(19,6) NULL,
        BP9Amount DECIMAL(19,6) NULL,
        BP10Amount DECIMAL(19,6) NULL,
        BP11Amount DECIMAL(19,6) NULL,
        BP12Amount DECIMAL(19,6) NULL,
        OuterYear1Amount DECIMAL(19,6) NULL,
        OuterYear2Amount DECIMAL(19,6) NULL,
        OuterYear3Amount DECIMAL(19,6) NULL,
        OuterYear4Amount DECIMAL(19,6) NULL,
        OuterYear5Amount DECIMAL(19,6) NULL,
        EnvelopeNotes NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbResourceEnvelope_ActiveFlag DEFAULT ((1)),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbResourceEnvelope_CreatedBy DEFAULT ((1)),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbResourceEnvelope_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbResourceEnvelope_tblSbFundingType'
      AND parent_object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
)
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope
    ADD CONSTRAINT FK_tblSbResourceEnvelope_tblSbFundingType
        FOREIGN KEY (FundingTypeID)
        REFERENCES dbo.tblSbFundingType (FundingTypeID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbResourceEnvelope_tblSbFundingSource'
      AND parent_object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
)
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope
    ADD CONSTRAINT FK_tblSbResourceEnvelope_tblSbFundingSource
        FOREIGN KEY (FundingSourceID)
        REFERENCES dbo.tblSbFundingSource (FundingSourceID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'UX_tblSbResourceEnvelope_ContextFunding'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbResourceEnvelope_ContextFunding
        ON dbo.tblSbResourceEnvelope (
            FiscalYearID,
            VersionID,
            FundingTypeID,
            FundingSourceID,
            ReliabilityCode,
            RestrictionCode,
            RestrictionScopeTypeCode,
            RestrictionReference,
            RestrictedSectorID,
            RestrictedProgramID,
            RestrictedSubProgramID,
            RestrictedOrgUnitID,
            RestrictedActivityID,
            RestrictedEconomicItemID,
            RestrictedProjectReference,
            FinancingInstrumentCode,
            OuterYearAssumptionBasisCode
        )
        WHERE ActiveFlag = 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'IX_tblSbResourceEnvelope_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbResourceEnvelope_Context
        ON dbo.tblSbResourceEnvelope (FiscalYearID, VersionID, ActiveFlag)
        INCLUDE (FundingTypeID, FundingSourceID, ReliabilityCode, RestrictionCode, RestrictionScopeTypeCode, RestrictionReference, RestrictionDescription, RestrictedSectorID, RestrictedProgramID, RestrictedSubProgramID, RestrictedOrgUnitID, RestrictedActivityID, RestrictedEconomicItemID, RestrictedProjectReference, FinancingInstrumentCode, OuterYearAssumptionBasisCode, CurrentYearAmount, OuterYear1Amount, OuterYear2Amount, OuterYear3Amount, OuterYear4Amount, OuterYear5Amount);
END
GO


/* Source: backend-php\config\sql\create_tblSbSegmentConfig.sql */
GO

IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbSegmentConfig (
        StrategicSegmentConfigID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        StrategicDimensionCode NVARCHAR(30) NOT NULL,
        SegmentNo INT NULL,
        Notes NVARCHAR(200) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbSegmentConfig_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbSegmentConfig_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbSegmentConfig_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode CHECK (
            StrategicDimensionCode IN (
                N'PROGRAM',
                N'SUBPROGRAM',
                N'PROJECT',
                N'SECTOR',
                N'ECONOMIC',
                N'FUNDING_TYPE',
                N'FUNDING_SOURCE',
                N'OBJECTIVE',
                N'INDICATOR',
                N'TARGET',
                N'ACTIVITY',
                N'OUTPUT'
            )
        ),
        CONSTRAINT CK_tblSbSegmentConfig_SegmentNo CHECK (SegmentNo IS NULL OR SegmentNo BETWEEN 1 AND 20)
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbSegmentConfig_FiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentConfig
    ADD CONSTRAINT FK_tblSbSegmentConfig_FiscalYear
        FOREIGN KEY (FiscalYearID)
        REFERENCES dbo.tblFiscalYears (FiscalYearID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbSegmentConfig_FiscalYearID_StrategicDimensionCode'
      AND object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbSegmentConfig_FiscalYearID_StrategicDimensionCode
        ON dbo.tblSbSegmentConfig (FiscalYearID, StrategicDimensionCode);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbSegmentConfig_SegmentNo'
      AND object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbSegmentConfig_SegmentNo
        ON dbo.tblSbSegmentConfig (SegmentNo, FiscalYearID, ActiveFlag);
END
GO


/* Source: backend-php\config\sql\create_tblSbProject.sql */
GO

IF OBJECT_ID('dbo.tblSbProject', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProject (
        ProjectID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectCode NVARCHAR(50) NULL,
        ProjectName NVARCHAR(200) NOT NULL,
        ProjectDescription NVARCHAR(MAX) NULL,
        SourceFiscalYearID INT NULL,
        SourceDataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NULL,
        SourceSegmentCode NVARCHAR(50) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProject_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProject_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProject_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProject_Source'
      AND object_id = OBJECT_ID('dbo.tblSbProject')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProject_Source
        ON dbo.tblSbProject (SourceFiscalYearID, SourceDataObjectCode, SourceSegmentCode)
        WHERE SourceFiscalYearID IS NOT NULL
          AND SourceSegmentCode IS NOT NULL
          AND ActiveFlag = 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbProject_Code'
      AND object_id = OBJECT_ID('dbo.tblSbProject')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProject_Code
        ON dbo.tblSbProject (ProjectCode, ActiveFlag);
END
GO


/* Source: backend-php\config\sql\create_tblSbProjectProgramLink.sql */
GO

IF OBJECT_ID('dbo.tblSbProjectProgramLink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProjectProgramLink (
        ProjectProgramLinkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectID INT NOT NULL,
        ProgramID INT NOT NULL,
        LinkTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProjectProgramLink_LinkTypeCode DEFAULT (N'PRIMARY'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectProgramLink_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProjectProgramLink_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProjectProgramLink_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectProgramLink_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectProgramLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectProgramLink
    ADD CONSTRAINT FK_tblSbProjectProgramLink_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectProgramLink_Program'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectProgramLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectProgramLink
    ADD CONSTRAINT FK_tblSbProjectProgramLink_Program
        FOREIGN KEY (ProgramID) REFERENCES dbo.tblSbProgram (ProgramID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProjectProgramLink_ProjectID_ProgramID_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProjectProgramLink')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProjectProgramLink_ProjectID_ProgramID_Active
        ON dbo.tblSbProjectProgramLink (ProjectID, ProgramID)
        WHERE ActiveFlag = 1;
END
GO


/* Source: backend-php\config\sql\create_tblSbProjectObjectiveLink.sql */
GO

IF OBJECT_ID('dbo.tblSbProjectObjectiveLink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProjectObjectiveLink (
        ProjectObjectiveLinkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectID INT NOT NULL,
        ObjectiveID INT NOT NULL,
        ContributionTypeCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProjectObjectiveLink_ContributionTypeCode DEFAULT (N'DIRECT'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectObjectiveLink_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProjectObjectiveLink_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProjectObjectiveLink_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectObjectiveLink_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectObjectiveLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectObjectiveLink
    ADD CONSTRAINT FK_tblSbProjectObjectiveLink_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectObjectiveLink_Objective'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectObjectiveLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectObjectiveLink
    ADD CONSTRAINT FK_tblSbProjectObjectiveLink_Objective
        FOREIGN KEY (ObjectiveID) REFERENCES dbo.tblSbObjective (ObjectiveID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProjectObjectiveLink_ProjectID_ObjectiveID_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProjectObjectiveLink')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProjectObjectiveLink_ProjectID_ObjectiveID_Active
        ON dbo.tblSbProjectObjectiveLink (ProjectID, ObjectiveID)
        WHERE ActiveFlag = 1;
END
GO


/* Source: backend-php\config\sql\create_tblSbProjectOrgUnitLink.sql */
GO

IF OBJECT_ID('dbo.tblSbProjectOrgUnitLink', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProjectOrgUnitLink (
        ProjectOrgUnitLinkID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectID INT NOT NULL,
        OrgUnitID INT NOT NULL,
        RoleCode NVARCHAR(30) NOT NULL CONSTRAINT DF_tblSbProjectOrgUnitLink_RoleCode DEFAULT (N'IMPLEMENTING'),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectOrgUnitLink_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProjectOrgUnitLink_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProjectOrgUnitLink_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectOrgUnitLink_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectOrgUnitLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectOrgUnitLink
    ADD CONSTRAINT FK_tblSbProjectOrgUnitLink_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectOrgUnitLink_OrgUnit'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectOrgUnitLink')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectOrgUnitLink
    ADD CONSTRAINT FK_tblSbProjectOrgUnitLink_OrgUnit
        FOREIGN KEY (OrgUnitID) REFERENCES dbo.tblSbOrgUnit (OrgUnitID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProjectOrgUnitLink_ProjectID_OrgUnitID_RoleCode_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProjectOrgUnitLink')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProjectOrgUnitLink_ProjectID_OrgUnitID_RoleCode_Active
        ON dbo.tblSbProjectOrgUnitLink (ProjectID, OrgUnitID, RoleCode)
        WHERE ActiveFlag = 1;
END
GO


/* Source: backend-php\config\sql\create_tblSbProjectSourceMap.sql */
GO

IF OBJECT_ID('dbo.tblSbProjectSourceMap', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbProjectSourceMap (
        ProjectSourceMapID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ProjectID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        DataObjectCode NVARCHAR(50) NULL,
        SourceSegmentNo INT NOT NULL,
        SourceSegmentCode NVARCHAR(50) NOT NULL,
        SourceSegmentName NVARCHAR(200) NULL,
        SourceSystemCode NVARCHAR(30) NULL,
        IsPrimaryFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectSourceMap_IsPrimaryFlag DEFAULT (1),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbProjectSourceMap_ActiveFlag DEFAULT (1),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbProjectSourceMap_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbProjectSourceMap_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbProjectSourceMap_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbProjectSourceMap')
)
BEGIN
    ALTER TABLE dbo.tblSbProjectSourceMap
    ADD CONSTRAINT FK_tblSbProjectSourceMap_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbProjectSourceMap_Source_Active'
      AND object_id = OBJECT_ID('dbo.tblSbProjectSourceMap')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbProjectSourceMap_Source_Active
        ON dbo.tblSbProjectSourceMap (FiscalYearID, DataObjectCode, SourceSegmentNo, SourceSegmentCode)
        WHERE ActiveFlag = 1;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbProjectSourceMap_ProjectID_FiscalYearID'
      AND object_id = OBJECT_ID('dbo.tblSbProjectSourceMap')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbProjectSourceMap_ProjectID_FiscalYearID
        ON dbo.tblSbProjectSourceMap (ProjectID, FiscalYearID, ActiveFlag);
END
GO


/* Source: backend-php\config\sql\create_tblSbFiscalPeriodConfig.sql */
GO

IF OBJECT_ID('dbo.tblSbFiscalPeriodConfig', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFiscalPeriodConfig (
        FiscalPeriodConfigID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        StartMonthNo TINYINT NOT NULL CONSTRAINT DF_tblSbFiscalPeriodConfig_StartMonthNo DEFAULT ((4)),
        BP1Label NVARCHAR(50) NULL,
        BP2Label NVARCHAR(50) NULL,
        BP3Label NVARCHAR(50) NULL,
        BP4Label NVARCHAR(50) NULL,
        BP5Label NVARCHAR(50) NULL,
        BP6Label NVARCHAR(50) NULL,
        BP7Label NVARCHAR(50) NULL,
        BP8Label NVARCHAR(50) NULL,
        BP9Label NVARCHAR(50) NULL,
        BP10Label NVARCHAR(50) NULL,
        BP11Label NVARCHAR(50) NULL,
        BP12Label NVARCHAR(50) NULL,
        OuterYear1Label NVARCHAR(30) NULL,
        OuterYear2Label NVARCHAR(30) NULL,
        OuterYear3Label NVARCHAR(30) NULL,
        OuterYear4Label NVARCHAR(30) NULL,
        OuterYear5Label NVARCHAR(30) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbFiscalPeriodConfig_ActiveFlag DEFAULT ((1)),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbFiscalPeriodConfig_CreatedBy DEFAULT ((1)),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbFiscalPeriodConfig_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFiscalPeriodConfig')
      AND name = 'UX_tblSbFiscalPeriodConfig_FiscalYearID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbFiscalPeriodConfig_FiscalYearID
        ON dbo.tblSbFiscalPeriodConfig (FiscalYearID);
END
GO


/* Source: backend-php\config\sql\create_tblSbPhasingProfile.sql */
IF OBJECT_ID('dbo.tblSbPhasingProfile', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbPhasingProfile (
        PhasingProfileID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        ProfileCode NVARCHAR(30) NOT NULL,
        ProfileName NVARCHAR(150) NOT NULL,
        ProfileDescription NVARCHAR(500) NULL,
        BP1Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP1Weight DEFAULT ((1)),
        BP2Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP2Weight DEFAULT ((1)),
        BP3Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP3Weight DEFAULT ((1)),
        BP4Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP4Weight DEFAULT ((1)),
        BP5Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP5Weight DEFAULT ((1)),
        BP6Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP6Weight DEFAULT ((1)),
        BP7Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP7Weight DEFAULT ((1)),
        BP8Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP8Weight DEFAULT ((1)),
        BP9Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP9Weight DEFAULT ((1)),
        BP10Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP10Weight DEFAULT ((1)),
        BP11Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP11Weight DEFAULT ((1)),
        BP12Weight DECIMAL(19,6) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_BP12Weight DEFAULT ((1)),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbPhasingProfile_ActiveFlag DEFAULT ((1)),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbPhasingProfile_CreatedBy DEFAULT ((1)),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbPhasingProfile_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbPhasingProfile')
      AND name = 'UX_tblSbPhasingProfile_FiscalYear_ProfileCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbPhasingProfile_FiscalYear_ProfileCode
        ON dbo.tblSbPhasingProfile (FiscalYearID, ProfileCode);
END;
GO


/* Source: backend-php\config\sql\create_tblSbFiscalAssumption.sql */
IF OBJECT_ID('dbo.tblSbFiscalAssumption', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbFiscalAssumption (
        FiscalAssumptionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NULL,
        AssumptionCode NVARCHAR(50) NOT NULL,
        AssumptionName NVARCHAR(150) NOT NULL,
        AssumptionValue DECIMAL(19,6) NOT NULL,
        AssumptionNotes NVARCHAR(500) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbFiscalAssumption_ActiveFlag DEFAULT ((1)),
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbFiscalAssumption_CreatedBy DEFAULT ((1)),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbFiscalAssumption_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFiscalAssumption')
      AND name = 'UX_tblSbFiscalAssumption_ContextCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbFiscalAssumption_ContextCode
        ON dbo.tblSbFiscalAssumption (FiscalYearID, VersionID, AssumptionCode);
END
GO


/* Source: backend-php\config\sql\create_tblSbDimensionAttribute_framework.sql */
IF OBJECT_ID('dbo.tblSbDimensionAttribute', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbDimensionAttribute (
        AttributeID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StrategicDimensionCode NVARCHAR(30) NOT NULL,
        AttributeCode NVARCHAR(50) NOT NULL,
        AttributeName NVARCHAR(150) NOT NULL,
        DataTypeCode NVARCHAR(20) NOT NULL,
        HelpText NVARCHAR(500) NULL,
        IsRequired BIT NOT NULL CONSTRAINT DF_tblSbDimensionAttribute_IsRequired DEFAULT (0),
        DisplayOrder INT NOT NULL CONSTRAINT DF_tblSbDimensionAttribute_DisplayOrder DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbDimensionAttribute_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbDimensionAttribute_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT CK_tblSbDimensionAttribute_StrategicDimensionCode CHECK (
            StrategicDimensionCode IN (
                'ORG_UNIT','SECTOR','PROGRAM','SUBPROGRAM','PROJECT','OBJECTIVE','INDICATOR',
                'TARGET','OUTPUT','ACTIVITY','ECONOMIC','FUNDING_TYPE','FUNDING_SOURCE',
                'GOAL','STRATEGIC_PILLAR'
            )
        ),
        CONSTRAINT CK_tblSbDimensionAttribute_DataTypeCode CHECK (
            DataTypeCode IN ('TEXT','LONG_TEXT','NUMBER','DATE','BOOLEAN','LIST')
        )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbDimensionAttribute_Dimension_AttributeCode'
      AND object_id = OBJECT_ID('dbo.tblSbDimensionAttribute')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbDimensionAttribute_Dimension_AttributeCode
        ON dbo.tblSbDimensionAttribute (StrategicDimensionCode, AttributeCode);
END;
GO

IF OBJECT_ID('dbo.tblSbDimensionAttributeOption', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbDimensionAttributeOption (
        AttributeOptionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        AttributeID INT NOT NULL,
        OptionCode NVARCHAR(50) NOT NULL,
        OptionLabel NVARCHAR(150) NOT NULL,
        DisplayOrder INT NOT NULL CONSTRAINT DF_tblSbDimensionAttributeOption_DisplayOrder DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblSbDimensionAttributeOption_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbDimensionAttributeOption_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT FK_tblSbDimensionAttributeOption_Attribute
            FOREIGN KEY (AttributeID) REFERENCES dbo.tblSbDimensionAttribute(AttributeID)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbDimensionAttributeOption_Attribute_OptionCode'
      AND object_id = OBJECT_ID('dbo.tblSbDimensionAttributeOption')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbDimensionAttributeOption_Attribute_OptionCode
        ON dbo.tblSbDimensionAttributeOption (AttributeID, OptionCode);
END;
GO

IF OBJECT_ID('dbo.tblSbDimensionAttributeValue', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbDimensionAttributeValue (
        AttributeValueID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        StrategicDimensionCode NVARCHAR(30) NOT NULL,
        EntityID INT NOT NULL,
        AttributeID INT NOT NULL,
        ValueText NVARCHAR(MAX) NULL,
        ValueNumber DECIMAL(18,4) NULL,
        ValueDate DATE NULL,
        ValueBit BIT NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbDimensionAttributeValue_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL,
        CONSTRAINT FK_tblSbDimensionAttributeValue_Attribute
            FOREIGN KEY (AttributeID) REFERENCES dbo.tblSbDimensionAttribute(AttributeID),
        CONSTRAINT CK_tblSbDimensionAttributeValue_StrategicDimensionCode CHECK (
            StrategicDimensionCode IN (
                'ORG_UNIT','SECTOR','PROGRAM','SUBPROGRAM','PROJECT','OBJECTIVE','INDICATOR',
                'TARGET','OUTPUT','ACTIVITY','ECONOMIC','FUNDING_TYPE','FUNDING_SOURCE',
                'GOAL','STRATEGIC_PILLAR'
            )
        )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'UX_tblSbDimensionAttributeValue_Dimension_Entity_Attribute'
      AND object_id = OBJECT_ID('dbo.tblSbDimensionAttributeValue')
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbDimensionAttributeValue_Dimension_Entity_Attribute
        ON dbo.tblSbDimensionAttributeValue (StrategicDimensionCode, EntityID, AttributeID);
END;
GO


/* Source: backend-php\config\sql\create_tblSbSegmentPublishRequest.sql */
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


/* Source: backend-php\config\sql\create_tblSbFundingSubmission.sql */
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


/* Source: backend-php\config\sql\create_tblSbVersionWorkflow.sql */
/*
Strategic Budgeting Version Workflow
------------------------------------
Adds workflow state for each fiscal year/version context used by the
Strategic Budgeting module.
*/

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF OBJECT_ID('dbo.tblVersions', 'U') IS NULL
BEGIN
    THROW 50010, 'Strategic workflow requires dbo.tblVersions to exist first.', 1;
END;
GO

IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbVersionWorkflow (
        StrategicVersionWorkflowID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        WorkflowStatusCode NVARCHAR(20) NOT NULL
            CONSTRAINT DF_tblSbVersionWorkflow_WorkflowStatusCode DEFAULT (N'DRAFT'),
        StatusNote NVARCHAR(500) NULL,
        SubmittedBy INT NULL,
        SubmittedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        LockedBy INT NULL,
        LockedDate DATETIME2(0) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbVersionWorkflow_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbVersionWorkflow_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NOT NULL CONSTRAINT DF_tblSbVersionWorkflow_UpdatedBy DEFAULT (1),
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbVersionWorkflow_UpdatedDate DEFAULT (SYSDATETIME()),
        CONSTRAINT CK_tblSbVersionWorkflow_StatusCode CHECK (
            WorkflowStatusCode IN (N'DRAFT', N'SUBMITTED', N'APPROVED', N'LOCKED')
        )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbVersionWorkflow_VersionFiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbVersionWorkflow')
)
BEGIN
    ALTER TABLE dbo.tblSbVersionWorkflow
    ADD CONSTRAINT FK_tblSbVersionWorkflow_VersionFiscalYear
        FOREIGN KEY (VersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbVersionWorkflow')
      AND name = 'UX_tblSbVersionWorkflow_VersionID_FiscalYearID'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbVersionWorkflow_VersionID_FiscalYearID
        ON dbo.tblSbVersionWorkflow (VersionID, FiscalYearID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbVersionWorkflow')
      AND name = 'IX_tblSbVersionWorkflow_StatusCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbVersionWorkflow_StatusCode
        ON dbo.tblSbVersionWorkflow (WorkflowStatusCode, FiscalYearID, VersionID);
END;
GO

IF OBJECT_ID('dbo.tblSbVersionWorkflowHistory', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbVersionWorkflowHistory (
        StrategicWorkflowHistoryID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        WorkflowActionCode NVARCHAR(20) NOT NULL,
        FromStatusCode NVARCHAR(20) NULL,
        ToStatusCode NVARCHAR(20) NOT NULL,
        StatusNote NVARCHAR(500) NULL,
        ActionBy INT NOT NULL,
        ActionDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbVersionWorkflowHistory_ActionDate DEFAULT (SYSDATETIME()),
        CONSTRAINT CK_tblSbVersionWorkflowHistory_ActionCode CHECK (
            WorkflowActionCode IN (N'SUBMIT', N'APPROVE', N'LOCK', N'REOPEN', N'UNLOCK')
        ),
        CONSTRAINT CK_tblSbVersionWorkflowHistory_ToStatusCode CHECK (
            ToStatusCode IN (N'DRAFT', N'SUBMITTED', N'APPROVED', N'LOCKED')
        )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbVersionWorkflowHistory_VersionFiscalYear'
      AND parent_object_id = OBJECT_ID('dbo.tblSbVersionWorkflowHistory')
)
BEGIN
    ALTER TABLE dbo.tblSbVersionWorkflowHistory
    ADD CONSTRAINT FK_tblSbVersionWorkflowHistory_VersionFiscalYear
        FOREIGN KEY (VersionID, FiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbVersionWorkflowHistory')
      AND name = 'IX_tblSbVersionWorkflowHistory_Version'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbVersionWorkflowHistory_Version
        ON dbo.tblSbVersionWorkflowHistory (FiscalYearID, VersionID, ActionDate DESC, StrategicWorkflowHistoryID DESC);
END;
GO


/* Source: backend-php\config\sql\create_tblSbObjectiveCleanupStage.sql */
GO

IF OBJECT_ID('dbo.tblSbObjectiveCleanupStage', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblSbObjectiveCleanupStage (
        ObjectiveCleanupStageID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        FiscalYearID INT NULL,
        VoteCode NVARCHAR(20) NULL,
        StrategicObjectiveCode NVARCHAR(50) NULL,
        StrategicObjectiveText NVARCHAR(MAX) NULL,
        LegacyGoalID NVARCHAR(20) NULL,
        LegacyStrategicPillarID NVARCHAR(20) NULL,
        ProposedProgramCode NVARCHAR(50) NULL,
        ProposedSubProgramCode NVARCHAR(50) NULL,
        ProposedGoalCode NVARCHAR(50) NULL,
        CleanObjectiveText NVARCHAR(MAX) NULL,
        KeepFlag BIT NOT NULL CONSTRAINT DF_tblSbObjectiveCleanupStage_KeepFlag DEFAULT (1),
        ReviewStatusCode NVARCHAR(20) NOT NULL CONSTRAINT DF_tblSbObjectiveCleanupStage_ReviewStatusCode DEFAULT (N'PENDING'),
        ReviewNotes NVARCHAR(MAX) NULL,
        SourceTag NVARCHAR(100) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblSbObjectiveCleanupStage_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblSbObjectiveCleanupStage_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbObjectiveCleanupStage')
      AND name = 'IX_tblSbObjectiveCleanupStage_ReviewStatusCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbObjectiveCleanupStage_ReviewStatusCode
        ON dbo.tblSbObjectiveCleanupStage (ReviewStatusCode, KeepFlag, VoteCode);
END
GO


/* Source: backend-php\config\sql\alter_strategic_dimensions_add_standalone_source_tracking.sql */
GO

IF COL_LENGTH('dbo.tblSbOrgUnit', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOrgUnit', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
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

IF COL_LENGTH('dbo.tblSbFundingSource', 'FundingTypeID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD FundingTypeID INT NULL;
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbFundingSource_FundingTypeCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFundingSource')
)
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    DROP CONSTRAINT CK_tblSbFundingSource_FundingTypeCode;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSector', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSector
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbProgram', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbProgram', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbProgram', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbProgram', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbProgram
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSubProgram', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSubProgram', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSubProgram', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSubProgram', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSubProgram
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbEconomicItem', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbEconomicItem', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbEconomicItem', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbEconomicItem', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbEconomicItem
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSector', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSector
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSector', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSector
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbSector', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbSector
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbObjective', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicator', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicator
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicator', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicator
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicator', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicator
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicator', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicator
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbObjective', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbObjective', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbObjective', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbObjective
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicatorTarget', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicatorTarget', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicatorTarget', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbIndicatorTarget', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbIndicatorTarget
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOutput', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOutput', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOutput', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOutput', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOutput
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'SourceSegmentNo') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD SourceSegmentNo INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD SourceSegmentCode NVARCHAR(50) NULL;
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

MERGE dbo.tblSbFundingType AS target
USING (
    SELECT N'DOMESTIC' AS FundingTypeCode, N'Domestic' AS FundingTypeName
    UNION ALL SELECT N'GRANT', N'Grant'
    UNION ALL SELECT N'LOAN', N'Loan'
) AS src
ON target.FundingTypeCode = src.FundingTypeCode
WHEN MATCHED THEN
    UPDATE SET
        target.FundingTypeName = src.FundingTypeName,
        target.ActiveFlag = 1
WHEN NOT MATCHED THEN
    INSERT (
        FundingTypeCode,
        FundingTypeName,
        FundingTypeDescription,
        ActiveFlag,
        CreatedBy,
        CreatedDate
    )
    VALUES (
        src.FundingTypeCode,
        src.FundingTypeName,
        NULL,
        1,
        1,
        SYSDATETIME()
    );
GO

UPDATE fs
SET fs.FundingTypeID = ft.FundingTypeID
FROM dbo.tblSbFundingSource fs
INNER JOIN dbo.tblSbFundingType ft
    ON ft.FundingTypeCode = fs.FundingTypeCode
WHERE fs.FundingTypeID IS NULL
  AND fs.FundingTypeCode IS NOT NULL;
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
      AND name = 'IX_tblSbFundingSource_FundingTypeID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingSource_FundingTypeID
        ON dbo.tblSbFundingSource (FundingTypeID, ActiveFlag);
END
GO


/* Source: backend-php\config\sql\alter_tblSbFundingSource_add_source_segment.sql */
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbFundingSource', 'SourceSegmentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingSource
    ADD SourceSegmentCode NVARCHAR(50) NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingSource')
      AND name = 'UX_tblSbFundingSource_SourceFiscalYearID_SourceSegmentCode'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbFundingSource_SourceFiscalYearID_SourceSegmentCode
        ON dbo.tblSbFundingSource (SourceFiscalYearID, SourceSegmentCode)
        WHERE SourceFiscalYearID IS NOT NULL
          AND SourceSegmentCode IS NOT NULL;
END
GO


/* Source: backend-php\config\sql\alter_tblSbFundingType_add_default_phasing_profile.sql */
IF COL_LENGTH('dbo.tblSbFundingType', 'DefaultPhasingProfileID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFundingType
    ADD DefaultPhasingProfileID INT NULL;
END
GO

IF OBJECT_ID('dbo.tblSbPhasingProfile', 'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE name = 'FK_tblSbFundingType_DefaultPhasingProfile'
         AND parent_object_id = OBJECT_ID('dbo.tblSbFundingType')
   )
BEGIN
    ALTER TABLE dbo.tblSbFundingType
    ADD CONSTRAINT FK_tblSbFundingType_DefaultPhasingProfile
        FOREIGN KEY (DefaultPhasingProfileID)
        REFERENCES dbo.tblSbPhasingProfile (PhasingProfileID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbFundingType')
      AND name = 'IX_tblSbFundingType_DefaultPhasingProfileID'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFundingType_DefaultPhasingProfileID
        ON dbo.tblSbFundingType (DefaultPhasingProfileID);
END
GO


/* Source: backend-php\config\sql\alter_tblSbOrgUnit_add_dataobject_source.sql */
GO

/*
Link strategic org units to existing DataScope org units (tblDataObjectCodes)
so the strategic budgeting module can reuse the established hierarchy.
*/

IF COL_LENGTH('dbo.tblSbOrgUnit', 'SourceFiscalYearID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD SourceFiscalYearID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbOrgUnit', 'SourceDataObjectCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbOrgUnit
    ADD SourceDataObjectCode NVARCHAR(50) NULL;
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


/* Source: backend-php\config\sql\alter_tblSbResourceEnvelope_add_restriction_detail.sql */
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionScopeTypeCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionScopeTypeCode NVARCHAR(30) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionReference') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionReference NVARCHAR(100) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionDescription') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionDescription NVARCHAR(255) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedSectorID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedSectorID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedProgramID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedProgramID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedSubProgramID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedSubProgramID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedOrgUnitID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedOrgUnitID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedActivityID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedActivityID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedEconomicItemID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedEconomicItemID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedProjectReference') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedProjectReference NVARCHAR(100) NULL;
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'UX_tblSbResourceEnvelope_ContextFunding'
)
BEGIN
    DROP INDEX UX_tblSbResourceEnvelope_ContextFunding
        ON dbo.tblSbResourceEnvelope;
END
GO

CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbResourceEnvelope_ContextFunding
    ON dbo.tblSbResourceEnvelope (
        FiscalYearID,
        VersionID,
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        RestrictionScopeTypeCode,
        RestrictionReference,
        RestrictedSectorID,
        RestrictedProgramID,
        RestrictedSubProgramID,
        RestrictedOrgUnitID,
        RestrictedActivityID,
        RestrictedEconomicItemID,
        RestrictedProjectReference,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode
    )
    WHERE ActiveFlag = 1;
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'IX_tblSbResourceEnvelope_Context'
)
BEGIN
    DROP INDEX IX_tblSbResourceEnvelope_Context
        ON dbo.tblSbResourceEnvelope;
END
GO

CREATE NONCLUSTERED INDEX IX_tblSbResourceEnvelope_Context
    ON dbo.tblSbResourceEnvelope (FiscalYearID, VersionID, ActiveFlag)
    INCLUDE (
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        RestrictionScopeTypeCode,
        RestrictionReference,
        RestrictionDescription,
        RestrictedSectorID,
        RestrictedProgramID,
        RestrictedSubProgramID,
        RestrictedOrgUnitID,
        RestrictedActivityID,
        RestrictedEconomicItemID,
        RestrictedProjectReference,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode,
        CurrentYearAmount,
        OuterYear1Amount,
        OuterYear2Amount,
        OuterYear3Amount,
        OuterYear4Amount,
        OuterYear5Amount
    );
GO


/* Source: backend-php\config\sql\alter_tblSbResourceEnvelope_mtff_attributes_and_outyears.sql */
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'ReliabilityCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD ReliabilityCode NVARCHAR(30) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionCode NVARCHAR(30) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionScopeTypeCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionScopeTypeCode NVARCHAR(30) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionReference') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionReference NVARCHAR(100) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictionDescription') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictionDescription NVARCHAR(255) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedSectorID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedSectorID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedProgramID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedProgramID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedSubProgramID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedSubProgramID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedOrgUnitID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedOrgUnitID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedActivityID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedActivityID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedEconomicItemID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedEconomicItemID INT NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedProjectReference') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedProjectReference NVARCHAR(100) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'FinancingInstrumentCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD FinancingInstrumentCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'OuterYearAssumptionBasisCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD OuterYearAssumptionBasisCode NVARCHAR(50) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'OuterYear3Amount') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD OuterYear3Amount DECIMAL(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'OuterYear4Amount') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD OuterYear4Amount DECIMAL(19,6) NULL;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'OuterYear5Amount') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD OuterYear5Amount DECIMAL(19,6) NULL;
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'UX_tblSbResourceEnvelope_ContextFunding'
)
BEGIN
    DROP INDEX UX_tblSbResourceEnvelope_ContextFunding
        ON dbo.tblSbResourceEnvelope;
END
GO

CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbResourceEnvelope_ContextFunding
    ON dbo.tblSbResourceEnvelope (
        FiscalYearID,
        VersionID,
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        RestrictionScopeTypeCode,
        RestrictionReference,
        RestrictedSectorID,
        RestrictedProgramID,
        RestrictedSubProgramID,
        RestrictedOrgUnitID,
        RestrictedActivityID,
        RestrictedEconomicItemID,
        RestrictedProjectReference,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode
    )
    WHERE ActiveFlag = 1;
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'IX_tblSbResourceEnvelope_Context'
)
BEGIN
    DROP INDEX IX_tblSbResourceEnvelope_Context
        ON dbo.tblSbResourceEnvelope;
END
GO

CREATE NONCLUSTERED INDEX IX_tblSbResourceEnvelope_Context
    ON dbo.tblSbResourceEnvelope (FiscalYearID, VersionID, ActiveFlag)
    INCLUDE (
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        RestrictionScopeTypeCode,
        RestrictionReference,
        RestrictionDescription,
        RestrictedSectorID,
        RestrictedProgramID,
        RestrictedSubProgramID,
        RestrictedOrgUnitID,
        RestrictedActivityID,
        RestrictedEconomicItemID,
        RestrictedProjectReference,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode,
        CurrentYearAmount,
        OuterYear1Amount,
        OuterYear2Amount,
        OuterYear3Amount,
        OuterYear4Amount,
        OuterYear5Amount
    );
GO


/* Source: backend-php\config\sql\alter_tblSbResourceEnvelope_unique_index_mtff_dimensions.sql */
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'UX_tblSbResourceEnvelope_ContextFunding'
)
BEGIN
    DROP INDEX UX_tblSbResourceEnvelope_ContextFunding ON dbo.tblSbResourceEnvelope;
END
GO

CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbResourceEnvelope_ContextFunding
    ON dbo.tblSbResourceEnvelope (
        FiscalYearID,
        VersionID,
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode
    )
    WHERE ActiveFlag = 1;
GO


/* Source: backend-php\config\sql\alter_tblSbProject_expand_master_fields.sql */
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


/* Source: backend-php\config\sql\alter_tblSbResourceEnvelope_add_project_fk.sql */
GO

IF OBJECT_ID('dbo.tblSbResourceEnvelope', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbResourceEnvelope does not exist. Run create_tblSbResourceEnvelope.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbResourceEnvelope', 'RestrictedProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope ADD RestrictedProjectID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbResourceEnvelope_RestrictedProject'
      AND parent_object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
)
BEGIN
    ALTER TABLE dbo.tblSbResourceEnvelope
    ADD CONSTRAINT FK_tblSbResourceEnvelope_RestrictedProject
        FOREIGN KEY (RestrictedProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'UX_tblSbResourceEnvelope_ContextFunding'
)
BEGIN
    DROP INDEX UX_tblSbResourceEnvelope_ContextFunding ON dbo.tblSbResourceEnvelope;
END
GO

CREATE UNIQUE NONCLUSTERED INDEX UX_tblSbResourceEnvelope_ContextFunding
    ON dbo.tblSbResourceEnvelope (
        FiscalYearID,
        VersionID,
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        RestrictionScopeTypeCode,
        RestrictionReference,
        RestrictedSectorID,
        RestrictedProgramID,
        RestrictedSubProgramID,
        RestrictedOrgUnitID,
        RestrictedActivityID,
        RestrictedEconomicItemID,
        RestrictedProjectID,
        RestrictedProjectReference,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode
    )
    WHERE ActiveFlag = 1;
GO

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblSbResourceEnvelope')
      AND name = 'IX_tblSbResourceEnvelope_Context'
)
BEGIN
    DROP INDEX IX_tblSbResourceEnvelope_Context ON dbo.tblSbResourceEnvelope;
END
GO

CREATE NONCLUSTERED INDEX IX_tblSbResourceEnvelope_Context
    ON dbo.tblSbResourceEnvelope (FiscalYearID, VersionID, ActiveFlag)
    INCLUDE (
        FundingTypeID,
        FundingSourceID,
        ReliabilityCode,
        RestrictionCode,
        RestrictionScopeTypeCode,
        RestrictionReference,
        RestrictionDescription,
        RestrictedSectorID,
        RestrictedProgramID,
        RestrictedSubProgramID,
        RestrictedOrgUnitID,
        RestrictedActivityID,
        RestrictedEconomicItemID,
        RestrictedProjectID,
        RestrictedProjectReference,
        FinancingInstrumentCode,
        OuterYearAssumptionBasisCode,
        CurrentYearAmount,
        OuterYear1Amount,
        OuterYear2Amount,
        OuterYear3Amount,
        OuterYear4Amount,
        OuterYear5Amount
    );
GO


/* Source: backend-php\config\sql\alter_tblSbActivity_add_project.sql */
GO

IF OBJECT_ID('dbo.tblSbActivity', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbActivity does not exist. Run create_strategic_budgeting_module_v2.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbActivity', 'ProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbActivity ADD ProjectID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbActivity_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbActivity')
)
BEGIN
    ALTER TABLE dbo.tblSbActivity
    ADD CONSTRAINT FK_tblSbActivity_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbActivity_ProjectID_ActiveFlag'
      AND object_id = OBJECT_ID('dbo.tblSbActivity')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbActivity_ProjectID_ActiveFlag
        ON dbo.tblSbActivity (ProjectID, ActiveFlag);
END
GO


/* Source: backend-php\config\sql\alter_tblSbNarrative_add_project.sql */
GO

IF OBJECT_ID('dbo.tblSbNarrative', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbNarrative does not exist. Run create_strategic_budgeting_module_v2.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbNarrative', 'ProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbNarrative ADD ProjectID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbNarrative_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbNarrative')
)
BEGIN
    ALTER TABLE dbo.tblSbNarrative
    ADD CONSTRAINT FK_tblSbNarrative_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbNarrative_ProjectID_ActiveFlag'
      AND object_id = OBJECT_ID('dbo.tblSbNarrative')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbNarrative_ProjectID_ActiveFlag
        ON dbo.tblSbNarrative (ProjectID, ActiveFlag, FiscalYearID, VersionID);
END
GO


/* Source: backend-php\config\sql\alter_tblSbFiscalRisk_add_project.sql */
GO

IF OBJECT_ID('dbo.tblSbFiscalRisk', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbFiscalRisk does not exist. Run create_strategic_budgeting_module_v2.sql first.', 1;
END
GO

IF COL_LENGTH('dbo.tblSbFiscalRisk', 'ProjectID') IS NULL
BEGIN
    ALTER TABLE dbo.tblSbFiscalRisk ADD ProjectID INT NULL;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblSbFiscalRisk_Project'
      AND parent_object_id = OBJECT_ID('dbo.tblSbFiscalRisk')
)
BEGIN
    ALTER TABLE dbo.tblSbFiscalRisk
    ADD CONSTRAINT FK_tblSbFiscalRisk_Project
        FOREIGN KEY (ProjectID) REFERENCES dbo.tblSbProject (ProjectID);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE name = 'IX_tblSbFiscalRisk_ProjectID_ActiveFlag'
      AND object_id = OBJECT_ID('dbo.tblSbFiscalRisk')
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblSbFiscalRisk_ProjectID_ActiveFlag
        ON dbo.tblSbFiscalRisk (ProjectID, ActiveFlag);
END
GO


/* Source: backend-php\config\sql\alter_tblSbSegmentConfig_add_project.sql */
GO

IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbSegmentConfig does not exist. Run create_tblSbSegmentConfig.sql first.', 1;
END
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbSegmentConfig_StrategicDimensionCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentConfig
    DROP CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode;
END
GO

ALTER TABLE dbo.tblSbSegmentConfig
ADD CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode CHECK (
    StrategicDimensionCode IN (
        N'PROGRAM',
        N'SUBPROGRAM',
        N'PROJECT',
        N'SECTOR',
        N'ECONOMIC',
        N'FUNDING_TYPE',
        N'FUNDING_SOURCE',
        N'OBJECTIVE',
        N'INDICATOR',
        N'TARGET',
        N'ACTIVITY',
        N'OUTPUT'
    )
);
GO


/* Source: backend-php\config\sql\alter_tblSbSegmentConfig_add_objective_target_activity.sql */
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbSegmentConfig_StrategicDimensionCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentConfig
    DROP CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode;
END
GO

ALTER TABLE dbo.tblSbSegmentConfig
ADD CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode CHECK (
    StrategicDimensionCode IN (
        N'PROGRAM',
        N'SUBPROGRAM',
        N'SECTOR',
        N'ECONOMIC',
        N'FUNDING_TYPE',
        N'FUNDING_SOURCE',
        N'OBJECTIVE',
        N'INDICATOR',
        N'TARGET',
        N'ACTIVITY',
        N'OUTPUT'
    )
);
GO


/* Source: backend-php\config\sql\alter_tblSbSegmentConfig_add_indicator_output.sql */
IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbSegmentConfig does not exist. Run create_tblSbSegmentConfig.sql first.', 1;
END;
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbSegmentConfig_StrategicDimensionCode'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentConfig
    DROP CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode;
END
GO

ALTER TABLE dbo.tblSbSegmentConfig
ADD CONSTRAINT CK_tblSbSegmentConfig_StrategicDimensionCode CHECK (
    StrategicDimensionCode IN (
        N'PROGRAM',
        N'SUBPROGRAM',
        N'SECTOR',
        N'ECONOMIC',
        N'FUNDING_TYPE',
        N'FUNDING_SOURCE',
        N'OBJECTIVE',
        N'INDICATOR',
        N'TARGET',
        N'ACTIVITY',
        N'OUTPUT'
    )
);
GO


/* Source: backend-php\config\sql\alter_tblSbSegmentConfig_allow_explicit_not_mapped.sql */
IF OBJECT_ID('dbo.tblSbSegmentConfig', 'U') IS NULL
BEGIN
    THROW 50000, 'tblSbSegmentConfig does not exist. Run create_tblSbSegmentConfig.sql first.', 1;
END;
GO

IF EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblSbSegmentConfig_SegmentNo'
      AND parent_object_id = OBJECT_ID('dbo.tblSbSegmentConfig')
)
BEGIN
    ALTER TABLE dbo.tblSbSegmentConfig
    DROP CONSTRAINT CK_tblSbSegmentConfig_SegmentNo;
END
GO

ALTER TABLE dbo.tblSbSegmentConfig
ALTER COLUMN SegmentNo INT NULL;
GO

ALTER TABLE dbo.tblSbSegmentConfig
ADD CONSTRAINT CK_tblSbSegmentConfig_SegmentNo
CHECK (SegmentNo IS NULL OR SegmentNo BETWEEN 1 AND 20);
GO


/* Source: backend-php\config\sql\create_strategic_budgeting_reporting_views.sql */
GO

/*
Strategic Budgeting Reporting Views
----------------------------------
Read-only reporting views over the tblSb* strategic budgeting schema.
These are safe to recreate and are intended to support PHP list/report pages.
*/

CREATE OR ALTER VIEW dbo.vwSbActivityBudgetDetail
AS
SELECT
    ab.ActivityBudgetID,
    ab.VersionID,
    ab.FiscalYearID,
    ab.ActivityID,
    act.ActivityName,
    act.ActivityTypeCode,
    act.ImplementationStatusCode,
    act.ProjectID,
    prj.ProjectCode,
    prj.ProjectName,
    outp.OutputID,
    outp.OutputName,
    prg.ProgramID,
    prg.ProgramCode,
    prg.ProgramName,
    sp.SubProgramID,
    sp.SubProgramCode,
    sp.SubProgramName,
    sec.SectorID,
    sec.SectorName,
    ou.OrgUnitID,
    ou.OrgUnitName,
    ou.OrgUnitTypeCode,
    ei.EconomicItemID,
    ei.EconomicCode,
    ei.EconomicName,
    fs.FundingSourceID,
    fs.FundingSourceName,
    fs.FundingTypeCode,
    ab.Amount,
    ab.CurrencyCode,
    ab.Notes,
    ab.ActiveFlag
FROM dbo.tblSbActivityBudget ab
INNER JOIN dbo.tblSbActivity act
    ON act.ActivityID = ab.ActivityID
INNER JOIN dbo.tblSbOutput outp
    ON outp.OutputID = act.OutputID
INNER JOIN dbo.tblSbProgram prg
    ON prg.ProgramID = outp.ProgramID
LEFT JOIN dbo.tblSbProject prj
    ON prj.ProjectID = act.ProjectID
LEFT JOIN dbo.tblSbSubProgram sp
    ON sp.SubProgramID = outp.SubProgramID
INNER JOIN dbo.tblSbSector sec
    ON sec.SectorID = prg.SectorID
INNER JOIN dbo.tblSbOrgUnit ou
    ON ou.OrgUnitID = prg.OrgUnitID
INNER JOIN dbo.tblSbEconomicItem ei
    ON ei.EconomicItemID = ab.EconomicItemID
LEFT JOIN dbo.tblSbFundingSource fs
    ON fs.FundingSourceID = ab.FundingSourceID
WHERE ab.ActiveFlag = 1
  AND act.ActiveFlag = 1
  AND outp.ActiveFlag = 1
  AND prg.ActiveFlag = 1
  AND sec.ActiveFlag = 1
  AND ou.ActiveFlag = 1
  AND ei.ActiveFlag = 1;
GO

CREATE OR ALTER VIEW dbo.vwSbBudgetBySector
AS
SELECT
    d.VersionID,
    d.FiscalYearID,
    d.SectorID,
    d.SectorName,
    COUNT(DISTINCT d.ProgramID) AS ProgramCount,
    COUNT(DISTINCT d.OutputID) AS OutputCount,
    COUNT(DISTINCT d.ActivityID) AS ActivityCount,
    SUM(d.Amount) AS TotalAmount
FROM dbo.vwSbActivityBudgetDetail d
GROUP BY
    d.VersionID,
    d.FiscalYearID,
    d.SectorID,
    d.SectorName;
GO

CREATE OR ALTER VIEW dbo.vwSbBudgetByProgram
AS
SELECT
    d.VersionID,
    d.FiscalYearID,
    d.ProgramID,
    d.ProgramCode,
    d.ProgramName,
    d.SectorID,
    d.SectorName,
    d.OrgUnitID,
    d.OrgUnitName,
    COUNT(DISTINCT d.OutputID) AS OutputCount,
    COUNT(DISTINCT d.ActivityID) AS ActivityCount,
    SUM(d.Amount) AS TotalAmount
FROM dbo.vwSbActivityBudgetDetail d
GROUP BY
    d.VersionID,
    d.FiscalYearID,
    d.ProgramID,
    d.ProgramCode,
    d.ProgramName,
    d.SectorID,
    d.SectorName,
    d.OrgUnitID,
    d.OrgUnitName;
GO

CREATE OR ALTER VIEW dbo.vwSbBudgetByEconomicItem
AS
SELECT
    d.VersionID,
    d.FiscalYearID,
    d.EconomicItemID,
    d.EconomicCode,
    d.EconomicName,
    SUM(d.Amount) AS TotalAmount
FROM dbo.vwSbActivityBudgetDetail d
GROUP BY
    d.VersionID,
    d.FiscalYearID,
    d.EconomicItemID,
    d.EconomicCode,
    d.EconomicName;
GO

CREATE OR ALTER VIEW dbo.vwSbBudgetByProject
AS
SELECT
    d.VersionID,
    d.FiscalYearID,
    d.ProjectID,
    d.ProjectCode,
    d.ProjectName,
    COUNT(DISTINCT d.ProgramID) AS ProgramCount,
    COUNT(DISTINCT d.OutputID) AS OutputCount,
    COUNT(DISTINCT d.ActivityID) AS ActivityCount,
    COUNT(DISTINCT d.EconomicItemID) AS EconomicLines,
    COUNT(DISTINCT d.FundingSourceID) AS FundingSourceCount,
    SUM(d.Amount) AS TotalAmount
FROM dbo.vwSbActivityBudgetDetail d
WHERE d.ProjectID IS NOT NULL
GROUP BY
    d.VersionID,
    d.FiscalYearID,
    d.ProjectID,
    d.ProjectCode,
    d.ProjectName;
GO
