<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkFlowTaskModel
{
    private PDO $conn;
    private string $lastError = '';

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function supportsWorkflowTaskAttachments(): bool
    {
        return $this->tableExists('dbo.tblWorkflowTaskAttachments');
    }

    public function supportsWorkflowTaskComments(): bool
    {
        return $this->tableExists('dbo.tblWorkflowTaskComments');
    }

    public function supportsWorkflowTaskViews(): bool
    {
        return $this->tableExists('dbo.tblWorkflowTaskViews');
    }

    public function supportsWorkflowTaskDependencies(): bool
    {
        return $this->tableExists('dbo.tblWorkflowTaskDependencies');
    }

    public function supportsWorkflowEntityLinks(): bool
    {
        return $this->tableExists('dbo.tblWorkflowEntityLinks');
    }

    public function listByUser(
        int $userID,
        int $page = 1,
        int $pageSize = 20,
        string $q = '',
        ?int $typeID = null,
        ?int $statusID = null
    ): array {
        return $this->listFiltered($userID, $page, $pageSize, $q, $typeID, $statusID, false);
    }

    public function listFilteredByUser(
        int $userID,
        string $q = '',
        ?int $typeID = null,
        ?int $statusID = null,
        int $page = 1,
        int $pageSize = 20
    ): array {
        return $this->listFiltered($userID, $page, $pageSize, $q, $typeID, $statusID, false);
    }

    public function listFiltered(
        ?int $assignedToUserID,
        int $page = 1,
        int $pageSize = 20,
        string $q = '',
        ?int $typeID = null,
        ?int $statusID = null,
        bool $onlyOpen = false,
        ?int $createdByUserID = null,
        bool $onlyClosed = false,
        string $dueState = '',
        ?int $workflowProjectID = null
    ): array {
        $where = [];
        $params = [];
        $offset = max(0, ($page - 1) * $pageSize);
        $dueState = $this->normalizeDueState($dueState);

        if ($assignedToUserID !== null) {
            $where[] = 't.AssignedToUserID = :assignedToUserID';
            $params[':assignedToUserID'] = $assignedToUserID;
        }
        if ($createdByUserID !== null) {
            $where[] = 't.CreatedByUserID = :createdByUserID';
            $params[':createdByUserID'] = $createdByUserID;
        }
        if ($q !== '') {
            $where[] = '(t.Title LIKE :q OR t.Description LIKE :q OR t.RelatedEntity LIKE :q OR t.RelatedKey LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($typeID !== null) {
            $where[] = 't.TaskTypeID = :typeID';
            $params[':typeID'] = $typeID;
        }
        if ($statusID !== null) {
            $where[] = 't.StatusID = :statusID';
            $params[':statusID'] = $statusID;
        }
        if ($workflowProjectID !== null && $workflowProjectID > 0) {
            $where[] = 't.WorkflowProjectID = :workflowProjectID';
            $params[':workflowProjectID'] = $workflowProjectID;
        }
        if ($onlyOpen) {
            $where[] = 't.CompletedAt IS NULL';
            $where[] = "UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')";
            $where[] = "UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')";
        } elseif ($onlyClosed) {
            $where[] = "(t.CompletedAt IS NOT NULL
                OR UPPER(ISNULL(st.Code, '')) IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                OR UPPER(ISNULL(st.Name, '')) IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED'))";
        }
        if ($dueState !== '') {
            $where[] = 't.CompletedAt IS NULL';
            $where[] = 't.DueDate IS NOT NULL';
            $where[] = "UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')";
            $where[] = "UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')";
            if ($dueState === 'overdue') {
                $where[] = 'CAST(t.DueDate AS date) < CAST(SYSUTCDATETIME() AS date)';
            } elseif ($dueState === 'today') {
                $where[] = 'CAST(t.DueDate AS date) = CAST(SYSUTCDATETIME() AS date)';
            } elseif ($dueState === 'soon') {
                $where[] = 'CAST(t.DueDate AS date) > CAST(SYSUTCDATETIME() AS date)';
                $where[] = 'CAST(t.DueDate AS date) <= DATEADD(DAY, 7, CAST(SYSUTCDATETIME() AS date))';
            }
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $hasTaskViews = $this->supportsWorkflowTaskViews();
        $recipientViewSelect = $hasTaskViews
            ? "
                    recipientView.FirstViewedAt AS RecipientFirstViewedAt,
                    recipientView.LastViewedAt AS RecipientLastViewedAt,
                    recipientView.ViewCount AS RecipientViewCount,"
            : "
                    CAST(NULL AS DATETIME2(0)) AS RecipientFirstViewedAt,
                    CAST(NULL AS DATETIME2(0)) AS RecipientLastViewedAt,
                    CAST(NULL AS INT) AS RecipientViewCount,";
        $recipientViewJoin = $hasTaskViews
            ? "
                LEFT JOIN dbo.tblWorkflowTaskViews recipientView
                    ON recipientView.WorkflowTaskID = t.WorkflowTaskID
                   AND recipientView.UserID = t.AssignedToUserID"
            : '';

        try {
            $countSql = "
                SELECT COUNT(*)
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                {$whereSql}
            ";
            $stc = $this->conn->prepare($countSql);
            $stc->execute($params);
            $total = (int) $stc->fetchColumn();

            $sql = "
                SELECT
                    t.WorkflowTaskID,
                    t.TaskTypeID,
                    t.StatusID,
                    t.Title,
                    t.Description,
                    t.PriorityCode,
                    t.RelatedEntity,
                    t.RelatedKey,
                    t.DueDate,
                    t.WorkflowProjectID,
                    t.ParentWorkflowTaskID,
                    t.PlannedStartDate,
                    t.PlannedEndDate,
                    t.PercentComplete,
                    t.ProjectUtilisationPercent,
                    t.CompletedAt,
                    t.RecipientResponse,
                    t.RespondedAt,
                    t.LastForwardedAt,
                    t.NotifyCreatorOnCompletion,
                    t.NotifyCreatorOnUpdate,
                    t.NotifyAudienceOnComment,
                    t.AutoReminderEnabled,
                    t.AutoReminderDaysBeforeDue,
                    t.AutoReminderSentAt,
                    t.LastManualReminderSentAt,
                    t.LastManualReminderByUserID,
                    t.OverdueEscalationEnabled,
                    t.OverdueEscalationDaysAfterDue,
                    t.OverdueEscalationSentAt,
                    t.WorkflowTaskBatchID,
                    t.WorkflowTaskCompletionRule,
                    t.CreatedAt,
                    t.UpdatedAt,
                    t.CreatedByUserID,
                    t.AssignedToUserID,
                    {$recipientViewSelect}
                    ty.Name AS TaskTypeName,
                    st.Code AS StatusCode,
                    st.Name AS StatusName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u1.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u1.Username)), N''), CASE WHEN t.CreatedByUserID IS NOT NULL THEN CONCAT(N'User #', t.CreatedByUserID) ELSE NULL END) AS CreatedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u2.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u2.Username)), N''), CASE WHEN t.AssignedToUserID IS NOT NULL THEN CONCAT(N'User #', t.AssignedToUserID) ELSE NULL END) AS AssignedToName,
                    parentTask.Title AS ParentWorkflowTaskTitle,
                    p.ProjectCode,
                    p.ProjectName,
                    p.ProjectStatusCode,
                    p.StartDate AS ProjectStartDate,
                    p.TargetEndDate AS ProjectTargetEndDate
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblUsers u1 ON t.CreatedByUserID = u1.UserID
                LEFT JOIN dbo.tblUsers u2 ON t.AssignedToUserID = u2.UserID
                {$recipientViewJoin}
                LEFT JOIN dbo.tblWorkflowTaskTypes ty ON t.TaskTypeID = ty.TaskTypeID
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                LEFT JOIN dbo.tblWorkflowProjects p ON t.WorkflowProjectID = p.WorkflowProjectID
                LEFT JOIN dbo.tblWorkflowTasks parentTask ON t.ParentWorkflowTaskID = parentTask.WorkflowTaskID
                {$whereSql}
                ORDER BY
                    CASE
                        WHEN t.CompletedAt IS NULL AND CAST(t.DueDate AS date) < CAST(SYSUTCDATETIME() AS date) THEN 0
                        WHEN t.CompletedAt IS NULL THEN 1
                        ELSE 2
                    END,
                    CASE UPPER(ISNULL(t.PriorityCode, 'NORMAL'))
                        WHEN 'URGENT' THEN 0
                        WHEN 'HIGH' THEN 1
                        WHEN 'NORMAL' THEN 2
                        WHEN 'LOW' THEN 3
                        ELSE 2
                    END,
                    t.DueDate ASC,
                    t.WorkflowTaskID DESC
                OFFSET :off ROWS FETCH NEXT :lim ROWS ONLY
            ";

            $st = $this->conn->prepare($sql);
            foreach ($params as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->bindValue(':off', $offset, PDO::PARAM_INT);
            $st->bindValue(':lim', $pageSize, PDO::PARAM_INT);
            $st->execute();

            return [
                'items' => $st->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'total' => $total,
            ];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return ['items' => [], 'total' => 0];
        }
    }

    public function summarizeFiltered(
        ?int $assignedToUserID,
        string $q = '',
        ?int $typeID = null,
        ?int $statusID = null,
        ?int $createdByUserID = null,
        ?int $workflowProjectID = null
    ): array {
        $where = [];
        $params = [];

        if ($assignedToUserID !== null) {
            $where[] = 't.AssignedToUserID = :assignedToUserID';
            $params[':assignedToUserID'] = $assignedToUserID;
        }
        if ($createdByUserID !== null) {
            $where[] = 't.CreatedByUserID = :createdByUserID';
            $params[':createdByUserID'] = $createdByUserID;
        }
        if ($q !== '') {
            $where[] = '(t.Title LIKE :q OR t.Description LIKE :q OR t.RelatedEntity LIKE :q OR t.RelatedKey LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($typeID !== null) {
            $where[] = 't.TaskTypeID = :typeID';
            $params[':typeID'] = $typeID;
        }
        if ($statusID !== null) {
            $where[] = 't.StatusID = :statusID';
            $params[':statusID'] = $statusID;
        }
        if ($workflowProjectID !== null && $workflowProjectID > 0) {
            $where[] = 't.WorkflowProjectID = :workflowProjectID';
            $params[':workflowProjectID'] = $workflowProjectID;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $sql = "
                SELECT
                    COUNT(*) AS TotalTasks,
                    SUM(CASE
                        WHEN t.CompletedAt IS NULL
                         AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                         AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                        THEN 1 ELSE 0 END) AS OpenTasks,
                    SUM(CASE
                        WHEN t.CompletedAt IS NULL
                         AND CAST(t.DueDate AS date) < CAST(SYSUTCDATETIME() AS date)
                         AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                         AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                        THEN 1 ELSE 0 END) AS OverdueTasks,
                    SUM(CASE
                        WHEN t.CompletedAt IS NULL
                         AND CAST(t.DueDate AS date) = CAST(SYSUTCDATETIME() AS date)
                         AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                         AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                        THEN 1 ELSE 0 END) AS DueTodayTasks,
                    SUM(CASE
                        WHEN t.CompletedAt IS NULL
                         AND CAST(t.DueDate AS date) > CAST(SYSUTCDATETIME() AS date)
                         AND CAST(t.DueDate AS date) <= DATEADD(DAY, 7, CAST(SYSUTCDATETIME() AS date))
                         AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                         AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                        THEN 1 ELSE 0 END) AS DueSoonTasks,
                    SUM(CASE
                        WHEN t.CompletedAt IS NOT NULL
                         OR UPPER(ISNULL(st.Code, '')) IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                         OR UPPER(ISNULL(st.Name, '')) IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                        THEN 1 ELSE 0 END) AS ClosedTasks
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                {$whereSql}
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'total' => (int) ($row['TotalTasks'] ?? 0),
                'open' => (int) ($row['OpenTasks'] ?? 0),
                'overdue' => (int) ($row['OverdueTasks'] ?? 0),
                'due_today' => (int) ($row['DueTodayTasks'] ?? 0),
                'due_soon' => (int) ($row['DueSoonTasks'] ?? 0),
                'closed' => (int) ($row['ClosedTasks'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return ['total' => 0, 'open' => 0, 'overdue' => 0, 'due_today' => 0, 'due_soon' => 0, 'closed' => 0];
        }
    }

    public function create(array $data): bool
    {
        return $this->createAndReturnId($data) > 0;
    }

    public function createAndReturnId(array $data): int
    {
        $sql = "
            INSERT INTO dbo.tblWorkflowTasks
                (TaskTypeID, StatusID, Title, Description,
                 CreatedByUserID, AssignedToUserID,
                 RelatedEntity, RelatedKey, PriorityCode, DueDate,
                 WorkflowProjectID, ParentWorkflowTaskID, PlannedStartDate, PlannedEndDate, PercentComplete, ProjectUtilisationPercent,
                 CompletedAt,
                 NotifyCreatorOnCompletion, NotifyCreatorOnUpdate, NotifyAudienceOnComment,
                 AutoReminderEnabled, AutoReminderDaysBeforeDue,
                 OverdueEscalationEnabled, OverdueEscalationDaysAfterDue,
                 WorkflowTaskBatchID, WorkflowTaskCompletionRule, CreatedAt)
            OUTPUT INSERTED.WorkflowTaskID
            VALUES
                (:TaskTypeID, :StatusID, :Title, :Description,
                 :CreatedByUserID, :AssignedToUserID,
                 :RelatedEntity, :RelatedKey, :PriorityCode, :DueDate,
                 :WorkflowProjectID, :ParentWorkflowTaskID, :PlannedStartDate, :PlannedEndDate, :PercentComplete, :ProjectUtilisationPercent,
                 :CompletedAt,
                 :NotifyCreatorOnCompletion, :NotifyCreatorOnUpdate, :NotifyAudienceOnComment,
                 :AutoReminderEnabled, :AutoReminderDaysBeforeDue,
                 :OverdueEscalationEnabled, :OverdueEscalationDaysAfterDue,
                 :WorkflowTaskBatchID, :WorkflowTaskCompletionRule, SYSUTCDATETIME())
        ";

        try {
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':TaskTypeID' => $data['TaskTypeID'],
                ':StatusID' => $data['StatusID'],
                ':Title' => $data['Title'],
                ':Description' => $data['Description'] ?? null,
                ':CreatedByUserID' => $data['CreatedByUserID'],
                ':AssignedToUserID' => !empty($data['AssignedToUserID']) ? $data['AssignedToUserID'] : null,
                ':RelatedEntity' => $data['RelatedEntity'] ?? null,
                ':RelatedKey' => $data['RelatedKey'] ?? null,
                ':PriorityCode' => $this->normalizePriorityCode($data['PriorityCode'] ?? 'NORMAL'),
                ':DueDate' => $data['DueDate'] ?? null,
                ':WorkflowProjectID' => !empty($data['WorkflowProjectID']) ? (int)$data['WorkflowProjectID'] : null,
                ':ParentWorkflowTaskID' => !empty($data['ParentWorkflowTaskID']) ? (int)$data['ParentWorkflowTaskID'] : null,
                ':PlannedStartDate' => $data['PlannedStartDate'] ?? null,
                ':PlannedEndDate' => $data['PlannedEndDate'] ?? null,
                ':PercentComplete' => $this->normalizePercentComplete($data['PercentComplete'] ?? 0),
                ':ProjectUtilisationPercent' => $this->normalizePercentComplete($data['ProjectUtilisationPercent'] ?? 0),
                ':CompletedAt' => $data['CompletedAt'] ?? null,
                ':NotifyCreatorOnCompletion' => !empty($data['NotifyCreatorOnCompletion']) ? 1 : 0,
                ':NotifyCreatorOnUpdate' => !empty($data['NotifyCreatorOnUpdate']) ? 1 : 0,
                ':NotifyAudienceOnComment' => !empty($data['NotifyAudienceOnComment']) ? 1 : 0,
                ':AutoReminderEnabled' => !empty($data['AutoReminderEnabled']) ? 1 : 0,
                ':AutoReminderDaysBeforeDue' => $this->normalizeReminderDays($data['AutoReminderDaysBeforeDue'] ?? 1),
                ':OverdueEscalationEnabled' => !empty($data['OverdueEscalationEnabled']) ? 1 : 0,
                ':OverdueEscalationDaysAfterDue' => $this->normalizeEscalationDays($data['OverdueEscalationDaysAfterDue'] ?? 1),
                ':WorkflowTaskBatchID' => !empty($data['WorkflowTaskBatchID']) ? (string) $data['WorkflowTaskBatchID'] : null,
                ':WorkflowTaskCompletionRule' => $this->normalizeCompletionRule($data['WorkflowTaskCompletionRule'] ?? 'INDIVIDUAL'),
            ]);

            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
                return 0;
            }

            $id = (int) ($st->fetchColumn() ?: 0);
            $this->lastError = '';
            return $id;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return 0;
        }
    }

    public function update(int $id, array $data): bool
    {
        $sql = "
            UPDATE dbo.tblWorkflowTasks
            SET TaskTypeID = :TaskTypeID,
                StatusID = :StatusID,
                Title = :Title,
                Description = :Description,
                AssignedToUserID = :AssignedToUserID,
                RelatedEntity = :RelatedEntity,
                RelatedKey = :RelatedKey,
                PriorityCode = :PriorityCode,
                DueDate = :DueDate,
                WorkflowProjectID = :WorkflowProjectID,
                ParentWorkflowTaskID = :ParentWorkflowTaskID,
                PlannedStartDate = :PlannedStartDate,
                PlannedEndDate = :PlannedEndDate,
                PercentComplete = :PercentComplete,
                ProjectUtilisationPercent = :ProjectUtilisationPercent,
                CompletedAt = :CompletedAt,
                NotifyCreatorOnCompletion = :NotifyCreatorOnCompletion,
                NotifyCreatorOnUpdate = :NotifyCreatorOnUpdate,
                NotifyAudienceOnComment = :NotifyAudienceOnComment,
                AutoReminderEnabled = :AutoReminderEnabled,
                AutoReminderDaysBeforeDue = :AutoReminderDaysBeforeDue,
                AutoReminderSentAt = CASE WHEN :AutoReminderReset = 1 THEN NULL ELSE AutoReminderSentAt END,
                OverdueEscalationEnabled = :OverdueEscalationEnabled,
                OverdueEscalationDaysAfterDue = :OverdueEscalationDaysAfterDue,
                OverdueEscalationSentAt = CASE WHEN :OverdueEscalationReset = 1 THEN NULL ELSE OverdueEscalationSentAt END,
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :UpdatedBy
            WHERE WorkflowTaskID = :id
        ";

        $st = $this->conn->prepare($sql);
        $ok = $st->execute([
            ':id' => $id,
            ':TaskTypeID' => $data['TaskTypeID'],
            ':StatusID' => $data['StatusID'],
            ':Title' => $data['Title'],
            ':Description' => $data['Description'] ?? null,
            ':AssignedToUserID' => !empty($data['AssignedToUserID']) ? $data['AssignedToUserID'] : null,
            ':RelatedEntity' => $data['RelatedEntity'] ?? null,
            ':RelatedKey' => $data['RelatedKey'] ?? null,
            ':PriorityCode' => $this->normalizePriorityCode($data['PriorityCode'] ?? 'NORMAL'),
            ':DueDate' => $data['DueDate'] ?? null,
            ':WorkflowProjectID' => !empty($data['WorkflowProjectID']) ? (int)$data['WorkflowProjectID'] : null,
            ':ParentWorkflowTaskID' => !empty($data['ParentWorkflowTaskID']) ? (int)$data['ParentWorkflowTaskID'] : null,
            ':PlannedStartDate' => $data['PlannedStartDate'] ?? null,
            ':PlannedEndDate' => $data['PlannedEndDate'] ?? null,
            ':PercentComplete' => $this->normalizePercentComplete($data['PercentComplete'] ?? 0),
            ':ProjectUtilisationPercent' => $this->normalizePercentComplete($data['ProjectUtilisationPercent'] ?? 0),
            ':CompletedAt' => $data['CompletedAt'] ?? null,
            ':NotifyCreatorOnCompletion' => !empty($data['NotifyCreatorOnCompletion']) ? 1 : 0,
            ':NotifyCreatorOnUpdate' => !empty($data['NotifyCreatorOnUpdate']) ? 1 : 0,
            ':NotifyAudienceOnComment' => !empty($data['NotifyAudienceOnComment']) ? 1 : 0,
            ':AutoReminderEnabled' => !empty($data['AutoReminderEnabled']) ? 1 : 0,
            ':AutoReminderDaysBeforeDue' => $this->normalizeReminderDays($data['AutoReminderDaysBeforeDue'] ?? 1),
            ':AutoReminderReset' => !empty($data['AutoReminderReset']) ? 1 : 0,
            ':OverdueEscalationEnabled' => !empty($data['OverdueEscalationEnabled']) ? 1 : 0,
            ':OverdueEscalationDaysAfterDue' => $this->normalizeEscalationDays($data['OverdueEscalationDaysAfterDue'] ?? 1),
            ':OverdueEscalationReset' => !empty($data['OverdueEscalationReset']) ? 1 : 0,
            ':UpdatedBy' => $data['UpdatedBy'] ?? null,
        ]);

        if (!$ok) {
            $this->lastError = implode(' | ', $st->errorInfo());
        } else {
            $this->lastError = '';
        }

        return $ok;
    }

    public function updateStatus(int $id, int $statusID, ?string $completedAt, ?int $updatedBy): bool
    {
        $sql = "
            UPDATE dbo.tblWorkflowTasks
            SET StatusID = :statusID,
                CompletedAt = :completedAt,
                PercentComplete = CASE WHEN :completedAtForPercent IS NOT NULL THEN 100 ELSE PercentComplete END,
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :updatedBy
            WHERE WorkflowTaskID = :id
        ";

        try {
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':id' => $id,
                ':statusID' => $statusID,
                ':completedAt' => $completedAt,
                ':completedAtForPercent' => $completedAt,
                ':updatedBy' => $updatedBy,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function completeStatusIfOpen(int $id, int $statusID, string $completedAt, ?int $updatedBy): int
    {
        $sql = "
            SET NOCOUNT ON;

            DECLARE @updated TABLE (WorkflowTaskID INT);

            UPDATE t
            SET t.StatusID = :statusID,
                t.CompletedAt = :completedAt,
                t.PercentComplete = 100,
                t.UpdatedAt = SYSUTCDATETIME(),
                t.UpdatedBy = :updatedBy
            OUTPUT INSERTED.WorkflowTaskID INTO @updated (WorkflowTaskID)
            FROM dbo.tblWorkflowTasks t
            LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
            WHERE t.WorkflowTaskID = :id
              AND t.CompletedAt IS NULL
              AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
              AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED');

            SELECT COUNT(1) AS UpdatedCount FROM @updated;
        ";

        try {
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':id' => $id,
                ':statusID' => $statusID,
                ':completedAt' => $completedAt,
                ':updatedBy' => $updatedBy,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
                return -1;
            }
            $this->lastError = '';
            $count = $st->fetchColumn();
            while ($count === false && $st->nextRowset()) {
                $count = $st->fetchColumn();
            }
            return max(0, (int)$count);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return -1;
        }
    }

    public function recordManualReminderSent(int $id, int $userID): bool
    {
        if ($id <= 0 || $userID <= 0) {
            return false;
        }

        try {
            $st = $this->conn->prepare("
                UPDATE dbo.tblWorkflowTasks
                SET LastManualReminderSentAt = SYSUTCDATETIME(),
                    LastManualReminderByUserID = :userID,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :userID
                WHERE WorkflowTaskID = :id
            ");
            $ok = $st->execute([
                ':id' => $id,
                ':userID' => $userID,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function acquireManualReminderLock(int $id, int $timeoutMs = 0): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $st = $this->conn->prepare("
                DECLARE @result INT;
                EXEC @result = sys.sp_getapplock
                    @Resource = :resource,
                    @LockMode = 'Exclusive',
                    @LockOwner = 'Session',
                    @LockTimeout = :timeoutMs;
                SELECT @result AS LockResult;
            ");
            $st->bindValue(':resource', $this->manualReminderLockResource($id), PDO::PARAM_STR);
            $st->bindValue(':timeoutMs', max(0, $timeoutMs), PDO::PARAM_INT);
            $st->execute();
            $result = $st->fetchColumn();
            while ($result === false && $st->nextRowset()) {
                $result = $st->fetchColumn();
            }
            $locked = (int)$result >= 0;
            $this->lastError = $locked ? '' : 'A manual reminder is already being processed for this task.';
            return $locked;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function releaseManualReminderLock(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $st = $this->conn->prepare("
                DECLARE @result INT;
                EXEC @result = sys.sp_releaseapplock
                    @Resource = :resource,
                    @LockOwner = 'Session';
                SELECT @result AS ReleaseResult;
            ");
            $st->bindValue(':resource', $this->manualReminderLockResource($id), PDO::PARAM_STR);
            $st->execute();
            $result = $st->fetchColumn();
            while ($result === false && $st->nextRowset()) {
                $result = $st->fetchColumn();
            }
            $released = (int)$result >= 0;
            $this->lastError = $released ? '' : 'The manual reminder lock could not be released.';
            return $released;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function wasManualReminderSentRecently(int $id, int $cooldownSeconds): bool
    {
        if ($id <= 0 || $cooldownSeconds <= 0) {
            return false;
        }

        try {
            $st = $this->conn->prepare("
                SELECT CASE
                    WHEN LastManualReminderSentAt IS NOT NULL
                     AND LastManualReminderSentAt >= DATEADD(SECOND, :cooldownOffset, SYSUTCDATETIME())
                    THEN 1 ELSE 0
                END
                FROM dbo.tblWorkflowTasks
                WHERE WorkflowTaskID = :id
            ");
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            $st->bindValue(':cooldownOffset', -1 * max(1, $cooldownSeconds), PDO::PARAM_INT);
            $st->execute();
            $this->lastError = '';
            return (int)($st->fetchColumn() ?: 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function markAutomaticReminderSent(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $st = $this->conn->prepare("
                UPDATE dbo.tblWorkflowTasks
                SET AutoReminderSentAt = SYSUTCDATETIME()
                WHERE WorkflowTaskID = :id
            ");
            $ok = $st->execute([':id' => $id]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function markOverdueEscalationSent(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        try {
            $st = $this->conn->prepare("
                UPDATE dbo.tblWorkflowTasks
                SET OverdueEscalationSentAt = SYSUTCDATETIME()
                WHERE WorkflowTaskID = :id
            ");
            $ok = $st->execute([':id' => $id]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAutomaticReminderDueTasks(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        try {
            $sql = "
                SELECT TOP {$limit} t.WorkflowTaskID
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                WHERE t.AutoReminderEnabled = 1
                  AND t.AutoReminderSentAt IS NULL
                  AND t.DueDate IS NOT NULL
                  AND t.CompletedAt IS NULL
                  AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                  AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                  AND SYSUTCDATETIME() >= DATEADD(DAY, -1 * ISNULL(t.AutoReminderDaysBeforeDue, 0), CAST(t.DueDate AS DATETIME2(0)))
                  AND CAST(SYSUTCDATETIME() AS date) <= CAST(t.DueDate AS date)
                ORDER BY t.DueDate ASC, t.WorkflowTaskID ASC
            ";
            $ids = $this->conn->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $tasks = [];
            foreach ($ids as $id) {
                $task = $this->find((int)$id);
                if ($task) {
                    $tasks[] = $task;
                }
            }
            return $tasks;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOverdueEscalationDueTasks(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));

        try {
            $sql = "
                SELECT TOP {$limit} t.WorkflowTaskID
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                WHERE t.OverdueEscalationEnabled = 1
                  AND t.OverdueEscalationSentAt IS NULL
                  AND t.DueDate IS NOT NULL
                  AND t.CompletedAt IS NULL
                  AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                  AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                  AND CAST(SYSUTCDATETIME() AS date) >= DATEADD(
                      DAY,
                      CASE
                          WHEN ISNULL(t.OverdueEscalationDaysAfterDue, 1) < 1 THEN 1
                          ELSE ISNULL(t.OverdueEscalationDaysAfterDue, 1)
                      END,
                      CAST(t.DueDate AS date)
                  )
                ORDER BY t.DueDate ASC, t.WorkflowTaskID ASC
            ";
            $ids = $this->conn->query($sql)->fetchAll(PDO::FETCH_COLUMN) ?: [];
            $tasks = [];
            foreach ($ids as $id) {
                $task = $this->find((int)$id);
                if ($task) {
                    $tasks[] = $task;
                }
            }
            return $tasks;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listOpenBatchTasks(string $batchID, int $excludeTaskID = 0): array
    {
        $batchID = trim($batchID);
        if ($batchID === '') {
            return [];
        }

        try {
            $where = [
                't.WorkflowTaskBatchID = :batchID',
                't.CompletedAt IS NULL',
                "UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')",
                "UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')",
            ];
            $params = [':batchID' => $batchID];
            if ($excludeTaskID > 0) {
                $where[] = 't.WorkflowTaskID <> :excludeTaskID';
                $params[':excludeTaskID'] = $excludeTaskID;
            }

            $sql = "
                SELECT
                    t.WorkflowTaskID,
                    t.StatusID,
                    t.AssignedToUserID,
                    t.CreatedByUserID,
                    t.Title,
                    t.WorkflowTaskBatchID,
                    t.WorkflowTaskCompletionRule
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                WHERE " . implode(' AND ', $where) . "
                ORDER BY t.WorkflowTaskID ASC
            ";
            $st = $this->conn->prepare($sql);
            foreach ($params as $key => $value) {
                $st->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function updateRecipientResponse(int $id, string $response, int $respondedByUserID): bool
    {
        $sql = "
            UPDATE dbo.tblWorkflowTasks
            SET RecipientResponse = :response,
                RespondedByUserID = :respondedByUserID,
                RespondedAt = SYSUTCDATETIME(),
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :respondedByUserID
            WHERE WorkflowTaskID = :id
        ";

        try {
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':id' => $id,
                ':response' => $response,
                ':respondedByUserID' => $respondedByUserID,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function forwardTask(int $id, int $newAssignedToUserID, string $reason, int $forwardedByUserID): bool
    {
        $sql = "
            UPDATE dbo.tblWorkflowTasks
            SET AssignedToUserID = :assignedToUserID,
                LastForwardedByUserID = :forwardedByUserID,
                LastForwardedAt = SYSUTCDATETIME(),
                LastForwardReason = :reason,
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :forwardedByUserID
            WHERE WorkflowTaskID = :id
        ";

        try {
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':id' => $id,
                ':assignedToUserID' => $newAssignedToUserID,
                ':forwardedByUserID' => $forwardedByUserID,
                ':reason' => $reason,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function addActivity(
        int $workflowTaskID,
        string $activityType,
        ?string $activityNote,
        int $actionByUserID,
        ?int $fromUserID = null,
        ?int $toUserID = null,
        ?int $fromStatusID = null,
        ?int $toStatusID = null,
        ?array $metadata = null
    ): bool {
        if ($workflowTaskID <= 0 || trim($activityType) === '' || $actionByUserID < 0) {
            return false;
        }

        $sql = "
            INSERT INTO dbo.tblWorkflowTaskActivity
                (WorkflowTaskID, ActivityType, ActivityNote, FromUserID, ToUserID,
                 FromStatusID, ToStatusID, ActionByUserID, ActionAt, MetadataJson)
            VALUES
                (:WorkflowTaskID, :ActivityType, :ActivityNote, :FromUserID, :ToUserID,
                 :FromStatusID, :ToStatusID, :ActionByUserID, SYSUTCDATETIME(), :MetadataJson)
        ";

        try {
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':WorkflowTaskID' => $workflowTaskID,
                ':ActivityType' => strtoupper(trim($activityType)),
                ':ActivityNote' => $activityNote,
                ':FromUserID' => $fromUserID,
                ':ToUserID' => $toUserID,
                ':FromStatusID' => $fromStatusID,
                ':ToStatusID' => $toStatusID,
                ':ActionByUserID' => $actionByUserID,
                ':MetadataJson' => $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function listActivity(int $workflowTaskID): array
    {
        try {
            $sql = "
                SELECT
                    a.WorkflowTaskActivityID,
                    a.WorkflowTaskID,
                    a.ActivityType,
                    a.ActivityNote,
                    a.FromUserID,
                    a.ToUserID,
                    a.FromStatusID,
                    a.ToStatusID,
                    a.ActionByUserID,
                    a.ActionAt,
                    a.MetadataJson,
                    CASE
                        WHEN a.ActionByUserID = 0 THEN N'System'
                        ELSE COALESCE(NULLIF(LTRIM(RTRIM(actor.DisplayName)), N''), NULLIF(LTRIM(RTRIM(actor.Username)), N''), CASE WHEN a.ActionByUserID IS NOT NULL THEN CONCAT(N'User #', a.ActionByUserID) ELSE NULL END)
                    END AS ActionByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(fromUser.DisplayName)), N''), NULLIF(LTRIM(RTRIM(fromUser.Username)), N''), CASE WHEN a.FromUserID IS NOT NULL THEN CONCAT(N'User #', a.FromUserID) ELSE NULL END) AS FromUserName,
                    COALESCE(NULLIF(LTRIM(RTRIM(toUser.DisplayName)), N''), NULLIF(LTRIM(RTRIM(toUser.Username)), N''), CASE WHEN a.ToUserID IS NOT NULL THEN CONCAT(N'User #', a.ToUserID) ELSE NULL END) AS ToUserName,
                    fromStatus.Name AS FromStatusName,
                    toStatus.Name AS ToStatusName
                FROM dbo.tblWorkflowTaskActivity a
                LEFT JOIN dbo.tblUsers actor ON a.ActionByUserID = actor.UserID
                LEFT JOIN dbo.tblUsers fromUser ON a.FromUserID = fromUser.UserID
                LEFT JOIN dbo.tblUsers toUser ON a.ToUserID = toUser.UserID
                LEFT JOIN dbo.tblWorkflowTaskStatuses fromStatus ON a.FromStatusID = fromStatus.StatusID
                LEFT JOIN dbo.tblWorkflowTaskStatuses toStatus ON a.ToStatusID = toStatus.StatusID
                WHERE a.WorkflowTaskID = :workflowTaskID
                ORDER BY a.ActionAt DESC, a.WorkflowTaskActivityID DESC
            ";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':workflowTaskID', $workflowTaskID, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAttachments(int $workflowTaskID, bool $includeDeleted = false): array
    {
        if ($workflowTaskID <= 0 || !$this->supportsWorkflowTaskAttachments()) {
            return [];
        }

        $deletedSql = $includeDeleted ? '' : 'AND a.Deleted = 0';

        try {
            $sql = "
                SELECT
                    a.WorkflowTaskAttachmentID,
                    a.WorkflowTaskID,
                    a.OriginalFileName,
                    a.StoredFileName,
                    a.StoragePath,
                    a.MimeType,
                    a.FileSizeBytes,
                    a.UploadedByUserID,
                    a.UploadedAt,
                    a.Deleted,
                    a.DeletedByUserID,
                    a.DeletedAt,
                    COALESCE(NULLIF(LTRIM(RTRIM(uploadedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(uploadedBy.Username)), N''), CASE WHEN a.UploadedByUserID IS NOT NULL THEN CONCAT(N'User #', a.UploadedByUserID) ELSE NULL END) AS UploadedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(deletedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(deletedBy.Username)), N''), CASE WHEN a.DeletedByUserID IS NOT NULL THEN CONCAT(N'User #', a.DeletedByUserID) ELSE NULL END) AS DeletedByName
                FROM dbo.tblWorkflowTaskAttachments a
                LEFT JOIN dbo.tblUsers uploadedBy ON a.UploadedByUserID = uploadedBy.UserID
                LEFT JOIN dbo.tblUsers deletedBy ON a.DeletedByUserID = deletedBy.UserID
                WHERE a.WorkflowTaskID = :workflowTaskID
                  {$deletedSql}
                ORDER BY a.UploadedAt DESC, a.WorkflowTaskAttachmentID DESC
            ";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':workflowTaskID', $workflowTaskID, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getAttachment(int $attachmentID, bool $includeDeleted = false): ?array
    {
        if ($attachmentID <= 0 || !$this->supportsWorkflowTaskAttachments()) {
            return null;
        }

        $deletedSql = $includeDeleted ? '' : 'AND a.Deleted = 0';

        try {
            $sql = "
                SELECT
                    a.WorkflowTaskAttachmentID,
                    a.WorkflowTaskID,
                    a.OriginalFileName,
                    a.StoredFileName,
                    a.StoragePath,
                    a.MimeType,
                    a.FileSizeBytes,
                    a.UploadedByUserID,
                    a.UploadedAt,
                    a.Deleted,
                    a.DeletedByUserID,
                    a.DeletedAt,
                    t.CreatedByUserID,
                    t.AssignedToUserID,
                    t.Title,
                    COALESCE(NULLIF(LTRIM(RTRIM(uploadedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(uploadedBy.Username)), N''), CASE WHEN a.UploadedByUserID IS NOT NULL THEN CONCAT(N'User #', a.UploadedByUserID) ELSE NULL END) AS UploadedByName
                FROM dbo.tblWorkflowTaskAttachments a
                INNER JOIN dbo.tblWorkflowTasks t ON a.WorkflowTaskID = t.WorkflowTaskID
                LEFT JOIN dbo.tblUsers uploadedBy ON a.UploadedByUserID = uploadedBy.UserID
                WHERE a.WorkflowTaskAttachmentID = :attachmentID
                  {$deletedSql}
            ";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':attachmentID', $attachmentID, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function saveAttachment(int $workflowTaskID, array $data): int
    {
        if ($workflowTaskID <= 0 || !$this->supportsWorkflowTaskAttachments()) {
            $this->lastError = 'Workflow task attachment storage is not available.';
            return 0;
        }

        try {
            $sql = "
                INSERT INTO dbo.tblWorkflowTaskAttachments
                    (WorkflowTaskID, OriginalFileName, StoredFileName, StoragePath,
                     MimeType, FileSizeBytes, UploadedByUserID, UploadedAt, Deleted)
                OUTPUT INSERTED.WorkflowTaskAttachmentID
                VALUES
                    (:WorkflowTaskID, :OriginalFileName, :StoredFileName, :StoragePath,
                     :MimeType, :FileSizeBytes, :UploadedByUserID, SYSUTCDATETIME(), 0)
            ";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':WorkflowTaskID' => $workflowTaskID,
                ':OriginalFileName' => trim((string)($data['OriginalFileName'] ?? '')),
                ':StoredFileName' => trim((string)($data['StoredFileName'] ?? '')),
                ':StoragePath' => trim((string)($data['StoragePath'] ?? '')),
                ':MimeType' => $this->nullableString($data['MimeType'] ?? null),
                ':FileSizeBytes' => (int)($data['FileSizeBytes'] ?? 0),
                ':UploadedByUserID' => (int)($data['UploadedByUserID'] ?? 0),
            ]);

            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
                return 0;
            }

            $attachmentID = (int)($st->fetchColumn() ?: 0);
            $this->lastError = '';
            return $attachmentID;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return 0;
        }
    }

    public function markAttachmentDeleted(int $attachmentID, int $userID): bool
    {
        if ($attachmentID <= 0 || $userID <= 0 || !$this->supportsWorkflowTaskAttachments()) {
            return false;
        }

        try {
            $sql = "
                UPDATE dbo.tblWorkflowTaskAttachments
                SET Deleted = 1,
                    DeletedByUserID = :DeletedByUserID,
                    DeletedAt = SYSUTCDATETIME()
                WHERE WorkflowTaskAttachmentID = :WorkflowTaskAttachmentID
                  AND Deleted = 0
            ";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':WorkflowTaskAttachmentID' => $attachmentID,
                ':DeletedByUserID' => $userID,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listComments(int $workflowTaskID, bool $includeDeleted = false): array
    {
        if ($workflowTaskID <= 0 || !$this->supportsWorkflowTaskComments()) {
            return [];
        }

        $deletedSql = $includeDeleted ? '' : 'AND c.Deleted = 0';

        try {
            $sql = "
                SELECT
                    c.WorkflowTaskCommentID,
                    c.WorkflowTaskID,
                    c.CommentText,
                    c.CreatedByUserID,
                    c.CreatedAt,
                    c.Deleted,
                    c.DeletedByUserID,
                    c.DeletedAt,
                    COALESCE(NULLIF(LTRIM(RTRIM(createdBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(createdBy.Username)), N''), CASE WHEN c.CreatedByUserID IS NOT NULL THEN CONCAT(N'User #', c.CreatedByUserID) ELSE NULL END) AS CreatedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(deletedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(deletedBy.Username)), N''), CASE WHEN c.DeletedByUserID IS NOT NULL THEN CONCAT(N'User #', c.DeletedByUserID) ELSE NULL END) AS DeletedByName
                FROM dbo.tblWorkflowTaskComments c
                LEFT JOIN dbo.tblUsers createdBy ON c.CreatedByUserID = createdBy.UserID
                LEFT JOIN dbo.tblUsers deletedBy ON c.DeletedByUserID = deletedBy.UserID
                WHERE c.WorkflowTaskID = :workflowTaskID
                  {$deletedSql}
                ORDER BY c.CreatedAt DESC, c.WorkflowTaskCommentID DESC
            ";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':workflowTaskID', $workflowTaskID, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getComment(int $commentID, bool $includeDeleted = false): ?array
    {
        if ($commentID <= 0 || !$this->supportsWorkflowTaskComments()) {
            return null;
        }

        $deletedSql = $includeDeleted ? '' : 'AND c.Deleted = 0';

        try {
            $sql = "
                SELECT
                    c.WorkflowTaskCommentID,
                    c.WorkflowTaskID,
                    c.CommentText,
                    c.CreatedByUserID AS CommentCreatedByUserID,
                    c.CreatedAt,
                    c.Deleted,
                    c.DeletedByUserID,
                    c.DeletedAt,
                    t.CreatedByUserID,
                    t.AssignedToUserID,
                    t.Title,
                    COALESCE(NULLIF(LTRIM(RTRIM(createdBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(createdBy.Username)), N''), CASE WHEN c.CreatedByUserID IS NOT NULL THEN CONCAT(N'User #', c.CreatedByUserID) ELSE NULL END) AS CreatedByName
                FROM dbo.tblWorkflowTaskComments c
                INNER JOIN dbo.tblWorkflowTasks t ON c.WorkflowTaskID = t.WorkflowTaskID
                LEFT JOIN dbo.tblUsers createdBy ON c.CreatedByUserID = createdBy.UserID
                WHERE c.WorkflowTaskCommentID = :commentID
                  {$deletedSql}
            ";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':commentID', $commentID, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return $row;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function saveComment(int $workflowTaskID, string $commentText, int $createdByUserID): int
    {
        $commentText = trim($commentText);
        if ($workflowTaskID <= 0 || $createdByUserID <= 0 || $commentText === '' || !$this->supportsWorkflowTaskComments()) {
            $this->lastError = 'Workflow task comment storage is not available.';
            return 0;
        }

        try {
            $sql = "
                INSERT INTO dbo.tblWorkflowTaskComments
                    (WorkflowTaskID, CommentText, CreatedByUserID, CreatedAt, Deleted)
                OUTPUT INSERTED.WorkflowTaskCommentID
                VALUES
                    (:WorkflowTaskID, :CommentText, :CreatedByUserID, SYSUTCDATETIME(), 0)
            ";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':WorkflowTaskID' => $workflowTaskID,
                ':CommentText' => $commentText,
                ':CreatedByUserID' => $createdByUserID,
            ]);

            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
                return 0;
            }

            $commentID = (int)($st->fetchColumn() ?: 0);
            $this->lastError = '';
            return $commentID;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return 0;
        }
    }

    public function markCommentDeleted(int $commentID, int $userID): bool
    {
        if ($commentID <= 0 || $userID <= 0 || !$this->supportsWorkflowTaskComments()) {
            return false;
        }

        try {
            $sql = "
                UPDATE dbo.tblWorkflowTaskComments
                SET Deleted = 1,
                    DeletedByUserID = :DeletedByUserID,
                    DeletedAt = SYSUTCDATETIME()
                WHERE WorkflowTaskCommentID = :WorkflowTaskCommentID
                  AND Deleted = 0
            ";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':WorkflowTaskCommentID' => $commentID,
                ':DeletedByUserID' => $userID,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function recordTaskView(int $workflowTaskID, int $userID): bool
    {
        if ($workflowTaskID <= 0 || $userID <= 0 || !$this->supportsWorkflowTaskViews()) {
            return false;
        }

        try {
            $sql = "
                MERGE dbo.tblWorkflowTaskViews AS target
                USING (SELECT :WorkflowTaskID AS WorkflowTaskID, :UserID AS UserID) AS source
                    ON target.WorkflowTaskID = source.WorkflowTaskID
                   AND target.UserID = source.UserID
                WHEN MATCHED THEN
                    UPDATE SET
                        LastViewedAt = SYSUTCDATETIME(),
                        ViewCount = target.ViewCount + 1
                WHEN NOT MATCHED THEN
                    INSERT (WorkflowTaskID, UserID, FirstViewedAt, LastViewedAt, ViewCount)
                    VALUES (source.WorkflowTaskID, source.UserID, SYSUTCDATETIME(), SYSUTCDATETIME(), 1);
            ";
            $st = $this->conn->prepare($sql);
            $ok = $st->execute([
                ':WorkflowTaskID' => $workflowTaskID,
                ':UserID' => $userID,
            ]);
            if (!$ok) {
                $this->lastError = implode(' | ', $st->errorInfo());
            } else {
                $this->lastError = '';
            }
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTaskViews(int $workflowTaskID): array
    {
        if ($workflowTaskID <= 0 || !$this->supportsWorkflowTaskViews()) {
            return [];
        }

        try {
            $sql = "
                SELECT
                    v.WorkflowTaskViewID,
                    v.WorkflowTaskID,
                    v.UserID,
                    v.FirstViewedAt,
                    v.LastViewedAt,
                    v.ViewCount,
                    COALESCE(NULLIF(LTRIM(RTRIM(u.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u.Username)), N''), CASE WHEN v.UserID IS NOT NULL THEN CONCAT(N'User #', v.UserID) ELSE NULL END) AS ViewedByName
                FROM dbo.tblWorkflowTaskViews v
                LEFT JOIN dbo.tblUsers u ON v.UserID = u.UserID
                WHERE v.WorkflowTaskID = :workflowTaskID
                ORDER BY v.LastViewedAt DESC, v.WorkflowTaskViewID DESC
            ";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':workflowTaskID', $workflowTaskID, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProjectTaskOptions(int $workflowProjectID, ?int $excludeTaskID = null): array
    {
        if ($workflowProjectID <= 0) {
            return [];
        }

        try {
            $excludeSql = ($excludeTaskID ?? 0) > 0 ? 'AND t.WorkflowTaskID <> :excludeTaskID' : '';
            $sql = "
                SELECT
                    t.WorkflowTaskID,
                    t.Title,
                    t.ParentWorkflowTaskID,
                    t.PlannedStartDate,
                    t.PlannedEndDate,
                    t.DueDate,
                    t.PercentComplete,
                    t.CompletedAt,
                    st.Code AS StatusCode,
                    st.Name AS StatusName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u.Username)), N''), CASE WHEN t.AssignedToUserID IS NOT NULL THEN CONCAT(N'User #', t.AssignedToUserID) ELSE NULL END) AS AssignedToName
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                LEFT JOIN dbo.tblUsers u ON t.AssignedToUserID = u.UserID
                WHERE t.WorkflowProjectID = :workflowProjectID
                  {$excludeSql}
                ORDER BY
                    CASE WHEN t.ParentWorkflowTaskID IS NULL THEN 0 ELSE 1 END,
                    t.PlannedStartDate ASC,
                    t.DueDate ASC,
                    t.WorkflowTaskID ASC
            ";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':workflowProjectID', $workflowProjectID, PDO::PARAM_INT);
            if (($excludeTaskID ?? 0) > 0) {
                $st->bindValue(':excludeTaskID', (int)$excludeTaskID, PDO::PARAM_INT);
            }
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDependencies(int $workflowTaskID): array
    {
        if ($workflowTaskID <= 0 || !$this->supportsWorkflowTaskDependencies()) {
            return [];
        }

        try {
            $sql = "
                SELECT
                    d.WorkflowTaskDependencyID,
                    d.WorkflowTaskID,
                    d.DependsOnWorkflowTaskID,
                    d.DependencyTypeCode,
                    d.CreatedAt,
                    d.CreatedBy,
                    t.Title AS DependsOnTitle,
                    t.PlannedStartDate AS DependsOnPlannedStartDate,
                    t.PlannedEndDate AS DependsOnPlannedEndDate,
                    t.DueDate AS DependsOnDueDate,
                    t.CompletedAt AS DependsOnCompletedAt,
                    st.Code AS DependsOnStatusCode,
                    st.Name AS DependsOnStatusName
                FROM dbo.tblWorkflowTaskDependencies d
                INNER JOIN dbo.tblWorkflowTasks t ON d.DependsOnWorkflowTaskID = t.WorkflowTaskID
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                WHERE d.WorkflowTaskID = :workflowTaskID
                ORDER BY t.PlannedEndDate ASC, t.DueDate ASC, t.WorkflowTaskID ASC
            ";
            $st = $this->conn->prepare($sql);
            $st->bindValue(':workflowTaskID', $workflowTaskID, PDO::PARAM_INT);
            $st->execute();
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @param array<int, int> $dependsOnWorkflowTaskIDs
     */
    public function saveDependencies(int $workflowTaskID, array $dependsOnWorkflowTaskIDs, ?int $createdBy): bool
    {
        if ($workflowTaskID <= 0) {
            return false;
        }
        if (!$this->supportsWorkflowTaskDependencies()) {
            $this->lastError = 'Workflow task dependency storage is not available.';
            return $dependsOnWorkflowTaskIDs === [];
        }

        $ids = [];
        foreach ($dependsOnWorkflowTaskIDs as $id) {
            $id = (int)$id;
            if ($id > 0 && $id !== $workflowTaskID) {
                $ids[$id] = $id;
            }
        }

        $startedTransaction = !$this->conn->inTransaction();
        try {
            if ($startedTransaction) {
                $this->conn->beginTransaction();
            }

            $delete = $this->conn->prepare('DELETE FROM dbo.tblWorkflowTaskDependencies WHERE WorkflowTaskID = :workflowTaskID');
            $delete->execute([':workflowTaskID' => $workflowTaskID]);

            if ($ids !== []) {
                $insert = $this->conn->prepare("
                    INSERT INTO dbo.tblWorkflowTaskDependencies
                        (WorkflowTaskID, DependsOnWorkflowTaskID, DependencyTypeCode, CreatedAt, CreatedBy)
                    VALUES
                        (:WorkflowTaskID, :DependsOnWorkflowTaskID, N'FINISH_TO_START', SYSUTCDATETIME(), :CreatedBy)
                ");
                foreach ($ids as $dependsOnID) {
                    $insert->execute([
                        ':WorkflowTaskID' => $workflowTaskID,
                        ':DependsOnWorkflowTaskID' => $dependsOnID,
                        ':CreatedBy' => $createdBy,
                    ]);
                }
            }

            if ($startedTransaction) {
                $this->conn->commit();
            }
            $this->lastError = '';
            return true;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $startedTransaction = !$this->conn->inTransaction();

        try {
            if ($startedTransaction) {
                $this->conn->beginTransaction();
            }

            if ($this->supportsWorkflowEntityLinks()) {
                $st = $this->conn->prepare("
                    DELETE FROM dbo.tblWorkflowEntityLinks
                    WHERE WorkflowTaskID = :id
                ");
                $st->execute([':id' => $id]);
            }

            if ($this->supportsWorkflowTaskDependencies()) {
                $st = $this->conn->prepare("
                    DELETE FROM dbo.tblWorkflowTaskDependencies
                    WHERE WorkflowTaskID = :idAsTask
                       OR DependsOnWorkflowTaskID = :idAsDependency
                ");
                $st->execute([
                    ':idAsTask' => $id,
                    ':idAsDependency' => $id,
                ]);
            }

            if ($this->columnExists('dbo.tblWorkflowTasks', 'ParentWorkflowTaskID')) {
                $st = $this->conn->prepare("
                    UPDATE dbo.tblWorkflowTasks
                    SET ParentWorkflowTaskID = NULL
                    WHERE ParentWorkflowTaskID = :id
                ");
                $st->execute([':id' => $id]);
            }

            $st = $this->conn->prepare("DELETE FROM dbo.tblWorkflowTasks WHERE WorkflowTaskID = :id");
            $ok = $st->execute([':id' => $id]);

            if ($startedTransaction) {
                $this->conn->commit();
            }

            return $ok;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function find(int $id): ?array
    {
        $hasTaskViews = $this->supportsWorkflowTaskViews();
        $recipientViewSelect = $hasTaskViews
            ? "
                    recipientView.FirstViewedAt AS RecipientFirstViewedAt,
                    recipientView.LastViewedAt AS RecipientLastViewedAt,
                    recipientView.ViewCount AS RecipientViewCount,"
            : "
                    CAST(NULL AS DATETIME2(0)) AS RecipientFirstViewedAt,
                    CAST(NULL AS DATETIME2(0)) AS RecipientLastViewedAt,
                    CAST(NULL AS INT) AS RecipientViewCount,";
        $recipientViewJoin = $hasTaskViews
            ? "
                LEFT JOIN dbo.tblWorkflowTaskViews recipientView
                    ON recipientView.WorkflowTaskID = t.WorkflowTaskID
                   AND recipientView.UserID = t.AssignedToUserID"
            : '';

        try {
            $sql = "
                SELECT
                    t.WorkflowTaskID,
                    t.Title,
                    t.Description,
                    t.PriorityCode,
                    t.DueDate,
                    t.WorkflowProjectID,
                    t.ParentWorkflowTaskID,
                    t.PlannedStartDate,
                    t.PlannedEndDate,
                    t.PercentComplete,
                    t.ProjectUtilisationPercent,
                    t.CompletedAt,
                    t.CreatedAt,
                    t.UpdatedAt,
                    t.RecipientResponse,
                    t.RespondedByUserID,
                    t.RespondedAt,
                    t.LastForwardedByUserID,
                    t.LastForwardedAt,
                    t.LastForwardReason,
                    t.NotifyCreatorOnCompletion,
                    t.NotifyCreatorOnUpdate,
                    t.NotifyAudienceOnComment,
                    t.AutoReminderEnabled,
                    t.AutoReminderDaysBeforeDue,
                    t.AutoReminderSentAt,
                    t.LastManualReminderSentAt,
                    t.LastManualReminderByUserID,
                    t.OverdueEscalationEnabled,
                    t.OverdueEscalationDaysAfterDue,
                    t.OverdueEscalationSentAt,
                    t.WorkflowTaskBatchID,
                    t.WorkflowTaskCompletionRule,
                    t.RelatedEntity,
                    t.RelatedKey,
                    t.TaskTypeID,
                    t.StatusID,
                    t.AssignedToUserID,
                    t.CreatedByUserID,
                    t.UpdatedBy,
                    {$recipientViewSelect}
                    ty.Name AS TaskTypeName,
                    st.Code AS StatusCode,
                    st.Name AS StatusName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u1.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u1.Username)), N''), CASE WHEN t.CreatedByUserID IS NOT NULL THEN CONCAT(N'User #', t.CreatedByUserID) ELSE NULL END) AS CreatedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u2.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u2.Username)), N''), CASE WHEN t.AssignedToUserID IS NOT NULL THEN CONCAT(N'User #', t.AssignedToUserID) ELSE NULL END) AS AssignedToName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u3.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u3.Username)), N''), CASE WHEN t.RespondedByUserID IS NOT NULL THEN CONCAT(N'User #', t.RespondedByUserID) ELSE NULL END) AS RespondedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u4.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u4.Username)), N''), CASE WHEN t.LastForwardedByUserID IS NOT NULL THEN CONCAT(N'User #', t.LastForwardedByUserID) ELSE NULL END) AS LastForwardedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u5.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u5.Username)), N''), CASE WHEN t.UpdatedBy IS NOT NULL THEN CONCAT(N'User #', t.UpdatedBy) ELSE NULL END) AS UpdatedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(u6.DisplayName)), N''), NULLIF(LTRIM(RTRIM(u6.Username)), N''), CASE WHEN t.LastManualReminderByUserID IS NOT NULL THEN CONCAT(N'User #', t.LastManualReminderByUserID) ELSE NULL END) AS LastManualReminderByName,
                    parentTask.Title AS ParentWorkflowTaskTitle,
                    p.ProjectCode,
                    p.ProjectName,
                    p.ProjectStatusCode,
                    p.StartDate AS ProjectStartDate,
                    p.TargetEndDate AS ProjectTargetEndDate
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskTypes ty ON t.TaskTypeID = ty.TaskTypeID
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                LEFT JOIN dbo.tblWorkflowProjects p ON t.WorkflowProjectID = p.WorkflowProjectID
                LEFT JOIN dbo.tblUsers u1 ON t.CreatedByUserID = u1.UserID
                LEFT JOIN dbo.tblUsers u2 ON t.AssignedToUserID = u2.UserID
                LEFT JOIN dbo.tblUsers u3 ON t.RespondedByUserID = u3.UserID
                LEFT JOIN dbo.tblUsers u4 ON t.LastForwardedByUserID = u4.UserID
                LEFT JOIN dbo.tblUsers u5 ON t.UpdatedBy = u5.UserID
                LEFT JOIN dbo.tblUsers u6 ON t.LastManualReminderByUserID = u6.UserID
                LEFT JOIN dbo.tblWorkflowTasks parentTask ON t.ParentWorkflowTaskID = parentTask.WorkflowTaskID
                {$recipientViewJoin}
                WHERE t.WorkflowTaskID = :id
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function listOpenByRelatedEntityKey(string $relatedEntity, string $relatedKey): array
    {
        try {
            $sql = "
                SELECT
                    t.WorkflowTaskID,
                    t.TaskTypeID,
                    t.StatusID,
                    t.Title,
                    t.Description,
                    t.PriorityCode,
                    t.CreatedByUserID,
                    t.AssignedToUserID,
                    t.RelatedEntity,
                    t.RelatedKey,
                    t.DueDate,
                    t.CompletedAt
                FROM dbo.tblWorkflowTasks t
                LEFT JOIN dbo.tblWorkflowTaskStatuses st ON t.StatusID = st.StatusID
                WHERE UPPER(ISNULL(t.RelatedEntity, '')) = UPPER(:relatedEntity)
                  AND ISNULL(t.RelatedKey, '') = :relatedKey
                  AND t.CompletedAt IS NULL
                  AND UPPER(ISNULL(st.Code, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                  AND UPPER(ISNULL(st.Name, '')) NOT IN ('COMPLETED','CANCELLED','CLOSED','DONE','RESOLVED')
                ORDER BY t.WorkflowTaskID DESC
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':relatedEntity' => $relatedEntity,
                ':relatedKey' => $relatedKey,
            ]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function findOpenByRelatedEntityKeyAndAssignee(string $relatedEntity, string $relatedKey, int $assignedToUserID): ?array
    {
        $rows = $this->listOpenByRelatedEntityKey($relatedEntity, $relatedKey);
        foreach ($rows as $row) {
            if ((int) ($row['AssignedToUserID'] ?? 0) === $assignedToUserID) {
                return $row;
            }
        }
        return null;
    }

    private function normalizePriorityCode($value): string
    {
        $code = strtoupper(trim((string) $value));
        return in_array($code, ['LOW', 'NORMAL', 'HIGH', 'URGENT'], true) ? $code : 'NORMAL';
    }

    private function normalizeCompletionRule($value): string
    {
        $code = strtoupper(trim((string) $value));
        return in_array($code, ['INDIVIDUAL', 'ANY_COMPLETES_ALL'], true) ? $code : 'INDIVIDUAL';
    }

    private function normalizeReminderDays($value): int
    {
        return max(0, min(365, (int)$value));
    }

    private function normalizeEscalationDays($value): int
    {
        return max(1, min(365, (int)$value));
    }

    private function normalizePercentComplete($value): float
    {
        return max(0, min(100, round((float)$value, 2)));
    }

    private function normalizeDueState($value): string
    {
        $state = strtolower(trim((string)$value));
        return in_array($state, ['overdue', 'today', 'soon'], true) ? $state : '';
    }

    private function manualReminderLockResource(int $id): string
    {
        return 'workflow-task-manual-reminder:' . $id;
    }

    private function nullableString($value): ?string
    {
        $text = trim((string)$value);
        return $text !== '' ? $text : null;
    }

    private function tableExists(string $qualifiedName): bool
    {
        $name = trim($qualifiedName);
        if ($name === '') {
            return false;
        }

        try {
            $st = $this->conn->prepare("SELECT CASE WHEN OBJECT_ID(:qualifiedName, N'U') IS NULL THEN 0 ELSE 1 END");
            $st->execute([':qualifiedName' => $name]);
            return (int)($st->fetchColumn() ?: 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    private function columnExists(string $qualifiedName, string $columnName): bool
    {
        $name = trim($qualifiedName);
        $column = trim($columnName);
        if ($name === '' || $column === '') {
            return false;
        }

        try {
            $st = $this->conn->prepare("
                SELECT CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM sys.columns
                        WHERE object_id = OBJECT_ID(:qualifiedName, N'U')
                          AND name = :columnName
                    )
                    THEN 1 ELSE 0 END
            ");
            $st->execute([
                ':qualifiedName' => $name,
                ':columnName' => $column,
            ]);
            return (int)($st->fetchColumn() ?: 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
}
