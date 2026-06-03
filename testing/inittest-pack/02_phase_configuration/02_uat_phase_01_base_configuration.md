# CBMSv21 Phase 02A: Base Configuration Gate

Date prepared: 2026-05-28

## Purpose

This note is the sign-off gate for the base configuration stage of the fresh-client rebuild.

Use it after Phase 01 and while working through the Phase 02 configuration steps.

## Baseline For This Gate

- Database: `CBMSv2_INITTEST`
- Fiscal Year: `2026`
- Default version: `1`

## Required Access

Run this gate as a user with broad admin coverage.

Recommended:

- `Super Admin`

## Gate Sequence

### 1. Login And Context

Confirm:

- login succeeds
- home page loads cleanly
- default context is `FY 2026 / Version 1`

### 2. Fiscal Context

Confirm:

- fiscal year `2026` exists and is active
- version `1` exists and is active
- one active default version exists for the active fiscal year
- system settings point to the correct default fiscal year and version

### 3. Security Foundation

Confirm:

- roles exist
- permissions exist
- the admin test user has assigned roles
- user maintenance screens load

### 4. Workflow Foundation

Confirm:

- workflow engine definitions load
- workflow inquiry loads
- workflow assignments screen loads

Expected note:

- assignments may still be empty until client-specific routing is configured
- the readiness screen should warn if routing remains unconfigured once organisation structure and users are in place

### 5. Organisation Structure

Confirm:

- data object types exist
- data object codes are loaded for FY `2026`
- hierarchy links are loaded
- scoped access warnings are either understood as not-yet-in-scope or resolved with real access rows

### 6. Segment Foundation

Confirm:

- segments are configured
- segment definitions include usable code, naming, layout, dimension, grouping, and usage metadata
- segment values are loaded for FY `2026`
- parent-link requirements are met where needed

### 7. Financial Setup Readiness

Confirm:

- transaction types exist
- transaction-type segment rules are in place for the active context
- rates and other required financial setup are available if financial testing is starting

## Built-In Screen Tests To Run

Run these in `Testing -> Test Scripts`:

1. `Base Configuration Readiness Gate`
2. `System Settings Baseline Review`
3. `Data Object Codes And Scope Readiness`
4. `Segment Catalogue Setup Review`
5. `Segment Values Baseline Review`
6. `Workflow Engine Foundation Review`

## SQL Verification

Use this SQL companion when the readiness screen shows a warning or blocker and you want the underlying rows:

- [03_base_configuration_readiness_verification.sql](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/02_phase_configuration/03_base_configuration_readiness_verification.sql)

## Sign-Off Standard

This gate is complete when:

- no critical blockers remain in base configuration readiness
- the admin user can navigate the core setup screens
- organisation structure, segments, and segment values are present
- workflow foundation is reachable
- the environment is ready for broader module testing
