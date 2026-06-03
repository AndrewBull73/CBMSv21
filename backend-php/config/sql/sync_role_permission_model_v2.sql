USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

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
(N'STRATEGY_PUBLISH', N'Publish approved Strategy submissions and segment publications'),
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
(N'METRICS_VIEW', N'View metrics and monitoring screens');

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
CREATE TABLE #Roles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #Roles (RoleName) VALUES
(N'Super Admin'),
(N'Configuration Administrator'),
(N'System Administrator'),
(N'Data Input User'),
(N'Reporting User'),
(N'Analytics User'),
(N'Workflow Reviewer'),
(N'Workflow Approver');

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

IF OBJECT_ID('tempdb..#RolePerm') IS NOT NULL DROP TABLE #RolePerm;
CREATE TABLE #RolePerm
(
    RoleName NVARCHAR(255) NOT NULL,
    PermissionCode NVARCHAR(100) NOT NULL
);

INSERT INTO #RolePerm (RoleName, PermissionCode) VALUES
(N'Super Admin', N'ADMIN_ALL'),
(N'Super Admin', N'SYSADMIN'),
(N'Super Admin', N'BASE_CONFIG_VIEW'),
(N'Super Admin', N'BASE_CONFIG_EDIT'),
(N'Super Admin', N'FIN_CONFIG_VIEW'),
(N'Super Admin', N'FIN_CONFIG_EDIT'),
(N'Super Admin', N'CALC_ADMIN'),
(N'Super Admin', N'ESTIMATES_VIEW'),
(N'Super Admin', N'ESTIMATES_EDIT'),
(N'Super Admin', N'RATES_VIEW'),
(N'Super Admin', N'RATES_EDIT'),
(N'Super Admin', N'RATES_CREATE'),
(N'Super Admin', N'STRATEGY_VIEW'),
(N'Super Admin', N'STRATEGY_CONFIG_EDIT'),
(N'Super Admin', N'STRATEGY_SETUP_EDIT'),
(N'Super Admin', N'STRATEGY_PERFORMANCE_EDIT'),
(N'Super Admin', N'STRATEGY_DELIVERY_EDIT'),
(N'Super Admin', N'STRATEGY_GOVERNANCE_EDIT'),
(N'Super Admin', N'STRATEGY_FISCAL_EDIT'),
(N'Super Admin', N'STRATEGY_REPORT_VIEW'),
(N'Super Admin', N'STRATEGY_WORKFLOW_EDIT'),
(N'Super Admin', N'STRATEGY_SUBMISSION_PREPARE'),
(N'Super Admin', N'STRATEGY_SUBMISSION_REVIEW'),
(N'Super Admin', N'STRATEGY_SUBMISSION_APPROVE'),
(N'Super Admin', N'STRATEGY_PUBLISH'),
(N'Super Admin', N'ANALYTICS_VIEW'),
(N'Super Admin', N'USERS_VIEW'),
(N'Super Admin', N'USERS_EDIT'),
(N'Super Admin', N'USERS_ADMIN'),
(N'Super Admin', N'ROLES_VIEW'),
(N'Super Admin', N'ROLES_ADMIN'),
(N'Super Admin', N'AUDIT_VIEW'),
(N'Super Admin', N'HEALTH_VIEW'),
(N'Super Admin', N'SESSION_VIEW'),
(N'Super Admin', N'SESSION_ADMIN'),
(N'Super Admin', N'DIAG_VIEW'),
(N'Super Admin', N'LOGS_VIEW'),
(N'Super Admin', N'ERRORLOG_VIEW'),
(N'Super Admin', N'ERRORLOG_ADMIN'),
(N'Super Admin', N'DATAOBJECTCODES_ADMIN'),
(N'Super Admin', N'SYSSETTINGS_VIEW'),
(N'Super Admin', N'SYSSETTINGS_EDIT'),
(N'Super Admin', N'SYSSETTINGS_ADMIN'),
(N'Super Admin', N'WORKFLOW_VIEW'),
(N'Super Admin', N'WORKFLOW_EDIT'),
(N'Super Admin', N'WORKFLOW_ADMIN'),
(N'Super Admin', N'METRICS_VIEW'),

(N'Configuration Administrator', N'BASE_CONFIG_VIEW'),
(N'Configuration Administrator', N'BASE_CONFIG_EDIT'),
(N'Configuration Administrator', N'FIN_CONFIG_VIEW'),
(N'Configuration Administrator', N'FIN_CONFIG_EDIT'),
(N'Configuration Administrator', N'CALC_ADMIN'),
(N'Configuration Administrator', N'SYSSETTINGS_VIEW'),
(N'Configuration Administrator', N'SYSSETTINGS_EDIT'),
(N'Configuration Administrator', N'SYSSETTINGS_ADMIN'),
(N'Configuration Administrator', N'STRATEGY_CONFIG_EDIT'),
(N'Configuration Administrator', N'STRATEGY_PUBLISH'),

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
(N'System Administrator', N'DATAOBJECTCODES_ADMIN'),
(N'System Administrator', N'WORKFLOW_VIEW'),
(N'System Administrator', N'WORKFLOW_EDIT'),
(N'System Administrator', N'WORKFLOW_ADMIN'),
(N'System Administrator', N'METRICS_VIEW'),

(N'Data Input User', N'ESTIMATES_VIEW'),
(N'Data Input User', N'ESTIMATES_EDIT'),
(N'Data Input User', N'RATES_VIEW'),
(N'Data Input User', N'STRATEGY_VIEW'),
(N'Data Input User', N'STRATEGY_SETUP_EDIT'),
(N'Data Input User', N'STRATEGY_PERFORMANCE_EDIT'),
(N'Data Input User', N'STRATEGY_DELIVERY_EDIT'),
(N'Data Input User', N'STRATEGY_GOVERNANCE_EDIT'),
(N'Data Input User', N'STRATEGY_FISCAL_EDIT'),
(N'Data Input User', N'STRATEGY_REPORT_VIEW'),
(N'Data Input User', N'STRATEGY_SUBMISSION_PREPARE'),

(N'Reporting User', N'STRATEGY_VIEW'),
(N'Reporting User', N'STRATEGY_REPORT_VIEW'),

(N'Analytics User', N'ANALYTICS_VIEW'),

(N'Workflow Reviewer', N'STRATEGY_VIEW'),
(N'Workflow Reviewer', N'STRATEGY_REPORT_VIEW'),
(N'Workflow Reviewer', N'STRATEGY_SUBMISSION_REVIEW'),

(N'Workflow Approver', N'STRATEGY_VIEW'),
(N'Workflow Approver', N'STRATEGY_REPORT_VIEW'),
(N'Workflow Approver', N'STRATEGY_SUBMISSION_APPROVE'),
(N'Workflow Approver', N'STRATEGY_PUBLISH');

INSERT INTO dbo.tblRolePermissions (RoleID, PermissionID)
SELECT r.RoleID, p.PermissionID
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
WHERE r.RoleName IN
(
    N'Super Admin',
    N'Configuration Administrator',
    N'System Administrator',
    N'Data Input User',
    N'Reporting User',
    N'Analytics User',
    N'Workflow Reviewer',
    N'Workflow Approver'
)
ORDER BY r.RoleName, p.PermissionCode;

SELECT
    r.RoleName AS LegacyRole,
    COUNT(ur.RoleID) AS AssignedUsers
FROM dbo.tblRoles r
LEFT JOIN dbo.tblUserRoles ur
    ON ur.RoleID = r.RoleID
WHERE r.RoleName IN
(
    N'Admin',
    N'SysAdmin',
    N'Config',
    N'Strategy',
    N'Reports',
    N'Analytics',
    N'Estimates',
    N'FiscalFramework',
    N'Manager',
    N'DataObjects',
    N'Workflow',
    N'Test Role',
    N'User'
)
GROUP BY r.RoleName
ORDER BY r.RoleName;

SELECT
    u.UserID,
    COALESCE(NULLIF(LTRIM(RTRIM(u.Username)), N''), NULLIF(LTRIM(RTRIM(u.Email)), N''), CAST(u.UserID AS NVARCHAR(50))) AS UserLabel,
    STRING_AGG(r.RoleName, N', ') WITHIN GROUP (ORDER BY r.RoleName) AS CurrentRoles
FROM dbo.tblUsers u
INNER JOIN dbo.tblUserRoles ur
    ON ur.UserID = u.UserID
INNER JOIN dbo.tblRoles r
    ON r.RoleID = ur.RoleID
GROUP BY
    u.UserID,
    COALESCE(NULLIF(LTRIM(RTRIM(u.Username)), N''), NULLIF(LTRIM(RTRIM(u.Email)), N''), CAST(u.UserID AS NVARCHAR(50)))
ORDER BY UserLabel;
GO
