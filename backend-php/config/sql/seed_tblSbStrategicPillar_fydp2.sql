USE [CBMSv2];
GO

IF OBJECT_ID('dbo.tblSbStrategicPillar', 'U') IS NULL
BEGIN
    THROW 50000, 'Run create_tblSbStrategicPillar_and_link_goal.sql first.', 1;
END
GO

MERGE dbo.tblSbStrategicPillar AS target
USING (
    SELECT N'FY2-P1' AS StrategicPillarCode, N'Promotion of growth and industrialization for economic transformation' AS StrategicPillarName, N'FYDP_II' AS FrameworkCode UNION ALL
    SELECT N'FY2-P2', N'Enhancement of Human Development', N'FYDP_II' UNION ALL
    SELECT N'FY2-P3', N'Improvement of the enabling environment for enterprise development', N'FYDP_II' UNION ALL
    SELECT N'FY2-P4', N'Improve Implementation Effectiveness of the FYDP II', N'FYDP_II'
) AS src
ON target.StrategicPillarCode = src.StrategicPillarCode
WHEN MATCHED THEN
    UPDATE SET
        StrategicPillarName = src.StrategicPillarName,
        FrameworkCode = src.FrameworkCode,
        ActiveFlag = 1,
        UpdatedBy = 1,
        UpdatedDate = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT (StrategicPillarCode, StrategicPillarName, FrameworkCode, ActiveFlag, CreatedBy, CreatedDate)
    VALUES (src.StrategicPillarCode, src.StrategicPillarName, src.FrameworkCode, 1, 1, SYSDATETIME());
GO

/*
  Suggested starting links between SDG-style goals and FYDP II strategic pillars.
  Review these with the client after seeding.
*/
UPDATE g
SET StrategicPillarID = p.StrategicPillarID,
    UpdatedBy = 1,
    UpdatedDate = SYSDATETIME()
FROM dbo.tblSbGoal g
INNER JOIN dbo.tblSbStrategicPillar p
    ON (
        (g.GoalCode IN (N'SDG2', N'SDG8', N'SDG9') AND p.StrategicPillarCode = N'FY2-P1')
        OR (g.GoalCode IN (N'SDG3', N'SDG4', N'SDG7') AND p.StrategicPillarCode = N'FY2-P2')
        OR (g.GoalCode IN (N'SDG12', N'SDG17') AND p.StrategicPillarCode = N'FY2-P3')
        OR (g.GoalCode IN (N'SDG16') AND p.StrategicPillarCode = N'FY2-P4')
    )
WHERE g.StrategicPillarID IS NULL
   OR g.StrategicPillarID <> p.StrategicPillarID;
GO
