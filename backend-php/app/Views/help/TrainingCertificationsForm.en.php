<?php
declare(strict_types=1);

$title = 'Certification Setup';
$icon = 'bi-award';
$intro = 'Use this form to create or maintain a module certification.';
$sections = [
    [
        'heading' => 'Certification Details',
        'items' => [
            'Use a stable certification code once attempts have been recorded.',
            'Set the module name consistently so dashboard and results filters group records correctly.',
            'Set the pass percentage required for certification.',
        ],
    ],
    [
        'heading' => 'Next Steps',
        'items' => [
            'Save the certification before maintaining questions.',
            'Use sort order to control display sequence.',
            'Keep inactive certifications hidden from learner launch views.',
        ],
    ],
];
$note = 'Questions are maintained separately from the certification definition.';
require __DIR__ . '/_TrainingHelp.php';
