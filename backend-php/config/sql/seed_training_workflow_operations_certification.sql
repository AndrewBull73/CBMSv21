SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblTrainingCertifications', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblTrainingCertificationQuestions', N'U') IS NOT NULL
BEGIN
    MERGE dbo.tblTrainingCertifications AS target
    USING (
        SELECT
            N'workflow_ops_certification' AS CertificationCode,
            N'Workflow Operations Certification' AS CertificationTitle,
            N'Workflow Operations' AS ModuleName,
            N'Final module certification covering Workflow Operations governance, projects, requirements, traceability, issues, and task follow-up.' AS Description,
            CAST(80.00 AS DECIMAL(5,2)) AS PassPercent,
            CAST(1 AS BIT) AS ActiveFlag,
            210 AS SortOrder
    ) AS source
    ON target.CertificationCode = source.CertificationCode
    WHEN MATCHED THEN
        UPDATE SET
            CertificationTitle = source.CertificationTitle,
            ModuleName = source.ModuleName,
            Description = source.Description,
            PassPercent = source.PassPercent,
            ActiveFlag = source.ActiveFlag,
            SortOrder = source.SortOrder,
            UpdatedDate = SYSUTCDATETIME()
    WHEN NOT MATCHED THEN
        INSERT
            (CertificationCode, CertificationTitle, ModuleName, Description, PassPercent, ActiveFlag, SortOrder)
        VALUES
            (source.CertificationCode, source.CertificationTitle, source.ModuleName, source.Description, source.PassPercent, source.ActiveFlag, source.SortOrder);

    DELETE FROM dbo.tblTrainingCertificationQuestions
    WHERE CertificationCode = N'workflow_ops_certification';

    INSERT INTO dbo.tblTrainingCertificationQuestions
        (CertificationCode, QuestionNo, QuestionText, OptionsJson, CorrectOptionKey, Explanation, ActiveFlag, SortOrder)
    VALUES
        (
            N'workflow_ops_certification',
            1,
            N'What is the main governance benefit of keeping Workflow Operations inside CBMS?',
            N'[{"key":"A","label":"It removes the need to assign owners to work."},{"key":"B","label":"It links projects, requirements, tasks, issues, evidence, and ownership in one governed record set."},{"key":"C","label":"It makes every project read-only after creation."},{"key":"D","label":"It only provides a different layout for existing reports."}]',
            N'B',
            N'Workflow Operations improves governance by keeping related delivery records connected and visible across the lifecycle.',
            1,
            10
        ),
        (
            N'workflow_ops_certification',
            2,
            N'In Workflow Operations, what role do projects usually play?',
            N'[{"key":"A","label":"They are containers for related requirements, tasks, issues, ownership, dates, and evidence."},{"key":"B","label":"They only store archived records that cannot be edited."},{"key":"C","label":"They replace workflow tasks and issue records."},{"key":"D","label":"They are used only for login permissions."}]',
            N'A',
            N'Projects provide the delivery context for the related operational records.',
            1,
            20
        ),
        (
            N'workflow_ops_certification',
            3,
            N'Why is a clear project owner important?',
            N'[{"key":"A","label":"The owner automatically completes every task."},{"key":"B","label":"The owner identifies who coordinates decisions, accountability, and follow-up."},{"key":"C","label":"The owner prevents team members from viewing the project."},{"key":"D","label":"The owner is used only for export filenames."}]',
            N'B',
            N'Ownership is a core governance control because it makes accountability explicit.',
            1,
            30
        ),
        (
            N'workflow_ops_certification',
            4,
            N'What is the purpose of filtering the requirements register by project?',
            N'[{"key":"A","label":"To hide requirements from all reports."},{"key":"B","label":"To keep scope, ownership, and delivery review focused on the selected project."},{"key":"C","label":"To permanently delete requirements outside the selected project."},{"key":"D","label":"To convert requirements into issues."}]',
            N'B',
            N'Project filtering helps users review the requirements that belong to a specific delivery scope.',
            1,
            40
        ),
        (
            N'workflow_ops_certification',
            5,
            N'What does the Requirements Matrix help reviewers assess?',
            N'[{"key":"A","label":"Only the spelling of requirement titles."},{"key":"B","label":"Coverage, linked tasks, evidence, gaps, testing, training, and issue impacts."},{"key":"C","label":"Only user login activity."},{"key":"D","label":"Only budget transaction totals."}]',
            N'B',
            N'The matrix is designed for traceability and readiness review across connected workflow records.',
            1,
            50
        ),
        (
            N'workflow_ops_certification',
            6,
            N'Which situation should normally be recorded in the Workflow Issues Log?',
            N'[{"key":"A","label":"A blocker, defect, decision, or risk that needs ownership and resolution tracking."},{"key":"B","label":"A user preference for page colors."},{"key":"C","label":"A completed project with no follow-up needed."},{"key":"D","label":"A duplicate of every task regardless of whether there is an issue."}]',
            N'A',
            N'Issues should capture items needing visibility, ownership, and resolution follow-up.',
            1,
            60
        ),
        (
            N'workflow_ops_certification',
            7,
            N'How does issue severity support governance?',
            N'[{"key":"A","label":"It prevents all low severity issues from being stored."},{"key":"B","label":"It highlights issues with greater delivery or quality impact so reviewers can focus attention."},{"key":"C","label":"It changes the project owner automatically."},{"key":"D","label":"It controls which browser is used."}]',
            N'B',
            N'Severity helps prioritise issues according to potential impact.',
            1,
            70
        ),
        (
            N'workflow_ops_certification',
            8,
            N'What is the task queue primarily used for?',
            N'[{"key":"A","label":"Operational follow-up, assigned work, due dates, and day-to-day control."},{"key":"B","label":"Storing module help text only."},{"key":"C","label":"Replacing all project and requirement records."},{"key":"D","label":"Changing fiscal years and versions."}]',
            N'A',
            N'The task queue is the operational work list for assigned follow-up and project activity.',
            1,
            80
        ),
        (
            N'workflow_ops_certification',
            9,
            N'Why are exports from project, requirement, issue, or task screens useful?',
            N'[{"key":"A","label":"They erase the source records after export."},{"key":"B","label":"They support offline governance review, meeting packs, readiness packs, and follow-up."},{"key":"C","label":"They replace the need to maintain data in CBMS."},{"key":"D","label":"They are only cosmetic and have no governance use."}]',
            N'B',
            N'Exports help teams review and communicate governed workflow information outside the live screen when needed.',
            1,
            90
        ),
        (
            N'workflow_ops_certification',
            10,
            N'Which statement best describes good use of Workflow Operations across the lifecycle?',
            N'[{"key":"A","label":"Create records once and avoid updating them as delivery changes."},{"key":"B","label":"Use connected projects, requirements, tasks, issues, evidence, and ownership to maintain visibility and control."},{"key":"C","label":"Track tasks outside CBMS so governance records stay smaller."},{"key":"D","label":"Use the module only after all work is completed."}]',
            N'B',
            N'Good lifecycle use keeps connected records current so governance, quality, and support handover remain reliable.',
            1,
            100
        );
END;
GO
