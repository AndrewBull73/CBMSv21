# Handoff Notes - Shared Workflow Engine, Budget Execution Retrofit, and Workflow Admin (2026-05-21)

## Session Summary

- Continued from the earlier Budget Execution and workflow design work.
- Completed the shared workflow engine foundation build and applied it to the live development database.
- Retrofitted `Budget Execution Supplementaries` onto the shared multi-stage workflow engine.
- Retrofitted `Budget Execution RIE` onto the shared multi-stage workflow engine.
- Built a new `Workflow Engine` admin module so workflow definitions, stages, actions, and routing diagnostics are manageable in the application.

This note should be treated as the current handoff baseline for workflow-related work. It supersedes older assumptions in `HANDOFF_NOTES_2026-05-20.md` that still describe `RIE` as a simple `DRAFT / APPROVED / CANCELLED` lifecycle only.

## Major Outcomes Completed

### 1. Shared Workflow Engine Foundation

- The shared engine foundation is now in place through:
  - `backend-php/config/sql/create_workflow_engine_foundation_v1.sql`
  - `backend-php/app/Models/WorkflowEngineModel.php`

- Core engine tables now exist for:
  - workflow definitions
  - workflow stages
  - workflow actions
  - workflow instances
  - workflow instance history

- Existing workflow assignments were extended to support:
  - hierarchy-aware routing
  - inheritance from parent DataObject scopes
  - richer future assignment logic

### 2. Shared Workflow Assignment Integration

- `WorkflowAssignmentModel` now dynamically reads workflow areas and stages from the shared workflow engine tables instead of relying only on hard-coded lists.
- Assignment resolution now explicitly respects:
  - `RouteByDataObjectHierarchy`
  - `InheritFromParentScope`
- The assignment admin screens remain active and now align better with the shared engine definitions.

Key files:
- `backend-php/app/Models/WorkflowAssignmentModel.php`
- `backend-php/app/Controllers/WorkflowAssignmentController.php`
- `backend-php/app/Views/config/WorkflowAssignmentForm.php`
- `backend-php/app/Views/config/WorkflowAssignmentsList.php`

### 3. Supplementary Budgets Retrofitted to Shared Workflow

- `Supplementaries` now follow a real multi-stage workflow instead of direct single-step approval.
- Current staged flow is:
  - `DRAFT`
  - `TECHNICAL_REVIEW`
  - `FINAL_APPROVAL`
  - `APPROVED`
  - `CANCELLED`

- Supported actions now include:
  - `SUBMIT`
  - `FORWARD`
  - `RETURN`
  - `APPROVE`
  - `CANCEL`

- Important behavior:
  - supplementary lines can only be edited while the workflow is still effectively at draft
  - final approval still runs the existing budget-control validations before updating authorized amounts
  - cancel access was tightened to reviewer-level handling

Key files:
- `backend-php/app/Controllers/BudgetExecutionController.php`
- `backend-php/app/Models/BudgetExecutionModel.php`
- `backend-php/app/Views/execution/Supplementaries.php`
- `backend-php/config/routes.php`

### 4. RIE Retrofitted to Shared Workflow

- `RIE` now also uses the shared workflow engine rather than a simple header status-only approval path.
- Current staged flow is:
  - `DRAFT`
  - `TECHNICAL_REVIEW`
  - `FINAL_APPROVAL`
  - `APPROVED`
  - `CANCELLED`

- Supported actions now include:
  - `SUBMIT`
  - `FORWARD`
  - `RETURN`
  - `APPROVE`
  - `CANCEL`

- Important behavior:
  - RIE lines can only be edited while the RIE is still at draft stage
  - approval still validates available released balance before final approval is posted
  - cancel access was tightened to reviewer-level handling

Key files:
- `backend-php/app/Controllers/BudgetExecutionController.php`
- `backend-php/app/Models/BudgetExecutionModel.php`
- `backend-php/app/Views/execution/Rie.php`
- `backend-php/config/routes.php`

### 5. Workflow Engine Admin Module Built

- A new admin area now exists for maintaining the shared workflow engine inside the application.

- New capabilities now available:
  - workflow definition register
  - workflow definition form
  - workflow stage form
  - workflow action form
  - workflow diagnostics screen for assignment resolution by scope and hierarchy

- The diagnostics screen is important because it shows:
  - the DataObject scope chain used during resolution
  - each attempted scope/context combination
  - the final resolved assignees if a match exists

Key files:
- `backend-php/app/Controllers/WorkflowEngineAdminController.php`
- `backend-php/app/Models/WorkflowEngineAdminModel.php`
- `backend-php/app/Views/config/WorkflowEngineList.php`
- `backend-php/app/Views/config/WorkflowEngineForm.php`
- `backend-php/app/Views/config/WorkflowEngineStageForm.php`
- `backend-php/app/Views/config/WorkflowEngineActionForm.php`
- `backend-php/app/Views/config/WorkflowEngineDiagnostics.php`

### 6. Menu / Navigation Wiring

- The new workflow engine admin is now wired into:
  - `backend-php/config/routes.php`
  - `backend-php/config/menu.php`
  - `backend-php/config/quick_links.php`

- New route family:
  - `workflow-engine/list`
  - `workflow-engine/form`
  - `workflow-engine/save`
  - `workflow-engine/archive`
  - `workflow-engine/stage-form`
  - `workflow-engine/save-stage`
  - `workflow-engine/archive-stage`
  - `workflow-engine/action-form`
  - `workflow-engine/save-action`
  - `workflow-engine/archive-action`
  - `workflow-engine/diagnostics`

## Database State

### Applied to Development Database

- Confirmed local DB connectivity to:
  - `CBMSv2`

- Applied successfully on 2026-05-21:
  - `backend-php/config/sql/create_workflow_engine_foundation_v1.sql`

- Verified after apply:
  - `BE_RIE` stages include `CANCELLED`
  - `BE_RIE` actions include:
    - cancel from draft / technical review / final approval / approved
    - return from final approval to technical review

### Existing Execution Context Still Used

- Current working development context remains:
  - `FiscalYearID = 2026`
  - submission version `5`
  - execution version `6`

## Verification Completed

### Syntax

- `php -l` passed on all touched workflow engine, execution, route, menu, and quick link files.

### Database

- Workflow foundation SQL executed successfully against `CBMSv2`.

### Functional Verification Completed from Terminal

- Verified seeded workflow definition data exists after migration.
- Verified `BE_RIE` stage and action records exist in the database.

### Not Yet Completed

- No authenticated browser click-through has been done yet for:
  - `Workflow Engine`
  - `Workflow Engine Diagnostics`
  - `Execution > Supplementaries` full staged workflow
  - `Execution > RIE` full staged workflow

## Current Workflow Screens Available

- Workflow task/inbox area:
  - `workflow/list`

- Workflow assignment admin:
  - `workflow-assignments/list`

- New shared workflow engine admin:
  - `workflow-engine/list`
  - `workflow-engine/diagnostics`

## Important Current Behavior Notes

### Shared Workflow Model

- The platform direction is now clearly:
  - approvals should be modeled through the shared workflow engine
  - module-specific direct approvals should be reduced over time
  - routing should resolve by `DataObjectHierarchy` wherever applicable

### Supplementaries and RIE

- These two execution documents are now the first live execution transactions on the shared engine.
- Their financial posting logic remains in `BudgetExecutionModel`; only the approval path has been upgraded.

### Workflow Engine Admin

- The admin module now supports definition, stage, and action maintenance in the UI.
- It does not yet include:
  - drag/drop workflow design
  - visual diagramming
  - bulk clone/copy workflow templates
  - assignment simulation from a selected live workflow instance

## Residual Risks / Gaps

1. `Warrants`, `Reservations`, and `Commitments` have shared workflow definitions seeded, but they have not yet been retrofitted onto the shared engine the way `Supplementaries` and `RIE` now are.
2. Browser-based end-to-end testing is still needed for the new workflow transitions and admin screens.
3. Maker-checker policy exists structurally in stage metadata, but a broader consistent enforcement pass across all future retrofits is still advisable.
4. Shared workflow definition edits now exist in the UI, so future sessions should be careful about changing workflow area codes already in use by live instances or assignments.
5. There is still no reporting/inquiry layer yet for shared workflow instances across the whole solution beyond the current task and diagnostics screens.

## Suggested First Step Next Session

1. Do a live browser verification pass in the FY 2026 / version 6 context.
2. Test:
   - `Workflow Engine`
   - `Workflow Engine Diagnostics`
   - `Execution > Supplementaries`
   - `Execution > RIE`
3. Confirm that assignment resolution behaves correctly for real DataObject scopes.

Reason:
- the code and DB scaffolding are now in place
- the biggest remaining risk is UI/runtime behavior rather than missing backend structure

## Suggested Follow-On Work

1. Retrofit `Commitments` to the shared workflow engine next.
2. Retrofit `Warrants` and `Reservations` after `Commitments`.
3. Add a workflow instance inquiry/reporting screen for enterprise oversight.
4. Add stronger maker-checker enforcement and workflow actor restrictions where needed.
5. Consider whether workflow definitions should later support controlled cloning/versioning rather than direct in-place edits only.

## Related Design / Reference Files

- `WORKFLOW_ENGINE_DESIGN.md`
- `BUDGET_EXECUTION_MODULE_DESIGN.md`
- `BUDGET_REDUCTION_WIZARD_DESIGN.md`
- `HANDOFF_NOTES_2026-05-20.md`

