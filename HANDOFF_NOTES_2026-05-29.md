# Handoff Notes - INITTEST Fresh-Start Baseline, Readiness Expansion, and Data Object Code Upload (2026-05-29)

## Session Summary

- Resumed from the earlier INITTEST rebuild and documentation work.
- Finalized the `CBMSv2_INITTEST` fresh-start reset/reseed approach so it behaves like a new-client baseline rather than a hard system wipe.
- Standardized the INITTEST pack under numbered phases for reset, configuration, and testing.
- Added a master rebuild script plus a parameter file so the full reset can be rerun consistently.
- Tightened the `Base Configuration Readiness` screen so it checks real configuration health instead of only table existence.
- Added matching SQL verification for the expanded readiness checks.
- Added spreadsheet upload support to the `Data Object Codes` admin screen so base configuration can be loaded from Excel or CSV.

This note should be treated as the current handoff baseline for INITTEST fresh-start configuration and early base-configuration testing.

## Major Outcomes Completed

### 1. INITTEST Full Fresh-Client Rebuild Pack Is In Place

- The working rebuild/test pack is now organized under:
  - `testing/inittest-pack/`

- Phase folders now exist in clear execution order:
  - `01_phase_environment_reset`
  - `02_phase_configuration`
  - `03_phase_testing`

- The environment reset phase now supports:
  - a one-shot master rebuild script
  - individual step scripts
  - a reusable parameter file for client defaults

Key files:

- `testing/inittest-pack/README.md`
- `testing/inittest-pack/01_phase_environment_reset/README.md`
- `testing/inittest-pack/01_phase_environment_reset/00_run_full_fresh_client_rebuild.sql`
- `testing/inittest-pack/01_phase_environment_reset/00a_rebuild_parameters.sql`

### 2. Full Reset Now Clears Client Data And Reseeds The New-Client Baseline

- The current design is intentional:
  - clear client-related data
  - preserve system reference data
  - reseed the minimum platform baseline needed to start configuration

- The reset now includes additional client-owned tables that were previously missed:
  - `tblUOMs`
  - `tblGLAccountCodes`
  - `tblLoginAttempts`
  - `tblLoginLocks`

- `tblVersions` now resets to a true fresh-start baseline:
  - the default fiscal year comes from `00a_rebuild_parameters.sql`
  - only one version row is seeded
  - `VersionID = 1`

- `tblVersionTypes` is now expected to exist and is reseeded during the rebuild.

Key files:

- `testing/inittest-pack/01_phase_environment_reset/02_full_fresh_client_reset.sql`
- `testing/inittest-pack/01_phase_environment_reset/02a_full_fresh_client_reset_execute.sql`
- `testing/inittest-pack/01_phase_environment_reset/03_install_core_platform_foundation.sql`
- `testing/inittest-pack/01_phase_environment_reset/09_verify_fresh_start_foundation.sql`

### 3. Default INITTEST Login Is Now Known And Documented

- The preserved default user after full reset is now:
  - `Username = InitConfig`
  - `Password = ChangeMe123!`

- This is now part of the reset process, so future fresh-start rebuilds land on a known login without needing a separate password fix.

### 4. Base Configuration Readiness Was Expanded

- The `Base Configuration Readiness` screen was reviewed and then tightened so it no longer gives false green results for key setup areas.

- The readiness model now checks:
  - default version coverage per active fiscal year
  - stronger default fiscal year / version consistency
  - segment definition health, not just row presence
  - scoped access coverage and invalid access rows
  - workflow assignment coverage and assignment integrity
  - system setting checks around login and URL behavior

- A `Workflow Assignments` quick link was also added to the screen.

Key files:

- `backend-php/app/Models/BaseConfigurationReadinessModel.php`
- `backend-php/app/Views/config/BaseConfigurationReadiness.php`

### 5. Matching SQL Verification Was Added For Readiness Checks

- A new SQL companion script now mirrors the expanded readiness logic so warnings and blockers can be cross-checked directly in SQL Server.

- Coverage includes:
  - fiscal year and version baseline
  - one-default-version-per-fiscal-year checks
  - default FY/version setting validation
  - deprecated setting key checks
  - segment definition and hierarchy checks
  - scoped access validation
  - workflow assignment validation

Key files:

- `testing/inittest-pack/02_phase_configuration/03_base_configuration_readiness_verification.sql`
- `testing/inittest-pack/02_phase_configuration/README.md`
- `testing/inittest-pack/02_phase_configuration/01_initial_system_configuration_instructions.md`
- `testing/inittest-pack/02_phase_configuration/02_uat_phase_01_base_configuration.md`

### 6. Deprecated System Settings Warning Was Fixed

- The warning about:
  - `DEFAULT_FISCAL_YEAR`
  - `DEFAULT_VERSION`

was caused by an exact-match issue in the readiness logic, not by bad seed data.

- The readiness model now treats the current default keys correctly, so those settings should remain in `tblSystemSettings`.

### 7. Data Object Codes Screen Now Supports Spreadsheet Upload

- The `Data Object Codes` admin screen now supports importing from:
  - `.xlsx`
  - `.xls`
  - `.csv`

- The screen now includes:
  - an `Upload` action
  - a `Template` download action

- Upload behavior:
  - supports `DataObjectTypeID` or `DataObjectTypeName`
  - supports optional parent codes
  - can force imported rows into the current fiscal year context
  - retries deferred child rows when parents appear later in the spreadsheet

Key files:

- `backend-php/app/Controllers/DataObjectCodesController.php`
- `backend-php/app/Models/DataObjectCodesModel.php`
- `backend-php/app/Views/dataobjectcodes/DataObjectCodesList.php`
- `backend-php/config/routes.php`

## Current Environment State

### Application Database

- The application is currently pointed at:
  - `CBMSv2_INITTEST`

### Current Fresh-Start Baseline

- The rebuild is designed to represent a new-client starting point.

- Current baseline assumptions:
  - default fiscal year `2026`
  - default version `1`
  - version label `2026 v 1`
  - `tblVersionTypes` present and seeded
  - client setup tables left empty where configuration is expected to be entered by the implementation team

### Rebuild Parameters

- The parameter file currently reflects the working baseline profile sourced from the existing client environment and then adapted for the fresh-start model.

Key file:

- `testing/inittest-pack/01_phase_environment_reset/00a_rebuild_parameters.sql`

## Verification Completed

### Syntax

- `php -l` passed on the touched readiness, upload, route, and view files.

### Database / SQL

- The master fresh-client rebuild script was rerun successfully after the reset fixes.
- The readiness verification SQL was executed successfully against `CBMSv2_INITTEST`.

### Readiness Screen Behavior

- The expanded readiness model was executed successfully against:
  - `FiscalYearID = 2026`
  - `VersionID = 1`

- Expected current blockers on a clean fresh-start baseline remain:
  - `Data Object Codes Loaded`
  - `Segments Configured`
  - `Segment Values Loaded`

- Workflow assignment coverage and scoped access coverage are currently expected to show informational or not-yet-in-scope states until more base configuration is loaded.

### Not Yet Completed

- No browser click-through has been completed yet for the new `Data Object Codes` upload flow.
- The new upload/template actions still need a live end-to-end import test from the UI.

## Important Current Behavior Notes

### INITTEST Is The Safe Rebuild Environment

- The fresh-start reset workflow is intended for:
  - `CBMSv2_INITTEST`

- It should be treated as the rebuild/testing clone, not the main reference database.

### Base Configuration Gate Is Now Stricter By Design

- The readiness screen is no longer just checking whether tables exist.
- It is now intended to act as a real configuration gate before wider testing starts.

### Phase 03 Legacy Assumptions May Still Need Cleanup

- Some older UAT materials were originally written around the previous `FY 2026 / Version 6` baseline.
- The current INITTEST fresh-start baseline is now `FY 2026 / Version 1`.
- If later regression packs still mention version `6`, those references should be reviewed before using them as the primary fresh-start documentation set.

## Recommended Next Steps

### Immediate Resume Point

1. Log into the application using:
   - `InitConfig / ChangeMe123!`
2. Open `Base Configuration Readiness`.
3. Confirm the remaining criticals are the expected fresh-start items only.
4. Start loading base setup data in this order:
   - `Data Object Codes`
   - `Segments`
   - `Segment Values`

### Next Functional Check

1. Browser-test the new `Data Object Codes` upload flow.
2. Download the template.
3. Upload a sample file.
4. Confirm parent-child rows import correctly.
5. Recheck `Base Configuration Readiness` after import.

### After Base Data Is Loaded

1. Re-run:
   - `testing/inittest-pack/02_phase_configuration/03_base_configuration_readiness_verification.sql`
2. Review whether scoped access and workflow assignments should remain informational or move into active setup.
3. Continue the Phase 02 configuration runbook screen by screen.

## Best Resume Starting Files

- `HANDOFF_NOTES_2026-05-29.md`
- `testing/inittest-pack/README.md`
- `testing/inittest-pack/01_phase_environment_reset/README.md`
- `testing/inittest-pack/02_phase_configuration/README.md`
- `testing/inittest-pack/02_phase_configuration/03_base_configuration_readiness_verification.sql`
- `backend-php/app/Models/BaseConfigurationReadinessModel.php`
- `backend-php/app/Controllers/DataObjectCodesController.php`
