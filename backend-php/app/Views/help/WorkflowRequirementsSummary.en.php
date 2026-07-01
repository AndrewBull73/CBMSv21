<?php
declare(strict_types=1);

$title = 'Requirements Summary';
$icon = 'bi-clipboard-check';
$intro = 'The Requirements Summary gives a reporting view of requirement coverage, status, and readiness for the selected project or current filter set.';
$sections = [
    [
        'heading' => 'Use This Screen To',
        'items' => [
            'Review counts by status, priority, type, and project.',
            'Identify requirements that still need delivery work, testing evidence, training links, or issue follow-up.',
            'Print or export the current summary for working group or steering committee review.',
        ],
    ],
    [
        'heading' => 'How To Read It',
        'items' => [
            'Start with the selected project cue to confirm the summary is scoped correctly.',
            'Use status and gap counts to find requirements that need action before closure.',
            'Open the matrix for a more detailed row-by-row traceability check.',
        ],
    ],
];
$note = 'This is a reporting screen. Update requirement details, links, and evidence from the requirement form or related task and issue screens.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
