<?php
declare(strict_types=1);

$title = 'Requirement Form';
$icon = 'bi-card-checklist';
$intro = 'Use this form to define and maintain a workflow requirement. A requirement should be specific enough that users can understand the expected outcome and delivery teams can link tasks, evidence, and issues to it.';
$sections = [
    [
        'heading' => 'Key Fields',
        'items' => [
            '<strong>Project:</strong> Connects the requirement to the workflow project that owns the work.',
            '<strong>Parent Requirement:</strong> Use for detailed requirements that belong under a high-level requirement.',
            '<strong>Requirement Type and Priority:</strong> Help classify what kind of need this is and how important it is.',
            '<strong>Status:</strong> Tracks the requirement lifecycle from draft through review, approval, delivery, or closure.',
            '<strong>Acceptance Criteria:</strong> Describe the definition of done in clear, testable language.',
        ],
    ],
    [
        'heading' => 'Traceability Actions',
        'items' => [
            'Create a task when the requirement needs assigned delivery work.',
            'Create an issue when the requirement has a defect, gap, risk, decision, dependency, or change request.',
            'Use attachments for supporting documents, screenshots, specifications, or evidence.',
        ],
    ],
];
$note = 'Detailed requirements should stay aligned with their parent. If the parent changes scope, review child requirements, linked tasks, and open issues.';
require __DIR__ . '/_WorkflowOperationsHelp.php';
