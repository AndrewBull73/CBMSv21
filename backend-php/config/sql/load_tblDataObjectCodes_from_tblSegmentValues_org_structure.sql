/*
Purpose
-------
Load organisation DataObjectCodes from dbo.tblSegmentValues using
dbo.tblDataObjectTypes as the mapping.

Default behavior is PREVIEW ONLY.

Expected type setup
-------------------
dbo.tblDataObjectTypes
    Government      SegmentNo = NULL  Level = 1
    Head            SegmentNo = 1     Level = 2
    Cost Centre     SegmentNo = 2     Level = 3
    Sub Cost Centre SegmentNo = 3     Level = 4

Mapping
-------
dbo.tblSegmentValues                         dbo.tblDataObjectCodes
------------------------------------------  --------------------------------
FiscalYearID                                FiscalYearID
DataObjectCode                              DataObjectCode
SegmentName                                 DataObjectName
parent tblSegmentValues.DataObjectCode      DataObjectCodeParent
tblDataObjectTypes by SegmentNo             DataObjectTypeID
ActiveFlag                                  DataObjectCodeStatus
SegmentExternalID                           DataObjectDesc
UpdatedBy                                   UpdatedBy
UpdatedDate / current date                  DateUpdated

Notes
-----
- Government/root is a synthetic DataObjectCode, not a segment value.
- Segment-backed rows are selected from tblDataObjectTypes where SegmentNo is
  not NULL. This means the script follows the active type configuration.
- Parent links use ParentSegmentValueID first. Segment rows directly below the
  root may omit ParentSegmentValueID and are attached to @RootDataObjectCode.
*/

SET NOCOUNT ON;
SET XACT_ABORT ON;
GO

DECLARE @TargetFiscalYearID int = 2026;
DECLARE @RootDataObjectCode nvarchar(50) = N'GOV';
DECLARE @RootDataObjectName nvarchar(100) = N'Government';
DECLARE @DefaultUpdatedBy int = 1;

DECLARE @PreviewOnly bit = 1;
DECLARE @IncludeInactiveSegmentValues bit = 0;
DECLARE @CreateOrUpdateRoot bit = 1;
DECLARE @RebuildDataObjectTree bit = 1;

IF OBJECT_ID(N'dbo.tblSegmentValues', N'U') IS NULL
BEGIN
    THROW 50000, 'Source dbo.tblSegmentValues does not exist in the current database.', 1;
END;

IF OBJECT_ID(N'dbo.tblDataObjectTypes', N'U') IS NULL
BEGIN
    THROW 50001, 'Target dbo.tblDataObjectTypes does not exist in the current database.', 1;
END;

IF OBJECT_ID(N'dbo.tblDataObjectCodes', N'U') IS NULL
BEGIN
    THROW 50002, 'Target dbo.tblDataObjectCodes does not exist in the current database.', 1;
END;

IF OBJECT_ID(N'dbo.tblDataObjectTree', N'U') IS NULL
BEGIN
    THROW 50003, 'Target dbo.tblDataObjectTree does not exist in the current database.', 1;
END;

IF OBJECT_ID(N'dbo.tblFiscalYears', N'U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM dbo.tblFiscalYears WHERE FiscalYearID = @TargetFiscalYearID)
BEGIN
    THROW 50004, 'Target FiscalYearID does not exist in dbo.tblFiscalYears.', 1;
END;

IF NULLIF(LTRIM(RTRIM(@RootDataObjectCode)), N'') IS NULL
BEGIN
    THROW 50005, 'Root DataObjectCode is required.', 1;
END;

IF OBJECT_ID(N'tempdb..#RootType') IS NOT NULL DROP TABLE #RootType;
IF OBJECT_ID(N'tempdb..#SegmentTypes') IS NOT NULL DROP TABLE #SegmentTypes;
IF OBJECT_ID(N'tempdb..#CandidateDataObjectCodes') IS NOT NULL DROP TABLE #CandidateDataObjectCodes;
IF OBJECT_ID(N'tempdb..#RejectedRows') IS NOT NULL DROP TABLE #RejectedRows;
IF OBJECT_ID(N'tempdb..#DuplicateCandidateCodes') IS NOT NULL DROP TABLE #DuplicateCandidateCodes;

SELECT TOP (1)
    DataObjectTypeID,
    DataObjectTypeName,
    [Level]
INTO #RootType
FROM dbo.tblDataObjectTypes
WHERE SegmentNo IS NULL
ORDER BY [Level], DataObjectTypeID;

IF NOT EXISTS (SELECT 1 FROM #RootType)
BEGIN
    THROW 50006, 'No root DataObjectType found. Set Government SegmentNo to NULL in dbo.tblDataObjectTypes.', 1;
END;

SELECT
    DataObjectTypeID,
    DataObjectTypeName,
    SegmentNo,
    [Level]
INTO #SegmentTypes
FROM dbo.tblDataObjectTypes
WHERE SegmentNo IS NOT NULL;

IF NOT EXISTS (SELECT 1 FROM #SegmentTypes)
BEGIN
    THROW 50007, 'No segment-backed DataObjectTypes found. Set SegmentNo on Head, Cost Centre, and Sub Cost Centre types.', 1;
END;

;WITH SourceRows AS (
    SELECT
        sv.SegmentValueID,
        sv.FiscalYearID,
        DataObjectCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.DataObjectCode))), N''),
        sv.SegmentNo,
        SegmentCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.SegmentCode))), N''),
        DataObjectName = COALESCE(
            NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(100), sv.SegmentName))), N''),
            NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.SegmentCode))), N''),
            NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.DataObjectCode))), N'')
        ),
        DataObjectDesc = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(500), sv.SegmentExternalID))), N''),
        sv.ParentSegmentValueID,
        sv.ParentSegmentNo,
        sv.ParentSegmentCode,
        sv.ActiveFlag,
        UpdatedBy = COALESCE(sv.UpdatedBy, @DefaultUpdatedBy),
        UpdatedDate = COALESCE(sv.UpdatedDate, GETDATE()),
        dot.DataObjectTypeID,
        dot.DataObjectTypeName,
        dot.[Level] AS DataObjectTypeLevel,
        rootType.DataObjectTypeID AS RootDataObjectTypeID,
        rootType.[Level] AS RootLevel,
        ParentDataObjectCode = parent.DataObjectCode,
        DuplicateRowsForCode = COUNT(*) OVER (
            PARTITION BY sv.FiscalYearID, NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.DataObjectCode))), N'')
        )
    FROM dbo.tblSegmentValues sv
    INNER JOIN #SegmentTypes dot
        ON dot.SegmentNo = sv.SegmentNo
    CROSS JOIN #RootType rootType
    LEFT JOIN dbo.tblSegmentValues parent
        ON parent.SegmentValueID = sv.ParentSegmentValueID
    WHERE sv.FiscalYearID = @TargetFiscalYearID
      AND (@IncludeInactiveSegmentValues = 1 OR sv.ActiveFlag = 1)
),
Candidates AS (
    SELECT
        FiscalYearID,
        DataObjectCode,
        DataObjectName,
        DataObjectCodeParent = CASE
            WHEN ParentDataObjectCode IS NOT NULL THEN ParentDataObjectCode
            WHEN DataObjectTypeLevel = RootLevel + 1 THEN @RootDataObjectCode
            ELSE NULL
        END,
        DataObjectTypeID,
        DataObjectDesc,
        DataObjectCodeStatus = CASE WHEN ActiveFlag = 1 THEN N'Active' ELSE N'Inactive' END,
        UpdatedBy,
        DateUpdated = UpdatedDate,
        SegmentValueID,
        SegmentNo,
        SegmentCode,
        DataObjectTypeName,
        ParentSegmentValueID,
        ParentSegmentNo,
        ParentSegmentCode,
        ParentDataObjectCode,
        DuplicateRowsForCode
    FROM SourceRows
)
SELECT
    FiscalYearID,
    DataObjectCode,
    DataObjectName,
    DataObjectCodeParent,
    DataObjectTypeID,
    DataObjectDesc,
    DataObjectCodeStatus,
    UpdatedBy,
    DateUpdated,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    DataObjectTypeName,
    ParentSegmentValueID,
    ParentSegmentNo,
    ParentSegmentCode,
    ParentDataObjectCode,
    DuplicateRowsForCode
INTO #CandidateDataObjectCodes
FROM Candidates
WHERE DataObjectCode IS NOT NULL
  AND DataObjectName IS NOT NULL
  AND DataObjectTypeID IS NOT NULL
  AND DataObjectCode <> @RootDataObjectCode
  AND DuplicateRowsForCode = 1
  AND DataObjectCodeParent IS NOT NULL;

SELECT
    FiscalYearID,
    DataObjectCode,
    Rows = COUNT(*)
INTO #DuplicateCandidateCodes
FROM #CandidateDataObjectCodes
GROUP BY FiscalYearID, DataObjectCode
HAVING COUNT(*) > 1;

;WITH SourceRows AS (
    SELECT
        sv.SegmentValueID,
        sv.FiscalYearID,
        DataObjectCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.DataObjectCode))), N''),
        sv.SegmentNo,
        SegmentCode = NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.SegmentCode))), N''),
        DataObjectName = COALESCE(
            NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(100), sv.SegmentName))), N''),
            NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.SegmentCode))), N''),
            NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.DataObjectCode))), N'')
        ),
        sv.ParentSegmentValueID,
        sv.ParentSegmentNo,
        sv.ParentSegmentCode,
        dot.DataObjectTypeID,
        dot.DataObjectTypeName,
        dot.[Level] AS DataObjectTypeLevel,
        rootType.[Level] AS RootLevel,
        ParentDataObjectCode = parent.DataObjectCode,
        DuplicateRowsForCode = COUNT(*) OVER (
            PARTITION BY sv.FiscalYearID, NULLIF(LTRIM(RTRIM(CONVERT(nvarchar(50), sv.DataObjectCode))), N'')
        )
    FROM dbo.tblSegmentValues sv
    LEFT JOIN #SegmentTypes dot
        ON dot.SegmentNo = sv.SegmentNo
    CROSS JOIN #RootType rootType
    LEFT JOIN dbo.tblSegmentValues parent
        ON parent.SegmentValueID = sv.ParentSegmentValueID
    WHERE sv.FiscalYearID = @TargetFiscalYearID
      AND (@IncludeInactiveSegmentValues = 1 OR sv.ActiveFlag = 1)
      AND (
            dot.SegmentNo IS NOT NULL
            OR EXISTS (SELECT 1 FROM #SegmentTypes st WHERE st.SegmentNo = sv.SegmentNo)
      )
)
SELECT
    RejectionReason = CASE
        WHEN DataObjectCode IS NULL THEN N'Missing DataObjectCode'
        WHEN DataObjectName IS NULL THEN N'Missing DataObjectName'
        WHEN DataObjectCode = @RootDataObjectCode THEN N'Segment value conflicts with root DataObjectCode'
        WHEN DataObjectTypeID IS NULL THEN N'No DataObjectType mapped to SegmentNo'
        WHEN DuplicateRowsForCode > 1 THEN N'Duplicate segment rows for DataObjectCode'
        WHEN DataObjectTypeLevel > RootLevel + 1 AND ParentDataObjectCode IS NULL THEN N'Missing parent DataObjectCode from ParentSegmentValueID'
        WHEN DataObjectTypeLevel = RootLevel + 1 AND ParentDataObjectCode IS NULL THEN N'Attached to root'
        ELSE N'Filtered'
    END,
    FiscalYearID,
    DataObjectCode,
    DataObjectName,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    DataObjectTypeID,
    DataObjectTypeName,
    ParentSegmentValueID,
    ParentSegmentNo,
    ParentSegmentCode,
    ParentDataObjectCode,
    DuplicateRowsForCode
INTO #RejectedRows
FROM SourceRows
WHERE DataObjectCode IS NULL
   OR DataObjectName IS NULL
   OR DataObjectCode = @RootDataObjectCode
   OR DataObjectTypeID IS NULL
   OR DuplicateRowsForCode > 1
   OR (DataObjectTypeLevel > RootLevel + 1 AND ParentDataObjectCode IS NULL);

DELETE candidate
FROM #CandidateDataObjectCodes candidate
WHERE EXISTS (
    SELECT 1
    FROM #DuplicateCandidateCodes duplicate
    WHERE duplicate.FiscalYearID = candidate.FiscalYearID
      AND duplicate.DataObjectCode = candidate.DataObjectCode
);

SELECT
    TargetDatabase = DB_NAME(),
    FiscalYearID = @TargetFiscalYearID,
    RootDataObjectTypeID = (SELECT TOP (1) DataObjectTypeID FROM #RootType),
    RootDataObjectCode = @RootDataObjectCode,
    SegmentBackedTypes = (SELECT COUNT(*) FROM #SegmentTypes),
    CandidateRows = (SELECT COUNT(*) FROM #CandidateDataObjectCodes),
    RejectedRows = (SELECT COUNT(*) FROM #RejectedRows),
    ExistingRowsToUpdate = (
        SELECT COUNT(*)
        FROM dbo.tblDataObjectCodes target
        INNER JOIN #CandidateDataObjectCodes source
            ON source.FiscalYearID = target.FiscalYearID
           AND source.DataObjectCode = target.DataObjectCode
    ),
    NewRowsToInsert = (
        SELECT COUNT(*)
        FROM #CandidateDataObjectCodes source
        WHERE NOT EXISTS (
            SELECT 1
            FROM dbo.tblDataObjectCodes target
            WHERE target.FiscalYearID = source.FiscalYearID
              AND target.DataObjectCode = source.DataObjectCode
        )
    ),
    RootWillBeCreatedOrUpdated = CASE WHEN @CreateOrUpdateRoot = 1 THEN 1 ELSE 0 END;

SELECT
    DataObjectTypeID,
    DataObjectTypeName,
    SegmentNo,
    [Level]
FROM #SegmentTypes
ORDER BY [Level], DataObjectTypeID;

SELECT
    SegmentNo,
    DataObjectTypeID = MAX(DataObjectTypeID),
    DataObjectTypeName = MAX(DataObjectTypeName),
    CandidateRows = COUNT(*)
FROM #CandidateDataObjectCodes
GROUP BY SegmentNo
ORDER BY SegmentNo;

SELECT
    RejectionReason,
    Rows = COUNT(*)
FROM #RejectedRows
GROUP BY RejectionReason
ORDER BY RejectionReason;

SELECT TOP (200)
    FiscalYearID,
    DataObjectCode,
    DataObjectName,
    DataObjectCodeParent,
    DataObjectTypeID,
    DataObjectTypeName,
    DataObjectCodeStatus,
    SegmentNo,
    SegmentCode,
    ParentSegmentValueID,
    ParentDataObjectCode
FROM #CandidateDataObjectCodes
ORDER BY DataObjectTypeID, DataObjectCode;

SELECT TOP (200)
    RejectionReason,
    FiscalYearID,
    DataObjectCode,
    DataObjectName,
    SegmentValueID,
    SegmentNo,
    SegmentCode,
    DataObjectTypeID,
    DataObjectTypeName,
    ParentSegmentValueID,
    ParentSegmentNo,
    ParentSegmentCode,
    ParentDataObjectCode,
    DuplicateRowsForCode
FROM #RejectedRows
ORDER BY RejectionReason, SegmentNo, DataObjectCode;

IF @PreviewOnly = 0
BEGIN
    BEGIN TRANSACTION;

        DECLARE @RootDataObjectTypeID int = (SELECT TOP (1) DataObjectTypeID FROM #RootType);
        DECLARE @InsertedRows int = 0;
        DECLARE @UpdatedRows int = 0;
        DECLARE @RootRowsAffected int = 0;
        DECLARE @TreeRowsInserted int = 0;

        IF @CreateOrUpdateRoot = 1
        BEGIN
            MERGE dbo.tblDataObjectCodes AS target
            USING (
                SELECT
                    FiscalYearID = @TargetFiscalYearID,
                    DataObjectCode = @RootDataObjectCode,
                    DataObjectName = @RootDataObjectName,
                    DataObjectCodeParent = CONVERT(nvarchar(50), NULL),
                    DataObjectTypeID = @RootDataObjectTypeID,
                    DataObjectDesc = CONVERT(nvarchar(500), NULL),
                    DataObjectCodeStatus = N'Active',
                    UpdatedBy = @DefaultUpdatedBy
            ) AS source
                ON target.FiscalYearID = source.FiscalYearID
               AND target.DataObjectCode = source.DataObjectCode
            WHEN MATCHED THEN
                UPDATE SET
                    target.DataObjectName = source.DataObjectName,
                    target.DataObjectCodeParent = source.DataObjectCodeParent,
                    target.DataObjectTypeID = source.DataObjectTypeID,
                    target.DataObjectDesc = source.DataObjectDesc,
                    target.DataObjectCodeStatus = source.DataObjectCodeStatus,
                    target.UpdatedBy = source.UpdatedBy,
                    target.DateUpdated = SYSUTCDATETIME()
            WHEN NOT MATCHED THEN
                INSERT (
                    FiscalYearID,
                    DataObjectCode,
                    DataObjectName,
                    DataObjectCodeParent,
                    DataObjectTypeID,
                    DataObjectDesc,
                    DataObjectCodeStatus,
                    UpdatedBy,
                    DateUpdated
                )
                VALUES (
                    source.FiscalYearID,
                    source.DataObjectCode,
                    source.DataObjectName,
                    source.DataObjectCodeParent,
                    source.DataObjectTypeID,
                    source.DataObjectDesc,
                    source.DataObjectCodeStatus,
                    source.UpdatedBy,
                    SYSUTCDATETIME()
                );

            SET @RootRowsAffected = @@ROWCOUNT;
        END;

        UPDATE target
        SET
            target.DataObjectName = source.DataObjectName,
            target.DataObjectCodeParent = source.DataObjectCodeParent,
            target.DataObjectTypeID = source.DataObjectTypeID,
            target.DataObjectDesc = source.DataObjectDesc,
            target.DataObjectCodeStatus = source.DataObjectCodeStatus,
            target.UpdatedBy = source.UpdatedBy,
            target.DateUpdated = source.DateUpdated
        FROM dbo.tblDataObjectCodes target
        INNER JOIN #CandidateDataObjectCodes source
            ON source.FiscalYearID = target.FiscalYearID
           AND source.DataObjectCode = target.DataObjectCode;

        SET @UpdatedRows = @@ROWCOUNT;

        INSERT INTO dbo.tblDataObjectCodes (
            FiscalYearID,
            DataObjectCode,
            DataObjectName,
            DataObjectCodeParent,
            DataObjectTypeID,
            DataObjectDesc,
            DataObjectCodeStatus,
            UpdatedBy,
            DateUpdated
        )
        SELECT
            source.FiscalYearID,
            source.DataObjectCode,
            source.DataObjectName,
            source.DataObjectCodeParent,
            source.DataObjectTypeID,
            source.DataObjectDesc,
            source.DataObjectCodeStatus,
            source.UpdatedBy,
            source.DateUpdated
        FROM #CandidateDataObjectCodes source
        WHERE NOT EXISTS (
            SELECT 1
            FROM dbo.tblDataObjectCodes target
            WHERE target.FiscalYearID = source.FiscalYearID
              AND target.DataObjectCode = source.DataObjectCode
        );

        SET @InsertedRows = @@ROWCOUNT;

        IF @RebuildDataObjectTree = 1
        BEGIN
            DELETE FROM dbo.tblDataObjectTree
            WHERE FiscalYearID = @TargetFiscalYearID;

            ;WITH CodeTree AS (
                SELECT
                    FiscalYearID,
                    AncestorCode = DataObjectCode,
                    DescendantCode = DataObjectCode,
                    DataObjectCodeParent,
                    Depth = 0,
                    PathText = CONVERT(nvarchar(max), N'/' + DataObjectCode + N'/')
                FROM dbo.tblDataObjectCodes
                WHERE FiscalYearID = @TargetFiscalYearID

                UNION ALL

                SELECT
                    child.FiscalYearID,
                    AncestorCode = parent.DataObjectCode,
                    child.DescendantCode,
                    parent.DataObjectCodeParent,
                    Depth = child.Depth + 1,
                    PathText = CONVERT(nvarchar(max), child.PathText + parent.DataObjectCode + N'/')
                FROM CodeTree child
                INNER JOIN dbo.tblDataObjectCodes parent
                    ON parent.FiscalYearID = child.FiscalYearID
                   AND parent.DataObjectCode = child.DataObjectCodeParent
                WHERE child.Depth < 50
                  AND CHARINDEX(N'/' + parent.DataObjectCode + N'/', child.PathText) = 0
            )
            INSERT INTO dbo.tblDataObjectTree (
                FiscalYearID,
                AncestorCode,
                DescendantCode,
                Depth
            )
            SELECT
                FiscalYearID,
                AncestorCode,
                DescendantCode,
                Depth
            FROM CodeTree
            OPTION (MAXRECURSION 100);

            SET @TreeRowsInserted = @@ROWCOUNT;
        END;

        SELECT
            RootRowsAffected = @RootRowsAffected,
            UpdatedRows = @UpdatedRows,
            InsertedRows = @InsertedRows,
            TreeRowsInserted = @TreeRowsInserted;

    COMMIT TRANSACTION;
END
ELSE
BEGIN
    PRINT 'Preview only. Set @PreviewOnly = 0 to load dbo.tblDataObjectCodes from dbo.tblSegmentValues.';
END;
GO
