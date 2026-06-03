USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

IF OBJECT_ID('tempdb..#FunctionalRoles') IS NOT NULL DROP TABLE #FunctionalRoles;
CREATE TABLE #FunctionalRoles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY,
    FunctionalArea NVARCHAR(100) NOT NULL
);

INSERT INTO #FunctionalRoles (RoleName, FunctionalArea) VALUES
(N'Super Admin', N'Platform'),
(N'System Administrator', N'Platform'),
(N'Configuration Administrator', N'Configuration'),
(N'Base Configuration Administrator', N'Configuration'),
(N'Financial Configuration Administrator', N'Configuration'),
(N'Strategy Configuration Administrator', N'Configuration'),
(N'Strategic Framework User', N'Strategic Framework'),
(N'Strategic Framework Reviewer', N'Strategic Framework'),
(N'Strategic Framework Approver', N'Strategic Framework'),
(N'Strategic Framework Reporting User', N'Strategic Framework'),
(N'Strategic Framework Administrator', N'Strategic Framework'),
(N'Budget Submission User', N'Budget Submission'),
(N'Budget Submission Reviewer', N'Budget Submission'),
(N'Budget Submission Approver', N'Budget Submission'),
(N'Budget Submission Administrator', N'Budget Submission'),
(N'Budget Execution User', N'Budget Execution'),
(N'Budget Execution Reviewer', N'Budget Execution'),
(N'Budget Execution Administrator', N'Budget Execution'),
(N'Reporting User', N'Reporting'),
(N'Reporting Administrator', N'Reporting'),
(N'Analytics User', N'Analytics'),
(N'Analytics Administrator', N'Analytics'),
(N'Dashboard User', N'Dashboards'),
(N'Dashboard Administrator', N'Dashboards');

IF OBJECT_ID('tempdb..#LegacyRoles') IS NOT NULL DROP TABLE #LegacyRoles;
CREATE TABLE #LegacyRoles
(
    RoleName NVARCHAR(255) NOT NULL PRIMARY KEY
);

INSERT INTO #LegacyRoles (RoleName) VALUES
(N'Admin'),
(N'SysAdmin'),
(N'Config'),
(N'Strategy'),
(N'Reports'),
(N'Analytics'),
(N'Estimates'),
(N'FiscalFramework'),
(N'Manager'),
(N'DataObjects'),
(N'Workflow'),
(N'Execution'),
(N'Test Role'),
(N'User');

;WITH UserRoleRows AS
(
    SELECT
        u.UserID,
        COALESCE(NULLIF(LTRIM(RTRIM(u.Username)), N''), NULLIF(LTRIM(RTRIM(u.Email)), N''), CAST(u.UserID AS NVARCHAR(50))) AS UserLabel,
        r.RoleName,
        CASE WHEN fr.RoleName IS NOT NULL THEN 1 ELSE 0 END AS IsFunctionalRole,
        CASE WHEN lr.RoleName IS NOT NULL THEN 1 ELSE 0 END AS IsLegacyRole,
        fr.FunctionalArea
    FROM dbo.tblUsers u
    INNER JOIN dbo.tblUserRoles ur
        ON ur.UserID = u.UserID
    INNER JOIN dbo.tblRoles r
        ON r.RoleID = ur.RoleID
    LEFT JOIN #FunctionalRoles fr
        ON fr.RoleName = r.RoleName
    LEFT JOIN #LegacyRoles lr
        ON lr.RoleName = r.RoleName
),
FunctionalAreaRows AS
(
    SELECT DISTINCT
        urr.UserID,
        urr.FunctionalArea
    FROM UserRoleRows urr
    WHERE urr.IsFunctionalRole = 1
      AND urr.FunctionalArea IS NOT NULL
),
FunctionalAreaSummary AS
(
    SELECT
        far.UserID,
        STRING_AGG(far.FunctionalArea, N', ') WITHIN GROUP (ORDER BY far.FunctionalArea) AS FunctionalAreas
    FROM FunctionalAreaRows far
    GROUP BY far.UserID
)
SELECT
    urr.UserID,
    urr.UserLabel,
    STRING_AGG(CASE WHEN urr.IsLegacyRole = 1 THEN urr.RoleName END, N', ') WITHIN GROUP (ORDER BY urr.RoleName) AS LegacyRoles,
    STRING_AGG(CASE WHEN urr.IsFunctionalRole = 1 THEN urr.RoleName END, N', ') WITHIN GROUP (ORDER BY urr.RoleName) AS FunctionalRoles,
    fas.FunctionalAreas,
    CASE
        WHEN MAX(CASE WHEN urr.IsFunctionalRole = 1 THEN 1 ELSE 0 END) = 0 THEN N'NO_FUNCTIONAL_ROLE'
        WHEN MAX(CASE WHEN urr.IsLegacyRole = 1 THEN 1 ELSE 0 END) = 1 THEN N'MIXED_TRANSITION'
        ELSE N'FUNCTIONAL_ONLY'
    END AS TransitionStatus
FROM UserRoleRows urr
LEFT JOIN FunctionalAreaSummary fas
    ON fas.UserID = urr.UserID
GROUP BY
    urr.UserID,
    urr.UserLabel,
    fas.FunctionalAreas
ORDER BY
    CASE
        WHEN MAX(CASE WHEN urr.IsFunctionalRole = 1 THEN 1 ELSE 0 END) = 0 THEN 0
        WHEN MAX(CASE WHEN urr.IsLegacyRole = 1 THEN 1 ELSE 0 END) = 1 THEN 1
        ELSE 2
    END,
    urr.UserLabel;

SELECT
    fr.FunctionalArea,
    fr.RoleName,
    COUNT(ur.UserID) AS AssignedUsers
FROM #FunctionalRoles fr
LEFT JOIN dbo.tblRoles r
    ON r.RoleName = fr.RoleName
LEFT JOIN dbo.tblUserRoles ur
    ON ur.RoleID = r.RoleID
GROUP BY
    fr.FunctionalArea,
    fr.RoleName
ORDER BY
    fr.FunctionalArea,
    fr.RoleName;

SELECT
    lr.RoleName AS LegacyRole,
    COUNT(ur.UserID) AS AssignedUsers
FROM #LegacyRoles lr
LEFT JOIN dbo.tblRoles r
    ON r.RoleName = lr.RoleName
LEFT JOIN dbo.tblUserRoles ur
    ON ur.RoleID = r.RoleID
GROUP BY
    lr.RoleName
ORDER BY
    lr.RoleName;
GO
