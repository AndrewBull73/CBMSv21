# Handoff Notes - Budget Execution, Shared UI Standard, and RIE (2026-05-20)

## Scope Completed Recently

- Standardized the shared screen shell and Quick Links behavior so new screens align with the `strategy-config/configuration-readiness` pattern.
- Continued Budget Execution module development from setup into transactional execution areas.
- Implemented and tested:
  - `Warrants`
  - `Reservations`
  - `Commitments`
  - `RIE (Request to Incur Expenditure)`
- Extended Budget Execution rollover/opening-balance support so execution testing can start from a real approved baseline.

## Major Functional Changes

### 1. Shared UI Standard

- The project now has a documented shared screen standard in:
  - `SCREEN_UI_STANDARD.md`
- The chosen canonical reference screen is:
  - `strategy-config/configuration-readiness`

- Shared UI rules now include:
  - title-only card headers
  - centralized Quick Links
  - optional Helper Instructions
  - shared compact button sizing
  - smaller standardized form input and label sizing

- Quick Links are now centrally defined in:
  - `backend-php/config/quick_links.php`

- Shared rendering is handled through:
  - `backend-php/app/Views/strategy/_QuickNav.php`
  - `backend-php/app/Views/layouts/main.php`

### 2. Versioning / Execution Context

- `tblVersions` remains the single version master.
- `VersionTypeID` is used to distinguish:
  - `SUBMISSION`
  - `EXECUTION`

- The version-typing migration had already been applied before this handoff.
- Execution continues to use:
  - one execution version per fiscal year in normal client practice
  - lineage back to the source submission version through `BaseFiscalYearID` and `BaseVersionID`

### 3. Budget Execution Foundation

- Execution setup and rollover were implemented earlier and remain active.
- Opening balances are created from an approved submission version into an execution version before downstream execution testing.

- Foundation tables already in use:
  - `dbo.tblBeExecutionRolloverRun`
  - `dbo.tblBeExecutionOpeningBalance`

- Verified seeded test context currently used for development:
  - `FiscalYearID = 2026`
  - submission version `5`
  - execution version `6`

### 4. Budget Execution Functional Areas Now Implemented

#### Warrants

- Implemented as the release-of-authority layer.
- Warrant lines consume `CurrentAuthorizedAmount` capacity but only approved warrants count as released authority for downstream controls.

- Main files:
  - `backend-php/config/sql/create_budget_execution_warrants_v1.sql`
  - `backend-php/app/Views/execution/Warrants.php`

#### Reservations

- Implemented as the funds-earmarking layer after released authority exists.
- Reservation controls now account for later commitments as part of remaining available released balance logic.

- Main files:
  - `backend-php/config/sql/create_budget_execution_reservations_v1.sql`
  - `backend-php/app/Views/execution/Reservations.php`

#### Commitments

- Implemented as the formal obligation layer.
- Commitments reduce available released balance.
- Commitments can now optionally link to an approved RIE.

- Main files:
  - `backend-php/config/sql/create_budget_execution_commitments_v1.sql`
  - `backend-php/app/Views/execution/Commitments.php`

#### RIE

- Implemented because the client needs a pre-commitment approval step.
- RIE is treated as:
  - a request/approval instrument
  - not a budget-consuming transaction by itself
  - an optional control source for linked commitments

- Approved RIEs can now be linked from commitment headers.
- Linked commitments are validated against the remaining approved RIE amount.

- Main files:
  - `backend-php/config/sql/create_budget_execution_rie_v1.sql`
  - `backend-php/app/Views/execution/Rie.php`

## Key Shared Files Touched

- `backend-php/app/Controllers/BudgetExecutionController.php`
- `backend-php/app/Models/BudgetExecutionModel.php`
- `backend-php/config/routes.php`
- `backend-php/config/menu.php`
- `backend-php/config/quick_links.php`
- `backend-php/app/Views/layouts/main.php`
- `SCREEN_UI_STANDARD.md`
- `SCREEN_UI_REVIEW.md`
- `BUDGET_EXECUTION_MODULE_DESIGN.md`

## Budget Execution Screens Currently Available

- `execution/index`
  - Setup & Rollover
- `execution/opening-balances`
  - Opening Balances
- `execution/warrants`
  - Warrants
- `execution/reservations`
  - Reservations
- `execution/rie`
  - RIE
- `execution/commitments`
  - Commitments

## Current Control Logic

- `Warrants` release authority.
- `Reservations` earmark released authority.
- `RIE` approves intended expenditure but does not consume budget by itself.
- `Commitments` create the financial obligation and reduce available released balance.

- Opening balance summaries now expose:
  - `OpeningAmountTotal`
  - `CurrentAuthorizedAmountTotal`
  - `ReleasedAmountTotal`
  - `ReservedAmountTotal`
  - `CommittedAmountTotal`
  - `AvailableReleasedAmountTotal`

## Verification Completed

### Syntax / Code Health

- `php -l` passed on the touched execution controller, model, view, route, menu, and Quick Links files.

### Database / Migration

- The following execution migrations are now applied:
  - `create_budget_execution_foundation_v1.sql`
  - `create_budget_execution_warrants_v1.sql`
  - `create_budget_execution_reservations_v1.sql`
  - `create_budget_execution_commitments_v1.sql`
  - `create_budget_execution_rie_v1.sql`

### Budget Execution Functional Verification

- Verification was performed inside rolled-back SQL transactions so no test data was left behind.

- Proven flow for commitments:
  - approved warrant release: `25,000.00`
  - approved reservation: `5,000.00`
  - approved commitment: `7,000.00`
  - remaining available released: `13,000.00`

- Proven flow for RIE plus linked commitment:
  - approved warrant release: `25,000.00`
  - approved reservation: `5,000.00`
  - approved RIE: `15,000.00`
  - approved commitment linked to RIE: `7,000.00`
  - remaining approved RIE amount: `8,000.00`
  - available released balance remained correct at `13,000.00`

## Important Current Behavior Notes

### Quick Links

- Quick Links are optional.
- Helper Instructions are optional.
- Quick Links are centrally configured and should not be duplicated as card-header navigation.

### Commitments / RIE Relationship

- RIE is currently optional for commitment creation.
- If linked, the commitment is validated against the approved RIE amount.
- If not linked, commitment control falls back to released/reserved/committed balance logic only.

### UI Consistency

- New screens should follow the shared shell, not add custom local navigation or sizing unless there is a strong reason.
- Budget Execution screens have been refactored to match the project UI standard more closely.

## Residual Risks / Gaps

1. `Supplementary Budgets` are still not implemented yet.
2. `Reallocations` and `Virements` are still placeholders in the menu.
3. `RIE` currently uses a simple `DRAFT / APPROVED / CANCELLED` lifecycle only.
4. `RIE` does not yet support richer workflow states such as:
   - `SUBMITTED`
   - `REJECTED`
   - `RETURNED`
5. No execution reporting screens have been built yet for:
   - budget vs released
   - released vs reserved
   - reserved vs committed
   - RIE vs commitment utilization
6. End-to-end browser click-throughs are still desirable across all new execution screens using authenticated real-user flows.

## Suggested First Step Next Session

1. Resume Budget Execution development.
2. Build `Supplementary Budgets` next.

Reason:
- it is the biggest remaining execution adjustment area
- it fits directly with the existing opening balance / warrant / reservation / commitment chain
- it will help complete the core executable budget lifecycle before transfers and reporting

## Suggested Follow-On Work

1. Implement `Supplementary Budgets`.
2. Implement `Reallocations`.
3. Implement `Virements`.
4. Decide whether `RIE` needs a richer approval workflow or whether the current lightweight design is sufficient for the first client rollout.
5. Add execution reporting and inquiry screens after the remaining transaction areas are in place.

## Later Design Decisions Captured After This Handoff

- `Supplementary Budgets` were later implemented for adjustments against existing execution balance lines.
- Brand-new budget line creation from scratch in supplementaries should wait until the Budget Submission Input Sheet and shared budget line model are finalized.
- Bulk budget cuts should use a dedicated `Budget Reduction Wizard` rather than manual negative supplementary lines one by one.
- The recommended model is:
  - `Supplementary Budget` remains the posting and approval instrument
  - `Budget Reduction Wizard` generates negative supplementary lines from filtered execution balance lines
- A later targeted feature should also support:
  - `Specific Budget Line Reduction`

- Platform workflow direction was later clarified further:
  - approvals should not be built separately inside each module
  - a shared enterprise workflow engine is required across the whole solution
  - workflows must support configurable `multiple stages`
  - workflows should in most cases route and inherit by `DataObjectHierarchy`

- Related design note:
  - `BUDGET_REDUCTION_WIZARD_DESIGN.md`
  - `WORKFLOW_ENGINE_DESIGN.md`

- Shared workflow phase 1 foundation was later scaffolded with:
  - `backend-php/config/sql/create_workflow_engine_foundation_v1.sql`
  - `backend-php/app/Models/WorkflowEngineModel.php`
  - dynamic workflow area/stage loading in `WorkflowAssignmentModel`
  - workflow assignment form/list filtering by area-driven stages
  - base configuration readiness check coverage for the workflow engine foundation
