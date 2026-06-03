# Functional Area Access Matrices

This document extends the detailed [STRATEGIC_FRAMEWORK_ACCESS_MATRIX.md](C:/xampp82/htdocs/CBMSv21/STRATEGIC_FRAMEWORK_ACCESS_MATRIX.md) and provides the same design treatment for the remaining top-level functional areas in CBMSv21.

It is intended to help with:

- role design
- permission bundling
- menu structure
- mnemonic quick-code consistency
- future user-role assignment

## Quick-code standard

Top-level functional prefixes:

- `SF` = Strategic Framework
- `BS` = Budget Submission
- `BE` = Budget Execution
- `RP` = Reporting
- `AN` = Analytics
- `DB` = Dashboards
- `AD` = Administration
- `BA` = Base Configuration
- `FC` = Financial & Calculation Configuration
- `SC` = Strategy Configuration inside Configuration

Design rule:

- labels stay user-friendly
- menu pills and quick references use mnemonic codes
- the same codes can later be reused in help, search, training, and access matrix output

## Budget Submission

### Recommended roles

- `Budget Submission User`
- `Budget Submission Reviewer`
- `Budget Submission Approver`
- `Budget Submission Administrator`

### Purpose

This area covers:

- funding lodgements
- submission review and approval
- rates
- transaction input
- submission-related ceiling views and support tools

### Current permission mapping

- `ESTIMATES_VIEW`
- `ESTIMATES_EDIT`
- `RATES_VIEW`
- `RATES_EDIT`
- `RATES_CREATE`
- `STRATEGY_SUBMISSION_PREPARE`
- `STRATEGY_SUBMISSION_REVIEW`
- `STRATEGY_SUBMISSION_APPROVE`
- `STRATEGY_PUBLISH`

### Suggested future bundle names

- `BS_VIEW`
- `BS_EDIT`
- `BS_REVIEW`
- `BS_APPROVE`
- `BS_ADMIN`

### Key quick codes

- `BSLG` = Funding Lodgements
- `BSRV` = Funding Reviews
- `BSAP` = Funding Approvals
- `BSFI` = All Funding Items
- `BSFS` = Funding Submission Summary
- `BSRT` = Rates
- `BSTL` = Transaction Input List
- `BSTE` = Transaction Input Editor
- `BSST` = Transaction Stub Runner
- `BSBR` = Batch Runner
- `BSSL` = Single Save Load Test
- `BSCB` = Ceiling Balances
- `BSCK` = Ceiling Balance Keys
- `BSBG` = Budgets

## Budget Execution

### Recommended roles

- `Budget Execution User`
- `Budget Execution Reviewer`
- `Budget Execution Administrator`

### Purpose

This area will cover:

- warrants
- reallocations
- virements
- future execution transactions and controls

### Current state

The module is not yet implemented in a mature way. Menu access still depends on legacy role logic more than permissions.

### Suggested future bundle names

- `BE_VIEW`
- `BE_EDIT`
- `BE_REVIEW`
- `BE_ADMIN`

### Key quick codes

- `BEWR` = Warrants
- `BERE` = Reallocations
- `BEVI` = Virements

## Reporting

### Recommended roles

- `Reporting User`
- `Reporting Administrator`

### Purpose

This area is for cross-functional consumption of reports and readiness outputs.

It should stay read-focused for most users.

### Current permission mapping

- `STRATEGY_REPORT_VIEW`

### Suggested future bundle names

- `RP_VIEW`
- `RP_ADMIN`

### Key quick codes

- `RPSU` = Strategic Summary
- `RPSR` = Submission Readiness
- `RPSB` = Sector Budget Report
- `RPPB` = Program Budget Report
- `RPJB` = Project Budget Report
- `RPSD` = Program Structure Diagnostics
- `RPMT` = MTFF
- `RPPF` = Performance Report

## Analytics

### Recommended roles

- `Analytics User`
- `Analytics Administrator`

### Purpose

This area covers:

- analytics overview
- scenario results
- scenario comparison

### Current permission mapping

- `ANALYTICS_VIEW`

### Suggested future bundle names

- `AN_VIEW`
- `AN_ADMIN`

### Key quick codes

- `ANOV` = Analytics Overview
- `ANSR` = Scenario Results
- `ANSC` = Scenario Compare

## Dashboards

### Recommended roles

- `Dashboard User`
- `Dashboard Administrator`

### Purpose

This area covers operational dashboards and visual tools that may overlap with reporting and analytics but are optimized for quick monitoring.

### Current state

This area still uses legacy role gating and should be reviewed later for permission alignment.

### Suggested future bundle names

- `DB_VIEW`
- `DB_ADMIN`

### Key quick codes

- `DBOV` = Dashboard
- `DBFX` = Flexmonster Dashboard
- `DBCC` = Ceiling Calculator

## Administration

### Recommended roles

- `System Administrator`
- optional `Security Administrator`
- optional `Monitoring Administrator`

### Purpose

This area covers:

- users
- roles
- sessions
- audit
- diagnostics
- health
- logs
- access tools
- monitoring

### Current permission mapping

- `USERS_VIEW`
- `USERS_EDIT`
- `USERS_ADMIN`
- `ROLES_VIEW`
- `ROLES_ADMIN`
- `AUDIT_VIEW`
- `HEALTH_VIEW`
- `SESSION_VIEW`
- `SESSION_ADMIN`
- `DIAG_VIEW`
- `LOGS_VIEW`
- `ERRORLOG_VIEW`
- `ERRORLOG_ADMIN`
- `DATAOBJECTCODES_ADMIN`
- `METRICS_VIEW`
- `WORKFLOW_VIEW`
- `WORKFLOW_EDIT`
- `WORKFLOW_ADMIN`

### Key quick codes

- `ADUS` = Users
- `ADAX` = Access Matrix
- `ADSE` = Active Sessions
- `ADRO` = Roles
- `ADAU` = Audit
- `ADDI` = Diagnostics
- `ADHE` = Health
- `ADSV` = Session Vars
- `ADSM` = System Messages
- `ADDO` = Data Object Codes
- `ADDC` = Add Data Object Code
- `ADAM` = Access Management
- `ADGA` = Grant Access
- `ADAA` = Access Audit
- `ADLG` = Logs
- `ADMO` = Monitoring

## Configuration

### Recommended roles

- `Configuration Administrator`
- optional `Base Configuration Administrator`
- optional `Financial Configuration Administrator`
- optional `Strategy Configuration Administrator`

### Purpose

This area covers:

- base configuration
- financial and calculation configuration
- strategy configuration

### Current permission mapping

- `BASE_CONFIG_VIEW`
- `BASE_CONFIG_EDIT`
- `SYSSETTINGS_VIEW`
- `SYSSETTINGS_EDIT`
- `SYSSETTINGS_ADMIN`
- `FIN_CONFIG_VIEW`
- `FIN_CONFIG_EDIT`
- `CALC_ADMIN`
- `STRATEGY_CONFIG_EDIT`
- `STRATEGY_PUBLISH`

### Key quick codes

Base Configuration:

- `BACF` = Base Configuration
- `BACR` = Base Configuration Readiness
- `BASS` = System Settings
- `BASE` = Segments
- `BASV` = Segment Values

Financial & Calculation Configuration:

- `FCCF` = Financial & Calculation Configuration
- `FCCR` = Financial & Calculation Readiness
- `FCTS` = Transaction Type Segment Config
- `FCCB` = Ceiling Balances
- `FCCK` = Ceiling Balance Keys
- `FCSC` = Scenario Config
- `FCTD` = Transaction Calc Debug
- `FCFR` = Full Recalculation

Strategy Configuration:

- `SCFG` = Strategy Configuration
- `SCFA` = Fiscal Assumptions
- `SCPP` = Custom Phasing Profiles
- `SCPU` = Segment Publication

## Implementation guidance

Phase 1:

- keep the current permission codes in controllers
- use these functional-area roles as business assignment targets
- keep menu pills and access documentation aligned

Phase 2:

- create module-specific role bundles in SQL
- map live users from legacy roles to new functional roles
- tighten remaining legacy role-gated areas such as dashboards and execution

Phase 3:

- optionally rename technical permissions to the new module prefixes once the system is stable enough to absorb that change
