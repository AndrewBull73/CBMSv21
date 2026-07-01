<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Quick Links Registry
|--------------------------------------------------------------------------
|
| Quick Links are local navigation helpers. Keep them aligned with the main
| menu families and avoid using them for page actions like Save, Run, Back,
| Download, or Reload.
|
*/

return [
    [
        'code' => 'RD',
        'title' => 'Readiness & Diagnostics',
        'patterns' => [
            'base-config/',
            'financial-config/',
            'strategy-config/configuration-readiness',
            'strategy-config/resolution-check',
            'strategy-reports/submission-readiness',
            'strategy-reports/program-structure',
            'transaction-calc-diagnostics/',
            'full-recalculation/',
            'diagnostics/',
            'health/',
            'application-log/',
            'emailqueue/',
        ],
        'links' => [
            ['code' => 'BACR', 'label' => 'Base Readiness', 'route' => 'base-config/readiness'],
            ['code' => 'SFCR', 'label' => 'Strategy Readiness', 'route' => 'strategy-config/configuration-readiness'],
            ['code' => 'SFSD', 'label' => 'Submission Readiness', 'route' => 'strategy-reports/submission-readiness'],
            ['code' => 'FCCR', 'label' => 'Financial Readiness', 'route' => 'financial-config/readiness'],
            ['code' => 'RPSD', 'label' => 'Program Diagnostics', 'route' => 'strategy-reports/program-structure'],
            ['code' => 'SFRC', 'label' => 'Source Resolution', 'route' => 'strategy-config/resolution-check'],
            ['code' => 'FCTD', 'label' => 'Calc Debug', 'route' => 'transaction-calc-diagnostics/index'],
            ['code' => 'ADLG', 'label' => 'Application Log', 'route' => 'application-log/index'],
            ['code' => 'ADEQ', 'label' => 'Email Queue', 'route' => 'emailqueue/index'],
            ['code' => 'ADHE', 'label' => 'Health', 'route' => 'health/index', 'requiresAdmin' => true],
        ],
    ],
    [
        'code' => 'OC',
        'title' => 'Organisation & Chart of Accounts',
        'patterns' => [
            'dataobject-types/',
            'dataobjectcodes/',
            'dataobjectworkflow/',
            'segments/',
            'segment-values/',
            'strategy-reports/segment-parent-child',
        ],
        'links' => [
            ['code' => 'ADOT', 'label' => 'Data Object Types', 'route' => 'dataobject-types/list'],
            ['code' => 'ADOC', 'label' => 'Data Object Codes', 'route' => 'dataobjectcodes/index'],
            ['code' => 'ADOH', 'label' => 'Hierarchy', 'route' => 'dataobjectcodes/hierarchy'],
            ['code' => 'ADOW', 'label' => 'Workflow Status', 'route' => 'dataobjectworkflow/statuses'],
            ['code' => 'BASE', 'label' => 'Segments', 'route' => 'segments/list'],
            ['code' => 'BASV', 'label' => 'Segment Values', 'route' => 'segment-values/list'],
            ['code' => 'RSPC', 'label' => 'Parent-Child Check', 'route' => 'strategy-reports/segment-parent-child'],
            ['code' => 'ADAM', 'label' => 'Access', 'route' => 'dataobjectcodes/access'],
        ],
    ],
    [
        'code' => 'SFSR',
        'title' => 'Budget Strategy: Start',
        'patterns' => [
            'strategy/index',
            'strategy/framework-guide',
        ],
        'links' => [
            ['code' => 'SFOH', 'label' => __t('menu_strategy_overview'), 'route' => 'strategy/index'],
            ['code' => 'SFGD', 'label' => 'Framework Guide', 'route' => 'strategy/framework-guide'],
            ['code' => 'SFSU', 'label' => __t('strategy_strategic_summary'), 'route' => 'strategy-reports/summary'],
            ['code' => 'SFCR', 'label' => __t('strategy_configuration_readiness'), 'route' => 'strategy-config/configuration-readiness'],
        ],
    ],
    [
        'code' => 'SFPF',
        'title' => 'Budget Strategy: Planning Framework',
        'patterns' => [
            'strategy-performance/',
        ],
        'links' => [
            ['code' => 'SFPI', 'label' => __t('strategy_strategic_pillars'), 'route' => 'strategy-performance/strategic-pillars'],
            ['code' => 'SFGO', 'label' => __t('strategy_goals'), 'route' => 'strategy-performance/goals'],
            ['code' => 'SFOB', 'label' => __t('strategy_objectives'), 'route' => 'strategy-performance/objectives'],
            ['code' => 'SFIN', 'label' => __t('strategy_indicators'), 'route' => 'strategy-performance/indicators'],
            ['code' => 'SFTA', 'label' => __t('strategy_targets'), 'route' => 'strategy-performance/targets'],
        ],
    ],
    [
        'code' => 'SFST',
        'title' => 'Budget Strategy: Program Structure',
        'patterns' => [
            'strategy-setup/',
        ],
        'links' => [
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
        'code' => 'SFFF',
        'title' => 'Budget Strategy: Fiscal Framework',
        'patterns' => [
            'strategy-fiscal/',
        ],
        'links' => [
            ['code' => 'SFFO', 'label' => __t('strategy_fiscal_overview'), 'route' => 'strategy-fiscal/overview'],
            ['code' => 'SFRE', 'label' => __t('strategy_resource_envelope_summary'), 'route' => 'strategy-fiscal/resource-envelope'],
            ['code' => 'SFRL', 'label' => __t('strategy_resource_envelope'), 'route' => 'strategy-fiscal/resource-envelope-lines'],
            ['code' => 'SFSC', 'label' => __t('strategy_sector_ceilings'), 'route' => 'strategy-fiscal/sector-ceilings'],
            ['code' => 'SFCP', 'label' => __t('strategy_ceiling_vs_plan'), 'route' => 'strategy-fiscal/ceiling-vs-plan'],
        ],
    ],
    [
        'code' => 'SFDC',
        'title' => 'Budget Strategy: Delivery & Costing',
        'patterns' => [
            'strategy-delivery/',
        ],
        'links' => [
            ['code' => 'SFOT', 'label' => __t('strategy_outputs'), 'route' => 'strategy-delivery/outputs'],
            ['code' => 'SFAC', 'label' => __t('strategy_activities'), 'route' => 'strategy-delivery/activities'],
            ['code' => 'SFAB', 'label' => __t('strategy_budgets'), 'route' => 'strategy-delivery/budgets'],
        ],
    ],
    [
        'code' => 'SFGV',
        'title' => 'Budget Strategy: Governance',
        'patterns' => [
            'strategy-governance/',
        ],
        'links' => [
            ['code' => 'SFNA', 'label' => __t('strategy_bsp_narratives'), 'route' => 'strategy-governance/narratives'],
            ['code' => 'SFFR', 'label' => __t('strategy_fiscal_risks'), 'route' => 'strategy-governance/fiscal-risks'],
            ['code' => 'SFGR', 'label' => __t('strategy_program_risks'), 'route' => 'strategy-governance/program-risks'],
        ],
    ],
    [
        'code' => 'SFFW',
        'title' => 'Budget Strategy: Funding Requests',
        'patterns' => [
            'strategy-submissions/',
        ],
        'links' => [
            ['code' => 'SFLD', 'label' => 'Lodgements', 'route' => 'strategy-submissions/lodgements'],
            ['code' => 'SFRV', 'label' => 'Reviews', 'route' => 'strategy-submissions/reviews'],
            ['code' => 'SFAP', 'label' => 'Approvals', 'route' => 'strategy-submissions/approvals'],
            ['code' => 'SFSR', 'label' => 'Submission Summary', 'route' => 'strategy-submissions/report'],
            ['code' => 'SFFW', 'label' => 'All Funding Items', 'route' => 'strategy-submissions/list'],
        ],
    ],
    [
        'code' => 'RA',
        'title' => 'Reports & Analysis',
        'patterns' => [
            'reports/',
            'strategy-reports/summary',
            'strategy-reports/sector-budget',
            'strategy-reports/program-budget',
            'strategy-reports/project-budget',
            'strategy-reports/mtff',
            'strategy-reports/performance',
            'dashboard/',
            'analytics',
            'analytics/',
            'scenario-results/',
        ],
        'links' => [
            ['code' => 'RCAT', 'label' => 'Report Catalogue', 'route' => 'reports/catalogue', 'perms' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'RPSU', 'label' => 'Strategic Summary', 'route' => 'strategy-reports/summary', 'perms' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'RPSB', 'label' => 'Sector Budget', 'route' => 'strategy-reports/sector-budget', 'perms' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'RPPB', 'label' => 'Program Budget', 'route' => 'strategy-reports/program-budget', 'perms' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'RPMT', 'label' => 'MTFF', 'route' => 'strategy-reports/mtff', 'perms' => ['STRATEGY_REPORT_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'DBOV', 'label' => 'Dashboard', 'route' => 'dashboard/index', 'perms' => ['DASHBOARD_VIEW', 'DASHBOARD_ADMIN', 'ADMIN_ALL']],
            ['code' => 'ANOV', 'label' => 'Analytics', 'route' => 'analytics', 'perms' => ['ANALYTICS_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        ],
    ],
    [
        'code' => 'BP',
        'title' => 'Budget Planning',
        'patterns' => [
            'transaction-input/',
            'ceilings/',
        ],
        'links' => [
            ['code' => 'BSTL', 'label' => __t('menu_transaction_input_list'), 'route' => 'transaction-input/list'],
            ['code' => 'BSTE', 'label' => __t('menu_transaction_input_editor'), 'route' => 'transaction-input/editor'],
            ['code' => 'BSCB', 'label' => 'Ceiling Balances', 'route' => 'ceilings/balances'],
            ['code' => 'BSCK', 'label' => 'Ceiling Keys', 'route' => 'ceilings/balances-keys'],
            ['code' => 'BSST', 'label' => 'Stub Runner', 'route' => 'transaction-input/stub', 'requiresAdmin' => true],
            ['code' => 'BSBR', 'label' => 'Batch Runner', 'route' => 'transaction-input/batch-runner', 'requiresAdmin' => true],
        ],
    ],
    [
        'code' => 'BE',
        'title' => 'Budget Execution',
        'patterns' => [
            'execution/',
        ],
        'links' => [
            ['code' => 'BESR', 'label' => 'Setup & Rollover', 'route' => 'execution/index'],
            ['code' => 'BEOB', 'label' => 'Opening Balances', 'route' => 'execution/opening-balances'],
            ['code' => 'BEWR', 'label' => 'Warrants', 'route' => 'execution/warrants'],
            ['code' => 'BESB', 'label' => 'Supplementaries', 'route' => 'execution/supplementaries'],
            ['code' => 'BEBR', 'label' => 'Reductions', 'route' => 'execution/budget-reductions'],
            ['code' => 'BERS', 'label' => 'Reservations', 'route' => 'execution/reservations'],
            ['code' => 'BERI', 'label' => 'RIE', 'route' => 'execution/rie'],
            ['code' => 'BECM', 'label' => 'Commitments', 'route' => 'execution/commitments'],
        ],
    ],
    [
        'code' => 'CF',
        'title' => 'System Configuration',
        'patterns' => [
            'fiscal-years/',
            'versions/',
            'version-types/',
            'currencies/',
            'currency-rates/',
            'system-settings/',
            'workflow-engine/',
            'workflow-task-types/',
            'workflow-task-statuses/',
            'workflow-assignments/',
            'transaction-type-segment-config/',
            'scenario-admin/',
            'report-admin/',
            'integration-admin/',
            'strategy-config/',
            'strategy-publish/',
        ],
        'links' => [
            ['code' => 'BAFY', 'label' => 'Fiscal Years', 'route' => 'fiscal-years/list'],
            ['code' => 'BAVR', 'label' => 'Versions', 'route' => 'versions/list'],
            ['code' => 'BACU', 'label' => 'Currencies', 'route' => 'currencies/list'],
            ['code' => 'BASS', 'label' => 'System Settings', 'route' => 'system-settings/list'],
            ['code' => 'BAWE', 'label' => 'Workflow Engine', 'route' => 'workflow-engine/list'],
            ['code' => 'FCTS', 'label' => 'Segment Rules', 'route' => 'transaction-type-segment-config/list'],
            ['code' => 'SFSM', 'label' => 'Strategy Mapping', 'route' => 'strategy-config/segment-mapping'],
            ['code' => 'CFIS', 'label' => 'Integrations', 'route' => 'integration-admin/systems', 'requiresAdmin' => true],
        ],
    ],
    [
        'code' => 'WO',
        'title' => 'Workflow Operations',
        'patterns' => [
            'workflow/',
            'workflow-projects/',
            'workflow-requirements/',
            'workflow-issues/',
            'workflow-user-groups/',
        ],
        'links' => [
            ['code' => 'ADWF', 'label' => 'Workflow Tasks', 'route' => 'workflow/list', 'perms' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'ADWP', 'label' => 'Workflow Projects', 'route' => 'workflow-projects/list', 'perms' => ['WORKFLOW_PROJECTS_VIEW', 'WORKFLOW_PROJECTS_CREATE', 'WORKFLOW_PROJECTS_EDIT', 'WORKFLOW_PROJECTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'ADWR', 'label' => 'Requirements', 'route' => 'workflow-requirements/list', 'perms' => ['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'ADRS', 'label' => 'Requirements Summary', 'route' => 'workflow-requirements/summary', 'perms' => ['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'ADRM', 'label' => 'Requirements Matrix', 'route' => 'workflow-requirements/matrix', 'perms' => ['WORKFLOW_REQUIREMENTS_VIEW', 'WORKFLOW_REQUIREMENTS_CREATE', 'WORKFLOW_REQUIREMENTS_EDIT', 'WORKFLOW_REQUIREMENTS_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'ADWI', 'label' => 'Issues Log', 'route' => 'workflow-issues/list', 'perms' => ['WORKFLOW_ISSUES_VIEW', 'WORKFLOW_ISSUES_CREATE', 'WORKFLOW_ISSUES_EDIT', 'WORKFLOW_ISSUES_DELETE', 'WORKFLOW_OPERATIONS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'ADWG', 'label' => 'Workflow Groups', 'route' => 'workflow-user-groups/list', 'perms' => ['WORKFLOW_OPERATIONS_VIEW', 'WORKFLOW_OPERATIONS_EDIT', 'WORKFLOW_OPERATIONS_ADMIN', 'USERS_VIEW', 'USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        ],
    ],
    [
        'code' => 'AD',
        'title' => 'Administration',
        'patterns' => [
            'users/',
            'roles/',
            'access-matrix/',
            'sessions/',
            'session/',
            'audit/',
            'metrics/',
            'systemmessages/',
            'email-templates/',
        ],
        'links' => [
            ['code' => 'ADUS', 'label' => 'Users', 'route' => 'users/list'],
            ['code' => 'ADRO', 'label' => 'Roles', 'route' => 'roles/list'],
            ['code' => 'ADAX', 'label' => 'Access Matrix', 'route' => 'access-matrix/index'],
            ['code' => 'ADSE', 'label' => 'Sessions', 'route' => 'sessions/index'],
            ['code' => 'ADAU', 'label' => 'Audit', 'route' => 'audit/list', 'requiresAdmin' => true],
            ['code' => 'ADSM', 'label' => 'System Messages', 'route' => 'systemmessages/list', 'requiresAdmin' => true],
            ['code' => 'ADET', 'label' => 'Email Templates', 'route' => 'email-templates/list', 'perms' => ['USERS_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
        ],
    ],
    [
        'code' => 'TR',
        'title' => 'Training',
        'patterns' => [
            'training/',
            'training-admin/',
            'training-certifications/',
        ],
        'links' => [
            ['code' => 'TRDB', 'label' => 'Training Dashboard', 'route' => 'training/dashboard', 'requiresTraining' => true, 'perms' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TRSC', 'label' => 'Training Scenarios', 'route' => 'training/scenarios', 'requiresTraining' => true, 'perms' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TRCF', 'label' => 'Certifications', 'route' => 'training-certifications/modules', 'requiresTraining' => true, 'perms' => ['TRAINING_USER', 'TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TROP', 'label' => 'Training Operations', 'route' => 'training-admin/operations', 'requiresTraining' => true, 'perms' => ['TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TRSM', 'label' => 'Training Summary', 'route' => 'training/summary', 'requiresTraining' => true, 'perms' => ['TRAINING_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TRCR', 'label' => 'Certification Results', 'route' => 'training-certifications/results', 'requiresTraining' => true, 'perms' => ['TRAINING_ADMIN', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TRCG', 'label' => 'Training Catalogue', 'route' => 'training-admin/scenarios', 'requiresTraining' => true, 'perms' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TRCC', 'label' => 'Certification Catalogue', 'route' => 'training-certifications/admin', 'requiresTraining' => true, 'perms' => ['TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TRMX', 'label' => 'Training Matrix', 'route' => 'training-admin/matrix', 'requiresTraining' => true, 'perms' => ['TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
            ['code' => 'TRVL', 'label' => 'Training Validation', 'route' => 'training-admin/validation', 'requiresTraining' => true, 'perms' => ['TRAINING_ADMIN', 'TRAINING_CONFIG', 'ADMIN_ALL', 'SYSADMIN']],
        ],
    ],
    [
        'code' => 'TS',
        'title' => 'Screen Testing',
        'patterns' => [
            'screen-tests/',
            'screen-tests-admin/',
        ],
        'links' => [
            ['code' => 'TSSC', 'label' => __t('screen_tests_title'), 'route' => 'screen-tests/scenarios', 'requiresTesting' => true],
            ['code' => 'TSSR', 'label' => __t('screen_tests_results_title'), 'route' => 'screen-tests/summary', 'requiresTesting' => true],
            ['code' => 'CFTS', 'label' => 'Test Catalogue', 'route' => 'screen-tests-admin/scenarios', 'requiresTesting' => true, 'requiresAdmin' => true],
        ],
    ],
];
