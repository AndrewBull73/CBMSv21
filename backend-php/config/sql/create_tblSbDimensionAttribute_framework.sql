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
