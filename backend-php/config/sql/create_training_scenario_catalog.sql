IF OBJECT_ID(N'dbo.tblTrainingScenarios', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingScenarios
    (
        TrainingScenarioID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(100) NOT NULL,
        ScenarioTitle NVARCHAR(200) NOT NULL,
        ScreenFamily NVARCHAR(100) NOT NULL,
        ModuleName NVARCHAR(100) NOT NULL,
        Audience NVARCHAR(200) NULL,
        Difficulty NVARCHAR(50) NULL,
        Description NVARCHAR(MAX) NULL,
        RunnerRoute NVARCHAR(200) NOT NULL,
        NextScenarioCode NVARCHAR(100) NULL,
        PrerequisitesJson NVARCHAR(MAX) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblTrainingScenarios_ActiveFlag DEFAULT (1),
        SortOrder INT NOT NULL CONSTRAINT DF_tblTrainingScenarios_SortOrder DEFAULT (0),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarios_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarios_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingScenarios')
      AND name = N'UX_tblTrainingScenarios_ScenarioCode'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingScenarios_ScenarioCode
        ON dbo.tblTrainingScenarios (ScenarioCode);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingScenarioTranslations', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingScenarioTranslations
    (
        TrainingScenarioTranslationID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(100) NOT NULL,
        LanguageCode NVARCHAR(20) NOT NULL,
        ScenarioTitle NVARCHAR(200) NULL,
        ModuleName NVARCHAR(100) NULL,
        Audience NVARCHAR(200) NULL,
        Description NVARCHAR(MAX) NULL,
        PrerequisitesJson NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioTranslations_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioTranslations_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingScenarioTranslations')
      AND name = N'UX_tblTrainingScenarioTranslations_CodeLang'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingScenarioTranslations_CodeLang
        ON dbo.tblTrainingScenarioTranslations (ScenarioCode, LanguageCode);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingScenarioSteps', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingScenarioSteps
    (
        TrainingScenarioStepID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(100) NOT NULL,
        StepNo INT NOT NULL,
        Route NVARCHAR(200) NOT NULL,
        TargetElementID NVARCHAR(150) NULL,
        StepTitle NVARCHAR(200) NOT NULL,
        InstructionText NVARCHAR(MAX) NOT NULL,
        CompletionMode NVARCHAR(50) NOT NULL,
        SampleKey NVARCHAR(100) NULL,
        ExpectedUserSampleKey NVARCHAR(100) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblTrainingScenarioSteps_ActiveFlag DEFAULT (1),
        SortOrder INT NOT NULL CONSTRAINT DF_tblTrainingScenarioSteps_SortOrder DEFAULT (0),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioSteps_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioSteps_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingScenarioSteps')
      AND name = N'UX_tblTrainingScenarioSteps_CodeStep'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingScenarioSteps_CodeStep
        ON dbo.tblTrainingScenarioSteps (ScenarioCode, StepNo);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingScenarioStepTranslations', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingScenarioStepTranslations
    (
        TrainingScenarioStepTranslationID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(100) NOT NULL,
        StepNo INT NOT NULL,
        LanguageCode NVARCHAR(20) NOT NULL,
        StepTitle NVARCHAR(200) NULL,
        InstructionText NVARCHAR(MAX) NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioStepTranslations_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioStepTranslations_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingScenarioStepTranslations')
      AND name = N'UX_tblTrainingScenarioStepTranslations_CodeStepLang'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingScenarioStepTranslations_CodeStepLang
        ON dbo.tblTrainingScenarioStepTranslations (ScenarioCode, StepNo, LanguageCode);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingScenarioSamples', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingScenarioSamples
    (
        TrainingScenarioSampleID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(100) NOT NULL,
        SampleKey NVARCHAR(100) NOT NULL,
        SampleValueTemplate NVARCHAR(400) NOT NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblTrainingScenarioSamples_ActiveFlag DEFAULT (1),
        SortOrder INT NOT NULL CONSTRAINT DF_tblTrainingScenarioSamples_SortOrder DEFAULT (0),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioSamples_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioSamples_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingScenarioSamples')
      AND name = N'UX_tblTrainingScenarioSamples_CodeSample'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingScenarioSamples_CodeSample
        ON dbo.tblTrainingScenarioSamples (ScenarioCode, SampleKey);
END;
GO

IF OBJECT_ID(N'dbo.tblTrainingScenarioSampleTranslations', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblTrainingScenarioSampleTranslations
    (
        TrainingScenarioSampleTranslationID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioCode NVARCHAR(100) NOT NULL,
        SampleKey NVARCHAR(100) NOT NULL,
        LanguageCode NVARCHAR(20) NOT NULL,
        SampleValueTemplate NVARCHAR(400) NOT NULL,
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioSampleTranslations_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblTrainingScenarioSampleTranslations_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblTrainingScenarioSampleTranslations')
      AND name = N'UX_tblTrainingScenarioSampleTranslations_CodeSampleLang'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblTrainingScenarioSampleTranslations_CodeSampleLang
        ON dbo.tblTrainingScenarioSampleTranslations (ScenarioCode, SampleKey, LanguageCode);
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
            N'users_create_demo',
            N'Create New User',
            N'users',
            N'Administration',
            N'System Administrator / User Administrator',
            N'Introductory',
            N'This guided exercise walks a trainee through creating one new user on the Users administration screens.',
            N'training/users',
            N'users_edit_record',
            N'["Training features are enabled in this environment.","The trainee has access to Users administration.","Stable target IDs for the prototype screen have been verified."]',
            CAST(1 AS BIT),
            10
        ),
        (
            N'users_edit_record',
            N'Edit Existing User',
            N'users',
            N'Administration',
            N'System Administrator / User Administrator',
            N'Intermediate',
            N'This guided exercise walks a trainee through finding an existing user, updating the record, saving the change, and reviewing every tab on the Edit User screen.',
            N'training/users-edit',
            N'users_create_demo',
            N'["Training features are enabled in this environment.","The trainee has access to Users administration.","The target user record is available for editing in this environment.","Stable target IDs for the prototype screen have been verified."]',
            CAST(1 AS BIT),
            20
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
WHERE ScenarioCode IN (N'users_create_demo', N'users_edit_record');
GO

INSERT INTO dbo.tblTrainingScenarioSteps
    (ScenarioCode, StepNo, Route, TargetElementID, StepTitle, InstructionText, CompletionMode, SampleKey, ExpectedUserSampleKey, ActiveFlag, SortOrder)
VALUES
    (N'users_create_demo', 1, N'users/list', N'users-create-btn', N'Open the user form', N'Click Create User to open the user maintenance form.', N'navigation', NULL, NULL, 1, 10),
    (N'users_create_demo', 2, N'users/edit', N'Username', N'Enter the username', N'Enter a username for the new user.', N'field_nonempty', N'Username', NULL, 1, 20),
    (N'users_create_demo', 3, N'users/edit', N'FirstName', N'Enter the first name', N'Enter a first name so the user record has a readable identity.', N'field_nonempty', N'FirstName', NULL, 1, 30),
    (N'users_create_demo', 4, N'users/edit', N'LastName', N'Enter the last name', N'Enter the last name so the user profile is complete.', N'field_nonempty', N'LastName', NULL, 1, 40),
    (N'users_create_demo', 5, N'users/edit', N'DisplayName', N'Review the display name', N'The display name is filled automatically from the first and last name. Review it and edit it if you want a different label.', N'field_prefilled', N'DisplayName', NULL, 1, 50),
    (N'users_create_demo', 6, N'users/edit', N'Email', N'Enter the email address', N'Enter a valid email address for the user.', N'field_email', N'Email', NULL, 1, 60),
    (N'users_create_demo', 7, N'users/edit', N'Phone', N'Enter the phone number', N'Enter a contact phone number for the user profile.', N'field_nonempty', N'Phone', NULL, 1, 70),
    (N'users_create_demo', 8, N'users/edit', N'Department', N'Enter the department', N'Enter the department or business area for the user.', N'field_nonempty', N'Department', NULL, 1, 80),
    (N'users_create_demo', 9, N'users/edit', N'JobTitle', N'Enter the job title', N'Enter the job title so the profile reflects the user role.', N'field_nonempty', N'JobTitle', NULL, 1, 90),
    (N'users_create_demo', 10, N'users/edit', N'IsActive', N'Enable the user account', N'Tick Enabled so the user can sign in to the system.', N'checkbox_checked', NULL, NULL, 1, 100),
    (N'users_create_demo', 11, N'users/edit', N'users-account-flags', N'Review the password options', N'Force Password Reset prompts the user to reset their password after the next login. Must Change Password requires the user to set a new password before they can continue. Review these options, then continue to the final save step.', N'manual_continue', NULL, NULL, 1, 110),
    (N'users_create_demo', 12, N'users/edit', N'users-save-btn', N'Save the user', N'Click Save to create the new user record.', N'submit_success', NULL, NULL, 1, 120),

    (N'users_edit_record', 1, N'users/list', N'users-search-input', N'Search for the user record', N'Enter the target username into the search box so the list can be narrowed to the record you want to edit.', N'field_nonempty', N'TargetUsername', NULL, 1, 10),
    (N'users_edit_record', 2, N'users/list', N'users-filter-btn', N'Filter the user list', N'Click Filter to refresh the register and focus on the matching user record.', N'navigation', NULL, NULL, 1, 20),
    (N'users_edit_record', 3, N'users/list', N'users-edit-target-btn', N'Open the user record', N'Click the highlighted Edit button to open the selected user record.', N'navigation', NULL, N'TargetUserID', 1, 30),
    (N'users_edit_record', 4, N'users/edit', N'Notes', N'Update the Notes field', N'Enter a note so you can practise making a safe change to the user profile.', N'field_nonempty', N'Notes', NULL, 1, 40),
    (N'users_edit_record', 5, N'users/edit', N'users-save-btn', N'Save the profile change', N'Click Save to update the user profile and return to the register.', N'submit_success', NULL, NULL, 1, 50),
    (N'users_edit_record', 6, N'users/list', N'users-edit-target-btn', N'Reopen the edited record', N'Open the same user again so you can review the remaining tabs on the Edit User screen.', N'navigation', NULL, N'TargetUserID', 1, 60),
    (N'users_edit_record', 7, N'users/edit', N'details-tab', N'Open the User Details tab', N'Click User Details to review login and metadata information for the user.', N'click_target', NULL, NULL, 1, 70),
    (N'users_edit_record', 8, N'users/edit', N'users-details-review', N'Review the user details', N'Review the metadata shown on this tab, including login activity, audit fields, and counters, then continue.', N'manual_continue', NULL, NULL, 1, 80),
    (N'users_edit_record', 9, N'users/edit', N'roles-tab', N'Open the Roles tab', N'Click Assign Roles to review how the user role assignments are grouped by functional area.', N'click_target', NULL, NULL, 1, 90),
    (N'users_edit_record', 10, N'users/edit', N'users-roles-review', N'Review assigned roles', N'Review the assigned roles and how they are grouped. No change is required for this scenario, so continue once you have reviewed the tab.', N'manual_continue', NULL, NULL, 1, 100),
    (N'users_edit_record', 11, N'users/edit', N'account-tab', N'Open the Account & Access tab', N'Click Account & Access to review the effective access information for the user.', N'click_target', NULL, NULL, 1, 110),
    (N'users_edit_record', 12, N'users/edit', N'users-account-review', N'Review account access', N'Review the embedded account and access information for the user, then continue to return to the register.', N'manual_continue', NULL, NULL, 1, 120),
    (N'users_edit_record', 13, N'users/edit', N'users-account-back-btn', N'Return to the user list', N'Click Back to return to the user register and finish this training scenario.', N'navigation', NULL, NULL, 1, 130);
GO

DELETE FROM dbo.tblTrainingScenarioSamples
WHERE ScenarioCode IN (N'users_create_demo', N'users_edit_record');
GO

INSERT INTO dbo.tblTrainingScenarioSamples
    (ScenarioCode, SampleKey, SampleValueTemplate, ActiveFlag, SortOrder)
VALUES
    (N'users_create_demo', N'Username', N'train_user_{stamp}', 1, 10),
    (N'users_create_demo', N'FirstName', N'Training', 1, 20),
    (N'users_create_demo', N'LastName', N'User', 1, 30),
    (N'users_create_demo', N'DisplayName', N'Training User', 1, 40),
    (N'users_create_demo', N'Email', N'train_user_{stamp}@example.com', 1, 50),
    (N'users_create_demo', N'Phone', N'+266 5555 0101', 1, 60),
    (N'users_create_demo', N'Department', N'Training Services', 1, 70),
    (N'users_create_demo', N'JobTitle', N'Training Officer', 1, 80),
    (N'users_edit_record', N'TargetUserID', N'{context.target_user_id}', 1, 10),
    (N'users_edit_record', N'TargetUsername', N'{context.target_username}', 1, 20),
    (N'users_edit_record', N'Notes', N'Reviewed during training on {now_ymd_hm}', 1, 30);
GO
