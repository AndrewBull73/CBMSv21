/*
Phase 01 / Parameter File
Edit these values before running the master rebuild script.

This file creates a temp-table profile consumed by
03_install_core_platform_foundation.sql.
*/

SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF OBJECT_ID('tempdb..#CbmsInitTestProfile') IS NOT NULL
BEGIN
    DROP TABLE #CbmsInitTestProfile;
END;
GO

CREATE TABLE #CbmsInitTestProfile
(
    ClientName NVARCHAR(200) NOT NULL,
    AppUrl NVARCHAR(500) NOT NULL,
    DefaultFiscalYearID INT NOT NULL,
    DefaultFiscalYearLabel NVARCHAR(20) NOT NULL,
    DefaultFiscalYearStart DATE NOT NULL,
    DefaultFiscalYearEnd DATE NOT NULL,
    DefaultVersionID INT NOT NULL,
    DefaultVersionLabel NVARCHAR(100) NOT NULL,
    DefaultLanguage NVARCHAR(20) NOT NULL,
    TrainingFeaturesEnabled BIT NOT NULL,
    SessionIdleTimeoutSec INT NOT NULL,
    SessionAbsoluteTimeoutMin INT NOT NULL,
    SessionRetentionDays INT NOT NULL,
    GlAccountSegmentNo INT NOT NULL
);
GO

INSERT INTO #CbmsInitTestProfile
(
    ClientName,
    AppUrl,
    DefaultFiscalYearID,
    DefaultFiscalYearLabel,
    DefaultFiscalYearStart,
    DefaultFiscalYearEnd,
    DefaultVersionID,
    DefaultVersionLabel,
    DefaultLanguage,
    TrainingFeaturesEnabled,
    SessionIdleTimeoutSec,
    SessionAbsoluteTimeoutMin,
    SessionRetentionDays,
    GlAccountSegmentNo
)
VALUES
(
    N'Kingdom of Lesotho',
    N'http://localhost:8080/CBMSv21',
    2026,
    N'2026/2027',
    '2026-04-01',
    '2027-03-31',
    1,
    N'2026 v 1',
    N'en',
    1,
    1800,
    6000,
    1,
    11
);

SELECT *
FROM #CbmsInitTestProfile;
