<?php
declare(strict_types=1);

namespace App\Shared;

final class ScreenTestCatalog
{
    public const CBMS_CONTEXT_SMOKE = 'cbms_context_smoke';
    public const CBMS_MULTI_WINDOW_SMOKE = 'cbms_multi_window_smoke';
    public const TRAINING_CATALOGUE_SMOKE = 'training_catalogue_smoke';
    public const USERS_CREATE_SMOKE = 'users_create_smoke';
    public const USERS_ACCOUNT_ACCESS_SMOKE = 'users_account_access_smoke';
    public const BASE_CONFIG_READINESS_SMOKE = 'base_config_readiness_smoke';
    public const BASE_FISCAL_YEARS_SMOKE = 'base_fiscal_years_smoke';
    public const BASE_VERSIONS_SMOKE = 'base_versions_smoke';
    public const BASE_SYSTEM_SETTINGS_SMOKE = 'base_system_settings_smoke';
    public const BASE_CURRENCIES_SMOKE = 'base_currencies_smoke';
    public const BASE_CURRENCY_RATES_SMOKE = 'base_currency_rates_smoke';
    public const BASE_DATA_OBJECT_CODES_SMOKE = 'base_data_object_codes_smoke';
    public const BASE_SEGMENTS_SMOKE = 'base_segments_smoke';
    public const BASE_SEGMENT_VALUES_SMOKE = 'base_segment_values_smoke';
    public const BASE_WORKFLOW_ENGINE_SMOKE = 'base_workflow_engine_smoke';
    public const BASE_WORKFLOW_TASK_TYPES_SMOKE = 'base_workflow_task_types_smoke';
    public const BASE_WORKFLOW_TASK_STATUSES_SMOKE = 'base_workflow_task_statuses_smoke';
    public const BASE_WORKFLOW_ASSIGNMENTS_SMOKE = 'base_workflow_assignments_smoke';
    public const BASE_DATA_OBJECT_CODES_CREATE_SMOKE = 'base_data_object_codes_create_smoke';
    public const WORKFLOW_ENGINE_CREATE_SMOKE = 'workflow_engine_create_smoke';
    public const STRATEGY_CONFIG_READINESS_SMOKE = 'strategy_config_readiness_smoke';
    public const STRATEGY_SETUP_SECTORS_SMOKE = 'strategy_setup_sectors_smoke';
    public const STRATEGY_FISCAL_ENVELOPE_SMOKE = 'strategy_fiscal_envelope_smoke';
    public const STRATEGY_RESOURCE_ENVELOPE_CREATE_SMOKE = 'strategy_resource_envelope_create_smoke';
    public const EXECUTION_RESERVATIONS_CREATE_SMOKE = 'execution_reservations_create_smoke';

    public static function all(): array
    {
        return self::mergeCatalog(self::fallbackScenarios(), self::loadEditableOverrides());
    }

    public static function get(string $scenarioId): ?array
    {
        $scenarioId = trim($scenarioId);
        if ($scenarioId === '') {
            return null;
        }

        $all = self::all();
        return $all[$scenarioId] ?? null;
    }

    public static function forTargetRoute(string $route): array
    {
        $route = trim($route);
        if ($route === '') {
            return [];
        }

        $matches = [];
        foreach (self::all() as $scenarioId => $scenario) {
            if (strcasecmp((string) ($scenario['target_route'] ?? ''), $route) !== 0) {
                continue;
            }
            $matches[$scenarioId] = $scenario;
        }

        uasort($matches, static function (array $left, array $right): int {
            $leftTitle = (string) ($left['title'] ?? $left['id'] ?? '');
            $rightTitle = (string) ($right['title'] ?? $right['id'] ?? '');
            return strcasecmp($leftTitle, $rightTitle);
        });

        return $matches;
    }

    public static function firstForTargetRoute(string $route): ?array
    {
        foreach (self::forTargetRoute($route) as $scenario) {
            return $scenario;
        }

        return null;
    }

    public static function builtIn(): array
    {
        return self::fallbackScenarios();
    }

    public static function editableOverrides(): array
    {
        return self::loadEditableOverrides();
    }

    public static function hasEditableOverride(string $scenarioId): bool
    {
        $scenarioId = trim($scenarioId);
        if ($scenarioId === '') {
            return false;
        }

        $overrides = self::loadEditableOverrides();
        return isset($overrides[$scenarioId]);
    }

    public static function isBuiltInScenario(string $scenarioId): bool
    {
        $scenarioId = trim($scenarioId);
        if ($scenarioId === '') {
            return false;
        }

        $builtIn = self::fallbackScenarios();
        return isset($builtIn[$scenarioId]);
    }

    public static function saveEditableScenario(array $scenario): string
    {
        $normalized = self::normalizeScenario($scenario);
        $scenarioId = trim((string) ($normalized['id'] ?? ''));
        if ($scenarioId === '') {
            throw new \RuntimeException('Scenario ID is required.');
        }

        $overrides = self::loadEditableOverrides();
        $overrides[$scenarioId] = $normalized;
        self::writeEditableOverrides($overrides);

        return $scenarioId;
    }

    public static function deleteEditableScenario(string $scenarioId): void
    {
        $scenarioId = trim($scenarioId);
        if ($scenarioId === '') {
            throw new \RuntimeException('Scenario ID is required.');
        }

        $overrides = self::loadEditableOverrides();
        if (!isset($overrides[$scenarioId])) {
            return;
        }

        unset($overrides[$scenarioId]);
        self::writeEditableOverrides($overrides);
    }

    public static function storagePath(): string
    {
        return dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'screen-tests'
            . DIRECTORY_SEPARATOR . 'catalog.json';
    }

    public static function makeRun(string $scenarioId, array $context = []): ?array
    {
        $scenario = self::get($scenarioId);
        if ($scenario === null) {
            return null;
        }

        return [
            'scenario_id' => $scenarioId,
            'status' => 'active',
            'attempt_no' => 1,
            'started_at' => gmdate('Y-m-d H:i:s'),
            'context' => $context,
            'test_data' => self::previewTestData($scenarioId, $context),
        ];
    }

    public static function previewTestData(string $scenarioId, array $context = []): array
    {
        $scenario = self::get($scenarioId);
        if ($scenario === null) {
            return [];
        }

        $defs = is_array($scenario['test_data_defs'] ?? null) ? $scenario['test_data_defs'] : [];
        return self::resolveTemplateMap($defs, $context, [], self::buildTokenMap());
    }

    public static function resolveTemplateText(string $template, array $context = [], array $testData = []): string
    {
        return self::resolveTemplateValue($template, $context, $testData, self::buildTokenMap());
    }

    public static function resolveTemplateList(array $templates, array $context = [], array $testData = []): array
    {
        $resolved = [];
        $tokenMap = self::buildTokenMap();
        foreach ($templates as $template) {
            $resolved[] = self::resolveTemplateValue((string) $template, $context, $testData, $tokenMap);
        }
        return $resolved;
    }

    private static function buildTokenMap(): array
    {
        return [
            '{stamp}' => gmdate('YmdHis') . random_int(10, 99),
            '{now_ymd}' => gmdate('Y-m-d'),
            '{now_ymd_hm}' => gmdate('Y-m-d H:i'),
        ];
    }

    private static function resolveTemplateValue(string $template, array $context, array $testData, array $tokenMap): string
    {
        $value = $template;
        foreach ($tokenMap as $token => $tokenValue) {
            $value = str_replace($token, $tokenValue, $value);
        }

        if (preg_match_all('/\{context\.([a-zA-Z0-9_]+)\}/', $value, $matches)) {
            foreach ($matches[1] as $index => $contextKey) {
                $placeholder = $matches[0][$index] ?? '';
                $value = str_replace($placeholder, (string) ($context[$contextKey] ?? ''), $value);
            }
        }

        if (preg_match_all('/\{sample\.([a-zA-Z0-9_]+)\}/', $value, $matches)) {
            foreach ($matches[1] as $index => $sampleKey) {
                $placeholder = $matches[0][$index] ?? '';
                $value = str_replace($placeholder, (string) ($testData[$sampleKey] ?? ''), $value);
            }
        }

        return $value;
    }

    private static function resolveTemplateMap(array $definitions, array $context = [], array $seedData = [], ?array $tokenMap = null): array
    {
        $tokenMap = $tokenMap ?? self::buildTokenMap();
        $resolved = [];
        foreach ($definitions as $key => $template) {
            $resolved[(string) $key] = self::resolveTemplateValue((string) $template, $context, array_merge($seedData, $resolved), $tokenMap);
        }
        return $resolved;
    }

    private static function mergeCatalog(array $base, array $overrides): array
    {
        $merged = [];

        foreach ($base as $scenarioId => $scenario) {
            $merged[$scenarioId] = self::normalizeScenario(is_array($scenario) ? $scenario : ['id' => $scenarioId]);
        }

        foreach ($overrides as $scenarioId => $scenario) {
            $merged[$scenarioId] = self::normalizeScenario(is_array($scenario) ? $scenario : ['id' => $scenarioId]);
        }

        return $merged;
    }

    private static function loadEditableOverrides(): array
    {
        $path = self::storagePath();
        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        foreach ($decoded as $scenarioId => $scenario) {
            if (!is_array($scenario)) {
                continue;
            }
            $normalized = self::normalizeScenario(array_merge(['id' => (string) $scenarioId], $scenario));
            $normalizedId = trim((string) ($normalized['id'] ?? ''));
            if ($normalizedId === '') {
                continue;
            }
            $rows[$normalizedId] = $normalized;
        }

        return $rows;
    }

    private static function writeEditableOverrides(array $rows): void
    {
        $normalizedRows = [];
        foreach ($rows as $scenarioId => $scenario) {
            if (!is_array($scenario)) {
                continue;
            }
            $normalized = self::normalizeScenario(array_merge(['id' => (string) $scenarioId], $scenario));
            $normalizedId = trim((string) ($normalized['id'] ?? ''));
            if ($normalizedId === '') {
                continue;
            }
            $normalizedRows[$normalizedId] = $normalized;
        }

        ksort($normalizedRows, SORT_NATURAL | SORT_FLAG_CASE);

        $path = self::storagePath();
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Screen test catalog storage directory could not be created.');
        }

        $json = json_encode($normalizedRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json) || @file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('Screen test catalog could not be saved.');
        }
    }

    private static function normalizeScenario(array $scenario): array
    {
        $id = trim((string) ($scenario['id'] ?? ''));
        $normalized = [
            'id' => $id,
            'module' => trim((string) ($scenario['module'] ?? '')),
            'screen_family' => trim((string) ($scenario['screen_family'] ?? '')),
            'title' => trim((string) ($scenario['title'] ?? $id)),
            'description' => trim((string) ($scenario['description'] ?? '')),
            'purpose' => trim((string) ($scenario['purpose'] ?? '')),
            'audience' => trim((string) ($scenario['audience'] ?? '')),
            'difficulty' => trim((string) ($scenario['difficulty'] ?? '')),
            'target_route' => trim((string) ($scenario['target_route'] ?? 'home/index')),
            'target_label' => trim((string) ($scenario['target_label'] ?? '')),
            'baseline_context' => self::normalizeKeyValueMap($scenario['baseline_context'] ?? []),
            'prerequisites' => self::normalizeStringList($scenario['prerequisites'] ?? []),
            'test_data_defs' => self::normalizeKeyValueMap($scenario['test_data_defs'] ?? []),
            'steps' => self::normalizeSteps($scenario['steps'] ?? []),
            'expected_visible' => self::normalizeStringList($scenario['expected_visible'] ?? []),
            'expected_data' => self::normalizeStringList($scenario['expected_data'] ?? []),
            'verification_queries' => self::normalizeStringList($scenario['verification_queries'] ?? []),
            'reset_scripts' => self::normalizeResetScripts($scenario['reset_scripts'] ?? []),
        ];

        if ($normalized['target_label'] === '') {
            $normalized['target_label'] = $normalized['target_route'];
        }

        return $normalized;
    }

    private static function normalizeKeyValueMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $item) {
            $label = trim((string) $key);
            if ($label === '' && is_array($item)) {
                $label = trim((string) ($item['label'] ?? ''));
                $item = $item['value'] ?? '';
            }
            $text = trim((string) $item);
            if ($label === '' || $text === '') {
                continue;
            }
            $normalized[$label] = $text;
        }

        return $normalized;
    }

    private static function normalizeStringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            $normalized[] = $text;
        }

        return array_values($normalized);
    }

    private static function normalizeSteps(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $steps = [];
        foreach ($value as $step) {
            if (!is_array($step)) {
                continue;
            }
            $number = max(1, (int) ($step['number'] ?? 0));
            $title = trim((string) ($step['title'] ?? ''));
            $instruction = trim((string) ($step['instruction'] ?? ''));
            if ($title === '' && $instruction === '') {
                continue;
            }
            $steps[] = [
                'number' => $number,
                'title' => $title,
                'instruction' => $instruction,
            ];
        }

        usort($steps, static function (array $left, array $right): int {
            return ((int) ($left['number'] ?? 0)) <=> ((int) ($right['number'] ?? 0));
        });

        return array_values($steps);
    }

    private static function normalizeResetScripts(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $path = trim((string) ($item['path'] ?? ''));
            $note = trim((string) ($item['note'] ?? ''));
            if ($path === '') {
                continue;
            }
            $normalized[] = [
                'path' => $path,
                'note' => $note,
            ];
        }

        return array_values($normalized);
    }

    private static function fallbackScenarios(): array
    {
        return [
            self::CBMS_CONTEXT_SMOKE => [
                'id' => self::CBMS_CONTEXT_SMOKE,
                'module' => 'CBMS Fundamentals',
                'screen_family' => 'home',
                'title' => 'Home Context And DataScope Smoke',
                'description' => 'Confirms that the shared CBMS shell is usable before deeper module testing begins.',
                'purpose' => 'Validate login landing, fiscal context, DataScope selection, clear scope, and basic navigation in one short smoke script.',
                'audience' => 'All testers',
                'difficulty' => 'Introductory',
                'target_route' => 'home/index',
                'target_label' => 'Home Dashboard',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use the agreed baseline test context unless the environment lead says otherwise.',
                    'DataScope' => 'Choose one accessible ministry, department, or other data object scope.',
                ],
                'prerequisites' => [
                    'A valid CBMS test login is available.',
                    'At least one Fiscal Year and Version pair is visible to the tester.',
                    'At least one DataScope option is available to the tester.',
                ],
                'test_data_defs' => [
                    'SuggestedScope' => 'Use any accessible scope in the picker.',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open Home', 'instruction' => 'Sign in and confirm the Home screen loads without a warning or PHP error.'],
                    ['number' => 2, 'title' => 'Check fiscal context', 'instruction' => 'Confirm the current Fiscal Year and Version display in the shared header.'],
                    ['number' => 3, 'title' => 'Set DataScope', 'instruction' => 'Open the DataScope picker, choose a scope, and confirm the badge appears in the shared header.'],
                    ['number' => 4, 'title' => 'Navigate with context', 'instruction' => 'Open one other CBMS screen from the main menu and confirm the same scope remains active.'],
                    ['number' => 5, 'title' => 'Clear DataScope', 'instruction' => 'Clear the scope and confirm the scope badge and clear action disappear.'],
                    ['number' => 6, 'title' => 'Return Home', 'instruction' => 'Return to Home and confirm the fiscal context remains intact after the navigation loop.'],
                ],
                'expected_visible' => [
                    'Home loads normally after login.',
                    'Fiscal Year and Version remain visible in the shared context area.',
                    'The selected DataScope badge appears after selection and disappears after clear.',
                    'Navigation to another screen preserves the window context.',
                ],
                'expected_data' => [
                    'No database insert is required for this smoke script.',
                    'This script mainly verifies session and linked-context behaviour.',
                ],
                'verification_queries' => [],
                'reset_scripts' => [],
            ],
            self::CBMS_MULTI_WINDOW_SMOKE => [
                'id' => self::CBMS_MULTI_WINDOW_SMOKE,
                'module' => 'CBMS Fundamentals',
                'screen_family' => 'home',
                'title' => 'New Window Context Isolation Smoke',
                'description' => 'Checks that a tester can work in two active CBMS windows without context drift.',
                'purpose' => 'Validate the linked multi-window FY, Version, and DataScope behaviour before comparison-style testing begins.',
                'audience' => 'All testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'home/index',
                'target_label' => 'Home Dashboard',
                'baseline_context' => [
                    'Window A' => 'Keep the current context as the baseline.',
                    'Window B' => 'Change to a different FY, Version, or DataScope if those options are available.',
                ],
                'prerequisites' => [
                    'The tester can see the New Window button in the shared header.',
                    'The environment has more than one valid Fiscal Year / Version pair, or at least one alternate DataScope.',
                ],
                'test_data_defs' => [
                    'WindowAContext' => 'Current header context',
                    'WindowBContext' => 'A different FY, Version, or DataScope from Window A',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open baseline window', 'instruction' => 'Open a normal CBMS screen and note the current FY, Version, and DataScope state.'],
                    ['number' => 2, 'title' => 'Launch a second window', 'instruction' => 'Click New Window and confirm the same screen opens in a second browser window or tab.'],
                    ['number' => 3, 'title' => 'Change the second window context', 'instruction' => 'In the second window, change FY, Version, or DataScope to a different context.'],
                    ['number' => 4, 'title' => 'Navigate in the second window', 'instruction' => 'Open another CBMS screen from the second window and confirm its new context stays intact.'],
                    ['number' => 5, 'title' => 'Return to the first window', 'instruction' => 'Go back to the original window and confirm it still shows the original context.'],
                    ['number' => 6, 'title' => 'Cross-check both windows', 'instruction' => 'Move once more in each window and confirm neither one drags the other onto the same scope or version.'],
                ],
                'expected_visible' => [
                    'The second window opens on the same route and inherited context.',
                    'Changing context in Window B does not change Window A.',
                    'Both windows keep their own linked context through normal navigation.',
                ],
                'expected_data' => [
                    'No database insert is required for this smoke script.',
                    'This script validates per-window linked context behavior only.',
                ],
                'verification_queries' => [],
                'reset_scripts' => [],
            ],
            self::TRAINING_CATALOGUE_SMOKE => [
                'id' => self::TRAINING_CATALOGUE_SMOKE,
                'module' => 'Training',
                'screen_family' => 'training',
                'title' => 'Training Catalogue And Fundamentals Launch',
                'description' => 'Confirms that the learner-facing training catalogue loads and can launch a CBMS Fundamentals scenario.',
                'purpose' => 'Validate the training catalogue filters, fundamentals module grouping, and a short launch/leave cycle.',
                'audience' => 'All testers',
                'difficulty' => 'Introductory',
                'target_route' => 'training/scenarios',
                'target_label' => 'Training Scenarios',
                'baseline_context' => [
                    'Login' => 'Any signed-in user may run this script.',
                ],
                'prerequisites' => [
                    'Training features are enabled in the environment.',
                    'The Training Scenarios route is available to the tester.',
                ],
                'test_data_defs' => [
                    'ScenarioCode' => 'cbms_fundamentals_context',
                    'ModuleFilter' => 'CBMS Fundamentals',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open the catalogue', 'instruction' => 'Open Training Scenarios and confirm the catalogue loads without a route error.'],
                    ['number' => 2, 'title' => 'Filter to fundamentals', 'instruction' => 'Filter the list to the CBMS Fundamentals module and confirm only those scenarios remain visible.'],
                    ['number' => 3, 'title' => 'Launch a fundamentals scenario', 'instruction' => 'Open Fiscal Context Basics or another CBMS Fundamentals scenario from the catalogue.'],
                    ['number' => 4, 'title' => 'Open the guided screen', 'instruction' => 'Use the runner or overlay action to open the target screen and confirm the guided instructions appear.'],
                    ['number' => 5, 'title' => 'Leave and return cleanly', 'instruction' => 'Leave the scenario and return to a plain Training Scenarios page without a stale completed overlay reopening by itself.'],
                ],
                'expected_visible' => [
                    'The catalogue shows module grouping and filters.',
                    'CBMS Fundamentals scenarios can launch from the catalogue.',
                    'Returning to the plain catalogue does not resurrect the last completed overlay unless explicitly reopened.',
                ],
                'expected_data' => [
                    'If the training progress table exists, a tblTrainingProgress row should be created or updated for the tester and scenario.',
                ],
                'verification_queries' => [
                    "SELECT TOP 1 ScenarioCode, Status, CurrentStep, AttemptNo, LastActivityAt\nFROM dbo.tblTrainingProgress\nWHERE UserID = {context.user_id}\n  AND ScenarioCode = N'{sample.ScenarioCode}'\nORDER BY TrainingProgressID DESC;",
                ],
                'reset_scripts' => [],
            ],
            self::USERS_CREATE_SMOKE => [
                'id' => self::USERS_CREATE_SMOKE,
                'module' => 'Administration',
                'screen_family' => 'users',
                'title' => 'Users Create Record Smoke',
                'description' => 'Exercises the Users administration list and create form with known sample values.',
                'purpose' => 'Validate that a tester with Users permissions can create a basic user account and find it again.',
                'audience' => 'Users admin testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'users/list',
                'target_label' => 'Users List',
                'baseline_context' => [
                    'Access' => 'Run this script only with a login that has Users edit or admin access.',
                ],
                'prerequisites' => [
                    'The tester has USERS_EDIT or USERS_ADMIN access.',
                    'The email and username generated for the run do not already exist.',
                ],
                'test_data_defs' => [
                    'Username' => 'qa_user_{stamp}',
                    'FirstName' => 'QA',
                    'LastName' => 'Tester',
                    'DisplayName' => 'QA Tester {stamp}',
                    'Email' => 'qa_user_{stamp}@example.com',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open Users list', 'instruction' => 'Open Users List and confirm the grid loads normally.'],
                    ['number' => 2, 'title' => 'Open the create form', 'instruction' => 'Choose the create/add action and confirm the user form opens.'],
                    ['number' => 3, 'title' => 'Enter sample values', 'instruction' => 'Use the generated Username, Display Name, Email, First Name, and Last Name from this test run.'],
                    ['number' => 4, 'title' => 'Save the record', 'instruction' => 'Save the user and confirm a success message or successful return flow.'],
                    ['number' => 5, 'title' => 'Find the saved user', 'instruction' => 'Search or reopen the list and confirm the new user can be found by username or email.'],
                ],
                'expected_visible' => [
                    'The Users list and form both load without PHP or permission errors.',
                    'Saving returns a success flow instead of a validation crash.',
                    'The created user can be found again in the list.',
                ],
                'expected_data' => [
                    'A tblUsers row should exist for the generated username and email.',
                ],
                'verification_queries' => [
                    "SELECT TOP 1 UserID, Username, DisplayName, Email\nFROM dbo.tblUsers\nWHERE Username = N'{sample.Username}'\n   OR Email = N'{sample.Email}'\nORDER BY UserID DESC;",
                ],
                'reset_scripts' => [],
            ],
            self::USERS_ACCOUNT_ACCESS_SMOKE => [
                'id' => self::USERS_ACCOUNT_ACCESS_SMOKE,
                'module' => 'Administration',
                'screen_family' => 'users',
                'title' => 'Users Account Access Iframe Smoke',
                'description' => 'Checks that the Account Access panel stays focused on the selected user.',
                'purpose' => 'Validate the iframe-based account access view and its refresh action after the linked-context fixes.',
                'audience' => 'Users admin testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'users/list',
                'target_label' => 'Users List',
                'baseline_context' => [
                    'Target user' => 'Use your own user record or a known accessible test user.',
                ],
                'prerequisites' => [
                    'The tester has access to open a user form.',
                    'At least one user record exists and can be opened for review.',
                ],
                'test_data_defs' => [
                    'TargetUsername' => '{context.username}',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open a user record', 'instruction' => 'From Users List, open a user record for edit or view.'],
                    ['number' => 2, 'title' => 'Open Account Access', 'instruction' => 'Switch to the Account Access area and confirm the iframe loads.'],
                    ['number' => 3, 'title' => 'Check target user identity', 'instruction' => 'Confirm the iframe still refers to the selected target user and not the logged-in admin by mistake.'],
                    ['number' => 4, 'title' => 'Run refresh access', 'instruction' => 'Use the refresh/rebuild access action if it is available and confirm the same target user remains in focus after the postback.'],
                ],
                'expected_visible' => [
                    'The iframe loads for the selected user.',
                    'Refreshing access does not drift back to the currently logged-in admin account.',
                ],
                'expected_data' => [
                    'No direct database insert is required for this smoke script.',
                ],
                'verification_queries' => [],
                'reset_scripts' => [],
            ],
            self::BASE_CONFIG_READINESS_SMOKE => [
                'id' => self::BASE_CONFIG_READINESS_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'base-config',
                'title' => 'Base Configuration Readiness Gate',
                'description' => 'Opens the readiness dashboard and confirms the initial setup gate can be reviewed before broader UAT begins.',
                'purpose' => 'Use this as the first screen test in a fresh environment or controlled retest cycle.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Introductory',
                'target_route' => 'base-config/readiness',
                'target_label' => 'Base Configuration Readiness',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use FY 2026 / Version 1 for the fresh-start INITTEST baseline unless the test lead approves a different context.',
                    'Database' => 'CBMSv2_INITTEST',
                ],
                'prerequisites' => [
                    'Run this script with an administrator or equivalent base configuration login.',
                    'The shared shell and quick links should already be reachable after login.',
                ],
                'test_data_defs' => [
                    'ExpectedBaseline' => 'FY 2026 / Version 1',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open readiness screen', 'instruction' => 'Open Base Configuration Readiness and confirm the page loads without a PHP error or blank shell.'],
                    ['number' => 2, 'title' => 'Check summary cards', 'instruction' => 'Review the top summary cards and confirm the dashboard reports usable counts, health, or blocker information.'],
                    ['number' => 3, 'title' => 'Review category sections', 'instruction' => 'Scroll through the readiness categories and confirm fiscal context, organisation structure, segments, security, workflow, and system configuration sections appear.'],
                    ['number' => 4, 'title' => 'Capture blockers and warnings', 'instruction' => 'Record any critical blocker or warning that would stop deeper testing from starting.'],
                    ['number' => 5, 'title' => 'Open one linked setup screen', 'instruction' => 'Use a quick link or related action from the readiness page and confirm navigation works without losing context.'],
                ],
                'expected_visible' => [
                    'The readiness dashboard loads fully in the shared shell.',
                    'Summary status and category-level detail are visible and readable.',
                    'The screen acts as a working entry point into the setup sequence.',
                ],
                'expected_data' => [
                    'This script is review-only and should not create transactional data.',
                ],
                'verification_queries' => [
                    "SELECT FiscalYearID, VersionID, VersionLabel, IsActive, IsDefault\nFROM dbo.tblVersions\nORDER BY FiscalYearID, VersionID;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_FISCAL_YEARS_SMOKE => [
                'id' => self::BASE_FISCAL_YEARS_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'fiscal-years',
                'title' => 'Fiscal Years Register And Form Smoke',
                'description' => 'Checks that the fiscal year register loads, the create form opens, and the shared context remains stable during the round trip.',
                'purpose' => 'Validate one of the core baseline master-data screens before version and context testing expands.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Introductory',
                'target_route' => 'fiscal-years/list',
                'target_label' => 'Fiscal Years',
                'baseline_context' => [
                    'Permissions' => 'Run this script with BASE_CONFIG_VIEW, BASE_CONFIG_EDIT, ADMIN_ALL, or SYSADMIN access.',
                ],
                'prerequisites' => [
                    'The tester can access the Base Configuration menu.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Fiscal Years',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open fiscal years register', 'instruction' => 'Open Fiscal Years and confirm the register loads without a PHP error or broken shell.'],
                    ['number' => 2, 'title' => 'Review cards and register', 'instruction' => 'Confirm the status cards, filters, and register columns render with readable data or a clean empty-state.'],
                    ['number' => 3, 'title' => 'Open the create form', 'instruction' => 'Use Create Fiscal Year to open the form and confirm the route change works cleanly.'],
                    ['number' => 4, 'title' => 'Review form sections', 'instruction' => 'Confirm the Identity, Dates, and Status sections all render with usable controls.'],
                    ['number' => 5, 'title' => 'Return without saving', 'instruction' => 'Return to the register without creating a new row and confirm the list still loads normally.'],
                ],
                'expected_visible' => [
                    'The register and create form both load in the shared shell.',
                    'The form exposes fiscal year id, year label, date range, and default-status fields.',
                ],
                'expected_data' => [
                    'This smoke script is non-destructive and should not require a committed save.',
                ],
                'verification_queries' => [
                    "SELECT TOP 20 FiscalYearID, YearLabel, StartDate, EndDate, IsActive, IsSystemDefault\nFROM dbo.tblFiscalYears\nORDER BY FiscalYearID DESC;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_VERSIONS_SMOKE => [
                'id' => self::BASE_VERSIONS_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'versions',
                'title' => 'Versions Register And Form Smoke',
                'description' => 'Checks that the version register loads, filtering works, and the create form opens with the expected lineage and status fields.',
                'purpose' => 'Validate the version maintenance flow before strategic or execution testing depends on the configured version set.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'versions/list',
                'target_label' => 'Versions',
                'baseline_context' => [
                    'Permissions' => 'Run this script with BASE_CONFIG_VIEW, BASE_CONFIG_EDIT, ADMIN_ALL, or SYSADMIN access.',
                ],
                'prerequisites' => [
                    'At least one fiscal year and one version type exist in the environment.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Versions',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open versions register', 'instruction' => 'Open Versions and confirm the register loads without a route, SQL, or PHP error.'],
                    ['number' => 2, 'title' => 'Review filters and flags', 'instruction' => 'Confirm the fiscal year, version type, search, and status filters render correctly.'],
                    ['number' => 3, 'title' => 'Open the create form', 'instruction' => 'Use Create Version to open a blank version form.'],
                    ['number' => 4, 'title' => 'Review type and lineage fields', 'instruction' => 'Confirm the form exposes identity, type, status, base-version lineage, and flags sections.'],
                    ['number' => 5, 'title' => 'Return without saving', 'instruction' => 'Return to the list and confirm the version register still loads in the same shared context.'],
                ],
                'expected_visible' => [
                    'The register and create form both load correctly.',
                    'The form exposes fiscal year, version label, version type, status, and base-version controls.',
                ],
                'expected_data' => [
                    'This smoke script is non-destructive and should not create a new version.',
                ],
                'verification_queries' => [
                    "SELECT TOP 20 FiscalYearID, VersionID, VersionLabel, VersionStatus, IsActive, IsDefault, IsSystemDefault\nFROM dbo.tblVersions\nORDER BY FiscalYearID DESC, VersionID DESC;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_SYSTEM_SETTINGS_SMOKE => [
                'id' => self::BASE_SYSTEM_SETTINGS_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'system-settings',
                'title' => 'System Settings Baseline Review',
                'description' => 'Confirms that required system settings exist for the agreed baseline context and session handling.',
                'purpose' => 'Validate the initial system configuration values that support stable sign-in and default fiscal context resolution.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Introductory',
                'target_route' => 'system-settings/list',
                'target_label' => 'System Settings',
                'baseline_context' => [
                    'Required keys' => 'DEFAULT_FISCAL_YEAR, DEFAULT_VERSION, SESSION_IDLE_TIMEOUT_SEC, SESSION_ABSOLUTE_TIMEOUT_MIN',
                ],
                'prerequisites' => [
                    'The tester has SYSSETTINGS_VIEW, SYSSETTINGS_EDIT, ADMIN_ALL, or SYSADMIN access.',
                ],
                'test_data_defs' => [
                    'ExpectedFiscalYear' => '2026',
                    'ExpectedVersion' => '1',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open settings register', 'instruction' => 'Open System Settings and confirm the register loads without error.'],
                    ['number' => 2, 'title' => 'Find baseline keys', 'instruction' => 'Locate the default fiscal year, default version, and session timeout keys.'],
                    ['number' => 3, 'title' => 'Verify values', 'instruction' => 'Confirm the stored values are populated and align with the agreed UAT baseline or approved environment defaults.'],
                    ['number' => 4, 'title' => 'Open usage map if needed', 'instruction' => 'If a key is unclear, open the Usage Map and confirm the setting has a visible purpose in the application.'],
                    ['number' => 5, 'title' => 'Record mismatches', 'instruction' => 'Log any missing, blank, deprecated, or contradictory setting before moving into deeper module testing.'],
                ],
                'expected_visible' => [
                    'The settings register shows the expected keys and values.',
                    'The environment default context can be reasoned about from the stored configuration.',
                ],
                'expected_data' => [
                    'This script should normally be non-destructive unless the tester is explicitly correcting a configuration defect.',
                ],
                'verification_queries' => [
                    "SELECT SettingKey, SettingValue\nFROM dbo.tblSystemSettings\nWHERE SettingKey IN (N'DEFAULT_FISCAL_YEAR', N'DEFAULT_VERSION', N'SESSION_IDLE_TIMEOUT_SEC', N'SESSION_ABSOLUTE_TIMEOUT_MIN')\nORDER BY SettingKey;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_CURRENCIES_SMOKE => [
                'id' => self::BASE_CURRENCIES_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'currencies',
                'title' => 'Currencies Register And Form Smoke',
                'description' => 'Checks that the currency register loads, the create form opens, and the main identity and status fields are available.',
                'purpose' => 'Validate the shared currency master before exchange-rate and version-base-currency testing continues.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Introductory',
                'target_route' => 'currencies/list',
                'target_label' => 'Currencies',
                'baseline_context' => [
                    'Permissions' => 'Run this script with BASE_CONFIG_VIEW, BASE_CONFIG_EDIT, ADMIN_ALL, or SYSADMIN access.',
                ],
                'prerequisites' => [
                    'The tester can access the Base Configuration menu.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Currencies',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open currencies register', 'instruction' => 'Open Currencies and confirm the register loads in the shared shell.'],
                    ['number' => 2, 'title' => 'Review actions and usage data', 'instruction' => 'Confirm the register shows filters, import/export actions, and usage indicators without layout issues.'],
                    ['number' => 3, 'title' => 'Open the create form', 'instruction' => 'Use Create Currency to open a blank currency form.'],
                    ['number' => 4, 'title' => 'Review form layout', 'instruction' => 'Confirm the form exposes currency code, name, symbol, formatting, and default/active controls.'],
                    ['number' => 5, 'title' => 'Return without saving', 'instruction' => 'Return to the register and confirm the list still loads cleanly.'],
                ],
                'expected_visible' => [
                    'The register and form both load correctly.',
                    'The form exposes the identity and formatting fields needed for maintenance.',
                ],
                'expected_data' => [
                    'This smoke script is non-destructive and should not create a new currency.',
                ],
                'verification_queries' => [
                    "SELECT TOP 20 CurrencyCode, CurrencyName, CurrencySymbol, DecimalPlaces, IsActive, IsSystemDefault\nFROM dbo.tblCurrencies\nORDER BY CurrencyCode;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_CURRENCY_RATES_SMOKE => [
                'id' => self::BASE_CURRENCY_RATES_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'currency-rates',
                'title' => 'Currency Rates Register And Form Smoke',
                'description' => 'Checks that the currency rate register loads and that the create form exposes the expected pair, date, and rate fields.',
                'purpose' => 'Validate exchange-rate maintenance after the currency master is in place.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'currency-rates/list',
                'target_label' => 'Currency Rates',
                'baseline_context' => [
                    'Permissions' => 'Run this script with BASE_CONFIG_VIEW, BASE_CONFIG_EDIT, ADMIN_ALL, or SYSADMIN access.',
                ],
                'prerequisites' => [
                    'Active currencies already exist so the pair selectors can populate.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Currency Rates',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open currency rates register', 'instruction' => 'Open Currency Rates and confirm the register loads without an error.'],
                    ['number' => 2, 'title' => 'Review filters and pair summary', 'instruction' => 'Confirm the pair filters, latest rate card, and register columns all render correctly.'],
                    ['number' => 3, 'title' => 'Open the create form', 'instruction' => 'Use Create Currency Rate to open a blank rate form.'],
                    ['number' => 4, 'title' => 'Review form fields', 'instruction' => 'Confirm the form exposes the from/to currencies, rate date, rate type, rate value, and status controls.'],
                    ['number' => 5, 'title' => 'Return without saving', 'instruction' => 'Return to the register without inserting a new rate.'],
                ],
                'expected_visible' => [
                    'The register and form both load correctly.',
                    'The form exposes the full rate pair and rate detail fields.',
                ],
                'expected_data' => [
                    'This smoke script is non-destructive and should not create a new exchange rate.',
                ],
                'verification_queries' => [
                    "SELECT TOP 20 FromCurrencyCode, ToCurrencyCode, RateDate, RateType, RateValue, IsActive\nFROM dbo.tblCurrencyRates\nORDER BY RateDate DESC, FromCurrencyCode, ToCurrencyCode;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_DATA_OBJECT_CODES_SMOKE => [
                'id' => self::BASE_DATA_OBJECT_CODES_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'dataobjects',
                'title' => 'Data Object Codes And Scope Readiness',
                'description' => 'Checks that organisation structure data needed for DataScope and workflow routing is visible in the current baseline year.',
                'purpose' => 'Validate the organisation structure foundation before strategy and execution screens depend on scoped access.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'dataobjectcodes/index',
                'target_label' => 'Data Object Codes',
                'baseline_context' => [
                    'Fiscal Year' => 'Use FY 2026 for baseline checks unless the environment lead directs otherwise.',
                ],
                'prerequisites' => [
                    'The tester has DATAOBJECTCODES_ADMIN, ADMIN_ALL, or SYSADMIN access.',
                    'Data object type and hierarchy source data should already be loaded for the active test year.',
                ],
                'test_data_defs' => [
                    'ExpectedFiscalYear' => '2026',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open data object codes', 'instruction' => 'Open Data Object Codes and confirm the register loads without raw error output.'],
                    ['number' => 2, 'title' => 'Filter to baseline year if needed', 'instruction' => 'Use the available filters to review FY 2026 organisation rows.'],
                    ['number' => 3, 'title' => 'Review code coverage', 'instruction' => 'Confirm rows exist and appear to include the expected organisation codes, labels, and type information.'],
                    ['number' => 4, 'title' => 'Check access or scope linkage', 'instruction' => 'Open a related access or scope management view if available and confirm the screen is reachable.'],
                    ['number' => 5, 'title' => 'Record gaps', 'instruction' => 'Note any missing hierarchy, empty year coverage, or broken access path before moving into scoped module testing.'],
                ],
                'expected_visible' => [
                    'The organisation code register loads normally.',
                    'Baseline-year structure is visible or a clean empty-state explains why it is not.',
                    'Related access-management navigation is available for follow-up.',
                ],
                'expected_data' => [
                    'This script is review-focused and should not require inserting new codes during smoke testing.',
                ],
                'verification_queries' => [
                    "SELECT TOP 50 FiscalYearID, DataObjectCode, DataObjectName, DataObjectTypeID\nFROM dbo.tblDataObjectCodes\nWHERE FiscalYearID = 2026\nORDER BY DataObjectCode;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_SEGMENTS_SMOKE => [
                'id' => self::BASE_SEGMENTS_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'segments',
                'title' => 'Segment Catalogue Setup Review',
                'description' => 'Confirms that the segment catalogue screen loads and that the structural chart definitions are present.',
                'purpose' => 'Validate the core coding structure required by financial, strategy, and execution flows.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'segments/list',
                'target_label' => 'Segments',
                'baseline_context' => [
                    'Focus' => 'Review segment numbering, dimension mapping, and hierarchy support for the current chart design.',
                ],
                'prerequisites' => [
                    'The tester has BASE_CONFIG_VIEW, BASE_CONFIG_EDIT, ADMIN_ALL, or SYSADMIN access.',
                ],
                'test_data_defs' => [
                    'ExpectedArea' => 'Segment catalogue',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open segments register', 'instruction' => 'Open Segments and confirm the register loads in the shared shell.'],
                    ['number' => 2, 'title' => 'Review structure columns', 'instruction' => 'Confirm the register exposes segment number, code, length, position, and other structural fields.'],
                    ['number' => 3, 'title' => 'Open a form', 'instruction' => 'Open an existing segment or the add form and confirm the form layout is complete and readable.'],
                    ['number' => 4, 'title' => 'Compare to intended design', 'instruction' => 'Check whether the segment ordering and dimension assignments match the current baseline design notes.'],
                    ['number' => 5, 'title' => 'Return safely', 'instruction' => 'Return to the list without unintended edits and record any missing or inconsistent definitions.'],
                ],
                'expected_visible' => [
                    'The segment register and form both load correctly.',
                    'Structure-related fields are visible enough to validate the chart design.',
                ],
                'expected_data' => [
                    'This smoke script is non-destructive unless a deliberate setup correction is being performed.',
                ],
                'verification_queries' => [
                    "SELECT SegmentID, SegmentCode, SegmentName, StartPoint, EndPoint, CBMSDimension, SegmentGroup, UsedInFinancialAccount, UsedInStrategicPlanning\nFROM dbo.tblSegments\nORDER BY SegmentID;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_SEGMENT_VALUES_SMOKE => [
                'id' => self::BASE_SEGMENT_VALUES_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'segment-values',
                'title' => 'Segment Values Baseline Review',
                'description' => 'Checks that segment values are loaded for the baseline year and that the maintenance screen is usable.',
                'purpose' => 'Validate that downstream planning and posting screens have foundational coding values to work with.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'segment-values/list',
                'target_label' => 'Segment Values',
                'baseline_context' => [
                    'Fiscal Year' => 'FY 2026 baseline',
                ],
                'prerequisites' => [
                    'The tester has BASE_CONFIG_VIEW, BASE_CONFIG_EDIT, ADMIN_ALL, or SYSADMIN access.',
                    'The segment catalogue should already exist before this check is run.',
                ],
                'test_data_defs' => [
                    'ExpectedFiscalYear' => '2026',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open segment values list', 'instruction' => 'Open Segment Values and confirm the screen loads without a runtime error.'],
                    ['number' => 2, 'title' => 'Filter to FY 2026', 'instruction' => 'Filter or review the list specifically for the baseline fiscal year.'],
                    ['number' => 3, 'title' => 'Review value coverage', 'instruction' => 'Confirm the list shows values for key segments or a valid empty-state if the environment is intentionally unseeded.'],
                    ['number' => 4, 'title' => 'Open a form or upload screen', 'instruction' => 'Open the form or upload path and confirm the maintenance flow is reachable.'],
                    ['number' => 5, 'title' => 'Record data quality issues', 'instruction' => 'Note duplicates, orphan parent issues, or obvious load gaps before continuing into module testing.'],
                ],
                'expected_visible' => [
                    'The value register and maintenance path open normally.',
                    'Baseline-year values can be reviewed directly from the screen.',
                ],
                'expected_data' => [
                    'This script is usually non-destructive in smoke mode.',
                ],
                'verification_queries' => [
                    "SELECT TOP 100 FiscalYearID, DataObjectCode, SegmentNo, SegmentCode, SegmentName, ParentSegmentNo, ParentSegmentCode\nFROM dbo.tblSegmentValues\nWHERE FiscalYearID = 2026\nORDER BY SegmentNo, SegmentCode, DataObjectCode;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_WORKFLOW_ENGINE_SMOKE => [
                'id' => self::BASE_WORKFLOW_ENGINE_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'workflow',
                'title' => 'Workflow Engine Foundation Review',
                'description' => 'Confirms that the shared workflow engine definition and assignment screens load as part of initial setup readiness.',
                'purpose' => 'Validate the workflow foundation before testing workflow-driven strategy and execution documents.',
                'audience' => 'Workflow and base configuration testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'workflow-engine/list',
                'target_label' => 'Workflow Engine Definitions',
                'baseline_context' => [
                    'Focus areas' => 'Definitions, inquiry, diagnostics, and assignments',
                ],
                'prerequisites' => [
                    'The tester has WORKFLOW_VIEW, WORKFLOW_EDIT, WORKFLOW_ADMIN, ADMIN_ALL, or SYSADMIN access.',
                    'The workflow foundation SQL should already be installed.',
                ],
                'test_data_defs' => [
                    'ExpectedWorkflowArea' => 'At least one live workflow definition',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open workflow definitions', 'instruction' => 'Open Workflow Engine Definitions and confirm the list renders correctly.'],
                    ['number' => 2, 'title' => 'Open inquiry', 'instruction' => 'Navigate to Workflow Engine Inquiry and confirm the filters, metrics, and result panel load.'],
                    ['number' => 3, 'title' => 'Open diagnostics', 'instruction' => 'Navigate to Workflow Diagnostics and confirm the screen loads inside the shared shell.'],
                    ['number' => 4, 'title' => 'Open assignments', 'instruction' => 'Open Workflow Assignments and confirm the register or form path is reachable.'],
                    ['number' => 5, 'title' => 'Record missing foundation items', 'instruction' => 'Log any missing definitions, broken routes, or empty critical setup areas before running workflow-enabled document tests.'],
                ],
                'expected_visible' => [
                    'Definitions, inquiry, diagnostics, and assignments are all reachable.',
                    'The workflow engine uses the shared shell and quick-link structure consistently.',
                ],
                'expected_data' => [
                    'This script is review-only in smoke mode.',
                ],
                'verification_queries' => [
                    "SELECT TOP 20 WorkflowAreaCode, WorkflowAreaName, ActiveFlag\nFROM dbo.tblWorkflowDefinition\nORDER BY WorkflowAreaCode;",
                    "SELECT TOP 20 s.WorkflowAreaCode, s.WorkflowStageCode, a.WorkflowActionCode\nFROM dbo.tblWorkflowDefinitionStage s\nLEFT JOIN dbo.tblWorkflowDefinitionAction a\n  ON a.WorkflowDefinitionID = s.WorkflowDefinitionID\n AND a.FromStageCode = s.WorkflowStageCode\nORDER BY s.WorkflowAreaCode, s.WorkflowStageCode, a.WorkflowActionCode;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_WORKFLOW_TASK_TYPES_SMOKE => [
                'id' => self::BASE_WORKFLOW_TASK_TYPES_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'workflow',
                'title' => 'Workflow Task Types Register And Form Smoke',
                'description' => 'Checks that the workflow task-type register loads and that the create form exposes the expected code, name, and active fields.',
                'purpose' => 'Validate the task-type master data before workflow task execution and reporting tests begin.',
                'audience' => 'Workflow and base configuration testers',
                'difficulty' => 'Introductory',
                'target_route' => 'workflow-task-types/list',
                'target_label' => 'Workflow Task Types',
                'baseline_context' => [
                    'Permissions' => 'Run this script with WORKFLOW_VIEW, WORKFLOW_EDIT, WORKFLOW_ADMIN, ADMIN_ALL, or SYSADMIN access.',
                ],
                'prerequisites' => [
                    'Workflow task-type foundation tables are installed.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Workflow Task Types',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open task types register', 'instruction' => 'Open Workflow Task Types and confirm the register loads without error.'],
                    ['number' => 2, 'title' => 'Review filters and usage counts', 'instruction' => 'Confirm the search, status filter, and usage counts render correctly.'],
                    ['number' => 3, 'title' => 'Open the create form', 'instruction' => 'Use Create Task Type to open a blank task-type form.'],
                    ['number' => 4, 'title' => 'Review form fields', 'instruction' => 'Confirm the form exposes code, name, and active-status controls.'],
                    ['number' => 5, 'title' => 'Return without saving', 'instruction' => 'Return to the register without saving a new task type.'],
                ],
                'expected_visible' => [
                    'The task-type register and form both load correctly.',
                    'The form exposes the core identity fields needed for maintenance.',
                ],
                'expected_data' => [
                    'This smoke script is non-destructive and should not create a new task type.',
                ],
                'verification_queries' => [
                    "SELECT TOP 20 TaskTypeID, Code, Name, IsActive\nFROM dbo.tblWorkflowTaskTypes\nORDER BY TaskTypeID DESC;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_WORKFLOW_TASK_STATUSES_SMOKE => [
                'id' => self::BASE_WORKFLOW_TASK_STATUSES_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'workflow',
                'title' => 'Workflow Task Statuses Register And Form Smoke',
                'description' => 'Checks that the workflow task-status register loads and that the create form exposes the expected code, name, and active fields.',
                'purpose' => 'Validate the task-status master data before workflow task transitions and reporting tests begin.',
                'audience' => 'Workflow and base configuration testers',
                'difficulty' => 'Introductory',
                'target_route' => 'workflow-task-statuses/list',
                'target_label' => 'Workflow Task Statuses',
                'baseline_context' => [
                    'Permissions' => 'Run this script with WORKFLOW_VIEW, WORKFLOW_EDIT, WORKFLOW_ADMIN, ADMIN_ALL, or SYSADMIN access.',
                ],
                'prerequisites' => [
                    'Workflow task-status foundation tables are installed.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Workflow Task Statuses',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open task statuses register', 'instruction' => 'Open Workflow Task Statuses and confirm the register loads without error.'],
                    ['number' => 2, 'title' => 'Review filters and usage counts', 'instruction' => 'Confirm the search, status filter, and usage counts render correctly.'],
                    ['number' => 3, 'title' => 'Open the create form', 'instruction' => 'Use Create Status to open a blank task-status form.'],
                    ['number' => 4, 'title' => 'Review form fields', 'instruction' => 'Confirm the form exposes code, name, and active-status controls.'],
                    ['number' => 5, 'title' => 'Return without saving', 'instruction' => 'Return to the register without saving a new task status.'],
                ],
                'expected_visible' => [
                    'The task-status register and form both load correctly.',
                    'The form exposes the core identity fields needed for maintenance.',
                ],
                'expected_data' => [
                    'This smoke script is non-destructive and should not create a new task status.',
                ],
                'verification_queries' => [
                    "SELECT TOP 20 StatusID, Code, Name, IsActive\nFROM dbo.tblWorkflowTaskStatuses\nORDER BY StatusID DESC;",
                ],
                'reset_scripts' => [],
            ],
            self::BASE_WORKFLOW_ASSIGNMENTS_SMOKE => [
                'id' => self::BASE_WORKFLOW_ASSIGNMENTS_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'workflow',
                'title' => 'Workflow Assignments Register And Form Smoke',
                'description' => 'Checks that the workflow assignment register loads and that the assignment form exposes the expected routing and assignee controls.',
                'purpose' => 'Validate the routing assignment setup before testing workflow-driven approvals.',
                'audience' => 'Workflow and base configuration testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'workflow-assignments/list',
                'target_label' => 'Workflow Assignments',
                'baseline_context' => [
                    'Permissions' => 'Run this script with WORKFLOW_VIEW, WORKFLOW_EDIT, WORKFLOW_ADMIN, ADMIN_ALL, or SYSADMIN access.',
                ],
                'prerequisites' => [
                    'Workflow areas and stages are already configured.',
                    'The workflow assignments table may be optional in some environments; an install warning is acceptable during smoke review.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Workflow Assignments',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open assignments register', 'instruction' => 'Open Workflow Assignments and confirm the page loads inside the shared shell.'],
                    ['number' => 2, 'title' => 'Review filters and register', 'instruction' => 'Confirm the workflow area, stage, and status filters render correctly along with the assignment grid or install warning.'],
                    ['number' => 3, 'title' => 'Open the create form', 'instruction' => 'Use Create Assignment to open a blank assignment form.'],
                    ['number' => 4, 'title' => 'Review routing fields', 'instruction' => 'Confirm the form exposes workflow area, stage, assignee, sequence, primary, and active controls.'],
                    ['number' => 5, 'title' => 'Return without saving', 'instruction' => 'Return to the register without creating a new assignment rule.'],
                ],
                'expected_visible' => [
                    'The assignments register and form both load correctly.',
                    'The form exposes the routing and assignee fields needed for maintenance.',
                ],
                'expected_data' => [
                    'This smoke script is non-destructive and should not create a new assignment rule.',
                ],
                'verification_queries' => [],
                'reset_scripts' => [],
            ],
            self::STRATEGY_CONFIG_READINESS_SMOKE => [
                'id' => self::STRATEGY_CONFIG_READINESS_SMOKE,
                'module' => 'Strategic Framework',
                'screen_family' => 'strategy',
                'title' => 'Strategy Configuration Readiness Review',
                'description' => 'Checks that the Strategic Framework readiness screen loads with the active fiscal context and shows actionable readiness feedback.',
                'purpose' => 'Validate the entry-point readiness view before deeper strategic setup and data-entry testing begins.',
                'audience' => 'Strategy configuration testers',
                'difficulty' => 'Introductory',
                'target_route' => 'strategy-config/configuration-readiness',
                'target_label' => 'Strategy Configuration Readiness',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use an active strategic planning context that should contain some setup data.',
                    'Permissions' => 'Run this script with a login that can view Strategic Framework configuration pages.',
                ],
                'prerequisites' => [
                    'The tester has STRATEGY_CONFIG_EDIT, ADMIN_ALL, or SYSADMIN access.',
                    'A valid Fiscal Year and Version are selected in the shared header.',
                ],
                'test_data_defs' => [
                    'ExpectedModule' => 'Strategic Framework',
                    'ExpectedScreen' => 'Configuration Readiness',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open readiness screen', 'instruction' => 'Open Strategy Configuration Readiness and confirm the page loads without a PHP error, blank card, or permission failure.'],
                    ['number' => 2, 'title' => 'Check active context', 'instruction' => 'Confirm the page is still using the current Fiscal Year and Version from the shared header.'],
                    ['number' => 3, 'title' => 'Review readiness sections', 'instruction' => 'Confirm the screen shows readiness cards, checks, or status sections instead of an empty placeholder.'],
                    ['number' => 4, 'title' => 'Inspect at least one issue or status area', 'instruction' => 'Open or review one visible readiness area and confirm the page provides usable detail for follow-up work.'],
                    ['number' => 5, 'title' => 'Navigate away and back', 'instruction' => 'Open one other Strategy screen and return, confirming the readiness page still loads in the same context.'],
                ],
                'expected_visible' => [
                    'The readiness page loads successfully for the active strategic context.',
                    'The screen shows readiness content or status messaging, not a broken or empty shell.',
                    'Context remains stable when navigating within Strategic Framework.',
                ],
                'expected_data' => [
                    'This script is review-focused and should not require a database insert.',
                ],
                'verification_queries' => [],
                'reset_scripts' => [],
            ],
            self::STRATEGY_SETUP_SECTORS_SMOKE => [
                'id' => self::STRATEGY_SETUP_SECTORS_SMOKE,
                'module' => 'Strategic Framework',
                'screen_family' => 'strategy',
                'title' => 'Strategy Sector Setup List And Form Smoke',
                'description' => 'Exercises the sector setup list and opens the sector form in the active strategy context.',
                'purpose' => 'Validate one of the core Strategic Framework setup maintenance screens and its form flow.',
                'audience' => 'Strategy setup testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'strategy-setup/sectors',
                'target_label' => 'Strategy Sectors',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use the strategic setup context agreed for current testing.',
                    'Permissions' => 'Run this script with a login that can maintain Strategic Framework setup screens.',
                ],
                'prerequisites' => [
                    'The tester has STRATEGY_SETUP_EDIT, ADMIN_ALL, or SYSADMIN access.',
                    'A valid Fiscal Year and Version are selected in the shared header.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Sectors',
                    'ReviewAction' => 'Open an existing sector or the add form, then return safely without unintended edits.',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open sectors list', 'instruction' => 'Open the Strategy Sectors screen and confirm the list or grid loads normally.'],
                    ['number' => 2, 'title' => 'Check list content', 'instruction' => 'Confirm at least one visible column, row set, or empty-state message renders correctly for the current context.'],
                    ['number' => 3, 'title' => 'Open sector form', 'instruction' => 'Use either an add action or an existing row action to open the sector form.'],
                    ['number' => 4, 'title' => 'Review form layout', 'instruction' => 'Confirm the form renders labels, action buttons, and context-sensitive content without PHP errors or missing sections.'],
                    ['number' => 5, 'title' => 'Return to list', 'instruction' => 'Return to the list without creating unintended data and confirm the screen still works after the form round trip.'],
                ],
                'expected_visible' => [
                    'The sectors list loads in the selected strategic context.',
                    'The sector form opens successfully from the list.',
                    'The tester can return to the list without breaking navigation or context.',
                ],
                'expected_data' => [
                    'This smoke script does not require a committed save.',
                    'If the tester chooses to save intentionally, that should be logged separately as a deliberate setup test.',
                ],
                'verification_queries' => [],
                'reset_scripts' => [],
            ],
            self::STRATEGY_FISCAL_ENVELOPE_SMOKE => [
                'id' => self::STRATEGY_FISCAL_ENVELOPE_SMOKE,
                'module' => 'Strategic Framework',
                'screen_family' => 'strategy',
                'title' => 'Strategy Resource Envelope Summary Smoke',
                'description' => 'Checks that the fiscal resource envelope summary opens and responds to the active strategic context.',
                'purpose' => 'Validate a key Strategic Framework fiscal screen before deeper envelope maintenance or reporting tests begin.',
                'audience' => 'Strategy fiscal testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'strategy-fiscal/resource-envelope',
                'target_label' => 'Resource Envelope Summary',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use a strategic planning version where envelope data is expected or where an empty-state message is acceptable.',
                    'Permissions' => 'Run this script with a login that can view or edit Strategic Framework fiscal screens.',
                ],
                'prerequisites' => [
                    'The tester has STRATEGY_FISCAL_EDIT, ADMIN_ALL, or SYSADMIN access.',
                    'A valid Fiscal Year and Version are selected in the shared header.',
                ],
                'test_data_defs' => [
                    'ExpectedScreen' => 'Resource Envelope Summary',
                    'ExpectedContext' => 'Active FY {context.fy} / Ver {context.ver}',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open resource envelope summary', 'instruction' => 'Open the Resource Envelope Summary screen and confirm the page loads without a route or PHP error.'],
                    ['number' => 2, 'title' => 'Check active context', 'instruction' => 'Confirm the shared header context still matches the intended Fiscal Year and Version for the run.'],
                    ['number' => 3, 'title' => 'Review summary content', 'instruction' => 'Confirm the page shows summary data, cards, a grid, or a valid empty-state message rather than a broken layout.'],
                    ['number' => 4, 'title' => 'Open a related action if available', 'instruction' => 'If the screen provides a drill-down or form action, open it and confirm the related page inherits the same context.'],
                    ['number' => 5, 'title' => 'Return to summary', 'instruction' => 'Return to the summary and confirm the page remains stable after the navigation loop.'],
                ],
                'expected_visible' => [
                    'The resource envelope screen opens correctly in the active strategic context.',
                    'Visible fiscal content or a valid empty-state message is rendered.',
                    'Related navigation remains within the same FY and Version context.',
                ],
                'expected_data' => [
                    'This script is intended as a non-destructive smoke test.',
                ],
                'verification_queries' => [],
                'reset_scripts' => [],
            ],
            self::BASE_DATA_OBJECT_CODES_CREATE_SMOKE => [
                'id' => self::BASE_DATA_OBJECT_CODES_CREATE_SMOKE,
                'module' => 'Base Configuration',
                'screen_family' => 'dataobjects',
                'title' => 'Data Object Codes Create Flow',
                'description' => 'Creates one new data object code in the active fiscal context and confirms it is visible again in the register.',
                'purpose' => 'Validate the list-to-form flow, core validation fields, and a successful save for Data Object Codes.',
                'audience' => 'Base configuration testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'dataobjectcodes/index',
                'target_label' => 'Data Object Codes',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use the agreed base-configuration test context for the current cycle.',
                    'Permissions' => 'Run this script with a login that can maintain data object codes.',
                ],
                'prerequisites' => [
                    'The tester has DATAOBJECTCODES_ADMIN, ADMIN_ALL, or SYSADMIN access.',
                    'At least one valid data object type exists in the environment.',
                    'The generated code for this run does not already exist in the active fiscal year.',
                ],
                'test_data_defs' => [
                    'DataObjectCode' => 'TRAIN_{stamp}',
                    'DataObjectName' => 'Training Data Object {stamp}',
                    'DataObjectDesc' => 'Created during screen test run {stamp}',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open the register', 'instruction' => 'Open Data Object Codes and confirm the register loads without a route, permission, or PHP error.'],
                    ['number' => 2, 'title' => 'Open create form', 'instruction' => 'Use the add action to open the create form.'],
                    ['number' => 3, 'title' => 'Enter the test code and name', 'instruction' => 'Enter the generated DataObjectCode and DataObjectName for this run.'],
                    ['number' => 4, 'title' => 'Choose a valid type and status', 'instruction' => 'Select any valid active data object type, keep status Active unless the test lead says otherwise, and enter the generated description.'],
                    ['number' => 5, 'title' => 'Save the code', 'instruction' => 'Save the record and confirm the flow returns successfully instead of crashing or failing silently.'],
                    ['number' => 6, 'title' => 'Find the saved row', 'instruction' => 'Use the register search or list view to confirm the newly created code is visible in the active fiscal year.'],
                ],
                'expected_visible' => [
                    'The register and create form both load in the shared shell.',
                    'Saving returns a success flow instead of a validation crash.',
                    'The created code can be found again in the register.',
                ],
                'expected_data' => [
                    'A tblDataObjectCodes row should exist for the generated DataObjectCode in the active fiscal year.',
                ],
                'verification_queries' => [
                    "SELECT TOP 1 FiscalYearID, DataObjectCode, DataObjectName, DataObjectTypeID, DataObjectCodeStatus\nFROM dbo.tblDataObjectCodes\nWHERE FiscalYearID = {context.fy}\n  AND DataObjectCode = N'{sample.DataObjectCode}';",
                ],
                'reset_scripts' => [],
            ],
            self::WORKFLOW_ENGINE_CREATE_SMOKE => [
                'id' => self::WORKFLOW_ENGINE_CREATE_SMOKE,
                'module' => 'Workflow',
                'screen_family' => 'workflow',
                'title' => 'Workflow Engine Definition Create Flow',
                'description' => 'Creates one workflow definition and confirms it appears in the workflow engine register.',
                'purpose' => 'Validate the workflow engine list-to-definition flow and one successful save of a workflow definition.',
                'audience' => 'Workflow admin testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'workflow-engine/list',
                'target_label' => 'Workflow Engine',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use the agreed admin context for workflow testing.',
                    'Permissions' => 'Run this script with a login that can maintain workflow engine definitions.',
                ],
                'prerequisites' => [
                    'Workflow engine foundation tables are installed.',
                    'The tester has WORKFLOW_ADMIN, ADMIN_ALL, or SYSADMIN access.',
                    'The generated workflow area code for this run does not already exist.',
                ],
                'test_data_defs' => [
                    'WorkflowAreaCode' => 'TRNWF_{stamp}',
                    'WorkflowAreaName' => 'Training Workflow {stamp}',
                    'ModuleCode' => 'TRAINING',
                    'RecordTableName' => 'dbo.tblAuditLog',
                    'Description' => 'Created during workflow screen test run {stamp}',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open workflow engine list', 'instruction' => 'Open Workflow Engine and confirm the definitions register loads cleanly.'],
                    ['number' => 2, 'title' => 'Open create definition form', 'instruction' => 'Use the create action to open a blank workflow definition form.'],
                    ['number' => 3, 'title' => 'Enter workflow identity fields', 'instruction' => 'Enter the generated WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, and Description values for this run.'],
                    ['number' => 4, 'title' => 'Save definition', 'instruction' => 'Save the definition and confirm the form returns successfully without a validation or SQL error.'],
                    ['number' => 5, 'title' => 'Confirm new definition is listed', 'instruction' => 'Verify the new workflow definition appears in the workflow engine register and can be reopened.'],
                ],
                'expected_visible' => [
                    'The workflow engine register and create form both load successfully.',
                    'Saving a definition returns a success flow instead of an exception.',
                    'The saved definition is visible again in the workflow register.',
                ],
                'expected_data' => [
                    'A tblWorkflowDefinition row should exist for the generated WorkflowAreaCode.',
                ],
                'verification_queries' => [
                    "SELECT TOP 1 WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, ActiveFlag\nFROM dbo.tblWorkflowDefinition\nWHERE WorkflowAreaCode = N'{sample.WorkflowAreaCode}';",
                ],
                'reset_scripts' => [],
            ],
            self::STRATEGY_RESOURCE_ENVELOPE_CREATE_SMOKE => [
                'id' => self::STRATEGY_RESOURCE_ENVELOPE_CREATE_SMOKE,
                'module' => 'Strategic Framework',
                'screen_family' => 'strategy',
                'title' => 'Resource Envelope Line Create Flow',
                'description' => 'Creates one resource envelope line in the active strategic context and confirms it is saved.',
                'purpose' => 'Validate the resource envelope summary-to-form flow and one successful save of a strategic funding line.',
                'audience' => 'Strategy fiscal testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'strategy-fiscal/resource-envelope',
                'target_label' => 'Resource Envelope Summary',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use a strategic planning context where resource envelope maintenance is allowed.',
                    'Permissions' => 'Run this script with a login that can maintain Strategic Framework fiscal screens.',
                ],
                'prerequisites' => [
                    'Resource envelope foundation tables are installed.',
                    'Funding type mapping is configured for the active fiscal year.',
                    'The tester has STRATEGY_FISCAL_EDIT, ADMIN_ALL, or SYSADMIN access.',
                ],
                'test_data_defs' => [
                    'CurrentYearAmount' => '125000',
                    'EnvelopeNotes' => 'Training resource envelope line {stamp}',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open resource envelope summary', 'instruction' => 'Open the resource envelope summary and confirm the page loads in the active strategic context.'],
                    ['number' => 2, 'title' => 'Open the add form', 'instruction' => 'Use an add action to open the resource envelope line form.'],
                    ['number' => 3, 'title' => 'Choose a funding type', 'instruction' => 'Select any valid funding type for the active context. Choose a funding source as well if the environment requires one.'],
                    ['number' => 4, 'title' => 'Enter amounts and notes', 'instruction' => 'Enter the CurrentYearAmount for this run, populate or project any required outer-year fields, and enter the generated EnvelopeNotes value.'],
                    ['number' => 5, 'title' => 'Save the line', 'instruction' => 'Save the line and confirm the application returns successfully without a validation or SQL error.'],
                    ['number' => 6, 'title' => 'Confirm the saved line exists', 'instruction' => 'Reopen the form or list view and confirm the newly saved envelope line is present in the active context.'],
                ],
                'expected_visible' => [
                    'The resource envelope summary and form both load correctly.',
                    'The save flow completes successfully for one envelope line.',
                    'The saved line can be found again in the active strategic context.',
                ],
                'expected_data' => [
                    'A tblSbResourceEnvelope row should exist for the active fiscal year, version, and generated EnvelopeNotes value.',
                ],
                'verification_queries' => [
                    "SELECT TOP 1 ResourceEnvelopeID, FiscalYearID, VersionID, FundingTypeID, FundingSourceID, CurrentYearAmount, EnvelopeNotes\nFROM dbo.tblSbResourceEnvelope\nWHERE FiscalYearID = {context.fy}\n  AND VersionID = {context.ver}\n  AND EnvelopeNotes = N'{sample.EnvelopeNotes}'\nORDER BY ResourceEnvelopeID DESC;",
                ],
                'reset_scripts' => [],
            ],
            self::EXECUTION_RESERVATIONS_CREATE_SMOKE => [
                'id' => self::EXECUTION_RESERVATIONS_CREATE_SMOKE,
                'module' => 'Budget Execution',
                'screen_family' => 'execution',
                'title' => 'Reservations Draft Create Flow',
                'description' => 'Creates one draft reservation batch in the active execution context and confirms it appears in the register.',
                'purpose' => 'Validate the reservations create flow and one successful draft save before deeper workflow testing begins.',
                'audience' => 'Execution testers',
                'difficulty' => 'Intermediate',
                'target_route' => 'execution/reservations',
                'target_label' => 'Reservations',
                'baseline_context' => [
                    'Fiscal Year / Version' => 'Use an execution version context with reservation foundation tables installed.',
                    'Permissions' => 'Run this script with a login that can create reservations in Budget Execution.',
                ],
                'prerequisites' => [
                    'Reservation foundation tables are installed.',
                    'The active version is an execution version.',
                    'The tester has execution create or admin access.',
                ],
                'test_data_defs' => [
                    'ReservationTitle' => 'Training Reservation {stamp}',
                    'Notes' => 'Created during reservation screen test run {stamp}',
                ],
                'steps' => [
                    ['number' => 1, 'title' => 'Open reservations screen', 'instruction' => 'Open Reservations and confirm the page loads in the current execution context.'],
                    ['number' => 2, 'title' => 'Enter reservation header values', 'instruction' => 'Enter the generated ReservationTitle and Notes for this run. Keep the default dates unless a different date is required for the test cycle.'],
                    ['number' => 3, 'title' => 'Save draft reservation', 'instruction' => 'Create the reservation batch and confirm the application returns successfully without a validation or SQL error.'],
                    ['number' => 4, 'title' => 'Confirm the draft is listed', 'instruction' => 'Verify the newly created reservation appears in the register and can be opened.'],
                ],
                'expected_visible' => [
                    'The reservations page loads correctly in the active execution context.',
                    'The create-reservation flow returns successfully.',
                    'The saved draft reservation appears in the register.',
                ],
                'expected_data' => [
                    'A tblBeReservation row should exist for the generated ReservationTitle in the active execution context.',
                ],
                'verification_queries' => [
                    "SELECT TOP 1 ReservationID, FiscalYearID, ExecutionVersionID, ReservationNo, ReservationTitle, ReservationStatusCode\nFROM dbo.tblBeReservation\nWHERE FiscalYearID = {context.fy}\n  AND ExecutionVersionID = {context.ver}\n  AND ReservationTitle = N'{sample.ReservationTitle}'\nORDER BY ReservationID DESC;",
                ],
                'reset_scripts' => [],
            ],
        ];
    }
}
