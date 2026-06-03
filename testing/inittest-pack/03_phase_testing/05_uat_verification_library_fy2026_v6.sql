/*
CBMSv21 UAT Verification SQL Library
Baseline context: FY 2026 / Version 6
Prepared: 2026-05-23

Usage:
1. Run in SQL Server Management Studio against the active test database.
2. Leave filter variables as NULL to inspect the whole baseline context.
3. Set specific variables to narrow to one workflow record, project, or execution document.
*/

DECLARE @FiscalYearID INT = 2026;
DECLARE @VersionID INT = 6;
DECLARE @WorkflowAreaCode NVARCHAR(50) = NULL;   -- e.g. N'BE_WARRANT'
DECLARE @RecordKey NVARCHAR(100) = NULL;         -- workflow RecordKey, usually the document ID as text
DECLARE @ProjectCode NVARCHAR(50) = NULL;        -- e.g. N'PRJ-001'
DECLARE @ExecutionDocumentNo NVARCHAR(50) = NULL;-- e.g. warrant/reservation/commitment number

PRINT 'Running UAT verification library against database: ' + DB_NAME();

PRINT '1. Baseline version row';

IF OBJECT_ID(N'dbo.tblVersions', N'U') IS NOT NULL
BEGIN
    IF OBJECT_ID(N'dbo.tblVersionTypes', N'U') IS NOT NULL
    BEGIN
        SELECT
            v.FiscalYearID,
            v.VersionID,
            v.VersionLabel,
            vt.VersionTypeCode,
            vt.VersionTypeName,
            v.ActiveFlag
        FROM dbo.tblVersions AS v
        LEFT JOIN dbo.tblVersionTypes AS vt
            ON vt.VersionTypeID = v.VersionTypeID
        WHERE v.FiscalYearID = @FiscalYearID
          AND v.VersionID = @VersionID;
    END
    ELSE
    BEGIN
        SELECT *
        FROM dbo.tblVersions
        WHERE FiscalYearID = @FiscalYearID
          AND VersionID = @VersionID;
    END;
END;

PRINT '2. Workflow definitions installed for execution workflow areas';

IF OBJECT_ID(N'dbo.tblWorkflowDefinition', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowDefinitionStage', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowDefinitionAction', N'U') IS NOT NULL
BEGIN
    SELECT
        d.WorkflowAreaCode,
        d.WorkflowAreaName,
        COUNT(DISTINCT s.WorkflowStageCode) AS StageCount,
        COUNT(DISTINCT a.WorkflowDefinitionActionID) AS ActionCount
    FROM dbo.tblWorkflowDefinition AS d
    LEFT JOIN dbo.tblWorkflowDefinitionStage AS s
        ON s.WorkflowDefinitionID = d.WorkflowDefinitionID
       AND s.ActiveFlag = 1
    LEFT JOIN dbo.tblWorkflowDefinitionAction AS a
        ON a.WorkflowDefinitionID = d.WorkflowDefinitionID
       AND a.ActiveFlag = 1
    WHERE d.ActiveFlag = 1
      AND d.WorkflowAreaCode IN (N'BE_COMMITMENT', N'BE_WARRANT', N'BE_RESERVATION', N'BE_SUPPLEMENTARY', N'BE_RIE')
    GROUP BY
        d.WorkflowAreaCode,
        d.WorkflowAreaName
    ORDER BY
        d.WorkflowAreaCode;
END;

PRINT '3. Workflow instances in baseline context';

IF OBJECT_ID(N'dbo.tblWorkflowInstance', N'U') IS NOT NULL
BEGIN
    SELECT TOP (100)
        wi.WorkflowInstanceID,
        wi.WorkflowAreaCode,
        wi.RecordTableName,
        wi.RecordID,
        wi.RecordKey,
        wi.ScopeDataObjectCode,
        wi.CurrentStageCode,
        wi.CurrentStatusCode,
        wi.SubmittedBy,
        wi.SubmittedDate,
        wi.ApprovedBy,
        wi.ApprovedDate,
        wi.CancelledBy,
        wi.CancelledDate,
        wi.LastActionCode,
        wi.LastActionDate
    FROM dbo.tblWorkflowInstance AS wi
    WHERE wi.FiscalYearID = @FiscalYearID
      AND wi.VersionID = @VersionID
      AND (@WorkflowAreaCode IS NULL OR wi.WorkflowAreaCode = @WorkflowAreaCode)
      AND (@RecordKey IS NULL OR wi.RecordKey = @RecordKey)
      AND wi.ActiveFlag = 1
    ORDER BY
        wi.UpdatedDate DESC,
        wi.WorkflowInstanceID DESC;
END;

PRINT '4. Workflow history for a selected workflow record';

IF OBJECT_ID(N'dbo.tblWorkflowInstance', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowInstanceHistory', N'U') IS NOT NULL
   AND @WorkflowAreaCode IS NOT NULL
   AND @RecordKey IS NOT NULL
BEGIN
    SELECT
        h.WorkflowInstanceHistoryID,
        h.WorkflowAreaCode,
        h.WorkflowActionCode,
        h.FromStageCode,
        h.ToStageCode,
        h.AssignmentScopeCode,
        h.AssignmentUserID,
        h.ActionBy,
        h.ActionDate,
        h.ActionNote
    FROM dbo.tblWorkflowInstanceHistory AS h
    INNER JOIN dbo.tblWorkflowInstance AS wi
        ON wi.WorkflowInstanceID = h.WorkflowInstanceID
    WHERE wi.WorkflowAreaCode = @WorkflowAreaCode
      AND wi.RecordKey = @RecordKey
    ORDER BY
        h.ActionDate DESC,
        h.WorkflowInstanceHistoryID DESC;
END;

PRINT '5. Strategic project register snapshot';

IF OBJECT_ID(N'dbo.tblSbProject', N'U') IS NOT NULL
BEGIN
    SELECT
        p.ProjectID,
        p.ProjectCode,
        p.ProjectName,
        p.SourceFiscalYearID,
        p.SourceDataObjectCode,
        p.SourceSegmentCode,
        p.ActiveFlag,
        p.CreatedDate,
        p.UpdatedDate
    FROM dbo.tblSbProject AS p
    WHERE (@ProjectCode IS NULL OR p.ProjectCode = @ProjectCode)
    ORDER BY
        CASE WHEN p.ProjectCode IS NULL THEN 1 ELSE 0 END,
        p.ProjectCode,
        p.ProjectName;
END;

PRINT '6. Strategic project link counts';

IF OBJECT_ID(N'dbo.tblSbProjectProgramLink', N'U') IS NOT NULL
BEGIN
    SELECT
        ppl.ProjectID,
        COUNT(*) AS ActiveProgramLinkCount
    FROM dbo.tblSbProjectProgramLink AS ppl
    INNER JOIN dbo.tblSbProject AS p
        ON p.ProjectID = ppl.ProjectID
    WHERE ppl.ActiveFlag = 1
      AND (@ProjectCode IS NULL OR p.ProjectCode = @ProjectCode)
    GROUP BY
        ppl.ProjectID
    ORDER BY
        ppl.ProjectID;
END;

IF OBJECT_ID(N'dbo.tblSbProjectObjectiveLink', N'U') IS NOT NULL
BEGIN
    SELECT
        pol.ProjectID,
        COUNT(*) AS ActiveObjectiveLinkCount
    FROM dbo.tblSbProjectObjectiveLink AS pol
    INNER JOIN dbo.tblSbProject AS p
        ON p.ProjectID = pol.ProjectID
    WHERE pol.ActiveFlag = 1
      AND (@ProjectCode IS NULL OR p.ProjectCode = @ProjectCode)
    GROUP BY
        pol.ProjectID
    ORDER BY
        pol.ProjectID;
END;

IF OBJECT_ID(N'dbo.tblSbProjectOrgUnitLink', N'U') IS NOT NULL
BEGIN
    SELECT
        poul.ProjectID,
        COUNT(*) AS ActiveOrgUnitLinkCount
    FROM dbo.tblSbProjectOrgUnitLink AS poul
    INNER JOIN dbo.tblSbProject AS p
        ON p.ProjectID = poul.ProjectID
    WHERE poul.ActiveFlag = 1
      AND (@ProjectCode IS NULL OR p.ProjectCode = @ProjectCode)
    GROUP BY
        poul.ProjectID
    ORDER BY
        poul.ProjectID;
END;

PRINT '7. Execution opening balance summary for baseline context';

IF OBJECT_ID(N'dbo.tblBeExecutionOpeningBalance', N'U') IS NOT NULL
BEGIN
    SELECT
        COUNT(*) AS ActiveOpeningBalanceLines,
        SUM(OpeningAmount) AS TotalOpeningAmount,
        SUM(CurrentAuthorizedAmount) AS TotalCurrentAuthorizedAmount
    FROM dbo.tblBeExecutionOpeningBalance
    WHERE FiscalYearID = @FiscalYearID
      AND ExecutionVersionID = @VersionID
      AND ActiveFlag = 1;
END;

PRINT '8. Warrant headers with line counts and workflow state';

IF OBJECT_ID(N'dbo.tblBeWarrant', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblBeWarrantLine', N'U') IS NOT NULL
BEGIN
    SELECT TOP (50)
        w.WarrantID,
        w.WarrantNo,
        w.WarrantTitle,
        w.WarrantStatusCode,
        w.WarrantDate,
        COUNT(wl.WarrantLineID) AS ActiveLineCount,
        COALESCE(SUM(wl.ReleaseAmount), 0) AS TotalReleaseAmount,
        wi.CurrentStageCode,
        wi.CurrentStatusCode,
        wi.LastActionCode,
        wi.LastActionDate
    FROM dbo.tblBeWarrant AS w
    LEFT JOIN dbo.tblBeWarrantLine AS wl
        ON wl.WarrantID = w.WarrantID
       AND wl.ActiveFlag = 1
    LEFT JOIN dbo.tblWorkflowInstance AS wi
        ON wi.WorkflowAreaCode = N'BE_WARRANT'
       AND wi.RecordTableName = N'dbo.tblBeWarrant'
       AND wi.RecordKey = CAST(w.WarrantID AS NVARCHAR(100))
       AND wi.ActiveFlag = 1
    WHERE w.FiscalYearID = @FiscalYearID
      AND w.ExecutionVersionID = @VersionID
      AND (@ExecutionDocumentNo IS NULL OR w.WarrantNo = @ExecutionDocumentNo)
      AND w.ActiveFlag = 1
    GROUP BY
        w.WarrantID,
        w.WarrantNo,
        w.WarrantTitle,
        w.WarrantStatusCode,
        w.WarrantDate,
        wi.CurrentStageCode,
        wi.CurrentStatusCode,
        wi.LastActionCode,
        wi.LastActionDate
    ORDER BY
        w.WarrantID DESC;
END;

PRINT '9. Reservation headers with line counts and workflow state';

IF OBJECT_ID(N'dbo.tblBeReservation', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblBeReservationLine', N'U') IS NOT NULL
BEGIN
    SELECT TOP (50)
        r.ReservationID,
        r.ReservationNo,
        r.ReservationTitle,
        r.ReservationStatusCode,
        r.ReservationDate,
        COUNT(rl.ReservationLineID) AS ActiveLineCount,
        COALESCE(SUM(rl.ReservationAmount), 0) AS TotalReservationAmount,
        wi.CurrentStageCode,
        wi.CurrentStatusCode,
        wi.LastActionCode,
        wi.LastActionDate
    FROM dbo.tblBeReservation AS r
    LEFT JOIN dbo.tblBeReservationLine AS rl
        ON rl.ReservationID = r.ReservationID
       AND rl.ActiveFlag = 1
    LEFT JOIN dbo.tblWorkflowInstance AS wi
        ON wi.WorkflowAreaCode = N'BE_RESERVATION'
       AND wi.RecordTableName = N'dbo.tblBeReservation'
       AND wi.RecordKey = CAST(r.ReservationID AS NVARCHAR(100))
       AND wi.ActiveFlag = 1
    WHERE r.FiscalYearID = @FiscalYearID
      AND r.ExecutionVersionID = @VersionID
      AND (@ExecutionDocumentNo IS NULL OR r.ReservationNo = @ExecutionDocumentNo)
      AND r.ActiveFlag = 1
    GROUP BY
        r.ReservationID,
        r.ReservationNo,
        r.ReservationTitle,
        r.ReservationStatusCode,
        r.ReservationDate,
        wi.CurrentStageCode,
        wi.CurrentStatusCode,
        wi.LastActionCode,
        wi.LastActionDate
    ORDER BY
        r.ReservationID DESC;
END;

PRINT '10. Commitment headers with line counts and workflow state';

IF OBJECT_ID(N'dbo.tblBeCommitment', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblBeCommitmentLine', N'U') IS NOT NULL
BEGIN
    SELECT TOP (50)
        c.CommitmentID,
        c.CommitmentNo,
        c.CommitmentTitle,
        c.CommitmentStatusCode,
        c.CommitmentDate,
        COUNT(cl.CommitmentLineID) AS ActiveLineCount,
        COALESCE(SUM(cl.CommitmentAmount), 0) AS TotalCommitmentAmount,
        wi.CurrentStageCode,
        wi.CurrentStatusCode,
        wi.LastActionCode,
        wi.LastActionDate
    FROM dbo.tblBeCommitment AS c
    LEFT JOIN dbo.tblBeCommitmentLine AS cl
        ON cl.CommitmentID = c.CommitmentID
       AND cl.ActiveFlag = 1
    LEFT JOIN dbo.tblWorkflowInstance AS wi
        ON wi.WorkflowAreaCode = N'BE_COMMITMENT'
       AND wi.RecordTableName = N'dbo.tblBeCommitment'
       AND wi.RecordKey = CAST(c.CommitmentID AS NVARCHAR(100))
       AND wi.ActiveFlag = 1
    WHERE c.FiscalYearID = @FiscalYearID
      AND c.ExecutionVersionID = @VersionID
      AND (@ExecutionDocumentNo IS NULL OR c.CommitmentNo = @ExecutionDocumentNo)
      AND c.ActiveFlag = 1
    GROUP BY
        c.CommitmentID,
        c.CommitmentNo,
        c.CommitmentTitle,
        c.CommitmentStatusCode,
        c.CommitmentDate,
        wi.CurrentStageCode,
        wi.CurrentStatusCode,
        wi.LastActionCode,
        wi.LastActionDate
    ORDER BY
        c.CommitmentID DESC;
END;

PRINT '11. End of UAT verification library';
