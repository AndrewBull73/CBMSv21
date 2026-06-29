USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'PriorityCode') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD PriorityCode NVARCHAR(20) NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_PriorityCode DEFAULT (N'NORMAL');
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'RecipientResponse') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD RecipientResponse NVARCHAR(MAX) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'RespondedByUserID') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD RespondedByUserID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'RespondedAt') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD RespondedAt DATETIME2(0) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'LastForwardedByUserID') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD LastForwardedByUserID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'LastForwardedAt') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD LastForwardedAt DATETIME2(0) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'LastForwardReason') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD LastForwardReason NVARCHAR(1000) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'NotifyCreatorOnCompletion') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD NotifyCreatorOnCompletion BIT NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_NotifyCreatorOnCompletion DEFAULT (1);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'NotifyCreatorOnUpdate') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD NotifyCreatorOnUpdate BIT NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_NotifyCreatorOnUpdate DEFAULT (0);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'NotifyAudienceOnComment') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD NotifyAudienceOnComment BIT NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_NotifyAudienceOnComment DEFAULT (1);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'AutoReminderEnabled') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD AutoReminderEnabled BIT NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_AutoReminderEnabled DEFAULT (0);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'AutoReminderDaysBeforeDue') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD AutoReminderDaysBeforeDue INT NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_AutoReminderDaysBeforeDue DEFAULT (1);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'AutoReminderSentAt') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD AutoReminderSentAt DATETIME2(0) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'LastManualReminderSentAt') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD LastManualReminderSentAt DATETIME2(0) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'LastManualReminderByUserID') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD LastManualReminderByUserID INT NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'OverdueEscalationEnabled') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD OverdueEscalationEnabled BIT NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_OverdueEscalationEnabled DEFAULT (0);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'OverdueEscalationDaysAfterDue') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD OverdueEscalationDaysAfterDue INT NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_OverdueEscalationDaysAfterDue DEFAULT (1);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'OverdueEscalationSentAt') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD OverdueEscalationSentAt DATETIME2(0) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowTaskBatchID') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD WorkflowTaskBatchID NVARCHAR(36) NULL;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTasks', N'WorkflowTaskCompletionRule') IS NULL
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD WorkflowTaskCompletionRule NVARCHAR(30) NOT NULL
        CONSTRAINT DF_tblWorkflowTasks_WorkflowTaskCompletionRule DEFAULT (N'INDIVIDUAL');
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'CK_tblWorkflowTasks_PriorityCode'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT CK_tblWorkflowTasks_PriorityCode
        CHECK (PriorityCode IN (N'LOW', N'NORMAL', N'HIGH', N'URGENT'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'CK_tblWorkflowTasks_WorkflowTaskCompletionRule'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT CK_tblWorkflowTasks_WorkflowTaskCompletionRule
        CHECK (WorkflowTaskCompletionRule IN (N'INDIVIDUAL', N'ANY_COMPLETES_ALL'));
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'CK_tblWorkflowTasks_AutoReminderDaysBeforeDue'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT CK_tblWorkflowTasks_AutoReminderDaysBeforeDue
        CHECK (AutoReminderDaysBeforeDue BETWEEN 0 AND 365);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.check_constraints
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'CK_tblWorkflowTasks_OverdueEscalationDaysAfterDue'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTasks
    ADD CONSTRAINT CK_tblWorkflowTasks_OverdueEscalationDaysAfterDue
        CHECK (OverdueEscalationDaysAfterDue BETWEEN 1 AND 365);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'IX_tblWorkflowTasks_Batch'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTasks_Batch
        ON dbo.tblWorkflowTasks (WorkflowTaskBatchID, WorkflowTaskCompletionRule, CompletedAt);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'IX_tblWorkflowTasks_AutoReminderDue'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTasks_AutoReminderDue
        ON dbo.tblWorkflowTasks (AutoReminderEnabled, AutoReminderSentAt, DueDate, CompletedAt)
        INCLUDE (AssignedToUserID, StatusID, AutoReminderDaysBeforeDue);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTasks')
         AND name = N'IX_tblWorkflowTasks_OverdueEscalationDue'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTasks_OverdueEscalationDue
        ON dbo.tblWorkflowTasks (OverdueEscalationEnabled, OverdueEscalationSentAt, DueDate, CompletedAt)
        INCLUDE (AssignedToUserID, CreatedByUserID, StatusID, OverdueEscalationDaysAfterDue);
END;
GO

IF OBJECT_ID(N'dbo.tblEmailTemplates', N'U') IS NOT NULL
BEGIN
    MERGE dbo.tblEmailTemplates AS target
    USING
    (
        VALUES
        (
            N'WORKFLOW_TASK_OVERDUE_ESCALATION',
            N'Workflow Task Overdue Escalation',
            N'Queued when the workflow reminder processor escalates an open task that is overdue.',
            N'Overdue task escalation: {{TASK_TITLE}}',
            N'<p>Hello {{RECIPIENT_NAME}},</p>
<p>Workflow task <strong>{{TASK_TITLE}}</strong> is overdue and has been escalated.</p>
<p><strong>Due Date:</strong> {{TASK_DUE_DATE}}<br>
<strong>Days Overdue:</strong> {{DAYS_OVERDUE}}<br>
<strong>Status:</strong> {{TASK_STATUS}}<br>
<strong>Priority:</strong> {{TASK_PRIORITY}}<br>
<strong>Assigned To:</strong> {{ASSIGNED_TO}}<br>
<strong>Created By:</strong> {{CREATOR_NAME}}</p>
<p>{{TASK_LINK}}</p>',
            N'Hello {{RECIPIENT_NAME}},

Workflow task {{TASK_TITLE}} is overdue and has been escalated.

Due Date: {{TASK_DUE_DATE}}
Days Overdue: {{DAYS_OVERDUE}}
Status: {{TASK_STATUS}}
Priority: {{TASK_PRIORITY}}
Assigned To: {{ASSIGNED_TO}}
Created By: {{CREATOR_NAME}}

{{TASK_URL}}'
        )
    ) AS source (TemplateKey, TemplateName, [Description], [Subject], BodyHtml, BodyText)
    ON target.TemplateKey = source.TemplateKey
    WHEN MATCHED THEN
        UPDATE SET
            TemplateName = source.TemplateName,
            [Description] = source.[Description],
            [Subject] = source.[Subject],
            BodyHtml = source.BodyHtml,
            BodyText = source.BodyText,
            Active = 1,
            UpdatedAt = SYSUTCDATETIME()
    WHEN NOT MATCHED THEN
        INSERT (TemplateKey, TemplateName, [Description], [Subject], BodyHtml, BodyText, Active, CreatedAt, UpdatedAt)
        VALUES (source.TemplateKey, source.TemplateName, source.[Description], source.[Subject], source.BodyHtml, source.BodyText, 1, SYSUTCDATETIME(), SYSUTCDATETIME());
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskStatuses', N'U') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTaskStatuses', N'Code') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTaskStatuses', N'Name') IS NOT NULL
   AND COL_LENGTH(N'dbo.tblWorkflowTaskStatuses', N'IsActive') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM dbo.tblWorkflowTaskStatuses
       WHERE UPPER(Code) IN (N'INPROGRESS', N'IN_PROGRESS')
   )
BEGIN
    INSERT INTO dbo.tblWorkflowTaskStatuses (Code, Name, IsActive)
    VALUES (N'INPROGRESS', N'In Progress', 1);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskActivity', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowTaskActivity
    (
        WorkflowTaskActivityID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowTaskActivity PRIMARY KEY,
        WorkflowTaskID INT NOT NULL,
        ActivityType NVARCHAR(30) NOT NULL,
        ActivityNote NVARCHAR(MAX) NULL,
        FromUserID INT NULL,
        ToUserID INT NULL,
        FromStatusID INT NULL,
        ToStatusID INT NULL,
        ActionByUserID INT NOT NULL,
        ActionAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowTaskActivity_ActionAt DEFAULT SYSUTCDATETIME(),
        MetadataJson NVARCHAR(MAX) NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskActivity', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskActivity')
         AND name = N'FK_tblWorkflowTaskActivity_Task'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskActivity
    ADD CONSTRAINT FK_tblWorkflowTaskActivity_Task
        FOREIGN KEY (WorkflowTaskID)
        REFERENCES dbo.tblWorkflowTasks (WorkflowTaskID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskActivity', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskActivity')
         AND name = N'IX_tblWorkflowTaskActivity_Task'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTaskActivity_Task
        ON dbo.tblWorkflowTaskActivity (WorkflowTaskID, ActionAt DESC, WorkflowTaskActivityID DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskActivity', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskActivity')
         AND name = N'IX_tblWorkflowTaskActivity_ActionBy'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTaskActivity_ActionBy
        ON dbo.tblWorkflowTaskActivity (ActionByUserID, ActionAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskAttachments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowTaskAttachments
    (
        WorkflowTaskAttachmentID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowTaskAttachments PRIMARY KEY,
        WorkflowTaskID INT NOT NULL,
        OriginalFileName NVARCHAR(255) NOT NULL,
        StoredFileName NVARCHAR(255) NOT NULL,
        StoragePath NVARCHAR(1000) NOT NULL,
        MimeType NVARCHAR(150) NULL,
        FileSizeBytes BIGINT NOT NULL,
        UploadedByUserID INT NOT NULL,
        UploadedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowTaskAttachments_UploadedAt DEFAULT SYSUTCDATETIME(),
        Deleted BIT NOT NULL CONSTRAINT DF_tblWorkflowTaskAttachments_Deleted DEFAULT (0),
        DeletedByUserID INT NULL,
        DeletedAt DATETIME2(0) NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskAttachments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskAttachments')
         AND name = N'FK_tblWorkflowTaskAttachments_Task'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskAttachments
    ADD CONSTRAINT FK_tblWorkflowTaskAttachments_Task
        FOREIGN KEY (WorkflowTaskID)
        REFERENCES dbo.tblWorkflowTasks (WorkflowTaskID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskAttachments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskAttachments')
         AND name = N'FK_tblWorkflowTaskAttachments_UploadedBy'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskAttachments
    ADD CONSTRAINT FK_tblWorkflowTaskAttachments_UploadedBy
        FOREIGN KEY (UploadedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskAttachments', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskAttachments')
         AND name = N'IX_tblWorkflowTaskAttachments_Task'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTaskAttachments_Task
        ON dbo.tblWorkflowTaskAttachments (WorkflowTaskID, Deleted, UploadedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskAttachments', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskAttachments')
         AND name = N'IX_tblWorkflowTaskAttachments_UploadedBy'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTaskAttachments_UploadedBy
        ON dbo.tblWorkflowTaskAttachments (UploadedByUserID, UploadedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskComments', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowTaskComments
    (
        WorkflowTaskCommentID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowTaskComments PRIMARY KEY,
        WorkflowTaskID INT NOT NULL,
        CommentText NVARCHAR(MAX) NOT NULL,
        CreatedByUserID INT NOT NULL,
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowTaskComments_CreatedAt DEFAULT SYSUTCDATETIME(),
        Deleted BIT NOT NULL CONSTRAINT DF_tblWorkflowTaskComments_Deleted DEFAULT (0),
        DeletedByUserID INT NULL,
        DeletedAt DATETIME2(0) NULL
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskComments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskComments')
         AND name = N'FK_tblWorkflowTaskComments_Task'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskComments
    ADD CONSTRAINT FK_tblWorkflowTaskComments_Task
        FOREIGN KEY (WorkflowTaskID)
        REFERENCES dbo.tblWorkflowTasks (WorkflowTaskID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskComments', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskComments')
         AND name = N'FK_tblWorkflowTaskComments_CreatedBy'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskComments
    ADD CONSTRAINT FK_tblWorkflowTaskComments_CreatedBy
        FOREIGN KEY (CreatedByUserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskComments', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskComments')
         AND name = N'IX_tblWorkflowTaskComments_Task'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTaskComments_Task
        ON dbo.tblWorkflowTaskComments (WorkflowTaskID, Deleted, CreatedAt DESC, WorkflowTaskCommentID DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskComments', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskComments')
         AND name = N'IX_tblWorkflowTaskComments_CreatedBy'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTaskComments_CreatedBy
        ON dbo.tblWorkflowTaskComments (CreatedByUserID, CreatedAt DESC);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskViews', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblWorkflowTaskViews
    (
        WorkflowTaskViewID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblWorkflowTaskViews PRIMARY KEY,
        WorkflowTaskID INT NOT NULL,
        UserID INT NOT NULL,
        FirstViewedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowTaskViews_FirstViewedAt DEFAULT SYSUTCDATETIME(),
        LastViewedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblWorkflowTaskViews_LastViewedAt DEFAULT SYSUTCDATETIME(),
        ViewCount INT NOT NULL CONSTRAINT DF_tblWorkflowTaskViews_ViewCount DEFAULT (1)
    );
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskViews', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblWorkflowTasks', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskViews')
         AND name = N'FK_tblWorkflowTaskViews_Task'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskViews
    ADD CONSTRAINT FK_tblWorkflowTaskViews_Task
        FOREIGN KEY (WorkflowTaskID)
        REFERENCES dbo.tblWorkflowTasks (WorkflowTaskID)
        ON DELETE CASCADE;
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskViews', N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.tblUsers', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.foreign_keys
       WHERE parent_object_id = OBJECT_ID(N'dbo.tblWorkflowTaskViews')
         AND name = N'FK_tblWorkflowTaskViews_User'
   )
BEGIN
    ALTER TABLE dbo.tblWorkflowTaskViews
    ADD CONSTRAINT FK_tblWorkflowTaskViews_User
        FOREIGN KEY (UserID)
        REFERENCES dbo.tblUsers (UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskViews', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskViews')
         AND name = N'UX_tblWorkflowTaskViews_TaskUser'
   )
BEGIN
    CREATE UNIQUE INDEX UX_tblWorkflowTaskViews_TaskUser
        ON dbo.tblWorkflowTaskViews (WorkflowTaskID, UserID);
END;
GO

IF OBJECT_ID(N'dbo.tblWorkflowTaskViews', N'U') IS NOT NULL
   AND NOT EXISTS (
       SELECT 1
       FROM sys.indexes
       WHERE object_id = OBJECT_ID(N'dbo.tblWorkflowTaskViews')
         AND name = N'IX_tblWorkflowTaskViews_User'
   )
BEGIN
    CREATE INDEX IX_tblWorkflowTaskViews_User
        ON dbo.tblWorkflowTaskViews (UserID, LastViewedAt DESC);
END;
GO
