<?php
declare(strict_types=1);

namespace App\Models;

final class BudgetExecutionModel
{
    private \PDO $conn;

    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
        $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function supportsVersionTyping(): bool
    {
        return $this->tableExists('dbo.tblVersionTypes');
    }

    public function supportsExecutionFoundation(): bool
    {
        return $this->tableExists('dbo.tblBeExecutionRolloverRun')
            && $this->tableExists('dbo.tblBeExecutionOpeningBalance');
    }

    public function supportsWarrantFoundation(): bool
    {
        return $this->tableExists('dbo.tblBeWarrant')
            && $this->tableExists('dbo.tblBeWarrantLine');
    }

    public function supportsReservationFoundation(): bool
    {
        return $this->tableExists('dbo.tblBeReservation')
            && $this->tableExists('dbo.tblBeReservationLine');
    }

    public function supportsSupplementaryFoundation(): bool
    {
        return $this->tableExists('dbo.tblBeSupplementaryBudget')
            && $this->tableExists('dbo.tblBeSupplementaryBudgetLine');
    }

    public function supportsCommitmentFoundation(): bool
    {
        return $this->tableExists('dbo.tblBeCommitment')
            && $this->tableExists('dbo.tblBeCommitmentLine');
    }

    public function supportsRieFoundation(): bool
    {
        return $this->tableExists('dbo.tblBeRie')
            && $this->tableExists('dbo.tblBeRieLine');
    }

    public function supportsWorkflowEngineFoundation(): bool
    {
        return $this->workflowEngine()->supportsWorkflowInstances();
    }

    public function getVersionTypeId(string $code): int
    {
        if (!$this->supportsVersionTyping()) {
            return 0;
        }

        $sql = "
            SELECT TOP 1 VersionTypeID
            FROM dbo.tblVersionTypes
            WHERE UPPER(VersionTypeCode) = UPPER(:code)
              AND ISNULL(ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([':code' => trim($code)]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    public function getVersion(int $fiscalYearId, int $versionId): ?array
    {
        if ($fiscalYearId <= 0 || $versionId <= 0) {
            return null;
        }

        $sql = "
            SELECT v.*,
                   vt.VersionTypeCode,
                   vt.VersionTypeName,
                   src.VersionLabel AS SourceVersionLabel
            FROM dbo.tblVersions v
            LEFT JOIN dbo.tblVersionTypes vt
                ON vt.VersionTypeID = v.VersionTypeID
            LEFT JOIN dbo.tblVersions src
                ON src.FiscalYearID = v.BaseFiscalYearID
               AND src.VersionID = v.BaseVersionID
            WHERE v.FiscalYearID = :fy
              AND v.VersionID = :ver
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':ver' => $versionId,
        ]);

        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function listExecutionVersions(int $fiscalYearId): array
    {
        $executionTypeId = $this->getVersionTypeId('EXECUTION');
        if ($fiscalYearId <= 0 || $executionTypeId <= 0) {
            return [];
        }

        $selectSummary = '';
        $joinSummary = '';
        if ($this->supportsExecutionFoundation()) {
            $selectSummary = ",
                   COALESCE(ob.OpeningLineCount, 0) AS OpeningLineCount,
                   COALESCE(ob.OpeningAmountTotal, 0) AS OpeningAmountTotal,
                   rr.StartedDate AS LastRolloverDate,
                   rr.RolloverStatusCode AS LastRolloverStatus";
            $joinSummary = "
            OUTER APPLY (
                SELECT COUNT(*) AS OpeningLineCount,
                       COALESCE(SUM(COALESCE(b.OpeningAmount, 0)), 0) AS OpeningAmountTotal
                FROM dbo.tblBeExecutionOpeningBalance b
                WHERE b.FiscalYearID = v.FiscalYearID
                  AND b.ExecutionVersionID = v.VersionID
                  AND ISNULL(b.ActiveFlag, 1) = 1
            ) ob
            OUTER APPLY (
                SELECT TOP 1 StartedDate, RolloverStatusCode
                FROM dbo.tblBeExecutionRolloverRun r
                WHERE r.FiscalYearID = v.FiscalYearID
                  AND r.ExecutionVersionID = v.VersionID
                ORDER BY r.StartedDate DESC, r.ExecutionRolloverRunID DESC
            ) rr";
        }

        $sql = "
            SELECT v.*,
                   vt.VersionTypeCode,
                   vt.VersionTypeName,
                   src.VersionLabel AS SourceVersionLabel
                   {$selectSummary}
            FROM dbo.tblVersions v
            INNER JOIN dbo.tblVersionTypes vt
                ON vt.VersionTypeID = v.VersionTypeID
            LEFT JOIN dbo.tblVersions src
                ON src.FiscalYearID = v.BaseFiscalYearID
               AND src.VersionID = v.BaseVersionID
            {$joinSummary}
            WHERE v.FiscalYearID = :fy
              AND v.VersionTypeID = :versionTypeId
              AND ISNULL(v.IsActive, 1) = 1
            ORDER BY ISNULL(v.IsDefault, 0) DESC, v.VersionID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':versionTypeId' => $executionTypeId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listSubmissionVersionCandidates(int $fiscalYearId): array
    {
        $submissionTypeId = $this->getVersionTypeId('SUBMISSION');
        if ($fiscalYearId <= 0 || $submissionTypeId <= 0) {
            return [];
        }

        $hasSubmissionTables = $this->tableExists('dbo.tblSbFundingSubmission')
            && $this->tableExists('dbo.tblSbFundingSubmissionLine');

        if (!$hasSubmissionTables) {
            $sql = "
                SELECT v.VersionID,
                       v.FiscalYearID,
                       v.VersionLabel,
                       v.VersionStatus,
                       CAST(0 AS INT) AS ApprovedLineCount,
                       CAST(0 AS DECIMAL(19,6)) AS ApprovedAmount
                FROM dbo.tblVersions v
                WHERE v.FiscalYearID = :fy
                  AND v.VersionTypeID = :versionTypeId
                  AND ISNULL(v.IsActive, 1) = 1
                ORDER BY ISNULL(v.IsDefault, 0) DESC, v.VersionID ASC
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':fy' => $fiscalYearId,
                ':versionTypeId' => $submissionTypeId,
            ]);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        $sql = "
            SELECT v.VersionID,
                   v.FiscalYearID,
                   v.VersionLabel,
                   v.VersionStatus,
                   COUNT(DISTINCT CASE
                       WHEN s.SubmissionStatusCode IN (N'APPROVED', N'PARTIAL', N'FUNDED')
                        AND l.LineStatusCode IN (N'APPROVED', N'PARTIAL', N'FUNDED')
                        AND COALESCE(l.CurrentYearApprovedAmount, 0) > 0
                       THEN l.StrategicFundingSubmissionLineID
                   END) AS ApprovedLineCount,
                   COALESCE(SUM(CASE
                       WHEN s.SubmissionStatusCode IN (N'APPROVED', N'PARTIAL', N'FUNDED')
                        AND l.LineStatusCode IN (N'APPROVED', N'PARTIAL', N'FUNDED')
                       THEN COALESCE(l.CurrentYearApprovedAmount, 0)
                       ELSE 0
                   END), 0) AS ApprovedAmount
            FROM dbo.tblVersions v
            LEFT JOIN dbo.tblSbFundingSubmission s
                ON s.FiscalYearID = v.FiscalYearID
               AND s.VersionID = v.VersionID
               AND ISNULL(s.ActiveFlag, 1) = 1
            LEFT JOIN dbo.tblSbFundingSubmissionLine l
                ON l.StrategicFundingSubmissionID = s.StrategicFundingSubmissionID
               AND ISNULL(l.ActiveFlag, 1) = 1
            WHERE v.FiscalYearID = :fy
              AND v.VersionTypeID = :versionTypeId
              AND ISNULL(v.IsActive, 1) = 1
            GROUP BY v.VersionID, v.FiscalYearID, v.VersionLabel, v.VersionStatus, v.IsDefault
            ORDER BY ISNULL(v.IsDefault, 0) DESC, v.VersionID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':versionTypeId' => $submissionTypeId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function createExecutionVersion(array $data): int
    {
        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $label = trim((string) ($data['VersionLabel'] ?? ''));
        $status = strtoupper(trim((string) ($data['VersionStatus'] ?? 'OPEN')));
        $isDefault = !empty($data['IsDefault']) ? 1 : 0;
        $userId = (int) ($data['UserID'] ?? 1);

        if ($fiscalYearId <= 0) {
            throw new \RuntimeException('Fiscal year is required.');
        }
        if ($label === '') {
            throw new \RuntimeException('Execution version label is required.');
        }

        $executionTypeId = $this->getVersionTypeId('EXECUTION');
        if ($executionTypeId <= 0) {
            throw new \RuntimeException('Execution version type is not configured. Run the version typing migration first.');
        }

        $validStatuses = ['OPEN', 'ACTIVE', 'SUSPENDED', 'CLOSED'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \RuntimeException('Execution version status is not valid.');
        }

        $this->conn->beginTransaction();
        try {
            $versionId = $this->nextVersionId($fiscalYearId);

            if ($isDefault === 1) {
                $clearSql = "
                    UPDATE dbo.tblVersions
                    SET IsDefault = 0,
                        UpdatedAt = SYSDATETIME()
                    WHERE FiscalYearID = :fy
                      AND VersionTypeID = :versionTypeId
                      AND ISNULL(IsActive, 1) = 1
                ";
                $clearStmt = $this->conn->prepare($clearSql);
                $clearStmt->execute([
                    ':fy' => $fiscalYearId,
                    ':versionTypeId' => $executionTypeId,
                ]);
            }

            $sql = "
                INSERT INTO dbo.tblVersions (
                    VersionID,
                    FiscalYearID,
                    VersionLabel,
                    VersionTypeID,
                    VersionStatus,
                    BaseFiscalYearID,
                    BaseVersionID,
                    ActualsPeriodID,
                    BaseCurrency,
                    CeilingsOn,
                    IsActive,
                    IsDefault,
                    CreatedAt,
                    UpdatedAt
                )
                VALUES (
                    :versionId,
                    :fiscalYearId,
                    :versionLabel,
                    :versionTypeId,
                    :versionStatus,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    1,
                    :isDefault,
                    SYSDATETIME(),
                    SYSDATETIME()
                )
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':versionId' => $versionId,
                ':fiscalYearId' => $fiscalYearId,
                ':versionLabel' => $label,
                ':versionTypeId' => $executionTypeId,
                ':versionStatus' => $status,
                ':isDefault' => $isDefault,
            ]);

            $this->conn->commit();
            return $versionId;
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function rolloverExecutionVersion(int $fiscalYearId, int $executionVersionId, int $sourceVersionId, int $userId): array
    {
        if (!$this->supportsExecutionFoundation()) {
            throw new \RuntimeException('Budget Execution foundation tables are not installed. Run create_budget_execution_foundation_v1.sql first.');
        }

        $executionVersion = $this->getVersion($fiscalYearId, $executionVersionId);
        if ($executionVersion === null || strtoupper(trim((string) ($executionVersion['VersionTypeCode'] ?? ''))) !== 'EXECUTION') {
            throw new \RuntimeException('Target version is not a valid execution version.');
        }

        $sourceVersion = $this->getVersion($fiscalYearId, $sourceVersionId);
        if ($sourceVersion === null || strtoupper(trim((string) ($sourceVersion['VersionTypeCode'] ?? ''))) !== 'SUBMISSION') {
            throw new \RuntimeException('Source version is not a valid submission version.');
        }

        $existingSql = "
            SELECT COUNT(*)
            FROM dbo.tblBeExecutionOpeningBalance
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
              AND ISNULL(ActiveFlag, 1) = 1
        ";
        $existingStmt = $this->conn->prepare($existingSql);
        $existingStmt->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        if ((int) ($existingStmt->fetchColumn() ?: 0) > 0) {
            throw new \RuntimeException('Opening balances already exist for this execution version. Rerollover is blocked once execution baseline data has been created.');
        }

        $sourceCountSql = "
            SELECT COUNT(*)
            FROM dbo.tblSbFundingSubmission s
            INNER JOIN dbo.tblSbFundingSubmissionLine l
                ON l.StrategicFundingSubmissionID = s.StrategicFundingSubmissionID
            WHERE s.FiscalYearID = :fy
              AND s.VersionID = :sourceVersionId
              AND ISNULL(s.ActiveFlag, 1) = 1
              AND ISNULL(l.ActiveFlag, 1) = 1
              AND s.SubmissionStatusCode IN (N'APPROVED', N'PARTIAL', N'FUNDED')
              AND l.LineStatusCode IN (N'APPROVED', N'PARTIAL', N'FUNDED')
              AND COALESCE(l.CurrentYearApprovedAmount, 0) > 0
        ";
        $sourceCountStmt = $this->conn->prepare($sourceCountSql);
        $sourceCountStmt->execute([
            ':fy' => $fiscalYearId,
            ':sourceVersionId' => $sourceVersionId,
        ]);
        $sourceLineCount = (int) ($sourceCountStmt->fetchColumn() ?: 0);
        if ($sourceLineCount <= 0) {
            throw new \RuntimeException('The source submission version has no approved current-year lines available for rollover.');
        }

        $this->conn->beginTransaction();
        try {
            $insertRunSql = "
                INSERT INTO dbo.tblBeExecutionRolloverRun (
                    FiscalYearID,
                    ExecutionVersionID,
                    SourceFiscalYearID,
                    SourceVersionID,
                    RolloverStatusCode,
                    SourceLineCount,
                    InsertedBalanceLineCount,
                    TotalOpeningAmount,
                    Notes,
                    StartedBy,
                    StartedDate
                )
                OUTPUT INSERTED.ExecutionRolloverRunID
                VALUES (
                    :fy,
                    :executionVersionId,
                    :sourceFy,
                    :sourceVersionId,
                    N'STARTED',
                    :sourceLineCount,
                    0,
                    0,
                    NULL,
                    :userId,
                    SYSDATETIME()
                )
            ";
            $runStmt = $this->conn->prepare($insertRunSql);
            $runStmt->execute([
                ':fy' => $fiscalYearId,
                ':executionVersionId' => $executionVersionId,
                ':sourceFy' => $fiscalYearId,
                ':sourceVersionId' => $sourceVersionId,
                ':sourceLineCount' => $sourceLineCount,
                ':userId' => $userId,
            ]);
            $runId = (int) ($runStmt->fetchColumn() ?: 0);
            if ($runId <= 0) {
                throw new \RuntimeException('Could not create execution rollover run.');
            }

            $versionUpdateSql = "
                UPDATE dbo.tblVersions
                SET BaseFiscalYearID = :sourceFy,
                    BaseVersionID = :sourceVersionId,
                    VersionStatus = CASE
                        WHEN NULLIF(LTRIM(RTRIM(COALESCE(VersionStatus, N''))), N'') IS NULL THEN N'OPEN'
                        ELSE VersionStatus
                    END,
                    UpdatedAt = SYSDATETIME()
                WHERE FiscalYearID = :fy
                  AND VersionID = :executionVersionId
            ";
            $versionUpdateStmt = $this->conn->prepare($versionUpdateSql);
            $versionUpdateStmt->execute([
                ':sourceFy' => $fiscalYearId,
                ':sourceVersionId' => $sourceVersionId,
                ':fy' => $fiscalYearId,
                ':executionVersionId' => $executionVersionId,
            ]);

            $insertBalanceSql = "
                INSERT INTO dbo.tblBeExecutionOpeningBalance (
                    ExecutionRolloverRunID,
                    FiscalYearID,
                    ExecutionVersionID,
                    SourceFiscalYearID,
                    SourceVersionID,
                    SourceSubmissionID,
                    SourceSubmissionLineID,
                    DataObjectCode,
                    OrgUnitID,
                    SectorID,
                    ProgramID,
                    SubProgramID,
                    ProjectID,
                    ActivityID,
                    FundingTypeID,
                    FundingSourceID,
                    EconomicItemID,
                    BidTitle,
                    OpeningAmount,
                    CurrentAuthorizedAmount,
                    ActiveFlag,
                    CreatedBy,
                    CreatedDate
                )
                SELECT :selectRunId,
                       :selectFy,
                       :selectExecutionVersionId,
                       :selectSourceFy,
                       :selectSourceVersionId,
                       s.StrategicFundingSubmissionID,
                       l.StrategicFundingSubmissionLineID,
                       s.DataObjectCode,
                       COALESCE(l.OrgUnitID, s.OrgUnitID),
                       l.SectorID,
                       l.ProgramID,
                       l.SubProgramID,
                       l.ProjectID,
                       l.ActivityID,
                       l.FundingTypeID,
                       l.FundingSourceID,
                       l.EconomicItemID,
                       l.BidTitle,
                       CAST(COALESCE(l.CurrentYearApprovedAmount, 0) AS DECIMAL(19,6)),
                       CAST(COALESCE(l.CurrentYearApprovedAmount, 0) AS DECIMAL(19,6)),
                       1,
                       :selectUserId,
                       SYSDATETIME()
                FROM dbo.tblSbFundingSubmission s
                INNER JOIN dbo.tblSbFundingSubmissionLine l
                    ON l.StrategicFundingSubmissionID = s.StrategicFundingSubmissionID
                WHERE s.FiscalYearID = :whereFy
                  AND s.VersionID = :whereSourceVersionId
                  AND ISNULL(s.ActiveFlag, 1) = 1
                  AND ISNULL(l.ActiveFlag, 1) = 1
                  AND s.SubmissionStatusCode IN (N'APPROVED', N'PARTIAL', N'FUNDED')
                  AND l.LineStatusCode IN (N'APPROVED', N'PARTIAL', N'FUNDED')
                  AND COALESCE(l.CurrentYearApprovedAmount, 0) > 0
            ";
            $insertBalanceStmt = $this->conn->prepare($insertBalanceSql);
            $insertBalanceStmt->execute([
                ':selectRunId' => $runId,
                ':selectFy' => $fiscalYearId,
                ':selectExecutionVersionId' => $executionVersionId,
                ':selectSourceFy' => $fiscalYearId,
                ':selectSourceVersionId' => $sourceVersionId,
                ':selectUserId' => $userId,
                ':whereFy' => $fiscalYearId,
                ':whereSourceVersionId' => $sourceVersionId,
            ]);

            $summarySql = "
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(OpeningAmount, 0)), 0) AS TotalAmount
                FROM dbo.tblBeExecutionOpeningBalance
                WHERE ExecutionRolloverRunID = :runId
            ";
            $summaryStmt = $this->conn->prepare($summarySql);
            $summaryStmt->execute([':runId' => $runId]);
            $summary = $summaryStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $insertedLineCount = (int) ($summary['LineCount'] ?? 0);
            $totalAmount = (float) ($summary['TotalAmount'] ?? 0);

            $updateRunSql = "
                UPDATE dbo.tblBeExecutionRolloverRun
                SET RolloverStatusCode = N'COMPLETED',
                    InsertedBalanceLineCount = :insertedCount,
                    TotalOpeningAmount = :totalAmount,
                    CompletedDate = SYSDATETIME()
                WHERE ExecutionRolloverRunID = :runId
            ";
            $updateRunStmt = $this->conn->prepare($updateRunSql);
            $updateRunStmt->execute([
                ':insertedCount' => $insertedLineCount,
                ':totalAmount' => $totalAmount,
                ':runId' => $runId,
            ]);

            $this->conn->commit();

            return [
                'ExecutionRolloverRunID' => $runId,
                'SourceLineCount' => $sourceLineCount,
                'InsertedBalanceLineCount' => $insertedLineCount,
                'TotalOpeningAmount' => $totalAmount,
            ];
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function listRolloverRuns(int $fiscalYearId, ?int $executionVersionId = null): array
    {
        if (!$this->supportsExecutionFoundation() || $fiscalYearId <= 0) {
            return [];
        }

        $sql = "
            SELECT r.*,
                   execv.VersionLabel AS ExecutionVersionLabel,
                   src.VersionLabel AS SourceVersionLabel
            FROM dbo.tblBeExecutionRolloverRun r
            INNER JOIN dbo.tblVersions execv
                ON execv.FiscalYearID = r.FiscalYearID
               AND execv.VersionID = r.ExecutionVersionID
            INNER JOIN dbo.tblVersions src
                ON src.FiscalYearID = r.SourceFiscalYearID
               AND src.VersionID = r.SourceVersionID
            WHERE r.FiscalYearID = :fy";
        $params = [':fy' => $fiscalYearId];
        if ($executionVersionId !== null && $executionVersionId > 0) {
            $sql .= "
              AND r.ExecutionVersionID = :executionVersionId";
            $params[':executionVersionId'] = $executionVersionId;
        }
        $sql .= "
            ORDER BY r.StartedDate DESC, r.ExecutionRolloverRunID DESC";

        $st = $this->conn->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getOpeningBalanceSummary(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [
                'LineCount' => 0,
                'OpeningAmountTotal' => 0.0,
                'CurrentAuthorizedAmountTotal' => 0.0,
                'SupplementaryAmountTotal' => 0.0,
                'ReleasedAmountTotal' => 0.0,
                'ReservedAmountTotal' => 0.0,
                'CommittedAmountTotal' => 0.0,
                'AvailableReleasedAmountTotal' => 0.0,
            ];
        }

        $supportsSupplementaries = $this->supportsSupplementaryFoundation();
        $supportsWarrants = $this->supportsWarrantFoundation();
        $supportsReservations = $this->supportsReservationFoundation();
        $supportsCommitments = $this->supportsCommitmentFoundation();
        $releaseSelect = ",
                   COALESCE(SUM(COALESCE(sup.SupplementaryAmountTotal, 0)), 0) AS SupplementaryAmountTotal"
            . ($supportsWarrants
            ? ",
                   COALESCE(SUM(COALESCE(rel.ReleaseAmountTotal, 0)), 0) AS ReleasedAmountTotal,
                   COALESCE(SUM(COALESCE(res.ReservedAmountTotal, 0)), 0) AS ReservedAmountTotal,
                   COALESCE(SUM(COALESCE(com.CommitmentAmountTotal, 0)), 0) AS CommittedAmountTotal,
                   COALESCE(SUM(COALESCE(rel.ReleaseAmountTotal, 0) - COALESCE(res.ReservedAmountTotal, 0) - COALESCE(com.CommitmentAmountTotal, 0)), 0) AS AvailableReleasedAmountTotal"
            : ",
                   CAST(0 AS DECIMAL(19,6)) AS ReleasedAmountTotal,
                   CAST(0 AS DECIMAL(19,6)) AS ReservedAmountTotal,
                   CAST(0 AS DECIMAL(19,6)) AS CommittedAmountTotal,
                   CAST(0 AS DECIMAL(19,6)) AS AvailableReleasedAmountTotal");
        $supplementaryJoin = $supportsSupplementaries
            ? "
            LEFT JOIN (
                SELECT sl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(sl.AdjustmentAmount, 0)) AS SupplementaryAmountTotal
                FROM dbo.tblBeSupplementaryBudgetLine sl
                INNER JOIN dbo.tblBeSupplementaryBudget s
                    ON s.SupplementaryBudgetID = sl.SupplementaryBudgetID
                WHERE ISNULL(sl.ActiveFlag, 1) = 1
                  AND ISNULL(s.ActiveFlag, 1) = 1
                  AND s.SupplementaryStatusCode = N'APPROVED'
                GROUP BY sl.ExecutionOpeningBalanceID
            ) sup
                ON sup.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "
            LEFT JOIN (
                SELECT CAST(NULL AS INT) AS ExecutionOpeningBalanceID,
                       CAST(0 AS DECIMAL(19,6)) AS SupplementaryAmountTotal
            ) sup
                ON 1 = 0";
        $releaseJoin = $supportsWarrants
            ? "
            LEFT JOIN (
                SELECT wl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(wl.ReleaseAmount, 0)) AS ReleaseAmountTotal
                FROM dbo.tblBeWarrantLine wl
                INNER JOIN dbo.tblBeWarrant w
                    ON w.WarrantID = wl.WarrantID
                WHERE ISNULL(wl.ActiveFlag, 1) = 1
                  AND ISNULL(w.ActiveFlag, 1) = 1
                  AND w.WarrantStatusCode = N'APPROVED'
                GROUP BY wl.ExecutionOpeningBalanceID
            ) rel
                ON rel.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : '';
        $reservationJoin = $supportsReservations
            ? "
            LEFT JOIN (
                SELECT rl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(rl.ReservationAmount, 0)) AS ReservedAmountTotal
                FROM dbo.tblBeReservationLine rl
                INNER JOIN dbo.tblBeReservation r
                    ON r.ReservationID = rl.ReservationID
                WHERE ISNULL(rl.ActiveFlag, 1) = 1
                  AND ISNULL(r.ActiveFlag, 1) = 1
                  AND r.ReservationStatusCode = N'APPROVED'
                GROUP BY rl.ExecutionOpeningBalanceID
            ) res
                ON res.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "
            LEFT JOIN (
                SELECT CAST(NULL AS INT) AS ExecutionOpeningBalanceID,
                       CAST(0 AS DECIMAL(19,6)) AS ReservedAmountTotal
            ) res
                ON 1 = 0";
        $commitmentJoin = $supportsCommitments
            ? "
            LEFT JOIN (
                SELECT cl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(cl.CommitmentAmount, 0)) AS CommitmentAmountTotal
                FROM dbo.tblBeCommitmentLine cl
                INNER JOIN dbo.tblBeCommitment c
                    ON c.CommitmentID = cl.CommitmentID
                WHERE ISNULL(cl.ActiveFlag, 1) = 1
                  AND ISNULL(c.ActiveFlag, 1) = 1
                  AND c.CommitmentStatusCode = N'APPROVED'
                GROUP BY cl.ExecutionOpeningBalanceID
            ) com
                ON com.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "
            LEFT JOIN (
                SELECT CAST(NULL AS INT) AS ExecutionOpeningBalanceID,
                       CAST(0 AS DECIMAL(19,6)) AS CommitmentAmountTotal
            ) com
                ON 1 = 0";

        $sql = "
            SELECT COUNT(*) AS LineCount,
                   COALESCE(SUM(COALESCE(OpeningAmount, 0)), 0) AS OpeningAmountTotal,
                   COALESCE(SUM(COALESCE(CurrentAuthorizedAmount, 0)), 0) AS CurrentAuthorizedAmountTotal
                   {$releaseSelect}
            FROM dbo.tblBeExecutionOpeningBalance b
            {$supplementaryJoin}
            {$releaseJoin}
            {$reservationJoin}
            {$commitmentJoin}
            WHERE b.FiscalYearID = :fy
              AND b.ExecutionVersionID = :executionVersionId
              AND ISNULL(b.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);

        return $st->fetch(\PDO::FETCH_ASSOC) ?: [
            'LineCount' => 0,
            'OpeningAmountTotal' => 0.0,
            'CurrentAuthorizedAmountTotal' => 0.0,
            'SupplementaryAmountTotal' => 0.0,
            'ReleasedAmountTotal' => 0.0,
            'ReservedAmountTotal' => 0.0,
            'CommittedAmountTotal' => 0.0,
            'AvailableReleasedAmountTotal' => 0.0,
        ];
    }

    public function listOpeningBalanceRows(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $supportsSupplementaries = $this->supportsSupplementaryFoundation();
        $supportsWarrants = $this->supportsWarrantFoundation();
        $supportsReservations = $this->supportsReservationFoundation();
        $supportsCommitments = $this->supportsCommitmentFoundation();
        $releaseSelect = "COALESCE(sup.SupplementaryAmountTotal, 0) AS SupplementaryAmountTotal,
                   "
            . ($supportsWarrants
            ? "COALESCE(rel.ReleaseAmountTotal, 0) AS ReleasedAmountTotal,
                   COALESCE(res.ReservedAmountTotal, 0) AS ReservedAmountTotal,
                   COALESCE(com.CommitmentAmountTotal, 0) AS CommittedAmountTotal,
                   COALESCE(rel.ReleaseAmountTotal, 0) - COALESCE(res.ReservedAmountTotal, 0) - COALESCE(com.CommitmentAmountTotal, 0) AS AvailableReleasedAmount"
            : "CAST(0 AS DECIMAL(19,6)) AS ReleasedAmountTotal,
                   CAST(0 AS DECIMAL(19,6)) AS ReservedAmountTotal,
                   CAST(0 AS DECIMAL(19,6)) AS CommittedAmountTotal,
                   CAST(0 AS DECIMAL(19,6)) AS AvailableReleasedAmount");
        $supplementaryJoin = $supportsSupplementaries
            ? "
            LEFT JOIN (
                SELECT sl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(sl.AdjustmentAmount, 0)) AS SupplementaryAmountTotal
                FROM dbo.tblBeSupplementaryBudgetLine sl
                INNER JOIN dbo.tblBeSupplementaryBudget s
                    ON s.SupplementaryBudgetID = sl.SupplementaryBudgetID
                WHERE ISNULL(sl.ActiveFlag, 1) = 1
                  AND ISNULL(s.ActiveFlag, 1) = 1
                  AND s.SupplementaryStatusCode = N'APPROVED'
                GROUP BY sl.ExecutionOpeningBalanceID
            ) sup
                ON sup.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "
            LEFT JOIN (
                SELECT CAST(NULL AS INT) AS ExecutionOpeningBalanceID,
                       CAST(0 AS DECIMAL(19,6)) AS SupplementaryAmountTotal
            ) sup
                ON 1 = 0";
        $releaseJoin = $supportsWarrants
            ? "
            LEFT JOIN (
                SELECT wl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(wl.ReleaseAmount, 0)) AS ReleaseAmountTotal
                FROM dbo.tblBeWarrantLine wl
                INNER JOIN dbo.tblBeWarrant w
                    ON w.WarrantID = wl.WarrantID
                WHERE ISNULL(wl.ActiveFlag, 1) = 1
                  AND ISNULL(w.ActiveFlag, 1) = 1
                  AND w.WarrantStatusCode = N'APPROVED'
                GROUP BY wl.ExecutionOpeningBalanceID
            ) rel
                ON rel.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : '';
        $reservationJoin = $supportsReservations
            ? "
            LEFT JOIN (
                SELECT rl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(rl.ReservationAmount, 0)) AS ReservedAmountTotal
                FROM dbo.tblBeReservationLine rl
                INNER JOIN dbo.tblBeReservation r
                    ON r.ReservationID = rl.ReservationID
                WHERE ISNULL(rl.ActiveFlag, 1) = 1
                  AND ISNULL(r.ActiveFlag, 1) = 1
                  AND r.ReservationStatusCode = N'APPROVED'
                GROUP BY rl.ExecutionOpeningBalanceID
            ) res
                ON res.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "
            LEFT JOIN (
                SELECT CAST(NULL AS INT) AS ExecutionOpeningBalanceID,
                       CAST(0 AS DECIMAL(19,6)) AS ReservedAmountTotal
            ) res
                ON 1 = 0";
        $commitmentJoin = $supportsCommitments
            ? "
            LEFT JOIN (
                SELECT cl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(cl.CommitmentAmount, 0)) AS CommitmentAmountTotal
                FROM dbo.tblBeCommitmentLine cl
                INNER JOIN dbo.tblBeCommitment c
                    ON c.CommitmentID = cl.CommitmentID
                WHERE ISNULL(cl.ActiveFlag, 1) = 1
                  AND ISNULL(c.ActiveFlag, 1) = 1
                  AND c.CommitmentStatusCode = N'APPROVED'
                GROUP BY cl.ExecutionOpeningBalanceID
            ) com
                ON com.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "
            LEFT JOIN (
                SELECT CAST(NULL AS INT) AS ExecutionOpeningBalanceID,
                       CAST(0 AS DECIMAL(19,6)) AS CommitmentAmountTotal
            ) com
                ON 1 = 0";

        $sql = "
            SELECT b.*,
                   {$releaseSelect},
                   run.StartedDate AS RolloverStartedDate
            FROM dbo.tblBeExecutionOpeningBalance b
            {$supplementaryJoin}
            {$releaseJoin}
            {$reservationJoin}
            {$commitmentJoin}
            LEFT JOIN dbo.tblBeExecutionRolloverRun run
                ON run.ExecutionRolloverRunID = b.ExecutionRolloverRunID
            WHERE b.FiscalYearID = :fy
              AND b.ExecutionVersionID = :executionVersionId
              AND ISNULL(b.ActiveFlag, 1) = 1
            ORDER BY b.DataObjectCode ASC,
                     b.SectorID ASC,
                     b.ProgramID ASC,
                     b.ProjectID ASC,
                     b.SourceSubmissionLineID ASC,
                     b.ExecutionOpeningBalanceID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupplementaryModuleSummary(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || !$this->supportsSupplementaryFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [
                'SupplementaryCount' => 0,
                'ApprovedSupplementaryCount' => 0,
                'ApprovedNetAdjustmentTotal' => 0.0,
                'PlannedNetAdjustmentTotal' => 0.0,
                'ApprovedIncreaseAmountTotal' => 0.0,
                'ApprovedReductionAmountTotal' => 0.0,
            ];
        }

        $sql = "
            SELECT COUNT(*) AS SupplementaryCount,
                   SUM(CASE WHEN s.SupplementaryStatusCode = N'APPROVED' THEN 1 ELSE 0 END) AS ApprovedSupplementaryCount,
                   COALESCE(SUM(COALESCE(CASE WHEN s.SupplementaryStatusCode = N'APPROVED' THEN line.TotalAdjustmentAmount ELSE 0 END, 0)), 0) AS ApprovedNetAdjustmentTotal,
                   COALESCE(SUM(COALESCE(line.TotalAdjustmentAmount, 0)), 0) AS PlannedNetAdjustmentTotal,
                   COALESCE(SUM(COALESCE(CASE WHEN s.SupplementaryStatusCode = N'APPROVED' THEN line.TotalIncreaseAmount ELSE 0 END, 0)), 0) AS ApprovedIncreaseAmountTotal,
                   COALESCE(SUM(COALESCE(CASE WHEN s.SupplementaryStatusCode = N'APPROVED' THEN line.TotalReductionAmount ELSE 0 END, 0)), 0) AS ApprovedReductionAmountTotal
            FROM dbo.tblBeSupplementaryBudget s
            OUTER APPLY (
                SELECT SUM(COALESCE(sl.AdjustmentAmount, 0)) AS TotalAdjustmentAmount,
                       SUM(CASE WHEN COALESCE(sl.AdjustmentAmount, 0) > 0 THEN COALESCE(sl.AdjustmentAmount, 0) ELSE 0 END) AS TotalIncreaseAmount,
                       SUM(CASE WHEN COALESCE(sl.AdjustmentAmount, 0) < 0 THEN ABS(COALESCE(sl.AdjustmentAmount, 0)) ELSE 0 END) AS TotalReductionAmount
                FROM dbo.tblBeSupplementaryBudgetLine sl
                WHERE sl.SupplementaryBudgetID = s.SupplementaryBudgetID
                  AND ISNULL(sl.ActiveFlag, 1) = 1
            ) line
            WHERE s.FiscalYearID = :fy
              AND s.ExecutionVersionID = :executionVersionId
              AND ISNULL(s.ActiveFlag, 1) = 1
              AND s.SupplementaryStatusCode <> N'CANCELLED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);

        return $st->fetch(\PDO::FETCH_ASSOC) ?: [
            'SupplementaryCount' => 0,
            'ApprovedSupplementaryCount' => 0,
            'ApprovedNetAdjustmentTotal' => 0.0,
            'PlannedNetAdjustmentTotal' => 0.0,
            'ApprovedIncreaseAmountTotal' => 0.0,
            'ApprovedReductionAmountTotal' => 0.0,
        ];
    }

    public function listSupplementaries(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsSupplementaryFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $sql = "
            SELECT s.*,
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalAdjustmentAmount, 0) AS TotalAdjustmentAmount,
                   COALESCE(line.TotalIncreaseAmount, 0) AS TotalIncreaseAmount,
                   COALESCE(line.TotalReductionAmount, 0) AS TotalReductionAmount
            FROM dbo.tblBeSupplementaryBudget s
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(sl.AdjustmentAmount, 0)), 0) AS TotalAdjustmentAmount,
                       COALESCE(SUM(CASE WHEN COALESCE(sl.AdjustmentAmount, 0) > 0 THEN COALESCE(sl.AdjustmentAmount, 0) ELSE 0 END), 0) AS TotalIncreaseAmount,
                       COALESCE(SUM(CASE WHEN COALESCE(sl.AdjustmentAmount, 0) < 0 THEN ABS(COALESCE(sl.AdjustmentAmount, 0)) ELSE 0 END), 0) AS TotalReductionAmount
                FROM dbo.tblBeSupplementaryBudgetLine sl
                WHERE sl.SupplementaryBudgetID = s.SupplementaryBudgetID
                  AND ISNULL(sl.ActiveFlag, 1) = 1
            ) line
            WHERE s.FiscalYearID = :fy
              AND s.ExecutionVersionID = :executionVersionId
              AND ISNULL(s.ActiveFlag, 1) = 1
            ORDER BY s.CreatedDate DESC, s.SupplementaryBudgetID DESC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getSupplementary(int $fiscalYearId, int $executionVersionId, int $supplementaryBudgetId): ?array
    {
        if (!$this->supportsSupplementaryFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $supplementaryBudgetId <= 0) {
            return null;
        }

        $sql = "
            SELECT s.*,
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalAdjustmentAmount, 0) AS TotalAdjustmentAmount,
                   COALESCE(line.TotalIncreaseAmount, 0) AS TotalIncreaseAmount,
                   COALESCE(line.TotalReductionAmount, 0) AS TotalReductionAmount
            FROM dbo.tblBeSupplementaryBudget s
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(sl.AdjustmentAmount, 0)), 0) AS TotalAdjustmentAmount,
                       COALESCE(SUM(CASE WHEN COALESCE(sl.AdjustmentAmount, 0) > 0 THEN COALESCE(sl.AdjustmentAmount, 0) ELSE 0 END), 0) AS TotalIncreaseAmount,
                       COALESCE(SUM(CASE WHEN COALESCE(sl.AdjustmentAmount, 0) < 0 THEN ABS(COALESCE(sl.AdjustmentAmount, 0)) ELSE 0 END), 0) AS TotalReductionAmount
                FROM dbo.tblBeSupplementaryBudgetLine sl
                WHERE sl.SupplementaryBudgetID = s.SupplementaryBudgetID
                  AND ISNULL(sl.ActiveFlag, 1) = 1
            ) line
            WHERE s.FiscalYearID = :fy
              AND s.ExecutionVersionID = :executionVersionId
              AND s.SupplementaryBudgetID = :supplementaryBudgetId
              AND ISNULL(s.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':supplementaryBudgetId' => $supplementaryBudgetId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createSupplementary(array $data): int
    {
        if (!$this->supportsSupplementaryFoundation()) {
            throw new \RuntimeException('Budget Execution Supplementary tables are not installed. Run create_budget_execution_supplementaries_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $title = trim((string) ($data['SupplementaryTitle'] ?? ''));
        $supplementaryDate = $this->normalizeDate($data['SupplementaryDate'] ?? null);
        $effectiveDate = $this->normalizeDate($data['EffectiveDate'] ?? null);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $scopeDataObjectCode = $this->nullableString($data['ScopeDataObjectCode'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($fiscalYearId <= 0 || $executionVersionId <= 0) {
            throw new \RuntimeException('Execution context is required for the supplementary budget.');
        }
        if ($title === '') {
            throw new \RuntimeException('Supplementary title is required.');
        }

        $executionVersion = $this->getVersion($fiscalYearId, $executionVersionId);
        if ($executionVersion === null || strtoupper(trim((string) ($executionVersion['VersionTypeCode'] ?? ''))) !== 'EXECUTION') {
            throw new \RuntimeException('Supplementary budgets can only be created inside an execution version.');
        }

        $supplementaryNo = $this->nextSupplementaryNumber($fiscalYearId, $executionVersionId);

        $sql = "
            INSERT INTO dbo.tblBeSupplementaryBudget (
                FiscalYearID,
                ExecutionVersionID,
                SupplementaryNo,
                SupplementaryTitle,
                SupplementaryStatusCode,
                SupplementaryDate,
                EffectiveDate,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.SupplementaryBudgetID
            VALUES (
                :fy,
                :executionVersionId,
                :supplementaryNo,
                :supplementaryTitle,
                N'DRAFT',
                :supplementaryDate,
                :effectiveDate,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':supplementaryNo' => $supplementaryNo,
            ':supplementaryTitle' => $title,
            ':supplementaryDate' => $supplementaryDate ?? date('Y-m-d'),
            ':effectiveDate' => $effectiveDate,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        $supplementaryBudgetId = (int) ($st->fetchColumn() ?: 0);
        if ($supplementaryBudgetId <= 0) {
            throw new \RuntimeException('Supplementary budget could not be created.');
        }

        if ($this->supportsWorkflowEngineFoundation()) {
            $this->workflowEngine()->ensureWorkflowInstance([
                'WorkflowAreaCode' => 'BE_SUPPLEMENTARY',
                'RecordTableName' => 'dbo.tblBeSupplementaryBudget',
                'RecordID' => $supplementaryBudgetId,
                'RecordKey' => (string) $supplementaryBudgetId,
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => $executionVersionId,
                'ScopeDataObjectCode' => $scopeDataObjectCode,
                'WorkflowTitle' => $title,
                'WorkflowNote' => $notes,
                'UserID' => $userId,
            ]);
        }

        return $supplementaryBudgetId;
    }

    public function previewBudgetReduction(int $fiscalYearId, int $executionVersionId, array $criteria): array
    {
        if (!$this->supportsExecutionFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [
                'criteria' => $criteria,
                'rows' => [],
                'summary' => [
                    'MatchedLineCount' => 0,
                    'EligibleLineCount' => 0,
                    'BlockedLineCount' => 0,
                    'MatchedCurrentAuthorizedTotal' => 0.0,
                    'EligibleReductionTotal' => 0.0,
                    'EligibleResultingAuthorizedTotal' => 0.0,
                ],
            ];
        }

        $method = strtoupper(trim((string) ($criteria['ReductionMethod'] ?? '')));
        $reductionValue = (float) ($criteria['ReductionValue'] ?? 0);
        if (!in_array($method, ['PERCENTAGE', 'FIXED_PER_LINE'], true)) {
            throw new \RuntimeException('Reduction method is not valid.');
        }
        if ($reductionValue <= 0) {
            throw new \RuntimeException('Reduction value must be greater than zero.');
        }
        if (!$this->hasBudgetReductionScope($criteria)) {
            throw new \RuntimeException('At least one scope filter is required before running a budget reduction preview.');
        }

        $rows = $this->listBudgetReductionScopeRows($fiscalYearId, $executionVersionId, $criteria);
        $previewRows = [];
        $matchedCurrentAuthorizedTotal = 0.0;
        $eligibleReductionTotal = 0.0;
        $eligibleResultingAuthorizedTotal = 0.0;
        $eligibleCount = 0;
        $blockedCount = 0;

        foreach ($rows as $row) {
            $currentAuthorizedAmount = (float) ($row['CurrentAuthorizedAmount'] ?? 0);
            $plannedReleasedAmount = (float) ($row['PlannedReleaseAmountTotal'] ?? 0);
            $maxReducibleAmount = max(0, $currentAuthorizedAmount - $plannedReleasedAmount);
            $proposedReductionAmount = $method === 'PERCENTAGE'
                ? round($currentAuthorizedAmount * ($reductionValue / 100), 6)
                : round($reductionValue, 6);
            $resultingAuthorizedAmount = $currentAuthorizedAmount - $proposedReductionAmount;
            $status = 'ELIGIBLE';
            $statusReason = '';

            if ($proposedReductionAmount <= 0) {
                $status = 'BLOCKED';
                $statusReason = 'Calculated reduction is zero.';
            } elseif ($maxReducibleAmount <= 0) {
                $status = 'BLOCKED';
                $statusReason = 'No reduction headroom remains after released authority.';
            } elseif ($proposedReductionAmount > $maxReducibleAmount) {
                $status = 'BLOCKED';
                $statusReason = 'Reduction exceeds the line reduction headroom after released authority.';
            } elseif ($resultingAuthorizedAmount < 0) {
                $status = 'BLOCKED';
                $statusReason = 'Reduction would reduce the current authorized amount below zero.';
            }

            $row['MaxReducibleAmount'] = $maxReducibleAmount;
            $row['ProposedReductionAmount'] = $proposedReductionAmount;
            $row['ResultingAuthorizedAmount'] = $resultingAuthorizedAmount;
            $row['ReductionStatus'] = $status;
            $row['ReductionStatusReason'] = $statusReason;
            $previewRows[] = $row;

            $matchedCurrentAuthorizedTotal += $currentAuthorizedAmount;
            if ($status === 'ELIGIBLE') {
                $eligibleCount++;
                $eligibleReductionTotal += $proposedReductionAmount;
                $eligibleResultingAuthorizedTotal += $resultingAuthorizedAmount;
            } else {
                $blockedCount++;
            }
        }

        return [
            'criteria' => $criteria,
            'rows' => $previewRows,
            'summary' => [
                'MatchedLineCount' => count($previewRows),
                'EligibleLineCount' => $eligibleCount,
                'BlockedLineCount' => $blockedCount,
                'MatchedCurrentAuthorizedTotal' => $matchedCurrentAuthorizedTotal,
                'EligibleReductionTotal' => $eligibleReductionTotal,
                'EligibleResultingAuthorizedTotal' => $eligibleResultingAuthorizedTotal,
            ],
        ];
    }

    public function listBudgetReductionMappedDimensions(int $fiscalYearId): array
    {
        if ($fiscalYearId <= 0 || !$this->tableExists('dbo.tblSbSegmentConfig')) {
            return [];
        }

        $hasFinancialUsageColumn = $this->columnExists('dbo.tblSegments', 'UsedInFinancialAccount');

        $definitions = $this->getBudgetReductionDimensionDefinitions();
        $codes = array_keys($definitions);
        $placeholders = [];
        $params = [':fy' => $fiscalYearId];
        foreach ($codes as $index => $code) {
            $placeholder = ':dim' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $code;
        }

        $sql = "
            SELECT
                c.StrategicDimensionCode,
                c.SegmentNo,
                s.SegmentName,
                s.CBMSDimension,
                s.SegmentGroup,
                s.DisplayOrder" . ($hasFinancialUsageColumn ? ",
                ISNULL(s.UsedInFinancialAccount, 0) AS UsedInFinancialAccount" : ",
                CAST(1 AS INT) AS UsedInFinancialAccount") . "
            FROM dbo.tblSbSegmentConfig c
            LEFT JOIN dbo.tblSegments s
              ON TRY_CONVERT(INT, s.SegmentCode) = c.SegmentNo
            WHERE c.FiscalYearID = :fy
              AND c.ActiveFlag = 1
              AND c.SegmentNo IS NOT NULL
              AND " . ($hasFinancialUsageColumn ? "ISNULL(s.UsedInFinancialAccount, 0) = 1" : "1 = 1") . "
              AND c.StrategicDimensionCode IN (" . implode(', ', $placeholders) . ")
            ORDER BY
                CASE WHEN s.DisplayOrder IS NULL THEN 1 ELSE 0 END ASC,
                s.DisplayOrder ASC,
                c.StrategicDimensionCode ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $mapped = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['StrategicDimensionCode'] ?? '')));
            if ($code === '' || !isset($definitions[$code])) {
                continue;
            }
            $mapped[] = [
                'Code' => $code,
                'Label' => $definitions[$code]['label'],
                'SegmentNo' => (int) ($row['SegmentNo'] ?? 0),
                'SegmentName' => trim((string) ($row['SegmentName'] ?? '')),
                'CBMSDimension' => trim((string) ($row['CBMSDimension'] ?? '')),
                'SegmentGroup' => trim((string) ($row['SegmentGroup'] ?? '')),
                'DisplayOrder' => $row['DisplayOrder'] ?? null,
                'UsedInFinancialAccount' => (int) ($row['UsedInFinancialAccount'] ?? 0),
            ];
        }

        return $mapped;
    }

    public function listBudgetReductionDataObjectOptions(int $fiscalYearId, string $scopeDataObjectCode = ''): array
    {
        if ($fiscalYearId <= 0 || !$this->tableExists('dbo.tblDataObjectCodes')) {
            return [];
        }

        if ($scopeDataObjectCode !== '') {
            $sql = "
                WITH ScopeTree AS (
                    SELECT DataObjectCode, DataObjectName, DataObjectCodeParent, 0 AS Depth
                    FROM dbo.tblDataObjectCodes
                    WHERE FiscalYearID = :fy
                      AND DataObjectCode = :scopeCode
                    UNION ALL
                    SELECT c.DataObjectCode, c.DataObjectName, c.DataObjectCodeParent, st.Depth + 1
                    FROM dbo.tblDataObjectCodes c
                    INNER JOIN ScopeTree st
                        ON c.DataObjectCodeParent = st.DataObjectCode
                    WHERE c.FiscalYearID = :fy
                )
                SELECT DataObjectCode, DataObjectName, Depth
                FROM ScopeTree
                ORDER BY Depth ASC, DataObjectCode ASC
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':fy' => $fiscalYearId,
                ':scopeCode' => $scopeDataObjectCode,
            ]);
        } else {
            $sql = "
                SELECT DataObjectCode, DataObjectName, 0 AS Depth
                FROM dbo.tblDataObjectCodes
                WHERE FiscalYearID = :fy
                ORDER BY DataObjectCode ASC
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([':fy' => $fiscalYearId]);
        }

        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listBudgetReductionDimensionOptions(int $fiscalYearId, string $scopeDataObjectCode, array $mappedDimensions): array
    {
        if ($fiscalYearId <= 0 || !$this->tableExists('dbo.tblSegmentValues')) {
            return [];
        }

        $scopeCodes = $this->listDescendantDataObjectCodes($fiscalYearId, $scopeDataObjectCode);
        $options = [];
        foreach ($mappedDimensions as $dimension) {
            $dimensionCode = strtoupper(trim((string) ($dimension['Code'] ?? '')));
            $segmentNo = (int) ($dimension['SegmentNo'] ?? 0);
            if ($dimensionCode === '' || $segmentNo <= 0) {
                $options[$dimensionCode] = [];
                continue;
            }

            $sql = "
                SELECT
                    sv.SegmentCode AS ValueCode,
                    COALESCE(NULLIF(LTRIM(RTRIM(sv.SegmentName)), N''), sv.SegmentCode) AS ValueLabel,
                    MAX(sv.ParentSegmentNo) AS ParentSegmentNo,
                    MAX(sv.ParentSegmentCode) AS ParentSegmentCode
                FROM dbo.tblSegmentValues sv
                WHERE sv.FiscalYearID = :fy
                  AND sv.SegmentNo = :segmentNo
                  AND ISNULL(sv.ActiveFlag, 1) = 1
            ";
            $params = [
                ':fy' => $fiscalYearId,
                ':segmentNo' => $segmentNo,
            ];

            if (!empty($scopeCodes)) {
                $in = [];
                foreach ($scopeCodes as $index => $scopeCode) {
                    $placeholder = ':scopeCode' . $dimensionCode . $index;
                    $in[] = $placeholder;
                    $params[$placeholder] = $scopeCode;
                }
                $sql .= "
                  AND sv.DataObjectCode IN (" . implode(', ', $in) . ")";
            }

            $sql .= "
                GROUP BY sv.SegmentCode, COALESCE(NULLIF(LTRIM(RTRIM(sv.SegmentName)), N''), sv.SegmentCode)
                ORDER BY
                    CASE WHEN TRY_CONVERT(INT, sv.SegmentCode) IS NULL THEN 1 ELSE 0 END ASC,
                    TRY_CONVERT(INT, sv.SegmentCode) ASC,
                    sv.SegmentCode ASC,
                    ValueLabel ASC
            ";
            $st = $this->conn->prepare($sql);
            $st->execute($params);
            $options[$dimensionCode] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        return $options;
    }

    public function generateBudgetReductionBatch(int $fiscalYearId, int $executionVersionId, array $criteria, array $selectedOpeningBalanceIds, string $title, ?string $notes, int $userId): array
    {
        $preview = $this->previewBudgetReduction($fiscalYearId, $executionVersionId, $criteria);
        $rows = is_array($preview['rows'] ?? null) ? $preview['rows'] : [];
        $selectedMap = [];
        foreach ($selectedOpeningBalanceIds as $openingBalanceId) {
            $id = (int) $openingBalanceId;
            if ($id > 0) {
                $selectedMap[$id] = true;
            }
        }

        $eligibleRows = [];
        foreach ($rows as $row) {
            $openingBalanceId = (int) ($row['ExecutionOpeningBalanceID'] ?? 0);
            if ($openingBalanceId <= 0 || !isset($selectedMap[$openingBalanceId])) {
                continue;
            }
            if (strtoupper(trim((string) ($row['ReductionStatus'] ?? ''))) !== 'ELIGIBLE') {
                continue;
            }
            $eligibleRows[] = $row;
        }

        if (empty($eligibleRows)) {
            throw new \RuntimeException('Select at least one eligible execution balance line to generate a reduction batch.');
        }

        $batchTitle = trim($title);
        if ($batchTitle === '') {
            $batchTitle = 'Budget Reduction ' . date('Y-m-d H:i');
        }

        $method = strtoupper(trim((string) ($criteria['ReductionMethod'] ?? '')));
        $reductionValue = (float) ($criteria['ReductionValue'] ?? 0);
        $headerNotes = $this->nullableString($notes);
        $scopeSummary = $this->buildBudgetReductionScopeSummary($criteria, $method, $reductionValue);
        if ($scopeSummary !== '') {
            $headerNotes = $headerNotes === null ? $scopeSummary : ($headerNotes . "\n\n" . $scopeSummary);
        }

        $this->conn->beginTransaction();
        try {
            $supplementaryBudgetId = $this->createSupplementary([
                'FiscalYearID' => $fiscalYearId,
                'ExecutionVersionID' => $executionVersionId,
                'SupplementaryTitle' => $batchTitle,
                'SupplementaryDate' => date('Y-m-d'),
                'EffectiveDate' => date('Y-m-d'),
                'Notes' => $headerNotes,
                'ScopeDataObjectCode' => trim((string) ($criteria['DataObjectCode'] ?? '')) !== ''
                    ? trim((string) ($criteria['DataObjectCode'] ?? ''))
                    : trim((string) ($criteria['SessionScopeDataObjectCode'] ?? '')),
                'UserID' => $userId,
            ]);

            $generatedLineCount = 0;
            $generatedReductionTotal = 0.0;
            foreach ($eligibleRows as $row) {
                $reductionAmount = (float) ($row['ProposedReductionAmount'] ?? 0);
                if ($reductionAmount <= 0) {
                    continue;
                }

                $lineNote = 'Generated by Budget Reduction Wizard';
                $statusReason = trim((string) ($row['ReductionStatusReason'] ?? ''));
                if ($statusReason !== '') {
                    $lineNote .= ' - ' . $statusReason;
                }

                $this->addSupplementaryLine([
                    'FiscalYearID' => $fiscalYearId,
                    'ExecutionVersionID' => $executionVersionId,
                    'SupplementaryBudgetID' => $supplementaryBudgetId,
                    'ExecutionOpeningBalanceID' => (int) ($row['ExecutionOpeningBalanceID'] ?? 0),
                    'AdjustmentAmount' => -1 * $reductionAmount,
                    'Notes' => $lineNote,
                    'UserID' => $userId,
                ]);
                $generatedLineCount++;
                $generatedReductionTotal += $reductionAmount;
            }

            if ($generatedLineCount <= 0) {
                throw new \RuntimeException('No reduction lines were generated.');
            }

            $this->conn->commit();

            return [
                'SupplementaryBudgetID' => $supplementaryBudgetId,
                'GeneratedLineCount' => $generatedLineCount,
                'GeneratedReductionTotal' => $generatedReductionTotal,
            ];
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function listSupplementaryLines(int $fiscalYearId, int $executionVersionId, int $supplementaryBudgetId): array
    {
        if (!$this->supportsSupplementaryFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $supplementaryBudgetId <= 0) {
            return [];
        }

        $sql = "
            SELECT sl.*,
                   ob.OpeningAmount,
                   ob.CurrentAuthorizedAmount
            FROM dbo.tblBeSupplementaryBudgetLine sl
            INNER JOIN dbo.tblBeExecutionOpeningBalance ob
                ON ob.ExecutionOpeningBalanceID = sl.ExecutionOpeningBalanceID
            WHERE sl.FiscalYearID = :fy
              AND sl.ExecutionVersionID = :executionVersionId
              AND sl.SupplementaryBudgetID = :supplementaryBudgetId
              AND ISNULL(sl.ActiveFlag, 1) = 1
            ORDER BY sl.SupplementaryBudgetLineID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':supplementaryBudgetId' => $supplementaryBudgetId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listSupplementaryBalanceCandidates(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $releaseJoin = $this->supportsWarrantFoundation()
            ? "
            LEFT JOIN (
                SELECT wl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(wl.ReleaseAmount, 0)) AS PlannedReleaseAmountTotal
                FROM dbo.tblBeWarrantLine wl
                INNER JOIN dbo.tblBeWarrant w
                    ON w.WarrantID = wl.WarrantID
                WHERE ISNULL(wl.ActiveFlag, 1) = 1
                  AND ISNULL(w.ActiveFlag, 1) = 1
                  AND w.WarrantStatusCode <> N'CANCELLED'
                GROUP BY wl.ExecutionOpeningBalanceID
            ) rel
                ON rel.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "
            LEFT JOIN (
                SELECT CAST(NULL AS INT) AS ExecutionOpeningBalanceID,
                       CAST(0 AS DECIMAL(19,6)) AS PlannedReleaseAmountTotal
            ) rel
                ON 1 = 0";
        $sql = "
            SELECT b.*,
                   COALESCE(rel.PlannedReleaseAmountTotal, 0) AS PlannedReleaseAmountTotal
            FROM dbo.tblBeExecutionOpeningBalance b
            {$releaseJoin}
            WHERE b.FiscalYearID = :fy
              AND b.ExecutionVersionID = :executionVersionId
              AND ISNULL(b.ActiveFlag, 1) = 1
            ORDER BY b.DataObjectCode ASC,
                     b.SectorID ASC,
                     b.ProgramID ASC,
                     b.ProjectID ASC,
                     b.SourceSubmissionLineID ASC,
                     b.ExecutionOpeningBalanceID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function addSupplementaryLine(array $data): int
    {
        if (!$this->supportsSupplementaryFoundation()) {
            throw new \RuntimeException('Budget Execution Supplementary tables are not installed. Run create_budget_execution_supplementaries_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $supplementaryBudgetId = (int) ($data['SupplementaryBudgetID'] ?? 0);
        $executionOpeningBalanceId = (int) ($data['ExecutionOpeningBalanceID'] ?? 0);
        $adjustmentAmount = (float) ($data['AdjustmentAmount'] ?? 0);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($adjustmentAmount == 0.0) {
            throw new \RuntimeException('Adjustment amount must not be zero.');
        }

        $supplementary = $this->getSupplementary($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        if ($supplementary === null) {
            throw new \RuntimeException('Selected supplementary budget was not found.');
        }
        if (!$this->isSupplementaryEditable($fiscalYearId, $executionVersionId, $supplementary)) {
            throw new \RuntimeException('Lines can only be added to a draft supplementary budget.');
        }

        $openingBalance = $this->getOpeningBalanceRow($fiscalYearId, $executionVersionId, $executionOpeningBalanceId);
        if ($openingBalance === null) {
            throw new \RuntimeException('Selected execution balance line was not found.');
        }

        $currentAuthorizedAmount = (float) ($openingBalance['CurrentAuthorizedAmount'] ?? 0);
        $plannedReleasedAmount = $this->getReleasedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $remainingAfterAdjustment = $currentAuthorizedAmount + $adjustmentAmount;
        if ($remainingAfterAdjustment < 0) {
            throw new \RuntimeException('This adjustment would reduce the current authorized amount below zero.');
        }
        if ($remainingAfterAdjustment < $plannedReleasedAmount) {
            throw new \RuntimeException(
                'This reduction would take the current authorized amount below the planned released amount for the selected balance line. Planned released: '
                . number_format(max(0, $plannedReleasedAmount), 2)
            );
        }

        $sql = "
            INSERT INTO dbo.tblBeSupplementaryBudgetLine (
                SupplementaryBudgetID,
                FiscalYearID,
                ExecutionVersionID,
                ExecutionOpeningBalanceID,
                DataObjectCode,
                OrgUnitID,
                SectorID,
                ProgramID,
                SubProgramID,
                ProjectID,
                ActivityID,
                FundingTypeID,
                FundingSourceID,
                EconomicItemID,
                BidTitle,
                AdjustmentAmount,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.SupplementaryBudgetLineID
            VALUES (
                :supplementaryBudgetId,
                :fy,
                :executionVersionId,
                :executionOpeningBalanceId,
                :dataObjectCode,
                :orgUnitId,
                :sectorId,
                :programId,
                :subProgramId,
                :projectId,
                :activityId,
                :fundingTypeId,
                :fundingSourceId,
                :economicItemId,
                :bidTitle,
                :adjustmentAmount,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':supplementaryBudgetId' => $supplementaryBudgetId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
            ':dataObjectCode' => (string) ($openingBalance['DataObjectCode'] ?? ''),
            ':orgUnitId' => $openingBalance['OrgUnitID'] ?? null,
            ':sectorId' => $openingBalance['SectorID'] ?? null,
            ':programId' => $openingBalance['ProgramID'] ?? null,
            ':subProgramId' => $openingBalance['SubProgramID'] ?? null,
            ':projectId' => $openingBalance['ProjectID'] ?? null,
            ':activityId' => $openingBalance['ActivityID'] ?? null,
            ':fundingTypeId' => $openingBalance['FundingTypeID'] ?? null,
            ':fundingSourceId' => $openingBalance['FundingSourceID'] ?? null,
            ':economicItemId' => $openingBalance['EconomicItemID'] ?? null,
            ':bidTitle' => $this->nullableString($openingBalance['BidTitle'] ?? null),
            ':adjustmentAmount' => $adjustmentAmount,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    public function deleteSupplementaryLine(int $fiscalYearId, int $executionVersionId, int $supplementaryBudgetLineId, int $userId): void
    {
        if (!$this->supportsSupplementaryFoundation() || $supplementaryBudgetLineId <= 0) {
            throw new \RuntimeException('Supplementary line could not be removed.');
        }

        $sql = "
            SELECT TOP 1 sl.SupplementaryBudgetLineID,
                   sl.SupplementaryBudgetID,
                   s.SupplementaryStatusCode
            FROM dbo.tblBeSupplementaryBudgetLine sl
            INNER JOIN dbo.tblBeSupplementaryBudget s
                ON s.SupplementaryBudgetID = sl.SupplementaryBudgetID
            WHERE sl.SupplementaryBudgetLineID = :supplementaryBudgetLineId
              AND sl.FiscalYearID = :fy
              AND sl.ExecutionVersionID = :executionVersionId
              AND ISNULL(sl.ActiveFlag, 1) = 1
              AND ISNULL(s.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':supplementaryBudgetLineId' => $supplementaryBudgetLineId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            throw new \RuntimeException('Supplementary line was not found.');
        }
        $supplementary = $this->getSupplementary($fiscalYearId, $executionVersionId, (int) ($row['SupplementaryBudgetID'] ?? 0));
        if ($supplementary === null || !$this->isSupplementaryEditable($fiscalYearId, $executionVersionId, $supplementary)) {
            throw new \RuntimeException('Only draft supplementary lines can be removed.');
        }

        $deleteSql = "
            UPDATE dbo.tblBeSupplementaryBudgetLine
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE SupplementaryBudgetLineID = :supplementaryBudgetLineId
        ";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->execute([
            ':userId' => $userId > 0 ? $userId : 1,
            ':supplementaryBudgetLineId' => $supplementaryBudgetLineId,
        ]);
    }

    public function approveSupplementary(int $fiscalYearId, int $executionVersionId, int $supplementaryBudgetId, int $userId): void
    {
        $supplementary = $this->getSupplementary($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        if ($supplementary === null) {
            throw new \RuntimeException('Supplementary budget was not found.');
        }
        $statusCode = strtoupper(trim((string) ($supplementary['SupplementaryStatusCode'] ?? '')));
        if ($statusCode !== 'DRAFT') {
            throw new \RuntimeException('Only draft supplementary budgets can be approved.');
        }
        if ((int) ($supplementary['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('A supplementary budget must contain at least one line before approval.');
        }

        $workflowState = $this->getSupplementaryWorkflowState($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        $workflowStageCode = strtoupper(trim((string) ($workflowInstance['CurrentStageCode'] ?? '')));
        if ($workflowInstance !== null && $workflowStageCode !== '' && $workflowStageCode !== 'FINAL_APPROVAL') {
            throw new \RuntimeException('This supplementary budget must reach Final Approval stage before it can be approved.');
        }

        $lines = $this->listSupplementaryLines($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        foreach ($lines as $line) {
            $openingBalanceId = (int) ($line['ExecutionOpeningBalanceID'] ?? 0);
            $openingBalance = $this->getOpeningBalanceRow($fiscalYearId, $executionVersionId, $openingBalanceId);
            if ($openingBalance === null) {
                throw new \RuntimeException('One or more supplementary lines reference a missing execution opening balance.');
            }
            $currentAuthorizedAmount = (float) ($openingBalance['CurrentAuthorizedAmount'] ?? 0);
            $plannedReleasedAmount = $this->getReleasedAmountAgainstOpeningBalance($openingBalanceId);
            $adjustmentAmount = (float) ($line['AdjustmentAmount'] ?? 0);
            $remainingAfterAdjustment = $currentAuthorizedAmount + $adjustmentAmount;
            if ($remainingAfterAdjustment < 0) {
                throw new \RuntimeException('One or more supplementary lines would reduce the current authorized amount below zero.');
            }
            if ($remainingAfterAdjustment < $plannedReleasedAmount) {
                throw new \RuntimeException('One or more supplementary lines would reduce the current authorized amount below planned released authority.');
            }
        }

        $this->conn->beginTransaction();
        try {
            $updateBalanceSql = "
                UPDATE dbo.tblBeExecutionOpeningBalance
                SET CurrentAuthorizedAmount = COALESCE(CurrentAuthorizedAmount, 0) + :adjustmentAmount,
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ExecutionOpeningBalanceID = :executionOpeningBalanceId
            ";
            $updateBalanceStmt = $this->conn->prepare($updateBalanceSql);
            foreach ($lines as $line) {
                $updateBalanceStmt->execute([
                    ':adjustmentAmount' => (float) ($line['AdjustmentAmount'] ?? 0),
                    ':updatedBy' => $userId > 0 ? $userId : 1,
                    ':executionOpeningBalanceId' => (int) ($line['ExecutionOpeningBalanceID'] ?? 0),
                ]);
            }

            $sql = "
                UPDATE dbo.tblBeSupplementaryBudget
                SET SupplementaryStatusCode = N'APPROVED',
                    ApprovedBy = :approvedBy,
                    ApprovedDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE SupplementaryBudgetID = :supplementaryBudgetId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':approvedBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':supplementaryBudgetId' => $supplementaryBudgetId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'APPROVE',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function cancelSupplementary(int $fiscalYearId, int $executionVersionId, int $supplementaryBudgetId, int $userId): void
    {
        $supplementary = $this->getSupplementary($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        if ($supplementary === null) {
            throw new \RuntimeException('Supplementary budget was not found.');
        }

        $status = strtoupper(trim((string) ($supplementary['SupplementaryStatusCode'] ?? '')));
        if ($status === 'CANCELLED') {
            throw new \RuntimeException('This supplementary budget has already been cancelled.');
        }

        $workflowState = $this->getSupplementaryWorkflowState($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;

        $lines = $this->listSupplementaryLines($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        if ($status === 'APPROVED') {
            foreach ($lines as $line) {
                $openingBalanceId = (int) ($line['ExecutionOpeningBalanceID'] ?? 0);
                $openingBalance = $this->getOpeningBalanceRow($fiscalYearId, $executionVersionId, $openingBalanceId);
                if ($openingBalance === null) {
                    throw new \RuntimeException('One or more supplementary lines reference a missing execution opening balance.');
                }
                $currentAuthorizedAmount = (float) ($openingBalance['CurrentAuthorizedAmount'] ?? 0);
                $adjustmentAmount = (float) ($line['AdjustmentAmount'] ?? 0);
                $reversedAmount = $currentAuthorizedAmount - $adjustmentAmount;
                $plannedReleasedAmount = $this->getReleasedAmountAgainstOpeningBalance($openingBalanceId);
                if ($reversedAmount < 0) {
                    throw new \RuntimeException('Cancelling this approved supplementary budget would reduce the current authorized amount below zero.');
                }
                if ($reversedAmount < $plannedReleasedAmount) {
                    throw new \RuntimeException('Cancelling this approved supplementary budget would reduce the current authorized amount below planned released authority.');
                }
            }
        }

        $this->conn->beginTransaction();
        try {
            if ($status === 'APPROVED') {
                $reverseSql = "
                    UPDATE dbo.tblBeExecutionOpeningBalance
                    SET CurrentAuthorizedAmount = COALESCE(CurrentAuthorizedAmount, 0) - :adjustmentAmount,
                        UpdatedBy = :updatedBy,
                        UpdatedDate = SYSDATETIME()
                    WHERE ExecutionOpeningBalanceID = :executionOpeningBalanceId
                ";
                $reverseStmt = $this->conn->prepare($reverseSql);
                foreach ($lines as $line) {
                    $reverseStmt->execute([
                        ':adjustmentAmount' => (float) ($line['AdjustmentAmount'] ?? 0),
                        ':updatedBy' => $userId > 0 ? $userId : 1,
                        ':executionOpeningBalanceId' => (int) ($line['ExecutionOpeningBalanceID'] ?? 0),
                    ]);
                }
            }

            $sql = "
                UPDATE dbo.tblBeSupplementaryBudget
                SET SupplementaryStatusCode = N'CANCELLED',
                    CancelledBy = :cancelledBy,
                    CancelledDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE SupplementaryBudgetID = :supplementaryBudgetId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':cancelledBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':supplementaryBudgetId' => $supplementaryBudgetId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'CANCEL',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function transitionSupplementaryWorkflow(int $fiscalYearId, int $executionVersionId, int $supplementaryBudgetId, string $workflowActionCode, int $userId, ?string $note = null): array
    {
        $supplementary = $this->getSupplementary($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        if ($supplementary === null) {
            throw new \RuntimeException('Supplementary budget was not found.');
        }
        if (!$this->supportsWorkflowEngineFoundation()) {
            throw new \RuntimeException('Shared workflow engine foundation is not installed.');
        }
        if (strtoupper(trim((string) ($supplementary['SupplementaryStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('Cancelled supplementary budgets cannot continue through workflow.');
        }

        $actionCode = strtoupper(trim($workflowActionCode));
        if (!in_array($actionCode, ['SUBMIT', 'FORWARD', 'RETURN'], true)) {
            throw new \RuntimeException('Workflow action is not supported for supplementary budgets.');
        }
        if ((int) ($supplementary['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('A supplementary budget must contain at least one line before workflow submission.');
        }

        $workflowState = $this->getSupplementaryWorkflowState($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($workflowInstance === null) {
            throw new \RuntimeException('Workflow instance could not be resolved for this supplementary budget.');
        }

        $updatedInstance = $this->workflowEngine()->transitionWorkflowInstance(
            (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
            $actionCode,
            $userId,
            $note
        );

        $statusSql = "
            UPDATE dbo.tblBeSupplementaryBudget
            SET UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE SupplementaryBudgetID = :supplementaryBudgetId
        ";
        $statusStmt = $this->conn->prepare($statusSql);
        $statusStmt->execute([
            ':updatedBy' => $userId > 0 ? $userId : 1,
            ':supplementaryBudgetId' => $supplementaryBudgetId,
        ]);

        return $updatedInstance;
    }

    public function annotateSupplementariesWithWorkflowState(int $fiscalYearId, int $executionVersionId, array $rows): array
    {
        if ($rows === [] || !$this->supportsWorkflowEngineFoundation()) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $supplementaryBudgetId = (int) ($row['SupplementaryBudgetID'] ?? 0);
            if ($supplementaryBudgetId <= 0) {
                $row['WorkflowStageCode'] = '';
                $row['WorkflowStageName'] = '';
                continue;
            }

            $workflowState = $this->getSupplementaryWorkflowState($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
            $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
            $currentStage = is_array($workflowState['CurrentStage'] ?? null) ? $workflowState['CurrentStage'] : null;
            $row['WorkflowStageCode'] = (string) ($instance['CurrentStageCode'] ?? '');
            $row['WorkflowStageName'] = (string) ($currentStage['WorkflowStageName'] ?? ($instance['CurrentStageCode'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public function getSupplementaryWorkflowState(int $fiscalYearId, int $executionVersionId, int $supplementaryBudgetId): array
    {
        if (!$this->supportsWorkflowEngineFoundation() || $supplementaryBudgetId <= 0) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $supplementary = $this->getSupplementary($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        if ($supplementary === null) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $this->ensureSupplementaryWorkflowInstance($fiscalYearId, $executionVersionId, $supplementary, 0);
        return $this->workflowEngine()->getWorkflowPanelState(
            'BE_SUPPLEMENTARY',
            'dbo.tblBeSupplementaryBudget',
            (string) $supplementaryBudgetId
        );
    }

    private function ensureSupplementaryWorkflowInstance(int $fiscalYearId, int $executionVersionId, array $supplementary, int $userId): ?array
    {
        if (!$this->supportsWorkflowEngineFoundation()) {
            return null;
        }

        $supplementaryBudgetId = (int) ($supplementary['SupplementaryBudgetID'] ?? 0);
        if ($supplementaryBudgetId <= 0) {
            return null;
        }

        $existing = $this->workflowEngine()->getWorkflowInstanceByRecord(
            'BE_SUPPLEMENTARY',
            'dbo.tblBeSupplementaryBudget',
            (string) $supplementaryBudgetId
        );
        if ($existing !== null) {
            return $existing;
        }

        return $this->workflowEngine()->ensureWorkflowInstance([
            'WorkflowAreaCode' => 'BE_SUPPLEMENTARY',
            'RecordTableName' => 'dbo.tblBeSupplementaryBudget',
            'RecordID' => $supplementaryBudgetId,
            'RecordKey' => (string) $supplementaryBudgetId,
            'FiscalYearID' => $fiscalYearId,
            'VersionID' => $executionVersionId,
            'ScopeDataObjectCode' => $this->inferSupplementaryScopeDataObjectCode($fiscalYearId, $executionVersionId, $supplementaryBudgetId),
            'WorkflowTitle' => (string) ($supplementary['SupplementaryTitle'] ?? ''),
            'WorkflowNote' => $this->nullableString($supplementary['Notes'] ?? null),
            'UserID' => $userId,
        ]);
    }

    private function inferSupplementaryScopeDataObjectCode(int $fiscalYearId, int $executionVersionId, int $supplementaryBudgetId): ?string
    {
        if (!$this->supportsSupplementaryFoundation() || $supplementaryBudgetId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS DistinctScopeCount,
                   MIN(NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS ScopeDataObjectCode
            FROM dbo.tblBeSupplementaryBudgetLine
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
              AND SupplementaryBudgetID = :supplementaryBudgetId
              AND ISNULL(ActiveFlag, 1) = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':supplementaryBudgetId' => $supplementaryBudgetId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $distinctCount = (int) ($row['DistinctScopeCount'] ?? 0);
        $scopeDataObjectCode = trim((string) ($row['ScopeDataObjectCode'] ?? ''));
        if ($distinctCount === 1 && $scopeDataObjectCode !== '') {
            return $scopeDataObjectCode;
        }

        return null;
    }

    private function isSupplementaryEditable(int $fiscalYearId, int $executionVersionId, array $supplementary): bool
    {
        $statusCode = strtoupper(trim((string) ($supplementary['SupplementaryStatusCode'] ?? '')));
        if ($statusCode !== 'DRAFT') {
            return false;
        }

        if (!$this->supportsWorkflowEngineFoundation()) {
            return true;
        }

        $supplementaryBudgetId = (int) ($supplementary['SupplementaryBudgetID'] ?? 0);
        if ($supplementaryBudgetId <= 0) {
            return false;
        }

        $workflowState = $this->getSupplementaryWorkflowState($fiscalYearId, $executionVersionId, $supplementaryBudgetId);
        $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($instance === null) {
            return true;
        }

        return strtoupper(trim((string) ($instance['CurrentStageCode'] ?? ''))) === 'DRAFT';
    }

    public function annotateWarrantsWithWorkflowState(int $fiscalYearId, int $executionVersionId, array $rows): array
    {
        if ($rows === [] || !$this->supportsWorkflowEngineFoundation()) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $warrantId = (int) ($row['WarrantID'] ?? 0);
            if ($warrantId <= 0) {
                $row['WorkflowStageName'] = '';
                continue;
            }

            $workflowState = $this->getWarrantWorkflowState($fiscalYearId, $executionVersionId, $warrantId);
            $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
            $currentStage = is_array($workflowState['CurrentStage'] ?? null) ? $workflowState['CurrentStage'] : null;
            $row['WorkflowStageName'] = (string) ($currentStage['WorkflowStageName'] ?? ($instance['CurrentStageCode'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public function getWarrantWorkflowState(int $fiscalYearId, int $executionVersionId, int $warrantId): array
    {
        if (!$this->supportsWorkflowEngineFoundation() || $warrantId <= 0) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $warrant = $this->getWarrant($fiscalYearId, $executionVersionId, $warrantId);
        if ($warrant === null) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $this->ensureWarrantWorkflowInstance($fiscalYearId, $executionVersionId, $warrant, 0);
        return $this->workflowEngine()->getWorkflowPanelState(
            'BE_WARRANT',
            'dbo.tblBeWarrant',
            (string) $warrantId
        );
    }

    private function ensureWarrantWorkflowInstance(int $fiscalYearId, int $executionVersionId, array $warrant, int $userId): ?array
    {
        if (!$this->supportsWorkflowEngineFoundation()) {
            return null;
        }

        $warrantId = (int) ($warrant['WarrantID'] ?? 0);
        if ($warrantId <= 0) {
            return null;
        }

        $statusCode = strtoupper(trim((string) ($warrant['WarrantStatusCode'] ?? 'DRAFT')));
        $initialStageCode = in_array($statusCode, ['APPROVED', 'CANCELLED'], true) ? $statusCode : 'DRAFT';
        $existing = $this->workflowEngine()->getWorkflowInstanceByRecord(
            'BE_WARRANT',
            'dbo.tblBeWarrant',
            (string) $warrantId
        );
        $scopeDataObjectCode = $this->inferWarrantScopeDataObjectCode($fiscalYearId, $executionVersionId, $warrantId);
        if ($scopeDataObjectCode === null && $existing !== null) {
            $scopeDataObjectCode = $this->nullableString($existing['ScopeDataObjectCode'] ?? null);
        }

        return $this->workflowEngine()->ensureWorkflowInstance([
            'WorkflowAreaCode' => 'BE_WARRANT',
            'RecordTableName' => 'dbo.tblBeWarrant',
            'RecordID' => $warrantId,
            'RecordKey' => (string) $warrantId,
            'FiscalYearID' => $fiscalYearId,
            'VersionID' => $executionVersionId,
            'ScopeDataObjectCode' => $scopeDataObjectCode,
            'CurrentStageCode' => $initialStageCode,
            'CurrentStatusCode' => $statusCode,
            'WorkflowTitle' => (string) ($warrant['WarrantTitle'] ?? ''),
            'WorkflowNote' => $this->nullableString($warrant['Notes'] ?? null),
            'UserID' => $userId,
        ]);
    }

    private function inferWarrantScopeDataObjectCode(int $fiscalYearId, int $executionVersionId, int $warrantId): ?string
    {
        if (!$this->supportsWarrantFoundation() || $warrantId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS DistinctScopeCount,
                   MIN(NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS ScopeDataObjectCode
            FROM dbo.tblBeWarrantLine
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
              AND WarrantID = :warrantId
              AND ISNULL(ActiveFlag, 1) = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':warrantId' => $warrantId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $distinctCount = (int) ($row['DistinctScopeCount'] ?? 0);
        $scopeDataObjectCode = trim((string) ($row['ScopeDataObjectCode'] ?? ''));
        if ($distinctCount === 1 && $scopeDataObjectCode !== '') {
            return $scopeDataObjectCode;
        }

        return null;
    }

    private function isWarrantEditable(int $fiscalYearId, int $executionVersionId, array $warrant): bool
    {
        $statusCode = strtoupper(trim((string) ($warrant['WarrantStatusCode'] ?? '')));
        if ($statusCode !== 'DRAFT') {
            return false;
        }

        if (!$this->supportsWorkflowEngineFoundation()) {
            return true;
        }

        $warrantId = (int) ($warrant['WarrantID'] ?? 0);
        if ($warrantId <= 0) {
            return false;
        }

        $workflowState = $this->getWarrantWorkflowState($fiscalYearId, $executionVersionId, $warrantId);
        $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($instance === null) {
            return true;
        }

        return strtoupper(trim((string) ($instance['CurrentStageCode'] ?? ''))) === 'DRAFT';
    }

    public function getWarrantModuleSummary(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || !$this->supportsWarrantFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [
                'WarrantCount' => 0,
                'ApprovedWarrantCount' => 0,
                'ApprovedReleasedAmountTotal' => 0.0,
                'PlannedReleaseAmountTotal' => 0.0,
                'RemainingWarrantCapacityTotal' => 0.0,
            ];
        }

        $sql = "
            SELECT COUNT(*) AS WarrantCount,
                   SUM(CASE WHEN w.WarrantStatusCode = N'APPROVED' THEN 1 ELSE 0 END) AS ApprovedWarrantCount,
                   COALESCE(SUM(COALESCE(CASE WHEN w.WarrantStatusCode = N'APPROVED' THEN line.TotalReleaseAmount ELSE 0 END, 0)), 0) AS ApprovedReleasedAmountTotal,
                   COALESCE(SUM(COALESCE(line.TotalReleaseAmount, 0)), 0) AS PlannedReleaseAmountTotal
            FROM dbo.tblBeWarrant w
            OUTER APPLY (
                SELECT SUM(COALESCE(wl.ReleaseAmount, 0)) AS TotalReleaseAmount
                FROM dbo.tblBeWarrantLine wl
                WHERE wl.WarrantID = w.WarrantID
                  AND ISNULL(wl.ActiveFlag, 1) = 1
            ) line
            WHERE w.FiscalYearID = :fy
              AND w.ExecutionVersionID = :executionVersionId
              AND ISNULL(w.ActiveFlag, 1) = 1
              AND w.WarrantStatusCode <> N'CANCELLED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

        $opening = $this->getOpeningBalanceSummary($fiscalYearId, $executionVersionId);
        $plannedReleaseAmountTotal = (float) ($row['PlannedReleaseAmountTotal'] ?? 0);
        $approvedReleasedAmountTotal = (float) ($row['ApprovedReleasedAmountTotal'] ?? 0);
        $currentAuthorizedAmountTotal = (float) ($opening['CurrentAuthorizedAmountTotal'] ?? 0);

        return [
            'WarrantCount' => (int) ($row['WarrantCount'] ?? 0),
            'ApprovedWarrantCount' => (int) ($row['ApprovedWarrantCount'] ?? 0),
            'ApprovedReleasedAmountTotal' => $approvedReleasedAmountTotal,
            'PlannedReleaseAmountTotal' => $plannedReleaseAmountTotal,
            'RemainingWarrantCapacityTotal' => max(0, $currentAuthorizedAmountTotal - $plannedReleaseAmountTotal),
        ];
    }

    public function listWarrants(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsWarrantFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $sql = "
            SELECT w.*,
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalReleaseAmount, 0) AS TotalReleaseAmount
            FROM dbo.tblBeWarrant w
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(wl.ReleaseAmount, 0)), 0) AS TotalReleaseAmount
                FROM dbo.tblBeWarrantLine wl
                WHERE wl.WarrantID = w.WarrantID
                  AND ISNULL(wl.ActiveFlag, 1) = 1
            ) line
            WHERE w.FiscalYearID = :fy
              AND w.ExecutionVersionID = :executionVersionId
              AND ISNULL(w.ActiveFlag, 1) = 1
            ORDER BY w.CreatedDate DESC, w.WarrantID DESC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getWarrant(int $fiscalYearId, int $executionVersionId, int $warrantId): ?array
    {
        if (!$this->supportsWarrantFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $warrantId <= 0) {
            return null;
        }

        $sql = "
            SELECT w.*,
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalReleaseAmount, 0) AS TotalReleaseAmount
            FROM dbo.tblBeWarrant w
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(wl.ReleaseAmount, 0)), 0) AS TotalReleaseAmount
                FROM dbo.tblBeWarrantLine wl
                WHERE wl.WarrantID = w.WarrantID
                  AND ISNULL(wl.ActiveFlag, 1) = 1
            ) line
            WHERE w.FiscalYearID = :fy
              AND w.ExecutionVersionID = :executionVersionId
              AND w.WarrantID = :warrantId
              AND ISNULL(w.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':warrantId' => $warrantId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createWarrant(array $data): int
    {
        if (!$this->supportsWarrantFoundation()) {
            throw new \RuntimeException('Budget Execution Warrant tables are not installed. Run create_budget_execution_warrants_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $title = trim((string) ($data['WarrantTitle'] ?? ''));
        $warrantDate = $this->normalizeDate($data['WarrantDate'] ?? null);
        $effectiveDate = $this->normalizeDate($data['EffectiveDate'] ?? null);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $scopeDataObjectCode = $this->nullableString($data['ScopeDataObjectCode'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($fiscalYearId <= 0 || $executionVersionId <= 0) {
            throw new \RuntimeException('Execution context is required for the warrant.');
        }
        if ($title === '') {
            throw new \RuntimeException('Warrant title is required.');
        }

        $executionVersion = $this->getVersion($fiscalYearId, $executionVersionId);
        if ($executionVersion === null || strtoupper(trim((string) ($executionVersion['VersionTypeCode'] ?? ''))) !== 'EXECUTION') {
            throw new \RuntimeException('Warrants can only be created inside an execution version.');
        }

        $warrantNo = $this->nextWarrantNumber($fiscalYearId, $executionVersionId);

        $sql = "
            INSERT INTO dbo.tblBeWarrant (
                FiscalYearID,
                ExecutionVersionID,
                WarrantNo,
                WarrantTitle,
                WarrantStatusCode,
                WarrantDate,
                EffectiveDate,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.WarrantID
            VALUES (
                :fy,
                :executionVersionId,
                :warrantNo,
                :warrantTitle,
                N'DRAFT',
                :warrantDate,
                :effectiveDate,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':warrantNo' => $warrantNo,
            ':warrantTitle' => $title,
            ':warrantDate' => $warrantDate ?? date('Y-m-d'),
            ':effectiveDate' => $effectiveDate,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        $warrantId = (int) ($st->fetchColumn() ?: 0);
        if ($warrantId <= 0) {
            throw new \RuntimeException('Warrant could not be created.');
        }

        if ($this->supportsWorkflowEngineFoundation()) {
            $this->workflowEngine()->ensureWorkflowInstance([
                'WorkflowAreaCode' => 'BE_WARRANT',
                'RecordTableName' => 'dbo.tblBeWarrant',
                'RecordID' => $warrantId,
                'RecordKey' => (string) $warrantId,
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => $executionVersionId,
                'ScopeDataObjectCode' => $scopeDataObjectCode,
                'WorkflowTitle' => $title,
                'WorkflowNote' => $notes,
                'UserID' => $userId,
            ]);
        }

        return $warrantId;
    }

    public function listWarrantLines(int $fiscalYearId, int $executionVersionId, int $warrantId): array
    {
        if (!$this->supportsWarrantFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $warrantId <= 0) {
            return [];
        }

        $sql = "
            SELECT wl.*,
                   ob.OpeningAmount,
                   ob.CurrentAuthorizedAmount
            FROM dbo.tblBeWarrantLine wl
            INNER JOIN dbo.tblBeExecutionOpeningBalance ob
                ON ob.ExecutionOpeningBalanceID = wl.ExecutionOpeningBalanceID
            WHERE wl.FiscalYearID = :fy
              AND wl.ExecutionVersionID = :executionVersionId
              AND wl.WarrantID = :warrantId
              AND ISNULL(wl.ActiveFlag, 1) = 1
            ORDER BY wl.WarrantLineID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':warrantId' => $warrantId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listWarrantBalanceCandidates(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $sql = "
            SELECT b.*,
                   COALESCE(wplan.PlannedReleaseAmountTotal, 0) AS PlannedReleaseAmountTotal,
                   COALESCE(b.CurrentAuthorizedAmount, 0) - COALESCE(wplan.PlannedReleaseAmountTotal, 0) AS RemainingWarrantCapacity
            FROM dbo.tblBeExecutionOpeningBalance b
            LEFT JOIN (
                SELECT wl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(wl.ReleaseAmount, 0)) AS PlannedReleaseAmountTotal
                FROM dbo.tblBeWarrantLine wl
                INNER JOIN dbo.tblBeWarrant w
                    ON w.WarrantID = wl.WarrantID
                WHERE ISNULL(wl.ActiveFlag, 1) = 1
                  AND ISNULL(w.ActiveFlag, 1) = 1
                  AND w.WarrantStatusCode <> N'CANCELLED'
                GROUP BY wl.ExecutionOpeningBalanceID
            ) wplan
                ON wplan.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID
            WHERE b.FiscalYearID = :fy
              AND b.ExecutionVersionID = :executionVersionId
              AND ISNULL(b.ActiveFlag, 1) = 1
            ORDER BY b.DataObjectCode ASC,
                     b.SectorID ASC,
                     b.ProgramID ASC,
                     b.ProjectID ASC,
                     b.SourceSubmissionLineID ASC,
                     b.ExecutionOpeningBalanceID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function addWarrantLine(array $data): int
    {
        if (!$this->supportsWarrantFoundation()) {
            throw new \RuntimeException('Budget Execution Warrant tables are not installed. Run create_budget_execution_warrants_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $warrantId = (int) ($data['WarrantID'] ?? 0);
        $executionOpeningBalanceId = (int) ($data['ExecutionOpeningBalanceID'] ?? 0);
        $releaseAmount = (float) ($data['ReleaseAmount'] ?? 0);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($releaseAmount <= 0) {
            throw new \RuntimeException('Release amount must be greater than zero.');
        }

        $warrant = $this->getWarrant($fiscalYearId, $executionVersionId, $warrantId);
        if ($warrant === null) {
            throw new \RuntimeException('Selected warrant was not found.');
        }
        if (!$this->isWarrantEditable($fiscalYearId, $executionVersionId, $warrant)) {
            throw new \RuntimeException('Lines can only be added to a draft warrant.');
        }

        $openingBalance = $this->getOpeningBalanceRow($fiscalYearId, $executionVersionId, $executionOpeningBalanceId);
        if ($openingBalance === null) {
            throw new \RuntimeException('Selected execution balance line was not found.');
        }

        $currentlyReleased = $this->getReleasedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $currentAuthorizedAmount = (float) ($openingBalance['CurrentAuthorizedAmount'] ?? 0);
        $remainingReleaseCapacity = $currentAuthorizedAmount - $currentlyReleased;

        if ($releaseAmount > $remainingReleaseCapacity) {
            throw new \RuntimeException(
                'Release amount exceeds the remaining release capacity for the selected balance line. Remaining: '
                . number_format(max(0, $remainingReleaseCapacity), 2)
            );
        }

        $sql = "
            INSERT INTO dbo.tblBeWarrantLine (
                WarrantID,
                FiscalYearID,
                ExecutionVersionID,
                ExecutionOpeningBalanceID,
                DataObjectCode,
                OrgUnitID,
                SectorID,
                ProgramID,
                SubProgramID,
                ProjectID,
                ActivityID,
                FundingTypeID,
                FundingSourceID,
                EconomicItemID,
                BidTitle,
                ReleaseAmount,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.WarrantLineID
            VALUES (
                :warrantId,
                :fy,
                :executionVersionId,
                :executionOpeningBalanceId,
                :dataObjectCode,
                :orgUnitId,
                :sectorId,
                :programId,
                :subProgramId,
                :projectId,
                :activityId,
                :fundingTypeId,
                :fundingSourceId,
                :economicItemId,
                :bidTitle,
                :releaseAmount,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':warrantId' => $warrantId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
            ':dataObjectCode' => (string) ($openingBalance['DataObjectCode'] ?? ''),
            ':orgUnitId' => $openingBalance['OrgUnitID'] ?? null,
            ':sectorId' => $openingBalance['SectorID'] ?? null,
            ':programId' => $openingBalance['ProgramID'] ?? null,
            ':subProgramId' => $openingBalance['SubProgramID'] ?? null,
            ':projectId' => $openingBalance['ProjectID'] ?? null,
            ':activityId' => $openingBalance['ActivityID'] ?? null,
            ':fundingTypeId' => $openingBalance['FundingTypeID'] ?? null,
            ':fundingSourceId' => $openingBalance['FundingSourceID'] ?? null,
            ':economicItemId' => $openingBalance['EconomicItemID'] ?? null,
            ':bidTitle' => $this->nullableString($openingBalance['BidTitle'] ?? null),
            ':releaseAmount' => $releaseAmount,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    public function deleteWarrantLine(int $fiscalYearId, int $executionVersionId, int $warrantLineId, int $userId): void
    {
        if (!$this->supportsWarrantFoundation() || $warrantLineId <= 0) {
            throw new \RuntimeException('Warrant line could not be removed.');
        }

        $sql = "
            SELECT TOP 1 wl.WarrantLineID,
                   wl.WarrantID,
                   w.WarrantStatusCode
            FROM dbo.tblBeWarrantLine wl
            INNER JOIN dbo.tblBeWarrant w
                ON w.WarrantID = wl.WarrantID
            WHERE wl.WarrantLineID = :warrantLineId
              AND wl.FiscalYearID = :fy
              AND wl.ExecutionVersionID = :executionVersionId
              AND ISNULL(wl.ActiveFlag, 1) = 1
              AND ISNULL(w.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':warrantLineId' => $warrantLineId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            throw new \RuntimeException('Warrant line was not found.');
        }
        $warrant = $this->getWarrant($fiscalYearId, $executionVersionId, (int) ($row['WarrantID'] ?? 0));
        if ($warrant === null || !$this->isWarrantEditable($fiscalYearId, $executionVersionId, $warrant)) {
            throw new \RuntimeException('Only draft warrant lines can be removed.');
        }

        $deleteSql = "
            UPDATE dbo.tblBeWarrantLine
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE WarrantLineID = :warrantLineId
        ";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->execute([
            ':userId' => $userId > 0 ? $userId : 1,
            ':warrantLineId' => $warrantLineId,
        ]);
    }

    public function approveWarrant(int $fiscalYearId, int $executionVersionId, int $warrantId, int $userId): void
    {
        $warrant = $this->getWarrant($fiscalYearId, $executionVersionId, $warrantId);
        if ($warrant === null) {
            throw new \RuntimeException('Warrant was not found.');
        }
        if (strtoupper(trim((string) ($warrant['WarrantStatusCode'] ?? ''))) !== 'DRAFT') {
            throw new \RuntimeException('Only draft warrants can be approved.');
        }
        if ((int) ($warrant['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('A warrant must contain at least one line before approval.');
        }

        $workflowState = $this->getWarrantWorkflowState($fiscalYearId, $executionVersionId, $warrantId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        $workflowStageCode = strtoupper(trim((string) ($workflowInstance['CurrentStageCode'] ?? '')));
        if ($workflowInstance !== null && $workflowStageCode !== '' && $workflowStageCode !== 'FINAL_APPROVAL') {
            throw new \RuntimeException('This warrant must reach Final Approval stage before it can be approved.');
        }

        $this->conn->beginTransaction();
        try {
            $sql = "
                UPDATE dbo.tblBeWarrant
                SET WarrantStatusCode = N'APPROVED',
                    ApprovedBy = :approvedBy,
                    ApprovedDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE WarrantID = :warrantId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':approvedBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':warrantId' => $warrantId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'APPROVE',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function cancelWarrant(int $fiscalYearId, int $executionVersionId, int $warrantId, int $userId): void
    {
        $warrant = $this->getWarrant($fiscalYearId, $executionVersionId, $warrantId);
        if ($warrant === null) {
            throw new \RuntimeException('Warrant was not found.');
        }
        if (strtoupper(trim((string) ($warrant['WarrantStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('This warrant has already been cancelled.');
        }

        $workflowState = $this->getWarrantWorkflowState($fiscalYearId, $executionVersionId, $warrantId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;

        $this->conn->beginTransaction();
        try {
            $sql = "
                UPDATE dbo.tblBeWarrant
                SET WarrantStatusCode = N'CANCELLED',
                    CancelledBy = :cancelledBy,
                    CancelledDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE WarrantID = :warrantId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':cancelledBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':warrantId' => $warrantId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'CANCEL',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function transitionWarrantWorkflow(int $fiscalYearId, int $executionVersionId, int $warrantId, string $workflowActionCode, int $userId, ?string $note = null): array
    {
        $warrant = $this->getWarrant($fiscalYearId, $executionVersionId, $warrantId);
        if ($warrant === null) {
            throw new \RuntimeException('Warrant was not found.');
        }
        if (!$this->supportsWorkflowEngineFoundation()) {
            throw new \RuntimeException('Shared workflow engine foundation is not installed.');
        }
        if (strtoupper(trim((string) ($warrant['WarrantStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('Cancelled warrants cannot continue through workflow.');
        }

        $actionCode = strtoupper(trim($workflowActionCode));
        if (!in_array($actionCode, ['SUBMIT', 'FORWARD', 'RETURN'], true)) {
            throw new \RuntimeException('Workflow action is not supported for warrants.');
        }
        if ((int) ($warrant['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('A warrant must contain at least one line before workflow submission.');
        }

        $workflowState = $this->getWarrantWorkflowState($fiscalYearId, $executionVersionId, $warrantId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($workflowInstance === null) {
            throw new \RuntimeException('Workflow instance could not be resolved for this warrant.');
        }

        $updatedInstance = $this->workflowEngine()->transitionWorkflowInstance(
            (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
            $actionCode,
            $userId,
            $note
        );

        $statusSql = "
            UPDATE dbo.tblBeWarrant
            SET UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE WarrantID = :warrantId
        ";
        $statusStmt = $this->conn->prepare($statusSql);
        $statusStmt->execute([
            ':updatedBy' => $userId > 0 ? $userId : 1,
            ':warrantId' => $warrantId,
        ]);

        return $updatedInstance;
    }

    public function annotateReservationsWithWorkflowState(int $fiscalYearId, int $executionVersionId, array $rows): array
    {
        if ($rows === [] || !$this->supportsWorkflowEngineFoundation()) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $reservationId = (int) ($row['ReservationID'] ?? 0);
            if ($reservationId <= 0) {
                $row['WorkflowStageCode'] = '';
                $row['WorkflowStageName'] = '';
                continue;
            }

            $workflowState = $this->getReservationWorkflowState($fiscalYearId, $executionVersionId, $reservationId);
            $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
            $currentStage = is_array($workflowState['CurrentStage'] ?? null) ? $workflowState['CurrentStage'] : null;
            $row['WorkflowStageCode'] = (string) ($instance['CurrentStageCode'] ?? '');
            $row['WorkflowStageName'] = (string) ($currentStage['WorkflowStageName'] ?? ($instance['CurrentStageCode'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public function getReservationWorkflowState(int $fiscalYearId, int $executionVersionId, int $reservationId): array
    {
        if (!$this->supportsWorkflowEngineFoundation() || $reservationId <= 0) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $reservation = $this->getReservation($fiscalYearId, $executionVersionId, $reservationId);
        if ($reservation === null) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $this->ensureReservationWorkflowInstance($fiscalYearId, $executionVersionId, $reservation, 0);
        return $this->workflowEngine()->getWorkflowPanelState(
            'BE_RESERVATION',
            'dbo.tblBeReservation',
            (string) $reservationId
        );
    }

    private function ensureReservationWorkflowInstance(int $fiscalYearId, int $executionVersionId, array $reservation, int $userId): ?array
    {
        if (!$this->supportsWorkflowEngineFoundation()) {
            return null;
        }

        $reservationId = (int) ($reservation['ReservationID'] ?? 0);
        if ($reservationId <= 0) {
            return null;
        }

        $statusCode = strtoupper(trim((string) ($reservation['ReservationStatusCode'] ?? 'DRAFT')));
        $initialStageCode = in_array($statusCode, ['APPROVED', 'CANCELLED'], true) ? $statusCode : 'DRAFT';
        $existing = $this->workflowEngine()->getWorkflowInstanceByRecord(
            'BE_RESERVATION',
            'dbo.tblBeReservation',
            (string) $reservationId
        );
        $scopeDataObjectCode = $this->inferReservationScopeDataObjectCode($fiscalYearId, $executionVersionId, $reservationId);
        if ($scopeDataObjectCode === null && $existing !== null) {
            $scopeDataObjectCode = $this->nullableString($existing['ScopeDataObjectCode'] ?? null);
        }

        return $this->workflowEngine()->ensureWorkflowInstance([
            'WorkflowAreaCode' => 'BE_RESERVATION',
            'RecordTableName' => 'dbo.tblBeReservation',
            'RecordID' => $reservationId,
            'RecordKey' => (string) $reservationId,
            'FiscalYearID' => $fiscalYearId,
            'VersionID' => $executionVersionId,
            'ScopeDataObjectCode' => $scopeDataObjectCode,
            'CurrentStageCode' => $initialStageCode,
            'CurrentStatusCode' => $statusCode,
            'WorkflowTitle' => (string) ($reservation['ReservationTitle'] ?? ''),
            'WorkflowNote' => $this->nullableString($reservation['Notes'] ?? null),
            'UserID' => $userId,
        ]);
    }

    private function inferReservationScopeDataObjectCode(int $fiscalYearId, int $executionVersionId, int $reservationId): ?string
    {
        if (!$this->supportsReservationFoundation() || $reservationId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS DistinctScopeCount,
                   MIN(NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS ScopeDataObjectCode
            FROM dbo.tblBeReservationLine
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
              AND ReservationID = :reservationId
              AND ISNULL(ActiveFlag, 1) = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':reservationId' => $reservationId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $distinctCount = (int) ($row['DistinctScopeCount'] ?? 0);
        $scopeDataObjectCode = trim((string) ($row['ScopeDataObjectCode'] ?? ''));
        if ($distinctCount === 1 && $scopeDataObjectCode !== '') {
            return $scopeDataObjectCode;
        }

        return null;
    }

    private function isReservationEditable(int $fiscalYearId, int $executionVersionId, array $reservation): bool
    {
        $statusCode = strtoupper(trim((string) ($reservation['ReservationStatusCode'] ?? '')));
        if ($statusCode !== 'DRAFT') {
            return false;
        }

        if (!$this->supportsWorkflowEngineFoundation()) {
            return true;
        }

        $reservationId = (int) ($reservation['ReservationID'] ?? 0);
        if ($reservationId <= 0) {
            return false;
        }

        $workflowState = $this->getReservationWorkflowState($fiscalYearId, $executionVersionId, $reservationId);
        $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($instance === null) {
            return true;
        }

        return strtoupper(trim((string) ($instance['CurrentStageCode'] ?? ''))) === 'DRAFT';
    }

    public function getReservationModuleSummary(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || !$this->supportsReservationFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [
                'ReservationCount' => 0,
                'ApprovedReservationCount' => 0,
                'ApprovedReservedAmountTotal' => 0.0,
                'PlannedReservedAmountTotal' => 0.0,
                'AvailableReservationCapacityTotal' => 0.0,
            ];
        }

        $sql = "
            SELECT COUNT(*) AS ReservationCount,
                   SUM(CASE WHEN r.ReservationStatusCode = N'APPROVED' THEN 1 ELSE 0 END) AS ApprovedReservationCount,
                   COALESCE(SUM(COALESCE(CASE WHEN r.ReservationStatusCode = N'APPROVED' THEN line.TotalReservationAmount ELSE 0 END, 0)), 0) AS ApprovedReservedAmountTotal,
                   COALESCE(SUM(COALESCE(line.TotalReservationAmount, 0)), 0) AS PlannedReservedAmountTotal
            FROM dbo.tblBeReservation r
            OUTER APPLY (
                SELECT SUM(COALESCE(rl.ReservationAmount, 0)) AS TotalReservationAmount
                FROM dbo.tblBeReservationLine rl
                WHERE rl.ReservationID = r.ReservationID
                  AND ISNULL(rl.ActiveFlag, 1) = 1
            ) line
            WHERE r.FiscalYearID = :fy
              AND r.ExecutionVersionID = :executionVersionId
              AND ISNULL(r.ActiveFlag, 1) = 1
              AND r.ReservationStatusCode <> N'CANCELLED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

        $opening = $this->getOpeningBalanceSummary($fiscalYearId, $executionVersionId);
        $plannedReservedAmountTotal = (float) ($row['PlannedReservedAmountTotal'] ?? 0);
        $approvedReleasedAmountTotal = (float) ($opening['ReleasedAmountTotal'] ?? 0);
        $approvedCommittedAmountTotal = (float) ($opening['CommittedAmountTotal'] ?? 0);

        return [
            'ReservationCount' => (int) ($row['ReservationCount'] ?? 0),
            'ApprovedReservationCount' => (int) ($row['ApprovedReservationCount'] ?? 0),
            'ApprovedReservedAmountTotal' => (float) ($row['ApprovedReservedAmountTotal'] ?? 0),
            'PlannedReservedAmountTotal' => $plannedReservedAmountTotal,
            'AvailableReservationCapacityTotal' => max(0, $approvedReleasedAmountTotal - $approvedCommittedAmountTotal - $plannedReservedAmountTotal),
        ];
    }

    public function listReservations(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsReservationFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $sql = "
            SELECT r.*,
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalReservationAmount, 0) AS TotalReservationAmount
            FROM dbo.tblBeReservation r
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(rl.ReservationAmount, 0)), 0) AS TotalReservationAmount
                FROM dbo.tblBeReservationLine rl
                WHERE rl.ReservationID = r.ReservationID
                  AND ISNULL(rl.ActiveFlag, 1) = 1
            ) line
            WHERE r.FiscalYearID = :fy
              AND r.ExecutionVersionID = :executionVersionId
              AND ISNULL(r.ActiveFlag, 1) = 1
            ORDER BY r.CreatedDate DESC, r.ReservationID DESC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getReservation(int $fiscalYearId, int $executionVersionId, int $reservationId): ?array
    {
        if (!$this->supportsReservationFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $reservationId <= 0) {
            return null;
        }

        $sql = "
            SELECT r.*,
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalReservationAmount, 0) AS TotalReservationAmount
            FROM dbo.tblBeReservation r
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(rl.ReservationAmount, 0)), 0) AS TotalReservationAmount
                FROM dbo.tblBeReservationLine rl
                WHERE rl.ReservationID = r.ReservationID
                  AND ISNULL(rl.ActiveFlag, 1) = 1
            ) line
            WHERE r.FiscalYearID = :fy
              AND r.ExecutionVersionID = :executionVersionId
              AND r.ReservationID = :reservationId
              AND ISNULL(r.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':reservationId' => $reservationId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createReservation(array $data): int
    {
        if (!$this->supportsReservationFoundation()) {
            throw new \RuntimeException('Budget Execution Reservation tables are not installed. Run create_budget_execution_reservations_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $title = trim((string) ($data['ReservationTitle'] ?? ''));
        $reservationDate = $this->normalizeDate($data['ReservationDate'] ?? null);
        $effectiveDate = $this->normalizeDate($data['EffectiveDate'] ?? null);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $scopeDataObjectCode = $this->nullableString($data['ScopeDataObjectCode'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($fiscalYearId <= 0 || $executionVersionId <= 0) {
            throw new \RuntimeException('Execution context is required for the reservation.');
        }
        if ($title === '') {
            throw new \RuntimeException('Reservation title is required.');
        }

        $executionVersion = $this->getVersion($fiscalYearId, $executionVersionId);
        if ($executionVersion === null || strtoupper(trim((string) ($executionVersion['VersionTypeCode'] ?? ''))) !== 'EXECUTION') {
            throw new \RuntimeException('Reservations can only be created inside an execution version.');
        }

        $reservationNo = $this->nextReservationNumber($fiscalYearId, $executionVersionId);

        $sql = "
            INSERT INTO dbo.tblBeReservation (
                FiscalYearID,
                ExecutionVersionID,
                ReservationNo,
                ReservationTitle,
                ReservationStatusCode,
                ReservationDate,
                EffectiveDate,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.ReservationID
            VALUES (
                :fy,
                :executionVersionId,
                :reservationNo,
                :reservationTitle,
                N'DRAFT',
                :reservationDate,
                :effectiveDate,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':reservationNo' => $reservationNo,
            ':reservationTitle' => $title,
            ':reservationDate' => $reservationDate ?? date('Y-m-d'),
            ':effectiveDate' => $effectiveDate,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        $reservationId = (int) ($st->fetchColumn() ?: 0);
        if ($reservationId <= 0) {
            throw new \RuntimeException('Reservation could not be created.');
        }

        if ($this->supportsWorkflowEngineFoundation()) {
            $this->workflowEngine()->ensureWorkflowInstance([
                'WorkflowAreaCode' => 'BE_RESERVATION',
                'RecordTableName' => 'dbo.tblBeReservation',
                'RecordID' => $reservationId,
                'RecordKey' => (string) $reservationId,
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => $executionVersionId,
                'ScopeDataObjectCode' => $scopeDataObjectCode,
                'WorkflowTitle' => $title,
                'WorkflowNote' => $notes,
                'UserID' => $userId,
            ]);
        }

        return $reservationId;
    }

    public function listReservationLines(int $fiscalYearId, int $executionVersionId, int $reservationId): array
    {
        if (!$this->supportsReservationFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $reservationId <= 0) {
            return [];
        }

        $sql = "
            SELECT rl.*,
                   ob.OpeningAmount,
                   ob.CurrentAuthorizedAmount
            FROM dbo.tblBeReservationLine rl
            INNER JOIN dbo.tblBeExecutionOpeningBalance ob
                ON ob.ExecutionOpeningBalanceID = rl.ExecutionOpeningBalanceID
            WHERE rl.FiscalYearID = :fy
              AND rl.ExecutionVersionID = :executionVersionId
              AND rl.ReservationID = :reservationId
              AND ISNULL(rl.ActiveFlag, 1) = 1
            ORDER BY rl.ReservationLineID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':reservationId' => $reservationId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listReservationBalanceCandidates(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || !$this->supportsWarrantFoundation() || !$this->supportsReservationFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $sql = "
            SELECT b.*,
                   COALESCE(rel.ApprovedReleasedAmountTotal, 0) AS ApprovedReleasedAmountTotal,
                   COALESCE(res.PlannedReservedAmountTotal, 0) AS PlannedReservedAmountTotal,
                   COALESCE(com.PlannedCommitmentAmountTotal, 0) AS PlannedCommitmentAmountTotal,
                   COALESCE(rel.ApprovedReleasedAmountTotal, 0) - COALESCE(res.PlannedReservedAmountTotal, 0) - COALESCE(com.PlannedCommitmentAmountTotal, 0) AS AvailableReservationCapacity
            FROM dbo.tblBeExecutionOpeningBalance b
            LEFT JOIN (
                SELECT wl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(wl.ReleaseAmount, 0)) AS ApprovedReleasedAmountTotal
                FROM dbo.tblBeWarrantLine wl
                INNER JOIN dbo.tblBeWarrant w
                    ON w.WarrantID = wl.WarrantID
                WHERE ISNULL(wl.ActiveFlag, 1) = 1
                  AND ISNULL(w.ActiveFlag, 1) = 1
                  AND w.WarrantStatusCode = N'APPROVED'
                GROUP BY wl.ExecutionOpeningBalanceID
            ) rel
                ON rel.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID
            LEFT JOIN (
                SELECT rl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(rl.ReservationAmount, 0)) AS PlannedReservedAmountTotal
                FROM dbo.tblBeReservationLine rl
                INNER JOIN dbo.tblBeReservation r
                    ON r.ReservationID = rl.ReservationID
                WHERE ISNULL(rl.ActiveFlag, 1) = 1
                  AND ISNULL(r.ActiveFlag, 1) = 1
                  AND r.ReservationStatusCode <> N'CANCELLED'
                GROUP BY rl.ExecutionOpeningBalanceID
            ) res
                ON res.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID
            LEFT JOIN (
                SELECT cl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(cl.CommitmentAmount, 0)) AS PlannedCommitmentAmountTotal
                FROM dbo.tblBeCommitmentLine cl
                INNER JOIN dbo.tblBeCommitment c
                    ON c.CommitmentID = cl.CommitmentID
                WHERE ISNULL(cl.ActiveFlag, 1) = 1
                  AND ISNULL(c.ActiveFlag, 1) = 1
                  AND c.CommitmentStatusCode <> N'CANCELLED'
                GROUP BY cl.ExecutionOpeningBalanceID
            ) com
                ON com.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID
            WHERE b.FiscalYearID = :fy
              AND b.ExecutionVersionID = :executionVersionId
              AND ISNULL(b.ActiveFlag, 1) = 1
            ORDER BY b.DataObjectCode ASC,
                     b.SectorID ASC,
                     b.ProgramID ASC,
                     b.ProjectID ASC,
                     b.SourceSubmissionLineID ASC,
                     b.ExecutionOpeningBalanceID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function addReservationLine(array $data): int
    {
        if (!$this->supportsReservationFoundation()) {
            throw new \RuntimeException('Budget Execution Reservation tables are not installed. Run create_budget_execution_reservations_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $reservationId = (int) ($data['ReservationID'] ?? 0);
        $executionOpeningBalanceId = (int) ($data['ExecutionOpeningBalanceID'] ?? 0);
        $reservationAmount = (float) ($data['ReservationAmount'] ?? 0);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($reservationAmount <= 0) {
            throw new \RuntimeException('Reservation amount must be greater than zero.');
        }

        $reservation = $this->getReservation($fiscalYearId, $executionVersionId, $reservationId);
        if ($reservation === null) {
            throw new \RuntimeException('Selected reservation was not found.');
        }
        if (!$this->isReservationEditable($fiscalYearId, $executionVersionId, $reservation)) {
            throw new \RuntimeException('Lines can only be added to a draft reservation.');
        }

        $openingBalance = $this->getOpeningBalanceRow($fiscalYearId, $executionVersionId, $executionOpeningBalanceId);
        if ($openingBalance === null) {
            throw new \RuntimeException('Selected execution balance line was not found.');
        }

        $approvedReleased = $this->getApprovedReleasedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $plannedReserved = $this->getPlannedReservedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $plannedCommitted = $this->getPlannedCommittedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $availableReservationCapacity = $approvedReleased - $plannedReserved - $plannedCommitted;

        if ($reservationAmount > $availableReservationCapacity) {
            throw new \RuntimeException(
                'Reservation amount exceeds the available released balance for the selected line. Available: '
                . number_format(max(0, $availableReservationCapacity), 2)
            );
        }

        $sql = "
            INSERT INTO dbo.tblBeReservationLine (
                ReservationID,
                FiscalYearID,
                ExecutionVersionID,
                ExecutionOpeningBalanceID,
                DataObjectCode,
                OrgUnitID,
                SectorID,
                ProgramID,
                SubProgramID,
                ProjectID,
                ActivityID,
                FundingTypeID,
                FundingSourceID,
                EconomicItemID,
                BidTitle,
                ReservationAmount,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.ReservationLineID
            VALUES (
                :reservationId,
                :fy,
                :executionVersionId,
                :executionOpeningBalanceId,
                :dataObjectCode,
                :orgUnitId,
                :sectorId,
                :programId,
                :subProgramId,
                :projectId,
                :activityId,
                :fundingTypeId,
                :fundingSourceId,
                :economicItemId,
                :bidTitle,
                :reservationAmount,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':reservationId' => $reservationId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
            ':dataObjectCode' => (string) ($openingBalance['DataObjectCode'] ?? ''),
            ':orgUnitId' => $openingBalance['OrgUnitID'] ?? null,
            ':sectorId' => $openingBalance['SectorID'] ?? null,
            ':programId' => $openingBalance['ProgramID'] ?? null,
            ':subProgramId' => $openingBalance['SubProgramID'] ?? null,
            ':projectId' => $openingBalance['ProjectID'] ?? null,
            ':activityId' => $openingBalance['ActivityID'] ?? null,
            ':fundingTypeId' => $openingBalance['FundingTypeID'] ?? null,
            ':fundingSourceId' => $openingBalance['FundingSourceID'] ?? null,
            ':economicItemId' => $openingBalance['EconomicItemID'] ?? null,
            ':bidTitle' => $this->nullableString($openingBalance['BidTitle'] ?? null),
            ':reservationAmount' => $reservationAmount,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    public function deleteReservationLine(int $fiscalYearId, int $executionVersionId, int $reservationLineId, int $userId): void
    {
        if (!$this->supportsReservationFoundation() || $reservationLineId <= 0) {
            throw new \RuntimeException('Reservation line could not be removed.');
        }

        $sql = "
            SELECT TOP 1 rl.ReservationLineID,
                   rl.ReservationID,
                   r.ReservationStatusCode
            FROM dbo.tblBeReservationLine rl
            INNER JOIN dbo.tblBeReservation r
                ON r.ReservationID = rl.ReservationID
            WHERE rl.ReservationLineID = :reservationLineId
              AND rl.FiscalYearID = :fy
              AND rl.ExecutionVersionID = :executionVersionId
              AND ISNULL(rl.ActiveFlag, 1) = 1
              AND ISNULL(r.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':reservationLineId' => $reservationLineId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            throw new \RuntimeException('Reservation line was not found.');
        }
        $reservation = $this->getReservation($fiscalYearId, $executionVersionId, (int) ($row['ReservationID'] ?? 0));
        if ($reservation === null || !$this->isReservationEditable($fiscalYearId, $executionVersionId, $reservation)) {
            throw new \RuntimeException('Only draft reservation lines can be removed.');
        }

        $deleteSql = "
            UPDATE dbo.tblBeReservationLine
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE ReservationLineID = :reservationLineId
        ";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->execute([
            ':userId' => $userId > 0 ? $userId : 1,
            ':reservationLineId' => $reservationLineId,
        ]);
    }

    public function approveReservation(int $fiscalYearId, int $executionVersionId, int $reservationId, int $userId): void
    {
        $reservation = $this->getReservation($fiscalYearId, $executionVersionId, $reservationId);
        if ($reservation === null) {
            throw new \RuntimeException('Reservation was not found.');
        }
        if (strtoupper(trim((string) ($reservation['ReservationStatusCode'] ?? ''))) !== 'DRAFT') {
            throw new \RuntimeException('Only draft reservations can be approved.');
        }
        if ((int) ($reservation['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('A reservation must contain at least one line before approval.');
        }

        $workflowState = $this->getReservationWorkflowState($fiscalYearId, $executionVersionId, $reservationId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        $workflowStageCode = strtoupper(trim((string) ($workflowInstance['CurrentStageCode'] ?? '')));
        if ($workflowInstance !== null && $workflowStageCode !== '' && $workflowStageCode !== 'FINAL_APPROVAL') {
            throw new \RuntimeException('This reservation must reach Final Approval stage before it can be approved.');
        }

        $lines = $this->listReservationLines($fiscalYearId, $executionVersionId, $reservationId);
        foreach ($lines as $line) {
            $openingBalanceId = (int) ($line['ExecutionOpeningBalanceID'] ?? 0);
            $approvedReleased = $this->getApprovedReleasedAmountAgainstOpeningBalance($openingBalanceId);
            $otherPlannedReserved = $this->getPlannedReservedAmountAgainstOpeningBalance($openingBalanceId, $reservationId);
            $plannedCommitted = $this->getPlannedCommittedAmountAgainstOpeningBalance($openingBalanceId);
            $lineAmount = (float) ($line['ReservationAmount'] ?? 0);
            if ($lineAmount > ($approvedReleased - $otherPlannedReserved - $plannedCommitted)) {
                throw new \RuntimeException('One or more reservation lines exceed the available released balance at approval time.');
            }
        }

        $this->conn->beginTransaction();
        try {
            $sql = "
                UPDATE dbo.tblBeReservation
                SET ReservationStatusCode = N'APPROVED',
                    ApprovedBy = :approvedBy,
                    ApprovedDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ReservationID = :reservationId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':approvedBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':reservationId' => $reservationId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'APPROVE',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function cancelReservation(int $fiscalYearId, int $executionVersionId, int $reservationId, int $userId): void
    {
        $reservation = $this->getReservation($fiscalYearId, $executionVersionId, $reservationId);
        if ($reservation === null) {
            throw new \RuntimeException('Reservation was not found.');
        }
        if (strtoupper(trim((string) ($reservation['ReservationStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('This reservation has already been cancelled.');
        }

        $workflowState = $this->getReservationWorkflowState($fiscalYearId, $executionVersionId, $reservationId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;

        $this->conn->beginTransaction();
        try {
            $sql = "
                UPDATE dbo.tblBeReservation
                SET ReservationStatusCode = N'CANCELLED',
                    CancelledBy = :cancelledBy,
                    CancelledDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE ReservationID = :reservationId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':cancelledBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':reservationId' => $reservationId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'CANCEL',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function transitionReservationWorkflow(int $fiscalYearId, int $executionVersionId, int $reservationId, string $workflowActionCode, int $userId, ?string $note = null): array
    {
        $reservation = $this->getReservation($fiscalYearId, $executionVersionId, $reservationId);
        if ($reservation === null) {
            throw new \RuntimeException('Reservation was not found.');
        }
        if (!$this->supportsWorkflowEngineFoundation()) {
            throw new \RuntimeException('Shared workflow engine foundation is not installed.');
        }
        if (strtoupper(trim((string) ($reservation['ReservationStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('Cancelled reservations cannot continue through workflow.');
        }

        $actionCode = strtoupper(trim($workflowActionCode));
        if (!in_array($actionCode, ['SUBMIT', 'FORWARD', 'RETURN'], true)) {
            throw new \RuntimeException('Workflow action is not supported for reservations.');
        }
        if ((int) ($reservation['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('A reservation must contain at least one line before workflow submission.');
        }

        $workflowState = $this->getReservationWorkflowState($fiscalYearId, $executionVersionId, $reservationId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($workflowInstance === null) {
            throw new \RuntimeException('Workflow instance could not be resolved for this reservation.');
        }

        $updatedInstance = $this->workflowEngine()->transitionWorkflowInstance(
            (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
            $actionCode,
            $userId,
            $note
        );

        $statusSql = "
            UPDATE dbo.tblBeReservation
            SET UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE ReservationID = :reservationId
        ";
        $statusStmt = $this->conn->prepare($statusSql);
        $statusStmt->execute([
            ':updatedBy' => $userId > 0 ? $userId : 1,
            ':reservationId' => $reservationId,
        ]);

        return $updatedInstance;
    }

    public function getRieModuleSummary(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || !$this->supportsRieFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [
                'RieCount' => 0,
                'ApprovedRieCount' => 0,
                'ApprovedRieAmountTotal' => 0.0,
                'PlannedRieAmountTotal' => 0.0,
                'CurrentAvailableReleasedTotal' => 0.0,
            ];
        }

        $sql = "
            SELECT COUNT(*) AS RieCount,
                   SUM(CASE WHEN r.RieStatusCode = N'APPROVED' THEN 1 ELSE 0 END) AS ApprovedRieCount,
                   COALESCE(SUM(COALESCE(CASE WHEN r.RieStatusCode = N'APPROVED' THEN line.TotalRieAmount ELSE 0 END, 0)), 0) AS ApprovedRieAmountTotal,
                   COALESCE(SUM(COALESCE(line.TotalRieAmount, 0)), 0) AS PlannedRieAmountTotal
            FROM dbo.tblBeRie r
            OUTER APPLY (
                SELECT SUM(COALESCE(rl.RieAmount, 0)) AS TotalRieAmount
                FROM dbo.tblBeRieLine rl
                WHERE rl.RieID = r.RieID
                  AND ISNULL(rl.ActiveFlag, 1) = 1
            ) line
            WHERE r.FiscalYearID = :fy
              AND r.ExecutionVersionID = :executionVersionId
              AND ISNULL(r.ActiveFlag, 1) = 1
              AND r.RieStatusCode <> N'CANCELLED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

        $opening = $this->getOpeningBalanceSummary($fiscalYearId, $executionVersionId);

        return [
            'RieCount' => (int) ($row['RieCount'] ?? 0),
            'ApprovedRieCount' => (int) ($row['ApprovedRieCount'] ?? 0),
            'ApprovedRieAmountTotal' => (float) ($row['ApprovedRieAmountTotal'] ?? 0),
            'PlannedRieAmountTotal' => (float) ($row['PlannedRieAmountTotal'] ?? 0),
            'CurrentAvailableReleasedTotal' => (float) ($opening['AvailableReleasedAmountTotal'] ?? 0),
        ];
    }

    public function listRies(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsRieFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $sql = "
            SELECT r.*,
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalRieAmount, 0) AS TotalRieAmount
            FROM dbo.tblBeRie r
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(rl.RieAmount, 0)), 0) AS TotalRieAmount
                FROM dbo.tblBeRieLine rl
                WHERE rl.RieID = r.RieID
                  AND ISNULL(rl.ActiveFlag, 1) = 1
            ) line
            WHERE r.FiscalYearID = :fy
              AND r.ExecutionVersionID = :executionVersionId
              AND ISNULL(r.ActiveFlag, 1) = 1
            ORDER BY r.CreatedDate DESC, r.RieID DESC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getRie(int $fiscalYearId, int $executionVersionId, int $rieId): ?array
    {
        if (!$this->supportsRieFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $rieId <= 0) {
            return null;
        }

        $sql = "
            SELECT r.*,
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalRieAmount, 0) AS TotalRieAmount
            FROM dbo.tblBeRie r
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(rl.RieAmount, 0)), 0) AS TotalRieAmount
                FROM dbo.tblBeRieLine rl
                WHERE rl.RieID = r.RieID
                  AND ISNULL(rl.ActiveFlag, 1) = 1
            ) line
            WHERE r.FiscalYearID = :fy
              AND r.ExecutionVersionID = :executionVersionId
              AND r.RieID = :rieId
              AND ISNULL(r.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':rieId' => $rieId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createRie(array $data): int
    {
        if (!$this->supportsRieFoundation()) {
            throw new \RuntimeException('Budget Execution RIE tables are not installed. Run create_budget_execution_rie_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $title = trim((string) ($data['RieTitle'] ?? ''));
        $rieDate = $this->normalizeDate($data['RieDate'] ?? null);
        $effectiveDate = $this->normalizeDate($data['EffectiveDate'] ?? null);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $scopeDataObjectCode = $this->nullableString($data['ScopeDataObjectCode'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($fiscalYearId <= 0 || $executionVersionId <= 0) {
            throw new \RuntimeException('Execution context is required for the RIE.');
        }
        if ($title === '') {
            throw new \RuntimeException('RIE title is required.');
        }

        $executionVersion = $this->getVersion($fiscalYearId, $executionVersionId);
        if ($executionVersion === null || strtoupper(trim((string) ($executionVersion['VersionTypeCode'] ?? ''))) !== 'EXECUTION') {
            throw new \RuntimeException('RIEs can only be created inside an execution version.');
        }

        $rieNo = $this->nextRieNumber($fiscalYearId, $executionVersionId);

        $sql = "
            INSERT INTO dbo.tblBeRie (
                FiscalYearID,
                ExecutionVersionID,
                RieNo,
                RieTitle,
                RieStatusCode,
                RieDate,
                EffectiveDate,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.RieID
            VALUES (
                :fy,
                :executionVersionId,
                :rieNo,
                :rieTitle,
                N'DRAFT',
                :rieDate,
                :effectiveDate,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':rieNo' => $rieNo,
            ':rieTitle' => $title,
            ':rieDate' => $rieDate ?? date('Y-m-d'),
            ':effectiveDate' => $effectiveDate,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        $rieId = (int) ($st->fetchColumn() ?: 0);
        if ($rieId <= 0) {
            throw new \RuntimeException('RIE could not be created.');
        }

        if ($this->supportsWorkflowEngineFoundation()) {
            $this->workflowEngine()->ensureWorkflowInstance([
                'WorkflowAreaCode' => 'BE_RIE',
                'RecordTableName' => 'dbo.tblBeRie',
                'RecordID' => $rieId,
                'RecordKey' => (string) $rieId,
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => $executionVersionId,
                'ScopeDataObjectCode' => $scopeDataObjectCode,
                'WorkflowTitle' => $title,
                'WorkflowNote' => $notes,
                'UserID' => $userId,
            ]);
        }

        return $rieId;
    }

    public function listRieLines(int $fiscalYearId, int $executionVersionId, int $rieId): array
    {
        if (!$this->supportsRieFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $rieId <= 0) {
            return [];
        }

        $sql = "
            SELECT rl.*,
                   ob.OpeningAmount,
                   ob.CurrentAuthorizedAmount
            FROM dbo.tblBeRieLine rl
            INNER JOIN dbo.tblBeExecutionOpeningBalance ob
                ON ob.ExecutionOpeningBalanceID = rl.ExecutionOpeningBalanceID
            WHERE rl.FiscalYearID = :fy
              AND rl.ExecutionVersionID = :executionVersionId
              AND rl.RieID = :rieId
              AND ISNULL(rl.ActiveFlag, 1) = 1
            ORDER BY rl.RieLineID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':rieId' => $rieId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listRieBalanceCandidates(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || !$this->supportsWarrantFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $reservationSelect = $this->supportsReservationFoundation()
            ? "COALESCE(res.ApprovedReservedAmountTotal, 0) AS ApprovedReservedAmountTotal,"
            : "CAST(0 AS DECIMAL(19,6)) AS ApprovedReservedAmountTotal,";
        $reservationJoin = $this->supportsReservationFoundation()
            ? "LEFT JOIN (
                SELECT rl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(rl.ReservationAmount, 0)) AS ApprovedReservedAmountTotal
                FROM dbo.tblBeReservationLine rl
                INNER JOIN dbo.tblBeReservation r
                    ON r.ReservationID = rl.ReservationID
                WHERE ISNULL(rl.ActiveFlag, 1) = 1
                  AND ISNULL(r.ActiveFlag, 1) = 1
                  AND r.ReservationStatusCode = N'APPROVED'
                GROUP BY rl.ExecutionOpeningBalanceID
            ) res
                ON res.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "";
        $commitmentSelect = $this->supportsCommitmentFoundation()
            ? "COALESCE(com.ApprovedCommittedAmountTotal, 0) AS ApprovedCommittedAmountTotal,"
            : "CAST(0 AS DECIMAL(19,6)) AS ApprovedCommittedAmountTotal,";
        $commitmentJoin = $this->supportsCommitmentFoundation()
            ? "LEFT JOIN (
                SELECT cl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(cl.CommitmentAmount, 0)) AS ApprovedCommittedAmountTotal
                FROM dbo.tblBeCommitmentLine cl
                INNER JOIN dbo.tblBeCommitment c
                    ON c.CommitmentID = cl.CommitmentID
                WHERE ISNULL(cl.ActiveFlag, 1) = 1
                  AND ISNULL(c.ActiveFlag, 1) = 1
                  AND c.CommitmentStatusCode = N'APPROVED'
                GROUP BY cl.ExecutionOpeningBalanceID
            ) com
                ON com.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "";
        $approvedReservedExpr = $this->supportsReservationFoundation()
            ? "COALESCE(res.ApprovedReservedAmountTotal, 0)"
            : "0";
        $approvedCommittedExpr = $this->supportsCommitmentFoundation()
            ? "COALESCE(com.ApprovedCommittedAmountTotal, 0)"
            : "0";

        $sql = "
            SELECT b.*,
                   COALESCE(rel.ApprovedReleasedAmountTotal, 0) AS ApprovedReleasedAmountTotal,
                   {$reservationSelect}
                   {$commitmentSelect}
                   COALESCE(rel.ApprovedReleasedAmountTotal, 0) - {$approvedReservedExpr} - {$approvedCommittedExpr} AS AvailableReleasedForRie
            FROM dbo.tblBeExecutionOpeningBalance b
            LEFT JOIN (
                SELECT wl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(wl.ReleaseAmount, 0)) AS ApprovedReleasedAmountTotal
                FROM dbo.tblBeWarrantLine wl
                INNER JOIN dbo.tblBeWarrant w
                    ON w.WarrantID = wl.WarrantID
                WHERE ISNULL(wl.ActiveFlag, 1) = 1
                  AND ISNULL(w.ActiveFlag, 1) = 1
                  AND w.WarrantStatusCode = N'APPROVED'
                GROUP BY wl.ExecutionOpeningBalanceID
            ) rel
                ON rel.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID
            {$reservationJoin}
            {$commitmentJoin}
            WHERE b.FiscalYearID = :fy
              AND b.ExecutionVersionID = :executionVersionId
              AND ISNULL(b.ActiveFlag, 1) = 1
            ORDER BY b.DataObjectCode ASC,
                     b.SectorID ASC,
                     b.ProgramID ASC,
                     b.ProjectID ASC,
                     b.SourceSubmissionLineID ASC,
                     b.ExecutionOpeningBalanceID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function addRieLine(array $data): int
    {
        if (!$this->supportsRieFoundation()) {
            throw new \RuntimeException('Budget Execution RIE tables are not installed. Run create_budget_execution_rie_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $rieId = (int) ($data['RieID'] ?? 0);
        $executionOpeningBalanceId = (int) ($data['ExecutionOpeningBalanceID'] ?? 0);
        $rieAmount = (float) ($data['RieAmount'] ?? 0);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($rieAmount <= 0) {
            throw new \RuntimeException('RIE amount must be greater than zero.');
        }

        $rie = $this->getRie($fiscalYearId, $executionVersionId, $rieId);
        if ($rie === null) {
            throw new \RuntimeException('Selected RIE was not found.');
        }
        if (!$this->isRieEditable($fiscalYearId, $executionVersionId, $rie)) {
            throw new \RuntimeException('Lines can only be added to a draft RIE.');
        }

        $openingBalance = $this->getOpeningBalanceRow($fiscalYearId, $executionVersionId, $executionOpeningBalanceId);
        if ($openingBalance === null) {
            throw new \RuntimeException('Selected execution balance line was not found.');
        }

        $approvedReleased = $this->getApprovedReleasedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $approvedReserved = $this->getApprovedReservedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $approvedCommitted = $this->getApprovedCommittedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $availableReleased = $approvedReleased - $approvedReserved - $approvedCommitted;

        if ($rieAmount > $availableReleased) {
            throw new \RuntimeException(
                'RIE amount exceeds the current available released balance for the selected line. Available: '
                . number_format(max(0, $availableReleased), 2)
            );
        }

        $sql = "
            INSERT INTO dbo.tblBeRieLine (
                RieID,
                FiscalYearID,
                ExecutionVersionID,
                ExecutionOpeningBalanceID,
                DataObjectCode,
                OrgUnitID,
                SectorID,
                ProgramID,
                SubProgramID,
                ProjectID,
                ActivityID,
                FundingTypeID,
                FundingSourceID,
                EconomicItemID,
                BidTitle,
                RieAmount,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.RieLineID
            VALUES (
                :rieId,
                :fy,
                :executionVersionId,
                :executionOpeningBalanceId,
                :dataObjectCode,
                :orgUnitId,
                :sectorId,
                :programId,
                :subProgramId,
                :projectId,
                :activityId,
                :fundingTypeId,
                :fundingSourceId,
                :economicItemId,
                :bidTitle,
                :rieAmount,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':rieId' => $rieId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
            ':dataObjectCode' => (string) ($openingBalance['DataObjectCode'] ?? ''),
            ':orgUnitId' => $openingBalance['OrgUnitID'] ?? null,
            ':sectorId' => $openingBalance['SectorID'] ?? null,
            ':programId' => $openingBalance['ProgramID'] ?? null,
            ':subProgramId' => $openingBalance['SubProgramID'] ?? null,
            ':projectId' => $openingBalance['ProjectID'] ?? null,
            ':activityId' => $openingBalance['ActivityID'] ?? null,
            ':fundingTypeId' => $openingBalance['FundingTypeID'] ?? null,
            ':fundingSourceId' => $openingBalance['FundingSourceID'] ?? null,
            ':economicItemId' => $openingBalance['EconomicItemID'] ?? null,
            ':bidTitle' => $this->nullableString($openingBalance['BidTitle'] ?? null),
            ':rieAmount' => $rieAmount,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    public function deleteRieLine(int $fiscalYearId, int $executionVersionId, int $rieLineId, int $userId): void
    {
        if (!$this->supportsRieFoundation() || $rieLineId <= 0) {
            throw new \RuntimeException('RIE line could not be removed.');
        }

        $sql = "
            SELECT TOP 1 rl.RieLineID,
                   rl.RieID,
                   r.RieStatusCode
            FROM dbo.tblBeRieLine rl
            INNER JOIN dbo.tblBeRie r
                ON r.RieID = rl.RieID
            WHERE rl.RieLineID = :rieLineId
              AND rl.FiscalYearID = :fy
              AND rl.ExecutionVersionID = :executionVersionId
              AND ISNULL(rl.ActiveFlag, 1) = 1
              AND ISNULL(r.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':rieLineId' => $rieLineId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            throw new \RuntimeException('RIE line was not found.');
        }
        $rie = $this->getRie($fiscalYearId, $executionVersionId, (int) ($row['RieID'] ?? 0));
        if ($rie === null || !$this->isRieEditable($fiscalYearId, $executionVersionId, $rie)) {
            throw new \RuntimeException('Only draft RIE lines can be removed.');
        }

        $deleteSql = "
            UPDATE dbo.tblBeRieLine
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE RieLineID = :rieLineId
        ";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->execute([
            ':userId' => $userId > 0 ? $userId : 1,
            ':rieLineId' => $rieLineId,
        ]);
    }

    public function approveRie(int $fiscalYearId, int $executionVersionId, int $rieId, int $userId): void
    {
        $rie = $this->getRie($fiscalYearId, $executionVersionId, $rieId);
        if ($rie === null) {
            throw new \RuntimeException('RIE was not found.');
        }
        $statusCode = strtoupper(trim((string) ($rie['RieStatusCode'] ?? '')));
        if ($statusCode !== 'DRAFT') {
            throw new \RuntimeException('Only draft RIEs can be approved.');
        }
        if ((int) ($rie['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('An RIE must contain at least one line before approval.');
        }

        $workflowState = $this->getRieWorkflowState($fiscalYearId, $executionVersionId, $rieId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        $workflowStageCode = strtoupper(trim((string) ($workflowInstance['CurrentStageCode'] ?? '')));
        if ($workflowInstance !== null && $workflowStageCode !== '' && $workflowStageCode !== 'FINAL_APPROVAL') {
            throw new \RuntimeException('This RIE must reach Final Approval stage before it can be approved.');
        }

        $lines = $this->listRieLines($fiscalYearId, $executionVersionId, $rieId);
        foreach ($lines as $line) {
            $openingBalanceId = (int) ($line['ExecutionOpeningBalanceID'] ?? 0);
            $approvedReleased = $this->getApprovedReleasedAmountAgainstOpeningBalance($openingBalanceId);
            $approvedReserved = $this->getApprovedReservedAmountAgainstOpeningBalance($openingBalanceId);
            $approvedCommitted = $this->getApprovedCommittedAmountAgainstOpeningBalance($openingBalanceId);
            $lineAmount = (float) ($line['RieAmount'] ?? 0);
            if ($lineAmount > ($approvedReleased - $approvedReserved - $approvedCommitted)) {
                throw new \RuntimeException('One or more RIE lines exceed the current available released balance at approval time.');
            }
        }

        $this->conn->beginTransaction();
        try {
            $sql = "
                UPDATE dbo.tblBeRie
                SET RieStatusCode = N'APPROVED',
                    ApprovedBy = :approvedBy,
                    ApprovedDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE RieID = :rieId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':approvedBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':rieId' => $rieId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'APPROVE',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function cancelRie(int $fiscalYearId, int $executionVersionId, int $rieId, int $userId): void
    {
        $rie = $this->getRie($fiscalYearId, $executionVersionId, $rieId);
        if ($rie === null) {
            throw new \RuntimeException('RIE was not found.');
        }
        if (strtoupper(trim((string) ($rie['RieStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('This RIE has already been cancelled.');
        }

        $workflowState = $this->getRieWorkflowState($fiscalYearId, $executionVersionId, $rieId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;

        $this->conn->beginTransaction();
        try {
            $sql = "
                UPDATE dbo.tblBeRie
                SET RieStatusCode = N'CANCELLED',
                    CancelledBy = :cancelledBy,
                    CancelledDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE RieID = :rieId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':cancelledBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':rieId' => $rieId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'CANCEL',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function transitionRieWorkflow(int $fiscalYearId, int $executionVersionId, int $rieId, string $workflowActionCode, int $userId, ?string $note = null): array
    {
        $rie = $this->getRie($fiscalYearId, $executionVersionId, $rieId);
        if ($rie === null) {
            throw new \RuntimeException('RIE was not found.');
        }
        if (!$this->supportsWorkflowEngineFoundation()) {
            throw new \RuntimeException('Shared workflow engine foundation is not installed.');
        }
        if (strtoupper(trim((string) ($rie['RieStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('Cancelled RIEs cannot continue through workflow.');
        }

        $actionCode = strtoupper(trim($workflowActionCode));
        if (!in_array($actionCode, ['SUBMIT', 'FORWARD', 'RETURN'], true)) {
            throw new \RuntimeException('Workflow action is not supported for RIE.');
        }
        if ((int) ($rie['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('An RIE must contain at least one line before workflow submission.');
        }

        $workflowState = $this->getRieWorkflowState($fiscalYearId, $executionVersionId, $rieId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($workflowInstance === null) {
            throw new \RuntimeException('Workflow instance could not be resolved for this RIE.');
        }

        $updatedInstance = $this->workflowEngine()->transitionWorkflowInstance(
            (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
            $actionCode,
            $userId,
            $note
        );

        $statusSql = "
            UPDATE dbo.tblBeRie
            SET UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE RieID = :rieId
        ";
        $statusStmt = $this->conn->prepare($statusSql);
        $statusStmt->execute([
            ':updatedBy' => $userId > 0 ? $userId : 1,
            ':rieId' => $rieId,
        ]);

        return $updatedInstance;
    }

    public function listApprovedRies(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsRieFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $sql = "
            SELECT r.RieID,
                   r.RieNo,
                   r.RieTitle,
                   r.RieDate,
                   COALESCE(line.TotalRieAmount, 0) AS TotalRieAmount,
                   COALESCE(com.CommittedAmountAgainstRie, 0) AS CommittedAmountAgainstRie,
                   COALESCE(line.TotalRieAmount, 0) - COALESCE(com.CommittedAmountAgainstRie, 0) AS AvailableRieAmount
            FROM dbo.tblBeRie r
            OUTER APPLY (
                SELECT SUM(COALESCE(rl.RieAmount, 0)) AS TotalRieAmount
                FROM dbo.tblBeRieLine rl
                WHERE rl.RieID = r.RieID
                  AND ISNULL(rl.ActiveFlag, 1) = 1
            ) line
            OUTER APPLY (
                SELECT SUM(COALESCE(cl.CommitmentAmount, 0)) AS CommittedAmountAgainstRie
                FROM dbo.tblBeCommitment c
                INNER JOIN dbo.tblBeCommitmentLine cl
                    ON cl.CommitmentID = c.CommitmentID
                WHERE c.RieID = r.RieID
                  AND ISNULL(c.ActiveFlag, 1) = 1
                  AND ISNULL(cl.ActiveFlag, 1) = 1
                  AND c.CommitmentStatusCode <> N'CANCELLED'
            ) com
            WHERE r.FiscalYearID = :fy
              AND r.ExecutionVersionID = :executionVersionId
              AND ISNULL(r.ActiveFlag, 1) = 1
              AND r.RieStatusCode = N'APPROVED'
            ORDER BY r.RieDate DESC, r.RieID DESC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getCommitmentModuleSummary(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || !$this->supportsCommitmentFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [
                'CommitmentCount' => 0,
                'ApprovedCommitmentCount' => 0,
                'ApprovedCommittedAmountTotal' => 0.0,
                'PlannedCommittedAmountTotal' => 0.0,
                'AvailableCommitmentCapacityTotal' => 0.0,
            ];
        }

        $sql = "
            SELECT COUNT(*) AS CommitmentCount,
                   SUM(CASE WHEN c.CommitmentStatusCode = N'APPROVED' THEN 1 ELSE 0 END) AS ApprovedCommitmentCount,
                   COALESCE(SUM(COALESCE(CASE WHEN c.CommitmentStatusCode = N'APPROVED' THEN line.TotalCommitmentAmount ELSE 0 END, 0)), 0) AS ApprovedCommittedAmountTotal,
                   COALESCE(SUM(COALESCE(line.TotalCommitmentAmount, 0)), 0) AS PlannedCommittedAmountTotal
            FROM dbo.tblBeCommitment c
            OUTER APPLY (
                SELECT SUM(COALESCE(cl.CommitmentAmount, 0)) AS TotalCommitmentAmount
                FROM dbo.tblBeCommitmentLine cl
                WHERE cl.CommitmentID = c.CommitmentID
                  AND ISNULL(cl.ActiveFlag, 1) = 1
            ) line
            WHERE c.FiscalYearID = :fy
              AND c.ExecutionVersionID = :executionVersionId
              AND ISNULL(c.ActiveFlag, 1) = 1
              AND c.CommitmentStatusCode <> N'CANCELLED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

        $opening = $this->getOpeningBalanceSummary($fiscalYearId, $executionVersionId);
        $plannedCommittedAmountTotal = (float) ($row['PlannedCommittedAmountTotal'] ?? 0);
        $approvedReleasedAmountTotal = (float) ($opening['ReleasedAmountTotal'] ?? 0);
        $approvedReservedAmountTotal = (float) ($opening['ReservedAmountTotal'] ?? 0);

        return [
            'CommitmentCount' => (int) ($row['CommitmentCount'] ?? 0),
            'ApprovedCommitmentCount' => (int) ($row['ApprovedCommitmentCount'] ?? 0),
            'ApprovedCommittedAmountTotal' => (float) ($row['ApprovedCommittedAmountTotal'] ?? 0),
            'PlannedCommittedAmountTotal' => $plannedCommittedAmountTotal,
            'AvailableCommitmentCapacityTotal' => max(0, $approvedReleasedAmountTotal - $approvedReservedAmountTotal - $plannedCommittedAmountTotal),
        ];
    }

    public function annotateRiesWithWorkflowState(int $fiscalYearId, int $executionVersionId, array $rows): array
    {
        if ($rows === [] || !$this->supportsWorkflowEngineFoundation()) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $rieId = (int) ($row['RieID'] ?? 0);
            if ($rieId <= 0) {
                $row['WorkflowStageCode'] = '';
                $row['WorkflowStageName'] = '';
                continue;
            }

            $workflowState = $this->getRieWorkflowState($fiscalYearId, $executionVersionId, $rieId);
            $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
            $currentStage = is_array($workflowState['CurrentStage'] ?? null) ? $workflowState['CurrentStage'] : null;
            $row['WorkflowStageCode'] = (string) ($instance['CurrentStageCode'] ?? '');
            $row['WorkflowStageName'] = (string) ($currentStage['WorkflowStageName'] ?? ($instance['CurrentStageCode'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public function annotateCommitmentsWithWorkflowState(int $fiscalYearId, int $executionVersionId, array $rows): array
    {
        if ($rows === [] || !$this->supportsWorkflowEngineFoundation()) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $commitmentId = (int) ($row['CommitmentID'] ?? 0);
            if ($commitmentId <= 0) {
                $row['WorkflowStageName'] = '';
                continue;
            }

            $workflowState = $this->getCommitmentWorkflowState($fiscalYearId, $executionVersionId, $commitmentId);
            $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
            $currentStage = is_array($workflowState['CurrentStage'] ?? null) ? $workflowState['CurrentStage'] : null;
            $row['WorkflowStageName'] = (string) ($currentStage['WorkflowStageName'] ?? ($instance['CurrentStageCode'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    public function getCommitmentWorkflowState(int $fiscalYearId, int $executionVersionId, int $commitmentId): array
    {
        if (!$this->supportsWorkflowEngineFoundation() || $commitmentId <= 0) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $commitment = $this->getCommitment($fiscalYearId, $executionVersionId, $commitmentId);
        if ($commitment === null) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $this->ensureCommitmentWorkflowInstance($fiscalYearId, $executionVersionId, $commitment, 0);
        return $this->workflowEngine()->getWorkflowPanelState(
            'BE_COMMITMENT',
            'dbo.tblBeCommitment',
            (string) $commitmentId
        );
    }

    private function ensureCommitmentWorkflowInstance(int $fiscalYearId, int $executionVersionId, array $commitment, int $userId): ?array
    {
        if (!$this->supportsWorkflowEngineFoundation()) {
            return null;
        }

        $commitmentId = (int) ($commitment['CommitmentID'] ?? 0);
        if ($commitmentId <= 0) {
            return null;
        }

        $statusCode = strtoupper(trim((string) ($commitment['CommitmentStatusCode'] ?? 'DRAFT')));
        $initialStageCode = in_array($statusCode, ['APPROVED', 'CANCELLED'], true) ? $statusCode : 'DRAFT';
        $existing = $this->workflowEngine()->getWorkflowInstanceByRecord(
            'BE_COMMITMENT',
            'dbo.tblBeCommitment',
            (string) $commitmentId
        );
        $scopeDataObjectCode = $this->inferCommitmentScopeDataObjectCode($fiscalYearId, $executionVersionId, $commitmentId);
        if ($scopeDataObjectCode === null && $existing !== null) {
            $scopeDataObjectCode = $this->nullableString($existing['ScopeDataObjectCode'] ?? null);
        }

        return $this->workflowEngine()->ensureWorkflowInstance([
            'WorkflowAreaCode' => 'BE_COMMITMENT',
            'RecordTableName' => 'dbo.tblBeCommitment',
            'RecordID' => $commitmentId,
            'RecordKey' => (string) $commitmentId,
            'FiscalYearID' => $fiscalYearId,
            'VersionID' => $executionVersionId,
            'ScopeDataObjectCode' => $scopeDataObjectCode,
            'CurrentStageCode' => $initialStageCode,
            'CurrentStatusCode' => $statusCode,
            'WorkflowTitle' => (string) ($commitment['CommitmentTitle'] ?? ''),
            'WorkflowNote' => $this->nullableString($commitment['Notes'] ?? null),
            'UserID' => $userId,
        ]);
    }

    private function inferCommitmentScopeDataObjectCode(int $fiscalYearId, int $executionVersionId, int $commitmentId): ?string
    {
        if (!$this->supportsCommitmentFoundation() || $commitmentId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS DistinctScopeCount,
                   MIN(NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS ScopeDataObjectCode
            FROM dbo.tblBeCommitmentLine
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
              AND CommitmentID = :commitmentId
              AND ISNULL(ActiveFlag, 1) = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':commitmentId' => $commitmentId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $distinctCount = (int) ($row['DistinctScopeCount'] ?? 0);
        $scopeDataObjectCode = trim((string) ($row['ScopeDataObjectCode'] ?? ''));
        if ($distinctCount === 1 && $scopeDataObjectCode !== '') {
            return $scopeDataObjectCode;
        }

        $commitment = $this->getCommitment($fiscalYearId, $executionVersionId, $commitmentId);
        $linkedRieId = (int) ($commitment['RieID'] ?? 0);
        if ($linkedRieId > 0) {
            $rieWorkflowState = $this->getRieWorkflowState($fiscalYearId, $executionVersionId, $linkedRieId);
            $rieInstance = is_array($rieWorkflowState['Instance'] ?? null) ? $rieWorkflowState['Instance'] : null;
            $rieScopeDataObjectCode = trim((string) ($rieInstance['ScopeDataObjectCode'] ?? ''));
            if ($rieScopeDataObjectCode !== '') {
                return $rieScopeDataObjectCode;
            }
        }

        return null;
    }

    private function isCommitmentEditable(int $fiscalYearId, int $executionVersionId, array $commitment): bool
    {
        $statusCode = strtoupper(trim((string) ($commitment['CommitmentStatusCode'] ?? '')));
        if ($statusCode !== 'DRAFT') {
            return false;
        }

        if (!$this->supportsWorkflowEngineFoundation()) {
            return true;
        }

        $commitmentId = (int) ($commitment['CommitmentID'] ?? 0);
        if ($commitmentId <= 0) {
            return false;
        }

        $workflowState = $this->getCommitmentWorkflowState($fiscalYearId, $executionVersionId, $commitmentId);
        $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($instance === null) {
            return true;
        }

        return strtoupper(trim((string) ($instance['CurrentStageCode'] ?? ''))) === 'DRAFT';
    }

    public function getRieWorkflowState(int $fiscalYearId, int $executionVersionId, int $rieId): array
    {
        if (!$this->supportsWorkflowEngineFoundation() || $rieId <= 0) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $rie = $this->getRie($fiscalYearId, $executionVersionId, $rieId);
        if ($rie === null) {
            return [
                'Definition' => null,
                'Instance' => null,
                'CurrentStage' => null,
                'AllowedActions' => [],
                'Assignments' => [],
                'History' => [],
            ];
        }

        $this->ensureRieWorkflowInstance($fiscalYearId, $executionVersionId, $rie, 0);
        return $this->workflowEngine()->getWorkflowPanelState(
            'BE_RIE',
            'dbo.tblBeRie',
            (string) $rieId
        );
    }

    private function ensureRieWorkflowInstance(int $fiscalYearId, int $executionVersionId, array $rie, int $userId): ?array
    {
        if (!$this->supportsWorkflowEngineFoundation()) {
            return null;
        }

        $rieId = (int) ($rie['RieID'] ?? 0);
        if ($rieId <= 0) {
            return null;
        }

        $existing = $this->workflowEngine()->getWorkflowInstanceByRecord(
            'BE_RIE',
            'dbo.tblBeRie',
            (string) $rieId
        );
        if ($existing !== null) {
            return $existing;
        }

        return $this->workflowEngine()->ensureWorkflowInstance([
            'WorkflowAreaCode' => 'BE_RIE',
            'RecordTableName' => 'dbo.tblBeRie',
            'RecordID' => $rieId,
            'RecordKey' => (string) $rieId,
            'FiscalYearID' => $fiscalYearId,
            'VersionID' => $executionVersionId,
            'ScopeDataObjectCode' => $this->inferRieScopeDataObjectCode($fiscalYearId, $executionVersionId, $rieId),
            'WorkflowTitle' => (string) ($rie['RieTitle'] ?? ''),
            'WorkflowNote' => $this->nullableString($rie['Notes'] ?? null),
            'UserID' => $userId,
        ]);
    }

    private function inferRieScopeDataObjectCode(int $fiscalYearId, int $executionVersionId, int $rieId): ?string
    {
        if (!$this->supportsRieFoundation() || $rieId <= 0) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT COUNT(DISTINCT NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS DistinctScopeCount,
                   MIN(NULLIF(LTRIM(RTRIM(DataObjectCode)), '')) AS ScopeDataObjectCode
            FROM dbo.tblBeRieLine
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
              AND RieID = :rieId
              AND ISNULL(ActiveFlag, 1) = 1
        ");
        $stmt->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':rieId' => $rieId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $distinctCount = (int) ($row['DistinctScopeCount'] ?? 0);
        $scopeDataObjectCode = trim((string) ($row['ScopeDataObjectCode'] ?? ''));
        if ($distinctCount === 1 && $scopeDataObjectCode !== '') {
            return $scopeDataObjectCode;
        }

        return null;
    }

    private function isRieEditable(int $fiscalYearId, int $executionVersionId, array $rie): bool
    {
        $statusCode = strtoupper(trim((string) ($rie['RieStatusCode'] ?? '')));
        if ($statusCode !== 'DRAFT') {
            return false;
        }

        if (!$this->supportsWorkflowEngineFoundation()) {
            return true;
        }

        $rieId = (int) ($rie['RieID'] ?? 0);
        if ($rieId <= 0) {
            return false;
        }

        $workflowState = $this->getRieWorkflowState($fiscalYearId, $executionVersionId, $rieId);
        $instance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($instance === null) {
            return true;
        }

        return strtoupper(trim((string) ($instance['CurrentStageCode'] ?? ''))) === 'DRAFT';
    }

    public function listCommitments(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsCommitmentFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $rieSelect = $this->supportsRieFoundation()
            ? "r.RieNo,
                   r.RieTitle,
                   r.RieStatusCode,"
            : "CAST(NULL AS NVARCHAR(50)) AS RieNo,
                   CAST(NULL AS NVARCHAR(200)) AS RieTitle,
                   CAST(NULL AS NVARCHAR(20)) AS RieStatusCode,";
        $rieJoin = $this->supportsRieFoundation()
            ? "LEFT JOIN dbo.tblBeRie r
                ON r.RieID = c.RieID"
            : "";

        $sql = "
            SELECT c.*,
                   {$rieSelect}
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalCommitmentAmount, 0) AS TotalCommitmentAmount
            FROM dbo.tblBeCommitment c
            {$rieJoin}
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(cl.CommitmentAmount, 0)), 0) AS TotalCommitmentAmount
                FROM dbo.tblBeCommitmentLine cl
                WHERE cl.CommitmentID = c.CommitmentID
                  AND ISNULL(cl.ActiveFlag, 1) = 1
            ) line
            WHERE c.FiscalYearID = :fy
              AND c.ExecutionVersionID = :executionVersionId
              AND ISNULL(c.ActiveFlag, 1) = 1
            ORDER BY c.CreatedDate DESC, c.CommitmentID DESC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getCommitment(int $fiscalYearId, int $executionVersionId, int $commitmentId): ?array
    {
        if (!$this->supportsCommitmentFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $commitmentId <= 0) {
            return null;
        }

        $rieSelect = $this->supportsRieFoundation()
            ? "r.RieNo,
                   r.RieTitle,
                   r.RieStatusCode,"
            : "CAST(NULL AS NVARCHAR(50)) AS RieNo,
                   CAST(NULL AS NVARCHAR(200)) AS RieTitle,
                   CAST(NULL AS NVARCHAR(20)) AS RieStatusCode,";
        $rieJoin = $this->supportsRieFoundation()
            ? "LEFT JOIN dbo.tblBeRie r
                ON r.RieID = c.RieID"
            : "";

        $sql = "
            SELECT c.*,
                   {$rieSelect}
                   COALESCE(line.LineCount, 0) AS LineCount,
                   COALESCE(line.TotalCommitmentAmount, 0) AS TotalCommitmentAmount
            FROM dbo.tblBeCommitment c
            {$rieJoin}
            OUTER APPLY (
                SELECT COUNT(*) AS LineCount,
                       COALESCE(SUM(COALESCE(cl.CommitmentAmount, 0)), 0) AS TotalCommitmentAmount
                FROM dbo.tblBeCommitmentLine cl
                WHERE cl.CommitmentID = c.CommitmentID
                  AND ISNULL(cl.ActiveFlag, 1) = 1
            ) line
            WHERE c.FiscalYearID = :fy
              AND c.ExecutionVersionID = :executionVersionId
              AND c.CommitmentID = :commitmentId
              AND ISNULL(c.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':commitmentId' => $commitmentId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createCommitment(array $data): int
    {
        if (!$this->supportsCommitmentFoundation()) {
            throw new \RuntimeException('Budget Execution Commitment tables are not installed. Run create_budget_execution_commitments_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $title = trim((string) ($data['CommitmentTitle'] ?? ''));
        $commitmentDate = $this->normalizeDate($data['CommitmentDate'] ?? null);
        $effectiveDate = $this->normalizeDate($data['EffectiveDate'] ?? null);
        $rieId = (int) ($data['RieID'] ?? 0);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $scopeDataObjectCode = $this->nullableString($data['ScopeDataObjectCode'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($fiscalYearId <= 0 || $executionVersionId <= 0) {
            throw new \RuntimeException('Execution context is required for the commitment.');
        }
        if ($title === '') {
            throw new \RuntimeException('Commitment title is required.');
        }

        $executionVersion = $this->getVersion($fiscalYearId, $executionVersionId);
        if ($executionVersion === null || strtoupper(trim((string) ($executionVersion['VersionTypeCode'] ?? ''))) !== 'EXECUTION') {
            throw new \RuntimeException('Commitments can only be created inside an execution version.');
        }
        if ($rieId > 0) {
            if (!$this->supportsRieFoundation()) {
                throw new \RuntimeException('RIE linkage is not available until the RIE tables are installed.');
            }
            $rie = $this->getRie($fiscalYearId, $executionVersionId, $rieId);
            if ($rie === null) {
                throw new \RuntimeException('Selected RIE was not found.');
            }
            if (strtoupper(trim((string) ($rie['RieStatusCode'] ?? ''))) !== 'APPROVED') {
                throw new \RuntimeException('Only approved RIEs can be linked to a commitment.');
            }
        }

        $commitmentNo = $this->nextCommitmentNumber($fiscalYearId, $executionVersionId);

        $sql = "
            INSERT INTO dbo.tblBeCommitment (
                FiscalYearID,
                ExecutionVersionID,
                RieID,
                CommitmentNo,
                CommitmentTitle,
                CommitmentStatusCode,
                CommitmentDate,
                EffectiveDate,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.CommitmentID
            VALUES (
                :fy,
                :executionVersionId,
                :rieId,
                :commitmentNo,
                :commitmentTitle,
                N'DRAFT',
                :commitmentDate,
                :effectiveDate,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':rieId' => $rieId > 0 ? $rieId : null,
            ':commitmentNo' => $commitmentNo,
            ':commitmentTitle' => $title,
            ':commitmentDate' => $commitmentDate ?? date('Y-m-d'),
            ':effectiveDate' => $effectiveDate,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        $commitmentId = (int) ($st->fetchColumn() ?: 0);
        if ($commitmentId <= 0) {
            throw new \RuntimeException('Commitment could not be created.');
        }

        if ($this->supportsWorkflowEngineFoundation()) {
            $this->workflowEngine()->ensureWorkflowInstance([
                'WorkflowAreaCode' => 'BE_COMMITMENT',
                'RecordTableName' => 'dbo.tblBeCommitment',
                'RecordID' => $commitmentId,
                'RecordKey' => (string) $commitmentId,
                'FiscalYearID' => $fiscalYearId,
                'VersionID' => $executionVersionId,
                'ScopeDataObjectCode' => $scopeDataObjectCode,
                'WorkflowTitle' => $title,
                'WorkflowNote' => $notes,
                'UserID' => $userId,
            ]);
        }

        return $commitmentId;
    }

    public function listCommitmentLines(int $fiscalYearId, int $executionVersionId, int $commitmentId): array
    {
        if (!$this->supportsCommitmentFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0 || $commitmentId <= 0) {
            return [];
        }

        $sql = "
            SELECT cl.*,
                   ob.OpeningAmount,
                   ob.CurrentAuthorizedAmount
            FROM dbo.tblBeCommitmentLine cl
            INNER JOIN dbo.tblBeExecutionOpeningBalance ob
                ON ob.ExecutionOpeningBalanceID = cl.ExecutionOpeningBalanceID
            WHERE cl.FiscalYearID = :fy
              AND cl.ExecutionVersionID = :executionVersionId
              AND cl.CommitmentID = :commitmentId
              AND ISNULL(cl.ActiveFlag, 1) = 1
            ORDER BY cl.CommitmentLineID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':commitmentId' => $commitmentId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listCommitmentBalanceCandidates(int $fiscalYearId, int $executionVersionId): array
    {
        if (!$this->supportsExecutionFoundation() || !$this->supportsWarrantFoundation() || !$this->supportsCommitmentFoundation() || $fiscalYearId <= 0 || $executionVersionId <= 0) {
            return [];
        }

        $sql = "
            SELECT b.*,
                   COALESCE(rel.ApprovedReleasedAmountTotal, 0) AS ApprovedReleasedAmountTotal,
                   COALESCE(res.PlannedReservedAmountTotal, 0) AS PlannedReservedAmountTotal,
                   COALESCE(com.PlannedCommitmentAmountTotal, 0) AS PlannedCommitmentAmountTotal,
                   COALESCE(rel.ApprovedReleasedAmountTotal, 0) - COALESCE(res.PlannedReservedAmountTotal, 0) - COALESCE(com.PlannedCommitmentAmountTotal, 0) AS AvailableCommitmentCapacity
            FROM dbo.tblBeExecutionOpeningBalance b
            LEFT JOIN (
                SELECT wl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(wl.ReleaseAmount, 0)) AS ApprovedReleasedAmountTotal
                FROM dbo.tblBeWarrantLine wl
                INNER JOIN dbo.tblBeWarrant w
                    ON w.WarrantID = wl.WarrantID
                WHERE ISNULL(wl.ActiveFlag, 1) = 1
                  AND ISNULL(w.ActiveFlag, 1) = 1
                  AND w.WarrantStatusCode = N'APPROVED'
                GROUP BY wl.ExecutionOpeningBalanceID
            ) rel
                ON rel.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID
            LEFT JOIN (
                SELECT rl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(rl.ReservationAmount, 0)) AS PlannedReservedAmountTotal
                FROM dbo.tblBeReservationLine rl
                INNER JOIN dbo.tblBeReservation r
                    ON r.ReservationID = rl.ReservationID
                WHERE ISNULL(rl.ActiveFlag, 1) = 1
                  AND ISNULL(r.ActiveFlag, 1) = 1
                  AND r.ReservationStatusCode <> N'CANCELLED'
                GROUP BY rl.ExecutionOpeningBalanceID
            ) res
                ON res.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID
            LEFT JOIN (
                SELECT cl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(cl.CommitmentAmount, 0)) AS PlannedCommitmentAmountTotal
                FROM dbo.tblBeCommitmentLine cl
                INNER JOIN dbo.tblBeCommitment c
                    ON c.CommitmentID = cl.CommitmentID
                WHERE ISNULL(cl.ActiveFlag, 1) = 1
                  AND ISNULL(c.ActiveFlag, 1) = 1
                  AND c.CommitmentStatusCode <> N'CANCELLED'
                GROUP BY cl.ExecutionOpeningBalanceID
            ) com
                ON com.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID
            WHERE b.FiscalYearID = :fy
              AND b.ExecutionVersionID = :executionVersionId
              AND ISNULL(b.ActiveFlag, 1) = 1
            ORDER BY b.DataObjectCode ASC,
                     b.SectorID ASC,
                     b.ProgramID ASC,
                     b.ProjectID ASC,
                     b.SourceSubmissionLineID ASC,
                     b.ExecutionOpeningBalanceID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function addCommitmentLine(array $data): int
    {
        if (!$this->supportsCommitmentFoundation()) {
            throw new \RuntimeException('Budget Execution Commitment tables are not installed. Run create_budget_execution_commitments_v1.sql first.');
        }

        $fiscalYearId = (int) ($data['FiscalYearID'] ?? 0);
        $executionVersionId = (int) ($data['ExecutionVersionID'] ?? 0);
        $commitmentId = (int) ($data['CommitmentID'] ?? 0);
        $executionOpeningBalanceId = (int) ($data['ExecutionOpeningBalanceID'] ?? 0);
        $commitmentAmount = (float) ($data['CommitmentAmount'] ?? 0);
        $notes = $this->nullableString($data['Notes'] ?? null);
        $userId = (int) ($data['UserID'] ?? 1);

        if ($commitmentAmount <= 0) {
            throw new \RuntimeException('Commitment amount must be greater than zero.');
        }

        $commitment = $this->getCommitment($fiscalYearId, $executionVersionId, $commitmentId);
        if ($commitment === null) {
            throw new \RuntimeException('Selected commitment was not found.');
        }
        if (!$this->isCommitmentEditable($fiscalYearId, $executionVersionId, $commitment)) {
            throw new \RuntimeException('Lines can only be added to a draft commitment.');
        }

        $openingBalance = $this->getOpeningBalanceRow($fiscalYearId, $executionVersionId, $executionOpeningBalanceId);
        if ($openingBalance === null) {
            throw new \RuntimeException('Selected execution balance line was not found.');
        }

        $approvedReleased = $this->getApprovedReleasedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $plannedReserved = $this->getPlannedReservedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $plannedCommitted = $this->getPlannedCommittedAmountAgainstOpeningBalance($executionOpeningBalanceId);
        $availableCommitmentCapacity = $approvedReleased - $plannedReserved - $plannedCommitted;

        if ($commitmentAmount > $availableCommitmentCapacity) {
            throw new \RuntimeException(
                'Commitment amount exceeds the available released balance for the selected line. Available: '
                . number_format(max(0, $availableCommitmentCapacity), 2)
            );
        }
        $linkedRieId = (int) ($commitment['RieID'] ?? 0);
        if ($linkedRieId > 0) {
            $availableRieAmount = $this->getAvailableApprovedRieAmount($linkedRieId);
            if ($commitmentAmount > $availableRieAmount) {
                throw new \RuntimeException(
                    'Commitment amount exceeds the remaining approved RIE amount. Available against RIE: '
                    . number_format(max(0, $availableRieAmount), 2)
                );
            }
        }

        $sql = "
            INSERT INTO dbo.tblBeCommitmentLine (
                CommitmentID,
                FiscalYearID,
                ExecutionVersionID,
                ExecutionOpeningBalanceID,
                DataObjectCode,
                OrgUnitID,
                SectorID,
                ProgramID,
                SubProgramID,
                ProjectID,
                ActivityID,
                FundingTypeID,
                FundingSourceID,
                EconomicItemID,
                BidTitle,
                CommitmentAmount,
                Notes,
                ActiveFlag,
                CreatedBy,
                CreatedDate
            )
            OUTPUT INSERTED.CommitmentLineID
            VALUES (
                :commitmentId,
                :fy,
                :executionVersionId,
                :executionOpeningBalanceId,
                :dataObjectCode,
                :orgUnitId,
                :sectorId,
                :programId,
                :subProgramId,
                :projectId,
                :activityId,
                :fundingTypeId,
                :fundingSourceId,
                :economicItemId,
                :bidTitle,
                :commitmentAmount,
                :notes,
                1,
                :userId,
                SYSDATETIME()
            )
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':commitmentId' => $commitmentId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
            ':dataObjectCode' => (string) ($openingBalance['DataObjectCode'] ?? ''),
            ':orgUnitId' => $openingBalance['OrgUnitID'] ?? null,
            ':sectorId' => $openingBalance['SectorID'] ?? null,
            ':programId' => $openingBalance['ProgramID'] ?? null,
            ':subProgramId' => $openingBalance['SubProgramID'] ?? null,
            ':projectId' => $openingBalance['ProjectID'] ?? null,
            ':activityId' => $openingBalance['ActivityID'] ?? null,
            ':fundingTypeId' => $openingBalance['FundingTypeID'] ?? null,
            ':fundingSourceId' => $openingBalance['FundingSourceID'] ?? null,
            ':economicItemId' => $openingBalance['EconomicItemID'] ?? null,
            ':bidTitle' => $this->nullableString($openingBalance['BidTitle'] ?? null),
            ':commitmentAmount' => $commitmentAmount,
            ':notes' => $notes,
            ':userId' => $userId > 0 ? $userId : 1,
        ]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    public function deleteCommitmentLine(int $fiscalYearId, int $executionVersionId, int $commitmentLineId, int $userId): void
    {
        if (!$this->supportsCommitmentFoundation() || $commitmentLineId <= 0) {
            throw new \RuntimeException('Commitment line could not be removed.');
        }

        $sql = "
            SELECT TOP 1 cl.CommitmentLineID,
                   cl.CommitmentID,
                   c.CommitmentStatusCode
            FROM dbo.tblBeCommitmentLine cl
            INNER JOIN dbo.tblBeCommitment c
                ON c.CommitmentID = cl.CommitmentID
            WHERE cl.CommitmentLineID = :commitmentLineId
              AND cl.FiscalYearID = :fy
              AND cl.ExecutionVersionID = :executionVersionId
              AND ISNULL(cl.ActiveFlag, 1) = 1
              AND ISNULL(c.ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':commitmentLineId' => $commitmentLineId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!is_array($row)) {
            throw new \RuntimeException('Commitment line was not found.');
        }
        $commitment = $this->getCommitment($fiscalYearId, $executionVersionId, (int) ($row['CommitmentID'] ?? 0));
        if ($commitment === null || !$this->isCommitmentEditable($fiscalYearId, $executionVersionId, $commitment)) {
            throw new \RuntimeException('Only draft commitment lines can be removed.');
        }

        $deleteSql = "
            UPDATE dbo.tblBeCommitmentLine
            SET ActiveFlag = 0,
                UpdatedBy = :userId,
                UpdatedDate = SYSDATETIME()
            WHERE CommitmentLineID = :commitmentLineId
        ";
        $deleteStmt = $this->conn->prepare($deleteSql);
        $deleteStmt->execute([
            ':userId' => $userId > 0 ? $userId : 1,
            ':commitmentLineId' => $commitmentLineId,
        ]);
    }

    public function approveCommitment(int $fiscalYearId, int $executionVersionId, int $commitmentId, int $userId): void
    {
        $commitment = $this->getCommitment($fiscalYearId, $executionVersionId, $commitmentId);
        if ($commitment === null) {
            throw new \RuntimeException('Commitment was not found.');
        }
        if (strtoupper(trim((string) ($commitment['CommitmentStatusCode'] ?? ''))) !== 'DRAFT') {
            throw new \RuntimeException('Only draft commitments can be approved.');
        }
        if ((int) ($commitment['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('A commitment must contain at least one line before approval.');
        }

        $workflowState = $this->getCommitmentWorkflowState($fiscalYearId, $executionVersionId, $commitmentId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        $workflowStageCode = strtoupper(trim((string) ($workflowInstance['CurrentStageCode'] ?? '')));
        if ($workflowInstance !== null && $workflowStageCode !== '' && $workflowStageCode !== 'FINAL_APPROVAL') {
            throw new \RuntimeException('This commitment must reach Final Approval stage before it can be approved.');
        }

        $lines = $this->listCommitmentLines($fiscalYearId, $executionVersionId, $commitmentId);
        foreach ($lines as $line) {
            $openingBalanceId = (int) ($line['ExecutionOpeningBalanceID'] ?? 0);
            $approvedReleased = $this->getApprovedReleasedAmountAgainstOpeningBalance($openingBalanceId);
            $plannedReserved = $this->getPlannedReservedAmountAgainstOpeningBalance($openingBalanceId);
            $otherPlannedCommitted = $this->getPlannedCommittedAmountAgainstOpeningBalance($openingBalanceId, $commitmentId);
            $lineAmount = (float) ($line['CommitmentAmount'] ?? 0);
            if ($lineAmount > ($approvedReleased - $plannedReserved - $otherPlannedCommitted)) {
                throw new \RuntimeException('One or more commitment lines exceed the available released balance at approval time.');
            }
        }
        $linkedRieId = (int) ($commitment['RieID'] ?? 0);
        if ($linkedRieId > 0) {
            $currentCommitmentAmount = (float) ($commitment['TotalCommitmentAmount'] ?? 0);
            $availableRieAmount = $this->getAvailableApprovedRieAmount($linkedRieId, $commitmentId);
            if ($currentCommitmentAmount > $availableRieAmount) {
                throw new \RuntimeException('This commitment exceeds the remaining amount available under the linked approved RIE.');
            }
        }

        $this->conn->beginTransaction();
        try {
            $sql = "
                UPDATE dbo.tblBeCommitment
                SET CommitmentStatusCode = N'APPROVED',
                    ApprovedBy = :approvedBy,
                    ApprovedDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE CommitmentID = :commitmentId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':approvedBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':commitmentId' => $commitmentId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'APPROVE',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function cancelCommitment(int $fiscalYearId, int $executionVersionId, int $commitmentId, int $userId): void
    {
        $commitment = $this->getCommitment($fiscalYearId, $executionVersionId, $commitmentId);
        if ($commitment === null) {
            throw new \RuntimeException('Commitment was not found.');
        }
        if (strtoupper(trim((string) ($commitment['CommitmentStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('This commitment has already been cancelled.');
        }

        $workflowState = $this->getCommitmentWorkflowState($fiscalYearId, $executionVersionId, $commitmentId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;

        $this->conn->beginTransaction();
        try {
            $sql = "
                UPDATE dbo.tblBeCommitment
                SET CommitmentStatusCode = N'CANCELLED',
                    CancelledBy = :cancelledBy,
                    CancelledDate = SYSDATETIME(),
                    UpdatedBy = :updatedBy,
                    UpdatedDate = SYSDATETIME()
                WHERE CommitmentID = :commitmentId
            ";
            $st = $this->conn->prepare($sql);
            $st->execute([
                ':cancelledBy' => $userId > 0 ? $userId : 1,
                ':updatedBy' => $userId > 0 ? $userId : 1,
                ':commitmentId' => $commitmentId,
            ]);

            if ($workflowInstance !== null) {
                $this->workflowEngine()->transitionWorkflowInstance(
                    (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
                    'CANCEL',
                    $userId,
                    null
                );
            }

            $this->conn->commit();
        } catch (\Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function transitionCommitmentWorkflow(int $fiscalYearId, int $executionVersionId, int $commitmentId, string $workflowActionCode, int $userId, ?string $note = null): array
    {
        $commitment = $this->getCommitment($fiscalYearId, $executionVersionId, $commitmentId);
        if ($commitment === null) {
            throw new \RuntimeException('Commitment was not found.');
        }
        if (!$this->supportsWorkflowEngineFoundation()) {
            throw new \RuntimeException('Shared workflow engine foundation is not installed.');
        }
        if (strtoupper(trim((string) ($commitment['CommitmentStatusCode'] ?? ''))) === 'CANCELLED') {
            throw new \RuntimeException('Cancelled commitments cannot continue through workflow.');
        }

        $actionCode = strtoupper(trim($workflowActionCode));
        if (!in_array($actionCode, ['SUBMIT', 'FORWARD', 'RETURN'], true)) {
            throw new \RuntimeException('Workflow action is not supported for commitments.');
        }
        if ((int) ($commitment['LineCount'] ?? 0) <= 0) {
            throw new \RuntimeException('A commitment must contain at least one line before workflow submission.');
        }

        $workflowState = $this->getCommitmentWorkflowState($fiscalYearId, $executionVersionId, $commitmentId);
        $workflowInstance = is_array($workflowState['Instance'] ?? null) ? $workflowState['Instance'] : null;
        if ($workflowInstance === null) {
            throw new \RuntimeException('Workflow instance could not be resolved for this commitment.');
        }

        $updatedInstance = $this->workflowEngine()->transitionWorkflowInstance(
            (int) ($workflowInstance['WorkflowInstanceID'] ?? 0),
            $actionCode,
            $userId,
            $note
        );

        $statusSql = "
            UPDATE dbo.tblBeCommitment
            SET UpdatedBy = :updatedBy,
                UpdatedDate = SYSDATETIME()
            WHERE CommitmentID = :commitmentId
        ";
        $statusStmt = $this->conn->prepare($statusSql);
        $statusStmt->execute([
            ':updatedBy' => $userId > 0 ? $userId : 1,
            ':commitmentId' => $commitmentId,
        ]);

        return $updatedInstance;
    }

    private function nextVersionId(int $fiscalYearId): int
    {
        $sql = "
            SELECT ISNULL(MAX(VersionID), 0) + 1
            FROM dbo.tblVersions
            WHERE FiscalYearID = :fy
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([':fy' => $fiscalYearId]);
        return (int) ($st->fetchColumn() ?: 1);
    }

    private function nextWarrantNumber(int $fiscalYearId, int $executionVersionId): string
    {
        $sql = "
            SELECT ISNULL(MAX(WarrantID), 0) + 1
            FROM dbo.tblBeWarrant
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $sequence = (int) ($st->fetchColumn() ?: 1);
        return 'WAR-' . $fiscalYearId . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function nextSupplementaryNumber(int $fiscalYearId, int $executionVersionId): string
    {
        $sql = "
            SELECT ISNULL(MAX(SupplementaryBudgetID), 0) + 1
            FROM dbo.tblBeSupplementaryBudget
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $sequence = (int) ($st->fetchColumn() ?: 1);
        return 'SUP-' . $fiscalYearId . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function nextReservationNumber(int $fiscalYearId, int $executionVersionId): string
    {
        $sql = "
            SELECT ISNULL(MAX(ReservationID), 0) + 1
            FROM dbo.tblBeReservation
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $sequence = (int) ($st->fetchColumn() ?: 1);
        return 'RES-' . $fiscalYearId . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function nextCommitmentNumber(int $fiscalYearId, int $executionVersionId): string
    {
        $sql = "
            SELECT ISNULL(MAX(CommitmentID), 0) + 1
            FROM dbo.tblBeCommitment
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $sequence = (int) ($st->fetchColumn() ?: 1);
        return 'COM-' . $fiscalYearId . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function nextRieNumber(int $fiscalYearId, int $executionVersionId): string
    {
        $sql = "
            SELECT ISNULL(MAX(RieID), 0) + 1
            FROM dbo.tblBeRie
            WHERE FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $sequence = (int) ($st->fetchColumn() ?: 1);
        return 'RIE-' . $fiscalYearId . '-' . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function listBudgetReductionScopeRows(int $fiscalYearId, int $executionVersionId, array $criteria): array
    {
        $releaseJoin = $this->supportsWarrantFoundation()
            ? "
            LEFT JOIN (
                SELECT wl.ExecutionOpeningBalanceID,
                       SUM(COALESCE(wl.ReleaseAmount, 0)) AS PlannedReleaseAmountTotal
                FROM dbo.tblBeWarrantLine wl
                INNER JOIN dbo.tblBeWarrant w
                    ON w.WarrantID = wl.WarrantID
                WHERE ISNULL(wl.ActiveFlag, 1) = 1
                  AND ISNULL(w.ActiveFlag, 1) = 1
                  AND w.WarrantStatusCode <> N'CANCELLED'
                GROUP BY wl.ExecutionOpeningBalanceID
            ) rel
                ON rel.ExecutionOpeningBalanceID = b.ExecutionOpeningBalanceID"
            : "
            LEFT JOIN (
                SELECT CAST(NULL AS INT) AS ExecutionOpeningBalanceID,
                       CAST(0 AS DECIMAL(19,6)) AS PlannedReleaseAmountTotal
            ) rel
                ON 1 = 0";

        $sql = "
            SELECT b.*,
                   COALESCE(rel.PlannedReleaseAmountTotal, 0) AS PlannedReleaseAmountTotal
            FROM dbo.tblBeExecutionOpeningBalance b
            {$releaseJoin}
            WHERE b.FiscalYearID = :fy
              AND b.ExecutionVersionID = :executionVersionId
              AND ISNULL(b.ActiveFlag, 1) = 1
        ";
        $params = [
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ];

        $effectiveScopeCode = trim((string) ($criteria['SessionScopeDataObjectCode'] ?? ''));
        $selectedDataObjectCode = trim((string) ($criteria['DataObjectCode'] ?? ''));
        if ($selectedDataObjectCode !== '') {
            $effectiveScopeCode = $selectedDataObjectCode;
        }

        $scopeCodes = $this->listDescendantDataObjectCodes($fiscalYearId, $effectiveScopeCode);
        if (!empty($scopeCodes)) {
            $in = [];
            foreach ($scopeCodes as $index => $scopeCode) {
                $placeholder = ':dataObjectScope' . $index;
                $in[] = $placeholder;
                $params[$placeholder] = $scopeCode;
            }
            $sql .= "
              AND b.DataObjectCode IN (" . implode(', ', $in) . ")";
        }

        $definitions = $this->getBudgetReductionDimensionDefinitions();
        $mappedDimensions = [];
        foreach ($this->listBudgetReductionMappedDimensions($fiscalYearId) as $dimensionRow) {
            $mappedDimensions[strtoupper(trim((string) ($dimensionRow['Code'] ?? '')))] = $dimensionRow;
        }
        $mappedFilters = is_array($criteria['DimensionFilters'] ?? null) ? $criteria['DimensionFilters'] : [];
        foreach ($mappedFilters as $dimensionCode => $segmentCode) {
            $dimensionCode = strtoupper(trim((string) $dimensionCode));
            $segmentCode = trim((string) $segmentCode);
            if ($dimensionCode === '' || $segmentCode === '' || !isset($definitions[$dimensionCode]) || !isset($mappedDimensions[$dimensionCode])) {
                continue;
            }

            $segmentNo = (int) ($mappedDimensions[$dimensionCode]['SegmentNo'] ?? 0);
            if ($segmentNo <= 0) {
                continue;
            }

            $segmentNoPlaceholder = ':mappedSegmentNo_' . $dimensionCode;
            $segmentCodePlaceholder = ':mappedSegmentCode_' . $dimensionCode;
            $params[$segmentNoPlaceholder] = $segmentNo;
            $params[$segmentCodePlaceholder] = $segmentCode;
            $sql .= $this->buildBudgetReductionDimensionFilterSql($dimensionCode, $segmentNoPlaceholder, $segmentCodePlaceholder);
        }

        $sql .= "
            ORDER BY b.DataObjectCode ASC,
                     b.SectorID ASC,
                     b.ProgramID ASC,
                     b.ProjectID ASC,
                     b.SourceSubmissionLineID ASC,
                     b.ExecutionOpeningBalanceID ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    private function hasBudgetReductionScope(array $criteria): bool
    {
        if (trim((string) ($criteria['DataObjectCode'] ?? '')) !== '') {
            return true;
        }

        $mappedFilters = is_array($criteria['DimensionFilters'] ?? null) ? $criteria['DimensionFilters'] : [];
        foreach ($mappedFilters as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }
        return false;
    }

    private function buildBudgetReductionScopeSummary(array $criteria, string $method, float $reductionValue): string
    {
        $parts = [];
        if (trim((string) ($criteria['DataObjectCode'] ?? '')) !== '') {
            $parts[] = 'DataObjectCode=' . trim((string) $criteria['DataObjectCode']);
        }

        $definitions = $this->getBudgetReductionDimensionDefinitions();
        $mappedFilters = is_array($criteria['DimensionFilters'] ?? null) ? $criteria['DimensionFilters'] : [];
        foreach ($mappedFilters as $dimensionCode => $value) {
            $dimensionCode = strtoupper(trim((string) $dimensionCode));
            $value = trim((string) $value);
            if ($value === '' || !isset($definitions[$dimensionCode])) {
                continue;
            }
            $parts[] = $definitions[$dimensionCode]['label'] . '=' . $value;
        }

        $methodLabel = $method === 'PERCENTAGE' ? 'percentage' : 'fixed per line';
        $valueLabel = $method === 'PERCENTAGE'
            ? number_format($reductionValue, 2) . '%'
            : number_format($reductionValue, 2);

        $summary = 'Generated by Budget Reduction Wizard';
        $summary .= '; method=' . $methodLabel;
        $summary .= '; value=' . $valueLabel;
        if (!empty($parts)) {
            $summary .= '; scope=' . implode(', ', $parts);
        }

        return $summary;
    }

    private function buildBudgetReductionDimensionFilterSql(string $dimensionCode, string $segmentNoPlaceholder, string $segmentCodePlaceholder): string
    {
        return match ($dimensionCode) {
            'SECTOR' => "
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblSbSector dim
                    WHERE dim.SectorID = b.SectorID
                      AND ISNULL(dim.ActiveFlag, 1) = 1
                      AND dim.SourceSegmentNo = {$segmentNoPlaceholder}
                      AND dim.SourceSegmentCode = {$segmentCodePlaceholder}
                )",
            'PROGRAM' => "
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblSbProgram dim
                    WHERE dim.ProgramID = b.ProgramID
                      AND ISNULL(dim.ActiveFlag, 1) = 1
                      AND dim.SourceSegmentNo = {$segmentNoPlaceholder}
                      AND dim.SourceSegmentCode = {$segmentCodePlaceholder}
                )",
            'SUBPROGRAM' => "
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblSbSubProgram dim
                    WHERE dim.SubProgramID = b.SubProgramID
                      AND ISNULL(dim.ActiveFlag, 1) = 1
                      AND dim.SourceSegmentNo = {$segmentNoPlaceholder}
                      AND dim.SourceSegmentCode = {$segmentCodePlaceholder}
                )",
            'PROJECT' => "
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblSbProjectSourceMap dim
                    WHERE dim.ProjectID = b.ProjectID
                      AND ISNULL(dim.ActiveFlag, 1) = 1
                      AND dim.SourceSegmentNo = {$segmentNoPlaceholder}
                      AND dim.SourceSegmentCode = {$segmentCodePlaceholder}
                )",
            'ACTIVITY' => "
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblSbActivity dim
                    WHERE dim.ActivityID = b.ActivityID
                      AND ISNULL(dim.ActiveFlag, 1) = 1
                      AND dim.SourceSegmentNo = {$segmentNoPlaceholder}
                      AND dim.SourceSegmentCode = {$segmentCodePlaceholder}
                )",
            'ECONOMIC' => "
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblSbEconomicItem dim
                    WHERE dim.EconomicItemID = b.EconomicItemID
                      AND ISNULL(dim.ActiveFlag, 1) = 1
                      AND dim.SourceSegmentNo = {$segmentNoPlaceholder}
                      AND dim.SourceSegmentCode = {$segmentCodePlaceholder}
                )",
            'FUNDING_TYPE' => "
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblSbFundingType dim
                    WHERE dim.FundingTypeID = b.FundingTypeID
                      AND ISNULL(dim.ActiveFlag, 1) = 1
                      AND dim.SourceSegmentNo = {$segmentNoPlaceholder}
                      AND dim.SourceSegmentCode = {$segmentCodePlaceholder}
                )",
            'FUNDING_SOURCE' => "
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblSbFundingSource dim
                    WHERE dim.FundingSourceID = b.FundingSourceID
                      AND ISNULL(dim.ActiveFlag, 1) = 1
                      AND dim.SourceSegmentNo = {$segmentNoPlaceholder}
                      AND dim.SourceSegmentCode = {$segmentCodePlaceholder}
                )",
            default => '',
        };
    }

    private function listDescendantDataObjectCodes(int $fiscalYearId, string $rootCode): array
    {
        if ($fiscalYearId <= 0 || !$this->tableExists('dbo.tblDataObjectCodes')) {
            return [];
        }

        $rootCode = trim($rootCode);
        if ($rootCode === '') {
            return [];
        }

        $sql = "
            WITH ScopeTree AS (
                SELECT DataObjectCode
                FROM dbo.tblDataObjectCodes
                WHERE FiscalYearID = :fy
                  AND DataObjectCode = :rootCode
                UNION ALL
                SELECT c.DataObjectCode
                FROM dbo.tblDataObjectCodes c
                INNER JOIN ScopeTree st
                    ON c.DataObjectCodeParent = st.DataObjectCode
                WHERE c.FiscalYearID = :fy
            )
            SELECT DataObjectCode
            FROM ScopeTree
            ORDER BY DataObjectCode ASC
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':fy' => $fiscalYearId,
            ':rootCode' => $rootCode,
        ]);

        return array_values(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            $st->fetchAll(\PDO::FETCH_COLUMN) ?: []
        )));
    }

    private function getBudgetReductionDimensionDefinitions(): array
    {
        return [
            'SECTOR' => ['label' => 'Sector'],
            'PROGRAM' => ['label' => 'Program'],
            'SUBPROGRAM' => ['label' => 'SubProgram'],
            'PROJECT' => ['label' => 'Project'],
            'ACTIVITY' => ['label' => 'Activity'],
            'ECONOMIC' => ['label' => 'Economic Item'],
            'FUNDING_TYPE' => ['label' => 'Funding Type'],
            'FUNDING_SOURCE' => ['label' => 'Funding Source'],
        ];
    }

    private function getOpeningBalanceRow(int $fiscalYearId, int $executionVersionId, int $executionOpeningBalanceId): ?array
    {
        if ($executionOpeningBalanceId <= 0) {
            return null;
        }

        $sql = "
            SELECT *
            FROM dbo.tblBeExecutionOpeningBalance
            WHERE ExecutionOpeningBalanceID = :executionOpeningBalanceId
              AND FiscalYearID = :fy
              AND ExecutionVersionID = :executionVersionId
              AND ISNULL(ActiveFlag, 1) = 1
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
            ':fy' => $fiscalYearId,
            ':executionVersionId' => $executionVersionId,
        ]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function getReleasedAmountAgainstOpeningBalance(int $executionOpeningBalanceId): float
    {
        if (!$this->supportsWarrantFoundation()) {
            return 0.0;
        }

        $sql = "
            SELECT COALESCE(SUM(COALESCE(wl.ReleaseAmount, 0)), 0)
            FROM dbo.tblBeWarrantLine wl
            INNER JOIN dbo.tblBeWarrant w
                ON w.WarrantID = wl.WarrantID
            WHERE wl.ExecutionOpeningBalanceID = :executionOpeningBalanceId
              AND ISNULL(wl.ActiveFlag, 1) = 1
              AND ISNULL(w.ActiveFlag, 1) = 1
              AND w.WarrantStatusCode <> N'CANCELLED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
        ]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    private function getApprovedReleasedAmountAgainstOpeningBalance(int $executionOpeningBalanceId): float
    {
        if (!$this->supportsWarrantFoundation()) {
            return 0.0;
        }

        $sql = "
            SELECT COALESCE(SUM(COALESCE(wl.ReleaseAmount, 0)), 0)
            FROM dbo.tblBeWarrantLine wl
            INNER JOIN dbo.tblBeWarrant w
                ON w.WarrantID = wl.WarrantID
            WHERE wl.ExecutionOpeningBalanceID = :executionOpeningBalanceId
              AND ISNULL(wl.ActiveFlag, 1) = 1
              AND ISNULL(w.ActiveFlag, 1) = 1
              AND w.WarrantStatusCode = N'APPROVED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
        ]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    private function getPlannedReservedAmountAgainstOpeningBalance(int $executionOpeningBalanceId, int $excludeReservationId = 0): float
    {
        $sql = "
            SELECT COALESCE(SUM(COALESCE(rl.ReservationAmount, 0)), 0)
            FROM dbo.tblBeReservationLine rl
            INNER JOIN dbo.tblBeReservation r
                ON r.ReservationID = rl.ReservationID
            WHERE rl.ExecutionOpeningBalanceID = :executionOpeningBalanceId
              AND ISNULL(rl.ActiveFlag, 1) = 1
              AND ISNULL(r.ActiveFlag, 1) = 1
              AND r.ReservationStatusCode <> N'CANCELLED'
        ";
        $params = [
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
        ];
        if ($excludeReservationId > 0) {
            $sql .= "
              AND r.ReservationID <> :excludeReservationId";
            $params[':excludeReservationId'] = $excludeReservationId;
        }

        $st = $this->conn->prepare($sql);
        $st->execute($params);
        return (float) ($st->fetchColumn() ?: 0);
    }

    private function getApprovedReservedAmountAgainstOpeningBalance(int $executionOpeningBalanceId): float
    {
        if (!$this->supportsReservationFoundation()) {
            return 0.0;
        }

        $sql = "
            SELECT COALESCE(SUM(COALESCE(rl.ReservationAmount, 0)), 0)
            FROM dbo.tblBeReservationLine rl
            INNER JOIN dbo.tblBeReservation r
                ON r.ReservationID = rl.ReservationID
            WHERE rl.ExecutionOpeningBalanceID = :executionOpeningBalanceId
              AND ISNULL(rl.ActiveFlag, 1) = 1
              AND ISNULL(r.ActiveFlag, 1) = 1
              AND r.ReservationStatusCode = N'APPROVED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
        ]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    private function getPlannedCommittedAmountAgainstOpeningBalance(int $executionOpeningBalanceId, int $excludeCommitmentId = 0): float
    {
        $sql = "
            SELECT COALESCE(SUM(COALESCE(cl.CommitmentAmount, 0)), 0)
            FROM dbo.tblBeCommitmentLine cl
            INNER JOIN dbo.tblBeCommitment c
                ON c.CommitmentID = cl.CommitmentID
            WHERE cl.ExecutionOpeningBalanceID = :executionOpeningBalanceId
              AND ISNULL(cl.ActiveFlag, 1) = 1
              AND ISNULL(c.ActiveFlag, 1) = 1
              AND c.CommitmentStatusCode <> N'CANCELLED'
        ";
        $params = [
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
        ];
        if ($excludeCommitmentId > 0) {
            $sql .= "
              AND c.CommitmentID <> :excludeCommitmentId";
            $params[':excludeCommitmentId'] = $excludeCommitmentId;
        }

        $st = $this->conn->prepare($sql);
        $st->execute($params);
        return (float) ($st->fetchColumn() ?: 0);
    }

    private function getApprovedCommittedAmountAgainstOpeningBalance(int $executionOpeningBalanceId): float
    {
        if (!$this->supportsCommitmentFoundation()) {
            return 0.0;
        }

        $sql = "
            SELECT COALESCE(SUM(COALESCE(cl.CommitmentAmount, 0)), 0)
            FROM dbo.tblBeCommitmentLine cl
            INNER JOIN dbo.tblBeCommitment c
                ON c.CommitmentID = cl.CommitmentID
            WHERE cl.ExecutionOpeningBalanceID = :executionOpeningBalanceId
              AND ISNULL(cl.ActiveFlag, 1) = 1
              AND ISNULL(c.ActiveFlag, 1) = 1
              AND c.CommitmentStatusCode = N'APPROVED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':executionOpeningBalanceId' => $executionOpeningBalanceId,
        ]);
        return (float) ($st->fetchColumn() ?: 0);
    }

    private function getAvailableApprovedRieAmount(int $rieId, int $excludeCommitmentId = 0): float
    {
        if ($rieId <= 0) {
            return 0.0;
        }

        $sql = "
            SELECT COALESCE(SUM(COALESCE(rl.RieAmount, 0)), 0)
            FROM dbo.tblBeRie r
            INNER JOIN dbo.tblBeRieLine rl
                ON rl.RieID = r.RieID
            WHERE r.RieID = :rieId
              AND ISNULL(r.ActiveFlag, 1) = 1
              AND ISNULL(rl.ActiveFlag, 1) = 1
              AND r.RieStatusCode = N'APPROVED'
        ";
        $st = $this->conn->prepare($sql);
        $st->execute([
            ':rieId' => $rieId,
        ]);
        $approvedRieAmount = (float) ($st->fetchColumn() ?: 0);

        $committedSql = "
            SELECT COALESCE(SUM(COALESCE(cl.CommitmentAmount, 0)), 0)
            FROM dbo.tblBeCommitment c
            INNER JOIN dbo.tblBeCommitmentLine cl
                ON cl.CommitmentID = c.CommitmentID
            WHERE c.RieID = :rieId
              AND ISNULL(c.ActiveFlag, 1) = 1
              AND ISNULL(cl.ActiveFlag, 1) = 1
              AND c.CommitmentStatusCode <> N'CANCELLED'
        ";
        $params = [
            ':rieId' => $rieId,
        ];
        if ($excludeCommitmentId > 0) {
            $committedSql .= "
              AND c.CommitmentID <> :excludeCommitmentId";
            $params[':excludeCommitmentId'] = $excludeCommitmentId;
        }
        $committedStmt = $this->conn->prepare($committedSql);
        $committedStmt->execute($params);
        $committedAmount = (float) ($committedStmt->fetchColumn() ?: 0);

        return $approvedRieAmount - $committedAmount;
    }

    private function workflowEngine(): WorkflowEngineModel
    {
        return new WorkflowEngineModel($this->conn);
    }

    private function normalizeDate($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function nullableString($value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function tableExists(string $tableName): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = :schemaName
              AND TABLE_NAME = :tableName
        ";

        $schemaName = 'dbo';
        $tableOnly = $tableName;
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableOnly] = explode('.', $tableName, 2);
        }

        $st = $this->conn->prepare($sql);
        $st->execute([
            ':schemaName' => $schemaName,
            ':tableName' => $tableOnly,
        ]);
        return (int) ($st->fetchColumn() ?: 0) > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :schemaName
              AND TABLE_NAME = :tableName
              AND COLUMN_NAME = :columnName
        ";

        $schemaName = 'dbo';
        $tableOnly = $tableName;
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableOnly] = explode('.', $tableName, 2);
        }

        $st = $this->conn->prepare($sql);
        $st->execute([
            ':schemaName' => $schemaName,
            ':tableName' => $tableOnly,
            ':columnName' => $columnName,
        ]);

        return (int) ($st->fetchColumn() ?: 0) > 0;
    }
}
