# CBMSv21 Role And Permission Model V2

This version refines access around the main business modules of CBMSv21:

- Strategic Framework
- Budget Submission
- Budget Execution
- Administration
- Configuration
- Reporting
- Analytics

The design principle is:

- roles are simple and business-friendly
- permissions remain the real technical enforcement layer
- menus and controllers should continue to enforce permissions, not role names

## Target roles

### `Super Admin`

Full platform access, including override and high-risk administration utilities.

Use for a very small number of trusted platform owners only.

### `Configuration Administrator`

Responsible for:

- base configuration
- financial and calculation configuration
- strategy configuration
- system settings needed for configuration work

This role should not be the main user-administration role.

### `System Administrator`

Responsible for:

- users
- roles
- access matrix
- audit
- sessions
- monitoring
- workflow administration
- logs
- diagnostics
- system messages

This role is for platform operations and support.

### `Data Input User`

Responsible for day-to-day business data entry across the currently implemented planning areas.

This role covers:

- Strategic Framework maintenance
- Budget Submission preparation
- estimate input

At this stage it does not include Budget Execution because that module has not yet been implemented.

### `Reporting User`

Read-focused access to:

- strategic summary
- readiness dashboards
- strategy reports
- published and review-oriented views

This role should not be able to change setup or transactional data.

### `Analytics User`

Access to:

- analytics overview
- scenario results
- scenario comparison

This role is intentionally separate from general reporting because analytics users are often a smaller specialist group.

### `Workflow Reviewer`

Responsible for reviewing submitted budget items and moving them through review stages where permitted.

This role is distinct from data entry so that review can be assigned independently.

### `Workflow Approver`

Responsible for approval and publication-related workflow actions.

This role should normally be tightly controlled and assigned sparingly.

## Future role

### `Budget Execution User`

This should be introduced when the execution module is implemented.

For now it is intentionally not created in the migration script.

## Permission bundles by role

### `Super Admin`

- all permissions in the implemented platform areas
- `ADMIN_ALL`
- `SYSADMIN`

### `Configuration Administrator`

- `BASE_CONFIG_VIEW`
- `BASE_CONFIG_EDIT`
- `SYSSETTINGS_VIEW`
- `SYSSETTINGS_EDIT`
- `SYSSETTINGS_ADMIN`
- `FIN_CONFIG_VIEW`
- `FIN_CONFIG_EDIT`
- `CALC_ADMIN`
- `STRATEGY_CONFIG_EDIT`
- `STRATEGY_PUBLISH`

### `System Administrator`

- `USERS_VIEW`
- `USERS_EDIT`
- `USERS_ADMIN`
- `ROLES_VIEW`
- `ROLES_ADMIN`
- `AUDIT_VIEW`
- `HEALTH_VIEW`
- `SESSION_VIEW`
- `SESSION_ADMIN`
- `DIAG_VIEW`
- `LOGS_VIEW`
- `ERRORLOG_VIEW`
- `ERRORLOG_ADMIN`
- `METRICS_VIEW`
- `WORKFLOW_VIEW`
- `WORKFLOW_EDIT`
- `WORKFLOW_ADMIN`
- `DATAOBJECTCODES_ADMIN`

Note:
Some older admin utilities still rely on `SYSADMIN` or `ADMIN_ALL` as a fallback. That can be tightened later once those screens are fully refactored.

### `Data Input User`

- `ESTIMATES_VIEW`
- `ESTIMATES_EDIT`
- `RATES_VIEW`
- `STRATEGY_VIEW`
- `STRATEGY_SETUP_EDIT`
- `STRATEGY_PERFORMANCE_EDIT`
- `STRATEGY_DELIVERY_EDIT`
- `STRATEGY_GOVERNANCE_EDIT`
- `STRATEGY_FISCAL_EDIT`
- `STRATEGY_REPORT_VIEW`
- `STRATEGY_SUBMISSION_PREPARE`

Optional later:
- split this into separate `Strategic Framework Editor` and `Budget Submission Preparer` roles if business ownership becomes more specialized.

### `Reporting User`

- `STRATEGY_VIEW`
- `STRATEGY_REPORT_VIEW`

### `Analytics User`

- `ANALYTICS_VIEW`

### `Workflow Reviewer`

- `STRATEGY_VIEW`
- `STRATEGY_REPORT_VIEW`
- `STRATEGY_SUBMISSION_REVIEW`

### `Workflow Approver`

- `STRATEGY_VIEW`
- `STRATEGY_REPORT_VIEW`
- `STRATEGY_SUBMISSION_APPROVE`
- `STRATEGY_PUBLISH`

## Recommended mapping from current roles

- `Admin` -> review for split between `Super Admin` and `System Administrator`
- `SysAdmin` -> review for `Super Admin`
- `Config` -> `Configuration Administrator`
- `Strategy` -> mostly `Data Input User`, with some users potentially also needing `Workflow Reviewer` or `Workflow Approver`
- `Reports` -> `Reporting User`
- `Analytics` -> `Analytics User`
- `Estimates` -> `Data Input User`
- `FiscalFramework` -> usually `Data Input User` or `Reporting User`, depending the user
- `Manager` -> usually `Reporting User` and possibly `Workflow Approver`
- `DataObjects` -> `System Administrator`
- `Workflow` -> `System Administrator`, `Workflow Reviewer`, or `Workflow Approver` depending the real duty
- `Test Role` -> review for removal
- `User` -> keep only if you still need a very light baseline login role

## Menu alignment guidance

This role model aligns best with a menu grouped around the platform’s main business modules:

- Strategic Framework
- Budget Submission
- Budget Execution
- Reporting
- Analytics
- Configuration
- Administration

Current implementation note:
the permission model can be implemented immediately, even if some menu labels are still transitional.

## Migration approach

Phase 1:

- create the new target roles
- assign permission bundles to them
- keep legacy roles active
- compare live user assignments

Phase 2:

- reassign users to the new roles
- verify menu and controller access
- retire or archive old roles

## Migration script

Run:

- [sync_role_permission_model_v2.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/sync_role_permission_model_v2.sql)

That script:

- creates any missing permissions needed by the new model
- creates the new target roles if they do not already exist
- assigns the target permission bundles
- leaves legacy roles in place
- outputs a legacy-role review report and a user-role summary to support cleanup
