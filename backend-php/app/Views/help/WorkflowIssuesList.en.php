<?php
declare(strict_types=1);

$title = 'Issues Log';
$icon = 'bi-exclamation-triangle';
$intro = 'Use the Issues Log to record and track defects, gaps, risks, decisions, data issues, dependencies, change requests, and other blockers connected to workflow projects and requirements.';
$sections = [
    [
        'heading' => 'What You Can Do Here',
        'items' => [
            'Filter issues by project, status, severity, and search text.',
            'Create a new issue using the current selected project context where available.',
            'Open an issue to update ownership, status, dates, details, attachments, and linked tasks.',
            'Create a task from an issue so follow-up work is assigned and traceable.',
            'Print or export the filtered issues list.',
        ],
    ],
    [
        'heading' => 'Issue Types',
        'items' => [
            '<strong>Bug:</strong> Something is not working as expected.',
            '<strong>Gap:</strong> A needed process, requirement, or capability is missing.',
            '<strong>Risk:</strong> A potential event or condition could affect delivery.',
            '<strong>Decision:</strong> A decision is needed or should be recorded.',
            '<strong>Data:</strong> The issue concerns data quality, mapping, migration, or reference data.',
            '<strong>Dependency:</strong> Progress depends on another task, team, system, or decision.',
            '<strong>Change Request:</strong> The issue proposes a scope, design, or delivery change.',
            '<strong>Other:</strong> Use only when the issue does not fit the standard categories.',
        ],
    ],
];
$note = 'On create, Issue Type must be selected by the user. The database default exists only for compatibility with older installs and integrations.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
