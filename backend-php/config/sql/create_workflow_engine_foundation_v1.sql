SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;
GO

IF OBJECT_ID(N'dbo.tblWorkflowDefinition', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowDefinition
    (
        WorkflowDefinitionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WorkflowAreaCode NVARCHAR(50) NOT NULL,
        WorkflowAreaName NVARCHAR(150) NOT NULL,
        ModuleCode NVARCHAR(50) NULL,
        RecordTableName NVARCHAR(128) NULL,
        Description NVARCHAR(500) NULL,
        RouteByDataObjectHierarchy BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinition_RouteByDataObjectHierarchy DEFAULT (1),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinition_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowDefinition_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowDefinition_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowDefinition')
      AND name = N'UX_tblWorkflowDefinition_WorkflowAreaCode'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowDefinition_WorkflowAreaCode
        ON dbo.tblWorkflowDefinition (WorkflowAreaCode);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowDefinitionStage', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowDefinitionStage
    (
        WorkflowDefinitionStageID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WorkflowDefinitionID INT NOT NULL,
        WorkflowAreaCode NVARCHAR(50) NOT NULL,
        WorkflowStageCode NVARCHAR(50) NOT NULL,
        WorkflowStageName NVARCHAR(150) NOT NULL,
        StageOrder INT NOT NULL,
        StageType NVARCHAR(20) NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_StageType DEFAULT (N'OTHER'),
        RequiredPermissionCodes NVARCHAR(500) NULL,
        RouteByDataObjectHierarchy BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_RouteByDataObjectHierarchy DEFAULT (1),
        AllowReturn BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_AllowReturn DEFAULT (0),
        AllowReject BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_AllowReject DEFAULT (0),
        AllowCancel BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_AllowCancel DEFAULT (0),
        AllowsDelegation BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_AllowsDelegation DEFAULT (0),
        RequireDifferentActorFromPreviousStage BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_RequireDifferentActorFromPreviousStage DEFAULT (0),
        IsDraftStage BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_IsDraftStage DEFAULT (0),
        IsFinalStage BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_IsFinalStage DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowDefinitionStage_UpdatedDate DEFAULT (SYSDATETIME()),
        CONSTRAINT CK_tblWorkflowDefinitionStage_StageType CHECK (
            StageType IN (N'START', N'REVIEW', N'APPROVAL', N'PUBLISH', N'END', N'CANCEL', N'OTHER')
        )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblWorkflowDefinitionStage_Definition'
      AND parent_object_id = OBJECT_ID(N'dbo.tblWorkflowDefinitionStage')
)
BEGIN
    ALTER TABLE dbo.tblWorkflowDefinitionStage
    ADD CONSTRAINT FK_tblWorkflowDefinitionStage_Definition
        FOREIGN KEY (WorkflowDefinitionID)
        REFERENCES dbo.tblWorkflowDefinition (WorkflowDefinitionID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowDefinitionStage')
      AND name = N'UX_tblWorkflowDefinitionStage_AreaStage'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowDefinitionStage_AreaStage
        ON dbo.tblWorkflowDefinitionStage (WorkflowAreaCode, WorkflowStageCode);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowDefinitionStage')
      AND name = N'UX_tblWorkflowDefinitionStage_AreaOrder'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowDefinitionStage_AreaOrder
        ON dbo.tblWorkflowDefinitionStage (WorkflowAreaCode, StageOrder);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowDefinitionAction', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowDefinitionAction
    (
        WorkflowDefinitionActionID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WorkflowDefinitionID INT NOT NULL,
        WorkflowAreaCode NVARCHAR(50) NOT NULL,
        WorkflowActionCode NVARCHAR(50) NOT NULL,
        WorkflowActionName NVARCHAR(150) NOT NULL,
        FromStageCode NVARCHAR(50) NOT NULL,
        ToStageCode NVARCHAR(50) NOT NULL,
        ActionType NVARCHAR(20) NOT NULL CONSTRAINT DF_tblWorkflowDefinitionAction_ActionType DEFAULT (N'OTHER'),
        RequiredPermissionCodes NVARCHAR(500) NULL,
        RequireNote BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionAction_RequireNote DEFAULT (0),
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblWorkflowDefinitionAction_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowDefinitionAction_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowDefinitionAction_UpdatedDate DEFAULT (SYSDATETIME()),
        CONSTRAINT CK_tblWorkflowDefinitionAction_ActionType CHECK (
            ActionType IN (N'SUBMIT', N'FORWARD', N'RETURN', N'APPROVE', N'REJECT', N'CANCEL', N'REOPEN', N'LOCK', N'PUBLISH', N'OTHER')
        )
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblWorkflowDefinitionAction_Definition'
      AND parent_object_id = OBJECT_ID(N'dbo.tblWorkflowDefinitionAction')
)
BEGIN
    ALTER TABLE dbo.tblWorkflowDefinitionAction
    ADD CONSTRAINT FK_tblWorkflowDefinitionAction_Definition
        FOREIGN KEY (WorkflowDefinitionID)
        REFERENCES dbo.tblWorkflowDefinition (WorkflowDefinitionID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowDefinitionAction')
      AND name = N'UX_tblWorkflowDefinitionAction_AreaActionTransition'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowDefinitionAction_AreaActionTransition
        ON dbo.tblWorkflowDefinitionAction (WorkflowAreaCode, WorkflowActionCode, FromStageCode, ToStageCode);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowInstance', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowInstance
    (
        WorkflowInstanceID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WorkflowDefinitionID INT NOT NULL,
        WorkflowAreaCode NVARCHAR(50) NOT NULL,
        RecordTableName NVARCHAR(128) NOT NULL,
        RecordID INT NULL,
        RecordKey NVARCHAR(100) NOT NULL,
        FiscalYearID INT NULL,
        VersionID INT NULL,
        DataObjectCode NVARCHAR(50) NULL,
        ScopeDataObjectCode NVARCHAR(50) NULL,
        CurrentStageCode NVARCHAR(50) NOT NULL,
        CurrentStatusCode NVARCHAR(50) NOT NULL,
        CurrentAssignmentScopeCode NVARCHAR(50) NULL,
        WorkflowTitle NVARCHAR(255) NULL,
        WorkflowNote NVARCHAR(1000) NULL,
        SubmittedBy INT NULL,
        SubmittedDate DATETIME2(0) NULL,
        ApprovedBy INT NULL,
        ApprovedDate DATETIME2(0) NULL,
        CancelledBy INT NULL,
        CancelledDate DATETIME2(0) NULL,
        LastActionCode NVARCHAR(50) NULL,
        LastActionBy INT NULL,
        LastActionDate DATETIME2(0) NULL,
        ActiveFlag BIT NOT NULL CONSTRAINT DF_tblWorkflowInstance_ActiveFlag DEFAULT (1),
        CreatedBy INT NULL,
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowInstance_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowInstance_UpdatedDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblWorkflowInstance_Definition'
      AND parent_object_id = OBJECT_ID(N'dbo.tblWorkflowInstance')
)
BEGIN
    ALTER TABLE dbo.tblWorkflowInstance
    ADD CONSTRAINT FK_tblWorkflowInstance_Definition
        FOREIGN KEY (WorkflowDefinitionID)
        REFERENCES dbo.tblWorkflowDefinition (WorkflowDefinitionID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowInstance')
      AND name = N'UX_tblWorkflowInstance_Record'
)
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowInstance_Record
        ON dbo.tblWorkflowInstance (WorkflowAreaCode, RecordTableName, RecordKey);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowInstance')
      AND name = N'IX_tblWorkflowInstance_Lookup'
)
BEGIN
    CREATE INDEX IX_tblWorkflowInstance_Lookup
        ON dbo.tblWorkflowInstance (WorkflowAreaCode, FiscalYearID, VersionID, ScopeDataObjectCode, CurrentStageCode, CurrentStatusCode, ActiveFlag);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowInstanceHistory', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowInstanceHistory
    (
        WorkflowInstanceHistoryID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        WorkflowInstanceID INT NOT NULL,
        WorkflowAreaCode NVARCHAR(50) NOT NULL,
        WorkflowActionCode NVARCHAR(50) NOT NULL,
        FromStageCode NVARCHAR(50) NULL,
        ToStageCode NVARCHAR(50) NOT NULL,
        AssignmentScopeCode NVARCHAR(50) NULL,
        AssignmentUserID INT NULL,
        ActionNote NVARCHAR(1000) NULL,
        ActionBy INT NOT NULL,
        ActionDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowInstanceHistory_ActionDate DEFAULT (SYSDATETIME())
    );
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = N'FK_tblWorkflowInstanceHistory_Instance'
      AND parent_object_id = OBJECT_ID(N'dbo.tblWorkflowInstanceHistory')
)
BEGIN
    ALTER TABLE dbo.tblWorkflowInstanceHistory
    ADD CONSTRAINT FK_tblWorkflowInstanceHistory_Instance
        FOREIGN KEY (WorkflowInstanceID)
        REFERENCES dbo.tblWorkflowInstance (WorkflowInstanceID);
END;
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowInstanceHistory')
      AND name = N'IX_tblWorkflowInstanceHistory_Instance'
)
BEGIN
    CREATE INDEX IX_tblWorkflowInstanceHistory_Instance
        ON dbo.tblWorkflowInstanceHistory (WorkflowInstanceID, ActionDate DESC, WorkflowInstanceHistoryID DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowAssignments', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowAssignments', N'AssignmentMode') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowAssignments
    ADD AssignmentMode NVARCHAR(20) NOT NULL
        CONSTRAINT DF_tblWorkflowAssignments_AssignmentMode DEFAULT (N'USER');
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowAssignments', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowAssignments', N'InheritFromParentScope') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowAssignments
    ADD InheritFromParentScope BIT NOT NULL
        CONSTRAINT DF_tblWorkflowAssignments_InheritFromParentScope DEFAULT (1);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowAssignments', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowAssignments', N'RouteByDataObjectHierarchy') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowAssignments
    ADD RouteByDataObjectHierarchy BIT NOT NULL
        CONSTRAINT DF_tblWorkflowAssignments_RouteByDataObjectHierarchy DEFAULT (1);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowAssignments', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowAssignments', N'RoleID') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowAssignments
    ADD RoleID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowAssignments', N'U') IS NOT NULL
   AND NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowAssignments')
      AND name = N'IX_tblWorkflowAssignments_Hierarchy'
)
BEGIN
    CREATE INDEX IX_tblWorkflowAssignments_Hierarchy
        ON dbo.tblWorkflowAssignments (WorkflowAreaCode, WorkflowStageCode, FiscalYearID, VersionID, DataObjectCode, RouteByDataObjectHierarchy, InheritFromParentScope, ActiveFlag, SequenceNo);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'STRATEGIC_VERSION'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'STRATEGIC_VERSION', N'Strategic Version', N'STRATEGY', N'dbo.tblVersions', N'Shared workflow definition for strategic version governance.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'FUNDING_REQUEST'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'FUNDING_REQUEST', N'Funding Request', N'BUDGET_SUBMISSION', N'dbo.tblSbFundingSubmission', N'Shared workflow definition for funding submission requests.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'SEGMENT_PUBLICATION'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'SEGMENT_PUBLICATION', N'Segment Publication', N'STRATEGY', N'dbo.tblSbSegmentPublishRequest', N'Shared workflow definition for publishing approved segment values.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'BUDGET_SUBMISSION'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'BUDGET_SUBMISSION', N'Budget Submission', N'BUDGET_SUBMISSION', N'dbo.tblSbFundingSubmission', N'General workflow definition for budget submission processing.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'BUDGET_EXECUTION'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'BUDGET_EXECUTION', N'Budget Execution', N'BUDGET_EXECUTION', NULL, N'General workflow definition for execution documents.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'BE_WARRANT'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'BE_WARRANT', N'Execution Warrant', N'BUDGET_EXECUTION', N'dbo.tblBeWarrant', N'Workflow definition for execution warrant approval.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'BE_RESERVATION'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'BE_RESERVATION', N'Execution Reservation', N'BUDGET_EXECUTION', N'dbo.tblBeReservation', N'Workflow definition for execution reservation approval.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'BE_SUPPLEMENTARY'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'BE_SUPPLEMENTARY', N'Execution Supplementary Budget', N'BUDGET_EXECUTION', N'dbo.tblBeSupplementaryBudget', N'Workflow definition for supplementary budget approval.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'BE_COMMITMENT'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'BE_COMMITMENT', N'Execution Commitment', N'BUDGET_EXECUTION', N'dbo.tblBeCommitment', N'Workflow definition for commitment approval.', 1, 1, 1, 1);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM dbo.tblWorkflowDefinition WHERE WorkflowAreaCode = N'BE_RIE'
)
BEGIN
    INSERT INTO dbo.tblWorkflowDefinition (WorkflowAreaCode, WorkflowAreaName, ModuleCode, RecordTableName, Description, RouteByDataObjectHierarchy, ActiveFlag, CreatedBy, UpdatedBy)
    VALUES (N'BE_RIE', N'Execution RIE', N'BUDGET_EXECUTION', N'dbo.tblBeRie', N'Workflow definition for Request to Incur Expenditure approval.', 1, 1, 1, 1);
END;
GO

;WITH SeedStage AS (
    SELECT N'STRATEGIC_VERSION' AS WorkflowAreaCode, N'DRAFT' AS WorkflowStageCode, N'Draft' AS WorkflowStageName, 10 AS StageOrder, N'START' AS StageType, NULL AS RequiredPermissionCodes, 1 AS AllowReturn, 0 AS AllowReject, 1 AS AllowCancel, 0 AS AllowsDelegation, 0 AS RequireDifferentActorFromPreviousStage, 1 AS IsDraftStage, 0 AS IsFinalStage
    UNION ALL SELECT N'STRATEGIC_VERSION', N'REVIEW', N'Review', 20, N'REVIEW', N'STRATEGY_VIEW,STRATEGY_WORKFLOW_EDIT,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'STRATEGIC_VERSION', N'APPROVAL', N'Approval', 30, N'APPROVAL', N'STRATEGY_WORKFLOW_EDIT,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'STRATEGIC_VERSION', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1
    UNION ALL SELECT N'STRATEGIC_VERSION', N'LOCKED', N'Locked', 50, N'END', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'FUNDING_REQUEST', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'FUNDING_REQUEST', N'REVIEW', N'Review', 20, N'REVIEW', N'STRATEGY_SUBMISSION_REVIEW,STRATEGY_SUBMISSION_APPROVE,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'FUNDING_REQUEST', N'APPROVAL', N'Approval', 30, N'APPROVAL', N'STRATEGY_SUBMISSION_APPROVE,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'FUNDING_REQUEST', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1
    UNION ALL SELECT N'FUNDING_REQUEST', N'PUBLISHED', N'Published', 50, N'END', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'REVIEW', N'Review', 20, N'REVIEW', N'STRATEGY_CONFIG_EDIT,STRATEGY_SETUP_EDIT,STRATEGY_SEGMENT_PUBLISH,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'APPROVAL', N'Approval', 30, N'APPROVAL', N'STRATEGY_SEGMENT_PUBLISH,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'PUBLISH', N'Publish', 40, N'PUBLISH', N'STRATEGY_SEGMENT_PUBLISH,ADMIN_ALL,SYSADMIN', 0, 0, 0, 0, 1, 0, 0
    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'PUBLISHED', N'Published', 50, N'END', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'BUDGET_SUBMISSION', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'BUDGET_SUBMISSION', N'TECHNICAL_REVIEW', N'Technical Review', 20, N'REVIEW', N'ESTIMATES_VIEW,ESTIMATES_EDIT,FIN_CONFIG_EDIT,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'BUDGET_SUBMISSION', N'APPROVAL', N'Approval', 30, N'APPROVAL', N'ESTIMATES_EDIT,FIN_CONFIG_EDIT,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'BUDGET_SUBMISSION', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'BUDGET_EXECUTION', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'BUDGET_EXECUTION', N'TECHNICAL_REVIEW', N'Technical Review', 20, N'REVIEW', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'BUDGET_EXECUTION', N'FINAL_APPROVAL', N'Final Approval', 30, N'APPROVAL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'BUDGET_EXECUTION', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'BE_WARRANT', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'BE_WARRANT', N'TECHNICAL_REVIEW', N'Technical Review', 20, N'REVIEW', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'BE_WARRANT', N'FINAL_APPROVAL', N'Final Approval', 30, N'APPROVAL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'BE_WARRANT', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1
    UNION ALL SELECT N'BE_WARRANT', N'CANCELLED', N'Cancelled', 50, N'CANCEL', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'BE_RESERVATION', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'BE_RESERVATION', N'TECHNICAL_REVIEW', N'Technical Review', 20, N'REVIEW', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'BE_RESERVATION', N'FINAL_APPROVAL', N'Final Approval', 30, N'APPROVAL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'BE_RESERVATION', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1
    UNION ALL SELECT N'BE_RESERVATION', N'CANCELLED', N'Cancelled', 50, N'CANCEL', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'TECHNICAL_REVIEW', N'Technical Review', 20, N'REVIEW', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 1, 0, 0, 0
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'FINAL_APPROVAL', N'Final Approval', 30, N'APPROVAL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 1, 1, 0, 0
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'CANCELLED', N'Cancelled', 50, N'CANCEL', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'BE_COMMITMENT', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'BE_COMMITMENT', N'TECHNICAL_REVIEW', N'Technical Review', 20, N'REVIEW', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'BE_COMMITMENT', N'FINAL_APPROVAL', N'Final Approval', 30, N'APPROVAL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'BE_COMMITMENT', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1
    UNION ALL SELECT N'BE_COMMITMENT', N'CANCELLED', N'Cancelled', 50, N'CANCEL', NULL, 0, 0, 0, 0, 0, 0, 1

    UNION ALL SELECT N'BE_RIE', N'DRAFT', N'Draft', 10, N'START', NULL, 1, 0, 1, 0, 0, 1, 0
    UNION ALL SELECT N'BE_RIE', N'TECHNICAL_REVIEW', N'Technical Review', 20, N'REVIEW', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 0, 0, 0
    UNION ALL SELECT N'BE_RIE', N'FINAL_APPROVAL', N'Final Approval', 30, N'APPROVAL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1, 1, 1, 0, 1, 0, 0
    UNION ALL SELECT N'BE_RIE', N'APPROVED', N'Approved', 40, N'END', NULL, 0, 0, 0, 0, 0, 0, 1
    UNION ALL SELECT N'BE_RIE', N'CANCELLED', N'Cancelled', 50, N'CANCEL', NULL, 0, 0, 0, 0, 0, 0, 1
)
INSERT INTO dbo.tblWorkflowDefinitionStage
(
    WorkflowDefinitionID,
    WorkflowAreaCode,
    WorkflowStageCode,
    WorkflowStageName,
    StageOrder,
    StageType,
    RequiredPermissionCodes,
    RouteByDataObjectHierarchy,
    AllowReturn,
    AllowReject,
    AllowCancel,
    AllowsDelegation,
    RequireDifferentActorFromPreviousStage,
    IsDraftStage,
    IsFinalStage,
    ActiveFlag,
    CreatedBy,
    UpdatedBy
)
SELECT
    d.WorkflowDefinitionID,
    s.WorkflowAreaCode,
    s.WorkflowStageCode,
    s.WorkflowStageName,
    s.StageOrder,
    s.StageType,
    s.RequiredPermissionCodes,
    1,
    s.AllowReturn,
    s.AllowReject,
    s.AllowCancel,
    s.AllowsDelegation,
    s.RequireDifferentActorFromPreviousStage,
    s.IsDraftStage,
    s.IsFinalStage,
    1,
    1,
    1
FROM SeedStage s
INNER JOIN dbo.tblWorkflowDefinition d
    ON d.WorkflowAreaCode = s.WorkflowAreaCode
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblWorkflowDefinitionStage x
    WHERE x.WorkflowAreaCode = s.WorkflowAreaCode
      AND x.WorkflowStageCode = s.WorkflowStageCode
);
GO

;WITH SeedAction AS (
    SELECT N'STRATEGIC_VERSION' AS WorkflowAreaCode, N'SUBMIT' AS WorkflowActionCode, N'Submit For Review' AS WorkflowActionName, N'DRAFT' AS FromStageCode, N'REVIEW' AS ToStageCode, N'SUBMIT' AS ActionType, NULL AS RequiredPermissionCodes, 0 AS RequireNote
    UNION ALL SELECT N'STRATEGIC_VERSION', N'FORWARD', N'Forward For Approval', N'REVIEW', N'APPROVAL', N'FORWARD', N'STRATEGY_WORKFLOW_EDIT,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'STRATEGIC_VERSION', N'RETURN', N'Return To Draft', N'REVIEW', N'DRAFT', N'RETURN', N'STRATEGY_VIEW,STRATEGY_WORKFLOW_EDIT,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'STRATEGIC_VERSION', N'APPROVE', N'Approve', N'APPROVAL', N'APPROVED', N'APPROVE', N'STRATEGY_WORKFLOW_EDIT,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'STRATEGIC_VERSION', N'LOCK', N'Lock', N'APPROVED', N'LOCKED', N'LOCK', N'STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 0

    UNION ALL SELECT N'FUNDING_REQUEST', N'SUBMIT', N'Submit For Review', N'DRAFT', N'REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'FUNDING_REQUEST', N'FORWARD', N'Forward For Approval', N'REVIEW', N'APPROVAL', N'FORWARD', N'STRATEGY_SUBMISSION_REVIEW,STRATEGY_SUBMISSION_APPROVE,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'FUNDING_REQUEST', N'RETURN', N'Return To Draft', N'REVIEW', N'DRAFT', N'RETURN', N'STRATEGY_SUBMISSION_REVIEW,STRATEGY_SUBMISSION_APPROVE,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'FUNDING_REQUEST', N'APPROVE', N'Approve', N'APPROVAL', N'APPROVED', N'APPROVE', N'STRATEGY_SUBMISSION_APPROVE,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'FUNDING_REQUEST', N'PUBLISH', N'Publish', N'APPROVED', N'PUBLISHED', N'PUBLISH', N'STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'FUNDING_REQUEST', N'REJECT', N'Reject', N'APPROVAL', N'DRAFT', N'REJECT', N'STRATEGY_SUBMISSION_APPROVE,STRATEGY_PUBLISH,ADMIN_ALL,SYSADMIN', 1

    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'SUBMIT', N'Submit For Review', N'DRAFT', N'REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'FORWARD', N'Forward For Approval', N'REVIEW', N'APPROVAL', N'FORWARD', N'STRATEGY_CONFIG_EDIT,STRATEGY_SETUP_EDIT,STRATEGY_SEGMENT_PUBLISH,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'APPROVE', N'Approve', N'APPROVAL', N'PUBLISH', N'APPROVE', N'STRATEGY_SEGMENT_PUBLISH,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'PUBLISH', N'Publish', N'PUBLISH', N'PUBLISHED', N'PUBLISH', N'STRATEGY_SEGMENT_PUBLISH,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'SEGMENT_PUBLICATION', N'RETURN', N'Return To Draft', N'REVIEW', N'DRAFT', N'RETURN', N'STRATEGY_CONFIG_EDIT,STRATEGY_SETUP_EDIT,STRATEGY_SEGMENT_PUBLISH,ADMIN_ALL,SYSADMIN', 1

    UNION ALL SELECT N'BUDGET_SUBMISSION', N'SUBMIT', N'Submit For Review', N'DRAFT', N'TECHNICAL_REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'BUDGET_SUBMISSION', N'FORWARD', N'Forward For Approval', N'TECHNICAL_REVIEW', N'APPROVAL', N'FORWARD', N'ESTIMATES_VIEW,ESTIMATES_EDIT,FIN_CONFIG_EDIT,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BUDGET_SUBMISSION', N'APPROVE', N'Approve', N'APPROVAL', N'APPROVED', N'APPROVE', N'ESTIMATES_EDIT,FIN_CONFIG_EDIT,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BUDGET_SUBMISSION', N'RETURN', N'Return To Draft', N'TECHNICAL_REVIEW', N'DRAFT', N'RETURN', N'ESTIMATES_VIEW,ESTIMATES_EDIT,FIN_CONFIG_EDIT,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BUDGET_SUBMISSION', N'REJECT', N'Reject', N'APPROVAL', N'DRAFT', N'REJECT', N'ESTIMATES_EDIT,FIN_CONFIG_EDIT,ADMIN_ALL,SYSADMIN', 1

    UNION ALL SELECT N'BUDGET_EXECUTION', N'SUBMIT', N'Submit For Review', N'DRAFT', N'TECHNICAL_REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'BUDGET_EXECUTION', N'FORWARD', N'Forward For Final Approval', N'TECHNICAL_REVIEW', N'FINAL_APPROVAL', N'FORWARD', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BUDGET_EXECUTION', N'APPROVE', N'Approve', N'FINAL_APPROVAL', N'APPROVED', N'APPROVE', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BUDGET_EXECUTION', N'RETURN', N'Return To Draft', N'TECHNICAL_REVIEW', N'DRAFT', N'RETURN', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1

    UNION ALL SELECT N'BE_WARRANT', N'SUBMIT', N'Submit For Review', N'DRAFT', N'TECHNICAL_REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'BE_WARRANT', N'FORWARD', N'Forward For Final Approval', N'TECHNICAL_REVIEW', N'FINAL_APPROVAL', N'FORWARD', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_WARRANT', N'APPROVE', N'Approve', N'FINAL_APPROVAL', N'APPROVED', N'APPROVE', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_WARRANT', N'RETURN', N'Return To Draft', N'TECHNICAL_REVIEW', N'DRAFT', N'RETURN', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_WARRANT', N'RETURN', N'Return To Technical Review', N'FINAL_APPROVAL', N'TECHNICAL_REVIEW', N'RETURN', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_WARRANT', N'CANCEL', N'Cancel', N'DRAFT', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_WARRANT', N'CANCEL', N'Cancel', N'TECHNICAL_REVIEW', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_WARRANT', N'CANCEL', N'Cancel', N'FINAL_APPROVAL', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_WARRANT', N'CANCEL', N'Cancel', N'APPROVED', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1

    UNION ALL SELECT N'BE_RESERVATION', N'SUBMIT', N'Submit For Review', N'DRAFT', N'TECHNICAL_REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'BE_RESERVATION', N'FORWARD', N'Forward For Final Approval', N'TECHNICAL_REVIEW', N'FINAL_APPROVAL', N'FORWARD', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_RESERVATION', N'APPROVE', N'Approve', N'FINAL_APPROVAL', N'APPROVED', N'APPROVE', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_RESERVATION', N'RETURN', N'Return To Draft', N'TECHNICAL_REVIEW', N'DRAFT', N'RETURN', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RESERVATION', N'RETURN', N'Return To Technical Review', N'FINAL_APPROVAL', N'TECHNICAL_REVIEW', N'RETURN', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RESERVATION', N'CANCEL', N'Cancel', N'DRAFT', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RESERVATION', N'CANCEL', N'Cancel', N'TECHNICAL_REVIEW', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RESERVATION', N'CANCEL', N'Cancel', N'FINAL_APPROVAL', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RESERVATION', N'CANCEL', N'Cancel', N'APPROVED', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1

    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'SUBMIT', N'Submit For Review', N'DRAFT', N'TECHNICAL_REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'FORWARD', N'Forward For Final Approval', N'TECHNICAL_REVIEW', N'FINAL_APPROVAL', N'FORWARD', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'APPROVE', N'Approve', N'FINAL_APPROVAL', N'APPROVED', N'APPROVE', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'RETURN', N'Return To Draft', N'TECHNICAL_REVIEW', N'DRAFT', N'RETURN', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'RETURN', N'Return To Technical Review', N'FINAL_APPROVAL', N'TECHNICAL_REVIEW', N'RETURN', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'CANCEL', N'Cancel', N'DRAFT', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'CANCEL', N'Cancel', N'TECHNICAL_REVIEW', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'CANCEL', N'Cancel', N'FINAL_APPROVAL', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_SUPPLEMENTARY', N'CANCEL', N'Cancel', N'APPROVED', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1

    UNION ALL SELECT N'BE_COMMITMENT', N'SUBMIT', N'Submit For Review', N'DRAFT', N'TECHNICAL_REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'BE_COMMITMENT', N'FORWARD', N'Forward For Final Approval', N'TECHNICAL_REVIEW', N'FINAL_APPROVAL', N'FORWARD', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_COMMITMENT', N'APPROVE', N'Approve', N'FINAL_APPROVAL', N'APPROVED', N'APPROVE', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_COMMITMENT', N'RETURN', N'Return To Draft', N'TECHNICAL_REVIEW', N'DRAFT', N'RETURN', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_COMMITMENT', N'RETURN', N'Return To Technical Review', N'FINAL_APPROVAL', N'TECHNICAL_REVIEW', N'RETURN', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_COMMITMENT', N'CANCEL', N'Cancel', N'DRAFT', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_COMMITMENT', N'CANCEL', N'Cancel', N'TECHNICAL_REVIEW', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_COMMITMENT', N'CANCEL', N'Cancel', N'FINAL_APPROVAL', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_COMMITMENT', N'CANCEL', N'Cancel', N'APPROVED', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1

    UNION ALL SELECT N'BE_RIE', N'SUBMIT', N'Submit For Review', N'DRAFT', N'TECHNICAL_REVIEW', N'SUBMIT', NULL, 0
    UNION ALL SELECT N'BE_RIE', N'FORWARD', N'Forward For Final Approval', N'TECHNICAL_REVIEW', N'FINAL_APPROVAL', N'FORWARD', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_RIE', N'APPROVE', N'Approve', N'FINAL_APPROVAL', N'APPROVED', N'APPROVE', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 0
    UNION ALL SELECT N'BE_RIE', N'RETURN', N'Return To Draft', N'TECHNICAL_REVIEW', N'DRAFT', N'RETURN', N'WORKFLOW_VIEW,WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RIE', N'RETURN', N'Return To Technical Review', N'FINAL_APPROVAL', N'TECHNICAL_REVIEW', N'RETURN', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RIE', N'CANCEL', N'Cancel', N'DRAFT', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RIE', N'CANCEL', N'Cancel', N'TECHNICAL_REVIEW', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RIE', N'CANCEL', N'Cancel', N'FINAL_APPROVAL', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
    UNION ALL SELECT N'BE_RIE', N'CANCEL', N'Cancel', N'APPROVED', N'CANCELLED', N'CANCEL', N'WORKFLOW_EDIT,WORKFLOW_ADMIN,ADMIN_ALL,SYSADMIN', 1
)
INSERT INTO dbo.tblWorkflowDefinitionAction
(
    WorkflowDefinitionID,
    WorkflowAreaCode,
    WorkflowActionCode,
    WorkflowActionName,
    FromStageCode,
    ToStageCode,
    ActionType,
    RequiredPermissionCodes,
    RequireNote,
    ActiveFlag,
    CreatedBy,
    UpdatedBy
)
SELECT
    d.WorkflowDefinitionID,
    a.WorkflowAreaCode,
    a.WorkflowActionCode,
    a.WorkflowActionName,
    a.FromStageCode,
    a.ToStageCode,
    a.ActionType,
    a.RequiredPermissionCodes,
    a.RequireNote,
    1,
    1,
    1
FROM SeedAction a
INNER JOIN dbo.tblWorkflowDefinition d
    ON d.WorkflowAreaCode = a.WorkflowAreaCode
WHERE NOT EXISTS (
    SELECT 1
    FROM dbo.tblWorkflowDefinitionAction x
    WHERE x.WorkflowAreaCode = a.WorkflowAreaCode
      AND x.WorkflowActionCode = a.WorkflowActionCode
      AND x.FromStageCode = a.FromStageCode
      AND x.ToStageCode = a.ToStageCode
);
GO
