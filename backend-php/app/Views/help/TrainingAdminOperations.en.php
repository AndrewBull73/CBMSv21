<?php
declare(strict_types=1);

$title = 'Training Operations';
$icon = 'bi-clipboard2-check';
$intro = 'Use Training Operations to turn configured training scenarios into practical learner assignments, trainer-led sessions, evidence records, and operational follow-up.';
$sections = [
    [
        'heading' => 'Purpose Of This Screen',
        'icon' => 'bi-compass',
        'items' => [
            '<strong>Training Operations is the trainer workspace.</strong> Use it after scenarios and steps have been configured. It does not define the guided overlay itself; it decides who receives training, how courses are grouped, whether a live session is being run, and what follow-up is needed.',
            '<strong>Courses/paths, assignments, and sessions are separate concepts.</strong> A course/path is the reusable sequence of scenarios. An assignment puts training on a learner dashboard. A session records a trainer-led event such as a workshop or classroom run-through.',
            '<strong>The selected course/path is the working context.</strong> Choose it at the top of the screen before using Assign Users or reviewing open assignments. The Assign Users tab uses that selected course/path automatically.',
        ],
    ],
    [
        'heading' => 'Recommended Workflow',
        'icon' => 'bi-arrow-repeat',
        'items' => [
            '<strong>1. Create or select a course/path.</strong> Use Courses / Paths to group scenario codes into the recommended learning sequence for a module, role, rollout, or audience.',
            '<strong>2. Assign learners.</strong> Use Assign Users to select Workflow User Groups or individual users. Assigning a path gives learners the full course; selecting a scenario narrows the assignment to one targeted exercise.',
            '<strong>3. Run sessions when training is trainer-led.</strong> Use Sessions for scheduled events, instructors, attendance-style participants, notes, and evidence. Sessions do not replace learner dashboard assignments.',
            '<strong>4. Monitor and support learners.</strong> Use the Support tab to review stuck progress or requests for help, then resolve only after confirming the learner can continue or the issue has been handled.',
            '<strong>5. Review cleanup records.</strong> Use Cleanup to track sample data, test evidence, or training artefacts that may need follow-up after practice exercises.',
        ],
    ],
    [
        'heading' => 'Courses / Paths Tab',
        'icon' => 'bi-signpost-split',
        'items' => [
            '<strong>Path Code</strong> is the stable identifier for the course. Keep it short and meaningful because assignments and path scenario rows depend on it.',
            '<strong>Path Title and Audience</strong> describe what the course is for. Use the audience to make rollout intent clear, such as Finance Officers, Approvers, Procurement Users, or Implementation Team.',
            '<strong>Scenario Codes</strong> is the ordered list of training scenarios in the course. Put one scenario code per line or use the accepted separator pattern already used on the screen. The order controls the learning sequence.',
            '<strong>Active</strong> indicates whether the course/path is available for current use. Inactive paths should generally be kept for history rather than deleted.',
        ],
    ],
    [
        'heading' => 'Assign Users Tab',
        'icon' => 'bi-person-check',
        'items' => [
            '<strong>Use this tab when learners need training on their Training Dashboard.</strong> Assignments are user-specific records that tell CBMS what each learner should complete.',
            '<strong>Workflow User Groups</strong> let you assign every active member of a reusable group in one step. This is best for module rollouts, role-based training, and repeated implementation audiences.',
            '<strong>Individual user search</strong> is useful for exceptions, small ad hoc assignments, or adding a learner who is not in the selected group.',
            '<strong>Scenario scope controls what is assigned.</strong> Leave Scenario as <em>All scenarios in selected course/path</em> to assign the full course. Choose a scenario only when the learner needs one targeted exercise.',
            '<strong>Due Date is optional.</strong> Leave it blank when the assignment is open-ended, or set it when the training has a rollout deadline.',
            '<strong>Duplicate protection is built in.</strong> If the same user already has an open assignment for the same course/path and scenario scope, CBMS skips that duplicate and reports it in the flash message.',
            '<strong>Remove</strong> cancels an open assignment so it no longer appears as active learner work, while preserving the assignment history for audit and troubleshooting.',
        ],
    ],
    [
        'heading' => 'Sessions Tab',
        'icon' => 'bi-calendar-check',
        'items' => [
            '<strong>Use sessions for trainer-led training events.</strong> A session can represent a classroom session, online workshop, supervised practice run, or implementation support event.',
            '<strong>Session participants are not the same as learner assignments.</strong> Participants record attendance or involvement in the event. Assign Users is still the main way to place training on learner dashboards.',
            '<strong>Session Code is optional.</strong> Leave it blank and CBMS will generate a unique code when the session is saved. Enter a code only when you need to use a specific local naming convention.',
            '<strong>Instructor</strong> is selected using the same user search pattern as Assign Users. Select the trainer or facilitator who is leading the session.',
            '<strong>Selected Course / Path</strong> follows the working course/path at the top of Training Operations. Change the course/path there before creating the session.',
            '<strong>Session Participants</strong> are selected with user search. They are attendees for the trainer-led event, not dashboard assignments.',
            '<strong>Add participants to their Training Dashboard</strong> when learners need to launch the same course/path or scenario from their own Training Dashboard. This creates the learner assignment records while keeping the session as the trainer-led event record.',
            '<strong>Scheduled Date/Time is optional but must be valid if entered.</strong> Leave it blank when the session timing is not yet confirmed.',
            '<strong>Session Status</strong> tracks whether the session is planned, running, completed, or cancelled.',
            '<strong>Edit from the Session List</strong> when session details need to change. The selected session is loaded back into the form and the session code is locked so saving updates the existing session.',
            '<strong>Session Dashboard</strong> is used to review participants, evidence, and follow-up for a specific trainer-led event.',
        ],
    ],
    [
        'heading' => 'Support Tab',
        'icon' => 'bi-life-preserver',
        'items' => [
            '<strong>Use Support for learners who cannot continue normally.</strong> It helps trainers identify stuck progress, failed checkpoints, or other training issues that need intervention.',
            '<strong>Resolve support items carefully.</strong> Resolve only after confirming the issue has been handled, the learner can continue, or the training record has been corrected.',
            '<strong>Support is operational follow-up, not course setup.</strong> If many users are stuck on the same step, check the scenario route, target element ID, instruction text, and validation output.',
        ],
    ],
    [
        'heading' => 'Cleanup Tab',
        'icon' => 'bi-tags',
        'items' => [
            '<strong>Cleanup tags help manage training artefacts.</strong> Use them to track sample data, evidence, or records created during practice that may need review after training.',
            '<strong>Cleanup status shows follow-up state.</strong> Tagged or open items need review; cleaned or resolved items indicate follow-up has been completed.',
            '<strong>Use cleanup as part of rollout governance.</strong> It gives trainers and administrators a place to record operational clean-up rather than relying on informal notes.',
        ],
    ],
    [
        'heading' => 'Common Usage Patterns',
        'icon' => 'bi-diagram-3',
        'items' => [
            '<strong>Role rollout:</strong> create a course/path for the module, select a Workflow User Group for the role, assign the full path, then schedule one or more instructor sessions if live training is required.',
            '<strong>Targeted remediation:</strong> select the course/path, choose one scenario in Scenario scope, assign only the users who need extra practice, and leave the rest of the course untouched.',
            '<strong>Implementation workshop:</strong> assign the course to attendees first so dashboards are ready, then create a session to record the event, instructor, participants, notes, and evidence.',
            '<strong>Ongoing administration:</strong> review Open Assignments for active learner work, remove assignments that were created in error, and use Support and Cleanup for follow-up.',
        ],
    ],
];
$note = 'Access to Training Operations should be limited to Training Administration users because this screen controls learner assignments, session records, evidence, support follow-up, and operational cleanup.';
require __DIR__ . '/_TrainingHelp.php';
