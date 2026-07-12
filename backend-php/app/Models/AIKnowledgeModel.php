<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class AIKnowledgeModel
{
    public function __construct(private PDO $conn)
    {
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function supportsAIKnowledge(): bool
    {
        return $this->tableExists('dbo.tblAIKnowledgeDocuments')
            && $this->tableExists('dbo.tblAIKnowledgeChunks')
            && $this->tableExists('dbo.tblAIQuestions');
    }

    public function summary(): array
    {
        if (!$this->supportsAIKnowledge()) {
            return [
                'document_count' => 0,
                'chunk_count' => 0,
                'question_count_7d' => 0,
                'latest_question_at' => null,
                'token_count_7d' => 0,
                'avg_response_ms_7d' => 0,
                'provider_error_count_7d' => 0,
            ];
        }

        $docCount = (int) ($this->conn->query("SELECT COUNT(1) FROM dbo.tblAIKnowledgeDocuments WHERE IsActive = 1")->fetchColumn() ?: 0);
        $chunkCount = (int) ($this->conn->query("SELECT COUNT(1) FROM dbo.tblAIKnowledgeChunks WHERE IsActive = 1")->fetchColumn() ?: 0);
        $question = $this->conn->query("
            SELECT
                COUNT(1) AS QuestionCount7d,
                MAX(CreatedDate) AS LatestQuestionAt,
                SUM(ISNULL(TotalTokens, 0)) AS TokenCount7d,
                AVG(CAST(ISNULL(ResponseTimeMs, 0) AS FLOAT)) AS AvgResponseMs7d,
                SUM(CASE WHEN Response LIKE N'I found relevant approved CBMS documentation, but the AI provider is not currently available.%' THEN 1 ELSE 0 END) AS ProviderErrorCount7d
            FROM dbo.tblAIQuestions
            WHERE CreatedDate >= DATEADD(DAY, -7, SYSUTCDATETIME())
        ")->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'document_count' => $docCount,
            'chunk_count' => $chunkCount,
            'question_count_7d' => (int) ($question['QuestionCount7d'] ?? 0),
            'latest_question_at' => $question['LatestQuestionAt'] ?? null,
            'token_count_7d' => (int) ($question['TokenCount7d'] ?? 0),
            'avg_response_ms_7d' => (int) ($question['AvgResponseMs7d'] ?? 0),
            'provider_error_count_7d' => (int) ($question['ProviderErrorCount7d'] ?? 0),
        ];
    }

    public function listDocuments(array $filters = []): array
    {
        if (!$this->tableExists('dbo.tblAIKnowledgeDocuments')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (($filters['active'] ?? '') !== '') {
            $where[] = 'd.IsActive = :active';
            $params[':active'] = ((string) $filters['active'] === '1') ? 1 : 0;
        }
        if (trim((string) ($filters['q'] ?? '')) !== '') {
            $where[] = '(d.Title LIKE :q OR d.FileName LIKE :q OR d.Category LIKE :q OR d.Module LIKE :q)';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $stmt = $this->conn->prepare("
            SELECT
                d.*,
                (SELECT COUNT(1) FROM dbo.tblAIKnowledgeChunks c WHERE c.DocumentID = d.DocumentID AND c.IsActive = 1) AS ChunkCount
            FROM dbo.tblAIKnowledgeDocuments d
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.UploadedDate DESC, d.DocumentID DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDocument(int $documentId): ?array
    {
        if ($documentId <= 0 || !$this->tableExists('dbo.tblAIKnowledgeDocuments')) {
            return null;
        }

        $stmt = $this->conn->prepare("SELECT * FROM dbo.tblAIKnowledgeDocuments WHERE DocumentID = :id");
        $stmt->execute([':id' => $documentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getActiveDocumentByFileName(string $fileName): ?array
    {
        $fileName = trim($fileName);
        if ($fileName === '' || !$this->tableExists('dbo.tblAIKnowledgeDocuments')) {
            return null;
        }

        $stmt = $this->conn->prepare("
            SELECT TOP 1 *
            FROM dbo.tblAIKnowledgeDocuments
            WHERE FileName = :fileName
              AND IsActive = 1
            ORDER BY DocumentID DESC
        ");
        $stmt->execute([':fileName' => $fileName]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function listChunks(int $documentId): array
    {
        if ($documentId <= 0 || !$this->tableExists('dbo.tblAIKnowledgeChunks')) {
            return [];
        }

        $stmt = $this->conn->prepare("
            SELECT *
            FROM dbo.tblAIKnowledgeChunks
            WHERE DocumentID = :id
            ORDER BY ChunkNumber ASC
        ");
        $stmt->execute([':id' => $documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveDocumentWithChunks(array $data, string $sourceText, int $userId): int
    {
        $this->requireFoundation();

        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO dbo.tblAIKnowledgeDocuments (
                    Title, Category, Module, AudienceCode, FiscalYearID, VersionID, CountryID,
                    MinistryCode, FileName, FileType, UploadedBy, IsActive, Notes
                )
                VALUES (
                    :Title, :Category, :Module, :AudienceCode, :FiscalYearID, :VersionID, :CountryID,
                    :MinistryCode, :FileName, :FileType, :UploadedBy, :IsActive, :Notes
                )
            ");
            $stmt->execute([
                ':Title' => trim((string) ($data['Title'] ?? '')),
                ':Category' => $this->nullableString($data['Category'] ?? null),
                ':Module' => $this->nullableString($data['Module'] ?? null),
                ':AudienceCode' => $this->normaliseAudience((string) ($data['AudienceCode'] ?? 'USER')),
                ':FiscalYearID' => $this->nullableInt($data['FiscalYearID'] ?? null),
                ':VersionID' => $this->nullableInt($data['VersionID'] ?? null),
                ':CountryID' => $this->nullableInt($data['CountryID'] ?? null),
                ':MinistryCode' => $this->nullableString($data['MinistryCode'] ?? null),
                ':FileName' => $this->nullableString($data['FileName'] ?? null),
                ':FileType' => $this->nullableString($data['FileType'] ?? null),
                ':UploadedBy' => $userId > 0 ? $userId : null,
                ':IsActive' => ((int) ($data['IsActive'] ?? 1) === 1) ? 1 : 0,
                ':Notes' => $this->nullableString($data['Notes'] ?? null),
            ]);

            $documentId = (int) $this->conn->lastInsertId();
            $chunks = $this->buildChunks($sourceText);
            $chunkStmt = $this->conn->prepare("
                INSERT INTO dbo.tblAIKnowledgeChunks (
                    DocumentID, ChunkNumber, ChunkText, SourcePage, Module, FiscalYearID, VersionID, IsActive
                )
                VALUES (
                    :DocumentID, :ChunkNumber, :ChunkText, :SourcePage, :Module, :FiscalYearID, :VersionID, 1
                )
            ");

            foreach ($chunks as $index => $chunkText) {
                $chunkStmt->execute([
                    ':DocumentID' => $documentId,
                    ':ChunkNumber' => $index + 1,
                    ':ChunkText' => $chunkText,
                    ':SourcePage' => null,
                    ':Module' => $this->nullableString($data['Module'] ?? null),
                    ':FiscalYearID' => $this->nullableInt($data['FiscalYearID'] ?? null),
                    ':VersionID' => $this->nullableInt($data['VersionID'] ?? null),
                ]);
            }

            $this->conn->commit();
            return $documentId;
        } catch (\Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function replaceDocumentWithChunks(int $documentId, array $data, string $sourceText, int $userId): int
    {
        $this->requireFoundation();
        $existing = $this->getDocument($documentId);
        if ($existing === null) {
            throw new \RuntimeException('Knowledge document was not found.');
        }

        $this->setDocumentActive($documentId, false);

        $payload = $existing;
        foreach ($data as $key => $value) {
            if ($value !== null && trim((string) $value) !== '') {
                $payload[$key] = $value;
            }
        }

        $payload['Title'] = trim((string) ($data['Title'] ?? $existing['Title'] ?? 'Untitled Knowledge Document'));
        $newFileName = trim((string) ($data['FileName'] ?? ''));
        $newFileType = trim((string) ($data['FileType'] ?? ''));
        $payload['FileName'] = $newFileName !== '' ? $newFileName : trim((string) ($existing['FileName'] ?? ''));
        $payload['FileType'] = $newFileType !== '' ? $newFileType : trim((string) ($existing['FileType'] ?? ''));
        $payload['Notes'] = trim((string) ($data['Notes'] ?? '')) !== ''
            ? trim((string) $data['Notes'])
            : ('Replaces AI knowledge document #' . $documentId);
        $payload['IsActive'] = 1;

        return $this->saveDocumentWithChunks($payload, $sourceText, $userId);
    }

    public function setDocumentActive(int $documentId, bool $active): void
    {
        $this->requireFoundation();
        $stmt = $this->conn->prepare("UPDATE dbo.tblAIKnowledgeDocuments SET IsActive = :active WHERE DocumentID = :id");
        $stmt->execute([':id' => $documentId, ':active' => $active ? 1 : 0]);

        $chunkStmt = $this->conn->prepare("UPDATE dbo.tblAIKnowledgeChunks SET IsActive = :active WHERE DocumentID = :id");
        $chunkStmt->execute([':id' => $documentId, ':active' => $active ? 1 : 0]);
    }

    public function searchChunks(string $question, array $context, bool $includeDeveloper, bool $includeAdmin = false, int $limit = 5): array
    {
        if (!$this->supportsAIKnowledge()) {
            return [];
        }

        $terms = $this->keywords($question);
        if ($terms === []) {
            return [];
        }

        $where = ['d.IsActive = 1', 'c.IsActive = 1'];
        $params = [];

        if ($includeDeveloper) {
            // Developer access includes all document audiences.
        } elseif ($includeAdmin) {
            $where[] = "UPPER(d.AudienceCode) <> N'DEVELOPER'";
        } else {
            $where[] = "UPPER(d.AudienceCode) IN (N'PUBLIC', N'USER')";
        }
        if ((int) ($context['FiscalYearID'] ?? 0) > 0) {
            $where[] = '(d.FiscalYearID IS NULL OR d.FiscalYearID = :fy)';
            $params[':fy'] = (int) $context['FiscalYearID'];
        }
        if ((int) ($context['VersionID'] ?? 0) > 0) {
            $where[] = '(d.VersionID IS NULL OR d.VersionID = :ver)';
            $params[':ver'] = (int) $context['VersionID'];
        }
        if (trim((string) ($context['Module'] ?? '')) !== '') {
            $where[] = '(d.Module IS NULL OR d.Module = :moduleDocument OR c.Module = :moduleChunk)';
            $params[':moduleDocument'] = trim((string) $context['Module']);
            $params[':moduleChunk'] = trim((string) $context['Module']);
        }

        $likeParts = [];
        foreach (array_slice($terms, 0, 8) as $i => $term) {
            $chunkParam = ':termChunk' . $i;
            $titleParam = ':termTitle' . $i;
            $likeParts[] = "(c.ChunkText LIKE {$chunkParam} OR d.Title LIKE {$titleParam})";
            $params[$chunkParam] = '%' . $term . '%';
            $params[$titleParam] = '%' . $term . '%';
        }
        $where[] = '(' . implode(' OR ', $likeParts) . ')';

        $stmt = $this->conn->prepare("
            SELECT TOP 60
                c.ChunkID, c.DocumentID, c.ChunkNumber, c.ChunkText, c.SourcePage,
                d.Title, d.Category, d.Module, d.FiscalYearID, d.VersionID, d.FileName, d.AudienceCode
            FROM dbo.tblAIKnowledgeChunks c
            INNER JOIN dbo.tblAIKnowledgeDocuments d
                ON d.DocumentID = c.DocumentID
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE WHEN d.FiscalYearID = :orderFy THEN 0 ELSE 1 END,
                CASE WHEN d.VersionID = :orderVer THEN 0 ELSE 1 END,
                d.UploadedDate DESC,
                c.ChunkNumber ASC
        ");
        $stmt->execute($params + [
            ':orderFy' => (int) ($context['FiscalYearID'] ?? 0),
            ':orderVer' => (int) ($context['VersionID'] ?? 0),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $haystack = strtolower((string) ($row['Title'] ?? '') . ' ' . (string) ($row['Category'] ?? '') . ' ' . (string) ($row['Module'] ?? '') . ' ' . (string) ($row['ChunkText'] ?? ''));
            $title = strtolower((string) ($row['Title'] ?? ''));
            $category = strtolower((string) ($row['Category'] ?? ''));
            $module = strtolower((string) ($row['Module'] ?? ''));
            $questionLower = strtolower($question);
            $screenLower = strtolower((string) ($context['Screen'] ?? ''));
            $moduleLower = strtolower((string) ($context['Module'] ?? ''));

            $score = 0;
            if ($questionLower !== '' && str_contains($haystack, $questionLower)) {
                $score += 20;
            }
            if ($screenLower !== '' && (str_contains($title, $screenLower) || str_contains($haystack, $screenLower))) {
                $score += 12;
            }
            if ($moduleLower !== '' && ($module === $moduleLower || str_contains($module, $moduleLower) || str_contains($title, $moduleLower))) {
                $score += 8;
            }
            foreach ($terms as $term) {
                $termLower = strtolower($term);
                $score += substr_count($haystack, $termLower);
                if (str_contains($title, $termLower)) {
                    $score += 4;
                }
                if (str_contains($category, $termLower)) {
                    $score += 2;
                }
            }
            $row['_score'] = $score;
        }
        unset($row);

        usort($rows, static fn (array $a, array $b): int => ((int) $b['_score'] <=> (int) $a['_score']) ?: ((int) $a['ChunkID'] <=> (int) $b['ChunkID']));
        return array_slice(array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['_score'] ?? 0) > 0)), 0, max(1, $limit));
    }

    public function logQuestion(array $payload): int
    {
        $this->requireFoundation();
        $stmt = $this->conn->prepare("
            INSERT INTO dbo.tblAIQuestions (
                UserID, Question, Response, DocumentsUsedJson, ResponseTimeMs, ProviderCode, ModelCode,
                PromptTokens, CompletionTokens, TotalTokens, EstimatedCost, ContextJson
            )
            VALUES (
                :UserID, :Question, :Response, :DocumentsUsedJson, :ResponseTimeMs, :ProviderCode, :ModelCode,
                :PromptTokens, :CompletionTokens, :TotalTokens, :EstimatedCost, :ContextJson
            )
        ");
        $stmt->execute([
            ':UserID' => $this->nullableInt($payload['UserID'] ?? null),
            ':Question' => (string) ($payload['Question'] ?? ''),
            ':Response' => $this->nullableString($payload['Response'] ?? null),
            ':DocumentsUsedJson' => $this->nullableJson($payload['DocumentsUsedJson'] ?? null),
            ':ResponseTimeMs' => $this->nullableInt($payload['ResponseTimeMs'] ?? null),
            ':ProviderCode' => $this->nullableString($payload['ProviderCode'] ?? null),
            ':ModelCode' => $this->nullableString($payload['ModelCode'] ?? null),
            ':PromptTokens' => $this->nullableInt($payload['PromptTokens'] ?? null),
            ':CompletionTokens' => $this->nullableInt($payload['CompletionTokens'] ?? null),
            ':TotalTokens' => $this->nullableInt($payload['TotalTokens'] ?? null),
            ':EstimatedCost' => null,
            ':ContextJson' => $this->nullableJson($payload['ContextJson'] ?? null),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function updateFeedback(int $questionId, ?bool $helpful, string $feedback): void
    {
        $this->requireFoundation();
        $stmt = $this->conn->prepare("
            UPDATE dbo.tblAIQuestions
            SET Helpful = :helpful, Feedback = :feedback
            WHERE QuestionID = :id
        ");
        $stmt->execute([
            ':id' => $questionId,
            ':helpful' => $helpful === null ? null : ($helpful ? 1 : 0),
            ':feedback' => $this->nullableString($feedback),
        ]);
    }

    public function recentQuestions(int $limit = 50): array
    {
        if (!$this->tableExists('dbo.tblAIQuestions')) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->conn->prepare("
            SELECT TOP {$limit} *
            FROM dbo.tblAIQuestions
            ORDER BY CreatedDate DESC, QuestionID DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function usageSummary(int $days = 30): array
    {
        if (!$this->tableExists('dbo.tblAIQuestions')) {
            return [
                'daily' => [],
                'by_model' => [],
                'feedback' => [],
            ];
        }

        $days = max(1, min(365, $days));

        $daily = $this->conn->query("
            SELECT
                CAST(CreatedDate AS DATE) AS UsageDate,
                COUNT(1) AS QuestionCount,
                SUM(ISNULL(TotalTokens, 0)) AS TokenCount,
                AVG(CAST(ISNULL(ResponseTimeMs, 0) AS FLOAT)) AS AvgResponseMs
            FROM dbo.tblAIQuestions
            WHERE CreatedDate >= DATEADD(DAY, -{$days}, SYSUTCDATETIME())
            GROUP BY CAST(CreatedDate AS DATE)
            ORDER BY UsageDate DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byModel = $this->conn->query("
            SELECT
                COALESCE(NULLIF(ProviderCode, N''), N'Not Set') AS ProviderCode,
                COALESCE(NULLIF(ModelCode, N''), N'Not Set') AS ModelCode,
                COUNT(1) AS QuestionCount,
                SUM(ISNULL(TotalTokens, 0)) AS TokenCount,
                AVG(CAST(ISNULL(ResponseTimeMs, 0) AS FLOAT)) AS AvgResponseMs
            FROM dbo.tblAIQuestions
            WHERE CreatedDate >= DATEADD(DAY, -{$days}, SYSUTCDATETIME())
            GROUP BY COALESCE(NULLIF(ProviderCode, N''), N'Not Set'), COALESCE(NULLIF(ModelCode, N''), N'Not Set')
            ORDER BY QuestionCount DESC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $feedbackRow = $this->conn->query("
            SELECT
                SUM(CASE WHEN Helpful = 1 THEN 1 ELSE 0 END) AS HelpfulCount,
                SUM(CASE WHEN Helpful = 0 THEN 1 ELSE 0 END) AS NotHelpfulCount,
                SUM(CASE WHEN Helpful IS NULL THEN 1 ELSE 0 END) AS NoFeedbackCount
            FROM dbo.tblAIQuestions
            WHERE CreatedDate >= DATEADD(DAY, -{$days}, SYSUTCDATETIME())
        ")->fetch(PDO::FETCH_ASSOC) ?: [];
        $feedback = [
            ['Helpful' => 1, 'FeedbackCount' => (int) ($feedbackRow['HelpfulCount'] ?? 0)],
            ['Helpful' => 0, 'FeedbackCount' => (int) ($feedbackRow['NotHelpfulCount'] ?? 0)],
            ['Helpful' => null, 'FeedbackCount' => (int) ($feedbackRow['NoFeedbackCount'] ?? 0)],
        ];

        return [
            'daily' => $daily,
            'by_model' => $byModel,
            'feedback' => $feedback,
        ];
    }

    private function buildChunks(string $text): array
    {
        $text = trim(preg_replace("/\r\n|\r/", "\n", strip_tags($text)) ?? '');
        if ($text === '') {
            return [];
        }

        $paragraphs = preg_split("/\n{2,}/", $text) ?: [$text];
        $chunks = [];
        $current = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim(preg_replace('/[ \t]+/', ' ', $paragraph) ?? '');
            if ($paragraph === '') {
                continue;
            }
            if (strlen($current) + strlen($paragraph) > 1800 && $current !== '') {
                $chunks[] = $current;
                $current = '';
            }
            $current .= ($current === '' ? '' : "\n\n") . $paragraph;
        }
        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks !== [] ? $chunks : [$text];
    }

    private function keywords(string $question): array
    {
        $question = strtolower($question);
        $words = preg_split('/[^a-z0-9_]+/', $question) ?: [];
        $stop = array_fill_keys(['the','and','for','with','that','this','what','when','where','why','how','does','can','should','from','into','about','please','unable'], true);
        $terms = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) < 3 || isset($stop[$word])) {
                continue;
            }
            $terms[$word] = true;
        }
        return array_keys($terms);
    }

    private function requireFoundation(): void
    {
        if (!$this->supportsAIKnowledge()) {
            throw new \RuntimeException('AI knowledge assistant schema is not installed.');
        }
    }

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->conn->prepare("SELECT OBJECT_ID(:tableName, 'U')");
        $stmt->execute([':tableName' => $tableName]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
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

    private function nullableJson(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        json_decode($text, true);
        return json_last_error() === JSON_ERROR_NONE ? $text : null;
    }

    private function normaliseAudience(string $audience): string
    {
        $audience = strtoupper(trim($audience));
        return in_array($audience, ['PUBLIC', 'USER', 'ADMIN', 'DEVELOPER'], true) ? $audience : 'USER';
    }
}
