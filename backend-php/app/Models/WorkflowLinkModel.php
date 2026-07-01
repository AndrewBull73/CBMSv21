<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkflowLinkModel
{
    private string $lastError = '';

    public function __construct(private PDO $conn)
    {
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function supportsWorkflowLinks(): bool
    {
        return $this->tableExists('dbo.tblWorkflowEntityLinks');
    }

    /**
     * @return array<string, string>
     */
    public function linkTypeOptions(): array
    {
        return [
            'RELATED_ITEM' => 'workflow_link_type_related_item',
            'REQUIREMENT' => 'workflow_link_type_requirement',
            'ISSUE' => 'workflow_link_type_issue',
            'TRAINING_SCENARIO' => 'workflow_link_type_training_scenario',
            'TRAINING_ASSIGNMENT' => 'workflow_link_type_training_assignment',
            'TRAINING_SESSION' => 'workflow_link_type_training_session',
            'SCREEN_TEST_SCENARIO' => 'workflow_link_type_screen_test_scenario',
            'SCREEN_TEST_RUN' => 'workflow_link_type_screen_test_run',
            'TEST_EVIDENCE' => 'workflow_link_type_test_evidence',
            'DEFECT' => 'workflow_link_type_defect',
            'RELEASE_CHECKLIST' => 'workflow_link_type_release_checklist',
            'DOCUMENTATION' => 'workflow_link_type_documentation',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTaskLinks(int $workflowTaskID): array
    {
        if ($workflowTaskID <= 0 || !$this->supportsWorkflowLinks()) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare($this->baseLinkSelectSql() . "
                WHERE l.WorkflowTaskID = :workflowTaskID
                  AND l.Active = 1
                ORDER BY l.CreatedAt DESC, l.WorkflowLinkID DESC
            ");
            $stmt->bindValue(':workflowTaskID', $workflowTaskID, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProjectLinks(int $workflowProjectID, int $limit = 100): array
    {
        if ($workflowProjectID <= 0 || !$this->supportsWorkflowLinks()) {
            return [];
        }

        $limit = max(1, min(500, $limit));

        try {
            $stmt = $this->conn->prepare("
                SELECT TOP {$limit} *
                FROM (
                    " . $this->baseLinkSelectSql() . "
                    WHERE l.Active = 1
                      AND (
                          l.WorkflowProjectID = :workflowProjectID
                          OR linkedTask.WorkflowProjectID = :workflowProjectIDForTask
                      )
                ) linkedRows
                ORDER BY linkedRows.CreatedAt DESC, linkedRows.WorkflowLinkID DESC
            ");
            $stmt->bindValue(':workflowProjectID', $workflowProjectID, PDO::PARAM_INT);
            $stmt->bindValue(':workflowProjectIDForTask', $workflowProjectID, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRequirementLinks(int $workflowRequirementID): array
    {
        if ($workflowRequirementID <= 0 || !$this->supportsWorkflowLinks()) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare($this->baseLinkSelectSql() . "
                WHERE l.Active = 1
                  AND l.LinkedEntity = N'WorkflowRequirement'
                  AND l.LinkedEntityID = :workflowRequirementID
                ORDER BY
                    CASE WHEN l.WorkflowTaskID IS NULL THEN 1 ELSE 0 END,
                    l.CreatedAt DESC,
                    l.WorkflowLinkID DESC
            ");
            $stmt->bindValue(':workflowRequirementID', $workflowRequirementID, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRequirementLinkedTaskLinks(int $workflowRequirementID): array
    {
        if ($workflowRequirementID <= 0 || !$this->supportsWorkflowLinks()) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare($this->baseLinkSelectSql() . "
                INNER JOIN (
                    SELECT DISTINCT WorkflowTaskID
                    FROM dbo.tblWorkflowEntityLinks
                    WHERE Active = 1
                      AND LinkedEntity = N'WorkflowRequirement'
                      AND LinkedEntityID = :workflowRequirementIDForTasks
                      AND WorkflowTaskID IS NOT NULL
                ) requirementTask
                    ON requirementTask.WorkflowTaskID = l.WorkflowTaskID
                WHERE l.Active = 1
                  AND NOT (
                      l.LinkedEntity = N'WorkflowRequirement'
                      AND l.LinkedEntityID = :workflowRequirementID
                  )
                ORDER BY l.CreatedAt DESC, l.WorkflowLinkID DESC
            ");
            $stmt->bindValue(':workflowRequirementIDForTasks', $workflowRequirementID, PDO::PARAM_INT);
            $stmt->bindValue(':workflowRequirementID', $workflowRequirementID, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listIssueLinks(int $workflowIssueID): array
    {
        if ($workflowIssueID <= 0 || !$this->supportsWorkflowLinks()) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare($this->baseLinkSelectSql() . "
                WHERE l.Active = 1
                  AND l.LinkedEntity = N'WorkflowIssue'
                  AND l.LinkedEntityID = :workflowIssueID
                ORDER BY
                    CASE WHEN l.WorkflowTaskID IS NULL THEN 1 ELSE 0 END,
                    l.CreatedAt DESC,
                    l.WorkflowLinkID DESC
            ");
            $stmt->bindValue(':workflowIssueID', $workflowIssueID, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $requirementLinks
     * @param array<int, array<string, mixed>> $relatedLinks
     * @return array<string, int>
     */
    public function summarizeRequirementTraceability(array $requirementLinks, array $relatedLinks): array
    {
        $summary = [
            'taskLinks' => 0,
            'openTasks' => 0,
            'closedTasks' => 0,
            'training' => 0,
            'testing' => 0,
            'defects' => 0,
            'documentation' => 0,
            'release' => 0,
            'other' => 0,
        ];

        foreach ($requirementLinks as $link) {
            if ((int)($link['WorkflowTaskID'] ?? 0) <= 0) {
                continue;
            }

            $summary['taskLinks']++;
            $statusCode = strtoupper(trim((string)($link['WorkflowTaskStatusCode'] ?? '')));
            $statusName = strtoupper(trim((string)($link['WorkflowTaskStatusName'] ?? '')));
            $completedAt = trim((string)($link['WorkflowTaskCompletedAt'] ?? ''));
            if ($completedAt !== ''
                || in_array($statusCode, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
                || in_array($statusName, ['COMPLETED', 'CANCELLED', 'CLOSED', 'DONE', 'RESOLVED'], true)
            ) {
                $summary['closedTasks']++;
            } else {
                $summary['openTasks']++;
            }
        }

        foreach ($relatedLinks as $link) {
            $type = strtoupper(trim((string)($link['LinkTypeCode'] ?? '')));
            if (str_starts_with($type, 'TRAINING_')) {
                $summary['training']++;
            } elseif (str_starts_with($type, 'SCREEN_TEST_') || $type === 'TEST_EVIDENCE') {
                $summary['testing']++;
            } elseif ($type === 'DEFECT') {
                $summary['defects']++;
            } elseif ($type === 'DOCUMENTATION') {
                $summary['documentation']++;
            } elseif ($type === 'RELEASE_CHECKLIST') {
                $summary['release']++;
            } elseif ($type !== '') {
                $summary['other']++;
            }
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeProjectLinks(int $workflowProjectID): array
    {
        $summary = [
            'total' => 0,
            'requirements' => 0,
            'training' => 0,
            'testing' => 0,
            'defects' => 0,
            'documentation' => 0,
            'release' => 0,
            'other' => 0,
        ];

        if ($workflowProjectID <= 0 || !$this->supportsWorkflowLinks()) {
            return $summary;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT
                    UPPER(l.LinkTypeCode) AS LinkTypeCode,
                    COUNT(*) AS LinkCount
                FROM dbo.tblWorkflowEntityLinks l
                LEFT JOIN dbo.tblWorkflowTasks linkedTask
                    ON linkedTask.WorkflowTaskID = l.WorkflowTaskID
                WHERE l.Active = 1
                  AND (
                      l.WorkflowProjectID = :workflowProjectID
                      OR linkedTask.WorkflowProjectID = :workflowProjectIDForTask
                  )
                GROUP BY UPPER(l.LinkTypeCode)
            ");
            $stmt->bindValue(':workflowProjectID', $workflowProjectID, PDO::PARAM_INT);
            $stmt->bindValue(':workflowProjectIDForTask', $workflowProjectID, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $count = (int)($row['LinkCount'] ?? 0);
                $type = strtoupper(trim((string)($row['LinkTypeCode'] ?? '')));
                $summary['total'] += $count;
                if (str_starts_with($type, 'TRAINING_')) {
                    $summary['training'] += $count;
                } elseif ($type === 'REQUIREMENT') {
                    $summary['requirements'] = ($summary['requirements'] ?? 0) + $count;
                } elseif (str_starts_with($type, 'SCREEN_TEST_') || $type === 'TEST_EVIDENCE') {
                    $summary['testing'] += $count;
                } elseif ($type === 'DEFECT') {
                    $summary['defects'] += $count;
                } elseif ($type === 'DOCUMENTATION') {
                    $summary['documentation'] += $count;
                } elseif ($type === 'RELEASE_CHECKLIST') {
                    $summary['release'] += $count;
                } else {
                    $summary['other'] += $count;
                }
            }
            $this->lastError = '';
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
        }

        return $summary;
    }

    public function findLink(int $workflowLinkID): ?array
    {
        if ($workflowLinkID <= 0 || !$this->supportsWorkflowLinks()) {
            return null;
        }

        try {
            $stmt = $this->conn->prepare($this->baseLinkSelectSql() . "
                WHERE l.WorkflowLinkID = :workflowLinkID
            ");
            $stmt->bindValue(':workflowLinkID', $workflowLinkID, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->lastError = '';
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function saveTaskLink(array $data, int $currentUserID): int
    {
        if (!$this->supportsWorkflowLinks()) {
            throw new \RuntimeException('Workflow link storage is not installed.');
        }

        $workflowTaskID = (int)($data['WorkflowTaskID'] ?? 0);
        if ($workflowTaskID <= 0) {
            throw new \InvalidArgumentException('A task is required before a link can be saved.');
        }

        $linkTypeCode = $this->normalizeLinkType($data['LinkTypeCode'] ?? 'RELATED_ITEM');
        $linkedEntity = $this->nullableString($data['LinkedEntity'] ?? null);
        $linkedTitle = $this->nullableString($data['LinkedTitle'] ?? null);
        $linkedKey = $this->nullableString($data['LinkedEntityKey'] ?? null);
        $linkedUrl = $this->nullableString($data['LinkedUrl'] ?? null);
        $linkedEntityID = (int)($data['LinkedEntityID'] ?? 0);
        $notes = $this->nullableString($data['Notes'] ?? null);

        if ($linkedEntity === null) {
            throw new \InvalidArgumentException('Linked entity is required.');
        }
        if ($linkedEntityID <= 0 && $linkedKey === null && $linkedTitle === null && $linkedUrl === null) {
            throw new \InvalidArgumentException('Enter a linked id, key, title, or URL.');
        }

        $workflowProjectID = $this->findProjectIdForTask($workflowTaskID);
        if ($workflowProjectID <= 0 && (int)($data['WorkflowProjectID'] ?? 0) > 0) {
            $workflowProjectID = (int)$data['WorkflowProjectID'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblWorkflowEntityLinks
                (WorkflowProjectID, WorkflowTaskID, LinkTypeCode, LinkedEntity, LinkedEntityID,
                 LinkedEntityKey, LinkedTitle, LinkedUrl, Notes, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
            OUTPUT INSERTED.WorkflowLinkID
            VALUES
                (:WorkflowProjectID, :WorkflowTaskID, :LinkTypeCode, :LinkedEntity, :LinkedEntityID,
                 :LinkedEntityKey, :LinkedTitle, :LinkedUrl, :Notes, 1, SYSUTCDATETIME(), :CreatedBy, SYSUTCDATETIME(), :UpdatedBy)
        ");
        $stmt->execute([
            ':WorkflowProjectID' => $workflowProjectID > 0 ? $workflowProjectID : null,
            ':WorkflowTaskID' => $workflowTaskID,
            ':LinkTypeCode' => $linkTypeCode,
            ':LinkedEntity' => $linkedEntity,
            ':LinkedEntityID' => $linkedEntityID > 0 ? $linkedEntityID : null,
            ':LinkedEntityKey' => $linkedKey,
            ':LinkedTitle' => $linkedTitle,
            ':LinkedUrl' => $linkedUrl,
            ':Notes' => $notes,
            ':CreatedBy' => $currentUserID > 0 ? $currentUserID : null,
            ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
        ]);

        $workflowLinkID = (int)($stmt->fetchColumn() ?: 0);
        if ($workflowLinkID <= 0) {
            throw new \RuntimeException('Workflow link save did not return a link id.');
        }
        $this->lastError = '';
        return $workflowLinkID;
    }

    public function syncTaskRequirementLink(
        int $workflowTaskID,
        ?int $workflowRequirementID,
        ?string $requirementCode,
        ?string $requirementTitle,
        ?string $requirementUrl,
        ?string $notes,
        int $currentUserID
    ): int {
        if (!$this->supportsWorkflowLinks()) {
            throw new \RuntimeException('Workflow link storage is not installed.');
        }
        if ($workflowTaskID <= 0) {
            throw new \InvalidArgumentException('A task is required before a requirement link can be saved.');
        }

        $requirementID = (int)($workflowRequirementID ?? 0);
        $updatedBy = $currentUserID > 0 ? $currentUserID : null;
        if ($requirementID <= 0) {
            $deactivate = $this->conn->prepare("
                UPDATE dbo.tblWorkflowEntityLinks
                SET Active = 0,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowTaskID = :WorkflowTaskID
                  AND LinkTypeCode = N'REQUIREMENT'
                  AND LinkedEntity = N'WorkflowRequirement'
                  AND Active = 1
            ");
            $deactivate->execute([
                ':UpdatedBy' => $updatedBy,
                ':WorkflowTaskID' => $workflowTaskID,
            ]);
            $this->lastError = '';
            return 0;
        }

        $workflowProjectID = $this->findProjectIdForTask($workflowTaskID);

        $deactivateOther = $this->conn->prepare("
            UPDATE dbo.tblWorkflowEntityLinks
            SET Active = 0,
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :UpdatedBy
            WHERE WorkflowTaskID = :WorkflowTaskID
              AND LinkTypeCode = N'REQUIREMENT'
              AND LinkedEntity = N'WorkflowRequirement'
              AND Active = 1
              AND (LinkedEntityID IS NULL OR LinkedEntityID <> :WorkflowRequirementID)
        ");
        $deactivateOther->execute([
            ':UpdatedBy' => $updatedBy,
            ':WorkflowTaskID' => $workflowTaskID,
            ':WorkflowRequirementID' => $requirementID,
        ]);

        $existing = $this->conn->prepare("
            SELECT TOP 1 WorkflowLinkID
            FROM dbo.tblWorkflowEntityLinks
            WHERE WorkflowTaskID = :WorkflowTaskID
              AND LinkTypeCode = N'REQUIREMENT'
              AND LinkedEntity = N'WorkflowRequirement'
              AND LinkedEntityID = :WorkflowRequirementID
            ORDER BY Active DESC, WorkflowLinkID DESC
        ");
        $existing->execute([
            ':WorkflowTaskID' => $workflowTaskID,
            ':WorkflowRequirementID' => $requirementID,
        ]);
        $workflowLinkID = (int)($existing->fetchColumn() ?: 0);

        if ($workflowLinkID > 0) {
            $update = $this->conn->prepare("
                UPDATE dbo.tblWorkflowEntityLinks
                SET WorkflowProjectID = :WorkflowProjectID,
                    LinkedEntityKey = :LinkedEntityKey,
                    LinkedTitle = :LinkedTitle,
                    LinkedUrl = :LinkedUrl,
                    Notes = :Notes,
                    Active = 1,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowLinkID = :WorkflowLinkID
            ");
            $update->execute([
                ':WorkflowProjectID' => $workflowProjectID > 0 ? $workflowProjectID : null,
                ':LinkedEntityKey' => $this->nullableString($requirementCode),
                ':LinkedTitle' => $this->nullableString($requirementTitle),
                ':LinkedUrl' => $this->nullableString($requirementUrl),
                ':Notes' => $this->nullableString($notes),
                ':UpdatedBy' => $updatedBy,
                ':WorkflowLinkID' => $workflowLinkID,
            ]);
            $this->lastError = '';
            return $workflowLinkID;
        }

        $insert = $this->conn->prepare("
            INSERT INTO dbo.tblWorkflowEntityLinks
                (WorkflowProjectID, WorkflowTaskID, LinkTypeCode, LinkedEntity, LinkedEntityID,
                 LinkedEntityKey, LinkedTitle, LinkedUrl, Notes, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
            OUTPUT INSERTED.WorkflowLinkID
            VALUES
                (:WorkflowProjectID, :WorkflowTaskID, N'REQUIREMENT', N'WorkflowRequirement', :WorkflowRequirementID,
                 :LinkedEntityKey, :LinkedTitle, :LinkedUrl, :Notes, 1, SYSUTCDATETIME(), :CreatedBy, SYSUTCDATETIME(), :UpdatedBy)
        ");
        $insert->execute([
            ':WorkflowProjectID' => $workflowProjectID > 0 ? $workflowProjectID : null,
            ':WorkflowTaskID' => $workflowTaskID,
            ':WorkflowRequirementID' => $requirementID,
            ':LinkedEntityKey' => $this->nullableString($requirementCode),
            ':LinkedTitle' => $this->nullableString($requirementTitle),
            ':LinkedUrl' => $this->nullableString($requirementUrl),
            ':Notes' => $this->nullableString($notes),
            ':CreatedBy' => $updatedBy,
            ':UpdatedBy' => $updatedBy,
        ]);
        $workflowLinkID = (int)($insert->fetchColumn() ?: 0);
        if ($workflowLinkID <= 0) {
            throw new \RuntimeException('Requirement link save did not return a link id.');
        }

        $this->lastError = '';
        return $workflowLinkID;
    }

    public function syncProjectEntityLink(
        ?int $workflowProjectID,
        string $linkTypeCode,
        string $linkedEntity,
        int $linkedEntityID,
        ?string $linkedEntityKey,
        ?string $linkedTitle,
        ?string $linkedUrl,
        ?string $notes,
        int $currentUserID
    ): void {
        if (!$this->supportsWorkflowLinks() || $linkedEntityID <= 0) {
            return;
        }

        $linkTypeCode = $this->normalizeLinkType($linkTypeCode);
        $linkedEntity = trim($linkedEntity);
        if ($linkedEntity === '') {
            return;
        }

        $workflowProjectID = ($workflowProjectID ?? 0) > 0 ? (int)$workflowProjectID : null;

        if ($workflowProjectID === null) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowEntityLinks
                SET Active = 0,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE LinkTypeCode = :LinkTypeCode
                  AND LinkedEntity = :LinkedEntity
                  AND LinkedEntityID = :LinkedEntityID
                  AND WorkflowTaskID IS NULL
                  AND Active = 1
            ");
            $stmt->execute([
                ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
                ':LinkTypeCode' => $linkTypeCode,
                ':LinkedEntity' => $linkedEntity,
                ':LinkedEntityID' => $linkedEntityID,
            ]);
            return;
        }

        $deactivate = $this->conn->prepare("
            UPDATE dbo.tblWorkflowEntityLinks
            SET Active = 0,
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :UpdatedBy
            WHERE LinkTypeCode = :LinkTypeCode
              AND LinkedEntity = :LinkedEntity
              AND LinkedEntityID = :LinkedEntityID
              AND WorkflowTaskID IS NULL
              AND Active = 1
              AND (WorkflowProjectID IS NULL OR WorkflowProjectID <> :WorkflowProjectID)
        ");
        $deactivate->execute([
            ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
            ':LinkTypeCode' => $linkTypeCode,
            ':LinkedEntity' => $linkedEntity,
            ':LinkedEntityID' => $linkedEntityID,
            ':WorkflowProjectID' => $workflowProjectID,
        ]);

        $existing = $this->conn->prepare("
            SELECT TOP 1 WorkflowLinkID
            FROM dbo.tblWorkflowEntityLinks
            WHERE WorkflowProjectID = :WorkflowProjectID
              AND WorkflowTaskID IS NULL
              AND LinkTypeCode = :LinkTypeCode
              AND LinkedEntity = :LinkedEntity
              AND LinkedEntityID = :LinkedEntityID
            ORDER BY Active DESC, WorkflowLinkID DESC
        ");
        $existing->execute([
            ':WorkflowProjectID' => $workflowProjectID,
            ':LinkTypeCode' => $linkTypeCode,
            ':LinkedEntity' => $linkedEntity,
            ':LinkedEntityID' => $linkedEntityID,
        ]);
        $workflowLinkID = (int)($existing->fetchColumn() ?: 0);

        if ($workflowLinkID > 0) {
            $update = $this->conn->prepare("
                UPDATE dbo.tblWorkflowEntityLinks
                SET LinkedEntityKey = :LinkedEntityKey,
                    LinkedTitle = :LinkedTitle,
                    LinkedUrl = :LinkedUrl,
                    Notes = :Notes,
                    Active = 1,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowLinkID = :WorkflowLinkID
            ");
            $update->execute([
                ':LinkedEntityKey' => $this->nullableString($linkedEntityKey),
                ':LinkedTitle' => $this->nullableString($linkedTitle),
                ':LinkedUrl' => $this->nullableString($linkedUrl),
                ':Notes' => $this->nullableString($notes),
                ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
                ':WorkflowLinkID' => $workflowLinkID,
            ]);
            return;
        }

        $insert = $this->conn->prepare("
            INSERT INTO dbo.tblWorkflowEntityLinks
                (WorkflowProjectID, WorkflowTaskID, LinkTypeCode, LinkedEntity, LinkedEntityID,
                 LinkedEntityKey, LinkedTitle, LinkedUrl, Notes, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
            VALUES
                (:WorkflowProjectID, NULL, :LinkTypeCode, :LinkedEntity, :LinkedEntityID,
                 :LinkedEntityKey, :LinkedTitle, :LinkedUrl, :Notes, 1, SYSUTCDATETIME(), :CreatedBy, SYSUTCDATETIME(), :UpdatedBy)
        ");
        $insert->execute([
            ':WorkflowProjectID' => $workflowProjectID,
            ':LinkTypeCode' => $linkTypeCode,
            ':LinkedEntity' => $linkedEntity,
            ':LinkedEntityID' => $linkedEntityID,
            ':LinkedEntityKey' => $this->nullableString($linkedEntityKey),
            ':LinkedTitle' => $this->nullableString($linkedTitle),
            ':LinkedUrl' => $this->nullableString($linkedUrl),
            ':Notes' => $this->nullableString($notes),
            ':CreatedBy' => $currentUserID > 0 ? $currentUserID : null,
            ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
        ]);
    }

    public function deactivateLink(int $workflowLinkID, int $currentUserID): bool
    {
        if ($workflowLinkID <= 0 || !$this->supportsWorkflowLinks()) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowEntityLinks
                SET Active = 0,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowLinkID = :WorkflowLinkID
            ");
            $ok = $stmt->execute([
                ':WorkflowLinkID' => $workflowLinkID,
                ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
            ]);
            $this->lastError = $ok ? '' : implode(' | ', $stmt->errorInfo());
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    private function baseLinkSelectSql(): string
    {
        return "
            SELECT
                l.WorkflowLinkID,
                l.WorkflowProjectID,
                l.WorkflowTaskID,
                l.LinkTypeCode,
                l.LinkedEntity,
                l.LinkedEntityID,
                l.LinkedEntityKey,
                l.LinkedTitle,
                l.LinkedUrl,
                l.Notes,
                l.Active,
                l.CreatedAt,
                l.CreatedBy,
                l.UpdatedAt,
                l.UpdatedBy,
                linkedTask.Title AS WorkflowTaskTitle,
                linkedTask.WorkflowProjectID AS LinkedTaskProjectID,
                linkedTask.PriorityCode AS WorkflowTaskPriorityCode,
                linkedTask.DueDate AS WorkflowTaskDueDate,
                linkedTask.PlannedStartDate AS WorkflowTaskPlannedStartDate,
                linkedTask.PlannedEndDate AS WorkflowTaskPlannedEndDate,
                linkedTask.PercentComplete AS WorkflowTaskPercentComplete,
                linkedTask.CompletedAt AS WorkflowTaskCompletedAt,
                taskStatus.Code AS WorkflowTaskStatusCode,
                taskStatus.Name AS WorkflowTaskStatusName,
                COALESCE(NULLIF(LTRIM(RTRIM(assignedTo.DisplayName)), N''), NULLIF(LTRIM(RTRIM(assignedTo.Username)), N''), CASE WHEN linkedTask.AssignedToUserID IS NOT NULL THEN CONCAT(N'User #', linkedTask.AssignedToUserID) ELSE NULL END) AS WorkflowTaskAssignedToName,
                COALESCE(NULLIF(LTRIM(RTRIM(createdBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(createdBy.Username)), N''), CASE WHEN l.CreatedBy IS NOT NULL THEN CONCAT(N'User #', l.CreatedBy) ELSE NULL END) AS CreatedByName,
                COALESCE(NULLIF(LTRIM(RTRIM(updatedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(updatedBy.Username)), N''), CASE WHEN l.UpdatedBy IS NOT NULL THEN CONCAT(N'User #', l.UpdatedBy) ELSE NULL END) AS UpdatedByName
            FROM dbo.tblWorkflowEntityLinks l
            LEFT JOIN dbo.tblWorkflowTasks linkedTask
                ON linkedTask.WorkflowTaskID = l.WorkflowTaskID
            LEFT JOIN dbo.tblWorkflowTaskStatuses taskStatus
                ON taskStatus.StatusID = linkedTask.StatusID
            LEFT JOIN dbo.tblUsers assignedTo
                ON assignedTo.UserID = linkedTask.AssignedToUserID
            LEFT JOIN dbo.tblUsers createdBy
                ON createdBy.UserID = l.CreatedBy
            LEFT JOIN dbo.tblUsers updatedBy
                ON updatedBy.UserID = l.UpdatedBy
        ";
    }

    private function findProjectIdForTask(int $workflowTaskID): int
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT WorkflowProjectID
                FROM dbo.tblWorkflowTasks
                WHERE WorkflowTaskID = :WorkflowTaskID
            ");
            $stmt->bindValue(':WorkflowTaskID', $workflowTaskID, PDO::PARAM_INT);
            $stmt->execute();
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return 0;
        }
    }

    private function normalizeLinkType($value): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->linkTypeOptions()) ? $code : 'RELATED_ITEM';
    }

    private function nullableString($value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function tableExists(string $qualifiedName): bool
    {
        try {
            $stmt = $this->conn->prepare("SELECT CASE WHEN OBJECT_ID(:qualifiedName, N'U') IS NULL THEN 0 ELSE 1 END");
            $stmt->execute([':qualifiedName' => trim($qualifiedName)]);
            return (int)($stmt->fetchColumn() ?: 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
}
