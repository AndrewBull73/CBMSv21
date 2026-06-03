<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use App\Services\MailService;

class EmailQueueModel
{
    public function __construct(private PDO $db, private MailService $mailer)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function enqueueBatch(array $rows): void
    {
        $sql = "INSERT INTO dbo.tblEmailQueue (ToAddress, Subject, BodyHtml, BodyText, ScheduledAt, MessageID)
                VALUES (:to, :subj, :html, :text, :when, :mid)";
        $st = $this->db->prepare($sql);
        foreach ($rows as $r) {
            $st->bindValue(':to',   $r['to']);
            $st->bindValue(':subj', $r['subject']);
            $st->bindValue(':html', $r['html'] ?? null);
            $st->bindValue(':text', $r['text'] ?? null);
            $st->bindValue(':when', is_string($r['when']) ? $r['when'] : ($r['when']->format('Y-m-d H:i:s')));
            $st->bindValue(':mid',  $r['MessageID'] ?? null, PDO::PARAM_INT);
            $st->execute();
        }
    }

    public function processDue(int $limit = 100): array
    {
        $this->db->beginTransaction();
        $sel = $this->db->prepare("
            SELECT TOP {$limit} EmailID, ToAddress, Subject, BodyHtml, BodyText
            FROM dbo.tblEmailQueue WITH (ROWLOCK, READPAST)
            WHERE Status = 'pending' AND ScheduledAt <= SYSUTCDATETIME()
            ORDER BY ScheduledAt ASC, EmailID ASC
        ");
        $sel->execute();
        $batch = $sel->fetchAll(PDO::FETCH_ASSOC);

        if ($batch) {
            $ids = implode(',', array_map('intval', array_column($batch, 'EmailID')));
            $this->db->exec("UPDATE dbo.tblEmailQueue SET Status='processing', LastAttemptAt=SYSUTCDATETIME(), Attempts = Attempts + 1 WHERE EmailID IN ({$ids})");
        }
        $this->db->commit();

        foreach ($batch as $row) {
            $ok = false;
            try {
                $ok = $this->mailer->sendEmail($row['ToAddress'], $row['Subject'], $row['BodyHtml'] ?? ($row['BodyText'] ?? ''));
            } catch (\Throwable $e) { $ok = false; }

            $upd = $this->db->prepare("UPDATE dbo.tblEmailQueue SET Status = :s, ErrorMsg = :e, LastAttemptAt = SYSUTCDATETIME() WHERE EmailID = :id");
            $upd->bindValue(':s', $ok ? 'sent' : 'failed');
            $upd->bindValue(':e', $ok ? null : 'MailService->sendEmail returned false');
            $upd->bindValue(':id', (int)$row['EmailID'], PDO::PARAM_INT);
            $upd->execute();
        }

        return $batch ?: [];
    }
}
