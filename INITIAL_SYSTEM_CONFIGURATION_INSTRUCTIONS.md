# CBMSv21 Initial System Configuration Instructions

Date prepared: 2026-05-28

## Purpose

This guide defines the starting configuration flow for a full CBMSv21 test cycle.

Use it when we want to:

- validate a fresh or reset environment
- confirm the platform baseline before broad screen testing
- create one repeatable sequence from setup through smoke and deeper UAT

This instruction set is designed to work with the existing UAT baseline and screen test runner.

## Controlled Baseline

Unless a test script explicitly says otherwise, use:

- Fiscal Year: `2026`
- Version: `6`
- Database: active test database for the run

Current rebuild-testing environment:

- Database: `CBMSv2_INITTEST`

Reference documents:

- [UAT_BASELINE_PACK_FY2026_V6.md](C:/xampp82/htdocs/CBMSv21/UAT_BASELINE_PACK_FY2026_V6.md)
- [UAT_PHASE_01_BASE_CONFIGURATION.md](C:/xampp82/htdocs/CBMSv21/UAT_PHASE_01_BASE_CONFIGURATION.md)
- [UAT_SMOKE_TEST_PACK.md](C:/xampp82/htdocs/CBMSv21/UAT_SMOKE_TEST_PACK.md)

## How To Use This Guide

Run the work in three layers:

1. confirm the environment and baseline configuration
2. complete the initial setup screen checks
3. continue into broader screen-by-screen smoke and functional testing

The app already has a built-in testing area:

- `Testing -> Test Scripts`
- route: `index.php?route=screen-tests/scenarios`

The new base-configuration scripts in that catalogue should be used as the front door into testing.

Important environment note:

- the PHP application only uses the database named in `backend-php/.env`
- SQL reset and verification scripts should be run against the active test database selected in SSMS
- for rebuild testing, keep production-like or reference databases out of the execution path

## Phase 0. Environment Confirmation

Before screen testing begins, confirm:

- the application opens without fatal PHP errors
- at least one administrator can sign in
- the default fiscal context resolves correctly
- the expected database is connected
- recent workflow seed changes are present if workflow-driven testing is planned

Recommended evidence:

- login screenshot
- home screen screenshot with context visible
- note of the active database and environment URL

## Phase 1. Initial System Configuration Sequence

Run these screens in order.

### 1. Base Configuration Readiness

Goal:

- establish whether any critical blocker prevents deeper testing

Screen:

- `Base Configuration Readiness`
- route: `base-config/readiness`

Test script:

- `Base Configuration Readiness Gate`

Pass focus:

- summary cards load
- all categories render
- critical blockers are understood before continuing

### 2. System Settings

Goal:

- confirm system-wide defaults and session settings

Screens:

- `System Settings`
- `System Settings Usage Map`

Test script:

- `System Settings Baseline Review`

Keys to verify:

- `DEFAULT_FISCAL_YEAR`
- `DEFAULT_VERSION`
- `SESSION_IDLE_TIMEOUT_SEC`
- `SESSION_ABSOLUTE_TIMEOUT_MIN`

### 3. Data Object Codes And Scope Foundation

Goal:

- confirm organisation structure exists for DataScope and workflow routing

Screens:

- `Data Object Codes`
- related access/scope maintenance screens as needed

Test script:

- `Data Object Codes And Scope Readiness`

Focus:

- baseline year rows exist
- type coverage looks valid
- related access management routes open

### 4. Segment Catalogue

Goal:

- confirm the platform coding structure is present

Screen:

- `Segments`

Test script:

- `Segment Catalogue Setup Review`

Focus:

- segment numbering
- length and position rules
- dimension mapping
- hierarchy support

### 5. Segment Values

Goal:

- confirm baseline coding values are loaded and reviewable

Screens:

- `Segment Values`
- `Upload Segment Values` when follow-up correction is needed

Test script:

- `Segment Values Baseline Review`

Focus:

- FY `2026` values are visible
- no obvious duplicates or missing-parent issues

### 6. Workflow Foundation

Goal:

- confirm the shared workflow engine is ready before workflow-enabled UAT

Screens:

- `Workflow Engine`
- `Workflow Engine Inquiry`
- `Workflow Diagnostics`
- `Workflow Assignments`

Test script:

- `Workflow Engine Foundation Review`

Focus:

- definitions load
- inquiry works
- diagnostics load
- assignment screens are reachable

## Phase 2. Core Smoke Testing

After the initial system configuration screens pass, continue with the existing smoke pack.

Primary reference:

- [UAT_SMOKE_TEST_PACK.md](C:/xampp82/htdocs/CBMSv21/UAT_SMOKE_TEST_PACK.md)

Recommended immediate next checks:

1. `Home Context And DataScope Smoke`
2. `New Window Context Isolation Smoke`
3. `Workflow Engine Definitions`
4. `Workflow Engine Inquiry`
5. key strategy setup and fiscal screens
6. key execution workflow screens

## Phase 3. Full Screen Testing

Once setup and smoke are stable, expand into full module coverage.

Suggested module order:

1. Base Configuration
2. Financial & Calculation Configuration
3. Strategic Framework Configuration
4. Strategic Setup
5. Strategic Performance
6. Strategic Delivery
7. Strategic Governance
8. Strategy Fiscal
9. Strategy Submissions
10. Budget Execution
11. Reporting
12. Training
13. Administration and diagnostics

## Defect And Evidence Rules

For each failed screen test, capture:

- screen name
- route
- user role used
- fiscal year and version
- DataScope if relevant
- exact action taken
- screenshot where helpful
- SQL verification result if the issue may be data-related

Use:

- [UAT_DEFECT_TRACKER_TEMPLATE.csv](C:/xampp82/htdocs/CBMSv21/UAT_DEFECT_TRACKER_TEMPLATE.csv)
- [backend-php/config/sql/uat_verification_library_fy2026_v6.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/uat_verification_library_fy2026_v6.sql)

## Reset And Retest Assets

Use these when the strategic test cycle needs to restart cleanly:

- [reset_strategic_user_input_for_retest.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/reset_strategic_user_input_for_retest.sql)
- [reset_strategic_configuration_for_retest.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/reset_strategic_configuration_for_retest.sql)
- [check_strategic_reset_status.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/check_strategic_reset_status.sql)

## Practical Start Point For Testers

For the next test cycle, start here:

1. sign in as administrator
2. confirm context is `FY 2026 / Version 6`
3. open `Testing -> Test Scripts`
4. run the six base-configuration scripts first
5. fix or log blockers
6. continue into the broader smoke pack
7. move into full module testing only after the initial configuration gate is stable

## Summary

This gives us one repeatable testing ramp:

- initial configuration first
- smoke testing second
- full screen coverage third

That should let us test the whole build from a controlled and documented starting point.
