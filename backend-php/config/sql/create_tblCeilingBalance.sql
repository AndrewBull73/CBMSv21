USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblCeilingBalance', 'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[tblCeilingBalance](
        [CeilingBalanceID] [int] IDENTITY(1,1) NOT NULL,
        [CeilingDefinitionID] [int] NOT NULL,
        [FiscalYearID] [int] NOT NULL,
        [VersionID] [int] NOT NULL,
        [BalanceBP1] [decimal](19,6) NOT NULL,
        [BalanceBP2] [decimal](19,6) NOT NULL,
        [BalanceBP3] [decimal](19,6) NOT NULL,
        [BalanceBP4] [decimal](19,6) NOT NULL,
        [BalanceBP5] [decimal](19,6) NOT NULL,
        [BalanceBP6] [decimal](19,6) NOT NULL,
        [BalanceBP7] [decimal](19,6) NOT NULL,
        [BalanceBP8] [decimal](19,6) NOT NULL,
        [BalanceBP9] [decimal](19,6) NOT NULL,
        [BalanceBP10] [decimal](19,6) NOT NULL,
        [BalanceBP11] [decimal](19,6) NOT NULL,
        [BalanceBP12] [decimal](19,6) NOT NULL,
        [BalanceBPTotal] [decimal](19,6) NOT NULL,
        [LastTransactionID] [int] NULL,
        [UpdatedBy] [int] NULL,
        [UpdatedDate] [datetime] NOT NULL,
        [RowVer] [rowversion] NOT NULL,
        CONSTRAINT [PK_tblCeilingBalance] PRIMARY KEY CLUSTERED ([CeilingBalanceID] ASC)
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCeilingBalance_tblCeilingDefinition'
      AND parent_object_id = OBJECT_ID('dbo.tblCeilingBalance')
)
BEGIN
    ALTER TABLE [dbo].[tblCeilingBalance]
    ADD CONSTRAINT [FK_tblCeilingBalance_tblCeilingDefinition]
        FOREIGN KEY ([CeilingDefinitionID])
        REFERENCES [dbo].[tblCeilingDefinition]([CeilingDefinitionID])
        ON DELETE CASCADE;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints
    WHERE name = 'DF_tblCeilingBalance_UpdatedDate'
      AND parent_object_id = OBJECT_ID('dbo.tblCeilingBalance')
)
BEGIN
    ALTER TABLE [dbo].[tblCeilingBalance] ADD CONSTRAINT [DF_tblCeilingBalance_UpdatedDate] DEFAULT (GETDATE()) FOR [UpdatedDate];
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCeilingBalance')
      AND name = 'UQ_tblCeilingBalance_Definition'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX [UQ_tblCeilingBalance_Definition]
        ON [dbo].[tblCeilingBalance]([CeilingDefinitionID]);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCeilingBalance')
      AND name = 'IX_tblCeilingBalance_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX [IX_tblCeilingBalance_Context]
        ON [dbo].[tblCeilingBalance]([FiscalYearID], [VersionID], [CeilingDefinitionID])
        INCLUDE ([BalanceBPTotal], [UpdatedDate], [LastTransactionID]);
END
GO
