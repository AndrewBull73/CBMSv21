USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblCeilingDefinition', 'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[tblCeilingDefinition](
        [CeilingDefinitionID] [int] IDENTITY(1,1) NOT NULL,
        [FiscalYearID] [int] NOT NULL,
        [VersionID] [int] NOT NULL,
        [DataObjectCode] [nvarchar](20) NULL,
        [TransactionTypeCode] [nvarchar](20) NULL,
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
        [CeilingBP1] [decimal](19,6) NOT NULL,
        [CeilingBP2] [decimal](19,6) NOT NULL,
        [CeilingBP3] [decimal](19,6) NOT NULL,
        [CeilingBP4] [decimal](19,6) NOT NULL,
        [CeilingBP5] [decimal](19,6) NOT NULL,
        [CeilingBP6] [decimal](19,6) NOT NULL,
        [CeilingBP7] [decimal](19,6) NOT NULL,
        [CeilingBP8] [decimal](19,6) NOT NULL,
        [CeilingBP9] [decimal](19,6) NOT NULL,
        [CeilingBP10] [decimal](19,6) NOT NULL,
        [CeilingBP11] [decimal](19,6) NOT NULL,
        [CeilingBP12] [decimal](19,6) NOT NULL,
        [CeilingBPTotal] [decimal](19,6) NOT NULL,
        [CeilingStatus] [nvarchar](20) NULL,
        [ApprovedFlag] [bit] NOT NULL,
        [ActiveFlag] [bit] NOT NULL,
        [Priority] [int] NOT NULL,
        [CreatedBy] [int] NOT NULL,
        [CreatedDate] [datetime] NOT NULL,
        [UpdatedBy] [int] NULL,
        [UpdatedDate] [datetime] NULL,
        CONSTRAINT [PK_tblCeilingDefinition] PRIMARY KEY CLUSTERED ([CeilingDefinitionID] ASC)
    );
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.default_constraints
    WHERE name = 'DF_tblCeilingDefinition_CeilingBP1'
      AND parent_object_id = OBJECT_ID('dbo.tblCeilingDefinition')
)
BEGIN
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP1] DEFAULT ((0)) FOR [CeilingBP1];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP2] DEFAULT ((0)) FOR [CeilingBP2];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP3] DEFAULT ((0)) FOR [CeilingBP3];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP4] DEFAULT ((0)) FOR [CeilingBP4];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP5] DEFAULT ((0)) FOR [CeilingBP5];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP6] DEFAULT ((0)) FOR [CeilingBP6];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP7] DEFAULT ((0)) FOR [CeilingBP7];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP8] DEFAULT ((0)) FOR [CeilingBP8];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP9] DEFAULT ((0)) FOR [CeilingBP9];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP10] DEFAULT ((0)) FOR [CeilingBP10];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP11] DEFAULT ((0)) FOR [CeilingBP11];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBP12] DEFAULT ((0)) FOR [CeilingBP12];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CeilingBPTotal] DEFAULT ((0)) FOR [CeilingBPTotal];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_ApprovedFlag] DEFAULT ((0)) FOR [ApprovedFlag];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_ActiveFlag] DEFAULT ((1)) FOR [ActiveFlag];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_Priority] DEFAULT ((100)) FOR [Priority];
    ALTER TABLE [dbo].[tblCeilingDefinition] ADD CONSTRAINT [DF_tblCeilingDefinition_CreatedDate] DEFAULT (GETDATE()) FOR [CreatedDate];
END
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCeilingDefinition')
      AND name = 'IX_tblCeilingDefinition_Lookup'
)
BEGIN
    CREATE NONCLUSTERED INDEX [IX_tblCeilingDefinition_Lookup]
        ON [dbo].[tblCeilingDefinition]([FiscalYearID], [VersionID], [ActiveFlag], [ApprovedFlag], [Priority])
        INCLUDE (
            [DataObjectCode], [TransactionTypeCode],
            [Segment1Code], [Segment2Code], [Segment3Code], [Segment4Code], [Segment5Code],
            [Segment6Code], [Segment7Code], [Segment8Code], [Segment9Code], [Segment10Code],
            [Segment11Code], [Segment12Code], [Segment13Code], [Segment14Code], [Segment15Code],
            [Segment16Code], [Segment17Code], [Segment18Code], [Segment19Code], [Segment20Code],
            [CeilingBPTotal]
        );
END
GO
