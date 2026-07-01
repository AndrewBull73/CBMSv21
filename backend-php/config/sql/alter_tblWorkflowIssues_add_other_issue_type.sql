SET NOCOUNT ON;

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues')
         AND name = N'CK_tblWorkflowIssues_IssueTypeCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    DROP CONSTRAINT CK_tblWorkflowIssues_IssueTypeCode;
END;

IF OBJECT_ID(N'dbo.tblWorkflowIssues', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowIssues')
         AND name = N'CK_tblWorkflowIssues_IssueTypeCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowIssues
    ADD CONSTRAINT CK_tblWorkflowIssues_IssueTypeCode
        CHECK (IssueTypeCode IN (N'BUG', N'GAP', N'RISK', N'DECISION', N'DATA', N'DEPENDENCY', N'CHANGE_REQUEST', N'OTHER'));
END;
