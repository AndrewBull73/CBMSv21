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
