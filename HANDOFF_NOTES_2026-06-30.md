# Handoff Notes - Workflow Projects, Requirements, and Task Return Navigation (2026-06-30)

## Session Summary

- Continued the Workflow Operations build-out for CBMSv21.
- Added and refined Workflow Projects and Workflow Requirements capability.
- Tidied project and requirement action buttons so the screens are less cluttered.
- Added detailed project/requirement permissions for view, create, edit, and delete/archive.
- Changed Workflow Projects list ordering to Project Start Date.
- Fixed workflow task save navigation so tasks created from project/requirement screens return to the originating screen after save.

This note is the current handoff baseline for the Workflow Operations work completed on 30 June 2026.

## Recent Commits

- `d494507` - Return workflow task saves to origin screen
- `cdf12e5` - Order workflow projects by start date
- `8db30ad` - Add workflow project and requirement permissions
- `4adf5d6` - Tidy workflow requirement actions
- `c16ad2c` - Shorten project task action label
- `1f51abd` - Tidy workflow project actions
- `966a3d9` - Validate project task dates
- `47972bb` - Add project user role editing
- `f71c884` - Checkpoint workflow operations and training features

## Major Outcomes Completed

### 1. Workflow Projects And Requirements Schema

- Workflow Projects, Project Users, Task Dependencies, Entity Links, Requirements, Requirement History, and Requirement Attachments are defined in:
  - `backend-php/config/sql/create_workflow_projects.sql`

- Supporting seed/demo scripts are present:
  - `backend-php/config/sql/seed_workflow_requirements_demo.sql`
  - `backend-php/config/sql/seed_workflow_project_demo_tasks.sql`

Important:

- Run `backend-php/config/sql/create_workflow_projects.sql` against the active database before using the Workflow Projects / Requirements screens in a fresh database.
- The script is written to be mostly idempotent and additive.

### 2. Workflow Project Screen Was Tidied

- Project list actions were reduced and moved into compact dropdown actions where appropriate.
- The task create button label was shortened from `Create Task` to `+ Task`.
- Project summary and project form/Gantt links were kept available but less visually noisy.

Key files:

- `backend-php/app/Views/workflow/WorkflowProjectList.php`
- `backend-php/app/Views/workflow/WorkflowProjectSummary.php`
- `backend-php/app/Views/workflow/WorkflowProjectForm.php`

### 3. Workflow Requirement Side Was Tidied

- Requirement row actions were consolidated so the list/matrix screens feel less busy.
- Requirement create/edit/delete/task actions are now permission-aware.
- Requirement forms support read-only behavior when the user only has view permission.

Key files:

- `backend-php/app/Views/workflow/WorkflowRequirementList.php`
- `backend-php/app/Views/workflow/WorkflowRequirementMatrix.php`
- `backend-php/app/Views/workflow/WorkflowRequirementSummary.php`
- `backend-php/app/Views/workflow/WorkflowRequirementForm.php`

### 4. Detailed Workflow Project And Requirement Permissions Were Added

New permissions:

- `WORKFLOW_PROJECTS_VIEW`
- `WORKFLOW_PROJECTS_CREATE`
- `WORKFLOW_PROJECTS_EDIT`
- `WORKFLOW_PROJECTS_DELETE`
- `WORKFLOW_REQUIREMENTS_VIEW`
- `WORKFLOW_REQUIREMENTS_CREATE`
- `WORKFLOW_REQUIREMENTS_EDIT`
- `WORKFLOW_REQUIREMENTS_DELETE`

Behavior:

- Project/requirement menus and quick links use the new permissions.
- View-only users can open records but forms are read-only.
- Create/edit/delete/archive controls only appear when allowed.
- Delete is implemented as archive/deactivate, not hard delete.

Key files:

- `backend-php/config/sql/sync_role_permission_model_v4.sql`
- `backend-php/app/Controllers/BaseController.php`
- `backend-php/app/Controllers/WorkflowProjectController.php`
- `backend-php/app/Controllers/WorkflowRequirementController.php`
- `backend-php/app/Models/WorkflowProjectModel.php`
- `backend-php/app/Models/WorkflowRequirementModel.php`
- `backend-php/config/menu.php`
- `backend-php/config/quick_links.php`
- `backend-php/config/routes.php`
- `backend-php/lang/en.php`
- `backend-php/lang/fr.php`

Important:

- Run `backend-php/config/sql/sync_role_permission_model_v4.sql` against the active database so the new permissions exist and are assigned to roles.

### 5. Workflow Projects Are Ordered By Start Date

- Workflow Projects list now sorts by:
  - `StartDate` ascending
  - projects with no start date at the bottom
  - `ProjectName`
  - `WorkflowProjectID`

Key file:

- `backend-php/app/Models/WorkflowProjectModel.php`

### 6. Task Save Returns To Origin Screen

- Workflow task create/edit can now carry a safe local `returnTo` URL.
- After saving a task, the app returns to the originating project or requirement screen instead of dropping back to the default task/home flow.
- Supported return targets include:
  - Workflow task list/edit
  - Workflow project list/summary/form
  - Workflow requirement list/summary/matrix/form

Key files:

- `backend-php/app/Controllers/WorkflowController.php`
- `backend-php/app/Views/workflow/WorkflowForm.php`
- `backend-php/app/Views/workflow/WorkflowProjectList.php`
- `backend-php/app/Views/workflow/WorkflowProjectSummary.php`
- `backend-php/app/Views/workflow/WorkflowProjectForm.php`
- `backend-php/app/Views/workflow/WorkflowRequirementForm.php`
- `backend-php/app/Views/workflow/WorkflowRequirementMatrix.php`

Security note:

- `returnTo` is normalized server-side and only accepts local `index.php?route=...` workflow routes.
- Absolute browser referrers are stripped back to local `index.php?...` paths before use.

## Current Environment State

### Workspace

- Current workspace:
  - `C:\CBMS\CBMSv21`

### Git

- Working tree was clean after the latest commit.
- Latest commit at handoff:
  - `d494507 Return workflow task saves to origin screen`

## Verification Completed

PHP syntax checks were run successfully on the touched workflow files during the session, including:

- `backend-php/app/Controllers/WorkflowController.php`
- `backend-php/app/Controllers/WorkflowProjectController.php`
- `backend-php/app/Controllers/WorkflowRequirementController.php`
- `backend-php/app/Models/WorkflowProjectModel.php`
- `backend-php/app/Models/WorkflowRequirementModel.php`
- `backend-php/app/Views/workflow/WorkflowForm.php`
- `backend-php/app/Views/workflow/WorkflowProjectForm.php`
- `backend-php/app/Views/workflow/WorkflowProjectList.php`
- `backend-php/app/Views/workflow/WorkflowProjectSummary.php`
- `backend-php/app/Views/workflow/WorkflowRequirementForm.php`
- `backend-php/app/Views/workflow/WorkflowRequirementList.php`
- `backend-php/app/Views/workflow/WorkflowRequirementMatrix.php`
- `backend-php/app/Views/workflow/WorkflowRequirementSummary.php`
- `backend-php/config/menu.php`
- `backend-php/config/quick_links.php`
- `backend-php/config/routes.php`
- `backend-php/lang/en.php`
- `backend-php/lang/fr.php`

`git diff --check` was run for the recent changes and showed no whitespace errors. It did show the repository's normal LF-to-CRLF warnings.

## Items To Verify In Browser

1. Open Workflow Projects:
   - confirm projects sort by Project Start Date
   - confirm undated projects appear after dated projects
   - confirm row actions are compact

2. Open a Workflow Project Summary:
   - click `+ Task`
   - save the task
   - confirm the app returns to the same project summary screen

3. Open a Workflow Project Form / Gantt section:
   - click `+ Task` from the task/Gantt area
   - save the task
   - confirm the app returns to the project form/Gantt area

4. Open a Workflow Requirement Form:
   - click `+ Task`
   - save the task
   - confirm the app returns to the requirement form

5. Open Requirements Matrix:
   - click `+ Task` from a requirement row
   - save the task
   - confirm the app returns to the matrix with filters preserved

6. Test permissions with representative roles:
   - Workflow Operations User: view only for projects/requirements
   - Workflow Operations Editor: view/create/edit for projects/requirements
   - Workflow Operations Administrator: view/create/edit/delete for projects/requirements

## Recommended Next Steps

1. Run the SQL scripts in the active database if not already done:
   - `backend-php/config/sql/create_workflow_projects.sql`
   - `backend-php/config/sql/sync_role_permission_model_v4.sql`

2. Browser-test the Workflow Projects and Requirements flows listed above.

3. Review whether requirement approval should receive its own separate permission in future:
   - current approval/status-governance behavior still relies on workflow operations admin-level permissions.

4. Consider adding a small workflow regression checklist or smoke-test script for:
   - creating a project
   - creating a requirement
   - creating a task from each originating screen
   - verifying return navigation
   - verifying permission visibility

## Resume Point

Start by opening:

- `index.php?route=workflow-projects/list`
- `index.php?route=workflow-requirements/list`
- `index.php?route=workflow-requirements/matrix`

Then validate task creation from projects and requirements. If anything feels awkward, the next likely refinement area is UI flow polish rather than schema work.
