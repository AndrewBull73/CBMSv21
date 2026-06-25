# Handoff Notes - Strategic Budget Framework, Funding Lodgements, and Segment Values (2026-06-15)

## Session Summary

- Continued the Strategic Framework build-out for CBMSv21.
- Added source segment value awareness to Strategic Segment Mapping and readiness checks.
- Created a SQL helper to load `tblSegmentValues` from the legacy `CBMSGOL` database into the current `CBMSv2_INITTEST` structure.
- Added and refined Funding Lodgement workflow screens, helper instructions, quick links, and UI consistency.
- Added a new Strategic Budget Framework Guide screen under the Strategy Start & Review menu.
- Fixed a Project Register screen error caused by missing context-label handling in the strategy setup controller.

This note is the current handoff baseline for the Strategic Framework work completed on 14-15 June 2026.

## Major Outcomes Completed

### 1. Strategic Segment Mapping Now Warns When Values Are Missing

- Strategic Segment Mapping now shows whether mapped segments have values loaded.
- If a mapped segment has no segment values, the mapping row shows a visible warning badge:
  - `No segment values loaded`
- If values exist, the row shows the loaded count.
- Configuration Readiness now includes a critical check for mapped segment values.
- `Needs Attention` in readiness now includes both critical and warning checks.

Key files:

- `backend-php/app/Models/StrategicBudgetingAdminModel.php`
- `backend-php/app/Models/StrategicBudgetingModel.php`
- `backend-php/app/Views/strategy/SegmentMapping.php`
- `backend-php/app/Views/strategy/ReportReadiness.php`

### 2. Strategic Dimension Constraint Script Was Updated

- `PROJECT` was added to the allowed strategic dimension list for `tblSbSegmentConfig`.
- This resolves save failures when Project is used as a strategic mapping dimension.

Key file:

- `backend-php/config/sql/alter_tblSbSegmentConfig_add_objective_target_activity.sql`

Important:

- Run this script against the active target database if Project mapping fails with an older-dimension-list constraint error.

### 3. Segment Values Load Script Was Created

- Created a SQL script to copy legacy values from:
  - `CBMSGOL.dbo.tblSegmentValues`
- Into:
  - `CBMSv2_INITTEST.dbo.tblSegmentValues`

The script supports:

- Preview mode by default.
- Insert/update mode when `@PreviewOnly = 0`.
- Optional replacement of existing values for loaded segments using `@ReplaceExisting = 1`.
- Loading all segments or one segment using:
  - `@OnlySegmentNo int = NULL`

Key file:

- `backend-php/config/sql/load_tblSegmentValues_from_cbmsgol_tblSegmentValues.sql`

Important parameters:

- `@SourceBudgetID = 8`
- `@TargetFiscalYearID = 2026`
- `@OnlySegmentNo = NULL`
- `@PreviewOnly = 1`
- `@ReplaceExisting = 0`

Recommended use:

1. Set `@OnlySegmentNo` if only one mapped segment is required.
2. Run with `@PreviewOnly = 1`.
3. Review summary and sample rows.
4. Set `@PreviewOnly = 0` to insert/update.

### 4. Project Register Error Was Fixed

- The Project Register screen had an error:
  - `Call to undefined method App\Models\StrategicBudgetingAdminModel::getContextLabels()`
- The setup controller now resolves context labels through the strategic reporting model instead of the admin model.

Key file:

- `backend-php/app/Controllers/StrategySetupController.php`

### 5. Funding Lodgement Screens Were Refined

- Added helper instructions for Funding Lodgement workflow screens:
  - Funding Lodgements
  - Funding Reviews
  - Funding Approvals
  - Funding Lodgement Header
  - Funding Lodgement Workflow
  - Funding Lodgement Item
  - Funding Item Review
  - Funding Submission Summary

- Added a Funding Lodgements quick-links group covering:
  - Lodgements
  - Reviews
  - Approvals
  - Submission Summary
  - All Submissions

Key files:

- `backend-php/app/Views/strategy/_RouteHelp.php`
- `backend-php/config/quick_links.php`

### 6. Funding Lodgement UI Was Aligned To UI Standard

- Funding Lodgement list screen was brought back to the standard Strategy screen style:
  - shared card header
  - standard dense table
  - Bootstrap card layout
  - icon + text action buttons
  - removed custom gradient/hero styling

Key file:

- `backend-php/app/Views/strategy/FundingSubmissionList.php`

### 7. Funding Submission Summary UI Was Aligned To UI Standard

- Funding Submission Summary was converted from a custom gradient report shell to the standard report pattern:
  - shared card header
  - metric cards
  - standard report cards
  - dense tables
  - consistent action buttons

Key file:

- `backend-php/app/Views/strategy/FundingSubmissionReport.php`

### 8. Strategic Budget Framework Guide Screen Was Added

- Added a high-level process guide for preparing the Strategic Budget Framework.
- The guide explicitly documents that Funding Lodgements should normally be completed and reviewed before final ceilings are set.
- Added a Print button using `window.print()`.
- Added the screen to:
  - Strategy Start & Review menu
  - Start & Review quick links
  - contextual helper instructions

Route:

- `index.php?route=strategy/framework-guide`

Key files:

- `backend-php/app/Controllers/StrategyController.php`
- `backend-php/app/Views/strategy/FrameworkGuide.php`
- `backend-php/config/routes.php`
- `backend-php/config/menu.php`
- `backend-php/config/quick_links.php`
- `backend-php/app/Views/strategy/_RouteHelp.php`

## Current Strategic Budget Preparation Sequence

The agreed high-level order is:

1. Confirm base setup and source segment values.
2. Map strategic source dimensions.
3. Import and clean core dimensions.
4. Build the planning framework.
5. Prepare delivery structure and initial costing.
6. Prepare and submit Funding Lodgements.
7. Review and approve funding submissions.
8. Set final resource envelopes and sector ceilings.
9. Check ceiling vs strategic plan.
10. Run readiness checks and review reports.
11. Finalize the framework through workflow and reporting.

Key principle:

- Funding Lodgements capture demand.
- Sector ceilings represent approved fiscal control.
- Indicative ceilings can be used early, but final ceilings should normally follow funding review and approval.

## Verification Completed

PHP syntax checks were run successfully for the main changed files, including:

- `backend-php/app/Controllers/StrategyController.php`
- `backend-php/app/Controllers/StrategySetupController.php`
- `backend-php/app/Models/StrategicBudgetingAdminModel.php`
- `backend-php/app/Models/StrategicBudgetingModel.php`
- `backend-php/app/Views/strategy/FrameworkGuide.php`
- `backend-php/app/Views/strategy/FundingSubmissionList.php`
- `backend-php/app/Views/strategy/FundingSubmissionReport.php`
- `backend-php/app/Views/strategy/ReportReadiness.php`
- `backend-php/app/Views/strategy/SegmentMapping.php`
- `backend-php/app/Views/strategy/_RouteHelp.php`
- `backend-php/config/menu.php`
- `backend-php/config/quick_links.php`
- `backend-php/config/routes.php`

## Items To Verify In Browser

- Open `strategy/framework-guide` and confirm:
  - it appears under Strategic Framework > Start & Review
  - quick links include Framework Guide
  - Print opens the browser print dialog

- Open Funding Lodgements and confirm:
  - quick links show Lodgements, Reviews, Approvals, Submission Summary, and All Submissions
  - UI matches the standard Strategy card/table style

- Open Funding Submission Summary and confirm:
  - metric cards render correctly
  - sector, program, and snapshot tables render without layout issues

- Open Strategic Segment Mapping and confirm:
  - mapped rows show segment value counts
  - mapped rows without values show `No segment values loaded`

- Run Configuration Readiness and confirm:
  - mapped strategic segments with no values are critical
  - warning/critical counts display correctly

## SQL / Database Follow-Up

- Run `alter_tblSbSegmentConfig_add_objective_target_activity.sql` on any target database where Project mapping fails because of the older constraint.
- Use `load_tblSegmentValues_from_cbmsgol_tblSegmentValues.sql` to load segment values from `CBMSGOL`.
- Start with preview mode before inserting.
- Confirm the target fiscal year exists in `dbo.tblFiscalYears`.
- Confirm source `SegmentNo` values exist in target `dbo.tblSegments`.

## Git / Workspace Notes

Current changed files include:

- Strategy controllers, models, route help, quick links, menu, routes, funding views, readiness view, and SQL scripts.
- New file:
  - `backend-php/app/Views/strategy/FrameworkGuide.php`
- New SQL script:
  - `backend-php/config/sql/load_tblSegmentValues_from_cbmsgol_tblSegmentValues.sql`

Untracked workspace item observed:

- `database/`

Before committing, review whether the `database/` folder should be included or ignored.

Suggested commit message:

- `Add strategic framework guide and funding lodgement refinements`

Suggested Git sequence:

- `git status`
- `git add backend-php HANDOFF_NOTES_2026-06-15.md`
- review whether to include `database/`
- `git commit -m "Add strategic framework guide and funding lodgement refinements"`
- `git push origin main`

## Resume Point

Next session should start by browser-testing the Strategic Framework Guide, Funding Lodgements, Funding Submission Summary, Strategic Segment Mapping, and Configuration Readiness screens under FY 2026 / Version 1.
