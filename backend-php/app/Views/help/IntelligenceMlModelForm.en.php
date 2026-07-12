<?php
declare(strict_types=1);

$helpEyebrow = 'ML Help';
$title = 'Register Or Edit ML Model';
$icon = 'bi-pencil-square';
$intro = 'Use this form to define the governed metadata and approved data source for one machine-learning model.';
$sections = [
    [
        'heading' => 'Key Fields',
        'icon' => 'bi-input-cursor-text',
        'items' => [
            '<strong>Model Code</strong> is the stable technical code used in training, prediction, and workflow history.',
            '<strong>Model Name</strong> is the human-readable name shown to users.',
            '<strong>Use Case</strong> identifies the business purpose, such as budget execution risk.',
            '<strong>Model Type</strong> classifies the analytical approach.',
            '<strong>Status</strong> records the lifecycle stage. Use workflow actions for normal status movement where possible.',
        ],
    ],
    [
        'heading' => 'Approved Source Setup',
        'icon' => 'bi-database-check',
        'items' => [
            '<strong>Approved View/Table</strong> should point to a governed SQL object, usually a semantic or training view.',
            '<strong>Target Column</strong> is the value the model learns or predicts, such as RiskScore.',
            '<strong>Feature Columns</strong> are the input columns sent to the Intelligence Engine. Enter one column per line.',
            'Use columns that exist in the approved source object and avoid raw sensitive fields unless they are explicitly approved.',
        ],
    ],
    [
        'heading' => 'Accuracy Field',
        'icon' => 'bi-speedometer2',
        'items' => [
            'Accuracy is normally populated after training.',
            'Leave it blank when first creating a model unless you are migrating a known value.',
            'For regression-style models, other metrics such as R squared, RMSE, and MAE may be more meaningful than a single accuracy score.',
        ],
    ],
    [
        'heading' => 'Before Saving',
        'icon' => 'bi-ui-checks',
        'items' => [
            'Confirm the approved source exists in the database.',
            'Confirm the target and feature columns match the approved source column names.',
            'Confirm the model code is unique and meaningful.',
            'Use the model detail workflow after saving to submit, train, review, and approve.',
        ],
    ],
];
$note = 'A model definition controls what data is sent to the Intelligence Engine. Treat changes as governed configuration.';
require __DIR__ . '/_ScreenHelpTemplate.php';
