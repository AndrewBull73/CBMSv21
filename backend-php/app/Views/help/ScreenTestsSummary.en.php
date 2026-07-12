<?php
declare(strict_types=1);

$title = 'Test Results';
$icon = 'bi-table';
$intro = 'Use Test Results to review assignment progress and saved run history, including outcomes, verification status, evidence, and defect references.';
$sections = [
    [
        'heading' => 'Purpose Of This Screen',
        'icon' => 'bi-clipboard-data',
        'items' => [
            '<strong>Assignment Progress</strong> shows assigned scripts, current assignment status, latest result, and latest run date.',
            '<strong>Run History</strong> shows each saved test run with outcome notes, context, verification status, defect reference, and evidence links.',
            '<strong>Coordinators with results access</strong> can review activity across users. Testers normally use it to review their own results.',
        ],
    ],
    [
        'heading' => 'Filters',
        'icon' => 'bi-funnel',
        'items' => [
            'Filter by script, module, result, verification status, or search text to narrow both assignment progress and run history.',
            'Use the result filter to find failed or blocked scripts that need review or retesting.',
            'Use the evidence column to open attached screenshots or files for a saved run.',
        ],
    ],
    [
        'heading' => 'How To Interpret Results',
        'icon' => 'bi-search',
        'items' => [
            '<strong>Assignment status</strong> tells you whether the assigned work is not started, in progress, or completed.',
            '<strong>Latest result</strong> comes from the newest saved run for that user and script.',
            '<strong>Run history</strong> keeps prior attempts, so retesting does not erase earlier failures, blockers, or evidence.',
        ],
    ],
];
$note = 'Test Results is an audit and review screen. Use My Test Scripts to launch assigned testing work.';
require __DIR__ . '/_ScreenHelpTemplate.php';
