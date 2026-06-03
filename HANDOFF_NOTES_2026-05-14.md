# Handoff Notes - Training, Multi-Window Context, and Test Script Runner (2026-05-14)

## Scope Completed Recently

- Continued the training module from the earlier handoff and stabilized the trainee-facing and admin-facing flows.
- Added the `CBMS Fundamentals` training track as a foundation module before business-module training.
- Moved Training Administration under `Configuration`.
- Implemented linked multi-window context isolation so separate CBMS windows can keep different `Fiscal Year`, `Version`, and `DataScope`.
- Added a shared navbar `New Window` action.
- Implemented the first version of the `Test Scripts` runner as a dedicated tester workflow.
- Fixed a menu visibility issue so `Testing` is visible to any signed-in user.

## Major Functional Changes

### 1. Training Module

- Training administration now sits under:
  - `Configuration`
  - `Training Configuration`
  - `Training Catalogue`

- Training content behavior is split as follows:
  - shared UI labels still use the normal language files
  - actual scenario titles, descriptions, steps, and sample values are database-driven where the training catalogue tables exist
  - PHP fallback definitions are retained so runtime still works if the DB catalogue is unavailable

- `CBMS Fundamentals` was added as a new training foundation module with starter scenarios covering:
  - home navigation basics
  - fiscal context basics
  - DataScope and status basics
  - menu navigation basics

- The fundamentals runner was built to work on shared-shell screens, including the learner catalogue page.

### 2. Training UX Fixes

- Fixed training admin pages so they no longer crash if the training catalogue schema is missing.
- Fixed a live `500` on training translations caused by SQL Server named parameter reuse in a `UNION` query.
- Fixed the final-step completion flow so a fundamentals scenario re-renders cleanly as completed instead of appearing stuck on the last step.
- Changed confusing overlay wording:
  - `Stop` became `Leave Scenario`
  - completed-state close action became `Finish Scenario`
- Fixed stale completed overlay behavior on `training/scenarios` so reopening the catalogue no longer auto-resurrects the last completed scenario unless explicitly requested.
- Added search, module filter, status filter, and module grouping to the `Training Scenarios` screen so it scales better for many scenarios.

### 3. Multi-Window Linked Context

- Implemented linked context propagation for:
  - `FiscalYearID`
  - `VersionID`
  - `scope.dataobject_code`
  - `scope.dataobject_name`

- Separate browser windows or tabs can now keep different context values when users navigate normally from links and forms inside each window.

- Shared hardening was added for:
  - normal same-app links
  - normal form posts
  - same-origin `fetch()`
  - same-origin `XMLHttpRequest`
  - iframe URLs
  - several special-case screens that build their own URLs

- Important bug fixes during this work:
  - clearing `DataScope` no longer bounces to `home/index` when a page uses an app-relative return URL
  - explicit view variables like `userId` and `username` are no longer overwritten by the logged-in session user during render, which fixed the admin account-access iframe flow
  - “no scope” is now preserved explicitly, so one window with empty scope does not inherit another window’s selected scope

- Added a shared main-navbar action:
  - label: `New Window`
  - purpose: open the current CBMS screen in a second window with the current linked context

### 4. Screen Test Runner

- Implemented the first version of a dedicated testing utility based on the earlier design note preference for a separate runner, not an overlay.

- New routes:
  - `screen-tests/scenarios`
  - `screen-tests/runner`
  - `screen-tests/start`
  - `screen-tests/save-result`
  - `screen-tests/summary`

- New menu area:
  - `Testing`
  - `Test Scripts`
  - `Test Results`

- The first version includes:
  - a central test-script catalogue
  - a dedicated runner page
  - structured test details
  - sample test data generation
  - expected visible outcomes
  - optional verification SQL snippets
  - result recording with pass/fail/blocked
  - verification status
  - notes and defect reference

- Current starter test scripts include:
  - `cbms_context_smoke`
  - `cbms_multi_window_smoke`
  - `training_catalogue_smoke`
  - `users_create_smoke`
  - `users_account_access_smoke`

- Persistence behavior:
  - if `dbo.tblScreenTestRuns` exists, results are stored persistently
  - if not, the runner still works in session-only mode for the current browser session

- A menu visibility issue was found after implementation:
  - the `Testing` menu used the pseudo-permission `AUTHENTICATED`
  - RBAC originally only recognized real permission codes from the DB
  - this was fixed so `AUTHENTICATED` now means any signed-in user

## Key Files Added Or Changed

### Training

- `backend-php/app/Controllers/TrainingController.php`
- `backend-php/app/Controllers/TrainingAdminController.php`
- `backend-php/app/Models/TrainingCatalogAdminModel.php`
- `backend-php/app/Shared/TrainingScenarioCatalog.php`
- `backend-php/app/Views/training/TrainingScenarios.php`
- `backend-php/app/Views/training/_TrainingOverlay.php`
- `backend-php/app/Views/training/UsersTrainingRunner.php`
- `backend-php/app/Views/trainingadmin/ScenarioList.php`
- `backend-php/app/Views/trainingadmin/ScenarioForm.php`
- `backend-php/app/Views/trainingadmin/StepForm.php`
- `backend-php/app/Views/trainingadmin/Translations.php`
- `backend-php/config/sql/seed_training_cbms_fundamentals.sql`
- `CBMS_FUNDAMENTALS_TRAINING_DESIGN.md`
- `TRAINING_HANDOFF_NOTE.md`

### Multi-Window Context

- `backend-php/app/Controllers/BaseController.php`
- `backend-php/app/Controllers/ContextController.php`
- `backend-php/app/Controllers/DataObjectsController.php`
- `backend-php/app/Controllers/WorkflowController.php`
- `backend-php/app/Controllers/AuthController.php`
- `backend-php/app/Views/layouts/main.php`
- `backend-php/app/Views/dataobjects/DataObjectPicker.php`
- `backend-php/app/Views/workflow/WorkflowList.php`
- `backend-php/app/Views/workflow/WorkflowForm.php`
- `backend-php/app/Views/auth/RefreshAccessView.php`
- `backend-php/app/Views/home/HomeIndexView.php`
- `backend-php/app/Views/users/UserForm.php`
- `backend-php/public/transaction_input_editor.php`

### Test Scripts

- `backend-php/app/Controllers/ScreenTestsController.php`
- `backend-php/app/Models/ScreenTestRunModel.php`
- `backend-php/app/Shared/ScreenTestCatalog.php`
- `backend-php/app/Views/screentests/Scenarios.php`
- `backend-php/app/Views/screentests/Runner.php`
- `backend-php/app/Views/screentests/Summary.php`
- `backend-php/config/sql/create_tblScreenTestRuns.sql`
- `backend-php/config/routes.php`
- `backend-php/config/menu.php`
- `backend-php/lang/en.php`
- `backend-php/lang/fr.php`
- `backend-php/app/Core/Rbac.php`

## Verification Completed

### Training

- `php -l` passed on the touched training controller, model, and view files.
- Live route verification confirmed:
  - unauthenticated training routes redirect to login
  - authenticated training catalogue loads
  - fundamentals completion now returns completed state cleanly
  - the stale completed overlay no longer appears when opening a plain `training/scenarios` page

### Multi-Window Context

- A focused live smoke sweep was completed across:
  - `Training Scenarios`
  - `Users > Account Access`
  - `Home > My Open Tasks`
  - `DataScope` picker
  - `Transaction Input Editor`

- The targeted pass completed cleanly and covered the main AJAX, iframe, and linked-navigation risk areas for the multi-window feature.

### Test Scripts

- `php -l` passed on all newly added screen-test files plus touched routing/menu/i18n files.
- Route-level live checks confirmed that:
  - `screen-tests/scenarios`
  - `screen-tests/runner`
  - `screen-tests/summary`
  resolve through the live app entrypoint and redirect to login correctly when unauthenticated

## Important Current Behavior Notes

### Test Runner UX

- The test runner is intentionally not a training-style overlay.
- It follows the separate-runner design from `SCREEN_TEST_FRAMEWORK_DESIGN_NOTE.md`:
  - tester opens the runner
  - tester opens the target screen in another tab or window
  - tester performs the actions
  - tester returns to the runner
  - tester records the result and optional verification status

- If a future tester UX change is desired, the most sensible upgrade path would be:
  - keep the dedicated runner for full scripts
  - optionally add a compact floating test panel for short smoke tests

### Screen Test Storage

- Persistent logging requires:
  - `backend-php/config/sql/create_tblScreenTestRuns.sql`

- Without that table:
  - scripts still run
  - results are stored only in session
  - results will not be available to other users or after session loss

## Residual Risks / Gaps

1. The multi-window foundation is now strong, but not every screen in the application has been manually clicked in a browser.
2. The new screen-test module has route-level verification and syntax verification, but it still needs a real authenticated click-through:
   - open script
   - start run
   - open target screen
   - save result
   - confirm summary behavior
3. The current test scripts are starter packs only. They need expansion into full module-specific UAT or smoke packs over time.
4. The screen-test runner currently supports manual verification status plus displayed SQL snippets; it does not yet execute verification SQL automatically.

## Suggested First Step Next Session

1. Log in and open:
   - `Testing > Test Scripts`
2. Run one complete smoke cycle with:
   - `cbms_context_smoke`
3. Confirm:
   - runner page loads correctly
   - `Start Test Run` works
   - `Open Target Screen` and `Open In New Window` behave correctly
   - `Save Result` writes to session or DB as expected
   - `Test Results` shows the saved outcome

## Suggested Follow-On Work

1. Install `create_tblScreenTestRuns.sql` if persistent shared test results are required.
2. Add the first proper module-specific packs, likely:
   - strategy setup/configuration smoke pack
   - funding submission smoke pack
   - reporting smoke pack
3. Decide whether the testing UX should remain dedicated-runner only, or gain an optional compact floating helper for short scripts.
4. Continue broader real-browser sweep of the multi-window context across more modules if comparison workflows will be used heavily.
