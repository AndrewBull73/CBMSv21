<?php
declare(strict_types=1);

$helpEyebrow = 'Training Concept';
$title = 'Training Module Overview';
$icon = 'bi-mortarboard';
$intro = 'The Training module manages guided scenarios, assigned learning paths, trainer operations, certification tests, and progress reporting inside CBMS.';
$sections = [
    [
        'heading' => 'What The Module Is For',
        'icon' => 'bi-compass',
        'items' => [
            'Give users a controlled way to learn CBMS screens through guided, step-by-step scenarios.',
            'Let trainers assign only the training that is relevant to a user, role, module, or rollout group.',
            'Record completion, evidence, trainer notes, and certification results so training status can be reviewed later.',
        ],
    ],
    [
        'heading' => 'Core Concepts',
        'icon' => 'bi-layers',
        'items' => [
            '<strong>Scenarios</strong> are guided exercises made from ordered steps that point to real CBMS screens and controls.',
            '<strong>Assignments</strong> determine which scenarios or paths appear on a user training dashboard.',
            '<strong>Paths</strong> group scenarios into a recommended module sequence for rollout or onboarding.',
            '<strong>Sessions</strong> support trainer-led events, attendance, evidence, and follow-up.',
            '<strong>Certifications</strong> are module tests with multiple choice questions and a configured pass mark.',
        ],
    ],
    [
        'heading' => 'How The Screens Fit Together',
        'icon' => 'bi-window-stack',
        'items' => [
            '<strong>Training Dashboard</strong> is the user view for assigned training and certification status.',
            '<strong>Training Scenarios</strong> is where a user launches assigned guided scenarios.',
            '<strong>Training Catalogue</strong> is where configuration users maintain scenario definitions and order.',
            '<strong>Training Operations</strong> is where trainers create paths, assign training, manage sessions, and resolve issues.',
            '<strong>Training Summary</strong> and <strong>Certification Results</strong> provide administration reporting.',
        ],
    ],
    [
        'heading' => 'Recommended Setup Order',
        'icon' => 'bi-list-ol',
        'items' => [
            '<strong>1. Confirm roles and permissions</strong> in <strong>Administration &gt; Access &amp; Security &gt; Roles</strong>. Make sure the right users have Training User, Training Administration, or Training Configuration access.',
            '<strong>2. Create the scenario shell</strong> in <strong>Training &gt; Training Catalogue</strong>. Add the scenario code, title, module name, audience, difficulty, active flag, runner route, and display order.',
            '<strong>3. Define the guided steps</strong> from <strong>Training &gt; Training Catalogue &gt; Steps</strong>. Add the live route, target element ID, completion mode, step title, instruction text, and sort order.',
            '<strong>4. Add translations if needed</strong> from <strong>Training &gt; Training Catalogue &gt; Translations</strong>. Translate scenario wording, step instructions, and samples for each supported language.',
            '<strong>5. Test the scenario as a builder</strong> from <strong>Training &gt; Training Scenarios</strong> or the scenario runner. Confirm the guided screen opens, the highlight lands on the correct element, and each completion mode advances correctly.',
            '<strong>6. Validate the setup</strong> in <strong>Training &gt; Training Validation</strong>. Fix missing routes, inactive steps, broken target IDs, duplicate ordering, or other configuration warnings before assigning users.',
            '<strong>7. Create a training course/path</strong> in <strong>Training &gt; Training Operations</strong> when a module needs a recommended sequence, such as overview, register, form, matrix, issues, tasks, then certification.',
            '<strong>8. Select the course/path and assign users</strong> in <strong>Training &gt; Training Operations</strong>. Use Selected Course / Path to keep the course active, then assign the path or an individual scenario so the user <strong>Training Dashboard</strong> shows only the training they need.',
            '<strong>9. Create module certifications</strong> in <strong>Training &gt; Certification Catalogue</strong>. Set the module, pass percentage, active flag, display order, and description.',
            '<strong>10. Maintain certification questions</strong> from <strong>Training &gt; Certification Catalogue &gt; Questions</strong>. Add multiple choice questions, options, correct answer keys, and explanations.',
            '<strong>11. Run an end-to-end learner test</strong> using a normal Training User account. Check <strong>Training &gt; Training Dashboard</strong>, launch an assigned scenario, complete progress, start a certification, and review the result.',
            '<strong>12. Monitor completion and certification</strong> through <strong>Training &gt; Training Summary</strong> and <strong>Training &gt; Certification Results</strong> after real users begin training.',
        ],
    ],
    [
        'heading' => 'Setup Checklist',
        'icon' => 'bi-ui-checks',
        'items' => [
            'The relevant users have the right Training role or permission.',
            'Each scenario has an active record, sensible sort order, and at least one active step.',
            'Each step target exists on the live screen and uses the correct route.',
            'Training paths contain the scenarios in the intended order.',
            'Assignments exist for the users who should see the training on their dashboard.',
            'Certification questions have valid options, one correct answer key, and a clear explanation.',
            'Summary and results screens show the expected progress after a test user completes training.',
        ],
    ],
    [
        'heading' => 'Good Operating Habits',
        'icon' => 'bi-check2-square',
        'items' => [
            'Build scenarios around real screen workflows rather than generic reading tasks.',
            'Keep scenario and certification codes stable once users have progress or attempts.',
            'Assign training deliberately so user dashboards stay focused and meaningful.',
            'Validate new scenarios before assigning them to users.',
            'Use certifications as final module checks after the guided scenarios have been completed.',
        ],
    ],
];
$note = 'This overview explains the Training module concept. Use the normal Help button on each screen for screen-specific instructions.';
require __DIR__ . '/_ScreenHelpTemplate.php';
