<?php
declare(strict_types=1);

$title = 'Certification Questions';
$icon = 'bi-list-ol';
$intro = 'Use this screen to maintain the multiple choice questions for one certification.';
$sections = [
    [
        'heading' => 'Question Bank',
        'items' => [
            'Review question order, active flag, correct answer key, and explanation.',
            'Add questions that test practical understanding of the module.',
            'Keep the question count sufficient for the pass mark to be meaningful.',
        ],
    ],
    [
        'heading' => 'Maintenance',
        'items' => [
            'Edit a question when wording, options, or the explanation need improvement.',
            'Avoid changing historical meaning after users have attempted the certification.',
            'Archive or deactivate questions that should no longer be presented.',
        ],
    ],
];
$note = 'Questions are scored automatically when a user submits a certification attempt.';
require __DIR__ . '/_TrainingHelp.php';
