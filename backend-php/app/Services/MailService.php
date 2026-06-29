<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\EmailQueueModel;
use App\Models\SystemSettingsModel;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    private PHPMailer $mail;
    private SystemSettingsModel $settings;
    private string $lastError = '';
    private static bool $loggingSend = false;

    public function __construct(private $conn)
    {
        $this->mail = new PHPMailer(true);
        $this->settings = new SystemSettingsModel($conn);

        $this->configureSMTP();
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    private function configureSMTP(): void
    {
        $host = $this->settings->get('SMTP_HOST', 'localhost') ?? 'localhost';
        $port = (int) ($this->settings->get('SMTP_PORT', '25') ?? '25');
        $user = $this->settings->get('SMTP_USER', '') ?? '';
        $pass = $this->settings->get('SMTP_PASS', '') ?? '';
        $sslSetting = strtolower(trim((string) ($this->settings->get('SMTP_SSL', '1') ?? '1')));
        $ssl = in_array($sslSetting, ['1', 'true', 'yes', 'on'], true);

        $this->mail->isSMTP();
        $this->mail->Host = $host;
        $this->mail->Port = $port;
        $this->mail->SMTPAuth = $user !== '';
        $this->mail->Username = $user;
        $this->mail->Password = $pass;

        if ($ssl) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
    }

    public function sendEmail(string $to, string $subject, string $body, ?string $from = null): bool
    {
        $this->lastError = '';

        try {
            $queue = new EmailQueueModel($this->conn, $this);
            $ids = $queue->enqueueBatch([[
                'to' => $to,
                'subject' => $subject,
                'html' => $body,
                'text' => trim(strip_tags($body)),
                'from' => $from,
                'when' => gmdate('Y-m-d H:i:s'),
                'status' => 'pending',
            ]]);

            $inserted = array_filter(array_map('intval', $ids));
            if ($inserted === []) {
                return true;
            }

            $sentAny = false;
            foreach ($inserted as $emailId) {
                $processed = $queue->processDue(1, $emailId);
                foreach ($processed as $row) {
                    if ((int) ($row['EmailID'] ?? 0) !== $emailId) {
                        continue;
                    }
                    $sentAny = true;
                    if (empty($row['Sent'])) {
                        return false;
                    }
                }
            }

            return $sentAny;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('MailService queue failed: ' . $this->lastError);
            $this->logSendResult(false, $to, $subject, trim((string) ($from ?? '')), 'Queue failed: ' . $this->lastError);
            return false;
        }
    }

    public function sendNow(string $to, string $subject, string $body, ?string $from = null): bool
    {
        $this->lastError = '';

        try {
            $defaultFrom = trim((string) ($this->settings->get('SMTP_FROM', 'noreply@cbmsv2.local') ?? ''));
            if ($defaultFrom === '') {
                $defaultFrom = 'noreply@cbmsv2.local';
            }
            $fromAddress = trim((string) ($from ?? ''));
            if ($fromAddress === '') {
                $fromAddress = $defaultFrom;
            }

            $this->mail->clearAllRecipients();
            $this->mail->setFrom($fromAddress, 'CBMSv2');
            $this->mail->addAddress($to);

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $body;
            $this->mail->AltBody = strip_tags($body);

            $sent = $this->mail->send();
            if (!$sent) {
                $this->lastError = $this->mail->ErrorInfo !== '' ? $this->mail->ErrorInfo : 'PHPMailer returned false without an error message.';
                error_log('MailService failed: ' . $this->lastError);
                $this->logSendResult(false, $to, $subject, $fromAddress, $this->lastError);
            } else {
                $this->logSendResult(true, $to, $subject, $fromAddress);
            }

            return $sent;
        } catch (Exception $e) {
            $this->lastError = $this->mail->ErrorInfo !== '' ? $this->mail->ErrorInfo : $e->getMessage();
            error_log('MailService failed: ' . $this->lastError);
            $this->logSendResult(false, $to, $subject, $fromAddress ?? '', $this->lastError);
            return false;
        }
    }

    private function logSendResult(bool $sent, string $to, string $subject, string $from, string $error = ''): void
    {
        if (self::$loggingSend || !function_exists('app_log')) {
            return;
        }

        self::$loggingSend = true;
        try {
            app_log($sent ? 'Email sent' : 'Email send failed', [
                'to' => $to,
                'from' => $from,
                'subject' => $subject,
                'smtp_host' => $this->mail->Host,
                'smtp_port' => $this->mail->Port,
                'error' => $error,
                'suppress_email_alert' => true,
            ], $sent ? 'info' : 'error');
        } finally {
            self::$loggingSend = false;
        }
    }
}
