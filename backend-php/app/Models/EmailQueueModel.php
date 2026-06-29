<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Services\MailService;

class EmailQueueModel
{
    private ?array $columns = null;
    private ?array $schema = null;

    public function __construct(private PDO $db, private ?MailService $mailer = null)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function enqueueBatch(array $rows): array
    {
        $schema = $this->schema();
        $insertMap = [
            'to' => 'to',
            'from' => 'from',
            'subject' => 'subject',
            'bodyHtml' => 'html',
            'bodyText' => 'text',
            'scheduledAt' => 'when',
            'status' => 'status',
            'attempts' => 'attempts',
            'messageId' => 'MessageID',
        ];

        $columns = [];
        $values = [];
        foreach ($insertMap as $logical => $param) {
            if (!empty($schema[$logical])) {
                $columns[] = $this->quote((string) $schema[$logical]);
                $values[] = ':' . $param;
            }
        }

        if ($columns === []) {
            throw new \RuntimeException('tblEmailQueue does not contain recognized insert columns.');
        }

        $output = !empty($schema['id'])
            ? ' OUTPUT INSERTED.' . $this->quote((string) $schema['id'])
            : '';
        $sql = 'INSERT INTO dbo.tblEmailQueue (' . implode(', ', $columns) . ')' . $output . ' VALUES (' . implode(', ', $values) . ')';
        $st = $this->db->prepare($sql);
        $insertedIds = [];
        foreach ($rows as $r) {
            if (!empty($schema['to'])) {
                $st->bindValue(':to', $r['to']);
            }
            if (!empty($schema['from'])) {
                $st->bindValue(':from', $r['from'] ?? null);
            }
            if (!empty($schema['subject'])) {
                $st->bindValue(':subject', $r['subject']);
            }
            if (!empty($schema['bodyHtml'])) {
                $st->bindValue(':html', $r['html'] ?? null);
            }
            if (!empty($schema['bodyText'])) {
                $st->bindValue(':text', $r['text'] ?? null);
            }
            if (!empty($schema['scheduledAt'])) {
                $when = $r['when'] ?? date('Y-m-d H:i:s');
                $st->bindValue(':when', is_string($when) ? $when : ($when->format('Y-m-d H:i:s')));
            }
            if (!empty($schema['status'])) {
                $st->bindValue(':status', $r['status'] ?? 'pending');
            }
            if (!empty($schema['attempts'])) {
                $st->bindValue(':attempts', (int) ($r['attempts'] ?? 0), PDO::PARAM_INT);
            }
            if (!empty($schema['messageId'])) {
                $st->bindValue(':MessageID', $r['MessageID'] ?? null, PDO::PARAM_INT);
            }
            $st->execute();
            if (!empty($schema['id'])) {
                $id = (int) ($st->fetchColumn() ?: 0);
                if ($id > 0) {
                    $insertedIds[] = $id;
                }
            }
        }

        return $insertedIds;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(array $filters = [], int $limit = 100): array
    {
        $schema = $this->schema();
        $limit = max(25, min(500, $limit));
        $where = [];
        $params = [];

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'LOWER(' . $this->statusExpression() . ') = :status';
            $params[':status'] = $status;
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $searchColumns = array_filter([
                $schema['to'] ?? null,
                $schema['subject'] ?? null,
                $schema['errorMsg'] ?? null,
                $schema['messageId'] ?? null,
            ]);
            if ($searchColumns !== []) {
                $parts = [];
                foreach ($searchColumns as $column) {
                    $parts[] = 'CAST(' . $this->quote((string) $column) . ' AS NVARCHAR(MAX)) LIKE :q';
                }
                $where[] = '(' . implode(' OR ', $parts) . ')';
                $params[':q'] = '%' . $search . '%';
            }
        }

        $sql = "
            SELECT TOP {$limit}
                {$this->selectAlias('id', 'EmailID', 'int')},
                {$this->selectAlias('to', 'ToAddress', 'text')},
                {$this->selectAlias('from', 'FromAddress', 'text')},
                {$this->selectAlias('subject', 'Subject', 'text')},
                {$this->selectAlias('bodyHtml', 'BodyHtml', 'text')},
                {$this->selectAlias('bodyText', 'BodyText', 'text')},
                {$this->selectAlias('scheduledAt', 'ScheduledAt', 'date')},
                {$this->statusExpression()} AS [Status],
                {$this->selectAlias('attempts', 'Attempts', 'int')},
                {$this->selectAlias('lastAttemptAt', 'LastAttemptAt', 'date')},
                {$this->selectAlias('errorMsg', 'ErrorMsg', 'text')},
                {$this->selectAlias('messageId', 'MessageID', 'int')}
            FROM dbo.tblEmailQueue
        ";

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $orderColumn = $schema['lastAttemptAt'] ?? ($schema['scheduledAt'] ?? ($schema['id'] ?? null));
        $idColumn = $schema['id'] ?? null;
        $sql .= ' ORDER BY ' . ($orderColumn ? $this->quote((string) $orderColumn) : '1') . ' DESC';
        if ($idColumn && $idColumn !== $orderColumn) {
            $sql .= ', ' . $this->quote((string) $idColumn) . ' DESC';
        }

        $st = $this->db->prepare($sql);
        foreach ($params as $name => $value) {
            $st->bindValue($name, $value);
        }
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, int>
     */
    public function getStatusSummary(): array
    {
        $schema = $this->schema();
        $summary = [
            'pending' => 0,
            'processing' => 0,
            'sent' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'total' => 0,
        ];

        $statusExpression = $this->statusExpression();
        $st = $this->db->query("
            SELECT LOWER({$statusExpression}) AS [StatusKey], COUNT(*) AS [RowCount]
            FROM dbo.tblEmailQueue
            GROUP BY LOWER({$statusExpression})
        ");

        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = strtolower(trim((string) ($row['StatusKey'] ?? '')));
            $count = (int) ($row['RowCount'] ?? 0);
            if ($key !== '') {
                $summary[$key] = $count;
            }
            $summary['total'] += $count;
        }

        return $summary;
    }

    public function processDue(int $limit = 100, ?int $onlyEmailId = null): array
    {
        if (!$this->mailer instanceof MailService) {
            throw new \RuntimeException('EmailQueueModel requires a MailService instance to process queued emails.');
        }

        $schema = $this->schema();
        foreach (['id', 'to', 'subject'] as $required) {
            if (empty($schema[$required])) {
                throw new \RuntimeException('tblEmailQueue is missing a recognized ' . $required . ' column.');
            }
        }

        $this->db->beginTransaction();
        $where = [];
        if ($onlyEmailId !== null) {
            $where[] = $this->quote((string) $schema['id']) . ' = :onlyEmailId';
            $limit = 1;
        } elseif (!empty($schema['status'])) {
            $where[] = 'LOWER(' . $this->quote((string) $schema['status']) . ") = 'pending'";
        } elseif (!empty($schema['processedAt'])) {
            $where[] = $this->quote((string) $schema['processedAt']) . ' IS NULL';
        } elseif (!empty($schema['sentAt'])) {
            $where[] = $this->quote((string) $schema['sentAt']) . ' IS NULL';
            if (!empty($schema['errorMsg'])) {
                $errorExpression = 'LOWER(LTRIM(RTRIM(CAST(' . $this->quote((string) $schema['errorMsg']) . ' AS NVARCHAR(MAX)))))';
                $where[] = "(NULLIF(LTRIM(RTRIM(CAST(" . $this->quote((string) $schema['errorMsg']) . " AS NVARCHAR(MAX)))), '') IS NULL OR {$errorExpression} LIKE 'queued for resend by administrator%')";
            }
        }
        if ($onlyEmailId === null && !empty($schema['scheduledAt'])) {
            $where[] = $this->quote((string) $schema['scheduledAt']) . ' <= SYSUTCDATETIME()';
        }
        $whereSql = $where !== [] ? implode(' AND ', $where) : '1 = 1';
        $orderColumn = $schema['scheduledAt'] ?? $schema['id'];
        $sel = $this->db->prepare("
            SELECT TOP {$limit}
                {$this->selectAlias('id', 'EmailID', 'int')},
                {$this->selectAlias('to', 'ToAddress', 'text')},
                {$this->selectAlias('from', 'FromAddress', 'text')},
                {$this->selectAlias('subject', 'Subject', 'text')},
                {$this->selectAlias('bodyHtml', 'BodyHtml', 'text')},
                {$this->selectAlias('bodyText', 'BodyText', 'text')}
            FROM dbo.tblEmailQueue WITH (ROWLOCK, READPAST)
            WHERE {$whereSql}
            ORDER BY {$this->quote((string) $orderColumn)} ASC
        ");
        if ($onlyEmailId !== null) {
            $sel->bindValue(':onlyEmailId', $onlyEmailId, PDO::PARAM_INT);
        }
        $sel->execute();
        $batch = $sel->fetchAll(PDO::FETCH_ASSOC);

        if ($batch) {
            $ids = implode(',', array_map('intval', array_column($batch, 'EmailID')));
            $updates = [];
            if (!empty($schema['status'])) {
                $updates[] = $this->quote((string) $schema['status']) . "='Processing'";
            }
            if (!empty($schema['lastAttemptAt'])) {
                $updates[] = $this->quote((string) $schema['lastAttemptAt']) . '=SYSUTCDATETIME()';
            }
            if (!empty($schema['processedAt'])) {
                $updates[] = $this->quote((string) $schema['processedAt']) . '=NULL';
            }
            if (!empty($schema['sentAt'])) {
                $updates[] = $this->quote((string) $schema['sentAt']) . '=NULL';
            }
            if (!empty($schema['errorMsg'])) {
                $updates[] = $this->quote((string) $schema['errorMsg']) . '=NULL';
            }
            if (!empty($schema['attempts'])) {
                $updates[] = $this->quote((string) $schema['attempts']) . ' = COALESCE(' . $this->quote((string) $schema['attempts']) . ', 0) + 1';
            }
            if ($updates !== []) {
                $this->db->exec('UPDATE dbo.tblEmailQueue SET ' . implode(', ', $updates) . ' WHERE ' . $this->quote((string) $schema['id']) . " IN ({$ids})");
            }
        }
        $this->db->commit();

        foreach ($batch as &$row) {
            $ok = false;
            try {
                $ok = $this->mailer->sendNow(
                    (string) ($row['ToAddress'] ?? ''),
                    (string) ($row['Subject'] ?? ''),
                    (string) ($row['BodyHtml'] ?? ($row['BodyText'] ?? '')),
                    trim((string) ($row['FromAddress'] ?? '')) !== '' ? (string) $row['FromAddress'] : null
                );
            } catch (\Throwable $e) { $ok = false; }

            $updates = [];
            if (!empty($schema['status'])) {
                $updates[] = $this->quote((string) $schema['status']) . ' = :s';
            }
            if (!empty($schema['errorMsg'])) {
                $updates[] = $this->quote((string) $schema['errorMsg']) . ' = :e';
            }
            if (!empty($schema['lastAttemptAt'])) {
                $updates[] = $this->quote((string) $schema['lastAttemptAt']) . ' = SYSUTCDATETIME()';
            }
            if (!empty($schema['processedAt'])) {
                $updates[] = $this->quote((string) $schema['processedAt']) . ' = SYSUTCDATETIME()';
            }
            if (!empty($schema['sentAt'])) {
                $updates[] = $this->quote((string) $schema['sentAt']) . ' = ' . ($ok ? 'SYSUTCDATETIME()' : 'NULL');
            }
            if ($updates === []) {
                continue;
            }
            $upd = $this->db->prepare('UPDATE dbo.tblEmailQueue SET ' . implode(', ', $updates) . ' WHERE ' . $this->quote((string) $schema['id']) . ' = :id');
            if (!empty($schema['status'])) {
                $upd->bindValue(':s', $ok ? 'Sent' : 'Failed');
            }
            if (!empty($schema['errorMsg'])) {
                $upd->bindValue(':e', $ok ? null : ($this->mailer->getLastError() ?: 'MailService->sendNow returned false'));
            }
            $upd->bindValue(':id', (int)$row['EmailID'], PDO::PARAM_INT);
            $upd->execute();
            $row['Sent'] = $ok;
            $row['ErrorMsg'] = $ok ? null : ($this->mailer->getLastError() ?: 'MailService->sendNow returned false');
        }
        unset($row);

        return $batch ?: [];
    }

    public function resetForResend(int $emailId): bool
    {
        if ($emailId <= 0) {
            return false;
        }

        $schema = $this->schema();
        if (empty($schema['id'])) {
            throw new \RuntimeException('tblEmailQueue is missing a recognized id column.');
        }

        $updates = [];
        if (!empty($schema['status'])) {
            $updates[] = $this->quote((string) $schema['status']) . " = 'Pending'";
        }
        if (!empty($schema['scheduledAt'])) {
            $updates[] = $this->quote((string) $schema['scheduledAt']) . ' = SYSUTCDATETIME()';
        }
        if (!empty($schema['processedAt'])) {
            $updates[] = $this->quote((string) $schema['processedAt']) . ' = NULL';
        }
        if (!empty($schema['sentAt'])) {
            $updates[] = $this->quote((string) $schema['sentAt']) . ' = NULL';
        }
        if (!empty($schema['errorMsg'])) {
            $updates[] = $this->quote((string) $schema['errorMsg']) . " = CONCAT('Queued for resend by administrator; PreviousStatus=', CAST(" . $this->statusExpression() . ' AS NVARCHAR(30)))';
        }

        if ($updates === []) {
            return false;
        }

        $st = $this->db->prepare(
            'UPDATE dbo.tblEmailQueue SET ' . implode(', ', $updates) .
            ' WHERE ' . $this->quote((string) $schema['id']) . ' = :id'
        );
        $st->bindValue(':id', $emailId, PDO::PARAM_INT);
        $st->execute();

        return $st->rowCount() > 0;
    }

    /**
     * @param array<int> $emailIds
     */
    public function removeFromQueue(array $emailIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $emailIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $schema = $this->schema();
        if (empty($schema['id'])) {
            throw new \RuntimeException('tblEmailQueue is missing a recognized id column.');
        }

        $updates = [];
        if (!empty($schema['status'])) {
            $updates[] = $this->quote((string) $schema['status']) . " = 'Cancelled'";
        }
        if (!empty($schema['errorMsg'])) {
            $updates[] = $this->quote((string) $schema['errorMsg']) . " = CONCAT('Removed from queue by administrator; PreviousStatus=', CAST(" . $this->statusExpression() . ' AS NVARCHAR(30)))';
        }
        if (!empty($schema['lastAttemptAt'])) {
            $updates[] = $this->quote((string) $schema['lastAttemptAt']) . ' = SYSUTCDATETIME()';
        }

        if ($updates === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $name = ':id' . $index;
            $placeholders[] = $name;
            $params[$name] = $id;
        }

        $st = $this->db->prepare(
            'UPDATE dbo.tblEmailQueue SET ' . implode(', ', $updates) .
            ' WHERE ' . $this->quote((string) $schema['id']) . ' IN (' . implode(', ', $placeholders) . ')'
        );
        foreach ($params as $name => $id) {
            $st->bindValue($name, $id, PDO::PARAM_INT);
        }
        $st->execute();

        return $st->rowCount();
    }

    /**
     * @param array<int> $emailIds
     */
    public function restorePreviousStatus(array $emailIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $emailIds), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return 0;
        }

        $schema = $this->schema();
        if (empty($schema['id'])) {
            throw new \RuntimeException('tblEmailQueue is missing a recognized id column.');
        }

        $updates = [];
        $errorColumn = !empty($schema['errorMsg']) ? $this->quote((string) $schema['errorMsg']) : null;
        if (!empty($schema['status']) && $errorColumn !== null) {
            $status = $this->quote((string) $schema['status']);
            $lowerError = "LOWER(CAST({$errorColumn} AS NVARCHAR(MAX)))";
            $updates[] = "{$status} = CASE " .
                "WHEN {$lowerError} LIKE '%previousstatus=sent%' THEN 'Sent' " .
                "WHEN {$lowerError} LIKE '%previousstatus=failed%' THEN 'Failed' " .
                "WHEN {$lowerError} LIKE '%previousstatus=processing%' THEN 'Processing' " .
                "WHEN {$lowerError} LIKE '%previousstatus=cancelled%' THEN 'Cancelled' " .
                "ELSE 'Pending' END";
        } elseif (!empty($schema['status'])) {
            $updates[] = $this->quote((string) $schema['status']) . " = 'Pending'";
        }
        if ($errorColumn !== null) {
            $updates[] = "{$errorColumn} = NULL";
        }
        if (!empty($schema['lastAttemptAt'])) {
            $updates[] = $this->quote((string) $schema['lastAttemptAt']) . ' = SYSUTCDATETIME()';
        }

        if ($updates === []) {
            return 0;
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $name = ':id' . $index;
            $placeholders[] = $name;
            $params[$name] = $id;
        }

        $where = $this->quote((string) $schema['id']) . ' IN (' . implode(', ', $placeholders) . ')';
        if ($errorColumn !== null) {
            $marker = "LOWER(LTRIM(RTRIM(CAST({$errorColumn} AS NVARCHAR(MAX)))))";
            $where .= " AND ({$marker} LIKE 'removed from queue by administrator%' OR {$marker} LIKE 'queued for resend by administrator%')";
        }

        $st = $this->db->prepare(
            'UPDATE dbo.tblEmailQueue SET ' . implode(', ', $updates) .
            ' WHERE ' . $where
        );
        foreach ($params as $name => $id) {
            $st->bindValue($name, $id, PDO::PARAM_INT);
        }
        $st->execute();

        return $st->rowCount();
    }

    /**
     * @param array<int> $emailIds
     */
    public function restoreRemovedFromQueue(array $emailIds): int
    {
        return $this->restorePreviousStatus($emailIds);
    }

    private function schema(): array
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        $columns = $this->columns();
        $this->schema = [
            'id' => $this->pickColumn($columns, ['EmailID', 'EmailQueueID', 'QueueID', 'ID']),
            'to' => $this->pickColumn($columns, ['ToAddress', 'RecipientEmail', 'RecipientAddress', 'EmailTo', 'ToEmail', 'ToEmailAddress', 'EmailAddress', 'Recipient']),
            'from' => $this->pickColumn($columns, ['FromAddress', 'SenderEmail', 'EmailFrom', 'FromEmail', 'ReplyTo']),
            'subject' => $this->pickColumn($columns, ['Subject', 'EmailSubject', 'SubjectLine', 'Title']),
            'bodyHtml' => $this->pickColumn($columns, ['BodyHtml', 'EmailBodyHtml', 'HtmlBody', 'BodyHTML', 'Body', 'MessageBody']),
            'bodyText' => $this->pickColumn($columns, ['BodyText', 'EmailBodyText', 'TextBody', 'MessageText']),
            'scheduledAt' => $this->pickColumn($columns, ['ScheduledAt', 'ScheduledUTC', 'SendAtUTC', 'SendAt', 'SendAfter', 'DeliveryStartUTC', 'CreatedAt', 'CreatedAtUTC', 'DateCreated']),
            'status' => $this->pickColumn($columns, ['Status', 'QueueStatus', 'EmailStatus', 'SendStatus']),
            'attempts' => $this->pickColumn($columns, ['Attempts', 'AttemptCount', 'RetryCount', 'SendAttempts']),
            'lastAttemptAt' => $this->pickColumn($columns, ['LastAttemptAt', 'LastAttemptUTC', 'LastTriedAt', 'LastAttemptAtUTC', 'ProcessedAtUTC', 'SentAt', 'UpdatedAtUTC', 'DateUpdated']),
            'processedAt' => $this->pickColumn($columns, ['ProcessedAtUTC', 'ProcessedAt']),
            'sentAt' => $this->pickColumn($columns, ['SentAtUTC', 'SentAt']),
            'errorMsg' => $this->pickColumn($columns, ['ErrorMsg', 'ErrorMessage', 'LastError', 'FailureReason']),
            'messageId' => $this->pickColumn($columns, ['MessageID', 'SystemMessageID']),
        ];

        return $this->schema;
    }

    private function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }

        $st = $this->db->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = 'dbo'
              AND TABLE_NAME = 'tblEmailQueue'
        ");
        $st->execute();
        $this->columns = array_map('strval', array_column($st->fetchAll(PDO::FETCH_ASSOC) ?: [], 'COLUMN_NAME'));
        return $this->columns;
    }

    private function pickColumn(array $columns, array $candidates): ?string
    {
        $lookup = [];
        foreach ($columns as $column) {
            $lookup[strtolower((string) $column)] = (string) $column;
        }

        foreach ($candidates as $candidate) {
            $key = strtolower((string) $candidate);
            if (isset($lookup[$key])) {
                return $lookup[$key];
            }
        }

        return null;
    }

    private function selectAlias(string $logical, string $alias, string $type): string
    {
        $schema = $this->schema();
        $column = $schema[$logical] ?? null;
        if ($column) {
            return $this->quote((string) $column) . ' AS ' . $this->quoteAlias($alias);
        }

        $expression = match ($type) {
            'int' => 'CAST(NULL AS INT)',
            'date' => 'CAST(NULL AS DATETIME2)',
            'status' => "CAST('pending' AS NVARCHAR(30))",
            default => 'CAST(NULL AS NVARCHAR(MAX))',
        };

        return $expression . ' AS ' . $this->quoteAlias($alias);
    }

    private function statusExpression(): string
    {
        $schema = $this->schema();
        $errorMsg = !empty($schema['errorMsg']) ? $this->quote((string) $schema['errorMsg']) : null;
        $cancelledCondition = $errorMsg !== null
            ? "LOWER(LTRIM(RTRIM(CAST({$errorMsg} AS NVARCHAR(MAX))))) LIKE 'removed from queue by administrator%'"
            : null;
        $queuedForResendCondition = $errorMsg !== null
            ? "LOWER(LTRIM(RTRIM(CAST({$errorMsg} AS NVARCHAR(MAX))))) LIKE 'queued for resend by administrator%'"
            : null;

        if (!empty($schema['status'])) {
            $status = $this->quote((string) $schema['status']);
            if ($cancelledCondition !== null) {
                return "CASE WHEN LOWER(COALESCE(CAST({$status} AS NVARCHAR(30)), '')) = 'cancelled' OR {$cancelledCondition} THEN 'Cancelled' ELSE COALESCE(CAST({$status} AS NVARCHAR(30)), 'Pending') END";
            }
            return 'COALESCE(CAST(' . $status . " AS NVARCHAR(30)), 'Pending')";
        }

        $processedAt = !empty($schema['processedAt']) ? $this->quote((string) $schema['processedAt']) : null;
        $sentAt = !empty($schema['sentAt']) ? $this->quote((string) $schema['sentAt']) : null;

        $sentExpression = $sentAt ?? $processedAt;

        if ($sentExpression !== null && $errorMsg !== null) {
            return "CASE WHEN {$cancelledCondition} THEN 'Cancelled' WHEN {$queuedForResendCondition} THEN 'Pending' WHEN NULLIF(LTRIM(RTRIM(CAST({$errorMsg} AS NVARCHAR(MAX)))), '') IS NOT NULL THEN 'Failed' WHEN {$sentExpression} IS NOT NULL THEN 'Sent' ELSE 'Pending' END";
        }

        if ($sentExpression !== null) {
            return "CASE WHEN {$sentExpression} IS NOT NULL THEN 'Sent' ELSE 'Pending' END";
        }

        if ($errorMsg !== null) {
            return "CASE WHEN {$cancelledCondition} THEN 'Cancelled' WHEN {$queuedForResendCondition} THEN 'Pending' WHEN NULLIF(LTRIM(RTRIM(CAST({$errorMsg} AS NVARCHAR(MAX)))), '') IS NOT NULL THEN 'Failed' ELSE 'Pending' END";
        }

        return "CAST('Pending' AS NVARCHAR(30))";
    }

    private function quote(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }

    private function quoteAlias(string $identifier): string
    {
        return '[' . str_replace(']', ']]', $identifier) . ']';
    }
}
