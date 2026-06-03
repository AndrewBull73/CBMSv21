/*
Formalize version design for CBMSv21 using the existing dbo.tblVersions table.

Key decisions:
- Reuse dbo.tblVersions for both submission and execution versions.
- Use VersionTypeID to distinguish submission vs execution.
- Use BaseFiscalYearID + BaseVersionID to link an execution version back to
  the approved submission version it was opened from.
- Allow one default version per fiscal year per version type, not one generic
  default across all version rows in a fiscal year.
*/

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
SET ANSI_PADDING ON;
SET ANSI_WARNINGS ON;
SET ARITHABORT ON;
SET CONCAT_NULL_YIELDS_NULL ON;
SET NUMERIC_ROUNDABORT OFF;

IF OBJECT_ID('dbo.tblVersions', 'U') IS NULL
BEGIN
    THROW 50020, 'dbo.tblVersions must exist before alter_tblVersions_budget_versioning_v1.sql can run.', 1;
END;

IF COL_LENGTH('dbo.tblVersions', 'VersionTypeID') IS NULL
BEGIN
    THROW 50021, 'dbo.tblVersions.VersionTypeID must exist before alter_tblVersions_budget_versioning_v1.sql can run.', 1;
END;

IF OBJECT_ID('dbo.tblVersionTypes', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblVersionTypes (
        VersionTypeID INT NOT NULL PRIMARY KEY,
        VersionTypeCode NVARCHAR(30) NOT NULL,
        VersionTypeName NVARCHAR(100) NOT NULL,
        Description NVARCHAR(255) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblVersionTypes_ActiveFlag DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblVersionTypes_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblVersionTypes_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblVersionTypes')
      AND name = 'UX_tblVersionTypes_Code'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblVersionTypes_Code
        ON dbo.tblVersionTypes (VersionTypeCode);
END;

MERGE dbo.tblVersionTypes AS target
USING (
    SELECT 1 AS VersionTypeID, N'SUBMISSION' AS VersionTypeCode, N'Budget Submission' AS VersionTypeName, N'Used for draft, review, approved, and published submission versions.' AS Description
    UNION ALL
    SELECT 2, N'EXECUTION', N'Budget Execution', N'Used for the live execution version linked to an approved submission baseline.'
) AS source
    ON target.VersionTypeID = source.VersionTypeID
WHEN MATCHED THEN
    UPDATE SET
        VersionTypeCode = source.VersionTypeCode,
        VersionTypeName = source.VersionTypeName,
        Description = source.Description,
        ActiveFlag = 1,
        UpdatedDate = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT (VersionTypeID, VersionTypeCode, VersionTypeName, Description, ActiveFlag, CreatedDate, UpdatedDate)
    VALUES (source.VersionTypeID, source.VersionTypeCode, source.VersionTypeName, source.Description, 1, SYSDATETIME(), SYSDATETIME());

UPDATE dbo.tblVersions
SET VersionTypeID = 1
WHERE VersionTypeID IS NULL;

UPDATE dbo.tblVersions
SET VersionStatus = N'DRAFT'
WHERE NULLIF(LTRIM(RTRIM(ISNULL(VersionStatus, N''))), N'') IS NULL;

UPDATE dbo.tblVersions
SET CeilingsOn = 0
WHERE CeilingsOn IS NULL;

UPDATE dbo.tblVersions
SET IsDefault = 0
WHERE IsDefault IS NULL;

IF EXISTS (
    SELECT 1
    FROM sys.columns
    WHERE object_id = OBJECT_ID('dbo.tblVersions')
      AND name = 'VersionTypeID'
      AND is_nullable = 1
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE object_id = OBJECT_ID('dbo.tblVersions')
          AND name = 'UX_tblVersions_DefaultPerFYType'
    )
    BEGIN
        DROP INDEX UX_tblVersions_DefaultPerFYType
            ON dbo.tblVersions;
    END;

    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE object_id = OBJECT_ID('dbo.tblVersions')
          AND name = 'IX_tblVersions_FY_Type_Active'
    )
    BEGIN
        DROP INDEX IX_tblVersions_FY_Type_Active
            ON dbo.tblVersions;
    END;

    IF EXISTS (
        SELECT 1
        FROM sys.indexes
        WHERE object_id = OBJECT_ID('dbo.tblVersions')
          AND name = 'IX_tblVersions_BaseVersion'
    )
    BEGIN
        DROP INDEX IX_tblVersions_BaseVersion
            ON dbo.tblVersions;
    END;

    ALTER TABLE dbo.tblVersions
    ALTER COLUMN VersionTypeID INT NOT NULL;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
       AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID('dbo.tblVersions')
      AND c.name = 'VersionStatus'
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT DF_tblVersions_VersionStatus
        DEFAULT (N'DRAFT') FOR VersionStatus;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
       AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID('dbo.tblVersions')
      AND c.name = 'CeilingsOn'
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT DF_tblVersions_CeilingsOn
        DEFAULT ((0)) FOR CeilingsOn;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints dc
    INNER JOIN sys.columns c
        ON c.object_id = dc.parent_object_id
       AND c.column_id = dc.parent_column_id
    WHERE dc.parent_object_id = OBJECT_ID('dbo.tblVersions')
      AND c.name = 'IsDefault'
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT DF_tblVersions_IsDefault
        DEFAULT ((0)) FOR IsDefault;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblVersions_VersionType'
      AND parent_object_id = OBJECT_ID('dbo.tblVersions')
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT FK_tblVersions_VersionType
        FOREIGN KEY (VersionTypeID)
        REFERENCES dbo.tblVersionTypes (VersionTypeID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.check_constraints
    WHERE name = 'CK_tblVersions_BaseVersionPair'
      AND parent_object_id = OBJECT_ID('dbo.tblVersions')
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT CK_tblVersions_BaseVersionPair CHECK (
        (BaseFiscalYearID IS NULL AND BaseVersionID IS NULL)
        OR
        (BaseFiscalYearID IS NOT NULL AND BaseVersionID IS NOT NULL)
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblVersions_BaseVersion'
      AND parent_object_id = OBJECT_ID('dbo.tblVersions')
)
BEGIN
    ALTER TABLE dbo.tblVersions
    ADD CONSTRAINT FK_tblVersions_BaseVersion
        FOREIGN KEY (BaseVersionID, BaseFiscalYearID)
        REFERENCES dbo.tblVersions (VersionID, FiscalYearID);
END;

IF EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblVersions')
      AND name = 'UX_tblVersions_DefaultPerFY'
)
BEGIN
    DROP INDEX UX_tblVersions_DefaultPerFY
        ON dbo.tblVersions;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblVersions')
      AND name = 'UX_tblVersions_DefaultPerFYType'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblVersions_DefaultPerFYType
        ON dbo.tblVersions (FiscalYearID, VersionTypeID)
        WHERE IsDefault = 1
          AND IsActive = 1
          AND VersionTypeID IS NOT NULL;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblVersions')
      AND name = 'IX_tblVersions_FY_Type_Active'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblVersions_FY_Type_Active
        ON dbo.tblVersions (FiscalYearID, VersionTypeID, IsActive, IsDefault, VersionID);
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblVersions')
      AND name = 'IX_tblVersions_BaseVersion'
)
BEGIN
    CREATE NONCLUSTERED INDEX IX_tblVersions_BaseVersion
        ON dbo.tblVersions (BaseFiscalYearID, BaseVersionID, VersionTypeID, IsActive, VersionID);
END;
