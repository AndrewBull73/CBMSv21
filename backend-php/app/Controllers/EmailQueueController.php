<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmailQueueModel;
use App\Services\MailService;

class EmailQueueController extends BaseController
{
    protected array $acl = [
        '*' => ['auth' => true, 'permsAny' => ['LOGS_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'index' => ['auth' => true, 'permsAny' => ['LOGS_VIEW', 'ADMIN_ALL', 'SYSADMIN']],
        'process' => ['auth' => true, 'permsAny' => ['SYSADMIN', 'ADMIN_ALL']],
        'resend' => ['auth' => true, 'permsAny' => ['SYSADMIN', 'ADMIN_ALL']],
        'remove' => ['auth' => true, 'permsAny' => ['SYSADMIN', 'ADMIN_ALL']],
        'restore' => ['auth' => true, 'permsAny' => ['SYSADMIN', 'ADMIN_ALL']],
    ];

    public function index(): void
    {
        global $conn;
        $queue = new EmailQueueModel($conn);

        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        $allowedStatuses = ['', 'pending', 'processing', 'sent', 'failed', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = '';
        }

        $limit = (int) ($_GET['limit'] ?? 100);
        if ($limit < 25) {
            $limit = 25;
        } elseif ($limit > 500) {
            $limit = 500;
        }

        $filters = [
            'status' => $status,
            'q' => trim((string) ($_GET['q'] ?? '')),
            'limit' => $limit,
        ];

        $this->render('diagnostics/EmailQueueView', [
            'title' => 'Email Queue',
            'rows' => $queue->listRecent($filters, $limit),
            'summary' => $queue->getStatusSummary(),
            'filters' => $filters,
        ]);
    }

    public function process(): void
    {
        global $conn;

        $isPost = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
        if ($isPost) {
            $this->assertPostWithCsrf('index.php?route=emailqueue/index');
        }

        $mailer = new MailService($conn);
        $queue  = new EmailQueueModel($conn, $mailer);
        $sent   = $queue->processDue(200);

        if ($isPost) {
            $sentCount = 0;
            $failedCount = 0;
            foreach ($sent as $row) {
                if (!empty($row['Sent'])) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            }

            $message = 'Sent queued emails. Processed: ' . count($sent) . '. Sent: ' . $sentCount . '. Failed: ' . $failedCount . '.';
            if ($failedCount > 0) {
                $this->flashError($message);
            } else {
                $this->flashSuccess($message);
            }

            header('Location: index.php?route=emailqueue/index');
            exit;
        }

        header('Content-Type: application/json');
        echo json_encode(['processed' => count($sent)]);
    }

    public function resend(): void
    {
        $this->assertPostWithCsrf('index.php?route=emailqueue/index');

        global $conn;
        $ids = [];
        $singleId = (int) ($_POST['email_id'] ?? 0);
        if ($singleId > 0) {
            $ids[] = $singleId;
        } else {
            foreach ((array) ($_POST['email_ids'] ?? []) as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            $this->flashError('Select one or more emails to queue for resend.');
            header('Location: index.php?route=emailqueue/index');
            exit;
        }

        try {
            $queue = new EmailQueueModel($conn);
            $queued = 0;
            foreach ($ids as $emailId) {
                if ($queue->resetForResend($emailId)) {
                    $queued++;
                }
            }

            if ($queued > 0) {
                $this->flashSuccess($queued . ' email' . ($queued === 1 ? '' : 's') . ' queued for resend. Use Send Queued Emails to send them.');
            } else {
                $this->flashError('No emails could be queued for resend.');
            }
        } catch (\Throwable $e) {
            $this->flashError('Queue for resend failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=emailqueue/index');
        exit;
    }

    public function remove(): void
    {
        $this->assertPostWithCsrf('index.php?route=emailqueue/index');

        global $conn;
        $ids = [];
        foreach ((array) ($_POST['email_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            $this->flashError('Select one or more emails to remove from the queue.');
            header('Location: index.php?route=emailqueue/index');
            exit;
        }

        try {
            $queue = new EmailQueueModel($conn);
            $removed = $queue->removeFromQueue($ids);
            if ($removed > 0) {
                $this->flashSuccess($removed . ' email' . ($removed === 1 ? '' : 's') . ' removed from the send queue.');
            } else {
                $this->flashError('No emails could be removed from the send queue.');
            }
        } catch (\Throwable $e) {
            $this->flashError('Remove from queue failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=emailqueue/index');
        exit;
    }

    public function restore(): void
    {
        $this->assertPostWithCsrf('index.php?route=emailqueue/index');

        global $conn;
        $ids = [];
        foreach ((array) ($_POST['email_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            $this->flashError('Select one or more cancelled emails to restore.');
            header('Location: index.php?route=emailqueue/index');
            exit;
        }

        try {
            $queue = new EmailQueueModel($conn);
            $restored = $queue->restorePreviousStatus($ids);
            if ($restored > 0) {
                $this->flashSuccess($restored . ' email' . ($restored === 1 ? '' : 's') . ' restored to the previous queue status.');
            } else {
                $this->flashError('No cancelled emails could be restored.');
            }
        } catch (\Throwable $e) {
            $this->flashError('Restore queue status failed: ' . $e->getMessage());
        }

        header('Location: index.php?route=emailqueue/index');
        exit;
    }
}
