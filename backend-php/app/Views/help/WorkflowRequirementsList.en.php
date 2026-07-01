<?php
declare(strict_types=1);

$title = 'Workflow Requirements';
$icon = 'bi-list-check';
$intro = 'Use this screen to manage business, operational, technical, and implementation requirements for workflow projects. Requirements describe what must be delivered and provide traceability to tasks, testing, training, and issues.';
$sections = [
    [
        'heading' => 'What You Can Do Here',
        'items' => [
            'Filter requirements by project, status, type, module, priority, or search text.',
            'Create high-level requirements or detailed requirements under a parent requirement.',
            'Open a requirement to maintain acceptance criteria, status, attachments, and linked work.',
            'Create tasks or issues from a requirement so delivery and defects remain traceable.',
            'Print or export the filtered requirement list.',
        ],
    ],
    [
        'heading' => 'Selected Project Context',
        'items' => [
            'If a project is selected, the list can default to that project on related workflow screens.',
            'Clearing the project filter clears the sticky selected project context.',
        ],
    ],
];
$note = 'Acceptance Criteria is free text and should describe the requirement definition of done. Matrix gaps are based on structured fields and links, not semantic interpretation of that text.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
