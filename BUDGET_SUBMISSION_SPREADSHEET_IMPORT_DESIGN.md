# CBMSv21 Budget Submission Spreadsheet Import Design

## Purpose

This document defines a recommended design for allowing offline budget users to prepare budget submissions in a spreadsheet and have those submissions imported into `dbo.tblTransactionInput`.

The design is aligned to the current CBMSv21 transaction input implementation:

- `backend-php/app/Controllers/TransactionInputController.php`
- `backend-php/public/transaction_input_editor.php`
- the existing Excel upload pattern used in controllers such as `CurrenciesController` and `DataObjectCodesController`

## Design Goals

1. Let offline users work in Excel without direct system access.
2. Import only data that belongs to the currently selected fiscal year, version, and scope.
3. Preserve the existing transaction calculation pipeline instead of bypassing it.
4. Validate spreadsheet rows before they become live transaction records.
5. Support both new submissions and controlled updates to existing head records.
6. Keep a clear audit trail of who uploaded what and when.

## Recommended Scope For Phase 1

Phase 1 should support:

- upload of `head` transaction rows only
- import into the current session context:
  - `FiscalYearID`
  - `VersionID`
  - `DataObjectCode`
- creation of new rows in `tblTransactionInput`
- update of existing head rows when `TransactionID` is supplied
- recalculation after import so derived child rows and results remain system-generated
- validation report back to the uploader

Phase 1 should not support:

- direct spreadsheet authoring of child calculation rows
- mixed fiscal years or mixed versions in one workbook
- mixed `DataObjectCode` scopes in one workbook
- direct import of ceiling result fields
- direct import of system-managed fields such as `CreatedDate`, `UpdatedDate`, `CreatedBy`, `UpdatedBy`

## Core Design Decision

The spreadsheet import should treat `tblTransactionInput` as the source for `head` records only. Child rows and calculated result rows should continue to be produced by the existing calculation engine.

This matches the current implementation, where:

- users save transaction input rows into `tblTransactionInput`
- the calculation process derives downstream results
- child rows are created or refreshed by `process_transaction_stub.php`

This is important because importing fully expanded rows would duplicate business logic and create a high risk of inconsistent calculations.

## Recommended User Workflow

1. User navigates to a new transaction import screen from the transaction input area.
2. User selects or confirms context:
   - fiscal year
   - version
   - data object scope
3. User downloads a generated Excel workbook for that context.
4. The workbook is preloaded with:
   - context metadata
   - ceiling snapshot data
   - existing transaction rows already in CBMS for that context
5. Offline budget users review, edit, add, or mark rows for update.
6. An online user uploads the completed workbook.
7. System performs validation and shows:
   - rows accepted
   - rows rejected
   - detailed error messages
8. User confirms commit.
9. System writes accepted rows into `tblTransactionInput`.
10. System runs recalculation for imported head rows.
11. System presents an import summary and audit reference.

## Recommended Screen Design

Add a new route group under `TransactionInputController`:

- `transaction-input/import`
- `transaction-input/download-template`
- `transaction-input/upload`
- `transaction-input/commit`
- `transaction-input/download-errors`

Recommended permission model:

- view template/import screen: `ESTIMATES_VIEW`, `ESTIMATES_EDIT`, `ADMIN_ALL`, `SYSADMIN`
- upload and commit: `ESTIMATES_EDIT`, `ADMIN_ALL`, `SYSADMIN`

Recommended UI sections:

- current context summary
- download template button
- upload workbook form
- validation summary panel
- row-level error grid
- commit accepted rows button
- optional checkbox: `Run recalculation after import`

## Workbook Design

Use `.xlsx` as the primary format. CSV can remain optional later, but Excel should be the main supported upload because the feature benefits from:

- instructions
- multiple sheets
- lookup tabs
- validation lists
- preloaded existing data
- protected formulas and snapshot tabs

Recommended sheets:

### 1. `Context`

This sheet should hold the workbook-level context and upload rules.

Recommended fields:

- `FiscalYearID`
- `FiscalYearName`
- `VersionID`
- `VersionName`
- `DataObjectCode`
- `DataObjectName`
- `PreparedBy`
- `PreparedDate`
- `SubmissionNote`

Recommended behavior:

- the system generates this sheet when the workbook is downloaded
- users can view it easily
- core system context fields should be locked or clearly marked as non-editable
- on upload, the system must validate that the `Context` sheet matches the currently selected system context

This sheet should also contain short instructions such as:

- this workbook is valid only for the shown fiscal year, version, and data object
- transactions must be entered only on the `Transactions` sheet
- do not add or rename required columns
- `UPDATE` rows must include a valid `TransactionID`

### 2. `Transactions`

This is the only sheet that should supply rows to `tblTransactionInput`.

For the offline round-trip model, this sheet should be pre-populated with existing `tblTransactionInput` head rows for the chosen context.

Recommended columns for Phase 1:

- `RowAction`
- `TransactionID`
- `ExternalRowRef`
- `TransactionTypeCode`
- `UOMCodeInpC`
- `CurrencyInpC`
- `UseLiveRates`
- `BP1InpN`
- `BP2InpN`
- `BP3InpN`
- `BP4InpN`
- `BP5InpN`
- `BP6InpN`
- `BP7InpN`
- `BP8InpN`
- `BP9InpN`
- `BP10InpN`
- `BP11InpN`
- `BP12InpN`
- `Segment1Code` to `Segment20Code`
- `ExistingRowFlag`
- `OriginalBPTotalInpN`
- `OriginalHash`
- optional future business columns such as:
  - `TransactionStartDate`
  - `TransactionEndDate`
  - `RecurrentFlag`
  - `CostItemID`

Recommended row behavior:

- existing rows exported from CBMS should default to `RowAction = UPDATE`
- newly inserted blank template rows should default to `RowAction = NEW`
- `TransactionID` should be populated for existing CBMS rows
- `ExistingRowFlag` should be system-generated and protected
- `OriginalHash` can be used later to detect whether the row changed offline before re-import

The `Transactions` sheet should not repeat workbook context columns like:

- `FiscalYearID`
- `VersionID`
- `DataObjectCode`
- `DataObjectName`

Those values should come only from the `Context` sheet plus the active session context at upload time.

### Optional supporting sheet: `Lookups`

This can be added if we want richer template usability.

Possible contents:

- transaction types
- UOMs
- visible segment values for the selected context
- maybe allowed currencies

This supporting sheet can be hidden if desired, but it is optional. The essential workbook structure should be `Context` plus `Transactions`.

## Row Action Rules

Recommended `RowAction` values:

- `NEW`
- `UPDATE`

Rules:

- `NEW` means create a new head record
- `UPDATE` means update an existing head record in the same context
- `UPDATE` requires `TransactionID`
- `UPDATE` must reject rows where the target transaction is not a head row
- blank `RowAction` can default to `NEW` only if `TransactionID` is blank
- exported existing rows should default to `UPDATE`

## Data Mapping Strategy

### Export source data

The generated workbook should be built from current CBMS data for the selected context:

- workbook context from session and lookup labels
- ceiling snapshot from the relevant ceiling tables or cached ceiling state
- existing head transactions from `tblTransactionInput`

Recommended transaction export filter:

- `FiscalYearID = current context`
- `VersionID = current context`
- `DataObjectCode = current context`
- head rows only:
  - `HeadRecordID IS NULL OR HeadRecordID = TransactionID`

### Fields supplied by spreadsheet

These are appropriate for spreadsheet entry:

- `TransactionTypeCode`
- `UOMCodeInpC`
- `CurrencyInpC`
- `UseLiveRates`
- `BP1InpN` to `BP12InpN`
- visible and allowed `SegmentXCode` fields
- other explicitly approved business-input fields

### Fields derived by import logic

These should be derived or normalized by the server:

- `FiscalYearID` from current context
- `VersionID` from current context
- `DataObjectCode` from current context
- `RecordTypeCode = 'H'`
- `HeadRecordID`
- `AccountCode` from segment composition rules
- `GLAccountCode` from configured GL account segment
- `CalculationID` from `TransactionTypeCode` and `UOMCodeInpC` where possible
- `BPQ1InpN` to `BPQ4InpN` from monthly values
- `BPTotalInpN` from monthly values
- `CreatedBy`, `UpdatedBy`, `CreatedDate`, `UpdatedDate`
- change-detection metadata should be ignored for live save purposes

### Fields never loaded from spreadsheet

- `CreatedDate`
- `UpdatedDate`
- ceiling status columns
- system flags not intended for offline entry
- calculated child-row fields

## Validation Rules

Validation should happen in two stages.

### Stage 1: File and header validation

- workbook can be opened
- `Context` sheet exists
- `Transactions` sheet exists
- required columns exist
- no duplicate header names
- workbook context matches current selected context
- required context keys are present on the `Context` sheet

### Stage 2: Row validation

Per row, validate:

- row is not completely blank
- `RowAction` is valid
- `TransactionTypeCode` exists
- `UOMCodeInpC` is valid for the transaction type where required
- segment values are valid codes
- required segments are present based on transaction type segment config
- amount cells are numeric
- `TransactionID` exists for update rows
- update target belongs to same `FiscalYearID`, `VersionID`, and `DataObjectCode`
- update target is a head record
- no duplicate `ExternalRowRef` values within the same workbook
- if `ExistingRowFlag = 1`, `TransactionID` must be present
- if `OriginalHash` is provided, it may be used to detect stale or altered source rows

Derived validation should also run:

- derived `CalculationID` can be resolved when required
- derived `AccountCode` and `GLAccountCode` can be built successfully
- imported row does not violate core transaction save assumptions already enforced in the editor

## Import Processing Pattern

The recommended pattern is `validate -> stage -> commit`, not direct write on upload.

### Why staging is recommended

Direct write is fast to build but weak operationally. A staging layer gives:

- safer review before commit
- better row-level error reporting
- easier audit
- easier rollback of failed imports
- simpler support for re-download of errors

## Recommended Database Additions

Add staging tables rather than writing upload metadata into `tblTransactionInput`.

### `tblTransactionInputImportBatch`

Suggested fields:

- `ImportBatchID`
- `FiscalYearID`
- `VersionID`
- `DataObjectCode`
- `FileName`
- `UploadedBy`
- `UploadedDate`
- `Status`
- `TotalRows`
- `AcceptedRows`
- `RejectedRows`
- `CommittedRows`
- `RunRecalculationFlag`
- `AuditJson`

### `tblTransactionInputImportRow`

Suggested fields:

- `ImportRowID`
- `ImportBatchID`
- `RowNumber`
- `RowAction`
- `TransactionID`
- `ExternalRowRef`
- `PayloadJson`
- `NormalizedJson`
- `ValidationStatus`
- `ErrorMessage`
- `CommittedTransactionID`
- `CreatedDate`

This keeps the live transaction table clean while still preserving a full import trail.

## Application Architecture

Recommended new components:

### Controller

Extend `TransactionInputController` with actions for:

- import screen
- template download
- upload/validate
- commit
- error workbook download

### Service

Add a dedicated service such as:

- `App\Services\TransactionInputSpreadsheetImportService`

Responsibilities:

- build workbook template
- preload workbook with existing context transactions
- preload workbook with ceiling snapshot data
- parse workbook
- normalize cell values
- validate rows
- stage batch and row data
- commit staged rows into `tblTransactionInput`
- trigger recalculation

### Model

Add a small model or repository for staging operations, for example:

- `TransactionInputImportModel`

Responsibilities:

- create import batch
- persist staged rows
- fetch batch results
- mark committed rows

## Context Sheet Rules

The `Context` sheet should be treated as authoritative workbook metadata, but not as unrestricted input.

Recommended rules:

- uploaded workbook must contain exactly one context block
- `FiscalYearID`, `VersionID`, and `DataObjectCode` must match the user’s current session context
- if there is a mismatch, upload should stop before row validation
- descriptive fields like `PreparedBy`, `PreparedDate`, and `SubmissionNote` may be stored in import batch metadata
- descriptive fields should not overwrite live transaction fields

This gives the client a visible cover sheet for offline work while still preventing users from uploading transactions into the wrong context.

## Offline Ceiling Support

Because the client wants the workbook to be used fully offline, ceiling information can be incorporated into the spreadsheet as an offline snapshot.

The key design rule is:

- the spreadsheet may contain ceiling data and perform offline ceiling checks
- the spreadsheet ceiling position is a point-in-time snapshot, not a guaranteed live balance

### Recommended workbook approach

For offline use, the template should include ceiling data generated at download time.

Recommended structure:

- `Context` sheet
  - include ceiling snapshot metadata such as:
    - `CeilingSnapshotDate`
    - `CeilingSnapshotVersion`
    - `CeilingEngine`
- `Transactions` sheet
  - include advisory ceiling result columns for the user
- optional `Ceilings` sheet
  - include the ceiling reference rows used by spreadsheet formulas or lookups

### Recommended `Ceilings` sheet

If the client wants real offline checking, a dedicated `Ceilings` sheet is the cleanest design.

Suggested columns:

- `CeilingDefinitionID`
- `TransactionTypeCode`
- `Segment1Code` to `Segment20Code` as needed
- `CeilingBP1` to `CeilingBP12`
- `CeilingBPTotal`
- `BalanceBP1` to `BalanceBP12`
- `BalanceBPTotal`
- `Priority`
- `SnapshotDate`

This sheet should be system-generated and normally protected from editing.

### Recommended `Transactions` sheet ceiling columns

Add spreadsheet-facing advisory columns such as:

- `CeilingMatchKey`
- `CeilingAvailableBP1` to `CeilingAvailableBP12`
- `CeilingAvailableTotal`
- `ProposedBPTotal`
- `CeilingVarianceTotal`
- `OfflineCeilingStatus`
- `OfflineCeilingMessage`

These columns should be formula-driven where practical.

### How offline ceiling checking should work

At template generation time:

1. The system exports the relevant ceiling rows for the workbook context.
2. The workbook includes those rows in a protected `Ceilings` sheet.
3. The `Transactions` sheet uses formulas or lookup logic to:
   - find the best matching ceiling row
   - compare entered monthly values or total values to the snapshot ceiling balance
   - show a warning when the proposed transaction appears to exceed the snapshot balance

### Important limitation

If the workbook is fully offline, its ceiling balance cannot stay synchronized with other activity after download.

That means:

- the workbook can warn based on the downloaded ceiling position
- it cannot know whether another user has already consumed that ceiling later
- the final authority must still be the imported batch plus any business rule chosen by the client

## Two possible offline operating models

There is an important design choice here.

### Model A: informational offline ceiling check

In this model:

- the workbook shows ceiling warnings based on the snapshot
- users can still upload rows that exceed the offline warning
- CBMSv21 decides what to do on import

This is the safer default model.

### Model B: controlled offline allocation workbook

In this model:

- the workbook snapshot is treated as the official ceiling budget for that offline exercise
- users are expected to stay within that exported ceiling envelope
- the imported batch is assessed against that same exported ceiling snapshot

This can work if the business process is truly offline and centrally coordinated, but it is less flexible and needs stronger governance.

## Recommended approach for this client

Because the client has said the process will be offline, I recommend:

- generate a protected `Ceilings` sheet with exported ceiling balances
- show advisory ceiling results directly in `Transactions`
- store the ceiling snapshot metadata in the `Context` sheet
- keep the imported batch linked to the ceiling snapshot used when the workbook was produced

On upload, the system should:

- record which ceiling snapshot the workbook used
- validate that the workbook structure and snapshot metadata are intact
- optionally compare the import against current live ceilings if the organisation wants a final online control

If the client wants the process to remain entirely governed by the offline snapshot, that is also possible, but it becomes a business policy decision rather than just a technical feature.

## Recommended import-time behavior with offline ceilings

When a workbook is uploaded, the system should preserve both:

- the user-entered transaction rows
- the ceiling snapshot metadata used offline

Recommended import checks:

- verify the `Context` sheet has not been broken
- verify the ceiling snapshot metadata is present
- optionally re-calculate the workbook rows against the uploaded ceiling snapshot on the server
- record whether each row passed the offline ceiling rule

Recommended staging fields to add:

- `CeilingSnapshotDate`
- `CeilingSnapshotVersion`
- `OfflineCeilingStatus`
- `OfflineCeilingMessage`
- `OfflineCeilingDefinitionID`

## Design note on formulas

Excel can support this feature, but formula complexity can grow quickly when ceiling matching depends on many segment columns.

So the best practical pattern is:

- keep the ceiling matching key simple and deterministic
- export only relevant ceilings for the chosen context
- avoid trying to make the workbook replicate every server-side nuance if the ceiling logic is highly dynamic

If matching becomes too complex across many segments, an alternative is to export a precomputed ceiling match key per row pattern rather than relying only on raw Excel formulas.

## Commit Logic

When the user commits accepted rows:

1. Start a database transaction.
2. For each accepted row:
   - normalize payload
   - derive system fields
   - insert new head row or update existing head row
   - capture resulting `TransactionID`
3. Commit the database transaction.
4. If recalculation was requested:
   - run the existing transaction save/load process for committed head rows
   - do this after the data commit, not before
5. Update import batch status and summary counts.

For large batches, recalculation may need a queue or batch runner later. Phase 1 can keep it synchronous if row volume is modest.

### Export and re-import behavior

The recommended operating model is not just a blank template upload. It is a controlled export/edit/import cycle.

At workbook generation time:

1. Read current context from session.
2. Export workbook metadata into `Context`.
3. Export current ceiling snapshot into `Ceilings` if enabled.
4. Export existing head transactions into `Transactions`.
5. Add a number of empty entry rows at the bottom for new submissions.

At upload time:

1. Parse all transaction rows.
2. Separate likely existing rows from new rows.
3. Validate `UPDATE` rows against current CBMS transaction identity.
4. Validate `NEW` rows as insert candidates.
5. Stage both sets for review and commit.

## Existing Transaction Export Rules

If transactions already exist in CBMS for the chosen context, they should absolutely be loaded into the workbook.

This gives the client a proper offline working paper rather than a blank upload form.

Recommended export content for each existing row:

- `TransactionID`
- `RowAction = UPDATE`
- current input values from `tblTransactionInput`
- visible segment codes
- monthly input values
- UOM and transaction type
- protected metadata such as `ExistingRowFlag`

Recommended exclusions:

- child rows generated by calculations
- ceiling result/status columns
- audit columns
- created/updated system timestamps unless shown only for reference

## Download Modes

The generated workbook can support more than one mode.

Recommended download options:

- `Template only`
  - context plus ceilings plus empty transaction rows
- `Current submission workbook`
  - context plus ceilings plus existing head transactions

The second option is likely the most useful for the client’s offline process.

## Recalculation Strategy

Imported rows should pass through the same calculation path as normal online transaction entry.

Recommended approach:

- commit head rows first
- then call the existing processing logic per committed `TransactionID`
- optionally reuse or extract logic currently used by `process_transaction_stub.php`

This keeps:

- child row generation
- period result generation
- ceiling checks
- downstream reporting behavior

consistent with the rest of the module.

## Duplicate Handling

Recommended duplicate strategy:

- `NEW` rows never match by business key automatically in Phase 1
- only explicit `UPDATE` rows may update existing transactions
- do not silently merge by transaction type plus segments plus values

This is safer because budget submissions can contain very similar lines, and inferred matching would create hard-to-trace errors.

## Audit And Supportability

Every upload should record:

- uploader
- timestamp
- context
- source filename
- accepted and rejected counts
- whether recalculation was run
- final committed transaction IDs

The user should also be able to download an error workbook or CSV with:

- original row number
- original data
- validation status
- error message

## Recommended Phase Plan

### Phase 1

- one-context workbook import
- head rows only
- stage and commit flow
- validation summary
- recalculation after commit

### Phase 2

- template pre-populated with existing rows for offline update rounds
- richer Excel validation dropdowns
- background recalculation for large imports
- import history screen
- error workbook regeneration

### Phase 3

- approval workflow for uploaded batches
- bulk replacement mode
- API-based submission intake from external client systems

## Key Open Questions

Before build, we should confirm:

1. Should the spreadsheet be for one `DataObjectCode` only, or should one workbook support multiple cost centres or ministries?
2. Are offline users creating only new lines, or must they also edit existing submitted lines?
3. Which business fields beyond monthly amounts and segment codes must be editable offline?
4. Should upload commit immediately after validation, or should a reviewer confirm the batch first?
5. What is the expected batch size?
6. Is recalculation required immediately on upload, or can it run asynchronously?
7. Do clients want the system to export an existing submission workbook as a starting point for offline editing?

## Recommended Starting Point

The safest and fastest first implementation is:

- one workbook per current context
- `NEW` and `UPDATE` of head rows only
- Excel template download
- upload to staging
- row-level validation
- explicit commit
- post-commit recalculation

That approach fits the current CBMSv21 transaction architecture and avoids putting spreadsheet-specific complexity directly into `tblTransactionInput`.
