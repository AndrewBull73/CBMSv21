<?php
declare(strict_types=1);

$title = 'Training Matrix';
$icon = 'bi-diagram-3';
$intro = 'Use this planning screen to review training paths, role alignment, and rollout coverage.';
$sections = [
    [
        'heading' => 'Planning View',
        'items' => [
            'Review path sequence and module coverage.',
            'Check how scenario coverage aligns with user roles and training rollout needs.',
            'Use filters to focus on the path, module, or status being reviewed.',
        ],
    ],
    [
        'heading' => 'Governance Use',
        'items' => [
            'Use the matrix before creating assignments to confirm the intended training set.',
            'Compare planned coverage with actual scenario catalogue records.',
            'Update the underlying training plan when rollout scope changes.',
        ],
    ],
];
$note = 'The matrix supports planning and governance; scenario content is maintained in the Training Catalogue.';
require __DIR__ . '/_TrainingHelp.php';
