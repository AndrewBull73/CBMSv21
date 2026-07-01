<?php
declare(strict_types=1);

$title = 'Workflow User Group Setup';
$icon = 'bi-people';
$intro = 'Use this form to define a reusable workflow user group and maintain the members who should receive generated tasks.';
$sections = [
    [
        'heading' => 'Key Fields',
        'items' => [
            '<strong>Group Name:</strong> Use a clear name that task creators will recognize.',
            '<strong>Description:</strong> Explain when this group should be selected.',
            '<strong>Active:</strong> Active groups are available for task assignment; inactive groups are retained for history.',
            '<strong>Members:</strong> Select the users who should receive individual tasks when the group is used.',
        ],
    ],
    [
        'heading' => 'Good Practice',
        'items' => [
            'Review membership before major project phases, training runs, or approval cycles.',
            'Avoid broad groups unless every member truly needs an individual task.',
            'Use inactive status instead of renaming a group for a completely different purpose.',
        ],
    ],
];
$note = 'Group changes affect future task creation. Existing tasks remain assigned to the users they were created for.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
