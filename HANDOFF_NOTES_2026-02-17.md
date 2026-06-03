# Handoff Notes - Ceiling Checks (2026-02-17)

## Scope Completed Today
- Enhanced `backend-php/public/process_transaction_stub.php` to make ceiling-check behavior visible and testable on screen.
- Added cached-calculation detail output so BP rows and totals display even when calculation results are loaded from cache.
- Added ceiling preview/config output and enriched with runtime metadata.
- Added Redis-oriented ceiling engine path (atomic check/deduct) to reduce DB workload on enforcement path.
- Added UI controls for cache refresh and reseed/reset during testing.

## Key Runtime Features Now
- Ceiling preview includes:
  - `stage` (`pre-run`, `post-run`, `post-commit`)
  - `mode` (`per_transaction`, `group_headrecord`)
  - `enforcement` (`period`, `total`)
  - `source` (`db`, `redis`, `redis_balance`)
  - `cached_at`
  - `ceiling_total`, `ceiling_balance`, `amount_total`, `balance_before`, `balance_after`
  - `last_tx`, `balance_updated`, `already_processed`
- Browser buttons:
  - `Refresh Ceilings (Reset + Sync)` => reseeds DB balance from definition + clears Redis ceiling keys + refreshes snapshot
  - `Reseed/Reset Ceiling State` => same reseed/reset behavior
- CLI flags available:
  - `--ceiling-engine=redis|sproc`
  - `--ceiling-enforcement=total|period`
  - `--ceiling-check-mode=group_headrecord|per_transaction`
  - `--refresh-ceiling-cache`
  - `--reseed-ceiling-state`

## Current Defaults in Stub
- `ceiling_engine = redis`
- `enableCeilingSprocCheck = true` (still gates whether commit check path runs)
- `ceiling_check_mode = per_transaction`
- `ceiling_enforcement = period`

## Important Behavior Notes
- `ceiling_total` comes from `tblCeilingDefinition.CeilingBPTotal`.
- `ceiling_balance` may come from Redis live balance state (`source=redis_balance`) and can differ from DB unless reseeded.
- Preview line can be projection (`pre-run`/`post-run`) or committed (`post-commit`).
- If `already_processed=yes`, same transaction ID is idempotently skipped.
- In Redis engine mode, enforcement updates Redis balance state; DB balance is not updated per check by this new path.

## Why `amount_total` Looked Wrong Earlier
- In `group_headrecord`, early preview originally used persisted `tblTransactionResultFlat` snapshots.
- Post-run preview now supports in-memory override totals for this run.
- Added `stage` + `post-commit` preview to reduce ambiguity.

## Known Risks / Follow-up Items
1. Gate naming still uses `enable_ceiling_sproc_check` for both sproc and redis commit-check entry.
   - Suggest renaming later to generic `enable_ceiling_commit_check` for clarity.
2. Redis engine currently lacks write-behind persistence to DB (`tblCeilingBalance`) and reconciliation job.
   - Needed before production hardening.
3. If keeping Redis as source of truth, add:
   - queue/worker for DB sync
   - periodic reconcile script
   - Redis durability review (AOF/RDB settings)
4. If DB remains source of truth, revert `ceiling_engine` default to `sproc`.

## Quick Resume Commands
- Typical Redis-engine test:
  - `php .\backend-php\public\process_transaction_stub.php --tx=2 --ceiling-sproc-check --ceiling-engine=redis --ceiling-check-mode=group_headrecord --ceiling-enforcement=total`
- Force refresh snapshot only:
  - `php .\backend-php\public\process_transaction_stub.php --tx=2 --refresh-ceiling-cache`
- Full reseed/reset before test:
  - `php .\backend-php\public\process_transaction_stub.php --tx=2 --reseed-ceiling-state --refresh-ceiling-cache --ceiling-engine=redis`

## Files Touched Today
- `backend-php/public/process_transaction_stub.php`

## Suggested First Step Tomorrow
- Decide target operating model for production:
  1. `sproc` authoritative (DB lock/check/update)
  2. Redis authoritative with DB async sync/reconcile
- Then align defaults and naming (`enable_ceiling_sproc_check`) accordingly.
