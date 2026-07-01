<?php
declare(strict_types=1);

$title = 'Certification Question Setup';
$icon = 'bi-ui-checks';
$intro = 'Use this form to define one multiple choice certification question.';
$sections = [
    [
        'heading' => 'Writing The Question',
        'items' => [
            'Ask one clear question with one best answer.',
            'Use answer options that are plausible but not misleading.',
            'Match the correct option key exactly to one of the configured options.',
        ],
    ],
    [
        'heading' => 'Feedback',
        'items' => [
            'Use the explanation to reinforce the correct learning point.',
            'Keep feedback specific to the module behaviour being assessed.',
            'Save and return to the question list to review ordering.',
        ],
    ],
];
$note = 'Good certification questions test judgement and correct use of CBMS, not memorised screen labels only.';
require __DIR__ . '/_TrainingHelp.php';
