<?php
declare(strict_types=1);

$title = 'Test Script Catalogue';
$icon = 'bi-journal-text';
$intro = 'Use the Test Script Catalogue to review and maintain the script definitions that testers run from the testing module.';
$sections = [
    [
        'heading' => 'Purpose Of This Screen',
        'icon' => 'bi-journals',
        'items' => [
            '<strong>The catalogue is the master script list.</strong> It defines script code, title, module, target screen, steps, expected results, and reset guidance.',
            '<strong>Built-in scripts remain the fallback.</strong> Editable overrides can change wording without changing PHP source code.',
            '<strong>Module consistency matters.</strong> Use the shared CBMS module names so testing, training, menus, and security reporting line up.',
        ],
    ],
    [
        'heading' => 'Catalogue Actions',
        'icon' => 'bi-lightning-charge',
        'items' => [
            '<strong>Create Script</strong> adds a new custom script to the catalogue.',
            '<strong>Edit</strong> opens the script form to update metadata, steps, expected outcomes, prerequisites, and reset notes.',
            '<strong>Reset</strong> restores a built-in script to its default wording when custom overrides are no longer wanted.',
            '<strong>Delete</strong> removes custom scripts. Use carefully because assignments and run history depend on stable script codes.',
        ],
    ],
    [
        'heading' => 'Good Catalogue Practice',
        'icon' => 'bi-check2-circle',
        'items' => [
            'Keep script codes stable after assignments or run history exist.',
            'Write steps as clear tester actions, not implementation notes.',
            'Describe expected visible results and expected data results separately where possible.',
            'Use prerequisites to make setup assumptions explicit before testers start the script.',
        ],
    ],
];
$note = 'Administrators should treat script codes like stable identifiers. Changing or deleting codes can make historical results harder to interpret.';
require __DIR__ . '/_ScreenHelpTemplate.php';
