<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Models\SystemSettingsModel;

class DataAccess
{
    private PHPMailer $mail;
    private SystemSettingsModel $settings;

    public function __construct($conn)
    {
        $this->mail     = new PHPMailer(true);
        $this->settings = new SystemSettingsModel($conn);

        $this->configureSMTP();
    }

    /**
     * Configure SMTP server from tblSystemSettings
     */
    private function configureSMTP(): void
    {
        $host = $this->settings->get('SMTP_HOST', 'localhost') ?? 'localhost';
        $port = (int) ($this->settings->get('SMTP_PORT', '25') ?? '25');
        $user = $this->settings->get('SMTP_USER', '') ?? '';
        $pass = $this->settings->get('SMTP_PASS', '') ?? '';
        $sslSetting = strtolower(trim((string) ($this->settings->get('SMTP_SSL', '1') ?? '1')));
        $ssl = in_array($sslSetting, ['1', 'true', 'yes', 'on'], true);

        $this->mail->isSMTP();
        $this->mail->Host       = $host;
        $this->mail->Port       = $port;
        $this->mail->SMTPAuth   = !empty($user);
        $this->mail->Username   = $user;
        $this->mail->Password   = $pass;

        // ✅ Always use STARTTLS if SMTP_SSL=true (works well with SMTP2GO)
        if ($ssl) {
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
    }

    /**
     * Send an email
     *
     * @param string $to Recipient email
     * @param string $subject Subject line
     * @param string $body HTML body
     * @param string|null $from Override FROM address
     * @return bool
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $from = null): bool
    {
        try {
            $defaultFrom = $this->settings->get('SMTP_FROM', 'noreply@cbmsv2.local') ?? 'noreply@cbmsv2.local';
            $fromAddress = $from ?? $defaultFrom;

            $this->mail->clearAllRecipients();
            $this->mail->setFrom($fromAddress, 'CBMSv2');
            $this->mail->addAddress($to);

            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = strip_tags($body);

            return $this->mail->send();
        } catch (Exception $e) {
            error_log("❌ MailService failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}
