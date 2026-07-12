<?php
declare(strict_types=1);

$title = 'My Test Scripts';
$icon = 'bi-person-check';
$intro = 'Use My Test Scripts as your personal testing work queue. It shows the scripts assigned to you, grouped by module, with the current testing status and due date.';
$sections = [
    [
        'heading' => 'Purpose Of This Screen',
        'icon' => 'bi-compass',
        'items' => [
            '<strong>My Test Scripts is the tester launch page.</strong> Use it to see the scripts you are expected to run and to start, resume, or retest assigned work.',
            '<strong>Assignments are user-specific.</strong> Coordinators assign scripts from Assign Test Scripts; this screen shows the scripts assigned to the signed-in user.',
            '<strong>Module grouping helps focus testing.</strong> Scripts are grouped by module so testers can work through one business area at a time.',
        ],
    ],
    [
        'heading' => 'How To Run A Script',
        'icon' => 'bi-play-circle',
        'items' => [
            '<strong>Start Script</strong> begins a new run and opens the runner for the selected test script.',
            '<strong>Resume Script</strong> continues an active run that was already started but not yet saved.',
            '<strong>Retest Script</strong> starts another run after a prior result was recorded. Use this when a failed or blocked script has been corrected, or when a coordinator asks for another pass.',
            '<strong>View Results</strong> opens the result history for that script so you can review prior runs, defects, and evidence.',
        ],
    ],
    [
        'heading' => 'Status And Due Dates',
        'icon' => 'bi-tags',
        'items' => [
            '<strong>Not Run</strong> means no result has been recorded for the assignment yet.',
            '<strong>In Progress</strong> means a run has been started or a previous run was failed/blocked and still needs follow-up.',
            '<strong>Passed, Failed, and Blocked</strong> reflect the latest saved test result for the script.',
            '<strong>Assignment Due</strong> appears when a coordinator has set a target completion date.',
        ],
    ],
    [
        'heading' => 'Good Testing Practice',
        'icon' => 'bi-check2-circle',
        'items' => [
            'Run the script in the intended fiscal year, version, and data-object context before recording the result.',
            'Attach screenshot evidence when the result needs proof or when the script fails or is blocked.',
            'Use clear outcome notes and defect references so coordinators can understand what happened without re-running the test immediately.',
        ],
    ],
];
$note = 'If an expected script is missing, ask a Testing Scripts administrator to check Assign Test Scripts and confirm that the assignment is active for your user account.';
require __DIR__ . '/_ScreenHelpTemplate.php';
