<?php
declare(strict_types=1);

$helpEyebrow = 'ML Help';
$title = 'ML Model Detail';
$icon = 'bi-kanban';
$intro = 'Use ML Model Detail to review model setup, run training and predictions, interpret outputs, and manage model workflow.';
$sections = [
    [
        'heading' => 'Overview Tab',
        'icon' => 'bi-info-circle',
        'items' => [
            'Shows the model source, target column, feature columns, status, accuracy, and governance actions.',
            'Use <strong>Run Training</strong> only after the approved source and feature list are correct.',
            'Use <strong>Run Predictions</strong> after a completed training run exists.',
            'Use <strong>AI Interpretation</strong> to generate an executive-style explanation of recent model results.',
        ],
    ],
    [
        'heading' => 'Predictions Tab',
        'icon' => 'bi-lightning-charge',
        'items' => [
            'Shows recent predictions consolidated by budget line so repeated period rows are easier to review.',
            'The Risk column shows the advisory risk level and score returned by the model.',
            'Why Flagged explains the likely anomaly type and recommended review action.',
            'Use <strong>Drill through</strong> to inspect the stored context and underlying ledger records.',
        ],
    ],
    [
        'heading' => 'Training Tab',
        'icon' => 'bi-clipboard-data',
        'items' => [
            'Shows completed and failed training runs.',
            'Latest Training Results summarise model metrics, algorithm, rows used, and top model terms.',
            'Raw metrics are available for technical review and troubleshooting.',
        ],
    ],
    [
        'heading' => 'Workflow Tab',
        'icon' => 'bi-kanban',
        'items' => [
            'Records model-level governance actions such as submit for review, request changes, mark results reviewed, approve, reopen, or retire.',
            'Workflow history also links to prediction-specific review events when a prediction has been reviewed from drill-through.',
            'Use notes to record what was reviewed and why a status changed.',
        ],
    ],
    [
        'heading' => 'How To Use Safely',
        'icon' => 'bi-shield-check',
        'items' => [
            'Treat the model as a prioritisation tool, not a final decision-maker.',
            'Review source data quality before interpreting predictions.',
            'Use drill-through and workflow notes to create an audit trail for material findings.',
            'Do not approve the model until test data and sample predictions make business sense.',
        ],
    ],
];
$note = 'Training and prediction calls may take time. Keep the Python Intelligence Engine running before using those actions.';
require __DIR__ . '/_ScreenHelpTemplate.php';
