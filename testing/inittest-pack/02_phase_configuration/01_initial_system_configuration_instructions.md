# CBMSv21 Initial System Configuration Instructions

Date prepared: 2026-05-29

## Purpose

This guide is the configuration sequence for a new-client starting point after the Phase 01 rebuild.

It is designed for:

- `CBMSv2_INITTEST`
- Fiscal Year `2026`
- Default login context `Version 1`
- a zero-state configuration where core platform tables exist but client setup data is still empty

## Starting Assumptions

After Phase 01 completes:

- one admin-capable user can sign in
- roles and permissions exist
- workflow engine tables exist
- strategy and execution foundation tables exist
- segments are empty
- segment values are empty
- data object codes may still be empty
- workflow assignments are empty
- transaction-type segment rules are empty

## Configuration Order

Work through the steps in this order.

### Step 1. Confirm The Seeded Baseline

Confirm:

- login works
- the home page loads without PHP errors
- default context is `FY 2026 / Version 1`
- `System Settings` shows the seeded defaults

Key screens:

- `Home`
- `System Settings`
- `Base Configuration Readiness`

### Step 2. Review Base Configuration Readiness

Open `Base Configuration Readiness` and use it as the main gate.

If a warning or blocker needs confirmation in SQL, run:

- `02_phase_configuration/03_base_configuration_readiness_verification.sql`

Expected result at this point:

- fiscal year and versions should be ready
- default-version coverage and default-context settings should be ready
- roles, permissions, and system settings should be ready
- workflow engine foundation should be ready
- workflow assignments and scoped access may still show warnings until client-specific routing and security rules are configured
- segments and segment values should still show as missing or incomplete until you load them
- data object codes may still remain incomplete at this stage of the baseline

### Step 3. Define The Segment Catalogue

Use `Segments` as the first client-owned base configuration area after fiscal year, versions, and the default user are confirmed.

Recommended minimum decisions:

- segment numbers
- codes and names
- lengths and positions
- CBMS dimensions
- segment groups
- financial usage flag
- strategic usage flag
- parent requirements

Expected outcome:

- the segment catalogue exists before segment values are loaded
- the implementation team has the baseline structure needed for downstream mapping and imports
- the `Segments` screen can be populated either manually or through the spreadsheet upload/template flow

### Step 4. Load Segment Values

Use `Segment Values` as the next base-configuration step immediately after `Segments`.

Recommended checks:

- active rows load for FY `2026`
- no duplicate active key combinations
- parent links are filled where required
- strategic source hierarchies can be traced

Working note:

- `tblSegmentValues` remains scope-aware because it still stores `DataObjectCode`
- if the client has already defined the relevant scope codes, use the actual values
- if organisation structure is still being completed, load the segment framework rows needed for the next setup stream and then revisit any client-specific scope refinement later

Expected outcome:

- the `Segments` and `Segment Values` baseline exists before wider client setup continues
- the `Segment Values` screen can be populated either manually or through the spreadsheet upload/template flow

### Step 5. Load Organisation Structure

Load the client organisation structure for FY `2026` after the segment framework exists.

Required areas:

- `Data Object Codes`
- `DataScope Access`
- any related hierarchy import or maintenance screen

Expected outcome:

- data object types remain available
- FY `2026` data object codes are loaded
- hierarchy links are loaded
- workflow status rows can be initialized later against the active context

### Step 6. Configure Workflow Ownership

Use `Workflow Assignments` after organisation and user setup is stable.

Assign:

- submission reviewers and approvers
- execution reviewers and approvers
- any workflow stages that depend on data object routing

Expected outcome:

- the workflow engine exists from Phase 01
- actual routing ownership is now client-specific and configured

### Step 7. Configure Financial Rules

Build the financial configuration needed for transaction entry and calculations.

Main areas:

- transaction-type segment rules
- rates
- units of measure
- ceiling definitions
- calculations
- scenario support if used

Expected outcome:

- financial readiness screens can begin to turn green for the active context

### Step 8. Configure Strategy

Build the strategic planning configuration in this order:

1. mapped dimensions
2. org units and structural masters
3. sectors, programs, subprograms, and related masters
4. fiscal assumptions
5. phasing profiles
6. resource envelopes
7. submission and publish controls

### Step 9. Configure Budget Execution

After submission/strategy context is stable, confirm execution setup.

Main areas:

- execution version selection
- opening-balance readiness
- warrants
- reservations
- commitments
- supplementary budgets
- RIE

## Suggested Daily Working Sequence

For a practical onboarding cycle, use this short order each day:

1. confirm login and context
2. rerun `Base Configuration Readiness`
3. complete the next missing setup area
4. rerun the matching screen test script
5. log blockers before moving on

## Exit Criteria

Phase 02 is complete when:

- segment catalogue is defined
- segment values are loaded
- organisation structure is loaded
- workflow assignments are configured
- core financial setup is in place
- strategy setup can be exercised
- execution setup can be exercised
- no critical base-configuration blockers remain
