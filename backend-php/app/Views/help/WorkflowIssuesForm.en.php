<?php
declare(strict_types=1);

$title = 'Issue Form';
$icon = 'bi-exclamation-triangle';
$intro = 'Use this form to record the detail, ownership, evidence, and follow-up actions for a workflow issue.';
$sections = [
    [
        'heading' => 'Key Fields',
        'items' => [
            '<strong>Issue Code:</strong> Generated when the issue is saved.',
            '<strong>Project:</strong> Required context for the issue. It may default from the sticky selected project.',
            '<strong>Requirement:</strong> Optional link to the affected requirement. The list is filtered by selected project.',
            '<strong>Issue Type:</strong> Required on create. Choose the category that best explains the issue.',
            '<strong>Severity and Priority:</strong> Use severity for impact and priority for response importance.',
            '<strong>Status:</strong> Tracks whether the issue is open, in progress, waiting, resolved, or closed.',
            '<strong>Owner and Due Date:</strong> Identify who is driving the resolution and when follow-up is expected.',
        ],
    ],
    [
        'heading' => 'Working The Issue',
        'items' => [
            'Add attachments for screenshots, emails, documents, or evidence.',
            'Create a task from the issue when someone needs assigned follow-up work.',
            'Update the issue status as decisions are made or corrective work is completed.',
            'Close the issue only when the resolution is clear and any linked tasks or requirements have been reviewed.',
        ],
    ],
];
$note = 'If the project changes, re-check the requirement field. Requirement options are project-specific.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
