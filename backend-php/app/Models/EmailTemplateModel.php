<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class EmailTemplateModel
{
    private string $lastError = '';

    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function supportsEmailTemplates(): bool
    {
        try {
            return (int)($this->db->query("SELECT OBJECT_ID(N'dbo.tblEmailTemplates')")->fetchColumn() ?: 0) > 0;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(bool $activeOnly = false): array
    {
        if (!$this->supportsEmailTemplates()) {
            return [];
        }

        try {
            $where = $activeOnly ? 'WHERE Active = 1' : '';
            $st = $this->db->query("
                SELECT
                    EmailTemplateID,
                    TemplateKey,
                    TemplateName,
                    [Description],
                    [Subject],
                    BodyHtml,
                    BodyText,
                    Active,
                    CreatedAt,
                    UpdatedAt
                FROM dbo.tblEmailTemplates
                {$where}
                ORDER BY TemplateName ASC, TemplateKey ASC
            ");
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0 || !$this->supportsEmailTemplates()) {
            return null;
        }

        try {
            $st = $this->db->prepare("
                SELECT TOP 1
                    EmailTemplateID,
                    TemplateKey,
                    TemplateName,
                    [Description],
                    [Subject],
                    BodyHtml,
                    BodyText,
                    Active,
                    CreatedAt,
                    CreatedBy,
                    UpdatedAt,
                    UpdatedBy
                FROM dbo.tblEmailTemplates
                WHERE EmailTemplateID = :id
            ");
            $st->execute([':id' => $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function findByKey(string $templateKey, bool $activeOnly = false): ?array
    {
        $templateKey = $this->normalizeTemplateKey($templateKey);
        if ($templateKey === '' || !$this->supportsEmailTemplates()) {
            return null;
        }

        try {
            $whereActive = $activeOnly ? 'AND Active = 1' : '';
            $st = $this->db->prepare("
                SELECT TOP 1
                    EmailTemplateID,
                    TemplateKey,
                    TemplateName,
                    [Description],
                    [Subject],
                    BodyHtml,
                    BodyText,
                    Active,
                    CreatedAt,
                    CreatedBy,
                    UpdatedAt,
                    UpdatedBy
                FROM dbo.tblEmailTemplates
                WHERE TemplateKey = :templateKey
                {$whereActive}
                ORDER BY EmailTemplateID ASC
            ");
            $st->execute([':templateKey' => $templateKey]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function save(array $data, ?int $userId = null): int
    {
        if (!$this->supportsEmailTemplates()) {
            throw new \RuntimeException('Email template table is not installed. Run backend-php/config/sql/create_email_templates.sql first.');
        }

        $id = (int)($data['EmailTemplateID'] ?? 0);
        $templateKey = $this->normalizeTemplateKey((string)($data['TemplateKey'] ?? ''));
        $templateName = trim((string)($data['TemplateName'] ?? ''));
        $subject = trim((string)($data['Subject'] ?? ''));
        $bodyHtml = trim((string)($data['BodyHtml'] ?? ''));
        $bodyText = trim((string)($data['BodyText'] ?? ''));
        $description = trim((string)($data['Description'] ?? ''));
        $active = !empty($data['Active']) ? 1 : 0;

        if ($templateKey === '') {
            throw new \InvalidArgumentException('Template key is required.');
        }
        if ($templateName === '') {
            throw new \InvalidArgumentException('Template name is required.');
        }
        if ($subject === '') {
            throw new \InvalidArgumentException('Subject is required.');
        }
        if ($bodyHtml === '') {
            throw new \InvalidArgumentException('HTML body is required.');
        }

        if ($id > 0) {
            $st = $this->db->prepare("
                UPDATE dbo.tblEmailTemplates
                SET TemplateKey = :templateKey,
                    TemplateName = :templateName,
                    [Description] = :description,
                    [Subject] = :subject,
                    BodyHtml = :bodyHtml,
                    BodyText = :bodyText,
                    Active = :active,
                    UpdatedAt = SYSUTCDATETIME(),
                    UpdatedBy = :updatedBy
                WHERE EmailTemplateID = :id
            ");
            $st->execute([
                ':id' => $id,
                ':templateKey' => $templateKey,
                ':templateName' => $templateName,
                ':description' => $description !== '' ? $description : null,
                ':subject' => $subject,
                ':bodyHtml' => $bodyHtml,
                ':bodyText' => $bodyText !== '' ? $bodyText : null,
                ':active' => $active,
                ':updatedBy' => $userId,
            ]);
            return $id;
        }

        $st = $this->db->prepare("
            INSERT INTO dbo.tblEmailTemplates
                (TemplateKey, TemplateName, [Description], [Subject], BodyHtml, BodyText, Active, CreatedAt, CreatedBy, UpdatedAt, UpdatedBy)
            OUTPUT INSERTED.EmailTemplateID
            VALUES
                (:templateKey, :templateName, :description, :subject, :bodyHtml, :bodyText, :active, SYSUTCDATETIME(), :createdBy, SYSUTCDATETIME(), :updatedBy)
        ");
        $st->execute([
            ':templateKey' => $templateKey,
            ':templateName' => $templateName,
            ':description' => $description !== '' ? $description : null,
            ':subject' => $subject,
            ':bodyHtml' => $bodyHtml,
            ':bodyText' => $bodyText !== '' ? $bodyText : null,
            ':active' => $active,
            ':createdBy' => $userId,
            ':updatedBy' => $userId,
        ]);

        return (int)($st->fetchColumn() ?: 0);
    }

    public function setActive(int $id, bool $active, ?int $userId = null): bool
    {
        if ($id <= 0 || !$this->supportsEmailTemplates()) {
            return false;
        }

        $st = $this->db->prepare("
            UPDATE dbo.tblEmailTemplates
            SET Active = :active,
                UpdatedAt = SYSUTCDATETIME(),
                UpdatedBy = :updatedBy
            WHERE EmailTemplateID = :id
        ");
        return $st->execute([
            ':id' => $id,
            ':active' => $active ? 1 : 0,
            ':updatedBy' => $userId,
        ]);
    }

    /**
     * @param array<string, scalar|null> $tokens
     * @return array<string, mixed>|null
     */
    public function render(string $templateKey, array $tokens): ?array
    {
        $row = $this->findByKey($templateKey, true);
        if (!$row) {
            return null;
        }

        return [
            'template' => $row,
            'subject' => $this->applyTokens((string)($row['Subject'] ?? ''), $tokens, false),
            'html' => $this->applyTokens((string)($row['BodyHtml'] ?? ''), $tokens, true),
            'text' => $this->applyTokens((string)($row['BodyText'] ?? ''), $tokens, false),
        ];
    }

    /**
     * @param array<string, scalar|null> $tokens
     */
    public function applyTokens(string $value, array $tokens, bool $htmlContext = false): string
    {
        if ($value === '' || $tokens === []) {
            return $value;
        }

        $replacements = [];
        foreach ($tokens as $key => $rawValue) {
            $normalizedKey = strtoupper(trim((string)$key));
            if ($normalizedKey === '') {
                continue;
            }

            $replacement = (string)($rawValue ?? '');
            if ($htmlContext && !$this->isHtmlToken($normalizedKey)) {
                $replacement = htmlspecialchars($replacement, ENT_QUOTES, 'UTF-8');
            }

            $replacements['{{' . $normalizedKey . '}}'] = $replacement;
            $replacements['{{ ' . $normalizedKey . ' }}'] = $replacement;
        }

        return strtr($value, $replacements);
    }

    private function normalizeTemplateKey(string $templateKey): string
    {
        $templateKey = strtoupper(trim($templateKey));
        $templateKey = preg_replace('/[^A-Z0-9_]+/', '_', $templateKey) ?? '';
        return trim($templateKey, '_');
    }

    private function isHtmlToken(string $tokenKey): bool
    {
        return str_ends_with($tokenKey, '_HTML') || str_ends_with($tokenKey, '_LINK');
    }
}
