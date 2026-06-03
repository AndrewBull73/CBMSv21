IF COL_LENGTH('dbo.tblSegments', 'UsedInFinancialAccount') IS NULL
BEGIN
    ALTER TABLE dbo.tblSegments
    ADD UsedInFinancialAccount BIT NOT NULL
        CONSTRAINT DF_tblSegments_UsedInFinancialAccount DEFAULT (0);
END;

IF COL_LENGTH('dbo.tblSegments', 'UsedInStrategicPlanning') IS NULL
BEGIN
    ALTER TABLE dbo.tblSegments
    ADD UsedInStrategicPlanning BIT NOT NULL
        CONSTRAINT DF_tblSegments_UsedInStrategicPlanning DEFAULT (0);
END;
