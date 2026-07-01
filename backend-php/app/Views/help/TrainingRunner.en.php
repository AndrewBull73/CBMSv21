<?php
declare(strict_types=1);

$title = 'Training Runner';
$icon = 'bi-play-circle';
$intro = 'Use the runner to start, resume, and monitor one guided training scenario.';
$sections = [
    [
        'heading' => 'Running A Scenario',
        'items' => [
            'Start the scenario, then open the guided screen when prompted.',
            'Follow the highlighted target and instruction text on each step.',
            'Use restart or stop only when you intentionally want to reset or leave the current exercise.',
        ],
    ],
    [
        'heading' => 'Checkpoints And Notes',
        'items' => [
            'Answer checkpoint questions where the scenario asks for confirmation of understanding.',
            'Review trainer notes or progress details when a trainer is supporting the session.',
            'Return to the Training Dashboard when the scenario is complete.',
        ],
    ],
];
$note = 'The runner drives the training overlay; it does not replace the live CBMS screen being practised.';
require __DIR__ . '/_TrainingHelp.php';
