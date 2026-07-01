<?php
declare(strict_types=1);

$title = 'Project Setup';
$icon = 'bi-kanban';
$intro = 'Use this form to create or maintain a workflow project. The project record is the parent context for requirements, tasks, issues, and schedule tracking.';
$sections = [
    [
        'heading' => 'Tabs',
        'items' => [
            '<strong>Details:</strong> Enter the project name, code, status, description, priority, and core classification.',
            '<strong>Team:</strong> Set project ownership and team responsibility so work can be routed and reviewed consistently.',
            '<strong>Schedule:</strong> Maintain planned dates and review the project Gantt/task section when available.',
        ],
    ],
    [
        'heading' => 'Good Practice',
        'items' => [
            'Use clear project names that will still make sense in issue, requirement, and task lists.',
            'Keep planned dates realistic because tasks created under the project may be validated against the project date range.',
            'Set the owner early. Ownership helps users understand who coordinates decisions and follow-up.',
            'Use inactive/archive only when the project should no longer appear in normal active work lists.',
        ],
    ],
    [
        'heading' => 'Related Work',
        'items' => [
            'After saving, use project actions to create requirements, issues, or tasks tied to this project.',
            'Open the project summary to see linked work, open issues, schedule, and task status in one place.',
        ],
    ],
];
$note = 'Links ending in <code>#workflow-project-gantt</code> open the Schedule tab automatically.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
