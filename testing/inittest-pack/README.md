# CBMSv21 INITTEST Pack

This folder is the ordered runbook for resetting `CBMSv2_INITTEST` to a fresh-client starting point, reseeding the required platform foundation, and then working through configuration and testing.

## Target

- Database: `CBMSv2_INITTEST`
- Application database setting: [backend-php/.env](/C:/xampp82/htdocs/CBMSv21/backend-php/.env:1)

## Fresh-Start Baseline

This pack assumes the rebuilt environment starts with:

- Fiscal Year `2026`
- Fiscal Year label `2026/27`
- Fiscal period `2026-07-01` to `2027-06-30`
- Default Version `1`
- Default login context `FY 2026 / Version 1`

## Phase Order

1. [01 Phase Environment Reset](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset)
2. [02 Phase Configuration](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/02_phase_configuration)
3. [03 Phase Testing](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/03_phase_testing)

## Execution Summary

1. Run Phase 01 in SSMS against `CBMSv2_INITTEST`.
   Fastest option: [00_run_full_fresh_client_rebuild.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/00_run_full_fresh_client_rebuild.sql) with `SQLCMD Mode` enabled.
2. Complete Phase 02 in the application from the seeded zero-state.
3. Use Phase 03 for smoke, regression, verification SQL, and defect logging.

## Notes

- Phase 01 is the fresh-client rebuild path.
- Phase 02 is the configuration documentation path.
- Phase 03 contains the active testing references, including the older `FY 2026 / Version 6` regression pack for later-cycle testing.
