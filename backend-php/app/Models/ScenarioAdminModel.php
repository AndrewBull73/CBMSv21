<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use Throwable;

final class ScenarioAdminModel
{
    private PDO $conn;
    private string $lastError = '';
    private const RATE_COLUMNS = [
        'BPRate',
        'BP1Rate', 'BP2Rate', 'BP3Rate', 'BP4Rate', 'BP5Rate', 'BP6Rate',
        'BP7Rate', 'BP8Rate', 'BP9Rate', 'BP10Rate', 'BP11Rate', 'BP12Rate',
        'OY1Rate', 'OY2Rate', 'OY3Rate', 'OY4Rate', 'OY5Rate',
        'OY6Rate', 'OY7Rate', 'OY8Rate', 'OY9Rate', 'OY10Rate',
    ];

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function listModelsSummary(): array
    {
        $sql = "
            SELECT
                m.CalcModelID,
                m.ModelCode,
                m.ModelName,
                m.ModelVersion,
                m.StatusCode,
                m.ActiveFlag,
                m.EffectiveFrom,
                m.EffectiveTo,
                ISNULL(s.ScenarioCount, 0) AS ScenarioCount,
                ISNULL(p.PeriodCount, 0) AS PeriodCount,
                ISNULL(co.CostObjectCount, 0) AS CostObjectCount,
                ISNULL(n.NodeCount, 0) AS NodeCount,
                ISNULL(f.FormulaCount, 0) AS FormulaCount,
                ISNULL(d.DependencyCount, 0) AS DependencyCount,
                ISNULL(v.ValueCount, 0) AS ValueCount
            FROM dbo.tblCalcModel m
            LEFT JOIN (
                SELECT CalcModelID, COUNT(*) AS ScenarioCount
                FROM dbo.tblCalcScenario
                GROUP BY CalcModelID
            ) s ON s.CalcModelID = m.CalcModelID
            LEFT JOIN (
                SELECT CalcModelID, COUNT(*) AS PeriodCount
                FROM dbo.tblCalcPeriod
                GROUP BY CalcModelID
            ) p ON p.CalcModelID = m.CalcModelID
            LEFT JOIN (
                SELECT CalcModelID, COUNT(*) AS CostObjectCount
                FROM dbo.tblCalcCostObject
                GROUP BY CalcModelID
            ) co ON co.CalcModelID = m.CalcModelID
            LEFT JOIN (
                SELECT CalcModelID, COUNT(*) AS NodeCount
                FROM dbo.tblCalcNode
                GROUP BY CalcModelID
            ) n ON n.CalcModelID = m.CalcModelID
            LEFT JOIN (
                SELECT n.CalcModelID, COUNT(*) AS FormulaCount
                FROM dbo.tblCalcFormula f
                INNER JOIN dbo.tblCalcNode n ON n.NodeID = f.NodeID
                GROUP BY n.CalcModelID
            ) f ON f.CalcModelID = m.CalcModelID
            LEFT JOIN (
                SELECT n.CalcModelID, COUNT(*) AS DependencyCount
                FROM dbo.tblCalcDependency d
                INNER JOIN dbo.tblCalcNode n ON n.NodeID = d.NodeID
                GROUP BY n.CalcModelID
            ) d ON d.CalcModelID = m.CalcModelID
            LEFT JOIN (
                SELECT s.CalcModelID, COUNT(*) AS ValueCount
                FROM dbo.tblScenarioNodeValue v
                INNER JOIN dbo.tblCalcScenario s ON s.ScenarioID = v.ScenarioID
                GROUP BY s.CalcModelID
            ) v ON v.CalcModelID = m.CalcModelID
            ORDER BY m.ModelCode ASC
        ";

        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findModel(int $calcModelId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblCalcModel
            WHERE CalcModelID = :id
        ");
        $stmt->execute([':id' => $calcModelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function saveModel(array $data, int $userId): int
    {
        $id = (int) ($data['CalcModelID'] ?? 0);

        if ($id > 0) {
            $sql = "
                UPDATE dbo.tblCalcModel
                SET ModelCode = :ModelCode,
                    ModelName = :ModelName,
                    ModelVersion = :ModelVersion,
                    StatusCode = :StatusCode,
                    EffectiveFrom = :EffectiveFrom,
                    EffectiveTo = :EffectiveTo,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE CalcModelID = :CalcModelID
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':CalcModelID' => $id,
                ':ModelCode' => $data['ModelCode'],
                ':ModelName' => $data['ModelName'],
                ':ModelVersion' => $data['ModelVersion'],
                ':StatusCode' => $data['StatusCode'],
                ':EffectiveFrom' => $data['EffectiveFrom'],
                ':EffectiveTo' => $data['EffectiveTo'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $userId > 0 ? $userId : null,
            ]);

            return $id;
        }

        $sql = "
            INSERT INTO dbo.tblCalcModel
                (ModelCode, ModelName, ModelVersion, StatusCode, EffectiveFrom, EffectiveTo, ActiveFlag, CreatedBy)
            VALUES
                (:ModelCode, :ModelName, :ModelVersion, :StatusCode, :EffectiveFrom, :EffectiveTo, :ActiveFlag, :CreatedBy)
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ModelCode' => $data['ModelCode'],
            ':ModelName' => $data['ModelName'],
            ':ModelVersion' => $data['ModelVersion'],
            ':StatusCode' => $data['StatusCode'],
            ':EffectiveFrom' => $data['EffectiveFrom'],
            ':EffectiveTo' => $data['EffectiveTo'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $userId > 0 ? $userId : 1,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function getModelCounts(int $calcModelId): array
    {
        $sql = "
            SELECT
                (SELECT COUNT(*) FROM dbo.tblCalcScenario WHERE CalcModelID = :id1) AS ScenarioCount,
                (SELECT COUNT(*) FROM dbo.tblCalcPeriod WHERE CalcModelID = :id2) AS PeriodCount,
                (SELECT COUNT(*) FROM dbo.tblCalcCostObject WHERE CalcModelID = :id3) AS CostObjectCount,
                (SELECT COUNT(*) FROM dbo.tblCalcNode WHERE CalcModelID = :id4) AS NodeCount,
                (SELECT COUNT(*)
                 FROM dbo.tblCalcFormula f
                 INNER JOIN dbo.tblCalcNode n ON n.NodeID = f.NodeID
                 WHERE n.CalcModelID = :id5) AS FormulaCount,
                (SELECT COUNT(*)
                 FROM dbo.tblCalcDependency d
                 INNER JOIN dbo.tblCalcNode n ON n.NodeID = d.NodeID
                 WHERE n.CalcModelID = :id6) AS DependencyCount,
                (SELECT COUNT(*)
                 FROM dbo.tblScenarioNodeValue v
                 INNER JOIN dbo.tblCalcScenario s ON s.ScenarioID = v.ScenarioID
                 WHERE s.CalcModelID = :id7) AS ValueCount
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id1' => $calcModelId,
            ':id2' => $calcModelId,
            ':id3' => $calcModelId,
            ':id4' => $calcModelId,
            ':id5' => $calcModelId,
            ':id6' => $calcModelId,
            ':id7' => $calcModelId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function listScenariosByModel(int $calcModelId): array
    {
        $sql = "
            SELECT
                s.ScenarioID,
                s.CalcModelID,
                s.ParentScenarioID,
                parent.ScenarioCode AS ParentScenarioCode,
                s.ScenarioCode,
                s.ScenarioName,
                s.ScenarioTypeCode,
                s.ScenarioStatusCode,
                s.SortOrder,
                s.LockedFlag,
                s.ApprovedFlag,
                s.ActiveFlag,
                ISNULL(val.ValueCount, 0) AS ValueCount
            FROM dbo.tblCalcScenario s
            LEFT JOIN dbo.tblCalcScenario parent
                ON parent.ScenarioID = s.ParentScenarioID
            LEFT JOIN (
                SELECT ScenarioID, COUNT(*) AS ValueCount
                FROM dbo.tblScenarioNodeValue
                GROUP BY ScenarioID
            ) val ON val.ScenarioID = s.ScenarioID
            WHERE s.CalcModelID = :id
            ORDER BY s.SortOrder, s.ScenarioCode
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listRecentRunsByModel(int $calcModelId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "
            SELECT TOP ({$limit})
                r.CalcRunID,
                r.CalcModelID,
                r.ScenarioID,
                s.ScenarioCode,
                s.ScenarioName,
                r.RunTypeCode,
                r.RunStatusCode,
                r.TriggerSourceCode,
                r.EngineVersion,
                r.StartedDate,
                r.CompletedDate,
                r.RowCountProcessed,
                r.ErrorCount,
                r.Notes
            FROM dbo.tblCalcRun r
            LEFT JOIN dbo.tblCalcScenario s
                ON s.ScenarioID = r.ScenarioID
            WHERE r.CalcModelID = :modelId
            ORDER BY r.StartedDate DESC, r.CalcRunID DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':modelId' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listRecentPublishesByModel(int $calcModelId, int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        $sql = "
            SELECT TOP ({$limit})
                pe.CalcPublishEventID,
                pe.CalcRunID,
                pe.CalcModelID,
                pe.ScenarioID,
                s.ScenarioCode,
                s.ScenarioName,
                pe.PublishStatusCode,
                pe.PublishedDate,
                pe.PublishedRowCount,
                pe.Notes
            FROM dbo.tblCalcPublishEvent pe
            LEFT JOIN dbo.tblCalcScenario s
                ON s.ScenarioID = pe.ScenarioID
            WHERE pe.CalcModelID = :modelId
            ORDER BY pe.PublishedDate DESC, pe.CalcPublishEventID DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':modelId' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findRun(int $calcRunId): ?array
    {
        $sql = "
            SELECT
                r.CalcRunID,
                r.CalcModelID,
                r.ScenarioID,
                s.ScenarioCode,
                s.ScenarioName,
                r.RunTypeCode,
                r.RunStatusCode,
                r.TriggerSourceCode,
                r.EngineVersion,
                r.StartedDate,
                r.CompletedDate,
                r.RowCountProcessed,
                r.ErrorCount,
                r.Notes,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblCalcRunResult rr
                    WHERE rr.CalcRunID = r.CalcRunID
                ) AS ResultRowCount
            FROM dbo.tblCalcRun r
            LEFT JOIN dbo.tblCalcScenario s
                ON s.ScenarioID = r.ScenarioID
            WHERE r.CalcRunID = :runId
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':runId' => $calcRunId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listRunErrors(int $calcRunId, int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $sql = "
            SELECT TOP ({$limit})
                e.CalcRunErrorID,
                e.CalcRunID,
                e.ScenarioID,
                e.CostObjectID,
                co.CostObjectCode,
                co.CostObjectName,
                e.PeriodID,
                p.PeriodCode,
                e.NodeID,
                n.NodeCode,
                n.NodeName,
                e.ErrorCode,
                e.ErrorSeverityCode,
                e.ErrorMessage,
                e.ExpressionText,
                e.ContextJson,
                e.CreatedDate
            FROM dbo.tblCalcRunError e
            LEFT JOIN dbo.tblCalcCostObject co
                ON co.CostObjectID = e.CostObjectID
            LEFT JOIN dbo.tblCalcPeriod p
                ON p.PeriodID = e.PeriodID
            LEFT JOIN dbo.tblCalcNode n
                ON n.NodeID = e.NodeID
            WHERE e.CalcRunID = :runId
            ORDER BY
                CASE e.ErrorSeverityCode
                    WHEN N'ERROR' THEN 1
                    WHEN N'WARN' THEN 2
                    ELSE 3
                END,
                e.CreatedDate DESC,
                e.CalcRunErrorID DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':runId' => $calcRunId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listRunResults(int $calcRunId, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = "
            SELECT TOP ({$limit})
                rr.CalcRunResultID,
                rr.CalcRunID,
                rr.ScenarioID,
                rr.CostObjectID,
                co.CostObjectCode,
                co.CostObjectName,
                rr.PeriodID,
                p.PeriodCode,
                rr.NodeID,
                n.NodeCode,
                n.NodeName,
                rr.ValueDecimal,
                rr.ValueText,
                rr.ValueBit,
                rr.CalculationStatusCode,
                rr.CalculatedDate
            FROM dbo.tblCalcRunResult rr
            LEFT JOIN dbo.tblCalcCostObject co
                ON co.CostObjectID = rr.CostObjectID
            LEFT JOIN dbo.tblCalcPeriod p
                ON p.PeriodID = rr.PeriodID
            LEFT JOIN dbo.tblCalcNode n
                ON n.NodeID = rr.NodeID
            WHERE rr.CalcRunID = :runId
            ORDER BY rr.CalculatedDate DESC, rr.CalcRunResultID DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':runId' => $calcRunId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findScenario(int $scenarioId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblCalcScenario
            WHERE ScenarioID = :id
        ");
        $stmt->execute([':id' => $scenarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listScenarioOptions(int $calcModelId, int $excludeScenarioId = 0): array
    {
        $sql = "
            SELECT ScenarioID, ScenarioCode, ScenarioName
            FROM dbo.tblCalcScenario
            WHERE CalcModelID = :modelId
        ";

        $params = [':modelId' => $calcModelId];
        if ($excludeScenarioId > 0) {
            $sql .= " AND ScenarioID <> :excludeId";
            $params[':excludeId'] = $excludeScenarioId;
        }

        $sql .= " ORDER BY SortOrder, ScenarioCode";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listCostObjectsByModel(int $calcModelId): array
    {
        $stmt = $this->conn->prepare("
            SELECT CostObjectID, CostObjectCode, CostObjectName, ActiveFlag
            FROM dbo.tblCalcCostObject
            WHERE CalcModelID = :modelId
            ORDER BY CostObjectCode
        ");
        $stmt->execute([':modelId' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPeriodsByModel(int $calcModelId): array
    {
        $stmt = $this->conn->prepare("
            SELECT PeriodID, PeriodCode, PeriodNo, SequenceNo, PeriodTypeCode, ActiveFlag
            FROM dbo.tblCalcPeriod
            WHERE CalcModelID = :modelId
            ORDER BY SequenceNo, PeriodCode
        ");
        $stmt->execute([':modelId' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getScenarioValuesEditor(int $scenarioId, int $costObjectId, string $search = ''): array
    {
        $scenario = $this->findScenario($scenarioId);
        if ($scenario === null) {
            throw new \RuntimeException('Scenario not found.');
        }

        $calcModelId = (int) ($scenario['CalcModelID'] ?? 0);
        if ($calcModelId <= 0) {
            throw new \RuntimeException('Scenario does not belong to a valid model.');
        }

        $periods = $this->listPeriodsByModel($calcModelId);
        $nodes = $this->listEditableNodesByModel($calcModelId, $search);
        $values = $this->loadScenarioValueMatrix($scenarioId, $costObjectId, $nodes, $periods);

        return [
            'periods' => $periods,
            'nodes' => $nodes,
            'values' => $values,
        ];
    }

    public function saveScenarioValues(int $scenarioId, int $costObjectId, array $values, int $userId): void
    {
        $scenario = $this->findScenario($scenarioId);
        if ($scenario === null) {
            throw new \RuntimeException('Scenario not found.');
        }

        $calcModelId = (int) ($scenario['CalcModelID'] ?? 0);
        if ($calcModelId <= 0) {
            throw new \RuntimeException('Scenario does not belong to a valid model.');
        }

        $costObjectStmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblCalcCostObject
            WHERE CostObjectID = :costObjectId
              AND CalcModelID = :modelId
        ");
        $costObjectStmt->execute([
            ':costObjectId' => $costObjectId,
            ':modelId' => $calcModelId,
        ]);
        if ((int) $costObjectStmt->fetchColumn() <= 0) {
            throw new \RuntimeException('Selected cost object is not valid for this model.');
        }

        $periods = $this->listPeriodsByModel($calcModelId);
        $nodes = $this->listEditableNodesByModel($calcModelId, '');

        $periodMap = [];
        foreach ($periods as $period) {
            $periodMap[(int) $period['PeriodID']] = $period;
        }

        $nodeMap = [];
        foreach ($nodes as $node) {
            $nodeMap[(int) $node['NodeID']] = $node;
        }

        $deleteStmt = $this->conn->prepare("
            DELETE FROM dbo.tblScenarioNodeValue
            WHERE ScenarioID = :scenarioId
              AND CostObjectID = :costObjectId
              AND PeriodID = :periodId
              AND NodeID = :nodeId
        ");
        $updateStmt = $this->conn->prepare("
            UPDATE dbo.tblScenarioNodeValue
            SET ValueDecimal = :valueDecimal,
                ValueText = :valueText,
                ValueBit = :valueBit,
                ValueSourceCode = N'OVERRIDE',
                OverriddenFlag = 1,
                UpdatedBy = :userId,
                UpdatedDate = SYSUTCDATETIME()
            WHERE ScenarioID = :scenarioId
              AND CostObjectID = :costObjectId
              AND PeriodID = :periodId
              AND NodeID = :nodeId
        ");
        $insertStmt = $this->conn->prepare("
            INSERT INTO dbo.tblScenarioNodeValue
                (ScenarioID, CostObjectID, PeriodID, NodeID, ValueDecimal, ValueText, ValueBit, ValueSourceCode, OverriddenFlag, CreatedBy)
            VALUES
                (:scenarioId, :costObjectId, :periodId, :nodeId, :valueDecimal, :valueText, :valueBit, N'OVERRIDE', 1, :createdBy)
        ");
        $existsStmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblScenarioNodeValue
            WHERE ScenarioID = :scenarioId
              AND CostObjectID = :costObjectId
              AND PeriodID = :periodId
              AND NodeID = :nodeId
        ");

        $this->conn->beginTransaction();
        try {
            foreach ($values as $nodeIdRaw => $periodValues) {
                $nodeId = (int) $nodeIdRaw;
                if (!isset($nodeMap[$nodeId]) || !is_array($periodValues)) {
                    continue;
                }

                $node = $nodeMap[$nodeId];
                foreach ($periodValues as $periodIdRaw => $rawValue) {
                    $periodId = (int) $periodIdRaw;
                    if (!isset($periodMap[$periodId])) {
                        continue;
                    }

                    $normalized = $this->normalizeScenarioValueForSave($rawValue, (string) ($node['DataTypeCode'] ?? 'DECIMAL'));

                    if ($normalized['delete']) {
                        $deleteStmt->execute([
                            ':scenarioId' => $scenarioId,
                            ':costObjectId' => $costObjectId,
                            ':periodId' => $periodId,
                            ':nodeId' => $nodeId,
                        ]);
                        continue;
                    }

                    $params = [
                        ':scenarioId' => $scenarioId,
                        ':costObjectId' => $costObjectId,
                        ':periodId' => $periodId,
                        ':nodeId' => $nodeId,
                        ':valueDecimal' => $normalized['ValueDecimal'],
                        ':valueText' => $normalized['ValueText'],
                        ':valueBit' => $normalized['ValueBit'],
                        ':userId' => $userId > 0 ? $userId : null,
                        ':createdBy' => $userId > 0 ? $userId : 1,
                    ];

                    $existsStmt->execute([
                        ':scenarioId' => $scenarioId,
                        ':costObjectId' => $costObjectId,
                        ':periodId' => $periodId,
                        ':nodeId' => $nodeId,
                    ]);

                    if ((int) $existsStmt->fetchColumn() > 0) {
                        $updateStmt->execute($params);
                    } else {
                        $insertStmt->execute($params);
                    }
                }
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function getScenarioRateOverrideEditor(int $scenarioId, string $rateCode = '', string $dataObjectCode = ''): array
    {
        $this->ensureScenarioRateOverrideTable();

        $scenario = $this->findScenario($scenarioId);
        if ($scenario === null) {
            throw new \RuntimeException('Scenario not found.');
        }

        $contexts = $this->listRateOverrideContextsByScenario($scenarioId);
        if ($contexts === []) {
            return [
                'contexts' => [],
                'rows' => [],
            ];
        }

        $rows = $this->loadRateOverrideEditorRows($scenarioId, $contexts, $rateCode, $dataObjectCode);

        return [
            'contexts' => $contexts,
            'rows' => $rows,
        ];
    }

    public function saveScenarioRateOverrides(int $scenarioId, array $rows, int $userId): void
    {
        $this->ensureScenarioRateOverrideTable();

        $scenario = $this->findScenario($scenarioId);
        if ($scenario === null) {
            throw new \RuntimeException('Scenario not found.');
        }

        $contexts = $this->listRateOverrideContextsByScenario($scenarioId);
        if ($contexts === []) {
            throw new \RuntimeException('No fiscal/version context could be resolved for this scenario model.');
        }

        $validContexts = [];
        foreach ($contexts as $context) {
            $key = $this->rateContextKey(
                (int) ($context['FiscalYearID'] ?? 0),
                (int) ($context['VersionID'] ?? 0),
                (string) ($context['DataObjectCode'] ?? ''),
                (string) ($context['RateCode'] ?? '')
            );
            $validContexts[$key] = true;
        }

        $existsStmt = $this->conn->prepare("
            SELECT COUNT(*)
            FROM dbo.tblCalcScenarioRateOverride
            WHERE ScenarioID = :scenarioId
              AND FiscalYearID = :fiscalYearId
              AND VersionID = :versionId
              AND DataObjectCode = :dataObjectCode
              AND RateCode = :rateCode
        ");
        $deleteStmt = $this->conn->prepare("
            DELETE FROM dbo.tblCalcScenarioRateOverride
            WHERE ScenarioID = :scenarioId
              AND FiscalYearID = :fiscalYearId
              AND VersionID = :versionId
              AND DataObjectCode = :dataObjectCode
              AND RateCode = :rateCode
        ");
        $updateStmt = $this->conn->prepare("
            UPDATE dbo.tblCalcScenarioRateOverride
            SET BPRate = :BPRate,
                BP1Rate = :BP1Rate,
                BP2Rate = :BP2Rate,
                BP3Rate = :BP3Rate,
                BP4Rate = :BP4Rate,
                BP5Rate = :BP5Rate,
                BP6Rate = :BP6Rate,
                BP7Rate = :BP7Rate,
                BP8Rate = :BP8Rate,
                BP9Rate = :BP9Rate,
                BP10Rate = :BP10Rate,
                BP11Rate = :BP11Rate,
                BP12Rate = :BP12Rate,
                OY1Rate = :OY1Rate,
                OY2Rate = :OY2Rate,
                OY3Rate = :OY3Rate,
                OY4Rate = :OY4Rate,
                OY5Rate = :OY5Rate,
                OY6Rate = :OY6Rate,
                OY7Rate = :OY7Rate,
                OY8Rate = :OY8Rate,
                OY9Rate = :OY9Rate,
                OY10Rate = :OY10Rate,
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSUTCDATETIME()
            WHERE ScenarioID = :scenarioId
              AND FiscalYearID = :fiscalYearId
              AND VersionID = :versionId
              AND DataObjectCode = :dataObjectCode
              AND RateCode = :rateCode
        ");
        $insertStmt = $this->conn->prepare("
            INSERT INTO dbo.tblCalcScenarioRateOverride
            (
                ScenarioID,
                FiscalYearID,
                VersionID,
                DataObjectCode,
                RateCode,
                BPRate,
                BP1Rate, BP2Rate, BP3Rate, BP4Rate, BP5Rate, BP6Rate,
                BP7Rate, BP8Rate, BP9Rate, BP10Rate, BP11Rate, BP12Rate,
                OY1Rate, OY2Rate, OY3Rate, OY4Rate, OY5Rate,
                OY6Rate, OY7Rate, OY8Rate, OY9Rate, OY10Rate,
                CreatedBy
            )
            VALUES
            (
                :scenarioId,
                :fiscalYearId,
                :versionId,
                :dataObjectCode,
                :rateCode,
                :BPRate,
                :BP1Rate, :BP2Rate, :BP3Rate, :BP4Rate, :BP5Rate, :BP6Rate,
                :BP7Rate, :BP8Rate, :BP9Rate, :BP10Rate, :BP11Rate, :BP12Rate,
                :OY1Rate, :OY2Rate, :OY3Rate, :OY4Rate, :OY5Rate,
                :OY6Rate, :OY7Rate, :OY8Rate, :OY9Rate, :OY10Rate,
                :createdBy
            )
        ");

        $this->conn->beginTransaction();
        try {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $fiscalYearId = (int) ($row['FiscalYearID'] ?? 0);
                $versionId = (int) ($row['VersionID'] ?? 0);
                $targetDataObjectCode = trim((string) ($row['DataObjectCode'] ?? ''));
                $targetRateCode = trim((string) ($row['RateCode'] ?? ''));
                $contextKey = $this->rateContextKey($fiscalYearId, $versionId, $targetDataObjectCode, $targetRateCode);
                if ($fiscalYearId <= 0 || $versionId <= 0 || $targetDataObjectCode === '' || $targetRateCode === '' || !isset($validContexts[$contextKey])) {
                    continue;
                }

                $normalizedColumns = [];
                $hasAnyValue = false;
                foreach (self::RATE_COLUMNS as $column) {
                    $rawValue = array_key_exists($column, $row) ? $row[$column] : null;
                    $value = $this->nullableRateDecimal($rawValue);
                    $normalizedColumns[$column] = $value;
                    if ($value !== null) {
                        $hasAnyValue = true;
                    }
                }

                if (!$hasAnyValue) {
                    $deleteStmt->execute([
                        ':scenarioId' => $scenarioId,
                        ':fiscalYearId' => $fiscalYearId,
                        ':versionId' => $versionId,
                        ':dataObjectCode' => $targetDataObjectCode,
                        ':rateCode' => $targetRateCode,
                    ]);
                    continue;
                }

                $baseParams = [
                    ':scenarioId' => $scenarioId,
                    ':fiscalYearId' => $fiscalYearId,
                    ':versionId' => $versionId,
                    ':dataObjectCode' => $targetDataObjectCode,
                    ':rateCode' => $targetRateCode,
                ];
                foreach ($normalizedColumns as $column => $value) {
                    $baseParams[':' . $column] = $value;
                }

                $existsStmt->execute([
                    ':scenarioId' => $scenarioId,
                    ':fiscalYearId' => $fiscalYearId,
                    ':versionId' => $versionId,
                    ':dataObjectCode' => $targetDataObjectCode,
                    ':rateCode' => $targetRateCode,
                ]);

                if ((int) $existsStmt->fetchColumn() > 0) {
                    $updateParams = $baseParams;
                    $updateParams[':updatedBy'] = $userId > 0 ? $userId : null;
                    $updateStmt->execute($updateParams);
                } else {
                    $insertParams = $baseParams;
                    $insertParams[':createdBy'] = $userId > 0 ? $userId : 1;
                    $insertStmt->execute($insertParams);
                }
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function resetScenarioOverrides(int $scenarioId): void
    {
        $this->ensureScenarioRateOverrideTable();

        $scenario = $this->findScenario($scenarioId);
        if ($scenario === null) {
            throw new \RuntimeException('Scenario not found.');
        }

        if ((int) ($scenario['ParentScenarioID'] ?? 0) <= 0) {
            throw new \RuntimeException('Only child scenarios can be reset.');
        }

        $deleteValuesStmt = $this->conn->prepare("
            DELETE FROM dbo.tblScenarioNodeValue
            WHERE ScenarioID = :scenarioId
        ");
        $deleteRateOverridesStmt = $this->conn->prepare("
            DELETE FROM dbo.tblCalcScenarioRateOverride
            WHERE ScenarioID = :scenarioId
        ");

        $this->conn->beginTransaction();
        try {
            $deleteValuesStmt->execute([':scenarioId' => $scenarioId]);
            $deleteRateOverridesStmt->execute([':scenarioId' => $scenarioId]);
            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function resetModelScenarios(int $calcModelId): void
    {
        $this->ensureScenarioRateOverrideTable();

        $calcModel = $this->findModel($calcModelId);
        if ($calcModel === null) {
            throw new \RuntimeException('Calculation model not found.');
        }

        $bridgeScenarioStmt = $this->conn->prepare("
            SELECT DISTINCT ScenarioID
            FROM dbo.tblCalcTransactionBridge
            WHERE CalcModelID = :modelId
              AND ScenarioID IS NOT NULL
        ");
        $bridgeScenarioStmt->execute([':modelId' => $calcModelId]);
        $bridgeScenarioIds = array_map('intval', $bridgeScenarioStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        $allScenarioStmt = $this->conn->prepare("
            SELECT ScenarioID
            FROM dbo.tblCalcScenario
            WHERE CalcModelID = :modelId
        ");
        $allScenarioStmt->execute([':modelId' => $calcModelId]);
        $allScenarioIds = array_map('intval', $allScenarioStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);

        if ($allScenarioIds === []) {
            throw new \RuntimeException('No scenarios were found for this model.');
        }

        if ($bridgeScenarioIds === []) {
            $bridgeScenarioIds = [(int) $allScenarioIds[0]];
        }

        $scenarioList = implode(',', array_map('intval', $allScenarioIds));
        $bridgeList = implode(',', array_map('intval', $bridgeScenarioIds));

        $this->conn->beginTransaction();
        try {
            $this->conn->exec("
                DELETE FROM dbo.tblCalcPublishedResult
                WHERE CalcModelID = {$calcModelId}
            ");
            $this->conn->exec("
                DELETE FROM dbo.tblCalcPublishEvent
                WHERE CalcModelID = {$calcModelId}
            ");
            $this->conn->exec("
                DELETE FROM dbo.tblCalcRunError
                WHERE CalcRunID IN (
                    SELECT CalcRunID
                    FROM dbo.tblCalcRun
                    WHERE CalcModelID = {$calcModelId}
                )
            ");
            $this->conn->exec("
                DELETE FROM dbo.tblCalcRunResult
                WHERE CalcRunID IN (
                    SELECT CalcRunID
                    FROM dbo.tblCalcRun
                    WHERE CalcModelID = {$calcModelId}
                )
            ");
            $this->conn->exec("
                DELETE FROM dbo.tblCalcRun
                WHERE CalcModelID = {$calcModelId}
            ");
            $this->conn->exec("
                DELETE FROM dbo.tblCalcScenarioRateOverride
                WHERE ScenarioID IN ({$scenarioList})
            ");
            $this->conn->exec("
                DELETE FROM dbo.tblScenarioNodeValue
                WHERE ScenarioID IN ({$scenarioList})
            ");
            $this->conn->exec("
                DELETE FROM dbo.tblCalcScenario
                WHERE CalcModelID = {$calcModelId}
                  AND ScenarioID NOT IN ({$bridgeList})
            ");
            $this->conn->exec("
                UPDATE dbo.tblCalcScenario
                SET ParentScenarioID = NULL,
                    ScenarioCode = N'BASE',
                    ScenarioName = N'Base Scenario',
                    ScenarioTypeCode = N'BASE',
                    ScenarioStatusCode = N'ACTIVE',
                    SortOrder = 1,
                    LockedFlag = 0,
                    ApprovedFlag = 0,
                    ActiveFlag = 1,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE CalcModelID = {$calcModelId}
                  AND ScenarioID IN ({$bridgeList})
            ");

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function syncLegacyFormulasForModel(int $calcModelId, int $userId): array
    {
        $calcModel = $this->findModel($calcModelId);
        if ($calcModel === null) {
            throw new \RuntimeException('Calculation model not found.');
        }

        $bridge = $this->findPrimaryBridgeForModel($calcModelId);
        if ($bridge === null) {
            throw new \RuntimeException('No active legacy transaction bridge was found for this model.');
        }

        $legacyCalculationId = (int) ($bridge['LegacyCalculationID'] ?? 0);
        $fiscalYearId = (int) ($bridge['FiscalYearID'] ?? 0);
        if ($legacyCalculationId <= 0 || $fiscalYearId <= 0) {
            throw new \RuntimeException('The bridge must define both FiscalYearID and LegacyCalculationID to sync formulas.');
        }

        $legacyFormulas = $this->loadLegacyFormulaMap($fiscalYearId, $legacyCalculationId);
        if ($legacyFormulas === []) {
            throw new \RuntimeException('No active legacy formulas were found for the linked calculation.');
        }

        $nodes = $this->listNodesByModel($calcModelId);
        $nodeMap = [];
        foreach ($nodes as $node) {
            $nodeCode = trim((string) ($node['NodeCode'] ?? ''));
            if ($nodeCode !== '') {
                $nodeMap[$nodeCode] = $node;
            }
        }

        $formulaByNodeId = [];
        foreach ($nodes as $node) {
            $nodeId = (int) ($node['NodeID'] ?? 0);
            if ($nodeId > 0) {
                $existing = $this->findFormulaByNodeId($nodeId);
                if ($existing !== null) {
                    $formulaByNodeId[$nodeId] = $existing;
                }
            }
        }

        $updateStmt = $this->conn->prepare("
            UPDATE dbo.tblCalcFormula
            SET ExpressionText = :expressionText,
                ExpressionHash = :expressionHash,
                ActiveFlag = 1,
                UpdatedBy = :updatedBy,
                UpdatedDate = SYSUTCDATETIME()
            WHERE NodeID = :nodeId
        ");
        $insertStmt = $this->conn->prepare("
            INSERT INTO dbo.tblCalcFormula
                (NodeID, ExpressionText, ExpressionHash, ExpressionLanguageCode, ParserVersion, ActiveFlag, CreatedBy)
            VALUES
                (:nodeId, :expressionText, :expressionHash, N'TOKEN_ARITH', N'v1', 1, :createdBy)
        ");

        $updated = 0;
        $this->conn->beginTransaction();
        try {
            foreach ($legacyFormulas as $periodCode => $legacyExpression) {
                if (!isset($nodeMap[$periodCode])) {
                    continue;
                }

                $node = $nodeMap[$periodCode];
                $nodeId = (int) ($node['NodeID'] ?? 0);
                if ($nodeId <= 0) {
                    continue;
                }

                $translated = $this->translateLegacyFormulaToScenarioExpression($periodCode, $legacyExpression, $nodeMap);
                $hash = hash('sha256', $translated);

                if (isset($formulaByNodeId[$nodeId])) {
                    $updateStmt->execute([
                        ':expressionText' => $translated,
                        ':expressionHash' => $hash,
                        ':updatedBy' => $userId > 0 ? $userId : null,
                        ':nodeId' => $nodeId,
                    ]);
                } else {
                    $insertStmt->execute([
                        ':nodeId' => $nodeId,
                        ':expressionText' => $translated,
                        ':expressionHash' => $hash,
                        ':createdBy' => $userId > 0 ? $userId : 1,
                    ]);
                }

                $updated++;
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }

        return [
            'updated' => $updated,
            'legacyCalculationId' => $legacyCalculationId,
            'fiscalYearId' => $fiscalYearId,
        ];
    }

    public function syncLegacyFormulaChainForModel(int $calcModelId, int $userId): array
    {
        $calcModel = $this->findModel($calcModelId);
        if ($calcModel === null) {
            throw new \RuntimeException('Calculation model not found.');
        }

        $bridge = $this->findPrimaryBridgeForModel($calcModelId);
        if ($bridge === null) {
            throw new \RuntimeException('No active legacy transaction bridge was found for this model.');
        }

        $rootLegacyCalculationId = (int) ($bridge['LegacyCalculationID'] ?? 0);
        $fiscalYearId = (int) ($bridge['FiscalYearID'] ?? 0);
        if ($rootLegacyCalculationId <= 0 || $fiscalYearId <= 0) {
            throw new \RuntimeException('The bridge must define both FiscalYearID and LegacyCalculationID to sync the legacy chain.');
        }

        $chain = $this->loadLegacyCalculationChain($fiscalYearId, $rootLegacyCalculationId);
        if ($chain === []) {
            throw new \RuntimeException('No legacy calculation chain was found for this model.');
        }

        $modelByLegacyCalculationId = $this->loadModelsByLegacyCalculationId(array_column($chain, 'CalculationID'));
        $updatedFormulas = 0;
        $updatedModels = 0;

        foreach ($chain as $step) {
            $legacyCalculationId = (int) ($step['CalculationID'] ?? 0);
            if ($legacyCalculationId <= 0 || !isset($modelByLegacyCalculationId[$legacyCalculationId])) {
                continue;
            }

            $linkedModelId = (int) ($modelByLegacyCalculationId[$legacyCalculationId]['CalcModelID'] ?? 0);
            if ($linkedModelId <= 0) {
                continue;
            }

            $summary = $this->syncLegacyFormulasForModel($linkedModelId, $userId);
            $updatedFormulas += (int) ($summary['updated'] ?? 0);
            $updatedModels++;
        }

        return [
            'updated_formulas' => $updatedFormulas,
            'updated_models' => $updatedModels,
            'root_legacy_calculation_id' => $rootLegacyCalculationId,
            'fiscal_year_id' => $fiscalYearId,
        ];
    }

    public function saveScenario(array $data, int $userId): int
    {
        $id = (int) ($data['ScenarioID'] ?? 0);

        if ($id > 0) {
            $sql = "
                UPDATE dbo.tblCalcScenario
                SET CalcModelID = :CalcModelID,
                    ParentScenarioID = :ParentScenarioID,
                    ScenarioCode = :ScenarioCode,
                    ScenarioName = :ScenarioName,
                    ScenarioTypeCode = :ScenarioTypeCode,
                    ScenarioStatusCode = :ScenarioStatusCode,
                    SortOrder = :SortOrder,
                    LockedFlag = :LockedFlag,
                    ApprovedFlag = :ApprovedFlag,
                    ActiveFlag = :ActiveFlag,
                    UpdatedBy = :UpdatedBy,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE ScenarioID = :ScenarioID
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':ScenarioID' => $id,
                ':CalcModelID' => $data['CalcModelID'],
                ':ParentScenarioID' => $data['ParentScenarioID'],
                ':ScenarioCode' => $data['ScenarioCode'],
                ':ScenarioName' => $data['ScenarioName'],
                ':ScenarioTypeCode' => $data['ScenarioTypeCode'],
                ':ScenarioStatusCode' => $data['ScenarioStatusCode'],
                ':SortOrder' => $data['SortOrder'],
                ':LockedFlag' => $data['LockedFlag'],
                ':ApprovedFlag' => $data['ApprovedFlag'],
                ':ActiveFlag' => $data['ActiveFlag'],
                ':UpdatedBy' => $userId > 0 ? $userId : null,
            ]);

            return $id;
        }

        $sql = "
            INSERT INTO dbo.tblCalcScenario
                (CalcModelID, ParentScenarioID, ScenarioCode, ScenarioName, ScenarioTypeCode, ScenarioStatusCode, SortOrder, LockedFlag, ApprovedFlag, ActiveFlag, CreatedBy)
            VALUES
                (:CalcModelID, :ParentScenarioID, :ScenarioCode, :ScenarioName, :ScenarioTypeCode, :ScenarioStatusCode, :SortOrder, :LockedFlag, :ApprovedFlag, :ActiveFlag, :CreatedBy)
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':CalcModelID' => $data['CalcModelID'],
            ':ParentScenarioID' => $data['ParentScenarioID'],
            ':ScenarioCode' => $data['ScenarioCode'],
            ':ScenarioName' => $data['ScenarioName'],
            ':ScenarioTypeCode' => $data['ScenarioTypeCode'],
            ':ScenarioStatusCode' => $data['ScenarioStatusCode'],
            ':SortOrder' => $data['SortOrder'],
            ':LockedFlag' => $data['LockedFlag'],
            ':ApprovedFlag' => $data['ApprovedFlag'],
            ':ActiveFlag' => $data['ActiveFlag'],
            ':CreatedBy' => $userId > 0 ? $userId : 1,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    public function listNodesByModel(int $calcModelId): array
    {
        $sql = "
            SELECT
                n.NodeID,
                n.CalcModelID,
                n.NodeCode,
                n.NodeName,
                n.NodeTypeCode,
                n.NodeCategoryCode,
                n.DataTypeCode,
                n.UnitOfMeasureCode,
                n.DecimalScale,
                n.NodeOrder,
                n.OutputFlag,
                n.ActiveFlag,
                f.ExpressionText,
                f.ActiveFlag AS FormulaActiveFlag,
                ISNULL(dep.DependencyCount, 0) AS DependencyCount
            FROM dbo.tblCalcNode n
            LEFT JOIN dbo.tblCalcFormula f
                ON f.NodeID = n.NodeID
            LEFT JOIN (
                SELECT NodeID, COUNT(*) AS DependencyCount
                FROM dbo.tblCalcDependency
                GROUP BY NodeID
            ) dep ON dep.NodeID = n.NodeID
            WHERE n.CalcModelID = :id
            ORDER BY n.NodeOrder, n.NodeCode
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findNode(int $nodeId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblCalcNode
            WHERE NodeID = :id
        ");
        $stmt->execute([':id' => $nodeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findFormulaByNodeId(int $nodeId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblCalcFormula
            WHERE NodeID = :id
        ");
        $stmt->execute([':id' => $nodeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listDependenciesByNode(int $nodeId): array
    {
        $sql = "
            SELECT
                d.CalcDependencyID,
                d.DependencyTypeCode,
                d.OffsetPeriods,
                d.RequiredFlag,
                d.SortOrder,
                src.NodeCode AS DependsOnNodeCode,
                src.NodeName AS DependsOnNodeName
            FROM dbo.tblCalcDependency d
            INNER JOIN dbo.tblCalcNode src
                ON src.NodeID = d.DependsOnNodeID
            WHERE d.NodeID = :id
            ORDER BY d.SortOrder, src.NodeCode
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $nodeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listDependenciesByModel(int $calcModelId): array
    {
        $sql = "
            SELECT
                d.CalcDependencyID,
                target.NodeID,
                target.NodeCode,
                target.NodeName,
                src.NodeCode AS DependsOnNodeCode,
                src.NodeName AS DependsOnNodeName,
                d.DependencyTypeCode,
                d.OffsetPeriods,
                d.RequiredFlag,
                d.SortOrder
            FROM dbo.tblCalcDependency d
            INNER JOIN dbo.tblCalcNode target
                ON target.NodeID = d.NodeID
            INNER JOIN dbo.tblCalcNode src
                ON src.NodeID = d.DependsOnNodeID
            WHERE target.CalcModelID = :id
            ORDER BY target.NodeOrder, target.NodeCode, d.SortOrder, src.NodeCode
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $calcModelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findPrimaryBridgeForModel(int $calcModelId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT TOP 1
                CalcTransactionBridgeID,
                CalcModelID,
                ScenarioID,
                LegacyCalculationID,
                FiscalYearID,
                VersionID,
                PriorityNo,
                ActiveFlag
            FROM dbo.tblCalcTransactionBridge
            WHERE CalcModelID = :modelId
              AND ActiveFlag = 1
              AND LegacyCalculationID IS NOT NULL
            ORDER BY PriorityNo, CalcTransactionBridgeID
        ");
        $stmt->execute([':modelId' => $calcModelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function loadLegacyCalculationChain(int $fiscalYearId, int $rootCalculationId): array
    {
        $stmt = $this->conn->prepare("
            WITH CalcChain AS (
                SELECT
                    FiscalYearID,
                    CalculationID,
                    ChildCalculationID,
                    CalculationName,
                    0 AS Depth
                FROM dbo.tblCalculations
                WHERE FiscalYearID = :fiscalYearId
                  AND CalculationID = :rootCalculationId

                UNION ALL

                SELECT
                    c.FiscalYearID,
                    c.CalculationID,
                    c.ChildCalculationID,
                    c.CalculationName,
                    chain.Depth + 1
                FROM dbo.tblCalculations c
                INNER JOIN CalcChain chain
                    ON c.FiscalYearID = chain.FiscalYearID
                   AND c.CalculationID = chain.ChildCalculationID
                WHERE chain.ChildCalculationID IS NOT NULL
                  AND chain.ChildCalculationID > 0
            )
            SELECT FiscalYearID, CalculationID, ChildCalculationID, CalculationName, Depth
            FROM CalcChain
            ORDER BY Depth, CalculationID
        ");
        $stmt->execute([
            ':fiscalYearId' => $fiscalYearId,
            ':rootCalculationId' => $rootCalculationId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function loadModelsByLegacyCalculationId(array $legacyCalculationIds): array
    {
        $legacyCalculationIds = array_values(array_unique(array_filter(array_map('intval', $legacyCalculationIds), static fn(int $value): bool => $value > 0)));
        if ($legacyCalculationIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($legacyCalculationIds as $index => $legacyCalculationId) {
            $placeholder = ':calc' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $legacyCalculationId;
        }

        $sql = "
            SELECT
                b.LegacyCalculationID,
                b.CalcModelID,
                m.ModelCode
            FROM dbo.tblCalcTransactionBridge b
            INNER JOIN dbo.tblCalcModel m
                ON m.CalcModelID = b.CalcModelID
            WHERE b.ActiveFlag = 1
              AND b.LegacyCalculationID IN (" . implode(', ', $placeholders) . ")
            ORDER BY b.PriorityNo, b.CalcTransactionBridgeID
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $legacyCalculationId = (int) ($row['LegacyCalculationID'] ?? 0);
            if ($legacyCalculationId > 0 && !isset($map[$legacyCalculationId])) {
                $map[$legacyCalculationId] = $row;
            }
        }

        return $map;
    }

    private function loadLegacyFormulaMap(int $fiscalYearId, int $legacyCalculationId): array
    {
        $stmt = $this->conn->prepare("
            SELECT PeriodCode, FormulaText
            FROM dbo.tblCalculationFormulas
            WHERE FiscalYearID = :fiscalYearId
              AND CalculationID = :calculationId
              AND ISNULL(ActiveFlag, 1) = 1
            ORDER BY PeriodCode
        ");
        $stmt->execute([
            ':fiscalYearId' => $fiscalYearId,
            ':calculationId' => $legacyCalculationId,
        ]);

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $periodCode = trim((string) ($row['PeriodCode'] ?? ''));
            $formulaText = trim((string) ($row['FormulaText'] ?? ''));
            if ($periodCode !== '' && $formulaText !== '') {
                $map[$periodCode] = $formulaText;
            }
        }

        return $map;
    }

    private function translateLegacyFormulaToScenarioExpression(string $nodeCode, string $expression, array $nodeMap): string
    {
        return (string) preg_replace_callback(
            '/@([A-Za-z0-9_]+)@/',
            function (array $matches) use ($nodeCode, $nodeMap): string {
                $token = (string) ($matches[1] ?? '');
                if ($token === '') {
                    return $matches[0];
                }

                if (isset($nodeMap[$token])) {
                    return '@' . $token . '@';
                }

                if (preg_match('/^(BP\d+|BPOY\d+|BPQ\d+)InpN$/i', $token, $tokenMatch) === 1) {
                    $candidate = $tokenMatch[1];
                    if (isset($nodeMap[$candidate])) {
                        return '@' . $candidate . '@';
                    }
                }

                if (strcasecmp($token, 'Inflation') === 0 && preg_match('/^BPOY(\d+)$/i', $nodeCode, $periodMatch) === 1) {
                    $candidate = 'InflationOY' . $periodMatch[1];
                    if (isset($nodeMap[$candidate])) {
                        return '@' . $candidate . '@';
                    }
                }

                return '@' . $token . '@';
            },
            $expression
        );
    }

    private function listEditableNodesByModel(int $calcModelId, string $search = ''): array
    {
        $sql = "
            SELECT
                NodeID,
                CalcModelID,
                NodeCode,
                NodeName,
                NodeTypeCode,
                NodeCategoryCode,
                DataTypeCode,
                UnitOfMeasureCode,
                DecimalScale,
                ActiveFlag
            FROM dbo.tblCalcNode
            WHERE CalcModelID = :modelId
              AND ActiveFlag = 1
              AND NodeTypeCode = N'INPUT'
        ";

        $params = [':modelId' => $calcModelId];
        $search = trim($search);
        if ($search !== '') {
            $sql .= "
              AND (
                    NodeCode LIKE :search
                 OR NodeName LIKE :search
              )
            ";
            $params[':search'] = '%' . $search . '%';
        }

        $sql .= " ORDER BY NodeOrder, NodeCode";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function listRateOverrideContextsByScenario(int $scenarioId): array
    {
        $sql = "
            SELECT DISTINCT
                bridge.FiscalYearID,
                bridge.VersionID,
                rates.DataObjectCode,
                rates.RateCode
            FROM dbo.tblCalcScenario scenario
            INNER JOIN dbo.tblCalcTransactionBridge bridge
                ON bridge.CalcModelID = scenario.CalcModelID
               AND bridge.ActiveFlag = 1
            INNER JOIN dbo.tblRates rates
                ON rates.FiscalYearID = bridge.FiscalYearID
               AND rates.VersionID = bridge.VersionID
            WHERE scenario.ScenarioID = :scenarioId
              AND bridge.FiscalYearID IS NOT NULL
              AND bridge.VersionID IS NOT NULL
            ORDER BY rates.RateCode, rates.DataObjectCode
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':scenarioId' => $scenarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function loadRateOverrideEditorRows(int $scenarioId, array $contexts, string $rateCode, string $dataObjectCode): array
    {
        $rateCode = trim($rateCode);
        $dataObjectCode = trim($dataObjectCode);
        $filteredContexts = array_values(array_filter($contexts, static function (array $row) use ($rateCode, $dataObjectCode): bool {
            if ($rateCode !== '' && stripos((string) ($row['RateCode'] ?? ''), $rateCode) === false) {
                return false;
            }
            if ($dataObjectCode !== '' && stripos((string) ($row['DataObjectCode'] ?? ''), $dataObjectCode) === false) {
                return false;
            }
            return true;
        }));

        if ($filteredContexts === []) {
            return [];
        }

        $rows = [];
        foreach ($filteredContexts as $context) {
            $baseRow = $this->loadBaseRateRow(
                (int) ($context['FiscalYearID'] ?? 0),
                (int) ($context['VersionID'] ?? 0),
                (string) ($context['DataObjectCode'] ?? ''),
                (string) ($context['RateCode'] ?? '')
            );
            if ($baseRow === null) {
                continue;
            }

            $effectiveOverride = $this->loadEffectiveScenarioRateOverride(
                $scenarioId,
                (int) ($context['FiscalYearID'] ?? 0),
                (int) ($context['VersionID'] ?? 0),
                (string) ($context['DataObjectCode'] ?? ''),
                (string) ($context['RateCode'] ?? '')
            );
            $currentOverride = $this->loadDirectScenarioRateOverride(
                $scenarioId,
                (int) ($context['FiscalYearID'] ?? 0),
                (int) ($context['VersionID'] ?? 0),
                (string) ($context['DataObjectCode'] ?? ''),
                (string) ($context['RateCode'] ?? '')
            );

            $row = [
                'FiscalYearID' => (int) ($context['FiscalYearID'] ?? 0),
                'VersionID' => (int) ($context['VersionID'] ?? 0),
                'DataObjectCode' => (string) ($context['DataObjectCode'] ?? ''),
                'RateCode' => (string) ($context['RateCode'] ?? ''),
                'base' => [],
                'effective' => [],
                'current' => [],
                'sourceScenarioCode' => $effectiveOverride['ScenarioCode'] ?? null,
                'sourceScenarioName' => $effectiveOverride['ScenarioName'] ?? null,
                'isInherited' => $effectiveOverride !== null && $currentOverride === null,
            ];

            foreach (self::RATE_COLUMNS as $column) {
                $row['base'][$column] = $this->formatRateValue($baseRow[$column] ?? null);
                $row['effective'][$column] = $this->formatRateValue($effectiveOverride[$column] ?? ($baseRow[$column] ?? null));
                $row['current'][$column] = $this->formatRateValue($currentOverride[$column] ?? null);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function loadBaseRateRow(int $fiscalYearId, int $versionId, string $dataObjectCode, string $rateCode): ?array
    {
        $columns = implode(', ', array_map(static fn(string $column): string => '[' . $column . ']', self::RATE_COLUMNS));
        $stmt = $this->conn->prepare("
            SELECT {$columns}
            FROM dbo.tblRates
            WHERE FiscalYearID = :fiscalYearId
              AND VersionID = :versionId
              AND DataObjectCode = :dataObjectCode
              AND RateCode = :rateCode
        ");
        $stmt->execute([
            ':fiscalYearId' => $fiscalYearId,
            ':versionId' => $versionId,
            ':dataObjectCode' => $dataObjectCode,
            ':rateCode' => $rateCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function loadDirectScenarioRateOverride(int $scenarioId, int $fiscalYearId, int $versionId, string $dataObjectCode, string $rateCode): ?array
    {
        $columns = implode(', ', array_map(static fn(string $column): string => '[' . $column . ']', self::RATE_COLUMNS));
        $stmt = $this->conn->prepare("
            SELECT {$columns}
            FROM dbo.tblCalcScenarioRateOverride
            WHERE ScenarioID = :scenarioId
              AND FiscalYearID = :fiscalYearId
              AND VersionID = :versionId
              AND DataObjectCode = :dataObjectCode
              AND RateCode = :rateCode
        ");
        $stmt->execute([
            ':scenarioId' => $scenarioId,
            ':fiscalYearId' => $fiscalYearId,
            ':versionId' => $versionId,
            ':dataObjectCode' => $dataObjectCode,
            ':rateCode' => $rateCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function loadEffectiveScenarioRateOverride(int $scenarioId, int $fiscalYearId, int $versionId, string $dataObjectCode, string $rateCode): ?array
    {
        $columns = implode(', ', array_map(static fn(string $column): string => 'override.[' . $column . ']', self::RATE_COLUMNS));
        $stmt = $this->conn->prepare("
            WITH ScenarioLineage AS (
                SELECT
                    s.ScenarioID,
                    s.ParentScenarioID,
                    s.ScenarioCode,
                    s.ScenarioName,
                    CAST(0 AS INT) AS Depth
                FROM dbo.tblCalcScenario s
                WHERE s.ScenarioID = :scenarioId

                UNION ALL

                SELECT
                    parent.ScenarioID,
                    parent.ParentScenarioID,
                    parent.ScenarioCode,
                    parent.ScenarioName,
                    line.Depth + 1
                FROM dbo.tblCalcScenario parent
                INNER JOIN ScenarioLineage line
                    ON parent.ScenarioID = line.ParentScenarioID
            )
            SELECT TOP 1
                line.ScenarioCode,
                line.ScenarioName,
                {$columns}
            FROM ScenarioLineage line
            INNER JOIN dbo.tblCalcScenarioRateOverride override
                ON override.ScenarioID = line.ScenarioID
            WHERE override.FiscalYearID = :fiscalYearId
              AND override.VersionID = :versionId
              AND override.DataObjectCode = :dataObjectCode
              AND override.RateCode = :rateCode
            ORDER BY line.Depth
        ");
        $stmt->execute([
            ':scenarioId' => $scenarioId,
            ':fiscalYearId' => $fiscalYearId,
            ':versionId' => $versionId,
            ':dataObjectCode' => $dataObjectCode,
            ':rateCode' => $rateCode,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function ensureScenarioRateOverrideTable(): void
    {
        $sql = "
IF OBJECT_ID('dbo.tblCalcScenarioRateOverride', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.tblCalcScenarioRateOverride (
        CalcScenarioRateOverrideID INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        ScenarioID INT NOT NULL,
        FiscalYearID INT NOT NULL,
        VersionID INT NOT NULL,
        DataObjectCode NVARCHAR(20) NOT NULL,
        RateCode NVARCHAR(50) NOT NULL,
        BPRate DECIMAL(19,6) NULL,
        BP1Rate DECIMAL(19,6) NULL,
        BP2Rate DECIMAL(19,6) NULL,
        BP3Rate DECIMAL(19,6) NULL,
        BP4Rate DECIMAL(19,6) NULL,
        BP5Rate DECIMAL(19,6) NULL,
        BP6Rate DECIMAL(19,6) NULL,
        BP7Rate DECIMAL(19,6) NULL,
        BP8Rate DECIMAL(19,6) NULL,
        BP9Rate DECIMAL(19,6) NULL,
        BP10Rate DECIMAL(19,6) NULL,
        BP11Rate DECIMAL(19,6) NULL,
        BP12Rate DECIMAL(19,6) NULL,
        OY1Rate DECIMAL(19,6) NULL,
        OY2Rate DECIMAL(19,6) NULL,
        OY3Rate DECIMAL(19,6) NULL,
        OY4Rate DECIMAL(19,6) NULL,
        OY5Rate DECIMAL(19,6) NULL,
        OY6Rate DECIMAL(19,6) NULL,
        OY7Rate DECIMAL(19,6) NULL,
        OY8Rate DECIMAL(19,6) NULL,
        OY9Rate DECIMAL(19,6) NULL,
        OY10Rate DECIMAL(19,6) NULL,
        CreatedBy INT NOT NULL CONSTRAINT DF_tblCalcScenarioRateOverride_CreatedBy DEFAULT (1),
        CreatedDate DATETIME2(0) NOT NULL CONSTRAINT DF_tblCalcScenarioRateOverride_CreatedDate DEFAULT (SYSDATETIME()),
        UpdatedBy INT NULL,
        UpdatedDate DATETIME2(0) NULL
    );
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.foreign_keys
    WHERE name = 'FK_tblCalcScenarioRateOverride_tblCalcScenario'
      AND parent_object_id = OBJECT_ID('dbo.tblCalcScenarioRateOverride')
)
BEGIN
    ALTER TABLE dbo.tblCalcScenarioRateOverride
        ADD CONSTRAINT FK_tblCalcScenarioRateOverride_tblCalcScenario
        FOREIGN KEY (ScenarioID) REFERENCES dbo.tblCalcScenario (ScenarioID) ON DELETE CASCADE;
END;

IF NOT EXISTS (
    SELECT 1
    FROM sys.indexes
    WHERE object_id = OBJECT_ID('dbo.tblCalcScenarioRateOverride')
      AND name = 'UX_tblCalcScenarioRateOverride_Key'
)
BEGIN
    CREATE UNIQUE NONCLUSTERED INDEX UX_tblCalcScenarioRateOverride_Key
        ON dbo.tblCalcScenarioRateOverride (ScenarioID, FiscalYearID, VersionID, DataObjectCode, RateCode);
END;
";
        $this->conn->exec($sql);
    }

    private function rateContextKey(int $fiscalYearId, int $versionId, string $dataObjectCode, string $rateCode): string
    {
        return $fiscalYearId . '|' . $versionId . '|' . strtoupper(trim($dataObjectCode)) . '|' . strtoupper(trim($rateCode));
    }

    private function nullableRateDecimal(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            throw new \RuntimeException('One or more entered rate overrides are not valid numbers.');
        }
        return (float) $value;
    }

    private function formatRateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = (string) $value;
        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return $text === '' ? '0' : $text;
    }

    private function loadScenarioValueMatrix(int $scenarioId, int $costObjectId, array $nodes, array $periods): array
    {
        $values = [];
        foreach ($nodes as $node) {
            $nodeId = (int) ($node['NodeID'] ?? 0);
            foreach ($periods as $period) {
                $periodId = (int) ($period['PeriodID'] ?? 0);
                $values[$nodeId][$periodId] = [
                    'current' => null,
                    'effective' => null,
                    'sourceScenarioCode' => null,
                    'sourceScenarioName' => null,
                    'isInherited' => false,
                ];
            }
        }

        if ($nodes === [] || $periods === [] || $costObjectId <= 0) {
            return $values;
        }

        $nodeIds = array_values(array_map(static fn(array $row): int => (int) $row['NodeID'], $nodes));
        $periodIds = array_values(array_map(static fn(array $row): int => (int) $row['PeriodID'], $periods));

        $nodePlaceholders = implode(',', array_fill(0, count($nodeIds), '?'));
        $periodPlaceholders = implode(',', array_fill(0, count($periodIds), '?'));

        $lineageSql = "
            WITH ScenarioLineage AS (
                SELECT
                    s.ScenarioID,
                    s.ParentScenarioID,
                    s.ScenarioCode,
                    s.ScenarioName,
                    CAST(0 AS INT) AS Depth
                FROM dbo.tblCalcScenario s
                WHERE s.ScenarioID = ?

                UNION ALL

                SELECT
                    parent.ScenarioID,
                    parent.ParentScenarioID,
                    parent.ScenarioCode,
                    parent.ScenarioName,
                    line.Depth + 1
                FROM dbo.tblCalcScenario parent
                INNER JOIN ScenarioLineage line
                    ON parent.ScenarioID = line.ParentScenarioID
            )
            SELECT
                line.Depth,
                line.ScenarioID,
                line.ScenarioCode,
                line.ScenarioName,
                v.NodeID,
                v.PeriodID,
                v.ValueDecimal,
                v.ValueText,
                v.ValueBit
            FROM ScenarioLineage line
            INNER JOIN dbo.tblScenarioNodeValue v
                ON v.ScenarioID = line.ScenarioID
            WHERE v.CostObjectID = ?
              AND v.NodeID IN ({$nodePlaceholders})
              AND v.PeriodID IN ({$periodPlaceholders})
            ORDER BY v.NodeID, v.PeriodID, line.Depth
        ";

        $params = array_merge([$scenarioId, $costObjectId], $nodeIds, $periodIds);
        $stmt = $this->conn->prepare($lineageSql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $nodeId = (int) ($row['NodeID'] ?? 0);
            $periodId = (int) ($row['PeriodID'] ?? 0);
            if (!isset($values[$nodeId][$periodId])) {
                continue;
            }

            $formatted = $this->formatScenarioValueForEditor($row);
            if ($formatted === null) {
                continue;
            }

            if ($values[$nodeId][$periodId]['effective'] === null) {
                $values[$nodeId][$periodId]['effective'] = $formatted;
                $values[$nodeId][$periodId]['sourceScenarioCode'] = (string) ($row['ScenarioCode'] ?? '');
                $values[$nodeId][$periodId]['sourceScenarioName'] = (string) ($row['ScenarioName'] ?? '');
                $values[$nodeId][$periodId]['isInherited'] = ((int) ($row['Depth'] ?? 0) > 0);
            }

            if ((int) ($row['Depth'] ?? 0) === 0) {
                $values[$nodeId][$periodId]['current'] = $formatted;
                $values[$nodeId][$periodId]['isInherited'] = false;
            }
        }

        return $values;
    }

    private function formatScenarioValueForEditor(array $row): ?string
    {
        if (array_key_exists('ValueDecimal', $row) && $row['ValueDecimal'] !== null) {
            $raw = rtrim(rtrim((string) $row['ValueDecimal'], '0'), '.');
            return $raw === '' ? '0' : $raw;
        }

        if (array_key_exists('ValueText', $row) && $row['ValueText'] !== null && $row['ValueText'] !== '') {
            return (string) $row['ValueText'];
        }

        if (array_key_exists('ValueBit', $row) && $row['ValueBit'] !== null) {
            return ((int) $row['ValueBit'] === 1) ? '1' : '0';
        }

        return null;
    }

    private function normalizeScenarioValueForSave(mixed $rawValue, string $dataTypeCode): array
    {
        $dataTypeCode = strtoupper(trim($dataTypeCode));
        $value = trim((string) $rawValue);
        if ($value === '') {
            return [
                'delete' => true,
                'ValueDecimal' => null,
                'ValueText' => null,
                'ValueBit' => null,
            ];
        }

        if (in_array($dataTypeCode, ['BOOLEAN', 'BIT'], true)) {
            $bit = in_array(strtolower($value), ['1', 'true', 'yes', 'y', 'on'], true) ? 1 : 0;
            return [
                'delete' => false,
                'ValueDecimal' => null,
                'ValueText' => null,
                'ValueBit' => $bit,
            ];
        }

        if (in_array($dataTypeCode, ['TEXT', 'STRING'], true)) {
            return [
                'delete' => false,
                'ValueDecimal' => null,
                'ValueText' => $value,
                'ValueBit' => null,
            ];
        }

        if (!is_numeric($value)) {
            throw new \RuntimeException('One or more entered values are not valid numbers.');
        }

        return [
            'delete' => false,
            'ValueDecimal' => (float) $value,
            'ValueText' => null,
            'ValueBit' => null,
        ];
    }

    public function saveNode(array $data, int $userId): int
    {
        $nodeId = (int) ($data['NodeID'] ?? 0);
        $expressionText = trim((string) ($data['ExpressionText'] ?? ''));
        $formulaActiveFlag = (int) ($data['FormulaActiveFlag'] ?? 1);

        try {
            $this->conn->beginTransaction();

            if ($nodeId > 0) {
                $sql = "
                    UPDATE dbo.tblCalcNode
                    SET CalcModelID = :CalcModelID,
                        NodeCode = :NodeCode,
                        NodeName = :NodeName,
                        NodeTypeCode = :NodeTypeCode,
                        NodeCategoryCode = :NodeCategoryCode,
                        DataTypeCode = :DataTypeCode,
                        UnitOfMeasureCode = :UnitOfMeasureCode,
                        DecimalScale = :DecimalScale,
                        DefaultDecimalValue = :DefaultDecimalValue,
                        DefaultTextValue = :DefaultTextValue,
                        DefaultBitValue = :DefaultBitValue,
                        NodeOrder = :NodeOrder,
                        OutputFlag = :OutputFlag,
                        ActiveFlag = :ActiveFlag,
                        UpdatedBy = :UpdatedBy,
                        UpdatedDate = SYSUTCDATETIME()
                    WHERE NodeID = :NodeID
                ";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    ':NodeID' => $nodeId,
                    ':CalcModelID' => $data['CalcModelID'],
                    ':NodeCode' => $data['NodeCode'],
                    ':NodeName' => $data['NodeName'],
                    ':NodeTypeCode' => $data['NodeTypeCode'],
                    ':NodeCategoryCode' => $data['NodeCategoryCode'],
                    ':DataTypeCode' => $data['DataTypeCode'],
                    ':UnitOfMeasureCode' => $data['UnitOfMeasureCode'],
                    ':DecimalScale' => $data['DecimalScale'],
                    ':DefaultDecimalValue' => $data['DefaultDecimalValue'],
                    ':DefaultTextValue' => $data['DefaultTextValue'],
                    ':DefaultBitValue' => $data['DefaultBitValue'],
                    ':NodeOrder' => $data['NodeOrder'],
                    ':OutputFlag' => $data['OutputFlag'],
                    ':ActiveFlag' => $data['ActiveFlag'],
                    ':UpdatedBy' => $userId > 0 ? $userId : null,
                ]);
            } else {
                $sql = "
                    INSERT INTO dbo.tblCalcNode
                        (CalcModelID, NodeCode, NodeName, NodeTypeCode, NodeCategoryCode, DataTypeCode, UnitOfMeasureCode, DecimalScale, DefaultDecimalValue, DefaultTextValue, DefaultBitValue, NodeOrder, OutputFlag, ActiveFlag, CreatedBy)
                    VALUES
                        (:CalcModelID, :NodeCode, :NodeName, :NodeTypeCode, :NodeCategoryCode, :DataTypeCode, :UnitOfMeasureCode, :DecimalScale, :DefaultDecimalValue, :DefaultTextValue, :DefaultBitValue, :NodeOrder, :OutputFlag, :ActiveFlag, :CreatedBy)
                ";

                $stmt = $this->conn->prepare($sql);
                $stmt->execute([
                    ':CalcModelID' => $data['CalcModelID'],
                    ':NodeCode' => $data['NodeCode'],
                    ':NodeName' => $data['NodeName'],
                    ':NodeTypeCode' => $data['NodeTypeCode'],
                    ':NodeCategoryCode' => $data['NodeCategoryCode'],
                    ':DataTypeCode' => $data['DataTypeCode'],
                    ':UnitOfMeasureCode' => $data['UnitOfMeasureCode'],
                    ':DecimalScale' => $data['DecimalScale'],
                    ':DefaultDecimalValue' => $data['DefaultDecimalValue'],
                    ':DefaultTextValue' => $data['DefaultTextValue'],
                    ':DefaultBitValue' => $data['DefaultBitValue'],
                    ':NodeOrder' => $data['NodeOrder'],
                    ':OutputFlag' => $data['OutputFlag'],
                    ':ActiveFlag' => $data['ActiveFlag'],
                    ':CreatedBy' => $userId > 0 ? $userId : 1,
                ]);

                $nodeId = (int) $this->conn->lastInsertId();
            }

            $existingFormula = $this->findFormulaByNodeId($nodeId);

            if ($expressionText !== '') {
                $hash = hash('sha256', $expressionText);

                if ($existingFormula !== null) {
                    $sql = "
                        UPDATE dbo.tblCalcFormula
                        SET ExpressionText = :ExpressionText,
                            ExpressionHash = :ExpressionHash,
                            ActiveFlag = :ActiveFlag,
                            UpdatedBy = :UpdatedBy,
                            UpdatedDate = SYSUTCDATETIME()
                        WHERE NodeID = :NodeID
                    ";

                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([
                        ':ExpressionText' => $expressionText,
                        ':ExpressionHash' => $hash,
                        ':ActiveFlag' => $formulaActiveFlag,
                        ':UpdatedBy' => $userId > 0 ? $userId : null,
                        ':NodeID' => $nodeId,
                    ]);
                } else {
                    $sql = "
                        INSERT INTO dbo.tblCalcFormula
                            (NodeID, ExpressionText, ExpressionHash, ExpressionLanguageCode, ParserVersion, ActiveFlag, CreatedBy)
                        VALUES
                            (:NodeID, :ExpressionText, :ExpressionHash, N'TOKEN_ARITH', N'v1', :ActiveFlag, :CreatedBy)
                    ";

                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([
                        ':NodeID' => $nodeId,
                        ':ExpressionText' => $expressionText,
                        ':ExpressionHash' => $hash,
                        ':ActiveFlag' => $formulaActiveFlag,
                        ':CreatedBy' => $userId > 0 ? $userId : 1,
                    ]);
                }
            } elseif ($existingFormula !== null) {
                $stmt = $this->conn->prepare("
                    DELETE FROM dbo.tblCalcFormula
                    WHERE NodeID = :NodeID
                ");
                $stmt->execute([':NodeID' => $nodeId]);
            }

            $this->conn->commit();
            return $nodeId;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $this->lastError = $e->getMessage();
            throw $e;
        }
    }
}
