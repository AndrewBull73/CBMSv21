<?php
declare(strict_types=1);

$title = 'Training Steps';
$icon = 'bi-list-ol';
$intro = 'Use this screen to maintain the ordered steps for one guided scenario.';
$sections = [
    [
        'heading' => 'Step Flow',
        'items' => [
            'Review each route, target element, completion mode, and sort order.',
            'Keep step numbers and order logical so the trainee can follow the workflow.',
            'Archive steps that should no longer appear instead of leaving broken instructions active.',
        ],
    ],
    [
        'heading' => 'Targeting',
        'items' => [
            'Target element IDs must match the real screen controls.',
            'Use click completion for buttons or tabs the user must press.',
            'Use manual continuation when the user must read, review, or confirm understanding.',
        ],
    ],
];
$note = 'Broken target IDs are one of the most common causes of guided scenario interruptions.';
require __DIR__ . '/_TrainingHelp.php';
