<?php
declare(strict_types=1);

$title = 'Training Translations';
$icon = 'bi-translate';
$intro = 'Use this screen to maintain translated scenario text, step instructions, and samples.';
$sections = [
    [
        'heading' => 'Translation Scope',
        'items' => [
            'Select the scenario and language before editing translated content.',
            'Translate scenario title, description, prerequisites, step titles, instructions, and samples where needed.',
            'Leave translation fields blank when the base text should remain the fallback.',
        ],
    ],
    [
        'heading' => 'Quality Checks',
        'items' => [
            'Keep translated instructions short enough to fit in the training overlay.',
            'Review sample values carefully because they may appear directly to trainees.',
            'Test important translated scenarios before assigning them broadly.',
        ],
    ],
];
$note = 'Translations change wording only; they do not change scenario order, routes, or target elements.';
require __DIR__ . '/_TrainingHelp.php';
