# CBMSv21 Screen UI Standard

## Canonical Reference

The canonical screen style for new CBMSv21 module pages is:

- Route: `strategy-config/configuration-readiness`
- View: `backend-php/app/Views/strategy/ReportReadiness.php`
- Mode: `readinessType = 'configuration'`

This screen should be treated as the primary reference when building:

- module landing pages
- setup dashboards
- readiness screens
- inquiry screens
- review/monitoring screens

Do not use the Budget Execution screens themselves as the style reference.

## Goal

The purpose of this standard is to stop each new screen from introducing a slightly different:

- page header
- quick link layout
- helper instruction block
- metric card style
- card header style
- table spacing

The objective is predictable visual consistency across modules.

## Standard Page Shell

Use this structure in this order:

1. `container mt-4`
2. one main `card shadow-sm`
3. top `card-header d-flex justify-content-between align-items-center gap-2 flex-wrap`
4. page title in `h3.mb-0`
5. page navigation comes from the shared Quick Links bar when used
6. `card-body`
7. current context line
8. metric summary cards
9. optional helper / instructional alert
10. one or more content section cards

This is the target pattern used by `strategy-config/configuration-readiness`.

## Header Standard

The top header must follow this pattern:

- title inside the main card header, not floating outside the card
- `h3.mb-0`
- optional icon before title

Use the shared partial:

- `backend-php/app/Views/shared/_ScreenCardHeader.php`

The shared card header is for the page title area only. It should not duplicate quick-link navigation.

Quick Links definitions should come from:

- `backend-php/config/quick_links.php`

The shared Quick Links renderer currently lives at:

- `backend-php/app/Views/strategy/_QuickNav.php`

Quick links must use:

- inactive link: `btn btn-sm btn-outline-secondary`
- active/current page link: `btn btn-sm btn-primary`

Do not mix:

- `btn-outline-primary`
- standalone top-page button rows outside the header
- different quick-link styles on the same screen

Quick links are optional.

Use them when:

- the screen is part of a small workflow cluster
- users need to move frequently between related screens
- the module has a clear “hub and spoke” navigation pattern

Do not force quick links onto every screen.

When quick links are used, they should be rendered through the shared Quick Links bar, not duplicated again inside the card header.

## Context Standard

The context line belongs inside the top card body and should use:

- `small text-muted mb-3`

Format:

`Current context: <Fiscal Year> / <Version>`

Where labels are available, use labels rather than raw IDs.

## Metric Card Standard

Metric cards should appear immediately below the context line.

Use:

- `row g-3 mb-4`
- each metric card as `card shadow-sm h-100`
- metric label as `text-muted small`
- metric value as `fs-4 fw-semibold`

Do not introduce custom metric sizing unless there is a strong reason.

## Helper Instruction Standard

If helper instructions are needed, use one main helper/instruction block after the metric cards:

- `alert alert-info border-0 shadow-sm mb-4`

This block should explain:

- what the screen is for
- what the user should verify
- what the next step is

If extra guidance is needed, prefer adding:

- short explanatory text inside relevant section cards

Do not stack multiple differently-styled helper panels unless truly necessary.

Helper instructions are optional.

Use them when the screen is:

- setup-heavy
- workflow-heavy
- new to users
- operationally sensitive
- likely to be confusing without guidance

Usually avoid them on:

- simple lists
- straightforward inquiry screens
- frequently used transaction-entry screens where users already know the flow

## Section Card Standard

Each major section should use:

- `card shadow-sm mb-4`

Section header:

- `card-header`
- `h5.mb-0`

Section body:

- `card-body`

Do not use:

- custom local card-header colors
- ad hoc header padding overrides
- mixed white/gradient header logic within the same module

If the global layout supplies shared card styles, use them rather than redefining them in the view.

## Table Standard

For inquiry/admin tables use:

- `table table-sm table-hover align-middle mb-0`

Use `table-admin` only where the module already relies on that shared class and it is visually consistent with the reference screen.

Preferred column behavior:

- text columns left-aligned
- counts and amounts right-aligned
- action column right-aligned

## Button Standard

Primary actions:

- `btn btn-sm btn-primary`

Secondary navigation:

- `btn btn-sm btn-outline-secondary`

Section actions inside tables/cards:

- `btn btn-outline-primary btn-sm`

Shared standardized screens should render buttons at the compact action size by default through the shared layout shell.

Do not add local button font-size overrides in individual views unless there is a genuine exceptional case.

Avoid mixing button color logic across otherwise similar screens.

## Copy And Labels

Prefer labels that match the Strategy Configuration Readiness tone:

- short
- operational
- plain English

Examples:

- `Current context`
- `Needs attention`
- `Open items`
- `Action`
- `Detail`

Avoid over-specific labels unless the business need requires them.

## Implementation Rules For New Screens

When creating a new screen:

1. Start from the structure of `backend-php/app/Views/strategy/ReportReadiness.php`.
2. Reuse existing shared classes from `backend-php/app/Views/layouts/main.php`.
3. Use `backend-php/app/Views/shared/_ScreenCardHeader.php` for the title area.
4. Use the shared Quick Links pattern when the screen needs related-screen navigation.
5. Do not introduce screen-specific CSS unless there is no shared option.
6. If new CSS is genuinely reusable, move it into the shared layout styles.

## Recommended Next Technical Step

To enforce this standard in code, create shared partials for:

- screen header
- quick links
- context line
- metric cards
- helper alert
- section card header

Suggested future files:

- `backend-php/app/Views/shared/_ScreenContext.php`
- `backend-php/app/Views/shared/_ScreenMetrics.php`
- `backend-php/app/Views/shared/_ScreenHelper.php`

This is the recommended way to stop design drift across new module screens.
