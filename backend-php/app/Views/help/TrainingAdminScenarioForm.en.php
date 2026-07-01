<?php
declare(strict_types=1);

$title = 'Training Scenario Setup';
$icon = 'bi-journal-text';
$intro = 'Use this form to maintain the metadata for one guided training scenario.';
$sections = [
    [
        'heading' => 'Key Fields',
        'items' => [
            'Use a stable scenario code because progress, assignments, and links depend on it.',
            'Set module, audience, difficulty, active flag, and display order so the scenario appears correctly.',
            'Use prerequisites to explain what must exist before the scenario is useful.',
        ],
    ],
    [
        'heading' => 'Related Setup',
        'items' => [
            'Maintain the ordered guided flow on the Steps screen.',
            'Maintain translated scenario and step text on the Translations screen.',
            'Return to the Catalogue to review all scenarios together.',
        ],
    ],
];
$note = 'This form defines the scenario shell; it does not define every guided step.';
require __DIR__ . '/_TrainingHelp.php';
