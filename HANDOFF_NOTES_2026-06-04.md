# Handoff Notes - Base Configuration Admin Expansion, Readiness Refinement, and GitHub Backup (2026-06-04)

## Session Summary

- Continued from the INITTEST fresh-start and Base Configuration Readiness work.
- Linked the local `CBMSv21` workspace to git and pushed the initial backup to GitHub.
- Added a dedicated `Version Types` admin module and repointed readiness actions to it.
- Added a dedicated `Data Object Types` admin module and repointed readiness actions to it.
- Fixed several Base Configuration maintenance issues found during live review:
  - duplicate save-success messages
  - create-version edit/create mode bug
  - currency rate insert parameter bug
- Refined Base Configuration Readiness ordering, labels, and edge-case logic so it better reflects true setup state.
- Expanded and clarified help text around Fiscal Years, Versions, and related default-context behavior.

This note should be treated as the current handoff baseline for the Base Configuration area and the latest resume point for CBMSv21.

## Major Outcomes Completed

### 1. Project Is Now Backed Up To GitHub

- The local workspace is now initialized as a git repository.
- The initial project import was committed and pushed.
- Current remote:
  - `https://github.com/AndrewBull73/CBMSv21.git`

- Important workflow note:
  - changes are **not** pushed automatically
  - future backups still require:
    - `git add .`
    - `git commit -m "..."`
    - `git push`

### 2. Version Types Now Have Their Own Admin Screen

- A dedicated `Version Types` admin flow now exists instead of sending users to the Versions screen.
- The screen was registered properly in the shared config UI shell, route help, and quick links.
- The readiness action for `Version Types Configured` now opens the correct admin page.

Key files:

- `backend-php/app/Controllers/VersionTypesController.php`
- `backend-php/app/Models/VersionTypesAdminModel.php`
- `backend-php/app/Views/config/VersionTypesList.php`
- `backend-php/app/Views/config/VersionTypesForm.php`
- `backend-php/config/routes.php`
- `backend-php/app/Models/BaseConfigurationReadinessModel.php`
- `backend-php/app/Views/layouts/main.php`
- `backend-php/config/quick_links.php`
- `backend-php/app/Views/strategy/_RouteHelp.php`

### 3. Data Object Types Now Have Their Own Admin Screen

- A dedicated `Data Object Types` admin flow now exists instead of sending users to the Data Object Codes screen.
- The new module uses the standard shared Base Configuration UI layout.
- The readiness action for `Data Object Types Configured` now opens the new admin screen.
- A `Types` shortcut was also added to the `Data Object Codes` list screen.

Key files:

- `backend-php/app/Controllers/DataObjectTypesController.php`
- `backend-php/app/Models/DataObjectTypesAdminModel.php`
- `backend-php/app/Views/config/DataObjectTypesList.php`
- `backend-php/app/Views/config/DataObjectTypesForm.php`
- `backend-php/config/routes.php`
- `backend-php/app/Models/BaseConfigurationReadinessModel.php`
- `backend-php/app/Views/dataobjectcodes/DataObjectCodesList.php`
- `backend-php/app/Views/layouts/main.php`
- `backend-php/config/quick_links.php`
- `backend-php/config/menu.php`
- `backend-php/app/Views/strategy/_RouteHelp.php`

### 4. Save-Confirmation Message Handling Was Standardized

- Full-page admin/config screens now use the shared top-of-screen flash message as the standard confirmation pattern.
- Inline/card-level duplicates are now suppressed by default for full-page screens unless explicitly opted back in.
- This resolves the behavior where one save message appeared at the top of the page and the same message also appeared again inside the main card.

Key file:

- `backend-php/app/Controllers/BaseController.php`

### 5. Version Create Bug Was Fixed

- Creating a new Version could incorrectly be treated as editing a missing row.
- Cause:
  - the form inferred edit mode from prefilled IDs
  - create mode preloaded a suggested `VersionID`
  - save logic then tried to load a record that did not yet exist

- Resulting error previously seen:
  - `Save failed: The version record no longer exists. Reload the list and try again.`

- The controller and form now use an explicit edit-state flag based on whether the row actually exists.

Key files:

- `backend-php/app/Controllers/VersionsController.php`
- `backend-php/app/Views/config/VersionsForm.php`

### 6. Currency Rate Insert Bug Was Fixed

- Creating a new currency rate could fail with:
  - `SQLSTATE[HY093]: Invalid parameter number: parameter was not defined`

- Cause:
  - the create path used an `INSERT`
  - but still passed the update-only `:currencyRateId` parameter

- The model now builds execute parameters correctly for create vs update.

Key file:

- `backend-php/app/Models/CurrencyRatesAdminModel.php`

### 7. Base Configuration Readiness Was Refined Further

- The readiness screen has been refined in several small but important ways:
  - `Segments And Dimensions` was moved before `Organisation Structure`
  - `Needs Attention` was renamed to `Warning Checks` to match the actual summary logic
  - `Data Object Hierarchy Links` no longer shows `Ready` when there are zero organisation codes loaded
  - `Version Types Configured` and `Data Object Types Configured` now open their dedicated admin screens

- The readiness summary logic should now be interpreted as:
  - `Warning Checks` = number of checks in warning state
  - `Blockers` = number of checks in critical state
  - `Open Items` = total issue counts summed across checks

Key files:

- `backend-php/app/Models/BaseConfigurationReadinessModel.php`
- `backend-php/app/Views/config/BaseConfigurationReadiness.php`
- `backend-php/app/Views/config/FinancialConfigurationReadiness.php`

### 8. Fiscal Year And Version Help Text Was Clarified

- Help wording was updated to better explain:
  - what the system default fiscal year means
  - what the system default version means
  - the difference between a local fiscal-year/type default version and the system default fiscal context

- The wording now explicitly states that CBMS **will** use the selected Fiscal Year or Version as the default context when users first open the application or when no other context has been selected.

Key file:

- `backend-php/app/Views/strategy/_RouteHelp.php`

## Current Environment State

### Application Workspace

- Working root:
  - `C:\xampp82\htdocs\CBMSv21`

### Git / Backup

- Local git repo exists at project root.
- Remote origin is configured and initial push has been completed.
- Current known remote:
  - `origin -> https://github.com/AndrewBull73/CBMSv21.git`

### Database / Functional Context

- Recent review and fixes were focused on:
  - Base Configuration Readiness
  - Fiscal Years
  - Versions
  - Version Types
  - Currencies
  - Currency Rates
  - Data Object Types
  - Data Object Codes

## Verification Completed

### Syntax

- `php -l` was run successfully on all newly added and touched controller/model/view/config files during this session.

### Functional / Logic Verification Completed

- Confirmed and fixed the duplicate flash-message rendering path.
- Confirmed and fixed the create-version save failure path.
- Confirmed and fixed the currency-rate insert parameter mismatch.
- Confirmed and fixed the false `Ready` result for empty `Data Object Hierarchy Links`.
- Confirmed readiness summary labels and counts:
  - `Warning Checks`
  - `Blockers`
  - `Open Items`

## Not Yet Completed

### Browser Click-Through Still Needed

- The following still need live in-browser verification:
  - `Version Types` action from Base Configuration Readiness
  - `Data Object Types` action from Base Configuration Readiness
  - create/edit/save flow on the new `Data Object Types` screen
  - create/edit/save flow on the new `Version Types` screen after the shared-shell corrections
  - confirmation that the single-flash-message standard now looks correct on all main admin screens

### Data Object Code Upload E2E Still Pending

- The `Data Object Codes` upload/template work from the previous handoff still needs a real UI test:
  - download template
  - populate sample rows
  - upload file
  - confirm parent-child handling
  - recheck readiness after import

## Important Current Behavior Notes

### Shared UI Rule For New Admin Screens

- The correct pattern for new config/admin screens is now clear and should be followed in this order:
  - register the route family in the shared layout shell
  - register the route family in quick links/help/navigation
  - then build the screen using the standard config UI pattern

- This was the key correction made for both:
  - `version-types/`
  - `dataobject-types/`

### Flash Message Standard

- Full-page admin/config screens should use the layout-level flash only.
- Avoid rendering the same session flash again inside the main card unless there is a deliberate exception.

### Readiness Interpretation

- A green `Ready` should mean the setup area genuinely passed the current rule.
- Empty or not-yet-loaded baselines should generally surface as:
  - `critical` when the data is required for baseline operation
  - `info` when the area is not yet in scope

## Recommended Next Steps

### Immediate Resume Point

1. Open `Base Configuration Readiness`.
2. Verify that:
   - `Segments And Dimensions` appears before `Organisation Structure`
   - `Warning Checks` label is visible
   - `Version Types Configured` opens the Version Types admin screen
   - `Data Object Types Configured` opens the Data Object Types admin screen
3. Browser-test create/save on:
   - `Version Types`
   - `Data Object Types`

### Next Functional Check

1. Continue the INITTEST base configuration review screen by screen.
2. Run the live `Data Object Codes` upload flow from the browser.
3. Recheck readiness after any imported org-structure or segment data is loaded.

### Git / Backup Follow-Up

1. Review the current working tree.
2. Commit the Base Configuration updates.
3. Push them to GitHub so the current state is backed up remotely.

---

# Handoff Addendum - Budget Workbook Export / Offline Submission Start (2026-06-04 14:28:37)

## Session Summary

- Started the first implementation pass for offline budget submission workbook generation from the CBMS UI.
- Added a design note for the broader feature:
  - context sheet
  - transaction sheet
  - offline ceiling concept
  - workbook export/version control concept
- Implemented a first live `Download Workbook` action from the Transaction Input area.
- Added workbook export registration and embedded workbook identity fields so future uploads can be validated against a known CBMS-issued workbook.
- Added real password-to-open protection using local Excel automation and a password sourced from `tblSystemSettings`.

## Major Outcomes Completed

### 1. Budget Workbook Design Note Was Created

- A new design note was added and progressively updated during the session.
- It captures:
  - `Context` sheet approach
  - `Transactions` sheet placeholder approach
  - future offline ceiling support options
  - workbook export/version control direction

Key file:

- `BUDGET_SUBMISSION_SPREADSHEET_IMPORT_DESIGN.md`

### 2. Workbook Download Is Now Exposed In The UI

- A new button was added to the Transaction Input list screen.
- A new route/action now generates a workbook directly from CBMS.

Current entry point:

- `index.php?route=transaction-input/download-template`

Key files:

- `backend-php/app/Controllers/TransactionInputController.php`
- `backend-php/app/Views/transactioninput/List.php`
- `backend-php/config/routes.php`
- `backend-php/lang/en.php`
- `backend-php/lang/fr.php`

### 3. Context Sheet Is Generated And Locked

- The workbook now includes a `Context` sheet.
- Context values are populated from current CBMS context metadata.
- The visible context cells are styled as system-controlled.
- The sheet is protected so users should not casually edit those values.

Current context fields include:

- `FiscalYearID`
- `FiscalYearName`
- `VersionID`
- `VersionName`
- `DataObjectCode`
- `DataObjectName`
- `PreparedBy`
- `PreparedDate`
- workbook export metadata fields

### 4. Workbook Export Identity / Version Control Was Implemented

- Each generated workbook now gets:
  - `WorkbookExportID`
  - `WorkbookTemplateVersion`
  - `WorkbookToken`
  - `WorkbookGeneratedAtUTC`
  - `WorkbookStatus`

- These values are embedded into the locked `Context` sheet.
- A new export tracking table was introduced:
  - `dbo.tblTransactionInputWorkbookExport`

Key files:

- `backend-php/app/Controllers/TransactionInputController.php`
- `backend-php/config/sql/create_tblTransactionInputWorkbookExport.sql`

### 5. Real Password-To-Open Protection Was Added

- The workbook download now requires a password from `tblSystemSettings`.
- The generated workbook is first saved by PhpSpreadsheet.
- It is then re-saved through local Microsoft Excel COM automation with an open password.
- This is real file-open protection, not just sheet locking.

Current setting keys checked:

- `TX_WORKBOOK_OPEN_PASSWORD`
- `BUDGET_WORKBOOK_OPEN_PASSWORD`

Current behavior:

- if neither setting exists, workbook download is blocked
- if Excel automation fails, workbook download is blocked and logged

## Important Environment / Operational Notes

### Excel Dependency

- The password-to-open flow depends on local Microsoft Excel being installed on the host running CBMS.
- This was verified on the current machine.
- The implementation currently uses PowerShell + Excel COM automation.

### Database Context Confusion Was Identified

- During troubleshooting of the Data Scope picker, it was confirmed that the app is currently connected to:
  - `CBMSv2_INITTEST`

- A mistaken check had initially been made against the wrong database.
- This matters because Data Scope / access / hierarchy troubleshooting must be done against the active app database, not another environment.

### Temporary Workbook Download Rule

- The workbook download currently does **not** require `DataObjectCode` to be selected.
- It only requires:
  - `FiscalYearID`
  - `VersionID`

- This was intentionally relaxed temporarily so workbook generation could be inspected before Data Scope setup is fully stabilized.

## Verification Completed

### Syntax

- `php -l` was run successfully on the updated `TransactionInputController.php`.

### Compatibility / Runtime Checks

- Confirmed the installed PhpSpreadsheet version supports the protection APIs used.
- Confirmed Microsoft Excel is installed and COM automation is available on this host.

## Not Yet Completed

### Upload Side Not Implemented Yet

- Workbook upload validation against:
  - `WorkbookExportID`
  - `WorkbookToken`
  - workbook status
  - context match

  is **not yet built**

- The current session implemented download/export control only.

### Transactions Sheet Is Still A Starter Layout

- The `Transactions` sheet is currently just a starter placeholder.
- It is not yet populated from existing `tblTransactionInput` rows.
- It is not yet driven by any future Input Sheet Configuration model.

### Existing Transaction Preload Not Implemented Yet

- Existing head rows from `tblTransactionInput` are not yet exported into the workbook.
- This remains an important next step for the true offline round-trip workflow.

### Ceiling Snapshot Export Not Implemented Yet

- The workbook design discusses offline ceiling support, but no live `Ceilings` sheet export has been built yet.

## Important Current Behavior Notes

### Version Label Fix

- `tblVersions` in this database uses `VersionLabel`, not `VersionName`.
- The workbook context lookup was corrected accordingly.

### Export Table Creation

- The export tracking table is created defensively from the controller if it does not already exist.
- A matching SQL script was also added to the repo for formal deployment.

## Recommended Next Steps

### Immediate Resume Point

1. Add one of these system settings in `tblSystemSettings`:
   - `TX_WORKBOOK_OPEN_PASSWORD`
   - `BUDGET_WORKBOOK_OPEN_PASSWORD`
2. Re-test workbook download from the Transaction Input screen.
3. Confirm the downloaded file:
   - prompts for password on open
   - shows the locked `Context` sheet
   - contains workbook export identity values

### Next Functional Build Step

1. Implement upload-side validation using:
   - `WorkbookExportID`
   - `WorkbookToken`
   - `FiscalYearID`
   - `VersionID`
   - `DataObjectCode`
2. Update workbook export status after upload.
3. Decide whether uploaded workbooks should become:
   - `UPLOADED`
   - `SUPERSEDED`
   - `REJECTED`

### Next Workbook Enhancement

1. Populate the `Transactions` sheet with existing head records from `tblTransactionInput`.
2. Keep new blank rows available underneath for new offline entries.

### Optional Follow-Up

1. Re-tighten the `DataObjectCode` requirement on workbook download after Data Scope setup is confirmed in the correct database.

## Best Resume Starting Files

- `HANDOFF_NOTES_2026-06-04.md`
- `HANDOFF_NOTES_2026-05-29.md`
- `backend-php/app/Models/BaseConfigurationReadinessModel.php`
- `backend-php/app/Controllers/BaseController.php`
- `backend-php/app/Controllers/VersionTypesController.php`
- `backend-php/app/Models/VersionTypesAdminModel.php`
- `backend-php/app/Controllers/DataObjectTypesController.php`
- `backend-php/app/Models/DataObjectTypesAdminModel.php`
- `backend-php/app/Views/config/VersionTypesList.php`
- `backend-php/app/Views/config/VersionTypesForm.php`
- `backend-php/app/Views/config/DataObjectTypesList.php`
- `backend-php/app/Views/config/DataObjectTypesForm.php`
- `backend-php/app/Views/strategy/_RouteHelp.php`
