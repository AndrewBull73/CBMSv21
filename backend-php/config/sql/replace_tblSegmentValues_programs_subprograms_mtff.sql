USE [CBMSv2];
GO

/*
  Replaces the Programme and SubProgramme rows in dbo.tblSegmentValues
  for one FiscalYearID and one pair of SegmentNo values.

  Before running:
  1. Set @FiscalYearID
  2. Set @ProgramSegmentNo
  3. Set @SubProgramSegmentNo
  4. Review the backup SELECTs below if you want to save the current rows first

  Design assumptions:
  - Programme codes are 2 characters
  - SubProgramme codes are 4 characters
  - SubProgramme codes start with the parent Programme code
  - ParentSegmentNo / ParentSegmentCode are used for the hierarchy
*/

DECLARE @FiscalYearID INT = 2026;
DECLARE @ProgramSegmentNo INT = 4;
DECLARE @SubProgramSegmentNo INT = 5;
DECLARE @UpdatedBy INT = 1;

IF @FiscalYearID <= 0 OR @ProgramSegmentNo <= 0 OR @SubProgramSegmentNo <= 0
BEGIN
    THROW 50000, 'Set @FiscalYearID, @ProgramSegmentNo, and @SubProgramSegmentNo before running this script.', 1;
END;

IF COL_LENGTH('dbo.tblSegmentValues', 'ParentSegmentNo') IS NULL
   OR COL_LENGTH('dbo.tblSegmentValues', 'ParentSegmentCode') IS NULL
BEGIN
    THROW 50001, 'tblSegmentValues must have ParentSegmentNo and ParentSegmentCode before running this script.', 1;
END;

SET NOCOUNT ON;
SET XACT_ABORT ON;

DECLARE @ProgramRows TABLE (
    DataObjectCode NVARCHAR(20) NOT NULL,
    SegmentCode NVARCHAR(50) NOT NULL,
    SegmentName NVARCHAR(255) NOT NULL,
    SortOrder INT NOT NULL
);

DECLARE @SubProgramRows TABLE (
    DataObjectCode NVARCHAR(20) NOT NULL,
    ParentSegmentCode NVARCHAR(50) NOT NULL,
    SegmentCode NVARCHAR(50) NOT NULL,
    SegmentName NVARCHAR(255) NOT NULL,
    SortOrder INT NOT NULL
);

INSERT INTO @ProgramRows (DataObjectCode, SegmentCode, SegmentName, SortOrder)
VALUES
('301','01','Crop Production & Irrigation',1),
('301','02','Livestock Development',2),
('301','03','Agricultural Extension & Research',3),
('301','04','Food Security & Nutrition',4),
('302','05','Public Health Services',5),
('302','06','Curative Services',6),
('302','07','Health Systems Management',7),
('303','08','Basic Education',8),
('303','09','Secondary Education',9),
('303','10','Tertiary & Skills Development',10),
('304','11','Fiscal Policy & Planning',11),
('304','12','Public Financial Management',12),
('304','13','Revenue Management',13),
('304','14','Development Planning & Aid Coordination',14),
('305','15','Trade Development',15),
('305','16','Industry Development',16),
('305','17','Private Sector Development',17),
('306','18','Gender Empowerment',18),
('306','19','Youth Development',19),
('306','20','Social Protection',20),
('307','21','Legal Affairs & Law Reform',21),
('307','22','Justice Administration & Human Rights',22),
('308','23','Local Governance & Decentralisation',23),
('308','24','Chieftainship Affairs',24),
('308','25','Home Affairs & Civil Protection',25),
('309','26','Executive Coordination & Cabinet Affairs',26),
('310','27','Digital Infrastructure',27),
('310','28','E-Government Services',28),
('310','29','ICT Policy & Innovation',29),
('311','30','Anti-Corruption Prevention & Enforcement',30),
('312','31','Diplomatic Relations & Protocol',31),
('312','32','International Cooperation & Regional Integration',32),
('313','33','Public Infrastructure Development',33),
('313','34','Transport Services & Regulation',34),
('314','35','Environment & Climate Management',35),
('314','36','Forestry Conservation & Development',36),
('315','37','Natural Resources Development & Regulation',37),
('316','38','Employment Promotion',38),
('316','39','Labour Regulation & Industrial Relations',39),
('317','40','Judicial Services',40),
('318','41','Electoral Management',41),
('319','42','Royal Household & State Ceremonies',42),
('320','43','Public Service Management & Oversight',43),
('321','44','Public Debt Servicing & Management',44),
('323','45','Pensions & Gratuities',45),
('324','46','Statutory Salaries & Allowances',46),
('325','47','International Subscriptions & Contributions',47),
('326','48','Refunds of Erroneous Receipts',48),
('330','49','Centralised Government Obligations',49),
('331','50','Contingency Provision',50),
('335','51','National Security & Intelligence',51),
('336','52','Disaster Risk Management & Emergency Response',52),
('337','53','Defence Services',53),
('338','54','Legislative Services',54),
('339','55','Senate Legislative Services',55),
('340','56','Ombudsman & Complaints Resolution',56);

INSERT INTO @SubProgramRows (DataObjectCode, ParentSegmentCode, SegmentCode, SegmentName, SortOrder)
VALUES
('301','01','0101','Crop Production',1),
('301','01','0102','Irrigation Development',2),
('301','02','0201','Livestock Production',1),
('301','02','0202','Veterinary Services',2),
('301','03','0301','Extension Services',1),
('301','03','0302','Agricultural Research',2),
('301','04','0401','Food Security Programs',1),
('301','04','0402','Nutrition Support',2),
('302','05','0501','Disease Prevention',1),
('302','05','0502','Health Promotion',2),
('302','06','0601','Hospital Services',1),
('302','06','0602','Primary Healthcare',2),
('302','07','0701','Health Policy & Planning',1),
('302','07','0702','Health Administration',2),
('303','08','0801','Primary Education',1),
('303','08','0802','Early Childhood Education',2),
('303','09','0901','Junior Secondary',1),
('303','09','0902','Senior Secondary',2),
('303','10','1001','University Education',1),
('303','10','1002','Technical & Vocational Training',2),
('304','11','1101','Fiscal Policy Analysis',1),
('304','11','1102','Macroeconomic Planning',2),
('304','12','1201','Budget Management',1),
('304','12','1202','Treasury Operations',2),
('304','13','1301','Domestic Revenue Policy',1),
('304','13','1302','Revenue Forecasting & Analysis',2),
('304','14','1401','National Development Planning',1),
('304','14','1402','Aid Coordination',2),
('305','15','1501','Export Promotion',1),
('305','15','1502','Market Access Development',2),
('305','16','1601','Industrial Growth Support',1),
('305','16','1602','Value Chain Development',2),
('305','17','1701','Enterprise Development Support',1),
('305','17','1702','SME Promotion',2),
('306','18','1801','Gender Equality Promotion',1),
('306','18','1802','Women''s Empowerment Programs',2),
('306','19','1901','Youth Development Programs',1),
('306','19','1902','Youth Employment Support',2),
('306','20','2001','Social Assistance',1),
('306','20','2002','Vulnerable Groups Support',2),
('307','21','2101','Legal Policy Development',1),
('307','21','2102','Law Reform Services',2),
('307','22','2201','Human Rights Promotion',1),
('307','22','2202','Justice Administration Support',2),
('308','23','2301','Local Council Administration',1),
('308','23','2302','Decentralised Service Delivery',2),
('308','24','2401','Traditional Leadership Support',1),
('308','24','2402','Community Governance',2),
('308','25','2501','Civil Protection Services',1),
('308','25','2502','Home Affairs Administration',2),
('309','26','2601','Cabinet Support Services',1),
('309','26','2602','Government Coordination',2),
('310','27','2701','National Connectivity Infrastructure',1),
('310','27','2702','Government Network Platforms',2),
('310','28','2801','Digital Public Services',1),
('310','28','2802','Government Systems Integration',2),
('310','29','2901','ICT Policy Development',1),
('310','29','2902','Digital Innovation Support',2),
('311','30','3001','Corruption Investigation',1),
('311','30','3002','Prevention & Public Education',2),
('312','31','3101','Diplomatic Missions & Protocol',1),
('312','31','3102','Bilateral Relations',2),
('312','32','3201','Regional Integration',1),
('312','32','3202','International Cooperation',2),
('313','33','3301','Construction Projects',1),
('313','33','3302','Infrastructure Upgrades',2),
('313','34','3401','Road Transport Services',1),
('313','34','3402','Transport Regulation & Safety',2),
('314','35','3501','Climate Change Management',1),
('314','35','3502','Environmental Protection',2),
('314','36','3601','Forestry Conservation',1),
('314','36','3602','Forestry Development',2),
('315','37','3701','Resource Development',1),
('315','37','3702','Resource Regulation & Compliance',2),
('316','38','3801','Job Creation Support',1),
('316','38','3802','Workforce Placement Services',2),
('316','39','3901','Labour Inspection',1),
('316','39','3902','Industrial Relations Management',2),
('317','40','4001','Court Administration',1),
('317','40','4002','Adjudication Services',2),
('318','41','4101','Voter Registration',1),
('318','41','4102','Electoral Operations & Civic Education',2),
('319','42','4201','Royal Household Services',1),
('319','42','4202','State Ceremonial Functions',2),
('320','43','4301','Public Service Recruitment',1),
('320','43','4302','Public Service Oversight',2),
('321','44','4401','Debt Servicing',1),
('321','44','4402','Debt Portfolio Management',2),
('323','45','4501','Pension Payments',1),
('323','45','4502','Gratuity Payments',2),
('324','46','4601','Statutory Salaries',1),
('324','46','4602','Statutory Allowances',2),
('325','47','4701','International Membership Contributions',1),
('325','47','4702','Treaty and Organisation Obligations',2),
('326','48','4801','Revenue Refund Processing',1),
('326','48','4802','Erroneous Receipt Adjustments',2),
('330','49','4901','Centralised Personnel Costs',1),
('330','49','4902','Other Centralised Obligations',2),
('331','50','5001','Contingency Reserve',1),
('331','50','5002','Emergency Financing Provision',2),
('335','51','5101','Intelligence Operations',1),
('335','51','5102','National Security Coordination',2),
('336','52','5201','Disaster Risk Reduction',1),
('336','52','5202','Emergency Response & Recovery',2),
('337','53','5301','Military Operations',1),
('337','53','5302','Defence Support Services',2),
('338','54','5401','Plenary and Committee Services',1),
('338','54','5402','Legislative Oversight Support',2),
('339','55','5501','Senate Chamber Services',1),
('339','55','5502','Senate Committee Support',2),
('340','56','5601','Complaints Investigation',1),
('340','56','5602','Ombudsman Outreach & Resolution',2);

BEGIN TRANSACTION;

    /*
      Optional backup preview before replacement:

      SELECT *
      FROM dbo.tblSegmentValues
      WHERE FiscalYearID = @FiscalYearID
        AND SegmentNo IN (@ProgramSegmentNo, @SubProgramSegmentNo)
      ORDER BY DataObjectCode, SegmentNo, SegmentCode;
    */

    DELETE FROM dbo.tblSegmentValues
    WHERE FiscalYearID = @FiscalYearID
      AND SegmentNo = @SubProgramSegmentNo;

    DELETE FROM dbo.tblSegmentValues
    WHERE FiscalYearID = @FiscalYearID
      AND SegmentNo = @ProgramSegmentNo;

    INSERT INTO dbo.tblSegmentValues (
        FiscalYearID,
        DataObjectCode,
        SegmentNo,
        SegmentCode,
        SegmentName,
        SegmentExternalID,
        ParentSegmentValueID,
        SortOrder,
        ActiveFlag,
        UpdatedBy,
        UpdatedDate,
        ParentSegmentNo,
        ParentSegmentCode
    )
    SELECT
        @FiscalYearID,
        p.DataObjectCode,
        @ProgramSegmentNo,
        p.SegmentCode,
        p.SegmentName,
        NULL,
        NULL,
        p.SortOrder,
        1,
        @UpdatedBy,
        GETDATE(),
        NULL,
        NULL
    FROM @ProgramRows p
    ORDER BY p.SortOrder, p.DataObjectCode, p.SegmentCode;

    INSERT INTO dbo.tblSegmentValues (
        FiscalYearID,
        DataObjectCode,
        SegmentNo,
        SegmentCode,
        SegmentName,
        SegmentExternalID,
        ParentSegmentValueID,
        SortOrder,
        ActiveFlag,
        UpdatedBy,
        UpdatedDate,
        ParentSegmentNo,
        ParentSegmentCode
    )
    SELECT
        @FiscalYearID,
        s.DataObjectCode,
        @SubProgramSegmentNo,
        s.SegmentCode,
        s.SegmentName,
        NULL,
        NULL,
        s.SortOrder,
        1,
        @UpdatedBy,
        GETDATE(),
        @ProgramSegmentNo,
        s.ParentSegmentCode
    FROM @SubProgramRows s
    ORDER BY s.ParentSegmentCode, s.SortOrder, s.SegmentCode;

    DECLARE @ProgramCount INT = (
        SELECT COUNT(*)
        FROM dbo.tblSegmentValues
        WHERE FiscalYearID = @FiscalYearID
          AND SegmentNo = @ProgramSegmentNo
    );

    DECLARE @SubProgramCount INT = (
        SELECT COUNT(*)
        FROM dbo.tblSegmentValues
        WHERE FiscalYearID = @FiscalYearID
          AND SegmentNo = @SubProgramSegmentNo
    );

    PRINT 'Replaced Programme rows: ' + CAST(@ProgramCount AS NVARCHAR(20));
    PRINT 'Replaced SubProgramme rows: ' + CAST(@SubProgramCount AS NVARCHAR(20));

COMMIT TRANSACTION;
GO
