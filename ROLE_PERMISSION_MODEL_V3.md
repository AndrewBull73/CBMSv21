# CBMSv21 Role And Permission Model V3

This version moves from broad shared roles toward business-facing roles aligned to the main functional areas in the menu.

It builds on:

- [ROLE_PERMISSION_MODEL_V2.md](C:/xampp82/htdocs/CBMSv21/ROLE_PERMISSION_MODEL_V2.md)
- [STRATEGIC_FRAMEWORK_ACCESS_MATRIX.md](C:/xampp82/htdocs/CBMSv21/STRATEGIC_FRAMEWORK_ACCESS_MATRIX.md)
- [FUNCTIONAL_AREA_ACCESS_MATRICES.md](C:/xampp82/htdocs/CBMSv21/FUNCTIONAL_AREA_ACCESS_MATRICES.md)

## Design goal

Admins should be able to assign roles based on the system’s main business areas:

- Strategic Framework
- Budget Submission
- Budget Execution
- Reporting
- Analytics
- Dashboards
- Administration
- Configuration

Permissions remain the technical enforcement layer underneath those roles.

## Target role set

### Platform-wide roles

- `System Administrator`
- `Configuration Administrator`

`Super Admin` is now treated as a legacy transition role and should not be used for new assignments. The intention is for `System Administrator` to be the primary full-access platform role.

### Strategic Framework

- `Strategic Framework User`
- `Strategic Framework Reviewer`
- `Strategic Framework Approver`
- `Strategic Framework Reporting User`
- `Strategic Framework Administrator`

### Budget Submission

- `Budget Submission User`
- `Budget Submission Reviewer`
- `Budget Submission Approver`
- `Budget Submission Administrator`

### Budget Execution

- `Budget Execution User`
- `Budget Execution Reviewer`
- `Budget Execution Administrator`

These are created now even though execution remains lightly implemented.

### Reporting

- `Reporting User`
- `Reporting Administrator`

### Analytics

- `Analytics User`
- `Analytics Administrator`

### Dashboards

- `Dashboard User`
- `Dashboard Administrator`

### Configuration specializations

- `Base Configuration Administrator`
- `Financial Configuration Administrator`
- `Strategy Configuration Administrator`

## Mapping principle

Where mature permissions already exist, the new roles map to those current permissions.

Where areas still rely on legacy role gating, the new roles are created now and wired into the menu so they can be assigned safely ahead of deeper controller refactoring.

That especially applies to:

- `Budget Execution`
- `Dashboards`

## Important implementation note

This version does not remove older roles.

It:

- creates the new functional-area roles
- maps them to the current permission set where possible
- keeps legacy roles active for transition
- adds review output to help plan user reassignment

## Migration script

Run:

- [sync_role_permission_model_v3.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/sync_role_permission_model_v3.sql)
