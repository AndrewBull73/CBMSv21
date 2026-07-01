<?php
declare(strict_types=1);

$title = 'Project Summary';
$icon = 'bi-clipboard-data';
$intro = 'The Project Summary brings the project, issues, linked work, schedule, and tasks together for review and follow-up.';
$sections = [
    [
        'heading' => 'Tabs',
        'items' => [
            '<strong>Overview:</strong> Shows the project headline information and key status indicators.',
            '<strong>Issues:</strong> Lists project issues and gives quick access to open or create issue-related tasks.',
            '<strong>Linked Work:</strong> Shows related workflow links so dependencies and supporting records are visible.',
            '<strong>Schedule:</strong> Focuses on dates, plan progress, and the project schedule view.',
            '<strong>Tasks:</strong> Lists project tasks and their current status.',
        ],
    ],
    [
        'heading' => 'Recommended Workflow',
        'items' => [
            'Use <strong>Overview</strong> first to confirm the project context.',
            'Review <strong>Issues</strong> before status meetings so blockers are visible.',
            'Use <strong>Add Issue</strong> or <strong>Add Task</strong> from this screen to keep the project context attached automatically.',
            'Use the schedule and task tabs together to compare planned work against actual follow-up.',
        ],
    ],
];
$note = 'Project Summary is a review screen. Changes to project details are made from the project edit screen; changes to tasks, requirements, and issues are made from their own forms.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
