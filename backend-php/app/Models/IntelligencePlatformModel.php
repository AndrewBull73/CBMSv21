<?php
declare(strict_types=1);

namespace App\Models;

use App\Shared\SessionHelper;
use PDO;

final class IntelligencePlatformModel
{
    public function __construct(private PDO $conn)
    {
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsIntelligencePlatform(): bool
    {
        return $this->tableExists('dbo.tblIntelligenceEngineRuns')
            && $this->tableExists('dbo.tblIntelligenceForecasts')
            && $this->tableExists('dbo.tblIntelligenceScenarios')
            && $this->tableExists('dbo.tblIntelligenceInsights')
            && $this->tableExists('dbo.tblAIProviders')
            && $this->tableExists('dbo.tblAIModuleSettings');
    }

    public function supportsAnalyticsOutputs(): bool
    {
        return $this->tableExists('dbo.tblAnalyticsRuns')
            && $this->tableExists('dbo.tblAnalyticsRunResults')
            && $this->tableExists('dbo.tblAnalyticsFindings')
            && $this->tableExists('dbo.tblAnalyticsFeatureSignals');
    }

    public function summary(): array
    {
        if (!$this->supportsIntelligencePlatform()) {
            return [
                'run_count_7d' => 0,
                'forecast_count' => 0,
                'scenario_count' => 0,
                'insight_count' => 0,
                'critical_insight_count' => 0,
                'analytics_run_count_7d' => 0,
                'analytics_finding_count' => 0,
                'critical_analytics_finding_count' => 0,
                'latest_run_at' => null,
            ];
        }

        $runs = $this->conn->query("
            SELECT COUNT(1) AS RunCount7d, MAX(CreatedDate) AS LatestRunAt
            FROM dbo.tblIntelligenceEngineRuns
            WHERE CreatedDate >= DATEADD(DAY, -7, SYSUTCDATETIME())
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $counts = $this->conn->query("
            SELECT
                (SELECT COUNT(1) FROM dbo.tblIntelligenceForecasts WHERE ActiveFlag = 1) AS ForecastCount,
                (SELECT COUNT(1) FROM dbo.tblIntelligenceScenarios WHERE ActiveFlag = 1) AS ScenarioCount,
                (SELECT COUNT(1) FROM dbo.tblIntelligenceInsights WHERE StatusCode = N'OPEN') AS InsightCount,
                (SELECT COUNT(1) FROM dbo.tblIntelligenceInsights WHERE StatusCode = N'OPEN' AND SeverityCode IN (N'HIGH', N'CRITICAL')) AS CriticalInsightCount
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $analytics = [
            'AnalyticsRunCount7d' => 0,
            'AnalyticsFindingCount' => 0,
            'CriticalAnalyticsFindingCount' => 0,
        ];
        if ($this->supportsAnalyticsOutputs()) {
            $analytics = $this->conn->query("
                SELECT
                    (SELECT COUNT(1) FROM dbo.tblAnalyticsRuns WHERE StartedDate >= DATEADD(DAY, -7, SYSUTCDATETIME())) AS AnalyticsRunCount7d,
                    (SELECT COUNT(1) FROM dbo.tblAnalyticsFindings WHERE StatusCode = N'OPEN') AS AnalyticsFindingCount,
                    (SELECT COUNT(1) FROM dbo.tblAnalyticsFindings WHERE StatusCode = N'OPEN' AND SeverityCode IN (N'HIGH', N'CRITICAL')) AS CriticalAnalyticsFindingCount
            ")->fetch(PDO::FETCH_ASSOC) ?: $analytics;
        }

        return [
            'run_count_7d' => (int) ($runs['RunCount7d'] ?? 0),
            'latest_run_at' => $runs['LatestRunAt'] ?? null,
            'forecast_count' => (int) ($counts['ForecastCount'] ?? 0),
            'scenario_count' => (int) ($counts['ScenarioCount'] ?? 0),
            'insight_count' => (int) ($counts['InsightCount'] ?? 0),
            'critical_insight_count' => (int) ($counts['CriticalInsightCount'] ?? 0),
            'analytics_run_count_7d' => (int) ($analytics['AnalyticsRunCount7d'] ?? 0),
            'analytics_finding_count' => (int) ($analytics['AnalyticsFindingCount'] ?? 0),
            'critical_analytics_finding_count' => (int) ($analytics['CriticalAnalyticsFindingCount'] ?? 0),
        ];
    }

    public function logEngineRun(array $payload): int
    {
        if (!$this->tableExists('dbo.tblIntelligenceEngineRuns')) {
            return 0;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblIntelligenceEngineRuns (
                RunTypeCode, RequestJson, ResponseJson, StatusCode, ErrorMessage,
                ResponseTimeMs, ProviderCode, ExternalServiceUsed, DataMaskingUsed, CreatedBy
            )
            VALUES (
                :RunTypeCode, :RequestJson, :ResponseJson, :StatusCode, :ErrorMessage,
                :ResponseTimeMs, :ProviderCode, :ExternalServiceUsed, :DataMaskingUsed, :CreatedBy
            )
        ");
        $stmt->execute([
            ':RunTypeCode' => trim((string) ($payload['RunTypeCode'] ?? 'UNKNOWN')),
            ':RequestJson' => $this->nullableJson($payload['RequestJson'] ?? null),
            ':ResponseJson' => $this->nullableJson($payload['ResponseJson'] ?? null),
            ':StatusCode' => trim((string) ($payload['StatusCode'] ?? 'SUCCESS')),
            ':ErrorMessage' => $this->nullableString($payload['ErrorMessage'] ?? null),
            ':ResponseTimeMs' => $this->nullableInt($payload['ResponseTimeMs'] ?? null),
            ':ProviderCode' => $this->nullableString($payload['ProviderCode'] ?? null),
            ':ExternalServiceUsed' => ((int) ($payload['ExternalServiceUsed'] ?? 0) === 1) ? 1 : 0,
            ':DataMaskingUsed' => ((int) ($payload['DataMaskingUsed'] ?? 0) === 1) ? 1 : 0,
            ':CreatedBy' => $this->nullableInt($payload['CreatedBy'] ?? null),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function recentRuns(int $limit = 50): array
    {
        if (!$this->tableExists('dbo.tblIntelligenceEngineRuns')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->conn->query("
            SELECT TOP {$limit} *
            FROM dbo.tblIntelligenceEngineRuns
            ORDER BY CreatedDate DESC, EngineRunID DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function supportsBudgetLedgerVersionRoles(): bool
    {
        return $this->tableExists('dbo.tblAIBudgetLedgerVersionRoleMap')
            && $this->sourceObjectExists('dbo.vwAI_BudgetLedgerVersionRoleCandidates');
    }

    public function listBudgetLedgerVersionRoleFiscalYears(): array
    {
        if (!$this->supportsBudgetLedgerVersionRoles()) {
            return [];
        }

        $stmt = $this->conn->query("
            SELECT FiscalYearID
            FROM (
                SELECT DISTINCT FiscalYearID
                FROM dbo.vwAI_BudgetLedgerVersionRoleCandidates
                WHERE FiscalYearID IS NOT NULL
                UNION
                SELECT DISTINCT FiscalYearID
                FROM dbo.tblAIBudgetLedgerVersionRoleMap
                WHERE FiscalYearID IS NOT NULL
            ) y
            ORDER BY FiscalYearID DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listBudgetLedgerVersionRoleCandidates(int $fiscalYearId): array
    {
        if ($fiscalYearId <= 0 || !$this->supportsBudgetLedgerVersionRoles()) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT
                c.*,
                CurrentRoleCode = activeMap.VersionRoleCode,
                CurrentIsBudgetBaseline = activeMap.IsBudgetBaseline,
                CurrentIsExecutionActuals = activeMap.IsExecutionActuals
            FROM dbo.vwAI_BudgetLedgerVersionRoleCandidates c
            LEFT JOIN dbo.tblAIBudgetLedgerVersionRoleMap activeMap
                ON activeMap.FiscalYearID = c.FiscalYearID
               AND activeMap.BudgetVersionID = c.BudgetVersionID
               AND activeMap.ActiveFlag = 1
            WHERE c.FiscalYearID = :FiscalYearID
            ORDER BY c.BudgetVersionID
        ");
        $stmt->execute([':FiscalYearID' => $fiscalYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listBudgetLedgerVersionRoleMappings(int $fiscalYearId): array
    {
        if ($fiscalYearId <= 0 || !$this->tableExists('dbo.tblAIBudgetLedgerVersionRoleMap')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblAIBudgetLedgerVersionRoleMap
            WHERE FiscalYearID = :FiscalYearID
            ORDER BY ActiveFlag DESC, VersionRoleCode, BudgetVersionID
        ");
        $stmt->execute([':FiscalYearID' => $fiscalYearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveBudgetLedgerVersionRoles(int $fiscalYearId, int $budgetBaselineVersionId, int $executionActualsVersionId): void
    {
        if (!$this->supportsBudgetLedgerVersionRoles()) {
            throw new \RuntimeException('Budget ledger version-role mapping tables/views are not installed.');
        }
        if ($fiscalYearId <= 0) {
            throw new \InvalidArgumentException('Fiscal year is required.');
        }
        if ($budgetBaselineVersionId <= 0 || $executionActualsVersionId <= 0) {
            throw new \InvalidArgumentException('Both budget baseline and execution actuals versions are required.');
        }
        if ($budgetBaselineVersionId === $executionActualsVersionId) {
            throw new \InvalidArgumentException('Budget baseline and execution actuals must be different versions.');
        }

        $baseline = $this->getBudgetLedgerVersionRoleCandidate($fiscalYearId, $budgetBaselineVersionId);
        $actuals = $this->getBudgetLedgerVersionRoleCandidate($fiscalYearId, $executionActualsVersionId);
        if ($baseline === null) {
            throw new \InvalidArgumentException('Selected budget baseline version was not found in the candidate list.');
        }
        if ($actuals === null) {
            throw new \InvalidArgumentException('Selected execution actuals version was not found in the candidate list.');
        }

        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblAIBudgetLedgerVersionRoleMap
                SET ActiveFlag = 0,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE FiscalYearID = :FiscalYearID
                  AND ActiveFlag = 1
            ");
            $stmt->execute([':FiscalYearID' => $fiscalYearId]);

            $this->upsertBudgetLedgerVersionRole([
                'FiscalYearID' => $fiscalYearId,
                'BudgetVersionID' => $budgetBaselineVersionId,
                'VersionTypeID' => $this->nullableInt($baseline['VersionTypeID'] ?? null),
                'VersionRoleCode' => 'BUDGET_BASELINE',
                'VersionRoleName' => 'Budget Baseline',
                'IsBudgetBaseline' => 1,
                'IsExecutionActuals' => 0,
                'Notes' => 'Selected in the Intelligence budget ledger version-role admin screen.',
            ]);

            $this->upsertBudgetLedgerVersionRole([
                'FiscalYearID' => $fiscalYearId,
                'BudgetVersionID' => $executionActualsVersionId,
                'VersionTypeID' => $this->nullableInt($actuals['VersionTypeID'] ?? null),
                'VersionRoleCode' => 'EXECUTION_ACTUALS',
                'VersionRoleName' => 'Execution Actuals',
                'IsBudgetBaseline' => 0,
                'IsExecutionActuals' => 1,
                'Notes' => 'Selected in the Intelligence budget ledger version-role admin screen.',
            ]);

            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    private function getBudgetLedgerVersionRoleCandidate(int $fiscalYearId, int $budgetVersionId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.vwAI_BudgetLedgerVersionRoleCandidates
            WHERE FiscalYearID = :FiscalYearID
              AND BudgetVersionID = :BudgetVersionID
        ");
        $stmt->execute([
            ':FiscalYearID' => $fiscalYearId,
            ':BudgetVersionID' => $budgetVersionId,
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function upsertBudgetLedgerVersionRole(array $payload): void
    {
        $params = [
            ':FiscalYearID' => (int) $payload['FiscalYearID'],
            ':BudgetVersionID' => (int) $payload['BudgetVersionID'],
            ':VersionTypeID' => $payload['VersionTypeID'],
            ':VersionRoleCode' => (string) $payload['VersionRoleCode'],
            ':VersionRoleName' => (string) $payload['VersionRoleName'],
            ':IsBudgetBaseline' => (int) $payload['IsBudgetBaseline'],
            ':IsExecutionActuals' => (int) $payload['IsExecutionActuals'],
            ':Notes' => (string) $payload['Notes'],
        ];

        $stmt = $this->conn->prepare("
            UPDATE dbo.tblAIBudgetLedgerVersionRoleMap
            SET VersionTypeID = :VersionTypeID,
                VersionRoleName = :VersionRoleName,
                IsBudgetBaseline = :IsBudgetBaseline,
                IsExecutionActuals = :IsExecutionActuals,
                ActiveFlag = 1,
                Notes = :Notes,
                UpdatedDate = SYSUTCDATETIME()
            WHERE FiscalYearID = :FiscalYearID
              AND BudgetVersionID = :BudgetVersionID
              AND VersionRoleCode = :VersionRoleCode
        ");
        $stmt->execute($params);
        if ($stmt->rowCount() > 0) {
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblAIBudgetLedgerVersionRoleMap
                (FiscalYearID, BudgetVersionID, VersionTypeID, VersionRoleCode, VersionRoleName,
                 IsBudgetBaseline, IsExecutionActuals, ActiveFlag, Notes)
            VALUES
                (:FiscalYearID, :BudgetVersionID, :VersionTypeID, :VersionRoleCode, :VersionRoleName,
                 :IsBudgetBaseline, :IsExecutionActuals, 1, :Notes)
        ");
        $stmt->execute($params);
    }

    public function recentMLInterpretations(int $modelId, int $limit = 5): array
    {
        if ($modelId <= 0 || !$this->tableExists('dbo.tblIntelligenceEngineRuns')) {
            return [];
        }
        $limit = max(1, min(20, $limit));
        $needle = '%"MLModelID":' . $modelId . '%';
        $stmt = $this->conn->prepare("
            SELECT TOP {$limit} *
            FROM dbo.tblIntelligenceEngineRuns
            WHERE RunTypeCode = N'ML_AI_INTERPRETATION'
              AND RequestJson LIKE :needle
            ORDER BY CreatedDate DESC, EngineRunID DESC
        ");
        $stmt->execute([':needle' => $needle]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProviders(): array
    {
        if (!$this->tableExists('dbo.tblAIProviders')) {
            return [];
        }
        $stmt = $this->conn->query("SELECT * FROM dbo.tblAIProviders ORDER BY ProviderCode ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listModuleSettings(): array
    {
        if (!$this->tableExists('dbo.tblAIModuleSettings')) {
            return [];
        }
        $stmt = $this->conn->query("SELECT * FROM dbo.tblAIModuleSettings ORDER BY ModuleCode ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listMLModels(): array
    {
        if (!$this->tableExists('dbo.tblMLModels')) {
            return [];
        }
        $stmt = $this->conn->query("
            SELECT
                m.*,
                (SELECT COUNT(1) FROM dbo.tblMLTrainingRuns r WHERE r.MLModelID = m.MLModelID) AS TrainingRunCount,
                (SELECT COUNT(1) FROM dbo.tblMLPredictions p WHERE p.MLModelID = m.MLModelID) AS PredictionCount
            FROM dbo.tblMLModels m
            ORDER BY m.CreatedDate DESC, m.MLModelID DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMLModel(int $modelId): ?array
    {
        if ($modelId <= 0 || !$this->tableExists('dbo.tblMLModels')) {
            return null;
        }
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblMLModels WHERE MLModelID = :id');
        $stmt->execute([':id' => $modelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function recentMLWorkflowEvents(int $modelId, int $limit = 50): array
    {
        if ($modelId <= 0 || !$this->tableExists('dbo.tblMLWorkflowEvents')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $predictionSelect = $this->columnExists('dbo.tblMLWorkflowEvents', 'MLPredictionID') ? 'MLPredictionID,' : 'CAST(NULL AS INT) AS MLPredictionID,';
        $stmt = $this->conn->prepare("
            SELECT TOP {$limit}
                MLWorkflowEventID,
                MLModelID,
                {$predictionSelect}
                ActionCode,
                FromStatusCode,
                ToStatusCode,
                Notes,
                EvidenceJson,
                CreatedBy,
                CreatedDate
            FROM dbo.tblMLWorkflowEvents
            WHERE MLModelID = :MLModelID
            ORDER BY CreatedDate DESC, MLWorkflowEventID DESC
        ");
        $stmt->execute([':MLModelID' => $modelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recentMLPredictionWorkflowEvents(int $predictionId, int $limit = 50): array
    {
        if ($predictionId <= 0 || !$this->tableExists('dbo.tblMLWorkflowEvents') || !$this->columnExists('dbo.tblMLWorkflowEvents', 'MLPredictionID')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->conn->prepare("
            SELECT TOP {$limit}
                MLWorkflowEventID,
                MLModelID,
                MLPredictionID,
                ActionCode,
                FromStatusCode,
                ToStatusCode,
                Notes,
                EvidenceJson,
                CreatedBy,
                CreatedDate
            FROM dbo.tblMLWorkflowEvents
            WHERE MLPredictionID = :MLPredictionID
            ORDER BY CreatedDate DESC, MLWorkflowEventID DESC
        ");
        $stmt->execute([':MLPredictionID' => $predictionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveMLModel(array $data, int $userId): int
    {
        $this->requireMLFoundation();
        $modelId = (int) ($data['MLModelID'] ?? 0);
        $payload = [
            ':ModelCode' => $this->normaliseCode((string) ($data['ModelCode'] ?? '')),
            ':ModelName' => trim((string) ($data['ModelName'] ?? '')),
            ':UseCaseCode' => $this->normaliseCode((string) ($data['UseCaseCode'] ?? '')),
            ':ModelTypeCode' => $this->normaliseCode((string) ($data['ModelTypeCode'] ?? '')),
            ':ApprovedViewName' => $this->normaliseNullableObjectName((string) ($data['ApprovedViewName'] ?? '')),
            ':TargetColumnName' => $this->nullableString($data['TargetColumnName'] ?? null),
            ':FeatureColumnsJson' => $this->featureColumnsJson((string) ($data['FeatureColumns'] ?? '')),
            ':StatusCode' => $this->normaliseStatus((string) ($data['StatusCode'] ?? 'DRAFT')),
            ':AccuracyScore' => $this->nullableDecimal($data['AccuracyScore'] ?? null),
            ':ActiveFlag' => ((int) ($data['ActiveFlag'] ?? 1) === 1) ? 1 : 0,
        ];

        if ($payload[':ModelCode'] === '' || $payload[':ModelName'] === '' || $payload[':UseCaseCode'] === '' || $payload[':ModelTypeCode'] === '') {
            throw new \InvalidArgumentException('Model code, name, use case, and model type are required.');
        }
        if ($payload[':ApprovedViewName'] !== null && !$this->sourceObjectExists($payload[':ApprovedViewName'])) {
            throw new \InvalidArgumentException('Approved view/table was not found.');
        }

        if ($modelId > 0) {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblMLModels
                SET ModelCode = :ModelCode,
                    ModelName = :ModelName,
                    UseCaseCode = :UseCaseCode,
                    ModelTypeCode = :ModelTypeCode,
                    ApprovedViewName = :ApprovedViewName,
                    TargetColumnName = :TargetColumnName,
                    FeatureColumnsJson = :FeatureColumnsJson,
                    StatusCode = :StatusCode,
                    AccuracyScore = :AccuracyScore,
                    ActiveFlag = :ActiveFlag
                WHERE MLModelID = :MLModelID
            ");
            $stmt->execute($payload + [':MLModelID' => $modelId]);
            return $modelId;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblMLModels (
                ModelCode, ModelName, UseCaseCode, ModelTypeCode, ApprovedViewName,
                TargetColumnName, FeatureColumnsJson, StatusCode, AccuracyScore, ActiveFlag, CreatedBy
            )
            VALUES (
                :ModelCode, :ModelName, :UseCaseCode, :ModelTypeCode, :ApprovedViewName,
                :TargetColumnName, :FeatureColumnsJson, :StatusCode, :AccuracyScore, :ActiveFlag, :CreatedBy
            )
        ");
        $stmt->execute($payload + [':CreatedBy' => $userId > 0 ? $userId : null]);
        return (int) $this->conn->lastInsertId();
    }

    public function approveMLModel(int $modelId, int $userId): void
    {
        $this->requireMLFoundation();
        $model = $this->getMLModel($modelId);
        $fromStatus = is_array($model) ? (string) ($model['StatusCode'] ?? '') : null;
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblMLModels
            SET StatusCode = N'APPROVED',
                ApprovedBy = :ApprovedBy,
                ApprovedDate = SYSUTCDATETIME()
            WHERE MLModelID = :MLModelID
        ");
        $stmt->execute([':ApprovedBy' => $userId > 0 ? $userId : null, ':MLModelID' => $modelId]);
        $this->recordMLWorkflowEvent($modelId, 'APPROVE_MODEL', $fromStatus, 'APPROVED', 'Model approved.', null, $userId);
    }

    public function applyMLWorkflowAction(int $modelId, string $actionCode, ?string $notes, int $userId): string
    {
        $this->requireMLFoundation();
        $model = $this->getMLModel($modelId);
        if ($model === null) {
            throw new \InvalidArgumentException('ML model was not found.');
        }

        $actionCode = $this->normaliseCode($actionCode);
        $fromStatus = (string) ($model['StatusCode'] ?? 'DRAFT');
        $toStatus = match ($actionCode) {
            'SUBMIT_FOR_REVIEW' => 'READY',
            'REQUEST_CHANGES' => 'CHANGES_REQUESTED',
            'MARK_RESULTS_REVIEWED' => $fromStatus === 'APPROVED' ? 'APPROVED' : 'REVIEWED',
            'REOPEN_DRAFT' => 'DRAFT',
            'RETIRE_MODEL' => 'RETIRED',
            default => throw new \InvalidArgumentException('Unsupported ML workflow action.'),
        };

        $this->conn->beginTransaction();
        try {
            if ($toStatus !== $fromStatus) {
                $stmt = $this->conn->prepare("
                    UPDATE dbo.tblMLModels
                    SET StatusCode = :StatusCode
                    WHERE MLModelID = :MLModelID
                ");
                $stmt->execute([':StatusCode' => $toStatus, ':MLModelID' => $modelId]);
            }

            $this->recordMLWorkflowEvent($modelId, $actionCode, $fromStatus, $toStatus, $notes, null, $userId);
            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }

        return $toStatus;
    }

    public function applyMLPredictionWorkflowAction(int $predictionId, string $actionCode, ?string $notes, int $userId): void
    {
        $prediction = $this->getMLPrediction($predictionId);
        if ($prediction === null) {
            throw new \InvalidArgumentException('Prediction was not found.');
        }

        $modelId = (int) ($prediction['MLModelID'] ?? 0);
        $actionCode = $this->normaliseCode($actionCode);
        $allowed = ['MARK_PREDICTION_REVIEWED', 'ACCEPT_AS_RISK', 'DISMISS_PREDICTION', 'REFER_FOR_FOLLOW_UP'];
        if (!in_array($actionCode, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported prediction workflow action.');
        }

        $this->recordMLWorkflowEvent(
            $modelId,
            $actionCode,
            null,
            null,
            $notes,
            [
                'MLPredictionID' => $predictionId,
                'EntityTypeCode' => $prediction['EntityTypeCode'] ?? null,
                'EntityCode' => $prediction['EntityCode'] ?? null,
                'PredictionValue' => $prediction['PredictionValue'] ?? null,
                'RiskScore' => $prediction['RiskScore'] ?? null,
            ],
            $userId,
            $predictionId
        );
    }

    public function recordMLWorkflowEvent(
        int $modelId,
        string $actionCode,
        ?string $fromStatus,
        ?string $toStatus,
        ?string $notes,
        mixed $evidence,
        int $userId,
        ?int $predictionId = null
    ): void {
        if ($modelId <= 0 || !$this->tableExists('dbo.tblMLWorkflowEvents')) {
            return;
        }

        $evidenceJson = $evidence === null ? null : json_encode($evidence, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $predictionColumn = $this->columnExists('dbo.tblMLWorkflowEvents', 'MLPredictionID');
        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblMLWorkflowEvents (
                MLModelID" . ($predictionColumn ? ", MLPredictionID" : "") . ", ActionCode, FromStatusCode, ToStatusCode, Notes, EvidenceJson, CreatedBy
            )
            VALUES (
                :MLModelID" . ($predictionColumn ? ", :MLPredictionID" : "") . ", :ActionCode, :FromStatusCode, :ToStatusCode, :Notes, :EvidenceJson, :CreatedBy
            )
        ");
        $params = [
            ':MLModelID' => $modelId,
            ':ActionCode' => mb_substr($this->normaliseCode($actionCode), 0, 80),
            ':FromStatusCode' => $this->nullableString($fromStatus),
            ':ToStatusCode' => $this->nullableString($toStatus),
            ':Notes' => $this->nullableString(mb_substr((string) ($notes ?? ''), 0, 2000)),
            ':EvidenceJson' => $evidenceJson,
            ':CreatedBy' => $userId > 0 ? $userId : null,
        ];
        if ($predictionColumn) {
            $params[':MLPredictionID'] = $predictionId !== null && $predictionId > 0 ? $predictionId : null;
        }
        $stmt->execute($params);
    }

    public function createMLTrainingRun(int $modelId, ?string $startDate, ?string $endDate): int
    {
        $this->requireMLFoundation();
        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblMLTrainingRuns (MLModelID, TrainingPeriodStart, TrainingPeriodEnd, StatusCode)
            VALUES (:MLModelID, :TrainingPeriodStart, :TrainingPeriodEnd, N'PENDING')
        ");
        $stmt->execute([
            ':MLModelID' => $modelId,
            ':TrainingPeriodStart' => $this->nullableDate($startDate),
            ':TrainingPeriodEnd' => $this->nullableDate($endDate),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function markMLTrainingRunRunning(int $trainingRunId): void
    {
        $this->requireMLFoundation();
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblMLTrainingRuns
            SET StatusCode = N'RUNNING',
                ErrorMessage = NULL,
                StartedDate = SYSUTCDATETIME(),
                CompletedDate = NULL
            WHERE MLTrainingRunID = :MLTrainingRunID
        ");
        $stmt->execute([':MLTrainingRunID' => $trainingRunId]);
    }

    public function completeMLTrainingRun(int $modelId, int $trainingRunId, array $response): void
    {
        $this->requireMLFoundation();
        $metrics = is_array($response['metrics'] ?? null) ? $response['metrics'] : [];
        $accuracy = $this->nullableDecimal($metrics['accuracy_score'] ?? null);
        $metricsJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("
                UPDATE dbo.tblMLTrainingRuns
                SET StatusCode = N'COMPLETED',
                    MetricsJson = :MetricsJson,
                    ErrorMessage = NULL,
                    CompletedDate = SYSUTCDATETIME()
                WHERE MLTrainingRunID = :MLTrainingRunID
            ");
            $stmt->execute([':MetricsJson' => $metricsJson, ':MLTrainingRunID' => $trainingRunId]);

            $stmt = $this->conn->prepare("
                UPDATE dbo.tblMLModels
                SET StatusCode = CASE WHEN StatusCode = N'APPROVED' THEN StatusCode ELSE N'TRAINED' END,
                    AccuracyScore = COALESCE(:AccuracyScore, AccuracyScore),
                    LastTrainedDate = SYSUTCDATETIME()
                WHERE MLModelID = :MLModelID
            ");
            $stmt->execute([':AccuracyScore' => $accuracy, ':MLModelID' => $modelId]);
            $this->conn->commit();
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function failMLTrainingRun(int $trainingRunId, string $message): void
    {
        $this->requireMLFoundation();
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblMLTrainingRuns
            SET StatusCode = N'ERROR',
                ErrorMessage = :ErrorMessage,
                CompletedDate = SYSUTCDATETIME()
            WHERE MLTrainingRunID = :MLTrainingRunID
        ");
        $stmt->execute([
            ':ErrorMessage' => mb_substr($message, 0, 2000),
            ':MLTrainingRunID' => $trainingRunId,
        ]);
    }

    public function buildMLTrainingPayload(array $model, int $maxRows = 5000): array
    {
        $this->requireMLFoundation();
        $sourceObject = $this->normaliseNullableObjectName((string) ($model['ApprovedViewName'] ?? ''));
        $targetColumn = trim((string) ($model['TargetColumnName'] ?? ''));
        $featureColumns = $this->decodeFeatureColumns($model['FeatureColumnsJson'] ?? null);
        if ($sourceObject === null || $targetColumn === '' || $featureColumns === []) {
            throw new \InvalidArgumentException('Approved source, target column, and feature columns are required before training.');
        }
        if (!$this->sourceObjectExists($sourceObject)) {
            throw new \InvalidArgumentException('Approved training source was not found.');
        }

        $available = $this->sourceColumnMap($sourceObject);
        $selectedColumns = [];
        foreach (array_merge([$targetColumn], $featureColumns) as $column) {
            $key = strtolower($column);
            if (!isset($available[$key])) {
                throw new \InvalidArgumentException('Training column was not found in approved source: ' . $column);
            }
            $selectedColumns[$available[$key]] = true;
        }

        $select = [];
        foreach (array_keys($selectedColumns) as $column) {
            if (strcasecmp($column, $available[strtolower($targetColumn)]) === 0) {
                $select[] = 'TRY_CONVERT(FLOAT, ' . $this->quoteIdentifier($column) . ') AS ' . $this->quoteIdentifier($column);
            } else {
                $select[] = 'CAST(' . $this->quoteIdentifier($column) . ' AS NVARCHAR(250)) AS ' . $this->quoteIdentifier($column);
            }
        }
        $where = ['TRY_CONVERT(FLOAT, ' . $this->quoteIdentifier($available[strtolower($targetColumn)]) . ') IS NOT NULL'];
        $contextWhere = $this->mlContextWhere($available);
        if ($contextWhere !== null) {
            $where[] = $contextWhere;
        }
        $limit = max(100, min(20000, $maxRows));
        $sql = 'SELECT TOP ' . $limit . ' ' . implode(', ', $select)
            . ' FROM ' . $this->quoteSourceObject($sourceObject)
            . ' WHERE ' . implode(' AND ', $where)
            . $this->mlOrderBy($available);
        $stmt = $this->conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) < 10) {
            throw new \RuntimeException('Not enough complete numeric rows were available for training.');
        }

        $approvedFeatureColumns = [];
        foreach ($featureColumns as $column) {
            $approvedFeatureColumns[] = $available[strtolower($column)] ?? $column;
        }

        return [
            'model_code' => (string) ($model['ModelCode'] ?? ''),
            'model_type' => (string) ($model['ModelTypeCode'] ?? 'REGRESSION'),
            'target_column' => $available[strtolower($targetColumn)],
            'feature_columns' => array_values($approvedFeatureColumns),
            'rows' => $rows,
        ];
    }

    public function recentMLTrainingRuns(int $modelId, int $limit = 20): array
    {
        if ($modelId <= 0 || !$this->tableExists('dbo.tblMLTrainingRuns')) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->conn->prepare("
            SELECT TOP {$limit} *
            FROM dbo.tblMLTrainingRuns
            WHERE MLModelID = :MLModelID
            ORDER BY StartedDate DESC, MLTrainingRunID DESC
        ");
        $stmt->execute([':MLModelID' => $modelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function recentMLPredictions(int $modelId, int $limit = 20): array
    {
        if ($modelId <= 0 || !$this->tableExists('dbo.tblMLPredictions')) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $stmt = $this->conn->prepare("
            SELECT TOP {$limit} *
            FROM dbo.tblMLPredictions
            WHERE MLModelID = :MLModelID
            ORDER BY CreatedDate DESC, MLPredictionID DESC
        ");
        $stmt->execute([':MLModelID' => $modelId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getMLPrediction(int $predictionId): ?array
    {
        if ($predictionId <= 0 || !$this->tableExists('dbo.tblMLPredictions')) {
            return null;
        }
        $stmt = $this->conn->prepare('SELECT * FROM dbo.tblMLPredictions WHERE MLPredictionID = :id');
        $stmt->execute([':id' => $predictionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function mlPredictionDrillData(array $model, array $prediction, int $limit = 100): array
    {
        $limit = max(20, min(500, $limit));
        $entityCode = trim((string) ($prediction['EntityCode'] ?? ''));
        $predictionJson = json_decode((string) ($prediction['PredictionJson'] ?? ''), true);
        $rowContext = is_array($predictionJson) && is_array($predictionJson['row_context'] ?? null) ? $predictionJson['row_context'] : [];
        $approvedSource = $this->normaliseNullableObjectName((string) ($model['ApprovedViewName'] ?? ''));

        $sourceRows = $approvedSource !== null && $this->sourceObjectExists($approvedSource)
            ? $this->queryMLDrillRows($approvedSource, $entityCode, $rowContext, min($limit, 100), false)
            : [];

        return [
            'sourceRows' => $sourceRows,
            'ledgerRows' => $sourceRows !== [] && $this->sourceObjectExists('dbo.vwAI_BudgetLedgerAnalysis')
                ? $this->queryMLDrillRows('dbo.vwAI_BudgetLedgerAnalysis', $entityCode, $rowContext, 50, true)
                : [],
        ];
    }

    public function listMLPredictionDrillRows(int $predictionId, int $limit = 100): array
    {
        if ($predictionId <= 0 || !$this->tableExists('dbo.tblMLPredictionDrillRows')) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = $this->conn->prepare("
            SELECT TOP {$limit}
                MLPredictionDrillRowID,
                FiscalYearID,
                BudgetVersionID,
                PeriodNo,
                Segment1,
                Segment2,
                Segment3,
                ProgramCode,
                EconomicCode,
                CurrencyCode,
                BudgetAmount,
                ActualAmount,
                AvailableBalance,
                ExecutionRate,
                CumulativeBudgetAmount,
                CumulativeActualAmount,
                CumulativeExecutionRate,
                ExpectedExecutionRate,
                VarianceAmount,
                VariancePct,
                RiskScore,
                RiskLabel,
                AnomalyTypeCode,
                RiskReason,
                DetailJson,
                CreatedDate
            FROM dbo.tblMLPredictionDrillRows
            WHERE MLPredictionID = :MLPredictionID
            ORDER BY MLPredictionDrillRowID
        ");
        $stmt->execute([':MLPredictionID' => $predictionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listMLPredictionUnderlyingLedgerRows(array $prediction, int $limit = 200): array
    {
        if (!$this->tableExists('dbo.tblAIBudgetLedgerAnalysisSource')) {
            return [];
        }

        $predictionJson = json_decode((string) ($prediction['PredictionJson'] ?? ''), true);
        $context = is_array($predictionJson) && is_array($predictionJson['row_context'] ?? null) ? $predictionJson['row_context'] : [];
        $entityCode = trim((string) ($prediction['EntityCode'] ?? ''));
        $parts = array_map('trim', explode('|', $entityCode));

        $fiscalYearId = (int) ($context['FiscalYearID'] ?? 0);
        if ($fiscalYearId <= 0 && count($parts) >= 5) {
            $fiscalYearId = (int) $parts[0];
        }
        if ($fiscalYearId <= 0) {
            $fiscalYearId = (int) SessionHelper::get('FiscalYearID', 0);
        }
        if ($fiscalYearId <= 0) {
            $fiscalYearId = (int) SessionHelper::get('context.FiscalYearID', 0);
        }

        $segment1 = trim((string) ($context['Segment1'] ?? ''));
        $programCode = trim((string) ($context['ProgramCode'] ?? ''));
        $economicCode = trim((string) ($context['EconomicCode'] ?? ''));
        if (($segment1 === '' || $programCode === '' || $economicCode === '') && count($parts) >= 5) {
            $segment1 = $segment1 !== '' ? $segment1 : $parts[2];
            $programCode = $programCode !== '' ? $programCode : $parts[3];
            $economicCode = $economicCode !== '' ? $economicCode : $parts[4];
        } elseif (($segment1 === '' || $programCode === '' || $economicCode === '') && count($parts) === 3) {
            $segment1 = $segment1 !== '' ? $segment1 : $parts[0];
            $programCode = $programCode !== '' ? $programCode : $parts[1];
            $economicCode = $economicCode !== '' ? $economicCode : $parts[2];
        }

        if ($fiscalYearId <= 0 || $segment1 === '' || $programCode === '' || $economicCode === '') {
            return [];
        }

        $where = [
            'FiscalYearID = :FiscalYearID',
            'Segment1 = :Segment1',
            'Segment5 = :ProgramCode',
            'Segment11 = :EconomicCode',
            'IsActive = 1',
        ];
        $params = [
            ':FiscalYearID' => $fiscalYearId,
            ':Segment1' => $segment1,
            ':ProgramCode' => $programCode,
            ':EconomicCode' => $economicCode,
        ];

        $limit = max(1, min(500, $limit));
        $sql = 'SELECT TOP ' . $limit . '
                BudgetLedgerAnalysisID,
                SourceRowReference,
                FiscalYearID,
                BudgetVersionID,
                PeriodNo,
                Segment1,
                Segment5 AS ProgramCode,
                Segment11 AS EconomicCode,
                CurrencyCode,
                BudgetAmount,
                ReleasedAmount,
                WarrantAmount,
                CommitmentAmount,
                ActualAmount,
                AvailableBalance,
                ExecutionRate,
                PostingDate,
                DataLoadBatchCode,
                SourceSystemCode
            FROM dbo.tblAIBudgetLedgerAnalysisSource
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY BudgetVersionID, PeriodNo, ABS(COALESCE(ActualAmount, 0)) DESC, BudgetLedgerAnalysisID';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function latestCompletedMLTrainingRun(int $modelId): ?array
    {
        if ($modelId <= 0 || !$this->tableExists('dbo.tblMLTrainingRuns')) {
            return null;
        }
        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblMLTrainingRuns
            WHERE MLModelID = :MLModelID
              AND StatusCode = N'COMPLETED'
              AND MetricsJson IS NOT NULL
            ORDER BY CompletedDate DESC, MLTrainingRunID DESC
        ");
        $stmt->execute([':MLModelID' => $modelId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function buildMLPredictionPayload(array $model, array $trainingRun, int $maxRows = 200): array
    {
        $this->requireMLFoundation();
        $trainingResult = json_decode((string) ($trainingRun['MetricsJson'] ?? ''), true);
        if (!is_array($trainingResult) || !is_array($trainingResult['model_artifact'] ?? null)) {
            throw new \InvalidArgumentException('Latest completed training run does not contain a model artifact.');
        }

        $sourceObject = $this->normaliseNullableObjectName((string) ($model['ApprovedViewName'] ?? ''));
        $targetColumn = trim((string) ($model['TargetColumnName'] ?? ''));
        $featureColumns = $this->decodeFeatureColumns($model['FeatureColumnsJson'] ?? null);
        if ($sourceObject === null || $featureColumns === []) {
            throw new \InvalidArgumentException('Approved source and feature columns are required before prediction.');
        }
        if (!$this->sourceObjectExists($sourceObject)) {
            throw new \InvalidArgumentException('Approved prediction source was not found.');
        }

        $available = $this->sourceColumnMap($sourceObject);
        $selectedColumns = [];
        foreach (array_merge([$targetColumn], $featureColumns) as $column) {
            $column = trim((string) $column);
            if ($column === '') {
                continue;
            }
            $key = strtolower($column);
            if (!isset($available[$key])) {
                throw new \InvalidArgumentException('Prediction column was not found in approved source: ' . $column);
            }
            $selectedColumns[$available[$key]] = true;
        }

        $entityColumn = $this->predictionEntityColumn($available);
        if ($entityColumn !== null) {
            $selectedColumns[$entityColumn] = true;
        }

        $select = [];
        foreach (array_keys($selectedColumns) as $column) {
            if ($column === $entityColumn) {
                $select[] = 'CAST(' . $this->quoteIdentifier($column) . ' AS NVARCHAR(250)) AS ' . $this->quoteIdentifier($column);
            } elseif ($targetColumn !== '' && isset($available[strtolower($targetColumn)]) && strcasecmp($column, $available[strtolower($targetColumn)]) === 0) {
                $select[] = 'TRY_CONVERT(FLOAT, ' . $this->quoteIdentifier($column) . ') AS ' . $this->quoteIdentifier($column);
            } else {
                $select[] = 'CAST(' . $this->quoteIdentifier($column) . ' AS NVARCHAR(250)) AS ' . $this->quoteIdentifier($column);
            }
        }
        $where = [];
        $contextWhere = $this->mlContextWhere($available);
        if ($contextWhere !== null) {
            $where[] = $contextWhere;
        }
        $limit = max(20, min(1000, $maxRows));
        $sql = 'SELECT TOP ' . $limit . ' ' . implode(', ', $select)
            . ' FROM ' . $this->quoteSourceObject($sourceObject)
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . $this->mlOrderBy($available);
        $stmt = $this->conn->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            throw new \RuntimeException('No complete rows were available for prediction.');
        }

        $preparedRows = [];
        foreach ($rows as $index => $row) {
            $row['_entity_type'] = (string) ($model['ModelCode'] ?? 'ML_ENTITY');
            $row['_entity_code'] = $this->predictionEntityCode($row, $entityColumn, $index);
            $preparedRows[] = $row;
        }

        return [
            'model_code' => (string) ($model['ModelCode'] ?? ''),
            'model_type' => (string) ($model['ModelTypeCode'] ?? 'REGRESSION'),
            'target_column' => $targetColumn !== '' && isset($available[strtolower($targetColumn)]) ? $available[strtolower($targetColumn)] : null,
            'feature_columns' => array_values(array_map(
                fn (string $column): string => $available[strtolower($column)] ?? $column,
                $featureColumns
            )),
            'model_artifact' => $trainingResult['model_artifact'],
            'rows' => $preparedRows,
        ];
    }

    public function storeMLPredictions(int $modelId, array $predictions): int
    {
        $this->requireMLFoundation();
        if ($predictions === []) {
            return 0;
        }

        $hasAnalyticsRunColumn = $this->columnExists('dbo.tblMLPredictions', 'SourceAnalyticsRunID');
        $hasAnalyticsFindingColumn = $this->columnExists('dbo.tblMLPredictions', 'SourceAnalyticsFindingID');
        $columns = ['MLModelID'];
        $placeholders = [':MLModelID'];
        if ($hasAnalyticsRunColumn) {
            $columns[] = 'SourceAnalyticsRunID';
            $placeholders[] = ':SourceAnalyticsRunID';
        }
        if ($hasAnalyticsFindingColumn) {
            $columns[] = 'SourceAnalyticsFindingID';
            $placeholders[] = ':SourceAnalyticsFindingID';
        }
        $columns = array_merge($columns, ['EntityTypeCode', 'EntityCode', 'PredictionValue', 'RiskScore', 'ConfidenceScore', 'PredictionJson']);
        $placeholders = array_merge($placeholders, [':EntityTypeCode', ':EntityCode', ':PredictionValue', ':RiskScore', ':ConfidenceScore', ':PredictionJson']);

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblMLPredictions (" . implode(', ', $columns) . ")
            OUTPUT INSERTED.MLPredictionID
            VALUES (" . implode(', ', $placeholders) . ")
        ");
        $count = 0;
        foreach ($predictions as $prediction) {
            if (!is_array($prediction)) {
                continue;
            }
            $details = is_array($prediction['prediction_json'] ?? null) ? $prediction['prediction_json'] : $prediction;
            $entityType = mb_substr((string) ($prediction['entity_type'] ?? 'ML_ENTITY'), 0, 60);
            $entityCode = mb_substr((string) ($prediction['entity_code'] ?? ''), 0, 120);
            $params = [
                ':MLModelID' => $modelId,
                ':EntityTypeCode' => $entityType,
                ':EntityCode' => $entityCode,
                ':PredictionValue' => $this->nullableDecimal($prediction['prediction_value'] ?? null),
                ':RiskScore' => $this->nullableDecimal($prediction['risk_score'] ?? null),
                ':ConfidenceScore' => $this->nullableDecimal($prediction['confidence_score'] ?? null),
                ':PredictionJson' => json_encode($prediction['prediction_json'] ?? $prediction, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
            if ($hasAnalyticsRunColumn) {
                $params[':SourceAnalyticsRunID'] = $this->nullableInt($prediction['source_analytics_run_id'] ?? $details['AnalyticsRunID'] ?? $details['analytics_run_id'] ?? null);
            }
            if ($hasAnalyticsFindingColumn) {
                $params[':SourceAnalyticsFindingID'] = $this->nullableInt($prediction['source_analytics_finding_id'] ?? $details['AnalyticsFindingID'] ?? $details['analytics_finding_id'] ?? null);
            }
            $stmt->execute($params);
            $predictionId = (int) ($stmt->fetchColumn() ?: 0);
            if ($predictionId > 0) {
                $this->storeMLPredictionDrillRow($predictionId, $modelId, $entityType, $entityCode, $prediction);
            }
            $count++;
        }
        return $count;
    }

    public function sourceObjectExists(string $sourceObject): bool
    {
        $sourceObject = $this->normaliseNullableObjectName($sourceObject);
        if ($sourceObject === null) {
            return false;
        }
        $stmt = $this->conn->prepare("SELECT OBJECT_ID(:objectName, 'U') UNION SELECT OBJECT_ID(:objectNameView, 'V')");
        $stmt->execute([':objectName' => $sourceObject, ':objectNameView' => $sourceObject]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $value) {
            if ((int) $value > 0) {
                return true;
            }
        }
        return false;
    }

    private function storeMLPredictionDrillRow(int $predictionId, int $modelId, string $entityType, string $entityCode, array $prediction): void
    {
        if (!$this->tableExists('dbo.tblMLPredictionDrillRows')) {
            return;
        }

        $details = is_array($prediction['prediction_json'] ?? null) ? $prediction['prediction_json'] : $prediction;
        $context = is_array($details['row_context'] ?? null) ? $details['row_context'] : [];
        $row = array_merge($details, $context);

        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblMLPredictionDrillRows (
                MLPredictionID, MLModelID, EntityTypeCode, EntityCode,
                FiscalYearID, BudgetVersionID, PeriodNo,
                Segment1, Segment2, Segment3, ProgramCode, EconomicCode, CurrencyCode,
                BudgetAmount, ActualAmount, AvailableBalance, ExecutionRate,
                CumulativeBudgetAmount, CumulativeActualAmount, CumulativeExecutionRate,
                ExpectedExecutionRate, VarianceAmount, VariancePct,
                RiskScore, RiskLabel, AnomalyTypeCode, RiskReason, DetailJson
            )
            VALUES (
                :MLPredictionID, :MLModelID, :EntityTypeCode, :EntityCode,
                :FiscalYearID, :BudgetVersionID, :PeriodNo,
                :Segment1, :Segment2, :Segment3, :ProgramCode, :EconomicCode, :CurrencyCode,
                :BudgetAmount, :ActualAmount, :AvailableBalance, :ExecutionRate,
                :CumulativeBudgetAmount, :CumulativeActualAmount, :CumulativeExecutionRate,
                :ExpectedExecutionRate, :VarianceAmount, :VariancePct,
                :RiskScore, :RiskLabel, :AnomalyTypeCode, :RiskReason, :DetailJson
            )
        ");
        $stmt->execute([
            ':MLPredictionID' => $predictionId,
            ':MLModelID' => $modelId,
            ':EntityTypeCode' => $entityType,
            ':EntityCode' => $entityCode,
            ':FiscalYearID' => $this->nullableInt($row['FiscalYearID'] ?? null),
            ':BudgetVersionID' => $this->nullableInt($row['BudgetVersionID'] ?? null),
            ':PeriodNo' => $this->nullableInt($row['PeriodNo'] ?? null),
            ':Segment1' => $this->nullableString($row['Segment1'] ?? null),
            ':Segment2' => $this->nullableString($row['Segment2'] ?? null),
            ':Segment3' => $this->nullableString($row['Segment3'] ?? null),
            ':ProgramCode' => $this->nullableString($row['ProgramCode'] ?? null),
            ':EconomicCode' => $this->nullableString($row['EconomicCode'] ?? null),
            ':CurrencyCode' => $this->nullableString($row['CurrencyCode'] ?? null),
            ':BudgetAmount' => $this->nullableDecimal($row['BudgetAmount'] ?? null),
            ':ActualAmount' => $this->nullableDecimal($row['ActualAmount'] ?? $row['actual_value'] ?? null),
            ':AvailableBalance' => $this->nullableDecimal($row['AvailableBalance'] ?? null),
            ':ExecutionRate' => $this->nullableDecimal($row['ExecutionRate'] ?? null),
            ':CumulativeBudgetAmount' => $this->nullableDecimal($row['CumulativeBudgetAmount'] ?? null),
            ':CumulativeActualAmount' => $this->nullableDecimal($row['CumulativeActualAmount'] ?? null),
            ':CumulativeExecutionRate' => $this->nullableDecimal($row['CumulativeExecutionRate'] ?? null),
            ':ExpectedExecutionRate' => $this->nullableDecimal($row['ExpectedExecutionRate'] ?? null),
            ':VarianceAmount' => $this->nullableDecimal($row['VarianceAmount'] ?? $row['variance_amount'] ?? null),
            ':VariancePct' => $this->nullableDecimal($row['VariancePct'] ?? $row['variance_percent'] ?? null),
            ':RiskScore' => $this->nullableDecimal($row['RiskScore'] ?? $prediction['risk_score'] ?? null),
            ':RiskLabel' => $this->nullableString($row['RiskLabel'] ?? $row['risk_level'] ?? null),
            ':AnomalyTypeCode' => $this->nullableString($row['AnomalyTypeCode'] ?? null),
            ':RiskReason' => mb_substr((string) ($row['RiskReason'] ?? $row['interpretation'] ?? ''), 0, 500),
            ':DetailJson' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function sourceColumnMap(string $sourceObject): array
    {
        [$schema, $object] = $this->splitSourceObject($sourceObject);
        $stmt = $this->conn->prepare("
            SELECT c.name AS ColumnName
            FROM sys.columns c
            INNER JOIN sys.objects o ON o.object_id = c.object_id
            INNER JOIN sys.schemas s ON s.schema_id = o.schema_id
            WHERE s.name = :schemaName
              AND o.name = :objectName
        ");
        $stmt->execute([':schemaName' => $schema, ':objectName' => $object]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $name = (string) ($row['ColumnName'] ?? '');
            if ($name !== '') {
                $map[strtolower($name)] = $name;
            }
        }
        return $map;
    }

    private function predictionEntityColumn(array $available): ?string
    {
        foreach (['entitycode', 'sourcerowreference', 'budgetdataid', 'budgetledgeranalysisid', 'transactionreference'] as $candidate) {
            if (isset($available[$candidate])) {
                return $available[$candidate];
            }
        }
        if (isset($available['segment1'], $available['programcode'], $available['economiccode'])) {
            return null;
        }
        foreach (['programcode', 'economiccode', 'segment1'] as $candidate) {
            if (isset($available[$candidate])) {
                return $available[$candidate];
            }
        }
        return null;
    }

    private function predictionEntityCode(array $row, ?string $entityColumn, int $index): string
    {
        if ($entityColumn !== null && trim((string) ($row[$entityColumn] ?? '')) !== '') {
            return (string) $row[$entityColumn];
        }

        $parts = [];
        foreach (['FiscalYearID', 'PeriodNo', 'Segment1', 'ProgramCode', 'EconomicCode'] as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return $parts !== [] ? implode('|', $parts) : 'ROW-' . ($index + 1);
    }

    private function queryMLDrillRows(string $sourceObject, string $entityCode, array $rowContext, int $limit, bool $requireStrictFilters): array
    {
        $available = $this->sourceColumnMap($sourceObject);
        if ($available === []) {
            return [];
        }

        $filters = $this->mlDrillFilters($available, $entityCode, $rowContext);
        if ($filters['where'] === []) {
            return [];
        }
        if ($requireStrictFilters && (int) ($filters['match_count'] ?? 0) < 4) {
            return [];
        }

        $preferred = [
            'FiscalYearID',
            'BudgetVersionID',
            'PeriodNo',
            'EntityCode',
            'Segment1',
            'Segment2',
            'Segment3',
            'ProgramCode',
            'EconomicCode',
            'CurrencyCode',
            'BudgetAmount',
            'ReleasedAmount',
            'WarrantAmount',
            'CommitmentAmount',
            'ActualAmount',
            'AvailableBalance',
            'ExecutionRate',
            'CumulativeBudgetAmount',
            'CumulativeActualAmount',
            'CumulativeExecutionRate',
            'ExpectedExecutionRate',
            'VarianceAmount',
            'VariancePct',
            'RiskScore',
            'RiskLabel',
            'RiskReason',
        ];
        $selectColumns = [];
        foreach ($preferred as $column) {
            $key = strtolower($column);
            if (isset($available[$key])) {
                $selectColumns[$available[$key]] = true;
            }
        }
        if ($selectColumns === []) {
            foreach (array_slice(array_values($available), 0, 24) as $column) {
                $selectColumns[$column] = true;
            }
        }

        $select = [];
        foreach (array_keys($selectColumns) as $column) {
            $select[] = $this->quoteIdentifier($column);
        }

        $sql = 'SELECT TOP ' . $limit . ' ' . implode(', ', $select)
            . ' FROM ' . $this->quoteSourceObject($sourceObject)
            . ' WHERE ' . implode(' AND ', $filters['where'])
            . ' OPTION (RECOMPILE)';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($filters['params']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function mlDrillFilters(array $available, string $entityCode, array $rowContext): array
    {
        $where = [];
        $params = [];

        if ($entityCode !== '' && isset($available['entitycode'])) {
            $where[] = $this->quoteIdentifier($available['entitycode']) . ' = :EntityCode';
            $params[':EntityCode'] = $entityCode;
            return ['where' => $where, 'params' => $params, 'match_count' => 1];
        }

        $parts = array_map('trim', explode('|', $entityCode));
        $parsed = [];
        if (count($parts) >= 5) {
            $parsed = [
                'FiscalYearID' => $parts[0],
                'PeriodNo' => $parts[1],
                'Segment1' => $parts[2],
                'ProgramCode' => $parts[3],
                'EconomicCode' => $parts[4],
            ];
        } elseif (count($parts) === 3) {
            $parsed = [
                'Segment1' => $parts[0],
                'ProgramCode' => $parts[1],
                'EconomicCode' => $parts[2],
            ];
        }

        $matchCount = 0;
        foreach (['FiscalYearID', 'BudgetVersionID', 'PeriodNo', 'Segment1', 'ProgramCode', 'EconomicCode'] as $column) {
            $value = $parsed[$column] ?? $rowContext[$column] ?? null;
            $value = trim((string) $value);
            $key = strtolower($column);
            if ($value === '' || !isset($available[$key])) {
                continue;
            }
            $param = ':' . $column;
            $where[] = $this->quoteIdentifier($available[$key]) . ' = ' . $param;
            $params[$param] = $value;
            $matchCount++;
        }

        return ['where' => $where, 'params' => $params, 'match_count' => $matchCount];
    }

    private function mlContextWhere(array $available): ?string
    {
        $fiscalYearId = (int) SessionHelper::get('FiscalYearID', 0);
        if ($fiscalYearId <= 0) {
            $fiscalYearId = (int) SessionHelper::get('context.FiscalYearID', 0);
        }
        if ($fiscalYearId <= 0 || !isset($available['fiscalyearid'])) {
            return null;
        }
        return $this->quoteIdentifier($available['fiscalyearid']) . ' = ' . $fiscalYearId;
    }

    private function mlOrderBy(array $available): string
    {
        $parts = [];
        foreach (['riskscore', 'materialityamount', 'budgetamount', 'actualamount'] as $column) {
            if (isset($available[$column])) {
                $parts[] = $this->quoteIdentifier($available[$column]) . ' DESC';
            }
        }
        return $parts !== [] ? ' ORDER BY ' . implode(', ', $parts) : '';
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->conn->prepare("SELECT OBJECT_ID(:tableName, 'U')");
        $stmt->execute([':tableName' => $tableName]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $stmt = $this->conn->prepare('SELECT COL_LENGTH(:tableName, :columnName)');
        $stmt->execute([':tableName' => $tableName, ':columnName' => $columnName]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null;
    }

    private function requireMLFoundation(): void
    {
        if (!$this->tableExists('dbo.tblMLModels') || !$this->tableExists('dbo.tblMLTrainingRuns') || !$this->tableExists('dbo.tblMLPredictions')) {
            throw new \RuntimeException('ML model register tables are not installed.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        return (int) $value;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $text = str_replace(',', '', $text);
        if (preg_match('/^\.\d+$/', $text) === 1) {
            $text = '0' . $text;
        }
        if (!is_numeric($text)) {
            throw new \InvalidArgumentException('Numeric value must be numeric.');
        }
        $number = (float) $text;
        if (!is_finite($number)) {
            throw new \InvalidArgumentException('Numeric value must be finite.');
        }
        if (stripos($text, 'e') !== false) {
            $text = rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');
            if ($text === '-0') {
                $text = '0';
            }
        }
        return $text;
    }

    private function nullableDate(?string $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $text);
        return $date !== false ? $date->format('Y-m-d') : null;
    }

    private function nullableJson(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        json_decode($text, true);
        return json_last_error() === JSON_ERROR_NONE ? $text : null;
    }

    private function decodeFeatureColumns(mixed $json): array
    {
        $decoded = json_decode((string) $json, true);
        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $decoded), static fn (string $value): bool => trim($value) !== ''));
    }

    private function featureColumnsJson(string $featureColumns): ?string
    {
        $columns = [];
        foreach (preg_split('/[\r\n,]+/', $featureColumns) ?: [] as $column) {
            $column = trim($column);
            if ($column !== '') {
                $columns[] = $column;
            }
        }
        return $columns !== [] ? json_encode(array_values(array_unique($columns)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    }

    private function normaliseCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9_]+/', '_', $code) ?? $code;
        return trim($code, '_');
    }

    private function normaliseStatus(string $status): string
    {
        $status = $this->normaliseCode($status);
        return in_array($status, ['DRAFT', 'READY', 'TRAINING', 'TRAINED', 'CHANGES_REQUESTED', 'REVIEWED', 'APPROVED', 'RETIRED'], true) ? $status : 'DRAFT';
    }

    private function normaliseNullableObjectName(string $sourceObject): ?string
    {
        $sourceObject = trim($sourceObject);
        if ($sourceObject === '') {
            return null;
        }
        if (!preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\.)?[A-Za-z_][A-Za-z0-9_]*$/', $sourceObject)) {
            return null;
        }
        return str_contains($sourceObject, '.') ? $sourceObject : 'dbo.' . $sourceObject;
    }

    private function splitSourceObject(string $sourceObject): array
    {
        $sourceObject = $this->normaliseNullableObjectName($sourceObject) ?? '';
        $parts = explode('.', $sourceObject, 2);
        return [$parts[0] ?? 'dbo', $parts[1] ?? ''];
    }

    private function quoteSourceObject(string $sourceObject): string
    {
        [$schema, $object] = $this->splitSourceObject($sourceObject);
        return $this->quoteIdentifier($schema) . '.' . $this->quoteIdentifier($object);
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }
}
