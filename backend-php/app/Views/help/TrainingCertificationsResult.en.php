<?php
declare(strict_types=1);

$title = 'Certification Result';
$icon = 'bi-clipboard-check';
$intro = 'Use this screen to review the outcome of one certification attempt.';
$sections = [
    [
        'heading' => 'Result Review',
        'items' => [
            'Compare your score percentage with the pass percentage.',
            'Review whether the attempt passed or failed.',
            'Read explanations where answer feedback is shown.',
        ],
    ],
    [
        'heading' => 'Next Action',
        'items' => [
            'Return to Certifications to continue with another module or retake where allowed.',
            'Return to the Training Dashboard to see your overall training and certification status.',
            'Ask a trainer for support if you do not understand a failed result.',
        ],
    ],
];
$note = 'A passed result confirms certification according to the configured pass mark.';
require __DIR__ . '/_TrainingHelp.php';
