<?php
declare(strict_types=1);

$title = 'Workflow User Groups';
$icon = 'bi-people';
$intro = 'Use Workflow User Groups to maintain reusable recipient groups for task creation. When a task is assigned to a group, CBMS can create one trackable task for each active group member.';
$sections = [
    [
        'heading' => 'What You Can Do Here',
        'items' => [
            'Search for groups by name or description.',
            'Filter active and inactive groups.',
            'Create or edit groups and maintain their active members.',
            'Review group counts, active member counts, and task-use purpose.',
        ],
    ],
    [
        'heading' => 'When To Use Groups',
        'items' => [
            'Use groups for recurring audiences such as reviewers, approvers, implementation teams, or training participants.',
            'Keep membership current so new tasks go to the correct users.',
            'Deactivate a group when it should no longer be selected for new task assignments.',
        ],
    ],
];
$note = 'Workflow user groups simplify assignment, but generated tasks are still tracked per recipient for accountability.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
