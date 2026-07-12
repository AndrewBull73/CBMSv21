<?php
declare(strict_types=1);

$title = 'Test Script Runner';
$icon = 'bi-play-circle';
$intro = 'Use the Test Script Runner to follow one script, open the target screen, capture evidence, and save the test result.';
$sections = [
    [
        'heading' => 'Runner Workflow',
        'icon' => 'bi-arrow-repeat',
        'items' => [
            '<strong>Review the script details first.</strong> Check the purpose, prerequisites, test data, expected visible result, and expected data result before opening the target screen.',
            '<strong>Start Test Run</strong> creates an active run and records the attempt number.',
            '<strong>Open Target Screen</strong> opens the CBMS screen being tested in the current context.',
            '<strong>Save Result</strong> records Passed, Failed, or Blocked, updates the assignment status, and returns you to My Test Scripts.',
        ],
    ],
    [
        'heading' => 'Recording Results',
        'icon' => 'bi-clipboard-check',
        'items' => [
            '<strong>Passed</strong> means the script completed successfully and expected results were observed.',
            '<strong>Failed</strong> means the screen loaded but did not behave as expected. Include a clear outcome summary and defect reference where possible.',
            '<strong>Blocked</strong> means the test could not be completed because of missing setup, unavailable data, access issues, or another blocker.',
            '<strong>Verification status</strong> records whether manual verification passed, failed, or was not run.',
        ],
    ],
    [
        'heading' => 'Evidence',
        'icon' => 'bi-paperclip',
        'items' => [
            '<strong>Capture Screenshot</strong> attaches a screenshot from the browser when screen capture is available and attachment storage is installed.',
            '<strong>Upload File</strong> can attach a screenshot, PDF, text note, or other allowed evidence file to the active run.',
            'Evidence is saved against the test run history, not just the current assignment, so coordinators can review it later from Test Results.',
        ],
    ],
];
$note = 'If storage warnings appear, ask an administrator to run the relevant screen test SQL setup scripts before relying on shared run history or screenshot evidence.';
require __DIR__ . '/_ScreenHelpTemplate.php';
