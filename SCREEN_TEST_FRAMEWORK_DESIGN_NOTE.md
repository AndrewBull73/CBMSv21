# Screen Test Framework Design Note

## Purpose

Create a future test framework that helps business testers validate CBMSv21 screens in a structured way and optionally verify saved results against the database.

This is **not** to be implemented yet. The note exists so the idea is not lost while screens, roles, workflows, and schema are still being refined.

## Why Wait

We should delay implementation until:

- the main screens are close to final layout
- workflow paths are stable
- role and permission design has mostly settled
- major schema changes have slowed down

If we build too early, we will likely have to rewrite:

- test steps
- expected results
- SQL verification checks
- route and menu references

## Recommended Future Design

### 1. Test Catalogue

Maintain a central list of test scenarios, for example:

- module
- screen / route
- scenario name
- purpose
- prerequisite setup
- test data to use
- expected visible result
- expected database outcome

### 2. Screen Test Guide

Allow a tester to open a screen-level test guide that shows:

- what the screen is for
- what needs to exist first
- step-by-step actions
- exact test values to enter
- expected outcome

### 3. Verification Runner

After the tester completes a scenario, provide a way to run a verification check that confirms:

- the row was created or updated
- expected key values were saved
- related rows were created where applicable
- status / workflow changes were applied correctly

### 4. Result Logging

Store the outcome of each test run, including:

- tester
- date/time
- scenario
- pass / fail
- notes
- verification result

## Preferred Tester UI

The preferred future UI is a **separate test screen / test runner**, not a modal.

Reason:

- testers will likely work across many screens
- they may need to move between test scenarios in a controlled order
- they may need to record notes and results centrally
- verification output is easier to manage on a dedicated page

## Recommended Test Runner Pattern

The future test runner could work like this:

1. tester opens a central test screen
2. tester selects module, screen, and scenario
3. the runner shows:
   - purpose
   - prerequisites
   - steps
   - sample values
   - expected results
4. tester opens the target screen in another tab or window
5. tester completes the actions
6. tester returns to the test runner
7. tester runs verification and records notes

This is preferred over:

- modal-based instructions
- very long inline instructions on every business screen

Inline help may still be useful later for very short smoke tests, but the main testing workflow should sit in a dedicated test screen.

## Recommended Architecture

Do **not** embed test SQL or hardcoded test steps inside each screen controller.

Instead, use:

- central test definitions
- reusable verification rules
- a separate test runner / admin utility

This keeps production code cleaner and avoids mixing business logic with testing logic.

## Example Future Flow

1. Tester opens the test runner.
2. Tester selects a screen test pack.
3. Tester follows the guided steps.
4. Tester enters sample values and saves on the target screen.
5. Tester returns to the test runner and runs verification.
6. System checks database state.
7. Result is logged as pass/fail with notes.

## Suggested Trigger To Start Building

Start implementation when we are approaching:

- UAT
- formal screen testing
- release-readiness validation

At that point the framework will be more stable, more reusable, and much less likely to need redesign.
