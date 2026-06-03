<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SystemSettingsModel;
use App\Services\MailService;

require_once __DIR__ . '/../../shared/logger.php';
require_once __DIR__ . '/../../shared/csrf.php';
require_once __DIR__ . '/../../shared/error_handler.php'; // for db_query()

class DiagnosticsController extends BaseController
{
      protected array $acl = [
        // Deny everything unless explicitly listed
        '*'                => ['auth' => true, 'permsAny' => ['SYSADMIN']],

        // Allow viewing diagnostics summary if user has DIAG_VIEW or SYSADMIN
        'index'            => ['auth' => true, 'permsAny' => ['DIAG_VIEW','SYSADMIN']],

        // Require SYSADMIN for mail + dangerous test routes
        'sendTestEmail'    => ['auth' => true, 'permsAny' => ['SYSADMIN']],
        'forceDbError'     => ['auth' => true, 'permsAny' => ['SYSADMIN']],
        'throwException'   => ['auth' => true, 'permsAny' => ['SYSADMIN']],
        'fatalError'       => ['auth' => true, 'permsAny' => ['SYSADMIN']],
    ];

    public function __construct()
    {
        parent::__construct(); // ✅ enforce auth + ACL checks
    }
    
    public function index(): void
    {
        global $conn;

        $results = [
            'db'       => false,
            'settings' => [],
            'log_test' => false,
            'mail_test'=> null,
        ];

        // DB check
        try {
            $st = $conn->query("SELECT 1");
            $results['db'] = ($st && $st->fetchColumn() == 1);
        } catch (\Throwable $e) {
            $results['db'] = $e->getMessage();
        }

        // Settings check
        try {
            $settings = new SystemSettingsModel($conn);
            $results['settings'] = [
                'APP_DEBUG'                   => getenv('APP_DEBUG'),
                'APP_DEBUG_LOG_ENABLED'       => getenv('APP_DEBUG_LOG_ENABLED'),
                'SLOW_REQUEST_THRESHOLD_MS'   => $settings->get('SLOW_REQUEST_THRESHOLD_MS', '(not set)'),
                'SLOW_REQUEST_ALERTS_ENABLED' => $settings->get('SLOW_REQUEST_ALERTS_ENABLED', '(not set)'),
                'EMAIL_ERROR_ENABLED'         => $settings->get('EMAIL_ERROR_ENABLED', '(not set)'),
                'EMAIL_ERROR_TO'              => $settings->get('EMAIL_ERROR_TO', '(not set)'),
            ];
        } catch (\Throwable $e) {
            $results['settings'] = ['error' => $e->getMessage()];
        }

        // Log test (INFO only, no ERROR spam)
        try {
            app_log('Diagnostics test INFO', ['controller'=>'Diagnostics'], 'info');
            $results['log_test'] = true;
        } catch (\Throwable $e) {
            $results['log_test'] = $e->getMessage();
        }
            
        $this->render('diagnostics/DiagnosticsView', [
            'title'   => __t('diagnostics_title'),
            'results' => $results,
        ]);
    }

    public function sendTestEmail(): void
    {
        global $conn;

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            echo __t('method_not_allowed');
            return;
        }

        if (!csrf_check($_POST['_csrf'] ?? '')) {
            $this->flashError(__t('security_check_failed'));
            header('Location: index.php?route=diagnostics/index');
            return;
        }

        try {
            $settings = new SystemSettingsModel($conn);
            $to   = $settings->get('EMAIL_ERROR_TO', '');
            $from = $settings->get('EMAIL_ERROR_FROM', 'noreply@cbmsv2.local');

            if ($to) {
                $mailer = new MailService($conn);
                $mailer->sendEmail(
                    $to,
                    '[' . __t('diagnostics_title') . '] ' . __t('test_email_subject'),
                    '<p>' . __t('test_email_body') . '</p>',
                    $from
                );
                $this->flashSuccess(__t('test_email_sent', ['to' => $to]));
            } else {
                $this->flashError(__t('no_error_email_to'));
            }
        } catch (\Throwable $e) {
            // Use placeholder for better translations
            $this->flashError(__t('mail_test_failed_detail', ['msg' => $e->getMessage()]));
        }

        header('Location: index.php?route=diagnostics/index');
    }

    /** Force a database error (using missing table). */
    public function forceDbError(): void
    {
        global $conn;
        db_query($conn, "SELECT TOP 1 * FROM tblRatesX");
    }

    /** Force a manual exception for testing. */
    public function throwException(): void
    {
        throw new \Exception("Diagnostics test exception");
    }

    /** Force a fatal error (undefined function). */
    public function fatalError(): void
    {
        undefined_function_call(); // will trigger fatal error
    }
}
