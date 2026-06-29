<?php
declare(strict_types=1);

namespace App\Shared;

use App\Models\TrainingScenarioModel;

final class TrainingScenarioCatalog
{
    public const USERS_CREATE_DEMO = 'users_create_demo';
    public const USERS_EDIT_RECORD = 'users_edit_record';
    public const CBMS_FUNDAMENTALS_HOME_NAV = 'cbms_fundamentals_home_nav';
    public const CBMS_FUNDAMENTALS_CONTEXT = 'cbms_fundamentals_context';
    public const CBMS_FUNDAMENTALS_DATASCOPE = 'cbms_fundamentals_datascope';
    public const CBMS_FUNDAMENTALS_MENU_NAV = 'cbms_fundamentals_menu_nav';
    public const BASE_CONFIGURATION_READINESS_BASICS = 'base_configuration_readiness_basics';
    public const SYSTEM_SETTINGS_BASICS = 'system_settings_basics';
    public const SEGMENTS_CREATE_BASICS = 'segments_create_basics';
    public const SEGMENT_VALUES_CREATE_BASICS = 'segment_values_create_basics';
    public const FISCAL_YEARS_CREATE_BASICS = 'fiscal_years_create_basics';
    public const VERSIONS_CREATE_BASICS = 'versions_create_basics';
    public const CURRENCIES_CREATE_BASICS = 'currencies_create_basics';
    public const CURRENCY_RATES_CREATE_BASICS = 'currency_rates_create_basics';
    public const DATA_OBJECT_CODES_CREATE_BASICS = 'data_object_codes_create_basics';
    public const WORKFLOW_TASK_TYPES_CREATE_BASICS = 'workflow_task_types_create_basics';
    public const WORKFLOW_TASK_STATUSES_CREATE_BASICS = 'workflow_task_statuses_create_basics';
    public const WORKFLOW_ASSIGNMENTS_CREATE_BASICS = 'workflow_assignments_create_basics';
    public const WORKFLOW_ENGINE_DEFINITION_BASICS = 'workflow_engine_definition_basics';
    public const STRATEGY_RESOURCE_ENVELOPE_LINE_BASICS = 'strategy_resource_envelope_line_basics';
    public const EXECUTION_RESERVATION_CREATE_BASICS = 'execution_reservation_create_basics';

    private static ?\PDO $db = null;
    private static array $dbScenarioCache = [];
    private static ?array $dbAllCache = null;

    public static function setDb(?\PDO $db): void
    {
        self::$db = $db;
        self::$dbScenarioCache = [];
        self::$dbAllCache = null;
    }

    public static function all(): array
    {
        $fallback = self::fallbackScenarios();
        $dbScenarios = self::loadAllFromDb();
        foreach ($dbScenarios as $scenario) {
            $scenarioId = (string) ($scenario['id'] ?? '');
            if ($scenarioId !== '') {
                $fallback[$scenarioId] = $scenario;
            }
        }
        return $fallback;
    }

    public static function get(string $scenarioId): ?array
    {
        $scenarioId = trim($scenarioId);
        if ($scenarioId === '') {
            return null;
        }

        $dbScenario = self::loadScenarioFromDb($scenarioId);
        if ($dbScenario !== null) {
            return $dbScenario;
        }

        return self::fallbackScenario($scenarioId);
    }

    public static function makeState(string $scenarioId, array $context = []): ?array
    {
        $scenario = self::get($scenarioId);
        if ($scenario === null) {
            return null;
        }

        $samples = self::buildSamplesForScenario($scenarioId, $scenario, $context);

        return [
            'scenario_id' => $scenarioId,
            'screen_family' => (string) ($scenario['screen_family'] ?? ''),
            'status' => 'active',
            'current_step' => 1,
            'total_steps' => count($scenario['steps'] ?? []),
            'samples' => $samples,
            'started_at' => gmdate('Y-m-d H:i:s'),
            'completed_at' => null,
        ];
    }

    public static function getStep(array $state): ?array
    {
        $scenarioId = (string) ($state['scenario_id'] ?? '');
        $scenario = self::get($scenarioId);
        if ($scenario === null) {
            return null;
        }

        $currentStep = (int) ($state['current_step'] ?? 0);
        foreach (($scenario['steps'] ?? []) as $step) {
            if ((int) ($step['number'] ?? 0) === $currentStep) {
                return $step;
            }
        }

        return null;
    }

    public static function advanceState(array $state, int $completedStepNumber): ?array
    {
        $currentStep = (int) ($state['current_step'] ?? 0);
        if ($completedStepNumber <= 0 || $completedStepNumber !== $currentStep) {
            return null;
        }

        $totalSteps = (int) ($state['total_steps'] ?? 0);
        if ($totalSteps <= 0) {
            return null;
        }

        if ($completedStepNumber >= $totalSteps) {
            $state['status'] = 'completed';
            $state['completed_at'] = gmdate('Y-m-d H:i:s');
            return $state;
        }

        $state['current_step'] = $completedStepNumber + 1;
        return $state;
    }

    public static function routeForStep(?array $step): string
    {
        $route = (string) ($step['route'] ?? 'users/list');
        return 'index.php?route=' . rawurlencode($route);
    }

    public static function startRoute(string $scenarioId): string
    {
        $scenario = self::get($scenarioId);
        $runnerRoute = trim((string) ($scenario['runner_route'] ?? ''));
        if ($runnerRoute !== '') {
            $query = ['route' => $runnerRoute];
            if ($scenarioId !== '') {
                $query['scenario_id'] = $scenarioId;
            }
            return 'index.php?' . http_build_query($query);
        }

        return match ($scenarioId) {
            self::USERS_CREATE_DEMO => 'index.php?route=training/users',
            self::USERS_EDIT_RECORD => 'index.php?route=training/users-edit',
            default => 'index.php?route=training/scenarios',
        };
    }

    public static function isUsersScenario(string $scenarioId): bool
    {
        $scenario = self::get($scenarioId);
        return is_array($scenario) && (string) ($scenario['screen_family'] ?? '') === 'users';
    }

    private static function loadAllFromDb(): array
    {
        if (self::$dbAllCache !== null) {
            return self::$dbAllCache;
        }

        $model = self::scenarioModel();
        if (!$model instanceof TrainingScenarioModel) {
            return self::$dbAllCache = [];
        }

        return self::$dbAllCache = $model->listAllActive(self::activeLanguage());
    }

    private static function loadScenarioFromDb(string $scenarioId): ?array
    {
        if (array_key_exists($scenarioId, self::$dbScenarioCache)) {
            return self::$dbScenarioCache[$scenarioId];
        }

        foreach (self::loadAllFromDb() as $scenario) {
            if ((string) ($scenario['id'] ?? '') === $scenarioId) {
                return self::$dbScenarioCache[$scenarioId] = $scenario;
            }
        }

        $model = self::scenarioModel();
        if (!$model instanceof TrainingScenarioModel) {
            return self::$dbScenarioCache[$scenarioId] = null;
        }

        return self::$dbScenarioCache[$scenarioId] = $model->findByCode($scenarioId, self::activeLanguage());
    }

    private static function scenarioModel(): ?TrainingScenarioModel
    {
        if (!(self::$db instanceof \PDO)) {
            return null;
        }

        $model = new TrainingScenarioModel(self::$db);
        return $model->supportsScenarioCatalog() ? $model : null;
    }

    private static function activeLanguage(): string
    {
        return class_exists(Lang::class) ? Lang::getActiveLang() : 'en';
    }

    private static function buildSamplesForScenario(string $scenarioId, array $scenario, array $context): array
    {
        $sampleDefs = is_array($scenario['sample_defs'] ?? null) ? $scenario['sample_defs'] : [];
        if ($sampleDefs !== []) {
            return self::resolveSampleTemplates($sampleDefs, $context);
        }

        return match ($scenarioId) {
            self::USERS_CREATE_DEMO => self::buildUsersCreateSamples(),
            self::USERS_EDIT_RECORD => self::buildUsersEditSamples($context),
            default => [],
        };
    }

    private static function resolveSampleTemplates(array $sampleDefs, array $context): array
    {
        $stamp = gmdate('YmdHis') . random_int(10, 99);
        $tokenMap = [
            '{stamp}' => $stamp,
            '{now_ymd_hm}' => gmdate('Y-m-d H:i'),
            '{now_ymd}' => gmdate('Y-m-d'),
        ];

        $resolved = [];
        foreach ($sampleDefs as $sampleKey => $template) {
            $value = (string) $template;
            foreach ($tokenMap as $token => $tokenValue) {
                $value = str_replace($token, $tokenValue, $value);
            }
            if (preg_match_all('/\{context\.([a-zA-Z0-9_]+)\}/', $value, $matches)) {
                foreach ($matches[1] as $index => $contextKey) {
                    $placeholder = $matches[0][$index] ?? '';
                    $contextValue = (string) ($context[$contextKey] ?? '');
                    $value = str_replace($placeholder, $contextValue, $value);
                }
            }
            $resolved[(string) $sampleKey] = $value;
        }

        return $resolved;
    }

    private static function buildUsersCreateSamples(): array
    {
        $stamp = date('YmdHis') . random_int(10, 99);

        return [
            'Username' => 'train_user_' . $stamp,
            'FirstName' => 'Training',
            'LastName' => 'User',
            'DisplayName' => 'Training User',
            'Email' => 'train_user_' . $stamp . '@example.com',
            'Phone' => '+266 5555 0101',
            'Department' => 'Training Services',
            'JobTitle' => 'Training Officer',
        ];
    }

    private static function buildUsersEditSamples(array $context = []): array
    {
        $targetUserId = max(0, (int) ($context['target_user_id'] ?? 0));
        $targetUsername = trim((string) ($context['target_username'] ?? ''));

        return [
            'TargetUserID' => $targetUserId,
            'TargetUsername' => $targetUsername,
            'Notes' => 'Reviewed during training on ' . date('Y-m-d H:i'),
        ];
    }

    private static function fallbackScenario(string $scenarioId): ?array
    {
        return match ($scenarioId) {
            self::CBMS_FUNDAMENTALS_HOME_NAV => [
                'id' => self::CBMS_FUNDAMENTALS_HOME_NAV,
                'title' => 'Home Navigation Basics',
                'screen_family' => 'core',
                'module' => 'CBMS Fundamentals',
                'audience' => 'All users',
                'difficulty' => 'Introductory',
                'description' => 'Learn the purpose of the shared top navigation bar, including the menu, help, language, account, and logout controls.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee is signed in to CBMS.',
                    'The shared navigation bar is visible on the current screen.',
                ],
                'next_scenario_id' => self::CBMS_FUNDAMENTALS_CONTEXT,
                'runner_route' => 'training/runner',
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'training/scenarios',
                        'target' => 'homeNavBtn',
                        'title' => 'Review the Home button',
                        'instruction' => 'Find the Home button in the top navigation bar and note that it returns you to the main landing page.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 2,
                        'route' => 'training/scenarios',
                        'target' => 'appMenuToggleBtn',
                        'title' => 'Open the main menu',
                        'instruction' => 'Click Menu to open the main navigation sidebar.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 3,
                        'route' => 'training/scenarios',
                        'target' => 'appMenu',
                        'title' => 'Review the menu panel',
                        'instruction' => 'Review how screens are grouped in the sidebar menu, then continue when you are comfortable with the layout.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 4,
                        'route' => 'training/scenarios',
                        'target' => 'langMenu',
                        'title' => 'Open the language menu',
                        'instruction' => 'Click the language control to see where language switching is managed.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 5,
                        'route' => 'training/scenarios',
                        'target' => 'helpBtn',
                        'title' => 'Open screen help',
                        'instruction' => 'Click Help to open the screen-specific guidance panel.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 6,
                        'route' => 'training/scenarios',
                        'target' => 'helpModal',
                        'title' => 'Review the help window',
                        'instruction' => 'Review the helper instructions window, then continue after you understand where route-level guidance appears.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 7,
                        'route' => 'training/scenarios',
                        'target' => 'accountNavBtn',
                        'title' => 'Review the Account link',
                        'instruction' => 'Find the Account link and note that it takes you to your personal account details.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 8,
                        'route' => 'training/scenarios',
                        'target' => 'logoutNavBtn',
                        'title' => 'Review the Logout link',
                        'instruction' => 'Find the Logout link and note that it signs you out of CBMS when you are finished working.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::CBMS_FUNDAMENTALS_CONTEXT => [
                'id' => self::CBMS_FUNDAMENTALS_CONTEXT,
                'title' => 'Fiscal Context Basics',
                'screen_family' => 'core',
                'module' => 'CBMS Fundamentals',
                'audience' => 'All users',
                'difficulty' => 'Introductory',
                'description' => 'Learn how Fiscal Year and Version work in CBMS and where to review or change the active fiscal context.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee is signed in to CBMS.',
                    'At least one fiscal year and version are configured in this environment.',
                ],
                'next_scenario_id' => self::CBMS_FUNDAMENTALS_DATASCOPE,
                'runner_route' => 'training/runner',
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'training/scenarios',
                        'target' => 'fyDropdownBtn',
                        'title' => 'Open the Fiscal Year selector',
                        'instruction' => 'Click the Fiscal Year button to review the fiscal years available in the current environment.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'training/scenarios',
                        'target' => 'fyDropdownBtn',
                        'title' => 'Understand Fiscal Year context',
                        'instruction' => 'Review the Fiscal Year selector and note that many records and reports are filtered by the active year.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 3,
                        'route' => 'training/scenarios',
                        'target' => 'verDropdownBtn',
                        'title' => 'Open the Version selector',
                        'instruction' => 'Click the Version button to review the available versions for the active fiscal year.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 4,
                        'route' => 'training/scenarios',
                        'target' => 'verDropdownBtn',
                        'title' => 'Understand Version context',
                        'instruction' => 'Review the Version selector and note that working in the wrong version can change what data you are seeing or editing.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::CBMS_FUNDAMENTALS_DATASCOPE => [
                'id' => self::CBMS_FUNDAMENTALS_DATASCOPE,
                'title' => 'DataScope And Status Basics',
                'screen_family' => 'core',
                'module' => 'CBMS Fundamentals',
                'audience' => 'All users',
                'difficulty' => 'Introductory',
                'description' => 'Learn how DataScope selection works, where the selected scope is shown, and how to read the current workflow status indicator.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee is signed in to CBMS.',
                    'The shared navigation bar is visible on the current screen.',
                ],
                'next_scenario_id' => self::CBMS_FUNDAMENTALS_MENU_NAV,
                'runner_route' => 'training/runner',
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'training/scenarios',
                        'target' => 'dataScopeBtn',
                        'title' => 'Open the DataScope selector',
                        'instruction' => 'Click the DataScope control to open the picker used to select the current organisational scope.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'training/scenarios',
                        'target' => 'dataObjectPickerModal',
                        'title' => 'Review the DataScope picker',
                        'instruction' => 'Review the picker window and note that DataScope controls which organisational records many screens are working with.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 3,
                        'route' => 'training/scenarios',
                        'target' => 'scopeStatusBtn',
                        'title' => 'Review the workflow status indicator',
                        'instruction' => 'Find the status button beside the DataScope controls and note that it shows the current workflow status for the selected scope.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::CBMS_FUNDAMENTALS_MENU_NAV => [
                'id' => self::CBMS_FUNDAMENTALS_MENU_NAV,
                'title' => 'Menu Navigation Basics',
                'screen_family' => 'core',
                'module' => 'CBMS Fundamentals',
                'audience' => 'All users',
                'difficulty' => 'Introductory',
                'description' => 'Practise the two main navigation patterns in CBMS: browsing the menu and jumping directly to a known screen code.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee is signed in to CBMS.',
                    'The shared navigation bar is visible on the current screen.',
                ],
                'next_scenario_id' => self::USERS_CREATE_DEMO,
                'runner_route' => 'training/runner',
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'training/scenarios',
                        'target' => 'appMenuToggleBtn',
                        'title' => 'Open the navigation menu',
                        'instruction' => 'Click Menu to open the full application navigation panel.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'training/scenarios',
                        'target' => 'appMenu',
                        'title' => 'Review grouped navigation',
                        'instruction' => 'Review how modules are grouped in the sidebar so you know how to browse to a screen even when you do not know its code yet.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 3,
                        'route' => 'training/scenarios',
                        'target' => 'menuJumpInput',
                        'title' => 'Find the screen jump box',
                        'instruction' => 'Locate the screen jump input and note that it can take a screen code or route when you already know where you want to go.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 4,
                        'route' => 'training/scenarios',
                        'target' => 'menuJumpGoBtn',
                        'title' => 'Review the jump action',
                        'instruction' => 'Find the jump button and note that it opens the screen entered in the jump box.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::USERS_CREATE_DEMO => [
                'id' => self::USERS_CREATE_DEMO,
                'title' => 'Create New User',
                'screen_family' => 'users',
                'module' => 'Administration',
                'audience' => 'System Administrator / User Administrator',
                'difficulty' => 'Introductory',
                'description' => 'This guided exercise walks a trainee through creating one new user on the Users administration screens.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Users administration.',
                    'Stable target IDs for the prototype screen have been verified.',
                ],
                'next_scenario_id' => self::USERS_EDIT_RECORD,
                'runner_route' => 'training/users',
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'users/list',
                        'target' => 'users-create-btn',
                        'title' => 'Open the user form',
                        'instruction' => 'Click Create User to open the user maintenance form.',
                        'completion_mode' => 'navigation',
                    ],
                    [
                        'number' => 2,
                        'route' => 'users/edit',
                        'target' => 'Username',
                        'title' => 'Enter the username',
                        'instruction' => 'Enter a username for the new user.',
                        'sample_key' => 'Username',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'users/edit',
                        'target' => 'FirstName',
                        'title' => 'Enter the first name',
                        'instruction' => 'Enter a first name so the user record has a readable identity.',
                        'sample_key' => 'FirstName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'users/edit',
                        'target' => 'LastName',
                        'title' => 'Enter the last name',
                        'instruction' => 'Enter the last name so the user profile is complete.',
                        'sample_key' => 'LastName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'users/edit',
                        'target' => 'DisplayName',
                        'title' => 'Review the display name',
                        'instruction' => 'The display name is filled automatically from the first and last name. Review it and edit it if you want a different label.',
                        'sample_key' => 'DisplayName',
                        'completion_mode' => 'field_prefilled',
                    ],
                    [
                        'number' => 6,
                        'route' => 'users/edit',
                        'target' => 'Email',
                        'title' => 'Enter the email address',
                        'instruction' => 'Enter a valid email address for the user.',
                        'sample_key' => 'Email',
                        'completion_mode' => 'field_email',
                    ],
                    [
                        'number' => 7,
                        'route' => 'users/edit',
                        'target' => 'Phone',
                        'title' => 'Enter the phone number',
                        'instruction' => 'Enter a contact phone number for the user profile.',
                        'sample_key' => 'Phone',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 8,
                        'route' => 'users/edit',
                        'target' => 'Department',
                        'title' => 'Enter the department',
                        'instruction' => 'Enter the department or business area for the user.',
                        'sample_key' => 'Department',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 9,
                        'route' => 'users/edit',
                        'target' => 'JobTitle',
                        'title' => 'Enter the job title',
                        'instruction' => 'Enter the job title so the profile reflects the user role.',
                        'sample_key' => 'JobTitle',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 10,
                        'route' => 'users/edit',
                        'target' => 'IsActive',
                        'title' => 'Enable the user account',
                        'instruction' => 'Tick Enabled so the user can sign in to the system.',
                        'completion_mode' => 'checkbox_checked',
                    ],
                    [
                        'number' => 11,
                        'route' => 'users/edit',
                        'target' => 'users-account-flags',
                        'title' => 'Review the password options',
                        'instruction' => 'Force Password Reset prompts the user to reset their password after the next login. Must Change Password requires the user to set a new password before they can continue. Review these options, then continue to the final save step.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 12,
                        'route' => 'users/edit',
                        'target' => 'users-save-btn',
                        'title' => 'Save the user',
                        'instruction' => 'Click Save to create the new user record.',
                        'completion_mode' => 'submit_success',
                    ],
                ],
            ],
            self::USERS_EDIT_RECORD => [
                'id' => self::USERS_EDIT_RECORD,
                'title' => 'Edit Existing User',
                'screen_family' => 'users',
                'module' => 'Administration',
                'audience' => 'System Administrator / User Administrator',
                'difficulty' => 'Intermediate',
                'description' => 'This guided exercise walks a trainee through finding an existing user, updating the record, saving the change, and reviewing every tab on the Edit User screen.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Users administration.',
                    'The target user record is available for editing in this environment.',
                    'Stable target IDs for the prototype screen have been verified.',
                ],
                'next_scenario_id' => self::USERS_CREATE_DEMO,
                'runner_route' => 'training/users-edit',
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'users/list',
                        'target' => 'users-search-input',
                        'title' => 'Search for the user record',
                        'instruction' => 'Enter the target username into the search box so the list can be narrowed to the record you want to edit.',
                        'sample_key' => 'TargetUsername',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 2,
                        'route' => 'users/list',
                        'target' => 'users-filter-btn',
                        'title' => 'Filter the user list',
                        'instruction' => 'Click Filter to refresh the register and focus on the matching user record.',
                        'completion_mode' => 'navigation',
                    ],
                    [
                        'number' => 3,
                        'route' => 'users/list',
                        'target' => 'users-edit-target-btn',
                        'title' => 'Open the user record',
                        'instruction' => 'Click the highlighted Edit button to open the selected user record.',
                        'completion_mode' => 'navigation',
                        'expected_user_sample_key' => 'TargetUserID',
                    ],
                    [
                        'number' => 4,
                        'route' => 'users/edit',
                        'target' => 'Notes',
                        'title' => 'Update the Notes field',
                        'instruction' => 'Enter a note so you can practise making a safe change to the user profile.',
                        'sample_key' => 'Notes',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'users/edit',
                        'target' => 'users-save-btn',
                        'title' => 'Save the profile change',
                        'instruction' => 'Click Save to update the user profile and return to the register.',
                        'completion_mode' => 'submit_success',
                    ],
                    [
                        'number' => 6,
                        'route' => 'users/list',
                        'target' => 'users-edit-target-btn',
                        'title' => 'Reopen the edited record',
                        'instruction' => 'Open the same user again so you can review the remaining tabs on the Edit User screen.',
                        'completion_mode' => 'navigation',
                        'expected_user_sample_key' => 'TargetUserID',
                    ],
                    [
                        'number' => 7,
                        'route' => 'users/edit',
                        'target' => 'details-tab',
                        'title' => 'Open the User Details tab',
                        'instruction' => 'Click User Details to review login and metadata information for the user.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 8,
                        'route' => 'users/edit',
                        'target' => 'users-details-review',
                        'title' => 'Review the user details',
                        'instruction' => 'Review the metadata shown on this tab, including login activity, audit fields, and counters, then continue.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 9,
                        'route' => 'users/edit',
                        'target' => 'roles-tab',
                        'title' => 'Open the Roles tab',
                        'instruction' => 'Click Assign Roles to review how the user role assignments are grouped by functional area.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 10,
                        'route' => 'users/edit',
                        'target' => 'users-roles-review',
                        'title' => 'Review assigned roles',
                        'instruction' => 'Review the assigned roles and how they are grouped. No change is required for this scenario, so continue once you have reviewed the tab.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 11,
                        'route' => 'users/edit',
                        'target' => 'account-tab',
                        'title' => 'Open the Account & Access tab',
                        'instruction' => 'Click Account & Access to review the effective access information for the user.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 12,
                        'route' => 'users/edit',
                        'target' => 'users-account-review',
                        'title' => 'Review account access',
                        'instruction' => 'Review the embedded account and access information for the user, then continue to return to the register.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 13,
                        'route' => 'users/edit',
                        'target' => 'users-account-back-btn',
                        'title' => 'Return to the user list',
                        'instruction' => 'Click Back to return to the user register and finish this training scenario.',
                        'completion_mode' => 'navigation',
                    ],
                ],
            ],
            self::BASE_CONFIGURATION_READINESS_BASICS => [
                'id' => self::BASE_CONFIGURATION_READINESS_BASICS,
                'title' => 'Base Configuration Readiness Basics',
                'screen_family' => 'base-config',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Introductory',
                'description' => 'Learn how to read the Base Configuration Readiness dashboard before deeper setup work begins.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Base Configuration Readiness.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'base-config/readiness',
                        'target' => 'base-config-readiness-health-card',
                        'title' => 'Review the health score',
                        'instruction' => 'Review the Health Score card to understand the overall readiness position of the current base configuration.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 2,
                        'route' => 'base-config/readiness',
                        'target' => 'base-config-readiness-runbook',
                        'title' => 'Review the runbook guidance',
                        'instruction' => 'Read the configuration runbook guidance so you know what this readiness screen is intended to cover.',
                        'completion_mode' => 'manual_continue',
                    ],
                    [
                        'number' => 3,
                        'route' => 'base-config/readiness',
                        'target' => 'screenContent',
                        'title' => 'Scan the category checks',
                        'instruction' => 'Scan the category sections and note how each readiness check includes status, issue counts, and recommended action detail.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::SYSTEM_SETTINGS_BASICS => [
                'id' => self::SYSTEM_SETTINGS_BASICS,
                'title' => 'System Settings Basics',
                'screen_family' => 'system-settings',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Introductory',
                'description' => 'Learn the key fields on the system settings register and draft-create row without needing to save a real setting.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to System Settings.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'SettingKey' => 'TRAINING_SAMPLE_KEY',
                    'Category' => 'Application',
                    'Description' => 'Guided training setting example',
                    'SettingValue' => 'sample-value',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'system-settings/list',
                        'target' => 'systemSettingKey',
                        'title' => 'Review the setting key field',
                        'instruction' => 'Enter the sample setting key to practise the naming style used for new settings.',
                        'sample_key' => 'SettingKey',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 2,
                        'route' => 'system-settings/list',
                        'target' => 'systemSettingCategory',
                        'title' => 'Review the category field',
                        'instruction' => 'Enter or review the category used to group the setting in the catalogue.',
                        'sample_key' => 'Category',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'system-settings/list',
                        'target' => 'systemSettingDescription',
                        'title' => 'Enter a short description',
                        'instruction' => 'Enter the sample description so you can see how purpose notes are captured.',
                        'sample_key' => 'Description',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'system-settings/list',
                        'target' => 'systemSettingValue',
                        'title' => 'Enter the setting value',
                        'instruction' => 'Enter the sample setting value to practise how the create row is used.',
                        'sample_key' => 'SettingValue',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'system-settings/list',
                        'target' => 'system-settings-usage-map-btn',
                        'title' => 'Open the usage map',
                        'instruction' => 'Open the Usage Map to understand how settings are traced back to application behavior.',
                        'completion_mode' => 'click_target',
                    ],
                ],
            ],
            self::SEGMENTS_CREATE_BASICS => [
                'id' => self::SEGMENTS_CREATE_BASICS,
                'title' => 'Create Segment Basics',
                'screen_family' => 'segments',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Intermediate',
                'description' => 'Learn the key fields on the segment create form without needing to submit a new segment.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Segments maintenance.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'SegmentID' => '90',
                    'SegmentCode' => 'TRAIN_SEG',
                    'SegmentName' => 'Training Segment',
                    'Dimension' => 'ORG',
                    'Group' => 'CORE',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'segments/list',
                        'target' => 'segments-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Segment to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'segments/form',
                        'target' => 'segmentId',
                        'title' => 'Enter the segment id',
                        'instruction' => 'Enter the sample segment id used to identify the master segment row.',
                        'sample_key' => 'SegmentID',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'segments/form',
                        'target' => 'segmentCode',
                        'title' => 'Enter the segment code',
                        'instruction' => 'Enter the sample segment code used in the chart structure.',
                        'sample_key' => 'SegmentCode',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'segments/form',
                        'target' => 'segmentName',
                        'title' => 'Enter the segment name',
                        'instruction' => 'Enter the sample segment name so the segment is readable in registers and reports.',
                        'sample_key' => 'SegmentName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'segments/form',
                        'target' => 'segmentDimension',
                        'title' => 'Review the dimension mapping',
                        'instruction' => 'Review or enter the CBMS dimension mapping used by the segment.',
                        'sample_key' => 'Dimension',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'segments/form',
                        'target' => 'segments-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::SEGMENT_VALUES_CREATE_BASICS => [
                'id' => self::SEGMENT_VALUES_CREATE_BASICS,
                'title' => 'Create Segment Value Basics',
                'screen_family' => 'segment-values',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Intermediate',
                'description' => 'Learn the key fields on the segment value create form without needing to submit a new value.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Segment Values maintenance.',
                    'Fiscal years, data object codes, and segment definitions already exist in the environment.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'SegmentCode' => 'TRAIN_VAL',
                    'SegmentName' => 'Training Segment Value',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'segment-values/list',
                        'target' => 'segment-values-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Segment Value to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'segment-values/form',
                        'target' => 'segmentValueFiscalYearID',
                        'title' => 'Choose the fiscal year',
                        'instruction' => 'Select the fiscal year that will own the segment value.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'segment-values/form',
                        'target' => 'segmentValueDataObjectCode',
                        'title' => 'Choose the org unit code',
                        'instruction' => 'Enter or choose the Org Unit code used to scope the value.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'segment-values/form',
                        'target' => 'segmentValueSegmentNo',
                        'title' => 'Choose the segment',
                        'instruction' => 'Select the segment that this value belongs to.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'segment-values/form',
                        'target' => 'segmentValueSegmentCode',
                        'title' => 'Enter the segment code',
                        'instruction' => 'Enter the sample segment code used for the value.',
                        'sample_key' => 'SegmentCode',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'segment-values/form',
                        'target' => 'segmentValueSegmentName',
                        'title' => 'Enter the segment name',
                        'instruction' => 'Enter the sample segment name so the value is readable in registers and downstream screens.',
                        'sample_key' => 'SegmentName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 7,
                        'route' => 'segment-values/form',
                        'target' => 'segment-values-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::FISCAL_YEARS_CREATE_BASICS => [
                'id' => self::FISCAL_YEARS_CREATE_BASICS,
                'title' => 'Create Fiscal Year Basics',
                'screen_family' => 'fiscal-years',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Introductory',
                'description' => 'Learn the key fields on the fiscal year create form without needing to submit a record during guided training.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Fiscal Years maintenance.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'FiscalYearID' => '2027',
                    'YearLabel' => 'FY 2027',
                    'StartDate' => '2027-04-01',
                    'EndDate' => '2028-03-31',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'fiscal-years/list',
                        'target' => 'fiscal-years-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Fiscal Year to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'fiscal-years/form',
                        'target' => 'fiscalYearId',
                        'title' => 'Enter the fiscal year id',
                        'instruction' => 'Enter the sample fiscal year number used to identify the record.',
                        'sample_key' => 'FiscalYearID',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'fiscal-years/form',
                        'target' => 'fiscalYearLabel',
                        'title' => 'Enter the year label',
                        'instruction' => 'Enter the sample year label so the fiscal year is readable in lists and context selectors.',
                        'sample_key' => 'YearLabel',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'fiscal-years/form',
                        'target' => 'fiscalYearStartDate',
                        'title' => 'Enter the start date',
                        'instruction' => 'Enter the sample start date to practise the beginning of the fiscal period.',
                        'sample_key' => 'StartDate',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'fiscal-years/form',
                        'target' => 'fiscalYearEndDate',
                        'title' => 'Enter the end date',
                        'instruction' => 'Enter the sample end date to complete the fiscal period range.',
                        'sample_key' => 'EndDate',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'fiscal-years/form',
                        'target' => 'fiscal-years-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::VERSIONS_CREATE_BASICS => [
                'id' => self::VERSIONS_CREATE_BASICS,
                'title' => 'Create Version Basics',
                'screen_family' => 'versions',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Intermediate',
                'description' => 'Learn the most important fields on the version create form without committing a save.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Versions maintenance.',
                    'At least one fiscal year and one version type exist in the environment.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'VersionLabel' => 'Training Version {stamp}',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'versions/list',
                        'target' => 'versions-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Version to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'versions/form',
                        'target' => 'versionFiscalYearID',
                        'title' => 'Choose a fiscal year',
                        'instruction' => 'Select the fiscal year that will own the new version.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'versions/form',
                        'target' => 'versionLabel',
                        'title' => 'Enter the version label',
                        'instruction' => 'Enter the sample version label so the record is readable in context selectors and reports.',
                        'sample_key' => 'VersionLabel',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'versions/form',
                        'target' => 'versionTypeID',
                        'title' => 'Choose a version type',
                        'instruction' => 'Select a version type to see how the form separates submission and execution contexts.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'versions/form',
                        'target' => 'versionStatus',
                        'title' => 'Review the version status',
                        'instruction' => 'Choose or review a status value so you can see how the form controls lifecycle state.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'versions/form',
                        'target' => 'versions-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::CURRENCIES_CREATE_BASICS => [
                'id' => self::CURRENCIES_CREATE_BASICS,
                'title' => 'Create Currency Basics',
                'screen_family' => 'currencies',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Introductory',
                'description' => 'Learn the key fields on the currency create form without needing to submit a new currency during guided training.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Currencies maintenance.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'CurrencyCode' => 'TST',
                    'CurrencyName' => 'Training Currency {stamp}',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'currencies/list',
                        'target' => 'currencies-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Currency to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'currencies/form',
                        'target' => 'currencyCode',
                        'title' => 'Enter the currency code',
                        'instruction' => 'Enter the sample three-letter currency code.',
                        'sample_key' => 'CurrencyCode',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'currencies/form',
                        'target' => 'currencyName',
                        'title' => 'Enter the currency name',
                        'instruction' => 'Enter the sample currency name so the code can be recognised elsewhere in the application.',
                        'sample_key' => 'CurrencyName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'currencies/form',
                        'target' => 'currencyDecimalPlaces',
                        'title' => 'Review decimal places',
                        'instruction' => 'Review or set the decimal places field to understand how formatting is maintained for each currency.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'currencies/form',
                        'target' => 'currencies-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::CURRENCY_RATES_CREATE_BASICS => [
                'id' => self::CURRENCY_RATES_CREATE_BASICS,
                'title' => 'Create Currency Rate Basics',
                'screen_family' => 'currency-rates',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Intermediate',
                'description' => 'Learn the key fields on the currency-rate form without committing a new rate during guided training.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Currency Rates maintenance.',
                    'At least two active currencies exist in the environment.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'RateDate' => '2026-06-30',
                    'RateValue' => '1.25000000',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'currency-rates/list',
                        'target' => 'currency-rates-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Currency Rate to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'currency-rates/form',
                        'target' => 'currencyRateFromCurrencyCode',
                        'title' => 'Choose the from currency',
                        'instruction' => 'Select the currency being converted from.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'currency-rates/form',
                        'target' => 'currencyRateToCurrencyCode',
                        'title' => 'Choose the to currency',
                        'instruction' => 'Select the currency being converted to.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'currency-rates/form',
                        'target' => 'currencyRateDate',
                        'title' => 'Enter the rate date',
                        'instruction' => 'Enter the sample date for the maintained rate.',
                        'sample_key' => 'RateDate',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'currency-rates/form',
                        'target' => 'currencyRateValue',
                        'title' => 'Enter the rate value',
                        'instruction' => 'Enter the sample rate value to practise the precision expected by the form.',
                        'sample_key' => 'RateValue',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'currency-rates/form',
                        'target' => 'currency-rates-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::DATA_OBJECT_CODES_CREATE_BASICS => [
                'id' => self::DATA_OBJECT_CODES_CREATE_BASICS,
                'title' => 'Create Data Object Code Basics',
                'screen_family' => 'dataobjects',
                'module' => 'Base Configuration',
                'audience' => 'Base configuration users',
                'difficulty' => 'Introductory',
                'description' => 'Learn the key fields on the Data Object Code create form without needing to submit the record during guided training.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Data Object Codes maintenance.',
                    'At least one valid data object type is available in the environment.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'DataObjectCode' => 'TRAIN_{stamp}',
                    'DataObjectName' => 'Training Data Object {stamp}',
                    'DataObjectDesc' => 'Guided training example {stamp}',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'dataobjectcodes/create',
                        'target' => 'DataObjectCode',
                        'title' => 'Enter the data object code',
                        'instruction' => 'Enter the sample data object code to practise how a new organisational code is captured.',
                        'sample_key' => 'DataObjectCode',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 2,
                        'route' => 'dataobjectcodes/create',
                        'target' => 'DataObjectName',
                        'title' => 'Enter the data object name',
                        'instruction' => 'Enter a readable name so the code can be recognised in lists and reports.',
                        'sample_key' => 'DataObjectName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'dataobjectcodes/create',
                        'target' => 'DataObjectTypeID',
                        'title' => 'Choose a data object type',
                        'instruction' => 'Select any valid type from the dropdown so you can see how hierarchy type assignment works.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'dataobjectcodes/create',
                        'target' => 'DataObjectCodeStatus',
                        'title' => 'Review the status field',
                        'instruction' => 'Select a status value and note how the form treats active versus inactive codes.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'dataobjectcodes/create',
                        'target' => 'DataObjectDesc',
                        'title' => 'Enter a short description',
                        'instruction' => 'Enter the sample description so you can practise the free-text notes field.',
                        'sample_key' => 'DataObjectDesc',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'dataobjectcodes/create',
                        'target' => 'dataobjectcodes-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save button and note that the guided scenario stops here so you can decide whether to save under trainer supervision.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::WORKFLOW_TASK_TYPES_CREATE_BASICS => [
                'id' => self::WORKFLOW_TASK_TYPES_CREATE_BASICS,
                'title' => 'Workflow Task Type Basics',
                'screen_family' => 'workflow',
                'module' => 'Base Configuration',
                'audience' => 'Workflow administrators',
                'difficulty' => 'Introductory',
                'description' => 'Learn the core fields on the workflow task-type form without needing to submit a new task type.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Workflow Task Types maintenance.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'TaskTypeCode' => 'TRAIN_TASK_{stamp}',
                    'TaskTypeName' => 'Training Task Type {stamp}',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'workflow-task-types/list',
                        'target' => 'workflow-task-types-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Task Type to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'workflow-task-types/form',
                        'target' => 'workflowTaskTypeCode',
                        'title' => 'Enter the task type code',
                        'instruction' => 'Enter the sample code used to identify the task type.',
                        'sample_key' => 'TaskTypeCode',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'workflow-task-types/form',
                        'target' => 'workflowTaskTypeName',
                        'title' => 'Enter the task type name',
                        'instruction' => 'Enter the sample display name used by workflow users and reports.',
                        'sample_key' => 'TaskTypeName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'workflow-task-types/form',
                        'target' => 'workflow-task-types-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save Task Type button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::WORKFLOW_TASK_STATUSES_CREATE_BASICS => [
                'id' => self::WORKFLOW_TASK_STATUSES_CREATE_BASICS,
                'title' => 'Workflow Task Status Basics',
                'screen_family' => 'workflow',
                'module' => 'Base Configuration',
                'audience' => 'Workflow administrators',
                'difficulty' => 'Introductory',
                'description' => 'Learn the core fields on the workflow task-status form without needing to submit a new status.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Workflow Task Statuses maintenance.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'TaskStatusCode' => 'TRAIN_STATUS_{stamp}',
                    'TaskStatusName' => 'Training Task Status {stamp}',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'workflow-task-statuses/list',
                        'target' => 'workflow-task-statuses-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Status to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'workflow-task-statuses/form',
                        'target' => 'workflowTaskStatusCode',
                        'title' => 'Enter the status code',
                        'instruction' => 'Enter the sample code used to identify the workflow task status.',
                        'sample_key' => 'TaskStatusCode',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'workflow-task-statuses/form',
                        'target' => 'workflowTaskStatusName',
                        'title' => 'Enter the status name',
                        'instruction' => 'Enter the sample display name used by workflow users and reports.',
                        'sample_key' => 'TaskStatusName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'workflow-task-statuses/form',
                        'target' => 'workflow-task-statuses-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save Status button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::WORKFLOW_ASSIGNMENTS_CREATE_BASICS => [
                'id' => self::WORKFLOW_ASSIGNMENTS_CREATE_BASICS,
                'title' => 'Workflow Assignment Basics',
                'screen_family' => 'workflow',
                'module' => 'Base Configuration',
                'audience' => 'Workflow administrators',
                'difficulty' => 'Intermediate',
                'description' => 'Learn the key routing and assignee fields on the workflow assignment form without committing a new rule.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'The trainee has access to Workflow Assignments maintenance.',
                    'Workflow areas and stages are already configured in the environment.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'SequenceNo' => '1',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'workflow-assignments/list',
                        'target' => 'workflow-assignments-create-btn',
                        'title' => 'Open the create form',
                        'instruction' => 'Click Create Assignment to open the maintenance form.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 2,
                        'route' => 'workflow-assignments/form',
                        'target' => 'workflowAssignmentAreaCode',
                        'title' => 'Choose the workflow area',
                        'instruction' => 'Select the workflow area that the routing rule will apply to.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'workflow-assignments/form',
                        'target' => 'workflowAssignmentStageCode',
                        'title' => 'Choose the workflow stage',
                        'instruction' => 'Select the stage within that workflow area.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'workflow-assignments/form',
                        'target' => 'workflowAssignmentUserID',
                        'title' => 'Choose the assignee',
                        'instruction' => 'Select an eligible user so you can see how access-qualified assignees are presented.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'workflow-assignments/form',
                        'target' => 'workflowAssignmentSequenceNo',
                        'title' => 'Review the sequence',
                        'instruction' => 'Review or enter the sequence number that orders multiple assignees.',
                        'sample_key' => 'SequenceNo',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'workflow-assignments/form',
                        'target' => 'workflow-assignments-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save button and note that guided training stops here so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::WORKFLOW_ENGINE_DEFINITION_BASICS => [
                'id' => self::WORKFLOW_ENGINE_DEFINITION_BASICS,
                'title' => 'Workflow Definition Form Basics',
                'screen_family' => 'workflow',
                'module' => 'Workflow',
                'audience' => 'Workflow administrators',
                'difficulty' => 'Introductory',
                'description' => 'Learn the main fields on the workflow definition form before creating full stage and action structures.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'Workflow engine foundation tables are installed.',
                    'The trainee has access to workflow engine administration.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'WorkflowAreaCode' => 'TRNWF_{stamp}',
                    'WorkflowAreaName' => 'Training Workflow {stamp}',
                    'ModuleCode' => 'TRAINING',
                    'RecordTableName' => 'dbo.tblAuditLog',
                    'WorkflowDescription' => 'Guided workflow definition example {stamp}',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'workflow-engine/form',
                        'target' => 'WorkflowAreaCode',
                        'title' => 'Enter the workflow area code',
                        'instruction' => 'Enter the sample workflow area code so you can see how the definition is uniquely identified.',
                        'sample_key' => 'WorkflowAreaCode',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 2,
                        'route' => 'workflow-engine/form',
                        'target' => 'WorkflowAreaName',
                        'title' => 'Enter the workflow area name',
                        'instruction' => 'Enter a readable workflow name for users and administrators.',
                        'sample_key' => 'WorkflowAreaName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'workflow-engine/form',
                        'target' => 'WorkflowModuleCode',
                        'title' => 'Enter the module code',
                        'instruction' => 'Enter the module code used to group or recognise the workflow definition.',
                        'sample_key' => 'ModuleCode',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 4,
                        'route' => 'workflow-engine/form',
                        'target' => 'WorkflowRecordTableName',
                        'title' => 'Enter the record table name',
                        'instruction' => 'Enter the sample record table name so you can see how the workflow links to business records.',
                        'sample_key' => 'RecordTableName',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'workflow-engine/form',
                        'target' => 'WorkflowDescription',
                        'title' => 'Enter a description',
                        'instruction' => 'Enter the sample description so the workflow intent is documented for future administrators.',
                        'sample_key' => 'WorkflowDescription',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'workflow-engine/form',
                        'target' => 'workflow-engine-save-btn',
                        'title' => 'Review the save action',
                        'instruction' => 'Review the Save Definition button and note that guided training stops before submission so the form can be used safely in any environment.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::STRATEGY_RESOURCE_ENVELOPE_LINE_BASICS => [
                'id' => self::STRATEGY_RESOURCE_ENVELOPE_LINE_BASICS,
                'title' => 'Resource Envelope Line Basics',
                'screen_family' => 'strategy',
                'module' => 'Strategic Framework',
                'audience' => 'Strategy fiscal users',
                'difficulty' => 'Intermediate',
                'description' => 'Practise the most important fields on the strategic resource envelope form without committing a save.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'Resource envelope foundation tables are installed.',
                    'Funding type mapping is configured for the active fiscal year.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'CurrentYearAmount' => '125000',
                    'EnvelopeNotes' => 'Guided resource envelope example {stamp}',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'strategy-fiscal/resource-envelope-form',
                        'target' => 'FundingTypeID',
                        'title' => 'Choose a funding type',
                        'instruction' => 'Select a funding type to establish the main category for the envelope line.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 2,
                        'route' => 'strategy-fiscal/resource-envelope-form',
                        'target' => 'CurrentYearAmount',
                        'title' => 'Enter the current year amount',
                        'instruction' => 'Enter the sample amount to practise the main current-year funding value.',
                        'sample_key' => 'CurrentYearAmount',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 3,
                        'route' => 'strategy-fiscal/resource-envelope-form',
                        'target' => 'jsCopyCurrentToOuterYears',
                        'title' => 'Copy the amount to outer years',
                        'instruction' => 'Use the helper button to copy the current year amount into the outer-year fields.',
                        'completion_mode' => 'click_target',
                    ],
                    [
                        'number' => 4,
                        'route' => 'strategy-fiscal/resource-envelope-form',
                        'target' => 'OuterYear1Amount',
                        'title' => 'Confirm outer year values were populated',
                        'instruction' => 'Review the first outer-year amount field and confirm it now contains a value.',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'strategy-fiscal/resource-envelope-form',
                        'target' => 'EnvelopeNotes',
                        'title' => 'Enter envelope notes',
                        'instruction' => 'Enter the sample notes value so the purpose of the funding line is clearly documented.',
                        'sample_key' => 'EnvelopeNotes',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 6,
                        'route' => 'strategy-fiscal/resource-envelope-form',
                        'target' => 'resource-envelope-save-close-btn',
                        'title' => 'Review the save actions',
                        'instruction' => 'Review the save buttons and note that the guided scenario pauses here so you can choose whether to save under supervision.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            self::EXECUTION_RESERVATION_CREATE_BASICS => [
                'id' => self::EXECUTION_RESERVATION_CREATE_BASICS,
                'title' => 'Reservation Header Basics',
                'screen_family' => 'execution',
                'module' => 'Budget Execution',
                'audience' => 'Execution users',
                'difficulty' => 'Introductory',
                'description' => 'Practise the draft reservation header fields before creating live execution records.',
                'prerequisites' => [
                    'Training features are enabled in this environment.',
                    'Reservation foundation tables are installed.',
                    'The active version is an execution version.',
                ],
                'runner_route' => 'training/runner',
                'sample_defs' => [
                    'ReservationTitle' => 'Training Reservation {stamp}',
                    'ReservationHeaderNotes' => 'Guided reservation example {stamp}',
                ],
                'steps' => [
                    [
                        'number' => 1,
                        'route' => 'execution/reservations',
                        'target' => 'ReservationTitle',
                        'title' => 'Enter the reservation title',
                        'instruction' => 'Enter the sample title so you can practise creating a new reservation batch header.',
                        'sample_key' => 'ReservationTitle',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 2,
                        'route' => 'execution/reservations',
                        'target' => 'ReservationDate',
                        'title' => 'Review the reservation date',
                        'instruction' => 'Review the default reservation date and note that it is prefilled for convenience.',
                        'completion_mode' => 'field_prefilled',
                    ],
                    [
                        'number' => 3,
                        'route' => 'execution/reservations',
                        'target' => 'ReservationEffectiveDate',
                        'title' => 'Review the effective date',
                        'instruction' => 'Review the default effective date and note that it can be adjusted if needed.',
                        'completion_mode' => 'field_prefilled',
                    ],
                    [
                        'number' => 4,
                        'route' => 'execution/reservations',
                        'target' => 'ReservationHeaderNotes',
                        'title' => 'Enter reservation notes',
                        'instruction' => 'Enter the sample notes value so the reservation purpose is documented clearly.',
                        'sample_key' => 'ReservationHeaderNotes',
                        'completion_mode' => 'field_nonempty',
                    ],
                    [
                        'number' => 5,
                        'route' => 'execution/reservations',
                        'target' => 'reservation-create-btn',
                        'title' => 'Review the create action',
                        'instruction' => 'Review the Create Reservation button and note that guided training stops before submission so no live batch is created accidentally.',
                        'completion_mode' => 'manual_continue',
                    ],
                ],
            ],
            default => null,
        };
    }

    private static function fallbackScenarios(): array
    {
        $ids = [
            self::CBMS_FUNDAMENTALS_HOME_NAV,
            self::CBMS_FUNDAMENTALS_CONTEXT,
            self::CBMS_FUNDAMENTALS_DATASCOPE,
            self::CBMS_FUNDAMENTALS_MENU_NAV,
            self::USERS_CREATE_DEMO,
            self::USERS_EDIT_RECORD,
            self::BASE_CONFIGURATION_READINESS_BASICS,
            self::SYSTEM_SETTINGS_BASICS,
            self::SEGMENTS_CREATE_BASICS,
            self::SEGMENT_VALUES_CREATE_BASICS,
            self::FISCAL_YEARS_CREATE_BASICS,
            self::VERSIONS_CREATE_BASICS,
            self::CURRENCIES_CREATE_BASICS,
            self::CURRENCY_RATES_CREATE_BASICS,
            self::DATA_OBJECT_CODES_CREATE_BASICS,
            self::WORKFLOW_TASK_TYPES_CREATE_BASICS,
            self::WORKFLOW_TASK_STATUSES_CREATE_BASICS,
            self::WORKFLOW_ASSIGNMENTS_CREATE_BASICS,
            self::WORKFLOW_ENGINE_DEFINITION_BASICS,
            self::STRATEGY_RESOURCE_ENVELOPE_LINE_BASICS,
            self::EXECUTION_RESERVATION_CREATE_BASICS,
        ];

        $map = [];
        foreach ($ids as $scenarioId) {
            $scenario = self::fallbackScenario($scenarioId);
            if ($scenario !== null) {
                $map[$scenarioId] = $scenario;
            }
        }

        return $map;
    }
}
