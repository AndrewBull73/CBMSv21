<?php
declare(strict_types=1);

$title = 'Create New User Training';
$icon = 'bi-person-plus';
$intro = 'Use this guided scenario to practise creating a new user record in CBMS.';
$sections = [
    [
        'heading' => 'Scenario Flow',
        'items' => [
            'Start the scenario from the runner before opening the target Users screen.',
            'Follow the highlighted controls and instruction text for each user setup step.',
            'Review each step before continuing so the user creation workflow is clear.',
        ],
    ],
    [
        'heading' => 'Good Practice',
        'items' => [
            'Use realistic sample values where the scenario provides them.',
            'Pay attention to user status, roles, and access context.',
            'Return to the runner if you need to restart or review progress.',
        ],
    ],
];
$note = 'This is a guided practice scenario, not the main Training Dashboard.';
require __DIR__ . '/_TrainingHelp.php';
