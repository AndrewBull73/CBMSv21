<?php
declare(strict_types=1);

$title = 'Certification Catalogue';
$icon = 'bi-award-fill';
$intro = 'Use this configuration screen to maintain certification definitions.';
$sections = [
    [
        'heading' => 'Certification Setup',
        'items' => [
            'Create one certification for each module or final assessed outcome.',
            'Set the module name, description, active flag, display order, and pass percentage.',
            'Keep certification codes stable once users have attempts.',
        ],
    ],
    [
        'heading' => 'Questions',
        'items' => [
            'Open Questions to maintain the multiple choice question bank.',
            'Use clear explanations so users understand the learning point after scoring.',
            'Review results after users begin attempting certifications.',
        ],
    ],
];
$note = 'Only Training Configuration users should maintain certification definitions.';
require __DIR__ . '/_TrainingHelp.php';
