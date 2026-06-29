<?php
declare(strict_types=1);

namespace App\Services;

class DataAccess
{
    private MailService $mail;

    public function __construct($conn)
    {
        $this->mail = new MailService($conn);
    }

    public function sendEmail(string $to, string $subject, string $body, ?string $from = null): bool
    {
        return $this->mail->sendEmail($to, $subject, $body, $from);
    }
}
