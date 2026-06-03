# Handoff Notes - Ceiling Runtime + Bulk Processing (2026-02-18)

## Scope Completed Today
- Extended `backend-php/public/process_transaction_stub.php` with admin operations and safer bulk behavior.
- Added Redis/ceiling tooling screens and buttons for runtime operations.
- Added warmup support via both CLI and on-screen action.
- Added bulk performance fast path by skipping preview-heavy ceiling diagnostics during bulk runs.
- Fixed bulk action parameter leakage that was causing unintended reseed/reset behavior.

## Major Functional Changes
1. Bulk processing
- Added `Run Save For ALL Transactions` flow that loops over `tblTransactionInput` and calls existing save logic per row.
- Bulk result screen now shows concise status table only (no verbose per-row debug dump).
- Bulk run now strips action flags from URL and forcibly disables:
  - `force_refresh_ceiling_cache`
  - `force_reseed_ceiling_state`
- New bulk fast mode default:
  - `skip_ceiling_previews = true` in bulk path
  - ceiling enforcement/commit checks still run
- Optional fallback to old behavior:
  - `?bulk_run=1&bulk_include_previews=1`

2. Timeout handling
- Added long-run guard (`set_time_limit(0)` / `max_execution_time=0`) in transaction flow.

3. Warmup support
- Added `backend-php/public/warmup_ceiling_cache.php` as reusable module + CLI entry.
- Added on-screen action button:
  - `Warmup Ceiling Cache Now`
- Warmup now accessible via web route:
  - `?warmup_ceiling_cache=1`

4. Ceiling balance sync/reset admin actions
- Added `Sync Ceiling Balances Now`:
  - Redis balance hashes -> `tblCeilingBalance`
- Added `Global Reset Balances to Ceilings`:
  - resets DB balances from `tblCeilingDefinition`
  - inserts missing balance rows
  - clears Redis `ceiling:balance:v1:*` and `ceiling:snapshot:*`

5. Monitoring screens
- Added `backend-php/public/ceiling_monitor.php`:
  - live auto-refresh Redis balance monitor
  - recent failed ceiling checks from `tblTransactionInput`
- Added button on tools/actions:
  - `Ceiling Monitor`
- Added `View Redis Balances` list screen in stub route.

6. No-`tx` screen behavior
- `process_transaction_stub.php` no longer auto-runs `tx=2` when `tx` is missing.
- It now renders a control/tools screen so admin buttons can be used without transaction execution.

## Root Causes Found During Testing
1. Bulk runtime >1 hour / 60k rows
- Main hotspot was repeated preview lookup calls (`getCeilingCheckPreview`) during save flow.
- Bulk fast mode now skips these preview calls by default.

2. Balance unexpectedly decreasing then increasing
- Caused by:
  - negative transactions
  - action flag leakage (`reseed_ceiling_state` / `refresh_ceiling_cache`) into bulk URL
- Flag leakage fixed in bulk link generation and bulk options.

## Recommended Run Order (Current)
1. `Global Reset Balances to Ceilings` (when starting clean test cycle)
2. `Warmup Ceiling Cache Now`
3. `Run Save For ALL Transactions`
4. Watch `Ceiling Monitor` during run
5. Use `Sync Ceiling Balances Now` only when explicitly needed

## Key Files Touched Today
- `backend-php/public/process_transaction_stub.php`
- `backend-php/public/warmup_ceiling_cache.php`
- `backend-php/public/ceiling_monitor.php`

## Quick URLs
- Tools screen (no tx): `process_transaction_stub.php`
- Bulk run: `process_transaction_stub.php?bulk_run=1`
- Warmup: `process_transaction_stub.php?warmup_ceiling_cache=1`
- Global reset: `process_transaction_stub.php?global_reset_balances=1`
- Redis balances: `process_transaction_stub.php?view_redis_balances=1`
- Live monitor: `ceiling_monitor.php?refresh=5&limit=500&recent_limit=100`

## Suggested First Step Tomorrow
- Re-run bulk with current defaults and capture throughput (rows/min) vs prior run.
- If still slow, next tuning pass:
  - reduce duplicate calculations for repeated signatures
  - consider constrained batching/worker parallelism strategy

---

## Session Update - Redis Ceiling Definitions + Chain Config Discussion (2026-02-18, later)

### What Was Reported
- New rows in `tblCeilingDefinition` were not appearing in Redis.
- Bulk run was requested to target `VersionID = 5`.
- Follow-up concern: child calculations not running as expected.
- Strategic discussion: redesigning chain configuration.

### Root Cause Confirmed (Redis Ceiling Warmup)
- `backend-php/public/warmup_ceiling_cache.php` only iterated distinct transaction signatures from `tblTransactionInput`.
- Result: a newly created ceiling definition with no matching transaction signature would not get a Redis balance key.

### Fix Implemented
- Updated `backend-php/public/warmup_ceiling_cache.php` to add a second pass:
  - reads all active+approved definitions from `tblCeilingDefinition` (optionally filtered by FY/version),
  - writes missing `ceiling:balance:v1:{CeilingDefinitionID}` hashes even when no tx signature exists.
- Syntax check passed:
  - `php -l backend-php/public/warmup_ceiling_cache.php`

### Bulk Run Change Implemented
- Updated `backend-php/public/process_transaction_stub.php`:
  - `getBulkTransactionIds()` now enforces `VersionID = 5` in the bulk selector query.
  - Bulk button label/confirm text updated to reflect VersionID=5 scope.
- Syntax check passed:
  - `php -l backend-php/public/process_transaction_stub.php`

### Important Clarification Captured
- Chain traversal is driven by `CalculationID -> ChildCalculationID`, not by version alone.
- Version only affects which transactions are selected in bulk and context/filters, not the core next-step linkage.

### Proposed Chain Redesign (Drafted, not implemented)
- New tables proposed:
  - `tblCalcChainSet`
  - `tblCalcChainStartRule`
  - `tblCalcChainEdge`
  - optional validation run/issue tables
- Goal:
  - explicit start-rule mapping,
  - explicit edge graph,
  - publishable/versioned chain configs,
  - easier validation (cycles/orphans/multiple defaults).

### SQL Correction Logged
- Seed script error encountered: `Invalid column name 'ActiveFlag'` on `tblCalculations`.
- Provided schema-adaptive edge seed logic that checks for:
  - `ActiveFlag`, else
  - `CalculationStatus`, else
  - `Status`, else no status filter.

### Open Items For Next Session
1. Decide whether bulk run should stay hardcoded to `VersionID = 5` or become a configurable query parameter.
2. If child generation still seems wrong, inspect actual chain data for selected tx rows:
   - starting calc from `tblUOMs`,
   - each hop `ChildCalculationID`,
   - `GenerateTransaction` flags.
3. If approved, implement chain v2 tables + resolver sprocs and migrate runtime reads.

### Commands/Paths Used This Session
- Redis/warmup logic:
  - `backend-php/public/warmup_ceiling_cache.php`
- Bulk/chain runtime:
  - `backend-php/public/process_transaction_stub.php`
- Key command used:
  - `php -l backend-php/public/warmup_ceiling_cache.php`
  - `php -l backend-php/public/process_transaction_stub.php`
