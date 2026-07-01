<?php
declare(strict_types=1);

$title = 'Training Validation';
$icon = 'bi-shield-check';
$intro = 'Use validation to find configuration issues before users run guided scenarios.';
$sections = [
    [
        'heading' => 'What Validation Checks',
        'items' => [
            'Missing or inactive routes that would prevent a step from opening.',
            'Target element IDs that may not exist on the live screen.',
            'Scenario and step ordering problems that could confuse users.',
        ],
    ],
    [
        'heading' => 'How To Use It',
        'items' => [
            'Review each issue and open the linked scenario or step maintenance screen.',
            'Correct the source configuration and run validation again.',
            'Validate new scenarios before assigning them to users.',
        ],
    ],
];
$note = 'Validation is a configuration safety check; still test important scenarios end to end.';
require __DIR__ . '/_TrainingHelp.php';
