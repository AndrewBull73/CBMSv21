USE [CBMSv2];
GO

SET NOCOUNT ON;
GO

IF OBJECT_ID(N'dbo.tblEmailTemplates', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblEmailTemplates
    (
        EmailTemplateID INT IDENTITY(1,1) NOT NULL CONSTRAINT PK_tblEmailTemplates PRIMARY KEY,
        TemplateKey NVARCHAR(100) NOT NULL,
        TemplateName NVARCHAR(255) NOT NULL,
        [Description] NVARCHAR(500) NULL,
        [Subject] NVARCHAR(255) NOT NULL,
        BodyHtml NVARCHAR(MAX) NOT NULL,
        BodyText NVARCHAR(MAX) NULL,
        Active BIT NOT NULL CONSTRAINT DF_tblEmailTemplates_Active DEFAULT (1),
        CreatedAt DATETIME2(0) NOT NULL CONSTRAINT DF_tblEmailTemplates_CreatedAt DEFAULT SYSUTCDATETIME(),
        CreatedBy INT NULL,
        UpdatedAt DATETIME2(0) NULL,
        UpdatedBy INT NULL
    );

    CREATE UNIQUE INDEX UX_tblEmailTemplates_TemplateKey
        ON dbo.tblEmailTemplates (TemplateKey);
END;
GO

MERGE dbo.tblEmailTemplates AS target
USING
(
    VALUES
    (
        N'USER_WELCOME_INVITE',
        N'New User Welcome Invite',
        N'Queued when an administrator creates a new user and chooses the email invite onboarding option.',
        N'Welcome to {{APP_NAME}}',
        N'<p>Hello {{DISPLAY_NAME}},</p>
<p>Your {{APP_NAME}} account has been created.</p>
<p>Username: <strong>{{USERNAME}}</strong></p>
<p>Use this secure link to sign in and set your password:</p>
<p>{{CBMS_SECURE_LOGIN_LINK}}</p>
<p>Password requirements: use at least 10 characters including uppercase, lowercase, and numeric characters.</p>
<p>This link expires at {{EXPIRES_AT}}.</p>
<p>If you were not expecting this message, please contact your system administrator.</p>',
        N'Hello {{DISPLAY_NAME}},

Your {{APP_NAME}} account has been created.

Username: {{USERNAME}}
Secure login link: {{CBMS_SECURE_LOGIN_URL}}

Password requirements: use at least 10 characters including uppercase, lowercase, and numeric characters.

This link expires at {{EXPIRES_AT}}.

If you were not expecting this message, please contact your system administrator.'
    ),
    (
        N'WORKFLOW_TASK_COMPLETED',
        N'Workflow Task Completed',
        N'Queued when a workflow task is completed and the task creator opted to receive completion confirmation.',
        N'Task completed: {{TASK_TITLE}}',
        N'<p>Hello {{CREATOR_NAME}},</p>
<p>The workflow task <strong>{{TASK_TITLE}}</strong> has been completed by {{ACTION_BY}}.</p>
<p><strong>Status:</strong> {{TASK_STATUS}}<br>
<strong>Completed At:</strong> {{COMPLETED_AT}}<br>
<strong>Assigned To:</strong> {{ASSIGNED_TO}}</p>
<p>{{TASK_LINK}}</p>',
        N'Hello {{CREATOR_NAME}},

The workflow task {{TASK_TITLE}} has been completed by {{ACTION_BY}}.

Status: {{TASK_STATUS}}
Completed At: {{COMPLETED_AT}}
Assigned To: {{ASSIGNED_TO}}

{{TASK_URL}}'
    ),
    (
        N'WORKFLOW_TASK_UPDATED',
        N'Workflow Task Updated',
        N'Queued when a workflow task is edited and the task creator opted to receive update notifications.',
        N'Task updated: {{TASK_TITLE}}',
        N'<p>Hello {{CREATOR_NAME}},</p>
<p>The workflow task <strong>{{TASK_TITLE}}</strong> has been updated by {{ACTION_BY}}.</p>
{{CHANGE_SUMMARY_HTML}}
<p><strong>Status:</strong> {{TASK_STATUS}}<br>
<strong>Due Date:</strong> {{TASK_DUE_DATE}}<br>
<strong>Assigned To:</strong> {{ASSIGNED_TO}}</p>
<p>{{TASK_LINK}}</p>',
        N'Hello {{CREATOR_NAME}},

The workflow task {{TASK_TITLE}} has been updated by {{ACTION_BY}}.

{{CHANGE_SUMMARY}}

Status: {{TASK_STATUS}}
Due Date: {{TASK_DUE_DATE}}
Assigned To: {{ASSIGNED_TO}}

{{TASK_URL}}'
    ),
    (
        N'WORKFLOW_TASK_REMINDER',
        N'Workflow Task Reminder',
        N'Queued when a workflow task reminder is sent manually or by the automatic reminder processor.',
        N'Reminder: {{TASK_TITLE}} is due {{TASK_DUE_DATE}}',
        N'<p>Hello {{RECIPIENT_NAME}},</p>
<p>This is a {{REMINDER_TYPE}} for workflow task <strong>{{TASK_TITLE}}</strong>.</p>
<p><strong>Status:</strong> {{TASK_STATUS}}<br>
<strong>Due Date:</strong> {{TASK_DUE_DATE}}<br>
<strong>Priority:</strong> {{TASK_PRIORITY}}<br>
<strong>Created By:</strong> {{CREATOR_NAME}}</p>
<p>{{TASK_LINK}}</p>',
        N'Hello {{RECIPIENT_NAME}},

This is a {{REMINDER_TYPE}} for workflow task {{TASK_TITLE}}.

Status: {{TASK_STATUS}}
Due Date: {{TASK_DUE_DATE}}
Priority: {{TASK_PRIORITY}}
Created By: {{CREATOR_NAME}}

{{TASK_URL}}'
    ),
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
    ),
    (
        N'WORKFLOW_TASK_COMMENT_ADDED',
        N'Workflow Task Discussion Note Added',
        N'Queued when a workflow task discussion note is added and the sender chooses to notify the task audience.',
        N'New note on task: {{TASK_TITLE}}',
        N'<p>Hello {{RECIPIENT_NAME}},</p>
<p>{{COMMENT_AUTHOR}} added a discussion note to workflow task <strong>{{TASK_TITLE}}</strong>.</p>
<div style="border-left:3px solid #d8e4f0;padding-left:12px;margin:12px 0;">{{COMMENT_HTML}}</div>
<p><strong>Status:</strong> {{TASK_STATUS}}<br>
<strong>Due Date:</strong> {{TASK_DUE_DATE}}<br>
<strong>Assigned To:</strong> {{ASSIGNED_TO}}</p>
<p>{{TASK_LINK}}</p>',
        N'Hello {{RECIPIENT_NAME}},

{{COMMENT_AUTHOR}} added a discussion note to workflow task {{TASK_TITLE}}.

{{COMMENT_TEXT}}

Status: {{TASK_STATUS}}
Due Date: {{TASK_DUE_DATE}}
Assigned To: {{ASSIGNED_TO}}

{{TASK_URL}}'
    )
) AS source (TemplateKey, TemplateName, Description, Subject, BodyHtml, BodyText)
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
GO
