<?php
declare(strict_types=1);

$helpEyebrow = 'ML Help';
$title = 'ML Prediction Drill Through';
$icon = 'bi-search';
$intro = 'Use Prediction Drill Through to review one flagged prediction, inspect the source evidence, and record the review decision.';
$sections = [
    [
        'heading' => 'What This Screen Shows',
        'icon' => 'bi-card-checklist',
        'items' => [
            'The summary cards identify the budget line, predicted risk, reference risk, and creation date.',
            'Stored Prediction Context shows the data captured when the prediction was generated.',
            'Underlying Ledger Records shows matching rows from the budget ledger analysis source.',
            'Indexed Drill Snapshot shows the fast stored detail rows captured for this prediction.',
            'Raw Prediction Details shows the full JSON payload for technical review.',
        ],
    ],
    [
        'heading' => 'Prediction Workflow',
        'icon' => 'bi-kanban',
        'items' => [
            '<strong>Mark prediction reviewed</strong> records that the item has been checked.',
            '<strong>Accept as risk item</strong> records that the prediction appears valid and should remain visible for follow-up.',
            '<strong>Dismiss after review</strong> records that the item was explainable, immaterial, or not useful.',
            '<strong>Refer for follow-up</strong> records that another user, unit, or process should investigate.',
            'Use review notes to explain the decision and evidence checked.',
        ],
    ],
    [
        'heading' => 'How To Verify A Prediction',
        'icon' => 'bi-check2-square',
        'items' => [
            'Compare budget, actual, available balance, execution rate, and anomaly reason.',
            'Confirm the segment, program, and economic code are correct.',
            'Check whether actual expenditure is expected to sit in the execution version for that fiscal year.',
            'Review the underlying ledger records before accepting or dismissing a material prediction.',
        ],
    ],
    [
        'heading' => 'Common Outcomes',
        'icon' => 'bi-signpost-split',
        'items' => [
            'Accept the prediction when the data supports a real budget execution risk.',
            'Dismiss it when the pattern is expected, duplicate, immaterial, or caused by known data setup.',
            'Refer it when budget, accounting, or programme staff need to confirm coding or authority.',
        ],
    ],
];
$note = 'Prediction review is separate from model approval. Reviewing a prediction records the decision for that specific flagged budget line.';
require __DIR__ . '/_ScreenHelpTemplate.php';
