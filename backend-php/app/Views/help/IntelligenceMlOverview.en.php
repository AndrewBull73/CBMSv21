<?php
declare(strict_types=1);

$helpEyebrow = 'ML Concept';
$title = 'Machine Learning Overview';
$icon = 'bi-diagram-3';
$intro = 'The CBMS Machine Learning area provides governed, advisory analysis over approved budget datasets so senior users can identify budget execution risks that need human review.';
$sections = [
    [
        'heading' => 'What ML Is For',
        'icon' => 'bi-compass',
        'items' => [
            'Prioritise unusual or risky budget execution patterns for review.',
            'Support analysts by highlighting budget lines where actuals, budget, execution rates, or prior-year trends look unusual.',
            'Create a governed audit trail showing which model and prediction outputs were reviewed, accepted, dismissed, or referred for follow-up.',
        ],
    ],
    [
        'heading' => 'Core Concepts',
        'icon' => 'bi-layers',
        'items' => [
            '<strong>ML Models</strong> define the approved source view, target column, feature columns, model type, and lifecycle status.',
            '<strong>Training Runs</strong> send approved rows to the Python Intelligence Engine so a model artifact can be produced.',
            '<strong>Predictions</strong> apply the trained artifact to current analysis rows and return advisory risk scores.',
            '<strong>Drill Through</strong> lets reviewers inspect stored context and the underlying ledger rows behind a prediction.',
            '<strong>Workflow</strong> records model-level governance and prediction-level review decisions.',
        ],
    ],
    [
        'heading' => 'Recommended Operating Flow',
        'icon' => 'bi-list-ol',
        'items' => [
            '<strong>1. Confirm the source dataset</strong> before relying on model results. Budget execution ML depends on clean fiscal-year, version-role, segment, budget, and actual data.',
            '<strong>2. Register or review the model</strong> in the ML Model Register. Confirm the approved source, target column, and features.',
            '<strong>3. Submit the model for review</strong> when configuration is ready.',
            '<strong>4. Run training</strong> after the Intelligence Engine is running and the dataset is ready.',
            '<strong>5. Run predictions</strong> to generate a prioritised list of budget lines needing review.',
            '<strong>6. Drill into each material prediction</strong> and record whether it is accepted, dismissed, reviewed, or referred.',
            '<strong>7. Use model workflow</strong> to mark results reviewed, request changes, approve the model, or retire it.',
        ],
    ],
    [
        'heading' => 'Important Safeguards',
        'icon' => 'bi-shield-check',
        'items' => [
            'ML results are advisory and should not be treated as audit findings without human review.',
            'A high-risk prediction means the line deserves attention; it does not prove error, fraud, or non-compliance.',
            'Always check version-role mapping before concluding that actual expenditure has no budget.',
            'Use prediction workflow notes to record what was checked and why a decision was made.',
        ],
    ],
];
$note = 'Use the page-specific Help button on each ML screen for field and action guidance.';
require __DIR__ . '/_ScreenHelpTemplate.php';
