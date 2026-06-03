# Training Handoff Note

## Current State

The training solution is now database-driven and includes both:

- trainee-facing screens
  - `training/scenarios`
  - `training/summary`
  - `training/users`
  - `training/users-edit`
- admin maintenance screens
  - `training-admin/scenarios`
  - `training-admin/scenario-form`
  - `training-admin/steps`
  - `training-admin/step-form`
  - `training-admin/translations`

## Training Catalogue Architecture

Runtime now loads training scenarios from the database first, with PHP fallback retained as a safety net.

Main runtime file:

- [backend-php/app/Shared/TrainingScenarioCatalog.php](backend-php/app/Shared/TrainingScenarioCatalog.php)

DB-backed loader:

- [backend-php/app/Models/TrainingScenarioModel.php](backend-php/app/Models/TrainingScenarioModel.php)

## Training Catalogue Tables

The following tables are now in use:

- `tblTrainingScenarios`
- `tblTrainingScenarioTranslations`
- `tblTrainingScenarioSteps`
- `tblTrainingScenarioStepTranslations`
- `tblTrainingScenarioSamples`
- `tblTrainingScenarioSampleTranslations`

Schema + seed script:

- [backend-php/config/sql/create_training_scenario_catalog.sql](backend-php/config/sql/create_training_scenario_catalog.sql)

## Current Seeded Scenarios

- `users_create_demo` = `Create New User`
- `users_edit_record` = `Edit Existing User`

## Training Progress

Training progress persists to:

- `tblTrainingProgress`

Progress model:

- [backend-php/app/Models/TrainingProgressModel.php](backend-php/app/Models/TrainingProgressModel.php)

Summary screen:

- `training/summary`

Reset functions already exist on Training Summary:

- per-row reset
- reset all

These actions are admin-only and audited.

## Training Feature Toggle

Training visibility is controlled by:

- `TRAINING_FEATURES_ENABLED`

Helper:

- [backend-php/shared/training_features.php](backend-php/shared/training_features.php)

If the toggle is off:

- training menu items are hidden
- training buttons are hidden
- training routes are blocked

## Where the Training Admin Screens Are

Menu location:

- `Administration`
- `Configuration`
- `Training Configuration`
- `Training Catalogue`

Main route:

- `index.php?route=training-admin/scenarios`

Visibility conditions:

- `TRAINING_FEATURES_ENABLED` must be enabled
- user must have one of:
  - `USERS_ADMIN`
  - `ADMIN_ALL`
  - `SYSADMIN`

## Training Admin Files

Controller:

- [backend-php/app/Controllers/TrainingAdminController.php](backend-php/app/Controllers/TrainingAdminController.php)

Model:

- [backend-php/app/Models/TrainingCatalogAdminModel.php](backend-php/app/Models/TrainingCatalogAdminModel.php)

Views:

- [backend-php/app/Views/trainingadmin/ScenarioList.php](backend-php/app/Views/trainingadmin/ScenarioList.php)
- [backend-php/app/Views/trainingadmin/ScenarioForm.php](backend-php/app/Views/trainingadmin/ScenarioForm.php)
- [backend-php/app/Views/trainingadmin/StepList.php](backend-php/app/Views/trainingadmin/StepList.php)
- [backend-php/app/Views/trainingadmin/StepForm.php](backend-php/app/Views/trainingadmin/StepForm.php)
- [backend-php/app/Views/trainingadmin/Translations.php](backend-php/app/Views/trainingadmin/Translations.php)

## Wiring Files Updated

- [backend-php/config/routes.php](backend-php/config/routes.php)
- [backend-php/config/menu.php](backend-php/config/menu.php)
- [backend-php/app/Views/layouts/main.php](backend-php/app/Views/layouts/main.php)
- [backend-php/app/Views/strategy/_QuickNav.php](backend-php/app/Views/strategy/_QuickNav.php)
- [backend-php/app/Views/strategy/_RouteHelp.php](backend-php/app/Views/strategy/_RouteHelp.php)

## UI Pattern

The new training admin screens were built to follow the same shared Strategy-style shell used on the newer admin/config screens:

- helper instructions
- quick links
- single-card layout
- compact controls

`training-admin/` was added to the shared layout/help route prefixes in:

- [backend-php/app/Views/layouts/main.php](backend-php/app/Views/layouts/main.php)

## Important Prototype and Runtime Behavior Already in Place

### Create New User scenario

Includes:

- username
- first name
- last name
- display name
- email
- phone
- department
- job title
- enabled
- explanation of password flags
- save

`Display Name` now auto-defaults to `First Name + Last Name` on create, but remains editable.

### Edit Existing User scenario

Now correctly covers:

- user search
- filter
- open record
- update note
- save
- reopen record
- review tabs
- return to list

Final completion now persists properly as `Completed`.

## Training Overlay / Runner Notes

Already implemented:

- orange border highlight without yellow fill
- draggable overlay
- removable confusing runner button from overlay
- clearer completion wording
- scenario-specific runner state
- restart from current step
- restart from beginning
- skip step
- jump to step
- mark complete
- reopen scenario
- reset this scenario
- prerequisites
- next recommended scenario
- step notes
- live training banner on target screens

## Multi-language

The training data model is now ready for multilingual content.

Translations are supported for:

- scenario title / description / audience / prerequisites
- step title / instruction
- sample value templates

Language support is resolved using the active app language via:

- [backend-php/shared/lang.php](backend-php/shared/lang.php)

## Recommended Next Checks

Before scaling further:

1. browser-smoke-test the new `training-admin/*` screens
2. confirm `Training Catalogue` is visible for the intended admin users
3. test saving:
   - scenario details
   - step edits
   - translation edits
4. test with a non-`en` language once translated content is added

## Likely Next Improvements

- clone scenario
- clone steps from another scenario
- bulk import/export of training content
- richer validation for target element IDs
- eventually remove PHP fallback once DB catalogue is fully trusted

## CBMS Fundamentals

The new foundation module is now scaffolded as `CBMS Fundamentals`.

Current scenarios:

- `cbms_fundamentals_home_nav`
- `cbms_fundamentals_context`
- `cbms_fundamentals_datascope`
- `cbms_fundamentals_menu_nav`

Supporting implementation added:

- generic runner route: `training/runner`
- training overlay support on `training/scenarios`
- stable shared-nav target IDs in the main layout for training highlights
- SQL seed: `backend-php/config/sql/seed_training_cbms_fundamentals.sql`
- design note: `CBMS_FUNDAMENTALS_TRAINING_DESIGN.md`

## Verification

`php -l` passed for:

- training admin controller
- training admin model
- all new training admin views
- updated routes/menu/layout/quick-nav/route-help files
