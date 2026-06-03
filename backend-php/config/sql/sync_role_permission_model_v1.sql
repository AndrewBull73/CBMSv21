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
(N'BASE_CONFIG_VIEW', N'View base configuration readiness and maintenance screens'),
(N'BASE_CONFIG_EDIT', N'Edit base configuration records and imports'),
(N'FIN_CONFIG_VIEW', N'View financial and calculation configuration screens'),
(N'FIN_CONFIG_EDIT', N'Edit financial and calculation configuration records'),
(N'CALC_ADMIN', N'Run advanced calculation, scenario, and recalculation administration'),
(N'ESTIMATES_VIEW', N'View transaction input and estimate work areas'),
(N'ESTIMATES_EDIT', N'Edit transaction input and estimate work areas'),
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
(N'DIAG_VIEW', N'View diagnostics screens'),
(N'SESSION_ADMIN', N'Force logout and administer active sessions');

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

IF OBJECT_ID('tempdb..#RolePerm') IS NOT NULL DROP TABLE #RolePerm;
CREATE TABLE #RolePerm
(
    RoleName NVARCHAR(255) NOT NULL,
    PermissionCode NVARCHAR(100) NOT NULL
);

INSERT INTO #RolePerm (RoleName, PermissionCode) VALUES
(N'Admin', N'BASE_CONFIG_VIEW'),
(N'Admin', N'BASE_CONFIG_EDIT'),
(N'Admin', N'FIN_CONFIG_VIEW'),
(N'Admin', N'FIN_CONFIG_EDIT'),
(N'Admin', N'CALC_ADMIN'),
(N'Admin', N'ESTIMATES_VIEW'),
(N'Admin', N'ESTIMATES_EDIT'),
(N'Admin', N'STRATEGY_VIEW'),
(N'Admin', N'STRATEGY_CONFIG_EDIT'),
(N'Admin', N'STRATEGY_SETUP_EDIT'),
(N'Admin', N'STRATEGY_PERFORMANCE_EDIT'),
(N'Admin', N'STRATEGY_DELIVERY_EDIT'),
(N'Admin', N'STRATEGY_GOVERNANCE_EDIT'),
(N'Admin', N'STRATEGY_FISCAL_EDIT'),
(N'Admin', N'STRATEGY_REPORT_VIEW'),
(N'Admin', N'STRATEGY_WORKFLOW_EDIT'),
(N'Admin', N'STRATEGY_SUBMISSION_PREPARE'),
(N'Admin', N'STRATEGY_SUBMISSION_REVIEW'),
(N'Admin', N'STRATEGY_SUBMISSION_APPROVE'),
(N'Admin', N'STRATEGY_PUBLISH'),
(N'Admin', N'DIAG_VIEW'),
(N'Admin', N'SESSION_ADMIN'),

(N'SysAdmin', N'BASE_CONFIG_VIEW'),
(N'SysAdmin', N'BASE_CONFIG_EDIT'),
(N'SysAdmin', N'FIN_CONFIG_VIEW'),
(N'SysAdmin', N'FIN_CONFIG_EDIT'),
(N'SysAdmin', N'CALC_ADMIN'),
(N'SysAdmin', N'ESTIMATES_VIEW'),
(N'SysAdmin', N'ESTIMATES_EDIT'),
(N'SysAdmin', N'STRATEGY_VIEW'),
(N'SysAdmin', N'STRATEGY_CONFIG_EDIT'),
(N'SysAdmin', N'STRATEGY_SETUP_EDIT'),
(N'SysAdmin', N'STRATEGY_PERFORMANCE_EDIT'),
(N'SysAdmin', N'STRATEGY_DELIVERY_EDIT'),
(N'SysAdmin', N'STRATEGY_GOVERNANCE_EDIT'),
(N'SysAdmin', N'STRATEGY_FISCAL_EDIT'),
(N'SysAdmin', N'STRATEGY_REPORT_VIEW'),
(N'SysAdmin', N'STRATEGY_WORKFLOW_EDIT'),
(N'SysAdmin', N'STRATEGY_SUBMISSION_PREPARE'),
(N'SysAdmin', N'STRATEGY_SUBMISSION_REVIEW'),
(N'SysAdmin', N'STRATEGY_SUBMISSION_APPROVE'),
(N'SysAdmin', N'STRATEGY_PUBLISH'),
(N'SysAdmin', N'DIAG_VIEW'),
(N'SysAdmin', N'SESSION_ADMIN'),

(N'Config', N'BASE_CONFIG_VIEW'),
(N'Config', N'BASE_CONFIG_EDIT'),
(N'Config', N'SYSSETTINGS_VIEW'),
(N'Config', N'SYSSETTINGS_EDIT'),
(N'Config', N'FIN_CONFIG_VIEW'),
(N'Config', N'FIN_CONFIG_EDIT'),
(N'Config', N'STRATEGY_VIEW'),
(N'Config', N'STRATEGY_CONFIG_EDIT'),
(N'Config', N'STRATEGY_PUBLISH'),

(N'Strategy', N'STRATEGY_VIEW'),
(N'Strategy', N'STRATEGY_CONFIG_EDIT'),
(N'Strategy', N'STRATEGY_SETUP_EDIT'),
(N'Strategy', N'STRATEGY_PERFORMANCE_EDIT'),
(N'Strategy', N'STRATEGY_DELIVERY_EDIT'),
(N'Strategy', N'STRATEGY_GOVERNANCE_EDIT'),
(N'Strategy', N'STRATEGY_FISCAL_EDIT'),
(N'Strategy', N'STRATEGY_REPORT_VIEW'),
(N'Strategy', N'STRATEGY_WORKFLOW_EDIT'),
(N'Strategy', N'STRATEGY_SUBMISSION_PREPARE'),
(N'Strategy', N'STRATEGY_SUBMISSION_REVIEW'),
(N'Strategy', N'STRATEGY_SUBMISSION_APPROVE'),
(N'Strategy', N'STRATEGY_PUBLISH'),

(N'Reports', N'STRATEGY_VIEW'),
(N'Reports', N'STRATEGY_REPORT_VIEW'),

(N'Analytics', N'ANALYTICS_VIEW'),

(N'Estimates', N'ESTIMATES_VIEW'),
(N'Estimates', N'ESTIMATES_EDIT'),

(N'FiscalFramework', N'STRATEGY_VIEW'),
(N'FiscalFramework', N'STRATEGY_FISCAL_EDIT'),
(N'FiscalFramework', N'STRATEGY_REPORT_VIEW'),

(N'Manager', N'STRATEGY_VIEW'),
(N'Manager', N'STRATEGY_REPORT_VIEW'),

(N'DataObjects', N'DATAOBJECTCODES_ADMIN');

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
WHERE r.RoleName IN (N'Admin', N'SysAdmin', N'Config', N'Strategy', N'Reports', N'Analytics', N'Estimates', N'FiscalFramework', N'Manager', N'DataObjects', N'Workflow')
ORDER BY r.RoleName, p.PermissionCode;
GO
