USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblTransactionResultPeriod', 'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[tblTransactionResultPeriod](
        [TransactionResultPeriodID] [int] IDENTITY(1,1) NOT NULL,
        [TransactionResultID] [int] NOT NULL,
        [TransactionID] [int] NOT NULL,
        [PeriodCode] [nvarchar](20) NOT NULL,
        [Amount] [decimal](19,6) NOT NULL,
        [CalculatedDate] [datetime] NOT NULL,
        CONSTRAINT [PK_tblTransactionResultPeriod] PRIMARY KEY CLUSTERED
        (
            [TransactionResultPeriodID] ASC
        )
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblTransactionResultPeriod_tblTransactionResult'
      AND parent_object_id = OBJECT_ID('dbo.tblTransactionResultPeriod')
)
BEGIN
    ALTER TABLE [dbo].[tblTransactionResultPeriod]
    ADD CONSTRAINT [FK_tblTransactionResultPeriod_tblTransactionResult]
        FOREIGN KEY ([TransactionResultID])
        REFERENCES [dbo].[tblTransactionResult]([TransactionResultID])
        ON DELETE CASCADE;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblTransactionResultPeriod_tblTransactionInput'
      AND parent_object_id = OBJECT_ID('dbo.tblTransactionResultPeriod')
)
BEGIN
    ALTER TABLE [dbo].[tblTransactionResultPeriod]
    ADD CONSTRAINT [FK_tblTransactionResultPeriod_tblTransactionInput]
        FOREIGN KEY ([TransactionID])
        REFERENCES [dbo].[tblTransactionInput]([TransactionID]);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints
    WHERE name = 'DF_tblTransactionResultPeriod_CalculatedDate'
      AND parent_object_id = OBJECT_ID('dbo.tblTransactionResultPeriod')
)
BEGIN
    ALTER TABLE [dbo].[tblTransactionResultPeriod]
    ADD CONSTRAINT [DF_tblTransactionResultPeriod_CalculatedDate]
        DEFAULT (GETDATE()) FOR [CalculatedDate];
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblTransactionResultPeriod')
      AND name = 'UQ_tblTransactionResultPeriod_Result_Period'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX [UQ_tblTransactionResultPeriod_Result_Period]
        ON [dbo].[tblTransactionResultPeriod]([TransactionResultID], [PeriodCode]);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblTransactionResultPeriod')
      AND name = 'IX_tblTransactionResultPeriod_TransactionID_PeriodCode'
)
BEGIN
    CREATE NONCLUSTERED INDEX [IX_tblTransactionResultPeriod_TransactionID_PeriodCode]
        ON [dbo].[tblTransactionResultPeriod]([TransactionID], [PeriodCode], [CalculatedDate] DESC)
        INCLUDE ([Amount], [TransactionResultID]);
END
GO
