<?php
declare(strict_types=1);

$title = 'Certifications';
$icon = 'bi-award';
$intro = 'Use this screen to review module certifications and start a certification attempt.';
$sections = [
    [
        'heading' => 'Before You Start',
        'items' => [
            'Review the module, question count, pass mark, and latest attempt result.',
            'Complete related guided scenarios before taking the final module test.',
            'Start the certification when you are ready to answer all questions.',
        ],
    ],
    [
        'heading' => 'Status',
        'items' => [
            'Passed attempts confirm certification for the configured module.',
            'Failed attempts remain in history so progress can be reviewed.',
            'Use the Training Dashboard to see certification status beside scenario training.',
        ],
    ],
];
$note = 'Certification tests are scored separately from guided training scenarios.';
require __DIR__ . '/_TrainingHelp.php';
