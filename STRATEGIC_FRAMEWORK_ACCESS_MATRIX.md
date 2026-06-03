# Strategic Framework Access Matrix

This document defines the proposed role model, permission bundles, quick-navigation code standards, and navigation scope for the `Strategic Framework` functional area.

It is intended to be the first detailed module-level access design under the broader [ROLE_PERMISSION_MODEL_V2.md](C:/xampp82/htdocs/CBMSv21/ROLE_PERMISSION_MODEL_V2.md).

## Purpose

The `Strategic Framework` area covers:

- strategic structure setup
- planning framework definition
- delivery and costing records
- governance narratives and risks
- fiscal framework working screens
- strategic readiness and strategy-focused reporting

It does not own:

- budget transaction input and rates maintenance
- general cross-system reporting
- platform administration
- system and financial configuration

## Strategic Framework roles

### `Strategic Framework User`

Business purpose:

- maintain day-to-day strategic planning records
- prepare the framework and related working data

Screens/functions:

- Strategy overview
- Structure Setup
- Planning Framework
- Delivery & Costing
- Governance
- Fiscal Framework working screens

Recommended access:

- edit operational module data
- view strategy reports needed to support entry and planning
- no approval or publication authority
- no advanced module configuration authority

### `Strategic Framework Reviewer`

Business purpose:

- review strategic outputs, readiness, and submitted items

Screens/functions:

- readiness and summary views
- funding review screens relevant to the strategy process
- read-only reporting needed for assessment

Recommended access:

- review, assess, and move items through review stages where appropriate
- no publication authority
- no core configuration authority

### `Strategic Framework Approver`

Business purpose:

- approve and publish strategic outputs

Screens/functions:

- all reviewer areas
- approval screens
- publication actions

Recommended access:

- approve submissions
- publish approved outputs
- no broad configuration authority unless separately assigned

### `Strategic Framework Reporting User`

Business purpose:

- consume strategic reports and readiness views without editing

Screens/functions:

- summary and readiness
- budget reports
- MTFF
- performance reports

Recommended access:

- read-only report and summary access
- no maintenance or workflow rights

### `Strategic Framework Administrator`

Business purpose:

- manage module-level configuration, imports, publication setup, and diagnostics-like setup functions inside the Strategic Framework area

Screens/functions:

- segment mapping
- import dimensions
- fiscal period labels
- fiscal assumptions
- phasing profiles
- custom attributes
- source resolution checks
- segment publication

Recommended access:

- full module configuration and setup authority
- no need for broad platform administration unless separately assigned

## Proposed Strategic Framework permission codes

These are the target business-facing permission bundles for the module.

- `SF_VIEW`
- `SF_SETUP_EDIT`
- `SF_FRAMEWORK_EDIT`
- `SF_DELIVERY_EDIT`
- `SF_GOVERNANCE_EDIT`
- `SF_FISCAL_EDIT`
- `SF_REPORT_VIEW`
- `SF_REVIEW`
- `SF_APPROVE`
- `SF_PUBLISH`
- `SF_ADMIN`

## Mapping to current live permissions

To avoid breaking working code, the current implementation can map these target bundles onto the existing permission set.

| Target bundle | Current permission mapping |
| --- | --- |
| `SF_VIEW` | `STRATEGY_VIEW` |
| `SF_SETUP_EDIT` | `STRATEGY_SETUP_EDIT` |
| `SF_FRAMEWORK_EDIT` | `STRATEGY_PERFORMANCE_EDIT` |
| `SF_DELIVERY_EDIT` | `STRATEGY_DELIVERY_EDIT` |
| `SF_GOVERNANCE_EDIT` | `STRATEGY_GOVERNANCE_EDIT` |
| `SF_FISCAL_EDIT` | `STRATEGY_FISCAL_EDIT` |
| `SF_REPORT_VIEW` | `STRATEGY_REPORT_VIEW` |
| `SF_REVIEW` | `STRATEGY_SUBMISSION_REVIEW` |
| `SF_APPROVE` | `STRATEGY_SUBMISSION_APPROVE` |
| `SF_PUBLISH` | `STRATEGY_PUBLISH` |
| `SF_ADMIN` | `STRATEGY_CONFIG_EDIT` plus publication/config helpers |

## Recommended role-to-permission matrix

| Role | Recommended permission bundle |
| --- | --- |
| `Strategic Framework User` | `SF_VIEW`, `SF_SETUP_EDIT`, `SF_FRAMEWORK_EDIT`, `SF_DELIVERY_EDIT`, `SF_GOVERNANCE_EDIT`, `SF_FISCAL_EDIT`, `SF_REPORT_VIEW` |
| `Strategic Framework Reviewer` | `SF_VIEW`, `SF_REPORT_VIEW`, `SF_REVIEW` |
| `Strategic Framework Approver` | `SF_VIEW`, `SF_REPORT_VIEW`, `SF_REVIEW`, `SF_APPROVE`, `SF_PUBLISH` |
| `Strategic Framework Reporting User` | `SF_VIEW`, `SF_REPORT_VIEW` |
| `Strategic Framework Administrator` | `SF_VIEW`, `SF_ADMIN`, `SF_REPORT_VIEW`, `SF_PUBLISH` |

## Menu and route scope

The current route families that belong to `Strategic Framework` are:

- `strategy/*`
- `strategy-config/*`
- `strategy-setup/*`
- `strategy-performance/*`
- `strategy-delivery/*`
- `strategy-governance/*`
- `strategy-fiscal/*`
- `strategy-reports/*`
- `strategy-publish/*`

The current route family that is related but should now be treated as a separate top-level business area is:

- `strategy-submissions/*`

That route family belongs under `Budget Submission`, not `Strategic Framework`, even though some legacy permission names still begin with `STRATEGY_`.

## Quick navigation code standards

The visible UI labels should stay business-friendly.

The visible quick codes should use a short mnemonic `SF` prefix.

Recommended pattern:

- `SFSR` = Strategic Framework Start & Review
- `SFCF` = Strategic Framework Configuration
- `SFST` = Strategic Framework Structure Setup
- `SFPF` = Strategic Framework Planning Framework
- `SFDC` = Strategic Framework Delivery & Costing
- `SFGV` = Strategic Framework Governance
- `SFRP` = Strategic Framework Reports
- `SFFF` = Strategic Framework Fiscal Framework

Design rule:

- user-facing label stays readable, such as `Start & Review`
- the quick code is short enough for users to remember and quote
- the code appears consistently in quick navigation and reference material

Examples for main screens:

- `SFCR` = Strategic Framework Configuration Readiness
- `SFID` = Strategic Framework Import Dimensions
- `SFSE` = Strategic Framework Sectors
- `SFPJ` = Strategic Framework Projects
- `SFOT` = Strategic Framework Outputs
- `SFAB` = Strategic Framework Activity Budgets
- `SFSB` = Strategic Framework Sector Budget Report
- `SFJR` = Strategic Framework Project Budget Report
- `SFMT` = Strategic Framework MTFF

## Quick navigation ownership

The following quick-nav groups belong to `Strategic Framework`:

- `Start & Review`
- `Configuration`
- `Structure Setup`
- `Planning Framework`
- `Delivery & Costing`
- `Governance`
- `Reports`
- `Fiscal Framework`

The existing `Funding Workflow` quick-nav group should move into the future `Budget Submission` quick-nav model.

## Implementation guidance

Phase 1:

- keep current live permissions in code
- use this matrix as the reference model
- introduce `SF_` internal codes in quick-nav and access documentation

Phase 2:

- create the new Strategic Framework roles in SQL
- map them to the current permission set
- move funding-submission concerns into the `Budget Submission` role model

Phase 3:

- optionally rename the technical permission codes from `STRATEGY_*` to `SF_*` through a controlled migration
- only do that once controller ACLs, menu configuration, and reporting references are fully aligned
