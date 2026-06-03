<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkflowEngineAdminModel
{
    private WorkflowEngineModel $engineModel;
    private WorkflowAssignmentModel $assignmentModel;

    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->engineModel = new WorkflowEngineModel($db);
        $this->assignmentModel = new WorkflowAssignmentModel($db);
    }

    public function supportsWorkflowDefinitions(): bool
    {
        return $this->engineModel->supportsWorkflowDefinitions();
    }

    public function supportsWorkflowInstances(): bool
    {
        return $this->engineModel->supportsWorkflowInstances();
    }

    public function supportsWorkflowAssignments(): bool
    {
        return $this->assignmentModel->supportsWorkflowAssignments();
    }

    public function listDefinitions(string $activeFilter = ''): array
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return [];
        }

        $where = [];
        $params = [];
        if ($activeFilter !== '') {
            $where[] = 'd.ActiveFlag = :activeFlag';
            $params[':activeFlag'] = (int) $activeFilter;
        }

        $sql = "
            SELECT
                d.WorkflowDefinitionID,
                d.WorkflowAreaCode,
                d.WorkflowAreaName,
                d.ModuleCode,
                d.RecordTableName,
                d.Description,
                d.RouteByDataObjectHierarchy,
                d.ActiveFlag,
                d.CreatedDate,
                d.UpdatedDate,
                COALESCE(stage.StageCount, 0) AS StageCount,
                COALESCE(stage.ActiveStageCount, 0) AS ActiveStageCount,
                COALESCE(actionRow.ActionCount, 0) AS ActionCount,
                COALESCE(actionRow.ActiveActionCount, 0) AS ActiveActionCount,
                COALESCE(instanceRow.InstanceCount, 0) AS InstanceCount,
                COALESCE(instanceRow.OpenInstanceCount, 0) AS OpenInstanceCount
            FROM dbo.tblWorkflowDefinition d
            OUTER APPLY (
                SELECT
                    COUNT(*) AS StageCount,
                    SUM(CASE WHEN s.ActiveFlag = 1 THEN 1 ELSE 0 END) AS ActiveStageCount
                FROM dbo.tblWorkflowDefinitionStage s
                WHERE s.WorkflowAreaCode = d.WorkflowAreaCode
            ) stage
            OUTER APPLY (
                SELECT
                    COUNT(*) AS ActionCount,
                    SUM(CASE WHEN a.ActiveFlag = 1 THEN 1 ELSE 0 END) AS ActiveActionCount
                FROM dbo.tblWorkflowDefinitionAction a
                WHERE a.WorkflowAreaCode = d.WorkflowAreaCode
            ) actionRow
            OUTER APPLY (
                SELECT
                    COUNT(*) AS InstanceCount,
                    SUM(CASE WHEN i.ActiveFlag = 1 AND UPPER(ISNULL(i.CurrentStatusCode, '')) NOT IN ('APPROVED', 'CANCELLED') THEN 1 ELSE 0 END) AS OpenInstanceCount
                FROM dbo.tblWorkflowInstance i
                WHERE i.WorkflowAreaCode = d.WorkflowAreaCode
            ) instanceRow
        ";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY d.WorkflowAreaName ASC, d.WorkflowAreaCode ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDefinition(string $workflowAreaCode): ?array
    {
        return $this->engineModel->getWorkflowDefinition($workflowAreaCode);
    }

    public function saveDefinition(array $payload): string
    {
        if (!$this->supportsWorkflowDefinitions()) {
            throw new \RuntimeException('Workflow engine definition tables are not installed.');
        }

        $originalAreaCode = strtoupper(trim((string) ($payload['OriginalWorkflowAreaCode'] ?? '')));
        $areaCode = strtoupper(trim((string) ($payload['WorkflowAreaCode'] ?? '')));
        $areaName = trim((string) ($payload['WorkflowAreaName'] ?? ''));
        $moduleCode = $this->nullableString($payload['ModuleCode'] ?? null);
        $recordTableName = $this->nullableString($payload['RecordTableName'] ?? null);
        $description = $this->nullableString($payload['Description'] ?? null);
        $routeByHierarchy = !empty($payload['RouteByDataObjectHierarchy']) ? 1 : 0;
        $activeFlag = array_key_exists('ActiveFlag', $payload) ? (int) $payload['ActiveFlag'] : 1;
        $userId = $this->nullableInt($payload['UpdatedBy'] ?? null);

        if ($areaCode === '' || $areaName === '') {
            throw new \RuntimeException('Workflow area code and workflow area name are required.');
        }
        if (!preg_match('/^[A-Z0-9_\\-]+$/', $areaCode)) {
            throw new \RuntimeException('Workflow area code can contain only letters, numbers, hyphens, and underscores.');
        }
        if ($activeFlag !== 0 && $activeFlag !== 1) {
            throw new \RuntimeException('ActiveFlag must be 0 or 1.');
        }
        if ($userId === null) {
            throw new \RuntimeException('A valid user context is required.');
        }

        $existing = $originalAreaCode !== '' ? $this->getDefinition($originalAreaCode) : null;
        if ($originalAreaCode !== '' && $existing === null) {
            throw new \RuntimeException('Workflow definition was not found.');
        }
        if ($existing === null) {
            $dup = $this->getDefinition($areaCode);
            if ($dup !== null) {
                throw new \RuntimeException('Workflow area code already exists.');
            }

            $stmt = $this->db->prepare("
                INSERT INTO dbo.tblWorkflowDefinition
                (
                    WorkflowAreaCode,
                    WorkflowAreaName,
                    ModuleCode,
                    RecordTableName,
                    Description,
                    RouteByDataObjectHierarchy,
                    ActiveFlag,
                    CreatedBy,
                    UpdatedBy,
                    CreatedDate,
                    UpdatedDate
                )
                VALUES
                (
                    :areaCode,
                    :areaName,
                    :moduleCode,
                    :recordTableName,
                    :description,
                    :routeByHierarchy,
                    :activeFlag,
                    :userId,
                    :userId,
                    SYSDATETIME(),
                    SYSDATETIME()
                )
            ");
            $stmt->execute([
                ':areaCode' => $areaCode,
                ':areaName' => $areaName,
                ':moduleCode' => $moduleCode,
                ':recordTableName' => $recordTableName,
                ':description' => $description,
                ':routeByHierarchy' => $routeByHierarchy,
                ':activeFlag' => $activeFlag,
                ':userId' => $userId,
            ]);

            return $areaCode;
        }

        if ($originalAreaCode !== $areaCode) {
            $dup = $this->getDefinition($areaCode);
            if ($dup !== null) {
                throw new \RuntimeException('Workflow area code already exists.');
            }
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE dbo.tblWorkflowDefinition
                SET WorkflowAreaCode = :newAreaCode,
                    WorkflowAreaName = :areaName,
                    ModuleCode = :moduleCode,
                    RecordTableName = :recordTableName,
                    Description = :description,
                    RouteByDataObjectHierarchy = :routeByHierarchy,
                    ActiveFlag = :activeFlag,
                    UpdatedBy = :userId,
                    UpdatedDate = SYSDATETIME()
                WHERE WorkflowAreaCode = :originalAreaCode
            ");
            $stmt->execute([
                ':newAreaCode' => $areaCode,
                ':areaName' => $areaName,
                ':moduleCode' => $moduleCode,
                ':recordTableName' => $recordTableName,
                ':description' => $description,
                ':routeByHierarchy' => $routeByHierarchy,
                ':activeFlag' => $activeFlag,
                ':userId' => $userId,
                ':originalAreaCode' => $originalAreaCode,
            ]);
            if ($stmt->rowCount() <= 0) {
                throw new \RuntimeException('Workflow definition was not found.');
            }

            if ($originalAreaCode !== $areaCode) {
                $this->renameDefinitionAreaCode($originalAreaCode, $areaCode);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $areaCode;
    }

    public function archiveDefinition(string $workflowAreaCode, int $updatedBy): void
    {
        $areaCode = strtoupper(trim($workflowAreaCode));
        if ($areaCode === '' || !$this->supportsWorkflowDefinitions()) {
            throw new \RuntimeException('Workflow definition was not found.');
        }
        if ($this->getDefinition($areaCode) === null) {
            throw new \RuntimeException('Workflow definition was not found.');
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblWorkflowDefinition
            SET ActiveFlag = 0,
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE WorkflowAreaCode = :areaCode
        ");
        $stmt->execute([
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
            ':areaCode' => $areaCode,
        ]);
        if ($stmt->rowCount() <= 0) {
            throw new \RuntimeException('Workflow definition was not found.');
        }
    }

    public function listStages(string $workflowAreaCode, bool $activeOnly = false): array
    {
        return $this->engineModel->listWorkflowStages($workflowAreaCode, $activeOnly);
    }

    public function getStageById(int $workflowDefinitionStageId): ?array
    {
        if ($workflowDefinitionStageId <= 0 || !$this->supportsWorkflowDefinitions()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dbo.tblWorkflowDefinitionStage
            WHERE WorkflowDefinitionStageID = :id
        ");
        $stmt->execute([':id' => $workflowDefinitionStageId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveStage(array $payload): int
    {
        if (!$this->supportsWorkflowDefinitions()) {
            throw new \RuntimeException('Workflow engine definition tables are not installed.');
        }

        $id = (int) ($payload['WorkflowDefinitionStageID'] ?? 0);
        $areaCode = strtoupper(trim((string) ($payload['WorkflowAreaCode'] ?? '')));
        $stageCode = strtoupper(trim((string) ($payload['WorkflowStageCode'] ?? '')));
        $stageName = trim((string) ($payload['WorkflowStageName'] ?? ''));
        $stageOrder = max(1, (int) ($payload['StageOrder'] ?? 0));
        $stageType = strtoupper(trim((string) ($payload['StageType'] ?? 'OTHER')));
        $requiredPermissionCodes = $this->normalizePermissionCodes($payload['RequiredPermissionCodes'] ?? null);
        $routeByHierarchy = !empty($payload['RouteByDataObjectHierarchy']) ? 1 : 0;
        $allowReturn = !empty($payload['AllowReturn']) ? 1 : 0;
        $allowReject = !empty($payload['AllowReject']) ? 1 : 0;
        $allowCancel = !empty($payload['AllowCancel']) ? 1 : 0;
        $allowsDelegation = !empty($payload['AllowsDelegation']) ? 1 : 0;
        $requireDifferentActor = !empty($payload['RequireDifferentActorFromPreviousStage']) ? 1 : 0;
        $isDraftStage = !empty($payload['IsDraftStage']) ? 1 : 0;
        $isFinalStage = !empty($payload['IsFinalStage']) ? 1 : 0;
        $activeFlag = array_key_exists('ActiveFlag', $payload) ? (int) $payload['ActiveFlag'] : 1;
        $userId = $this->nullableInt($payload['UpdatedBy'] ?? null);

        if ($areaCode === '' || $stageCode === '' || $stageName === '') {
            throw new \RuntimeException('Workflow area, stage code, and stage name are required.');
        }
        if (!in_array($stageType, $this->stageTypeCodes(), true)) {
            throw new \RuntimeException('Stage type is not valid.');
        }
        if ($isDraftStage === 1 && $isFinalStage === 1) {
            throw new \RuntimeException('A stage cannot be both draft and final.');
        }
        if ($activeFlag !== 0 && $activeFlag !== 1) {
            throw new \RuntimeException('ActiveFlag must be 0 or 1.');
        }
        if ($userId === null) {
            throw new \RuntimeException('A valid user context is required.');
        }

        $definition = $this->getDefinition($areaCode);
        if ($definition === null) {
            throw new \RuntimeException('Workflow definition was not found for the selected area.');
        }

        $existingStage = $id > 0 ? $this->getStageById($id) : null;
        if ($id > 0 && $existingStage === null) {
            throw new \RuntimeException('Workflow stage was not found.');
        }
        if ($existingStage === null) {
            $this->assertStageUniqueness($areaCode, $stageCode, $stageOrder, 0);
            $this->assertSingleDraftStage($areaCode, $isDraftStage, 0);

            $stmt = $this->db->prepare("
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
                    UpdatedBy,
                    CreatedDate,
                    UpdatedDate
                )
                OUTPUT INSERTED.WorkflowDefinitionStageID
                VALUES
                (
                    :definitionId,
                    :areaCode,
                    :stageCode,
                    :stageName,
                    :stageOrder,
                    :stageType,
                    :requiredPermissionCodes,
                    :routeByHierarchy,
                    :allowReturn,
                    :allowReject,
                    :allowCancel,
                    :allowsDelegation,
                    :requireDifferentActor,
                    :isDraftStage,
                    :isFinalStage,
                    :activeFlag,
                    :userId,
                    :userId,
                    SYSDATETIME(),
                    SYSDATETIME()
                )
            ");
            $stmt->execute([
                ':definitionId' => (int) ($definition['WorkflowDefinitionID'] ?? 0),
                ':areaCode' => $areaCode,
                ':stageCode' => $stageCode,
                ':stageName' => $stageName,
                ':stageOrder' => $stageOrder,
                ':stageType' => $stageType,
                ':requiredPermissionCodes' => $requiredPermissionCodes,
                ':routeByHierarchy' => $routeByHierarchy,
                ':allowReturn' => $allowReturn,
                ':allowReject' => $allowReject,
                ':allowCancel' => $allowCancel,
                ':allowsDelegation' => $allowsDelegation,
                ':requireDifferentActor' => $requireDifferentActor,
                ':isDraftStage' => $isDraftStage,
                ':isFinalStage' => $isFinalStage,
                ':activeFlag' => $activeFlag,
                ':userId' => $userId,
            ]);

            return (int) ($stmt->fetchColumn() ?: 0);
        }

        $this->assertStageUniqueness($areaCode, $stageCode, $stageOrder, $id);
        $this->assertSingleDraftStage($areaCode, $isDraftStage, $id);

        $stmt = $this->db->prepare("
            UPDATE dbo.tblWorkflowDefinitionStage
            SET WorkflowDefinitionID = :definitionId,
                WorkflowAreaCode = :areaCode,
                WorkflowStageCode = :stageCode,
                WorkflowStageName = :stageName,
                StageOrder = :stageOrder,
                StageType = :stageType,
                RequiredPermissionCodes = :requiredPermissionCodes,
                RouteByDataObjectHierarchy = :routeByHierarchy,
                AllowReturn = :allowReturn,
                AllowReject = :allowReject,
                AllowCancel = :allowCancel,
                AllowsDelegation = :allowsDelegation,
                RequireDifferentActorFromPreviousStage = :requireDifferentActor,
                IsDraftStage = :isDraftStage,
                IsFinalStage = :isFinalStage,
                ActiveFlag = :activeFlag,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE WorkflowDefinitionStageID = :id
        ");
        $stmt->execute([
            ':definitionId' => (int) ($definition['WorkflowDefinitionID'] ?? 0),
            ':areaCode' => $areaCode,
            ':stageCode' => $stageCode,
            ':stageName' => $stageName,
            ':stageOrder' => $stageOrder,
            ':stageType' => $stageType,
            ':requiredPermissionCodes' => $requiredPermissionCodes,
            ':routeByHierarchy' => $routeByHierarchy,
            ':allowReturn' => $allowReturn,
            ':allowReject' => $allowReject,
            ':allowCancel' => $allowCancel,
            ':allowsDelegation' => $allowsDelegation,
            ':requireDifferentActor' => $requireDifferentActor,
            ':isDraftStage' => $isDraftStage,
            ':isFinalStage' => $isFinalStage,
            ':activeFlag' => $activeFlag,
            ':userId' => $userId,
            ':id' => $id,
        ]);

        return $id;
    }

    public function archiveStage(int $workflowDefinitionStageId, int $updatedBy): void
    {
        if ($workflowDefinitionStageId <= 0 || !$this->supportsWorkflowDefinitions()) {
            throw new \RuntimeException('Workflow stage was not found.');
        }
        if ($this->getStageById($workflowDefinitionStageId) === null) {
            throw new \RuntimeException('Workflow stage was not found.');
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblWorkflowDefinitionStage
            SET ActiveFlag = 0,
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE WorkflowDefinitionStageID = :id
        ");
        $stmt->execute([
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
            ':id' => $workflowDefinitionStageId,
        ]);
        if ($stmt->rowCount() <= 0) {
            throw new \RuntimeException('Workflow stage was not found.');
        }
    }

    public function listActions(string $workflowAreaCode, bool $activeOnly = false): array
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return [];
        }

        $areaCode = strtoupper(trim($workflowAreaCode));
        if ($areaCode === '') {
            return [];
        }

        $where = ['a.WorkflowAreaCode = :areaCode'];
        $params = [':areaCode' => $areaCode];
        if ($activeOnly) {
            $where[] = 'a.ActiveFlag = 1';
        }

        $sql = "
            SELECT
                a.*,
                fs.WorkflowStageName AS FromStageName,
                ts.WorkflowStageName AS ToStageName
            FROM dbo.tblWorkflowDefinitionAction a
            LEFT JOIN dbo.tblWorkflowDefinitionStage fs
                ON fs.WorkflowAreaCode = a.WorkflowAreaCode
               AND fs.WorkflowStageCode = a.FromStageCode
            LEFT JOIN dbo.tblWorkflowDefinitionStage ts
                ON ts.WorkflowAreaCode = a.WorkflowAreaCode
               AND ts.WorkflowStageCode = a.ToStageCode
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.FromStageCode ASC, a.WorkflowActionCode ASC, a.ToStageCode ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getActionById(int $workflowDefinitionActionId): ?array
    {
        if ($workflowDefinitionActionId <= 0 || !$this->supportsWorkflowDefinitions()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dbo.tblWorkflowDefinitionAction
            WHERE WorkflowDefinitionActionID = :id
        ");
        $stmt->execute([':id' => $workflowDefinitionActionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveAction(array $payload): int
    {
        if (!$this->supportsWorkflowDefinitions()) {
            throw new \RuntimeException('Workflow engine definition tables are not installed.');
        }

        $id = (int) ($payload['WorkflowDefinitionActionID'] ?? 0);
        $areaCode = strtoupper(trim((string) ($payload['WorkflowAreaCode'] ?? '')));
        $actionCode = strtoupper(trim((string) ($payload['WorkflowActionCode'] ?? '')));
        $actionName = trim((string) ($payload['WorkflowActionName'] ?? ''));
        $fromStageCode = strtoupper(trim((string) ($payload['FromStageCode'] ?? '')));
        $toStageCode = strtoupper(trim((string) ($payload['ToStageCode'] ?? '')));
        $actionType = strtoupper(trim((string) ($payload['ActionType'] ?? 'OTHER')));
        $requiredPermissionCodes = $this->normalizePermissionCodes($payload['RequiredPermissionCodes'] ?? null);
        $requireNote = !empty($payload['RequireNote']) ? 1 : 0;
        $activeFlag = array_key_exists('ActiveFlag', $payload) ? (int) $payload['ActiveFlag'] : 1;
        $userId = $this->nullableInt($payload['UpdatedBy'] ?? null);

        if ($areaCode === '' || $actionCode === '' || $actionName === '' || $fromStageCode === '' || $toStageCode === '') {
            throw new \RuntimeException('Workflow area, action code, action name, from stage, and to stage are required.');
        }
        if (!in_array($actionType, $this->actionTypeCodes(), true)) {
            throw new \RuntimeException('Action type is not valid.');
        }
        if ($activeFlag !== 0 && $activeFlag !== 1) {
            throw new \RuntimeException('ActiveFlag must be 0 or 1.');
        }
        if ($userId === null) {
            throw new \RuntimeException('A valid user context is required.');
        }

        $definition = $this->getDefinition($areaCode);
        if ($definition === null) {
            throw new \RuntimeException('Workflow definition was not found for the selected area.');
        }
        if ($this->engineModel->getWorkflowStage($areaCode, $fromStageCode) === null) {
            throw new \RuntimeException('The selected From Stage does not exist in this workflow.');
        }
        if ($this->engineModel->getWorkflowStage($areaCode, $toStageCode) === null) {
            throw new \RuntimeException('The selected To Stage does not exist in this workflow.');
        }

        $this->assertActionUniqueness($areaCode, $actionCode, $fromStageCode, $toStageCode, $id);

        $existingAction = $id > 0 ? $this->getActionById($id) : null;
        if ($id > 0 && $existingAction === null) {
            throw new \RuntimeException('Workflow action was not found.');
        }
        if ($existingAction === null) {
            $stmt = $this->db->prepare("
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
                    UpdatedBy,
                    CreatedDate,
                    UpdatedDate
                )
                OUTPUT INSERTED.WorkflowDefinitionActionID
                VALUES
                (
                    :definitionId,
                    :areaCode,
                    :actionCode,
                    :actionName,
                    :fromStageCode,
                    :toStageCode,
                    :actionType,
                    :requiredPermissionCodes,
                    :requireNote,
                    :activeFlag,
                    :userId,
                    :userId,
                    SYSDATETIME(),
                    SYSDATETIME()
                )
            ");
            $stmt->execute([
                ':definitionId' => (int) ($definition['WorkflowDefinitionID'] ?? 0),
                ':areaCode' => $areaCode,
                ':actionCode' => $actionCode,
                ':actionName' => $actionName,
                ':fromStageCode' => $fromStageCode,
                ':toStageCode' => $toStageCode,
                ':actionType' => $actionType,
                ':requiredPermissionCodes' => $requiredPermissionCodes,
                ':requireNote' => $requireNote,
                ':activeFlag' => $activeFlag,
                ':userId' => $userId,
            ]);

            return (int) ($stmt->fetchColumn() ?: 0);
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblWorkflowDefinitionAction
            SET WorkflowDefinitionID = :definitionId,
                WorkflowAreaCode = :areaCode,
                WorkflowActionCode = :actionCode,
                WorkflowActionName = :actionName,
                FromStageCode = :fromStageCode,
                ToStageCode = :toStageCode,
                ActionType = :actionType,
                RequiredPermissionCodes = :requiredPermissionCodes,
                RequireNote = :requireNote,
                ActiveFlag = :activeFlag,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE WorkflowDefinitionActionID = :id
        ");
        $stmt->execute([
            ':definitionId' => (int) ($definition['WorkflowDefinitionID'] ?? 0),
            ':areaCode' => $areaCode,
            ':actionCode' => $actionCode,
            ':actionName' => $actionName,
            ':fromStageCode' => $fromStageCode,
            ':toStageCode' => $toStageCode,
            ':actionType' => $actionType,
            ':requiredPermissionCodes' => $requiredPermissionCodes,
            ':requireNote' => $requireNote,
            ':activeFlag' => $activeFlag,
            ':userId' => $userId,
            ':id' => $id,
        ]);

        return $id;
    }

    public function archiveAction(int $workflowDefinitionActionId, int $updatedBy): void
    {
        if ($workflowDefinitionActionId <= 0 || !$this->supportsWorkflowDefinitions()) {
            throw new \RuntimeException('Workflow action was not found.');
        }
        if ($this->getActionById($workflowDefinitionActionId) === null) {
            throw new \RuntimeException('Workflow action was not found.');
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblWorkflowDefinitionAction
            SET ActiveFlag = 0,
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE WorkflowDefinitionActionID = :id
        ");
        $stmt->execute([
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
            ':id' => $workflowDefinitionActionId,
        ]);
        if ($stmt->rowCount() <= 0) {
            throw new \RuntimeException('Workflow action was not found.');
        }
    }

    public function getDiagnostics(array $filters): array
    {
        $workflowAreaCode = strtoupper(trim((string) ($filters['workflow_area_code'] ?? '')));
        $workflowStageCode = strtoupper(trim((string) ($filters['workflow_stage_code'] ?? '')));
        $fiscalYearId = (int) ($filters['fy'] ?? 0);
        $versionId = (int) ($filters['version_id'] ?? 0);
        $dataObjectCode = trim((string) ($filters['data_object_code'] ?? ''));

        $diagnostics = $this->assignmentModel->diagnoseResolution(
            $workflowAreaCode,
            $workflowStageCode,
            $fiscalYearId,
            $versionId,
            $dataObjectCode
        );

        $diagnostics['Filters'] = [
            'workflow_area_code' => $workflowAreaCode,
            'workflow_stage_code' => $workflowStageCode,
            'fy' => $fiscalYearId,
            'version_id' => $versionId,
            'data_object_code' => $dataObjectCode,
        ];
        $diagnostics['WorkflowAreaOptions'] = $this->assignmentModel->getWorkflowAreaOptions();
        $diagnostics['WorkflowStageOptions'] = $this->assignmentModel->getWorkflowStageOptions();
        $diagnostics['FiscalYears'] = $this->assignmentModel->listFiscalYears();
        $diagnostics['Versions'] = $this->assignmentModel->listVersions($fiscalYearId);
        $diagnostics['DataObjectCodes'] = $this->assignmentModel->listDataObjectCodes($fiscalYearId);

        return $diagnostics;
    }

    public function getInquiry(array $filters, int $selectedWorkflowInstanceId = 0): array
    {
        $normalizedFilters = $this->normalizeInquiryFilters($filters);
        $fiscalYearId = (int) ($normalizedFilters['fy'] !== '' ? $normalizedFilters['fy'] : 0);
        $summary = $this->getInquirySummary($normalizedFilters);
        $rows = $this->listInquiryRows($normalizedFilters, 250);

        $effectiveSelectedWorkflowInstanceId = $selectedWorkflowInstanceId > 0
            ? $selectedWorkflowInstanceId
            : (int) ($rows[0]['WorkflowInstanceID'] ?? 0);
        $selectedInstance = $effectiveSelectedWorkflowInstanceId > 0
            ? $this->getInquiryInstanceDetail($effectiveSelectedWorkflowInstanceId)
            : null;

        return [
            'Filters' => $normalizedFilters,
            'WorkflowAreaOptions' => $this->assignmentModel->getWorkflowAreaOptions(),
            'WorkflowStageOptions' => $this->assignmentModel->getWorkflowStageOptions(),
            'FiscalYears' => $this->assignmentModel->listFiscalYears(),
            'Versions' => $this->assignmentModel->listVersions($fiscalYearId),
            'DataObjectCodes' => $this->assignmentModel->listDataObjectCodes($fiscalYearId),
            'Users' => $this->tableExists('dbo.tblUsers') ? $this->assignmentModel->listUsers() : [],
            'StateBucketOptions' => [
                ['code' => 'OPEN', 'label' => 'Open Only'],
                ['code' => 'APPROVED', 'label' => 'Approved Only'],
                ['code' => 'CANCELLED', 'label' => 'Cancelled Only'],
                ['code' => 'CLOSED', 'label' => 'Closed Only'],
                ['code' => 'ALL', 'label' => 'All'],
            ],
            'Summary' => $summary,
            'Rows' => $rows,
            'RowLimit' => 250,
            'SelectedWorkflowInstanceID' => $effectiveSelectedWorkflowInstanceId,
            'SelectedInstance' => $selectedInstance,
        ];
    }

    public function getStageTypeOptions(): array
    {
        return [
            ['code' => 'START', 'label' => 'Start'],
            ['code' => 'REVIEW', 'label' => 'Review'],
            ['code' => 'APPROVAL', 'label' => 'Approval'],
            ['code' => 'PUBLISH', 'label' => 'Publish'],
            ['code' => 'END', 'label' => 'End'],
            ['code' => 'CANCEL', 'label' => 'Cancel'],
            ['code' => 'OTHER', 'label' => 'Other'],
        ];
    }

    public function getActionTypeOptions(): array
    {
        return [
            ['code' => 'SUBMIT', 'label' => 'Submit'],
            ['code' => 'FORWARD', 'label' => 'Forward'],
            ['code' => 'RETURN', 'label' => 'Return'],
            ['code' => 'APPROVE', 'label' => 'Approve'],
            ['code' => 'REJECT', 'label' => 'Reject'],
            ['code' => 'CANCEL', 'label' => 'Cancel'],
            ['code' => 'REOPEN', 'label' => 'Reopen'],
            ['code' => 'LOCK', 'label' => 'Lock'],
            ['code' => 'PUBLISH', 'label' => 'Publish'],
            ['code' => 'OTHER', 'label' => 'Other'],
        ];
    }

    private function renameDefinitionAreaCode(string $originalAreaCode, string $newAreaCode): void
    {
        foreach ([
            'dbo.tblWorkflowDefinitionStage',
            'dbo.tblWorkflowDefinitionAction',
            'dbo.tblWorkflowInstance',
            'dbo.tblWorkflowInstanceHistory',
            'dbo.tblWorkflowAssignments',
        ] as $tableName) {
            if (!$this->tableExists($tableName)) {
                continue;
            }

            $stmt = $this->db->prepare("
                UPDATE {$tableName}
                SET WorkflowAreaCode = :newAreaCode
                WHERE WorkflowAreaCode = :originalAreaCode
            ");
            $stmt->execute([
                ':newAreaCode' => $newAreaCode,
                ':originalAreaCode' => $originalAreaCode,
            ]);
        }
    }

    private function getInquirySummary(array $filters): array
    {
        if (!$this->supportsWorkflowInstances()) {
            return [
                'TotalCount' => 0,
                'OpenCount' => 0,
                'ApprovedCount' => 0,
                'CancelledCount' => 0,
            ];
        }

        ['where' => $whereSql, 'params' => $params] = $this->buildInquiryWhereClause($filters);

        $sql = "
            SELECT
                COUNT(*) AS TotalCount,
                SUM(CASE WHEN UPPER(ISNULL(i.CurrentStatusCode, '')) NOT IN ('APPROVED', 'CANCELLED') THEN 1 ELSE 0 END) AS OpenCount,
                SUM(CASE WHEN UPPER(ISNULL(i.CurrentStatusCode, '')) = 'APPROVED' THEN 1 ELSE 0 END) AS ApprovedCount,
                SUM(CASE WHEN UPPER(ISNULL(i.CurrentStatusCode, '')) = 'CANCELLED' THEN 1 ELSE 0 END) AS CancelledCount
            FROM dbo.tblWorkflowInstance i
            INNER JOIN dbo.tblWorkflowDefinition d
                ON d.WorkflowDefinitionID = i.WorkflowDefinitionID
            OUTER APPLY (
                SELECT TOP 1
                    h.WorkflowInstanceHistoryID,
                    h.AssignmentUserID
                FROM dbo.tblWorkflowInstanceHistory h
                WHERE h.WorkflowInstanceID = i.WorkflowInstanceID
                ORDER BY h.ActionDate DESC, h.WorkflowInstanceHistoryID DESC
            ) latestHistory
            WHERE {$whereSql}
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'TotalCount' => (int) ($row['TotalCount'] ?? 0),
            'OpenCount' => (int) ($row['OpenCount'] ?? 0),
            'ApprovedCount' => (int) ($row['ApprovedCount'] ?? 0),
            'CancelledCount' => (int) ($row['CancelledCount'] ?? 0),
        ];
    }

    private function listInquiryRows(array $filters, int $limit): array
    {
        if (!$this->supportsWorkflowInstances()) {
            return [];
        }

        ['where' => $whereSql, 'params' => $params] = $this->buildInquiryWhereClause($filters);
        $top = max(1, $limit);

        $sql = "
            SELECT TOP {$top}
                i.WorkflowInstanceID,
                i.WorkflowDefinitionID,
                i.WorkflowAreaCode,
                d.WorkflowAreaName,
                d.ModuleCode,
                i.RecordTableName,
                i.RecordID,
                i.RecordKey,
                i.FiscalYearID,
                fy.YearLabel,
                i.VersionID,
                v.VersionLabel,
                vt.VersionTypeCode,
                i.DataObjectCode,
                i.ScopeDataObjectCode,
                scopeDoc.DataObjectName AS ScopeDataObjectName,
                i.CurrentAssignmentScopeCode,
                assignmentScopeDoc.DataObjectName AS CurrentAssignmentScopeName,
                i.CurrentStageCode,
                stageDef.WorkflowStageName AS CurrentStageName,
                i.CurrentStatusCode,
                i.WorkflowTitle,
                i.WorkflowNote,
                i.SubmittedDate,
                i.ApprovedDate,
                i.CancelledDate,
                i.LastActionCode,
                i.LastActionBy,
                COALESCE(NULLIF(LTRIM(RTRIM(lastActionUser.DisplayName)), ''), lastActionUser.Username) AS LastActionByName,
                i.LastActionDate,
                latestHistory.AssignmentUserID AS CurrentAssignmentUserID,
                COALESCE(NULLIF(LTRIM(RTRIM(currentAssignmentUser.DisplayName)), ''), currentAssignmentUser.Username) AS CurrentAssignmentUserName,
                currentAssignmentUser.Username AS CurrentAssignmentUsername,
                i.CreatedDate,
                i.UpdatedDate,
                i.ActiveFlag
            FROM dbo.tblWorkflowInstance i
            INNER JOIN dbo.tblWorkflowDefinition d
                ON d.WorkflowDefinitionID = i.WorkflowDefinitionID
            LEFT JOIN dbo.tblWorkflowDefinitionStage stageDef
                ON stageDef.WorkflowAreaCode = i.WorkflowAreaCode
               AND stageDef.WorkflowStageCode = i.CurrentStageCode
            LEFT JOIN dbo.tblFiscalYears fy
                ON fy.FiscalYearID = i.FiscalYearID
            LEFT JOIN dbo.tblVersions v
                ON v.FiscalYearID = i.FiscalYearID
               AND v.VersionID = i.VersionID
            LEFT JOIN dbo.tblVersionTypes vt
                ON vt.VersionTypeID = v.VersionTypeID
            LEFT JOIN dbo.tblDataObjectCodes scopeDoc
                ON scopeDoc.FiscalYearID = i.FiscalYearID
               AND scopeDoc.DataObjectCode = i.ScopeDataObjectCode
            LEFT JOIN dbo.tblDataObjectCodes assignmentScopeDoc
                ON assignmentScopeDoc.FiscalYearID = i.FiscalYearID
               AND assignmentScopeDoc.DataObjectCode = i.CurrentAssignmentScopeCode
            LEFT JOIN dbo.tblUsers lastActionUser
                ON lastActionUser.UserID = i.LastActionBy
            OUTER APPLY (
                SELECT TOP 1
                    h.WorkflowInstanceHistoryID,
                    h.AssignmentUserID,
                    h.AssignmentScopeCode,
                    h.ActionDate
                FROM dbo.tblWorkflowInstanceHistory h
                WHERE h.WorkflowInstanceID = i.WorkflowInstanceID
                ORDER BY h.ActionDate DESC, h.WorkflowInstanceHistoryID DESC
            ) latestHistory
            LEFT JOIN dbo.tblUsers currentAssignmentUser
                ON currentAssignmentUser.UserID = latestHistory.AssignmentUserID
            WHERE {$whereSql}
            ORDER BY
                CASE WHEN UPPER(ISNULL(i.CurrentStatusCode, '')) IN ('APPROVED', 'CANCELLED') THEN 1 ELSE 0 END ASC,
                COALESCE(i.LastActionDate, i.UpdatedDate, i.CreatedDate) DESC,
                i.WorkflowInstanceID DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getInquiryInstanceDetail(int $workflowInstanceId): ?array
    {
        if ($workflowInstanceId <= 0 || !$this->supportsWorkflowInstances()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                i.WorkflowInstanceID,
                i.WorkflowDefinitionID,
                i.WorkflowAreaCode,
                d.WorkflowAreaName,
                d.ModuleCode,
                d.RecordTableName AS DefinitionRecordTableName,
                i.RecordTableName,
                i.RecordID,
                i.RecordKey,
                i.FiscalYearID,
                fy.YearLabel,
                i.VersionID,
                v.VersionLabel,
                vt.VersionTypeCode,
                i.DataObjectCode,
                i.ScopeDataObjectCode,
                scopeDoc.DataObjectName AS ScopeDataObjectName,
                i.CurrentAssignmentScopeCode,
                assignmentScopeDoc.DataObjectName AS CurrentAssignmentScopeName,
                i.CurrentStageCode,
                stageDef.WorkflowStageName AS CurrentStageName,
                i.CurrentStatusCode,
                i.WorkflowTitle,
                i.WorkflowNote,
                i.SubmittedBy,
                COALESCE(NULLIF(LTRIM(RTRIM(submittedUser.DisplayName)), ''), submittedUser.Username) AS SubmittedByName,
                i.SubmittedDate,
                i.ApprovedBy,
                COALESCE(NULLIF(LTRIM(RTRIM(approvedUser.DisplayName)), ''), approvedUser.Username) AS ApprovedByName,
                i.ApprovedDate,
                i.CancelledBy,
                COALESCE(NULLIF(LTRIM(RTRIM(cancelledUser.DisplayName)), ''), cancelledUser.Username) AS CancelledByName,
                i.CancelledDate,
                i.LastActionCode,
                i.LastActionBy,
                COALESCE(NULLIF(LTRIM(RTRIM(lastActionUser.DisplayName)), ''), lastActionUser.Username) AS LastActionByName,
                i.LastActionDate,
                latestHistory.AssignmentUserID AS CurrentAssignmentUserID,
                COALESCE(NULLIF(LTRIM(RTRIM(currentAssignmentUser.DisplayName)), ''), currentAssignmentUser.Username) AS CurrentAssignmentUserName,
                currentAssignmentUser.Username AS CurrentAssignmentUsername,
                i.CreatedBy,
                COALESCE(NULLIF(LTRIM(RTRIM(createdUser.DisplayName)), ''), createdUser.Username) AS CreatedByName,
                i.CreatedDate,
                i.UpdatedBy,
                COALESCE(NULLIF(LTRIM(RTRIM(updatedUser.DisplayName)), ''), updatedUser.Username) AS UpdatedByName,
                i.UpdatedDate,
                i.ActiveFlag
            FROM dbo.tblWorkflowInstance i
            INNER JOIN dbo.tblWorkflowDefinition d
                ON d.WorkflowDefinitionID = i.WorkflowDefinitionID
            LEFT JOIN dbo.tblWorkflowDefinitionStage stageDef
                ON stageDef.WorkflowAreaCode = i.WorkflowAreaCode
               AND stageDef.WorkflowStageCode = i.CurrentStageCode
            LEFT JOIN dbo.tblFiscalYears fy
                ON fy.FiscalYearID = i.FiscalYearID
            LEFT JOIN dbo.tblVersions v
                ON v.FiscalYearID = i.FiscalYearID
               AND v.VersionID = i.VersionID
            LEFT JOIN dbo.tblVersionTypes vt
                ON vt.VersionTypeID = v.VersionTypeID
            LEFT JOIN dbo.tblDataObjectCodes scopeDoc
                ON scopeDoc.FiscalYearID = i.FiscalYearID
               AND scopeDoc.DataObjectCode = i.ScopeDataObjectCode
            LEFT JOIN dbo.tblDataObjectCodes assignmentScopeDoc
                ON assignmentScopeDoc.FiscalYearID = i.FiscalYearID
               AND assignmentScopeDoc.DataObjectCode = i.CurrentAssignmentScopeCode
            LEFT JOIN dbo.tblUsers submittedUser
                ON submittedUser.UserID = i.SubmittedBy
            LEFT JOIN dbo.tblUsers approvedUser
                ON approvedUser.UserID = i.ApprovedBy
            LEFT JOIN dbo.tblUsers cancelledUser
                ON cancelledUser.UserID = i.CancelledBy
            LEFT JOIN dbo.tblUsers lastActionUser
                ON lastActionUser.UserID = i.LastActionBy
            LEFT JOIN dbo.tblUsers createdUser
                ON createdUser.UserID = i.CreatedBy
            LEFT JOIN dbo.tblUsers updatedUser
                ON updatedUser.UserID = i.UpdatedBy
            OUTER APPLY (
                SELECT TOP 1
                    h.WorkflowInstanceHistoryID,
                    h.AssignmentUserID
                FROM dbo.tblWorkflowInstanceHistory h
                WHERE h.WorkflowInstanceID = i.WorkflowInstanceID
                ORDER BY h.ActionDate DESC, h.WorkflowInstanceHistoryID DESC
            ) latestHistory
            LEFT JOIN dbo.tblUsers currentAssignmentUser
                ON currentAssignmentUser.UserID = latestHistory.AssignmentUserID
            WHERE i.WorkflowInstanceID = :id
        ");
        $stmt->execute([':id' => $workflowInstanceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $workflowAreaCode = (string) ($row['WorkflowAreaCode'] ?? '');
        $currentStageCode = (string) ($row['CurrentStageCode'] ?? '');
        $fiscalYearId = (int) ($row['FiscalYearID'] ?? 0);
        $versionId = (int) ($row['VersionID'] ?? 0);
        $scopeDataObjectCode = (string) ($row['ScopeDataObjectCode'] ?? '');

        return [
            'Instance' => $row,
            'AllowedActions' => $this->engineModel->listWorkflowActions($workflowAreaCode, $currentStageCode),
            'CurrentAssignments' => $this->supportsWorkflowAssignments()
                ? $this->engineModel->resolveAssignmentsForStage($workflowAreaCode, $currentStageCode, $fiscalYearId, $versionId, $scopeDataObjectCode)
                : [],
            'History' => $this->getInquiryInstanceHistory($workflowInstanceId),
        ];
    }

    private function getInquiryInstanceHistory(int $workflowInstanceId): array
    {
        if ($workflowInstanceId <= 0 || !$this->supportsWorkflowInstances()) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                h.WorkflowInstanceHistoryID,
                h.WorkflowInstanceID,
                h.WorkflowAreaCode,
                h.WorkflowActionCode,
                h.FromStageCode,
                fromStage.WorkflowStageName AS FromStageName,
                h.ToStageCode,
                toStage.WorkflowStageName AS ToStageName,
                h.AssignmentScopeCode,
                assignmentScopeDoc.DataObjectName AS AssignmentScopeName,
                h.AssignmentUserID,
                COALESCE(NULLIF(LTRIM(RTRIM(assignmentUser.DisplayName)), ''), assignmentUser.Username) AS AssignmentUserName,
                assignmentUser.Username AS AssignmentUsername,
                h.ActionNote,
                h.ActionBy,
                COALESCE(NULLIF(LTRIM(RTRIM(actionByUser.DisplayName)), ''), actionByUser.Username) AS ActionByName,
                actionByUser.Username AS ActionByUsername,
                h.ActionDate
            FROM dbo.tblWorkflowInstanceHistory h
            LEFT JOIN dbo.tblWorkflowInstance i
                ON i.WorkflowInstanceID = h.WorkflowInstanceID
            LEFT JOIN dbo.tblWorkflowDefinitionStage fromStage
                ON fromStage.WorkflowAreaCode = h.WorkflowAreaCode
               AND fromStage.WorkflowStageCode = h.FromStageCode
            LEFT JOIN dbo.tblWorkflowDefinitionStage toStage
                ON toStage.WorkflowAreaCode = h.WorkflowAreaCode
               AND toStage.WorkflowStageCode = h.ToStageCode
            LEFT JOIN dbo.tblDataObjectCodes assignmentScopeDoc
                ON assignmentScopeDoc.FiscalYearID = i.FiscalYearID
               AND assignmentScopeDoc.DataObjectCode = h.AssignmentScopeCode
            LEFT JOIN dbo.tblUsers assignmentUser
                ON assignmentUser.UserID = h.AssignmentUserID
            LEFT JOIN dbo.tblUsers actionByUser
                ON actionByUser.UserID = h.ActionBy
            WHERE h.WorkflowInstanceID = :id
            ORDER BY h.ActionDate DESC, h.WorkflowInstanceHistoryID DESC
        ");
        $stmt->execute([':id' => $workflowInstanceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizeInquiryFilters(array $filters): array
    {
        $workflowAreaCode = strtoupper(trim((string) ($filters['workflow_area_code'] ?? '')));
        $currentStageCode = strtoupper(trim((string) ($filters['current_stage_code'] ?? '')));
        $fiscalYearId = max(0, (int) ($filters['fy'] ?? 0));
        $versionId = max(0, (int) ($filters['version_id'] ?? 0));
        $assignedUserId = max(0, (int) ($filters['assigned_user_id'] ?? 0));
        $stateBucket = strtoupper(trim((string) ($filters['state_bucket'] ?? 'OPEN')));
        if (!in_array($stateBucket, ['OPEN', 'APPROVED', 'CANCELLED', 'CLOSED', 'ALL'], true)) {
            $stateBucket = 'OPEN';
        }

        return [
            'workflow_area_code' => $workflowAreaCode,
            'current_stage_code' => $currentStageCode,
            'fy' => $fiscalYearId > 0 ? (string) $fiscalYearId : '',
            'version_id' => $versionId > 0 ? (string) $versionId : '',
            'data_object_code' => trim((string) ($filters['data_object_code'] ?? '')),
            'assigned_user_id' => $assignedUserId > 0 ? (string) $assignedUserId : '',
            'state_bucket' => $stateBucket,
            'q' => trim((string) ($filters['q'] ?? '')),
        ];
    }

    private function buildInquiryWhereClause(array $filters): array
    {
        $where = ['ISNULL(i.ActiveFlag, 1) = 1'];
        $params = [];

        $workflowAreaCode = strtoupper(trim((string) ($filters['workflow_area_code'] ?? '')));
        if ($workflowAreaCode !== '') {
            $where[] = 'i.WorkflowAreaCode = :workflowAreaCode';
            $params[':workflowAreaCode'] = $workflowAreaCode;
        }

        $currentStageCode = strtoupper(trim((string) ($filters['current_stage_code'] ?? '')));
        if ($currentStageCode !== '') {
            $where[] = 'i.CurrentStageCode = :currentStageCode';
            $params[':currentStageCode'] = $currentStageCode;
        }

        $fiscalYearId = (int) ($filters['fy'] ?? 0);
        if ($fiscalYearId > 0) {
            $where[] = 'ISNULL(i.FiscalYearID, 0) = :fiscalYearId';
            $params[':fiscalYearId'] = $fiscalYearId;
        }

        $versionId = (int) ($filters['version_id'] ?? 0);
        if ($versionId > 0) {
            $where[] = 'ISNULL(i.VersionID, 0) = :versionId';
            $params[':versionId'] = $versionId;
        }

        $dataObjectCode = trim((string) ($filters['data_object_code'] ?? ''));
        if ($dataObjectCode !== '') {
            $where[] = "(
                ISNULL(i.DataObjectCode, '') LIKE :dataObjectCode
                OR ISNULL(i.ScopeDataObjectCode, '') LIKE :dataObjectCode
                OR ISNULL(i.CurrentAssignmentScopeCode, '') LIKE :dataObjectCode
            )";
            $params[':dataObjectCode'] = '%' . $dataObjectCode . '%';
        }

        $assignedUserId = (int) ($filters['assigned_user_id'] ?? 0);
        if ($assignedUserId > 0) {
            $where[] = 'ISNULL(latestHistory.AssignmentUserID, 0) = :assignedUserId';
            $params[':assignedUserId'] = $assignedUserId;
        }

        $stateBucket = strtoupper(trim((string) ($filters['state_bucket'] ?? 'OPEN')));
        switch ($stateBucket) {
            case 'APPROVED':
                $where[] = "UPPER(ISNULL(i.CurrentStatusCode, '')) = 'APPROVED'";
                break;
            case 'CANCELLED':
                $where[] = "UPPER(ISNULL(i.CurrentStatusCode, '')) = 'CANCELLED'";
                break;
            case 'CLOSED':
                $where[] = "UPPER(ISNULL(i.CurrentStatusCode, '')) IN ('APPROVED', 'CANCELLED')";
                break;
            case 'ALL':
                break;
            default:
                $where[] = "UPPER(ISNULL(i.CurrentStatusCode, '')) NOT IN ('APPROVED', 'CANCELLED')";
                break;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                ISNULL(i.WorkflowTitle, '') LIKE :search
                OR ISNULL(i.RecordKey, '') LIKE :search
                OR ISNULL(i.RecordTableName, '') LIKE :search
                OR ISNULL(i.WorkflowNote, '') LIKE :search
                OR ISNULL(d.WorkflowAreaName, '') LIKE :search
            )";
            $params[':search'] = '%' . $search . '%';
        }

        return [
            'where' => implode(' AND ', $where),
            'params' => $params,
        ];
    }

    private function assertStageUniqueness(string $areaCode, string $stageCode, int $stageOrder, int $excludeId): void
    {
        $stmt = $this->db->prepare("
            SELECT WorkflowDefinitionStageID
            FROM dbo.tblWorkflowDefinitionStage
            WHERE WorkflowAreaCode = :areaCode
              AND (
                    WorkflowStageCode = :stageCode
                    OR StageOrder = :stageOrder
                  )
              " . ($excludeId > 0 ? 'AND WorkflowDefinitionStageID <> :excludeId' : '') . "
        ");
        $params = [
            ':areaCode' => $areaCode,
            ':stageCode' => $stageCode,
            ':stageOrder' => $stageOrder,
        ];
        if ($excludeId > 0) {
            $params[':excludeId'] = $excludeId;
        }
        $stmt->execute($params);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new \RuntimeException('Stage code or stage order already exists within this workflow.');
        }
    }

    private function assertSingleDraftStage(string $areaCode, int $isDraftStage, int $excludeId): void
    {
        if ($isDraftStage !== 1) {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT WorkflowDefinitionStageID
            FROM dbo.tblWorkflowDefinitionStage
            WHERE WorkflowAreaCode = :areaCode
              AND IsDraftStage = 1
              AND ActiveFlag = 1
              " . ($excludeId > 0 ? 'AND WorkflowDefinitionStageID <> :excludeId' : '') . "
        ");
        $params = [':areaCode' => $areaCode];
        if ($excludeId > 0) {
            $params[':excludeId'] = $excludeId;
        }
        $stmt->execute($params);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new \RuntimeException('Only one active draft stage is allowed per workflow.');
        }
    }

    private function assertActionUniqueness(string $areaCode, string $actionCode, string $fromStageCode, string $toStageCode, int $excludeId): void
    {
        $stmt = $this->db->prepare("
            SELECT WorkflowDefinitionActionID
            FROM dbo.tblWorkflowDefinitionAction
            WHERE WorkflowAreaCode = :areaCode
              AND WorkflowActionCode = :actionCode
              AND FromStageCode = :fromStageCode
              AND ToStageCode = :toStageCode
              " . ($excludeId > 0 ? 'AND WorkflowDefinitionActionID <> :excludeId' : '') . "
        ");
        $params = [
            ':areaCode' => $areaCode,
            ':actionCode' => $actionCode,
            ':fromStageCode' => $fromStageCode,
            ':toStageCode' => $toStageCode,
        ];
        if ($excludeId > 0) {
            $params[':excludeId'] = $excludeId;
        }
        $stmt->execute($params);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            throw new \RuntimeException('This workflow action transition already exists.');
        }
    }

    private function stageTypeCodes(): array
    {
        return array_column($this->getStageTypeOptions(), 'code');
    }

    private function actionTypeCodes(): array
    {
        return array_column($this->getActionTypeOptions(), 'code');
    }

    private function normalizePermissionCodes(mixed $csv): ?string
    {
        $raw = trim((string) ($csv ?? ''));
        if ($raw === '') {
            return null;
        }

        $parts = preg_split('/[\s,]+/', strtoupper($raw)) ?: [];
        $parts = array_values(array_unique(array_filter(array_map('trim', $parts), static fn(string $part): bool => $part !== '')));
        return $parts === [] ? null : implode(',', $parts);
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
        $string = trim((string) ($value ?? ''));
        return $string === '' ? null : $string;
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare("SELECT OBJECT_ID(:tableName, 'U')");
        $stmt->execute([':tableName' => $tableName]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }
}
