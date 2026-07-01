<?php
declare(strict_types=1);

$title = 'Requirements Matrix';
$icon = 'bi-grid-3x3-gap';
$intro = 'The Requirements Matrix is the detailed traceability view. It helps confirm each requirement has the structured links and evidence needed for delivery readiness.';
$sections = [
    [
        'heading' => 'Common Gap Indicators',
        'items' => [
            '<strong>Missing acceptance criteria:</strong> The definition of done has not been recorded.',
            '<strong>No linked task:</strong> No delivery task has been linked to the requirement.',
            '<strong>Open linked tasks:</strong> Delivery work exists but is not complete.',
            '<strong>No testing link:</strong> Testing evidence or testing task is not linked.',
            '<strong>No training link:</strong> Training preparation or evidence is not linked.',
            '<strong>Has defect or issue links:</strong> Open issues may need attention before closure.',
        ],
    ],
    [
        'heading' => 'Recommended Review Pattern',
        'items' => [
            'Filter to the project you are reviewing.',
            'Sort or scan by gap columns to find incomplete traceability.',
            'Open the requirement or related task/issue to correct the underlying record.',
            'Export the matrix when you need an offline readiness checklist.',
        ],
    ],
];
$note = 'Matrix gaps are calculated from structured fields and workflow links. They do not read the meaning of free-text acceptance criteria.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
