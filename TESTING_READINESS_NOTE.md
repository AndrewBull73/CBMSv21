# Testing Readiness Note

## Purpose

Capture the key practical items that will help CBMSv21 move into structured screen and workflow testing more smoothly.

This note does **not** introduce any implementation yet. It is a readiness checklist and planning reference for the future testing phase.

## Recommended Readiness Items

### 1. Standard Test Users

Create stable test accounts for the main business roles, for example:

- Strategic Framework User
- Strategic Framework Reviewer
- Strategic Framework Approver
- Reporting User
- Configuration Administrator
- System Administrator

This makes it easier to test:

- menu visibility
- role-based access
- workflow routing
- approval paths

### 2. Standard Test Data Packs

Prepare a small set of known test scenarios and datasets, for example:

- simple happy-path setup
- incomplete configuration case
- workflow approval case
- cross-Org Unit / ministry case
- reporting case

This helps testers work against consistent examples instead of inventing their own data each time.

### 3. Reset Scripts

Keep standard reset scripts available at a few levels, for example:

- reset input data only
- reset strategy setup plus input
- post-reset verification checks

This allows testers to restart cleanly and repeat scenarios reliably.

### 4. Known-Good Baseline Context

Agree one standard fiscal year / version / Org Unit context for general testing unless a scenario explicitly requires a different context.

This reduces confusion and makes defect reproduction easier.

### 5. Smoke Test Pack

Define a short smoke test pack to run before deeper testing begins, covering:

- login
- menu access
- create
- edit
- archive / delete
- workflow step
- report load

This provides a fast first check that the environment is basically usable.

### 6. Defect And Retest Tracker

Maintain a simple register that records:

- screen
- scenario
- tester
- date
- pass / fail
- defect reference
- retest status

This can be lightweight at first, but it should be consistent.

### 7. Verification SQL Library

Before a full automated test runner exists, keep a small library of manual verification SQL queries by module.

Examples:

- projects
- activities
- budgets
- funding requests
- workflow tasks
- narratives and risks

This helps confirm whether the database state matches what the tester entered on screen.

### 8. Environment Identification

Make sure the environment clearly shows whether it is:

- test
- training
- UAT
- production

This helps prevent confusion and accidental testing in the wrong environment.

### 9. Change Freeze Point

Once formal testing begins, try to reduce structural UI, schema, and workflow changes as much as possible.

Frequent changes during testing will:

- invalidate scripts
- confuse testers
- make defects harder to compare

### 10. Test Runner Preparation

When the application is stable enough, move toward the future screen test runner described in:

- [SCREEN_TEST_FRAMEWORK_DESIGN_NOTE.md](C:/xampp82/htdocs/CBMSv21/SCREEN_TEST_FRAMEWORK_DESIGN_NOTE.md)

That future test runner should become the structured way to:

- present test scenarios
- direct testers to target screens
- run verification
- record results

## Recommended Priority Order

When the team is closer to testing, the first things to prepare should be:

1. standard test users
2. reset scripts and known test datasets
3. known-good baseline context
4. smoke test pack
5. defect / retest tracker
6. verification SQL library

## Summary

The main objective is to make testing:

- repeatable
- understandable
- role-aware
- easy to reset
- easy to verify

Preparing these items before formal testing starts will save a lot of confusion and rework later.
