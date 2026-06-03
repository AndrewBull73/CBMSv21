# Handoff Notes - Execution Workflow Retrofits (2026-05-22)

## Session Summary

- Resumed from `HANDOFF_NOTES_2026-05-21.md`.
- Retrofitted `Budget Execution Commitments` onto the shared workflow engine pattern already used by `Supplementaries` and `RIE`.
- Retrofitted `Budget Execution Warrants` onto the same shared workflow engine pattern.
- Retrofitted `Budget Execution Reservations` onto the same shared workflow engine pattern.
- Added a new `Workflow Engine Inquiry` screen for cross-document workflow inquiry and reporting.
- Realigned the `Workflow Engine` screens to the documented shared UI standard and shared Quick Links shell.
- Extended the workflow seed SQL so `BE_COMMITMENT` now supports cancellation and return from final approval as well as the existing submit, forward, approve, and return transitions.
- Extended the workflow seed SQL so `BE_WARRANT` now supports cancellation and return from final approval as well as the existing submit, forward, approve, and return transitions.
- Extended the workflow seed SQL so `BE_RESERVATION` now supports cancellation and return from final approval as well as the existing submit, forward, approve, and return transitions.
- Applied the updated workflow seed SQL to the development database and verified the new commitment, warrant, and reservation workflow records exist.

## Major Outcomes Completed

### 1. Commitment Screen Now Reads Shared Workflow State

- `Commitments` now load workflow panel state when the shared workflow engine is available.
- Commitment register rows now show the workflow stage label where applicable.
- The selected commitment panel now shows:
  - workflow stage
  - workflow record id
  - workflow-aware action buttons
  - recent workflow history

Key files:

- `backend-php/app/Controllers/BudgetExecutionController.php`
- `backend-php/app/Models/BudgetExecutionModel.php`
- `backend-php/app/Views/execution/Commitments.php`

### 2. Commitment Workflow Actions Added

- Added controller routes/actions for:
  - `submit-commitment`
  - `forward-commitment`
  - `return-commitment`

- These now mirror the shared execution workflow pattern used for:
  - `Supplementaries`
  - `RIE`

Key file:

- `backend-php/config/routes.php`

### 3. Commitment Editability Now Respects Workflow Stage

- Commitment lines can no longer be added or removed merely because the header status is still `DRAFT`.
- Editability now also respects the workflow stage, so only:
  - header status `DRAFT`
  - workflow stage `DRAFT`

allow line editing.

This aligns commitment behavior with the existing shared workflow retrofit approach.

### 4. Commitment Approval And Cancellation Now Integrate With Workflow

- Approval now checks:
  - at least one line exists
  - the commitment is still `DRAFT`
  - the workflow instance has reached `FINAL_APPROVAL`
  - balance validations still pass at approval time
  - linked RIE availability still passes at approval time

- Cancellation now:
  - requires reviewer-level access in the controller
  - updates the commitment header
  - transitions the workflow instance through `CANCEL` when workflow is active

### 5. Commitment Workflow Instance Support Added

- Added workflow support methods for commitments:
  - annotate commitments with workflow stage
  - load workflow panel state
  - ensure workflow instance exists
  - infer workflow scope from commitment lines
  - fall back to linked RIE scope where useful
  - keep old approved/cancelled records from being initialized incorrectly at `DRAFT`

### 6. Warrant Screen Now Reads Shared Workflow State

- `Warrants` now load workflow panel state when the shared workflow engine is available.
- Warrant register rows now show the workflow stage label where applicable.
- The selected warrant panel now shows:
  - workflow stage
  - workflow record id
  - workflow-aware action buttons
  - recent workflow history

Key files:

- `backend-php/app/Controllers/BudgetExecutionController.php`
- `backend-php/app/Models/BudgetExecutionModel.php`
- `backend-php/app/Views/execution/Warrants.php`

### 7. Warrant Workflow Actions Added

- Added controller routes/actions for:
  - `submit-warrant`
  - `forward-warrant`
  - `return-warrant`

- These now mirror the shared execution workflow pattern used for:
  - `Supplementaries`
  - `RIE`
  - `Commitments`

Key file:

- `backend-php/config/routes.php`

### 8. Warrant Editability Now Respects Workflow Stage

- Warrant lines can no longer be added or removed merely because the header status is still `DRAFT`.
- Editability now also respects the workflow stage, so only:
  - header status `DRAFT`
  - workflow stage `DRAFT`

allow line editing.

This aligns warrant behavior with the existing shared workflow retrofit approach.

### 9. Warrant Approval And Cancellation Now Integrate With Workflow

- Approval now checks:
  - at least one line exists
  - the warrant is still `DRAFT`
  - the workflow instance has reached `FINAL_APPROVAL`

- Cancellation now:
  - requires reviewer-level access in the controller
  - updates the warrant header
  - transitions the workflow instance through `CANCEL` when workflow is active

### 10. Warrant Workflow Instance Support Added

- Added workflow support methods for warrants:
  - annotate warrants with workflow stage
  - load workflow panel state
  - ensure workflow instance exists
  - infer workflow scope from warrant lines
  - preserve prior workflow scope if a historic warrant cannot infer one cleanly
  - keep old approved/cancelled records from being initialized incorrectly at `DRAFT`

### 11. Reservation Screen Now Reads Shared Workflow State

- `Reservations` now load workflow panel state when the shared workflow engine is available.
- Reservation register rows now show the workflow stage label where applicable.
- The selected reservation panel now shows:
  - workflow stage
  - workflow record id
  - workflow-aware action buttons
  - recent workflow history

Key files:

- `backend-php/app/Controllers/BudgetExecutionController.php`
- `backend-php/app/Models/BudgetExecutionModel.php`
- `backend-php/app/Views/execution/Reservations.php`

### 12. Reservation Workflow Actions Added

- Added controller routes/actions for:
  - `submit-reservation`
  - `forward-reservation`
  - `return-reservation`

- These now mirror the shared execution workflow pattern used for:
  - `Supplementaries`
  - `RIE`
  - `Commitments`
  - `Warrants`

Key file:

- `backend-php/config/routes.php`

### 13. Reservation Editability Now Respects Workflow Stage

- Reservation lines can no longer be added or removed merely because the header status is still `DRAFT`.
- Editability now also respects the workflow stage, so only:
  - header status `DRAFT`
  - workflow stage `DRAFT`

allow line editing.

This aligns reservation behavior with the existing shared workflow retrofit approach.

### 14. Reservation Approval And Cancellation Now Integrate With Workflow

- Approval now checks:
  - at least one line exists
  - the reservation is still `DRAFT`
  - the workflow instance has reached `FINAL_APPROVAL`
  - released-balance capacity still passes at approval time

- Cancellation now:
  - requires reviewer-level access in the controller
  - updates the reservation header
  - transitions the workflow instance through `CANCEL` when workflow is active

### 15. Reservation Workflow Instance Support Added

- Added workflow support methods for reservations:
  - annotate reservations with workflow stage
  - load workflow panel state
  - ensure workflow instance exists
  - infer workflow scope from reservation lines
  - preserve prior workflow scope if a historic reservation cannot infer one cleanly
  - keep old approved/cancelled records from being initialized incorrectly at `DRAFT`

### 16. Workflow Inquiry And Reporting Screen Added

- Added a new `Workflow Engine Inquiry` screen under the workflow engine admin area.
- The new screen provides:
  - cross-workflow filtering by workflow area, stage, fiscal year, version, scope, assignee, state bucket, and free-text search
  - summary counts for matching, open, approved, and cancelled workflow instances
  - a live result register of workflow instances across modules
  - a selected-instance detail pane with workflow metadata, current assignments, allowed next actions, and full workflow history

- The workflow engine admin screens now link into the new inquiry view from:
  - workflow definition list
  - workflow definition form
  - workflow diagnostics

Key files:

- `backend-php/app/Controllers/WorkflowEngineAdminController.php`
- `backend-php/app/Models/WorkflowEngineAdminModel.php`
- `backend-php/app/Views/config/WorkflowEngineInquiry.php`
- `backend-php/app/Views/config/WorkflowEngineList.php`
- `backend-php/app/Views/config/WorkflowEngineDiagnostics.php`
- `backend-php/app/Views/config/WorkflowEngineForm.php`
- `backend-php/config/routes.php`

### 17. Workflow Engine Screens Realigned To Shared UI Standard

- Added `workflow-engine/` into the shared layout route family so workflow engine pages now use the shared `strategy-ui` shell styles.
- Added a dedicated shared Quick Links group for:
  - workflow engine list
  - workflow inquiry
  - workflow diagnostics
  - workflow assignments

- Removed duplicated sibling-screen navigation from workflow engine page bodies so navigation now follows the shared Quick Links pattern introduced on `2026-05-20`.
- Added the standard in-card `Current context` line to the workflow engine admin views.
- Brought workflow engine list/form/diagnostics/inquiry screens closer to the documented `SCREEN_UI_STANDARD.md` structure with:
  - context line
  - metric cards
  - single helper alert
  - standard section cards
  - compact action buttons

Key files:

- `backend-php/app/Views/layouts/main.php`
- `backend-php/config/quick_links.php`
- `backend-php/app/Controllers/WorkflowEngineAdminController.php`
- `backend-php/app/Views/config/WorkflowEngineList.php`
- `backend-php/app/Views/config/WorkflowEngineForm.php`
- `backend-php/app/Views/config/WorkflowEngineDiagnostics.php`
- `backend-php/app/Views/config/WorkflowEngineInquiry.php`
- `backend-php/app/Views/config/WorkflowEngineStageForm.php`
- `backend-php/app/Views/config/WorkflowEngineActionForm.php`

## Database State

### Applied To Development Database

- Development database:
  - `CBMSv2`

- Re-applied:
  - `backend-php/config/sql/create_workflow_engine_foundation_v1.sql`

- Verified `BE_COMMITMENT` now includes:
  - stage `CANCELLED`
  - action `RETURN` from `FINAL_APPROVAL` to `TECHNICAL_REVIEW`
  - action `CANCEL` from:
    - `DRAFT`
    - `TECHNICAL_REVIEW`
    - `FINAL_APPROVAL`
    - `APPROVED`

- Verified `BE_WARRANT` now includes:
  - stage `CANCELLED`
  - action `RETURN` from `FINAL_APPROVAL` to `TECHNICAL_REVIEW`
  - action `CANCEL` from:
    - `DRAFT`
    - `TECHNICAL_REVIEW`
    - `FINAL_APPROVAL`
    - `APPROVED`

- Verified `BE_RESERVATION` now includes:
  - stage `CANCELLED`
  - action `RETURN` from `FINAL_APPROVAL` to `TECHNICAL_REVIEW`
  - action `CANCEL` from:
    - `DRAFT`
    - `TECHNICAL_REVIEW`
    - `FINAL_APPROVAL`
    - `APPROVED`

### No Additional Database Changes Required

- The new workflow inquiry/reporting screen uses the existing:
  - `tblWorkflowInstance`
  - `tblWorkflowInstanceHistory`
  - workflow definition and assignment tables

- No schema or seed changes were needed for this screen.

## Verification Completed

### Syntax

- `php -l` passed on:
  - `backend-php/app/Controllers/BudgetExecutionController.php`
  - `backend-php/app/Models/BudgetExecutionModel.php`
  - `backend-php/app/Views/execution/Commitments.php`
  - `backend-php/app/Views/execution/Warrants.php`
  - `backend-php/app/Views/execution/Reservations.php`
  - `backend-php/app/Controllers/WorkflowEngineAdminController.php`
  - `backend-php/app/Models/WorkflowEngineAdminModel.php`
  - `backend-php/app/Views/config/WorkflowEngineInquiry.php`
  - `backend-php/app/Views/config/WorkflowEngineList.php`
  - `backend-php/app/Views/config/WorkflowEngineDiagnostics.php`
  - `backend-php/app/Views/config/WorkflowEngineForm.php`
  - `backend-php/app/Views/config/WorkflowEngineStageForm.php`
  - `backend-php/app/Views/config/WorkflowEngineActionForm.php`
  - `backend-php/app/Views/layouts/main.php`
  - `backend-php/config/quick_links.php`
  - `backend-php/config/routes.php`

### Database

- Confirmed `BE_COMMITMENT` workflow stage:
  - `CANCELLED`

- Confirmed `BE_COMMITMENT` cancel action count:
  - `4`

- Confirmed `BE_WARRANT` workflow stage:
  - `CANCELLED`

- Confirmed `BE_WARRANT` cancel action count:
  - `4`

- Confirmed `BE_WARRANT` final approval return action count:
  - `1`

- Confirmed `BE_RESERVATION` workflow stage:
  - `CANCELLED`

- Confirmed `BE_RESERVATION` cancel action count:
  - `4`

- Confirmed `BE_RESERVATION` final approval return action count:
  - `1`

## Not Yet Completed

- No authenticated browser click-through has yet been performed for:
  - `Execution > Commitments` full staged workflow
  - `Execution > Warrants` full staged workflow
  - `Execution > Reservations` full staged workflow
  - `Workflow Engine`
  - `Workflow Engine Inquiry`
  - `Workflow Engine Diagnostics`
  - `Execution > Supplementaries`
  - `Execution > RIE`

## Current Recommended Next Step

1. Do a live browser verification pass in FY `2026`, execution version `6`.
2. Test:
  - `Execution > Commitments`
  - `Execution > Warrants`
  - `Execution > Reservations`
  - `Workflow Engine Inquiry`
  - submit
  - forward
  - return
  - approve
  - cancel
3. Confirm workflow assignment resolution behaves correctly when scope is inferred from real balance lines for:
  - commitments
  - warrants
  - reservations
4. Confirm inquiry filters and history drill-through behave correctly for:
  - open items
  - approved items
  - cancelled items
  - assigned-user filtering

## Suggested Follow-On Development

1. Review maker-checker enforcement consistently across all execution workflow stages.
2. Add export or printable reporting support to `Workflow Engine Inquiry` if users need offline reporting.
3. Do an authenticated browser/UAT pass across all execution workflow-enabled documents and the workflow admin inquiry screens.

## Important Notes

- Commitment, warrant, and reservation workflow retrofits intentionally follow the `RIE` and `Supplementary` implementation pattern rather than inventing a new variant.
- The workflow seed SQL is now ahead of the 2026-05-21 handoff for `BE_COMMITMENT`, `BE_WARRANT`, and `BE_RESERVATION`; future environments should pick up the richer workflow behavior automatically when the foundation script is run.
- The new workflow inquiry screen intentionally stays focused on workflow state, routing, and history. It does not yet deep-link into every source document screen because some source routes still depend on active fiscal/version context.
