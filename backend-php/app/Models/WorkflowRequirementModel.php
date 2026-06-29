<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class WorkflowRequirementModel
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

    public function supportsRequirements(): bool
    {
        return $this->tableExists('dbo.tblWorkflowRequirements');
    }

    public function supportsRequirementAttachments(): bool
    {
        return $this->tableExists('dbo.tblWorkflowRequirementAttachments');
    }

    public function supportsRequirementHistory(): bool
    {
        return $this->tableExists('dbo.tblWorkflowRequirementHistory');
    }

    public function supportsRequirementHierarchy(): bool
    {
        return $this->columnExists('dbo.tblWorkflowRequirements', 'ParentRequirementID')
            && $this->columnExists('dbo.tblWorkflowRequirements', 'RequirementLevelCode');
    }

    /**
     * @return array<string, string>
     */
    public function typeOptions(): array
    {
        return [
            'FUNCTIONAL' => 'workflow_requirement_type_functional',
            'NON_FUNCTIONAL' => 'workflow_requirement_type_non_functional',
            'REPORTING' => 'workflow_requirement_type_reporting',
            'SECURITY' => 'workflow_requirement_type_security',
            'INTEGRATION' => 'workflow_requirement_type_integration',
            'DATA' => 'workflow_requirement_type_data',
            'TRAINING' => 'workflow_requirement_type_training',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function deliveryClassOptions(): array
    {
        return [
            'UPGRADE' => 'workflow_requirement_delivery_upgrade',
            'ENHANCEMENT' => 'workflow_requirement_delivery_enhancement',
            'CONFIGURATION' => 'workflow_requirement_delivery_configuration',
            'INTEGRATION' => 'workflow_requirement_delivery_integration',
            'MIGRATION' => 'workflow_requirement_delivery_migration',
            'TESTING' => 'workflow_requirement_delivery_testing',
            'TRAINING' => 'workflow_requirement_delivery_training',
            'GOVERNANCE' => 'workflow_requirement_delivery_governance',
            'SECURITY' => 'workflow_requirement_delivery_security',
            'SUPPORT' => 'workflow_requirement_delivery_support',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function priorityOptions(): array
    {
        return [
            'MUST' => 'workflow_requirement_priority_must',
            'SHOULD' => 'workflow_requirement_priority_should',
            'COULD' => 'workflow_requirement_priority_could',
            'WONT' => 'workflow_requirement_priority_wont',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return [
            'DRAFT' => 'workflow_requirement_status_draft',
            'REVIEW' => 'workflow_requirement_status_review',
            'APPROVED' => 'workflow_requirement_status_approved',
            'IN_BUILD' => 'workflow_requirement_status_in_build',
            'IN_TEST' => 'workflow_requirement_status_in_test',
            'COMPLETED' => 'workflow_requirement_status_completed',
            'DEFERRED' => 'workflow_requirement_status_deferred',
            'CANCELLED' => 'workflow_requirement_status_cancelled',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function requirementLevelOptions(): array
    {
        return [
            'HIGH_LEVEL' => 'workflow_requirement_level_high_level',
            'DETAILED' => 'workflow_requirement_level_detailed',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRequirements(array $filters = []): array
    {
        if (!$this->supportsRequirements()) {
            return [];
        }

        $hasDeliveryClass = $this->columnExists('dbo.tblWorkflowRequirements', 'DeliveryClassCode');
        $hasSourceDocument = $this->columnExists('dbo.tblWorkflowRequirements', 'SourceDocument');
        $hasSourceSection = $this->columnExists('dbo.tblWorkflowRequirements', 'SourceSection');
        $hasHierarchy = $this->supportsRequirementHierarchy();
        $where = ['1=1'];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $searchParts = [
                'r.RequirementCode LIKE :q',
                'r.RequirementTitle LIKE :q',
                'r.ModuleCode LIKE :q',
                'r.[Description] LIKE :q',
                'r.AcceptanceCriteria LIKE :q',
            ];
            if ($hasDeliveryClass) {
                $searchParts[] = 'r.DeliveryClassCode LIKE :q';
            }
            if ($hasSourceDocument) {
                $searchParts[] = 'r.SourceDocument LIKE :q';
            }
            if ($hasSourceSection) {
                $searchParts[] = 'r.SourceSection LIKE :q';
            }
            if ($hasHierarchy) {
                $searchParts[] = 'parentReq.RequirementCode LIKE :q';
                $searchParts[] = 'parentReq.RequirementTitle LIKE :q';
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
            $params[':q'] = '%' . $q . '%';
        }

        $workflowProjectID = (int)($filters['workflowProjectID'] ?? 0);
        if ($workflowProjectID > 0) {
            $where[] = 'r.WorkflowProjectID = :workflowProjectID';
            $params[':workflowProjectID'] = $workflowProjectID;
        }

        $deliveryClass = $this->normalizeDeliveryClassCode($filters['deliveryClass'] ?? '', false);
        if ($hasDeliveryClass && $deliveryClass !== '') {
            $where[] = 'r.DeliveryClassCode = :deliveryClass';
            $params[':deliveryClass'] = $deliveryClass;
        }

        $status = $this->normalizeStatusCode($filters['status'] ?? '', false);
        if ($status !== '') {
            $where[] = 'r.RequirementStatusCode = :status';
            $params[':status'] = $status;
        }

        $type = $this->normalizeTypeCode($filters['type'] ?? '', false);
        if ($type !== '') {
            $where[] = 'r.RequirementTypeCode = :type';
            $params[':type'] = $type;
        }

        $priority = $this->normalizePriorityCode($filters['priority'] ?? '', false);
        if ($priority !== '') {
            $where[] = 'r.PriorityCode = :priority';
            $params[':priority'] = $priority;
        }

        $requirementLevel = $this->normalizeRequirementLevelCode($filters['requirementLevel'] ?? '', false);
        if ($hasHierarchy && $requirementLevel !== '') {
            $where[] = 'r.RequirementLevelCode = :requirementLevel';
            $params[':requirementLevel'] = $requirementLevel;
        }

        $parentRequirementID = (int)($filters['parentRequirementID'] ?? 0);
        if ($hasHierarchy && $parentRequirementID > 0) {
            $where[] = 'r.ParentRequirementID = :parentRequirementID';
            $params[':parentRequirementID'] = $parentRequirementID;
        }

        $active = trim((string)($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 'r.Active = :active';
            $params[':active'] = (int)$active;
        }

        $parentRequirementSelect = $hasHierarchy ? 'r.ParentRequirementID' : 'CAST(NULL AS INT) AS ParentRequirementID';
        $requirementLevelSelect = $hasHierarchy ? 'r.RequirementLevelCode' : "CAST(N'HIGH_LEVEL' AS NVARCHAR(30)) AS RequirementLevelCode";
        $deliveryClassSelect = $hasDeliveryClass ? 'r.DeliveryClassCode' : "CAST(NULL AS NVARCHAR(30)) AS DeliveryClassCode";
        $sourceDocumentSelect = $hasSourceDocument ? 'r.SourceDocument' : "CAST(NULL AS NVARCHAR(255)) AS SourceDocument";
        $sourceSectionSelect = $hasSourceSection ? 'r.SourceSection' : "CAST(NULL AS NVARCHAR(255)) AS SourceSection";
        $parentRequirementInfoSelect = $hasHierarchy
            ? "parentReq.RequirementCode AS ParentRequirementCode,\n                    parentReq.RequirementTitle AS ParentRequirementTitle"
            : "CAST(NULL AS NVARCHAR(50)) AS ParentRequirementCode,\n                    CAST(NULL AS NVARCHAR(255)) AS ParentRequirementTitle";
        $parentRequirementJoin = $hasHierarchy ? "
                LEFT JOIN dbo.tblWorkflowRequirements parentReq
                    ON parentReq.WorkflowRequirementID = r.ParentRequirementID
        " : '';
        $childRequirementCountSelect = $hasHierarchy
            ? 'COALESCE(childCounts.ChildRequirementCount, 0) AS ChildRequirementCount'
            : 'CAST(0 AS INT) AS ChildRequirementCount';
        $childRequirementCountJoin = $hasHierarchy ? "
                LEFT JOIN (
                    SELECT ParentRequirementID, COUNT(1) AS ChildRequirementCount
                    FROM dbo.tblWorkflowRequirements
                    WHERE ParentRequirementID IS NOT NULL
                      AND Active = 1
                    GROUP BY ParentRequirementID
                ) childCounts
                    ON childCounts.ParentRequirementID = r.WorkflowRequirementID
        " : '';
        $hierarchyOrderSql = $hasHierarchy ? "
                    COALESCE(r.ParentRequirementID, r.WorkflowRequirementID) ASC,
                    CASE r.RequirementLevelCode WHEN N'HIGH_LEVEL' THEN 0 ELSE 1 END," : "";

        try {
            $stmt = $this->conn->prepare("
                SELECT
                    r.WorkflowRequirementID,
                    r.RequirementCode,
                    r.WorkflowProjectID,
                    {$parentRequirementSelect},
                    {$requirementLevelSelect},
                    r.ModuleCode,
                    r.RequirementTitle,
                    {$deliveryClassSelect},
                    r.RequirementTypeCode,
                    r.PriorityCode,
                    r.RequirementStatusCode,
                    {$sourceDocumentSelect},
                    {$sourceSectionSelect},
                    r.[Description],
                    r.AcceptanceCriteria,
                    r.RequestedByUserID,
                    r.OwnerUserID,
                    r.ApprovedByUserID,
                    r.ApprovedAt,
                    r.Active,
                    r.CreatedAt,
                    r.CreatedBy,
                    r.UpdatedAt,
                    r.UpdatedBy,
                    p.ProjectCode,
                    p.ProjectName,
                    {$parentRequirementInfoSelect},
                    {$childRequirementCountSelect},
                    COALESCE(NULLIF(LTRIM(RTRIM(owner.DisplayName)), N''), NULLIF(LTRIM(RTRIM(owner.Username)), N''), CASE WHEN r.OwnerUserID IS NOT NULL THEN CONCAT(N'User #', r.OwnerUserID) ELSE NULL END) AS OwnerName,
                    COALESCE(NULLIF(LTRIM(RTRIM(requestedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(requestedBy.Username)), N''), CASE WHEN r.RequestedByUserID IS NOT NULL THEN CONCAT(N'User #', r.RequestedByUserID) ELSE NULL END) AS RequestedByName
                FROM dbo.tblWorkflowRequirements r
                LEFT JOIN dbo.tblWorkflowProjects p
                    ON p.WorkflowProjectID = r.WorkflowProjectID
                {$parentRequirementJoin}
                {$childRequirementCountJoin}
                LEFT JOIN dbo.tblUsers owner
                    ON owner.UserID = r.OwnerUserID
                LEFT JOIN dbo.tblUsers requestedBy
                    ON requestedBy.UserID = r.RequestedByUserID
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    {$hierarchyOrderSql}
                    CASE r.PriorityCode WHEN N'MUST' THEN 1 WHEN N'SHOULD' THEN 2 WHEN N'COULD' THEN 3 ELSE 4 END,
                    r.Active DESC,
                    " . ($hasDeliveryClass ? "r.DeliveryClassCode ASC," : "") . "
                    r.RequirementStatusCode ASC,
                    r.RequirementCode ASC,
                    r.WorkflowRequirementID DESC
            ");
            $stmt->execute($params);
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
    public function listProjectRequirements(int $workflowProjectID): array
    {
        if ($workflowProjectID <= 0) {
            return [];
        }
        return $this->listRequirements([
            'workflowProjectID' => $workflowProjectID,
            'active' => '1',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listHighLevelRequirements(int $workflowProjectID = 0, int $excludeRequirementID = 0): array
    {
        if (!$this->supportsRequirements()) {
            return [];
        }

        $filters = [
            'requirementLevel' => 'HIGH_LEVEL',
            'active' => '1',
        ];
        if ($workflowProjectID > 0) {
            $filters['workflowProjectID'] = $workflowProjectID;
        }

        $rows = $this->supportsRequirementHierarchy()
            ? $this->listRequirements($filters)
            : $this->listRequirements(['workflowProjectID' => $workflowProjectID, 'active' => '1']);

        if ($excludeRequirementID > 0) {
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (int)($row['WorkflowRequirementID'] ?? 0) !== $excludeRequirementID));
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listChildRequirements(int $parentRequirementID): array
    {
        if ($parentRequirementID <= 0 || !$this->supportsRequirementHierarchy()) {
            return [];
        }

        return $this->listRequirements([
            'parentRequirementID' => $parentRequirementID,
            'active' => '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeRequirements(array $filters = []): array
    {
        $filters['active'] = $filters['active'] ?? '';
        $rows = $this->listRequirements($filters);

        $summary = [
            'total' => count($rows),
            'active' => 0,
            'inactive' => 0,
            'missingOwner' => 0,
            'missingAcceptanceCriteria' => 0,
            'highLevel' => 0,
            'detailed' => 0,
            'byStatus' => [],
            'byPriority' => [],
            'byType' => [],
            'byDeliveryClass' => [],
            'byLevel' => [],
            'byProject' => [],
            'parentCoverage' => [],
            'parentCoverageSummary' => [
                'totalParents' => 0,
                'parentsWithGaps' => 0,
                'parentsWithoutDetails' => 0,
                'detailRowsCovered' => 0,
                'missingDetailAcceptance' => 0,
                'missingDetailOwner' => 0,
            ],
            'recent' => [],
        ];
        $parentCoverage = [];
        $approvedProgressStatuses = [
            'APPROVED' => true,
            'IN_BUILD' => true,
            'IN_TEST' => true,
            'COMPLETED' => true,
        ];
        $ensureParentCoverage = static function (int $parentRequirementID, array $row, bool $inferred) use (&$parentCoverage): void {
            if ($parentRequirementID <= 0) {
                return;
            }

            if (!isset($parentCoverage[$parentRequirementID])) {
                $parentCoverage[$parentRequirementID] = [
                    'WorkflowRequirementID' => $parentRequirementID,
                    'RequirementCode' => '',
                    'RequirementTitle' => '',
                    'WorkflowProjectID' => 0,
                    'ProjectName' => '',
                    'RequirementStatusCode' => '',
                    'PriorityCode' => '',
                    'RequirementTypeCode' => '',
                    'DeliveryClassCode' => '',
                    'OwnerUserID' => null,
                    'OwnerName' => '',
                    'ChildRequirementCount' => 0,
                    'DetailedCount' => 0,
                    'DetailedApprovedCount' => 0,
                    'DetailedMissingOwnerCount' => 0,
                    'DetailedMissingAcceptanceCount' => 0,
                    'ParentMissingOwner' => 0,
                    'ParentMissingAcceptance' => 0,
                    'AttentionCount' => 0,
                    'CoveragePercent' => 0,
                    'ByStatus' => [],
                    'IsInferredParent' => $inferred ? 1 : 0,
                ];
            }

            if ($inferred && empty($parentCoverage[$parentRequirementID]['IsInferredParent'])) {
                return;
            }

            $parentCoverage[$parentRequirementID]['RequirementCode'] = trim((string)($inferred ? ($row['ParentRequirementCode'] ?? '') : ($row['RequirementCode'] ?? '')));
            $parentCoverage[$parentRequirementID]['RequirementTitle'] = trim((string)($inferred ? ($row['ParentRequirementTitle'] ?? '') : ($row['RequirementTitle'] ?? '')));
            $parentCoverage[$parentRequirementID]['WorkflowProjectID'] = (int)($row['WorkflowProjectID'] ?? 0);
            $parentCoverage[$parentRequirementID]['ProjectName'] = trim((string)($row['ProjectName'] ?? ''));

            if (!$inferred) {
                $parentCoverage[$parentRequirementID]['RequirementStatusCode'] = strtoupper(trim((string)($row['RequirementStatusCode'] ?? '')));
                $parentCoverage[$parentRequirementID]['PriorityCode'] = strtoupper(trim((string)($row['PriorityCode'] ?? '')));
                $parentCoverage[$parentRequirementID]['RequirementTypeCode'] = strtoupper(trim((string)($row['RequirementTypeCode'] ?? '')));
                $parentCoverage[$parentRequirementID]['DeliveryClassCode'] = strtoupper(trim((string)($row['DeliveryClassCode'] ?? '')));
                $parentCoverage[$parentRequirementID]['OwnerUserID'] = !empty($row['OwnerUserID']) ? (int)$row['OwnerUserID'] : null;
                $parentCoverage[$parentRequirementID]['OwnerName'] = trim((string)($row['OwnerName'] ?? ''));
                $parentCoverage[$parentRequirementID]['ChildRequirementCount'] = (int)($row['ChildRequirementCount'] ?? 0);
                $parentCoverage[$parentRequirementID]['ParentMissingOwner'] = empty($row['OwnerUserID']) ? 1 : 0;
                $parentCoverage[$parentRequirementID]['ParentMissingAcceptance'] = trim((string)($row['AcceptanceCriteria'] ?? '')) === '' ? 1 : 0;
                $parentCoverage[$parentRequirementID]['IsInferredParent'] = 0;
            }
        };

        foreach ($rows as $row) {
            if ((int)($row['Active'] ?? 0) === 1) {
                $summary['active']++;
            } else {
                $summary['inactive']++;
            }

            if (empty($row['OwnerUserID'])) {
                $summary['missingOwner']++;
            }
            if (trim((string)($row['AcceptanceCriteria'] ?? '')) === '') {
                $summary['missingAcceptanceCriteria']++;
            }

            $status = strtoupper(trim((string)($row['RequirementStatusCode'] ?? 'DRAFT'))) ?: 'DRAFT';
            $priority = strtoupper(trim((string)($row['PriorityCode'] ?? 'SHOULD'))) ?: 'SHOULD';
            $type = strtoupper(trim((string)($row['RequirementTypeCode'] ?? 'FUNCTIONAL'))) ?: 'FUNCTIONAL';
            $deliveryClass = strtoupper(trim((string)($row['DeliveryClassCode'] ?? ''))) ?: 'UNCLASSIFIED';
            $level = $this->normalizeRequirementLevelCode($row['RequirementLevelCode'] ?? 'HIGH_LEVEL', true);
            if ($level === 'DETAILED') {
                $summary['detailed']++;
                $parentRequirementID = (int)($row['ParentRequirementID'] ?? 0);
                if ($parentRequirementID > 0) {
                    $ensureParentCoverage($parentRequirementID, $row, true);
                    $summaryStatus = strtoupper(trim((string)($row['RequirementStatusCode'] ?? 'DRAFT'))) ?: 'DRAFT';
                    $parentCoverage[$parentRequirementID]['DetailedCount']++;
                    $parentCoverage[$parentRequirementID]['ByStatus'][$summaryStatus] = ($parentCoverage[$parentRequirementID]['ByStatus'][$summaryStatus] ?? 0) + 1;
                    if (empty($row['OwnerUserID'])) {
                        $parentCoverage[$parentRequirementID]['DetailedMissingOwnerCount']++;
                    }
                    if (trim((string)($row['AcceptanceCriteria'] ?? '')) === '') {
                        $parentCoverage[$parentRequirementID]['DetailedMissingAcceptanceCount']++;
                    }
                    if (isset($approvedProgressStatuses[$summaryStatus])) {
                        $parentCoverage[$parentRequirementID]['DetailedApprovedCount']++;
                    }
                }
            } else {
                $summary['highLevel']++;
                $parentRequirementID = (int)($row['WorkflowRequirementID'] ?? 0);
                if ($parentRequirementID > 0) {
                    $ensureParentCoverage($parentRequirementID, $row, false);
                }
            }
            $project = trim((string)($row['ProjectName'] ?? ''));
            if ($project === '') {
                $project = 'workflow_project_no_project';
            }

            $summary['byStatus'][$status] = ($summary['byStatus'][$status] ?? 0) + 1;
            $summary['byPriority'][$priority] = ($summary['byPriority'][$priority] ?? 0) + 1;
            $summary['byType'][$type] = ($summary['byType'][$type] ?? 0) + 1;
            $summary['byDeliveryClass'][$deliveryClass] = ($summary['byDeliveryClass'][$deliveryClass] ?? 0) + 1;
            $summary['byLevel'][$level] = ($summary['byLevel'][$level] ?? 0) + 1;
            $summary['byProject'][$project] = ($summary['byProject'][$project] ?? 0) + 1;
        }

        foreach ($parentCoverage as &$coverageRow) {
            $detailCount = (int)($coverageRow['DetailedCount'] ?? 0);
            $missingDetailAcceptance = (int)($coverageRow['DetailedMissingAcceptanceCount'] ?? 0);
            $missingDetailOwner = (int)($coverageRow['DetailedMissingOwnerCount'] ?? 0);
            $parentMissingAcceptance = (int)($coverageRow['ParentMissingAcceptance'] ?? 0);
            $parentMissingOwner = (int)($coverageRow['ParentMissingOwner'] ?? 0);
            $coverageRow['AttentionCount'] = ($detailCount <= 0 ? 1 : 0)
                + $missingDetailAcceptance
                + $missingDetailOwner
                + $parentMissingAcceptance
                + $parentMissingOwner;
            $coverageRow['CoveragePercent'] = $detailCount > 0
                ? (int)round(((int)($coverageRow['DetailedApprovedCount'] ?? 0) / $detailCount) * 100)
                : 0;
        }
        unset($coverageRow);

        $priorityRank = ['MUST' => 1, 'SHOULD' => 2, 'COULD' => 3, 'WONT' => 4];
        $parentCoverageRows = array_values($parentCoverage);
        usort($parentCoverageRows, static function (array $a, array $b) use ($priorityRank): int {
            $aAttention = (int)($a['AttentionCount'] ?? 0);
            $bAttention = (int)($b['AttentionCount'] ?? 0);
            if ($aAttention !== $bAttention) {
                return $bAttention <=> $aAttention;
            }

            $aNoDetails = (int)($a['DetailedCount'] ?? 0) <= 0 ? 1 : 0;
            $bNoDetails = (int)($b['DetailedCount'] ?? 0) <= 0 ? 1 : 0;
            if ($aNoDetails !== $bNoDetails) {
                return $bNoDetails <=> $aNoDetails;
            }

            $aPriority = $priorityRank[(string)($a['PriorityCode'] ?? '')] ?? 99;
            $bPriority = $priorityRank[(string)($b['PriorityCode'] ?? '')] ?? 99;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }

            $aLabel = trim((string)($a['RequirementCode'] ?? ''));
            if ($aLabel === '') {
                $aLabel = trim((string)($a['RequirementTitle'] ?? ''));
            }
            $bLabel = trim((string)($b['RequirementCode'] ?? ''));
            if ($bLabel === '') {
                $bLabel = trim((string)($b['RequirementTitle'] ?? ''));
            }

            return strnatcasecmp($aLabel, $bLabel);
        });

        $parentCoverageSummary = [
            'totalParents' => count($parentCoverageRows),
            'parentsWithGaps' => 0,
            'parentsWithoutDetails' => 0,
            'detailRowsCovered' => 0,
            'missingDetailAcceptance' => 0,
            'missingDetailOwner' => 0,
        ];
        foreach ($parentCoverageRows as $coverageRow) {
            $detailCount = (int)($coverageRow['DetailedCount'] ?? 0);
            $parentCoverageSummary['detailRowsCovered'] += $detailCount;
            $parentCoverageSummary['missingDetailAcceptance'] += (int)($coverageRow['DetailedMissingAcceptanceCount'] ?? 0);
            $parentCoverageSummary['missingDetailOwner'] += (int)($coverageRow['DetailedMissingOwnerCount'] ?? 0);
            if ((int)($coverageRow['AttentionCount'] ?? 0) > 0) {
                $parentCoverageSummary['parentsWithGaps']++;
            }
            if ($detailCount <= 0) {
                $parentCoverageSummary['parentsWithoutDetails']++;
            }
        }
        $summary['parentCoverage'] = $parentCoverageRows;
        $summary['parentCoverageSummary'] = $parentCoverageSummary;

        usort($rows, static function (array $a, array $b): int {
            $left = strtotime((string)($a['UpdatedAt'] ?? $a['CreatedAt'] ?? '')) ?: 0;
            $right = strtotime((string)($b['UpdatedAt'] ?? $b['CreatedAt'] ?? '')) ?: 0;
            return $right <=> $left;
        });
        $summary['recent'] = array_slice($rows, 0, 8);

        arsort($summary['byStatus']);
        arsort($summary['byPriority']);
        arsort($summary['byType']);
        arsort($summary['byDeliveryClass']);
        arsort($summary['byLevel']);
        arsort($summary['byProject']);

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTraceabilityMatrix(array $filters = []): array
    {
        if (!$this->supportsRequirements()) {
            return [];
        }

        $hasDeliveryClass = $this->columnExists('dbo.tblWorkflowRequirements', 'DeliveryClassCode');
        $hasSourceDocument = $this->columnExists('dbo.tblWorkflowRequirements', 'SourceDocument');
        $hasSourceSection = $this->columnExists('dbo.tblWorkflowRequirements', 'SourceSection');
        $hasHierarchy = $this->supportsRequirementHierarchy();
        $hasLinks = $this->tableExists('dbo.tblWorkflowEntityLinks');
        $hasTasks = $this->tableExists('dbo.tblWorkflowTasks');
        $hasAttachments = $this->supportsRequirementAttachments();

        if (!$hasLinks || !$hasTasks) {
            return $this->applyTraceabilityCoverageFilter(
                $this->hydrateTraceabilityRows($this->listRequirements($filters)),
                (string)($filters['coverage'] ?? '')
            );
        }

        $where = ['1=1'];
        $params = [];

        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $searchParts = [
                'r.RequirementCode LIKE :q',
                'r.RequirementTitle LIKE :q',
                'r.ModuleCode LIKE :q',
                'r.[Description] LIKE :q',
                'r.AcceptanceCriteria LIKE :q',
                'p.ProjectCode LIKE :q',
                'p.ProjectName LIKE :q',
            ];
            if ($hasDeliveryClass) {
                $searchParts[] = 'r.DeliveryClassCode LIKE :q';
            }
            if ($hasSourceDocument) {
                $searchParts[] = 'r.SourceDocument LIKE :q';
            }
            if ($hasSourceSection) {
                $searchParts[] = 'r.SourceSection LIKE :q';
            }
            if ($hasHierarchy) {
                $searchParts[] = 'parentReq.RequirementCode LIKE :q';
                $searchParts[] = 'parentReq.RequirementTitle LIKE :q';
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
            $params[':q'] = '%' . $q . '%';
        }

        $workflowProjectID = (int)($filters['workflowProjectID'] ?? 0);
        if ($workflowProjectID > 0) {
            $where[] = 'r.WorkflowProjectID = :workflowProjectID';
            $params[':workflowProjectID'] = $workflowProjectID;
        }

        $deliveryClass = $this->normalizeDeliveryClassCode($filters['deliveryClass'] ?? '', false);
        if ($hasDeliveryClass && $deliveryClass !== '') {
            $where[] = 'r.DeliveryClassCode = :deliveryClass';
            $params[':deliveryClass'] = $deliveryClass;
        }

        $status = $this->normalizeStatusCode($filters['status'] ?? '', false);
        if ($status !== '') {
            $where[] = 'r.RequirementStatusCode = :status';
            $params[':status'] = $status;
        }

        $type = $this->normalizeTypeCode($filters['type'] ?? '', false);
        if ($type !== '') {
            $where[] = 'r.RequirementTypeCode = :type';
            $params[':type'] = $type;
        }

        $priority = $this->normalizePriorityCode($filters['priority'] ?? '', false);
        if ($priority !== '') {
            $where[] = 'r.PriorityCode = :priority';
            $params[':priority'] = $priority;
        }

        $requirementLevel = $this->normalizeRequirementLevelCode($filters['requirementLevel'] ?? '', false);
        if ($hasHierarchy && $requirementLevel !== '') {
            $where[] = 'r.RequirementLevelCode = :requirementLevel';
            $params[':requirementLevel'] = $requirementLevel;
        }

        $parentRequirementID = (int)($filters['parentRequirementID'] ?? 0);
        if ($hasHierarchy && $parentRequirementID > 0) {
            $where[] = 'r.ParentRequirementID = :parentRequirementID';
            $params[':parentRequirementID'] = $parentRequirementID;
        }

        $active = trim((string)($filters['active'] ?? '1'));
        if ($active === '1' || $active === '0') {
            $where[] = 'r.Active = :active';
            $params[':active'] = (int)$active;
        }

        $parentRequirementSelect = $hasHierarchy ? 'r.ParentRequirementID' : 'CAST(NULL AS INT) AS ParentRequirementID';
        $requirementLevelSelect = $hasHierarchy ? 'r.RequirementLevelCode' : "CAST(N'HIGH_LEVEL' AS NVARCHAR(30)) AS RequirementLevelCode";
        $deliveryClassSelect = $hasDeliveryClass ? 'r.DeliveryClassCode' : "CAST(NULL AS NVARCHAR(30)) AS DeliveryClassCode";
        $sourceDocumentSelect = $hasSourceDocument ? 'r.SourceDocument' : "CAST(NULL AS NVARCHAR(255)) AS SourceDocument";
        $sourceSectionSelect = $hasSourceSection ? 'r.SourceSection' : "CAST(NULL AS NVARCHAR(255)) AS SourceSection";
        $parentRequirementInfoSelect = $hasHierarchy
            ? "parentReq.RequirementCode AS ParentRequirementCode,\n                    parentReq.RequirementTitle AS ParentRequirementTitle"
            : "CAST(NULL AS NVARCHAR(50)) AS ParentRequirementCode,\n                    CAST(NULL AS NVARCHAR(255)) AS ParentRequirementTitle";
        $parentRequirementJoin = $hasHierarchy ? "
                LEFT JOIN dbo.tblWorkflowRequirements parentReq
                    ON parentReq.WorkflowRequirementID = r.ParentRequirementID
        " : '';
        $hierarchyOrderSql = $hasHierarchy ? "
                    COALESCE(r.ParentRequirementID, r.WorkflowRequirementID) ASC,
                    CASE r.RequirementLevelCode WHEN N'HIGH_LEVEL' THEN 0 ELSE 1 END," : "";
        $attachmentCte = $hasAttachments ? ",
                attachment_counts AS (
                    SELECT WorkflowRequirementID, COUNT(1) AS AttachmentCount
                    FROM dbo.tblWorkflowRequirementAttachments
                    WHERE Deleted = 0
                    GROUP BY WorkflowRequirementID
                )
        " : '';
        $attachmentJoin = $hasAttachments ? "
                LEFT JOIN attachment_counts ac
                    ON ac.WorkflowRequirementID = r.WorkflowRequirementID
        " : '';
        $attachmentSelect = $hasAttachments ? 'COALESCE(ac.AttachmentCount, 0)' : '0';

        try {
            $stmt = $this->conn->prepare("
                WITH requirement_task_links AS (
                    SELECT DISTINCT
                        CONVERT(INT, l.LinkedEntityID) AS WorkflowRequirementID,
                        l.WorkflowTaskID
                    FROM dbo.tblWorkflowEntityLinks l
                    WHERE l.Active = 1
                      AND l.LinkedEntity = N'WorkflowRequirement'
                      AND l.LinkedEntityID IS NOT NULL
                      AND l.WorkflowTaskID IS NOT NULL
                ),
                task_counts AS (
                    SELECT
                        rtl.WorkflowRequirementID,
                        COUNT(1) AS TaskLinkCount,
                        SUM(CASE
                            WHEN t.WorkflowTaskID IS NULL THEN 0
                            WHEN t.CompletedAt IS NOT NULL
                              OR UPPER(COALESCE(s.Code, N'')) IN (N'COMPLETED', N'CANCELLED', N'CLOSED', N'DONE', N'RESOLVED')
                              OR UPPER(COALESCE(s.Name, N'')) IN (N'COMPLETED', N'CANCELLED', N'CLOSED', N'DONE', N'RESOLVED')
                            THEN 0
                            ELSE 1
                        END) AS OpenTaskCount,
                        SUM(CASE
                            WHEN t.WorkflowTaskID IS NOT NULL
                              AND (
                                  t.CompletedAt IS NOT NULL
                                  OR UPPER(COALESCE(s.Code, N'')) IN (N'COMPLETED', N'CANCELLED', N'CLOSED', N'DONE', N'RESOLVED')
                                  OR UPPER(COALESCE(s.Name, N'')) IN (N'COMPLETED', N'CANCELLED', N'CLOSED', N'DONE', N'RESOLVED')
                              )
                            THEN 1
                            ELSE 0
                        END) AS ClosedTaskCount,
                        MIN(CASE
                            WHEN t.WorkflowTaskID IS NOT NULL
                              AND t.DueDate IS NOT NULL
                              AND t.CompletedAt IS NULL
                              AND UPPER(COALESCE(s.Code, N'')) NOT IN (N'COMPLETED', N'CANCELLED', N'CLOSED', N'DONE', N'RESOLVED')
                              AND UPPER(COALESCE(s.Name, N'')) NOT IN (N'COMPLETED', N'CANCELLED', N'CLOSED', N'DONE', N'RESOLVED')
                            THEN t.DueDate
                            ELSE NULL
                        END) AS NextOpenTaskDueDate
                    FROM requirement_task_links rtl
                    LEFT JOIN dbo.tblWorkflowTasks t
                        ON t.WorkflowTaskID = rtl.WorkflowTaskID
                    LEFT JOIN dbo.tblWorkflowTaskStatuses s
                        ON s.StatusID = t.StatusID
                    GROUP BY rtl.WorkflowRequirementID
                ),
                related_counts AS (
                    SELECT
                        rtl.WorkflowRequirementID,
                        SUM(CASE WHEN UPPER(COALESCE(rel.LinkTypeCode, N'')) LIKE N'TRAINING[_]%' THEN 1 ELSE 0 END) AS TrainingLinkCount,
                        SUM(CASE WHEN UPPER(COALESCE(rel.LinkTypeCode, N'')) LIKE N'SCREEN[_]TEST[_]%' OR UPPER(COALESCE(rel.LinkTypeCode, N'')) = N'TEST_EVIDENCE' THEN 1 ELSE 0 END) AS TestingLinkCount,
                        SUM(CASE WHEN UPPER(COALESCE(rel.LinkTypeCode, N'')) = N'DEFECT' THEN 1 ELSE 0 END) AS DefectLinkCount,
                        SUM(CASE WHEN UPPER(COALESCE(rel.LinkTypeCode, N'')) = N'DOCUMENTATION' THEN 1 ELSE 0 END) AS DocumentationLinkCount,
                        SUM(CASE WHEN UPPER(COALESCE(rel.LinkTypeCode, N'')) = N'RELEASE_CHECKLIST' THEN 1 ELSE 0 END) AS ReleaseLinkCount
                    FROM requirement_task_links rtl
                    INNER JOIN dbo.tblWorkflowEntityLinks rel
                        ON rel.WorkflowTaskID = rtl.WorkflowTaskID
                       AND rel.Active = 1
                    WHERE NOT (
                        rel.LinkedEntity = N'WorkflowRequirement'
                        AND rel.LinkedEntityID = rtl.WorkflowRequirementID
                    )
                    GROUP BY rtl.WorkflowRequirementID
                )
                {$attachmentCte}
                SELECT
                    r.WorkflowRequirementID,
                    r.RequirementCode,
                    r.WorkflowProjectID,
                    {$parentRequirementSelect},
                    {$requirementLevelSelect},
                    r.ModuleCode,
                    r.RequirementTitle,
                    {$deliveryClassSelect},
                    r.RequirementTypeCode,
                    r.PriorityCode,
                    r.RequirementStatusCode,
                    {$sourceDocumentSelect},
                    {$sourceSectionSelect},
                    r.AcceptanceCriteria,
                    r.OwnerUserID,
                    r.Active,
                    r.UpdatedAt,
                    p.ProjectCode,
                    p.ProjectName,
                    {$parentRequirementInfoSelect},
                    COALESCE(NULLIF(LTRIM(RTRIM(owner.DisplayName)), N''), NULLIF(LTRIM(RTRIM(owner.Username)), N''), CASE WHEN r.OwnerUserID IS NOT NULL THEN CONCAT(N'User #', r.OwnerUserID) ELSE NULL END) AS OwnerName,
                    COALESCE(tc.TaskLinkCount, 0) AS TaskLinkCount,
                    COALESCE(tc.OpenTaskCount, 0) AS OpenTaskCount,
                    COALESCE(tc.ClosedTaskCount, 0) AS ClosedTaskCount,
                    tc.NextOpenTaskDueDate,
                    COALESCE(rc.TestingLinkCount, 0) AS TestingLinkCount,
                    COALESCE(rc.TrainingLinkCount, 0) AS TrainingLinkCount,
                    COALESCE(rc.DefectLinkCount, 0) AS DefectLinkCount,
                    COALESCE(rc.DocumentationLinkCount, 0) AS DocumentationLinkCount,
                    COALESCE(rc.ReleaseLinkCount, 0) AS ReleaseLinkCount,
                    {$attachmentSelect} AS AttachmentCount,
                    CASE WHEN r.AcceptanceCriteria IS NULL OR LTRIM(RTRIM(r.AcceptanceCriteria)) = N'' THEN 1 ELSE 0 END AS MissingAcceptanceCriteria,
                    CAST(NULL AS NVARCHAR(MAX)) AS LinkedTaskTitles
                FROM dbo.tblWorkflowRequirements r
                LEFT JOIN dbo.tblWorkflowProjects p
                    ON p.WorkflowProjectID = r.WorkflowProjectID
                {$parentRequirementJoin}
                LEFT JOIN dbo.tblUsers owner
                    ON owner.UserID = r.OwnerUserID
                LEFT JOIN task_counts tc
                    ON tc.WorkflowRequirementID = r.WorkflowRequirementID
                LEFT JOIN related_counts rc
                    ON rc.WorkflowRequirementID = r.WorkflowRequirementID
                {$attachmentJoin}
                WHERE " . implode(' AND ', $where) . "
                ORDER BY
                    {$hierarchyOrderSql}
                    CASE WHEN COALESCE(tc.TaskLinkCount, 0) = 0 THEN 0 ELSE 1 END,
                    CASE r.PriorityCode WHEN N'MUST' THEN 1 WHEN N'SHOULD' THEN 2 WHEN N'COULD' THEN 3 ELSE 4 END,
                    " . ($hasDeliveryClass ? "r.DeliveryClassCode ASC," : "") . "
                    p.ProjectName ASC,
                    r.RequirementCode ASC,
                    r.WorkflowRequirementID DESC
            ");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $rows = $this->attachTraceabilityTaskTitles($rows);
            $this->lastError = '';
            return $this->applyTraceabilityCoverageFilter(
                $this->hydrateTraceabilityRows($rows),
                (string)($filters['coverage'] ?? '')
            );
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    public function summarizeTraceabilityMatrix(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'missingTask' => 0,
            'withOpenTasks' => 0,
            'missingTesting' => 0,
            'missingAcceptanceCriteria' => 0,
            'withDefects' => 0,
        ];

        foreach ($rows as $row) {
            if ((int)($row['TaskLinkCount'] ?? 0) <= 0) {
                $summary['missingTask']++;
            }
            if ((int)($row['OpenTaskCount'] ?? 0) > 0) {
                $summary['withOpenTasks']++;
            }
            if ((int)($row['TestingLinkCount'] ?? 0) <= 0) {
                $summary['missingTesting']++;
            }
            if ((int)($row['MissingAcceptanceCriteria'] ?? 0) === 1) {
                $summary['missingAcceptanceCriteria']++;
            }
            if ((int)($row['DefectLinkCount'] ?? 0) > 0) {
                $summary['withDefects']++;
            }
        }

        return $summary;
    }

    public function findRequirement(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsRequirements()) {
            return null;
        }

        $hasDeliveryClass = $this->columnExists('dbo.tblWorkflowRequirements', 'DeliveryClassCode');
        $hasSourceDocument = $this->columnExists('dbo.tblWorkflowRequirements', 'SourceDocument');
        $hasSourceSection = $this->columnExists('dbo.tblWorkflowRequirements', 'SourceSection');
        $hasHierarchy = $this->supportsRequirementHierarchy();
        $parentRequirementSelect = $hasHierarchy ? 'r.ParentRequirementID' : 'CAST(NULL AS INT) AS ParentRequirementID';
        $requirementLevelSelect = $hasHierarchy ? 'r.RequirementLevelCode' : "CAST(N'HIGH_LEVEL' AS NVARCHAR(30)) AS RequirementLevelCode";
        $deliveryClassSelect = $hasDeliveryClass ? 'r.DeliveryClassCode' : "CAST(NULL AS NVARCHAR(30)) AS DeliveryClassCode";
        $sourceDocumentSelect = $hasSourceDocument ? 'r.SourceDocument' : "CAST(NULL AS NVARCHAR(255)) AS SourceDocument";
        $sourceSectionSelect = $hasSourceSection ? 'r.SourceSection' : "CAST(NULL AS NVARCHAR(255)) AS SourceSection";
        $parentRequirementInfoSelect = $hasHierarchy
            ? "parentReq.RequirementCode AS ParentRequirementCode,\n                    parentReq.RequirementTitle AS ParentRequirementTitle"
            : "CAST(NULL AS NVARCHAR(50)) AS ParentRequirementCode,\n                    CAST(NULL AS NVARCHAR(255)) AS ParentRequirementTitle";
        $parentRequirementJoin = $hasHierarchy ? "
                LEFT JOIN dbo.tblWorkflowRequirements parentReq
                    ON parentReq.WorkflowRequirementID = r.ParentRequirementID
        " : '';

        try {
            $stmt = $this->conn->prepare("
                SELECT TOP 1
                    r.WorkflowRequirementID,
                    r.RequirementCode,
                    r.WorkflowProjectID,
                    {$parentRequirementSelect},
                    {$requirementLevelSelect},
                    r.ModuleCode,
                    r.RequirementTitle,
                    {$deliveryClassSelect},
                    r.RequirementTypeCode,
                    r.PriorityCode,
                    r.RequirementStatusCode,
                    {$sourceDocumentSelect},
                    {$sourceSectionSelect},
                    r.[Description],
                    r.AcceptanceCriteria,
                    r.RequestedByUserID,
                    r.OwnerUserID,
                    r.ApprovedByUserID,
                    r.ApprovedAt,
                    r.Active,
                    r.CreatedAt,
                    r.CreatedBy,
                    r.UpdatedAt,
                    r.UpdatedBy,
                    p.ProjectCode,
                    p.ProjectName,
                    {$parentRequirementInfoSelect},
                    COALESCE(NULLIF(LTRIM(RTRIM(owner.DisplayName)), N''), NULLIF(LTRIM(RTRIM(owner.Username)), N''), CASE WHEN r.OwnerUserID IS NOT NULL THEN CONCAT(N'User #', r.OwnerUserID) ELSE NULL END) AS OwnerName,
                    COALESCE(NULLIF(LTRIM(RTRIM(requestedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(requestedBy.Username)), N''), CASE WHEN r.RequestedByUserID IS NOT NULL THEN CONCAT(N'User #', r.RequestedByUserID) ELSE NULL END) AS RequestedByName,
                    COALESCE(NULLIF(LTRIM(RTRIM(approvedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(approvedBy.Username)), N''), CASE WHEN r.ApprovedByUserID IS NOT NULL THEN CONCAT(N'User #', r.ApprovedByUserID) ELSE NULL END) AS ApprovedByName
                FROM dbo.tblWorkflowRequirements r
                LEFT JOIN dbo.tblWorkflowProjects p
                    ON p.WorkflowProjectID = r.WorkflowProjectID
                {$parentRequirementJoin}
                LEFT JOIN dbo.tblUsers owner
                    ON owner.UserID = r.OwnerUserID
                LEFT JOIN dbo.tblUsers requestedBy
                    ON requestedBy.UserID = r.RequestedByUserID
                LEFT JOIN dbo.tblUsers approvedBy
                    ON approvedBy.UserID = r.ApprovedByUserID
                WHERE r.WorkflowRequirementID = :id
            ");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->lastError = '';
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function saveRequirement(array $data, int $currentUserID): int
    {
        if (!$this->supportsRequirements()) {
            throw new \RuntimeException('Workflow requirement tables are not installed.');
        }

        $id = (int)($data['WorkflowRequirementID'] ?? 0);
        $title = trim((string)($data['RequirementTitle'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('Requirement title is required.');
        }

        $status = $this->normalizeStatusCode($data['RequirementStatusCode'] ?? 'DRAFT', true);
        $approvedByUserID = !empty($data['ApprovedByUserID']) ? (int)$data['ApprovedByUserID'] : null;
        $approvedAt = $this->nullableDateTime($data['ApprovedAt'] ?? null);
        $approvalStatusCodes = ['APPROVED', 'IN_BUILD', 'IN_TEST', 'COMPLETED'];
        if (in_array($status, $approvalStatusCodes, true) && $approvedAt === null) {
            $approvedByUserID = $currentUserID > 0 ? $currentUserID : $approvedByUserID;
            $approvedAt = 'SYSUTCDATETIME';
        } elseif (in_array($status, ['DRAFT', 'REVIEW'], true)) {
            $approvedByUserID = null;
            $approvedAt = null;
        }

        $hasHierarchy = $this->supportsRequirementHierarchy();
        $hasDeliveryClass = $this->columnExists('dbo.tblWorkflowRequirements', 'DeliveryClassCode');
        $hasSourceDocument = $this->columnExists('dbo.tblWorkflowRequirements', 'SourceDocument');
        $hasSourceSection = $this->columnExists('dbo.tblWorkflowRequirements', 'SourceSection');
        $workflowProjectID = !empty($data['WorkflowProjectID']) ? (int)$data['WorkflowProjectID'] : null;
        $requirementLevel = $this->normalizeRequirementLevelCode($data['RequirementLevelCode'] ?? 'HIGH_LEVEL', true);
        $parentRequirementID = !empty($data['ParentRequirementID']) ? (int)$data['ParentRequirementID'] : null;

        if ($hasHierarchy) {
            if ($requirementLevel === 'HIGH_LEVEL') {
                $parentRequirementID = null;
            } elseif ($parentRequirementID === null || $parentRequirementID <= 0) {
                throw new \InvalidArgumentException('Detailed requirements must be linked to a high-level requirement.');
            } elseif ($id > 0 && $parentRequirementID === $id) {
                throw new \InvalidArgumentException('A requirement cannot be its own parent.');
            }

            if ($requirementLevel === 'DETAILED' && $id > 0 && $this->listChildRequirements($id) !== []) {
                throw new \InvalidArgumentException('A requirement with detailed child requirements cannot itself be converted to a detailed requirement.');
            }

            if ($parentRequirementID !== null) {
                $parent = $this->findRequirement($parentRequirementID);
                if (!$parent) {
                    throw new \InvalidArgumentException('Parent requirement was not found.');
                }
                if ($this->normalizeRequirementLevelCode($parent['RequirementLevelCode'] ?? 'HIGH_LEVEL', true) !== 'HIGH_LEVEL') {
                    throw new \InvalidArgumentException('Detailed requirements can only be linked to a high-level requirement.');
                }

                $parentProjectID = (int)($parent['WorkflowProjectID'] ?? 0);
                if ($parentProjectID > 0 && $workflowProjectID === null) {
                    $workflowProjectID = $parentProjectID;
                } elseif ($parentProjectID > 0 && $workflowProjectID !== null && $workflowProjectID !== $parentProjectID) {
                    throw new \InvalidArgumentException('Detailed requirements must use the same project as their high-level requirement.');
                }
            }
        }

        $payload = [
            ':RequirementCode' => $this->nullableString($data['RequirementCode'] ?? null),
            ':WorkflowProjectID' => $workflowProjectID,
            ':ModuleCode' => $this->nullableString($data['ModuleCode'] ?? null),
            ':RequirementTitle' => $title,
            ':RequirementTypeCode' => $this->normalizeTypeCode($data['RequirementTypeCode'] ?? 'FUNCTIONAL', true),
            ':PriorityCode' => $this->normalizePriorityCode($data['PriorityCode'] ?? 'SHOULD', true),
            ':RequirementStatusCode' => $status,
            ':Description' => $this->nullableString($data['Description'] ?? null),
            ':AcceptanceCriteria' => $this->nullableString($data['AcceptanceCriteria'] ?? null),
            ':RequestedByUserID' => !empty($data['RequestedByUserID']) ? (int)$data['RequestedByUserID'] : null,
            ':OwnerUserID' => !empty($data['OwnerUserID']) ? (int)$data['OwnerUserID'] : null,
            ':ApprovedByUserID' => $approvedByUserID,
            ':ApprovedAt' => $approvedAt === 'SYSUTCDATETIME' ? null : $approvedAt,
            ':Active' => !empty($data['Active']) ? 1 : 0,
        ];

        $extraUpdateSet = [];
        $extraInsertColumns = [];
        $extraInsertValues = [];
        if ($hasHierarchy) {
            $payload[':ParentRequirementID'] = $parentRequirementID;
            $payload[':RequirementLevelCode'] = $requirementLevel;
            $extraUpdateSet[] = 'ParentRequirementID = :ParentRequirementID';
            $extraUpdateSet[] = 'RequirementLevelCode = :RequirementLevelCode';
            $extraInsertColumns[] = 'ParentRequirementID';
            $extraInsertColumns[] = 'RequirementLevelCode';
            $extraInsertValues[] = ':ParentRequirementID';
            $extraInsertValues[] = ':RequirementLevelCode';
        }
        if ($hasDeliveryClass) {
            $payload[':DeliveryClassCode'] = $this->normalizeDeliveryClassCode($data['DeliveryClassCode'] ?? 'ENHANCEMENT', true);
            $extraUpdateSet[] = 'DeliveryClassCode = :DeliveryClassCode';
            $extraInsertColumns[] = 'DeliveryClassCode';
            $extraInsertValues[] = ':DeliveryClassCode';
        }
        if ($hasSourceDocument) {
            $payload[':SourceDocument'] = $this->nullableString($data['SourceDocument'] ?? null);
            $extraUpdateSet[] = 'SourceDocument = :SourceDocument';
            $extraInsertColumns[] = 'SourceDocument';
            $extraInsertValues[] = ':SourceDocument';
        }
        if ($hasSourceSection) {
            $payload[':SourceSection'] = $this->nullableString($data['SourceSection'] ?? null);
            $extraUpdateSet[] = 'SourceSection = :SourceSection';
            $extraInsertColumns[] = 'SourceSection';
            $extraInsertValues[] = ':SourceSection';
        }

        $extraUpdateSql = $extraUpdateSet !== [] ? "                    " . implode(",\n                    ", $extraUpdateSet) . ",\n" : '';
        $extraInsertColumnSql = $extraInsertColumns !== [] ? implode(', ', $extraInsertColumns) . ', ' : '';
        $extraInsertValueSql = $extraInsertValues !== [] ? implode(', ', $extraInsertValues) . ', ' : '';

        if ($id > 0) {
            $approvedAtSql = $approvedAt === 'SYSUTCDATETIME' ? 'SYSUTCDATETIME()' : ':ApprovedAt';
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowRequirements
                SET RequirementCode = :RequirementCode,
                    WorkflowProjectID = :WorkflowProjectID,
                    ModuleCode = :ModuleCode,
                    RequirementTitle = :RequirementTitle,
                    RequirementTypeCode = :RequirementTypeCode,
                    PriorityCode = :PriorityCode,
                    RequirementStatusCode = :RequirementStatusCode,
{$extraUpdateSql}
                    [Description] = :Description,
                    AcceptanceCriteria = :AcceptanceCriteria,
                    RequestedByUserID = :RequestedByUserID,
                    OwnerUserID = :OwnerUserID,
                    ApprovedByUserID = :ApprovedByUserID,
                    ApprovedAt = {$approvedAtSql},
                    Active = :Active,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowRequirementID = :id
            ");
            $payload[':UpdatedBy'] = $currentUserID > 0 ? $currentUserID : null;
            $payload[':id'] = $id;
            if ($approvedAt === 'SYSUTCDATETIME') {
                unset($payload[':ApprovedAt']);
            }
            $stmt->execute($payload);
            $savedId = $id;
        } else {
            $approvedAtSql = $approvedAt === 'SYSUTCDATETIME' ? 'SYSUTCDATETIME()' : ':ApprovedAt';
            $stmt = $this->conn->prepare("
                INSERT INTO dbo.tblWorkflowRequirements
                    (RequirementCode, WorkflowProjectID, ModuleCode, RequirementTitle, {$extraInsertColumnSql}RequirementTypeCode,
                     PriorityCode, RequirementStatusCode, [Description], AcceptanceCriteria,
                     RequestedByUserID, OwnerUserID, ApprovedByUserID, ApprovedAt, Active,
                     CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
                OUTPUT INSERTED.WorkflowRequirementID
                VALUES
                    (:RequirementCode, :WorkflowProjectID, :ModuleCode, :RequirementTitle, {$extraInsertValueSql}:RequirementTypeCode,
                     :PriorityCode, :RequirementStatusCode, :Description, :AcceptanceCriteria,
                     :RequestedByUserID, :OwnerUserID, :ApprovedByUserID, {$approvedAtSql}, :Active,
                     SYSUTCDATETIME(), :CreatedBy, SYSUTCDATETIME(), :UpdatedBy)
            ");
            $payload[':CreatedBy'] = $currentUserID > 0 ? $currentUserID : null;
            $payload[':UpdatedBy'] = $currentUserID > 0 ? $currentUserID : null;
            if ($approvedAt === 'SYSUTCDATETIME') {
                unset($payload[':ApprovedAt']);
            }
            $stmt->execute($payload);
            $savedId = (int)($stmt->fetchColumn() ?: 0);
        }

        if ($savedId <= 0) {
            throw new \RuntimeException('Workflow requirement save did not return a requirement id.');
        }

        if (empty($data['RequirementCode'])) {
            $code = $this->defaultRequirementCode($savedId);
            $updateCode = $this->conn->prepare("
                UPDATE dbo.tblWorkflowRequirements
                SET RequirementCode = :code
                WHERE WorkflowRequirementID = :id
                  AND (RequirementCode IS NULL OR LTRIM(RTRIM(RequirementCode)) = N'')
            ");
            $updateCode->execute([
                ':code' => $code,
                ':id' => $savedId,
            ]);
        }

        $this->lastError = '';
        return $savedId;
    }

    public function archiveRequirement(int $requirementID, int $currentUserID): bool
    {
        if ($requirementID <= 0 || !$this->supportsRequirements()) {
            return false;
        }

        try {
            $hasHierarchy = $this->supportsRequirementHierarchy();
            $where = $hasHierarchy
                ? '(WorkflowRequirementID = :WorkflowRequirementID OR ParentRequirementID = :WorkflowRequirementID)'
                : 'WorkflowRequirementID = :WorkflowRequirementID';
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowRequirements
                SET Active = 0,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE {$where}
                  AND Active = 1
            ");
            $stmt->execute([
                ':WorkflowRequirementID' => $requirementID,
                ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
            ]);
            $this->lastError = '';
            return $stmt->rowCount() > 0;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRequirementHistory(int $requirementID): array
    {
        if ($requirementID <= 0 || !$this->supportsRequirementHistory()) {
            return [];
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT
                    h.WorkflowRequirementHistoryID,
                    h.WorkflowRequirementID,
                    h.EventTypeCode,
                    h.FromStatusCode,
                    h.ToStatusCode,
                    h.FieldName,
                    h.OldValue,
                    h.NewValue,
                    h.Notes,
                    h.ChangedByUserID,
                    h.ChangedAt,
                    COALESCE(NULLIF(LTRIM(RTRIM(changedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(changedBy.Username)), N''), CASE WHEN h.ChangedByUserID IS NOT NULL THEN CONCAT(N'User #', h.ChangedByUserID) ELSE NULL END) AS ChangedByName
                FROM dbo.tblWorkflowRequirementHistory h
                LEFT JOIN dbo.tblUsers changedBy
                    ON changedBy.UserID = h.ChangedByUserID
                WHERE h.WorkflowRequirementID = :WorkflowRequirementID
                ORDER BY h.ChangedAt DESC, h.WorkflowRequirementHistoryID DESC
            ");
            $stmt->execute([':WorkflowRequirementID' => $requirementID]);
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    public function recordRequirementSaveHistory(int $requirementID, ?array $before, array $after, int $currentUserID): void
    {
        if ($requirementID <= 0 || !$this->supportsRequirementHistory()) {
            return;
        }

        if ($before === null) {
            $this->insertRequirementHistory($requirementID, [
                'EventTypeCode' => 'CREATED',
                'ToStatusCode' => $this->historyValue($after['RequirementStatusCode'] ?? ''),
                'FieldName' => null,
                'OldValue' => null,
                'NewValue' => $this->historyValue($after['RequirementTitle'] ?? ''),
                'Notes' => null,
            ], $currentUserID);
            return;
        }

        $fields = [
            'RequirementCode',
            'WorkflowProjectID',
            'ParentRequirementID',
            'RequirementLevelCode',
            'ModuleCode',
            'RequirementTitle',
            'DeliveryClassCode',
            'RequirementTypeCode',
            'PriorityCode',
            'RequirementStatusCode',
            'SourceDocument',
            'SourceSection',
            'Description',
            'AcceptanceCriteria',
            'RequestedByUserID',
            'OwnerUserID',
            'ApprovedByUserID',
            'ApprovedAt',
            'Active',
        ];

        foreach ($fields as $fieldName) {
            $oldValue = $this->historyValue($before[$fieldName] ?? null);
            $newValue = $this->historyValue($after[$fieldName] ?? null);
            if ($oldValue === $newValue) {
                continue;
            }

            $isStatus = $fieldName === 'RequirementStatusCode';
            $this->insertRequirementHistory($requirementID, [
                'EventTypeCode' => $isStatus ? 'STATUS_CHANGED' : 'FIELD_CHANGED',
                'FromStatusCode' => $isStatus ? $oldValue : $this->historyValue($after['RequirementStatusCode'] ?? null),
                'ToStatusCode' => $isStatus ? $newValue : $this->historyValue($after['RequirementStatusCode'] ?? null),
                'FieldName' => $fieldName,
                'OldValue' => $oldValue,
                'NewValue' => $newValue,
                'Notes' => $isStatus ? 'Saved from requirement form.' : null,
            ], $currentUserID);
        }
    }

    public function transitionRequirementStatus(int $requirementID, string $toStatusCode, string $notes, int $currentUserID): bool
    {
        if ($requirementID <= 0 || !$this->supportsRequirements()) {
            $this->lastError = 'Workflow requirement storage is not available.';
            return false;
        }

        $toStatusCode = $this->normalizeStatusCode($toStatusCode, false);
        if ($toStatusCode === '') {
            $this->lastError = 'Invalid requirement status.';
            return false;
        }

        $record = $this->findRequirement($requirementID);
        if (!$record) {
            $this->lastError = 'Workflow requirement not found.';
            return false;
        }

        $fromStatusCode = $this->normalizeStatusCode($record['RequirementStatusCode'] ?? 'DRAFT', true);
        $approvedByUserID = !empty($record['ApprovedByUserID']) ? (int)$record['ApprovedByUserID'] : null;
        $approvedAt = $this->nullableDateTime($record['ApprovedAt'] ?? null);
        $approvalStatusCodes = ['APPROVED', 'IN_BUILD', 'IN_TEST', 'COMPLETED'];
        $approvedAtSql = ':ApprovedAt';
        $isApprovalStatus = in_array($toStatusCode, $approvalStatusCodes, true);
        $isEnteringApprovalStatus = $isApprovalStatus && !in_array($fromStatusCode, $approvalStatusCodes, true);

        if ($isApprovalStatus && ($isEnteringApprovalStatus || $approvedByUserID === null || $approvedAt === null)) {
            $approvedByUserID = $currentUserID > 0 ? $currentUserID : $approvedByUserID;
            $approvedAtSql = 'SYSUTCDATETIME()';
            $approvedAt = null;
        } elseif (in_array($toStatusCode, ['DRAFT', 'REVIEW'], true)) {
            $approvedByUserID = null;
            $approvedAt = null;
        }

        try {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblWorkflowRequirements
                SET RequirementStatusCode = :RequirementStatusCode,
                    ApprovedByUserID = :ApprovedByUserID,
                    ApprovedAt = {$approvedAtSql},
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :UpdatedBy
                WHERE WorkflowRequirementID = :WorkflowRequirementID
            ");

            $params = [
                ':RequirementStatusCode' => $toStatusCode,
                ':ApprovedByUserID' => $approvedByUserID,
                ':UpdatedBy' => $currentUserID > 0 ? $currentUserID : null,
                ':WorkflowRequirementID' => $requirementID,
            ];
            if ($approvedAtSql !== 'SYSUTCDATETIME()') {
                $params[':ApprovedAt'] = $approvedAt;
            }
            $stmt->execute($params);

            $eventType = $fromStatusCode === $toStatusCode ? 'REVIEW_NOTE' : ($toStatusCode === 'APPROVED' ? 'APPROVED' : 'STATUS_CHANGED');
            $this->insertRequirementHistory($requirementID, [
                'EventTypeCode' => $eventType,
                'FromStatusCode' => $fromStatusCode,
                'ToStatusCode' => $toStatusCode,
                'FieldName' => 'RequirementStatusCode',
                'OldValue' => $fromStatusCode,
                'NewValue' => $toStatusCode,
                'Notes' => $this->nullableString($notes),
            ], $currentUserID);

            $this->lastError = '';
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function defaultRequirementCode(int $id): string
    {
        return 'REQ-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAttachments(int $requirementID, bool $includeDeleted = false): array
    {
        if ($requirementID <= 0 || !$this->supportsRequirementAttachments()) {
            return [];
        }

        $deletedSql = $includeDeleted ? '' : 'AND a.Deleted = 0';

        try {
            $sql = "
                SELECT
                    a.WorkflowRequirementAttachmentID,
                    a.WorkflowRequirementID,
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
                FROM dbo.tblWorkflowRequirementAttachments a
                LEFT JOIN dbo.tblUsers uploadedBy ON a.UploadedByUserID = uploadedBy.UserID
                LEFT JOIN dbo.tblUsers deletedBy ON a.DeletedByUserID = deletedBy.UserID
                WHERE a.WorkflowRequirementID = :WorkflowRequirementID
                  {$deletedSql}
                ORDER BY a.UploadedAt DESC, a.WorkflowRequirementAttachmentID DESC
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':WorkflowRequirementID', $requirementID, PDO::PARAM_INT);
            $stmt->execute();
            $this->lastError = '';
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getAttachment(int $attachmentID, bool $includeDeleted = false): ?array
    {
        if ($attachmentID <= 0 || !$this->supportsRequirementAttachments()) {
            return null;
        }

        $deletedSql = $includeDeleted ? '' : 'AND a.Deleted = 0';

        try {
            $sql = "
                SELECT
                    a.WorkflowRequirementAttachmentID,
                    a.WorkflowRequirementID,
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
                    r.RequirementCode,
                    r.RequirementTitle,
                    r.WorkflowProjectID,
                    r.OwnerUserID,
                    r.CreatedBy,
                    COALESCE(NULLIF(LTRIM(RTRIM(uploadedBy.DisplayName)), N''), NULLIF(LTRIM(RTRIM(uploadedBy.Username)), N''), CASE WHEN a.UploadedByUserID IS NOT NULL THEN CONCAT(N'User #', a.UploadedByUserID) ELSE NULL END) AS UploadedByName
                FROM dbo.tblWorkflowRequirementAttachments a
                INNER JOIN dbo.tblWorkflowRequirements r
                    ON r.WorkflowRequirementID = a.WorkflowRequirementID
                LEFT JOIN dbo.tblUsers uploadedBy
                    ON a.UploadedByUserID = uploadedBy.UserID
                WHERE a.WorkflowRequirementAttachmentID = :WorkflowRequirementAttachmentID
                  {$deletedSql}
            ";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':WorkflowRequirementAttachmentID', $attachmentID, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->lastError = '';
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function saveAttachment(int $requirementID, array $data): int
    {
        if ($requirementID <= 0 || !$this->supportsRequirementAttachments()) {
            $this->lastError = 'Workflow requirement attachment storage is not available.';
            return 0;
        }

        try {
            $sql = "
                INSERT INTO dbo.tblWorkflowRequirementAttachments
                    (WorkflowRequirementID, OriginalFileName, StoredFileName, StoragePath,
                     MimeType, FileSizeBytes, UploadedByUserID, UploadedAt, Deleted)
                OUTPUT INSERTED.WorkflowRequirementAttachmentID
                VALUES
                    (:WorkflowRequirementID, :OriginalFileName, :StoredFileName, :StoragePath,
                     :MimeType, :FileSizeBytes, :UploadedByUserID, SYSUTCDATETIME(), 0)
            ";
            $stmt = $this->conn->prepare($sql);
            $ok = $stmt->execute([
                ':WorkflowRequirementID' => $requirementID,
                ':OriginalFileName' => trim((string)($data['OriginalFileName'] ?? '')),
                ':StoredFileName' => trim((string)($data['StoredFileName'] ?? '')),
                ':StoragePath' => trim((string)($data['StoragePath'] ?? '')),
                ':MimeType' => $this->nullableString($data['MimeType'] ?? null),
                ':FileSizeBytes' => (int)($data['FileSizeBytes'] ?? 0),
                ':UploadedByUserID' => (int)($data['UploadedByUserID'] ?? 0),
            ]);

            if (!$ok) {
                $this->lastError = implode(' | ', $stmt->errorInfo());
                return 0;
            }

            $attachmentID = (int)($stmt->fetchColumn() ?: 0);
            $this->lastError = '';
            return $attachmentID;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return 0;
        }
    }

    public function markAttachmentDeleted(int $attachmentID, int $userID): bool
    {
        if ($attachmentID <= 0 || $userID <= 0 || !$this->supportsRequirementAttachments()) {
            return false;
        }

        try {
            $sql = "
                UPDATE dbo.tblWorkflowRequirementAttachments
                SET Deleted = 1,
                    DeletedByUserID = :DeletedByUserID,
                    DeletedAt = SYSUTCDATETIME()
                WHERE WorkflowRequirementAttachmentID = :WorkflowRequirementAttachmentID
                  AND Deleted = 0
            ";
            $stmt = $this->conn->prepare($sql);
            $ok = $stmt->execute([
                ':WorkflowRequirementAttachmentID' => $attachmentID,
                ':DeletedByUserID' => $userID,
            ]);
            $this->lastError = $ok ? '' : implode(' | ', $stmt->errorInfo());
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function attachTraceabilityTaskTitles(array $rows): array
    {
        $requirementIDs = [];
        foreach ($rows as $row) {
            $id = (int)($row['WorkflowRequirementID'] ?? 0);
            if ($id > 0) {
                $requirementIDs[$id] = $id;
            }
        }

        if ($requirementIDs === [] || !$this->tableExists('dbo.tblWorkflowEntityLinks') || !$this->tableExists('dbo.tblWorkflowTasks')) {
            return $rows;
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($requirementIDs) as $index => $id) {
            $placeholder = ':requirementID' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $id;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT
                    CONVERT(INT, l.LinkedEntityID) AS WorkflowRequirementID,
                    t.WorkflowTaskID,
                    t.Title,
                    t.DueDate
                FROM dbo.tblWorkflowEntityLinks l
                INNER JOIN dbo.tblWorkflowTasks t
                    ON t.WorkflowTaskID = l.WorkflowTaskID
                WHERE l.Active = 1
                  AND l.LinkedEntity = N'WorkflowRequirement'
                  AND l.LinkedEntityID IN (" . implode(', ', $placeholders) . ")
                  AND l.WorkflowTaskID IS NOT NULL
                ORDER BY
                    CONVERT(INT, l.LinkedEntityID),
                    COALESCE(t.DueDate, CONVERT(DATE, '9999-12-31')),
                    t.WorkflowTaskID
            ");
            $stmt->execute($params);

            $titlesByRequirement = [];
            $seenTaskLinks = [];
            foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $taskRow) {
                $requirementID = (int)($taskRow['WorkflowRequirementID'] ?? 0);
                $taskID = (int)($taskRow['WorkflowTaskID'] ?? 0);
                if ($requirementID <= 0 || $taskID <= 0) {
                    continue;
                }
                $seenKey = $requirementID . ':' . $taskID;
                if (isset($seenTaskLinks[$seenKey])) {
                    continue;
                }
                $seenTaskLinks[$seenKey] = true;
                $title = trim((string)($taskRow['Title'] ?? ''));
                if ($title === '') {
                    $title = '(untitled)';
                }
                $titlesByRequirement[$requirementID][] = '#' . $taskID . ' ' . $title;
            }

            foreach ($rows as &$row) {
                $requirementID = (int)($row['WorkflowRequirementID'] ?? 0);
                $row['LinkedTaskTitles'] = implode('; ', $titlesByRequirement[$requirementID] ?? []);
            }
            unset($row);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
        }

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateTraceabilityRows(array $rows): array
    {
        foreach ($rows as &$row) {
            foreach ([
                'TaskLinkCount',
                'OpenTaskCount',
                'ClosedTaskCount',
                'TestingLinkCount',
                'TrainingLinkCount',
                'DefectLinkCount',
                'DocumentationLinkCount',
                'ReleaseLinkCount',
                'AttachmentCount',
                'MissingAcceptanceCriteria',
            ] as $key) {
                $row[$key] = (int)($row[$key] ?? 0);
            }

            if (!array_key_exists('LinkedTaskTitles', $row)) {
                $row['LinkedTaskTitles'] = '';
            }

            $gapCodes = [];
            if ($row['TaskLinkCount'] <= 0) {
                $gapCodes[] = 'NEEDS_TASK';
            }
            if ($row['OpenTaskCount'] > 0) {
                $gapCodes[] = 'OPEN_TASKS';
            }
            if ($row['MissingAcceptanceCriteria'] === 1) {
                $gapCodes[] = 'MISSING_ACCEPTANCE';
            }
            if ($row['TestingLinkCount'] <= 0) {
                $gapCodes[] = 'NO_TESTING';
            }
            if ($this->traceabilityRowRequiresTraining($row) && $row['TrainingLinkCount'] <= 0) {
                $gapCodes[] = 'NO_TRAINING';
            }
            if ($row['DefectLinkCount'] > 0) {
                $gapCodes[] = 'HAS_DEFECTS';
            }

            $row['TraceabilityGapCodes'] = $gapCodes;
            $row['TraceabilityGapCount'] = count($gapCodes);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function applyTraceabilityCoverageFilter(array $rows, string $coverage): array
    {
        $coverage = strtoupper(trim($coverage));
        if ($coverage === '' || $coverage === 'ALL') {
            return $rows;
        }

        return array_values(array_filter($rows, function (array $row) use ($coverage): bool {
            $gapCodes = is_array($row['TraceabilityGapCodes'] ?? null) ? $row['TraceabilityGapCodes'] : [];

            return match ($coverage) {
                'NEEDS_TASK' => (int)($row['TaskLinkCount'] ?? 0) <= 0,
                'OPEN_TASKS' => (int)($row['OpenTaskCount'] ?? 0) > 0,
                'NO_TESTING' => (int)($row['TestingLinkCount'] ?? 0) <= 0,
                'NO_TRAINING' => in_array('NO_TRAINING', $gapCodes, true),
                'MISSING_ACCEPTANCE' => (int)($row['MissingAcceptanceCriteria'] ?? 0) === 1,
                'HAS_DEFECTS' => (int)($row['DefectLinkCount'] ?? 0) > 0,
                'COMPLETE' => (int)($row['TaskLinkCount'] ?? 0) > 0
                    && (int)($row['OpenTaskCount'] ?? 0) === 0
                    && (int)($row['TestingLinkCount'] ?? 0) > 0
                    && (int)($row['MissingAcceptanceCriteria'] ?? 0) === 0
                    && (int)($row['DefectLinkCount'] ?? 0) === 0,
                default => true,
            };
        }));
    }

    private function traceabilityRowRequiresTraining(array $row): bool
    {
        $type = strtoupper(trim((string)($row['RequirementTypeCode'] ?? '')));
        $deliveryClass = strtoupper(trim((string)($row['DeliveryClassCode'] ?? '')));
        $module = strtoupper(trim((string)($row['ModuleCode'] ?? '')));

        return $type === 'TRAINING'
            || $deliveryClass === 'TRAINING'
            || str_contains($module, 'TRAINING');
    }

    private function normalizeTypeCode($value, bool $defaultFunctional): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->typeOptions()) ? $code : ($defaultFunctional ? 'FUNCTIONAL' : '');
    }

    private function normalizePriorityCode($value, bool $defaultShould): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->priorityOptions()) ? $code : ($defaultShould ? 'SHOULD' : '');
    }

    private function normalizeStatusCode($value, bool $defaultDraft): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->statusOptions()) ? $code : ($defaultDraft ? 'DRAFT' : '');
    }

    private function normalizeRequirementLevelCode($value, bool $defaultHighLevel): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->requirementLevelOptions()) ? $code : ($defaultHighLevel ? 'HIGH_LEVEL' : '');
    }

    private function normalizeDeliveryClassCode($value, bool $defaultEnhancement): string
    {
        $code = strtoupper(trim((string)$value));
        return array_key_exists($code, $this->deliveryClassOptions()) ? $code : ($defaultEnhancement ? 'ENHANCEMENT' : '');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertRequirementHistory(int $requirementID, array $data, int $currentUserID): bool
    {
        if ($requirementID <= 0 || !$this->supportsRequirementHistory()) {
            return false;
        }

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO dbo.tblWorkflowRequirementHistory
                    (WorkflowRequirementID, EventTypeCode, FromStatusCode, ToStatusCode,
                     FieldName, OldValue, NewValue, Notes, ChangedByUserID, ChangedAt)
                VALUES
                    (:WorkflowRequirementID, :EventTypeCode, :FromStatusCode, :ToStatusCode,
                     :FieldName, :OldValue, :NewValue, :Notes, :ChangedByUserID, SYSUTCDATETIME())
            ");
            $ok = $stmt->execute([
                ':WorkflowRequirementID' => $requirementID,
                ':EventTypeCode' => strtoupper(trim((string)($data['EventTypeCode'] ?? 'FIELD_CHANGED'))) ?: 'FIELD_CHANGED',
                ':FromStatusCode' => $this->nullableString($data['FromStatusCode'] ?? null),
                ':ToStatusCode' => $this->nullableString($data['ToStatusCode'] ?? null),
                ':FieldName' => $this->nullableString($data['FieldName'] ?? null),
                ':OldValue' => $this->nullableString($data['OldValue'] ?? null),
                ':NewValue' => $this->nullableString($data['NewValue'] ?? null),
                ':Notes' => $this->nullableString($data['Notes'] ?? null),
                ':ChangedByUserID' => $currentUserID > 0 ? $currentUserID : null,
            ]);
            $this->lastError = $ok ? '' : implode(' | ', $stmt->errorInfo());
            return $ok;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    private function historyValue($value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return trim((string)$value);
        }
        return trim(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
    }

    private function nullableString($value): ?string
    {
        $text = trim((string)$value);
        return $text === '' ? null : $text;
    }

    private function nullableDateTime($value): ?string
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

    private function columnExists(string $qualifiedName, string $columnName): bool
    {
        try {
            $stmt = $this->conn->prepare("SELECT CASE WHEN COL_LENGTH(:qualifiedName, :columnName) IS NULL THEN 0 ELSE 1 END");
            $stmt->execute([
                ':qualifiedName' => trim($qualifiedName),
                ':columnName' => trim($columnName),
            ]);
            return (int)($stmt->fetchColumn() ?: 0) === 1;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
}
