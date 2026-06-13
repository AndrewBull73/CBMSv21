<?php
declare(strict_types=1);

/**
 * Config-driven, role/permission-aware menu.
 * Keys: label, route?, icon, roles[], perms[], active[], children[].
 */

require_once __DIR__ . '/../shared/training_features.php';
require_once __DIR__ . '/../shared/testing_features.php';

$testingEnabled = screen_testing_features_enabled($GLOBALS['conn'] ?? null);
$trainingEnabled = training_features_enabled($GLOBALS['conn'] ?? null);

return [

  [
    'label'  => __t('menu_home'),
    'route'  => 'home/index',
    'icon'   => 'house',
    'active' => ['home/*'],
    'roles'  => [],
    'perms'  => ['AUTHENTICATED'],
  ],

  [
    'label' => 'Strategic Framework',
    'code'  => 'SF',
    'icon'  => 'flag',
    'roles' => [],
    'perms' => ['STRATEGY_VIEW','STRATEGY_REPORT_VIEW','STRATEGY_CONFIG_EDIT','STRATEGY_SETUP_EDIT','STRATEGY_PERFORMANCE_EDIT','STRATEGY_DELIVERY_EDIT','STRATEGY_GOVERNANCE_EDIT','STRATEGY_FISCAL_EDIT','STRATEGY_WORKFLOW_EDIT','STRATEGY_SUBMISSION_PREPARE','STRATEGY_SUBMISSION_REVIEW','STRATEGY_SUBMISSION_APPROVE','STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN'],
    'active' => ['strategy/*','strategy-config/*','strategy-setup/*','strategy-performance/*','strategy-delivery/*','strategy-governance/*','strategy-reports/*','strategy-submissions/*','strategy-fiscal/*','strategy-publish/*'],
    'children' => [
      [
        'label' => 'Start & Review',
        'code'  => 'SFSR',
        'icon'  => 'compass',
        'roles' => [],
        'perms' => ['STRATEGY_VIEW','STRATEGY_REPORT_VIEW','STRATEGY_WORKFLOW_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>__t('menu_strategy_overview'),'code'=>'SFOH','route'=>'strategy/index','icon'=>'flag','roles'=>[],'perms'=>['STRATEGY_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Configuration Readiness','code'=>'SFCR','route'=>'strategy-config/configuration-readiness','icon'=>'gear-wide-connected','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Submission Readiness','code'=>'SFSD','route'=>'strategy-reports/submission-readiness','icon'=>'clipboard-check','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Strategic Summary','code'=>'SFSU','route'=>'strategy-reports/summary','icon'=>'clipboard-data','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Configuration',
        'code'  => 'SFCF',
        'icon'  => 'sliders',
        'roles' => [],
        'perms' => ['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Segment Mapping','code'=>'SFSM','route'=>'strategy-config/segment-mapping','icon'=>'sliders','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Import Dimensions','code'=>'SFID','route'=>'strategy-config/import-dashboard','icon'=>'download','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Fiscal Period Labels','code'=>'SFPL','route'=>'strategy-config/fiscal-periods','icon'=>'calendar3','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Fiscal Assumptions','code'=>'SFFA','route'=>'strategy-config/fiscal-assumptions','icon'=>'graph-up-arrow','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Custom Phasing Profiles','code'=>'SFPP','route'=>'strategy-config/phasing-profiles','icon'=>'pie-chart','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Fiscal and Version Rollover','code'=>'SFRO','route'=>'strategy-config/rollover','icon'=>'arrow-repeat','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Segment Publication','code'=>'SFPU','route'=>'strategy-publish/requests','icon'=>'upload','roles'=>[],'perms'=>['STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Source Resolution Check','code'=>'SFRC','route'=>'strategy-config/resolution-check','icon'=>'search','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Custom Attributes','code'=>'SFCA','route'=>'strategy-config/custom-attributes','icon'=>'input-cursor-text','roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Structure Setup',
        'code'  => 'SFST',
        'icon'  => 'diagram-3',
        'roles' => [],
        'perms' => ['STRATEGY_SETUP_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Sectors','code'=>'SFSE','route'=>'strategy-setup/sectors','icon'=>'grid','roles'=>[],'perms'=>['STRATEGY_SETUP_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Programs','code'=>'SFPG','route'=>'strategy-setup/programs','icon'=>'kanban','roles'=>[],'perms'=>['STRATEGY_SETUP_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'SubPrograms','code'=>'SFSP','route'=>'strategy-setup/sub-programs','icon'=>'diagram-2','roles'=>[],'perms'=>['STRATEGY_SETUP_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Projects','code'=>'SFPJ','route'=>'strategy-setup/projects','icon'=>'kanban-fill','roles'=>[],'perms'=>['STRATEGY_SETUP_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Funding Types','code'=>'SFFT','route'=>'strategy-setup/funding-types','icon'=>'collection','roles'=>[],'perms'=>['STRATEGY_SETUP_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Funding Sources','code'=>'SFFS','route'=>'strategy-setup/funding-sources','icon'=>'bank','roles'=>[],'perms'=>['STRATEGY_SETUP_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Economic Items','code'=>'SFEI','route'=>'strategy-setup/economic-items','icon'=>'tags','roles'=>[],'perms'=>['STRATEGY_SETUP_EDIT','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Planning Framework',
        'code'  => 'SFPF',
        'icon'  => 'bullseye',
        'roles' => [],
        'perms' => ['STRATEGY_PERFORMANCE_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Strategic Pillars','code'=>'SFPI','route'=>'strategy-performance/strategic-pillars','icon'=>'stack','roles'=>[],'perms'=>['STRATEGY_PERFORMANCE_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Goals','code'=>'SFGO','route'=>'strategy-performance/goals','icon'=>'signpost-split','roles'=>[],'perms'=>['STRATEGY_PERFORMANCE_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Objectives','code'=>'SFOB','route'=>'strategy-performance/objectives','icon'=>'bullseye','roles'=>[],'perms'=>['STRATEGY_PERFORMANCE_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Indicators','code'=>'SFIN','route'=>'strategy-performance/indicators','icon'=>'speedometer2','roles'=>[],'perms'=>['STRATEGY_PERFORMANCE_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Indicator Targets','code'=>'SFTA','route'=>'strategy-performance/targets','icon'=>'graph-up-arrow','roles'=>[],'perms'=>['STRATEGY_PERFORMANCE_EDIT','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Delivery & Costing',
        'code'  => 'SFDC',
        'icon'  => 'box-seam',
        'roles' => [],
        'perms' => ['STRATEGY_DELIVERY_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Outputs','code'=>'SFOT','route'=>'strategy-delivery/outputs','icon'=>'box-seam','roles'=>[],'perms'=>['STRATEGY_DELIVERY_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Activities','code'=>'SFAC','route'=>'strategy-delivery/activities','icon'=>'list-task','roles'=>[],'perms'=>['STRATEGY_DELIVERY_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Activity Budgets','code'=>'SFAB','route'=>'strategy-delivery/budgets','icon'=>'cash-stack','roles'=>[],'perms'=>['STRATEGY_DELIVERY_EDIT','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Governance',
        'code'  => 'SFGV',
        'icon'  => 'shield-check',
        'roles' => [],
        'perms' => ['STRATEGY_GOVERNANCE_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'BSP Narratives','code'=>'SFNA','route'=>'strategy-governance/narratives','icon'=>'journal-richtext','roles'=>[],'perms'=>['STRATEGY_GOVERNANCE_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Fiscal Risks','code'=>'SFFR','route'=>'strategy-governance/fiscal-risks','icon'=>'shield-exclamation','roles'=>[],'perms'=>['STRATEGY_GOVERNANCE_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Program Risks','code'=>'SFGR','route'=>'strategy-governance/program-risks','icon'=>'link-45deg','roles'=>[],'perms'=>['STRATEGY_GOVERNANCE_EDIT','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Fiscal Framework',
        'code'  => 'SFFF',
        'icon'  => 'bank',
        'roles' => [],
        'perms' => ['STRATEGY_FISCAL_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>__t('menu_fiscal_overview'),'code'=>'SFFO','route'=>'strategy-fiscal/overview','icon'=>'bank','roles'=>[],'perms'=>['STRATEGY_FISCAL_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Resource Envelope Summary','code'=>'SFRE','route'=>'strategy-fiscal/resource-envelope','icon'=>'calendar3','roles'=>[],'perms'=>['STRATEGY_FISCAL_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>__t('menu_fiscal_envelope'),'code'=>'SFRL','route'=>'strategy-fiscal/resource-envelope-lines','icon'=>'list-ul','roles'=>[],'perms'=>['STRATEGY_FISCAL_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>__t('menu_fiscal_ceilings'),'code'=>'SFSC','route'=>'strategy-fiscal/sector-ceilings','icon'=>'grid-3x3-gap','roles'=>[],'perms'=>['STRATEGY_FISCAL_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Ceiling vs Strategic Plan','code'=>'SFCP','route'=>'strategy-fiscal/ceiling-vs-plan','icon'=>'arrows-collapse','roles'=>[],'perms'=>['STRATEGY_FISCAL_EDIT','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Funding Requests',
        'code'  => 'SFFW',
        'icon'  => 'cash-coin',
        'roles' => [],
        'perms' => ['STRATEGY_SUBMISSION_PREPARE','STRATEGY_SUBMISSION_REVIEW','STRATEGY_SUBMISSION_APPROVE','STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Funding Lodgements','code'=>'BSLG','route'=>'strategy-submissions/lodgements','icon'=>'cash-coin','roles'=>[],'perms'=>['STRATEGY_SUBMISSION_PREPARE','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Funding Reviews','code'=>'BSRV','route'=>'strategy-submissions/reviews','icon'=>'clipboard-check','roles'=>[],'perms'=>['STRATEGY_SUBMISSION_REVIEW','STRATEGY_SUBMISSION_APPROVE','STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Funding Approvals','code'=>'BSAP','route'=>'strategy-submissions/approvals','icon'=>'stamp','roles'=>[],'perms'=>['STRATEGY_SUBMISSION_APPROVE','STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN']],
          ['label'=>'All Funding Items','code'=>'BSFI','route'=>'strategy-submissions/list','icon'=>'list-ul','roles'=>[],'perms'=>['STRATEGY_SUBMISSION_PREPARE','STRATEGY_SUBMISSION_REVIEW','STRATEGY_SUBMISSION_APPROVE','STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Funding Submission Summary','code'=>'BSFS','route'=>'strategy-submissions/report','icon'=>'bar-chart','roles'=>[],'perms'=>['STRATEGY_SUBMISSION_PREPARE','STRATEGY_SUBMISSION_REVIEW','STRATEGY_SUBMISSION_APPROVE','STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN']],
        ],
      ],
    ],
  ],

  [
    'label' => 'Budget Submission',
    'code'  => 'BS',
    'icon'  => 'journal-text',
    'roles' => [],
    'perms' => ['ESTIMATES_VIEW','ESTIMATES_EDIT','RATES_VIEW','RATES_EDIT','RATES_CREATE','STRATEGY_SUBMISSION_PREPARE','STRATEGY_SUBMISSION_REVIEW','STRATEGY_SUBMISSION_APPROVE','STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN'],
    'active' => ['transaction-input/*','rates/*','ceilings/*','budgets/*','strategy-submissions/*'],
    'children' => [
      [
        'label' => 'Budget Input',
        'code'  => 'BSBI',
        'icon'  => 'journal-text',
        'roles' => [],
        'perms' => ['ESTIMATES_VIEW','ESTIMATES_EDIT','RATES_VIEW','RATES_EDIT','RATES_CREATE','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>__t('menu_transaction_input_list'),'code'=>'BSTL','route'=>'transaction-input/list','icon'=>'journal-text','active'=>['transaction-input/list'],'roles'=>[],'perms'=>['ESTIMATES_VIEW','ESTIMATES_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>__t('menu_transaction_input_editor'),'code'=>'BSTE','route'=>'transaction-input/editor','icon'=>'pencil-square','active'=>['transaction-input/editor'],'roles'=>[],'perms'=>['ESTIMATES_VIEW','ESTIMATES_EDIT','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Processing Tools',
        'code'  => 'BSPR',
        'icon'  => 'cpu',
        'roles' => [],
        'perms' => ['ESTIMATES_EDIT','CALC_ADMIN','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Transaction Stub Runner','code'=>'BSST','route'=>'transaction-input/stub','icon'=>'play-circle','active'=>['transaction-input/stub'],'roles'=>[],'perms'=>['ESTIMATES_EDIT','CALC_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Batch Runner','code'=>'BSBR','route'=>'transaction-input/batch-runner','icon'=>'collection-play','active'=>['transaction-input/batch-runner'],'roles'=>[],'perms'=>['ESTIMATES_EDIT','CALC_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Single Save Load Test','code'=>'BSSL','route'=>'transaction-input/single-save-load-test','icon'=>'speedometer2','active'=>['transaction-input/single-save-load-test'],'roles'=>[],'perms'=>['ESTIMATES_EDIT','CALC_ADMIN','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Ceiling Management',
        'code'  => 'BSCM',
        'icon'  => 'safe2',
        'roles' => [],
        'perms' => ['ESTIMATES_VIEW','ESTIMATES_EDIT','FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Ceiling Balances','code'=>'BSCB','route'=>'ceilings/balances','icon'=>'safe2','active'=>['ceilings/*'],'roles'=>[],'perms'=>['ESTIMATES_VIEW','ESTIMATES_EDIT','FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Ceiling Balance Keys','code'=>'BSCK','route'=>'ceilings/balances-keys','icon'=>'key','active'=>['ceilings/*'],'roles'=>[],'perms'=>['ESTIMATES_VIEW','ESTIMATES_EDIT','FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
        ],
      ],
    ],
  ],

  [
    'label' => 'Budget Execution',
    'code'  => 'BE',
    'icon'  => 'play-circle',
    'roles' => ['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],
    'perms' => [],
    'children' => [
      ['label'=>'Setup & Rollover','code'=>'BESR','route'=>'execution/index','icon'=>'gear-wide-connected','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>'Opening Balances','code'=>'BEOB','route'=>'execution/opening-balances','icon'=>'table','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>__t('menu_execution_warrants'),'code'=>'BEWR','route'=>'execution/warrants','icon'=>'check2-square','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>'Supplementary Budgets','code'=>'BESB','route'=>'execution/supplementaries','icon'=>'plus-square','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>'Budget Reductions','code'=>'BEBR','route'=>'execution/budget-reductions','icon'=>'dash-square','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>'Reservations','code'=>'BERS','route'=>'execution/reservations','icon'=>'bookmark-check','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>'RIE','code'=>'BERI','route'=>'execution/rie','icon'=>'file-earmark-check','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>'Commitments','code'=>'BECM','route'=>'execution/commitments','icon'=>'journal-check','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>__t('menu_execution_realloc'),'code'=>'BERE','route'=>'execution/index','icon'=>'check2-square','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
      ['label'=>__t('menu_execution_virements'),'code'=>'BEVI','route'=>'execution/index','icon'=>'check2-square','active'=>['execution/*'],'roles'=>['Budget Execution User','Budget Execution Reviewer','Budget Execution Administrator','System Administrator'],'perms'=>[]],
    ],
  ],

  [
    'label' => 'Reporting',
    'code'  => 'RP',
    'icon'  => 'file-earmark-text',
    'roles' => [],
    'perms' => ['AUTHENTICATED','STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN'],
    'active' => ['strategy-reports/*','reports/*'],
    'children' => [
      [
        'label' => 'Formal Reports',
        'code'  => 'RPFR',
        'icon'  => 'printer',
        'roles' => [],
        'perms' => ['AUTHENTICATED','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Approved Budget Report','code'=>'RPAB','route'=>'reports/execute','href'=>'index.php?route=reports/execute&code=RPT_APPROVED_BUDGET','icon'=>'play-circle','active'=>['reports/execute','reports/run'],'roles'=>[],'perms'=>['AUTHENTICATED','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Report Catalogue','code'=>'RPCA','route'=>'reports/catalogue','icon'=>'file-earmark-text','active'=>['reports/catalogue','reports/run'],'roles'=>[],'perms'=>['AUTHENTICATED','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Report Run History','code'=>'RPRH','route'=>'reports/history','icon'=>'clock-history','active'=>['reports/history','reports/run-detail'],'roles'=>[],'perms'=>['AUTHENTICATED','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Readiness & Summary',
        'code'  => 'RPRS',
        'icon'  => 'clipboard-data',
        'roles' => [],
        'perms' => ['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Strategic Summary','code'=>'RPSU','route'=>'strategy-reports/summary','icon'=>'clipboard-data','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Submission Readiness','code'=>'RPSR','route'=>'strategy-reports/submission-readiness','icon'=>'clipboard-check','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Budget Reports',
        'code'  => 'RPBR',
        'icon'  => 'bar-chart-line',
        'roles' => [],
        'perms' => ['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Sector Budget Report','code'=>'RPSB','route'=>'strategy-reports/sector-budget','icon'=>'grid-3x3-gap','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Program Budget Report','code'=>'RPPB','route'=>'strategy-reports/program-budget','icon'=>'kanban','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Project Budget Report','code'=>'RPJB','route'=>'strategy-reports/project-budget','icon'=>'kanban-fill','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'MTFF View','code'=>'RPMT','route'=>'strategy-reports/mtff','icon'=>'bar-chart-line','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Performance & Diagnostics',
        'code'  => 'RPPD',
        'icon'  => 'graph-up-arrow',
        'roles' => [],
        'perms' => ['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Performance Report','code'=>'RPPF','route'=>'strategy-reports/performance','icon'=>'graph-up-arrow','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Program Structure Diagnostics','code'=>'RPSD','route'=>'strategy-reports/program-structure','icon'=>'diagram-3','roles'=>[],'perms'=>['STRATEGY_REPORT_VIEW','ADMIN_ALL','SYSADMIN']],
        ],
      ],
    ],
  ],

  [
    'label' => 'Testing',
    'code'  => 'TS',
    'icon'  => 'clipboard-check',
    'roles' => [],
    'perms' => ['AUTHENTICATED'],
    'active' => ['screen-tests/*'],
    'enabled' => $testingEnabled,
    'children' => [
      ['label' => 'Test Scripts', 'code' => 'TSSC', 'route' => 'screen-tests/scenarios', 'icon' => 'list-check', 'active' => ['screen-tests/scenarios', 'screen-tests/runner'], 'roles' => [], 'perms' => ['AUTHENTICATED'], 'enabled' => $testingEnabled],
      ['label' => 'Test Results', 'code' => 'TSSR', 'route' => 'screen-tests/summary', 'icon' => 'table', 'active' => ['screen-tests/summary'], 'roles' => [], 'perms' => ['AUTHENTICATED'], 'enabled' => $testingEnabled],
    ],
  ],

  [
    'label'  => __t('menu_analytics'),
    'code'   => 'AN',
    'route'  => 'analytics',
    'icon'   => 'bar-chart',
    'active' => ['analytics*', 'scenario-results*'],
    'roles'  => [],
    'perms'  => ['ANALYTICS_VIEW'],
    'children' => [
      ['label'=>'Overview','code'=>'ANOV','route'=>'analytics','icon'=>'speedometer','roles'=>[],'perms'=>['ANALYTICS_VIEW']],
      ['label'=>'Scenario Results','code'=>'ANSR','route'=>'scenario-results/index','icon'=>'table','roles'=>[],'perms'=>['ANALYTICS_VIEW']],
      ['label'=>'Scenario Compare','code'=>'ANSC','route'=>'scenario-results/compare','icon'=>'columns-gap','roles'=>[],'perms'=>['ANALYTICS_VIEW']],
    ],
  ],

  [
    'label' => 'Dashboards',
    'code'  => 'DB',
    'icon'  => 'pie-chart',
    'roles' => ['Dashboard User','Dashboard Administrator','Analytics User','Analytics Administrator','System Administrator'],
    'perms' => [],
    'children' => [
      ['label'=>'Dashboard','code'=>'DBOV','route'=>'dashboard/index','icon'=>'graph-up','active'=>['dashboard/*'],'roles'=>['Dashboard User','Dashboard Administrator','System Administrator'],'perms'=>[]],
      ['label'=>'Flexmonster Dashboard','code'=>'DBFX','route'=>'dashboard/flexdash','icon'=>'bar-chart-line-fill','active'=>['dashboard/flexdash'],'roles'=>['Dashboard User','Dashboard Administrator','Analytics User','Analytics Administrator','System Administrator'],'perms'=>[]],
    ],
  ],

  [
    'label' => __t('menu_admin'),
    'code'  => 'AD',
    'icon'  => 'gear',
    'roles' => [],
    'perms' => ['USERS_VIEW','ROLES_VIEW','AUDIT_VIEW','HEALTH_VIEW','SESSION_VIEW','DATAOBJECTCODES_ADMIN','METRICS_VIEW','WORKFLOW_VIEW','WORKFLOW_EDIT','WORKFLOW_ADMIN','SYSADMIN','ADMIN_ALL'],
    'children' => [
      [
        'label' => 'Access & Security',
        'code'  => 'ADAS',
        'icon'  => 'shield-lock',
        'roles' => [],
        'perms' => ['USERS_VIEW','ROLES_VIEW','SESSION_VIEW','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>__t('menu_users'),'code'=>'ADUS','route'=>'users/list','icon'=>'people','active'=>['users/*'],'roles'=>[],'perms'=>['USERS_VIEW','USERS_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>__t('menu_roles'),'code'=>'ADRO','route'=>'roles/list','icon'=>'shield-lock','active'=>['roles/*'],'roles'=>[],'perms'=>['ROLES_VIEW','ROLES_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Access Matrix','code'=>'ADAX','route'=>'access-matrix/index','icon'=>'shield-check','active'=>['access-matrix/*'],'roles'=>[],'perms'=>['ROLES_VIEW','ROLES_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Training Scenarios','code'=>'ADTC','route'=>'training/scenarios','icon'=>'list-check','active'=>['training/*'],'roles'=>[],'perms'=>['USERS_VIEW','USERS_EDIT','USERS_ADMIN','ADMIN_ALL','SYSADMIN'],'enabled'=>$trainingEnabled],
          ['label'=>'Training Summary','code'=>'ADTS','route'=>'training/summary','icon'=>'mortarboard','active'=>['training/*'],'roles'=>[],'perms'=>['USERS_VIEW','USERS_ADMIN','ADMIN_ALL','SYSADMIN'],'enabled'=>$trainingEnabled],
          ['label'=>__t('menu_active_sessions'),'code'=>'ADSE','route'=>'sessions/index','icon'=>'activity','active'=>['sessions/*'],'roles'=>[],'perms'=>['SESSION_VIEW']],
          ['label'=>__t('menu_session_vars'),'code'=>'ADSV','route'=>'session/list','icon'=>'person-badge','active'=>['session/*'],'roles'=>[],'perms'=>['SESSION_VIEW','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Audit & Diagnostics',
        'code'  => 'ADAD',
        'icon'  => 'activity',
        'roles' => [],
        'perms' => ['AUDIT_VIEW','HEALTH_VIEW','SYSADMIN','ADMIN_ALL'],
        'children' => [
          ['label'=>__t('menu_audit'),'code'=>'ADAU','route'=>'audit/list','icon'=>'journal-text','active'=>['audit/*'],'roles'=>[],'perms'=>['AUDIT_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Application Log','code'=>'ADLG','route'=>'application-log/index','icon'=>'file-earmark-text','active'=>['application-log/*'],'roles'=>[],'perms'=>['METRICS_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>__t('menu_diagnostics'),'code'=>'ADDI','route'=>'diagnostics/index','icon'=>'activity','active'=>['diagnostics/*'],'roles'=>[],'perms'=>['SYSADMIN','ADMIN_ALL']],
          ['label'=>__t('menu_health'),'code'=>'ADHE','route'=>'health/index','icon'=>'heart-pulse','active'=>['health/*'],'roles'=>[],'perms'=>['HEALTH_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>'System Messages','code'=>'ADSM','route'=>'systemmessages/list','icon'=>'megaphone','active'=>['systemmessages/*'],'roles'=>[],'perms'=>['SYSADMIN','ADMIN_ALL']],
        ],
      ],
      [
        'label' => 'Data Object Administration',
        'code'  => 'ADDO',
        'icon'  => 'collection',
        'roles' => [],
        'perms' => ['DATAOBJECTCODES_ADMIN'],
        'children' => [
          ['label'=>'Data Object Types','code'=>'ADOT','route'=>'dataobject-types/list','icon'=>'diagram-3','active'=>['dataobject-types/*'],'roles'=>[],'perms'=>['DATAOBJECTCODES_ADMIN']],
          ['label'=>'Data Object Codes','code'=>'ADOC','route'=>'dataobjectcodes/index','icon'=>'collection','active'=>['dataobjectcodes/*'],'roles'=>[],'perms'=>['DATAOBJECTCODES_ADMIN']],
          ['label'=>'Data Object Hierarchy','code'=>'ADOH','route'=>'dataobjectcodes/hierarchy','icon'=>'diagram-2','active'=>['dataobjectcodes/hierarchy'],'roles'=>[],'perms'=>['DATAOBJECTCODES_ADMIN']],
          ['label'=>'Workflow Status','code'=>'ADOW','route'=>'dataobjectworkflow/statuses','icon'=>'list-check','active'=>['dataobjectworkflow/*'],'roles'=>[],'perms'=>['DATAOBJECTCODES_ADMIN']],
          ['label'=>'Add Data Object Code','code'=>'ADDC','route'=>'dataobjectcodes/create','icon'=>'plus-circle','active'=>['dataobjectcodes/create'],'roles'=>[],'perms'=>['DATAOBJECTCODES_ADMIN']],
          ['label'=>'Access Management','code'=>'ADAM','route'=>'dataobjectcodes/access','icon'=>'shield-lock','active'=>['dataobjectcodes/access*'],'roles'=>[],'perms'=>['DATAOBJECTCODES_ADMIN']],
          ['label'=>'Grant Access','code'=>'ADGA','route'=>'dataobjectcodes/access_form','icon'=>'person-plus','active'=>['dataobjectcodes/access_form'],'roles'=>[],'perms'=>['DATAOBJECTCODES_ADMIN']],
        ],
      ],
      [
        'label' => 'Monitoring',
        'code'  => 'ADMO',
        'icon'  => 'graph-up',
        'roles' => [],
        'perms' => ['METRICS_VIEW','HEALTH_VIEW','SESSION_VIEW','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>__t('menu_metrics_failed_logins'),'code'=>'ADFL','route'=>'metrics/failed-logins','icon'=>'shield-exclamation','active'=>['metrics/failed-logins'],'roles'=>[],'perms'=>['METRICS_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>__t('menu_metrics_errors'),'code'=>'ADER','route'=>'metrics/errors-trend','icon'=>'activity','active'=>['metrics/errors-trend'],'roles'=>[],'perms'=>['METRICS_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>__t('menu_metrics_health'),'code'=>'ADMH','route'=>'metrics/health','icon'=>'heart-pulse','active'=>['metrics/health'],'roles'=>[],'perms'=>['METRICS_VIEW','ADMIN_ALL','SYSADMIN']],
          ['label'=>__t('menu_workflow_tasks'),'code'=>'ADWF','route'=>'workflow/list','icon'=>'list-task','active'=>['workflow/*'],'roles'=>[],'perms'=>['WORKFLOW_VIEW','WORKFLOW_EDIT','WORKFLOW_ADMIN','ADMIN_ALL','SYSADMIN']],
        ],
      ],
    ],
  ],

  [
    'label' => __t('menu_config'),
    'code'  => 'CF',
    'icon'  => 'sliders',
    'roles' => [],
    'perms' => ['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','CALC_ADMIN','STRATEGY_CONFIG_EDIT','STRATEGY_PUBLISH','SYSSETTINGS_VIEW','SYSSETTINGS_EDIT','DATAOBJECTCODES_ADMIN','ADMIN_ALL','SYSADMIN'],
    'children' => [
      [
        'label' => 'Base Configuration',
        'code'  => 'BACF',
        'icon'  => 'clipboard-check',
        'roles' => [],
        'perms' => ['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','SYSSETTINGS_VIEW','SYSSETTINGS_EDIT','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Base Configuration Readiness','code'=>'BACR','route'=>'base-config/readiness','icon'=>'clipboard-check','active'=>['base-config/*'],'roles'=>[],'perms'=>['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Fiscal Years','code'=>'BAFY','route'=>'fiscal-years/list','icon'=>'calendar3','active'=>['fiscal-years/*'],'roles'=>[],'perms'=>['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Versions','code'=>'BAVR','route'=>'versions/list','icon'=>'layers','active'=>['versions/*'],'roles'=>[],'perms'=>['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Currencies','code'=>'BACU','route'=>'currencies/list','icon'=>'currency-dollar','active'=>['currencies/*'],'roles'=>[],'perms'=>['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Currency Rates','code'=>'BACX','route'=>'currency-rates/list','icon'=>'currency-exchange','active'=>['currency-rates/*'],'roles'=>[],'perms'=>['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          [
            'label'=>__t('menu_config_syssettings'),
            'code'=>'BASS',
            'icon'=>'toggles',
            'active'=>['system-settings/*'],
            'roles'=>[],
            'perms'=>['SYSSETTINGS_VIEW','SYSSETTINGS_EDIT','SYSSETTINGS_ADMIN','ADMIN_ALL','SYSADMIN'],
            'children' => [
              ['label'=>'Settings Register','code'=>'BASR','route'=>'system-settings/list','icon'=>'toggles','active'=>['system-settings/list'],'roles'=>[],'perms'=>['SYSSETTINGS_VIEW','SYSSETTINGS_EDIT','SYSSETTINGS_ADMIN','ADMIN_ALL','SYSADMIN']],
              ['label'=>'Usage Map','code'=>'BASU','route'=>'system-settings/usage-map','icon'=>'diagram-3','active'=>['system-settings/usage-map'],'roles'=>[],'perms'=>['SYSSETTINGS_VIEW','SYSSETTINGS_ADMIN','ADMIN_ALL','SYSADMIN']],
            ],
          ],
          ['label'=>'Segments','code'=>'BASE','route'=>'segments/list','icon'=>'sliders2-vertical','active'=>['segments/*'],'roles'=>[],'perms'=>['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Segment Values','code'=>'BASV','route'=>'segment-values/list','icon'=>'diagram-3','active'=>['segment-values/*'],'roles'=>[],'perms'=>['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Workflow Engine','code'=>'BAWE','route'=>'workflow-engine/list','icon'=>'diagram-3','active'=>['workflow-engine/*'],'roles'=>[],'perms'=>['WORKFLOW_VIEW','WORKFLOW_EDIT','WORKFLOW_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Workflow Task Types','code'=>'BAWT','route'=>'workflow-task-types/list','icon'=>'list-task','active'=>['workflow-task-types/*'],'roles'=>[],'perms'=>['WORKFLOW_VIEW','WORKFLOW_EDIT','WORKFLOW_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Workflow Task Statuses','code'=>'BAWS','route'=>'workflow-task-statuses/list','icon'=>'signpost-split','active'=>['workflow-task-statuses/*'],'roles'=>[],'perms'=>['WORKFLOW_VIEW','WORKFLOW_EDIT','WORKFLOW_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Workflow Assignments','code'=>'BAWA','route'=>'workflow-assignments/list','icon'=>'person-gear','active'=>['workflow-assignments/*'],'roles'=>[],'perms'=>['BASE_CONFIG_VIEW','BASE_CONFIG_EDIT','WORKFLOW_VIEW','WORKFLOW_EDIT','WORKFLOW_ADMIN','ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Training Configuration',
        'code'  => 'CFTG',
        'icon'  => 'mortarboard',
        'roles' => [],
        'perms' => ['USERS_ADMIN','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Training Catalogue','code'=>'CFTC','route'=>'training-admin/scenarios','icon'=>'journal-text','active'=>['training-admin/*'],'roles'=>[],'perms'=>['USERS_ADMIN','ADMIN_ALL','SYSADMIN'],'enabled'=>$trainingEnabled],
        ],
      ],
      [
        'label' => 'Testing Configuration',
        'code'  => 'CFTS',
        'icon'  => 'clipboard-check',
        'roles' => [],
        'perms' => ['USERS_ADMIN','ADMIN_ALL','SYSADMIN'],
        'enabled' => $testingEnabled,
        'children' => [
          ['label'=>'Test Script Catalogue','code'=>'CFTT','route'=>'screen-tests-admin/scenarios','icon'=>'journal-text','active'=>['screen-tests-admin/*'],'roles'=>[],'perms'=>['USERS_ADMIN','ADMIN_ALL','SYSADMIN'],'enabled'=>$testingEnabled],
        ],
      ],
      [
        'label' => 'Integration Configuration',
        'code'  => 'CFIN',
        'icon'  => 'arrow-left-right',
        'roles' => [],
        'perms' => ['ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Integration Systems','code'=>'CFIS','route'=>'integration-admin/systems','icon'=>'diagram-3','active'=>['integration-admin/systems','integration-admin/system-form'],'roles'=>[],'perms'=>['ADMIN_ALL','SYSADMIN']],
          ['label'=>'Integration Interfaces','code'=>'CFII','route'=>'integration-admin/interfaces','icon'=>'arrow-left-right','active'=>['integration-admin/interfaces','integration-admin/interface-form'],'roles'=>[],'perms'=>['ADMIN_ALL','SYSADMIN']],
          ['label'=>'Integration Run History','code'=>'CFIR','route'=>'integration-admin/runs','icon'=>'clock-history','active'=>['integration-admin/runs'],'roles'=>[],'perms'=>['ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Reporting Configuration',
        'code'  => 'CFRP',
        'icon'  => 'printer',
        'roles' => [],
        'perms' => ['ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Report Definitions','code'=>'CFRD','route'=>'report-admin/definitions','icon'=>'journal-text','active'=>['report-admin/definitions','report-admin/definition-form'],'roles'=>[],'perms'=>['ADMIN_ALL','SYSADMIN']],
        ],
      ],
      [
        'label' => 'Financial & Calculation Configuration',
        'code'  => 'FCCF',
        'icon'  => 'calculator',
        'roles' => [],
        'perms' => ['FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','CALC_ADMIN','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Financial & Calculation Readiness','code'=>'FCCR','route'=>'financial-config/readiness','icon'=>'clipboard-data','active'=>['financial-config/*'],'roles'=>[],'perms'=>['FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','CALC_ADMIN','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Transaction Type Segment Config','code'=>'FCTS','route'=>'transaction-type-segment-config/list','icon'=>'sliders2-vertical','active'=>['transaction-type-segment-config/*'],'roles'=>[],'perms'=>['FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Ceiling Balances','code'=>'FCCB','route'=>'ceilings/balances','icon'=>'safe2','active'=>['ceilings/balances'],'roles'=>[],'perms'=>['FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','ESTIMATES_VIEW','ESTIMATES_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Ceiling Balance Keys','code'=>'FCCK','route'=>'ceilings/balances-keys','icon'=>'key','active'=>['ceilings/balances-keys'],'roles'=>[],'perms'=>['FIN_CONFIG_VIEW','FIN_CONFIG_EDIT','ESTIMATES_VIEW','ESTIMATES_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Scenario Config','code'=>'FCSC','route'=>'scenario-admin/index','icon'=>'diagram-3','active'=>['scenario-admin/*'],'roles'=>[],'perms'=>['CALC_ADMIN','SYSADMIN','ADMIN_ALL']],
          ['label'=>'Transaction Calc Debug','code'=>'FCTD','route'=>'transaction-calc-diagnostics/index','icon'=>'search','active'=>['transaction-calc-diagnostics/*'],'roles'=>[],'perms'=>['CALC_ADMIN','SYSADMIN','ADMIN_ALL']],
          ['label'=>'Full Recalculation','code'=>'FCFR','route'=>'full-recalculation/index','icon'=>'cpu','active'=>['full-recalculation/*'],'roles'=>[],'perms'=>['CALC_ADMIN','SYSADMIN','ADMIN_ALL']],
        ],
      ],
      [
        'label' => 'Strategy Configuration',
        'code'  => 'SCFG',
        'icon'  => 'flag',
        'roles' => [],
        'perms' => ['STRATEGY_CONFIG_EDIT','STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN'],
        'children' => [
          ['label'=>'Fiscal Assumptions','code'=>'SCFA','route'=>'strategy-config/fiscal-assumptions','icon'=>'graph-up-arrow','active'=>['strategy-config/fiscal-assumptions*'],'roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Custom Phasing Profiles','code'=>'SCPP','route'=>'strategy-config/phasing-profiles','icon'=>'pie-chart','active'=>['strategy-config/phasing-profiles*'],'roles'=>[],'perms'=>['STRATEGY_CONFIG_EDIT','ADMIN_ALL','SYSADMIN']],
          ['label'=>'Segment Publication','code'=>'SCPU','route'=>'strategy-publish/requests','icon'=>'upload','active'=>['strategy-publish/*'],'roles'=>[],'perms'=>['STRATEGY_PUBLISH','ADMIN_ALL','SYSADMIN']],
        ],
      ],
    ],
  ],

];
