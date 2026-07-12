<?php
declare(strict_types=1);

return [
    'planning_submission_readiness_smoke' => [
        'id' => 'planning_submission_readiness_smoke',
        'module' => 'Planning',
        'screen_family' => 'planning',
        'title' => 'Planning Submission Readiness Smoke',
        'description' => 'Checks that the submission readiness screen loads for the active planning context and provides useful readiness feedback.',
        'purpose' => 'Validate the planning readiness entry point before broader planning test cycles begin.',
        'audience' => 'Planning testers, budget analysts, implementation leads',
        'difficulty' => 'Introductory',
        'target_route' => 'strategy-reports/submission-readiness',
        'target_label' => 'Submission Readiness',
        'baseline_context' => [
            'Fiscal Year / Version' => 'Use the active planning version for the current test cycle.',
            'Permissions' => 'Run with a user who can view planning readiness or strategy reports.',
        ],
        'prerequisites' => [
            'A valid Fiscal Year and Version are selected.',
            'Planning setup has been seeded or intentionally left in a known empty-state.',
        ],
        'test_data_defs' => [
            'ExpectedScreen' => 'Submission Readiness',
        ],
        'steps' => [
            ['number' => 1, 'title' => 'Open submission readiness', 'instruction' => 'Open Submission Readiness and confirm the screen loads without a route, permission, or PHP error.'],
            ['number' => 2, 'title' => 'Confirm fiscal context', 'instruction' => 'Check that the shared fiscal year and version match the intended planning test context.'],
            ['number' => 3, 'title' => 'Review readiness sections', 'instruction' => 'Confirm readiness cards, issue lists, or a valid empty-state message are visible.'],
            ['number' => 4, 'title' => 'Inspect one readiness item', 'instruction' => 'Open or review one warning, blocker, or status item and confirm the message is understandable.'],
            ['number' => 5, 'title' => 'Return safely', 'instruction' => 'Navigate away to another planning screen and return to confirm context and layout remain stable.'],
        ],
        'expected_visible' => [
            'Submission readiness content or a clean empty-state is displayed.',
            'The selected Fiscal Year and Version remain stable.',
            'No fatal error, blank shell, or broken layout appears.',
        ],
        'expected_data' => [
            'This smoke script is review-only and should not create data.',
        ],
        'verification_queries' => [],
        'reset_scripts' => [],
    ],
    'budget_execution_reductions_smoke' => [
        'id' => 'budget_execution_reductions_smoke',
        'module' => 'Budget Execution',
        'screen_family' => 'execution',
        'title' => 'Budget Reductions Review Smoke',
        'description' => 'Checks that the budget reductions screen opens and supports review of the active execution context.',
        'purpose' => 'Validate a key Budget Execution workflow screen before detailed execution testing.',
        'audience' => 'Budget execution testers, budget officers',
        'difficulty' => 'Intermediate',
        'target_route' => 'execution/budget-reductions',
        'target_label' => 'Budget Reductions',
        'baseline_context' => [
            'Fiscal Year / Version' => 'Use an execution version where reduction review is allowed.',
            'Permissions' => 'Run with Budget Execution view, edit, review, or admin access.',
        ],
        'prerequisites' => [
            'Budget Execution routes are enabled.',
            'The active context is appropriate for execution testing.',
        ],
        'test_data_defs' => [
            'ExpectedScreen' => 'Budget Reductions',
        ],
        'steps' => [
            ['number' => 1, 'title' => 'Open budget reductions', 'instruction' => 'Open Budget Reductions and confirm the page loads without an application error.'],
            ['number' => 2, 'title' => 'Review filters or controls', 'instruction' => 'Confirm visible filters, action buttons, or status panels render correctly.'],
            ['number' => 3, 'title' => 'Review list content', 'instruction' => 'Confirm a grid, summary, or valid empty-state is shown for the current execution context.'],
            ['number' => 4, 'title' => 'Open preview if available', 'instruction' => 'If a preview action is available and safe, open it and confirm it inherits the same context.'],
            ['number' => 5, 'title' => 'Return to list', 'instruction' => 'Return to Budget Reductions and confirm the screen remains stable.'],
        ],
        'expected_visible' => [
            'Budget Reductions loads in the shared shell.',
            'Execution context remains stable.',
            'The screen provides usable controls or a clear empty-state.',
        ],
        'expected_data' => [
            'This seed script does not require a committed reduction transaction.',
        ],
        'verification_queries' => [],
        'reset_scripts' => [],
    ],
    'analytics_overview_smoke' => [
        'id' => 'analytics_overview_smoke',
        'module' => 'Analytics',
        'screen_family' => 'analytics',
        'title' => 'Analytics Overview Smoke',
        'description' => 'Checks that the Analytics overview route loads and presents usable dashboard or reporting entry points.',
        'purpose' => 'Validate the Analytics module entry point after menu, permission, or context changes.',
        'audience' => 'Analytics users, reporting testers, implementation leads',
        'difficulty' => 'Introductory',
        'target_route' => 'analytics',
        'target_label' => 'Analytics Overview',
        'baseline_context' => [
            'Permissions' => 'Run with ANALYTICS_VIEW, ADMIN_ALL, or SYSADMIN access.',
        ],
        'prerequisites' => [
            'Analytics menu items are visible to the tester.',
            'Any required analytics services or embedded dashboards are configured for the environment.',
        ],
        'test_data_defs' => [
            'ExpectedModule' => 'Analytics',
        ],
        'steps' => [
            ['number' => 1, 'title' => 'Open Analytics', 'instruction' => 'Open the Analytics overview and confirm the route resolves without an error.'],
            ['number' => 2, 'title' => 'Review landing content', 'instruction' => 'Confirm cards, dashboard links, report links, or a valid configuration message is displayed.'],
            ['number' => 3, 'title' => 'Check permissions', 'instruction' => 'Confirm the page does not expose actions the tester should not have, and does not hide allowed actions unexpectedly.'],
            ['number' => 4, 'title' => 'Open a related analytics link', 'instruction' => 'Open one related dashboard or report link if available and confirm navigation works.'],
        ],
        'expected_visible' => [
            'Analytics overview loads without a fatal error.',
            'Dashboard/report entry points or a clear configuration message are visible.',
        ],
        'expected_data' => [
            'This script is non-destructive.',
        ],
        'verification_queries' => [],
        'reset_scripts' => [],
    ],
    'testing_scripts_assignment_smoke' => [
        'id' => 'testing_scripts_assignment_smoke',
        'module' => 'Testing Scripts',
        'screen_family' => 'screen-tests',
        'title' => 'Testing Scripts Assignment Smoke',
        'description' => 'Checks that test coordinators can open the assignment screen, review scripts by module, and see user assignment controls.',
        'purpose' => 'Validate the Testing Scripts assignment workflow before assigning UAT work to users.',
        'audience' => 'Test coordinators, implementation leads',
        'difficulty' => 'Introductory',
        'target_route' => 'screen-tests-admin/assignments',
        'target_label' => 'Assign Test Scripts',
        'baseline_context' => [
            'Permissions' => 'Run with TEST_SCRIPT_ADMIN, ADMIN_ALL, or SYSADMIN access.',
        ],
        'prerequisites' => [
            'Screen test assignment storage has been installed.',
            'At least one active user exists for assignment testing.',
        ],
        'test_data_defs' => [
            'ExpectedScreen' => 'Assign Test Scripts',
        ],
        'steps' => [
            ['number' => 1, 'title' => 'Open assignment screen', 'instruction' => 'Open Assign Test Scripts and confirm it loads without a storage warning or route error.'],
            ['number' => 2, 'title' => 'Review user picker', 'instruction' => 'Search for an active user and confirm the picker returns readable user names.'],
            ['number' => 3, 'title' => 'Review module groups', 'instruction' => 'Open the Module Group selector and confirm modules such as Planning, Budget Execution, Analytics, and Testing Scripts are available.'],
            ['number' => 4, 'title' => 'Review specific scripts', 'instruction' => 'Scroll the script checklist and confirm scripts are grouped by module.'],
            ['number' => 5, 'title' => 'Review cleanup controls', 'instruction' => 'Confirm the assignment cleanup controls are visible and clearly separated from the assignment action.'],
        ],
        'expected_visible' => [
            'User search, workflow group, module group, script checklist, and cleanup controls render correctly.',
            'Duplicate assignment handling is described or enforced by the assignment flow.',
        ],
        'expected_data' => [
            'This script can be run as review-only. If an assignment is created intentionally, record the assigned user and script in the result notes.',
        ],
        'verification_queries' => [
            "SELECT COUNT(1) AS ActiveAssignments\nFROM dbo.tblScreenTestAssignments\nWHERE ActiveFlag = 1;",
        ],
        'reset_scripts' => [],
    ],
    'testing_scripts_results_export_smoke' => [
        'id' => 'testing_scripts_results_export_smoke',
        'module' => 'Testing Scripts',
        'screen_family' => 'screen-tests',
        'title' => 'Testing Results Export Smoke',
        'description' => 'Checks that test result and coordinator summary export options are visible and use the current filters.',
        'purpose' => 'Validate evidence export before UAT sign-off reporting.',
        'audience' => 'Test coordinators, auditors, implementation leads',
        'difficulty' => 'Introductory',
        'target_route' => 'screen-tests-admin/summary',
        'target_label' => 'Testing Summary',
        'baseline_context' => [
            'Permissions' => 'Run with TEST_SCRIPT_ADMIN, ADMIN_ALL, or SYSADMIN access.',
        ],
        'prerequisites' => [
            'Screen test run storage is installed.',
            'At least one test assignment or saved run exists for a richer export.',
        ],
        'test_data_defs' => [
            'ExpectedExport' => 'TestingSummary Excel',
        ],
        'steps' => [
            ['number' => 1, 'title' => 'Open Testing Summary', 'instruction' => 'Open the Testing Summary screen and confirm counters, filters, and detail rows load.'],
            ['number' => 2, 'title' => 'Apply a filter', 'instruction' => 'Filter by module or status and confirm the on-screen results update.'],
            ['number' => 3, 'title' => 'Export summary', 'instruction' => 'Use Export Excel and confirm a spreadsheet downloads without a PHP error.'],
            ['number' => 4, 'title' => 'Open Test Results', 'instruction' => 'Open Run History/Test Results and confirm its Export Excel option is also visible.'],
            ['number' => 5, 'title' => 'Export results', 'instruction' => 'Export the filtered Test Results and confirm assignment progress and run history sections are included.'],
        ],
        'expected_visible' => [
            'Testing Summary and Test Results both expose Export Excel.',
            'Exports respect the current filters.',
        ],
        'expected_data' => [
            'Downloaded spreadsheets should include readable section and status labels.',
        ],
        'verification_queries' => [],
        'reset_scripts' => [],
    ],
];
