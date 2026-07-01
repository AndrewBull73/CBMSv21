<?php
declare(strict_types=1);

$title = 'Training Step Setup';
$icon = 'bi-input-cursor-text';
$intro = 'Use this form to configure one guided step in a training scenario.';
$sections = [
    [
        'heading' => 'Step Details',
        'items' => [
            'Set the route where the step should run.',
            'Set the target element ID for the control, field, table, or button to highlight.',
            'Write concise step instructions that tell the trainee what to do and why it matters.',
        ],
    ],
    [
        'heading' => 'Completion',
        'items' => [
            'Choose click target when the step should complete after clicking the target.',
            'Choose manual continue when the trainee must review information before moving on.',
            'Use sample keys only when the scenario should provide or validate sample data.',
        ],
    ],
];
$note = 'After saving step changes, test the scenario from the runner to confirm the overlay lands correctly.';
require __DIR__ . '/_TrainingHelp.php';
