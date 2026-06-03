<?php
declare(strict_types=1);

namespace App\Models;

use App\Shared\SessionHelper;
use PDO;

final class WorkflowEngineModel
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsWorkflowDefinitions(): bool
    {
        return $this->tableExists('dbo.tblWorkflowDefinition')
            && $this->tableExists('dbo.tblWorkflowDefinitionStage')
            && $this->tableExists('dbo.tblWorkflowDefinitionAction');
    }

    public function supportsWorkflowInstances(): bool
    {
        return $this->supportsWorkflowDefinitions()
            && $this->tableExists('dbo.tblWorkflowInstance')
            && $this->tableExists('dbo.tblWorkflowInstanceHistory');
    }

    public function listWorkflowDefinitions(bool $activeOnly = true): array
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return [];
        }

        $sql = "
            SELECT
                WorkflowDefinitionID,
                WorkflowAreaCode,
                WorkflowAreaName,
                ModuleCode,
                RecordTableName,
                Description,
                RouteByDataObjectHierarchy,
                ActiveFlag
            FROM dbo.tblWorkflowDefinition
        ";
        if ($activeOnly) {
            $sql .= " WHERE ActiveFlag = 1";
        }
        $sql .= " ORDER BY WorkflowAreaName ASC, WorkflowAreaCode ASC";

        $stmt = $this->db->query($sql);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    public function getWorkflowDefinition(string $workflowAreaCode): ?array
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return null;
        }

        $area = strtoupper(trim($workflowAreaCode));
        if ($area === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                WorkflowDefinitionID,
                WorkflowAreaCode,
                WorkflowAreaName,
                ModuleCode,
                RecordTableName,
                Description,
                RouteByDataObjectHierarchy,
                ActiveFlag
            FROM dbo.tblWorkflowDefinition
            WHERE WorkflowAreaCode = :area
        ");
        $stmt->execute([':area' => $area]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listWorkflowStages(string $workflowAreaCode = '', bool $activeOnly = true): array
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        $area = strtoupper(trim($workflowAreaCode));
        if ($area !== '') {
            $where[] = 's.WorkflowAreaCode = :area';
            $params[':area'] = $area;
        }
        if ($activeOnly) {
            $where[] = 's.ActiveFlag = 1';
        }

        $sql = "
            SELECT
                s.WorkflowDefinitionStageID,
                s.WorkflowDefinitionID,
                s.WorkflowAreaCode,
                s.WorkflowStageCode,
                s.WorkflowStageName,
                s.StageOrder,
                s.StageType,
                s.RequiredPermissionCodes,
                s.RouteByDataObjectHierarchy,
                s.AllowReturn,
                s.AllowReject,
                s.AllowCancel,
                s.AllowsDelegation,
                s.RequireDifferentActorFromPreviousStage,
                s.IsDraftStage,
                s.IsFinalStage,
                s.ActiveFlag
            FROM dbo.tblWorkflowDefinitionStage s
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.WorkflowAreaCode ASC, s.StageOrder ASC, s.WorkflowStageCode ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getWorkflowStage(string $workflowAreaCode, string $workflowStageCode): ?array
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return null;
        }

        $area = strtoupper(trim($workflowAreaCode));
        $stage = strtoupper(trim($workflowStageCode));
        if ($area === '' || $stage === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                WorkflowDefinitionStageID,
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
                ActiveFlag
            FROM dbo.tblWorkflowDefinitionStage
            WHERE WorkflowAreaCode = :area
              AND WorkflowStageCode = :stage
        ");
        $stmt->execute([
            ':area' => $area,
            ':stage' => $stage,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listWorkflowActions(string $workflowAreaCode, string $fromStageCode = '', bool $activeOnly = true): array
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return [];
        }

        $area = strtoupper(trim($workflowAreaCode));
        if ($area === '') {
            return [];
        }

        $where = ['WorkflowAreaCode = :area'];
        $params = [':area' => $area];
        $fromStage = strtoupper(trim($fromStageCode));
        if ($fromStage !== '') {
            $where[] = 'FromStageCode = :fromStage';
            $params[':fromStage'] = $fromStage;
        }
        if ($activeOnly) {
            $where[] = 'ActiveFlag = 1';
        }

        $sql = "
            SELECT
                WorkflowDefinitionActionID,
                WorkflowDefinitionID,
                WorkflowAreaCode,
                WorkflowActionCode,
                WorkflowActionName,
                FromStageCode,
                ToStageCode,
                ActionType,
                RequiredPermissionCodes,
                RequireNote,
                ActiveFlag
            FROM dbo.tblWorkflowDefinitionAction
            WHERE " . implode(' AND ', $where) . "
            ORDER BY FromStageCode ASC, WorkflowActionCode ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getWorkflowInstanceByRecord(string $workflowAreaCode, string $recordTableName, string $recordKey): ?array
    {
        if (!$this->supportsWorkflowInstances()) {
            return null;
        }

        $area = strtoupper(trim($workflowAreaCode));
        $table = trim($recordTableName);
        $key = trim($recordKey);
        if ($area === '' || $table === '' || $key === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dbo.tblWorkflowInstance
            WHERE WorkflowAreaCode = :area
              AND RecordTableName = :tableName
              AND RecordKey = :recordKey
        ");
        $stmt->execute([
            ':area' => $area,
            ':tableName' => $table,
            ':recordKey' => $key,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getWorkflowInstance(int $workflowInstanceId): ?array
    {
        if (!$this->supportsWorkflowInstances() || $workflowInstanceId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dbo.tblWorkflowInstance
            WHERE WorkflowInstanceID = :id
        ");
        $stmt->execute([':id' => $workflowInstanceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function ensureWorkflowInstance(array $payload): array
    {
        if (!$this->supportsWorkflowInstances()) {
            throw new \RuntimeException('Workflow engine foundation tables are not installed.');
        }

        $area = strtoupper(trim((string) ($payload['WorkflowAreaCode'] ?? '')));
        $recordTableName = trim((string) ($payload['RecordTableName'] ?? ''));
        $recordId = isset($payload['RecordID']) && $payload['RecordID'] !== '' ? (int) $payload['RecordID'] : null;
        $recordKey = trim((string) ($payload['RecordKey'] ?? ($recordId !== null ? (string) $recordId : '')));
        $userId = (int) ($payload['UserID'] ?? 0);

        if ($area === '' || $recordTableName === '' || $recordKey === '') {
            throw new \RuntimeException('Workflow area, record table, and record key are required.');
        }

        $definition = $this->getWorkflowDefinition($area);
        if ($definition === null) {
            throw new \RuntimeException('Workflow area ' . $area . ' is not defined.');
        }

        $requestedStageCode = strtoupper(trim((string) ($payload['CurrentStageCode'] ?? '')));
        $stage = $requestedStageCode !== ''
            ? $this->getWorkflowStage($area, $requestedStageCode)
            : $this->getInitialWorkflowStage($area);
        if ($stage === null) {
            throw new \RuntimeException('Workflow area ' . $area . ' has no initial stage configured.');
        }

        $currentStageCode = strtoupper(trim((string) ($stage['WorkflowStageCode'] ?? '')));
        $currentStatusCode = strtoupper(trim((string) ($payload['CurrentStatusCode'] ?? $currentStageCode)));
        $fiscalYearId = $this->nullableInt($payload['FiscalYearID'] ?? null);
        $versionId = $this->nullableInt($payload['VersionID'] ?? null);
        $dataObjectCode = $this->nullableString($payload['DataObjectCode'] ?? null);
        $scopeDataObjectCode = $this->nullableString($payload['ScopeDataObjectCode'] ?? ($dataObjectCode ?? null));
        $workflowTitle = $this->nullableString($payload['WorkflowTitle'] ?? null);
        $workflowNote = $this->nullableString($payload['WorkflowNote'] ?? null);
        $existing = $this->getWorkflowInstanceByRecord($area, $recordTableName, $recordKey);

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            if ($existing !== null) {
                $stmt = $this->db->prepare("
                    UPDATE dbo.tblWorkflowInstance
                    SET FiscalYearID = :fy,
                        VersionID = :ver,
                        DataObjectCode = :dataObjectCode,
                        ScopeDataObjectCode = :scopeDataObjectCode,
                        WorkflowTitle = :workflowTitle,
                        WorkflowNote = :workflowNote,
                        UpdatedBy = :updatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE WorkflowInstanceID = :id
                ");
                $stmt->execute([
                    ':fy' => $fiscalYearId,
                    ':ver' => $versionId,
                    ':dataObjectCode' => $dataObjectCode,
                    ':scopeDataObjectCode' => $scopeDataObjectCode,
                    ':workflowTitle' => $workflowTitle,
                    ':workflowNote' => $workflowNote,
                    ':updatedBy' => $userId > 0 ? $userId : null,
                    ':id' => (int) ($existing['WorkflowInstanceID'] ?? 0),
                ]);

                if ($ownsTransaction && $this->db->inTransaction()) {
                    $this->db->commit();
                }
                return $this->getWorkflowInstance((int) ($existing['WorkflowInstanceID'] ?? 0)) ?? $existing;
            }

            $stmt = $this->db->prepare("
                INSERT INTO dbo.tblWorkflowInstance
                (
                    WorkflowDefinitionID,
                    WorkflowAreaCode,
                    RecordTableName,
                    RecordID,
                    RecordKey,
                    FiscalYearID,
                    VersionID,
                    DataObjectCode,
                    ScopeDataObjectCode,
                    CurrentStageCode,
                    CurrentStatusCode,
                    CurrentAssignmentScopeCode,
                    WorkflowTitle,
                    WorkflowNote,
                    ActiveFlag,
                    CreatedBy,
                    UpdatedBy
                )
                VALUES
                (
                    :definitionId,
                    :area,
                    :recordTableName,
                    :recordId,
                    :recordKey,
                    :fy,
                    :ver,
                    :dataObjectCode,
                    :scopeDataObjectCode,
                    :currentStageCode,
                    :currentStatusCode,
                    :currentAssignmentScopeCode,
                    :workflowTitle,
                    :workflowNote,
                    1,
                    :createdBy,
                    :updatedBy
                )
            ");
            $stmt->execute([
                ':definitionId' => (int) ($definition['WorkflowDefinitionID'] ?? 0),
                ':area' => $area,
                ':recordTableName' => $recordTableName,
                ':recordId' => $recordId,
                ':recordKey' => $recordKey,
                ':fy' => $fiscalYearId,
                ':ver' => $versionId,
                ':dataObjectCode' => $dataObjectCode,
                ':scopeDataObjectCode' => $scopeDataObjectCode,
                ':currentStageCode' => $currentStageCode,
                ':currentStatusCode' => $currentStatusCode,
                ':currentAssignmentScopeCode' => $scopeDataObjectCode,
                ':workflowTitle' => $workflowTitle,
                ':workflowNote' => $workflowNote,
                ':createdBy' => $userId > 0 ? $userId : null,
                ':updatedBy' => $userId > 0 ? $userId : null,
            ]);

            $workflowInstanceId = (int) ($this->db->lastInsertId() ?: 0);
            if ($workflowInstanceId <= 0) {
                throw new \RuntimeException('Workflow instance could not be created.');
            }

            $this->recordHistory($workflowInstanceId, $area, 'CREATE', null, $currentStageCode, $scopeDataObjectCode, null, $workflowNote, $userId);

            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            return $this->getWorkflowInstance($workflowInstanceId) ?? [];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function transitionWorkflowInstance(int $workflowInstanceId, string $workflowActionCode, int $userId, ?string $note = null): array
    {
        if (!$this->supportsWorkflowInstances()) {
            throw new \RuntimeException('Workflow engine foundation tables are not installed.');
        }

        $instance = $this->getWorkflowInstance($workflowInstanceId);
        if ($instance === null) {
            throw new \RuntimeException('Workflow instance was not found.');
        }

        $area = strtoupper(trim((string) ($instance['WorkflowAreaCode'] ?? '')));
        $currentStageCode = strtoupper(trim((string) ($instance['CurrentStageCode'] ?? '')));
        $actionCode = strtoupper(trim($workflowActionCode));
        if ($area === '' || $currentStageCode === '' || $actionCode === '') {
            throw new \RuntimeException('Workflow instance state is incomplete.');
        }

        $action = $this->findAction($area, $currentStageCode, $actionCode);
        if ($action === null) {
            throw new \RuntimeException('Workflow action ' . $actionCode . ' is not allowed from stage ' . $currentStageCode . '.');
        }

        $currentStage = $this->getWorkflowStage($area, $currentStageCode);
        if ($currentStage === null) {
            throw new \RuntimeException('Current workflow stage ' . $currentStageCode . ' is not configured.');
        }

        $this->assertUserCanPerformAction($instance, $currentStage, $action, $userId);

        $actionNote = $this->nullableString($note);
        if ((int) ($action['RequireNote'] ?? 0) === 1 && $actionNote === null) {
            throw new \RuntimeException('A note is required for this workflow action.');
        }

        $targetStageCode = strtoupper(trim((string) ($action['ToStageCode'] ?? '')));
        $targetStage = $this->getWorkflowStage($area, $targetStageCode);
        if ($targetStage === null) {
            throw new \RuntimeException('Target workflow stage ' . $targetStageCode . ' is not configured.');
        }

        $scopeDataObjectCode = $this->nullableString($instance['ScopeDataObjectCode'] ?? null);
        $resolvedAssignments = $this->resolveAssignmentsForStage(
            $area,
            $targetStageCode,
            (int) ($instance['FiscalYearID'] ?? 0),
            (int) ($instance['VersionID'] ?? 0),
            (string) ($scopeDataObjectCode ?? '')
        );
        $assignmentScopeCode = $scopeDataObjectCode;
        $assignmentUserId = null;
        if ($resolvedAssignments !== []) {
            $assignmentScopeCode = $this->nullableString($resolvedAssignments[0]['DataObjectCode'] ?? $scopeDataObjectCode);
            $assignmentUserId = (int) ($resolvedAssignments[0]['UserID'] ?? 0);
        }

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $sql = "
                UPDATE dbo.tblWorkflowInstance
                SET CurrentStageCode = :currentStageCode,
                    CurrentStatusCode = :currentStatusCode,
                    CurrentAssignmentScopeCode = :currentAssignmentScopeCode,
                    WorkflowNote = :workflowNote,
                    LastActionCode = :lastActionCode,
                    LastActionBy = :lastActionBy,
                    LastActionDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()";

            $params = [
                ':currentStageCode' => $targetStageCode,
                ':currentStatusCode' => $targetStageCode,
                ':currentAssignmentScopeCode' => $assignmentScopeCode,
                ':workflowNote' => $actionNote,
                ':lastActionCode' => $actionCode,
                ':lastActionBy' => $userId > 0 ? $userId : null,
                ':updatedBy' => $userId > 0 ? $userId : null,
                ':id' => $workflowInstanceId,
            ];

            if ($actionCode === 'SUBMIT' && (int) ($instance['SubmittedBy'] ?? 0) <= 0) {
                $sql .= ",
                    SubmittedBy = :submittedBy,
                    SubmittedDate = SYSDATETIME()";
                $params[':submittedBy'] = $userId > 0 ? $userId : null;
            }
            if ($actionCode === 'APPROVE' || $targetStageCode === 'APPROVED') {
                $sql .= ",
                    ApprovedBy = :approvedBy,
                    ApprovedDate = SYSDATETIME()";
                $params[':approvedBy'] = $userId > 0 ? $userId : null;
            }
            if ($actionCode === 'CANCEL' || $targetStageCode === 'CANCELLED') {
                $sql .= ",
                    CancelledBy = :cancelledBy,
                    CancelledDate = SYSDATETIME()";
                $params[':cancelledBy'] = $userId > 0 ? $userId : null;
            }

            $sql .= "
                WHERE WorkflowInstanceID = :id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->recordHistory(
                $workflowInstanceId,
                $area,
                $actionCode,
                $currentStageCode,
                $targetStageCode,
                $assignmentScopeCode,
                $assignmentUserId,
                $actionNote,
                $userId
            );

            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->commit();
            }
            return $this->getWorkflowInstance($workflowInstanceId) ?? $instance;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getWorkflowHistory(int $workflowInstanceId): array
    {
        if (!$this->supportsWorkflowInstances() || $workflowInstanceId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                WorkflowInstanceHistoryID,
                WorkflowInstanceID,
                WorkflowAreaCode,
                WorkflowActionCode,
                FromStageCode,
                ToStageCode,
                AssignmentScopeCode,
                AssignmentUserID,
                ActionNote,
                ActionBy,
                ActionDate
            FROM dbo.tblWorkflowInstanceHistory
            WHERE WorkflowInstanceID = :id
            ORDER BY ActionDate DESC, WorkflowInstanceHistoryID DESC
        ");
        $stmt->execute([':id' => $workflowInstanceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getWorkflowPanelState(string $workflowAreaCode, string $recordTableName, string $recordKey): array
    {
        $instance = $this->getWorkflowInstanceByRecord($workflowAreaCode, $recordTableName, $recordKey);
        if ($instance === null) {
            return [
                'Definition' => $this->getWorkflowDefinition($workflowAreaCode),
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $area = strtoupper(trim((string) ($instance['WorkflowAreaCode'] ?? '')));
        $stageCode = strtoupper(trim((string) ($instance['CurrentStageCode'] ?? '')));
        $currentStage = $this->getWorkflowStage($area, $stageCode);
        $assignments = $this->resolveAssignmentsForStage(
            $area,
            $stageCode,
            (int) ($instance['FiscalYearID'] ?? 0),
            (int) ($instance['VersionID'] ?? 0),
            (string) ($instance['ScopeDataObjectCode'] ?? '')
        );
        $allowedActions = $this->getAllowedWorkflowActionsForUser(
            $instance,
            $currentStage,
            $assignments,
            $this->getCurrentUserId()
        );

        return [
            'Definition' => $this->getWorkflowDefinition($area),
            'Instance' => $instance,
            'CurrentStage' => $currentStage,
            'AllowedActions' => $allowedActions,
            'Assignments' => $assignments,
            'History' => $this->getWorkflowHistory((int) ($instance['WorkflowInstanceID'] ?? 0)),
        ];
    }

    public function resolveAssignmentsForStage(string $workflowAreaCode, string $workflowStageCode, int $fiscalYearId, int $versionId, string $dataObjectCode): array
    {
        $assignmentModel = new WorkflowAssignmentModel($this->db);
        return $assignmentModel->resolveAssignments($workflowAreaCode, $workflowStageCode, $fiscalYearId, $versionId, $dataObjectCode);
    }

    private function getAllowedWorkflowActionsForUser(array $instance, ?array $currentStage, array $assignments, int $userId): array
    {
        $area = strtoupper(trim((string) ($instance['WorkflowAreaCode'] ?? '')));
        $stageCode = strtoupper(trim((string) ($instance['CurrentStageCode'] ?? '')));
        if ($area === '' || $stageCode === '' || $currentStage === null || $userId <= 0) {
            return [];
        }

        $actions = $this->listWorkflowActions($area, $stageCode);
        if ($actions === []) {
            return [];
        }

        $userPermissions = $this->loadUserPermissionCodes($userId);
        $allowed = [];
        foreach ($actions as $action) {
            if ($this->canUserPerformAction($instance, $currentStage, $action, $userId, $assignments, $userPermissions)) {
                $allowed[] = $action;
            }
        }

        return $allowed;
    }

    private function assertUserCanPerformAction(array $instance, array $currentStage, array $action, int $userId): void
    {
        if ($userId <= 0) {
            throw new \RuntimeException('A valid workflow actor is required.');
        }

        $userPermissions = $this->loadUserPermissionCodes($userId);
        $stageRequiredPermissions = $this->parsePermissionCodes($currentStage['RequiredPermissionCodes'] ?? null);
        if (!$this->userMatchesPermissionCodes($userId, $stageRequiredPermissions, $userPermissions)) {
            throw new \RuntimeException('You do not hold one of the required permissions for this workflow stage.');
        }

        $assignments = $this->resolveCurrentStageAssignments($instance);
        if ($assignments !== [] && !$this->userMatchesAssignments($userId, $assignments)) {
            throw new \RuntimeException('You are not assigned to act on this workflow item at the current stage.');
        }

        $actionRequiredPermissions = $this->parsePermissionCodes($action['RequiredPermissionCodes'] ?? null);
        if (!$this->userMatchesPermissionCodes($userId, $actionRequiredPermissions, $userPermissions)) {
            throw new \RuntimeException('You do not hold one of the required permissions for this workflow action.');
        }

        $lastActionBy = (int) ($instance['LastActionBy'] ?? 0);
        if ((int) ($currentStage['RequireDifferentActorFromPreviousStage'] ?? 0) === 1
            && $lastActionBy > 0
            && $lastActionBy === $userId) {
            throw new \RuntimeException('This workflow stage requires a different actor from the previous stage.');
        }
    }

    private function canUserPerformAction(
        array $instance,
        array $currentStage,
        array $action,
        int $userId,
        array $assignments,
        array $userPermissions
    ): bool {
        if ($userId <= 0) {
            return false;
        }

        $stageRequiredPermissions = $this->parsePermissionCodes($currentStage['RequiredPermissionCodes'] ?? null);
        if (!$this->userMatchesPermissionCodes($userId, $stageRequiredPermissions, $userPermissions)) {
            return false;
        }

        if ($assignments !== [] && !$this->userMatchesAssignments($userId, $assignments)) {
            return false;
        }

        $actionRequiredPermissions = $this->parsePermissionCodes($action['RequiredPermissionCodes'] ?? null);
        if (!$this->userMatchesPermissionCodes($userId, $actionRequiredPermissions, $userPermissions)) {
            return false;
        }

        $lastActionBy = (int) ($instance['LastActionBy'] ?? 0);
        if ((int) ($currentStage['RequireDifferentActorFromPreviousStage'] ?? 0) === 1
            && $lastActionBy > 0
            && $lastActionBy === $userId) {
            return false;
        }

        return true;
    }

    private function resolveCurrentStageAssignments(array $instance): array
    {
        $area = strtoupper(trim((string) ($instance['WorkflowAreaCode'] ?? '')));
        $stageCode = strtoupper(trim((string) ($instance['CurrentStageCode'] ?? '')));
        $scopeDataObjectCode = $this->nullableString($instance['ScopeDataObjectCode'] ?? ($instance['DataObjectCode'] ?? null));
        if ($area === '' || $stageCode === '') {
            return [];
        }

        return $this->resolveAssignmentsForStage(
            $area,
            $stageCode,
            (int) ($instance['FiscalYearID'] ?? 0),
            (int) ($instance['VersionID'] ?? 0),
            (string) ($scopeDataObjectCode ?? '')
        );
    }

    private function parsePermissionCodes(mixed $value): array
    {
        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        $tokens = preg_split('/\s*,\s*/', $text) ?: [];
        $codes = [];
        foreach ($tokens as $token) {
            $code = strtoupper(trim((string) $token));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }

    private function loadUserPermissionCodes(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $sessionUserId = (int) SessionHelper::get('auth.user_id', 0);
        $sessionPerms = SessionHelper::get('auth.perms', []);
        if ($sessionUserId === $userId && is_array($sessionPerms) && $sessionPerms !== []) {
            $normalized = [];
            foreach ($sessionPerms as $perm) {
                $code = strtoupper(trim((string) $perm));
                if ($code !== '') {
                    $normalized[$code] = true;
                }
            }
            return array_keys($normalized);
        }

        $stmt = $this->db->prepare("
            SELECT DISTINCT p.PermissionCode
            FROM dbo.tblUserRoles ur
            INNER JOIN dbo.tblRolePermissions rp
                ON rp.RoleID = ur.RoleID
            INNER JOIN dbo.tblPermissions p
                ON p.PermissionID = rp.PermissionID
            WHERE ur.UserID = :userId
              AND (p.Active = 1 OR p.Active IS NULL)
        ");
        $stmt->execute([':userId' => $userId]);

        $codes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $code = strtoupper(trim((string) ($row['PermissionCode'] ?? '')));
            if ($code !== '') {
                $codes[$code] = true;
            }
        }

        return array_keys($codes);
    }

    private function userMatchesPermissionCodes(int $userId, array $requiredPermissionCodes, array $userPermissions): bool
    {
        if ($requiredPermissionCodes === []) {
            return true;
        }

        if ($userId <= 0) {
            return false;
        }

        if (in_array('AUTHENTICATED', $requiredPermissionCodes, true)) {
            return true;
        }

        foreach ($requiredPermissionCodes as $code) {
            if (in_array($code, $userPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    private function userMatchesAssignments(int $userId, array $assignments): bool
    {
        foreach ($assignments as $assignment) {
            if ((int) ($assignment['UserID'] ?? 0) === $userId) {
                return true;
            }
        }

        return false;
    }

    private function getCurrentUserId(): int
    {
        return (int) SessionHelper::get('auth.user_id', 0);
    }

    private function getInitialWorkflowStage(string $workflowAreaCode): ?array
    {
        $area = strtoupper(trim($workflowAreaCode));
        if ($area === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT TOP 1
                WorkflowDefinitionStageID,
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
                ActiveFlag
            FROM dbo.tblWorkflowDefinitionStage
            WHERE WorkflowAreaCode = :area
              AND ActiveFlag = 1
            ORDER BY CASE WHEN IsDraftStage = 1 THEN 0 ELSE 1 END ASC,
                     StageOrder ASC,
                     WorkflowDefinitionStageID ASC
        ");
        $stmt->execute([':area' => $area]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function findAction(string $workflowAreaCode, string $fromStageCode, string $workflowActionCode): ?array
    {
        $area = strtoupper(trim($workflowAreaCode));
        $fromStage = strtoupper(trim($fromStageCode));
        $actionCode = strtoupper(trim($workflowActionCode));
        if ($area === '' || $fromStage === '' || $actionCode === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT TOP 1
                WorkflowDefinitionActionID,
                WorkflowDefinitionID,
                WorkflowAreaCode,
                WorkflowActionCode,
                WorkflowActionName,
                FromStageCode,
                ToStageCode,
                ActionType,
                RequiredPermissionCodes,
                RequireNote,
                ActiveFlag
            FROM dbo.tblWorkflowDefinitionAction
            WHERE WorkflowAreaCode = :area
              AND FromStageCode = :fromStage
              AND WorkflowActionCode = :actionCode
              AND ActiveFlag = 1
            ORDER BY WorkflowDefinitionActionID ASC
        ");
        $stmt->execute([
            ':area' => $area,
            ':fromStage' => $fromStage,
            ':actionCode' => $actionCode,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function recordHistory(
        int $workflowInstanceId,
        string $workflowAreaCode,
        string $workflowActionCode,
        ?string $fromStageCode,
        string $toStageCode,
        ?string $assignmentScopeCode,
        ?int $assignmentUserId,
        ?string $actionNote,
        int $userId
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblWorkflowInstanceHistory
            (
                WorkflowInstanceID,
                WorkflowAreaCode,
                WorkflowActionCode,
                FromStageCode,
                ToStageCode,
                AssignmentScopeCode,
                AssignmentUserID,
                ActionNote,
                ActionBy
            )
            VALUES
            (
                :workflowInstanceId,
                :workflowAreaCode,
                :workflowActionCode,
                :fromStageCode,
                :toStageCode,
                :assignmentScopeCode,
                :assignmentUserId,
                :actionNote,
                :actionBy
            )
        ");
        $stmt->execute([
            ':workflowInstanceId' => $workflowInstanceId,
            ':workflowAreaCode' => strtoupper(trim($workflowAreaCode)),
            ':workflowActionCode' => strtoupper(trim($workflowActionCode)),
            ':fromStageCode' => $this->nullableString($fromStageCode),
            ':toStageCode' => strtoupper(trim($toStageCode)),
            ':assignmentScopeCode' => $this->nullableString($assignmentScopeCode),
            ':assignmentUserId' => $assignmentUserId,
            ':actionNote' => $this->nullableString($actionNote),
            ':actionBy' => $userId > 0 ? $userId : 1,
        ]);
    }

    private function tableExists(string $qualifiedName): bool
    {
        $schema = 'dbo';
        $table = $qualifiedName;
        if (str_contains($qualifiedName, '.')) {
            [$schema, $table] = explode('.', $qualifiedName, 2);
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = :schemaName
              AND TABLE_NAME = :tableName
        ");
        $stmt->execute([
            ':schemaName' => $schema,
            ':tableName' => $table,
        ]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
