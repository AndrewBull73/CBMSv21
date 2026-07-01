<?php
declare(strict_types=1);

$title = 'Training Summary';
$icon = 'bi-mortarboard';
$intro = 'Use this administration screen to review user training progress and completion.';
$sections = [
    [
        'heading' => 'Filters',
        'items' => [
            'Filter by user, module, scenario, status, or linked context to narrow the results.',
            'Use module filtering when reviewing one rollout area such as Workflow Operations.',
            'Reset filters before switching to a different review question.',
        ],
    ],
    [
        'heading' => 'Reviewing Results',
        'items' => [
            'Check progress, attempt number, started date, and completed date for each row.',
            'Use completion status to confirm whether assigned training has been finished.',
            'Combine this screen with Certification Results for final module readiness reporting.',
        ],
    ],
];
$note = 'This screen is for Training Administration users and can include multiple users.';
require __DIR__ . '/_TrainingHelp.php';
