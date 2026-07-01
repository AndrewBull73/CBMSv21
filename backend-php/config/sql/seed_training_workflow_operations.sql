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
            N'workflow_ops_overview',
            N'Workflow Operations Module Overview',
            N'workflow-operations',
            N'Workflow Operations',
            N'Project managers, workflow administrators, implementation teams, support teams',
            N'Introductory',
            N'Learn the governance purpose of Workflow Operations and how the module connects projects, requirements, tasks, issues, evidence, and lifecycle support.',
            N'training/runner',
            N'workflow_ops_project_register',
            N'["Training features are enabled in this environment.","The trainee has access to Workflow Operations.","Workflow Operations helper cards and Help modal are available."]',
            CAST(1 AS BIT),
            210
        ),
        (
            N'workflow_ops_project_register',
            N'Workflow Project Register Orientation',
            N'workflow-projects',
            N'Workflow Operations',
            N'Project managers, workflow administrators, implementation teams',
            N'Introductory',
            N'Practise reading and filtering the project register and understand how projects create the context for related requirements, tasks, and issues.',
            N'training/runner',
            N'workflow_ops_project_form',
            N'["Workflow project tables are installed.","The trainee has permission to view workflow projects.","At least one workflow project is recommended for a richer exercise."]',
            CAST(1 AS BIT),
            220
        ),
        (
            N'workflow_ops_project_form',
            N'Workflow Project Form Orientation',
            N'workflow-projects',
            N'Workflow Operations',
            N'Project managers, workflow administrators, implementation teams',
            N'Introductory',
            N'Review the project form tabs and learn where project details, ownership, team membership, and schedule information are maintained.',
            N'training/runner',
            N'workflow_ops_requirements_register',
            N'["Workflow project tables are installed.","The trainee has permission to create or edit workflow projects.","This scenario is orientation only and does not require saving a project."]',
            CAST(1 AS BIT),
            230
        ),
        (
            N'workflow_ops_requirements_register',
            N'Workflow Requirements Register Orientation',
            N'workflow-requirements',
            N'Workflow Operations',
            N'Business analysts, project managers, workflow editors, implementation teams',
            N'Introductory',
            N'Learn how requirements are filtered, reviewed, and connected to projects so delivery work remains traceable.',
            N'training/runner',
            N'workflow_ops_requirement_matrix',
            N'["Workflow requirement tables are installed.","The trainee has permission to view workflow requirements.","At least one workflow project and requirement is recommended for a richer exercise."]',
            CAST(1 AS BIT),
            240
        ),
        (
            N'workflow_ops_requirement_matrix',
            N'Requirements Matrix Traceability Review',
            N'workflow-requirements',
            N'Workflow Operations',
            N'Business analysts, project managers, quality reviewers, implementation teams',
            N'Intermediate',
            N'Practise using the Requirements Matrix to review coverage, linked tasks, evidence, gaps, testing, training, and issue impacts.',
            N'training/runner',
            N'workflow_ops_issue_log',
            N'["Workflow requirements and workflow links are installed.","The trainee has permission to view the requirements matrix.","Requirement links and tasks improve the usefulness of this scenario."]',
            CAST(1 AS BIT),
            250
        ),
        (
            N'workflow_ops_issue_log',
            N'Workflow Issues Log Orientation',
            N'workflow-issues',
            N'Workflow Operations',
            N'Project managers, workflow editors, support teams, quality reviewers',
            N'Introductory',
            N'Learn how issues are filtered, reviewed, and connected to projects, requirements, and resolution tasks.',
            N'training/runner',
            N'workflow_ops_task_queue',
            N'["Workflow issue tables are installed.","The trainee has permission to view workflow issues.","At least one workflow project is recommended for a richer exercise."]',
            CAST(1 AS BIT),
            260
        ),
        (
            N'workflow_ops_task_queue',
            N'Workflow Task Queue Orientation',
            N'workflow',
            N'Workflow Operations',
            N'All Workflow Operations users',
            N'Introductory',
            N'Practise reading the task queue, filtering assigned work, reviewing status and due-date information, and understanding how tasks drive follow-up.',
            N'training/runner',
            N'workflow_ops_overview',
            N'["Workflow task tables are installed.","The trainee has permission to view workflow tasks.","Task examples are recommended for a richer exercise."]',
            CAST(1 AS BIT),
            270
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
    N'workflow_ops_overview',
    N'workflow_ops_project_register',
    N'workflow_ops_project_form',
    N'workflow_ops_requirements_register',
    N'workflow_ops_requirement_matrix',
    N'workflow_ops_issue_log',
    N'workflow_ops_task_queue'
);
GO

INSERT INTO dbo.tblTrainingScenarioSteps
    (ScenarioCode, StepNo, Route, TargetElementID, StepTitle, InstructionText, CompletionMode, SampleKey, ExpectedUserSampleKey, ActiveFlag, SortOrder)
VALUES
    (N'workflow_ops_overview', 1, N'workflow-projects/list', N'workflowModuleOverviewHelpBtn', N'Open and read the module overview', N'Click Module Overview, then read and scroll the conceptual help for Workflow Operations. Continue when you understand how the module supports governance, control, visibility, integrity, and delivery quality.', N'manual_continue', NULL, NULL, 1, 10),
    (N'workflow_ops_overview', 2, N'workflow-projects/list', N'workflow-projects-table', N'Find the project register', N'Locate the project table and note that projects are the containers for requirements, tasks, issues, team ownership, dates, and evidence.', N'manual_continue', NULL, NULL, 1, 20),
    (N'workflow_ops_overview', 3, N'workflow-projects/list', N'helpBtn', N'Compare screen help', N'Click the normal Help button, then read and scroll the screen-specific help. Continue when you can see how screen help differs from the wider Module Overview.', N'manual_continue', NULL, NULL, 1, 30),
    (N'workflow_ops_overview', 4, N'workflow-projects/list', N'workflowModuleOverviewHelpBtn', N'Checkpoint: governance purpose', N'Checkpoint question: Which answer best explains why keeping Workflow Operations inside CBMS improves project governance? Select the best answer, then continue.', N'manual_continue', NULL, NULL, 1, 40),

    (N'workflow_ops_project_register', 1, N'workflow-projects/list', N'workflowProjectSearch', N'Review project search', N'Find the project search field. Use it when the project register grows and you need to locate a project by code, name, owner, or description.', N'manual_continue', NULL, NULL, 1, 10),
    (N'workflow_ops_project_register', 2, N'workflow-projects/list', N'workflowProjectStatus', N'Review project status filter', N'Find the project status filter and note that status helps managers separate planned, active, completed, and paused work.', N'manual_continue', NULL, NULL, 1, 20),
    (N'workflow_ops_project_register', 3, N'workflow-projects/list', N'workflowProjectActive', N'Review active filter', N'Find the active filter. Active projects appear in normal work lists, while inactive projects are retained for history and governance evidence.', N'manual_continue', NULL, NULL, 1, 30),
    (N'workflow_ops_project_register', 4, N'workflow-projects/list', N'workflow-projects-filter-btn', N'Apply project filters', N'Click Filter to apply the current project register filters.', N'click_target', NULL, NULL, 1, 40),
    (N'workflow_ops_project_register', 5, N'workflow-projects/list', N'workflow-projects-table', N'Review the register table', N'Review the project register columns and actions. The table gives visibility over ownership, status, open tasks, and project-level follow-up.', N'manual_continue', NULL, NULL, 1, 50),
    (N'workflow_ops_project_register', 6, N'workflow-projects/list', N'workflow-projects-export-excel-btn', N'Review export action', N'Find Export Excel and note that exported registers support offline governance review and meeting packs.', N'manual_continue', NULL, NULL, 1, 60),

    (N'workflow_ops_project_form', 1, N'workflow-projects/form', N'WorkflowProjectDetailsTabButton', N'Review Details tab', N'Find the Details tab. This is where the project name, code, status, owner, dates, and description are maintained.', N'manual_continue', NULL, NULL, 1, 10),
    (N'workflow_ops_project_form', 2, N'workflow-projects/form', N'ProjectName', N'Review project name', N'Find the Project Name field. A clear project name helps tasks, requirements, issues, and reports stay understandable across the lifecycle.', N'manual_continue', NULL, NULL, 1, 20),
    (N'workflow_ops_project_form', 3, N'workflow-projects/form', N'ProjectOwnerUserID', N'Review project owner', N'Find the owner field. Ownership is a core governance control because it identifies who coordinates decisions and follow-up.', N'manual_continue', NULL, NULL, 1, 30),
    (N'workflow_ops_project_form', 4, N'workflow-projects/form', N'WorkflowProjectTeamTabButton', N'Review Team tab', N'Click the Team tab to see where project participants and roles are maintained.', N'click_target', NULL, NULL, 1, 40),
    (N'workflow_ops_project_form', 5, N'workflow-projects/form', N'workflowProjectUserSearch', N'Review user search', N'Find the project user search field. Use it to maintain the team list when ownership or implementation responsibilities change.', N'manual_continue', NULL, NULL, 1, 50),
    (N'workflow_ops_project_form', 6, N'workflow-projects/form', N'workflow-project-save-btn', N'Review save action', N'Find the Save button. This orientation scenario does not require saving, but saving is how project details become available to related workflow screens.', N'manual_continue', NULL, NULL, 1, 60),

    (N'workflow_ops_requirements_register', 1, N'workflow-requirements/list', N'workflowRequirementProject', N'Review project filter', N'Find the project filter. Filtering requirements by project keeps scope and ownership clear.', N'manual_continue', NULL, NULL, 1, 10),
    (N'workflow_ops_requirements_register', 2, N'workflow-requirements/list', N'workflowRequirementLevel', N'Review requirement level', N'Find the requirement level filter. High-level requirements define major outcomes, while detailed requirements support delivery and acceptance tracking.', N'manual_continue', NULL, NULL, 1, 20),
    (N'workflow_ops_requirements_register', 3, N'workflow-requirements/list', N'workflowRequirementPriority', N'Review priority filter', N'Find the priority filter and note how it supports delivery planning and governance focus.', N'manual_continue', NULL, NULL, 1, 30),
    (N'workflow_ops_requirements_register', 4, N'workflow-requirements/list', N'workflow-requirements-filter-btn', N'Apply requirement filters', N'Click Filter to apply the current requirement filters.', N'click_target', NULL, NULL, 1, 40),
    (N'workflow_ops_requirements_register', 5, N'workflow-requirements/list', N'workflow-requirements-table', N'Review requirement rows', N'Review the requirements table and note how requirements connect project scope, priority, status, owner, and row actions.', N'manual_continue', NULL, NULL, 1, 50),
    (N'workflow_ops_requirements_register', 6, N'workflow-requirements/list', N'workflowRequirementListActions', N'Open requirement actions', N'Open the actions menu to see the related summary and matrix views.', N'click_target', NULL, NULL, 1, 60),

    (N'workflow_ops_requirement_matrix', 1, N'workflow-requirements/matrix', N'workflowRequirementMatrixProject', N'Review matrix project filter', N'Find the project filter. The matrix is most useful when scoped to the project under review.', N'manual_continue', NULL, NULL, 1, 10),
    (N'workflow_ops_requirement_matrix', 2, N'workflow-requirements/matrix', N'workflowRequirementMatrixCoverage', N'Review coverage filter', N'Find the coverage filter. Use it to focus on missing tasks, open tasks, missing testing, missing training, defects, or missing acceptance criteria.', N'manual_continue', NULL, NULL, 1, 20),
    (N'workflow_ops_requirement_matrix', 3, N'workflow-requirements/matrix', N'workflow-requirement-matrix-filter-btn', N'Apply matrix filters', N'Click Filter to apply the current traceability filters.', N'click_target', NULL, NULL, 1, 30),
    (N'workflow_ops_requirement_matrix', 4, N'workflow-requirements/matrix', N'workflow-requirement-matrix-table', N'Review traceability table', N'Review the matrix table. It shows linked tasks, evidence, and gaps so governance and quality reviews can focus on what is not yet ready.', N'manual_continue', NULL, NULL, 1, 40),
    (N'workflow_ops_requirement_matrix', 5, N'workflow-requirements/matrix', N'workflow-requirement-matrix-export-excel-btn', N'Review matrix export', N'Find Export Excel and note that the matrix can support readiness packs, audit review, and project governance meetings.', N'manual_continue', NULL, NULL, 1, 50),

    (N'workflow_ops_issue_log', 1, N'workflow-issues/list', N'WorkflowIssueProject', N'Review issue project filter', N'Find the project filter. Project-scoped issues help managers see blockers and decisions for one delivery stream.', N'manual_continue', NULL, NULL, 1, 10),
    (N'workflow_ops_issue_log', 2, N'workflow-issues/list', N'WorkflowIssueStatus', N'Review issue status filter', N'Find the status filter. Status helps separate open, waiting, resolved, and closed issues.', N'manual_continue', NULL, NULL, 1, 20),
    (N'workflow_ops_issue_log', 3, N'workflow-issues/list', N'WorkflowIssueSeverity', N'Review severity filter', N'Find the severity filter. Severity supports governance focus by highlighting issues with greater delivery or quality impact.', N'manual_continue', NULL, NULL, 1, 30),
    (N'workflow_ops_issue_log', 4, N'workflow-issues/list', N'workflow-issues-filter-btn', N'Apply issue filters', N'Click Filter to apply the current issue log filters.', N'click_target', NULL, NULL, 1, 40),
    (N'workflow_ops_issue_log', 5, N'workflow-issues/list', N'workflow-issues-table', N'Review issue rows', N'Review the issues table and note how each issue can connect to a project, requirement, owner, due date, and resolution tasks.', N'manual_continue', NULL, NULL, 1, 50),
    (N'workflow_ops_issue_log', 6, N'workflow-issues/list', N'workflow-issues-export-excel-btn', N'Review issue export action', N'Find Export Excel and note that exported issue logs can support governance meetings, resolution follow-up, and support handover.', N'manual_continue', NULL, NULL, 1, 60),

    (N'workflow_ops_task_queue', 1, N'workflow/list', N'workflow-tasks-filter-form', N'Review task filters', N'Find the task filters. The task queue is the operational work list for assigned follow-up and project activity.', N'manual_continue', NULL, NULL, 1, 10),
    (N'workflow_ops_task_queue', 2, N'workflow/list', N'q', N'Review task search', N'Find the search field. Search helps locate tasks by title, description, project, or related context.', N'manual_continue', NULL, NULL, 1, 20),
    (N'workflow_ops_task_queue', 3, N'workflow/list', N'statusID', N'Review task status', N'Find the status filter. Status separates work that is open, closed, waiting, or in progress depending on the configured task statuses.', N'manual_continue', NULL, NULL, 1, 30),
    (N'workflow_ops_task_queue', 4, N'workflow/list', N'workflowProjectID', N'Review task project filter', N'Find the project filter. Project-scoped task lists show what work is still outstanding for the selected project.', N'manual_continue', NULL, NULL, 1, 40),
    (N'workflow_ops_task_queue', 5, N'workflow/list', N'workflow-tasks-filter-btn', N'Apply task filters', N'Click Filter to apply the current task filters.', N'click_target', NULL, NULL, 1, 50),
    (N'workflow_ops_task_queue', 6, N'workflow/list', N'workflow-tasks-table', N'Review task rows', N'Review the task table and note how title, type, status, due date, assignee, and actions support day-to-day control.', N'manual_continue', NULL, NULL, 1, 60),
    (N'workflow_ops_task_queue', 7, N'workflow/list', N'workflow-tasks-export-excel-btn', N'Review task export action', N'Find Export Excel and note that exported task queues can support status meetings, governance reporting, and support follow-up.', N'manual_continue', NULL, NULL, 1, 70);
GO

IF OBJECT_ID(N'dbo.tblTrainingStepCheckpoints', N'U') IS NOT NULL
BEGIN
    DELETE FROM dbo.tblTrainingStepCheckpoints
    WHERE ScenarioCode = N'workflow_ops_overview'
      AND StepNo IN (1, 2)
      AND QuestionText IN (
          N'In your own words, why does keeping Workflow Operations inside CBMS improve project governance?',
          N'Which answer best explains why keeping Workflow Operations inside CBMS improves project governance?'
      );

    MERGE dbo.tblTrainingStepCheckpoints AS target
    USING (
        SELECT
            N'workflow_ops_overview' AS ScenarioCode,
            4 AS StepNo,
            N'Which answer best explains why keeping Workflow Operations inside CBMS improves project governance?' AS QuestionText,
            N'{"type":"multiple_choice","correct":"B","options":[{"key":"A","label":"It replaces the need for project ownership and review meetings."},{"key":"B","label":"It links projects, requirements, tasks, issues, evidence, and ownership in one governed record set."},{"key":"C","label":"It only changes the page layout so the screens are easier to read."},{"key":"D","label":"It prevents any changes after a project has been created."}],"explanation":"Integrated workflow records improve governance by giving visibility and control across project scope, ownership, tasks, issues, evidence, quality, and lifecycle support."}' AS ExpectedAnswer,
            CAST(1 AS BIT) AS RequiredFlag,
            CAST(1 AS BIT) AS ActiveFlag
    ) AS source
    ON target.ScenarioCode = source.ScenarioCode
       AND target.StepNo = source.StepNo
    WHEN MATCHED THEN
        UPDATE SET
            QuestionText = source.QuestionText,
            ExpectedAnswer = source.ExpectedAnswer,
            RequiredFlag = source.RequiredFlag,
            ActiveFlag = source.ActiveFlag,
            UpdatedDate = SYSUTCDATETIME()
    WHEN NOT MATCHED THEN
        INSERT
            (ScenarioCode, StepNo, QuestionText, ExpectedAnswer, RequiredFlag, ActiveFlag)
        VALUES
            (source.ScenarioCode, source.StepNo, source.QuestionText, source.ExpectedAnswer, source.RequiredFlag, source.ActiveFlag);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingScenarioStepTranslations', N'U') IS NOT NULL
BEGIN
    UPDATE dbo.tblTrainingScenarioStepTranslations
    SET
        StepTitle = N'Checkpoint: governance purpose',
        InstructionText = N'Checkpoint question: Which answer best explains why keeping Workflow Operations inside CBMS improves project governance? Select the best answer, then continue.',
        UpdatedDate = SYSUTCDATETIME()
    WHERE ScenarioCode = N'workflow_ops_overview'
      AND StepNo = 4
      AND LanguageCode IN (N'en', N'en-US', N'en-GB');
END;
GO
