<?php
declare(strict_types=1);

$title = 'Task Form';
$icon = 'bi-check2-square';
$intro = 'Use this form to create, assign, update, and respond to workflow tasks. A task may stand alone or be linked to a project, requirement, issue, or another workflow entity.';
$sections = [
    [
        'heading' => 'Tabs',
        'items' => [
            '<strong>Details:</strong> Maintain the title, type, status, description, priority, due date, and related context.',
            '<strong>Project Plan:</strong> Connect the task to project schedule information, dependencies, and planning fields.',
            '<strong>Assignment:</strong> Assign the task to a user or workflow user group. Groups expand into trackable user tasks.',
            '<strong>Notifications:</strong> Manage reminder and notification options where available.',
        ],
    ],
    [
        'heading' => 'Lower Panels',
        'items' => [
            '<strong>Comments:</strong> Record progress notes and decisions.',
            '<strong>Attachments:</strong> Upload supporting documents or evidence.',
            '<strong>Links:</strong> Maintain traceability to requirements, issues, projects, or other work.',
            '<strong>History and views:</strong> Review activity, transitions, and read tracking where enabled.',
        ],
    ],
];
$note = 'If validation fails, the form opens the tab containing the first invalid field so you can correct it quickly.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
