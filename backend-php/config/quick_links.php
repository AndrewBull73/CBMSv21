<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Quick Links Registry
|--------------------------------------------------------------------------
|
| This file is the single source of truth for shared Quick Links.
|
| How it works:
| - The shared layout decides whether a route family is allowed to show
|   Quick Links in `app/Views/layouts/main.php`.
| - If the current route is eligible, the renderer in
|   `app/Views/strategy/_QuickNav.php` loads this file.
| - The first group whose `patterns` match the current route becomes the
|   active Quick Links group for that screen.
|
| Group structure:
| [
|   'code' => 'BESR',
|   'title' => 'Budget Execution',
|   'patterns' => [
|       'execution/',                    // prefix match
|       'some/route/exact',              // exact match
|   ],
|   'links' => [
|       ['code' => 'BESR', 'label' => 'Setup & Rollover', 'route' => 'execution/index'],
|       ['code' => 'BEOB', 'label' => 'Opening Balances', 'route' => 'execution/opening-balances'],
|   ],
| ]
|
| Matching rules:
| - A pattern ending in `/` is treated as a route prefix.
| - A pattern without a trailing `/` is treated as an exact route.
| - Put more specific groups earlier than broader groups.
|
| Optional link flags:
| - `requiresAdmin` => true
|     Only show the link to ADMIN_ALL / SYSADMIN users.
| - `requiresTraining` => true
|     Only show the link when training features are enabled.
| - `requiresTesting` => true
|     Only show the link when screen testing features are enabled.
|
| Maintenance rules:
| - Use Quick Links for related-screen navigation only.
| - Do not use Quick Links for page actions like Save, Create, Run, Back,
|   Download, or Reload.
| - Keep each group short and focused. Aim for 3-7 links.
| - Include the current screen in the group when it helps orientation.
|
| To add Quick Links for a new module:
| 1. Add the route prefix to `$sharedGuidanceRoutePrefixes` in
|    `app/Views/layouts/main.php`.
| 2. Add a new group below with the correct `patterns` and `links`.
| 3. Make sure the linked routes already exist in `config/routes.php`.
|
*/

return [
    [
        'code' => 'SFSR',
        'title' => 'Start & Review',
        'patterns' => [
            'strategy/index',
            'strategy-config/configuration-readiness',
            'strategy-reports/submission-readiness',
            'strategy-reports/readiness',
            'strategy-reports/summary',
        ],
        'links' => [
            ['code' => 'SFOH', 'label' => __t('menu_strategy_overview'), 'route' => 'strategy/index'],
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFSR', 'label' => __t('strategy_submission_readiness'), 'route' => 'strategy-reports/submission-readiness'],
            ['code' => 'SFSU', 'label' => __t('strategy_strategic_summary'), 'route' => 'strategy-reports/summary'],
        ],
    ],
    [
        'code' => 'SFCF',
        'title' => __t('strategy_nav_configuration'),
        'patterns' => [
            'strategy-config/',
            'strategy-publish/',
        ],
        'links' => [
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFSM', 'label' => __t('strategy_segment_mapping'), 'route' => 'strategy-config/segment-mapping'],
            ['code' => 'SFID', 'label' => __t('strategy_import_dimensions'), 'route' => 'strategy-config/import-dashboard'],
            ['code' => 'SFPL', 'label' => __t('strategy_fiscal_period_labels'), 'route' => 'strategy-config/fiscal-periods'],
            ['code' => 'SFFA', 'label' => 'Fiscal Assumptions', 'route' => 'strategy-config/fiscal-assumptions'],
            ['code' => 'SFPP', 'label' => 'Custom Phasing Profiles', 'route' => 'strategy-config/phasing-profiles'],
            ['code' => 'SFRO', 'label' => __t('strategy_rollover'), 'route' => 'strategy-config/rollover'],
            ['code' => 'SFPU', 'label' => 'Segment Publication', 'route' => 'strategy-publish/requests'],
            ['code' => 'SFRC', 'label' => __t('strategy_source_resolution'), 'route' => 'strategy-config/resolution-check'],
            ['code' => 'SFCA', 'label' => __t('strategy_custom_attributes'), 'route' => 'strategy-config/custom-attributes'],
        ],
    ],
    [
        'code' => 'SFST',
        'title' => 'Structure Setup',
        'patterns' => [
            'strategy-setup/',
        ],
        'links' => [
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFID', 'label' => __t('strategy_import_dimensions'), 'route' => 'strategy-config/import-dashboard'],
            ['code' => 'SFSE', 'label' => __t('strategy_sectors'), 'route' => 'strategy-setup/sectors'],
            ['code' => 'SFPG', 'label' => __t('strategy_programs'), 'route' => 'strategy-setup/programs'],
            ['code' => 'SFSP', 'label' => __t('strategy_subprograms'), 'route' => 'strategy-setup/sub-programs'],
            ['code' => 'SFPJ', 'label' => 'Projects', 'route' => 'strategy-setup/projects'],
            ['code' => 'SFFT', 'label' => __t('strategy_funding_types'), 'route' => 'strategy-setup/funding-types'],
            ['code' => 'SFFS', 'label' => __t('strategy_funding_sources'), 'route' => 'strategy-setup/funding-sources'],
            ['code' => 'SFEI', 'label' => __t('strategy_economic_items'), 'route' => 'strategy-setup/economic-items'],
        ],
    ],
    [
        'code' => 'SFPF',
        'title' => 'Planning Framework',
        'patterns' => [
            'strategy-performance/',
        ],
        'links' => [
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFPI', 'label' => __t('strategy_strategic_pillars'), 'route' => 'strategy-performance/strategic-pillars'],
            ['code' => 'SFGO', 'label' => __t('strategy_goals'), 'route' => 'strategy-performance/goals'],
            ['code' => 'SFOB', 'label' => __t('strategy_objectives'), 'route' => 'strategy-performance/objectives'],
            ['code' => 'SFIN', 'label' => __t('strategy_indicators'), 'route' => 'strategy-performance/indicators'],
            ['code' => 'SFTA', 'label' => __t('strategy_targets'), 'route' => 'strategy-performance/targets'],
        ],
    ],
    [
        'code' => 'SFDC',
        'title' => 'Delivery & Costing',
        'patterns' => [
            'strategy-delivery/',
        ],
        'links' => [
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFOT', 'label' => __t('strategy_outputs'), 'route' => 'strategy-delivery/outputs'],
            ['code' => 'SFAC', 'label' => __t('strategy_activities'), 'route' => 'strategy-delivery/activities'],
            ['code' => 'SFAB', 'label' => __t('strategy_budgets'), 'route' => 'strategy-delivery/budgets'],
        ],
    ],
    [
        'code' => 'SFGV',
        'title' => __t('strategy_nav_governance'),
        'patterns' => [
            'strategy-governance/',
        ],
        'links' => [
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFNA', 'label' => __t('strategy_bsp_narratives'), 'route' => 'strategy-governance/narratives'],
            ['code' => 'SFFR', 'label' => __t('strategy_fiscal_risks'), 'route' => 'strategy-governance/fiscal-risks'],
            ['code' => 'SFGR', 'label' => __t('strategy_program_risks'), 'route' => 'strategy-governance/program-risks'],
        ],
    ],
    [
        'code' => 'SFRP',
        'title' => __t('strategy_nav_reports'),
        'patterns' => [
            'strategy-reports/',
        ],
        'links' => [
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFSU', 'label' => __t('strategy_strategic_summary'), 'route' => 'strategy-reports/summary'],
            ['code' => 'SFSB', 'label' => __t('strategy_sector_budget'), 'route' => 'strategy-reports/sector-budget'],
            ['code' => 'SFPB', 'label' => __t('strategy_program_budget'), 'route' => 'strategy-reports/program-budget'],
            ['code' => 'SFJR', 'label' => 'Project Budget Report', 'route' => 'strategy-reports/project-budget'],
            ['code' => 'SFPD', 'label' => __t('strategy_program_structure'), 'route' => 'strategy-reports/program-structure'],
            ['code' => 'SFMT', 'label' => 'MTFF', 'route' => 'strategy-reports/mtff'],
            ['code' => 'SFPF', 'label' => __t('strategy_performance'), 'route' => 'strategy-reports/performance'],
        ],
    ],
    [
        'code' => 'SFFF',
        'title' => __t('strategy_nav_fiscal_framework'),
        'patterns' => [
            'strategy-fiscal/',
        ],
        'links' => [
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFFO', 'label' => __t('strategy_fiscal_overview'), 'route' => 'strategy-fiscal/overview'],
            ['code' => 'SFRE', 'label' => __t('strategy_resource_envelope_summary'), 'route' => 'strategy-fiscal/resource-envelope'],
            ['code' => 'SFRL', 'label' => __t('strategy_resource_envelope'), 'route' => 'strategy-fiscal/resource-envelope-lines'],
            ['code' => 'SFSC', 'label' => __t('strategy_sector_ceilings'), 'route' => 'strategy-fiscal/sector-ceilings'],
            ['code' => 'SFCP', 'label' => __t('strategy_ceiling_vs_plan'), 'route' => 'strategy-fiscal/ceiling-vs-plan'],
        ],
    ],
    [
        'code' => 'BESR',
        'title' => 'Budget Execution',
        'patterns' => [
            'execution/',
        ],
        'links' => [
            ['code' => 'BESR', 'label' => 'Setup & Rollover', 'route' => 'execution/index'],
            ['code' => 'BEOB', 'label' => 'Opening Balances', 'route' => 'execution/opening-balances'],
            ['code' => 'BEWR', 'label' => 'Warrants', 'route' => 'execution/warrants'],
            ['code' => 'BESB', 'label' => 'Supplementary Budgets', 'route' => 'execution/supplementaries'],
            ['code' => 'BEBR', 'label' => 'Budget Reductions', 'route' => 'execution/budget-reductions'],
            ['code' => 'BERS', 'label' => 'Reservations', 'route' => 'execution/reservations'],
            ['code' => 'BERI', 'label' => 'RIE', 'route' => 'execution/rie'],
            ['code' => 'BECM', 'label' => 'Commitments', 'route' => 'execution/commitments'],
            ['code' => 'FCRD', 'label' => 'Financial Config Readiness', 'route' => 'financial-config/readiness'],
        ],
    ],
    [
        'code' => 'FCRD',
        'title' => 'Financial Configuration',
        'patterns' => [
            'financial-config/',
            'transaction-type-segment-config/',
            'ceilings/',
            'scenario-admin/',
            'transaction-calc-diagnostics/',
            'full-recalculation/',
        ],
        'links' => [
            ['code' => 'FCRD', 'label' => 'Configuration Readiness', 'route' => 'financial-config/readiness'],
            ['code' => 'FTSC', 'label' => 'Segment Rules', 'route' => 'transaction-type-segment-config/list'],
            ['code' => 'FCBL', 'label' => 'Ceiling Balances', 'route' => 'ceilings/balances'],
            ['code' => 'FCKY', 'label' => 'Ceiling Keys', 'route' => 'ceilings/balances-keys'],
            ['code' => 'FCSN', 'label' => 'Scenario Config', 'route' => 'scenario-admin/index'],
            ['code' => 'FCCD', 'label' => 'Calc Diagnostics', 'route' => 'transaction-calc-diagnostics/index'],
            ['code' => 'FCRL', 'label' => 'Full Recalculation', 'route' => 'full-recalculation/index'],
        ],
    ],
    [
        'code' => 'IADM',
        'title' => 'Integration Admin',
        'patterns' => [
            'integration-admin/',
        ],
        'links' => [
            ['code' => 'INSY', 'label' => 'Systems', 'route' => 'integration-admin/systems'],
            ['code' => 'INIF', 'label' => 'Interfaces', 'route' => 'integration-admin/interfaces'],
            ['code' => 'INRH', 'label' => 'Run History', 'route' => 'integration-admin/runs'],
        ],
    ],
    [
        'code' => 'RPTS',
        'title' => 'Reports',
        'patterns' => [
            'reports/',
            'report-admin/',
        ],
        'links' => [
            ['code' => 'RCAT', 'label' => 'Catalogue', 'route' => 'reports/catalogue'],
            ['code' => 'RHIS', 'label' => 'Run History', 'route' => 'reports/history'],
            ['code' => 'RDEF', 'label' => 'Definitions', 'route' => 'report-admin/definitions', 'requiresAdmin' => true],
        ],
    ],
    [
        'code' => 'SCRT',
        'title' => 'Screen Tests',
        'patterns' => [
            'screen-tests/',
        ],
        'links' => [
            ['code' => 'SCTS', 'label' => __t('screen_tests_title'), 'route' => 'screen-tests/scenarios'],
            ['code' => 'SCSR', 'label' => __t('screen_tests_results_title'), 'route' => 'screen-tests/summary'],
        ],
    ],
    [
        'code' => 'BAWF',
        'title' => 'Workflow Configuration',
        'patterns' => [
            'workflow-engine/',
            'workflow-task-types/',
            'workflow-task-statuses/',
            'workflow-assignments/',
        ],
        'links' => [
            ['code' => 'BACR', 'label' => 'Base Config Readiness', 'route' => 'base-config/readiness'],
            ['code' => 'BAWE', 'label' => 'Workflow Engine', 'route' => 'workflow-engine/list'],
            ['code' => 'BAWT', 'label' => 'Workflow Task Types', 'route' => 'workflow-task-types/list'],
            ['code' => 'BAWS', 'label' => 'Workflow Task Statuses', 'route' => 'workflow-task-statuses/list'],
            ['code' => 'BAWI', 'label' => 'Workflow Inquiry', 'route' => 'workflow-engine/inquiry'],
            ['code' => 'BAWD', 'label' => 'Workflow Diagnostics', 'route' => 'workflow-engine/diagnostics'],
            ['code' => 'BAWA', 'label' => 'Workflow Assignments', 'route' => 'workflow-assignments/list'],
        ],
    ],
    [
        'code' => 'BACF',
        'title' => 'Base Configuration',
        'patterns' => [
            'base-config/',
            'fiscal-years/',
            'versions/',
            'version-types/',
            'currencies/',
            'currency-rates/',
            'dataobject-types/',
            'segments/',
            'segment-values/',
            'dataobjectcodes/',
            'system-settings/',
        ],
        'links' => [
            ['code' => 'BACR', 'label' => 'Base Configuration Readiness', 'route' => 'base-config/readiness'],
            ['code' => 'BAFY', 'label' => 'Fiscal Years', 'route' => 'fiscal-years/list'],
            ['code' => 'BAVR', 'label' => 'Versions', 'route' => 'versions/list'],
            ['code' => 'BAVT', 'label' => 'Version Types', 'route' => 'version-types/list'],
            ['code' => 'BACU', 'label' => 'Currencies', 'route' => 'currencies/list'],
            ['code' => 'BACX', 'label' => 'Currency Rates', 'route' => 'currency-rates/list'],
            ['code' => 'BADT', 'label' => 'Data Object Types', 'route' => 'dataobject-types/list'],
            ['code' => 'BASE', 'label' => 'Segments', 'route' => 'segments/list'],
            ['code' => 'BASV', 'label' => 'Segment Values', 'route' => 'segment-values/list'],
            ['code' => 'BADO', 'label' => 'Data Object Codes', 'route' => 'dataobjectcodes/index'],
            ['code' => 'BASS', 'label' => 'System Settings', 'route' => 'system-settings/list'],
        ],
    ],
    [
        'code' => 'CFTG',
        'title' => 'Training Configuration',
        'patterns' => [
            'training-admin/',
        ],
        'links' => [
            ['code' => 'CFTC', 'label' => 'Training Catalogue', 'route' => 'training-admin/scenarios'],
            ['code' => 'ADTC', 'label' => 'Training Scenarios', 'route' => 'training/scenarios', 'requiresTraining' => true],
            ['code' => 'ADTS', 'label' => 'Training Summary', 'route' => 'training/summary', 'requiresTraining' => true],
            ['code' => 'ADTR', 'label' => 'Create New User Training', 'route' => 'training/users', 'requiresTraining' => true],
            ['code' => 'ADTE', 'label' => 'Edit Existing User Training', 'route' => 'training/users-edit', 'requiresTraining' => true],
        ],
    ],
    [
        'code' => 'ADAS',
        'title' => 'Access & Security',
        'patterns' => [
            'users/',
            'roles/',
            'access-matrix/',
            'sessions/',
            'training/',
        ],
        'links' => [
            ['code' => 'ADUS', 'label' => 'Users', 'route' => 'users/list'],
            ['code' => 'ADRO', 'label' => 'Roles & Permissions', 'route' => 'roles/list'],
            ['code' => 'ADAX', 'label' => 'Access Matrix', 'route' => 'access-matrix/index'],
            ['code' => 'ADSS', 'label' => 'Active Sessions', 'route' => 'sessions/index'],
            ['code' => 'ADTC', 'label' => 'Training Scenarios', 'route' => 'training/scenarios', 'requiresTraining' => true],
            ['code' => 'ADTS', 'label' => 'Training Summary', 'route' => 'training/summary', 'requiresTraining' => true],
            ['code' => 'ADTR', 'label' => 'Create New User Training', 'route' => 'training/users', 'requiresTraining' => true],
            ['code' => 'ADTE', 'label' => 'Edit Existing User Training', 'route' => 'training/users-edit', 'requiresTraining' => true],
        ],
    ],
];
