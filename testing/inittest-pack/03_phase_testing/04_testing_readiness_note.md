# Testing Readiness Note

## Purpose

This note captures the minimum testing setup expected after the fresh-client rebuild and configuration phases.

## Readiness Checklist

Before broader testing starts, confirm:

1. the environment is clearly identified as `CBMSv2_INITTEST`
2. at least one admin user can sign in
3. one or more business-role test users exist
4. default context is understood for the test cycle
5. workflow assignments are configured for the scenarios being tested
6. organisation structure and segment values are loaded for FY `2026`
7. defect logging and evidence capture are ready
8. verification SQL is available for the modules under test

## Recommended Test User Set

Prepare at least:

- `Super Admin`
- `System Administrator`
- `Configuration Administrator`
- `Strategic Framework User`
- `Strategic Framework Reviewer`
- `Strategic Framework Approver`
- `Budget Submission Administrator`
- `Budget Execution Administrator`
- `Reporting User`

## Recommended Context Rules

Use these defaults unless a script says otherwise:

- fresh-start build and configuration: `FY 2026 / Version 1`
- execution-focused tests: `FY 2026 / Version 2`
- legacy regression/UAT pack: use the exact context named in that pack

## Reset Rule

If the environment needs to restart from zero-state, return to:

- [01_phase_environment_reset](/C:/xampp82/htdocs/CBMSv21/testing/inittest-pack/01_phase_environment_reset)

Do not mix partial legacy reset scripts into the fresh-start cycle unless the test objective is intentionally narrow.
