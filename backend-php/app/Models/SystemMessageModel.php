<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Services\AudienceService;
use App\Models\EmailQueueModel;
use App\Models\LoginTokenModel;
use App\Services\MailService;
use App\Models\SystemSettingsModel;

class SystemMessageModel
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getById(int $id): ?array
    {
        $row = $this->getRawById($id);
        return $row ? $this->applyTemplateTokensToRow($row) : null;
    }

    public function listMessages(?string $status = null, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $sql = "
            SELECT TOP ({$limit})
                m.MessageID,
                m.Title,
                m.Severity,
                m.Status,
                m.IsGlobal,
                m.RequireAck,
                m.EmailAlso,
                m.FiscalYearID,
                m.VersionID,
                (
                    SELECT TOP 1 d.DataObjectCode
                    FROM dbo.tblSystemMessageDataObject d
                    WHERE d.MessageID = m.MessageID
                    ORDER BY d.DataObjectCode
                ) AS ScopeDataObjectCode,
                m.DeliveryStartUTC,
                m.DeliveryEndUTC,
                m.CreatedAtUTC,
                m.UpdatedAtUTC,
                m.CreatedBy,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblSystemMessageDataObject d
                    WHERE d.MessageID = m.MessageID
                ) AS DataObjectCount,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblSystemMessageRole r
                    WHERE r.MessageID = m.MessageID
                ) AS RoleCount,
                (
                    SELECT COUNT(*)
                    FROM dbo.tblSystemMessageUser u
                    WHERE u.MessageID = m.MessageID
                ) AS UserCount
            FROM dbo.tblSystemMessage m
        ";

        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= " WHERE m.Status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY m.MessageID DESC";

        $st = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $st->bindValue($key, $value);
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $row): array => $this->applyTemplateTokensToRow($row), $rows);
    }

    /** Normalise severity: accepts int or strings like info/success/warning/danger */
    private function normalizeSeverity(mixed $sev): int
    {
        if (is_numeric($sev)) return (int)$sev;
        $map = ['info'=>1,'success'=>2,'warning'=>3,'danger'=>4];
        $key = strtolower((string)$sev);
        return $map[$key] ?? 1;
    }

    public function create(array $data, array $codes, array $userIds, array $roles): int
    {
        $this->db->beginTransaction();
        try {
            // Map incoming keys -> DB columns
            $title           = (string)($data['Title'] ?? '');
            $bodyHtml        = (string)($data['BodyHtml'] ?? $data['Body'] ?? '');
            $severity        = $this->normalizeSeverity($data['Severity'] ?? 1);
            $isGlobal        = (int)($data['IsGlobal'] ?? $data['AudienceGlobal'] ?? 0);
            $descTarget      = (int)($data['DescendantTarget'] ?? $data['IncludeDescendants'] ?? 0);
            $fy              = isset($data['FiscalYearID']) ? (int)$data['FiscalYearID'] : (isset($data['ScopeFiscalYearID']) ? (int)$data['ScopeFiscalYearID'] : null);
            $ver             = isset($data['VersionID']) ? (int)$data['VersionID'] : (isset($data['ScopeVersionID']) ? (int)$data['ScopeVersionID'] : null);
            $requireAck      = (int)($data['RequireAck'] ?? $data['RequiresAck'] ?? 0);
            $startUtc        = (string)($data['DeliveryStartUTC'] ?? $data['StartAt'] ?? gmdate('Y-m-d H:i:s'));
            $endUtc          = $data['DeliveryEndUTC'] ?? $data['EndAt'] ?? null;
            $status          = (string)($data['Status'] ?? 'draft'); // draft|published|archived
            $emailAlso       = (int)($data['EmailAlso'] ?? $data['SendEmail'] ?? 0);
            $emailSubject    = $data['EmailSubject'] ?? null;
            $emailBodyHtml   = $data['EmailBodyHtml'] ?? $bodyHtml;
            $createdBy       = isset($data['CreatedBy']) ? (int)$data['CreatedBy'] : null;

            // Insert (SQL Server): use OUTPUT to get the new ID in one round-trip
            $sql = "INSERT INTO dbo.tblSystemMessage
                    (Title, BodyHtml, Severity, IsGlobal, DescendantTarget,
                     FiscalYearID, VersionID, RequireAck,
                     DeliveryStartUTC, DeliveryEndUTC,
                     Status, EmailAlso, EmailSubject, EmailBodyHtml,
                     CreatedBy, CreatedAtUTC)
                    OUTPUT INSERTED.MessageID
                    VALUES
                    (:t, :bodyHtml, :sev, :isGlobal, :descTgt,
                     :fy, :ver, :reqAck,
                     :startUtc, :endUtc,
                     :status, :emailAlso, :emailSubj, :emailBody,
                     :createdBy, SYSUTCDATETIME())";

            $st = $this->db->prepare($sql);
            $st->bindValue(':t',         $title);
            $st->bindValue(':bodyHtml',  $bodyHtml);
            $st->bindValue(':sev',       $severity, PDO::PARAM_INT);
            $st->bindValue(':isGlobal',  $isGlobal, PDO::PARAM_INT);
            $st->bindValue(':descTgt',   $descTarget, PDO::PARAM_INT);
            $fy === null  ? $st->bindValue(':fy',  null, PDO::PARAM_NULL)  : $st->bindValue(':fy',  $fy,  PDO::PARAM_INT);
            $ver === null ? $st->bindValue(':ver', null, PDO::PARAM_NULL)  : $st->bindValue(':ver', $ver, PDO::PARAM_INT);
            $st->bindValue(':reqAck',    $requireAck, PDO::PARAM_INT);
            $st->bindValue(':startUtc',  $startUtc);
            $endUtc === null ? $st->bindValue(':endUtc', null, PDO::PARAM_NULL) : $st->bindValue(':endUtc', (string)$endUtc);
            $st->bindValue(':status',    $status);
            $st->bindValue(':emailAlso', $emailAlso, PDO::PARAM_INT);
            $emailSubject === null ? $st->bindValue(':emailSubj', null, PDO::PARAM_NULL) : $st->bindValue(':emailSubj', (string)$emailSubject);
            $st->bindValue(':emailBody', $emailBodyHtml);
            $createdBy === null ? $st->bindValue(':createdBy', null, PDO::PARAM_NULL) : $st->bindValue(':createdBy', $createdBy, PDO::PARAM_INT);
            $st->execute();
            $id = (int)$st->fetchColumn();

            // Mapping tables
            if (!empty($codes)) {
                $ins = $this->db->prepare("INSERT INTO dbo.tblSystemMessageDataObject (MessageID, DataObjectCode) VALUES (:id,:c)");
                foreach ($codes as $c) {
                    $ins->bindValue(':id', $id, PDO::PARAM_INT);
                    $ins->bindValue(':c',  (string)$c);
                    $ins->execute();
                }
            }
            if (!empty($userIds)) {
                $inu = $this->db->prepare("INSERT INTO dbo.tblSystemMessageUser (MessageID, UserID) VALUES (:id,:u)");
                foreach ($userIds as $u) {
                    $inu->bindValue(':id', $id, PDO::PARAM_INT);
                    $inu->bindValue(':u',  (int)$u, PDO::PARAM_INT);
                    $inu->execute();
                }
            }
            if (!empty($roles)) {
                $inr = $this->db->prepare("INSERT INTO dbo.tblSystemMessageRole (MessageID, RoleName) VALUES (:id,:r)");
                foreach ($roles as $r) {
                    $inr->bindValue(':id', $id, PDO::PARAM_INT);
                    $inr->bindValue(':r',  (string)$r);
                    $inr->execute();
                }
            }

            $this->logEvent($id, $createdBy, 'created', null);
            $this->db->commit();

            if ($status === 'published' && $emailAlso === 1) {
                $this->scheduleEmails($id);
            }

            return $id;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $messageId, array $data, array $codes, array $userIds, array $roles, ?int $updatedBy = null): void
    {
        $this->db->beginTransaction();
        try {
            $title         = (string)($data['Title'] ?? '');
            $bodyHtml      = (string)($data['BodyHtml'] ?? $data['Body'] ?? '');
            $severity      = $this->normalizeSeverity($data['Severity'] ?? 1);
            $isGlobal      = (int)($data['IsGlobal'] ?? $data['AudienceGlobal'] ?? 0);
            $descTarget    = (int)($data['DescendantTarget'] ?? $data['IncludeDescendants'] ?? 0);
            $fy            = isset($data['FiscalYearID']) ? (int)$data['FiscalYearID'] : (isset($data['ScopeFiscalYearID']) ? (int)$data['ScopeFiscalYearID'] : null);
            $ver           = isset($data['VersionID']) ? (int)$data['VersionID'] : (isset($data['ScopeVersionID']) ? (int)$data['ScopeVersionID'] : null);
            $requireAck    = (int)($data['RequireAck'] ?? $data['RequiresAck'] ?? 0);
            $startUtc      = (string)($data['DeliveryStartUTC'] ?? $data['StartAt'] ?? gmdate('Y-m-d H:i:s'));
            $endUtc        = $data['DeliveryEndUTC'] ?? $data['EndAt'] ?? null;
            $status        = (string)($data['Status'] ?? 'draft');
            $emailAlso     = (int)($data['EmailAlso'] ?? $data['SendEmail'] ?? 0);
            $emailSubject  = $data['EmailSubject'] ?? null;
            $emailBodyHtml = $data['EmailBodyHtml'] ?? $bodyHtml;

            $sql = "UPDATE dbo.tblSystemMessage
                    SET Title = :t,
                        BodyHtml = :bodyHtml,
                        Severity = :sev,
                        IsGlobal = :isGlobal,
                        DescendantTarget = :descTgt,
                        FiscalYearID = :fy,
                        VersionID = :ver,
                        RequireAck = :reqAck,
                        DeliveryStartUTC = :startUtc,
                        DeliveryEndUTC = :endUtc,
                        Status = :status,
                        EmailAlso = :emailAlso,
                        EmailSubject = :emailSubj,
                        EmailBodyHtml = :emailBody,
                        UpdatedBy = :updatedBy,
                        UpdatedAtUTC = SYSUTCDATETIME()
                    WHERE MessageID = :id";

            $st = $this->db->prepare($sql);
            $st->bindValue(':id', $messageId, PDO::PARAM_INT);
            $st->bindValue(':t', $title);
            $st->bindValue(':bodyHtml', $bodyHtml);
            $st->bindValue(':sev', $severity, PDO::PARAM_INT);
            $st->bindValue(':isGlobal', $isGlobal, PDO::PARAM_INT);
            $st->bindValue(':descTgt', $descTarget, PDO::PARAM_INT);
            $fy === null ? $st->bindValue(':fy', null, PDO::PARAM_NULL) : $st->bindValue(':fy', $fy, PDO::PARAM_INT);
            $ver === null ? $st->bindValue(':ver', null, PDO::PARAM_NULL) : $st->bindValue(':ver', $ver, PDO::PARAM_INT);
            $st->bindValue(':reqAck', $requireAck, PDO::PARAM_INT);
            $st->bindValue(':startUtc', $startUtc);
            $endUtc === null ? $st->bindValue(':endUtc', null, PDO::PARAM_NULL) : $st->bindValue(':endUtc', (string)$endUtc);
            $st->bindValue(':status', $status);
            $st->bindValue(':emailAlso', $emailAlso, PDO::PARAM_INT);
            $emailSubject === null ? $st->bindValue(':emailSubj', null, PDO::PARAM_NULL) : $st->bindValue(':emailSubj', (string)$emailSubject);
            $st->bindValue(':emailBody', $emailBodyHtml);
            $updatedBy === null ? $st->bindValue(':updatedBy', null, PDO::PARAM_NULL) : $st->bindValue(':updatedBy', $updatedBy, PDO::PARAM_INT);
            $st->execute();

            $this->replaceAudienceMappings($messageId, $codes, $userIds, $roles);

            $this->logEvent($messageId, $updatedBy, 'updated', null);
            $this->db->commit();

            if ($status === 'published' && $emailAlso === 1) {
                $this->scheduleEmails($messageId);
            }
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $messageId, string $status, int $byUserId): void
    {
        $st = $this->db->prepare(
            "UPDATE dbo.tblSystemMessage
             SET Status = :s, UpdatedBy = :u, UpdatedAtUTC = SYSUTCDATETIME()
             WHERE MessageID = :id"
        );
        $st->bindValue(':s',  $status);
        $st->bindValue(':u',  $byUserId, PDO::PARAM_INT);
        $st->bindValue(':id', $messageId, PDO::PARAM_INT);
        $st->execute();

        $this->logEvent($messageId, $byUserId, $status === 'published' ? 'published' : 'archived', null);

        if ($status === 'published') {
            $row = $this->getById($messageId);
            if (!empty($row['EmailAlso'])) {
                $this->scheduleEmails($messageId);
            }
        }
    }

public function getActiveForUser(?int $userId, ?string $scopeCode, ?int $currentFy, ?int $currentVer): array
{
    $nowSql = "SYSUTCDATETIME()";
    $params = [];
    $joins  = [];
    $wheres = [
        "m.Status = 'published'",
        "m.DeliveryStartUTC <= {$nowSql}",
        "(m.DeliveryEndUTC IS NULL OR m.DeliveryEndUTC >= {$nowSql})",
    ];

    if ($userId) {
    // Show if it doesn't require ack, OR (requires ack AND user has NOT acked)
        $wheres[] = "(m.RequireAck = 0 OR NOT EXISTS (
                        SELECT 1
                        FROM dbo.tblSystemMessageAck a
                        WHERE a.MessageID = m.MessageID AND a.UserID = :uid
                    ))";
        $params[':uid'] = $userId;
    } else {
        // No user context => only show non-ack messages
        $wheres[] = "m.RequireAck = 0";
    }

    // Scope (FY / Version)
    $wheres[] = "(m.FiscalYearID IS NULL OR m.FiscalYearID = :cfy_gate)";
    $params[':cfy_gate'] = (int)($currentFy ?? 0);

    $wheres[] = "(m.VersionID IS NULL OR m.VersionID = :cver_gate)";
    $params[':cver_gate'] = (int)($currentVer ?? 0);

    // Audience: global OR by scoped DataObjectCode (with descendant targeting)
    $aud = [];
    $aud[] = "m.IsGlobal = 1";
    $aud[] = "(
                m.IsGlobal = 0
                AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSystemMessageDataObject md0
                    WHERE md0.MessageID = m.MessageID
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSystemMessageRole mr0
                    WHERE mr0.MessageID = m.MessageID
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM dbo.tblSystemMessageUser mu0
                    WHERE mu0.MessageID = m.MessageID
                )
              )";

    if ($userId) {
        $wheres[] = "(
            NOT EXISTS (
                SELECT 1
                FROM dbo.tblSystemMessageRole mr_gate
                WHERE mr_gate.MessageID = m.MessageID
            )
            OR EXISTS (
                SELECT 1
                FROM dbo.tblSystemMessageRole mr
                JOIN dbo.tblRoles r
                  ON r.RoleName = mr.RoleName
                JOIN dbo.tblUserRoles ur
                  ON ur.RoleID = r.RoleID
                WHERE mr.MessageID = m.MessageID
                  AND ur.UserID = :uid_role_gate
            )
        )";
        $params[':uid_role_gate'] = (int)$userId;
    }

    if ($scopeCode && $currentFy) {
        $aud[] = "EXISTS (
                    SELECT 1
                    FROM dbo.tblSystemMessageDataObject md
                    WHERE md.MessageID = m.MessageID
                      AND (
                           (m.DescendantTarget = 1 AND EXISTS (
                               SELECT 1
                               FROM dbo.tblDataObjectTree t
                               WHERE t.FiscalYearID   = :cfy_tree
                                 AND t.AncestorCode   = md.DataObjectCode
                                 AND t.DescendantCode = :scope_tree
                           ))
                           OR
                           (m.DescendantTarget = 0 AND md.DataObjectCode = :scope_exact)
                      )
                  )";
        $params[':cfy_tree']    = (int)$currentFy;
        $params[':scope_tree']  = $scopeCode;
        $params[':scope_exact'] = $scopeCode;
    }

    $wheres[] = '(' . implode(' OR ', $aud) . ')';

    $sql = "SELECT TOP 50 m.*
            FROM dbo.tblSystemMessage m
            " . implode("\n", $joins) . "
            WHERE " . implode(' AND ', $wheres) . "
            ORDER BY m.DeliveryStartUTC DESC, m.MessageID DESC";

    $st = $this->db->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return array_map(fn(array $row): array => $this->applyTemplateTokensToRow($row), $rows);
}

public function acknowledge(int $messageId, int $userId, ?string $ip = null): bool
{
    // Idempotent insert using positional placeholders (ODBC-safe).
    // Uses AckAtUTC; if your table has AckAt instead, uncomment the second SQL.
    $sql = "
        IF NOT EXISTS (
            SELECT 1
            FROM dbo.tblSystemMessageAck
            WHERE MessageID = ? AND UserID = ?
        )
        BEGIN
            INSERT INTO dbo.tblSystemMessageAck (MessageID, UserID, AckAt, AckFromIP)
            VALUES (?, ?, SYSUTCDATETIME(), ?)
        END
    ";

    // If your column is AckAt (not AckAtUTC), use this instead:
    // $sql = "
    //     IF NOT EXISTS (
    //         SELECT 1
    //         FROM dbo.tblSystemMessageAck
    //         WHERE MessageID = ? AND UserID = ?
    //     )
    //     BEGIN
    //         INSERT INTO dbo.tblSystemMessageAck (MessageID, UserID, AckAt, AckFromIP)
    //         VALUES (?, ?, SYSUTCDATETIME(), ?)
    //     END
    // ";

    $st = $this->db->prepare($sql);

    // Order matters with positional placeholders:
    // 1: EXISTS (MessageID)
    // 2: EXISTS (UserID)
    // 3: INSERT (MessageID)
    // 4: INSERT (UserID)
    // 5: INSERT (AckFromIP)
    $st->bindValue(1, $messageId, \PDO::PARAM_INT);
    $st->bindValue(2, $userId,    \PDO::PARAM_INT);
    $st->bindValue(3, $messageId, \PDO::PARAM_INT);
    $st->bindValue(4, $userId,    \PDO::PARAM_INT);
    $st->bindValue(5, $ip);

    $st->execute();

    $this->logEvent($messageId, $userId, 'acknowledged', $ip ?? null);
    return true;
}

// Minimal event logger for sys messages.
// Relies on tblSystemMessageEvent(EventAtUTC) having a DEFAULT SYSUTCDATETIME().
private function logEvent(int $messageId, ?int $userId, string $type, ?string $detail): void
{
    $sql = "INSERT INTO dbo.tblSystemMessageEvent (MessageID, UserID, EventType, Detail)
            VALUES (?, ?, ?, ?)";
    $st = $this->db->prepare($sql);
    $st->bindValue(1, $messageId, \PDO::PARAM_INT);
    if ($userId === null) {
        $st->bindValue(2, null, \PDO::PARAM_NULL);
    } else {
        $st->bindValue(2, $userId, \PDO::PARAM_INT);
    }
    $st->bindValue(3, $type);
    $st->bindValue(4, $detail);
    $st->execute();
}

private function replaceAudienceMappings(int $messageId, array $codes, array $userIds, array $roles): void
{
    $delCode = $this->db->prepare("DELETE FROM dbo.tblSystemMessageDataObject WHERE MessageID = :id");
    $delRole = $this->db->prepare("DELETE FROM dbo.tblSystemMessageRole WHERE MessageID = :id");
    $delUser = $this->db->prepare("DELETE FROM dbo.tblSystemMessageUser WHERE MessageID = :id");
    foreach ([$delCode, $delRole, $delUser] as $stmt) {
        $stmt->bindValue(':id', $messageId, PDO::PARAM_INT);
        $stmt->execute();
    }

    if (!empty($codes)) {
        $ins = $this->db->prepare("INSERT INTO dbo.tblSystemMessageDataObject (MessageID, DataObjectCode) VALUES (:id,:c)");
        foreach (array_values(array_unique(array_filter(array_map('strval', $codes)))) as $c) {
            $ins->bindValue(':id', $messageId, PDO::PARAM_INT);
            $ins->bindValue(':c', (string)$c);
            $ins->execute();
        }
    }

    if (!empty($userIds)) {
        $inu = $this->db->prepare("INSERT INTO dbo.tblSystemMessageUser (MessageID, UserID) VALUES (:id,:u)");
        foreach ($userIds as $u) {
            $inu->bindValue(':id', $messageId, PDO::PARAM_INT);
            $inu->bindValue(':u', (int)$u, PDO::PARAM_INT);
            $inu->execute();
        }
    }

    if (!empty($roles)) {
        $inr = $this->db->prepare("INSERT INTO dbo.tblSystemMessageRole (MessageID, RoleName) VALUES (:id,:r)");
        foreach ($roles as $r) {
            $inr->bindValue(':id', $messageId, PDO::PARAM_INT);
            $inr->bindValue(':r', (string)$r);
            $inr->execute();
        }
    }
}

private function scheduleEmails(int $messageId): void
{
    $row = $this->getRawById($messageId);
    if (!$row) {
        return;
    }

    $codes = $this->fetchMessageCodes($messageId);
    $roles = $this->fetchMessageRoles($messageId);
    $aud = new AudienceService($this->db);
    $uids = $aud->resolveUserIds(
        !empty($row['IsGlobal']),
        $codes,
        !empty($row['DescendantTarget']),
        [],
        $roles,
        isset($row['FiscalYearID']) && $row['FiscalYearID'] !== null ? (int)$row['FiscalYearID'] : null
    );
    $recipients = $this->fetchRecipientUsers($uids);
    if ($recipients === []) {
        return;
    }

    $subjectTemplate = trim((string)($row['EmailSubject'] ?? '')) !== '' ? (string)$row['EmailSubject'] : (string)($row['Title'] ?? 'CBMSv21 message');
    $htmlTemplate = trim((string)($row['EmailBodyHtml'] ?? '')) !== '' ? (string)$row['EmailBodyHtml'] : (string)($row['BodyHtml'] ?? '');
    $when = (string)($row['DeliveryStartUTC'] ?? gmdate('Y-m-d H:i:s'));
    $settings = new SystemSettingsModel($this->db);
    $ttlMinutes = (int)($settings->get('AUTH_SECURE_LOGIN_TTL_MINUTES', '1440') ?? '1440');
    $tokenModel = new LoginTokenModel($this->db);

    $queue = new EmailQueueModel($this->db, new MailService($this->db));
    $batch = [];
    foreach ($recipients as $recipient) {
        $issued = $tokenModel->issue(
            (int)$recipient['UserID'],
            (string)$recipient['Email'],
            $ttlMinutes > 0 ? $ttlMinutes : 1440,
            null,
            $messageId
        );
        $secureUrl = $this->buildSecureLoginUrl((string)$issued['Token']);
        $subject = $this->applyEmailTemplateTokens($subjectTemplate, $secureUrl);
        $html = $this->applyEmailTemplateTokens($htmlTemplate, $secureUrl);
        $batch[] = [
            'to' => (string)$recipient['Email'],
            'subject' => $subject,
            'html' => $html,
            'text' => trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))),
            'when' => $when,
            'MessageID' => $messageId,
        ];
    }
    $queue->enqueueBatch($batch);
}

private function fetchMessageCodes(int $messageId): array
{
    $st = $this->db->prepare("SELECT DataObjectCode FROM dbo.tblSystemMessageDataObject WHERE MessageID = :id");
    $st->bindValue(':id', $messageId, PDO::PARAM_INT);
    $st->execute();
    return array_values(array_unique(array_map('strval', array_column($st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'DataObjectCode'))));
}

private function fetchMessageRoles(int $messageId): array
{
    $st = $this->db->prepare("SELECT RoleName FROM dbo.tblSystemMessageRole WHERE MessageID = :id");
    $st->bindValue(':id', $messageId, PDO::PARAM_INT);
    $st->execute();
    return array_values(array_unique(array_map('strval', array_column($st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'RoleName'))));
}

private function fetchRecipientUsers(array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
    if ($userIds === []) {
        return [];
    }

    $rows = [];
    foreach (array_chunk($userIds, 900) as $chunk) {
        $place = implode(',', array_fill(0, count($chunk), '?'));
        $st = $this->db->prepare("
            SELECT UserID, Email
            FROM dbo.tblUsers
            WHERE IsActive = 1
              AND Email IS NOT NULL
              AND LEN(Email) > 3
              AND UserID IN ($place)
        ");
        foreach ($chunk as $index => $id) {
            $st->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $st->execute();
        $rows = array_merge($rows, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    return $rows;
}

private function getRawById(int $id): ?array
{
    $st = $this->db->prepare("SELECT * FROM dbo.tblSystemMessage WHERE MessageID = :id");
    $st->bindValue(':id', $id, PDO::PARAM_INT);
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

private function applyTemplateTokensToRow(array $row): array
{
    foreach (['Title', 'BodyHtml', 'EmailSubject', 'EmailBodyHtml'] as $field) {
        if (array_key_exists($field, $row) && $row[$field] !== null) {
            $row[$field] = $this->applyTemplateTokens((string)$row[$field]);
        }
    }
    return $row;
}

private function applyTemplateTokens(string $value): string
{
    $loginUrl = $this->resolveLoginUrl();
    $loginLink = '<a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Log in to CBMSv21</a>';

    return strtr($value, [
        '{{CBMS_LOGIN_URL}}' => $loginUrl,
        '{{CBMS_LOGIN_LINK}}' => $loginLink,
        '{{CBMS_SECURE_LOGIN_URL}}' => $loginUrl,
        '{{CBMS_SECURE_LOGIN_LINK}}' => $loginLink,
    ]);
}

private function applyEmailTemplateTokens(string $value, string $secureUrl): string
{
    $loginUrl = $this->resolveLoginUrl();
    $loginLink = '<a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Log in to CBMSv21</a>';
    $secureLink = '<a href="' . htmlspecialchars($secureUrl, ENT_QUOTES, 'UTF-8') . '">Securely log in to CBMSv21</a>';

    return strtr($value, [
        '{{CBMS_LOGIN_URL}}' => $loginUrl,
        '{{CBMS_LOGIN_LINK}}' => $loginLink,
        '{{CBMS_SECURE_LOGIN_URL}}' => $secureUrl,
        '{{CBMS_SECURE_LOGIN_LINK}}' => $secureLink,
    ]);
}

private function resolveLoginUrl(): string
{
    $settings = new SystemSettingsModel($this->db);
    $explicit = trim((string)$settings->get('AUTH_LOGIN_URL', ''));
    if ($explicit !== '') {
        return $explicit;
    }

    $appUrl = rtrim((string)$settings->get('APP_URL', 'http://localhost/CBMSv21'), '/');
    return $appUrl . '/backend-php/public/index.php?route=auth/loginForm';
}

private function buildSecureLoginUrl(string $token): string
{
    $settings = new SystemSettingsModel($this->db);
    $base = trim((string)$settings->get('AUTH_TOKEN_LOGIN_URL_BASE', ''));
    if ($base === '') {
        $appUrl = rtrim((string)$settings->get('APP_URL', 'http://localhost/CBMSv21'), '/');
        $base = $appUrl . '/backend-php/public/index.php?route=auth/tokenLogin';
    }

    $separator = str_contains($base, '?') ? '&' : '?';
    return $base . $separator . 'token=' . urlencode($token);
}

}
