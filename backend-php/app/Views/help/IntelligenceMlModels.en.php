<?php
declare(strict_types=1);

$helpEyebrow = 'ML Help';
$title = 'ML Model Register';
$icon = 'bi-diagram-3';
$intro = 'Use the ML Model Register to find, open, edit, approve, and monitor registered machine-learning models.';
$sections = [
    [
        'heading' => 'Purpose',
        'icon' => 'bi-compass',
        'items' => [
            'View all registered ML models and their current governance status.',
            'See how many training runs and predictions exist for each model.',
            'Open the model detail screen to train, predict, interpret, or review workflow history.',
        ],
    ],
    [
        'heading' => 'Main Columns',
        'icon' => 'bi-table',
        'items' => [
            '<strong>Model</strong> shows the user-facing name and stable model code.',
            '<strong>Use Case</strong> explains the analytical purpose, such as budget execution risk.',
            '<strong>Type</strong> identifies the model method category, such as regression or anomaly detection.',
            '<strong>Status</strong> shows whether the model is draft, ready, trained, reviewed, approved, or retired.',
            '<strong>Runs and Predictions</strong> show whether the model has already been exercised.',
        ],
    ],
    [
        'heading' => 'Common Actions',
        'icon' => 'bi-ui-checks',
        'items' => [
            '<strong>Register Model</strong> creates a new model definition.',
            '<strong>Open</strong> goes to model detail for training, predictions, workflow, and interpretation.',
            '<strong>Edit</strong> changes model metadata and approved source configuration.',
            '<strong>Approve</strong> records approval for models that are ready for governed use.',
        ],
    ],
    [
        'heading' => 'Good Practice',
        'icon' => 'bi-check2-square',
        'items' => [
            'Do not approve a model until the source dataset and prediction results have been reviewed.',
            'Use stable model codes because they become part of training, prediction, and workflow history.',
            'Keep retired models in the register for audit traceability instead of deleting their history.',
        ],
    ],
];
$note = 'The register is the entry point. Most ML operational work happens from the model detail screen.';
require __DIR__ . '/_ScreenHelpTemplate.php';
