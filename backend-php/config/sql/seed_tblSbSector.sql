USE [CBMSv2];
GO

/*
Seed data for dbo.tblSbSector
-----------------------------
- Safe to run multiple times.
- Inserts common public-sector policy groupings if they do not already exist.
- Reactivates matching inactive rows by SectorName.
*/

SET NOCOUNT ON;
GO

DECLARE @SeedUserID INT = 1;

DECLARE @SectorSeed TABLE (
    SectorName NVARCHAR(150) NOT NULL,
    SectorDescription NVARCHAR(MAX) NULL
);

INSERT INTO @SectorSeed (SectorName, SectorDescription)
VALUES
    (N'Agriculture', N'Agriculture, fisheries, livestock, irrigation, food security, and rural production systems.'),
    (N'Education', N'Pre-primary, primary, secondary, tertiary, technical, and vocational education services.'),
    (N'Energy', N'Power generation, transmission, distribution, renewable energy, and energy access initiatives.'),
    (N'Environment and Climate', N'Environmental protection, climate adaptation, resilience, forestry, and natural resource management.'),
    (N'Finance and Economic Management', N'Public finance, macroeconomic management, planning, debt, treasury, and revenue administration.'),
    (N'Gender, Youth and Social Protection', N'Gender equity, youth development, disability inclusion, social safety nets, and vulnerable populations.'),
    (N'Governance, Justice and Security', N'Public administration, justice, rule of law, internal security, and democratic governance institutions.'),
    (N'Health', N'Public health, primary care, hospitals, disease control, pharmaceuticals, and health systems strengthening.'),
    (N'Housing and Urban Development', N'Housing policy, urban planning, settlements, municipal development, and urban services.'),
    (N'ICT and Digital Transformation', N'Information and communications technology, digital government, connectivity, and innovation systems.'),
    (N'Infrastructure and Public Works', N'Public works, government buildings, construction standards, and strategic infrastructure support.'),
    (N'Labour and Employment', N'Employment creation, labour administration, productivity, occupational safety, and industrial relations.'),
    (N'Local Government', N'Decentralisation, local authorities, district administration, and community-level service delivery.'),
    (N'Mining and Natural Resources', N'Mining, extractives, geology, and sustainable resource development.'),
    (N'Trade, Industry and Private Sector Development', N'Trade facilitation, industrial development, investment promotion, MSMEs, and enterprise growth.'),
    (N'Transport', N'Roads, rail, aviation, maritime, public transport, and transport regulation.'),
    (N'Water and Sanitation', N'Water supply, sanitation, sewerage, water resources, and hygiene services.');

MERGE dbo.tblSbSector AS target
USING @SectorSeed AS source
    ON target.SectorName = source.SectorName
WHEN MATCHED THEN
    UPDATE SET
        target.SectorDescription = source.SectorDescription,
        target.ActiveFlag = 1,
        target.UpdatedBy = @SeedUserID,
        target.UpdatedDate = SYSDATETIME()
WHEN NOT MATCHED BY TARGET THEN
    INSERT (
        SectorName,
        SectorDescription,
        ActiveFlag,
        CreatedBy,
        CreatedDate
    )
    VALUES (
        source.SectorName,
        source.SectorDescription,
        1,
        @SeedUserID,
        SYSDATETIME()
    );
GO

SELECT
    SectorID,
    SectorName,
    SectorDescription,
    ActiveFlag
FROM dbo.tblSbSector
ORDER BY SectorName;
GO
