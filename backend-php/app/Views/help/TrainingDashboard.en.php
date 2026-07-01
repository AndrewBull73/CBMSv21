<?php
declare(strict_types=1);

$title = 'Training Dashboard';
$icon = 'bi-speedometer2';
$intro = 'Use this dashboard to see your assigned training scenarios, certification status, and next actions.';
$sections = [
    [
        'heading' => 'What To Check',
        'items' => [
            'Review only the scenarios and certifications assigned to your user account.',
            'Use progress and status labels to see what is not started, in progress, completed, or certified.',
            'Start or resume the next training item directly from the dashboard.',
        ],
    ],
    [
        'heading' => 'Good Practice',
        'items' => [
            'Complete assigned guided scenarios before attempting the related certification.',
            'Return to this dashboard after a trainer assigns new module training.',
            'Use Certifications when you are ready for a final module test.',
        ],
    ],
];
$note = 'If expected training is missing, ask a Training Administration user to check your assignments.';
require __DIR__ . '/_TrainingHelp.php';
