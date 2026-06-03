# Training And Test Script Compliance Audit

Date: 2026-05-29

## Scope

This audit covers the main routed screens in:

- Base Configuration
- Workflow
- Strategic Framework
- Budget Execution

It evaluates two related but different questions:

1. Can the screen support a reusable screen test script?
2. Can the screen support a guided training scenario with reliable step targets?

## Audit Criteria

### Test script compliant

A screen is treated as test-script compliant when:

- it is a normal routed UI screen
- it renders through the shared layout and controller stack
- it has a stable route that can be targeted from the screen test catalogue
- the page has enough visible state and predictable flow for manual or guided scripted verification

### Training scenario compliant

A screen is treated as training-scenario compliant when:

- it renders through the shared training-enabled layout
- it can expose stable step targets for the training overlay
- its flow is predictable enough for step-by-step user guidance
- the page has enough stable controls or targetable regions to support `TargetElementID` authoring

## Framework Findings

The platform-level training and test-script framework is broadly available across the application:

- Shared training context is injected from `BaseController`.
- The main layout includes the training banner, overlay, and `screenContent` metadata wrapper.
- The auto-hook script can generate element IDs for controls, forms, tables, and actions when the view does not define them explicitly.
- Screen test scripts are metadata-driven and only require stable route/module/screen-family definitions plus authored steps.

This means most normal screens are structurally capable of supporting test scripts, and many can support training scenarios even if the page was not originally hand-instrumented.

## Coverage Findings

Built-in catalogue coverage is still limited compared with overall screen volume:

- Built-in screen test scripts:
  - Base Configuration: 5 directly relevant built-ins (`base-config/readiness`, `system-settings/list`, `dataobjectcodes/index`, `segments/list`, `segment-values/list`)
  - Workflow: 1 built-in (`workflow-engine/list`)
  - Strategic Framework: 3 built-ins (`strategy-config/configuration-readiness`, `strategy-setup/sectors`, `strategy-fiscal/resource-envelope`)
  - Budget Execution: 0 built-ins
- Built-in training scenarios:
  - No built-in fallback scenarios currently target Base Configuration, Workflow, Strategic Framework, or Budget Execution screens
  - Current fallback scenarios are limited to fundamentals and users

So the framework exists, but authored content coverage is still selective.

## Module Matrix

| Module | Main Screens Audited | Test Script Compliance | Training Scenario Compliance | Notes |
|---|---:|---|---|---|
| Base Configuration | 23 | Pass | Partial | Strong for scripted checks; training is possible but several readiness/list/upload screens rely on auto-generated IDs rather than explicit stable targets. |
| Workflow | 14 | Pass | Mostly Pass | Workflow engine forms are strong training candidates; list and generic workflow screens are lighter on explicit IDs but still workable via auto-hooks. |
| Strategic Framework | 59 | Pass with authoring | Partial | Very broad surface area; many forms are viable, but many landing/report/list screens still need scenario curation and sometimes explicit target hardening. |
| Budget Execution | 8 | Pass with authoring | Mostly Pass | Transaction screens are strong training candidates; dashboard-style entry screens are lighter and not the best first training scenarios. |

## View Instrumentation Snapshot

Explicit `id=` usage is a useful proxy for hand-authored training readiness, though not the only one because auto-hooks can fill gaps.

### Base Configuration

- 23 audited screens
- 6 screens with zero explicit IDs in the first audit set, plus a few more low-ID screens in the extended Base Configuration area
- Strongest views:
  - `DataObjectCodesForm.php`
  - `DataObjectCodesList.php`
  - `WorkflowEngineForm.php`
  - `WorkflowEngineInquiry.php`
- Weaker views:
  - `BaseConfigurationReadiness.php`
  - `SystemSettingsListView.php`
  - `FiscalYearsList.php`
  - `VersionsList.php`
  - `SegmentValuesList.php`
  - `SegmentValuesUpload.php`

### Workflow

- 14 audited screens
- 4 screens with zero explicit IDs
- Strongest views:
  - `WorkflowEngineForm.php`
  - `WorkflowEngineStageForm.php`
  - `WorkflowEngineActionForm.php`
  - `WorkflowEngineInquiry.php`
- Weaker views:
  - `WorkflowAssignmentsList.php`
  - `WorkflowTaskTypesList.php`
  - `WorkflowTaskStatusesList.php`
  - `WorkflowForm.php`

### Strategic Framework

- 59 audited screens
- 18 screens with zero explicit IDs
- 20 additional screens with only light explicit ID coverage
- Strongest views:
  - `ProjectForm.php`
  - `ResourceEnvelopeForm.php`
  - `ProjectList.php`
  - `ResourceEnvelopeLines.php`
  - `ProgramForm.php`
- Weaker views:
  - `Index.php`
  - `ReportReadiness.php`
  - `StrategicPillarList.php`
  - `GoalList.php`
  - `IndicatorList.php`
  - `IndicatorTargetList.php`
  - `FundingSubmissionList.php`
  - `FundingSubmissionView.php`
  - `FundingSubmissionReport.php`
  - `SegmentPublishRequestList.php`
  - `SegmentPublishRequestForm.php`
  - `SegmentPublishRequestView.php`
  - `ProgramRiskList.php`
  - `ProgramRiskForm.php`

### Budget Execution

- 8 audited screens
- Transaction-heavy screens are well instrumented
- Strongest views:
  - `Warrants.php`
  - `Reservations.php`
  - `Supplementaries.php`
  - `Commitments.php`
  - `Rie.php`
- Weaker views:
  - `Index.php`
  - `BudgetReductions.php`
  - `OpeningBalances.php`

## Screen-Type Assessment

### Best candidates for immediate test script authoring

- Base Configuration readiness and maintenance screens
- Workflow engine list, forms, diagnostics, and inquiry
- Strategy setup forms and lists
- Strategy fiscal resource envelope screens
- Budget execution warrants, reservations, supplementaries, commitments, and RIE

### Best candidates for immediate training scenario authoring

- `dataobjectcodes/index` and edit/create flow
- `workflow-engine/list` plus definition, stage, and action forms
- `strategy-setup/programs` and `strategy-setup/program-form`
- `strategy-setup/projects` and `strategy-setup/project-form`
- `strategy-performance/objectives` and `strategy-performance/objective-form`
- `strategy-delivery/outputs` and `strategy-delivery/output-form`
- `strategy-fiscal/resource-envelope` and line/form flow
- `execution/warrants`
- `execution/reservations`
- `execution/supplementaries`
- `execution/commitments`
- `execution/rie`

### Screens that are compliant enough for test scripts but not ideal first training candidates

- Readiness dashboards
- Summary/report screens
- Sparse list-only screens with no explicit stable targets
- Views that depend heavily on context and record-specific dynamic state without obvious fixed targets

These are still testable and trainable, but they are better as later-wave scenarios after stronger form-driven scenarios are in place.

## Compliance Conclusion

### Test script conclusion

The four priority modules are broadly compliant for screen test script creation.

- No major framework blocker was found
- The main limitation is authored catalogue coverage, not technical capability
- New scripts can be created for most of these screens immediately

### Training scenario conclusion

The four priority modules are only partially uniform for guided training scenario authoring.

- The framework support is present
- Many high-value forms are already good candidates
- Not all screens are equally ready for durable step-by-step training without extra scenario curation
- Some views still depend too heavily on auto-generated IDs or sparse target structure

## Recommended Next Actions

1. Create screen test scripts first for uncovered high-value flows in Workflow, Strategic Framework, and Budget Execution.
2. Add explicit hand-authored IDs to the weaker training-target screens before building large training packs.
3. Start training scenario authoring with the strongest transactional and form-based screens rather than dashboards or reports.
4. Treat report/readiness screens as supporting steps inside broader scenarios, not as the first standalone guided scenarios.

## Practical Readout

If the question is:

- "Can we create test scripts for these modules now?" -> Yes
- "Can we create guided training scenarios for every screen in these modules right now with the same reliability?" -> No, not uniformly yet
- "Can we create guided training scenarios for the most important screens now?" -> Yes
