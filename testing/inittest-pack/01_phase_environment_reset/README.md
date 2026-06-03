# 01 Phase Environment Reset

Use this phase to reset `CBMSv2_INITTEST` to a fresh-client starting point and reseed the minimum required platform foundation.

## Run Order

0. Optional one-shot run:
   [00_run_full_fresh_client_rebuild.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/00_run_full_fresh_client_rebuild.sql)
0a. Editable parameter profile for the one-shot run:
   [00a_rebuild_parameters.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/00a_rebuild_parameters.sql)
1. [01_confirm_active_database.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/01_confirm_active_database.sql)
2. [02_full_fresh_client_reset.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/02_full_fresh_client_reset.sql)
3. [03_install_core_platform_foundation.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/03_install_core_platform_foundation.sql)
4. [04_install_role_permission_model.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/04_install_role_permission_model.sql)
5. [05_grant_admin_baseline_access.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/05_grant_admin_baseline_access.sql)
6. [06_install_workflow_foundation.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/06_install_workflow_foundation.sql)
7. [07_install_strategy_foundation.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/07_install_strategy_foundation.sql)
8. [08_install_budget_execution_foundation.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/08_install_budget_execution_foundation.sql)
9. [08a_install_testing_support.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/08a_install_testing_support.sql)
10. [09_verify_fresh_start_foundation.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset/09_verify_fresh_start_foundation.sql)

## Steps

Quick path:

1. Open `00a_rebuild_parameters.sql` and edit the client defaults.
2. Open `00_run_full_fresh_client_rebuild.sql` in SSMS.
3. Turn on `SQLCMD Mode`.
4. Run the script.

The parameter file controls:

- client name
- app URL
- default fiscal year and label
- fiscal start and end dates
- default version id and label
- default language
- training features flag
- session timeout values
- GL account segment number

Default login after reset:

- username: `InitConfig`
- password: `ChangeMe123!`

Manual path:

1. Run `01_confirm_active_database.sql` and confirm the active database is `CBMSv2_INITTEST`.
2. Open `02_full_fresh_client_reset.sql`.
3. Run it first with `@ExecuteReset = 0`.
4. Review the preview counts and preserved user.
5. Set `@ExecuteReset = 1`.
6. Run `02_full_fresh_client_reset.sql` again to perform the reset.
7. Run steps `03` through `08a` in order.
8. Run `09_verify_fresh_start_foundation.sql`.

## What Phase 01 Leaves Empty On Purpose

- data object codes and access
- data object hierarchy
- workflow assignments
- segments
- segment values
- transaction-type segment rules
- rates
- calculations
- strategy setup data
- execution transactions
- screen test history

## Exit Criteria

- one admin-capable user can sign in
- the default context resolves to `FY 2026 / Version 1`
- the current role and permission model is installed
- workflow, strategy, and execution foundation tables exist
- client-owned setup tables are empty and ready for Phase 02
