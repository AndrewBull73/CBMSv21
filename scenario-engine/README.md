# CBMS Scenario Engine

This folder contains the first .NET codebase for the redesigned scenario
modelling engine.

## Projects

- `CBMS.ScenarioEngine.Core`
- `CBMS.ScenarioEngine.Runner`
- `CBMS.ScenarioEngine.Core.Tests`
- `CBMS.ScenarioEngine.Runner.Tests`

## What works now

The runner currently supports:

- schema probing against `tblCalc*` / `tblScenarioNodeValue`
- listing models from `tblCalcModel`
- loading one model bundle and performing basic structural validation
- executing a seeded model in memory and printing calculated outputs
- executing a single `tblTransactionInput` row through the new engine
- executing batches of legacy transactions by `CalculationID`
- persisting run headers, result rows, and error rows to the new run tables
- persisting transaction-mode outputs back into `tblTransactionResult`,
  `tblTransactionResultFlat`, and `tblTransactionResultPeriod`
- materializing legacy child transactions when a child calculation stage has
  `GenerateTransaction = 1`
- blocking execution when model validation errors are present
- publishing a completed run into stable reporting tables

## Commands

From the repo root:

```powershell
dotnet build CBMS.ScenarioEngine.sln
dotnet test scenario-engine/tests/CBMS.ScenarioEngine.Core.Tests/CBMS.ScenarioEngine.Core.Tests.csproj
dotnet test scenario-engine/tests/CBMS.ScenarioEngine.Runner.Tests/CBMS.ScenarioEngine.Runner.Tests.csproj
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- probe
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- list-models
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- inspect-model SCENARIO_V1_DEMO
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-model SCENARIO_V1_DEMO BASE
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction 68068
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction 68068 SCENARIO_V1_DEMO BASE
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch 1
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch 1 100 --progress=100
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- benchmark-transaction-batch 1 --progress=10000
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-batch-bulk 1 --progress=10000
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- execute-transaction-chain-batch-bulk 2 --progress=5000
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- publish-run 12
dotnet run --project scenario-engine/src/CBMS.ScenarioEngine.Runner -- publish-latest SCENARIO_V1_DEMO BASE
```

## Configuration

The runner prefers:

1. `scenario-engine/src/CBMS.ScenarioEngine.Runner/appsettings.json`
2. environment variables prefixed with `CBMS_SCENARIO_ENGINE_`
3. fallback loading from `backend-php/.env`

That means it can usually reuse the existing local `CBMSv2` SQL Server
connection without extra setup.

## Demo Seed

An idempotent demo seed script is available at:

- [backend-php/config/sql/seed_scenario_modelling_engine_v1_demo.sql](../backend-php/config/sql/seed_scenario_modelling_engine_v1_demo.sql)

It creates:

- model `SCENARIO_V1_DEMO`
- scenario `BASE`
- scenario `WHATIF_HEADCOUNT_UP` inheriting from `BASE`
- one `GLOBAL` cost object
- 12 monthly periods
- 5 input nodes
- 11 formula nodes
- explicit dependencies
- base scenario input values
- child scenario overrides for `Headcount` only
- a month-varying `RevenueVolume` pattern for cross-period validation

Running `execute-model` after seeding will:

- validate formulas, dependencies, and same-period graph structure first
- calculate the formula nodes in memory
- create a new row in `tblCalcRun`
- insert calculated outputs into `tblCalcRunResult`
- insert any execution issues into `tblCalcRunError`

Publishing now uses:

- `tblCalcPublishEvent` for publish audit history
- `tblCalcPublishedResult` for the current promoted result set per model/scenario

Transaction-mode execution now also uses:

- `tblCalcTransactionBridge` to route a `tblTransactionInput` row into a model/scenario
- `tblCalcTransactionNodeMap` to map transaction columns into scenario input nodes
- the existing `tblTransactionInput.CalculationID` as the first routing hook

The current seeded demo bridge routes:

- legacy `CalculationID = 2002`
- `FiscalYearID = 2026`
- `TransactionTypeCode = 11`
- `UOMCodeInpC = APS1`

into:

- model `SCENARIO_V1_DEMO`
- scenario `BASE`
- cost object `GLOBAL`

with one seeded node mapping:

- `RevenueVolume <= BP{PeriodNo}InpN`

Running `publish-run` or `publish-latest` will:

- validate that the selected run is completed and contains results
- replace the currently published slice for that model/scenario in one transaction
- record a publish event with the source run and published row count

Published-result reporting now also has:

- SQL views:
  - `dbo.vwCalcPublishedResultCurrent`
  - `dbo.vwCalcPublishedScenarioSummary`
- PHP routes:
  - `index.php?route=scenario-results/index`
  - `index.php?route=scenario-results/api`
- a reporting SQL patch:
  - [backend-php/config/sql/patch_scenario_modelling_engine_v1_add_reporting_views.sql](../backend-php/config/sql/patch_scenario_modelling_engine_v1_add_reporting_views.sql)

## Supported Expression Features

The current controlled evaluator supports:

- arithmetic: `+`, `-`, `*`, `/`
- parentheses
- unary `+` and unary `-`
- comparisons: `=`, `==`, `!=`, `<>`, `<`, `<=`, `>`, `>=`
- boolean constants: `TRUE`, `FALSE`
- functions:
  - `IF(condition, whenTrue, whenFalse)` with lazy branch evaluation
  - `MIN(...)`
  - `MAX(...)`
  - `ABS(value)`
  - `ROUND(value, digits)`
  - `AND(...)`
  - `OR(...)`
  - `NOT(value)`

The demo model now exercises:

- `ROUND(@Revenue@ / @Headcount@, 2)`
- `IF(@Margin@ > 0, 1, 0)`
- `MAX(@Margin@, 0)`
- `@Revenue[-1]@`
- `@Revenue[+1]@`
- `@Revenue[-1]@ + @Revenue@ + @Revenue[+1]@`
- `@Revenue[2026-03]@`
- `IF(@Headcount@ > 0, 1, 1 / 0)`

The core test project currently locks in:

- `IF` short-circuiting for both true and false branches
- lazy variable resolution inside `IF`
- nested branch-skipping behavior

The runner test project currently locks in:

- relative period token resolution such as `[-1]` and `[+1]`
- absolute period token resolution such as `[2026-03]`
- shifted self-dependency execution without false cycle detection
- scenario inheritance precedence across child, parent, and base values
- failure behavior for missing-parent and cyclic scenario chains
- model validation for formula syntax, token/dependency alignment, and same-period cycles
- publish eligibility rules for completed vs incomplete runs

Cross-period token selectors currently supported are:

- current period: `@Revenue@`
- relative offset: `@Revenue[-1]@`, `@Revenue[+1]@`
- absolute period: `@Revenue[2026-03]@`

The runner now resolves effective values using scenario inheritance:

1. selected scenario override
2. parent scenario override
3. nearest ancestor override
4. base scenario value

For `execute-transaction`, the runner now does this:

1. load one `tblTransactionInput` row
2. resolve the best active bridge in `tblCalcTransactionBridge`
3. load the target model/scenario
4. apply transaction overrides from `tblCalcTransactionNodeMap`
5. run the same in-memory engine used for batch/scenario execution
6. persist the execution to `tblCalcRun`, `tblCalcRunResult`, and `tblCalcRunError`
7. project the output back into `tblTransactionResult`, `tblTransactionResultFlat`,
   and `tblTransactionResultPeriod`

The transaction runner now also understands legacy child-calculation chains:

- it can load the legacy `ChildCalculationID` chain from `tblCalculations`
- it executes stages in order from the starting `CalculationID`
- it keeps named prior-stage results in memory so a child bridge can map from
  earlier stages such as `WAGES|BP1`
- if a child stage has `GenerateTransaction = 1`, it creates or reuses the
  corresponding child `tblTransactionInput` row and writes that stage's legacy
  result rows against the child transaction
- if a child stage has no bridge yet, the runner reports the missing
  `CalculationID` explicitly instead of silently skipping it

For legacy transaction batches there are now three distinct modes:

- `execute-transaction-batch`
  - uses transaction-mode orchestration for each row
  - persists row by row
  - best for parity/diagnostics, not performance
- `benchmark-transaction-batch`
  - loads the targeted batch once
  - runs the calculation loop fully in memory
  - skips persistence so you can measure pure engine speed
- `execute-transaction-batch-bulk`
  - loads the targeted batch once
  - runs the calculation loop fully in memory
  - bulk replaces legacy rows in:
    - `tblTransactionResult`
    - `tblTransactionResultFlat`
    - `tblTransactionResultPeriod`
- `execute-transaction-chain-batch-bulk`
  - runs a legacy `ChildCalculationID` chain stage by stage in memory
  - bulk upserts generated child `tblTransactionInput` rows
  - bulk persists legacy result rows for each stage

## Next intended step

Broaden the first reporting slice into full scenario workflows:

- add richer scenario comparison and summary views in PHP
- expose publish history alongside current published results
- add named helper/business functions where needed in the expression layer
- replace the legacy transaction calculation path by calling `execute-transaction`
  semantics from PHP during `tblTransactionInput` save/preview workflows
