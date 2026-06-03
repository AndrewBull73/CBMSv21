# Handoff Notes - Transaction Input Editor + Summary Fixes (2026-03-27)

## Scope Completed Today
- Continued refactoring of the Transaction Input editor in `backend-php/public/transaction_input_editor.php`.
- Corrected the editor layout after an initial over-customized pass drifted away from the CBMS house style.
- Simplified the Core Section so it reflects actual user entry flow.
- Added UI-only GL Grouping support in Core.
- Added a new combined summary mode on the Transaction Input list: `Budget Class + GL Grouping`.
- Fixed a summary-screen regression where all budget figures disappeared.

## Major Functional Changes

### 1. Editor layout brought back into CBMS house style
- File: `backend-php/public/transaction_input_editor.php`
- Removed the custom dashboard-like visual treatment that was inconsistent with the rest of the app.
- Returned to standard Bootstrap / CBMS patterns:
  - `card shadow-sm`
  - standard `card-header` / `card-body`
  - standard accordions
  - reduced custom CSS
- Kept the improved grouping and flow, but within the existing visual language.

### 2. Core Section simplified
- File: `backend-php/public/transaction_input_editor.php`
- Core Section now focuses on the true primary inputs:
  - `Budget Class`
  - `GL Grouping`
- Removed `UOM` from Core.
- Removed full-display derived fields from Core as primary inputs:
  - `AccountCode`
  - `GLAccountCode`
  - `CalculationID`
- These still exist in the form as hidden/derived values and compact summary references.

### 3. GL Grouping added as UI filter only
- File: `backend-php/public/transaction_input_editor.php`
- Added GL Grouping options loaded from `dbo.tblGLGrouping`.
- Current filter source:
  - `FiscalYearID`
  - `VersionID`
  - `BudgetClassID` mapped from selected `TransactionTypeCode`
- Important behavior:
  - GL Grouping is **not saved** to `tblTransactionInput`
  - it is only used to reduce user choice overload in the UI
- Current filter effect:
  - narrows the GL-driving segment options by matching the selected grouping prefix
- Current selected grouping can also be derived from `GLAccountCode` when possible.

### 4. UOM moved to Rates / Quantities section
- File: `backend-php/public/transaction_input_editor.php`
- `UOMCodeInpC` now appears in the BP / Rates / Quantities section instead of Core.
- `CalculationID` is displayed there as derived from UOM.
- This better matches the real user workflow.

### 5. Transaction Input list: new combined summary mode
- Files:
  - `backend-php/app/Controllers/TransactionInputController.php`
  - `backend-php/app/Views/transactioninput/List.php`
- Added new `summary_by` mode:
  - `transaction_type_gl_group`
- This provides a list grouped by:
  - Budget Class (`TransactionTypeCode`)
  - GL Grouping (`tblGLGrouping.GroupName`)
  - GL Prefix
- GL grouping follows the list screen’s currently selected GL level.

### 6. Drill behavior updated for combined summary
- Files:
  - `backend-php/app/Controllers/TransactionInputController.php`
  - `backend-php/app/Views/transactioninput/List.php`
- Drilldown from `Budget Class + GL Grouping` rows now goes to filtered detail using:
  - `tt`
  - `gl_prefix`
- Also adjusted mode auto-routing so:
  - `tt + gl_prefix` correctly resolves to detail
  - instead of being forced into the older single-dimension flow

## Critical Bug Fixed Today

### Summary screens lost all budget figures
- Root cause found in `backend-php/app/Controllers/TransactionInputController.php`
- The list/summary queries were summing values from:
  - `dbo.tblTransactionResultFlat`
- But current live data situation:
  - `dbo.tblTransactionResultFlat` has `0` rows
  - budget values exist in `dbo.tblTransactionInput`
    - `BP1InpN` ... `BP12InpN`
    - `BPTotalInpN`
- Fix implemented:
  - all relevant summary/detail aggregates now use:
    - `tblTransactionResultFlat` values when available
    - fallback to `tblTransactionInput` values when result rows do not exist
- Example new pattern:
  - `COALESCE(l.BP1, ti.BP1InpN, 0)`
  - `COALESCE(l.BPTotal, ti.BPTotalInpN, 0)`

## Database / Config Notes

### 1. GL account segment setting
- Previously added and confirmed in `dbo.tblSystemSettings`:
  - `SettingKey = GLAccountSegmentNo`
  - `SettingValue = 11`
- Editor currently uses this to derive `GLAccountCode` for head records.

### 2. GL Grouping source
- `dbo.tblGLGrouping` structure confirmed includes:
  - `GLGroupingID`
  - `FiscalYearID`
  - `VersionID`
  - `ReportID`
  - `Level`
  - `Prefix`
  - `GroupName`
  - `TransactionType`
  - `SortOrder`
  - `BudgetClassID`
  - `StatementClass`
- Current editor usage is UI filtering only.

### 3. No persisted GL Grouping field in transaction table
- `dbo.tblTransactionInput` does **not** currently contain:
  - `GLGroupingID`
  - `GLGrouping`
  - similar persisted grouping field
- This is why GL Grouping was implemented as UI filter only.

## Files Touched This Session
- `backend-php/public/transaction_input_editor.php`
- `backend-php/app/Controllers/TransactionInputController.php`
- `backend-php/app/Views/transactioninput/List.php`

## Verification Performed
- `php -l backend-php/public/transaction_input_editor.php`
- `php -l backend-php/app/Controllers/TransactionInputController.php`
- `php -l backend-php/app/Views/transactioninput/List.php`

## Important Current State

### Editor
- Core Section now has:
  - Budget Class
  - GL Grouping
- UOM is in BP / Rates / Quantities
- Derived values are reduced in Core and shown more lightly
- Layout is closer again to existing CBMS screens

### List screen
- Summary modes now include:
  - GL Prefix Summary
  - Budget Class Summary
  - Budget Class + GL Grouping
- Summary figures should now reappear because of the input-value fallback

## Suggested First Step Next Session
1. Open the Transaction Input list in browser and verify:
- `GL Prefix Summary` shows figures again
- `Budget Class Summary` shows figures again
- `Budget Class + GL Grouping` shows figures again
- drilldown from combined rows works correctly

2. Open the Transaction Input editor and verify:
- Core shows only Budget Class + GL Grouping
- changing GL Grouping actually narrows the GL-related segment options
- UOM in BP / Rates / Quantities still derives `CalculationID` correctly

3. Decide whether the top summary strip on the editor should be reduced further
- it still shows some derived reference information
- may be worth trimming again if users still find it noisy

4. Decide whether GL Grouping should influence more than one segment
- currently it filters the GL-driving segment options by prefix
- if users expect broader dimension filtering, expand carefully

## Useful SQL Checks

### Check whether result table is empty
```sql
SELECT COUNT(*) AS ResultRows
FROM dbo.tblTransactionResultFlat;
```

### Check whether input values exist
```sql
SELECT COUNT(*) AS TxWithInputValues
FROM dbo.tblTransactionInput
WHERE COALESCE(BPTotalInpN,0) <> 0
   OR COALESCE(BP1InpN,0) <> 0
   OR COALESCE(BP2InpN,0) <> 0;
```

### Sample recent transaction input values
```sql
SELECT TOP 10
    TransactionID,
    TransactionTypeCode,
    GLAccountCode,
    BP1InpN, BP2InpN, BP3InpN,
    BPTotalInpN
FROM dbo.tblTransactionInput
ORDER BY TransactionID DESC;
```

### Check GL grouping options for a budget class
```sql
SELECT FiscalYearID, VersionID, ReportID, Level, Prefix, GroupName, BudgetClassID, SortOrder
FROM dbo.tblGLGrouping
WHERE FiscalYearID = 2026
  AND VersionID = 5
  AND BudgetClassID = 11
ORDER BY SortOrder, Level, Prefix;
```

## Commands Used This Session
- `php -l backend-php/public/transaction_input_editor.php`
- `php -l backend-php/app/Controllers/TransactionInputController.php`
- `php -l backend-php/app/Views/transactioninput/List.php`
- `sqlcmd` checks against:
  - `dbo.tblTransactionInput`
  - `dbo.tblTransactionResultFlat`
  - `dbo.tblGLGrouping`
  - `INFORMATION_SCHEMA.COLUMNS`
