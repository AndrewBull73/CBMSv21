<?php
declare(strict_types=1);

$title = 'Certification Results';
$icon = 'bi-table';
$intro = 'Use this administration screen to review certification attempts across users and modules.';
$sections = [
    [
        'heading' => 'Filters',
        'items' => [
            'Filter by module, certification, user, or pass status.',
            'Use the latest attempt details when checking a user current certification state.',
            'Reset filters before moving to a different reporting question.',
        ],
    ],
    [
        'heading' => 'Reviewing Attempts',
        'items' => [
            'Compare score percentage with the configured pass mark.',
            'Review attempt number, submitted date, and pass result.',
            'Use this screen with Training Summary for full training and certification reporting.',
        ],
    ],
];
$note = 'This screen is intended for Training Administration users.';
require __DIR__ . '/_TrainingHelp.php';
