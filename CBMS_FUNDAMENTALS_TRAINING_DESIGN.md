# CBMS Fundamentals Training Design

## Module

`CBMS Fundamentals`

This module is intended to give every user a short foundation track before they begin module-specific training.

## Design Approach

The fundamentals content is split into several short scenarios instead of one long scenario.

Why this structure works better:

- Users can repeat only the part they need.
- The training catalogue stays easier to maintain as the shared UI evolves.
- The sequence can be used as a prerequisite ladder before module training like Users, Strategy, or Budget Submission.
- Shared navigation and context concepts change less often than business-module workflows, so they make good reusable foundation content.

## Recommended Sequence

1. `cbms_fundamentals_home_nav`
2. `cbms_fundamentals_context`
3. `cbms_fundamentals_datascope`
4. `cbms_fundamentals_menu_nav`
5. Module-specific scenarios after the learner completes the fundamentals set

## Scenario Catalogue

### `cbms_fundamentals_home_nav`

Title: `Home Navigation Basics`

Purpose:
Introduce the shared top navigation bar and explain what the main controls do.

Coverage:

- Home button
- Menu button
- Language selector
- Help button
- Account link
- Logout link

### `cbms_fundamentals_context`

Title: `Fiscal Context Basics`

Purpose:
Explain how Fiscal Year and Version drive what data a user is seeing and editing.

Coverage:

- Fiscal Year selector
- Version selector
- Why active context matters

### `cbms_fundamentals_datascope`

Title: `DataScope And Status Basics`

Purpose:
Explain how organisational scope is selected and how to interpret the workflow status shown for the selected scope.

Coverage:

- DataScope picker
- Selected scope area
- Workflow status indicator

### `cbms_fundamentals_menu_nav`

Title: `Menu Navigation Basics`

Purpose:
Teach the two main ways users move around CBMS.

Coverage:

- Browse using the sidebar menu
- Jump directly using screen code / route entry

## Implementation Notes

- The fundamentals scenarios are designed around the shared navigation bar so they can run on `training/scenarios`.
- Stable target IDs were added to shared navigation controls to make training highlights reliable.
- A generic training runner route is now available at `training/runner`.
- The app includes fallback PHP definitions for the fundamentals scenarios, and a SQL seed script is available at:
  `backend-php/config/sql/seed_training_cbms_fundamentals.sql`

## Recommended Next Content

After this base set, the next training modules should be:

1. `Users Administration`
2. `Strategy`
3. `Base Configuration`
4. `Financial / Transaction Input`

Each of those modules can assume the learner already understands:

- navigation
- fiscal context
- DataScope
- workflow status awareness
