<?php
declare(strict_types=1);

$title = 'Workflow Projects';
$icon = 'bi-kanban';
$intro = 'Use this screen as the register of workflow delivery projects. A project groups related requirements, tasks, issues, attachments, and progress reporting into one operational context.';
$sections = [
    [
        'heading' => 'What You Can Do Here',
        'items' => [
            '<strong>Search and filter projects</strong> by text, status, and active/inactive state.',
            '<strong>Create a project</strong> when a new implementation, improvement, release, or operational initiative needs tracking.',
            '<strong>Open the project summary</strong> to review project health, issues, linked work, schedule, and tasks.',
            '<strong>Create related work</strong> such as requirements, tasks, or issues while preserving the selected project context.',
            '<strong>Print or export</strong> the filtered project register for review meetings or offline analysis.',
        ],
    ],
    [
        'heading' => 'How To Use It',
        'items' => [
            'Start with search and status filters when the project list is long.',
            'Use <strong>Summary</strong> when you need the operational picture for one project.',
            'Use <strong>Edit</strong> when you need to maintain project details, team ownership, or schedule information.',
            'Use <strong>Reset</strong> to clear filters and return to the active project list.',
        ],
    ],
    [
        'heading' => 'Permissions And Deletion',
        'items' => [
            'Create and edit actions depend on your Workflow Operations permissions.',
            'Delete is shown only where your role allows it. Projects are archived/deactivated rather than physically removed.',
        ],
    ],
];
$note = 'Selecting or filtering by a project can update the sticky selected project context used by requirements, tasks, and issues screens.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
