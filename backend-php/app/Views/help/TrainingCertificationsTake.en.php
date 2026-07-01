<?php
declare(strict_types=1);

$title = 'Take Certification';
$icon = 'bi-pencil-square';
$intro = 'Use this screen to complete a certification attempt for one module.';
$sections = [
    [
        'heading' => 'Completing The Test',
        'items' => [
            'Read every question and select the best answer.',
            'Answer all required questions before submitting.',
            'Submit only when you are ready for the attempt to be scored.',
        ],
    ],
    [
        'heading' => 'After Submission',
        'items' => [
            'The system calculates your score and pass result automatically.',
            'Your attempt is retained for training and certification history.',
            'Return to Certifications or the Training Dashboard after reviewing the result.',
        ],
    ],
];
$note = 'Certification attempts are final once submitted.';
require __DIR__ . '/_TrainingHelp.php';
