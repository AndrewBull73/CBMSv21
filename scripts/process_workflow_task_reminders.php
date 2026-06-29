<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$vendor = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (is_file($vendor)) {
    require_once $vendor;
}

require_once $root . DIRECTORY_SEPARATOR . 'backend-php' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
require_once $root . DIRECTORY_SEPARATOR . 'backend-php' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'logger.php';

if (isset($conn) && $conn instanceof PDO && function_exists('app_log_set_conn')) {
    app_log_set_conn($conn);
}

$limit = 100;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(500, (int)substr($arg, 8)));
    }
}

$service = new App\Services\WorkflowTaskReminderService($conn);
$summary = $service->processDueWorkflowNotifications($limit);
$automatic = is_array($summary['automatic'] ?? null) ? $summary['automatic'] : ['checked' => 0, 'sent' => 0, 'failed' => 0, 'failures' => []];
$overdue = is_array($summary['overdue'] ?? null) ? $summary['overdue'] : ['checked' => 0, 'sent' => 0, 'failed' => 0, 'failures' => []];

echo 'Workflow reminder processor complete' . PHP_EOL;
echo 'Automatic reminders checked: ' . (int)$automatic['checked'] . PHP_EOL;
echo 'Automatic reminders sent: ' . (int)$automatic['sent'] . PHP_EOL;
echo 'Automatic reminders failed: ' . (int)$automatic['failed'] . PHP_EOL;
echo 'Overdue escalations checked: ' . (int)$overdue['checked'] . PHP_EOL;
echo 'Overdue escalations sent: ' . (int)$overdue['sent'] . PHP_EOL;
echo 'Overdue escalations failed: ' . (int)$overdue['failed'] . PHP_EOL;

foreach ($automatic['failures'] as $failure) {
    echo 'Automatic reminder task #' . (int)($failure['WorkflowTaskID'] ?? 0) . ': ' . (string)($failure['error'] ?? 'Unknown error') . PHP_EOL;
}

foreach ($overdue['failures'] as $failure) {
    echo 'Overdue escalation task #' . (int)($failure['WorkflowTaskID'] ?? 0) . ': ' . (string)($failure['error'] ?? 'Unknown error') . PHP_EOL;
}

$failed = (int)$automatic['failed'] + (int)$overdue['failed'];
exit($failed > 0 ? 1 : 0);
