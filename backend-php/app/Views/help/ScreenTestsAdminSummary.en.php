<?php
declare(strict_types=1);

$title = 'Testing Summary';
$icon = 'bi-bar-chart-line';
$intro = 'Use Testing Summary to monitor assigned testing progress by module, tester, and script.';
$sections = [
    [
        'heading' => 'Purpose Of This Screen',
        'icon' => 'bi-speedometer2',
        'items' => [
            '<strong>Testing Summary is the coordinator dashboard.</strong> It shows total assigned scripts, not-started work, in-progress work, completed work, and overdue assignments.',
            '<strong>Progress by Module</strong> shows completion and overdue counts for each module so coordinators can identify weak coverage areas.',
            '<strong>Progress by Tester</strong> shows each tester&rsquo;s assigned workload and completion rate.',
            '<strong>Assignment Detail</strong> lists each user/script assignment with the latest result, run date, defect reference, and reset action.',
        ],
    ],
    [
        'heading' => 'Coordinator Actions',
        'icon' => 'bi-tools',
        'items' => [
            '<strong>Filter</strong> by module, assignment status, or search text to focus on one rollout, tester, script, result, or defect.',
            '<strong>Run History</strong> opens detailed result history and evidence for saved runs.',
            '<strong>Reset</strong> moves an assignment back to Not Started for retesting. It does not delete old runs or evidence.',
            '<strong>Assign Scripts</strong> opens the assignment screen when additional users or scripts need to be added.',
        ],
    ],
    [
        'heading' => 'Recommended Review Pattern',
        'icon' => 'bi-check2-circle',
        'items' => [
            'Start by checking overdue and in-progress counts.',
            'Review modules with low completion percentages before sign-off.',
            'Open failed or blocked detail rows to inspect defect references and evidence.',
            'Reset assignments only when the tester should run the script again after a fix or clarification.',
        ],
    ],
];
$note = 'Testing Summary depends on assignment storage. Run create_tblScreenTestAssignments.sql before using assignment progress reporting.';
require __DIR__ . '/_ScreenHelpTemplate.php';
