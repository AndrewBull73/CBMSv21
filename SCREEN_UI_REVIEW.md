# CBMSv21 Screen UI Review

## Scope Completed

This review focused on standardizing **module navigation patterns** across existing screens.

The following areas were addressed:

- shared Quick Links source of truth
- shared Quick Links rendering
- removal of duplicated per-module Quick Links partials
- removal of duplicated header navigation on screens already covered by shared Quick Links
- update of the UI standard documentation

## Centralized Quick Links

Quick Links are now centrally defined in:

- `backend-php/config/quick_links.php`

They are rendered through:

- `backend-php/app/Views/strategy/_QuickNav.php`

They are injected from the shared layout:

- `backend-php/app/Views/layouts/main.php`

## Modules Covered By Shared Quick Links

The centralized Quick Links registry now includes groups for:

- Strategy
- Budget Execution
- Financial Configuration
- Integration Admin
- Reports
- Screen Tests
- Base Configuration
- Training Configuration
- Access & Security

## Local Quick Links Removed

The old local Quick Links partials were removed from:

- Integrations
- Reports
- Report Admin
- Screen Tests

The obsolete files were deleted so they are not reused accidentally.

## Important Distinction

This review standardizes **navigation between related screens**.

It does **not** remove legitimate screen actions such as:

- Create
- Save
- Back
- Download
- Reload
- Run
- Test

Those buttons may still appear in page or card headers because they are actions, not sibling-screen navigation.

## Remaining Design Rule

Going forward:

- use Quick Links for module or workflow navigation
- use header buttons for screen actions only
- do not duplicate the same navigation in both places

