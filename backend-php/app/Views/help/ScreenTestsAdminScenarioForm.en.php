<?php
declare(strict_types=1);

$title = 'Test Script Form';
$icon = 'bi-pencil-square';
$intro = 'Use the Test Script Form to create or update one test script definition.';
$sections = [
    [
        'heading' => 'Core Metadata',
        'icon' => 'bi-card-list',
        'items' => [
            '<strong>Scenario ID</strong> is the stable script code used by assignments, results, and launch links. Keep it short, unique, and stable.',
            '<strong>Module</strong> should use the shared CBMS module taxonomy so reporting is consistent.',
            '<strong>Screen family and target route</strong> identify the screen or workflow area being tested.',
            '<strong>Title, description, purpose, audience, and difficulty</strong> help testers understand why the script exists and who should run it.',
        ],
    ],
    [
        'heading' => 'Script Content',
        'icon' => 'bi-list-ol',
        'items' => [
            '<strong>Prerequisites</strong> define setup, permissions, data, or context needed before the script can run.',
            '<strong>Test data</strong> provides values or examples testers should use during the run.',
            '<strong>Steps</strong> should be ordered actions that a tester can follow without guessing.',
            '<strong>Expected visible results</strong> describe what the tester should see on screen.',
            '<strong>Expected data results</strong> describe database, report, or saved-record expectations.',
        ],
    ],
    [
        'heading' => 'Verification And Reset Guidance',
        'icon' => 'bi-database-check',
        'items' => [
            '<strong>Verification queries</strong> document checks that can confirm the data outcome when database review is appropriate.',
            '<strong>Reset scripts</strong> document cleanup actions or scripts that may be needed after testing.',
            '<strong>Save carefully</strong> because script changes affect future runs and future assignments, while existing run history remains as previously recorded.',
        ],
    ],
];
$note = 'Do not put sensitive passwords, production-only credentials, or destructive SQL directly into tester-facing script content.';
require __DIR__ . '/_ScreenHelpTemplate.php';
