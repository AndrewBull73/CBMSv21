# CBMSv21 UAT Baseline Pack

Date prepared: 2026-05-23

## Purpose

This pack defines the controlled baseline for upcoming UAT so testing is:

- repeatable
- easier to verify
- less affected by structural drift
- aligned to the current CBMSv21 platform standards

## Approved UAT Baseline

 Unless a test case explicitly says otherwise, use:

- Fiscal Year: `2026`
- Version: `6`
- Database: active controlled test database

Current initial-configuration test environment:

- Database: `CBMSv2_INITTEST`

This baseline aligns to the recent workflow retrofit handoff guidance in:

- [HANDOFF_NOTES_2026-05-22.md](C:/xampp82/htdocs/CBMSv21/HANDOFF_NOTES_2026-05-22.md)

## Pack Contents

### 1. Baseline Context Note

- [UAT_BASELINE_CONTEXT_FY2026_V6.md](C:/xampp82/htdocs/CBMSv21/UAT_BASELINE_CONTEXT_FY2026_V6.md)

### 2. Smoke Test Pack

- [UAT_SMOKE_TEST_PACK.md](C:/xampp82/htdocs/CBMSv21/UAT_SMOKE_TEST_PACK.md)

### 3. Verification SQL Library

- [uat_verification_library_fy2026_v6.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/uat_verification_library_fy2026_v6.sql)

### 4. Defect Tracker Template

- [UAT_DEFECT_TRACKER_TEMPLATE.csv](C:/xampp82/htdocs/CBMSv21/UAT_DEFECT_TRACKER_TEMPLATE.csv)

### 5. Testing Readiness Reference

- [TESTING_READINESS_NOTE.md](C:/xampp82/htdocs/CBMSv21/TESTING_READINESS_NOTE.md)

### 6. Phase 01 Base Configuration Plan

- [UAT_PHASE_01_BASE_CONFIGURATION.md](C:/xampp82/htdocs/CBMSv21/UAT_PHASE_01_BASE_CONFIGURATION.md)

### 7. Initial System Configuration Instructions

- [INITIAL_SYSTEM_CONFIGURATION_INSTRUCTIONS.md](C:/xampp82/htdocs/CBMSv21/INITIAL_SYSTEM_CONFIGURATION_INSTRUCTIONS.md)

## Supporting Reset And Check Assets

### Strategic Reset

- [reset_strategic_user_input_for_retest.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/reset_strategic_user_input_for_retest.sql)
- [reset_strategic_configuration_for_retest.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/reset_strategic_configuration_for_retest.sql)
- [check_strategic_reset_status.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/check_strategic_reset_status.sql)

### Execution / Transaction Support

- [process_transaction_stub.php](C:/xampp82/htdocs/CBMSv21/backend-php/public/process_transaction_stub.php)
- [batch_runner.php](C:/xampp82/htdocs/CBMSv21/backend-php/public/batch_runner.php)

## UAT Control Rules

During UAT:

1. keep to `FY 2026 / Version 6` unless a scenario requires a different context
2. avoid structural UI, schema, or workflow changes unless a real defect forces them
3. log defects consistently using the shared tracker template
4. confirm data state with the SQL library before marking defects as workflow/data issues

## Summary

This pack establishes one clear UAT baseline so tomorrow's testing starts from a stable and maintainable platform position.
