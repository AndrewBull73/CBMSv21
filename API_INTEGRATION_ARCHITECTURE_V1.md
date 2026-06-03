# CBMSv21 API Integration Architecture V1

## Why This Needs A Proper Layer

CBMSv21 already has:

- internal JSON endpoints such as `dataobjectcodes.api/*`
- screen-driven imports and exports across several modules
- upload/download workflows
- strong existing patterns for audit, session context, workflow, and test logging

What it does **not** yet have is a reusable integration framework for:

- scheduled inbound finance-system imports
- outbound budget exports to more than one external finance system
- multiple transport/authentication styles
- mapping and transformation by target system
- reliable run history, retries, and error handling

For this requirement, the right solution is not "just add a few API routes".
It should be a small **integration hub inside CBMSv21**.

## Recommended Direction

Build a generic integration framework with these layers:

1. `Integration Definitions`
   Store which interfaces exist, whether they are inbound or outbound, which external system they belong to, and how they should run.

2. `Connector Adapters`
   One adapter per external finance system.
   Each adapter knows how to:
   - authenticate
   - call the remote API
   - fetch data
   - send data
   - normalize external errors

3. `Payload Mapping / Transformation`
   CBMS should convert between:
   - CBMS domain structures
   - Finance System A payload format
   - Finance System B payload format

4. `Run Engine`
   A shared execution layer should:
   - start a run
   - load config
   - call the correct adapter
   - stage/validate payloads
   - import/export records
   - log results
   - mark success / partial / failed

5. `Monitoring + Admin`
   Provide UI for:
   - interface definitions
   - connection status
   - manual run trigger
   - run history
   - error details

## Core Design Principle

Do **not** make each external system its own custom controller flow.

Instead:

- keep a shared integration engine
- plug in system-specific adapters
- plug in interface-specific mapping rules

That is what will let CBMSv21 support two systems cleanly without duplicating logic.

## Target Use Cases

### Inbound

- daily actuals import from Finance System A
- daily actuals import from Finance System B
- reference/master data sync if required
- status or posting confirmation import

### Outbound

- export approved budget data to Finance System A
- export approved budget data to Finance System B
- export selected segments, funding lines, or submission packages

## Recommended Functional Components

### 1. Integration Registry

Create a master definition of each interface.

Examples:

- `FINA_ACTUALS_DAILY_IMPORT`
- `FINA_APPROVED_BUDGET_EXPORT`
- `FINB_ACTUALS_DAILY_IMPORT`
- `FINB_APPROVED_BUDGET_EXPORT`

Each definition should store:

- interface code
- name
- external system code
- direction: `inbound` / `outbound`
- source module or target module
- active flag
- trigger mode: `manual`, `scheduled`, or `both`
- schedule expression or frequency
- authentication mode
- endpoint URL
- timeout / batch size / paging settings
- fiscal-context requirements
- environment mode: dev / test / prod

### 2. External System Registry

Store reusable connection settings per external system.

Examples:

- `FINANCE_SYS_A`
- `FINANCE_SYS_B`

Suggested fields:

- system code
- display name
- base URL
- auth type
  - API key
  - bearer token
  - OAuth client credentials
  - basic auth
- credential references
- default headers
- active flag

Important:

- credentials should not be stored in plain text if avoidable
- if encrypted-at-rest is not ready yet, store sensitive secrets in environment variables and keep only references in DB

### 3. Integration Run Log

Every import/export run should create a durable log row.

Suggested run statuses:

- `queued`
- `running`
- `succeeded`
- `partial_success`
- `failed`
- `cancelled`

Suggested metrics:

- started at
- completed at
- interface code
- system code
- initiated by user or scheduler
- record counts
- created / updated / skipped / failed counts
- request identifier / correlation id
- summary message
- error text

### 4. Integration Message / Item Log

When helpful, store line-level or item-level results separately from the run header.

Examples:

- one log row per inbound actual line
- one log row per outbound budget item

This is important when:

- one run processes hundreds or thousands of rows
- partial success is possible
- the business needs to reprocess failed items only

### 5. Staging Tables

For finance imports, do not write raw external API data directly into business tables first.

Recommended pattern:

1. fetch payload
2. store raw/staged rows
3. validate/match/map
4. promote into business tables

Benefits:

- replayability
- auditability
- better debugging
- partial validation before commit

## Recommended Technical Structure In CBMSv21

### Controllers

Add a dedicated integration controller family, not mixed into business controllers.

Suggested future controllers:

- `IntegrationAdminController`
- `IntegrationRunsController`
- `IntegrationApiController`

### Services / Shared Layer

Introduce new service classes for orchestration.

Suggested structure:

- `app/Services/Integrations/IntegrationManager.php`
- `app/Services/Integrations/Adapters/FinanceSystemAAdapter.php`
- `app/Services/Integrations/Adapters/FinanceSystemBAdapter.php`
- `app/Services/Integrations/Mappers/ActualsImportMapper.php`
- `app/Services/Integrations/Mappers/BudgetExportMapper.php`
- `app/Services/Integrations/Transport/HttpClient.php`

### Models

Suggested models:

- `IntegrationSystemModel`
- `IntegrationInterfaceModel`
- `IntegrationRunModel`
- `IntegrationRunItemModel`
- `IntegrationMappingModel`

## Authentication / Security Recommendations

For inbound and outbound integrations:

- authenticate to external systems with connector-specific auth
- keep connector secrets out of normal UI forms where possible
- log requests and responses carefully without leaking secrets
- add permission gates for manual trigger and admin maintenance

Suggested CBMS permissions:

- `INTEGRATION_ADMIN`
- `INTEGRATION_RUN`
- `INTEGRATION_VIEW`
- `INTEGRATION_EXPORT`
- `INTEGRATION_IMPORT`

## Scheduling Recommendations

Daily finance actuals loads should run through a scheduled job.

Recommended approach:

- CBMS stores interface definitions and status
- Windows Task Scheduler or server cron hits a CLI entrypoint or secured route
- the run engine executes the configured interfaces

Avoid relying only on browser-triggered imports for operational finance syncing.

## Error Handling Recommendations

Standardize error categories:

- connection/auth failure
- remote API validation failure
- transformation failure
- local validation failure
- database save failure
- partial item failure

Every run should capture:

- short summary
- technical detail
- raw response snippet when safe
- retry recommendation

## Idempotency And Replay

This is especially important for daily actuals imports.

Recommended safeguards:

- store external source keys
- store source system + source record id
- detect duplicate payloads before re-import
- support rerun of a failed batch without double-posting

For exports:

- track whether a budget package has already been exported
- store external acknowledgement/reference where available

## Mapping Strategy

Do not hardcode every field mapping inside controllers.

Use explicit mapping logic per interface.

Two likely categories:

1. `Static mappings`
   Example:
   - external field `fund_code` -> CBMS `FundingSourceCode`

2. `Context-aware mappings`
   Example:
   - map to active `FiscalYearID`
   - map by `VersionID`
   - map by DataScope
   - map external org codes to CBMS DataObject codes

## Minimum Viable First Release

The best first release is:

1. integration registry
2. external system registry
3. run log
4. one inbound actuals import interface
5. one outbound approved budget export interface
6. one adapter for System A
7. one adapter for System B
8. manual trigger screen
9. run history screen

That gives a real platform without overbuilding too early.

## Recommended Build Order

### Phase 1. Foundation

- add integration tables
- add models
- add permissions
- add admin UI for systems + interfaces
- add run logging

### Phase 2. Execution Engine

- add shared HTTP client wrapper
- add adapter interface
- add connector implementations for System A and System B
- add manual trigger flow

### Phase 3. First Real Interfaces

- daily actuals import
- approved budget export

### Phase 4. Scheduling + Monitoring

- scheduler integration
- retry / rerun support
- richer run diagnostics

## Important Scoping Advice

Do not try to build "all APIs" at once.

Pick:

1. the first inbound interface
2. the first outbound interface
3. the first target system

Then build the framework from those real use cases.

## Recommended Next Step

Start with a formal integration foundation for:

- `Finance System A Actuals Daily Import`
- `Finance System A Approved Budget Export`
- architecture designed so `Finance System B` plugs into the same engine

Once that foundation works, add the second system adapter and second set of mappings.
