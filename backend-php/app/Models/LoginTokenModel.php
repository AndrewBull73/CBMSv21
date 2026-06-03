<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

final class LoginTokenModel
{
    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function issue(int $userId, string $email, int $ttlMinutes = 1440, ?int $createdBy = null, ?int $messageId = null): array
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + (max(1, $ttlMinutes) * 60));

        $st = $this->db->prepare("
            INSERT INTO dbo.tblLoginTokens
                (UserID, Token, Email, ExpiresAt, CreatedBy, MessageID)
            VALUES
                (:userId, :token, :email, :expiresAt, :createdBy, :messageId)
        ");
        $st->bindValue(':userId', $userId, PDO::PARAM_INT);
        $st->bindValue(':token', $token);
        $st->bindValue(':email', $email);
        $st->bindValue(':expiresAt', $expiresAt);
        $createdBy === null ? $st->bindValue(':createdBy', null, PDO::PARAM_NULL) : $st->bindValue(':createdBy', $createdBy, PDO::PARAM_INT);
        $messageId === null ? $st->bindValue(':messageId', null, PDO::PARAM_NULL) : $st->bindValue(':messageId', $messageId, PDO::PARAM_INT);
        $st->execute();

        return [
            'Token' => $token,
            'ExpiresAt' => $expiresAt,
        ];
    }

    public function consume(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare("
                SELECT LoginTokenID, UserID, IsUsed, ExpiresAt
                FROM dbo.tblLoginTokens
                WHERE Token = :token
            ");
            $st->execute([':token' => $token]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: null;

            $expiresAt = (string)($row['ExpiresAt'] ?? '');
            $expiresTs = $expiresAt !== ''
                ? (new \DateTimeImmutable($expiresAt, new \DateTimeZone('UTC')))->getTimestamp()
                : 0;

            if (!$row || (int)($row['IsUsed'] ?? 0) === 1 || $expiresTs < time()) {
                $this->db->rollBack();
                return null;
            }

            $upd = $this->db->prepare("
                UPDATE dbo.tblLoginTokens
                SET IsUsed = 1,
                    UsedAt = SYSUTCDATETIME()
                WHERE LoginTokenID = :id
            ");
            $upd->execute([':id' => (int)$row['LoginTokenID']]);

            $userSt = $this->db->prepare("
                SELECT UserID, Username, PasswordHash, IsActive
                FROM dbo.tblUsers
                WHERE UserID = :id
            ");
            $userSt->execute([':id' => (int)$row['UserID']]);
            $user = $userSt->fetch(PDO::FETCH_ASSOC) ?: null;

            $this->db->commit();
            return $user ?: null;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
