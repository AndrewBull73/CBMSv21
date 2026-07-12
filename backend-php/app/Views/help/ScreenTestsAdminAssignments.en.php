<?php
declare(strict_types=1);

$title = 'Assign Test Scripts';
$icon = 'bi-person-check';
$intro = 'Use Assign Test Scripts to assign individual scripts or whole module groups to users and workflow user groups.';
$sections = [
    [
        'heading' => 'Purpose Of This Screen',
        'icon' => 'bi-people',
        'items' => [
            '<strong>Assign Test Scripts creates tester work.</strong> Assigned scripts appear in each tester&rsquo;s My Test Scripts queue.',
            '<strong>Users</strong> are selected through the active-user search control for one-off or exception assignments.',
            '<strong>Workflow User Groups</strong> assign every active member of a reusable group in one step.',
            '<strong>Module Group</strong> assigns all scripts in the selected module. Specific script checkboxes can be used instead, or combined with a module group.',
        ],
    ],
    [
        'heading' => 'Assignment Fields',
        'icon' => 'bi-input-cursor-text',
        'items' => [
            '<strong>Due Date is optional.</strong> Use it when a test cycle has a deadline; leave it blank for open-ended assignments.',
            '<strong>Notes</strong> should explain context, special test data, rollout instructions, or what the tester should pay attention to.',
            '<strong>Duplicate open assignments are skipped automatically.</strong> This prevents the same user from receiving duplicate active work for the same script.',
        ],
    ],
    [
        'heading' => 'Open Assignments Table',
        'icon' => 'bi-table',
        'items' => [
            'Review current open assignments by user, script, module, due date, and status.',
            'Use remove only when an assignment was created in error or is no longer required.',
            'Use Testing Summary when you need completion percentages, latest results, overdue counts, or reset-for-retest actions.',
        ],
    ],
];
$note = 'Assignment storage must be installed before this screen can create assignments. Workflow user groups are optional but useful for repeated testing audiences.';
require __DIR__ . '/_ScreenHelpTemplate.php';
