USE [CBMSv2];
GO

/*
Allow a node to depend on itself only when the dependency is period-shifted.
This supports formulas such as:

    @Revenue[-1]@
    @ClosingBalance[+1]@

while still preventing invalid same-period self-dependencies.
*/

IF OBJECT_ID('dbo.tblCalcDependency', 'U') IS NOT NULL
BEGIN
    IF EXISTS (
        SELECT 1
        FROM sys.check_constraints
        WHERE parent_object_id = OBJECT_ID('dbo.tblCalcDependency')
          AND name = 'CK_tblCalcDependency_NotSelf'
    )
    BEGIN
        ALTER TABLE dbo.tblCalcDependency
            DROP CONSTRAINT CK_tblCalcDependency_NotSelf;
    END;

    ALTER TABLE dbo.tblCalcDependency
        ADD CONSTRAINT CK_tblCalcDependency_NotSelf
        CHECK (NodeID <> DependsOnNodeID OR OffsetPeriods <> 0);
END
GO
