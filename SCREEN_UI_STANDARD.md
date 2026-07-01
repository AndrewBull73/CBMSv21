# CBMSv21 Screen UI Standard

This file is the agreed reference for CBMSv21 screen layout consistency. Use it before changing or creating any admin, setup, readiness, inquiry, diagnostic, or operational screen.

## Canonical References

Use these screens as the visual and structural references:

- Primary reference: `base-config/readiness`
- Primary view: `backend-php/app/Views/config/BaseConfigurationReadiness.php`
- Operational reference: `emailqueue/index`
- Operational view: `backend-php/app/Views/diagnostics/EmailQueueView.php`
- Diagnostic reference: `application-log/index`
- Diagnostic view: `backend-php/app/Views/diagnostics/ApplicationLogView.php`
- Readiness report reference: `strategy-config/configuration-readiness`
- Readiness report view: `backend-php/app/Views/strategy/ReportReadiness.php`

Do not use Budget Execution transaction screens as the general style reference. They can have workflow-specific needs that do not apply to setup, diagnostic, or admin screens.

## Goal

Every standard CBMSv21 screen should feel like it belongs to the same application. Avoid one-off differences in:

- page header size and placement
- card header labels
- quick links
- helper instructions
- metric cards
- filter/control layout
- table header styling
- button sizing and colour

## Standard Page Anatomy

Use this order unless the screen has a strong workflow reason not to:

1. `<div class="container mt-4">`
2. One main `<div class="card shadow-sm">`
3. Shared screen card header
4. `<div class="card-body">`
5. Current context line
6. Metric summary cards, when useful
7. Helper or runbook alert, when useful
8. One or more section cards
9. Tables, forms, or detailed content inside section cards

The shared UI CSS scope is applied globally by:

- `backend-php/app/Views/layouts/main.php`
- `<body class="bg-light strategy-ui">`

Do not make `strategy-ui` route-specific. It is the common CBMSv21 screen styling baseline and should be present throughout the application.

Standard skeleton:

```php
$screenHeader = [
    'title' => 'Screen Title',
    'icon' => 'bi-example',
];
?>

<div class="container mt-4">
  <div class="card shadow-sm">
    <?php require __DIR__ . '/../shared/_ScreenCardHeader.php'; ?>
    <div class="card-body">
      <div class="small text-muted mb-3">
        Current context:
        <strong><?= h($contextSummary) ?></strong>
      </div>

      <!-- metric cards -->
      <!-- helper/runbook alert -->
      <!-- section cards -->
    </div>
  </div>
</div>
```

## Header Standard

Always use:

- `backend-php/app/Views/shared/_ScreenCardHeader.php`
- title in the shared card header
- `h3.mb-0` from the shared partial
- optional Bootstrap icon through `$screenHeader['icon']`

Do not put:

- a custom `h3.mb-1` header in the view
- descriptive subtitle text inside the main card header
- one-off action button rows inside the main header unless the shared partial supports them

The main header is for the screen title only. Put operational text in the helper/runbook alert or section cards.

## Current Context Standard

The current context line belongs immediately inside the main `card-body`.

Use:

```html
<div class="small text-muted mb-3">
  Current context:
  <strong>...</strong>
</div>
```

Examples:

- `Current context: FY 2026 / Original Budget`
- `Current context: Email delivery queue`
- `Current context: app-2026-06-27.log`

Use labels rather than raw IDs where labels are available.

## Metric Card Standard

Metric cards appear immediately below the context line.

Use:

- wrapper: `row g-3 mb-4`
- column: `col-6 col-xl-3`
- card: `card shadow-sm h-100`
- body: `card-body`
- label: `text-muted small`
- value: `fs-4 fw-semibold`

Example:

```html
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="text-muted small">Displayed</div>
        <div class="fs-4 fw-semibold">123</div>
      </div>
    </div>
  </div>
</div>
```

Do not use bordered metric blocks such as `border rounded p-3 h-100` for standard screens.

## Helper And Runbook Standard

Use one helper/runbook alert after the metrics when the screen needs guidance.

Use:

- `alert alert-info border-0 shadow-sm mb-4`
- title line: `fw-semibold mb-1`
- short operational text
- optional muted supporting line

Example:

```html
<div id="screen-runbook" class="alert alert-info border-0 shadow-sm mb-4">
  <div class="fw-semibold mb-1">Screen Runbook</div>
  <div class="mb-2">Explain what the screen is for.</div>
  <div class="small text-muted mb-2">Explain what the user should check first.</div>
  <div class="small">Explain the next safe action.</div>
</div>
```

Use helper/runbook alerts for:

- setup-heavy screens
- workflow-heavy screens
- operationally sensitive screens
- diagnostic screens
- screens that are new or easy to misunderstand

Avoid helper/runbook alerts on very simple lists unless the list has sensitive actions.

## Shared Helper Instructions

The shared route helper is:

- `backend-php/app/Views/strategy/_RouteHelp.php`

If a route uses the shared helper area, it must have route-specific wording when generic text would be misleading.

Rules:

- Do not let non-Strategy screens display Strategy-specific help.
- Add route-specific entries for operational screens such as Email Queue, Application Log, Diagnostics, and Health.
- The generic fallback should be neutral, such as `Screen Help`, not module-specific.

## Quick Links Standard

Quick Links definitions belong in:

- `backend-php/config/quick_links.php`

The shared Quick Links renderer is:

- `backend-php/app/Views/strategy/_QuickNav.php`

Quick Links are optional. Use them when a screen is part of a small workflow group where users move frequently between related screens.

Quick links must use the shared renderer. Do not duplicate quick links inside local card headers.

Standard button styles:

- inactive link: `btn btn-sm btn-outline-secondary`
- active/current page link: `btn btn-sm btn-primary`

Do not mix `btn-outline-primary` quick links into the shared quick-link bar.

## Section Card Standard

Each major area inside the main screen body should be a section card.

Use:

```html
<div class="card shadow-sm mb-4">
  <div class="card-header">
    <h5 class="mb-0">Section Title</h5>
  </div>
  <div class="card-body">
    ...
  </div>
</div>
```

Section card rules:

- header label uses `h5.mb-0`
- no oversized section headings
- no custom card-header colours
- no local card-header padding overrides
- no gradient or decorative section headers

## Filter And Control Card Standard

Filters and action controls should live in a dedicated section card when there is more than one control.

Use section titles such as:

- `Queue Controls`
- `Log Controls`
- `Readiness Filters`
- `Search Criteria`

Form layout:

- `row g-2 align-items-end`
- labels use `form-label`
- selects use `form-select`
- inputs use `form-control`
- action buttons are compact

Primary filter button:

- `btn btn-primary btn-sm`

Reset or refresh:

- `btn btn-outline-secondary btn-sm`

## Tabbed Content Standard

Use tabs when a screen has several dense content groups that users do not need to compare all at once, such as overview, issues, linked work, schedule, and task detail.

Tabbed content rules:

- keep the main page header, current context, and key summary metrics outside the tabs
- use `nav nav-tabs flex-nowrap overflow-auto mb-3` for the tab list when labels may wrap on smaller screens
- use Bootstrap `data-bs-toggle="tab"` with matching `aria-controls`, `aria-labelledby`, and `role` attributes
- make the first tab the default overview tab unless a workflow has a stronger primary task
- use compact badges inside tab labels only for actionable counts, such as open issues or open tasks
- do not put tabs inside cards nested within other cards

Example:

```php
<ul class="nav nav-tabs flex-nowrap overflow-auto mb-3" id="ExampleTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="ExampleOverviewTabButton" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#ExampleOverviewTab" aria-controls="ExampleOverviewTab" aria-selected="true">
      <?= h(__t('workflow_project_tab_overview')) ?>
    </button>
  </li>
</ul>
<div class="tab-content" id="ExampleTabContent">
  <div class="tab-pane fade show active" id="ExampleOverviewTab" role="tabpanel" aria-labelledby="ExampleOverviewTabButton" tabindex="0">
    ...
  </div>
</div>
```

## Read-Only Field Standard

Use the shared read-only convention for any field that appears in a form but cannot be edited, including generated codes, derived values, locked workflow fields, and permission-limited values.

Read-only fields must:

- keep the native `readonly` attribute when the value should remain selectable/copyable
- use `cbms-readonly-control` on the control
- show the localized `read_only` badge in the label using `cbms-readonly-badge`
- avoid one-off grey styles in individual screens

Example:

```php
<div class="col-12 col-lg-4 cbms-readonly-field">
  <label class="form-label" for="IssueCode">
    <?= h(__t('workflow_issue_code')) ?>
    <span class="cbms-readonly-badge"><?= h(__t('read_only')) ?></span>
  </label>
  <input type="text" class="form-control cbms-readonly-control" id="IssueCode" value="<?= h($issueCode) ?>" readonly aria-readonly="true">
</div>
```

Use disabled controls only when the control is unavailable and should not be submitted or copied. Use read-only controls when users may need to inspect or copy the value but must not edit it.

## Table Standard

Standard inquiry/admin tables use:

- `table table-sm table-hover align-middle mb-0`
- `<thead class="table-light">`
- text columns left-aligned
- counts, amounts, and action columns right-aligned
- action header: `class="text-end"`
- action cell: `class="text-end text-nowrap"`

Example:

```html
<div class="table-responsive">
  <table class="table table-sm table-hover align-middle mb-0">
    <thead class="table-light">
      <tr>
        <th>Status</th>
        <th>Message</th>
        <th class="text-end">Action</th>
      </tr>
    </thead>
    <tbody>
      ...
    </tbody>
  </table>
</div>
```

Use expandable detail rows when a table row needs more detail. Do not make an accordion the primary list for standard admin or diagnostic screens.

## Button Standard

Use compact buttons by default.

Primary actions:

- `btn btn-sm btn-primary`

Secondary navigation:

- `btn btn-sm btn-outline-secondary`

Section/table actions:

- `btn btn-outline-primary btn-sm`

Destructive actions:

- `btn btn-sm btn-danger`
- or `btn btn-sm btn-outline-danger` when the action is secondary and confirmation is present

Rules:

- Do not add local button font-size overrides.
- Do not mix button colour logic across similar controls.
- Use Bootstrap icons where helpful, especially for refresh, send, reset, open, remove, restore, and details actions.

## Status Badge Standard

Use Bootstrap text background badges:

- success: completed, sent, ready, healthy
- warning: warning, processing, needs review
- danger: failed, error, critical
- secondary: pending, neutral, unknown
- dark: cancelled or intentionally removed where appropriate
- light: empty or no context

Use consistent casing for badge labels. Prefer title case for user-facing labels unless the domain convention is uppercase, such as `ERROR`.

## Empty State Standard

Use a simple muted empty state inside the section card body:

```html
<div class="text-center text-muted py-3">No rows match the current filters.</div>
```

Avoid large custom empty-state illustrations or differently styled alert blocks unless the absence of data is an actual warning.

## Copy And Labels

Use short, operational labels:

- `Current context`
- `Health Score`
- `Open Items`
- `Queue Rows`
- `Log Controls`
- `Log Entries`
- `Action`
- `Detail`

Avoid long labels in table headers and card headers. Put explanatory text in helper/runbook alerts or small muted supporting text inside the section body.

## Do Not Use On Standard Screens

Avoid these patterns on standard CBMSv21 screens:

- custom top headers instead of `_ScreenCardHeader.php`
- large card header subtitles
- custom card-header colours
- bordered metric blocks instead of metric cards
- accordions as the main list layout
- inconsistent table header styles
- `btn-outline-primary` for shared quick links
- screen-specific CSS for spacing, font size, or header size
- multiple competing helper panels with different visual styles

## Implementation Rules For New Screens

When creating or correcting a screen:

1. Start from `backend-php/app/Views/config/BaseConfigurationReadiness.php` for layout.
2. Use `backend-php/app/Views/shared/_ScreenCardHeader.php` for the page title.
3. Add a current context line inside the main `card-body`.
4. Add metric cards only when they help users scan the screen.
5. Add one helper/runbook alert when the workflow needs guidance.
6. Put filters and controls in a section card.
7. Put result lists in a section card.
8. Use the standard table classes and `thead.table-light`.
9. Add route-specific shared helper text if the route uses `_RouteHelp.php`.
10. Add or update Quick Links only in `backend-php/config/quick_links.php`.
11. Do not introduce local CSS unless no shared option exists.
12. Run `php -l` on changed PHP view/controller/config files.

## Review Checklist

Before finishing a UI change, verify:

- The main screen starts with `container mt-4`.
- There is one main `card shadow-sm`.
- The page title uses `_ScreenCardHeader.php`.
- The header title size matches other screens.
- The page is rendered through the shared layout and receives the global `strategy-ui` body class.
- The current context line is present where relevant.
- Metric cards match the standard card structure.
- Helper/runbook text is route-specific and not from the wrong module.
- Section card headers use `h5.mb-0`.
- Table headers use `thead.table-light`.
- Buttons are compact and use standard colours.
- Quick Links come from the shared renderer.
- No local one-off CSS was added for common layout concerns.

## Future Shared Partials

To reduce drift further, prefer creating shared partials for repeated standard pieces:

- `backend-php/app/Views/shared/_ScreenContext.php`
- `backend-php/app/Views/shared/_ScreenMetrics.php`
- `backend-php/app/Views/shared/_ScreenHelper.php`
- `backend-php/app/Views/shared/_SectionCard.php`

Until those exist, follow the exact structure in the reference screens above.
