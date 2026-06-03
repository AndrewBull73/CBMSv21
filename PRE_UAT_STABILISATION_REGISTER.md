# CBMSv21 Pre-UAT Stabilisation Register

Date: 2026-05-23

## Purpose

Capture the structural cleanup and platform-hardening items that should be controlled before and during UAT.

This register separates:

- `Must Fix Before UAT`
- `Control During UAT`
- `Post-UAT Refactor`

## Must Fix Before UAT

### 1. Adopt Repo-Level Platform Standards

Status: `Done`

Artifacts:

- [PLATFORM_STANDARDS.md](C:/xampp82/htdocs/CBMSv21/PLATFORM_STANDARDS.md)
- [DATABASE_NAMING_STANDARD.md](C:/xampp82/htdocs/CBMSv21/DATABASE_NAMING_STANDARD.md)

Reason:

- new work needs a stable standard before testing starts

### 2. Normalize Shared Screen Layout Usage

Status: `Done`

Reference:

- [SCREEN_UI_STANDARD.md](C:/xampp82/htdocs/CBMSv21/SCREEN_UI_STANDARD.md)

Reason:

- testers should see one consistent shell and navigation pattern across modules

### 3. Remove Clearly Stray Duplicate Runtime Files

Status: `Done`

Removed:

- `backend-php/app/Models/DataObjectCodeAccessModel (2).php`
- `backend-php/app/Controllers/Copy - DataObjectCodesController - Copy.php`
- `backend-php/public/process_transaction_stub - Copy.php`

Reason:

- these files were unreferenced duplicate copies inside the live app tree
- they increase maintenance risk and can confuse future development

### 4. Freeze Database Naming By Policy

Status: `Done`

Decision:

- no broad physical table or column rename before UAT
- all new schema work must follow [DATABASE_NAMING_STANDARD.md](C:/xampp82/htdocs/CBMSv21/DATABASE_NAMING_STANDARD.md)

Reason:

- maintainability improves without destabilizing the test baseline

## Control During UAT

### 5. Avoid Structural UI Or Workflow Churn

Status: `Open`

Rule:

- avoid broad shell, navigation, or workflow-stage redesign during UAT unless a real defect requires it

### 6. Use One Known-Good Baseline Context

Status: `Open`

Reference:

- [TESTING_READINESS_NOTE.md](C:/xampp82/htdocs/CBMSv21/TESTING_READINESS_NOTE.md)

Recommended baseline:

- one agreed fiscal year
- one agreed version
- one agreed scope for general testing

### 7. Keep Reset Scripts And Verification Queries Ready

Status: `Open`

Reason:

- repeatable testing matters more than cosmetic refactoring once UAT begins

## Post-UAT Refactor Backlog

### 8. Rationalize Legacy Public Transaction Tools

Status: `Deferred`

Examples:

- `backend-php/public/process_transaction_stub.php`
- `backend-php/public/transaction_input_editor.php`

Reason:

- these still represent legacy operational paths and should later be pulled further behind cleaner service boundaries

### 9. Complete Access-Control Consolidation

Status: `Deferred`

Reference:

- [FUNCTIONAL_AREA_ACCESS_MATRICES.md](C:/xampp82/htdocs/CBMSv21/FUNCTIONAL_AREA_ACCESS_MATRICES.md)

Reason:

- some areas still rely partly on legacy role gating and should be aligned further to permission bundles

### 10. Build Automated Test Coverage For The PHP Application

Status: `Deferred`

Reason:

- structural stability improves when manual UAT is later backed by repeatable automated tests

### 11. Plan A Controlled Schema Refactor Only If Still Needed

Status: `Deferred`

Reason:

- any broad rename should be treated as a migration project, not a side effect of feature work

### 12. Clean Remaining Non-Runtime Temp Artifacts

Status: `Deferred`

Examples:

- root-level `tmp_*` analysis files
- helper inspection scripts used during development
- stale structure snapshots if no longer useful

Reason:

- these are lower risk than duplicate runtime files, but they should still be tidied after UAT

## Summary

The platform is now in a better position to test from a controlled baseline:

- standards are documented
- naming policy is explicit
- obvious duplicate runtime files are removed
- high-risk refactors are intentionally deferred

That is the right balance between maintainability and UAT stability.
