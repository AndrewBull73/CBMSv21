# Training Mode Guided Prompts Note

## Purpose

Capture the idea of a future **Training Mode** for CBMSv21 that helps new users learn screens and workflows through guided on-screen prompts, field highlighting, and step-by-step progression.

This is a design note only. It is **not** for implementation yet.

## Core Idea

When Training Mode is enabled, the application should be able to:

- show short contextual training prompts
- highlight the field, control, or button the trainee should use next
- track where the user is in a training scenario
- move the highlight to the next step when the current step is completed

## Key Question

### Will Training Mode know what step the user is at?

Yes, that is the recommended design.

Training Mode should not be just a set of passive help tips. It should understand the current step in a defined training scenario.

That means:

- the scenario defines the ordered steps
- each step points to a target field, button, or action
- each step has a completion rule
- when the completion rule is met, the runner advances to the next step

## Recommended User Experience

Example flow:

1. user starts a training scenario
2. Training Mode highlights the first field
3. a short prompt explains what to enter and why
4. user completes the field or action
5. the system detects completion
6. highlight moves to the next field or button
7. the scenario continues until complete

## What Can Be Highlighted

Training Mode could highlight:

- text inputs
- select lists
- checkboxes
- action buttons
- tabs
- navigation links
- workflow buttons
- report filters

It could also:

- scroll the relevant control into view
- dim the rest of the page slightly
- show a small prompt bubble or side note

## How Step Progression Should Work

Each training step should define:

- `StepNumber`
- `Route`
- `TargetSelector`
- `InstructionText`
- `ExpectedActionType`
- `CompletionRule`

Examples of completion rules:

- field has a non-empty value
- selected option matches the expected training value
- button is clicked
- record is saved successfully
- workflow status changes
- navigation moves to the next screen

## Example Scenario

### Create a Project

Step 1:
- route: `strategy-setup/project-form`
- target: `ProjectCode`
- prompt: `Enter the project code first.`
- completion: field not empty

Step 2:
- target: `ProjectName`
- prompt: `Enter the project name.`
- completion: field not empty

Step 3:
- target: `LifecycleStatusCode`
- prompt: `Choose the current project status.`
- completion: selected

Step 4:
- target: `Save Project`
- prompt: `Save the project record.`
- completion: save succeeds and record exists

## Scenario State

Training Mode should track scenario state explicitly.

Recommended state elements:

- training scenario id
- current step number
- started by user id
- current route
- step completion status
- optional notes
- whether the scenario is complete

This could later support:

- pause and resume
- trainer-led walkthroughs
- user self-paced learning
- progress tracking by role or module

## Recommended Architecture

Do not hardcode the training sequence directly into each screen.

Instead:

- define training scenarios centrally
- use selectors or field ids to point to controls
- use a training runner to decide which step is active
- let screens expose stable ids / hooks that Training Mode can target

This is important because:

- the same screen may be used in multiple scenarios
- steps may differ by role
- scenarios will change over time

## Relationship To Screen Stability

This should be implemented only after:

- major screen layouts have stabilized
- field names and button labels are mostly settled
- workflows are stable

Otherwise the training selectors and step definitions will need constant rework.

## Recommended Future Components

1. `Training Scenario Catalogue`
- defines scenarios and step order

2. `Training Runner`
- starts, pauses, resumes, and advances scenarios

3. `Screen Highlight Layer`
- visually highlights fields and actions

4. `Prompt Renderer`
- shows the instruction text for the current step

5. `Scenario Progress Log`
- records who completed which training scenario

## Suggested Uses

Training Mode would be especially valuable for:

- new user onboarding
- role-based training
- complex workflow training
- guided demonstrations
- refresher learning before UAT or rollout

## Summary

Yes, the preferred future design is:

- highlight the first required field or action
- detect when the user completes that step
- move the focus and highlight to the next step
- track the user’s progress through a defined training scenario

This should be designed as a structured scenario runner, not just a set of static help notes.
