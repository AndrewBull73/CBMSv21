<?php
declare(strict_types=1);

$title = 'Training Catalogue';
$icon = 'bi-journal-text';
$intro = 'Use this configuration screen to maintain the master list of guided training scenarios.';
$sections = [
    [
        'heading' => 'Scenario Setup',
        'items' => [
            'Create one scenario for each guided exercise users should complete.',
            'Use order, module, audience, and difficulty to make the catalogue easy to manage.',
            'Keep scenario codes stable once progress records exist.',
        ],
    ],
    [
        'heading' => 'Actions',
        'items' => [
            'Open Steps to maintain the guided flow for a scenario.',
            'Open Translations to maintain language-specific scenario wording.',
            'Use Launch View to see the learner-facing scenario list.',
        ],
    ],
];
$note = 'This screen controls definitions. Assignments are managed from Training Operations.';
require __DIR__ . '/_TrainingHelp.php';
