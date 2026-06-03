# Language Framework Standard

## Purpose
This standard defines how multilingual support must be implemented in CBMSv21 so the platform stays consistent, testable, and maintainable.

## Core Rules
1. The active language must be resolved through `App\Shared\Lang`.
2. Only language packs that physically exist in `backend-php/lang/*.php` may be exposed to users.
3. All routed layouts and standalone iframe screens must set `<html lang="<?= \App\Shared\Lang::getActiveLang() ?>">`.
4. Views must use `__t('key')` for user-facing static text.
5. Controllers should pass translated flash keys, not hardcoded English sentences, whenever practical.
6. Shared screen headers should use `titleKey` where possible so browser titles and screen titles stay aligned.
7. New languages are not considered enabled until:
   - a `backend-php/lang/<code>.php` file exists
   - the switcher exposes the language automatically through `Lang::availableLanguageLabels()`
   - the language pack has been checked against `en.php` for missing keys

## View Standard
Use `__t()` for:
- headings
- button text
- table headers
- filter labels
- empty-state messages
- alerts and instructional copy
- placeholders and help text when those strings are static

Avoid:
- hardcoded English labels in templates
- hardcoded language menus in views
- hardcoded `<html lang="en">`

## Controller Standard
Use translation-aware flash keys such as:
- `workflow_engine_definition_saved`
- `workflow_engine_definition_save_failed_detail`

Where a message includes a runtime detail, prefer replacements:
- `__t('save_failed_detail', ['msg' => $message])`

## Language Pack Standard
1. `en.php` is the baseline pack.
2. Every new key added to `en.php` should be added to every supported language pack in the same change.
3. Use stable snake_case keys for reusable UI text.
4. Screen or module-specific keys should use a clear prefix, for example `workflow_engine_*`.

## Audit
Run this audit periodically during rollout:

```powershell
php backend-php/tools/language_framework_audit.php
```

This audit reports:
- installed language packs
- missing keys versus English
- views/controllers without `__t()`
- remaining hardcoded `<html lang="en">` cases

## Rollout Priority
1. Shared layout and navigation
2. Standalone iframe and popup screens
3. Shared admin/platform tooling
4. Execution screens
5. Strategy, reporting, and integration screens
