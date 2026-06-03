USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbGoal', 'U') IS NULL
BEGIN
    THROW 50000, 'Run create_tblSbGoal_and_ObjectiveGoal.sql first.', 1;
END
GO

MERGE dbo.tblSbGoal AS target
USING (
    SELECT N'SDG2' AS GoalCode, N'SDG2: End hunger, achieve food security and improved nutrition and promote sustainable Agriculture' AS GoalName, N'SDG' AS GoalTypeCode UNION ALL
    SELECT N'SDG3', N'SDG3: Ensure healthy lives and promote wellbeing for all at all ages.', N'SDG' UNION ALL
    SELECT N'SDG4', N'SDG4: Ensure inclusive and equitable quality education and promote lifelong learning opportunities for all.', N'SDG' UNION ALL
    SELECT N'SDG7', N'SDG7: Ensure access to affordable, reliable, sustainable and modern energy for all.', N'SDG' UNION ALL
    SELECT N'SDG8', N'SDG8: Promote sustained, inclusive and sustainable economic growth, full and productive employment and decent work for all.', N'SDG' UNION ALL
    SELECT N'SDG9', N'SDG9: Build resilient infrastructure, promote inclusive and sustainable industrialisation and foster innovation.', N'SDG' UNION ALL
    SELECT N'SDG12', N'SDG12: Ensure sustainable consumption and production patterns.', N'SDG' UNION ALL
    SELECT N'SDG16', N'SDG16: Promote peaceful and inclusive societies for sustainable development, provide access to justice for all and build effective, accountable and inclusive institutions at all levels.', N'SDG' UNION ALL
    SELECT N'SDG17', N'SDG17: Strengthen the means of implementation and the Global Partnership for Sustainable Development.', N'SDG'
) AS src
ON target.GoalCode = src.GoalCode
WHEN MATCHED THEN
    UPDATE SET
        GoalName = src.GoalName,
        GoalTypeCode = src.GoalTypeCode,
        ActiveFlag = 1,
        UpdatedBy = 1,
        UpdatedDate = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT (GoalCode, GoalName, GoalTypeCode, ActiveFlag, CreatedBy, CreatedDate)
    VALUES (src.GoalCode, src.GoalName, src.GoalTypeCode, 1, 1, SYSDATETIME());
GO
