<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkflowAssignmentModel
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsWorkflowAssignments(): bool
    {
        $sql = "SELECT OBJECT_ID(N'dbo.tblWorkflowAssignments')";
        return (int) ($this->db->query($sql)->fetchColumn() ?: 0) > 0;
    }

    public function supportsWorkflowDefinitions(): bool
    {
        $sql = "SELECT OBJECT_ID(N'dbo.tblWorkflowDefinition')";
        $definitionsInstalled = (int) ($this->db->query($sql)->fetchColumn() ?: 0) > 0;
        if (!$definitionsInstalled) {
            return false;
        }

        $sql = "SELECT OBJECT_ID(N'dbo.tblWorkflowDefinitionStage')";
        return (int) ($this->db->query($sql)->fetchColumn() ?: 0) > 0;
    }

    public function listRows(array $filters = []): array
    {
        if (!$this->supportsWorkflowAssignments()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (($filters['workflow_area_code'] ?? '') !== '') {
            $where[] = 'a.WorkflowAreaCode = :area';
            $params[':area'] = trim((string) $filters['workflow_area_code']);
        }
        if (($filters['workflow_stage_code'] ?? '') !== '') {
            $where[] = 'a.WorkflowStageCode = :stage';
            $params[':stage'] = trim((string) $filters['workflow_stage_code']);
        }
        if (($filters['fy'] ?? '') !== '') {
            $where[] = 'ISNULL(a.FiscalYearID, 0) = :fy';
            $params[':fy'] = (int) $filters['fy'];
        }
        if (($filters['version_id'] ?? '') !== '') {
            $where[] = 'ISNULL(a.VersionID, 0) = :ver';
            $params[':ver'] = (int) $filters['version_id'];
        }
        if (($filters['data_object_code'] ?? '') !== '') {
            $where[] = 'a.DataObjectCode LIKE :doc';
            $params[':doc'] = '%' . trim((string) $filters['data_object_code']) . '%';
        }
        if (($filters['active'] ?? '') !== '') {
            $where[] = 'a.ActiveFlag = :active';
            $params[':active'] = (int) $filters['active'];
        }

        $sql = "
            SELECT
                a.WorkflowAssignmentID,
                a.WorkflowAreaCode,
                a.WorkflowStageCode,
                a.FiscalYearID,
                a.VersionID,
                a.DataObjectCode,
                a.UserID,
                a.AssignmentMode,
                a.SequenceNo,
                a.IsPrimary,
                a.InheritFromParentScope,
                a.RouteByDataObjectHierarchy,
                a.RoleID,
                a.ActiveFlag,
                a.CreatedDate,
                a.UpdatedDate,
                u.Username,
                LTRIM(RTRIM(u.DisplayName)) AS DisplayName,
                u.Email,
                d.DataObjectName
            FROM dbo.tblWorkflowAssignments a
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = a.UserID
            LEFT JOIN dbo.tblDataObjectCodes d
                ON d.FiscalYearID = a.FiscalYearID
               AND d.DataObjectCode = a.DataObjectCode
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                a.WorkflowAreaCode,
                a.WorkflowStageCode,
                ISNULL(a.FiscalYearID, 0),
                CASE WHEN a.DataObjectCode = N'0' THEN 1 ELSE 0 END,
                a.DataObjectCode,
                a.SequenceNo,
                u.DisplayName,
                u.Username
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getById(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsWorkflowAssignments()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM dbo.tblWorkflowAssignments
            WHERE WorkflowAssignmentID = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $payload): void
    {
        if (!$this->supportsWorkflowAssignments()) {
            throw new \RuntimeException('Workflow assignment table is not installed.');
        }

        $id = (int) ($payload['WorkflowAssignmentID'] ?? 0);
        $area = strtoupper(trim((string) ($payload['WorkflowAreaCode'] ?? '')));
        $stage = strtoupper(trim((string) ($payload['WorkflowStageCode'] ?? '')));
        $dataObjectCode = trim((string) ($payload['DataObjectCode'] ?? ''));
        $userId = (int) ($payload['UserID'] ?? 0);

        if ($area === '' || $stage === '' || $dataObjectCode === '' || $userId <= 0) {
            throw new \RuntimeException('Workflow area, stage, DataScope, and assignee are required.');
        }

        $normalizedDataObjectCode = strtoupper($dataObjectCode === 'GLOBAL' ? '0' : $dataObjectCode);
        $fiscalYearId = $this->nullableInt($payload['FiscalYearID'] ?? null);
        $versionId = $this->nullableInt($payload['VersionID'] ?? null);
        $sequenceNo = max(1, (int) ($payload['SequenceNo'] ?? 1));
        $isPrimary = !empty($payload['IsPrimary']) ? 1 : 0;
        $activeFlag = array_key_exists('ActiveFlag', $payload) ? (int) $payload['ActiveFlag'] : 1;
        $updatedBy = $this->nullableInt($payload['UpdatedBy'] ?? null);

        if ($activeFlag !== 0 && $activeFlag !== 1) {
            throw new \RuntimeException('ActiveFlag must be 0 or 1.');
        }
        if ($updatedBy === null) {
            throw new \RuntimeException('A valid user context is required.');
        }

        $existingRow = $id > 0 ? $this->getById($id) : null;
        if ($id > 0 && $existingRow === null) {
            throw new \RuntimeException('Workflow assignment was not found.');
        }
        if (!$this->workflowAreaExists($area)) {
            throw new \RuntimeException('Selected workflow area was not found.');
        }
        if (!$this->workflowStageExists($area, $stage)) {
            throw new \RuntimeException('Selected workflow stage was not found for the workflow area.');
        }
        if ($fiscalYearId === null && $versionId !== null) {
            throw new \RuntimeException('Fiscal year is required when a version is selected.');
        }
        if ($fiscalYearId !== null && !$this->fiscalYearExists($fiscalYearId)) {
            throw new \RuntimeException('Selected fiscal year was not found.');
        }
        if ($versionId !== null && !$this->versionExists($fiscalYearId, $versionId)) {
            throw new \RuntimeException('Selected version was not found for the fiscal year.');
        }
        if ($normalizedDataObjectCode !== '0') {
            if ($fiscalYearId === null) {
                throw new \RuntimeException('Fiscal year is required when a DataScope code is selected.');
            }
            if (!$this->dataObjectCodeExists($fiscalYearId, $normalizedDataObjectCode)) {
                throw new \RuntimeException('Selected DataScope code was not found for the fiscal year.');
            }
        }
        if (!$this->userIsActive($userId)) {
            throw new \RuntimeException('Selected assignee was not found or is inactive.');
        }

        $requiredPermissions = $this->getRequiredPermissions($area, $stage);
        if ($requiredPermissions !== []) {
            $permissionMap = $this->loadUserPermissionMap();
            $userPermissions = $permissionMap[$userId] ?? [];
            if (count(array_intersect($requiredPermissions, $userPermissions)) === 0) {
                throw new \RuntimeException(
                    'Selected assignee does not currently hold one of the required permissions for this workflow step: '
                    . implode(', ', $requiredPermissions)
                );
            }
        }

        $dupSql = "
            SELECT WorkflowAssignmentID
            FROM dbo.tblWorkflowAssignments
            WHERE WorkflowAreaCode = :area
              AND WorkflowStageCode = :stage
              AND ISNULL(FiscalYearID, 0) = ISNULL(:fy, 0)
              AND ISNULL(VersionID, 0) = ISNULL(:ver, 0)
              AND DataObjectCode = :doc
              AND UserID = :uid
        ";
        if ($id > 0) {
            $dupSql .= " AND WorkflowAssignmentID <> :id";
        }
        $dup = $this->db->prepare($dupSql);
        $dupParams = [
            ':area' => $area,
            ':stage' => $stage,
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':doc' => $normalizedDataObjectCode,
            ':uid' => $userId,
        ];
        if ($id > 0) {
            $dupParams[':id'] = $id;
        }
        $dup->execute($dupParams);
        if ($dup->fetchColumn()) {
            throw new \RuntimeException('This assignee is already configured for the same workflow area, stage, and scope.');
        }
        if ($isPrimary === 1 && $this->hasOtherPrimaryAssignment($area, $stage, $fiscalYearId, $versionId, $normalizedDataObjectCode, $id)) {
            throw new \RuntimeException('Only one active primary assignee is allowed for the same workflow area, stage, and scope.');
        }

        if ($id > 0) {
            $stmt = $this->db->prepare("
                UPDATE dbo.tblWorkflowAssignments
                SET WorkflowAreaCode = :area,
                    WorkflowStageCode = :stage,
                    FiscalYearID = :fy,
                    VersionID = :ver,
                    DataObjectCode = :doc,
                    UserID = :uid,
                    SequenceNo = :seq,
                    IsPrimary = :isPrimary,
                    ActiveFlag = :activeFlag,
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE WorkflowAssignmentID = :id
            ");
            $stmt->execute([
                ':area' => $area,
                ':stage' => $stage,
                ':fy' => $fiscalYearId,
                ':ver' => $versionId,
                ':doc' => $normalizedDataObjectCode,
                ':uid' => $userId,
                ':seq' => $sequenceNo,
                ':isPrimary' => $isPrimary,
                ':activeFlag' => $activeFlag,
                ':updatedBy' => $updatedBy,
                ':id' => $id,
            ]);
            if ($stmt->rowCount() <= 0) {
                throw new \RuntimeException('Workflow assignment was not found.');
            }
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblWorkflowAssignments
                (WorkflowAreaCode, WorkflowStageCode, FiscalYearID, VersionID, DataObjectCode, UserID, SequenceNo, IsPrimary, ActiveFlag, CreatedBy, CreatedDate, UpdatedBy, UpdatedDate)
            VALUES
                (:area, :stage, :fy, :ver, :doc, :uid, :seq, :isPrimary, :activeFlag, :createdBy, SYSDATETIME(), :updatedBy, SYSDATETIME())
        ");
        $stmt->execute([
            ':area' => $area,
            ':stage' => $stage,
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':doc' => $normalizedDataObjectCode,
            ':uid' => $userId,
            ':seq' => $sequenceNo,
            ':isPrimary' => $isPrimary,
            ':activeFlag' => $activeFlag,
            ':createdBy' => $updatedBy,
            ':updatedBy' => $updatedBy,
        ]);
    }

    public function archive(int $id, int $updatedBy): void
    {
        if ($id <= 0 || !$this->supportsWorkflowAssignments()) {
            throw new \RuntimeException('Workflow assignment was not found.');
        }
        if ($this->getById($id) === null) {
            throw new \RuntimeException('Workflow assignment was not found.');
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblWorkflowAssignments
            SET ActiveFlag = 0,
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE WorkflowAssignmentID = :id
        ");
        $stmt->execute([
            ':updatedBy' => $updatedBy > 0 ? $updatedBy : null,
            ':id' => $id,
        ]);
        if ($stmt->rowCount() <= 0) {
            throw new \RuntimeException('Workflow assignment was not found.');
        }
    }

    public function resolveAssignments(string $workflowAreaCode, string $workflowStageCode, int $fiscalYearId, int $versionId, string $dataObjectCode): array
    {
        if (!$this->supportsWorkflowAssignments()) {
            return [];
        }

        $area = strtoupper(trim($workflowAreaCode));
        $stage = strtoupper(trim($workflowStageCode));
        $scopeChain = $this->buildScopeChain($fiscalYearId, $dataObjectCode);

        foreach ($scopeChain as $scopeCode) {
            foreach ($this->buildContextCandidates($fiscalYearId, $versionId) as [$fy, $ver]) {
                $rows = $this->loadAssignmentsForScope($area, $stage, $fy, $ver, $scopeCode, strcasecmp($scopeCode, $dataObjectCode) === 0);
                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        return [];
    }

    public function listFiscalYears(): array
    {
        $stmt = $this->db->query("
            SELECT FiscalYearID, YearLabel
            FROM dbo.tblFiscalYears
            ORDER BY FiscalYearID DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listVersions(int $fiscalYearId = 0): array
    {
        if ($fiscalYearId > 0) {
            $stmt = $this->db->prepare("
                SELECT v.VersionID, v.VersionLabel, vt.VersionTypeCode
                FROM dbo.tblVersions v
                LEFT JOIN dbo.tblVersionTypes vt
                    ON vt.VersionTypeID = v.VersionTypeID
                WHERE FiscalYearID = :fy
                ORDER BY v.VersionLabel ASC, v.VersionID ASC
            ");
            $stmt->execute([':fy' => $fiscalYearId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->db->query("
            SELECT v.VersionID, v.VersionLabel, vt.VersionTypeCode
            FROM dbo.tblVersions v
            LEFT JOIN dbo.tblVersionTypes vt
                ON vt.VersionTypeID = v.VersionTypeID
            ORDER BY v.VersionLabel ASC, v.VersionID ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listDataObjectCodes(int $fiscalYearId = 0): array
    {
        if ($fiscalYearId > 0) {
            $stmt = $this->db->prepare("
                SELECT DataObjectCode, DataObjectName
                FROM dbo.tblDataObjectCodes
                WHERE FiscalYearID = :fy
                ORDER BY DataObjectCode
            ");
            $stmt->execute([':fy' => $fiscalYearId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->db->query("
            SELECT DISTINCT DataObjectCode, DataObjectName
            FROM dbo.tblDataObjectCodes
            ORDER BY DataObjectCode
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listUsers(): array
    {
        $stmt = $this->db->query("
            SELECT UserID, Username, LTRIM(RTRIM(DisplayName)) AS DisplayName, Email
            FROM dbo.tblUsers
            WHERE IsActive = 1
            ORDER BY LTRIM(RTRIM(DisplayName)) ASC, Username ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listAssignableUsers(string $workflowAreaCode = '', string $workflowStageCode = ''): array
    {
        $users = $this->listUsersWithPermissions();
        $requiredPermissions = $this->getRequiredPermissions($workflowAreaCode, $workflowStageCode);
        if ($requiredPermissions === []) {
            foreach ($users as &$user) {
                $user['EligibleFlag'] = 1;
            }
            unset($user);
            return $users;
        }

        $filtered = [];
        foreach ($users as $user) {
            $permissions = $user['PermissionCodes'] ?? [];
            $eligible = count(array_intersect($requiredPermissions, $permissions)) > 0;
            if (!$eligible) {
                continue;
            }
            $user['EligibleFlag'] = 1;
            $filtered[] = $user;
        }

        return $filtered;
    }

    public function listUsersWithPermissions(): array
    {
        $users = $this->listUsers();
        if ($users === []) {
            return [];
        }

        $permissionMap = $this->loadUserPermissionMap();
        foreach ($users as &$user) {
            $userId = (int) ($user['UserID'] ?? 0);
            $permissions = $permissionMap[$userId] ?? [];
            $user['PermissionCodes'] = $permissions;
            $user['PermissionCodesCsv'] = implode(', ', $permissions);
        }
        unset($user);

        return $users;
    }

    public function annotateRowsWithAccessStatus(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $permissionMap = $this->loadUserPermissionMap();
        foreach ($rows as &$row) {
            $requiredPermissions = $this->getRequiredPermissions(
                (string) ($row['WorkflowAreaCode'] ?? ''),
                (string) ($row['WorkflowStageCode'] ?? '')
            );
            $row['RequiredPermissions'] = $requiredPermissions;
            if ($requiredPermissions === []) {
                $row['HasRequiredAccess'] = 1;
                $row['AccessWarning'] = '';
                continue;
            }

            $userId = (int) ($row['UserID'] ?? 0);
            $userPermissions = $permissionMap[$userId] ?? [];
            $hasAccess = count(array_intersect($requiredPermissions, $userPermissions)) > 0;
            $row['HasRequiredAccess'] = $hasAccess ? 1 : 0;
            $row['AccessWarning'] = $hasAccess
                ? ''
                : 'Assigned user no longer has one of the required permissions: ' . implode(', ', $requiredPermissions);
        }
        unset($row);

        return $rows;
    }

    public function getWorkflowAreaOptions(): array
    {
        if ($this->supportsWorkflowDefinitions()) {
            $stmt = $this->db->query("
                SELECT WorkflowAreaCode, WorkflowAreaName
                FROM dbo.tblWorkflowDefinition
                WHERE ActiveFlag = 1
                ORDER BY WorkflowAreaName ASC, WorkflowAreaCode ASC
            ");
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            if ($rows !== []) {
                return array_map(static function (array $row): array {
                    return [
                        'code' => (string) ($row['WorkflowAreaCode'] ?? ''),
                        'label' => (string) ($row['WorkflowAreaName'] ?? ($row['WorkflowAreaCode'] ?? '')),
                    ];
                }, $rows);
            }
        }

        return [
            ['code' => 'FUNDING_REQUEST', 'label' => 'Funding Request'],
            ['code' => 'SEGMENT_PUBLICATION', 'label' => 'Segment Publication'],
            ['code' => 'STRATEGIC_VERSION', 'label' => 'Strategic Version'],
            ['code' => 'BUDGET_SUBMISSION', 'label' => 'Budget Submission'],
            ['code' => 'BUDGET_EXECUTION', 'label' => 'Budget Execution'],
        ];
    }

    public function getWorkflowAccessRules(): array
    {
        if ($this->supportsWorkflowDefinitions()) {
            $stmt = $this->db->query("
                SELECT
                    WorkflowAreaCode,
                    WorkflowStageCode,
                    RequiredPermissionCodes
                FROM dbo.tblWorkflowDefinitionStage
                WHERE ActiveFlag = 1
            ");
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            $rules = [];
            foreach ($rows as $row) {
                $area = strtoupper(trim((string) ($row['WorkflowAreaCode'] ?? '')));
                $stage = strtoupper(trim((string) ($row['WorkflowStageCode'] ?? '')));
                if ($area === '' || $stage === '') {
                    continue;
                }
                $codes = $this->parsePermissionCodes((string) ($row['RequiredPermissionCodes'] ?? ''));
                $rules[$area][$stage] = $codes;
            }
            if ($rules !== []) {
                return $rules;
            }
        }

        return [
            'FUNDING_REQUEST' => [
                'REVIEW' => ['STRATEGY_SUBMISSION_REVIEW', 'STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
                'APPROVAL' => ['STRATEGY_SUBMISSION_APPROVE', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
                'PUBLISH' => ['STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
            ],
            'SEGMENT_PUBLICATION' => [
                'REVIEW' => ['STRATEGY_CONFIG_EDIT', 'STRATEGY_SETUP_EDIT', 'STRATEGY_SEGMENT_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
                'APPROVAL' => ['STRATEGY_SEGMENT_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
                'PUBLISH' => ['STRATEGY_SEGMENT_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
            ],
            'STRATEGIC_VERSION' => [
                'REVIEW' => ['STRATEGY_VIEW', 'STRATEGY_WORKFLOW_EDIT', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
                'APPROVAL' => ['STRATEGY_WORKFLOW_EDIT', 'STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
                'PUBLISH' => ['STRATEGY_PUBLISH', 'ADMIN_ALL', 'SYSADMIN'],
            ],
            'BUDGET_SUBMISSION' => [
                'REVIEW' => ['ESTIMATES_VIEW', 'ESTIMATES_EDIT', 'FIN_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN'],
                'APPROVAL' => ['ESTIMATES_EDIT', 'FIN_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN'],
                'PUBLISH' => ['FIN_CONFIG_EDIT', 'ADMIN_ALL', 'SYSADMIN'],
            ],
            'BUDGET_EXECUTION' => [
                'REVIEW' => ['WORKFLOW_VIEW', 'WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN'],
                'APPROVAL' => ['WORKFLOW_EDIT', 'WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN'],
                'PUBLISH' => ['WORKFLOW_ADMIN', 'ADMIN_ALL', 'SYSADMIN'],
            ],
        ];
    }

    public function getRequiredPermissions(string $workflowAreaCode, string $workflowStageCode): array
    {
        $area = strtoupper(trim($workflowAreaCode));
        $stage = strtoupper(trim($workflowStageCode));
        $rules = $this->getWorkflowAccessRules();
        return $rules[$area][$stage] ?? [];
    }

    public function getWorkflowStageOptions(): array
    {
        if ($this->supportsWorkflowDefinitions()) {
            $stmt = $this->db->query("
                SELECT
                    WorkflowAreaCode,
                    WorkflowStageCode,
                    WorkflowStageName,
                    StageOrder
                FROM dbo.tblWorkflowDefinitionStage
                WHERE ActiveFlag = 1
                ORDER BY WorkflowAreaCode ASC, StageOrder ASC, WorkflowStageCode ASC
            ");
            $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            if ($rows !== []) {
                return array_map(static function (array $row): array {
                    return [
                        'code' => (string) ($row['WorkflowStageCode'] ?? ''),
                        'label' => (string) ($row['WorkflowStageName'] ?? ($row['WorkflowStageCode'] ?? '')),
                        'workflow_area_code' => (string) ($row['WorkflowAreaCode'] ?? ''),
                        'stage_order' => (int) ($row['StageOrder'] ?? 0),
                    ];
                }, $rows);
            }
        }

        return [
            ['code' => 'REVIEW', 'label' => 'Review'],
            ['code' => 'APPROVAL', 'label' => 'Approval'],
            ['code' => 'PUBLISH', 'label' => 'Publish'],
        ];
    }

    public function diagnoseResolution(string $workflowAreaCode, string $workflowStageCode, int $fiscalYearId, int $versionId, string $dataObjectCode): array
    {
        $area = strtoupper(trim($workflowAreaCode));
        $stage = strtoupper(trim($workflowStageCode));
        $scopeCode = trim($dataObjectCode);

        if ($area === '' || $stage === '') {
            return [
                'ScopeChain' => [],
                'Attempts' => [],
                'ResolvedAssignments' => [],
                'ResolvedScopeCode' => '',
                'ResolvedFiscalYearID' => null,
                'ResolvedVersionID' => null,
            ];
        }

        $scopeChain = $this->buildScopeChain($fiscalYearId, $scopeCode);
        $attempts = [];
        $resolvedAssignments = [];
        $resolvedScopeCode = '';
        $resolvedFiscalYearId = null;
        $resolvedVersionId = null;

        foreach ($scopeChain as $candidateScopeCode) {
            foreach ($this->buildContextCandidates($fiscalYearId, $versionId) as [$candidateFiscalYearId, $candidateVersionId]) {
                $rows = $this->loadAssignmentsForScope(
                    $area,
                    $stage,
                    $candidateFiscalYearId,
                    $candidateVersionId,
                    $candidateScopeCode,
                    strcasecmp($candidateScopeCode, $scopeCode) === 0
                );

                $attempts[] = [
                    'ScopeCode' => $candidateScopeCode,
                    'IsExactScope' => strcasecmp($candidateScopeCode, $scopeCode) === 0 ? 1 : 0,
                    'FiscalYearID' => $candidateFiscalYearId,
                    'VersionID' => $candidateVersionId,
                    'Matched' => $rows !== [] ? 1 : 0,
                    'Assignments' => $rows,
                ];

                if ($rows !== [] && $resolvedAssignments === []) {
                    $resolvedAssignments = $rows;
                    $resolvedScopeCode = $candidateScopeCode;
                    $resolvedFiscalYearId = $candidateFiscalYearId;
                    $resolvedVersionId = $candidateVersionId;
                    break 2;
                }
            }
        }

        return [
            'ScopeChain' => $scopeChain,
            'Attempts' => $attempts,
            'ResolvedAssignments' => $resolvedAssignments,
            'ResolvedScopeCode' => $resolvedScopeCode,
            'ResolvedFiscalYearID' => $resolvedFiscalYearId,
            'ResolvedVersionID' => $resolvedVersionId,
        ];
    }

    private function buildScopeChain(int $fiscalYearId, string $dataObjectCode): array
    {
        $scopeCode = trim($dataObjectCode);
        $chain = [];

        if ($scopeCode !== '') {
            $chain[] = $scopeCode;
        }

        if ($fiscalYearId > 0 && $scopeCode !== '') {
            try {
                $stmt = $this->db->prepare("
                    SELECT AncestorCode, Depth
                    FROM dbo.tblDataObjectTree
                    WHERE FiscalYearID = :fy
                      AND DescendantCode = :code
                      AND Depth > 0
                    ORDER BY Depth ASC
                ");
                $stmt->execute([':fy' => $fiscalYearId, ':code' => $scopeCode]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $row) {
                    $ancestorCode = trim((string) ($row['AncestorCode'] ?? ''));
                    if ($ancestorCode !== '' && !in_array($ancestorCode, $chain, true)) {
                        $chain[] = $ancestorCode;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        foreach (['0', 'GLOBAL'] as $globalCode) {
            if (!in_array($globalCode, $chain, true)) {
                $chain[] = $globalCode;
            }
        }

        return $chain;
    }

    private function buildContextCandidates(int $fiscalYearId, int $versionId): array
    {
        $candidates = [];
        if ($fiscalYearId > 0 && $versionId > 0) {
            $candidates[] = [$fiscalYearId, $versionId];
        }
        if ($fiscalYearId > 0) {
            $candidates[] = [$fiscalYearId, null];
        }
        $candidates[] = [null, null];
        return $candidates;
    }

    private function loadAssignmentsForScope(string $area, string $stage, ?int $fiscalYearId, ?int $versionId, string $scopeCode, bool $isExactScope): array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.WorkflowAssignmentID,
                a.WorkflowAreaCode,
                a.WorkflowStageCode,
                a.FiscalYearID,
                a.VersionID,
                a.DataObjectCode,
                a.UserID,
                a.AssignmentMode,
                a.SequenceNo,
                a.IsPrimary,
                a.InheritFromParentScope,
                a.RouteByDataObjectHierarchy,
                a.RoleID,
                LTRIM(RTRIM(u.DisplayName)) AS DisplayName,
                u.Username,
                u.Email
            FROM dbo.tblWorkflowAssignments a
            INNER JOIN dbo.tblUsers u
                ON u.UserID = a.UserID
            WHERE a.ActiveFlag = 1
              AND u.IsActive = 1
              AND a.WorkflowAreaCode = :area
              AND a.WorkflowStageCode = :stage
              AND ISNULL(a.FiscalYearID, 0) = ISNULL(:fy, 0)
              AND ISNULL(a.VersionID, 0) = ISNULL(:ver, 0)
              AND UPPER(a.DataObjectCode) = UPPER(:doc)
              AND (
                    :isExactScope = 1
                    OR (
                        ISNULL(a.InheritFromParentScope, 1) = 1
                        AND ISNULL(a.RouteByDataObjectHierarchy, 1) = 1
                    )
                  )
            ORDER BY a.SequenceNo ASC, a.IsPrimary DESC, LTRIM(RTRIM(u.DisplayName)) ASC, u.Username ASC
        ");
        $stmt->execute([
            ':area' => $area,
            ':stage' => $stage,
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':doc' => $scopeCode === 'GLOBAL' ? '0' : $scopeCode,
            ':isExactScope' => $isExactScope ? 1 : 0,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function loadUserPermissionMap(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT
                ur.UserID,
                UPPER(p.PermissionCode) AS PermissionCode
            FROM dbo.tblUserRoles ur
            INNER JOIN dbo.tblRoles r
                ON r.RoleID = ur.RoleID
            INNER JOIN dbo.tblRolePermissions rp
                ON rp.RoleID = ur.RoleID
            INNER JOIN dbo.tblPermissions p
                ON p.PermissionID = rp.PermissionID
            INNER JOIN dbo.tblUsers u
                ON u.UserID = ur.UserID
            WHERE u.IsActive = 1
              AND (r.Active = 1 OR r.Active IS NULL)
              AND (p.Active = 1 OR p.Active IS NULL)
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['UserID'] ?? 0);
            $permissionCode = strtoupper(trim((string) ($row['PermissionCode'] ?? '')));
            if ($userId <= 0 || $permissionCode === '') {
                continue;
            }
            $map[$userId] ??= [];
            if (!in_array($permissionCode, $map[$userId], true)) {
                $map[$userId][] = $permissionCode;
            }
        }
        return $map;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int) $value;
        return $int > 0 ? $int : null;
    }

    private function workflowAreaExists(string $workflowAreaCode): bool
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dbo.tblWorkflowDefinition
            WHERE WorkflowAreaCode = :area
              AND ActiveFlag = 1
        ");
        $stmt->execute([':area' => $workflowAreaCode]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function workflowStageExists(string $workflowAreaCode, string $workflowStageCode): bool
    {
        if (!$this->supportsWorkflowDefinitions()) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dbo.tblWorkflowDefinitionStage
            WHERE WorkflowAreaCode = :area
              AND WorkflowStageCode = :stage
              AND ActiveFlag = 1
        ");
        $stmt->execute([
            ':area' => $workflowAreaCode,
            ':stage' => $workflowStageCode,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function fiscalYearExists(int $fiscalYearId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dbo.tblFiscalYears
            WHERE FiscalYearID = :fy
              AND ISNULL(IsActive, 1) = 1
        ");
        $stmt->execute([':fy' => $fiscalYearId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function versionExists(?int $fiscalYearId, int $versionId): bool
    {
        if ($fiscalYearId === null) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dbo.tblVersions
            WHERE FiscalYearID = :fy
              AND VersionID = :ver
              AND ISNULL(IsActive, 1) = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function dataObjectCodeExists(int $fiscalYearId, string $dataObjectCode): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dbo.tblDataObjectCodes
            WHERE FiscalYearID = :fy
              AND DataObjectCode = :code
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':code' => $dataObjectCode,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function userIsActive(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dbo.tblUsers
            WHERE UserID = :userId
              AND IsActive = 1
        ");
        $stmt->execute([':userId' => $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function hasOtherPrimaryAssignment(string $area, string $stage, ?int $fiscalYearId, ?int $versionId, string $dataObjectCode, int $excludeId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dbo.tblWorkflowAssignments
            WHERE WorkflowAreaCode = :area
              AND WorkflowStageCode = :stage
              AND ISNULL(FiscalYearID, 0) = ISNULL(:fy, 0)
              AND ISNULL(VersionID, 0) = ISNULL(:ver, 0)
              AND DataObjectCode = :doc
              AND ActiveFlag = 1
              AND ISNULL(IsPrimary, 0) = 1
              " . ($excludeId > 0 ? 'AND WorkflowAssignmentID <> :excludeId' : '') . "
        ");
        $params = [
            ':area' => $area,
            ':stage' => $stage,
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
            ':doc' => $dataObjectCode,
        ];
        if ($excludeId > 0) {
            $params[':excludeId'] = $excludeId;
        }
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function parsePermissionCodes(string $csv): array
    {
        $values = array_map(
            static fn(string $code): string => strtoupper(trim($code)),
            explode(',', $csv)
        );
        return array_values(array_filter(array_unique($values), static fn(string $code): bool => $code !== ''));
    }
}
