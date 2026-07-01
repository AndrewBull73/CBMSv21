SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblTrainingScenarios', N'U') IS NULL
   OR OBJECT_ID(N'dbo.tblTrainingScenarioSteps', N'U') IS NULL
BEGIN
    RAISERROR('Training scenario catalogue tables were not found. Run create_training_scenario_catalog.sql before seed_training_training_module.sql.', 16, 1);
    RETURN;
END;
GO

MERGE dbo.tblTrainingScenarios AS target
USING (
    SELECT
        ScenarioCode,
        ScenarioTitle,
        ScreenFamily,
        ModuleName,
        Audience,
        Difficulty,
        Description,
        RunnerRoute,
        NextScenarioCode,
        PrerequisitesJson,
        ActiveFlag,
        SortOrder
    FROM (VALUES
        (
            N'training_module_overview',
            N'Training Module Overview',
            N'training',
            N'Training',
            N'Training administrators, training configuration users, implementation teams',
            N'Introductory',
            N'Learn the purpose of the Training module and the recommended order for setting up scenarios, paths, assignments, certifications, and reporting.',
            N'training/runner',
            N'training_catalogue_setup',
            N'["Training features are enabled.","The user has Training Administration or Training Configuration access.","The Training Module Overview help is available."]',
            CAST(1 AS BIT),
            310
        ),
        (
            N'training_catalogue_setup',
            N'Training Catalogue Setup Orientation',
            N'training-admin-scenarios',
            N'Training',
            N'Training configuration users, implementation teams',
            N'Introductory',
            N'Practise reviewing the Training Catalogue and understand how scenario records define module, audience, runner route, order, prerequisites, samples, and launch behaviour.',
            N'training/runner',
            N'training_step_builder',
            N'["Training scenario catalogue tables are installed.","The user has Training Configuration access.","At least one scenario is recommended for a richer exercise."]',
            CAST(1 AS BIT),
            320
        ),
        (
            N'training_step_builder',
            N'Training Step Builder Orientation',
            N'training-admin-steps',
            N'Training',
            N'Training configuration users, implementation teams',
            N'Intermediate',
            N'Learn how step definitions drive the guided overlay by linking route, target element, completion mode, instructions, samples, and checkpoints.',
            N'training/runner',
            N'training_validation_review',
            N'["Training scenario steps table is installed.","The user has Training Configuration access.","A scenario exists before maintaining steps."]',
            CAST(1 AS BIT),
            330
        ),
        (
            N'training_validation_review',
            N'Training Validation Review',
            N'training-admin-validation',
            N'Training',
            N'Training administrators, training configuration users, implementation teams',
            N'Introductory',
            N'Use Training Validation to review setup quality before assigning scenarios to users.',
            N'training/runner',
            N'training_operations_assignment',
            N'["Training validation screen is available.","The user has Training Administration or Training Configuration access.","Scenario records and steps exist for validation."]',
            CAST(1 AS BIT),
            340
        ),
        (
            N'training_operations_assignment',
            N'Training Operations Path And Assignment Setup',
            N'training-admin-operations',
            N'Training',
            N'Training administrators, implementation leads',
            N'Intermediate',
            N'Practise reviewing Training Operations, creating paths, assigning training to users, and understanding how assignments control what appears on the user Training Dashboard.',
            N'training/runner',
            N'training_certification_setup',
            N'["Training management feature tables are installed.","The user has Training Administration access.","Users and scenario records exist before assigning training."]',
            CAST(1 AS BIT),
            350
        ),
        (
            N'training_certification_setup',
            N'Training Certification Setup Orientation',
            N'training-certifications-admin',
            N'Training',
            N'Training configuration users, training administrators',
            N'Intermediate',
            N'Learn how module certifications, pass marks, questions, answer options, and explanations are configured for final module tests.',
            N'training/runner',
            N'training_dashboard_review',
            N'["Training certification tables are installed.","The user has Training Configuration access.","At least one module certification is recommended for a richer exercise."]',
            CAST(1 AS BIT),
            360
        ),
        (
            N'training_dashboard_review',
            N'Training Dashboard Learner Review',
            N'training-dashboard',
            N'Training',
            N'Training users, training administrators, implementation teams',
            N'Introductory',
            N'Review the learner dashboard and understand how assigned scenarios and certification status appear to the logged-in user.',
            N'training/runner',
            N'training_module_overview',
            N'["The logged-in user has assigned training or certification records for a richer exercise.","The user has Training User access.","Training progress and certification tables are installed."]',
            CAST(1 AS BIT),
            370
        )
    ) AS src (
        ScenarioCode,
        ScenarioTitle,
        ScreenFamily,
        ModuleName,
        Audience,
        Difficulty,
        Description,
        RunnerRoute,
        NextScenarioCode,
        PrerequisitesJson,
        ActiveFlag,
        SortOrder
    )
) AS source
ON target.ScenarioCode = source.ScenarioCode
WHEN MATCHED THEN
    UPDATE SET
        ScenarioTitle = source.ScenarioTitle,
        ScreenFamily = source.ScreenFamily,
        ModuleName = source.ModuleName,
        Audience = source.Audience,
        Difficulty = source.Difficulty,
        Description = source.Description,
        RunnerRoute = source.RunnerRoute,
        NextScenarioCode = source.NextScenarioCode,
        PrerequisitesJson = source.PrerequisitesJson,
        ActiveFlag = source.ActiveFlag,
        SortOrder = source.SortOrder,
        UpdatedDate = SYSDATETIME()
WHEN NOT MATCHED THEN
    INSERT
        (ScenarioCode, ScenarioTitle, ScreenFamily, ModuleName, Audience, Difficulty, Description, RunnerRoute, NextScenarioCode, PrerequisitesJson, ActiveFlag, SortOrder)
    VALUES
        (source.ScenarioCode, source.ScenarioTitle, source.ScreenFamily, source.ModuleName, source.Audience, source.Difficulty, source.Description, source.RunnerRoute, source.NextScenarioCode, source.PrerequisitesJson, source.ActiveFlag, source.SortOrder);
GO

DELETE FROM dbo.tblTrainingScenarioSteps
WHERE ScenarioCode IN (
    N'training_module_overview',
    N'training_catalogue_setup',
    N'training_step_builder',
    N'training_validation_review',
    N'training_operations_assignment',
    N'training_certification_setup',
    N'training_dashboard_review'
);
GO

INSERT INTO dbo.tblTrainingScenarioSteps
    (ScenarioCode, StepNo, Route, TargetElementID, StepTitle, InstructionText, CompletionMode, SampleKey, ExpectedUserSampleKey, ActiveFlag, SortOrder)
VALUES
    (N'training_module_overview', 1, N'training/dashboard', N'trainingModuleOverviewHelpBtn', N'Open the Training module overview', N'Click Module Overview and review the Training concept, core screens, recommended setup order, and setup checklist.', N'manual_continue', NULL, NULL, 1, 10),
    (N'training_module_overview', 2, N'training/dashboard', N'helpBtn', N'Compare screen help', N'Open the normal Help button and compare route-specific help with the wider Training Module Overview.', N'manual_continue', NULL, NULL, 1, 20),
    (N'training_module_overview', 3, N'training/dashboard', N'training-dashboard-overview', N'Review dashboard status tiles', N'Review the dashboard overview tiles. These summarise assigned completion, active training, certified modules, and modules shown for the logged-in user.', N'manual_continue', NULL, NULL, 1, 30),
    (N'training_module_overview', 4, N'training/dashboard', N'training-dashboard-modules', N'Review module cards', N'Review the module cards area. Assigned training and related certifications appear here after an administrator assigns scenarios or paths.', N'manual_continue', NULL, NULL, 1, 40),

    (N'training_catalogue_setup', 1, N'training-admin/scenarios', N'training-catalogue-filter-form', N'Review catalogue filters', N'Find the catalogue filters. Configuration users use these to find scenario records by module, status, code, title, or audience.', N'manual_continue', NULL, NULL, 1, 10),
    (N'training_catalogue_setup', 2, N'training-admin/scenarios', N'training-catalogue-create-scenario-btn', N'Review create scenario action', N'Find Create Scenario. New guided exercises start with a scenario shell before steps are added.', N'manual_continue', NULL, NULL, 1, 20),
    (N'training_catalogue_setup', 3, N'training-admin/scenarios', N'training-catalogue-table', N'Review scenario rows', N'Review the catalogue table and note the order, scenario code, module, runner route, step count, samples, translations, and action buttons.', N'manual_continue', NULL, NULL, 1, 30),
    (N'training_catalogue_setup', 4, N'training-admin/scenario-form', N'TrainingScenarioCode', N'Review scenario code', N'Find Scenario Code. Keep this stable because assignments, progress, steps, translations, and links depend on it.', N'manual_continue', NULL, NULL, 1, 40),
    (N'training_catalogue_setup', 5, N'training-admin/scenario-form', N'TrainingScenarioModuleName', N'Review module name', N'Find Module. Module names group scenarios on the catalogue, dashboard, summary, and filters.', N'manual_continue', NULL, NULL, 1, 50),
    (N'training_catalogue_setup', 6, N'training-admin/scenario-form', N'TrainingScenarioRunnerRoute', N'Review runner route', N'Find Runner Route. Most new catalogue scenarios should use training/runner so the generic runner can launch the guided flow.', N'manual_continue', NULL, NULL, 1, 60),
    (N'training_catalogue_setup', 7, N'training-admin/scenario-form', N'TrainingScenarioPrerequisites', N'Review prerequisites', N'Find Prerequisites. Use this area to tell trainers and users what must exist before the scenario is useful.', N'manual_continue', NULL, NULL, 1, 70),
    (N'training_catalogue_setup', 8, N'training-admin/scenario-form', N'training-scenario-save-btn', N'Review save scenario action', N'Find Save Scenario. Saving the shell makes the scenario available for steps, translations, paths, and assignments.', N'manual_continue', NULL, NULL, 1, 80),

    (N'training_step_builder', 1, N'training-admin/step-form', N'TrainingStepScenarioCode', N'Review scenario selector', N'Find the Scenario selector. Each step belongs to exactly one scenario.', N'manual_continue', NULL, NULL, 1, 10),
    (N'training_step_builder', 2, N'training-admin/step-form', N'TrainingStepNo', N'Review step number', N'Find Step No. Step numbers and sort order control the guided sequence shown to the trainee.', N'manual_continue', NULL, NULL, 1, 20),
    (N'training_step_builder', 3, N'training-admin/step-form', N'TrainingStepRoute', N'Review target route', N'Find Route. This must match the live CBMS route where the guided overlay should appear.', N'manual_continue', NULL, NULL, 1, 30),
    (N'training_step_builder', 4, N'training-admin/step-form', N'TrainingStepTargetElementID', N'Review target element', N'Find Target Element ID. This must match the id attribute of the field, button, table, or section that the overlay should highlight.', N'manual_continue', NULL, NULL, 1, 40),
    (N'training_step_builder', 5, N'training-admin/step-form', N'TrainingStepCompletionMode', N'Review completion mode', N'Find Completion Mode. Use click target for required clicks and manual continue when the trainee must read or review information.', N'manual_continue', NULL, NULL, 1, 50),
    (N'training_step_builder', 6, N'training-admin/step-form', N'TrainingStepInstructionText', N'Review instruction text', N'Find Instruction Text. Keep instructions clear, short, and focused on what the trainee should do and why it matters.', N'manual_continue', NULL, NULL, 1, 60),
    (N'training_step_builder', 7, N'training-admin/step-form', N'training-step-save-btn', N'Review save step action', N'Find Save Step. Saving the step updates the guided scenario flow.', N'manual_continue', NULL, NULL, 1, 70),

    (N'training_validation_review', 1, N'training-admin/validation', N'training-validation-runbook', N'Review validation runbook', N'Review the validation runbook. Validation helps identify missing routes, broken target IDs, inactive content, ordering issues, and other setup risks.', N'manual_continue', NULL, NULL, 1, 10),
    (N'training_validation_review', 2, N'training-admin/matrix', N'training-matrix-runbook', N'Review training matrix', N'Review the matrix runbook. The matrix supports planning, role alignment, scenario coverage, and rollout governance.', N'manual_continue', NULL, NULL, 1, 20),
    (N'training_validation_review', 3, N'training-admin/matrix', N'trainingMatrixPathFilter', N'Review path filter', N'Find the path filter. Use it to focus the matrix on one learning path or rollout sequence.', N'manual_continue', NULL, NULL, 1, 30),
    (N'training_validation_review', 4, N'training-admin/matrix', N'trainingMatrixSearchFilter', N'Review matrix search', N'Find the matrix search field. Search helps locate scenarios, screens, roles, or permissions in the training plan.', N'manual_continue', NULL, NULL, 1, 40),

    (N'training_operations_assignment', 1, N'training-admin/operations', N'training-operations-runbook', N'Review operations runbook', N'Review the Training Operations runbook. Courses/paths, user assignments, trainer sessions, support, and cleanup are separated into tabs so each task has a clear purpose.', N'manual_continue', NULL, NULL, 1, 10),
    (N'training_operations_assignment', 2, N'training-admin/operations', N'TrainingSelectedPathCode', N'Select the working course/path', N'Find Selected Course / Path. Choosing a path keeps it active while you review the course, edit it, or create user assignments.', N'manual_continue', NULL, NULL, 1, 20),
    (N'training_operations_assignment', 3, N'training-admin/operations', N'TrainingPathCode', N'Review path code', N'Find Course / Path Code. A path stores the training course and groups scenarios into a reusable learning sequence for a module or audience.', N'manual_continue', NULL, NULL, 1, 30),
    (N'training_operations_assignment', 4, N'training-admin/operations', N'TrainingPathScenarioCodes', N'Review path scenario codes', N'Find Scenario Codes. The ordered list of scenario codes determines which scenarios belong to the course/path.', N'manual_continue', NULL, NULL, 1, 40),
    (N'training_operations_assignment', 5, N'training-admin/operations', N'training-path-save-btn', N'Review save path action', N'Find Save Path. Saving the path makes the course available for assignment and session planning.', N'manual_continue', NULL, NULL, 1, 50),
    (N'training_operations_assignment', 6, N'training-admin/operations', N'TrainingAssignmentUserIDs', N'Select assignment users or groups', N'Find the assignment users area. Select a Workflow User Group to assign every active group member, or search by name, username, or email for individual exceptions. Duplicate open assignments are skipped automatically.', N'manual_continue', NULL, NULL, 1, 60),
    (N'training_operations_assignment', 7, N'training-admin/operations', N'TrainingAssignmentPathCode', N'Confirm assignment course/path', N'Find the selected Course / Path summary in Assign Users. Change the course/path using the Selected Course / Path control at the top of Training Operations before assigning learners.', N'manual_continue', NULL, NULL, 1, 70),
    (N'training_operations_assignment', 8, N'training-admin/operations', N'TrainingAssignmentScenarioCode', N'Review scenario assignment scope', N'Find the Scenario selector. Leave it as All scenarios in selected course/path to assign the full course, or choose one scenario when a user needs a targeted exercise.', N'manual_continue', NULL, NULL, 1, 80),
    (N'training_operations_assignment', 9, N'training-admin/operations', N'training-assignment-save-btn', N'Review save assignment action', N'Find Assign. Saving an assignment updates what the selected users can see on the Training Dashboard.', N'manual_continue', NULL, NULL, 1, 90),

    (N'training_certification_setup', 1, N'training-certifications/admin', N'certification-catalogue-filter-form', N'Review certification filters', N'Find the certification catalogue filters. Use these to locate certifications by module, status, code, title, or description.', N'manual_continue', NULL, NULL, 1, 10),
    (N'training_certification_setup', 2, N'training-certifications/admin', N'certification-catalogue-create-btn', N'Review create certification action', N'Find Create Certification. Certifications define final module tests and pass requirements.', N'manual_continue', NULL, NULL, 1, 20),
    (N'training_certification_setup', 3, N'training-certifications/admin', N'certification-catalogue-table', N'Review certification rows', N'Review the certification table and note module, question count, pass mark, status, order, and actions.', N'manual_continue', NULL, NULL, 1, 30),
    (N'training_certification_setup', 4, N'training-certifications/form', N'TrainingCertificationCode', N'Review certification code', N'Find Certification Code. Keep it stable once attempts have been recorded.', N'manual_continue', NULL, NULL, 1, 40),
    (N'training_certification_setup', 5, N'training-certifications/form', N'TrainingCertificationPassPercent', N'Review pass percentage', N'Find Pass Percent. This is the score required for the user to be certified for the module.', N'manual_continue', NULL, NULL, 1, 50),
    (N'training_certification_setup', 6, N'training-certifications/question-form', N'TrainingCertificationQuestionText', N'Review question text', N'Find Question Text. Write questions that test practical module understanding rather than only screen labels.', N'manual_continue', NULL, NULL, 1, 60),
    (N'training_certification_setup', 7, N'training-certifications/question-form', N'TrainingCertificationQuestionOptions', N'Review answer options', N'Find Options. Each option should have a key and a clear label so automatic scoring can work.', N'manual_continue', NULL, NULL, 1, 70),
    (N'training_certification_setup', 8, N'training-certifications/question-form', N'TrainingCertificationCorrectOptionKey', N'Review correct answer key', N'Find Correct Option Key. The key must match the correct option exactly.', N'manual_continue', NULL, NULL, 1, 80),
    (N'training_certification_setup', 9, N'training-certifications/question-form', N'TrainingCertificationQuestionExplanation', N'Review explanation', N'Find Explanation. Use it to reinforce the learning point after scoring.', N'manual_continue', NULL, NULL, 1, 90),

    (N'training_dashboard_review', 1, N'training/dashboard', N'training-dashboard-overview', N'Review learner dashboard overview', N'Review the learner dashboard overview. This shows assigned completion, in-progress training, certifications, and module count for the logged-in user.', N'manual_continue', NULL, NULL, 1, 10),
    (N'training_dashboard_review', 2, N'training/dashboard', N'training-dashboard-modules', N'Review assigned module cards', N'Review the module cards. Assigned scenarios and related certifications appear here after Training Operations assignments are created.', N'manual_continue', NULL, NULL, 1, 20),
    (N'training_dashboard_review', 3, N'training/scenarios', N'trainingScenarioModule', N'Review assigned scenario module filter', N'Find the module filter on Training Scenarios. Learners can focus on one assigned module at a time.', N'manual_continue', NULL, NULL, 1, 30),
    (N'training_dashboard_review', 4, N'training/scenarios', N'trainingScenarioStatus', N'Review assigned scenario status filter', N'Find the status filter. Learners can separate not started, in progress, stopped, and completed training.', N'manual_continue', NULL, NULL, 1, 40);
GO

IF OBJECT_ID(N'dbo.tblTrainingPaths', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblTrainingPathScenarios', N'U') IS NOT NULL
BEGIN
    MERGE dbo.tblTrainingPaths AS target
    USING (
        SELECT
            N'training_module_path' AS PathCode,
            N'Training Module Setup Learning Path' AS PathTitle,
            N'Training administrators, training configuration users, implementation teams' AS Audience,
            N'Recommended setup sequence for configuring the Training module, including scenario catalogue, steps, validation, paths, assignments, certifications, and dashboard review.' AS Description,
            CAST(1 AS BIT) AS ActiveFlag,
            310 AS SortOrder
    ) AS source
    ON target.PathCode = source.PathCode
    WHEN MATCHED THEN
        UPDATE SET
            PathTitle = source.PathTitle,
            Audience = source.Audience,
            Description = source.Description,
            ActiveFlag = source.ActiveFlag,
            SortOrder = source.SortOrder,
            UpdatedDate = SYSUTCDATETIME()
    WHEN NOT MATCHED THEN
        INSERT
            (PathCode, PathTitle, Audience, Description, ActiveFlag, SortOrder)
        VALUES
            (source.PathCode, source.PathTitle, source.Audience, source.Description, source.ActiveFlag, source.SortOrder);

    DELETE FROM dbo.tblTrainingPathScenarios
    WHERE PathCode = N'training_module_path';

    INSERT INTO dbo.tblTrainingPathScenarios
        (PathCode, ScenarioCode, RequiredFlag, SortOrder)
    VALUES
        (N'training_module_path', N'training_module_overview', CAST(1 AS BIT), 10),
        (N'training_module_path', N'training_catalogue_setup', CAST(1 AS BIT), 20),
        (N'training_module_path', N'training_step_builder', CAST(1 AS BIT), 30),
        (N'training_module_path', N'training_validation_review', CAST(1 AS BIT), 40),
        (N'training_module_path', N'training_operations_assignment', CAST(1 AS BIT), 50),
        (N'training_module_path', N'training_certification_setup', CAST(1 AS BIT), 60),
        (N'training_module_path', N'training_dashboard_review', CAST(1 AS BIT), 70);
END;
GO
