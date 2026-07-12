<?php
declare(strict_types=1);

$title = 'All Test Scripts';
$icon = 'bi-list-check';
$intro = 'Use All Test Scripts to browse the full testing catalogue, including scripts that may not be assigned to you.';
$sections = [
    [
        'heading' => 'Purpose Of This Screen',
        'icon' => 'bi-journal-text',
        'items' => [
            '<strong>All Test Scripts is the full catalogue.</strong> It helps testers, coordinators, and administrators find available scripts by module, status, or search text.',
            '<strong>Assigned to Me</strong> narrows the view back to your personal work queue when assignment storage is installed.',
            '<strong>All Scripts</strong> shows the wider catalogue and is useful for ad hoc checks, exploratory testing, and review of available coverage.',
        ],
    ],
    [
        'heading' => 'Filters',
        'icon' => 'bi-funnel',
        'items' => [
            '<strong>Search</strong> looks across script code, title, description, screen family, and audience.',
            '<strong>Module filter</strong> focuses the catalogue on a business module such as Planning, Budget Execution, Analytics, Workflow Operations, Training, or Security & Access.',
            '<strong>Result filter</strong> helps find scripts by latest run state, such as Not Run, In Progress, Passed, Failed, or Blocked.',
        ],
    ],
    [
        'heading' => 'When To Use This Screen',
        'icon' => 'bi-lightning-charge',
        'items' => [
            'Use the assigned view for normal testing work.',
            'Use the all-scripts view when a coordinator asks you to run an unassigned script or when validating catalogue coverage.',
            'Use View Results before retesting if you need to understand the last result, defect reference, or evidence already captured.',
        ],
    ],
];
$note = 'Running an unassigned script can still create a test run result, but it will not count as completion of an assignment unless a matching active assignment exists.';
require __DIR__ . '/_ScreenHelpTemplate.php';
