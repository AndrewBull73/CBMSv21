<?php
declare(strict_types=1);

$helpEyebrow = 'Workflow Operations Concept';
$title = 'Workflow Operations Module Overview';
$icon = 'bi-diagram-3';
$intro = 'Workflow Operations is the integrated governance and delivery layer for managing project work, requirements, issues, assignments, decisions, and evidence inside CBMS.';
$sections = [
    [
        'heading' => 'What The Module Is For',
        'icon' => 'bi-compass',
        'items' => [
            'Bring project delivery work, requirements, tasks, issues, decisions, and supporting evidence into one connected operational workspace.',
            'Give project teams and managers visibility over the whole project lifecycle, from planned outcomes through assigned work, blockers, testing, training, and closure evidence.',
            'Strengthen governance by making ownership, status, dependencies, issues, and follow-up actions visible rather than scattered across emails, spreadsheets, and separate documents.',
        ],
    ],
    [
        'heading' => 'Governance, Control, And Quality',
        'icon' => 'bi-shield-check',
        'items' => [
            'The module provides a control framework for project delivery by linking requirements, tasks, issues, dates, owners, attachments, and decisions in one place.',
            'Integrated tracking improves integrity because users can see whether work has an approved requirement, assigned task, open issue, testing evidence, or training follow-up.',
            'Project summaries and matrix views give managers visibility over progress, gaps, blockers, and readiness before they affect delivery quality.',
            'Keeping this governance inside the application streamlines development, maintenance, and support because the operational record stays close to the system being changed.',
            'The result is a more efficient lifecycle: less duplication, clearer accountability, better evidence, stronger quality control, and easier handover between delivery and support teams.',
        ],
    ],
    [
        'heading' => 'Core Concepts',
        'icon' => 'bi-layers',
        'items' => [
            '<strong>Projects</strong> are the main containers. They group the work, dates, team ownership, requirements, tasks, and issues for an initiative.',
            '<strong>Requirements</strong> describe what must be delivered. They can be high-level or detailed and should include acceptance criteria.',
            '<strong>Tasks</strong> are assigned units of work. They carry due dates, owners, status, comments, files, reminders, and links to related records.',
            '<strong>Issues</strong> record blockers, defects, risks, gaps, decisions, dependencies, data problems, and change requests.',
            '<strong>Links and attachments</strong> provide the evidence trail between requirements, tasks, issues, testing, training, decisions, and project records.',
        ],
    ],
    [
        'heading' => 'Typical Flow',
        'icon' => 'bi-arrow-right-circle',
        'items' => [
            'Create or select a workflow project so the rest of the module has the correct project context.',
            'Capture requirements for the project, including acceptance criteria and any parent-child requirement structure.',
            'Create tasks from the project, requirement, or issue when someone needs assigned follow-up work.',
            'Record issues when delivery is blocked, a defect is found, a decision is needed, or scope changes need to be tracked.',
            'Use the Project Summary, Requirements Summary, and Requirements Matrix to review readiness, gaps, open work, and issue impacts.',
        ],
    ],
    [
        'heading' => 'How The Screens Fit Together',
        'icon' => 'bi-window-stack',
        'items' => [
            '<strong>Workflow Projects</strong> is the project register and entry point for project context.',
            '<strong>Project Summary</strong> is the project dashboard for overview, issues, linked work, schedule, and tasks.',
            '<strong>Workflow Requirements</strong> is the requirements register and maintenance area.',
            '<strong>Requirements Summary</strong> gives management-level counts and coverage indicators.',
            '<strong>Requirements Matrix</strong> is the traceability and gap view for tasks, testing, training, defects, and acceptance criteria.',
            '<strong>Workflow Tasks</strong> is the operational work queue for assigned, created, open, due, and completed tasks.',
            '<strong>Issues Log</strong> is the register of blockers, defects, risks, decisions, data problems, dependencies, and change requests.',
            '<strong>Workflow User Groups</strong> helps assign repeatable task audiences while still tracking tasks per recipient.',
        ],
    ],
    [
        'heading' => 'Good Operating Habits',
        'icon' => 'bi-check2-square',
        'items' => [
            'Start from the project when creating new work so tasks, requirements, and issues stay connected.',
            'Write acceptance criteria clearly enough that someone can test or verify completion.',
            'Create tasks for real follow-up work instead of leaving actions buried in comments.',
            'Link issues to requirements where the issue affects scope, delivery, testing, training, or readiness.',
            'Use summaries and matrix views before status meetings to find gaps before they become surprises.',
        ],
    ],
];
$note = 'This overview explains the module concept. Use the normal Help button on each screen for screen-specific instructions and field guidance.';
require __DIR__ . '/_ScreenHelpTemplate.php';
