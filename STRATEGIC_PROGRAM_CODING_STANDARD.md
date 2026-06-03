# Strategic Program Coding Standard

This project assumes an MTFF-friendly Sector, Program, and SubProgram structure, with optional client-specific planning layers above budgeting and below strategy.

## Core Rules

1. `Sector`, `Program`, and `SubProgram` should all be mapping-driven strategic dimensions.
2. Segment numbers must come from `tblSbSegmentConfig` and must never be hardcoded per client.
3. `Program` rows in `tblSegmentValues` should point to their parent `Sector`.
4. `SubProgram` rows in `tblSegmentValues` should point to their parent `Program`.
5. `ProgramCode` should be globally unique.
6. One `ProgramCode` should always represent one consistent `ProgramName`.
7. `SubProgramCode` should be globally unique.
8. `SubProgramCode` should begin with its parent `ProgramCode`.
9. Programs should normally be global or inherited from a higher `DataObjectCode`.
10. SubPrograms should normally be the more department-specific layer.
11. Use `DataObjectCode = '0'` for truly global values.
12. Use a head-level `DataObjectCode` such as `302` where the value applies to that head and its children.
13. Only create child-level overrides where the value genuinely differs.

## Recommended Hierarchy In `tblSegmentValues`

- Sector row:
  - `SegmentNo = mapped SECTOR segment`
  - `DataObjectCode = '0'` for global sectors unless a client genuinely needs scoped sector rows
- Program row:
  - `SegmentNo = mapped PROGRAM segment`
  - `ParentSegmentNo = mapped SECTOR segment`
  - `ParentSegmentCode = SectorCode`
- SubProgram row:
  - `SegmentNo = mapped SUBPROGRAM segment`
  - `ParentSegmentNo = mapped PROGRAM segment`
  - `ParentSegmentCode = ProgramCode`

## Recommended Shape

- Sector: policy grouping
- Program: broad strategic purpose
- SubProgram: department or head-specific delivery/planning slice
- Objective: strategic result statement
- Target: optional result/milestone node used by some clients
- Output: optional deliverable node used by some clients
- Activity: concrete action

## Supported Hierarchy Patterns

The module must support more than one valid strategic hierarchy. Do not assume every client uses the same middle layers.

### Pattern A: Output-Based Delivery

- `Sector -> Program -> SubProgram -> Objective -> Output -> Activity`

Use this pattern when:

- the client plans around deliverables or service outputs
- activities are grouped under deliverables
- targets are mainly numeric indicator targets, not structural planning nodes

### Pattern B: Target-Based Delivery

- `Sector -> Program -> SubProgram -> Objective -> Target -> Activity`

Use this pattern when:

- the client stores structural target/result statements in source data
- activity codes or parent links clearly roll up to target codes
- the target is a planning node, not just a numeric indicator value

### Pattern C: Mixed Result And Delivery Model

- `Sector -> Program -> SubProgram -> Objective`
- optional result branch: `Objective -> Indicator -> Numeric Target`
- optional delivery branch: `Objective -> Output -> Activity`
- optional target branch: `Objective -> Target -> Activity`

Use this pattern when:

- a client tracks both delivery outputs and separate performance targets
- different ministries use different planning detail beneath the same objective layer

## Design Rules For Optional Layers

1. `Output` must remain supported in the platform, but it is not mandatory for every client.
2. `Target` can mean either:
   - a numeric indicator target in the performance layer, or
   - a structural result node in the planning hierarchy.
3. When a client uses structural `Target` rows from `tblSegmentValues`, treat them as hierarchy nodes, not as numeric indicator target values.
4. Do not force `Output` into a client hierarchy where the real source structure is `Objective -> Target -> Activity`.
5. Do not force structural `Target` into clients that only need numeric indicator targets.
6. `Objective`, `Target`, `Output`, and `Activity` may be source-backed or directly maintained depending on client configuration.
7. `Goal` and `Strategic Pillar` are planning-framework reference tables and do not need to exist in `tblSegmentValues`.
8. `OrgUnit / Ministry` is an ownership dimension, not part of the strategic code chain.

## Ownership Layer

The strategic hierarchy and the administrative ownership layer should stay separate.

- Strategic chain:
  - `Sector -> Program -> SubProgram -> Objective -> [Target or Output] -> Activity`
- Ownership chain:
  - one primary `OrgUnit / Ministry` owns a `Program`
  - optional additional ministries can be linked as collaborators
  - activity-level implementation ownership can be added where needed

## Example

- Sector `HL` = `Health`
- Program `01` = `Primary Health Care`
- SubProgram `0101` = `District Clinical Services`
- SubProgram `0102` = `Community Outreach`

## Why This Matters

This structure helps with:

- cleaner MTFF reporting
- simpler parent-child validation
- support for different client planning models without forcing fake layers
- safer overlay imports
- less duplication across `DataObjectCode` scopes
- clearer accountability and sector reporting

## What To Clean Up In Client Data

Flag these patterns for review:

- same `ProgramCode` with different names
- same `ProgramName` with different codes
- same `SubProgramCode` with different names
- `SubProgramCode` not starting with its parent `ProgramCode`
- global programs duplicated unnecessarily across many `DataObjectCode` values
