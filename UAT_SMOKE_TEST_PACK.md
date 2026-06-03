# CBMSv21 UAT Smoke Test Pack

Date prepared: 2026-05-23

## Purpose

Provide a short, reliable smoke pack to confirm that the environment is usable before deeper UAT begins.

Default context:

- Fiscal Year: `2026`
- Version: `6`

Reference:

- [UAT_BASELINE_CONTEXT_FY2026_V6.md](C:/xampp82/htdocs/CBMSv21/UAT_BASELINE_CONTEXT_FY2026_V6.md)
- [INITIAL_SYSTEM_CONFIGURATION_INSTRUCTIONS.md](C:/xampp82/htdocs/CBMSv21/INITIAL_SYSTEM_CONFIGURATION_INSTRUCTIONS.md)

Important sequence note:

- complete the initial system configuration scripts first
- then run this smoke pack

## Smoke Sequence

### 1. Login And Context

Role:

- any valid business user

Check:

- login succeeds
- home screen loads
- context can be confirmed as `FY 2026 / Version 6`

Pass if:

- no fatal error
- menus render
- context is stable

### 2. Workflow Engine Definitions

Role:

- administrator or workflow admin

Path:

- `Workflow Engine -> Definitions`

Check:

- list loads
- definitions appear
- quick links render correctly

Pass if:

- no styling break
- no raw error
- register is readable and filterable

### 3. Workflow Engine Inquiry

Role:

- administrator or workflow reviewer

Path:

- `Workflow Engine -> Inquiry`

Check:

- inquiry screen loads
- filters render
- at least one result or an empty-state message appears cleanly

Pass if:

- summary cards render
- register and detail pane behave normally

### 4. Workflow Diagnostics

Role:

- administrator

Path:

- `Workflow Engine -> Diagnostics`

Check:

- diagnostics screen loads
- no missing shell/layout issue

Pass if:

- screen loads without error

### 5. Projects Register

Role:

- strategy setup/configuration user

Path:

- `Strategy Setup -> Projects`

Check:

- register loads
- filter or search works
- create/edit buttons render

Pass if:

- no fatal error
- register is usable

### 6. Project Usage Inquiry

Role:

- strategy setup/configuration user

Path:

- open one existing project and then `Usage`

Check:

- usage screen loads
- strategic links, source mappings, and usage sections render

Pass if:

- no fatal error
- all major cards/sections display

### 7. Resource Envelope

Role:

- strategy fiscal/configuration user

Path:

- `Strategy Fiscal -> Resource Envelope`

Check:

- register loads
- totals render
- add/edit action is available if mappings are ready

Pass if:

- totals and line register display cleanly

### 8. Sector Ceilings

Role:

- strategy fiscal/configuration user

Path:

- `Strategy Fiscal -> Sector Ceilings`

Check:

- register loads
- comparison chart area renders
- add/edit form loads

Pass if:

- screen is usable without layout or runtime error

### 9. Strategic Reports

Role:

- reporting user

Paths:

- `Reports -> MTFF`
- `Reports -> Performance`
- `Reports -> Program Budget`
- `Reports -> Project Budget`

Check:

- each report loads
- filters work
- tables render

Pass if:

- each report opens without fatal error

### 10. Commitments Register

Role:

- budget execution user or reviewer

Path:

- `Execution -> Commitments`

Check:

- register loads
- selected panel shows workflow information

Pass if:

- no fatal error
- workflow panel appears for selected record

### 11. Warrants Register

Role:

- budget execution user or reviewer

Path:

- `Execution -> Warrants`

Check:

- register loads
- selected panel shows workflow information

Pass if:

- no fatal error
- workflow panel appears for selected record

### 12. Reservations Register

Role:

- budget execution user or reviewer

Path:

- `Execution -> Reservations`

Check:

- register loads
- selected panel shows workflow information

Pass if:

- no fatal error
- workflow panel appears for selected record

## Deep UAT Focus After Smoke Pass

Once the smoke pack passes, focus deeper UAT on:

1. `submit`
2. `forward`
3. `return`
4. `approve`
5. `cancel`

Across:

1. commitments
2. warrants
3. reservations
4. workflow inquiry visibility
5. workflow history correctness

## Evidence To Capture

For failures, capture:

- screen name
- role used
- exact action
- document number or project code
- screenshot if useful
- SQL verification result from [uat_verification_library_fy2026_v6.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/uat_verification_library_fy2026_v6.sql)

## Summary

If this smoke pack passes, the environment is ready for deeper functional UAT against the `FY 2026 / Version 6` baseline.
