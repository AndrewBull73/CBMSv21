/*
Phase 02 / Base Configuration Readiness Verification

Purpose:
- match the expanded Base Configuration Readiness screen
- help confirm the cause of warnings or blockers during INITTEST setup

Usage:
1. Run against CBMSv2_INITTEST.
2. Change @FiscalYearID and @VersionID if the active context is different.
*/

SET NOCOUNT ON;

DECLARE @FiscalYearID INT = 2026;
DECLARE @VersionID INT = 1;

PRINT 'Running Base Configuration Readiness verification against database: ' + DB_NAME();

PRINT '1. Fiscal year and version baseline';

SELECT
    fy.FiscalYearID,
    fy.YearLabel,
    fy.StartDate,
    fy.EndDate,
    fy.IsActive
FROM dbo.tblFiscalYears AS fy
ORDER BY fy.FiscalYearID;

SELECT
    v.FiscalYearID,
    v.VersionID,
    v.VersionLabel,
    v.VersionTypeID,
    v.VersionStatus,
    v.IsActive,
    v.IsDefault
FROM dbo.tblVersions AS v
ORDER BY v.FiscalYearID, v.VersionID;

PRINT '2. Version type baseline';

SELECT
    vt.VersionTypeID,
    vt.VersionTypeCode,
    vt.VersionTypeName,
    vt.ActiveFlag
FROM dbo.tblVersionTypes AS vt
ORDER BY vt.VersionTypeID;

SELECT
    COUNT(*) AS ActiveVersionTypeCount
FROM dbo.tblVersionTypes AS vt
WHERE ISNULL(vt.ActiveFlag, 1) = 1;

PRINT '3. Active fiscal years without exactly one default version';

SELECT
    fy.FiscalYearID,
    fy.YearLabel,
    COUNT(CASE WHEN ISNULL(v.IsActive, 1) = 1 AND ISNULL(v.IsDefault, 0) = 1 THEN 1 END) AS ActiveDefaultVersionCount
FROM dbo.tblFiscalYears AS fy
LEFT JOIN dbo.tblVersions AS v
    ON v.FiscalYearID = fy.FiscalYearID
WHERE ISNULL(fy.IsActive, 1) = 1
GROUP BY
    fy.FiscalYearID,
    fy.YearLabel
HAVING COUNT(CASE WHEN ISNULL(v.IsActive, 1) = 1 AND ISNULL(v.IsDefault, 0) = 1 THEN 1 END) <> 1;

PRINT '4. Default context settings and matching version row';

SELECT
    ss.SettingKey,
    ss.SettingValue
FROM dbo.tblSystemSettings AS ss
WHERE ss.SettingKey IN (
    N'APP_URL',
    N'AUTH_LOGIN_URL',
    N'AUTH_LOGIN_MODE',
    N'AUTH_LOGIN_MAX_ATTEMPTS',
    N'AUTH_LOGIN_LOCKOUT_MIN',
    N'DEFAULT_FISCAL_YEAR',
    N'DEFAULT_VERSION',
    N'SESSION_IDLE_TIMEOUT_SEC',
    N'SESSION_ABSOLUTE_TIMEOUT_MIN'
)
ORDER BY ss.SettingKey;

DECLARE @DefaultFiscalYearID INT = TRY_CAST((
    SELECT TOP (1) SettingValue
    FROM dbo.tblSystemSettings
    WHERE SettingKey = N'DEFAULT_FISCAL_YEAR'
) AS INT);

DECLARE @DefaultVersionID INT = TRY_CAST((
    SELECT TOP (1) SettingValue
    FROM dbo.tblSystemSettings
    WHERE SettingKey = N'DEFAULT_VERSION'
) AS INT);

SELECT
    @DefaultFiscalYearID AS DefaultFiscalYearID,
    @DefaultVersionID AS DefaultVersionID,
    v.VersionLabel,
    v.IsActive,
    v.IsDefault,
    v.VersionStatus
FROM dbo.tblVersions AS v
WHERE v.FiscalYearID = @DefaultFiscalYearID
  AND v.VersionID = @DefaultVersionID;

PRINT '5. Version integrity';

SELECT
    v.FiscalYearID,
    v.VersionID,
    v.VersionLabel,
    v.VersionTypeID,
    vt.VersionTypeCode,
    vt.ActiveFlag AS VersionTypeActiveFlag,
    v.VersionStatus,
    v.BaseFiscalYearID,
    v.BaseVersionID,
    base.VersionLabel AS BaseVersionLabel,
    v.BaseCurrency,
    c.CurrencyName AS BaseCurrencyName,
    c.IsActive AS BaseCurrencyIsActive,
    v.IsActive,
    v.IsDefault
FROM dbo.tblVersions AS v
LEFT JOIN dbo.tblVersionTypes AS vt
    ON vt.VersionTypeID = v.VersionTypeID
LEFT JOIN dbo.tblVersions AS base
    ON base.FiscalYearID = v.BaseFiscalYearID
   AND base.VersionID = v.BaseVersionID
LEFT JOIN dbo.tblCurrencies AS c
    ON c.CurrencyCode = v.BaseCurrency
WHERE NULLIF(LTRIM(RTRIM(ISNULL(v.VersionLabel, N''))), N'') IS NULL
   OR NULLIF(LTRIM(RTRIM(ISNULL(v.VersionStatus, N''))), N'') IS NULL
   OR vt.VersionTypeID IS NULL
   OR ISNULL(vt.ActiveFlag, 1) = 0
   OR (v.BaseFiscalYearID IS NULL AND v.BaseVersionID IS NOT NULL)
   OR (v.BaseFiscalYearID IS NOT NULL AND v.BaseVersionID IS NULL)
   OR (v.BaseFiscalYearID IS NOT NULL AND v.BaseVersionID IS NOT NULL AND base.VersionID IS NULL)
   OR (v.BaseFiscalYearID = v.FiscalYearID AND v.BaseVersionID = v.VersionID)
   OR (
        NULLIF(LTRIM(RTRIM(ISNULL(v.BaseCurrency, N''))), N'') IS NOT NULL
        AND (
            c.CurrencyCode IS NULL
            OR ISNULL(c.IsActive, 1) = 0
        )
   )
   OR (ISNULL(v.IsDefault, 0) = 1 AND ISNULL(v.IsActive, 1) = 0)
ORDER BY v.FiscalYearID, v.VersionID;

PRINT '6. Currency baseline';

SELECT
    c.CurrencyCode,
    c.CurrencyName,
    c.CurrencySymbol,
    c.DecimalPlaces,
    c.IsSystemDefault,
    c.IsActive,
    c.SortOrder
FROM dbo.tblCurrencies AS c
ORDER BY c.SortOrder, c.CurrencyCode;

SELECT
    COUNT(*) AS ActiveCurrencyCount,
    SUM(CASE WHEN ISNULL(c.IsSystemDefault, 0) = 1 THEN 1 ELSE 0 END) AS SystemDefaultCurrencyCount
FROM dbo.tblCurrencies AS c
WHERE ISNULL(c.IsActive, 1) = 1;

SELECT
    v.FiscalYearID,
    v.VersionID,
    v.VersionLabel,
    v.BaseCurrency,
    c.CurrencyName,
    c.IsActive AS CurrencyIsActive
FROM dbo.tblVersions AS v
LEFT JOIN dbo.tblCurrencies AS c
    ON c.CurrencyCode = v.BaseCurrency
WHERE NULLIF(LTRIM(RTRIM(ISNULL(v.BaseCurrency, N''))), N'') IS NOT NULL
  AND (
        c.CurrencyCode IS NULL
        OR ISNULL(c.IsActive, 1) = 0
  )
ORDER BY v.FiscalYearID, v.VersionID;

PRINT '7. Email and SMTP settings';

SELECT
    ss.SettingKey,
    ss.SettingValue
FROM dbo.tblSystemSettings AS ss
WHERE ss.SettingKey IN (
    N'SMTP_ENABLED',
    N'SMTP_FROM',
    N'SMTP_HOST',
    N'SMTP_PASS',
    N'SMTP_PORT',
    N'SMTP_SECURE',
    N'SMTP_SSL',
    N'SMTP_USER',
    N'EMAIL_ERROR_ENABLED',
    N'EMAIL_ERROR_FROM',
    N'EMAIL_ERROR_TO'
)
ORDER BY ss.SettingKey;

SELECT
    CASE WHEN EXISTS (
        SELECT 1
        FROM dbo.tblSystemSettings
        WHERE SettingKey = N'SMTP_ENABLED'
          AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
    ) THEN 1 ELSE 0 END AS SmtpEnabled,
    CASE WHEN EXISTS (
        SELECT 1
        FROM dbo.tblSystemSettings
        WHERE SettingKey IN (N'EMAIL_ERROR_ENABLED', N'ERROR_EMAIL_ENABLED')
          AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
    ) THEN 1 ELSE 0 END AS ErrorEmailEnabled;

SELECT
    N'SMTP issues' AS IssueArea,
    IssueDetail
FROM (
    SELECT CASE
        WHEN EXISTS (
            SELECT 1
            FROM dbo.tblSystemSettings
            WHERE SettingKey = N'SMTP_ENABLED'
              AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
        ) AND NOT EXISTS (
            SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = N'SMTP_HOST' AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL
        ) THEN N'SMTP_HOST is blank.'
        END AS IssueDetail
    UNION ALL
    SELECT CASE
        WHEN EXISTS (
            SELECT 1
            FROM dbo.tblSystemSettings
            WHERE SettingKey = N'SMTP_ENABLED'
              AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
        ) AND NOT EXISTS (
            SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = N'SMTP_PORT' AND TRY_CAST(SettingValue AS INT) > 0
        ) THEN N'SMTP_PORT is blank or not greater than zero.'
        END
    UNION ALL
    SELECT CASE
        WHEN EXISTS (
            SELECT 1
            FROM dbo.tblSystemSettings
            WHERE SettingKey = N'SMTP_ENABLED'
              AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
        ) AND NOT EXISTS (
            SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = N'SMTP_FROM' AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL
        ) THEN N'SMTP_FROM is blank.'
        END
    UNION ALL
    SELECT CASE
        WHEN EXISTS (
            SELECT 1
            FROM dbo.tblSystemSettings
            WHERE SettingKey = N'SMTP_ENABLED'
              AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
        ) AND (
            (
                EXISTS (SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = N'SMTP_USER' AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL)
                AND NOT EXISTS (SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = N'SMTP_PASS' AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL)
            )
            OR (
                NOT EXISTS (SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = N'SMTP_USER' AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL)
                AND EXISTS (SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = N'SMTP_PASS' AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL)
            )
        ) THEN N'SMTP_USER and SMTP_PASS should either both be provided or both be blank.'
        END
    UNION ALL
    SELECT CASE
        WHEN EXISTS (
            SELECT 1
            FROM dbo.tblSystemSettings
            WHERE SettingKey IN (N'EMAIL_ERROR_ENABLED', N'ERROR_EMAIL_ENABLED')
              AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
        ) AND NOT EXISTS (
            SELECT 1
            FROM dbo.tblSystemSettings
            WHERE SettingKey = N'SMTP_ENABLED'
              AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
        ) THEN N'EMAIL_ERROR_ENABLED is true while SMTP_ENABLED is false.'
        END
    UNION ALL
    SELECT CASE
        WHEN EXISTS (
            SELECT 1
            FROM dbo.tblSystemSettings
            WHERE SettingKey IN (N'EMAIL_ERROR_ENABLED', N'ERROR_EMAIL_ENABLED')
              AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
        ) AND NOT EXISTS (
            SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey IN (N'EMAIL_ERROR_TO', N'ERROR_EMAIL_TO') AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL
        ) THEN N'EMAIL_ERROR_TO is blank.'
        END
    UNION ALL
    SELECT CASE
        WHEN EXISTS (
            SELECT 1
            FROM dbo.tblSystemSettings
            WHERE SettingKey IN (N'EMAIL_ERROR_ENABLED', N'ERROR_EMAIL_ENABLED')
              AND LOWER(LTRIM(RTRIM(ISNULL(SettingValue, N'')))) IN (N'1', N'true', N'yes', N'on')
        ) AND NOT EXISTS (
            SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey IN (N'EMAIL_ERROR_FROM', N'ERROR_EMAIL_FROM') AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL
        ) AND NOT EXISTS (
            SELECT 1 FROM dbo.tblSystemSettings WHERE SettingKey = N'SMTP_FROM' AND NULLIF(LTRIM(RTRIM(ISNULL(SettingValue, N''))), N'') IS NOT NULL
        ) THEN N'EMAIL_ERROR_FROM is blank and SMTP_FROM is also blank.'
        END
) AS issues
WHERE IssueDetail IS NOT NULL;

PRINT '8. Currency rate baseline';

SELECT
    r.CurrencyRateID,
    r.FromCurrencyCode,
    fc.CurrencyName AS FromCurrencyName,
    r.ToCurrencyCode,
    tc.CurrencyName AS ToCurrencyName,
    r.RateDate,
    r.RateType,
    r.RateValue,
    r.RateSource,
    r.IsActive
FROM dbo.tblCurrencyRates AS r
LEFT JOIN dbo.tblCurrencies AS fc
    ON fc.CurrencyCode = r.FromCurrencyCode
LEFT JOIN dbo.tblCurrencies AS tc
    ON tc.CurrencyCode = r.ToCurrencyCode
ORDER BY r.RateDate DESC, r.FromCurrencyCode, r.ToCurrencyCode, r.CurrencyRateID DESC;

SELECT
    COUNT(*) AS ActiveCurrencyRateCount
FROM dbo.tblCurrencyRates AS r
WHERE ISNULL(r.IsActive, 1) = 1;

SELECT
    r.CurrencyRateID,
    r.FromCurrencyCode,
    r.ToCurrencyCode,
    r.RateDate,
    r.RateType,
    r.RateValue,
    fc.CurrencyName AS FromCurrencyName,
    tc.CurrencyName AS ToCurrencyName
FROM dbo.tblCurrencyRates AS r
LEFT JOIN dbo.tblCurrencies AS fc
    ON fc.CurrencyCode = r.FromCurrencyCode
LEFT JOIN dbo.tblCurrencies AS tc
    ON tc.CurrencyCode = r.ToCurrencyCode
WHERE fc.CurrencyCode IS NULL
   OR tc.CurrencyCode IS NULL
   OR r.FromCurrencyCode = r.ToCurrencyCode
ORDER BY r.CurrencyRateID;

PRINT '9. Deprecated system settings using exact key match';

SELECT
    ss.SettingKey,
    ss.SettingValue
FROM dbo.tblSystemSettings AS ss
WHERE ss.SettingKey COLLATE Latin1_General_100_BIN2 IN (
    N'GLAccountSegmentNo',
    N'LOGIN_AUTH_MODE',
    N'LOGIN_DECAY_MIN',
    N'LOGIN_DECAY_HOUR_MIN',
    N'LOGIN_LOCKOUT_MIN',
    N'LOGIN_LOCKOUT_PERMANENT',
    N'LOGIN_MAX_ATTEMPTS',
    N'LOGIN_MAX_ATTEMPTS_HOUR',
    N'CBMS_LOGIN_URL',
    N'CBMS_SECURE_LOGIN_TTL_MINUTES',
    N'CBMS_TOKEN_LOGIN_URL_BASE',
    N'SESSION_IDLE_LIMIT',
    N'SESSION_TIMEOUT_MIN',
    N'ERROR_EMAIL_ENABLED',
    N'ERROR_EMAIL_FROM',
    N'ERROR_EMAIL_TO',
    N'Default_Fiscal_Year',
    N'Default_Version'
)
ORDER BY ss.SettingKey;

PRINT '10. Segment definition health';

SELECT
    s.SegmentID,
    s.SegmentCode,
    s.SegmentName,
    s.StartPoint,
    s.EndPoint,
    s.MinLength,
    s.MaxLength,
    s.CBMSDimension,
    s.SegmentGroup,
    s.UsedInFinancialAccount,
    s.UsedInStrategicPlanning,
    s.UsedInOrgStructure,
    s.ParentRequired
FROM dbo.tblSegments AS s
WHERE NULLIF(LTRIM(RTRIM(ISNULL(s.SegmentCode, N''))), N'') IS NULL
   OR NULLIF(LTRIM(RTRIM(ISNULL(s.SegmentName, N''))), N'') IS NULL
   OR (s.StartPoint IS NOT NULL AND s.StartPoint <= 0)
   OR (s.EndPoint IS NOT NULL AND s.EndPoint <= 0)
   OR (s.StartPoint IS NOT NULL AND s.EndPoint IS NOT NULL AND s.EndPoint < s.StartPoint)
   OR (s.MinLength IS NOT NULL AND s.MaxLength IS NOT NULL AND s.MinLength > s.MaxLength)
   OR NULLIF(LTRIM(RTRIM(ISNULL(s.CBMSDimension, N''))), N'') IS NULL
   OR NULLIF(LTRIM(RTRIM(ISNULL(s.SegmentGroup, N''))), N'') IS NULL
   OR (
        ISNULL(s.UsedInFinancialAccount, 0) = 0
        AND ISNULL(s.UsedInStrategicPlanning, 0) = 0
        AND ISNULL(s.UsedInOrgStructure, 0) = 0
   )
ORDER BY s.SegmentID;

PRINT '11. Configured segments missing active current-year values';

SELECT
    s.SegmentID,
    s.SegmentCode,
    s.SegmentName
FROM dbo.tblSegments AS s
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblSegmentValues AS sv
    WHERE sv.FiscalYearID = @FiscalYearID
      AND sv.ActiveFlag = 1
      AND sv.SegmentNo = s.SegmentID
)
ORDER BY s.SegmentID;

PRINT '12. Segment values with duplicate active keys';

SELECT
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentNo,
    sv.SegmentCode,
    COUNT(*) AS ActiveDuplicateCount
FROM dbo.tblSegmentValues AS sv
WHERE sv.FiscalYearID = @FiscalYearID
  AND sv.ActiveFlag = 1
GROUP BY
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentNo,
    sv.SegmentCode
HAVING COUNT(*) > 1
ORDER BY
    sv.SegmentNo,
    sv.SegmentCode,
    sv.DataObjectCode;

PRINT '13. Parent-required segment values missing parent coverage';

SELECT
    sv.SegmentValueID,
    sv.FiscalYearID,
    sv.DataObjectCode,
    sv.SegmentNo,
    sv.SegmentCode,
    sv.ParentSegmentNo,
    sv.ParentSegmentCode
FROM dbo.tblSegmentValues AS sv
INNER JOIN dbo.tblSegments AS s
    ON s.SegmentID = sv.SegmentNo
WHERE sv.FiscalYearID = @FiscalYearID
  AND sv.ActiveFlag = 1
  AND ISNULL(s.ParentRequired, 0) = 1
  AND (
        sv.ParentSegmentNo IS NULL
        OR sv.ParentSegmentNo = 0
        OR NULLIF(LTRIM(RTRIM(ISNULL(sv.ParentSegmentCode, N''))), N'') IS NULL
  )
ORDER BY sv.SegmentNo, sv.SegmentCode, sv.DataObjectCode;

PRINT '13. Scoped access direct rows for current fiscal year';

SELECT
    a.UserID,
    u.Username,
    a.FiscalYearID,
    a.DataObjectCode,
    a.AccessLevel,
    a.IncludeChildren,
    a.Revoked,
    a.AssignedAt
FROM dbo.tblDataObjectCodeAccess AS a
LEFT JOIN dbo.tblUsers AS u
    ON u.UserID = a.UserID
WHERE a.FiscalYearID = @FiscalYearID
ORDER BY a.UserID, a.DataObjectCode;

PRINT '14. Scoped access rows with invalid users or missing organisation codes';

SELECT
    a.UserID,
    u.Username,
    u.IsActive,
    a.FiscalYearID,
    a.DataObjectCode,
    a.AccessLevel,
    a.Revoked,
    c.DataObjectName
FROM dbo.tblDataObjectCodeAccess AS a
LEFT JOIN dbo.tblUsers AS u
    ON u.UserID = a.UserID
LEFT JOIN dbo.tblDataObjectCodes AS c
    ON c.FiscalYearID = a.FiscalYearID
   AND c.DataObjectCode = a.DataObjectCode
WHERE a.FiscalYearID = @FiscalYearID
  AND ISNULL(a.Revoked, 0) = 0
  AND (
        u.UserID IS NULL
        OR ISNULL(u.IsActive, 0) = 0
        OR c.DataObjectCode IS NULL
  )
ORDER BY a.UserID, a.DataObjectCode;

PRINT '15. Workflow assignment coverage for current context';

SELECT
    a.WorkflowAreaCode,
    a.WorkflowStageCode,
    a.FiscalYearID,
    a.VersionID,
    a.DataObjectCode,
    a.UserID,
    u.Username,
    a.SequenceNo,
    a.IsPrimary,
    a.ActiveFlag
FROM dbo.tblWorkflowAssignments AS a
LEFT JOIN dbo.tblUsers AS u
    ON u.UserID = a.UserID
WHERE ISNULL(a.ActiveFlag, 1) = 1
  AND (a.FiscalYearID = @FiscalYearID OR a.FiscalYearID IS NULL)
  AND (a.VersionID = @VersionID OR a.VersionID IS NULL)
ORDER BY
    a.WorkflowAreaCode,
    a.WorkflowStageCode,
    a.DataObjectCode,
    a.SequenceNo,
    a.UserID;

PRINT '16. Workflow areas without any assignment coverage for current context';

SELECT
    d.WorkflowAreaCode,
    d.WorkflowAreaName
FROM dbo.tblWorkflowDefinition AS d
WHERE d.ActiveFlag = 1
  AND NOT EXISTS (
        SELECT 1
        FROM dbo.tblWorkflowAssignments AS a
        WHERE ISNULL(a.ActiveFlag, 1) = 1
          AND a.WorkflowAreaCode = d.WorkflowAreaCode
          AND (a.FiscalYearID = @FiscalYearID OR a.FiscalYearID IS NULL)
          AND (a.VersionID = @VersionID OR a.VersionID IS NULL)
  )
ORDER BY d.WorkflowAreaCode;

PRINT '17. Workflow assignment rows with invalid users, invalid context, or missing scope';

SELECT
    a.WorkflowAssignmentID,
    a.WorkflowAreaCode,
    a.WorkflowStageCode,
    a.FiscalYearID,
    a.VersionID,
    a.DataObjectCode,
    a.UserID,
    u.Username,
    u.IsActive,
    c.DataObjectName
FROM dbo.tblWorkflowAssignments AS a
LEFT JOIN dbo.tblUsers AS u
    ON u.UserID = a.UserID
LEFT JOIN dbo.tblDataObjectCodes AS c
    ON c.FiscalYearID = COALESCE(a.FiscalYearID, @FiscalYearID)
   AND c.DataObjectCode = a.DataObjectCode
LEFT JOIN dbo.tblVersions AS v
    ON v.FiscalYearID = COALESCE(a.FiscalYearID, @FiscalYearID)
   AND v.VersionID = a.VersionID
WHERE ISNULL(a.ActiveFlag, 1) = 1
  AND (a.FiscalYearID = @FiscalYearID OR a.FiscalYearID IS NULL)
  AND (a.VersionID = @VersionID OR a.VersionID IS NULL)
  AND (
        u.UserID IS NULL
        OR ISNULL(u.IsActive, 0) = 0
        OR NULLIF(LTRIM(RTRIM(ISNULL(a.DataObjectCode, N''))), N'') IS NULL
        OR (
            UPPER(LTRIM(RTRIM(ISNULL(a.DataObjectCode, N'')))) NOT IN (N'0', N'GLOBAL')
            AND c.DataObjectCode IS NULL
        )
        OR (a.FiscalYearID IS NOT NULL AND NOT EXISTS (
            SELECT 1
            FROM dbo.tblFiscalYears AS fy
            WHERE fy.FiscalYearID = a.FiscalYearID
        ))
        OR (a.VersionID IS NOT NULL AND a.FiscalYearID IS NOT NULL AND v.VersionID IS NULL)
  )
ORDER BY a.WorkflowAreaCode, a.WorkflowStageCode, a.DataObjectCode, a.UserID;

PRINT '18. Workflow assignment scopes with multiple primary rows';

SELECT
    a.WorkflowAreaCode,
    a.WorkflowStageCode,
    ISNULL(a.FiscalYearID, 0) AS FiscalYearID,
    ISNULL(a.VersionID, 0) AS VersionID,
    a.DataObjectCode,
    COUNT(*) AS PrimaryRowCount
FROM dbo.tblWorkflowAssignments AS a
WHERE ISNULL(a.ActiveFlag, 1) = 1
  AND (a.FiscalYearID = @FiscalYearID OR a.FiscalYearID IS NULL)
  AND (a.VersionID = @VersionID OR a.VersionID IS NULL)
  AND ISNULL(a.IsPrimary, 0) = 1
GROUP BY
    a.WorkflowAreaCode,
    a.WorkflowStageCode,
    ISNULL(a.FiscalYearID, 0),
    ISNULL(a.VersionID, 0),
    a.DataObjectCode
HAVING COUNT(*) > 1
ORDER BY
    a.WorkflowAreaCode,
    a.WorkflowStageCode,
    a.DataObjectCode;
