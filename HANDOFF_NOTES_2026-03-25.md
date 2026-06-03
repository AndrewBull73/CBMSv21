# Handoff Notes - Transaction Input Editor UX + Segment Metadata (2026-03-25)

## Scope Completed Today
- Continued work on the Transaction Input editor in `backend-php/public/transaction_input_editor.php`.
- Added segment labels sourced from `dbo.tblSegments` instead of showing raw `SegmentXCode` field names.
- Added support for new `tblSegments` metadata columns:
  - `SegmentGroup`
  - `DisplayOrder`
- Updated the editor so hidden scope-owned segments can be auto-derived from the current Data Scope path.
- Reworked the Transaction Input editor layout to better match the intended user flow for data entry.

## Major Functional Changes
1. Segment labels and grouping
- `backend-php/public/transaction_input_editor.php`
- Segment labels now come from `dbo.tblSegments.SegmentName`.
- Visible segment controls are grouped using `dbo.tblSegments.SegmentGroup`.
- Segment rendering order now prefers:
  - `dbo.tblTransactionTypeSegmentConfig.DisplayOrder`
  - fallback to `dbo.tblSegments.DisplayOrder`
  - fallback to `SegmentNo`

2. Scope-owned segment auto-fill
- `backend-php/public/transaction_input_editor.php`
- Hidden segments are now auto-populated from the active Data Scope path when the segment name matches the Data Object type name.
- This was specifically intended for scope-owned dimensions such as:
  - Head
  - Cost Centre
  - Sub Cost Centre
- Auto-fill now happens both:
  - during render
  - during save
- This means config rows with `VisibleFlag = 0` can still populate `SegmentXCode` values if the scope provides them.

3. Transaction Input editor flow/order
- `backend-php/public/transaction_input_editor.php`
- The editor flow was reordered to better match user entry flow:
  - Budget Class first
  - segment selection next
  - Unit of Measure after segments
  - CalcID shown as derived from UOM
  - Account Code shown as derived from segments
- `CalculationID` is now treated as derived/read-only on the form.
- `AccountCode` is now shown as derived/read-only on the form.

4. Derived Account Code behavior
- `backend-php/public/transaction_input_editor.php`
- Added a simple account-code derivation method that concatenates segment values in segment order.
- Derivation is applied:
  - server-side on render/save
  - client-side as visible segment selections change
- Important: this is currently a straightforward concatenation, not a fixed-width padded build using `StartPoint` / `EndPoint`.

5. Data-entry UX improvements
- `backend-php/public/transaction_input_editor.php`
- Combined ceiling/context/existing-transactions into a collapsed top accordion so the user can reach data-entry fields faster.
- Added a compact summary strip above the BP entry grid for quick reference:
  - Budget Class
  - Account Code
  - UOM
  - CalcID
- Moved advanced calculation/extra options into a collapsed `Advanced Options` accordion.
- Added a sticky action bar at the bottom of the form for:
  - Refresh Segment Layout
  - Save
  - Save + Compute
  - Compute
  - Delete

6. Section styling updates
- `backend-php/public/transaction_input_editor.php`
- Segment groups now render in distinct grey panels.
- `Budget Class` and `Unit Of Measure` also use the same grouped grey panel treatment.
- The outer `Core Fields` wrapper was returned to a neutral background so the inner panels remain visually distinct.

## Database Notes
1. `dbo.tblSegments`
- Confirmed new columns now exist:
  - `SegmentGroup`
  - `DisplayOrder`
- Current design direction:
  - `SegmentGroup` is being treated as segment-definition metadata
  - `tblTransactionTypeSegmentConfig.DisplayOrder` is being treated as transaction-screen layout order

2. `dbo.tblTransactionTypeSegmentConfig`
- This table already had `DisplayOrder`.
- Current editor behavior now uses config `DisplayOrder` first for actual screen order.

3. `dbo.tblSegmentValues`
- Segment options are still sourced from `tblSegmentValues`, filtered by:
  - `FiscalYearID`
  - current Data Scope `DataObjectCode`
  - `SegmentNo`
  - `ActiveFlag = 1`

## Important Findings / Behavior Notes
1. Why Segment 12 (`Location`) was missing
- `tblSegments` contains `SegmentCode = 12` with label `Location`.
- However, for transaction type `11`, the active config only currently includes segments `1` through `11`.
- Therefore `Segment12` will not render until a config row for `SegmentNo = 12` is added for the relevant transaction type(s).
- There were also no `tblSegmentValues` rows returned for `SegmentNo = 12` in the current checks, so making it visible would still require loading values.

2. GL Account Code is still manual
- The form flow was changed so `AccountCode` is derived from segments.
- `GLAccountCode` is still left as an editable field because no reliable existing derivation rule was found in the current code session.

3. Scope auto-fill depends on naming alignment
- Hidden segment auto-fill currently works by matching:
  - `tblSegments.SegmentName`
  - `ctxDataObjectPath[].DataObjectTypeName`
- This works for current scope types such as:
  - Head
  - Cost Centre
  - Sub Cost Centre
- If naming diverges in future data, this mapping logic may need to become explicit rather than name-matched.

4. AccountCode derivation may need refinement
- Current behavior concatenates non-empty segment values in segment order.
- If the real account code requires:
  - fixed-width padding
  - blank placeholders
  - positional formatting using `StartPoint` / `EndPoint`
  then the derivation function should be upgraded.

## Files Touched This Session
- `backend-php/public/transaction_input_editor.php`

## Suggested First Step Next Session
1. Open the Transaction Input editor in a live browser and verify the revised data-entry flow:
- top info accordion collapsed by default
- Budget Class panel first
- segment groups render in correct grouped order
- Unit Of Measure panel appears after segments
- BP entry grid is reached with much less scrolling
- sticky action bar behaves correctly while scrolling

2. Test hidden scope-derived segments on a real scoped record:
- Segment1 / Segment2 / Segment3 should be hidden when config says not visible
- those segment values should still be saved from scope

3. Validate derived field behavior:
- selecting UOM should update CalcID immediately
- changing visible segments should update AccountCode immediately
- saved record should retain derived `AccountCode`

4. Decide whether to implement server/client derivation for `GLAccountCode`
- currently still manual
- may need to derive from Economic Indicator / segment combination depending on business rules

5. Decide whether `AccountCode` derivation must use fixed-width positional formatting
- if yes, upgrade the derivation logic to use:
  - `StartPoint`
  - `EndPoint`
  - `MinLength`
  - `MaxLength`

6. If `Location` should be shown:
- add `SegmentNo = 12` config rows to `tblTransactionTypeSegmentConfig` for the relevant transaction type(s)
- load `tblSegmentValues` rows for `SegmentNo = 12`

## Useful SQL Checks
```sql
SELECT SegmentCode, SegmentName, SegmentGroup, DisplayOrder
FROM dbo.tblSegments
ORDER BY TRY_CONVERT(int, SegmentCode), SegmentCode;
```

```sql
SELECT FiscalYearID, VersionID, TransactionTypeCode, SegmentNo, VisibleFlag, RequiredFlag, ActiveFlag, DisplayOrder
FROM dbo.tblTransactionTypeSegmentConfig
WHERE TransactionTypeCode = '11'
ORDER BY COALESCE(FiscalYearID, 0), COALESCE(VersionID, 0), DisplayOrder, SegmentNo;
```

```sql
SELECT TOP 50 FiscalYearID, DataObjectCode, SegmentNo, SegmentCode, SegmentName, ActiveFlag
FROM dbo.tblSegmentValues
WHERE SegmentNo = 12
ORDER BY FiscalYearID, DataObjectCode, SegmentCode;
```

## Commands Used This Session
- `php -l backend-php/public/transaction_input_editor.php`
- PHP one-off DB checks through `backend-php/config/db.php`
- metadata inspection queries against:
  - `dbo.tblSegments`
  - `dbo.tblTransactionTypeSegmentConfig`
  - `dbo.tblSegmentValues`
  - `dbo.tblDataObjectTree`
  - `dbo.tblDataObjectTypes`
