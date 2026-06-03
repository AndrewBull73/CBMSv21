/*
Strategic Budgeting Demo Seed
-----------------------------
Creates one end-to-end demo chain so the Strategic Budgeting module
has visible data in setup, delivery, performance, governance, and reports.

Safe to re-run:
- uses DEMO-prefixed codes/names
- reactivates and updates matching demo rows where possible
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

BEGIN TRY
    BEGIN TRAN;

    DECLARE @FiscalYearID INT;
    DECLARE @VersionID INT;

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
        THROW 51001, 'No fiscal year exists. Seed tblFiscalYears and tblVersions first.', 1;
    END;

    SELECT TOP (1) @VersionID = v.VersionID
    FROM dbo.tblVersions v
    WHERE v.FiscalYearID = @FiscalYearID
      AND ISNULL(v.IsActive, 1) = 1
    ORDER BY ISNULL(v.IsDefault, 0) DESC, v.VersionID ASC;

    IF @VersionID IS NULL
    BEGIN
        THROW 51002, 'No active version exists for the selected fiscal year.', 1;
    END;

    DECLARE
        @OrgUnitID INT,
        @SectorID INT,
        @FundingTypeID INT,
        @FundingSourceID INT,
        @EconomicItemID INT,
        @ProgramID INT,
        @SubProgramID INT,
        @OutcomeIndicatorID INT,
        @OutputIndicatorID INT,
        @ObjectiveID INT,
        @OutputID INT,
        @ActivityID INT,
        @FiscalRiskID INT;

    SELECT TOP (1) @OrgUnitID = ou.OrgUnitID
    FROM dbo.tblSbOrgUnit ou
    WHERE ou.ActiveFlag = 1
    ORDER BY CASE WHEN ou.SourceFiscalYearID = @FiscalYearID THEN 0 ELSE 1 END, ou.OrgUnitID ASC;

    IF @OrgUnitID IS NULL
    BEGIN
        SELECT @OrgUnitID = ou.OrgUnitID
        FROM dbo.tblSbOrgUnit ou
        WHERE ou.VoteCode = N'SB-DEMO-ORG';

        IF @OrgUnitID IS NULL
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
                N'SB-DEMO-ORG',
                N'Demo Strategic Vote',
                1,
                1,
                SYSDATETIME()
            );

            SET @OrgUnitID = CAST(SCOPE_IDENTITY() AS INT);
        END
        ELSE
        BEGIN
            UPDATE dbo.tblSbOrgUnit
            SET OrgUnitName = N'Demo Strategic Vote',
                OrgUnitTypeCode = N'VOTE',
                ActiveFlag = 1,
                UpdatedBy = 1,
                UpdatedDate = SYSDATETIME()
            WHERE OrgUnitID = @OrgUnitID;
        END;
    END;

    SELECT @SectorID = s.SectorID
    FROM dbo.tblSbSector s
    WHERE s.SectorName = N'DEMO Health and Community Services';

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
            N'DEMO Health and Community Services',
            N'Demo policy sector used to illustrate how strategic programs roll into outputs, activities, budgets, targets, and narratives.',
            1,
            1,
            SYSDATETIME()
        );

        SET @SectorID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbSector
        SET SectorDescription = N'Demo policy sector used to illustrate how strategic programs roll into outputs, activities, budgets, targets, and narratives.',
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE SectorID = @SectorID;
    END;

    SELECT @FundingTypeID = ft.FundingTypeID
    FROM dbo.tblSbFundingType ft
    WHERE ft.FundingTypeCode = N'DEMO-DOM';

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
            N'DEMO-DOM',
            N'Demo Domestic Funding',
            N'Demo funding type for seeded strategic budget data.',
            1,
            1,
            SYSDATETIME()
        );

        SET @FundingTypeID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbFundingType
        SET FundingTypeName = N'Demo Domestic Funding',
            FundingTypeDescription = N'Demo funding type for seeded strategic budget data.',
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE FundingTypeID = @FundingTypeID;
    END;

    SELECT @FundingSourceID = fs.FundingSourceID
    FROM dbo.tblSbFundingSource fs
    WHERE fs.FundingSourceName = N'Demo Consolidated Fund';

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
            N'DEMO-DOM',
            N'Demo Consolidated Fund',
            NULL,
            N'Demo seeded funding source for strategic budget walkthroughs.',
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
            FundingTypeCode = N'DEMO-DOM',
            DonorName = NULL,
            ConditionsText = N'Demo seeded funding source for strategic budget walkthroughs.',
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE FundingSourceID = @FundingSourceID;
    END;

    SELECT @EconomicItemID = ei.EconomicItemID
    FROM dbo.tblSbEconomicItem ei
    WHERE ei.EconomicCode = N'DEMO-2210';

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
            N'DEMO-2210',
            N'Demo Goods and Services',
            2,
            1,
            1,
            SYSDATETIME()
        );

        SET @EconomicItemID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbEconomicItem
        SET EconomicName = N'Demo Goods and Services',
            EconomicLevel = 2,
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE EconomicItemID = @EconomicItemID;
    END;

    SELECT @ProgramID = p.ProgramID
    FROM dbo.tblSbProgram p
    WHERE p.ProgramCode = N'DEMO-PROG-001'
      AND p.OrgUnitID = @OrgUnitID;

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
            @OrgUnitID,
            @SectorID,
            N'DEMO-PROG-001',
            N'Demo Primary Care Access Program',
            N'Demo strategic program showing how a policy priority links to outputs, activities, budget lines, indicators, risks, and BSP narratives.',
            N'Demo Programme Manager',
            1,
            1,
            SYSDATETIME()
        );

        SET @ProgramID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbProgram
        SET SectorID = @SectorID,
            ProgramName = N'Demo Primary Care Access Program',
            ProgramDescription = N'Demo strategic program showing how a policy priority links to outputs, activities, budget lines, indicators, risks, and BSP narratives.',
            ProgramManagerName = N'Demo Programme Manager',
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE ProgramID = @ProgramID;
    END;

    SELECT @SubProgramID = sp.SubProgramID
    FROM dbo.tblSbSubProgram sp
    WHERE sp.ProgramID = @ProgramID
      AND sp.SubProgramCode = N'DEMO-SUB-001';

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
            N'DEMO-SUB-001',
            N'Demo Community Outreach',
            N'Demo subprogram focused on outreach and frontline service delivery.',
            1,
            1,
            SYSDATETIME()
        );

        SET @SubProgramID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbSubProgram
        SET SubProgramName = N'Demo Community Outreach',
            SubProgramDescription = N'Demo subprogram focused on outreach and frontline service delivery.',
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE SubProgramID = @SubProgramID;
    END;

    SELECT @ObjectiveID = o.ObjectiveID
    FROM dbo.tblSbObjective o
    WHERE o.ProgramID = @ProgramID
      AND o.ObjectiveText = N'Expand access to essential primary care services in underserved communities.';

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
            N'Expand access to essential primary care services in underserved communities.',
            N'DEMO-NDP-1',
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
            PolicyLink = N'DEMO-NDP-1',
            PriorityRank = 1,
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE ObjectiveID = @ObjectiveID;
    END;

    SELECT @OutcomeIndicatorID = i.IndicatorID
    FROM dbo.tblSbIndicator i
    WHERE i.IndicatorTypeCode = N'OUTCOME'
      AND i.IndicatorName = N'Demo households within reach of primary care';

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
            N'Demo households within reach of primary care',
            N'Share of households in target communities that can access a primary care point within the defined service radius.',
            N'Percent',
            N'Demo HMIS',
            N'ANNUAL',
            N'Region/Sex',
            N'Demo indicator used for strategic module walkthroughs.',
            1,
            1,
            SYSDATETIME()
        );

        SET @OutcomeIndicatorID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbIndicator
        SET IndicatorDefinition = N'Share of households in target communities that can access a primary care point within the defined service radius.',
            UnitOfMeasure = N'Percent',
            DataSource = N'Demo HMIS',
            FrequencyCode = N'ANNUAL',
            Disaggregation = N'Region/Sex',
            QualityNotes = N'Demo indicator used for strategic module walkthroughs.',
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE IndicatorID = @OutcomeIndicatorID;
    END;

    SELECT @OutputIndicatorID = i.IndicatorID
    FROM dbo.tblSbIndicator i
    WHERE i.IndicatorTypeCode = N'OUTPUT'
      AND i.IndicatorName = N'Demo outreach sessions delivered';

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
            N'Demo outreach sessions delivered',
            N'Count of mobile outreach sessions completed during the fiscal year.',
            N'Number',
            N'Demo Outreach Register',
            N'QUARTERLY',
            N'Region',
            N'Demo output indicator used for strategic module walkthroughs.',
            1,
            1,
            SYSDATETIME()
        );

        SET @OutputIndicatorID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbIndicator
        SET IndicatorDefinition = N'Count of mobile outreach sessions completed during the fiscal year.',
            UnitOfMeasure = N'Number',
            DataSource = N'Demo Outreach Register',
            FrequencyCode = N'QUARTERLY',
            Disaggregation = N'Region',
            QualityNotes = N'Demo output indicator used for strategic module walkthroughs.',
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE IndicatorID = @OutputIndicatorID;
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
            BaselineValue = 42.000000,
            TargetValue = 58.000000,
            Notes = N'Demo target for strategic walkthrough.',
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
            42.000000,
            58.000000,
            N'Demo target for strategic walkthrough.',
            1,
            1,
            SYSDATETIME()
        );
    END;

    SELECT @OutputID = o.OutputID
    FROM dbo.tblSbOutput o
    WHERE o.ProgramID = @ProgramID
      AND o.OutputName = N'Demo mobile primary care outreach delivered';

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
            N'Demo mobile primary care outreach delivered',
            N'Demo output representing frontline outreach delivery in remote communities.',
            @OrgUnitID,
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
            OutputDescription = N'Demo output representing frontline outreach delivery in remote communities.',
            OutputOwnerOrgUnitID = @OrgUnitID,
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE OutputID = @OutputID;
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
            BaselineValue = 24.000000,
            TargetValue = 36.000000,
            Notes = N'Demo output target for strategic walkthrough.',
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
            24.000000,
            36.000000,
            N'Demo output target for strategic walkthrough.',
            1,
            1,
            SYSDATETIME()
        );
    END;

    SELECT @ActivityID = a.ActivityID
    FROM dbo.tblSbActivity a
    WHERE a.OutputID = @OutputID
      AND a.ActivityName = N'Demo deploy monthly mobile clinic sessions';

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
            N'Demo deploy monthly mobile clinic sessions',
            N'Demo activity used to illustrate costing and implementation tracking.',
            N'PROJECT',
            N'DEMO-REGION-01',
            DATEFROMPARTS(YEAR(GETDATE()), 7, 1),
            DATEFROMPARTS(YEAR(GETDATE()) + 1, 6, 30),
            N'PLANNED',
            1,
            N'Demo vehicle availability and clinical staffing plan.',
            N'Demo fuel and logistics cost volatility.',
            1,
            1,
            SYSDATETIME()
        );

        SET @ActivityID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbActivity
        SET ActivityDescription = N'Demo activity used to illustrate costing and implementation tracking.',
            ActivityTypeCode = N'PROJECT',
            LocationCode = N'DEMO-REGION-01',
            ImplementationStatusCode = N'PLANNED',
            ProcurementRequiredFlag = 1,
            Dependencies = N'Demo vehicle availability and clinical staffing plan.',
            RiskNotes = N'Demo fuel and logistics cost volatility.',
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
          AND ab.EconomicItemID = @EconomicItemID
          AND ISNULL(ab.FundingSourceID, 0) = ISNULL(@FundingSourceID, 0)
          AND ab.ActiveFlag = 1
    )
    BEGIN
        UPDATE dbo.tblSbActivityBudget
        SET FiscalYearID = @FiscalYearID,
            Amount = 1500000.00,
            CurrencyCode = N'USD',
            Notes = N'Demo budget line for outreach delivery.',
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE ActivityID = @ActivityID
          AND VersionID = @VersionID
          AND EconomicItemID = @EconomicItemID
          AND ISNULL(FundingSourceID, 0) = ISNULL(@FundingSourceID, 0)
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
            @EconomicItemID,
            @FundingSourceID,
            1500000.00,
            N'USD',
            N'Demo budget line for outreach delivery.',
            1,
            1,
            SYSDATETIME()
        );
    END;

    SELECT @FiscalRiskID = fr.FiscalRiskID
    FROM dbo.tblSbFiscalRisk fr
    WHERE fr.RiskTitle = N'Demo medical supply disruption risk';

    IF @FiscalRiskID IS NULL
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
            N'OTHER',
            N'Demo medical supply disruption risk',
            N'Demo fiscal risk showing how risks can be linked back to strategic programs.',
            3,
            4,
            250000.00,
            N'Demo framework contracts and buffer stock arrangements.',
            @OrgUnitID,
            1,
            1,
            SYSDATETIME()
        );

        SET @FiscalRiskID = CAST(SCOPE_IDENTITY() AS INT);
    END
    ELSE
    BEGIN
        UPDATE dbo.tblSbFiscalRisk
        SET RiskTypeCode = N'OTHER',
            RiskDescription = N'Demo fiscal risk showing how risks can be linked back to strategic programs.',
            LikelihoodScore = 3,
            ImpactScore = 4,
            EstimatedFiscalExposure = 250000.00,
            MitigationStrategy = N'Demo framework contracts and buffer stock arrangements.',
            OwnerOrgUnitID = @OrgUnitID,
            ActiveFlag = 1,
            UpdatedBy = 1,
            UpdatedDate = SYSDATETIME()
        WHERE FiscalRiskID = @FiscalRiskID;
    END;

    IF NOT EXISTS (
        SELECT 1
        FROM dbo.tblSbProgramRisk pr
        WHERE pr.ProgramID = @ProgramID
          AND pr.FiscalRiskID = @FiscalRiskID
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
            @FiscalRiskID,
            1,
            SYSDATETIME()
        );
    END;

    DECLARE @Narratives TABLE (
        SectionCode NVARCHAR(30) NOT NULL,
        SortOrder INT NOT NULL,
        NarrativeTitle NVARCHAR(200) NOT NULL,
        BodyText NVARCHAR(MAX) NOT NULL
    );

    INSERT INTO @Narratives (SectionCode, SortOrder, NarrativeTitle, BodyText)
    VALUES
        (N'MACRO', 10, N'Demo Macro Context', N'Demo macro narrative explaining the operating environment for the strategic budget example.'),
        (N'REVENUE', 20, N'Demo Revenue Strategy', N'Demo revenue narrative showing how the financing assumptions support the seeded program.'),
        (N'EXPENDITURE', 30, N'Demo Expenditure Strategy', N'Demo expenditure narrative linking the outreach activity budget to service delivery priorities.'),
        (N'PRIORITIES', 40, N'Demo Strategic Priorities', N'Demo priority narrative describing the policy case for expanding outreach access.'),
        (N'RISKS', 50, N'Demo Fiscal Risks', N'Demo risk narrative referencing supply disruption exposure and mitigation actions.'),
        (N'MTFF', 60, N'Demo Medium-Term Outlook', N'Demo MTFF narrative describing how the program could scale over the planning horizon.');

    MERGE dbo.tblSbNarrative AS target
    USING (
        SELECT
            @VersionID AS VersionID,
            @FiscalYearID AS FiscalYearID,
            n.SectionCode,
            @OrgUnitID AS OrgUnitID,
            @SectorID AS SectorID,
            @ProgramID AS ProgramID,
            n.NarrativeTitle,
            n.BodyText,
            n.SortOrder
        FROM @Narratives n
    ) AS source
    ON target.VersionID = source.VersionID
       AND target.FiscalYearID = source.FiscalYearID
       AND target.SectionCode = source.SectionCode
       AND ISNULL(target.ProgramID, 0) = ISNULL(source.ProgramID, 0)
       AND ISNULL(target.SectorID, 0) = ISNULL(source.SectorID, 0)
       AND ISNULL(target.OrgUnitID, 0) = ISNULL(source.OrgUnitID, 0)
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
        IF NOT EXISTS (
            SELECT 1
            FROM dbo.tblSbVersionWorkflow w
            WHERE w.FiscalYearID = @FiscalYearID
              AND w.VersionID = @VersionID
        )
        BEGIN
            INSERT INTO dbo.tblSbVersionWorkflow (
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
                @FiscalYearID,
                @VersionID,
                N'DRAFT',
                N'Demo strategic data seeded and ready for review.',
                1,
                SYSDATETIME(),
                1,
                SYSDATETIME()
            );
        END;
    END;

    COMMIT TRAN;

    SELECT
        @FiscalYearID AS FiscalYearID,
        @VersionID AS VersionID,
        @OrgUnitID AS OrgUnitID,
        @SectorID AS SectorID,
        @ProgramID AS ProgramID,
        @SubProgramID AS SubProgramID,
        @ObjectiveID AS ObjectiveID,
        @OutcomeIndicatorID AS OutcomeIndicatorID,
        @OutputIndicatorID AS OutputIndicatorID,
        @OutputID AS OutputID,
        @ActivityID AS ActivityID,
        @EconomicItemID AS EconomicItemID,
        @FundingTypeID AS FundingTypeID,
        @FundingSourceID AS FundingSourceID,
        @FiscalRiskID AS FiscalRiskID,
        CAST(1500000.00 AS DECIMAL(18,2)) AS DemoBudgetAmount;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0
        ROLLBACK TRAN;

    THROW;
END CATCH;
GO
