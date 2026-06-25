/*
Purpose
-------
Resolve dbo.tblSegmentValues.ParentSegmentValueID from the parent link fields.

Use after loading or editing segment values:

    EXEC dbo.uspResolveSegmentValueParentLinks @FiscalYearID = 2026;
    EXEC dbo.uspResolveSegmentValueParentLinks @FiscalYearID = 2026, @SegmentNo = 3;

The procedure also refreshes ParentSegmentDataObjectCode when it can derive the
parent data-object scope from dbo.tblDataObjectCodes.
*/

CREATE OR ALTER PROCEDURE dbo.uspResolveSegmentValueParentLinks
    @FiscalYearID int,
    @SegmentNo int = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @FiscalYearID IS NULL OR @FiscalYearID <= 0
    BEGIN
        THROW 50000, 'FiscalYearID is required.', 1;
    END;

    IF OBJECT_ID(N'dbo.tblSegmentValues', N'U') IS NULL
    BEGIN
        THROW 50001, 'dbo.tblSegmentValues does not exist.', 1;
    END;

    IF COL_LENGTH(N'dbo.tblSegmentValues', N'ParentSegmentNo') IS NULL
       OR COL_LENGTH(N'dbo.tblSegmentValues', N'ParentSegmentCode') IS NULL
       OR COL_LENGTH(N'dbo.tblSegmentValues', N'ParentSegmentValueID') IS NULL
       OR COL_LENGTH(N'dbo.tblSegmentValues', N'ParentSegmentDataObjectCode') IS NULL
    BEGIN
        THROW 50002, 'tblSegmentValues must have ParentSegmentValueID, ParentSegmentNo, ParentSegmentCode, and ParentSegmentDataObjectCode.', 1;
    END;

    DECLARE @ParentDataObjectRows int = 0;
    DECLARE @ParentValueRows int = 0;

    IF OBJECT_ID(N'dbo.tblDataObjectCodes', N'U') IS NOT NULL
    BEGIN
        ;WITH ChildRows AS (
            SELECT
                child.SegmentValueID,
                child.FiscalYearID,
                child.DataObjectCode,
                child.SegmentNo,
                child.SegmentCode,
                child.ParentSegmentNo,
                child.ParentSegmentCode
            FROM dbo.tblSegmentValues child
            WHERE child.FiscalYearID = @FiscalYearID
              AND (@SegmentNo IS NULL OR child.SegmentNo = @SegmentNo)
              AND child.ParentSegmentNo IS NOT NULL
              AND NULLIF(LTRIM(RTRIM(ISNULL(child.ParentSegmentCode, N''))), N'') IS NOT NULL
        ),
        DataObjectScope AS (
            SELECT
                child.SegmentValueID,
                ScopeDataObjectCode = CONVERT(nvarchar(50), child.DataObjectCode),
                ScopeDepth = 0
            FROM ChildRows child

            UNION ALL

            SELECT
                scope.SegmentValueID,
                ScopeDataObjectCode = CONVERT(nvarchar(50), doc.DataObjectCodeParent),
                ScopeDepth = scope.ScopeDepth + 1
            FROM DataObjectScope scope
            INNER JOIN dbo.tblDataObjectCodes doc
                ON doc.FiscalYearID = @FiscalYearID
               AND doc.DataObjectCode = scope.ScopeDataObjectCode
            WHERE NULLIF(LTRIM(RTRIM(ISNULL(doc.DataObjectCodeParent, N''))), N'') IS NOT NULL
              AND doc.DataObjectCodeParent <> scope.ScopeDataObjectCode
              AND scope.ScopeDepth < 20
        ),
        ParentCandidates AS (
            SELECT
                child.SegmentValueID,
                ParentSegmentDataObjectCode = parent.DataObjectCode,
                rn = ROW_NUMBER() OVER (
                    PARTITION BY child.SegmentValueID
                    ORDER BY
                        CASE WHEN scope.ScopeDataObjectCode IS NOT NULL THEN 0 ELSE 1 END,
                        ISNULL(scope.ScopeDepth, 999),
                        CASE WHEN parent.DataObjectCode = child.DataObjectCode THEN 0 ELSE 1 END,
                        parent.SegmentValueID
                )
            FROM ChildRows child
            INNER JOIN dbo.tblSegmentValues parent
                ON parent.FiscalYearID = child.FiscalYearID
               AND parent.SegmentNo = child.ParentSegmentNo
               AND parent.SegmentCode = child.ParentSegmentCode
               AND parent.ActiveFlag = 1
            LEFT JOIN DataObjectScope scope
                ON scope.SegmentValueID = child.SegmentValueID
               AND scope.ScopeDataObjectCode = parent.DataObjectCode
        )
        UPDATE child
        SET child.ParentSegmentDataObjectCode = candidate.ParentSegmentDataObjectCode
        FROM dbo.tblSegmentValues child
        INNER JOIN ParentCandidates candidate
            ON candidate.SegmentValueID = child.SegmentValueID
           AND candidate.rn = 1
        WHERE ISNULL(child.ParentSegmentDataObjectCode, N'') <> ISNULL(candidate.ParentSegmentDataObjectCode, N'')
        OPTION (MAXRECURSION 100);

        SET @ParentDataObjectRows = @@ROWCOUNT;
    END;

    ;WITH ParentMatches AS (
        SELECT
            child.SegmentValueID AS ChildSegmentValueID,
            parent.SegmentValueID AS ParentSegmentValueID,
            rn = ROW_NUMBER() OVER (
                PARTITION BY child.SegmentValueID
                ORDER BY
                    CASE WHEN parent.DataObjectCode = child.ParentSegmentDataObjectCode THEN 0 ELSE 1 END,
                    parent.SegmentValueID
            ),
            match_count = COUNT(parent.SegmentValueID) OVER (PARTITION BY child.SegmentValueID)
        FROM dbo.tblSegmentValues child
        INNER JOIN dbo.tblSegmentValues parent
            ON parent.FiscalYearID = child.FiscalYearID
           AND parent.SegmentNo = child.ParentSegmentNo
           AND parent.SegmentCode = child.ParentSegmentCode
           AND parent.ActiveFlag = 1
           AND (
                NULLIF(LTRIM(RTRIM(ISNULL(child.ParentSegmentDataObjectCode, N''))), N'') IS NULL
                OR parent.DataObjectCode = child.ParentSegmentDataObjectCode
           )
        WHERE child.FiscalYearID = @FiscalYearID
          AND (@SegmentNo IS NULL OR child.SegmentNo = @SegmentNo)
          AND child.ParentSegmentNo IS NOT NULL
          AND NULLIF(LTRIM(RTRIM(ISNULL(child.ParentSegmentCode, N''))), N'') IS NOT NULL
    )
    UPDATE child
    SET child.ParentSegmentValueID = matches.ParentSegmentValueID
    FROM dbo.tblSegmentValues child
    INNER JOIN ParentMatches matches
        ON matches.ChildSegmentValueID = child.SegmentValueID
       AND matches.rn = 1
       AND matches.match_count = 1
    WHERE ISNULL(child.ParentSegmentValueID, 0) <> matches.ParentSegmentValueID;

    SET @ParentValueRows = @@ROWCOUNT;

    SELECT
        FiscalYearID = @FiscalYearID,
        SegmentNo = @SegmentNo,
        ParentDataObjectRowsUpdated = @ParentDataObjectRows,
        ParentValueRowsUpdated = @ParentValueRows,
        MissingParentValueIDRows = (
            SELECT COUNT(*)
            FROM dbo.tblSegmentValues child
            WHERE child.FiscalYearID = @FiscalYearID
              AND (@SegmentNo IS NULL OR child.SegmentNo = @SegmentNo)
              AND child.ParentSegmentNo IS NOT NULL
              AND NULLIF(LTRIM(RTRIM(ISNULL(child.ParentSegmentCode, N''))), N'') IS NOT NULL
              AND child.ParentSegmentValueID IS NULL
        );
END;
GO
