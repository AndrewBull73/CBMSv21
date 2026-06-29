<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\EmailTemplateModel;
use App\Models\SystemSettingsModel;
use App\Models\UserModel;
use App\Models\WorkflowTaskModel;
use App\Models\WorkflowTaskStatusModel;
use App\Models\WorkflowTaskTypeModel;
use PDO;

require_once __DIR__ . '/../../shared/workflow_helpers.php';

final class WorkflowTaskReminderService
{
    private string $lastError = '';
    private WorkflowTaskModel $tasksModel;
    private UserModel $userModel;
    private SystemSettingsModel $settingsModel;
    private WorkflowTaskStatusModel $statusesModel;
    private WorkflowTaskTypeModel $typesModel;
    private EmailTemplateModel $templateModel;
    private MailService $mailer;

    public function __construct(private PDO $conn)
    {
        $this->tasksModel = new WorkflowTaskModel($conn);
        $this->userModel = new UserModel($conn);
        $this->settingsModel = new SystemSettingsModel($conn);
        $this->statusesModel = new WorkflowTaskStatusModel($conn);
        $this->typesModel = new WorkflowTaskTypeModel($conn);
        $this->templateModel = new EmailTemplateModel($conn);
        $this->mailer = new MailService($conn);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    private function t(string $key, array $replacements = []): string
    {
        if (!function_exists('\\__t')) {
            $sharedRoot = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'shared';
            $sessionHelper = $sharedRoot . DIRECTORY_SEPARATOR . 'SessionHelper.php';
            $langHelper = $sharedRoot . DIRECTORY_SEPARATOR . 'lang.php';
            if (is_file($sessionHelper)) {
                require_once $sessionHelper;
            }
            if (is_file($langHelper)) {
                require_once $langHelper;
            }
        }

        if (function_exists('\\__t')) {
            return \__t($key, $replacements);
        }

        foreach ($replacements as $name => $value) {
            $key = str_replace(':' . $name, (string)$value, $key);
        }
        return $key;
    }

    private function taskLinkHtml(string $taskUrl): string
    {
        return '<a href="' . htmlspecialchars($taskUrl, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($this->t('workflow_task_open_in_cbms'), ENT_QUOTES, 'UTF-8')
            . '</a>';
    }

    public function sendReminder(array $task, int $workflowTaskID, string $mode, int $actionByUserID = 0): bool
    {
        $this->lastError = '';
        if ($workflowTaskID <= 0) {
            $this->lastError = $this->t('workflow_task_valid_task_required');
            return false;
        }
        if ($this->isClosedTask($task)) {
            $this->lastError = $this->t('workflow_task_reminder_closed_not_needed');
            return false;
        }

        $assignedToUserID = (int)($task['AssignedToUserID'] ?? 0);
        if ($assignedToUserID <= 0) {
            $this->lastError = $this->t('workflow_task_assignee_required');
            return false;
        }

        $recipient = $this->userModel->findById($assignedToUserID);
        if (!$recipient || trim((string)($recipient['Email'] ?? '')) === '') {
            $this->lastError = $this->t('workflow_task_assignee_email_required');
            return false;
        }

        $creator = !empty($task['CreatedByUserID'])
            ? $this->userModel->findById((int)$task['CreatedByUserID'])
            : null;
        $actionBy = $actionByUserID > 0 ? $this->userModel->findById($actionByUserID) : null;
        $statusName = $this->statusesModel->findNameById((int)($task['StatusID'] ?? 0))
            ?? (string)($task['StatusName'] ?? '-');
        $taskType = $this->typesModel->findNameById((int)($task['TaskTypeID'] ?? 0))
            ?? (string)($task['TaskTypeName'] ?? '-');
        $appName = trim((string)$this->settingsModel->get('APP_NAME', 'CBMSv21'));
        if ($appName === '') {
            $appName = 'CBMSv21';
        }

        $appUrl = rtrim(trim((string)$this->settingsModel->get('APP_URL', '')), '/');
        if ($appUrl === '') {
            $appUrl = 'http://localhost/CBMSv21';
        }
        $taskUrl = $this->workflowPublicIndexUrl($appUrl) . '?' . http_build_query([
            'route' => 'workflow/edit',
            'id' => $workflowTaskID,
        ]);
        $mode = strtoupper(trim($mode));
        $reminderType = $mode === 'AUTO' || $mode === 'AUTOMATIC'
            ? $this->t('workflow_task_reminder_type_auto')
            : $this->t('workflow_task_reminder_type_manual');

        $tokens = [
            'APP_NAME' => $appName,
            'RECIPIENT_NAME' => $this->userDisplayName($recipient),
            'CREATOR_NAME' => $creator ? $this->userDisplayName($creator) : '-',
            'ACTION_BY' => $actionBy ? $this->userDisplayName($actionBy) : 'CBMS',
            'REMINDER_TYPE' => $reminderType,
            'TASK_ID' => (string)$workflowTaskID,
            'TASK_TITLE' => (string)($task['Title'] ?? ''),
            'TASK_DESCRIPTION' => workflow_rich_text_to_plain_text((string)($task['Description'] ?? '')),
            'TASK_DESCRIPTION_HTML' => workflow_render_rich_text((string)($task['Description'] ?? '')),
            'TASK_TYPE' => $taskType,
            'TASK_STATUS' => $statusName,
            'TASK_PRIORITY' => $this->normalizePriorityCode($task['PriorityCode'] ?? 'NORMAL'),
            'TASK_DUE_DATE' => (string)($task['DueDate'] ?? '-'),
            'ASSIGNED_TO' => $this->userDisplayName($recipient),
            'TASK_URL' => $taskUrl,
            'TASK_LINK' => $this->taskLinkHtml($taskUrl),
            'REMINDER_SENT_AT' => $this->formatDateTime(gmdate('Y-m-d H:i:s')),
            'AUTO_REMINDER_DAYS_BEFORE_DUE' => (string)max(0, (int)($task['AutoReminderDaysBeforeDue'] ?? 0)),
        ];

        $rendered = $this->templateModel->render('WORKFLOW_TASK_REMINDER', $tokens);
        if ($rendered === null) {
            $rendered = $this->fallbackReminderTemplate($tokens);
        }

        $from = trim((string)$this->settingsModel->get('EMAIL_ERROR_FROM', ''));
        $sent = $this->mailer->sendEmail(
            trim((string)$recipient['Email']),
            (string)($rendered['subject'] ?? ''),
            (string)($rendered['html'] ?? ''),
            $from !== '' ? $from : null
        );
        if (!$sent) {
            $this->lastError = $this->mailer->getLastError() ?: $this->t('workflow_task_reminder_email_send_failed');
        }

        return $sent;
    }

    public function sendOverdueEscalation(array $task, int $workflowTaskID): bool
    {
        $this->lastError = '';
        if ($workflowTaskID <= 0) {
            $this->lastError = $this->t('workflow_task_valid_task_required');
            return false;
        }
        if ($this->isClosedTask($task)) {
            $this->lastError = $this->t('workflow_task_overdue_escalation_closed_not_needed');
            return false;
        }

        $recipients = $this->overdueEscalationRecipients($task);
        if ($recipients === []) {
            $this->lastError = $this->t('workflow_task_escalation_email_required');
            return false;
        }

        $assignedToUserID = (int)($task['AssignedToUserID'] ?? 0);
        $assignee = $assignedToUserID > 0 ? $this->userModel->findById($assignedToUserID) : null;
        $creator = !empty($task['CreatedByUserID'])
            ? $this->userModel->findById((int)$task['CreatedByUserID'])
            : null;
        $statusName = $this->statusesModel->findNameById((int)($task['StatusID'] ?? 0))
            ?? (string)($task['StatusName'] ?? '-');
        $taskType = $this->typesModel->findNameById((int)($task['TaskTypeID'] ?? 0))
            ?? (string)($task['TaskTypeName'] ?? '-');
        $appName = trim((string)$this->settingsModel->get('APP_NAME', 'CBMSv21'));
        if ($appName === '') {
            $appName = 'CBMSv21';
        }

        $appUrl = rtrim(trim((string)$this->settingsModel->get('APP_URL', '')), '/');
        if ($appUrl === '') {
            $appUrl = 'http://localhost/CBMSv21';
        }
        $taskUrl = $this->workflowPublicIndexUrl($appUrl) . '?' . http_build_query([
            'route' => 'workflow/edit',
            'id' => $workflowTaskID,
        ]);

        $baseTokens = [
            'APP_NAME' => $appName,
            'CREATOR_NAME' => $creator ? $this->userDisplayName($creator) : '-',
            'TASK_ID' => (string)$workflowTaskID,
            'TASK_TITLE' => (string)($task['Title'] ?? ''),
            'TASK_DESCRIPTION' => workflow_rich_text_to_plain_text((string)($task['Description'] ?? '')),
            'TASK_DESCRIPTION_HTML' => workflow_render_rich_text((string)($task['Description'] ?? '')),
            'TASK_TYPE' => $taskType,
            'TASK_STATUS' => $statusName,
            'TASK_PRIORITY' => $this->normalizePriorityCode($task['PriorityCode'] ?? 'NORMAL'),
            'TASK_DUE_DATE' => (string)($task['DueDate'] ?? '-'),
            'DAYS_OVERDUE' => (string)$this->daysOverdue($task['DueDate'] ?? null),
            'ASSIGNED_TO' => $assignee ? $this->userDisplayName($assignee) : '-',
            'TASK_URL' => $taskUrl,
            'TASK_LINK' => $this->taskLinkHtml($taskUrl),
            'ESCALATION_SENT_AT' => $this->formatDateTime(gmdate('Y-m-d H:i:s')),
            'OVERDUE_ESCALATION_DAYS_AFTER_DUE' => (string)max(1, (int)($task['OverdueEscalationDaysAfterDue'] ?? 1)),
        ];

        $from = trim((string)$this->settingsModel->get('EMAIL_ERROR_FROM', ''));
        $sentCount = 0;
        $failures = [];
        foreach ($recipients as $recipient) {
            $tokens = $baseTokens + [
                'RECIPIENT_NAME' => $this->userDisplayName($recipient),
                'RECIPIENT_ROLE' => (string)($recipient['_EscalationRole'] ?? $this->t('workflow_task_escalation_role_recipient')),
            ];
            $rendered = $this->templateModel->render('WORKFLOW_TASK_OVERDUE_ESCALATION', $tokens);
            if ($rendered === null) {
                $rendered = $this->fallbackOverdueEscalationTemplate($tokens);
            }

            $sent = $this->mailer->sendEmail(
                trim((string)$recipient['Email']),
                (string)($rendered['subject'] ?? ''),
                (string)($rendered['html'] ?? ''),
                $from !== '' ? $from : null
            );
            if ($sent) {
                $sentCount++;
                continue;
            }

            $failures[] = trim((string)$recipient['Email']) . ': ' . ($this->mailer->getLastError() ?: $this->t('workflow_task_send_failed_short'));
        }

        if ($sentCount <= 0) {
            $this->lastError = $failures !== []
                ? implode('; ', $failures)
                : $this->t('workflow_task_overdue_escalation_send_failed');
            return false;
        }
        if ($failures !== [] && function_exists('app_log')) {
            app_log('Workflow overdue escalation partially sent', [
                'WorkflowTaskID' => $workflowTaskID,
                'failures' => $failures,
            ], 'warn');
        }

        return true;
    }

    /**
     * @return array{checked: int, sent: int, failed: int, failures: array<int, array<string, mixed>>}
     */
    public function processDueAutomaticReminders(int $limit = 100): array
    {
        $tasks = $this->tasksModel->listAutomaticReminderDueTasks($limit);
        $summary = [
            'checked' => count($tasks),
            'sent' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($tasks as $task) {
            $workflowTaskID = (int)($task['WorkflowTaskID'] ?? 0);
            if ($workflowTaskID <= 0) {
                continue;
            }

            if ($this->sendReminder($task, $workflowTaskID, 'AUTO', 0)) {
                if (!$this->tasksModel->markAutomaticReminderSent($workflowTaskID)) {
                    $summary['failed']++;
                    $summary['failures'][] = [
                        'WorkflowTaskID' => $workflowTaskID,
                        'error' => $this->tasksModel->getLastError() ?: $this->t('workflow_task_auto_reminder_mark_sent_failed'),
                    ];
                    continue;
                }

                $this->tasksModel->addActivity(
                    $workflowTaskID,
                    'AUTOMATIC_REMINDER_SENT',
                    $this->t('workflow_task_auto_reminder_scheduler_activity'),
                    0,
                    null,
                    (int)($task['AssignedToUserID'] ?? 0) ?: null,
                    null,
                    null,
                    [
                        'daysBeforeDue' => max(0, (int)($task['AutoReminderDaysBeforeDue'] ?? 0)),
                    ]
                );
                $summary['sent']++;
                continue;
            }

            $summary['failed']++;
            $summary['failures'][] = [
                'WorkflowTaskID' => $workflowTaskID,
                'error' => $this->getLastError(),
            ];
        }

        return $summary;
    }

    /**
     * @return array{checked: int, sent: int, failed: int, failures: array<int, array<string, mixed>>}
     */
    public function processDueOverdueEscalations(int $limit = 100): array
    {
        $tasks = $this->tasksModel->listOverdueEscalationDueTasks($limit);
        $summary = [
            'checked' => count($tasks),
            'sent' => 0,
            'failed' => 0,
            'failures' => [],
        ];

        foreach ($tasks as $task) {
            $workflowTaskID = (int)($task['WorkflowTaskID'] ?? 0);
            if ($workflowTaskID <= 0) {
                continue;
            }

            if (!$this->sendOverdueEscalation($task, $workflowTaskID)) {
                $summary['failed']++;
                $summary['failures'][] = [
                    'WorkflowTaskID' => $workflowTaskID,
                    'error' => $this->getLastError(),
                ];
                continue;
            }

            if (!$this->tasksModel->markOverdueEscalationSent($workflowTaskID)) {
                $summary['failed']++;
                $summary['failures'][] = [
                    'WorkflowTaskID' => $workflowTaskID,
                    'error' => $this->tasksModel->getLastError() ?: $this->t('workflow_task_overdue_escalation_mark_sent_failed'),
                ];
                continue;
            }

            $this->tasksModel->addActivity(
                $workflowTaskID,
                'OVERDUE_ESCALATION_SENT',
                $this->t('workflow_task_overdue_escalation_scheduler_activity'),
                0,
                null,
                (int)($task['AssignedToUserID'] ?? 0) ?: null,
                null,
                null,
                [
                    'daysOverdue' => $this->daysOverdue($task['DueDate'] ?? null),
                    'daysAfterDue' => max(1, (int)($task['OverdueEscalationDaysAfterDue'] ?? 1)),
                ]
            );
            $summary['sent']++;
        }

        return $summary;
    }

    /**
     * @return array{automatic: array<string, mixed>, overdue: array<string, mixed>}
     */
    public function processDueWorkflowNotifications(int $limit = 100): array
    {
        return [
            'automatic' => $this->processDueAutomaticReminders($limit),
            'overdue' => $this->processDueOverdueEscalations($limit),
        ];
    }

    private function isClosedTask(array $task): bool
    {
        $statusCode = strtoupper(trim((string)($task['StatusCode'] ?? '')));
        $statusName = strtoupper(trim((string)($task['StatusName'] ?? '')));
        return !empty($task['CompletedAt'])
            || in_array($statusCode, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
            || in_array($statusName, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true);
    }

    private function userDisplayName(array $user): string
    {
        foreach (['DisplayName', 'FullName', 'Username', 'Email'] as $key) {
            $value = trim((string)($user[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return 'User #' . (int)($user['UserID'] ?? 0);
    }

    private function normalizePriorityCode($value): string
    {
        $code = strtoupper(trim((string)$value));
        return in_array($code, ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true) ? $code : 'NORMAL';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overdueEscalationRecipients(array $task): array
    {
        $byEmail = [];
        foreach ([
            [(int)($task['AssignedToUserID'] ?? 0), 'assignee'],
            [(int)($task['CreatedByUserID'] ?? 0), 'creator'],
        ] as [$userID, $role]) {
            if ($userID <= 0) {
                continue;
            }
            $user = $this->userModel->findById($userID);
            if ($user) {
                $this->addEscalationRecipient($byEmail, $user, $role);
            }
        }

        foreach ($this->workflowAdminRecipients() as $admin) {
            $this->addEscalationRecipient($byEmail, $admin, 'workflow admin');
        }

        return array_values($byEmail);
    }

    /**
     * @param array<string, array<string, mixed>> $byEmail
     * @param array<string, mixed> $user
     */
    private function addEscalationRecipient(array &$byEmail, array $user, string $role): void
    {
        if ((int)($user['IsActive'] ?? 1) === 0) {
            return;
        }
        $email = strtolower(trim((string)($user['Email'] ?? '')));
        if ($email === '') {
            return;
        }
        if (!isset($byEmail[$email])) {
            $user['_EscalationRole'] = $role;
            $byEmail[$email] = $user;
            return;
        }

        $existingRole = trim((string)($byEmail[$email]['_EscalationRole'] ?? ''));
        if ($existingRole !== '' && !str_contains($existingRole, $role)) {
            $byEmail[$email]['_EscalationRole'] = $existingRole . ', ' . $role;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function workflowAdminRecipients(): array
    {
        try {
            $sql = "
                SELECT DISTINCT
                    u.UserID,
                    u.Username,
                    LTRIM(RTRIM(u.DisplayName)) AS DisplayName,
                    u.FirstName,
                    u.LastName,
                    (u.FirstName + ' ' + u.LastName) AS FullName,
                    u.Email,
                    u.IsActive
                FROM dbo.tblUsers u
                INNER JOIN dbo.tblUserRoles ur ON ur.UserID = u.UserID
                INNER JOIN dbo.tblRoles r ON r.RoleID = ur.RoleID
                INNER JOIN dbo.tblRolePermissions rp ON rp.RoleID = r.RoleID
                INNER JOIN dbo.tblPermissions p ON p.PermissionID = rp.PermissionID
                WHERE u.IsActive = 1
                  AND (r.Active = 1 OR r.Active IS NULL)
                  AND (p.Active = 1 OR p.Active IS NULL)
                  AND UPPER(p.PermissionCode) IN (N'WORKFLOW_OPERATIONS_ADMIN', N'ADMIN_ALL', N'SYSADMIN')
                  AND NULLIF(LTRIM(RTRIM(u.Email)), N'') IS NOT NULL
                ORDER BY DisplayName ASC, Username ASC
            ";

            return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            if (function_exists('app_log')) {
                app_log('Workflow admin escalation recipient lookup failed', [
                    'error' => $e->getMessage(),
                ], 'warn');
            }
            return [];
        }
    }

    private function daysOverdue($dueDate): int
    {
        $raw = trim((string)$dueDate);
        if ($raw === '') {
            return 0;
        }
        $timestamp = strtotime($raw);
        if (!$timestamp) {
            return 0;
        }

        try {
            $due = new \DateTimeImmutable(gmdate('Y-m-d', $timestamp), new \DateTimeZone('UTC'));
            $today = new \DateTimeImmutable(gmdate('Y-m-d'), new \DateTimeZone('UTC'));
            if ($today <= $due) {
                return 0;
            }
            return (int)$due->diff($today)->days;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function workflowPublicIndexUrl(string $appUrl): string
    {
        $base = rtrim($appUrl, '/');
        $lower = strtolower($base);
        if (str_ends_with($lower, '/backend-php/public/index.php')) {
            return $base;
        }
        if (str_ends_with($lower, '/backend-php/public')) {
            return $base . '/index.php';
        }

        return $base . '/backend-php/public/index.php';
    }

    /**
     * @param array<string, scalar|null> $tokens
     * @return array<string, string>
     */
    private function fallbackReminderTemplate(array $tokens): array
    {
        $subject = $this->t('workflow_task_reminder_fallback_subject');
        $html = $this->t('workflow_task_reminder_fallback_html');
        $text = $this->t('workflow_task_reminder_fallback_text');

        return [
            'subject' => $this->templateModel->applyTokens($subject, $tokens, false),
            'html' => $this->templateModel->applyTokens($html, $tokens, true),
            'text' => $this->templateModel->applyTokens($text, $tokens, false),
        ];
    }

    /**
     * @param array<string, scalar|null> $tokens
     * @return array<string, string>
     */
    private function fallbackOverdueEscalationTemplate(array $tokens): array
    {
        $subject = $this->t('workflow_task_escalation_fallback_subject');
        $html = $this->t('workflow_task_escalation_fallback_html');
        $text = $this->t('workflow_task_escalation_fallback_text');

        return [
            'subject' => $this->templateModel->applyTokens($subject, $tokens, false),
            'html' => $this->templateModel->applyTokens($html, $tokens, true),
            'text' => $this->templateModel->applyTokens($text, $tokens, false),
        ];
    }

    private function formatDateTime(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '-';
        }
        try {
            $utc = new \DateTimeImmutable($raw, new \DateTimeZone('UTC'));
            return $utc->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            $ts = strtotime($raw . ' UTC');
            return $ts ? date('Y-m-d H:i', $ts) : $raw;
        }
    }
}
