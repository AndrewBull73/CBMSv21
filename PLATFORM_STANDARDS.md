# CBMSv21 Platform Standards

Date: 2026-05-23

## Purpose

Define the minimum structural standards that keep CBMSv21 maintainable as one platform rather than a collection of unrelated screens, scripts, and module-specific patterns.

This standard is intended to:

- reduce architectural drift
- keep new work consistent with the current platform direction
- protect maintainability during and after UAT
- make future onboarding and support easier

This document does **not** require mass renaming of the current platform before UAT. It defines the target standard for:

- all new work
- any file materially touched during maintenance
- controlled post-UAT refactoring

## Core Platform Principles

1. Build one platform, not separate mini-systems.
2. Prefer shared engines over module-specific one-off logic.
3. Keep business document data separate from workflow, approval, and audit data.
4. Use configuration-driven behavior where the client context can vary.
5. Make structural changes deliberately, not opportunistically during testing.
6. Treat maintainability as a design requirement, not a cleanup task for later.

## Current Accepted Baseline

CBMSv21 currently contains several established architectural families:

- `backend-php` for the primary MVC business application
- `scenario-engine` for scenario modelling and calculation execution
- SQL Server schemas built around existing `tbl...` naming families
- shared workflow engine foundations
- shared UI shell and shared Quick Links pattern

These existing families are acceptable. The objective now is to stop further drift, not to pretend the current platform is greenfield.

## Mandatory Standards For New And Touched Code

### 1. Module Structure

Each functional module should follow this general pattern:

- controller under `backend-php/app/Controllers`
- model under `backend-php/app/Models`
- views under `backend-php/app/Views/<module>`
- SQL install or patch scripts under `backend-php/config/sql`
- route entries in `backend-php/config/routes.php`
- menu and Quick Links entries only where the module belongs in shared navigation

Do not build new business features as standalone `public/*.php` tools unless there is a clear operational reason and the page is explicitly guarded.

### 2. Controller Standard

Controllers should be thin orchestration layers.

Controllers should:

- enforce ACL and authentication
- enforce CSRF on state-changing actions
- resolve context such as fiscal year and version
- call models for data access and business operations
- assemble view data
- redirect and flash messages cleanly

Controllers should not:

- hold large SQL blocks
- perform multi-step business rules that belong in models or shared services
- duplicate workflow transition logic
- become the only place where a rule is implemented

### 3. Model Standard

Models are the primary home for:

- SQL
- transactional business operations
- workflow-aware state changes
- support checks for optional schema foundations
- reusable data assembly logic

Model rules:

- use parameterized SQL
- wrap multi-table state changes in transactions
- keep capability checks explicit, for example `supports...Foundation()`
- prefer one authoritative implementation of a rule
- keep write behavior consistent with workflow, audit, and status models

### 4. View Standard

Views must follow the established shared UI shell and structure documented in:

- [SCREEN_UI_STANDARD.md](C:/xampp82/htdocs/CBMSv21/SCREEN_UI_STANDARD.md)

Views should:

- use the shared page header partial where applicable
- use the shared layout shell and shared Quick Links patterns
- keep logic lightweight and presentation-focused
- use escaped output
- rely on the shared render-time standardization layer in `BaseController` so routed screens emit deterministic ids and standard roles even where legacy markup is still being normalized

Views should not:

- implement business rules
- duplicate navigation that the shared shell already provides
- introduce new page shell patterns without a documented reason

For maintainability, every routed screen should satisfy one of these two conditions for key interactive elements such as forms, buttons, action links, fields, tables, cards, alerts, and modals:

- provide an explicit human-readable id in the view markup
- allow the shared standardization layer to generate a deterministic route-based id

Explicit ids remain preferred for high-value workflow, training, inquiry, and transaction-entry paths.

### 5. Workflow Standard

All new approval-style processes must use the shared workflow engine pattern documented in:

- [WORKFLOW_ENGINE_DESIGN.md](C:/xampp82/htdocs/CBMSv21/WORKFLOW_ENGINE_DESIGN.md)

Do not create new module-specific approval stacks when the shared engine can serve the need.

Workflow rules:

- business document tables own document data
- workflow tables own stage, transitions, history, and routing
- maker-checker and actor eligibility must be enforced server-side
- workflow actions must not rely only on UI gating

### 6. Database Access Standard

The application must tolerate optional foundations during staged rollout.

That means:

- support checks are acceptable
- feature flags based on installed tables are acceptable
- hard failure is acceptable only when the feature genuinely depends on the missing foundation

Direct database interaction should remain in models or explicitly designated infrastructure code, not spread across views and controllers.

### 7. Security And Operations Standard

No direct public utility page should remain accessible without clear guard behavior.

Operational rules:

- do not hardcode secrets
- do not expose raw exception detail to end users
- do not rely on hidden routes alone when a direct `public/*.php` file exists
- protect self-posting utility forms with CSRF
- prefer env-driven configuration for external integrations

### 8. File Hygiene Standard

The live app tree must not accumulate ambiguous duplicate files such as:

- `Copy - ...`
- `(...).php`
- `*- Copy.php`

If a file is a backup, experiment, or one-off investigation artifact, it should not remain inside the production application tree.

Temporary analysis utilities should live outside the runtime path or be removed once the work is complete.

### 9. Documentation Standard

When a structural decision affects more than one module, document it in the repo.

Required examples:

- shared workflow behavior
- UI shell conventions
- naming conventions
- role and permission bundling
- testing readiness assumptions

The standard is:

- document once
- reference the standard
- do not re-invent it ad hoc in each module

### 10. Testing Freeze Standard

Once formal UAT begins:

- avoid schema renames
- avoid major route reorganizations
- avoid broad UI shell changes
- avoid approval-state redesigns unless they fix a real defect

Testing should validate a controlled baseline, not a moving target.

## Naming And Structure Rules

### PHP Files

- controllers: `<Area>Controller.php`
- models: `<Area>Model.php`
- views: business-named screen files under the correct module folder

Avoid names that imply drafts or copies in the live tree.

### Routes

Routes should:

- stay grouped by module family
- use readable functional paths
- avoid mixing multiple naming styles in one module

### SQL Scripts

SQL install and patch scripts should:

- be placed in `backend-php/config/sql`
- use descriptive names
- align to the module or platform capability they introduce

## Current Transition Policy

The platform is in a controlled transition state.

This means:

- existing legacy structures are tolerated where already in use
- new work must align to the target standard
- touched legacy areas should be improved when safe
- mass renaming or mass refactoring should be planned, not improvised

## Pre-UAT Rule

Before deeper testing, the priority is:

1. standardize structure and conventions
2. remove obvious drift and ambiguity
3. freeze the baseline
4. test the baseline

Do not launch a broad cosmetic refactor immediately before UAT.

## Summary

CBMSv21 should now be treated as a platform with:

- shared structural rules
- shared workflow rules
- shared UI rules
- controlled naming rules
- controlled pre-UAT change discipline

That discipline is what will make the platform easier to maintain in the future.
