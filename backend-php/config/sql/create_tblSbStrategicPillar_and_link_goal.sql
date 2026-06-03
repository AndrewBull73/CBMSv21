USE [CBMSv2];
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
