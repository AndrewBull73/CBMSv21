<?php
declare(strict_types=1);

$title = 'Training Session Dashboard';
$icon = 'bi-people';
$intro = 'Use this screen to review one trainer-led training session and its attendees.';
$sections = [
    [
        'heading' => 'Session Review',
        'items' => [
            'Check session details, roster, progress, and completion status.',
            'Identify attendees who need follow-up or evidence before completion is accepted.',
            'Use trainer notes to document decisions or support activity.',
        ],
    ],
    [
        'heading' => 'Evidence',
        'items' => [
            'Attach or review files that support attendance, completion, or trainer sign-off.',
            'Keep evidence meaningful so later reviewers can understand what was confirmed.',
            'Return to Training Operations to manage other sessions.',
        ],
    ],
];
$note = 'Session evidence supports trainer-led delivery but does not replace user-specific scenario progress.';
require __DIR__ . '/_TrainingHelp.php';
