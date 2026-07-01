<?php
declare(strict_types=1);

$title = 'Training Scenarios';
$icon = 'bi-list-check';
$intro = 'Use this screen to launch and resume the guided scenarios assigned to you.';
$sections = [
    [
        'heading' => 'Using The List',
        'items' => [
            'Review module, order, audience, step count, and status before launching a scenario.',
            'Use Open Scenario or Resume Scenario to enter the runner.',
            'Use filters to focus on a module, difficulty, status, or text search.',
        ],
    ],
    [
        'heading' => 'Progress',
        'items' => [
            'Scenario progress is stored against your user account.',
            'Completed scenarios remain visible so you can review or repeat them where allowed.',
            'The Training Dashboard gives a combined view of scenarios and certifications.',
        ],
    ],
];
$note = 'This is the learner launch screen. The Training Catalogue is the configuration screen.';
require __DIR__ . '/_TrainingHelp.php';
