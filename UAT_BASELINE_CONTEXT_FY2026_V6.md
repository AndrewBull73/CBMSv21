# UAT Baseline Context - FY 2026 / Version 6

Date prepared: 2026-05-23

## Standard Context

Use this as the default context for general UAT:

- Fiscal Year: `2026`
- Version: `6`
- Database: `CBMSv2`

Do not change context casually during testing. Only switch away from this baseline where the scenario explicitly requires it.

## Why This Context

This context was already identified as the preferred live verification baseline after the workflow retrofit work. It is the cleanest current point for testing:

- workflow engine behavior
- workflow inquiry
- strategic reporting
- execution workflow screens

## Main Screens In Scope

### Workflow Engine

- workflow definitions
- workflow diagnostics
- workflow inquiry
- workflow assignments

### Strategy / Planning

- projects and project usage
- resource envelope
- sector ceilings
- MTFF and performance reports
- program and project budget reports

### Budget Execution

- commitments
- warrants
- reservations

## Recommended Test Roles

Use stable test accounts aligned to the following roles where available:

- `System Administrator`
- `Strategy Configuration Administrator`
- `Workflow Reviewer`
- `Workflow Approver`
- `Reporting User`
- `Budget Execution User`

If named test users already exist locally, record the exact usernames in the first line of your test run notes before starting.

## Baseline Reset Assets

### Strategic

- [reset_strategic_user_input_for_retest.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/reset_strategic_user_input_for_retest.sql)
- [reset_strategic_configuration_for_retest.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/reset_strategic_configuration_for_retest.sql)
- [check_strategic_reset_status.sql](C:/xampp82/htdocs/CBMSv21/backend-php/config/sql/check_strategic_reset_status.sql)

### Execution / Transaction Support

- [process_transaction_stub.php](C:/xampp82/htdocs/CBMSv21/backend-php/public/process_transaction_stub.php)
- [batch_runner.php](C:/xampp82/htdocs/CBMSv21/backend-php/public/batch_runner.php)

## Freeze Rules During UAT

- do not change the shared UI shell unless a real defect requires it
- do not rename schema objects
- do not change workflow definitions casually while active test cases are being run
- do not mix multiple baseline contexts in the same defect batch

## Evidence To Capture In Each Test Run

For each meaningful defect or pass result, capture:

- screen name
- role used
- exact route or menu path
- document number or project code if applicable
- whether the baseline context remained `FY 2026 / Version 6`
- SQL verification evidence where needed

## Summary

This note is the anchor for tomorrow's test run. Treat `FY 2026 / Version 6` as the default UAT operating context unless the scenario says otherwise.
