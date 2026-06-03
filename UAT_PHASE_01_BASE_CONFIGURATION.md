# CBMSv21 UAT Phase 01: Initial Setup And Base Configuration

Date prepared: 2026-05-24

## Purpose

This phase confirms that the platform has a valid starting configuration before deeper testing begins in:

- financial and calculation configuration
- strategic planning
- budget submission
- budget execution
- workflow-driven approvals
- training scenarios

This is the first gate in the wider test plan. No detailed functional UAT should proceed until Base Configuration is signed off.

## Scope

This phase covers the current `Base Configuration` foundation and the related platform dependencies checked by the application itself:

- fiscal context
- organisation structure and DataScope
- segment catalogue
- segment values
- security and access
- workflow foundation
- system settings

Reference screen:

- `BACR` = `Base Configuration Readiness`
- route: `index.php?route=base-config/readiness`

Related screens:

- `BASS` = `System Settings`
- `BASE` = `Segments`
- `BASV` = `Segment Values`
- `BADO` = `Data Object Codes`
- `DataScope Access`
- `Roles`
- `Users`
- `Workflow`
- `Workflow Engine`
- `Workflow Assignments`

## Current UAT Baseline

For the current controlled UAT cycle, use:

- Fiscal Year: `2026`
- Version: `6`
- Database: `CBMSv2`

Reference:

- [UAT_BASELINE_PACK_FY2026_V6.md](C:/xampp82/htdocs/CBMSv21/UAT_BASELINE_PACK_FY2026_V6.md)

Important note:

- this phase is not intended to rebuild the whole platform from scratch unless the environment has been reset
- for the current environment, the goal is to verify, correct, and sign off the existing base configuration

## Required Test Roles

At least one active test account must exist with enough access to complete this phase.

Recommended minimum:

- `System Administrator`

Acceptable equivalent permission coverage:

- `BASE_CONFIG_VIEW`
- `BASE_CONFIG_EDIT`
- `SYSSETTINGS_ADMIN` or `SYSSETTINGS_EDIT`
- `USERS_ADMIN`
- `ROLES_ADMIN`
- `WORKFLOW_ADMIN`

## Inputs To Prepare Before Testing

Before this phase begins, gather or confirm the following source inputs:

1. Fiscal calendar and version naming convention.
2. Organisation hierarchy source for the test fiscal year.
3. Data object type definitions.
4. Segment catalogue design:
   segment numbers, segment codes, dimensions, groups, length rules, parent rules, usage flags.
5. Segment value source files for the active fiscal year.
6. Role matrix and named test users.
7. System settings values for:
   default fiscal year, default version, session idle timeout, session absolute timeout.
8. Workflow foundation install status and seed SQL status.

If any of the above is missing, log it as a setup dependency before starting detailed test execution.

## Phase Objective

At the end of this phase we should be able to say:

- the platform opens in a valid fiscal context
- users can sign in with correct roles
- organisation scope exists and is navigable
- segments and values are loaded and structurally valid
- system settings support stable login/session behaviour
- workflow foundation is installed and usable
- Base Configuration Readiness shows no critical blockers

## Setup And Test Sequence

### Step 1. Confirm Access And Environment

Screen:

- Login
- Home
- `BACR`

Actions:

1. Sign in with the nominated administrator account.
2. Confirm the home screen loads without raw PHP errors.
3. Open `Base Configuration Readiness`.
4. Confirm the screen loads fully and the quick links render.

Pass if:

- login succeeds
- the shared shell renders correctly
- the readiness dashboard loads without fatal error

Evidence:

- screenshot of home screen after login
- screenshot of `BACR`

Stop if:

- no administrator can sign in
- base readiness screen fails to load

### Step 2. Fiscal Context Setup

Screens:

- `BACR`
- `System Settings`

Checks to confirm:

- `Fiscal Years Configured`
- `Versions Configured`
- `Default Context Settings`

Actions:

1. Confirm at least one active fiscal year exists.
2. Confirm at least one active version exists for each active fiscal year.
3. Confirm the current baseline uses `FY 2026 / Version 6`.
4. Open `System Settings` and verify:
   - `DEFAULT_FISCAL_YEAR`
   - `DEFAULT_VERSION`
   - `SESSION_IDLE_TIMEOUT_SEC`
   - `SESSION_ABSOLUTE_TIMEOUT_MIN`
5. Sign out and sign back in once defaults are confirmed.

Pass if:

- the login landing context resolves cleanly
- default fiscal year and version are valid
- session settings are populated

Stop if:

- no active fiscal year exists
- no active version exists
- default fiscal year/version does not resolve to a valid pair

### Step 3. Organisation Structure And DataScope

Screens:

- `Data Object Codes`
- `DataScope Access`
- `BACR`

Checks to confirm:

- `Data Object Types Configured`
- `Data Object Codes Loaded`
- `Data Object Hierarchy Links`
- `Data Object Workflow Status`

Actions:

1. Confirm data object types exist.
2. Confirm current-year data object codes are loaded for `FY 2026`.
3. Confirm each code has a valid type.
4. Confirm hierarchy links exist for the current fiscal year.
5. Confirm workflow status support exists for scoped workflow behavior.
6. If scoped access is intended, confirm DataScope access tables and sample records exist.

Pass if:

- DataScope can support context selection and workflow routing
- no critical readiness issue remains in organisation structure

Stop if:

- data object codes are missing for the active fiscal year
- hierarchy links are missing
- workflow status support needed by scoped workflow is absent

### Step 4. Segment Catalogue Setup

Screen:

- `Segments`

Checks to confirm:

- `Segments Configured`
- `Segment Hierarchy Support`

Actions:

1. Open `Segments`.
2. Review the segment catalogue for:
   - code
   - name
   - length rules
   - start and end positions
   - CBMS dimension mapping
   - segment group
   - strategic and financial usage flags
   - display order
   - parent segment default and parent required flag
3. Create or correct missing catalogue records if required.
4. Confirm the segment structure matches the current intended chart design.

Pass if:

- required segments exist
- segment numbering and dimension mapping are coherent
- hierarchy support is present where required

Stop if:

- core segments are missing
- segment structure is inconsistent with the agreed model

### Step 5. Segment Value Load And Validation

Screens:

- `Segment Values`
- `Upload Segment Values`
- `BACR`

Checks to confirm:

- `Segment Values Loaded`
- `Segment Value Duplicates`
- `Missing Required Segment Parents`

Actions:

1. Filter `Segment Values` to `FY 2026`.
2. Confirm values exist for required segments and relevant DataScope rows.
3. Confirm no duplicate segment values are reported.
4. Confirm parent-child relationships are complete where parent rules apply.
5. If needed, use the upload template/process for controlled bulk loading.

Pass if:

- required segment values are present
- duplicates are resolved
- parent validation does not leave structural gaps

Stop if:

- segment values are missing for core dimensions
- duplicate or orphaned values would distort downstream planning or posting

### Step 6. Security And Access Setup

Screens:

- `Roles`
- `Users`
- `DataScope Access`
- `BACR`

Checks to confirm:

- `Permissions Catalog`
- `Roles`
- `Role Permissions`
- `Role Permission Integrity`
- `Users Configured`
- `User Role Coverage`
- `Administrative Access Coverage`
- `Scoped Access Readiness`

Actions:

1. Confirm the permissions catalogue exists and is populated.
2. Confirm required business and admin roles exist.
3. Confirm role-permission mapping is loaded and consistent.
4. Confirm all active test users have at least one role.
5. Confirm at least one active admin user can maintain the platform.
6. If organisation-code scoping is in use, confirm scoped access records are loaded.

Pass if:

- no active user is unassigned
- at least one admin user has full recovery/maintenance capability
- role coverage is sufficient for the planned UAT streams

Stop if:

- no admin coverage exists
- role-permission integrity is broken
- key test users cannot access their modules

### Step 7. Workflow Foundation Setup

Screens:

- `Workflow`
- `Workflow Engine`
- `Workflow Assignments`
- `BACR`

Checks to confirm:

- `Workflow Task Types Configured`
- `Workflow Task Statuses Configured`
- `Workflow Engine Foundation`

Actions:

1. Confirm workflow task types exist.
2. Confirm workflow task statuses exist.
3. Confirm the shared workflow engine tables are installed.
4. Confirm the workflow seed foundation has been applied:
   `create_workflow_engine_foundation_v1.sql`
5. Confirm workflow definitions, stages, and actions exist for enabled modules.
6. Confirm workflow assignment screens load without error.

Pass if:

- the workflow platform can support shared routing and approvals
- workflow admin screens load and display live definitions

Stop if:

- workflow foundation tables are missing
- workflow task types or statuses are absent

### Step 8. System Settings Review

Screen:

- `System Settings`

Checks to confirm:

- `System Settings Available`
- `Required System Settings`
- `Deprecated System Settings`

Actions:

1. Confirm the system settings table is populated.
2. Confirm the required keys are populated with valid values.
3. Review and remove or rename deprecated keys where practical.
4. Save any corrections and rerun `BACR`.

Pass if:

- required system keys are populated
- deprecated keys do not create ambiguity

Stop if:

- required keys are blank or invalid
- login/session defaults are unpredictable

### Step 9. Final Base Configuration Readiness Review

Screen:

- `BACR`

Actions:

1. Refresh the readiness screen after all corrections.
2. Review every category:
   - Fiscal Context
   - Organisation Structure
   - Segments
   - Security And Access
   - Workflow Configuration
   - System Configuration
3. Record remaining warnings, if any.
4. Decide whether remaining warnings are acceptable for the next testing phase.

Pass if:

- no critical blockers remain
- the base platform is stable enough for the next test phase

## Recommended Pass / Fail Gate

Minimum gate to leave Phase 01:

1. `Base Configuration Readiness` loads successfully.
2. `Critical Blockers` = `0`.
3. At least one administrator can sign in and maintain configuration.
4. Default fiscal year and version resolve correctly at login.
5. Current-year DataScope hierarchy exists.
6. Segment catalogue and segment values are present for the UAT baseline.
7. Workflow foundation is installed.
8. Required system settings are populated.

Recommended stronger gate:

- health score `>= 90%`
- no unresolved warning that would block:
  - strategy setup
  - workflow routing
  - user access
  - context resolution

## Defect Logging Rules For This Phase

Log defects in the shared UAT tracker when:

- a screen fails to load
- a readiness check gives an incorrect result
- a required table or dependency is missing unexpectedly
- a role or permission behaves inconsistently
- a setup save or archive action fails

Tag defects using one of these prefixes:

- `BC-FISCAL`
- `BC-DATASCOPE`
- `BC-SEGMENTS`
- `BC-SECURITY`
- `BC-WORKFLOW`
- `BC-SYSSET`

## Evidence To Capture

For sign-off, capture:

1. screenshot of `BACR` summary cards
2. screenshot of each category after final review
3. screenshot of `System Settings` for the required keys
4. screenshot of `Segments` register
5. screenshot of `Segment Values` filtered to the baseline fiscal year
6. screenshot of `Users` and `Roles`
7. screenshot of `Workflow Engine` list

## Exit Criteria

Phase 01 is complete when:

- the setup sequence above has been executed
- blockers have been corrected or formally accepted
- the readiness dashboard shows no critical blocker
- the project team agrees the platform is ready for:
  - Financial Configuration
  - Strategy Configuration
  - Strategy Setup
  - Workflow-enabled business UAT

## Recommended Next Phase

After Phase 01 is signed off, proceed to:

- Financial And Calculation Configuration Readiness

Related screen:

- `financial-config/readiness`

