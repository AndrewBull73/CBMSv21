USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

DECLARE @ResetManagedRolePermissions bit = 1;

IF OBJECT_ID('tempdb..#Permissions') IS NOT NULL DROP TABLE #Permissions;
CREATE TABLE #Permissions
(
    PermissionCode NVARCHAR(100) NOT NULL PRIMARY KEY,
    [Description] NVARCHAR(255) NULL
);

INSERT INTO #Permissions (PermissionCode, [Description]) VALUES
(N'ADMIN_ALL', N'Full administrative override access across the application'),
(N'SYSADMIN', N'System-level administrative access for legacy and advanced administration screens'),
(N'BASE_CONFIG_VIEW', N'View base configuration readiness and maintenance screens'),
(N'BASE_CONFIG_EDIT', N'Edit base configuration records and imports'),
(N'FIN_CONFIG_VIEW', N'View financial and calculation configuration screens'),
(N'FIN_CONFIG_EDIT', N'Edit financial and calculation configuration records'),
(N'CALC_ADMIN', N'Run advanced calculation, scenario, and recalculation administration'),
(N'ESTIMATES_VIEW', N'View transaction input and estimate work areas'),
(N'ESTIMATES_EDIT', N'Edit transaction input and estimate work areas'),
(N'RATES_VIEW', N'View rates and estimate factors'),
(N'RATES_EDIT', N'Edit rates and estimate factors'),
(N'RATES_CREATE', N'Create new rates and estimate factors'),
(N'STRATEGY_VIEW', N'View the Strategy overview and strategic dashboards'),
(N'STRATEGY_CONFIG_EDIT', N'Maintain Strategy configuration and mapped dimensions'),
(N'STRATEGY_SETUP_EDIT', N'Maintain Strategy setup structures and master records'),
(N'STRATEGY_PERFORMANCE_EDIT', N'Maintain Strategy performance framework records'),
(N'STRATEGY_DELIVERY_EDIT', N'Maintain Strategy delivery, activities, and budgets'),
(N'STRATEGY_GOVERNANCE_EDIT', N'Maintain Strategy narratives and risks'),
(N'STRATEGY_FISCAL_EDIT', N'Maintain Strategy fiscal framework, envelopes, and ceilings'),
(N'STRATEGY_REPORT_VIEW', N'View Strategy reports and readiness dashboards'),
(N'STRATEGY_WORKFLOW_EDIT', N'Advance Strategy workflow states'),
(N'STRATEGY_SUBMISSION_PREPARE', N'Prepare and maintain funding submissions'),
(N'STRATEGY_SUBMISSION_REVIEW', N'Review and assess funding submissions'),
(N'STRATEGY_SUBMISSION_APPROVE', N'Approve funding submissions'),
(N'STRATEGY_PUBLISH', N'Publish approved Strategy submissions and workflow items'),
(N'STRATEGY_SEGMENT_PUBLISH', N'Administer Strategy segment publication requests'),
(N'ANALYTICS_VIEW', N'View analytics and scenario result screens'),
(N'USERS_VIEW', N'View users'),
(N'USERS_EDIT', N'Edit users'),
(N'USERS_ADMIN', N'Administer users including security actions'),
(N'ROLES_VIEW', N'View roles and access summaries'),
(N'ROLES_ADMIN', N'Administer roles and permission assignments'),
(N'AUDIT_VIEW', N'View audit trails'),
(N'HEALTH_VIEW', N'View application health screens'),
(N'SESSION_VIEW', N'View active sessions and session state'),
(N'SESSION_ADMIN', N'Administer active sessions'),
(N'DIAG_VIEW', N'View diagnostics screens'),
(N'LOGS_VIEW', N'View application logs'),
(N'ERRORLOG_VIEW', N'View error logs'),
(N'ERRORLOG_ADMIN', N'Administer error logs'),
(N'DATAOBJECTCODES_ADMIN', N'Administer data object codes and access mappings'),
(N'SYSSETTINGS_VIEW', N'View system settings'),
(N'SYSSETTINGS_EDIT', N'Edit system settings'),
(N'SYSSETTINGS_ADMIN', N'Administer system settings'),
(N'WORKFLOW_VIEW', N'View workflow administration screens'),
(N'WORKFLOW_EDIT', N'Edit workflow configuration'),
(N'WORKFLOW_ADMIN', N'Administer workflow definitions and destructive workflow actions'),
(N'WORKFLOW_OPERATIONS_VIEW', N'View workflow task operations'),
(N'WORKFLOW_OPERATIONS_EDIT', N'Edit and transition workflow task operations'),
(N'WORKFLOW_OPERATIONS_ADMIN', N'Administer workflow task operations'),
(N'WORKFLOW_PROJECTS_VIEW', N'View workflow projects'),
(N'WORKFLOW_PROJECTS_CREATE', N'Create workflow projects'),
(N'WORKFLOW_PROJECTS_EDIT', N'Edit workflow projects'),
(N'WORKFLOW_PROJECTS_DELETE', N'Delete or archive workflow projects'),
(N'WORKFLOW_REQUIREMENTS_VIEW', N'View workflow requirements'),
(N'WORKFLOW_REQUIREMENTS_CREATE', N'Create workflow requirements'),
(N'WORKFLOW_REQUIREMENTS_EDIT', N'Edit workflow requirements'),
(N'WORKFLOW_REQUIREMENTS_DELETE', N'Delete or archive workflow requirements'),
(N'METRICS_VIEW', N'View metrics and monitoring screens'),
(N'DATAOBJECTCODES_VIEW', N'View data object codes, types, hierarchy, and workflow status'),
(N'DATAOBJECTCODES_EDIT', N'Edit data object codes, types, hierarchy, and workflow status'),
(N'DATAOBJECTCODES_IMPORT', N'Run data object code imports and load-from-segment workflows'),
(N'DATAOBJECTCODES_ACCESS_ADMIN', N'Administer data object code access grants'),
(N'SEGMENTS_VIEW', N'View segment and segment value setup'),
(N'SEGMENTS_EDIT', N'Edit segment and segment value setup'),
(N'SEGMENT_VALUES_IMPORT', N'Import or bulk load segment values'),
(N'BUDGET_EXECUTION_VIEW', N'View budget execution work areas'),
(N'BUDGET_EXECUTION_EDIT', N'Prepare and edit budget execution transactions'),
(N'BUDGET_EXECUTION_REVIEW', N'Review, return, approve, or cancel budget execution transactions'),
(N'BUDGET_EXECUTION_ADMIN', N'Administer budget execution setup, rollover, and advanced actions'),
(N'DASHBOARD_VIEW', N'View dashboard screens'),
(N'DASHBOARD_ADMIN', N'Administer dashboard access and advanced dashboard options');

INSERT INTO dbo.tblPermissions (PermissionCode, [Description], Active)
SELECT p.PermissionCode, p.[Description], 1
FROM #Permissions p
LEFT JOIN dbo.tblPermissions existing
    ON existing.PermissionCode = p.PermissionCode
WHERE existing.PermissionID IS NULL;

UPDATE existing
SET existing.[Description] = p.[Description],
    existing.Active = 1
FROM dbo.tblPermissions existing
INNER JOIN #Permissions p
    ON p.PermissionCode = existing.PermissionCode;

IF OBJECT_ID('tempdb..#Roles') IS NOT NULL DROP TABLE #Roles;
CREATE TABLE #Roles (RoleName NVARCHAR(255) NOT NULL PRIMARY KEY);

INSERT INTO #Roles (RoleName) VALUES
(N'Super Admin'),
(N'System Administrator'),
(N'Security Administrator'),
(N'Configuration Administrator'),
(N'Base Configuration Administrator'),
(N'Financial Configuration Administrator'),
(N'Organisation / COA Administrator'),
(N'Strategy Configuration Administrator'),
(N'Strategic Framework User'),
(N'Strategic Framework Reviewer'),
(N'Strategic Framework Approver'),
(N'Strategic Framework Reporting User'),
(N'Strategic Framework Administrator'),
(N'Budget Strategy User'),
(N'Budget Strategy Reviewer'),
(N'Budget Strategy Approver'),
(N'Budget Strategy Administrator'),
(N'Budget Planning User'),
(N'Budget Planning Administrator'),
(N'Budget Submission User'),
(N'Budget Submission Reviewer'),
(N'Budget Submission Approver'),
(N'Budget Submission Administrator'),
(N'Budget Execution User'),
(N'Budget Execution Reviewer'),
(N'Budget Execution Administrator'),
(N'Workflow Operations User'),
(N'Workflow Operations Editor'),
(N'Workflow Operations Administrator'),
(N'Reporting User'),
(N'Reporting Administrator'),
(N'Analytics User'),
(N'Analytics Administrator'),
(N'Dashboard User'),
(N'Dashboard Administrator');

INSERT INTO dbo.tblRoles (RoleName, Active, DateCreated, DateUpdated)
SELECT r.RoleName, 1, GETDATE(), GETDATE()
FROM #Roles r
LEFT JOIN dbo.tblRoles existing
    ON existing.RoleName = r.RoleName
WHERE existing.RoleID IS NULL;

UPDATE existing
SET existing.Active = 1,
    existing.DateUpdated = GETDATE()
FROM dbo.tblRoles existing
INNER JOIN #Roles r
    ON r.RoleName = existing.RoleName;

IF OBJECT_ID('tempdb..#ManagedRoles') IS NOT NULL DROP TABLE #ManagedRoles;
CREATE TABLE #ManagedRoles (RoleName NVARCHAR(255) NOT NULL PRIMARY KEY);

INSERT INTO #ManagedRoles (RoleName)
SELECT RoleName FROM #Roles;

IF @ResetManagedRolePermissions = 1
BEGIN
    DELETE rp
    FROM dbo.tblRolePermissions rp
    INNER JOIN dbo.tblRoles r
        ON r.RoleID = rp.RoleID
    INNER JOIN #ManagedRoles mr
        ON mr.RoleName = r.RoleName;
END;

IF OBJECT_ID('tempdb..#RolePerm') IS NOT NULL DROP TABLE #RolePerm;
CREATE TABLE #RolePerm
(
    RoleName NVARCHAR(255) NOT NULL,
    PermissionCode NVARCHAR(100) NOT NULL
);

INSERT INTO #RolePerm (RoleName, PermissionCode)
SELECT N'Super Admin', PermissionCode
FROM dbo.tblPermissions
WHERE Active = 1 OR Active IS NULL;

INSERT INTO #RolePerm (RoleName, PermissionCode) VALUES
(N'System Administrator', N'SYSADMIN'),
(N'System Administrator', N'USERS_VIEW'),
(N'System Administrator', N'USERS_EDIT'),
(N'System Administrator', N'USERS_ADMIN'),
(N'System Administrator', N'ROLES_VIEW'),
(N'System Administrator', N'ROLES_ADMIN'),
(N'System Administrator', N'AUDIT_VIEW'),
(N'System Administrator', N'HEALTH_VIEW'),
(N'System Administrator', N'SESSION_VIEW'),
(N'System Administrator', N'SESSION_ADMIN'),
(N'System Administrator', N'DIAG_VIEW'),
(N'System Administrator', N'LOGS_VIEW'),
(N'System Administrator', N'ERRORLOG_VIEW'),
(N'System Administrator', N'ERRORLOG_ADMIN'),
(N'System Administrator', N'WORKFLOW_OPERATIONS_VIEW'),
(N'System Administrator', N'WORKFLOW_OPERATIONS_EDIT'),
(N'System Administrator', N'WORKFLOW_OPERATIONS_ADMIN'),
(N'System Administrator', N'WORKFLOW_PROJECTS_VIEW'),
(N'System Administrator', N'WORKFLOW_PROJECTS_CREATE'),
(N'System Administrator', N'WORKFLOW_PROJECTS_EDIT'),
(N'System Administrator', N'WORKFLOW_PROJECTS_DELETE'),
(N'System Administrator', N'WORKFLOW_REQUIREMENTS_VIEW'),
(N'System Administrator', N'WORKFLOW_REQUIREMENTS_CREATE'),
(N'System Administrator', N'WORKFLOW_REQUIREMENTS_EDIT'),
(N'System Administrator', N'WORKFLOW_REQUIREMENTS_DELETE'),
(N'System Administrator', N'METRICS_VIEW'),

(N'Security Administrator', N'USERS_VIEW'),
(N'Security Administrator', N'USERS_EDIT'),
(N'Security Administrator', N'USERS_ADMIN'),
(N'Security Administrator', N'ROLES_VIEW'),
(N'Security Administrator', N'ROLES_ADMIN'),
(N'Security Administrator', N'AUDIT_VIEW'),
(N'Security Administrator', N'SESSION_VIEW'),
(N'Security Administrator', N'SESSION_ADMIN'),

(N'Configuration Administrator', N'BASE_CONFIG_VIEW'),
(N'Configuration Administrator', N'BASE_CONFIG_EDIT'),
(N'Configuration Administrator', N'SEGMENTS_VIEW'),
(N'Configuration Administrator', N'SEGMENTS_EDIT'),
(N'Configuration Administrator', N'SEGMENT_VALUES_IMPORT'),
(N'Configuration Administrator', N'FIN_CONFIG_VIEW'),
(N'Configuration Administrator', N'FIN_CONFIG_EDIT'),
(N'Configuration Administrator', N'CALC_ADMIN'),
(N'Configuration Administrator', N'SYSSETTINGS_VIEW'),
(N'Configuration Administrator', N'SYSSETTINGS_EDIT'),
(N'Configuration Administrator', N'SYSSETTINGS_ADMIN'),
(N'Configuration Administrator', N'WORKFLOW_VIEW'),
(N'Configuration Administrator', N'WORKFLOW_EDIT'),
(N'Configuration Administrator', N'WORKFLOW_ADMIN'),
(N'Configuration Administrator', N'STRATEGY_CONFIG_EDIT'),
(N'Configuration Administrator', N'STRATEGY_SEGMENT_PUBLISH'),

(N'Base Configuration Administrator', N'BASE_CONFIG_VIEW'),
(N'Base Configuration Administrator', N'BASE_CONFIG_EDIT'),
(N'Base Configuration Administrator', N'SEGMENTS_VIEW'),
(N'Base Configuration Administrator', N'SEGMENTS_EDIT'),
(N'Base Configuration Administrator', N'SEGMENT_VALUES_IMPORT'),

(N'Financial Configuration Administrator', N'FIN_CONFIG_VIEW'),
(N'Financial Configuration Administrator', N'FIN_CONFIG_EDIT'),
(N'Financial Configuration Administrator', N'CALC_ADMIN'),

(N'Organisation / COA Administrator', N'BASE_CONFIG_VIEW'),
(N'Organisation / COA Administrator', N'BASE_CONFIG_EDIT'),
(N'Organisation / COA Administrator', N'SEGMENTS_VIEW'),
(N'Organisation / COA Administrator', N'SEGMENTS_EDIT'),
(N'Organisation / COA Administrator', N'SEGMENT_VALUES_IMPORT'),
(N'Organisation / COA Administrator', N'DATAOBJECTCODES_VIEW'),
(N'Organisation / COA Administrator', N'DATAOBJECTCODES_EDIT'),
(N'Organisation / COA Administrator', N'DATAOBJECTCODES_IMPORT'),
(N'Organisation / COA Administrator', N'DATAOBJECTCODES_ACCESS_ADMIN'),
(N'Organisation / COA Administrator', N'DATAOBJECTCODES_ADMIN'),

(N'Strategy Configuration Administrator', N'STRATEGY_CONFIG_EDIT'),
(N'Strategy Configuration Administrator', N'STRATEGY_SEGMENT_PUBLISH'),

(N'Strategic Framework User', N'STRATEGY_VIEW'),
(N'Strategic Framework User', N'STRATEGY_SETUP_EDIT'),
(N'Strategic Framework User', N'STRATEGY_PERFORMANCE_EDIT'),
(N'Strategic Framework User', N'STRATEGY_DELIVERY_EDIT'),
(N'Strategic Framework User', N'STRATEGY_GOVERNANCE_EDIT'),
(N'Strategic Framework User', N'STRATEGY_FISCAL_EDIT'),

(N'Strategic Framework Reviewer', N'STRATEGY_SUBMISSION_REVIEW'),

(N'Strategic Framework Approver', N'STRATEGY_SUBMISSION_REVIEW'),
(N'Strategic Framework Approver', N'STRATEGY_SUBMISSION_APPROVE'),

(N'Strategic Framework Reporting User', N'STRATEGY_REPORT_VIEW'),

(N'Strategic Framework Administrator', N'STRATEGY_VIEW'),
(N'Strategic Framework Administrator', N'STRATEGY_SETUP_EDIT'),
(N'Strategic Framework Administrator', N'STRATEGY_PERFORMANCE_EDIT'),
(N'Strategic Framework Administrator', N'STRATEGY_DELIVERY_EDIT'),
(N'Strategic Framework Administrator', N'STRATEGY_GOVERNANCE_EDIT'),
(N'Strategic Framework Administrator', N'STRATEGY_FISCAL_EDIT'),
(N'Strategic Framework Administrator', N'STRATEGY_WORKFLOW_EDIT'),

(N'Budget Strategy User', N'STRATEGY_VIEW'),
(N'Budget Strategy User', N'STRATEGY_SETUP_EDIT'),
(N'Budget Strategy User', N'STRATEGY_PERFORMANCE_EDIT'),
(N'Budget Strategy User', N'STRATEGY_DELIVERY_EDIT'),
(N'Budget Strategy User', N'STRATEGY_GOVERNANCE_EDIT'),
(N'Budget Strategy User', N'STRATEGY_FISCAL_EDIT'),

(N'Budget Strategy Reviewer', N'STRATEGY_SUBMISSION_REVIEW'),

(N'Budget Strategy Approver', N'STRATEGY_SUBMISSION_REVIEW'),
(N'Budget Strategy Approver', N'STRATEGY_SUBMISSION_APPROVE'),

(N'Budget Strategy Administrator', N'STRATEGY_VIEW'),
(N'Budget Strategy Administrator', N'STRATEGY_SETUP_EDIT'),
(N'Budget Strategy Administrator', N'STRATEGY_PERFORMANCE_EDIT'),
(N'Budget Strategy Administrator', N'STRATEGY_DELIVERY_EDIT'),
(N'Budget Strategy Administrator', N'STRATEGY_GOVERNANCE_EDIT'),
(N'Budget Strategy Administrator', N'STRATEGY_FISCAL_EDIT'),
(N'Budget Strategy Administrator', N'STRATEGY_WORKFLOW_EDIT'),
(N'Budget Strategy Administrator', N'STRATEGY_SUBMISSION_PREPARE'),
(N'Budget Strategy Administrator', N'STRATEGY_SUBMISSION_REVIEW'),
(N'Budget Strategy Administrator', N'STRATEGY_SUBMISSION_APPROVE'),

(N'Budget Planning User', N'ESTIMATES_VIEW'),
(N'Budget Planning User', N'ESTIMATES_EDIT'),
(N'Budget Planning User', N'RATES_VIEW'),

(N'Budget Planning Administrator', N'ESTIMATES_VIEW'),
(N'Budget Planning Administrator', N'ESTIMATES_EDIT'),
(N'Budget Planning Administrator', N'RATES_VIEW'),
(N'Budget Planning Administrator', N'RATES_EDIT'),
(N'Budget Planning Administrator', N'RATES_CREATE'),

(N'Budget Submission User', N'ESTIMATES_VIEW'),
(N'Budget Submission User', N'ESTIMATES_EDIT'),
(N'Budget Submission User', N'RATES_VIEW'),
(N'Budget Submission User', N'RATES_EDIT'),
(N'Budget Submission User', N'RATES_CREATE'),
(N'Budget Submission User', N'STRATEGY_SUBMISSION_PREPARE'),

(N'Budget Submission Reviewer', N'STRATEGY_SUBMISSION_REVIEW'),

(N'Budget Submission Approver', N'STRATEGY_SUBMISSION_APPROVE'),
(N'Budget Submission Approver', N'STRATEGY_PUBLISH'),

(N'Budget Submission Administrator', N'ESTIMATES_VIEW'),
(N'Budget Submission Administrator', N'ESTIMATES_EDIT'),
(N'Budget Submission Administrator', N'RATES_VIEW'),
(N'Budget Submission Administrator', N'RATES_EDIT'),
(N'Budget Submission Administrator', N'RATES_CREATE'),
(N'Budget Submission Administrator', N'STRATEGY_SUBMISSION_PREPARE'),
(N'Budget Submission Administrator', N'STRATEGY_SUBMISSION_REVIEW'),
(N'Budget Submission Administrator', N'STRATEGY_SUBMISSION_APPROVE'),
(N'Budget Submission Administrator', N'STRATEGY_PUBLISH'),

(N'Budget Execution User', N'BUDGET_EXECUTION_VIEW'),
(N'Budget Execution User', N'BUDGET_EXECUTION_EDIT'),

(N'Budget Execution Reviewer', N'BUDGET_EXECUTION_VIEW'),
(N'Budget Execution Reviewer', N'BUDGET_EXECUTION_REVIEW'),

(N'Budget Execution Administrator', N'BUDGET_EXECUTION_VIEW'),
(N'Budget Execution Administrator', N'BUDGET_EXECUTION_EDIT'),
(N'Budget Execution Administrator', N'BUDGET_EXECUTION_REVIEW'),
(N'Budget Execution Administrator', N'BUDGET_EXECUTION_ADMIN'),

(N'Workflow Operations User', N'WORKFLOW_OPERATIONS_VIEW'),
(N'Workflow Operations User', N'WORKFLOW_PROJECTS_VIEW'),
(N'Workflow Operations User', N'WORKFLOW_REQUIREMENTS_VIEW'),

(N'Workflow Operations Editor', N'WORKFLOW_OPERATIONS_VIEW'),
(N'Workflow Operations Editor', N'WORKFLOW_OPERATIONS_EDIT'),
(N'Workflow Operations Editor', N'WORKFLOW_PROJECTS_VIEW'),
(N'Workflow Operations Editor', N'WORKFLOW_PROJECTS_CREATE'),
(N'Workflow Operations Editor', N'WORKFLOW_PROJECTS_EDIT'),
(N'Workflow Operations Editor', N'WORKFLOW_REQUIREMENTS_VIEW'),
(N'Workflow Operations Editor', N'WORKFLOW_REQUIREMENTS_CREATE'),
(N'Workflow Operations Editor', N'WORKFLOW_REQUIREMENTS_EDIT'),

(N'Workflow Operations Administrator', N'WORKFLOW_OPERATIONS_VIEW'),
(N'Workflow Operations Administrator', N'WORKFLOW_OPERATIONS_EDIT'),
(N'Workflow Operations Administrator', N'WORKFLOW_OPERATIONS_ADMIN'),
(N'Workflow Operations Administrator', N'WORKFLOW_PROJECTS_VIEW'),
(N'Workflow Operations Administrator', N'WORKFLOW_PROJECTS_CREATE'),
(N'Workflow Operations Administrator', N'WORKFLOW_PROJECTS_EDIT'),
(N'Workflow Operations Administrator', N'WORKFLOW_PROJECTS_DELETE'),
(N'Workflow Operations Administrator', N'WORKFLOW_REQUIREMENTS_VIEW'),
(N'Workflow Operations Administrator', N'WORKFLOW_REQUIREMENTS_CREATE'),
(N'Workflow Operations Administrator', N'WORKFLOW_REQUIREMENTS_EDIT'),
(N'Workflow Operations Administrator', N'WORKFLOW_REQUIREMENTS_DELETE'),

(N'Reporting User', N'STRATEGY_REPORT_VIEW'),

(N'Reporting Administrator', N'STRATEGY_REPORT_VIEW'),
(N'Reporting Administrator', N'ROLES_VIEW'),

(N'Analytics User', N'ANALYTICS_VIEW'),

(N'Analytics Administrator', N'ANALYTICS_VIEW'),

(N'Dashboard User', N'DASHBOARD_VIEW'),

(N'Dashboard Administrator', N'DASHBOARD_VIEW'),
(N'Dashboard Administrator', N'DASHBOARD_ADMIN');

INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
SELECT DISTINCT r.RoleID, p.PermissionID
FROM #RolePerm rp
INNER JOIN dbo.tblRoles r
    ON r.RoleName = rp.RoleName
INNER JOIN dbo.tblPermissions p
    ON p.PermissionCode = rp.PermissionCode
LEFT JOIN dbo.tblRolePermissions existing
    ON existing.RoleID = r.RoleID
   AND existing.PermissionID = p.PermissionID
WHERE existing.RoleID IS NULL;

SELECT
    r.RoleName,
    p.PermissionCode
FROM dbo.tblRoles r
LEFT JOIN dbo.tblRolePermissions rp
    ON rp.RoleID = r.RoleID
LEFT JOIN dbo.tblPermissions p
    ON p.PermissionID = rp.PermissionID
WHERE r.RoleName IN (SELECT RoleName FROM #ManagedRoles)
ORDER BY r.RoleName, p.PermissionCode;
GO
