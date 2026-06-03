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
            N'cbms_fundamentals_home_nav',
            N'Home Navigation Basics',
            N'core',
            N'CBMS Fundamentals',
            N'All users',
            N'Introductory',
            N'Learn the purpose of the shared top navigation bar, including the menu, help, language, account, and logout controls.',
            N'training/runner',
            N'cbms_fundamentals_context',
            N'["Training features are enabled in this environment.","The trainee is signed in to CBMS.","The shared navigation bar is visible on the current screen."]',
            CAST(1 AS BIT),
            30
        ),
        (
            N'cbms_fundamentals_context',
            N'Fiscal Context Basics',
            N'core',
            N'CBMS Fundamentals',
            N'All users',
            N'Introductory',
            N'Learn how Fiscal Year and Version work in CBMS and where to review or change the active fiscal context.',
            N'training/runner',
            N'cbms_fundamentals_datascope',
            N'["Training features are enabled in this environment.","The trainee is signed in to CBMS.","At least one fiscal year and version are configured in this environment."]',
            CAST(1 AS BIT),
            40
        ),
        (
            N'cbms_fundamentals_datascope',
            N'DataScope And Status Basics',
            N'core',
            N'CBMS Fundamentals',
            N'All users',
            N'Introductory',
            N'Learn how DataScope selection works, where the selected scope is shown, and how to read the current workflow status indicator.',
            N'training/runner',
            N'cbms_fundamentals_menu_nav',
            N'["Training features are enabled in this environment.","The trainee is signed in to CBMS.","The shared navigation bar is visible on the current screen."]',
            CAST(1 AS BIT),
            50
        ),
        (
            N'cbms_fundamentals_menu_nav',
            N'Menu Navigation Basics',
            N'core',
            N'CBMS Fundamentals',
            N'All users',
            N'Introductory',
            N'Practise the two main navigation patterns in CBMS: browsing the menu and jumping directly to a known screen code.',
            N'training/runner',
            N'users_create_demo',
            N'["Training features are enabled in this environment.","The trainee is signed in to CBMS.","The shared navigation bar is visible on the current screen."]',
            CAST(1 AS BIT),
            60
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
    N'cbms_fundamentals_home_nav',
    N'cbms_fundamentals_context',
    N'cbms_fundamentals_datascope',
    N'cbms_fundamentals_menu_nav'
);
GO

INSERT INTO dbo.tblTrainingScenarioSteps
    (ScenarioCode, StepNo, Route, TargetElementID, StepTitle, InstructionText, CompletionMode, SampleKey, ExpectedUserSampleKey, ActiveFlag, SortOrder)
VALUES
    (N'cbms_fundamentals_home_nav', 1, N'training/scenarios', N'homeNavBtn', N'Review the Home button', N'Find the Home button in the top navigation bar and note that it returns you to the main landing page.', N'manual_continue', NULL, NULL, 1, 10),
    (N'cbms_fundamentals_home_nav', 2, N'training/scenarios', N'appMenuToggleBtn', N'Open the main menu', N'Click Menu to open the main navigation sidebar.', N'click_target', NULL, NULL, 1, 20),
    (N'cbms_fundamentals_home_nav', 3, N'training/scenarios', N'appMenu', N'Review the menu panel', N'Review how screens are grouped in the sidebar menu, then continue when you are comfortable with the layout.', N'manual_continue', NULL, NULL, 1, 30),
    (N'cbms_fundamentals_home_nav', 4, N'training/scenarios', N'langMenu', N'Open the language menu', N'Click the language control to see where language switching is managed.', N'click_target', NULL, NULL, 1, 40),
    (N'cbms_fundamentals_home_nav', 5, N'training/scenarios', N'helpBtn', N'Open screen help', N'Click Help to open the screen-specific guidance panel.', N'click_target', NULL, NULL, 1, 50),
    (N'cbms_fundamentals_home_nav', 6, N'training/scenarios', N'helpModal', N'Review the help window', N'Review the helper instructions window, then continue after you understand where route-level guidance appears.', N'manual_continue', NULL, NULL, 1, 60),
    (N'cbms_fundamentals_home_nav', 7, N'training/scenarios', N'accountNavBtn', N'Review the Account link', N'Find the Account link and note that it takes you to your personal account details.', N'manual_continue', NULL, NULL, 1, 70),
    (N'cbms_fundamentals_home_nav', 8, N'training/scenarios', N'logoutNavBtn', N'Review the Logout link', N'Find the Logout link and note that it signs you out of CBMS when you are finished working.', N'manual_continue', NULL, NULL, 1, 80),

    (N'cbms_fundamentals_context', 1, N'training/scenarios', N'fyDropdownBtn', N'Open the Fiscal Year selector', N'Click the Fiscal Year button to review the fiscal years available in the current environment.', N'click_target', NULL, NULL, 1, 10),
    (N'cbms_fundamentals_context', 2, N'training/scenarios', N'fyDropdownBtn', N'Understand Fiscal Year context', N'Review the Fiscal Year selector and note that many records and reports are filtered by the active year.', N'manual_continue', NULL, NULL, 1, 20),
    (N'cbms_fundamentals_context', 3, N'training/scenarios', N'verDropdownBtn', N'Open the Version selector', N'Click the Version button to review the available versions for the active fiscal year.', N'click_target', NULL, NULL, 1, 30),
    (N'cbms_fundamentals_context', 4, N'training/scenarios', N'verDropdownBtn', N'Understand Version context', N'Review the Version selector and note that working in the wrong version can change what data you are seeing or editing.', N'manual_continue', NULL, NULL, 1, 40),

    (N'cbms_fundamentals_datascope', 1, N'training/scenarios', N'dataScopeBtn', N'Open the DataScope selector', N'Click the DataScope control to open the picker used to select the current organisational scope.', N'click_target', NULL, NULL, 1, 10),
    (N'cbms_fundamentals_datascope', 2, N'training/scenarios', N'dataObjectPickerModal', N'Review the DataScope picker', N'Review the picker window and note that DataScope controls which organisational records many screens are working with.', N'manual_continue', NULL, NULL, 1, 20),
    (N'cbms_fundamentals_datascope', 3, N'training/scenarios', N'scopeStatusBtn', N'Review the workflow status indicator', N'Find the status button beside the DataScope controls and note that it shows the current workflow status for the selected scope.', N'manual_continue', NULL, NULL, 1, 30),

    (N'cbms_fundamentals_menu_nav', 1, N'training/scenarios', N'appMenuToggleBtn', N'Open the navigation menu', N'Click Menu to open the full application navigation panel.', N'click_target', NULL, NULL, 1, 10),
    (N'cbms_fundamentals_menu_nav', 2, N'training/scenarios', N'appMenu', N'Review grouped navigation', N'Review how modules are grouped in the sidebar so you know how to browse to a screen even when you do not know its code yet.', N'manual_continue', NULL, NULL, 1, 20),
    (N'cbms_fundamentals_menu_nav', 3, N'training/scenarios', N'menuJumpInput', N'Find the screen jump box', N'Locate the screen jump input and note that it can take a screen code or route when you already know where you want to go.', N'manual_continue', NULL, NULL, 1, 30),
    (N'cbms_fundamentals_menu_nav', 4, N'training/scenarios', N'menuJumpGoBtn', N'Review the jump action', N'Find the jump button and note that it opens the screen entered in the jump box.', N'manual_continue', NULL, NULL, 1, 40);
GO
