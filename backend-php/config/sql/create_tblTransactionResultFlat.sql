USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblTransactionResultFlat', 'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[tblTransactionResultFlat](
        [TransactionResultFlatID] [int] IDENTITY(1,1) NOT NULL,
        [TransactionResultID] [int] NOT NULL,
        [TransactionID] [int] NOT NULL,
        [HeadRecordID] [int] NULL,
        [RecordTypeCode] [char](2) NULL,
        [FiscalYearID] [int] NULL,
        [VersionID] [int] NULL,
        [DataObjectCode] [nvarchar](20) NULL,
        [TransactionTypeCode] [nvarchar](20) NULL,
        [AccountCode] [nvarchar](200) NULL,
        [GLAccountCode] [nvarchar](20) NULL,
        [CostItemID] [int] NULL,
        [CalculationID] [int] NULL,
        [CurrencyInpC] [nvarchar](20) NULL,
        [UOMCodeInpC] [nvarchar](20) NULL,
        [Segment1Code] [nvarchar](20) NULL,
        [Segment2Code] [nvarchar](20) NULL,
        [Segment3Code] [nvarchar](20) NULL,
        [Segment4Code] [nvarchar](20) NULL,
        [Segment5Code] [nvarchar](20) NULL,
        [Segment6Code] [nvarchar](20) NULL,
        [Segment7Code] [nvarchar](20) NULL,
        [Segment8Code] [nvarchar](20) NULL,
        [Segment9Code] [nvarchar](20) NULL,
        [Segment10Code] [nvarchar](20) NULL,
        [Segment11Code] [nvarchar](20) NULL,
        [Segment12Code] [nvarchar](20) NULL,
        [Segment13Code] [nvarchar](20) NULL,
        [Segment14Code] [nvarchar](20) NULL,
        [Segment15Code] [nvarchar](20) NULL,
        [Segment16Code] [nvarchar](20) NULL,
        [Segment17Code] [nvarchar](20) NULL,
        [Segment18Code] [nvarchar](20) NULL,
        [Segment19Code] [nvarchar](20) NULL,
        [Segment20Code] [nvarchar](20) NULL,
        [BP1] [decimal](19,6) NOT NULL,
        [BP2] [decimal](19,6) NOT NULL,
        [BP3] [decimal](19,6) NOT NULL,
        [BP4] [decimal](19,6) NOT NULL,
        [BP5] [decimal](19,6) NOT NULL,
        [BP6] [decimal](19,6) NOT NULL,
        [BP7] [decimal](19,6) NOT NULL,
        [BP8] [decimal](19,6) NOT NULL,
        [BP9] [decimal](19,6) NOT NULL,
        [BP10] [decimal](19,6) NOT NULL,
        [BP11] [decimal](19,6) NOT NULL,
        [BP12] [decimal](19,6) NOT NULL,
        [BPTotal] [decimal](19,6) NOT NULL,
        [PY5] [decimal](19,6) NULL,
        [PY4] [decimal](19,6) NULL,
        [PY3] [decimal](19,6) NULL,
        [PY2] [decimal](19,6) NULL,
        [PY1] [decimal](19,6) NULL,
        [BPOpBal] [decimal](19,6) NULL,
        [BPQ1] [decimal](19,6) NULL,
        [BPQ2] [decimal](19,6) NULL,
        [BPQ3] [decimal](19,6) NULL,
        [BPQ4] [decimal](19,6) NULL,
        [BPOY1] [decimal](19,6) NULL,
        [BPOY2] [decimal](19,6) NULL,
        [BPOY3] [decimal](19,6) NULL,
        [BPOY4] [decimal](19,6) NULL,
        [BPOY5] [decimal](19,6) NULL,
        [BPOY6] [decimal](19,6) NULL,
        [BPOY7] [decimal](19,6) NULL,
        [BPOY8] [decimal](19,6) NULL,
        [BPOY9] [decimal](19,6) NULL,
        [BPOY10] [decimal](19,6) NULL,
        [CalculatedDate] [datetime] NOT NULL,
        CONSTRAINT [PK_tblTransactionResultFlat] PRIMARY KEY CLUSTERED
        (
            [TransactionResultFlatID] ASC
        )
    );
END
GO

IF COL_LENGTH('dbo.tblTransactionResultFlat', 'HeadRecordID') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [HeadRecordID] [int] NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'RecordTypeCode') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [RecordTypeCode] [char](2) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'FiscalYearID') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [FiscalYearID] [int] NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'VersionID') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [VersionID] [int] NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'DataObjectCode') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [DataObjectCode] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'TransactionTypeCode') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [TransactionTypeCode] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'AccountCode') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [AccountCode] [nvarchar](200) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'GLAccountCode') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [GLAccountCode] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'CostItemID') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [CostItemID] [int] NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'CalculationID') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [CalculationID] [int] NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'CurrencyInpC') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [CurrencyInpC] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'UOMCodeInpC') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [UOMCodeInpC] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment1Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment1Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment2Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment2Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment3Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment3Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment4Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment4Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment5Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment5Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment6Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment6Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment7Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment7Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment8Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment8Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment9Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment9Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment10Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment10Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment11Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment11Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment12Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment12Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment13Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment13Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment14Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment14Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment15Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment15Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment16Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment16Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment17Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment17Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment18Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment18Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment19Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment19Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'Segment20Code') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [Segment20Code] [nvarchar](20) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'PY5') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [PY5] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'PY4') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [PY4] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'PY3') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [PY3] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'PY2') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [PY2] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'PY1') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [PY1] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOpBal') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOpBal] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPQ1') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPQ1] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPQ2') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPQ2] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPQ3') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPQ3] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPQ4') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPQ4] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY1') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY1] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY2') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY2] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY3') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY3] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY4') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY4] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY5') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY5] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY6') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY6] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY7') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY7] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY8') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY8] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY9') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY9] [decimal](19,6) NULL;
IF COL_LENGTH('dbo.tblTransactionResultFlat', 'BPOY10') IS NULL
    ALTER TABLE [dbo].[tblTransactionResultFlat] ADD [BPOY10] [decimal](19,6) NULL;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblTransactionResultFlat_tblTransactionResult'
      AND parent_object_id = OBJECT_ID('dbo.tblTransactionResultFlat')
)
BEGIN
    ALTER TABLE [dbo].[tblTransactionResultFlat]
    ADD CONSTRAINT [FK_tblTransactionResultFlat_tblTransactionResult]
        FOREIGN KEY ([TransactionResultID])
        REFERENCES [dbo].[tblTransactionResult]([TransactionResultID])
        ON DELETE CASCADE;
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblTransactionResultFlat_tblTransactionInput'
      AND parent_object_id = OBJECT_ID('dbo.tblTransactionResultFlat')
)
BEGIN
    ALTER TABLE [dbo].[tblTransactionResultFlat]
    ADD CONSTRAINT [FK_tblTransactionResultFlat_tblTransactionInput]
        FOREIGN KEY ([TransactionID])
        REFERENCES [dbo].[tblTransactionInput]([TransactionID]);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints
    WHERE name = 'DF_tblTransactionResultFlat_CalculatedDate'
      AND parent_object_id = OBJECT_ID('dbo.tblTransactionResultFlat')
)
BEGIN
    ALTER TABLE [dbo].[tblTransactionResultFlat]
    ADD CONSTRAINT [DF_tblTransactionResultFlat_CalculatedDate]
        DEFAULT (GETDATE()) FOR [CalculatedDate];
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblTransactionResultFlat')
      AND name = 'UQ_tblTransactionResultFlat_Result'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX [UQ_tblTransactionResultFlat_Result]
        ON [dbo].[tblTransactionResultFlat]([TransactionResultID]);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblTransactionResultFlat')
      AND name = 'IX_tblTransactionResultFlat_Context'
)
BEGIN
    CREATE NONCLUSTERED INDEX [IX_tblTransactionResultFlat_Context]
        ON [dbo].[tblTransactionResultFlat]([FiscalYearID], [VersionID], [DataObjectCode], [TransactionTypeCode], [CalculatedDate] DESC)
        INCLUDE ([TransactionID], [TransactionResultID], [BPTotal]);
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblTransactionResultFlat')
      AND name = 'IX_tblTransactionResultFlat_TransactionID_CalculatedDate'
)
BEGIN
    CREATE NONCLUSTERED INDEX [IX_tblTransactionResultFlat_TransactionID_CalculatedDate]
        ON [dbo].[tblTransactionResultFlat]([TransactionID], [CalculatedDate] DESC)
        INCLUDE ([TransactionResultID], [BPTotal], [BP1], [BP2], [BP3], [BP4], [BP5], [BP6], [BP7], [BP8], [BP9], [BP10], [BP11], [BP12]);
END
GO
