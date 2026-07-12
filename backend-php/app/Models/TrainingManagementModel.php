<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class TrainingManagementModel
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsManagementTables(): bool
    {
        $row = $this->db->query("
            SELECT
                OBJECT_ID(N'dbo.tblTrainingPaths', N'U') AS PathsTableId,
                OBJECT_ID(N'dbo.tblTrainingSessions', N'U') AS SessionsTableId,
                OBJECT_ID(N'dbo.tblTrainingAssignments', N'U') AS AssignmentsTableId,
                OBJECT_ID(N'dbo.tblTrainingStuckEvents', N'U') AS StuckTableId
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return (int) ($row['PathsTableId'] ?? 0) > 0
            && (int) ($row['SessionsTableId'] ?? 0) > 0
            && (int) ($row['AssignmentsTableId'] ?? 0) > 0
            && (int) ($row['StuckTableId'] ?? 0) > 0;
    }

    public function supportsStepSupportTables(): bool
    {
        $row = $this->db->query("
            SELECT
                OBJECT_ID(N'dbo.tblTrainingStepInstructorNotes', N'U') AS NotesTableId,
                OBJECT_ID(N'dbo.tblTrainingStepCheckpoints', N'U') AS CheckpointsTableId
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return (int) ($row['NotesTableId'] ?? 0) > 0
            && (int) ($row['CheckpointsTableId'] ?? 0) > 0;
    }

    public function listPaths(): array
    {
        if (!$this->supportsManagementTables()) {
            return [];
        }

        return $this->db->query("
            SELECT
                p.PathCode,
                p.PathTitle,
                p.Audience,
                p.Description,
                p.ActiveFlag,
                p.SortOrder,
                ScenarioCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingPathScenarios ps
                    WHERE ps.PathCode = p.PathCode
                )
            FROM dbo.tblTrainingPaths p
            ORDER BY p.SortOrder, p.PathTitle, p.PathCode
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function searchUsers(string $query = '', int $limit = 50): array
    {
        $query = trim($query);
        $limit = max(1, min(100, $limit));
        $where = 'IsActive = 1';
        $params = [];
        if ($query !== '') {
            $where .= " AND (
                Username LIKE :q_username
                OR Email LIKE :q_email
                OR DisplayName LIKE :q_display
                OR FirstName LIKE :q_first
                OR LastName LIKE :q_last
                OR CONVERT(NVARCHAR(20), UserID) = :exact_id
            )";
            $likeQuery = '%' . $query . '%';
            $params[':q_username'] = $likeQuery;
            $params[':q_email'] = $likeQuery;
            $params[':q_display'] = $likeQuery;
            $params[':q_first'] = $likeQuery;
            $params[':q_last'] = $likeQuery;
            $params[':exact_id'] = $query;
        }

        $stmt = $this->db->prepare("
            SELECT TOP ({$limit})
                UserID,
                Username,
                LTRIM(RTRIM(DisplayName)) AS DisplayName,
                Email
            FROM dbo.tblUsers
            WHERE {$where}
            ORDER BY LTRIM(RTRIM(DisplayName)) ASC, Username ASC
        ");
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, \PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getPath(string $pathCode): ?array
    {
        $pathCode = trim($pathCode);
        if ($pathCode === '' || !$this->supportsManagementTables()) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT PathCode, PathTitle, Audience, Description, ActiveFlag, SortOrder
            FROM dbo.tblTrainingPaths
            WHERE PathCode = :code
        ");
        $stmt->execute([':code' => $pathCode]);
        $path = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$path) {
            return null;
        }

        $path['Scenarios'] = $this->listPathScenarios($pathCode);
        return $path;
    }

    public function listPathScenarios(string $pathCode): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ps.PathCode,
                ps.ScenarioCode,
                ps.RequiredFlag,
                ps.SortOrder,
                s.ScenarioTitle
            FROM dbo.tblTrainingPathScenarios ps
            LEFT JOIN dbo.tblTrainingScenarios s
                ON s.ScenarioCode = ps.ScenarioCode
            WHERE ps.PathCode = :code
            ORDER BY ps.SortOrder, ps.ScenarioCode
        ");
        $stmt->execute([':code' => trim($pathCode)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function savePath(array $data, int $updatedBy): string
    {
        $pathCode = trim((string) ($data['PathCode'] ?? ''));
        $pathTitle = trim((string) ($data['PathTitle'] ?? ''));
        if ($pathCode === '' || $pathTitle === '') {
            throw new \RuntimeException('Path code and title are required.');
        }

        $this->db->beginTransaction();
        try {
            $params = [
                ':code' => $pathCode,
                ':title' => $pathTitle,
                ':audience' => $this->nullIfEmpty($data['Audience'] ?? null),
                ':description' => $this->nullIfEmpty($data['Description'] ?? null),
                ':active' => !empty($data['ActiveFlag']) ? 1 : 0,
                ':sort_order' => max(0, (int) ($data['SortOrder'] ?? 0)),
                ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ];

            $existsStmt = $this->db->prepare("SELECT 1 FROM dbo.tblTrainingPaths WHERE PathCode = :code");
            $existsStmt->execute([':code' => $pathCode]);
            $pathExists = (bool) $existsStmt->fetchColumn();

            if ($pathExists) {
                $stmt = $this->db->prepare("
                    UPDATE dbo.tblTrainingPaths
                    SET PathTitle = :title,
                        Audience = :audience,
                        Description = :description,
                        ActiveFlag = :active,
                        SortOrder = :sort_order,
                        UpdatedBy = :updated_by,
                        UpdatedDate = SYSUTCDATETIME()
                    WHERE PathCode = :code
                ");
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO dbo.tblTrainingPaths
                        (PathCode, PathTitle, Audience, Description, ActiveFlag, SortOrder, CreatedBy, UpdatedBy)
                    VALUES (:code, :title, :audience, :description, :active, :sort_order, :created_by, :updated_by)
                ");
            }
            $stmt->execute($params + ($pathExists ? [] : [':created_by' => $updatedBy > 0 ? $updatedBy : null]));

            $this->syncPathScenarios($pathCode, (string) ($data['ScenarioCodes'] ?? ''), $updatedBy);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $pathCode;
    }

    public function listAssignments(array $filters = []): array
    {
        if (!$this->supportsManagementTables()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            if ($status === 'open') {
                $where[] = "a.Status IN (N'assigned', N'active', N'in_progress')";
            } else {
                $where[] = 'a.Status = :status';
                $params[':status'] = $status;
            }
        }
        $pathCode = trim((string) ($filters['path_code'] ?? ''));
        if ($pathCode !== '') {
            $where[] = 'a.PathCode = :path_code';
            $params[':path_code'] = $pathCode;
        }

        return $this->prepareFetchAll("
            SELECT
                a.TrainingAssignmentID,
                a.UserID,
                a.PathCode,
                a.ScenarioCode,
                a.DueDate,
                a.Status,
                a.AssignedAt,
                a.CompletedAt,
                a.Notes,
                u.Username,
                u.DisplayName,
                p.PathTitle,
                s.ScenarioTitle
            FROM dbo.tblTrainingAssignments a
            LEFT JOIN dbo.tblUsers u ON u.UserID = a.UserID
            LEFT JOIN dbo.tblTrainingPaths p ON p.PathCode = a.PathCode
            LEFT JOIN dbo.tblTrainingScenarios s ON s.ScenarioCode = a.ScenarioCode
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ISNULL(a.DueDate, CONVERT(date, '9999-12-31')), a.AssignedAt DESC
        ", $params);
    }

    public function listUserAssignments(int $userId): array
    {
        if ($userId <= 0 || !$this->supportsManagementTables()) {
            return [];
        }

        return $this->prepareFetchAll("
            SELECT *
            FROM (
                SELECT
                    a.TrainingAssignmentID,
                    a.UserID,
                    a.PathCode,
                    a.ScenarioCode,
                    EffectiveScenarioCode = COALESCE(a.ScenarioCode, ps.ScenarioCode),
                    a.DueDate,
                    a.Status,
                    a.AssignedAt,
                    a.CompletedAt,
                    a.Notes,
                    AssignmentMode = CASE
                        WHEN a.Notes LIKE N'Created from instructor-led session #%' THEN N'Instructor-led'
                        ELSE N'Self-paced'
                    END,
                    p.PathTitle,
                    s.ScenarioTitle
                FROM dbo.tblTrainingAssignments a
                LEFT JOIN dbo.tblTrainingPathScenarios ps
                    ON ps.PathCode = a.PathCode
                   AND a.ScenarioCode IS NULL
                LEFT JOIN dbo.tblTrainingPaths p
                    ON p.PathCode = a.PathCode
                LEFT JOIN dbo.tblTrainingScenarios s
                    ON s.ScenarioCode = COALESCE(a.ScenarioCode, ps.ScenarioCode)
                WHERE a.UserID = :user_id
                  AND a.Status IN (N'assigned', N'active', N'in_progress', N'completed')
                  AND COALESCE(a.ScenarioCode, ps.ScenarioCode) IS NOT NULL

                UNION ALL

                SELECT
                    TrainingAssignmentID = -su.TrainingSessionUserID,
                    su.UserID,
                    sess.PathCode,
                    sess.ScenarioCode,
                    EffectiveScenarioCode = COALESCE(sess.ScenarioCode, ps.ScenarioCode),
                    DueDate = CAST(NULL AS DATE),
                    Status = CASE
                        WHEN sess.Status = N'completed' OR su.Status = N'completed' THEN N'completed'
                        WHEN sess.Status = N'cancelled' THEN N'cancelled'
                        ELSE N'assigned'
                    END,
                    AssignedAt = COALESCE(sess.ScheduledAt, su.CreatedDate),
                    CompletedAt = su.CompletedAt,
                    Notes = N'Created from instructor-led session #' + CONVERT(NVARCHAR(20), sess.TrainingSessionID) + N'.',
                    AssignmentMode = N'Instructor-led',
                    p.PathTitle,
                    sc.ScenarioTitle
                FROM dbo.tblTrainingSessionUsers su
                INNER JOIN dbo.tblTrainingSessions sess
                    ON sess.TrainingSessionID = su.TrainingSessionID
                LEFT JOIN dbo.tblTrainingPathScenarios ps
                    ON ps.PathCode = sess.PathCode
                   AND sess.ScenarioCode IS NULL
                LEFT JOIN dbo.tblTrainingPaths p
                    ON p.PathCode = sess.PathCode
                LEFT JOIN dbo.tblTrainingScenarios sc
                    ON sc.ScenarioCode = COALESCE(sess.ScenarioCode, ps.ScenarioCode)
                WHERE su.UserID = :session_user_id
                  AND sess.Status <> N'cancelled'
                  AND COALESCE(sess.ScenarioCode, ps.ScenarioCode) IS NOT NULL
                  AND NOT EXISTS (
                        SELECT 1
                        FROM dbo.tblTrainingAssignments a
                        WHERE a.UserID = su.UserID
                          AND a.Status IN (N'assigned', N'active', N'in_progress', N'completed')
                          AND ISNULL(a.PathCode, N'') = ISNULL(sess.PathCode, N'')
                          AND (
                                (a.ScenarioCode IS NULL AND sess.ScenarioCode IS NULL)
                                OR a.ScenarioCode = sess.ScenarioCode
                          )
                          AND a.Notes = N'Created from instructor-led session #' + CONVERT(NVARCHAR(20), sess.TrainingSessionID) + N'.'
                  )
            ) assigned
            WHERE assigned.Status <> N'cancelled'
            ORDER BY ISNULL(assigned.DueDate, CONVERT(date, '9999-12-31')), assigned.AssignedAt DESC
        ", [
            ':user_id' => $userId,
            ':session_user_id' => $userId,
        ]);
    }

    public function saveAssignment(array $data, int $updatedBy): array
    {
        $userIds = array_values(array_unique($this->parseIntList((string) ($data['UserIDs'] ?? ''))));
        $pathCode = $this->nullIfEmpty($data['PathCode'] ?? null);
        $scenarioCode = $this->nullIfEmpty($data['ScenarioCode'] ?? null);
        $notes = $this->nullIfEmpty($data['Notes'] ?? null);
        $sourceRef = trim((string) ($data['SourceRef'] ?? ''));
        if ($userIds === [] || ($pathCode === null && $scenarioCode === null)) {
            throw new \RuntimeException('At least one user and either a path or scenario are required.');
        }

        $duplicateWhere = [
            'UserID = :user_id_check',
            "Status IN (N'assigned', N'active', N'in_progress')",
            $pathCode === null ? 'PathCode IS NULL' : 'PathCode = :path_code_check',
            $scenarioCode === null ? 'ScenarioCode IS NULL' : 'ScenarioCode = :scenario_code_check',
        ];
        if (str_starts_with($sourceRef, 'training_session:')) {
            $duplicateWhere[] = $notes === null ? 'Notes IS NULL' : 'Notes = :notes_check';
        } elseif ($sourceRef === 'self_paced') {
            $duplicateWhere[] = "(Notes IS NULL OR Notes NOT LIKE N'Created from instructor-led session #%')";
        }
        $duplicateStmt = $this->db->prepare("
            SELECT TOP 1 TrainingAssignmentID
            FROM dbo.tblTrainingAssignments
            WHERE " . implode(' AND ', $duplicateWhere) . "
        ");

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblTrainingAssignments
                (UserID, PathCode, ScenarioCode, DueDate, Status, AssignedBy, Notes, CreatedBy, UpdatedBy)
            VALUES
                (:user_id, :path_code, :scenario_code, :due_date, N'assigned', :assigned_by, :notes, :created_by, :updated_by)
        ");

        $count = 0;
        $skipped = 0;
        $auditUserId = $updatedBy > 0 ? $updatedBy : null;
        foreach ($userIds as $userId) {
            $duplicateParams = [
                ':user_id_check' => $userId,
            ];
            if ($pathCode !== null) {
                $duplicateParams[':path_code_check'] = $pathCode;
            }
            if ($scenarioCode !== null) {
                $duplicateParams[':scenario_code_check'] = $scenarioCode;
            }
            if (str_starts_with($sourceRef, 'training_session:') && $notes !== null) {
                $duplicateParams[':notes_check'] = $notes;
            }
            $duplicateStmt->execute($duplicateParams);
            if ($duplicateStmt->fetchColumn()) {
                $skipped++;
                continue;
            }

            $stmt->execute([
                ':user_id' => $userId,
                ':path_code' => $pathCode,
                ':scenario_code' => $scenarioCode,
                ':due_date' => $this->nullIfEmpty($data['DueDate'] ?? null),
                ':assigned_by' => $auditUserId,
                ':notes' => $notes,
                ':created_by' => $auditUserId,
                ':updated_by' => $auditUserId,
            ]);
            $count++;
        }

        return ['created' => $count, 'skipped' => $skipped];
    }

    public function markAssignmentsCompleted(int $userId, string $scenarioCode, int $updatedBy): void
    {
        $scenarioCode = trim($scenarioCode);
        if ($userId <= 0 || $scenarioCode === '' || !$this->supportsManagementTables()) {
            return;
        }

        $params = [
            ':user_id' => $userId,
            ':scenario_code' => $scenarioCode,
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ];

        $directStmt = $this->db->prepare("
            UPDATE dbo.tblTrainingAssignments
            SET Status = N'completed',
                CompletedAt = SYSUTCDATETIME(),
                UpdatedBy = :updated_by,
                UpdatedDate = SYSUTCDATETIME()
            WHERE UserID = :user_id
              AND ScenarioCode = :scenario_code
              AND Status IN (N'assigned', N'active', N'in_progress')
              AND (Notes IS NULL OR Notes NOT LIKE N'Created from instructor-led session #%')
        ");
        $directStmt->execute($params);

        $pathStmt = $this->db->prepare("
            UPDATE a
            SET Status = N'completed',
                CompletedAt = SYSUTCDATETIME(),
                UpdatedBy = :updated_by,
                UpdatedDate = SYSUTCDATETIME()
            FROM dbo.tblTrainingAssignments a
            WHERE a.UserID = :user_id
              AND a.ScenarioCode IS NULL
              AND a.PathCode IS NOT NULL
              AND a.Status IN (N'assigned', N'active', N'in_progress')
              AND (a.Notes IS NULL OR a.Notes NOT LIKE N'Created from instructor-led session #%')
              AND EXISTS (
                    SELECT 1
                    FROM dbo.tblTrainingPathScenarios ps
                    WHERE ps.PathCode = a.PathCode
                      AND ps.ScenarioCode = :scenario_code
              )
              AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblTrainingPathScenarios ps
                    WHERE ps.PathCode = a.PathCode
                      AND ps.RequiredFlag = 1
                      AND NOT EXISTS (
                            SELECT 1
                            FROM dbo.tblTrainingProgress tp
                            WHERE tp.UserID = a.UserID
                              AND tp.ScenarioCode = ps.ScenarioCode
                              AND tp.Status = N'completed'
                      )
              )
        ");
        $pathStmt->execute($params);
    }

    public function markAssignmentCompleted(int $assignmentId, int $userId, int $updatedBy): void
    {
        if ($assignmentId <= 0 || $userId <= 0 || !$this->supportsManagementTables()) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblTrainingAssignments
            SET Status = N'completed',
                CompletedAt = SYSUTCDATETIME(),
                UpdatedBy = :updated_by,
                UpdatedDate = SYSUTCDATETIME()
            WHERE TrainingAssignmentID = :assignment_id
              AND UserID = :user_id
              AND Status IN (N'assigned', N'active', N'in_progress')
              AND (Notes IS NULL OR Notes NOT LIKE N'Created from instructor-led session #%')
        ");
        $stmt->execute([
            ':assignment_id' => $assignmentId,
            ':user_id' => $userId,
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ]);
    }

    public function cancelAssignment(int $assignmentId, int $updatedBy): bool
    {
        if ($assignmentId <= 0 || !$this->supportsManagementTables()) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE dbo.tblTrainingAssignments
            SET Status = N'cancelled',
                UpdatedBy = :updated_by,
                UpdatedDate = SYSUTCDATETIME()
            WHERE TrainingAssignmentID = :assignment_id
              AND Status IN (N'assigned', N'active', N'in_progress')
        ");
        $stmt->execute([
            ':assignment_id' => $assignmentId,
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function listSessions(): array
    {
        if (!$this->supportsManagementTables()) {
            return [];
        }

        return $this->db->query("
            SELECT
                s.TrainingSessionID,
                s.SessionCode,
                s.SessionTitle,
                s.InstructorUserID,
                s.PathCode,
                s.ScenarioCode,
                s.ScheduledAt,
                s.Status,
                s.Notes,
                InstructorName = COALESCE(NULLIF(i.DisplayName, N''), i.Username),
                p.PathTitle,
                sc.ScenarioTitle,
                ParticipantUserIDs = ISNULL(STUFF((
                    SELECT N',' + CONVERT(NVARCHAR(20), su.UserID)
                    FROM dbo.tblTrainingSessionUsers su
                    WHERE su.TrainingSessionID = s.TrainingSessionID
                    ORDER BY su.UserID
                    FOR XML PATH(''), TYPE
                ).value(N'.', N'NVARCHAR(MAX)'), 1, 1, N''), N''),
                ParticipantUsersJson = ISNULL((
                    SELECT
                        su.UserID AS id,
                        COALESCE(NULLIF(u.DisplayName, N''), u.Username, N'User #' + CONVERT(NVARCHAR(20), su.UserID)) AS label,
                        ISNULL(u.Username, N'') AS username,
                        ISNULL(u.Email, N'') AS email
                    FROM dbo.tblTrainingSessionUsers su
                    LEFT JOIN dbo.tblUsers u ON u.UserID = su.UserID
                    WHERE su.TrainingSessionID = s.TrainingSessionID
                    ORDER BY COALESCE(NULLIF(u.DisplayName, N''), u.Username, N'User #' + CONVERT(NVARCHAR(20), su.UserID))
                    FOR JSON PATH
                ), N'[]'),
                ParticipantCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingSessionUsers su
                    WHERE su.TrainingSessionID = s.TrainingSessionID
                )
            FROM dbo.tblTrainingSessions s
            LEFT JOIN dbo.tblUsers i ON i.UserID = s.InstructorUserID
            LEFT JOIN dbo.tblTrainingPaths p ON p.PathCode = s.PathCode
            LEFT JOIN dbo.tblTrainingScenarios sc ON sc.ScenarioCode = s.ScenarioCode
            ORDER BY ISNULL(s.ScheduledAt, SYSUTCDATETIME()) DESC, s.TrainingSessionID DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listSessionSummary(array $filters = []): array
    {
        if (!$this->supportsManagementTables()) {
            return [];
        }

        $hasEvidenceTable = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingEvidence', N'U')")->fetchColumn() ?: 0) > 0;
        $hasProgressTable = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingProgress', N'U')")->fetchColumn() ?: 0) > 0;
        $activeParticipantCountSql = $hasProgressTable
            ? "(
                    SELECT COUNT(DISTINCT su.UserID)
                    FROM dbo.tblTrainingSessionUsers su
                    INNER JOIN dbo.tblTrainingProgress tp
                        ON tp.UserID = su.UserID
                    WHERE su.TrainingSessionID = s.TrainingSessionID
                      AND tp.Status IN (N'active', N'in_progress')
                      AND (
                            (s.ScenarioCode IS NOT NULL AND tp.ScenarioCode = s.ScenarioCode)
                            OR (
                                s.ScenarioCode IS NULL
                                AND s.PathCode IS NOT NULL
                                AND EXISTS (
                                    SELECT 1
                                    FROM dbo.tblTrainingPathScenarios ps
                                    WHERE ps.PathCode = s.PathCode
                                      AND ps.ScenarioCode = tp.ScenarioCode
                                )
                            )
                      )
                )"
            : "CAST(0 AS INT)";
        $evidenceCountSql = $hasEvidenceTable
            ? "(
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingEvidence e
                    WHERE e.TrainingSessionID = s.TrainingSessionID
                )"
            : "CAST(0 AS INT)";
        $lastEvidenceAtSql = $hasEvidenceTable
            ? "(
                    SELECT MAX(e.EvidenceAt)
                    FROM dbo.tblTrainingEvidence e
                    WHERE e.TrainingSessionID = s.TrainingSessionID
                )"
            : "CAST(NULL AS DATETIME2(0))";

        $where = [];
        $params = [];
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 's.Status = :status';
            $params[':status'] = $status;
        }
        $pathCode = trim((string) ($filters['path_code'] ?? ''));
        if ($pathCode !== '') {
            $where[] = 's.PathCode = :path_code';
            $params[':path_code'] = $pathCode;
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                s.SessionCode LIKE :q_session_code
                OR s.SessionTitle LIKE :q_session_title
                OR p.PathTitle LIKE :q_path_title
                OR sc.ScenarioTitle LIKE :q_scenario_title
                OR i.Username LIKE :q_instructor_username
                OR i.DisplayName LIKE :q_instructor_display
            )";
            $likeSearch = '%' . $search . '%';
            $params[':q_session_code'] = $likeSearch;
            $params[':q_session_title'] = $likeSearch;
            $params[':q_path_title'] = $likeSearch;
            $params[':q_scenario_title'] = $likeSearch;
            $params[':q_instructor_username'] = $likeSearch;
            $params[':q_instructor_display'] = $likeSearch;
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

        return $this->prepareFetchAll("
            SELECT
                s.TrainingSessionID,
                s.SessionCode,
                s.SessionTitle,
                s.InstructorUserID,
                s.PathCode,
                s.ScenarioCode,
                s.ScheduledAt,
                s.Status,
                s.Notes,
                InstructorName = COALESCE(NULLIF(i.DisplayName, N''), i.Username),
                p.PathTitle,
                sc.ScenarioTitle,
                PathScenarioCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingPathScenarios ps
                    WHERE ps.PathCode = s.PathCode
                ),
                ParticipantCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingSessionUsers su
                    WHERE su.TrainingSessionID = s.TrainingSessionID
                ),
                CompletedParticipantCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingSessionUsers su
                    WHERE su.TrainingSessionID = s.TrainingSessionID
                      AND su.Status = N'completed'
                ),
                ActiveParticipantCount = {$activeParticipantCountSql},
                EvidenceCount = {$evidenceCountSql},
                LastEvidenceAt = {$lastEvidenceAtSql}
            FROM dbo.tblTrainingSessions s
            LEFT JOIN dbo.tblUsers i ON i.UserID = s.InstructorUserID
            LEFT JOIN dbo.tblTrainingPaths p ON p.PathCode = s.PathCode
            LEFT JOIN dbo.tblTrainingScenarios sc ON sc.ScenarioCode = s.ScenarioCode
            WHERE {$whereSql}
            ORDER BY ISNULL(s.ScheduledAt, SYSUTCDATETIME()) DESC, s.TrainingSessionID DESC
        ", $params);
    }

    public function listSessionSummaryParticipants(array $filters = []): array
    {
        if (!$this->supportsManagementTables()) {
            return [];
        }

        $hasEvidenceTable = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingEvidence', N'U')")->fetchColumn() ?: 0) > 0;
        $hasProgressTable = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingProgress', N'U')")->fetchColumn() ?: 0) > 0;
        $progressColumnsSql = $hasProgressTable
            ? "
                ProgressScenarioCode = tp.ScenarioCode,
                ProgressStatus = tp.Status,
                CurrentStep = tp.CurrentStep,
                TotalSteps = tp.TotalSteps,
                AttemptNo = tp.AttemptNo,
                LastActivityAt = tp.LastActivityAt,
                CompletedScenarioCount = CASE
                    WHEN s.PathCode IS NULL THEN CASE WHEN tp.Status = N'completed' THEN 1 ELSE 0 END
                    ELSE (
                        SELECT COUNT(*)
                        FROM dbo.tblTrainingProgress done
                        INNER JOIN dbo.tblTrainingPathScenarios ps
                            ON ps.ScenarioCode = done.ScenarioCode
                        WHERE done.UserID = su.UserID
                          AND ps.PathCode = s.PathCode
                          AND done.Status = N'completed'
                    )
                END"
            : "
                ProgressScenarioCode = CAST(NULL AS NVARCHAR(100)),
                ProgressStatus = CAST(NULL AS NVARCHAR(50)),
                CurrentStep = CAST(NULL AS INT),
                TotalSteps = CAST(NULL AS INT),
                AttemptNo = CAST(NULL AS INT),
                LastActivityAt = CAST(NULL AS DATETIME2(0)),
                CompletedScenarioCount = CAST(0 AS INT)";
        $progressApplySql = $hasProgressTable
            ? "
            OUTER APPLY (
                SELECT TOP 1 p.*
                FROM dbo.tblTrainingProgress p
                WHERE p.UserID = su.UserID
                  AND (
                        (s.ScenarioCode IS NOT NULL AND p.ScenarioCode = s.ScenarioCode)
                        OR (
                            s.ScenarioCode IS NULL
                            AND s.PathCode IS NOT NULL
                            AND EXISTS (
                                SELECT 1
                                FROM dbo.tblTrainingPathScenarios ps
                                WHERE ps.PathCode = s.PathCode
                                  AND ps.ScenarioCode = p.ScenarioCode
                            )
                        )
                  )
                ORDER BY p.LastActivityAt DESC
            ) tp"
            : "";
        $evidenceCountSql = $hasEvidenceTable
            ? "(
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingEvidence e
                    WHERE e.TrainingSessionID = s.TrainingSessionID
                      AND e.UserID = su.UserID
                )"
            : "CAST(0 AS INT)";

        $where = [];
        $params = [];
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 's.Status = :status';
            $params[':status'] = $status;
        }
        $pathCode = trim((string) ($filters['path_code'] ?? ''));
        if ($pathCode !== '') {
            $where[] = 's.PathCode = :path_code';
            $params[':path_code'] = $pathCode;
        }
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $where[] = "(
                s.SessionCode LIKE :q_session_code
                OR s.SessionTitle LIKE :q_session_title
                OR p.PathTitle LIKE :q_path_title
                OR sc.ScenarioTitle LIKE :q_scenario_title
                OR i.Username LIKE :q_instructor_username
                OR i.DisplayName LIKE :q_instructor_display
                OR u.Username LIKE :q_learner_username
                OR u.DisplayName LIKE :q_learner_display
                OR u.Email LIKE :q_learner_email
            )";
            $likeSearch = '%' . $search . '%';
            $params[':q_session_code'] = $likeSearch;
            $params[':q_session_title'] = $likeSearch;
            $params[':q_path_title'] = $likeSearch;
            $params[':q_scenario_title'] = $likeSearch;
            $params[':q_instructor_username'] = $likeSearch;
            $params[':q_instructor_display'] = $likeSearch;
            $params[':q_learner_username'] = $likeSearch;
            $params[':q_learner_display'] = $likeSearch;
            $params[':q_learner_email'] = $likeSearch;
        }

        $whereSql = $where === [] ? '1=1' : implode(' AND ', $where);

        return $this->prepareFetchAll("
            SELECT
                s.TrainingSessionID,
                su.TrainingSessionUserID,
                su.UserID,
                LearnerName = COALESCE(NULLIF(u.DisplayName, N''), u.Username, N'User #' + CONVERT(NVARCHAR(20), su.UserID)),
                u.Username,
                u.Email,
                SessionUserStatus = su.Status,
                su.JoinedAt,
                su.CompletedAt AS SessionCompletedAt,
                {$progressColumnsSql},
                EvidenceCount = {$evidenceCountSql}
            FROM dbo.tblTrainingSessions s
            INNER JOIN dbo.tblTrainingSessionUsers su
                ON su.TrainingSessionID = s.TrainingSessionID
            LEFT JOIN dbo.tblUsers u
                ON u.UserID = su.UserID
            LEFT JOIN dbo.tblUsers i
                ON i.UserID = s.InstructorUserID
            LEFT JOIN dbo.tblTrainingPaths p
                ON p.PathCode = s.PathCode
            LEFT JOIN dbo.tblTrainingScenarios sc
                ON sc.ScenarioCode = s.ScenarioCode
            {$progressApplySql}
            WHERE {$whereSql}
            ORDER BY s.TrainingSessionID DESC, COALESCE(NULLIF(u.DisplayName, N''), u.Username), su.UserID
        ", $params);
    }

    public function saveSession(array $data, int $updatedBy): int
    {
        $sessionCode = trim((string) ($data['SessionCode'] ?? ''));
        $sessionTitle = trim((string) ($data['SessionTitle'] ?? ''));
        if ($sessionTitle === '') {
            throw new \RuntimeException('Session title is required.');
        }
        if ($sessionCode === '') {
            $sessionCode = $this->generateSessionCode();
        }

        $this->db->beginTransaction();
        try {
            $params = [
                ':code' => $sessionCode,
                ':title' => $sessionTitle,
                ':instructor_user_id' => $this->intOrNull($data['InstructorUserID'] ?? null),
                ':path_code' => $this->nullIfEmpty($data['PathCode'] ?? null),
                ':scenario_code' => $this->nullIfEmpty($data['ScenarioCode'] ?? null),
                ':scheduled_at' => $this->normalizeSqlDateTime($data['ScheduledAt'] ?? null),
                ':status' => trim((string) ($data['Status'] ?? 'planned')) ?: 'planned',
                ':notes' => $this->nullIfEmpty($data['Notes'] ?? null),
                ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
            ];

            $existsStmt = $this->db->prepare("SELECT 1 FROM dbo.tblTrainingSessions WHERE SessionCode = :code");
            $existsStmt->execute([':code' => $sessionCode]);
            $sessionExists = (bool) $existsStmt->fetchColumn();

            if ($sessionExists) {
                $stmt = $this->db->prepare("
                    UPDATE dbo.tblTrainingSessions
                    SET SessionTitle = :title,
                        InstructorUserID = :instructor_user_id,
                        PathCode = :path_code,
                        ScenarioCode = :scenario_code,
                        ScheduledAt = :scheduled_at,
                        Status = :status,
                        Notes = :notes,
                        UpdatedBy = :updated_by,
                        UpdatedDate = SYSUTCDATETIME()
                    WHERE SessionCode = :code
                ");
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO dbo.tblTrainingSessions
                        (SessionCode, SessionTitle, InstructorUserID, PathCode, ScenarioCode, ScheduledAt, Status, Notes, CreatedBy, UpdatedBy)
                    VALUES (:code, :title, :instructor_user_id, :path_code, :scenario_code, :scheduled_at, :status, :notes, :created_by, :updated_by)
                ");
            }
            $stmt->execute($params + ($sessionExists ? [] : [':created_by' => $updatedBy > 0 ? $updatedBy : null]));

            $sessionId = $this->sessionIdForCode($sessionCode);
            $this->syncSessionUsers($sessionId, (string) ($data['UserIDs'] ?? ''), $updatedBy);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->sessionIdForCode($sessionCode);
    }

    public function getSessionDashboard(int $sessionId): array
    {
        if ($sessionId <= 0 || !$this->supportsManagementTables()) {
            return ['session' => null, 'participants' => []];
        }

        $stmt = $this->db->prepare("
            SELECT
                s.*,
                InstructorName = COALESCE(NULLIF(i.DisplayName, N''), i.Username),
                p.PathTitle,
                sc.ScenarioTitle
            FROM dbo.tblTrainingSessions s
            LEFT JOIN dbo.tblUsers i ON i.UserID = s.InstructorUserID
            LEFT JOIN dbo.tblTrainingPaths p ON p.PathCode = s.PathCode
            LEFT JOIN dbo.tblTrainingScenarios sc ON sc.ScenarioCode = s.ScenarioCode
            WHERE s.TrainingSessionID = :id
        ");
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($session === null) {
            return ['session' => null, 'participants' => []];
        }

        $scenarioCode = trim((string) ($session['ScenarioCode'] ?? ''));
        $pathCode = trim((string) ($session['PathCode'] ?? ''));
        $pathScenarioCount = $pathCode !== '' ? count($this->listPathScenarios($pathCode)) : 0;

        $progressFilterSql = '1=0';
        $progressParams = [];
        if ($scenarioCode !== '') {
            $progressFilterSql = 'p.ScenarioCode = :scenario_code_match';
            $progressParams[':scenario_code_match'] = $scenarioCode;
        } elseif ($pathCode !== '') {
            $progressFilterSql = 'p.ScenarioCode IN (
                SELECT ScenarioCode FROM dbo.tblTrainingPathScenarios WHERE PathCode = :path_code_match
            )';
            $progressParams[':path_code_match'] = $pathCode;
        }

        $participants = $this->prepareFetchAll("
            SELECT
                su.TrainingSessionUserID,
                su.UserID,
                su.Status AS SessionUserStatus,
                su.JoinedAt,
                su.CompletedAt AS SessionCompletedAt,
                u.Username,
                u.DisplayName,
                u.Email,
                tp.ScenarioCode,
                tp.Status AS ProgressStatus,
                tp.CurrentStep,
                tp.TotalSteps,
                tp.AttemptNo,
                tp.LastActivityAt,
                CompletedScenarioCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingProgress done
                    INNER JOIN dbo.tblTrainingPathScenarios ps
                        ON ps.ScenarioCode = done.ScenarioCode
                    WHERE done.UserID = su.UserID
                      AND ps.PathCode = :path_code_count
                      AND done.Status = N'completed'
                )
            FROM dbo.tblTrainingSessionUsers su
            LEFT JOIN dbo.tblUsers u ON u.UserID = su.UserID
            OUTER APPLY (
                SELECT TOP 1 *
                FROM dbo.tblTrainingProgress p
                WHERE p.UserID = su.UserID
                  AND {$progressFilterSql}
                ORDER BY p.LastActivityAt DESC
            ) tp
            WHERE su.TrainingSessionID = :session_id
            ORDER BY COALESCE(NULLIF(u.DisplayName, N''), u.Username), su.UserID
        ", $progressParams + [
            ':session_id' => $sessionId,
            ':path_code_count' => $pathCode,
        ]);

        return [
            'session' => $session + ['PathScenarioCount' => $pathScenarioCount],
            'participants' => $participants,
        ];
    }

    public function resetSessionTrainingProgress(int $sessionId, int $updatedBy): array
    {
        if ($sessionId <= 0 || !$this->supportsManagementTables()) {
            throw new \RuntimeException('Training management tables are not installed.');
        }

        $sessionStmt = $this->db->prepare("
            SELECT TrainingSessionID, PathCode, ScenarioCode
            FROM dbo.tblTrainingSessions
            WHERE TrainingSessionID = :session_id
        ");
        $sessionStmt->execute([':session_id' => $sessionId]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($session === null) {
            throw new \RuntimeException('Training session was not found.');
        }

        $participantStmt = $this->db->prepare("
            SELECT UserID
            FROM dbo.tblTrainingSessionUsers
            WHERE TrainingSessionID = :session_id
        ");
        $participantStmt->execute([':session_id' => $sessionId]);
        $participantUserIds = array_values(array_unique(array_map(
            static fn(array $row): int => (int) ($row['UserID'] ?? 0),
            $participantStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        )));
        $participantUserIds = array_values(array_filter($participantUserIds, static fn(int $userId): bool => $userId > 0));

        $scenarioCode = trim((string) ($session['ScenarioCode'] ?? ''));
        $pathCode = trim((string) ($session['PathCode'] ?? ''));
        $scenarioCodes = [];
        if ($scenarioCode !== '') {
            $scenarioCodes[] = $scenarioCode;
        } elseif ($pathCode !== '') {
            foreach ($this->listPathScenarios($pathCode) as $pathScenario) {
                $pathScenarioCode = trim((string) ($pathScenario['ScenarioCode'] ?? ''));
                if ($pathScenarioCode !== '') {
                    $scenarioCodes[] = $pathScenarioCode;
                }
            }
        }
        $scenarioCodes = array_values(array_unique($scenarioCodes));

        if ($scenarioCodes === []) {
            throw new \RuntimeException('The selected session does not have a scenario or course/path scenario scope to reset.');
        }

        $progressDeleted = 0;
        $participantsReset = 0;
        $assignmentsReset = 0;
        $auditUserId = $updatedBy > 0 ? $updatedBy : null;

        $this->db->beginTransaction();
        try {
            if ($participantUserIds !== []) {
                $participantsStmt = $this->db->prepare("
                    UPDATE dbo.tblTrainingSessionUsers
                    SET Status = N'assigned',
                        CompletedAt = NULL,
                        UpdatedBy = :updated_by,
                        UpdatedDate = SYSUTCDATETIME()
                    WHERE TrainingSessionID = :session_id
                ");
                $participantsStmt->execute([
                    ':updated_by' => $auditUserId,
                    ':session_id' => $sessionId,
                ]);
                $participantsReset = (int) $participantsStmt->rowCount();

                $assignmentSql = "
                    UPDATE a
                    SET Status = N'assigned',
                        CompletedAt = NULL,
                        UpdatedBy = :assignment_updated_by,
                        UpdatedDate = SYSUTCDATETIME()
                    FROM dbo.tblTrainingAssignments a
                    INNER JOIN dbo.tblTrainingSessionUsers su
                        ON su.UserID = a.UserID
                    WHERE su.TrainingSessionID = :assignment_session_id
                      AND a.Notes = :assignment_note
                      AND a.Status IN (N'assigned', N'active', N'in_progress', N'completed')
                ";
                $assignmentParams = [
                    ':assignment_updated_by' => $auditUserId,
                    ':assignment_session_id' => $sessionId,
                    ':assignment_note' => 'Created from instructor-led session #' . $sessionId . '.',
                ];
                if ($scenarioCode !== '') {
                    $assignmentSql .= " AND a.ScenarioCode = :assignment_scenario_code";
                    $assignmentParams[':assignment_scenario_code'] = $scenarioCode;
                } else {
                    $assignmentSql .= " AND a.PathCode = :assignment_path_code AND a.ScenarioCode IS NULL";
                    $assignmentParams[':assignment_path_code'] = $pathCode;
                }
                $assignmentStmt = $this->db->prepare($assignmentSql);
                $assignmentStmt->execute($assignmentParams);
                $assignmentsReset = (int) $assignmentStmt->rowCount();

                $hasProgressTable = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingProgress', N'U')")->fetchColumn() ?: 0) > 0;
                if ($hasProgressTable) {
                    $scenarioPlaceholders = [];
                    $progressParams = [
                        ':progress_session_id' => $sessionId,
                    ];
                    foreach ($scenarioCodes as $index => $resetScenarioCode) {
                        $placeholder = ':progress_scenario_' . $index;
                        $scenarioPlaceholders[] = $placeholder;
                        $progressParams[$placeholder] = $resetScenarioCode;
                    }

                    $progressStmt = $this->db->prepare("
                        DELETE tp
                        FROM dbo.tblTrainingProgress tp
                        INNER JOIN dbo.tblTrainingSessionUsers su
                            ON su.UserID = tp.UserID
                        WHERE su.TrainingSessionID = :progress_session_id
                          AND tp.ScenarioCode IN (" . implode(', ', $scenarioPlaceholders) . ")
                    ");
                    $progressStmt->execute($progressParams);
                    $progressDeleted = (int) $progressStmt->rowCount();
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'participants' => count($participantUserIds),
            'participant_rows_reset' => $participantsReset,
            'scenario_count' => count($scenarioCodes),
            'progress_rows_deleted' => $progressDeleted,
            'assignments_reset' => $assignmentsReset,
        ];
    }

    public function resetSessionParticipantTrainingProgress(int $sessionId, int $userId, int $updatedBy): array
    {
        if ($sessionId <= 0 || $userId <= 0 || !$this->supportsManagementTables()) {
            throw new \RuntimeException('Training management tables are not installed.');
        }

        $sessionStmt = $this->db->prepare("
            SELECT TrainingSessionID, PathCode, ScenarioCode
            FROM dbo.tblTrainingSessions
            WHERE TrainingSessionID = :session_id
        ");
        $sessionStmt->execute([':session_id' => $sessionId]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($session === null) {
            throw new \RuntimeException('Training session was not found.');
        }

        $participantStmt = $this->db->prepare("
            SELECT 1
            FROM dbo.tblTrainingSessionUsers
            WHERE TrainingSessionID = :participant_session_id
              AND UserID = :participant_user_id
        ");
        $participantStmt->execute([
            ':participant_session_id' => $sessionId,
            ':participant_user_id' => $userId,
        ]);
        if (!$participantStmt->fetchColumn()) {
            throw new \RuntimeException('The selected user is not a participant in this training session.');
        }

        $scenarioCode = trim((string) ($session['ScenarioCode'] ?? ''));
        $pathCode = trim((string) ($session['PathCode'] ?? ''));
        $scenarioCodes = [];
        if ($scenarioCode !== '') {
            $scenarioCodes[] = $scenarioCode;
        } elseif ($pathCode !== '') {
            foreach ($this->listPathScenarios($pathCode) as $pathScenario) {
                $pathScenarioCode = trim((string) ($pathScenario['ScenarioCode'] ?? ''));
                if ($pathScenarioCode !== '') {
                    $scenarioCodes[] = $pathScenarioCode;
                }
            }
        }
        $scenarioCodes = array_values(array_unique($scenarioCodes));

        if ($scenarioCodes === []) {
            throw new \RuntimeException('The selected session does not have a scenario or course/path scenario scope to reset.');
        }

        $progressDeleted = 0;
        $participantsReset = 0;
        $assignmentsReset = 0;
        $auditUserId = $updatedBy > 0 ? $updatedBy : null;

        $this->db->beginTransaction();
        try {
            $participantsStmt = $this->db->prepare("
                UPDATE dbo.tblTrainingSessionUsers
                SET Status = N'assigned',
                    CompletedAt = NULL,
                    UpdatedBy = :updated_by,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE TrainingSessionID = :session_id
                  AND UserID = :user_id
            ");
            $participantsStmt->execute([
                ':updated_by' => $auditUserId,
                ':session_id' => $sessionId,
                ':user_id' => $userId,
            ]);
            $participantsReset = (int) $participantsStmt->rowCount();

            $assignmentSql = "
                UPDATE a
                SET Status = N'assigned',
                    CompletedAt = NULL,
                    UpdatedBy = :assignment_updated_by,
                    UpdatedDate = SYSUTCDATETIME()
                FROM dbo.tblTrainingAssignments a
                WHERE a.UserID = :assignment_user_id
                  AND a.Notes = :assignment_note
                  AND a.Status IN (N'assigned', N'active', N'in_progress', N'completed')
            ";
            $assignmentParams = [
                ':assignment_updated_by' => $auditUserId,
                ':assignment_user_id' => $userId,
                ':assignment_note' => 'Created from instructor-led session #' . $sessionId . '.',
            ];
            if ($scenarioCode !== '') {
                $assignmentSql .= " AND a.ScenarioCode = :assignment_scenario_code";
                $assignmentParams[':assignment_scenario_code'] = $scenarioCode;
            } else {
                $assignmentSql .= " AND a.PathCode = :assignment_path_code AND a.ScenarioCode IS NULL";
                $assignmentParams[':assignment_path_code'] = $pathCode;
            }
            $assignmentStmt = $this->db->prepare($assignmentSql);
            $assignmentStmt->execute($assignmentParams);
            $assignmentsReset = (int) $assignmentStmt->rowCount();

            $hasProgressTable = (int) ($this->db->query("SELECT OBJECT_ID(N'dbo.tblTrainingProgress', N'U')")->fetchColumn() ?: 0) > 0;
            if ($hasProgressTable) {
                $scenarioPlaceholders = [];
                $progressParams = [
                    ':progress_user_id' => $userId,
                ];
                foreach ($scenarioCodes as $index => $resetScenarioCode) {
                    $placeholder = ':progress_scenario_' . $index;
                    $scenarioPlaceholders[] = $placeholder;
                    $progressParams[$placeholder] = $resetScenarioCode;
                }

                $progressStmt = $this->db->prepare("
                    DELETE FROM dbo.tblTrainingProgress
                    WHERE UserID = :progress_user_id
                      AND ScenarioCode IN (" . implode(', ', $scenarioPlaceholders) . ")
                ");
                $progressStmt->execute($progressParams);
                $progressDeleted = (int) $progressStmt->rowCount();
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'participants' => 1,
            'participant_rows_reset' => $participantsReset,
            'scenario_count' => count($scenarioCodes),
            'progress_rows_deleted' => $progressDeleted,
            'assignments_reset' => $assignmentsReset,
        ];
    }

    public function getStepSupport(string $scenarioCode, int $stepNo): array
    {
        $scenarioCode = trim($scenarioCode);
        if ($scenarioCode === '' || $stepNo <= 0 || !$this->supportsStepSupportTables()) {
            return [];
        }

        $noteStmt = $this->db->prepare("
            SELECT TrainerNote, ExpectedOutcome, CommonIssues
            FROM dbo.tblTrainingStepInstructorNotes
            WHERE ScenarioCode = :code
              AND StepNo = :step_no
        ");
        $noteStmt->execute([':code' => $scenarioCode, ':step_no' => $stepNo]);

        $checkpointStmt = $this->db->prepare("
            SELECT QuestionText, ExpectedAnswer, RequiredFlag, ActiveFlag
            FROM dbo.tblTrainingStepCheckpoints
            WHERE ScenarioCode = :code
              AND StepNo = :step_no
        ");
        $checkpointStmt->execute([':code' => $scenarioCode, ':step_no' => $stepNo]);

        return [
            'note' => $noteStmt->fetch(PDO::FETCH_ASSOC) ?: [],
            'checkpoint' => $checkpointStmt->fetch(PDO::FETCH_ASSOC) ?: [],
        ];
    }

    public function saveStepSupport(array $data, int $updatedBy): void
    {
        $scenarioCode = trim((string) ($data['ScenarioCode'] ?? ''));
        $stepNo = (int) ($data['StepNo'] ?? 0);
        if ($scenarioCode === '' || $stepNo <= 0 || !$this->supportsStepSupportTables()) {
            return;
        }

        $noteParams = [
            ':code' => $scenarioCode,
            ':step_no' => $stepNo,
            ':trainer_note' => $this->nullIfEmpty($data['TrainerNote'] ?? null),
            ':expected_outcome' => $this->nullIfEmpty($data['ExpectedOutcome'] ?? null),
            ':common_issues' => $this->nullIfEmpty($data['CommonIssues'] ?? null),
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ];
        $noteExists = $this->db->prepare("
            SELECT 1
            FROM dbo.tblTrainingStepInstructorNotes
            WHERE ScenarioCode = :code AND StepNo = :step_no
        ");
        $noteExists->execute([':code' => $scenarioCode, ':step_no' => $stepNo]);
        $hasNote = (bool) $noteExists->fetchColumn();
        if ($hasNote) {
            $stmt = $this->db->prepare("
                UPDATE dbo.tblTrainingStepInstructorNotes
                SET TrainerNote = :trainer_note,
                    ExpectedOutcome = :expected_outcome,
                    CommonIssues = :common_issues,
                    UpdatedBy = :updated_by,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE ScenarioCode = :code
                  AND StepNo = :step_no
            ");
        } else {
            $stmt = $this->db->prepare("
                INSERT INTO dbo.tblTrainingStepInstructorNotes
                    (ScenarioCode, StepNo, TrainerNote, ExpectedOutcome, CommonIssues, CreatedBy, UpdatedBy)
                VALUES (:code, :step_no, :trainer_note, :expected_outcome, :common_issues, :created_by, :updated_by)
            ");
        }
        $stmt->execute($noteParams + ($hasNote ? [] : [':created_by' => $updatedBy > 0 ? $updatedBy : null]));

        $checkpointParams = [
            ':code' => $scenarioCode,
            ':step_no' => $stepNo,
            ':question_text' => $this->nullIfEmpty($data['QuestionText'] ?? null),
            ':expected_answer' => $this->nullIfEmpty($data['ExpectedAnswer'] ?? null),
            ':required_flag' => !empty($data['CheckpointRequired']) ? 1 : 0,
            ':active_flag' => !empty($data['CheckpointActive']) ? 1 : 0,
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ];
        $checkpointExists = $this->db->prepare("
            SELECT 1
            FROM dbo.tblTrainingStepCheckpoints
            WHERE ScenarioCode = :code AND StepNo = :step_no
        ");
        $checkpointExists->execute([':code' => $scenarioCode, ':step_no' => $stepNo]);
        $hasCheckpoint = (bool) $checkpointExists->fetchColumn();
        if ($hasCheckpoint) {
            $checkpointStmt = $this->db->prepare("
                UPDATE dbo.tblTrainingStepCheckpoints
                SET QuestionText = :question_text,
                    ExpectedAnswer = :expected_answer,
                    RequiredFlag = :required_flag,
                    ActiveFlag = :active_flag,
                    UpdatedBy = :updated_by,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE ScenarioCode = :code
                  AND StepNo = :step_no
            ");
        } else {
            $checkpointStmt = $this->db->prepare("
                INSERT INTO dbo.tblTrainingStepCheckpoints
                    (ScenarioCode, StepNo, QuestionText, ExpectedAnswer, RequiredFlag, ActiveFlag, CreatedBy, UpdatedBy)
                VALUES (:code, :step_no, :question_text, :expected_answer, :required_flag, :active_flag, :created_by, :updated_by)
            ");
        }
        $checkpointStmt->execute($checkpointParams + ($hasCheckpoint ? [] : [':created_by' => $updatedBy > 0 ? $updatedBy : null]));
    }

    public function validateCatalog(array $routes, string $viewRoot): array
    {
        $scenarioRows = $this->db->query("
            SELECT ScenarioCode, ScenarioTitle, RunnerRoute, ActiveFlag
            FROM dbo.tblTrainingScenarios
            ORDER BY SortOrder, ScenarioCode
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $steps = $this->db->query("
            SELECT ScenarioCode, StepNo, Route, TargetElementID, CompletionMode, SampleKey, ExpectedUserSampleKey, ActiveFlag
            FROM dbo.tblTrainingScenarioSteps
            ORDER BY ScenarioCode, StepNo
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $samplesByScenario = [];
        foreach ($this->db->query("SELECT ScenarioCode, SampleKey FROM dbo.tblTrainingScenarioSamples")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $sample) {
            $samplesByScenario[(string) $sample['ScenarioCode']][(string) $sample['SampleKey']] = true;
        }

        $knownTargets = $this->scanKnownElementIds($viewRoot);
        $validModes = [
            'navigation' => true,
            'field_nonempty' => true,
            'field_email' => true,
            'field_prefilled' => true,
            'field_matches_sample' => true,
            'checkbox_checked' => true,
            'manual_continue' => true,
            'submit_success' => true,
            'click_target' => true,
        ];
        $routeMap = array_fill_keys(array_keys($routes), true);

        $findings = [];
        foreach ($scenarioRows as $scenario) {
            $code = (string) ($scenario['ScenarioCode'] ?? '');
            $runnerRoute = trim((string) ($scenario['RunnerRoute'] ?? ''));
            if ($runnerRoute === '' || !isset($routeMap[$runnerRoute])) {
                $findings[] = $this->finding($code, null, 'error', 'Runner route is not registered.', $runnerRoute);
            }
        }

        $seenSteps = [];
        foreach ($steps as $step) {
            $scenarioCode = (string) ($step['ScenarioCode'] ?? '');
            $stepNo = (int) ($step['StepNo'] ?? 0);
            $stepKey = $scenarioCode . ':' . $stepNo;
            if (isset($seenSteps[$stepKey])) {
                $findings[] = $this->finding($scenarioCode, $stepNo, 'error', 'Duplicate step number.', (string) $stepNo);
            }
            $seenSteps[$stepKey] = true;

            $route = trim((string) ($step['Route'] ?? ''));
            if ($route === '' || !isset($routeMap[$route])) {
                $findings[] = $this->finding($scenarioCode, $stepNo, 'error', 'Step route is not registered.', $route);
            }

            $mode = trim((string) ($step['CompletionMode'] ?? ''));
            if ($mode === '' || !isset($validModes[$mode])) {
                $findings[] = $this->finding($scenarioCode, $stepNo, 'error', 'Completion mode is not supported.', $mode);
            }

            $target = trim((string) ($step['TargetElementID'] ?? ''));
            if ($target !== '' && !isset($knownTargets[$target])) {
                $findings[] = $this->finding($scenarioCode, $stepNo, 'warning', 'Target element ID was not found in checked view files.', $target);
            }

            foreach (['SampleKey', 'ExpectedUserSampleKey'] as $field) {
                $sampleKey = trim((string) ($step[$field] ?? ''));
                if ($sampleKey !== '' && !isset($samplesByScenario[$scenarioCode][$sampleKey])) {
                    $findings[] = $this->finding($scenarioCode, $stepNo, 'warning', $field . ' does not exist in scenario samples.', $sampleKey);
                }
            }
        }

        return $findings;
    }

    public function recordStuckEvent(int $userId, array $data): void
    {
        if ($userId <= 0 || !$this->supportsManagementTables()) {
            return;
        }

        $scenarioCode = trim((string) ($data['ScenarioCode'] ?? ''));
        $stepNo = (int) ($data['StepNo'] ?? 0);
        if ($scenarioCode === '' || $stepNo <= 0) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblTrainingStuckEvents
                (UserID, ScenarioCode, StepNo, Route, TargetElementID, Message)
            VALUES
                (:user_id, :scenario_code, :step_no, :route, :target, :message)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':scenario_code' => $scenarioCode,
            ':step_no' => $stepNo,
            ':route' => $this->nullIfEmpty($data['Route'] ?? null),
            ':target' => $this->nullIfEmpty($data['TargetElementID'] ?? null),
            ':message' => $this->nullIfEmpty($data['Message'] ?? null),
        ]);
    }

    public function listStuckEvents(string $status = 'open'): array
    {
        if (!$this->supportsManagementTables()) {
            return [];
        }

        $where = '1=1';
        $params = [];
        if ($status !== '') {
            $where = 'e.Status = :status';
            $params[':status'] = $status;
        }

        return $this->prepareFetchAll("
            SELECT
                e.*,
                u.Username,
                u.DisplayName,
                s.ScenarioTitle
            FROM dbo.tblTrainingStuckEvents e
            LEFT JOIN dbo.tblUsers u ON u.UserID = e.UserID
            LEFT JOIN dbo.tblTrainingScenarios s ON s.ScenarioCode = e.ScenarioCode
            WHERE {$where}
            ORDER BY e.CreatedAt DESC
        ", $params);
    }

    public function resolveStuckEvent(int $eventId, string $note, int $updatedBy): void
    {
        $stmt = $this->db->prepare("
            UPDATE dbo.tblTrainingStuckEvents
            SET Status = N'resolved',
                ResolvedBy = :resolved_by,
                ResolvedAt = SYSUTCDATETIME(),
                ResolutionNote = :note
            WHERE TrainingStuckEventID = :event_id
        ");
        $stmt->execute([
            ':event_id' => $eventId,
            ':resolved_by' => $updatedBy > 0 ? $updatedBy : null,
            ':note' => $this->nullIfEmpty($note),
        ]);
    }

    public function saveEvidence(array $data, int $updatedBy): void
    {
        $userId = (int) ($data['UserID'] ?? 0);
        $scenarioCode = trim((string) ($data['ScenarioCode'] ?? ''));
        if ($userId <= 0 || $scenarioCode === '') {
            throw new \RuntimeException('User and scenario are required for evidence.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblTrainingEvidence
                (TrainingAttemptID, TrainingSessionID, UserID, ScenarioCode, AttemptNo, EvidenceType, EvidenceNote, EvidenceBy, CreatedBy)
            SELECT
                :attempt_id,
                :session_id,
                :user_id,
                :scenario_code,
                :attempt_no,
                :evidence_type,
                :evidence_note,
                :evidence_by,
                :created_by
        ");
        $stmt->execute([
            ':attempt_id' => $this->intOrNull($data['TrainingAttemptID'] ?? null),
            ':session_id' => $this->intOrNull($data['TrainingSessionID'] ?? null),
            ':user_id' => $userId,
            ':scenario_code' => $scenarioCode,
            ':attempt_no' => $this->intOrNull($data['AttemptNo'] ?? null),
            ':evidence_type' => trim((string) ($data['EvidenceType'] ?? 'instructor_signoff')) ?: 'instructor_signoff',
            ':evidence_note' => $this->nullIfEmpty($data['EvidenceNote'] ?? null),
            ':evidence_by' => $updatedBy > 0 ? $updatedBy : null,
            ':created_by' => $updatedBy > 0 ? $updatedBy : null,
        ]);
    }

    public function listCleanupTags(): array
    {
        if (!$this->supportsManagementTables()) {
            return [];
        }

        return $this->db->query("
            SELECT TOP 200 *
            FROM dbo.tblTrainingDataTags
            ORDER BY CreatedAt DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function syncPathScenarios(string $pathCode, string $scenarioCodesText, int $updatedBy): void
    {
        $codes = $this->parseCodeLines($scenarioCodesText);
        $this->db->prepare("DELETE FROM dbo.tblTrainingPathScenarios WHERE PathCode = :code")
            ->execute([':code' => $pathCode]);

        $stmt = $this->db->prepare("
            INSERT INTO dbo.tblTrainingPathScenarios
                (PathCode, ScenarioCode, RequiredFlag, SortOrder, CreatedBy, UpdatedBy)
            VALUES
                (:path_code, :scenario_code, 1, :sort_order, :created_by, :updated_by)
        ");

        $auditUserId = $updatedBy > 0 ? $updatedBy : null;
        foreach ($codes as $index => $scenarioCode) {
            $stmt->execute([
                ':path_code' => $pathCode,
                ':scenario_code' => $scenarioCode,
                ':sort_order' => ($index + 1) * 10,
                ':created_by' => $auditUserId,
                ':updated_by' => $auditUserId,
            ]);
        }
    }

    private function syncSessionUsers(int $sessionId, string $userIdsText, int $updatedBy): void
    {
        if ($sessionId <= 0) {
            return;
        }

        $userIds = $this->parseIntList($userIdsText);
        if ($userIds === []) {
            return;
        }

        $existsStmt = $this->db->prepare("
            SELECT 1
            FROM dbo.tblTrainingSessionUsers
            WHERE TrainingSessionID = :session_id
              AND UserID = :user_id
        ");
        $updateStmt = $this->db->prepare("
            UPDATE dbo.tblTrainingSessionUsers
            SET UpdatedBy = :updated_by,
                UpdatedDate = SYSUTCDATETIME()
            WHERE TrainingSessionID = :session_id
              AND UserID = :user_id
        ");
        $insertStmt = $this->db->prepare("
            INSERT INTO dbo.tblTrainingSessionUsers
                (TrainingSessionID, UserID, Status, CreatedBy, UpdatedBy)
            VALUES
                (:session_id, :user_id, N'assigned', :created_by, :updated_by)
        ");
        $auditUserId = $updatedBy > 0 ? $updatedBy : null;
        foreach ($userIds as $userId) {
            $params = [
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':updated_by' => $auditUserId,
            ];
            $existsStmt->execute([':session_id' => $sessionId, ':user_id' => $userId]);
            if ($existsStmt->fetchColumn()) {
                $updateStmt->execute($params);
            } else {
                $insertStmt->execute($params + [':created_by' => $auditUserId]);
            }
        }
    }

    private function sessionIdForCode(string $sessionCode): int
    {
        $stmt = $this->db->prepare("SELECT TrainingSessionID FROM dbo.tblTrainingSessions WHERE SessionCode = :code");
        $stmt->execute([':code' => trim($sessionCode)]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function generateSessionCode(): string
    {
        $prefix = 'TRN-' . gmdate('Ymd-His');
        for ($index = 1; $index <= 99; $index++) {
            $code = $prefix . '-' . str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            if ($this->sessionIdForCode($code) <= 0) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate a unique session code. Please try again.');
    }

    private function scanKnownElementIds(string $viewRoot): array
    {
        $ids = [];
        if (!is_dir($viewRoot)) {
            return $ids;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewRoot));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            if (preg_match_all('/\bid\s*=\s*["\']([^"\']+)["\']/', $contents, $matches)) {
                foreach ($matches[1] as $id) {
                    $ids[(string) $id] = true;
                }
            }
            if (preg_match_all('/["\']([A-Za-z][A-Za-z0-9_-]{2,})["\']/', $contents, $quotedMatches)) {
                foreach ($quotedMatches[1] as $id) {
                    $ids[(string) $id] = true;
                }
            }
        }

        return $ids;
    }

    private function finding(string $scenarioCode, ?int $stepNo, string $severity, string $message, string $detail = ''): array
    {
        return [
            'ScenarioCode' => $scenarioCode,
            'StepNo' => $stepNo,
            'Severity' => $severity,
            'Message' => $message,
            'Detail' => $detail,
        ];
    }

    private function prepareFetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function parseCodeLines(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n|,/', $text) ?: [];
        return array_values(array_unique(array_filter(array_map(
            static fn(string $line): string => trim($line),
            $lines
        ), static fn(string $line): bool => $line !== '')));
    }

    private function parseIntList(string $text): array
    {
        $parts = preg_split('/\D+/', $text) ?: [];
        return array_values(array_unique(array_filter(array_map(
            static fn(string $part): int => (int) $part,
            $parts
        ), static fn(int $value): bool => $value > 0)));
    }

    private function intOrNull(mixed $value): ?int
    {
        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeSqlDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace('T', ' ', $value);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
            throw new \RuntimeException('Scheduled Date/Time must be a valid date and time.');
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $hour = (int) $matches[4];
        $minute = (int) $matches[5];
        $second = isset($matches[6]) ? (int) $matches[6] : 0;

        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new \RuntimeException('Scheduled Date/Time must be a valid date and time.');
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}
