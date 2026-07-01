# Handoff Notes - Workflow Operations Build-Out (2026-06-30)

## Current Resume Context

This handoff captures the current state of the Workflow Operations work in `C:\CBMS\CBMSv21`.

The work is not committed. Several files are modified and the new Issues Log files are still untracked. Treat the workspace as intentionally dirty and do not reset it.

## High-Level Summary

Workflow Operations is close to feature-complete for this pass. The session expanded Projects, Requirements, Tasks, Issues, traceability, navigation, print/export, helper text, selected-project usability, training hooks, read-only styling, delete behavior, and tabbed layouts.

The main functional areas now covered are:

- Workflow Projects
- Workflow Requirements and Requirement Matrix
- Workflow Tasks
- Workflow Issues Log
- Project Summary with tabs and issue visibility
- Selected Project sticky context
- Training module hooks for future scenario authoring
- Print and Excel export for operation screens

## Important Workspace State

`git status --short` currently shows:

- Modified:
  - `SCREEN_UI_STANDARD.md`
  - `backend-php/app/Controllers/BaseController.php`
  - `backend-php/app/Controllers/WorkflowController.php`
  - `backend-php/app/Controllers/WorkflowProjectController.php`
  - `backend-php/app/Controllers/WorkflowRequirementController.php`
  - `backend-php/app/Models/WorkflowLinkModel.php`
  - `backend-php/app/Views/layouts/main.php`
  - `backend-php/app/Views/strategy/_RouteHelp.php`
  - multiple `backend-php/app/Views/workflow/*.php`
  - `backend-php/config/menu.php`
  - `backend-php/config/quick_links.php`
  - `backend-php/config/routes.php`
  - `backend-php/config/sql/create_workflow_projects.sql`
  - `backend-php/config/sql/sync_role_permission_model_v4.sql`
  - `backend-php/lang/en.php`
  - `backend-php/lang/fr.php`

- Untracked new files:
  - `backend-php/app/Controllers/WorkflowIssueController.php`
  - `backend-php/app/Models/WorkflowIssueModel.php`
  - `backend-php/app/Views/workflow/WorkflowIssueForm.php`
  - `backend-php/app/Views/workflow/WorkflowIssueList.php`
  - `backend-php/app/Views/workflow/_SelectedProjectCue.php`
  - `backend-php/config/sql/alter_tblWorkflowIssues_add_other_issue_type.sql`

## Major Changes Completed This Session

### 1. Issues Log

Added a proper Issues Log linked to:

- Workflow Projects
- Workflow Requirements
- Workflow Tasks via workflow entity links

Issue work includes:

- Issue list screen
- Create/Edit Issue screen
- Issue Code generated on save
- Requirement dropdown filtered by selected project
- Attachments on issues
- Create task from issue
- Project Summary shows project issues
- Issue type now requires user selection on create
- Added `Other` issue type

Key files:

- `backend-php/app/Controllers/WorkflowIssueController.php`
- `backend-php/app/Models/WorkflowIssueModel.php`
- `backend-php/app/Views/workflow/WorkflowIssueList.php`
- `backend-php/app/Views/workflow/WorkflowIssueForm.php`
- `backend-php/config/routes.php`
- `backend-php/config/menu.php`
- `backend-php/config/quick_links.php`
- `backend-php/config/sql/create_workflow_projects.sql`
- `backend-php/config/sql/alter_tblWorkflowIssues_add_other_issue_type.sql`
- `backend-php/lang/en.php`
- `backend-php/lang/fr.php`

Important database note:

- Fresh installs use `create_workflow_projects.sql`.
- Existing installs that already have the issue type check constraint need:
  - `backend-php/config/sql/alter_tblWorkflowIssues_add_other_issue_type.sql`

### 2. Sticky Selected Project

Added sticky selected project behavior so users do not have to reselect the project on each Workflow Operations screen.

Behavior:

- Project context is remembered in session.
- Requirements Summary, Requirements Matrix, Tasks, and Issues can default from the remembered project.
- Clearing the project filter clears the sticky context.
- Create Issue now defaults the sticky project when `workflowProjectID` is not supplied in the URL.

Key files:

- `backend-php/app/Controllers/BaseController.php`
- `backend-php/app/Controllers/WorkflowController.php`
- `backend-php/app/Controllers/WorkflowRequirementController.php`
- `backend-php/app/Controllers/WorkflowIssueController.php`
- `backend-php/app/Views/workflow/_SelectedProjectCue.php`
- `backend-php/app/Views/workflow/WorkflowList.php`
- `backend-php/app/Views/workflow/WorkflowRequirementList.php`
- `backend-php/app/Views/workflow/WorkflowRequirementSummary.php`
- `backend-php/app/Views/workflow/WorkflowRequirementMatrix.php`
- `backend-php/app/Views/workflow/WorkflowIssueList.php`

### 3. Project Summary Tabs

Project Summary was reorganized into tabs to reduce visual overload:

- Overview
- Issues
- Linked Work
- Schedule
- Tasks

Project Issues now appear on Project Summary, with actions to open the issue or create a task from the issue.

Key file:

- `backend-php/app/Views/workflow/WorkflowProjectSummary.php`

### 4. Edit Task Tabs

Edit Task screen was reorganized into tabs to reduce clutter:

- Details
- Project Plan
- Assignment
- Notifications
- Existing lower workflow panels remain available for comments, attachments, links, history/views, etc.

Validation now opens the tab containing the first invalid field.

Key file:

- `backend-php/app/Views/workflow/WorkflowForm.php`

### 5. Edit Project Tabs

Edit Project screen was reorganized into tabs:

- Details
- Team
- Schedule

The Schedule tab contains the Gantt/task section. Existing `#workflow-project-gantt` links now open the Schedule tab automatically.

Key file:

- `backend-php/app/Views/workflow/WorkflowProjectForm.php`

### 6. Read-Only Field Visual Standard

Read-only fields now have consistent visual treatment:

- Greyed/read-only control styling
- Read-only badge where appropriate
- Issue Code shows “Generated on Save” and has the read-only visual state

The UI template/standard was updated.

Key files:

- `SCREEN_UI_STANDARD.md`
- `backend-php/app/Views/layouts/main.php`
- `backend-php/app/Views/workflow/WorkflowIssueForm.php`

### 7. Delete Behavior

Delete/archive behavior was expanded and tightened.

Current rule:

- System/workflow administrators can delete.
- Record creators can delete their own items where the model has a creator field.

Notes:

- Projects, Requirements, and Issues use archive/deactivate style delete.
- Tasks use the existing task delete method, now with server-side creator/admin permission checks.
- Delete buttons were added or exposed where needed:
  - Project List
  - Project Summary
  - Project Edit
  - Requirement List/Form
  - Issue List/Form
  - Task List/Edit

Key files:

- `backend-php/app/Controllers/WorkflowProjectController.php`
- `backend-php/app/Controllers/WorkflowRequirementController.php`
- `backend-php/app/Controllers/WorkflowIssueController.php`
- `backend-php/app/Controllers/WorkflowController.php`
- `backend-php/app/Views/workflow/WorkflowProjectList.php`
- `backend-php/app/Views/workflow/WorkflowProjectSummary.php`
- `backend-php/app/Views/workflow/WorkflowProjectForm.php`
- `backend-php/app/Views/workflow/WorkflowRequirementList.php`
- `backend-php/app/Views/workflow/WorkflowRequirementForm.php`
- `backend-php/app/Views/workflow/WorkflowIssueList.php`
- `backend-php/app/Views/workflow/WorkflowIssueForm.php`
- `backend-php/app/Views/workflow/WorkflowList.php`
- `backend-php/app/Views/workflow/WorkflowForm.php`
- `backend-php/lang/en.php`
- `backend-php/lang/fr.php`

### 8. Print And Excel Export

Workflow operation list/report screens now have print and Excel export actions.

Covered screens include:

- Workflow Projects
- Workflow Tasks
- Workflow Requirements
- Requirement Summary
- Requirement Matrix
- Workflow Issues

Key files:

- `backend-php/app/Controllers/WorkflowProjectController.php`
- `backend-php/app/Controllers/WorkflowRequirementController.php`
- `backend-php/app/Controllers/WorkflowIssueController.php`
- `backend-php/app/Controllers/WorkflowController.php`
- list/report views under `backend-php/app/Views/workflow/`

### 9. Helper Instructions Updated

Workflow Operation helper instructions were updated so the screen help aligns with the new workflows.

Key file:

- `backend-php/app/Views/strategy/_RouteHelp.php`

### 10. Training Module Hooks

Reviewed Training module hooks and added stable explicit IDs for future training scenarios.

Confirmed:

- Global training auto-hook include exists in `backend-php/app/Views/layouts/main.php`.
- Explicit IDs were added to important workflow forms, filters, tables, tabs, and action buttons.
- Duplicate `workflow-*` IDs were checked and none were found at the time of verification.

Key files:

- `backend-php/app/Views/layouts/main.php`
- `backend-php/app/Views/training/_TrainingAutoHooks.php`
- `backend-php/app/Views/workflow/WorkflowProjectList.php`
- `backend-php/app/Views/workflow/WorkflowProjectForm.php`
- `backend-php/app/Views/workflow/WorkflowProjectSummary.php`
- `backend-php/app/Views/workflow/WorkflowRequirementList.php`
- `backend-php/app/Views/workflow/WorkflowRequirementSummary.php`
- `backend-php/app/Views/workflow/WorkflowRequirementMatrix.php`
- `backend-php/app/Views/workflow/WorkflowRequirementForm.php`
- `backend-php/app/Views/workflow/WorkflowIssueList.php`
- `backend-php/app/Views/workflow/WorkflowIssueForm.php`
- `backend-php/app/Views/workflow/WorkflowList.php`
- `backend-php/app/Views/workflow/WorkflowForm.php`

## Requirement Acceptance Criteria And Matrix Gaps

Current design:

- Acceptance Criteria is still free text.
- It should be treated as the requirement’s “definition of done”.
- Matrix gaps are determined from structured fields/links, not semantic reading of the free text.

Gap examples:

- Missing acceptance criteria
- No linked task
- Open linked tasks
- No testing link
- No training link
- Has defect/issue links

Suggested future enhancement:

- Add structured acceptance/evidence checklist items if stronger reporting is needed:
  - Testing required
  - Training required
  - Procedure update required
  - Communications required
  - UAT evidence required

## Important SQL Scripts

Run as needed:

- `backend-php/config/sql/create_workflow_projects.sql`
- `backend-php/config/sql/sync_role_permission_model_v4.sql`
- `backend-php/config/sql/alter_workflow_tasks_add_response_forwarding_activity.sql`
- `backend-php/config/sql/alter_tblWorkflowIssues_add_other_issue_type.sql`

Important:

- The Issues Log depends on the workflow project SQL objects.
- Existing databases may need the new `alter_tblWorkflowIssues_add_other_issue_type.sql` patch to allow Issue Type `OTHER`.

## Verification Completed During Session

PHP syntax checks were run successfully for the touched workflow files throughout the session, including:

- `backend-php/app/Controllers/WorkflowController.php`
- `backend-php/app/Controllers/WorkflowProjectController.php`
- `backend-php/app/Controllers/WorkflowRequirementController.php`
- `backend-php/app/Controllers/WorkflowIssueController.php`
- `backend-php/app/Models/WorkflowIssueModel.php`
- `backend-php/app/Views/workflow/WorkflowForm.php`
- `backend-php/app/Views/workflow/WorkflowList.php`
- `backend-php/app/Views/workflow/WorkflowProjectForm.php`
- `backend-php/app/Views/workflow/WorkflowProjectList.php`
- `backend-php/app/Views/workflow/WorkflowProjectSummary.php`
- `backend-php/app/Views/workflow/WorkflowRequirementForm.php`
- `backend-php/app/Views/workflow/WorkflowRequirementList.php`
- `backend-php/app/Views/workflow/WorkflowRequirementMatrix.php`
- `backend-php/app/Views/workflow/WorkflowRequirementSummary.php`
- `backend-php/app/Views/workflow/WorkflowIssueForm.php`
- `backend-php/app/Views/workflow/WorkflowIssueList.php`
- `backend-php/app/Views/workflow/_SelectedProjectCue.php`
- `backend-php/lang/en.php`
- `backend-php/lang/fr.php`

`git diff --check` was run multiple times and was clean apart from normal LF-to-CRLF warnings.

## Browser Verification Still Recommended

1. Workflow Projects
   - Create/edit project.
   - Confirm tabs: Details, Team, Schedule.
   - Confirm `#workflow-project-gantt` opens Schedule tab.
   - Confirm project delete appears only for admin/creator.

2. Workflow Project Summary
   - Confirm tabs render correctly.
   - Confirm Issues tab shows project issues.
   - Confirm Add Issue and Add Task actions keep the project context.

3. Workflow Issues
   - Create issue from sticky project context without `workflowProjectID` in URL.
   - Confirm project defaults from sticky selected project.
   - Confirm Issue Type starts blank and user must select.
   - Confirm Issue Type includes Other.
   - Confirm Requirement dropdown filters by selected Project.
   - Upload/download/delete an issue attachment.
   - Create task from issue and verify project date range validation.

4. Workflow Requirements
   - Create high-level and detailed requirements.
   - Confirm detailed requirement parent behavior.
   - Confirm Create Issue from requirement carries project/requirement.
   - Confirm Matrix gaps show expected no-training/no-testing/no-task states.

5. Workflow Tasks
   - Create task from project, requirement, and issue.
   - Confirm return navigation works.
   - Confirm task edit tabs render and validation opens invalid tab.
   - Confirm delete appears for task creator/admin only.

6. Print/Excel
   - Test print buttons and Excel downloads on the operation screens.

7. Permissions
   - Test with representative roles:
     - View-only user
     - Editor/creator
     - Workflow Operations Administrator
     - System Administrator

## Known Caveats / Follow-Up Items

- Issue files are still untracked. Make sure they are added when committing.
- The create workflow SQL still has default Issue Type `BUG` at the DB level for compatibility, but the UI now requires explicit selection. App-side save validation prevents blank Issue Type.
- Existing DBs with old issue type constraints require the new `alter_tblWorkflowIssues_add_other_issue_type.sql` patch.
- Acceptance Criteria remains free text. Structured acceptance/evidence checklist is a future enhancement, not implemented yet.
- Consider adding an automated smoke checklist later. Manual browser testing is still recommended before commit.

## Suggested Resume Point

Start here:

1. Run `git status --short`.
2. Browser-test Workflow Issues create flow:
   - sticky project default
   - issue type required
   - Other issue type saves
3. Browser-test Project Edit tabs and Task Edit tabs.
4. Review untracked issue files and SQL patch before committing.

Useful routes:

- `index.php?route=workflow-projects/list`
- `index.php?route=workflow-projects/summary&id=<projectId>`
- `index.php?route=workflow-requirements/list`
- `index.php?route=workflow-requirements/matrix`
- `index.php?route=workflow-issues/list`
- `index.php?route=workflow/list`

## Update - Detailed Workflow Operations Help Files

Added detailed context-sensitive modal help files for Workflow Operations screens.

New/updated files:

- `backend-php/app/Controllers/HelpController.php`
- `backend-php/app/Views/help/_ScreenHelpTemplate.php`
- `backend-php/app/Views/help/_WorkflowOperationsHelp.php`
- `backend-php/app/Views/help/WorkflowProjectsList.en.php`
- `backend-php/app/Views/help/WorkflowProjectsForm.en.php`
- `backend-php/app/Views/help/WorkflowProjectsSummary.en.php`
- `backend-php/app/Views/help/WorkflowRequirementsList.en.php`
- `backend-php/app/Views/help/WorkflowRequirementsForm.en.php`
- `backend-php/app/Views/help/WorkflowRequirementsSummary.en.php`
- `backend-php/app/Views/help/WorkflowRequirementsMatrix.en.php`
- `backend-php/app/Views/help/WorkflowList.en.php`
- `backend-php/app/Views/help/WorkflowForm.en.php`
- `backend-php/app/Views/help/WorkflowIssuesList.en.php`
- `backend-php/app/Views/help/WorkflowIssuesForm.en.php`
- `backend-php/app/Views/help/WorkflowUserGroupsList.en.php`
- `backend-php/app/Views/help/WorkflowUserGroupsForm.en.php`
- `backend-php/app/Views/help/WorkflowOperationsOverview.en.php`

HelpController now resolves hyphenated routes such as `workflow-projects/list` to clean help filenames such as `WorkflowProjectsList.en.php`, with fallback support for older literal route-style filenames.

The shared help file template now uses a restrained documentation-style layout:

- Compact icon/title header
- Section rows with small left-side icons
- Subtle dot bullets for help points
- Left-accented note callout

Use `backend-php/app/Views/help/_ScreenHelpTemplate.php` as the standard template for new detailed help pages across the application. Module-specific wrappers can set `$helpEyebrow` before requiring the template; Workflow Operations does this through `_WorkflowOperationsHelp.php`.

Recommended help depth:

- Explain what the screen is for.
- Describe the main actions and filters.
- Explain key fields where users must make decisions.
- Include permission, workflow, or data-impact cautions.
- Keep long policy/process detail out of the modal unless it is needed to complete the screen.

Added a conceptual Workflow Operations module overview help page:

- Screen key: `workflow-operations/overview`
- File: `backend-php/app/Views/help/WorkflowOperationsOverview.en.php`
- Purpose: explain the module concept, governance/control value, whole-project visibility, integrity and quality benefits, core entities, typical flow, how screens fit together, and good operating habits.
- Link approach: Workflow Operations helper cards now show a `Module Overview` button that opens this overview in the existing Help modal. This does not replace the normal screen-specific Help button.

Verification:

- `php -l` passed for `HelpController.php`.
- `php -l` passed for `_ScreenHelpTemplate.php`.
- `php -l` passed for `_WorkflowOperationsHelp.php`.
- `php -l` passed for `WorkflowOperationsOverview.en.php`.
- `php -l` passed for `_RouteHelp.php`.
- `php -l` passed for all new `Workflow*.en.php` help files.
- Route coverage check confirmed help files exist for:
  - `workflow-projects/list`
  - `workflow-projects/form`
  - `workflow-projects/summary`
  - `workflow-requirements/list`
  - `workflow-requirements/form`
  - `workflow-requirements/summary`
  - `workflow-requirements/matrix`
  - `workflow/list`
  - `workflow/form`
  - `workflow-issues/list`
  - `workflow-issues/form`
  - `workflow-user-groups/list`
- `workflow-user-groups/form`

## Update - Workflow Operations Training Scenarios

Added an initial Workflow Operations training seed script:

- `backend-php/config/sql/seed_training_workflow_operations.sql`

The seed creates non-destructive orientation scenarios for:

- `workflow_ops_overview` - module concept, governance purpose, and module-vs-screen help.
- `workflow_ops_project_register` - project register filters, table, and export.
- `workflow_ops_project_form` - project form details, owner, team tab, and save action orientation.
- `workflow_ops_requirements_register` - requirement register filters, levels, priority, table, and actions menu.
- `workflow_ops_requirement_matrix` - traceability matrix filters, coverage gaps, table, and export.
- `workflow_ops_issue_log` - issue filters, severity/status/project review, table, and export.
- `workflow_ops_task_queue` - task filters, status/project filtering, table, and export.

Design choice:

- These are orientation scenarios, not data-creation scenarios.
- They avoid save/delete requirements except for reviewing the project form save button.
- They use existing stable screen IDs and `manual_continue` / `click_target` completion modes.
- The Workflow Operations Module Overview scenario uses manual continue steps for help modal reading. It no longer advances from a button click into a separate `helpModal` step, so learners can scroll/read the Module Overview and screen Help before choosing Continue.
- Data-entry scenarios can be added later once training sample data and cleanup rules are agreed.

Verification:

- Target ID coverage check passed for every target element used by `seed_training_workflow_operations.sql`.
- `git diff --check` passed for the new seed script.

Training Scenarios screen ordering update:

- `backend-php/app/Models/TrainingScenarioModel.php` now exposes scenario `sort_order`.
- `backend-php/app/Controllers/TrainingController.php` now sorts scenarios by module, then `sort_order`, then title.
- `backend-php/app/Views/training/TrainingScenarios.php` now shows a recommended completion order badge on each scenario card and labels module groups as shown in recommended completion order.
- `NextScenarioCode` still provides the recommended next scenario after completion.
- The Training Scenarios Module filter is sticky in session. Choosing All Modules or clicking Reset clears the sticky module filter.
- Navigation polish: Training Catalogue is now available in Administration quick links, and Training Scenarios / Training Summary use separate active menu rules so they do not both appear selected.
- Training Catalogue now has a Module filter sourced from distinct scenario module names.
- Added a sample multiple-choice checkpoint question as the final Workflow Operations Module Overview step. The seed stores structured checkpoint metadata for that step when `tblTrainingStepCheckpoints` exists.
- Active checkpoint questions now display on the live training overlay and runner page. Required multiple-choice checkpoints must be answered correctly before Continue advances; the runner page shows radio-button answers with a Submit Answer and Continue action, and expected answers/explanations are only shown to trainer/admin users.
- Removed the checkpoint runner reload workaround after it caused scenario pages to refresh repeatedly. The runner now renders checkpoint choices in place from the polled state, and the live overlay updates to the completed state without forcing a page replace.
- Added a controller fallback for the Workflow Operations Module Overview final checkpoint so A/B/C/D choices still display in the runner if the checkpoint support table row is missing or still has old free-text expected-answer content.

Training stop fix:

- `backend-php/app/Models/TrainingProgressModel.php` fixed an ODBC/PDO SQL parameter issue in `stopScenario()`.
- The update statement previously reused `:stoppedAt` for both `StoppedAt` and `LastActivityAt`, which caused SQL Server ODBC error `COUNT field incorrect or syntax error` on `training/stop`.
- It now uses distinct `:stoppedAt` and `:lastActivityAt` parameters with the same timestamp value.
