# Scenario Modelling Engine V1

This repo now includes a first-pass SQL Server schema for a redesigned scenario
modelling engine in:

- [backend-php/config/sql/create_scenario_modelling_engine_v1.sql](backend-php/config/sql/create_scenario_modelling_engine_v1.sql)

## Purpose

The new schema is designed for a C# in-memory batch calculation engine and is
intentionally separate from the current PHP + Redis transaction batch path.

The design shifts the calculation model from:

- transaction-chain execution
- row-by-row persistence
- Redis-backed intermediate state

to:

- model/scenario/node-based execution
- explicit dependency graphs
- in-memory evaluation
- bulk SQL persistence by run

## Main Tables

- `tblCalcModel`: model/version root
- `tblCalcScenario`: base and what-if scenarios with inheritance
- `tblCalcPeriod`: ordered periods, monthly in v1
- `tblCalcCostObject`: items being modelled
- `tblCalcNode`: drivers, formulas, outputs
- `tblCalcFormula`: tokenized arithmetic expressions
- `tblCalcDependency`: explicit graph edges between nodes
- `tblScenarioNodeValue`: scenario overrides / input values
- `tblCalcRun`: batch job header
- `tblCalcRunResult`: persisted calculated outputs
- `tblCalcRunError`: run-time calculation errors

## Formula Direction

The v1 schema keeps the useful part of the existing formula approach:

- token syntax like `@Token@`

But it drops the old execution assumptions:

- no `ChildCalculationID` chain execution
- no Redis requirement
- no SQL inside the calculation loop

Recommended v1 token style:

- `@WageRate@`
- `@Headcount@`
- `@UtilisationPct@`

with period resolved by execution context rather than embedding `BP1..BP12`
directly into the long-term model.

## Execution Direction

Expected C# engine flow:

1. Load model metadata, periods, scenarios, cost objects, nodes, formulas, and dependencies.
2. Load effective input values once.
3. Resolve scenario inheritance in memory.
4. Topologically sort dependencies.
5. Evaluate formula nodes in memory.
6. Bulk write results and errors back to SQL Server.

## Migration Direction

The existing `CBMSv21` calculation engine remains the business-rule baseline.

Migration should treat the current engine as:

- a parity-check reference
- a source for formulas and variable mappings

not as the target architecture for the new scenario engine.
