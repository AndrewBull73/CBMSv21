<?php
declare(strict_types=1);

$title = 'Edit Existing User Training';
$icon = 'bi-person-gear';
$intro = 'Use this guided scenario to practise finding and updating an existing user record.';
$sections = [
    [
        'heading' => 'Scenario Flow',
        'items' => [
            'Start the scenario from the runner and follow the highlighted Users list and edit form controls.',
            'Practise searching, opening the record, updating details, and reviewing related tabs.',
            'Use the runner controls if you need to restart or stop the scenario.',
        ],
    ],
    [
        'heading' => 'What To Notice',
        'items' => [
            'User profile details and role assignments affect application access.',
            'Multi-tab forms may require reviewing more than the first visible section.',
            'Saving confirms the intended change, so review fields before committing updates.',
        ],
    ],
];
$note = 'This training route demonstrates guided overlay behaviour for an existing administrative workflow.';
require __DIR__ . '/_TrainingHelp.php';
