USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

IF OBJECT_ID('tempdb..#LegacyToFunctional') IS NOT NULL DROP TABLE #LegacyToFunctional;
CREATE TABLE #LegacyToFunctional
(
    LegacyRoleName NVARCHAR(255) NOT NULL,
    FunctionalRoleName NVARCHAR(255) NOT NULL,
    RecommendationPriority INT NOT NULL
);

INSERT INTO #LegacyToFunctional (LegacyRoleName, FunctionalRoleName, RecommendationPriority) VALUES
(N'Admin', N'System Administrator', 10),
(N'SysAdmin', N'System Administrator', 10),
(N'Config', N'Configuration Administrator', 10),
(N'Strategy', N'Strategic Framework User', 10),
(N'Strategy', N'Budget Submission User', 20),
(N'Reports', N'Reporting User', 10),
(N'Analytics', N'Analytics User', 10),
(N'Analytics', N'Dashboard User', 20),
(N'Estimates', N'Budget Submission User', 10),
(N'FiscalFramework', N'Strategic Framework User', 10),
(N'FiscalFramework', N'Reporting User', 20),
(N'Manager', N'Reporting User', 10),
(N'Manager', N'Strategic Framework Approver', 20),
(N'DataObjects', N'System Administrator', 10),
(N'Workflow', N'System Administrator', 10),
(N'Workflow', N'Strategic Framework Reviewer', 20),
(N'Execution', N'Budget Execution User', 10);

;WITH CurrentRoles AS
(
    SELECT
        u.UserID,
        COALESCE(NULLIF(LTRIM(RTRIM(u.Username)), N''), NULLIF(LTRIM(RTRIM(u.Email)), N''), CAST(u.UserID AS NVARCHAR(50))) AS UserLabel,
        r.RoleName
    FROM dbo.tblUsers u
    INNER JOIN dbo.tblUserRoles ur
        ON ur.UserID = u.UserID
    INNER JOIN dbo.tblRoles r
        ON r.RoleID = ur.RoleID
),
Recommended AS
(
    SELECT DISTINCT
        cr.UserID,
        cr.UserLabel,
        map.FunctionalRoleName,
        map.RecommendationPriority
    FROM CurrentRoles cr
    INNER JOIN #LegacyToFunctional map
        ON map.LegacyRoleName = cr.RoleName
),
ExistingFunctional AS
(
    SELECT
        cr.UserID,
        cr.RoleName AS FunctionalRoleName
    FROM CurrentRoles cr
    WHERE cr.RoleName IN
    (
        N'System Administrator',
        N'Configuration Administrator',
        N'Base Configuration Administrator',
        N'Financial Configuration Administrator',
        N'Strategy Configuration Administrator',
        N'Strategic Framework User',
        N'Strategic Framework Reviewer',
        N'Strategic Framework Approver',
        N'Strategic Framework Reporting User',
        N'Strategic Framework Administrator',
        N'Budget Submission User',
        N'Budget Submission Reviewer',
        N'Budget Submission Approver',
        N'Budget Submission Administrator',
        N'Budget Execution User',
        N'Budget Execution Reviewer',
        N'Budget Execution Administrator',
        N'Reporting User',
        N'Reporting Administrator',
        N'Analytics User',
        N'Analytics Administrator',
        N'Dashboard User',
        N'Dashboard Administrator'
    )
)
SELECT
    r.UserID,
    r.UserLabel,
    r.FunctionalRoleName AS RecommendedRole,
    r.RecommendationPriority,
    CASE WHEN ef.FunctionalRoleName IS NULL THEN N'ADD' ELSE N'ALREADY_ASSIGNED' END AS RecommendationAction
FROM Recommended r
LEFT JOIN ExistingFunctional ef
    ON ef.UserID = r.UserID
   AND ef.FunctionalRoleName = r.FunctionalRoleName
ORDER BY
    r.UserLabel,
    r.RecommendationPriority,
    r.FunctionalRoleName;
GO
