<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class TrainingCertificationModel
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsCertificationTables(): bool
    {
        $row = $this->pdo->query("
            SELECT
                OBJECT_ID(N'dbo.tblTrainingCertifications', N'U') AS CertificationsTableId,
                OBJECT_ID(N'dbo.tblTrainingCertificationQuestions', N'U') AS QuestionsTableId,
                OBJECT_ID(N'dbo.tblTrainingCertificationAttempts', N'U') AS AttemptsTableId,
                OBJECT_ID(N'dbo.tblTrainingCertificationAnswers', N'U') AS AnswersTableId
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return (int) ($row['CertificationsTableId'] ?? 0) > 0
            && (int) ($row['QuestionsTableId'] ?? 0) > 0
            && (int) ($row['AttemptsTableId'] ?? 0) > 0
            && (int) ($row['AnswersTableId'] ?? 0) > 0;
    }

    public function listCertifications(array $filters = [], ?int $userId = null): array
    {
        $where = [];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(
                c.CertificationCode LIKE :q_code
                OR c.CertificationTitle LIKE :q_title
                OR c.ModuleName LIKE :q_module
                OR ISNULL(c.Description, N\'\') LIKE :q_description
            )';
            $like = '%' . $q . '%';
            $params[':q_code'] = $like;
            $params[':q_title'] = $like;
            $params[':q_module'] = $like;
            $params[':q_description'] = $like;
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where[] = 'c.ModuleName = :module';
            $params[':module'] = $module;
        }

        $active = trim((string) ($filters['active'] ?? ''));
        if ($active === '1' || $active === '0') {
            $where[] = 'c.ActiveFlag = :active';
            $params[':active'] = (int) $active;
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $latestSelect = '';
        $latestJoin = '';
        if ($userId !== null && $userId > 0) {
            $latestSelect = ',
                latest.AttemptNo AS LatestAttemptNo,
                latest.Status AS LatestStatus,
                latest.ScorePercent AS LatestScorePercent,
                latest.PassedFlag AS LatestPassedFlag,
                latest.SubmittedAt AS LatestSubmittedAt';
            $latestJoin = "
                OUTER APPLY (
                    SELECT TOP 1 a.AttemptNo, a.Status, a.ScorePercent, a.PassedFlag, a.SubmittedAt
                    FROM dbo.tblTrainingCertificationAttempts a
                    WHERE a.CertificationCode = c.CertificationCode
                      AND a.UserID = :latest_user_id
                    ORDER BY a.AttemptNo DESC, a.TrainingCertificationAttemptID DESC
                ) latest";
            $params[':latest_user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare("
            SELECT
                c.TrainingCertificationID,
                c.CertificationCode,
                c.CertificationTitle,
                c.ModuleName,
                c.Description,
                c.PassPercent,
                c.ActiveFlag,
                c.SortOrder,
                c.UpdatedDate,
                QuestionCount = (
                    SELECT COUNT(*)
                    FROM dbo.tblTrainingCertificationQuestions q
                    WHERE q.CertificationCode = c.CertificationCode
                      AND q.ActiveFlag = 1
                )
                {$latestSelect}
            FROM dbo.tblTrainingCertifications c
            {$latestJoin}
            {$whereSql}
            ORDER BY c.SortOrder, c.ModuleName, c.CertificationTitle, c.CertificationCode
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listModuleOptions(): array
    {
        $stmt = $this->pdo->query("
            SELECT DISTINCT ModuleName
            FROM dbo.tblTrainingCertifications
            WHERE NULLIF(LTRIM(RTRIM(ModuleName)), N'') IS NOT NULL
            ORDER BY ModuleName
        ");

        return array_map(
            static fn(array $row): string => (string) ($row['ModuleName'] ?? ''),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    public function getCertification(string $certificationCode): ?array
    {
        $certificationCode = trim($certificationCode);
        if ($certificationCode === '') {
            return null;
        }

        $stmt = $this->pdo->prepare("
            SELECT TrainingCertificationID, CertificationCode, CertificationTitle, ModuleName, Description,
                   PassPercent, ActiveFlag, SortOrder
            FROM dbo.tblTrainingCertifications
            WHERE CertificationCode = :code
        ");
        $stmt->execute([':code' => $certificationCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveCertification(array $data, int $updatedBy): string
    {
        $code = trim((string) ($data['CertificationCode'] ?? ''));
        $title = trim((string) ($data['CertificationTitle'] ?? ''));
        $module = trim((string) ($data['ModuleName'] ?? ''));
        $passPercent = (float) ($data['PassPercent'] ?? 80);

        if ($code === '' || $title === '' || $module === '') {
            throw new \RuntimeException('Certification code, title, and module are required.');
        }
        if ($passPercent < 0 || $passPercent > 100) {
            throw new \RuntimeException('Pass percent must be between 0 and 100.');
        }

        $exists = $this->getCertification($code) !== null;
        $params = [
            ':code' => $code,
            ':title' => $title,
            ':module' => $module,
            ':description' => $this->nullIfEmpty($data['Description'] ?? null),
            ':pass_percent' => $passPercent,
            ':active_flag' => !empty($data['ActiveFlag']) ? 1 : 0,
            ':sort_order' => max(0, (int) ($data['SortOrder'] ?? 0)),
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ];

        if ($exists) {
            $sql = "
                UPDATE dbo.tblTrainingCertifications
                SET CertificationTitle = :title,
                    ModuleName = :module,
                    Description = :description,
                    PassPercent = :pass_percent,
                    ActiveFlag = :active_flag,
                    SortOrder = :sort_order,
                    UpdatedBy = :updated_by,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE CertificationCode = :code
            ";
        } else {
            $params[':created_by'] = $updatedBy > 0 ? $updatedBy : null;
            $sql = "
                INSERT INTO dbo.tblTrainingCertifications
                    (CertificationCode, CertificationTitle, ModuleName, Description, PassPercent,
                     ActiveFlag, SortOrder, CreatedBy, UpdatedBy)
                VALUES
                    (:code, :title, :module, :description, :pass_percent,
                     :active_flag, :sort_order, :created_by, :updated_by)
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $code;
    }

    public function listQuestions(string $certificationCode, bool $activeOnly = false): array
    {
        $certificationCode = trim($certificationCode);
        if ($certificationCode === '') {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT TrainingCertificationQuestionID, CertificationCode, QuestionNo, QuestionText,
                   OptionsJson, CorrectOptionKey, Explanation, ActiveFlag, SortOrder, UpdatedDate
            FROM dbo.tblTrainingCertificationQuestions
            WHERE CertificationCode = :code
              " . ($activeOnly ? "AND ActiveFlag = 1" : "") . "
            ORDER BY SortOrder, QuestionNo
        ");
        $stmt->execute([':code' => $certificationCode]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $index => $row) {
            $rows[$index]['Options'] = $this->decodeOptions((string) ($row['OptionsJson'] ?? ''));
        }
        return $rows;
    }

    public function getQuestion(string $certificationCode, int $questionNo): ?array
    {
        if (trim($certificationCode) === '' || $questionNo <= 0) {
            return null;
        }
        $stmt = $this->pdo->prepare("
            SELECT TrainingCertificationQuestionID, CertificationCode, QuestionNo, QuestionText,
                   OptionsJson, CorrectOptionKey, Explanation, ActiveFlag, SortOrder
            FROM dbo.tblTrainingCertificationQuestions
            WHERE CertificationCode = :code
              AND QuestionNo = :question_no
        ");
        $stmt->execute([':code' => $certificationCode, ':question_no' => $questionNo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['Options'] = $this->decodeOptions((string) ($row['OptionsJson'] ?? ''));
        return $row;
    }

    public function saveQuestion(array $data, int $updatedBy): int
    {
        $code = trim((string) ($data['CertificationCode'] ?? ''));
        $oldQuestionNo = (int) ($data['OldQuestionNo'] ?? 0);
        $questionNo = (int) ($data['QuestionNo'] ?? 0);
        $questionText = trim((string) ($data['QuestionText'] ?? ''));
        $correct = strtoupper(trim((string) ($data['CorrectOptionKey'] ?? '')));
        $options = $this->normalizeOptions($data['Options'] ?? []);

        if ($code === '' || $questionNo <= 0 || $questionText === '' || $correct === '') {
            throw new \RuntimeException('Certification, question number, question text, and correct option are required.');
        }
        if ($options === []) {
            throw new \RuntimeException('At least one answer option is required.');
        }
        $optionKeys = array_map(static fn(array $option): string => strtoupper((string) ($option['key'] ?? '')), $options);
        if (!in_array($correct, $optionKeys, true)) {
            throw new \RuntimeException('Correct option must match one of the option keys.');
        }

        $existing = $this->getQuestion($code, $oldQuestionNo > 0 ? $oldQuestionNo : $questionNo);
        $params = [
            ':code' => $code,
            ':old_question_no' => $oldQuestionNo > 0 ? $oldQuestionNo : $questionNo,
            ':question_no' => $questionNo,
            ':question_text' => $questionText,
            ':options_json' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':correct' => $correct,
            ':explanation' => $this->nullIfEmpty($data['Explanation'] ?? null),
            ':active_flag' => !empty($data['ActiveFlag']) ? 1 : 0,
            ':sort_order' => max(0, (int) ($data['SortOrder'] ?? $questionNo)),
            ':updated_by' => $updatedBy > 0 ? $updatedBy : null,
        ];

        if ($existing !== null) {
            $sql = "
                UPDATE dbo.tblTrainingCertificationQuestions
                SET QuestionNo = :question_no,
                    QuestionText = :question_text,
                    OptionsJson = :options_json,
                    CorrectOptionKey = :correct,
                    Explanation = :explanation,
                    ActiveFlag = :active_flag,
                    SortOrder = :sort_order,
                    UpdatedBy = :updated_by,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE CertificationCode = :code
                  AND QuestionNo = :old_question_no
            ";
        } else {
            $params[':created_by'] = $updatedBy > 0 ? $updatedBy : null;
            $sql = "
                INSERT INTO dbo.tblTrainingCertificationQuestions
                    (CertificationCode, QuestionNo, QuestionText, OptionsJson, CorrectOptionKey,
                     Explanation, ActiveFlag, SortOrder, CreatedBy, UpdatedBy)
                VALUES
                    (:code, :question_no, :question_text, :options_json, :correct,
                     :explanation, :active_flag, :sort_order, :created_by, :updated_by)
            ";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $questionNo;
    }

    public function startAttempt(string $certificationCode, int $userId): int
    {
        $certification = $this->getCertification($certificationCode);
        if ($certification === null || (int) ($certification['ActiveFlag'] ?? 0) !== 1) {
            throw new \RuntimeException('Certification is not available.');
        }
        $questionCount = count($this->listQuestions($certificationCode, true));
        if ($questionCount <= 0) {
            throw new \RuntimeException('Certification has no active questions.');
        }

        $stmt = $this->pdo->prepare("
            SELECT ISNULL(MAX(AttemptNo), 0) + 1
            FROM dbo.tblTrainingCertificationAttempts
            WHERE UserID = :user_id
              AND CertificationCode = :code
        ");
        $stmt->execute([':user_id' => $userId, ':code' => $certificationCode]);
        $attemptNo = (int) ($stmt->fetchColumn() ?: 1);

        $insert = $this->pdo->prepare("
            INSERT INTO dbo.tblTrainingCertificationAttempts
                (UserID, CertificationCode, AttemptNo, Status, QuestionCount, PassPercent, CreatedBy, UpdatedBy)
            OUTPUT INSERTED.TrainingCertificationAttemptID
            VALUES
                (:user_id, :code, :attempt_no, N'in_progress', :question_count, :pass_percent, :user_id_created, :user_id_updated)
        ");
        $insert->execute([
            ':user_id' => $userId,
            ':code' => $certificationCode,
            ':attempt_no' => $attemptNo,
            ':question_count' => $questionCount,
            ':pass_percent' => (float) ($certification['PassPercent'] ?? 80),
            ':user_id_created' => $userId,
            ':user_id_updated' => $userId,
        ]);

        return (int) ($insert->fetchColumn() ?: 0);
    }

    public function getAttempt(int $attemptId, ?int $userId = null): ?array
    {
        if ($attemptId <= 0) {
            return null;
        }
        $where = 'a.TrainingCertificationAttemptID = :attempt_id';
        $params = [':attempt_id' => $attemptId];
        if ($userId !== null && $userId > 0) {
            $where .= ' AND a.UserID = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $this->pdo->prepare("
            SELECT a.*, c.CertificationTitle, c.ModuleName, c.Description,
                   u.Username, u.Email, LTRIM(RTRIM(u.DisplayName)) AS DisplayName
            FROM dbo.tblTrainingCertificationAttempts a
            JOIN dbo.tblTrainingCertifications c ON c.CertificationCode = a.CertificationCode
            LEFT JOIN dbo.tblUsers u ON u.UserID = a.UserID
            WHERE {$where}
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function submitAttempt(int $attemptId, int $userId, array $answers): array
    {
        $attempt = $this->getAttempt($attemptId, $userId);
        if ($attempt === null) {
            throw new \RuntimeException('Certification attempt was not found.');
        }
        if ((string) ($attempt['Status'] ?? '') === 'submitted') {
            return $attempt;
        }

        $questions = $this->listQuestions((string) ($attempt['CertificationCode'] ?? ''), true);
        if ($questions === []) {
            throw new \RuntimeException('Certification questions are not available.');
        }

        $missing = [];
        foreach ($questions as $question) {
            $questionId = (int) ($question['TrainingCertificationQuestionID'] ?? 0);
            if (trim((string) ($answers[$questionId] ?? '')) === '') {
                $missing[] = (int) ($question['QuestionNo'] ?? 0);
            }
        }
        if ($missing !== []) {
            throw new \RuntimeException('Answer every question before submitting.');
        }

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare("
                DELETE FROM dbo.tblTrainingCertificationAnswers
                WHERE TrainingCertificationAttemptID = :attempt_id
            ");
            $delete->execute([':attempt_id' => $attemptId]);

            $insert = $this->pdo->prepare("
                INSERT INTO dbo.tblTrainingCertificationAnswers
                    (TrainingCertificationAttemptID, TrainingCertificationQuestionID, QuestionNo,
                     SelectedOptionKey, CorrectOptionKey, CorrectFlag)
                VALUES
                    (:attempt_id, :question_id, :question_no, :selected, :correct, :correct_flag)
            ");

            $correctCount = 0;
            foreach ($questions as $question) {
                $questionId = (int) ($question['TrainingCertificationQuestionID'] ?? 0);
                $selected = strtoupper(trim((string) ($answers[$questionId] ?? '')));
                $correct = strtoupper(trim((string) ($question['CorrectOptionKey'] ?? '')));
                $isCorrect = $selected !== '' && $selected === $correct;
                if ($isCorrect) {
                    $correctCount++;
                }
                $insert->execute([
                    ':attempt_id' => $attemptId,
                    ':question_id' => $questionId,
                    ':question_no' => (int) ($question['QuestionNo'] ?? 0),
                    ':selected' => $selected,
                    ':correct' => $correct,
                    ':correct_flag' => $isCorrect ? 1 : 0,
                ]);
            }

            $questionCount = count($questions);
            $score = $questionCount > 0 ? round(($correctCount / $questionCount) * 100, 2) : 0.0;
            $passPercent = (float) ($attempt['PassPercent'] ?? 80);
            $passed = $score >= $passPercent;

            $update = $this->pdo->prepare("
                UPDATE dbo.tblTrainingCertificationAttempts
                SET Status = N'submitted',
                    QuestionCount = :question_count,
                    CorrectCount = :correct_count,
                    ScorePercent = :score_percent,
                    PassedFlag = :passed,
                    SubmittedAt = SYSUTCDATETIME(),
                    UpdatedBy = :user_id,
                    UpdatedDate = SYSUTCDATETIME()
                WHERE TrainingCertificationAttemptID = :attempt_id
            ");
            $update->execute([
                ':attempt_id' => $attemptId,
                ':question_count' => $questionCount,
                ':correct_count' => $correctCount,
                ':score_percent' => $score,
                ':passed' => $passed ? 1 : 0,
                ':user_id' => $userId,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->getAttempt($attemptId, $userId) ?? [];
    }

    public function listAttemptAnswers(int $attemptId): array
    {
        if ($attemptId <= 0) {
            return [];
        }
        $stmt = $this->pdo->prepare("
            SELECT ans.*, q.QuestionText, q.OptionsJson, q.Explanation
            FROM dbo.tblTrainingCertificationAnswers ans
            JOIN dbo.tblTrainingCertificationQuestions q
              ON q.TrainingCertificationQuestionID = ans.TrainingCertificationQuestionID
            WHERE ans.TrainingCertificationAttemptID = :attempt_id
            ORDER BY ans.QuestionNo
        ");
        $stmt->execute([':attempt_id' => $attemptId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $index => $row) {
            $rows[$index]['Options'] = $this->decodeOptions((string) ($row['OptionsJson'] ?? ''));
        }
        return $rows;
    }

    public function listResults(array $filters = []): array
    {
        $where = ['a.Status = N\'submitted\''];
        $params = [];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(
                u.Username LIKE :q_username
                OR u.DisplayName LIKE :q_display
                OR u.Email LIKE :q_email
                OR c.CertificationTitle LIKE :q_title
                OR c.CertificationCode LIKE :q_code
            )';
            $like = '%' . $q . '%';
            $params[':q_username'] = $like;
            $params[':q_display'] = $like;
            $params[':q_email'] = $like;
            $params[':q_title'] = $like;
            $params[':q_code'] = $like;
        }

        $module = trim((string) ($filters['module'] ?? ''));
        if ($module !== '') {
            $where[] = 'c.ModuleName = :module';
            $params[':module'] = $module;
        }

        $code = trim((string) ($filters['certification_code'] ?? ''));
        if ($code !== '') {
            $where[] = 'a.CertificationCode = :certification_code';
            $params[':certification_code'] = $code;
        }

        $result = trim((string) ($filters['result'] ?? ''));
        if ($result === 'passed' || $result === 'failed') {
            $where[] = 'a.PassedFlag = :passed';
            $params[':passed'] = $result === 'passed' ? 1 : 0;
        }

        $stmt = $this->pdo->prepare("
            SELECT TOP 500
                a.TrainingCertificationAttemptID,
                a.UserID,
                a.CertificationCode,
                a.AttemptNo,
                a.QuestionCount,
                a.CorrectCount,
                a.ScorePercent,
                a.PassPercent,
                a.PassedFlag,
                a.StartedAt,
                a.SubmittedAt,
                c.CertificationTitle,
                c.ModuleName,
                u.Username,
                u.Email,
                LTRIM(RTRIM(u.DisplayName)) AS DisplayName
            FROM dbo.tblTrainingCertificationAttempts a
            JOIN dbo.tblTrainingCertifications c ON c.CertificationCode = a.CertificationCode
            LEFT JOIN dbo.tblUsers u ON u.UserID = a.UserID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.SubmittedAt DESC, a.TrainingCertificationAttemptID DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizeOptions(mixed $value): array
    {
        $rows = [];
        if (is_string($value)) {
            $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                if (str_contains($line, '=')) {
                    [$key, $label] = array_map('trim', explode('=', $line, 2));
                } else {
                    $key = chr(65 + count($rows));
                    $label = $line;
                }
                $key = strtoupper(trim($key));
                if ($key !== '' && $label !== '') {
                    $rows[] = ['key' => $key, 'label' => $label];
                }
            }
        } elseif (is_array($value)) {
            foreach ($value as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $key = strtoupper(trim((string) ($option['key'] ?? '')));
                $label = trim((string) ($option['label'] ?? ''));
                if ($key !== '' && $label !== '') {
                    $rows[] = ['key' => $key, 'label' => $label];
                }
            }
        }
        return $rows;
    }

    private function decodeOptions(string $json): array
    {
        $decoded = json_decode($json, true);
        return $this->normalizeOptions(is_array($decoded) ? $decoded : []);
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
