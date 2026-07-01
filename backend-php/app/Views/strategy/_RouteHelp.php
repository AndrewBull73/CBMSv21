<?php
declare(strict_types=1);

$route = (string)($_GET['route'] ?? '');

$help = [
    'title' => 'Screen Help',
    'purpose' => 'Use this area to review the current screen, confirm the active context, and follow the available actions in the right order.',
    'points' => [
        'Check the filters, selected context, and visible status values before taking action.',
        'Use the available buttons and row actions to move through the workflow for this screen.',
        'Review warnings or status messages after saving, sending, importing, or running a diagnostic action.',
    ],
    'note' => 'Some screens are context-specific, so confirm the active fiscal year, version, or selected filter before relying on the results.',
];

$map = [
    'workflow/list' => [
        'title' => 'Workflow Tasks Help',
        'purpose' => 'Use this screen to manage workflow tasks assigned to you, created by you, or visible to you through Workflow Operations permissions.',
        'points' => [
            'Use the task tabs and due-date filters to focus on open, closed, overdue, due today, or due soon work.',
            'When a workflow project has already been selected elsewhere, this screen keeps that project context until you choose All Projects or another project.',
            'Create tasks directly from this screen for general work, or create them from a project, requirement, or issue when the task should stay linked to that record.',
            'Use Print for a quick on-screen report and Export Excel to download the currently filtered task register.',
        ],
        'note' => 'Task visibility depends on your workflow permissions. Non-admin users only export the tasks they are allowed to see.',
    ],
    'workflow/edit' => [
        'title' => 'Workflow Task Form Help',
        'purpose' => 'Use this form to create, update, respond to, and track a workflow task.',
        'points' => [
            'Use the Details and Assignment tabs for the core task information, dates, assignee, priority, and project links.',
            'For project tasks, planned and due dates must sit inside the valid project date range shown on the form.',
            'Use the Discussion, Files, Views, Activity, and Notifications tabs to keep supporting evidence and communication with the task.',
            'Read-only fields are visually muted; they show generated or controlled values that cannot be edited on the current screen.',
        ],
        'note' => 'Tasks created from requirements or issues should remain linked to those records so progress can be reported from the requirement matrix and issue log.',
    ],
    'workflow-projects/list' => [
        'title' => 'Workflow Projects Help',
        'purpose' => 'Use this screen to create and review workflow project containers for related tasks, requirements, issues, and project users.',
        'points' => [
            'Open a project summary when you want the project to become the active workflow project context for related workflow screens.',
            'Use the project filters to narrow the register by search text, status, or active flag.',
            'Create requirements, issues, or tasks from project actions when the new item should be linked to that project.',
            'Use Print for a quick project register report and Export Excel to download the filtered project list.',
        ],
        'note' => 'The selected project is remembered across Workflow Operations screens to reduce repeated project selection.',
    ],
    'workflow-projects/summary' => [
        'title' => 'Project Summary Help',
        'purpose' => 'Use this screen to review one workflow project across overview, requirements, issues, linked work, users, and schedule information.',
        'points' => [
            'Use the tabs to move between project overview, requirements, issues, linked work, team, and schedule details without losing the project context.',
            'Create project tasks, requirements, and issues from this screen when they belong to the current project.',
            'Review project issues here to understand blockers and the tasks being used to resolve them.',
            'Use the quick links to move to Requirements, Matrix, Issues, or Tasks while keeping this project selected.',
        ],
        'note' => 'Opening this screen sets the remembered workflow project context for other Workflow Operations screens.',
    ],
    'workflow-projects/form' => [
        'title' => 'Workflow Project Form Help',
        'purpose' => 'Use this form to create or update project details, ownership, project users, dates, and project schedule information.',
        'points' => [
            'Enter clear project dates because project task start and due dates are validated against the project range.',
            'Assign project users and roles so the project team is visible from the summary screen.',
            'Use the Gantt and task sections to review project work by planned dates and dependencies.',
            'Read-only fields are visually muted and indicate generated or controlled values.',
        ],
        'note' => 'Saving or opening an existing project sets it as the remembered workflow project context.',
    ],
    'workflow-requirements/list' => [
        'title' => 'Requirements Help',
        'purpose' => 'Use this screen to maintain high-level and detailed requirements for workflow projects.',
        'points' => [
            'Use the project, type, priority, status, level, and active filters to narrow the requirement register.',
            'Create detailed requirements under a high-level requirement when you need more granular delivery or acceptance tracking.',
            'Use row actions to open, edit, create tasks, create issues, or navigate to related project information.',
            'Use Print for a quick register report and Export Excel to download the filtered requirements list.',
        ],
        'note' => 'If a project was selected on another Workflow Operations screen, the requirement filters default to that project until you clear or change it.',
    ],
    'workflow-requirements/summary' => [
        'title' => 'Requirements Summary Help',
        'purpose' => 'Use this screen to review requirement totals, gaps, status mix, priority mix, and project distribution.',
        'points' => [
            'Filter by project, delivery class, type, priority, status, level, and active flag before reviewing the metrics.',
            'Use the parent coverage section to identify high-level requirements missing detailed requirements or ownership details.',
            'Move to the Requirements Matrix when you need traceability evidence and linked-task coverage.',
            'Use Print for the current summary view and Export Excel to download the summarized metrics.',
        ],
        'note' => 'This screen also follows the remembered project context from the Workflow Projects area.',
    ],
    'workflow-requirements/matrix' => [
        'title' => 'Requirements Matrix Help',
        'purpose' => 'Use this screen to check requirement traceability, linked tasks, testing evidence, training links, defects, and coverage gaps.',
        'points' => [
            'Use the coverage filter to focus on missing tasks, open tasks, missing testing, missing training, missing acceptance criteria, or defects.',
            'Create tasks from requirement rows when delivery work is missing or needs follow-up.',
            'Use the gaps column to identify the action needed before a requirement is considered fully covered.',
            'Use Print for a quick matrix view and Export Excel to download the filtered matrix evidence.',
        ],
        'note' => 'The matrix is most useful when tasks, testing, training, and defect links are maintained as part of the delivery process.',
    ],
    'workflow-requirements/form' => [
        'title' => 'Requirement Form Help',
        'purpose' => 'Use this form to create or update a high-level or detailed requirement, its acceptance criteria, ownership, links, tasks, history, and files.',
        'points' => [
            'Select the project first; the available parent requirements and related links depend on that project.',
            'Use Requirement Level and Parent Requirement to create detailed requirements under a high-level requirement.',
            'Use the Tasks and Issues areas to create linked work that delivers or resolves the requirement.',
            'Attach supporting files and keep acceptance criteria clear enough to test.',
        ],
        'note' => 'Read-only fields are visually muted. Generated codes and controlled status values should be changed only through the available workflow actions.',
    ],
    'workflow-issues/list' => [
        'title' => 'Issues Log Help',
        'purpose' => 'Use this screen to record, filter, review, and manage project or requirement issues.',
        'points' => [
            'Use the project filter to see only issues for the selected workflow project.',
            'Link issues to requirements where possible so requirement impacts and resolution work are easier to trace.',
            'Use linked tasks to manage the work required to resolve each issue.',
            'Use Print for a quick issues report and Export Excel to download the filtered issues log.',
        ],
        'note' => 'Issue Code is generated on save and shown as read-only before it exists.',
    ],
    'workflow-issues/form' => [
        'title' => 'Issue Form Help',
        'purpose' => 'Use this form to create or update an issue, link it to a project or requirement, attach evidence, and create resolution tasks.',
        'points' => [
            'Select a project first; the requirement dropdown is filtered to requirements for that project.',
            'Use severity, priority, owner, due date, and status to keep the issue actionable.',
            'Attach relevant files when the issue needs screenshots, documents, logs, or other evidence.',
            'Create linked tasks from the issue so resolution work can be assigned and tracked without losing the issue record.',
        ],
        'note' => 'Read-only fields are visually muted. Generated issue codes and controlled fields should be left to the system or workflow actions.',
    ],
    'workflow-user-groups/list' => [
        'title' => 'Workflow User Groups Help',
        'purpose' => 'Use this screen to maintain reusable recipient groups for workflow tasks.',
        'points' => [
            'Create groups for teams that regularly receive the same workflow tasks.',
            'Keep group names and descriptions clear so task creators select the correct audience.',
            'Review active groups before sending multi-recipient workflow tasks.',
        ],
        'note' => 'Groups expand into individual recipient tasks when used on the task form.',
    ],
    'workflow-user-groups/form' => [
        'title' => 'Workflow User Group Form Help',
        'purpose' => 'Use this form to create or edit a reusable workflow recipient group.',
        'points' => [
            'Add only active users who should receive tasks for this group.',
            'Keep membership current when staff responsibilities change.',
            'Disable a group instead of reusing it for a different purpose when the old meaning still matters historically.',
        ],
        'note' => 'Workflow user groups help task creators assign work consistently, but the generated tasks are still tracked per recipient.',
    ],
    'system-settings/list' => [
        'title' => 'System Settings Help',
        'purpose' => 'Use this screen to maintain the application settings that control defaults, authentication, sessions, email, monitoring, and environment-specific behaviour.',
        'points' => [
            'Use clear uppercase setting keys with an area prefix such as APP, AUTH, SESSION, SMTP, DEFAULT, FIN, or MON.',
            'Group settings into categories so operational, security, and financial configuration values are easier to review.',
            'Keep descriptions current so administrators can understand what each value controls before changing it.',
        ],
        'note' => 'Changes on this screen can affect login, session handling, default fiscal context, integrations, and other shared application behaviour.',
    ],
    'system-settings/usage-map' => [
        'title' => 'System Settings Usage Map Help',
        'purpose' => 'Use this screen to see where each system setting is referenced in the application codebase.',
        'points' => [
            'Review usage before renaming or retiring a setting key.',
            'Use the map to distinguish actively used settings from catalogue entries that may be obsolete.',
            'Follow up any unexpected usage before changing sensitive settings such as authentication, sessions, URLs, or email.',
        ],
        'note' => 'The usage map is a diagnostic aid. It helps explain code references but does not change settings by itself.',
    ],
    'emailqueue/index' => [
        'title' => 'Email Queue Help',
        'purpose' => 'Use this screen to review queued email activity, send due pending messages, and manage messages that need to be resent, removed, or restored.',
        'points' => [
            'Filter by status, search text, and row count to narrow the queue before selecting messages.',
            'Use Send Queued Emails to process pending messages that are due to send.',
            'Use Queue Selected for Resend only when a sent, failed, or cancelled message should be returned to pending.',
            'Use Remove Selected to stop accidental pending messages from being sent, then Restore Selected if they need to return to their previous status.',
        ],
        'note' => 'Removed queue items are intentionally held out of sending. Restoring a removed item should return it to the status it had before removal where that status is available.',
    ],
    'email-templates/list' => [
        'title' => 'Email Templates Help',
        'purpose' => 'Use this screen to maintain reusable email content for onboarding and application notifications.',
        'points' => [
            'Keep template keys stable because application workflows refer to them by key.',
            'Use the token list on the edit screen to personalise content without hard-coding values.',
            'Disable a template only when the related email flow should use its built-in fallback or be held for review.',
        ],
        'note' => 'The new-user invite workflow uses USER_WELCOME_INVITE when an administrator creates a user with email invitation onboarding.',
    ],
    'email-templates/form' => [
        'title' => 'Email Template Form Help',
        'purpose' => 'Use this screen to edit the subject, HTML body, plain text body, status, and description for an email template.',
        'points' => [
            'Use uppercase template keys with underscores, for example USER_WELCOME_INVITE.',
            'Keep the HTML body readable and include a plain text version for clients that do not render HTML.',
            'Preview token spelling carefully before saving because unmatched tokens are left in the email content.',
        ],
        'note' => 'Template changes apply to future queued emails. Messages already in the queue keep the content they were queued with.',
    ],
    'application-log/index' => [
        'title' => 'Application Log Help',
        'purpose' => 'Use this screen to review application log entries when tracing errors, warnings, operational events, or recent user-facing failures.',
        'points' => [
            'Choose the relevant log file first, then filter or search for the time window and message you need.',
            'Expand a log entry when you need more detail before moving to a related diagnostic screen.',
            'Download the selected log when you need to share the evidence outside the application.',
        ],
        'note' => 'Logs are diagnostic records. They help explain what happened but should be paired with the matching screen, user action, or queue entry before making changes.',
    ],
    'diagnostics/index' => [
        'title' => 'Diagnostics Help',
        'purpose' => 'Use this screen to check core runtime services and run controlled diagnostic actions for troubleshooting.',
        'points' => [
            'Review database, logging, and mail-test results before investigating screen-specific behaviour.',
            'Use forced-error actions only when intentionally validating error handling and log capture.',
            'Follow any failed check into the matching health, log, or configuration screen.',
        ],
        'note' => 'Diagnostic actions can create test log entries or emails, so use them deliberately in shared environments.',
    ],
    'health/index' => [
        'title' => 'Health Check Help',
        'purpose' => 'Use this screen to confirm whether key application services are reachable and reporting healthy status.',
        'points' => [
            'Refresh the health check before relying on a previous result.',
            'Use Ping for a lightweight service response check.',
            'Open Diagnostics when a health item needs deeper investigation.',
        ],
        'note' => 'A healthy result means the checked service responded at that moment. It does not guarantee every downstream workflow is configured correctly.',
    ],
    'strategy/index' => [
        'title' => 'Overview Help',
        'purpose' => 'Use this page as the landing screen for the strategic budgeting cycle. It tells you what context you are in, what has been captured, and what to do next.',
        'points' => [
            'Use Start Here when you are still configuring mappings and imports.',
            'Use Maintain Data when you are entering or refining strategic records.',
            'Use Review And Publish when you are validating the version and preparing workflow submission.',
        ],
        'note' => 'The total strategic budget shown here is the sum of active activity budget lines in the active fiscal year and version.',
    ],
    'strategy/framework-guide' => [
        'title' => 'Strategic Budget Framework Guide Help',
        'purpose' => 'Use this guide as the recommended high-level order for preparing the Strategic Budget Framework.',
        'points' => [
            'Start with base setup, source mappings, and clean dimensions before entering planning detail.',
            'Prepare and review Funding Lodgements before setting final ceilings, because lodgements capture demand and ceilings represent approved fiscal control.',
            'Use readiness checks and reports at the end to confirm the framework is complete before workflow approval.',
        ],
        'note' => 'Indicative ceilings can be used early as planning guidance, but final ceilings should normally follow funding review and approval.',
    ],
    'strategy-config/segment-mapping' => [
        'title' => 'Segment Mapping Help',
        'purpose' => 'This screen tells the strategic module which CBMS segments represent each strategic dimension for this client and fiscal year.',
        'points' => [
            'Map dimensions like Program, SubProgram, Economic, Funding Type, and Funding Source before importing records.',
            'Leave optional dimensions unmapped when the client maintains them directly in Strategic Budgeting.',
            'Review mappings carefully because dimension imports and source-backed lists depend on them.',
        ],
        'note' => 'Mappings can differ by client, so do not assume a segment number like Program equals segment 4 in every environment.',
    ],
    'strategy-config/import-dashboard' => [
        'title' => 'Import Dimensions Help',
        'purpose' => 'This dashboard shows how much of each strategic dimension has been brought into the strategic module from source segments.',
        'points' => [
            'Use Import on mapped dimensions to create missing records.',
            'Use Manage to review or refine imported records after the record exists.',
            'Look at missing counts before moving into performance, delivery, and reporting screens.',
        ],
        'note' => 'A primary import card does not replace maintenance. It helps create the first Strategy records for the active fiscal year.',
    ],
    'strategy-config/resolution-check' => [
        'title' => 'Source Resolution Check Help',
        'purpose' => 'Use this diagnostic screen to confirm whether a mapped strategic dimension can be resolved back to the expected source segment value.',
        'points' => [
            'Enter the strategic dimension, data object code, and segment code you want to test.',
            'Use the result to confirm whether Strategic Segment Mapping, DataObjectCode, SegmentNo, and SegmentCode line up with tblSegmentValues.',
            'Run this check when imports create no records, parent links look wrong, or a mapped source value is not appearing on a Strategy screen.',
        ],
        'note' => 'This screen does not import or change data. It only helps explain why a source-backed strategic value can or cannot be found.',
    ],
    'strategy-config/custom-attributes' => [
        'title' => 'Strategic Custom Attributes Help',
        'purpose' => 'Use this screen to define extra client-specific fields for strategic dimensions without changing the core Strategy tables.',
        'points' => [
            'Create attributes only for information the client needs to capture consistently on a strategic dimension.',
            'Choose the target dimension, data type, required flag, and display order so data-entry screens can render the field correctly.',
            'Use list-type attributes when users should choose from controlled options rather than typing free text.',
        ],
        'note' => 'Custom attributes extend Strategy records such as programs, projects, objectives, outputs, and activities. They do not create new source segment values or replace Strategic Segment Mapping.',
    ],
    'strategy-config/custom-attribute-form' => [
        'title' => 'Custom Attribute Setup Help',
        'purpose' => 'Use this form to create or refine one extra field for a selected strategic dimension.',
        'points' => [
            'Set the dimension first so the attribute appears only where it is relevant.',
            'Pick the data type carefully because it controls validation and how the field appears to users.',
            'Use required and active flags deliberately so incomplete or retired fields do not disrupt data entry.',
        ],
        'note' => 'For LIST attributes, save the attribute first, then maintain its selectable options from the options screen.',
    ],
    'strategy-config/custom-attribute-options' => [
        'title' => 'Custom Attribute Options Help',
        'purpose' => 'Use this screen to maintain the allowed choices for one list-type strategic custom attribute.',
        'points' => [
            'Create one option for each value users should be able to select.',
            'Keep option codes stable because stored Strategy records may refer to them.',
            'Archive options that should no longer be used rather than changing their meaning.',
        ],
        'note' => 'Options apply only to LIST attributes. Text, number, date, and yes/no attributes do not need option rows.',
    ],
    'strategy-config/custom-attribute-option-form' => [
        'title' => 'Custom Attribute Option Setup Help',
        'purpose' => 'Use this form to create or edit one selectable value for a list-type strategic custom attribute.',
        'points' => [
            'Use a short stable option code for storage and reporting.',
            'Use the label for the wording users should see on forms.',
            'Set display order and active status so current choices appear predictably on data-entry screens.',
        ],
        'note' => 'Changing an option label is usually safe, but changing the code can affect existing records and reports.',
    ],
    'strategy-config/fiscal-assumptions' => [
        'title' => 'Fiscal Assumptions Help',
        'purpose' => 'Use this screen to maintain fiscal planning assumptions for the active fiscal year and version, such as inflation rates used by MTFF projection helpers.',
        'points' => [
            'Create one active assumption for each planning driver the Strategy module should reuse in calculations or helper tools.',
            'Keep assumption codes stable because projection screens and reports may look them up by code, such as INFLATION_RATE.',
            'Use notes to document the source, approval basis, or policy rationale behind an assumption value.',
        ],
        'note' => 'Fiscal assumptions support planning helpers and projections. They do not replace approved ceilings, resource envelope entries, or final budget amounts.',
    ],
    'strategy-config/phasing-profiles' => [
        'title' => 'Custom Phasing Profiles Help',
        'purpose' => 'Use this screen to define reusable phasing patterns that spread a resource envelope amount across BP1 to BP12.',
        'points' => [
            'Create profiles for common timing patterns such as even monthly, front-loaded, seasonal, or grant-disbursement schedules.',
            'Enter relative weights rather than final amounts; the resource envelope helper normalizes the weights to the line amount being phased.',
            'Assign default phasing profiles to funding types when new resource envelope lines should start with a predictable phasing method.',
        ],
        'note' => 'Custom phasing profiles are calculation helpers for spreading amounts over periods. They do not create funding, change ceilings, or approve budget values.',
    ],
    'strategy-performance/strategic-pillars' => [
        'title' => 'Strategic Pillars Help',
        'purpose' => 'Use this screen to maintain the top-level pillars of the strategic results framework for the active planning cycle.',
        'points' => [
            'Create one pillar for each high-level policy or development area that goals and objectives should sit underneath.',
            'Keep pillar codes stable because goals, readiness checks, reports, and historical references may rely on them.',
            'Review inactive or duplicate pillars before building out goals, objectives, indicators, outputs, and activities.',
        ],
        'note' => 'Strategic pillars organize the planning framework. They are not source segment values and do not by themselves create budgets or resource envelope lines.',
    ],
    'strategy-performance/strategic-pillar-form' => [
        'title' => 'Strategic Pillar Setup Help',
        'purpose' => 'Use this form to create or refine one top-level strategic pillar.',
        'points' => [
            'Use a short stable code that can be recognized in reports and framework references.',
            'Use the name and description to make the policy intent clear before linking goals beneath the pillar.',
            'Archive a pillar only after checking whether goals or downstream records still depend on it.',
        ],
        'note' => 'A pillar should describe a broad strategic direction. More detailed outcomes belong in goals, objectives, indicators, outputs, and activities.',
    ],
    'strategy-performance/goals' => [
        'title' => 'Goals Help',
        'purpose' => 'Use this screen to maintain the strategic goals that sit below pillars and above detailed objectives in the results framework.',
        'points' => [
            'Create goals for high-level outcomes or commitments such as SDGs, national development goals, government priorities, or client-specific goal types.',
            'Link goals to strategic pillars where possible so the framework can be reviewed from broad policy area down to objective detail.',
            'Review inactive, duplicate, or unlinked goals before creating objectives and indicators underneath them.',
        ],
        'note' => 'Goals organize strategic intent. They do not hold budget lines directly; budget and performance detail is captured further down through objectives, outputs, activities, submissions, and resource envelopes.',
    ],
    'strategy-performance/goal-form' => [
        'title' => 'Goal Setup Help',
        'purpose' => 'Use this form to create or refine one strategic goal and optionally align it to a strategic pillar.',
        'points' => [
            'Use the goal type to classify whether the goal is an SDG, national plan goal, government priority, or another framework category.',
            'Choose a strategic pillar when the goal should contribute to a broader policy area.',
            'Keep the code stable because objectives, reports, and readiness checks may use it as a framework reference.',
        ],
        'note' => 'A goal should describe a high-level outcome or commitment. Specific measurable results belong in objectives and indicators.',
    ],
    'integration-admin/systems' => [
        'title' => 'Integration Systems Help',
        'purpose' => 'Use this screen to register each external finance platform once before creating import or export interfaces against it.',
        'points' => [
            'Create one system per real platform, environment, or credential boundary.',
            'Keep system codes stable because adapters and downstream interfaces refer to them.',
            'Add interfaces only after the system connection details and ownership are clear.',
        ],
        'note' => 'The system record describes the external platform itself. The actual data flows are maintained separately as interfaces.',
    ],
    'integration-admin/system-form' => [
        'title' => 'Integration System Setup Help',
        'purpose' => 'Use this form to define the external platform identity, connection shape, and credential reference for one integration system.',
        'points' => [
            'Set the code, name, and environment first so the system is easy to recognise later.',
            'Use credential reference rather than storing secrets directly in the form.',
            'Keep shared headers and notes focused on connection behaviour that downstream interfaces will reuse.',
        ],
        'note' => 'This form defines the target platform. Interface-specific endpoints, mappings, and execution rules belong on the interface screen.',
    ],
    'integration-admin/interfaces' => [
        'title' => 'Integration Interfaces Help',
        'purpose' => 'Use this screen to manage each concrete import or export contract between CBMS and an external finance system.',
        'points' => [
            'Create one interface per data flow, such as daily actuals import or approved budget export.',
            'Use filters to review interfaces by system, direction, and status.',
            'Open Test Export for outbound interfaces when you want to preview extract and payload behaviour safely.',
        ],
        'note' => 'Interfaces describe individual data flows. Multiple interfaces can sit under the same external system.',
    ],
    'integration-admin/interface-form' => [
        'title' => 'Integration Interface Setup Help',
        'purpose' => 'Use this form to configure one import or export contract, including context requirements, endpoint details, and mapping metadata.',
        'points' => [
            'Define the owning system and direction first so the rest of the form reflects the right execution pattern.',
            'Use mapping configuration to document the source object and field translation logic.',
            'Use output profiles when the same source data may need more than one outbound format later.',
        ],
        'note' => 'This form defines the contract and metadata. Live execution behaviour can then be built against that stable configuration.',
    ],
    'integration-admin/runs' => [
        'title' => 'Integration Run History Help',
        'purpose' => 'Use this log to review recent integration attempts, their context, and their outcomes across systems and interfaces.',
        'points' => [
            'Filter by system, interface, or status when troubleshooting a specific integration path.',
            'Open the run detail screen to review payloads, counts, and summary messages for a specific run.',
            'Use this screen as the operational history for dry runs first, then real integrations later.',
        ],
        'note' => 'Run history becomes more valuable as more interfaces move from preview mode into real scheduled or manual execution.',
    ],
    'integration-admin/test-export' => [
        'title' => 'Test Export Runner Help',
        'purpose' => 'Use this runner to preview the source extract and outbound payload for one export interface without calling the real finance endpoint.',
        'points' => [
            'Enter the fiscal, version, and scope context you want to test.',
            'Review source rows, mapped rows, and the outbound payload preview together before sharing output with the finance team.',
            'Use the full JSON, CSV, and review package downloads when you need to inspect or circulate the result outside the browser.',
        ],
        'note' => 'This runner is safe preview mode. It is designed to validate extraction, scope expansion, and payload shaping before live endpoint delivery exists.',
    ],
    'integration-admin/run-detail' => [
        'title' => 'Integration Run Detail Help',
        'purpose' => 'Use this screen to review one run in detail, including payloads, counts, context, and governance metadata.',
        'points' => [
            'Check the run context first to confirm the fiscal year, version, scope, and interface are correct.',
            'Review request and response payloads when validating mapping output or future endpoint behaviour.',
            'Use the downloadable summary when you need a lightweight record for review or audit discussions.',
        ],
        'note' => 'This detail view is the documented record for one integration attempt, whether it was a preview, test run, or future live execution.',
    ],
    'reports/catalogue' => [
        'title' => 'Report Catalogue Help',
        'purpose' => 'Use this catalogue to find the formal SSRS reports you can run from CBMS with a standard parameter screen and audit trail.',
        'points' => [
            'Filter by module or search text when the report library starts to grow.',
            'Open Run on a report to review the required context and preview the SSRS launch details before opening the output.',
            'Use Report Run History to review what was launched and with which parameters.',
        ],
        'note' => 'The catalogue manages the CBMS launch experience. The final printable layout is rendered by SSRS.',
    ],
    'reports/run' => [
        'title' => 'Run Report Help',
        'purpose' => 'Use this screen to supply fiscal, version, scope, date, and output format parameters before launching one formal SSRS report.',
        'points' => [
            'Preview the launch first so you can confirm the resolved SSRS path and parameter combination.',
            'Use the same context values users would expect to see on the final printed report.',
            'Open the SSRS report in a new tab only after the preview shows a valid launch URL.',
        ],
        'note' => 'This runner validates the launch inputs inside CBMS and records the run. SSRS handles the final PDF, Excel, or interactive rendering.',
    ],
    'reports/history' => [
        'title' => 'Report Run History Help',
        'purpose' => 'Use this log to review previous report launches, their context, and the output format used.',
        'points' => [
            'Filter by module, status, or search text when tracking a specific report launch.',
            'Open the detail screen to inspect the exact payload and SSRS URL generated for one run.',
            'Use the history to confirm which formal reports were launched by users and in what context.',
        ],
        'note' => 'This screen records the CBMS-side launch history. It does not replace SSRS server execution logs.',
    ],
    'reports/run-detail' => [
        'title' => 'Report Run Detail Help',
        'purpose' => 'Use this screen to inspect one logged report launch in detail, including the launch URL and the exact parameters passed from CBMS.',
        'points' => [
            'Review the run payload when validating a new report definition or troubleshooting a parameter mapping issue.',
            'Compare the fiscal year, version, scope, and dates to what the final SSRS output shows.',
            'Use the linked report definition when you need to refine the SSRS path, formats, or required fields.',
        ],
        'note' => 'Run detail is especially useful while building out the first wave of formal printable reports.',
    ],
    'report-admin/definitions' => [
        'title' => 'Report Definitions Help',
        'purpose' => 'Use this admin screen to maintain the CBMS catalogue of formal SSRS reports.',
        'points' => [
            'Create one definition per formal report layout you want to launch from CBMS.',
            'Keep codes stable because they become the long-term references in run history and governance discussions.',
            'Use the runner from each definition to validate the launch setup before rolling the report out to users.',
        ],
        'note' => 'A report definition stores CBMS launch metadata, not the report layout itself. The actual printable design remains in SSRS.',
    ],
    'report-admin/definition-form' => [
        'title' => 'Report Definition Setup Help',
        'purpose' => 'Use this form to define the SSRS path, permitted output formats, required inputs, and launch metadata for one formal report.',
        'points' => [
            'Set the report code, name, and SSRS path first so the definition has a stable identity.',
            'Use required input flags to make the CBMS parameter screen enforce the right context before launch.',
            'Use parameter configuration JSON only when the SSRS parameter names differ from the standard CBMS names.',
        ],
        'note' => 'This form describes how CBMS launches the report. It does not change the SSRS layout or dataset directly.',
    ],
    'workflow-assignments/list' => [
        'title' => 'Workflow Assignments Help',
        'purpose' => 'Use this screen to route workflow tasks to the right people for the current fiscal context and Org Unit.',
        'points' => [
            'Create one assignment per workflow area, stage, and assignee for the current context.',
            'Assignees shown here already match the required permissions for the selected workflow step.',
            'Use this list screen to review current routing and open a separate form to create or edit one assignment.',
        ],
        'note' => 'Assignments are scoped to the active fiscal year, version, and Org Unit in the current context bar.',
    ],
    'workflow-assignments/form' => [
        'title' => 'Workflow Assignment Setup Help',
        'purpose' => 'Use this form to define one workflow assignment for the current context and Org Unit.',
        'points' => [
            'Choose the workflow area first, then the stage, then assign the appropriate user.',
            'Use Sequence and Primary when more than one person is configured for the same step.',
            'Keep assignments active only while they should continue receiving workflow tasks.',
        ],
        'note' => 'The form uses the active fiscal year, version, and Org Unit from the current context rather than asking you to re-enter them.',
    ],
    'workflow-task-types/list' => [
        'title' => 'Workflow Task Types Help',
        'purpose' => 'Use this register to maintain the workflow task-type catalogue used by workflow tasks, readiness checks, and related workflow reporting.',
        'points' => [
            'Keep the task-type codes stable because tasks and downstream workflow logic depend on them.',
            'Use active filtering to distinguish the live catalogue from retired task types.',
            'Open the form screen to create or edit one catalogue row at a time.',
        ],
        'note' => 'This screen manages the workflow task-type catalogue itself, not individual workflow tasks.',
    ],
    'workflow-task-types/form' => [
        'title' => 'Workflow Task Type Setup Help',
        'purpose' => 'Use this form to create or refine one workflow task-type record.',
        'points' => [
            'Enter a stable code first, then the display name users will recognise.',
            'Keep only the task types active that should remain available for live workflow use.',
            'Avoid casual code renames once tasks already exist against the type.',
        ],
        'note' => 'This form defines one workflow task-type master row for the shared workflow catalogue.',
    ],
    'workflow-task-statuses/list' => [
        'title' => 'Workflow Task Statuses Help',
        'purpose' => 'Use this register to maintain the workflow task-status catalogue used by task lifecycle tracking and workflow reporting.',
        'points' => [
            'Keep the status codes deliberate because workflow behavior and reporting depend on them.',
            'Use active filtering to distinguish current statuses from retired ones.',
            'Open the form screen to create or edit one status record at a time.',
        ],
        'note' => 'This screen manages the shared status catalogue, not the status of one individual workflow task.',
    ],
    'workflow-task-statuses/form' => [
        'title' => 'Workflow Task Status Setup Help',
        'purpose' => 'Use this form to create or refine one workflow task-status record.',
        'points' => [
            'Enter a stable code first, then the display name used across workflow screens.',
            'Keep only the statuses active that should remain available for live use.',
            'Avoid casual code renames once existing workflow tasks rely on the status.',
        ],
        'note' => 'This form defines one workflow task-status master row for the shared workflow catalogue.',
    ],
    'base-config/readiness' => [
        'title' => 'Base Configuration Readiness Help',
        'purpose' => 'Use this dashboard as the gate for the base-configuration setup sequence before wider client testing begins.',
        'points' => [
            'Follow the agreed base-configuration run order: confirm fiscal years, versions, system defaults, and baseline access first, then define Segments, load Segment Values, and finally load Data Object Codes and related scope setup.',
            'Use the action buttons on each readiness row to move straight into the next missing setup area.',
            'Cross-check warnings or blockers with the matching SQL script when you need to confirm whether an issue is data, schema, or context driven.',
        ],
        'note' => 'Primary runbook: testing/inittest-pack/02_phase_configuration/01_initial_system_configuration_instructions.md. Matching SQL verification: testing/inittest-pack/02_phase_configuration/03_base_configuration_readiness_verification.sql.',
    ],
    'fiscal-years/list' => [
        'title' => 'Fiscal Years Help',
        'purpose' => 'Use this register to maintain the fiscal year list used by session context, defaults, readiness checks, and downstream configuration.',
        'points' => [
            'Create the fiscal year baseline before building versions, segment values, or other fiscal-year-scoped setup.',
            'Keep the year label and date range aligned with the client calendar that the implementation team is configuring.',
            'Set the system default fiscal year carefully because it influences the fallback session context when users log in.',
        ],
        'note' => 'This screen manages the fiscal-year register itself. Version-level defaults are maintained on the Versions screen.',
    ],
    'fiscal-years/form' => [
        'title' => 'Fiscal Year Setup Help',
        'purpose' => 'Use this form to create or refine one fiscal year record in the base configuration.',
        'points' => [
            'Enter the fiscal year ID, label, and full start and end dates first.',
            'Keep the fiscal year active when it should remain available in session context and base configuration setup.',
            'Use the system-default option for the fiscal year CBMS will use as the default fiscal year when users first open the application or no other context has been selected.',
        ],
        'note' => 'If this fiscal year will be used as the current implementation baseline, create the required version records immediately after saving it.',
    ],
    'versions/list' => [
        'title' => 'Versions Help',
        'purpose' => 'Use this register to maintain the submission and execution versions available within each fiscal year, including which version is treated as the default for that fiscal year and type.',
        'points' => [
            'Create at least one active default submission version for each active fiscal year that users will use for setup, planning, and review.',
            'Use the fiscal year and version type filters to focus on one baseline at a time, especially when the same fiscal year has both submission and execution versions.',
            'Review the flags carefully. A fiscal-year default controls the default version inside that fiscal year and type, while the system default controls the version CBMS will open when no other context has been selected.',
            'Review base-version links carefully when a version was created from rollover or should inherit lineage from another version.',
        ],
        'note' => 'This screen manages fiscal-year version records, version types, local defaults, and lineage. The system-wide default version is the one marked on the form as the system default context version.',
    ],
    'versions/form' => [
        'title' => 'Version Setup Help',
        'purpose' => 'Use this form to create or refine one version record within a fiscal year, including its type, status, default behavior, and any lineage back to an earlier version.',
        'points' => [
            'Choose the fiscal year and version type first so the version is created in the correct planning context. Version type tells CBMS whether the row is a submission version or an execution version.',
            'Mark only one active version as the default for a given fiscal year and version type. This is the default version users will fall into inside that fiscal year and type.',
            'Use the system-default option for the version CBMS will use as the default version when users first open the application or no other context has been selected.',
            'Use the base fiscal year and base version fields when this version should inherit lineage from an earlier approved or rolled-over version.',
        ],
        'note' => 'Leave Version ID blank on create if you want the next available version number for that fiscal year. The version marked as system default should also be active and marked as the default for its fiscal year and type.',
    ],
    'version-types/list' => [
        'title' => 'Version Types Help',
        'purpose' => 'Use this register to maintain the shared version-type catalogue that separates submission and execution versions.',
        'points' => [
            'Keep the version-type codes stable because version rows, readiness checks, and downstream logic depend on them.',
            'Use active filtering to distinguish the live catalogue from retired version types.',
            'Open the form screen to create or edit one version-type row at a time.',
        ],
        'note' => 'This screen manages the version-type catalogue itself, while actual fiscal-year version rows are maintained on the Versions screen.',
    ],
    'version-types/form' => [
        'title' => 'Version Type Setup Help',
        'purpose' => 'Use this form to create or refine one version-type record in the shared catalogue.',
        'points' => [
            'Enter a stable code first, then the user-facing name and a short description of when the type should be used.',
            'Keep only the version types active that should remain available for live version maintenance.',
            'Avoid casual code renames once existing version rows already depend on the type.',
        ],
        'note' => 'This form defines the version-type master row, not the individual versions that use it.',
    ],
    'dataobject-types/list' => [
        'title' => 'Data Object Types Help',
        'purpose' => 'Use this register to maintain the shared catalogue of organisational type rows that sit behind Data Object Codes.',
        'points' => [
            'Keep the type names stable because Data Object Codes, hierarchy logic, and readiness checks depend on them.',
            'Use level and container settings carefully because they influence which codes can sit above or below each other in the hierarchy.',
            'Open the form screen to create or edit one type row at a time before loading or refining Data Object Codes.',
        ],
        'note' => 'This screen manages the type catalogue itself. The actual organisational codes that use these types are maintained on the Data Object Codes screen.',
    ],
    'dataobject-types/form' => [
        'title' => 'Data Object Type Setup Help',
        'purpose' => 'Use this form to create or refine one data object type record in the shared organisational structure catalogue.',
        'points' => [
            'Enter the type name first, then set the hierarchy level that determines where the type sits in the structure.',
            'Use Segment No only when this type maps cleanly to one source segment in the client structure.',
            'Leave the container option enabled only for types that should be allowed to hold child Data Object Codes.',
        ],
        'note' => 'Changes here affect how the Data Object Codes screen interprets hierarchy depth and parent eligibility, so update type structure fields carefully.',
    ],
    'currencies/list' => [
        'title' => 'Currencies Help',
        'purpose' => 'Use this register to maintain the shared currency master that supports version setup and exchange-rate maintenance.',
        'points' => [
            'Keep the 3-letter code aligned with the currency code used elsewhere in CBMS.',
            'Use one active system-default currency as the baseline application currency.',
            'Open the Currency Rates screen after the currency master exists and active currencies are in place.',
        ],
        'note' => 'This screen manages the master list of currencies, not the dated exchange rates between them.',
    ],
    'currencies/form' => [
        'title' => 'Currency Setup Help',
        'purpose' => 'Use this form to create or refine one currency in the shared master list.',
        'points' => [
            'Enter the 3-letter code and descriptive name first.',
            'Use decimal places and sort order to keep later data entry and dropdown displays consistent.',
            'Set the system-default flag carefully because only one currency should hold that role.',
        ],
        'note' => 'Deactivate a currency only when it should no longer appear in active setup lists.',
    ],
    'currency-rates/list' => [
        'title' => 'Currency Rates Help',
        'purpose' => 'Use this register to maintain dated exchange rates between active currencies.',
        'points' => [
            'Filter by pair or status when reviewing a specific maintained rate stream.',
            'Use consistent rate types such as SPOT or BUDGET when more than one rate family is required.',
            'Review the latest rate date before relying on a pair in wider configuration or later calculations.',
        ],
        'note' => 'This screen manages the maintained exchange-rate history, not the master currency list itself.',
    ],
    'currency-rates/form' => [
        'title' => 'Currency Rate Setup Help',
        'purpose' => 'Use this form to create or refine one dated exchange rate between two currencies.',
        'points' => [
            'Set the pair direction explicitly because USD to LSL is a different stored rate from LSL to USD.',
            'Use one date and one rate type per maintained row to avoid ambiguity.',
            'Keep the rate value positive and document the source when the rate comes from an external publication or policy instruction.',
        ],
        'note' => 'Create the currencies first on the Currencies screen before entering exchange rates here.',
    ],
    'segment-values/list' => [
        'title' => 'Segment Values Help',
        'purpose' => 'Use this register to review and maintain source segment values that support base configuration and downstream imports.',
        'points' => [
            'Filter by fiscal year, segment, Org Unit, or search text to narrow the register quickly.',
            'Use Create Segment Value for individual maintenance and Upload Excel for larger batches.',
            'Review parent links carefully because hierarchies and downstream dimension setup may depend on them.',
        ],
        'note' => 'This screen manages source segment values directly rather than the Strategy records created from them.',
    ],
    'segment-values/form' => [
        'title' => 'Segment Value Setup Help',
        'purpose' => 'Use this form to create or refine one source segment value record.',
        'points' => [
            'Choose the fiscal year, Org Unit, and segment first so the value is scoped correctly.',
            'Use parent fields only when the source structure genuinely has a parent-child relationship to preserve.',
            'Keep codes and names aligned with the source structure used by the client.',
        ],
        'note' => 'This form maintains the source-side value, not the imported Strategy dimension record.',
    ],
    'segment-values/upload' => [
        'title' => 'Segment Values Upload Help',
        'purpose' => 'Use this upload screen to create or update larger batches of source segment values from an Excel workbook.',
        'points' => [
            'Download the template first so the workbook column order and names stay correct.',
            'Use Override Fiscal Year when the whole workbook should be loaded into one fiscal year.',
            'Uploads match on fiscal year, Org Unit, segment number, and segment code to update existing rows cleanly.',
        ],
        'note' => 'Upload is best for controlled batch maintenance. Use the individual form when you only need to correct one row.',
    ],
    'segments/list' => [
        'title' => 'Segments Help',
        'purpose' => 'Use this register to review and maintain the base segment master structure used across CBMS.',
        'points' => [
            'Filter by dimension, group, or search text to narrow the register quickly.',
            'Open a segment to maintain its lengths, ranges, grouping, and attribute definitions.',
            'Review value counts to understand where each segment is already in use by source values.',
        ],
        'note' => 'This screen manages the segment master definition itself, not the fiscal-year segment values maintained on the Segment Values screen.',
    ],
    'segments/form' => [
        'title' => 'Segment Setup Help',
        'purpose' => 'Use this form to create or refine one segment definition in the base configuration.',
        'points' => [
            'Set the segment identity and naming fields first so downstream configuration remains clear.',
            'Use length, range, delimiter, and parent settings to reflect the structure used by the client.',
            'Define attribute names only where the segment genuinely carries extra source-side attributes.',
        ],
        'note' => 'Changes here affect how source segment values and downstream configuration behave, so segment definitions should remain stable once in use.',
    ],
    'dataobjectcodes/index' => [
        'title' => 'Data Object Codes Help',
        'purpose' => 'Use this register to review and maintain the organisational code structure used across CBMS.',
        'points' => [
            'Use filters to narrow the register before opening a code, exporting, or creating a new record.',
            'Review parent, type, and status values carefully because they affect hierarchy, scope, and downstream configuration.',
            'Open a code to maintain its core details and any configured custom attributes.',
        ],
        'note' => 'Data Object Codes underpin Org Unit scope and access across the application, so structural changes should be made carefully.',
    ],
    'dataobjectcodes/create' => [
        'title' => 'Data Object Code Setup Help',
        'purpose' => 'Use this form to create one Data Object Code record, including hierarchy, type, status, and custom attributes.',
        'points' => [
            'Complete the General tab first so the code, name, parent, and type are set correctly.',
            'Use the Attributes tab only for the extra fields configured for this Data Object Code structure.',
            'Keep code formats and parent links consistent with the organisational structure used by the client.',
        ],
        'note' => 'Changes here affect Org Unit structure and scope behavior, so new codes should be created carefully and consistently.',
    ],
    'dataobjectcodes/edit' => [
        'title' => 'Data Object Code Setup Help',
        'purpose' => 'Use this form to refine one Data Object Code record, including hierarchy, type, status, and custom attributes.',
        'points' => [
            'Review parent and type changes carefully because they can affect hierarchy behavior and scope interpretation.',
            'Use the Attributes tab only for the extra fields configured for this Data Object Code structure.',
            'Keep status aligned to whether the Org Unit should remain active in the application.',
        ],
        'note' => 'Data Object Code maintenance affects Org Unit structure across the system, so edits should be made carefully and consistently.',
    ],
    'users/list' => [
        'title' => 'Users Help',
        'purpose' => 'Use this register to manage user accounts, account status, and access-related maintenance.',
        'points' => [
            'Use filters to narrow the register before editing, unlocking, exporting, or uploading users.',
            'Open a user to maintain profile details, assigned roles, and account access information.',
            'Use the unlock action when a user needs login access restored without changing the rest of the account record.',
        ],
        'note' => 'User accounts control sign-in and role assignment. The permissions users receive come from their assigned roles rather than from this register directly.',
    ],
    'users/edit' => [
        'title' => 'User Setup Help',
        'purpose' => 'Use this form to create or maintain one user account, including profile details and assigned roles.',
        'points' => [
            'Use the Edit tab for core identity and account status details.',
            'Use the Roles tab to assign business-area roles grouped by functional area.',
            'Use the Data Object Access tab to review the user-specific Data Object Code grants and inherited access for the current fiscal year.',
            'Use the Account & Access tab to review the account-level access view without leaving the user screen.',
        ],
        'note' => 'Role assignment drives permission access across the application. Data Object Code grants are reviewed here but maintained from Organisation & Chart of Accounts.',
    ],
    'roles/list' => [
        'title' => 'Roles & Permissions Help',
        'purpose' => 'Use this register to review the role catalogue by functional area and understand how roles are assigned across the system.',
        'points' => [
            'Review roles by functional area so the access model matches the business structure of the menu and modules.',
            'Check assignment counts to see which roles are actively in use by users.',
            'Check permission counts to confirm whether a role is already carrying the expected access bundle.',
        ],
        'note' => 'Roles group permissions into business-friendly access bundles. User access is granted by assigning the right role rather than linking permissions directly to users.',
    ],
    'roles/edit' => [
        'title' => 'Role Setup Help',
        'purpose' => 'Use this form to create or refine one role in the access-control catalogue.',
        'points' => [
            'Use clear role names that describe the business purpose of the role.',
            'Keep the role active only while it should remain available for assignment.',
            'Use the broader roles and permissions screens together so the catalogue stays aligned to the functional-area access model.',
        ],
        'note' => 'This form maintains the role record itself. Permission assignment and user assignment are managed through the wider access-control screens.',
    ],
    'access-matrix/index' => [
        'title' => 'Access Matrix Help',
        'purpose' => 'Use this matrix to review which screens and routes require which roles or permissions across the application.',
        'points' => [
            'Use functional area grouping to review access in business terms rather than as one long technical list.',
            'Check controller and menu alignment when diagnosing why a user can or cannot open a screen.',
            'Use this screen together with Users and Roles when validating new access assignments.',
        ],
        'note' => 'The matrix is a review tool. It helps administrators understand access requirements before changing user-role assignments.',
    ],
    'sessions/index' => [
        'title' => 'Active Sessions Help',
        'purpose' => 'Use this register to review active user sessions and terminate a session when administrative intervention is needed.',
        'points' => [
            'Review login time, last activity, expiry time, and current session status for each active session.',
            'Use force logout when a session needs to be terminated for security or support reasons.',
            'Use this screen alongside Users and Roles when diagnosing account-access issues.',
        ],
        'note' => 'Ending a session signs the user out of the application and requires them to log in again.',
    ],
    'training/dashboard' => [
        'title' => 'Training Dashboard Help',
        'purpose' => 'Use this dashboard to see the training and certifications assigned to you, your current progress, and the next item you should work on.',
        'points' => [
            'Review assigned scenarios and certifications only; catalogue items are shown elsewhere for trainers and configuration users.',
            'Use the progress indicators to see what is not started, in progress, completed, or certified.',
            'Launch the next assigned scenario or certification directly from the dashboard.',
        ],
        'note' => 'The dashboard is learner-focused. Training administrators use Training Summary, Training Operations, and Certification Results for wider oversight.',
    ],
    'training/users' => [
        'title' => 'Create New User Training Help',
        'purpose' => 'Use this runner to launch and monitor the guided Create New User training scenario.',
        'points' => [
            'Start the scenario here, then open the target Users screen when prompted.',
            'The guided overlay highlights the next field or button and advances the scenario as steps are completed.',
            'Use this runner to restart or stop the scenario after trying the guided flow.',
        ],
        'note' => 'The runner is now part of the wider training catalogue and can be used alongside the CBMS Fundamentals scenarios.',
    ],
    'training/users-edit' => [
        'title' => 'Edit Existing User Training Help',
        'purpose' => 'Use this runner to practise editing an existing user record and reviewing every tab on the Edit User screen.',
        'points' => [
            'Start the scenario here, then follow the highlighted steps on the Users list and Edit User pages.',
            'The guided overlay will walk you through searching, opening the record, making one profile change, saving it, and reviewing the remaining tabs.',
            'Use this runner to restart or stop the scenario after trying the guided flow.',
        ],
        'note' => 'This scenario builds on the same training framework as the Create New User exercise and helps demonstrate how multi-tab training can work.',
    ],
    'training/runner' => [
        'title' => 'Training Runner Help',
        'purpose' => 'Use this runner to launch one guided training scenario, review the step list, and monitor your progress while the overlay guides you through the live screen.',
        'points' => [
            'Use Start Scenario when beginning from scratch and Restart from Current Step when resuming a partially completed scenario.',
            'Open Guided Screen takes you to the live page where the highlighted training overlay appears.',
            'Use the scenario summary, notes, and trainer controls here to manage the training session without leaving the runner.',
        ],
        'note' => 'The CBMS Fundamentals scenarios use this generic runner so shared-navigation training can launch cleanly from the catalogue.',
    ],
    'training/scenarios' => [
        'title' => 'Training Scenarios Help',
        'purpose' => 'Use this screen to review and launch the guided scenarios assigned to your user account.',
        'points' => [
            'Review module, audience, display order, step count, and current progress before launching a scenario.',
            'Use Open Scenario to start a new exercise or Resume Scenario to continue an in-progress one.',
            'Use the summary link when you want to review persisted training outcomes rather than launch a scenario.',
        ],
        'note' => 'This is the trainee launch view. Training Configuration users maintain the master catalogue separately.',
    ],
    'training/summary' => [
        'title' => 'Training Summary Help',
        'purpose' => 'Use this screen to review persisted training progress and completion across assigned users and scenarios.',
        'points' => [
            'Filter by user, module, status, or scenario to find specific training activity.',
            'Use progress, attempt number, and completion times to understand how far a user has progressed through the scenario.',
            'Review this screen after training sessions to confirm who completed the scenario successfully.',
        ],
        'note' => 'This is an administration view. Learners should use Training Dashboard for their own assigned work.',
    ],
    'training-admin/scenarios' => [
        'title' => 'Training Catalogue Help',
        'purpose' => 'Use this screen to maintain the master list of guided training scenarios that drive the training overlay and runner.',
        'points' => [
            'Create one scenario record for each guided exercise you want users to complete.',
            'Use the action buttons to move from scenario details into step maintenance and multilingual translations.',
            'Keep scenario codes stable because training progress and launch links depend on them.',
        ],
        'note' => 'This is the administrative catalogue, not the trainee launch screen. Trainees should use Training Scenarios instead.',
    ],
    'training-admin/scenario-form' => [
        'title' => 'Training Scenario Setup Help',
        'purpose' => 'Use this form to maintain the core metadata for one training scenario, including its title, runner route, prerequisites, and sample templates.',
        'points' => [
            'Keep Scenario Code stable once a scenario is in use so progress history and launch links stay aligned.',
            'Use prerequisites and sample templates to provide clearer guidance in the runner and overlay.',
            'Maintain the actual ordered steps on the dedicated Steps screen rather than overloading the scenario form.',
        ],
        'note' => 'This screen defines the scenario itself. Step-by-step flow and multilingual wording are maintained separately.',
    ],
    'training-admin/steps' => [
        'title' => 'Training Steps Help',
        'purpose' => 'Use this register to maintain the ordered step flow for one training scenario.',
        'points' => [
            'Review the route, target element, and completion mode for each step before launching the scenario for users.',
            'Open a step to refine its highlight target, sample mapping, or instruction text.',
            'Archive a step when it should no longer be part of the scenario flow.',
        ],
        'note' => 'Target element IDs must match the real field, tab, or button IDs on the live screen or the guided highlight will not land correctly.',
    ],
    'training-admin/step-form' => [
        'title' => 'Training Step Setup Help',
        'purpose' => 'Use this form to define how one training step behaves on the live application screen.',
        'points' => [
            'Set the route and target element so the overlay can find the correct control on the screen.',
            'Choose the completion mode that best matches how the trainee is expected to interact with the control.',
            'Use sample keys when the step should display suggested values or compare against a scenario-specific target value.',
        ],
        'note' => 'The step title and instruction are the base language values. Use Training Translations for language-specific wording.',
    ],
    'training-admin/translations' => [
        'title' => 'Training Translations Help',
        'purpose' => 'Use this screen to maintain translated scenario text, translated step instructions, and translated sample values for one language.',
        'points' => [
            'Select the scenario and target language first, then update scenario, step, and sample wording in one place.',
            'Leave a translation blank where the base content should continue to be used as the fallback.',
            'Review translated sample values carefully if the examples shown to trainees should differ by language.',
        ],
        'note' => 'This screen maintains multilingual training content only. It does not change the underlying training flow or completion logic.',
    ],
    'training-admin/matrix' => [
        'title' => 'Training Matrix Help',
        'purpose' => 'Use this screen to review the agreed CBMSv21 training path order, role alignment, scenario list, and rollout status from the training matrix document.',
        'points' => [
            'Filter by path or status to focus on the next scenarios that need to be created or validated.',
            'Use the role alignment section to confirm that training coverage matches menu access and permission assignments.',
            'Update TRAINING_SCENARIO_MATRIX.md when the agreed training plan changes, then refresh this screen.',
        ],
        'note' => 'This screen is a planning and governance view. Scenario step content is still maintained in the Training Catalogue screens.',
    ],
    'training-admin/operations' => [
        'title' => 'Training Operations Help',
        'purpose' => 'Use this screen to create training paths, assign training to users, schedule sessions, and resolve operational training issues.',
        'points' => [
            'Create paths to group scenarios into a recommended module sequence.',
            'Assign paths or individual scenarios to users so their Training Dashboard only shows relevant work.',
            'Use sessions and evidence records to support trainer-led activities, attendance, and follow-up.',
        ],
        'note' => 'This is the main trainer administration workspace. It should be limited to Training Administration users.',
    ],
    'training-admin/session-dashboard' => [
        'title' => 'Training Session Dashboard Help',
        'purpose' => 'Use this screen to review one trainer-led session, attendee progress, evidence, and follow-up actions.',
        'points' => [
            'Review the session roster and identify attendees who still need completion or evidence.',
            'Attach evidence where a session requires supporting files, notes, or trainer confirmation.',
            'Use this view after a session to confirm outcomes before reporting completion.',
        ],
        'note' => 'Scenario completion remains user-specific even when training is delivered through a shared session.',
    ],
    'training-admin/validation' => [
        'title' => 'Training Validation Help',
        'purpose' => 'Use this screen to find training configuration issues before users encounter broken or incomplete guided scenarios.',
        'points' => [
            'Review missing targets, inactive routes, duplicate ordering, and other configuration warnings.',
            'Open the scenario or step maintenance screens from each issue to correct the source record.',
            'Run validation after adding new scenarios, translations, paths, or certification-related training content.',
        ],
        'note' => 'Validation helps prevent avoidable training interruptions, but it does not replace testing the guided scenario end to end.',
    ],
    'training-certifications/modules' => [
        'title' => 'Certifications Help',
        'purpose' => 'Use this screen to view the module certifications assigned or available to you and start a final module test.',
        'points' => [
            'Review the module, pass mark, question count, and your latest result before starting.',
            'Start the certification when you are ready to answer the multiple choice questions.',
            'Use the dashboard link to return to your combined training and certification status.',
        ],
        'note' => 'Certification attempts are recorded separately from guided scenario progress.',
    ],
    'training-certifications/admin' => [
        'title' => 'Certification Catalogue Help',
        'purpose' => 'Use this screen to maintain the list of module certifications and their pass requirements.',
        'points' => [
            'Create one certification per module or assessed learning outcome.',
            'Set the pass percentage required before a user is considered certified.',
            'Use the Questions action to maintain the multiple choice question bank for the certification.',
        ],
        'note' => 'This is a configuration screen. Only Training Configuration users should maintain certification definitions.',
    ],
    'training-certifications/form' => [
        'title' => 'Certification Setup Help',
        'purpose' => 'Use this form to create or update a certification definition, including title, module, description, pass percentage, and order.',
        'points' => [
            'Keep the certification code stable once attempts have been recorded.',
            'Use clear module names so dashboard, results, and filters group certifications consistently.',
            'Set an achievable but meaningful pass percentage for final module certification.',
        ],
        'note' => 'Question content is maintained separately on the Certification Questions screen.',
    ],
    'training-certifications/questions' => [
        'title' => 'Certification Questions Help',
        'purpose' => 'Use this screen to maintain the ordered multiple choice questions for one certification.',
        'points' => [
            'Review each question number, active flag, correct answer key, and explanation.',
            'Open a question to edit wording, answer options, correct answer, and feedback.',
            'Keep questions focused on module behaviours, governance rules, and practical use of the screens.',
        ],
        'note' => 'Questions are presented to the trainee during certification attempts and are scored automatically.',
    ],
    'training-certifications/question-form' => [
        'title' => 'Certification Question Setup Help',
        'purpose' => 'Use this form to define one multiple choice certification question and its answer options.',
        'points' => [
            'Write the question so there is one clearly best answer.',
            'Keep option keys consistent with the correct answer key.',
            'Use the explanation to reinforce the learning point after scoring.',
        ],
        'note' => 'Avoid changing the meaning of a question after users have already answered it unless you are intentionally revising the test.',
    ],
    'training-certifications/take' => [
        'title' => 'Take Certification Help',
        'purpose' => 'Use this screen to complete the multiple choice certification test for the selected module.',
        'points' => [
            'Answer every question before submitting the attempt.',
            'Review the module and pass mark before you begin.',
            'Submit the attempt when you are ready for the system to calculate your score.',
        ],
        'note' => 'Submitted attempts are retained for certification history and trainer review.',
    ],
    'training-certifications/result' => [
        'title' => 'Certification Result Help',
        'purpose' => 'Use this screen to review the score, pass result, and feedback for one certification attempt.',
        'points' => [
            'Compare your score with the required pass percentage.',
            'Review incorrect answers and explanations where they are shown.',
            'Return to certifications when you need to retake or continue with another module.',
        ],
        'note' => 'A passed attempt confirms certification for the module according to the configured pass mark.',
    ],
    'training-certifications/results' => [
        'title' => 'Certification Results Help',
        'purpose' => 'Use this administration screen to review certification attempts and outcomes across users and modules.',
        'points' => [
            'Filter by module, certification, user, or pass status to find the results you need.',
            'Review attempt number, submitted date, score, pass mark, and pass result for each attempt.',
            'Use this screen alongside Training Summary when preparing trainer reports.',
        ],
        'note' => 'This screen is intended for Training Administration users, not ordinary learner self-service.',
    ],
    'strategy-config/fiscal-periods' => [
        'title' => 'Fiscal Period Labels Help',
        'purpose' => 'Use this page to name the BP periods and outer years so fiscal screens show real month and year labels instead of technical placeholders.',
        'points' => [
            'Choose the fiscal start month and auto-generate the BP labels first.',
            'Review and adjust any generated month or outer-year label before saving.',
            'These labels are reused across Resource Envelope screens for the active fiscal year.',
        ],
        'note' => 'If this screen is not configured yet, the module falls back to generic labels like BP1 and Outer Year 1.',
    ],
    'strategy-config/rollover' => [
        'title' => 'Fiscal and Version Rollover Help',
        'purpose' => 'Use this screen to create a new strategic submission version from an existing one, or roll a new fiscal year forward with selected strategic setup and planning data copied into it.',
        'points' => [
            'Choose the source fiscal year and source submission version first so you know exactly what baseline you are copying.',
            'Select copy scopes carefully because only the checked fiscal-year and version tables will be cloned into the new target context.',
            'Use the fiscal year rollover when you need a new year and an initial submission version together, and use the version rollover when you only need another version inside the same fiscal year.',
        ],
        'note' => 'Workflow state, approval history, publish requests, attachments, and other runtime records are intentionally not copied by rollover.',
    ],
    'strategy-setup/sectors' => [
        'title' => 'Sector Help',
        'purpose' => 'Sectors are policy groupings used for strategic reporting. They are not the same thing as ministries or DataScope org units.',
        'points' => [
            'Keep sector names broad and policy-oriented, such as Health or Education.',
            'A program should normally belong to one sector for clean MTFF and BSP reporting.',
            'If sectors are segment-backed for this client, use import first and then refine the records if needed.',
        ],
        'note' => 'Sectors are used heavily in sector budget reports, MTFF views, and BSP summaries.',
    ],
    'strategy-setup/sector-form' => [
        'title' => 'Sector Setup Help',
        'purpose' => 'Use this form to define or refine the strategic sector record used for policy grouping and reporting.',
        'points' => [
            'Choose a clear sector name that budget users will recognize.',
            'Keep the description short and focused on the policy grouping.',
            'Archive sectors only when they should no longer be available for strategic use.',
        ],
        'note' => 'Programs should point to one main sector, so sector definitions should stay stable over the planning cycle.',
    ],
    'strategy-setup/funding-types' => [
        'title' => 'Funding Type Help',
        'purpose' => 'Funding Types are the broad categories behind funding sources, such as domestic, grant, or loan.',
        'points' => [
            'Set up funding types before refining funding sources if the client separates those concepts.',
            'Keep funding types broad and stable so detailed sources can sit under them cleanly.',
            'If funding types are mapped from segments, import them first and then refine their records.',
        ],
        'note' => 'Funding Types help standardize reporting across many detailed funding sources.',
    ],
    'strategy-setup/funding-type-form' => [
        'title' => 'Funding Type Setup Help',
        'purpose' => 'Use this form to define one funding type record or refine a source-backed funding type.',
        'points' => [
            'Keep the code and name aligned with how the client classifies broad funding categories.',
            'Use active status to control whether the type remains available for downstream records.',
            'Refine the type here before relying on it in funding source maintenance.',
        ],
        'note' => 'Funding types are broad categories. Detailed source names belong on Funding Sources.',
    ],
    'strategy-setup/programs' => [
        'title' => 'Program Help',
        'purpose' => 'Programs are the main strategic planning units. They connect sector classification, organizational accountability, delivery, performance, and reporting.',
        'points' => [
            'A program should usually have one primary sector and one primary owner DataScope org.',
            'Use linked DataScope orgs for cross-cutting programs rather than duplicating the program.',
            'If programs are mapped from segments, import or configure records before creating subprograms, objectives, outputs, and budgets.',
        ],
        'note' => 'In MTFF terms, one sector and one lead owner keeps reporting and accountability clear while still allowing many linked orgs.',
    ],
    'strategy-setup/program-form' => [
        'title' => 'Program Setup Help',
        'purpose' => 'Use this form to refine the Strategy record for a program. The base code and name may come from segment values, while the strategic fields are maintained here.',
        'points' => [
            'Set one primary sector for the program.',
            'Keep the owner DataScope org as the main accountable institution.',
            'Add extra linked DataScope orgs when the program is cross-cutting across multiple institutions.',
        ],
        'note' => 'The program record is where sector, manager, description, and cross-org participation are brought together.',
    ],
    'strategy-setup/sub-programs' => [
        'title' => 'SubProgram Help',
        'purpose' => 'SubPrograms break a program into more detailed planning units where the client needs extra structure below program level.',
        'points' => [
            'Only use subprograms where they add real planning or reporting value.',
            'Each subprogram should belong to one parent program.',
            'Import subprograms only after their parent program records exist.',
        ],
        'note' => 'Subprograms are optional in the model, but they are useful where the client budgets below program level.',
    ],
    'strategy-setup/sub-program-form' => [
        'title' => 'SubProgram Setup Help',
        'purpose' => 'Use this form to define the subprogram record linked to its parent program.',
        'points' => [
            'Pick the correct parent program first.',
            'Keep subprogram names short and distinct within the parent program.',
            'Use descriptions to explain the narrower planning scope under the parent program.',
        ],
        'note' => 'Subprograms should support planning detail, not duplicate the program name without adding meaning.',
    ],
    'strategy-setup/economic-items' => [
        'title' => 'Economic Item Help',
        'purpose' => 'Economic Items classify the cost of activities for strategic budgeting and MTFF summaries.',
        'points' => [
            'Use economic records to align costing with the client’s economic classification structure.',
            'Import source-backed items first if the client maintains economic classification in segments.',
            'Refine parent and level details where needed for reporting quality.',
        ],
        'note' => 'Economic Items drive expenditure strategy and classification totals in reporting.',
    ],
    'strategy-setup/economic-item-form' => [
        'title' => 'Economic Item Setup Help',
        'purpose' => 'Use this form to define or refine an economic classification record for strategic costing.',
        'points' => [
            'Keep the code aligned with the client’s economic chart.',
            'Use parent and level carefully when the hierarchy matters in reporting.',
            'Keep the item active only if it should remain selectable in activity budgets.',
        ],
        'note' => 'Economic items are the classification backbone of activity budget lines.',
    ],
    'strategy-setup/funding-sources' => [
        'title' => 'Funding Source Help',
        'purpose' => 'Funding Sources identify where money comes from for a strategic activity budget line.',
        'points' => [
            'Set up the broad funding type first if the client uses that distinction.',
            'Use source-backed records to keep names and codes aligned with client funding structures.',
            'Refine descriptions or notes where donors or conditions matter for analysis.',
        ],
        'note' => 'Funding Sources are used directly on activity budgets, so clarity here improves budget analysis later.',
    ],
    'strategy-setup/funding-source-form' => [
        'title' => 'Funding Source Setup Help',
        'purpose' => 'Use this form to refine one funding source record that can later be used on activity budget lines.',
        'points' => [
            'Keep the funding source name recognizable to finance and planning users.',
            'Check the funding type assignment because it affects downstream grouping.',
            'Use notes for donor or conditionality details where they matter.',
        ],
        'note' => 'Funding Sources are operational records used directly during budget costing.',
    ],
    'strategy-performance/objectives' => [
        'title' => 'Objective Help',
        'purpose' => 'Objectives describe what a program or subprogram is trying to achieve.',
        'points' => [
            'Write objectives as outcomes or clear strategic intentions, not as activities.',
            'Attach each objective to the correct program and, if needed, subprogram.',
            'Add indicators after objectives so the program can be measured properly.',
            'Below the objective, some clients use Outputs while others use structural Targets before Activities.',
        ],
        'note' => 'Objectives anchor both the performance branch and the delivery branch of the model.',
    ],
    'strategy-performance/objective-form' => [
        'title' => 'Objective Setup Help',
        'purpose' => 'Use this form to capture one strategic objective under a program or subprogram.',
        'points' => [
            'Keep the objective statement outcome-oriented.',
            'Use policy link and priority fields where the client tracks alignment to higher policy.',
            'Do not use an objective to describe a single activity or budget line.',
        ],
        'note' => 'A good objective explains the intended result, not just the work to be done.',
    ],
    'strategy-performance/indicators' => [
        'title' => 'Indicator Help',
        'purpose' => 'Indicators measure performance against objectives or outputs.',
        'points' => [
            'Use outcome indicators for program-level strategic results.',
            'Use output indicators for deliverables where the client tracks service delivery or production.',
            'Keep definitions and units clear so targets are easy to interpret.',
        ],
        'note' => 'Indicators become much more useful when definitions, data sources, and frequency are completed carefully.',
    ],
    'strategy-performance/indicator-form' => [
        'title' => 'Indicator Setup Help',
        'purpose' => 'Use this form to define one indicator that can later receive targets and be linked into the results framework.',
        'points' => [
            'Choose the right type first: outcome or output.',
            'Write a short name, then use the definition field to remove ambiguity.',
            'Set unit of measure and data source clearly before entering targets.',
        ],
        'note' => 'Targets depend on indicators, so indicator quality has a direct effect on reporting quality.',
    ],
    'strategy-performance/targets' => [
        'title' => 'Target Help',
        'purpose' => 'Targets may be numeric indicator targets or, for some clients, structural target/result nodes in the planning hierarchy.',
        'points' => [
            'Where the client uses indicator targets, enter them after indicators exist and the fiscal context is confirmed.',
            'Where the client uses structural target nodes from source data, keep the source coding and parent links consistent before importing activities.',
            'Check the active version before saving because numeric indicator targets are version-sensitive.',
        ],
        'note' => 'This module supports both numeric indicator targets and structural target nodes, so confirm which target model the client is using.',
    ],
    'strategy-performance/target-form' => [
        'title' => 'Target Setup Help',
        'purpose' => 'Use this form to enter or refine one numeric target for an indicator in the active strategic context.',
        'points' => [
            'Confirm the indicator first.',
            'Use realistic target values that match the planning horizon.',
            'Add notes where changes from baseline or prior versions need explanation.',
        ],
        'note' => 'This screen manages numeric indicator targets, not structural target nodes imported from source hierarchy data.',
    ],
    'strategy-delivery/outputs' => [
        'title' => 'Output Help',
        'purpose' => 'Outputs describe the main deliverables that a program or subprogram will produce where the client uses an output-based delivery hierarchy.',
        'points' => [
            'Outputs should be concrete deliverables, not broad strategic outcomes.',
            'Link each output to the correct program and, where used, subprogram.',
            'Use outputs only where the client genuinely plans around deliverables. Some clients move from Objective to Target to Activity instead.',
        ],
        'note' => 'Outputs remain part of the platform design, but they are an optional branch rather than a mandatory layer for every client.',
    ],
    'strategy-delivery/output-form' => [
        'title' => 'Output Setup Help',
        'purpose' => 'Use this form to define one deliverable under a program or subprogram.',
        'points' => [
            'Use a short output name that describes the deliverable clearly.',
            'If needed, set an output owner org where implementation sits below the main program owner.',
            'Create activities after the output is in place.',
        ],
        'note' => 'Outputs should describe what will be delivered, not the internal tasks used to deliver it.',
    ],
    'strategy-delivery/activities' => [
        'title' => 'Activity Help',
        'purpose' => 'Activities are the concrete actions carried out under the client’s chosen delivery hierarchy.',
        'points' => [
            'Create activities under the correct parent node for the client design, usually an Output or a structural Target.',
            'Use activity type, status, timing, and location to give delivery detail.',
            'After the activity exists, attach one or more activity budget lines to cost it.',
        ],
        'note' => 'Activities are the operational layer where strategic planning turns into specific deliverable work, regardless of whether the parent layer is Output-based or Target-based.',
    ],
    'strategy-delivery/activity-form' => [
        'title' => 'Activity Setup Help',
        'purpose' => 'Use this form to describe one delivery action under the configured parent planning layer.',
        'points' => [
            'Choose the correct parent node first because that controls the planning chain.',
            'Use status and timing fields to support monitoring later.',
            'Record procurement or risk notes where they matter for implementation planning.',
        ],
        'note' => 'Activities become financially meaningful once you add activity budget lines.',
    ],
    'strategy-delivery/budgets' => [
        'title' => 'Activity Budget Help',
        'purpose' => 'Activity Budget lines are the core costing records in the strategic model.',
        'points' => [
            'Each budget line should point to one activity, one economic item, and optionally one funding source.',
            'Use multiple lines when one activity needs to be split across different economic or funding classifications.',
            'Check these records carefully because they drive summary totals, sector reports, and MTFF views.',
        ],
        'note' => 'The total strategic budget comes from active activity budget lines in the active fiscal year and version.',
    ],
    'strategy-delivery/budget-form' => [
        'title' => 'Activity Budget Setup Help',
        'purpose' => 'Use this form to cost one activity line in the active fiscal context.',
        'points' => [
            'Confirm the activity first.',
            'Pick the correct economic item and funding source for the line.',
            'Use notes where the amount needs explanation or where assumptions should be preserved.',
        ],
        'note' => 'Budget lines are the main fact records behind strategic financial reporting.',
    ],
    'strategy-governance/narratives' => [
        'title' => 'BSP Narrative Help',
        'purpose' => 'Narratives store the explanatory paragraphs that will later support BSP and strategic reporting outputs.',
        'points' => [
            'Use sections consistently so narratives can be assembled into reports cleanly.',
            'Keep paragraphs aligned with the active strategic version and the supporting numbers.',
            'Use scoped narratives where a paragraph belongs to one sector, program, or org rather than the whole document.',
        ],
        'note' => 'Narratives turn the tables and numbers into a readable budget statement.',
    ],
    'strategy-governance/narrative-form' => [
        'title' => 'Narrative Setup Help',
        'purpose' => 'Use this form to write or refine one BSP narrative paragraph.',
        'points' => [
            'Choose the correct section first.',
            'Use title and sort order to keep the narrative readable when assembled later.',
            'Keep text aligned with the evidence in reports, risks, and performance records.',
        ],
        'note' => 'This is a curated narrative layer, so analysts can refine text after auto-generated drafts if needed.',
    ],
    'strategy-governance/fiscal-risks' => [
        'title' => 'Fiscal Risk Help',
        'purpose' => 'Fiscal Risks capture exposures that can affect the budget or medium-term fiscal path.',
        'points' => [
            'Record the risk clearly, then estimate likelihood and impact consistently.',
            'Use fiscal exposure where the client has a monetary estimate.',
            'Link risks to programs where the effect is program-specific or where mitigation is program-led.',
        ],
        'note' => 'Fiscal risk records support the BSP risk section and broader budget governance.',
    ],
    'strategy-governance/fiscal-risk-form' => [
        'title' => 'Fiscal Risk Setup Help',
        'purpose' => 'Use this form to define one fiscal risk and its mitigation approach.',
        'points' => [
            'Choose a risk type that fits the nature of the exposure.',
            'Use likelihood and impact consistently across risks.',
            'Describe mitigation in practical terms that decision-makers can understand.',
        ],
        'note' => 'A concise, clear risk statement is usually more useful than a very long narrative description.',
    ],
    'strategy-governance/program-risks' => [
        'title' => 'Program Risk Link Help',
        'purpose' => 'This screen links fiscal risks to programs so governance reporting can show which strategic areas are affected.',
        'points' => [
            'Link a risk only where the program is genuinely affected or involved in mitigation.',
            'Avoid duplicate links that do not add analytical value.',
            'Review linked risks before finalizing BSP narratives and readiness.',
        ],
        'note' => 'The program-risk bridge helps connect high-level fiscal risk governance back to specific strategic programs.',
    ],
    'strategy-governance/program-risk-form' => [
        'title' => 'Program Risk Link Setup Help',
        'purpose' => 'Use this form to connect one program to one fiscal risk.',
        'points' => [
            'Choose the program carefully.',
            'Link the most relevant fiscal risk record rather than creating duplicate risks.',
            'Use this screen as a bridge, not as the place to redefine the risk itself.',
        ],
        'note' => 'The risk details stay on the fiscal risk record. This form only creates the relationship.',
    ],
    'strategy-reports/summary' => [
        'title' => 'Strategic Summary Help',
        'purpose' => 'This report gives a high-level summary of the strategic dataset and budget totals for the active context.',
        'points' => [
            'Use it to get an overall sense of structure and financial coverage.',
            'Move to sector, program, MTFF, or performance reports when you need more detail.',
            'Use readiness checks if the summary looks thinner than expected.',
        ],
        'note' => 'This is a summary report, so it is best used as a starting point rather than the final analytical view.',
    ],
    'strategy-reports/readiness' => [
        'title' => 'Submission Readiness Help',
        'purpose' => 'This report checks whether the active strategic version looks complete enough for submission or review.',
        'points' => [
            'Look at warning rows first because they identify missing planning links or missing governance content.',
            'Use the action buttons to go straight to the screen that needs attention.',
            'Review this page before moving the workflow from Draft to Submitted.',
        ],
        'note' => 'Readiness is a management aid, not a replacement for analytical review and judgment.',
    ],
    'strategy-reports/submission-readiness' => [
        'title' => 'Submission Readiness Help',
        'purpose' => 'This report checks whether the active strategic version looks complete enough for submission or review.',
        'points' => [
            'Look at warning rows first because they identify missing planning links or missing governance content.',
            'Use the action buttons to go straight to the screen that needs attention.',
            'Review this page before moving the workflow from Draft to Submitted.',
        ],
        'note' => 'Submission readiness is about the active fiscal year/version, not about base setup quality.',
    ],
    'strategy-config/configuration-readiness' => [
        'title' => 'Configuration Readiness Help',
        'purpose' => 'This report checks whether the underlying strategic setup is ready for users to work with.',
        'points' => [
            'Review mappings, parent links, imports, and planning framework reference data first.',
            'Use this page after loading or changing tblSegmentValues-driven hierarchy data.',
            'Check that the mapped dimensions reflect the client’s actual hierarchy, for example Output-based or Target-based delivery.',
            'Fix configuration readiness issues before asking users to enter detailed strategic plans.',
        ],
        'note' => 'Configuration readiness focuses on setup quality, not submission completeness.',
    ],
    'strategy-publish/requests' => [
        'title' => 'Segment Publication Help',
        'purpose' => 'Use this area to control which new strategic dimension values are allowed to flow back into tblSegmentValues.',
        'points' => [
            'Create a request first, then add the proposed segment rows that need authorization.',
            'Submit the request for approval before publishing anything back to the shared segment table.',
            'Use this process to prevent uncontrolled creation of new source segment values.',
        ],
        'note' => 'This first version is approval-first and explicit. It is designed for control and auditability before automation.',
    ],
    'strategy-publish/request-form' => [
        'title' => 'Publication Request Setup Help',
        'purpose' => 'Create one publication request batch before adding the proposed segment values for review.',
        'points' => [
            'Use a clear title so approvers understand the purpose of the request.',
            'Group related segment changes into one request where they belong to the same release or configuration change.',
            'Add the detailed request lines after saving the request header.',
        ],
        'note' => 'The request header is the approval container. The actual segment values sit on the request lines.',
    ],
    'strategy-publish/request-view' => [
        'title' => 'Publication Request Review Help',
        'purpose' => 'Review the proposed lines, move the request through approval, and publish only when it is authorized.',
        'points' => [
            'Use Draft while still editing lines.',
            'Use Submit for Approval once the request is complete.',
            'Users can submit requests without being approvers, but only approver-role users can approve, reject, or publish them.',
            'Only approved lines can be published back to tblSegmentValues.',
        ],
        'note' => 'Publishing checks for existing active segment rows first and records failures line by line.',
    ],
    'strategy-publish/line-form' => [
        'title' => 'Publication Line Help',
        'purpose' => 'Each line represents one proposed row to be written back to tblSegmentValues after approval.',
        'points' => [
            'Use the mapped strategic dimension so the system can resolve the correct SegmentNo.',
            'Enter the correct DataObjectCode, SegmentCode, and parent link values because these control hierarchy behavior in the source table.',
            'Leave Sort Order blank where a numeric SegmentCode should drive the order automatically.',
        ],
        'note' => 'The first version captures the proposed source row explicitly so approvers can review exactly what will be published.',
    ],
    'strategy-submissions/list' => [
        'title' => 'Funding Submissions Help',
        'purpose' => 'Use this workspace to track funding lodgements as they move from draft capture through formal submission, review, approval, and publication.',
        'points' => [
            'Create a lodgement header first so the request has an owner, scope, title, and classification.',
            'Add one or more funding items to describe the actual funding demand before lodging the package.',
            'Use the status views to separate draft lodgements from items awaiting review, approval, or publication.',
        ],
        'note' => 'A lodgement captures funding demand and supporting detail. It does not affect ceilings until approved amounts are published.',
    ],
    'strategy-submissions/lodgements' => [
        'title' => 'Funding Lodgements Help',
        'purpose' => 'Use this screen to focus on funding packages that are still being prepared or have been lodged for handoff before formal review.',
        'points' => [
            'Use Draft lodgements while preparers are still editing the header, attachments, and funding items.',
            'Move a complete package to Lodged when it is ready to leave preparation and become part of the submission workflow.',
            'Open each lodgement to check whether the funding items, scope, priority, and supporting notes are complete.',
        ],
        'note' => 'The lodgement stage is about capturing and packaging funding demand. Reviewers and approvers make funding decisions later in the workflow.',
    ],
    'strategy-submissions/reviews' => [
        'title' => 'Funding Reviews Help',
        'purpose' => 'Use this screen to focus on funding submissions that require reviewer assessment before approval.',
        'points' => [
            'Review each funding item and record recommended approved amounts, reductions, or rejection decisions.',
            'Use assessment notes to explain trade-offs, missing evidence, or conditions attached to the recommendation.',
            'Move the package forward only when item-level review and overall assessment are complete.',
        ],
        'note' => 'Review records recommendations and analysis. Final approval remains a separate approval step.',
    ],
    'strategy-submissions/approvals' => [
        'title' => 'Funding Approvals Help',
        'purpose' => 'Use this screen to focus on reviewed funding submissions that need final approval, rejection, funding, or publication action.',
        'points' => [
            'Confirm that reviewer recommendations and approved amounts are complete before making the final decision.',
            'Approve or reject the submission according to the current delegation and workflow rules.',
            'Publish approved amounts to sector ceilings only when they are ready to affect fiscal controls.',
        ],
        'note' => 'Approval decisions are auditable workflow actions. Publishing changes the fiscal control layer; it is not the same as editing the original lodgement.',
    ],
    'strategy-submissions/report' => [
        'title' => 'Funding Submission Summary Help',
        'purpose' => 'This report summarizes funding item demand and decisions for the active fiscal year and version.',
        'points' => [
            'Use the sector table to see where funding pressure is concentrated.',
            'Use the program table to identify which delivery areas are getting approved or left unfunded.',
            'Compare published amounts with sector ceilings to confirm approved bids are actually reflected in fiscal controls.',
        ],
        'note' => 'Published totals only reflect lines that have been pushed into sector ceilings from the funding submission workflow.',
    ],
    'strategy-submissions/form' => [
        'title' => 'Funding Lodgement Header Help',
        'purpose' => 'Use this form to create or edit the lodgement header that holds one package of funding items for the active context.',
        'points' => [
            'Use a clear title and the system DataScope so preparers, reviewers, and approvers understand ownership.',
            'Choose the submission type, priority, and notes carefully because they classify why the funding is being requested.',
            'Save the header first, then add funding items, attachments, and supporting narrative before lodging.',
        ],
        'note' => 'The header is the container. Requested and approved amounts come from the funding items attached to it.',
    ],
    'strategy-submissions/view' => [
        'title' => 'Funding Lodgement Workflow Help',
        'purpose' => 'Use this screen to manage one funding package from lodgement through submission, review, approval, and publication.',
        'points' => [
            'Use Draft while still editing the lodgement header and funding items, then use Lodged as the handoff state before formal review.',
            'Use funding item review and submission assessment for reviewer comments before the submission moves to Reviewed - Awaiting Approval.',
            'Publish to Sector Ceilings only after the approved amounts are ready to affect fiscal controls.',
        ],
        'note' => 'Publishing to sector ceilings updates the fiscal control layer but does not yet create plan rows.',
    ],
    'strategy-submissions/line-form' => [
        'title' => 'Funding Lodgement Item Help',
        'purpose' => 'Use this form to add or edit one funding item inside the lodgement package.',
        'points' => [
            'Classify the item by sector, program, project, funding source, and other dimensions so reporting and review totals aggregate correctly.',
            'Enter the requested amounts and supporting narrative here. Approved amounts are set during review.',
            'Use expected results, justification, and attachments to help reviewers understand the value and readiness of the request.',
        ],
        'note' => 'Funding items capture demand. They do not create plan rows or affect ceilings until the package is approved and published.',
    ],
    'strategy-submissions/review-line' => [
        'title' => 'Funding Item Review Help',
        'purpose' => 'Use this screen to set approved amounts and record a decision for one funding item.',
        'points' => [
            'Approve to fund the full request, partial approve to set reduced values, or reject to zero out the item.',
            'Use decision notes where a reviewer needs to explain reductions or conditions.',
            'Check that the item is mapped to the correct sector before publishing to ceilings.',
        ],
        'note' => 'Item-level review moves the submission toward review completion, but a separate approver still makes the overall approval decision.',
    ],
    'strategy-reports/sector-budget' => [
        'title' => 'Sector Budget Report Help',
        'purpose' => 'This report shows how strategic budgets roll up by sector for the active context.',
        'points' => [
            'Use it to compare strategic allocations across policy sectors.',
            'Check whether programs are classified into the right sector if totals look wrong.',
            'Use this report alongside the MTFF view for medium-term resource discussions.',
        ],
        'note' => 'Sector totals depend on activity budget lines and on programs being assigned to the correct sector.',
    ],
    'strategy-reports/program-budget' => [
        'title' => 'Program Budget Report Help',
        'purpose' => 'This report shows budget totals at program level and helps users understand where resources are concentrated.',
        'points' => [
            'Use it to compare programs within or across sectors.',
            'Check program setup and activity budgets when amounts look incomplete.',
            'Use it together with performance reporting to compare planned resources and intended results.',
        ],
        'note' => 'Program-level reporting depends on clean program records and valid activity budget lines.',
    ],
    'strategy-reports/segment-parent-child' => [
        'title' => 'Segment Parent-Child Check Help',
        'purpose' => 'Use this screen to verify that one segment is correctly linked to its parent segment for the active fiscal year.',
        'points' => [
            'Select the parent segment and child segment you want to test, such as Program to SubProgram.',
            'Review errors for missing parent codes, missing ParentSegmentValueID values, duplicate keys, or child rows that resolve to more than one parent.',
            'Use Resolve Parent Links after loading or editing segment values so ParentSegmentDataObjectCode and ParentSegmentValueID are refreshed from the current parent-child codes.',
        ],
        'note' => 'SegmentCode may repeat across DataObjectCode values, so parent checks rely on FiscalYearID, SegmentNo, SegmentCode, and the correct parent DataObjectCode scope.',
    ],
    'strategy-reports/mtff' => [
        'title' => 'MTFF Help',
        'purpose' => 'This report gives a medium-term expenditure view using the active fiscal context as the anchor year.',
        'points' => [
            'Use it to see the medium-term profile of strategic costs over the planning horizon.',
            'Keep one sector and one primary owner per program to avoid ambiguous MTFF rollups.',
            'Use linked orgs for cross-cutting participation rather than duplicating the program across sectors or owners.',
        ],
        'note' => 'MTFF reporting works best when programs have clear classification and accountability.',
    ],
    'strategy-reports/performance' => [
        'title' => 'Performance Report Help',
        'purpose' => 'This report brings together objectives, indicators, and targets so users can review the strategic results framework.',
        'points' => [
            'Use it to check whether programs are properly linked to measurable indicators and targets.',
            'Look for gaps where objectives exist without indicators or indicators exist without targets.',
            'Use it together with program and sector budget reports to compare money and results.',
        ],
        'note' => 'Performance reporting is strongest when indicators are clearly defined and targets are version-complete.',
    ],
    'strategy-fiscal/overview' => [
        'title' => 'Fiscal Overview Help',
        'purpose' => 'This page summarizes the fiscal control picture for the active context by comparing approved ceilings with the strategic plan totals.',
        'points' => [
            'Use it first to see whether the current strategy sits broadly within the approved fiscal envelope.',
            'Look at headroom, sector overruns, and program overruns before reviewing detailed ceiling tables.',
            'Move to Resource Envelope, Sector Ceilings, and Ceiling vs Plan for the underlying detail.',
        ],
        'note' => 'This screen depends on approved active rows in tblCeilingDefinition for the active fiscal year and version.',
    ],
    'strategy-fiscal/resource-envelope' => [
        'title' => 'Resource Envelope Summary Help',
        'purpose' => 'This page summarizes the total available funds for the active fiscal year and version before allocating ceilings.',
        'points' => [
            'Use it to review current-year totals, outer-year totals, and BP phasing totals.',
            'Move to Envelope Lines to manage the individual funding lines behind the totals.',
            'Use Add Envelope Line when you need to capture a new funding line.',
        ],
        'note' => 'Funding Type and Funding Source should be mapped and configured first because they are key classification attributes on envelope lines.',
    ],
    'strategy-fiscal/resource-envelope-lines' => [
        'title' => 'Resource Envelope Help',
        'purpose' => 'This page lists the individual funding lines that make up the total resource envelope.',
        'points' => [
            'Use Add Envelope Line to create a new funding line.',
            'Edit existing lines here when you need to change amounts, phasing, or funding classification.',
            'Use the subtotal sections to review totals by Funding Type and Funding Source.',
            'Return to Resource Envelope Summary to review totals after making changes.',
        ],
        'note' => 'Keep each line focused on one Funding Type and, where needed, one Funding Source.',
    ],
    'strategy-fiscal/resource-envelope-form' => [
        'title' => 'Resource Envelope Line Help',
        'purpose' => 'Use this form to enter one funding line into the total available resource envelope.',
        'points' => [
            'Select a Funding Type first, then optionally narrow the line to a specific Funding Source.',
            'Enter the current year total amount for this funding line.',
            'If you also enter BP1 to BP12, the monthly phasing should add up to the same current year total.',
        ],
        'note' => 'Outer-year amounts are optional and help extend the envelope into the medium-term planning horizon.',
    ],
    'strategy-fiscal/sector-ceilings' => [
        'title' => 'Sector Ceilings Help',
        'purpose' => 'This page compares sector-level approved ceilings with current strategic plan totals.',
        'points' => [
            'Use it to identify sectors that are over ceiling or materially underallocated.',
            'Check sector mapping and source segment links if ceiling rows are not matching imported sectors.',
            'Treat unmatched sector codes as a setup issue to fix before relying on the comparison.',
        ],
        'note' => 'Sector comparisons work best when sectors were imported from tblSegmentValues and retain source tracking.',
    ],
    'strategy-fiscal/ceiling-vs-plan' => [
        'title' => 'Ceiling vs Plan Help',
        'purpose' => 'This page compares approved ceilings against strategic plan totals at both sector and program level.',
        'points' => [
            'Use the sector section for top-level allocation discipline.',
            'Use the program section to see where pressure or slack is sitting within sectors.',
            'Follow up on negative variance rows because they show planned amounts above ceiling.',
        ],
        'note' => 'This comparison helps bridge strategy and fiscal control, but it relies on consistent source code alignment.',
    ],
];

foreach ($map as $pattern => $definition) {
    if ($route === $pattern) {
        $help = $definition;
        break;
    }
}

if (!function_exists('h')) {
    function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

$isWorkflowOperationsRoute = preg_match('/^workflow($|\/)|^workflow-projects\//', $route) === 1
    || preg_match('/^workflow-requirements\/|^workflow-issues\/|^workflow-user-groups\//', $route) === 1;
$isTrainingRoute = preg_match('/^training($|\/)|^training-admin\/|^training-certifications\//', $route) === 1;
?>
<div class="container-fluid mt-3">
  <div class="card shadow-sm border-0">
    <div class="card-body py-3">
      <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div class="flex-grow-1">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
            <div class="small text-uppercase text-muted fw-semibold">Helper Instructions</div>
            <div class="d-flex gap-2 flex-wrap justify-content-end">
              <?php if ($isWorkflowOperationsRoute): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-primary"
                        id="workflowModuleOverviewHelpBtn"
                        data-module-overview-help
                        data-help-title="Workflow Operations Overview"
                        data-help-screen="workflow-operations/overview">
                  <i class="bi bi-diagram-3 me-1"></i>Module Overview
                </button>
              <?php endif; ?>
              <?php if ($isTrainingRoute): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-primary"
                        id="trainingModuleOverviewHelpBtn"
                        data-module-overview-help
                        data-help-title="Training Overview"
                        data-help-screen="training/overview">
                  <i class="bi bi-mortarboard me-1"></i>Module Overview
                </button>
              <?php endif; ?>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="strategyHelpToggle">
                Hide Help
              </button>
            </div>
          </div>
          <div id="strategyHelpBody">
            <h5 class="mb-2"><?= h((string)($help['title'] ?? 'Screen Help')) ?></h5>
            <p class="mb-2 text-muted"><?= h((string)($help['purpose'] ?? '')) ?></p>
            <?php if (!empty($help['points']) && is_array($help['points'])): ?>
              <ul class="mb-2 ps-3">
                <?php foreach ($help['points'] as $point): ?>
                  <li><?= h((string)$point) ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
            <?php if (!empty($help['note'])): ?>
              <div class="small"><strong>Note:</strong> <?= h((string)$help['note']) ?></div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('strategyHelpToggle');
    const body = document.getElementById('strategyHelpBody');
    if (!toggle || !body) {
        return;
    }

    const storageKey = 'strategy-help-hidden';

    const applyState = function (hidden) {
        body.style.display = hidden ? 'none' : '';
        toggle.textContent = hidden ? 'Show Help' : 'Hide Help';
    };

    applyState(window.localStorage.getItem(storageKey) === '1');

    toggle.addEventListener('click', function () {
        const hidden = body.style.display !== 'none';
        window.localStorage.setItem(storageKey, hidden ? '1' : '0');
        applyState(hidden);
    });

    const overviewButtons = document.querySelectorAll('[data-module-overview-help]');
    overviewButtons.forEach(function (overviewButton) {
        overviewButton.addEventListener('click', async function () {
            const screen = overviewButton.getAttribute('data-help-screen') || 'workflow-operations/overview';
            const title = overviewButton.getAttribute('data-help-title') || 'Module Overview';
            const modalBody = document.getElementById('helpModalBody');
            const modalTitle = document.getElementById('helpModalLabel');
            const modalEl = document.getElementById('helpModal');
            if (!modalBody || !modalEl || !window.bootstrap) {
                window.location.href = 'index.php?route=help/show&screen=' + encodeURIComponent(screen);
                return;
            }

            modalBody.innerHTML = '<div class="text-center my-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            if (modalTitle) {
                modalTitle.innerHTML = '<i class="bi bi-question-circle me-2"></i>Help - ' + title;
            }

            try {
                const response = await fetch('index.php?route=help/show&screen=' + encodeURIComponent(screen), { credentials: 'same-origin' });
                modalBody.innerHTML = await response.text();
            } catch (error) {
                modalBody.innerHTML = '<p class="text-danger">Failed to load help content.</p>';
            }

            bootstrap.Modal.getOrCreateInstance(modalEl, {
                backdrop: 'static',
                keyboard: true
            }).show();
        });
    });
});
</script>
