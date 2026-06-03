/*
Expanded Strategic Budgeting Demo Seed
-------------------------------------
Builds a broader demo dataset for the active fiscal year/version so
the Strategic Summary, reports, readiness, governance, and workflow
screens have richer data to display.

This script is additive and idempotent:
- it uses DEMO-prefixed codes/names
- it updates/reactivates matching demo rows when re-run
- it works alongside seed_strategic_budgeting_demo.sql
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

BEGIN TRY
    BEGIN TRAN;

    DECLARE @FiscalYearID INT;
    DECLARE @VersionID INT;
    DECLARE @DefaultOrgUnitID INT;
    DECLARE @PlanningOrgUnitID INT;

    SELECT TOP (1) @FiscalYearID = fy.FiscalYearID
    FROM dbo.tblFiscalYears fy
    WHERE ISNULL(fy.IsActive, 0) = 1
    ORDER BY fy.FiscalYearID DESC;

    IF @FiscalYearID IS NULL
    BEGIN
        SELECT TOP (1) @FiscalYearID = fy.FiscalYearID
        FROM dbo.tblFiscalYears fy
        ORDER BY fy.FiscalYearID DESC;
    END;

    IF @FiscalYearID IS NULL
    BEGIN
        THROW 51011, 'No fiscal year exists. Seed tblFiscalYears and tblVersions first.', 1;
    END;

    SELECT TOP (1) @VersionID = v.VersionID
    FROM dbo.tblVersions v
    WHERE v.FiscalYearID = @FiscalYearID
      AND ISNULL(v.IsActive, 1) = 1
    ORDER BY ISNULL(v.IsDefault, 0) DESC, v.VersionID ASC;

    IF @VersionID IS NULL
    BEGIN
        THROW 51012, 'No active version exists for the selected fiscal year.', 1;
    END;

    SELECT TOP (1) @DefaultOrgUnitID = ou.OrgUnitID
    FROM dbo.tblSbOrgUnit ou
    WHERE ou.ActiveFlag = 1
    ORDER BY CASE WHEN ou.SourceFiscalYearID = @FiscalYearID THEN 0 ELSE 1 END, ou.OrgUnitID ASC;

    IF @DefaultOrgUnitID IS NULL
    BEGIN
        INSERT INTO dbo.tblSbOrgUnit (
            ParentOrgUnitID,
            OrgUnitTypeCode,
            VoteCode,
            OrgUnitName,
            ActiveFlag,
            CreatedBy,
            CreatedDate
        )
        VALUES (
            NULL,
            N'VOTE',
            N'SB-DEMO-ROOT',
            N'Demo Strategic Root Vote',
            1,
            1,
            SYSDATETIME()
        );

        SET @DefaultOrgUnitID = CAST(SCOPE_IDENTITY() AS INT);
    END;

    SELECT @PlanningOrgUnitID = ou.OrgUnitID
    FROM dbo.tblSbOrgUnit ou
    WHERE ou.VoteCode = N'SB-DEMO-PLAN';

    IF @PlanningOrgUnitID IS NULL
    BEGIN
        INSERT INTO dbo.tblSbOrgUnit (
            ParentOrgUnitID,
            OrgUnitTypeCode,
            VoteCode,
            OrgUnitName,
            ActiveFlag,
            CreatedBy,
            CreatedDate
        )
        VALUES (
            @DefaultOrgUnitID,
            N'MDA',
            N'SB-DEMO-PLAN',
            N'Demo Planning and Delivery Office',
            1,
            1,
            SYSDATETIME()
        );

        SET @PlanningOrgUnitID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbOrgUnit
        SET ParentOrgUnitID = @DefaultOrgUnitID,
            OrgUnitTypeCode = N'MDA',
            OrgUnitName = N'Demo Planning and Delivery Office',
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE OrgUnitID = @PlanningOrgUnitID;
    END;

    DECLARE @Sectors TABLE (
        SectorCode NVARCHAR(20) NOT NULL PRIMARY KEY,
        SectorName NVARCHAR(150) NOT NULL,
        SectorDescription NVARCHAR(MAX) NOT NULL,
        SectorID INT NULL
    );

    INSERT INTO @Sectors (SectorCode, SectorName, SectorDescription)
    VALUES
        (N'DEMO-HEALTH', N'DEMO Health and Community Services', N'Demo sector for community health, outreach, and access improvements.'),
        (N'DEMO-EDU', N'DEMO Education Quality and Access', N'Demo sector for learning access, classroom support, and service readiness.'),
        (N'DEMO-WASH', N'DEMO Water and Local Infrastructure', N'Demo sector for water access, maintenance, and community infrastructure delivery.');

    DECLARE @SectorCode NVARCHAR(20);
    DECLARE @SectorName NVARCHAR(150);
    DECLARE @SectorDescription NVARCHAR(MAX);
    DECLARE @SectorID INT;

    DECLARE sector_cursor CURSOR LOCAL FAST_FORWARD FOR
        SELECT SectorCode, SectorName, SectorDescription
        FROM @Sectors;

    OPEN sector_cursor;
    FETCH NEXT FROM sector_cursor INTO @SectorCode, @SectorName, @SectorDescription;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        SELECT @SectorID = s.SectorID
        FROM dbo.tblSbSector s
        WHERE s.SectorName = @SectorName;

        IF @SectorID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbSector (
                SectorName,
                SectorDescription,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @SectorName,
                @SectorDescription,
                1,
                1,
                SYSDATETIME()
            );

            SET @SectorID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbSector
            SET SectorDescription = @SectorDescription,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE SectorID = @SectorID;
        END;

        UPDATE @Sectors
        SET SectorID = @SectorID
        WHERE SectorCode = @SectorCode;

        FETCH NEXT FROM sector_cursor INTO @SectorCode, @SectorName, @SectorDescription;
    END;

    CLOSE sector_cursor;
    DEALLOCATE sector_cursor;

    DECLARE @FundingTypes TABLE (
        FundingTypeCode NVARCHAR(50) NOT NULL PRIMARY KEY,
        FundingTypeName NVARCHAR(150) NOT NULL,
        FundingTypeDescription NVARCHAR(MAX) NOT NULL,
        FundingTypeID INT NULL
    );

    INSERT INTO @FundingTypes (FundingTypeCode, FundingTypeName, FundingTypeDescription)
    VALUES
        (N'DEMO-DOM', N'Demo Domestic Funding', N'Demo domestic resource envelope used in expanded strategic seed data.'),
        (N'DEMO-GRANT', N'Demo Grant Funding', N'Demo partner grant funding used in expanded strategic seed data.');

    DECLARE @FundingTypeCode NVARCHAR(50);
    DECLARE @FundingTypeName NVARCHAR(150);
    DECLARE @FundingTypeDescription NVARCHAR(MAX);
    DECLARE @FundingTypeID INT;

    DECLARE funding_type_cursor CURSOR LOCAL FAST_FORWARD FOR
        SELECT FundingTypeCode, FundingTypeName, FundingTypeDescription
        FROM @FundingTypes;

    OPEN funding_type_cursor;
    FETCH NEXT FROM funding_type_cursor INTO @FundingTypeCode, @FundingTypeName, @FundingTypeDescription;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        SELECT @FundingTypeID = ft.FundingTypeID
        FROM dbo.tblSbFundingType ft
        WHERE ft.FundingTypeCode = @FundingTypeCode;

        IF @FundingTypeID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbFundingType (
                FundingTypeCode,
                FundingTypeName,
                FundingTypeDescription,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @FundingTypeCode,
                @FundingTypeName,
                @FundingTypeDescription,
                1,
                1,
                SYSDATETIME()
            );

            SET @FundingTypeID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbFundingType
            SET FundingTypeName = @FundingTypeName,
                FundingTypeDescription = @FundingTypeDescription,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE FundingTypeID = @FundingTypeID;
        END;

        UPDATE @FundingTypes
        SET FundingTypeID = @FundingTypeID
        WHERE FundingTypeCode = @FundingTypeCode;

        FETCH NEXT FROM funding_type_cursor INTO @FundingTypeCode, @FundingTypeName, @FundingTypeDescription;
    END;

    CLOSE funding_type_cursor;
    DEALLOCATE funding_type_cursor;

    DECLARE @FundingSources TABLE (
        FundingSourceName NVARCHAR(200) NOT NULL PRIMARY KEY,
        FundingTypeCode NVARCHAR(50) NOT NULL,
        DonorName NVARCHAR(200) NULL,
        ConditionsText NVARCHAR(MAX) NOT NULL,
        FundingSourceID INT NULL
    );

    INSERT INTO @FundingSources (FundingSourceName, FundingTypeCode, DonorName, ConditionsText)
    VALUES
        (N'Demo Consolidated Fund', N'DEMO-DOM', NULL, N'Demo recurrent resource source for service delivery.'),
        (N'Demo Resilience Grant', N'DEMO-GRANT', N'Demo Development Partner', N'Demo grant resource focused on outreach and infrastructure resilience.');

    DECLARE @FundingSourceName NVARCHAR(200);
    DECLARE @FundingSourceTypeCode NVARCHAR(50);
    DECLARE @DonorName NVARCHAR(200);
    DECLARE @ConditionsText NVARCHAR(MAX);
    DECLARE @FundingSourceID INT;

    DECLARE funding_source_cursor CURSOR LOCAL FAST_FORWARD FOR
        SELECT FundingSourceName, FundingTypeCode, DonorName, ConditionsText
        FROM @FundingSources;

    OPEN funding_source_cursor;
    FETCH NEXT FROM funding_source_cursor INTO @FundingSourceName, @FundingSourceTypeCode, @DonorName, @ConditionsText;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        SELECT @FundingTypeID = ft.FundingTypeID
        FROM @FundingTypes ft
        WHERE ft.FundingTypeCode = @FundingSourceTypeCode;

        SELECT @FundingSourceID = fs.FundingSourceID
        FROM dbo.tblSbFundingSource fs
        WHERE fs.FundingSourceName = @FundingSourceName;

        IF @FundingSourceID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbFundingSource (
                FundingTypeID,
                FundingTypeCode,
                FundingSourceName,
                DonorName,
                ConditionsText,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @FundingTypeID,
                @FundingSourceTypeCode,
                @FundingSourceName,
                @DonorName,
                @ConditionsText,
                1,
                1,
                SYSDATETIME()
            );

            SET @FundingSourceID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbFundingSource
            SET FundingTypeID = @FundingTypeID,
                FundingTypeCode = @FundingSourceTypeCode,
                DonorName = @DonorName,
                ConditionsText = @ConditionsText,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE FundingSourceID = @FundingSourceID;
        END;

        UPDATE @FundingSources
        SET FundingSourceID = @FundingSourceID
        WHERE FundingSourceName = @FundingSourceName;

        FETCH NEXT FROM funding_source_cursor INTO @FundingSourceName, @FundingSourceTypeCode, @DonorName, @ConditionsText;
    END;

    CLOSE funding_source_cursor;
    DEALLOCATE funding_source_cursor;

    DECLARE @EconomicItems TABLE (
        EconomicCode NVARCHAR(50) NOT NULL PRIMARY KEY,
        EconomicName NVARCHAR(200) NOT NULL,
        EconomicLevel INT NOT NULL,
        EconomicItemID INT NULL
    );

    INSERT INTO @EconomicItems (EconomicCode, EconomicName, EconomicLevel)
    VALUES
        (N'DEMO-2110', N'Demo Compensation of Employees', 2),
        (N'DEMO-2210', N'Demo Goods and Services', 2),
        (N'DEMO-3110', N'Demo Capital Equipment', 2);

    DECLARE @EconomicCode NVARCHAR(50);
    DECLARE @EconomicName NVARCHAR(200);
    DECLARE @EconomicLevel INT;
    DECLARE @EconomicItemID INT;

    DECLARE economic_cursor CURSOR LOCAL FAST_FORWARD FOR
        SELECT EconomicCode, EconomicName, EconomicLevel
        FROM @EconomicItems;

    OPEN economic_cursor;
    FETCH NEXT FROM economic_cursor INTO @EconomicCode, @EconomicName, @EconomicLevel;
    WHILE @@FETCH_STATUS = 0
    BEGIN
        SELECT @EconomicItemID = ei.EconomicItemID
        FROM dbo.tblSbEconomicItem ei
        WHERE ei.EconomicCode = @EconomicCode;

        IF @EconomicItemID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbEconomicItem (
                ParentEconomicItemID,
                EconomicCode,
                EconomicName,
                EconomicLevel,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                NULL,
                @EconomicCode,
                @EconomicName,
                @EconomicLevel,
                1,
                1,
                SYSDATETIME()
            );

            SET @EconomicItemID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbEconomicItem
            SET EconomicName = @EconomicName,
                EconomicLevel = @EconomicLevel,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE EconomicItemID = @EconomicItemID;
        END;

        UPDATE @EconomicItems
        SET EconomicItemID = @EconomicItemID
        WHERE EconomicCode = @EconomicCode;

        FETCH NEXT FROM economic_cursor INTO @EconomicCode, @EconomicName, @EconomicLevel;
    END;

    CLOSE economic_cursor;
    DEALLOCATE economic_cursor;

    DECLARE @Programs TABLE (
        ProgramCode NVARCHAR(50) NOT NULL PRIMARY KEY,
        SectorCode NVARCHAR(20) NOT NULL,
        ProgramName NVARCHAR(200) NOT NULL,
        ProgramDescription NVARCHAR(MAX) NOT NULL,
        ProgramManagerName NVARCHAR(150) NOT NULL,
        SubProgramCode NVARCHAR(50) NOT NULL,
        SubProgramName NVARCHAR(200) NOT NULL,
        ObjectiveText NVARCHAR(MAX) NOT NULL,
        PolicyLink NVARCHAR(200) NOT NULL,
        OutcomeIndicatorName NVARCHAR(200) NOT NULL,
        OutcomeIndicatorDefinition NVARCHAR(MAX) NOT NULL,
        OutcomeUnit NVARCHAR(50) NOT NULL,
        OutcomeDataSource NVARCHAR(200) NOT NULL,
        OutcomeFrequency NVARCHAR(20) NOT NULL,
        BaselineValue DECIMAL(18,6) NOT NULL,
        TargetValue DECIMAL(18,6) NOT NULL,
        OutputName NVARCHAR(200) NOT NULL,
        OutputDescription NVARCHAR(MAX) NOT NULL,
        OutputIndicatorName NVARCHAR(200) NOT NULL,
        OutputIndicatorDefinition NVARCHAR(MAX) NOT NULL,
        OutputUnit NVARCHAR(50) NOT NULL,
        OutputDataSource NVARCHAR(200) NOT NULL,
        OutputFrequency NVARCHAR(20) NOT NULL,
        OutputBaselineValue DECIMAL(18,6) NOT NULL,
        OutputTargetValue DECIMAL(18,6) NOT NULL,
        ActivityName NVARCHAR(200) NOT NULL,
        ActivityDescription NVARCHAR(MAX) NOT NULL,
        ActivityTypeCode NVARCHAR(20) NOT NULL,
        LocationCode NVARCHAR(50) NULL,
        FundingSourceName NVARCHAR(200) NOT NULL,
        EconomicCode NVARCHAR(50) NOT NULL,
        BudgetAmount DECIMAL(18,2) NOT NULL,
        RiskTitle NVARCHAR(200) NOT NULL,
        RiskTypeCode NVARCHAR(30) NOT NULL,
        RiskDescription NVARCHAR(MAX) NOT NULL,
        RiskLikelihood TINYINT NOT NULL,
        RiskImpact TINYINT NOT NULL
    );

    INSERT INTO @Programs (
        ProgramCode, SectorCode, ProgramName, ProgramDescription, ProgramManagerName,
        SubProgramCode, SubProgramName, ObjectiveText, PolicyLink,
        OutcomeIndicatorName, OutcomeIndicatorDefinition, OutcomeUnit, OutcomeDataSource, OutcomeFrequency, BaselineValue, TargetValue,
        OutputName, OutputDescription,
        OutputIndicatorName, OutputIndicatorDefinition, OutputUnit, OutputDataSource, OutputFrequency, OutputBaselineValue, OutputTargetValue,
        ActivityName, ActivityDescription, ActivityTypeCode, LocationCode,
        FundingSourceName, EconomicCode, BudgetAmount,
        RiskTitle, RiskTypeCode, RiskDescription, RiskLikelihood, RiskImpact
    )
    VALUES
    (
        N'DEMO-PROG-101', N'DEMO-HEALTH',
        N'Demo Primary Care Access Program',
        N'Expands routine primary care access through outreach and referral coverage.',
        N'Demo Health Manager',
        N'DEMO-SUB-101', N'Demo Outreach Services',
        N'Increase the share of remote households within practical reach of basic primary care services.',
        N'DEMO-NDP-HEALTH-1',
        N'Demo households within reach of primary care',
        N'Percent of households in target locations within the service radius of a primary care contact point.',
        N'Percent', N'Demo HMIS', N'ANNUAL', 42.000000, 58.000000,
        N'Demo mobile primary care outreach delivered',
        N'Mobile service sessions delivered to underserved settlements.',
        N'Demo outreach sessions delivered',
        N'Count of completed mobile outreach sessions in the fiscal year.',
        N'Number', N'Demo Outreach Register', N'QUARTERLY', 24.000000, 36.000000,
        N'Demo deploy monthly mobile clinic sessions',
        N'Operates monthly outreach teams with referral follow-up and logistics support.',
        N'PROJECT', N'DEMO-REGION-01',
        N'Demo Consolidated Fund', N'DEMO-2210', 1500000.00,
        N'Demo medical supply disruption risk', N'OTHER',
        N'Supply delays could interrupt outreach continuity and increase operational costs.',
        3, 4
    ),
    (
        N'DEMO-PROG-102', N'DEMO-EDU',
        N'Demo Basic Learning Recovery Program',
        N'Supports classroom recovery through targeted teaching and school support packages.',
        N'Demo Education Manager',
        N'DEMO-SUB-102', N'Demo School Support',
        N'Improve the proportion of learners meeting minimum reading benchmarks in supported schools.',
        N'DEMO-NDP-EDU-1',
        N'Demo learners meeting reading benchmark',
        N'Percent of sampled learners in supported schools meeting the agreed reading benchmark.',
        N'Percent', N'Demo Assessment Survey', N'ANNUAL', 37.000000, 52.000000,
        N'Demo reading support packages delivered',
        N'Teaching and learning support packages distributed to target schools.',
        N'Demo schools receiving support packages',
        N'Count of supported schools receiving the full learning recovery package.',
        N'Number', N'Demo Education Logistics Register', N'QUARTERLY', 18.000000, 30.000000,
        N'Demo distribute school learning recovery kits',
        N'Procures and distributes reading kits and teacher support materials.',
        N'PROJECT', N'DEMO-REGION-02',
        N'Demo Resilience Grant', N'DEMO-3110', 2100000.00,
        N'Demo school distribution delay risk', N'OTHER',
        N'Delivery bottlenecks could delay learning materials and reduce term-time effectiveness.',
        2, 4
    ),
    (
        N'DEMO-PROG-103', N'DEMO-WASH',
        N'Demo Rural Water Point Reliability Program',
        N'Improves the uptime and responsiveness of rural water service maintenance.',
        N'Demo Infrastructure Manager',
        N'DEMO-SUB-103', N'Demo Water Maintenance',
        N'Increase the share of rural water points functioning reliably throughout the year.',
        N'DEMO-NDP-WASH-1',
        N'Demo functioning rural water points',
        N'Percent of mapped rural water points operating within service reliability standards.',
        N'Percent', N'Demo WASH Monitoring System', N'ANNUAL', 61.000000, 74.000000,
        N'Demo water point maintenance responses completed',
        N'Field maintenance visits completed in response to reported outages.',
        N'Demo maintenance responses completed',
        N'Count of maintenance response visits completed to restore service.',
        N'Number', N'Demo Maintenance Log', N'QUARTERLY', 45.000000, 64.000000,
        N'Demo dispatch preventive maintenance teams',
        N'Coordinates preventive and corrective maintenance visits across target districts.',
        N'OPERATIONAL', N'DEMO-REGION-03',
        N'Demo Consolidated Fund', N'DEMO-2110', 980000.00,
        N'Demo spare parts availability risk', N'OTHER',
        N'Long lead times for replacement parts could extend outages and raise response costs.',
        3, 3
    ),
    (
        N'DEMO-PROG-104', N'DEMO-HEALTH',
        N'Demo Community Nutrition Outreach Program',
        N'Strengthens preventive nutrition services through local outreach and counseling.',
        N'Demo Nutrition Manager',
        N'DEMO-SUB-104', N'Demo Nutrition Counselling',
        N'Improve early nutrition screening and counselling coverage among at-risk households.',
        N'DEMO-NDP-HEALTH-2',
        N'Demo at-risk households screened for nutrition',
        N'Percent of identified at-risk households reached with screening and counselling support.',
        N'Percent', N'Demo Nutrition Register', N'ANNUAL', 28.000000, 46.000000,
        N'Demo nutrition screening outreach delivered',
        N'Community nutrition screening and counselling sessions completed.',
        N'Demo nutrition outreach sessions completed',
        N'Count of nutrition outreach sessions completed in target communities.',
        N'Number', N'Demo Nutrition Outreach Log', N'MONTHLY', 30.000000, 54.000000,
        N'Demo run quarterly nutrition outreach cycles',
        N'Runs community-based screening, counselling, and referral cycles with volunteers.',
        N'OPERATIONAL', N'DEMO-REGION-04',
        N'Demo Resilience Grant', N'DEMO-2210', 760000.00,
        N'Demo volunteer attrition risk', N'OTHER',
        N'Volunteer turnover could reduce outreach coverage and increase retraining needs.',
        2, 3
    );

    DECLARE
        @ProgramCode NVARCHAR(50),
        @ProgramSectorCode NVARCHAR(20),
        @ProgramName NVARCHAR(200),
        @ProgramDescription NVARCHAR(MAX),
        @ProgramManagerName NVARCHAR(150),
        @SubProgramCode NVARCHAR(50),
        @SubProgramName NVARCHAR(200),
        @ObjectiveText NVARCHAR(MAX),
        @PolicyLink NVARCHAR(200),
        @OutcomeIndicatorName NVARCHAR(200),
        @OutcomeIndicatorDefinition NVARCHAR(MAX),
        @OutcomeUnit NVARCHAR(50),
        @OutcomeDataSource NVARCHAR(200),
        @OutcomeFrequency NVARCHAR(20),
        @BaselineValue DECIMAL(18,6),
        @TargetValue DECIMAL(18,6),
        @OutputName NVARCHAR(200),
        @OutputDescription NVARCHAR(MAX),
        @OutputIndicatorName NVARCHAR(200),
        @OutputIndicatorDefinition NVARCHAR(MAX),
        @OutputUnit NVARCHAR(50),
        @OutputDataSource NVARCHAR(200),
        @OutputFrequency NVARCHAR(20),
        @OutputBaselineValue DECIMAL(18,6),
        @OutputTargetValue DECIMAL(18,6),
        @ActivityName NVARCHAR(200),
        @ActivityDescription NVARCHAR(MAX),
        @ActivityTypeCode NVARCHAR(20),
        @LocationCode NVARCHAR(50),
        @ProgramFundingSourceName NVARCHAR(200),
        @ProgramEconomicCode NVARCHAR(50),
        @BudgetAmount DECIMAL(18,2),
        @RiskTitle NVARCHAR(200),
        @RiskTypeCode NVARCHAR(30),
        @RiskDescription NVARCHAR(MAX),
        @RiskLikelihood TINYINT,
        @RiskImpact TINYINT,
        @ProgramSectorID INT,
        @ProgramID INT,
        @SubProgramID INT,
        @ObjectiveID INT,
        @OutcomeIndicatorID INT,
        @OutputID INT,
        @OutputIndicatorID INT,
        @ActivityID INT,
        @ProgramFundingSourceID INT,
        @ProgramEconomicItemID INT,
        @ProgramRiskID INT;

    DECLARE program_cursor CURSOR LOCAL FAST_FORWARD FOR
        SELECT
            ProgramCode, SectorCode, ProgramName, ProgramDescription, ProgramManagerName,
            SubProgramCode, SubProgramName, ObjectiveText, PolicyLink,
            OutcomeIndicatorName, OutcomeIndicatorDefinition, OutcomeUnit, OutcomeDataSource, OutcomeFrequency, BaselineValue, TargetValue,
            OutputName, OutputDescription,
            OutputIndicatorName, OutputIndicatorDefinition, OutputUnit, OutputDataSource, OutputFrequency, OutputBaselineValue, OutputTargetValue,
            ActivityName, ActivityDescription, ActivityTypeCode, LocationCode,
            FundingSourceName, EconomicCode, BudgetAmount,
            RiskTitle, RiskTypeCode, RiskDescription, RiskLikelihood, RiskImpact
        FROM @Programs;

    OPEN program_cursor;
    FETCH NEXT FROM program_cursor INTO
        @ProgramCode, @ProgramSectorCode, @ProgramName, @ProgramDescription, @ProgramManagerName,
        @SubProgramCode, @SubProgramName, @ObjectiveText, @PolicyLink,
        @OutcomeIndicatorName, @OutcomeIndicatorDefinition, @OutcomeUnit, @OutcomeDataSource, @OutcomeFrequency, @BaselineValue, @TargetValue,
        @OutputName, @OutputDescription,
        @OutputIndicatorName, @OutputIndicatorDefinition, @OutputUnit, @OutputDataSource, @OutputFrequency, @OutputBaselineValue, @OutputTargetValue,
        @ActivityName, @ActivityDescription, @ActivityTypeCode, @LocationCode,
        @ProgramFundingSourceName, @ProgramEconomicCode, @BudgetAmount,
        @RiskTitle, @RiskTypeCode, @RiskDescription, @RiskLikelihood, @RiskImpact;

    WHILE @@FETCH_STATUS = 0
    BEGIN
        SELECT @ProgramSectorID = s.SectorID
        FROM @Sectors s
        WHERE s.SectorCode = @ProgramSectorCode;

        SELECT @ProgramFundingSourceID = fs.FundingSourceID
        FROM @FundingSources fs
        WHERE fs.FundingSourceName = @ProgramFundingSourceName;

        SELECT @ProgramEconomicItemID = ei.EconomicItemID
        FROM @EconomicItems ei
        WHERE ei.EconomicCode = @ProgramEconomicCode;

        SELECT @ProgramID = p.ProgramID
        FROM dbo.tblSbProgram p
        WHERE p.ProgramCode = @ProgramCode;

        IF @ProgramID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbProgram (
                OrgUnitID,
                SectorID,
                ProgramCode,
                ProgramName,
                ProgramDescription,
                ProgramManagerName,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @PlanningOrgUnitID,
                @ProgramSectorID,
                @ProgramCode,
                @ProgramName,
                @ProgramDescription,
                @ProgramManagerName,
                1,
                1,
                SYSDATETIME()
            );

            SET @ProgramID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbProgram
            SET OrgUnitID = @PlanningOrgUnitID,
                SectorID = @ProgramSectorID,
                ProgramName = @ProgramName,
                ProgramDescription = @ProgramDescription,
                ProgramManagerName = @ProgramManagerName,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE ProgramID = @ProgramID;
        END;

        SELECT @SubProgramID = sp.SubProgramID
        FROM dbo.tblSbSubProgram sp
        WHERE sp.ProgramID = @ProgramID
          AND sp.SubProgramCode = @SubProgramCode;

        IF @SubProgramID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbSubProgram (
                ProgramID,
                SubProgramCode,
                SubProgramName,
                SubProgramDescription,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @ProgramID,
                @SubProgramCode,
                @SubProgramName,
                @SubProgramName + N' for ' + @ProgramName,
                1,
                1,
                SYSDATETIME()
            );

            SET @SubProgramID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbSubProgram
            SET SubProgramName = @SubProgramName,
                SubProgramDescription = @SubProgramName + N' for ' + @ProgramName,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE SubProgramID = @SubProgramID;
        END;

        SELECT @ObjectiveID = o.ObjectiveID
        FROM dbo.tblSbObjective o
        WHERE o.ProgramID = @ProgramID
          AND o.ObjectiveText = @ObjectiveText;

        IF @ObjectiveID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbObjective (
                ProgramID,
                SubProgramID,
                ObjectiveText,
                PolicyLink,
                PriorityRank,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @ProgramID,
                @SubProgramID,
                @ObjectiveText,
                @PolicyLink,
                1,
                1,
                1,
                SYSDATETIME()
            );

            SET @ObjectiveID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbObjective
            SET SubProgramID = @SubProgramID,
                PolicyLink = @PolicyLink,
                PriorityRank = 1,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE ObjectiveID = @ObjectiveID;
        END;

        SELECT @OutcomeIndicatorID = i.IndicatorID
        FROM dbo.tblSbIndicator i
        WHERE i.IndicatorTypeCode = N'OUTCOME'
          AND i.IndicatorName = @OutcomeIndicatorName;

        IF @OutcomeIndicatorID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbIndicator (
                IndicatorTypeCode,
                IndicatorName,
                IndicatorDefinition,
                UnitOfMeasure,
                DataSource,
                FrequencyCode,
                Disaggregation,
                QualityNotes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                N'OUTCOME',
                @OutcomeIndicatorName,
                @OutcomeIndicatorDefinition,
                @OutcomeUnit,
                @OutcomeDataSource,
                @OutcomeFrequency,
                N'Region/Sex',
                N'Expanded strategic demo outcome indicator.',
                1,
                1,
                SYSDATETIME()
            );

            SET @OutcomeIndicatorID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbIndicator
            SET IndicatorDefinition = @OutcomeIndicatorDefinition,
                UnitOfMeasure = @OutcomeUnit,
                DataSource = @OutcomeDataSource,
                FrequencyCode = @OutcomeFrequency,
                Disaggregation = N'Region/Sex',
                QualityNotes = N'Expanded strategic demo outcome indicator.',
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE IndicatorID = @OutcomeIndicatorID;
        END;

        IF NOT EXISTS (
            SELECT 1
            FROM dbo.tblSbObjectiveIndicator oi
            WHERE oi.ObjectiveID = @ObjectiveID
              AND oi.IndicatorID = @OutcomeIndicatorID
        )
        BEGIN
            INSERT INTO dbo.tblSbObjectiveIndicator (
                ObjectiveID,
                IndicatorID,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @ObjectiveID,
                @OutcomeIndicatorID,
                1,
                SYSDATETIME()
            );
        END;

        IF EXISTS (
            SELECT 1
            FROM dbo.tblSbIndicatorTarget t
            WHERE t.IndicatorID = @OutcomeIndicatorID
              AND t.VersionID = @VersionID
        )
        BEGIN
            UPDATE dbo.tblSbIndicatorTarget
            SET FiscalYearID = @FiscalYearID,
                BaselineValue = @BaselineValue,
                TargetValue = @TargetValue,
                Notes = N'Expanded demo target for active context.',
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE IndicatorID = @OutcomeIndicatorID
              AND VersionID = @VersionID;
        END
        ELSE
        BEGIN
            INSERT INTO dbo.tblSbIndicatorTarget (
                IndicatorID,
                VersionID,
                FiscalYearID,
                BaselineValue,
                TargetValue,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @OutcomeIndicatorID,
                @VersionID,
                @FiscalYearID,
                @BaselineValue,
                @TargetValue,
                N'Expanded demo target for active context.',
                1,
                1,
                SYSDATETIME()
            );
        END;

        SELECT @OutputID = o.OutputID
        FROM dbo.tblSbOutput o
        WHERE o.ProgramID = @ProgramID
          AND o.OutputName = @OutputName;

        IF @OutputID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbOutput (
                ProgramID,
                SubProgramID,
                OutputName,
                OutputDescription,
                OutputOwnerOrgUnitID,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @ProgramID,
                @SubProgramID,
                @OutputName,
                @OutputDescription,
                @PlanningOrgUnitID,
                1,
                1,
                SYSDATETIME()
            );

            SET @OutputID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbOutput
            SET SubProgramID = @SubProgramID,
                OutputDescription = @OutputDescription,
                OutputOwnerOrgUnitID = @PlanningOrgUnitID,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE OutputID = @OutputID;
        END;

        SELECT @OutputIndicatorID = i.IndicatorID
        FROM dbo.tblSbIndicator i
        WHERE i.IndicatorTypeCode = N'OUTPUT'
          AND i.IndicatorName = @OutputIndicatorName;

        IF @OutputIndicatorID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbIndicator (
                IndicatorTypeCode,
                IndicatorName,
                IndicatorDefinition,
                UnitOfMeasure,
                DataSource,
                FrequencyCode,
                Disaggregation,
                QualityNotes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                N'OUTPUT',
                @OutputIndicatorName,
                @OutputIndicatorDefinition,
                @OutputUnit,
                @OutputDataSource,
                @OutputFrequency,
                N'Region',
                N'Expanded strategic demo output indicator.',
                1,
                1,
                SYSDATETIME()
            );

            SET @OutputIndicatorID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbIndicator
            SET IndicatorDefinition = @OutputIndicatorDefinition,
                UnitOfMeasure = @OutputUnit,
                DataSource = @OutputDataSource,
                FrequencyCode = @OutputFrequency,
                Disaggregation = N'Region',
                QualityNotes = N'Expanded strategic demo output indicator.',
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE IndicatorID = @OutputIndicatorID;
        END;

        IF NOT EXISTS (
            SELECT 1
            FROM dbo.tblSbOutputIndicator oi
            WHERE oi.OutputID = @OutputID
              AND oi.IndicatorID = @OutputIndicatorID
        )
        BEGIN
            INSERT INTO dbo.tblSbOutputIndicator (
                OutputID,
                IndicatorID,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @OutputID,
                @OutputIndicatorID,
                1,
                SYSDATETIME()
            );
        END;

        IF EXISTS (
            SELECT 1
            FROM dbo.tblSbIndicatorTarget t
            WHERE t.IndicatorID = @OutputIndicatorID
              AND t.VersionID = @VersionID
        )
        BEGIN
            UPDATE dbo.tblSbIndicatorTarget
            SET FiscalYearID = @FiscalYearID,
                BaselineValue = @OutputBaselineValue,
                TargetValue = @OutputTargetValue,
                Notes = N'Expanded demo output target for active context.',
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE IndicatorID = @OutputIndicatorID
              AND VersionID = @VersionID;
        END
        ELSE
        BEGIN
            INSERT INTO dbo.tblSbIndicatorTarget (
                IndicatorID,
                VersionID,
                FiscalYearID,
                BaselineValue,
                TargetValue,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @OutputIndicatorID,
                @VersionID,
                @FiscalYearID,
                @OutputBaselineValue,
                @OutputTargetValue,
                N'Expanded demo output target for active context.',
                1,
                1,
                SYSDATETIME()
            );
        END;

        SELECT @ActivityID = a.ActivityID
        FROM dbo.tblSbActivity a
        WHERE a.OutputID = @OutputID
          AND a.ActivityName = @ActivityName;

        IF @ActivityID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbActivity (
                OutputID,
                ActivityName,
                ActivityDescription,
                ActivityTypeCode,
                LocationCode,
                StartDate,
                EndDate,
                ImplementationStatusCode,
                ProcurementRequiredFlag,
                Dependencies,
                RiskNotes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @OutputID,
                @ActivityName,
                @ActivityDescription,
                @ActivityTypeCode,
                @LocationCode,
                DATEFROMPARTS(YEAR(GETDATE()), 7, 1),
                DATEFROMPARTS(YEAR(GETDATE()) + 1, 6, 30),
                N'PLANNED',
                1,
                N'Expanded demo operational dependencies.',
                @RiskDescription,
                1,
                1,
                SYSDATETIME()
            );

            SET @ActivityID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbActivity
            SET ActivityDescription = @ActivityDescription,
                ActivityTypeCode = @ActivityTypeCode,
                LocationCode = @LocationCode,
                ImplementationStatusCode = N'PLANNED',
                ProcurementRequiredFlag = 1,
                Dependencies = N'Expanded demo operational dependencies.',
                RiskNotes = @RiskDescription,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE ActivityID = @ActivityID;
        END;

        IF EXISTS (
            SELECT 1
            FROM dbo.tblSbActivityBudget ab
            WHERE ab.ActivityID = @ActivityID
              AND ab.VersionID = @VersionID
              AND ab.EconomicItemID = @ProgramEconomicItemID
              AND ISNULL(ab.FundingSourceID, 0) = ISNULL(@ProgramFundingSourceID, 0)
              AND ab.ActiveFlag = 1
        )
        BEGIN
            UPDATE dbo.tblSbActivityBudget
            SET FiscalYearID = @FiscalYearID,
                Amount = @BudgetAmount,
                CurrencyCode = N'USD',
                Notes = N'Expanded demo budget line.',
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE ActivityID = @ActivityID
              AND VersionID = @VersionID
              AND EconomicItemID = @ProgramEconomicItemID
              AND ISNULL(FundingSourceID, 0) = ISNULL(@ProgramFundingSourceID, 0)
              AND ActiveFlag = 1;
        END
        ELSE
        BEGIN
            INSERT INTO dbo.tblSbActivityBudget (
                ActivityID,
                VersionID,
                FiscalYearID,
                EconomicItemID,
                FundingSourceID,
                Amount,
                CurrencyCode,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @ActivityID,
                @VersionID,
                @FiscalYearID,
                @ProgramEconomicItemID,
                @ProgramFundingSourceID,
                @BudgetAmount,
                N'USD',
                N'Expanded demo budget line.',
                1,
                1,
                SYSDATETIME()
            );
        END;

        SELECT @ProgramRiskID = fr.FiscalRiskID
        FROM dbo.tblSbFiscalRisk fr
        WHERE fr.RiskTitle = @RiskTitle;

        IF @ProgramRiskID IS NULL
        BEGIN
            INSERT INTO dbo.tblSbFiscalRisk (
                RiskTypeCode,
                RiskTitle,
                RiskDescription,
                LikelihoodScore,
                ImpactScore,
                EstimatedFiscalExposure,
                MitigationStrategy,
                OwnerOrgUnitID,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @RiskTypeCode,
                @RiskTitle,
                @RiskDescription,
                @RiskLikelihood,
                @RiskImpact,
                @BudgetAmount * 0.15,
                N'Expanded demo mitigation action plan.',
                @PlanningOrgUnitID,
                1,
                1,
                SYSDATETIME()
            );

            SET @ProgramRiskID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbFiscalRisk
            SET RiskTypeCode = @RiskTypeCode,
                RiskDescription = @RiskDescription,
                LikelihoodScore = @RiskLikelihood,
                ImpactScore = @RiskImpact,
                EstimatedFiscalExposure = @BudgetAmount * 0.15,
                MitigationStrategy = N'Expanded demo mitigation action plan.',
                OwnerOrgUnitID = @PlanningOrgUnitID,
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE FiscalRiskID = @ProgramRiskID;
        END;

        IF NOT EXISTS (
            SELECT 1
            FROM dbo.tblSbProgramRisk pr
            WHERE pr.ProgramID = @ProgramID
              AND pr.FiscalRiskID = @ProgramRiskID
        )
        BEGIN
            INSERT INTO dbo.tblSbProgramRisk (
                ProgramID,
                FiscalRiskID,
                CreatedBy,
                CreatedDate
            )
            VALUES (
                @ProgramID,
                @ProgramRiskID,
                1,
                SYSDATETIME()
            );
        END;

        FETCH NEXT FROM program_cursor INTO
            @ProgramCode, @ProgramSectorCode, @ProgramName, @ProgramDescription, @ProgramManagerName,
            @SubProgramCode, @SubProgramName, @ObjectiveText, @PolicyLink,
            @OutcomeIndicatorName, @OutcomeIndicatorDefinition, @OutcomeUnit, @OutcomeDataSource, @OutcomeFrequency, @BaselineValue, @TargetValue,
            @OutputName, @OutputDescription,
            @OutputIndicatorName, @OutputIndicatorDefinition, @OutputUnit, @OutputDataSource, @OutputFrequency, @OutputBaselineValue, @OutputTargetValue,
            @ActivityName, @ActivityDescription, @ActivityTypeCode, @LocationCode,
            @ProgramFundingSourceName, @ProgramEconomicCode, @BudgetAmount,
            @RiskTitle, @RiskTypeCode, @RiskDescription, @RiskLikelihood, @RiskImpact;
    END;

    CLOSE program_cursor;
    DEALLOCATE program_cursor;

    DECLARE @Narratives TABLE (
        SectionCode NVARCHAR(30) NOT NULL,
        SortOrder INT NOT NULL,
        NarrativeTitle NVARCHAR(200) NOT NULL,
        BodyText NVARCHAR(MAX) NOT NULL
    );

    INSERT INTO @Narratives (SectionCode, SortOrder, NarrativeTitle, BodyText)
    VALUES
        (N'MACRO', 10, N'Expanded Demo Macro Context', N'Demo macro context covering service demand pressures, inflation risks, and delivery constraints across multiple sectors.'),
        (N'REVENUE', 20, N'Expanded Demo Revenue Strategy', N'Demo revenue strategy combining domestic and grant funding assumptions used in the expanded strategic seed.'),
        (N'EXPENDITURE', 30, N'Expanded Demo Expenditure Strategy', N'Demo expenditure strategy showing how seeded resources are prioritized across health, education, and water delivery programs.'),
        (N'PRIORITIES', 40, N'Expanded Demo Strategic Priorities', N'Demo priorities narrative describing why the seeded programs were selected for protection and expansion in the active context.'),
        (N'RISKS', 50, N'Expanded Demo Fiscal Risks', N'Demo risk narrative summarising supply, logistics, and implementation pressures across the seeded programs.'),
        (N'MTFF', 60, N'Expanded Demo Medium-Term Outlook', N'Demo MTFF narrative showing how the seeded programs could scale over the next planning window.');

    MERGE dbo.tblSbNarrative AS target
    USING (
        SELECT
            @VersionID AS VersionID,
            @FiscalYearID AS FiscalYearID,
            n.SectionCode,
            @PlanningOrgUnitID AS OrgUnitID,
            NULL AS SectorID,
            NULL AS ProgramID,
            n.NarrativeTitle,
            n.BodyText,
            n.SortOrder
        FROM @Narratives n
    ) AS source
    ON target.VersionID = source.VersionID
       AND target.FiscalYearID = source.FiscalYearID
       AND target.SectionCode = source.SectionCode
       AND ISNULL(target.OrgUnitID, 0) = ISNULL(source.OrgUnitID, 0)
       AND target.SectorID IS NULL
       AND target.ProgramID IS NULL
    WHEN MATCHED THEN
        UPDATE SET
            NarrativeTitle = source.NarrativeTitle,
            BodyText = source.BodyText,
            SortOrder = source.SortOrder,
            LockedFlag = 0,
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
    WHEN NOT MATCHED THEN
        INSERT (
            VersionID,
            FiscalYearID,
            SectionCode,
            OrgUnitID,
            SectorID,
            ProgramID,
            NarrativeTitle,
            BodyText,
            SortOrder,
            LockedFlag,
            ActiveFlag,
            CreatedBy,
            CreatedDate
        )
        VALUES (
            source.VersionID,
            source.FiscalYearID,
            source.SectionCode,
            source.OrgUnitID,
            source.SectorID,
            source.ProgramID,
            source.NarrativeTitle,
            source.BodyText,
            source.SortOrder,
            0,
            1,
            1,
            SYSDATETIME()
        );

    IF OBJECT_ID('dbo.tblSbVersionWorkflow', 'U') IS NOT NULL
    BEGIN
        MERGE dbo.tblSbVersionWorkflow AS target
        USING (
            SELECT
                @FiscalYearID AS FiscalYearID,
                @VersionID AS VersionID
        ) AS source
        ON target.FiscalYearID = source.FiscalYearID
           AND target.VersionID = source.VersionID
        WHEN MATCHED THEN
            UPDATE SET
                WorkflowStatusCode = N'DRAFT',
                StatusNote = N'Expanded demo strategic data seeded and ready for review.',
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
        WHEN NOT MATCHED THEN
            INSERT (
                FiscalYearID,
                VersionID,
                WorkflowStatusCode,
                StatusNote,
                CreatedBy,
                CreatedDate,
                UpdatedBy,
                UpdatedDate
            )
            VALUES (
                source.FiscalYearID,
                source.VersionID,
                N'DRAFT',
                N'Expanded demo strategic data seeded and ready for review.',
                1,
                SYSDATETIME(),
                1,
                SYSDATETIME()
            );
    END;

    COMMIT TRAN;

    SELECT
        @FiscalYearID AS FiscalYearID,
        @VersionID AS VersionID,
        (SELECT COUNT(*) FROM @Sectors) AS DemoSectorCount,
        (SELECT COUNT(*) FROM @Programs) AS DemoProgramCount,
        (SELECT COUNT(*) FROM @FundingTypes) AS DemoFundingTypeCount,
        (SELECT COUNT(*) FROM @FundingSources) AS DemoFundingSourceCount,
        (SELECT COUNT(*) FROM @EconomicItems) AS DemoEconomicItemCount,
        (SELECT SUM(BudgetAmount) FROM @Programs) AS DemoBudgetTotal;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;
END CATCH;
GO
