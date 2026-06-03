<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmailQueueModel;
use App\Services\MailService;

class EmailQueueController extends BaseController
{
    public function process(): void
    {
        require __DIR__ . '/../../config/db.php'; // $conn
        $mailer = new MailService($conn);
        $queue  = new EmailQueueModel($conn, $mailer);
        $sent   = $queue->processDue(200);

        header('Content-Type: application/json');
        echo json_encode(['processed' => count($sent)]);
    }
}
