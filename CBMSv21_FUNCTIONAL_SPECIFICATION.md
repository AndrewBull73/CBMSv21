# CBMSv21 Functional Specification

Version: Client Issue 1  
Prepared: 2026-05-22  

## Executive Summary

CBMSv21 is a modular budget management platform designed to support the public financial management lifecycle from strategic planning through budget preparation, approval, execution control, reporting, and administration.

The solution is intended for organizations that require more than simple budget entry. It provides a structured operating model for planning frameworks, funding requests, workflow-driven approvals, in-year control transactions, management reporting, and role-based governance.

At a high level, CBMSv21 enables a client to:

- define strategic and fiscal planning structures
- prepare and review budget submissions within controlled workflows
- roll approved budgets into execution and manage in-year adjustments
- monitor transactions, approvals, reports, and operational activity through a single platform
- control access by user role, permission, and organizational or data scope

The platform is well suited to phased implementation. A client may adopt the full end-to-end scope or sequence delivery across strategic planning, budget submission, execution control, reporting, and integration workstreams.

Key strengths of the solution include:

- modular functional design
- configurable workflows and approval routing
- role-based and scope-based security
- strong administrative and audit support
- support for reporting, dashboards, training, and testing

In practical terms, CBMSv21 should be viewed as an enterprise budgeting and control platform that combines business process support with governance, traceability, and implementation flexibility.

## 1. Document Purpose

This document provides a high-level functional specification for the CBMSv21 solution. It is intended to give a prospective customer a clear view of the platform scope, major modules, business capabilities, user roles, workflows, reporting features, and operational controls.

The document is written to be suitable for direct customer review and commercial discussions.

## 2. Solution Overview

CBMSv21 is a web-based budget management platform designed to support the end-to-end public financial management lifecycle across:

- strategic planning and budget framework setup
- budget preparation and submission
- budget execution control
- workflow-driven review and approval
- operational reporting and dashboards
- user, security, and configuration administration

The solution is organized as a modular platform so that clients can adopt a full implementation or phase modules in over time.

At a high level, CBMSv21 supports the following business outcomes:

- define and maintain strategic and fiscal planning structures
- prepare budget submissions against approved planning frameworks
- manage budget execution transactions against an approved baseline
- route transactions and planning items through configurable approval workflows
- publish or report approved information for management use
- control access by user, role, permission, and data scope

## 3. Delivery Model And Product Positioning

CBMSv21 is designed as a configurable government budgeting and control platform rather than a single-purpose line-of-business screen set.

The product supports:

- multi-module deployment
- role-based access control
- fiscal year and version-based working contexts
- configurable data object scope control
- workflow assignment by organizational or data hierarchy
- configurable strategic dimensions and client-specific attributes

Typical deployment sequencing can include:

1. base configuration and security setup
2. strategic framework and planning structures
3. budget submission and review
4. budget execution controls
5. reporting, dashboards, training, and integrations

## 4. Core Functional Areas

The current CBMSv21 baseline is organized into the following major functional areas.

### 4.1 Strategic Framework

This module supports the medium-term and annual planning framework that sits upstream of budget submission and execution.

The Strategic Framework area includes:

- strategic overview and readiness review
- segment mapping and source-dimension configuration
- fiscal period labels and fiscal assumptions
- custom phasing profiles
- custom attribute administration
- publication preparation for selected strategic dimensions
- structure setup for sectors, programs, sub-programs, projects, funding types, funding sources, and economic items
- planning framework setup for strategic pillars, goals, objectives, indicators, and indicator targets
- delivery and costing setup for outputs, activities, and activity budgets
- governance records including narratives, fiscal risks, and program risks
- fiscal framework management including resource envelopes and sector ceilings
- strategy-level summary and readiness reporting

Business purpose:

- create the policy and program structure against which budgets are planned
- define strategic outcomes and measurable indicators
- maintain the fiscal envelope and sector allocations used during budget preparation
- provide a controlled framework for downstream funding requests and reports

### 4.2 Budget Submission

This module supports working budget preparation and funding request activities.

The Budget Submission area includes:

- transaction input list and transaction editor
- rates maintenance
- processing utilities for transaction runs and recalculation support
- ceiling balance views and ceiling key maintenance
- funding lodgements
- funding review screens
- funding approval screens
- consolidated funding item views and submission summaries

Business purpose:

- capture and revise draft budget information
- validate entries against configured dimensions and ceiling structures
- prepare funding submissions for review and approval
- support controlled progression from preparation to assessment and approval

### 4.3 Budget Execution

This module supports post-approval execution control using an execution version created from an approved submission baseline.

The Budget Execution area includes:

- setup and rollover from approved submission versions into execution versions
- opening balances
- warrants
- supplementary budgets
- budget reductions
- reservations
- RIE (Request to Incur Expenditure)
- commitments

Business purpose:

- establish an execution baseline from an approved budget
- manage authority release and in-year budget adjustments
- reserve and commit funds against available authority
- provide transaction-level control and auditability during budget implementation

Current product note:

- supplementary budgets and RIE currently use the shared multi-stage workflow engine
- warrants, reservations, and commitments are present as functional screens and transaction types, with continued workflow alignment underway as part of product evolution

### 4.4 Workflow And Approvals

CBMSv21 includes a shared workflow capability intended to support review and approval across multiple business areas.

The workflow capability includes:

- workflow task list / inbox
- workflow transition actions
- workflow assignment maintenance
- workflow engine administration
- workflow stage and action configuration
- routing diagnostics for assignment resolution by scope and hierarchy

Business purpose:

- standardize review and approval handling across modules
- support configurable multi-stage workflows
- assign actions by user, role, and data scope
- maintain a traceable workflow history for oversight and audit

### 4.5 Reporting

The Reporting area provides formal and operational reporting support.

Current reporting capabilities include:

- report catalogue
- report execution and launch
- report context selection
- report run history
- report run detail review
- report definition administration
- strategic summary reports
- submission readiness reporting
- sector, program, and project budget reports
- MTFF view
- performance report
- program structure diagnostics

Business purpose:

- provide formal outputs for management, review, and publication
- support both pre-defined strategic reports and broader report catalogue execution
- retain report run history for repeatability and traceability

### 4.6 Dashboards And Analytics

CBMSv21 includes operational analytics and dashboard functions.

These include:

- analytics overview
- published scenario results
- scenario comparison
- operational dashboards
- flex-style dashboard views

Business purpose:

- provide visual and comparative analysis of selected outputs
- support operational monitoring and management insight
- complement formal reports with faster, more interactive views

### 4.7 Scenario Modelling

The solution contains scenario administration and scenario result capabilities linked to a dedicated calculation engine.

Current scenario functions include:

- scenario model definition
- scenario setup and parameter maintenance
- value and rate override maintenance
- scenario execution
- result publication
- scenario reset and synchronization utilities
- published result viewing and comparison

Business purpose:

- support what-if analysis and structured calculation scenarios
- allow selected assumptions or rate changes to be tested
- publish scenario outputs for analytical consumption

### 4.8 Integration Administration

The product includes an integration administration layer to support controlled exchanges with external systems.

Current capabilities include:

- external system register
- interface register
- run history
- run detail review
- test export execution
- download of export outputs and summary packages

Business purpose:

- define and manage external interface points
- support controlled import/export operations
- retain a traceable run history for operational support

Current product note:

- the platform direction clearly supports an internal integration hub model with reusable system adapters and run logging
- this area should be positioned as integration-ready and administratively manageable, with client-specific interface build-out completed during implementation

### 4.9 User Training And Guided Adoption

CBMSv21 contains a training capability intended to support guided learning and rollout support.

Current training-related functions include:

- training scenario catalogue
- training runner
- user-level training progress views
- training state management
- start, stop, reset, complete, and note capture actions
- training administration for scenarios, steps, and translations

Business purpose:

- support guided onboarding and structured learning
- help users follow real business scenarios in a controlled way
- track completion and user support requirements during rollout

### 4.10 Testing Support

The application also includes a structured screen test facility for internal testing and UAT-style support.

Current capabilities include:

- screen test scenario catalogue
- guided test runner
- result logging
- summary review
- screenshot capture
- attachment upload and retrieval
- screen test administration

Business purpose:

- support repeatable business testing
- retain evidence of test execution
- improve release readiness and user acceptance processes

### 4.11 Administration And Platform Control

The Administration area provides cross-platform control features.

These include:

- user administration
- role administration
- access matrix review
- user-role assignment
- account unlock
- user import and export
- active session management
- session inspection
- audit log access
- diagnostics and health monitoring
- system messages
- data object code administration
- scoped access management for data object codes
- system settings and readiness checks

Business purpose:

- enforce security and controlled access
- provide operational support tooling
- support rollout administration and ongoing platform governance

### 4.12 Configuration Workbench

CBMSv21 also includes a dedicated configuration area for core system, workflow, and calculation setup.

This area includes:

- base configuration readiness
- system settings register and usage map
- segment and segment value administration
- workflow engine configuration
- workflow assignment configuration
- training catalogue administration
- testing script catalogue administration
- integration configuration
- financial and calculation readiness
- transaction type segment configuration
- ceiling balance maintenance utilities
- scenario configuration
- transaction calculation diagnostics
- full recalculation support

Business purpose:

- provide implementation and support teams with controlled configuration tooling
- separate business operations from technical and setup administration
- support phased rollout, diagnostics, and controlled maintenance of core rules

## 5. Functional Users And Roles

CBMSv21 supports role-based access control and data-scope-aware access. While role names can be adapted during implementation, the current product baseline supports role families such as:

- Strategic Framework User
- Strategic Framework Reviewer
- Strategic Framework Approver
- Strategic Framework Reporting User
- Strategic Framework Administrator
- Budget Submission User
- Budget Submission Reviewer
- Budget Submission Approver
- Budget Submission Administrator
- Budget Execution User
- Budget Execution Reviewer
- Budget Execution Administrator
- Reporting User
- Reporting Administrator
- Analytics User
- Analytics Administrator
- Dashboard User
- Dashboard Administrator
- System Administrator

Roles are enforced through:

- authentication
- permission codes
- menu visibility
- controller-level access checks
- workflow assignments
- data object scope access

## 6. Key Business Concepts

The following concepts are central to how CBMSv21 operates.

### 6.1 Fiscal Year And Version Context

The platform works in an explicit fiscal context and version context. This allows clients to separate:

- planning cycles
- draft and approved submission versions
- execution versions
- scenario runs and published outputs

This design supports controlled preparation, approval, rollover, and reporting.

### 6.2 Data Object Scope

Many screens and workflows operate within a selected data object scope. This supports:

- segregation by ministry, department, or organizational area
- hierarchical routing and approval
- scoped visibility of transactions and tasks

### 6.3 Strategic Dimensions

Strategic setup is based on configurable dimensions such as:

- sectors
- programs
- sub-programs
- projects
- funding sources
- economic items
- objectives and indicators

These structures are then reused in submission, reporting, and execution contexts.

### 6.4 Shared Workflow

Workflow is treated as a common service across modules rather than as isolated approval logic. This provides:

- consistent stage progression
- configurable actions
- controlled transition handling
- workflow history
- common inbox/task behavior

## 7. End-To-End Process Support

The current product baseline supports the following end-to-end business process pattern.

### 7.1 Planning And Framework Preparation

Users configure strategic structures, planning dimensions, fiscal assumptions, and ceiling frameworks.

Outputs include:

- strategic structure records
- fiscal envelope and ceiling data
- performance and governance records
- readiness views showing configuration completeness

### 7.2 Budget Preparation And Funding Requests

Budget users enter or revise working estimates, rates, and supporting submission information.

Outputs include:

- draft budget lines
- funding lodgements
- readiness and review outputs
- items prepared for approval workflow

### 7.3 Review, Approval, And Publication

Reviewers and approvers assess submissions or workflow items and move them through configured transitions.

Outputs include:

- approved funding items
- published planning outputs where applicable
- workflow history and task closure

### 7.4 Execution Baseline Creation

An approved submission version is rolled into an execution version.

Outputs include:

- execution opening balances
- linked baseline/version lineage
- a controlled starting point for in-year execution

### 7.5 In-Year Budget Control

Execution users manage authority release, adjustments, reservations, RIE items, and commitments.

Outputs include:

- released budget authority
- supplementary increases or reductions
- reserved and committed balances
- audit-ready execution transactions

### 7.6 Reporting, Analysis, And Oversight

Managers, analysts, and administrators consume reports, dashboards, scenario outputs, and operational run history.

Outputs include:

- management reports
- budget summaries
- performance views
- workflow oversight
- integration and testing evidence

## 8. Module-Level Functional Detail

### 8.1 Strategic Framework Detail

The Strategic Framework module provides the master policy and planning structure of the solution.

Main user actions:

- maintain planning dimensions and hierarchies
- import or map source overlay values
- define strategic objectives and indicators
- record delivery outputs and activity budgets
- maintain narratives and risk registers
- manage fiscal assumptions, envelopes, and ceilings
- review readiness and summary reports

Key outputs:

- strategic structure master data
- fiscal planning records
- governance records
- strategic and budget reports

### 8.2 Budget Submission Detail

The Budget Submission module provides working-entry and review capabilities for draft budget preparation.

Main user actions:

- capture and edit transaction input
- maintain calculation rates
- run calculation or processing utilities
- review ceiling balances
- prepare and submit funding items
- review and approve funding requests according to role

Key outputs:

- prepared budget entries
- validated submission items
- funding review and approval outcomes

### 8.3 Budget Execution Detail

The Budget Execution module manages post-approval control activities.

Main user actions:

- create or select the active execution version
- run rollover from an approved source budget
- review opening balances
- create and approve warrants
- create and process supplementary budgets
- generate budget reductions
- create and process reservations
- create and process RIE documents
- create and process commitments

Key outputs:

- execution control records
- in-year authority changes
- committed and reserved balances
- workflow and audit history for execution documents

### 8.4 Reporting Detail

The Reporting module supports both formal outputs and operational retrieval.

Main user actions:

- browse available reports
- select report context
- execute or launch a report
- review run history
- inspect run details
- maintain report definitions where authorized

Key outputs:

- formal report files
- run logs and repeatable report history
- standard strategic management reports

### 8.5 Workflow Detail

The workflow module provides a shared operational approval layer.

Main user actions:

- review pending tasks
- action workflow transitions
- maintain workflow assignments
- define or update workflow areas, stages, and actions
- inspect routing diagnostics

Key outputs:

- workflow task queues
- assignment resolution
- workflow history and traceability

## 9. Security And Control Model

CBMSv21 includes the following functional control features.

- authenticated user access
- login, logout, and account management
- token-based access support for selected use cases
- permission-based menu and function control
- role-based segregation of duties
- data-scope-aware visibility and routing
- active session monitoring and forced logout support
- audit logging
- diagnostics and health checks
- workflow-driven approval history

These controls support both operational governance and implementation-specific segregation-of-duties design.

## 10. Reporting And Operational Evidence

The current baseline supports traceability through several evidence layers:

- audit records
- workflow history
- report run history
- integration run history
- training progress
- screen test results
- session monitoring

This is an important differentiator for public-sector budgeting environments where accountability and reviewability are often as important as raw data entry capability.

## 11. Configurability

CBMSv21 is designed to allow implementation-time tailoring without changing the core business purpose of the solution.

Configurable areas include:

- roles and permission assignments
- data object scope structures
- system settings
- segments and segment values
- workflow definitions, stages, and actions
- workflow assignments by hierarchy and context
- strategic dimensions and overlays
- custom attributes
- fiscal assumptions and period labels
- calculation and segment rules
- report definitions
- external system and interface definitions
- training scenarios and guided steps

## 12. Interfaces And External Connectivity

The current product baseline is suitable for client-specific integration extensions.

Supported integration-oriented capabilities include:

- registration of external systems
- registration of interface definitions
- controlled test export execution
- downloadable export outputs
- run logging and run review

Typical implementation targets may include:

- finance systems
- actuals imports
- approved budget exports
- reference/master data exchange

The detailed transport, mapping, and scheduling model should be finalized as part of implementation design for each customer.

## 13. Scope Notes

The following points should be noted for customer discussion:

- CBMSv21 provides broad coverage across planning, submission, workflow, reporting, administration, and execution control.
- Some areas, especially within Budget Execution workflow coverage and external integration build-out, are best finalized during implementation design so that they align with client policy and operating practice.
- The solution includes training and testing capabilities that may be deployed directly or used as rollout-support tools depending on client preference.
- Final delivery scope, priorities, and integration detail should be confirmed during the implementation blueprint phase.

## 14. Recommended Customer Positioning Summary

CBMSv21 should be presented to a prospective customer as:

- a modular budget management platform
- strong in strategic framework setup, structured funding workflows, reporting, and administration
- already equipped with execution control foundations and shared workflow capability
- suitable for phased rollout
- adaptable to client organizational structures, approval models, and integration requirements

## 15. Proposed Implementation Workstreams

For customer discussions, the solution can be grouped into the following workstreams:

1. Security, users, roles, and data scope
2. Strategic framework and fiscal setup
3. Budget submission and approval workflows
4. Budget execution controls
5. Reporting and dashboards
6. Integrations
7. Training, testing, and rollout support

## 16. Conclusion

CBMSv21 is a broad-scope budgeting and control platform intended to support the full lifecycle from planning through execution oversight. Its current structure shows a clear emphasis on controlled workflows, configurable structures, public-sector reporting needs, and administrative traceability.

For a prospective customer, the product is best framed as a configurable enterprise budgeting platform with mature planning, reporting, and administrative foundations, plus extendable workflow, execution, analytics, and integration capabilities that can be tailored during implementation.
