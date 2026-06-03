# Role And Permission Refinement

This pass keeps the current role framework in place, but makes permissions the authoritative enforcement layer.

## What changed

- Added module permissions for base config, financial config, estimates, strategy, submissions, and publishing.
- Switched the main Strategy, Configuration, Financial/Calculation, Estimates, and related admin controllers to permission-based ACLs.
- Updated the menu so key screens follow permissions instead of old role-name assumptions.
- Replaced hardcoded funding-submission and publication role checks with permission checks.

## Target permission groups

- `BASE_CONFIG_VIEW`, `BASE_CONFIG_EDIT`
- `FIN_CONFIG_VIEW`, `FIN_CONFIG_EDIT`
- `CALC_ADMIN`
- `ESTIMATES_VIEW`, `ESTIMATES_EDIT`
- `STRATEGY_VIEW`
- `STRATEGY_CONFIG_EDIT`
- `STRATEGY_SETUP_EDIT`
- `STRATEGY_PERFORMANCE_EDIT`
- `STRATEGY_DELIVERY_EDIT`
- `STRATEGY_GOVERNANCE_EDIT`
- `STRATEGY_FISCAL_EDIT`
- `STRATEGY_REPORT_VIEW`
- `STRATEGY_WORKFLOW_EDIT`
- `STRATEGY_SUBMISSION_PREPARE`
- `STRATEGY_SUBMISSION_REVIEW`
- `STRATEGY_SUBMISSION_APPROVE`
- `STRATEGY_PUBLISH`

## Current role guidance

- `Admin`: broad operational admin role, retains full access.
- `SysAdmin`: still supported; currently overlaps heavily with `Admin`.
- `Config`: base and financial configuration, plus Strategy configuration and publication.
- `Strategy`: full Strategy module working role.
- `Reports`: read-focused strategic reporting role.
- `Analytics`: analytics/scenario-results access.
- `Estimates`: transaction input and estimate execution.
- `DataObjects`: data object code administration.
- `Workflow`: workflow management, plus diagnostics support where needed.
- `FiscalFramework` and `Manager`: currently kept as transitional roles with targeted read/fiscal access.

## Roles that should be reviewed later

- `SysAdmin`: candidate to merge into `Admin` after confirming no user depends on it exclusively.
- `Manager`: likely replace with a clearer reporting/approver role.
- `FiscalFramework`: likely merge into `Strategy` or split into a dedicated fiscal role.
- `User`: currently very thin and may remain as a baseline login role only.
- `Test Role`: likely removable after confirming it is unused.
- `Execution`: no strong permission model was added in this pass because the execution area still looks incomplete.

## Migration

Run:

- [sync_role_permission_model_v1.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/sync_role_permission_model_v1.sql)

That script:

- inserts the missing permission codes
- updates permission descriptions
- maps the new permissions onto existing roles without deleting any current rows

## Important note

This pass intentionally does not delete roles or remove legacy permissions. It makes access control safer first. Role consolidation should happen only after reviewing live users and confirming which roles are still operationally needed.
