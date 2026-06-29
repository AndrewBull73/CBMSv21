# CBMSv21 Training Scenario Matrix

This document is the planning source of truth for CBMSv21 training paths and scenarios. It aligns training with the application menu, access permissions, role model, and the order in which users should learn the system.

## Purpose

Training scenarios should support both:

- Self-paced learning, where users can open contextual training from a screen and complete guided steps.
- Instructor-led training, where a facilitator can take a group through standard scenarios in a controlled order.

Every training scenario should map to a real screen, menu item, permission, and target user role. If a scenario is not tied to a screen or operational action, it should not be added to the catalogue.

## Scenario Rules

1. Every scenario must map to a menu route or a clear operational task.
2. Every scenario must declare the required permission codes.
3. Every scenario must declare target roles.
4. Every scenario must have a defined order inside a training path.
5. Every scenario must record prerequisites, sample data needs, and cleanup expectations.
6. Existing implemented scenarios should be reused instead of renamed.
7. Planned scenarios should stay marked as `PLANNED` until added to the training catalogue.
8. Validation must pass before a scenario is used in live training.

## Training Path Order

| Order | Path Code | Path Name | Purpose |
| ---: | --- | --- | --- |
| 10 | FOUNDATION | CBMS Fundamentals | Navigation, context, page layout, menu use, help, quick links, and screen conventions. |
| 20 | BASE-CONFIG | Base Configuration | Base readiness and core configuration setup needed before operational use. |
| 30 | ORG-COA | Organisation and Chart of Accounts | Segments, segment values, data object codes, hierarchy, access, and workflow status. |
| 40 | SECURITY-ADMIN | Security Administration | Users, roles, access matrix, sessions, audit visibility, logs, and email queue administration. |
| 50 | STRATEGY-CONFIG | Strategy Configuration | Strategy dimensions, mapping, fiscal setup, assumptions, profiles, and publication configuration. |
| 60 | STRATEGY-SETUP | Strategic Framework Setup | Pillars, goals, objectives, indicators, programs, projects, funding sources, and classifications. |
| 70 | STRATEGY-PLANNING | Strategy Planning | Resource envelopes, ceilings, outputs, activities, activity budgets, narratives, and risks. |
| 80 | SUBMISSIONS | Budget Submissions | Funding submissions, review, approval, publication, and submission monitoring. |
| 90 | BUDGET-PLANNING | Budget Planning | Transaction input, estimate processing, ceilings, rates, and planning administration. |
| 100 | BUDGET-EXECUTION | Budget Execution | Execution setup, opening balances, warrants, supplementaries, reductions, reservations, RIE, and commitments. |
| 110 | REPORTING-ANALYTICS | Reporting and Analytics | Reports, dashboards, analytics, scenario results, and comparison views. |
| 120 | TRAINING-ADMIN | Training Administration | Scenario maintenance, assignments, sessions, validation, evidence, and training operations. |

## Role to Path Alignment

| Role | Required Training Paths |
| --- | --- |
| Super Admin | All paths. |
| System Administrator | FOUNDATION, SECURITY-ADMIN, TRAINING-ADMIN, REPORTING-ANALYTICS, diagnostics and monitoring scenarios. |
| Security Administrator | FOUNDATION, SECURITY-ADMIN. |
| Configuration Administrator | FOUNDATION, BASE-CONFIG, ORG-COA, STRATEGY-CONFIG, TRAINING-ADMIN where required. |
| Base Configuration Administrator | FOUNDATION, BASE-CONFIG, ORG-COA basics. |
| Financial Configuration Administrator | FOUNDATION, BASE-CONFIG financial scenarios, BUDGET-PLANNING configuration scenarios. |
| Organisation / COA Administrator | FOUNDATION, ORG-COA. |
| Strategy Configuration Administrator | FOUNDATION, STRATEGY-CONFIG. |
| Strategic Framework User | FOUNDATION, STRATEGY-SETUP, STRATEGY-PLANNING user scenarios. |
| Strategic Framework Reviewer | FOUNDATION, SUBMISSIONS review scenarios. |
| Strategic Framework Approver | FOUNDATION, SUBMISSIONS review and approval scenarios. |
| Strategic Framework Reporting User | FOUNDATION, REPORTING-ANALYTICS strategy report scenarios. |
| Strategic Framework Administrator | FOUNDATION, STRATEGY-CONFIG, STRATEGY-SETUP, STRATEGY-PLANNING, SUBMISSIONS admin scenarios. |
| Budget Strategy User | FOUNDATION, STRATEGY-SETUP, STRATEGY-PLANNING user scenarios. |
| Budget Strategy Reviewer | FOUNDATION, SUBMISSIONS review scenarios. |
| Budget Strategy Approver | FOUNDATION, SUBMISSIONS review and approval scenarios. |
| Budget Strategy Administrator | FOUNDATION, STRATEGY-CONFIG, STRATEGY-SETUP, STRATEGY-PLANNING, SUBMISSIONS. |
| Budget Planning User | FOUNDATION, BUDGET-PLANNING user scenarios. |
| Budget Planning Administrator | FOUNDATION, BUDGET-PLANNING admin scenarios. |
| Budget Submission User | FOUNDATION, BUDGET-PLANNING user scenarios, SUBMISSIONS prepare scenarios. |
| Budget Submission Reviewer | FOUNDATION, SUBMISSIONS review scenarios. |
| Budget Submission Approver | FOUNDATION, SUBMISSIONS approval and publish scenarios. |
| Budget Submission Administrator | FOUNDATION, BUDGET-PLANNING admin scenarios, SUBMISSIONS. |
| Budget Execution User | FOUNDATION, BUDGET-EXECUTION user scenarios. |
| Budget Execution Reviewer | FOUNDATION, BUDGET-EXECUTION review scenarios. |
| Budget Execution Administrator | FOUNDATION, BUDGET-EXECUTION admin scenarios. |
| Reporting User | FOUNDATION, REPORTING-ANALYTICS report scenarios. |
| Reporting Administrator | FOUNDATION, REPORTING-ANALYTICS report admin scenarios. |
| Analytics User | FOUNDATION, REPORTING-ANALYTICS analytics scenarios. |
| Analytics Administrator | FOUNDATION, REPORTING-ANALYTICS analytics scenarios. |
| Dashboard User | FOUNDATION, REPORTING-ANALYTICS dashboard scenarios. |
| Dashboard Administrator | FOUNDATION, REPORTING-ANALYTICS dashboard admin scenarios. |

## Scenario Status Values

| Status | Meaning |
| --- | --- |
| IMPLEMENTED | Scenario exists in the current training catalogue. |
| PLANNED | Scenario is required but still needs to be added. |
| REVIEW | Scenario exists or partially exists but needs verification against the screen. |
| DEFERRED | Scenario is useful but not required for the first rollout. |

## Scenario Matrix

| Order | Path | Scenario Code | Status | Menu / Screen | Required Permissions | Target Roles | Prerequisites | Training Data | Cleanup |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 10 | FOUNDATION | cbms_fundamentals_home_nav | IMPLEMENTED | Home | AUTHENTICATED | All users | User can sign in | None | None |
| 20 | FOUNDATION | cbms_fundamentals_context | IMPLEMENTED | Application context controls | AUTHENTICATED | All users | Home navigation | None | None |
| 30 | FOUNDATION | cbms_fundamentals_datascope | IMPLEMENTED | Data scope/context | AUTHENTICATED | All users | Application context | None | None |
| 40 | FOUNDATION | cbms_fundamentals_menu_nav | IMPLEMENTED | Menu navigation | AUTHENTICATED | All users | Home navigation | None | None |
| 50 | FOUNDATION | screen_help_and_quick_links_basics | PLANNED | Helper Instructions and Quick Links | AUTHENTICATED | All users | Menu navigation | None | None |
| 110 | BASE-CONFIG | base_configuration_readiness_basics | IMPLEMENTED | Base Configuration Readiness | BASE_CONFIG_VIEW | Configuration and base administrators | Foundation | None | None |
| 120 | BASE-CONFIG | fiscal_years_create_basics | IMPLEMENTED | Fiscal Years | BASE_CONFIG_EDIT | Configuration administrators | Base readiness | Demo fiscal year | Remove demo fiscal year if created |
| 130 | BASE-CONFIG | versions_create_basics | IMPLEMENTED | Versions | BASE_CONFIG_EDIT | Configuration administrators | Fiscal years | Demo version | Remove demo version if created |
| 140 | BASE-CONFIG | currencies_create_basics | IMPLEMENTED | Currencies | FIN_CONFIG_EDIT | Financial configuration administrators | Base readiness | Demo currency | Remove demo currency if created |
| 150 | BASE-CONFIG | currency_rates_create_basics | IMPLEMENTED | Currency Rates | FIN_CONFIG_EDIT | Financial configuration administrators | Currencies | Demo exchange rate | Remove demo rate if created |
| 160 | BASE-CONFIG | system_settings_basics | IMPLEMENTED | System Settings | SYSSETTINGS_VIEW, SYSSETTINGS_EDIT | System and configuration administrators | Foundation | Training setting value | Restore original setting |
| 170 | BASE-CONFIG | financial_config_basics | PLANNED | Financial Configuration | FIN_CONFIG_VIEW, FIN_CONFIG_EDIT | Financial configuration administrators | Fiscal years, currencies | Demo config value | Restore original setting |
| 180 | BASE-CONFIG | calculation_config_basics | PLANNED | Calculation Configuration | CALC_ADMIN | Financial configuration administrators | Financial config | Demo calculation parameter | Restore original setting |
| 210 | ORG-COA | segments_create_basics | IMPLEMENTED | Segments | SEGMENTS_VIEW, SEGMENTS_EDIT | Organisation / COA administrators | Foundation | Demo segment | Remove demo segment if created |
| 220 | ORG-COA | segment_values_create_basics | IMPLEMENTED | Segment Values | SEGMENTS_VIEW, SEGMENTS_EDIT | Organisation / COA administrators | Segments | Demo segment value | Remove demo value if created |
| 230 | ORG-COA | data_object_codes_create_basics | IMPLEMENTED | Data Object Codes | DATAOBJECTCODES_VIEW, DATAOBJECTCODES_EDIT | Organisation / COA administrators | Segment values | Demo data object code | Remove demo code if created |
| 240 | ORG-COA | data_object_types_basics | PLANNED | Data Object Types | DATAOBJECTCODES_VIEW, DATAOBJECTCODES_EDIT | Organisation / COA administrators | Foundation | Demo type | Remove demo type if created |
| 250 | ORG-COA | data_object_hierarchy_basics | PLANNED | Data Object Hierarchy | DATAOBJECTCODES_VIEW, DATAOBJECTCODES_EDIT | Organisation / COA administrators | Data object codes | Demo parent/child relation | Remove demo relation if created |
| 260 | ORG-COA | data_object_load_from_segments_basics | PLANNED | Load From Segment Values | DATAOBJECTCODES_IMPORT | Organisation / COA administrators | Segment values | Demo import set | Reverse import where possible |
| 270 | ORG-COA | data_object_access_basics | PLANNED | Data Object Access | DATAOBJECTCODES_ACCESS_ADMIN | Organisation / COA administrators | Data object codes | Demo access grant | Remove demo grant |
| 280 | ORG-COA | data_object_workflow_status_basics | PLANNED | Data Object Workflow Status | DATAOBJECTCODES_EDIT | Organisation / COA administrators | Data object codes | Demo status transition | Restore original status |
| 310 | SECURITY-ADMIN | users_create_demo | IMPLEMENTED | Users | USERS_VIEW, USERS_EDIT | Security administrators | Foundation | Demo user | Deactivate or remove demo user |
| 320 | SECURITY-ADMIN | users_edit_record | IMPLEMENTED | Users | USERS_VIEW, USERS_EDIT | Security administrators | Demo user | Existing demo user | Restore changed fields |
| 330 | SECURITY-ADMIN | roles_and_permissions_basics | PLANNED | Roles and Permissions | ROLES_VIEW, ROLES_ADMIN | Security administrators | Foundation | Demo role or mapping | Restore mapping |
| 340 | SECURITY-ADMIN | access_matrix_basics | PLANNED | Access Matrix | ROLES_VIEW | Security administrators | Roles | None | None |
| 350 | SECURITY-ADMIN | active_sessions_basics | PLANNED | Active Sessions | SESSION_VIEW, SESSION_ADMIN | System and security administrators | Foundation | Active session | None |
| 360 | SECURITY-ADMIN | audit_trail_basics | PLANNED | Audit Trail | AUDIT_VIEW | System and security administrators | Foundation | Existing audit data | None |
| 370 | SECURITY-ADMIN | application_log_basics | PLANNED | Application Log | LOGS_VIEW | System administrators | Foundation | Existing log entries | None |
| 380 | SECURITY-ADMIN | email_queue_operations_basics | PLANNED | Email Queue | ERRORLOG_VIEW, ERRORLOG_ADMIN | System administrators | Foundation | Demo queued email | Restore original queue status |
| 410 | STRATEGY-CONFIG | strategy_configuration_overview_basics | PLANNED | Strategy Configuration | STRATEGY_CONFIG_EDIT | Strategy configuration administrators | Foundation | None | None |
| 420 | STRATEGY-CONFIG | strategy_segment_mapping_basics | PLANNED | Segment Mapping | STRATEGY_CONFIG_EDIT | Strategy configuration administrators | ORG-COA basics | Demo mapping | Remove demo mapping |
| 430 | STRATEGY-CONFIG | strategy_import_dimensions_basics | PLANNED | Import Dimensions | STRATEGY_CONFIG_EDIT | Strategy configuration administrators | Segment mapping | Demo import | Reverse import where possible |
| 440 | STRATEGY-CONFIG | strategy_fiscal_periods_basics | PLANNED | Fiscal Periods | STRATEGY_CONFIG_EDIT | Strategy configuration administrators | Fiscal years | Demo period | Remove demo period |
| 450 | STRATEGY-CONFIG | strategy_fiscal_assumptions_basics | PLANNED | Fiscal Assumptions | STRATEGY_CONFIG_EDIT | Strategy configuration administrators | Fiscal periods | Demo assumption | Remove demo assumption |
| 460 | STRATEGY-CONFIG | strategy_phasing_profiles_basics | PLANNED | Phasing Profiles | STRATEGY_CONFIG_EDIT | Strategy configuration administrators | Fiscal periods | Demo profile | Remove demo profile |
| 470 | STRATEGY-CONFIG | strategy_segment_publication_basics | PLANNED | Segment Publication | STRATEGY_SEGMENT_PUBLISH | Strategy configuration administrators | Segment mapping | Demo request | Cancel demo request |
| 510 | STRATEGY-SETUP | strategic_pillars_basics | PLANNED | Pillars | STRATEGY_SETUP_EDIT | Strategy users and administrators | Strategy config | Demo pillar | Remove demo pillar |
| 520 | STRATEGY-SETUP | strategic_goals_basics | PLANNED | Goals | STRATEGY_SETUP_EDIT | Strategy users and administrators | Pillars | Demo goal | Remove demo goal |
| 530 | STRATEGY-SETUP | strategic_objectives_basics | PLANNED | Objectives | STRATEGY_SETUP_EDIT | Strategy users and administrators | Goals | Demo objective | Remove demo objective |
| 540 | STRATEGY-SETUP | strategic_indicators_targets_basics | PLANNED | Indicators and Targets | STRATEGY_PERFORMANCE_EDIT | Strategy users and administrators | Objectives | Demo indicator and target | Remove demo records |
| 550 | STRATEGY-SETUP | strategic_program_structure_basics | PLANNED | Sectors, Programs, SubPrograms, Projects | STRATEGY_SETUP_EDIT | Strategy users and administrators | Objectives | Demo program structure | Remove demo records |
| 560 | STRATEGY-SETUP | strategic_funding_classifications_basics | PLANNED | Funding Types and Sources | STRATEGY_FISCAL_EDIT | Strategy users and administrators | Foundation | Demo funding source | Remove demo source |
| 610 | STRATEGY-PLANNING | strategy_resource_envelope_line_basics | IMPLEMENTED | Resource Envelope Lines | STRATEGY_FISCAL_EDIT | Budget strategy users and administrators | Fiscal framework | Demo envelope line | Remove demo line |
| 620 | STRATEGY-PLANNING | strategy_sector_ceilings_basics | PLANNED | Sector Ceilings | STRATEGY_FISCAL_EDIT | Budget strategy users and administrators | Resource envelopes | Demo ceiling | Remove demo ceiling |
| 630 | STRATEGY-PLANNING | strategy_outputs_basics | PLANNED | Outputs | STRATEGY_DELIVERY_EDIT | Budget strategy users and administrators | Program structure | Demo output | Remove demo output |
| 640 | STRATEGY-PLANNING | strategy_activities_basics | PLANNED | Activities | STRATEGY_DELIVERY_EDIT | Budget strategy users and administrators | Outputs | Demo activity | Remove demo activity |
| 650 | STRATEGY-PLANNING | strategy_activity_budgets_basics | PLANNED | Activity Budgets | STRATEGY_DELIVERY_EDIT | Budget strategy users and administrators | Activities | Demo budget line | Remove demo budget |
| 660 | STRATEGY-PLANNING | strategy_narratives_risks_basics | PLANNED | Narratives and Risks | STRATEGY_GOVERNANCE_EDIT | Budget strategy users and administrators | Program structure | Demo narrative/risk | Remove demo record |
| 710 | SUBMISSIONS | funding_submission_prepare_basics | PLANNED | Funding Submissions | STRATEGY_SUBMISSION_PREPARE | Budget submission users and administrators | Strategy planning | Demo submission | Cancel or remove demo submission |
| 720 | SUBMISSIONS | funding_submission_review_basics | PLANNED | Submission Review | STRATEGY_SUBMISSION_REVIEW | Reviewers and administrators | Prepared submission | Demo submission | Restore review status |
| 730 | SUBMISSIONS | funding_submission_approval_basics | PLANNED | Submission Approval | STRATEGY_SUBMISSION_APPROVE | Approvers and administrators | Reviewed submission | Demo submission | Restore approval status |
| 740 | SUBMISSIONS | funding_submission_publish_basics | PLANNED | Submission Publish | STRATEGY_PUBLISH | Approvers and administrators | Approved submission | Demo submission | Do not publish live data during training |
| 810 | BUDGET-PLANNING | transaction_input_list_basics | PLANNED | Transaction Input List | ESTIMATES_VIEW | Budget planning users | Foundation | Existing transactions | None |
| 820 | BUDGET-PLANNING | transaction_input_edit_basics | PLANNED | Transaction Input Editor | ESTIMATES_VIEW, ESTIMATES_EDIT | Budget planning users | Transaction list | Demo transaction | Remove demo transaction |
| 830 | BUDGET-PLANNING | budget_planning_ceilings_basics | PLANNED | Ceilings | RATES_VIEW, RATES_EDIT | Budget planning administrators | Foundation | Demo ceiling | Remove demo ceiling |
| 840 | BUDGET-PLANNING | estimate_processing_basics | PLANNED | Processing Tools | CALC_ADMIN | Budget planning administrators | Transaction data | Demo processing run | Remove demo run if stored |
| 850 | BUDGET-PLANNING | rate_management_basics | PLANNED | Rates | RATES_VIEW, RATES_EDIT, RATES_CREATE | Budget planning administrators | Foundation | Demo rate | Remove demo rate |
| 910 | BUDGET-EXECUTION | execution_setup_basics | PLANNED | Budget Execution Setup | BUDGET_EXECUTION_ADMIN | Budget execution administrators | Foundation | Demo setup value | Restore original value |
| 920 | BUDGET-EXECUTION | execution_opening_balances_basics | PLANNED | Opening Balances | BUDGET_EXECUTION_EDIT | Budget execution users and administrators | Execution setup | Demo opening balance | Remove demo balance |
| 930 | BUDGET-EXECUTION | execution_warrant_basics | PLANNED | Warrants | BUDGET_EXECUTION_EDIT | Budget execution users and administrators | Opening balances | Demo warrant | Remove demo warrant |
| 940 | BUDGET-EXECUTION | execution_supplementary_basics | PLANNED | Supplementaries | BUDGET_EXECUTION_EDIT | Budget execution users and administrators | Execution setup | Demo supplementary | Remove demo supplementary |
| 950 | BUDGET-EXECUTION | execution_reduction_basics | PLANNED | Reductions | BUDGET_EXECUTION_EDIT | Budget execution users and administrators | Execution setup | Demo reduction | Remove demo reduction |
| 960 | BUDGET-EXECUTION | execution_reservation_create_basics | IMPLEMENTED | Reservations | BUDGET_EXECUTION_VIEW, BUDGET_EXECUTION_EDIT | Budget execution users and administrators | Execution setup | Demo reservation | Cancel or remove demo reservation |
| 970 | BUDGET-EXECUTION | execution_rie_basics | PLANNED | RIE | BUDGET_EXECUTION_EDIT | Budget execution users and administrators | Reservation or warrant | Demo RIE | Remove demo RIE |
| 980 | BUDGET-EXECUTION | execution_commitment_basics | PLANNED | Commitments | BUDGET_EXECUTION_EDIT | Budget execution users and administrators | Execution setup | Demo commitment | Remove demo commitment |
| 990 | BUDGET-EXECUTION | execution_review_workflow_basics | PLANNED | Execution Review | BUDGET_EXECUTION_REVIEW | Budget execution reviewers | Demo execution transaction | Demo workflow item | Restore original status |
| 1010 | REPORTING-ANALYTICS | report_catalogue_basics | PLANNED | Report Catalogue | STRATEGY_REPORT_VIEW | Reporting users | Foundation | Existing report | None |
| 1020 | REPORTING-ANALYTICS | report_execute_basics | PLANNED | Run Report | STRATEGY_REPORT_VIEW | Reporting users | Report catalogue | Existing report | None |
| 1030 | REPORTING-ANALYTICS | dashboard_basics | PLANNED | Dashboard | DASHBOARD_VIEW | Dashboard users | Foundation | Existing dashboard | None |
| 1040 | REPORTING-ANALYTICS | analytics_view_basics | PLANNED | Analytics | ANALYTICS_VIEW | Analytics users | Foundation | Existing analytics data | None |
| 1050 | REPORTING-ANALYTICS | scenario_results_basics | PLANNED | Scenario Results | ANALYTICS_VIEW | Analytics users | Existing scenario results | Existing scenario | None |
| 1110 | TRAINING-ADMIN | training_scenario_catalogue_basics | PLANNED | Training Catalogue | SYSADMIN | Training administrators | Foundation | Existing scenario | None |
| 1120 | TRAINING-ADMIN | training_assignments_basics | PLANNED | Training Operations | SYSADMIN | Training administrators | Scenario catalogue | Demo assignment | Remove demo assignment |
| 1130 | TRAINING-ADMIN | training_session_dashboard_basics | PLANNED | Training Session Dashboard | SYSADMIN | Training administrators and instructors | Assignment | Demo session | Close demo session |
| 1140 | TRAINING-ADMIN | training_validation_basics | PLANNED | Training Validation | SYSADMIN | Training administrators | Scenario catalogue | Existing scenarios | None |
| 1150 | TRAINING-ADMIN | screen_testing_catalogue_basics | PLANNED | Screen Testing Catalogue | SYSADMIN | System administrators | Foundation | Existing screen tests | None |

## Scenario Naming Standard

Use lower snake case:

```text
{module}_{screen_or_process}_{action_or_learning_goal}
```

Examples:

- `strategy_activity_budgets_basics`
- `funding_submission_review_basics`
- `execution_reservation_create_basics`
- `application_log_basics`

Path codes should use uppercase words separated by hyphens, for example `BUDGET-EXECUTION`.

## Completion Evidence

Each scenario should record at least one completion signal:

- User started the scenario.
- User completed the final step.
- User skipped or abandoned the scenario.
- User marked a step as difficult or got stuck.
- Instructor or administrator can view session progress.

Where the scenario creates or changes data, evidence should include the created record key or transaction reference where practical.

## Rollout Plan

### Phase 1 - Confirm Matrix

Review this document with functional leads and confirm:

- Path order.
- Required scenario list.
- Target roles.
- Priority screens for the first training rollout.

### Phase 2 - Foundation and Administration

Complete missing scenarios for:

- Screen help and quick links.
- Users, roles, access matrix, sessions, audit, application log, and email queue.
- Training administration operations and validation.

### Phase 3 - Configuration and COA

Complete missing scenarios for:

- Financial and calculation configuration.
- Data object types, hierarchy, load from segments, access, and workflow status.
- Strategy configuration setup.

### Phase 4 - Strategy and Submissions

Complete missing scenarios for:

- Strategic framework setup.
- Strategy planning.
- Funding submission preparation, review, approval, and publish.

### Phase 5 - Budget Planning and Execution

Complete missing scenarios for:

- Transaction input.
- Estimate processing.
- Rates and ceilings.
- Budget execution transaction lifecycle and review.

### Phase 6 - Reporting and Pilot

Complete reporting and analytics scenarios, then run pilot training with:

- One administrator.
- One configuration user.
- One strategy user.
- One reviewer.
- One approver.
- One budget execution user.

## Validation Checklist

Before any scenario is released:

- The menu route exists.
- The screen can be opened by the target role.
- Required permission codes match the menu access rules.
- Training overlay targets exist on the screen.
- Required sample data is available.
- Cleanup expectations are documented.
- Training validation returns no errors.
- Scenario was tested by at least one user with the target role.
