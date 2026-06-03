# Enterprise Workflow Engine Design

Date: 2026-05-20

## Goal

Build one shared approval and workflow engine for the entire CBMSv21 solution instead of separate one-off approval logic inside each module.

This engine should support:

- Strategic Framework workflows
- Budget Submission workflows
- Budget Execution workflows
- future publication, adjustment, transfer, and exception workflows

## Why A Shared Engine

The current solution already contains multiple workflow patterns:

- generic workflow task screens
- workflow assignments
- strategic version workflow
- submission review and approval logic
- execution transaction status approvals

These patterns are useful, but they are not yet unified. If each module keeps building its own approval model, the platform will drift into:

- inconsistent statuses
- different actor-separation rules
- duplicated audit logic
- inconsistent inbox behavior
- hard-to-maintain approval routing

## Design Principle

Separate:

1. business document tables
2. workflow state
3. workflow history
4. workflow routing and assignment
5. workflow actions

Business modules keep their own document and line tables.

The workflow engine owns:

- state transitions
- actor eligibility
- maker-checker rules
- assignment routing
- approval history
- pending task generation

## Recommended Workflow Layers

### 1. Workflow Definition Layer

Defines what a workflow is.

Core concepts:

- `WorkflowAreaCode`
  - examples: `STRATEGIC_VERSION`, `FUNDING_SUBMISSION`, `BE_WARRANT`, `BE_RESERVATION`, `BE_SUPPLEMENTARY`, `BE_COMMITMENT`, `BE_RIE`, `SEGMENT_PUBLISH`
- `WorkflowStageCode`
  - examples: `DRAFT`, `SUBMITTED`, `REVIEW`, `APPROVAL`, `APPROVED`, `REJECTED`, `RETURNED`, `CANCELLED`, `LOCKED`, `PUBLISHED`
- `WorkflowActionCode`
  - examples: `SUBMIT`, `RETURN`, `REVIEW_COMPLETE`, `APPROVE`, `REJECT`, `CANCEL`, `REOPEN`, `LOCK`, `PUBLISH`

Important requirement:

- workflows must support `multiple configurable stages`, not just a fixed single review and approval step

This means the engine should not assume there is only one reviewer stage and one approver stage. A workflow definition may need:

- preparer review
- technical review
- finance review
- management approval
- final approval

or any shorter or longer sequence depending on the workflow area.

### 2. Workflow Instance Layer

Stores workflow state for one real document.

Recommended shared table shape:

- `WorkflowInstanceID`
- `WorkflowAreaCode`
- `RecordTableName`
- `RecordID`
- `FiscalYearID`
- `VersionID`
- `DataObjectCode`
- `ScopeDataObjectCode`
- `CurrentStageCode`
- `CurrentStatusCode`
- `SubmittedBy`
- `SubmittedDate`
- `ReviewedBy`
- `ReviewedDate`
- `ApprovedBy`
- `ApprovedDate`
- `CancelledBy`
- `CancelledDate`
- `LockedBy`
- `LockedDate`
- `LastActionCode`
- `LastActionNote`
- `ActiveFlag`

This lets one engine serve many modules.

`ScopeDataObjectCode` is important because in most CBMS workflows the routing and visibility should follow the `DataObjectHierarchy`, not just the document header table.

### 3. Workflow History Layer

Stores every transition as a permanent audit trail.

Recommended shared history table:

- `WorkflowHistoryID`
- `WorkflowInstanceID`
- `WorkflowAreaCode`
- `RecordID`
- `FromStageCode`
- `ToStageCode`
- `WorkflowActionCode`
- `ActionBy`
- `ActionDate`
- `ActionNote`
- `DecisionOutcome`
- `AssignmentID`

This becomes the legal and operational audit trail.

### 4. Workflow Assignment Layer

The solution already has assignment concepts. Keep and extend them as the routing layer.

Assignments should support:

- workflow area
- workflow stage
- fiscal year
- version
- data scope
- org unit
- role-based routing
- named-user routing
- active date ranges
- escalation user or role

Assignments should normally resolve against the `DataObjectHierarchy`.

That means the engine should be able to:

- assign at an exact `DataObjectCode`
- inherit assignments from a parent data object when no exact assignment exists
- support escalation up the hierarchy where required
- respect the current session scope and document scope

### 5. Workflow Task Layer

Each pending action should create a task or inbox item.

Tasks should support:

- assigned user
- assigned role
- due date
- escalation status
- record link
- workflow stage
- open/closed status

## Standard Lifecycle Pattern

Not every module needs every state, but the shared engine should support this superset:

- `DRAFT`
- `SUBMITTED`
- `UNDER_REVIEW`
- `REVIEWED`
- `APPROVED`
- `REJECTED`
- `RETURNED`
- `CANCELLED`
- `REOPENED`
- `LOCKED`
- `PUBLISHED`

The engine should also support `multiple ordered stages` inside the lifecycle, for example:

- `DRAFT`
- `SUBMITTED`
- `TECHNICAL_REVIEW`
- `BUDGET_REVIEW`
- `FINANCE_REVIEW`
- `APPROVAL`
- `FINAL_APPROVAL`
- `APPROVED`

So the workflow state model needs both:

- a broad document status
- a precise current stage within that status

Examples:

- Budget Execution Warrant:
  - `DRAFT -> SUBMITTED -> APPROVED -> CANCELLED`
- RIE:
  - `DRAFT -> SUBMITTED -> UNDER_REVIEW -> APPROVED / REJECTED / RETURNED`
- Strategic Version:
  - `DRAFT -> SUBMITTED -> APPROVED -> LOCKED`

## Critical Control Rules

The shared engine should enforce these globally where configured:

- maker-checker separation
- reviewer cannot approve their own review where 2-step approval is required
- actor cannot approve own draft unless explicitly allowed by policy
- no editing after submission unless returned or reopened
- no line edits after approval
- no cancellation by ordinary user roles
- optional dual approval for sensitive areas
- mandatory notes for rejection, return, reopen, and cancel

When hierarchy-linked routing is enabled, the engine should also enforce:

- users can only action workflow items within their allowed `DataObjectHierarchy` scope
- inherited routing from parent scope is traceable
- workflow history records which scope assignment rule was used

## Module Integration Pattern

Each module should integrate like this:

1. create document in local table
2. create or sync workflow instance
3. render current workflow status from shared engine
4. render allowed actions from shared engine
5. execute transition through shared engine
6. engine updates:
   - workflow instance
   - workflow history
   - task assignments
7. module-specific post-approval logic runs only after valid approval

Examples of module-specific post-approval logic:

- Warrant approval updates released authority
- Supplementary approval updates current authorized budget
- Segment publish approval writes approved values into `tblSegmentValues`

## What Can Be Reused

Existing solution pieces that can inform the shared engine:

- generic workflow screens under `app/Views/workflow`
- workflow assignment setup
- strategic version workflow tables and history
- submission actor-separation rules
- execution reviewer/admin role split
- existing session scope and `DataObjectCode` hierarchy behavior

## DataObjectHierarchy Requirement

This is a core platform rule, not an optional enhancement.

In most cases workflow routing should be linked to the `DataObjectHierarchy`.

That means:

1. each workflow instance should carry the effective `ScopeDataObjectCode`
2. assignment lookup should first try an exact scope match
3. if no match exists, it should walk up the parent hierarchy
4. the resolved assignment should be stored in workflow history/tasks for audit
5. inbox filtering should respect the same hierarchy logic

Example:

- a commitment raised under a district-level data object should route to the district approver if configured
- if no district approver assignment exists, it may inherit the ministry or parent-scope approver depending on setup

This hierarchy-aware routing is likely to be one of the most important differences between CBMSv21 and a generic workflow package.

## Additional Definition Tables Recommended

To support multiple stages and hierarchy-aware routing, the engine should likely include:

- `tblWorkflowDefinition`
  - one row per workflow area
- `tblWorkflowDefinitionStage`
  - ordered stages per workflow area
  - includes `StageOrder`, `StageCode`, `StageLabel`, `StageType`
- `tblWorkflowDefinitionAction`
  - allowed actions from each stage
- `tblWorkflowAssignment`
  - extended to include `DataObjectCode` and inheritance behavior
- `tblWorkflowTask`
  - pending actions generated from the current stage

Key stage fields should include:

- `StageOrder`
- `IsReviewStage`
- `IsApprovalStage`
- `AllowsReturn`
- `AllowsReject`
- `AllowsDelegation`
- `RequiresDifferentActorFromPreviousStage`
- `RouteByDataObjectHierarchy`

## Recommended Build Phases

### Phase 1. Foundation

Build the shared engine tables and service layer.

Deliverables:

- workflow instance table
- workflow history table
- workflow definition table
- workflow definition stages table
- workflow definition actions table
- workflow action definitions
- workflow stage definitions
- reusable workflow service
- reusable workflow panel UI partial

### Phase 2. Governance Rules

Add:

- maker-checker enforcement
- action permission matrix
- routing resolution
- hierarchy-based routing resolution
- workflow notes
- due dates and SLA hooks
- multi-stage progression rules

### Phase 3. First Adopters

Migrate highest-risk functional workflows first:

1. Budget Execution
2. Budget Submission
3. Strategic publication requests

### Phase 4. Shared Inbox

Build:

- my approvals
- my reviews
- overdue tasks
- returned items
- delegated tasks

### Phase 5. Advanced Workflow

Later features:

- multi-step approvals
- conditional routing by amount/risk
- escalation rules
- delegation
- substitute approvers
- notifications

## Immediate Recommendation

Do not keep extending module-specific approval logic.

Instead:

1. define the shared workflow engine now
2. build it as a platform service
3. migrate Budget Execution onto it first
4. then align Budget Submission and Strategic workflows to the same engine

## First Implementation Target

The first practical implementation target should be:

- shared workflow engine foundation tables
- shared PHP workflow service
- retrofit `Budget Execution Supplementaries` and `RIE` first

Reason:

- they have the highest approval sensitivity
- they already expose the need for richer states
- they benefit most from return/reject/maker-checker controls
- they will validate multi-stage and hierarchy-based routing in a real transactional context
