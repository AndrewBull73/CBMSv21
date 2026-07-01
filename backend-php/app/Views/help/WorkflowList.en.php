<?php
declare(strict_types=1);

$title = 'Workflow Tasks';
$icon = 'bi-check2-square';
$intro = 'Use this screen to find and manage workflow tasks. Tasks are the assigned pieces of work that move projects, requirements, issues, reviews, and approvals forward.';
$sections = [
    [
        'heading' => 'Filters',
        'items' => [
            '<strong>Mine / Assigned / Created:</strong> Switch between work assigned to you and work you created.',
            '<strong>Status and Type:</strong> Narrow the list to open, completed, overdue, review, approval, or project tasks.',
            '<strong>Project:</strong> Focus tasks on the selected workflow project.',
            '<strong>Due state:</strong> Find overdue, due today, or soon-due work.',
        ],
    ],
    [
        'heading' => 'Task Actions',
        'items' => [
            'Open or edit a task to update details, respond, forward, transition status, add comments, or manage attachments.',
            'Create a task directly, or create it from a project, requirement, or issue so links are created automatically.',
            'Print or export the current task list for operational follow-up.',
        ],
    ],
];
$note = 'Delete is available only to permitted administrators or the task creator where allowed. Task deletion uses server-side permission checks even if a button is hidden.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
