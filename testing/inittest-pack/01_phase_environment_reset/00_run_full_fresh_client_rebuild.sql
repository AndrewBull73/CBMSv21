/*
Phase 01 / Master Script
Run the full fresh-client rebuild in one pass.

Important:
1. Open this file in SSMS.
2. Turn on SQLCMD Mode.
3. Edit 00a_rebuild_parameters.sql if client defaults need to change.
4. Confirm the target database is CBMSv2_INITTEST.
5. Run the script.
*/

:setvar Root C:\xampp82\htdocs\CBMSv21\testing\inittest-pack\01_phase_environment_reset

:r $(Root)\00a_rebuild_parameters.sql
:r $(Root)\01_confirm_active_database.sql
:r $(Root)\02a_full_fresh_client_reset_execute.sql
:r $(Root)\03_install_core_platform_foundation.sql
:r $(Root)\04_install_role_permission_model.sql
:r $(Root)\05_grant_admin_baseline_access.sql
:r $(Root)\06_install_workflow_foundation.sql
:r $(Root)\07_install_strategy_foundation.sql
:r $(Root)\08_install_budget_execution_foundation.sql
:r $(Root)\08a_install_testing_support.sql
:r $(Root)\09_verify_fresh_start_foundation.sql
